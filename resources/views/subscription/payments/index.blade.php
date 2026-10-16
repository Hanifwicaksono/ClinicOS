<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Subscription</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Riwayat pembayaran</h1></div></x-slot>

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        @if ($platform)
            <form method="GET" class="flex w-full max-w-xl gap-3"><input class="clinic-input" name="search" value="{{ $search }}" placeholder="Cari invoice atau klinik"><x-clinic-button>Cari</x-clinic-button></form>
        @else
            <div><p class="text-sm text-slate-500">Redirect browser tidak mengaktifkan subscription. Status paid hanya berasal dari DOKU.</p><x-input-error :messages="$errors->get('payment')" class="mt-2" /></div>
            <x-clinic-button href="{{ route('onboarding.plans.index') }}" wire:navigate>Beli / perpanjang</x-clinic-button>
        @endif
    </div>

    <div class="clinic-card overflow-hidden"><div class="overflow-x-auto"><table class="clinic-table w-full"><thead><tr><th>Invoice</th>@if ($platform)<th>Klinik</th>@endif<th>Paket</th><th>Nominal</th><th>Status</th><th>Dibuat</th><th class="text-right">Aksi</th></tr></thead><tbody>
        @forelse ($payments as $payment)
            <tr><td class="font-bold text-clinic-700">{{ $payment->invoice_number }}</td>@if ($platform)<td class="font-semibold text-slate-800">{{ $payment->clinic->name }}</td>@endif<td>{{ $payment->plan_name }} · {{ $payment->duration_months }} bulan</td><td class="font-semibold">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td><td><span class="{{ $payment->status === \App\PaymentStatus::Paid ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ ucfirst($payment->status->value) }}</span></td><td>{{ $payment->created_at->format('d M Y H:i') }}</td><td class="text-right"><a href="{{ $platform ? route('platform.payments.show', $payment) : route('subscription.payments.show', $payment) }}" class="font-bold text-clinic-600" wire:navigate>Detail</a></td></tr>
        @empty
            <tr><td colspan="{{ $platform ? 7 : 6 }}" class="py-14 text-center text-slate-500">Belum ada pembayaran.</td></tr>
        @endforelse
    </tbody></table></div>@if ($payments->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $payments->links() }}</div>@endif</div>
</x-app-layout>
