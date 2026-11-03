<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Subscription</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Add-on Odontogram</h1></div></x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <section class="clinic-card p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="text-xl font-extrabold text-clinic-950">Odontogram untuk klinik</h2><p class="mt-2 text-sm leading-6 text-slate-600">RME General tersedia pada semua paket. Odontogram opsional untuk Individual dan Team berbayar.</p></div>
                <span class="{{ $active ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ $active ? 'Aktif' : 'Belum aktif' }}</span>
            </div>
            <p class="mt-5 text-2xl font-extrabold text-clinic-700">{{ $price ? 'Rp '.number_format($price->monthly_amount, 0, ',', '.') : 'Belum tersedia' }}<span class="text-sm font-medium text-slate-500"> / klinik / bulan</span></p>
            <p class="mt-2 text-sm text-slate-500">Dibayar sekali untuk sisa periode paket utama. Tidak diperpanjang otomatis.</p>
            <x-input-error :messages="$errors->get('addon')" class="mt-4" />

            @if ($active)
                <p class="mt-6 rounded-xl border border-clinic-200 bg-clinic-50 p-4 text-sm font-semibold text-clinic-800" role="status">Odontogram dapat digunakan dokter yang ditugaskan sampai akhir periode paket utama.</p>
            @elseif ($pendingPayment)
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950" role="status"><p class="font-semibold">{{ $pendingPayment->status === \App\PaymentStatus::Review ? 'Pembayaran perlu ditinjau sebelum add-on aktif.' : 'Invoice add-on masih menunggu pembayaran.' }}</p><a class="mt-3 inline-block font-bold underline" href="{{ route('subscription.payments.show', $pendingPayment) }}">Lihat invoice {{ $pendingPayment->invoice_number }}</a></div>
            @elseif ($quote)
                <div class="mt-6 rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-clinic-950">Ringkasan checkout</h3>
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Mulai setelah pembayaran sah</dt><dd class="font-semibold text-slate-900">{{ $quote['starts_at']->timezone($quote['period']->clinic->settings?->timezone ?? config('app.timezone'))->format('d M Y') }}</dd></div>
                        <div><dt class="text-slate-500">Berakhir bersama paket utama</dt><dd class="font-semibold text-slate-900">{{ $quote['ends_at']->subSecond()->timezone($quote['period']->clinic->settings?->timezone ?? config('app.timezone'))->format('d M Y') }}</dd></div>
                    </dl>
                    <ul class="mt-4 space-y-1 text-sm text-slate-600">
                        @foreach ($quote['breakdown'] as $month)
                            <li>{{ $month['month'] }}: {{ $month['days'] }}/{{ $month['days_in_month'] }} hari × Rp {{ number_format($price->monthly_amount, 0, ',', '.') }} = Rp {{ number_format($month['subtotal'], 2, ',', '.') }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-xl font-extrabold text-clinic-700">Total prorata Rp {{ number_format($quote['amount'], 0, ',', '.') }}</p>
                    <form method="POST" action="{{ route('subscription.addons.store') }}" class="mt-5">@csrf<x-clinic-button>Bayar add-on di DOKU</x-clinic-button></form>
                </div>
            @else
                <p class="mt-6 rounded-xl border border-slate-200 p-4 text-sm text-slate-600" role="status">{{ $reason ?? 'Add-on belum tersedia untuk dibeli.' }}</p>
                <x-clinic-button href="{{ route('onboarding.plans.index') }}" variant="secondary" class="mt-4">Lihat paket</x-clinic-button>
            @endif
        </section>

        <section class="clinic-card overflow-hidden">
            <div class="border-b border-slate-100 p-6"><h2 class="font-bold text-clinic-950">Riwayat add-on</h2></div>
            <div class="overflow-x-auto"><table class="clinic-table w-full"><thead><tr><th>Periode</th><th>Nominal</th><th>Status</th><th>Invoice</th></tr></thead><tbody>
                @forelse ($periods as $period)
                    <tr><td>{{ $period->starts_at->format('d M Y') }} – {{ $period->ends_at->subSecond()->format('d M Y') }}</td><td>Rp {{ number_format($period->amount, 0, ',', '.') }}</td><td>{{ $period->revoked_at ? 'Refunded' : ($period->ends_at->isPast() ? 'Berakhir' : 'Aktif') }}</td><td><a class="font-bold text-clinic-600" href="{{ route('subscription.payments.show', $period->payment) }}">Lihat invoice</a></td></tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada add-on yang aktif atau pernah dibeli.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </div>
</x-app-layout>
