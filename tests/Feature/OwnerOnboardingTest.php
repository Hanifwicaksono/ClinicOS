<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Payment;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\SubscriptionStatus;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OwnerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    public function test_owner_registration_is_separate_and_requires_email_verification(): void
    {
        Notification::fake();

        $this->get(route('register.clinic'))->assertOk()->assertSeeVolt('pages.auth.register-clinic');
        $component = Volt::test('pages.auth.register-clinic')
            ->set('name', 'Pemilik Klinik')
            ->set('email', 'owner@baru.test')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));
        $owner = User::query()->where('email', 'owner@baru.test')->firstOrFail();
        $this->assertSame(UserAccountType::ClinicOwner, $owner->account_type);
        $this->assertTrue($owner->hasRole('Clinic Admin'));
        $this->assertNull($owner->clinic_id);
        $this->assertNotNull($owner->terms_accepted_at);
        Notification::assertSentTo($owner, VerifyEmail::class);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));

        $owner->markEmailAsVerified();
        $this->actingAs($owner->fresh())->get(route('dashboard'))
            ->assertRedirect(route('onboarding.clinic.create'));
    }

    public function test_owner_must_accept_terms_to_register(): void
    {
        Volt::test('pages.auth.register-clinic')
            ->set('name', 'Pemilik Klinik')
            ->set('email', 'owner@baru.test')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors('accept_terms')
            ->assertNoRedirect();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_owner_creates_exactly_one_clinic_and_resumes_at_plan_selection(): void
    {
        $owner = User::factory()->clinicOwnerCandidate()->create();
        $payload = $this->clinicPayload();

        $this->actingAs($owner)->get(route('profile'))->assertOk();
        $this->get(route('notifications.index'))->assertRedirect(route('onboarding.clinic.create'));
        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertRedirect(route('onboarding.clinic.create'));
        $this->get(route('onboarding.clinic.create'))->assertOk()->assertSee('Daftarkan klinik');
        $this->post(route('onboarding.clinic.store'), $payload)
            ->assertRedirect(route('onboarding.plans.index'));

        $clinic = Clinic::query()->firstOrFail();
        $this->assertSame($owner->id, $clinic->owner_user_id);
        $this->assertSame($clinic->id, $owner->refresh()->clinic_id);
        $this->assertDatabaseHas('clinic_settings', ['clinic_id' => $clinic->id, 'timezone' => 'Asia/Jakarta']);
        $this->assertDatabaseHas('audit_logs', ['clinic_id' => $clinic->id, 'action' => 'clinic.created']);

        $this->post(route('onboarding.clinic.store'), $payload)
            ->assertRedirect(route('onboarding.plans.index'));
        $this->assertDatabaseCount('clinics', 1);
        $this->get(route('onboarding.clinic.create'))->assertRedirect(route('onboarding.plans.index'));
        $this->get(route('onboarding.plans.index'))
            ->assertOk()
            ->assertSee('Rp 149.000')
            ->assertSee('Rp 4.999.000')
            ->assertSee('Akun tak terbatas');
    }

    public function test_invalid_clinic_data_does_not_create_a_partial_tenant(): void
    {
        $owner = User::factory()->clinicOwnerCandidate()->create();
        $payload = $this->clinicPayload();
        $payload['closing_time'] = '07:00';

        $this->actingAs($owner)->post(route('onboarding.clinic.store'), $payload)
            ->assertSessionHasErrors('closing_time');

        $this->assertDatabaseCount('clinics', 0);
        $this->assertNull($owner->refresh()->clinic_id);
    }

    public function test_free_activation_is_idempotent_and_unlocks_tenant_routes(): void
    {
        [$owner, $clinic] = $this->ownerWithClinic();

        $this->actingAs($owner)->post(route('onboarding.plans.free'))->assertRedirect(route('dashboard'));
        $this->assertSame(SubscriptionStatus::Free, $clinic->subscription->status);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'subscription.activated',
        ]);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->post(route('onboarding.plans.free'))->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_trial_activation_is_idempotent_and_creates_one_period(): void
    {
        [$owner, $clinic] = $this->ownerWithClinic();

        $this->actingAs($owner)->post(route('onboarding.plans.trial'))->assertRedirect(route('dashboard'));
        $this->assertSame(SubscriptionStatus::Trialing, $clinic->subscription->status);
        $this->assertSame('team', $clinic->subscription->plan->code);
        $this->post(route('onboarding.plans.trial'))->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_periods', 1);
    }

    public function test_paid_checkout_creates_pending_payment_but_never_activates_from_redirect(): void
    {
        [$owner, $clinic] = $this->ownerWithClinic();
        $price = SubscriptionPrice::query()
            ->whereRelation('plan', 'code', 'team')
            ->where('duration_months', 12)
            ->firstOrFail();
        config([
            'services.doku.client_id' => 'MCH-TEST',
            'services.doku.secret_key' => 'secret',
            'services.doku.base_url' => 'https://api-sandbox.doku.com',
        ]);
        Http::fake([
            'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
                'response' => [
                    'payment' => [
                        'url' => 'https://sandbox.doku.com/checkout-link-v2/test',
                        'expired_date' => '20261015235959',
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($owner)->post(route('onboarding.plans.paid', $price));
        $payment = Payment::query()->firstOrFail();

        $response->assertRedirect(route('subscription.payments.show', $payment));
        $this->get(route('subscription.payments.show', $payment))->assertOk()->assertSee('Rp 4.999.000');

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'payment.created',
        ]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('onboarding.plans.index'));
    }

    public function test_non_owner_cannot_open_owner_onboarding(): void
    {
        $patient = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $patient->assignRole('Patient');

        $this->actingAs($patient)->get(route('onboarding.clinic.create'))->assertForbidden();
        $this->get(route('onboarding.plans.index'))->assertForbidden();
    }

    /** @return array{User, Clinic} */
    private function ownerWithClinic(): array
    {
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();
        $clinic->settings()->create([
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'timezone' => 'Asia/Jakarta',
            'queue_prefix' => 'A',
        ]);

        return [$owner, $clinic];
    }

    /** @return array<string, mixed> */
    private function clinicPayload(): array
    {
        return [
            'name' => 'Klinik Baru',
            'slug' => 'klinik-baru',
            'address' => 'Jl. Sehat No. 1',
            'phone' => '0215550000',
            'email' => 'halo@klinik-baru.test',
            'description' => 'Klinik keluarga.',
            'is_active' => true,
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'timezone' => 'Asia/Jakarta',
            'queue_prefix' => 'KB',
        ];
    }
}
