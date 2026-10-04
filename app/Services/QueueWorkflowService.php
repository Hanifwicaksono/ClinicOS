<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\User;
use App\QueueStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueWorkflowService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ClinicNotificationService $notificationService,
    ) {}

    public function checkIn(Queue $queue, User $actor): Queue
    {
        return $this->transition($queue, $actor, QueueStatus::Booked, QueueStatus::Waiting, [
            'checked_in_at' => now(),
        ]);
    }

    public function call(Queue $queue, User $actor): Queue
    {
        return DB::transaction(function () use ($queue, $actor): Queue {
            DoctorSchedule::query()->lockForUpdate()->findOrFail($queue->doctor_schedule_id);
            $lockedQueue = Queue::query()->lockForUpdate()->findOrFail($queue->id);

            $this->ensureOperationalDate($lockedQueue);
            $this->ensureStatus($lockedQueue, QueueStatus::Waiting);

            $hasActiveCall = Queue::query()
                ->where('clinic_id', $lockedQueue->clinic_id)
                ->where('doctor_id', $lockedQueue->doctor_id)
                ->where('doctor_schedule_id', $lockedQueue->doctor_schedule_id)
                ->whereDate('queue_date', $lockedQueue->queue_date)
                ->whereIn('status', [QueueStatus::Called->value, QueueStatus::InProgress->value])
                ->where('id', '!=', $lockedQueue->id)
                ->exists();

            if ($hasActiveCall) {
                throw ValidationException::withMessages([
                    'queue' => 'Selesaikan atau lewati panggilan aktif sebelum memanggil antrean berikutnya.',
                ]);
            }

            $nextQueueId = Queue::query()
                ->where('clinic_id', $lockedQueue->clinic_id)
                ->where('doctor_id', $lockedQueue->doctor_id)
                ->where('doctor_schedule_id', $lockedQueue->doctor_schedule_id)
                ->whereDate('queue_date', $lockedQueue->queue_date)
                ->where('status', QueueStatus::Waiting->value)
                ->orderBy('queue_number')
                ->value('id');

            if ($nextQueueId !== $lockedQueue->id) {
                throw ValidationException::withMessages([
                    'queue' => 'Panggil nomor antrean paling awal yang masih menunggu.',
                ]);
            }

            return $this->applyTransition($lockedQueue, $actor, QueueStatus::Called, [
                'called_at' => now(),
            ]);
        }, 3);
    }

    public function skip(Queue $queue, User $actor): Queue
    {
        return $this->transition($queue, $actor, QueueStatus::Called, QueueStatus::Skipped, [
            'skipped_at' => now(),
        ]);
    }

    public function returnToWaiting(Queue $queue, User $actor): Queue
    {
        return $this->transition($queue, $actor, QueueStatus::Skipped, QueueStatus::Waiting);
    }

    public function cancel(Queue $queue, User $actor, ?string $reason = null): Queue
    {
        return DB::transaction(function () use ($queue, $actor, $reason): Queue {
            $lockedQueue = Queue::query()->lockForUpdate()->findOrFail($queue->id);

            if (! in_array($lockedQueue->status, [
                QueueStatus::Booked,
                QueueStatus::Waiting,
                QueueStatus::Called,
                QueueStatus::Skipped,
            ], true)) {
                $this->throwInvalidTransition($lockedQueue);
            }

            $lockedQueue->appointment()->update([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $this->applyTransition($lockedQueue, $actor, QueueStatus::Cancelled, [
                'cancelled_at' => now(),
            ], ['reason' => $reason]);
        }, 3);
    }

    public function markNoShow(Queue $queue, User $actor): Queue
    {
        return DB::transaction(function () use ($queue, $actor): Queue {
            $lockedQueue = Queue::query()
                ->with(['appointment.clinic.settings', 'schedule'])
                ->lockForUpdate()
                ->findOrFail($queue->id);

            if (! in_array($lockedQueue->status, [
                QueueStatus::Booked,
                QueueStatus::Waiting,
                QueueStatus::Called,
                QueueStatus::Skipped,
            ], true)) {
                $this->throwInvalidTransition($lockedQueue);
            }

            $timezone = $lockedQueue->appointment->clinic->settings?->timezone ?? 'Asia/Jakarta';
            $sessionStartsAt = CarbonImmutable::parse(
                $lockedQueue->queue_date->toDateString().' '.$lockedQueue->schedule->start_time,
                $timezone,
            );

            if ($sessionStartsAt->isFuture()) {
                throw ValidationException::withMessages([
                    'queue' => 'Pasien baru dapat ditandai tidak hadir setelah sesi dimulai.',
                ]);
            }

            $lockedQueue->appointment()->update(['status' => AppointmentStatus::NoShow]);

            return $this->applyTransition($lockedQueue, $actor, QueueStatus::NoShow, [
                'no_show_at' => now(),
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    private function transition(
        Queue $queue,
        User $actor,
        QueueStatus $from,
        QueueStatus $to,
        array $attributes = [],
    ): Queue {
        return DB::transaction(function () use ($queue, $actor, $from, $to, $attributes): Queue {
            $lockedQueue = Queue::query()->lockForUpdate()->findOrFail($queue->id);
            $this->ensureOperationalDate($lockedQueue);
            $this->ensureStatus($lockedQueue, $from);

            return $this->applyTransition($lockedQueue, $actor, $to, $attributes);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    private function applyTransition(
        Queue $queue,
        User $actor,
        QueueStatus $status,
        array $attributes = [],
        array $metadata = [],
    ): Queue {
        $previousStatus = $queue->status;
        $queue->update(['status' => $status, ...$attributes]);
        $this->auditLogger->recordForClinic(
            $queue->clinic,
            'queue.status_changed',
            $queue,
            [
                'from' => $previousStatus->value,
                'to' => $status->value,
                ...$metadata,
            ],
            $actor,
        );
        $this->notificationService->queueStatusChanged($queue->refresh(), $previousStatus);

        return $queue->load(['appointment.patient', 'doctor.user', 'schedule']);
    }

    private function ensureStatus(Queue $queue, QueueStatus $expected): void
    {
        if ($queue->status !== $expected) {
            $this->throwInvalidTransition($queue);
        }
    }

    private function ensureOperationalDate(Queue $queue): void
    {
        $timezone = $queue->clinic->settings?->timezone ?? 'Asia/Jakarta';

        if (! $queue->queue_date->isSameDay(CarbonImmutable::now($timezone))) {
            throw ValidationException::withMessages([
                'queue' => 'Check-in dan pemanggilan hanya dapat dilakukan pada tanggal antrean.',
            ]);
        }
    }

    private function throwInvalidTransition(Queue $queue): never
    {
        throw ValidationException::withMessages([
            'queue' => "Status antrean {$queue->display_number} sudah berubah. Muat ulang halaman.",
        ]);
    }
}
