# Analisis Arsitektur dan Kesenjangan ClinicOS

Tanggal: **24 Oktober 2026**. Baseline Git: branch `main`, HEAD `ff4647c`, termasuk perubahan lokal pengguna yang sudah ada. Laporan ini mendeskripsikan kondisi checkout, bukan hanya kode pada commit tersebut.

## 1. Kesimpulan utama

ClinicOS sudah menjadi monolith Laravel dengan domain klinis dan SaaS yang cukup luas. Autentikasi, role/policy, data klinik/staf/jadwal/layanan, pasien, booking, antrean, visit, SOAP, resep, koreksi RME, dashboard, audit, onboarding pemilik, subscription, dan integrasi pembayaran subscription DOKU sudah memiliki implementasi backend.

Namun, implementasi **belum memenuhi keseluruhan cakupan RPP web-only**. Kekurangan utama adalah persistence odontogram dan alergi, autosave nyata, Predictive Queue, billing/receipt pasien, pelaporan, discovery klinik, multi-cabang, dan Resilient Edge Mode. Beberapa bagian sudah memiliki UI presentasional sehingga tidak tepat dikategorikan sebagai fitur end-to-end yang selesai.

Baseline pengujian terbaru juga **belum hijau**: **186 test, 179 lulus, 5 gagal assertion, 2 error, 988 assertion** pada SQLite in-memory setelah extension yang diperlukan diaktifkan. Hasil historis dalam dokumentasi tidak menggantikan hasil ini.

Permintaan pengguna menetapkan **full Laravel + Blade + Tailwind CSS**. Penyebutan React pada RPP dicatat sebagai konflik acuan, bukan rekomendasi mengganti stack.

## 2. Cakupan dan sumber yang diperiksa

Penelusuran meliputi area source milik proyek: entry point/bootstrap, route, controller, Form Request, service, model/relasi, enum, policy, middleware, event/listener, job/notification, command/scheduler, konfigurasi, migration, factory/seeder, Blade/Volt, CSS/JavaScript, manifest dependency, serta inventaris dan pengujian yang tersedia. Dependency `vendor/` dan `node_modules/`, generated build/cache, log aplikasi, dan isi database pasien bukan objek audit source proyek.

Seluruh dokumen dalam `docs/` telah dibaca, termasuk batas integrasi yang dijelaskan penulisnya. Dokumen DOCX dibaca melalui isi XML paragraf/tabel; gambar diagram yang tertanam tidak dievaluasi secara visual. Laporan ini bukan audit keamanan menyeluruh, UAT, atau sertifikasi kesiapan production.

| Sumber | Isi dan kedudukan dalam analisis |
| --- | --- |
| Permintaan pengguna saat ini | SaaS multi-klinik, isolasi data, pemilik/resepsionis/dokter/pasien, Blade/Tailwind, desktop/mobile, analisis dahulu |
| `C:/Users/User/Downloads/RPP_ClinicOS_Revisi_Web_Only.docx` | Revisi 13 Oktober 2026, referensi SRS v1.0/MF-1–MF-7; web-only, React, tenant/cabang, kasir, Predictive Queue, RME General/Dental, Edge |
| [Development-Plan.md](Development-Plan.md) | Domain SaaS/subscription 15 Oktober, hasil hingga 16 Oktober; Free/Individual/Team, DOKU, owner eksplisit, read-only, reminder/backfill |
| [PRD.md](PRD.md) | Draft 4 Oktober; requirement AUTH/CLINIC/DOC/SERVICE/PAT/PUBLIC/QUEUE/MR/VISIT/DASH/NOTIF/AUDIT; SaaS masih post-MVP |
| [Plan.md](Plan.md) | Status/roadmap 8 Oktober; MVP satu klinik dan release gate; lebih lama daripada implementasi SaaS |
| [README.md](README.md) | Setup dan status lama M1; beberapa klaim tidak cocok dengan kode sekarang |
| [design (1).md](design%20%281%29.md) | Token UI, state komponen, responsivitas, accessibility; menyebut PDF kontrak dan spesifikasi v3.0 yang tidak tersedia di checkout |
| [docs/odontogram-allergy/README.md](docs/odontogram-allergy/README.md) | Bukti UI 22 Oktober; secara eksplisit belum menyimpan odontogram dan belum mempunyai sumber data alergi |
| [docs/rme-general-autosave/README.md](docs/rme-general-autosave/README.md) | Bukti field/status/hook autosave 22 Oktober; endpoint dan debounce autosave belum dibuat |
| [docs/prescription-workflow/README.md](docs/prescription-workflow/README.md) | Editor/preview resep, dialog finalisasi, Modification beralasan; belum ada katalog/interaksi obat |
| `AGENTS.md`, `CLAUDE.md`, `boost.json` | Aturan pengembangan; kedua file panduan identik sebelum pembaruan AGENTS pada tugas ini |

