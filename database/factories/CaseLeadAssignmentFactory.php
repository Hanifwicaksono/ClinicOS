<?php

namespace Database\Factories;

use App\Models\CaseLeadAssignment;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CaseLeadAssignment> */
class CaseLeadAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory()->withConsistentRelations(),
            'clinic_id' => fn (array $attributes) => $this->visit($attributes)->clinic_id,
            'doctor_id' => fn (array $attributes) => $this->visit($attributes)->doctor_id,
            'approver_user_id' => fn (array $attributes) => $this->visit($attributes)->doctor->user_id,
            'role' => 'PRIMARY',
            'purpose' => 'CARE_COORDINATION',
            'verification_evidence' => 'Verified case responsibility by the attending doctor.',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'request_id' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
        ];
    }

    /** @param array<string,mixed> $attributes */
    private function visit(array $attributes): Visit
    {
        return Visit::query()->findOrFail($attributes['visit_id']);
    }
}
