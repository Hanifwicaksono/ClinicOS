<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active,
            'source' => 'manual_grant',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'trial_used_at' => null,
        ];
    }
}
