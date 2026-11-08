<?php

namespace App\Http\Controllers\Receptionist;

use App\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Queue;
use App\QueueStatus;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Receptionist dashboard.
     */
    public function index(Request $request, SubscriptionService $subscriptionService): View
    {
        $clinicId = $request->user()->clinic_id;
        $timezone = $request->user()->clinic?->settings?->timezone ?? 'Asia/Jakarta';
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where('clinic_id', $clinicId)],
        ]);
        $date = CarbonImmutable::parse($validated['date'] ?? now($timezone)->toDateString(), $timezone);
        $doctorId = $validated['doctor_id'] ?? null;
        $queueQuery = Queue::query()
            ->where('clinic_id', $clinicId)
            ->whereDate('queue_date', $date)
            ->when($doctorId !== null, fn ($query) => $query->where('doctor_id', $doctorId));
        $statusCounts = (clone $queueQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $appointmentCounts = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_date', $date)
            ->when($doctorId !== null, fn ($query) => $query->where('doctor_id', $doctorId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $metrics = [
            'bookings' => (int) ($appointmentCounts[AppointmentStatus::Booked->value] ?? 0),
            'total_bookings' => $appointmentCounts->sum(),
            'not_checked_in' => (int) ($statusCounts[QueueStatus::Booked->value] ?? 0),
            'waiting' => (int) ($statusCounts[QueueStatus::Waiting->value] ?? 0),
            'called' => (int) ($statusCounts[QueueStatus::Called->value] ?? 0),
            'in_progress' => (int) ($statusCounts[QueueStatus::InProgress->value] ?? 0),
            'completed' => (int) ($statusCounts[QueueStatus::Completed->value] ?? 0),
        ];
        $queueRelations = ['appointment.patient', 'doctor.user:id,name', 'schedule'];
        $calledQueues = (clone $queueQuery)
            ->where('status', QueueStatus::Called->value)
            ->with($queueRelations)
            ->orderBy('doctor_id')
            ->orderBy('doctor_schedule_id')
            ->orderBy('queue_number')
            ->limit(4)
            ->get();
        $queues = (clone $queueQuery)
            ->whereIn('status', [QueueStatus::Booked->value, QueueStatus::Waiting->value])
            ->with($queueRelations)
            ->orderByRaw('CASE status WHEN ? THEN 0 ELSE 1 END', [QueueStatus::Waiting->value])
            ->orderBy('doctor_id')
            ->orderBy('doctor_schedule_id')
            ->orderBy('queue_number')
            ->limit(13)
            ->get();
        $activeQueue = $metrics['called'] === 1
            ? $calledQueues->first()
            : $queues->first(fn (Queue $queue): bool => $queue->status === QueueStatus::Waiting);
        $remainingQueues = $activeQueue?->status === QueueStatus::Waiting
            ? $queues->reject->is($activeQueue)->take(12)
            : $queues->take(12);
        $remainingQueueCount = $metrics['not_checked_in'] + $metrics['waiting']
            - ($activeQueue?->status === QueueStatus::Waiting ? 1 : 0);
        $doctors = Doctor::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->with('user:id,name')
            ->orderBy('id')
            ->get();
        $isToday = $date->isSameDay(CarbonImmutable::now($timezone));
        $clinic = $request->user()->clinic;
        $canWrite = $clinic === null || $subscriptionService->allowsOperationalWrites($clinic);

        return view('receptionist.dashboard', [
            'metrics' => $metrics,
            'calledQueues' => $calledQueues,
            'activeQueue' => $activeQueue,
            'queues' => $remainingQueues,
            'remainingQueueCount' => $remainingQueueCount,
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'isToday' => $isToday,
            'canWrite' => $canWrite,
            'isInteractive' => $isToday && $canWrite,
            'date' => $date->toDateString(),
            'timezone' => $timezone,
        ]);
    }
}
