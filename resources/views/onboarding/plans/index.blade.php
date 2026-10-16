<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Subscription</p><h1 class="mt-1 text-xl font-extrabold text-clinic-950">Pilih paket klinik</h1></div></x-slot>

    <div class="mx-auto max-w-7xl">
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div class="max-w-2xl"><h2 class="text-3xl font-extrabold text-clinic-950">{{ $subscription ? 'Perpanjang akses ClinicOS' : 'Aktifkan akses ClinicOS' }}</h2><p class="mt-3 text-sm leading-7 text-slate-500">Mulai gratis, coba kapasitas Team selama 14 hari, atau pilih durasi paket berbayar.</p></div>
            <x-clinic-button href="{{ route('subscription.payments.index') }}" variant="secondary" wire:navigate>Riwayat pembayaran</x-clinic-button>
        </div>

        <x-input-error :messages="$errors->get('trial')" class="mb-5" />
        <x-input-error :messages="$errors->get('checkout')" class="mb-5" />
        <div class="grid gap-5 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <section class="clinic-card flex flex-col p-6">
                    <div><div class="flex items-center justify-between gap-3"><h3 class="text-xl font-extrabold text-clinic-950">{{ $plan->name }}</h3>@if ($plan->code === 'team')<span class="clinic-status-active">Akun tak terbatas</span>@endif</div><p class="mt-4 text-sm leading-6 text-slate-500">{{ $plan->doctor_limit === null ? 'Dokter tak terbatas' : $plan->doctor_limit.' dokter' }} · {{ $plan->staff_limit === null ? 'Staf tak terbatas' : $plan->staff_limit.' staf' }} · {{ $plan->monthly_visit_limit === null ? 'Kunjungan tak terbatas' : $plan->monthly_visit_limit.' kunjungan/bulan' }}</p></div>

                    @if ($plan->code === 'free')
                        <div class="mt-auto pt-8"><p class="mb-4 text-2xl font-extrabold text-clinic-950">Rp0</p>@if ($subscription)<p class="text-sm text-slate-500">Free hanya tersedia saat aktivasi awal.</p>@else<form method="POST" action="{{ route('onboarding.plans.free') }}">@csrf<x-clinic-button class="w-full">Gunakan Free</x-clinic-button></form>@endif</div>
                    @else
                        <div class="mt-7 space-y-3">@foreach ($plan->prices as $price)<form method="POST" action="{{ route('onboarding.plans.paid', $price) }}" x-data="{ submitting: false }" @submit="submitting = true">@csrf<button class="flex w-full items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:border-clinic-300 hover:bg-clinic-50 disabled:opacity-60" x-bind:disabled="submitting"><span class="font-semibold text-slate-700">{{ $price->duration_months }} bulan</span><span class="font-extrabold text-clinic-700">Rp {{ number_format($price->amount, 0, ',', '.') }}</span></button></form>@endforeach</div>
                        @if ($plan->code === 'team' && ! $subscription)<form method="POST" action="{{ route('onboarding.plans.trial') }}" class="mt-4">@csrf<x-clinic-button variant="secondary" class="w-full">Coba Team 14 hari</x-clinic-button></form>@endif
                    @endif
                </section>
            @endforeach
        </div>
        <p class="mt-6 text-center text-xs text-slate-500">Harga adalah total pembayaran. Akses berbayar aktif hanya setelah notification DOKU terverifikasi.</p>
    </div>
</x-app-layout>