**Batas traceability:** SRS v1.0 lengkap, spesifikasi v3.0, PDF kontrak frontend, Figma, backlog Notion/GitHub, dan bukti deployment tidak disediakan sebagai sumber yang diperiksa. Pemetaan MF berikut memakai uraian RPP, bukan rekonstruksi acceptance criteria SRS yang tidak tersedia. Persentase pemenuhan atau KPI minimal 90% belum dapat dinyatakan.

## 3. Arsitektur dan struktur repository

### 3.1 Stack yang benar-benar ditemukan

| Area | Bukti saat analisis |
| --- | --- |
| PHP | CLI `8.5.5`; `composer.json` mensyaratkan `^8.5` |
| Laravel | `13.34.0` terpasang; constraint `^13.17` |
| UI server | Blade, Livewire `3.8.10`, Volt `1.11.2`, Alpine pada komponen |
| Styling/build | Tailwind `3.4.17`, Vite `8.3.4`, Laravel Vite Plugin `3.2.0` dari lock file frontend |
| Authorization | Spatie Permission `8.3.0`, route middleware, Form Request, policy |
| Realtime | Reverb `1.12.0`, Echo `2.6.1`, Pusher JS `8.6.0` |
| Database | MySQL menjadi acuan proyek dan konfigurasi PHPUnit default; driver SQLite tersedia sebagai opsi setelah extension CLI diaktifkan |
| Background | Laravel Queue; default konfigurasi queue/cache/session berbasis database, opsi Redis tersedia |
| Test/format | PHPUnit `12.5.37`, Pint `1.32.1` |

Versi PHP diverifikasi dengan `php -v`, paket PHP dengan `composer show --direct`, frontend dengan `package.json`/`package-lock.json`. Konfigurasi Redis/Reverb di source belum membuktikan service itu berjalan di deployment.

### 3.2 Bentuk aplikasi

```text
Browser desktop/mobile
  ├─ Blade + Livewire/Volt + Alpine + Tailwind
  └─ Echo/WebSocket + refetch HTML/polling
        ↓
Web routes → middleware → controller / Form Request → policy
        ↓
Services: booking, queue, visit/RME, staff, subscription, DOKU
        ↓
Eloquent + transaksi/lock/constraint → database bersama
        ├─ tenant: clinic_id / relasi kepemilikan
        ├─ public logo storage / private medical photo storage
        └─ audit, notification, jobs, subscription periods/payment events
```

Ini merupakan **monolith MVC dengan service layer**, bukan dua frontend React atau backend REST API terpisah. Terdapat endpoint web yang mengembalikan JSON untuk pencarian pasien dan aksi antrean, tetapi belum ada `routes/api.php`, API Resources, token API, atau kontrak API terversi. Pemisahan area dilakukan melalui folder dan prefix route, belum berupa paket modul mandiri.

| Lokasi | Tanggung jawab |
| --- | --- |
| `bootstrap/app.php`, `bootstrap/providers.php` | Routing, middleware aktif/nonaktif/security, alias role/permission/onboarding/subscription, CSRF exception webhook, health `/up` |
| `routes/web.php`, `routes/auth.php` | Public site, autentikasi Volt, onboarding, tenant operations, platform, subscription, notifikasi |
| `routes/channels.php` | Private channel user dan antrean klinik |
| `routes/console.php` | Reminder subscription setiap jam |
| `app/Http/Controllers/{Admin,Receptionist,Doctor,Patient,Onboarding,Platform}` | Orkestrasi halaman dan aksi berdasarkan area |
| `app/Http/Requests`, `app/Policies`, `app/Http/Middleware` | Validasi, hak akses resource, serta batas akses request |
| `app/Services` | Workflow dan transaksi domain, audit, pembatasan kapasitas, integrasi DOKU |
| `app/Models`, enum di `app/` | Entitas, relasi, cast, dan status |
| `database/` | 33 migration, factory dan seeder |
| `resources/views` | Shell, komponen bersama, halaman operasional/publik, auth/profil Volt |
| `resources/js` | Inisialisasi Echo, status koneksi, refetch, polling 5 detik, submit antrean via fetch |
| `resources/css/app.css`, `tailwind.config.js`, `public/css/clinicos-landing.css` | Token dan komponen visual, stylesheet khusus landing |
| `tests/` | 43 file `*Test.php`; 186 kasus dieksekusi termasuk data provider |

