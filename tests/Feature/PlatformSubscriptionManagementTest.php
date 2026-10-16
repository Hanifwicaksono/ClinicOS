<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use App\Models\User;
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
        $this->patch(route('platform.subscription-prices.update', $price), ['is_active' => false])->assertRedirect();
        $this->assertFalse($price->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => null,
            'user_id' => $platformAdmin->id,
            'action' => 'subscription_price.updated',
        ]);
        $this->patch(route('platform.subscription-prices.update', $price), ['is_active' => true])->assertRedirect();
        $this->post(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $price->id,
            'reason' => 'Kompensasi migrasi pelanggan lama',
        ])->assertRedirect();

        $this->assertDatabaseHas('subscription_periods', [
            'clinic_id' => $clinic->id,
            'subscription_plan_id' => $team->id,
            'granted_by_user_id' => $platformAdmin->id,
            'duration_months' => 12,
            'amount' => 4_999_000,
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
        $this->post(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $price->id,
            'reason' => 'Tidak sah',
        ])->assertForbidden();
    }
}
