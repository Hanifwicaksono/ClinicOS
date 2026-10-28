@php
    $queueItems = $queues->map(fn ($queue): array => [
        'display_number' => $queue->display_number,
        'poli_label' => $queue->doctor->specialization,
        'doctor_label' => $queue->doctor->user->name,
        'session_label' => 'Sesi '.substr($queue->schedule->start_time, 0, 5).'–'.substr($queue->schedule->end_time, 0, 5),
        'status' => $queue->status->value,
        'eta_min_minutes' => $etas[$queue->id]['eta_min_minutes'],
        'eta_max_minutes' => $etas[$queue->id]['eta_max_minutes'],
        'confidence' => $etas[$queue->id]['confidence'],
    ]);
@endphp

<x-public-layout :title="'Antrean '.$clinic->name">
    <x-public-queue-screen
        :clinic-name="$clinic->name"
        :date-label="\Carbon\CarbonImmutable::parse($date)->translatedFormat('d F Y')"
        :queues="$queueItems"
        :back-url="route('public.clinic.show', $clinic)"
        :booking-url="route('public.booking.create', $clinic)"
        data-realtime-queue-channel="clinic.{{ $clinic->id }}.queue-board"
        data-realtime-public
        data-realtime-refresh-root
    />
</x-public-layout>
