# F1 Implementation Report — PG-18 dan PG-17

Eksekusi: **15 November 2026, Asia/Bangkok (UTC+7)**. Scope hanya F1A/PG-18 dan F1B/PG-17. Dasar: [spesifikasi](implementation-plan/FEATURE_SPECIFICATIONS.md), [fase](implementation-plan/IMPLEMENTATION_PHASES.md), [database impact](implementation-plan/DATABASE_IMPACT.md). Tidak memulai PG lain atau PR-01/S1/S2 dan tidak menganggap D-01–D-20 disetujui.

## Hasil final

| Paket | Sebelum | Sesudah | Status |
| --- | --- | --- | --- |
| PG-18 | @js literal pada custom-button attribute, Alpine SyntaxError, submitting=true tetapi tombol enabled | HTML binding valid, feedback Memproses dan disabled selama POST, validasi/retry/Back, empty catalog dan idempotency tetap | **FIXED & VERIFIED** |
| PG-17 | 10 kandidat fixture, hanya8 terlihat dan tidak ada pagination | Kandidat9/10 di browser, kandidat17 pada backend; input tetap; cursor signed tenant/criteria/snapshot/expiry; review token existing tetap divalidasi | **FIXED & VERIFIED** |

Status tersebut didukung backend **dan** browser, bukan hanya render/HTTP200. [Ringkasan machine-readable](f1-implementation/evidence/final-summary.json).

| Lapisan / run final | PASS | FAIL / error | Bukti dan batas |
| --- | --- | --- | --- |
| PHP full termasuk QA historis | 424 | 0 / 0 | [JUnit](f1-implementation/evidence/php-full-with-qa-final.xml); 2.877 assertions. Seluruh402 case historis tetap ada +22 baru; [perbandingan nama case](f1-implementation/evidence/php-historical-coverage.json). |
| PHP F1 terfokus | 22 | 0 / 0 | [JUnit](f1-implementation/evidence/f1-focused-r2.xml); 159 assertions; sudah termasuk full424. |
| PHP regression terkait | 57 | 0 / 0 | [JUnit](f1-implementation/evidence/related-regression.xml); 434 assertions; subset full suite, tidak dijumlahkan lagi. |
| JavaScript unit | 20 | 0 | [TAP final](f1-implementation/evidence/javascript-chrome-full-final-r2.tap); termasuk8 patient-duplicate tests,5 baru. |
| Chrome workflow existing | 2 | 0 | TAP yang sama: clinical/booking dan P1/staff-security. Assertion lama tetap, lihat penyesuaian harness di bawah. |
| Playwright F1 | 12 | 0 | [Hasil final](f1-implementation/evidence/final/scenarios.json):4 PG-18,8 PG-17; 11 pada fresh-fixture full run +1 supplementary walk-in pada kode aplikasi yang sama. |
| Build / Pint | PASS | 0 | [Build sandbox](f1-implementation/evidence/final/build-startup.txt), [npm run build workspace](f1-implementation/evidence/build-workspace.txt); [Pint scoped PHP](f1-implementation/evidence/pint-final.json). Dependency tidak berubah. |


Unit test/data set, grouped browser scenario dan workflow Chrome berbeda ukuran; angka ini bukan persentase coverage produk. Perintah full PHP final memakai `tests qa-audit/tests`: default phpunit.xml tidak mencakup dua berkas QA historis. Run default sebelumnya juga lulus402 case termasuk22 baru; full424 menambahkan kembali22 case QA eksternal, bukan menghapus test.

## File yang diubah

