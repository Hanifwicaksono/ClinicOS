<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CareWorkflowNotification extends Notification
{
    public function __construct(public int $visitId, public string $kind) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => match ($this->kind) {
            'pause_overdue' => 'Estimasi jeda pemeriksaan terlewati', 'pause_escalated' => 'Kasus memerlukan koordinasi klinis',
            'pause_unassigned' => 'Penanggung jawab klinis kasus perlu ditinjau', 'lead_assigned' => 'Penunjukan klinis khusus kasus',
            'handoff_requested' => 'Permintaan kelanjutan pelayanan', default => 'Koordinasi pelayanan diperbarui',
        }, 'message' => 'Periksa status terbaru pada daftar kelanjutan pelayanan.', 'url' => route('care-continuity.index', absolute: false), 'visit_id' => $this->visitId, 'status' => 'CARE_UPDATED'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'care.workflow';
    }
}
