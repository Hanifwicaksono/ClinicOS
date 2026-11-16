# CCL Fix & Regression Report

Run **14 November 2026, Asia/Bangkok**. Kedelapan temuan **CCL-01–CCL-08: FIXED & VERIFIED**, terbatas pada skenario yang dijelaskan di bawah. **0 temuan CCL masih OPEN.** Hasil audit sebelumnya tetap merupakan bukti historis; laporan ini menjadi pembaruan status delapan temuan, bukan penggantian isi audit lama.

## Hasil aktual

| Lapisan | PASS | FAIL / error | Bukti dan cakupan |
| --- | --- | --- | --- |
| PHP lengkap | 402/402 | 0 / 0 | [JUnit](ccl-fixes/evidence/ccl-full-final.xml); [ringkasan](ccl-fixes/evidence/ccl-full-final-summary.json);2.718 assertion. Termasuk24 test/data set CCL baru dan378 test sebelumnya. |
| JavaScript unit | 15/15 | 0 | [TAP](ccl-fixes/evidence/javascript-final.tap);10 test sebelumnya+5 test401/419/manual/network. |
| Playwright sesudah perbaikan | 28/28 | 0 | [ringkasan](ccl-fixes/evidence/browser-final-summary.json);[hasil lengkap](ccl-fixes/evidence/scenarios.json). Lima role tersimpan, owner sebagai persona Clinic Admin. |
| Chrome workflow regresi existing | 2/2 | 0 | [TAP](ccl-fixes/evidence/chrome-workflows-final.tap); clinical/booking workflow dan P1/staff-security workflow. [retest dengan output terisolasi](ccl-fixes/evidence/chrome-p1-isolated.tap). |
| Browser sebelum perbaikan | 0/9 | 9 FAIL | [case before-*](ccl-fixes/evidence/scenarios.json);semua8 bug direproduksi pada source sebelum edit;CCL-01 mempunyai2 nominal. |
| Build / format | PASS | 0 | npm run build berhasil; build terisolasi juga [tersimpan](ccl-fixes/evidence/build-after.txt). Pint --dirty --format agent lulus pada9 file PHP yang disentuh. |

Angka PHP, JavaScript, Playwright dan workflow Chrome mempunyai unit yang berbeda; tidak dijumlahkan menjadi persentase coverage produk. Playwright menyimpan51 catatan:9 baseline FAIL,28 hasil aktif PASS dan14 percobaan superseded. Hasil superseded dan alasannya tetap tersimpan; sebagian merupakan penguatan bukti screenshot/readiness, bukan kegagalan aplikasi. Tidak ada test gagal yang dihapus.

## Status dan perubahan per temuan

