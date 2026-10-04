# ClinicOS — Development Plan Implementasi MVP

Tanggal: 4 Oktober 2026
Versi: 0.1
Status: Implementasi berjalan — M3 selesai
Sumber: [Plan.md](Plan.md) dan [PRD.md](PRD.md)

## 1. Tujuan dan cara menggunakan dokumen

Dokumen ini menerjemahkan roadmap Plan.md menjadi backlog implementasi yang dapat dikerjakan dan diverifikasi secara bertahap. PRD.md menjadi acuan perilaku produk; Plan.md menjadi acuan cakupan dan urutan pengembangan. Perbedaan kedua dokumen dicatat sebagai keputusan yang harus diselesaikan sebelum implementasi fitur terkait.

Target rilis adalah satu instalasi untuk satu klinik, dengan satu siklus pelayanan lengkap:

```text
Admin mengatur klinik, staf, jadwal, dan layanan
    → pasien mendaftar dan booking
    → nomor antrean diterbitkan
    → resepsionis melakukan check-in
    → dokter memanggil dan memeriksa pasien
    → SOAP, diagnosis, tindakan, dan resep disimpan
    → kunjungan selesai dan nilai layanan dicatat
    → dashboard dan audit log diperbarui
```

Setiap milestone memiliki dependensi, pekerjaan, dan kriteria selesai. Checklist hanya dicentang setelah implementasi dan verifikasi selesai. Kehadiran file atau dependency belum berarti fitur berjalan.

## 2. Kondisi awal proyek

Baseline berasal dari inspeksi file pada 4 Oktober 2026. Aplikasi, koneksi database, build, dan test suite belum diverifikasi pada saat penyusunan dokumen ini.

| Area | Bukti di repositori | Status untuk perencanaan |
| --- | --- | --- |
| Framework | composer.json menggunakan Laravel `^13.17` dan PHP `^8.3` | Sudah dikonfigurasi; runtime perlu diverifikasi |
| Prasyarat lokal | PHP 8.3.16 dan Composer 2.8.8 tersedia | Terverifikasi melalui CLI pada sesi ini |
| Frontend | Blade, Livewire `^3.6.4`, Volt `^1.7.0`, Tailwind, Vite | Fondasi tersedia; build perlu diverifikasi |
| Authentication | Breeze, halaman auth dan profile, LoginForm, Logout | Implementasi awal tersedia; perlu baseline tests |
| Role | Spatie Permission, HasRoles, RoleAndPermissionSeeder | Empat role dan permission awal tersedia |
| Akun aktif | Kolom is_active dan CheckUserIsActive | Perlu pengujian login dan sesi pengguna nonaktif |
| Dashboard | Dashboard umum dan controller Admin/Doctor/Receptionist | Routing dan view dashboard per role belum lengkap |
| Registrasi | Registrasi membuat User | Belum memberikan role kepada pengguna baru |
| Domain klinik | Model utama yang terlihat adalah User | Klinik, pasien, booking, antrean, dan medis belum tersedia |
| Realtime dan audit | Belum terlihat implementasi domain terkait | Belum dikerjakan |
| Pengujian | Test auth/profile bawaan tersedia; phpunit.xml memakai SQLite memory | Belum dijalankan; uji MySQL khusus diperlukan untuk konkurensi |
| Dokumentasi | Seluruh checklist Plan.md belum dicentang | Perlu sinkronisasi setelah verifikasi implementasi |

Working tree memiliki perubahan lokal yang sudah ada. Implementasi berikutnya harus mempertahankan perubahan tersebut dan membaca kembali file yang akan diedit.

## 3. Batas cakupan rilis

### Termasuk MVP

- Authentication, profil, akun aktif/nonaktif, dan akses berdasarkan role serta kepemilikan data.
- Profil klinik, jam operasional, akun dokter dan resepsionis, jadwal, layanan dan harga.
- Pasien, nomor rekam medis, website publik, booking, dan antrean.
- Kunjungan, SOAP, tanda vital, diagnosis, tindakan, dan resep berupa catatan.
- Snapshot harga layanan, nilai kunjungan, dashboard operasional, dan ringkasan pendapatan.
- Notifikasi web dasar, antrean realtime, audit log, keamanan dasar, responsive UI, dan deployment.

### Ditunda sampai MVP stabil

Multi-tenant penuh, Super Admin platform, subscription, payment gateway, multi-cabang, offline-first, mobile app, WhatsApp/SMS/push, integrasi BPJS/apotek/laboratorium, advanced analytics, ekspor lanjutan, dan AI.

Reverb tetap direncanakan pada tahap akhir MVP mengikuti Phase 14 Plan.md. Roadmap PRD menempatkan realtime pada fase enhancement; keputusan ini perlu diselaraskan di dokumen sumber sebelum cakupan rilis dikunci.

