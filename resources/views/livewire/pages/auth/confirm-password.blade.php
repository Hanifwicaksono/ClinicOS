<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Area aman</p>
        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Konfirmasi kata sandi</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Masukkan kembali kata sandi Anda sebelum melanjutkan.</p>
    </div>

    <form wire:submit="confirmPassword" class="space-y-5">
        <div>
            <x-input-label for="password" value="Kata sandi" />

            <x-text-input wire:model="password"
                          id="password"
                          class="mt-2 block w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-primary-button class="w-full">
                Konfirmasi
            </x-primary-button>
        </div>
    </form>
</div>