Route registry berisi **106 route keseluruhan**. `route:list --except-vendor` menampilkan 89; beberapa endpoint Volt/framework terklasifikasi vendor, sehingga angka itu bukan jumlah seluruh fitur aplikasi. Entitas aplikasi berjumlah **25 model**.

### 3.3 Model data dan tenancy

- Relasi inti: Clinic → User/Doctor/Service/Patient/Appointment/Queue/Visit; Doctor → DoctorSchedule dan many-to-many Service; Appointment → satu Queue dan maksimal satu Visit; Visit → satu MedicalRecord → VitalSign/Diagnosis/Treatment/Prescription/Revision.
- Domain komersial: Clinic → satu Subscription, SubscriptionPeriods, Payments, SubscriptionReminders; katalog SubscriptionPlan/SubscriptionPrice; PaymentEvents untuk deduplikasi provider.
- `clinics.owner_user_id` unik dan nullable untuk kompatibilitas; `subscriptions.clinic_id`, `visits.appointment_id`, serta `queues.appointment_id` unik. Nomor antrean unik per klinik/dokter/sesi/tanggal. Database belum mempunyai composite FK yang membuktikan seluruh relasi anak selalu satu tenant.
- Isolasi terutama memakai filter query eksplisit dan policy. Hanya `Patient` memiliki global `ClinicScope`; scope tidak memfilter bila tidak ada user/clinic context. Job, console, guest, dan Platform Admin membutuhkan pembatasan eksplisit.
- Seorang user staf terhubung satu klinik. Pasien dapat memiliki profil per klinik melalui `patients.user_id`; ini berbeda dari dukungan organisasi/cabang. Tidak ada model organization/branch atau assignment staf lintas klinik.
- Tenant memakai URL `/clinic/{slug}`; belum ada resolver domain/subdomain tenant.

### 3.4 Workflow dan batas akses yang terimplementasi

1. Owner mendaftar terpisah dari pasien, memverifikasi email, membuat klinik, lalu memilih Free/trial/checkout. Role pemilik adalah `Clinic Admin`; kepemilikan disimpan terpisah.
2. Booking memvalidasi layanan-dokter-sesi/tanggal/kuota, membuat profil pasien bila perlu, appointment dan queue `BOOKED` dalam transaksi. UUID idempotency dan snapshot nama/harga layanan disimpan.
3. Check-in mengubah queue ke `WAITING`; call mengikuti FIFO per dokter/sesi/tanggal, dengan pembatasan panggilan aktif. Skip/return/cancel/no-show tersedia.
4. **Dokter yang ditugaskan** memulai pemeriksaan dari `CALLED`; saat itu Visit dan draft RME dibuat. Check-in belum membuat Visit seperti alur RPP.
5. Dokter menyimpan draft atau memfinalisasi. Finalisasi mengubah RME, Visit, Appointment, Queue dalam satu transaksi; pengulangan finalisasi pada visit selesai tidak menyelesaikan dua kali.
6. Koreksi catatan final menyimpan alasan, pelaku, waktu, snapshot sebelum/sesudah; tidak mengembalikan status menjadi draft. Ini amendment berbasis histori, belum protokol version conflict.
7. DOKU membayar **subscription klinik kepada platform**. Signature notification, invoice, nominal, original request ID, status, transaksi, dan deduplikasi event diperiksa. Reconciliation tersedia sebagai aksi owner/platform; tidak ada jadwal rekonsiliasi otomatis di `routes/console.php`.
8. Subscription mengatur seat, kuota, trial/renewal/expiry. Expired/suspended membatasi tulis; visit aktif tetap dapat disimpan/diselesaikan. Flag fitur premium belum dipakai sebagai gate realtime/dashboard.

Clinic Admin/resepsionis mengakses administrasi, bukan isi RME. Dokter mengakses pemeriksaannya sendiri. Platform Admin mengakses metadata tenant/komersial tanpa akses route medis. Status booking guest memakai token yang di-hash; public queue tidak menampilkan identitas pasien. Proteksi ini terlihat pada source dan beberapa test negatif, bukan jaminan isolasi semua jalur yang belum diuji.

