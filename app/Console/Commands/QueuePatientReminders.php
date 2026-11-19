<?php

namespace App\Console\Commands;

use App\Services\PatientReminderService;
use Illuminate\Console\Command;

class QueuePatientReminders extends Command
{
    protected $signature = 'patients:queue-reminders';

    protected $description = 'Queue current, deduplicated patient reminders';

    public function handle(PatientReminderService $reminders): int
    {
        $reminders->queueDue();
        $this->info('Patient reminder eligibility checked.');

        return self::SUCCESS;
    }
}
