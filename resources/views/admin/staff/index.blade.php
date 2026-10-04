<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Manajemen tim</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Dokter & resepsionis</h1></div>
            <a href="{{ route('admin.staff.create') }}" class="clinic-button-primary" wire:navigate>+ Tambah staf</a>
        </div>
    </x-slot>

    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Tim klinik</h2><p class="mt-2 text-sm text-slate-500">Kelola akses operasional dan profil dokter tanpa membuka data medis kepada admin.</p></div>

    <div class="clinic-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="clinic-table w-full">
                <thead><tr><th>Staf</th><th>Peran</th><th>Spesialisasi</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($staffMembers as $staff)
                        <tr class="hover:bg-slate-50/70">
                            <td><div class="flex items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-clinic-100 font-bold text-clinic-700">{{ str($staff->name)->substr(0, 1)->upper() }}</span><div><p class="font-bold text-slate-900">{{ $staff->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $staff->email }}</p></div></div></td>
                            <td><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $staff->roles->first()?->name }}</span></td>
                            <td>{{ $staff->doctor?->specialization ?? '—' }}</td>
                            <td><span class="{{ $staff->is_active ? 'clinic-status-active' : 'clinic-status-inactive' }}"><span class="h-1.5 w-1.5 rounded-full {{ $staff->is_active ? 'bg-clinic-500' : 'bg-slate-400' }}"></span>{{ $staff->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-right"><a href="{{ route('admin.staff.edit', $staff) }}" class="font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-16 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-50 text-xl text-clinic-600">+</div><p class="mt-4 font-bold text-slate-800">Belum ada staf</p><p class="mt-1 text-sm text-slate-500">Tambahkan dokter atau resepsionis untuk mulai membentuk tim.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($staffMembers->hasPages()) <div class="border-t border-slate-100 px-5 py-4">{{ $staffMembers->links() }}</div> @endif
    </div>
</x-app-layout>
