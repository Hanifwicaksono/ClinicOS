# ClinicOS — Status Proyek dan Development Plan

> **Diperbarui:** 5 Oktober 2026
>
> **Status:** MVP core feature-complete; release readiness masih berjalan
>
> **Arsitektur:** Laravel monolith, Blade + Livewire, Tailwind CSS, MySQL, Laravel Reverb
>
> **Target rilis saat ini:** satu klinik, satu instalasi, alur pelayanan lengkap
>
> **Dokumen detail implementasi:** [Development-Plan.md](Development-Plan.md)
>
> **Requirement produk:** [PRD.md](PRD.md)

---

## 1. Ringkasan Eksekutif

ClinicOS telah menyelesaikan seluruh modul fungsional utama MVP dari M1 sampai M6:

```text
Setup klinik
    → data dokter, resepsionis, jadwal, dan layanan
    → pasien dan booking
    → antrean
    → pemeriksaan dokter
    → rekam medis, diagnosis, tindakan, dan resep
    → finalisasi kunjungan dan nilai layanan
    → dashboard, notifikasi, realtime, dan audit log
```

MVP belum dinyatakan dirilis ke production. Milestone M7 masih membutuhkan penyelesaian UI akhir, simulasi staging end-to-end, infrastruktur production, backup/restore, observability, dokumentasi operasional, dan release sign-off.

Status yang digunakan dalam dokumen ini:

- **Selesai:** fitur telah diimplementasikan dan memiliki bukti verifikasi di repository.
- **Berjalan:** sebagian kriteria selesai, masih ada pekerjaan yang teridentifikasi.
- **Belum dimulai:** belum ada bukti implementasi yang cukup.
- **Perlu keputusan:** implementasi bergantung pada keputusan produk atau operasional.

---

## 2. Baseline Teknis Saat Ini

Versi berikut berasal dari lock file repository, bukan asumsi versi terbaru:

| Area | Implementasi saat ini |
| --- | --- |
| Runtime | PHP `^8.3` |
| Framework | Laravel `13.34.0` |
| UI | Blade, Livewire `3.8.10`, Volt `1.11.2`, Tailwind CSS |
| Authorization | Spatie Laravel Permission `8.3.0`, Policies, middleware role/permission |
| Realtime | Laravel Reverb `1.12.0`, Laravel Echo, private channels |
| Database | MySQL untuk aplikasi dan verifikasi integrasi; SQLite memory tersedia untuk test cepat yang sesuai |
| Testing | PHPUnit `12.5.37` |
| Repository | Git dengan remote GitHub `origin` telah dikonfigurasi |

Catatan environment saat pembaruan dokumen: folder dependency `vendor` tidak tersedia pada checkout aktif, sehingga hasil verifikasi terakhir mengacu pada catatan implementasi yang sudah tersimpan di [Development-Plan.md](Development-Plan.md), bukan test run baru pada sesi ini.

---

## 3. Status Milestone MVP

| Milestone | Status | Hasil utama | Bukti terakhir yang tercatat |
| --- | --- | --- | --- |
| M1 — Foundation | Selesai | Auth, verifikasi email, empat role, permission, akun aktif/nonaktif, audit auth | 66 tests / 260 assertions |
| M2 — Clinic Core | Selesai | Klinik, staff, dokter, jadwal, layanan, isolasi data klinik | 74 tests / 284 assertions |
| M3 — Patient & Booking | Selesai | Pasien, website publik, booking guest/staff, kuota, nomor antrean, token status | 84 tests / 329 assertions |
| M4 — Queue | Selesai | Check-in, call, skip, return, cancel, no-show, display publik | 93 tests / 380 assertions |
| M5 — Medical & Visit | Selesai | Visit, SOAP, vital sign, diagnosis, tindakan, resep, koreksi, finalisasi atomik | 103 tests / 455 assertions |
| M6 — Management | Selesai | Dashboard per role, filter periode, notifikasi, realtime, audit viewer | 112 tests / 497 assertions |
| M7 — Release | Berjalan | Security hardening dan regression selesai; staging dan production belum selesai | Lihat checklist M7 |

