<?php

use App\Models\User;
use App\Services\RegistrationRateLimiter;
use App\UserAccountType;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $accept_terms = false;

    public function registerWithGoogle(): void
    {
        $this->validate(['accept_terms' => ['accepted']]);
        session()->put('auth.google.terms_accepted_at', now()->toIso8601String());

        $this->redirect(route('auth.google.redirect', ['register' => 1]), navigate: false);
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(RegistrationRateLimiter $rateLimiter): void
    {
        $rateLimiter->hit($this->email);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'accept_terms' => ['accepted'],
        ]);

        unset($validated['accept_terms']);
        $validated['password'] = Hash::make($validated['password']);
        $validated['account_type'] = UserAccountType::Patient;
        $validated['terms_accepted_at'] = now();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create($validated);
            $user->assignRole(Role::firstOrCreate(['name' => 'Patient', 'guard_name' => 'web']));

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Akun pasien</p>
        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Buat akun pasien</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Simpan akses Anda untuk memantau pendaftaran dan kunjungan.</p>
    </div>

    <form wire:submit="register" class="space-y-5">
        <div>
            <x-input-label for="name" value="Nama lengkap" />
            <x-text-input wire:model="name" id="name" class="mt-2 block w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="mt-2 block w-full" type="email" name="email" required autocomplete="username" placeholder="nama@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Kata sandi" />

            <x-text-input wire:model="password" id="password" class="mt-2 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Ulangi kata sandi" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="mt-2 block w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div>
            <label for="accept_terms" class="flex items-start gap-3 text-sm leading-6 text-slate-600">
                <input wire:model="accept_terms" id="accept_terms" type="checkbox" class="mt-1 rounded border-slate-300 text-clinic-500 focus:ring-clinic-500" required>
                <span>Saya menyetujui syarat dan ketentuan serta kebijakan privasi ClinicOS.</span>
            </label>
            <x-input-error :messages="$errors->get('accept_terms')" class="mt-2" />
        </div>

        <div>
            <x-primary-button class="w-full">
                Buat akun
            </x-primary-button>
        </div>

        <div class="flex items-center gap-3" aria-hidden="true">
            <span class="h-px flex-1 bg-slate-200"></span>
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">atau</span>
            <span class="h-px flex-1 bg-slate-200"></span>
        </div>

        <button type="button" wire:click="registerWithGoogle" wire:loading.attr="disabled" wire:target="registerWithGoogle" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:border-clinic-400 hover:text-clinic-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 focus-visible:ring-offset-2 disabled:opacity-60">
            Daftar dengan Google
        </button>

        <p class="text-center text-sm text-slate-500">Mengelola klinik? <a href="{{ route('register.clinic') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Daftarkan klinik</a></p>
        <p class="text-center text-sm text-slate-500">Sudah punya akun? <a href="{{ route('login') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Masuk</a></p>
    </form>
</div>
