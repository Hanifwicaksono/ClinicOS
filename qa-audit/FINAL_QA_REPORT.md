# Final QA report - hasil setelah P2

**Laporan terbaru: [Production readiness scorecard](production-readiness/PRODUCTION_READINESS_SCORECARD.md) — NO-GO untuk produksi multi-user.** Run MySQL terbaru: PHP 361 PASS / 1 FAIL / 1 ERROR; JavaScript 7 PASS; Playwright 13 PASS / 2 FAIL. Concurrency 9/9 dan recovery 7/7 lulus. Tiga temuan baru OPEN tercatat pada [NEW_FINDINGS.md](production-readiness/NEW_FINDINGS.md). Bagian di bawah mempertahankan hasil historis P2 dan bukan hasil MySQL/readiness terbaru.

11 November 2026, Asia/Bangkok. **QA-01 sampai QA-15 FIXED pada skenario yang diuji; 0 temuan terkonfirmasi OPEN.** QA-15 adalah bug baru pada kegagalan notifikasi create staf. Sesi lama setelah reset admin dan verifikasi ulang email juga sudah ditangani. Perbaikan P1 dipertahankan dan diuji kembali.

## Hasil pengujian terakhir

| Pemeriksaan | PASS | FAIL / ERROR / SKIP | Bukti |
| --- | ---: | --- | --- |
| PHP aplikasi + seluruh audit | **363** | **0 / 0 / 0**, 2.438 assertion | [JSON](evidence/p2-retest/php-all.txt), [JUnit](evidence/p2-retest/php-all.xml) |
| JavaScript unit/state | **7** | **0 / 0 / 0** | [Node](evidence/p2-retest/node-all.txt) |
| Chrome E2E | **3** | **0 / 0 / 0** | [Node](evidence/p2-retest/node-all.txt), [role/UX](evidence/p2-retest/browser-audit-steps.json), [P1/staf](evidence/p2-retest/p1-browser-steps.json) |
| Total automated unik | **373** | **0 FAIL** | Run awal dan retest berulang tidak dijumlahkan lagi |

Suite PHP mencakup 341 kasus aplikasi dan 22 kasus audit eksplisit. Seluruh regression audit yang sebelumnya gagal sudah dijalankan kembali setelah perbaikan. Test browser opt-in benar-benar diaktifkan, bukan dilewati. Angka tersebut bukan bukti 100% route/scenario coverage.

Build Vite, kompilasi Blade ke temporary directory, lint 30 file PHP, Pint dirty/explicit Volt/audit, dan git diff --check PASS. Composer audit turun dari 8 advisory menjadi 0; npm audit 0. Hanya lima paket Symfony berubah melalui patch pada seri 7.4, tanpa major/minor upgrade atau perubahan composer.json.

## Perubahan dan integrasi yang diverifikasi

| Area | Hasil |
| --- | --- |
| QA-13 permission staf | Permission create sesuai role target; matrix policy dan browser menolak Doctor create tanpa doctor.create. |
| QA-14 retensi akun | Akun dengan kepemilikan/referensi klinis ditolak sebelum logout/delete; akun dan histori tetap utuh. Deaktivasi tetap mencabut akses. |
| Sesi staf | Setelah reset admin, cookie sesi lama dan remember-only ditolak; password baru bekerja. Transaksi gagal mempertahankan sesi/password lama. |
| Email staf | Alamat baru wajib diverifikasi; tautan lama tidak berlaku, tautan baru/login alamat baru bekerja. Notifikasi setelah commit, failure dilaporkan dan resend dijelaskan. |
| QA-05/06/08 RME | Versi wajib, stale conflict tetap 409, null/omission konsisten, rollback membersihkan upload baru dan menjaga file/histori lama. |
| QA-07 add-on | Owner tanpa klinik diarahkan ke onboarding; invoice pending tidak kehilangan akses. |
| QA-09/10 UX | Lima role lulus focus trap/Tab/Shift+Tab/Escape/focus return melalui DOM keyboard Chrome; kedua role pendaftaran memiliki label pada tujuh kontrol. |
| QA-11 dependency | Lima patch terpasang, audit 0, HEAD/auth boundary lima role dan suite lengkap PASS. |
| QA-12 dokumentasi | README sesuai manifest/config/seeder dan perintah testing terisolasi. |
| QA-15 baru | Kegagalan email create staf tidak lagi menghasilkan 500 setelah commit atau mengklaim delivery selesai. |
| QA-01 sampai QA-04 | Booking publik, foto histori, owner guard dan jadwal terpakai tetap PASS pada PHP/Chrome. |

Detail akar masalah, reproduksi sebelum fix dan bukti per masalah: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md). Siklus akun: [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md). Status individu: [BUG_REPORT.md](BUG_REPORT.md).

## Keputusan production-readiness

**Layak melanjutkan pengujian production-readiness pada staging untuk cakupan yang tersedia.** Hasil lokal ini belum menjadi persetujuan deploy produksi.

| Gate | Status aktual |
| --- | --- |
| Bug terkonfirmasi QA-01 sampai QA-15 | FIXED dalam scope regression |
| MySQL lock/concurrency dua proses | BLOCKED: probe localhost:3306 terbaru tidak menerima koneksi; [bukti](evidence/p2-retest/mysql-probe.json) |
| SMTP/inbox, Reverb/socket aktif, DOKU nyata | BLOCKED/NOT TESTED; yang dijalankan memakai fake, mailer array atau transport null |
| Staging, cron/worker deployment dan observability | NOT TESTED pada environment target |
| Backup/restore, storage failure deployment, load/soak/UAT | NOT TESTED |
| Keyboard native dan screen reader/perangkat fisik | NOT TESTED; Chrome DOM keyboard diuji, CDP native tidak mengirim event pada sesi ini |
| Feature gap discovery, kasir, multi-cabang dan invitation UI | Masih backlog; hasil bugfix tidak mengimplementasikannya |

Risiko regresi utama berada pada autentikasi sesi semua role, kewajiban versi pada setiap caller RME, FK/retensi akun dan paket HTTP/routing/MIME. Suite dan browser menutup skenario terpilih di atas; deployment tetap perlu memverifikasi provider nyata, concurrency, revokasi koneksi yang sudah aktif dan recovery. Foto yang telah hilang sebelum fix P1 tidak dipulihkan tanpa backup.

## Menjalankan kembali

PowerShell proses terpisah; jangan mengubah .env atau memakai database aplikasi:

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP ('clinicos-audit-' + [guid]::NewGuid().ToString('N') + '.php')
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/bin/phpunit tests qa-audit/tests --no-progress --do-not-cache-result
```

```powershell
npm run build
$env:CLINICOS_BROWSER_TEST = '1'
node --test tests/notification-realtime.test.mjs tests/partial-workflows.test.mjs tests/browser-partial-workflows.test.mjs tests/browser-p1-regressions.test.mjs qa-audit/tests/browser-audit.test.mjs
composer audit
npm audit
```

Build harus selesai sebelum browser, karena Vite mengganti manifest selama build. Seluruh browser memakai database dan storage sementara terpisah. Tidak ada migrasi/seed pada database pengguna, commit, push, deploy atau pembayaran nyata. Bukti baseline audit dan P1 tetap disimpan; angka coverage route awal tidak diklaim sebagai hasil pengukuran ulang.
