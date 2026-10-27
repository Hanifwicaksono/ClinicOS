<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_shows_live_platform_statistics_and_subscription_prices(): void
    {
        $activeClinic = Clinic::factory()->create();
        Clinic::factory()->create(['is_active' => false]);
        Patient::factory()->for($activeClinic)->count(2)->create();
        Patient::factory()->for($activeClinic)->create(['is_active' => false]);

        $individual = SubscriptionPlan::factory()->create(['code' => 'individual']);
        SubscriptionPrice::factory()->for($individual, 'plan')->create([
            'amount' => 149_000,
            'duration_months' => 1,
        ]);
        SubscriptionPrice::factory()->for($individual, 'plan')->create([
            'amount' => 799_000,
            'duration_months' => 6,
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('1 klinik · 2 pasien tercatat')
            ->assertSee('Rp 149.000');
    }
}
