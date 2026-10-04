<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public static function rolePermissions(): array
    {
        return [
            'admin manages clinic but has no automatic medical access' => [
                'Clinic Admin',
                ['clinic.update', 'doctor.create', 'receptionist.create', 'schedule.manage', 'revenue.view', 'audit_log.view'],
                ['medical_record.view', 'medical_record.create', 'medical_record.update', 'visit.start', 'visit.complete'],
            ],
            'doctor examines and calls but cannot configure clinic' => [
                'Doctor',
                ['queue.call', 'visit.start', 'visit.complete', 'medical_record.view', 'medical_record.create', 'medical_record.update'],
                ['clinic.update', 'doctor.create', 'receptionist.create', 'queue.manage', 'revenue.view', 'audit_log.view'],
            ],
            'receptionist manages administration but not medical records' => [
                'Receptionist',
                ['patient.create', 'patient.update', 'appointment.create', 'queue.manage', 'queue.call'],
                ['clinic.update', 'medical_record.view', 'medical_record.create', 'medical_record.update', 'visit.complete', 'audit_log.view'],
            ],
            'patient books but cannot manage other users or medical data' => [
                'Patient',
                ['dashboard.view', 'appointment.create', 'appointment.cancel', 'queue.view'],
                ['clinic.update', 'patient.create', 'patient.update', 'queue.call', 'queue.manage', 'medical_record.view', 'audit_log.view'],
            ],
        ];
    }

    #[DataProvider('rolePermissions')]
    public function test_roles_enforce_the_agreed_permission_boundaries(string $role, array $allowed, array $denied): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        foreach ($allowed as $permission) {
            $this->assertTrue($user->can($permission), $role.' should have '.$permission);
        }

        foreach ($denied as $permission) {
            $this->assertFalse($user->can($permission), $role.' should not have '.$permission);
        }
    }

    public function test_reseeding_removes_old_automatic_medical_access_without_creating_demo_users(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        Role::findByName('Clinic Admin')->givePermissionTo('medical_record.view');

        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertFalse(Role::findByName('Clinic Admin')->hasPermissionTo('medical_record.view'));
        $this->assertSame(4, Role::count());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_medical_access_can_be_granted_explicitly_without_changing_the_admin_role(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Clinic Admin');
        $user->givePermissionTo('medical_record.view');

        $this->assertTrue($user->can('medical_record.view'));
        $this->assertFalse($user->can('medical_record.update'));
        $this->assertFalse(Role::findByName('Clinic Admin')->hasPermissionTo('medical_record.view'));
    }

    public function test_production_seeding_does_not_create_known_demo_accounts(): void
    {
        $this->app->instance('env', 'production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertSame(4, Role::count());
    }
}
