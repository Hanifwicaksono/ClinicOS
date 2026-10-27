<?php

namespace Tests\Feature;

use App\MedicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\UserAccountType;
use App\VisitStatus;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPrescriptionDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_patient_sees_only_their_final_prescriptions_from_completed_visits(): void
    {
        $patientUser = $this->patientUser();
        $otherPatient = $this->patientUser();

        $this->createPrescription($patientUser, medicine: 'Paracetamol 500 mg', clinicName: 'Klinik Sehat');
        $this->createPrescription($patientUser, medicine: 'Vitamin Multi Klinik', clinicName: 'Klinik Kedua');
        $this->createPrescription($patientUser, medicine: 'Obat Masih Draft', recordStatus: MedicalRecordStatus::Draft);
        $this->createPrescription($patientUser, medicine: 'Obat Visit Berjalan', visitStatus: VisitStatus::InProgress);
        $this->createPrescription($otherPatient, medicine: 'Obat Rahasia Pasien Lain');

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
            ->assertDontSee('CATATAN INTERNAL DOKTER');
    }

    public function test_dashboard_handles_no_prescription_and_a_note_only_prescription(): void
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
            ->assertSee('Diminum sesuai petunjuk dokter.')
            ->assertSee('Tidak ada item obat pada resep ini.');
    }

    public function test_dashboard_reads_the_current_corrected_prescription(): void
    {
        $patientUser = $this->patientUser();
        $item = $this->createPrescription($patientUser, medicine: 'Nama Obat Lama');

        $this->actingAs($patientUser)
            ->get('/patient/dashboard')
            ->assertSee('Nama Obat Lama');

        $item->update(['medicine_name' => 'Nama Obat Koreksi']);

        $this->get('/patient/dashboard')
            ->assertOk()
            ->assertSee('Nama Obat Koreksi')
            ->assertDontSee('Nama Obat Lama');
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
    ): ?PrescriptionItem {
        $clinic = Clinic::factory()->create(['name' => $clinicName]);
        $doctorUser = User::factory()->create(['name' => 'Budi Santoso']);
        $doctor = Doctor::factory()->create(['clinic_id' => $clinic->id, 'user_id' => $doctorUser->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id, 'user_id' => $patientUser->id]);
        $service = Service::factory()->create(['clinic_id' => $clinic->id]);
        $appointment = Appointment::factory()->create([
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

        return PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_name' => $medicine,
        ]);
    }
}
