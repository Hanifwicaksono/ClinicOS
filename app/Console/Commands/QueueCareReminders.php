<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\Visit;
use App\Services\ClinicNotificationService;
use App\VisitStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('care:queue-reminders')]
#[Description('Store and queue deduplicated pause reminders for authorized clinicians')]
class QueueCareReminders extends Command
{
    public function handle(ClinicNotificationService $notifications): int
    {
        $count = 0;
        Visit::query()->where('status', VisitStatus::Paused)->whereNotNull('paused_until')->where('paused_until', '<=', now())->eachById(function (Visit $visit) use ($notifications, &$count): void {
            DB::transaction(function () use ($visit, $notifications, &$count): void {
                Clinic::query()->lockForUpdate()->findOrFail($visit->clinic_id);
                $locked = Visit::query()->lockForUpdate()->findOrFail($visit->id);
                if ($locked->status !== VisitStatus::Paused || $locked->paused_until === null || $locked->paused_until->isFuture()) {
                    return;
                }
                $notifications->careWorkflow($locked, $locked->doctor->user, 'pause_overdue', $locked->pause_generation);
                $count++;
                $delay = $locked->clinic->settings?->pause_escalation_minutes ?? 15;
                if ($locked->paused_until->addMinutes($delay)->isFuture()) {
                    return;
                }
                $lead = $locked->caseLeads()->where('purpose', 'CARE_COORDINATION')->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                    ->whereHas('doctor', fn ($query) => $query->where('is_active', true)->whereHas('user', fn ($users) => $users->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'Doctor'))))
                    ->with('doctor.user')->orderByRaw("CASE WHEN role='PRIMARY' THEN 0 ELSE 1 END")->first();
                $notifications->careWorkflow($locked, $lead?->doctor->user ?? $locked->doctor->user, $lead !== null ? 'pause_escalated' : 'pause_unassigned', $locked->pause_generation);
            }, 3);
        });
        $this->info('Checked '.$count.' paused-care reminder context(s).');

        return self::SUCCESS;
    }
}