| Temuan | Akar masalah | Sebelum | Perubahan terkecil | Sesudah / status |
| --- | --- | --- | --- | --- |
| CCL-05 | BookingService memeriksa tanggal/hari/akhir sesi, tetapi tidak membandingkan sesi dengan jam klinik terbaru. | Booking baru di luar jam diterima dan menambah appointment/queue. | Periksa jam sesi terhadap setting klinik di BookingService sebelum penulisan pasien/appointment. Batas jam inklusif; replay UUID lama tetap mengembalikan appointment yang sudah ada. Tidak memindahkan atau membatalkan appointment lama. [Source](../../app/Services/BookingService.php) | 3 skenario browser aktif PASS; **FIXED & VERIFIED** |
| CCL-03 | Validator menerima titik; query normalisasi kolom lama tidak menghapus titik dan sebagian whitespace. Penyimpanan juga belum canonical. | Nomor bertitik tersimpan, tetapi lookup digit tidak menemukan kandidat. | PhoneNumber menyediakan satu daftar separator/normalizer. Validator dan input lookup menggunakannya; mutator Patient menormalisasi saat penyimpanan. Query legacy memakai daftar separator yang sama dengan parameter binding; tidak melakukan bulk rewrite, merge atau account linking. [Source](../../app/Rules/PhoneNumber.php) | 3 skenario browser aktif PASS; **FIXED & VERIFIED** |
| CCL-04 | Prefill pasien difilter aktif lalu null diperlakukan sebagai pasien baru. | URL pasien nonaktif membuka form pasien baru tanpa alasan. | Prefill harus resource tenant yang ada. Nonaktif diarahkan ke profil dengan alasan dan langkah Edit pasien; CTA booking disembunyikan. ID asing/tidak ada404. Reaktivasi tetap tindakan pengguna melalui fitur existing. [Source](../../app/Http/Controllers/Receptionist/AppointmentController.php) | 2 skenario browser aktif PASS; **FIXED & VERIFIED** |
| CCL-06 | List dan agregat Platform membaca status cache Subscription tanpa refresh periode efektif. | List tetap active setelah ends_at; detail/owner baru memicu refresh. | List memakai refreshStatus pada tenant halaman tersebut; dashboard merefresh cache lewat SubscriptionService pada instant yang sama sebelum agregasi. Metode domain existing menentukan expired/trial→Free/suspended/periode terjadwal. SubscriptionPeriod dan Payment tidak ditulis ulang. [Source](../../app/Http/Controllers/Platform/DashboardController.php) | 2 skenario browser aktif PASS; **FIXED & VERIFIED** |
| CCL-07 | Refetch mengganti seluruh region; focus identity dipulihkan, tetapi nilai input/Alpine dan selection tidak. | Fokus kembali tetapi draft filter menjadi kosong. | Pertahankan DOM form GET filter yang ditandai saat mengganti rows/stats region, sehingga nilai dan state Alpine tetap ada. Tangkap/pulihkan selectionStart, selectionEnd dan direction. Form aksi tulis tidak ditandai untuk dipertahankan. [Source](../../resources/js/realtime.js) | 3 skenario browser aktif PASS; **FIXED & VERIFIED** |
| CCL-02 | 401 jatuh ke pesan koneksi; manual submit tidak membedakan401/419. | Editor menyarankan periksa koneksi walau autentikasi telah berakhir. | 401/419 menjadi state expired dan memblok retry otomatis. Teks/foto pending tetap pada form, tanpa redirect/reload otomatis. Link Masuk kembali menuju portal staf /admin di tab baru; instruksi copy dan pemulihan manual. Offline tetap error yang dapat dicoba ulang;409 tetap konflik. [Source](../../resources/js/rme-autosave.js) | 10 skenario browser aktif PASS; **FIXED & VERIFIED** |
| CCL-01 | Input memakai step1000, sedangkan request backend menerima numeric0..9999999999.99. | 123456 dan123.45 diblok native stepMismatch sebelum POST. | Min0/max9999999999.99/step any mengikuti kontrak backend yang sudah ada, termasuk pada form edit yang sama. Tidak menambah kebijakan kelipatan atau presisi mata uang baru. [Source](../../resources/views/admin/services/form.blade.php) | 3 skenario browser aktif PASS; **FIXED & VERIFIED** |
| CCL-08 | Loop paket aktif tidak mempunyai empty branch. | Seluruh paket nonaktif menghasilkan halaman kosong dengan ajakan aktivasi yang tidak sesuai. | Forelse menampilkan alasan ketersediaan, panduan menghubungi administrator dan link reload. Header menyesuaikan kondisi kosong; checkout tidak ditawarkan. Katalog normal kembali tampil ketika diaktifkan. [Source](../../resources/views/onboarding/plans/index.blade.php) | 2 skenario browser aktif PASS; **FIXED & VERIFIED** |

## Bukti before / after

| Temuan | Bukti sebelum | Bukti sesudah |
| --- | --- | --- |
| CCL-05 | [jumlah/data sebelum](ccl-fixes/evidence/before-hours-proof.json); [booking diterima](ccl-fixes/evidence/screenshots/before-CCL-05.png) | [tidak ada write baru](ccl-fixes/evidence/after-hours-proof.json); [penolakan jam](ccl-fixes/evidence/screenshots/after-CCL-05.png) |
| CCL-03 | [kandidat tidak ditemukan](ccl-fixes/evidence/screenshots/before-CCL-03.png) | [kandidat ditemukan](ccl-fixes/evidence/screenshots/after-r2-CCL-03.png); related-CCL-03-LEGACY dan PUBLIC-STORAGE |
| CCL-04 | [form pasien baru](ccl-fixes/evidence/screenshots/before-CCL-04.png) | [profil dan pemulihan](ccl-fixes/evidence/screenshots/after-CCL-04.png); related-r2-CCL-04-REACTIVATE-STALE |
| CCL-06 | [active sesudah expiry](ccl-fixes/evidence/screenshots/before-CCL-06.png) | [expired](ccl-fixes/evidence/screenshots/after-CCL-06.png); related-CCL-06-EXACT-END-DASHBOARD |
| CCL-07 | [input hilang](ccl-fixes/evidence/before-filter-proof.json) | [nilai/selection/direction tetap](ccl-fixes/evidence/after-filter-proof.json); Admin dan Doctor diuji pada related-CCL-07-* |
| CCL-02 | [pesan koneksi401](ccl-fixes/evidence/screenshots/before-CCL-02.png) | [expired375px dengan teks utuh](ccl-fixes/evidence/screenshots/CCL-02-pending-ready-419-manual.png); related-CCL-02-RECOVERY membuktikan login staf dan copy/paste/save |
| CCL-01 | [step mismatch](ccl-fixes/evidence/screenshots/before-CCL-01-123456.png) | [form375px](ccl-fixes/evidence/screenshots/boundary-final-CCL-01-ready-width-375.png); after-CCL-01-* memeriksa create/store/edit |
| CCL-08 | [halaman kosong](ccl-fixes/evidence/screenshots/before-CCL-08.png) | [empty state375px](ccl-fixes/evidence/screenshots/boundary-final-CCL-08-ready-width-375.png); reload keyboard dan pemulihan katalog diuji |

