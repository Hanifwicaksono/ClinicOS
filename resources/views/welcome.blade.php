<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ClinicOS — Operasional klinik dalam satu sistem</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=plus-jakarta-sans:600,700,800" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="overflow-x-hidden bg-white font-sans antialiased">
       <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-sm">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:h-20 sm:px-8">
        <a href="/" class="flex items-center gap-2 sm:gap-3">
            <x-application-logo class="h-10 w-10 sm:h-14 sm:w-14" />
            <span class="font-display text-2xl font-extrabold tracking-tight text-clinic-950 sm:text-3xl">Clinic<span class="text-clinic-500">OS</span></span>
        </a>

        <nav class="hidden items-center gap-6 text-base font-semibold text-slate-600 md:flex lg:gap-10 lg:text-[18px]">
            <a href="#fitur" class="transition hover:text-clinic-600">Fitur</a>
            <a href="#alur" class="transition hover:text-clinic-600">Cara kerja</a>
            <a href="#keamanan" class="transition hover:text-clinic-600">Keamanan</a>
        </nav>

        <div class="flex items-center gap-3">
            @auth
                <x-clinic-button href="{{ route('dashboard') }}" class="hidden md:inline-flex">Buka dashboard</x-clinic-button>
            @endauth

            <details class="group md:hidden">
                <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 text-slate-600 [&::-webkit-details-marker]:hidden" aria-label="Buka navigasi">
                    <svg class="h-6 w-6 group-open:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg class="hidden h-6 w-6 group-open:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </summary>

                <nav class="absolute inset-x-0 top-full border-b border-slate-200 bg-white p-4 shadow-elevated">
                    <div class="flex flex-col gap-1">
                        <a href="#fitur" onclick="this.closest('details').removeAttribute('open')" class="rounded-xl px-4 py-3 text-base font-semibold text-slate-700 hover:bg-slate-50">Fitur</a>
                        <a href="#alur" onclick="this.closest('details').removeAttribute('open')" class="rounded-xl px-4 py-3 text-base font-semibold text-slate-700 hover:bg-slate-50">Cara kerja</a>
                        <a href="#keamanan" onclick="this.closest('details').removeAttribute('open')" class="rounded-xl px-4 py-3 text-base font-semibold text-slate-700 hover:bg-slate-50">Keamanan</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="clinic-button-primary mt-2">Buka dashboard</a>
                        @endauth
                    </div>
                </nav>
            </details>
        </div>
    </div>
</header>

        <main>
            <section class="relative overflow-hidden bg-clinic-background">
                <div class="absolute left-1/2 top-0 h-[520px] w-[900px] -translate-x-1/2 rounded-full bg-clinic-100/60 blur-3xl"></div>
                <div class="relative mx-auto grid max-w-7xl grid-cols-1 gap-10 px-5 py-10 sm:px-8 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:gap-14 lg:py-16">
                <div class="min-w-0">
                        <h1 class="max-w-3xl font-display text-4xl font-extrabold leading-[1.1] tracking-tight text-clinic-950 sm:text-5xl lg:text-6xl">Operasional klinik <span class="text-clinic-500">lebih rapi</span> setiap hari.</h1>
                        <p class="mt-6 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">ClinicOS membantu mengelola pendaftaran, antrean, jadwal dokter, dan rekam medis klinik dalam satu sistem.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
    @auth
        <x-clinic-button href="{{ route('dashboard') }}" variant="primary" class="w-full justify-center px-6 py-3.5 sm:w-auto">Buka dashboard</x-clinic-button>
    @else
        <x-clinic-button href="{{ route('login') }}" variant="primary" class="w-full justify-center px-6 py-3.5 sm:w-auto">Masuk</x-clinic-button>
        <x-clinic-button href="{{ route('register') }}" variant="secondary" class="w-full justify-center px-6 py-3.5 sm:w-auto">Buat akun pasien</x-clinic-button>
        <x-clinic-button href="{{ route('register.clinic') }}" variant="secondary" class="w-full justify-center px-6 py-3.5 sm:w-auto">Daftarkan klinik</x-clinic-button>
    @endauth
