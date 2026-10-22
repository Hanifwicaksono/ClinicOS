@props([
    'name',
    'label',
    'value' => '',
    'id' => null,
    'rows' => 4,
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
    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        data-rme-field="{{ $name }}"
        @required($required)
        @disabled($disabled)
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($errorId) aria-invalid="true" @endif
        {{ $attributes->class(['clinic-input', 'border-red-300 focus:border-red-500 focus:ring-red-500' => $errorId]) }}
    >{{ $value }}</textarea>

    @if ($description)
        <p id="{{ $descriptionId }}" class="mt-2 text-xs leading-5 text-slate-500">{{ $description }}</p>
    @endif
    <x-input-error :id="$errorId" :messages="$errorMessages" class="mt-2" role="alert" />
</div>
