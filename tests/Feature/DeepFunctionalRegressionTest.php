<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\SubscriptionPrice;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeepFunctionalRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, SubscriptionPlanSeeder::class]);
        $this->travelTo(now('Asia/Jakarta')->startOfDay()->addHours(9));
    }

    /** @return array<string, array{string}> */
    public static function cancellationActors(): array
    {
        return ['patient' => ['Patient'], 'receptionist' => ['Receptionist'], 'admin' => ['Clinic Admin'], 'token' => ['token']];
    }

    #[DataProvider('cancellationActors')]
    public function test_started_visit_cannot_be_cancelled_and_can_still_be_finalized(string $role): void
    {
        $context = $this->appointmentContext();
        $appointment = $context['appointment'];
        $context['queue']->update(['status' => QueueStatus::InProgress]);
        $visit = Visit::factory()->for($appointment)->create();
        MedicalRecord::factory()->for($visit)->create();
        if ($role === 'token') {
            $url = route('public.booking.cancel', $appointment);
        } elseif ($role === 'Patient') {
            $this->actingAs($context['patient_user']);
            $url = route('patient.appointments.cancel', $appointment);
        } else {
            $actor = User::factory()->forClinic($context['clinic'])->create();
            $actor->assignRole($role);
            $this->actingAs($actor);
            $url = route('receptionist.appointments.cancel', $appointment);
        }

        $this->postJson($url, ['token' => $context['token']])->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::InProgress, $context['queue']->refresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.cancelled', 'entity_id' => $appointment->id]);
        $this->actingAs($context['doctor']->user)->putJson(route('doctor.visits.complete', $visit), [
            ...$this->recordVersions($visit), 'subjective' => 'Catatan audit', 'assessment' => 'Assessment audit', 'plan' => 'Rencana audit',
        ])->assertOk();
        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
    }

    /** @return array<string, array{string, int|string}> */
    public static function sessionChanges(): array
    {
        return ['day' => ['day_of_week', 2], 'start' => ['start_time', '13:00'], 'end' => ['end_time', '17:00']];
    }

    #[DataProvider('sessionChanges')]
    public function test_used_session_keeps_its_day_and_times(string $field, int|string $value): void
    {
        $context = $this->appointmentContext();
        $admin = User::factory()->forClinic($context['clinic'])->create();
        $admin->assignRole('Clinic Admin');
        $schedule = $context['schedule'];
        $payload = ['doctor_id' => $context['doctor']->id, 'day_of_week' => $schedule->day_of_week,
            'start_time' => '12:00', 'end_time' => '18:00', 'quota' => 20, 'is_active' => true];
        $payload[$field] = $field === 'day_of_week' ? ($schedule->day_of_week + 1) % 7 : $value;

        $this->actingAs($admin)->putJson(route('admin.doctor-schedules.update', $schedule), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(12, (int) substr($schedule->refresh()->start_time, 0, 2));
        $this->assertSame($context['appointment']->appointment_date->dayOfWeek, $schedule->day_of_week);
        $this->assertSame($context['appointment']->appointment_date->toDateString(), $context['queue']->queue_date->toDateString());
    }

    public function test_replayed_grant_creates_one_period_and_one_audit_event(): void
    {
        $actor = User::factory()->platformAdmin()->create();
        $clinic = Clinic::factory()->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'individual')->firstOrFail();
        $payload = ['subscription_price_id' => $price->id, 'reason' => 'Grant audit satu kali', 'grant_request_id' => (string) Str::uuid()];
        $this->actingAs($actor)->post(route('platform.subscription-grants.store', $clinic), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('platform.subscription-grants.store', $clinic), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->assertSame(1, AuditLog::query()->where('clinic_id', $clinic->id)->where('action', 'subscription.granted')->count());
        $this->postJson(route('platform.subscription-grants.store', $clinic), [...$payload, 'reason' => 'Maksud grant berbeda'])
            ->assertUnprocessable();
        $otherClinic = Clinic::factory()->create();
        $this->postJson(route('platform.subscription-grants.store', $otherClinic), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('subscription_periods', 1);
        $this->post(route('platform.subscription-grants.store', $clinic), [...$payload, 'grant_request_id' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('subscription_periods', 2);
    }

    public function test_http_grant_requires_a_request_identifier(): void
    {
        $actor = User::factory()->platformAdmin()->create();
        $clinic = Clinic::factory()->create();
        $price = SubscriptionPrice::query()->whereRelation('plan', 'code', 'individual')->firstOrFail();
        $this->actingAs($actor)->postJson(route('platform.subscription-grants.store', $clinic), [
            'subscription_price_id' => $price->id, 'reason' => 'Tanpa identitas request',
        ])->assertUnprocessable()->assertJsonValidationErrors('grant_request_id');
        $this->assertDatabaseCount('subscription_periods', 0);
    }

    public function test_patient_phone_validation_is_shared_by_all_entry_points(): void
    {
        $context = $this->appointmentContext();
        $receptionist = User::factory()->forClinic($context['clinic'])->create();
        $receptionist->assignRole('Receptionist');
        $data = ['name' => 'Pasien regresi', 'birth_date' => '1990-01-01', 'gender' => 'MALE', 'phone' => 'abc', 'is_active' => true];
        $this->actingAs($receptionist)->postJson(route('receptionist.patients.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson(route('receptionist.patients.update', $context['patient']), $data)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson(route('receptionist.patients.duplicates'), $data)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson(route('receptionist.appointments.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->actingAs($context['patient_user'])->postJson(route('public.booking.store', $context['clinic']->slug), $data)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('patients', 1);
        foreach (['0812 3456 7890', '+62 (812) 345-678', '021.555.1234'] as $phone) {
            $this->actingAs($receptionist)->postJson(route('receptionist.patients.duplicates'), [...$data, 'phone' => $phone])->assertOk();
        }
        $this->postJson(route('receptionist.patients.duplicates'), [...$data, 'phone' => str_repeat('1', 31)])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_rejected_forms_render_errors_and_retain_input(): void
    {
        $context = $this->appointmentContext();
        $url = route('patient.appointments.show', $context['appointment']);
        $reason = str_repeat('x', 501);
        $this->actingAs($context['patient_user'])->from($url)->post(route('patient.appointments.cancel', $context['appointment']), ['cancel_reason' => $reason])
            ->assertRedirect($url)->assertSessionHasErrors('cancel_reason');
        $this->assertSame($reason, session()->getOldInput('cancel_reason'));
        $this->assertSame(AppointmentStatus::Booked, $context['appointment']->refresh()->status);
        $admin = User::factory()->forClinic($context['clinic'])->create();
        $admin->assignRole('Clinic Admin');
        $dashboard = route('admin.dashboard');
        $this->actingAs($admin)->from($dashboard)->get($dashboard.'?from=2026-11-13&to=2026-11-01')->assertRedirect($dashboard)->assertSessionHasErrors('to');
        $this->assertSame('2026-11-01', session()->getOldInput('to'));
        $this->getJson($dashboard.'?to='.now()->subDay()->toDateString())->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson($dashboard.'?from='.now()->addDay()->toDateString())->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->get($dashboard)->assertOk();
    }

    public function test_patient_can_choose_a_known_clinic_or_use_an_explicit_clinic_code(): void
    {
        $context = $this->appointmentContext();
        $other = Clinic::factory()->create();
        $this->actingAs($context['patient_user'])->get(route('patient.appointments.create'))
            ->assertOk()->assertSee($context['clinic']->name)->assertDontSee($other->name);
        $this->getJson(route('patient.appointments.create', ['clinic_code' => $other->slug]))->assertUnprocessable()->assertJsonValidationErrors('clinic_code');
        $newPatient = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $newPatient->assignRole('Patient');
        $this->actingAs($newPatient)->get(route('patient.appointments.create', ['clinic_code' => $other->slug]))->assertRedirect(route('public.booking.create', $other->slug));
        $other->update(['is_active' => false]);
        $this->getJson(route('patient.appointments.create', ['clinic_code' => $other->slug]))->assertUnprocessable()->assertJsonValidationErrors('clinic_code');
        $this->actingAs($newPatient)->get(route('patient.appointments.create'))->assertOk()->assertSee('Kode klinik');
    }

    /** @return array<string, array{string, string}> */
    public static function clinicTimezones(): array
    {
        return ['WIB' => ['Asia/Jakarta', '14 Nov 2026, 00:30'], 'WITA' => ['Asia/Makassar', '14 Nov 2026, 01:30'], 'WIT' => ['Asia/Jayapura', '14 Nov 2026, 02:30']];
    }

    #[DataProvider('clinicTimezones')]
    public function test_visit_and_queue_instants_use_clinic_timezone(string $timezone, string $expected): void
    {
        $context = $this->appointmentContext();
        $context['clinic']->settings()->update(['timezone' => $timezone]);
        $visit = Visit::factory()->for($context['clinic'])->for($context['doctor'])->for($context['patient'])
            ->for($context['appointment'])->create(['started_at' => '2026-11-13 17:30:00']);
        MedicalRecord::factory()->for($visit)->for($context['patient'])->for($context['doctor'])->create();
        $this->actingAs($context['doctor']->user)->get(route('doctor.visits.show', $visit))->assertOk()->assertSee($expected);
        $this->assertSame('2026-11-13 17:30:00', $visit->fresh()->started_at->format('Y-m-d H:i:s'));
        $context['queue']->update(['status' => QueueStatus::Called, 'called_at' => '2026-11-13 17:30:00']);
        $this->get(route('doctor.queues.index'))->assertOk()->assertSee(substr($expected, -5));
    }

    /** @return array{clinic: Clinic, doctor: Doctor, schedule: DoctorSchedule, patient_user: User, patient: Patient, appointment: Appointment, queue: Queue, token: string} */
    private function appointmentContext(): array
    {
        $clinic = Clinic::factory()->create();
        $clinic->settings()->create(['opening_time' => '00:00', 'closing_time' => '23:59', 'timezone' => 'Asia/Jakarta', 'queue_prefix' => 'QA']);
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser, 'user')->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '12:00', 'end_time' => '18:00']);
        $patientUser = User::factory()->forClinic($clinic)->create(['account_type' => UserAccountType::Patient]);
        $patientUser->assignRole('Patient');
        $patient = Patient::factory()->for($clinic)->for($patientUser, 'user')->create();
        $token = Str::random(64);
        $appointment = Appointment::factory()->for($clinic)->for($doctor)->for($service)->for($patient)->for($schedule, 'schedule')
            ->create(['appointment_date' => now('Asia/Jakarta')->toDateString(), 'access_token_hash' => hash('sha256', $token)]);
        $queue = Queue::factory()->for($clinic)->for($doctor)->for($schedule, 'schedule')->for($appointment)
            ->create(['queue_date' => $appointment->appointment_date, 'status' => QueueStatus::Booked]);

        return ['clinic' => $clinic, 'doctor' => $doctor, 'schedule' => $schedule, 'patient_user' => $patientUser,
            'patient' => $patient, 'appointment' => $appointment, 'queue' => $queue, 'token' => $token];
    }
}
