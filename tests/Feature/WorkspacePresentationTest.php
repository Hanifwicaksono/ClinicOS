<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitWorkflowService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 03:00', 'UTC'));
    }

    public function test_sidebar_modules_follow_the_existing_roles_without_finance_shortcuts_for_doctors(): void
    {
        $clinic = Clinic::factory()->create();
        foreach (['Doctor', 'Patient', 'Receptionist', 'Clinic Admin', 'Platform Admin'] as $role) {
            $user = User::factory()->forClinic($clinic)->create();
            $user->assignRole($role);
            $this->actingAs($user);
            $navigation = Volt::test('layout.navigation');
            if ($role === 'Doctor') {
                $navigation->assertSee('Tugas pelayanan')->assertSee('Kontrol pasien')->assertSee('Resep pasien')->assertDontSeeHtml('href="'.route('patient-billing.index').'"')->assertDontSeeHtml('href="'.route('clinic-reports.index').'"');
            } elseif ($role === 'Patient') {
                $navigation->assertSee('Tagihan saya')->assertSee('Kontrol saya')->assertSee('Resep saya')->assertDontSee('Tugas pelayanan')->assertDontSee('Pengaturan klinik');
            } elseif ($role === 'Platform Admin') {
                $navigation->assertSee('Tenant klinik')->assertDontSee('Kontrol pasien')->assertDontSee('Tagihan pasien');
            } else {
                $navigation->assertSee('Tagihan pasien')->assertSee('Laporan klinik')->assertSee('Tugas pelayanan');
                if ($role === 'Clinic Admin') {
                    $navigation->assertDontSee('Resep pasien');
                }
            }
        }
    }

    public function test_paused_doctor_view_prioritizes_resume_and_only_lists_todays_sessions(): void
    {
        $visit = Visit::factory()->withConsistentRelations()->create();
        $this->pause($visit);
        DoctorSchedule::factory()->for($visit->doctor)->create(['day_of_week' => 0, 'start_time' => '00:00', 'end_time' => '23:59']);
        $response = $this->actingAs($visit->doctor->user)->get(route('care-continuity.show', $visit))->assertOk()->assertSee('Lanjutkan pemeriksaan')->assertDontSee('name="clinical_delay_assessment"', false)->assertDontSee('Tarik kesiapan kembali')->assertSee('type="hidden" name="doctor_schedule_id"', false);
        $this->assertSame([$visit->appointment->doctor_schedule_id], $response->viewData('schedules')->pluck('id')->all());
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Lihat catatan pemeriksaan'), strpos($html, 'data-pause-actions'));
    }

    public function test_readiness_controls_and_cross_day_review_appear_only_when_relevant(): void
    {
        $visit = Visit::factory()->withConsistentRelations()->create();
        $this->pause($visit);
        $actor = $visit->doctor->user;
        app(VisitWorkflowService::class)->changeWorkflow($visit, $actor, 'ready', $this->data($visit, ['doctor_schedule_id' => $visit->appointment->doctor_schedule_id]));
        $this->actingAs($actor)->get(route('care-continuity.show', $visit))->assertOk()->assertSee('Tarik kesiapan kembali')->assertDontSee('Tandai siap kembali');
        $visit->fresh()->resumeReservation->update(['resume_date' => '2026-11-18']);
        $visit->queue->update(['queue_date' => '2026-11-18']);
        $visit->appointment->update(['appointment_date' => '2026-11-18']);
        $this->get(route('care-continuity.show', $visit))->assertOk()->assertSee('name="clinical_delay_assessment"', false)->assertSee('Tandai siap kembali');
    }

    public function test_receptionist_only_sees_operational_readiness_and_doctor_dashboard_is_tenant_scoped(): void
    {
        $visit = Visit::factory()->withConsistentRelations()->create();
        $visit->patient->update(['name' => 'Pasien milik dokter']);
        $visit->medicalRecord->update(['subjective' => 'SOAP_PRIVATE_FOR_UI_TEST']);
        $this->pause($visit);
        $foreign = Visit::factory()->withConsistentRelations()->create();
        $foreign->patient->update(['name' => 'Pasien rahasia tenant lain']);
        $this->pause($foreign);
        $staff = User::factory()->forClinic($visit->clinic)->create();
        $staff->assignRole('Receptionist');
        $this->actingAs($staff)->get(route('care-continuity.show', $visit))->assertOk()->assertSee('Tandai siap kembali')->assertDontSee('/workflow/resume')->assertDontSee('SOAP_PRIVATE_FOR_UI_TEST');
        $this->actingAs($visit->doctor->user)->get(route('doctor.dashboard'))->assertOk()->assertSee('data-care-attention', false)->assertSee('Pasien milik dokter')->assertDontSee('Pasien rahasia tenant lain');
    }

    private function pause(Visit $visit): void
    {
        app(VisitWorkflowService::class)->changeWorkflow($visit, $visit->doctor->user, 'pause', $this->data($visit, ['pause_mode' => 'RELEASE', 'estimated_return_at' => '2026-11-19T12:00']));
    }

    private function data(Visit $visit, array $extra = []): array
    {
        $visit = $visit->fresh();

        return ['request_id' => (string) Str::uuid(), 'workflow_version' => $visit->workflow_version, 'record_version' => $visit->medicalRecord->lock_version, 'reason' => 'Reviewed synthetic care decision.', ...$extra];
    }
}
