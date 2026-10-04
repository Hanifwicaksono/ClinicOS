<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\VitalSign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VitalSign>
 */
class VitalSignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medical_record_id' => MedicalRecord::factory(),
            'weight_kg' => fake()->randomFloat(1, 40, 100),
            'height_cm' => fake()->randomFloat(1, 145, 190),
            'systolic' => fake()->numberBetween(100, 140),
            'diastolic' => fake()->numberBetween(60, 95),
            'pulse' => fake()->numberBetween(60, 100),
            'respiratory_rate' => fake()->numberBetween(12, 24),
            'temperature_c' => fake()->randomFloat(1, 36, 38),
            'oxygen_saturation' => fake()->numberBetween(95, 100),
        ];
    }
}
