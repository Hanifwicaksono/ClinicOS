<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Portal akun</p>
            <h1 class="mt-1 text-xl font-extrabold text-clinic-950">{{ $dashboardTitle ?? 'Akses akun' }}</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <section class="overflow-hidden rounded-3xl bg-clinic-950 p-7 text-white shadow-elevated sm:p-10">
            <p class="text-sm font-semibold text-clinic-200">Selamat datang, {{ auth()->user()->name }}</p>
            <h2 class="mt-3 text-3xl font-extrabold">Informasi penting Anda, dalam satu tempat.</h2>
            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">{{ $dashboardDescription ?? 'Akun Anda belum memiliki role. Hubungi administrator klinik untuk mendapatkan akses.' }}</p>
            <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                @if (auth()->user()->hasRole('Patient'))
                    <x-clinic-button href="{{ route('patient.appointments.index') }}" wire:navigate>Lihat booking saya</x-clinic-button>
                @endif
                <x-clinic-button href="{{ route('profile') }}" variant="secondary" wire:navigate class="border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white">Kelola profil akun</x-clinic-button>
            </div>
        </section>
    </div>
</x-app-layout>
