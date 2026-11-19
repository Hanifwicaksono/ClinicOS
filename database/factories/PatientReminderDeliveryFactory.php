<?php

namespace Database\Factories;

use App\Models\FollowUp;
use App\Models\PatientReminderDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientReminderDelivery> */
class PatientReminderDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return ['target_type' => 'follow_up', 'target_id' => FollowUp::factory(), 'clinic_id' => fn (array $a) => FollowUp::findOrFail($a['target_id'])->clinic_id, 'recipient_user_id' => function (array $a): int {
            $task = FollowUp::findOrFail($a['target_id']);
            $user = User::factory()->forClinic($task->clinic)->create();
            $user->assignRole('Receptionist');

            return $user->id;
        }, 'category' => 'follow_up_contact', 'generation' => 1, 'notification_id' => fake()->uuid(), 'state' => 'PENDING'];
    }
}
