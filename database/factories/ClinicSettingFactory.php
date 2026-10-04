<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicSetting>
 */
class ClinicSettingFactory extends Factory
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
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'timezone' => 'Asia/Jakarta',
            'queue_prefix' => 'A',
        ];
    }
}
