<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DoctorAbsence> */
class DoctorAbsenceFactory extends Factory
{
    public function definition(): array
    {
        return ['doctor_id' => Doctor::factory(), 'clinic_id' => fn (array $attributes) => Doctor::findOrFail($attributes['doctor_id'])->clinic_id,
            'reported_by_user_id' => fn (array $attributes) => Doctor::findOrFail($attributes['doctor_id'])->user_id,
            'starts_at' => now()->startOfDay(), 'ends_at' => now()->addDay()->startOfDay(), 'timezone' => 'Asia/Jakarta', 'reason' => 'Berhalangan praktik.', 'request_id' => fake()->uuid(), 'request_fingerprint' => str_repeat('a', 64)];
    }
}
