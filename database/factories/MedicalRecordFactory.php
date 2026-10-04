<?php

namespace Database\Factories;

use App\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'patient_id' => fn (array $attributes) => $this->visit($attributes)->patient_id,
            'doctor_id' => fn (array $attributes) => $this->visit($attributes)->doctor_id,
            'created_by_user_id' => fn (array $attributes) => $this->visit($attributes)->started_by_user_id,
            'status' => MedicalRecordStatus::Draft,
            'chief_complaint' => fake()->sentence(),
            'subjective' => fake()->paragraph(),
            'objective' => fake()->paragraph(),
            'assessment' => fake()->sentence(),
            'plan' => fake()->paragraph(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function visit(array $attributes): Visit
    {
        return Visit::query()->findOrFail($attributes['visit_id']);
    }
}
