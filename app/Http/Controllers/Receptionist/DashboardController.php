<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Queue;
use App\QueueStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Receptionist dashboard.
     */
    public function index(Request $request): View
    {
        $clinicId = $request->user()->clinic_id;
        $timezone = $request->user()->clinic?->settings?->timezone ?? 'Asia/Jakarta';
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($validated['date'] ?? now($timezone)->toDateString(), $timezone);
        $queueQuery = Queue::query()->where('clinic_id', $clinicId)->whereDate('queue_date', $date);
        $metrics = [
            'bookings' => Appointment::query()->where('clinic_id', $clinicId)->whereDate('appointment_date', $date)->count(),
            'checked_in' => (clone $queueQuery)->whereNotNull('checked_in_at')->count(),
            'waiting' => (clone $queueQuery)->where('status', QueueStatus::Waiting->value)->count(),
            'called' => (clone $queueQuery)->whereIn('status', [QueueStatus::Called->value, QueueStatus::InProgress->value])->count(),
        ];
        $queues = $queueQuery
            ->with(['appointment.patient', 'doctor.user:id,name'])
            ->orderBy('doctor_id')
            ->orderBy('queue_number')
            ->limit(12)
            ->get();

        return view('receptionist.dashboard', [
            'metrics' => $metrics,
            'queues' => $queues,
            'date' => $date->toDateString(),
        ]);
    }
}
