<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_records_the_actor_and_only_safe_metadata(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('audit_logs', 1);
        $log = AuditLog::sole();
        $this->assertSame('auth.login', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($user->name, $log->actor_name);
        $this->assertSame(User::class, $log->entity_type);
        $this->assertSame($user->id, $log->entity_id);
        $this->assertSame(['guard' => 'web'], $log->metadata);
        $this->assertSame(now()->toDateTimeString(), $log->created_at->toDateTimeString());
        $this->assertNotNull($log->ip_address);
        $this->assertNull($log->clinic_id);
    }

    public function test_failed_login_does_not_record_a_successful_login(): void
    {
        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertGuest();
    }

    public function test_logout_is_recorded_before_the_session_is_invalidated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('layout.navigation')->call('logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'action' => 'auth.logout',
            'entity_id' => $user->id,
        ]);
    }

    public function test_audit_history_is_retained_when_an_account_is_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertRedirect('/');

        $this->assertModelMissing($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'actor_name' => $user->name,
            'entity_id' => $user->id,
            'action' => 'auth.logout',
        ]);
    }
}
