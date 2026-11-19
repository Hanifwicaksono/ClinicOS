<?php

namespace App\Jobs;

use App\Models\CareNotificationDelivery;
use App\Notifications\CareWorkflowNotification;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;

class DeliverCareNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(BroadcastingFactory $broadcasting): void
    {
        $delivery = CareNotificationDelivery::query()->with(['visit.doctor', 'recipient'])->findOrFail($this->deliveryId);
        if ($delivery->broadcast_dispatched_at !== null || ! $delivery->canDeliver($delivery->recipient)) {
            return;
        }
        $stored = $delivery->recipient->notifications()->where('type', CareWorkflowNotification::class)->findOrFail($delivery->notification_id);
        $notification = new CareWorkflowNotification($delivery->visit_id, $delivery->kind);
        $notification->id = $delivery->notification_id;
        (new BroadcastEvent(new BroadcastNotificationCreated($delivery->recipient, $notification, $stored->data)))->handle($broadcasting);
        $delivery->broadcast_dispatched_at = now();
        $delivery->save();
    }
}
