<?php

namespace App\Services;

use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\AppointmentReplacement;
use App\Models\Clinic;
use App\Models\IssuedPrescription;
use App\Models\Patient;
use App\Models\PatientBill;
use App\Models\PatientBillClinicalReview;
use App\Models\PatientBillEntry;
use App\Models\PatientBillingDocument;
use App\Models\PatientBillRequest;
use App\Models\PatientBillTask;
use App\Models\PatientCashClosing;
use App\Models\PatientTransaction;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitContinuation;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientBillingService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public static function money(string|int|float $amount): int
    {
        if (is_float($amount)) {
            throw ValidationException::withMessages(['amount' => 'Kirim nominal pecahan sebagai teks desimal agar presisi tetap utuh.']);
        }
        $value = (string) $amount;
        if (! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
            throw ValidationException::withMessages(['amount' => 'Gunakan nominal positif dengan maksimal dua angka desimal.']);
        }

        return ((int) $parts[1]) * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function amount(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Caller determines clinic from an authorized resource; all links are checked again. */
    public function forAppointment(Appointment $appointment): PatientBill
    {
        return DB::transaction(function () use ($appointment): PatientBill {
            Clinic::query()->lockForUpdate()->findOrFail($appointment->clinic_id);
            $current = Appointment::query()->where('clinic_id', $appointment->clinic_id)->lockForUpdate()->findOrFail($appointment->id);
            $origin = $current;
            $handoff = VisitContinuation::query()->where('clinic_id', $current->clinic_id)->where('target_appointment_id', $current->id)->where('protected_service', true)->first();
            if ($handoff !== null) {
                $origin = $handoff->root->appointment;
            }
            $seen = [];
            while (($link = AppointmentReplacement::query()->where('new_appointment_id', $origin->id)->first()) !== null) {
                abort_if(isset($seen[$origin->id]), 409, 'Rangkaian booking tidak konsisten.');
                $seen[$origin->id] = true;
                $origin = $link->original;
                abort_unless($origin->clinic_id === $current->clinic_id && $origin->patient_id === $current->patient_id, 409);
            }
            abort_unless($origin->clinic_id === $current->clinic_id && $origin->patient_id === $current->patient_id && Patient::withoutGlobalScopes()->where('clinic_id', $current->clinic_id)->whereKey($current->patient_id)->exists(), 409);
            $bill = PatientBill::query()->firstOrCreate(['root_appointment_id' => $origin->id], ['clinic_id' => $origin->clinic_id, 'patient_id' => $origin->patient_id, 'service_name' => $origin->service_name, 'original_basis_minor' => self::money($origin->service_price)]);
            if ($bill->bill_number === null) {
                $bill->bill_number = 'BILL-'.$bill->clinic_id.'-'.str_pad((string) $bill->id, 8, '0', STR_PAD_LEFT);
                $bill->save();
            }
            $bill->appointments()->syncWithoutDetaching([$origin->id, $current->id]);

            return $bill;
        }, 3);
    }

    public function appointmentReplaced(Appointment $old, Appointment $new): void
    {
        $bill = PatientBill::query()->where('clinic_id', $old->clinic_id)->whereHas('appointments', fn ($query) => $query->whereKey($old->id))->first();
        if ($bill !== null) {
            abort_unless($new->clinic_id === $bill->clinic_id && $new->patient_id === $bill->patient_id, 409);
            $bill->appointments()->syncWithoutDetaching([$new->id]);
        }
    }

    public function continuationBooked(VisitContinuation $handoff, Appointment $appointment): void
    {
        if ($handoff->protected_service) {
            $this->forAppointment($appointment);
        }
    }

    public function visitCompleted(Visit $visit, User $actor): void
    {
        $bill = $this->forAppointment($visit->appointment);
        if ($bill->clinicalReviews()->where('auto_source_visit_id', $visit->id)->exists()) {
            return;
        }
        $record = $visit->medicalRecord;
        if ($record === null || $record->status !== MedicalRecordStatus::Final || $record->patient_id !== $visit->patient_id || $record->doctor_id !== $visit->doctor_id) {
            $bill->review_required = true;
            $bill->save();
            $this->refreshTask($bill);

            return;
        }
        $amount = self::money($visit->service_price);
        $review = $bill->clinicalReviews()->create(['clinic_id' => $bill->clinic_id, 'visit_id' => $visit->id, 'author_user_id' => $record->created_by_user_id, 'auto_source_visit_id' => $visit->id, 'record_version' => $record->lock_version,
            'lines' => [['service_id' => $visit->service_id, 'service_name' => $visit->service_name, 'legal_unit_minor' => $amount, 'quantity' => 1, 'provided_minor' => $amount]], 'authorized_minor' => $amount,
            'clinical_evidence' => 'Completed Visit and finalized documentation.', 'request_id' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'completed-visit:'.$visit->id)]);
        if ($bill->finalized_at === null) {
            $this->finalizeFromReview($bill, $review, $actor);
        } elseif ($this->base($bill) !== $amount) {
            $bill->review_required = true;
            $bill->save();
            $this->refreshTask($bill);
        }
    }

    /** @param array<string,mixed> $data */
    public function recordClinicalReview(Visit $visit, User $actor, array $data): PatientBillClinicalReview
    {
        return DB::transaction(function () use ($visit, $actor, $data): PatientBillClinicalReview {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $visit = Visit::query()->where('clinic_id', $visit->clinic_id)->lockForUpdate()->findOrFail($visit->id);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            abort_unless($actor->can('issue', [IssuedPrescription::class, $visit]), 403);
            if (! in_array($visit->status, [VisitStatus::Completed, VisitStatus::Interrupted], true) || $visit->medicalRecord?->status !== MedicalRecordStatus::Final) {
                throw ValidationException::withMessages(['clinical_review' => 'Sahkan dokumentasi pelayanan sebelum validasi tagihan klinis.']);
            }
            abort_unless((int) $data['record_version'] === $visit->medicalRecord->lock_version, 409, 'Dokumentasi berubah. Tinjau ulang layanan yang diberikan.');
            $bill = $this->forAppointment($visit->appointment);
            $fingerprint = $this->fingerprint($bill, $actor, 'clinical-review', $data);
            $existing = PatientBillClinicalReview::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->patient_bill_id === $bill->id && hash_equals($existing->request_fingerprint, $fingerprint), 409);

                return $existing;
            }
            $previous = $bill->clinicalReviews()->where('visit_id', $visit->id)->latest('id')->first();
            if ($previous !== null && $previous->record_version >= $visit->medicalRecord->lock_version) {
                abort(409, 'Perubahan layanan pada catatan final memerlukan amendment RME terlebih dahulu.');
            }
            $base = self::money($data['provided_base_amount']);
            $maximum = self::money($visit->service_price);
            if ($base > $maximum || mb_strlen(trim($data['clinical_evidence'] ?? '')) < 10) {
                throw ValidationException::withMessages(['provided_base_amount' => 'Nominal diberikan tidak boleh melampaui snapshot sah; penilaian klinis beralasan wajib dicatat.']);
            }
            $lines = [['service_id' => $visit->service_id, 'service_name' => $visit->service_name, 'legal_unit_minor' => $maximum, 'quantity' => 1, 'provided_minor' => $base]];
            $total = $base;
            foreach ($data['additional_services'] ?? [] as $item) {
                if (empty($item['service_id'])) {
                    continue;
                }
                if (empty($data['additional_fees_consented']) || ! app(SubscriptionService::class)->allowsOperationalWrites($visit->clinic)) {
                    throw ValidationException::withMessages(['additional_fees_consented' => 'Layanan tambahan memerlukan hak operasional aktif dan persetujuan biaya pasien.']);
                }
                $service = Service::query()->where('clinic_id', $visit->clinic_id)->where('is_active', true)->whereKeyNot($visit->service_id)->whereHas('doctors', fn ($doctors) => $doctors->whereKey($visit->doctor_id))->findOrFail($item['service_id']);
                $price = self::money($service->price);
                if (self::money($item['expected_unit_price']) !== $price) {
                    abort(409, 'Tarif layanan tambahan berubah. Tinjau dan konfirmasikan kembali.');
                }
                $quantity = (int) $item['quantity'];
                if ($quantity < 1 || $quantity > 9999) {
                    throw ValidationException::withMessages(['additional_services' => 'Jumlah layanan tidak sah.']);
                }
                $provided = $price * $quantity;
                if ($provided > 99999999999999 - $total) {
                    throw ValidationException::withMessages(['additional_services' => 'Total melampaui presisi nominal yang didukung.']);
                }
                $total += $provided;
                $lines[] = ['service_id' => $service->id, 'service_name' => $service->name, 'legal_unit_minor' => $price, 'quantity' => $quantity, 'provided_minor' => $provided];
            }
            $review = $bill->clinicalReviews()->create(['clinic_id' => $bill->clinic_id, 'visit_id' => $visit->id, 'author_user_id' => $actor->id, 'record_version' => $visit->medicalRecord->lock_version, 'lines' => $lines,
                'authorized_minor' => $total, 'clinical_evidence' => $data['clinical_evidence'], 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
            if ($bill->finalized_at === null) {
                $this->finalizeFromReview($bill, $review, $actor);
            } else {
                $bill->review_required = $this->base($bill) !== $total;
                $bill->save();
                $this->refreshTask($bill);
            }
            $this->auditLogger->recordForClinic($bill->clinic, 'patient_bill.clinical_reviewed', $bill, ['review_id' => $review->id, 'visit_id' => $visit->id], $actor);

            return $review;
        }, 3);
    }

    private function finalizeFromReview(PatientBill $bill, PatientBillClinicalReview $review, User $actor): void
    {
        abort_unless($bill->id === $review->patient_bill_id && $bill->clinic_id === $review->clinic_id, 409);
        if ($bill->finalized_at !== null) {
            return;
        }
        $bill->finalized_at = now();
        $bill->review_required = false;
        $bill->lock_version++;
        $bill->save();
        $entry = $this->entry($bill, $actor, 'BILL_FINALIZED', ['billed_delta_minor' => $review->authorized_minor, 'details' => ['clinical_review_id' => $review->id]], (string) Str::uuid());
        $this->allocateDeposits($bill, $actor);
        $this->document($entry, 'BILL_STATEMENT', $actor);
        $this->refreshTask($bill);
    }

    /** @return array{billed_minor:int,paid_minor:int,writeoff_minor:int,due_minor:int,excess_minor:int,unallocated_minor:int,status:string,refund_status:string} */
    public function balances(PatientBill $bill): array
    {
        $b = (int) $bill->entries()->sum('billed_delta_minor');
        $p = (int) $bill->entries()->sum('paid_delta_minor');
        $w = (int) $bill->entries()->sum('writeoff_delta_minor');
        $cash = (int) $bill->entries()->sum('cash_delta_minor');
        $due = max($b - $p - $w, 0);
        $excess = max($p - $b, 0);
        $unallocated = max($cash - $p, 0);
        $status = $bill->finalized_at === null ? 'DRAFT' : ($b === 0 ? 'NO_PAYMENT_DUE' : ($due > 0 ? ($p === 0 ? 'UNPAID' : 'PARTIALLY_PAID') : ($w > 0 ? 'SETTLED' : 'PAID')));
        $refund = $bill->requests()->where('kind', 'REFUND')->where('status', 'APPROVED')->exists() ? 'PENDING' : (($excess > 0 || ($bill->finalized_at !== null && $unallocated > 0)) ? 'REFUND_DUE' : ($bill->requests()->where('kind', 'REFUND')->where('status', 'COMPLETED')->exists() ? 'COMPLETED' : 'NONE'));

        return ['billed_minor' => $b, 'paid_minor' => $p, 'writeoff_minor' => $w, 'due_minor' => $due, 'excess_minor' => $excess, 'unallocated_minor' => $unallocated, 'status' => $status, 'refund_status' => $refund];
    }

    /** @param array<string,mixed> $data */
    public function recordTransaction(PatientBill $bill, User $actor, array $data): PatientTransaction
    {
        try {
            return DB::transaction(function () use ($bill, $actor, $data): PatientTransaction {
                $bill = $this->lockBill($bill);
                $actor = $actor->fresh();
                abort_unless($actor?->can('operate', $bill), 403);
                $fingerprint = $this->fingerprint($bill, $actor, 'receive', $data);
                $existing = PatientTransaction::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
                if ($existing !== null) {
                    abort_unless($existing->patient_bill_id === $bill->id && hash_equals($existing->request_fingerprint, $fingerprint), 409);

                    return $existing;
                }
                $amount = self::money($data['amount']);
                if (! in_array($data['method'], ['CASH', 'QRIS', 'TRANSFER'], true)) {
                    throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak sah.']);
                } if ($amount <= 0) {
                    throw ValidationException::withMessages(['amount' => 'Nominal harus lebih dari nol.']);
                }
                $balance = $this->balances($bill);
                $deposit = ($data['kind'] ?? 'PAYMENT') === 'DEPOSIT';
                if ($deposit) {
                    $latest = $bill->appointments()->latest('patient_bill_appointments.id')->firstOrFail();
                    if ($bill->finalized_at !== null || ! $latest->service->allows_patient_deposit || $latest->queue->status->isTerminal()) {
                        throw ValidationException::withMessages(['kind' => 'Uang muka hanya untuk layanan yang diizinkan dan booking yang masih sah sebelum tagihan final.']);
                    }
                } elseif ($bill->finalized_at === null || $amount > $balance['due_minor']) {
                    throw ValidationException::withMessages(['amount' => 'Pembayaran biasa tidak boleh melebihi sisa tagihan final. Transfer yang benar-benar cair kemudian tetap dicatat saat verifikasi.']);
                }
                $transaction = PatientTransaction::query()->create(['clinic_id' => $bill->clinic_id, 'patient_bill_id' => $bill->id, 'kind' => $deposit ? 'DEPOSIT' : 'PAYMENT', 'method' => $data['method'], 'amount_minor' => $amount,
                    'recorded_by_user_id' => $actor->id, 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
                if ($data['method'] === 'CASH') {
                    if (empty($data['funds_received_confirmed'])) {
                        throw ValidationException::withMessages(['funds_received_confirmed' => 'Konfirmasikan uang tunai benar-benar diterima.']);
                    }
                    $this->verifyLocked($bill, $transaction, $actor, $data['verification_reference'] ?? 'Cash received and counted.');
                }
                $this->refreshTask($bill);
                $this->auditLogger->recordForClinic($bill->clinic, 'patient_payment.recorded', $transaction, ['kind' => $transaction->kind, 'method' => $transaction->method, 'status' => $transaction->status], $actor);

                return $transaction;
            }, 3);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['amount']) && $actor->can('operate', $bill)) {
                $this->auditLogger->recordForClinic($bill->clinic, 'patient_payment.amount_rejected', $bill, ['category' => 'amount_validation'], $actor);
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data */
    public function verifyTransaction(PatientTransaction $transaction, User $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($transaction, $actor, $action, $data): void {
            $bill = $this->lockBill($transaction);
            $actor = $actor->fresh();
            abort_unless($actor?->can('operate', $bill), 403);
            $transaction = PatientTransaction::query()->where('patient_bill_id', $bill->id)->lockForUpdate()->findOrFail($transaction->id);
            if ($action === 'verify' && $transaction->status === 'VERIFIED') {
                return;
            }
            if ($action === 'fail' && $transaction->status === 'FAILED') {
                return;
            }
            if ($transaction->status !== 'PENDING_VERIFICATION') {
                abort(409, 'Transaksi sudah diputuskan. Gunakan koreksi berizin.');
            }
            if ($action === 'verify') {
                if (empty($data['funds_received_confirmed']) || blank($data['verification_reference'] ?? null)) {
                    throw ValidationException::withMessages(['funds_received_confirmed' => 'Verifikasi dana nyata dan referensi bukti wajib dicatat.']);
                }
                $this->verifyLocked($bill, $transaction, $actor, $data['verification_reference']);
            } elseif ($action === 'fail') {
                if (blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Alasan kegagalan verifikasi wajib diisi.']);
                }
                $transaction->forceFill(['status' => 'FAILED', 'failed_at' => now(), 'failure_reason' => $data['reason']])->save();
            } else {
                abort(404);
            }
            $this->refreshTask($bill);
            $this->auditLogger->recordForClinic($bill->clinic, 'patient_payment.'.$action, $transaction, [], $actor);
        }, 3);
    }

    private function verifyLocked(PatientBill $bill, PatientTransaction $transaction, User $actor, string $reference): void
    {
        $balance = $this->balances($bill);
        $allocation = $transaction->kind === 'DEPOSIT' || $bill->finalized_at === null ? 0 : min($transaction->amount_minor, $balance['due_minor']);
        $transaction->forceFill(['status' => 'VERIFIED', 'verified_by_user_id' => $actor->id, 'verified_at' => now(), 'verification_reference' => $reference])->save();
        $entry = $this->entry($bill, $actor, 'CASH_RECEIVED', ['paid_delta_minor' => $allocation, 'cash_delta_minor' => $transaction->amount_minor, 'cash_method' => $transaction->method,
            'patient_transaction_id' => $transaction->id, 'cash_receipt_transaction_id' => $transaction->id, 'details' => ['kind' => $transaction->kind]], (string) Str::uuid());
        $this->document($entry, $transaction->kind === 'DEPOSIT' ? 'DEPOSIT_RECEIPT' : 'PAYMENT_RECEIPT', $actor);
    }

    /** @return array{net_minor:int,allocated_minor:int,unallocated_minor:int,reserved_minor:int,reserved_allocated_minor:int,reserved_unallocated_minor:int} */
    public function funds(PatientTransaction $transaction, ?int $excludeRequestId = null): array
    {
        $entries = PatientBillEntry::query()->where('patient_transaction_id', $transaction->id);
        $net = (int) (clone $entries)->sum('cash_delta_minor');
        $allocated = (int) (clone $entries)->sum('paid_delta_minor');
        $reserved = PatientBillRequest::query()->where('patient_transaction_id', $transaction->id)->where('kind', 'REFUND')->where('status', 'APPROVED')->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId));
        $reservedAllocated = (int) (clone $reserved)->where('refund_mode', 'EXCESS_ALLOCATED')->sum('amount_minor');
        $reservedUnallocated = (int) (clone $reserved)->where('refund_mode', 'UNALLOCATED')->sum('amount_minor');

        return ['net_minor' => $net, 'allocated_minor' => $allocated, 'unallocated_minor' => max($net - $allocated, 0), 'reserved_minor' => $reservedAllocated + $reservedUnallocated, 'reserved_allocated_minor' => $reservedAllocated, 'reserved_unallocated_minor' => $reservedUnallocated];
    }

    private function allocateDeposits(PatientBill $bill, User $actor): void
    {
        foreach ($bill->transactions()->where('kind', 'DEPOSIT')->where('status', 'VERIFIED')->orderBy('id')->lockForUpdate()->get() as $transaction) {
            $funds = $this->funds($transaction);
            $allocation = min($this->balances($bill)['due_minor'], max($funds['unallocated_minor'] - $funds['reserved_unallocated_minor'], 0));
            if ($allocation > 0) {
                $this->entry($bill, $actor, 'DEPOSIT_ALLOCATED', ['paid_delta_minor' => $allocation, 'patient_transaction_id' => $transaction->id, 'details' => []], (string) Str::uuid());
            }
        }
    }

    /** @param array<string,mixed> $data */
    public function submitRequest(PatientBill $bill, User $actor, array $data): PatientBillRequest
    {
        return DB::transaction(function () use ($bill, $actor, $data): PatientBillRequest {
            $bill = $this->lockBill($bill);
            $actor = $actor->fresh();
            abort_unless($actor?->can('operate', $bill), 403);
            $kind = $data['kind'];
            if (! in_array($kind, ['DISCOUNT', 'DISCOUNT_REVERSAL', 'WRITE_OFF', 'WRITE_OFF_REVERSAL', 'REFUND', 'PAYMENT_REVERSAL', 'CHARGE_CORRECTION', 'ALLOCATE_FUNDS'], true)) {
                abort(422, 'Jenis pengajuan tidak sah.');
            }
            if (in_array($kind, ['DISCOUNT', 'REFUND'], true)) {
                abort_unless($actor->hasRole('Receptionist'), 403);
            }
            $fingerprint = $this->fingerprint($bill, $actor, 'request', $data);
            $existing = PatientBillRequest::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->patient_bill_id === $bill->id && hash_equals($existing->request_fingerprint, $fingerprint), 409);

                return $existing;
            }
            if (blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Alasan pengajuan wajib dicatat.']);
            }
            if ($bill->finalized_at === null && ! in_array($kind, ['REFUND', 'PAYMENT_REVERSAL'], true)) {
                throw ValidationException::withMessages(['kind' => 'Tagihan belum final. Tinjau layanan atau dana deposit terlebih dahulu.']);
            }
            $review = null;
            $transaction = null;
            if ($kind === 'CHARGE_CORRECTION') {
                $review = $bill->clinicalReviews()->latest('id')->firstOrFail();
                abort_unless($review->id === (int) ($data['clinical_review_id'] ?? 0), 409, 'Gunakan validasi layanan terbaru yang disahkan dokter.');
                $amount = abs($review->authorized_minor - $this->base($bill));
            } else {
                $amount = self::money($data['amount']);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Nominal pengajuan harus lebih dari nol.']);
            }
            if (in_array($kind, ['REFUND', 'PAYMENT_REVERSAL', 'ALLOCATE_FUNDS'], true)) {
                $transaction = $bill->transactions()->where('status', 'VERIFIED')->lockForUpdate()->findOrFail($data['patient_transaction_id']);
            }
            $mode = $kind === 'REFUND' ? ($data['refund_mode'] ?? null) : null;
            if ($kind === 'REFUND' && ! in_array($mode, ['EXCESS_ALLOCATED', 'UNALLOCATED'], true)) {
                throw ValidationException::withMessages(['refund_mode' => 'Pilih pengembalian kelebihan alokasi atau dana belum dialokasikan.']);
            }
            $request = $bill->requests()->create(['clinic_id' => $bill->clinic_id, 'patient_transaction_id' => $transaction?->id, 'clinical_review_id' => $review?->id, 'kind' => $kind, 'refund_mode' => $mode, 'amount_minor' => $amount,
                'requested_by_user_id' => $actor->id, 'reason' => $data['reason'], 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
            $this->auditLogger->recordForClinic($bill->clinic, 'patient_bill.requested', $request, ['kind' => $kind], $actor);

            return $request;
        }, 3);
    }

    /** @return array{before:array<string,mixed>,after:array<string,mixed>,billed_delta_minor:int,paid_delta_minor:int,writeoff_delta_minor:int,cash_delta_minor:int,required_writeoff_reversal_minor:int} */
    public function previewRequest(PatientBillRequest $request): array
    {
        $bill = $request->bill;
        $before = $this->balances($bill);
        $b = 0;
        $p = 0;
        $w = 0;
        $cash = 0;
        $amount = $request->amount_minor;
        switch ($request->kind) {
            case 'DISCOUNT': $b = -$amount;
                break;
            case 'DISCOUNT_REVERSAL': $b = $amount;
                break;
            case 'WRITE_OFF': $w = $amount;
                break;
            case 'WRITE_OFF_REVERSAL': $w = -$amount;
                break;
            case 'CHARGE_CORRECTION': $b = $request->clinicalReview->authorized_minor - $this->base($bill);
                break;
            case 'ALLOCATE_FUNDS': $p = $amount;
                break;
            case 'REFUND': $p = $request->refund_mode === 'EXCESS_ALLOCATED' ? -$amount : 0;
                $cash = -$amount;
                break;
            case 'PAYMENT_REVERSAL':
                $funds = $this->funds($request->transaction, $request->id);
                $p = -max($amount - max($funds['unallocated_minor'] - $funds['reserved_unallocated_minor'], 0), 0);
                $cash = -$amount;
                break;
        }
        $afterB = $before['billed_minor'] + $b;
        $afterP = $before['paid_minor'] + $p;
        $afterW = $before['writeoff_minor'] + $w;
        $required = max($afterW - max($afterB - $afterP, 0), 0);
        $effectiveW = $afterW - $required;

        return ['before' => $before, 'after' => ['billed_minor' => $afterB, 'paid_minor' => $afterP, 'writeoff_minor' => $effectiveW, 'due_minor' => max($afterB - $afterP - $effectiveW, 0), 'excess_minor' => max($afterP - $afterB, 0)],
            'billed_delta_minor' => $b, 'paid_delta_minor' => $p, 'writeoff_delta_minor' => $w, 'cash_delta_minor' => $cash, 'required_writeoff_reversal_minor' => $required];
    }

    /** @param array<string,mixed> $data */
    public function decideRequest(PatientBillRequest $request, User $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($request, $actor, $action, $data): void {
            $bill = $this->lockBill($request);
            $actor = $actor->fresh();
            abort_unless($actor?->can('approve', $bill), 403);
            $request = PatientBillRequest::query()->where('patient_bill_id', $bill->id)->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'REQUESTED') {
                if (($action === 'approve' && in_array($request->status, ['APPROVED', 'APPLIED', 'COMPLETED'], true)) || ($action === 'reject' && $request->status === 'REJECTED')) {
                    return;
                }
                abort(409, 'Pengajuan sudah diputuskan.');
            }
            if ($request->kind === 'REFUND') {
                abort_if($request->requested_by_user_id === $actor->id, 403, 'Pengaju refund tidak boleh menyetujui pengajuannya sendiri, termasuk akun multi-role.');
            }
            if ($action === 'reject') {
                if (blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib dicatat.']);
                }
                $request->forceFill(['status' => 'REJECTED', 'approved_by_user_id' => $actor->id, 'approved_at' => now(), 'decision_reason' => $data['reason']])->save();
            } elseif ($action === 'approve') {
                $preview = $this->validateRequest($bill, $request);
                if ($request->kind === 'REFUND') {
                    $request->forceFill(['status' => 'APPROVED', 'approved_by_user_id' => $actor->id, 'approved_at' => now(), 'decision_reason' => $data['reason'] ?? null])->save();
                } else {
                    if ($preview['required_writeoff_reversal_minor'] > 0 && empty($data['reverse_writeoff_if_required'])) {
                        throw ValidationException::withMessages(['reverse_writeoff_if_required' => 'Koreksi ini juga memerlukan pembalikan write-off yang ditampilkan pada preview. Konfirmasikan secara eksplisit.']);
                    }
                    $values = ['billed_delta_minor' => $preview['billed_delta_minor'], 'paid_delta_minor' => $preview['paid_delta_minor'], 'writeoff_delta_minor' => $preview['writeoff_delta_minor'],
                        'cash_delta_minor' => $preview['cash_delta_minor'], 'cash_method' => $request->kind === 'PAYMENT_REVERSAL' ? $request->transaction->method : null,
                        'patient_transaction_id' => $request->patient_transaction_id, 'patient_bill_request_id' => $request->id, 'details' => ['clinical_review_id' => $request->clinical_review_id]];
                    $entry = $this->entry($bill, $actor, $request->kind, $values, (string) Str::uuid());
                    if ($preview['required_writeoff_reversal_minor'] > 0) {
                        $this->entry($bill, $actor, 'WRITE_OFF_REVERSAL', ['writeoff_delta_minor' => -$preview['required_writeoff_reversal_minor'], 'source_entry_id' => $entry->id, 'details' => ['approved_request_id' => $request->id]], (string) Str::uuid());
                    }
                    $request->forceFill(['status' => 'APPLIED', 'approved_by_user_id' => $actor->id, 'approved_at' => now(), 'completed_at' => now(), 'completed_by_user_id' => $actor->id, 'decision_reason' => $data['reason'] ?? null])->save();
                    if ($request->kind === 'CHARGE_CORRECTION') {
                        $bill->review_required = false;
                        $bill->save();
                    }
                    if ($request->kind === 'PAYMENT_REVERSAL') {
                        foreach ($bill->documents()->whereHas('entry', fn ($query) => $query->where('cash_receipt_transaction_id', $request->patient_transaction_id))->get() as $document) {
                            $document->status = 'CORRECTED';
                            $document->save();
                        }
                    }
                    $this->allocateDeposits($bill, $actor);
                    $this->document($entry, $request->kind === 'WRITE_OFF' ? 'WRITE_OFF_STATEMENT' : ($request->kind === 'PAYMENT_REVERSAL' ? 'CORRECTION_STATEMENT' : 'ADJUSTMENT_STATEMENT'), $actor);
                }
            } else {
                abort(404);
            }
            $this->refreshTask($bill);
            $this->auditLogger->recordForClinic($bill->clinic, 'patient_bill.request_'.$action, $request, ['kind' => $request->kind, 'status' => $request->status], $actor);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function completeRefund(PatientBillRequest $request, User $actor, array $data): void
    {
        DB::transaction(function () use ($request, $actor, $data): void {
            $bill = $this->lockBill($request);
            $actor = $actor->fresh();
            abort_unless($actor?->can('operate', $bill), 403);
            $request = PatientBillRequest::query()->where('patient_bill_id', $bill->id)->lockForUpdate()->findOrFail($request->id);
            if ($request->kind !== 'REFUND') {
                abort(422, 'Bukan pengajuan refund.');
            }
            if ($request->status === 'COMPLETED') {
                return;
            }
            if ($request->status !== 'APPROVED' || empty($data['actual_return_confirmed']) || blank($data['completion_reference'] ?? null)) {
                throw ValidationException::withMessages(['actual_return_confirmed' => 'Refund perlu approval dan konfirmasi uang benar-benar dikembalikan beserta referensi bukti.']);
            }
            $preview = $this->validateRequest($bill, $request);
            if (! in_array($data['payout_method'] ?? null, ['CASH', 'QRIS', 'TRANSFER'], true)) {
                throw ValidationException::withMessages(['payout_method' => 'Metode pengembalian tidak sah.']);
            }
            $entry = $this->entry($bill, $actor, 'REFUND_COMPLETED', ['paid_delta_minor' => $preview['paid_delta_minor'], 'cash_delta_minor' => -$request->amount_minor,
                'cash_method' => $data['payout_method'], 'patient_transaction_id' => $request->patient_transaction_id, 'patient_bill_request_id' => $request->id, 'details' => ['refund_mode' => $request->refund_mode]], (string) Str::uuid());
            $request->forceFill(['status' => 'COMPLETED', 'completed_by_user_id' => $actor->id, 'completed_at' => now(), 'completion_reference' => $data['completion_reference'], 'payout_method' => $data['payout_method']])->save();
            $this->document($entry, 'REFUND_RECEIPT', $actor);
            $this->refreshTask($bill);
            $this->auditLogger->recordForClinic($bill->clinic, 'patient_refund.completed', $request, ['entry_id' => $entry->id], $actor);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function correctBeforePayment(PatientBill $bill, User $actor, array $data): void
    {
        DB::transaction(function () use ($bill, $actor, $data): void {
            $bill = $this->lockBill($bill);
            $actor = $actor->fresh();
            abort_unless($actor?->can('operate', $bill), 403);
            if ($bill->transactions()->whereNotNull('verified_at')->exists()) {
                throw ValidationException::withMessages(['correction' => 'Koreksi setelah penerimaan memerlukan pengajuan dan approval Owner/Admin.']);
            }
            $fingerprint = $this->fingerprint($bill, $actor, 'correct-before-payment', $data);
            $existing = $bill->entries()->where('request_id', $data['request_id'])->first();
            if ($existing !== null) {
                abort_unless(hash_equals($existing->request_fingerprint, $fingerprint), 409);

                return;
            }
            $review = $bill->clinicalReviews()->latest('id')->firstOrFail();
            abort_unless($review->id === (int) $data['clinical_review_id'], 409, 'Gunakan dasar layanan terbaru yang disahkan dokter.');
            $delta = $review->authorized_minor - $this->base($bill);
            $before = $this->balances($bill);
            if ($before['billed_minor'] + $delta < 0 || $before['writeoff_minor'] > max($before['billed_minor'] + $delta - $before['paid_minor'], 0)) {
                throw ValidationException::withMessages(['correction' => 'Saldo memerlukan pembalikan diskon/write-off melalui approval dahulu.']);
            }
            $entry = $this->entry($bill, $actor, 'CHARGE_CORRECTION', ['billed_delta_minor' => $delta, 'details' => ['clinical_review_id' => $review->id, 'reason' => $data['reason'] ?? 'Validated billing basis correction.']], $data['request_id'], $fingerprint);
            $bill->review_required = false;
            $bill->save();
            $this->allocateDeposits($bill, $actor);
            $this->document($entry, 'ADJUSTMENT_STATEMENT', $actor);
            $this->refreshTask($bill);
        }, 3);
    }

    /** @return array<string,mixed> */
    private function validateRequest(PatientBill $bill, PatientBillRequest $request): array
    {
        $balance = $this->balances($bill);
        $amount = $request->amount_minor;
        $preview = $this->previewRequest($request);
        if ($request->kind === 'WRITE_OFF' && $amount > $balance['due_minor']) {
            throw ValidationException::withMessages(['amount' => 'Write-off tidak boleh melebihi sisa kewajiban.']);
        }
        if ($request->kind === 'WRITE_OFF_REVERSAL' && $amount > $balance['writeoff_minor']) {
            throw ValidationException::withMessages(['amount' => 'Pembalikan melebihi write-off yang masih berlaku.']);
        }
        if ($request->kind === 'DISCOUNT_REVERSAL') {
            $discount = -(int) $bill->entries()->whereIn('kind', ['DISCOUNT', 'DISCOUNT_REVERSAL'])->sum('billed_delta_minor');
            if ($amount > $discount) {
                throw ValidationException::withMessages(['amount' => 'Pembalikan melebihi diskon yang masih berlaku.']);
            }
        }
        if ($request->kind === 'CHARGE_CORRECTION') {
            $latest = $bill->clinicalReviews()->latest('id')->firstOrFail();
            abort_unless($latest->id === $request->clinical_review_id && abs($preview['billed_delta_minor']) === $amount, 409, 'Dasar koreksi berubah. Tinjau dan ajukan ulang.');
        }
        if ($request->patient_transaction_id !== null) {
            $transaction = $bill->transactions()->where('status', 'VERIFIED')->lockForUpdate()->findOrFail($request->patient_transaction_id);
            $funds = $this->funds($transaction, $request->id);
            if ($amount > $funds['net_minor'] - $funds['reserved_minor']) {
                throw ValidationException::withMessages(['amount' => 'Dana sumber sudah dipakai atau dicadangkan untuk pengembalian lain.']);
            }
            if ($request->kind === 'REFUND') {
                if ($request->refund_mode === 'UNALLOCATED' && $amount > $funds['unallocated_minor'] - $funds['reserved_unallocated_minor']) {
                    throw ValidationException::withMessages(['amount' => 'Refund deposit/dana belum dialokasikan melebihi saldo sumber.']);
                }
                if ($request->refund_mode === 'EXCESS_ALLOCATED') {
                    $reserved = (int) $bill->requests()->where('kind', 'REFUND')->where('status', 'APPROVED')->where('refund_mode', 'EXCESS_ALLOCATED')->whereKeyNot($request->id)->sum('amount_minor');
                    if ($amount > $funds['allocated_minor'] - $funds['reserved_allocated_minor'] || $amount + $reserved > $balance['excess_minor']) {
                        throw ValidationException::withMessages(['amount' => 'Refund alokasi hanya untuk kelebihan sesudah nota pengurang yang sah; bukan pembalikan pembayaran.']);
                    }
                }
            }
            if ($request->kind === 'ALLOCATE_FUNDS' && ($amount > $funds['unallocated_minor'] - $funds['reserved_unallocated_minor'] || $amount > $balance['due_minor'])) {
                throw ValidationException::withMessages(['amount' => 'Alokasi melebihi dana bebas atau sisa kewajiban.']);
            }
        }
        if ($preview['after']['billed_minor'] < 0 || $preview['after']['paid_minor'] < 0 || $preview['after']['writeoff_minor'] < 0) {
            throw ValidationException::withMessages(['amount' => 'Koreksi menghasilkan saldo tidak sah.']);
        }
        if ($request->kind !== 'REFUND') {
            $reserved = (int) $bill->requests()->where('kind', 'REFUND')->where('status', 'APPROVED')->where('refund_mode', 'EXCESS_ALLOCATED')->sum('amount_minor');
            if ($preview['after']['excess_minor'] < $reserved) {
                throw ValidationException::withMessages(['amount' => 'Koreksi memakai kelebihan dana yang sudah dicadangkan untuk refund. Tinjau refund terlebih dahulu.']);
            }
        }

        return $preview;
    }

    public function cancelUnstartedAppointment(Appointment $appointment, ?User $actor): void
    {
        $bill = PatientBill::query()->where('clinic_id', $appointment->clinic_id)->whereHas('appointments', fn ($query) => $query->whereKey($appointment->id))->first();
        if ($bill === null || $bill->finalized_at !== null || $bill->appointments()->whereHas('visit')->exists() || $bill->appointments()->where('status', 'BOOKED')->exists()) {
            return;
        }
        $bill->finalized_at = now();
        $bill->save();
        $entry = $this->entry($bill, $actor, 'BILL_FINALIZED', ['billed_delta_minor' => 0, 'details' => ['no_service_provided' => true]], (string) Str::uuid());
        $this->document($entry, 'BILL_STATEMENT', $actor);
        $this->refreshTask($bill);
    }

    /** @param array<string,mixed> $data */
    public function closeCash(Clinic $clinic, User $actor, array $data): PatientCashClosing
    {
        return DB::transaction(function () use ($clinic, $actor, $data): PatientCashClosing {
            $clinic = Clinic::query()->with('settings')->lockForUpdate()->findOrFail($clinic->id);
            $actor = $actor->fresh();
            abort_unless($actor?->is_active && $actor->clinic_id === $clinic->id && $actor->hasAnyRole(['Receptionist', 'Clinic Admin']) && $actor->can('appointment.update'), 403);
            $fingerprint = hash('sha256', json_encode([$clinic->id, $actor->id, $data], JSON_THROW_ON_ERROR));
            $existing = PatientCashClosing::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->clinic_id === $clinic->id && hash_equals($existing->request_fingerprint, $fingerprint), 409);

                return $existing;
            }
            $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
            $date = now($timezone)->toDateString();
            if (PatientCashClosing::query()->where('clinic_id', $clinic->id)->where('status', 'PENDING_REVIEW')->exists()) {
                throw ValidationException::withMessages(['closing' => 'Selesaikan review closing sebelumnya sebelum membuat closing berikutnya.']);
            }
            if (PatientCashClosing::query()->where('clinic_id', $clinic->id)->whereDate('business_date', $date)->where('status', 'APPROVED')->exists()) {
                throw ValidationException::withMessages(['closing' => 'Closing hari ini sudah disahkan. Transaksi sesudah cutoff masuk periode closing berikutnya.']);
            }
            $previous = PatientCashClosing::query()->where('clinic_id', $clinic->id)->where('status', 'APPROVED')->latest('id')->first();
            $last = (int) PatientBillEntry::query()->where('clinic_id', $clinic->id)->max('id');
            $entries = PatientBillEntry::query()->where('clinic_id', $clinic->id)->where('id', '>', $previous?->last_ledger_id ?? 0)->where('id', '<=', $last)->where('cash_delta_minor', '!=', 0)->orderBy('id')->get();
            $net = ['CASH' => 0, 'QRIS' => 0, 'TRANSFER' => 0];
            foreach ($entries as $entry) {
                $net[$entry->cash_method] += $entry->cash_delta_minor;
            }
            $opening = self::money($data['opening_cash']);
            $expected = ['CASH' => $opening + $net['CASH'], 'QRIS' => $net['QRIS'], 'TRANSFER' => $net['TRANSFER']];
            $counted = ['CASH' => self::money($data['counted_cash']), 'QRIS' => $this->signedMoney($data['counted_qris']), 'TRANSFER' => $this->signedMoney($data['counted_transfer'])];
            $openingChanged = $previous !== null && $opening !== (int) $previous->counted['CASH'];
            $variance = [];
            foreach ($expected as $method => $amount) {
                $variance[$method] = $counted[$method] - $amount;
            }
            $needsReview = $openingChanged || count(array_filter($variance, fn ($amount) => $amount !== 0)) > 0;
            if ($needsReview && blank($data['variance_reason'] ?? null)) {
                throw ValidationException::withMessages(['variance_reason' => 'Selisih kas/dana atau perubahan kas awal memerlukan alasan dan review Owner/Admin.']);
            }
            $snapshot = ['previous_last_ledger_id' => $previous?->last_ledger_id ?? 0, 'ledger_ids' => $entries->modelKeys(), 'net' => $net, 'opening_cash_minor' => $opening, 'expected' => $expected, 'variance' => $variance, 'opening_changed' => $openingChanged,
                'period_starts_at' => $previous?->cutoff_at?->toIso8601String() ?? $entries->first()?->occurred_at?->toIso8601String(), 'cutoff_at' => now()->toIso8601String(),
                'pending_payments' => PatientTransaction::query()->where('clinic_id', $clinic->id)->where('status', 'PENDING_VERIFICATION')->get(['id', 'patient_bill_id', 'method', 'amount_minor'])->toArray(),
                'pending_refunds' => PatientBillRequest::query()->where('clinic_id', $clinic->id)->where('kind', 'REFUND')->whereIn('status', ['REQUESTED', 'APPROVED'])->get(['id', 'patient_bill_id', 'status', 'amount_minor'])->toArray()];
            $closing = PatientCashClosing::query()->create(['clinic_id' => $clinic->id, 'business_date' => $date, 'timezone' => $timezone, 'status' => $needsReview ? 'PENDING_REVIEW' : 'APPROVED', 'previous_closing_id' => $previous?->id,
                'last_ledger_id' => $last, 'cutoff_at' => now(), 'snapshot' => $snapshot, 'counted' => $counted, 'variance_reason' => $data['variance_reason'] ?? null, 'created_by_user_id' => $actor->id,
                'approved_by_user_id' => $needsReview ? null : $actor->id, 'approved_at' => $needsReview ? null : now(), 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
            $this->auditLogger->recordForClinic($clinic, 'patient_cash.closing_created', $closing, ['status' => $closing->status], $actor);

            return $closing;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function reviewClosing(PatientCashClosing $closing, User $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($closing, $actor, $action, $data): void {
            Clinic::query()->lockForUpdate()->findOrFail($closing->clinic_id);
            $actor = $actor->fresh();
            abort_unless($actor?->is_active && $actor->clinic_id === $closing->clinic_id && $actor->hasRole('Clinic Admin') && $actor->can('revenue.view'), 403);
            $closing = PatientCashClosing::query()->where('clinic_id', $closing->clinic_id)->lockForUpdate()->findOrFail($closing->id);
            $target = $action === 'approve' ? 'APPROVED' : ($action === 'reject' ? 'REJECTED' : abort(404));
            if ($closing->status === $target) {
                return;
            }
            if ($closing->status !== 'PENDING_REVIEW' || blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['closing' => 'Closing final tidak dapat diubah; keputusan review memerlukan alasan.']);
            }
            $previous = PatientCashClosing::query()->where('clinic_id', $closing->clinic_id)->where('status', 'APPROVED')->latest('id')->first();
            abort_unless($closing->previous_closing_id === $previous?->id, 409, 'Periode closing berubah. Tinjau closing pendahulu.');
            $closing->forceFill(['status' => $target, 'approved_by_user_id' => $actor->id, 'approved_at' => $target === 'APPROVED' ? now() : null, 'review_reason' => $data['reason']])->save();
            $this->auditLogger->recordForClinic($closing->clinic, 'patient_cash.closing_'.$action, $closing, [], $actor);
        }, 3);
    }

    private function signedMoney(string|int $amount): int
    {
        $value = (string) $amount;

        return str_starts_with($value, '-') ? -self::money(substr($value, 1)) : self::money($value);
    }

    private function base(PatientBill $bill): int
    {
        return (int) $bill->entries()->whereIn('kind', ['BILL_FINALIZED', 'CHARGE_CORRECTION'])->sum('billed_delta_minor');
    }

    private function lockBill(PatientBill|PatientTransaction|PatientBillRequest $resource): PatientBill
    {
        Clinic::query()->lockForUpdate()->findOrFail($resource->clinic_id);

        return PatientBill::query()->where('clinic_id', $resource->clinic_id)->lockForUpdate()->findOrFail($resource instanceof PatientBill ? $resource->id : $resource->patient_bill_id);
    }

    /** @param array<string,mixed> $data */
    private function fingerprint(PatientBill $bill, User $actor, string $action, array $data): string
    {
        return hash('sha256', json_encode([$bill->id, $actor->id, $action, $data], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $values */
    private function entry(PatientBill $bill, ?User $actor, string $kind, array $values, string $requestId, ?string $fingerprint = null): PatientBillEntry
    {
        $entry = $bill->entries()->create(['clinic_id' => $bill->clinic_id, 'actor_user_id' => $actor?->id, 'kind' => $kind, 'details' => [], ...$values, 'occurred_at' => now(), 'request_id' => $requestId,
            'request_fingerprint' => $fingerprint ?? hash('sha256', json_encode([$bill->id, $actor?->id, $kind, $values], JSON_THROW_ON_ERROR))]);
        $bill->lock_version++;
        $bill->save();
        $this->refreshTask($bill);

        return $entry;
    }

    private function refreshTask(PatientBill $bill): void
    {
        $balance = $this->balances($bill);
        $codes = [];
        if ($bill->finalized_at !== null && $balance['due_minor'] > 0) {
            $codes[] = 'UNPAID';
        }
        if ($bill->finalized_at !== null && ($balance['excess_minor'] > 0 || $balance['unallocated_minor'] > 0)) {
            $codes[] = 'REFUND_OR_ALLOCATION_REVIEW';
        }
        if ($bill->review_required) {
            $codes[] = 'CLINICAL_BASIS_REVIEW';
        }
        PatientBillTask::query()->updateOrCreate(['patient_bill_id' => $bill->id], ['clinic_id' => $bill->clinic_id, 'status' => $codes === [] ? 'RESOLVED' : 'OPEN', 'reason_codes' => $codes, 'resolved_at' => $codes === [] ? now() : null]);
    }

    private function document(PatientBillEntry $entry, string $kind, ?User $actor): PatientBillingDocument
    {
        $bill = $entry->bill;
        $balance = $this->balances($bill);
        $snapshot = ['clinic' => $bill->clinic->only(['name', 'address', 'phone']), 'patient' => $bill->patient->only(['name', 'medical_record_number']), 'bill_number' => $bill->bill_number,
            'service_name' => $bill->service_name, 'lines' => $bill->clinicalReviews()->latest('id')->first()?->lines ?? [], 'kind' => $kind, 'amount_minor' => abs($entry->cash_delta_minor), 'cash_received_now_minor' => $entry->cash_delta_minor,
            'writeoff_now_minor' => $entry->writeoff_delta_minor, 'method' => $entry->cash_method, 'balances' => $balance, 'occurred_at' => $entry->occurred_at->toIso8601String(), 'timezone' => $bill->clinic->settings?->timezone ?? 'Asia/Jakarta'];
        $document = $bill->documents()->create(['clinic_id' => $bill->clinic_id, 'patient_bill_entry_id' => $entry->id, 'kind' => $kind, 'snapshot' => $snapshot, 'issued_at' => now()]);
        $document->document_number = 'FIN-'.$bill->clinic_id.'-'.str_pad((string) $document->id, 8, '0', STR_PAD_LEFT);
        $document->save();
        $this->auditLogger->recordForClinic($bill->clinic, 'patient_bill.document_issued', $document, ['kind' => $kind, 'entry_id' => $entry->id], $actor);

        return $document;
    }
}
