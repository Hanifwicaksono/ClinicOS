<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    #[Locked]
    public bool $adminLogin = false;

    public function mount(): void
    {
        $this->adminLogin = request()->query('portal') === 'admin';
    }

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Pemulihan akun</p>
        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Lupa kata sandi?</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Masukkan email akun Anda. Kami akan mengirimkan tautan untuk membuat kata sandi baru.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-5">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="mt-2 block w-full" type="email" name="email" required autofocus placeholder="nama@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-primary-button class="w-full">
                Kirim tautan reset
            </x-primary-button>
        </div>

        <p class="text-center text-sm text-slate-500"><a href="{{ route($adminLogin ? 'admin.login' : 'login') }}" wire:navigate class="font-bold text-clinic-600 hover:text-clinic-700">Kembali ke halaman masuk</a></p>
    </form>
</div>
