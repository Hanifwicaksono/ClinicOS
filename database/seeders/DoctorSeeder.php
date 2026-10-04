<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
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
        $user = User::query()->where('email', 'doctor@clinicos.test')->first();

        if ($clinic === null || $user === null) {
            return;
        }

        $doctor = Doctor::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'clinic_id' => $clinic->id,
                'specialization' => 'Dokter Umum',
                'license_number' => 'SIP-DEMO-001',
                'bio' => 'Dokter layanan primer untuk pasien dewasa dan keluarga.',
                'is_active' => true,
            ],
        );

        $doctor->services()->sync($clinic->services()->pluck('id'));
    }
}
