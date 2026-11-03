<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Platform</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Paket & harga</h1></div></x-slot>

    <div class="mb-7"><h2 class="text-2xl font-extrabold text-clinic-950">Katalog subscription</h2><p class="mt-2 text-sm text-slate-500">Harga baru berlaku untuk checkout berikutnya; invoice dan periode lama tetap memakai nominal tersimpan.</p></div>
    <x-input-error :messages="$errors->get('amount')" class="mb-5" />
    <x-input-error :messages="$errors->get('is_active')" class="mb-5" />

    <div class="space-y-5">
        @if ($addonPrice)
            <section class="clinic-card p-6">
                <h3 class="text-lg font-extrabold text-clinic-950">Add-on {{ $addonPrice->name }}</h3>
                <p class="mt-2 text-sm text-slate-500">Harga baru hanya berlaku untuk invoice berikutnya.</p>
                <form method="POST" action="{{ route('platform.addon-prices.update', $addonPrice) }}" class="mt-5 flex flex-wrap items-end gap-4">@csrf @method('PATCH')
                    <div><label class="clinic-label" for="monthly_amount">Harga per klinik per bulan (Rp)</label><input class="clinic-input mt-2" id="monthly_amount" name="monthly_amount" type="number" min="1" required value="{{ old('monthly_amount', $addonPrice->monthly_amount) }}"></div>
                    <div><label class="clinic-label" for="addon_is_active">Status</label><select class="clinic-input mt-2" id="addon_is_active" name="is_active"><option value="1" @selected($addonPrice->is_active)>Aktif</option><option value="0" @selected(! $addonPrice->is_active)>Nonaktif</option></select></div>
                    <x-clinic-button>Simpan add-on</x-clinic-button>
                </form>
            </section>
        @endif
        @foreach ($plans as $plan)
            <section class="clinic-card overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-6">
                    <div><div class="flex items-center gap-3"><h3 class="text-lg font-extrabold text-clinic-950">{{ $plan->name }}</h3><span class="{{ $plan->is_active ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><p class="mt-2 text-sm text-slate-500">Dokter {{ $plan->doctor_limit ?? 'tak terbatas' }} · Staf {{ $plan->staff_limit ?? 'tak terbatas' }} · Kunjungan {{ $plan->monthly_visit_limit ?? 'tak terbatas' }}</p></div>
                    <form method="POST" action="{{ route('platform.subscription-plans.update', $plan) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}"><x-clinic-button variant="{{ $plan->is_active ? 'danger' : 'secondary' }}">{{ $plan->is_active ? 'Nonaktifkan paket' : 'Aktifkan paket' }}</x-clinic-button></form>
                </div>

                @if ($plan->prices->isEmpty())
                    <p class="p-6 text-sm text-slate-500">Paket gratis tidak memerlukan harga checkout.</p>
                @else
                    <div class="overflow-x-auto"><table class="clinic-table w-full"><thead><tr><th>Durasi</th><th>Harga dan status</th><th class="text-right">Aksi</th></tr></thead><tbody>
                        @foreach ($plan->prices as $price)
                            <tr>
                                <td>{{ $price->duration_months }} bulan</td>
                                <td>
                                    <form id="price-{{ $price->id }}" method="POST" action="{{ route('platform.subscription-prices.update', $price) }}">@csrf @method('PATCH')<input type="hidden" name="price_id" value="{{ $price->id }}"></form>
                                    <div class="flex flex-wrap gap-3">
                                        <label class="text-xs font-bold text-slate-600">Harga (Rp)<input form="price-{{ $price->id }}" class="clinic-input mt-1 w-40" name="amount" type="number" min="1" max="999999999" required value="{{ old('price_id') == $price->id ? old('amount') : $price->amount }}"><span class="mt-1 block font-normal">Saat ini Rp {{ number_format($price->amount, 0, ',', '.') }}</span></label>
                                        <label class="text-xs font-bold text-slate-600">Status<select form="price-{{ $price->id }}" class="clinic-input mt-1" name="is_active"><option value="1" @selected((old('price_id') == $price->id ? old('is_active') : $price->is_active) == 1)>Aktif</option><option value="0" @selected((old('price_id') == $price->id ? old('is_active') : $price->is_active) == 0)>Nonaktif</option></select></label>
                                    </div>
                                </td>
                                <td class="text-right"><button form="price-{{ $price->id }}" class="font-bold text-clinic-600 hover:text-clinic-700">Simpan harga</button></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </section>
        @endforeach
    </div>
</x-app-layout>