Selain test suite, milestone yang selesai telah dicatat melalui kombinasi Pint, Vite build, Blade view cache, migration/seed MySQL, pengujian transaksi/konkurensi, dan pemeriksaan browser sesuai ruang lingkup milestone.

---

## 4. Cakupan MVP yang Sudah Selesai

### 4.1 Foundation, Authentication, dan Authorization

- [x] Login, logout, reset password, perubahan profil, dan perubahan password.
- [x] Verifikasi email untuk akses dashboard.
- [x] Role Clinic Admin, Doctor, Receptionist, dan Patient.
- [x] Registrasi publik hanya memberikan role Patient.
- [x] Penolakan login dan penghentian sesi untuk akun nonaktif.
- [x] Permission matrix, middleware, serta policy resource.
- [x] Redirect dashboard sesuai role dan penanganan akun tanpa role.
- [x] Audit login/logout tanpa menyimpan kredensial sensitif.

### 4.2 Clinic Core

- [x] Profil klinik, slug, logo, kontak, timezone, jam operasional, dan status.
- [x] Pengelolaan akun dokter dan resepsionis.
- [x] Profil dokter, status aktif, dan relasi dokter–layanan.
- [x] Jadwal berbasis hari/sesi, jam, kuota, dan validasi bentrok.
- [x] Layanan, harga, dan status aktif.
- [x] Isolasi data berdasarkan klinik pada resource internal.

### 4.3 Patient dan Booking

- [x] Data pasien dan nomor rekam medis unik per klinik.
- [x] Pencarian, pagination, detail, edit administratif, dan riwayat kunjungan.
- [x] Halaman publik klinik untuk profil, dokter, layanan, dan jadwal aktif.
- [x] Booking guest dan booking oleh staf untuk pasien baru/lama.
- [x] Validasi dokter–layanan, sesi, tanggal, status klinik, dan kuota.
- [x] Snapshot nama/harga layanan saat booking.
- [x] Idempotency dan locking untuk mencegah booking/nomor ganda.
- [x] Status booking publik menggunakan token rahasia dan rate limiting.
- [x] Pembatalan tersinkron dengan antrean serta mengembalikan kuota.

### 4.4 Queue

- [x] Filter antrean berdasarkan klinik, dokter, tanggal, sesi, dan status.
- [x] Transisi `BOOKED → WAITING → CALLED → IN_PROGRESS → COMPLETED`.
- [x] Jalur alternatif `SKIPPED`, `CANCELLED`, dan `NO_SHOW`.
- [x] Check-in, call, skip, return to waiting, cancel, dan no-show.
- [x] FIFO per dokter/sesi/tanggal dan pencegahan pemanggilan bersamaan.
- [x] Dokter dibatasi pada antreannya sendiri.
- [x] Display publik hanya menampilkan data antrean minimal.
- [x] Audit setiap transisi status.

### 4.5 Visit dan Rekam Medis

- [x] Pembuatan Visit atomik saat dokter memulai pemeriksaan.
- [x] SOAP, pemeriksaan fisik, vital sign, diagnosis, tindakan, dan resep.
- [x] Draft rekam medis dan validasi lebih ketat saat finalisasi.
- [x] Policy medis: hanya dokter yang ditugaskan dapat melihat isi medis.
- [x] Rekam medis final tidak dapat diedit langsung.
- [x] Koreksi final wajib memiliki alasan dan revision snapshot.
- [x] Snapshot biaya kunjungan agar histori tidak berubah saat harga master berubah.
- [x] Finalisasi Medical Record, Visit, Appointment, dan Queue dalam satu transaksi.

### 4.6 Dashboard, Notification, Realtime, dan Audit

