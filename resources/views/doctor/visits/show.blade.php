@php
    $timezone = $visit->clinic->settings?->timezone ?? 'Asia/Jakarta';
    $record = $visit->medicalRecord;
    $isInterrupted = $visit->status === \App\VisitStatus::Interrupted;
    $isFinal = $record->status === \App\MedicalRecordStatus::Final;
    $ignoredRevisionKeys = ['id', 'medical_record_id', 'prescription_id', 'created_at', 'updated_at', 'sort_order'];
    $normalizeRevisionSnapshot = function (mixed $value) use (&$normalizeRevisionSnapshot, $ignoredRevisionKeys): mixed {
        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->reject(fn (mixed $item, int|string $key): bool => in_array($key, $ignoredRevisionKeys, true))
            ->map(fn (mixed $item): mixed => $normalizeRevisionSnapshot($item))
            ->all();
    };
    $modifications = $record->revisions->filter(fn ($revision): bool => $normalizeRevisionSnapshot($revision->before_data) !== $normalizeRevisionSnapshot($revision->after_data));
    $hasModifications = $modifications->isNotEmpty();
    $vital = $record->vitalSign;
    $prescriptionRows = old('prescription_items', $record->prescription?->items->map(fn ($item) => [
        'medicine_name' => $item->medicine_name, 'dosage' => $item->dosage, 'frequency' => $item->frequency,
        'quantity' => $item->quantity, 'unit' => $item->unit ?? '', 'instructions' => $item->instructions ?? '',
    ])->all() ?? []);
    $prescriptionRows = $prescriptionRows ?: [['medicine_name' => '', 'dosage' => '', 'frequency' => '', 'quantity' => '', 'unit' => '', 'instructions' => '']];
    $rmeFormState = match (true) {
        $errors->any() => 'error',
        $isFinal => 'finalized',
        session('status') === 'Draft rekam medis berhasil disimpan.' => 'saved',
        default => 'ready',
    };
