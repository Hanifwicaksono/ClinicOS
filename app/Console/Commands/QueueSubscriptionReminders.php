<?php

namespace App\Console\Commands;

use App\Jobs\SendSubscriptionReminder;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('subscriptions:queue-reminders')]
#[Description('Queue subscription expiry reminders for clinic owners')]
class QueueSubscriptionReminders extends Command
{
    private const MILESTONES = [7, 3, 1, 0];

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $queued = 0;

        SubscriptionPeriod::query()
            ->with(['clinic.settings', 'clinic.owner'])
            ->whereBetween('ends_at', [$now->subDay()->utc(), $now->addDays(8)->utc()])
            ->each(function (SubscriptionPeriod $period) use ($now, &$queued): void {
                $timezone = $period->clinic->settings?->timezone ?? config('app.timezone', 'UTC');
                $today = $now->setTimezone($timezone)->startOfDay();
                $dueOn = $period->ends_at->setTimezone($timezone)->startOfDay();
                $daysRemaining = (int) $today->diffInDays($dueOn, false);

                if (! in_array($daysRemaining, self::MILESTONES, true) || $this->hasRenewal($period)) {
                    return;
                }

                $reminder = SubscriptionReminder::query()->firstOrCreate(
                    [
                        'subscription_period_id' => $period->id,
                        'milestone_days' => $daysRemaining,
                    ],
                    [
                        'clinic_id' => $period->clinic_id,
                        'owner_user_id' => $period->clinic->owner_user_id,
                        'due_on' => $dueOn->toDateString(),
                        'notification_id' => (string) Str::uuid(),
                    ],
                );

                if ($reminder->sent_at === null && $reminder->failed_at === null) {
                    SendSubscriptionReminder::dispatch($reminder->id);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} subscription reminder(s).");

        return self::SUCCESS;
    }

    private function hasRenewal(SubscriptionPeriod $period): bool
    {
        return $period->clinic->subscriptionPeriods()
            ->whereKeyNot($period->id)
            ->where('starts_at', '<=', $period->ends_at)
            ->where('ends_at', '>', $period->ends_at)
            ->exists();
    }
}
