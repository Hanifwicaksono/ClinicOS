<?php

namespace Database\Seeders;

use App\Models\Doctor;
use Illuminate\Database\Seeder;

class DoctorScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $doctor = Doctor::query()->whereHas('user', fn ($query) => $query->where('email', 'doctor@clinicos.test'))->first();

        if ($doctor === null) {
            return;
        }

        foreach (range(0, 6) as $dayOfWeek) {
            $doctor->schedules()->updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                [
                    'start_time' => '00:00',
                    'end_time' => '23:59',
                    'quota' => 16,
                    'is_active' => true,
                ],
            );
        }
    }
}
