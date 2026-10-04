<?php

namespace App\Notifications;

use App\Models\User;
use App\QueueStatus;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class QueueStatusNotification extends Notification
{
    public function __construct(
        public int $appointmentId,
        public int $queueId,
        public string $displayNumber,
        public QueueStatus $status,
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
        return 'queue.status';
    }

    /** @return array{title: string, message: string, status: string, queue_id: int, appointment_id: int, display_number: string, url: string} */
    private function payload(object $notifiable): array
    {
        $title = match ($this->status) {
            QueueStatus::Waiting => 'Pasien sudah check-in',
            QueueStatus::Called => 'Nomor antrean dipanggil',
            QueueStatus::InProgress => 'Pemeriksaan dimulai',
            QueueStatus::Completed => 'Kunjungan selesai',
            QueueStatus::Cancelled => 'Antrean dibatalkan',
            QueueStatus::Skipped => 'Panggilan dilewati',
            QueueStatus::NoShow => 'Pasien ditandai tidak hadir',
            default => 'Status antrean berubah',
        };

        return [
            'title' => $title,
            'message' => "Antrean {$this->displayNumber} sekarang berstatus {$this->status->label()}.",
            'status' => $this->status->value,
            'queue_id' => $this->queueId,
            'appointment_id' => $this->appointmentId,
            'display_number' => $this->displayNumber,
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

        return route('receptionist.queues.index', absolute: false);
    }
}
