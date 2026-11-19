<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentReplacement;
use App\Models\Clinic;
use App\Models\DoctorSchedule;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Scopes\ClinicScope;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitContinuation;
use App\Models\VisitResumeReservation;
use App\QueueStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private MedicalRecordNumberGenerator $medicalRecordNumberGenerator,
        private AuditLogger $auditLogger,
        private ClinicNotificationService $notificationService,
        private SubscriptionService $subscriptionService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{appointment: Appointment, access_token: string, created: bool}
     */
    public function book(Clinic $clinic, array $data, ?User $actor = null, string $source = 'ONLINE'): array
    {
        return $this->createBooking($clinic, $data, $actor, $source);
    }

    /** @param array<string, mixed> $data
     * @return array{appointment: Appointment, access_token: string, created: bool}
     */
    private function createBooking(Clinic $clinic, array $data, ?User $actor, string $source, ?Appointment $replacementFor = null, ?VisitContinuation $continuation = null): array
    {
        return DB::transaction(function () use ($clinic, $data, $actor, $source, $replacementFor, $continuation): array {
            $clinic = Clinic::query()->with('settings')->lockForUpdate()->findOrFail($clinic->id);
            $schedule = DoctorSchedule::query()
                ->whereKey($data['doctor_schedule_id'])
                ->where('doctor_id', $data['doctor_id'])
                ->where('is_active', true)
                ->whereHas('doctor', function ($query) use ($clinic): void {
                    $query->where('clinic_id', $clinic->id)
                        ->where('is_active', true)
                        ->whereHas('user', fn ($userQuery) => $userQuery->where('is_active', true));
                })
                ->lockForUpdate()
                ->first();

            if (! $clinic->is_active || $schedule === null) {
                throw ValidationException::withMessages([
                    'doctor_schedule_id' => 'Jadwal dokter tidak tersedia.',
                ]);
            }

            $accessToken = $this->accessToken($data['idempotency_key']);
            $existingAppointment = Appointment::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existingAppointment !== null) {
                if ($replacementFor !== null || $continuation !== null) {
                    throw ValidationException::withMessages(['request_id' => 'Identitas permintaan sudah digunakan oleh booking lain. Tinjau ulang formulir.']);
                }
                if ($existingAppointment->clinic_id !== $clinic->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Permintaan booking tidak valid.',
                    ]);
                }

                return [
                    'appointment' => $this->loadBookingRelations($existingAppointment),
                    'access_token' => $accessToken,
                    'created' => false,
                ];
            }

            try {
                if (! $continuation?->protected_service && $replacementFor === null) {
                    $this->subscriptionService->assertBookingAvailable($clinic);
                } elseif ($replacementFor !== null) {
                    $this->subscriptionService->assertVisitCanStart($clinic, $replacementFor);
                }
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
            }

            $appointmentDate = CarbonImmutable::parse($data['appointment_date'], $clinic->settings?->timezone ?? 'Asia/Jakarta')->startOfDay();
            $this->validateAppointmentDate($clinic, $schedule, $appointmentDate);
            app(DoctorAvailabilityService::class)->assertSessionAvailable($clinic, $schedule, $appointmentDate);

            $service = Service::query()
                ->whereKey($data['service_id'])
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->whereHas('doctors', fn ($query) => $query->whereKey($schedule->doctor_id))
                ->lockForUpdate()
                ->first();

            if ($service === null) {
                throw ValidationException::withMessages([
                    'service_id' => 'Layanan tidak tersedia untuk dokter yang dipilih.',
                ]);
            }

            $price = $continuation !== null ? $continuation->service_price : ($replacementFor !== null && $replacementFor->service_id === $service->id ? $replacementFor->service_price : $service->price);
            if ($replacementFor !== null && (! isset($data['expected_price']) || number_format((float) $data['expected_price'], 2, '.', '') !== number_format((float) $price, 2, '.', ''))) {
                throw ValidationException::withMessages(['expected_price' => 'Tarif berubah. Tinjau ulang ringkasan sebelum mengonfirmasi.']);
            }

            $reserved = $this->reservedCapacity($clinic, $schedule, $appointmentDate);

            if ($reserved >= $schedule->quota) {
                throw ValidationException::withMessages([
                    'doctor_schedule_id' => 'Kuota sesi ini sudah penuh.',
                ]);
            }

            $patient = $this->resolvePatient($clinic, $data, $actor, ($replacementFor !== null || $continuation !== null) && ! empty($data['patient_reviewed']));
            $appointment = Appointment::query()->create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $schedule->doctor_id,
                'service_id' => $service->id,
                'doctor_schedule_id' => $schedule->id,
                'created_by_user_id' => $actor?->id,
                'appointment_date' => $appointmentDate->toDateString(),
                'booking_code' => $this->uniqueBookingCode($clinic),
                'access_token_hash' => hash('sha256', $accessToken),
                'idempotency_key' => $data['idempotency_key'],
                'status' => AppointmentStatus::Booked,
                'source' => $source,
                'service_name' => $service->name,
                'service_price' => $price,
                'notes' => $data['notes'] ?? null,
            ]);
            if ($continuation?->protected_service) {
                $appointment->entitlement_started_at = $continuation->root->appointment->entitlement_started_at ?? $continuation->root->appointment->created_at;
                $appointment->save();
            }
            if ($replacementFor !== null) {
                $appointment->entitlement_started_at = $replacementFor->entitlement_started_at ?? $replacementFor->created_at;
                $appointment->save();
            }

            $queueNumber = $this->nextQueueNumber($clinic, $schedule, $appointmentDate);
            $queuePrefix = Str::upper($clinic->settings?->queue_prefix ?? 'A');

            $appointment->queue()->create([
                'clinic_id' => $clinic->id,
                'doctor_id' => $schedule->doctor_id,
                'doctor_schedule_id' => $schedule->id,
                'queue_date' => $appointmentDate->toDateString(),
                'queue_number' => $queueNumber,
                'display_number' => sprintf('%s-%03d', $queuePrefix, $queueNumber),
                'status' => QueueStatus::Booked,
            ]);

            $this->auditLogger->recordForClinic($clinic, 'appointment.created', $appointment, [
                'source' => $source,
                'appointment_date' => $appointmentDate->toDateString(),
                'queue_number' => $queueNumber,
            ], $actor);
            $this->notificationService->appointmentStatusChanged($appointment, AppointmentStatus::Booked);

            return [
                'appointment' => $this->loadBookingRelations($appointment),
                'access_token' => $accessToken,
                'created' => true,
            ];
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function scheduleContinuation(VisitContinuation $continuation, User $actor, array $data): Appointment
    {
        return DB::transaction(function () use ($continuation, $actor, $data): Appointment {
            $clinic = Clinic::query()->lockForUpdate()->findOrFail($continuation->clinic_id);
            abort_unless($actor->is_active && $actor->clinic_id === $clinic->id && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('appointment.create'), 403);
            $locked = VisitContinuation::query()->where('clinic_id', $clinic->id)->lockForUpdate()->findOrFail($continuation->id);
            $fingerprint = hash('sha256', json_encode([$locked->id, $actor->id, Arr::only($data, ['doctor_schedule_id', 'appointment_date', 'expected_version', 'patient_reviewed'])], JSON_THROW_ON_ERROR));
            if ($locked->booking_request_id === $data['request_id']) {
                abort_unless(hash_equals($locked->booking_request_fingerprint, $fingerprint), 409, 'Permintaan booking berbeda.');

                return Appointment::query()->where('clinic_id', $clinic->id)->where('idempotency_key', $data['request_id'])->firstOrFail();
            }
            abort_unless((int) $data['expected_version'] === $locked->lock_version, 409, 'Kelanjutan sudah berubah. Tinjau ulang.');
            if ($locked->status !== 'ACCEPTED' || $locked->target_appointment_id !== null || $locked->to_visit_id !== null) {
                throw ValidationException::withMessages(['handoff' => 'Kelanjutan harus sudah diterima dan belum mempunyai booking.']);
            }
            app(VisitWorkflowService::class)->assertHandoffSnapshotCurrent($locked);
            $result = $this->createBooking($clinic, [...$data, 'patient_id' => $locked->source->patient_id, 'doctor_id' => $locked->receiving_doctor_id,
                'service_id' => $locked->service_id, 'idempotency_key' => $data['request_id']], $actor, 'RECEPTIONIST', null, $locked);
            $locked->forceFill(['target_appointment_id' => $result['appointment']->id, 'status' => 'BOOKED', 'lock_version' => $locked->lock_version + 1,
                'booking_request_id' => $data['request_id'], 'booking_request_fingerprint' => $fingerprint])->save();
            app(FollowUpService::class)->continuationBooked($locked, $result['appointment'], $actor);
            app(PatientBillingService::class)->continuationBooked($locked, $result['appointment']);
            $this->auditLogger->recordForClinic($clinic, 'care_continuation.booked', $locked, ['appointment_id' => $result['appointment']->id], $actor);

            return $result['appointment'];
        }, 3);
    }

    /** The caller holds the clinic lock so counters and queues commit together. */
    public function nextQueueNumber(Clinic $clinic, DoctorSchedule $schedule, CarbonImmutable $date): int
    {
        $scope = ['clinic_id' => $clinic->id, 'doctor_id' => $schedule->doctor_id, 'doctor_schedule_id' => $schedule->id, 'queue_date' => $date->toDateString()];
        $last = max((int) DB::table('queue_number_counters')->where($scope)->value('last_number'), (int) DB::table('queues')->where(Arr::except($scope, ['queue_date']))->whereDate('queue_date', $scope['queue_date'])->max('queue_number'));
        DB::table('queue_number_counters')->updateOrInsert($scope, ['last_number' => $last + 1]);

        return $last + 1;
    }

    public function rememberQueueNumber(Queue $queue): void
    {
        $scope = ['clinic_id' => $queue->clinic_id, 'doctor_id' => $queue->doctor_id, 'doctor_schedule_id' => $queue->doctor_schedule_id, 'queue_date' => $queue->queue_date->toDateString()];
        $last = max((int) DB::table('queue_number_counters')->where($scope)->value('last_number'), (int) DB::table('queues')->where(Arr::except($scope, ['queue_date']))->whereDate('queue_date', $scope['queue_date'])->max('queue_number'));
        DB::table('queue_number_counters')->updateOrInsert($scope, ['last_number' => $last]);
    }

    public function reservedCapacity(Clinic $clinic, DoctorSchedule $schedule, CarbonImmutable $date): int
    {
        $bookings = Appointment::query()->where('clinic_id', $clinic->id)->where('doctor_schedule_id', $schedule->id)
            ->whereDate('appointment_date', $date->toDateString())->where('status', '!=', AppointmentStatus::Cancelled)->count();
        $resumes = VisitResumeReservation::query()->join('visits', 'visits.id', '=', 'visit_resume_reservations.visit_id')
            ->join('appointments', 'appointments.id', '=', 'visits.appointment_id')->where('visit_resume_reservations.clinic_id', $clinic->id)
            ->where('visit_resume_reservations.doctor_schedule_id', $schedule->id)->whereDate('resume_date', $date->toDateString())->where('state', '!=', 'RELEASED')
            ->where(fn ($query) => $query->whereColumn('appointments.doctor_schedule_id', '!=', 'visit_resume_reservations.doctor_schedule_id')
                ->orWhereColumn('appointments.appointment_date', '!=', 'visit_resume_reservations.resume_date'))->distinct()->count('visit_resume_reservations.visit_id');

        return $bookings + $resumes;
    }

    public function reserveResume(Visit $visit, User $actor, DoctorSchedule $schedule, string $requestId): VisitResumeReservation
    {
        $clinic = $visit->clinic;
        $date = CarbonImmutable::now($clinic->settings?->timezone ?? 'Asia/Jakarta')->startOfDay();
        abort_unless($schedule->doctor_id === $visit->doctor_id && $schedule->doctor->clinic_id === $visit->clinic_id, 404);
        $current = $visit->resume_reservation_id !== null ? VisitResumeReservation::query()->lockForUpdate()->findOrFail($visit->resume_reservation_id) : null;
        if ($current !== null && $current->state === 'READY' && $current->doctor_schedule_id === $schedule->id && $current->resume_date->isSameDay($date)) {
            return $current;
        }
        $alreadyReserved = ($visit->appointment->doctor_schedule_id === $schedule->id && $visit->appointment->appointment_date->isSameDay($date))
            || VisitResumeReservation::query()->where('visit_id', $visit->id)->where('doctor_schedule_id', $schedule->id)->whereDate('resume_date', $date->toDateString())->where('state', '!=', 'RELEASED')->exists();
        if (! $alreadyReserved) {
            $this->validateAppointmentDate($clinic, $schedule, $date);
        }
        if ((! $schedule->is_active && ! $alreadyReserved) || (! $alreadyReserved && $this->reservedCapacity($clinic, $schedule, $date) >= $schedule->quota)) {
            throw ValidationException::withMessages(['doctor_schedule_id' => 'Kapasitas sesi untuk melanjutkan pemeriksaan tidak tersedia. Pilih sesi lain.']);
        }
        $readyAt = $current !== null && $current->state === 'READY' ? $current->ready_at : now();
        if ($current !== null && $current->state === 'READY') {
            $current->update(['state' => 'RELEASED', 'closed_at' => now()]);
        }
        $reservation = VisitResumeReservation::query()->create(['clinic_id' => $visit->clinic_id, 'visit_id' => $visit->id, 'doctor_schedule_id' => $schedule->id,
            'resume_date' => $date->toDateString(), 'ready_at' => $readyAt, 'ready_by_user_id' => $actor->id, 'request_id' => $requestId, 'state' => 'READY']);
        $visit->resume_reservation_id = $reservation->id;
        $visit->save();

        return $reservation;
    }

    /** @param array<string, mixed> $data
     * @return array{schedule: DoctorSchedule, service: Service, price: string, date: string}
     */
    public function replacementPreview(Appointment $original, User $actor, array $data): array
    {
        $this->authorizeReplacement($original, $actor);
        $this->assertReplaceable($original);
        $this->assertContinuationReplacement($original, $data);
        $schedule = DoctorSchedule::query()->whereKey($data['doctor_schedule_id'])->where('doctor_id', $data['doctor_id'])->where('is_active', true)
            ->whereHas('doctor', fn ($query) => $query->where('clinic_id', $original->clinic_id)->where('is_active', true)->whereHas('user', fn ($users) => $users->where('is_active', true)))
            ->with('doctor.user')->first();
        if ($schedule === null) {
            throw ValidationException::withMessages(['doctor_schedule_id' => 'Sesi tujuan tidak tersedia.']);
        }
        $clinic = $original->clinic;
        $date = CarbonImmutable::parse($data['appointment_date'], $clinic->settings?->timezone ?? 'Asia/Jakarta')->startOfDay();
        $this->validateAppointmentDate($clinic, $schedule, $date);
        app(DoctorAvailabilityService::class)->assertSessionAvailable($clinic, $schedule, $date);
        $service = Service::query()->whereKey($data['service_id'])->where('clinic_id', $original->clinic_id)->where('is_active', true)
            ->whereHas('doctors', fn ($query) => $query->whereKey($schedule->doctor_id))->first();
        if ($service === null) {
            throw ValidationException::withMessages(['service_id' => 'Layanan tujuan tidak tersedia.']);
        }

        return ['schedule' => $schedule, 'service' => $service, 'price' => (string) ($original->service_id === $service->id ? $original->service_price : $service->price), 'date' => $date->toDateString()];
    }

    /** @param array<string, mixed> $data */
    public function replace(Appointment $original, User $actor, array $data): Appointment
    {
        $this->authorizeReplacement($original, $actor);

        return DB::transaction(function () use ($original, $actor, $data): Appointment {
            $clinic = Clinic::query()->with('settings')->lockForUpdate()->findOrFail($original->clinic_id);
            $this->authorizeReplacement($original, $actor);
            if (! $this->protectedContinuation($original)) {
                abort_unless($this->subscriptionService->allowsOperationalWrites($clinic), 403, 'Akses operasional klinik sedang hanya-baca.');
            }
            $fingerprint = hash('sha256', json_encode([$original->id, $actor->id, Arr::only($data, ['expected_version', 'doctor_id', 'service_id', 'doctor_schedule_id', 'appointment_date', 'reason', 'reason_source', 'withdraw_call', 'patient_present', 'patient_reviewed', 'price_confirmed', 'expected_price'])], JSON_THROW_ON_ERROR));
            $existing = AppointmentReplacement::query()->where('request_id', $data['request_id'])->first();
            if ($existing !== null) {
                abort_unless($existing->clinic_id === $clinic->id && $existing->old_appointment_id === $original->id && hash_equals($existing->request_fingerprint, $fingerprint), 409, 'Identitas perubahan sudah digunakan untuk permintaan berbeda.');

                return $existing->replacement;
            }
            DoctorSchedule::query()->whereIn('id', [$original->doctor_schedule_id, $data['doctor_schedule_id']])->orderBy('id')->lockForUpdate()->get();
            $locked = Appointment::query()->where('clinic_id', $clinic->id)->lockForUpdate()->findOrFail($original->id);
            $queue = $locked->queue()->lockForUpdate()->firstOrFail();
            $locked->setRelation('queue', $queue);
            abort_unless(hash_equals($locked->replacementVersion(), $data['expected_version']), 409, 'Booking atau antrean sudah berubah. Tinjau ulang perubahan sebelum melanjutkan.');
            $this->assertReplaceable($locked);
            $this->assertContinuationReplacement($locked, $data);
            if ($queue->status === QueueStatus::Called && empty($data['withdraw_call'])) {
                throw ValidationException::withMessages(['withdraw_call' => 'Konfirmasikan penarikan panggilan aktif sebelum memindahkan booking.']);
            }
            if ($locked->service_id !== (int) $data['service_id'] && empty($data['price_confirmed'])) {
                throw ValidationException::withMessages(['price_confirmed' => 'Persetujuan pasien atas layanan dan tarif tujuan wajib dicatat.']);
            }
            $oldStatus = $queue->status;
            $locked->update(['status' => AppointmentStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Penggantian booking: '.$data['reason']]);
            $queue->update(['status' => QueueStatus::Cancelled, 'cancelled_at' => now()]);
            $result = $this->createBooking($clinic, [...$data, 'patient_id' => $locked->patient_id, 'idempotency_key' => $data['request_id']], $actor, 'RECEPTIONIST', $locked);
            $replacement = $result['appointment'];
            $handoff = VisitContinuation::query()->where('target_appointment_id', $locked->id)->lockForUpdate()->first();
            if ($handoff !== null) {
                $handoff->target_appointment_id = $replacement->id;
                $handoff->lock_version++;
                $handoff->save();
            }
            app(FollowUpService::class)->bookingReplaced($locked, $replacement, $actor);
            app(PatientBillingService::class)->appointmentReplaced($locked, $replacement);
            $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
            if (! empty($data['patient_present']) && $queue->checked_in_at !== null && $replacement->appointment_date->isSameDay($locked->appointment_date)
                && $replacement->appointment_date->isSameDay(CarbonImmutable::now($timezone))) {
                app(QueueWorkflowService::class)->checkIn($replacement->queue, $actor);
            }
            $link = AppointmentReplacement::query()->create(['clinic_id' => $clinic->id, 'old_appointment_id' => $locked->id, 'new_appointment_id' => $replacement->id,
                'actor_user_id' => $actor->id, 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint, 'reason' => $data['reason'], 'reason_source' => $data['reason_source'],
                'patient_present' => ! empty($data['patient_present']), 'price_confirmed' => ! empty($data['price_confirmed'])]);
            $this->auditLogger->recordForClinic($clinic, 'appointment.replaced', $link, [
                'old_appointment_id' => $locked->id, 'new_appointment_id' => $replacement->id,
                'check_in_preserved' => $replacement->queue->refresh()->checked_in_at !== null,
                'call_withdrawn' => $oldStatus === QueueStatus::Called,
                'patient_profile_reviewed' => ! empty($data['patient_reviewed']),
            ], $actor);
            $this->notificationService->appointmentStatusChanged($locked, AppointmentStatus::Cancelled);
            $this->notificationService->queueStatusChanged($queue, $oldStatus);

            return $this->loadBookingRelations($replacement->refresh());
        }, 3);
    }

    private function authorizeReplacement(Appointment $appointment, User $actor): void
    {
        abort_unless($actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->clinic_id === $appointment->clinic_id && $actor->can('update', $appointment) && $actor->can('queue.manage'), 403);
    }

    private function assertReplaceable(Appointment $appointment): void
    {
        if ($appointment->status !== AppointmentStatus::Booked || $appointment->visit()->exists()
            || ! in_array($appointment->queue?->status, [QueueStatus::Booked, QueueStatus::Waiting, QueueStatus::Called, QueueStatus::Skipped], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking ini tidak dapat dipindahkan. Pemeriksaan yang sudah dimulai memerlukan alur interupsi atau handoff.']);
        }
    }

    public function tokenMatches(Appointment $appointment, string $accessToken): bool
    {
        return hash_equals($appointment->access_token_hash, hash('sha256', $accessToken));
    }

    public function cancel(
        Appointment $appointment,
        ?User $actor = null,
        ?string $accessToken = null,
        ?string $reason = null,
    ): Appointment {
        if ($accessToken !== null && ! $this->tokenMatches($appointment, $accessToken)) {
            abort(404);
        }

        return DB::transaction(function () use ($appointment, $actor, $reason): Appointment {
            $clinic = Clinic::query()->lockForUpdate()->findOrFail($appointment->clinic_id);
            abort_unless($this->subscriptionService->allowsOperationalWrites($clinic), 403, 'Akses operasional klinik sedang hanya-baca.');
            $lockedAppointment = Appointment::query()->where('clinic_id', $clinic->id)->lockForUpdate()->findOrFail($appointment->id);

            if ($lockedAppointment->status !== AppointmentStatus::Booked) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking ini tidak dapat dibatalkan.',
                ]);
            }

            $queue = $lockedAppointment->queue()->where('clinic_id', $clinic->id)->lockForUpdate()->firstOrFail();
            if ($queue->status === QueueStatus::InProgress || $lockedAppointment->visit()->exists()) {
                throw ValidationException::withMessages([
                    'booking' => 'Pemeriksaan sudah dimulai. Booking tidak dapat dibatalkan.',
                ]);
            }
            if ($queue->status->isTerminal()) {
                throw ValidationException::withMessages(['booking' => 'Booking ini tidak dapat dibatalkan.']);
            }

            $timezone = $lockedAppointment->clinic->settings?->timezone ?? 'Asia/Jakarta';
            $sessionStartsAt = CarbonImmutable::parse(
                $lockedAppointment->appointment_date->toDateString().' '.$lockedAppointment->schedule->start_time,
                $timezone,
            );

            if ($sessionStartsAt->isPast()) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking tidak dapat dibatalkan setelah sesi dimulai.',
                ]);
            }

            $lockedAppointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $previousQueueStatus = $queue->status;
            $queue->update(['status' => QueueStatus::Cancelled, 'cancelled_at' => now()]);
            $this->releaseContinuationBooking($lockedAppointment, $actor);
            $this->auditLogger->recordForClinic(
                $lockedAppointment->clinic,
                'appointment.cancelled',
                $lockedAppointment,
                ['reason' => $reason],
                $actor,
            );
            $this->notificationService->appointmentStatusChanged($lockedAppointment, AppointmentStatus::Cancelled);
            $this->notificationService->queueStatusChanged($queue->refresh(), $previousQueueStatus);

            return $this->loadBookingRelations($lockedAppointment->refresh());
        }, 3);
    }

    public function releaseContinuationBooking(Appointment $appointment, ?User $actor): void
    {
        app(FollowUpService::class)->bookingReleased($appointment, $actor);
        app(PatientBillingService::class)->cancelUnstartedAppointment($appointment, $actor);
        $handoff = VisitContinuation::query()->where('clinic_id', $appointment->clinic_id)->where('target_appointment_id', $appointment->id)->lockForUpdate()->first();
        if ($handoff !== null && $handoff->status === 'BOOKED' && $handoff->to_visit_id === null) {
            $handoff->forceFill(['target_appointment_id' => null, 'status' => 'ACCEPTED', 'lock_version' => $handoff->lock_version + 1])->save();
            $handoff->source->interruptionTasks()->where('kind', 'CARE')->whereNotIn('status', ['RESOLVED', 'CANCELLED'])->update(['status' => 'WAITING_PATIENT']);
            $this->auditLogger->recordForClinic($appointment->clinic, 'care_continuation.booking_released', $handoff, ['appointment_id' => $appointment->id], $actor);
        }
    }

    public function protectedContinuation(Appointment $appointment): bool
    {
        return VisitContinuation::query()->where('clinic_id', $appointment->clinic_id)->where('target_appointment_id', $appointment->id)
            ->where('protected_service', true)->whereIn('status', ['BOOKED', 'IN_PROGRESS'])->exists();
    }

    /** @param array<string,mixed> $data */
    private function assertContinuationReplacement(Appointment $appointment, array $data): void
    {
        $handoff = VisitContinuation::query()->where('target_appointment_id', $appointment->id)->first();
        if ($handoff !== null) {
            abort_unless($handoff->clinic_id === $appointment->clinic_id && $handoff->status === 'BOOKED' && $handoff->to_visit_id === null
                && $handoff->receiving_doctor_id === (int) $data['doctor_id'] && $handoff->service_id === (int) $data['service_id'], 409,
                'Persetujuan kelanjutan berlaku untuk dokter dan layanan yang diterima. Batalkan permintaan dan rilis persetujuan baru untuk mengubahnya.');
        }
        $followUp = FollowUp::query()->where('active_appointment_id', $appointment->id)->first();
        if ($followUp !== null) {
            abort_unless($followUp->clinic_id === $appointment->clinic_id && $followUp->status === 'BOOKED' && $followUp->service_id === (int) $data['service_id']
                && (($handoff?->protected_service && $handoff->receiving_doctor_id === (int) $data['doctor_id']) || $followUp->responsible_doctor_id === (int) $data['doctor_id']), 409, 'Dokter/layanan kontrol harus sesuai instruksi yang disahkan.');
        }
    }

    /** @param array<string, mixed> $data */
    private function resolvePatient(Clinic $clinic, array $data, ?User $actor, bool $allowInactive = false): Patient
    {
        if (! empty($data['patient_id'])) {
            $patient = Patient::query()
                ->withoutGlobalScope(ClinicScope::class)
                ->whereKey($data['patient_id'])
                ->where('clinic_id', $clinic->id)
                ->lockForUpdate()
                ->first();

            if ($patient === null) {
                throw ValidationException::withMessages(['patient_id' => 'Pasien tidak ditemukan.']);
            }

            if (! $allowInactive) {
                $this->assertPatientActive($patient);
            }

            return $patient;
        }

        if ($actor?->hasRole('Patient')) {
            $patient = Patient::query()
                ->withoutGlobalScope(ClinicScope::class)
                ->where('clinic_id', $clinic->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if ($patient !== null) {
                $this->assertPatientActive($patient);
                $patient->fill(Arr::only($data, ['name', 'birth_date', 'gender', 'phone', 'email', 'address']));

                if ($patient->isDirty()) {
                    $patient->save();
                    $this->auditLogger->recordForClinic($clinic, 'patient.updated', $patient, [
                        'source' => 'PATIENT_BOOKING',
                    ], $actor);
                }

                return $patient;
            }
        }

        if ($actor !== null && ! $actor->hasRole('Patient')) {
            app(PatientDuplicateService::class)->assertReviewed($clinic->id, $data);
        }

        $patient = Patient::query()->create([
            ...Arr::only($data, ['name', 'nik', 'birth_date', 'gender', 'phone', 'email', 'address']),
            'clinic_id' => $clinic->id,
            'user_id' => $actor?->hasRole('Patient') ? $actor->id : null,
            'medical_record_number' => 'TEMP-'.Str::random(32),
            'is_active' => true,
        ]);
        $patient->update([
            'medical_record_number' => $this->medicalRecordNumberGenerator->generate($patient),
        ]);

        $this->auditLogger->recordForClinic($clinic, 'patient.created', $patient, [
            'source' => $actor === null ? 'PUBLIC_BOOKING' : 'STAFF_OR_ACCOUNT',
        ], $actor);

        return $patient;
    }

    private function loadBookingRelations(Appointment $appointment): Appointment
    {
        return $appointment->load([
            'patient' => fn (BelongsTo $query): BelongsTo => $query->withoutGlobalScope(ClinicScope::class)->where('patients.clinic_id', $appointment->clinic_id),
            'doctor.user', 'service', 'schedule', 'queue',
        ]);
    }

    private function assertPatientActive(Patient $patient): void
    {
        if (! $patient->is_active) {
            throw ValidationException::withMessages([
                'patient_id' => 'Profil pasien di klinik ini nonaktif. Hubungi staf klinik untuk meninjau dan mengaktifkan kembali profil sebelum membuat booking baru. Booking sebelumnya tetap berlaku.',
            ]);
        }
    }

    private function validateAppointmentDate(
        Clinic $clinic,
        DoctorSchedule $schedule,
        CarbonImmutable $appointmentDate,
    ): void {
        $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($appointmentDate->isBefore($today) || $appointmentDate->isAfter($today->addDays(30))) {
            throw ValidationException::withMessages([
                'appointment_date' => 'Tanggal kunjungan harus antara hari ini dan 30 hari ke depan.',
            ]);
        }

        if ($appointmentDate->dayOfWeek !== $schedule->day_of_week) {
            throw ValidationException::withMessages([
                'appointment_date' => 'Tanggal tidak sesuai dengan hari praktik dokter.',
            ]);
        }

        $settings = $clinic->settings;
        if ($settings !== null && (substr($schedule->start_time, 0, 5) < substr($settings->opening_time, 0, 5)
            || substr($schedule->end_time, 0, 5) > substr($settings->closing_time, 0, 5))) {
            throw ValidationException::withMessages([
                'doctor_schedule_id' => 'Sesi berada di luar jam operasional klinik saat ini. Pilih sesi lain atau hubungi klinik.',
            ]);
        }

        $sessionEndsAt = CarbonImmutable::parse(
            $appointmentDate->toDateString().' '.$schedule->end_time,
            $timezone,
        );

        if ($sessionEndsAt->isPast()) {
            throw ValidationException::withMessages([
                'appointment_date' => 'Sesi praktik ini sudah berakhir.',
            ]);
        }
    }

    private function accessToken(string $idempotencyKey): string
    {
        return hash_hmac('sha256', $idempotencyKey, (string) config('app.key'));
    }

    private function uniqueBookingCode(Clinic $clinic): string
    {
        do {
            $code = Str::upper(Str::substr($clinic->slug, 0, 3)).'-'.Str::upper(Str::random(8));
        } while (Appointment::query()->where('booking_code', $code)->exists());

        return $code;
    }
}
