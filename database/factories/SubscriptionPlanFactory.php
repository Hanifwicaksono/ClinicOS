<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'doctor_limit' => fake()->numberBetween(1, 10),
            'staff_limit' => fake()->numberBetween(1, 20),
            'monthly_visit_limit' => null,
            'features' => [],
            'is_active' => true,
        ];
    }
}