- [x] Dashboard Clinic Admin, Doctor, Receptionist, dan Patient.
- [x] Statistik pasien, booking, kunjungan, antrean, serta nilai layanan.
- [x] Filter tanggal memakai timezone klinik.
- [x] Notification center dan read state.
- [x] Notifikasi booking, pembatalan, panggilan, dan perubahan antrean.
- [x] Realtime queue melalui Reverb, Echo, event setelah commit, dan private channel.
- [x] Audit viewer untuk admin yang berwenang.
- [x] Audit untuk aksi penting dari auth sampai finalisasi kunjungan.

### 4.7 Security dan Regression yang Sudah Selesai

- [x] Form Request validation pada alur utama.
- [x] Authorization policy dan pemeriksaan ownership/clinic scope.
- [x] CSRF dan output escaping bawaan Laravel/Blade.
- [x] Rate limiting pada auth, booking, dan status publik.
- [x] Validasi upload logo.
- [x] Security headers untuk response web.
- [x] Konfigurasi production tidak membuat akun demo.
- [x] Regression suite, Pint, frontend build, Blade compile, dan pemeriksaan migration lokal tercatat lulus.

---

## 5. Release Gate MVP — Pekerjaan yang Masih Tersisa

Bagian ini adalah prioritas tertinggi. Pekerjaan post-MVP tidak dimulai sebelum release gate yang relevan selesai atau risikonya diterima secara eksplisit.

### M7.2 — UI dan Accessibility Final

- [ ] Audit responsive pada desktop, tablet, dan mobile untuk seluruh alur utama.
- [ ] Konsistenkan loading, empty, error, validation, dan confirmation state.
- [ ] Verifikasi keterbacaan form pemeriksaan serta rekam medis yang panjang.
- [ ] Verifikasi keyboard navigation, focus state, label form, dan kontras dasar.
- [ ] Uji kondisi data panjang, tabel sempit, dan koneksi realtime terputus.

**Definition of done:** tidak ada blocker usability P0/P1 pada alur utama dan seluruh role dapat menyelesaikan tugasnya pada viewport target.

### M7.4 — Staging dan UAT End-to-End

- [ ] Siapkan environment staging yang menyerupai production.
- [ ] Jalankan alur pasien baru, pasien lama, guest booking, dan walk-in.
- [ ] Jalankan check-in, call, skip, return, cancel, dan no-show.
- [ ] Jalankan pemeriksaan, draft, finalisasi, resep, serta koreksi rekam medis.
- [ ] Verifikasi dashboard, notification center, realtime lintas browser, dan audit log.
- [ ] Uji akses langsung lintas role, dokter, pasien, dan klinik.
- [ ] Dokumentasikan temuan, severity, owner, dan hasil retest.
- [ ] Dapatkan sign-off dari perwakilan operasional klinik.

**Definition of done:** seluruh skenario kritis lulus, tidak ada defect P0/P1 terbuka, dan aturan operasional sementara telah disetujui.

### M7.5 — Infrastruktur Production

- [ ] Tentukan target hosting dan topologi production.
- [ ] Konfigurasi domain, HTTPS, PHP, web server, MySQL, dan storage permission.
- [ ] Konfigurasi queue worker, scheduler, Reverb, dan mail bila digunakan.
- [ ] Pisahkan secret per environment dan nonaktifkan debug di production.
- [ ] Siapkan proses deployment, migration, restart worker, dan rollback.
- [ ] Tambahkan health check untuk web, database, queue, dan realtime.

**Definition of done:** deployment staging dapat diulang dari prosedur tertulis dan production smoke test lulus tanpa konfigurasi manual yang tidak terdokumentasi.

### M7.6 — Backup, Recovery, dan Observability

