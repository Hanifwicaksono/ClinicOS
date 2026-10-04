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
    <body class="bg-white font-sans antialiased">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
                <a href="/" class="flex items-center gap-3"><x-application-logo class="h-11 w-11" /><span class="font-display text-xl font-extrabold text-clinic-950">Clinic<span class="text-clinic-500">OS</span></span></a>
                <nav class="hidden items-center gap-8 text-sm font-semibold text-slate-600 md:flex"><a href="#fitur" class="hover:text-clinic-600">Fitur</a><a href="#alur" class="hover:text-clinic-600">Cara kerja</a><a href="#keamanan" class="hover:text-clinic-600">Keamanan</a></nav>
                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="clinic-button-primary">Buka dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden px-4 py-2 text-sm font-bold text-slate-700 sm:inline-flex">Masuk</a>
                        <a href="{{ route('register') }}" class="clinic-button-primary">Daftar pasien</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden bg-[#fafafa]">
                <div class="absolute left-1/2 top-0 h-[520px] w-[900px] -translate-x-1/2 rounded-full bg-clinic-100/60 blur-3xl"></div>
                <div class="relative mx-auto grid max-w-7xl gap-14 px-5 py-20 sm:px-8 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:py-28">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-clinic-200 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.12em] text-clinic-700"><span class="h-2 w-2 rounded-full bg-clinic-500"></span> Dibangun untuk klinik Indonesia</span>
                        <h1 class="mt-7 max-w-3xl font-display text-4xl font-extrabold leading-[1.1] tracking-tight text-clinic-950 sm:text-5xl lg:text-6xl">Operasional klinik, <span class="text-clinic-500">lebih rapi</span> setiap hari.</h1>
                        <p class="mt-6 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">Satukan tim, layanan, jadwal, pendaftaran, antrean, dan rekam kunjungan dalam alur kerja yang jelas bagi staf dan pasien.</p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('register') }}" class="clinic-button-primary px-6 py-3.5">Buat akun pasien <span aria-hidden="true">→</span></a>
                            <a href="{{ route('login') }}" class="clinic-button-secondary px-6 py-3.5">Masuk ke workspace</a>
                        </div>
                        <div class="mt-9 flex flex-wrap gap-x-7 gap-y-3 text-sm font-semibold text-slate-600"><span class="flex items-center gap-2"><span class="text-clinic-500">✓</span> Akun pasien opsional</span><span class="flex items-center gap-2"><span class="text-clinic-500">✓</span> Akses sesuai peran</span><span class="flex items-center gap-2"><span class="text-clinic-500">✓</span> Audit aktivitas</span></div>
                    </div>

                    <div class="relative">
                        <div class="absolute -inset-5 rounded-[2rem] bg-clinic-500/10 blur-2xl"></div>
                        <div class="clinic-card relative overflow-hidden rounded-[2rem] p-5 sm:p-7">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-5"><div><p class="text-xs font-bold uppercase tracking-wider text-clinic-600">Hari ini</p><p class="mt-1 font-display text-lg font-extrabold text-clinic-950">Klinik Sehat Sentosa</p></div><span class="clinic-status-active">● Aktif</span></div>
                            <div class="mt-5 grid grid-cols-3 gap-3">@foreach ([['24','Pasien'],['3','Dokter'],['6','Layanan']] as [$value, $label])<div class="rounded-2xl bg-slate-50 p-4"><p class="font-display text-2xl font-extrabold text-clinic-950">{{ $value }}</p><p class="mt-1 text-xs font-medium text-slate-500">{{ $label }}</p></div>@endforeach</div>
                            <div class="mt-5 rounded-2xl bg-clinic-950 p-5 text-white"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold text-clinic-200">Antrean berikutnya</p><p class="mt-2 font-display text-3xl font-extrabold">KS-014</p></div><div class="rounded-2xl bg-white/10 px-4 py-3 text-right"><p class="text-xs text-slate-300">dr. Budi</p><p class="mt-1 text-sm font-bold">Ruang 02</p></div></div></div>
                            <div class="mt-5 space-y-3">@foreach ([['09:00','Konsultasi Umum','Selesai'],['09:30','Pemeriksaan Kesehatan','Berjalan'],['10:00','Konsultasi Anak','Menunggu']] as [$time,$name,$status])<div class="flex items-center gap-4 rounded-xl border border-slate-100 px-4 py-3"><span class="text-sm font-bold text-slate-500">{{ $time }}</span><span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800">{{ $name }}</span><span class="text-xs font-bold {{ $status === 'Berjalan' ? 'text-clinic-600' : 'text-slate-400' }}">{{ $status }}</span></div>@endforeach</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="fitur" class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28">
                <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Fondasi operasional</p><h2 class="mt-3 font-display text-3xl font-extrabold text-clinic-950 sm:text-4xl">Satu sumber data untuk seluruh tim klinik</h2><p class="mt-4 leading-7 text-slate-600">Setiap peran mendapat tampilan dan akses yang tepat untuk pekerjaannya.</p></div>
                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['01','Profil klinik','Atur identitas, kontak, jam operasional, dan zona waktu.'],
                        ['02','Tim & akses','Kelola dokter dan resepsionis dengan batas akses yang jelas.'],
                        ['03','Layanan','Simpan tarif, durasi, dan dokter untuk setiap layanan.'],
                        ['04','Jadwal praktik','Cegah jadwal bertabrakan dan atur kuota per sesi.'],
                    ] as [$number,$title,$description])
                        <article class="clinic-card p-6"><span class="font-display text-sm font-extrabold text-clinic-500">{{ $number }}</span><h3 class="mt-8 text-lg font-extrabold text-clinic-950">{{ $title }}</h3><p class="mt-3 text-sm leading-6 text-slate-500">{{ $description }}</p></article>
                    @endforeach
                </div>
            </section>

            <section id="alur" class="bg-clinic-950 text-white"><div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:flex lg:items-center lg:justify-between lg:py-24"><div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-300">Mulai dengan fondasi yang benar</p><h2 class="mt-4 font-display text-3xl font-extrabold sm:text-4xl">Siapkan klinik, bentuk tim, lalu buka layanan.</h2><p class="mt-4 leading-7 text-slate-300">ClinicOS memandu setup secara bertahap agar data operasional konsisten sejak hari pertama.</p></div><a href="{{ route('login') }}" class="clinic-button-primary mt-8 shrink-0 px-6 lg:mt-0">Masuk ke ClinicOS</a></div></section>

            <section id="keamanan" class="mx-auto max-w-7xl px-5 py-20 sm:px-8"><div class="grid gap-8 rounded-3xl border border-clinic-200 bg-clinic-50 p-7 sm:p-10 lg:grid-cols-[1fr_1.2fr] lg:items-center"><div><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-clinic-500 text-xl font-bold text-white">✓</span><h2 class="mt-5 font-display text-2xl font-extrabold text-clinic-950">Akses medis tetap terlindungi</h2></div><div class="grid gap-4 sm:grid-cols-2"><p class="text-sm leading-6 text-slate-600"><strong class="block text-slate-900">Hak akses per peran</strong> Admin mengatur operasional tanpa otomatis melihat isi rekam medis.</p><p class="text-sm leading-6 text-slate-600"><strong class="block text-slate-900">Jejak aktivitas</strong> Perubahan penting dicatat untuk membantu pengawasan klinik.</p></div></div></section>
        </main>

        <footer class="border-t border-slate-200 bg-white"><div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8"><div class="flex items-center gap-2"><x-application-logo class="h-8 w-8" /><span class="font-display font-extrabold text-clinic-950">ClinicOS</span></div><p>© {{ date('Y') }} ClinicOS. Operasional klinik dalam satu sistem.</p></div></footer>
    </body>
</html>
