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
        <h2 class="text-lg font-extrabold text-red-700">
            Hapus akun
        </h2>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            Penghapusan akun bersifat permanen. Pastikan data yang masih diperlukan sudah disimpan sebelum melanjutkan.
        </p>
    </header>

    <x-clinic-button
        type="button"
        variant="danger"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Hapus akun</x-clinic-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" label="Konfirmasi penghapusan akun" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-extrabold text-clinic-950">
                Yakin ingin menghapus akun?
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                Semua data akun akan dihapus permanen. Masukkan kata sandi untuk mengonfirmasi tindakan ini.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Kata sandi" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-2 block w-full"
                    placeholder="Kata sandi"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <x-clinic-button type="button" variant="secondary" x-on:click="$dispatch('close')">
                    Batal
                </x-clinic-button>

                <x-clinic-button type="submit" variant="danger">
                    Hapus akun permanen
                </x-clinic-button>
            </div>
        </form>
    </x-modal>
</section>
