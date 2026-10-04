<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\QueueStatus;
use Illuminate\Database\Seeder;

class QueueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $appointment = Appointment::query()->where('source', 'SEEDER')->latest()->first();

        if ($appointment === null || $appointment->queue()->exists()) {
            return;
        }

        $appointment->queue()->create([
            'clinic_id' => $appointment->clinic_id,
            'doctor_id' => $appointment->doctor_id,
            'doctor_schedule_id' => $appointment->doctor_schedule_id,
            'queue_date' => $appointment->appointment_date,
            'queue_number' => 1,
            'display_number' => 'A-001',
            'status' => QueueStatus::Booked,
        ]);
    }
}
