<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Workspace dokter</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Dashboard Dokter</h1></div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500" data-realtime-connection-state="reconnecting" role="status" aria-live="polite" title="Status pembaruan antrean">
                <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse" data-realtime-connection-dot aria-hidden="true"></span>
                <span class="hidden sm:inline" data-realtime-connection-label>Menghubungkan kembali…</span>
                <span class="sr-only sm:hidden">Status koneksi antrean</span>
            </div>
        </div>
    </x-slot>

    <div data-realtime-queue-channel="clinic.{{ auth()->user()->clinic_id }}.queues">
        <div class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-extrabold text-clinic-950">Ringkasan {{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('d F Y') }}</h2>
                @if($schedules->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2" aria-label="Sesi praktik">
                        @foreach($schedules as $schedule)
                            <span class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">{{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }} · kuota {{ $schedule->quota }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-500">Tidak ada jadwal praktik aktif pada tanggal ini.</p>
                @endif
            </div>
            <form method="GET" class="grid gap-2 sm:grid-cols-[minmax(12rem,1fr)_auto]">
                <label class="sr-only" for="date">Tanggal ringkasan</label>
                <input id="date" class="clinic-input" name="date" type="date" value="{{ $date }}">
                <x-clinic-button variant="secondary" class="w-full sm:w-auto">Tampilkan</x-clinic-button>
            </form>
        </div>

        <div data-realtime-refresh-root>
            <div class="mb-6 hidden rounded-2xl border px-5 py-4 text-sm font-semibold" data-queue-action-feedback role="status" aria-live="polite"></div>
            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700" role="alert">{{ $errors->first() }}</div>
            @endif

            @if(! $isToday)
                <div class="mb-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-semibold text-slate-700" role="status">Mode riwayat · tindakan antrean dinonaktifkan untuk tanggal selain hari ini.</div>
            @elseif(! $canWrite)
                <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-900" role="status">Operasional sedang hanya-baca. Pemeriksaan yang sudah berjalan tetap dapat dilanjutkan.</div>
            @endif

            @if($activeQueue)
                @php
                    $activeSince = match ($activeQueue->status) {
                        \App\QueueStatus::Waiting => $activeQueue->checked_in_at,
                        \App\QueueStatus::Called => $activeQueue->called_at,
                        default => $activeQueue->appointment->visit?->started_at,
                    };
                    $activeMinutes = $activeSince ? max(0, (int) $activeSince->diffInMinutes(now())) : null;
                @endphp
                <section data-queue-id="{{ $activeQueue->id }}" @class([
                    'mb-6 overflow-hidden rounded-3xl border p-6 transition sm:p-8',
                    'border-clinic-200 bg-clinic-950 text-white' => $activeQueue->status === \App\QueueStatus::InProgress,
                    'border-clinic-300 bg-clinic-50' => $activeQueue->status === \App\QueueStatus::Called,
                    'border-amber-200 bg-white ring-2 ring-amber-100' => $activeQueue->status === \App\QueueStatus::Waiting,
                ]) aria-labelledby="active-queue-title">
                    <div class="grid gap-6 lg:grid-cols-[auto_1fr_auto] lg:items-center">
                        <div @class(['flex h-20 w-20 items-center justify-center rounded-2xl font-display text-2xl font-extrabold', 'bg-white/10 text-white' => $activeQueue->status === \App\QueueStatus::InProgress, 'bg-white text-clinic-700 shadow-sm' => $activeQueue->status !== \App\QueueStatus::InProgress])>{{ $activeQueue->display_number }}</div>
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <p @class(['text-xs font-bold uppercase tracking-[0.16em]', 'text-clinic-200' => $activeQueue->status === \App\QueueStatus::InProgress, 'text-clinic-700' => $activeQueue->status === \App\QueueStatus::Called, 'text-amber-800' => $activeQueue->status === \App\QueueStatus::Waiting])>{{ $activeQueue->status === \App\QueueStatus::InProgress ? 'Pemeriksaan sedang berlangsung' : ($activeQueue->status === \App\QueueStatus::Called ? 'Pasien sudah dipanggil' : 'Pasien berikutnya') }}</p>
                                <x-status-badge :status="$activeQueue->status" />
                            </div>
                            <h3 id="active-queue-title" @class(['mt-2 text-2xl font-extrabold', 'text-white' => $activeQueue->status === \App\QueueStatus::InProgress, 'text-clinic-950' => $activeQueue->status !== \App\QueueStatus::InProgress])>{{ $activeQueue->appointment->patient->name }}</h3>
                            <p @class(['mt-2 text-sm', 'text-slate-300' => $activeQueue->status === \App\QueueStatus::InProgress, 'text-slate-600' => $activeQueue->status !== \App\QueueStatus::InProgress])>{{ $activeQueue->appointment->service_name }} · Sesi {{ substr($activeQueue->schedule->start_time, 0, 5) }}–{{ substr($activeQueue->schedule->end_time, 0, 5) }}</p>
                            @if($activeMinutes !== null)
                                <p @class(['mt-2 text-xs font-semibold', 'text-clinic-200' => $activeQueue->status === \App\QueueStatus::InProgress, 'text-amber-800' => $activeQueue->status === \App\QueueStatus::Waiting, 'text-clinic-700' => $activeQueue->status === \App\QueueStatus::Called])>{{ $activeQueue->status === \App\QueueStatus::Waiting ? 'Menunggu' : ($activeQueue->status === \App\QueueStatus::Called ? 'Dipanggil sejak' : 'Berjalan') }} {{ $activeMinutes }} menit</p>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                            @if($isInteractive)
                                @can('call', $activeQueue)
                                    @if($activeQueue->status === \App\QueueStatus::Waiting)
                                        <form method="POST" action="{{ route('queues.call', $activeQueue) }}" data-queue-action>@csrf<x-clinic-button class="w-full">Panggil pasien</x-clinic-button></form>
                                    @endif
                                @endcan
                                @can('startVisit', $activeQueue)
                                    @if($activeQueue->status === \App\QueueStatus::Called)
                                        <form method="POST" action="{{ route('doctor.visits.start', $activeQueue) }}" data-queue-action>@csrf<x-clinic-button class="w-full">Mulai pemeriksaan</x-clinic-button></form>
                                    @endif
                                @endcan
                            @endif
                            @if($activeQueue->status === \App\QueueStatus::InProgress && $activeQueue->appointment->visit)
                                <x-clinic-button href="{{ route('doctor.visits.show', $activeQueue->appointment->visit) }}" class="w-full" wire:navigate>Lanjutkan pemeriksaan</x-clinic-button>
                            @endif
                            <a href="{{ route('doctor.queues.index', ['date' => $date]) }}" @class(['inline-flex min-h-11 items-center justify-center rounded-xl px-4 py-3 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500', 'border border-white/20 text-white hover:bg-white/10' => $activeQueue->status === \App\QueueStatus::InProgress, 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' => $activeQueue->status !== \App\QueueStatus::InProgress]) wire:navigate>Lihat antrean</a>
                        </div>
                    </div>
                </section>
            @else
                <section class="clinic-card mb-6 p-8 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-100 text-xl font-bold text-clinic-700">✓</span>
                    @if($metrics['booked'] > 0)
                        <h3 class="mt-4 font-extrabold text-clinic-950">Belum ada pasien check-in</h3><p class="mt-1 text-sm text-slate-500">{{ $metrics['booked'] }} pasien masih terjadwal dan belum masuk antrean tunggu.</p>
                    @elseif($metrics['completed'] > 0)
                        <h3 class="mt-4 font-extrabold text-clinic-950">Semua pemeriksaan selesai</h3><p class="mt-1 text-sm text-slate-500">{{ $metrics['completed'] }} pasien sudah dilayani pada tanggal ini.</p>
                    @else
                        <h3 class="mt-4 font-extrabold text-clinic-950">Belum ada pasien terjadwal</h3><p class="mt-1 text-sm text-slate-500">Antrean akan muncul setelah pasien melakukan check-in.</p>
                    @endif
                    <x-clinic-button href="{{ route('doctor.queues.index', ['date' => $date]) }}" variant="secondary" class="mt-5" wire:navigate>Lihat semua antrean</x-clinic-button>
                </section>
            @endif

            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Status antrean dokter">
                @foreach([['Menunggu', $metrics['waiting'], 'text-amber-800'], ['Dipanggil', $metrics['called'], 'text-clinic-600'], ['Diperiksa', $metrics['in_progress'], 'text-clinic-700'], ['Selesai', $metrics['completed'], 'text-clinic-700']] as [$label, $value, $color])
                    <article class="clinic-card p-4 sm:p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-3 font-display text-2xl font-extrabold {{ $color }} sm:text-3xl">{{ $value }}</p></article>
                @endforeach
            </section>

            <section class="clinic-card mt-7 overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 class="font-extrabold text-clinic-950">Antrean berikutnya</h3><p class="mt-1 text-sm text-slate-500">Menampilkan {{ $queues->count() }} dari {{ max(0, $activeQueueCount - ($activeQueue ? 1 : 0)) }} antrean berikutnya.</p></div>
                    <a href="{{ route('doctor.queues.index', ['date' => $date]) }}" class="shrink-0 text-sm font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Kelola semua →</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($queues as $queue)
                        @php
                            $queueSince = $queue->status === \App\QueueStatus::Waiting ? $queue->checked_in_at : ($queue->status === \App\QueueStatus::Called ? $queue->called_at : $queue->appointment->visit?->started_at);
                            $queueMinutes = $queueSince ? max(0, (int) $queueSince->diffInMinutes(now())) : null;
                        @endphp
                        <div data-queue-id="{{ $queue->id }}" class="flex flex-col gap-3 p-5 transition sm:flex-row sm:items-center">
                            <span class="font-display text-2xl font-extrabold text-clinic-700">{{ $queue->display_number }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-900">{{ $queue->appointment->patient->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $queue->appointment->service_name }} · Sesi {{ substr($queue->schedule->start_time, 0, 5) }}
                                    @if($queueMinutes !== null)
                                        · {{ $queueMinutes }} menit
                                    @endif
                                </p>
                            </div>
                            <x-status-badge :status="$queue->status" />
                        </div>
                    @empty
                        <div class="p-10 text-center"><p class="font-semibold text-slate-700">Tidak ada antrean berikutnya</p><p class="mt-1 text-sm text-slate-500">Pasien baru akan muncul otomatis setelah check-in.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
