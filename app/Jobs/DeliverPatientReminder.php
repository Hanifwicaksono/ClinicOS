<?php

namespace App\Jobs;

use App\Services\PatientReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverPatientReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(PatientReminderService $reminders): void
    {
        $reminders->deliver($this->deliveryId);
    }
}
