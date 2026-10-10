<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-clinic-950">
            {{ __('Profil Pasien') }}
        </h2>
    </x-slot>

    <div class="pt-4 pb-12">
        <div class="mx-auto max-w-3xl space-y-14 sm:px-6 lg:px-4">
            <div>
                <livewire:profile.update-profile-information-form />
            </div>

            <div>
                <livewire:profile.update-password-form />
            </div>

            <div>
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
