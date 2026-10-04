<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecordRevision>
 */
class MedicalRecordRevisionFactory extends Factory
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
            'corrected_by_user_id' => User::factory(),
            'reason' => 'Memperbaiki informasi klinis yang kurang tepat.',
            'before_data' => ['record' => ['assessment' => 'Diagnosis awal']],
            'after_data' => ['record' => ['assessment' => 'Diagnosis terkoreksi']],
        ];
    }
}
