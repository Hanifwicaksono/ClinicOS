<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Workspace admin</p>
                <h1 class="mt-1 text-xl font-extrabold text-clinic-950">Dashboard</h1>
            </div>
            @if ($clinic)
                <span class="clinic-status-active"><span class="h-1.5 w-1.5 rounded-full bg-clinic-500"></span> Klinik aktif</span>
            @endif
        </div>
    </x-slot>

    @if (! $clinic)
        <section class="overflow-hidden rounded-3xl bg-clinic-950 px-6 py-10 text-white sm:px-10 lg:flex lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <span class="inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-clinic-200">Langkah pertama</span>
                <h2 class="mt-5 text-3xl font-extrabold sm:text-4xl">Siapkan identitas klinik Anda</h2>
                <p class="mt-3 text-base leading-7 text-slate-300">Isi profil dan jam operasional klinik sebelum menambahkan dokter, resepsionis, layanan, dan jadwal.</p>
            </div>
            <x-clinic-button href="{{ route('admin.clinic.create') }}" variant="primary" class="mt-7 shrink-0 lg:mt-0" wire:navigate>Mulai setup klinik</x-clinic-button>
        </section>

    @else
        <section class="mb-8 overflow-hidden rounded-3xl bg-clinic-950 px-6 py-8 text-white sm:px-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-clinic-200">Selamat datang, {{ auth()->user()->name }}</p>
                    <h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">{{ $clinic->name }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Kelola fondasi operasional klinik dari satu workspace yang rapi dan mudah dipantau.</p>
                </div>
                <x-clinic-button href="{{ route('admin.staff.create') }}" variant="primary" class="shrink-0" wire:navigate>+ Tambah staf</x-clinic-button>
            </div>
        </section>

        <section class="clinic-card mb-6 p-5"><form method="GET" class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-bold text-clinic-950">Periode operasional</p><p class="mt-1 text-xs text-slate-500">Menggunakan zona waktu {{ $timezone }}.</p></div><div class="flex flex-col gap-3 sm:flex-row"><div><label class="clinic-label">Dari</label><input class="clinic-input" name="from" type="date" value="{{ $from }}"></div><div><label class="clinic-label">Sampai</label><input class="clinic-input" name="to" type="date" value="{{ $to }}"></div><x-clinic-button variant="secondary" class="self-end">Terapkan</x-clinic-button></div></form></section>

        <section class="mb-8 grid grid-cols-2 gap-4 xl:grid-cols-5">@foreach ([['label' => 'Pasien unik terjadwal', 'value' => $operationalMetrics['unique_patients']],['label' => 'Total booking', 'value' => $operationalMetrics['bookings']],['label' => 'Kunjungan selesai', 'value' => $operationalMetrics['completed_visits']],['label' => 'Antrean aktif', 'value' => $operationalMetrics['active_queues']],['label' => 'Nilai layanan selesai', 'value' => 'Rp '.number_format($operationalMetrics['service_value'], 0, ',', '.')]] as $metric)<article class="clinic-card p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $metric['label'] }}</p><p class="mt-4 font-display text-2xl font-extrabold text-clinic-950">{{ $metric['value'] }}</p></article>@endforeach</section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Dokter aktif', 'value' => $metrics['doctors'], 'color' => 'bg-[#EAF2F0] text-[#287F78]'],
                ['label' => 'Resepsionis', 'value' => $metrics['receptionists'], 'color' => 'bg-[#EEF3F2] text-[#3D5F5C]'],
                ['label' => 'Layanan aktif', 'value' => $metrics['services'], 'color' => 'bg-[#F7F1EA] text-[#D89B68]'],
                ['label' => 'Sesi jadwal', 'value' => $metrics['schedules'], 'color' => 'bg-[#F7F8F5] text-[#253331]'],
            ] as $metric)
                <article class="clinic-card p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-500">{{ $metric['label'] }}</p>
                        <span class="h-3 w-3 rounded-full {{ $metric['color'] }}"></span>
                    </div>
                    <p class="mt-5 font-display text-3xl font-extrabold text-clinic-950">{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <div class="clinic-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div><h3 class="font-bold text-clinic-950">Staf terbaru</h3><p class="mt-1 text-sm text-slate-500">Anggota tim yang baru ditambahkan.</p></div>
                    <a href="{{ route('admin.staff.index') }}" class="text-sm font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Lihat semua</a>
                </div>
                @forelse ($recentStaff as $staff)
                    <div class="flex items-center gap-4 border-b border-slate-100 px-6 py-4 last:border-0">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-clinic-100 font-bold text-clinic-700">{{ str($staff->name)->substr(0, 1)->upper() }}</span>
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-900">{{ $staff->name }}</p><p class="truncate text-xs text-slate-500">{{ $staff->email }}</p></div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $staff->roles->first()?->name }}</span>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center"><p class="font-semibold text-slate-700">Belum ada staf</p><p class="mt-1 text-sm text-slate-500">Tambahkan dokter atau resepsionis pertama Anda.</p></div>
                @endforelse
            </div>

            <div class="clinic-card p-6">
                <h3 class="font-bold text-clinic-950">Setup klinik</h3>
                <p class="mt-1 text-sm text-slate-500">Lengkapi fondasi sebelum membuka pendaftaran.</p>
                <div class="mt-6 space-y-4">
                    @foreach ([
                        ['done' => true, 'label' => 'Profil dan jam operasional', 'route' => 'admin.clinic.index'],
                        ['done' => $metrics['services'] > 0, 'label' => 'Daftar layanan', 'route' => 'admin.services.index'],
                        ['done' => $metrics['doctors'] > 0, 'label' => 'Akun dokter', 'route' => 'admin.staff.index'],
                        ['done' => $metrics['schedules'] > 0, 'label' => 'Jadwal praktik', 'route' => 'admin.doctor-schedules.index'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 hover:border-clinic-200 hover:bg-clinic-50">
                            <span @class(['flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold', 'bg-clinic-500 text-white' => $item['done'], 'bg-slate-100 text-slate-400' => ! $item['done']])>{{ $item['done'] ? '✓' : '•' }}</span>
                            <span class="text-sm font-semibold text-slate-700">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="clinic-card mt-8 overflow-hidden"><div class="flex items-center justify-between border-b border-slate-100 p-5"><div><h3 class="font-bold text-clinic-950">Kunjungan selesai terbaru</h3><p class="mt-1 text-sm text-slate-500">Nilai layanan memakai snapshot saat kunjungan.</p></div><a href="{{ route('admin.audit-logs.index', ['action' => 'visit.completed', 'from' => $from, 'to' => $to]) }}" class="text-sm font-bold text-clinic-600">Lihat audit</a></div><div class="divide-y divide-slate-100">@forelse($recentVisits as $visit)<div class="grid gap-2 p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-center"><div><p class="font-bold text-slate-900">{{ $visit->patient->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $visit->service_name }}</p></div><p class="text-sm font-semibold text-slate-600">{{ $visit->doctor->user->name }}</p><div class="sm:text-right"><p class="font-bold text-clinic-700">Rp {{ number_format((float) $visit->total_amount, 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-400">{{ $visit->completed_at->timezone($timezone)->format('d M H:i') }}</p></div></div>@empty<div class="p-10 text-center text-sm text-slate-500">Belum ada kunjungan selesai pada periode ini.</div>@endforelse</div></section>
    @endif
</x-app-layout>
