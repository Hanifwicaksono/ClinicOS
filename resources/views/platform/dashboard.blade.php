<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Platform</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Dashboard platform</h1></div>
            <x-clinic-button href="{{ route('platform.tenants.index') }}" variant="secondary" class="hidden sm:inline-flex" wire:navigate>Cari tenant</x-clinic-button>
        </div>
    </x-slot>

    @php
        $attentionTotal = array_sum($attention);
        $subscriptionStatuses = [
            ['label' => 'Free', 'status' => \App\SubscriptionStatus::Free, 'color' => 'bg-slate-400'],
            ['label' => 'Trial', 'status' => \App\SubscriptionStatus::Trialing, 'color' => 'bg-amber-400'],
            ['label' => 'Aktif', 'status' => \App\SubscriptionStatus::Active, 'color' => 'bg-clinic-500'],
            ['label' => 'Expired', 'status' => \App\SubscriptionStatus::Expired, 'color' => 'bg-orange-500'],
            ['label' => 'Suspended', 'status' => \App\SubscriptionStatus::Suspended, 'color' => 'bg-red-500'],
        ];
    @endphp

    <section class="mb-6 overflow-hidden rounded-3xl bg-clinic-950 px-6 py-8 text-white sm:px-8">
        <div class="grid gap-7 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
                <p class="text-sm font-semibold text-clinic-200">Ringkasan ekosistem ClinicOS</p>
                <h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">{{ $metrics['activeTenants'] }} dari {{ $metrics['tenants'] }} tenant operasional</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Pantau kesehatan tenant, subscription, dan pembayaran tanpa membuka data medis klinik.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white/10 px-5 py-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-300">Subscription aktif</p><p class="mt-2 text-2xl font-extrabold">{{ $metrics['activeSubscriptions'] }}</p></div>
                <div class="rounded-2xl bg-white/10 px-5 py-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-300">Pengguna tenant</p><p class="mt-2 text-2xl font-extrabold">{{ $metrics['tenantUsers'] }}</p></div>
            </div>
        </div>
    </section>

    <section class="mb-6" aria-labelledby="attention-title">
        <div class="mb-4 flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wider text-clinic-600">Prioritas</p><h2 id="attention-title" class="mt-1 text-xl font-extrabold text-clinic-950">Perlu perhatian</h2></div><span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-clinic-100 text-clinic-700' => $attentionTotal === 0, 'bg-amber-100 text-amber-800' => $attentionTotal > 0])>{{ $attentionTotal === 0 ? 'Semua sehat' : $attentionTotal.' temuan' }}</span></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Tenant nonaktif', 'value' => $attention['inactiveTenants'], 'description' => 'Akun klinik tidak operasional', 'route' => route('platform.tenants.index'), 'tone' => 'bg-slate-100 text-slate-700'],
                ['label' => 'Owner belum dipetakan', 'value' => $attention['missingOwners'], 'description' => 'Tenant tanpa pemilik eksplisit', 'route' => route('platform.tenants.index'), 'tone' => 'bg-amber-100 text-amber-800'],
                ['label' => 'Berakhir ≤ 7 hari', 'value' => $attention['expiringSubscriptions'], 'description' => 'Subscription aktif atau trial', 'route' => route('platform.tenants.index'), 'tone' => 'bg-orange-100 text-orange-800'],
                ['label' => 'Pembayaran gagal', 'value' => $attention['failedPayments'], 'description' => 'Perlu pemeriksaan pembayaran', 'route' => route('platform.payments.index'), 'tone' => 'bg-red-100 text-red-700'],
            ] as $item)
                <a href="{{ $item['route'] }}" class="clinic-card group p-5 transition hover:-translate-y-0.5 hover:border-clinic-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500" wire:navigate>
                    <div class="flex items-center justify-between gap-3"><span class="rounded-xl px-2.5 py-1 text-xs font-bold {{ $item['tone'] }}">{{ $item['value'] }}</span><span class="text-clinic-500 transition group-hover:translate-x-0.5" aria-hidden="true">→</span></div>
                    <h3 class="mt-4 font-bold text-clinic-950">{{ $item['label'] }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ $item['description'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[.9fr_1.1fr]">
        <div class="clinic-card p-6">
            <div><p class="text-xs font-bold uppercase tracking-wider text-clinic-600">Kesehatan komersial</p><h2 class="mt-2 text-lg font-extrabold text-clinic-950">Distribusi subscription</h2><p class="mt-1 text-sm text-slate-500">Status subscription saat ini, terpisah dari status operasional tenant.</p></div>
            <div class="mt-6 space-y-4">
                @foreach($subscriptionStatuses as $item)
                    @php($count = (int) ($subscriptionCounts[$item['status']->value] ?? 0))
                    <div>
                        <div class="flex items-center justify-between text-sm"><span class="font-semibold text-slate-600">{{ $item['label'] }}</span><span class="font-extrabold text-clinic-950">{{ $count }}</span></div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $item['color'] }}" style="width: {{ $count > 0 && $metrics['tenants'] > 0 ? max(3, ($count / $metrics['tenants']) * 100) : 0 }}%"></div></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-6"><div><p class="text-xs font-semibold text-slate-500">Owner terpetakan</p><p class="mt-1 text-xl font-extrabold text-clinic-950">{{ $metrics['ownedTenants'] }}/{{ $metrics['tenants'] }}</p></div><div><p class="text-xs font-semibold text-slate-500">Cakupan owner</p><p class="mt-1 text-xl font-extrabold text-clinic-950">{{ $metrics['tenants'] > 0 ? round(($metrics['ownedTenants'] / $metrics['tenants']) * 100) : 0 }}%</p></div></div>
        </div>

        <div class="clinic-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h2 class="font-extrabold text-clinic-950">Tenant terbaru</h2><p class="mt-1 text-sm text-slate-500">Aktivitas onboarding tenant paling baru.</p></div><a href="{{ route('platform.tenants.index') }}" class="text-sm font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Lihat semua</a></div>
            <div class="divide-y divide-slate-100">
                @forelse($recentTenants as $tenant)
                    <a href="{{ route('platform.tenants.show', $tenant) }}" class="flex items-center gap-4 px-6 py-4 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-clinic-500" wire:navigate>
                        <span @class(['h-2.5 w-2.5 shrink-0 rounded-full', 'bg-clinic-500' => $tenant->is_active, 'bg-slate-300' => ! $tenant->is_active]) aria-hidden="true"></span>
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-900">{{ $tenant->name }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $tenant->owner?->email ?? 'Owner belum dipetakan' }}</p></div>
                        <div class="text-right"><p class="text-xs font-bold text-slate-600">{{ $tenant->subscription?->plan?->name ?? 'Belum ada paket' }}</p><p class="mt-1 text-xs text-slate-500">{{ $tenant->created_at->diffForHumans() }}</p></div>
                    </a>
                @empty
                    <div class="p-10 text-center"><p class="font-semibold text-slate-700">Belum ada tenant</p><p class="mt-1 text-sm text-slate-500">Tenant yang mendaftar akan muncul di sini.</p></div>
                @endforelse
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        <x-clinic-button href="{{ route('platform.tenants.index') }}" variant="primary" class="w-full" wire:navigate>Kelola tenant klinik</x-clinic-button>
        <x-clinic-button href="{{ route('platform.payments.index') }}" variant="secondary" class="w-full" wire:navigate>Lihat pembayaran</x-clinic-button>
    </div>
</x-app-layout>
