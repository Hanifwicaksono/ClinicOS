<?php

namespace Tests\Feature;

use App\MedicalRecordStatus;
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
use App\UserAccountType;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartialWorkflowCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_autosave_persists_nested_data_and_rejects_stale_save_and_finalize(): void
    {
        $visit = $this->visit();
        $payload = $this->payload($visit);
        $payload['diagnoses'] = [['name' => 'Diagnosis uji', 'is_primary' => true]];
        $payload['treatments'] = [['name' => 'Tindakan uji', 'quantity' => 3]];
        $payload['prescription_items'] = [['medicine_name' => 'Obat draft']];
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.save', $visit), $payload)
            ->assertOk()->assertJsonPath('record_version', 1);
        $this->assertDatabaseHas('treatments', ['medical_record_id' => $visit->medicalRecord->id, 'quantity' => 3]);
        $this->assertDatabaseHas('diagnoses', ['medical_record_id' => $visit->medicalRecord->id, 'name' => 'Diagnosis uji']);
        $this->assertDatabaseHas('prescription_items', ['medicine_name' => 'Obat draft', 'dosage' => '', 'frequency' => '', 'quantity' => null]);
        $this->assertSame(MedicalRecordStatus::Draft, $visit->medicalRecord->refresh()->status);
        $payload['assessment'] = 'Perubahan tab lama';
        $this->putJson(route('doctor.visits.save', $visit), $payload)->assertConflict();
        unset($payload['prescription_items']);
        $this->putJson(route('doctor.visits.complete', $visit), $payload)->assertConflict();
        $this->assertSame('Assessment uji', $visit->medicalRecord->refresh()->assessment);
        $this->assertSame(VisitStatus::InProgress, $visit->refresh()->status);
        $payload['record_version'] = 1;
        $payload['diagnoses'] = [['name' => '']];
        $payload['treatments'] = [['name' => '', 'quantity' => 1]];
        $this->putJson(route('doctor.visits.save', $visit), $payload)->assertOk()->assertJsonPath('record_version', 2);
        $this->assertSame(0, $visit->medicalRecord->diagnoses()->count());
        $this->assertSame(0, $visit->medicalRecord->treatments()->count());
    }

    public function test_autosave_requires_version_and_validates_without_mutation(): void
    {
        $visit = $this->visit();
        $payload = $this->payload($visit);
        unset($payload['record_version']);
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.save', $visit), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('record_version');
        $payload['record_version'] = 0;
        $payload['treatments'] = [['name' => 'Tindakan', 'quantity' => 0]];
        $this->putJson(route('doctor.visits.save', $visit), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('treatments.0.quantity');
        $payload['treatments'][0]['quantity'] = null;
        $this->putJson(route('doctor.visits.save', $visit), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('treatments.0.quantity');
        $this->assertSame(0, $visit->medicalRecord->refresh()->lock_version);
    }

    public function test_incomplete_prescription_is_saved_as_draft_without_inventing_quantity(): void
    {
        $visit = $this->visit();
        $payload = $this->payload($visit);
        $payload['prescription_items'] = [['medicine_name' => 'Obat draft', 'dosage' => 'Dosis uji', 'frequency' => 'Frekuensi uji', 'quantity' => null]];
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.save', $visit), $payload)->assertOk();
        $payload['record_version'] = 1;
        $this->putJson(route('doctor.visits.complete', $visit), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('prescription_items.0.quantity');
        $this->assertDatabaseHas('prescription_items', ['medicine_name' => 'Obat draft', 'quantity' => null]);
        $this->assertSame(MedicalRecordStatus::Draft, $visit->medicalRecord->refresh()->status);
    }

    public function test_autosave_forbids_other_doctors_and_final_records(): void
    {
        $visit = $this->visit();
        $other = $this->visit();
        $payload = $this->payload($visit);
        $this->actingAs($other->doctor->user)->putJson(route('doctor.visits.save', $visit), $payload)->assertForbidden();
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.complete', $visit), $payload)->assertOk();
        $payload['record_version'] = 1;
        $this->putJson(route('doctor.visits.save', $visit), $payload)->assertForbidden();
        $this->assertSame(MedicalRecordStatus::Final, $visit->medicalRecord->refresh()->status);
    }

    public function test_allergy_changes_preserve_history_and_invalidate_stale_editors(): void
    {
        $visit = $this->visit();
        $other = $this->visit();
        $payload = $this->payload($visit);
        $allergy = ['substance' => 'Pemicu uji', 'reaction' => 'Reaksi uji', 'severity' => 'CRITICAL', 'is_active' => true];
        $this->actingAs($other->doctor->user)->postJson(route('doctor.visits.allergies.store', $visit), $allergy)->assertForbidden();
        $this->actingAs($visit->doctor->user)->postJson(route('doctor.visits.allergies.store', $visit), $allergy)->assertOk();
        $saved = $visit->patient->allergies()->sole();
        $this->assertSame($visit->doctor->user_id, $saved->recorded_by_user_id);
        $this->get(route('doctor.visits.show', $visit))->assertOk()->assertSeeText('Pemicu uji');
        $this->putJson(route('doctor.visits.save', $visit), $payload)->assertConflict();
        $foreign = PatientAllergy::factory()->create(['patient_id' => $other->patient_id]);
        $this->putJson(route('doctor.visits.allergies.update', [$visit, $foreign]), $allergy)->assertNotFound();
        $this->putJson(route('doctor.visits.allergies.update', [$visit, $saved]), [...$allergy, 'is_active' => false])->assertOk();
        $this->assertFalse($saved->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_allergy.updated', 'entity_id' => $saved->id]);
        $this->assertDatabaseCount('patient_allergies', 2);
    }

    public function test_allergy_endpoints_reject_administrative_roles_and_invalid_values(): void
    {
        $visit = $this->visit();
        foreach (['Clinic Admin', 'Receptionist', 'Platform Admin', 'Patient'] as $role) {
            $actor = User::factory()->forClinic($visit->clinic)->create();
            $actor->assignRole($role);
            $this->actingAs($actor)->postJson(route('doctor.visits.allergies.store', $visit), [
                'substance' => 'Pemicu', 'severity' => 'CRITICAL', 'is_active' => true,
            ])->assertForbidden();
        }
        $this->actingAs($visit->doctor->user)->postJson(route('doctor.visits.allergies.store', $visit), [
            'substance' => '', 'severity' => 'INVALID', 'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['substance', 'severity']);
        $this->putJson(route('doctor.visits.complete', $visit), $this->payload($visit))->assertOk();
        $this->postJson(route('doctor.visits.allergies.store', $visit), [
            'substance' => 'Pemicu', 'severity' => 'CRITICAL', 'is_active' => true,
        ])->assertForbidden();
        $this->assertDatabaseCount('patient_allergies', 0);
    }

    public function test_correction_of_allergy_details_preserves_the_previous_clinical_values(): void
    {
        $visit = $this->visit();
        $allergy = PatientAllergy::factory()->for($visit->patient)->critical()->create(['substance' => 'Pemicu lama']);
        $this->actingAs($visit->doctor->user)->putJson(route('doctor.visits.allergies.update', [$visit, $allergy]), [
            'substance' => 'Pemicu terkoreksi', 'reaction' => 'Reaksi terkoreksi', 'severity' => 'MODERATE', 'is_active' => true,
        ])->assertOk();
        $this->assertFalse($allergy->refresh()->is_active);
        $this->assertSame('Pemicu lama', $allergy->substance);
        $this->assertDatabaseHas('patient_allergies', ['patient_id' => $visit->patient_id, 'substance' => 'Pemicu terkoreksi', 'is_active' => true]);
        $this->assertDatabaseCount('patient_allergies', 2);
    }

    public function test_duplicate_review_requires_confirmation_and_is_tenant_scoped(): void
    {
        $visit = $this->visit();
        $staff = $this->receptionist($visit->clinic);
        $visit->patient->update(['phone' => '081234567890']);
        $foreign = Patient::factory()->for(Clinic::factory()->create())->create(['phone' => '081234567890', 'name' => 'Pasien klinik lain']);
        $payload = ['name' => 'Pasien baru', 'birth_date' => '1990-01-01', 'gender' => 'FEMALE', 'phone' => '+62 812-3456-7890', 'is_active' => true];
        $review = $this->actingAs($staff)->postJson(route('receptionist.patients.duplicates'), $payload)->assertOk()
            ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.id', $visit->patient_id)->assertDontSee($foreign->name);
        $this->postJson(route('receptionist.patients.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('duplicate_reviewed');
        $payload['duplicate_reviewed'] = true;
        $payload['duplicate_review_token'] = $review->json('token');
        $this->postJson(route('receptionist.patients.store'), [...$payload, 'phone' => '081234567890'])
            ->assertUnprocessable()->assertJsonValidationErrors('duplicate_reviewed');
        $this->post(route('receptionist.patients.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, Patient::query()->where('clinic_id', $visit->clinic_id)->count());
    }

    public function test_walk_in_uses_duplicate_review_without_auto_linking(): void
    {
        $visit = $this->visit();
        $staff = $this->receptionist($visit->clinic);
        $payload = [
            'name' => 'Pasien baru lain', 'birth_date' => '1992-01-01', 'gender' => 'MALE', 'phone' => $visit->patient->phone,
            'doctor_id' => $visit->doctor_id, 'service_id' => $visit->service_id,
            'doctor_schedule_id' => $visit->appointment->doctor_schedule_id,
            'appointment_date' => now('Asia/Jakarta')->toDateString(), 'idempotency_key' => Str::uuid()->toString(),
        ];
        $this->actingAs($staff)->postJson(route('receptionist.appointments.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('duplicate_reviewed');
        $review = $this->postJson(route('receptionist.patients.duplicates'), $payload)->assertOk();
        $payload['duplicate_reviewed'] = true;
        $payload['duplicate_review_token'] = $review->json('token');
        $this->post(route('receptionist.appointments.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $created = Appointment::query()->where('idempotency_key', $payload['idempotency_key'])->sole();
        $this->assertNotSame($visit->patient_id, $created->patient_id);
        $this->assertNull($created->patient->user_id);
    }

    public function test_duplicate_candidates_match_name_and_birth_date_and_forbid_patient_access(): void
    {
        $visit = $this->visit();
        $staff = $this->receptionist($visit->clinic);
        $payload = ['name' => mb_strtoupper($visit->patient->name), 'birth_date' => $visit->patient->birth_date->toDateString()];
        $this->actingAs($staff)->postJson(route('receptionist.patients.duplicates'), $payload)->assertOk()->assertJsonCount(1, 'candidates');
        $patient = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $patient->assignRole('Patient');
        $this->actingAs($patient)->postJson(route('receptionist.patients.duplicates'), $payload)->assertForbidden();
    }

    public function test_patient_eta_is_owned_refreshable_and_hidden_outside_active_today(): void
    {
        $visit = $this->visit();
        $owner = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $owner->assignRole('Patient');
        $visit->patient->update(['user_id' => $owner->id]);
        $queue = $visit->queue;
        $queue->update(['status' => QueueStatus::Waiting]);
        $this->actingAs($owner)->get(route('patient.appointments.show', $visit->appointment))->assertOk()
            ->assertViewHas('eta', fn (?array $eta): bool => $eta !== null && $eta['confidence'] === 'low')
            ->assertSee('data-realtime-refresh-root', false)->assertSeeText('Perkiraan 0–0 menit');
        $queue->update(['status' => QueueStatus::Completed]);
        $this->get(route('patient.appointments.show', $visit->appointment))->assertOk()->assertViewHas('eta', null);
        $queue->update(['status' => QueueStatus::Waiting, 'queue_date' => now()->addDay()]);
        $this->get(route('patient.appointments.show', $visit->appointment))->assertOk()->assertViewHas('eta', null);
    }

    public function test_forms_render_shared_filters_and_structured_editor(): void
    {
        $visit = $this->visit();
        $this->actingAs($visit->doctor->user)->get(route('doctor.visits.show', $visit))->assertOk()
            ->assertSee('data-rme-autosave', false)->assertSeeText('Diagnosis dan tindakan')->assertSeeText('Catat alergi');
        $staff = $this->receptionist($visit->clinic);
        $this->actingAs($staff)->get(route('receptionist.appointments.create'))->assertOk()
            ->assertSee('data-booking-fields', false)->assertSee('data-duplicate-review', false);
        $this->get(route('receptionist.patients.create'))->assertOk()->assertSee('data-duplicate-review', false);
    }

    private function visit(): Visit
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create(['day_of_week' => now('Asia/Jakarta')->dayOfWeek, 'start_time' => '00:00', 'end_time' => '23:59']);
        $patient = Patient::factory()->for($clinic)->create();
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

    private function receptionist(Clinic $clinic): User
    {
        $staff = User::factory()->forClinic($clinic)->create();
        $staff->assignRole('Receptionist');

        return $staff;
    }

    /** @return array<string, mixed> */
    private function payload(Visit $visit): array
    {
        return [
            'record_version' => $visit->medicalRecord->lock_version, 'allergy_version' => $visit->patient->allergyVersion(),
            'subjective' => 'Subjektif uji', 'assessment' => 'Assessment uji', 'plan' => 'Rencana uji',
        ];
    }
}
