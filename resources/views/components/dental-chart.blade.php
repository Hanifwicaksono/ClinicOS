@props([
    'teeth' => [],
    'conditions' => [],
    'selectedTooth' => null,
    'readOnly' => false,
])

@php
    $quadrantLabels = [
        'upper-right' => 'Rahang atas kanan',
        'upper-left' => 'Rahang atas kiri',
        'lower-right' => 'Rahang bawah kanan',
        'lower-left' => 'Rahang bawah kiri',
    ];
    $teeth = collect($teeth)->map(fn (array $tooth): array => [
        'id' => (string) $tooth['id'],
        'label' => $tooth['label'] ?? "Gigi {$tooth['id']}",
        'quadrant' => $tooth['quadrant'] ?? 'upper-right',
        'condition' => $tooth['condition'] ?? 'unknown',
        'disabled' => (bool) ($tooth['disabled'] ?? false),
    ])->values()->all();
    $conditions = collect($conditions)->map(fn (array $condition): array => [
        'value' => (string) $condition['value'],
        'label' => $condition['label'],
    ])->values()->all();
    $teethByQuadrant = collect($teeth)->groupBy('quadrant');
@endphp

<section
    {{ $attributes->class(['clinic-card min-w-0 overflow-hidden p-6 sm:p-8']) }}
    aria-labelledby="dental-chart-heading"
    data-dental-chart
    data-persists="false"
    data-initial-selected-tooth="{{ $selectedTooth }}"
    data-read-only="{{ $readOnly ? 'true' : 'false' }}"
    data-tooth-selected-event="dental-tooth-selected"
    data-condition-changed-event="dental-condition-changed"
    x-data="{
        teeth: @js($teeth),
        conditions: @js($conditions),
        selectedTooth: @js($selectedTooth),
        readOnly: @js((bool) $readOnly),
        tooth(id) {
            return this.teeth.find(tooth => tooth.id === id);
        },
        conditionLabel(value) {
            return this.conditions.find(condition => condition.value === value)?.label ?? value;
        },
        conditionClasses(value) {
            return {
                unknown: 'border-slate-200 bg-slate-50 text-slate-700',
                healthy: 'border-emerald-300 bg-emerald-50 text-emerald-900',
                caries: 'border-amber-400 bg-amber-50 text-amber-950',
                filled: 'border-blue-300 bg-blue-50 text-blue-900',
                missing: 'border-dashed border-slate-400 bg-white text-slate-500',
                crown: 'border-violet-300 bg-violet-50 text-violet-900',
            }[value] ?? 'border-slate-200 bg-slate-50 text-slate-700';
        },
        selectTooth(id) {
            const tooth = this.tooth(id);

            if (this.readOnly || tooth?.disabled) {
                return;
            }

            this.selectedTooth = id;
            this.$dispatch('dental-tooth-selected', { tooth: { ...tooth } });
        },
        applyCondition(value) {
            const tooth = this.tooth(this.selectedTooth);

            if (this.readOnly || ! tooth || tooth.disabled) {
                return;
            }

            tooth.condition = value;
            this.$dispatch('dental-condition-changed', { tooth: { ...tooth } });
        },
    }"
>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 id="dental-chart-heading" class="text-lg font-extrabold text-clinic-950">Odontogram</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Pilih gigi, lalu pilih kondisi. Perubahan hanya tersimpan di state komponen.</p>
        </div>
        @if ($readOnly)
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">Read-only</span>
        @else
            <span class="rounded-full bg-clinic-50 px-3 py-1.5 text-xs font-bold text-clinic-700">Interaktif</span>
        @endif
    </div>

    <p class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700" aria-live="polite">
        <span class="font-bold">Gigi terpilih:</span>
        <span x-show="! selectedTooth">Belum ada</span>
        <span x-show="selectedTooth" x-cloak x-text="tooth(selectedTooth)?.label"></span>
    </p>

    <div class="mt-5 grid min-w-0 gap-4 md:grid-cols-2">
        @foreach ($quadrantLabels as $quadrant => $quadrantLabel)
            <fieldset class="min-w-0 rounded-2xl border border-slate-200 p-4">
                <legend class="px-2 text-xs font-extrabold uppercase tracking-wider text-slate-500">{{ $quadrantLabel }}</legend>
                <div class="grid min-w-0 grid-cols-4 gap-2">
                    @foreach ($teethByQuadrant->get($quadrant, collect()) as $tooth)
                        @php
                            $initialConditionLabel = collect($conditions)->firstWhere('value', $tooth['condition'])['label'] ?? $tooth['condition'];
                        @endphp
                        <button
                            type="button"
                            data-tooth-id="{{ $tooth['id'] }}"
                            class="min-w-0 rounded-xl border px-1 py-2 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            x-bind:class="[
                                conditionClasses(tooth(@js($tooth['id'])).condition),
                                selectedTooth === @js($tooth['id']) ? 'ring-2 ring-clinic-600 ring-offset-2' : '',
                            ]"
                            x-bind:aria-pressed="selectedTooth === @js($tooth['id'])"
                            x-bind:aria-label="`${tooth(@js($tooth['id'])).label}, kondisi ${conditionLabel(tooth(@js($tooth['id'])).condition)}`"
                            x-on:click="selectTooth(@js($tooth['id']))"
                            @disabled($readOnly || $tooth['disabled'])
                        >
                            <svg class="mx-auto size-7" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path d="M9 3.5c2.2 0 3.7 1.2 7 1.2s4.8-1.2 7-1.2c3.2 0 5 2.6 4.5 6.2-.7 5.2-3.2 6.2-4.1 11.4-.7 4-1.7 7.4-4.2 7.4-2.1 0-1.8-7-3.2-7s-1.1 7-3.2 7c-2.5 0-3.5-3.4-4.2-7.4-.9-5.2-3.4-6.2-4.1-11.4C4 6.1 5.8 3.5 9 3.5Z" />
                            </svg>
                            <span class="mt-1 block text-xs font-extrabold">{{ $tooth['id'] }}</span>
                            <span class="mt-0.5 block truncate text-[10px] font-semibold" x-text="conditionLabel(tooth(@js($tooth['id'])).condition)">{{ $initialConditionLabel }}</span>
                        </button>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    <fieldset class="mt-5 rounded-2xl border border-slate-200 p-4" x-bind:disabled="readOnly || ! selectedTooth" @disabled($readOnly || ! $selectedTooth)>
        <legend class="px-2 text-sm font-extrabold text-clinic-950">Pilih kondisi</legend>
        <div class="flex flex-wrap gap-2">
            @foreach ($conditions as $condition)
                <button
                    type="button"
                    class="rounded-full border px-3 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    x-bind:class="conditionClasses(@js($condition['value']))"
                    x-bind:aria-pressed="tooth(selectedTooth)?.condition === @js($condition['value'])"
                    x-bind:disabled="readOnly || ! selectedTooth"
                    x-on:click="applyCondition(@js($condition['value']))"
                >
                    <span x-show="tooth(selectedTooth)?.condition === @js($condition['value'])" x-cloak aria-hidden="true">✓</span>
                    {{ $condition['label'] }}
                </button>
            @endforeach
        </div>
    </fieldset>

    <div class="mt-5" aria-label="Legenda kondisi gigi">
        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Legenda</p>
        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-700">
            @foreach ($conditions as $condition)
                <li class="flex items-center gap-2">
                    <span class="size-3 rounded-full border" x-bind:class="conditionClasses(@js($condition['value']))" aria-hidden="true"></span>
                    {{ $condition['label'] }}
                </li>
            @endforeach
        </ul>
    </div>
</section>
