<x-app-layout>
    <x-slot name="header">
        <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">{{ $appointment->booking_code }}</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Detail booking</h1></div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-5">
        <a class="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-clinic-700 hover:underline focus-visible:ring-2 focus-visible:ring-clinic-500" href="{{ route('receptionist.appointments.index') }}" wire:navigate><span aria-hidden="true">←</span> Booking & antrean</a>
        <section class="clinic-card overflow-hidden" data-booking-detail>
            <div class="flex flex-wrap items-center justify-between gap-4 bg-clinic-950 p-5 text-white sm:p-7">
                <div><p class="text-sm text-clinic-200">Nomor antrean</p><p class="clinic-queue-number mt-3 text-3xl sm:text-4xl" data-queue-number>{{ $appointment->queue->display_number }}</p></div>
                <x-status-badge :status="$appointment->status->value" />
            </div>
            <dl class="grid min-w-0 gap-6 p-5 sm:grid-cols-2 sm:p-7">
                @foreach([
                    ['Pasien', $appointment->patient->name],
                    ['No. RM', $appointment->patient->medical_record_number],
                    ['Dokter', $appointment->doctor->user->name],
                    ['Layanan', $appointment->service_name],
                    ['Tanggal', $appointment->appointment_date->translatedFormat('d F Y')],
                    ['Sesi', substr($appointment->schedule->start_time,0,5).'–'.substr($appointment->schedule->end_time,0,5)],
                    ['Tarif', 'Rp '.number_format((float)$appointment->service_price,0,',','.')],
                    ['Sumber', ['RECEPTIONIST'=>'Resepsionis','PATIENT'=>'Pasien','PUBLIC'=>'Booking online'][$appointment->source] ?? \Illuminate\Support\Str::headline($appointment->source)],
                ] as [$label,$value])
                    <div class="min-w-0" data-booking-field><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</dt><dd class="mt-2 font-bold leading-6 text-slate-900">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <div class="space-y-4 border-t border-slate-100 bg-slate-50/60 p-5 sm:p-7">
                @if($appointment->replacedBy)
                    <p class="text-sm leading-6 text-slate-600">Booking ini digantikan oleh <a class="font-bold text-clinic-700 underline" href="{{ route('receptionist.appointments.show',$appointment->replacedBy->replacement) }}">{{ $appointment->replacedBy->replacement->booking_code }}</a>.</p>
                @endif
                @if($appointment->replacementOf)
                    <p class="text-sm leading-6 text-slate-600">Pengganti <a class="font-bold text-clinic-700 underline" href="{{ route('receptionist.appointments.show',$appointment->replacementOf->original) }}">{{ $appointment->replacementOf->original->booking_code }}</a>. Histori booking asal tetap tersimpan.</p>
                @endif
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap" data-booking-actions>
                    <form method="POST" action="{{ route('patient-billing.open',$appointment) }}" data-care-form>
                        @csrf
                        <p data-care-error role="alert" class="mb-2 empty:hidden text-sm text-red-700"></p>
                        <x-clinic-button class="w-full sm:w-auto">Buka tagihan</x-clinic-button>
                    </form>
                    @if(!$appointment->replacedBy && $appointment->status===\App\AppointmentStatus::Booked && !$appointment->visit)
                        <x-clinic-button href="{{ route('appointments.replace.create',$appointment) }}" variant="secondary">Pindahkan jadwal / dokter</x-clinic-button>
                    @endif
                </div>
                <p class="text-xs leading-5 text-slate-500">Buka tagihan untuk meninjau biaya layanan atau mencatat uang muka yang diizinkan.</p>
            </div>
            @if($appointment->status === \App\AppointmentStatus::Booked)
                <details class="border-t border-slate-100 p-5 sm:p-7">
                    <summary class="min-h-11 cursor-pointer py-2 text-sm font-bold text-red-700 focus-visible:ring-2 focus-visible:ring-red-500">Batalkan booking</summary>
                    <form method="POST" action="{{ route('receptionist.appointments.cancel', $appointment) }}" class="mt-4 space-y-4" onsubmit="return confirm('Batalkan booking ini?')">
                        @csrf
                        <div><label class="clinic-label" for="cancel_reason">Alasan pembatalan (opsional)</label><input id="cancel_reason" class="clinic-input" name="cancel_reason" value="{{ old('cancel_reason') }}" maxlength="500" placeholder="Catat alasan pembatalan"></div>
                        <x-clinic-button variant="danger">Konfirmasi pembatalan</x-clinic-button>
                    </form>
                </details>
            @endif
        </section>
    </div>
</x-app-layout>
