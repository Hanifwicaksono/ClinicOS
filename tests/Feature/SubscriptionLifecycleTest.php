<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\PaymentStatus;
use App\Services\SubscriptionService;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\SubscriptionPlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_catalog_seeder_creates_the_agreed_plans_and_prices_idempotently(): void
    {
        $team = SubscriptionPlan::query()->where('code', 'team')->firstOrFail();
        $team->update(['is_active' => false]);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->assertDatabaseCount('subscription_plans', 3);
        $this->assertDatabaseCount('subscription_prices', 6);
        $this->assertDatabaseHas('subscription_plans', [
            'code' => 'free',
            'doctor_limit' => 1,
            'staff_limit' => 1,
            'monthly_visit_limit' => 50,
        ]);
        $this->assertDatabaseHas('subscription_prices', [
            'subscription_plan_id' => $team->id,
            'duration_months' => 12,
            'amount' => 4_999_000,
        ]);
        $this->assertFalse($team->refresh()->is_active);
    }

    public function test_team_trial_can_only_be_used_once_and_transitions_to_free(): void
    {
        $clinic = $this->clinicInJakarta();
        $service = app(SubscriptionService::class);
        $startsAt = CarbonImmutable::parse('2026-10-15 10:00:00', 'Asia/Jakarta')->utc();

        $subscription = $service->startTrial($clinic, $startsAt);

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame('team', $subscription->plan->code);
        $this->assertSame(SubscriptionStatus::Trialing, $service->statusAt($clinic, $subscription->ends_at->subSecond()));
        $this->assertSame(SubscriptionStatus::Free, $service->statusAt($clinic, $subscription->ends_at));

        $subscription = $service->refreshStatus($clinic, $subscription->ends_at);

        $this->assertSame(SubscriptionStatus::Free, $subscription->status);
        $this->assertSame('free', $subscription->plan->code);
        $this->assertDatabaseCount('subscription_periods', 1);

        $this->expectException(DomainException::class);
        $service->startTrial($clinic, $subscription->starts_at->addDay());
    }

    public function test_paid_renewal_stacks_periods_and_keeps_price_snapshots(): void
    {
        $clinic = $this->clinicInJakarta();
        $service = app(SubscriptionService::class);
        $price = SubscriptionPrice::query()
            ->whereRelation('plan', 'code', 'individual')
            ->where('duration_months', 1)
            ->firstOrFail();
        $startsAt = CarbonImmutable::parse('2026-10-15 10:00:00', 'Asia/Jakarta')->utc();
        $firstPayment = $this->paidPayment($clinic, $price, 'INV-FIRST');
        $firstPeriod = $service->activatePaid($clinic, $price, $firstPayment, $startsAt);
        $secondPayment = $this->paidPayment($clinic, $price, 'INV-SECOND');
        $secondPeriod = $service->activatePaid($clinic, $price, $secondPayment, $startsAt->addDays(5));

        $this->assertTrue($secondPeriod->starts_at->equalTo($firstPeriod->ends_at));
        $this->assertSame(149_000, $firstPeriod->amount);
        $this->assertSame($firstPeriod->id, $service->activatePaid($clinic, $price, $firstPayment, $startsAt)->id);
        $this->assertDatabaseCount('subscription_periods', 2);

        $price->update(['amount' => 199_000]);

        $this->assertSame(149_000, $firstPeriod->refresh()->amount);
        $this->assertSame(149_000, $firstPayment->refresh()->amount);
        $this->assertSame(SubscriptionStatus::Active, $service->statusAt($clinic, $secondPeriod->ends_at->subSecond()));
        $this->assertSame(SubscriptionStatus::Expired, $service->statusAt($clinic, $secondPeriod->ends_at));
        $this->assertSame(SubscriptionStatus::Expired, $service->refreshStatus($clinic, $secondPeriod->ends_at)->status);
    }

    public function test_paid_activation_rejects_a_mismatched_snapshot(): void
    {
        $clinic = $this->clinicInJakarta();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'team')->firstOrFail();
        $payment = $this->paidPayment($clinic, $price, 'INV-WRONG', ['amount' => $price->amount + 1]);

        $this->expectException(DomainException::class);
        app(SubscriptionService::class)->activatePaid($clinic, $price, $payment);
    }

    public function test_late_renewal_starts_when_payment_succeeds(): void
    {
        $clinic = $this->clinicInJakarta();
        $service = app(SubscriptionService::class);
        $price = SubscriptionPrice::query()
            ->whereRelation('plan', 'code', 'individual')
            ->where('duration_months', 1)
            ->firstOrFail();
        $firstPayment = $this->paidPayment($clinic, $price, 'INV-EXPIRED');
        $firstPeriod = $service->activatePaid(
            $clinic,
            $price,
            $firstPayment,
            CarbonImmutable::parse('2026-08-01 10:00:00', 'Asia/Jakarta')->utc(),
        );
        $renewedAt = $firstPeriod->ends_at->addDays(3);
        $renewalPayment = $this->paidPayment($clinic, $price, 'INV-LATE');

        $renewalPeriod = $service->activatePaid($clinic, $price, $renewalPayment, $renewedAt);

        $this->assertTrue($renewalPeriod->starts_at->equalTo($renewedAt));
        $this->assertSame(SubscriptionStatus::Expired, $service->statusAt($clinic, $renewedAt->subSecond()));
        $this->assertSame(SubscriptionStatus::Active, $service->statusAt($clinic, $renewedAt));
    }

    private function clinicInJakarta(): Clinic
    {
        $clinic = Clinic::factory()->create();
        ClinicSetting::factory()->for($clinic)->create(['timezone' => 'Asia/Jakarta']);

        return $clinic;
    }

    /** @param array<string, mixed> $overrides */
    private function paidPayment(
        Clinic $clinic,
        SubscriptionPrice $price,
        string $invoiceNumber,
        array $overrides = [],
    ): Payment {
        $price->loadMissing('plan');

        return Payment::factory()->create($overrides + [
            'clinic_id' => $clinic->id,
            'subscription_price_id' => $price->id,
            'invoice_number' => $invoiceNumber,
            'plan_code' => $price->plan->code,
            'plan_name' => $price->plan->name,
            'duration_months' => $price->duration_months,
            'amount' => $price->amount,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
