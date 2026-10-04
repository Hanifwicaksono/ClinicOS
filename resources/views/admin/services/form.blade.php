@php($editing = isset($service))
<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Katalog klinik</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">{{ $editing ? 'Edit layanan' : 'Tambah layanan' }}</h1></div></x-slot>
    <div class="mx-auto max-w-3xl"><div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">{{ $editing ? $service->name : 'Layanan baru' }}</h2><p class="mt-2 text-sm text-slate-500">Tentukan nama, tarif, dan durasi standar pelayanan.</p></div>
        <form method="POST" action="{{ $editing ? route('admin.services.update', $service) : route('admin.services.store') }}" class="space-y-6" x-data="{ submitting: false }" @submit="submitting = true">@csrf @if ($editing) @method('PUT') @endif
            <section class="clinic-card p-6 sm:p-8"><div class="grid gap-6 md:grid-cols-2">
                <div class="md:col-span-2"><label class="clinic-label" for="name">Nama layanan</label><input class="clinic-input" id="name" name="name" value="{{ old('name', $service->name ?? '') }}" required><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                <div><label class="clinic-label" for="price">Biaya (Rp)</label><input class="clinic-input" id="price" name="price" type="number" min="0" step="1000" value="{{ old('price', $service->price ?? '') }}" required><x-input-error :messages="$errors->get('price')" class="mt-2" /></div>
                <div><label class="clinic-label" for="duration_minutes">Durasi</label><div class="relative"><input class="clinic-input pr-16" id="duration_minutes" name="duration_minutes" type="number" min="5" max="480" value="{{ old('duration_minutes', $service->duration_minutes ?? 30) }}" required><span class="pointer-events-none absolute right-4 top-3 text-sm text-slate-400">menit</span></div><x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" /></div>
                <div class="md:col-span-2"><label class="clinic-label" for="description">Deskripsi</label><textarea class="clinic-input" id="description" name="description" rows="4">{{ old('description', $service->description ?? '') }}</textarea><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                <div class="md:col-span-2"><input type="hidden" name="is_active" value="0"><label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true)) class="rounded border-slate-300 text-clinic-500 focus:ring-clinic-500">Layanan aktif dan dapat dipilih</label></div>
            </div></section>
            <div class="flex justify-end gap-3"><a href="{{ route('admin.services.index') }}" class="clinic-button-secondary" wire:navigate>Batal</a><button type="submit" class="clinic-button-primary disabled:cursor-wait disabled:opacity-60" :disabled="submitting"><span x-show="! submitting">{{ $editing ? 'Simpan perubahan' : 'Tambah layanan' }}</span><span x-show="submitting" x-cloak>Menyimpan…</span></button></div>
        </form>
    </div>
</x-app-layout>
