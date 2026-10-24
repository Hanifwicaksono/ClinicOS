<?php

namespace App\Jobs;

use App\Events\QueueUpdated;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastClinicQueueUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public QueueUpdated $event) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(BroadcastingFactory $broadcasting): void
    {
        (new BroadcastEvent($this->event))->handle($broadcasting);
    }
}
