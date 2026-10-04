<?php

namespace App\Notifications;

use App\AppointmentStatus;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AppointmentStatusNotification extends Notification
{
    public function __construct(
        public int $appointmentId,
        public string $bookingCode,
        public AppointmentStatus $status,
        public string $clinicName,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload($notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload($notifiable));
    }

    public function broadcastType(): string
    {
        return 'appointment.status';
    }

    /** @return array{title: string, message: string, status: string, appointment_id: int, booking_code: string, url: string} */
    private function payload(object $notifiable): array
    {
        $isCancelled = $this->status === AppointmentStatus::Cancelled;

        return [
            'title' => $isCancelled ? 'Booking dibatalkan' : 'Booking berhasil dibuat',
            'message' => $isCancelled
                ? "Booking {$this->bookingCode} di {$this->clinicName} telah dibatalkan."
                : "Booking {$this->bookingCode} di {$this->clinicName} telah dikonfirmasi.",
            'status' => $this->status->value,
            'appointment_id' => $this->appointmentId,
            'booking_code' => $this->bookingCode,
            'url' => $this->url($notifiable),
        ];
    }

    private function url(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->hasRole('Patient')) {
            return route('patient.appointments.show', $this->appointmentId, false);
        }

        if ($notifiable instanceof User && $notifiable->hasRole('Doctor')) {
            return route('doctor.queues.index', absolute: false);
        }

        return route('receptionist.appointments.show', $this->appointmentId, false);
    }
}
