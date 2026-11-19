<?php

namespace Database\Factories;

use App\Models\Visit;
use App\Models\VisitResumeReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisitResumeReservation> */
class VisitResumeReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory()->withConsistentRelations(),
            'clinic_id' => fn (array $attributes) => $this->visit($attributes)->clinic_id,
            'doctor_schedule_id' => fn (array $attributes) => $this->visit($attributes)->appointment->doctor_schedule_id,
            'resume_date' => now()->toDateString(),
            'state' => 'READY',
            'ready_by_user_id' => fn (array $attributes) => $this->visit($attributes)->doctor->user_id,
            'ready_at' => now(),
            'request_id' => fake()->uuid(),
        ];
    }

    /** @param array<string,mixed> $attributes */
    private function visit(array $attributes): Visit
    {
        return Visit::query()->findOrFail($attributes['visit_id']);
    }
}
