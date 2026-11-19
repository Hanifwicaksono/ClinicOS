<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-extrabold text-clinic-950">Pindahkan booking</h1></x-slot>
    <div class="mx-auto max-w-4xl space-y-6">
        <section class="clinic-card p-6"><h2 class="text-lg font-bold">{{ $appointment->booking_code }} | {{ $appointment->patient->name }}</h2><p class="mt-2 text-sm text-slate-600">{{ $appointment->doctor->user->name }} | {{ $appointment->appointment_date->format('d/m/Y') }} | {{ $appointment->service_name }} | Rp {{ number_format((float)$appointment->service_price,0,',','.') }}</p><p class="mt-2 text-sm text-slate-500">Booking lama tetap menjadi histori. Perpindahan hanya berlaku sebelum pemeriksaan dimulai.</p></section>
        <form method="POST" action="{{ route('appointments.replace.preview',$appointment) }}" class="clinic-card space-y-5 p-6" x-data="{submitting:false}" @submit="submitting=true" @pageshow.window="submitting=false">
            @csrf
            <input type="hidden" name="request_id" value="{{ $requestId }}"><input type="hidden" name="expected_version" value="{{ $expectedVersion }}">
            <h2 class="text-lg font-bold">Jadwal tujuan</h2>@if($linkedWorkflow)<p class="text-sm text-slate-600">Booking ini tertaut kontrol/kelanjutan. Pilih sesi untuk dokter dan layanan yang sudah disahkan. Perubahan penanggung jawab mengikuti persetujuan klinis.</p>@endif
            <x-booking-fields :doctors="$doctors" :timezone="$appointment->clinic->settings?->timezone ?? 'Asia/Jakarta'" />
            <div><label class="clinic-label" for="reason_source">Permintaan perubahan</label><select class="clinic-input" id="reason_source" name="reason_source" required><option value="PATIENT" @selected(old('reason_source')==='PATIENT')>Permintaan pasien</option><option value="CLINIC" @selected(old('reason_source')==='CLINIC')>Kebutuhan klinik</option></select></div>
            <div><label class="clinic-label" for="reason">Alasan operasional</label><textarea class="clinic-input" id="reason" name="reason" maxlength="2000" required>{{ old('reason') }}</textarea><p class="mt-2 text-xs text-slate-500">Cukup alasan penjadwalan; jangan menulis isi pemeriksaan medis.</p></div>
            <div class="flex flex-wrap justify-end gap-3"><x-clinic-button href="{{ route('receptionist.appointments.show',$appointment) }}" variant="secondary">Kembali</x-clinic-button><x-clinic-button x-bind:disabled="submitting">Tinjau dampak perpindahan</x-clinic-button></div>
        </form>
    </div>
</x-app-layout>
