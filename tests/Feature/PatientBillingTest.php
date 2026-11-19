<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\PatientBill;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use App\Services\PatientBillingService;
use App\Services\QueueWorkflowService;
use App\Services\VisitWorkflowService;
use App\SubscriptionStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PatientBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 03:00:00', 'UTC'));
    }

    public function test_pending_transfer_and_multiple_methods_do_not_mark_unverified_funds_paid(): void
    {
        [$bill,$staff,,$visit] = $this->context();
        $service = app(PatientBillingService::class);
        $cash = $service->recordTransaction($bill, $staff, $this->payment('40000'));
        $transfer = $service->recordTransaction($bill, $staff, $this->payment('60000', 'TRANSFER'));
        $this->assertSame('PENDING_VERIFICATION', $transfer->status);
        $this->assertSame('PARTIALLY_PAID', $service->balances($bill)['status']);
        $this->assertSame(6000000, $service->balances($bill)['due_minor']);
        $this->assertSame(2, $bill->documents()->count());
        $service->verifyTransaction($transfer, $staff, 'verify', ['funds_received_confirmed' => true, 'verification_reference' => 'Verified synthetic bank credit']);
        $service->verifyTransaction($transfer, $staff, 'verify', ['funds_received_confirmed' => true, 'verification_reference' => 'Retry']);
        $this->assertSame('PAID', $service->balances($bill)['status']);
        $this->assertSame(10000000, (int) $bill->entries()->sum('cash_delta_minor'));
        $this->assertSame(3, $bill->documents()->count());
        $this->assertSame(VisitStatus::Completed, $visit->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->invalid(fn () => $service->recordTransaction($bill, $staff, $this->payment('1')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_payment.amount_rejected']);
    }

    public function test_refund_approval_is_not_cash_out_and_credit_then_excess_refund_does_not_create_debt(): void
    {
        [$bill,$staff,$admin] = $this->context('150000');
        $service = app(PatientBillingService::class);
        $payment = $service->recordTransaction($bill, $staff, $this->payment('150000'));
        $discount = $service->submitRequest($bill, $staff, $this->proposal('DISCOUNT', '50000'));
        $service->decideRequest($discount, $admin, 'approve', []);
        $refund = $service->submitRequest($bill, $staff, [...$this->proposal('REFUND', '50000'), 'patient_transaction_id' => $payment->id, 'refund_mode' => 'EXCESS_ALLOCATED']);
        $preview = $service->previewRequest($refund);
        $this->assertSame(0, $preview['after']['due_minor']);
        $this->assertSame(0, $preview['after']['excess_minor']);
        $service->decideRequest($refund, $admin, 'approve', []);
        $this->assertSame(15000000, (int) $bill->entries()->sum('cash_delta_minor'));
        $this->assertSame('PENDING', $service->balances($bill)['refund_status']);
        $service->completeRefund($refund, $staff, ['actual_return_confirmed' => true, 'completion_reference' => 'Synthetic confirmed return', 'payout_method' => 'TRANSFER']);
        $service->completeRefund($refund, $staff, ['actual_return_confirmed' => true, 'completion_reference' => 'Retry', 'payout_method' => 'TRANSFER']);
        $balance = $service->balances($bill);
        $this->assertSame(10000000, $balance['billed_minor']);
        $this->assertSame(10000000, $balance['paid_minor']);
        $this->assertSame(0, $balance['due_minor']);
        $this->assertSame(0, $balance['excess_minor']);
        $this->assertSame(10000000, (int) $bill->entries()->sum('cash_delta_minor'));
        $this->assertSame(1, $bill->entries()->where('kind', 'REFUND_COMPLETED')->count());
    }

    public function test_deposit_allocation_is_once_and_excess_deposit_refund_does_not_reduce_paid_service(): void
    {
        [$bill,$staff,$admin,$visit] = $this->context('150000', true, false);
        $service = app(PatientBillingService::class);
        $visit->service->update(['allows_patient_deposit' => true]);
        $data = [...$this->payment('200000'), 'kind' => 'DEPOSIT'];
        $deposit = $service->recordTransaction($bill, $staff, $data);
        $this->assertSame($deposit->id, $service->recordTransaction($bill, $staff, $data)->id);
        $this->assertSame('DRAFT', $service->balances($bill)['status']);
        $this->assertSame(0, $service->balances($bill)['paid_minor']);
        app(VisitWorkflowService::class)->complete($visit, $visit->doctor->user, $this->record($visit));
        $bill = $bill->fresh();
        $balance = $service->balances($bill);
        $this->assertSame(15000000, $balance['paid_minor']);
        $this->assertSame(5000000, $balance['unallocated_minor']);
        $this->assertSame('PAID', $balance['status']);
        $this->assertSame(20000000, (int) $bill->entries()->sum('cash_delta_minor'));
        $this->assertSame(1, $bill->entries()->where('kind', 'DEPOSIT_ALLOCATED')->count());
        $refund = $service->submitRequest($bill, $staff, [...$this->proposal('REFUND', '50000'), 'patient_transaction_id' => $deposit->id, 'refund_mode' => 'UNALLOCATED']);
        $service->decideRequest($refund, $admin, 'approve', []);
        $service->completeRefund($refund, $staff, ['actual_return_confirmed' => true, 'completion_reference' => 'Returned excess deposit', 'payout_method' => 'CASH']);
        $this->assertSame(15000000, $service->balances($bill)['paid_minor']);
        $this->assertSame(0, $service->balances($bill)['due_minor']);
        $this->assertSame(0, $service->balances($bill)['unallocated_minor']);
    }

    public function test_refund_reservations_prevent_double_use_and_multi_role_self_approval(): void
    {
        [$bill,$staff,$admin,$visit] = $this->context('100000', true, false);
        $service = app(PatientBillingService::class);
        $visit->service->update(['allows_patient_deposit' => true]);
        $payment = $service->recordTransaction($bill, $staff, [...$this->payment('100000'), 'kind' => 'DEPOSIT']);
        $first = $service->submitRequest($bill, $staff, [...$this->proposal('REFUND', '80000'), 'patient_transaction_id' => $payment->id, 'refund_mode' => 'UNALLOCATED']);
        $second = $service->submitRequest($bill, $staff, [...$this->proposal('REFUND', '80000'), 'patient_transaction_id' => $payment->id, 'refund_mode' => 'UNALLOCATED']);
        $staff->assignRole('Clinic Admin');
        try {
            $service->decideRequest($first, $staff, 'approve', []);
            $this->fail('Self refund approval must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $service->decideRequest($first, $admin, 'approve', []);
        $this->invalid(fn () => $service->decideRequest($second, $admin, 'approve', []));
        $this->assertSame(8000000, $service->funds($payment)['reserved_minor']);
        $this->assertSame(10000000, (int) $bill->entries()->sum('cash_delta_minor'));
    }

    public function test_writeoff_is_not_payment_and_late_verified_transfer_remains_unallocated_until_approved_resolution(): void
    {
        [$bill,$staff,$admin] = $this->context('200000');
        $service = app(PatientBillingService::class);
        $pending = $service->recordTransaction($bill, $staff, $this->payment('200000', 'TRANSFER'));
        $writeoff = $service->submitRequest($bill, $staff, $this->proposal('WRITE_OFF', '200000'));
        $service->decideRequest($writeoff, $admin, 'approve', []);
        $this->assertSame('SETTLED', $service->balances($bill)['status']);
        $this->assertSame(0, (int) $bill->entries()->sum('cash_delta_minor'));
        $service->verifyTransaction($pending, $staff, 'verify', ['funds_received_confirmed' => true, 'verification_reference' => 'Actual funds arrived after writeoff']);
        $balance = $service->balances($bill);
        $this->assertSame(0, $balance['paid_minor']);
        $this->assertSame(20000000, $balance['writeoff_minor']);
        $this->assertSame(20000000, $balance['unallocated_minor']);
        $this->assertSame('SETTLED', $balance['status']);
        $reverse = $service->submitRequest($bill, $staff, $this->proposal('WRITE_OFF_REVERSAL', '200000'));
        $service->decideRequest($reverse, $admin, 'approve', []);
        $allocate = $service->submitRequest($bill, $staff, [...$this->proposal('ALLOCATE_FUNDS', '200000'), 'patient_transaction_id' => $pending->id]);
        $service->decideRequest($allocate, $admin, 'approve', []);
        $this->assertSame('PAID', $service->balances($bill)['status']);
        $this->assertSame(20000000, (int) $bill->entries()->sum('cash_delta_minor'));
    }

    public function test_payment_reversal_keeps_bill_and_reopens_the_unpaid_administrative_task(): void
    {
        [$bill,$staff,$admin] = $this->context('150000');
        $service = app(PatientBillingService::class);
        $payment = $service->recordTransaction($bill, $staff, $this->payment('150000'));
        $request = $service->submitRequest($bill, $staff, [...$this->proposal('PAYMENT_REVERSAL', '50000'), 'patient_transaction_id' => $payment->id]);
        $service->decideRequest($request, $admin, 'approve', []);
        $balance = $service->balances($bill);
        $this->assertSame(15000000, $balance['billed_minor']);
        $this->assertSame(10000000, $balance['paid_minor']);
        $this->assertSame(5000000, $balance['due_minor']);
        $this->assertSame('OPEN', $bill->tasks()->firstOrFail()->status);
        $this->assertSame('CORRECTED', $bill->documents()->where('kind', 'PAYMENT_RECEIPT')->firstOrFail()->status);
    }

    public function test_charge_correction_uses_medically_validated_snapshot_and_explicit_writeoff_reversal(): void
    {
        [$bill,$staff,$admin,$visit] = $this->context();
        $service = app(PatientBillingService::class);
        $visit->service->update(['price' => 150000]);
        app(VisitWorkflowService::class)->correct($visit, $visit->doctor->user, [...$this->record($visit), 'correction_reason' => 'Amend actual service provided after clinical review.']);
        $this->invalid(fn () => $service->recordClinicalReview($visit, $visit->doctor->user, ['request_id' => (string) Str::uuid(), 'record_version' => $visit->medicalRecord->fresh()->lock_version, 'provided_base_amount' => '150000', 'clinical_evidence' => 'Do not replace protected price with newer catalog.']));
        $writeoff = $service->submitRequest($bill, $staff, $this->proposal('WRITE_OFF', '100000'));
        $service->decideRequest($writeoff, $admin, 'approve', []);
        $review = $service->recordClinicalReview($visit, $visit->doctor->user, ['request_id' => (string) Str::uuid(), 'record_version' => $visit->medicalRecord->fresh()->lock_version, 'provided_base_amount' => '50000', 'clinical_evidence' => 'Amendment confirms partial service provided.']);
        $correction = $service->submitRequest($bill, $staff, [...$this->proposal('CHARGE_CORRECTION', '50000'), 'clinical_review_id' => $review->id]);
        $this->invalid(fn () => $service->decideRequest($correction, $admin, 'approve', []));
        $service->decideRequest($correction, $admin, 'approve', ['reverse_writeoff_if_required' => true]);
        $balance = $service->balances($bill);
        $this->assertSame(5000000, $balance['billed_minor']);
        $this->assertSame(5000000, $balance['writeoff_minor']);
        $this->assertSame(0, $balance['due_minor']);
        $this->assertSame(10000000, $bill->documents()->where('kind', 'BILL_STATEMENT')->firstOrFail()->snapshot['balances']['billed_minor']);
    }

    public function test_pending_and_post_closing_refund_are_in_the_next_immutable_closing_period(): void
    {
        [$bill,$staff,$admin] = $this->context();
        $service = app(PatientBillingService::class);
        $service->recordTransaction($bill, $staff, $this->payment('40000'));
        $pending = $service->recordTransaction($bill, $staff, $this->payment('60000', 'TRANSFER'));
        $first = $service->closeCash($bill->clinic, $staff, $this->closing('0', '40000', '0'));
        $snapshot = $first->snapshot;
        $this->assertSame('APPROVED', $first->status);
        $this->assertCount(1, $snapshot['pending_payments']);
        $service->verifyTransaction($pending, $staff, 'verify', ['funds_received_confirmed' => true, 'verification_reference' => 'Confirmed after closing']);
        $discount = $service->submitRequest($bill, $staff, $this->proposal('DISCOUNT', '30000'));
        $service->decideRequest($discount, $admin, 'approve', []);
        $refund = $service->submitRequest($bill, $staff, [...$this->proposal('REFUND', '30000'), 'patient_transaction_id' => $pending->id, 'refund_mode' => 'EXCESS_ALLOCATED']);
        $service->decideRequest($refund, $admin, 'approve', []);
        $service->completeRefund($refund, $staff, ['actual_return_confirmed' => true, 'completion_reference' => 'Actual refund after closing', 'payout_method' => 'CASH']);
        $this->assertSame($snapshot, $first->fresh()->snapshot);
        $this->travel(1)->days();
        $second = $service->closeCash($bill->clinic, $staff, $this->closing('40000', '10000', '60000'));
        $this->assertSame('APPROVED', $second->status);
        $this->assertSame(-3000000, $second->snapshot['net']['CASH']);
        $this->assertSame(6000000, $second->snapshot['net']['TRANSFER']);
        $this->assertSame($first->id, $second->previous_closing_id);
        $this->assertSame([], array_intersect($first->snapshot['ledger_ids'], $second->snapshot['ledger_ids']));
    }

    public function test_discrepant_closing_requires_admin_review_and_its_snapshot_cannot_be_changed(): void
    {
        [$bill,$staff,$admin] = $this->context();
        $service = app(PatientBillingService::class);
        $service->recordTransaction($bill, $staff, $this->payment('100000'));
        $closing = $service->closeCash($bill->clinic, $staff, [...$this->closing('0', '99000', '0'), 'variance_reason' => 'Physical cash discrepancy reported for owner review.']);
        $this->assertSame('PENDING_REVIEW', $closing->status);
        $service->reviewClosing($closing, $admin, 'approve', ['reason' => 'Review of actual discrepancy completed.']);
        $this->assertSame('APPROVED', $closing->fresh()->status);
        $this->expectException(\LogicException::class);
        $closing->update(['snapshot' => []]);
    }

    public function test_cancelled_unstarted_service_is_not_billed_and_deposit_remains_refundable(): void
    {
        [$bill,$staff,,$visit] = $this->context('100000', false, false);
        $appointment = $bill->rootAppointment;
        $appointment->service->update(['allows_patient_deposit' => true]);
        $service = app(PatientBillingService::class);
        $service->recordTransaction($bill, $staff, [...$this->payment('50000'), 'kind' => 'DEPOSIT']);
        app(QueueWorkflowService::class)->cancel($appointment->queue, $staff, 'Patient cancelled before any service.');
        $balance = $service->balances($bill->fresh());
        $this->assertSame('NO_PAYMENT_DUE', $balance['status']);
        $this->assertSame(0, $balance['billed_minor']);
        $this->assertSame(5000000, $balance['unallocated_minor']);
        $this->assertSame('REFUND_DUE', $balance['refund_status']);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_http_billing_keeps_financial_and_clinical_authority_separate_and_checks_tenant(): void
    {
        [$bill,$staff,$admin,$visit] = $this->context();
        $service = app(PatientBillingService::class);
        $service->recordTransaction($bill, $staff, [...$this->payment('100000'), 'verification_reference' => 'PRIVATE-BANK-REFERENCE']);
        $this->actingAs($staff)->get(route('patient-billing.show', $bill))->assertOk()->assertDontSee('Synthetic assessment')->assertDontSee('PRIVATE-BANK-REFERENCE');
        $this->actingAs($visit->doctor->user)->get(route('patient-billing.show', $bill))->assertOk()->assertSee('Nilai tagihan layanan relevan')->assertDontSee('Transaksi dan verifikasi dana')->assertDontSee('PRIVATE-BANK-REFERENCE');
        $this->actingAs($admin)->get(route('patient-billing.clinical-form', $visit))->assertForbidden();
        $this->actingAs($staff)->get(route('patient-billing.closings.index'))->assertOk();
        $patient = User::factory()->create();
        $patient->assignRole('Patient');
        $bill->patient->update(['user_id' => $patient->id]);
        $document = $bill->documents()->where('kind', 'PAYMENT_RECEIPT')->firstOrFail();
        $this->actingAs($staff)->post(route('patient-billing.documents.print', $document))->assertSessionHasErrors('recipient_identity_verified');
        $this->actingAs($patient)->post(route('patient-billing.documents.print', $document))->assertOk()->assertSee('Uang diterima pada bukti ini')->assertDontSee('PRIVATE-BANK-REFERENCE')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($visit->doctor->user)->post(route('patient-billing.documents.print', $document))->assertForbidden();
        [, $foreignStaff] = $this->context();
        $this->actingAs($foreignStaff)->get(route('patient-billing.show', $bill))->assertForbidden();
        $this->actingAs($foreignStaff)->postJson(route('patient-billing.receive', $bill), $this->payment('1'))->assertForbidden();
    }

    public function test_existing_collection_is_allowed_when_subscription_is_suspended_but_stale_deactivated_staff_is_denied(): void
    {
        [$bill,$staff] = $this->context();
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->for($bill->clinic)->for($plan, 'plan')->create(['status' => SubscriptionStatus::Suspended]);
        $this->actingAs($staff)->postJson(route('patient-billing.receive', $bill), $this->payment('40000'))->assertOk();
        User::query()->whereKey($staff->id)->update(['is_active' => false]);
        try {
            app(PatientBillingService::class)->recordTransaction($bill, $staff, $this->payment('60000'));
            $this->fail('Stale staff state must not restore write authority.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(4000000, (int) $bill->entries()->sum('cash_delta_minor'));
    }

    public function test_additional_given_service_requires_patient_fee_consent_current_price_and_doctor_validation(): void
    {
        [$bill,$staff,,$visit] = $this->context();
        $service = app(PatientBillingService::class);
        $extra = Service::factory()->for($bill->clinic)->create(['price' => 20000]);
        $visit->doctor->services()->attach($extra);
        app(VisitWorkflowService::class)->correct($visit, $visit->doctor->user, [...$this->record($visit), 'correction_reason' => 'Amend documentation for additional service actually given.']);
        $data = ['request_id' => (string) Str::uuid(), 'record_version' => $visit->medicalRecord->fresh()->lock_version, 'provided_base_amount' => '100000', 'clinical_evidence' => 'Clinical validation of additional service and patient consent.',
            'additional_services' => [['service_id' => $extra->id, 'expected_unit_price' => '20000', 'quantity' => 2]]];
        $this->invalid(fn () => $service->recordClinicalReview($visit, $visit->doctor->user, $data));
        $extra->update(['price' => 25000]);
        try {
            $service->recordClinicalReview($visit, $visit->doctor->user, [...$data, 'additional_fees_consented' => true]);
            $this->fail('Stale catalog quote must be reviewed.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $review = $service->recordClinicalReview($visit, $visit->doctor->user, [...$data, 'additional_fees_consented' => true, 'additional_services' => [['service_id' => $extra->id, 'expected_unit_price' => '25000', 'quantity' => 2]]]);
        $this->assertSame(15000000, $review->authorized_minor);
        $this->assertSame(10000000, $service->balances($bill)['billed_minor']);
        $service->correctBeforePayment($bill, $staff, ['request_id' => (string) Str::uuid(), 'clinical_review_id' => $review->id, 'reason' => 'Apply current medically validated services before payment.']);
        $this->assertSame(15000000, $service->balances($bill)['billed_minor']);
        $this->assertSame(0, (int) $bill->entries()->sum('cash_delta_minor'));
    }

    public function test_issued_financial_document_numbers_and_snapshots_cannot_be_rewritten(): void
    {
        [$bill,$staff] = $this->context();
        app(PatientBillingService::class)->recordTransaction($bill, $staff, $this->payment('100000'));
        $document = $bill->documents()->where('kind', 'PAYMENT_RECEIPT')->firstOrFail();
        foreach ([[$bill, 'bill_number', 'REPLACED'], [$document, 'document_number', 'REPLACED'], [$document, 'snapshot', ['amount_minor' => 1]]] as [$model,$field,$value]) {
            try {
                $model->fresh()->forceFill([$field => $value])->save();
                $this->fail('Issued financial identity/content must be immutable.');
            } catch (\LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    /** @return array{PatientBill,User,User,?Visit} */
    private function context(string $amount = '100000', bool $start = true, bool $complete = true): array
    {
        Auth::logout();
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create(['price' => $amount]);
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'quota' => 10]);
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $patient = Patient::factory()->for($clinic)->create();
        $appointment = app(BookingService::class)->book($clinic, ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'service_id' => $service->id, 'doctor_schedule_id' => $schedule->id, 'appointment_date' => '2026-11-18', 'idempotency_key' => (string) Str::uuid()], $staff)['appointment'];
        $visit = null;
        if ($start) {
            app(QueueWorkflowService::class)->checkIn($appointment->queue, $staff);
            app(QueueWorkflowService::class)->call($appointment->queue, $staff);
            $visit = app(VisitWorkflowService::class)->start($appointment->queue, $doctorUser)['visit'];
            if ($complete) {
                app(VisitWorkflowService::class)->complete($visit, $doctorUser, $this->record($visit));
            }
        }

        return [app(PatientBillingService::class)->forAppointment($appointment), $staff, $admin, $visit];
    }

    /** @return array<string,mixed> */
    private function record(Visit $visit): array
    {
        return ['record_version' => $visit->medicalRecord->fresh()->lock_version, 'allergy_version' => $visit->patient->allergyVersion(), 'subjective' => 'Synthetic symptoms', 'assessment' => 'Synthetic assessment', 'plan' => 'Synthetic plan'];
    }

    /** @return array<string,mixed> */
    private function payment(string $amount, string $method = 'CASH'): array
    {
        return ['request_id' => (string) Str::uuid(), 'kind' => 'PAYMENT', 'amount' => $amount, 'method' => $method, 'funds_received_confirmed' => true];
    }

    /** @return array<string,mixed> */
    private function proposal(string $kind, string $amount): array
    {
        return ['request_id' => (string) Str::uuid(), 'kind' => $kind, 'amount' => $amount, 'reason' => 'Documented financial request for the synthetic case.'];
    }

    /** @return array<string,mixed> */
    private function closing(string $opening, string $cash, string $transfer): array
    {
        return ['request_id' => (string) Str::uuid(), 'opening_cash' => $opening, 'counted_cash' => $cash, 'counted_qris' => '0', 'counted_transfer' => $transfer];
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected financial validation rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
