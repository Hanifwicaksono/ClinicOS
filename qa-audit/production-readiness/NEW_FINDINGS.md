# Temuan baru production readiness

Status per run 11 November 2026. QA-01–QA-15 tidak diimplementasikan ulang. Source produksi tidak diubah oleh audit ini.

## PR-01 — Revokasi akses tidak mencabut subscription WebSocket yang sudah aktif

**OPEN — P2 / Medium — gate realtime FAIL.**

Reproduksi nyata menggunakan Playwright, Reverb 1.12.0, database MySQL audit, dan database queue worker:

1. Login dua browser resepsionis dari klinik yang sama; subscribe `private-clinic.{id}.queues`.
2. Cabut role salah satu pengguna menggunakan API Spatie pada fixture audit. Request otorisasi channel baru ditolak HTTP 403.
3. Browser yang tetap berwenang membuat booking dan melakukan check-in. Worker memublikasikan `queue.updated` setelah transaksi commit.
4. Browser yang rolenya sudah dicabut masih menerima satu event baru pada socket lama, dengan koneksi `connected`.
5. Ulangi dengan akun staf baru yang diverifikasi melalui SMTP. Admin menonaktifkan akun lewat UI. Akses HTTP berikutnya dialihkan ke login, tetapi socket lama tetap menerima satu event check-in baru.

Bukti: [permission-revocation.json](evidence/permission-revocation.json), [deactivation-revocation.json](evidence/deactivation-revocation.json), [browser.mjs](tests/browser.mjs).

Akar masalah: authorization `routes/channels.php` berjalan pada subscribe, sementara perubahan role/status akun tidak mengakhiri subscription/koneksi yang sudah diotorisasi. Penolakan request HTTP berikutnya tidak memutus socket yang ada.

Tidak ditemukan isi medis atau identitas pasien pada payload antrean yang diamati. Informasi tersebut juga memiliki channel queue-board publik yang disengaja. Severity dibatasi pada revokasi akses private yang tidak efektif; bukti ini bukan bukti kebocoran medis atau kebocoran tenant lain.

Rekomendasi: tetapkan kontrak waktu revokasi dan terapkan pencabutan subscription di sisi server atau mekanisme otorisasi ulang yang membatasi umur akses. Disconnect di browser saja tidak mencukupi. Retest socket lama, subscribe ulang, reconnect, akun inactive, perubahan role/permission, serta seluruh channel user notification. **Belum diperbaiki karena pengguna membatasi sesi ini pada audit.**

## PR-02 — Assertion urutan key JSON tidak portabel pada MySQL

**OPEN — P3 / Low — regression suite FAIL, bukan bukti RME kehilangan data.**

`RmeGeneralDentalTest::test_assigned_doctor_can_persist_general_rme_and_odontogram_in_draft` menggunakan `assertSame` untuk array objek JSON. MySQL menormalisasi urutan key object menjadi `condition`, `tooth_number`; fixture mengharapkan `tooth_number`, `condition`. Nilai dan urutan elemen gigi tetap sama. Suite SQLite historis lulus, suite MySQL nyata gagal.

Bukti: [php-main.txt](evidence/php-main.txt), [php-main.xml](evidence/php-main.xml). Perbaikan yang disarankan terbatas pada assertion: bandingkan key/value per elemen tanpa mensyaratkan urutan key object, sambil mempertahankan urutan elemen gigi. Test lama tidak dihapus atau dilonggarkan selama audit.

## Catatan harness, bukan bug aplikasi

Run awal Playwright memiliki selector label/ID yang salah, SMTP `MAIL_URL` kosong yang mengganti transport pada Laravel, pemanggilan `Echo.connect()` yang membuat connector baru, dan payload pasien baru yang memicu duplicate review. Run tersebut disimpan sebagai `playwright-harness-attempt*.json`; hasilnya bukan hasil final gate aplikasi. Konfigurasi/harness diperbaiki hanya di direktori audit dan environment proses sementara.

Test subdomain juga gagal ketika suite mewarisi `APP_URL` berupa IP: route fallback `/clinic/{slug}` memang mengalihkan ke subdomain. Retest dengan `APP_URL=http://localhost` lulus 1 test / 11 assertions; bukti [public-host-retest.txt](evidence/public-host-retest.txt). Ini perbedaan fixture host, bukan regresi isolasi tenant.

Probe revokasi awal yang hanya membuat booking tidak menghasilkan `QueueUpdated`; hasil PASS probe itu tidak sah sebagai bukti revokasi. Run final melakukan check-in, memverifikasi penerimaan pada browser kontrol yang masih berwenang, lalu membuktikan penerimaan pada socket yang sudah kehilangan akses. Hasil final **FAIL** dipertahankan.

## Risiko performa dan deployment yang belum terselesaikan

Target realtime awal p95 <= 2 detik belum tercapai pada burst 40 booking/check-in: p95 aktual 2,283 detik, tanpa event hilang. Ini **FAIL terhadap asumsi SLO**, belum merupakan bukti akar masalah pada satu fungsi tertentu. Profiling worker polling, refetch browser, antrean, dan konfigurasi server diperlukan; tidak dilakukan tuning source otomatis.

Load tanpa think time mengakses halaman pembayaran berulang dan menerima HTTP 429 sesuai limiter per akun. Jumlahnya dilaporkan, bukan dibuang dari statistik. Ini bukti pembatasan workload, bukan bukti kegagalan transaksi atau alasan untuk menonaktifkan limiter.

Workflow deployment memperbarui direktori aplikasi secara langsung tanpa rollback otomatis atau langkah restart worker/Reverb. Konfigurasi process manager di VPS belum diperiksa. Binary MySQL audit 8.0.30 juga bukan acuan versi produksi yang didukung: Oracle menyatakan MySQL 8.0 masuk Sustaining Support pada April 2026 ([pengumuman resmi](https://www.mysql.com/support/eol-notice.html)). Tidak dilakukan upgrade server atau dependency otomatis.

## PR-03 — Factory subscription dapat melampaui panjang kolom MySQL

**OPEN — P3 / Low — error fixture membuat suite MySQL tidak stabil.**

Run penuh terbaru menghasilkan SQLSTATE 22001 / MySQL 1406 pada `RmeGeneralDentalTest::test_invalid_general_and_odontogram_payload_is_rejected_without_mutating_record`. Error terjadi pada pembuatan fixture sebelum validasi RME yang hendak diuji. `PaymentFactory` membuat `SubscriptionPriceFactory`, yang membuat `SubscriptionPlanFactory`. Factory plan memakai `fake()->unique()->slug(2)` tanpa batas panjang, sedangkan `subscription_plans.code` adalah varchar(30).

Reproduksi deterministik: `fake()->seed(0)`, reset unique generator, lalu `SubscriptionPlan::factory()->raw()` menghasilkan kode 31 karakter, melampaui schema 30. Probe ini tidak menulis database. Bukti: [factory-contract.json](evidence/factory-contract.json), [php-main.xml](evidence/php-main.xml). Run pertama tidak mengalami error ini; ketidakstabilan tidak disembunyikan dengan memilih run hijau.

Rekomendasi: batasi kode factory sesuai schema dan gunakan relasi plan/price eksplisit pada fixture terkait. Jangan memperlebar kolom produksi hanya untuk mengakomodasi string Faker. Katalog produksi yang memakai kode tetap tidak terbukti rusak oleh temuan fixture ini. Tidak ada source factory atau test lama diubah dalam audit ini.
