@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-xl bg-clinic-50 px-4 py-3 text-start text-base font-semibold text-clinic-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500'
            : 'block w-full rounded-xl px-4 py-3 text-start text-base font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
