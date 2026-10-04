<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array{queue_id: int, display_number: string, status: string, doctor_id: int, queue_date: string, updated_at: string} $payload */
    public function __construct(public int $clinicId, public array $payload) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("clinic.{$this->clinicId}.queues"),
            new Channel("clinic.{$this->clinicId}.queue-board"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    /** @return array<string, int|string> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
