<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use App\Services\PatientDuplicateService;
use App\Services\SubscriptionService;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoverageClosureRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-11-14T02:00:00Z'));
    }

    public function test_new_booking_obeys_changed_hours_without_rewriting_history_or_replay(): void
    {
        $c = $this->context();
        $data = $this->bookingData($c);
        $first = app(BookingService::class)->book($c['clinic'], $data, $c['receptionist'], 'WALK_IN');
        $appointment = $first['appointment']->fresh()->getRawOriginal();
        $queue = $first['appointment']->queue()->first()->getRawOriginal();
        $c['clinic']->settings()->update(['closing_time' => '10:00']);
        $this->actingAs($c['receptionist'])->postJson(route('receptionist.appointments.store'), [...$data, 'idempotency_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('doctor_schedule_id');
        $this->assertSame($appointment, $first['appointment']->fresh()->getRawOriginal());
        $this->assertSame($queue, $first['appointment']->queue()->first()->getRawOriginal());
        $replay = app(BookingService::class)->book($c['clinic'], $data, $c['receptionist'], 'WALK_IN');
        $this->assertFalse($replay['created']);
        $this->assertSame($first['appointment']->id, $replay['appointment']->id);
        $this->assertDatabaseCount('appointments', 1);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function hours(): array
    {
        return ['inclusive' => ['12:00', '18:00', true], 'opening' => ['13:00', '20:00', false], 'closing' => ['08:00', '17:00', false]];
    }

    #[DataProvider('hours')]
    public function test_booking_boundaries_match_current_hours(string $opening, string $closing, bool $allowed): void
    {
        $c = $this->context();
        $c['clinic']->settings()->update(['opening_time' => $opening, 'closing_time' => $closing]);
        $response = $this->actingAs($c['receptionist'])->postJson(route('receptionist.appointments.store'), $this->bookingData($c));
        if ($allowed) {
            $response->assertRedirect();
            $this->assertDatabaseCount('appointments', 1);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('doctor_schedule_id');
            $this->assertDatabaseCount('appointments', 0);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function phones(): array
    {
        return ['dots' => ['0812.8888.9999', '081288889999'], 'ID' => ['+62 (812) 8888-9999', '+6281288889999'], 'US' => ['+1 (202) 555-0123', '+12025550123'], 'whitespace' => ["0812\t8888\n9999", '081288889999']];
    }

    #[DataProvider('phones')]
    public function test_phone_validation_storage_and_legacy_candidates_share_normalization(string $formatted, string $canonical): void
    {
        $c = $this->context();
        $this->actingAs($c['receptionist'])->post(route('receptionist.patients.store'), ['name' => 'Normalisasi telepon', 'birth_date' => '1992-02-02', 'gender' => 'MALE', 'phone' => $formatted, 'is_active' => true])->assertSessionHasNoErrors()->assertRedirect();
        $patient = Patient::query()->where('name', 'Normalisasi telepon')->firstOrFail();
        $this->assertSame($canonical, $patient->phone);
        $duplicates = app(PatientDuplicateService::class);
        $this->assertTrue($duplicates->candidates($c['clinic']->id, ['phone' => $formatted])->contains('id', $patient->id));
        DB::table('patients')->where('id', $patient->id)->update(['phone' => $formatted]);
        $this->assertTrue($duplicates->candidates($c['clinic']->id, ['phone' => $canonical])->contains('id', $patient->id));
        $this->assertSame($formatted, $patient->fresh()->phone);
        $this->assertTrue($duplicates->candidates(Clinic::factory()->create()->id, ['phone' => $canonical])->isEmpty());
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['admin' => ['Clinic Admin'], 'receptionist' => ['Receptionist']];
    }

    #[DataProvider('roles')]
    public function test_inactive_patient_keeps_context_and_has_recovery(string $role): void
    {
        $c = $this->context();
        $c['patient']->update(['is_active' => false]);
        $this->actingAs($role === 'Clinic Admin' ? $c['owner'] : $c['receptionist'])
            ->get(route('receptionist.appointments.create', ['patient_id' => $c['patient']->id]))->assertRedirect(route('receptionist.patients.show', $c['patient']));
        $this->get(route('receptionist.patients.show', $c['patient']))->assertOk()->assertSee('Pasien nonaktif')->assertSee('Aktifkan kembali melalui Edit pasien')
            ->assertSee(route('receptionist.patients.edit', $c['patient']), false)->assertDontSee('Buat booking');
        $this->postJson(route('receptionist.appointments.store'), $this->bookingData($c))->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $c['patient']->update(['is_active' => true]);
        $this->get(route('receptionist.appointments.create', ['patient_id' => $c['patient']->id]))->assertOk()->assertViewHas('selectedPatient', fn (Patient $p): bool => $p->is($c['patient']));
    }

    public function test_foreign_or_missing_patient_never_falls_back_to_new_patient(): void
    {
        $c = $this->context();
        $foreign = Patient::factory()->for(Clinic::factory())->create();
        foreach ([$foreign->id, 999999] as $id) {
            $this->actingAs($c['receptionist'])->get(route('receptionist.appointments.create', ['patient_id' => $id]))->assertNotFound();
        }
    }

    /** @return array<string, array{string}> */
    public static function subscriptionStates(): array
    {
        return ['expired' => ['expired'], 'trial' => ['trial'], 'scheduled' => ['scheduled'], 'future' => ['future'], 'suspended' => ['suspended']];
    }

    #[DataProvider('subscriptionStates')]
    public function test_platform_reads_effective_subscription_without_changing_history(string $state): void
    {
        $c = $this->context();
        $platform = User::factory()->platformAdmin()->create();
        $team = SubscriptionPlan::query()->where('code', 'team')->firstOrFail();
        $individual = SubscriptionPlan::query()->where('code', 'individual')->firstOrFail();
        $subscription = $c['clinic']->subscription;
        $subscription->update(['subscription_plan_id' => $team->id, 'status' => $state === 'suspended' ? SubscriptionStatus::Suspended : SubscriptionStatus::Active, 'source' => 'payment', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subSecond()]);
        $payment = Payment::factory()->for($c['clinic'])->create();
        SubscriptionPeriod::factory()->for($subscription)->for($c['clinic'])->create(['subscription_plan_id' => $team->id, 'plan_code' => 'team', 'plan_name' => 'Team', 'source' => $state === 'trial' ? 'trial' : 'payment', 'payment_id' => $payment->id, 'starts_at' => now()->subMonth(), 'ends_at' => now()]);
        if (in_array($state, ['scheduled', 'future'], true)) {
            SubscriptionPeriod::factory()->for($subscription)->for($c['clinic'])->create(['subscription_plan_id' => $individual->id, 'plan_code' => 'individual', 'plan_name' => 'Individual', 'source' => 'manual_grant', 'starts_at' => $state === 'scheduled' ? now() : now()->addDay(), 'ends_at' => now()->addMonth()]);
        }
        $periods = SubscriptionPeriod::query()->where('clinic_id', $c['clinic']->id)->orderBy('id')->get()->toArray();
        $paymentData = $payment->fresh()->getRawOriginal();
        $expected = match ($state) {
            'trial' => 'free', 'scheduled' => 'active', 'suspended' => 'suspended', default => 'expired'
        };
        $response = $this->actingAs($platform)->get(route('platform.tenants.index', ['search' => $c['clinic']->slug]));
        $response->assertOk()->assertViewHas('tenants', fn ($tenants): bool => $tenants->first()->subscription->status->value === $expected);
        if ($state === 'scheduled') {
            $response->assertSee('Individual');
        }
        $this->get(route('platform.dashboard'))->assertOk()->assertViewHas('subscriptionCounts', fn ($counts): bool => (int) ($counts[$expected] ?? 0) === 1);
        $this->assertSame($periods, SubscriptionPeriod::query()->where('clinic_id', $c['clinic']->id)->orderBy('id')->get()->toArray());
        $this->assertSame($paymentData, $payment->fresh()->getRawOriginal());
    }

    /** @return array<string, array{string}> */
    public static function prices(): array
    {
        return ['nonmultiple' => ['123456'], 'fraction' => ['123.45'], 'maximum' => ['9999999999.99']];
    }

    #[DataProvider('prices')]
    public function test_service_ui_and_server_share_numeric_price_contract(string $price): void
    {
        $c = $this->context();
        $this->actingAs($c['owner'])->get(route('admin.services.create'))->assertOk()->assertSee('step="any"', false)->assertSee('max="9999999999.99"', false);
        $data = ['name' => 'Tarif CCL', 'price' => $price, 'duration_minutes' => 20, 'is_active' => true];
        $this->post(route('admin.services.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $service = Service::query()->where('name', 'Tarif CCL')->firstOrFail();
        $this->assertSame($price === '123456' ? '123456.00' : $price, $service->price);
        $this->put(route('admin.services.update', $service), $data)->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_empty_catalog_has_explanation_and_recovery_without_checkout(): void
    {
        $c = $this->context();
        SubscriptionPlan::query()->update(['is_active' => false]);
        $this->actingAs($c['owner'])->get(route('onboarding.plans.index'))->assertOk()->assertSee('Belum ada paket tersedia')->assertSee('Muat ulang ketersediaan paket')->assertDontSee('action="'.route('onboarding.plans.free').'"', false);
    }

    public function test_expired_editor_exposes_login_recovery_without_losing_data(): void
    {
        $this->blade('<x-rme.autosave-status state="expired" />')->assertSee('Sesi berakhir')->assertSee('Salin perubahan Anda')->assertSee('Masuk kembali')->assertSee('target="_blank"', false)->assertSee('href="'.route('admin.login').'"', false);
        $c = $this->context();
        $booking = app(BookingService::class)->book($c['clinic'], $this->bookingData($c), $c['receptionist']);
        $visit = Visit::factory()->for($booking['appointment'])->create();
        $record = MedicalRecord::factory()->for($visit)->create(['subjective' => 'Tetap utuh']);
        $this->postJson(route('doctor.visits.save', $visit), ['_method' => 'PUT', 'subjective' => 'Tidak boleh tersimpan'])->assertUnauthorized();
        $this->assertSame('Tetap utuh', $record->fresh()->subjective);
    }

    public function test_stale_csrf_is_rejected_without_changing_the_record(): void
    {
        $c = $this->context();
        $booking = app(BookingService::class)->book($c['clinic'], $this->bookingData($c), $c['receptionist']);
        $visit = Visit::factory()->for($booking['appointment'])->create();
        $record = MedicalRecord::factory()->for($visit)->create(['subjective' => 'Tetap utuh']);
        $this->actingAs($c['doctor']->user);
        $this->app['env'] = 'local';
        try {
            $this->withSession(['_token' => 'current-test-token'])->putJson(route('doctor.visits.save', $visit), [
                '_token' => 'stale-test-token', 'subjective' => 'Tidak boleh tersimpan',
            ])->assertStatus(419);
            $this->assertSame('Tetap utuh', $record->fresh()->subjective);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    #[DataProvider('roles')]
    public function test_queue_filters_are_preserved_separately_from_refreshed_rows(string $role): void
    {
        $c = $this->context();
        $this->actingAs($role === 'Clinic Admin' ? $c['owner'] : $c['receptionist'])->get(route('receptionist.queues.index', ['status' => 'WAITING']))->assertOk()->assertSee('data-realtime-preserve="queue-filters"', false)->assertSee('value="WAITING" selected', false);
    }

    /** @return array{clinic: Clinic, owner: User, receptionist: User, doctor: Doctor, service: Service, schedule: DoctorSchedule, patient: Patient} */
    private function context(): array
    {
        $clinic = Clinic::factory()->create();
        $clinic->settings()->create(['opening_time' => '08:00', 'closing_time' => '20:00', 'timezone' => 'Asia/Jakarta']);
        $owner = User::factory()->clinicOwner($clinic)->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $user = User::factory()->forClinic($clinic)->create();
        $user->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($user, 'user')->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '12:00', 'end_time' => '18:00', 'quota' => 100]);
        $patient = Patient::factory()->for($clinic)->create();
        app(SubscriptionService::class)->activateFree($clinic);

        return compact('clinic', 'owner', 'receptionist', 'doctor', 'service', 'schedule', 'patient');
    }

    /** @param array{patient: Patient, doctor: Doctor, schedule: DoctorSchedule, service: Service} $c
     * @return array<string, mixed>
     */
    private function bookingData(array $c): array
    {
        return ['patient_id' => $c['patient']->id, 'doctor_id' => $c['doctor']->id, 'doctor_schedule_id' => $c['schedule']->id, 'service_id' => $c['service']->id, 'appointment_date' => now('Asia/Jakarta')->toDateString(), 'idempotency_key' => (string) Str::uuid()];
    }
}
