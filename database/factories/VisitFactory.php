<?php

namespace Database\Factories;

use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
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

    public function withConsistentRelations(): static
    {
        return $this->state(function (): array {
            $clinic = Clinic::factory()->create();
            $user = User::factory()->forClinic($clinic)->create();
            $user->assignRole('Doctor');
            $doctor = Doctor::factory()->for($clinic)->for($user)->create();
            $service = Service::factory()->for($clinic)->create();
            $doctor->services()->attach($service);
            $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now()->dayOfWeek]);
            $appointment = Appointment::factory()->for($clinic)->for($doctor)->for($service)->for(Patient::factory()->for($clinic))
                ->create(['doctor_schedule_id' => $schedule->id, 'appointment_date' => now()->toDateString()]);

            return ['appointment_id' => $appointment->id];
        })->afterCreating(function (Visit $visit): void {
            $visit->queue()->create(['clinic_id' => $visit->clinic_id, 'doctor_id' => $visit->doctor_id, 'doctor_schedule_id' => $visit->appointment->doctor_schedule_id,
                'queue_date' => $visit->appointment->appointment_date, 'queue_number' => 1, 'display_number' => 'A-001', 'status' => QueueStatus::InProgress]);
            $visit->medicalRecord()->create(['patient_id' => $visit->patient_id, 'doctor_id' => $visit->doctor_id, 'created_by_user_id' => $visit->doctor->user_id, 'status' => MedicalRecordStatus::Draft]);
        });
    }

    public function completedWithDuration(int $durationMinutes, int $minutesAgo = 0): static
    {
        return $this->state(function () use ($durationMinutes, $minutesAgo): array {
            $completedAt = now()->subMinutes($minutesAgo);

            return [
                'status' => VisitStatus::Completed,
                'started_at' => $completedAt->copy()->subMinutes($durationMinutes),
                'completed_at' => $completedAt,
            ];
        });
    }

    /** @param array<string, mixed> $attributes */
    private function appointment(array $attributes): Appointment
    {
        return Appointment::query()->findOrFail($attributes['appointment_id']);
    }
}
