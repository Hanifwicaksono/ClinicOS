<?php

namespace Tests\Feature;

use App\MedicalRecordStatus;
use App\Models\AddonPeriod;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecordRevision;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Visit;
use App\PaymentStatus;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RmeGeneralDentalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_assigned_doctor_can_persist_general_rme_and_odontogram_in_draft(): void
    {
        $context = $this->inProgressVisitContext();
        $this->enableOdontogram($context['clinic']);
        $payload = $this->medicalPayload();
        $payload['odontogram'] = [
            ['tooth_number' => 11, 'condition' => 'caries'],
            ['tooth_number' => 26, 'condition' => 'filled'],
        ];

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $record = $context['visit']->medicalRecord->refresh();
        $this->assertSame(MedicalRecordStatus::Draft, $record->status);
        $this->assertSame('Nyeri gigi kanan atas.', $record->chief_complaint);
        $this->assertSame([
            ['tooth_number' => 11, 'condition' => 'caries'],
            ['tooth_number' => 26, 'condition' => 'filled'],
        ], $record->odontogram);

        $this->actingAs($context['doctor_user'])
            ->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()
            ->assertSee('data-persists="true"', false)
            ->assertSeeText('Kondisi tersimpan bersama draft atau finalisasi RME.');
    }

    public function test_invalid_general_and_odontogram_payload_is_rejected_without_mutating_record(): void
    {
        $context = $this->inProgressVisitContext();
        $this->enableOdontogram($context['clinic']);
        $payload = $this->medicalPayload();
        $payload['vital_signs'] = ['systolic' => 999];
        $payload['odontogram'] = [
            ['tooth_number' => 99, 'condition' => 'fractured'],
            ['tooth_number' => 99, 'condition' => 'healthy'],
        ];

        $this->actingAs($context['doctor_user'])
            ->from(route('doctor.visits.show', $context['visit']))
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertRedirect(route('doctor.visits.show', $context['visit']))
            ->assertSessionHasErrors([
                'vital_signs.systolic',
                'odontogram.0.tooth_number',
                'odontogram.0.condition',
                'odontogram.1.tooth_number',
            ]);

        $record = $context['visit']->medicalRecord->refresh();
        $this->assertNull($record->chief_complaint);
        $this->assertNull($record->odontogram);
    }

    public function test_critical_allergy_is_visible_and_prescription_requires_recorded_acknowledgement(): void
    {
        $context = $this->inProgressVisitContext();
        PatientAllergy::factory()->for($context['patient'])->critical()->create([
            'substance' => 'Penisilin',
            'reaction' => 'Anafilaksis',
        ]);

        $this->actingAs($context['doctor_user'])
            ->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()
            ->assertSeeText('Alergi kritis')
            ->assertSeeText('Penisilin')
            ->assertSeeText('Saya telah meninjau alergi kritis pasien');

        $payload = $this->medicalPayload();
        $payload['prescription_items'] = [[
            'medicine_name' => 'Amoxicillin 500 mg',
            'dosage' => '1 kapsul',
            'frequency' => '3 kali sehari',
            'quantity' => 15,
            'unit' => 'kapsul',
        ]];

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertSessionHasErrors('critical_allergies_acknowledged');

        $this->assertDatabaseCount('prescriptions', 0);

        $payload['critical_allergies_acknowledged'] = '1';
        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $record = $context['visit']->medicalRecord->refresh();
        $this->assertNotNull($record->allergy_acknowledged_at);
        $this->assertSame($context['doctor_user']->id, $record->allergy_acknowledged_by_user_id);
        $this->assertSame('Amoxicillin 500 mg', $record->prescription->items()->value('medicine_name'));
    }

    public function test_doctor_from_another_tenant_cannot_write_general_or_dental_record(): void
    {
        $context = $this->inProgressVisitContext();
        $otherClinic = Clinic::factory()->create();
        $otherDoctor = User::factory()->forClinic($otherClinic)->create();
        $otherDoctor->assignRole('Doctor');
        Doctor::factory()->for($otherClinic)->for($otherDoctor)->create();
        $payload = $this->medicalPayload();
        $payload['odontogram'] = [['tooth_number' => 11, 'condition' => 'caries']];

        $this->actingAs($otherDoctor)
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertForbidden();

        $this->assertNull($context['visit']->medicalRecord->refresh()->odontogram);
    }

    public function test_final_record_rejects_direct_edit_and_dental_correction_creates_revision(): void
    {
        $context = $this->inProgressVisitContext();
        $this->enableOdontogram($context['clinic']);
        $payload = $this->medicalPayload();
        $payload['odontogram'] = [['tooth_number' => 11, 'condition' => 'caries']];

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.complete', $context['visit']), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(MedicalRecordStatus::Final, $context['visit']->medicalRecord->refresh()->status);

        $payload['odontogram'] = [['tooth_number' => 11, 'condition' => 'filled']];
        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertForbidden();

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.correct', $context['visit']), $payload)
            ->assertSessionHasErrors('correction_reason');

        $payload['correction_reason'] = 'Karies telah ditangani dengan restorasi permanen.';
        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.correct', $context['visit']), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $revision = MedicalRecordRevision::query()->sole();
        $this->assertSame('caries', $revision->before_data['record']['odontogram'][0]['condition']);
        $this->assertSame('filled', $revision->after_data['record']['odontogram'][0]['condition']);
        $this->assertSame(MedicalRecordStatus::Final, $context['visit']->medicalRecord->refresh()->status);
    }

    public function test_general_rme_stays_writable_and_existing_odontogram_is_read_only_after_refund(): void
    {
        $context = $this->inProgressVisitContext();
        $this->enableOdontogram($context['clinic']);
        $payload = $this->medicalPayload();
        $payload['odontogram'] = [['tooth_number' => 11, 'condition' => 'caries']];

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertSessionHasNoErrors();

        AddonPeriod::query()->update(['revoked_at' => now()]);
        $this->actingAs($context['doctor_user'])
            ->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()
            ->assertSeeText('Histori di bawah ini hanya dapat dibaca.')
            ->assertSee('data-persists="false"', false);

        $payload['odontogram'][0]['condition'] = 'filled';
        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.save', $context['visit']), $payload)
            ->assertSessionHasErrors('odontogram');

        unset($payload['odontogram']);
        $payload['assessment'] = 'Rencana pemeriksaan umum dilanjutkan.';
        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.complete', $context['visit']), $payload)
            ->assertSessionHasNoErrors();

        $record = $context['visit']->medicalRecord->refresh();
        $this->assertSame(MedicalRecordStatus::Final, $record->status);
        $this->assertSame('caries', $record->odontogram[0]['condition']);
        $this->assertSame('Rencana pemeriksaan umum dilanjutkan.', $record->assessment);
    }

    private function enableOdontogram(Clinic $clinic): void
    {
        $plan = SubscriptionPlan::factory()->create(['code' => 'team', 'name' => 'Team']);
        $subscription = Subscription::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $basePayment = Payment::factory()->create(['clinic_id' => $clinic->id, 'status' => PaymentStatus::Paid]);
        $period = SubscriptionPeriod::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'payment_id' => $basePayment->id,
            'plan_code' => 'team',
            'plan_name' => 'Team',
            'source' => 'payment',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $payment = Payment::factory()->create(['clinic_id' => $clinic->id, 'status' => PaymentStatus::Paid]);
        AddonPeriod::factory()->create([
            'clinic_id' => $clinic->id,
            'subscription_period_id' => $period->id,
            'payment_id' => $payment->id,
            'starts_at' => now()->subDay(),
            'ends_at' => $period->ends_at,
        ]);
    }

    /** @return array{clinic: Clinic, doctor_user: User, patient: Patient, visit: Visit} */
    private function inProgressVisitContext(): array
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

        return [
            'clinic' => $clinic,
            'doctor_user' => $doctorUser,
            'patient' => $patient,
            'visit' => Visit::query()->sole(),
        ];
    }

    /** @return array<string, mixed> */
    private function medicalPayload(): array
    {
        return [
            'chief_complaint' => 'Nyeri gigi kanan atas.',
            'subjective' => 'Nyeri saat mengunyah sejak tiga hari.',
            'objective' => 'Karies tampak pada gigi 11.',
            'assessment' => 'Karies dentin tanpa tanda infeksi sistemik.',
            'plan' => 'Restorasi dan edukasi kebersihan mulut.',
            'physical_examination' => 'Keadaan umum baik.',
            'vital_signs' => [
                'systolic' => 120,
                'diastolic' => 80,
                'temperature_c' => 36.8,
            ],
        ];
    }
}