| File | Perubahan |
| --- | --- |
| [resources/views/platform/tenants/show.blade.php](../../resources/views/platform/tenants/show.blade.php) | PG-18: boolean katalog pada native form Alpine, binding tombol valid, aria-busy/feedback pending, guard submit ulang dan reset pageshow untuk Back. |
| [app/Services/PatientDuplicateService.php](../../app/Services/PatientDuplicateService.php) | PG-17: shared matchingQuery mempertahankan matcher/normalisasi; candidatePage dengan signed encrypted cursor dan snapshot/version satu ordered read. |
| [app/Http/Controllers/Receptionist/PatientController.php](../../app/Http/Controllers/Receptionist/PatientController.php) | Endpoint POST existing memvalidasi cursor (maks4096), memetakan hasil halaman dan next_cursor, tetap no-store dan tenant dari akun. |
| [resources/js/patient-duplicates.js](../../resources/js/patient-duplicates.js) | Append halaman, reset acknowledgement, invalidasi response langsung saat input berubah, feedback cursor stale/invalid dan fresh retry. |
| [resources/views/components/patient-duplicate-review.blade.php](../../resources/views/components/patient-duplicate-review.blade.php) | Jumlah/has-more, tombol pagination type=button, profil tab baru dengan label eksplisit, input form asal tetap. |
| [tests/Feature/F1ImplementationTest.php](../../tests/Feature/F1ImplementationTest.php) | Baru: 22 test/data set backend grant, cursor, boundaries, role/tenant, review token dan no silent creation. |
| [tests/patient-duplicates.test.mjs](../../tests/patient-duplicates.test.mjs) | Lima test JS baru untuk paging, input/response stale, invalid cursor dan retry; tiga test lama tetap. |
| [public/build/manifest.json](../../public/build/manifest.json) | Generated manifest/assets diperbarui lewat npm run build normal agar workspace menyajikan JS pagination terbaru; tidak menyalin konfigurasi VITE dummy ke build pengguna. |


Harness/bukti baru berada di [f1-implementation](f1-implementation/): runtime clone, runner suite, reproduksi before, Playwright, runner Chrome legacy terisolasi, router test dan pembuat laporan. Tidak ada migration, dependency, route baru atau perubahan **SubscriptionService**, BookingService, policy klinis, scheduler, atau konfigurasi permission. Perubahan pengguna yang sudah ada dipertahankan; diff Git keseluruhan mencakup pekerjaan sebelumnya dan bukan ukuran perubahan F1 ini.

## Akar masalah dan perbaikan

### PG-18

Directive @js pada atribut x-clinic-button tersimpan sebagai teks atribut, sehingga Alpine gagal mengompilasi expression disabled. Boolean katalog sekarang dihitung pada native form yang dikompilasi Blade, lalu tombol membaca submitting/catalogEmpty. Disabled server untuk empty catalog tetap ada; form mempunyai pending feedback, aria-busy, guard repeat submit dan pageshow reset untuk halaman yang dipulihkan dari Back.

Alur tetap native POST/redirect/error. Tidak menambahkan AJAX grant atau mengubah aturan subscription. Validation failure merender ulang input/UUID existing dan tombol bisa dicoba lagi; browser memeriksa UUID sebelum/sesudah secara in-memory, tidak menyalinnya ke laporan. Double-click nyata menghasilkan satu POST, satu grant terjadwal dan satu audit; period/invoice/payment lama tetap. Replay UUID dan payload mismatch diuji backend melalui service/controller existing. Simulasi500 hanya menguji recovery Back UI; bukan klaim provider/payment-failure E2E.

### PG-17

Limit8 yang semula hard-coded tetap menjadi ukuran halaman default. Predicate matching NIK, nama+tanggal lahir dan normalisasi telepon diekstrak sekali di service; candidates() serta assertReviewed() mempertahankan kontrak first-page review token existing. API paging memakai predicate yang sama dan mengembalikan kandidat8 per halaman serta next_cursor, tanpa mengirim NIK/telepon melalui query URL.

Cursor dienkripsi dan diautentikasi Laravel Crypt, mengikat clinic_id server-side melalui scope HMAC, input kriteria, anchor ID, snapshot kandidat serta expiry teknis15 menit. Scope/shape/expiry/version/anchor yang salah menghasilkan422 sebelum kandidat dikembalikan. Snapshot berasal satu ordered streamed read; perubahan/add/delete pada hasil di halaman berikutnya juga membatalkan cursor. Hanya first page dan maksimal9 model page yang ditahan untuk hasil/lookahead; setiap halaman tetap membaca matching snapshot keseluruhan untuk validasi, bukan klaim optimasi volume besar.

UI append hasil, menampilkan has-more, membuka profil di tab baru dan tidak menulis ulang field pasien/jadwal. Input event langsung membatalkan response authority sebelum debounce, sehingga response lama tidak memulihkan token/cursor. Membuka halaman berikutnya membatalkan checkbox acknowledgement; error cursor menghapus token/cursor authority dan memberi Periksa kembali. Tidak auto-merge, auto-link, auto-reactivate, memperluas hak tenant atau membuat pasien saat mencari/paging. Review token lama tetap token first-page/kriteria; tidak menciptakan kebijakan wajib membuka semua halaman atau kebijakan identitas baru.

