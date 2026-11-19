<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\FollowUp;
use App\Models\FollowUpEvent;
use App\Models\IssuedPrescription;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitContinuation;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FollowUpService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string,mixed> $data */
    public function create(Visit $visit, User $actor, array $data): FollowUp
    {
        return DB::transaction(function () use ($visit, $actor, $data): FollowUp {
            Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
            $source = Visit::query()->where('clinic_id', $visit->clinic_id)->lockForUpdate()->findOrFail($visit->id);
            $actor = $actor->fresh();
            abort_unless($actor?->can('issue', [IssuedPrescription::class, $source]), 403);
            $fingerprint = hash('sha256', json_encode([$source->id, $actor->id, $data], JSON_THROW_ON_ERROR));
            $existing = FollowUp::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->clinic_id === $source->clinic_id && hash_equals($existing->request_fingerprint, $fingerprint), 409);

                return $existing;
            }
            abort_unless($source->patient->clinic_id === $source->clinic_id, 409);
            $service = Service::query()->where('clinic_id', $source->clinic_id)->whereHas('doctors', fn ($doctors) => $doctors->whereKey($source->doctor_id))->findOrFail($data['service_id']);
            [$start,$end,$timezone] = $this->range($source->clinic, $data);
            $task = FollowUp::query()->create(['clinic_id' => $source->clinic_id, 'source_visit_id' => $source->id, 'patient_id' => $source->patient_id, 'responsible_doctor_id' => $source->doctor_id,
                'service_id' => $service->id, 'created_by_user_id' => $actor->id, 'instructions' => $data['instructions'], 'range_starts_at' => $start, 'range_ends_at' => $end, 'input_timezone' => $timezone,
                'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
            $this->event($task, $actor, 'created', $data, ['instructions' => $task->instructions, 'range_from' => $data['range_from'], 'range_until' => $data['range_until'], 'responsible_doctor_id' => $source->doctor_id]);

            return $task;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function change(FollowUp $task, User $actor, string $action, array $data): FollowUp
    {
        return DB::transaction(function () use ($task, $actor, $action, $data): FollowUp {
            $task = $this->locked($task);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            abort_unless($actor->is_active && ($actor->clinic_id === null || $actor->clinic_id === $task->clinic_id), 403);
            $patient = $actor->hasRole('Patient') && $actor->hasVerifiedEmail() && $task->patient->user_id === $actor->id;
            $receiver = $actor->is_active && $actor->clinic_id === $task->clinic_id && $actor->hasRole('Doctor') && $actor->can('medical_record.update') && $task->proposedDoctor?->is_active && $task->proposedDoctor?->user_id === $actor->id;
            $clinical = $actor->can('clinical', $task);
            $staff = $actor->can('schedule', $task);
            abort_unless(match ($action) {
                'transfer-accept','transfer-decline' => $receiver, 'contact' => $staff, 'decline' => $patient || $staff || $clinical, default => $clinical
            }, 403);
            if ($this->replay($task, $actor, $action, $data) !== null) {
                return $task;
            }
            abort_unless((int) $data['expected_version'] === $task->lock_version, 409, 'Tugas kontrol berubah. Tinjau ulang sebelum menyimpan.');
            if (in_array($task->status, ['COMPLETED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages(['follow_up' => 'Tugas kontrol sudah ditutup.']);
            }
            $details = [];
            if ($action === 'revise') {
                [$start,$end,$timezone] = $this->range($task->clinic, $data);
                $details = ['before' => ['instructions' => $task->instructions, 'range_starts_at' => $task->range_starts_at, 'range_ends_at' => $task->range_ends_at]];
                $task->instructions = $data['instructions'];
                $task->range_starts_at = $start;
                $task->range_ends_at = $end;
                $task->reminder_generation++;
                $task->input_timezone = $timezone;
                $task->review_required = false;
                $details['after'] = ['instructions' => $task->instructions, 'range_starts_at' => $start, 'range_ends_at' => $end];
            } elseif ($action === 'transfer-request') {
                $doctor = Doctor::query()->where('clinic_id', $task->clinic_id)->where('is_active', true)->whereKeyNot($task->responsible_doctor_id)
                    ->whereHas('user', fn ($query) => $query->where('is_active', true)->whereNotNull('email_verified_at')->whereHas('roles', fn ($roles) => $roles->where('name', 'Doctor')))
                    ->whereHas('services', fn ($query) => $query->whereKey($task->service_id))->findOrFail($data['doctor_id']);
                $task->proposed_doctor_id = $doctor->id;
                $task->transfer_requested_at = now();
                $details = ['proposed_doctor_id' => $doctor->id];
            } elseif ($action === 'transfer-accept') {
                $details = ['previous_doctor_id' => $task->responsible_doctor_id, 'receiving_doctor_id' => $task->proposed_doctor_id];
                $task->responsible_doctor_id = $task->proposed_doctor_id;
                $task->proposed_doctor_id = null;
                $task->transfer_requested_at = null;
                if ($task->active_appointment_id !== null) {
                    $task->review_required = true;
                }
            } elseif ($action === 'transfer-decline') {
                $details = ['declined_doctor_id' => $task->proposed_doctor_id];
                $task->proposed_doctor_id = null;
                $task->transfer_requested_at = null;
                $task->review_required = true;
            } elseif ($action === 'decline') {
                if (blank($data['patient_decision'] ?? null)) {
                    throw ValidationException::withMessages(['patient_decision' => 'Catat keputusan atau penolakan pasien.']);
                }
                $task->patient_decision = $data['patient_decision'];
                $task->review_required = true;
                $details = ['patient_decision' => $data['patient_decision']];
            } elseif ($action === 'cancel') {
                if (empty($data['clinical_closure_confirmed']) || blank($data['risk_explanation'] ?? null) || blank($data['closure_assessment'] ?? null) || blank($task->patient_decision)) {
                    throw ValidationException::withMessages(['clinical_closure_confirmed' => 'Penutupan perlu keputusan pasien, penjelasan risiko, dan penilaian klinis yang disahkan dokter.']);
                }
                if ($task->activeAppointment !== null && $task->activeAppointment->visit()->exists()) {
                    throw ValidationException::withMessages(['follow_up' => 'Episode kontrol sudah dimulai. Tangani melalui kelanjutan/interupsi sebelum menutup tugas.']);
                }
                if ($task->activeAppointment !== null && ! $task->activeAppointment->queue->status->isTerminal()) {
                    app(QueueWorkflowService::class)->cancel($task->activeAppointment->queue, $actor, 'Kontrol dibatalkan setelah review dokter.');
                    $task->refresh();
                }
                $task->status = 'CANCELLED';
                $task->cancelled_at = now();
                $task->closed_by_user_id = $actor->id;
                $task->review_required = false;
                $task->risk_explanation = $data['risk_explanation'];
                $task->closure_assessment = $data['closure_assessment'];
                $details = ['risk_explanation' => $data['risk_explanation'], 'closure_assessment' => $data['closure_assessment']];
            } elseif ($action === 'complete') {
                $visit = Visit::query()->where('clinic_id', $task->clinic_id)->where('patient_id', $task->patient_id)->whereHas('appointment', fn ($query) => $query->whereKey($task->active_appointment_id))->findOrFail($data['completed_visit_id']);
                if ($visit->status !== VisitStatus::Completed || empty($data['follow_up_goal_confirmed'])) {
                    throw ValidationException::withMessages(['follow_up' => 'Tugas hanya selesai setelah Visit kontrol tertaut selesai dan tujuan kontrol dikonfirmasi dokter.']);
                }
                $task->status = 'COMPLETED';
                $task->completed_visit_id = $visit->id;
                $task->completed_at = now();
                $task->closed_by_user_id = $actor->id;
                $task->review_required = false;
                $details = ['completed_visit_id' => $visit->id, 'goal_confirmed' => true];
            } elseif ($action !== 'contact') {
                abort(404);
            }
            $task->lock_version++;
            $task->save();
            $this->event($task, $actor, $action, $data, $details);

            return $task;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function book(FollowUp $task, User $actor, array $data): Appointment
    {
        return DB::transaction(function () use ($task, $actor, $data): Appointment {
            $task = $this->locked($task);
            $actor = $actor->fresh();
            abort_unless($actor !== null, 403);
            abort_unless($actor->can('schedule', $task), 403);
            if (($event = $this->replay($task, $actor, 'book', $data)) !== null) {
                return Appointment::query()->where('clinic_id', $task->clinic_id)->findOrFail($event->details['appointment_id']);
            }
            abort_unless((int) $data['expected_version'] === $task->lock_version, 409, 'Tugas sudah berubah. Tinjau booking terbaru.');
            if ($task->status !== 'OPEN' || $task->active_appointment_id !== null) {
                throw ValidationException::withMessages(['follow_up' => 'Tugas ini sudah mempunyai booking aktif atau ditutup.']);
            }
            $date = CarbonImmutable::parse($data['appointment_date'], $task->input_timezone)->startOfDay()->utc();
            if (($date->lessThan($task->range_starts_at) || $date->greaterThanOrEqualTo($task->range_ends_at)) && empty($data['outside_range_acknowledged'])) {
                throw ValidationException::withMessages(['outside_range_acknowledged' => 'Jadwal di luar rentang anjuran. Konfirmasikan review bersama pasien/dokter; instruksi medis tidak diubah.']);
            }
            $appointment = app(BookingService::class)->book($task->clinic, [...$data, 'patient_id' => $task->patient_id, 'doctor_id' => $task->responsible_doctor_id, 'service_id' => $task->service_id, 'idempotency_key' => $data['request_id']], $actor, 'RECEPTIONIST')['appointment'];
            $task->active_appointment_id = $appointment->id;
            $task->status = 'BOOKED';
            $task->review_required = false;
            $task->lock_version++;
            $task->save();
            $task->bookings()->attach($appointment);
            $this->event($task, $actor, 'book', $data, ['appointment_id' => $appointment->id]);

            return $appointment;
        }, 3);
    }

    public function bookingReleased(Appointment $appointment, ?User $actor): void
    {
        $task = FollowUp::query()->where('clinic_id', $appointment->clinic_id)->where('active_appointment_id', $appointment->id)->where('status', 'BOOKED')->lockForUpdate()->first();
        if ($task !== null) {
            $task->status = 'OPEN';
            $task->active_appointment_id = null;
            $task->review_required = true;
            $task->lock_version++;
            $task->save();
            $this->event($task, $actor, 'booking.released', ['request_id' => (string) Str::uuid()], ['appointment_id' => $appointment->id]);
        }
    }

    public function bookingReplaced(Appointment $old, Appointment $replacement, User $actor): void
    {
        $task = FollowUp::query()->where('clinic_id', $old->clinic_id)->where('active_appointment_id', $old->id)->lockForUpdate()->first();
        if ($task !== null) {
            $this->link($task, $replacement, $actor, 'booking.replaced');
        }
    }

    public function continuationBooked(VisitContinuation $handoff, Appointment $appointment, User $actor): void
    {
        if (! $handoff->protected_service) {
            return;
        }
        foreach (FollowUp::query()->where('clinic_id', $handoff->clinic_id)->where('patient_id', $handoff->source->patient_id)->where('service_id', $handoff->service_id)
            ->where('active_appointment_id', $handoff->source->appointment_id)->where('status', 'BOOKED')->lockForUpdate()->get() as $task) {
            $this->link($task, $appointment, $actor, 'booking.continued');
            $task->review_required = true;
            $task->save();
        }
    }

    public function assertStart(Appointment $appointment): void
    {
        $task = FollowUp::query()->where('active_appointment_id', $appointment->id)->first();
        if ($task !== null) {
            $continuation = VisitContinuation::query()->where('target_appointment_id', $appointment->id)->where('protected_service', true)->where('status', 'BOOKED')->exists();
            abort_unless($task->clinic_id === $appointment->clinic_id && $task->patient_id === $appointment->patient_id && $task->service_id === $appointment->service_id && $task->status === 'BOOKED'
                && ($continuation || $task->responsible_doctor_id === $appointment->doctor_id), 409, 'Penanggung jawab atau tautan kontrol berubah. Koordinasikan jadwal sebelum pemeriksaan.');
        }
    }

    /** @param array<string,mixed> $data */
    public function visitCompleted(Visit $visit, User $actor, array $data): void
    {
        foreach (FollowUp::query()->where('clinic_id', $visit->clinic_id)->where('patient_id', $visit->patient_id)->where('active_appointment_id', $visit->appointment_id)->where('status', 'BOOKED')->lockForUpdate()->get() as $task) {
            $continued = $visit->continuedFrom()->exists();
            if ($continued && empty($data['follow_up_goal_confirmed'])) {
                $task->review_required = true;
                $task->lock_version++;
                $task->save();

                continue;
            }
            $task->status = 'COMPLETED';
            $task->completed_visit_id = $visit->id;
            $task->completed_at = now();
            $task->closed_by_user_id = $actor->id;
            $task->review_required = false;
            $task->lock_version++;
            $task->save();
            $this->event($task, $actor, 'visit.completed', ['request_id' => (string) Str::uuid()], ['completed_visit_id' => $visit->id, 'continuation_goal_confirmed' => $continued]);
        }
    }

    private function link(FollowUp $task, Appointment $appointment, User $actor, string $action): void
    {
        abort_unless($task->clinic_id === $appointment->clinic_id && $task->patient_id === $appointment->patient_id && $task->service_id === $appointment->service_id, 409);
        $previous = $task->active_appointment_id;
        $task->active_appointment_id = $appointment->id;
        $task->status = 'BOOKED';
        $task->lock_version++;
        $task->save();
        $task->bookings()->syncWithoutDetaching([$appointment->id]);
        $this->event($task, $actor, $action, ['request_id' => (string) Str::uuid()], ['old_appointment_id' => $previous, 'appointment_id' => $appointment->id]);
    }

    private function locked(FollowUp $task): FollowUp
    {
        Clinic::query()->lockForUpdate()->findOrFail($task->clinic_id);

        return FollowUp::query()->where('clinic_id', $task->clinic_id)->lockForUpdate()->findOrFail($task->id);
    }

    /** @param array<string,mixed> $data
     * @return array{CarbonImmutable,CarbonImmutable,string} */
    private function range(Clinic $clinic, array $data): array
    {
        $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
        $start = CarbonImmutable::parse($data['range_from'], $timezone)->startOfDay();
        $end = CarbonImmutable::parse($data['range_until'], $timezone)->addDay()->startOfDay();
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['range_until' => 'Batas akhir tidak boleh sebelum awal rentang.']);
        }

        return [$start->utc(), $end->utc(), $timezone];
    }

    /** @param array<string,mixed> $data */
    private function replay(FollowUp $task, User $actor, string $action, array $data): ?FollowUpEvent
    {
        $event = FollowUpEvent::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
        if ($event !== null) {
            abort_unless($event->follow_up_id === $task->id && $event->actor_user_id === $actor->id && hash_equals($event->request_fingerprint, hash('sha256', json_encode([$task->id, $actor->id, $action, $data], JSON_THROW_ON_ERROR))), 409, 'Identitas aksi sudah digunakan untuk perubahan berbeda.');
        }

        return $event;
    }

    /** @param array<string,mixed> $data
     * @param array<string,mixed> $details */
    private function event(FollowUp $task, ?User $actor, string $action, array $data, array $details): void
    {
        FollowUpEvent::query()->create(['clinic_id' => $task->clinic_id, 'follow_up_id' => $task->id, 'actor_user_id' => $actor?->id, 'action' => $action, 'details' => $details, 'reason' => $data['reason'] ?? null,
            'request_id' => $data['request_id'], 'request_fingerprint' => hash('sha256', json_encode([$task->id, $actor?->id, $action, $data], JSON_THROW_ON_ERROR))]);
        $this->auditLogger->recordForClinic($task->clinic, 'follow_up.'.$action, $task, ['status' => $task->status, 'active_appointment_id' => $task->active_appointment_id], $actor);
    }
}
