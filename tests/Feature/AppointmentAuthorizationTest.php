<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\QueueStatus;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /** @return array<string, array{bool, bool, bool}> */
    public static function patientAccessCases(): array
    {
        return [
            'own without clinic' => [true, false, false],
            'own with clinic' => [true, true, false],
            'other patient without clinic' => [false, false, false],
            'other patient same clinic' => [false, true, false],
            'other clinic without account clinic' => [false, false, true],
            'other clinic with account clinic' => [false, true, true],
        ];
    }

    #[DataProvider('patientAccessCases')]
    public function test_patient_detail_and_cancel_require_ownership(bool $owns, bool $hasClinic, bool $otherClinic): void
    {
        $clinic = Clinic::factory()->create();
        $actor = User::factory()->create([
            'clinic_id' => $hasClinic ? $clinic->id : null,
            'account_type' => UserAccountType::Patient,
        ]);
        $actor->assignRole('Patient');
        $appointment = $this->appointment($otherClinic ? Clinic::factory()->create() : $clinic, $owns ? $actor : null);
        $before = $this->state();
        $policy = app(AppointmentPolicy::class);

        $this->assertSame($owns, $policy->view($actor, $appointment));
        $this->assertSame($owns, $policy->delete($actor, $appointment));

        $response = $this->actingAs($actor)->get(route('patient.appointments.show', $appointment));

        if (! $owns) {
            $response->assertForbidden()->assertDontSee($appointment->booking_code);
            $this->post(route('patient.appointments.cancel', $appointment))->assertForbidden();
            $this->assertSame($before, $this->state());

            return;
        }

        $response->assertOk()
            ->assertSee($appointment->booking_code)
            ->assertViewHas('appointment', fn (Appointment $shown): bool => $shown->id === $appointment->id && $shown->patient->user_id === $actor->id);
        $this->post(route('patient.appointments.cancel', $appointment), ['cancel_reason' => 'Berhalangan'])
            ->assertRedirect();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Cancelled, $appointment->queue->refresh()->status);
        $this->assertSame('Berhalangan', $appointment->cancel_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.cancelled', 'entity_id' => $appointment->id, 'user_id' => $actor->id]);
        $this->assertDatabaseCount('visits', 0);
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function staffAccessCases(): array
    {
        return [
            'receptionist own clinic' => ['Receptionist', false, true],
            'admin own clinic' => ['Clinic Admin', false, true],
            'receptionist other clinic' => ['Receptionist', true, false],
            'admin other clinic' => ['Clinic Admin', true, false],
            'doctor has no receptionist endpoint' => ['Doctor', false, false],
            'platform has no receptionist endpoint' => ['Platform Admin', false, false],
        ];
    }

    #[DataProvider('staffAccessCases')]
    public function test_staff_endpoint_preserves_roles_and_tenant_boundaries(string $role, bool $otherClinic, bool $allowed): void
    {
        $clinic = Clinic::factory()->create();
        $actor = User::factory()->forClinic($clinic)->create();
        $actor->assignRole($role);
        $appointment = $this->appointment($otherClinic ? Clinic::factory()->create() : $clinic);
        $before = $this->state();
        $response = $this->actingAs($actor)->get(route('receptionist.appointments.show', $appointment));

        if (! $allowed) {
            $response->assertForbidden()->assertDontSee($appointment->booking_code);
            $this->post(route('receptionist.appointments.cancel', $appointment))->assertForbidden();
            $this->assertSame($before, $this->state());

            return;
        }

        $response->assertOk()->assertViewHas('appointment', fn (Appointment $shown): bool => $shown->id === $appointment->id);
        $this->post(route('receptionist.appointments.cancel', $appointment))->assertRedirect();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertSame(QueueStatus::Cancelled, $appointment->queue->refresh()->status);
    }

    public function test_multi_role_user_uses_ownership_on_patient_routes_and_staff_authority_on_staff_routes(): void
    {
        $clinic = Clinic::factory()->create();
        $actor = User::factory()->forClinic($clinic)->create(['account_type' => UserAccountType::Patient]);
        $actor->assignRole(['Patient', 'Receptionist']);
        $appointment = $this->appointment($clinic);
        $before = $this->state();

        $this->actingAs($actor)->get(route('patient.appointments.show', $appointment))->assertForbidden();
        $this->post(route('patient.appointments.cancel', $appointment))->assertForbidden();
        $this->assertSame($before, $this->state());
        $this->get(route('receptionist.appointments.show', $appointment))->assertOk();
        $this->post(route('receptionist.appointments.cancel', $appointment))->assertRedirect();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_patient_cannot_cancel_another_patient_in_the_same_clinic_directly(): void
    {
        $clinic = Clinic::factory()->create();
        $actor = User::factory()->forClinic($clinic)->create(['account_type' => UserAccountType::Patient]);
        $actor->assignRole('Patient');
        $appointment = $this->appointment($clinic);
        $before = $this->state();

        $this->actingAs($actor)->post(route('patient.appointments.cancel', $appointment))->assertForbidden();
        $this->assertSame($before, $this->state());
    }

    public function test_repeated_patient_cancel_keeps_validation_contract_and_creates_no_second_effect(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create(['account_type' => UserAccountType::Patient]);
        $user->assignRole('Patient');
        $appointment = $this->appointment($clinic, $user);

        $this->actingAs($user)->post(route('patient.appointments.cancel', $appointment))->assertRedirect();
        $afterFirst = $this->state();
        $this->postJson(route('patient.appointments.cancel', $appointment))->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertSame($afterFirst, $this->state());
    }

    public function test_patient_ownership_does_not_override_revoked_permissions(): void
    {
        $clinic = Clinic::factory()->create();
        $actor = User::factory()->forClinic($clinic)->create(['account_type' => UserAccountType::Patient]);
        $actor->assignRole('Patient');
        $actor->roles->first()->revokePermissionTo(['appointment.view', 'appointment.cancel']);
        $appointment = $this->appointment($clinic, $actor);
        $before = $this->state();

        $this->actingAs($actor)->get(route('patient.appointments.show', $appointment))->assertForbidden();
        $this->post(route('patient.appointments.cancel', $appointment))->assertForbidden();
        $this->assertSame($before, $this->state());
    }

    private function appointment(Clinic $clinic, ?User $owner = null): Appointment
    {
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->addDays(2)->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => $date->dayOfWeek, 'start_time' => '08:00', 'end_time' => '23:59']);
        $patient = Patient::factory()->for($clinic)->create(['user_id' => $owner?->id]);
        $appointment = Appointment::factory()->for($clinic)->for($patient)->for($doctor)->for($service)->create([
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date->toDateString(),
        ]);
        Queue::factory()->for($clinic)->for($appointment)->for($doctor)->create([
            'doctor_schedule_id' => $schedule->id, 'queue_date' => $date->toDateString(),
        ]);

        return $appointment;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function state(): array
    {
        $state = [];
        foreach (['appointments', 'queues', 'visits', 'audit_logs', 'notifications', 'jobs'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $state;
    }
}
