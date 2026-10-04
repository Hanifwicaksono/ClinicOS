<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_name' => fake()->name(),
            'action' => 'auth.login',
            'entity_type' => User::class,
            'ip_address' => '127.0.0.1',
            'metadata' => ['guard' => 'web'],
        ];
    }
}
