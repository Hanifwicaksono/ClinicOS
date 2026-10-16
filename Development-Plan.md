# ClinicOS — Development Plan SaaS Multi-Tenant & Subscription

Tanggal: 15 Oktober 2026
Versi: 1.0
Status: M0–M4 kode selesai — validasi sandbox DOKU menunggu kredensial merchant dan URL publik

## 1. Fokus pengembangan

Fokus berikutnya adalah mengubah ClinicOS menjadi SaaS multi-tenant yang memungkinkan pemilik klinik:

1. mendaftarkan akun dan memverifikasi email;
2. mendaftarkan satu klinik;
3. memilih Free, trial, atau paket berbayar;
4. membayar melalui DOKU Checkout bila memilih paket berbayar; dan
5. memperoleh akses hanya untuk klinik yang didaftarkan setelah pembayaran terverifikasi.

Fitur klinis yang sudah ada dipertahankan. Milestone lama tidak dilanjutkan dalam dokumen ini kecuali perubahan SaaS menyentuhnya. Implementasi tidak boleh mereset atau menghapus data klinik, pengguna, pasien, booking, antrean, kunjungan, maupun rekam medis yang sudah ada.

## 2. Keputusan produk yang dikunci

### 2.1 Model tenant dan kepemilikan

- Satu akun pemilik hanya memiliki satu klinik.
- Satu klinik memiliki satu subscription aktif pada satu waktu.
- Subscription melekat pada klinik, bukan pada akun pengguna.
- Pemilik dicatat secara eksplisit pada klinik dan juga memiliki role `Clinic Admin`.
- Hanya pemilik klinik yang dapat memilih paket, melakukan pembayaran, melihat riwayat pembayaran, dan memperpanjang subscription.
- Dokter, resepsionis, dan pasien tidak dapat mengelola subscription.
- Paket berlaku untuk satu klinik/lokasi. Multi-cabang memerlukan subscription terpisah dan belum termasuk cakupan ini.
- Data tenant tetap menggunakan satu database dan dibedakan oleh `clinic_id`; tidak diperlukan dependency tenancy baru.

### 2.2 Registrasi dan onboarding

- Registrasi pemilik klinik dipisahkan dari registrasi pasien.
- `Daftarkan Klinik` membuat calon pemilik klinik; registrasi pasien tetap membuat akun `Patient`.
- Satu alamat email hanya dapat digunakan oleh satu akun.
- Alur pemilik: daftar akun → verifikasi email → isi data klinik → pilih paket → bila berbayar, bayar melalui DOKU → akses aktif setelah status pembayaran sah.
- Free dan trial tidak memerlukan pembayaran.
- Klinik dan role pemilik dibuat dalam transaksi agar onboarding tidak menghasilkan tenant setengah jadi.
- Pengguna yang belum menyelesaikan onboarding hanya dapat membuka langkah onboarding, profil, dan logout.

### 2.3 Paket dan harga peluncuran

| Paket | Hak akses | 1 bulan | 6 bulan | 12 bulan |
| --- | --- | ---: | ---: | ---: |
| Free | 1 dokter, 1 resepsionis, maksimal 50 kunjungan per bulan | Rp0 | — | — |
| Individual | 1 dokter, maksimal 3 staf di luar pemilik, kunjungan tanpa batas | Rp149.000 | Rp799.000 | Rp1.399.000 |
| Team | Akun dokter dan staf tanpa batas, kunjungan tanpa batas | Rp499.000 | Rp2.699.000 | Rp4.999.000 |

Aturan paket:

- Akun pasien tidak dihitung sebagai akun dokter atau staf.
- Seluruh paket berbayar memperoleh fitur aplikasi yang sama; harga berbeda berdasarkan kapasitas tim dan durasi.
- Team tidak memiliki batas jumlah akun, tetapi hanya boleh digunakan oleh satu klinik/lokasi.
- Free tetap menyediakan data pasien, booking, antrean, rekam medis, resep, dan audit dasar. Audit keamanan dan akses terhadap data medis sendiri tidak boleh dijadikan fitur berbayar.
- Realtime dan dashboard manajemen lengkap tersedia pada trial dan paket berbayar. Free menggunakan pengalaman dasar tanpa realtime premium.
- Harga yang ditampilkan kepada pelanggan adalah jumlah yang dibayar. Biaya gateway DOKU tidak ditampilkan sebagai baris terpisah dan ditanggung platform.
- Dukungan PPN dan faktur pajak belum dikunci; harga harus disimpan sebagai integer rupiah dan dapat diubah tanpa mengubah invoice lama.
- Paket atau harga yang sudah pernah dipakai tidak dihapus permanen. Gunakan status aktif/nonaktif agar riwayat pembayaran tetap utuh.

Harga ini terinspirasi pola paket eHealth: paket individual berdasarkan kapasitas tenaga medis dan paket team untuk akun lebih luas, tetapi disesuaikan dengan fitur ClinicOS saat ini. Paket lifetime tidak dibuat karena hosting, backup, penyimpanan data medis, dan keamanan tetap menimbulkan biaya berkelanjutan.

### 2.4 Trial dan freemium

