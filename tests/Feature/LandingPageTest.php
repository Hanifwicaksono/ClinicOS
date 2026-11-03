<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_shows_subscription_prices_without_platform_statistics(): void
    {
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
            ->assertSee('Dirancang untuk alur kerja klinik')
            ->assertDontSee('pasien tercatat')
            ->assertSee('Rp 149.000');
    }
}
