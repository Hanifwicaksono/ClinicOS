<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\Services\BookingService;
use App\SubscriptionStatus;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PublicCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    /** @return array<string, array{string, bool, string}> */
    public static function subscriptionCases(): array
    {
        return [
            'guest free' => ['free', false, 'active'],
            'guest individual' => ['individual', false, 'active'],
            'guest team' => ['team', false, 'active'],
            'guest trial' => ['trialing', false, 'active'],
            'guest suspended' => ['suspended', false, 'active'],
            'guest expired' => ['expired', false, 'active'],
            'active actor suspended target' => ['suspended', true, 'active'],
            'active actor expired target' => ['expired', true, 'active'],
            'suspended actor active target' => ['team', true, 'suspended'],
            'expired actor active target' => ['team', true, 'expired'],
            'active actor active target' => ['team', true, 'active'],
        ];
    }

    #[DataProvider('subscriptionCases')]
    public function test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users(string $targetState, bool $loggedIn, string $actorState): void
    {
        [$appointment, $token, $patientUser, $doctorUser] = $this->booking();
        $this->subscription($appointment->clinic, $targetState);
        $actor = null;
        if ($loggedIn) {
            $actorClinic = Clinic::factory()->create();
            $actor = User::factory()->forClinic($actorClinic)->create();
            $actor->assignRole('Receptionist');
            $this->subscription($actorClinic, $actorState);
            $this->actingAs($actor);
        }
        $before = $this->state();
        $response = $this->post(route('public.booking.cancel', $appointment), ['token' => $token, 'cancel_reason' => 'Batal']);
        if (in_array($targetState, ['expired', 'suspended'], true)) {
            $response->assertForbidden();
            $this->assertSame($before, $this->state());

            return;
        }

        $response->assertRedirect(route('public.booking.status', ['appointment' => $appointment, 'token' => $token]));
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Cancelled, $appointment->queue->refresh()->status);
        $this->assertDatabaseCount('visits', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.cancelled', 'clinic_id' => $appointment->clinic_id, 'user_id' => $actor?->id]);
        $recipientIds = DB::table('notifications')->pluck('notifiable_id')->unique()->sort()->values()->all();
        $expectedRecipients = collect([$patientUser->id, $doctorUser->id])->sort()->values()->all();
        $this->assertSame($expectedRecipients, $recipientIds);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee($appointment->booking_code)
            ->assertViewHas('appointment', fn (Appointment $shown): bool => $shown->patient_id === $appointment->patient_id && $shown->patient->user_id === $patientUser->id);
    }

    /** @return array<string, array{string, int}> */
    public static function invalidTokens(): array
    {
        return ['missing' => ['', 422], 'wrong format' => ['short', 422], 'wrong hash' => [str_repeat('x', 64), 404], 'another booking' => ['another', 404]];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_tokens_change_no_resource(string $token, int $status): void
    {
        [$appointment] = $this->booking();
        if ($token === 'another') {
            [, $token] = $this->booking();
        }
        $before = $this->state();
        $this->postJson(route('public.booking.cancel', $appointment), ['token' => $token])->assertStatus($status);
        $this->assertSame($before, $this->state());
    }

    public function test_repeat_cancellation_is_validation_error_without_duplicate_side_effects(): void
    {
        [$appointment, $token] = $this->booking();
        $this->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertRedirect();
        $afterFirst = $this->state();
        $this->postJson(route('public.booking.cancel', $appointment), ['token' => $token])->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertSame($afterFirst, $this->state());
    }

    public function test_service_itself_rejects_suspended_target_before_writing(): void
    {
        [$appointment, $token] = $this->booking();
        $this->subscription($appointment->clinic, 'suspended');
        $before = $this->state();
        try {
            app(BookingService::class)->cancel($appointment, accessToken: $token);
            $this->fail('Suspended target must not be cancelled.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->state());
    }

    public function test_started_session_cannot_be_cancelled(): void
    {
        [$appointment, $token] = $this->booking();
        $appointment->update(['appointment_date' => now()->subDay()->toDateString()]);
        $before = $this->state();
        $this->postJson(route('public.booking.cancel', $appointment), ['token' => $token])->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertSame($before, $this->state());
    }

    public function test_public_booking_reuses_owned_target_profile_with_another_auth_clinic(): void
    {
        [$existing, , $patientUser] = $this->booking();
        $patientUser->update(['clinic_id' => Clinic::factory()->create()->id]);
        $this->actingAs($patientUser);
        $data = [
            'doctor_id' => $existing->doctor_id,
            'service_id' => $existing->service_id,
            'doctor_schedule_id' => $existing->doctor_schedule_id,
            'appointment_date' => $existing->appointment_date->toDateString(),
            'idempotency_key' => Str::uuid()->toString(),
        ];

        $result = app(BookingService::class)->book($existing->clinic, $data, $patientUser);
        $this->assertTrue($result['created']);
        $this->assertSame($existing->patient_id, $result['appointment']->patient_id);
        $this->assertSame($patientUser->id, $result['appointment']->patient->user_id);
        $this->assertDatabaseCount('patients', 1);
        $afterFirst = $this->state();
        $replayed = app(BookingService::class)->book($existing->clinic, $data, $patientUser);
        $this->assertFalse($replayed['created']);
        $this->assertSame($result['appointment']->id, $replayed['appointment']->id);
        $this->assertSame($patientUser->id, $replayed['appointment']->patient->user_id);
        $this->assertSame($afterFirst, $this->state());
        $this->get(route('public.booking.status', ['appointment' => $result['appointment'], 'token' => $result['access_token']]))
            ->assertOk()->assertViewHas('appointment', fn (Appointment $shown): bool => $shown->patient->user_id === $patientUser->id);
    }

    public function test_free_target_with_excess_seats_cannot_be_cancelled_by_another_writable_clinic(): void
    {
        [$appointment, $token] = $this->booking();
        $this->subscription($appointment->clinic, 'free');
        $extraDoctor = User::factory()->forClinic($appointment->clinic)->create();
        $extraDoctor->assignRole('Doctor');
        Doctor::factory()->for($appointment->clinic)->for($extraDoctor)->create();
        $actor = User::factory()->forClinic(Clinic::factory()->create())->create();
        $actor->assignRole('Receptionist');
        $before = $this->state();

        $this->actingAs($actor)->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertForbidden();
        $this->assertSame($before, $this->state());
    }

    /** @return array<string, array{string}> */
    public static function readOnlyStates(): array
    {
        return ['expired' => ['expired'], 'suspended' => ['suspended']];
    }

    #[DataProvider('readOnlyStates')]
    public function test_read_only_target_allows_assigned_doctor_to_save_and_complete_active_visit(string $state): void
    {
        [$appointment, $token, , $doctorUser] = $this->booking();
        $this->subscription($appointment->clinic, $state);
        $appointment->queue()->update(['status' => QueueStatus::InProgress]);
        $visit = Visit::factory()->for($appointment)->create();
        $visit->medicalRecord()->create([
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'created_by_user_id' => $doctorUser->id,
        ]);
        $data = ['subjective' => 'Keluhan masih sama.', 'assessment' => 'Observasi.', 'plan' => 'Lanjutkan perawatan.'];

        $this->actingAs($doctorUser)->post(route('public.booking.cancel', $appointment), ['token' => $token])->assertForbidden();
        $this->assertSame(VisitStatus::InProgress, $visit->refresh()->status);
        $this->put(route('doctor.visits.save', $visit), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(VisitStatus::InProgress, $visit->refresh()->status);
        $this->assertSame($data['subjective'], $visit->medicalRecord->subjective);
        $this->put(route('doctor.visits.complete', $visit), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->status);
        $this->assertSame(MedicalRecordStatus::Final, $visit->medicalRecord->refresh()->status);
        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Completed, $appointment->queue->refresh()->status);
        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('jobs', 3);
        $this->assertSame($state, $appointment->clinic->subscription->status->value);
    }

    /** @return array{Appointment, string, User, User} */
    private function booking(): array
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $patientUser = User::factory()->create(['clinic_id' => null]);
        $patientUser->assignRole('Patient');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->addDays(2)->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => $date->dayOfWeek, 'start_time' => '08:00', 'end_time' => '23:59']);
        $patient = Patient::factory()->for($clinic)->for($patientUser)->create();
        $token = Str::random(64);
        $appointment = Appointment::factory()->for($clinic)->for($patient)->for($doctor)->for($service)->create([
            'doctor_schedule_id' => $schedule->id, 'appointment_date' => $date->toDateString(), 'access_token_hash' => hash('sha256', $token),
        ]);
        Queue::factory()->for($clinic)->for($appointment)->for($doctor)->create(['doctor_schedule_id' => $schedule->id, 'queue_date' => $date->toDateString()]);

        return [$appointment, $token, $patientUser, $doctorUser];
    }

    private function subscription(Clinic $clinic, string $state): void
    {
        $code = in_array($state, ['free', 'individual', 'team'], true) ? $state : 'team';
        $status = match ($state) {
            'free' => SubscriptionStatus::Free,
            'expired' => SubscriptionStatus::Expired,
            'suspended' => SubscriptionStatus::Suspended,
            'trialing' => SubscriptionStatus::Trialing,
            default => SubscriptionStatus::Active,
        };
        Subscription::factory()->for($clinic)->create([
            'subscription_plan_id' => SubscriptionPlan::query()->where('code', $code)->value('id'),
            'status' => $status,
            'ends_at' => $status === SubscriptionStatus::Expired ? now()->subDay() : now()->addMonth(),
        ]);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function state(): array
    {
        $state = [];
        foreach (['appointments', 'queues', 'visits', 'subscriptions', 'audit_logs', 'notifications', 'jobs'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $state;
    }
}
