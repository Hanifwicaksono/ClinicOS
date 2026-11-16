<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_code_fits_schema_for_the_previous_overflow_seed(): void
    {
        fake()->unique(true);
        fake()->seed(0);
        try {
            $attributes = SubscriptionPlan::factory()->raw();
            $this->assertLessThanOrEqual(30, strlen($attributes['code']));
            $plan = SubscriptionPlan::factory()->create($attributes);
            $this->assertSame($attributes['code'], $plan->fresh()->code);
        } finally {
            fake()->unique(true);
            fake()->seed();
        }
    }

    public function test_a_deterministic_code_at_the_schema_boundary_is_preserved(): void
    {
        $code = str_repeat('a', 30);
        $plan = SubscriptionPlan::factory()->create(['code' => $code]);

        $this->assertSame($code, $plan->fresh()->code);
    }
}
