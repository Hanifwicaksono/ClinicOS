<?php

namespace App\Services;

use App\Models\Queue;
use App\Models\Visit;
use App\QueueStatus;
use App\VisitStatus;

class QueueEtaService
{
    private const int HistoryLimit = 20;

    /**
     * @return array{
     *     eta_minutes: int,
     *     eta_min_minutes: int,
     *     eta_max_minutes: int,
     *     confidence: 'low'|'medium'|'high'
     * }
     */
    public function estimate(Queue $queue): array
    {
        $durations = Visit::query()
            ->where('clinic_id', $queue->clinic_id)
            ->where('doctor_id', $queue->doctor_id)
            ->where('status', VisitStatus::Completed->value)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereColumn('completed_at', '>', 'started_at')
            ->latest('completed_at')
            ->limit(self::HistoryLimit)
            ->get(['started_at', 'completed_at'])
            ->map(fn (Visit $visit): int => (int) round($visit->started_at->diffInMinutes($visit->completed_at)));

        $peopleInFront = Queue::query()
            ->where('clinic_id', $queue->clinic_id)
            ->where('doctor_id', $queue->doctor_id)
            ->where('doctor_schedule_id', $queue->doctor_schedule_id)
            ->whereDate('queue_date', $queue->queue_date->toDateString())
            ->whereIn('status', [
                QueueStatus::Waiting->value,
                QueueStatus::Called->value,
                QueueStatus::InProgress->value,
            ])
            ->where('queue_number', '<', $queue->queue_number)
            ->count();

        $dataCount = $durations->count();
        $fallbackDuration = (int) $queue->appointment()->with('service')->firstOrFail()->service->duration_minutes;
        $averageDuration = $dataCount === 0 ? $fallbackDuration : (int) round((float) $durations->average());
        $minimumDuration = $dataCount === 0 ? $fallbackDuration : (int) $durations->min();
        $maximumDuration = $dataCount === 0 ? $fallbackDuration : (int) $durations->max();

        if ($dataCount >= 20) {
            $confidence = 'high';
        } elseif ($dataCount >= 10) {
            $confidence = 'medium';
        } else {
            $confidence = 'low';
        }

        return [
            'eta_minutes' => $averageDuration * $peopleInFront,
            'eta_min_minutes' => $minimumDuration * $peopleInFront,
            'eta_max_minutes' => $maximumDuration * $peopleInFront,
            'confidence' => $confidence,
        ];
    }
}
