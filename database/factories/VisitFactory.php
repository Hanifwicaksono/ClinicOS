<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Visit;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'clinic_id' => fn (array $attributes) => $this->appointment($attributes)->clinic_id,
            'patient_id' => fn (array $attributes) => $this->appointment($attributes)->patient_id,
            'doctor_id' => fn (array $attributes) => $this->appointment($attributes)->doctor_id,
            'service_id' => fn (array $attributes) => $this->appointment($attributes)->service_id,
            'started_by_user_id' => fn (array $attributes) => $this->appointment($attributes)->doctor->user_id,
            'status' => VisitStatus::InProgress,
            'service_name' => fn (array $attributes) => $this->appointment($attributes)->service_name,
            'service_price' => fn (array $attributes) => $this->appointment($attributes)->service_price,
            'total_amount' => fn (array $attributes) => $this->appointment($attributes)->service_price,
            'started_at' => now(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function appointment(array $attributes): Appointment
    {
        return Appointment::query()->findOrFail($attributes['appointment_id']);
    }
}
