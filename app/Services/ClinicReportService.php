<?php

namespace App\Services;

use App\Jobs\BuildClinicReport;
use App\Models\Appointment;
use App\Models\AppointmentReplacement;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicReportExport;
use App\Models\IssuedPrescription;
use App\Models\PatientBillEntry;
use App\Models\PatientBillRequest;
use App\Models\PatientTransaction;
use App\Models\Queue;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitWorkflowEvent;
use App\Notifications\ClinicReportReadyNotification;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue as QueueDispatcher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClinicReportService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function canRequest(User $actor, Clinic $clinic, string $kind): bool
    {
        if (! $actor->is_active || ! $actor->hasVerifiedEmail() || $actor->clinic_id !== $clinic->id) {
            return false;
        }
        if (in_array($kind, ['service', 'finance'], true)) {
            return $actor->hasRole('Clinic Admin') && $actor->can('revenue.view');
        }

        return in_array($kind, ['activity', 'queue'], true) && $actor->hasAnyRole(['Clinic Admin', 'Receptionist']) && $actor->can('queue.view');
    }

    public function canRead(User $actor, ClinicReportExport $report): bool
    {
        return $actor->id === $report->requested_by_user_id && $this->canRequest($actor, $report->clinic, $report->kind);
    }

    /** @param array<string,mixed> $data */
    public function request(Clinic $clinic, User $actor, array $data): ClinicReportExport
    {
        return DB::transaction(function () use ($clinic, $actor, $data): ClinicReportExport {
            $clinic = Clinic::query()->with('settings')->lockForUpdate()->findOrFail($clinic->id);
            $actor = $actor->fresh();
            abort_unless($actor !== null && $this->canRequest($actor, $clinic, $data['kind']), 403);
            $fingerprint = hash('sha256', json_encode([$clinic->id, $actor->id, $data], JSON_THROW_ON_ERROR));
            $existing = ClinicReportExport::query()->where('request_id', $data['request_id'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->clinic_id === $clinic->id && hash_equals($existing->request_fingerprint, $fingerprint), 409);

                return $existing;
            }
            $timezone = $clinic->settings?->timezone ?? 'Asia/Jakarta';
            $from = CarbonImmutable::parse($data['range_from'], $timezone)->startOfDay();
            $until = CarbonImmutable::parse($data['range_until'], $timezone)->addDay()->startOfDay();
            $expires = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['expires_at'], $timezone);
            if ($until->lessThanOrEqualTo($from) || $expires->lessThanOrEqualTo(now())) {
                abort(422, 'Periode atau masa berlaku laporan tidak sah.');
            }
            $report = ClinicReportExport::query()->create(['clinic_id' => $clinic->id, 'requested_by_user_id' => $actor->id, 'kind' => $data['kind'], 'timezone' => $timezone, 'period_starts_at' => $from->utc(),
                'period_ends_at' => $until->utc(), 'expires_at' => $expires->utc(), 'request_id' => $data['request_id'], 'request_fingerprint' => $fingerprint]);
            QueueDispatcher::connection('database')->push((new BuildClinicReport($report->id))->onQueue('reports')->beforeCommit(), '', 'reports');
            $this->auditLogger->recordForClinic($clinic, 'clinic_report.requested', $report, ['kind' => $report->kind], $actor);

            return $report;
        }, 3);
    }

    public function generate(int $reportId): void
    {
        $path = null;
        $part = null;
        try {
            DB::transaction(function () use ($reportId, &$path, &$part): void {
                $report = ClinicReportExport::query()->lockForUpdate()->findOrFail($reportId);
                if ($report->status === 'READY') {
                    return;
                }
                $actor = $report->requestedBy;
                if ($report->expires_at->lessThanOrEqualTo(now()) || ! $this->canRead($actor, $report)) {
                    $report->forceFill(['status' => 'FAILED', 'failure_code' => $report->expires_at->lessThanOrEqualTo(now()) ? 'EXPIRED' : 'ACCESS_REVOKED'])->save();

                    return;
                }
                $metrics = $this->metrics($report);
                $generated = CarbonImmutable::now();
                $stream = fopen('php://temp', 'w+');
                try {
                    fwrite($stream, chr(239).chr(187).chr(191));
                    $rows = [['report_kind', $report->kind], ['timezone', $report->timezone], ['period_start_inclusive', $report->period_starts_at->setTimezone($report->timezone)->toIso8601String()],
                        ['period_end_exclusive', $report->period_ends_at->setTimezone($report->timezone)->toIso8601String()], ['generated_at', $generated->setTimezone($report->timezone)->toIso8601String()], ['definition_version', $report->definition_version ?? '2026-11-v1'], [], ['metric', 'value', 'unit']];
                    foreach ($metrics as $metric => $value) {
                        $rows[] = [$metric, $value, str_ends_with($metric, '_minor') ? 'IDR minor (100 = Rp1)' : 'count'];
                    }
                    foreach ($rows as $row) {
                        fputcsv($stream, array_map(self::safeCsvCell(...), $row), ',', chr(34), '', PHP_EOL);
                    }
                    rewind($stream);
                    $contents = stream_get_contents($stream);
                } finally {
                    fclose($stream);
                }
                $path = $this->path($report);
                $part = $path.'.'.Str::uuid().'.part';
                if (! Storage::disk('local')->put($part, $contents) || ! Storage::disk('local')->move($part, $path)) {
                    throw new \RuntimeException('Report artifact could not be completed.');
                }
                $notificationId = (string) Str::uuid();
                $report->forceFill(['status' => 'READY', 'file_path' => $path, 'file_hash' => hash('sha256', $contents), 'metrics' => $metrics, 'generated_at' => $generated, 'failure_code' => null, 'notification_id' => $notificationId])->save();
                $notification = new ClinicReportReadyNotification($report->id);
                $notification->id = $notificationId;
                Notification::sendNow($actor, $notification, ['database']);
                $this->auditLogger->recordForClinic($report->clinic, 'clinic_report.ready', $report, ['kind' => $report->kind, 'definition_version' => $report->definition_version], $actor);
            });
        } catch (\Throwable $exception) {
            if ($part !== null) {
                Storage::disk('local')->delete($part);
            }
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            $report = ClinicReportExport::query()->find($reportId);
            if ($report !== null && $report->status !== 'READY') {
                $report->forceFill(['status' => 'FAILED', 'failure_code' => 'GENERATION_FAILED', 'file_path' => null, 'file_hash' => null])->save();
            }
            throw $exception;
        }
    }

    public function path(ClinicReportExport $report): string
    {
        return 'clinic-reports/'.$report->clinic_id.'/'.$report->id.'.csv';
    }

    public static function safeCsvCell(string|int $cell): string|int
    {
        if (is_int($cell)) {
            return $cell;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $cell) ? "'".$cell : $cell;
    }

    /** Counts are measured in one generation transaction; date bounds remain the requested clinic-local interval. @return array<string,int> */
    private function metrics(ClinicReportExport $report): array
    {
        $clinic = $report->clinic_id;
        $from = $report->period_starts_at;
        $until = $report->period_ends_at;
        $visits = Visit::query()->where('clinic_id', $clinic);
        $entries = PatientBillEntry::query()->where('clinic_id', $clinic)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $until);
        if ($report->kind === 'activity') {
            $bookings = Appointment::query()->where('clinic_id', $clinic)->where('created_at', '>=', $from)->where('created_at', '<', $until);

            return ['bookings_created_count' => (clone $bookings)->count(), 'patients_on_created_bookings_count' => (clone $bookings)->distinct()->count('patient_id'),
                'visit_episodes_started_count' => (clone $visits)->where('started_at', '>=', $from)->where('started_at', '<', $until)->count(),
                'visit_episodes_completed_count' => (clone $visits)->where('status', VisitStatus::Completed)->where('completed_at', '>=', $from)->where('completed_at', '<', $until)->count(),
                'reschedule_events_count' => AppointmentReplacement::query()->where('clinic_id', $clinic)->where('created_at', '>=', $from)->where('created_at', '<', $until)->count(),
                'pause_events_count' => $this->workflowCount($clinic, 'pause', $from, $until), 'resume_events_count' => $this->workflowCount($clinic, 'resume', $from, $until),
                'interruption_events_count' => $this->workflowCount($clinic, 'interrupt', $from, $until),
                'prescriptions_issued_count' => IssuedPrescription::query()->where('clinic_id', $clinic)->where('issued_at', '>=', $from)->where('issued_at', '<', $until)->count()];
        }
        if ($report->kind === 'queue') {
            $queues = Queue::query()->where('clinic_id', $clinic)->whereDate('queue_date', '>=', $from->setTimezone($report->timezone)->toDateString())->whereDate('queue_date', '<', $until->setTimezone($report->timezone)->toDateString());
            $metrics = ['scheduled_queue_count' => (clone $queues)->count()];
            foreach (QueueStatus::cases() as $status) {
                $metrics['scheduled_queue_current_status_'.$status->value.'_count'] = (clone $queues)->where('status', $status)->count();
            }
            $events = AuditLog::query()->where('clinic_id', $clinic)->where('action', 'queue.status_changed')->where('created_at', '>=', $from)->where('created_at', '<', $until);
            $metrics['checked_in_events_count'] = (clone $events)->where('metadata->from', 'BOOKED')->where('metadata->to', 'WAITING')->count();
            $metrics['returned_events_count'] = (clone $events)->where('metadata->from', 'SKIPPED')->where('metadata->to', 'WAITING')->count();
            $metrics['called_events_count'] = (clone $events)->where('metadata->to', 'CALLED')->count();

            return $metrics;
        }
        if ($report->kind === 'service') {
            return ['completed_service_episode_count' => (clone $visits)->where('status', VisitStatus::Completed)->where('completed_at', '>=', $from)->where('completed_at', '<', $until)->count(),
                'protected_continuation_episodes_started_count' => (clone $visits)->whereNotNull('quota_origin_visit_id')->where('started_at', '>=', $from)->where('started_at', '<', $until)->count(),
                'completed_episode_snapshot_value_not_cash_minor' => (int) (clone $visits)->where('status', VisitStatus::Completed)->where('completed_at', '>=', $from)->where('completed_at', '<', $until)->selectRaw('COALESCE(ROUND(SUM(total_amount * 100),0),0) AS value')->value('value'),
                'bills_finalized_count' => (clone $entries)->where('kind', 'BILL_FINALIZED')->count(), 'net_billed_change_minor' => (int) (clone $entries)->sum('billed_delta_minor')];
        }

        return ['net_billed_change_minor' => (int) (clone $entries)->sum('billed_delta_minor'), 'net_allocated_payment_change_minor' => (int) (clone $entries)->sum('paid_delta_minor'),
            'net_writeoff_change_minor' => (int) (clone $entries)->sum('writeoff_delta_minor'), 'verified_receipts_minor' => (int) (clone $entries)->where('kind', 'CASH_RECEIVED')->sum('cash_delta_minor'),
            'verified_deposits_minor' => (int) (clone $entries)->where('kind', 'CASH_RECEIVED')->whereHas('transaction', fn ($query) => $query->where('kind', 'DEPOSIT'))->sum('cash_delta_minor'),
            'deposit_allocations_not_new_cash_minor' => (int) (clone $entries)->where('kind', 'DEPOSIT_ALLOCATED')->sum('paid_delta_minor'),
            'actual_refunds_out_minor' => -(int) (clone $entries)->where('kind', 'REFUND_COMPLETED')->sum('cash_delta_minor'),
            'receipt_corrections_minor' => (int) (clone $entries)->where('kind', 'PAYMENT_REVERSAL')->sum('cash_delta_minor'), 'net_cash_minor' => (int) (clone $entries)->sum('cash_delta_minor'),
            'unallocated_funds_change_minor' => (int) (clone $entries)->sum('cash_delta_minor') - (int) (clone $entries)->sum('paid_delta_minor'),
            'payments_created_in_period_still_pending_count' => PatientTransaction::query()->where('clinic_id', $clinic)->where('created_at', '>=', $from)->where('created_at', '<', $until)->where('status', 'PENDING_VERIFICATION')->count(),
            'refunds_approved_in_period_still_pending_count' => PatientBillRequest::query()->where('clinic_id', $clinic)->where('kind', 'REFUND')->where('status', 'APPROVED')->where('approved_at', '>=', $from)->where('approved_at', '<', $until)->count()];
    }

    private function workflowCount(int $clinic, string $action, CarbonImmutable $from, CarbonImmutable $until): int
    {
        return VisitWorkflowEvent::query()->where('clinic_id', $clinic)->where('action', $action)->where('created_at', '>=', $from)->where('created_at', '<', $until)->count();
    }
}
