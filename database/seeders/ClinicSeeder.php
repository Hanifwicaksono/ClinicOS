<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $clinic = Clinic::query()->updateOrCreate(
            ['slug' => 'klinik-sehat-sentosa'],
            [
                'name' => 'Klinik Sehat Sentosa',
                'address' => 'Jl. Merdeka No. 10, Jakarta',
                'phone' => '021-555-0123',
                'email' => 'halo@kliniksehat.test',
                'description' => 'Klinik keluarga dengan layanan kesehatan yang mudah diakses.',
                'is_active' => true,
            ],
        );

        User::query()
            ->whereIn('email', [
                'admin@clinicos.test',
                'doctor@clinicos.test',
                'receptionist@clinicos.test',
            ])
            ->update(['clinic_id' => $clinic->id]);
    }
}
