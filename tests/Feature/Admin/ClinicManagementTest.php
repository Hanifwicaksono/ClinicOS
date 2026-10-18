<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_clinic_admin_can_create_clinic_with_operating_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Clinic Admin');

        $response = $this->actingAs($admin)->post(route('admin.clinic.store'), [
            'name' => 'Klinik Sehat Sentosa',
            'slug' => 'klinik-sehat-sentosa',
            'address' => 'Jl. Merdeka No. 10',
            'phone' => '0215550123',
            'email' => 'halo@klinik.test',
            'description' => 'Klinik keluarga.',
            'is_active' => true,
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'timezone' => 'Asia/Jakarta',
            'queue_prefix' => 'KS',
        ]);

        $clinic = Clinic::query()->firstOrFail();

        $response->assertRedirect(route('admin.clinic.edit', $clinic));
        $this->assertSame($clinic->id, $admin->refresh()->clinic_id);
        $this->assertSame($admin->id, $clinic->owner_user_id);
        $this->assertDatabaseHas('clinic_settings', [
            'clinic_id' => $clinic->id,
            'queue_prefix' => 'KS',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'clinic.created',
        ]);
    }

    public function test_clinic_admin_cannot_edit_another_clinic(): void
    {
        $admin = User::factory()->for(Clinic::factory())->create();
        $admin->assignRole('Clinic Admin');
        $otherClinic = Clinic::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.clinic.edit', $otherClinic))
            ->assertForbidden();
    }

    public function test_clinic_admin_can_remove_the_current_logo(): void
    {
        Storage::fake('public');
        $clinic = Clinic::factory()->create(['logo_path' => 'clinic-logos/old-logo.png']);
        ClinicSetting::factory()->for($clinic)->create();
        Storage::disk('public')->put($clinic->logo_path, 'logo');
        $admin = User::factory()->for($clinic)->create();
        $admin->assignRole('Clinic Admin');

        $this->actingAs($admin)
            ->get(route('admin.clinic.edit', $clinic))
            ->assertOk()
            ->assertSee('storage/clinic-logos/old-logo.png')
            ->assertSee('Hapus logo');

        $this->actingAs($admin)
            ->put(route('admin.clinic.update', $clinic), [
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'is_active' => true,
                'opening_time' => '08:00',
                'closing_time' => '17:00',
                'timezone' => 'Asia/Jakarta',
                'queue_prefix' => 'A',
                'remove_logo' => true,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($clinic->refresh()->logo_path);
        Storage::disk('public')->assertMissing('clinic-logos/old-logo.png');
    }
}
