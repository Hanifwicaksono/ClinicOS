<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-extrabold leading-tight text-clinic-950">
            {{ $dashboardTitle ?? 'Akses akun' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-clinic-950 shadow-sm sm:rounded-lg">
                <div class="p-6 text-stone-100">
                    <h3 class="text-lg font-extrabold text-stone-50">Selamat datang, {{ auth()->user()->name }}.</h3>
                    <p class="mt-2 text-stone-300">
                        {{ $dashboardDescription ?? 'Akun Anda belum memiliki role. Hubungi administrator klinik untuk mendapatkan akses.' }}
                    </p>
                    <x-clinic-button href="{{ route('profile') }}" variant="secondary" wire:navigate class="mt-4">
                        Kelola profil akun
                    </x-clinic-button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
