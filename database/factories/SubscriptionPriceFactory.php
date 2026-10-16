<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPrice>
 */
class SubscriptionPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'duration_months' => fake()->randomElement([1, 6, 12]),
            'amount' => fake()->numberBetween(100_000, 5_000_000),
            'is_active' => true,
        ];
    }
}
