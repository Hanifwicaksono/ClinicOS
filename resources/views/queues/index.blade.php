@php
    $isDoctor = auth()->user()->hasRole('Doctor');
    $statusStyles = [
        'BOOKED' => 'bg-slate-100 text-slate-700',
        'WAITING' => 'bg-[#F7F1EA] text-[#D89B68]',
        'CALLED' => 'bg-[#EAF2F0] text-[#287F78]',
        'IN_PROGRESS' => 'bg-[#EEF3F2] text-[#3D5F5C]',
        'COMPLETED' => 'bg-[#EAF2F0] text-[#287F78]',
        'SKIPPED' => 'bg-orange-100 text-orange-700',
        'CANCELLED' => 'bg-slate-100 text-slate-500',
        'NO_SHOW' => 'bg-red-100 text-red-700',
    ];
@endphp
<x-app-layout>
    <x-slot name="header"><div class="flex w-full items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Operasional hari ini</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Antrean pasien</h1></div>@unless($isDoctor)<x-clinic-button href="{{ route('receptionist.appointments.create') }}" variant="primary" wire:navigate>+ Booking walk-in</x-clinic-button>@endunless</div></x-slot>

    <div data-realtime-queue-channel="clinic.{{ auth()->user()->clinic_id }}.queues" data-realtime-refresh-root>
    @if($errors->any())<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Antrean {{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('d F Y') }}</h2><p class="mt-2 text-sm text-slate-500">Check-in pasien sesuai kedatangan, lalu panggil satu per satu dalam setiap sesi dokter.</p></div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach([[\App\QueueStatus::Booked, 'Terdaftar'], [\App\QueueStatus::Waiting, 'Menunggu'], [\App\QueueStatus::Called, 'Dipanggil'], [\App\QueueStatus::InProgress, 'Diperiksa'], [\App\QueueStatus::Completed, 'Selesai'], [\App\QueueStatus::Skipped, 'Dilewati']] as [$status, $label])
            <div class="clinic-card p-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-extrabold text-clinic-950">{{ $statusCounts[$status->value] ?? 0 }}</p></div>
        @endforeach
    </div>

    <form method="GET" class="clinic-card mb-6 grid gap-4 p-5 md:grid-cols-4">
        <div><label class="clinic-label" for="date">Tanggal</label><input class="clinic-input" id="date" name="date" type="date" value="{{ $date }}"></div>
        @unless($isDoctor)<div><label class="clinic-label" for="doctor_id">Dokter</label><select class="clinic-input" id="doctor_id" name="doctor_id"><option value="">Semua dokter</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected(request('doctor_id') == $doctor->id)>{{ $doctor->user->name }}</option>@endforeach</select></div>@endunless
        <div><label class="clinic-label" for="doctor_schedule_id">Sesi</label><select class="clinic-input" id="doctor_schedule_id" name="doctor_schedule_id"><option value="">Semua sesi</option>@foreach($doctors as $doctor)@foreach($doctor->schedules as $schedule)<option value="{{ $schedule->id }}" @selected(request('doctor_schedule_id') == $schedule->id)>{{ $doctor->user->name }} · {{ $schedule->day_name }} {{ substr($schedule->start_time, 0, 5) }}</option>@endforeach @endforeach</select></div>
        <div><label class="clinic-label" for="status">Status</label><select class="clinic-input" id="status" name="status"><option value="">Semua status</option>@foreach(\App\QueueStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
        <div class="flex items-end"><x-clinic-button variant="secondary" class="w-full">Terapkan filter</x-clinic-button></div>
    </form>

    <div class="space-y-4">
        @forelse($queues as $queue)
            <article @class(['clinic-card overflow-hidden border-blue-300 ring-2 ring-blue-100' => $queue->status === \App\QueueStatus::Called, 'clinic-card overflow-hidden' => $queue->status !== \App\QueueStatus::Called])>
                <div class="grid gap-5 p-5 lg:grid-cols-[100px_1fr_1fr_auto] lg:items-center">
                    <div><p class="font-display text-3xl font-extrabold text-clinic-700">{{ $queue->display_number }}</p><span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $statusStyles[$queue->status->value] }}">{{ $queue->status->label() }}</span></div>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Pasien</p><p class="mt-1 font-bold text-slate-900">{{ $queue->appointment->patient->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $queue->appointment->patient->medical_record_number }} · {{ $queue->appointment->booking_code }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Dokter & sesi</p><p class="mt-1 font-bold text-slate-900">{{ $queue->doctor->user->name }}</p><p class="mt-1 text-xs text-slate-500">{{ substr($queue->schedule->start_time, 0, 5) }}–{{ substr($queue->schedule->end_time, 0, 5) }} · {{ $queue->appointment->service_name }}</p></div>
                    <div class="flex flex-wrap gap-2 lg:max-w-64 lg:justify-end">
                        @can('update', $queue)
                            @if($queue->status === \App\QueueStatus::Booked)<form method="POST" action="{{ route('queues.check-in', $queue) }}">@csrf<x-clinic-button variant="primary" class="px-4 py-2">Check-in</x-clinic-button></form>@endif
                        @endcan
                        @can('call', $queue)
                            @if($queue->status === \App\QueueStatus::Waiting)<form method="POST" action="{{ route('queues.call', $queue) }}">@csrf<x-clinic-button variant="primary" class="px-4 py-2">Panggil</x-clinic-button></form>@endif
                            @if($queue->status === \App\QueueStatus::Called)<form method="POST" action="{{ route('queues.skip', $queue) }}">@csrf<x-clinic-button variant="secondary" class="px-4 py-2">Lewati</x-clinic-button></form>@endif
                            @if($queue->status === \App\QueueStatus::Skipped)<form method="POST" action="{{ route('queues.return', $queue) }}">@csrf<x-clinic-button variant="secondary" class="px-4 py-2">Kembalikan</x-clinic-button></form>@endif
                        @endcan
                        @can('startVisit', $queue)
                            @if($queue->status === \App\QueueStatus::Called)<form method="POST" action="{{ route('doctor.visits.start', $queue) }}">@csrf<x-clinic-button variant="primary" class="px-4 py-2">Mulai periksa</x-clinic-button></form>@endif
                            @if($queue->status === \App\QueueStatus::InProgress && $queue->appointment->visit)<x-clinic-button href="{{ route('doctor.visits.show', $queue->appointment->visit) }}" variant="primary" class="px-4 py-2" wire:navigate>Buka pemeriksaan</x-clinic-button>@endif
                        @endcan
                        @can('update', $queue)
                            @if(in_array($queue->status, [\App\QueueStatus::Booked, \App\QueueStatus::Waiting, \App\QueueStatus::Called, \App\QueueStatus::Skipped], true))
                                <form method="POST" action="{{ route('queues.no-show', $queue) }}" onsubmit="return confirm('Tandai pasien tidak hadir?')">@csrf<button class="rounded-xl px-3 py-2 text-xs font-bold text-orange-700 hover:bg-orange-50">No-show</button></form>
                                <form method="POST" action="{{ route('queues.cancel', $queue) }}" onsubmit="return confirm('Batalkan antrean dan booking ini?')">@csrf<button class="rounded-xl px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">Batalkan</button></form>
                            @endif
                        @endcan
                    </div>
                </div>
                @if($queue->status === \App\QueueStatus::Called)<div class="border-t border-[#DDE5E1] bg-[#EEF3F2] px-5 py-3 text-sm font-bold text-[#287F78]">Sedang dipanggil sejak {{ $queue->called_at?->format('H:i') }}</div>@endif
            </article>
        @empty
            <div class="clinic-card p-14 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-50 text-xl text-clinic-600">✓</div><p class="mt-4 font-bold text-slate-800">Tidak ada antrean</p><p class="mt-1 text-sm text-slate-500">Belum ada antrean yang sesuai dengan filter ini.</p></div>
        @endforelse
    </div>
    @if($queues->hasPages())<div class="mt-6">{{ $queues->links() }}</div>@endif
    </div>
</x-app-layout>
