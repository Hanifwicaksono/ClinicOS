<x-app-layout>
    <x-slot name="header"><div class="flex w-full items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Katalog klinik</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Layanan</h1></div><x-clinic-button href="{{ route('admin.services.create') }}" variant="primary" wire:navigate>+ Tambah layanan</x-clinic-button></div></x-slot>

    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Daftar layanan</h2><p class="mt-2 text-sm text-slate-500">Atur biaya dan durasi standar sebagai dasar pendaftaran pasien.</p></div>

    <div class="clinic-card overflow-hidden"><div class="overflow-x-auto"><table class="clinic-table w-full"><thead><tr><th>Layanan</th><th>Biaya</th><th>Durasi</th><th>Dokter</th><th>Status</th><th class="text-right">Aksi</th></tr></thead><tbody>
        @forelse ($services as $service)
            <tr class="hover:bg-slate-50/70"><td><p class="font-bold text-slate-900">{{ $service->name }}</p><p class="mt-1 max-w-md truncate text-xs text-slate-500">{{ $service->description ?: 'Tanpa deskripsi' }}</p></td><td class="font-semibold text-slate-900">Rp {{ number_format((float) $service->price, 0, ',', '.') }}</td><td>{{ $service->duration_minutes }} menit</td><td>{{ $service->doctors_count }}</td><td><span class="{{ $service->is_active ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ $service->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-right"><a href="{{ route('admin.services.edit', $service) }}" class="font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Edit</a></td></tr>
        @empty
            <tr><td colspan="6" class="py-16 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-50 text-xl text-clinic-600">+</div><p class="mt-4 font-bold text-slate-800">Belum ada layanan</p><p class="mt-1 text-sm text-slate-500">Tambahkan layanan pertama untuk dihubungkan ke dokter.</p></td></tr>
        @endforelse
    </tbody></table></div>@if ($services->hasPages()) <div class="border-t border-slate-100 px-5 py-4">{{ $services->links() }}</div> @endif</div>
</x-app-layout>
