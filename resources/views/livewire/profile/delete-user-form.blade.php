<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="max-w-3xl space-y-6">
    <header>
        <h2 class="text-lg font-medium text-clinic-950">
            {{ __('Hapus Akun') }}
        </h2>

        <p class="mt-1 text-sm text-clinic-700">
            {{ __('Setelah akun anda dihapus, semua data akan dihapus secara permanen.') }}
        </p>
    </header>

    <x-clinic-button
        type="button"
        variant="danger"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Hapus Akun') }}</x-clinic-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-medium text-clinic-950">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-clinic-700">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only text-clinic-950" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4 border-clinic-200 bg-white text-clinic-950 focus:border-clinic-500 focus:ring-clinic-500"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-clinic-button type="button" variant="secondary" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-clinic-button>

                <x-clinic-button type="submit" variant="danger" class="ms-3">
                    {{ __('Delete Account') }}
                </x-clinic-button>
            </div>
        </form>
    </x-modal>
</section>
