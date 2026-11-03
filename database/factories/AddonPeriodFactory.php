<?php

namespace Database\Factories;

use App\Models\AddonPeriod;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonPeriod>
 */
class AddonPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_period_id' => SubscriptionPeriod::factory(),
            'clinic_id' => fn (array $attributes) => SubscriptionPeriod::query()->findOrFail($attributes['subscription_period_id'])->clinic_id,
            'payment_id' => fn (array $attributes) => Payment::factory()->create([
                'clinic_id' => SubscriptionPeriod::query()->findOrFail($attributes['subscription_period_id'])->clinic_id,
            ])->id,
            'code' => 'odontogram',
            'amount' => 99_000,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'revoked_at' => null,
        ];
    }
}
