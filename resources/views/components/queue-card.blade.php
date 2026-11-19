@props([
    'queue',
    'timezone' => 'Asia/Jakarta',
])

@php
    $isCalled = $queue->status === \App\QueueStatus::Called;
@endphp

<article data-queue-id="{{ $queue->id }}" data-queue-card {{ $attributes->class([
    'clinic-card',
    'border-blue-300 ring-2 ring-blue-100' => $isCalled,
]) }}>
    <div class="grid gap-5 p-5 sm:grid-cols-[10rem_minmax(0,1fr)] xl:grid-cols-[10rem_minmax(0,1fr)_minmax(0,1fr)_12rem] xl:items-start">
        <div>
            <p class="clinic-queue-number text-xl text-clinic-700" data-queue-number>{{ $queue->display_number }}</p>
            <div class="mt-2">
                <x-status-badge :status="$queue->status" />
            </div>
        </div>
        <div class="min-w-0" data-queue-patient>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Pasien</p>
            <p class="mt-1 font-bold text-slate-900">{{ $queue->appointment->patient->name }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $queue->appointment->patient->medical_record_number }} · {{ $queue->appointment->booking_code }}</p>
        </div>
        <div class="min-w-0 sm:col-start-2 xl:col-start-auto" data-queue-doctor>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Dokter & sesi</p>
            <p class="mt-1 font-bold text-slate-900">{{ $queue->doctor->user->name }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ substr($queue->schedule->start_time, 0, 5) }}–{{ substr($queue->schedule->end_time, 0, 5) }} · {{ $queue->appointment->service_name }}</p>
        </div>
        <div class="grid min-w-0 grid-cols-2 gap-2 border-t border-slate-100 pt-4 sm:col-span-2 xl:col-span-1 xl:border-0 xl:pt-0" data-queue-actions>
            @can('update', $queue)
                @if($queue->status === \App\QueueStatus::Booked)
                    <form class="col-span-2" method="POST" action="{{ route('queues.check-in', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="primary" class="w-full">Check-in</x-clinic-button>
                    </form>
                @endif
            @endcan
            @can('call', $queue)
                @if($queue->status === \App\QueueStatus::Waiting)
                    <form class="col-span-2" method="POST" action="{{ route('queues.call', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="primary" class="w-full">Panggil</x-clinic-button>
                    </form>
                @endif
                @if($queue->status === \App\QueueStatus::Called)
                    <form class="col-span-2" method="POST" action="{{ route('queues.skip', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="secondary" class="w-full">Lewati</x-clinic-button>
                    </form>
                @endif
                @if($queue->status === \App\QueueStatus::Skipped)
                    <form class="col-span-2" method="POST" action="{{ route('queues.return', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="secondary" class="w-full">Kembalikan</x-clinic-button>
                    </form>
                @endif
            @endcan
            @can('startVisit', $queue)
                @if($queue->status === \App\QueueStatus::Called)
                    <form class="col-span-2" method="POST" action="{{ route('doctor.visits.start', $queue) }}" data-queue-action>
                        @csrf
                        <x-clinic-button type="submit" variant="primary" class="w-full">Mulai periksa</x-clinic-button>
                    </form>
                @endif
                @if($queue->status === \App\QueueStatus::InProgress && $queue->appointment->visit)
                    <x-clinic-button href="{{ route('doctor.visits.show', $queue->appointment->visit) }}" variant="primary" class="col-span-2 w-full" wire:navigate>
                        Buka pemeriksaan
                    </x-clinic-button>
                @endif
            @endcan
            @can('update', $queue)
                @if(in_array($queue->status, [\App\QueueStatus::Booked, \App\QueueStatus::Waiting, \App\QueueStatus::Called, \App\QueueStatus::Skipped], true))
                    <form method="POST" action="{{ route('queues.no-show', $queue) }}" data-queue-action onsubmit="return confirm('Tandai pasien tidak hadir?')">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-xl border border-amber-200 bg-amber-50/50 px-2 py-2 text-xs font-bold text-amber-900 transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-60">Tidak hadir</button>
                    </form>
                    <form method="POST" action="{{ route('queues.cancel', $queue) }}" data-queue-action onsubmit="return confirm('Batalkan antrean dan booking ini?')">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-xl border border-red-200 bg-red-50/50 px-2 py-2 text-xs font-bold text-red-700 transition hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-60">Batalkan</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
    @if($queue->status === \App\QueueStatus::Called)
        <div class="rounded-b-3xl border-t border-[#DDE5E1] bg-[#EEF3F2] px-5 py-3 text-sm font-bold text-[#287F78]">
            Sedang dipanggil sejak {{ $queue->called_at?->timezone($timezone)->format('H:i') }}
        </div>
    @endif
</article>

