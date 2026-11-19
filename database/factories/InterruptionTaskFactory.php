<?php

namespace Database\Factories;

use App\Models\InterruptionTask;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InterruptionTask> */
class InterruptionTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory()->withConsistentRelations(),
            'clinic_id' => fn (array $attributes) => $this->visit($attributes)->clinic_id,
            'kind' => 'CARE',
            'status' => 'OPEN',
        ];
    }

    /** @param array<string,mixed> $attributes */
    private function visit(array $attributes): Visit
    {
        return Visit::query()->findOrFail($attributes['visit_id']);
    }
}
