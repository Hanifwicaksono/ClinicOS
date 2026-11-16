<?php

use App\Livewire\Actions\Logout;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordRevision;
use App\Models\Queue;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        DB::transaction(function () use ($logout): void {
            $user = User::query()->lockForUpdate()->findOrFail(Auth::id());
            if ($user->ownedClinic()->exists()) {
                throw ValidationException::withMessages([
                    'password' => 'Akun pemilik tidak dapat dihapus selama masih memiliki klinik. Hubungi pengelola platform untuk penanganan kepemilikan.',
                ]);
            }
            $doctor = $user->doctor()->lockForUpdate()->first();
            $hasDoctorHistory = $doctor !== null && ($doctor->appointments()->exists()
                || $doctor->visits()->exists()
                || Queue::query()->where('doctor_id', $doctor->id)->exists()
                || MedicalRecord::query()->where('doctor_id', $doctor->id)->exists());
            if ($hasDoctorHistory
                || Visit::query()->where('started_by_user_id', $user->id)->exists()
                || MedicalRecord::query()->where('created_by_user_id', $user->id)->exists()
                || MedicalRecordRevision::query()->where('corrected_by_user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'password' => 'Akun terhubung dengan riwayat pelayanan dan tidak dapat dihapus. Hubungi admin klinik untuk menonaktifkan akses.',
                ]);
            }
            $logout();
            $user->delete();
        }, 3);

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
        @if (auth()->user()->ownedClinic()->exists())
            <p class="mt-2 text-sm font-semibold text-amber-800">Akun pemilik tidak dapat dihapus selama masih memiliki klinik.</p>
        @endif
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
