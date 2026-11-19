<?php

namespace Database\Factories;

use App\Models\FollowUp;
use App\Models\FollowUpEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FollowUpEvent> */
class FollowUpEventFactory extends Factory
{
    public function definition(): array
    {
        return ['follow_up_id' => FollowUp::factory(), 'clinic_id' => fn (array $a) => FollowUp::findOrFail($a['follow_up_id'])->clinic_id,
            'actor_user_id' => fn (array $a) => FollowUp::findOrFail($a['follow_up_id'])->created_by_user_id, 'action' => 'contact', 'details' => [], 'reason' => 'Synthetic contact history.',
            'request_id' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }
}
