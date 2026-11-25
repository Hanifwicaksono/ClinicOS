<section class="clinic-card voice-panel mb-6" data-voice-panel
    data-base-url="{{ route('doctor.voice.index', $visit) }}"
    data-consent-version="{{ config('voice.consent_version') }}"
    data-provider="{{ config('voice.provider') }}"
    data-can-record="{{ ! $isFinal && $visit->status === \App\VisitStatus::InProgress ? 'true' : 'false' }}"
    data-max-seconds="{{ config('voice.max_session_seconds') }}"
    aria-labelledby="voice-title">
    <header class="voice-header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <span class="voice-brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 10v1a7 7 0 0 0 14 0v-1M12 18v3m-4 0h8" stroke-linecap="round"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="voice-eyebrow">Asisten dokumentasi</p>
                    <h2 id="voice-title" class="mt-1 font-display text-xl font-extrabold tracking-tight text-clinic-950">ClinicOS Voice</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Rekam konsultasi, tinjau hasil, lalu lengkapi RME.</p>
                </div>
            </div>
            <p data-voice-status class="voice-status" role="status" aria-live="polite">Memuat status…</p>
        </div>
        <details class="voice-patient-context mt-4">
            <summary class="flex min-h-11 min-w-0 cursor-pointer items-center gap-3 rounded-xl px-3 py-2">
                <span class="shrink-0 text-xs font-semibold text-slate-500">Pasien</span>
                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-clinic-950">{{ $visit->patient->name }}</span>
                <span class="shrink-0 text-xs font-medium text-slate-500">#{{ $visit->id }}</span>
                <svg class="voice-disclosure-arrow h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="grid min-w-0 gap-3 border-t border-slate-200 px-3 py-3 sm:grid-cols-2">
                <div class="min-w-0"><p class="text-xs text-slate-500">Nama pasien</p><p class="mt-1 break-words text-sm font-medium text-clinic-950 [overflow-wrap:anywhere]">{{ $visit->patient->name }}</p></div>
                <div class="min-w-0"><p class="text-xs text-slate-500">Dokter penanggung jawab</p><p class="mt-1 break-words text-sm font-medium text-clinic-950">{{ $visit->doctor->user->name }}</p><p class="mt-1 text-xs text-slate-500">Kunjungan #{{ $visit->id }}</p></div>
            </div>
        </details>
    </header>

    <div class="voice-body">
        <p data-voice-error class="voice-error" role="alert"></p>
        <a href="{{ route('admin.login') }}" data-voice-login hidden target="_blank" rel="noopener" class="mb-4 inline-flex min-h-11 items-center text-sm font-bold text-clinic-700 underline">Masuk kembali di tab baru</a>
        <div class="voice-simulation mb-5" data-voice-simulation @if(config('voice.provider') !== 'fake') hidden @endif>
            <span data-voice-simulation-label class="shrink-0 rounded-md bg-amber-100 px-2 py-1 text-xs font-bold text-amber-900">Mode simulasi</span>
            <p data-voice-simulation-copy class="text-xs leading-5 text-amber-950">Hasil berupa contoh, bukan transkripsi audio. Gunakan hanya untuk pengujian.</p>
        </div>

        <div class="voice-toolbar">
            <div class="flex min-w-0 items-start gap-2.5">
                <span class="voice-mic-dot mt-1.5" aria-hidden="true"></span>
                <div class="min-w-0">
                    <p data-voice-microphone class="text-sm font-medium leading-5 text-slate-700" aria-live="polite">Mikrofon tidak aktif.</p>
                    <p data-voice-upload class="mt-1 text-xs leading-5 text-slate-500" role="status"></p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2" aria-label="Kontrol perekaman">
                <x-clinic-button type="button" data-voice-action="start">Mulai Rekam</x-clinic-button>
                <x-clinic-button type="button" variant="secondary" data-voice-action="pause" hidden>Jeda</x-clinic-button>
                <x-clinic-button type="button" variant="secondary" data-voice-action="resume" hidden>Lanjut Rekam</x-clinic-button>
                <x-clinic-button type="button" data-voice-action="stop" hidden>Selesai Rekam</x-clinic-button>
                <x-clinic-button type="button" variant="secondary" data-voice-action="retry" hidden>Coba Lagi</x-clinic-button>
                <button type="button" class="voice-text-button" data-voice-action="manual">Input manual <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 15 15 5M5 5h10v10" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                <details class="voice-options" data-voice-options hidden>
                    <summary class="voice-text-button cursor-pointer">Opsi sesi <span aria-hidden="true">⋯</span></summary>
                    <div class="voice-options-content">
                        <button type="button" data-voice-action="cancel" hidden>Batalkan Sesi</button>
                        <button type="button" data-voice-action="withdraw" hidden>Cabut Persetujuan</button>
                    </div>
                </details>
            </div>
        </div>

        <fieldset class="voice-consent mt-5" data-voice-consent>
            <legend class="sr-only">Persiapan dan persetujuan perekaman</legend>
            <div class="mb-4">
                <h3 class="text-sm font-bold text-clinic-950">Sebelum mulai merekam</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">Pastikan pasien sesuai dan persetujuan sudah diberikan.</p>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="voice-consent-option">
                    <input type="checkbox" data-voice-patient class="voice-checkbox">
                    <span><strong class="block text-sm font-semibold text-clinic-950">Identitas pasien sesuai</strong><span class="mt-1 block text-xs leading-5 text-slate-500">Saya memastikan pasien dan kunjungan di atas sudah benar.</span></span>
                </label>
                <label class="voice-consent-option">
                    <input type="checkbox" data-voice-agreement class="voice-checkbox">
                    <span><strong class="block text-sm font-semibold text-clinic-950">Pasien menyetujui perekaman</strong><span class="mt-1 block text-xs leading-5 text-slate-500">Saya telah menjelaskan perekaman dan pemrosesan AI; pasien atau wakil yang berwenang telah menyetujuinya.</span></span>
                </label>
            </div>
            <details class="mt-3 text-xs leading-5 text-slate-500">
                <summary class="min-h-11 cursor-pointer py-3 font-medium text-clinic-700">Tentang persetujuan dan privasi</summary>
                <p>Pasien dapat menolak dan tetap memakai dokumentasi manual. Persetujuan {{ config('voice.consent_version') }} dicatat beserta dokter dan waktu. Izin mikrofon browser merupakan izin perangkat yang terpisah.</p>
            </details>
        </fieldset>

        <div data-voice-review class="mt-6" hidden>
            <div class="mb-4 flex items-start gap-3">
                <span class="voice-section-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 3H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1h-2M7 2h6v4H7zM6 10h8M6 13h5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <div><h3 class="text-base font-bold text-clinic-950">Tinjau hasil konsultasi</h3><p class="mt-1 text-sm leading-6 text-slate-500">Periksa informasi dari percakapan sebelum mengisi draft SOAP.</p></div>
            </div>
            <div class="voice-review-grid">
                <div class="min-w-0">
                    <div role="tablist" aria-label="Hasil ClinicOS Voice" class="voice-tabs">
                        @foreach(['transcript' => 'Transkrip', 'diagnoses' => 'Diagnosis', 'orders' => 'Resep & tindakan'] as $tab => $label)
                            <button type="button" role="tab" id="voice-tab-{{ $tab }}" aria-controls="voice-pane-{{ $tab }}"
                                aria-selected="{{ $tab === 'transcript' ? 'true' : 'false' }}" tabindex="{{ $tab === 'transcript' ? '0' : '-1' }}"
                                data-voice-tab="{{ $tab }}" class="voice-tab">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="voice-results">
                        <div id="voice-pane-transcript" role="tabpanel" aria-labelledby="voice-tab-transcript" data-voice-pane="transcript">
                            <div class="voice-results-heading"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Transkrip konsultasi</p><p class="mt-1 text-xs leading-5 text-slate-500">Cocokkan isi transkrip dengan percakapan pasien.</p></div>
                            <div data-voice-transcript class="voice-result-list" tabindex="0" role="region" aria-label="Isi transkrip konsultasi"></div>
                        </div>
                        <div id="voice-pane-diagnoses" role="tabpanel" aria-labelledby="voice-tab-diagnoses" data-voice-pane="diagnoses" hidden>
                            <p class="voice-results-heading text-xs leading-5 text-slate-500">Kandidat dari percakapan. Dokter tetap menentukan diagnosis dan mencatatnya pada form pemeriksaan.</p>
                            <div data-voice-diagnoses class="voice-result-list"></div>
                        </div>
                        <div id="voice-pane-orders" role="tabpanel" aria-labelledby="voice-tab-orders" data-voice-pane="orders" hidden>
                            <p class="voice-results-heading text-xs leading-5 text-slate-500">Tinjau nama, dosis, rute, durasi, alergi, dan status tindakan pada form pemeriksaan. Kandidat ini belum menjadi resep, tindakan, atau tagihan.</p>
                            <div data-voice-orders class="voice-result-list"></div>
                        </div>
                    </div>
                    <details class="voice-summary mt-4" data-voice-summary>
                        <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-clinic-800">
                            Ringkasan hasil & kutipan sumber
                            <svg class="voice-disclosure-arrow h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </summary>
                        <dl class="space-y-4 border-t border-slate-200 p-4">
                            @foreach(['chief_complaint' => 'Keluhan utama', 'subjective' => 'S · Subjektif', 'objective' => 'O · Objektif', 'assessment' => 'A · Assessment', 'plan' => 'P · Rencana'] as $field => $label)
                                <div><dt class="text-sm font-semibold text-clinic-950">{{ $label }}</dt><dd data-voice-value="{{ $field }}" class="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700"></dd><p data-voice-evidence="{{ $field }}" class="mt-2 whitespace-pre-wrap break-words border-l-2 border-clinic-200 pl-3 text-xs leading-5 text-slate-500"></p></div>
                            @endforeach
                        </dl>
                    </details>
                    <div class="voice-review-note mt-4">
                        <p class="text-xs font-semibold text-amber-900">Catatan untuk ditinjau</p>
                        <p data-voice-warnings class="mt-1 whitespace-pre-wrap text-xs leading-5 text-amber-950"></p>
                    </div>
                </div>

                <aside class="voice-apply" aria-labelledby="voice-apply-title">
                    <div class="flex items-center justify-between gap-3"><h3 id="voice-apply-title" class="text-base font-bold text-clinic-950">Isi draft SOAP</h3><span class="rounded-md border border-clinic-200 bg-white px-2 py-1 text-[11px] font-bold text-clinic-700">DRAFT</span></div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Pilih kolom dari hasil Voice.</p>
                    <fieldset class="mt-4">
                        <legend class="sr-only">Kolom SOAP yang akan diisi</legend>
                        <div class="voice-choice-list">
                            @foreach(['chief_complaint' => 'Keluhan', 'subjective' => 'Subjektif', 'objective' => 'Objektif', 'assessment' => 'Assessment', 'plan' => 'Rencana'] as $field => $label)
                                <label class="voice-choice" data-available="false">
                                    <input type="checkbox" data-voice-select="{{ $field }}" class="voice-checkbox" disabled>
                                    <span class="min-w-0"><span class="block text-xs font-semibold">{{ $label }}</span><span class="voice-choice-available mt-0.5 text-[10px]">Hasil tersedia</span><span class="voice-choice-unavailable mt-0.5 text-[10px]">Belum tercatat</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <p data-voice-selection-status class="mt-3 text-xs font-medium text-clinic-700" role="status" aria-live="polite">Pilih minimal satu kolom.</p>
                    <label class="mt-4 flex cursor-pointer items-start gap-2.5 border-t border-slate-200 pt-4 text-xs leading-5 text-slate-600">
                        <input type="checkbox" data-voice-confirm class="voice-checkbox">
                        <span>Saya menyetujui kolom yang dipilih setelah meninjau hasil Voice.</span>
                    </label>
                    <x-clinic-button type="button" class="mt-4 w-full" data-voice-action="approve">
                        Isi kolom SOAP
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </x-clinic-button>
                    <p class="mt-3 text-[11px] leading-5 text-slate-500">Pengisian hanya menyimpan draft. Lengkapi dan finalisasi melalui form RME.</p>
                    <button type="button" data-voice-action="manual" class="mt-3 inline-flex min-h-11 items-center gap-2 text-xs font-semibold text-clinic-700 underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-clinic-500">Lihat form pemeriksaan <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 15 15 5M5 5h10v10" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                </aside>
            </div>
        </div>
    </div>
</section>
