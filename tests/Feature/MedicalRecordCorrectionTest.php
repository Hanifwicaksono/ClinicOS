<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecordRevision;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MedicalRecordCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_final_record_rejects_draft_edits_and_correction_without_reason(): void
    {
        $context = $this->completedVisitContext();
        $visit = $context['visit'];

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $visit), $this->medicalPayload('Perubahan tanpa koreksi'))
            ->assertForbidden();
        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.correct', $visit), $this->medicalPayload('Perubahan tanpa alasan'))
            ->assertSessionHasErrors('correction_reason');

        $this->assertSame('Diagnosis awal', $visit->medicalRecord->refresh()->assessment);
        $this->assertDatabaseCount('medical_record_revisions', 0);
    }

    public function test_versioned_correction_records_reason_before_and_after_snapshot(): void
    {
        $context = $this->completedVisitContext();
        $visit = $context['visit'];
        $payload = $this->medicalPayload('Diagnosis terkoreksi');
        $payload['correction_reason'] = 'Memperbaiki diagnosis berdasarkan hasil evaluasi lanjutan.';

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.correct', $visit), $payload)
            ->assertRedirect();

        $revision = MedicalRecordRevision::query()->sole();
        $this->assertSame('Diagnosis awal', $revision->before_data['record']['assessment']);
        $this->assertSame('Diagnosis terkoreksi', $revision->after_data['record']['assessment']);
        $this->assertSame($payload['correction_reason'], $revision->reason);
        $this->assertSame($context['doctor_user']->id, $revision->corrected_by_user_id);
        $this->assertSame('Diagnosis terkoreksi', $visit->medicalRecord->refresh()->assessment);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'medical_record.corrected',
            'entity_id' => $visit->medicalRecord->id,
        ]);
    }

    /** @return array{doctor_user: User, visit: Visit} */
    private function completedVisitContext(): array
    {
        $clinic = Clinic::factory()->create();
        $receptionist = User::factory()->forClinic($clinic)->create();
        $receptionist->assignRole('Receptionist');
        $doctorUser = User::factory()->forClinic($clinic)->create();
        $doctorUser->assignRole('Doctor');
        $doctor = Doctor::factory()->for($clinic)->for($doctorUser)->create();
        $service = Service::factory()->for($clinic)->create();
        $doctor->services()->attach($service);
        $date = now('Asia/Jakarta')->startOfDay();
        $schedule = DoctorSchedule::factory()->for($doctor)->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);
        $patient = Patient::factory()->for($clinic)->create();
        $booking = app(BookingService::class)->book($clinic, [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'doctor_schedule_id' => $schedule->id,
            'appointment_date' => $date->toDateString(),
            'idempotency_key' => Str::uuid()->toString(),
        ], $receptionist, 'RECEPTIONIST');
        $queue = $booking['appointment']->queue;
        app(QueueWorkflowService::class)->checkIn($queue, $receptionist);
        app(QueueWorkflowService::class)->call($queue, $receptionist);
        $this->actingAs($doctorUser)->post(route('doctor.visits.start', $queue));
        $visit = Visit::query()->sole();
        $this->actingAs($doctorUser)->put(
            route('doctor.visits.complete', $visit),
            $this->medicalPayload('Diagnosis awal'),
        );

        return ['doctor_user' => $doctorUser, 'visit' => $visit->refresh()];
    }

    /** @return array<string, mixed> */
    private function medicalPayload(string $assessment): array
    {
        return [
            'chief_complaint' => 'Nyeri tenggorokan sejak kemarin.',
            'subjective' => 'Nyeri saat menelan tanpa sesak napas.',
            'objective' => 'Keadaan umum baik dan faring hiperemis.',
            'assessment' => $assessment,
            'plan' => 'Terapi simptomatik dan kontrol bila keluhan menetap.',
            'diagnoses' => [
                ['code' => 'J02.9', 'name' => 'Faringitis akut', 'is_primary' => true],
            ],
            'treatments' => [
                ['name' => 'Konsultasi dan edukasi'],
            ],
            'prescription_items' => [
                [
                    'medicine_name' => 'Paracetamol 500 mg',
                    'dosage' => '1 tablet',
                    'frequency' => '3 kali sehari',
                    'quantity' => 10,
                    'unit' => 'tablet',
                ],
            ],
        ];
    }
}
