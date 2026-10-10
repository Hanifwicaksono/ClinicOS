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
        <main class="grid min-h-screen bg-white lg:grid-cols-[1.05fr_1fr]">
            <section class="relative hidden overflow-hidden bg-clinic-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-clinic-500/15 blur-3xl"></div>
                <div class="absolute -bottom-40 -left-32 h-96 w-96 rounded-full bg-[#287F78]/10 blur-3xl"></div>
                <a href="/" class="relative flex items-center gap-3">
                    <x-application-logo class="h-11 w-11" />
                    <span class="font-display text-2xl font-extrabold">Clinic<span class="text-clinic-500">OS</span></span>
                </a>
                <div class="relative max-w-xl">
                    <h1 class="mt-6 font-display text-4xl font-extrabold leading-tight xl:text-5xl">Operasional klinik lebih mudah dalam satu platform</h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">Melalui ClinicOS kelola berbagai kebutuhan klinik mulai dari pendaftaran pasien, jadwal dokter, antrean, hingga pengelolaan rekam medis dan administrasi. Terintegrasi dalam satu platform untuk operasional klinik lebih efisien.</p>
                </div>
                <p class="relative text-xs text-slate-500">© {{ date('Y') }} ClinicOS · Sistem operasional klinik</p>
            </section>

            <section class="flex items-center justify-center bg-[#fafafa] px-5 py-10 sm:px-10">
                <div class="w-full max-w-md">
                    <a href="/" class="mb-10 flex items-center gap-3 lg:hidden"><x-application-logo class="h-10 w-10" /><span class="font-display text-xl font-extrabold text-clinic-950">Clinic<span class="text-clinic-500">OS</span></span></a>
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-card sm:p-9">
                        {{ $slot }}
                    </div>
                    <p class="mt-6 text-center text-xs leading-5 text-slate-400">Dengan melanjutkan, Anda menyetujui kebijakan penggunaan dan privasi ClinicOS.</p>
                </div>
            </section>
        </main>
    </body>
</html>
