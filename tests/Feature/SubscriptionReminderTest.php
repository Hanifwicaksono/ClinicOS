<?php

namespace Tests\Feature;

use App\Jobs\SendSubscriptionReminder;
use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReminder;
use App\Models\User;
use App\Notifications\SubscriptionReminderNotification;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SubscriptionReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_scheduler_queues_each_timezone_milestone_once_and_job_notifies_only_owner(): void
    {
        CarbonImmutable::setTestNow('2026-10-16 17:30:00 UTC');
        Queue::fake();
        Notification::fake();
        [$owner, $period] = $this->periodEndingAt('2026-10-23 17:00:00 UTC');
        $otherUser = User::factory()->forClinic($period->clinic)->create();

        $this->artisan('subscriptions:queue-reminders')->assertSuccessful();
        $this->artisan('subscriptions:queue-reminders')->assertSuccessful();

        $reminder = SubscriptionReminder::query()->sole();
        $this->assertSame(7, $reminder->milestone_days);
        $this->assertSame('2026-10-24', $reminder->due_on->toDateString());
        $this->assertSame($owner->id, $reminder->owner_user_id);
        Queue::assertPushed(SendSubscriptionReminder::class);

        $job = new SendSubscriptionReminder($reminder->id);
        $job->handle();
        $job->handle();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($reminder->refresh()->sent_at);
        Notification::assertSentToTimes($owner, SubscriptionReminderNotification::class, 1);
        Notification::assertNotSentTo($otherUser, SubscriptionReminderNotification::class);

        Artisan::call('schedule:list');
        $this->assertStringContainsString('subscriptions:queue-reminders', Artisan::output());
    }

    public function test_job_has_bounded_retries_and_records_failure_without_error_details(): void
    {
        [, $period] = $this->periodEndingAt(now()->addDays(7)->utc());
        $period->clinic->update(['owner_user_id' => null]);
        $reminder = SubscriptionReminder::query()->create([
            'clinic_id' => $period->clinic_id,
            'subscription_period_id' => $period->id,
            'owner_user_id' => null,
            'milestone_days' => 7,
            'due_on' => now()->addDays(7)->toDateString(),
            'notification_id' => fake()->uuid(),
        ]);
        $job = new SendSubscriptionReminder($reminder->id);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff());

        try {
            $job->handle();
            $this->fail('Job should fail without an owner.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        $this->assertSame(RuntimeException::class, $reminder->refresh()->error_type);
        $this->assertNotNull($reminder->failed_at);
        $this->assertDatabaseCount('notifications', 0);
    }

    /** @return array{User, SubscriptionPeriod} */
    private function periodEndingAt(string|CarbonImmutable $endsAt): array
    {
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();
        ClinicSetting::factory()->for($clinic)->create(['timezone' => 'Asia/Jakarta']);
        $plan = SubscriptionPlan::query()->where('code', 'team')->firstOrFail();
        $subscription = Subscription::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'source' => 'payment',
        ]);
        $period = SubscriptionPeriod::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'plan_name' => $plan->name,
            'source' => 'payment',
            'starts_at' => CarbonImmutable::parse($endsAt)->subMonth(),
            'ends_at' => CarbonImmutable::parse($endsAt),
        ]);

        return [$owner, $period];
    }
}
