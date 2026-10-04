<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Events\QueueUpdated;
use App\Models\Appointment;
use App\Models\Queue;
use App\Notifications\AppointmentStatusNotification;
use App\Notifications\QueueStatusNotification;
use App\QueueStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ClinicNotificationService
{
    public function appointmentStatusChanged(Appointment $appointment, AppointmentStatus $status): void
    {
        $appointmentId = $appointment->id;

        DB::afterCommit(function () use ($appointmentId, $status): void {
            $freshAppointment = Appointment::query()
                ->with(['clinic', 'patient.user', 'doctor.user'])
                ->find($appointmentId);

            if ($freshAppointment === null) {
                return;
            }

            $recipients = collect([
                $freshAppointment->patient->user,
                $freshAppointment->doctor->user,
            ])->filter()->unique('id')->values();

            Notification::send($recipients, new AppointmentStatusNotification(
                $freshAppointment->id,
                $freshAppointment->booking_code,
                $status,
                $freshAppointment->clinic->name,
            ));
        });
    }

    public function queueStatusChanged(Queue $queue, QueueStatus $previousStatus): void
    {
        QueueUpdated::dispatch($queue->clinic_id, [
            'queue_id' => $queue->id,
            'display_number' => $queue->display_number,
            'status' => $queue->status->value,
            'doctor_id' => $queue->doctor_id,
            'queue_date' => $queue->queue_date->toDateString(),
            'updated_at' => $queue->updated_at->toIso8601String(),
        ]);

        if ($queue->status === QueueStatus::Booked || $queue->status === $previousStatus) {
            return;
        }

        $queueId = $queue->id;
        $status = $queue->status;

        DB::afterCommit(function () use ($queueId, $status): void {
            $freshQueue = Queue::query()
                ->with(['appointment.patient.user', 'doctor.user'])
                ->find($queueId);

            if ($freshQueue === null) {
                return;
            }

            $recipients = collect([
                $freshQueue->appointment->patient->user,
                $freshQueue->doctor->user,
            ])->filter()->unique('id')->values();

            Notification::send($recipients, new QueueStatusNotification(
                $freshQueue->appointment_id,
                $freshQueue->id,
                $freshQueue->display_number,
                $status,
            ));
        });
    }
}
