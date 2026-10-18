<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Pendaftaran</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Booking baru</h1></div></x-slot>
    <div class="mx-auto max-w-4xl"><div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Daftarkan kunjungan</h2><p class="mt-2 text-sm text-slate-500">Pilih pasien yang sudah terdaftar agar tidak membuat data duplikat.</p></div>@if($errors->any())<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('receptionist.appointments.store') }}" class="space-y-6" x-data="{ newPatient: {{ old('patient_id', request('patient_id')) ? 'false' : 'true' }}, submitting:false }" @submit="submitting=true">@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <section class="clinic-card p-6 sm:p-8"><div class="flex gap-3"><button type="button" @click="newPatient=false" :class="!newPatient ? 'bg-clinic-500 text-white' : 'bg-slate-100 text-slate-600'" class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold">Pasien terdaftar</button><button type="button" @click="newPatient=true" :class="newPatient ? 'bg-clinic-500 text-white' : 'bg-slate-100 text-slate-600'" class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold">Pasien baru</button></div>
                <div x-show="!newPatient" class="mt-6" x-data="patientSearch(@js($selectedPatient ? ['id' => $selectedPatient->id, 'label' => "{$selectedPatient->medical_record_number} — {$selectedPatient->name} — {$selectedPatient->phone}"] : null), @js(route('receptionist.patients.search')))"><label class="clinic-label" for="patient_search">Cari pasien</label><input type="hidden" name="patient_id" :value="selectedId" :disabled="newPatient"><div class="relative"><input class="clinic-input" id="patient_search" type="search" x-model="query" @input.debounce.250ms="search()" @focus="open = true" :disabled="newPatient" :required="!newPatient" autocomplete="off" placeholder="Nama, No. RM, atau telepon"><div x-cloak x-show="open" @click.outside="open = false" class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"><template x-for="patient in results" :key="patient.id"><button type="button" class="block w-full px-4 py-3 text-left text-sm text-slate-700 hover:bg-clinic-50" @click="select(patient)" x-text="patient.label"></button></template><p x-show="query.trim().length < 2" class="px-4 py-3 text-sm text-slate-500">Ketik minimal 2 karakter untuk mencari pasien.</p><p x-show="query.trim().length >= 2 && !results.length" class="px-4 py-3 text-sm text-slate-500">Pasien tidak ditemukan.</p></div></div></div>
                <div x-show="newPatient" class="mt-6 grid gap-5 md:grid-cols-2"><div class="md:col-span-2"><label class="clinic-label">Nama lengkap</label><input class="clinic-input" name="name" value="{{ old('name') }}" :disabled="!newPatient"></div><div><label class="clinic-label">NIK</label><input class="clinic-input" name="nik" value="{{ old('nik') }}" :disabled="!newPatient"></div><div><label class="clinic-label">Tanggal lahir</label><input class="clinic-input" name="birth_date" type="date" value="{{ old('birth_date') }}" :disabled="!newPatient"></div><div><label class="clinic-label">Jenis kelamin</label><select class="clinic-input" name="gender" :disabled="!newPatient"><option value="MALE">Laki-laki</option><option value="FEMALE">Perempuan</option></select></div><div><label class="clinic-label">Telepon</label><input class="clinic-input" name="phone" value="{{ old('phone') }}" :disabled="!newPatient"></div><div><label class="clinic-label">Email</label><input class="clinic-input" name="email" type="email" value="{{ old('email') }}" :disabled="!newPatient"></div><div><label class="clinic-label">Alamat</label><input class="clinic-input" name="address" value="{{ old('address') }}" :disabled="!newPatient"></div></div>
            </section>
            <section class="clinic-card p-6 sm:p-8"><h3 class="text-lg font-extrabold text-clinic-950">Jadwal & layanan</h3><div class="mt-6 grid gap-5 md:grid-cols-2"><x-receptionist.search-select name="doctor_id" label="Dokter" placeholder="Cari dokter" :options="$doctorOptions" :selected="old('doctor_id')" /><x-receptionist.search-select name="service_id" label="Layanan" placeholder="Cari layanan" :options="$serviceOptions" :selected="old('service_id')" /><x-receptionist.search-select name="doctor_schedule_id" label="Sesi praktik" placeholder="Cari sesi praktik" :options="$scheduleOptions" :selected="old('doctor_schedule_id')" /><div><label class="clinic-label">Tanggal</label><input class="clinic-input" name="appointment_date" type="date" value="{{ old('appointment_date') }}" min="{{ now()->toDateString() }}" max="{{ now()->addDays(30)->toDateString() }}" required></div><div class="md:col-span-2"><label class="clinic-label">Catatan</label><textarea class="clinic-input" name="notes" rows="3">{{ old('notes') }}</textarea></div></div></section>
            <div class="flex justify-end gap-3"><x-clinic-button href="{{ route('receptionist.appointments.index') }}" variant="secondary" wire:navigate>Batal</x-clinic-button><x-clinic-button type="submit" variant="primary" x-bind:disabled="submitting">Buat booking</x-clinic-button></div>
        </form>
    </div>
    <script>
        function patientSearch(selectedPatient, endpoint) {
            return {
                query: selectedPatient?.label ?? '', selectedId: selectedPatient?.id ?? '', results: [], open: false,
                async search() {
                    this.selectedId = '';
                    const query = this.query.trim();
                    this.open = true;

                    if (query.length < 2) { this.results = []; return; }

                    this.results = [];
                    const response = await fetch(`${endpoint}?search=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
                    if (response.ok && query === this.query.trim()) { this.results = await response.json(); }
                },
                select(patient) { this.selectedId = patient.id; this.query = patient.label; this.open = false; },
            };
        }

    </script>
</x-app-layout>
