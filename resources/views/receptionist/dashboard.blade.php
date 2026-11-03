<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between gap-3">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Front desk</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Dashboard Resepsionis</h1></div>
            <div class="flex items-center gap-2">
                <x-clinic-button href="{{ route('receptionist.patients.index') }}" variant="secondary" class="hidden lg:inline-flex" wire:navigate>Cari pasien</x-clinic-button>
                @if($canWrite)
                    <x-clinic-button href="{{ route('receptionist.appointments.create') }}" variant="primary" class="px-3 sm:px-5" wire:navigate><span class="sm:hidden">+ Booking</span><span class="hidden sm:inline">+ Booking walk-in</span></x-clinic-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div data-realtime-queue-channel="clinic.{{ auth()->user()->clinic_id }}.queues">
        <div class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-extrabold text-clinic-950">Operasional {{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('d F Y') }}</h2>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500 shadow-sm ring-1 ring-slate-200" data-realtime-connection-state="reconnecting" role="status" aria-live="polite">
                        <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse" data-realtime-connection-dot aria-hidden="true"></span>
                        <span data-realtime-connection-label>Menghubungkan kembali…</span>
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">Pantau kedatangan dan tangani antrean tanpa berpindah halaman.</p>
                <a href="{{ route('receptionist.patients.index') }}" class="mt-3 inline-flex text-sm font-bold text-clinic-600 hover:text-clinic-700 lg:hidden" wire:navigate>Cari pasien →</a>
            </div>
            <form method="GET" @class(['grid gap-2', 'sm:grid-cols-[minmax(10rem,1fr)_minmax(12rem,1fr)_auto]' => $doctors->count() > 1, 'sm:grid-cols-[minmax(12rem,1fr)_auto]' => $doctors->count() <= 1])>
                <div><label class="sr-only" for="date">Tanggal operasional</label><input id="date" class="clinic-input" name="date" type="date" value="{{ $date }}"></div>
                @if($doctors->count() > 1)
                    <div><label class="sr-only" for="doctor_id">Filter dokter</label><select id="doctor_id" class="clinic-input" name="doctor_id"><option value="">Semua dokter</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected($doctorId === $doctor->id)>{{ $doctor->user->name }}</option>@endforeach</select></div>
                @endif
                <x-clinic-button variant="secondary" class="w-full sm:w-auto">Tampilkan</x-clinic-button>
            </form>
        </div>

        <div data-realtime-refresh-root>
            <div class="mb-6 hidden rounded-2xl border px-5 py-4 text-sm font-semibold" data-queue-action-feedback role="status" aria-live="polite"></div>
            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700" role="alert">{{ $errors->first() }}</div>
            @endif

            @if(! $isToday)
                <div class="mb-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm font-semibold text-slate-700" role="status">Mode riwayat · Check-in dan pemanggilan dinonaktifkan untuk tanggal selain hari ini.</div>
            @elseif(! $canWrite)
                <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-900" role="status">Operasional klinik sedang hanya-baca. Data antrean tetap dapat dipantau.</div>
            @endif

            <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
                @if($activeQueue)
                    @php
                        $isCalled = $activeQueue->status === \App\QueueStatus::Called;
                        $activeSince = $isCalled ? $activeQueue->called_at : $activeQueue->checked_in_at;
                        $activeMinutes = $activeSince ? max(0, (int) $activeSince->diffInMinutes(now())) : null;
                    @endphp
                    <article data-queue-id="{{ $activeQueue->id }}" @class(['relative overflow-hidden rounded-3xl border p-6 transition sm:p-8', 'border-clinic-950 bg-clinic-950 text-white shadow-elevated' => $isCalled, 'border-amber-200 bg-white ring-2 ring-amber-100' => ! $isCalled])>
                        @if($isCalled)
                            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-clinic-500/20" aria-hidden="true"></div>
                        @endif
                        <div class="relative">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p @class(['text-xs font-bold uppercase tracking-[0.18em]', 'text-clinic-200' => $isCalled, 'text-amber-800' => ! $isCalled])>{{ $isCalled ? 'Sedang dipanggil' : 'Pasien berikutnya' }}</p>
                                <x-status-badge :status="$activeQueue->status" />
                            </div>
                            <div class="mt-7 grid gap-6 sm:grid-cols-[auto_1fr] sm:items-center">
                                <div @class(['flex h-24 w-24 items-center justify-center rounded-3xl font-display text-3xl font-extrabold', 'bg-white/10 text-white ring-1 ring-white/10' => $isCalled, 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' => ! $isCalled])>{{ $activeQueue->display_number }}</div>
                                <div>
                                    <h3 @class(['text-2xl font-extrabold', 'text-white' => $isCalled, 'text-clinic-950' => ! $isCalled])>{{ $activeQueue->appointment->patient->name }}</h3>
                                    <p @class(['mt-2 text-sm', 'text-slate-300' => $isCalled, 'text-slate-600' => ! $isCalled])>{{ $activeQueue->appointment->service_name }} · {{ $activeQueue->doctor->user->name }}</p>
                                    <p @class(['mt-1 text-xs', 'text-slate-300' => $isCalled, 'text-slate-500' => ! $isCalled])>Sesi {{ substr($activeQueue->schedule->start_time, 0, 5) }}–{{ substr($activeQueue->schedule->end_time, 0, 5) }} · {{ $activeQueue->appointment->booking_code }}</p>
                                    @if($activeMinutes !== null)
                                        <p @class(['mt-2 text-xs font-bold', 'text-clinic-200' => $isCalled, 'text-amber-800' => ! $isCalled])>{{ $isCalled ? 'Dipanggil sejak' : 'Menunggu' }} {{ $activeMinutes }} menit</p>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                @if($isInteractive && ! $isCalled)
                                    @can('call', $activeQueue)
                                        <form method="POST" action="{{ route('queues.call', $activeQueue) }}" data-queue-action>@csrf<x-clinic-button class="w-full sm:w-auto">Panggil pasien</x-clinic-button></form>
                                    @endcan
                                @endif
                                <a href="{{ route('receptionist.queues.index', ['date' => $date, 'doctor_id' => $doctorId]) }}" @class(['inline-flex min-h-11 items-center justify-center rounded-xl border px-5 py-3 text-sm font-bold transition focus:outline-none focus-visible:ring-2', 'border-white/20 text-white hover:bg-white/10 focus-visible:ring-white' => $isCalled, 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-clinic-500' => ! $isCalled]) wire:navigate>Buka antrean</a>
                            </div>
                        </div>
                    </article>
                @else
                    <article class="clinic-card flex min-h-72 flex-col justify-between p-6 sm:p-8">
                        <div>
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-100 text-xl font-bold text-clinic-700">✓</span>
                            @if($metrics['in_progress'] > 0)
                                <h3 class="mt-5 text-2xl font-extrabold text-clinic-950">Semua pasien yang datang sedang dilayani</h3><p class="mt-2 text-sm leading-6 text-slate-500">{{ $metrics['in_progress'] }} pasien sedang bersama dokter. Tidak ada pasien yang menunggu dipanggil.</p>
                            @elseif($metrics['not_checked_in'] > 0)
                                <h3 class="mt-5 text-2xl font-extrabold text-clinic-950">Belum ada pasien check-in</h3><p class="mt-2 text-sm leading-6 text-slate-500">{{ $metrics['not_checked_in'] }} booking masih menunggu kedatangan pasien.</p>
                            @elseif($metrics['completed'] > 0)
                                <h3 class="mt-5 text-2xl font-extrabold text-clinic-950">Semua pelayanan selesai</h3><p class="mt-2 text-sm leading-6 text-slate-500">{{ $metrics['completed'] }} pasien sudah selesai dilayani pada tanggal ini.</p>
                            @else
                                <h3 class="mt-5 text-2xl font-extrabold text-clinic-950">Belum ada antrean</h3><p class="mt-2 text-sm leading-6 text-slate-500">Booking dan pasien yang check-in akan muncul di sini.</p>
                            @endif
                        </div>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @if($canWrite)
                                <x-clinic-button href="{{ route('receptionist.appointments.create') }}" wire:navigate>Buat booking</x-clinic-button>
                            @endif
                            <a href="{{ route('receptionist.queues.index', ['date' => $date, 'doctor_id' => $doctorId]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50" wire:navigate>Lihat antrean</a>
                        </div>
                    </article>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    @foreach([
                        ['Booking aktif', $metrics['bookings'], $metrics['total_bookings'].' total hari ini', 'text-clinic-700'],
                        ['Belum check-in', $metrics['not_checked_in'], 'Masih terdaftar', 'text-amber-800'],
                        ['Sedang menunggu', $metrics['waiting'], 'Siap dipanggil', 'text-[#8A5427]'],
                        ['Sedang diperiksa', $metrics['in_progress'], 'Bersama dokter', 'text-clinic-700'],
                    ] as [$label, $value, $description, $color])
                        <article class="clinic-card flex min-h-36 flex-col justify-between p-4 sm:p-5"><div><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-2 text-xs leading-5 text-slate-500">{{ $description }}</p></div><p class="mt-4 font-display text-3xl font-extrabold {{ $color }}">{{ $value }}</p></article>
                    @endforeach
                </div>
            </section>

            <section class="clinic-card mt-7 overflow-hidden">
                <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-xs font-bold uppercase tracking-wider text-clinic-600">Tindakan berikutnya</p><h3 class="mt-1 font-extrabold text-clinic-950">Antrean yang perlu ditangani</h3><p class="mt-1 text-sm text-slate-500">Menampilkan {{ $queues->count() }} dari {{ max(0, $actionableQueueCount - ($activeQueue ? 1 : 0)) }} antrean lainnya.</p></div>
                    <a href="{{ route('receptionist.queues.index', ['date' => $date, 'doctor_id' => $doctorId]) }}" class="inline-flex min-h-11 items-center justify-center px-2 text-sm font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Kelola semua →</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($queues as $queue)
                        @php
                            $queueSince = $queue->status === \App\QueueStatus::Waiting ? $queue->checked_in_at : ($queue->status === \App\QueueStatus::Called ? $queue->called_at : null);
                            $queueMinutes = $queueSince ? max(0, (int) $queueSince->diffInMinutes(now())) : null;
                        @endphp
                        <div data-queue-id="{{ $queue->id }}" class="grid gap-4 p-5 transition md:grid-cols-[90px_1fr_1fr_auto] md:items-center">
                            <div><p class="font-display text-2xl font-extrabold text-clinic-700">{{ $queue->display_number }}</p><div class="mt-2"><x-status-badge :status="$queue->status" /></div></div>
                            <div class="min-w-0">
                                <p class="truncate font-bold text-slate-900">{{ $queue->appointment->patient->name }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $queue->appointment->booking_code }} · {{ $queue->appointment->service_name }}</p>
                                @if($queueMinutes !== null)
                                    <p class="mt-1 text-xs font-semibold text-amber-800">{{ $queue->status === \App\QueueStatus::Waiting ? 'Menunggu' : 'Dipanggil sejak' }} {{ $queueMinutes }} menit</p>
                                @endif
                            </div>
                            <div><p class="text-sm font-semibold text-slate-700">{{ $queue->doctor->user->name }}</p><p class="mt-1 text-xs text-slate-500">Sesi {{ substr($queue->schedule->start_time, 0, 5) }}–{{ substr($queue->schedule->end_time, 0, 5) }}</p></div>
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                @if($isInteractive)
                                    @can('update', $queue)
                                        @if($queue->status === \App\QueueStatus::Booked)
                                            <form method="POST" action="{{ route('queues.check-in', $queue) }}" data-queue-action>@csrf<x-clinic-button class="px-4 py-2">Check-in</x-clinic-button></form>
                                        @endif
                                    @endcan
                                    @can('call', $queue)
                                        @if($queue->status === \App\QueueStatus::Waiting)
                                            <form method="POST" action="{{ route('queues.call', $queue) }}" data-queue-action>@csrf<x-clinic-button class="px-4 py-2">Panggil</x-clinic-button></form>
                                        @endif
                                    @endcan
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center">
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-100 text-xl font-bold text-clinic-700">✓</span><p class="mt-4 font-semibold text-slate-700">Tidak ada antrean lain</p><p class="mt-1 text-sm text-slate-500">Booking dan pasien yang perlu ditangani akan muncul di sini.</p>
                            @if($canWrite)
                                <x-clinic-button href="{{ route('receptionist.appointments.create') }}" variant="secondary" class="mt-5" wire:navigate>Buat booking walk-in</x-clinic-button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
