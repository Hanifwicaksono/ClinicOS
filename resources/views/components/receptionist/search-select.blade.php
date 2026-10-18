@props(['name', 'label', 'placeholder', 'options', 'selected' => null, 'required' => true, 'emptyLabel' => null])

<div x-data="{
    options: @js($options), selectedId: String(@js($selected) ?? ''), query: '', open: false,
    init() { this.query = this.options.find((option) => String(option.id) === this.selectedId)?.label ?? ''; },
    get results() { const query = this.query.toLowerCase(); return this.options.filter((option) => option.label.toLowerCase().includes(query)).slice(0, 8); },
    search() { this.selectedId = ''; this.open = true; },
    select(option) { this.selectedId = option.id; this.query = option.label; this.open = false; },
    clear() { this.selectedId = ''; this.query = ''; this.open = false; },
}">
    <label class="clinic-label" for="{{ $name }}_search">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" :value="selectedId">
    <div class="relative">
        <input class="clinic-input pr-10" id="{{ $name }}_search" type="search" x-model="query" @input="search()" @focus="open = true" autocomplete="off" placeholder="{{ $placeholder }}" @if ($required) required @endif>
        @if ($emptyLabel)<button x-cloak x-show="selectedId" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400 hover:text-slate-700" @click="clear()" aria-label="Hapus pilihan {{ strtolower($label) }}">×</button>@endif
        <div x-cloak x-show="open" @click.outside="open = false" class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
            <template x-for="option in results" :key="option.id"><button type="button" class="block w-full px-4 py-3 text-left text-sm text-slate-700 hover:bg-clinic-50" @click="select(option)" x-text="option.label"></button></template>
            <p x-show="!results.length" class="px-4 py-3 text-sm text-slate-500">{{ $label }} tidak ditemukan.</p>
        </div>
    </div>
</div>
