<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $dashboardTitle ?? 'Akses akun' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold">Selamat datang, {{ auth()->user()->name }}.</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        {{ $dashboardDescription ?? 'Akun Anda belum memiliki role. Hubungi administrator klinik untuk mendapatkan akses.' }}
                    </p>
                    <a href="{{ route('profile') }}" wire:navigate class="mt-4 inline-block text-indigo-600 dark:text-indigo-400 underline">
                        Kelola profil akun
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
