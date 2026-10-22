@props([
    'name',
    'label',
    'value' => '',
    'id' => null,
    'type' => 'text',
    'unit' => null,
    'description' => null,
    'messages' => [],
    'required' => false,
    'disabled' => false,
])

@php
    $fieldId = $id ?? str_replace(['.', '[', ']'], ['_', '_', ''], $name);
    $descriptionId = $description ? "{$fieldId}_description" : null;
    $errorMessages = array_values(array_filter((array) $messages));
    $errorId = $errorMessages !== [] ? "{$fieldId}_error" : null;
    $describedBy = collect([$descriptionId, $errorId])->filter()->join(' ');
@endphp

<div>
    <label class="clinic-label" for="{{ $fieldId }}">
        {{ $label }}
        @if ($required)
            <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only"> (wajib)</span>
        @endif
    </label>
    <div @class(['relative' => $unit])>
        <input
            id="{{ $fieldId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $value }}"
            data-rme-field="{{ $name }}"
            @required($required)
            @disabled($disabled)
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($errorId) aria-invalid="true" @endif
            {{ $attributes->class([
                'clinic-input',
                'pr-16' => $unit,
                'border-red-300 focus:border-red-500 focus:ring-red-500' => $errorId,
            ]) }}
        >
        @if ($unit)
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $unit }}</span>
        @endif
    </div>

    @if ($description)
        <p id="{{ $descriptionId }}" class="mt-2 text-xs leading-5 text-slate-500">{{ $description }}</p>
    @endif
    <x-input-error :id="$errorId" :messages="$errorMessages" class="mt-2" role="alert" />
</div>
