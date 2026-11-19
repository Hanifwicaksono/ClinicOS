<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Manajemen tim</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Dokter & resepsionis</h1></div>
            <x-clinic-button href="{{ route('admin.staff.create') }}" variant="primary" wire:navigate>+ Tambah staf</x-clinic-button>
        </div>
    </x-slot>

    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Tim klinik</h2><p class="mt-2 text-sm text-slate-500">Kelola akses operasional dan profil dokter tanpa membuka data medis kepada admin.</p></div>

    <div class="clinic-card overflow-hidden">
        <div class="overflow-x-auto clinic-table-scroll" tabindex="0" role="region" aria-label="Tabel data, geser untuk melihat kolom lainnya">
            <table class="clinic-table w-full min-w-[56rem]"><colgroup><col><col style="width:10rem"><col style="width:12rem"><col style="width:8rem"><col style="width:6rem"></colgroup>
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
    @if ($realtimeRevocations->isNotEmpty())
        <section class="clinic-card mt-6 p-5" aria-labelledby="realtime-revocations-title">
            <h2 id="realtime-revocations-title" class="font-bold text-clinic-950">Revokasi akses realtime</h2>
            <p class="mt-2 text-sm text-slate-600">Perubahan hak HTTP langsung berlaku. Konfirmasi berikut berasal dari fence server Reverb satu node, bukan konfirmasi request transport saja.</p>
            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($realtimeRevocations as $revocation)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" data-realtime-revocation="{{ $revocation->id }}">
                        <p class="text-sm">Akun #{{ $revocation->user_id }} · versi {{ $revocation->auth_version }}:
                            <strong>{{ $revocation->acknowledged_at ? 'Dikonfirmasi server' : ($revocation->failed_at ? 'Transport gagal; revokasi menunggu konfirmasi' : 'Menunggu konfirmasi server') }}</strong>
                        </p>
                        @if (! $revocation->acknowledged_at && auth()->user()->canAny(['doctor.update', 'receptionist.update']))
                            <form method="POST" action="{{ route('admin.realtime-revocations.retry', $revocation) }}">@csrf<button type="submit" class="text-sm font-bold text-clinic-700 underline">Coba konfirmasi lagi</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-app-layout>
