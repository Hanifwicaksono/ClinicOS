<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $clinic = Clinic::query()->where('slug', 'klinik-sehat-sentosa')->with('settings')->first();
        $patient = Patient::query()->where('clinic_id', $clinic?->id)->first();
        $doctor = Doctor::query()->where('clinic_id', $clinic?->id)->with(['services', 'schedules'])->first();
        $schedule = $doctor?->schedules->first();
        $service = $doctor?->services->first();

        if ($clinic === null || $patient === null || $doctor === null || $schedule === null || $service === null) {
            return;
        }

        $date = CarbonImmutable::now($clinic->settings?->timezone ?? 'Asia/Jakarta')->addDay()->startOfDay();
        while ($date->dayOfWeek !== $schedule->day_of_week) {
            $date = $date->addDay();
        }

        app(BookingService::class)->book($clinic, [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date->toDateString(),
            'idempotency_key' => '00000000-0000-4000-8000-000000000001',
            'notes' => 'Data booking demo.',
        ], source: 'SEEDER');
    }
}
