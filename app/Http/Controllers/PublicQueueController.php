<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Queue;
use App\QueueStatus;
use App\Services\QueueEtaService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class PublicQueueController extends Controller
{
    public function index(Clinic $clinic, QueueEtaService $etaService): View
    {
        abort_unless($clinic->is_active && ! in_array($clinic->slug, Clinic::RESERVED_SUBDOMAINS, true), 404);

        return $this->renderQueueScreen($clinic, $etaService);
    }

    public function display(string $clinicSlugOrToken, QueueEtaService $etaService): View
    {
        $clinic = Clinic::query()
            ->where('slug', $clinicSlugOrToken)
            ->when(is_numeric($clinicSlugOrToken), fn ($query) => $query->orWhere('id', (int) $clinicSlugOrToken))
            ->first();

        abort_unless($clinic && $clinic->is_active && ! in_array($clinic->slug, Clinic::RESERVED_SUBDOMAINS, true), 404);

        return $this->renderQueueScreen($clinic, $etaService);
    }

    private function renderQueueScreen(Clinic $clinic, QueueEtaService $etaService): View
    {
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
