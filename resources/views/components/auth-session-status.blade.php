@props(['status'])

@if ($status)
    <div role="status" aria-live="polite" {{ $attributes->merge(['class' => 'rounded-2xl border border-clinic-200 bg-clinic-50 px-4 py-3 text-sm font-semibold text-clinic-700']) }}>
        {{ $status }}
    </div>
@endif