## 4. Pemetaan requirement dan implementasi

Status **Ada** berarti jalur implementasi tersedia; **Parsial** berarti ada bagian yang belum terhubung atau belum memenuhi seluruh uraian. Status **Belum ada** berarti belum ditemukan implementasi domain pada checkout. **Belum terverifikasi** dipakai untuk klaim yang memerlukan bukti lingkungan/browser, bukan source saja.

| Requirement/acuan | Status | Bukti dan selisih |
| --- | --- | --- |
| AUTH-1–6: auth, profil, akun aktif/nonaktif | Ada | `LoginForm`, auth/profil Volt, `CheckUserIsActive`, tests/Auth dan ProfileTest. Login memakai email; username terpisah tidak tersedia. |
| Role/capability tenant — MF-1 | Ada/parsial | Lima role dan policy; owner eksplisit, area platform terpisah. Tidak ada role kasir atau editor permission matrix di UI. |
| Tenant/branch isolation — MF-1 | Parsial | Isolasi klinik tersedia; organisasi/cabang dan pengujian lintas cabang belum ada. |
| CLINIC-1–4: profil/jam/logo/public info | Ada | Admin ClinicController, ClinicRegistrationService, ClinicSetting, public clinic view. |
| DOC-1–5: dokter/staf/jadwal | Ada | StaffAccountService, DoctorScheduleController/Request; relasi layanan dan validasi bentrok/jam operasional. Belum ada halaman detail kalender jadwal dokter mandiri. |
| SERVICE-1–5: layanan/harga/status | Ada | Admin ServiceController/Request, Service, website publik; histori harga memakai snapshot. |
| PAT-1–6: pasien/pencarian/administrasi/histori | Ada | Receptionist PatientController, nomor RM, pencarian/pagination, appointment history; histori isi medis dibatasi dokter yang menangani. |
| PUBLIC-1–7: halaman klinik/layanan/dokter/booking | Ada | PublicClinicController, PublicBookingController, Blade publik. Discovery/pencarian lintas klinik belum tersedia. |
| Booking/walk-in/idempotency — MF-2, QUEUE-1–3 | Ada/parsial | BookingService dan form staf/publik; guest baru tidak dicocokkan otomatis ke profil lama; pilihan dokter/layanan/sesi belum saling memfilter dinamis meski server memvalidasi. |
| Appointment vs Visit saat check-in — MF-2 | Berbeda | Source membuat Visit saat dokter mulai pemeriksaan; RPP menyebut create/link Visit saat check-in. |
| Queue states/FIFO/call/skip — MF-3, QUEUE-4–7 | Ada | QueueWorkflowService/Policy/Controller, QueueManagementTest. Start/complete hanya dokter; RPP juga menyebut resepsionis sebagai pelaku. |
| Realtime/fallback — MF-3, QUEUE-8 | Ada/parsial | QueueUpdated setelah commit, channel staff/public, Echo/refetch/polling/status koneksi. Belum uji WebSocket lintas browser pada sesi ini; expiry/suspension channel dan beban polling perlu evaluasi. |
| Predictive Queue + confidence — MF-3 | Parsial UI | `queue-number-display` menerima ETA min/max/confidence/stale; public queue mapping tidak memasok nilai itu. Tidak ditemukan estimator, cold-start logic, pembelajaran durasi, atau evaluasi akurasi. |
| RME General/vital/foto — MF-4, MR-1–6/MR-10–12 | Ada | VisitWorkflowService, request/policy, halaman visit, private photo endpoint. |
| Diagnosis/tindakan terstruktur — MR-7–8 | Parsial UI | Model, schema, validasi, service dan test mendukung array diagnosis/tindakan. Editor saat ini tidak mengirim `diagnoses[]`/`treatments[]`; assessment/rencana SOAP berupa teks. |
| Autosave RME — MF-4 | Parsial UI | Status dan custom event tersedia; belum ada pengiriman otomatis, debounce, retry autosave, atau version-conflict handling. Draft tersimpan lewat submit manual. |
| Odontogram/RME Dental — MF-4 | Parsial UI | 32 gigi FDI, kondisi, legend, read-only; `data-persists="false"`, tanpa field form maupun tabel persistence. Kondisi awal selalu `unknown`. |
| Critical allergy — MF-4 | Parsial UI | Komponen banner tersedia; VisitController mengirim `$criticalAllergies = []`. Belum ada sumber data alergi pasien. |
| Resep — MR-9, MF-4 | Ada/parsial | Obat free text, dosis/frekuensi/jumlah/instruksi, preview, validasi dan konfirmasi finalisasi. Belum ada katalog obat, pemeriksaan interaksi/alergi otomatis, atau cetak resep. |
| Finalize/amendment — MF-4, VISIT-1–6 | Ada/parsial | Transaksi dan revision snapshot tersedia; belum ada optimistic version identifier untuk mencegah stale editor menimpa perubahan lain. Retensi file pada revisi perlu diperiksa. |
| Dashboard — DASH-1–6 | Ada, regresi terbuka | Statistik pasien terjadwal/booking/antrean/visit/nilai layanan per periode; satu assertion hitungan pasien gagal pada SQLite. |
| Layanan paling banyak digunakan — DASH-7 | Belum ada | Belum ada query agregasi/ranking layanan atau tampilan terkait di dashboard admin. |
| Kasir, tagihan pasien, payment/receipt — MF-5 | Belum ada | Payment/invoice yang ditemukan hanya untuk subscription; Visit.total_amount bukan ledger pembayaran pasien. |
| Laporan/export — MF-5 | Parsial ringkasan | Ada ringkasan dashboard/audit filter; belum ada modul laporan, CSV/Excel/PDF, receipt, atau template cetak. |
| Patient Web — MF-6 | Parsial | Registrasi opsional, booking, daftar/detail/cancel appointment dan public queue tersedia. Dashboard pasien berupa pengantar/link; belum ada discovery, reminder kunjungan, dan tracker pribadi dengan ETA. |
| NOTIF-1/2/4: booking/status/notification center | Ada, test setup bermasalah | Database+broadcast kepada akun pasien terkait dan dokter; guest memperoleh halaman konfirmasi/token, bukan pengiriman pesan eksternal. Dua NotificationCenterTest error pada SQLite. |
| NOTIF-3: mendekati giliran/reminder pasien | Belum ada | Tidak ada trigger near-turn atau scheduled reminder appointment. Reminder terjadwal yang ada adalah subscription owner. |
| AUDIT-1–6 | Ada | Audit auth/domain, filter klinik dan metadata aman. Belum ada bukti audit storage append-only/tamper-evident atau backup/retensi production. |
| SaaS onboarding/subscription — MF-7 dan Development-Plan | Ada | Owner, Free/Individual/Team, trial, limit, renewal/read-only, backfill legacy, scheduler reminder. Feature entitlements belum lengkap. |
| DOKU subscription — Development-Plan | Ada, operasional belum diverifikasi ulang | Checkout/signature/deduplikasi/reconcile/refund manual tersedia, tests memakai fake provider. Sandbox historis tercatat; transaksi nyata tidak dijalankan pada analisis ini. |
| Platform suspend/revoke — Development-Plan | Parsial | Status `Suspended` dipahami domain, grant/katalog/view tenant tersedia; tidak ada aksi platform untuk suspend/unsuspend/revoke grant. Katalog UI hanya toggle aktif, bukan edit nominal/kapasitas. |
| Resilient Edge/sync/recovery — MF-7 | Belum ada | Tidak ditemukan Local API/DB, sync agent/outbox, device enrollment, conflict handling, recovery, atau tes WAN/LAN. Polling/reconnect browser bukan Edge Mode. |
| Responsive/accessibility — pengguna, RPP, design | Implementasi ada; belum terverifikasi lengkap | Breakpoint Tailwind/CSS, drawer/mobile menu, label/status/modal tersedia. Belum audit browser semua role/viewport atau keyboard/screen reader pada sesi ini. |
| Deployment/backup/observability/UAT | Belum terverifikasi | Health `/up`, logging, config worker/scheduler/Reverb ada; belum ditemukan pipeline CI/deploy, backup/restore drill atau bukti UAT terbaru di sumber yang diperiksa. |

