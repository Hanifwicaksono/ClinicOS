<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPeriod>
 */
class SubscriptionPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'clinic_id' => fn (array $attributes) => Subscription::query()->findOrFail($attributes['subscription_id'])->clinic_id,
            'subscription_plan_id' => fn (array $attributes) => Subscription::query()->findOrFail($attributes['subscription_id'])->subscription_plan_id,
            'payment_id' => null,
            'granted_by_user_id' => null,
            'plan_code' => fake()->slug(2),
            'plan_name' => fake()->words(2, true),
            'duration_months' => 1,
            'amount' => fake()->numberBetween(100_000, 5_000_000),
            'source' => 'manual_grant',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'reason' => fake()->sentence(),
        ];
    }
}
