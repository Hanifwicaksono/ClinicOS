@props(['visit'])
<section class="clinic-card p-5" aria-labelledby="patient-allergies-heading">
    <h2 id="patient-allergies-heading" class="scroll-mt-40 font-extrabold text-clinic-950">Alergi pasien</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500">Catatan alergi mengikuti pasien. Nonaktifkan catatan yang tidak berlaku; riwayat tetap disimpan.</p>
    <div class="mt-4 space-y-4">
        @forelse($visit->patient->allergies as $allergy)
            @can('update', $visit)
                <details class="rounded-xl border border-slate-200 p-3">
                    <summary class="min-h-11 cursor-pointer break-words text-sm font-bold text-slate-800">{{ $allergy->substance }} · {{ $allergy->severity->label() }}{{ $allergy->is_active ? '' : ' · Nonaktif' }}</summary>
                    <form method="POST" action="{{ route('doctor.visits.allergies.update', [$visit, $allergy]) }}" class="mt-3 space-y-3" data-allergy-form>
                        @csrf @method('PUT')
                        <x-rme.allergy-fields :allergy="$allergy" :field-key="'allergy-'.$allergy->id" />
                        <p role="alert" class="text-sm text-red-700"></p>
                        <x-clinic-button variant="secondary" class="w-full">Perbarui alergi</x-clinic-button>
                    </form>
                </details>
            @else
                <div class="rounded-xl border border-slate-200 p-3 text-sm"><p class="break-words font-bold">{{ $allergy->substance }}</p><p class="mt-1 text-slate-600">{{ $allergy->severity->label() }} · {{ $allergy->is_active ? 'Aktif' : 'Nonaktif' }}</p><p class="mt-1 break-words">{{ $allergy->reaction }}</p></div>
            @endcan
        @empty
            <p class="text-sm text-slate-500">Belum ada alergi yang dicatat. Konfirmasikan riwayat alergi kepada pasien.</p>
        @endforelse
    </div>
    @can('update', $visit)
        <details class="mt-4 rounded-xl border border-clinic-200 p-3">
            <summary class="min-h-11 cursor-pointer text-sm font-bold text-clinic-700">+ Catat alergi</summary>
            <form method="POST" action="{{ route('doctor.visits.allergies.store', $visit) }}" class="mt-3 space-y-3" data-allergy-form>
                @csrf
                <x-rme.allergy-fields field-key="new-allergy" />
                <p role="alert" class="text-sm text-red-700"></p>
                <x-clinic-button variant="secondary" class="w-full">Simpan alergi</x-clinic-button>
            </form>
        </details>
    @endcan
</section>