Audit, validasi, authorization, dan pengujian dasar diterapkan sejak fitur pertama. Milestone akhir memperluas dan memverifikasi semuanya. Fitur tersebut tidak ditunda seluruhnya sampai hardening.

## 4. Keputusan produk sebelum implementasi terkait

Isi kolom usulan merupakan titik awal diskusi, bukan keputusan pengguna yang sudah disetujui. Pekerjaan yang tidak bergantung pada keputusan tersebut dapat tetap dilakukan.

| ID | Keputusan | Usulan untuk MVP | Harus ditetapkan sebelum |
| --- | --- | --- | --- |
| D01 | Apakah akun pasien wajib? | Booking publik tanpa akun; akun Patient opsional. Registrasi publik tidak dapat membuat akun staf | M1 registrasi dan M3 booking |
| D02 | Identitas pasien lama dan pengaitan booking publik | Nomor telepon bukan identitas unik karena dapat dipakai keluarga. Pengaitan pasien lama memerlukan verifikasi; jangan tampilkan riwayat/identitas lama hanya berdasarkan kecocokan nama/NIK/telepon. NIK opsional sampai kebutuhan klinik dipastikan | M3 pasien dan booking |
| D03 | Booking memilih jam atau sesi? | Pasien memilih dokter, tanggal, dan sesi praktik; nomor antrean bukan jaminan jam pemeriksaan | M2 jadwal dan M3 booking |
| D04 | Ruang lingkup nomor dan urutan antrean | Nomor unik per klinik, dokter, tanggal, dan sesi. Online dan walk-in memakai sistem yang sama; urutan pelayanan serta prioritas pasien perlu ditentukan bersama klinik | M3 penerbitan nomor dan M4 pemanggilan |
| D05 | Pembatalan, no-show, dan skipped | CANCELLED/NO_SHOW terminal; SKIPPED dapat dikembalikan ke WAITING oleh petugas berwenang. Tentukan batas pembatalan, pelepasan kuota, dan aturan no-show | M3 booking dan M4 transisi |
| D06 | Siapa yang dapat memanggil dan menyelesaikan? | Resepsionis dan dokter dapat memanggil; dokter hanya antreannya sendiri. Mulai/selesai pemeriksaan oleh dokter yang menangani | M1 permission, M4 antrean, dan M5 medis |
| D07 | Akses admin ke isi rekam medis | Admin mengelola administrasi dan melihat ringkasan operasional; akses isi medis harus diberikan eksplisit. Resepsionis tidak mendapat akses isi medis. Dokter mendapat akses sesuai penugasan dan kebijakan riwayat pasien | M1 matriks akses dan M5 policy |
| D08 | Pengubahan rekam medis selesai | Draft dapat diedit dokter yang menangani; setelah finalisasi, koreksi tercatat dengan pembuat, waktu, dan alasan. Tentukan mekanisme koreksi tanpa menimpa catatan lama | M5 finalisasi |
| D09 | Definisi pendapatan dan harga | Ringkasan nilai layanan dari kunjungan COMPLETED; belum membuktikan uang sudah dibayar. Simpan harga saat booking dan aturan perubahan layanan sebelum finalisasi; tentukan biaya tindakan bila dibutuhkan | M3 harga dan M5 transaksi |
| D10 | Notifikasi pasien tanpa akun | Konfirmasi langsung dan halaman status booking berakses terbatas; notification center untuk pengguna berakun. Email memerlukan kanal dan alamat terverifikasi; WhatsApp tetap Post-MVP | M3 akses status dan M6 notifikasi |
| D11 | Hubungan dokter dengan layanan | Tetapkan layanan yang dapat ditangani setiap dokter, termasuk apakah perlu relasi many-to-many | M2 dokter/layanan dan M3 validasi |
| D12 | Operasional klinik | Tetapkan timezone klinik, sesi lintas tengah malam, hari libur, perubahan jadwal setelah booking, dan masa booking maksimal | M2 jadwal dan M3 ketersediaan |
| D13 | Retensi, backup, dan pemulihan | Tetapkan kebijakan penyimpanan/koreksi data medis, frekuensi backup, akses backup, dan target pemulihan bersama pemilik klinik | M5 lifecycle data dan M7 rilis |

Catat hasil setiap keputusan beserta tanggal di bagian log keputusan. Jangan mengubah usulan menjadi perilaku aplikasi secara diam-diam.

## 5. Aturan implementasi dan data

