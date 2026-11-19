<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Jobs\DeliverPatientReminder;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\PatientReminderDelivery;
use App\Models\Queue;
use App\Models\User;
use App\Models\VisitResumeReservation;
use App\Notifications\PatientReminderNotification;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue as Dispatcher;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientReminderService
{
    public function nearTurn(Queue $queue): bool
    {
        if ($queue->clinic_id !== $queue->appointment->clinic_id || $queue->doctor_id !== $queue->appointment->doctor_id || $queue->doctor_schedule_id !== $queue->appointment->doctor_schedule_id || $queue->status !== QueueStatus::Waiting || $queue->checked_in_at === null || $queue->appointment->visit()->exists() || ! $this->validAppointment($queue->appointment)) {
            return false;
        }
        $today = CarbonImmutable::now($queue->clinic->settings?->timezone ?? 'Asia/Jakarta')->toDateString();
        if ($queue->queue_date->toDateString() !== $today || CarbonImmutable::parse($today.' '.$queue->schedule->end_time, $queue->clinic->settings?->timezone ?? 'Asia/Jakarta')->isPast()) {
            return false;
        }
        $ahead = Queue::query()->where('clinic_id', $queue->clinic_id)->where('doctor_id', $queue->doctor_id)->where('doctor_schedule_id', $queue->doctor_schedule_id)->whereDate('queue_date', $today)->whereKeyNot($queue->id)
            ->where(fn ($q) => $q->where(fn ($waiting) => $waiting->where('status', QueueStatus::Waiting)->where('queue_number', '<', $queue->queue_number))->orWhereIn('status', [QueueStatus::Called, QueueStatus::InProgress])->orWhere(fn ($paused) => $paused->where('status', QueueStatus::Paused)->where('pause_holds_slot', true)))->count();
        $ahead += VisitResumeReservation::query()->where('clinic_id', $queue->clinic_id)->where('doctor_schedule_id', $queue->doctor_schedule_id)->whereDate('resume_date', $today)->where('state', 'READY')->whereHas('visit', fn ($v) => $v->where('status', VisitStatus::Paused)->whereColumn('visits.resume_reservation_id', 'visit_resume_reservations.id')->whereDoesntHave('queue', fn ($held) => $held->where('clinic_id', $queue->clinic_id)->where('doctor_schedule_id', $queue->doctor_schedule_id)->whereDate('queue_date', $today)->where('status', QueueStatus::Paused)->where('pause_holds_slot', true)))->count();

        return $ahead <= config('patient-reminders.near_turn_threshold');
    }

    private function validAppointment(Appointment $appointment): bool
    {
        if (! in_array($appointment->status, [AppointmentStatus::Booked], true) || ! $appointment->clinic->is_active || ! $appointment->doctor->is_active || ! $appointment->doctor->user->is_active || ! $appointment->doctor->user->hasVerifiedEmail() || ! $appointment->doctor->user->hasRole('Doctor') || ! $appointment->schedule->is_active || $appointment->schedule->doctor_id !== $appointment->doctor_id || $appointment->doctor->clinic_id !== $appointment->clinic_id) {
            return false;
        }
        try {
            app(DoctorAvailabilityService::class)->assertSessionAvailable($appointment->clinic, $appointment->schedule, CarbonImmutable::parse($appointment->appointment_date->toDateString(), $appointment->clinic->settings?->timezone ?? 'Asia/Jakarta'));
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    public function queueDue(): void
    {
        Queue::query()->whereDate('queue_date', '>=', now()->subDay()->toDateString())->whereDate('queue_date', '<=', now()->addDay()->toDateString())->where('status', QueueStatus::Waiting)->whereNotNull('checked_in_at')->eachById(function (Queue $queue): void {
            DB::transaction(function () use ($queue): void {
                Clinic::query()->lockForUpdate()->findOrFail($queue->clinic_id);
                $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);
                if (! $this->nearTurn($queue)) {
                    return;
                }
                if ($queue->near_turn_return_pending) {
                    $last = PatientReminderDelivery::query()->where('target_type', 'queue')->where('target_id', $queue->id)->whereNotNull('sent_at')->latest('sent_at')->first();
                    if ($last !== null && $last->sent_at->addMinutes(config('patient-reminders.near_turn_cooldown_minutes'))->isFuture()) {
                        return;
                    }
                    if ($last !== null) {
                        $queue->near_turn_generation++;
                    }
                    $queue->near_turn_return_pending = false;
                    $queue->save();
                }
                $this->store('queue', $queue->id, $queue->clinic_id, 'near_turn', (int) $queue->near_turn_generation, $this->patientUser($queue->appointment->patient_id, $queue->clinic_id));
            }, 3);
        });
        Appointment::query()->whereDate('appointment_date', '>=', now()->toDateString())->whereDate('appointment_date', '<=', now()->addDays(2)->toDateString())->whereIn('status', [AppointmentStatus::Booked])->eachById(function (Appointment $appointment): void {
            DB::transaction(function () use ($appointment): void {
                Clinic::query()->lockForUpdate()->findOrFail($appointment->clinic_id);
                $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
                if ($this->h1($appointment)) {
                    $this->store('appointment', $appointment->id, $appointment->clinic_id, 'appointment', 1, $this->patientUser($appointment->patient_id, $appointment->clinic_id));
                }
            }, 3);
        });
        FollowUp::query()->whereIn('status', ['OPEN', 'BOOKED'])->eachById(function (FollowUp $task): void {
            DB::transaction(function () use ($task): void {
                Clinic::query()->lockForUpdate()->findOrFail($task->clinic_id);
                $task = FollowUp::query()->lockForUpdate()->findOrFail($task->id);
                if ($task->isOverdue()) {
                    foreach (User::query()->where('clinic_id', $task->clinic_id)->where('is_active', true)->role(['Clinic Admin', 'Receptionist'])->get() as $staff) {
                        $this->store('follow_up', $task->id, $task->clinic_id, 'follow_up_overdue', (int) $task->reminder_generation, $staff);
                    }
                } elseif ($this->followUpDue($task)) {
                    $patient = $this->patientUser($task->patient_id, $task->clinic_id);
                    if ($patient !== null) {
                        $this->store('follow_up', $task->id, $task->clinic_id, 'follow_up', (int) $task->reminder_generation, $patient);
                    } else {
                        foreach (User::query()->where('clinic_id', $task->clinic_id)->where('is_active', true)->role(['Clinic Admin', 'Receptionist'])->get() as $staff) {
                            $this->store('follow_up', $task->id, $task->clinic_id, 'follow_up_contact', (int) $task->reminder_generation, $staff);
                        }
                    }
                }
            }, 3);
        });
    }

    private function patientUser(int $patientId, int $clinicId): ?User
    {
        return Patient::withoutGlobalScopes()->where('clinic_id', $clinicId)->find($patientId)?->user;
    }

    private function h1(Appointment $appointment): bool
    {
        if ($appointment->visit()->exists() || ! $this->validAppointment($appointment)) {
            return false;
        }
        $timezone = $appointment->clinic->settings?->timezone ?? 'Asia/Jakarta';
        $now = CarbonImmutable::now($timezone);
        $due = CarbonImmutable::parse($appointment->appointment_date->toDateString().' '.config('patient-reminders.appointment_h1_time'), $timezone)->subDay();

        return $now->isSameDay($due) && $now->greaterThanOrEqualTo($due) && $appointment->created_at->lessThanOrEqualTo($due);
    }

    private function followUpDue(FollowUp $task): bool
    {
        $cutoff = $task->range_ends_at->setTimezone($task->input_timezone)->startOfDay();
        $today = CarbonImmutable::now($task->input_timezone)->startOfDay();

        return $task->status === 'OPEN' && $task->active_appointment_id === null && $today->greaterThanOrEqualTo($cutoff->subDay()->subDays(config('patient-reminders.follow_up_lead_days'))) && $today->lessThan($cutoff);
    }

    private function store(string $type, int $id, int $clinicId, string $category, int $generation, ?User $recipient): void
    {
        if ($recipient === null) {
            return;
        }
        $delivery = PatientReminderDelivery::query()->firstOrNew(['target_type' => $type, 'target_id' => $id, 'recipient_user_id' => $recipient->id, 'category' => $category, 'generation' => $generation]);
        if (! $delivery->exists) {
            $delivery->forceFill(['clinic_id' => $clinicId, 'notification_id' => (string) Str::uuid()]);
        }
        if ($this->context($delivery) === null || ($delivery->sent_at !== null && $delivery->broadcast_sent_at !== null) || ($delivery->queued_at !== null && $delivery->queued_at->addMinutes(5)->isFuture())) {
            return;
        }
        $delivery->state = 'PENDING';
        $delivery->queued_at = now();
        $delivery->save();
        $queue = Dispatcher::connection('database');
        if (! $queue instanceof DatabaseQueue || $queue->getDatabase() !== DB::connection()) {
            throw new \LogicException('Patient reminders require the database queue on the business database connection.');
        }
        $queue->push((new DeliverPatientReminder($delivery->id))->beforeCommit());
    }

    /** @return array{user:User,payload:array<string,mixed>}|null */
    public function context(PatientReminderDelivery $delivery): ?array
    {
        $user = User::query()->find($delivery->recipient_user_id);
        $clinic = Clinic::query()->find($delivery->clinic_id);
        if ($user === null || ! $user->is_active || ! $user->hasVerifiedEmail() || $clinic === null || ! $clinic->is_active || ($user->clinic_id !== null && $user->clinic_id !== $delivery->clinic_id)) {
            return null;
        }
        $staff = in_array($delivery->category, ['follow_up_overdue', 'follow_up_contact'], true);
        if ($staff && ($user->clinic_id !== $delivery->clinic_id || ! $user->hasAnyRole(['Clinic Admin', 'Receptionist']) || ! $user->can('appointment.update'))) {
            return null;
        }
        if (! $staff && (! $user->hasRole('Patient') || (($user->reminder_preferences ?? [])[$delivery->category] ?? true) === false)) {
            return null;
        }
        if ($delivery->target_type === 'follow_up') {
            $task = FollowUp::query()->where('clinic_id', $delivery->clinic_id)->find($delivery->target_id);
            if ($task === null || (int) $task->reminder_generation !== $delivery->generation || ! in_array($task->status, ['OPEN', 'BOOKED'], true)) {
                return null;
            }
            if (! $staff && $this->patientUser($task->patient_id, $task->clinic_id)?->id !== $user->id) {
                return null;
            }
            if ($delivery->category === 'follow_up_overdue' ? ! $task->isOverdue() : ! $this->followUpDue($task)) {
                return null;
            }
            if ($delivery->category === 'follow_up_contact' && $this->patientUser($task->patient_id, $task->clinic_id) !== null) {
                return null;
            }
            $title = $delivery->category === 'follow_up_overdue' ? 'Kontrol melewati batas waktu' : 'Pengingat rencana kontrol';
            $message = $staff ? 'Tinjau daftar kontrol dan tindak lanjut komunikasi pasien.' : 'Anda belum memiliki booking kontrol. Periksa rencana kontrol yang telah disampaikan dokter.';
            $url = route('follow-ups.show', $task, absolute: false);
        } else {
            $queue = $delivery->target_type === 'queue' ? Queue::query()->where('clinic_id', $delivery->clinic_id)->find($delivery->target_id) : null;
            $appointment = $queue?->appointment ?? ($delivery->target_type === 'appointment' ? Appointment::query()->where('clinic_id', $delivery->clinic_id)->find($delivery->target_id) : null);
            if ($appointment === null || $this->patientUser($appointment->patient_id, $appointment->clinic_id)?->id !== $user->id) {
                return null;
            }
            if ($delivery->category === 'near_turn' ? ($queue === null || (int) $queue->near_turn_generation !== $delivery->generation || $queue->near_turn_return_pending || ! $this->nearTurn($queue)) : ! $this->h1($appointment)) {
                return null;
            }
            $title = $delivery->category === 'near_turn' ? 'Antrean Anda mendekat' : 'Pengingat jadwal besok';
            $message = $delivery->category === 'near_turn' ? 'Bersiap untuk antrean '.$queue->display_number.' di '.$clinic->name.'. Perkiraan, bisa berubah. Ini bukan panggilan pemeriksaan.' : 'Periksa jadwal terbaru Anda di '.$clinic->name.' untuk '.$appointment->appointment_date->format('d M Y').'.';
            $url = route('patient.appointments.show', $appointment, absolute: false);
        }

        return ['user' => $user, 'payload' => ['title' => $title, 'message' => $message, 'url' => $url, 'status' => 'REMINDER', 'reminder_delivery_id' => $delivery->id]];
    }

    public function deliver(int $id): void
    {
        $candidate = PatientReminderDelivery::query()->findOrFail($id);
        DB::transaction(function () use ($candidate): void {
            Clinic::query()->lockForUpdate()->findOrFail($candidate->clinic_id);
            $delivery = PatientReminderDelivery::query()->lockForUpdate()->findOrFail($candidate->id);
            $context = $this->context($delivery);
            if ($context === null) {
                $delivery->state = 'SKIPPED';
                $delivery->save();

                return;
            }
            $notification = new PatientReminderNotification($context['payload']);
            $notification->id = $delivery->notification_id;
            if ($delivery->sent_at === null) {
                Notification::sendNow($context['user'], $notification, ['database']);
                $delivery->sent_at = now();
                $delivery->state = 'SENT';
                $delivery->save();
            }
        }, 3);
        DB::transaction(function () use ($candidate): void {
            Clinic::query()->lockForUpdate()->findOrFail($candidate->clinic_id);
            $delivery = PatientReminderDelivery::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($delivery->sent_at === null || $delivery->broadcast_sent_at !== null || ($context = $this->context($delivery)) === null) {
                return;
            }
            $notification = new PatientReminderNotification($context['payload']);
            $notification->id = $delivery->notification_id;
            // A transport may accept a message then time out; retries keep the same notification ID for client deduplication.
            (new BroadcastEvent(new BroadcastNotificationCreated($context['user'], $notification, $context['payload'])))->handle(app(BroadcastingFactory::class));
            $delivery->broadcast_sent_at = now();
            $delivery->save();
        }, 3);
    }
}
