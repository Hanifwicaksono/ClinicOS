<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
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
            'name' => fake()->randomElement(['Konsultasi', 'Perawatan luka', 'Nebulisasi']),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
