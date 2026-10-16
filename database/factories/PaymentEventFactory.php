<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentEvent>
 */
class PaymentEventFactory extends Factory
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
            'provider_request_id' => (string) Str::uuid(),
            'source' => 'notification',
            'invoice_number' => fake()->unique()->bothify('COS############'),
            'provider_status' => 'SUCCESS',
            'payload_hash' => hash('sha256', fake()->uuid()),
            'result' => 'processed',
            'received_at' => now(),
            'processed_at' => now(),
        ];
    }
}
