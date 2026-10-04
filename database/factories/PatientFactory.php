<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
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
            'medical_record_number' => 'TEMP-'.Str::upper(Str::random(20)),
            'name' => fake()->name(),
            'nik' => fake()->boolean(80) ? fake()->unique()->numerify('################') : null,
            'birth_date' => fake()->dateTimeBetween('-80 years', '-1 year'),
            'gender' => fake()->randomElement(['MALE', 'FEMALE']),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
