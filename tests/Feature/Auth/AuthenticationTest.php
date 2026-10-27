<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $user->assignRole('Patient');

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $user->assignRole('Patient');

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_admin_login_accepts_staff_and_hides_patient_google_login(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee('Masuk pengelola klinik')
            ->assertDontSee('Lanjutkan dengan Google');

        $staff = User::factory()->create();
        $staff->assignRole('Receptionist');

        Volt::test('pages.auth.login', ['adminLogin' => true])
            ->set('form.email', $staff->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_login_portals_reject_accounts_from_the_other_group(): void
    {
        $patient = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $patient->assignRole('Patient');
        $staff = User::factory()->create();
        $staff->assignRole('Doctor');

        Volt::test('pages.auth.login')
            ->set('form.email', $staff->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email');
        $this->assertGuest();

        Volt::test('pages.auth.login', ['adminLogin' => true])
            ->set('form.email', $patient->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email');
        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.login']);
    }

    public function test_owner_and_platform_admin_can_use_admin_login(): void
    {
        foreach ([User::factory()->clinicOwnerCandidate()->create(), User::factory()->platformAdmin()->create()] as $user) {
            Volt::test('pages.auth.login', ['adminLogin' => true])
                ->set('form.email', $user->email)
                ->set('form.password', 'password')
                ->call('login')
                ->assertHasNoErrors();

            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
