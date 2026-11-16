<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** @return array<string, array{string}> */
    public static function bookedStatuses(): array
    {
        return ['booked' => ['BOOKED'], 'cancelled history' => ['CANCELLED'], 'completed history' => ['COMPLETED']];
    }

    #[DataProvider('bookedStatuses')]
    public function test_used_sessions_keep_their_doctor_while_quota_can_be_updated(string $status): void
    {
        [$admin, $doctor] = $this->clinicTeam();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'quota' => 10,
        ]);
        $replacementUser = User::factory()->forClinic($doctor->clinic)->create();
        $replacementUser->assignRole('Doctor');
        $replacement = Doctor::factory()->for($doctor->clinic)->for($replacementUser, 'user')->create();
        $appointment = Appointment::factory()->for($doctor->clinic)->for($doctor)
            ->for(Patient::factory()->for($doctor->clinic))->for(Service::factory()->for($doctor->clinic))
            ->for($schedule, 'schedule')->create(['status' => $status]);
        $payload = ['doctor_id' => $replacement->id, 'day_of_week' => 1,
            'start_time' => '09:00', 'end_time' => '12:00', 'quota' => 17, 'is_active' => true,
        ];
        $this->actingAs($admin)->putJson(route('admin.doctor-schedules.update', $schedule), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('doctor_id');
        $this->assertSame($doctor->id, $schedule->refresh()->doctor_id);
        $this->assertSame(10, $schedule->quota);
        $this->assertSame($doctor->id, $appointment->refresh()->doctor_id);
        $this->putJson(route('admin.doctor-schedules.update', $schedule), [...$payload, 'doctor_id' => $doctor->id])
            ->assertRedirect(route('admin.doctor-schedules.index'));
        $this->assertSame(17, $schedule->refresh()->quota);
    }

    public function test_unused_session_can_be_reassigned_only_by_its_clinic_admin(): void
    {
        [$admin, $doctor] = $this->clinicTeam();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);
        $replacementUser = User::factory()->forClinic($doctor->clinic)->create();
        $replacementUser->assignRole('Doctor');
        $replacement = Doctor::factory()->for($doctor->clinic)->for($replacementUser, 'user')->create();
        $payload = ['doctor_id' => $replacement->id, 'day_of_week' => 1,
            'start_time' => '09:00', 'end_time' => '12:00', 'quota' => 17, 'is_active' => true,
        ];
        foreach (['Doctor', 'Receptionist', 'Patient', 'Platform Admin'] as $role) {
            $actor = User::factory()->forClinic($doctor->clinic)->create();
            $actor->assignRole($role);
            $this->actingAs($actor)->putJson(route('admin.doctor-schedules.update', $schedule), $payload)->assertForbidden();
        }
        [$foreignAdmin, $foreignDoctor] = $this->clinicTeam();
        $this->actingAs($foreignAdmin)->putJson(route('admin.doctor-schedules.update', $schedule), $payload)->assertForbidden();
        $this->actingAs($admin)->putJson(route('admin.doctor-schedules.update', $schedule), [...$payload, 'doctor_id' => $foreignDoctor->id])
            ->assertUnprocessable()->assertJsonValidationErrors('doctor_id');
        $this->assertSame($doctor->id, $schedule->refresh()->doctor_id);
        $this->putJson(route('admin.doctor-schedules.update', $schedule), $payload)->assertRedirect();
        $this->assertSame($replacement->id, $schedule->refresh()->doctor_id);
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
