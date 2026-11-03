<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentItem>
 */
class PaymentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'kind' => 'plan',
            'code' => 'individual',
            'name' => 'Individual',
            'amount' => 149_000,
            'duration_months' => 1,
            'monthly_amount' => null,
            'subscription_price_id' => null,
            'addon_price_id' => null,
            'target_subscription_period_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
