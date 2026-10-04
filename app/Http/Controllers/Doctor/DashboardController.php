<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\QueueStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Doctor dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $timezone = $user->clinic?->settings?->timezone ?? 'Asia/Jakarta';
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($validated['date'] ?? now($timezone)->toDateString(), $timezone);
        $doctor = $user->doctor;
        $queueQuery = Queue::query()->where('doctor_id', $doctor?->id)->whereDate('queue_date', $date);
        $metrics = [
            'schedules' => DoctorSchedule::query()->where('doctor_id', $doctor?->id)->where('day_of_week', $date->dayOfWeek)->where('is_active', true)->count(),
            'waiting' => (clone $queueQuery)->where('status', QueueStatus::Waiting->value)->count(),
            'in_progress' => (clone $queueQuery)->where('status', QueueStatus::InProgress->value)->count(),
            'completed' => (clone $queueQuery)->where('status', QueueStatus::Completed->value)->count(),
        ];
        $queues = $queueQuery
            ->whereIn('status', [QueueStatus::Waiting->value, QueueStatus::Called->value, QueueStatus::InProgress->value])
            ->with(['appointment.patient', 'appointment.visit', 'schedule'])
            ->orderBy('queue_number')
            ->limit(12)
            ->get();

        return view('doctor.dashboard', [
            'metrics' => $metrics,
            'queues' => $queues,
            'date' => $date->toDateString(),
        ]);
    }
}
