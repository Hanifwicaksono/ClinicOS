<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ClinicOS — Sistem operasional untuk klinik yang bertumbuh</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=plus-jakarta-sans:600,700,800" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="overflow-x-hidden bg-[#f7f8f5] font-sans antialiased">
        <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-sm">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:h-20 sm:px-8">
                <a href="{{ route('welcome') }}" class="flex items-center gap-2 sm:gap-3"><x-application-logo class="h-10 w-10 sm:h-14 sm:w-14" /><span class="font-display text-2xl font-extrabold tracking-tight text-clinic-950 sm:text-3xl">Clinic<span class="text-clinic-500">OS</span></span></a>
                <nav class="hidden items-center gap-8 text-sm font-semibold text-slate-600 md:flex"><a href="#fitur" class="transition hover:text-clinic-600">Fitur</a><a href="#demo" class="transition hover:text-clinic-600">Demo</a><a href="#paket" class="transition hover:text-clinic-600">Paket</a><a href="#faq" class="transition hover:text-clinic-600">FAQ</a></nav>
                <div class="flex items-center gap-3">
                    @auth
                        <x-clinic-button href="{{ route('dashboard') }}" class="hidden sm:inline-flex">Buka dashboard</x-clinic-button>
                    @else
                        <x-clinic-button href="{{ route('login') }}" variant="secondary" class="hidden sm:inline-flex">Masuk</x-clinic-button>
                        <x-clinic-button href="{{ route('register.clinic') }}" class="hidden sm:inline-flex">Daftarkan klinik</x-clinic-button>
                    @endauth
                    <button type="button" @click="open = ! open" :aria-expanded="open" aria-controls="mobile-navigation" class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 md:hidden" aria-label="Buka navigasi">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
            <nav id="mobile-navigation" x-show="open" x-cloak @click.outside="open = false" class="absolute inset-x-0 top-16 border-b border-slate-200 bg-white p-5 shadow-elevated sm:top-20 md:hidden">
                <div class="mx-auto flex max-w-7xl flex-col gap-2">
                    <a href="#fitur" @click="open = false" class="rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fitur</a>
                    <a href="#demo" @click="open = false" class="rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Demo</a>
                    <a href="#paket" @click="open = false" class="rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Paket</a>
                    <a href="#faq" @click="open = false" class="rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">FAQ</a>
                    <div class="mt-2 grid gap-2 border-t border-slate-100 pt-4 sm:hidden">
                        @auth
                            <a href="{{ route('dashboard') }}" class="clinic-button-primary">Buka dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="clinic-button-secondary">Masuk</a>
                            <a href="{{ route('register.clinic') }}" class="clinic-button-primary">Daftarkan klinik</a>
                        @endauth
                    </div>
                </div>
            </nav>
        </header>

        <main>
            <section class="relative overflow-hidden bg-[radial-gradient(circle_at_top_left,_#ffffff_0%,_#eef8f4_42%,_#dcefeb_100%)]">
                <div class="absolute -left-32 -top-36 h-96 w-96 rounded-full bg-clinic-200/70 blur-3xl"></div>
                <div class="absolute left-1/2 top-12 h-64 w-64 rounded-full bg-white/80 blur-3xl"></div>
                <div class="absolute -right-32 bottom-0 h-80 w-80 rounded-full bg-[#f8dec5]/80 blur-3xl"></div>
                <div class="relative mx-auto grid max-w-7xl gap-12 px-5 py-10 sm:px-8 sm:py-16 lg:grid-cols-[1fr_.95fr] lg:items-center lg:py-24">
                    <div class="landing-reveal min-w-0">
                        <p class="clinic-badge-accent">Sistem operasional klinik</p>
                        <h1 class="mt-6 max-w-3xl font-display text-4xl font-extrabold leading-[1.08] tracking-tight text-clinic-950 sm:text-5xl lg:text-6xl">Lebih banyak waktu untuk <span class="text-clinic-500">melayani pasien.</span></h1>
                        <p class="mt-6 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">ClinicOS menyatukan pendaftaran, antrean, jadwal dokter, rekam medis, dan administrasi dalam satu alur kerja yang tenang untuk klinik dan tim medis.</p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <x-clinic-button href="{{ route('register.clinic') }}" class="px-6 py-3.5">Daftarkan klinik</x-clinic-button>
                            <x-clinic-button href="#demo" variant="secondary" class="px-6 py-3.5">Lihat demo</x-clinic-button>
                        </div>
                        <div class="mt-9 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-slate-600"><span>✓ Akses sesuai peran</span><span>✓ Audit aktivitas</span><span>✓ Data terpusat</span></div>
                    </div>

                    <div class="landing-reveal landing-reveal-delay relative min-w-0">
                        <div class="landing-float clinic-card relative overflow-hidden rounded-[2rem] border-white/80 bg-white p-5 shadow-elevated sm:p-7">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-5"><div><p class="text-xs font-bold uppercase tracking-[0.14em] text-clinic-600">Contoh tampilan</p><p class="mt-2 font-display text-lg font-extrabold text-clinic-950">Operasional hari ini</p></div><span class="clinic-status-active">Terhubung</span></div>
                            <div class="mt-5 grid grid-cols-3 gap-3">@foreach ([['24','Pasien'],['3','Dokter'],['6','Layanan']] as [$value, $label])<div class="rounded-2xl bg-clinic-50 p-3 sm:p-4"><p class="font-display text-xl font-extrabold text-clinic-950 sm:text-2xl">{{ $value }}</p><p class="mt-1 text-xs font-medium text-clinic-700">{{ $label }}</p></div>@endforeach</div>
                            <div class="mt-5 rounded-2xl bg-clinic-500 p-5 text-white"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold text-white/80">Antrean berikutnya</p><p class="mt-2 font-display text-3xl font-extrabold">A-014</p></div><div class="rounded-2xl bg-white/15 px-4 py-3 text-right"><p class="text-xs text-white/80">Ruang 02</p><p class="mt-1 text-sm font-bold">Sedang berjalan</p></div></div></div>
                            <div class="mt-5 space-y-3">@foreach ([['09:00','Konsultasi umum','Selesai'],['09:30','Pemeriksaan kesehatan','Berjalan'],['10:00','Konsultasi anak','Menunggu']] as [$time, $name, $status])<div class="flex items-center gap-4 rounded-xl border border-slate-100 bg-white px-4 py-3"><span class="text-sm font-bold text-slate-500">{{ $time }}</span><span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800">{{ $name }}</span><span class="text-xs font-bold {{ $status === 'Berjalan' ? 'text-clinic-600' : 'text-slate-400' }}">{{ $status }}</span></div>@endforeach</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white/80">
                <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 text-center sm:grid-cols-3 sm:px-8 sm:text-left">
                    <div><p class="font-display text-3xl font-extrabold text-clinic-950">{{ number_format($statistics['clinics'], 0, ',', '.') }}+</p><p class="mt-1 text-sm font-semibold text-slate-500">klinik aktif bergabung</p></div>
                    <div><p class="font-display text-3xl font-extrabold text-clinic-950">{{ number_format($statistics['patients'], 0, ',', '.') }}+</p><p class="mt-1 text-sm font-semibold text-slate-500">pasien aktif tercatat</p></div>
                    <div><p class="font-display text-3xl font-extrabold text-clinic-950">3 Paket</p><p class="mt-1 text-sm font-semibold text-slate-500">untuk tahap pertumbuhan klinik</p></div>
                </div>
            </section>

            <section id="fitur" class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28">
                <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Fitur utama</p><h2 class="mt-3 font-display text-3xl font-extrabold text-clinic-950 sm:text-4xl">Satu sistem yang mengikuti ritme klinik Anda.</h2><p class="mt-4 leading-7 text-slate-600">Dari pasien datang hingga layanan selesai, setiap peran mendapat informasi yang dibutuhkan tanpa berpindah-pindah sistem.</p></div>
                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['01', 'Pendaftaran pasien', 'Profil dan riwayat pasien tersimpan rapi untuk pelayanan berikutnya.'],
                        ['02', 'Antrean terarah', 'Pantau status antrean dan arahkan alur kunjungan dari satu tempat.'],
                        ['03', 'Jadwal dokter', 'Atur sesi praktik, kuota, layanan, dan ketersediaan dokter.'],
                        ['04', 'Rekam medis', 'Dokumentasikan pemeriksaan dan tindak lanjut dengan struktur yang konsisten.'],
                        ['05', 'Tim sesuai peran', 'Pisahkan akses pemilik, resepsionis, dokter, dan pasien sesuai tugasnya.'],
                        ['06', 'Kontrol aktivitas', 'Perubahan penting dicatat untuk membantu pengawasan operasional.'],
                    ] as [$number, $title, $description])
                        <article class="group clinic-card bg-white p-6 transition duration-300 hover:-translate-y-1 hover:shadow-elevated"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#fff1e2] font-display text-sm font-extrabold text-[#a96d34]">{{ $number }}</span><h3 class="mt-8 text-lg font-extrabold text-clinic-950">{{ $title }}</h3><p class="mt-3 text-sm leading-6 text-slate-500">{{ $description }}</p></article>
                    @endforeach
                </div>
            </section>

            <section id="demo" class="bg-clinic-950 py-20 text-white lg:py-28">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[.9fr_1.1fr] lg:items-center">
                    <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-300">Demo alur kerja</p><h2 class="mt-4 font-display text-3xl font-extrabold sm:text-4xl">Informasi yang tepat, untuk peran yang tepat.</h2><p class="mt-5 max-w-xl leading-7 text-slate-300">Resepsionis melihat antrean, dokter melanjutkan ke pemeriksaan, dan pemilik klinik memantau operasional—dalam alur yang saling terhubung.</p><a href="{{ route('register.clinic') }}" class="mt-8 inline-flex items-center gap-2 font-bold text-clinic-200 underline underline-offset-8 transition hover:text-white">Mulai dari pendaftaran klinik <span aria-hidden="true"></span></a></div>
                    <div class="rounded-[2rem] border border-white/10 bg-white/5 p-5 sm:p-7"><div class="flex items-center gap-3 border-b border-white/10 pb-5"><span class="h-3 w-3 rounded-full bg-[#d89b68]"></span><span class="h-3 w-3 rounded-full bg-white/25"></span><span class="h-3 w-3 rounded-full bg-white/25"></span><p class="ml-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-300">Ringkasan klinik</p></div><div class="mt-6 grid gap-4 sm:grid-cols-2"><div class="rounded-2xl bg-white p-5 text-clinic-950"><p class="text-xs font-bold uppercase tracking-wider text-clinic-600">Antrean</p><p class="mt-5 font-display text-4xl font-extrabold">08</p><p class="mt-1 text-sm text-slate-500">menunggu pelayanan</p></div><div class="rounded-2xl bg-clinic-500 p-5"><p class="text-xs font-bold uppercase tracking-wider text-white/75">Jadwal</p><p class="mt-5 font-display text-2xl font-extrabold">3 dokter</p><p class="mt-2 text-sm text-white/80">praktik hari ini</p></div></div><div class="mt-4 rounded-2xl border border-white/10 p-5"><div class="flex items-center justify-between text-sm"><span class="font-semibold">Pendaftaran pasien</span><span class="font-bold text-clinic-300">Tersambung</span></div><div class="mt-4 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full w-2/3 rounded-full bg-clinic-300"></div></div></div></div>
                </div>
            </section>

            <section id="paket" class="relative overflow-hidden bg-[linear-gradient(135deg,_#dcefeb_0%,_#f4fbf7_48%,_#fff0e3_100%)] py-20 lg:py-28">
                <div class="absolute -left-24 top-12 h-72 w-72 rounded-full bg-clinic-200/60 blur-3xl"></div><div class="absolute -right-24 bottom-0 h-80 w-80 rounded-full bg-[#f8dec5]/70 blur-3xl"></div>
                <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
                <div class="mx-auto max-w-2xl text-center"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Paket subscription</p><h2 class="mt-3 font-display text-3xl font-extrabold text-clinic-950 sm:text-4xl">Mulai sesuai kebutuhan klinik Anda.</h2><p class="mt-4 leading-7 text-slate-600">Pilih fondasi yang sesuai saat ini, lalu tingkatkan saat tim dan volume layanan bertumbuh.</p></div>
                <div class="mt-12 grid gap-5 lg:grid-cols-3">
                    @foreach ([
                        ['Free', 'Untuk klinik yang baru mulai digitalisasi.', 'Gratis', ['1 dokter', '1 staf', 'Hingga 50 kunjungan per bulan'], false],
                        ['Individual', 'Untuk praktik mandiri dengan alur operasional lengkap.', isset($minimumPrices['individual']) ? 'Mulai dari Rp '.number_format($minimumPrices['individual'], 0, ',', '.') : 'Harga saat pendaftaran', ['1 dokter', 'Hingga 3 staf', 'Kunjungan tanpa batas', 'Dashboard manajemen'], false],
                        ['Team', 'Untuk klinik dengan tim dan layanan yang terus berkembang.', isset($minimumPrices['team']) ? 'Mulai dari Rp '.number_format($minimumPrices['team'], 0, ',', '.') : 'Harga saat pendaftaran', ['Dokter tanpa batas', 'Staf tanpa batas', 'Kunjungan tanpa batas', 'Dashboard manajemen'], true],
                    ] as [$name, $description, $price, $benefits, $featured])
                        <article class="relative rounded-3xl border p-7 {{ $featured ? 'border-clinic-500 bg-clinic-950 text-white shadow-elevated' : 'border-slate-200 bg-white text-clinic-950' }}">@if ($featured)<span class="absolute -top-3 left-7 rounded-full bg-[#d89b68] px-3 py-1 text-xs font-bold text-white">Paling lengkap</span>@endif<h3 class="font-display text-2xl font-extrabold">{{ $name }}</h3><p class="mt-3 min-h-12 text-sm leading-6 {{ $featured ? 'text-slate-300' : 'text-slate-500' }}">{{ $description }}</p><p class="mt-7 font-display text-xl font-extrabold">{{ $price }}</p><ul class="mt-7 space-y-3 text-sm {{ $featured ? 'text-slate-200' : 'text-slate-600' }}">@foreach ($benefits as $benefit)<li class="flex gap-3"><span class="font-bold text-clinic-400" aria-hidden="true">✓</span>{{ $benefit }}</li>@endforeach</ul><a href="{{ route('register.clinic') }}" class="mt-8 {{ $featured ? 'inline-flex min-h-11 w-full items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-bold text-clinic-950 transition hover:bg-clinic-50' : 'clinic-button-primary w-full' }}">Pilih {{ $name }}</a></article>
                    @endforeach
                </div>
                </div>
            </section>

            <section id="faq" class="bg-clinic-background py-20 lg:py-24">
                <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[.75fr_1.25fr]"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">FAQ</p><h2 class="mt-3 font-display text-3xl font-extrabold text-clinic-950">Pertanyaan sebelum memulai.</h2><p class="mt-4 leading-7 text-slate-600">Pilih paket yang sesuai dan lanjutkan proses onboarding setelah mendaftarkan klinik.</p></div><div class="space-y-3">@foreach ([['Apakah saya bisa mulai tanpa biaya?', 'Bisa. Paket Free tersedia untuk memulai dengan satu dokter, satu staf, dan hingga 50 kunjungan per bulan.'], ['Kapan saya perlu memilih paket berbayar?', 'Saat kebutuhan tim, jumlah kunjungan, atau akses dashboard manajemen klinik bertambah.'], ['Apakah data setiap klinik terpisah?', 'Ya. ClinicOS membatasi akses berdasarkan peran dan konteks klinik pengguna.'], ['Bagaimana cara mencoba alurnya?', 'Daftarkan klinik, verifikasi akun, lalu selesaikan onboarding untuk memilih paket dan mulai mengatur operasional.']] as [$question, $answer])<details class="group rounded-2xl border border-slate-200 bg-white px-5 py-1"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-5 font-bold text-clinic-950">{{ $question }}<span class="text-xl text-clinic-500 transition group-open:rotate-45" aria-hidden="true">+</span></summary><p class="border-t border-slate-100 pb-5 pt-4 text-sm leading-6 text-slate-600">{{ $answer }}</p></details>@endforeach</div></div>
            </section>

            <section id="kontak" class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28"><div class="relative overflow-hidden rounded-[2rem] bg-clinic-500 px-7 py-12 text-white sm:px-12 lg:flex lg:items-center lg:justify-between"><div class="absolute -right-12 -top-24 h-64 w-64 rounded-full bg-clinic-300/30 blur-2xl"></div><div class="absolute -bottom-24 left-1/3 h-52 w-52 rounded-full bg-[#f8dec5]/35 blur-2xl"></div><div class="relative max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-white/75">Mulai bersama ClinicOS</p><h2 class="mt-4 font-display text-3xl font-extrabold sm:text-4xl">Siapkan operasional klinik yang lebih terarah.</h2><p class="mt-4 leading-7 text-white/85">Daftarkan klinik Anda untuk membuat akun pemilik, melanjutkan onboarding, dan memilih paket yang sesuai.</p></div><a href="{{ route('register.clinic') }}" class="relative mt-8 inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-clinic-950 shadow-sm transition hover:bg-clinic-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-clinic-500 lg:mt-0">Daftarkan klinik</a></div></section>
        </main>

        <footer class="bg-white"><div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8"><div class="flex items-center gap-2"><x-application-logo class="h-8 w-8" /><span class="font-display font-extrabold text-clinic-950">ClinicOS</span></div><div class="flex gap-5 font-semibold"><a href="#paket" class="hover:text-clinic-600">Paket</a><a href="#faq" class="hover:text-clinic-600">FAQ</a><a href="{{ route('login') }}" class="hover:text-clinic-600">Masuk</a></div><p>© {{ date('Y') }} ClinicOS.</p></div></footer>
    </body>
</html>
