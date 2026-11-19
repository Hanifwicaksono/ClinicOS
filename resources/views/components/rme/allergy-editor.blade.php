@props(['visit'])
<section class="clinic-card p-5" aria-labelledby="patient-allergies-heading">
    <h2 id="patient-allergies-heading" class="scroll-mt-40 font-extrabold text-clinic-950">Alergi pasien</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500">Catatan alergi mengikuti pasien. Nonaktifkan catatan yang tidak berlaku; riwayat tetap disimpan.</p>
    <div class="mt-4 space-y-4">
        @forelse($visit->patient->allergies as $allergy)
            <p class="text-xs text-slate-500">Revisi {{ $allergy->revision_number }} · {{ $allergy->created_at->timezone($visit->clinic->settings?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i') }} · {{ $allergy->recordedBy?->name ?? 'Pencatat belum tersedia' }}</p>
            @if(auth()->user()->can('updateAllergies', $visit) && $allergy->superseded_by_id === null)
                <details class="rounded-xl border border-slate-200 p-3">
                    <summary class="min-h-11 cursor-pointer break-words text-sm font-bold text-slate-800">{{ $allergy->substance }} · {{ $allergy->severity->label() }}{{ $allergy->is_active ? '' : ' · Nonaktif' }}</summary>
                    <form method="POST" action="{{ route('doctor.visits.allergies.update', [$visit, $allergy]) }}" class="mt-3 space-y-3" data-allergy-form>
                        @csrf @method('PUT')
                        <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="expected_version" value="{{ $allergy->revisionToken() }}">
                        @if($allergy->lineage_id === null)
                            <p class="text-sm text-amber-800">Catatan lama belum memiliki hubungan revisi. Tinjau isinya; penyimpanan membuat riwayat revisi untuk catatan ini saja.</p>
                        @else
                            <p class="text-sm text-slate-500">Revisi {{ $allergy->revision_number }}. Perubahan disimpan sebagai versi baru; versi sebelumnya tetap tersedia.</p>
                        @endif
                        <x-rme.allergy-fields :allergy="$allergy" :field-key="'allergy-'.$allergy->id" />
                        <div><label class="clinic-label" for="allergy-{{ $allergy->id }}-reason">Alasan klinis perubahan</label><textarea class="clinic-input" id="allergy-{{ $allergy->id }}-reason" name="revision_reason" maxlength="2000" required></textarea></div>
                        <p role="alert" class="text-sm text-red-700"></p>
                        <a href="{{ route(auth()->user()->can('view',$visit) ? 'doctor.visits.show' : 'care-continuity.show', $visit) }}#patient-allergies-heading" target="_blank" rel="noopener" class="hidden text-sm font-bold text-clinic-700 underline" data-allergy-review>Buka versi terbaru di tab baru untuk membandingkan</a>
                        <x-clinic-button variant="secondary" class="w-full">Perbarui alergi</x-clinic-button>
                    </form>
                </details>
            @else
                <div class="rounded-xl border border-slate-200 p-3 text-sm"><p class="break-words font-bold">{{ $allergy->substance }}</p><p class="mt-1 text-slate-600">{{ $allergy->severity->label() }} · {{ $allergy->is_active ? 'Aktif' : 'Nonaktif' }}</p><p class="mt-1 break-words">{{ $allergy->reaction }}</p></div>
                @if($allergy->superseded_by_id !== null)<p class="text-xs text-slate-500">Versi sebelumnya, digantikan revisi berikutnya.</p>@endif
                @if($allergy->revision_reason)<p class="break-words text-sm text-slate-600">Alasan revisi: {{ $allergy->revision_reason }}</p>@endif
            @endif
        @empty
            <p class="text-sm text-slate-500">Belum ada alergi yang dicatat. Konfirmasikan riwayat alergi kepada pasien.</p>
        @endforelse
    </div>
    @can('updateAllergies', $visit)
        <details class="mt-4 rounded-xl border border-clinic-200 p-3">
            <summary class="min-h-11 cursor-pointer text-sm font-bold text-clinic-700">+ Catat alergi</summary>
            <form method="POST" action="{{ route('doctor.visits.allergies.store', $visit) }}" class="mt-3 space-y-3" data-allergy-form>
                @csrf
                <input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <x-rme.allergy-fields field-key="new-allergy" />
                <p role="alert" class="text-sm text-red-700"></p>
                <x-clinic-button variant="secondary" class="w-full">Simpan alergi</x-clinic-button>
            </form>
        </details>
    @endcan
</section>
