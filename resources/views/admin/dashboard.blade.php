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
        @php
            $setupItems = [
                ['done' => true, 'label' => 'Profil dan jam operasional', 'route' => 'admin.clinic.index'],
                ['done' => $metrics['services'] > 0, 'label' => 'Daftar layanan', 'route' => 'admin.services.index'],
                ['done' => $metrics['doctors'] > 0, 'label' => 'Akun dokter', 'route' => 'admin.staff.index'],
                ['done' => $metrics['schedules'] > 0, 'label' => 'Jadwal praktik', 'route' => 'admin.doctor-schedules.index'],
            ];
            $setupComplete = collect($setupItems)->every(fn (array $item): bool => $item['done']);
            $primaryAction = match (true) {
                $metrics['doctors'] === 0 => ['label' => 'Tambah dokter', 'route' => 'admin.staff.create'],
                $metrics['services'] === 0 => ['label' => 'Tambah layanan', 'route' => 'admin.services.create'],
                $metrics['schedules'] === 0 => ['label' => 'Buat jadwal', 'route' => 'admin.doctor-schedules.create'],
                default => ['label' => 'Lihat antrean', 'route' => 'receptionist.queues.index'],
            };
        @endphp

        <section class="mb-6 overflow-hidden rounded-3xl bg-clinic-950 px-6 py-8 text-white sm:px-8">
            <div class="grid gap-7 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-semibold text-clinic-200">Selamat datang, {{ auth()->user()->name }}</p>
                    <h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">{{ $clinic->name }}</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                        Ada <strong class="text-white">{{ $operationalMetrics['bookings'] }} booking</strong>,
                        <strong class="text-white">{{ $operationalMetrics['active_queues'] }} antrean aktif</strong>, dan
                        <strong class="text-white">{{ $operationalMetrics['completed_visits'] }} kunjungan selesai</strong> pada periode ini.
                    </p>
                </div>
                <x-clinic-button href="{{ route($primaryAction['route']) }}" variant="primary" class="w-full shrink-0 sm:w-auto" wire:navigate>
                    {{ $primaryAction['label'] }} <span aria-hidden="true">→</span>
                </x-clinic-button>
            </div>
        </section>

        <section class="clinic-card mb-6 p-5">
            <form method="GET" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div><p class="text-sm font-bold text-clinic-950">Periode operasional</p><p class="mt-1 text-xs text-slate-500">Menggunakan zona waktu {{ $timezone }}.</p></div>
                <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    <div><label class="clinic-label" for="from">Dari</label><input id="from" class="clinic-input" name="from" type="date" value="{{ $from }}"></div>
                    <div><label class="clinic-label" for="to">Sampai</label><input id="to" class="clinic-input" name="to" type="date" value="{{ $to }}"></div>
                    <x-clinic-button variant="secondary" class="w-full self-end sm:w-auto">Terapkan</x-clinic-button>
                </div>
            </form>
        </section>

        <section class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan operasional">
            @foreach ([
                ['label' => 'Pasien terjadwal', 'value' => $operationalMetrics['unique_patients'], 'description' => 'Pasien unik pada periode ini', 'route' => route('receptionist.appointments.index')],
                ['label' => 'Antrean aktif', 'value' => $operationalMetrics['active_queues'], 'description' => 'Menunggu, dipanggil, atau diperiksa', 'route' => route('receptionist.queues.index', ['date' => $from])],
                ['label' => 'Kunjungan selesai', 'value' => $operationalMetrics['completed_visits'], 'description' => 'Pelayanan yang sudah difinalisasi', 'route' => route('admin.audit-logs.index', ['action' => 'visit.completed', 'from' => $from, 'to' => $to])],
                ['label' => 'Estimasi nilai layanan', 'value' => 'Rp '.number_format($operationalMetrics['service_value'], 0, ',', '.'), 'description' => 'Bukan pembayaran yang diterima', 'route' => route('admin.audit-logs.index', ['action' => 'visit.completed', 'from' => $from, 'to' => $to])],
            ] as $metric)
                <a href="{{ $metric['route'] }}" class="clinic-card group p-5 transition hover:-translate-y-0.5 hover:border-clinic-200 hover:shadow-elevated focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500" wire:navigate>
                    <div class="flex items-start justify-between gap-3"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $metric['label'] }}</p><span class="text-clinic-500 transition group-hover:translate-x-0.5" aria-hidden="true">→</span></div>
                    <p class="mt-4 font-display text-2xl font-extrabold text-clinic-950">{{ $metric['value'] }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $metric['description'] }}</p>
                </a>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.05fr_.95fr]">
            <div class="clinic-card p-6">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-wider text-clinic-600">Kesiapan klinik</p><h3 class="mt-2 text-lg font-extrabold text-clinic-950">{{ $setupComplete ? 'Operasional siap dijalankan' : 'Masih ada yang perlu disiapkan' }}</h3><p class="mt-1 text-sm text-slate-500">Kelola fondasi klinik dari pintasan berikut.</p></div>
                    <span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-clinic-100 text-clinic-700' => $setupComplete, 'bg-amber-100 text-amber-800' => ! $setupComplete])>{{ collect($setupItems)->where('done', true)->count() }}/{{ count($setupItems) }} selesai</span>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach ($setupItems as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-clinic-200 hover:bg-clinic-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500">
                            <span @class(['flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold', 'bg-clinic-500 text-white' => $item['done'], 'bg-amber-100 text-amber-800' => ! $item['done']])>{{ $item['done'] ? '✓' : '!' }}</span>
                            <span class="min-w-0 flex-1 text-sm font-semibold text-slate-700">{{ $item['label'] }}</span><span class="text-slate-400" aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </div>
                <dl class="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-6 sm:grid-cols-4">
                    @foreach ([['Dokter', $metrics['doctors']], ['Resepsionis', $metrics['receptionists']], ['Layanan', $metrics['services']], ['Sesi jadwal', $metrics['schedules']]] as [$label, $value])
                        <div><dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt><dd class="mt-1 text-xl font-extrabold text-clinic-950">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>

            <div class="clinic-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h3 class="font-bold text-clinic-950">Tim terbaru</h3><p class="mt-1 text-sm text-slate-500">Anggota tim yang baru ditambahkan.</p></div><a href="{{ route('admin.staff.index') }}" class="text-sm font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Lihat semua</a></div>
                @forelse ($recentStaff as $staff)
                    <div class="flex items-center gap-4 border-b border-slate-100 px-6 py-4 last:border-0">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-clinic-100 font-bold text-clinic-700">{{ str($staff->name)->substr(0, 1)->upper() }}</span>
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-900">{{ $staff->name }}</p><p class="truncate text-xs text-slate-500">{{ $staff->email }}</p></div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $staff->roles->first()?->name }}</span>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center"><p class="font-semibold text-slate-700">Belum ada anggota tim</p><p class="mt-1 text-sm text-slate-500">Tambahkan dokter atau resepsionis pertama Anda.</p><x-clinic-button href="{{ route('admin.staff.create') }}" variant="secondary" class="mt-5" wire:navigate>Tambah staf</x-clinic-button></div>
                @endforelse
            </div>
        </section>

        <section class="clinic-card mt-8 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-bold text-clinic-950">Kunjungan selesai terbaru</h3><p class="mt-1 text-sm text-slate-500">Nominal menggunakan snapshot layanan saat kunjungan, bukan bukti pembayaran.</p></div><a href="{{ route('admin.audit-logs.index', ['action' => 'visit.completed', 'from' => $from, 'to' => $to]) }}" class="text-sm font-bold text-clinic-600">Lihat audit</a></div>
            <div class="divide-y divide-slate-100">
                @forelse($recentVisits as $visit)
                    <div class="grid gap-2 p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-center"><div><p class="font-bold text-slate-900">{{ $visit->patient->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $visit->service_name }}</p></div><p class="text-sm font-semibold text-slate-600">{{ $visit->doctor->user->name }}</p><div class="sm:text-right"><p class="font-bold text-clinic-700">Rp {{ number_format((float) $visit->total_amount, 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ $visit->completed_at->timezone($timezone)->format('d M H:i') }}</p></div></div>
                @empty
                    <div class="p-10 text-center"><p class="font-semibold text-slate-700">Belum ada kunjungan selesai</p><p class="mt-1 text-sm text-slate-500">Kunjungan yang difinalisasi pada periode ini akan muncul di sini.</p></div>
                @endforelse
            </div>
        </section>
    @endif
</x-app-layout>
