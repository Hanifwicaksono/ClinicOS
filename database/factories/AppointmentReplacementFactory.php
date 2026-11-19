<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentReplacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppointmentReplacement> */
class AppointmentReplacementFactory extends Factory
{
    public function definition(): array
    {
        return ['old_appointment_id' => Appointment::factory(), 'clinic_id' => fn (array $attributes) => Appointment::findOrFail($attributes['old_appointment_id'])->clinic_id,
            'new_appointment_id' => function (array $attributes): int {
                $old = Appointment::findOrFail($attributes['old_appointment_id']);

                return Appointment::factory()->create($old->only(['clinic_id', 'patient_id', 'doctor_id', 'doctor_schedule_id', 'service_id']))->id;
            }, 'actor_user_id' => fn (array $attributes) => Appointment::findOrFail($attributes['old_appointment_id'])->doctor->user_id,
            'request_id' => fake()->uuid(), 'request_fingerprint' => str_repeat('a', 64), 'reason' => 'Perubahan jadwal fixture.', 'reason_source' => 'CLINIC'];
    }
}
