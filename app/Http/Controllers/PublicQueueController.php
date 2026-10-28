<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Queue;
use App\QueueStatus;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use App\Services\QueueEtaService;

class PublicQueueController extends Controller
{
    public function index(Clinic $clinic, QueueEtaService $etaService): View
    {
        abort_unless($clinic->is_active && ! in_array($clinic->slug, Clinic::RESERVED_SUBDOMAINS, true), 404);
        $clinic->load('settings');
        $date = CarbonImmutable::now($clinic->settings?->timezone ?? 'Asia/Jakarta')->toDateString();
        $queues = Queue::query()
            ->where('clinic_id', $clinic->id)
            ->whereDate('queue_date', $date)
            ->whereIn('status', [
                QueueStatus::Waiting->value,
                QueueStatus::Called->value,
                QueueStatus::InProgress->value,
            ])
            ->with(['doctor.user:id,name', 'schedule', 'appointment.service'])
            ->orderBy('doctor_schedule_id')
            ->orderBy('queue_number')
            ->get();

        $etas = $queues->mapWithKeys(fn (Queue $queue) => [
            $queue->id => $etaService->estimate($queue),
        ]);

        return view('public.queue', compact('clinic', 'queues', 'date', 'etas'));
    }
}
