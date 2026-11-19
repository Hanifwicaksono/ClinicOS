<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClinicReportExport;
use App\Models\PatientBill;
use App\Models\Queue;
use App\Models\User;
use App\Notifications\ClinicReportReadyNotification;
use App\Services\ClinicReportService;
use App\Services\PatientBillingService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 03:00:00', 'UTC'));
    }

    public function test_financial_export_is_private_aggregate_and_separates_pending_cash_and_billing(): void
    {
        [$bill,$admin,$staff] = $this->context();
        $billing = app(PatientBillingService::class);
        $reports = app(ClinicReportService::class);
        $billing->recordTransaction($bill, $staff, ['request_id' => (string) Str::uuid(), 'kind' => 'PAYMENT', 'method' => 'CASH', 'amount' => '50000', 'funds_received_confirmed' => true, 'verification_reference' => 'PRIVATE-BANK-REFERENCE']);
        $pending = $billing->recordTransaction($bill, $staff, ['request_id' => (string) Str::uuid(), 'kind' => 'PAYMENT', 'method' => 'TRANSFER', 'amount' => '25000']);
        $data = $this->data('finance');
        $report = $reports->request($bill->clinic, $admin, $data);
        $this->assertSame($report->id, $reports->request($bill->clinic, $admin, $data)->id);
        $this->assertDatabaseHas('jobs', ['queue' => 'reports']);
        $this->actingAs($admin)->get(route('clinic-reports.download', $report))->assertNotFound();
        $reports->generate($report->id);
        $report = $report->fresh();
        $this->assertSame('READY', $report->status);
        $this->assertSame(7500000, $report->metrics['net_billed_change_minor']);
        $this->assertSame(5000000, $report->metrics['verified_receipts_minor']);
        $this->assertSame(1, $report->metrics['payments_created_in_period_still_pending_count']);
        $contents = Storage::disk('local')->get($report->file_path);
        $this->assertStringContainsString('verified_receipts_minor,5000000,', $contents);
        foreach ([$bill->patient->name, $bill->patient->medical_record_number, 'Synthetic private assessment', 'PRIVATE-BANK-REFERENCE'] as $private) {
            $this->assertStringNotContainsString($private, $contents);
        }
        $this->actingAs($admin)->get(route('clinic-reports.download', $report))->assertOk()->assertDownload('clinic-report-finance-'.$report->id.'.csv');
        $before = $report->metrics;
        $billing->verifyTransaction($pending, $staff, 'verify', ['funds_received_confirmed' => true, 'verification_reference' => 'Actual credit confirmed']);
        $reports->generate($report->id);
        $this->assertSame($before, $report->fresh()->metrics);
        $this->assertSame($contents, Storage::disk('local')->get($report->file_path));
        $this->assertSame(1, $admin->notifications()->where('type', ClinicReportReadyNotification::class)->count());
    }

    public function test_receptionist_can_request_operational_but_not_financial_exports_and_cannot_read_another_users_artifact(): void
    {
        [$bill,$admin,$staff] = $this->context();
        $service = app(ClinicReportService::class);
        $this->actingAs($staff)->postJson(route('clinic-reports.store'), $this->data('finance'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('clinic-reports.store'), $this->data('queue'))->assertOk();
        $report = ClinicReportExport::query()->latest('id')->firstOrFail();
        $service->generate($report->id);
        $this->actingAs($admin)->get(route('clinic-reports.download', $report))->assertForbidden();
        $this->actingAs($bill->rootAppointment->doctor->user)->get(route('clinic-reports.index'))->assertForbidden();
        [, $foreign] = $this->context();
        $this->actingAs($foreign)->get(route('clinic-reports.show', $report))->assertForbidden();
    }

    public function test_local_exclusive_period_and_returned_event_are_distinct_from_queue_status(): void
    {
        [$bill,$admin,$staff] = $this->context();
        foreach (['2026-11-17 17:00:00', '2026-11-18 17:00:00'] as $date) {
            AuditLog::factory()->create(['clinic_id' => $bill->clinic_id, 'user_id' => $staff->id, 'entity_type' => Queue::class, 'entity_id' => $bill->rootAppointment->queue->id,
                'action' => 'queue.status_changed', 'metadata' => ['from' => 'SKIPPED', 'to' => 'WAITING'], 'created_at' => $date]);
        }
        $service = app(ClinicReportService::class);
        $report = $service->request($bill->clinic, $admin, $this->data('queue'));
        $service->generate($report->id);
        $report = $report->fresh();
        $this->assertSame('2026-11-17 17:00:00', $report->period_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-18 17:00:00', $report->period_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $report->metrics['returned_events_count']);
        $this->assertArrayNotHasKey('scheduled_queue_current_status_RETURNED_count', $report->metrics);
    }

    public function test_generation_and_download_recheck_current_access_and_expiry(): void
    {
        [$bill,$admin] = $this->context();
        $service = app(ClinicReportService::class);
        $report = $service->request($bill->clinic, $admin, [...$this->data('activity'), 'expires_at' => '2026-11-18T10:01']);
        $service->generate($report->id);
        $this->travel(2)->minutes();
        $this->actingAs($admin)->get(route('clinic-reports.download', $report))->assertGone();
        $second = $service->request($bill->clinic, $admin, $this->data('activity'));
        User::query()->whereKey($admin->id)->update(['is_active' => false]);
        $service->generate($second->id);
        $this->assertSame('FAILED', $second->fresh()->status);
        $this->assertSame('ACCESS_REVOKED', $second->fresh()->failure_code);
        Storage::disk('local')->assertMissing($service->path($second));
    }

    public function test_failed_storage_never_marks_a_partial_export_ready(): void
    {
        [$bill,$admin] = $this->context();
        $service = app(ClinicReportService::class);
        $report = $service->request($bill->clinic, $admin, $this->data('activity'));
        $disk = $this->createMock(Filesystem::class);
        $disk->method('put')->willReturn(false);
        $disk->method('delete')->willReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            $service->generate($report->id);
            $this->fail('Storage failure must fail the job.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('artifact could not be completed', $exception->getMessage());
        }
        $this->assertSame('FAILED', $report->fresh()->status);
        $this->assertNull($report->fresh()->file_path);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_csv_cells_reject_spreadsheet_formulas_without_changing_known_numeric_negative_values(): void
    {
        $this->assertSame("'=1+1", ClinicReportService::safeCsvCell('=1+1'));
        $this->assertSame("'\t@SUM(1)", ClinicReportService::safeCsvCell("\t@SUM(1)"));
        $this->assertSame(-2500, ClinicReportService::safeCsvCell(-2500));
    }

    /** @return array{PatientBill,User,User} */
    private function context(): array
    {
        Auth::logout();
        $bill = PatientBill::factory()->finalized()->create();
        $admin = User::factory()->forClinic($bill->clinic)->create();
        $admin->assignRole('Clinic Admin');
        $staff = User::factory()->forClinic($bill->clinic)->create();
        $staff->assignRole('Receptionist');

        return [$bill, $admin, $staff];
    }

    /** @return array<string,mixed> */
    private function data(string $kind): array
    {
        return ['request_id' => (string) Str::uuid(), 'kind' => $kind, 'range_from' => '2026-11-18', 'range_until' => '2026-11-18', 'expires_at' => '2026-11-20T17:00'];
    }
}
