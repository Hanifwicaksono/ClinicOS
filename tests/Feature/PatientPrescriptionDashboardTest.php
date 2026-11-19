<?php

namespace Tests\Feature;

use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\IssuedPrescription;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\PrescriptionIssuanceService;
use App\Services\VisitWorkflowService;
use App\UserAccountType;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientPrescriptionDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_patient_sees_only_their_explicitly_issued_and_released_prescriptions(): void
    {
        $patientUser = $this->patientUser();
        $otherPatient = $this->patientUser();

        $this->createPrescription($patientUser, medicine: 'Paracetamol 500 mg', clinicName: 'Klinik Sehat');
        $this->createPrescription($patientUser, medicine: 'Vitamin Multi Klinik', clinicName: 'Klinik Kedua');
        $this->createPrescription($patientUser, medicine: 'Obat Masih Draft', recordStatus: MedicalRecordStatus::Draft);
        $this->createPrescription($patientUser, medicine: 'Obat Visit Berjalan', visitStatus: VisitStatus::InProgress);
        $this->createPrescription($otherPatient, medicine: 'Obat Rahasia Pasien Lain');
        $this->createPrescription($patientUser, medicine: 'Resep belum dirilis', released: false);

        $this->actingAs($patientUser)
            ->get('/patient/dashboard')
            ->assertOk()
            ->assertSee('Resep saya')
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('Klinik Sehat')
            ->assertSee('Vitamin Multi Klinik')
            ->assertSee('1 tablet')
            ->assertSee('3 kali sehari')
            ->assertSee('10 tablet')
            ->assertSee('Sesudah makan')
            ->assertSee('Diminum sesuai petunjuk dokter.')
            ->assertDontSee('Obat Masih Draft')
            ->assertDontSee('Obat Visit Berjalan')
            ->assertDontSee('Obat Rahasia Pasien Lain')
            ->assertDontSee('Resep belum dirilis')
            ->assertDontSee('CATATAN INTERNAL DOKTER');
    }

    public function test_dashboard_does_not_publish_a_note_only_medical_record_as_an_issued_prescription(): void
    {
        $patientWithoutPrescription = $this->patientUser();

        $this->actingAs($patientWithoutPrescription)
            ->get('/patient/dashboard')
            ->assertOk()
            ->assertSee('Belum ada resep');

        $noteOnlyPatient = $this->patientUser();
        $this->createPrescription($noteOnlyPatient, medicine: null);

        $this->actingAs($noteOnlyPatient)
            ->get('/patient/dashboard')
            ->assertOk()
            ->assertSee('Belum ada resep')
            ->assertDontSee('Diminum sesuai petunjuk dokter.');
    }

    public function test_dashboard_preserves_the_issued_snapshot_until_a_replacement_is_explicitly_released(): void
    {
        $patientUser = $this->patientUser();
        $item = $this->createPrescription($patientUser, medicine: 'Nama Obat Lama');

        $this->actingAs($patientUser)
            ->get('/patient/dashboard')
            ->assertSee('Nama Obat Lama');

        $record = $item->prescription->medicalRecord;
        $visit = $record->visit;
        $actor = $visit->doctor->user;
        app(VisitWorkflowService::class)->correct($visit, $actor, ['record_version' => $record->lock_version, 'allergy_version' => $visit->patient->allergyVersion(),
            'correction_reason' => 'Amend prescription after clinical review.', 'prescription_items' => [[...$item->only(['dosage', 'frequency', 'quantity', 'unit', 'instructions']), 'medicine_name' => 'Nama Obat Koreksi']]]);
        $this->get('/patient/dashboard')->assertOk()->assertSee('Nama Obat Lama')->assertDontSee('Nama Obat Koreksi');
        $previous = IssuedPrescription::query()->where('visit_id', $visit->id)->firstOrFail();
        app(PrescriptionIssuanceService::class)->issue($visit, $actor, ['request_id' => (string) Str::uuid(), 'record_version' => $record->fresh()->lock_version,
            'allergy_version' => $visit->patient->allergyVersion(), 'clinical_review_confirmed' => true, 'release_to_patient' => true, 'supersedes_id' => $previous->id, 'revision_reason' => 'Replace the issued prescription after review.']);
        $this->get('/patient/dashboard')->assertOk()->assertSee('Nama Obat Koreksi')->assertDontSee('Nama Obat Lama')->assertSee('Tidak berlaku - SUPERSEDED');
    }

    private function patientUser(): User
    {
        $user = User::factory()->create(['account_type' => UserAccountType::Patient]);
        $user->assignRole('Patient');

        return $user;
    }

    private function createPrescription(
        User $patientUser,
        ?string $medicine,
        string $clinicName = 'Klinik Resep',
        MedicalRecordStatus $recordStatus = MedicalRecordStatus::Final,
        VisitStatus $visitStatus = VisitStatus::Completed,
        bool $released = true,
    ): ?PrescriptionItem {
        $clinic = Clinic::factory()->create(['name' => $clinicName]);
        $doctorUser = User::factory()->forClinic($clinic)->create(['name' => 'Budi Santoso']);
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->create(['clinic_id' => $clinic->id, 'user_id' => $doctorUser->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id, 'user_id' => $patientUser->id]);
        $service = Service::factory()->create(['clinic_id' => $clinic->id]);
        $doctor->services()->attach($service);
        $schedule = DoctorSchedule::factory()->for($doctor)->create();
        $appointment = Appointment::factory()->create([
            'doctor_schedule_id' => $schedule->id,
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
        ]);
        $visit = Visit::factory()->create([
            'appointment_id' => $appointment->id,
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'started_by_user_id' => $doctorUser->id,
            'completed_by_user_id' => $visitStatus === VisitStatus::Completed ? $doctorUser->id : null,
            'status' => $visitStatus,
            'completed_at' => $visitStatus === VisitStatus::Completed ? now() : null,
        ]);
        $medicalRecord = MedicalRecord::factory()->create([
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'created_by_user_id' => $doctorUser->id,
            'status' => $recordStatus,
            'doctor_notes' => 'CATATAN INTERNAL DOKTER',
            'finalized_at' => $recordStatus === MedicalRecordStatus::Final ? now() : null,
        ]);
        $prescription = Prescription::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'notes' => 'Diminum sesuai petunjuk dokter.',
        ]);

        if ($medicine === null) {
            return null;
        }

        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_name' => $medicine,
        ]);
        if ($recordStatus === MedicalRecordStatus::Final && in_array($visitStatus, [VisitStatus::Completed, VisitStatus::Interrupted], true)) {
            app(PrescriptionIssuanceService::class)->issue($visit, $doctorUser, ['request_id' => (string) Str::uuid(), 'record_version' => $medicalRecord->lock_version,
                'allergy_version' => $patient->allergyVersion(), 'clinical_review_confirmed' => true, 'release_to_patient' => $released]);
        }

        return $item;
    }
}