@endphp
<x-app-layout clinical-workspace>
    <x-slot name="header">
        <div class="rme-patient-header w-full py-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-clinic-600">Pemeriksaan pasien · {{ $visit->queue->display_number }}</p>
                <h1 class="mt-1 truncate text-lg font-extrabold text-clinic-950 sm:text-xl" title="{{ $visit->patient->name }}">{{ $visit->patient->name }}</h1>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ $visit->patient->medical_record_number }} · {{ $visit->patient->birth_date->age }} tahun · {{ $visit->patient->gender === 'MALE' ? 'Laki-laki' : 'Perempuan' }}<span class="block sm:inline"> · {{ $visit->service_name }}</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                <span class="rounded-lg bg-clinic-50 px-2 py-1 text-clinic-800">{{ $visit->status->label() }}</span>
                <span class="rounded-lg border border-slate-200 px-2 py-1">RME {{ $isFinal ? ($hasModifications ? 'Final · dikoreksi' : 'Final') : 'Draft' }}</span>
                <a href="#patient-history-heading" class="inline-flex min-h-11 items-center text-clinic-700 underline">Riwayat</a>
                <a href="#patient-allergies-heading" class="inline-flex min-h-11 items-center underline"><x-rme.allergy-status :allergies="$visit->patient->allergies" /></a>
            </div>
        </div>
    </x-slot>
    <x-critical-allergy-banner :allergies="$criticalAllergies" id="critical-allergy-banner" class="rme-critical-allergies mb-3 scroll-mt-48" />
    @if(in_array($visit->status,[\App\VisitStatus::Paused,\App\VisitStatus::Interrupted],true))
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"><div><p class="font-extrabold text-amber-950">{{ $visit->status->label() }}</p><p class="mt-1 text-sm text-amber-900">{{ $isInterrupted ? 'Finalisasi dokumentasi interupsi tidak menyelesaikan pelayanan.' : 'Buka kelanjutan pelayanan untuk melanjutkan pemeriksaan ini.' }}</p></div><a class="clinic-button-secondary" href="{{ route('care-continuity.show',$visit) }}">{{ $isInterrupted ? 'Kelola kelanjutan' : 'Lanjutkan pemeriksaan' }}</a></div>
    @endif
    @if(app(\App\Services\VoiceScribeService::class)->enabledFor($visit->clinic_id) && $visit->doctor->user_id === auth()->id())
        <details class="rme-voice-disclosure clinic-disclosure mb-4" data-rme-voice-disclosure>
            <summary class="flex flex-wrap items-center justify-between gap-2"><span>Asistensi AI &amp; mikrofon</span><span data-rme-voice-status class="text-xs font-normal text-slate-500" role="status" aria-live="polite">Memuat status…</span></summary>
            @include('doctor.visits.voice-panel')
        </details>
    @endif
    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
        <form method="POST" enctype="multipart/form-data" action="{{ $isFinal ? route('doctor.visits.correct', $visit) : route('doctor.visits.save', $visit) }}" class="rme-workspace min-w-0 space-y-4" x-data="{
            medicines: @js($prescriptionRows),
            diagnoses: @js(old('diagnoses', $record->diagnoses->map->only(['code', 'name', 'notes', 'is_primary'])->all()) ?: [['code' => '', 'name' => '', 'notes' => '', 'is_primary' => true]]),
            treatments: @js(old('treatments', $record->treatments->map->only(['name', 'description', 'quantity'])->all()) ?: [['name' => '', 'description' => '', 'quantity' => 1]]),
            prescriptionNotes: @js(old('prescription_notes', $record->prescription?->notes)),
            selectedPhotos: [],
            removedPhotos: @js(array_map('intval', old('removed_photos', []))),
            submitting: false,
            get filledMedicines() {
                return this.medicines.filter(item => String(item.medicine_name ?? '').trim() !== '');
            },
            get incompleteMedicineCount() {
                return this.filledMedicines.filter(item => ! String(item.dosage ?? '').trim() || ! String(item.frequency ?? '').trim() || ! String(item.quantity ?? '').trim()).length;
            },
            addPhotos(files) {
                [...files].filter(file => file.type.startsWith('image/')).forEach(file => {
                    if (this.selectedPhotos.length < 10) {
                        this.selectedPhotos.push({ file, url: URL.createObjectURL(file) });
                    }
                });
                this.syncPhotos();
            },
            removePhoto(index) {
                URL.revokeObjectURL(this.selectedPhotos[index].url);
                this.selectedPhotos.splice(index, 1);
                this.syncPhotos();
            },
            syncPhotos() {
                const files = new DataTransfer();
                this.selectedPhotos.forEach(photo => files.items.add(photo.file));
                this.$refs.photos.files = files.files;
                this.$dispatch('rme-changed');
            },
        }" data-rme-form data-rme-workspace @unless($isFinal) data-rme-autosave @endunless @paste.window="const images = [...$event.clipboardData.files].filter(file => file.type.startsWith('image/')); if (images.length) { $event.preventDefault(); addPhotos(images); }">
            @csrf @method('PUT')
            @if($isInterrupted)<input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="workflow_version" value="{{ $visit->workflow_version }}">@endif
            <input type="hidden" name="record_version" value="{{ old('record_version', $record->lock_version) }}">
            <input type="hidden" name="allergy_version" value="{{ old('allergy_version', $visit->patient->allergyVersion()) }}">
            <template x-for="index in removedPhotos" :key="index"><input type="hidden" name="removed_photos[]" :value="index"></template>
            @if($isFinal)<section class="rounded-2xl border border-amber-300 bg-amber-50 p-5"><p class="font-bold text-amber-950">Mode Modification rekam medis finalized</p><p class="mt-1 text-sm leading-6 text-amber-900">Perubahan tidak menimpa histori. Sistem menyimpan snapshot sebelum dan sesudah Modification beserta pelaku, waktu, dan alasan.</p><label class="clinic-label mt-4" for="correction_reason">Alasan Modification</label><textarea class="clinic-input" id="correction_reason" name="correction_reason" rows="2" minlength="10" required aria-describedby="correction_reason_help">{{ old('correction_reason') }}</textarea><p id="correction_reason_help" class="mt-2 text-xs text-amber-900">Minimal 10 karakter dan harus menjelaskan alasan klinis atau administratif yang spesifik.</p></section>@endif

            <nav class="rme-tabs" role="tablist" aria-label="Bagian pemeriksaan" data-rme-tabs>
                @foreach(['soap' => 'SOAP', 'vital' => 'Tanda vital', 'clinical' => 'Diagnosis & tindakan', 'prescription' => 'Resep'] as $tab => $label)
                    <button type="button" role="tab" id="rme-tab-{{ $tab }}" aria-controls="rme-panel-{{ $tab }}" aria-selected="{{ $tab === 'soap' ? 'true' : 'false' }}" tabindex="{{ $tab === 'soap' ? '0' : '-1' }}" data-rme-tab="{{ $tab }}"><span>{{ $label }}</span><span data-rme-tab-status class="mt-1 block text-[11px] font-medium">{{ $tab === 'soap' ? '3 isian wajib' : 'Opsional' }}</span></button>
                @endforeach
            </nav>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" data-rme-validation-summary role="alert" hidden></div>
            <div id="rme-panel-soap" role="tabpanel" aria-labelledby="rme-tab-soap" tabindex="0" data-rme-panel="soap" class="rme-panel space-y-4">
            <x-rme.section heading-id="rme-general-heading" title="RME General" description="S, A, P wajib untuk finalisasi.">


                <div class="rme-soap-fields grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-rme.textarea-field
                            name="chief_complaint"
                            maxlength="5000"
                            label="Keluhan utama"
                            :value="old('chief_complaint', $record->chief_complaint)"
                            :messages="$errors->get('chief_complaint')"
                            rows="2"
                            description="Ringkasan keluhan yang membawa pasien datang."
                        />
                    </div>
                    <x-rme.textarea-field
                        name="subjective"
                        maxlength="10000"
                        label="S · Subjektif"
                        :value="old('subjective', $record->subjective ?: $record->chief_complaint)"
                        :messages="$errors->get('subjective')"
                        rows="3"
                        required
                        description="Keluhan, riwayat, dan informasi yang disampaikan pasien."
                    />
                    <x-rme.textarea-field
                        name="objective"
                        maxlength="10000"
                        label="O · Objektif"
                        :value="old('objective', $record->objective)"
                        :messages="$errors->get('objective')"
                        rows="3"
                        description="Temuan pemeriksaan dan data objektif klinis."
                    />
                    <x-rme.textarea-field
                        name="assessment"
                        maxlength="10000"
                        label="A · Assessment"
                        :value="old('assessment', $record->assessment ?: $record->diagnoses->pluck('name')->join(', '))"
                        :messages="$errors->get('assessment')"
                        rows="3"
                        required
                        description="Penilaian atau diagnosis kerja dokter."
                    />
                    <x-rme.textarea-field
                        name="plan"
                        maxlength="10000"
                        label="P · Rencana"
                        :value="old('plan', $record->plan ?: $record->treatments->pluck('name')->join(', '))"
                        :messages="$errors->get('plan')"
                        rows="3"
                        required
                        description="Terapi, edukasi, pemeriksaan lanjutan, atau rencana kontrol."
                    />
                    <div class="md:col-span-2">
                        <x-rme.textarea-field
                            name="physical_examination"
                        maxlength="10000"
                            label="Pemeriksaan fisik"
                            :value="old('physical_examination', $record->physical_examination)"
                            :messages="$errors->get('physical_examination')"
                            rows="3"
                        />
                    </div>
                    <details class="md:col-span-2 rounded-xl border border-slate-200 p-4" data-rme-photo-details><summary class="min-h-11 cursor-pointer text-sm font-bold text-clinic-700">Foto pendukung ({{ count($record->photos ?? []) }} tersimpan)</summary>
                    <div class="md:col-span-2">
                        <label class="clinic-label" for="photos">Foto pendukung <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input class="clinic-input file:mr-4 file:rounded-lg file:border-0 file:bg-clinic-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-clinic-700" id="photos" x-ref="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="photos_description{{ $errors->has('photos') || $errors->has('photos.*') ? ' photos_error' : '' }}" @change="addPhotos($event.target.files)" @if($errors->has('photos') || $errors->has('photos.*')) aria-invalid="true" @endif>
                        <p id="photos_description" class="mt-2 text-xs text-slate-500">Pilih hingga 10 foto (maks. 5 MB per foto), atau paste gambar langsung di halaman ini.</p>
                        <x-input-error id="photos_error" :messages="array_merge($errors->get('photos'), $errors->get('photos.*'))" class="mt-2" role="alert" />
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4" x-show="selectedPhotos.length" x-cloak>
                            <template x-for="(photo, index) in selectedPhotos" :key="photo.url">
                                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                    <img :src="photo.url" :alt="photo.file.name" class="h-28 w-full object-cover">
                                    <button type="button" class="absolute right-2 top-2 flex size-7 items-center justify-center rounded-full bg-slate-950/75 text-lg font-bold leading-none text-white hover:bg-red-600" @click="removePhoto(index)" aria-label="Hapus foto yang dipilih">×</button>
                                    <p class="truncate px-3 py-2 text-xs text-slate-600" x-text="photo.file.name"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                    @if($record->photos)
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:col-span-2 md:grid-cols-4">
                            @foreach($record->photos as $index => $photo)
                                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50" x-show="!removedPhotos.includes({{ $index }})" x-cloak>
                                    <a href="{{ route('doctor.visits.photos.show', [$visit, $index]) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('doctor.visits.photos.show', [$visit, $index]) }}" alt="{{ $photo['name'] ?? 'Foto rekam medis' }}" class="h-28 w-full object-cover" loading="lazy">
                                        <p class="truncate px-3 py-2 text-xs text-slate-600">{{ $photo['name'] ?? 'Foto rekam medis' }}</p>
                                    </a>
                                    <button type="button" class="absolute right-2 top-2 flex size-7 items-center justify-center rounded-full bg-slate-950/75 text-lg font-bold leading-none text-white hover:bg-red-600" @click="removedPhotos.push({{ $index }}); $nextTick(() => $dispatch('rme-changed'))" aria-label="Hapus {{ $photo['name'] ?? 'foto rekam medis' }}">×</button>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs font-semibold text-red-600 md:col-span-2" x-show="removedPhotos.length" x-cloak>Foto bertanda hapus akan dihapus saat rekam medis disimpan.</p>
                    @endif
                    </details>
                </div>
            </x-rme.section>

            <x-rme.section heading-id="doctor-notes-heading" title="Catatan tambahan" description="Catatan internal dokter yang tetap menjadi bagian rekam medis.">
                <x-rme.textarea-field
                    name="doctor_notes"
                    label="Catatan dokter"
                    :value="old('doctor_notes', $record->doctor_notes)"
                    :messages="$errors->get('doctor_notes')"
                    rows="2"
                    maxlength="10000"
                />
            </x-rme.section>

            </div>
            <div id="rme-panel-vital" role="tabpanel" aria-labelledby="rme-tab-vital" tabindex="0" data-rme-panel="vital" class="rme-panel space-y-4">
            <x-rme.section heading-id="vital-signs-heading" title="Tanda vital" description="Catat hasil pengukuran kunjungan ini. Semua isian opsional.">
                @if($vital)<p class="mb-4 text-xs text-slate-500">Data dari RME kunjungan ini · terakhir dicatat {{ $vital->updated_at->timezone($timezone)->format('d M Y, H:i') }}. Koreksi hanya bila diperlukan.</p>@endif
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['weight_kg', 'Berat badan', 'kg', '0.1', '0.1', '500'],
                        ['height_cm', 'Tinggi badan', 'cm', '0.1', '10', '300'],
                        ['systolic', 'Sistolik', 'mmHg', '1', '40', '300'],
                        ['diastolic', 'Diastolik', 'mmHg', '1', '20', '200'],
                        ['pulse', 'Nadi', 'x/menit', '1', '20', '250'],
                        ['respiratory_rate', 'Pernapasan', 'x/menit', '1', '5', '100'],
                        ['temperature_c', 'Suhu', '°C', '0.1', '30', '45'],
                        ['oxygen_saturation', 'Saturasi oksigen', '%', '1', '40', '100'],
                    ] as [$name, $label, $unit, $step, $min, $max])
                        <x-rme.input-field
                            :name="'vital_signs['.$name.']'"
                            :id="'vital_'.$name"
                            :label="$label"
                            type="number"
                            :unit="$unit"
                            :value="old('vital_signs.'.$name, $vital?->{$name})"
                            :messages="$errors->get('vital_signs.'.$name)"
                            :step="$step"
                            :min="$min"
                            :max="$max"
                            inputmode="decimal"
                        />
                    @endforeach
                </div>
            </x-rme.section>

            </div>
            <div id="rme-panel-clinical" role="tabpanel" aria-labelledby="rme-tab-clinical" tabindex="0" data-rme-panel="clinical" class="rme-panel space-y-4">
            <x-rme.section heading-id="structured-clinical-heading" title="Diagnosis dan tindakan" description="Catat diagnosis serta tindakan yang benar-benar diberikan. Diagnosis pertama menjadi utama jika tidak dipilih.">
                <div class="space-y-5">
                    <template x-for="(diagnosis, index) in diagnoses" :key="index">
                        <fieldset data-rme-row="diagnosis" class="rounded-2xl border border-slate-200 p-4">
                            <legend class="px-2 text-sm font-bold" x-text="`Diagnosis ${index + 1}`"></legend>
                            <div class="grid gap-4 sm:grid-cols-[8rem_1fr]">
                                <div><label class="clinic-label" :for="`diagnosis_code_${index}`">Kode (opsional)</label><input class="clinic-input" :id="`diagnosis_code_${index}`" :name="`diagnoses[${index}][code]`" x-model="diagnosis.code" maxlength="30"></div>
                                <div><label class="clinic-label" :for="`diagnosis_name_${index}`">Diagnosis</label><input class="clinic-input" :id="`diagnosis_name_${index}`" :name="`diagnoses[${index}][name]`" x-model="diagnosis.name" maxlength="255"></div>
                                <div class="sm:col-span-2"><label class="clinic-label" :for="`diagnosis_notes_${index}`">Catatan</label><textarea class="clinic-input" :id="`diagnosis_notes_${index}`" :name="`diagnoses[${index}][notes]`" x-model="diagnosis.notes" maxlength="2000" rows="2"></textarea></div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" :name="`diagnoses[${index}][is_primary]`" value="1" x-model="diagnosis.is_primary" @change="if (diagnosis.is_primary) diagnoses.forEach((row, i) => { if (i !== index) row.is_primary = false; })" class="rounded text-clinic-600 focus:ring-clinic-500">Diagnosis utama</label>
                                <button type="button" class="min-h-11 text-sm font-bold text-red-700 focus-visible:ring-2 focus-visible:ring-red-500" @click="diagnoses.splice(index, 1); if (!diagnoses.length) diagnoses.push({code:'',name:'',notes:'',is_primary:true}); $nextTick(() => $dispatch('rme-changed'))">Hapus diagnosis <span x-text="index + 1" class="sr-only"></span></button>
                            </div>
                        </fieldset>
                    </template>
                    <x-clinic-button type="button" variant="secondary" data-rme-add="diagnosis" @click="diagnoses.push({code:'',name:'',notes:'',is_primary:false})" x-bind:disabled="diagnoses.length >= 20">+ Tambah diagnosis</x-clinic-button>
                    <template x-for="(treatment, index) in treatments" :key="index">
                        <fieldset data-rme-row="treatment" class="rounded-2xl border border-slate-200 p-4">
                            <legend class="px-2 text-sm font-bold" x-text="`Tindakan ${index + 1}`"></legend>
                            <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                                <div><label class="clinic-label" :for="`treatment_name_${index}`">Tindakan</label><input class="clinic-input" :id="`treatment_name_${index}`" :name="`treatments[${index}][name]`" x-model="treatment.name" maxlength="255"></div>
                                <div><label class="clinic-label" :for="`treatment_quantity_${index}`">Jumlah</label><input class="clinic-input" :id="`treatment_quantity_${index}`" :name="`treatments[${index}][quantity]`" x-model="treatment.quantity" x-bind:required="String(treatment.name ?? '').trim() !== ''" type="number" min="1" max="9999"></div>
                                <div class="sm:col-span-2"><label class="clinic-label" :for="`treatment_description_${index}`">Rincian tindakan</label><textarea class="clinic-input" :id="`treatment_description_${index}`" :name="`treatments[${index}][description]`" x-model="treatment.description" maxlength="3000" rows="2"></textarea></div>
                            </div>
                            <button type="button" class="mt-2 min-h-11 text-sm font-bold text-red-700 focus-visible:ring-2 focus-visible:ring-red-500" @click="treatments.splice(index,1); if (!treatments.length) treatments.push({name:'',description:'',quantity:1}); $nextTick(() => $dispatch('rme-changed'))">Hapus tindakan <span x-text="index + 1" class="sr-only"></span></button>
                        </fieldset>
                    </template>
                    <x-clinic-button type="button" variant="secondary" data-rme-add="treatment" @click="treatments.push({name:'',description:'',quantity:1})" x-bind:disabled="treatments.length >= 30">+ Tambah tindakan</x-clinic-button>
                </div>
            </x-rme.section>

