<?php

namespace Database\Factories;

use App\Models\AddonPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonPrice>
 */
class AddonPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'odontogram',
            'name' => 'Odontogram',
            'monthly_amount' => 99_000,
            'is_active' => true,
        ];
    }
}
