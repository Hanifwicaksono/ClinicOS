<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffLifecycleAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();
    }

    public function test_receptionist_create_permission_does_not_authorize_doctor_creation(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        Role::findByName('Clinic Admin')->syncPermissions(['receptionist.create']);
        $response = $this->actingAs($admin->fresh())->postJson(route('admin.staff.store'), [
            'name' => 'Synthetic permission boundary', 'email' => 'restricted-create@clinic.test',
            'password' => 'SyntheticStaff123!', 'role' => 'Doctor', 'specialization' => 'Synthetic QA',
        ]);
        $this->assertFalse(User::query()->where('email', 'restricted-create@clinic.test')->exists(),
            'An administrator with only receptionist.create must not persist a Doctor account.');
        $response->assertForbidden();
    }

    public function test_doctor_with_booking_history_gets_a_controlled_self_deletion_error(): void
    {
        $clinic = Clinic::factory()->create();
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($staff, 'user')->create();
        $schedule = DoctorSchedule::factory()->for($doctor)->create();
        Appointment::factory()->for($clinic)->for($doctor)->for($schedule, 'schedule')
            ->for(Patient::factory()->for($clinic))->for(Service::factory()->for($clinic))->create();
        $this->actingAs($staff);
        try {
            Volt::test('profile.delete-user-form')->set('password', 'password')->call('deleteUser')
                ->assertHasErrors('password')->assertNoRedirect();
        } catch (QueryException) {
            $this->fail('Staff self-deletion must return a validation error instead of a foreign-key exception.');
        }
        $this->assertNotNull($staff->fresh());
        $this->assertAuthenticatedAs($staff);
    }
}
