<?php

namespace App\Services;

use App\AppointmentStatus;
use App\DentalCondition;
use App\Events\VisitCompleted;
use App\MedicalRecordStatus;
use App\Models\CaseLeadAssignment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\InterruptionTask;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitContinuation;
use App\Models\VisitResumeReservation;
use App\Models\VisitWorkflowEvent;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VisitWorkflowService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ClinicNotificationService $notificationService,
        private SubscriptionService $subscriptionService,
    ) {}

    /** @return array{visit: Visit, created: bool} */
    public function start(Queue $queue, User $actor): array
    {
        return DB::transaction(function () use ($queue, $actor): array {
            Clinic::query()->lockForUpdate()->findOrFail($queue->clinic_id);
            DoctorSchedule::query()->lockForUpdate()->findOrFail($queue->doctor_schedule_id);
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

            $clinic = Clinic::query()->lockForUpdate()->findOrFail($lockedQueue->appointment->clinic_id);

            try {
                $this->subscriptionService->assertVisitCanStart($clinic, $lockedQueue->appointment);
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['queue' => $exception->getMessage()]);
            }

            $timezone = $lockedQueue->appointment->clinic->settings?->timezone ?? 'Asia/Jakarta';
            if (! $lockedQueue->queue_date->isSameDay(CarbonImmutable::now($timezone))) {
                throw ValidationException::withMessages([
                    'queue' => 'Pemeriksaan hanya dapat dimulai pada tanggal antrean.',
                ]);
            }

            $appointment = $lockedQueue->appointment;
            app(FollowUpService::class)->assertStart($appointment);
            $handoff = VisitContinuation::query()->where('target_appointment_id', $appointment->id)->lockForUpdate()->first();
            if ($handoff !== null) {
                abort_unless($handoff->clinic_id === $appointment->clinic_id && $handoff->source->patient_id === $appointment->patient_id
                    && $handoff->receiving_doctor_id === $appointment->doctor_id && $handoff->service_id === $appointment->service_id
                    && $handoff->status === 'BOOKED' && $handoff->accepted_at !== null && $handoff->patient_consented_at !== null && $handoff->to_visit_id === null, 409, 'Persetujuan kelanjutan tidak sesuai dengan booking.');
                $this->assertHandoffSnapshotCurrent($handoff);
            }
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
            if ($handoff !== null) {
                if ($handoff->protected_service) {
                    $visit->quota_origin_visit_id = $handoff->root_visit_id;
                    $visit->save();
                }
                $handoff->to_visit_id = $visit->id;
                $handoff->status = 'IN_PROGRESS';
                $handoff->lock_version++;
                $handoff->save();
            }
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
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->ensureDoctor($lockedVisit, $actor);
            if (! in_array($lockedVisit->status, [VisitStatus::InProgress, VisitStatus::Interrupted], true)) {
                throw ValidationException::withMessages(['visit' => 'Draft hanya dapat ditulis pada pemeriksaan aktif atau dokumentasi interupsi milik dokter.']);
            }
            $record = MedicalRecord::query()->lockForUpdate()->where('visit_id', $lockedVisit->id)->firstOrFail();

            if ($record->status !== MedicalRecordStatus::Draft) {
                throw ValidationException::withMessages(['record' => 'Rekam medis sudah difinalisasi.']);
            }

            $this->syncRecord($record, $data, $actor);
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
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
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
            $this->syncRecord($record, $data, $actor);
            $record->update(['status' => MedicalRecordStatus::Final, 'finalized_at' => now()]);
            $lockedVisit->update([
                'status' => VisitStatus::Completed,
                'completed_by_user_id' => $actor->id,
                'total_amount' => $lockedVisit->service_price,
                'completed_at' => now(),
            ]);
            $appointment->update(['status' => AppointmentStatus::Completed]);
            $queue->update(['status' => QueueStatus::Completed, 'completed_at' => now()]);
            $incoming = $lockedVisit->continuedFrom()->lockForUpdate()->first();
            if ($incoming !== null) {
                $incoming->status = 'COMPLETED';
                $incoming->lock_version++;
                $incoming->save();
            }
            $this->consumeResumeReservation($lockedVisit);
            $lockedVisit->workflow_version++;
            $lockedVisit->save();

            app(FollowUpService::class)->visitCompleted($lockedVisit, $actor, $data);
            app(PatientBillingService::class)->visitCompleted($lockedVisit, $actor);
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
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->ensureDoctor($lockedVisit, $actor);

            if (! in_array($lockedVisit->status, [VisitStatus::Completed, VisitStatus::Interrupted], true) || $lockedVisit->medicalRecord->status !== MedicalRecordStatus::Final) {
                throw ValidationException::withMessages(['record' => 'Koreksi hanya tersedia untuk catatan final.']);
            }

            $record = MedicalRecord::query()->lockForUpdate()->where('visit_id', $lockedVisit->id)->firstOrFail();
            $before = $this->snapshot($record);
            $this->syncRecord($record, Arr::except($data, ['correction_reason']), $actor);
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

    /** @param array<string, string|null> $fields */
    public function applyVoiceSoap(Visit $visit, User $actor, array $fields, int $expectedVersion): MedicalRecord
    {
        return DB::transaction(function () use ($visit, $actor, $fields, $expectedVersion): MedicalRecord {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->ensureDoctor($visit, $actor);
            abort_unless($actor->fresh()->can('medical_record.update'), 403);
            $this->ensureInProgress($visit);
            $record = $visit->medicalRecord()->lockForUpdate()->firstOrFail();
            abort_unless($record->status === MedicalRecordStatus::Draft && $record->lock_version === $expectedVersion, 409,
                'RME telah berubah atau sudah final. Muat ulang dan tinjau sebelum menerapkan draft Voice.');
            $record->fill(Arr::only($fields, ClinicalDraftValidator::SOAP_FIELDS));
            $record->lock_version++;
            $record->save();
            $this->auditLogger->recordForClinic($visit->clinic, 'medical_record.voice_applied', $record, ['visit_id' => $visit->id], $actor);

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function syncRecord(MedicalRecord $record, array $data, User $actor): void
    {
        abort_if(! isset($data['record_version']) || (int) $data['record_version'] !== $record->lock_version, 409,
            'Rekam medis telah berubah di tab lain. Salin perubahan Anda lalu muat ulang halaman sebelum melanjutkan.');
        $patient = Patient::query()->where('clinic_id', $record->visit->clinic_id)->lockForUpdate()->findOrFail($record->patient_id);
        abort_if(! isset($data['allergy_version']) || ! hash_equals($patient->allergyVersion(), $data['allergy_version']), 409,
            'Data alergi pasien telah berubah. Salin perubahan Anda lalu muat ulang untuk meninjau alergi terbaru.');
        $items = array_values(array_filter($data['prescription_items'] ?? [], fn (array $item): bool => filled($item['medicine_name'] ?? null)));
        $hasCriticalAllergy = $record->patient->criticalAllergies()->exists();

        if ($items !== [] && $hasCriticalAllergy && ! filter_var($data['critical_allergies_acknowledged'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw ValidationException::withMessages([
                'critical_allergies_acknowledged' => 'Alergi kritis pasien wajib ditinjau sebelum resep disimpan.',
            ]);
        }

        $recordData = Arr::only($data, [
            'chief_complaint', 'subjective', 'objective', 'assessment', 'plan',
            'physical_examination', 'doctor_notes',
        ]);

        if (array_key_exists('odontogram', $data)) {
            if (! app(AddonService::class)->canEdit($record->visit->clinic)) {
                throw ValidationException::withMessages(['odontogram' => 'Add-on odontogram tidak aktif untuk klinik ini.']);
            }
            $recordData['odontogram'] = collect($data['odontogram'])
                ->map(fn (array $tooth): array => [
                    'tooth_number' => (int) $tooth['tooth_number'],
                    'condition' => DentalCondition::from($tooth['condition'])->value,
                ])
                ->sortBy('tooth_number')
                ->values()
                ->all();
        }

        $record->fill($recordData);
        $record->lock_version++;
        $record->save();

        $storedPhotos = $record->photos ?? [];
        $removedPhotoIndexes = array_map('intval', $data['removed_photos'] ?? []);
        $removedPhotoPaths = [];
        $photos = [];

        foreach ($storedPhotos as $index => $photo) {
            if (in_array($index, $removedPhotoIndexes, true)) {
                $removedPhotoPaths[] = $photo['path'];

                continue;
            }

            $photos[] = $photo;
        }

        foreach ($data['photos'] ?? [] as $photo) {
            $path = $photo->store("medical-records/{$record->id}", 'local');

            if ($path === false) {
                throw new RuntimeException('Foto rekam medis gagal disimpan.');
            }

            DB::afterRollBack(fn () => Storage::disk('local')->delete($path));

            $photos[] = ['path' => $path, 'name' => $photo->getClientOriginalName()];
        }

        if ($photos !== $storedPhotos) {
            $record->update(['photos' => $photos]);
        }

        if ($removedPhotoPaths !== [] && $record->status !== MedicalRecordStatus::Final) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($removedPhotoPaths));
        }

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

        if (array_key_exists('diagnoses', $data)) {
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
        }

        if (array_key_exists('treatments', $data)) {
            $record->treatments()->delete();
            foreach (array_values(array_filter($data['treatments'] ?? [], fn (array $item): bool => filled($item['name'] ?? null))) as $index => $treatment) {
                $record->treatments()->create([
                    ...Arr::only($treatment, ['name', 'description']),
                    'quantity' => $treatment['quantity'] ?? 1,
                    'sort_order' => $index,
                ]);
            }
        }

        if ($items !== [] && $hasCriticalAllergy) {
            $record->update([
                'allergy_acknowledged_at' => now(),
                'allergy_acknowledged_by_user_id' => $actor->id,
            ]);
        } else {
            $record->update([
                'allergy_acknowledged_at' => null,
                'allergy_acknowledged_by_user_id' => null,
            ]);
        }

        if ($items === [] && blank($data['prescription_notes'] ?? null)) {
            $record->prescription()->delete();
        } else {
            $prescription = $record->prescription()->updateOrCreate([], ['notes' => $data['prescription_notes'] ?? null]);
            $prescription->items()->delete();
            foreach ($items as $index => $item) {
                $prescription->items()->create([
                    ...Arr::only($item, ['medicine_name', 'dosage', 'frequency', 'quantity', 'unit', 'instructions']),
                    'dosage' => $item['dosage'] ?? '',
                    'frequency' => $item['frequency'] ?? '',
                    'quantity' => $item['quantity'] ?? null,
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
                'physical_examination', 'doctor_notes', 'photos', 'odontogram',
                'allergy_acknowledged_at', 'allergy_acknowledged_by_user_id', 'status', 'finalized_at',
            ]),
            'vital_signs' => $record->vitalSign?->attributesToArray(),
            'diagnoses' => $record->diagnoses->map->attributesToArray()->all(),
            'treatments' => $record->treatments->map->attributesToArray()->all(),
            'prescription' => $record->prescription?->attributesToArray(),
            'prescription_items' => $record->prescription?->items->map->attributesToArray()->all() ?? [],
        ];
    }

    /** @param array<string,mixed> $data */
    public function changeWorkflow(Visit $visit, User $actor, string $action, array $data): Visit
    {
        return DB::transaction(function () use ($visit, $actor, $action, $data): Visit {
            [$locked,$queue] = $this->lockCareVisit($visit);
            abort_unless($actor->can('changeWorkflow', [$locked, $action]), 403);
            if ($this->replayedCareEvent($locked, $actor, $action, $data) !== null) {
                return $this->loadVisit($locked);
            }
            $this->expectedCareVersion($locked, $data, ! in_array($action, ['ready', 'clear-ready'], true));
            $from = $locked->status;
            $before = $queue->only(['doctor_schedule_id', 'queue_date', 'queue_number', 'display_number', 'status']);
            $details = [];
            if ($action === 'pause') {
                $this->ensureInProgress($locked);
                if ($queue->status !== QueueStatus::InProgress) {
                    throw ValidationException::withMessages(['workflow' => 'Status antrean tidak sesuai untuk jeda.']);
                }
                $until = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['estimated_return_at'], $locked->clinic->settings?->timezone ?? 'Asia/Jakarta');
                if ($until->lessThanOrEqualTo(now())) {
                    throw ValidationException::withMessages(['estimated_return_at' => 'Estimasi kembali harus setelah waktu sekarang.']);
                }
                if (! in_array($data['pause_mode'] ?? null, ['HOLD', 'RELEASE'], true)) {
                    throw ValidationException::withMessages(['pause_mode' => 'Pilih mode jeda yang sah.']);
                }
                $locked->status = VisitStatus::Paused;
                $locked->pause_mode = $data['pause_mode'];
                $locked->paused_at = now();
                $locked->paused_until = $until->utc();
                $locked->pause_generation++;
                $queue->forceFill(['status' => QueueStatus::Paused, 'pause_holds_slot' => $data['pause_mode'] === 'HOLD'])->save();
                $this->consumeResumeReservation($locked);
                $details = ['pause_mode' => $data['pause_mode'], 'estimated_return_at' => $until->toIso8601String()];
            } elseif ($action === 'ready') {
                $this->ensurePaused($locked, $queue);
                $schedule = DoctorSchedule::query()->lockForUpdate()->findOrFail($data['doctor_schedule_id']);
                $reservation = app(BookingService::class)->reserveResume($locked, $actor, $schedule, $data['request_id']);
                $details = ['resume_reservation_id' => $reservation->id];
            } elseif ($action === 'clear-ready') {
                $this->ensurePaused($locked, $queue);
                $reservation = $locked->resumeReservation()->lockForUpdate()->first();
                if ($reservation === null || $reservation->state !== 'READY') {
                    throw ValidationException::withMessages(['workflow' => 'Belum ada kesiapan kembali yang dapat ditarik.']);
                }
                $reservation->update(['state' => 'RELEASED', 'closed_at' => now()]);
                $locked->resume_reservation_id = null;
                $details = ['resume_reservation_id' => $reservation->id];
            } elseif ($action === 'resume') {
                $this->ensurePaused($locked, $queue);
                abort_unless($locked->doctor->is_active && $actor->is_active, 403, 'Dokter belum aktif untuk melanjutkan pelayanan. Koordinasikan dengan tenaga klinis yang berwenang.');
                if (empty($data['patient_ready'])) {
                    throw ValidationException::withMessages(['patient_ready' => 'Konfirmasikan pasien siap melanjutkan.']);
                }
                $timezone = $locked->clinic->settings?->timezone ?? 'Asia/Jakarta';
                $today = CarbonImmutable::now($timezone)->startOfDay();
                if (! $queue->queue_date->isSameDay($today) && blank($data['clinical_delay_assessment'] ?? null)) {
                    throw ValidationException::withMessages(['clinical_delay_assessment' => 'Penilaian klinis kelanjutan lintas hari wajib dicatat oleh dokter.']);
                }
                $schedule = DoctorSchedule::query()->lockForUpdate()->findOrFail($data['doctor_schedule_id']);
                $reservation = app(BookingService::class)->reserveResume($locked, $actor, $schedule, $data['request_id']);
                $active = Queue::query()->where('clinic_id', $locked->clinic_id)->where('doctor_id', $locked->doctor_id)->where('doctor_schedule_id', $schedule->id)->whereDate('queue_date', $today->toDateString())
                    ->whereKeyNot($queue->id)->where(fn ($query) => $query->whereIn('status', [QueueStatus::Called, QueueStatus::InProgress])->orWhere(fn ($paused) => $paused->where('status', QueueStatus::Paused)->where('pause_holds_slot', true)))->exists();
                if ($active) {
                    throw ValidationException::withMessages(['workflow' => 'Selesaikan panggilan atau pemeriksaan aktif pada sesi tujuan sebelum melanjutkan pasien ini.']);
                }
                $retainsSlot = $queue->pause_holds_slot && $queue->doctor_schedule_id === $schedule->id && $queue->queue_date->isSameDay($today);
                $first = VisitResumeReservation::query()->where('clinic_id', $locked->clinic_id)->where('doctor_schedule_id', $schedule->id)->whereDate('resume_date', $today->toDateString())->where('state', 'READY')
                    ->whereHas('visit', fn ($query) => $query->where('status', VisitStatus::Paused)->whereColumn('visits.resume_reservation_id', 'visit_resume_reservations.id'))->orderBy('ready_at')->orderBy('id')->value('id');
                if (! $retainsSlot && $first !== $reservation->id) {
                    throw ValidationException::withMessages(['workflow' => 'Pasien lain lebih dahulu siap kembali. Lanjutkan pasien tersebut terlebih dahulu.']);
                }
                if ($queue->doctor_schedule_id !== $schedule->id || ! $queue->queue_date->isSameDay($today)) {
                    app(BookingService::class)->rememberQueueNumber($queue);
                    $number = app(BookingService::class)->nextQueueNumber($locked->clinic, $schedule, $today);
                    $queue->doctor_schedule_id = $schedule->id;
                    $queue->queue_date = $today;
                    $queue->queue_number = $number;
                    $queue->display_number = sprintf('%s-%03d', Str::upper($locked->clinic->settings?->queue_prefix ?? 'A'), $number);
                }
                $queue->status = QueueStatus::InProgress;
                $queue->pause_holds_slot = false;
                $queue->save();
                $reservation->update(['state' => 'ACTIVE', 'closed_at' => null]);
                $locked->paused_seconds += (int) $locked->paused_at->diffInSeconds(now());
                $locked->status = VisitStatus::InProgress;
                $locked->pause_mode = null;
                $locked->paused_at = null;
                $locked->paused_until = null;
                $details = ['resume_reservation_id' => $reservation->id, 'clinical_delay_assessment' => $data['clinical_delay_assessment'] ?? null];
            } elseif ($action === 'interrupt') {
                if (! in_array($locked->status, [VisitStatus::InProgress, VisitStatus::Paused], true)) {
                    throw ValidationException::withMessages(['workflow' => 'Pelayanan ini sudah tidak aktif untuk ditandai terinterupsi.']);
                }
                if ($locked->paused_at !== null) {
                    $locked->paused_seconds += (int) $locked->paused_at->diffInSeconds(now());
                }
                $locked->status = VisitStatus::Interrupted;
                $locked->interrupted_at = now();
                $locked->pause_mode = null;
                $locked->paused_at = null;
                $locked->paused_until = null;
                $locked->appointment()->update(['status' => AppointmentStatus::Interrupted]);
                $queue->forceFill(['status' => QueueStatus::Interrupted, 'pause_holds_slot' => false])->save();
                $reservation = $locked->resumeReservation()->lockForUpdate()->first();
                if ($reservation !== null) {
                    $reservation->update(['state' => $reservation->state === 'READY' ? 'RELEASED' : 'CONSUMED', 'closed_at' => now()]);
                }
                InterruptionTask::query()->firstOrCreate(['visit_id' => $locked->id, 'kind' => 'CARE'], ['clinic_id' => $locked->clinic_id, 'status' => 'OPEN']);
                if ($locked->medicalRecord->status === MedicalRecordStatus::Draft) {
                    InterruptionTask::query()->firstOrCreate(['visit_id' => $locked->id, 'kind' => 'DOCUMENTATION'], ['clinic_id' => $locked->clinic_id, 'status' => 'OPEN']);
                }
                $incoming = $locked->continuedFrom()->first();
                if ($incoming !== null) {
                    $incoming->status = 'INTERRUPTED';
                    $incoming->lock_version++;
                    $incoming->save();
                }
            } elseif ($action === 'note') {
                $details = ['note_author_user_id' => $actor->id];
            } else {
                abort(404);
            }
            $locked->save();
            $details['queue_before'] = $before;
            $details['queue_after'] = $queue->fresh()->only(['doctor_schedule_id', 'queue_date', 'queue_number', 'display_number', 'status']);
            $this->recordCareEvent($locked, $actor, $action, $data, $from, $details);
            if ($queue->status->value !== $before['status']->value) {
                $this->notificationService->queueStatusChanged($queue->refresh(), $before['status']);
            }

            return $this->loadVisit($locked);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function finalizeInterruption(Visit $visit, User $actor, array $data): Visit
    {
        return DB::transaction(function () use ($visit, $actor, $data): Visit {
            [$locked] = $this->lockCareVisit($visit);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            abort_unless($actor->can('finalizeInterruption', $locked), 403);
            if ($this->replayedCareEvent($locked, $actor, 'documentation.finalize', $data) !== null) {
                return $this->loadVisit($locked);
            }
            $this->expectedCareVersion($locked, $data, true);
            if (empty($data['documentation_attested'])) {
                throw ValidationException::withMessages(['documentation_attested' => 'Pengesahan oleh penulis catatan wajib dikonfirmasi.']);
            }
            $record = $locked->medicalRecord()->lockForUpdate()->firstOrFail();
            if ($record->status !== MedicalRecordStatus::Draft) {
                throw ValidationException::withMessages(['record' => 'Dokumentasi sudah final. Gunakan koreksi beralasan.']);
            }
            $this->syncRecord($record, $data, $actor);
            $record->update(['status' => MedicalRecordStatus::Final, 'finalized_at' => now()]);
            $task = $locked->interruptionTasks()->where('kind', 'DOCUMENTATION')->first();
            if ($task !== null) {
                $task->forceFill(['status' => 'RESOLVED', 'resolved_by_user_id' => $actor->id, 'resolved_at' => now(), 'lock_version' => $task->lock_version + 1])->save();
            }
            $this->recordCareEvent($locked, $actor, 'documentation.finalize', $data, VisitStatus::Interrupted, ['medical_record_id' => $record->id]);

            return $this->loadVisit($locked);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function appointCaseLead(Visit $visit, User $actor, array $data): CaseLeadAssignment
    {
        return DB::transaction(function () use ($visit, $actor, $data): CaseLeadAssignment {
            [$locked] = $this->lockCareVisit($visit);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            abort_unless($actor->can('appointLead', $locked), 403);
            if (($event = $this->replayedCareEvent($locked, $actor, 'lead.appoint', $data)) !== null) {
                return CaseLeadAssignment::query()->findOrFail($event->details['lead_assignment_id']);
            }
            $this->expectedCareVersion($locked, $data, false);
            $doctor = Doctor::query()->where('clinic_id', $locked->clinic_id)->where('is_active', true)->whereHas('user', fn ($query) => $query->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'Doctor')))
                ->whereHas('services', fn ($query) => $query->whereKey($locked->service_id))->findOrFail($data['doctor_id']);
            if (empty($data['competence_confirmed'])) {
                throw ValidationException::withMessages(['competence_confirmed' => 'Konfirmasi verifikasi kompetensi dan tanggung jawab klinis wajib dicatat.']);
            }
            $ends = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $locked->clinic->settings?->timezone ?? 'Asia/Jakarta');
            if ($ends->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['ends_at' => 'Masa penunjukan harus berakhir setelah waktu sekarang.']);
            }
            $previous = $locked->caseLeads()->where('role', $data['role'])->whereNull('revoked_at')->lockForUpdate()->get();
            if ($previous->contains(fn ($row) => $row->ends_at->isFuture())) {
                throw ValidationException::withMessages(['doctor_id' => 'Cabut penunjukan pada posisi ini sebelum menunjuk penggantinya.']);
            }
            foreach ($previous as $row) {
                $row->forceFill(['revoked_at' => now(), 'revoked_by_user_id' => $actor->id, 'revocation_reason' => 'Penunjukan diperbarui setelah masa berlaku berakhir.'])->save();
            }
            $lead = CaseLeadAssignment::query()->create(['clinic_id' => $locked->clinic_id, 'visit_id' => $locked->id, 'doctor_id' => $doctor->id, 'approver_user_id' => $actor->id,
                'role' => $data['role'], 'purpose' => $data['purpose'], 'verification_evidence' => $data['verification_evidence'], 'starts_at' => now(), 'ends_at' => $ends->utc(),
                'request_id' => $data['request_id'], 'request_fingerprint' => $this->careFingerprint($locked, $actor, 'lead.appoint', $data)]);
            $this->recordCareEvent($locked, $actor, 'lead.appoint', $data, $locked->status, ['lead_assignment_id' => $lead->id]);
            $this->notificationService->careWorkflow($locked, $doctor->user, 'lead_assigned', $lead->id);

            return $lead;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function revokeCaseLead(CaseLeadAssignment $lead, User $actor, array $data): void
    {
        DB::transaction(function () use ($lead, $actor, $data): void {
            [$visit] = $this->lockCareVisit($lead->visit);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            abort_unless($actor->can('appointLead', $visit), 403);
            if ($this->replayedCareEvent($visit, $actor, 'lead.revoke', $data) !== null) {
                return;
            }
            $this->expectedCareVersion($visit, $data, false);
            $locked = CaseLeadAssignment::query()->where('visit_id', $visit->id)->lockForUpdate()->findOrFail($lead->id);
            $locked->forceFill(['revoked_at' => $locked->revoked_at ?? now(), 'revoked_by_user_id' => $actor->id, 'revocation_reason' => $data['reason']])->save();
            $this->recordCareEvent($visit, $actor, 'lead.revoke', $data, $visit->status, ['lead_assignment_id' => $locked->id]);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function requestContinuation(Visit $visit, User $actor, array $data): VisitContinuation
    {
        return DB::transaction(function () use ($visit, $actor, $data): VisitContinuation {
            [$source] = $this->lockCareVisit($visit);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            abort_unless($actor->can('coordinate', $source), 403);
            if (($event = $this->replayedCareEvent($source, $actor, 'handoff.request', $data)) !== null) {
                return VisitContinuation::query()->findOrFail($event->details['handoff_id']);
            }
            $this->expectedCareVersion($source, $data, true);
            if ($source->status !== VisitStatus::Interrupted) {
                throw ValidationException::withMessages(['handoff' => 'Tetapkan interupsi secara klinis sebelum mengajukan kelanjutan kepada dokter lain.']);
            }
            foreach ($source->continuations()->where('status', 'REQUESTED')->where('expires_at', '<=', now())->lockForUpdate()->get() as $expired) {
                $expired->status = 'EXPIRED';
                $expired->lock_version++;
                $expired->save();
            }
            if ($source->continuations()->where(fn ($query) => $query->whereNotNull('to_visit_id')->orWhereIn('status', ['REQUESTED', 'ACCEPTED', 'BOOKED', 'IN_PROGRESS']))->exists()) {
                throw ValidationException::withMessages(['handoff' => 'Episode ini sudah memiliki kelanjutan aktif atau episode penerus. Tinjau kelanjutannya terlebih dahulu.']);
            }
            $service = Service::query()->where('clinic_id', $source->clinic_id)->where('is_active', true)->lockForUpdate()->findOrFail($data['service_id']);
            $receiver = Doctor::query()->where('clinic_id', $source->clinic_id)->where('is_active', true)->whereKeyNot($source->doctor_id)
                ->whereHas('user', fn ($query) => $query->where('is_active', true)->whereNotNull('email_verified_at')->whereHas('roles', fn ($roles) => $roles->where('name', 'Doctor')))
                ->whereHas('services', fn ($query) => $query->whereKey($service->id))->findOrFail($data['receiving_doctor_id']);
            $protected = $source->service_id === $service->id;
            if (! $protected) {
                abort_unless($this->subscriptionService->allowsOperationalWrites($source->clinic), 403, 'Layanan baru memerlukan hak operasional aktif.');
                if (empty($data['additional_fee_consented']) || ! isset($data['expected_service_price']) || number_format((float) $data['expected_service_price'], 2, '.', '') !== number_format((float) $service->price, 2, '.', '')) {
                    throw ValidationException::withMessages(['additional_fee_consented' => 'Layanan berbeda memerlukan tarif yang masih berlaku dan persetujuan biaya pasien.']);
                }
            }
            if (empty($data['patient_consent']) || blank($data['patient_consent_note'] ?? null)) {
                throw ValidationException::withMessages(['patient_consent' => 'Persetujuan pasien atas pergantian dokter wajib dicatat.']);
            }
            abort_unless(isset($data['allergy_version']) && hash_equals($source->patient->allergyVersion(), $data['allergy_version']), 409, 'Data alergi berubah. Tinjau kembali sebelum merilis ringkasan.');
            $expires = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['expires_at'], $source->clinic->settings?->timezone ?? 'Asia/Jakarta');
            if ($expires->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['expires_at' => 'Batas penerimaan harus setelah waktu sekarang.']);
            }
            $root = Visit::query()->where('clinic_id', $source->clinic_id)->where('patient_id', $source->patient_id)->findOrFail($source->quota_origin_visit_id ?? $source->id);
            $handoff = VisitContinuation::query()->create(['clinic_id' => $source->clinic_id, 'from_visit_id' => $source->id, 'root_visit_id' => $root->id, 'receiving_doctor_id' => $receiver->id,
                'service_id' => $service->id, 'service_name' => $protected ? $source->service_name : $service->name, 'service_price' => $protected ? $source->service_price : $service->price, 'protected_service' => $protected,
                'summary' => [...Arr::only($data['summary'], ['context', 'allergies', 'medications', 'handoff_goal']), 'critical_allergies' => $source->patient->criticalAllergies()->get()->map(fn ($allergy): array => ['substance' => $allergy->substance, 'reaction' => $allergy->reaction, 'severity' => $allergy->severity->label(), 'requires_review' => $allergy->lineage_id === null])->all()], 'allergy_version_snapshot' => $data['allergy_version'], 'source_record_version' => $source->medicalRecord->lock_version,
                'requested_by_user_id' => $actor->id, 'patient_consented_at' => now(), 'patient_consent_note' => $data['patient_consent_note'], 'expires_at' => $expires->utc(),
                'request_id' => $data['request_id'], 'request_fingerprint' => $this->careFingerprint($source, $actor, 'handoff.request', $data)]);
            $source->interruptionTasks()->where('kind', 'CARE')->whereNotIn('status', ['RESOLVED', 'CANCELLED'])->update(['status' => 'WAITING_DOCTOR']);
            $this->recordCareEvent($source, $actor, 'handoff.request', $data, $source->status, ['handoff_id' => $handoff->id]);
            $this->notificationService->careWorkflow($source, $receiver->user, 'handoff_requested', $handoff->id);

            return $handoff;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function respondContinuation(VisitContinuation $handoff, User $actor, string $action, array $data): VisitContinuation
    {
        return DB::transaction(function () use ($handoff, $actor, $action, $data): VisitContinuation {
            [$source] = $this->lockCareVisit($handoff->source);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            $locked = VisitContinuation::query()->where('clinic_id', $source->clinic_id)->lockForUpdate()->findOrFail($handoff->id);
            $isReceiver = $actor->is_active && $actor->clinic_id === $source->clinic_id && $actor->hasRole('Doctor') && $actor->can('visit.start')
                && $locked->receivingDoctor->user_id === $actor->id && $locked->receivingDoctor->is_active;
            abort_unless($action === 'cancel' ? $actor->can('coordinate', $source) : $isReceiver, 403);
            $eventData = [...$data, 'handoff_id' => $locked->id];
            if ($this->replayedCareEvent($source, $actor, 'handoff.'.$action, $eventData) !== null) {
                return $locked;
            }
            abort_unless(isset($data['expected_version']) && (int) $data['expected_version'] === $locked->lock_version, 409, 'Permintaan kelanjutan sudah berubah. Tinjau status terbaru.');
            if ($locked->to_visit_id !== null || ! in_array($locked->status, ['REQUESTED', 'ACCEPTED', 'BOOKED'], true)) {
                throw ValidationException::withMessages(['handoff' => 'Permintaan ini tidak dapat diubah. Pelayanan yang sudah dimulai memakai interupsi pada episodenya sendiri.']);
            }
            if ($action === 'accept') {
                if ($locked->status !== 'REQUESTED' || ! $locked->canReadSummary($actor) || ! $locked->expires_at->isFuture()) {
                    throw ValidationException::withMessages(['handoff' => 'Permintaan tidak tersedia untuk diterima. Minta pengirim meninjau status dan batas penerimaan.']);
                }
                $this->assertHandoffSnapshotCurrent($locked);
                $locked->accepted_at = now();
                $locked->status = 'ACCEPTED';
                $source->interruptionTasks()->where('kind', 'CARE')->whereNotIn('status', ['RESOLVED', 'CANCELLED'])->update(['status' => 'IN_PROGRESS']);
            } elseif (in_array($action, ['decline', 'cancel'], true)) {
                if (blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Alasan klinis keputusan wajib dicatat.']);
                }
                if ($locked->target_appointment_id !== null) {
                    $appointment = $locked->targetAppointment()->lockForUpdate()->firstOrFail();
                    if ($appointment->visit()->exists()) {
                        throw ValidationException::withMessages(['handoff' => 'Pemeriksaan penerima sudah dimulai. Gunakan interupsi pada episode penerima.']);
                    }
                    if ($appointment->status === AppointmentStatus::Booked) {
                        app(QueueWorkflowService::class)->cancel($appointment->queue, $actor, 'Permintaan kelanjutan pelayanan dibatalkan.');
                        $locked->refresh();
                    }
                }
                if ($action === 'decline') {
                    $locked->status = 'DECLINED';
                    $locked->declined_at = now();
                    $locked->decline_reason = $data['reason'];
                } else {
                    $locked->status = 'CANCELLED';
                    $locked->cancelled_at = now();
                    $locked->cancellation_reason = $data['reason'];
                }
                $source->interruptionTasks()->where('kind', 'CARE')->whereNotIn('status', ['RESOLVED', 'CANCELLED'])->update(['status' => $action === 'decline' ? 'WAITING_DOCTOR' : 'WAITING_PATIENT']);
            } else {
                abort(404);
            }
            $locked->lock_version++;
            $locked->save();
            $this->recordCareEvent($source, $actor, 'handoff.'.$action, $eventData, $source->status, ['handoff_id' => $locked->id]);
            $this->notificationService->careWorkflow($source, $source->doctor->user, 'handoff_'.$action, $locked->id);

            return $locked;
        }, 3);
    }

    public function assertHandoffSnapshotCurrent(VisitContinuation $handoff): void
    {
        abort_unless($handoff->source->clinic_id === $handoff->clinic_id && $handoff->receivingDoctor->clinic_id === $handoff->clinic_id
            && hash_equals($handoff->allergy_version_snapshot, $handoff->source->patient->allergyVersion())
            && (int) $handoff->source_record_version === (int) $handoff->source->medicalRecord()->value('lock_version'), 409,
            'Informasi klinis sumber berubah sejak ringkasan dirilis. Minta pengirim merilis ringkasan terbaru sebelum menerima atau memulai pelayanan.');
    }

    /** @param array<string,mixed> $data */
    public function resolveInterruptionTask(InterruptionTask $task, User $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($task, $actor, $action, $data): void {
            [$visit] = $this->lockCareVisit($task->visit);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            $locked = InterruptionTask::query()->where('visit_id', $visit->id)->lockForUpdate()->findOrFail($task->id);
            $clinical = $actor->can('coordinate', $visit);
            $operational = $actor->is_active && $actor->clinic_id === $visit->clinic_id && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('queue.manage');
            abort_unless($action === 'status' ? ($clinical || $operational) : $clinical, 403);
            $eventData = [...$data, 'task_id' => $locked->id];
            if ($this->replayedCareEvent($visit, $actor, 'task.'.$action, $eventData) !== null) {
                return;
            }
            abort_unless(isset($data['expected_version']) && (int) $data['expected_version'] === $locked->lock_version, 409, 'Tugas telah berubah. Tinjau status terbaru.');
            if ($locked->kind === 'DOCUMENTATION') {
                throw ValidationException::withMessages(['task' => 'Dokumentasi interupsi hanya diselesaikan melalui pengesahan penulis RME, bukan penutupan tugas manual.']);
            }
            if (in_array($locked->status, ['RESOLVED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages(['task' => 'Tugas ini sudah ditutup.']);
            }
            if ($action === 'resolve') {
                if (empty($data['safe_to_close']) || blank($data['patient_decision'] ?? null) || blank($data['clinical_assessment'] ?? null)) {
                    throw ValidationException::withMessages(['task' => 'Penutupan memerlukan keputusan pasien dan penilaian klinis keselamatan tindak lanjut.']);
                }
                $locked->patient_decision = $data['patient_decision'];
                $locked->clinical_assessment = $data['clinical_assessment'];
                $locked->status = 'RESOLVED';
                $locked->resolved_by_user_id = $actor->id;
                $locked->resolved_at = now();
            } elseif ($action === 'cancel') {
                $replacement = InterruptionTask::query()->where('clinic_id', $visit->clinic_id)->where('kind', 'CARE')->whereNotIn('status', ['RESOLVED', 'CANCELLED'])
                    ->whereKeyNot($locked->id)->whereHas('visit', fn ($query) => $query->where('patient_id', $visit->patient_id))->findOrFail($data['replacement_task_id'] ?? 0);
                abort_unless($actor->can('coordinate', $replacement->visit), 403);
                if (blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Alasan pembatalan tugas salah input atau duplikat wajib dicatat.']);
                }
                $locked->status = 'CANCELLED';
                $locked->replacement_task_id = $replacement->id;
                $locked->resolved_by_user_id = $actor->id;
                $locked->resolved_at = now();
            } elseif ($action === 'status') {
                if (! in_array($data['status'] ?? null, ['OPEN', 'IN_PROGRESS', 'WAITING_DOCTOR', 'WAITING_PATIENT'], true)) {
                    throw ValidationException::withMessages(['status' => 'Pilih status penanganan yang sah.']);
                }
                $locked->status = $data['status'];
            } else {
                abort(404);
            }
            $locked->lock_version++;
            $locked->save();
            $this->recordCareEvent($visit, $actor, 'task.'.$action, $eventData, $visit->status, ['task_id' => $locked->id, 'task_status' => $locked->status, 'audience' => $action === 'status' ? 'operations' : 'clinical']);
        }, 3);
    }

    /** @return array{Visit, Queue} */
    private function lockCareVisit(Visit $visit): array
    {
        Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
        $persisted = Visit::query()->where('clinic_id', $visit->clinic_id)->findOrFail($visit->id);
        $queue = Queue::query()->where('clinic_id', $persisted->clinic_id)->where('appointment_id', $persisted->appointment_id)->firstOrFail();
        DoctorSchedule::query()->lockForUpdate()->findOrFail($queue->doctor_schedule_id);
        $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);
        $locked = Visit::query()->where('clinic_id', $persisted->clinic_id)->lockForUpdate()->findOrFail($persisted->id);
        abort_unless($queue->doctor_id === $locked->doctor_id && $locked->appointment->clinic_id === $locked->clinic_id
            && $locked->appointment->patient_id === $locked->patient_id && $locked->appointment->doctor_id === $locked->doctor_id
            && $locked->appointment->service_id === $locked->service_id, 409, 'Relasi antrean dan pelayanan perlu ditinjau sebelum melanjutkan.');

        return [$locked, $queue];
    }

    private function ensurePaused(Visit $visit, Queue $queue): void
    {
        if ($visit->status !== VisitStatus::Paused || $queue->status !== QueueStatus::Paused || $visit->paused_at === null) {
            throw ValidationException::withMessages(['workflow' => 'Pelayanan ini tidak sedang dijeda atau datanya perlu ditinjau.']);
        }
    }

    private function consumeResumeReservation(Visit $visit): void
    {
        $reservation = $visit->resumeReservation()->lockForUpdate()->first();
        if ($reservation !== null && $reservation->state === 'ACTIVE') {
            $reservation->update(['state' => 'CONSUMED', 'closed_at' => now()]);
        }
    }

    /** @param array<string,mixed> $data */
    private function expectedCareVersion(Visit $visit, array $data, bool $checkRecord): void
    {
        abort_unless(isset($data['workflow_version']) && (int) $data['workflow_version'] === $visit->workflow_version, 409, 'Alur pelayanan sudah berubah. Input Anda tetap tersedia; tinjau status terbaru.');
        if ($checkRecord) {
            $record = $visit->medicalRecord()->select(['id', 'visit_id', 'lock_version'])->lockForUpdate()->firstOrFail();
            $version = (int) $record->lock_version;
            abort_unless(isset($data['record_version']) && (int) $data['record_version'] === $version, 409, 'Draft rekam medis sudah berubah. Simpan dan tinjau draft terbaru sebelum melanjutkan.');
        }
    }

    /** @param array<string,mixed> $data */
    private function careFingerprint(Visit $visit, User $actor, string $action, array $data): string
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if ($value instanceof UploadedFile) {
                return ['uploaded_content_hash' => hash_file('sha256', $value->getRealPath())];
            }

            return is_array($value) ? array_map($normalize, $value) : $value;
        };

        return hash('sha256', json_encode([$visit->id, $actor->id, $action, $normalize(Arr::except($data, ['request_id']))], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $data */
    private function replayedCareEvent(Visit $visit, User $actor, string $action, array $data): ?VisitWorkflowEvent
    {
        if (! isset($data['request_id']) || ! Str::isUuid($data['request_id'])) {
            throw ValidationException::withMessages(['request_id' => 'Identitas aksi pelayanan tidak valid.']);
        }
        $event = VisitWorkflowEvent::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
        if ($event !== null) {
            abort_unless($event->visit_id === $visit->id && $event->actor_user_id === $actor->id && hash_equals($event->request_fingerprint, $this->careFingerprint($visit, $actor, $action, $data)), 409, 'Identitas aksi sudah digunakan untuk permintaan berbeda.');
        }

        return $event;
    }

    /** @param array<string,mixed> $data
     * @param  array<string,mixed>  $details
     */
    private function recordCareEvent(Visit $visit, User $actor, string $action, array $data, VisitStatus $from, array $details = []): void
    {
        $visit->workflow_version++;
        $visit->save();
        $event = VisitWorkflowEvent::query()->create(['clinic_id' => $visit->clinic_id, 'visit_id' => $visit->id, 'actor_user_id' => $actor->id, 'action' => $action,
            'from_status' => $from->value, 'to_status' => $visit->status->value, 'workflow_version' => $visit->workflow_version, 'record_version' => $visit->medicalRecord()->value('lock_version'),
            'reason' => $data['reason'] ?? $data['verification_evidence'] ?? 'Koordinasi klinis khusus kasus.', 'details' => $details, 'request_id' => $data['request_id'],
            'request_fingerprint' => $this->careFingerprint($visit, $actor, $action, $data)]);
        $this->auditLogger->recordForClinic($visit->clinic, 'visit.workflow_changed', $visit, ['workflow_event_id' => $event->id, 'action' => $action, 'from' => $from->value, 'to' => $visit->status->value], $actor);
    }

    private function ensureDoctor(Visit $visit, User $actor): void
    {
        $actor = $actor->fresh();
        if ($actor === null) {
            throw new AuthorizationException('Akun dokter tidak lagi tersedia.');
        }
        if (! $actor->is_active || ! $actor->hasRole('Doctor') || ! $visit->doctor->is_active || $visit->doctor->user_id !== $actor->id || $actor->clinic_id !== $visit->clinic_id) {
            throw new AuthorizationException('Dokter hanya dapat mengelola kunjungannya sendiri.');
        }
    }

    private function ensureInProgress(Visit $visit): void
    {
        if ($visit->status !== VisitStatus::InProgress) {
            throw ValidationException::withMessages(['visit' => 'Kunjungan ini sudah tidak aktif.']);
        }
    }

    /** @param array<string, mixed> $data */
    public function saveAllergy(Visit $visit, User $actor, array $data, ?PatientAllergy $allergy = null): PatientAllergy
    {
        return DB::transaction(function () use ($visit, $actor, $data, $allergy): PatientAllergy {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $actor = $actor->fresh();
            abort_unless($actor?->can('updateAllergies', $lockedVisit), 403);
            $patient = Patient::query()->where('clinic_id', $lockedVisit->clinic_id)->lockForUpdate()->findOrFail($lockedVisit->patient_id);
            $isNew = $allergy === null;
            $allergy = $isNew ? $patient->allergies()->make() : $patient->allergies()->lockForUpdate()->findOrFail($allergy->id);
            $previousId = $allergy->id;
            if (! isset($data['request_id']) || ! Str::isUuid($data['request_id'])) {
                throw ValidationException::withMessages(['request_id' => 'Identitas permintaan alergi tidak valid. Muat ulang formulir.']);
            }
            $fingerprint = hash('sha256', json_encode([
                $previousId, $actor->id, Arr::only($data, ['substance', 'reaction', 'severity', 'is_active', 'expected_version', 'revision_reason']),
            ], JSON_THROW_ON_ERROR));
            $existing = PatientAllergy::query()->where('request_id', $data['request_id'])->first();
            if ($existing !== null) {
                abort_unless($existing->patient_id === $patient->id && hash_equals($existing->request_fingerprint, $fingerprint), 409,
                    'Identitas permintaan alergi sudah digunakan. Muat ulang formulir untuk permintaan baru.');

                return $existing;
            }

            if (! $isNew) {
                abort_unless(isset($data['expected_version']) && hash_equals($allergy->revisionToken(), $data['expected_version'])
                    && $allergy->superseded_by_id === null, 409,
                    'Catatan alergi sudah berubah. Input Anda tetap tersedia; buka versi terbaru untuk membandingkan sebelum membuat revisi.');
                if (blank($data['revision_reason'] ?? null)) {
                    throw ValidationException::withMessages(['revision_reason' => 'Alasan klinis perubahan alergi wajib diisi.']);
                }
            }

            $previous = $isNew ? null : $allergy;
            $allergy = $patient->allergies()->make(Arr::only($data, ['substance', 'reaction', 'severity', 'is_active']));
            $allergy->recorded_by_user_id = $actor->id;
            $allergy->request_id = $data['request_id'];
            $allergy->request_fingerprint = $fingerprint;
            $allergy->revision_reason = $data['revision_reason'] ?? null;
            $allergy->lineage_id = $previous?->lineage_id ?? $previous?->id;
            $allergy->supersedes_id = $previous?->id;
            $allergy->revision_number = ($previous?->revision_number ?? 0) + 1;
            $allergy->save();
            if ($previous !== null) {
                $previous->forceFill(['lineage_id' => $allergy->lineage_id, 'superseded_by_id' => $allergy->id])->save();
            } else {
                $allergy->forceFill(['lineage_id' => $allergy->id])->save();
            }
            $this->auditLogger->recordForClinic($lockedVisit->clinic, $isNew ? 'patient_allergy.created' : 'patient_allergy.updated', $allergy, [
                'patient_id' => $patient->id,
                'visit_id' => $lockedVisit->id,
                'is_active' => $allergy->is_active,
                'previous_allergy_id' => $previousId,
            ], $actor);

            return $allergy;
        }, 3);
    }

    private function loadVisit(Visit $visit): Visit
    {
        return $visit->refresh()->load([
            'clinic', 'appointment', 'patient.criticalAllergies', 'doctor.user', 'service', 'queue',
            'medicalRecord.vitalSign', 'medicalRecord.diagnoses', 'medicalRecord.treatments',
            'medicalRecord.prescription.items', 'medicalRecord.revisions.correctedBy',
        ]);
    }
}
