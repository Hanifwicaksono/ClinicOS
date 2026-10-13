<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Pengaturan akun</p>
            <h1 class="mt-1 text-xl font-extrabold text-clinic-950">Profil</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="mb-7">
            <h2 class="text-2xl font-extrabold text-clinic-950">Kelola akun Anda</h2>
            <p class="mt-2 text-sm text-slate-500">Perbarui identitas, keamanan, dan akses akun ClinicOS.</p>
        </div>

        <section class="clinic-card p-6 sm:p-8">
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </section>

        <section class="clinic-card p-6 sm:p-8">
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </section>

        <section class="rounded-3xl border border-red-200 bg-white p-6 shadow-card sm:p-8">
            <div class="max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </section>
    </div>
</x-app-layout>
