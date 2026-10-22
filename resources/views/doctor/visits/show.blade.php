@php
    $record = $visit->medicalRecord;
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
        default => 'draft',
    };
@endphp
<x-app-layout>
    <x-slot name="header"><div class="flex w-full items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">{{ $visit->appointment->booking_code }} · {{ $visit->queue->display_number }}</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Pemeriksaan pasien</h1></div><span class="{{ $isFinal ? 'clinic-status-active' : 'rounded-full bg-[#F7F1EA] px-3 py-1.5 text-xs font-bold text-[#D89B68]' }}">{{ $isFinal ? ($hasModifications ? 'Finalized · Modification' : 'Finalized') : 'Draft' }}</span></div></x-slot>

    @if($errors->any())<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700"><p class="font-bold">Periksa kembali rekam medis:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <form method="POST" enctype="multipart/form-data" action="{{ $isFinal ? route('doctor.visits.correct', $visit) : route('doctor.visits.save', $visit) }}" class="space-y-6" x-data="{
            medicines: @js($prescriptionRows),
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
            },
        }" data-rme-form @input="$dispatch('rme-autosave-state', { state: 'draft' })" @paste.window="const images = [...$event.clipboardData.files].filter(file => file.type.startsWith('image/')); if (images.length) { $event.preventDefault(); addPhotos(images); }" @submit="submitting = true; $dispatch('rme-autosave-state', { state: 'saving' })">
            @csrf @method('PUT')
            <template x-for="index in removedPhotos" :key="index"><input type="hidden" name="removed_photos[]" :value="index"></template>
            <section class="clinic-card p-6 sm:p-8"><div class="flex flex-wrap items-start justify-between gap-5"><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Pasien</p><h2 class="mt-2 text-2xl font-extrabold text-clinic-950">{{ $visit->patient->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $visit->patient->medical_record_number }} · {{ $visit->patient->birth_date->age }} tahun · {{ $visit->patient->gender === 'MALE' ? 'Laki-laki' : 'Perempuan' }}</p></div><div class="text-left sm:text-right"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Layanan</p><p class="mt-2 font-bold text-slate-900">{{ $visit->service_name }}</p><p class="mt-1 text-sm text-clinic-600">Rp {{ number_format((float) $visit->service_price, 0, ',', '.') }}</p></div></div></section>

            <x-critical-allergy-banner :allergies="$criticalAllergies" />

            <section class="clinic-card p-6 sm:p-8" aria-labelledby="prescription-workflow-heading">
                <h2 id="prescription-workflow-heading" class="text-lg font-extrabold text-clinic-950">Alur resep dan rekam medis</h2>
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
            </section>

            @if($isFinal)<section class="rounded-2xl border border-amber-300 bg-amber-50 p-5"><p class="font-bold text-amber-950">Mode Modification rekam medis finalized</p><p class="mt-1 text-sm leading-6 text-amber-900">Perubahan tidak menimpa histori. Sistem menyimpan snapshot sebelum dan sesudah Modification beserta pelaku, waktu, dan alasan.</p><label class="clinic-label mt-4" for="correction_reason">Alasan Modification</label><textarea class="clinic-input" id="correction_reason" name="correction_reason" rows="2" minlength="10" required aria-describedby="correction_reason_help">{{ old('correction_reason') }}</textarea><p id="correction_reason_help" class="mt-2 text-xs text-amber-900">Minimal 10 karakter dan harus menjelaskan alasan klinis atau administratif yang spesifik.</p></section>@endif

            <x-rme.section heading-id="rme-general-heading" title="RME General" description="Lengkapi SOAP dan data klinis. Tanda bintang wajib diisi sebelum finalisasi.">
                <x-slot:actions>
                    <x-rme.autosave-status :state="$rmeFormState" :message="$errors->first()" />
                </x-slot:actions>

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-rme.textarea-field
                            name="chief_complaint"
                            label="Keluhan utama"
                            :value="old('chief_complaint', $record->chief_complaint)"
                            :messages="$errors->get('chief_complaint')"
                            rows="3"
                            description="Ringkasan keluhan yang membawa pasien datang."
                        />
                    </div>
                    <x-rme.textarea-field
                        name="subjective"
                        label="S · Subjektif"
                        :value="old('subjective', $record->subjective ?: $record->chief_complaint)"
                        :messages="$errors->get('subjective')"
                        rows="5"
                        required
                        description="Keluhan, riwayat, dan informasi yang disampaikan pasien."
                    />
                    <x-rme.textarea-field
                        name="objective"
                        label="O · Objektif"
                        :value="old('objective', $record->objective)"
                        :messages="$errors->get('objective')"
                        rows="5"
                        description="Temuan pemeriksaan dan data objektif klinis."
                    />
                    <x-rme.textarea-field
                        name="assessment"
                        label="A · Assessment"
                        :value="old('assessment', $record->assessment ?: $record->diagnoses->pluck('name')->join(', '))"
                        :messages="$errors->get('assessment')"
                        rows="4"
                        required
                        description="Penilaian atau diagnosis kerja dokter."
                    />
                    <x-rme.textarea-field
                        name="plan"
                        label="P · Rencana"
                        :value="old('plan', $record->plan ?: $record->treatments->pluck('name')->join(', '))"
                        :messages="$errors->get('plan')"
                        rows="4"
                        required
                        description="Terapi, edukasi, pemeriksaan lanjutan, atau rencana kontrol."
                    />
                    <div class="md:col-span-2">
                        <x-rme.textarea-field
                            name="physical_examination"
                            label="Pemeriksaan fisik"
                            :value="old('physical_examination', $record->physical_examination)"
                            :messages="$errors->get('physical_examination')"
                            rows="4"
                        />
                    </div>
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
                                    <button type="button" class="absolute right-2 top-2 flex size-7 items-center justify-center rounded-full bg-slate-950/75 text-lg font-bold leading-none text-white hover:bg-red-600" @click="removedPhotos.push({{ $index }})" aria-label="Hapus {{ $photo['name'] ?? 'foto rekam medis' }}">×</button>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs font-semibold text-red-600 md:col-span-2" x-show="removedPhotos.length" x-cloak>Foto bertanda hapus akan dihapus saat rekam medis disimpan.</p>
                    @endif
                </div>
            </x-rme.section>

            <x-rme.section heading-id="vital-signs-heading" title="Tanda vital" description="Semua field opsional dan dapat dihubungkan ke payload API yang sama.">
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

            <x-dental-chart
                :teeth="$dentalChart['teeth']"
                :conditions="$dentalChart['conditions']"
                :selected-tooth="$dentalChart['selected_tooth']"
                :read-only="$dentalChart['read_only']"
            />

            <section class="clinic-card p-6 sm:p-8" aria-labelledby="prescription-heading">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 id="prescription-heading" class="text-lg font-extrabold text-clinic-950">Resep</h2>
                        <p class="mt-1 text-sm text-slate-500">Tambahkan obat, lengkapi aturan pakai, lalu periksa preview sebelum finalisasi.</p>
                    </div>
                    <x-clinic-button type="button" @click="medicines.push({medicine_name:'',dosage:'',frequency:'',quantity:'',unit:'',instructions:''})" variant="secondary" class="px-4 py-2">+ Tambah obat</x-clinic-button>
                </div>

                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950" role="note">
                    <p class="font-bold">Peringatan keselamatan resep</p>
                    <p>Periksa alergi, duplikasi terapi, interaksi, dosis, dan kondisi pasien sebelum finalisasi. Peringatan ini tidak menggantikan penilaian klinis dokter.</p>
                </div>

                <div class="mt-6 space-y-4">
                    <template x-for="(item, index) in medicines" :key="index">
                        <fieldset class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <legend class="px-2 text-sm font-bold text-slate-700" x-text="`Obat ${index + 1}`"></legend>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="clinic-label" :for="`medicine_name_${index}`">Nama obat</label>
                                    <input class="clinic-input" :id="`medicine_name_${index}`" :name="`prescription_items[${index}][medicine_name]`" x-model="item.medicine_name" autocomplete="off" placeholder="Contoh: Paracetamol 500 mg">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_dosage_${index}`">Dosis</label>
                                    <input class="clinic-input" :id="`medicine_dosage_${index}`" :name="`prescription_items[${index}][dosage]`" x-model="item.dosage" placeholder="Contoh: 1 tablet">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_frequency_${index}`">Frekuensi</label>
                                    <input class="clinic-input" :id="`medicine_frequency_${index}`" :name="`prescription_items[${index}][frequency]`" x-model="item.frequency" placeholder="Contoh: 3 kali sehari">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_quantity_${index}`">Jumlah</label>
                                    <input class="clinic-input" :id="`medicine_quantity_${index}`" :name="`prescription_items[${index}][quantity]`" x-model="item.quantity" type="number" min="1" max="9999" placeholder="10">
                                </div>
                                <div>
                                    <label class="clinic-label" :for="`medicine_unit_${index}`">Satuan</label>
                                    <input class="clinic-input" :id="`medicine_unit_${index}`" :name="`prescription_items[${index}][unit]`" x-model="item.unit" placeholder="Contoh: tablet">
                                </div>
                                <div class="flex items-end">
                                    <button type="button" @click="medicines.splice(index,1)" class="inline-flex min-h-11 items-center font-bold text-red-600 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">Hapus obat</button>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="clinic-label" :for="`medicine_instructions_${index}`">Instruksi penggunaan <span class="font-normal text-slate-400">(opsional)</span></label>
                                    <textarea class="clinic-input" :id="`medicine_instructions_${index}`" :name="`prescription_items[${index}][instructions]`" x-model="item.instructions" rows="2" placeholder="Contoh: diminum setelah makan"></textarea>
                                </div>
                            </div>
                        </fieldset>
                    </template>

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

            <x-rme.section heading-id="doctor-notes-heading" title="Catatan tambahan" description="Catatan internal dokter yang tetap menjadi bagian rekam medis.">
                <x-rme.textarea-field
                    name="doctor_notes"
                    label="Catatan dokter"
                    :value="old('doctor_notes', $record->doctor_notes)"
                    :messages="$errors->get('doctor_notes')"
                    rows="4"
                />
            </x-rme.section>

            <div class="flex flex-col justify-end gap-3 sm:flex-row">
                @if($isFinal)
                    <x-clinic-button variant="primary" class="disabled:opacity-60" x-bind:disabled="submitting">Simpan Modification</x-clinic-button>
                @else
                    <x-clinic-button variant="secondary" class="disabled:opacity-60" x-bind:disabled="submitting" formnovalidate>Simpan draft</x-clinic-button>
                    <x-clinic-button type="button" variant="primary" class="disabled:opacity-60" x-bind:disabled="submitting || incompleteMedicineCount > 0" x-on:click="$dispatch('open-modal', 'confirm-finalize-visit')">Tinjau &amp; finalisasi</x-clinic-button>
                @endif
            </div>

            @unless($isFinal)
                <x-modal name="confirm-finalize-visit" maxWidth="lg" label="Konfirmasi finalisasi kunjungan" focusable>
                    <div class="p-6 sm:p-8">
                        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-clinic-600">Konfirmasi finalisasi</p>
                        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Jadikan rekam medis ini finalized?</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Setelah finalisasi, draft menjadi catatan resmi. Perubahan berikutnya hanya dapat disimpan sebagai Modification beralasan dan histori sebelum/sesudah akan tetap terlihat.</p>

                        <dl class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                            <div><dt class="font-semibold text-slate-500">Pasien</dt><dd class="mt-1 font-bold text-slate-900">{{ $visit->patient->name }}</dd></div>
                            <div><dt class="font-semibold text-slate-500">Jumlah obat</dt><dd class="mt-1 font-bold text-slate-900" x-text="`${filledMedicines.length} obat`"></dd></div>
                        </dl>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <x-clinic-button type="button" variant="secondary" x-on:click="$dispatch('close')">Kembali periksa</x-clinic-button>
                            <x-clinic-button variant="primary" formaction="{{ route('doctor.visits.complete', $visit) }}" x-bind:disabled="submitting || incompleteMedicineCount > 0">Finalisasi kunjungan</x-clinic-button>
                        </div>
                    </div>
                </x-modal>
            @endunless
        </form>

        <aside class="space-y-6"><section class="clinic-card p-6"><h2 class="font-extrabold text-clinic-950">Kunjungan</h2><dl class="mt-5 space-y-4 text-sm"><div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Mulai</dt><dd class="mt-1 font-semibold">{{ $visit->started_at->format('d M Y, H:i') }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Dokter</dt><dd class="mt-1 font-semibold">{{ $visit->doctor->user->name }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Nilai layanan</dt><dd class="mt-1 font-semibold">Rp {{ number_format((float) $visit->total_amount, 0, ',', '.') }}</dd></div>@if($visit->completed_at)<div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Selesai</dt><dd class="mt-1 font-semibold">{{ $visit->completed_at->format('d M Y, H:i') }}</dd></div>@endif</dl></section>
            <section class="clinic-card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="font-extrabold text-clinic-950">Riwayat dengan dokter ini</h2></div><div class="divide-y divide-slate-100">@forelse($history as $item)<a href="{{ route('doctor.visits.show', $item) }}" class="block p-5 hover:bg-slate-50" wire:navigate><p class="text-sm font-bold text-slate-900">{{ $item->started_at->format('d M Y') }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->service_name }} · {{ $item->status->value }}</p><p class="mt-2 text-xs font-semibold text-clinic-700">{{ $item->medicalRecord?->assessment ?: $item->medicalRecord?->diagnoses->pluck('name')->join(', ') ?: 'Belum ada diagnosa' }}</p></a>@empty<div class="p-6 text-sm text-slate-500">Belum ada kunjungan sebelumnya.</div>@endforelse</div></section>
            @if($hasModifications)
                <section class="clinic-card min-w-0 overflow-hidden p-6 xl:col-span-1" aria-labelledby="modification-history-heading">
                    <h2 id="modification-history-heading" class="font-extrabold text-clinic-950">Version history / Modification</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Setiap Modification mempertahankan seluruh perubahan rekam medis.</p>
                    <div class="mt-5 space-y-5">
                        @foreach($modifications as $revision)
                            <article class="min-w-0 overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p class="truncate text-xs font-extrabold uppercase tracking-wider text-amber-800">Modification {{ $modifications->count() - $loop->index }}</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900 [overflow-wrap:anywhere]">{{ $revision->reason }}</p>
                                <p class="mt-1 truncate text-xs text-slate-600" title="{{ $revision->correctedBy->name }} · {{ $revision->created_at->format('d M Y, H:i') }}">{{ $revision->correctedBy->name }} · {{ $revision->created_at->format('d M Y, H:i') }}</p>
                                <details class="mt-4">
                                    <summary class="cursor-pointer text-sm font-bold text-clinic-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2">Lihat semua perubahan</summary>
                                    <div class="mt-3 grid gap-3">
                                        <x-medical-record-modification :before="$revision->before_data" :after="$revision->after_data" />
                                    </div>
                                </details>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
