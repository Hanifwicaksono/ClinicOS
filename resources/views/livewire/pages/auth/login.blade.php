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
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Selamat datang kembali</p>
        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">{{ $adminLogin ? 'Masuk pengelola klinik' : 'Masuk pasien' }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">{{ $adminLogin ? 'Gunakan akun pemilik klinik atau staf Anda.' : 'Gunakan akun pasien Anda untuk booking dan melihat dashboard.' }}</p>
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