- Gunakan stack yang sudah ada. Verifikasi versi terpasang dan kompatibilitasnya pada M1 sebelum menambah dependency.
- Ikuti AGENTS.md. Laravel Boost sudah dipasang pada M1 dan guidelines hasil instalasi sudah dibaca. Baca ulang guidelines jika konfigurasi Boost diperbarui.
- Gunakan model/relasi Eloquent, migration bertahap, Form Request atau validasi Livewire, policy, service/action, Blade, dan komponen Livewire sesuai kebutuhan.
- Role memberikan kemampuan umum; policy memeriksa klinik, pasien, dokter yang ditugaskan, dan status resource. Jangan mengandalkan penyembunyian tombol sebagai authorization.
- Gunakan konteks klinik yang berasal dari pengguna/session atau slug yang sudah divalidasi. Jangan percaya clinic_id yang dikirim browser sebagai bukti hak akses.
- Desain domain memiliki clinic_id pada entitas milik klinik dan scope akses yang konsisten. Entitas anak memperoleh konteks melalui relasi induknya. Ini persiapan data, belum implementasi SaaS penuh.
- Pisahkan akun User dari profil Patient. Pasien tanpa akun tetap dapat dicatat; hubungan ke User nullable bila portal pasien diterapkan. Relasi akun staf ke klinik harus didefinisikan pada M2.
- Gunakan foreign key, index pencarian, unique constraint untuk nomor rekam medis dan nomor antrean sesuai scope, serta booking code unik.
- Gunakan nilai uang decimal atau integer rupiah secara konsisten; jangan menggunakan float untuk perhitungan biaya.
- Bedakan status Appointment dari Queue. Appointment: BOOKED, CANCELLED, COMPLETED, NO_SHOW. Queue: BOOKED, WAITING, CALLED, IN_PROGRESS, COMPLETED, CANCELLED, SKIPPED, NO_SHOW. Perubahan yang saling terkait harus disinkronkan dalam transaksi.
- Pembuatan booking, reservasi kuota, dan nomor antrean harus atomik dengan lock/constraint yang sesuai. Hindari pola membaca nomor terbesar lalu menambah satu tanpa proteksi konkurensi.
- Saat memulai pemeriksaan, buat atau ambil Visit secara idempotent. Finalisasi medis, kunjungan, harga, appointment, dan antrean dilakukan dalam transaksi agar tidak selesai sebagian.
- Nomor antrean yang terlihat tidak menjadi token akses. Gunakan token acak atau akses terverifikasi untuk detail booking, dengan data publik minimal.
- Jangan broadcast nama lengkap, NIK, SOAP, diagnosis, atau isi resep ke kanal publik. Kirim event setelah transaksi berhasil commit.
- Audit menyimpan aktor, klinik, aksi, resource, waktu, dan metadata yang diperlukan. Jangan mencatat password, token, atau isi medis lengkap ke log umum.
- Hindari penghapusan permanen data yang sudah direferensikan kunjungan; gunakan nonaktif/arsip dan kebijakan koreksi yang ditetapkan.

## 6. Ringkasan milestone dan dependensi

| Milestone | Pemetaan Plan.md | Hasil utama | Dependensi |
| --- | --- | --- | --- |
| M1 — Foundation | Phase 0–1 | Aplikasi terverifikasi, auth dan RBAC bekerja | Baseline, D01/D06/D07 |
| M2 — Clinic Core | Phase 2–5, manajemen resepsionis dari PRD | Klinik, staf, jadwal, dan layanan siap | M1; D03/D11/D12 |
| M3 — Patient & Booking | Phase 6–8, penerbitan nomor dari Phase 9 | Pasien dapat booking dan menerima nomor | M2; D01–D05/D09–D12 |
| M4 — Queue | Phase 9 | Check-in, panggil, skip, cancel, dan antrean dokter | M3; D04–D06 |
| M5 — Medical & Visit | Phase 10–11 | Pemeriksaan, rekam medis, resep, finalisasi biaya | M4; D07–D09/D13 |
| M6 — Management | Phase 12–15 | Dashboard, notifikasi, realtime, dan viewer audit | M5; D10 dan cakupan realtime |
| M7 — Release | Phase 16–19 dan final testing | MVP lolos simulasi dan siap operasional | M1–M6; D13 dan lingkungan deployment |

Urutan dependensi inti: M1 → M2 → M3 → M4 → M5 → M6 → M7. Fondasi audit dibangun di M1, identitas klinik pada audit di M2, dan log tiap fitur ditambahkan saat fiturnya dibuat. View riwayat pasien dibuat di M3; data kunjungan nyata dan akses riwayat medis baru lengkap setelah M5.

## 7. Backlog implementasi

### M1 — Foundation: environment, auth, role, dan akses

