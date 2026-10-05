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

        $patients = [
            [
                'nik' => '3273010101900001',
                'user_id' => $user?->id,
                'name' => $user?->name ?? 'Ahmad Fauzi',
                'birth_date' => '1990-01-01',
                'gender' => 'MALE',
                'phone' => '081234567890',
                'email' => $user?->email,
                'address' => 'Coblong, Kota Bandung',
                'is_active' => true,
            ],
            ['nik' => '3273021502850002', 'name' => 'Siti Aminah', 'birth_date' => '1985-02-15', 'gender' => 'FEMALE', 'phone' => '081298765401', 'email' => 'siti.aminah@example.test', 'address' => 'Cicendo, Kota Bandung', 'is_active' => true],
            ['nik' => '3273032211720003', 'name' => 'Bambang Sutrisno', 'birth_date' => '1972-11-22', 'gender' => 'MALE', 'phone' => '082112340003', 'email' => null, 'address' => 'Antapani, Kota Bandung', 'is_active' => true],
            ['nik' => '3273040809980004', 'name' => 'Dewi Lestari', 'birth_date' => '1998-09-08', 'gender' => 'FEMALE', 'phone' => '085721340004', 'email' => 'dewi.lestari@example.test', 'address' => 'Buahbatu, Kota Bandung', 'is_active' => true],
            ['nik' => '3273051905600005', 'name' => 'Hendra Gunawan', 'birth_date' => '1960-05-19', 'gender' => 'MALE', 'phone' => '081320450005', 'email' => null, 'address' => 'Sukajadi, Kota Bandung', 'is_active' => true],
            ['nik' => '3273060303150006', 'name' => 'Nadia Putri', 'birth_date' => '2015-03-03', 'gender' => 'FEMALE', 'phone' => '081911220006', 'email' => null, 'address' => 'Arcamanik, Kota Bandung', 'is_active' => true],
            ['nik' => '3273071708070007', 'name' => 'Rizky Pratama', 'birth_date' => '2007-08-17', 'gender' => 'MALE', 'phone' => '087812340007', 'email' => 'rizky.pratama@example.test', 'address' => 'Lengkong, Kota Bandung', 'is_active' => true],
            ['nik' => '3273082912880008', 'name' => 'Maria Christina', 'birth_date' => '1988-12-29', 'gender' => 'FEMALE', 'phone' => '081223450008', 'email' => 'maria.christina@example.test', 'address' => 'Bandung Wetan, Kota Bandung', 'is_active' => true],
            ['nik' => '3273091106790009', 'name' => 'Yusuf Maulana', 'birth_date' => '1979-06-11', 'gender' => 'MALE', 'phone' => '085155660009', 'email' => null, 'address' => 'Kiaracondong, Kota Bandung', 'is_active' => true],
            ['nik' => '3273102404020010', 'name' => 'Lina Marlina', 'birth_date' => '2002-04-24', 'gender' => 'FEMALE', 'phone' => '083812340010', 'email' => 'lina.marlina@example.test', 'address' => 'Ujungberung, Kota Bandung', 'is_active' => true],
            ['nik' => '3273110519450011', 'name' => 'Soedarto', 'birth_date' => '1945-10-05', 'gender' => 'MALE', 'phone' => '081377770011', 'email' => null, 'address' => 'Cibeunying Kaler, Kota Bandung', 'is_active' => true],
            ['nik' => '3273121203930012', 'name' => 'Fitri Handayani', 'birth_date' => '1993-03-12', 'gender' => 'FEMALE', 'phone' => '089612340012', 'email' => 'fitri.handayani@example.test', 'address' => 'Regol, Kota Bandung', 'is_active' => false],
        ];

        foreach ($patients as $patientData) {
            $patient = Patient::query()->firstOrNew([
                'clinic_id' => $clinic->id,
                'nik' => $patientData['nik'],
            ]);
            $patient->fill($patientData);

            if (! $patient->exists) {
                $patient->medical_record_number = 'TEMP-'.Str::random(32);
            }

            $patient->save();

            if (str_starts_with($patient->medical_record_number, 'TEMP-')) {
                $patient->update([
                    'medical_record_number' => app(MedicalRecordNumberGenerator::class)->generate($patient),
                ]);
            }
        }
    }
}