## 5. Konflik spesifikasi dan dokumentasi

| Konflik | Kondisi yang dipakai untuk analisis |
| --- | --- |
| RPP dua frontend React vs permintaan Blade/Tailwind | Permintaan langsung pengguna dan implementasi sekarang menjadi dasar stack; requirement fungsional RPP tetap dibandingkan. |
| PRD/Plan satu klinik vs Development-Plan/kode SaaS | Kode sudah multi-klinik dengan owner/subscription/platform; status satu klinik bukan gambaran checkout saat ini. |
| PHP 8.3 di panduan/README/Plan vs `composer.json`/CLI | Checkout meminta PHP `^8.5` dan berjalan dengan CLI `8.5.5`; kompatibilitas 8.3 tidak terbukti. Panduan AGENTS diperjelas, dependency tidak diubah. |
| README mengklaim baru M1 dan dashboard placeholder | Source sudah memuat domain klinis dan komersial; dashboard pasien masih sederhana, dashboard staf tidak lagi semuanya placeholder. |
| Judul Development-Plan “M0–M4 ... menunggu sandbox” vs isi M5–M7 selesai/sandbox sukses | Dokumen bertentangan dengan dirinya sendiri; hasil historis diperlakukan sebagai catatan penulis, bukan verifikasi sesi ini. |
| PRD Basic/Pro/Business vs katalog Free/Individual/Team | Katalog dan Development-Plan menggunakan Free/Individual/Team; tidak perlu menambah paket PRD lama secara otomatis. |
| RPP Visit saat check-in; resepsionis start/complete vs kode | Kode memakai Visit saat start oleh dokter; perlu keputusan requirement sebelum mengubah workflow/permission. |
| Klaim UI “autosave/odontogram selesai” vs batas dokumen | Dokumen docs justru menjelaskan bahwa yang selesai adalah UI/hook; persistence dan integrasinya belum selesai. |
| README mengatakan seeder tidak mereset akun existing | `DemoUserSeeder::updateOrCreate()` menulis kembali password/status/verifikasi pada environment local. Jangan jalankan ulang seed sebagai langkah audit. |
| `docs/` diabaikan Git | Tiga README existing tetap tracked; file baru di bawahnya akan terkena ignore. Laporan baru diletakkan di root tanpa mengubah `.gitignore`. |

