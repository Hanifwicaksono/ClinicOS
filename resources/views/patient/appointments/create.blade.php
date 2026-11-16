<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-extrabold text-clinic-950">Booking baru</h1></x-slot>
    <div class="mx-auto max-w-3xl space-y-6">
        <section class="clinic-card p-6">
            <h2 class="text-lg font-bold">Pilih klinik Anda</h2>
            <p class="mt-2 text-sm text-slate-600">Klinik yang terhubung dengan akun Anda.</p>
            <div class="mt-4 grid gap-3">
                @forelse ($clinics as $clinic)
                    <a class="rounded-xl border border-clinic-200 p-4 font-bold text-clinic-700 hover:bg-clinic-50" href="{{ route('public.booking.create', $clinic->slug) }}">{{ $clinic->name }}</a>
                @empty
                    <p class="text-sm text-slate-600">Belum ada klinik terhubung. Gunakan kode dari klinik untuk booking pertama.</p>
                @endforelse
            </div>
        </section>
        <form method="GET" action="{{ route('patient.appointments.create') }}" class="clinic-card p-6">
            <label for="clinic_code" class="clinic-label">Kode klinik</label>
            <p id="clinic-code-help" class="mb-3 text-sm text-slate-600">Masukkan kode pada tautan booking dari klinik, misalnya klinik-sehat-sentosa. Anda juga dapat membuka tautan booking langsung.</p>
            <input id="clinic_code" name="clinic_code" class="clinic-input" value="{{ old('clinic_code') }}" maxlength="255" aria-describedby="clinic-code-help" required>
            <x-clinic-button class="mt-4">Lanjutkan booking</x-clinic-button>
        </form>
    </div>
</x-app-layout>
