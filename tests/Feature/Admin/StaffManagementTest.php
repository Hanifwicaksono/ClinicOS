<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_clinic_admin_can_create_doctor_and_assign_services(): void
    {
        Notification::fake();
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $service = Service::factory()->for($clinic)->create();

        $this->actingAs($admin)
            ->get(route('admin.staff.create'))
            ->assertOk()
            ->assertSee('name="password"', false);

        $response = $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'dr. Nadia Putri',
            'email' => 'nadia@clinic.test',
            'phone' => '08123456789',
            'password' => 'StaffPass123!',
            'role' => 'Doctor',
            'specialization' => 'Dokter Umum',
            'license_number' => 'SIP-12345',
            'bio' => 'Berpengalaman menangani pasien keluarga.',
            'service_ids' => [$service->id],
        ]);

        $doctorUser = User::query()->where('email', 'nadia@clinic.test')->firstOrFail();

        $response->assertRedirect(route('admin.staff.index'));
        $this->assertTrue($doctorUser->hasRole('Doctor'));
        $this->assertTrue(Hash::check('StaffPass123!', $doctorUser->password));
        $this->assertDatabaseHas('doctors', [
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'specialization' => 'Dokter Umum',
        ]);
        $this->assertDatabaseHas('doctor_service', [
            'doctor_id' => $doctorUser->doctor->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_clinic_admin_can_change_staff_password(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');

        $this->actingAs($admin)
            ->put(route('admin.staff.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'password' => 'NewStaffPass123!',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.staff.index'));

        $this->assertTrue(Hash::check('NewStaffPass123!', $staff->refresh()->password));
    }

    public function test_clinic_admin_cannot_edit_staff_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $otherStaff = User::factory()->forClinic(Clinic::factory()->create())->create();
        $otherStaff->assignRole('Receptionist');

        $this->actingAs($admin)
            ->get(route('admin.staff.edit', $otherStaff))
            ->assertForbidden();
    }
}
