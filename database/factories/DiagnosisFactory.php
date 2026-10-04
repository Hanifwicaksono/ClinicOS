<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diagnosis>
 */
class DiagnosisFactory extends Factory
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
            'code' => fake()->bothify('J##.#'),
            'name' => fake()->randomElement(['Infeksi saluran napas akut', 'Hipertensi', 'Dispepsia']),
            'notes' => fake()->optional()->sentence(),
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }
}
