<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Queue;
use App\QueueStatus;
use App\Services\QueueWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Queue::class);
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'doctor_id' => ['nullable', 'integer'],
            'doctor_schedule_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(QueueStatus::class)],
        ]);
        $user = $request->user();
        $timezone = $user->clinic?->settings?->timezone ?? 'Asia/Jakarta';
        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'], $timezone)->toDateString()
            : CarbonImmutable::now($timezone)->toDateString();

        $baseQuery = Queue::query()
            ->where('clinic_id', $user->clinic_id)
            ->whereDate('queue_date', $date)
            ->when($user->hasRole('Doctor'), function ($query) use ($user): void {
                $query->whereHas('doctor', fn ($doctorQuery) => $doctorQuery->where('user_id', $user->id));
            })
            ->when(! $user->hasRole('Doctor') && isset($validated['doctor_id']), fn ($query) => $query->where('doctor_id', $validated['doctor_id']))
            ->when(isset($validated['doctor_schedule_id']), fn ($query) => $query->where('doctor_schedule_id', $validated['doctor_schedule_id']));

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $queues = $baseQuery
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->with(['appointment.patient', 'appointment.visit', 'doctor.user:id,name', 'schedule'])
            ->orderBy('doctor_schedule_id')
            ->orderBy('queue_number')
            ->paginate(20)
            ->withQueryString();
        $doctors = Doctor::query()
            ->where('clinic_id', $user->clinic_id)
            ->where('is_active', true)
            ->when($user->hasRole('Doctor'), fn ($query) => $query->where('user_id', $user->id))
            ->with(['user:id,name', 'schedules'])
            ->orderBy('id')
            ->get();

        return view('queues.index', compact('queues', 'doctors', 'date', 'statusCounts'));
    }

    public function checkIn(Request $request, Queue $queue, QueueWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('update', $queue);
        $workflow->checkIn($queue, $request->user());

        return back()->with('status', "Antrean {$queue->display_number} sudah check-in.");
    }

    public function call(Request $request, Queue $queue, QueueWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('call', $queue);
        $workflow->call($queue, $request->user());

        return back()->with('status', "Antrean {$queue->display_number} sedang dipanggil.");
    }

    public function skip(Request $request, Queue $queue, QueueWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('call', $queue);
        $workflow->skip($queue, $request->user());

        return back()->with('status', "Antrean {$queue->display_number} dilewati sementara.");
    }

    public function returnToWaiting(Request $request, Queue $queue, QueueWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('call', $queue);
        $workflow->returnToWaiting($queue, $request->user());

        return back()->with('status', "Antrean {$queue->display_number} dikembalikan ke daftar tunggu.");
    }

    public function cancel(Request $request, Queue $queue, QueueWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('update', $queue);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $workflow->cancel($queue, $request->user(), $validated['reason'] ?? null);

        return back()->with('status', "Antrean {$queue->display_number} dibatalkan.");
    }

    public function markNoShow(Request $request, Queue $queue, QueueWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('update', $queue);
        $workflow->markNoShow($queue, $request->user());

        return back()->with('status', "Antrean {$queue->display_number} ditandai tidak hadir.");
    }
}
