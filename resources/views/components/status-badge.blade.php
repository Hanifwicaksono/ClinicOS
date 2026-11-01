@props([
    'status',
])

@php
    $statusValue = $status instanceof \App\QueueStatus ? $status->value : (string) $status;
    $statusLabel = $status instanceof \App\QueueStatus ? $status->label() : match ($statusValue) {
        'BOOKED' => 'Terdaftar',
        'WAITING' => 'Menunggu',
        'CALLED' => 'Dipanggil',
        'IN_PROGRESS' => 'Diperiksa',
        'COMPLETED' => 'Selesai',
        'SKIPPED' => 'Dilewati',
        'CANCELLED' => 'Dibatalkan',
        'NO_SHOW' => 'Tidak hadir',
        default => $statusValue,
    };
    $statusClasses = match ($statusValue) {
        'BOOKED' => 'bg-slate-100 text-slate-700',
        'WAITING' => 'bg-[#F7F1EA] text-[#D89B68]',
        'CALLED' => 'bg-[#EAF2F0] text-[#287F78]',
        'IN_PROGRESS' => 'bg-[#EEF3F2] text-[#3D5F5C]',
        'COMPLETED' => 'bg-[#EAF2F0] text-[#287F78]',
        'SKIPPED' => 'bg-orange-100 text-orange-700',
        'CANCELLED' => 'bg-slate-100 text-slate-500',
        'NO_SHOW' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<span {{ $attributes->class(["inline-flex rounded-full px-2.5 py-1 text-xs font-bold {$statusClasses}"]) }}>
    {{ $statusLabel }}
</span>

