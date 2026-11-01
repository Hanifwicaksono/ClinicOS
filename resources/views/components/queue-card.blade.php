@props([
    'queue',
])

@php
    $isCalled = $queue->status === \App\QueueStatus::Called;
@endphp

<article {{ $attributes->class([
    'clinic-card overflow-hidden',
    'border-blue-300 ring-2 ring-blue-100' => $isCalled,
]) }}>
    <div class="grid gap-5 p-5 lg:grid-cols-[100px_1fr_1fr_auto] lg:items-center">
        <div>
            <p class="font-display text-3xl font-extrabold text-clinic-700">{{ $queue->display_number }}</p>
            <div class="mt-2">
                <x-status-badge :status="$queue->status" />
            </div>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Pasien</p>
            <p class="mt-1 font-bold text-slate-900">{{ $queue->appointment->patient->name }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $queue->appointment->patient->medical_record_number }} · {{ $queue->appointment->booking_code }}</p>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Dokter & sesi</p>
            <p class="mt-1 font-bold text-slate-900">{{ $queue->doctor->user->name }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ substr($queue->schedule->start_time, 0, 5) }}–{{ substr($queue->schedule->end_time, 0, 5) }} · {{ $queue->appointment->service_name }}</p>
        </div>
        <div class="flex flex-wrap gap-2 lg:max-w-64 lg:justify-end">
            @can('update', $queue)
                @if($queue->status === \App\QueueStatus::Booked)
                    <form method="POST" action="{{ route('queues.check-in', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="primary" class="px-4 py-2">Check-in</x-clinic-button>
                    </form>
                @endif
            @endcan
            @can('call', $queue)
                @if($queue->status === \App\QueueStatus::Waiting)
                    <form method="POST" action="{{ route('queues.call', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="primary" class="px-4 py-2">Panggil</x-clinic-button>
                    </form>
                @endif
                @if($queue->status === \App\QueueStatus::Called)
                    <form method="POST" action="{{ route('queues.skip', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="secondary" class="px-4 py-2">Lewati</x-clinic-button>
                    </form>
                @endif
                @if($queue->status === \App\QueueStatus::Skipped)
                    <form method="POST" action="{{ route('queues.return', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="secondary" class="px-4 py-2">Kembalikan</x-clinic-button>
                    </form>
                @endif
            @endcan
            @can('startVisit', $queue)
                @if($queue->status === \App\QueueStatus::Called)
                    <form method="POST" action="{{ route('doctor.visits.start', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="primary" class="px-4 py-2">Mulai periksa</x-clinic-button>
                    </form>
                @endif
                @if($queue->status === \App\QueueStatus::InProgress && $queue->appointment->visit)
                    <x-clinic-button href="{{ route('doctor.visits.show', $queue->appointment->visit) }}" variant="primary" class="px-4 py-2" wire:navigate>
                        Buka pemeriksaan
                    </x-clinic-button>
                @endif
            @endcan
            @can('update', $queue)
                @if(in_array($queue->status, [\App\QueueStatus::Booked, \App\QueueStatus::Waiting, \App\QueueStatus::Called, \App\QueueStatus::Skipped], true))
                    <form method="POST" action="{{ route('queues.no-show', $queue) }}" data-queue-action onsubmit="return confirm('Tandai pasien tidak hadir?')">
                        @csrf
                        <button type="submit" class="rounded-xl px-3 py-2 text-xs font-bold text-orange-700 hover:bg-orange-50 disabled:cursor-not-allowed disabled:opacity-60">No-show</button>
                    </form>
                    <form method="POST" action="{{ route('queues.cancel', $queue) }}" data-queue-action onsubmit="return confirm('Batalkan antrean dan booking ini?')">
                        @csrf
                        <button type="submit" class="rounded-xl px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60">Batalkan</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
    @if($queue->status === \App\QueueStatus::Called)
        <div class="border-t border-[#DDE5E1] bg-[#EEF3F2] px-5 py-3 text-sm font-bold text-[#287F78]">
            Sedang dipanggil sejak {{ $queue->called_at?->format('H:i') }}
        </div>
    @endif
</article>

