<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
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

        foreach ([
            ['Konsultasi Umum', 75000, 30],
            ['Pemeriksaan Kesehatan', 125000, 45],
            ['Konsultasi Anak', 100000, 30],
        ] as [$name, $price, $duration]) {
            $clinic->services()->updateOrCreate(
                ['name' => $name],
                [
                    'description' => 'Layanan '.$name.' di Klinik Sehat Sentosa.',
                    'price' => $price,
                    'duration_minutes' => $duration,
                    'is_active' => true,
                ],
            );
        }
    }
}
