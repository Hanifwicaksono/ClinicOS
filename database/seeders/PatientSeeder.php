<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Services\MedicalRecordNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PatientSeeder extends Seeder
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
        $user = User::query()->where('email', 'patient@clinicos.test')->first();

        if ($clinic === null) {
            return;
        }

        $patient = Patient::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'nik' => '3273010101900001'],
            [
                'user_id' => $user?->id,
                'medical_record_number' => 'TEMP-'.Str::random(32),
                'name' => $user?->name ?? 'Siti Rahma',
                'birth_date' => '1990-01-01',
                'gender' => 'FEMALE',
                'phone' => '081234567890',
                'email' => $user?->email,
                'address' => 'Bandung, Jawa Barat',
                'is_active' => true,
            ],
        );

        if (str_starts_with($patient->medical_record_number, 'TEMP-')) {
            $patient->update([
                'medical_record_number' => app(MedicalRecordNumberGenerator::class)->generate($patient),
            ]);
        }
    }
}