<details class="clinic-disclosure"><summary>Odontogram</summary>            @if ($canEditOdontogram || $hasOdontogram)
            @if ($canEditOdontogram)
                <p class="rounded-xl border border-clinic-200 bg-clinic-50 p-4 text-sm font-semibold text-clinic-800" role="status">Odontogram aktif. Perubahan disimpan saat Anda menyimpan RME.</p>
            @endif
            @unless ($canEditOdontogram)
                <p class="rounded-xl border border-clinic-200 bg-clinic-50 p-4 text-sm text-clinic-800" role="status">Add-on odontogram tidak aktif. Histori di bawah ini hanya dapat dibaca.</p>
            @endunless
            <x-dental-chart
                :teeth="$dentalChart['teeth']"
                :conditions="$dentalChart['conditions']"
                :selected-tooth="$dentalChart['selected_tooth']"
                :read-only="$dentalChart['read_only']"
            />
            @else
                <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600" role="status">Odontogram belum aktif untuk klinik ini. RME General tetap tersedia.</p>
            @endif

</details>
            </div>
            <div id="rme-panel-prescription" role="tabpanel" aria-labelledby="rme-tab-prescription" tabindex="0" data-rme-panel="prescription" class="rme-panel space-y-4">
            <section class="clinic-card p-6 sm:p-8" aria-labelledby="prescription-heading">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 id="prescription-heading" class="text-lg font-extrabold text-clinic-950">Resep</h2>
                        <p class="mt-1 text-sm text-slate-500">Tambahkan obat, lengkapi aturan pakai, lalu periksa preview sebelum finalisasi.</p>
                    </div>
                    <x-clinic-button type="button" @click="medicines.push({medicine_name:'',dosage:'',frequency:'',quantity:'',unit:'',instructions:''})" variant="secondary" data-rme-add="medicine" x-bind:disabled="medicines.length >= 30" class="px-4 py-2">+ Tambah obat</x-clinic-button>
                </div>

                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950" role="note">
                    <p class="font-bold">Peringatan keselamatan resep</p>
                    <p>Periksa alergi, duplikasi terapi, interaksi, dosis, dan kondisi pasien sebelum finalisasi. Peringatan ini tidak menggantikan penilaian klinis dokter.</p>
                </div>

                @if($criticalAllergies !== [])
                    <label class="mt-5 flex items-start gap-3 rounded-2xl border border-red-300 bg-red-50 p-4 text-sm text-red-950" for="critical_allergies_acknowledged">
                        <input
                            id="critical_allergies_acknowledged"
                            name="critical_allergies_acknowledged"
                            type="checkbox"
                            value="1"
                            class="mt-1 rounded border-red-300 text-red-700 focus:ring-red-500"
                            x-bind:required="filledMedicines.length > 0"
                            @checked(old('critical_allergies_acknowledged'))
                        >
                        <span>
                            <span class="block font-extrabold">Saya telah meninjau alergi kritis pasien</span>
                            <span class="mt-1 block leading-6">Konfirmasi ini wajib saat resep berisi obat. Sistem mencatat dokter dan waktu peninjauan.</span>
                        </span>
                    </label>
                    <x-input-error :messages="$errors->get('critical_allergies_acknowledged')" class="mt-2" role="alert" />
                @endif

                <div class="mt-6 space-y-4">
                    <template x-for="(item, index) in medicines" :key="index">
                        <fieldset data-rme-row="medicine" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <legend class="px-2 text-sm font-bold text-slate-700" x-text="`Obat ${index + 1}`"></legend>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="clinic-label" :for="`medicine_name_${index}`">Nama obat</label>
                                    <input class="clinic-input" :id="`medicine_name_${index}`" :name="`prescription_items[${index}][medicine_name]`" x-model="item.medicine_name" autocomplete="off" placeholder="Contoh: Paracetamol 500 mg">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_dosage_${index}`">Dosis</label>
                                    <input class="clinic-input" :id="`medicine_dosage_${index}`" :name="`prescription_items[${index}][dosage]`" x-model="item.dosage" x-bind:required="String(item.medicine_name ?? '').trim() !== ''" placeholder="Contoh: 1 tablet">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_frequency_${index}`">Frekuensi</label>
                                    <input class="clinic-input" :id="`medicine_frequency_${index}`" :name="`prescription_items[${index}][frequency]`" x-model="item.frequency" x-bind:required="String(item.medicine_name ?? '').trim() !== ''" placeholder="Contoh: 3 kali sehari">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_quantity_${index}`">Jumlah</label>
                                    <input class="clinic-input" :id="`medicine_quantity_${index}`" :name="`prescription_items[${index}][quantity]`" x-model="item.quantity" x-bind:required="String(item.medicine_name ?? '').trim() !== ''" type="number" min="1" max="9999" placeholder="10">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_unit_${index}`">Satuan</label>
                                    <input class="clinic-input" :id="`medicine_unit_${index}`" :name="`prescription_items[${index}][unit]`" x-model="item.unit" placeholder="Contoh: tablet">
                                </div>
                                <div class="flex items-end">
                                    <button type="button" @click="medicines.splice(index,1); $nextTick(() => $dispatch('rme-changed'))" class="inline-flex min-h-11 items-center font-bold text-red-600 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">Hapus obat</button>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="clinic-label" :for="`medicine_instructions_${index}`">Instruksi penggunaan <span class="font-normal text-slate-400">(opsional)</span></label>
                                    <textarea class="clinic-input" :id="`medicine_instructions_${index}`" :name="`prescription_items[${index}][instructions]`" x-model="item.instructions" rows="2" placeholder="Contoh: diminum setelah makan"></textarea>
                                </div>
                            </div>
                        </fieldset>
                    </template>

                    <x-clinic-button type="button" variant="secondary" data-rme-add="medicine" x-bind:disabled="medicines.length >= 30" @click="medicines.push({medicine_name:'',dosage:'',frequency:'',quantity:'',unit:'',instructions:''})">+ Tambah obat berikutnya</x-clinic-button>

                    <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700" role="alert" x-show="incompleteMedicineCount > 0" x-cloak>
                        <span x-text="`${incompleteMedicineCount} obat belum memiliki dosis, frekuensi, atau jumlah lengkap.`"></span>
                    </p>

                    <div>
                        <label class="clinic-label" for="prescription_notes">Catatan resep</label>
                        <textarea class="clinic-input" id="prescription_notes" name="prescription_notes" rows="2" x-model="prescriptionNotes">{{ old('prescription_notes', $record->prescription?->notes) }}</textarea>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-clinic-200 bg-clinic-50 p-5" aria-live="polite">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-extrabold text-clinic-950">Preview resep</h3>
                        <span class="text-xs font-bold uppercase tracking-wider text-clinic-700" x-text="`${filledMedicines.length} obat`"></span>
                    </div>
                    <p class="mt-3 text-sm text-slate-500" x-show="filledMedicines.length === 0">Belum ada obat pada resep.</p>
                    <ol class="mt-3 space-y-3" x-show="filledMedicines.length > 0" x-cloak>
                        <template x-for="(item, index) in filledMedicines" :key="`${item.medicine_name}-${index}`">
                            <li class="rounded-xl bg-white p-4 text-sm shadow-sm">
                                <p class="font-bold text-slate-900" x-text="item.medicine_name"></p>
                                <p class="mt-1 text-slate-600"><span x-text="item.dosage || 'Dosis belum diisi'"></span> · <span x-text="item.frequency || 'Frekuensi belum diisi'"></span> · <span x-text="item.quantity || 'Jumlah belum diisi'"></span> <span x-text="item.unit || ''"></span></p>
                                <p class="mt-1 text-xs text-slate-500" x-show="item.instructions" x-text="item.instructions"></p>
                            </li>
                        </template>
                    </ol>
                    <p class="mt-3 text-xs leading-5 text-slate-600" x-show="prescriptionNotes" x-cloak><span class="font-bold text-slate-800">Catatan:</span> <span x-text="prescriptionNotes"></span></p>
                </div>
            </section>

            </div>
            <div class="rme-actions"><div class="min-w-0 flex-1"><x-rme.autosave-status :state="$rmeFormState" :message="$errors->first()" /></div><div class="flex flex-wrap items-center gap-2">
                @if($isFinal)
                    <x-clinic-button variant="primary" class="disabled:opacity-60" x-bind:disabled="submitting">Simpan Modification</x-clinic-button>
                @else
                    <x-clinic-button variant="secondary" class="disabled:opacity-60" x-bind:disabled="submitting" formnovalidate>Simpan draft</x-clinic-button>
                    <x-clinic-button type="button" variant="primary" data-rme-review class="disabled:opacity-60" x-bind:disabled="submitting" x-on:click="$dispatch('open-modal', 'confirm-finalize-visit')">Tinjau &amp; finalisasi</x-clinic-button>
                @endif
            </div></div>

            @unless($isFinal)
                <x-modal name="confirm-finalize-visit" maxWidth="2xl" label="Konfirmasi finalisasi kunjungan" focusable>
                    <div class="rme-finalize-review p-6 sm:p-8">
                        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-clinic-600">Konfirmasi finalisasi</p>
                        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Jadikan rekam medis ini finalized?</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Setelah finalisasi, draft menjadi catatan resmi. Perubahan berikutnya hanya dapat disimpan sebagai Modification beralasan dan histori sebelum/sesudah akan tetap terlihat.</p>

                        @if($isInterrupted)<label class="mt-4 flex gap-2"><input type="checkbox" name="documentation_attested" value="1" required>Saya penulis RME dan mengesahkan dokumentasi kejadian interupsi ini</label>@endif
                        @if($visit->continuedFrom && \App\Models\FollowUp::query()->where('active_appointment_id',$visit->appointment_id)->where('status','BOOKED')->exists())<label class="mt-4 flex gap-2"><input type="checkbox" name="follow_up_goal_confirmed" value="1">Tujuan kontrol tertaut tercapai pada episode lanjutan ini. Tanpa konfirmasi, tugas kontrol tetap memerlukan review.</label>@endif
                        <dl class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                            <div><dt class="font-semibold text-slate-500">Pasien</dt><dd class="mt-1 font-bold text-slate-900">{{ $visit->patient->name }}</dd></div>
                            <div><dt class="font-semibold text-slate-500">Jumlah obat</dt><dd class="mt-1 font-bold text-slate-900" x-text="`${filledMedicines.length} obat`"></dd></div>
                        </dl>

                        <div class="mt-4 max-h-[45vh] space-y-3 overflow-y-auto rounded-xl border border-slate-200 p-4 text-sm focus-visible:ring-2 focus-visible:ring-clinic-500" tabindex="0" aria-label="Ringkasan sebelum finalisasi" data-rme-review-content>
                            <section><h3 class="font-bold text-clinic-950">SOAP · wajib S, A, P</h3><p data-rme-review-soap class="mt-1 text-slate-600"></p></section>
                            <section><h3 class="font-bold text-clinic-950">Tanda vital · opsional</h3><ul data-rme-review-vital class="mt-1 space-y-1 text-slate-600"></ul></section>
                            <section><h3 class="font-bold text-clinic-950">Diagnosis &amp; tindakan · opsional</h3><ul data-rme-review-clinical class="mt-1 space-y-1 text-slate-600"></ul></section>
                            <section><h3 class="font-bold text-clinic-950">Resep · opsional, aturan pakai wajib bila ada obat</h3><ul data-rme-review-prescription class="mt-1 space-y-1 text-slate-600"></ul></section>
                            <section><h3 class="font-bold text-clinic-950">Peninjauan alergi</h3><p data-rme-review-allergy class="mt-1 text-slate-600"></p></section>
                            <p data-rme-review-validation class="rounded-lg bg-amber-50 p-3 font-semibold text-amber-950"></p>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <x-clinic-button type="button" variant="secondary" x-on:click="$dispatch('close')">Kembali periksa</x-clinic-button>
                            <x-clinic-button variant="primary" formaction="{{ route($isInterrupted ? 'doctor.visits.finalize-interruption' : 'doctor.visits.complete', $visit) }}" x-bind:disabled="submitting || incompleteMedicineCount > 0">{{ $isInterrupted ? 'Finalisasi dokumentasi interupsi' : 'Finalisasi kunjungan' }}</x-clinic-button>
                        </div>
                    </div>
                </x-modal>
            @endunless
            <details class="clinic-disclosure"><summary id="prescription-workflow-heading">Panduan draft, finalisasi, dan koreksi</summary>
                @unless($isFinal)<p class="mb-4 text-xs leading-5 text-slate-500">Autosave untuk isian. Simpan draft untuk menyimpan manual dan foto.</p>@endunless


                <ol class="mt-5 grid gap-3 md:grid-cols-3">
                    <li class="rounded-2xl border p-4 {{ ! $isFinal ? 'border-[#E7C199] bg-[#FFF9F2]' : 'border-slate-200 bg-slate-50' }}" @if(! $isFinal) aria-current="step" @endif>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">1 · Draft</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Masih dapat disimpan dan dilengkapi tanpa membuat versi koreksi.</p>
                    </li>
                    <li class="rounded-2xl border p-4 {{ $isFinal && ! $hasModifications ? 'border-clinic-200 bg-clinic-50' : 'border-slate-200 bg-slate-50' }}" @if($isFinal && ! $hasModifications) aria-current="step" @endif>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">2 · Finalized</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Menjadi catatan resmi setelah konfirmasi finalisasi kunjungan.</p>
                    </li>
                    <li class="rounded-2xl border p-4 {{ $hasModifications ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50' }}" @if($hasModifications) aria-current="step" @endif>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">3 · Modification</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700">Perubahan setelah finalisasi wajib beralasan; versi sebelum dan sesudah tetap tersimpan.</p>
                    </li>
                </ol>
            </details>

    <details class="clinic-disclosure mb-5"><summary>Resep, rencana kontrol, dan validasi layanan</summary><p class="mt-4 text-sm leading-6 text-slate-500">Gunakan tindakan berikut sesuai tahap pemeriksaan. Penerbitan resep memerlukan dokumentasi final.</p><div class="mt-4 flex flex-wrap gap-3"><x-clinic-button variant="secondary" href="{{ route('clinical-documents.for-visit',$visit) }}">Kelola resep</x-clinic-button><x-clinic-button variant="secondary" href="{{ route('follow-ups.create',$visit) }}">Buat rencana kontrol</x-clinic-button><x-clinic-button variant="secondary" href="{{ route('care-continuity.show',$visit) }}">Koordinasi kasus</x-clinic-button>@if(in_array($visit->status,[\App\VisitStatus::Completed,\App\VisitStatus::Interrupted],true) && $visit->medicalRecord?->status===\App\MedicalRecordStatus::Final)<x-clinic-button variant="secondary" href="{{ route('patient-billing.clinical-form',$visit) }}">Validasi layanan diberikan</x-clinic-button>@else<p class="w-full text-xs leading-5 text-slate-500">Validasi biaya layanan tersedia setelah dokumentasi pelayanan disahkan.</p>@endif</div></details>
        </form>
        <aside class="min-w-0 space-y-4" id="patient-context" aria-label="Konteks pasien">
            <x-rme.allergy-editor :visit="$visit" />
            <section class="clinic-card overflow-hidden" aria-labelledby="patient-history-heading">
                <div class="border-b border-slate-200 p-4"><h2 id="patient-history-heading" tabindex="-1" class="scroll-mt-48 font-extrabold text-clinic-950">Riwayat dengan dokter ini</h2><p class="mt-1 text-xs leading-5 text-slate-500">Ringkasan kunjungan sebelumnya. Resep lama bukan bukti obat masih aktif.</p></div>
                <div class="divide-y divide-slate-200">
                    @forelse($history as $item)
                        <details class="p-4">
                            @php($historyRecord = $item->medicalRecord)
                            <summary class="min-h-11 cursor-pointer text-sm font-bold text-clinic-950">
                                {{ $item->started_at->timezone($timezone)->format('d M Y') }}
                                <span class="mt-1 block text-xs font-normal text-slate-500">{{ $item->service_name }} &middot; {{ $item->status->label() }}</span>
                                <span class="mt-2 inline-block rounded-md border border-slate-200 px-2 py-1 text-xs" data-history-record-status="{{ $historyRecord?->status->value ?? 'NONE' }}">{{ $historyRecord?->status === \App\MedicalRecordStatus::Final ? 'RME Final' : ($historyRecord ? 'RME Draft - belum final' : 'RME belum tersedia') }}</span>
                            </summary>
                            <dl class="mt-3 space-y-3 text-xs leading-5">
                                <div><dt class="font-bold text-slate-800">Assessment</dt><dd class="mt-1 whitespace-pre-line text-slate-600">{{ $historyRecord?->assessment ?: 'Belum dicatat.' }}</dd></div>
                                <div><dt class="font-bold text-slate-800">Diagnosis tercatat</dt><dd class="mt-1 text-slate-600">@forelse($historyRecord?->diagnoses ?? [] as $diagnosis)<p>{{ $diagnosis->name }}@if($diagnosis->code) ({{ $diagnosis->code }})@endif @if($diagnosis->is_primary) &middot; utama @endif</p>@empty Belum dicatat. @endforelse</dd></div>
                                <div><dt class="font-bold text-slate-800">Tindakan tercatat</dt><dd class="mt-1 text-slate-600">@forelse($historyRecord?->treatments ?? [] as $treatment)<p>{{ $treatment->name }} &middot; {{ $treatment->quantity }}@if($treatment->description)<span class="block whitespace-pre-line">{{ $treatment->description }}</span>@endif</p>@empty Belum dicatat. @endforelse</dd></div>
                                <div><dt class="font-bold text-slate-800">Resep kunjungan</dt><dd class="mt-1 space-y-2 text-slate-600">@forelse($historyRecord?->prescription?->items ?? [] as $medicine)<p>{{ $medicine->medicine_name }}<span class="block">{{ $medicine->dosage }} &middot; {{ $medicine->frequency }} &middot; {{ $medicine->quantity }} {{ $medicine->unit }}</span>@if($medicine->instructions)<span class="block whitespace-pre-line">{{ $medicine->instructions }}</span>@endif</p>@empty Tidak ada obat tercatat. @endforelse</dd></div>
                                <div><dt class="font-bold text-slate-800">Rencana</dt><dd class="mt-1 whitespace-pre-line text-slate-600">{{ $historyRecord?->plan ?: 'Belum dicatat.' }}</dd></div>
                            </dl>
                            <a href="{{ route('doctor.visits.show', $item) }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-bold text-clinic-700 underline" wire:navigate>Buka RME kunjungan</a>
                        </details>
                    @empty
                        <p class="p-4 text-sm text-slate-500">Belum ada kunjungan sebelumnya.</p>
                    @endforelse
                </div>
            </section>
            @if($hasModifications)
                <section class="clinic-card min-w-0 overflow-hidden p-6 xl:col-span-1" aria-labelledby="modification-history-heading">
                    <h2 id="modification-history-heading" class="font-extrabold text-clinic-950">Version history / Modification</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Setiap Modification mempertahankan seluruh perubahan rekam medis.</p>
                    <div class="mt-5 space-y-5">
                        @foreach($modifications as $revision)
                            <article class="min-w-0 overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p class="truncate text-xs font-extrabold uppercase tracking-wider text-amber-800">Modification {{ $modifications->count() - $loop->index }}</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900 [overflow-wrap:anywhere]">{{ $revision->reason }}</p>
                                <p class="mt-1 truncate text-xs text-slate-600" title="{{ $revision->correctedBy->name }} · {{ $revision->created_at->timezone($timezone)->format('d M Y, H:i') }}">{{ $revision->correctedBy->name }} · {{ $revision->created_at->timezone($timezone)->format('d M Y, H:i') }}</p>
                                <details class="mt-4">
                                    <summary class="cursor-pointer text-sm font-bold text-clinic-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2">Lihat semua perubahan</summary>
                                    <div class="mt-3 grid gap-3">
                                        <x-medical-record-modification :before="$revision->before_data" :after="$revision->after_data" />
                                        @foreach(['before' => 'Sebelum', 'after' => 'Sesudah'] as $snapshot => $label)
                                            @if($revision->{$snapshot.'_data'}['record']['photos'] ?? [])
                                                <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4">
                                                    <h4 class="text-xs font-bold text-slate-500">Foto {{ strtolower($label) }} koreksi</h4>
                                                    <ul class="mt-3 space-y-2">
                                                        @foreach($revision->{$snapshot.'_data'}['record']['photos'] as $index => $photo)
                                                            <li><a data-revision-photo href="{{ route('doctor.visits.photos.show', ['visit' => $visit, 'photo' => $index, 'revision' => $revision->id, 'snapshot' => $snapshot]) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-clinic-700 underline [overflow-wrap:anywhere]">{{ $photo['name'] ?? 'Foto rekam medis' }}</a></li>
                                                        @endforeach
                                                    </ul>
                                                </section>
                                            @endif
                                        @endforeach
                                    </div>
                                </details>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
            <details class="clinic-disclosure">
                <summary>Jadwal &amp; informasi kunjungan</summary>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-xs font-bold text-slate-500">Kode booking</dt><dd class="mt-1 font-semibold">{{ $visit->appointment->booking_code }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-500">Tanggal janji</dt><dd class="mt-1 font-semibold">{{ $visit->appointment->appointment_date->format('d M Y') }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-500">Mulai pemeriksaan</dt><dd class="mt-1 font-semibold">{{ $visit->started_at->timezone($timezone)->format('d M Y, H:i') }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-500">Dokter</dt><dd class="mt-1 font-semibold">{{ $visit->doctor->user->name }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-500">Tarif layanan</dt><dd class="mt-1 font-semibold">Rp {{ number_format((float) $visit->service_price, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-500">Nilai layanan kunjungan</dt><dd class="mt-1 font-semibold">Rp {{ number_format((float) $visit->total_amount, 0, ',', '.') }}</dd></div>
                    @if($visit->completed_at)<div><dt class="text-xs font-bold text-slate-500">Selesai</dt><dd class="mt-1 font-semibold">{{ $visit->completed_at->timezone($timezone)->format('d M Y, H:i') }}</dd></div>@endif
                </dl>
            </details>
            @if($visit->status===\App\VisitStatus::InProgress)<details class="clinic-disclosure"><summary>Jeda pemeriksaan</summary><form class="mt-4 space-y-3" method="POST" action="{{ route('care-continuity.workflow',[$visit,'pause']) }}" data-care-form>@include('care-continuity.version')<label class="clinic-label">Mode jeda<select class="clinic-input" name="pause_mode"><option value="HOLD">Pertahankan slot</option><option value="RELEASE">Lepas slot sementara</option></select></label><label class="clinic-label">Estimasi kembali ({{ $timezone }})<input class="clinic-input" type="datetime-local" name="estimated_return_at" required></label><label class="clinic-label">Alasan klinis<textarea class="clinic-input" name="reason" required maxlength="2000"></textarea></label><button class="clinic-button-secondary" type="submit">Jeda pemeriksaan</button></form></details>@endif
        </aside>
    </div>
</x-app-layout>
