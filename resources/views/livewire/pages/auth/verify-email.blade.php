<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Satu langkah lagi</p>
        <h2 class="mt-2 text-2xl font-extrabold text-clinic-950">Verifikasi email Anda</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Buka tautan verifikasi yang kami kirimkan agar akun Anda dapat mengakses ClinicOS.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div role="status" class="mb-5 rounded-2xl border border-clinic-200 bg-clinic-50 px-4 py-3 text-sm font-semibold text-clinic-700">
            Tautan verifikasi baru telah dikirim ke email Anda.
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-primary-button wire:click="sendVerification" class="sm:flex-1">
            Kirim ulang email verifikasi
        </x-primary-button>

        <button wire:click="logout" type="submit" class="clinic-button-secondary">
            Keluar
        </button>
    </div>
</div>
