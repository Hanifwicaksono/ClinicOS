<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\QueueStatus;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Doctor dashboard.
     */
    public function index(Request $request, SubscriptionService $subscriptionService): View
    {
        $user = $request->user();
        $timezone = $user->clinic?->settings?->timezone ?? 'Asia/Jakarta';
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($validated['date'] ?? now($timezone)->toDateString(), $timezone);
        $doctor = $user->doctor;
        $queueQuery = Queue::query()->where('doctor_id', $doctor?->id)->whereDate('queue_date', $date);
        $schedules = DoctorSchedule::query()
            ->where('doctor_id', $doctor?->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
        $statusCounts = (clone $queueQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $metrics = [
            'schedules' => $schedules->count(),
            'booked' => (int) ($statusCounts[QueueStatus::Booked->value] ?? 0),
            'waiting' => (int) ($statusCounts[QueueStatus::Waiting->value] ?? 0),
            'called' => (int) ($statusCounts[QueueStatus::Called->value] ?? 0),
            'in_progress' => (int) ($statusCounts[QueueStatus::InProgress->value] ?? 0),
            'completed' => (int) ($statusCounts[QueueStatus::Completed->value] ?? 0),
        ];
        $activeQueueCount = $metrics['waiting'] + $metrics['called'] + $metrics['in_progress'];
        $queues = $queueQuery
            ->whereIn('status', [QueueStatus::Waiting->value, QueueStatus::Called->value, QueueStatus::InProgress->value])
            ->with(['appointment.patient', 'appointment.visit', 'schedule'])
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [
                QueueStatus::InProgress->value,
                QueueStatus::Called->value,
            ])
            ->orderBy('queue_number')
            ->limit(13)
            ->get();
        $activeQueue = $queues->first();
        $isToday = $date->isSameDay(CarbonImmutable::now($timezone));
        $canWrite = $user->clinic === null || $subscriptionService->allowsOperationalWrites($user->clinic);

        return view('doctor.dashboard', [
            'metrics' => $metrics,
            'schedules' => $schedules,
            'activeQueue' => $activeQueue,
            'queues' => $activeQueue === null ? $queues->take(12) : $queues->skip(1)->take(12),
            'activeQueueCount' => $activeQueueCount,
            'isToday' => $isToday,
            'canWrite' => $canWrite,
            'isInteractive' => $isToday && $canWrite,
            'date' => $date->toDateString(),
            'timezone' => $timezone,
        ]);
    }
}