- [x] **M1.1 Baseline environment:** verifikasi dependency terpasang, Node/NPM, extension PHP, boot Laravel, koneksi MySQL, status migration, dan frontend build. Gunakan database testing terpisah; jangan menjalankan reset database operasional.
- [x] **M1.2 Agent setup:** pasang Laravel Boost dan baca ulang AGENTS.md sebelum perubahan kode aplikasi. Identifikasi aturan tambahan hasil instalasi.
- [x] **M1.3 Baseline verifikasi:** jalankan test auth/profile yang sudah ada; catat kegagalan, route aktif, dan bagian implementasi yang perlu dilengkapi. Periksa konfigurasi Tailwind/PostCSS/Vite yang saat ini tersedia.
- [x] **M1.4 Authentication:** validasi login/logout, reset password, profil, perubahan password, aturan verifikasi email, rate limiting, serta penolakan akun nonaktif saat login dan selama sesi aktif.
- [x] **M1.5 Role dan dashboard:** tetapkan matriks permission dari D06/D07; lengkapi route/view dashboard per role dan redirect setelah login. Tetapkan perilaku pengguna tanpa role dan multi-role. Registrasi mengikuti D01 tanpa hak memilih role staf.
- [x] **M1.6 Audit awal:** sediakan struktur audit dan pencatatan login/logout tanpa kredensial sensitif. Siapkan pengisian clinic_id setelah M2.

**Kriteria selesai:** build dan baseline tests berhasil; seluruh role mencapai halaman yang benar; akses dashboard role lain ditolak; akun nonaktif tidak mendapat sesi yang dapat dipakai; registrasi tidak menaikkan privilege; login/logout tercatat.

**Verifikasi utama:** login valid/invalid, rate limit, logout, reset password, redirect per role, pengguna tanpa role, akun nonaktif, dan request langsung ke route terlarang.

### M2 — Clinic Core: klinik, dokter, resepsionis, jadwal, layanan

- [x] **M2.1 Klinik:** migration/model clinics dan settings, konteks klinik untuk staf, profil/edit, slug unik, kontak, logo tervalidasi, timezone, dan jam operasional. Batasi akses melalui policy.
- [x] **M2.2 Akun staf:** admin membuat/memperbarui/menonaktifkan dokter dan resepsionis. Buat User dan profil Doctor secara transaksional, berikan role yang sesuai, dan batasi perubahan role. Tentukan pemberian kredensial awal tanpa password tetap pada production.
- [x] **M2.3 Jadwal:** doctor_schedules, hari/sesi, jam, kuota, status, validasi tumpang tindih, dan pemeriksaan kesesuaian jam operasional. Implementasikan ketersediaan sesuai D03/D12.
- [x] **M2.4 Layanan:** services, harga, status aktif, daftar/tambah/edit, dan hubungan dokter–layanan sesuai D11.
- [x] **M2.5 Layout bersama:** navigasi per role, form/table/badge dasar, pagination, empty state, loading/error state, dan responsive layout. Catat perubahan master data dalam audit.

**Kriteria selesai:** admin dapat menyiapkan satu klinik beserta dokter, resepsionis, sesi praktik, dan layanan. Pengguna lain tidak dapat mengubah konfigurasi atau mengambil data klinik lain melalui ID langsung. Dokter/layanan/jadwal nonaktif tidak dianggap tersedia.

**Verifikasi utama:** CRUD dan validasi, role assignment, transaksi akun dokter, upload logo, jadwal bentrok, nonaktif akun, harga tidak valid, dan policy antar-klinik menggunakan fixture testing.

### M3 — Patient & Booking: data pasien dan pendaftaran publik

- [x] **M3.1 Pasien:** patients, nomor rekam medis unik dalam klinik, formulir identitas, pencarian/pagination, edit administratif, dan detail pasien. Pengaitan akun serta deduplikasi mengikuti D01/D02. Riwayat kunjungan menampilkan empty state sampai M5 tersedia.
- [x] **M3.2 Website klinik:** `/clinic/{slug}` menampilkan profil, dokter aktif, layanan/harga aktif, dan sesi tersedia; hanya field publik yang ditampilkan.
- [x] **M3.3 Booking:** appointments dengan tanggal/sesi, dokter, layanan, pasien, kode unik, status, dan snapshot harga sesuai D09. Validasi dokter–layanan, hari/sesi, batas tanggal, status klinik, dan kuota.
- [x] **M3.4 Reservasi antrean:** sediakan tabel queues dan mekanisme nomor sesuai D04; booking dan nomor dibuat dalam transaksi yang sama. Cegah kuota terlampaui, nomor ganda, dan duplikasi akibat submit ulang.
- [x] **M3.5 Detail booking:** konfirmasi dan akses status terbatas, data pasien minimal, rate limiting, serta pembatalan sesuai D05. Jangan memakai nomor antrean/kode yang mudah ditebak sebagai satu-satunya bukti kepemilikan.
- [x] **M3.6 Booking oleh resepsionis:** gunakan aturan booking yang sama untuk pendaftaran walk-in dan pasien lama; lengkapi audit pembuatan/perubahan pasien serta booking.

