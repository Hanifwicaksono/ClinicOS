@props(['allergies'])
@php
    $currentAllergies = collect($allergies)->filter(fn ($allergy): bool => $allergy->is_active && $allergy->superseded_by_id === null);
    $hasCriticalAllergy = $currentAllergies->contains(fn ($allergy): bool => $allergy->severity === \App\AllergySeverity::Critical);
    $allergyState = $hasCriticalAllergy ? 'critical' : ($currentAllergies->isNotEmpty() ? 'recorded' : 'unconfirmed');
@endphp
<span {{ $attributes->class(['rme-allergy-status inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs font-bold', 'border-red-300 bg-red-50 text-red-800' => $hasCriticalAllergy, 'border-amber-200 bg-amber-50 text-amber-900' => $allergyState === 'unconfirmed', 'border-slate-300 bg-slate-50 text-slate-700' => $allergyState === 'recorded']) }} data-allergy-state="{{ $allergyState }}">
    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z" stroke-linejoin="round"/><path d="M12 9v5m0 3h.01" stroke-linecap="round"/></svg>
    @if($hasCriticalAllergy) Alergi kritis
    @elseif($allergyState === 'recorded') Alergi tercatat · {{ $currentAllergies->count() }}
    @else Alergi belum dikonfirmasi
    @endif
</span>
