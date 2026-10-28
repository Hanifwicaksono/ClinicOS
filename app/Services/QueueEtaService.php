<?php

namespace App\Services;
use App\Models\Queue;
use App\Models\Visit;
use App\QueueStatus;

class QueueEtaService
{
    public function estimate(Queue $queue): array
    {
        $visits = Visit::query()
        ->where('doctor_id', $queue->doctor_id)
        ->whereNotNull('completed_at')
        ->latest('completed_at')
        ->limit(20)
        ->get();
        $durations = $visits->map(fn (Visit $visit) => $visit->started_at->diffInMinutes($visit->completed_at));
        $avg = $durations->avg();
        $peopleInFront = Queue::query()
        ->where('doctor_id', $queue->doctor_id)
        ->where('queue_date', $queue->queue_date)
        ->whereDate('doctor_schedule_id', $queue->doctor_schedule_id)
        ->whereIn('status', [QueueStatus::Waiting->value, QueueStatus::InProgress->value, QueueStatus::Called->value])
        ->where('queue_number', '<', $queue->queue_number)
        ->count();
        $etaMinutes = $avg * $peopleInFront;
        $etaMax = $durations->max() * $peopleInFront;
        $etaMin = $durations->min()* $peopleInFront;
        $dataCount = $durations->count();

        if ($dataCount >= 20) {
            $confidence = 'high';
        } elseif ($dataCount >= 10) {
            $confidence = 'medium';
        } else {
            $confidence = 'low';
        }

        if ( $dataCount === 0 ) {
            $serviceMinutes = $queue->appointment->service->duration_minutes;
            $etaMinutes = $serviceMinutes * $peopleInFront;
            $etaMin = $serviceMinutes * $peopleInFront;
            $etaMax = $serviceMinutes * $peopleInFront;
        }

        return [
            'eta_minutes'     => $etaMinutes,
            'eta_min_minutes' => $etaMin,
            'eta_max_minutes' => $etaMax,
            'confidence'      => $confidence,
        ];
    }
}
