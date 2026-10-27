<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true);

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame(['Patient'], $user->getRoleNames()->all());
        $this->assertSame(UserAccountType::Patient, $user->account_type);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertFalse($user->can('clinic.update'));
        $this->assertFalse($user->can('medical_record.view'));
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/dashboard')->assertRedirect('/verify-email');
    }

    public function test_registration_returns_to_the_intended_page(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        Notification::fake();
        $this->withSession(['url.intended' => '/patient/dashboard']);

        Volt::test('pages.auth.register')
            ->set('name', 'Test Patient')
            ->set('email', 'patient@example.test')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->call('register')
            ->assertRedirect('/patient/dashboard');
    }

    public function test_patient_must_accept_terms_to_register(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        Volt::test('pages.auth.register')
            ->set('name', 'Test Patient')
            ->set('email', 'patient@example.test')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors('accept_terms')
            ->assertNoRedirect();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
