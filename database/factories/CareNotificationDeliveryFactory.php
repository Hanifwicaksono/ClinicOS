<?php

namespace Database\Factories;

use App\Models\CareNotificationDelivery;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CareNotificationDelivery> */
class CareNotificationDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory()->withConsistentRelations(),
            'clinic_id' => fn (array $attributes) => $this->visit($attributes)->clinic_id,
            'recipient_user_id' => fn (array $attributes) => $this->visit($attributes)->doctor->user_id,
            'kind' => 'pause_overdue',
            'generation' => 1,
            'notification_id' => fake()->uuid(),
        ];
    }

    /** @param array<string,mixed> $attributes */
    private function visit(array $attributes): Visit
    {
        return Visit::query()->findOrFail($attributes['visit_id']);
    }
}