- [ ] Putuskan retensi data, frekuensi backup, enkripsi, dan pihak yang boleh mengakses backup.
- [ ] Tetapkan RPO dan RTO bersama pemilik klinik.
- [ ] Otomatiskan backup database dan file private yang relevan.
- [ ] Lakukan restore drill; backup tanpa uji restore belum dianggap selesai.
- [ ] Konfigurasi structured logging, error monitoring, dan alert minimum.
- [ ] Buat runbook insiden, recovery, serta rollback kode/migration.

**Definition of done:** restore berhasil pada environment terisolasi dan alert kritis mencapai penanggung jawab yang ditetapkan.

### M7.7 — Release MVP

- [ ] Tetapkan versi rilis dan changelog.
- [ ] Catat known limitations dan keputusan risiko yang diterima.
- [ ] Bekukan scope rilis dan lakukan final regression/smoke test.
- [ ] Deploy production dan verifikasi monitoring awal.
- [ ] Dapatkan release sign-off.

**MVP dinyatakan selesai hanya setelah M7.2, M7.4, M7.5, M7.6, dan M7.7 selesai.**

---

## 6. Keputusan Produk/Operasional yang Belum Final

Keputusan ini harus ditutup pada UAT atau sebelum fitur post-MVP yang bergantung padanya:

| Keputusan | Kondisi saat ini | Batas penyelesaian |
| --- | --- | --- |
| Pencocokan identitas pasien guest | Guest selalu membuat profil pasien baru; tidak memakai telepon/NIK sebagai bukti identitas | Sebelum patient portal diperluas |
| Aturan booking | Berbasis sesi, maksimum 30 hari, harga di-snapshot saat booking | Saat UAT operasional |
| Prioritas antrean | FIFO per dokter/sesi/tanggal; satu CALLED/IN_PROGRESS per sesi | Saat UAT operasional |
| Toleransi keterlambatan/no-show | No-show tersedia setelah sesi dimulai | Saat UAT operasional |
| Koreksi data medis | Alasan wajib dan revision snapshot; data final tidak dihapus | Sebelum production sign-off |
| Retensi dan pemulihan | Belum ditetapkan | Sebelum M7.6 selesai |
| Istilah “revenue” | Saat ini nilai layanan selesai, bukan pembayaran kas yang sudah diterima | Sebelum modul billing/reporting |

---

## 7. Prinsip Pengembangan Post-MVP

1. Production stability dan keamanan data medis lebih dahulu daripada perluasan fitur.
2. Setiap fase harus memiliki metrik keberhasilan, migration plan, test, monitoring, dan rollback plan.
3. API, event, dan model data distabilkan sebelum mobile app atau integrasi pihak ketiga.
4. Multi-tenant dan multi-branch tidak dipaksakan menjadi satu perubahan besar; isolasi, billing, dan migrasi diuji bertahap.
5. Fitur komunikasi harus menyimpan consent, delivery status, retry, dan audit.
6. Fitur keuangan membedakan nilai layanan, invoice, pembayaran, refund, dan settlement.
7. Offline-first hanya dikerjakan jika kebutuhan konektivitas klinik terbukti melalui riset.
8. AI hanya membantu administrasi/dokumentasi, tidak membuat keputusan klinis dan tidak boleh membuka data lintas pasien/klinik.

---

## 8. Roadmap Post-MVP

Roadmap memakai urutan dependensi, bukan tanggal kalender. Estimasi dibuat setelah kapasitas tim, hasil production baseline, dan prioritas bisnis tersedia.

### PM1 — Production Stabilization dan Operational Excellence

**Tujuan:** membuktikan bahwa ClinicOS stabil dipakai sehari-hari setelah MVP dirilis.

- [ ] Triage dan SLA defect berdasarkan severity.
- [ ] Monitoring error, latency, slow query, queue, Reverb, dan kapasitas storage.
- [ ] Audit index/query pada dashboard, antrean, audit log, dan riwayat pasien.
- [ ] Automated smoke test untuk flow kritis setelah deployment.
- [ ] Jadwal patch dependency dan security review berkala.
- [ ] Runbook support, incident, backup, restore, dan disaster recovery.
- [ ] Pengukuran baseline: uptime, error rate, waktu respons, booking success, dan queue update delay.

