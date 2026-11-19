<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Events\QueueUpdated;
use App\Jobs\BroadcastClinicQueueUpdate;
use App\Jobs\DeliverCareNotification;
use App\Jobs\DeliverClinicNotification;
use App\Models\Appointment;
use App\Models\CareNotificationDelivery;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Scopes\ClinicScope;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\AppointmentStatusNotification;
use App\Notifications\CareWorkflowNotification;
use App\Notifications\QueueStatusNotification;
use App\QueueStatus;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue as QueueDispatcher;
use Illuminate\Support\Str;
use LogicException;

class ClinicNotificationService
{
    public function appointmentStatusChanged(Appointment $appointment, AppointmentStatus $status): void
    {
        DB::transaction(function () use ($appointment, $status): void {
            $this->notify($appointment, new AppointmentStatusNotification(
                $appointment->id,
                $appointment->booking_code,
                $status,
                $appointment->clinic->name,
            ));
        });
    }

    public function queueStatusChanged(Queue $queue, QueueStatus $previousStatus): void
    {
        DB::transaction(function () use ($queue, $previousStatus): void {
            $appointment = $queue->appointment()
                ->where('clinic_id', $queue->clinic_id)
                ->where('doctor_id', $queue->doctor_id)
                ->firstOrFail();

            $event = new QueueUpdated($queue->clinic_id, [
                'queue_id' => $queue->id,
                'display_number' => $queue->display_number,
                'status' => $queue->status->value,
                'doctor_id' => $queue->doctor_id,
                'queue_date' => $queue->queue_date->toDateString(),
                'updated_at' => $queue->updated_at->toIso8601String(),
            ]);
            $this->databaseQueue()->push((new BroadcastClinicQueueUpdate($event))->beforeCommit());

            if ($queue->status === QueueStatus::Booked || $queue->status === $previousStatus) {
                return;
            }

            $this->notify($appointment, new QueueStatusNotification(
                $appointment->id,
                $queue->id,
                $queue->display_number,
                $queue->status,
            ));
        });
    }

    public function careWorkflow(Visit $visit, User $recipient, string $kind, int $generation): void
    {
        DB::transaction(function () use ($visit, $recipient, $kind, $generation): void {
            $delivery = new CareNotificationDelivery([
                'clinic_id' => $visit->clinic_id, 'visit_id' => $visit->id,
                'recipient_user_id' => $recipient->id, 'kind' => $kind, 'generation' => $generation,
                'notification_id' => (string) Str::uuid(),
            ]);
            $delivery->setRelation('visit', $visit);
            if (! $delivery->canDeliver($recipient) || CareNotificationDelivery::query()
                ->where('visit_id', $visit->id)->where('recipient_user_id', $recipient->id)->where('kind', $kind)->where('generation', $generation)->exists()) {
                return;
            }
            $delivery->save();
            $notification = new CareWorkflowNotification($visit->id, $kind);
            $notification->id = $delivery->notification_id;
            Notification::sendNow($recipient, $notification, ['database']);
            $delivery->database_sent_at = now();
            $delivery->save();
            $this->databaseQueue()->push((new DeliverCareNotification($delivery->id))->beforeCommit());
        });
    }

    private function notify(Appointment $appointment, AppointmentStatusNotification|QueueStatusNotification $notification): void
    {
        $queue = $this->databaseQueue();

        foreach ($this->recipients($appointment) as $recipient) {
            $delivery = clone $notification;
            $delivery->id = Str::uuid()->toString();
            Notification::sendNow($recipient, $delivery, ['database']);
            $queue->push((new DeliverClinicNotification($appointment->clinic_id, $recipient->id, $delivery))->beforeCommit());
        }
    }

    /** @return Collection<int, User> */
    private function recipients(Appointment $appointment): Collection
    {
        $patient = Patient::query()
            ->withoutGlobalScope(ClinicScope::class)
            ->where('clinic_id', $appointment->clinic_id)
            ->with('user')
            ->findOrFail($appointment->patient_id);
        $doctor = Doctor::query()
            ->where('clinic_id', $appointment->clinic_id)
            ->with('user')
            ->findOrFail($appointment->doctor_id);

        return collect([$patient->user, $doctor->user])->filter()->unique('id')->values();
    }

    private function databaseQueue(): DatabaseQueue
    {
        $queue = QueueDispatcher::connection('database');

        if (! $queue instanceof DatabaseQueue || $queue->getDatabase() !== DB::connection()) {
            throw new LogicException('Clinic delivery requires the database queue on the business database connection.');
        }

        return $queue;
    }
}