Semua screenshot/identitas/isi klinis berasal dari fixture dummy. Detail aksi dapat diulang dari [runner reproduksi](../../qa-audit/deep-functional-ux/ccl-fixes/browser.mjs), [regresi terkait](../../qa-audit/deep-functional-ux/ccl-fixes/related-browser.mjs) dan [batas UI/pending file](../../qa-audit/deep-functional-ux/ccl-fixes/ui-boundaries.mjs).

## Aturan bisnis dan integritas yang dipertahankan

- **Jam klinik:** pilihan yang diterapkan adalah mempertahankan perubahan setting, kemudian menolak booking baru pada sesi yang tidak lagi sesuai. Alternatif menolak perubahan setting dapat dipilih produk kemudian; tidak melakukan reschedule/cancel massal. Snapshot appointment/queue lama dan replay UUID lama tetap utuh.
- **Telepon:** normalisasi hanya karakter pemisah yang sudah diterima validator. Tanda+ dan alias62↔0 existing dipertahankan. Nomor bersama keluarga tetap diperbolehkan; tidak menambah OTP, aturan negara, deduplikasi identitas, account linking, atau rewrite massal nomor lama.
- **Pasien nonaktif:** tidak otomatis diaktifkan dan tidak diganti dengan identitas baru. Staff menggunakan Edit pasien yang sudah diotorisasi; resource asing/tidak ada tidak dibocorkan melalui fallback.
- **Subscription:** refresh hanya menggunakan aturan domain existing dan cache Subscription. Histori SubscriptionPeriod/Payment, status suspended, trial→Free, future period dan aktivasi saat starts_at tetap mengikuti SubscriptionService. Tidak menambahkan grant, pembayaran atau refund.
- **Tarif:** frontend mengikuti numeric/rentang existing; kebijakan pembulatan atau mata uang baru tidak diciptakan.
- **Editor:** tidak ada redirect/reload otomatis pada401/419, penyimpanan isi medis ke localStorage, atau bypass version/authorization. Teks dan file pending bertahan saat gagal. Pemulihan yang diuji adalah manual: copy teks, login staf di tab baru, reload dan paste/save. Foto perlu dipilih ulang jika pengguna sengaja me-reload halaman; tidak ada klaim file input browser bertahan melewati reload.

## Verifikasi terkait dan perlindungan DFU

[24 test CCL](../../tests/Feature/CoverageClosureRegressionTest.php) meliputi jam inklusif/di luar batas dan replay/history,4 format telepon serta legacy/tenant, inactive/foreign/stale patient,5 state subscription termasuk future/suspended, create/edit nominal, empty catalog,401/419 dan kontrak render filter. [JavaScript](../../tests/partial-workflows.test.mjs) membuktikan401/419 pada autosave/manual, draft tetap unsaved, form tidak inert setelah gagal, tanpa navigasi, serta retry jaringan.

Browser memeriksa penolakan jam untuk Patient/Admin/Receptionist, nomor canonical dari public booking dan kandidat legacy tanpa rewrite, reaktivasi dan stale patient form, expiry tepat ends_at+dashboard counts, input/filter/cursor pada Receptionist/Admin/Doctor, sesi401/419 autosave/manual dengan foto pending, login staf dan pemulihan draft, serta tariff/empty state pada375/768/1024/1440px dan keyboard.

Suite PHP lengkap menjalankan semua test sebelumnya, termasuk DeepFunctionalRegressionTest, booking/cancellation/queue, subscription/grant/payment, tenant/policy, alergi/odontogram, finalisasi/revisi dan staff lifecycle. DFU-01–DFU-12 dipertahankan; kode pembatalan visit, grant UUID, pembekuan sesi terpakai dan policy medis tidak dibatalkan atau dilemahkan. Browser65 dari laporan DFU lama tidak diklaim dijalankan ulang sebagai suite yang sama: bukti run sekarang adalah28 Playwright+2 workflow Chrome di atas.

##419 dan lingkungan pengujian

Laravel13 menerima Fetch Metadata same-origin yang valid. Logout tab lain menghasilkan401; relogin dapat membuat request stale-token same-origin diterima200. Menghapus header hanya pada intercept Chrome tidak cukup karena browser memasoknya kembali. Untuk419, **router QA loopback saja** meniru proxy/klien tanpa Fetch Metadata ketika header test eksplisit diberikan; token form lama berasal dari logout/login UI. Laravel kemudian benar-benar menghasilkan419 sebelum controller. Tidak ada response stub, perubahan middleware produksi, atau penonaktifan CSRF produksi. [Konfigurasi kompatibilitas](ccl-fixes/evidence/csrf-compatibility-environment.json).

