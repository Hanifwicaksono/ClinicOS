<?php

use App\Models\User;
use App\Services\RegistrationRateLimiter;
use App\UserAccountType;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $validated['account_type'] = UserAccountType::ClinicOwner;
        $validated['terms_accepted_at'] = now();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create($validated);
            $user->assignRole(Role::firstOrCreate(['name' => 'Clinic Admin', 'guard_name' => 'web']));

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Pemilik klinik</p>
        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Daftarkan klinik Anda</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Buat akun pemilik, verifikasi email, lalu lengkapi profil klinik dan pilih paket.</p>
    </div>

    <form wire:submit="register" class="space-y-5">
        <div><x-input-label for="name" value="Nama pemilik" /><x-text-input wire:model="name" id="name" class="mt-2 block w-full" type="text" required autofocus autocomplete="name" /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
        <div><x-input-label for="email" value="Email" /><x-text-input wire:model="email" id="email" class="mt-2 block w-full" type="email" required autocomplete="username" placeholder="pemilik@klinik.com" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
        <div><x-input-label for="password" value="Kata sandi" /><x-text-input wire:model="password" id="password" class="mt-2 block w-full" type="password" required autocomplete="new-password" /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
        <div><x-input-label for="password_confirmation" value="Ulangi kata sandi" /><x-text-input wire:model="password_confirmation" id="password_confirmation" class="mt-2 block w-full" type="password" required autocomplete="new-password" /><x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" /></div>
        <div><label for="accept_terms" class="flex items-start gap-3 text-sm leading-6 text-slate-600"><input wire:model="accept_terms" id="accept_terms" type="checkbox" class="mt-1 rounded border-slate-300 text-clinic-500 focus:ring-clinic-500" required><span>Saya menyetujui syarat dan ketentuan serta kebijakan privasi ClinicOS.</span></label><x-input-error :messages="$errors->get('accept_terms')" class="mt-2" /></div>
        <x-primary-button class="w-full">Buat akun pemilik</x-primary-button>
        <p class="text-center text-sm text-slate-500">Mendaftar sebagai pasien? <a href="{{ route('register') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Buat akun pasien</a></p>
    </form>
</div>
