<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Clinic',
            'slug' => fake()->unique()->slug(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function ownedBy(User $owner): static
    {
        return $this
            ->state(fn (array $attributes) => ['owner_user_id' => $owner->id])
            ->afterCreating(fn (Clinic $clinic) => $owner->update(['clinic_id' => $clinic->id]));
    }
}
