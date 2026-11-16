# Temuan baru dari coverage closure

Run 13–14 November 2026 (Asia/Bangkok), database SQLite dan seluruh identitas/isi klinis dummy terisolasi. **8 akar masalah OPEN**, terwakili9 assertion browser FAIL. Source produksi tidak diperbaiki. Ini temuan baru dari kombinasi yang sebelumnya belum diuji, bukan pembukaan ulang DFU-01–DFU-12 yang sudah FIXED & VERIFIED.

Bukti utama: [hasil browser](closure/evidence/scenarios.json), [runner kasus](closure/), [matrix](COVERAGE_CLOSURE_MATRIX.md). Hanya hasil tanpa `superseded_by` yang aktif. Screenshot bernama khusus di bawah lebih tepat daripada screenshot otomatis kegagalan yang kadang menangkap tab lain.

| ID | Prioritas / jenis | Role dan state | Hasil |
| --- | --- | --- | --- |
| CCL-01 | P2 UX / validation | Clinic Admin, tarif valid menurut server | step1000 memblok tarif |
| CCL-02 | P2 UX / recovery | Doctor, editor terbuka ketika sesi logout |401 dilaporkan sebagai gangguan koneksi |
| CCL-03 | P2 Functional | Receptionist, nomor lama berformat titik | Kandidat telepon tidak ditemukan |
| CCL-04 | P2 UX / booking context | Receptionist, pasien nonaktif | Konteks berubah diam-diam menjadi pasien baru |
| CCL-05 | P2 Functional / integritas | Owner mengubah jam; Patient booking sesi existing | Booking baru di luar jam operasional diterima |
| CCL-06 | P2 Functional / commercial status | Platform Admin, ends_at telah lewat | List tenant tetap active |
| CCL-07 | P2 UX / realtime | Receptionist, teks filter belum dikirim | Refetch menghapus teks input |
| CCL-08 | P3 UX / empty state | Owner, seluruh paket nonaktif | Halaman kosong tanpa penjelasan atau langkah lanjut |

P2: menghambat tugas atau menghasilkan perilaku/status menyesatkan yang bermakna; P3: kekurangan panduan pada keadaan khusus. Tidak ada klaim akses medis lintas tenant atau pembayaran provider sukses dari temuan ini.

<a id="ccl-01"></a>
## CCL-01 — Tarif valid diblok input step1000

Prasyarat: Clinic Admin dengan hak tulis. Buka **Layanan → Tambah layanan**, isi nama/durasi valid, lalu tarif `123456`; klik Tambah layanan. Ulangi dengan `123.45`.

Expected: kontrak UI sejalan dengan request server; tarif yang diizinkan dapat dikirim, atau aturan nominal dijelaskan konsisten. Actual: native validation `stepMismatch` menolak kedua nilai sebelum POST; layanan tidak tersimpan. Case `SERVICE-PRICE-123456` dan `SERVICE-PRICE-123_45` FAIL; `SERVICE-MAX` PASS untuk batas maksimum.

Akar source: [form layanan](../../resources/views/admin/services/form.blade.php):8 menetapkan `step="1000"`; [StoreServiceRequest](../../app/Http/Requests/StoreServiceRequest.php) dan [UpdateServiceRequest](../../app/Http/Requests/UpdateServiceRequest.php) mengizinkan numeric hingga9999999999.99 tanpa aturan kelipatan1000. Dampak paling jelas adalah nominal rupiah bulat nonkelipatan1000. Kebijakan pecahan mata uang perlu dipastikan sebelum memilih step; audit tidak menetapkan kebijakan baru.

[Screenshot123456](closure/evidence/screenshots/SERVICE-PRICE-123456.png), [screenshot pecahan](closure/evidence/screenshots/SERVICE-PRICE-123.45.png), [reproduksi](closure/administration.mjs).

<a id="ccl-02"></a>
## CCL-02 — Sesi berakhir401 diberi saran periksa koneksi

Prasyarat: Doctor membuka visit IN_PROGRESS. Di tab kedua context yang sama, buka profil lalu Keluar. Pada editor lama ubah SOAP dan tunggu autosave.

Expected: session/authentication error menyebut perlunya login ulang sambil menjaga teks belum tersimpan. Actual: request benar-benar401; status berbunyi **“Penyimpanan gagal. Periksa koneksi lalu coba lagi.”** dan menawarkan retry. Teks tetap ada, tetapi retry tidak menyelesaikan autentikasi. Case `SESSION-401-FEEDBACK` FAIL. Relogin same-origin200 teruji terpisah; ini bukan kasus419.

Akar source: [rme-autosave.js](../../resources/js/rme-autosave.js):85–89 menangani403/409/419 tetapi401 jatuh ke pesan koneksi umum. Tidak ditemukan kesalahan penolakan autentikasi backend. Perbaikan yang disarankan: tangani401 sebagai sesi berakhir dan sediakan pemulihan teks, dengan regression terhadap403/409/419/offline.

