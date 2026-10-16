<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Payment;
use App\Models\SubscriptionPrice;
use App\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'subscription_price_id' => SubscriptionPrice::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('##########'),
            'request_id' => (string) Str::uuid(),
            'provider' => 'doku',
            'plan_code' => 'individual',
            'plan_name' => 'Individual',
            'duration_months' => 1,
            'amount' => 149_000,
            'status' => PaymentStatus::Pending,
            'payment_url' => null,
            'expires_at' => now()->addHour(),
            'paid_at' => null,
            'provider_reference' => null,
        ];
    }
}
