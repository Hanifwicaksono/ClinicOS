@props([
    'clinicName',
    'dateLabel',
    'queues' => [],
    'backUrl' => null,
    'bookingUrl' => null,
    'connectionState' => 'reconnecting',
])

@php
    $queueItems = collect($queues);
    $connectionLabel = match ($connectionState) {
        'connected' => 'Terhubung · diperbarui otomatis',
        'refetching' => 'Memperbarui data…',
        'disconnected' => 'Koneksi terputus · menampilkan data terakhir',
        default => 'Menghubungkan kembali…',
    };
    $connectionDotClasses = match ($connectionState) {
        'connected' => 'bg-emerald-400',
        'refetching' => 'bg-sky-400 animate-pulse',
        'disconnected' => 'bg-red-400',
        default => 'bg-amber-400 animate-pulse',
    };
@endphp

<div {{ $attributes->class(['min-w-0 overflow-x-hidden']) }}>
    <section class="bg-clinic-950 text-white">
        <div class="mx-auto max-w-screen-2xl px-5 py-9 sm:px-8 sm:py-12 2xl:px-12">
            @if ($backUrl)
                <a href="{{ $backUrl }}" class="inline-flex max-w-full items-center gap-2 break-words text-sm font-bold text-clinic-300 hover:text-white">
                    <span aria-hidden="true">←</span>
                    <span>{{ $clinicName }}</span>
                </a>
            @else
                <p class="max-w-full break-words text-sm font-bold text-clinic-300">{{ $clinicName }}</p>
            @endif
            <div class="mt-5 flex min-w-0 flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-300">Antrean hari ini</p>
                    <h1 class="mt-2 break-words text-3xl font-extrabold sm:text-4xl 2xl:text-5xl">Status antrean klinik</h1>
                    <p class="mt-2 text-sm text-slate-300 sm:text-base">{{ $dateLabel }}</p>
                </div>
                <div class="flex w-full shrink-0 flex-col gap-3 sm:w-auto sm:items-end">
                    <div
                        class="flex max-w-full items-center gap-2 rounded-full bg-white/10 px-3 py-2 text-xs font-bold text-white sm:text-sm"
                        data-realtime-connection-state="{{ $connectionState }}"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $connectionDotClasses }}" data-realtime-connection-dot aria-hidden="true"></span>
                        <span class="break-words" data-realtime-connection-label>{{ $connectionLabel }}</span>
                    </div>
                    @if ($bookingUrl)
                        <x-clinic-button href="{{ $bookingUrl }}" variant="primary" class="w-full sm:w-auto">
                            Buat booking
                        </x-clinic-button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto min-h-[55vh] max-w-screen-2xl px-5 py-8 sm:px-8 sm:py-12 2xl:px-12">
        @if ($queueItems->isNotEmpty())
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($queueItems as $queue)
                    <x-queue-number-display
                        :display-number="$queue['display_number']"
                        :poli-label="$queue['poli_label'] ?? null"
                        :doctor-label="$queue['doctor_label'] ?? null"
                        :session-label="$queue['session_label'] ?? null"
                        :status="$queue['status']"
                        :eta-min-minutes="$queue['eta_min_minutes'] ?? null"
                        :eta-max-minutes="$queue['eta_max_minutes'] ?? null"
                        :confidence="$queue['confidence'] ?? null"
                        :is-stale="$queue['is_stale'] ?? false"
                    />
                @endforeach
            </div>
        @else
            <div class="clinic-card px-5 py-14 text-center sm:px-8 sm:py-20">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-clinic-50 text-2xl text-clinic-600" aria-hidden="true">✓</div>
                <p class="mt-5 text-lg font-extrabold text-slate-800">Belum ada antrean aktif</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Nomor antrean aktif akan muncul setelah pasien check-in.</p>
            </div>
        @endif

        <p class="mt-7 text-center text-xs text-slate-500">Status mengikuti pembaruan sistem antrean.</p>
    </section>
</div>