## 6. Temuan teknis yang perlu ditindaklanjuti

Prioritas berikut adalah usulan analisis, bukan persetujuan implementasi. **P1** berkaitan integritas/requirement inti atau verifikasi rilis; **P2** konsistensi fungsi/operasional; **P3** pemeliharaan. Tidak ada klaim eksploitasi yang dibuktikan pada sesi ini.

| ID | Prioritas | Temuan, bukti, dan dampak |
| --- | --- | --- |
| GAP-01 | P1 | Autosave, odontogram, dan allergy alert belum memiliki jalur penyimpanan/data penuh. Bukti: docs terkait, VisitController, dental-chart, autosave-status. Pengguna berisiko mengira perubahan UI tersimpan. |
| GAP-02 | P1 | Billing pasien/receipt, Predictive Queue, multi-cabang dan Edge belum memenuhi RPP. Keputusan scope diperlukan karena sebagian berbeda dari roadmap SaaS yang sudah dibangun. |
| GAP-03 | P1 | Belum ada optimistic locking/version check pada draft/amendment. Row lock menyerialkan transaksi tetapi tidak mendeteksi dua editor dengan data awal yang sama. Perlu test stale write sebelum autosave diperluas. |
| GAP-04 | P1 | `VisitWorkflowService::syncRecord()` menghapus file foto setelah commit, sementara revision snapshot menyimpan path/nama. Pada penghapusan foto melalui koreksi final, metadata versi lama tidak mempertahankan isi file. Ini konsekuensi source yang perlu keputusan retensi dan test khusus; skenario tidak dieksekusi dalam audit. |
| GAP-05 | P1 | `ClinicScope` tidak menyediakan proteksi universal; relasi tenant harus konsisten eksplisit. Beberapa factory membuat clinic/doctor/patient/schedule terpisah secara default. Audit lintas tenant perlu mencakup guest, job/console, storage, dan nested relation. Ini risiko desain, bukan bukti kebocoran yang sudah terjadi. |
| GAP-06 | P1 | Suite terbaru memiliki 7 kasus tidak lulus. Pisahkan mismatch assertion UI, setup SQLite, dan kemungkinan beda perilaku MySQL sebelum menyatakan regression selesai. |
| GAP-07 | P2 | `SubscriptionPlan.features` menyimpan `realtime`/`management_dashboard`, tetapi akses dashboard, channel, dan dispatch tidak memeriksanya. Pembatasan fitur Free vs berbayar belum sesuai Development-Plan. |
| GAP-08 | P2 | `EnsureSubscriptionWritable::clinic()` memilih klinik akun sebelum klinik resource publik. Untuk akun tenant A yang mengakses resource publik B, konteks pengecekan dapat berbeda. BookingService memeriksa klinik target saat create, tetapi jalur cancel publik tidak mengulang pemeriksaan subscription. Perlu test kombinasi akun/target/status sebelum menyatakan coverage read-only lengkap. |
| GAP-09 | P2 | Diagnosis/tindakan terstruktur tidak bisa diedit lewat form sekarang, walaupun backend mendukungnya. Dashboard juga belum menampilkan layanan terpopuler. |
| GAP-10 | P2 | BookingConcurrencyTest membuat dua booking berurutan, bukan proses bersamaan. SQLite run tidak membuktikan efektivitas `lockForUpdate`/deadlock retry MySQL atau performa lock klinik yang menyerialkan booking. |
| GAP-11 | P2 | `resources/js/realtime.js` menjalankan polling HTML setiap 5 detik, juga ketika WebSocket terhubung. Tidak ada bukti load test atau evaluasi request overlap. Halaman status booking guest tidak memasang refresh root/channel antrean sehingga tampilan personalnya belum live tracker. |
| GAP-12 | P2 | Landing memakai `assets/images/clinic-bg.jpg` yang tidak ditemukan; file lokal yang tersedia bernama `public/assets/logo/clinic-bg.png`. Jangan mengubah aset pengguna pada tugas analisis ini. |
| GAP-13 | P2 | Platform mempunyai enum suspended, tetapi tidak menyediakan route/aksi pengelolaan suspend/revoke. Reconciliation pembayaran hanya manual; scheduler hanya reminder subscription. |
| GAP-14 | P2 | Bukti backup, restore, staging/UAT, observability dan konfigurasi production belum tersedia. Dokumen historis tidak membuktikan runtime production saat ini. |
| GAP-15 | P3 | `vite.config.js` menyimpan alamat HMR LAN tertentu; kurang portabel antar komputer. README/Plan/heading Development-Plan dan panduan versi perlu diselaraskan setelah keputusan scope. |

