# Perbaikan dan pengujian ulang P2

11 November 2026, Asia/Bangkok. Acuan audit awal: `b82237d932478df9ed02240cb698acff9b4ec669`; checkpoint P1 dipertahankan. QA-05 sampai QA-14 dan temuan baru QA-15 sekarang **FIXED pada skenario yang diuji**. Kebijakan revokasi sesi dan verifikasi ulang email staf juga sudah diterapkan. Tidak ada commit, push, deploy, migrasi data pengguna, atau pembayaran nyata.

## Hasil per masalah

| ID | Reproduksi sebelum perbaikan dan akar masalah | Perbaikan aktual | Regression/bukti |
| --- | --- | --- | --- |
| QA-13 / P2 | Admin dengan hanya receptionist.create dapat menyimpan Doctor. UserPolicy menerima salah satu permission create tanpa memeriksa role target. | Policy menerima role target; StoreStaffRequest mengirim role itu ke gate. Akses form umum tetap memakai canAny, tetapi penyimpanan wajib memiliki permission sesuai target. | Audit HTTP lama kini PASS; matrix policy Doctor/Receptionist dan tenant-null; browser submit Doctor ditolak 403 tanpa akun baru. |
| QA-14 / P2 | Self-delete dokter berbooking mencoba cascade user/Doctor dan gagal pada FK restrict setelah logout. | Periksa kepemilikan P1 dan referensi Doctor/appointment/queue/visit/RME/revision sebelum logout. User dan Doctor dikunci selama pemeriksaan. Penolakan menjelaskan penggunaan deaktivasi. | Audit lama PASS, regression role Doctor dicabut tetap melindungi histori, Chrome memeriksa error form, sesi dan visit tetap utuh. Self-delete staf tanpa referensi tetap PASS. |
| QA-05 / P2 | Request koreksi tanpa record_version diterima; allergy_version juga dapat dihilangkan. Validasi dan service melewati pemeriksaan ketika field tidak ada. | Kedua versi wajib pada draft, complete dan correct. Service tidak lagi melewati pemeriksaan ketika versi absen. Konflik versi tetap 409; field hilang 422. | Enam kasus omission pada tiga endpoint, stale record/allergy existing, audit lama PASS. Browser autosave, retry, finalize/correction dan histori tetap PASS. |
| QA-06 / P2 | Array diagnoses/treatments null lolos validasi lalu menimbulkan TypeError pada array_filter. | Null dinormalisasi menjadi array kosong; field yang tidak dikirim tidak mengubah koleksi tersebut. | Audit null/scalar PASS; regression persist -> omit mempertahankan baris -> null mengosongkan diagnosis/tindakan PASS. |
| QA-07 / P2 | Owner tanpa klinik membuka add-on dan menghasilkan 500 dari service bertipe Clinic. | Middleware onboarding.complete pada GET/POST add-on. Route pembayaran pending tidak diberi gate ini sehingga checkout onboarding tetap dapat dibaca. | Audit GET PASS; regression GET dan POST redirect ke onboarding tanpa membuat payment; seluruh subscription/DOKU fake suite PASS. |
| QA-08 / P2 | Upload draft lalu audit storage gagal: DB rollback, file baru tertinggal. | Daftarkan delete hanya untuk path upload baru lewat DB::afterRollBack, tersedia pada Laravel terpasang. Terpasang pada shared syncRecord untuk draft/finalize/correct; retry/deadlock MySQL belum dieksekusi. Guard retensi foto final P1 tidak diubah. | Audit lama PASS; regression gagal setelah menandai foto lama untuk dihapus membuktikan foto lama tetap ada dan hanya upload baru dibersihkan. Chrome foto historis P1 tetap 200. |
| QA-09 / P2 | Pada source lama Escape tidak mengubah drawer terbuka. Tidak ada handler, focus return atau closed inert state. | Handler Escape/close, aria-expanded/controls, label dialog mobile, inert ketika tertutup, dan trap Tab/Shift+Tab melalui plugin focus bawaan Livewire; resize desktop melepaskan trap. | Chrome lima role: buka, Tab/Shift+Tab wrap, Escape, fokus kembali, inert tertutup, desktop aktif kembali. Event DOM keyboard; keterbatasan native dijelaskan di bawah. |
| QA-10 / P2 | Tujuh kontrol identitas pasien baru tidak memiliki label terasosiasi. | for/id pada nama, NIK, lahir, gender, telepon, email dan alamat; memakai form yang sama. | Reproduksi browser sebelum fix gagal pada kedua role; Clinic Admin dan Receptionist kini mempunyai input.labels untuk ketujuh kontrol. |
| QA-11 / P2 | Composer audit mereproduksi 8 advisory pada 5 paket. | Update kompatibel dengan --patch-only dan --minimal-changes, tanpa major/minor upgrade. | Composer audit 0 advisory, npm audit 0; suite lengkap dan HEAD/auth boundary lima role PASS. |
| QA-12 / P3 | README menyebut PHP ^8.3, hanya fondasi, SQLite sebagai konfigurasi default, serta seeder tidak mereset akun. | README sesuai PHP ^8.5, cakupan saat ini, default MySQL testing, override SQLite/GD, proses shell, perilaku seeder dan browser opt-in. Tidak memuat password demo. | Review manifest/config/seeder; perintah pengujian yang didokumentasikan dijalankan. Tidak membuat test string README yang hanya menyalin isi dokumen. |
| QA-15 / P2, baru | Fault injection pengiriman verifikasi saat create menghasilkan 500 meskipun user/role telah commit. Client dapat menyangka akun belum dibuat. | Kegagalan event notifikasi setelah commit dilaporkan melalui handler exception; akun tetap unverified dan respons menjelaskan kebutuhan verifikasi/resend tanpa mengklaim delivery berhasil. | Test baru direproduksi FAIL/500 sebelum fix, kini PASS/redirect; role/hash/unverified dan exception reported diperiksa. |

