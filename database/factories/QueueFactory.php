<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\QueueStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Queue>
 */
class QueueFactory extends Factory
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
            'appointment_id' => Appointment::factory(),
            'doctor_id' => Doctor::factory(),
            'doctor_schedule_id' => DoctorSchedule::factory(),
            'queue_date' => now()->addDay()->toDateString(),
            'queue_number' => fake()->unique()->numberBetween(1, 500),
            'display_number' => 'A-'.fake()->unique()->numerify('###'),
            'status' => QueueStatus::Booked,
        ];
    }
}