## Before/after dan reproduksi

1. **PG-18 before:** login Platform pada tenant dummy → isi grant → submit, POST dicegat sebelum backend. Dua fakta terlihat: Alpine SyntaxError dan submitting=true/disabled=false. [Before browser](f1-implementation/evidence/before.json), [screenshot](f1-implementation/evidence/screenshots/F1A-before.png).
2. **PG-17 before:** Rec membuka pasien baru → phone fixture dengan10 profil →8 link tanpa tombol hasil lanjutan. [Screenshot](f1-implementation/evidence/screenshots/F1B-before.png). Tidak membuat pasien selama reproduksi.
3. **Backend before:** dua regression awal gagal tepat pada directive mentah dan tidak adanya next_cursor; **2 FAIL /0 error**, disimpan pada [JUnit tersanitasi](f1-implementation/evidence/backend-before.xml).
4. **After:** [pending grant nyata](f1-implementation/evidence/final/screenshots/PG18-pending.png), [kandidat9/10 dan input](f1-implementation/evidence/final/screenshots/PG17-more.png), [inactive](f1-implementation/evidence/final/screenshots/PG17-inactive.png), [stale recovery](f1-implementation/evidence/final/screenshots/PG17-stale.png), [walk-in375](f1-implementation/evidence/final/screenshots/PG17-walk-in.png).

## Acceptance criteria dan negative cases

| Area | Bukti aktual |
| --- | --- |
| Grant raw directive / Alpine | Backend render + seluruh4 Playwright PG-18; tidak ada pageerror pada validasi/pending yang diperiksa. |
| Pending, error, retry, UUID | Native required + whitespace server validation, selected plan/UUID retained, empty catalog, double-click nyata, simulated500→Back. Backend retry/replay/payload mismatch tidak mengubah histori. |
| Halaman kandidat | Backend0/1/8/9/10/17, ordered no-loss/no-duplicate; browser halaman2 menemukan9/10, supplementary walk-in juga mencapai11 setelah explicit-create fixture sebelumnya. |
| Matcher/telepon | Shared predicate; alias62/0, separator dot/whitespace/legacy, NIK dan name+DOB. CCL/DFU dan PartialWorkflow regression tetap lulus. |
| Cursor palsu/stale/foreign | Backend forged/decrypt/shape/size/criteria/expiry/add/delete/edit/inactive; browser422 nyata untuk forged, perubahan halaman lanjut via tab kedua dan foreign tenant dengan clinic_id override. |
| Input/aksesibilitas UI | Create-patient input dan selection tetap, profile tab baru, keyboard Enter pada pagination, no overflow375/768/1024/1440; walk-in patient+doctor/service/date/schedule/notes tetap dan375px. |
| Role/tenant | Clinic Admin dan Receptionist dapat meninjau; Doctor/Patient/Platform ditolak. Foreign cursor tidak mengembalikan kandidat; policy profil existing tetap. |
| Review dan identitas | Unreviewed submit tidak mengirim store; forged/stale review token ditolak; setelah review eksplisit hanya profil orang berbeda yang dibuat. Kandidat nonaktif tetap nonaktif dan user_id tidak berubah; booking profil9 tidak membuat profil baru. |
| Histori/fitur terkait | [Integrity](f1-implementation/evidence/integrity-final.json): seluruh old appointment/queue/period retained; clinical/payment/addon data utama identik; full424 melindungi QA/DFU/CCL. |


Negative browser request tampering hanya mengubah request fixture untuk menguji respons backend nyata; bukan response stub. Respons500 untuk Back dinyatakan simulasi secara eksplisit. Tidak ada penilaian manusia/screen reader atau klaim semua kemungkinan kombinasi input diuji.

## Percobaan gagal yang tetap dipertahankan

