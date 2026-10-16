<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Platform</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Paket & harga</h1></div></x-slot>

    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Katalog subscription</h2><p class="mt-2 text-sm text-slate-500">Nonaktifkan paket atau durasi tanpa mengubah histori subscription lama.</p></div>

    <div class="space-y-5">
        @foreach ($plans as $plan)
            <section class="clinic-card overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-6">
                    <div><div class="flex items-center gap-3"><h3 class="text-lg font-extrabold text-clinic-950">{{ $plan->name }}</h3><span class="{{ $plan->is_active ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><p class="mt-2 text-sm text-slate-500">Dokter {{ $plan->doctor_limit ?? 'tak terbatas' }} · Staf {{ $plan->staff_limit ?? 'tak terbatas' }} · Kunjungan {{ $plan->monthly_visit_limit ?? 'tak terbatas' }}</p></div>
                    <form method="POST" action="{{ route('platform.subscription-plans.update', $plan) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}"><x-clinic-button variant="{{ $plan->is_active ? 'danger' : 'secondary' }}">{{ $plan->is_active ? 'Nonaktifkan paket' : 'Aktifkan paket' }}</x-clinic-button></form>
                </div>

                @if ($plan->prices->isEmpty())
                    <p class="p-6 text-sm text-slate-500">Paket gratis tidak memerlukan harga checkout.</p>
                @else
                    <div class="overflow-x-auto"><table class="clinic-table w-full"><thead><tr><th>Durasi</th><th>Harga</th><th>Status</th><th class="text-right">Aksi</th></tr></thead><tbody>
                        @foreach ($plan->prices as $price)
                            <tr><td>{{ $price->duration_months }} bulan</td><td class="font-semibold text-slate-900">Rp {{ number_format($price->amount, 0, ',', '.') }}</td><td><span class="{{ $price->is_active ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ $price->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-right"><form method="POST" action="{{ route('platform.subscription-prices.update', $price) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $price->is_active ? 0 : 1 }}"><button class="font-bold text-clinic-600 hover:text-clinic-700">{{ $price->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </section>
        @endforeach
    </div>
</x-app-layout>
