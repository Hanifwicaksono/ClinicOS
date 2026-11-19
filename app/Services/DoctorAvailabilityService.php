<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorAbsence;
use App\Models\DoctorSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorAvailabilityService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function assertSessionAvailable(Clinic $clinic, DoctorSchedule $schedule, CarbonImmutable $date): void
    {
        [$start,$end] = $this->sessionInterval($schedule, $date, $clinic->settings?->timezone ?? 'Asia/Jakarta');
        if (DoctorAbsence::query()->where('clinic_id', $clinic->id)->where('doctor_id', $schedule->doctor_id)->whereNull('cancelled_at')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
            throw ValidationException::withMessages(['doctor_schedule_id' => 'Sesi dokter diblokir karena laporan berhalangan. Pilih sesi atau tanggal lain.']);
        }
    }

    /** @param Collection<int, Doctor> $doctors
     * @return array<int, list<string>>
     */
    public function blockedDates(Collection $doctors, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $absences = DoctorAbsence::query()->whereIn('doctor_id', $doctors->pluck('id')->all())->whereNull('cancelled_at')
            ->where('starts_at', '<', $today->addDays(31)->utc())->where('ends_at', '>', $today->utc())->get()->groupBy('doctor_id');
        $result = [];
        foreach ($doctors as $doctor) {
            foreach ($doctor->schedules as $schedule) {
                $result[$schedule->id] = [];
                foreach (range(0, 30) as $offset) {
                    $date = $today->addDays($offset);
                    if ($date->dayOfWeek !== (int) $schedule->day_of_week) {
                        continue;
                    }
                    [$start,$end] = $this->sessionInterval($schedule, $date, $timezone);
                    if (($absences[$doctor->id] ?? collect())->contains(fn (DoctorAbsence $absence): bool => $absence->starts_at->lessThan($end) && $absence->ends_at->greaterThan($start))) {
                        $result[$schedule->id][] = $date->toDateString();
                    }
                }
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $data */
    public function report(User $actor, array $data): DoctorAbsence
    {
        return DB::transaction(function () use ($actor, $data): DoctorAbsence {
            $clinic = Clinic::query()->with('settings')->lockForUpdate()->findOrFail($actor->clinic_id);
            $doctor = Doctor::query()->where('clinic_id', $clinic->id)->findOrFail($data['doctor_id']);
            abort_unless($this->canManage($actor) || ($actor->hasRole('Doctor') && $doctor->user_id === $actor->id), 403);
            $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
            $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], $timezone);
            $end = empty($data['whole_day']) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $timezone) : $start->startOfDay()->addDay();
            if (! empty($data['whole_day'])) {
                $start = $start->startOfDay();
            }
            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(['ends_at' => 'Akhir berhalangan harus setelah waktu mulai.']);
            }
            $fingerprint = hash('sha256', json_encode([$doctor->id, $actor->id, $start->toIso8601String(), $end->toIso8601String(), $data['reason']], JSON_THROW_ON_ERROR));
            $existing = DoctorAbsence::query()->where('request_id', $data['request_id'])->first();
            if ($existing !== null) {
                abort_unless($existing->clinic_id === $clinic->id && hash_equals($existing->request_fingerprint, $fingerprint), 409, 'Identitas laporan sudah dipakai untuk isi berbeda.');

                return $existing;
            }
            $absence = DoctorAbsence::query()->create(['clinic_id' => $clinic->id, 'doctor_id' => $doctor->id, 'starts_at' => $start->utc(), 'ends_at' => $end->utc(),
                'timezone' => $timezone, 'reason' => $data['reason'], 'reported_by_user_id' => $actor->id, 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
            $this->auditLogger->recordForClinic($clinic, 'doctor_absence.reported', $absence, ['doctor_id' => $doctor->id], $actor);

            return $absence;
        }, 3);
    }

    public function authorizeView(User $actor, DoctorAbsence $absence): void
    {
        abort_unless($actor->clinic_id === $absence->clinic_id &&
            (($actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('appointment.view')) || ($actor->hasRole('Doctor') && $absence->doctor->user_id === $actor->id)), 403);
    }

    public function canManage(User $actor): bool
    {
        return $actor->hasRole('Clinic Admin') && $actor->can('schedule.manage');
    }

    public function change(User $actor, DoctorAbsence $absence, string $action, int $expectedVersion, string $reason): DoctorAbsence
    {
        return DB::transaction(function () use ($actor, $absence, $action, $expectedVersion, $reason): DoctorAbsence {
            $clinic = Clinic::query()->lockForUpdate()->findOrFail($absence->clinic_id);
            $locked = DoctorAbsence::query()->lockForUpdate()->findOrFail($absence->id);
            $this->authorizeView($actor, $locked);
            abort_unless($action === 'request-cancel' ? ($actor->hasRole('Doctor') && $locked->doctor->user_id === $actor->id) : $this->canManage($actor), 403);
            $column = match ($action) {
                'verify' => 'verified_at','request-cancel' => 'cancel_requested_at','reopen' => 'cancelled_at',default => abort(404)
            };
            if ($locked->$column !== null) {
                return $locked;
            }
            abort_unless($locked->cancelled_at === null && $locked->lock_version === $expectedVersion, 409, 'Laporan sudah berubah. Muat ulang sebelum melanjutkan.');
            $locked->$column = now();
            $locked->lock_version++;
            if ($action === 'verify') {
                $locked->verified_by_user_id = $actor->id;
            }
            if ($action === 'request-cancel') {
                $locked->cancel_request_reason = $reason;
            }
            if ($action === 'reopen') {
                $locked->cancelled_by_user_id = $actor->id;
                $locked->cancellation_reason = $reason;
            }
            $locked->save();
            $this->auditLogger->recordForClinic($clinic, 'doctor_absence.'.$action, $locked, ['doctor_id' => $locked->doctor_id], $actor);

            return $locked;
        }, 3);
    }

    public function affected(DoctorAbsence $absence): Collection
    {
        $start = $absence->starts_at->timezone($absence->timezone);
        $end = $absence->ends_at->timezone($absence->timezone);

        return Appointment::query()->where('clinic_id', $absence->clinic_id)->where('doctor_id', $absence->doctor_id)
            ->whereDate('appointment_date', '>=', $start->toDateString())->whereDate('appointment_date', '<=', $end->toDateString())
            ->with(['patient', 'doctor.user', 'schedule', 'queue', 'visit', 'replacedBy.replacement'])->orderBy('appointment_date')->get()
            ->filter(function (Appointment $appointment) use ($absence): bool {
                [$start,$end] = $this->sessionInterval($appointment->schedule, CarbonImmutable::parse($appointment->appointment_date->toDateString(), $absence->timezone), $absence->timezone);

                return $absence->starts_at->lessThan($end) && $absence->ends_at->greaterThan($start);
            })->values();
    }

    public function contact(User $actor, DoctorAbsence $absence, Appointment $appointment, string $note, string $requestId): void
    {
        DB::transaction(function () use ($actor, $absence, $appointment, $note, $requestId): void {
            $clinic = Clinic::query()->lockForUpdate()->findOrFail($absence->clinic_id);
            $this->authorizeView($actor, $absence);
            abort_unless($actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('appointment.update'), 403);
            if ($absence->verified_at === null) {
                throw ValidationException::withMessages(['absence' => 'Sahkan laporan sebelum memulai komunikasi penanganan pasien terdampak.']);
            }
            abort_unless($appointment->clinic_id === $absence->clinic_id && $this->affected($absence)->contains('id', $appointment->id), 404);
            $existing = DB::table('doctor_absence_contacts')->where('request_id', $requestId)->first();
            if ($existing !== null) {
                abort_unless($existing->doctor_absence_id === $absence->id && $existing->appointment_id === $appointment->id && $existing->actor_user_id === $actor->id && $existing->note === $note, 409);

                return;
            }
            $id = DB::table('doctor_absence_contacts')->insertGetId(['doctor_absence_id' => $absence->id, 'appointment_id' => $appointment->id, 'actor_user_id' => $actor->id, 'request_id' => $requestId, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
            $this->auditLogger->recordForClinic($clinic, 'doctor_absence.contacted', $absence, ['contact_id' => $id, 'appointment_id' => $appointment->id], $actor);
        }, 3);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function sessionInterval(DoctorSchedule $schedule, CarbonImmutable $date, string $timezone): array
    {
        return [CarbonImmutable::parse($date->toDateString().' '.$schedule->start_time, $timezone)->utc(), CarbonImmutable::parse($date->toDateString().' '.$schedule->end_time, $timezone)->utc()];
    }
}
