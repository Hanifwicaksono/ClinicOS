<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordRevision;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use App\Services\QueueWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
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
            ->put(route('doctor.visits.save', $visit), $this->medicalPayload($visit, 'Perubahan tanpa koreksi'))
            ->assertForbidden();
        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.correct', $visit), $this->medicalPayload($visit, 'Perubahan tanpa alasan'))
            ->assertSessionHasErrors('correction_reason');

        $this->assertSame('Diagnosis awal', $visit->medicalRecord->refresh()->assessment);
        $this->assertDatabaseCount('medical_record_revisions', 0);
    }

    public function test_versioned_correction_records_reason_before_and_after_snapshot(): void
    {
        $context = $this->completedVisitContext();
        $visit = $context['visit'];
        $payload = $this->medicalPayload($visit, 'Diagnosis terkoreksi');
        $payload['correction_reason'] = 'Memperbaiki diagnosis berdasarkan hasil evaluasi lanjutan.';
        $payload['prescription_items'][0]['medicine_name'] = 'Ibuprofen 400 mg';
        $payload['doctor_notes'] = 'Pasien diminta kembali bila gejala memburuk.';

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.correct', $visit), $payload)
            ->assertRedirect();

        $revision = MedicalRecordRevision::query()->sole();
        $this->assertSame('Diagnosis awal', $revision->before_data['record']['assessment']);
        $this->assertSame('Diagnosis terkoreksi', $revision->after_data['record']['assessment']);
        $this->assertSame('Paracetamol 500 mg', $revision->before_data['prescription_items'][0]['medicine_name']);
        $this->assertSame('Ibuprofen 400 mg', $revision->after_data['prescription_items'][0]['medicine_name']);
        $this->assertSame($payload['correction_reason'], $revision->reason);
        $this->assertSame($context['doctor_user']->id, $revision->corrected_by_user_id);
        $this->assertSame('Diagnosis terkoreksi', $visit->medicalRecord->refresh()->assessment);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'medical_record.corrected',
            'entity_id' => $visit->medicalRecord->id,
        ]);

        $this->actingAs($context['doctor_user'])
            ->get(route('doctor.visits.show', $visit))
            ->assertOk()
            ->assertSeeText('Finalized · Modification')
            ->assertSeeText('Modification 1')
            ->assertSeeText($payload['correction_reason'])
            ->assertSee('<details class="mt-4">', false)
            ->assertDontSee('<details class="mt-4" open>', false)
            ->assertSeeText('Lihat semua perubahan')
            ->assertSeeText('Assessment')
            ->assertSeeText('Diagnosis awal')
            ->assertSeeText('Diagnosis terkoreksi')
            ->assertSeeText('Catatan dokter')
            ->assertSeeText('Pasien diminta kembali bila gejala memburuk.')
            ->assertSeeText('Resep')
            ->assertSeeText('Paracetamol 500 mg')
            ->assertSeeText('Ibuprofen 400 mg')
            ->assertSeeText('Lihat detail')
            ->assertSee('truncate', false)
            ->assertSee('scrollWidth > this.$refs.preview.clientWidth', false)
            ->assertSee('open:col-span-full', false)
            ->assertSee('[overflow-wrap:anywhere]', false);
    }

    public function test_history_hides_revision_without_medical_record_changes(): void
    {
        $context = $this->completedVisitContext();
        $visit = $context['visit'];
        $payload = $this->medicalPayload($visit, 'Diagnosis awal');
        $payload['correction_reason'] = 'Konfirmasi data tanpa perubahan isi rekam medis.';

        $this->actingAs($context['doctor_user'])
            ->put(route('doctor.visits.correct', $context['visit']), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('medical_record_revisions', 1);

        $this->actingAs($context['doctor_user'])
            ->get(route('doctor.visits.show', $context['visit']))
            ->assertOk()
            ->assertDontSeeText('Version history / Modification')
            ->assertDontSeeText('Modification 1')
            ->assertDontSeeText($payload['correction_reason']);
    }

    public function test_removed_final_photos_remain_readable_only_through_their_own_authorized_history(): void
    {
        Storage::fake('local');
        $context = $this->completedVisitContext();
        $visit = $context['visit'];
        $payload = [...$this->medicalPayload($visit, 'Synthetic photo correction'),
            'correction_reason' => 'Synthetic attachment history regression',
        ];
        $this->putJson(route('doctor.visits.correct', $visit), [...$payload,
            'photos' => [UploadedFile::fake()->image('history.png', 1, 1)],
        ])->assertOk();
        $addedRevision = $visit->medicalRecord->revisions()->sole();
        $path = $visit->medicalRecord->refresh()->photos[0]['path'];
        $this->putJson(route('doctor.visits.correct', $visit), [...$payload, ...$this->recordVersions($visit), 'removed_photos' => [0]])->assertOk();
        $removedRevision = $visit->medicalRecord->revisions()->latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], $visit->medicalRecord->refresh()->photos);
        $this->get(route('doctor.visits.photos.show', [$visit, 0]))->assertNotFound();
        $beforeUrl = route('doctor.visits.photos.show', ['visit' => $visit, 'photo' => 0,
            'revision' => $removedRevision->id, 'snapshot' => 'before',
        ]);
        $this->get($beforeUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('doctor.visits.photos.show', ['visit' => $visit, 'photo' => 0,
            'revision' => $addedRevision->id, 'snapshot' => 'after',
        ]))->assertOk();
        $this->get(route('doctor.visits.show', $visit))->assertOk()->assertSee('data-revision-photo', false);
        $this->getJson($beforeUrl.'&snapshot=invalid')->assertUnprocessable();
        $this->get($beforeUrl.'&revision=999999')->assertNotFound();
        $otherAppointment = Appointment::factory()->for($visit->clinic)->for($visit->doctor)->for($visit->patient)
            ->for($visit->service)->for($visit->appointment->schedule, 'schedule')->create();
        $otherRecord = MedicalRecord::factory()->for(Visit::factory()->for($otherAppointment))->create();
        $foreignRevision = $otherRecord->revisions()->create([
            'corrected_by_user_id' => $context['doctor_user']->id, 'reason' => 'Synthetic foreign record',
            'before_data' => ['record' => ['photos' => [['path' => $path]]]], 'after_data' => [],
        ]);
        $this->get($beforeUrl.'&revision='.$foreignRevision->id)->assertNotFound();

        foreach (['Clinic Admin', 'Receptionist', 'Patient', 'Platform Admin', 'Doctor'] as $role) {
            $actor = User::factory()->forClinic($visit->clinic)->create();
            $actor->assignRole($role);
            if ($role === 'Doctor') {
                Doctor::factory()->for($visit->clinic)->for($actor, 'user')->create();
            }
            $this->actingAs($actor)->get($beforeUrl)->assertForbidden();
        }
        $otherClinic = Clinic::factory()->create();
        $otherClinicDoctor = User::factory()->forClinic($otherClinic)->create();
        $otherClinicDoctor->assignRole('Doctor');
        Doctor::factory()->for($otherClinic)->for($otherClinicDoctor, 'user')->create();
        $this->actingAs($otherClinicDoctor)->get($beforeUrl)->assertForbidden();
        $this->get($beforeUrl.'&revision=invalid')->assertForbidden();
    }

    public function test_self_delete_preserves_clinical_history_and_session_even_after_doctor_role_is_removed(): void
    {
        $context = $this->completedVisitContext();
        $doctor = $context['doctor_user'];
        $doctor->removeRole('Doctor');
        $this->actingAs($doctor);

        Volt::test('profile.delete-user-form')->set('password', 'password')->call('deleteUser')
            ->assertHasErrors('password')->assertSee('menonaktifkan akses')->assertNoRedirect();

        $this->assertAuthenticatedAs($doctor);
        $this->assertModelExists($doctor);
        $this->assertModelExists($context['visit']);
        $this->assertModelExists($context['visit']->medicalRecord);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.logout']);
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
            $this->medicalPayload($visit, 'Diagnosis awal'),
        );

        return ['doctor_user' => $doctorUser, 'visit' => $visit->refresh()];
    }

    /** @return array<string, mixed> */
    private function medicalPayload(Visit $visit, string $assessment): array
    {
        return [
            ...$this->recordVersions($visit),
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
