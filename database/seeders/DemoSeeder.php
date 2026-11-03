<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed a complete, deterministic ClinicOS demonstration workspace.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoSeeder hanya dapat dijalankan pada environment local.');

            return;
        }

        $this->call([
            RoleAndPermissionSeeder::class,
            SubscriptionPlanSeeder::class,
            AddonPriceSeeder::class,
            DemoUserSeeder::class,
            ClinicSeeder::class,
            ClinicSettingSeeder::class,
            ServiceSeeder::class,
            DoctorSeeder::class,
            DoctorScheduleSeeder::class,
            PatientSeeder::class,
            AppointmentSeeder::class,
            QueueSeeder::class,
            MedicalVisitSeeder::class,
            DemoOperationalSeeder::class,
            DemoClinicalSeeder::class,
            DemoCommercialSeeder::class,
            DemoActivitySeeder::class,
        ]);
    }
}
