<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_filter_only_their_clinics_safe_audit_data(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->forClinic($clinic)->create();
        $admin->assignRole('Clinic Admin');
        AuditLog::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $admin->id,
            'actor_name' => 'Admin Sendiri',
            'action' => 'visit.completed',
            'metadata' => ['visit_id' => 12, 'subjective' => 'Tidak boleh tampil'],
        ]);
        AuditLog::factory()->create([
            'clinic_id' => Clinic::factory(),
            'actor_name' => 'Admin Klinik Lain',
            'action' => 'visit.completed',
        ]);

        $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'action' => 'visit.completed',
            'from' => now('Asia/Jakarta')->toDateString(),
            'to' => now('Asia/Jakarta')->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Admin Sendiri')
            ->assertSee('visit_id: 12')
            ->assertDontSee('Tidak boleh tampil')
            ->assertDontSee('Admin Klinik Lain');
    }

    public function test_non_admin_cannot_open_audit_viewer(): void
    {
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->get(route('admin.audit-logs.index'))->assertForbidden();
    }
}
