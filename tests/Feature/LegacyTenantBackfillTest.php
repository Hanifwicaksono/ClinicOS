<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Services\LegacyTenantBackfillService;
use App\SubscriptionStatus;
use App\UserAccountType;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyTenantBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    public function test_backfill_maps_owner_and_grants_team_once_without_payment_or_medical_changes(): void
    {
        $clinic = Clinic::factory()->create(['created_at' => '2026-10-01 00:00:00']);
        $owner = User::factory()->forClinic($clinic)->create(['account_type' => null]);
        $owner->assignRole('Clinic Admin');
        $patient = Patient::factory()->for($clinic)->create(['name' => 'Data Medis Tetap']);
        $service = app(LegacyTenantBackfillService::class);
        $cutoff = CarbonImmutable::parse('2026-10-10 00:00:00 UTC');
        $grantedAt = CarbonImmutable::parse('2026-10-16 03:00:00 UTC');

        $this->assertSame(1, $service->run($cutoff, $grantedAt));
        $this->assertSame(0, $service->run($cutoff, $grantedAt));

        $clinic->refresh();
        $subscription = $clinic->subscription()->with('plan')->firstOrFail();
        $period = $clinic->subscriptionPeriods()->sole();
        $this->assertSame($owner->id, $clinic->owner_user_id);
        $this->assertSame(UserAccountType::ClinicOwner, $owner->refresh()->account_type);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('team', $subscription->plan->code);
        $this->assertSame('legacy_migration', $period->source);
        $this->assertSame(12, $period->duration_months);
        $this->assertSame('2027-10-17 00:00:00', $period->ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertSame('Data Medis Tetap', $patient->refresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.owner_backfilled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.legacy_granted']);
    }

    public function test_ambiguous_owner_stops_the_entire_backfill_before_writes(): void
    {
        $validClinic = Clinic::factory()->create(['created_at' => '2026-10-01 00:00:00']);
        User::factory()->forClinic($validClinic)->create()->assignRole('Clinic Admin');
        $ambiguousClinic = Clinic::factory()->create(['created_at' => '2026-10-01 00:00:00']);
        User::factory()->count(2)->forClinic($ambiguousClinic)->create()
            ->each(fn (User $user) => $user->assignRole('Clinic Admin'));

        try {
            app(LegacyTenantBackfillService::class)->run(CarbonImmutable::parse('2026-10-10 00:00:00 UTC'));
            $this->fail('Backfill should reject an ambiguous owner.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString((string) $ambiguousClinic->id, $exception->getMessage());
        }

        $this->assertNull($validClinic->refresh()->owner_user_id);
        $this->assertNull($ambiguousClinic->refresh()->owner_user_id);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_periods', 0);
    }
}
