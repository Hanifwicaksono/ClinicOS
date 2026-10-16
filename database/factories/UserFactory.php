<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\User;
use App\UserAccountType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forClinic(?Clinic $clinic = null): static
    {
        return $this->state(fn (array $attributes) => [
            'clinic_id' => $clinic?->getKey() ?? Clinic::factory(),
        ]);
    }

    public function clinicOwner(Clinic $clinic): static
    {
        return $this->forClinic($clinic)
            ->state(fn (array $attributes) => ['account_type' => UserAccountType::ClinicOwner])
            ->afterCreating(function (User $user) use ($clinic): void {
                $user->assignRole('Clinic Admin');
                $clinic->update(['owner_user_id' => $user->id]);
            });
    }

    public function clinicOwnerCandidate(): static
    {
        return $this
            ->state(fn (array $attributes) => ['account_type' => UserAccountType::ClinicOwner])
            ->afterCreating(fn (User $user) => $user->assignRole('Clinic Admin'));
    }

    public function platformAdmin(): static
    {
        return $this
            ->state(fn (array $attributes) => ['clinic_id' => null])
            ->afterCreating(fn (User $user) => $user->assignRole('Platform Admin'));
    }
}
