<?php

namespace Database\Factories;

use App\AllergySeverity;
use App\Models\Patient;
use App\Models\PatientAllergy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAllergy>
 */
class PatientAllergyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'substance' => fake()->randomElement(['Penisilin', 'Sulfonamida', 'Aspirin', 'Lateks']),
            'reaction' => fake()->randomElement(['Urtikaria', 'Sesak napas', 'Anafilaksis']),
            'severity' => fake()->randomElement(AllergySeverity::cases()),
            'is_active' => true,
            'recorded_by_user_id' => null,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (): array => [
            'severity' => AllergySeverity::Critical,
            'reaction' => 'Anafilaksis',
            'is_active' => true,
        ]);
    }
}
