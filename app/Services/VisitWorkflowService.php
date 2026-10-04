<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Events\VisitCompleted;
use App\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\Models\Queue;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitWorkflowService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ClinicNotificationService $notificationService,
    ) {}

    /** @return array{visit: Visit, created: bool} */
    public function start(Queue $queue, User $actor): array
    {
        return DB::transaction(function () use ($queue, $actor): array {
            $lockedQueue = Queue::query()
                ->with(['appointment.clinic.settings', 'doctor'])
                ->lockForUpdate()
                ->findOrFail($queue->id);
            $existingVisit = Visit::query()->where('appointment_id', $lockedQueue->appointment_id)->first();

            if ($existingVisit !== null) {
                $this->ensureDoctor($existingVisit, $actor);

                return ['visit' => $this->loadVisit($existingVisit), 'created' => false];
            }

            if ($lockedQueue->status !== QueueStatus::Called) {
                throw ValidationException::withMessages([
                    'queue' => 'Pemeriksaan hanya dapat dimulai setelah pasien dipanggil.',
                ]);
            }

            if ($lockedQueue->doctor->user_id !== $actor->id) {
                throw new AuthorizationException('Dokter hanya dapat memulai antreannya sendiri.');
            }

            $timezone = $lockedQueue->appointment->clinic->settings?->timezone ?? 'Asia/Jakarta';
            if (! $lockedQueue->queue_date->isSameDay(CarbonImmutable::now($timezone))) {
                throw ValidationException::withMessages([
                    'queue' => 'Pemeriksaan hanya dapat dimulai pada tanggal antrean.',
                ]);
            }

            $appointment = $lockedQueue->appointment;
            $visit = Visit::query()->create([
                'clinic_id' => $appointment->clinic_id,
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'service_id' => $appointment->service_id,
                'started_by_user_id' => $actor->id,
                'status' => VisitStatus::InProgress,
                'service_name' => $appointment->service_name,
                'service_price' => $appointment->service_price,
                'total_amount' => $appointment->service_price,
                'started_at' => now(),
            ]);
            $visit->medicalRecord()->create([
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'created_by_user_id' => $actor->id,
                'status' => MedicalRecordStatus::Draft,
            ]);
            $lockedQueue->update(['status' => QueueStatus::InProgress]);

            $this->auditLogger->recordForClinic($appointment->clinic, 'visit.started', $visit, [
                'appointment_id' => $appointment->id,
                'queue_id' => $lockedQueue->id,
            ], $actor);
            $this->auditLogger->recordForClinic($appointment->clinic, 'queue.status_changed', $lockedQueue, [
                'from' => QueueStatus::Called->value,
                'to' => QueueStatus::InProgress->value,
            ], $actor);
            $this->notificationService->queueStatusChanged($lockedQueue->refresh(), QueueStatus::Called);

            return ['visit' => $this->loadVisit($visit), 'created' => true];
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function saveDraft(Visit $visit, User $actor, array $data): Visit
    {
        return DB::transaction(function () use ($visit, $actor, $data): Visit {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->ensureDoctor($lockedVisit, $actor);
            $this->ensureInProgress($lockedVisit);
            $record = MedicalRecord::query()->lockForUpdate()->where('visit_id', $lockedVisit->id)->firstOrFail();

            if ($record->status !== MedicalRecordStatus::Draft) {
                throw ValidationException::withMessages(['record' => 'Rekam medis sudah difinalisasi.']);
            }

            $this->syncRecord($record, $data);
            $this->auditLogger->recordForClinic($lockedVisit->clinic, 'medical_record.draft_saved', $record, [
                'visit_id' => $lockedVisit->id,
            ], $actor);

            return $this->loadVisit($lockedVisit);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function complete(Visit $visit, User $actor, array $data): Visit
    {
        return DB::transaction(function () use ($visit, $actor, $data): Visit {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->ensureDoctor($lockedVisit, $actor);

            if ($lockedVisit->status === VisitStatus::Completed) {
                return $this->loadVisit($lockedVisit);
            }

            $this->ensureInProgress($lockedVisit);
            $appointment = $lockedVisit->appointment()->lockForUpdate()->firstOrFail();
            $queue = Queue::query()->lockForUpdate()->where('appointment_id', $appointment->id)->firstOrFail();

            if ($queue->status !== QueueStatus::InProgress) {
                throw ValidationException::withMessages(['queue' => 'Status antrean tidak sesuai untuk finalisasi.']);
            }

            $record = MedicalRecord::query()->lockForUpdate()->where('visit_id', $lockedVisit->id)->firstOrFail();
            $this->syncRecord($record, $data);
            $record->update(['status' => MedicalRecordStatus::Final, 'finalized_at' => now()]);
            $lockedVisit->update([
                'status' => VisitStatus::Completed,
                'completed_by_user_id' => $actor->id,
                'total_amount' => $lockedVisit->service_price,
                'completed_at' => now(),
            ]);
            $appointment->update(['status' => AppointmentStatus::Completed]);
            $queue->update(['status' => QueueStatus::Completed, 'completed_at' => now()]);

            $this->auditLogger->recordForClinic($lockedVisit->clinic, 'visit.completed', $lockedVisit, [
                'appointment_id' => $appointment->id,
                'medical_record_id' => $record->id,
                'total_amount' => $lockedVisit->service_price,
            ], $actor);
            $this->notificationService->queueStatusChanged($queue->refresh(), QueueStatus::InProgress);
            VisitCompleted::dispatch(
                $lockedVisit->id,
                $lockedVisit->clinic_id,
                $lockedVisit->doctor_id,
                $lockedVisit->patient_id,
            );

            return $this->loadVisit($lockedVisit);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function correct(Visit $visit, User $actor, array $data): Visit
    {
        return DB::transaction(function () use ($visit, $actor, $data): Visit {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->ensureDoctor($lockedVisit, $actor);

            if ($lockedVisit->status !== VisitStatus::Completed) {
                throw ValidationException::withMessages(['record' => 'Koreksi hanya tersedia untuk kunjungan selesai.']);
            }

            $record = MedicalRecord::query()->lockForUpdate()->where('visit_id', $lockedVisit->id)->firstOrFail();
            $before = $this->snapshot($record);
            $this->syncRecord($record, Arr::except($data, ['correction_reason']));
            $after = $this->snapshot($record->refresh());
            $revision = $record->revisions()->create([
                'corrected_by_user_id' => $actor->id,
                'reason' => $data['correction_reason'],
                'before_data' => $before,
                'after_data' => $after,
            ]);
            $this->auditLogger->recordForClinic($lockedVisit->clinic, 'medical_record.corrected', $record, [
                'visit_id' => $lockedVisit->id,
                'revision_id' => $revision->id,
            ], $actor);

            return $this->loadVisit($lockedVisit);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function syncRecord(MedicalRecord $record, array $data): void
    {
        $record->update(Arr::only($data, [
            'chief_complaint', 'subjective', 'objective', 'assessment', 'plan',
            'physical_examination', 'doctor_notes',
        ]));

        $vitalSignData = Arr::only($data['vital_signs'] ?? [], [
            'weight_kg', 'height_cm', 'systolic', 'diastolic', 'pulse',
            'respiratory_rate', 'temperature_c', 'oxygen_saturation',
        ]);
        $hasVitalSign = collect($vitalSignData)->contains(fn ($value): bool => $value !== null && $value !== '');
        if (! $hasVitalSign) {
            $record->vitalSign()->delete();
        } else {
            $record->vitalSign()->updateOrCreate([], $vitalSignData);
        }

        $record->diagnoses()->delete();
        $diagnoses = array_values(array_filter(
            $data['diagnoses'] ?? [],
            fn (array $item): bool => filled($item['name'] ?? null),
        ));
        $primaryDiagnosisIndex = collect($diagnoses)->search(
            fn (array $diagnosis): bool => (bool) ($diagnosis['is_primary'] ?? false),
        );
        $primaryDiagnosisIndex = $primaryDiagnosisIndex === false ? 0 : $primaryDiagnosisIndex;

        foreach ($diagnoses as $index => $diagnosis) {
            $record->diagnoses()->create([
                ...Arr::only($diagnosis, ['code', 'name', 'notes']),
                'is_primary' => $index === $primaryDiagnosisIndex,
                'sort_order' => $index,
            ]);
        }

        $record->treatments()->delete();
        foreach (array_values(array_filter($data['treatments'] ?? [], fn (array $item): bool => filled($item['name'] ?? null))) as $index => $treatment) {
            $record->treatments()->create([
                ...Arr::only($treatment, ['name', 'description']),
                'sort_order' => $index,
            ]);
        }

        $items = array_values(array_filter($data['prescription_items'] ?? [], fn (array $item): bool => filled($item['medicine_name'] ?? null)));
        if ($items === [] && blank($data['prescription_notes'] ?? null)) {
            $record->prescription()->delete();
        } else {
            $prescription = $record->prescription()->updateOrCreate([], ['notes' => $data['prescription_notes'] ?? null]);
            $prescription->items()->delete();
            foreach ($items as $index => $item) {
                $prescription->items()->create([
                    ...Arr::only($item, ['medicine_name', 'dosage', 'frequency', 'quantity', 'unit', 'instructions']),
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(MedicalRecord $record): array
    {
        $record->load(['vitalSign', 'diagnoses', 'treatments', 'prescription.items']);

        return [
            'record' => Arr::only($record->attributesToArray(), [
                'chief_complaint', 'subjective', 'objective', 'assessment', 'plan',
                'physical_examination', 'doctor_notes', 'status', 'finalized_at',
            ]),
            'vital_signs' => $record->vitalSign?->attributesToArray(),
            'diagnoses' => $record->diagnoses->map->attributesToArray()->all(),
            'treatments' => $record->treatments->map->attributesToArray()->all(),
            'prescription' => $record->prescription?->attributesToArray(),
            'prescription_items' => $record->prescription?->items->map->attributesToArray()->all() ?? [],
        ];
    }

    private function ensureDoctor(Visit $visit, User $actor): void
    {
        if ($visit->doctor->user_id !== $actor->id) {
            throw new AuthorizationException('Dokter hanya dapat mengelola kunjungannya sendiri.');
        }
    }

    private function ensureInProgress(Visit $visit): void
    {
        if ($visit->status !== VisitStatus::InProgress) {
            throw ValidationException::withMessages(['visit' => 'Kunjungan ini sudah tidak aktif.']);
        }
    }

    private function loadVisit(Visit $visit): Visit
    {
        return $visit->refresh()->load([
            'clinic', 'appointment', 'patient', 'doctor.user', 'service', 'queue',
            'medicalRecord.vitalSign', 'medicalRecord.diagnoses', 'medicalRecord.treatments',
            'medicalRecord.prescription.items', 'medicalRecord.revisions.correctedBy',
        ]);
    }
}
