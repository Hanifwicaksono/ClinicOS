<?php

namespace Tests\Feature;

use App\Models\AddonPeriod;
use App\Models\Clinic;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\PaymentStatus;
use App\Services\AddonService;
use App\Services\DokuPaymentService;
use App\Services\SubscriptionService;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    public function test_platform_admin_can_manage_catalog_and_grant_audited_access(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $clinic = Clinic::factory()->create(['name' => 'Klinik Langganan']);
        User::factory()->clinicOwner($clinic)->create();
        $team = SubscriptionPlan::query()->where('code', 'team')->firstOrFail();
        $price = $team->prices()->where('duration_months', 12)->firstOrFail();

        $this->actingAs($platformAdmin)->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Rp 4.999.000');
        $this->patch(route('platform.subscription-plans.update', $team), ['is_active' => false])
            ->assertRedirect();
        $this->assertFalse($team->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => null,
            'user_id' => $platformAdmin->id,
            'action' => 'subscription_plan.updated',
        ]);

        $this->patch(route('platform.subscription-plans.update', $team), ['is_active' => true])->assertRedirect();
        $this->patch(route('platform.subscription-prices.update', $price), ['amount' => 5_100_000, 'is_active' => false])->assertRedirect();
        $this->assertFalse($price->refresh()->is_active);
        $this->assertSame(5_100_000, $price->amount);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => null,
            'user_id' => $platformAdmin->id,
            'action' => 'subscription_price.updated',
        ]);
        $this->seed(SubscriptionPlanSeeder::class);
        $this->assertSame(5_100_000, $price->refresh()->amount);
        $this->patch(route('platform.subscription-prices.update', $price), ['amount' => 5_100_000, 'is_active' => true])->assertRedirect();
        $this->post(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $price->id,
            'reason' => 'Kompensasi migrasi pelanggan lama',
        ])->assertRedirect();

        $this->assertDatabaseHas('subscription_periods', [
            'clinic_id' => $clinic->id,
            'subscription_plan_id' => $team->id,
            'granted_by_user_id' => $platformAdmin->id,
            'duration_months' => 12,
            'amount' => 5_100_000,
            'source' => 'manual_grant',
            'reason' => 'Kompensasi migrasi pelanggan lama',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $platformAdmin->id,
            'action' => 'subscription.granted',
        ]);
        $this->get(route('platform.tenants.show', $clinic))
            ->assertOk()
            ->assertSee('Team')
            ->assertSee('Active');
    }

    public function test_grant_requires_a_reason_and_an_active_catalog_entry(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $clinic = Clinic::factory()->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'individual')->firstOrFail();

        $this->actingAs($platformAdmin)->post(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $price->id,
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $price->update(['is_active' => false]);
        $this->post(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $price->id,
            'reason' => 'Tidak boleh dipakai',
        ])->assertSessionHasErrors('subscription_price_id');

        $this->assertDatabaseCount('subscription_periods', 0);
    }

    public function test_tenant_owner_cannot_manage_platform_catalog_or_grants(): void
    {
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();
        $plan = SubscriptionPlan::query()->where('code', 'team')->firstOrFail();
        $price = $plan->prices()->firstOrFail();

        $this->actingAs($owner)->get(route('platform.subscription-plans.index'))->assertForbidden();
        $this->patch(route('platform.subscription-plans.update', $plan), ['is_active' => false])->assertForbidden();
        $this->patch(route('platform.subscription-prices.update', $price), ['amount' => 1, 'is_active' => true])->assertForbidden();
        $this->post(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $price->id,
            'reason' => 'Tidak sah',
        ])->assertForbidden();
    }

    public function test_price_edit_rejects_invalid_amount_without_changing_catalog(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'individual')->firstOrFail();
        $oldAmount = $price->amount;

        $this->actingAs($admin)->patch(route('platform.subscription-prices.update', $price), [
            'amount' => 0,
            'is_active' => true,
        ])->assertSessionHasErrors('amount');

        $this->assertSame($oldAmount, $price->refresh()->amount);
    }

    public function test_grant_downgrade_to_individual_applies_now_and_cancels_queued_access(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $clinic = Clinic::factory()->create();
        $teamPrice = SubscriptionPrice::query()->whereRelation('plan', 'code', 'team')->where('duration_months', 12)->firstOrFail();
        $individualPrice = SubscriptionPrice::query()->whereRelation('plan', 'code', 'individual')->where('duration_months', 1)->firstOrFail();
        $service = app(SubscriptionService::class);
        $activeTeam = $service->grant($clinic, $teamPrice, $admin, 'Akses awal untuk klinik.');
        $queuedTeam = $service->grant($clinic, $teamPrice, $admin, 'Periode berikutnya.');
        $pending = Payment::factory()->create(['clinic_id' => $clinic->id]);
        for ($i = 0; $i < 2; $i++) {
            User::factory()->forClinic($clinic)->create()->assignRole('Doctor');
        }

        $this->actingAs($admin)->post(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $individualPrice->id,
            'reason' => 'Klinik meminta paket Individual sekarang.',
        ])->assertRedirect()->assertSessionHas('status', 'Grant subscription berlaku sekarang.');

        $this->assertNotNull($activeTeam->refresh()->revoked_at);
        $this->assertNotNull($queuedTeam->refresh()->revoked_at);
        $this->assertSame(PaymentStatus::Expired, $pending->refresh()->status);
        $this->assertSame('individual', $clinic->subscription->refresh()->plan->code);
        $this->assertSame('seat_limit', $service->operationalBlockReason($clinic));
        $this->assertSame(SubscriptionStatus::Expired, $service->statusAt($clinic, $activeTeam->ends_at->addDay()));
        $this->assertDatabaseHas('audit_logs', ['clinic_id' => $clinic->id, 'action' => 'subscription.granted']);

        $payload = [
            'order' => ['invoice_number' => $pending->invoice_number, 'amount' => $pending->amount],
            'transaction' => ['status' => 'SUCCESS', 'original_request_id' => $pending->request_id],
            'customer' => ['id' => 'clinic-'.$clinic->id],
        ];
        app(DokuPaymentService::class)->processProviderPayload(
            'late-after-downgrade', 'reconciliation', $payload, json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $this->assertSame(PaymentStatus::Review, $pending->refresh()->status);
        $this->assertDatabaseCount('subscription_periods', 3);
    }

    public function test_platform_admin_can_downgrade_paid_team_to_free_without_erasing_invoice_or_addon_history(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $clinic = Clinic::factory()->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'team')->where('duration_months', 1)->firstOrFail();
        $price->load('plan');
        $basePayment = Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_price_id' => $price->id,
            'plan_code' => $price->plan->code,
            'plan_name' => $price->plan->name,
            'duration_months' => $price->duration_months,
            'amount' => $price->amount,
            'status' => PaymentStatus::Paid,
        ]);
        $service = app(SubscriptionService::class);
        $teamPeriod = $service->activatePaid($clinic, $price, $basePayment);
        $addonPayment = Payment::factory()->create(['clinic_id' => $clinic->id, 'status' => PaymentStatus::Paid]);
        AddonPeriod::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_period_id' => $teamPeriod->id,
            'payment_id' => $addonPayment->id,
            'starts_at' => $teamPeriod->starts_at,
            'ends_at' => $teamPeriod->ends_at,
        ]);
        $this->assertTrue(app(AddonService::class)->canEdit($clinic));

        $this->actingAs($admin)->post(route('platform.subscription-grants.store', $clinic), [
            'plan_code' => 'free',
            'reason' => 'Klinik mengajukan downgrade langsung ke Free.',
        ])->assertRedirect()->assertSessionHas('status', 'Downgrade ke Free berlaku sekarang.');

        $this->assertSame(SubscriptionStatus::Free, $service->statusAt($clinic, CarbonImmutable::now('UTC')));
        $this->assertSame('free', $service->refreshStatus($clinic)->plan->code);
        $this->assertSame(SubscriptionStatus::Free, $service->statusAt($clinic, $teamPeriod->ends_at->addDay()));
        $this->assertFalse(app(AddonService::class)->canEdit($clinic));
        $this->assertNotNull($teamPeriod->refresh()->revoked_at);
        $this->assertSame(PaymentStatus::Paid, $basePayment->refresh()->status);
        $this->assertDatabaseCount('addon_periods', 1);
        $this->assertDatabaseHas('audit_logs', ['clinic_id' => $clinic->id, 'action' => 'subscription.downgraded']);
    }
}
