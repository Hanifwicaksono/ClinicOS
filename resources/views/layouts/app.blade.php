<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ClinicOS') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=plus-jakarta-sans:600,700,800" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen">
            <livewire:layout.navigation />

            <div class="lg:pl-72">
                @if (isset($header))
                    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur-sm">
                        <div class="mx-auto flex min-h-20 max-w-[1440px] items-center px-4 sm:px-6 lg:px-10">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main class="mx-auto max-w-[1440px] px-4 py-7 sm:px-6 lg:px-10 lg:py-10">
                    @php
                        $subscriptionOverview = auth()->check()
                            ? app(\App\Services\SubscriptionService::class)->overviewFor(auth()->user())
                            : null;
                    @endphp

                    @if ($subscriptionOverview !== null)
                        @php
                            $subscriptionMessage = match ($subscriptionOverview['status']) {
                                \App\SubscriptionStatus::Trialing => "Trial {$subscriptionOverview['plan_name']} aktif, tersisa {$subscriptionOverview['remaining_days']} hari.",
                                \App\SubscriptionStatus::Free => $subscriptionOverview['seat_overage']
                                    ? (auth()->user()->isClinicOwner()
                                        ? 'Paket Free aktif. Nonaktifkan akun tambahan sebelum melanjutkan operasional.'
                                        : 'Paket Free melewati batas akun. Hubungi pemilik klinik.')
                                    : 'Paket Free aktif.',
                                \App\SubscriptionStatus::Expired => 'Subscription berakhir. Data tetap dapat dibaca, tetapi operasional klinik sedang hanya-baca.',
                                \App\SubscriptionStatus::Suspended => 'Akses operasional klinik sedang hanya-baca.',
                                default => $subscriptionOverview['pending_payment'] ? 'Pembayaran sedang menunggu konfirmasi DOKU.' : null,
                            };

                            if ($subscriptionOverview['pending_payment']) {
                                $subscriptionMessage = 'Pembayaran sedang menunggu konfirmasi DOKU.';
                            }

                            $subscriptionActionIsPlans = ! $subscriptionOverview['pending_payment']
                                && in_array($subscriptionOverview['status'], [
                                    \App\SubscriptionStatus::Trialing,
                                    \App\SubscriptionStatus::Free,
                                    \App\SubscriptionStatus::Expired,
                                ], true);
                        @endphp

                        @if ($subscriptionMessage !== null)
                            <div class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-900" role="status">
                                <span>{{ $subscriptionMessage }}</span>
                                @if (auth()->user()->isClinicOwner())
                                    <a href="{{ $subscriptionActionIsPlans ? route('onboarding.plans.index') : route('subscription.payments.index') }}" class="shrink-0 font-extrabold text-amber-950 underline underline-offset-4" wire:navigate>
                                        {{ $subscriptionOverview['status'] === \App\SubscriptionStatus::Expired ? 'Perpanjang' : ($subscriptionActionIsPlans ? 'Lihat paket' : 'Kelola') }}
                                    </a>
                                @endif
                            </div>
                        @endif
                    @endif

                    @if (session('status'))
                        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-clinic-200 bg-clinic-50 px-5 py-4 text-sm font-medium text-clinic-700" role="status">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.7a1 1 0 0 0-1.4-1.4L9 10.2 7.7 8.9a1 1 0 0 0-1.4 1.4l2 2a1 1 0 0 0 1.4 0l4-4Z" clip-rule="evenodd"/></svg>
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