**Exit criteria:** minimal satu siklus operasional yang disepakati berjalan tanpa defect kritis; backup/restore serta alert telah diuji.

### PM2 — Reporting, Documents, dan Clinic Operations

**Tujuan:** meningkatkan pekerjaan administratif tanpa mengubah fondasi tenancy.

- [ ] Laporan kunjungan, pasien, layanan, dokter, antrean, dan nilai layanan per periode.
- [ ] Export CSV/Excel dengan authorization, filter, audit, dan batas ukuran.
- [ ] Export PDF/print untuk ringkasan kunjungan dan dokumen yang disetujui.
- [ ] Cetak resep dengan identitas klinik, dokter, pasien, dan nomor dokumen.
- [ ] Template dokumen serta penomoran yang dapat dikonfigurasi.
- [ ] Dashboard tren dan perbandingan periode.
- [ ] Peningkatan pencarian serta histori pasien nonmedis/medis sesuai policy.
- [ ] Data retention dan proses archival untuk audit/notifikasi lama.

**Exit criteria:** angka laporan direkonsiliasi dengan data transaksi sumber dan export besar tidak mengganggu request web.

### PM3 — Communication dan Patient Experience

**Tujuan:** mengurangi no-show dan memberi informasi yang jelas kepada pasien.

- [ ] Consent dan preferensi kanal komunikasi pasien.
- [ ] Email booking, perubahan jadwal, pembatalan, dan reminder.
- [ ] Integrasi WhatsApp melalui provider resmi setelah biaya dan template disetujui.
- [ ] Delivery log, retry, failure handling, rate limit, dan opt-out.
- [ ] Reminder terjadwal dan aturan anti-duplikasi.
- [ ] Patient portal untuk booking aktif, histori kunjungan yang aman, dan profil.
- [ ] Proteksi account linking dan recovery agar pasien keluarga tidak tertukar.

**Exit criteria:** delivery dapat ditelusuri, consent dapat dibuktikan, dan tidak ada data medis sensitif pada pesan yang tidak terenkripsi.

### PM4 — Billing dan Payment

**Tujuan:** memisahkan nilai layanan dari transaksi keuangan sebenarnya.

- [ ] Model invoice, invoice item, payment, refund, discount, dan adjustment.
- [ ] Status serta nomor invoice yang immutable dan dapat diaudit.
- [ ] Kasir, metode pembayaran, rekonsiliasi, dan laporan penerimaan.
- [ ] Hak akses keuangan terpisah dari akses isi rekam medis.
- [ ] Payment gateway hanya setelah flow manual stabil.
- [ ] Webhook idempotent, signature verification, retry, dan reconciliation job.

**Exit criteria:** nilai layanan, tagihan, pembayaran, refund, dan settlement dapat direkonsiliasi tanpa mengubah histori kunjungan.

### PM5 — API dan Integration Platform

**Tujuan:** menyediakan kontrak stabil untuk mobile app dan integrasi eksternal.

- [ ] Versioned API dan Eloquent API Resources.
- [ ] Token/session strategy per jenis client.
- [ ] Scope, rate limit, idempotency, audit, dan revocation.
- [ ] OpenAPI contract serta integration test.
- [ ] Event/webhook catalog dengan retry dan dead-letter handling.
- [ ] Data export/import terkontrol dan mapping identifier eksternal.

**Exit criteria:** kontrak API terversi, diuji, terdokumentasi, dan perubahan breaking memiliki migration policy.

### PM6 — SaaS Multi-Tenant dan Subscription

**Tujuan:** mengubah instalasi satu klinik menjadi platform yang dapat melayani banyak tenant dengan isolasi kuat.

