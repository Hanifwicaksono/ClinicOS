<x-public-layout :title="$clinic->name.' — ClinicOS'">
    {{--
        Tampilan profil klinik / public clinic.
        Data dan route tetap berasal dari controller ClinicOS.
        Stylesheet terpisah agar desain halaman ini tidak memengaruhi area admin/dokter.
    --}}
    <link rel="stylesheet" href="{{ asset('css/clinicos-clinic.css') }}">

    <div class="co-clinic-page" id="konten-utama">
        <section class="co-clinic-hero" aria-labelledby="clinic-heading">
            {{-- Aset ini mengikuti file yang tercatat pada audit: public/assets/logo/clinic-bg.png --}}
            <div class="co-clinic-hero-photo" aria-hidden="true"
                style="background-image: url('{{ asset('assets/logo/clinic-bg.png') }}')"></div>
            <div class="co-clinic-hero-overlay" aria-hidden="true"></div>
            <div class="co-clinic-hero-glow" aria-hidden="true"></div>

            <div class="co-clinic-container co-clinic-hero-inner">
                <div class="co-clinic-hero-copy">
                    <a class="co-clinic-back" href="{{ route('welcome') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        ClinicOS <span aria-hidden="true">/</span> Profil klinik
                    </a>

                    <div class="co-clinic-identity">
                        <span class="co-clinic-logo">
                            @if ($clinic->logo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($clinic->logo_path) }}" alt="Logo {{ $clinic->name }}">
                            @else
                                <span aria-hidden="true">{{ str($clinic->name)->substr(0, 1)->upper() }}</span>
                            @endif
                        </span>
                        <span class="co-clinic-eyebrow"><span class="co-clinic-live-dot" aria-hidden="true"></span> PROFIL KLINIK</span>
                    </div>

                    <h1 id="clinic-heading">{{ $clinic->name }}</h1>
                    <p class="co-clinic-hero-description">{{ $clinic->description ?: 'Pelayanan kesehatan dengan pendaftaran yang mudah dan jadwal yang transparan.' }}</p>

                    @if ($clinic->address || $clinic->phone)
                        <div class="co-clinic-contact" aria-label="Informasi kontak klinik">
                            @if ($clinic->address)
                                <span class="co-clinic-contact-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                    {{ $clinic->address }}
                                </span>
                            @endif
                            @if ($clinic->phone)
                                <span class="co-clinic-contact-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7.4 3h3l1 4-2 2a15 15 0 0 0 5.6 5.6l2-2 4 1v3a3 3 0 0 1-3.3 3A17.5 17.5 0 0 1 4.5 6.3 3 3 0 0 1 7.4 3Z"/></svg>
                                    {{ $clinic->phone }}
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="co-clinic-hero-actions">
                        <a href="{{ route('public.booking.create', $clinic) }}" class="co-clinic-button co-clinic-button-mint">
                            Buat booking
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a href="{{ route('public.queue.index', $clinic) }}" class="co-clinic-button co-clinic-button-glass">
                            Lihat antrean
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 5h11v14H8zM5 8h3M5 12h3M5 16h3M11 10h5M11 14h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </div>

                <aside class="co-clinic-overview" aria-label="Ringkasan informasi klinik">
                    <div class="co-clinic-overview-heading">
                        <span class="co-clinic-overview-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V7l8-4 8 4v13M4 20h16M9 20v-7h6v7M8 9h1m6 0h1" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div><span class="co-clinic-mini-label">LAYANAN KLINIK</span><strong>Siap bantu kunjungan Anda</strong></div>
                    </div>
                    <div class="co-clinic-stats">
                        <div class="co-clinic-stat">
                            <span class="co-clinic-stat-icon co-clinic-stat-icon-green" aria-hidden="true">✚</span>
                            <strong>{{ $clinic->services->count() }}</strong>
                            <span>Layanan aktif</span>
                        </div>
                        <div class="co-clinic-stat">
                            <span class="co-clinic-stat-icon co-clinic-stat-icon-cream" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0" stroke-linecap="round"/></svg>
                            </span>
                            <strong>{{ $clinic->doctors->count() }}</strong>
                            <span>Dokter praktik</span>
                        </div>
                    </div>
                    <div class="co-clinic-steps">
                        <div class="co-clinic-steps-header"><span>ALUR KUNJUNGAN</span><span class="co-clinic-step-badge">3 langkah</span></div>
                        <div class="co-clinic-step"><span class="co-clinic-step-number">01</span><div><strong>Pilih layanan</strong><small>Lihat biaya dan durasi layanan</small></div></div>
                        <div class="co-clinic-step"><span class="co-clinic-step-number">02</span><div><strong>Atur jadwal</strong><small>Sesuaikan dengan sesi dokter</small></div></div>
                        <div class="co-clinic-step"><span class="co-clinic-step-number">03</span><div><strong>Pantau antrean</strong><small>Ikuti informasi antrean klinik</small></div></div>
                    </div>
                    <div class="co-clinic-overview-foot"><span class="co-clinic-overview-foot-dot" aria-hidden="true"></span> Informasi layanan terhubung ke ClinicOS</div>
                </aside>
            </div>
        </section>

        <div class="co-clinic-benefits" aria-label="Akses cepat">
            <div class="co-clinic-container co-clinic-benefit-inner">
                <a href="#layanan"><span class="co-clinic-benefit-symbol" aria-hidden="true">✚</span><span>Jelajahi layanan klinik</span><span aria-hidden="true">↗</span></a>
                <a href="#dokter"><span class="co-clinic-benefit-symbol" aria-hidden="true">◷</span><span>Cek jadwal praktik</span><span aria-hidden="true">↗</span></a>
                <a href="{{ route('public.queue.index', $clinic) }}"><span class="co-clinic-benefit-symbol" aria-hidden="true">☷</span><span>Informasi antrean</span><span aria-hidden="true">↗</span></a>
            </div>
        </div>

        <section class="co-clinic-section co-clinic-services" id="layanan" aria-labelledby="services-heading">
            <div class="co-clinic-container">
                <div class="co-clinic-section-heading">
                    <div>
                        <span class="co-clinic-section-kicker">LAYANAN TERSEDIA</span>
                        <h2 id="services-heading">Perawatan yang Anda butuhkan,<br><span>semua ada di sini.</span></h2>
                    </div>
                    <p></p>
                </div>

                <div class="co-clinic-service-grid">
                    @forelse ($clinic->services as $service)
                        <article class="co-clinic-service-card">
                            <div class="co-clinic-service-top">
                                <span class="co-clinic-service-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18" stroke-linecap="round"/></svg>
                                </span>
                                <span class="co-clinic-service-index">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }} / LAYANAN</span>
                            </div>
                            <h3>{{ $service->name }}</h3>
                            <p>{{ $service->description ?: 'Informasi lengkap layanan dapat dilihat saat proses booking.' }}</p>
                            <div class="co-clinic-service-footer">
                                <div><small>BIAYA LAYANAN</small><strong>Rp {{ number_format((float) $service->price, 0, ',', '.') }}</strong></div>
                                <span class="co-clinic-duration">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    {{ $service->duration_minutes }} menit
                                </span>
                            </div>
                            <a class="co-clinic-service-link" href="{{ route('public.booking.create', $clinic) }}" aria-label="Buat booking untuk {{ $service->name }}">Buat booking <span aria-hidden="true">↗</span></a>
                        </article>
                    @empty
                        <div class="co-clinic-empty"><span aria-hidden="true">✚</span><h3>Layanan belum tersedia</h3><p>Belum ada layanan aktif yang ditampilkan oleh klinik.</p></div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="co-clinic-section co-clinic-doctors" id="dokter" aria-labelledby="doctors-heading">
            <div class="co-clinic-container">
                <div class="co-clinic-section-heading">
                    <div>
                        <span class="co-clinic-section-kicker">DOKTER & JADWAL</span>
                        <h2 id="doctors-heading">Kenali dokter dan<br><span>waktu praktiknya.</span></h2>
                    </div>
                    <p></p>
                </div>

                <div class="co-clinic-doctor-grid">
                    @forelse ($clinic->doctors as $doctor)
                        <article class="co-clinic-doctor-card">
                            <div class="co-clinic-doctor-top">
                                <div class="co-clinic-doctor-avatar" aria-hidden="true">{{ str($doctor->user?->name ?? 'Dokter')->substr(0, 1)->upper() }}</div>
                                <div class="co-clinic-doctor-details">
                                    <h3>{{ $doctor->user?->name ?? 'Dokter' }}</h3>
                                    <p>{{ $doctor->specialization ?: 'Dokter' }}</p>
                                </div>
                                <span class="co-clinic-doctor-tag">DOKTER</span>
                            </div>
                            <div class="co-clinic-schedules">
                                <div class="co-clinic-schedules-title"><span>JADWAL PRAKTIK</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/></svg></div>
                                <div class="co-clinic-schedule-list">
                                    @forelse ($doctor->schedules as $schedule)
                                        <div class="co-clinic-schedule">
                                            <span>{{ $schedule->day_name }}</span>
                                            <strong>{{ substr($schedule->start_time, 0, 5) }} – {{ substr($schedule->end_time, 0, 5) }}</strong>
                                        </div>
                                    @empty
                                        <p class="co-clinic-schedule-empty">Jadwal praktik belum tersedia.</p>
                                    @endforelse
                                </div>
                            </div>
                            <a href="{{ route('public.booking.create', $clinic) }}" class="co-clinic-doctor-link">Lanjut ke booking <span aria-hidden="true">↗</span></a>
                        </article>
                    @empty
                        <div class="co-clinic-empty"><span aria-hidden="true">◷</span><h3>Jadwal dokter belum tersedia</h3><p>Silakan cek kembali informasi praktik di lain waktu.</p></div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="co-clinic-container co-clinic-last-wrap" id="booking" aria-labelledby="booking-heading">
            <div class="co-clinic-last-cta">
                <div class="co-clinic-last-orbit" aria-hidden="true"></div>
                <div class="co-clinic-last-copy">
                    <span class="co-clinic-last-label">LANGKAH SELANJUTNYA</span>
                    <h2 id="booking-heading">Sudah tahu mau periksa kapan?</h2>
                    <p>Atur kunjungan di {{ $clinic->name }} lewat proses booking ClinicOS.</p>
                </div>
                <a href="{{ route('public.booking.create', $clinic) }}" class="co-clinic-button co-clinic-button-white">
                    Buat booking
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </div>
        </section>
    </div>
</x-public-layout>
