@props([
    'variant' => 'primary',
    'href' => null,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-clinic-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

    $variantClasses = match ($variant) {
        'primary' => 'bg-clinic-500 text-white hover:bg-clinic-600',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:border-clinic-200 hover:bg-clinic-50 hover:text-clinic-700',
        'danger' => 'bg-red-600 text-white hover:bg-red-500',
        default => 'bg-clinic-500 text-white hover:bg-clinic-600',
    };

    $classes = trim($baseClasses . ' ' . $variantClasses);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
