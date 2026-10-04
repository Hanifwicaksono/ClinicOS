<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{ open: false }" data-user-notification-channel="users.{{ auth()->id() }}">
    <div class="fixed inset-x-0 top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3" wire:navigate>
            <x-application-logo class="h-9 w-9" />
            <span class="font-display text-lg font-extrabold text-clinic-950">Clinic<span class="text-clinic-500">OS</span></span>
        </a>
        <button type="button" @click="open = true" class="rounded-xl border border-slate-200 p-2 text-slate-600" aria-label="Buka navigasi">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-40 bg-clinic-950/40 lg:hidden"></div>

    <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-20 items-center justify-between border-b border-slate-100 px-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" wire:navigate>
                <x-application-logo class="h-11 w-11" />
                <div>
                    <div class="font-display text-xl font-extrabold tracking-tight text-clinic-950">Clinic<span class="text-clinic-500">OS</span></div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Clinic workspace</div>
                </div>
            </a>
            <button type="button" @click="open = false" class="p-2 text-slate-400 lg:hidden" aria-label="Tutup navigasi">✕</button>
        </div>

        <div class="border-b border-slate-100 px-6 py-5">
            <p class="truncate text-sm font-bold text-slate-900">{{ auth()->user()->clinic?->name ?? 'Siapkan klinik Anda' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ auth()->user()->roles->first()?->name ?? 'Pengguna' }}</p>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
            <p class="px-3 pb-2 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Workspace</p>
            @php
                $links = auth()->user()->hasRole('Clinic Admin')
                    ? [
                        ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Ringkasan', 'icon' => 'M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z'],
                        ['route' => 'admin.clinic.index', 'match' => 'admin.clinic.*', 'label' => 'Profil klinik', 'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 10h2m2 0h2m-6 4h2m2 0h2m-5 7v-3h4v3'],
                        ['route' => 'admin.staff.index', 'match' => 'admin.staff.*', 'label' => 'Tim klinik', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-12a4 4 0 0 1 0 7.75'],
                        ['route' => 'admin.services.index', 'match' => 'admin.services.*', 'label' => 'Layanan', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-10h6m-3-3v6'],
                        ['route' => 'admin.doctor-schedules.index', 'match' => 'admin.doctor-schedules.*', 'label' => 'Jadwal dokter', 'icon' => 'M3 9h18M7 3v4m10-4v4M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm3 8h3v3H8v-3Z'],
                        ['route' => 'receptionist.patients.index', 'match' => 'receptionist.patients.*', 'label' => 'Pasien', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                        ['route' => 'receptionist.appointments.index', 'match' => 'receptionist.appointments.*', 'label' => 'Booking & antrean', 'icon' => 'M6 3v3m12-3v3M4 9h16M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm4 8h6'],
                        ['route' => 'receptionist.queues.index', 'match' => '*.queues.*', 'label' => 'Antrean hari ini', 'icon' => 'M5 6h14M5 12h14M5 18h9'],
                        ['route' => 'admin.audit-logs.index', 'match' => 'admin.audit-logs.*', 'label' => 'Audit aktivitas', 'icon' => 'M4 4h16v16H4V4Zm4 5h8m-8 4h8m-8 4h5'],
                    ]
                    : (auth()->user()->hasRole('Receptionist') ? [
                        ['route' => 'receptionist.dashboard', 'match' => 'receptionist.dashboard', 'label' => 'Ringkasan', 'icon' => 'M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z'],
                        ['route' => 'receptionist.patients.index', 'match' => 'receptionist.patients.*', 'label' => 'Pasien', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                        ['route' => 'receptionist.appointments.index', 'match' => 'receptionist.appointments.*', 'label' => 'Booking & antrean', 'icon' => 'M6 3v3m12-3v3M4 9h16M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm4 8h6'],
                        ['route' => 'receptionist.queues.index', 'match' => '*.queues.*', 'label' => 'Antrean hari ini', 'icon' => 'M5 6h14M5 12h14M5 18h9'],
                    ] : (auth()->user()->hasRole('Doctor') ? [
                        ['route' => 'doctor.dashboard', 'match' => 'doctor.dashboard', 'label' => 'Dashboard', 'icon' => 'M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z'],
                        ['route' => 'doctor.queues.index', 'match' => 'doctor.queues.*', 'label' => 'Antrean saya', 'icon' => 'M5 6h14M5 12h14M5 18h9'],
                    ] : (auth()->user()->hasRole('Patient') ? [
                        ['route' => 'patient.dashboard', 'match' => 'patient.dashboard', 'label' => 'Dashboard', 'icon' => 'M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z'],
                        ['route' => 'patient.appointments.index', 'match' => 'patient.appointments.*', 'label' => 'Booking saya', 'icon' => 'M6 3v3m12-3v3M4 9h16M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
                    ] : [
                        ['route' => 'dashboard', 'match' => '*.dashboard', 'label' => 'Dashboard', 'icon' => 'M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z'],
                    ])));
            @endphp

            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}" wire:navigate @click="open = false" @class([
                    'flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition',
                    'bg-clinic-50 text-clinic-700' => request()->routeIs($link['match']),
                    'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs($link['match']),
                ])>
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $link['icon'] }}"/></svg>
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ route('notifications.index') }}" wire:navigate @click="open = false" @class(['flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition', 'bg-clinic-50 text-clinic-700' => request()->routeIs('notifications.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('notifications.*')])>
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <span class="flex-1">Notifikasi</span>
                <span data-notification-count @class(['rounded-full bg-clinic-500 px-2 py-0.5 text-[10px] font-bold text-white', 'hidden' => auth()->user()->unreadNotifications()->count() === 0])>{{ auth()->user()->unreadNotifications()->count() }}</span>
            </a>
        </nav>

        <div class="border-t border-slate-100 p-4">
            <a href="{{ route('profile') }}" wire:navigate class="mb-2 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-clinic-100 font-bold text-clinic-700">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                <span class="min-w-0 flex-1"><span class="block truncate text-slate-900">{{ auth()->user()->name }}</span><span class="block truncate text-xs font-normal text-slate-500">{{ auth()->user()->email }}</span></span>
            </a>
            <button wire:click="logout" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-red-50 hover:text-red-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 17l5-5-5-5M15 12H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg>
                Keluar
            </button>
        </div>
    </aside>

    <div class="h-16 lg:hidden"></div>
</div>
