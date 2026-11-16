# Production readiness scorecard

**Keputusan: NO-GO untuk penggunaan produksi multi-user.**

Audit operasional lokal selesai pada 11 November 2026. Alur klinik utama, transaksi MySQL, SMTP inbox, checkout/reconciliation DOKU sandbox dan pemulihan lokal sudah memiliki bukti nyata. Namun revokasi socket aktif gagal, asumsi latency realtime belum tercapai, suite MySQL masih gagal, serta callback provider dan lingkungan deployment belum terverifikasi. Tidak memberi CONDITIONAL GO hanya berdasarkan banyaknya test yang lulus.

## Gate

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

## Jumlah test dan skenario

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

## Temuan tersisa

| ID | Severity | Status | Dampak |
| --- | --- | --- | --- |
| PR-01 | P2 / Medium | OPEN | Pencabutan role/deactivation tidak mengakhiri private socket yang sudah aktif; tidak ditemukan payload medis pada event yang diuji |
| PR-02 | P3 / Low | OPEN | Assertion urutan key JSON tidak portabel pada MySQL; isi/value yang dibandingkan sama |
| PR-03 | P3 / Low | OPEN | Factory kode plan tidak dibatasi varchar(30); reproduksi seed 0 menghasilkan 31 karakter dan error MySQL |

[Akar masalah, reproduksi dan rekomendasi](NEW_FINDINGS.md). QA-01–QA-15 tidak diimplementasikan ulang. Tidak ditemukan bukti baru yang mengharuskan membuka kembali fix tersebut. Satu bug akses aplikasi dan dua masalah test/fixture baru tetap terbuka; tidak menyatakan suite hijau atau aplikasi siap produksi.

## Risiko regresi dan syarat melanjutkan

1. Perbaikan PR-01 harus ditegakkan server dan diuji terhadap socket lama, auth ulang, reconnect, channel clinic dan user notification, semua role/tenant. Risiko regresinya pada fanout, reconnect dan delivery; source belum diubah.
2. Perbaiki portability assertion/factory secara minimal sambil mempertahankan regression test, lalu jalankan ulang seluruh suite MySQL. Jangan menyamakan error fixture dengan bukti validasi RME sudah diuji pada run yang error itu.
3. Tetapkan volume klinik dan SLO, profilkan latency realtime, jalankan load/soak realistis pada staging dengan stack dan database server yang didukung. Jangan mematikan limiter untuk menjadikan run hijau.
4. Sediakan callback sandbox HTTPS terisolasi; buktikan aktivasi, failed/timeout, duplicate/replay webhook dan rekonsiliasi saat pelayanan berlangsung. Jangan memakai database produksi.
5. Buktikan supervisor/cron, worker/Reverb restart, health gate dan rollback deployment, backup offsite dan RPO/RTO; lanjutkan UAT bersama manusia klinik.

**Layak melanjutkan pengujian production-readiness di staging terkontrol: YA. Layak dipakai klinik produksi multi-user sekarang: TIDAK.** Keputusan GO perlu bukti penutupan gate tersebut, bukan sekadar jumlah PASS.

## Laporan pendukung

- [Environment](ENVIRONMENT_REPORT.md)
- [MySQL concurrency](MYSQL_CONCURRENCY_REPORT.md)
- [Realtime](REALTIME_TEST_REPORT.md)
- [Integration](INTEGRATION_TEST_REPORT.md)
- [Load](LOAD_TEST_REPORT.md)
- [Disaster recovery](DISASTER_RECOVERY_REPORT.md)
- [Human UAT](HUMAN_UAT_REPORT.md)

Seluruh perubahan kode sesi readiness dibatasi pada harness/evidence/laporan audit. Environment sementara dipakai untuk service pengujian; catatan koreksi storage runtime awal tetap dilaporkan di environment report. Tidak mengubah source produksi, menghapus histori pasien, melakukan commit, push, deploy, migration produksi, atau mengganti konfigurasi produksi.