## 7. Verifikasi yang benar-benar dijalankan

### 7.1 Pemeriksaan tanpa mutasi database aplikasi

- `git status --short`, inventaris file termasuk dokumen yang diabaikan pencarian default, `git ls-files docs`, dan pembacaan source.
- `composer show --direct`, `php -v`, pembacaan manifest/lock frontend.
- `php artisan route:list --except-vendor --no-interaction` dan `route:list --json --no-interaction`.
- Pembacaan semua tiga dokumen `docs/`, dokumentasi root, dan paragraf/tabel RPP DOCX.
- PHPUnit dengan SQLite in-memory; tidak menjalankan migrasi/seed pada database aplikasi atau transaksi gateway nyata.

Percobaan awal SQLite tanpa extension gagal karena driver tidak aktif; menambahkan `pdo_sqlite`/`sqlite3` menyelesaikan hambatan itu. Test foto memerlukan `gd`, yang kemudian diaktifkan hanya untuk proses CLI. Tidak ada perubahan `php.ini`/`.env`/dependency pada tugas ini. Flag `--no-interaction` yang diteruskan Artisan test tidak diterima PHPUnit versi ini, sehingga runner langsung digunakan.

### 7.2 Baseline final

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/bin/phpunit --no-progress --do-not-cache-result
```

**Hasil:** 186 test; 179 passed; 5 failed; 2 errors; 988 assertions; sekitar 36,6 detik; PHPUnit exit code 2.

| Kasus yang tidak lulus | Temuan terbaru |
| --- | --- |
| `ExampleTest::test_landing_page_contains_accessible_mobile_navigation` | Test mengharapkan `mobile-navigation`/“Buka navigasi”; markup sekarang memakai `co-mobile-menu`/“Buka menu”. Mismatch kontrak test, bukan bukti bahwa tombol mobile pasti rusak. |
| `LandingPageTest::test_landing_page_shows_live_platform_statistics_and_subscription_prices` | Assertion `1+` tidak cocok dengan statistik baru “1 klinik · 2 pasien tercatat”. Assertion berikutnya tidak tercapai; harga belum dapat dinyatakan gagal dari kasus ini. |
| `ManagementDashboardTest::test_admin_dashboard_uses_completed_visit_snapshots_and_isolates_clinics` | `unique_patients` menghasilkan 0, test mengharapkan 1 pada baris 46. Perlu cek representasi DATE/range pada fixture SQLite dan ulang pada MySQL; penyebab production belum disimpulkan. |
| `MedicalRecordAccessTest::test_only_assigned_doctor_can_open_medical_record` | Response untuk dokter penanggung jawab 200, tetapi test mengharapkan label “Anamnesa” yang kini diganti SOAP. Assertion authorization lanjutan pada test ini tidak tercapai. |
| `SecurityHeadersTest::test_web_responses_include_security_headers` | Halaman `/` membaca subscription catalog tetapi test tidak menyiapkan schema pada SQLite in-memory; 500 karena `subscription_plans` tidak ada. Belum merupakan bukti middleware header gagal. |
| `NotificationCenterTest::test_booking_and_queue_changes_notify_linked_patient_and_doctor` | Error tabel `permissions` tidak ada; setup `DatabaseTruncation` berbeda dari mayoritas `RefreshDatabase` dan tidak konsisten dengan suite SQLite in-memory. |
| `NotificationCenterTest::test_user_can_read_only_their_own_notification` | Error setup database yang sama; perilaku akses notification belum dibuktikan oleh run ini. |

Pemeriksaan ulang terfokus `--filter='ManagementDashboardTest|NotificationCenterTest'`: 4 test, 1 lulus, 1 gagal, 2 error, 10 assertion; hasil masalah tersebut berulang. Source dan test tidak diperbaiki pada tugas ini.

### 7.3 Batas verifikasi

- Belum menjalankan suite pada MySQL atau uji booking/payment multi-proses; hasil SQLite tidak boleh dianggap bukti locking MySQL.
- Belum menjalankan build Vite baru, browser responsive/accessibility, Reverb lintas perangkat, load test, worker/scheduler/mail nyata, transaksi DOKU sandbox/production, ataupun backup/restore.
- Tidak ada pengukuran KPI RPP seperti confidence/akurasi ETA atau 95% backlog Edge tersinkron dalam 2 menit, karena domain terkait belum tersedia.
- Hasil build, test, sandbox, screenshot/UAT yang disebut dokumen lama adalah bukti historis yang belum diverifikasi ulang dalam sesi ini.
- Pemeriksaan checksum sebelum/sesudah penulisan dokumentasi mencakup 357 file source, konfigurasi, manifest, aset, dan test: tidak ada perubahan atau penghapusan. `git diff --check` keseluruhan menemukan trailing whitespace pada perubahan lokal landing yang sudah ada (baris 19, 64, 86); bagian itu dipertahankan. Pemeriksaan diff AGENTS dan whitespace laporan baru dilakukan terpisah.

## 8. Urutan tindak lanjut yang disarankan

1. **Tetapkan baseline requirement:** selaraskan cakupan RPP vs SaaS Development-Plan, Blade sebagai stack, model satu klinik vs cabang, waktu pembuatan Visit, serta permission start/complete. Lengkapi SRS/kontrak yang belum tersedia sebelum mengklaim traceability penuh.
2. **Pulihkan baseline verifikasi:** selidiki tujuh kasus tidak lulus, tetapkan fixture/database test yang konsisten, lalu verifikasi alur penting dan concurrency pada MySQL terisolasi.
3. **Tutup integrasi klinis yang masih presentasional:** persistence odontogram/alergi, diagnosis/tindakan UI, autosave dengan version conflict, retensi lampiran, serta regresi tenant/permission.
4. **Lengkapi pengalaman pasien/antrean:** discovery, tracker personal, Predictive Queue dengan rentang/keyakinan, reminder kunjungan, stale/reconnect dan validasi multi-browser.
5. **Lengkapi operasional dan komersial:** billing/receipt pasien serta laporan bila masuk scope, entitlement premium, aksi suspend/revoke dan reconciliation sesuai keputusan produk.
6. **Tangani cabang/Edge sebagai pekerjaan arsitektur tersendiri** bila RPP tetap mengharuskannya; perlu desain identitas, conflict/idempotency/sync dan pengujian WAN/LAN, bukan sekadar menambah polling.
7. **Buktikan kesiapan rilis:** audit responsive/accessibility seluruh role, staging/UAT, backup/restore, queue/scheduler/Reverb/mail, observability, deployment/rollback dan sign-off.

Tidak ada langkah implementasi di atas yang dijalankan dalam tugas ini. Perubahan yang dibuat hanya pengayaan **AGENTS.md** dan laporan **PROJECT_ANALYSIS.md**. Perubahan aplikasi/dependency/aset yang sudah ada dari pengguna dipertahankan.
