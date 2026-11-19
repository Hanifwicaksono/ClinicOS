<?php

namespace Database\Factories;

use App\Models\FollowUp;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FollowUp> */
class FollowUpFactory extends Factory
{
    public function definition(): array
    {
        return ['source_visit_id' => Visit::factory()->withConsistentRelations(), 'clinic_id' => fn (array $a) => $this->source($a)->clinic_id,
            'patient_id' => fn (array $a) => $this->source($a)->patient_id, 'responsible_doctor_id' => fn (array $a) => $this->source($a)->doctor_id,
            'service_id' => fn (array $a) => $this->source($a)->service_id, 'created_by_user_id' => fn (array $a) => $this->source($a)->doctor->user_id,
            'instructions' => 'Released synthetic follow-up instructions.', 'range_starts_at' => now()->startOfDay(), 'range_ends_at' => now()->addDays(8)->startOfDay(), 'input_timezone' => 'UTC',
            'request_id' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }

    /** @param array<string,mixed> $a */
    private function source(array $a): Visit
    {
        return Visit::query()->findOrFail($a['source_visit_id']);
    }
}
