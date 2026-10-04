<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function dashboards(): array
    {
        return [
            'admin' => ['Clinic Admin', '/admin/dashboard', 'Ringkasan klinik'],
            'doctor' => ['Doctor', '/doctor/dashboard', 'Dashboard Dokter'],
            'receptionist' => ['Receptionist', '/receptionist/dashboard', 'Dashboard Resepsionis'],
            'patient' => ['Patient', '/patient/dashboard', 'Dashboard Pasien'],
        ];
    }

    #[DataProvider('dashboards')]
    public function test_users_reach_their_own_dashboard(string $role, string $path, string $title): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/dashboard')->assertRedirect($path);
        $this->get($path)->assertSee($title)->assertSeeVolt('layout.navigation');
    }

    #[DataProvider('dashboards')]
    public function test_guests_are_redirected_to_login(string $role, string $path, string $title): void
    {
        $this->get($path)->assertRedirect('/login');
    }

    public static function forbiddenDashboards(): array
    {
        return [
            'admin cannot use doctor route' => ['Clinic Admin', '/doctor/dashboard'],
            'admin cannot use receptionist route' => ['Clinic Admin', '/receptionist/dashboard'],
            'admin cannot use patient route' => ['Clinic Admin', '/patient/dashboard'],
            'doctor cannot use admin route' => ['Doctor', '/admin/dashboard'],
            'doctor cannot use receptionist route' => ['Doctor', '/receptionist/dashboard'],
            'doctor cannot use patient route' => ['Doctor', '/patient/dashboard'],
            'receptionist cannot use admin route' => ['Receptionist', '/admin/dashboard'],
            'receptionist cannot use doctor route' => ['Receptionist', '/doctor/dashboard'],
            'receptionist cannot use patient route' => ['Receptionist', '/patient/dashboard'],
            'patient cannot use admin route' => ['Patient', '/admin/dashboard'],
            'patient cannot use doctor route' => ['Patient', '/doctor/dashboard'],
            'patient cannot use receptionist route' => ['Patient', '/receptionist/dashboard'],
        ];
    }

    #[DataProvider('forbiddenDashboards')]
    public function test_users_cannot_open_another_roles_dashboard(string $role, string $path): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get($path)->assertForbidden();
    }

    public function test_users_without_a_role_receive_an_access_message_without_a_redirect_loop(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Akun Anda belum memiliki role.');
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_multiple_roles_use_a_stable_dashboard_priority(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(['Patient', 'Receptionist', 'Doctor', 'Clinic Admin']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/admin/dashboard');
        $this->get('/doctor/dashboard')->assertSee('Dashboard Dokter');
    }

    public function test_unverified_users_must_verify_email_before_opening_a_dashboard(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->unverified()->create();
        $user->assignRole('Patient');

        $this->actingAs($user)->get('/patient/dashboard')->assertRedirect('/verify-email');
    }

    public function test_a_role_without_dashboard_permission_cannot_open_its_dashboard(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Doctor');
        $user->roles->first()->revokePermissionTo('dashboard.view');

        $this->actingAs($user)->get('/doctor/dashboard')->assertForbidden();
    }

    public function test_the_dashboard_escapes_the_users_name(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
