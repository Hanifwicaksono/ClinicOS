# ClinicOS - Laporan Lengkap Production Readiness dan Stress Testing

Tanggal hasil audit: **11 November 2026**. Tanggal penggabungan: **12 November 2026, Asia/Bangkok**.

Dokumen ini menggabungkan delapan laporan production-readiness, seluruh temuan baru, dan rekap hasil pengujian. Keputusan **NO-GO**, angka test, bukti, langkah reproduksi, rekomendasi, serta batas PASS/FAIL/BLOCKED/NOT TESTED dipertahankan dari laporan asal. Penggabungan ini tidak menjalankan ulang test atau mengubah status temuan.

Seluruh isi laporan ada di file ini; tautan antarlaporan diarahkan ke bagian dalam dokumen. Bukti mentah dan harness tetap tersedia melalui tautan ke folder [evidence/](evidence/) dan [tests/](tests/). Sertakan kedua folder tersebut bila memerlukan verifikasi bukti saat membagikan dokumen. File asal dipertahankan.

## Daftar isi

1. [Production readiness scorecard](#scorecard)
2. [Environment report](#environment)
3. [MySQL concurrency report](#mysql-concurrency)
4. [Realtime test report](#realtime)
5. [Integration test report](#integration)
6. [Load and reliability report](#load)
7. [Disaster recovery report](#disaster-recovery)
8. [Human acceptance and clinic-day simulation](#human-uat)
9. [Temuan baru production readiness](#new-findings)
10. [Rekap hasil pengujian (JSON)](#test-summary)

---

<a id="scorecard"></a>

## 1. Production readiness scorecard

Sumber: `PRODUCTION_READINESS_SCORECARD.md`.

**Keputusan: NO-GO untuk penggunaan produksi multi-user.**

Audit operasional lokal selesai pada 11 November 2026. Alur klinik utama, transaksi MySQL, SMTP inbox, checkout/reconciliation DOKU sandbox dan pemulihan lokal sudah memiliki bukti nyata. Namun revokasi socket aktif gagal, asumsi latency realtime belum tercapai, suite MySQL masih gagal, serta callback provider dan lingkungan deployment belum terverifikasi. Tidak memberi CONDITIONAL GO hanya berdasarkan banyaknya test yang lulus.

### Gate

| Gate | Status | Alasan / bukti |
| --- | --- | --- |
| Lingkungan audit MySQL terisolasi | PASS | Instance/datadir/database audit baru, 44 tabel InnoDB, 69 FK, 151 index; koreksi storage awal tercatat |
| Kesetaraan deployment dan versi server produksi | BLOCKED | VPS, TLS/proxy, supervisor/cron, supported MySQL dan release rollback belum diuji |
| Concurrency aktual | PASS pada 9 skenario | Dua proses/koneksi per kasus dan waktu overlap; row-lock wait terbukti; tidak ada duplikasi/kuota/lost update teramati |
| Realtime normal / tenant boundary baru | PASS | Browser menerima WebSocket nyata; tenant lain 403; payload nonmedis |
| Revokasi akses pada socket aktif | FAIL | Role dicabut/deactivation hanya menolak HTTP; socket lama masih menerima event — PR-01 |
| SMTP, auth lifecycle, scheduler/queue lokal | PASS | Inbox nyata, link verifikasi/reset, sesi lama ditolak, email diubah perlu verifikasi ulang |
| DOKU checkout / reconciliation sandbox | PASS | HTTPS provider nyata, pending tetap pending dan tidak mengaktifkan subscription |
| Callback, kegagalan/timeout/replay provider end-to-end | BLOCKED | Receiver notifikasi audit tidak dapat dijangkau provider secara publik |
| Load HTTP latency/5xx | PASS | Target numerik agregat tercapai; nol 5xx, percentile mencakup 429 |
| Workload baca tanpa response rejection | FAIL | 236 dari 2.667 read menerima 429 pada limiter payment-status; pembatasan yang disengaja tetap dilaporkan |
| Load realtime | FAIL terhadap asumsi | p95 2.283 ms > asumsi 2.000 ms; 40/40 event diterima |
| Disaster recovery lokal | PASS, 7/7 | Restore checksum 44 tabel dan hash empat file cocok; endpoint private hasil restore 200; worker/app restart dan rollback lulus |
| RPO/RTO, offsite, crash pada side effect eksternal | NOT TESTED | Restore lokal bukan penerimaan recovery produksi |
| Hari klinik terkompresi melalui UI semua role | PASS | Booking Patient, queue UI, skip/return, pemeriksaan, finalisasi dan penutupan dashboard tanpa developer data repair |
| Security lifecycle pada UAT | FAIL | Socket lama tetap menerima event setelah revokasi |
| Human acceptance oleh staf klinik | NOT TESTED | Sign-off manusia dan shift nyata belum diuji |
| Regression suite MySQL | FAIL | Satu assertion JSON portability dan satu error factory terlalu panjang; tetap dipertahankan |
| Advisory Composer/npm | PASS dalam cakupan | Nol advisory terdeteksi; bukan audit OS/server atau bukti tidak ada kerentanan lain |

Status memakai PASS, FAIL, BLOCKED, dan NOT TESTED pada subgate yang terukur. Tidak membuat persentase kesiapan karena bobot dan acceptance bisnis belum disepakati.

### Jumlah test dan skenario

| Suite / pengujian | PASS | FAIL | ERROR | BLOCKED / SKIP | Bukti |
| --- | --- | --- | --- | --- | --- |
| PHP aplikasi, run MySQL final, 341 test | 339 | 1 | 1 | 0 | [JUnit](evidence/php-main.xml), [output](evidence/php-main.txt) |
| PHP audit tambahan, 22 test | 22 | 0 | 0 | 0 | [JUnit](evidence/php-audit.xml) |
| **Total PHP, 363 test** | **361** | **1** | **1** | **0** | Database `_suite`, APP_URL localhost |
| JavaScript, 7 test | 7 | 0 | 0 | 0 | [output](evidence/javascript.txt) |
| Playwright utama, 14 kasus | 12 | 2 | 0 | 0 | [results](evidence/playwright-results.json) |
| Playwright clinic-day, 1 kasus | 1 | 0 | 0 | 0 | [clinic-day](evidence/clinic-day.json) |
| **Total Playwright, 15 kasus** | **13** | **2** | **0** | **0** | Kedua FAIL adalah dua reproduksi PR-01 |
| MySQL multiprocess race | 9 | 0 | 0 | 0 | [concurrency](evidence/mysql-concurrency.json) |
| Recovery / failure injection | 7 | 0 | 0 | 0 | [recovery](evidence/recovery-results.json) |
| Scheduler -> worker -> SMTP, dua invocation | 1 skenario | 0 | 0 | 0 | [integration](evidence/integration-results.json) |
| DOKU checkout + reconciliation sandbox | 1 skenario | 0 | 0 | Callback BLOCKED | [probe](evidence/doku-sandbox-probe.json) |

Tidak menghitung 2.667 request load atau 40 event sebagai ribuan test case. Test PHP asli tidak dihapus/diubah. Browser CDP lama yang bergantung SQLite tidak dijalankan ulang dalam run readiness ini; hasil historisnya tidak dimasukkan. Bukti readiness browser memakai Playwright/MySQL baru. Run awal yang gagal karena konfigurasi/selector harness tetap diarsipkan dan dibedakan dari hasil final.

Ringkasan yang dapat dibaca mesin: [test-summary.json](evidence/test-summary.json). Pemeriksaan sintaks/format harness dan preservasi tracked diff: [final-checks.json](evidence/final-checks.json). Layanan audit telah dihentikan; [cleanup.json](evidence/cleanup.json).

### Temuan tersisa

| ID | Severity | Status | Dampak |
| --- | --- | --- | --- |
| PR-01 | P2 / Medium | OPEN | Pencabutan role/deactivation tidak mengakhiri private socket yang sudah aktif; tidak ditemukan payload medis pada event yang diuji |
| PR-02 | P3 / Low | OPEN | Assertion urutan key JSON tidak portabel pada MySQL; isi/value yang dibandingkan sama |
| PR-03 | P3 / Low | OPEN | Factory kode plan tidak dibatasi varchar(30); reproduksi seed 0 menghasilkan 31 karakter dan error MySQL |

[Akar masalah, reproduksi dan rekomendasi](#new-findings). QA-01–QA-15 tidak diimplementasikan ulang. Tidak ditemukan bukti baru yang mengharuskan membuka kembali fix tersebut. Satu bug akses aplikasi dan dua masalah test/fixture baru tetap terbuka; tidak menyatakan suite hijau atau aplikasi siap produksi.

### Risiko regresi dan syarat melanjutkan

1. Perbaikan PR-01 harus ditegakkan server dan diuji terhadap socket lama, auth ulang, reconnect, channel clinic dan user notification, semua role/tenant. Risiko regresinya pada fanout, reconnect dan delivery; source belum diubah.
2. Perbaiki portability assertion/factory secara minimal sambil mempertahankan regression test, lalu jalankan ulang seluruh suite MySQL. Jangan menyamakan error fixture dengan bukti validasi RME sudah diuji pada run yang error itu.
3. Tetapkan volume klinik dan SLO, profilkan latency realtime, jalankan load/soak realistis pada staging dengan stack dan database server yang didukung. Jangan mematikan limiter untuk menjadikan run hijau.
4. Sediakan callback sandbox HTTPS terisolasi; buktikan aktivasi, failed/timeout, duplicate/replay webhook dan rekonsiliasi saat pelayanan berlangsung. Jangan memakai database produksi.
5. Buktikan supervisor/cron, worker/Reverb restart, health gate dan rollback deployment, backup offsite dan RPO/RTO; lanjutkan UAT bersama manusia klinik.

**Layak melanjutkan pengujian production-readiness di staging terkontrol: YA. Layak dipakai klinik produksi multi-user sekarang: TIDAK.** Keputusan GO perlu bukti penutupan gate tersebut, bukan sekadar jumlah PASS.

### Laporan pendukung

- [Environment](#environment)
- [MySQL concurrency](#mysql-concurrency)
- [Realtime](#realtime)
- [Integration](#integration)
- [Load](#load)
- [Disaster recovery](#disaster-recovery)
- [Human UAT](#human-uat)

Seluruh perubahan kode sesi readiness dibatasi pada harness/evidence/laporan audit. Environment sementara dipakai untuk service pengujian; catatan koreksi storage runtime awal tetap dilaporkan di environment report. Tidak mengubah source produksi, menghapus histori pasien, melakukan commit, push, deploy, migration produksi, atau mengganti konfigurasi produksi.

---

<a id="environment"></a>

## 2. Environment report

Sumber: `ENVIRONMENT_REPORT.md`.

Run: 11 November 2026, Windows lokal. Status lingkungan audit: **PASS setelah koreksi isolasi storage**. Kesetaraan dengan deployment produksi: **BLOCKED**.

### Lingkungan yang benar-benar dijalankan

| Komponen | Aktual | Bukti / batas |
| --- | --- | --- |
| PHP / Laravel | PHP 8.5.5 / Laravel 13.34.0 | CLI dan backend HTTP nyata |
| Livewire / Volt | 3.8.10 / 1.11.2 | Source produksi dipakai tanpa perubahan audit |
| Reverb / Permission / PHPUnit | 1.12.0 / 8.3.0 / 12.5.37 | [versi PHP](evidence/composer-versions.json) |
| Frontend | Vite 8.3.4, Tailwind 3.4.17, Echo 2.6.1, Pusher 8.6.0 | [versi JS](evidence/npm-versions.json) |
| Browser | Playwright 1.65.0, Chrome 157.0.8103.50 | [run browser](evidence/playwright-results.json); library di direktori sementara, dependency proyek tidak diubah |
| MySQL | 8.0.30, port 13316, bind 127.0.0.1 | Instance baru dengan `--no-defaults`, datadir sementara; bukan service/data Laragon pengguna |
| Database utama audit | `clinicos_pr_0thp4gu` | [manifest nonrahasia](evidence/runtime.json); suite PHP memakai suffix `_suite`, restore memakai database lain |
| HTTP | Proxy loopback 13110 menuju empat proses PHP independen 13111-13114 | Bukan satu proses `php -S`; bukan stack Linux/FPM produksi |
| Session / cache / queue | Database audit | Session browser nyata, cache permission bersama, worker database berjalan |
| Reverb | Loopback 13411, kredensial sintetis | WebSocket nyata, bukan broadcast fake |
| SMTP | Loopback 13225, inbox penampung tanpa penerusan | Hanya alamat sintetis domain `.test`; MIME dan URL verifikasi disimpan privat di temporary directory |
| Storage / asset build | Direktori sementara; build Vite terpisah | Path HTTP diikat eksplisit; tidak mengganti `.env`, `APP_KEY`, atau public build pengguna |
| DOKU | HTTPS sandbox saja, override kredensial hanya pada proses probe | Runtime HTTP umum tetap tidak mempunyai kredensial DOKU; tidak ada pembayaran nyata |

`@@datadir` diperiksa terhadap direktori MySQL audit dan `@@port` adalah 13316. Guard bootstrap menolak host/port/nama database di luar lingkungan audit. Tidak menjalankan migration, seeder, `migrate:fresh`, atau query ke database pengguna/produksi. Fixture memakai relasi klinik eksplisit dan paket Team trial, bukan aktivasi pembayaran palsu.

### Migration, foreign key, transaction, index

**PASS:** seluruh migration berhasil dari database kosong, 44 tabel memakai InnoDB, 69 constraint foreign key dan 151 index ditemukan. Pemeriksaan orphan untuk seluruh constraint menghasilkan nol; pemeriksaan relasi tenant appointment/patient/doctor/service juga nol mismatch. Lock dan rollback diuji secara operasional, bukan disimpulkan dari nama index. Bukti: [migration](evidence/migrations.txt), [schema](evidence/schema.json), [concurrency](#mysql-concurrency), [recovery](#disaster-recovery).

Schema belum menjadi pengganti validasi tenant pada service/policy: foreign key individual tidak membuktikan seluruh foreign key anak memiliki `clinic_id` yang konsisten.

### Koreksi isolasi yang ditemukan selama audit

Override `LARAVEL_STORAGE_PATH` belum efektif pada request PHP HTTP awal walaupun efektif pada CLI. Tiga foto sintetis audit tersimpan di storage checkout. Ketiganya diidentifikasi hanya melalui referensi database audit, dipindahkan ke private temporary storage dengan validasi absolute path, lalu diuji lagi. Tidak memindahkan/menghapus file pasien asli. Cache/view/log yang sudah ada tidak dihapus untuk membersihkan bukti run awal. Binding eksplisit `useStoragePath()` sekarang dipasang pada bootstrap harness; header HTTP `X-Audit-Storage-Isolated: true` diverifikasi. Run browser final memakai binding ini. Bukti: [storage-isolation-correction.json](evidence/storage-isolation-correction.json).

Queue load awal memakai retry-after uji 5 detik dengan timeout worker 30 detik; seluruh job yang diamati selesai jauh sebelum batas itu. Nilai tersebut tidak layak menjadi konfigurasi produksi. Run recovery final memakai retry-after **90 detik > timeout 30 detik**, sesuai default queue aplikasi. Tidak mengklaim pengujian job panjang atau exactly-once delivery.

### Deployment dan dependency

**BLOCKED:** VPS, HTTPS, DNS wildcard tenant, firewall, process manager, cron, backup offsite, observability, dan secret management produksi tidak diakses. Workflow `.github/workflows/deploy.yml` melakukan update direktori langsung, migration dan optimize; tidak memiliki rollback otomatis, health gate, atau langkah restart queue worker/Reverb. Ini analisis source, bukan bukti bahwa service VPS tidak berjalan.

**PASS dalam cakupan paket:** Composer audit dan npm audit terbaru melaporkan **0 advisory**. [Composer](evidence/composer-audit.json), [npm](evidence/npm-audit.json). Tidak mencakup OS, MySQL binary, konfigurasi server, atau seluruh kemungkinan kerentanan aplikasi.

MySQL 8.0.30 dipakai untuk membuktikan perilaku MySQL yang tersedia lokal. Oracle menyatakan MySQL 8.0 masuk Sustaining Support pada April 2026; hasil ini tidak mensertifikasi versi database produksi yang didukung. Evaluasi versi server staging/produksi diperlukan, tanpa melakukan major upgrade otomatis. [Pengumuman resmi MySQL](https://www.mysql.com/support/eol-notice.html).

### Reproduksi

Skrip di [tests/](tests/) hanya mengoperasikan lingkungan disposable. `runtime.py init` membuat datadir baru; jalankan setelah instance audit sebelumnya dihentikan dan arsipkan evidence sebelum rerun. Sesuaikan lokasi binary MySQL pada manifest bila berpindah mesin.

```powershell
python qa-audit/production-readiness/tests/runtime.py init
python qa-audit/production-readiness/tests/runtime.py setup
python qa-audit/production-readiness/tests/runtime.py php qa-audit/production-readiness/tests/audit.php fixture
python qa-audit/production-readiness/tests/runtime.py start
```

Jangan menyalin kredensial, inbox MIME, SQL dump, URL reset/booking/checkout, atau data private temporary runtime ke laporan publik. Tidak ada commit, push, deploy, atau perubahan konfigurasi produksi dilakukan.

Pint dijalankan hanya pada tiga file PHP harness audit agar tidak memformat perubahan produksi pengguna yang sudah ada. Pemeriksaan sintaks 14 file harness, `git diff --check`, dan fingerprint tracked diff lulus; [final-checks.json](evidence/final-checks.json). Perintah Pint global `--dirty` sengaja tidak dipakai karena cakupannya dapat menyentuh perubahan produksi yang bukan bagian audit ini.

Setelah pengujian, delapan proses service audit dihentikan dengan verifikasi identitas PID, termasuk MySQL audit. Port HTTP/SMTP/Reverb/MySQL audit sudah ditutup. Temporary data dipertahankan untuk reproduksi; service/database pengguna tidak dihentikan. [cleanup.json](evidence/cleanup.json).

---

<a id="mysql-concurrency"></a>

## 3. MySQL concurrency report

Sumber: `MYSQL_CONCURRENCY_REPORT.md`.

Status skenario yang dijalankan: **PASS, 9/9**. Aktivasi subscription dari callback provider saat operasional: **BLOCKED**.

### Metode dan bukti concurrency

Setiap kasus membuat fixture klinik tersendiri. Dua proses PHP melakukan bootstrap aplikasi dan membuka koneksi MySQL independen, menulis PID serta `CONNECTION_ID()`, menunggu barrier, lalu dilepas bersama. Semua sembilan pasangan mempunyai waktu eksekusi yang overlap dan connection ID berbeda. Bukan request berurutan dan bukan SQLite.

Pada perebutan slot terakhir dan replay identik, observer ketiga membaca `performance_schema.data_lock_waits`; penantian lock InnoDB terbukti. Hold lock 400 ms sengaja ditambahkan oleh harness pada query lock pertama untuk membuat observasi deterministik. Angka ini tidak dimasukkan ke pengukuran performa.

Bukti lengkap: [mysql-concurrency.json](evidence/mysql-concurrency.json), [harness](tests/concurrency.py), [worker/kernel](tests/audit.php).

| Kasus | Hasil aktual | Gate |
| --- | --- | --- |
| Dua resepsionis, satu kuota tersisa | 302 + 422; satu appointment, satu queue, satu nomor antrean | PASS |
| Dua akun pasien memilih dokter/sesi sama, kuota satu | 302 + 422; satu booking; account type dan role Patient nyata | PASS |
| Dua request booking dengan UUID identik | 302 + 302; satu appointment/queue, tanpa duplikasi patient | PASS |
| Dua sesi dokter penanggung jawab menyimpan versi RME sama | 200 + 409; lock version naik satu, tidak ada lost update | PASS |
| Dua dokter berbeda mencoba RME sama | 200 + 403; dokter yang tidak ditugaskan ditolak | PASS |
| Admin mengganti dokter sesi saat booking berjalan | 302 + 422; tidak ada mismatch dokter appointment/schedule | PASS |
| Owner menonaktifkan resepsionis saat request berjalan | Update sukses; request yang telah diotorisasi dapat selesai; request berikutnya dialihkan ke login | PASS pada HTTP |
| Perubahan entitlement ke Free bersamaan dengan draft aktif | Kedua operasi sukses; draft IN_PROGRESS tetap bisa disimpan, booking baru ditolak 403, histori tetap ada | PASS pada service + HTTP |
| Owner memilih paket Individual melalui checkout DOKU sandbox saat dokter menyimpan | Owner 302, dokter 200; satu pembayaran pending, draft naik satu versi; paket Team yang berjalan tidak berubah sebelum callback sah | PASS |

Perubahan ke Free menguji `SubscriptionService::downgradeToFree()` dari proses independen, bukan menyatakan owner mempunyai tombol downgrade yang sebenarnya merupakan operasi platform. Kasus checkout owner menggunakan HTTP kernel, middleware, owner fixture, dan sandbox nyata. Tidak ada pembayaran sungguhan dilakukan.

Sesi berakhir saat menyimpan diuji terpisah di Playwright: HTTP **401**, teks belum tersimpan tetap ada, versi database tidak berubah, pengguna login kembali lalu finalisasi melalui UI. Laravel dapat menolak dengan 401 pada request browser same-origin; tidak memaksakan semua sesi berakhir menjadi 419. [Bukti browser](evidence/playwright-results.json).

### Integritas dan batas

Tidak ditemukan duplicate booking, duplicate queue, nomor antrean ganda, pelanggaran kuota, lost update, tenant mismatch, atau deadlock tak tertangani pada skenario ini. Satu dokter lain memang harus ditolak, sehingga lost update juga diuji memakai dua sesi dokter penanggung jawab yang sama.

Request yang sudah melewati authorization sebelum deactivation bukan dianggap otomatis dibatalkan. **Socket yang tetap menerima event sesudah revokasi adalah masalah terpisah PR-01**, bukan PASS akses menyeluruh. [Realtime report](#realtime).

**BLOCKED:** pergantian paket yang benar-benar diaktifkan oleh callback DOKU bersamaan dengan operasi klinik, karena receiver sandbox publik belum tersedia. **NOT TESTED:** soak beberapa jam, failover MySQL, jaringan database terputus saat commit, dan proof semua pola deadlock yang mungkin. Hasil sembilan race tidak menjamin tidak ada race lain.

Reproduksi:

```powershell
python qa-audit/production-readiness/tests/concurrency.py
```

Rekomendasi: pertahankan urutan lock dan constraint yang ada, perbaiki revokasi socket, lalu ulangi pada versi MySQL serta stack deployment staging yang akan dipakai.

---

<a id="realtime"></a>

## 4. Realtime test report

Sumber: `REALTIME_TEST_REPORT.md`.

Gate keseluruhan: **FAIL**. Koneksi, delivery normal, reconnect, dan isolasi tenant baru lulus; revokasi socket aktif gagal.

| Pemeriksaan | Bukti aktual | Status |
| --- | --- | --- |
| Reverb dan database worker benar-benar hidup | TCP/WebSocket nyata, Echo browser menerima `queue.updated` dari worker | PASS |
| Dua browser staf menerima perubahan setelah check-in | Dua konteks browser berbeda, payload whitelist enam key | PASS |
| Tenant lain subscribe private clinic channel | Authorization 403 dan nol event diterima | PASS |
| Payload antrean tidak berisi identitas/isi medis | Hanya queue_id, display_number, status, doctor_id, queue_date, updated_at | PASS pada event yang diuji |
| Disconnect dan reconnect | Socket ditutup, Pusher reconnect, subscribe ulang dan event berikutnya diterima | PASS |
| Role dicabut ketika socket aktif | Subscribe baru 403; socket lama tetap connected dan menerima satu event check-in berikutnya | FAIL — PR-01 |
| Staf dinonaktifkan melalui UI | HTTP berikutnya dialihkan ke login; socket lama masih menerima satu event berikutnya | FAIL — PR-01 |
| Burst 40 check-in | 40 event diterima, nol hilang; latency p50 1,270 s / p95 2,283 s / p99 2,590 s | FAIL terhadap asumsi p95 <= 2 s |

Bukti: [browser results](evidence/playwright-results.json), [permission revocation](evidence/permission-revocation.json), [deactivation](evidence/deactivation-revocation.json), [load](evidence/load-results.json).

### Reproduksi PR-01 dan akar masalah

1. Login dua browser resepsionis dan tunggu private clinic channel ter-subscribe.
2. Cabut role browser kedua menggunakan API Spatie pada fixture, atau nonaktifkan akun melalui UI admin.
3. Buktikan akses HTTP/authorization channel baru ditolak.
4. Buat booking dan lakukan check-in dari pengguna yang masih berwenang.
5. Buktikan browser kontrol menerima event baru; browser yang kehilangan akses juga masih menerimanya.

Authorization channel berjalan ketika subscribe. Tidak ada mekanisme server untuk mencabut subscription lama pada perubahan role/status akun. Penolakan HTTP tidak otomatis mengakhiri koneksi Reverb. Severity **P2 / Medium**: akses private masih hidup setelah revokasi; tidak ditemukan isi medis pada event yang diamati. Tidak mengubah source produksi selama audit. [Temuan lengkap](#new-findings).

Channel `clinic.{id}.queue-board` memang public dan membawa metadata antrean nonmedis yang sama. Sifat public itu tidak dianggap kebocoran lintas tenant; private clinic channel tetap harus memenuhi kontrak revokasi akses.

### Batas dan tindak lanjut

**NOT TESTED:** revokasi channel user notification yang sudah aktif, perpindahan tenant ketika socket hidup, TLS/wss deployment, proxy idle timeout, horizontal Reverb/Redis, restart node Reverb saat event sedang dipublikasikan, dan koneksi ribuan socket. Tidak menyamakan authorization private clinic channel dengan audit semua channel.

Terapkan revokasi/otorisasi ulang yang ditegakkan server; jangan mengandalkan browser untuk disconnect. Profilkan queue polling, fanout dan refetch UI untuk target latency, lalu retest di staging. Referensi operasi: [dokumentasi Reverb Laravel 13](https://laravel.com/framework/docs/13.x/reverb).

```powershell
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/browser.mjs
```

Run awal dengan selector/probe yang tidak tepat tetap diarsipkan. Hanya run final dengan positive control event yang dipakai untuk gate revokasi.

---

<a id="integration"></a>

## 5. Integration test report

Sumber: `INTEGRATION_TEST_REPORT.md`.

Gate keseluruhan: **BLOCKED** untuk integrasi provider end-to-end. SMTP lokal, workflow autentikasi, scheduler/worker, checkout dan reconciliation sandbox telah diuji nyata.

| Integrasi / skenario | Status | Bukti dan batas |
| --- | --- | --- |
| SMTP TCP dan penerimaan inbox testing | PASS | Symfony mailer mengirim MIME ke sink loopback; sink tidak meneruskan email |
| Staff create, signed verification, login | PASS | Receptionist baru dibuat admin melalui UI, login unverified, link dari inbox, kemudian dashboard |
| Password reset mandiri | PASS | Email reset dari inbox dipakai di browser; sesi lama 401, login password baru berhasil |
| Password reset oleh admin | PASS | UI admin mengganti password; sesi lama ditolak, fresh login berhasil |
| Email staf diubah | PASS | Verified timestamp direset, link lama 403, email baru diterima dan diverifikasi melalui UI |
| Scheduler -> queue -> inbox | PASS | `schedule:test` menjalankan entry asli dua kali; satu reminder, satu email, sent/mailed tersimpan, nol failed |
| Queue delivery dan restart | PASS | Job bertahan ketika worker berhenti dan habis setelah restart; lihat recovery |
| DOKU sandbox checkout | PASS | Request HTTPS nyata; URL checkout HTTPS diterima dan invoice pending; tidak membuka/membayar dengan instrumen pembayaran |
| DOKU backend reconciliation | PASS | GET status provider nyata diproses; subscription tetap tidak berubah untuk pembayaran pending |
| Owner checkout bersamaan dengan pelayanan | PASS | Dua proses/koneksi MySQL; pembayaran pending dan draft dokter sukses tanpa aktivasi prematur |
| Callback DOKU dari provider | BLOCKED | Tidak ada endpoint notifikasi audit yang dapat dijangkau publik |
| Payment failed/timeout/duplicate webhook dari sandbox nyata | BLOCKED | Belum ada siklus pembayaran/callback sandbox lengkap; tidak diganti klaim fake test |
| Signature invalid, mismatch invoice/amount, replay/failure handling lokal | PASS dalam regression suite terkait | HTTP fake/fixture pada suite PHP; bukan bukti provider mengirim callback nyata |
| Private photo upload dan policy | PASS | Upload browser; assigned doctor 200, tujuh persona lain 403; endpoint private diuji kembali dari hasil restore |
| Kegagalan filesystem nyata | PASS | Root disk diarahkan ke file penghalang di proses audit; save gagal, versi/foto database tidak berubah, file lama utuh |

Bukti: [Playwright](evidence/playwright-results.json), [integration](evidence/integration-results.json), [sandbox probe](evidence/doku-sandbox-probe.json), [scheduler](evidence/scheduler.txt), [concurrency](evidence/mysql-concurrency.json), [recovery](evidence/recovery-results.json), [PHP](evidence/php-main.xml).

### Keamanan dan metode

Konfigurasi lokal mempunyai credential sandbox yang tidak menyerupai placeholder. Probe memakai nilai tersebut hanya di environment proses, dengan guard host persis `https://api-sandbox.doku.com`. Runtime HTTP umum tetap menonaktifkan credential DOKU. Tidak menyalin credential, signature, URL checkout, token reset/verifikasi/booking atau MIME ke evidence publik. Semua owner/customer memakai identitas sintetis domain `.test`; tidak ada pembayaran nyata atau email kepada pengguna sungguhan.

Invoice pending dan reconciliation tidak mengaktifkan subscription. Redirect browser tidak dipakai sebagai bukti pembayaran. Callback terkontrol dalam test lokal tidak diperlakukan sebagai callback yang benar-benar dikirim provider.

Pada SMTP, staf Doctor memakai notification path yang sama tetapi siklus inbox nyata lengkap dijalankan pada Receptionist; Doctor mempunyai regression coverage PHP. **NOT TESTED:** inbox eksternal, TLS SMTP eksternal, SPF/DKIM/DMARC, reputation/deliverability, dan cron daemon VPS. Scheduler diuji dengan `schedule:test` pada due-date fixture, bukan menunggu pergantian jam atau membuktikan cron terpasang.

### Reproduksi dan sisa pekerjaan

```powershell
python qa-audit/production-readiness/tests/integration.py
python qa-audit/production-readiness/tests/doku_probe.py
```

`integration.py` memperbarui hasil lokal; jalankan probe sesudahnya untuk memperbarui bukti sandbox. Sediakan receiver HTTPS publik khusus staging yang terhubung ke database audit terisolasi untuk melanjutkan callback sukses/gagal, timeout, replay dan duplicate webhook. Jangan menunjuk callback staging ke aplikasi/database produksi. Tidak membuat tunnel/deploy tanpa izin.

---

<a id="load"></a>

## 6. Load and reliability report

Sumber: `LOAD_TEST_REPORT.md`.

Gate: **FAIL terhadap asumsi realtime p95**. Bukan sertifikasi kapasitas produksi. Semua angka di bawah berasal dari run, bukan target yang diklaim sudah disepakati bisnis.

### Asumsi SLO

Pengguna tidak mempunyai target operasional dan meminta nilai standar. Tidak ada satu angka universal untuk semua klinik. Asumsi awal: request interaktif p95 <= 2 s, p99 <= 5 s; 5xx/network error <= 0,1%; event antrean p95 <= 2 s; nol pelanggaran integritas/akses. Target availability bulanan, pengguna unik produksi, booking/jam dan RPO/RTO belum ditetapkan. Pendekatan SLI/SLO mengikuti [Google SRE](https://sre.google/sre-book/service-level-objectives/); **angka target adalah asumsi audit ini**, bukan angka baku dari sumber tersebut.

### Workload dan lingkungan

MySQL nyata, empat backend PHP independen di belakang proxy loopback, satu database worker, Reverb nyata, database session/cache, 2.003 pasien sintetis pada klinik load. Lima akun berbeda dengan **20 sesi login independen**: receptionist, doctor, patient, clinic admin, owner persona. Owner tetap role Clinic Admin; Platform Admin tidak menjadi bagian mix load ini dan diuji pada UAT.

Closed loop tanpa think time, tahap 1/5/10/20 request aktif sekitar 15 detik per tahap. Ini stress singkat, bukan simulasi 20 orang unik, arrival-rate tetap, uji 8 jam, atau beban produksi lewat jaringan internet. Authentication setup dilakukan sebelum pengukuran tahap baca.

Endpoint baca: dashboard, pasien, appointments, antrean, medical-record list, staff, jadwal dan histori pembayaran owner. Tidak mengambil SQL, bindings, nama pasien, isi medis atau URL bertoken ke telemetry. Durasi SQL diukur dengan DB listener sebagai waktu query yang diamati client, bukan profiler internal server.

### Hasil aktual

| Sesi aktif | Request | Throughput req/s | p50 ms | p95 ms | p99 ms | HTTP 200 | HTTP 429 | 5xx/network |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | 172 | 11,44 | 73,5 | 124,4 | 340,4 | 172 | 0 | 0 |
| 5 | 778 | 51,50 | 83,3 | 155,8 | 333,0 | 725 | 53 | 0 |
| 10 | 845 | 55,05 | 166,5 | 337,1 | 507,8 | 757 | 88 | 0 |
| 20 | 872 | 55,60 | 281,7 | 721,5 | 802,9 | 777 | 95 | 0 |

Total **2.667 read request**, 2.431 HTTP 200, **236 HTTP 429**, nol 5xx/network error. Halaman subscription memakai limiter `payment-status` per akun; empat sesi owner melakukan pembacaan berulang tanpa think time dan terkena limiter. Angka 429 tetap disertakan. Ini pembatasan yang disengaja, bukan alasan menghapus limiter. Skenario yang mengharapkan seluruh read 200 **FAIL**; SLO 5xx numerik **PASS**. Percentile agregat mencakup respons 429 yang cepat sehingga tidak boleh dipakai sebagai jaminan latency semua transaksi sukses atau kapasitas klinik produksi.

| Metrik database | 1 sesi | 5 sesi | 10 sesi | 20 sesi |
| --- | --- | --- | --- | --- |
| Query p95 ms | 1,87 | 1,63 | 1,65 | 1,72 |
| Query p99 ms | 24,12 | 20,56 | 21,15 | 21,23 |
| Total SQL/request p95 ms | 39,43 | 36,64 | 37,69 | 37,32 |
| Rata-rata query/request | 21,0 | 21,1 | 20,5 | 20,5 |

Burst tulis tambahan: **4 sesi resepsionis, 40 booking dan 40 check-in**. Seluruh booking 302 sesuai kontrak redirect, seluruh check-in 200, tepat 40 appointment dan 40 queue ditambahkan, tidak ada nomor antrean ganda/mismatch dokter. Booking p50 **656,8 ms**, p95 **1.617,5 ms**, p99 **1.646,3 ms**. Waktu keseluruhan burst mencakup pembacaan fixture untuk observasi, sehingga bukan benchmark throughput booking murni.

Seluruh 40 event WebSocket diterima: p50 **1.270 ms**, p95 **2.283 ms**, p99 **2.590 ms**, nol event hilang. **FAIL** terhadap asumsi p95 <= 2.000 ms. Pengukuran dari sebelum request check-in sampai handler browser menerima event, termasuk antrean dan scheduling browser; bukan waktu server broadcast saja.

### CPU, memori dan konsistensi

89 sampel selama 88,91 detik untuk delapan proses service milik audit. Rata-rata konsumsi CPU agregat **1,709 core ekuivalen**, sekitar **10,68%** dari 16 logical processor. Peak jumlah working set proses service **361,7 MiB**. Tidak mencakup Chrome, load driver, PHP CLI observer, cache OS, atau seluruh memori mesin; working set bersama dapat terhitung lebih dari sekali. Tidak dibuat target CPU/memori kelulusan yang tidak disepakati.

Setelah beban: pasangan appointment/queue konsisten, nomor antrean unik, kuota tidak terlampaui, versi RME final tetap ada. Worker kemudian terbukti drain tanpa failed job; schema/recovery sesudah beban menemukan nol orphan dan tenant mismatch.

Bukti: [load-results.json](evidence/load-results.json), [resource-samples.json](evidence/resource-samples.json), [recovery](evidence/recovery-results.json), [harness](tests/load.mjs).

### Batas dan rekomendasi

**NOT TESTED:** spike ratusan pengguna unik, soak harian/mingguan, cold-start deployment, disk penuh pada server sesungguhnya, network partition, horizontal scaling, error budget bulanan, serta latency p95 khusus respons 200 terpisah dari semua respons. Run awal yang mengakses route owner tidak ada disimpan sebagai `load-harness-attempt1.json`; tidak dipakai sebagai hasil final.

Tetapkan volume operasional dan think time yang realistis, pisahkan profil clinical read dari payment-status polling, ukur respons sukses tersendiri, dan retest pada stack/server staging yang didukung. Profilkan worker polling/refetch/fanout sebelum menetapkan tuning. Tidak mengubah source atau limiter untuk memperoleh hasil hijau.

```powershell
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/load.mjs
```

---

<a id="disaster-recovery"></a>

## 7. Disaster recovery report

Sumber: `DISASTER_RECOVERY_REPORT.md`.

Gate pemulihan lokal terisolasi: **PASS, 7/7 pemeriksaan**. RPO/RTO produksi dan runbook operasional: **NOT TESTED**.

### Bukti aktual

| Pemeriksaan | Hasil | Gate |
| --- | --- | --- |
| Kegagalan storage nyata | Root disk di proses uji berupa file penghalang; upload gagal, versi RME tidak berubah, tidak ada photo reference baru; hash file lama tetap | PASS |
| Transaksi booking sengaja gagal | Patient, appointment, queue, notification, jobs dan audit log kembali ke jumlah sebelum transaksi; koneksi tetap dapat dipakai | PASS |
| Worker dihentikan lalu dihidupkan | Dua job bertahan di database saat worker mati, kemudian drain; nol failed job | PASS |
| Backup/restore MySQL | Dump hanya database audit; restore ke database audit lain; checksum seluruh 44 tabel sama | PASS |
| Pemulihan private file | Empat file dipulihkan ke direktori lain di luar webroot; semua SHA-256 cocok | PASS |
| Restart aplikasi | Empat backend dihentikan setelah identitas proses diverifikasi; PID baru hidup dan health endpoint 200 | PASS |
| Aplikasi membaca hasil restore | HTTP kernel pada database hasil restore dan disk hasil restore mengembalikan foto private 200 untuk dokter penanggung jawab | PASS |

Hasil numerik terbaru, termasuk durasi backup/restore dan PID, ada pada [recovery-results.json](evidence/recovery-results.json). File dump, MIME inbox dan byte private tidak disalin ke repository. Backup awal diarsipkan sebagai `recovery-initial-run.json`; run final memakai database restore baru serta retry-after 90 s > timeout worker 30 s.

### Cara reproduksi

1. Gunakan instance audit dari environment report dan jalankan browser fixture agar terdapat foto klinis sintetis.
2. Tunggu job yang berjalan drain; hentikan hanya worker audit yang command line-nya cocok dengan PID yang direkam.
3. Injeksi exception setelah operasi booking dalam outer transaction; bandingkan enam tabel sebelum/sesudah.
4. Buat booking ketika worker berhenti, buktikan job tetap ada, restart worker, tunggu drain dan periksa failed jobs.
5. Quiesce backend audit/worker, jalankan `mysqldump --single-transaction`, buat database restore baru, import, dan bandingkan checksum semua tabel.
6. Salin file private ke backup dan direktori restore baru, bandingkan hash, restart backend dan uji endpoint foto dari data hasil restore.

```powershell
python qa-audit/production-readiness/tests/recovery.py
```

Tidak menghentikan MySQL pengguna, tidak menimpa database asal, tidak menghapus pasien atau histori. Nama database restore dan path temporary divalidasi. Kegagalan transaksi memakai fixture sintetis tersendiri.

### Batas yang belum terbukti

**NOT TESTED:** kill worker di tengah pengiriman SMTP/provider sesudah side effect tetapi sebelum ack, exactly-once delivery eksternal, crash mysqld saat commit, corrupt backup, incremental/binlog point-in-time restore, offsite restore di mesin lain, backup encryption/retention/key escrow, kehilangan satu disk/server, dan pemulihan VPS dengan process manager sesungguhnya.

Backup+restore lokal yang cepat tidak menetapkan RTO/RPO bisnis. Database dump dan file harus diambil pada titik konsisten; quiesce audit di run ini tidak membuktikan backup terjadwal tanpa downtime saat klinik sibuk. Restart `/up` sendiri bukan bukti seluruh alur sehat, sehingga ditambah schema/orphan check serta endpoint foto hasil restore.

Rekomendasi sebelum GO: tetapkan RPO/RTO, siapkan backup offsite terenkripsi dan restore drill berkala, uji crash worker di titik side effect, dokumentasikan supervisor/cron/restart, serta buat release/rollback yang dapat diuji di staging. Source/deployment produksi tidak diubah otomatis.

---

<a id="human-uat"></a>

## 8. Human acceptance and clinic-day simulation

Sumber: `HUMAN_UAT_REPORT.md`.

Alur hari klinik terkompresi melalui UI: **PASS**. Gate penerimaan menyeluruh: **FAIL karena PR-01**. Acceptance oleh manusia klinisi dan shift delapan jam nyata: **NOT TESTED**.

### Skenario normal melalui Playwright

Satu klinik sintetis baru dengan seluruh role tersimpan: Clinic Admin, Receptionist, Doctor, Patient dan Platform Admin, ditambah owner persona pada role Clinic Admin. Enam konteks browser berjalan bersamaan. Tidak mengubah database lewat SQL untuk memperbaiki atau melompati langkah workflow.

| Tahap | Aksi UI yang benar-benar dilakukan | Status |
| --- | --- | --- |
| Pembukaan | Admin membuka jadwal; owner meninjau subscription; platform admin membuka tenant metadata | PASS |
| Booking pasien | Patient login, memilih dokter/layanan/sesi dengan keyboard, mengisi form publik, menerima konfirmasi dan nomor antrean | PASS |
| Kedatangan | Resepsionis menekan Check-in dan Panggil melalui tombol queue UI | PASS |
| Kesalahan manusia | Pasien dilewati, dikembalikan ke WAITING, lalu dipanggil lagi melalui UI | PASS |
| Pemeriksaan | Dokter menekan Mulai periksa, membuka RME, mengisi subjektif | PASS |
| Validasi | Upaya finalisasi tidak lengkap tetap DRAFT; dokter menekan Kembali periksa, melengkapi assessment/rencana | PASS |
| Finalisasi | Finalisasi UI membuat appointment/queue/visit COMPLETED dan RME FINAL | PASS |
| Penutupan | Pasien membuka appointment miliknya; admin dan owner meninjau dashboard; tepat satu appointment/queue/visit tersimpan | PASS |

Durasi alur otomatis sekitar 30,6 detik. Ini kompresi urutan aktivitas pada waktu sistem yang sama, **bukan delapan jam operasional atau pengujian buka/tutup jam klinik**. Fixture menggunakan jam 00:00-23:59 untuk menjalankan alur pada waktu audit lokal. Tidak mengklaim fitur closing cash/kasir, receipt, atau export yang belum tersedia.

Bukti: [clinic-day.json](evidence/clinic-day.json), [day.mjs](tests/day.mjs). Nol page error JavaScript dan nol developer data repair selama workflow. Run awal yang tidak menutup dialog validasi sebelum kembali mengedit diarsipkan; run final menggunakan tombol Kembali periksa yang memang tersedia.

### Skenario pendamping

Suite [browser.mjs](tests/browser.mjs) menjalankan **14 kasus: 12 PASS, 2 FAIL**. Tambahan clinic-day di atas **1 PASS**; total Playwright **13 PASS, 2 FAIL, 0 BLOCKED**.

| Skenario | Status |
| --- | --- |
| Sembilan konteks browser login, lima role dan owner persona | PASS |
| Responsive 375/768/1024/1440 px; native keyboard Escape dan focus return pada drawer | PASS |
| Admin membuat staf, inbox menerima verifikasi, signed verification, login | PASS |
| Upload foto, draft, finalisasi dan koreksi beralasan dengan revision history | PASS |
| Owner/admin/receptionist/patient/platform/dokter lain/tenant lain ditolak pada RME dan foto private | PASS |
| Reset password mandiri dan admin; sesi lama ditolak; fresh login sukses | PASS |
| Perubahan email: verified direset, link lama ditolak, link baru dari SMTP berhasil | PASS |
| Sesi berakhir saat autosave | PASS: server 401, teks dipertahankan, tidak ada write, login ulang dan finalisasi UI berhasil |
| Dua resepsionis/pasien berebut kuota dan dua sesi dokter menulis bersamaan | PASS pada harness MySQL independen; bukan sekadar render browser |
| Penonaktifan staf dan pencabutan role ketika socket masih aktif | FAIL pada socket; HTTP berikutnya sudah menolak |

Playwright memakai Chrome nyata, bukan DOM emulator atau keyboard event JavaScript pengganti. Beberapa aksi suite pendamping memakai request authenticated browser context untuk mengisolasi protokol; clinic-day khusus di atas memakai tombol/form UI untuk booking, check-in, call, skip/return dan start/finalize.

### Yang belum diterima

**BLOCKED:** perubahan paket yang benar-benar diaktifkan oleh callback DOKU saat operasional; checkout owner dan reconciliation pending sudah diuji, tetapi provider callback belum dapat menjangkau receiver audit.

**NOT TESTED:** sign-off dokter/resepsionis manusia, shift nyata, screen reader, semua kombinasi browser/mobile OS, proses klinik ketika banyak pasien datang berjam-jam, cetak dokumen, billing pasien, serta semua keadaan error eksternal. Load singkat dan race MySQL melengkapi busy-condition evidence, tidak menggantikan penerimaan manusia.

```powershell
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/browser.mjs
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/day.mjs
```

Rekomendasi: perbaiki PR-01, ulangi failure scenario tanpa menghapus test, kemudian lakukan sesi UAT bersama staf klinik di staging dengan volume dan perangkat yang akan dipakai.

---

<a id="new-findings"></a>

## 9. Temuan baru production readiness

Sumber: `NEW_FINDINGS.md`.

Status per run 11 November 2026. QA-01–QA-15 tidak diimplementasikan ulang. Source produksi tidak diubah oleh audit ini.

### PR-01 — Revokasi akses tidak mencabut subscription WebSocket yang sudah aktif

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

### PR-02 — Assertion urutan key JSON tidak portabel pada MySQL

**OPEN — P3 / Low — regression suite FAIL, bukan bukti RME kehilangan data.**

`RmeGeneralDentalTest::test_assigned_doctor_can_persist_general_rme_and_odontogram_in_draft` menggunakan `assertSame` untuk array objek JSON. MySQL menormalisasi urutan key object menjadi `condition`, `tooth_number`; fixture mengharapkan `tooth_number`, `condition`. Nilai dan urutan elemen gigi tetap sama. Suite SQLite historis lulus, suite MySQL nyata gagal.

Bukti: [php-main.txt](evidence/php-main.txt), [php-main.xml](evidence/php-main.xml). Perbaikan yang disarankan terbatas pada assertion: bandingkan key/value per elemen tanpa mensyaratkan urutan key object, sambil mempertahankan urutan elemen gigi. Test lama tidak dihapus atau dilonggarkan selama audit.

### Catatan harness, bukan bug aplikasi

Run awal Playwright memiliki selector label/ID yang salah, SMTP `MAIL_URL` kosong yang mengganti transport pada Laravel, pemanggilan `Echo.connect()` yang membuat connector baru, dan payload pasien baru yang memicu duplicate review. Run tersebut disimpan sebagai `playwright-harness-attempt*.json`; hasilnya bukan hasil final gate aplikasi. Konfigurasi/harness diperbaiki hanya di direktori audit dan environment proses sementara.

Test subdomain juga gagal ketika suite mewarisi `APP_URL` berupa IP: route fallback `/clinic/{slug}` memang mengalihkan ke subdomain. Retest dengan `APP_URL=http://localhost` lulus 1 test / 11 assertions; bukti [public-host-retest.txt](evidence/public-host-retest.txt). Ini perbedaan fixture host, bukan regresi isolasi tenant.

Probe revokasi awal yang hanya membuat booking tidak menghasilkan `QueueUpdated`; hasil PASS probe itu tidak sah sebagai bukti revokasi. Run final melakukan check-in, memverifikasi penerimaan pada browser kontrol yang masih berwenang, lalu membuktikan penerimaan pada socket yang sudah kehilangan akses. Hasil final **FAIL** dipertahankan.

### Risiko performa dan deployment yang belum terselesaikan

Target realtime awal p95 <= 2 detik belum tercapai pada burst 40 booking/check-in: p95 aktual 2,283 detik, tanpa event hilang. Ini **FAIL terhadap asumsi SLO**, belum merupakan bukti akar masalah pada satu fungsi tertentu. Profiling worker polling, refetch browser, antrean, dan konfigurasi server diperlukan; tidak dilakukan tuning source otomatis.

Load tanpa think time mengakses halaman pembayaran berulang dan menerima HTTP 429 sesuai limiter per akun. Jumlahnya dilaporkan, bukan dibuang dari statistik. Ini bukti pembatasan workload, bukan bukti kegagalan transaksi atau alasan untuk menonaktifkan limiter.

Workflow deployment memperbarui direktori aplikasi secara langsung tanpa rollback otomatis atau langkah restart worker/Reverb. Konfigurasi process manager di VPS belum diperiksa. Binary MySQL audit 8.0.30 juga bukan acuan versi produksi yang didukung: Oracle menyatakan MySQL 8.0 masuk Sustaining Support pada April 2026 ([pengumuman resmi](https://www.mysql.com/support/eol-notice.html)). Tidak dilakukan upgrade server atau dependency otomatis.

### PR-03 — Factory subscription dapat melampaui panjang kolom MySQL

**OPEN — P3 / Low — error fixture membuat suite MySQL tidak stabil.**

Run penuh terbaru menghasilkan SQLSTATE 22001 / MySQL 1406 pada `RmeGeneralDentalTest::test_invalid_general_and_odontogram_payload_is_rejected_without_mutating_record`. Error terjadi pada pembuatan fixture sebelum validasi RME yang hendak diuji. `PaymentFactory` membuat `SubscriptionPriceFactory`, yang membuat `SubscriptionPlanFactory`. Factory plan memakai `fake()->unique()->slug(2)` tanpa batas panjang, sedangkan `subscription_plans.code` adalah varchar(30).

Reproduksi deterministik: `fake()->seed(0)`, reset unique generator, lalu `SubscriptionPlan::factory()->raw()` menghasilkan kode 31 karakter, melampaui schema 30. Probe ini tidak menulis database. Bukti: [factory-contract.json](evidence/factory-contract.json), [php-main.xml](evidence/php-main.xml). Run pertama tidak mengalami error ini; ketidakstabilan tidak disembunyikan dengan memilih run hijau.

Rekomendasi: batasi kode factory sesuai schema dan gunakan relasi plan/price eksplisit pada fixture terkait. Jangan memperlebar kolom produksi hanya untuk mengakomodasi string Faker. Katalog produksi yang memakai kode tetap tidak terbukti rusak oleh temuan fixture ini. Tidak ada source factory atau test lama diubah dalam audit ini.

---

<a id="test-summary"></a>

## 10. Rekap hasil pengujian (JSON)

Sumber: `evidence/test-summary.json`. Ini rekap run audit yang sama, bukan run tambahan.

```json
{
  "php-main": {
    "tests": 341,
    "pass": 339,
    "fail": 1,
    "error": 1,
    "skip": 0
  },
  "php-audit": {
    "tests": 22,
    "pass": 22,
    "fail": 0,
    "error": 0,
    "skip": 0
  },
  "playwright": {
    "PASS": 12,
    "FAIL": 2,
    "BLOCKED": 0
  },
  "clinic_day": "PASS",
  "javascript": {
    "tests": 7,
    "pass": 7,
    "fail": 0,
    "evidence": "javascript.txt"
  },
  "mysql_concurrency": {
    "PASS": 9,
    "FAIL": 0
  },
  "recovery": {
    "PASS": 7,
    "FAIL": 0
  },
  "open_findings": {
    "P2": 1,
    "P3": 2,
    "total": 3
  },
  "decision": "NO-GO"
}
```
