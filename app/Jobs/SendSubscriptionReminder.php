<?php

namespace App\Jobs;

use App\Models\SubscriptionReminder;
use App\Notifications\SubscriptionReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class SendSubscriptionReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $reminderId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('subscription-reminder-'.$this->reminderId))->dontRelease()->expireAfter(300)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reminder = SubscriptionReminder::query()
            ->with(['clinic.owner', 'period'])
            ->findOrFail($this->reminderId);

        if ($reminder->sent_at !== null) {
            return;
        }

        $owner = $reminder->clinic->owner;
        if ($owner === null || $owner->clinic_id !== $reminder->clinic_id) {
            throw new RuntimeException('Subscription reminder owner is missing.');
        }

        if ($reminder->owner_user_id !== $owner->id) {
            $reminder->update(['owner_user_id' => $owner->id]);
        }

        $notification = new SubscriptionReminderNotification($reminder);
        $now = now();

        if ($reminder->database_notified_at === null) {
            DB::table('notifications')->insertOrIgnore([
                'id' => $reminder->notification_id,
                'type' => SubscriptionReminderNotification::class,
                'notifiable_type' => $owner->getMorphClass(),
                'notifiable_id' => $owner->id,
                'data' => json_encode($notification->toArray($owner), JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $reminder->update(['database_notified_at' => $now]);
        }

        if ($reminder->mailed_at === null) {
            Notification::sendNow($owner, $notification, ['mail']);
            $reminder->update(['mailed_at' => now()]);
        }

        $reminder->update(['sent_at' => now(), 'failed_at' => null, 'error_type' => null]);
    }

    public function failed(Throwable $exception): void
    {
        SubscriptionReminder::query()->whereKey($this->reminderId)->update([
            'failed_at' => now(),
            'error_type' => $exception::class,
        ]);
    }
}