**Kriteria selesai:** pasien baru dapat booking tanpa kebocoran data pasien lama; pasien lama digunakan hanya melalui pengaitan yang sah; nomor terbit otomatis; sesi penuh/nonaktif ditolak; submit bersamaan tidak melampaui kuota.

**Verifikasi utama:** pasien baru/lama, kegagalan validasi yang tidak meninggalkan data parsial, booking tanggal salah, dokter/layanan salah, pembatalan dan kuota, akses token salah, submit ulang, dan konkurensi pada MySQL testing.

### M4 — Queue: administrasi antrean dan pemanggilan

- [ ] **M4.1 Antrean harian:** filter klinik/dokter/tanggal/sesi/status dan daftar booking untuk check-in.
- [ ] **M4.2 Transisi status:** BOOKED → WAITING → CALLED → IN_PROGRESS → COMPLETED; tambahan CANCELLED/SKIPPED/NO_SHOW mengikuti D05. Simpan called_at dan timestamp relevan; tolak transisi yang tidak sah.
- [ ] **M4.3 Aksi petugas:** resepsionis check-in, panggil, skip, kembalikan antrean bila diizinkan, cancel, dan no-show; dokter melihat/memanggil antreannya sendiri sesuai D06.
- [ ] **M4.4 Konsistensi:** satu aksi panggil tidak memilih pasien yang sama pada dua request bersamaan. Penerbitan nomor, urutan pelayanan, dan prioritas mengikuti D04.
- [ ] **M4.5 Display publik:** tampilkan nomor/status minimal; audit setiap perubahan status. Aksi IN_PROGRESS dan COMPLETED dihubungkan ke workflow Visit pada M5, bukan finalisasi medis mandiri oleh resepsionis.

**Kriteria selesai:** check-in dan pemanggilan bekerja sesuai aturan, dokter hanya melihat antrean yang diizinkan, race condition pemanggilan ditangani, dan status terminal tidak dapat dikembalikan sembarangan.

**Verifikasi utama:** semua transisi sah/tidak sah, skipped/no-show/cancel, pemanggilan bersamaan, ownership dokter, akses publik minimal, dan sinkronisasi Appointment–Queue.

### M5 — Medical & Visit: pemeriksaan sampai finalisasi

- [ ] **M5.1 Visit lebih dahulu:** visits dan relasi pasien/dokter/appointment/layanan. Aksi mulai pemeriksaan membuat satu Visit dan memindahkan antrean ke IN_PROGRESS secara atomik; request berulang tidak membuat kunjungan ganda.
- [ ] **M5.2 Rekam medis:** medical_records, vital_signs, diagnoses, treatments, prescriptions, prescription_items, formulir SOAP, tanda vital, diagnosis, tindakan, dosis/frekuensi/jumlah/instruksi resep, dokter pembuat, dan timestamps.
- [ ] **M5.3 Akses dan riwayat:** policy akses/update sesuai D07, riwayat kunjungan yang relevan, serta pemisahan informasi administratif dari isi medis. Isi medis tidak tersedia melalui route antrean publik.
- [ ] **M5.4 Draft dan koreksi:** validasi saat simpan/finalisasi serta lifecycle rekam medis mengikuti D08/D13. Catat perubahan dan koreksi dengan aktor, waktu, dan alasan.
- [ ] **M5.5 Biaya kunjungan:** simpan snapshot harga, layanan yang diberikan, dan total_amount sesuai D09; perubahan harga master tidak mengubah kunjungan historis. Tidak menambahkan payment gateway untuk MVP.
- [ ] **M5.6 Finalisasi:** dalam satu transaksi, finalisasi rekam medis, Visit, Appointment, dan Queue menjadi selesai. Jika salah satu gagal, seluruh perubahan terkait dibatalkan. Tambahkan audit dan event setelah commit.

**Kriteria selesai:** dokter dapat melayani satu pasien sampai selesai dengan SOAP, diagnosis, tindakan, dan resep; kunjungan serta harga historis konsisten; resepsionis/pasien tidak dapat mengubah rekam medis; finalisasi ulang tidak menggandakan biaya atau data.

**Verifikasi utama:** ownership/role, mulai pemeriksaan ulang, validasi medis, simpan draft, koreksi, rollback finalisasi, klik selesai berulang, perubahan harga layanan, dan riwayat pasien sesuai hak akses.

### M6 — Management: dashboard, notifikasi, realtime, audit

