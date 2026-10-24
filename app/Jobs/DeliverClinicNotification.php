<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Scopes\ClinicScope;
use App\Models\User;
use App\Notifications\AppointmentStatusNotification;
use App\Notifications\QueueStatusNotification;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use LogicException;

class DeliverClinicNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $clinicId,
        public int $recipientId,
        public AppointmentStatusNotification|QueueStatusNotification $notification,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(BroadcastingFactory $broadcasting): void
    {
        $recipient = User::query()->findOrFail($this->recipientId);
        $stored = $recipient->notifications()
            ->where('type', $this->notification::class)
            ->findOrFail($this->notification->id);
        $appointment = Appointment::query()
            ->where('clinic_id', $this->clinicId)
            ->findOrFail($this->notification->appointmentId);

        $isPatient = Patient::query()->withoutGlobalScope(ClinicScope::class)
            ->where('clinic_id', $this->clinicId)
            ->where('user_id', $recipient->id)
            ->whereKey($appointment->patient_id)
            ->exists();
        $isDoctor = Doctor::query()
            ->where('clinic_id', $this->clinicId)
            ->where('user_id', $recipient->id)
            ->whereKey($appointment->doctor_id)
            ->exists();

        if (! $isPatient && ! $isDoctor) {
            throw new LogicException('Clinic notification recipient no longer belongs to the target resource.');
        }

        (new BroadcastEvent(new BroadcastNotificationCreated($recipient, $this->notification, $stored->data)))
            ->handle($broadcasting);
    }
}