Database browser adalah SQLite file baru di temporary runtime; PHP menggunakan SQLite :memory:. Migration/seeding hanya pada database disposable. Clock aplikasi dikendalikan14Nov2026 09:00WIB, digeser untuk ends_at lalu dipulihkan; clock OS tidak diubah. HTTP/storage/cache/session/build QA terpisah, worker dan Reverb nyata untuk refetch. DOKU/Google eksternal dinonaktifkan. Runtime HTTP/Reverb/worker telah dihentikan dan env sementara dibersihkan dari environment pengguna yang terwarisi. [Retensi](ccl-fixes/evidence/retention.json).

## Catatan percobaan yang tetap dipertahankan

- Sebelum perbaikan, backend terfokus23 test mencatat1 PASS/22 FAIL. Snapshot baru sempat membandingkan urutan atribut model sebelum insert dengan pembacaan DB; pengambilan snapshot disamakan ke row persisted tanpa melemahkan assertion nilai/histori. [Baseline](ccl-fixes/evidence/ccl-before-summary.json); [17 PASS/6 FAIL](ccl-fixes/evidence/ccl-after-focused-summary.json); [23 PASS setelah koreksi snapshot](ccl-fixes/evidence/ccl-after-focused-r2-summary.json).
- Full run pertama401 test:400 PASS dan1 error karena fixture all-day session berada di luar jam kliniknya. [Fixture](../../tests/Feature/SubscriptionEnforcementTest.php) kini eksplisit00:00–23:59; test pelayanan lama setelah quota tetap ada dan lulus. [Percobaan lengkap pertama](ccl-fixes/evidence/ccl-full-summary.json).
- Stub Form unit test diberi default inert=false sebagaimana HTMLElement. Assertion P1 lama diselaraskan dengan pesan pembekuan sesi DFU yang sudah ada; semua pemeriksaan doctor/appointment/queue tetap dipertahankan. Runner P1 menerima direktori output terpisah untuk run berikutnya. [Catatan](ccl-fixes/evidence/attempt-notes.json).
- Retest Playwright memperbaiki sinkronisasi candidate review, tab background, redirect recovery pasien, asumsi419, dan kesiapan screenshot/atribut aria-hidden desktop. Seluruh percobaan dan rantai superseded_by tetap ada pada [scenarios.json](ccl-fixes/evidence/scenarios.json). Rahasia/token pada log PHP disamarkan tanpa menghapus test atau failure/error. [Validasi JUnit](ccl-fixes/evidence/junit-validation.json).

## Masalah masih terbuka dan batas hasil

**Dalam lingkup delapan CCL: tidak ada yang masih OPEN.** Gap provider DOKU/Google, studi partisipan manusia, kontrol/history UI lain dan permission-revoke socket dari [Coverage Closure](REMAINING_GAPS.md) tidak diperluas dalam tugas ini. Tidak mengklaim legacy browser sungguhan telah diuji hanya berdasarkan mode proxy QA.

SQLite dan server PHP tunggal tidak membuktikan locking/concurrency MySQL. Tidak melakukan load/stress testing, optimasi performa atau penilaian production-readiness. Tidak ada perubahan database pengguna, penghapusan histori klinis/pembayaran, commit, push atau deploy. Tidak ada migration baru untuk delapan perbaikan ini.

## Integritas artefak

[Pemeriksaan source/.env](ccl-fixes/evidence/source-integrity.json) membatasi perubahan produksi ke14 file terkait CCL; file produksi lain pada baseline377 tetap sama. Tiga file test existing diperbarui dengan perubahan terarah dan satu class test baru ditambahkan. Laporan audit historis dipertahankan utuh; hasil ini tersimpan sebagai laporan tambahan. [Status8 temuan](ccl-fixes/evidence/finding-statuses.json) · [Browser](ccl-fixes/evidence/browser-final-summary.json) · [PHP](ccl-fixes/evidence/ccl-full-final-summary.json) · [JavaScript](ccl-fixes/evidence/javascript-final-summary.json) · [Chrome](ccl-fixes/evidence/chrome-workflows-final-summary.json).

Untuk menjalankan ulang, gunakan runtime/database baru: [bootstrap audit](../../qa-audit/deep-functional-ux/scripts/runtime.mjs) dengan CLINICOS_AUDIT_EVIDENCE menunjuk evidence terpisah, lalu [setup CCL](../../qa-audit/deep-functional-ux/ccl-fixes/setup.mjs) sekali. Jalankan runner berurutan; jangan menjalankan dua penulis scenarios.json/pengubah clock bersamaan. Jangan menyalin env QA ke.env aplikasi.