- [ ] **M6.1 Dashboard:** admin melihat pasien/booking/kunjungan selesai/antrean aktif/nilai layanan; dokter melihat jadwal dan antreannya; resepsionis melihat booking/check-in/antrean. Definisikan label statistik dengan jelas agar pasien unik dan jumlah kunjungan tidak tertukar.
- [ ] **M6.2 Ringkasan periode:** gunakan timezone klinik dan filter tanggal; pendapatan bersumber dari snapshot kunjungan COMPLETED sesuai D09. Verifikasi agregat, query/index, dan akses per role.
- [ ] **M6.3 Notifikasi:** konfirmasi booking, pembatalan, pemanggilan, perubahan status, notification center dan read state untuk akun yang relevan. Kanal pasien tanpa akun mengikuti D10. Gunakan bentuk tabel database notification Laravel yang kompatibel dengan implementasi terpilih; schema PRD masih high-level.
- [ ] **M6.4 Realtime:** pasang/configure Reverb, QueueUpdated event setelah commit, private channel yang diotorisasi untuk staf/pasien, dan payload publik minimal. Tangani reconnect/refresh agar status terbaru kembali terbaca.
- [ ] **M6.5 Audit viewer:** halaman daftar/filter aktivitas untuk admin yang berwenang. Pastikan semua aksi penting dari M1–M5 tercatat dengan aktor dan klinik yang tepat.

**Kriteria selesai:** angka dashboard sesuai data fixture dan timezone; notifikasi sampai ke penerima yang benar; status diperbarui lintas browser; subscription kanal lain ditolak; audit dapat ditelusuri tanpa memuat rahasia atau isi medis berlebihan.

**Verifikasi utama:** agregat periode dan batas hari, kunjungan batal/no-show, perubahan harga, penerima/read state notifikasi, dua browser dengan role berbeda, reconnect, otorisasi channel, payload publik, dan kegagalan transaksi yang tidak mengirim event sukses.

### M7 — Release: hardening, simulasi, dan deployment

- [ ] **M7.1 Review keamanan:** input validation, policy seluruh resource, CSRF, rate limit auth/booking/status, escaping output, upload, session, akses antar-klinik, dan konfigurasi production tanpa debug/seeder password development.
- [ ] **M7.2 UI akhir:** desktop/tablet/mobile, navigasi, loading, empty/error state, pesan validasi, confirmation dialog, keterbacaan form medis, dan akses keyboard dasar.
- [ ] **M7.3 Regression:** jalankan test suite, frontend build, pemeriksaan format kode sesuai guidelines, dan uji MySQL untuk lock/constraint/konkurensi. Catat hasil dan perbaiki kegagalan relevan.
- [ ] **M7.4 Simulasi staging:** admin setup → pasien baru/lama/walk-in → check-in → panggil/skip/cancel/no-show → pemeriksaan → resep → selesai → dashboard/notifikasi/audit. Jalankan dengan akun masing-masing role.
- [ ] **M7.5 Infrastruktur:** environment staging/production, domain/HTTPS, Nginx/PHP/MySQL, storage permissions, queue worker, scheduler, Reverb, mail bila digunakan, dan proses restart worker setelah deploy.
- [ ] **M7.6 Operasional:** backup dan uji restore sesuai D13, logging/error monitoring dasar, health check, panduan setup dan penggunaan, serta prosedur deployment/rollback kode dan migration yang aman.
- [ ] **M7.7 Rilis:** dokumentasikan versi, hasil verifikasi, dan batasan yang masih berlaku; deployment production dilakukan setelah scope, lingkungan, dan kesiapan operasional disepakati.

**Kriteria selesai:** seluruh skenario inti berhasil di staging; tidak ada kegagalan akses atau integritas data yang menghalangi rilis; backup dapat direstore; queue/scheduler/realtime berjalan; production dikonfigurasi sesuai kebutuhan operasional.

## 8. Strategi pengujian dan definition of done per fitur

Pengujian dilakukan bersama implementasi, bukan hanya pada M7.

| Jenis pemeriksaan | Sasaran |
| --- | --- |
| Feature/Livewire tests | Aksi pengguna, validasi, response, dan perubahan data |
| Authorization tests | Role, ownership, clinic scope, request langsung, dan akses data sensitif |
| Unit tests terpilih | Aturan status, penomoran, atau perhitungan yang memiliki variasi penting |
| MySQL integration tests | Lock, transaksi, unique constraint, kuota, dan pemanggilan bersamaan |
| Build dan pemeriksaan manual | Halaman dapat dirender, assets tersedia, responsive, dan realtime lintas browser |
| Simulasi operasional staging | Siklus pelayanan lengkap dengan empat peran dan pemulihan backup |

SQLite memory yang sudah dikonfigurasi dapat dipakai untuk tests cepat yang sesuai. Hasil SQLite tidak menggantikan bukti perilaku konkurensi pada MySQL.

Satu fitur selesai apabila:

