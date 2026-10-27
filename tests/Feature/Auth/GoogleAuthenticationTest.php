<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Volt\Volt;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_patient_authentication_pages_show_google_option(): void
    {
        $this->get(route('login'))->assertSee('Lanjutkan dengan Google');
        $this->get(route('register'))->assertSee('Daftar dengan Google');
    }

    public function test_patient_can_be_redirected_to_google(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.test/oauth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://accounts.google.test/oauth');
    }

    public function test_verified_google_account_creates_and_logs_in_a_patient(): void
    {
        $this->mockGoogleUser($this->googleUser());

        $this->withSession(['auth.google.terms_accepted_at' => now()->toIso8601String()])
            ->get(route('auth.google.callback'))
            ->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'patient@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame(UserAccountType::Patient, $user->account_type);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(['Patient'], $user->getRoleNames()->all());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
    }

    public function test_google_account_links_an_existing_patient_by_verified_email(): void
    {
        $patient = $this->patient(['email' => 'patient@example.test', 'email_verified_at' => null]);
        $this->mockGoogleUser($this->googleUser());

        $this->get(route('auth.google.callback'))->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($patient);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('google-123', $patient->fresh()->google_id);
        $this->assertTrue($patient->fresh()->hasVerifiedEmail());
    }

    public function test_google_callback_returns_to_the_intended_page(): void
    {
        $this->mockGoogleUser($this->googleUser());

        $this->withSession([
            'url.intended' => '/patient/dashboard',
            'auth.google.terms_accepted_at' => now()->toIso8601String(),
        ])
            ->get(route('auth.google.callback'))
            ->assertRedirect('/patient/dashboard');
    }

    public function test_google_registration_requires_terms_for_a_new_patient(): void
    {
        Volt::test('pages.auth.register')
            ->call('registerWithGoogle')
            ->assertHasErrors('accept_terms')
            ->assertNoRedirect()
            ->set('accept_terms', true)
            ->call('registerWithGoogle')
            ->assertHasNoErrors()
            ->assertRedirect(route('auth.google.redirect', ['register' => 1]));

        $this->app['session']->forget('auth.google.terms_accepted_at');
        $this->mockGoogleUser($this->googleUser());
        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('accept_terms');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_login_rejects_non_patient_accounts(): void
    {
        $user = User::factory()->create([
            'account_type' => UserAccountType::ClinicOwner,
            'email' => 'patient@example.test',
        ]);
        $user->assignRole('Clinic Admin');
        $this->mockGoogleUser($this->googleUser());

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_google_login_rejects_inactive_patients(): void
    {
        $patient = $this->patient(['email' => 'patient@example.test', 'is_active' => false]);
        $this->mockGoogleUser($this->googleUser());

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($patient->fresh()->google_id);
    }

    public function test_google_login_rejects_an_unverified_email(): void
    {
        $this->mockGoogleUser($this->googleUser(['email_verified' => false]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_login_rejects_conflicting_accounts(): void
    {
        $this->patient(['google_id' => 'google-123']);
        $emailAccount = $this->patient(['email' => 'patient@example.test']);
        $this->mockGoogleUser($this->googleUser());

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($emailAccount->fresh()->google_id);
    }

    public function test_google_provider_failure_does_not_create_a_user(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('Provider unavailable'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** @param array<string, mixed> $attributes */
    private function patient(array $attributes = []): User
    {
        $patient = User::factory()->create([
            'account_type' => UserAccountType::Patient,
            ...$attributes,
        ]);
        $patient->assignRole('Patient');

        return $patient;
    }

    /** @param array<string, mixed> $attributes */
    private function googleUser(array $attributes = []): GoogleUser
    {
        return GoogleUser::fake([
            'id' => 'google-123',
            'name' => 'Google Patient',
            'email' => 'patient@example.test',
            'email_verified' => true,
            ...$attributes,
        ]);
    }

    private function mockGoogleUser(GoogleUser $googleUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
