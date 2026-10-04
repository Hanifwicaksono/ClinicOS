<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $timezone = $request->user()->clinic?->settings?->timezone ?? 'Asia/Jakarta';
        $from = CarbonImmutable::parse($validated['from'] ?? now($timezone)->subDays(6)->toDateString(), $timezone)->startOfDay();
        $to = CarbonImmutable::parse($validated['to'] ?? now($timezone)->toDateString(), $timezone)->endOfDay();
        $clinicId = $request->user()->clinic_id;
        $logs = AuditLog::query()
            ->where('clinic_id', $clinicId)
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->when(filled($validated['action'] ?? null), fn ($query) => $query->where('action', $validated['action']))
            ->when(filled($validated['actor'] ?? null), fn ($query) => $query->where('actor_name', 'like', '%'.$validated['actor'].'%'))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();
        $actions = AuditLog::query()
            ->where('clinic_id', $clinicId)
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'timezone' => $timezone,
        ]);
    }
}