- [ ] Perilakunya memenuhi requirement PRD dan keputusan produk yang relevan.
- [ ] UI, validasi, policy, dan alur gagal tersedia.
- [ ] Relasi, constraint, dan transaksi menjaga integritas data.
- [ ] Aktivitas penting tercatat pada audit tanpa data sensitif yang tidak diperlukan.
- [ ] Tests yang relevan lulus dan UI diverifikasi bila ada perubahan tampilan.
- [ ] Migration aman untuk kondisi database yang ada; data operasional tidak direset.
- [ ] Checklist dan catatan implementasi diperbarui, termasuk keterbatasan yang ditemukan.

## 9. Risiko utama dan mitigasi yang harus dibuktikan

| Risiko | Mitigasi dan bukti |
| --- | --- |
| Kuota/nomor ganda pada booking bersamaan | Transaksi, lock/constraint, idempotensi, dan uji MySQL |
| Pasien keluarga tergabung karena telepon sama | Aturan D02 dan tests pengaitan identitas yang sah |
| Kebocoran antar-role/klinik | Policy dan scope server-side; tests ID langsung dan channel subscription |
| Status booking/antrean/visit berbeda | Transisi terpusat dan transaksi; tests rollback/finalisasi ulang |
| Riwayat pendapatan berubah saat harga diedit | Snapshot harga serta tests perubahan master layanan |
| Rekam medis berubah tanpa jejak | Lifecycle draft/final dan koreksi sesuai D08, dengan audit |
| Guest menerima data pasien lain | Token/akses terverifikasi, minimisasi payload, dan rate limiting |
| UI realtime menampilkan transaksi gagal | Event setelah commit dan tests penerima/payload |
| Backup tersedia tetapi tidak bisa digunakan | Uji restore ke lingkungan terpisah dan dokumentasi hasil |
| Cakupan melebar sebelum core flow selesai | Post-MVP tetap pada backlog terpisah; tutup milestone sebelum memperluas fitur |

## 10. Urutan sesi coding pertama

Sesi pertama berfokus pada M1, dengan hasil yang bisa ditinjau:

1. Verifikasi baseline environment, dependency, build, dan test suite.
2. Jalankan agent setup sesuai AGENTS.md dan baca hasil guidelines.
3. Tetapkan D01, D06, dan D07 agar registrasi dan permission tidak dibangun berdasarkan asumsi yang berbeda.
4. Lengkapi routing/view dashboard per role, redirect, dan perilaku pengguna tanpa role.
5. Pastikan akun nonaktif tidak dapat menggunakan aplikasi; verifikasi registrasi dan role assignment sesuai keputusan.
6. Tambahkan tests akses yang relevan dan fondasi audit login/logout.
7. Laporkan perubahan, hasil checks, dan pekerjaan M1 yang tersisa; mulai M2 setelah kriteria M1 terpenuhi.

Tidak ada estimasi tanggal selesai yang dikunci pada tahap ini. Estimasi dibuat setelah baseline M1 dan keputusan produk utama tersedia, berdasarkan kapasitas pengembangan serta hasil verifikasi nyata.

## 11. Tracking milestone dan keputusan

| Milestone | Status | Bukti selesai |
| --- | --- | --- |
| M1 | Selesai | 66 tests / 260 assertions lulus, build Vite, view:cache, migration MySQL, dan sinkronisasi permission |
| M2 | Selesai | 74 tests / 284 assertions lulus, build Vite, view:cache, migration dan seed MySQL, serta pemeriksaan browser tanpa error console |
| M3 | Selesai | 84 tests / 329 assertions lulus, build Vite, view:cache, migration dan seed MySQL, serta pemeriksaan browser halaman publik dan staf |
| M4 | Belum dimulai | — |
| M5 | Belum dimulai | — |
| M6 | Belum dimulai | — |
| M7 | Belum dimulai | — |

Status dapat berubah menjadi Dikerjakan, Perlu keputusan, Terverifikasi, atau Selesai. Cantumkan bukti berupa test/check dan skenario yang telah dijalankan.

| Keputusan | Hasil | Tanggal | Dampak pada backlog |
| --- | --- | --- | --- |
| D01 | Disetujui pengguna: akun Patient opsional; registrasi publik hanya Patient; booking dapat tanpa akun | 4 Oktober 2026 | Registrasi M1 diterapkan; booking menyusul M3 |
| D06 | Disetujui pengguna: dokter dan resepsionis boleh memanggil, dokter hanya antreannya sendiri | 4 Oktober 2026 | Permission queue.call diterapkan; ownership/transisi mengikuti M4–M5 |
| D07 | Disetujui pengguna: admin tidak otomatis mendapat akses isi medis; resepsionis tidak mendapat akses isi medis | 4 Oktober 2026 | Permission medis admin dihapus; policy rekam medis mengikuti M5 |
| D02–D05, D09–D12 | Default M3 diterapkan sementara: guest membuat profil baru tanpa pencocokan identitas, resepsionis memilih pasien lama secara eksplisit, booking berbasis sesi sampai 30 hari, nomor antrean per klinik/dokter/sesi/tanggal, pembatalan sebelum sesi melepaskan kuota, harga disalin saat booking, dan status guest memakai token rahasia | 4 Oktober 2026 | Validasi akhir bersama operasional klinik tetap diperlukan sebelum rilis |
| D08, D13 | Belum ditetapkan; lihat usulan di bagian 4 | — | Tetapkan sebelum M5 dan M7 |

