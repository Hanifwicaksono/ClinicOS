@php
    $record = $visit->medicalRecord;
    $isFinal = $record->status === \App\MedicalRecordStatus::Final;
    $vital = $record->vitalSign;
    $prescriptionRows = old('prescription_items', $record->prescription?->items->map(fn ($item) => [
        'medicine_name' => $item->medicine_name, 'dosage' => $item->dosage, 'frequency' => $item->frequency,
        'quantity' => $item->quantity, 'unit' => $item->unit ?? '', 'instructions' => $item->instructions ?? '',
    ])->all() ?? []);
    $prescriptionRows = $prescriptionRows ?: [['medicine_name' => '', 'dosage' => '', 'frequency' => '', 'quantity' => '', 'unit' => '', 'instructions' => '']];
@endphp
<x-app-layout>
    <x-slot name="header"><div class="flex w-full items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">{{ $visit->appointment->booking_code }} · {{ $visit->queue->display_number }}</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Pemeriksaan pasien</h1></div><span class="{{ $isFinal ? 'clinic-status-active' : 'rounded-full bg-[#F7F1EA] px-3 py-1.5 text-xs font-bold text-[#D89B68]' }}">{{ $isFinal ? 'Final' : 'Draft' }}</span></div></x-slot>

    @if($errors->any())<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700"><p class="font-bold">Periksa kembali rekam medis:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <form method="POST" enctype="multipart/form-data" action="{{ $isFinal ? route('doctor.visits.correct', $visit) : route('doctor.visits.save', $visit) }}" class="space-y-6" x-data="{
            medicines: @js($prescriptionRows),
            selectedPhotos: [],
            removedPhotos: @js(array_map('intval', old('removed_photos', []))),
            submitting: false,
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
        }" @paste.window="const images = [...$event.clipboardData.files].filter(file => file.type.startsWith('image/')); if (images.length) { $event.preventDefault(); addPhotos(images); }" @submit="submitting = true">
            @csrf @method('PUT')
            <template x-for="index in removedPhotos" :key="index"><input type="hidden" name="removed_photos[]" :value="index"></template>
            <section class="clinic-card p-6 sm:p-8"><div class="flex flex-wrap items-start justify-between gap-5"><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Pasien</p><h2 class="mt-2 text-2xl font-extrabold text-clinic-950">{{ $visit->patient->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $visit->patient->medical_record_number }} · {{ $visit->patient->birth_date->age }} tahun · {{ $visit->patient->gender === 'MALE' ? 'Laki-laki' : 'Perempuan' }}</p></div><div class="text-left sm:text-right"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Layanan</p><p class="mt-2 font-bold text-slate-900">{{ $visit->service_name }}</p><p class="mt-1 text-sm text-clinic-600">Rp {{ number_format((float) $visit->service_price, 0, ',', '.') }}</p></div></div></section>

            @if($isFinal)<section class="rounded-2xl border border-[#DDE5E1] bg-[#F7F8F5] p-5"><p class="font-bold text-[#253331]">Mode koreksi rekam medis final</p><p class="mt-1 text-sm leading-6 text-[#3D5F5C]">Perubahan akan menyimpan snapshot sebelum dan sesudah koreksi. Isi alasan yang spesifik.</p><label class="clinic-label mt-4" for="correction_reason">Alasan koreksi</label><textarea class="clinic-input" id="correction_reason" name="correction_reason" rows="2" required>{{ old('correction_reason') }}</textarea></section>@endif

            <section class="clinic-card p-6 sm:p-8">
                <h2 class="text-lg font-extrabold text-clinic-950">Rekam medis</h2>
                <div class="mt-6 grid gap-5">
                    <div><label class="clinic-label" for="subjective">Anamnesa</label><textarea class="clinic-input" id="subjective" name="subjective" rows="5" required>{{ old('subjective', $record->subjective ?: $record->chief_complaint) }}</textarea></div>
                    <div><label class="clinic-label" for="assessment">Diagnosa</label><textarea class="clinic-input" id="assessment" name="assessment" rows="4" required>{{ old('assessment', $record->assessment ?: $record->diagnoses->pluck('name')->join(', ')) }}</textarea></div>
                    <div><label class="clinic-label" for="plan">Treatment</label><textarea class="clinic-input" id="plan" name="plan" rows="4" required>{{ old('plan', $record->plan ?: $record->treatments->pluck('name')->join(', ')) }}</textarea></div>
                    <div>
                        <label class="clinic-label" for="photos">Foto pendukung <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input class="clinic-input file:mr-4 file:rounded-lg file:border-0 file:bg-clinic-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-clinic-700" id="photos" x-ref="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple @change="addPhotos($event.target.files)">
                        <p class="mt-2 text-xs text-slate-500">Pilih hingga 10 foto (maks. 5 MB per foto), atau paste gambar langsung di halaman ini.</p>
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
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
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
                        <p class="text-xs font-semibold text-red-600" x-show="removedPhotos.length" x-cloak>Foto bertanda hapus akan dihapus saat rekam medis disimpan.</p>
                    @endif
                </div>
            </section>

            <section class="clinic-card p-6 sm:p-8">
                <div><h2 class="text-lg font-extrabold text-clinic-950">Tanda vital</h2><p class="mt-1 text-sm text-slate-500">Semua field opsional.</p></div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([['weight_kg','Berat badan','kg','0.1'],['height_cm','Tinggi badan','cm','0.1']] as [$name,$label,$unit,$step])
                        <div><label class="clinic-label" for="vital_{{ $name }}">{{ $label }}</label><div class="relative"><input class="clinic-input pr-16" id="vital_{{ $name }}" name="vital_signs[{{ $name }}]" type="number" step="{{ $step }}" value="{{ old('vital_signs.'.$name, $vital?->{$name}) }}"><span class="absolute right-3 top-3 text-xs text-slate-400">{{ $unit }}</span></div></div>
                    @endforeach
                    <div><label class="clinic-label" for="vital_systolic">Tekanan darah</label><div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2"><input class="clinic-input" id="vital_systolic" name="vital_signs[systolic]" type="number" step="1" value="{{ old('vital_signs.systolic', $vital?->systolic) }}" aria-label="Tekanan darah sistolik" placeholder="120"><span class="text-slate-400">/</span><input class="clinic-input" name="vital_signs[diastolic]" type="number" step="1" value="{{ old('vital_signs.diastolic', $vital?->diastolic) }}" aria-label="Tekanan darah diastolik" placeholder="80"></div><p class="mt-1 text-xs text-slate-400">mmHg</p></div>
                    <div><label class="clinic-label" for="vital_temperature_c">Suhu</label><div class="relative"><input class="clinic-input pr-16" id="vital_temperature_c" name="vital_signs[temperature_c]" type="number" step="0.1" value="{{ old('vital_signs.temperature_c', $vital?->temperature_c) }}"><span class="absolute right-3 top-3 text-xs text-slate-400">°C</span></div></div>
                </div>
            </section>

            <section class="clinic-card p-6 sm:p-8"><div class="flex items-center justify-between"><h2 class="text-lg font-extrabold text-clinic-950">Resep</h2><x-clinic-button type="button" @click="medicines.push({medicine_name:'',dosage:'',frequency:'',quantity:'',unit:'',instructions:''})" variant="secondary" class="px-4 py-2">+ Obat</x-clinic-button></div><div class="mt-6 space-y-4"><template x-for="(item, index) in medicines" :key="index"><div class="grid gap-3 rounded-2xl bg-slate-50 p-4 md:grid-cols-3"><input class="clinic-input" :name="`prescription_items[${index}][medicine_name]`" x-model="item.medicine_name" placeholder="Nama obat"><input class="clinic-input" :name="`prescription_items[${index}][dosage]`" x-model="item.dosage" placeholder="Dosis"><input class="clinic-input" :name="`prescription_items[${index}][frequency]`" x-model="item.frequency" placeholder="Frekuensi"><input class="clinic-input" :name="`prescription_items[${index}][quantity]`" x-model="item.quantity" type="number" min="1" placeholder="Jumlah"><input class="clinic-input" :name="`prescription_items[${index}][unit]`" x-model="item.unit" placeholder="Satuan"><button type="button" @click="medicines.splice(index,1)" class="px-3 text-left font-bold text-red-500 md:text-center">Hapus</button><textarea class="clinic-input md:col-span-3" :name="`prescription_items[${index}][instructions]`" x-model="item.instructions" rows="2" placeholder="Instruksi penggunaan"></textarea></div></template><div><label class="clinic-label" for="prescription_notes">Catatan resep</label><textarea class="clinic-input" id="prescription_notes" name="prescription_notes" rows="2">{{ old('prescription_notes', $record->prescription?->notes) }}</textarea></div></div></section>

            <section class="clinic-card p-6 sm:p-8"><label class="clinic-label" for="doctor_notes">Catatan dokter</label><textarea class="clinic-input" id="doctor_notes" name="doctor_notes" rows="4">{{ old('doctor_notes', $record->doctor_notes) }}</textarea></section>

            <div class="flex flex-col justify-end gap-3 sm:flex-row">@if($isFinal)<x-clinic-button variant="primary" class="disabled:opacity-60" x-bind:disabled="submitting">Simpan koreksi</x-clinic-button>@else<x-clinic-button variant="secondary" class="disabled:opacity-60" x-bind:disabled="submitting" formnovalidate>Simpan draft</x-clinic-button><x-clinic-button variant="primary" class="disabled:opacity-60" x-bind:disabled="submitting" formaction="{{ route('doctor.visits.complete', $visit) }}" onclick="return confirm('Finalisasi kunjungan? Rekam medis hanya dapat diubah melalui koreksi beralasan.')">Selesaikan kunjungan</x-clinic-button>@endif</div>
        </form>

        <aside class="space-y-6"><section class="clinic-card p-6"><h2 class="font-extrabold text-clinic-950">Kunjungan</h2><dl class="mt-5 space-y-4 text-sm"><div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Mulai</dt><dd class="mt-1 font-semibold">{{ $visit->started_at->format('d M Y, H:i') }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Dokter</dt><dd class="mt-1 font-semibold">{{ $visit->doctor->user->name }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Nilai layanan</dt><dd class="mt-1 font-semibold">Rp {{ number_format((float) $visit->total_amount, 0, ',', '.') }}</dd></div>@if($visit->completed_at)<div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Selesai</dt><dd class="mt-1 font-semibold">{{ $visit->completed_at->format('d M Y, H:i') }}</dd></div>@endif</dl></section>
            <section class="clinic-card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="font-extrabold text-clinic-950">Riwayat dengan dokter ini</h2></div><div class="divide-y divide-slate-100">@forelse($history as $item)<a href="{{ route('doctor.visits.show', $item) }}" class="block p-5 hover:bg-slate-50" wire:navigate><p class="text-sm font-bold text-slate-900">{{ $item->started_at->format('d M Y') }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->service_name }} · {{ $item->status->value }}</p><p class="mt-2 text-xs font-semibold text-clinic-700">{{ $item->medicalRecord?->assessment ?: $item->medicalRecord?->diagnoses->pluck('name')->join(', ') ?: 'Belum ada diagnosa' }}</p></a>@empty<div class="p-6 text-sm text-slate-500">Belum ada kunjungan sebelumnya.</div>@endforelse</div></section>
            @if($record->revisions->isNotEmpty())<section class="clinic-card p-6"><h2 class="font-extrabold text-clinic-950">Riwayat koreksi</h2><div class="mt-4 space-y-4">@foreach($record->revisions as $revision)<div class="border-l-2 border-amber-300 pl-3"><p class="text-sm font-semibold text-slate-800">{{ $revision->reason }}</p><p class="mt-1 text-xs text-slate-500">{{ $revision->correctedBy->name }} · {{ $revision->created_at->format('d M Y, H:i') }}</p></div>@endforeach</div></section>@endif
        </aside>
    </div>
</x-app-layout>
