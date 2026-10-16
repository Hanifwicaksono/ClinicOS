<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Platform</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Dashboard platform</h1></div></x-slot>

    <section class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['label' => 'Total tenant', 'value' => $metrics['tenants']],
            ['label' => 'Tenant aktif', 'value' => $metrics['activeTenants']],
            ['label' => 'Owner terpetakan', 'value' => $metrics['ownedTenants']],
            ['label' => 'Pengguna tenant', 'value' => $metrics['tenantUsers']],
            ['label' => 'Subscription aktif', 'value' => $metrics['activeSubscriptions']],
        ] as $metric)
            <article class="clinic-card p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $metric['label'] }}</p><p class="mt-4 font-display text-3xl font-extrabold text-clinic-950">{{ $metric['value'] }}</p></article>
        @endforeach
    </section>

    <x-clinic-button href="{{ route('platform.tenants.index') }}" variant="primary" wire:navigate>Lihat tenant klinik</x-clinic-button>
</x-app-layout>