### Hasil implementasi M3

- Data pasien dipisahkan dari akun User. Nomor rekam medis unik per klinik dibuat setelah insert, dan NIK tetap opsional serta tidak dipakai untuk mengautentikasi atau mengaitkan booking guest.
- Halaman publik klinik hanya memuat profil, layanan aktif, dokter aktif, dan jadwal aktif. Booking tersedia tanpa akun melalui dokter, layanan, sesi, tanggal, dan identitas pasien.
- `BookingService` mengunci sesi dokter di MySQL, memvalidasi ketersediaan serta hubungan dokter–layanan, lalu membuat pasien, appointment, dan queue dalam satu transaksi. Idempotency key mencegah submit ulang membuat booking ganda.
- Nomor antrean unik dalam scope klinik, dokter, sesi, dan tanggal. Appointment menyimpan nama serta harga layanan sebagai snapshot.
- Status publik hanya dapat dibuka dengan token 64 karakter yang hash-nya disimpan di database. Route publik diberi rate limit; kode booking dan nomor antrean tidak cukup untuk membuka detail.
- Resepsionis dan Clinic Admin dapat mencari/mengelola pasien serta membuat booking untuk pasien baru atau memilih pasien lama secara eksplisit. Pembuatan/perubahan pasien, booking, dan pembatalan dicatat dalam audit.
- Pembatalan hanya diizinkan saat status BOOKED dan sebelum sesi dimulai. Appointment dan queue dibatalkan bersama, dan kuota dapat digunakan kembali.
- Verifikasi akhir M3: 84 tests / 329 assertions pada MySQL, Pint, Vite build, Blade view cache, migration, seed, serta pemeriksaan browser responsive halaman publik dan daftar booking staf.

### Hasil implementasi M1

- Laravel terpasang versi 13.34.0; Livewire 3.8.10; Volt 1.11.2; Spatie Permission 8.3.0; Boost 2.10.1.
- Dashboard empat role sudah dapat dirender dan dilindungi auth, verified, permission, dan role. Dashboard masih berupa halaman awal; statistik domain mengikuti M6.
- `/dashboard` mengarahkan multi-role dengan prioritas Clinic Admin → Doctor → Receptionist → Patient. Pengguna tanpa role memperoleh pesan akses akun tanpa loop redirect dan dapat mengelola profil.
- Email verification diaktifkan melalui MustVerifyEmail mengikuti scaffold yang tersedia. Pengguna yang belum terverifikasi diarahkan ke `/verify-email` sebelum dashboard; profil tetap dapat diakses. Saat mail driver menggunakan log, tautan verifikasi ada di log lokal.
- Registrasi membuat role Patient dalam transaksi; akun staf tidak dapat dibuat melalui registrasi publik. Login menolak akun nonaktif sebelum sesi/remember token dibuat; sesi akun yang dinonaktifkan diakhiri middleware.
- Login/logout dicatat melalui auth events ke audit_logs; tidak menyimpan password/token. Audit tetap ada ketika akun dihapus. clinic_id pada audit masih nullable; konteks dan foreign key klinik mengikuti M2.
- Role seeder bersifat idempotent dan menggunakan syncPermissions agar permission lama yang tidak sesuai tidak tertinggal. Akun demo dipisah ke DemoUserSeeder, hanya berjalan pada environment local, dan tidak dibuat pada production.
- Baseline: test runner awal gagal karena pdo_sqlite tidak aktif. Dengan extension per proses, baseline menemukan lima kegagalan terkait default is_active. Default model/factory diperbaiki dan regression suite akhir lulus.
- Command test yang berhasil pada PHP Windows saat ini: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --no-progress`. Flag extension pada proses Artisan tidak diteruskan ke child test runner; gunakan command langsung ini atau aktifkan extension di PHP CLI sebelum `composer test`.
- Migration audit sudah diterapkan ke MySQL lokal, role/permission sudah disinkronkan. Tidak menjalankan migrate:fresh/reset database atau membuat ulang akun yang sudah ada.
- Policy kepemilikan resource klinik/medis akan dibuat saat resource M2/M5 tersedia. M1 memverifikasi akses dashboard dan kemampuan role; belum membuktikan isolasi data domain yang belum diimplementasikan.

Setelah sebuah milestone terverifikasi, sinkronkan checklist terkait di Plan.md. Jika scope/perilaku berubah, perbarui PRD.md dan dokumen ini agar requirement dan implementasi tetap selaras.
