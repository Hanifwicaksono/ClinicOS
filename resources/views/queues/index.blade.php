@php
    $isDoctor = auth()->user()->hasRole('Doctor');
    $doctorOptions = $doctors->map(fn ($doctor) => ['id' => $doctor->id, 'label' => $doctor->user->name])->values();
    $scheduleOptions = $doctors->flatMap(fn ($doctor) => $doctor->schedules->map(fn ($schedule) => ['id' => $schedule->id, 'label' => $doctor->user->name.' · '.$schedule->day_name.' '.substr($schedule->start_time, 0, 5)]))->values();
    $statusStyles = [
        'BOOKED' => 'bg-slate-100 text-slate-700',
        'WAITING' => 'bg-[#F7F1EA] text-[#D89B68]',
        'CALLED' => 'bg-[#EAF2F0] text-[#287F78]',
        'IN_PROGRESS' => 'bg-[#EEF3F2] text-[#3D5F5C]',
        'COMPLETED' => 'bg-[#EAF2F0] text-[#287F78]',
        'SKIPPED' => 'bg-orange-100 text-orange-700',
        'CANCELLED' => 'bg-slate-100 text-slate-500',
        'NO_SHOW' => 'bg-red-100 text-red-700',
    ];
@endphp
<x-app-layout>
    <x-slot name="header"><div class="flex w-full items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Operasional hari ini</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Antrean pasien</h1></div>@unless($isDoctor)<x-clinic-button href="{{ route('receptionist.appointments.create') }}" variant="primary" wire:navigate>+ Booking walk-in</x-clinic-button>@endunless</div></x-slot>

    <div data-realtime-queue-channel="clinic.{{ auth()->user()->clinic_id }}.queues" data-realtime-refresh-root>
    <div class="mb-6 hidden rounded-2xl border px-5 py-4 text-sm font-semibold" data-queue-action-feedback role="status" aria-live="polite"></div>
    @if($errors->any())<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Antrean {{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('d F Y') }}</h2><p class="mt-2 text-sm text-slate-500">Check-in pasien sesuai kedatangan, lalu panggil satu per satu dalam setiap sesi dokter.</p></div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach([[\App\QueueStatus::Booked, 'Terdaftar'], [\App\QueueStatus::Waiting, 'Menunggu'], [\App\QueueStatus::Called, 'Dipanggil'], [\App\QueueStatus::InProgress, 'Diperiksa'], [\App\QueueStatus::Completed, 'Selesai'], [\App\QueueStatus::Skipped, 'Dilewati']] as [$status, $label])
            <div class="clinic-card p-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-extrabold text-clinic-950">{{ $statusCounts[$status->value] ?? 0 }}</p></div>
        @endforeach
    </div>

    <form method="GET" class="clinic-card mb-6 grid gap-4 p-5 md:grid-cols-4">
        <div><label class="clinic-label" for="date">Tanggal</label><input class="clinic-input" id="date" name="date" type="date" value="{{ $date }}"></div>
        @unless($isDoctor)<x-receptionist.search-select name="doctor_id" label="Dokter" placeholder="Cari dokter" :options="$doctorOptions" :selected="request('doctor_id')" :required="false" empty-label="Semua dokter" />@endunless
        <x-receptionist.search-select name="doctor_schedule_id" label="Sesi" placeholder="Cari sesi" :options="$scheduleOptions" :selected="request('doctor_schedule_id')" :required="false" empty-label="Semua sesi" />
        <div><label class="clinic-label" for="status">Status</label><select class="clinic-input" id="status" name="status"><option value="">Semua status</option>@foreach(\App\QueueStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
        <div class="flex items-end"><x-clinic-button variant="secondary" class="w-full">Terapkan filter</x-clinic-button></div>
    </form>

    <div class="space-y-4">
        @forelse($queues as $queue)
            <x-queue-card :queue="$queue" />
        @empty
            <div class="clinic-card p-14 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-50 text-xl text-clinic-600">✓</div><p class="mt-4 font-bold text-slate-800">Tidak ada antrean</p><p class="mt-1 text-sm text-slate-500">Belum ada antrean yang sesuai dengan filter ini.</p></div>
        @endforelse
    </div>
    @if($queues->hasPages())<div class="mt-6">{{ $queues->links() }}</div>@endif
    </div>
</x-app-layout>
