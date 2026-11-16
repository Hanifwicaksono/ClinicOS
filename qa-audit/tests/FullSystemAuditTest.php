<?php

namespace Tests\Feature;

use App\Models\AddonPrice;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\Models\Visit;
use App\PaymentStatus;
use App\Services\AuditLogger;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use Database\Seeders\AddonPriceSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class FullSystemAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
        Http::preventStrayRequests();
    }

    public function test_schedule_edit_preserves_the_doctor_of_existing_bookings(): void
    {
        $visit = $this->visit();
        $admin = User::factory()->forClinic($visit->clinic)->create();
        $admin->assignRole('Clinic Admin');
        $replacementUser = User::factory()->forClinic($visit->clinic)->create();
        $replacementUser->assignRole('Doctor');
        $replacement = Doctor::factory()->for($visit->clinic)->for($replacementUser, 'user')->create();
        $schedule = $visit->appointment->schedule;
        $this->actingAs($admin)->putJson(route('admin.doctor-schedules.update', $schedule), [
            'doctor_id' => $replacement->id,
            'day_of_week' => $schedule->day_of_week,
            'start_time' => '00:00', 'end_time' => '23:59', 'quota' => 10, 'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('doctor_id');

        $this->assertSame($visit->appointment->doctor_id, $schedule->refresh()->doctor_id,
            'Editing a booked session must not leave appointments assigned to a different doctor.');
    }

    public function test_final_record_photo_remains_available_in_revision_history(): void
    {
        Storage::fake('local');
        $visit = $this->visit();
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.complete', $visit), [
            ...$this->payload($visit),
            'photos' => [UploadedFile::fake()->image('audit-pixel.png', 1, 1)],
        ])->assertOk();
        $photo = $visit->medicalRecord->refresh()->photos[0]['path'];
        $this->putJson(route('doctor.visits.correct', $visit), [
            ...$this->payload($visit),
            'correction_reason' => 'Synthetic audit correction reason', 'removed_photos' => [0],
        ])->assertOk();
        $revision = $visit->medicalRecord->revisions()->sole();
        $this->assertSame($photo, $revision->before_data['record']['photos'][0]['path']);
        $this->assertTrue(Storage::disk('local')->exists($photo),
            'An attachment referenced by a final medical revision must remain available.');
    }

    public function test_failed_draft_transaction_does_not_leave_an_unreferenced_upload(): void
    {
        Storage::fake('local');
        $visit = $this->visit();
        $this->mock(AuditLogger::class)->shouldReceive('recordForClinic')->once()
            ->andThrow(new RuntimeException('Simulated audit storage failure'));
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.save', $visit), [
            ...$this->payload($visit),
            'photos' => [UploadedFile::fake()->image('audit-pixel.png', 1, 1)],
        ])->assertStatus(500);
        $this->assertEmpty($visit->medicalRecord->refresh()->photos);
        $this->assertSame(0, count(Storage::disk('local')->allFiles()),
            'A rolled-back draft must not leave its uploaded attachment on disk.');
    }

    public function test_clinic_owner_cannot_delete_the_only_owner_account(): void
    {
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();
        $this->actingAs($owner);
        Volt::test('profile.delete-user-form')->set('password', 'password')->call('deleteUser');
        $this->assertTrue(User::query()->whereKey($owner->id)->exists(),
            'Owner deletion needs a supported transfer or closure flow before it can orphan a clinic.');
        $this->assertSame($owner->id, $clinic->refresh()->owner_user_id);
    }

    public function test_owner_without_a_clinic_is_guided_to_onboarding_from_addons(): void
    {
        $owner = User::factory()->clinicOwnerCandidate()->create();
        $this->actingAs($owner)->get(route('subscription.addons.index'))
            ->assertRedirect(route('onboarding.clinic.create'));
    }

    public function test_final_correction_requires_a_version_to_prevent_stale_overwrites(): void
    {
        $visit = $this->visit();
        $this->actingAs($visit->doctor->user)
            ->putJson(route('doctor.visits.complete', $visit), $this->payload($visit))->assertOk();
        $payload = $this->payload($visit);
        unset($payload['record_version']);
        $payload['correction_reason'] = 'Synthetic audit correction reason';
        $this->putJson(route('doctor.visits.correct', $visit), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('record_version');
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function malformedNestedFields(): array
    {
        return [
            'nullable diagnoses' => [['diagnoses' => null]],
            'nullable treatments' => [['treatments' => null]],
            'scalar diagnosis item' => [['diagnoses' => ['invalid']]],
            'scalar treatment item' => [['treatments' => ['invalid']]],
            'scalar prescription item' => [['prescription_items' => ['invalid']]],
        ];
    }

    /** @param array<string, mixed> $fields */
    #[DataProvider('malformedNestedFields')]
    public function test_nested_fields_are_handled_without_a_server_error(array $fields): void
    {
        $visit = $this->visit();
        $response = $this->actingAs($visit->doctor->user)
            ->putJson(route('doctor.visits.save', $visit), [...$this->payload($visit), ...$fields]);
        $this->assertContains($response->status(), [200, 422],
            'A nullable field or malformed nested row must be accepted safely or rejected by validation.');
    }

    /** @return array<string, array{string, array<string, bool>}> */
    public static function operationalAccess(): array
    {
        $routes = [
            'admin.staff.index', 'admin.services.index', 'admin.doctor-schedules.index', 'admin.audit-logs.index',
            'receptionist.patients.index', 'receptionist.appointments.index', 'receptionist.queues.index',
            'doctor.queues.index', 'doctor.medical-records.index', 'patient.appointments.index',
            'platform.tenants.index', 'platform.subscription-plans.index', 'platform.payments.index',
        ];
        $allowed = [
            'Clinic Admin' => array_slice($routes, 0, 7), 'Receptionist' => array_slice($routes, 4, 3),
            'Doctor' => array_slice($routes, 7, 2), 'Patient' => [$routes[9]],
            'Platform Admin' => array_slice($routes, 10, 3),
        ];

        return collect($allowed)->mapWithKeys(fn (array $grants, string $role): array => [
            $role => [$role, array_fill_keys($grants, true) + array_fill_keys($routes, false)],
        ])->all();
    }

    /** @param array<string, bool> $routes */
    #[DataProvider('operationalAccess')]
    public function test_each_role_is_checked_at_operational_endpoints(string $role, array $routes): void
    {
        $clinic = Clinic::factory()->create();
        $actor = User::factory()->forClinic($clinic)->create();
        $actor->assignRole($role);
        if ($role === 'Doctor') {
            Doctor::factory()->for($clinic)->for($actor, 'user')->create();
        }
        $this->actingAs($actor);
        foreach ($routes as $route => $allowed) {
            $response = $this->get(route($route));
            $this->assertSame($allowed ? 200 : 403, $response->status(), "$role: $route");
        }
    }

    public function test_admin_without_a_clinic_can_open_the_setup_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Clinic Admin');
        $this->actingAs($admin)->get(route('admin.clinic.index'))
            ->assertRedirect(route('admin.clinic.create'));
        $this->get(route('admin.clinic.create'))->assertOk();
    }

    public function test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit(): void
    {
        $visit = $this->visit();
        $admin = User::factory()->forClinic($visit->clinic)->create();
        $admin->assignRole('Clinic Admin');
        $originalPrice = $visit->service_price;
        $this->actingAs($admin)->get(route('admin.clinic.index'))
            ->assertRedirect(route('admin.clinic.edit', $visit->clinic));
        $this->get(route('admin.doctor-schedules.create'))->assertOk();
        $this->get(route('admin.doctor-schedules.edit', $visit->appointment->schedule))->assertOk();
        $this->get(route('admin.services.create'))->assertOk();
        $this->put(route('admin.services.update', $visit->service), [
            'name' => 'Synthetic audit service', 'price' => 85000,
            'duration_minutes' => 45, 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('85000.00', $visit->service->refresh()->price);
        $this->assertSame($originalPrice, $visit->refresh()->service_price);
        $this->get(route('receptionist.patients.edit', $visit->patient))->assertOk();
        $this->put(route('receptionist.patients.update', $visit->patient), [
            'name' => 'Synthetic audit patient', 'birth_date' => '1990-01-01',
            'gender' => 'FEMALE', 'phone' => '081299900001', 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Synthetic audit patient', $visit->patient->refresh()->name);
    }

    public function test_read_all_notifications_does_not_mark_another_accounts_notifications(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$actor, $other] as $user) {
            $user->notifications()->create([
                'id' => Str::uuid()->toString(), 'type' => 'audit-fixture',
                'data' => ['title' => 'Synthetic notification', 'url' => '/notifications'],
            ]);
        }
        $this->actingAs($actor)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $actor->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_platform_commercial_actions_preserve_invoice_and_owner_history(): void
    {
        $this->seed(AddonPriceSeeder::class);
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'individual')
            ->where('duration_months', 1)->firstOrFail();
        $payment = Payment::factory()->for($clinic)->create([
            'subscription_price_id' => $price->id, 'plan_code' => 'individual',
            'plan_name' => 'Individual', 'duration_months' => 1, 'amount' => $price->amount,
        ]);
        $this->actingAs($owner)->get(route('subscription.payments.index'))->assertOk();
        $platform = User::factory()->platformAdmin()->create();
        $this->actingAs($platform)->get(route('platform.payments.show', $payment))->assertOk();
        $addon = AddonPrice::query()->where('code', 'odontogram')->sole();
        $this->patch(route('platform.addon-prices.update', $addon), [
            'monthly_amount' => 99001, 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(99001, $addon->refresh()->monthly_amount);
        $this->assertSame($price->amount, $payment->refresh()->amount);
        config([
            'services.doku.base_url' => 'https://audit.invalid',
            'services.doku.client_id' => 'audit-fixture',
            'services.doku.secret_key' => str_repeat('x', 32),
        ]);
        Http::fake(['https://audit.invalid/orders/v1/status/*' => Http::response([
            'order' => ['invoice_number' => $payment->invoice_number, 'amount' => $payment->amount],
            'customer' => ['id' => 'clinic-'.$clinic->id],
            'transaction' => ['status' => 'PENDING', 'original_request_id' => $payment->request_id],
        ])]);
        $this->post(route('platform.payments.reconcile', $payment))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame('ignored_non_final', $payment->events()->sole()->result);
        $this->assertSame($owner->id, $clinic->refresh()->owner_user_id);
        Http::assertSentCount(1);
    }

    private function visit(): Visit
    {
        $clinic = Clinic::factory()->create();
        $actor = User::factory()->forClinic($clinic)->create();
        $actor->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($actor, 'user')->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59',
        ]);
        $patient = Patient::factory()->for($clinic)->create();
        $appointment = app(BookingService::class)->book($clinic, [
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id, 'appointment_date' => now('Asia/Jakarta')->toDateString(),
            'idempotency_key' => Str::uuid()->toString(),
        ], $actor)['appointment'];
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        app(QueueWorkflowService::class)->checkIn($appointment->queue, $receptionist);
        app(QueueWorkflowService::class)->call($appointment->queue, $actor);
        $this->actingAs($actor)->postJson(route('doctor.visits.start', $appointment->queue))->assertOk();

        return Visit::query()->where('appointment_id', $appointment->id)->sole();
    }

    /** @return array<string, mixed> */
    private function payload(Visit $visit): array
    {
        return [
            'record_version' => $visit->medicalRecord->refresh()->lock_version,
            'allergy_version' => $visit->patient->allergyVersion(),
            'subjective' => 'Synthetic audit entry', 'assessment' => 'Synthetic audit entry', 'plan' => 'Synthetic audit entry',
        ];
    }
}
