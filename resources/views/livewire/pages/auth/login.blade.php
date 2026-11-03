<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    #[Locked]
    public bool $adminLogin = false;

    public function mount(bool $adminLogin = false): void
    {
        $this->adminLogin = $adminLogin || request()->routeIs('admin.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate($this->adminLogin);

        Session::regenerate();

        if ($intended = Session::get('url.intended')) {
            if (parse_url($intended, PHP_URL_HOST) !== parse_url(config('app.url'), PHP_URL_HOST)) {
                Session::forget('url.intended');
            }
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <h2 class="font-display text-2xl font-extrabold text-clinic-950 sm:text-3xl">{{ $adminLogin ? 'Masuk pengelola klinik' : 'Masuk pasien' }}</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $adminLogin ? 'Gunakan akun pemilik klinik atau staf Anda.' : 'Gunakan akun pasien Anda untuk melanjutkan kunjungan.' }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />
    @unless ($adminLogin)
        <x-input-error :messages="$errors->get('google')" class="mb-4" />

    <a href="{{ route('auth.google.redirect') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:border-clinic-400 hover:text-clinic-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2">
        Lanjutkan dengan Google
    </a>

    <div class="my-5 flex items-center gap-3" aria-hidden="true">
        <span class="h-px flex-1 bg-slate-200"></span>
        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">atau</span>
        <span class="h-px flex-1 bg-slate-200"></span>
    </div>
    @endunless

    <form wire:submit="login" class="space-y-5">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="mt-2 block w-full" type="email" name="email" required autofocus autocomplete="username" placeholder="nama@email.com" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Kata sandi" />
            <div class="relative mt-2" x-data="{ visible: false }">
                <x-text-input wire:model="form.password" id="password" class="block w-full !pr-14"
                                type="password" x-bind:type="visible ? 'text' : 'password'"
                                name="password" required autocomplete="current-password" />
                <button type="button" class="absolute inset-y-0 right-1 flex w-11 items-center justify-center rounded-lg text-clinic-700 transition hover:text-clinic-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-500"
                        @click="visible = !visible" x-bind:aria-label="visible ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'" x-bind:aria-pressed="visible.toString()" aria-label="Lihat kata sandi" aria-controls="password">
                    <svg x-show="!visible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg x-show="visible" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 3 21 21M10.6 6.1A10.9 10.9 0 0 1 12 6c6.25 0 9.75 6 9.75 6a13.6 13.6 0 0 1-3.1 3.55M6.2 7.65A13.5 13.5 0 0 0 2.25 12s3.5 6 9.75 6c1.1 0 2.13-.19 3.08-.52" />
                        <path d="M10.1 10.1a3 3 0 0 0 3.8 3.8" />
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-slate-300 text-clinic-500 focus:ring-clinic-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">Ingat saya</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm font-bold text-clinic-600 hover:text-clinic-700" href="{{ route('password.request', $adminLogin ? ['portal' => 'admin'] : []) }}" wire:navigate>
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <div>
            <x-primary-button class="w-full">
                Masuk
            </x-primary-button>
        </div>

        @if ($adminLogin)
            <p class="text-center text-sm text-slate-500">Belum punya akun klinik? <a href="{{ route('register.clinic') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Daftarkan klinik</a></p>
            <p class="text-center text-sm text-slate-500">Masuk sebagai pasien? <a href="{{ route('login') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Login pasien</a></p>
        @else
            <p class="text-center text-sm text-slate-500">Belum punya akun pasien? <a href="{{ route('register') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Daftar gratis</a></p>
            <p class="text-center text-sm text-slate-500">Mengelola klinik? <a href="{{ route('admin.login') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Login pengelola</a></p>
        @endif
    </form>
</div>
