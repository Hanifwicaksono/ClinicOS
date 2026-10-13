@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center border-b-2 border-clinic-500 px-1 pt-1 text-sm font-semibold leading-5 text-clinic-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2'
            : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-semibold leading-5 text-slate-500 transition hover:border-clinic-200 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
