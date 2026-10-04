<?php

namespace Database\Factories;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'service_id' => Service::factory(),
            'doctor_schedule_id' => DoctorSchedule::factory(),
            'appointment_date' => now()->addDay()->toDateString(),
            'booking_code' => 'BK-'.Str::upper(Str::random(10)),
            'access_token_hash' => hash('sha256', Str::random(64)),
            'idempotency_key' => fake()->uuid(),
            'status' => AppointmentStatus::Booked,
            'source' => 'ONLINE',
            'service_name' => 'Konsultasi Umum',
            'service_price' => 75000,
        ];
    }
}
