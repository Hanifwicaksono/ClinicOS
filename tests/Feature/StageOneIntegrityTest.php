<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\Services\AuditLogger;
use App\Services\BookingService;
use App\UserAccountType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StageOneIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_stale_revision_is_rejected_and_only_one_successor_is_current(): void
    {
        $visit = $this->visit();
        $allergy = PatientAllergy::factory()->for($visit->patient)->critical()->create();
        $payload = $this->allergyPayload() + ['expected_version' => $allergy->revisionToken(), 'revision_reason' => 'Dikonfirmasi ulang bersama pasien.'];
        $route = route('doctor.visits.allergies.update', [$visit, $allergy]);
        $this->actingAs($visit->doctor->user)->putJson($route, $payload)->assertOk();
        $this->putJson($route, $payload)->assertOk();
        $this->putJson($route, [...$payload, 'request_id' => (string) Str::uuid(), 'reaction' => 'Input tab lama'])
            ->assertConflict()->assertSee('Input Anda tetap tersedia');
        $this->assertDatabaseCount('patient_allergies', 2);
        $current = $visit->patient->allergies()->whereNull('superseded_by_id')->sole();
        $this->assertSame($allergy->id, $current->supersedes_id);
        $this->assertSame($allergy->id, $current->lineage_id);
        $this->assertSame(2, $current->revision_number);
        $this->assertSame($current->id, $allergy->fresh()->superseded_by_id);
        $this->assertTrue($allergy->fresh()->is_active);
        $this->assertSame($visit->doctor->user_id, $current->recorded_by_user_id);
        $this->assertSame(1, $visit->patient->criticalAllergies()->count());
    }

    public function test_creation_is_idempotent_and_changed_replay_is_rejected(): void
    {
        $visit = $this->visit();
        $payload = $this->allergyPayload();
        $route = route('doctor.visits.allergies.store', $visit);
        $this->actingAs($visit->doctor->user)->postJson($route, $payload)->assertOk();
        $this->postJson($route, $payload)->assertOk();
        $this->postJson($route, [...$payload, 'substance' => 'Berbeda'])->assertConflict();
        $this->assertDatabaseCount('patient_allergies', 1);
        $root = $visit->patient->allergies()->sole();
        $this->assertSame($root->id, $root->lineage_id);
    }

    public function test_revision_requires_version_and_reason_without_merging_similar_legacy_entries(): void
    {
        $visit = $this->visit();
        $first = PatientAllergy::factory()->for($visit->patient)->critical()->create(['substance' => 'Pemicu sama']);
        $second = PatientAllergy::factory()->for($visit->patient)->critical()->create(['substance' => 'Pemicu sama']);
        $route = route('doctor.visits.allergies.update', [$visit, $first]);
        $this->actingAs($visit->doctor->user)->get(route('doctor.visits.show', $visit))->assertOk()->assertSeeText('Perlu verifikasi');
        $this->actingAs($visit->doctor->user)->putJson($route, $this->allergyPayload())
            ->assertUnprocessable()->assertJsonValidationErrors(['expected_version', 'revision_reason']);
        $this->putJson($route, $this->allergyPayload() + ['expected_version' => $first->revisionToken(), 'revision_reason' => 'Hasil peninjauan klinis.'])->assertOk();
        $this->assertNull($second->fresh()->lineage_id);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(2, $visit->patient->criticalAllergies()->count());
    }

    public function test_inactive_linked_patient_cannot_book_or_update_profile_but_can_read_existing_booking(): void
    {
        $visit = $this->visit();
        $user = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $user->assignRole('Patient');
        $visit->patient->update(['user_id' => $user->id, 'is_active' => false]);
        $before = $visit->patient->fresh()->getAttributes();
        $this->actingAs($user)->postJson(route('public.booking.store', $visit->clinic), $this->bookingPayload($visit))
            ->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->assertSame($before, $visit->patient->fresh()->getAttributes());
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('patients', 1);
        $this->get(route('public.booking.create', $visit->clinic))->assertOk()->assertSeeText('Profil pasien Anda di klinik ini nonaktif');
        $this->get(route('patient.appointments.show', $visit->appointment))->assertOk();
    }

    public function test_staff_booking_checks_fresh_patient_state_and_allows_reactivation_and_existing_replay(): void
    {
        $visit = $this->visit();
        $staff = User::factory()->forClinic($visit->clinic)->create();
        $staff->assignRole('Receptionist');
        $patient = $visit->patient;
        $patient->update(['is_active' => false]);
        $payload = ['patient_id' => $patient->id, ...$this->bookingPayload($visit)];
        try {
            app(BookingService::class)->book($visit->clinic, $payload, $staff);
            $this->fail('Profil nonaktif harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('patient_id', $exception->errors());
        }
        $this->assertDatabaseCount('appointments', 1);
        $this->actingAs($staff)->put(route('receptionist.patients.update', $patient), [
            ...$patient->only(['name', 'gender', 'phone', 'email', 'address']),
            'birth_date' => $patient->birth_date->toDateString(), 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $result = app(BookingService::class)->book($visit->clinic, $payload, $staff);
        $this->assertTrue($result['created']);
        $this->assertDatabaseCount('patients', 1);
        $patient->update(['is_active' => false]);
        $replay = app(BookingService::class)->book($visit->clinic, $payload, $staff);
        $this->assertFalse($replay['created']);
        $this->assertSame($result['appointment']->id, $replay['appointment']->id);
    }

    public function test_guest_similarity_does_not_link_or_reactivate_inactive_patient(): void
    {
        $visit = $this->visit();
        $visit->patient->update(['is_active' => false]);
        $result = app(BookingService::class)->book($visit->clinic, [...$this->bookingPayload($visit), 'phone' => $visit->patient->phone]);
        $this->assertNotSame($visit->patient_id, $result['appointment']->patient_id);
        $this->assertFalse($visit->patient->fresh()->is_active);
    }

    public function test_revisions_from_another_assigned_visit_cannot_branch_the_same_parent(): void
    {
        $first = $this->visit();
        $second = $this->visit($first->patient);
        $allergy = PatientAllergy::factory()->for($first->patient)->critical()->create();
        $version = $allergy->revisionToken();
        foreach ([$first, $second] as $index => $visit) {
            $response = $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.allergies.update', [$visit, $allergy]),
                $this->allergyPayload() + ['expected_version' => $version, 'revision_reason' => 'Evaluasi klinis melalui kunjungan berbeda.']);
            $index === 0 ? $response->assertOk() : $response->assertConflict();
        }
        $this->assertSame(1, $first->patient->allergies()->whereNull('superseded_by_id')->count());
    }

    public function test_audit_failure_rolls_back_the_entire_allergy_revision(): void
    {
        $visit = $this->visit();
        $allergy = PatientAllergy::factory()->for($visit->patient)->critical()->create();
        $this->mock(AuditLogger::class)->shouldReceive('recordForClinic')->once()->andThrow(new \RuntimeException('Simulated audit failure'));
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.allergies.update', [$visit, $allergy]),
            $this->allergyPayload() + ['expected_version' => $allergy->revisionToken(), 'revision_reason' => 'Alasan klinis pengujian rollback.'])->assertStatus(500);
        $this->assertDatabaseCount('patient_allergies', 1);
        $this->assertTrue($allergy->fresh()->is_active);
        $this->assertNull($allergy->fresh()->superseded_by_id);
        $this->assertNull($allergy->fresh()->lineage_id);
    }

    private function visit(?Patient $patient = null): Visit
    {
        $clinic = $patient?->clinic ?? Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59']);
        $patient ??= Patient::factory()->for($clinic)->create();
        $appointment = Appointment::factory()->for($clinic)->for($doctor)->for($patient)->for($service)->create([
            'doctor_schedule_id' => $schedule->id, 'appointment_date' => now('Asia/Jakarta')->toDateString(),
        ]);
        Queue::factory()->for($clinic)->for($doctor)->for($appointment)->create([
            'doctor_schedule_id' => $schedule->id, 'queue_date' => now('Asia/Jakarta')->toDateString(), 'status' => QueueStatus::InProgress,
        ]);
        $visit = Visit::factory()->for($appointment)->create();
        MedicalRecord::factory()->for($visit)->create();

        return $visit;
    }

    /** @return array<string, mixed> */
    private function allergyPayload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'substance' => 'Pemicu uji', 'reaction' => 'Reaksi uji', 'severity' => 'CRITICAL', 'is_active' => true];
    }

    /** @return array<string, mixed> */
    private function bookingPayload(Visit $visit): array
    {
        return [
            'doctor_id' => $visit->doctor_id, 'service_id' => $visit->service_id,
            'doctor_schedule_id' => $visit->appointment->doctor_schedule_id, 'appointment_date' => $visit->appointment->appointment_date->toDateString(),
            'idempotency_key' => (string) Str::uuid(), 'name' => 'Pasien uji', 'birth_date' => '1990-01-01',
            'gender' => 'FEMALE', 'phone' => '081234567890', 'email' => 'pasien@example.test', 'address' => 'Alamat uji',
        ];
    }
}