[Screenshot](closure/evidence/screenshots/SESSION-401-feedback.png), [reproduksi](closure/clinical-retest.mjs).

<a id="ccl-03"></a>
## CCL-03 — Format titik lolos validasi tetapi hilang dari pencarian kandidat

Prasyarat: Receptionist membuat pasien dummy dengan telepon `0812.8888.9999` melalui UI. Buka form pasien baru dengan nama/tanggal lahir berbeda agar pencocokan hanya melalui telepon; masukkan `081288889999` dan jalankan pemeriksaan kandidat.

Expected: format pemisah yang secara eksplisit diterima validator dinormalisasi konsisten pada kedua sisi. Actual: pasien tersimpan tetapi tidak masuk kandidat; variasi US/UK/ID dengan pemisah yang didukung query lulus. Case `PHONE-DOT-NORMALIZATION` FAIL. Ini meningkatkan risiko duplikasi; sistem tetap tidak boleh melakukan account linking otomatis.

Akar source: [PhoneNumber](../../app/Rules/PhoneNumber.php):18 mengizinkan titik; [PatientDuplicateService](../../app/Services/PatientDuplicateService.php):19/35 menghapus seluruh nondigit pada input, sedangkan normalisasi kolom hanya menghapus spasi, tanda hubung, plus dan kurung. Usulan: satu kontrak normalisasi yang konsisten, tanpa menulis ulang identitas pengguna secara otomatis.

[Screenshot](closure/evidence/screenshots/PHONE-dot-missing.png), [reproduksi dan format tepat fixture](closure/staff-duplicates.mjs).

<a id="ccl-04"></a>
## CCL-04 — Booking dari profil nonaktif kehilangan konteks pasien

Prasyarat: Receptionist membuka kandidat pasien bertanda **Nonaktif**, masuk profilnya, lalu klik **Buat booking**.

Expected: tampil penjelasan bahwa pasien nonaktif tidak dapat dipilih, beserta jalur pemulihan yang diotorisasi. Actual: halaman booking membuka mode pasien baru, `patient_id` kosong, tanpa menjelaskan mengapa pilihan sebelumnya hilang. Case `INACTIVE-CANDIDATE-BOOKING` FAIL. Audit tidak melanjutkan membuat identitas duplikat untuk membuktikan risiko tersebut.

Akar source: [AppointmentController.create](../../app/Http/Controllers/Receptionist/AppointmentController.php):37–48 memfilter `is_active=true`, lalu mengirim null; [form booking](../../resources/views/receptionist/appointments/form.blade.php) menafsirkan null sebagai mode pasien baru. Usulan: penolakan eksplisit atau tombol booking nonaktif dengan alasan. Hak/flow reaktivasi perlu mengikuti policy yang sudah ada.

[Screenshot](closure/evidence/screenshots/INACTIVE-patient-new-booking.png), [reproduksi](closure/staff-duplicates.mjs).

<a id="ccl-05"></a>
## CCL-05 — Perubahan jam klinik tidak membatasi booking baru pada sesi existing

Prasyarat: klinik berbayar dummy memiliki sesi12:00–23:59. Owner mengubah jam tutup menjadi10:00 melalui Profil klinik. Patient kemudian membuat booking baru pada sesi12:00 dari UI.

Expected minimum: ketidaksesuaian operasional dikenali dan tidak diam-diam menerima booking baru di luar jam klinik yang baru. Actual: appointment dan queue baru tersimpan; sesi tetap12:00–23:59. Case `CLINIC-HOURS-EXISTING` FAIL. Jam klinik dipulihkan melalui UI setelah pengujian; appointment dummy dipertahankan sebagai bukti.

Akar source: [ClinicController.update](../../app/Http/Controllers/Admin/ClinicController.php):54–79 menyimpan setting tanpa pemeriksaan sesi existing. [StoreDoctorScheduleRequest](../../app/Http/Requests/StoreDoctorScheduleRequest.php):54–58 memeriksa jam klinik ketika membuat jadwal, sedangkan [BookingService.validateAppointmentDate](../../app/Services/BookingService.php):300–330 memeriksa tanggal/hari/akhir sesi tanpa jam operasional terbaru.

Pilihan produk: (a) tolak perubahan jam yang bertabrakan sampai sesi disesuaikan, atau (b) izinkan perubahan tetapi blok booking baru pada sesi di luar jam, sambil mempertahankan appointment yang sudah ada. Memindahkan/membatalkan appointment lama otomatis berisiko merusak komitmen pelayanan; tidak dilakukan saat audit.

[Bukti database read-only](closure/evidence/clinic-hours-inconsistency.json), [screenshot booking diterima](closure/evidence/screenshots/CLINIC-hours-outside-booking.png), [reproduksi](closure/onboarding-boundaries.mjs).

