<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\QueueStatus;
use Carbon\CarbonImmutable;
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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{appointment: Appointment, access_token: string, created: bool}
     */
    public function book(Clinic $clinic, array $data, ?User $actor = null, string $source = 'ONLINE'): array
    {
        return DB::transaction(function () use ($clinic, $data, $actor, $source): array {
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
                if ($existingAppointment->clinic_id !== $clinic->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Permintaan booking tidak valid.',
                    ]);
                }

                return [
                    'appointment' => $existingAppointment->load(['patient', 'doctor.user', 'service', 'schedule', 'queue']),
                    'access_token' => $accessToken,
                    'created' => false,
                ];
            }

            $appointmentDate = CarbonImmutable::parse($data['appointment_date'], $clinic->settings?->timezone ?? 'Asia/Jakarta')->startOfDay();
            $this->validateAppointmentDate($clinic, $schedule, $appointmentDate);

            $service = Service::query()
                ->whereKey($data['service_id'])
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->whereHas('doctors', fn ($query) => $query->whereKey($schedule->doctor_id))
                ->first();

            if ($service === null) {
                throw ValidationException::withMessages([
                    'service_id' => 'Layanan tidak tersedia untuk dokter yang dipilih.',
                ]);
            }

            $reserved = Appointment::query()
                ->where('doctor_schedule_id', $schedule->id)
                ->whereDate('appointment_date', $appointmentDate)
                ->where('status', '!=', AppointmentStatus::Cancelled->value)
                ->count();

            if ($reserved >= $schedule->quota) {
                throw ValidationException::withMessages([
                    'doctor_schedule_id' => 'Kuota sesi ini sudah penuh.',
                ]);
            }

            $patient = $this->resolvePatient($clinic, $data, $actor);
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
                'service_price' => $service->price,
                'notes' => $data['notes'] ?? null,
            ]);

            $queueNumber = ((int) DB::table('queues')
                ->where('clinic_id', $clinic->id)
                ->where('doctor_id', $schedule->doctor_id)
                ->where('doctor_schedule_id', $schedule->id)
                ->whereDate('queue_date', $appointmentDate)
                ->max('queue_number')) + 1;
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
                'appointment' => $appointment->load(['patient', 'doctor.user', 'service', 'schedule', 'queue']),
                'access_token' => $accessToken,
                'created' => true,
            ];
        }, 3);
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
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($lockedAppointment->status !== AppointmentStatus::Booked) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking ini tidak dapat dibatalkan.',
                ]);
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
            $queue = $lockedAppointment->queue()->lockForUpdate()->firstOrFail();
            $previousQueueStatus = $queue->status;
            $queue->update(['status' => QueueStatus::Cancelled, 'cancelled_at' => now()]);
            $this->auditLogger->recordForClinic(
                $lockedAppointment->clinic,
                'appointment.cancelled',
                $lockedAppointment,
                ['reason' => $reason],
                $actor,
            );
            $this->notificationService->appointmentStatusChanged($lockedAppointment, AppointmentStatus::Cancelled);
            $this->notificationService->queueStatusChanged($queue->refresh(), $previousQueueStatus);

            return $lockedAppointment->refresh()->load(['patient', 'doctor.user', 'service', 'schedule', 'queue']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function resolvePatient(Clinic $clinic, array $data, ?User $actor): Patient
    {
        if (! empty($data['patient_id'])) {
            $patient = Patient::query()
                ->whereKey($data['patient_id'])
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->first();

            if ($patient === null) {
                throw ValidationException::withMessages(['patient_id' => 'Pasien tidak ditemukan.']);
            }

            return $patient;
        }

        if ($actor?->hasRole('Patient')) {
            $patient = Patient::query()
                ->where('clinic_id', $clinic->id)
                ->where('user_id', $actor->id)
                ->first();

            if ($patient !== null) {
                return $patient;
            }
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
