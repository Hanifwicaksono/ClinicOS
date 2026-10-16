<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_platform_admin_can_view_nonmedical_tenant_dashboard_and_detail(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Klinik Aman']);
        $owner = User::factory()->clinicOwner($clinic)->create([
            'name' => 'Pemilik Klinik',
            'email' => 'owner@example.test',
        ]);
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)->get('/dashboard')->assertRedirect(route('platform.dashboard'));
        $this->get(route('platform.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard platform')
            ->assertSee('1');
        $this->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertSee('Klinik Aman')
            ->assertSee('owner@example.test');
        $this->get(route('platform.tenants.show', $clinic))
            ->assertOk()
            ->assertSee('Profil nonmedis')
            ->assertSee($owner->name)
            ->assertDontSee('Rekam medis');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $platformAdmin->id,
            'action' => 'platform.tenant.viewed',
            'entity_type' => Clinic::class,
            'entity_id' => $clinic->id,
        ]);
    }

    public function test_platform_admin_cannot_open_tenant_or_medical_routes(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('doctor.medical-records.index'))->assertForbidden();
        $this->assertFalse($platformAdmin->can('medical_record.view'));
        $this->assertNull($platformAdmin->clinic_id);
    }

    public function test_tenant_users_cannot_open_platform_routes(): void
    {
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();

        $this->actingAs($owner)->get(route('platform.dashboard'))->assertForbidden();
        $this->get(route('platform.tenants.index'))->assertForbidden();
        $this->get(route('platform.tenants.show', $clinic))->assertForbidden();
    }

    public function test_clinic_owner_is_explicit(): void
    {
        $owner = User::factory()->create();
        $clinic = Clinic::factory()->ownedBy($owner)->create();

        $this->assertTrue($clinic->owner->is($owner));
        $this->assertTrue($owner->refresh()->ownedClinic->is($clinic));
        $this->assertSame($clinic->id, $owner->clinic_id);
    }
}
