<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;

class ClinicSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $clinic = Clinic::query()->where('slug', 'klinik-sehat-sentosa')->first();

        if ($clinic === null) {
            return;
        }

        $clinic->settings()->updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'opening_time' => '08:00',
                'closing_time' => '17:00',
                'timezone' => 'Asia/Jakarta',
                'queue_prefix' => 'KS',
            ],
        );
    }
}
