<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ClinicReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClinicReportExport> */
class ClinicReportExportFactory extends Factory
{
    public function definition(): array
    {
        return ['clinic_id' => Clinic::factory(), 'requested_by_user_id' => function (array $a): int {
            $user = User::factory()->forClinic(Clinic::findOrFail($a['clinic_id']))->create();
            $user->assignRole('Clinic Admin');

            return $user->id;
        },
            'kind' => 'activity', 'timezone' => 'Asia/Jakarta', 'period_starts_at' => now('Asia/Jakarta')->startOfDay()->utc(), 'period_ends_at' => now('Asia/Jakarta')->addDay()->startOfDay()->utc(),
            'expires_at' => now()->addDay(), 'request_id' => fake()->uuid(), 'request_fingerprint' => hash('sha256', fake()->uuid())];
    }
}
