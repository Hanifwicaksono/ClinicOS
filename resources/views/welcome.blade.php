<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8faf8">
    <meta name="description" content="ClinicOS membantu klinik mengelola pendaftaran, antrean, jadwal dokter, rekam medis, dan aktivitas tim dalam satu sistem.">
    <title>ClinicOS — Operasional klinik, lebih tertata</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/clinicos-landing.css') }}">
</head>
<body class="co-page">
    <a class="co-skip-link" href="#konten">Lewati navigasi</a>

    <header class="co-header" id="atas">
        <div class="co-container co-header-inner">
            
            <a href="{{ route('welcome') }}"
            class="flex items-center gap-3">
                <img
                    src="{{ asset('assets/logo/ClinicOS ribbon logo recolor.png') }}"
                    alt="Logo ClinicOS"
                    class="h-12 w-auto object-contain"
                >
            </a>


            <nav class="co-desktop-nav" aria-label="Navigasi utama">
                <a href="#fitur">Fitur</a>
                <a href="#cara-kerja">Cara kerja</a>
                <a href="#harga">Harga</a>
                <a href="#faq">FAQ</a>
            </nav>

            <div class="co-header-actions">
                @auth
                    <a class="co-btn co-btn-primary co-btn-small" href="{{ route('dashboard') }}">Buka dashboard <span aria-hidden="true">↗</span></a>
                @else
                    <a class="co-login" href="{{ route('admin.login') }}">Masuk pengelola</a>
                    <a class="co-btn co-btn-primary co-btn-small" href="{{ route('register.clinic') }}">Daftarkan klinik <span aria-hidden="true">↗</span></a>
                @endauth
            </div>
            <button class="co-menu-button" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="co-mobile-menu" data-co-menu-button>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
            </button>
        </div>
        <nav class="co-mobile-menu" id="co-mobile-menu" aria-label="Navigasi mobile" hidden>
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara kerja</a>
            <a href="#harga">Harga</a>
            <a href="#faq">FAQ</a>
            @auth
                <a class="co-mobile-cta" href="{{ route('dashboard') }}">Buka dashboard ↗</a>
            @else
                <a href="{{ route('admin.login') }}">Masuk pengelola</a>
                <a class="co-mobile-cta" href="{{ route('register.clinic') }}">Daftarkan klinik ↗</a>
            @endauth
        </nav>
    </header>

    <main id="konten">
        
            <section class="co-hero"aria-labelledby="hero-title" style="background-image:linear-gradient( 90deg, rgba(5, 28, 24, 0.85) 0%, rgba(5, 28, 24, 0.65) 100% ),url('{{ asset('assets/logo/clinic-bg.png') }}');background-size: cover;background-position: center;background-repeat: no-repeat; ">

            <div class="co-hero-grid co-container">
                <div class="co-hero-copy">
                    
                    <h1 id="hero-title">Klinik lebih teratur.<br><span>Pelayanan lebih fokus.</span></h1>
                    <p class="co-hero-description">Dari pendaftaran sampai rekam medis, semua terhubung di satu tempat. Biar tim klinik nggak habis waktu pindah-pindah sistem.</p>
                    <div class="co-hero-actions">
                        <a href="{{ route('register.clinic') }}" class="co-btn co-btn-primary co-btn-large">Daftarkan klinik <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                        <a href="#produk" class="co-btn co-btn-outline co-btn-large">Lihat tampilan <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                    </div>
                    <div class="co-hero-footnote">
                        <span><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Mulai dari paket gratis</span>
                        <span><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Akses sesuai peran</span>
                    </div>
                </div>

                <div class="co-hero-visual" id="produk" aria-label="Contoh ilustrasi dashboard ClinicOS">
                    <div class="co-decor co-decor-one" aria-hidden="true"></div>
                    <div class="co-decor co-decor-two" aria-hidden="true"></div>
                    <div class="co-dashboard">
                        <aside class="co-dashboard-sidebar" aria-hidden="true">     
                            <div class="co-mini-logo"> <img src="{{ asset('assets/logo/ClinicOS green ribbon C symbol.png') }}" alt="ClinicOS"class="co-logo-image"></div>
                            <div class="co-sidebar-icon co-sidebar-icon-active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg></div>
                            <div class="co-sidebar-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h16v14H4zM4 10h16M9 3v4M15 3v4"/></svg></div>
                            <div class="co-sidebar-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-4 3-6 7-6s7 2 7 6"/></svg></div>
                            <div class="co-sidebar-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 3h8l4 4v14H7zM15 3v5h5M10 12h6M10 16h6"/></svg></div>
                            <span class="co-sidebar-avatar">A</span>
                        </aside>
                        <div class="co-dashboard-body">
                            <div class="co-dashboard-topline"><span><span class="co-dashboard-breadcrumb">Workspace</span> / Ringkasan</span><span class="co-demo-tag">ILUSTRASI PRODUK</span></div>
                            <div class="co-dashboard-heading"><div><small>DASHBOARD KLINIK</small><h2>Ringkasan hari ini</h2></div><span class="co-online"><span></span> Sistem aktif</span></div>
                            <div class="co-kpi-grid">
                                <div class="co-kpi"><div class="co-kpi-label"><span>Pasien terdaftar</span><span class="co-kpi-icon co-icon-green">↗</span></div><strong>24</strong><small>Hari ini</small></div>
                                <div class="co-kpi"><div class="co-kpi-label"><span>Antrean berjalan</span><span class="co-kpi-icon co-icon-peach">↗</span></div><strong>08</strong><small>3 sedang dilayani</small></div>
                                <div class="co-kpi"><div class="co-kpi-label"><span>Dokter praktik</span><span class="co-kpi-icon co-icon-lilac">✚</span></div><strong>03</strong><small>Dari 4 dokter</small></div>
                            </div>
                            <div class="co-dashboard-row">
                                <div class="co-activity-panel"><div class="co-panel-header"><h3>Alur kunjungan</h3><span>Minggu ini</span></div><div class="co-chart"><div class="co-chart-grid" aria-hidden="true"><span></span><span></span><span></span></div><div class="co-bars" aria-hidden="true"><div class="co-bar" style="--bar-height:52%"></div><div class="co-bar" style="--bar-height:72%"></div><div class="co-bar" style="--bar-height:60%"></div><div class="co-bar co-bar-highlight" style="--bar-height:89%"></div><div class="co-bar" style="--bar-height:69%"></div><div class="co-bar" style="--bar-height:78%"></div><div class="co-bar" style="--bar-height:42%"></div></div></div><div class="co-chart-axis"><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span></div></div>
                                <div class="co-next-panel"><div class="co-panel-header"><h3>Antrean</h3><span>Simulasi</span></div><div class="co-next-num">A-014</div><span class="co-next-status"><span></span> Sedang dilayani</span><div class="co-next-line"></div><div class="co-next-meta"><span>Berikutnya</span><strong>A-015</strong></div></div>
                            </div>
                            <div class="co-appointments"><div class="co-panel-header"><h3>Jadwal terdekat</h3><span>Lihat semua ↗</span></div><div class="co-appointment-row"><span class="co-appt-time">09:30</span><span class="co-appt-avatar">NA</span><span class="co-appt-text">Pemeriksaan umum<small>Poli umum</small></span><span class="co-appt-status">Berjalan</span></div><div class="co-appointment-row"><span class="co-appt-time">10:00</span><span class="co-appt-avatar co-appt-avatar-alt">DK</span><span class="co-appt-text">Konsultasi anak<small>Poli anak</small></span><span class="co-appt-wait">Menunggu</span></div></div>
                        </div>
                    </div>
                    <div class="co-floating-note"><span class="co-floating-icon">✓</span><span><strong>Semua dalam satu alur</strong><small>Pendaftaran → pelayanan → selesai</small></span></div>
                </div>
            </div>
        </section>

        <section class="co-benefit-strip" aria-label="Keunggulan ClinicOS"><div class="co-container co-benefit-grid"><span>
            @if (isset($statistics['clinics'], $statistics['patients']) && (int) $statistics['clinics'] > 0 && (int) $statistics['patients'] > 0)
                {{ number_format($statistics['clinics'], 0, ',', '.') }} klinik · {{ number_format($statistics['patients'], 0, ',', '.') }} pasien tercatat
            @else
                Dirancang untuk alur kerja klinik
            @endif
        </span><div><span class="co-benefit-symbol">✳</span> Akses sesuai peran</div><div><span class="co-benefit-symbol">◉</span> Antrean terpusat</div><div><span class="co-benefit-symbol">▣</span> Rekam medis terstruktur</div></div></section>

        <section class="co-section co-features" id="fitur" aria-labelledby="features-title">
            <div class="co-container">
                <div class="co-section-heading"><div><span class="co-eyebrow">KENAPA CLINICOS</span><h2 id="features-title">Banyak aktivitas.<br><span>Satu tempat buat semuanya.</span></h2></div><p></p></div>
                <div class="co-feature-grid">
                    <article class="co-feature-card co-feature-card-primary"><div class="co-feature-icon">✚</div><div class="co-feature-content"><div class="co-feature-number">01 / PENDAFTARAN</div><h3>Data pasien tertata sejak awal.</h3><p>Kelola pendaftaran, data pasien, dan riwayat kunjungan tanpa pencatatan yang terpisah-pisah.</p></div><div class="co-registration-demo" aria-hidden="true"><div class="co-reg-head"><span>Pasien terdaftar</span><span>＋ Tambah</span></div><div class="co-reg-item"><i class="co-patient-photo">AY</i><div><strong>Andi Yusuf</strong><small>PX-000143</small></div><span>Aktif</span></div><div class="co-reg-item"><i class="co-patient-photo co-patient-pink">NS</i><div><strong>Nadia Sari</strong><small>PX-000144</small></div><span>Aktif</span></div></div></article>
                    <article class="co-feature-card"><div class="co-feature-icon co-feature-icon-peach"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 12h16M12 4v16M5 5l14 14" stroke-linecap="round"/></svg></div><div class="co-feature-number">02 / ANTREAN</div><h3>Antrean yang mudah dipantau.</h3><p>Lihat status antrean dari satu layar supaya staf tahu siapa yang berikutnya.</p></article>
                    <article class="co-feature-card"><div class="co-feature-icon co-feature-icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v4M16 3v4" stroke-linecap="round"/></svg></div><div class="co-feature-number">03 / JADWAL</div><h3>Jadwal dokter, nggak berantakan.</h3><p>Atur sesi praktik, layanan, kuota, dan ketersediaan dalam satu kalender.</p></article>
                    <article class="co-feature-card"><div class="co-feature-icon co-feature-icon-rose"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3h8l4 4v14H7zM15 3v5h5M10 12h6M10 16h6" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="co-feature-number">04 / REKAM MEDIS</div><h3>Catatan pemeriksaan tersusun.</h3><p>Dokumentasikan pemeriksaan dan tindak lanjut pasien secara terstruktur.</p></article>
                    <article class="co-feature-card"><div class="co-feature-icon co-feature-icon-yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4 7v5c0 5 3.3 8.3 8 9 4.7-.7 8-4 8-9V7zM9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="co-feature-number">06 / AKTIVITAS</div><h3>Aktivitas lebih mudah ditelusuri.</h3><p>Catatan perubahan penting membantu pemilik klinik memantau operasional.</p></article>
                </div>
            </div>
        </section>

        <section class="co-workflow-section" id="cara-kerja" aria-labelledby="workflow-title">
            <div class="co-container co-workflow-layout"><div><span class="co-eyebrow co-eyebrow-dark">CARA KERJA</span><h2 id="workflow-title">Alur klinik yang nyambung dari awal sampai akhir.</h2><p class="co-workflow-lede">Bukan sekadar dashboard. ClinicOS membantu tiap peran melanjutkan proses tanpa perlu input ulang data yang sama.</p><a href="{{ route('register.clinic') }}" class="co-text-link">Mulai atur klinik <span aria-hidden="true">↗</span></a></div><ol class="co-steps"><li><span class="co-step-number">01</span><div><h3>Pasien didaftarkan</h3><p>Resepsionis mencatat kunjungan dan memasukkan pasien ke antrean.</p></div></li><li><span class="co-step-number">02</span><div><h3>Dokter melanjutkan pelayanan</h3><p>Jadwal dan data pasien tersedia untuk proses pemeriksaan.</p></div></li><li><span class="co-step-number">03</span><div><h3>Operasional tercatat</h3><p>Riwayat kunjungan dan aktivitas terdokumentasi untuk kebutuhan klinik.</p></div></li></ol></div>
        </section>

        <section class="co-section co-pricing" id="harga" aria-labelledby="pricing-title"><div class="co-container"><div class="co-centered-heading"><span class="co-eyebrow">PILIH PAKET</span><h2 id="pricing-title">Mulai kecil, berkembang sesuai kebutuhan.</h2><p>Harga dan cakupan layanan bisa dipilih sesuai kondisi klinik saat pendaftaran.</p></div><div class="co-pricing-grid">
            <article class="co-price-card"><div class="co-plan-label">UNTUK MEMULAI</div><h3>Free</h3><p class="co-plan-description">Coba alur dasar digitalisasi klinik.</p><div class="co-plan-price">Gratis</div><p class="co-plan-price-note">Tanpa biaya berlangganan</p><a class="co-btn co-btn-price-outline" href="{{ route('register.clinic') }}">Pilih Free <span aria-hidden="true">↗</span></a><div class="co-price-divider"></div><p class="co-includes">YANG DIDAPAT</p><ul><li>1 dokter</li><li>1 staf</li><li>Hingga 50 kunjungan per bulan</li></ul></article>
            <article class="co-price-card co-price-featured"><div class="co-featured-badge">UNTUK PRAKTIK MANDIRI</div><div class="co-plan-label">PALING FLEKSIBEL</div><h3>Individual</h3><p class="co-plan-description">Lebih leluasa untuk praktik sehari-hari.</p><div class="co-plan-price co-price-dynamic">{{ isset($minimumPrices['individual']) ? 'Rp '.number_format($minimumPrices['individual'], 0, ',', '.') : 'Cek harga' }}</div><p class="co-plan-price-note">{{ isset($minimumPrices['individual']) ? 'Harga mulai dari, detail saat pendaftaran' : 'Detail harga saat pendaftaran' }}</p><a class="co-btn co-btn-price-solid" href="{{ route('register.clinic') }}">Pilih Individual <span aria-hidden="true">↗</span></a><div class="co-price-divider"></div><p class="co-includes">SEMUA YANG DIBUTUHKAN</p><ul><li>1 dokter</li><li>Hingga 3 staf</li><li>Kunjungan tanpa batas</li><li>Dashboard manajemen</li></ul></article>
            <article class="co-price-card"><div class="co-plan-label">UNTUK KLINIK BERKEMBANG</div><h3>Team</h3><p class="co-plan-description">Ruang untuk tim dan layanan yang terus bertambah.</p><div class="co-plan-price co-price-dynamic">{{ isset($minimumPrices['team']) ? 'Rp '.number_format($minimumPrices['team'], 0, ',', '.') : 'Cek harga' }}</div><p class="co-plan-price-note">{{ isset($minimumPrices['team']) ? 'Harga mulai dari, detail saat pendaftaran' : 'Detail harga saat pendaftaran' }}</p><a class="co-btn co-btn-price-outline" href="{{ route('register.clinic') }}">Pilih Team <span aria-hidden="true">↗</span></a><div class="co-price-divider"></div><p class="co-includes">UNTUK SELURUH TIM</p><ul><li>Dokter tanpa batas</li><li>Staf tanpa batas</li><li>Kunjungan tanpa batas</li><li>Dashboard manajemen</li></ul></article>
        </div></div></section>

        <section class="co-section co-faq" id="faq" aria-labelledby="faq-title"><div class="co-container co-faq-layout"><div><span class="co-eyebrow">FAQ</span><h2 id="faq-title">Masih ada yang mau ditanyain?</h2><p>Beberapa hal yang biasanya perlu dipastikan sebelum mulai pakai ClinicOS.</p></div><div class="co-faq-items"><details><summary>Apakah ada paket gratis?<span aria-hidden="true">+</span></summary><p>Ada. Paket Free mencakup 1 dokter, 1 staf, dan hingga 50 kunjungan per bulan.</p></details><details><summary>Kalau klinik saya berkembang, bisa ganti paket?<span aria-hidden="true">+</span></summary><p>ClinicOS menyediakan paket Individual dan Team untuk kebutuhan pengguna dan kunjungan yang lebih besar. Pilih paket saat proses pendaftaran.</p></details><details><summary>Apakah akses staf dan dokter dibedakan?<span aria-hidden="true">+</span></summary><p>Ya. Akses dibatasi berdasarkan peran dan konteks klinik pengguna.</p></details><details><summary>Bagaimana cara mulai menggunakan ClinicOS?<span aria-hidden="true">+</span></summary><p>Daftarkan klinik, verifikasi akun, lalu ikuti onboarding untuk memilih paket dan menyiapkan operasional.</p></details></div></div></section>

        <section class="co-container co-final-wrap"><div class="co-final-cta"><div class="co-final-orbit" aria-hidden="true"></div><div><span class="co-eyebrow co-eyebrow-dark">LANGKAH BERIKUTNYA</span><h2>Yuk, bikin operasional klinik lebih tertata.</h2><p>Mulai dari pendaftaran klinik dan siapkan alur kerja untuk tim Anda.</p></div><a class="co-btn co-btn-white co-btn-large" href="{{ route('register.clinic') }}">Daftarkan klinik <span aria-hidden="true">↗</span></a></div></section>
    </main>

    
    <footer class="co-footer"><div class="co-container co-footer-inner"> <a class="co-brand co-footer-brand" href="{{ route('welcome') }}"> <img src="{{ asset('assets/logo/ClinicOS green ribbon C symbol.png') }}" alt="ClinicOS" class="co-footer-logo" > <span>Clinic<span class="co-brand-highlight">OS</span> </span> </a> <span class="co-footer-copyright"> © {{ date('Y') }} ClinicOS. Dibuat untuk operasional klinik.</span> <nav aria-label="Navigasi footer"><a href="#fitur">Fitur</a><a href="#harga">Harga</a><a href="#faq">FAQ</a><a href="{{ route('admin.login') }}">Masuk pengelola</a> </nav></div></footer>

    <script>
        // Menu mobile menggunakan JavaScript biasa, tidak bergantung pada Alpine.
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.querySelector('[data-co-menu-button]');
            const menu = document.getElementById('co-mobile-menu');
            if (!toggle || !menu) return;
            function setOpen(open) {
                menu.hidden = !open;
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            }
            toggle.addEventListener('click', function () { setOpen(menu.hidden); });
            menu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () { setOpen(false); });
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') setOpen(false);
            });
            document.addEventListener('click', function (event) {
                if (!menu.hidden && !menu.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 960) setOpen(false);
            });
        });
    </script>
</body>
</html>
