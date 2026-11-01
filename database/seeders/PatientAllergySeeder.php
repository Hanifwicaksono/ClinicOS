<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\PatientAllergy;
use Illuminate\Database\Seeder;

class PatientAllergySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patient = Patient::factory()->create([
            'name' => 'Pasien Fixture Alergi Kritis',
        ]);

        PatientAllergy::factory()->for($patient)->critical()->create([
            'substance' => 'Penisilin',
            'reaction' => 'Anafilaksis',
        ]);
    }
}
