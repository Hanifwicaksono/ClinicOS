# Urutan pengujian browser terisolasi

Semua fixture hanya boleh memakai runtime SQLite/storage disposable dari `scripts/runtime.mjs`. Jangan menjalankannya terhadap `.env` atau database aplikasi. Bukti run ini berada pada `fixes/evidence`; untuk run baru, gunakan direktori bukti baru melalui `CLINICOS_AUDIT_EVIDENCE` agar ID skenario tetap unik.

1. Set `CLINICOS_AUDIT_EVIDENCE` ke direktori bukti absolut, lalu jalankan `node qa-audit/deep-functional-ux/scripts/runtime.mjs` di terminal terpisah. Runtime menyiapkan migration/seed/build **hanya pada direktori temp** dan menyimpan metadata `runtime.json`.
2. `DFU_BROWSER_LABEL=before`: `fixes/browser-phase1.mjs` dan `DFU_BROWSER_LABEL=before-ux`: `fixes/browser-ux.mjs` adalah bukti sebelum fix pada run ini. Source sekarang sudah diperbaiki; hasil merah itu tidak diharapkan berulang pada source terkini.
3. Jalankan `fixes/build.mjs` untuk rebuild JavaScript ke public/build runtime disposable. CSS landing disajikan dari source repository oleh router.
4. `DFU_BROWSER_LABEL=final`: `fixes/browser-phase1.mjs`, kemudian `fixes/browser-ux.mjs`. Script pertama membuat fixture dokter/pasien/sesi eksplisit untuk kedua script. Jangan menjalankannya bersamaan karena menulis `scenarios.json` yang sama.
5. Cakupan lintas fitur: `scripts/administration.mjs` → `scripts/retests.mjs` → `scripts/booking-queue.mjs` → `fixes/browser-regression.mjs` → `scripts/clinical.mjs` → `fixes/browser-clinical-retest.mjs` → `fixes/browser-portal-booking.mjs`. Urutan memakai `journey-state.json` dari langkah sebelumnya. Failure harness awal tetap disimpan, dengan `superseded_by` menuju retest berprasyarat benar.
6. Backend: `DFU_TEST_LABEL=final-backend-r3`, lalu `fixes/run-php.mjs tests qa-audit/tests`. Runner memakai database SQLite `:memory:` baru untuk setiap run. Periksa summary/result; jangan menganggap exit runner saja membuktikan suite lulus.
7. `node --test tests/patient-duplicates.test.mjs tests/notification-realtime.test.mjs tests/partial-workflows.test.mjs`; `vendor/bin/pint --dirty --format agent`; `git diff --check`.
8. `fixes/report.mjs` memverifikasi hasil final dan menghasilkan laporan/status. Ia menolak membuat kesimpulan hijau jika retest final DFU tidak PASS, suite backend tidak hijau, atau ada FAIL/BLOCKED aktif. Nama file backend run ini tetap dicantumkan secara eksplisit untuk traceability.

Variabel environment di atas ditulis dengan sintaks PowerShell `$env:NAMA = 'nilai'`. Gunakan path lengkap dari root repository, misalnya `node qa-audit/deep-functional-ux/fixes/browser-ux.mjs`. Jalankan satu kali per direktori bukti atau gunakan label/ID unik; fixture email dan ID skenario bukan runner idempotent.

Setelah selesai, hentikan hanya server PHP/Node yang dimiliki runtime ini. Sanitasi env privat ke allowlist nilai disposable, hapus mail dummy privat, dan samarkan token pada log percobaan gagal. Tidak ada token booking/credential yang dibutuhkan untuk membaca laporan.
