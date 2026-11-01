@props([
    'visits' => [],
    'title' => 'Ringkasan kunjungan',
    'description' => 'Kunjungan terbaru dalam periode yang dipilih.',
    'emptyTitle' => 'Belum ada kunjungan',
    'emptyMessage' => 'Ringkasan akan muncul setelah kunjungan pasien tercatat.',
    'headingId' => null,
])

@php
    $headingId ??= 'visit-summary-heading-'.str()->random(8);
    $visitItems = collect($visits)->map(function (array $visit): array {
        $status = $visit['status'] instanceof \App\VisitStatus
            ? $visit['status']->value
            : strtoupper((string) ($visit['status'] ?? ''));
        $statusLabel = $visit['status_label'] ?? match ($status) {
            'IN_PROGRESS' => 'Sedang diperiksa',
            'COMPLETED' => 'Selesai',
            'CANCELLED' => 'Dibatalkan',
            'NO_SHOW' => 'Tidak hadir',
            default => filled($status) ? str($status)->replace('_', ' ')->headline()->toString() : 'Belum diketahui',
        };
        $statusClasses = match ($status) {
            'IN_PROGRESS' => 'bg-[#FFF4E8] text-[#A96D34]',
            'COMPLETED' => 'bg-clinic-100 text-clinic-700',
            'CANCELLED' => 'bg-slate-100 text-slate-500',
            'NO_SHOW' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };

        return [
            'date' => (string) ($visit['date'] ?? '-'),
            'datetime' => $visit['datetime'] ?? null,
            'doctor' => (string) ($visit['doctor'] ?? 'Dokter belum ditetapkan'),
            'service' => $visit['service'] ?? null,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_classes' => $statusClasses,
        ];
    })->values();
@endphp

<section
    {{ $attributes->class(['clinic-card min-w-0 overflow-hidden']) }}
    data-visit-summary
    aria-labelledby="{{ $headingId }}"
>
    <div class="flex min-w-0 flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 id="{{ $headingId }}" class="break-words text-lg font-extrabold text-clinic-950">{{ $title }}</h2>
            <p class="mt-1 break-words text-sm leading-6 text-slate-500">{{ $description }}</p>
        </div>
        @if ($visitItems->isNotEmpty())
            <span class="w-fit shrink-0 rounded-full bg-clinic-50 px-3 py-1.5 text-xs font-bold text-clinic-700">
                {{ $visitItems->count() }} kunjungan
            </span>
        @endif
    </div>

    @if ($visitItems->isNotEmpty())
        <ul class="min-w-0 divide-y divide-slate-100" role="list">
            @foreach ($visitItems as $visit)
                <li class="grid min-w-0 gap-4 px-5 py-5 sm:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)_auto] sm:items-center sm:px-6" data-visit-summary-item>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Tanggal kunjungan</p>
                        @if ($visit['datetime'])
                            <time datetime="{{ $visit['datetime'] }}" class="mt-1 block break-words text-sm font-bold text-slate-900">{{ $visit['date'] }}</time>
                        @else
                            <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ $visit['date'] }}</p>
                        @endif
                        @if ($visit['service'])
                            <p class="mt-1 break-words text-xs leading-5 text-slate-500">{{ $visit['service'] }}</p>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Dokter</p>
                        <p class="mt-1 break-words text-sm font-semibold text-slate-700">{{ $visit['doctor'] }}</p>
                    </div>
                    <div class="min-w-0 sm:text-right">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400 sm:sr-only">Status visit</p>
                        <span class="mt-1 inline-flex max-w-full rounded-full px-3 py-1.5 text-xs font-bold sm:mt-0 {{ $visit['status_classes'] }}" data-visit-status="{{ $visit['status'] }}">
                            <span class="break-words">{{ $visit['status_label'] }}</span>
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <div class="px-5 py-12 text-center sm:px-8 sm:py-14" data-visit-summary-empty>
            <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-clinic-50 text-clinic-600" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                    <path d="M8 13h3m-3 3h6" />
                </svg>
            </div>
            <p class="mt-4 break-words text-base font-extrabold text-slate-800">{{ $emptyTitle }}</p>
            <p class="mx-auto mt-2 max-w-md break-words text-sm leading-6 text-slate-500">{{ $emptyMessage }}</p>
        </div>
    @endif
</section>
