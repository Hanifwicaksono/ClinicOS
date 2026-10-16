<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Platform</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Tenant klinik</h1></div></x-slot>

    <form method="GET" class="clinic-card mb-6 flex gap-3 p-5">
        <div class="flex-1"><label class="clinic-label" for="search">Cari tenant</label><input id="search" class="clinic-input" name="search" value="{{ $search }}" placeholder="Nama, slug, atau email owner"></div>
        <x-clinic-button variant="secondary" class="self-end">Cari</x-clinic-button>
    </form>

    <section class="clinic-card overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Klinik</th><th class="px-5 py-4">Owner</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Paket</th><th class="px-5 py-4">Dokter</th><th class="px-5 py-4">Akun tenant</th><th class="px-5 py-4"></th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse ($tenants as $tenant)
                <tr><td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $tenant->name }}</p><p class="text-xs text-slate-400">{{ $tenant->slug }}</p></td><td class="px-5 py-4"><p class="text-slate-700">{{ $tenant->owner?->name ?? 'Belum dipetakan' }}</p><p class="text-xs text-slate-400">{{ $tenant->owner?->email }}</p></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $tenant->is_active ? 'bg-clinic-50 text-clinic-700' : 'bg-red-50 text-red-700' }}">{{ $tenant->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="px-5 py-4"><p class="font-semibold text-slate-700">{{ $tenant->subscription?->plan?->name ?? 'Belum ada' }}</p><p class="text-xs text-slate-400">{{ $tenant->subscription?->status?->value }}</p></td><td class="px-5 py-4 text-slate-600">{{ $tenant->doctors_count }}</td><td class="px-5 py-4 text-slate-600">{{ $tenant->users_count }}</td><td class="px-5 py-4 text-right"><a href="{{ route('platform.tenants.show', $tenant) }}" class="font-bold text-clinic-600 hover:text-clinic-700" wire:navigate>Detail</a></td></tr>
            @empty
                <tr><td colspan="7" class="p-14 text-center text-slate-500">Tenant tidak ditemukan.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
    @if ($tenants->hasPages())<div class="mt-6">{{ $tenants->links() }}</div>@endif
</x-app-layout>
