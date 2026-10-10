<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Dokumentasi klinis</p>
            <h1 class="mt-1 text-xl font-extrabold text-clinic-950">Rekam medis</h1>
        </div>
    </x-slot>

    <div class="mb-7">
        <h2 class="text-2xl font-extrabold text-clinic-950">Riwayat pasien</h2>
        <p class="mt-2 text-sm text-slate-500">Hanya menampilkan rekam medis pasien yang di tangani.</p>
    </div>

    <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ([
            ['Semua', $summary['total'], 'text-clinic-700'],
            ['Draft', $summary['draft'], 'text-[#D89B68]'],
            ['Final', $summary['final'], 'text-[#287F78]'],
        ] as [$label, $value, $color])
            <article class="clinic-card p-5">
                <p class="text-sm font-semibold text-slate-500">{{ $label }}</p>
                <p class="mt-3 font-display text-3xl font-extrabold {{ $color }}">{{ $value }}</p>
            </article>
        @endforeach
    </section>

    <form method="GET" class="clinic-card mb-6 grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-[minmax(240px,1.5fr)_180px_170px_170px_auto]">
        <div>
            <label class="clinic-label" for="search">Cari</label>
            <input class="clinic-input" id="search" name="search" value="{{ $search }}" placeholder="Nama, no. RM, diagnosis, layanan…">
        </div>
        <div>
            <label class="clinic-label" for="status">Status</label>
            <select class="clinic-input" id="status" name="status">
                <option value="">Semua status</option>
                <option value="DRAFT" @selected($status === 'DRAFT')>Draft</option>
                <option value="FINAL" @selected($status === 'FINAL')>Final</option>
            </select>
        </div>
        <div>
            <label class="clinic-label" for="from">Dari tanggal</label>
            <input class="clinic-input" id="from" name="from" type="date" value="{{ $from }}">
        </div>
        <div>
            <label class="clinic-label" for="to">Sampai tanggal</label>
            <input class="clinic-input" id="to" name="to" type="date" value="{{ $to }}">
        </div>
        <div class="flex items-end gap-2">
            <x-clinic-button variant="primary" class="flex-1 px-4">Terapkan</x-clinic-button>
            @if ($search !== '' || $status !== '' || $from !== '' || $to !== '')
                <x-clinic-button href="{{ route('doctor.medical-records.index') }}" variant="secondary" class="px-4" wire:navigate>Reset</x-clinic-button>
            @endif
        </div>
    </form>

    <div class="clinic-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="clinic-table w-full">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pasien</th>
                        <th>Layanan</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($medicalRecords as $medicalRecord)
                        @php($primaryDiagnosis = $medicalRecord->diagnoses->firstWhere('is_primary', true) ?? $medicalRecord->diagnoses->first())
                        <tr>
                            <td>
                                <p class="font-bold text-slate-900">{{ $medicalRecord->visit->started_at->format('d M Y') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $medicalRecord->visit->started_at->format('H:i') }}</p>
                            </td>
                            <td>
                                <p class="font-bold text-slate-900">{{ $medicalRecord->patient->name }}</p>
                                <p class="mt-1 text-xs font-semibold text-clinic-600">{{ $medicalRecord->patient->medical_record_number }}</p>
                            </td>
                            <td>
                                <p class="font-semibold text-slate-800">{{ $medicalRecord->visit->service_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $medicalRecord->chief_complaint ?: 'Keluhan belum dicatat' }}</p>
                            </td>
                            <td>
                                @if ($primaryDiagnosis)
                                    <p class="font-semibold text-slate-800">{{ $primaryDiagnosis->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $primaryDiagnosis->code ?: 'Tanpa kode' }}{{ $medicalRecord->diagnoses->count() > 1 ? ' · +'.($medicalRecord->diagnoses->count() - 1).' lainnya' : '' }}</p>
                                @else
                                    <span class="text-sm text-slate-400">Belum ada diagnosis</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $medicalRecord->status === \App\MedicalRecordStatus::Final ? 'clinic-status-active' : 'rounded-full bg-[#F7F1EA] px-3 py-1.5 text-xs font-bold text-[#D89B68]' }}">
                                    {{ $medicalRecord->status === \App\MedicalRecordStatus::Final ? 'Final' : 'Draft' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('doctor.visits.show', $medicalRecord->visit) }}" class="font-bold text-clinic-600" wire:navigate>
                                    {{ $medicalRecord->status === \App\MedicalRecordStatus::Final ? 'Lihat' : 'Lanjutkan' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-14 text-center">
                                <p class="font-bold text-slate-700">Rekam medis tidak ditemukan</p>
                                <p class="mt-2 text-sm text-slate-500">Ubah filter atau mulai pemeriksaan dari halaman antrean.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($medicalRecords->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $medicalRecords->links() }}</div>
        @endif
    </div>
</x-app-layout>
