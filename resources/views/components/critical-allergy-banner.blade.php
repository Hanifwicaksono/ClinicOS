@props([
    'allergies' => [],
])

@if ($allergies !== [])
    <section
        {{ $attributes->class(['rounded-2xl border-2 border-red-300 bg-red-50 p-5 text-red-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 sm:p-6']) }}
        role="alert"
        aria-labelledby="critical-allergy-heading"
        tabindex="0"
        data-critical-allergy-alert
    >
        <div class="flex items-start gap-4">
            <svg class="mt-0.5 size-7 shrink-0 text-red-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 3 2.8 20h18.4L12 3Z" stroke-linejoin="round" />
                <path d="M12 9v5m0 3h.01" stroke-linecap="round" />
            </svg>
            <div class="min-w-0">
                <h2 id="critical-allergy-heading" class="text-lg font-extrabold">Alergi kritis</h2>
                <p class="mt-1 text-sm leading-6 text-red-900">Periksa alergi sebelum memberikan obat atau tindakan.</p>
                <ul class="mt-4 space-y-2">
                    @foreach ($allergies as $allergy)
                        <li class="rounded-xl border border-red-200 bg-white px-4 py-3 text-sm [overflow-wrap:anywhere]">
                            <span class="font-extrabold">{{ $allergy['substance'] }}</span>
                            @if (filled($allergy['reaction'] ?? null))
                                <span> — {{ $allergy['reaction'] }}</span>
                            @endif
                            @if (filled($allergy['severity'] ?? null))
                                <span class="mt-1 block text-xs font-bold uppercase tracking-wide text-red-700">Keparahan: {{ $allergy['severity'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
@endif