## Sesi dan verifikasi staf

AuthenticateSession dipasang pada web middleware. SessionGuard Laravel terpasang menyimpan hash password saat login; middleware membandingkannya pada request berikutnya. Update password admin mengganti hash dan remember token di transaksi yang sama. Sesi/cookie lama ditolak, login password baru bekerja. Ini mengikuti mekanisme [autentikasi sesi Laravel 13](https://laravel.com/framework/docs/13.x/authentication#invalidating-sessions-on-other-devices), dengan hasil integrasi dibuktikan oleh test aplikasi.

Perubahan email mengosongkan verified timestamp dan menjadwalkan VerifyEmail setelah commit. Tautan lama ditolak karena hash email tidak cocok; tautan baru memulihkan akses. Jika transport gagal, error dilaporkan, perubahan email tidak diklaim rollback, dan UI menyediakan petunjuk resend. Transaksi gagal tidak mengubah password/email atau mengirim verifikasi. Lihat [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md).

Deaktivasi tetap mekanisme pencabutan akses tanpa menghapus histori. Tidak menambahkan UI permission editor, invitation flow, impersonation, atau hard delete admin.

## Dependency

| Paket | Sebelum | Sesudah |
| --- | --- | --- |
| symfony/http-foundation | 7.4.20 | 7.4.23 |
| symfony/http-kernel | 7.4.20 | 7.4.23 |
| symfony/mime | 7.4.19 | 7.4.22 |
| symfony/routing | 7.4.20 | 7.4.23 |
| symfony/yaml | 7.4.20 | 7.4.23 |

Hanya lima paket tersebut berubah di composer.lock; composer.json dan major framework tetap. Upstream mencatat perbaikan [HTTP Foundation pada 7.4.23](https://symfony.com/cve-2026-118514) dan [MIME pada 7.4.22](https://symfony.com/cve-2026-45070). Seluruh advisory terpasang hilang dari feed Composer setelah update; ini bukan klaim telah mengeksploitasi atau melakukan pentest semua jalur aplikasi.

## Hasil run terakhir

| Pemeriksaan | Hasil |
| --- | --- |
| PHP seluruh aplikasi + audit | **363 PASS / 0 FAIL / 0 ERROR**, 2.438 assertion; termasuk 22 regression audit yang dahulu merah |
| JavaScript unit/state | **7 PASS / 0 FAIL** |
| Chrome E2E | **3 PASS / 0 FAIL / 0 SKIP**; role/menu/keyboard DOM, workflow klinis, P1 dan keamanan staf |
| Total automated unik | **373 PASS / 0 FAIL**; run sebelum perbaikan tidak dihitung lagi |
| Build Vite / compile Blade | PASS; build selesai sebelum browser dijalankan |
| PHP lint / Pint / whitespace | PASS; 30 file pada lint, Pint dirty dan explicit Volt/audit, git diff --check |
| Composer / npm audit | 0 advisory / 0 vulnerability |

Bukti ada di [evidence/p2-retest/](evidence/p2-retest/): [PHP JSON](evidence/p2-retest/php-all.txt), [JUnit](evidence/p2-retest/php-all.xml), [Node/browser](evidence/p2-retest/node-all.txt), [langkah role/UX](evidence/p2-retest/browser-audit-steps.json), [langkah P1/staf](evidence/p2-retest/p1-browser-steps.json), composer-before/after.json, npm-audit.json, build.txt, blade.txt, pint.txt dan php-lint.txt. File staff-before, session-email-before, clinical-before, browser-before serta staff-create-mail-before menyimpan reproduksi terarah.

Skill testing-best-practices beserta rule yang sesuai dibaca dari vendor/laravel/boost/.ai/laravel/skill/testing-best-practices; skill Volt lokal digunakan untuk komponen terkait.

Test existing dipertahankan. Fixture request positif diperbarui agar mengirim versi eksplisit; request negatif yang menghilangkan versi tidak diberi backfill otomatis. Helper actingAs menyamai hash sesi login Laravel; test revokasi memasukkan hash lama secara eksplisit dan browser memakai cookie sungguhan.

## Koreksi harness dan batas klaim

- Input.dispatchKeyEvent tidak menghasilkan event pada halaman dalam Chrome headless lokal. Probe yang tidak menerima keydown tidak dipakai sebagai bukti kegagalan aplikasi. Source navigasi lama direproduksi ulang dengan KeyboardEvent DOM yang sama: FAIL sebelum fix, PASS sesudah. Bukti: qa09-before-keyboard-steps.json dan browser-audit-steps.json. Keyboard native/perangkat fisik dan screen reader **NOT TESTED**; tidak disamakan dengan keyboard DOM.
- Satu run browser sempat 500 karena Vite manifest sedang diganti oleh build bersamaan. Log menunjukkan ViteManifestNotFoundException. Run tersebut tidak dianggap bug tenant; build dan browser kemudian dipisah. Seluruh browser kini juga memakai storage/compiled-view sementara terpisah.
- Statistik route 699 request dan 107 route aplikasi berasal dari audit awal; recorder tidak dijalankan ulang. Tidak diklaim 100% coverage.
- MySQL localhost:3306 tidak menerima koneksi pada probe terbaru: concurrency/locking dua proses **BLOCKED**. SQLite membuktikan perilaku transaksi yang diuji, bukan lock MySQL.
- SMTP/inbox nyata, Reverb/socket aktif, DOKU nyata, cron produksi dan staging **BLOCKED/NOT TESTED** pada sesi lokal. Backup/restore, load/soak dan seluruh kombinasi permission custom **NOT TESTED**. File yang hilang sebelum fix foto P1 memerlukan backup untuk pemulihan.

## Keputusan tahap berikut

Seluruh 15 temuan terkonfirmasi sekarang FIXED; **0 temuan terkonfirmasi OPEN**. Aplikasi layak melanjutkan pengujian production-readiness pada staging untuk cakupan yang ada. Persetujuan produksi masih memerlukan pembuktian MySQL concurrency, delivery/provider nyata, revokasi koneksi aktif, storage/backup restore, observability dan load/UAT. Feature gap pada MISSING_FEATURES tidak dianggap selesai oleh hasil test ini.
