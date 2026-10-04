<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'name' => 'Layanan '.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([50000, 75000, 100000, 150000]),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'is_active' => true,
        ];
    }
}
