<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Selamat datang kembali</p>
        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Masuk ke ClinicOS</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan akun klinik atau akun pasien Anda.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="mt-2 block w-full" type="email" name="email" required autofocus autocomplete="username" placeholder="nama@email.com" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Kata sandi" />

            <x-text-input wire:model="form.password" id="password" class="mt-2 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-slate-300 text-clinic-500 focus:ring-clinic-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">Ingat saya</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm font-bold text-clinic-600 hover:text-clinic-700" href="{{ route('password.request') }}" wire:navigate>
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <div>
            <x-primary-button class="w-full">
                Masuk
            </x-primary-button>
        </div>

        <p class="text-center text-sm text-slate-500">Belum punya akun pasien? <a href="{{ route('register') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Daftar gratis</a></p>
    </form>
</div>
