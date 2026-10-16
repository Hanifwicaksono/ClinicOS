<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Invoice</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">{{ $payment->invoice_number }}</h1></div></x-slot>

    @if (session('status'))<div class="mb-5 rounded-xl border border-clinic-200 bg-clinic-50 px-5 py-4 text-sm font-semibold text-clinic-800">{{ session('status') }}</div>@endif
    <x-input-error :messages="$errors->get('payment')" class="mb-5" />

    <div class="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
        <section class="clinic-card p-7"><div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wider text-clinic-600">{{ $platform ? $payment->clinic->name : 'Pilihan Anda' }}</p><h2 class="mt-2 text-2xl font-extrabold text-clinic-950">{{ $payment->plan_name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $payment->duration_months }} bulan</p></div><span class="{{ $payment->status === \App\PaymentStatus::Paid ? 'clinic-status-active' : 'clinic-status-inactive' }}">{{ ucfirst($payment->status->value) }}</span></div><p class="mt-7 text-3xl font-extrabold text-clinic-700">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p><dl class="mt-7 grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-slate-400">Dibuat</dt><dd class="mt-1 font-semibold">{{ $payment->created_at->format('d M Y H:i') }}</dd></div><div><dt class="text-slate-400">Kedaluwarsa</dt><dd class="mt-1 font-semibold">{{ $payment->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}</dd></div><div><dt class="text-slate-400">Dibayar</dt><dd class="mt-1 font-semibold">{{ $payment->paid_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}</dd></div><div><dt class="text-slate-400">Provider</dt><dd class="mt-1 font-semibold uppercase">{{ $payment->provider }}</dd></div></dl></section>

        <section class="clinic-card p-7"><h2 class="font-extrabold text-clinic-950">Tindakan</h2><div class="mt-5 space-y-3">
            @if ($payment->status === \App\PaymentStatus::Pending && $payment->payment_url)
                <x-clinic-button type="button" id="doku-checkout-button" class="w-full">Bayar di DOKU</x-clinic-button>
                <form method="POST" action="{{ $platform ? route('platform.payments.reconcile', $payment) : route('subscription.payments.reconcile', $payment) }}">@csrf<x-clinic-button variant="secondary" class="w-full">Periksa status DOKU</x-clinic-button></form>
                <p class="text-xs leading-5 text-slate-500">Kembali dari DOKU hanya menampilkan halaman ini. Subscription aktif setelah notification sah atau hasil rekonsiliasi terverifikasi.</p>
            @elseif ($payment->status === \App\PaymentStatus::Failed)
                <p class="text-sm leading-6 text-red-700">Checkout gagal dibuat. Buat invoice baru dari halaman paket.</p>
            @else
                <p class="text-sm leading-6 text-slate-500">Tidak ada tindakan pembayaran untuk status ini.</p>
            @endif
            @if ($platform && $payment->status === \App\PaymentStatus::Paid)
                <form method="POST" action="{{ route('platform.payments.refund', $payment) }}" class="border-t border-slate-100 pt-4">@csrf<label class="clinic-label" for="reason">Catat refund manual</label><textarea id="reason" name="reason" class="clinic-input mt-2 min-h-24" maxlength="1000" required>{{ old('reason') }}</textarea><x-input-error :messages="$errors->get('reason')" class="mt-2" /><x-clinic-button variant="danger" class="mt-3 w-full">Tandai refunded</x-clinic-button><p class="mt-2 text-xs text-slate-500">Gunakan setelah refund diproses di DOKU. Periode subscription tidak dihapus.</p></form>
            @endif
        </div></section>
    </div>

    @if ($platform)
        <section class="clinic-card mt-6 overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="font-extrabold text-clinic-950">Event pembayaran</h2></div><div class="overflow-x-auto"><table class="clinic-table w-full"><thead><tr><th>Waktu</th><th>Sumber</th><th>Status DOKU</th><th>Hasil</th></tr></thead><tbody>@forelse ($payment->events as $event)<tr><td>{{ $event->received_at->format('d M Y H:i:s') }}</td><td>{{ $event->source }}</td><td>{{ $event->provider_status ?? '—' }}</td><td>{{ $event->result }}</td></tr>@empty<tr><td colspan="4" class="py-10 text-center text-slate-500">Belum ada event.</td></tr>@endforelse</tbody></table></div></section>
    @endif

    <div class="mt-6"><x-clinic-button href="{{ $platform ? route('platform.payments.index') : route('subscription.payments.index') }}" variant="secondary" wire:navigate>Kembali</x-clinic-button></div>

    @if ($payment->status === \App\PaymentStatus::Pending && $payment->payment_url)
        <script src="{{ config('services.doku.base_url') === 'https://api.doku.com' ? 'https://jokul.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js' : 'https://sandbox.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js' }}"></script>
        <script>
            document.getElementById('doku-checkout-button')?.addEventListener('click', () => {
                window.loadJokulCheckout(@js($payment->payment_url));
            });
        </script>
    @endif
</x-app-layout>
