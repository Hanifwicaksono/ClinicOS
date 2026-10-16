<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Halaman tidak ditemukan — {{ config('app.name', 'ClinicOS') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=plus-jakarta-sans:600,700,800" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <main class="relative flex min-h-screen items-center overflow-hidden bg-clinic-background px-5 py-12 sm:px-8">
            <div class="absolute -left-28 -top-28 h-80 w-80 rounded-full bg-clinic-100/70 blur-3xl"></div>
            <div class="absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-[#FCE7D2]/70 blur-3xl"></div>

            <div class="relative mx-auto grid w-full max-w-5xl items-center gap-10 lg:grid-cols-[1fr_.9fr]">
                <section class="text-center lg:text-left">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                        <x-application-logo class="h-11 w-11" />
                        <span class="font-display text-2xl font-extrabold text-clinic-950">Clinic<span class="text-clinic-500">OS</span></span>
                    </a>

                    <p class="mt-10 text-sm font-bold uppercase tracking-[0.2em] text-clinic-600">Kode diagnosis: 404</p>
                    <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight text-clinic-950 sm:text-6xl">Ups, halamannya<br><span class="text-clinic-500">izin pulang dulu.</span></h1>
                    <p class="mx-auto mt-6 max-w-xl text-base leading-7 text-slate-600 lg:mx-0">Kami sudah cek ruang tunggu, apotek, bahkan di balik meja resepsionis. Halaman yang kamu cari tetap tidak ditemukan.</p>

                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
                        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="clinic-button-primary px-6">
                            {{ auth()->check() ? 'Kembali ke dashboard' : 'Kembali ke beranda' }}
                        </a>
                        <button type="button" onclick="history.back()" class="clinic-button-secondary px-6">Kembali satu langkah</button>
                    </div>
                </section>

                <section class="relative mx-auto w-full max-w-md" aria-label="Ilustrasi berkas medis yang tersesat">
                    <div class="absolute -inset-5 rotate-3 rounded-[2.5rem] bg-clinic-100/60"></div>
                    <div class="clinic-card relative overflow-hidden p-7 sm:p-10">
                        <div class="absolute right-6 top-6 rounded-full bg-[#FFF1E2] px-3 py-1 text-xs font-bold text-[#A96D34]">Tidak darurat kok</div>

                        <div class="mt-10 flex justify-center" aria-hidden="true">
                            <div class="relative h-56 w-48">
                                <div class="absolute inset-x-4 bottom-0 top-4 rotate-6 rounded-3xl border-2 border-clinic-200 bg-clinic-50"></div>
                                <div class="absolute inset-x-4 bottom-0 top-4 -rotate-3 rounded-3xl border-2 border-[#F0D5AF] bg-[#FFF9F2]"></div>
                                <div class="absolute inset-x-0 bottom-3 top-0 rounded-3xl border-2 border-slate-200 bg-white p-6 shadow-lg">
                                    <div class="h-3 w-16 rounded-full bg-clinic-100"></div>
                                    <div class="mt-3 h-2 w-24 rounded-full bg-slate-100"></div>
                                    <div class="mt-10 flex justify-center gap-8">
                                        <span class="h-3 w-3 rounded-full bg-clinic-950"></span>
                                        <span class="h-3 w-3 rounded-full bg-clinic-950"></span>
                                    </div>
                                    <div class="mx-auto mt-5 h-5 w-10 rounded-b-full border-b-4 border-clinic-500"></div>
                                    <div class="absolute -right-5 bottom-10 rotate-12 rounded-xl border-2 border-[#E7C199] bg-[#FFF1E2] px-5 py-2 shadow-sm">
                                        <span class="block h-2 w-12 rounded-full bg-[#E7C199]"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-7 rounded-2xl bg-clinic-950 px-5 py-4 text-center text-sm font-semibold text-white">
                            “Dok, saya salah belok di URL.”
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>
