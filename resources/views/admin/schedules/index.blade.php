<x-app-layout>
    <x-slot name="header"><div class="flex w-full items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Operasional</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Jadwal dokter</h1></div><a href="{{ route('admin.doctor-schedules.create') }}" class="clinic-button-primary" wire:navigate>+ Tambah jadwal</a></div></x-slot>
    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Jadwal praktik</h2><p class="mt-2 text-sm text-slate-500">Atur sesi dan kuota dokter dalam jam operasional klinik.</p></div>
    <div class="clinic-card overflow-hidden"><div class="overflow-x-auto"><table class="clinic-table w-full"><thead><tr><th>Dokter</th><th>Hari</th><th>Jam praktik</th><th>Kuota</th><th>Status</th><th class="text-right">Aksi</th></tr></thead><tbody>
        @forelse ($schedules as $schedule)
            <tr class="hover:bg-slate-50/70"><td><p class="font-bold text-slate-900">{{ $schedule->doctor->user->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $schedule->doctor->specialization }}</p></td><td class="font-semibold">{{ $schedule->day_name }}</td><td>{{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }}</td><td>{{ $schedule->quota }} pasien</td><td><span class="{{ $schedule->is_active ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-right"><a href="{{ route('admin.doctor-schedules.edit', $schedule) }}" class="font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Edit</a></td></tr>
        @empty
            <tr><td colspan="6" class="py-16 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-50 text-xl text-clinic-600">+</div><p class="mt-4 font-bold text-slate-800">Belum ada jadwal</p><p class="mt-1 text-sm text-slate-500">Tambahkan dokter aktif terlebih dahulu, lalu buat sesi praktik.</p></td></tr>
        @endforelse
    </tbody></table></div>@if ($schedules->hasPages()) <div class="border-t border-slate-100 px-5 py-4">{{ $schedules->links() }}</div> @endif</div>
</x-app-layout>