- Focused PHP pertama setelah penambahan kasus:20 PASS/2 FAIL akibat fixture test: forged token terlalu pendek tertolak pada validasi panjang sebelum domain; role test membuat resepsionis kedua melebihi seat Free. Input/fixture test diperbaiki, bukan melonggarkan source guard. [Run awal](f1-implementation/evidence/f1-focused.xml), lalu22 PASS.
- Run Playwright awal:10 PASS/1 FAIL; selector submit memakai label booking publik pada form staf, timeout sebelum POST. Runner diperbaiki menjadi Buat booking; fresh-fixture full run lulus11/11, supplementary walk-in lulus. [Catatan run awal](f1-implementation/evidence/scenarios.json); tidak ada case gagal dihapus.
- Full Node pertama:20 unit PASS/2 Chrome FAIL saat login pada copied application workspace dengan HTTP500. Harness diganti ke original application bootstrap + router/storage/cache/build terisolasi; assertion test original tetap. [TAP awal](f1-implementation/evidence/javascript-chrome-full-final.tap).
- Retest berikutnya:21 PASS/1 FAIL, timeout menunggu revision-photo pada workflow lama. Pengujian ulang dengan assertion sama dan fixture baru lulus22/22 **tanpa perubahan kode klinis atau pelonggaran assertion**. Penyebab timeout waktu/sinkronisasi belum dipastikan; bukan dihapus dari bukti. [Retest timeout](f1-implementation/evidence/javascript-chrome-full-retest.tap), [hasil final](f1-implementation/evidence/javascript-chrome-full-final-r2.tap). Ini tetap batas stabilitas harness yang perlu diperhatikan bila berulang.

Runner Chrome hanya mengganti root resolution/server-router pada salinan test untuk isolasi, serta menambah diagnostik saat timeout. Berkas test lama pada workspace tidak diubah; unit JS dijalankan langsung dari source current. [Metadata](f1-implementation/evidence/javascript-chrome-full-final-r2-exit.json).

## Isolasi, integritas dan batas

- PHP memakai SQLite :memory: dan storage/cache/view process-private. Playwright memakai klon DB/storage CCL disposable baru, APP_KEY baru **hanya pada proses/private fixture**, mail array, broadcast null, kredensial provider kosong. Main fixture clock dibekukan14Nov09.00WIB agar data CCL dapat diulang; eksekusi tetap15Nov Bangkok. Chrome legacy membuat DB/storage sendiri, tanpa .env/public storage pengguna dan memakai build dummy terpisah. Workspace kemudian dibuild dengan npm run build normal menggunakan konfigurasi environment existing, agar tidak menyajikan bundle lama atau menerima konfigurasi VITE dummy dari sandbox.
- [Integrity final](f1-implementation/evidence/integrity-final.json): hanya5 file aplikasi dan1 test existing berubah dari439 baseline; satu test PHP baru. .env, SubscriptionService, migrations, composer/package files tetap. Seluruh40 laporan QA historis identik. Laporan product/plan historis tidak ditulis ulang.
- Dalam DB dummy Playwright: satu grant manual terjadwal, satu appointment+queue untuk profil existing, satu profil berbeda yang **secara eksplisit** dikonfirmasi melalui review. Semua row historis tetap; nomor RM/NIK/user_id/nama/tanggal lahir/telepon/gender/aktivasi pasien lama utuh. Satu alamat multiline dummy kehilangan satu pemisah baris ketika profil itu diedit melalui field alamat single-line **existing** untuk uji stale/inactive; seluruh karakter alamat lainnya tetap. Perbedaan ini dicatat pada integrity JSON, tidak disembunyikan dengan SQL cleanup dan tidak diperbaiki di luar scope F1. Clinical records/revisions/allergies/prescriptions dan payments/items/events/add-on utama identik dengan clone asal. Ini bukan klaim data pengguna telah diperiksa.
- Tidak menguji concurrency/locking MySQL baru, load/stress, provider settlement/refund nyata atau production-readiness. Cursor melakukan snapshot check tiap halaman; kapasitas dataset besar belum diukur. Expiry15 menit memerlukan Periksa kembali, bukan perubahan kebijakan pasien/subscription.
- Satu timeout Chrome historis tidak berulang pada final run tetapi akar sinkronisasinya belum dipastikan. Tidak mengklaim aplikasi bebas flake atau menutupi attempt gagal.
- Skill testing-best-practices tidak ditemukan pada katalog/lokasi lokal; pola PHPUnit/JS existing dipakai. Tidak mengubah Volt; tidak menambah dependency.
- PR-01 tetap **OPEN, tidak dikerjakan**. PG lain dan keputusan D-01–D-20 tetap di luar scope. Tidak commit, push, deploy atau mengubah data pengguna.