<a id="ccl-06"></a>
## CCL-06 — List Platform menampilkan active sesudah masa berlaku berakhir

Prasyarat: subscription dummy berakhir02:30UTC. Majukan clock proses audit ke02:31UTC tanpa request owner atau membuka detail tenant; Platform membuka list tenant dan mencari tenant tersebut.

Expected: status efektif pada list selaras dengan periode yang telah berakhir. Actual: row tetap **active**, DB cached status juga active walau ends_at telah lewat. Membuka detail/owner kemudian merefresh status. Case `EXPIRY-PLATFORM-LIST-R2` FAIL. Guard tulis owner expired tetap PASS; tidak ada bukti bypass subscription.

Akar source: [TenantController.index](../../app/Http/Controllers/Platform/TenantController.php):24–38 hanya memuat subscription tersimpan; `show` pada baris48 memanggil `refreshStatus`. Usulan: hitung status efektif secara konsisten pada read path, dengan perhatian pada histori/periode terjadwal; bukan sekadar mengganti label lewat JavaScript.

[Proof clock/ends_at/UI](closure/evidence/expiry-cached-active-proof.json), [screenshot](closure/evidence/screenshots/EXPIRY-list-status-stale.png), [reproduksi](closure/subscription-ui.mjs).

<a id="ccl-07"></a>
## CCL-07 — Refetch memulihkan fokus tetapi menghapus teks filter

Prasyarat: dua browser Receptionist terhubung Reverb. Pada daftar antrean browser A fokus input pencarian dokter dan ketik `QA Clo` tanpa submit/pilih. Browser B mengubah antrean melalui UI sehingga memicu refetch A.

Expected: refetch tidak menghapus draft filter pengguna yang sedang diketik. Actual: elemen kembali fokus, tetapi nilai berubah menjadi string kosong. Case `QUEUE-FOCUS-REFETCH` FAIL. Ini kehilangan input lokal, bukan bukti filter server yang sudah diterapkan salah.

Akar source: [realtime.js](../../resources/js/realtime.js):64–143 menangkap identitas elemen fokus lalu mengganti region dengan HTML server; nilai/selection input yang belum dikirim tidak dipulihkan. Usulan: pertahankan state input relevan atau tunda replacement saat pengguna mengedit, tanpa mempertahankan ID resource yang sudah tidak sah.

[Expected/actual/focus](closure/evidence/queue-focus-refetch.json), [screenshot](closure/evidence/screenshots/QUEUE-filter-reset-refetch.png), [reproduksi](closure/realtime-final.mjs).

<a id="ccl-08"></a>
## CCL-08 — Seluruh paket nonaktif menghasilkan halaman tanpa penjelasan

Prasyarat: Platform menonaktifkan seluruh paket melalui UI. Owner membuka pemilihan paket.

Expected: tidak menawarkan checkout yang tidak sah dan menjelaskan paket belum tersedia serta langkah lanjut. Actual: guard aman—tidak ada formulir checkout—tetapi daftar kosong tanpa penjelasan atau kontak pemulihan. Teks pembuka masih mengajak mulai Free/trial/paket berbayar. Case `CATALOG-EMPTY-EXPLANATION` FAIL; `CATALOG-ALL-INACTIVE` PASS hanya untuk guard. Paket dipulihkan lewat UI setelah tes.

Akar source: [PlanController.index](../../app/Http/Controllers/Onboarding/PlanController.php):28 memfilter active; [view paket](../../resources/views/onboarding/plans/index.blade.php):16–40 memakai foreach tanpa empty state. Tidak ada keputusan harga/paket baru yang diperlukan untuk memperjelas availability.

[Screenshot](closure/evidence/screenshots/CATALOG-empty-no-guidance.png), [reproduksi](closure/remaining-ui.mjs).

## Observasi yang memerlukan keputusan, bukan bug yang diasumsikan

- Dua editor alergi menghasilkan versi append-only yang dapat sama-sama aktif. Kedua perubahan tetap tersimpan; kebijakan penggabungan/conflict antarversi perlu keputusan produk.
- NIK saat ini string opsional max30 dengan trim/exact match; tidak ada kontrak16 digit yang boleh diciptakan oleh audit. Telepon bukan bukti kepemilikan.
- Dua UUID berbeda dapat menghasilkan dua booking untuk pasien/sesi sama. Aturan satu booking per hari tidak tersedia; tidak dinyatakan sebagai duplikasi idempotency.
- Revoke grant individual, pemulihan grant historis ganda, full commercial-period viewer, dan kontrol kiosk khusus tidak tersedia pada UI saat ini. Dicatat sebagai gap/keputusan di [remaining gaps](REMAINING_GAPS.md), tanpa menulis fitur atau mengubah histori.
