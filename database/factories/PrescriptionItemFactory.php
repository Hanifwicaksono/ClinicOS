<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'medicine_name' => fake()->randomElement(['Paracetamol 500 mg', 'Amoxicillin 500 mg', 'Omeprazole 20 mg']),
            'dosage' => '1 tablet',
            'frequency' => '3 kali sehari',
            'quantity' => 10,
            'unit' => 'tablet',
            'instructions' => 'Sesudah makan',
            'sort_order' => 0,
        ];
    }
}
