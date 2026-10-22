@props([
    'before' => [],
    'after' => [],
])

@php
    $recordFields = [
        'chief_complaint' => 'Keluhan utama',
        'subjective' => 'Subjektif',
        'objective' => 'Objektif',
        'assessment' => 'Assessment',
        'plan' => 'Rencana',
        'physical_examination' => 'Pemeriksaan fisik',
        'doctor_notes' => 'Catatan dokter',
        'status' => 'Status',
        'finalized_at' => 'Waktu finalisasi',
    ];
    $vitalSignFields = [
        'weight_kg' => ['Berat badan', 'kg'],
        'height_cm' => ['Tinggi badan', 'cm'],
        'systolic' => ['Sistolik', 'mmHg'],
        'diastolic' => ['Diastolik', 'mmHg'],
        'pulse' => ['Nadi', 'x/menit'],
        'respiratory_rate' => ['Pernapasan', 'x/menit'],
        'temperature_c' => ['Suhu', '°C'],
        'oxygen_saturation' => ['Saturasi oksigen', '%'],
    ];

    $formatVitalSigns = static function (?array $vitalSigns) use ($vitalSignFields): array {
        return collect($vitalSignFields)
            ->filter(fn (array $definition, string $key): bool => filled($vitalSigns[$key] ?? null))
            ->map(fn (array $definition, string $key): string => "{$definition[0]}: {$vitalSigns[$key]} {$definition[1]}")
            ->values()
            ->all();
    };
    $formatDiagnoses = static fn (array $diagnoses): array => collect($diagnoses)
        ->map(function (array $diagnosis): string {
            $value = collect([$diagnosis['code'] ?? null, $diagnosis['name'] ?? null])->filter()->join(' · ');
            $value .= ($diagnosis['is_primary'] ?? false) ? ' (Utama)' : '';

            return filled($diagnosis['notes'] ?? null) ? "{$value} — {$diagnosis['notes']}" : $value;
        })
        ->all();
    $formatTreatments = static fn (array $treatments): array => collect($treatments)
        ->map(fn (array $treatment): string => filled($treatment['description'] ?? null)
            ? "{$treatment['name']} — {$treatment['description']}"
            : $treatment['name'])
        ->all();
    $formatPrescription = static function (array $snapshot): array {
        $items = collect($snapshot['prescription_items'] ?? [])->map(function (array $item): string {
            $value = collect([
                $item['medicine_name'] ?? null,
                $item['dosage'] ?? null,
                $item['frequency'] ?? null,
                filled($item['quantity'] ?? null) ? trim("{$item['quantity']} ".($item['unit'] ?? '')) : null,
            ])->filter()->join(' · ');

            return filled($item['instructions'] ?? null) ? "{$value} — {$item['instructions']}" : $value;
        });

        if (filled($snapshot['prescription']['notes'] ?? null)) {
            $items->push("Catatan: {$snapshot['prescription']['notes']}");
        }

        return $items->all();
    };
    $formatPhotos = static fn (array $snapshot): array => collect($snapshot['record']['photos'] ?? [])
        ->map(fn (array $photo): string => $photo['name'] ?? basename($photo['path'] ?? ''))
        ->filter()
        ->all();

    $fields = collect($recordFields)->map(fn (string $label, string $key): array => [
        'label' => $label,
        'before' => $before['record'][$key] ?? null,
        'after' => $after['record'][$key] ?? null,
    ])->push(
        ['label' => 'Tanda vital', 'before' => $formatVitalSigns($before['vital_signs'] ?? null), 'after' => $formatVitalSigns($after['vital_signs'] ?? null)],
        ['label' => 'Diagnosis', 'before' => $formatDiagnoses($before['diagnoses'] ?? []), 'after' => $formatDiagnoses($after['diagnoses'] ?? [])],
        ['label' => 'Tindakan', 'before' => $formatTreatments($before['treatments'] ?? []), 'after' => $formatTreatments($after['treatments'] ?? [])],
        ['label' => 'Resep', 'before' => $formatPrescription($before), 'after' => $formatPrescription($after)],
        ['label' => 'Foto pendukung', 'before' => $formatPhotos($before), 'after' => $formatPhotos($after)],
    )->filter(fn (array $field): bool => $field['before'] !== $field['after']);
@endphp

@foreach ($fields as $field)
    <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-4">
        <h4 class="truncate text-xs font-extrabold uppercase tracking-wider text-slate-500" title="{{ $field['label'] }}">{{ $field['label'] }}</h4>
        <div class="mt-3 grid min-w-0 gap-3 sm:grid-cols-2">
            @foreach (['Sebelum' => $field['before'], 'Sesudah' => $field['after']] as $label => $value)
                @php
                    $displayValue = is_array($value) ? ($value === [] ? 'Kosong' : collect($value)->join(' · ')) : (filled($value) ? $value : 'Kosong');
                @endphp
                <details
                    class="group min-w-0 overflow-hidden rounded-xl bg-slate-50 p-3 text-sm text-slate-700 open:col-span-full"
                    x-data="{
                        clipped: false,
                        observer: null,
                        init() {
                            this.$nextTick(() => {
                                this.observer = new ResizeObserver(() => this.checkClipped());
                                this.observer.observe(this.$refs.preview);
                                this.checkClipped();
                            });
                        },
                        destroy() {
                            this.observer?.disconnect();
                        },
                        checkClipped() {
                            if (this.$refs.preview.clientWidth > 0) {
                                this.clipped = this.$refs.preview.scrollWidth > this.$refs.preview.clientWidth;
                            }
                        },
                    }"
                >
                    <summary
                        class="list-none focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2"
                        x-bind:class="clipped ? 'cursor-pointer' : 'cursor-default'"
                        x-bind:tabindex="clipped ? 0 : -1"
                        x-on:click="if (! clipped) $event.preventDefault()"
                    >
                        <span class="block truncate text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</span>
                        <span x-ref="preview" class="mt-2 block truncate group-open:hidden" title="{{ $displayValue }}">{{ $displayValue }}</span>
                        <span x-show="clipped" x-cloak class="mt-2 inline-block text-xs font-bold text-clinic-700 group-open:hidden">Lihat detail</span>
                        <span class="mt-2 hidden text-xs font-bold text-clinic-700 group-open:inline-block">Tutup detail</span>
                    </summary>

                    <div class="mt-3 border-t border-slate-200 pt-3">
                        @if (is_array($value) && $value !== [])
                            <ul class="min-w-0 space-y-2 [overflow-wrap:anywhere]">
                                @foreach ($value as $item)
                                    <li class="rounded-lg bg-white px-3 py-2 leading-6">{{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="min-w-0 whitespace-pre-line rounded-lg bg-white px-3 py-2 leading-6 [overflow-wrap:anywhere]">{{ $displayValue }}</p>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </section>
@endforeach
