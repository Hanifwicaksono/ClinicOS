<?php

namespace App\Services;

use App\MedicalRecordStatus;
use App\Models\Clinic;
use App\Models\IssuedPrescription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PrescriptionIssuanceService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string,mixed> $data */
    public function issue(Visit $visit, User $actor, array $data): IssuedPrescription
    {
        return DB::transaction(function () use ($visit, $actor, $data): IssuedPrescription {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $source = Visit::query()->where('clinic_id', $visit->clinic_id)->lockForUpdate()->findOrFail($visit->id);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            Gate::forUser($actor)->authorize('issue', [IssuedPrescription::class, $source]);
            $fingerprint = hash('sha256', json_encode([$source->id, $actor->id, $data], JSON_THROW_ON_ERROR));
            $existing = IssuedPrescription::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->clinic_id === $source->clinic_id && hash_equals($existing->request_fingerprint, $fingerprint), 409, 'Identitas penerbitan telah digunakan untuk permintaan berbeda.');

                return $existing;
            }
            $record = $source->medicalRecord()->lockForUpdate()->firstOrFail();
            abort_unless($record->patient_id === $source->patient_id && $record->doctor_id === $source->doctor_id && $source->patient->clinic_id === $source->clinic_id, 409);
            if ($record->status !== MedicalRecordStatus::Final || $record->finalized_at === null) {
                throw ValidationException::withMessages(['prescription' => 'Finalisasi dokumentasi oleh penulis sebelum menerbitkan resep.']);
            }
            abort_unless((int) $data['record_version'] === $record->lock_version && hash_equals($source->patient->allergyVersion(), $data['allergy_version']), 409, 'RME atau alergi berubah. Tinjau kembali sebelum penerbitan.');
            if (empty($data['clinical_review_confirmed']) || blank($source->doctor->license_number)) {
                throw ValidationException::withMessages(['clinical_review_confirmed' => 'Pengesahan klinis dan nomor izin dokter wajib tersedia sebelum terbit.']);
            }
            if ($source->patient->criticalAllergies()->exists() && empty($data['critical_allergies_reviewed'])) {
                throw ValidationException::withMessages(['critical_allergies_reviewed' => 'Tinjau dan konfirmasikan alergi kritis saat penerbitan.']);
            }
            $items = $record->prescription?->items->map->only(['medicine_name', 'dosage', 'frequency', 'quantity', 'unit', 'instructions'])->all() ?? [];
            Validator::make(['items' => $items], ['items' => ['required', 'array', 'min:1', 'max:30'], 'items.*.medicine_name' => ['required', 'string', 'max:255'],
                'items.*.dosage' => ['required', 'string', 'max:100'], 'items.*.frequency' => ['required', 'string', 'max:100'], 'items.*.quantity' => ['required', 'integer', 'between:1,9999']])->validate();
            if (IssuedPrescription::query()->where('medical_record_id', $record->id)->where('record_version', $record->lock_version)->exists()) {
                throw ValidationException::withMessages(['prescription' => 'Versi RME ini sudah pernah diterbitkan. Cetak versi tersebut, atau buat koreksi RME sebelum versi baru.']);
            }
            $previous = IssuedPrescription::query()->where('medical_record_id', $record->id)->latest('version_number')->lockForUpdate()->first();
            if ($previous !== null) {
                abort_unless((int) ($data['supersedes_id'] ?? 0) === $previous->id, 409, 'Pilih versi terbit terbaru sebagai dasar penggantian.');
                if (mb_strlen(trim($data['revision_reason'] ?? '')) < 10) {
                    throw ValidationException::withMessages(['revision_reason' => 'Alasan klinis penggantian minimal 10 karakter.']);
                }
                if ($previous->status === 'ISSUED') {
                    $previous->status = 'SUPERSEDED';
                    $previous->save();
                }
            } elseif (! empty($data['supersedes_id'])) {
                abort(409, 'Dokumen dasar tidak sesuai dengan kasus ini.');
            }
            $snapshot = ['clinic' => $source->clinic->only(['name', 'address', 'phone']), 'patient' => $source->patient->only(['name', 'medical_record_number']),
                'birth_date' => $source->patient->birth_date?->toDateString(), 'doctor' => ['name' => $source->doctor->user->name, 'license_number' => $source->doctor->license_number],
                'items' => $items, 'notes' => $record->prescription?->notes, 'record_version' => $record->lock_version, 'issued_at' => now()->toIso8601String(), 'timezone' => $source->clinic->settings?->timezone ?? 'Asia/Jakarta'];
            $released = ! empty($data['release_to_patient']);
            $document = IssuedPrescription::query()->create(['clinic_id' => $source->clinic_id, 'visit_id' => $source->id, 'medical_record_id' => $record->id, 'patient_id' => $source->patient_id, 'doctor_id' => $source->doctor_id,
                'issued_by_user_id' => $actor->id, 'record_version' => $record->lock_version, 'version_number' => ($previous?->version_number ?? 0) + 1, 'supersedes_id' => $previous?->id,
                'snapshot' => $snapshot, 'snapshot_hash' => $this->snapshotDigest($snapshot), 'revision_reason' => $data['revision_reason'] ?? null, 'issued_at' => now(),
                'released_at' => $released ? now() : null, 'released_by_user_id' => $released ? $actor->id : null, 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
            $document->document_number = 'RX-'.$source->clinic_id.'-'.str_pad((string) $document->id, 8, '0', STR_PAD_LEFT);
            $document->save();
            $this->auditLogger->recordForClinic($source->clinic, 'prescription.issued', $document, ['visit_id' => $source->id, 'record_version' => $record->lock_version, 'supersedes_id' => $previous?->id, 'released' => $released], $actor);

            return $document;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function change(IssuedPrescription $document, User $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($document, $actor, $action, $data): void {
            Clinic::query()->lockForUpdate()->findOrFail($document->clinic_id);
            $locked = IssuedPrescription::query()->where('clinic_id', $document->clinic_id)->lockForUpdate()->findOrFail($document->id);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            Gate::forUser($actor)->authorize('manage', $locked);
            if ($action === 'release') {
                if ($locked->released_at !== null) {
                    return;
                }
                if ($locked->status !== 'ISSUED') {
                    throw ValidationException::withMessages(['document' => 'Dokumen sudah tidak berlaku.']);
                }
                $locked->released_at = now();
                $locked->released_by_user_id = $actor->id;
            } elseif ($action === 'recall') {
                if ($locked->status === 'RECALLED') {
                    return;
                }
                if (mb_strlen(trim($data['reason'] ?? '')) < 10) {
                    throw ValidationException::withMessages(['reason' => 'Alasan klinis penarikan minimal 10 karakter.']);
                }
                $locked->status = 'RECALLED';
                $locked->recalled_at = now();
                $locked->recalled_by_user_id = $actor->id;
                $locked->recall_reason = $data['reason'];
            } else {
                abort(404);
            }
            $locked->save();
            $this->auditLogger->recordForClinic($locked->clinic, 'prescription.'.$action, $locked, [], $actor);
        }, 3);
    }

    /** @param array<string,mixed> $snapshot */
    public function snapshotDigest(array $snapshot): string
    {
        $sort = function (array $value) use (&$sort): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $sort($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($snapshot), JSON_THROW_ON_ERROR));
    }
}