</div>
                        <div class="mt-9 flex flex-wrap gap-x-7 gap-y-3 text-sm font-semibold text-slate-600"><span class="flex items-center gap-2">Akun pasien opsional</span><span class="flex items-center gap-2">Akses sesuai peran</span><span class="flex items-center gap-2">Audit aktivitas</span></div>
                    </div>

                    <div class="relative min-w-0">
                        <div class="absolute -inset-5 rounded-[2rem] bg-clinic-500/10 blur-2xl"></div>
                        <div class="clinic-card relative overflow-hidden rounded-[2rem] p-5 sm:p-7" style="background:#FFFFFF; background-image:none; border:1px solid #E2E8F0; box-shadow:0 10px 30px rgba(15,23,42,.06);">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-5"><div><span class="text-sm font-bold uppercase tracking-[0.12em] text-clinic-950">Hari ini</span><p class="mt-2 font-display text-lg font-extrabold text-clinic-950">Klinik Sehat Sentosa</p></div><span class="clinic-status-active">Aktif</span></div>
                            <div class="mt-5 grid grid-cols-3 gap-3">@foreach ([['24','Pasien'],['3','Dokter'],['6','Layanan']] as [$value, $label])<div class="rounded-2xl border border-brand-accent bg-clinic-50 p-4"><p class="font-display text-2xl font-extrabold text-clinic-950">{{ $value }}</p><p class="mt-1 text-xs font-medium text-clinic-950">{{ $label }}</p></div>@endforeach</div>
                        <div class="mt-5 rounded-2xl bg-clinic-500 p-5 text-[#FBF7EC]"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold text-[#FBF7EC]/90">Antrean berikutnya</p><p class="mt-2 font-display text-3xl font-extrabold text-[#FBF7EC]">KS-014</p></div><div class="rounded-2xl bg-white/15 px-4 py-3 text-right"><p class="text-xs text-[#FBF7EC]/90">dr. Budi</p><p class="mt-1 text-sm font-bold text-[#FBF7EC]">Ruang 02</p></div></div></div>
                            <div class="mt-5 space-y-3">@foreach ([['09:00','Konsultasi Umum','Selesai'],['09:30','Pemeriksaan Kesehatan','Berjalan'],['10:00','Konsultasi Anak','Menunggu']] as [$time,$name,$status])<div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3"><span class="text-sm font-bold text-slate-500">{{ $time }}</span><span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800">{{ $name }}</span><span class="text-xs font-bold {{ $status === 'Berjalan' ? '' : 'text-slate-400' }}" @if($status === 'Berjalan') style="color:#2B7F73;" @endif>{{ $status }}</span></div>@endforeach</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="fitur" class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28">
                <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Fondasi operasional</p><h2 class="mt-3 font-display text-3xl font-extrabold text-clinic-950 sm:text-4xl">Satu sumber data untuk seluruh tim klinik</h2><p class="mt-4 leading-7 text-slate-600">Setiap peran mendapat tampilan dan akses sesuai kebutuhan.</p></div>
                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['01','Profil klinik','Atur identitas, kontak, jam operasional, dan zona waktu.'],
                        ['02','Tim & akses','Kelola dokter dan resepsionis dengan batas akses yang jelas.'],
                        ['03','Layanan','Simpan tarif, durasi, dan dokter untuk setiap layanan.'],
                        ['04','Jadwal praktik','Cegah jadwal bertabrakan dan atur kuota per sesi.'],
                    ] as [$number,$title,$description])
                        <article class="clinic-card bg-[#FFFDFB] p-6"><span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-[#FFF1E2] font-display text-sm font-extrabold text-[#A96D34]">{{ $number }}</span><h3 class="mt-8 text-lg font-extrabold text-clinic-950">{{ $title }}</h3><p class="mt-3 text-sm leading-6 text-slate-500">{{ $description }}</p></article>
                    @endforeach
                </div>
            </section>

            <section id="alur" class="bg-clinic-950 text-white"><div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:flex lg:items-center lg:justify-between lg:py-24"><div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-300">KELOLA KLINIK LEBIH MUDAH</p><h2 class="mt-4 font-display text-3xl font-extrabold sm:text-4xl">Semua kebutuhan klinik, dalam satu sistem.</h2><p class="mt-4 leading-7 text-slate-300">Mulai dari mengatur jadwal dokter, mendaftarkan pasien, sampai mengelola antrean dan rekam medis, semuanya bisa dilakukan lewat ClinicOS.</p></div><x-clinic-button href="{{ route('login') }}" variant="primary" class="mt-8 shrink-0 px-6 lg:mt-0">Masuk ke ClinicOS</x-clinic-button></div></section>

            <section id="keamanan" class="mx-auto max-w-7xl px-5 py-20 sm:px-8"><div class="grid gap-8 rounded-3xl border border-[#F0D5AF] bg-[#FFF9F2] p-7 sm:p-10 lg:grid-cols-[1fr_1.2fr] lg:items-center"><div><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#D89B68] text-xl font-bold text-white">✓</span><h2 class="mt-5 font-display text-2xl font-extrabold text-clinic-950">Akses medis tetap terlindungi</h2></div><div class="grid gap-4 sm:grid-cols-2"><p class="text-sm leading-6 text-slate-600"><strong class="block text-slate-900">Hak akses per peran</strong>Setiap pengguna hanya memiliki akses sesuai perannya.</p><p class="text-sm leading-6 text-slate-600"><strong class="block text-slate-900">Riwayat aktivitas</strong> Perubahan penting selalu dicatat untuk pengawasan klinik.</p></div></div></section>
        </main>

        <footer class="border-t border-slate-200 bg-white"><div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8"><div class="flex items-center gap-2"><x-application-logo class="h-8 w-8" /><span class="font-display font-extrabold text-clinic-950">ClinicOS</span></div><p>© {{ date('Y') }} ClinicOS. Operasional klinik dalam satu sistem.</p></div></footer>
    </body>
</html>
