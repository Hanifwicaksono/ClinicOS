<section x-data="patientDuplicateReview(@js(route('receptionist.patients.duplicates')))" class="rounded-2xl border border-amber-200 bg-amber-50 p-5" data-duplicate-review>
    <h3 class="font-bold text-amber-950">Periksa pasien terdaftar</h3>
    <p class="mt-2 text-sm leading-6 text-amber-900">Nomor telepon dapat dipakai bersama keluarga. Cocokkan identitas sebelum memakai data pasien lama atau membuat pasien baru.</p>
    <p x-show="busy" class="mt-3 text-sm" role="status">Memeriksa kandidat pasien…</p>
    <p x-ref="feedback" tabindex="-1" role="alert" class="mt-3 text-sm font-semibold text-red-700" x-text="message"></p>
    <div class="mt-3 space-y-2" x-show="candidates.length" x-cloak>
        <p role="status" class="text-sm text-amber-950" x-text="'Menampilkan ' + candidates.length + ' kandidat. ' + (nextCursor ? 'Masih ada hasil lain.' : 'Seluruh kandidat saat pemeriksaan ini telah ditampilkan.')"></p>
        <template x-for="candidate in candidates" :key="candidate.id">
            <a :href="candidate.url" target="_blank" rel="noopener" class="block min-h-11 break-words rounded-xl border border-amber-200 bg-white p-3 text-sm font-semibold text-clinic-800 underline focus-visible:ring-2 focus-visible:ring-clinic-500"><span x-text="candidate.label"></span><span class="block text-xs font-normal">Buka profil di tab baru</span></a>
        </template>
        <button type="button" x-show="nextCursor" @click="check(nextCursor)" :disabled="busy" class="min-h-11 text-sm font-bold text-clinic-800 underline focus-visible:ring-2 focus-visible:ring-clinic-500">Lihat kandidat lain</button>
        <label class="flex min-h-11 items-start gap-3 py-3 text-sm font-semibold text-amber-950"><input type="checkbox" name="duplicate_reviewed" value="1" x-model="reviewed" class="mt-1 rounded text-clinic-600 focus:ring-clinic-500">Saya telah meninjau kandidat dan memastikan ini pasien yang berbeda.</label>
    </div>
    <input type="hidden" name="duplicate_review_token" :value="token">
    <p x-show="!busy && !message && !candidates.length" x-cloak class="mt-3 text-sm text-amber-900">Belum ditemukan kandidat dengan data yang diisi.</p>
    <button type="button" @click="check()" :disabled="busy" class="mt-2 min-h-11 text-sm font-bold text-clinic-800 underline focus-visible:ring-2 focus-visible:ring-clinic-500">Periksa kembali</button>
    <x-input-error :messages="$errors->get('duplicate_reviewed')" class="mt-2" />
</section>
