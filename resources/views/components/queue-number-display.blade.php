@props([
    'displayNumber',
    'poliLabel' => null,
    'doctorLabel' => null,
    'sessionLabel' => null,
    'status',
    'etaMinMinutes' => null,
    'etaMaxMinutes' => null,
    'confidence' => null,
    'isStale' => false,
])

@php
    $statusLabel = match ($status) {
        'CALLED' => 'Dipanggil',
        'WAITING' => 'Menunggu',
        'IN_PROGRESS' => 'Diperiksa',
        default => 'Antrean',
    };
    $statusClasses = match ($status) {
        'CALLED' => 'bg-[#EAF2F0] text-[#287F78]',
        'WAITING' => 'bg-[#FFF4E8] text-[#A96D34]',
        'IN_PROGRESS' => 'bg-[#EEF3F2] text-[#3D5F5C]',
        default => 'bg-slate-100 text-slate-600',
    };
    $hasEta = is_int($etaMinMinutes)
        && is_int($etaMaxMinutes)
        && $etaMinMinutes >= 0
        && $etaMaxMinutes >= $etaMinMinutes;
    $confidenceLabel = match ($confidence) {
        'high' => 'Keyakinan tinggi',
        'medium' => 'Keyakinan sedang',
        'low' => 'Keyakinan rendah',
        default => null,
    };
    $confidenceClasses = match ($confidence) {
        'high' => 'bg-clinic-50 text-clinic-700',
        'medium' => 'bg-amber-50 text-amber-700',
        'low' => 'bg-red-50 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<article {{ $attributes->class([
    'clinic-card min-w-0 overflow-hidden p-5 sm:p-6 2xl:p-8',
    'ring-2 ring-clinic-200' => $status === 'CALLED',
]) }}>
    <div class="flex min-w-0 items-start justify-between gap-3 sm:gap-5">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Nomor antrean</p>
            <p class="mt-2 max-w-full break-words font-display text-4xl font-extrabold leading-none text-clinic-700 sm:text-5xl 2xl:text-6xl">
                {{ $displayNumber }}
            </p>
        </div>
        <span class="shrink-0 rounded-full px-3 py-1.5 text-xs font-bold sm:text-sm {{ $statusClasses }}">
            {{ $statusLabel }}
        </span>
    </div>

    <dl class="mt-6 grid min-w-0 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
        <div class="min-w-0">
            <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Poli</dt>
            <dd class="mt-1 break-words text-lg font-extrabold text-slate-900">
                {{ $poliLabel ?: 'Poli belum ditentukan' }}
            </dd>
        </div>
        @if ($doctorLabel || $sessionLabel)
            <div class="min-w-0 sm:text-right">
                @if ($doctorLabel)
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Dokter</dt>
                    <dd class="mt-1 break-words font-bold text-slate-800">{{ $doctorLabel }}</dd>
                @endif
                @if ($sessionLabel)
                    <dd class="mt-1 break-words text-sm text-slate-500">{{ $sessionLabel }}</dd>
                @endif
            </div>
        @endif
    </dl>

    <div class="mt-5 rounded-2xl bg-slate-50 p-4">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Perkiraan waktu tunggu</p>
        @if ($hasEta)
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <p class="text-lg font-extrabold text-slate-900">Perkiraan {{ $etaMinMinutes }}–{{ $etaMaxMinutes }} menit</p>
                @if ($confidenceLabel)
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $confidenceClasses }}">{{ $confidenceLabel }}</span>
                @endif
            </div>
            @if ($confidence === 'low')
                <p class="mt-2 text-sm text-slate-600">Perkiraan dapat berubah.</p>
            @endif
        @else
            <p class="mt-2 text-sm font-semibold text-slate-600">Estimasi belum tersedia.</p>
        @endif

        @if ($isStale)
            <p class="mt-2 text-sm font-semibold text-amber-700">Data estimasi sudah lama. Menampilkan perkiraan terakhir.</p>
        @endif
    </div>
</article>
