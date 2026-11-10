<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'ClinicOS') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @php
            $adminLoginPage = request()->routeIs('admin.login');
            $patientLoginPage = request()->routeIs('login');
        @endphp
        <main @class([
            'grid min-h-screen lg:grid-cols-[1.05fr_1fr]',
            'bg-clinic-background' => $adminLoginPage,
            'bg-white lg:grid-cols-[1fr_1fr]' => $patientLoginPage,
            'bg-white' => ! $adminLoginPage && ! $patientLoginPage,
        ])>
            <section @class([
                'relative overflow-hidden lg:flex lg:flex-col lg:justify-between',
                'bg-clinic-950 text-white' => ! $patientLoginPage,
                'bg-clinic-50 text-clinic-950' => $patientLoginPage,
                'flex flex-col gap-5 px-5 py-5 sm:gap-8 sm:px-10 sm:py-7 lg:gap-0 lg:p-12' => $adminLoginPage || $patientLoginPage,
                'hidden p-12' => ! $adminLoginPage && ! $patientLoginPage,
            ])>
                <a href="{{ route('welcome') }}" class="relative flex items-center gap-3 focus-visible:rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-clinic-500">
                    <x-application-logo class="h-10 w-10 lg:h-11 lg:w-11" aria-hidden="true" />
                    <span class="font-display text-xl font-extrabold lg:text-2xl">Clinic<span class="text-clinic-500">OS</span></span>
                </a>
                <div class="relative max-w-xl">
                    @if ($adminLoginPage)
                        <h1 class="max-w-lg font-display text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl xl:text-5xl">Satu ruang kerja untuk tim klinik.</h1>
                        <p class="mt-4 max-w-md text-sm leading-6 text-clinic-100 sm:text-base sm:leading-7">Kelola pendaftaran, antrean, dan pemeriksaan dari akun yang sesuai dengan tugas Anda.</p>
                    @elseif ($patientLoginPage)
                        <h1 class="max-w-lg font-display text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl xl:text-5xl">Kunjungan klinik, lebih mudah diikuti.</h1>
                        <p class="mt-4 max-w-md text-sm leading-6 text-clinic-700 sm:text-base sm:leading-7">Masuk untuk membuat booking dan melihat jadwal serta antrean kunjungan Anda.</p>
                    @else
                        <h1 class="mt-6 font-display text-4xl font-extrabold leading-tight xl:text-5xl">Operasional klinik lebih mudah dalam satu platform</h1>
                        <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">Melalui ClinicOS kelola berbagai kebutuhan klinik mulai dari pendaftaran pasien, jadwal dokter, antrean, hingga pengelolaan rekam medis dan administrasi. Terintegrasi dalam satu platform untuk operasional klinik lebih efisien.</p>
                    @endif
                </div>
                @if ($adminLoginPage || $patientLoginPage)
                    <div class="relative hidden max-w-md lg:block">
                        <div @class(['h-px w-12', 'bg-clinic-400' => $adminLoginPage, 'bg-clinic-500' => $patientLoginPage])></div>
                        <p @class(['mt-5 text-sm font-semibold', 'text-clinic-100' => $adminLoginPage, 'text-clinic-700' => $patientLoginPage])>
                            {{ $adminLoginPage ? 'Pendaftaran  /  Antrean  /  Pemeriksaan' : 'Pilih jadwal  /  Booking  /  Pantau antrean' }}
                        </p>
                    </div>
                @else
                    <p class="relative text-xs text-slate-500">© {{ date('Y') }} ClinicOS · Sistem operasional klinik</p>
                @endif
            </section>

            <section @class([
                'flex items-center justify-center px-5 py-10 sm:px-10',
                'bg-clinic-background' => ! $patientLoginPage,
                'bg-white' => $patientLoginPage,
            ])>
                <div class="w-full max-w-md">
                    @unless ($adminLoginPage || $patientLoginPage)
                        <a href="{{ route('welcome') }}" class="mb-10 flex items-center gap-3 lg:hidden"><x-application-logo class="h-10 w-10" /><span class="font-display text-xl font-extrabold text-clinic-950">Clinic<span class="text-clinic-500">OS</span></span></a>
                    @endunless
                    <div @class([
                        'p-6 sm:p-9',
                        'rounded-2xl bg-white shadow-card' => ! $patientLoginPage,
                        'bg-white' => $patientLoginPage,
                    ])>
                        {{ $slot }}
                    </div>
                    <p class="mt-6 text-center text-xs leading-5 text-slate-500">Dengan melanjutkan, Anda menyetujui kebijakan penggunaan dan privasi ClinicOS.</p>
                </div>
            </section>
        </main>
    </body>
</html>
