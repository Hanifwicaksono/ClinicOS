<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_users_cannot_start_a_session_even_with_remember_me(): void
    {
        $user = User::factory()->inactive()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->set('form.remember', true)
            ->call('login')
            ->assertHasErrors('form.email')
            ->assertNoRedirect();

        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.login']);
        $this->assertSame($user->remember_token, $user->fresh()->remember_token);
    }

    public function test_an_existing_session_is_invalidated_when_the_account_is_deactivated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->withSession(['private_marker' => 'remove-on-logout'])
            ->get('/profile')
            ->assertRedirect('/login')
            ->assertSessionMissing('private_marker')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.logout']);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('login')->assertHasErrors('form.email');
        }

        $component->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email')
            ->assertSee('Terlalu banyak percobaan login.')
            ->assertNoRedirect();

        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.login']);

        $this->travel(61)->seconds();
        $component->call('login')->assertHasNoErrors()->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_errors_do_not_create_a_user_or_a_login_audit(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Test Patient')
            ->set('email', 'invalid-email')
            ->set('password', 'password')
            ->set('password_confirmation', 'different-password')
            ->call('register')
            ->assertHasErrors(['email', 'password'])
            ->assertNoRedirect();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