- Setiap klinik hanya berhak atas satu trial Team selama 14 hari tanpa kartu pembayaran.
- Trial dicatat server-side dan tidak dapat diulang dengan mengubah paket.
- Setelah trial berakhir tanpa pembayaran, klinik otomatis beralih ke Free.
- Jika jumlah dokter atau staf melebihi batas Free, operasional baru ditahan sampai pemilik menonaktifkan akun tambahan.
- Pemilik tetap dapat membuka halaman subscription dan menonaktifkan akun untuk memenuhi batas paket.
- Batas 50 kunjungan Free dihitung ketika pemeriksaan dimulai pada bulan kalender sesuai timezone klinik.
- Booking yang dibatalkan atau no-show tidak mengurangi kuota kunjungan.
- Ketika kuota Free tercapai, booking baru ditutup, tetapi booking yang sudah ada tetap dapat diperiksa dan diselesaikan agar pelayanan pasien tidak terputus.
- Data lama tidak dihapus, disembunyikan, atau dipindahkan ketika trial berakhir.

### 2.5 Perpanjangan dan kedaluwarsa

- Subscription berbayar tidak diperpanjang otomatis; pemilik melakukan checkout ulang.
- Pembayaran saat subscription masih aktif menambahkan periode baru setelah tanggal akhir saat ini.
- Pembayaran setelah subscription berakhir memulai periode pada saat pembayaran sukses.
- Masa aktif berakhir pada akhir hari berdasarkan timezone klinik agar akses tidak terputus di tengah hari operasional.
- Paket berbayar yang kedaluwarsa masuk mode read-only, bukan otomatis turun ke Free.
- Semua pengguna klinik tetap dapat login dan membaca data yang memang diizinkan oleh role dan policy mereka.
- Public booking dan seluruh perubahan operasional ditutup saat read-only.
- Hanya pemilik yang dapat membuka checkout/perpanjangan; Platform Admin dapat memberi masa aktif manual.
- Data tidak dihapus saat subscription kedaluwarsa.

### 2.6 Platform Admin

- Tambahkan role `Platform Admin` dengan area `/platform` terpisah dari area tenant.
- Platform Admin mempunyai `clinic_id = null` dan tidak boleh menggunakan route operasional klinik.
- Platform Admin dapat mengelola paket dan harga, melihat tenant, status onboarding, subscription, invoice, pembayaran, serta memberikan atau mencabut akses manual dengan alasan wajib.
- Platform Admin dapat melihat statistik agregat nonmedis, misalnya jumlah tenant, subscription aktif, dan pembayaran.
- Platform Admin tidak boleh membuka isi rekam medis, SOAP, diagnosis, resep, lampiran medis, atau melakukan impersonation terhadap pengguna klinik.
- Semua perubahan paket, grant manual, suspend, dan koreksi pembayaran dicatat dalam audit.

### 2.7 Pembayaran DOKU

- Gunakan DOKU Checkout agar kanal pembayaran mengikuti kanal yang aktif pada akun merchant tanpa integrasi terpisah per bank atau e-wallet.
- Gunakan sandbox terlebih dahulu; kredensial disimpan melalui environment dan tidak masuk repositori.
- Redirect/callback browser hanya menampilkan status dan tidak boleh mengaktifkan subscription.
- Subscription hanya diaktifkan setelah HTTP Notification DOKU memiliki signature valid, invoice cocok, nominal cocok, dan status pembayaran final yang diterima.
- `Request-Id` dan identitas event disimpan unik agar notification duplikat bersifat idempotent.
- Payload notification diproses non-strict agar field tambahan dari DOKU tidak merusak integrasi.
- Untuk DOKU Checkout, status gagal dari satu metode pembayaran tidak langsung menutup invoice bila pelanggan masih dapat memilih metode lain.
- Sediakan rekonsiliasi status untuk pembayaran pending ketika notification terlambat atau hilang.
- Refund tidak tersedia sebagai aksi mandiri pelanggan. Refund diproses manual oleh Platform Admin dan dicatat tanpa menghapus riwayat pembayaran.

Referensi implementasi:

