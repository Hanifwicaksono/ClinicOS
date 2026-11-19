<?php

namespace Database\Factories;

use App\Models\Visit;
use App\Models\VisitWorkflowEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisitWorkflowEvent> */
class VisitWorkflowEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory()->withConsistentRelations(),
            'clinic_id' => fn (array $attributes) => $this->visit($attributes)->clinic_id,
            'actor_user_id' => fn (array $attributes) => $this->visit($attributes)->doctor->user_id,
            'action' => 'coordination.note',
            'from_status' => 'IN_PROGRESS',
            'to_status' => 'IN_PROGRESS',
            'workflow_version' => 1,
            'record_version' => 0,
            'reason' => 'Case coordination recorded.',
            'details' => [],
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
