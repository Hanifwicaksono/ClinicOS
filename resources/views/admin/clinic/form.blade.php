@php
    $editing = isset($clinic);
    $settings = $editing ? $clinic->settings : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Pengaturan</p>
            <h1 class="mt-1 text-xl font-extrabold text-clinic-950">{{ $editing ? 'Profil klinik' : 'Setup klinik' }}</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="mb-7">
            <h2 class="text-2xl font-extrabold text-clinic-950">Identitas dan operasional</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">Informasi ini menjadi sumber utama untuk layanan, jadwal, dan komunikasi pasien.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.clinic.update', $clinic) : route('admin.clinic.store') }}" class="space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @if ($editing) @method('PUT') @endif

            <section class="clinic-card p-6 sm:p-8">
                <div class="border-b border-slate-100 pb-5"><h3 class="font-bold text-clinic-950">Profil dasar</h3><p class="mt-1 text-sm text-slate-500">Nama, alamat, dan kontak resmi klinik.</p></div>
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <div><label class="clinic-label" for="name">Nama klinik</label><input class="clinic-input" id="name" name="name" value="{{ old('name', $clinic->name ?? '') }}" required><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="slug">Slug klinik</label><input class="clinic-input" id="slug" name="slug" value="{{ old('slug', $clinic->slug ?? '') }}" placeholder="klinik-sehat" required><x-input-error :messages="$errors->get('slug')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="email">Email klinik</label><input class="clinic-input" id="email" type="email" name="email" value="{{ old('email', $clinic->email ?? '') }}"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="phone">Nomor telepon</label><input class="clinic-input" id="phone" name="phone" value="{{ old('phone', $clinic->phone ?? '') }}"><x-input-error :messages="$errors->get('phone')" class="mt-2" /></div>
                    <div class="md:col-span-2"><label class="clinic-label" for="address">Alamat</label><textarea class="clinic-input" id="address" name="address" rows="3">{{ old('address', $clinic->address ?? '') }}</textarea><x-input-error :messages="$errors->get('address')" class="mt-2" /></div>
                    <div class="md:col-span-2"><label class="clinic-label" for="description">Deskripsi singkat</label><textarea class="clinic-input" id="description" name="description" rows="3">{{ old('description', $clinic->description ?? '') }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                    <div class="md:col-span-2"><label class="clinic-label" for="logo">Logo klinik</label><input class="clinic-input file:mr-4 file:rounded-lg file:border-0 file:bg-clinic-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-clinic-700" id="logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"><p class="mt-2 text-xs text-slate-500">JPG, PNG, atau WebP. Maksimal 2 MB.</p><x-input-error :messages="$errors->get('logo')" class="mt-2" /></div>
                </div>
            </section>

            <section class="clinic-card p-6 sm:p-8">
                <div class="border-b border-slate-100 pb-5"><h3 class="font-bold text-clinic-950">Jam operasional</h3><p class="mt-1 text-sm text-slate-500">Jadwal dokter harus berada di dalam rentang waktu ini.</p></div>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="clinic-label" for="opening_time">Jam buka</label><input class="clinic-input" id="opening_time" type="time" name="opening_time" value="{{ old('opening_time', $settings ? substr($settings->opening_time, 0, 5) : '08:00') }}" required><x-input-error :messages="$errors->get('opening_time')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="closing_time">Jam tutup</label><input class="clinic-input" id="closing_time" type="time" name="closing_time" value="{{ old('closing_time', $settings ? substr($settings->closing_time, 0, 5) : '17:00') }}" required><x-input-error :messages="$errors->get('closing_time')" class="mt-2" /></div>
                    <div><label class="clinic-label" for="timezone">Zona waktu</label><select class="clinic-input" id="timezone" name="timezone">@foreach (['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'] as $value => $label)<option value="{{ $value }}" @selected(old('timezone', $settings->timezone ?? 'Asia/Jakarta') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="clinic-label" for="queue_prefix">Prefix antrean</label><input class="clinic-input uppercase" id="queue_prefix" name="queue_prefix" maxlength="5" value="{{ old('queue_prefix', $settings->queue_prefix ?? 'A') }}" required><x-input-error :messages="$errors->get('queue_prefix')" class="mt-2" /></div>
                </div>
                <div class="mt-6"><input type="hidden" name="is_active" value="0"><label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $clinic->is_active ?? true)) class="rounded border-slate-300 text-clinic-500 focus:ring-clinic-500">Klinik aktif dan dapat digunakan</label></div>
            </section>

            <div class="flex justify-end gap-3"><x-clinic-button href="{{ route('admin.dashboard') }}" variant="secondary" wire:navigate>Batal</x-clinic-button><x-clinic-button type="submit" variant="primary" class="disabled:cursor-wait disabled:opacity-60" x-bind:disabled="submitting"><span x-show="! submitting">{{ $editing ? 'Simpan perubahan' : 'Buat klinik' }}</span><span x-show="submitting" x-cloak>Menyimpan…</span></x-clinic-button></div>
        </form>
    </div>
</x-app-layout>