- [DOKU Checkout](https://developers.doku.com/accept-payments/doku-checkout)
- [DOKU HTTP Notification](https://developers.doku.com/get-started-with-doku-api/notification)
- [DOKU notification best practices](https://developers.doku.com/get-started-with-doku-api/notification/best-practice)

## 3. Baseline dan dampak ke aplikasi saat ini

ClinicOS sudah memiliki `Clinic`, `clinic_id` pada sebagian besar entitas operasional, policy antar-klinik, dan pengujian isolasi pada beberapa alur. Perubahan ini memperluas fondasi tersebut; bukan mengganti aplikasi klinis.

Area yang harus disentuh:

- registrasi dan verifikasi email;
- model `Clinic` dan `User` untuk kepemilikan eksplisit;
- role, permission, route, middleware, dan dashboard;
- pembuatan dokter/staf serta batas paket;
- booking publik dan internal;
- awal pemeriksaan untuk kuota Free;
- seluruh route tulis tenant untuk mode read-only;
- notification, scheduler, mail, audit, dan konfigurasi deployment;
- factory, seeder, serta tests yang membuat klinik atau Clinic Admin.

Perubahan lokal yang sudah ada pada fitur rekam medis/foto harus dipertahankan. Implementasi subscription tidak boleh menimpa atau mereset perubahan tersebut.

## 4. Desain data minimum

Nama akhir mengikuti konvensi project setelah sibling migration dan model diperiksa. Struktur minimum yang direncanakan:

### `clinics`

- Tambahkan `owner_user_id` yang unik dan nullable selama migrasi data lama.
- Setelah seluruh tenant lama dipetakan, owner wajib untuk tenant baru.
- `owner_user_id` menjadi sumber otorisasi subscription; jangan mengandalkan role saja.

### `subscription_plans`

- Kode stabil: `free`, `individual`, `team`.
- Nama, batas dokter, batas staf, batas kunjungan bulanan, entitlement fitur, dan status aktif.
- Nilai `null` pada batas berarti tanpa batas.

### `subscription_prices`

- Plan, durasi dalam bulan, harga integer rupiah, dan status aktif.
- Invoice menyimpan snapshot nama paket, durasi, serta harga agar perubahan katalog tidak mengubah histori.

### `subscriptions`

- Satu record keadaan subscription per klinik.
- Plan saat ini, status, trial, waktu mulai/akhir, dan sumber aktivasi terakhir.
- Status minimum: `trialing`, `free`, `active`, `expired`, `suspended`.

### `subscription_periods`

- Riwayat append-only untuk trial, pembayaran, dan grant Platform Admin.
- Menyimpan plan, waktu mulai/akhir, sumber, payment nullable, aktor nullable, dan alasan manual.

### `payments`

- Klinik, subscription price, invoice number unik, request ID unik, provider `doku`, nominal snapshot, status, payment URL, waktu kedaluwarsa, waktu bayar, dan referensi provider yang aman.
- Status minimum: `pending`, `paid`, `expired`, `failed`, `refunded`.
- Jangan menyimpan secret key, signature mentah yang tidak diperlukan, nomor kartu, atau data pembayaran sensitif.

### `payment_events`

- Identitas event unik, payment, status provider, waktu diterima/diproses, dan hasil proses.
- Simpan metadata minimum yang dibutuhkan untuk audit dan rekonsiliasi; jangan menjadikan payload mentah sebagai sumber kebenaran permanen.

## 5. Aturan authorization dan isolasi tenant

- Semua query tenant harus dibatasi oleh clinic context yang tervalidasi server-side.
- `clinic_id` dari request tidak pernah dipercaya sebagai bukti kepemilikan.
- Audit seluruh model dan route yang memiliki data klinik; isolasi tidak boleh hanya bergantung pada tampilan atau hidden input.
- Platform Admin tidak memperoleh bypass generik ke model medis.
- Pemilik klinik tidak otomatis memperoleh akses isi medis hanya karena ia membayar subscription; policy medis yang ada tetap berlaku.
- Middleware subscription memeriksa kemampuan menulis, sedangkan policy tetap memeriksa role dan kepemilikan resource.
- Mode read-only menolak POST/PUT/PATCH/DELETE operasional di server, termasuk request langsung ke route.
- Pengecualian tulis saat read-only hanya untuk login/logout, profil akun, pembayaran/perpanjangan, dan tindakan pemilik untuk memenuhi batas paket.
- Public clinic dan public queue boleh tetap dibaca; public booking ditutup untuk tenant read-only atau suspended.
- Pesan penolakan tidak membocorkan paket, pembayaran, atau data tenant lain.

## 6. Milestone implementasi

### M0 — Baseline dan scope aman

- [x] Catat status working tree dan pertahankan perubahan pengguna yang sudah ada.
- [x] Jalankan test relevan dan build sebagai baseline sebelum perubahan SaaS.
- [x] Audit seluruh model, controller, policy, route, factory, dan seeder yang membaca atau menulis data tenant.
- [x] Tetapkan migration path tanpa `migrate:fresh`, reset, atau penghapusan data.
- [x] Pastikan konfigurasi queue, scheduler, dan mail tersedia untuk notification pembayaran serta pengingat.

Kriteria selesai: baseline tercatat, titik akses tenant terpetakan, dan tidak ada perubahan data sebelum migration plan diverifikasi.

#### Hasil M0 — 15 Oktober 2026

**Baseline dan working tree**

- PHP 8.3.26, Laravel 13.34.0, Livewire 3.8.10, Spatie Permission 8.3.0, database MySQL, broadcasting Reverb, queue database, dan mail SMTP berhasil dimuat.
- `npm run build` lulus menggunakan Vite 8.3.2.
- `php artisan test --compact` menjalankan 119 tests / 530 assertions: 118 lulus dan 1 gagal.
- Kegagalan baseline berada di `DashboardAccessTest::test_users_reach_their_own_dashboard` untuk Clinic Admin tanpa klinik. Test masih mengharapkan teks `Ringkasan klinik`, sedangkan UI saat ini menampilkan onboarding `Siapkan identitas klinik Anda`.
- Perubahan lokal fitur rekam medis/foto, migration foto, error view, route, dan tests terkait sudah ada sebelum M0 dan tidak diubah.
- Runner PHPUnit harus dijalankan di luar sandbox Windows pada sesi ini karena `is_readable()` mengembalikan `false` untuk `vendor/autoload.php` di dalam sandbox meskipun file dapat di-require.

**Peta tenant saat ini**

- Entitas dengan `clinic_id` langsung: `User`, `Doctor`, `ClinicSetting`, `Service`, `Patient`, `Appointment`, `Queue`, `Visit`, dan `AuditLog`.
- `DoctorSchedule` memperoleh tenant melalui `Doctor`; `MedicalRecord` dan data klinis anak memperoleh tenant melalui `Visit`, `Patient`, atau `Doctor`.
- Controller operasional umumnya memfilter `clinic_id` secara eksplisit dan route model binding dilanjutkan dengan policy.
- Policy appointment, queue, patient, staff, service, schedule, visit, dan medical record memeriksa klinik atau kepemilikan dokter/pasien.
- Channel realtime antrean membandingkan `clinic_id`, permission, dan role. Notification pengguna diambil melalui relasi milik user.
- Booking publik memvalidasi clinic, doctor, schedule, service, patient, appointment, dan queue di dalam `BookingService`; halaman status memakai access token hash.
- Seeder domain dibatasi ke environment local. Database lokal berisi satu klinik, satu Clinic Admin, dan tidak ada klinik tanpa Clinic Admin.

**Temuan yang wajib dibawa ke M1–M3**

- Belum ada owner klinik eksplisit; Clinic Admin saat ini hanya terhubung melalui `users.clinic_id`.
- Global `ClinicScope` hanya digunakan oleh `Patient` dan bergantung pada user yang sedang login. Scope ini tidak boleh menjadi fondasi tunggal SaaS karena proses queue/console dan Platform Admin dengan `clinic_id = null` tidak memperoleh filter tenant.
- Factory `Doctor`, `Appointment`, dan `Queue` dapat membuat relasi dari klinik berbeda secara default. Fixture harus dibuat konsisten sebelum tests subscription mengandalkannya.
- Route publik dan write route tenant belum mengetahui status subscription; enforcement baru boleh diaktifkan setelah tenant lama mendapat grant.
- `QueueSeeder` memilih appointment seeder terbaru tanpa filter klinik. Seeder ini local-only, tetapi perlu dibuat deterministik saat fixture tenant diperbarui.
- Queue dan mail sudah dikonfigurasi, tabel jobs/failed jobs/notifications tersedia, tetapi belum ada scheduled task. Reminder subscription dibuat pada M6.

**Jalur migrasi aman**

1. Tambahkan `owner_user_id` nullable dan tabel paket/subscription/payment secara additive; jangan mengubah atau menghapus data klinis.
2. Deploy model dan relasi yang dapat membaca schema lama maupun baru sebelum enforcement diaktifkan.
3. Jalankan backfill idempotent untuk memilih Clinic Admin tenant lama sebagai owner dan membuat grant Team 12 bulan dalam transaksi.
4. Verifikasi setiap klinik memiliki tepat satu owner, subscription/grant, serta relasi yang valid; hentikan proses bila ada tenant ambigu.
5. Aktifkan constraint untuk tenant baru dan middleware subscription hanya setelah backfill terverifikasi.
6. Rollback sebelum enforcement hanya menghapus schema SaaS yang baru dan tidak menyentuh tabel klinis. Setelah payment production tercatat, rollback dilakukan secara forward-fix agar histori komersial tidak hilang.

Tidak menjalankan `migrate`, `migrate:fresh`, reset, seed, atau perubahan data pada M0. Seluruh 27 migration yang ada berstatus `Ran`, termasuk migration foto rekam medis yang sudah ada di working tree.

### M1 — Ownership dan Platform Admin

- [x] Tambahkan ownership eksplisit pada klinik dan relasi model terkait.
- [x] Tambahkan role/permission Platform Admin tanpa akses medis.
- [x] Buat area platform terpisah dengan dashboard agregat nonmedis.
- [x] Sediakan daftar tenant dan detail nonmedis tenant.
- [x] Tambahkan policy yang mencegah Platform Admin masuk ke area operasional klinik.
- [x] Perbarui factory/seeder agar owner, Clinic Admin, dan Platform Admin dibuat secara eksplisit.
- [x] Tambahkan audit untuk perubahan tenant dan akses manual.

Kriteria selesai: owner dapat dibedakan dari admin biasa, Platform Admin dapat mengelola metadata tenant, dan tidak dapat membuka konten medis atau route tenant.

#### Hasil M1 — 15 Oktober 2026

- `clinics.owner_user_id` ditambahkan sebagai foreign key unik yang nullable untuk jalur migrasi aman; tenant baru menetapkan pembuat klinik sebagai owner eksplisit.
- Role `Platform Admin` hanya memiliki permission dashboard dan daftar/detail tenant nonmedis. Route `/platform` dipisahkan dan middleware role tenant tetap menolak akses ke area admin klinik serta medis.
- Dashboard platform, pencarian tenant, detail profil nonmedis, metrik agregat, dan audit `platform.tenant.viewed` tersedia. Perubahan profil klinik tetap diaudit oleh alur tenant yang sudah ada.
- Factory dan seeder membuat owner/Clinic Admin serta Platform Admin secara eksplisit. Seed lokal menghasilkan 1 Platform Admin, 1 klinik ber-owner, dan 0 klinik tanpa owner.
- Migration lokal dan seed selesai tanpa reset atau penghapusan data.
- `php artisan test --compact`: 126 tests / 569 assertions lulus. Test terfokus M1: 41 tests / 144 assertions lulus.
- Laravel Pint, Blade cache, tiga route platform, dan `npm run build` lulus.

### M2 — Katalog paket dan domain subscription

- [x] Buat model, migration, factory, dan seeder paket serta harga yang telah disetujui.
- [x] Buat subscription, period history, dan service untuk menentukan entitlement saat ini.
- [x] Implementasikan trial Team satu kali selama 14 hari.
- [x] Implementasikan transisi trial → Free dan active → expired/read-only.
- [x] Implementasikan penambahan masa aktif ketika renewal dibayar sebelum kedaluwarsa.
- [x] Buat halaman Platform Admin untuk mengaktifkan/nonaktifkan katalog dan memberi grant manual dengan alasan.
- [x] Snapshot paket dan harga pada period/payment.

Kriteria selesai: keadaan subscription dapat dihitung deterministik pada tanggal tertentu, histori tidak berubah saat harga katalog diedit, dan grant manual dapat diaudit.

#### Hasil M2 — 15 Oktober 2026

- Domain additive tersedia untuk `subscription_plans`, `subscription_prices`, `subscriptions`, `subscription_periods`, dan `payments`; migration tidak mengubah atau menghapus data klinis.
- Seeder idempotent membuat Free, Individual, Team dan enam harga yang disetujui tanpa mengaktifkan kembali item yang dinonaktifkan Platform Admin.
- `SubscriptionService` menjadi sumber lifecycle tunggal untuk Free, trial Team 14 hari satu kali, aktivasi pembayaran, renewal bertumpuk, expiry, grant manual, dan perhitungan status pada waktu tertentu.
- Periode dan payment menyimpan snapshot kode/nama paket, durasi, serta nominal. Perubahan harga katalog tidak mengubah histori.
- Platform Admin dapat mengaktifkan/nonaktifkan paket dan harga, melihat status subscription tenant, serta memberi grant beralasan. Perubahan katalog dan grant masuk audit log.
- Schema dan katalog diterapkan ke database lokal: 3 paket, 6 harga, dan 2 permission komersial Platform Admin. Tenant lama belum diberi subscription palsu; backfill grant tetap di M6.
- `php artisan test --compact`: 133 tests / 620 assertions lulus. Test terfokus M2: 46 tests / 188 assertions lulus.
- Laravel Pint, Blade cache, tujuh route platform, dan `npm run build` lulus.

Status `expired` sudah menjadi keadaan read-only domain. Penolakan write route tenant tetap dikerjakan pada M5 setelah backfill aman tersedia.

### M3 — Registrasi dan onboarding tenant

- [x] Pisahkan entry point registrasi pasien dan pemilik klinik.
- [x] Tambahkan state onboarding untuk akun pemilik yang belum memiliki klinik.
- [x] Setelah email terverifikasi, minta profil klinik dan buat owner/clinic/setting secara transaksional.
- [x] Tampilkan Free, trial, Individual, dan Team beserta harga durasi.
- [x] Aktifkan Free atau trial tanpa pembayaran.
- [x] Arahkan pilihan berbayar ke proses checkout dan tahan akses berbayar sampai payment sah.
- [x] Tangani refresh, back button, submit ulang, dan onboarding yang terputus secara idempotent.
- [x] Audit pembuatan tenant dan pemilihan paket tanpa mencatat secret/token.

Kriteria selesai: pemilik baru dapat menyelesaikan onboarding tanpa dibuat ganda, pasien tidak memperoleh role klinik, dan tenant belum aktif tidak dapat masuk ke data tenant lain.

#### Hasil M3 — 15 Oktober 2026

- Registrasi pasien tetap di `/register`; entry point pemilik klinik tersedia di `/register/clinic`. Keduanya memakai email unik dan tipe akun eksplisit.
- Owner baru memperoleh role Clinic Admin tetapi tetap harus memverifikasi email. State onboarding dihitung dari keberadaan klinik dan subscription, sehingga login ulang selalu melanjutkan ke langkah yang benar tanpa kolom state tambahan.
- Pembuatan clinic, setting, ownership, relasi user, dan audit berjalan dalam satu transaksi melalui service yang juga dipakai alur admin lama.
- Middleware onboarding menahan owner dari dashboard, notifikasi, dan route operasional tenant sebelum clinic serta subscription tersedia. Profil akun dan logout tetap dapat diakses.
- Halaman paket menampilkan Free, trial Team 14 hari, Individual, Team, dan seluruh durasi/harga. Free/trial aktif tanpa payment dan submit ulang tidak membuat subscription atau period ganda.
- Pilihan berbayar menuju halaman checkout M3, dicatat sekali per pilihan dalam session, dan tidak membuat payment/subscription atau mengaktifkan akses. Integrasi DOKU menggantikan placeholder ini pada M4.
- Migration `users.account_type` dan seed akun demo diterapkan tanpa menghapus data. Admin demo menjadi `clinic_owner`, pasien demo menjadi `patient`, dan tenant lama tetap belum mendapat subscription palsu.
- `php artisan test --compact`: 140 tests / 689 assertions lulus. Test terfokus M3: 48 tests / 194 assertions lulus.
- Laravel Pint, Blade cache, tujuh route onboarding, dua entry point registrasi, dan `npm run build` lulus.

### M4 — DOKU Checkout

- [x] Konfirmasi versi/API DOKU yang digunakan dari dokumentasi resmi dan sandbox merchant sebelum menulis client. API Checkout Non-SNAP resmi dan transaksi merchant sandbox nyata sudah terverifikasi.
- [x] Tambahkan konfigurasi environment untuk sandbox/production, Client ID, Secret Key, dan endpoint.
- [x] Buat invoice lokal dan request checkout DOKU dari backend dengan request ID unik.
- [x] Simpan payment URL dan tampilkan status pending tanpa menganggap redirect sebagai sukses.
- [x] Buat endpoint notification publik yang memverifikasi signature sebelum memproses body.
- [x] Proses notification secara idempotent dan aktifkan subscription dalam transaksi dengan row lock.
- [x] Tolak aktivasi bila clinic, invoice, nominal, atau status tidak cocok.
- [x] Tangani notification duplikat, terlambat, dan out-of-order.
- [x] Tambahkan pemeriksaan status/reconciliation untuk payment pending.
- [x] Tampilkan riwayat invoice dan pembayaran hanya kepada owner serta Platform Admin.
- [x] Sediakan pencatatan refund manual tanpa menghapus period/payment.

Kriteria selesai: hanya payment DOKU sah yang mengaktifkan akses, replay tidak menambah masa aktif dua kali, dan kegagalan gateway tidak merusak subscription yang sudah aktif.

#### Hasil M4 — 15 Oktober 2026

- Integrasi mengikuti DOKU Checkout Non-SNAP resmi: checkout `POST /checkout/v1/payment`, status `GET /orders/v1/status/{invoice}`, dan signature `HMACSHA256` dengan digest SHA-256 body. Sandbox dan production dipilih melalui `DOKU_BASE_URL`; secret hanya dibaca dari environment.
- Checkout owner membuat invoice snapshot lokal lebih dahulu, memakai invoice/request ID unik, lalu menyimpan URL dan kedaluwarsa DOKU. Redirect browser hanya membuka detail pending dan tidak pernah mengaktifkan subscription.
- Endpoint publik `POST /payments/doku/notification` dikecualikan dari CSRF tetapi wajib lolos Client ID serta signature atas raw JSON sebelum body diproses.
- `payment_events` menyimpan identitas event unik dan metadata minimum tanpa payload/signature mentah. Row lock, unique event ID, dan unique period per payment membuat notification/reconciliation idempoten.
- Hanya status `SUCCESS` dengan invoice, request ID, clinic marker, nominal, dan snapshot lokal yang cocok dapat menjadi `paid` serta mengaktifkan periode secara atomik. `FAILED` Checkout tetap pending; event duplikat atau out-of-order tidak menurunkan status final.
- Owner hanya melihat pembayaran kliniknya dan dapat checkout ulang untuk perpanjangan. Platform Admin dapat melihat seluruh invoice nonmedis, menjalankan reconciliation pending, dan mencatat refund manual beralasan tanpa menghapus payment atau period.
- Migration `payment_events` dan permission pembayaran Platform Admin sudah diterapkan pada database lokal tanpa mengubah histori subscription/payment lama.
- Test terfokus M4: 24 tests / 172 assertions lulus. Regression penuh: 150 tests / 746 assertions lulus. Laravel Pint, Blade cache, delapan route pembayaran/notification, `git diff --check`, dan build Vite lulus.
- Transaksi sandbox DOKU nyata berhasil diselesaikan pada 15 Oktober 2026; Checkout popup, pembayaran sandbox, dan aktivasi subscription terverifikasi end-to-end.

### M5 — Enforcement paket dan read-only

- [x] Buat satu service sebagai sumber kebenaran entitlement dan status subscription.
- [x] Terapkan batas dokter/staf pada pembuatan dan pengaktifan ulang akun.
- [x] Terapkan kuota 50 kunjungan Free saat pemeriksaan dimulai, bukan hanya pada UI.
- [x] Izinkan penyelesaian booking yang sudah ada ketika Free mencapai kuota.
- [x] Terapkan read-only pada seluruh write route tenant saat subscription berbayar habis.
- [x] Nonaktifkan public booking untuk tenant read-only/suspended.
- [x] Pastikan role dan policy medis tetap berlaku saat akses baca diizinkan.
- [x] Tampilkan banner status trial, sisa hari, Free, pending payment, dan expired tanpa membocorkan nominal ke staf yang bukan owner.
- [x] Izinkan owner menonaktifkan seat berlebih setelah trial turun ke Free.

Kriteria selesai: batas tidak dapat dilewati melalui request langsung, data lama tetap terbaca oleh pengguna yang berwenang, dan operasi klinis tidak terputus di tengah kunjungan yang sudah berjalan.

#### Hasil M5 — 15 Oktober 2026

- `SubscriptionService` menjadi sumber tunggal status, batas seat, kuota bulanan, kemampuan booking, dan ringkasan banner. Tenant lama tanpa subscription belum diblokir sebelum backfill M6.
- Free membatasi 1 dokter, 1 resepsionis, dan 50 pemeriksaan per bulan timezone klinik. Individual membatasi 1 dokter/3 staf; Team tidak membatasi akun.
- Booking baru ditutup saat kuota Free tercapai. Booking yang dibuat sebelum kuota tercapai tetap dapat mulai dan kunjungan aktif tetap dapat disimpan/diselesaikan.
- Middleware read-only menolak write operasional tenant expired/suspended serta over-limit, tetapi mempertahankan GET sesuai role/policy, checkout owner, dan deaktivasi seat berlebih oleh owner.
- Public booking expired/suspended ditutup. Banner trial, sisa hari, Free, pending, over-limit, dan expired tersedia tanpa nominal/invoice untuk staf non-owner.
- Regression dibagi menjadi tiga batch agar output runner tetap terbaca: 156 tests / 795 assertions lulus. Laravel Pint, Blade cache, route inspection, `git diff --check`, dan Vite build lulus.

### M6 — Reminder, migrasi tenant lama, dan hardening

- [x] Jadwalkan reminder email dan notifikasi aplikasi pada H-7, H-3, H-1, dan saat kedaluwarsa.
- [x] Cegah reminder ganda dan kirim hanya kepada owner.
- [x] Petakan owner seluruh klinik lama secara eksplisit.
- [x] Berikan Team 12 bulan kepada tenant lama melalui grant migration yang tercatat, tanpa payment palsu.
- [x] Jangan menyentuh isi medis selama backfill subscription.
- [x] Uji scheduler, queue worker, retry, failure handling, dan timezone klinik.
- [x] Tambahkan rate limit pada registrasi, checkout, status payment, dan notification sesuai kebutuhan tanpa menghambat webhook sah.
- [x] Verifikasi log tidak memuat secret DOKU, signature, token, atau data medis.
- [x] Dokumentasikan environment production hanya bila diminta pada tahap deployment; belum dibuat karena deployment belum diminta.

Kriteria selesai: tenant lama tidak terkunci, reminder konsisten, retry aman, dan tidak ada secret atau data medis di log pembayaran.

#### Hasil M6 — 16 Oktober 2026

- Reminder subscription dijadwalkan setiap jam untuk H-7, H-3, H-1, dan H0 berdasarkan timezone klinik. Setiap periode/milestone unik, job retry maksimal tiga kali, kegagalan tercatat hanya sebagai tipe error, dan database notification/email hanya dikirim kepada owner saat ini.
- Migration tenant lama memvalidasi tepat satu Clinic Admin sebelum menulis, memetakan owner secara eksplisit, lalu memberi Team 12 bulan dengan sumber `legacy_migration`, audit trail, dan tanpa payment. Tenant yang sudah memiliki subscription tidak ditimpa.
- Registrasi dibatasi 5 percobaan/menit per email+IP, checkout 5/menit per pengguna, status payment 30/menit per pengguna, dan notification DOKU 300/menit per IP+Client ID.
- Payment event hanya menyimpan hash payload dan audit hanya menyimpan metadata aman; test membuktikan secret, signature, token, dan isi medis tidak tersimpan.
- Migration M6 berhasil diterapkan pada database lokal: 2 klinik memiliki 2 subscription dan tidak ada klinik tanpa owner. Seluruh regression lulus: 163 tests / 845 assertions.

### M7 — Verifikasi rilis SaaS

- [x] Jalankan test terfokus setelah setiap milestone.
- [x] Jalankan seluruh regression suite, Pint untuk PHP yang berubah, build Vite, dan pemeriksaan migration.
- [x] Simulasikan dua tenant dan buktikan data, channel realtime, audit, payment, serta subscription tidak bocor silang.
- [x] Simulasikan Free, trial, Individual, Team, payment pending/sukses/kedaluwarsa, renewal awal, renewal terlambat, refund manual, dan grant Platform Admin.
- [x] Simulasikan notification DOKU sah, signature salah, nominal salah, replay, duplikat, serta urutan event terbalik.
- [x] Simulasikan subscription kedaluwarsa ketika dokter/staf sedang login dan pastikan write ditolak tanpa menghilangkan read yang sah.
- [x] Verifikasi halaman penting pada desktop dan mobile.
- [x] Uji sandbox DOKU end-to-end sebelum kredensial production dipakai.

Kriteria selesai: seluruh acceptance criteria pada bagian berikut terbukti dan tidak ada regression yang menghalangi operasional klinik.

#### Hasil M7 — 16 Oktober 2026

- Gate terfokus SaaS menjalankan 48 tests / 315 assertions; dua skenario tepi yang ditambahkan untuk renewal terlambat dan notification payment kedaluwarsa menjalankan 16 tests / 94 assertions. Semuanya lulus.
- Regression penuh menjalankan 165 tests / 852 assertions dan seluruhnya lulus. Laravel Pint, `git diff --check`, dan build production Vite 8.3.2 juga lulus.
- Seluruh 33 migration berstatus `Ran`; tidak ada reset, seed ulang, atau penghapusan data.
- Test lintas tenant membuktikan isolasi data operasional, channel realtime, audit, payment, subscription, dan akses medis. Test lifecycle mencakup Free, trial, Individual, Team, pending, success, expired, renewal awal/terlambat, refund, grant, serta read-only tanpa kehilangan akses baca yang sah.
- Test DOKU mencakup notification sah, signature/Client ID salah, nominal salah, replay, duplikat, event terbalik, failure, expiry, dan reconciliation idempoten.
- Landing page dan onboarding pemilik diverifikasi pada desktop serta viewport mobile 390×844. Keduanya tidak memiliki overflow horizontal dan kontrol utama tetap terlihat.
- Sandbox DOKU terkonfigurasi dan histori lokal membuktikan satu payment `paid`, satu event reconciliation `processed_success`, serta satu paid subscription period. Checkout popup dan pembayaran sandbox sebelumnya juga telah diverifikasi manual.

## 7. Pengujian minimum

### Tenant dan authorization

- Owner hanya dapat mengelola subscription kliniknya sendiri.
- Clinic Admin non-owner tidak dapat checkout atau melihat payment.
- Staff dan pasien tidak dapat mengelola subscription.
- Platform Admin tidak dapat membuka route atau model medis.
- Request dengan ID clinic/payment milik tenant lain ditolak tanpa kebocoran data.

### Paket dan lifecycle

- Trial hanya dapat dibuat satu kali per klinik.
- Trial berakhir menjadi Free pada waktu yang benar.
- Free menolak dokter/staf/kunjungan baru setelah batas tercapai.
- Individual dan Team menerapkan kapasitas yang benar.
- Team tidak membatasi jumlah akun operasional dalam satu klinik.
- Renewal aktif menambah periode dari tanggal akhir lama; renewal expired mulai dari payment sukses.
- Expired berbayar menjadi read-only dan tidak menghapus data.

### Pembayaran

- Checkout menyimpan invoice/nominal snapshot yang benar.
- Redirect browser tidak mengaktifkan subscription.
- Signature DOKU invalid ditolak.
- Invoice atau nominal tidak cocok ditolak dan diaudit.
- Event duplikat/replay tidak memperpanjang dua kali.
- Notification out-of-order tidak menurunkan payment final.
- Payment sukses dan perubahan subscription atomik.
- Refund manual tidak menghapus histori.

### Migrasi

- Semua klinik lama mendapatkan owner yang valid.
- Semua klinik lama menerima grant Team 12 bulan satu kali.
- Migration dapat dijalankan pada database berisi data dan memiliki rollback yang aman sesuai keterbatasan perubahan schema.
- Factory, seeder, dan tests lama tetap bekerja setelah ownership menjadi eksplisit.

## 8. Acceptance criteria rilis

Fitur SaaS dianggap siap apabila:

- pemilik dapat mendaftar, memverifikasi email, membuat satu klinik, dan memilih paket;
- Free/trial dapat aktif tanpa payment dan tidak dapat disalahgunakan untuk trial berulang;
- pembayaran DOKU yang sah mengaktifkan paket tepat satu kali;
- paket dan harga tampil sesuai keputusan pada dokumen ini;
- Team mendukung akun operasional tanpa batas untuk satu klinik;
- tenant tidak dapat membaca atau mengubah data tenant lain;
- Platform Admin dapat mengelola aspek komersial tanpa akses isi medis;
- subscription expired menjadi read-only tanpa menghapus data;
- tenant lama tetap dapat beroperasi dengan grant Team 12 bulan;
- reminder, queue, scheduler, webhook, audit, dan reconciliation berjalan;
- test suite, formatter, frontend build, migration, dan sandbox DOKU lulus.

## 9. Di luar cakupan saat ini

- Auto-renew atau recurring charge.
- Paket lifetime.
- Multi-cabang dalam satu subscription.
- Satu owner mengelola lebih dari satu klinik.
- Overage otomatis atau tagihan pascabayar.
- Refund mandiri pelanggan.
- Impersonation oleh Platform Admin.
- WhatsApp/SMS reminder.
- Kupon, referral, affiliate, prorata upgrade/downgrade, dan perubahan paket di tengah periode.
- Integrasi SATUSEHAT, BPJS, farmasi, laboratorium, atau fitur klinis baru yang tidak diperlukan subscription.
- PPN dan faktur pajak sampai status perpajakan badan usaha ditetapkan.

## 10. Risiko dan mitigasi

| Risiko | Mitigasi wajib |
| --- | --- |
| Kebocoran data antar-klinik | Clinic context server-side, policy, query scope yang konsisten, dan tests lintas tenant |
| Payment palsu melalui callback browser | Aktivasi hanya dari notification DOKU dengan signature, invoice, nominal, dan status valid |
| Notification duplikat memperpanjang dua kali | Unique event/request ID, transaksi, row lock, dan idempotency test |
| Team dipakai beberapa cabang | Satu clinic profile/domain/location per subscription, terms penggunaan, audit, dan review Platform Admin |
| Read-only memutus pelayanan yang sudah berjalan | Akhiri periode pada akhir hari dan izinkan penyelesaian kunjungan/booking yang sudah ada sesuai aturan paket |
| Tenant lama langsung terkunci | Backfill owner dan grant Team 12 bulan sebelum enforcement diaktifkan |
| Harga berubah dan histori ikut berubah | Snapshot plan, durasi, dan nominal pada payment/period |
| Platform Admin melihat data medis | Area, permission, policy, query, dan test negatif khusus Platform Admin |
| Free trial disalahgunakan | Satu trial per clinic/owner, email unik, audit, rate limit, dan pemeriksaan server-side |
| Akun Team tanpa batas disalahgunakan | Berlaku hanya untuk satu klinik, verifikasi email, rate limit undangan, audit akun, dan kemampuan suspend |

## 11. Urutan pengerjaan

Urutan wajib: M0 → M1 → M2 → M3 → M4 → M5 → M6 → M7.

DOKU tidak diintegrasikan sebelum model ownership, subscription, dan invoice lokal stabil. Enforcement tidak diaktifkan pada tenant lama sebelum backfill owner serta grant Team 12 bulan terbukti aman. Setiap milestone ditutup dengan test relevan dan bukti hasil sebelum melanjutkan.
