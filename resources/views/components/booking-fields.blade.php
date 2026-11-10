@props(['doctors', 'timezone' => 'Asia/Jakarta'])
@php
    $choices = $doctors->map(fn ($doctor) => [
        'id' => $doctor->id,
        'label' => $doctor->user->name.' — '.$doctor->specialization,
        'services' => $doctor->services->where('is_active', true)->map(fn ($service) => [
            'id' => $service->id, 'label' => $service->name.' — Rp '.number_format((float) $service->price, 0, ',', '.'),
        ])->values()->all(),
        'schedules' => $doctor->schedules->where('is_active', true)->map(fn ($schedule) => [
            'id' => $schedule->id, 'day' => $schedule->day_of_week,
            'label' => $schedule->day_name.' · '.substr($schedule->start_time, 0, 5).'–'.substr($schedule->end_time, 0, 5),
        ])->values()->all(),
    ])->values();
@endphp
<div x-data="bookingSelection(@js($choices), @js(['doctor_id' => old('doctor_id'), 'service_id' => old('service_id'), 'doctor_schedule_id' => old('doctor_schedule_id'), 'appointment_date' => old('appointment_date')]))" class="mt-6 grid gap-5 md:grid-cols-2" data-booking-fields>
    <x-receptionist.search-select name="doctor_id" label="Dokter / spesialisasi" placeholder="Cari dokter atau spesialisasi" :options="$choices" :selected="old('doctor_id')" x-model="doctorId" />
    <div><label class="clinic-label" for="appointment_date">Tanggal kunjungan</label><input class="clinic-input" id="appointment_date" name="appointment_date" type="date" x-model="date" value="{{ old('appointment_date') }}" min="{{ now($timezone)->toDateString() }}" max="{{ now($timezone)->addDays(30)->toDateString() }}" required></div>
    <div>
        <x-receptionist.search-select name="service_id" label="Layanan" placeholder="Pilih layanan" :options="[]" :selected="old('service_id')" x-model="serviceId" x-effect="setOptions(availableServices)" />
        <p class="mt-2 text-xs text-slate-500" x-show="doctorId && !availableServices.length" x-cloak>Belum ada layanan aktif untuk dokter ini.</p>
    </div>
    <div>
        <x-receptionist.search-select name="doctor_schedule_id" label="Sesi praktik" placeholder="Pilih sesi praktik" :options="[]" :selected="old('doctor_schedule_id')" x-model="scheduleId" x-effect="setOptions(availableSchedules)" />
        <p class="mt-2 text-xs text-slate-500" x-show="doctorId && date && !availableSchedules.length" x-cloak>Dokter tidak memiliki sesi pada tanggal ini. Pilih tanggal lain.</p>
    </div>
    <p class="text-sm leading-6 text-slate-500 md:col-span-2">Layanan mengikuti dokter yang dipilih; sesi mengikuti hari kunjungan. Ketersediaan kuota diperiksa saat booking disimpan.</p>
</div>