- [ ] Pilih strategi tenancy dan dokumentasikan threat model.
- [ ] Tambahkan Super Admin pada control plane terpisah dari akses data medis tenant.
- [ ] Self-service clinic registration dan onboarding aman.
- [ ] Tenant provisioning, suspension, export, dan deletion workflow.
- [ ] Subscription plan, entitlement/feature flag, trial, renewal, dan grace period.
- [ ] Invoice subscription dan payment gateway.
- [ ] Per-tenant quota, usage metering, observability, backup, dan restore.
- [ ] Automated isolation test untuk HTTP, job, event, broadcast, cache, file, dan export.

**Exit criteria:** pengujian isolasi tenant lulus pada seluruh jalur data dan satu tenant dapat dipulihkan tanpa memengaruhi tenant lain.

### PM7 — Multi-Branch

**Tujuan:** satu organisasi dapat mengelola beberapa cabang tanpa mencampur konteks pelayanan.

- [ ] Model organization dan branch serta migration data klinik yang sudah ada.
- [ ] Assignment staff/dokter ke satu atau beberapa cabang.
- [ ] Jadwal, layanan, antrean, nomor dokumen, dan timezone per cabang.
- [ ] Aturan kepemilikan pasien dan akses cross-branch.
- [ ] Dashboard serta laporan cabang dan konsolidasi organisasi.
- [ ] Transfer/referral antar-cabang dengan audit.

**Exit criteria:** seluruh query operasional memiliki branch context yang eksplisit dan laporan konsolidasi dapat direkonsiliasi.

### PM8 — Mobile/PWA dan Offline Capability

**Tujuan:** menyediakan pengalaman mobile setelah API stabil; offline hanya berdasarkan kebutuhan nyata.

#### Patient App

- [ ] Login dan account recovery.
- [ ] Booking, pembatalan, status antrean, dan notification.
- [ ] Profil serta histori yang diizinkan.

#### Doctor App

- [ ] Login dengan keamanan perangkat.
- [ ] Jadwal, antrean, detail pasien yang berwenang, dan notification.
- [ ] Dokumentasi medis hanya setelah kontrol keamanan mobile disetujui.

#### Offline, jika tervalidasi

- [ ] Tentukan data minimum yang boleh berada di perangkat/server lokal.
- [ ] Enkripsi local storage, device enrollment, remote revoke, dan audit.
- [ ] Sync protocol, retry, tombstone, versioning, dan observability.
- [ ] Conflict detection/resolution per jenis data.
- [ ] Uji kehilangan perangkat, koneksi putus, clock drift, dan restore.

**Exit criteria:** mobile security review lulus; untuk offline, konflik dapat diprediksi dan tidak merusak rekam medis final.

### PM9 — Advanced Security dan Compliance Readiness

**Tujuan:** meningkatkan assurance seiring bertambahnya tenant, integrasi, dan data.

- [ ] Two-factor authentication untuk role berisiko tinggi.
- [ ] Device/session management dan forced logout.
- [ ] Encryption at rest serta key rotation strategy.
- [ ] Immutable/centralized audit trail dan anomaly alert.
- [ ] Vulnerability scanning, dependency policy, dan penetration testing.
- [ ] Periodic access review dan least-privilege review.
- [ ] Disaster recovery exercise berkala.
- [ ] Data classification, retention, consent, export, dan deletion policy sesuai yurisdiksi target.

**Exit criteria:** threat model dan kontrol diperbarui; temuan kritis/high ditutup atau diterima secara formal.

### PM10 — AI Assistance

**Tujuan:** membantu pekerjaan administratif dan dokumentasi setelah governance data matang.

- [ ] Riset kebutuhan dan evaluasi risiko per use case.
- [ ] Ringkasan riwayat medis dengan sumber yang dapat ditelusuri.
- [ ] Bantuan draft SOAP yang selalu memerlukan review dokter.
- [ ] FAQ administratif tanpa akses data medis yang tidak diperlukan.
- [ ] Ringkasan laporan manajemen.
- [ ] Consent, redaction, tenant isolation, prompt/output audit, dan retention policy.
- [ ] Evaluasi hallucination, bias, data leakage, dan human override.

