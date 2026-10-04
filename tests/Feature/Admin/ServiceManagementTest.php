<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_clinic_admin_can_create_service_for_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');

        $response = $this->actingAs($admin)->post(route('admin.services.store'), [
            'name' => 'Konsultasi Umum',
            'description' => 'Konsultasi dengan dokter umum.',
            'price' => 75000,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.services.index'));
        $this->assertDatabaseHas('services', [
            'clinic_id' => $clinic->id,
            'name' => 'Konsultasi Umum',
            'price' => 75000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'service.created',
        ]);
    }

    public function test_clinic_admin_cannot_edit_service_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        $otherService = Service::factory()->for(Clinic::factory()->create())->create();

        $this->actingAs($admin)
            ->get(route('admin.services.edit', $otherService))
            ->assertForbidden();
    }
}
