<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
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
            'user_id' => User::factory(),
            'specialization' => fake()->randomElement(['Dokter Umum', 'Dokter Gigi', 'Dokter Anak']),
            'license_number' => fake()->unique()->numerify('SIP-######'),
            'bio' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
