<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_clinic_admin_can_create_schedule_within_operating_hours(): void
    {
        [$admin, $doctor] = $this->clinicTeam();

        $response = $this->actingAs($admin)->post(route('admin.doctor-schedules.store'), [
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'quota' => 15,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.doctor-schedules.index'));
        $this->assertDatabaseHas('doctor_schedules', [
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'quota' => 15,
        ]);
    }

    public function test_overlapping_schedule_is_rejected(): void
    {
        [$admin, $doctor] = $this->clinicTeam();
        DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.doctor-schedules.create'))
            ->post(route('admin.doctor-schedules.store'), [
                'doctor_id' => $doctor->id,
                'day_of_week' => 1,
                'start_time' => '11:00',
                'end_time' => '13:00',
                'quota' => 10,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.doctor-schedules.create'))
            ->assertSessionHasErrors('start_time');
    }

    /** @return array{User, Doctor} */
    private function clinicTeam(): array
    {
        $clinic = Clinic::factory()->create();
        $clinic->settings()->create([
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'timezone' => 'Asia/Jakarta',
            'queue_prefix' => 'A',
        ]);
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser, 'user')->create();

        return [$admin, $doctor];
    }
}
