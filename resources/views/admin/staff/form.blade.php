@php
    $editing = isset($staff);
    $selectedServices = collect(old('service_ids', $editing ? $staff->doctor?->services->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Manajemen tim</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">{{ $editing ? 'Edit staf' : 'Tambah staf' }}</h1></div></x-slot>

    <div class="mx-auto max-w-4xl">
        <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">{{ $editing ? $staff->name : 'Akun staf baru' }}</h2><p class="mt-2 text-sm text-slate-500">{{ $editing ? 'Perbarui profil dan status akses staf.' : 'Staf akan menerima email verifikasi dan tautan untuk membuat kata sandi.' }}</p></div>

        <form method="POST" action="{{ $editing ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" class="space-y-6" x-data="{ role: '{{ old('role', $editing ? $staff->roles->first()?->name : 'Doctor') }}', submitting: false }" @submit="submitting = true">
            @csrf
            @if ($editing) @method('PUT') @endif

            <section class="clinic-card p-6 sm:p-8">
                <div class="grid gap-6 md:grid-cols-2">
                    <div><label class="clinic-label" for="name">Nama lengkap</label><input class="clinic-input" id="name" name="name" value="{{ old('name', $staff->name ?? '') }}" required><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="email">Email</label><input class="clinic-input" id="email" type="email" name="email" value="{{ old('email', $staff->email ?? '') }}" required><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="phone">Nomor telepon</label><input class="clinic-input" id="phone" name="phone" value="{{ old('phone', $staff->phone ?? '') }}"><x-input-error :messages="$errors->get('phone')" class="mt-2" /></div>
                    @if (! $editing)
                        <div><label class="clinic-label" for="role">Peran</label><select class="clinic-input" id="role" name="role" x-model="role"><option value="Doctor">Dokter</option><option value="Receptionist">Resepsionis</option></select><x-input-error :messages="$errors->get('role')" class="mt-2" /></div>
                    @else
                        <div><label class="clinic-label">Peran</label><div class="clinic-input bg-slate-50 text-slate-500">{{ $staff->roles->first()?->name }}</div></div>
                    @endif
                </div>
            </section>

            <section x-show="role === 'Doctor'" class="clinic-card p-6 sm:p-8">
                <div class="border-b border-slate-100 pb-5"><h3 class="font-bold text-clinic-950">Profil dokter</h3><p class="mt-1 text-sm text-slate-500">Informasi profesi yang akan digunakan dalam jadwal dan layanan.</p></div>
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <div><label class="clinic-label" for="specialization">Spesialisasi</label><input class="clinic-input" id="specialization" name="specialization" value="{{ old('specialization', $editing ? $staff->doctor?->specialization : '') }}" :required="role === 'Doctor'"><x-input-error :messages="$errors->get('specialization')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="license_number">Nomor SIP/STR</label><input class="clinic-input" id="license_number" name="license_number" value="{{ old('license_number', $editing ? $staff->doctor?->license_number : '') }}"><x-input-error :messages="$errors->get('license_number')" class="mt-2" /></div>
                    <div class="md:col-span-2"><label class="clinic-label" for="bio">Bio singkat</label><textarea class="clinic-input" id="bio" name="bio" rows="3">{{ old('bio', $editing ? $staff->doctor?->bio : '') }}</textarea><x-input-error :messages="$errors->get('bio')" class="mt-2" /></div>
                    <div class="md:col-span-2"><p class="clinic-label">Layanan yang ditangani</p><div class="grid gap-3 sm:grid-cols-2">@forelse ($services as $service)<label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-clinic-200"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked($selectedServices->contains($service->id)) class="rounded border-slate-300 text-clinic-500 focus:ring-clinic-500">{{ $service->name }}</label>@empty<p class="text-sm text-slate-500 sm:col-span-2">Belum ada layanan aktif. Anda dapat menghubungkannya nanti.</p>@endforelse</div><x-input-error :messages="$errors->get('service_ids')" class="mt-2" /></div>
                </div>
            </section>

            @if ($editing)
                <section class="clinic-card p-6 sm:p-8"><input type="hidden" name="is_active" value="0"><label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active)) class="rounded border-slate-300 text-clinic-500 focus:ring-clinic-500">Akun staf aktif</label><p class="ml-7 mt-1 text-xs text-slate-500">Staf nonaktif tidak dapat masuk ke ClinicOS.</p></section>
            @endif

            <div class="flex justify-end gap-3"><x-clinic-button href="{{ route('admin.staff.index') }}" variant="secondary" wire:navigate>Batal</x-clinic-button><x-clinic-button type="submit" variant="primary" class="disabled:cursor-wait disabled:opacity-60" x-bind:disabled="submitting"><span x-show="! submitting">{{ $editing ? 'Simpan perubahan' : 'Buat akun staf' }}</span><span x-show="submitting" x-cloak>Menyimpan…</span></x-clinic-button></div>
        </form>
    </div>
</x-app-layout>