**Exit criteria:** hasil AI tidak pernah otomatis menjadi keputusan klinis atau rekam medis final; kualitas dan risiko diukur dengan evaluation set yang disetujui.

---

## 9. Urutan dan Dependensi yang Direkomendasikan

```text
M7 Release MVP
    ↓
PM1 Production Stabilization
    ├──→ PM2 Reporting & Documents
    ├──→ PM3 Communication
    └──→ PM4 Billing
             ↓
        PM5 API Platform
             ↓
        PM6 SaaS Multi-Tenant
             ↓
        PM7 Multi-Branch
             ↓
        PM8 Mobile / Offline

PM9 Security & Compliance berjalan lintas fase
PM10 AI dimulai setelah governance, API, dan observability matang
```

Urutan praktis:

1. **Now:** selesaikan M7 dan rilis MVP dengan aman.
2. **Next:** stabilisasi production, reporting/documents, dan komunikasi pasien.
3. **Then:** billing serta API platform.
4. **Later:** SaaS multi-tenant, multi-branch, mobile/offline.
5. **Last/controlled pilot:** AI assistance.

---

## 10. Backlog Awal untuk Siklus Berikutnya

Siklus pengembangan berikutnya sebaiknya tetap fokus pada release gate, bukan langsung pada fitur baru:

1. Tutup keputusan retensi, backup, RPO, RTO, dan penanggung jawab insiden.
2. Siapkan staging yang setara dengan production.
3. Jalankan audit UI/accessibility seluruh role dan perbaiki blocker P0/P1.
4. Buat matriks UAT end-to-end beserta expected result dan bukti eksekusi.
5. Jalankan restore drill serta validasi queue, scheduler, Reverb, dan mail.
6. Susun runbook deployment/rollback dan lakukan rehearsal.
7. Lakukan final regression, production deployment, smoke test, dan sign-off.
8. Setelah satu baseline production tersedia, ukur data nyata untuk memprioritaskan PM1–PM4.

---

## 11. Definition of Done untuk Semua Fitur Baru

Sebuah fitur dianggap selesai jika:

- [ ] Requirement, actor, permission, success path, dan failure path telah jelas.
- [ ] UI dan API tidak membocorkan data lintas role, dokter, pasien, klinik, tenant, atau cabang.
- [ ] Validasi, policy, constraint, transaksi, dan idempotency diterapkan sesuai risiko.
- [ ] Aktivitas penting diaudit tanpa menyimpan secret atau isi medis yang tidak diperlukan.
- [ ] Test relevan mencakup perilaku utama, kegagalan penting, dan authorization.
- [ ] Perilaku concurrency diuji pada MySQL bila menggunakan lock/constraint.
- [ ] Migration aman untuk data existing dan memiliki rollback/forward-fix plan.
- [ ] Logging, metric, dan alert tersedia untuk kegagalan operasional penting.
- [ ] UI diperiksa pada viewport target dan memiliki state loading/empty/error yang sesuai.
- [ ] Pint, test terkait, frontend build, dan smoke test lulus.
- [ ] Dokumentasi operasional dan roadmap diperbarui bila perilaku atau scope berubah.

---

## 12. Target Akhir

Target terdekat bukan menambah semua fitur sekaligus, tetapi merilis MVP satu klinik yang aman dan dapat dioperasikan:

```text
Clinic Admin setup
    → Patient booking
    → Receptionist queue
    → Doctor examination
    → Medical record and prescription
    → Visit completed
    → Dashboard, notification, realtime, and audit
    → Backup, monitoring, and operational support
```

Setelah target tersebut terbukti stabil di production, ClinicOS dapat berkembang secara bertahap menjadi platform SaaS multi-tenant dan multi-branch dengan API, aplikasi mobile, integrasi komunikasi/pembayaran, serta AI assistance yang terkontrol.
