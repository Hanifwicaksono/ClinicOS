# Bug report ClinicOS

**Audit terbaru setelah P2 — production readiness:** **NO-GO untuk produksi multi-user**. QA-01–QA-15 tetap FIXED pada cakupan semula. Ada tiga temuan baru OPEN: PR-01 (P2, revokasi socket aktif), PR-02 (P3, assertion JSON MySQL), dan PR-03 (P3, factory melampaui panjang kolom). [Reproduksi/akar masalah](production-readiness/NEW_FINDINGS.md), [scorecard dan bukti run](production-readiness/PRODUCTION_READINESS_SCORECARD.md). Angka 0 OPEN pada paragraf P2 di bawah adalah status historis sebelum audit operasional ini.

**Status setelah perbaikan P2, 11 November 2026:** QA-01 sampai QA-15 FIXED pada skenario yang diuji; 0 temuan terkonfirmasi OPEN. QA-15 adalah temuan baru kegagalan notifikasi setelah akun staf commit. Kebijakan revokasi sesi dan verifikasi ulang email juga selesai. Bukti terbaru: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Expected/actual audit awal di bawah adalah histori, bukan status source terbaru.

Audit awal 11 November 2026, Asia/Bangkok. Baseline `b82237d932478df9ed02240cb698acff9b4ec669`, working tree awal bersih. Source produksi tidak diubah. Semua reproduksi memakai fixture sintetis dan SQLite terisolasi.

Severity: P1 = menghalangi alur utama atau merusak integritas/histori; P2 = kegagalan skenario tertentu, aksesibilitas, atau risiko dependency yang perlu ditangani; P3 = informasi pengembangan keliru. Tidak ada P0 yang terbukti. Satu bug dapat menghasilkan beberapa assertion gagal.

## QA-01 — Pasien tidak dapat memilih dokter pada booking publik

- **Status/severity:** FIXED / P1 (pengujian ulang 11 November 2026).
- **Role/prasyarat:** Patient terverifikasi; klinik aktif dengan dokter, layanan, dan jadwal aktif.
- **Reproduksi sebelum perbaikan:** Login melalui `/login`; buka `/clinic/klinik-sehat-sentosa/booking` pada fixture demo; fokus/klik pilihan dokter; coba pilih dokter dan lanjutkan booking.
- **Expected:** Pilihan dokter muncul, pilihan layanan/sesi mengikuti pilihan, form dapat diselesaikan.
- **Actual saat audit awal:** HTTP 200, tetapi `window.Alpine` tidak tersedia; factory `bookingSelection` tersedia tanpa inisialisasi; jumlah tombol pilihan dokter 0. Pasien tidak dapat mengisi foreign key yang diperlukan.
- **Bukti:** `B-PUBLIC-BOOKING-UI` di [interactive-browser.json](evidence/interactive-browser.json); langkah `choose a doctor in public booking` di [browser-audit-steps.json](evidence/browser-audit-steps.json); [browser regression](evidence/browser-regression.txt).
- **Akar masalah:** [public-layout.blade.php](../resources/views/components/public-layout.blade.php) tidak mempunyai komponen Livewire yang memicu penyertaan runtime; [app.js](../resources/js/app.js) mengimpor helper tanpa memulai Alpine. [booking-fields.blade.php](../resources/views/components/booking-fields.blade.php) bergantung pada Alpine. Halaman staf memperoleh runtime melalui navigasi Livewire.
- **Perbaikan terkecil yang disarankan:** Sediakan bootstrap Alpine/Livewire pada layout publik sesuai runtime yang sudah dipakai aplikasi; hindari dua instance Alpine. Verifikasi pilihan dokter → layanan → sesi → submit dalam browser.
- **Perbaikan dan retest aktual:** Runtime Livewire dimuat eksplisit. Query layanan aktif diperbaiki setelah E2E menemukan bahwa is_active tidak terambil, sehingga layanan tetap kosong meskipun dokter sudah dapat dipilih. PHP dan Chrome submit booking sampai DB/queue PASS. Bukti: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [langkah browser](evidence/p1-retest/p1-browser-steps.json), [PHP terbaru](evidence/p1-audit-php.txt).


## QA-02 — Koreksi menghapus file foto yang masih dirujuk histori final

- **Status/severity:** FIXED / P1 (pengujian ulang 11 November 2026).
- **Reproduksi sebelum perbaikan:** Dokter simpan foto dummy pada visit; finalisasi; buka Modification, hapus foto, isi alasan sah, simpan.
- **Expected:** Foto pada versi final sebelumnya tetap dapat ditelusuri oleh dokter yang berwenang.
- **Actual saat audit awal:** Revision menyimpan path pada `before_data`, tetapi file fisik tidak lagi ada. Endpoint foto saat ini juga hanya mengalamatkan foto pada record terkini.
- **Bukti:** `B-PHOTO-HISTORY`: `revisionRetainsReference=true`, `fileExists=false`; regression `test_final_record_photo_remains_available_in_revision_history` gagal di [FullSystemAuditTest.php](tests/FullSystemAuditTest.php).
- **Sumber:** [VisitWorkflowService.php:254](../app/Services/VisitWorkflowService.php#L254), penghapusan setelah commit pada baris 282; [VisitController.php](../app/Http/Controllers/Doctor/VisitController.php), aksi `photo`.
- **Perbaikan terkecil:** Pertahankan file yang dirujuk revision final dan sediakan pembacaan versi historis melalui policy yang sama. Penghapusan dari tampilan terkini tidak boleh memutus histori.
- **Perbaikan dan retest aktual:** File yang dikeluarkan dari record final tetap disimpan; foto snapshot before/after dapat dibuka dari UI histori melalui endpoint foto ber-policy dan revision milik record yang sama. Draft cleanup tetap PASS; browser upload/finalize/correct/read-history PASS. Bukti: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [langkah browser](evidence/p1-retest/p1-browser-steps.json), [PHP terbaru](evidence/p1-audit-php.txt).


## QA-03 — Hapus akun owner meninggalkan klinik tanpa pemilik

- **Status/severity:** FIXED / P1 (pengujian ulang 11 November 2026).
- **Reproduksi sebelum perbaikan:** Owner membuat klinik dan memilih Free; buka Profil → Hapus akun; masukkan password benar; konfirmasi.
- **Expected:** Ada penutupan/transfer kepemilikan yang didukung atau penghapusan ditolak dengan penjelasan sebelum klinik kehilangan owner.
- **Actual saat audit awal:** Akun terhapus, klinik dan subscription tetap ada, `owner_user_id=NULL`; UI tidak menyediakan pemulihan owner. Checkout memerlukan owner eksplisit sehingga akses komersial dapat terputus.
- **Bukti:** `B-OWNER-DELETE` menyimpan tiga boolean tersebut; regression `test_clinic_owner_cannot_delete_the_only_owner_account` gagal.
- **Sumber:** [delete-user-form.blade.php:20](../resources/views/livewire/profile/delete-user-form.blade.php#L20); [migration owner](../database/migrations/2026_10_15_085858_add_owner_user_id_to_clinics_table.php) menggunakan `nullOnDelete`; pemeriksaan owner di [DokuPaymentService.php](../app/Services/DokuPaymentService.php).
- **Perbaikan terkecil:** Tolak penghapusan owner selama ia masih memiliki klinik sampai tersedia keputusan/alur penutupan atau transfer. Jangan menghapus histori klinis/komersial untuk menyelesaikan masalah ini.
- **Perbaikan dan retest aktual:** Relasi ownedClinic diperiksa di bawah row lock sebelum logout/delete. Akun owner dan legacy owner tetap ada, sesi tetap login, subscription/akses komersial tetap tersedia. Skill Volt resmi sudah tersedia lokal dan dibaca sebelum verifikasi akhir. Bukti: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [langkah browser](evidence/p1-retest/p1-browser-steps.json), [PHP terbaru](evidence/p1-audit-php.txt).


## QA-04 — Mengganti dokter jadwal terpakai merusak konsistensi relasi

- **Status/severity:** FIXED / P1 (pengujian ulang 11 November 2026).
- **Reproduksi sebelum perbaikan:** Buat dokter pengganti tanpa jadwal bentrok; edit sesi yang sudah mempunyai booking; ganti dokter; simpan.
- **Expected:** Perubahan ditolak untuk sesi terpakai atau dilakukan melalui pemindahan booking yang menjaga semua relasi dan pemberitahuan.
- **Actual saat audit awal:** Penyimpanan berhasil. Pada browser, appointment dan queue tetap menunjuk dokter 1 sementara schedule menunjuk dokter 3. Regression juga membuktikan perubahan saat visit sudah aktif.
- **Bukti:** `B-SCHEDULE-REASSIGN`; `test_schedule_edit_preserves_the_doctor_of_existing_bookings` gagal.
- **Sumber:** [DoctorScheduleController.php:66](../app/Http/Controllers/Admin/DoctorScheduleController.php#L66); [StoreDoctorScheduleRequest.php](../app/Http/Requests/StoreDoctorScheduleRequest.php) hanya memeriksa klinik, jam, dan overlap.
- **Perbaikan terkecil:** Bekukan perubahan dokter pada sesi yang sudah dirujuk booking; buat sesi baru untuk dokter pengganti. Perubahan hari/jam pada sesi terpakai juga memerlukan aturan produk dan pengujian tersendiri; belum direproduksi sebagai bug terpisah.
- **Perbaikan dan retest aktual:** Update mengunci clinic lalu schedule dan menolak penggantian dokter ketika ada appointment/queue, termasuk histori. Sesi kosong dapat dipindahkan dan kuota dengan dokter sama tetap bisa diubah. Regression active visit dan Chrome PASS; concurrency MySQL belum diuji. Bukti: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [langkah browser](evidence/p1-retest/p1-browser-steps.json), [PHP terbaru](evidence/p1-audit-php.txt).


## QA-05 — Pemeriksaan versi koreksi dapat dilewati dengan menghilangkan field

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Finalisasi visit; kirim request koreksi valid beserta alasan, tetapi hilangkan `record_version`.
- **Expected:** Kontrak tulis mewajibkan versi agar editor lama tidak bisa melewati kontrol konflik.
- **Actual sebelum perbaikan:** HTTP 200 dan koreksi diterima. `allergy_version` juga nullable pada validasi dan hanya dibandingkan bila dikirim; bypass versi alergi belum diuji terpisah.
- **Bukti:** `test_final_correction_requires_a_version_to_prevent_stale_overwrites` gagal. Sebaliknya, test existing membuktikan stale version yang **dikirim** ditolak 409.
- **Sumber:** [SaveMedicalRecordRequest.php:37](../app/Http/Requests/Doctor/SaveMedicalRecordRequest.php#L37); [VisitWorkflowService.php:215](../app/Services/VisitWorkflowService.php#L215).
- **Batas:** Form browser saat ini menyertakan versi; temuan ini mengenai kontrak server/request yang tidak menyertakannya, bukan bukti bahwa semua autosave menimpa data.
- **Perbaikan terkecil:** Wajibkan versi pada setiap jalur tulis yang memakai kontrol konflik, termasuk finalisasi/koreksi; uji penghilangan field dan dua editor.
- **Perbaikan/retest terbaru:** record_version dan allergy_version wajib untuk save, complete dan correct; service menolak versi absen/stale. Enam omission case dan stale conflict existing PASS. Fixture positif memakai versi eksplisit, tanpa backfill pada request negatif. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-06 — Array klinis nullable menghasilkan HTTP 500

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Sebagai dokter visit aktif, PUT JSON ke draft dengan versi terkini dan `diagnoses: null`; ulangi dengan `treatments: null`.
- **Expected:** Nilai nullable ditangani aman atau ditolak 422; database tetap konsisten.
- **Actual sebelum perbaikan:** Kedua request menghasilkan 500 karena `array_filter` menerima null setelah validasi mengizinkannya.
- **Bukti:** Dua dataset `nullable diagnoses` dan `nullable treatments` gagal pada regression; [route-requests.jsonl](evidence/route-requests.jsonl) merekam statusnya. Tiga dataset item scalar ditolak dengan aman dan PASS.
- **Sumber:** [SaveMedicalRecordRequest.php:70](../app/Http/Requests/Doctor/SaveMedicalRecordRequest.php#L70), [VisitWorkflowService.php:296](../app/Services/VisitWorkflowService.php#L296).
- **Perbaikan terkecil:** Selaraskan kontrak nullable dan normalisasi service; tetapkan apakah null berarti kosong atau tidak mengubah data, kemudian uji semantik tersebut.
- **Perbaikan/retest terbaru:** Null diagnosis/tindakan dinormalisasi menjadi array kosong. Omission mempertahankan baris, null mengosongkannya; scalar tetap ditolak. Audit dan regression persistence PASS. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-07 — Add-on crash untuk owner yang belum mempunyai klinik

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Login sebagai owner terverifikasi sebelum membuat klinik; buka `/subscription/addons` secara langsung/bookmark.
- **Expected:** Redirect ke pembuatan klinik, atau state yang menjelaskan prasyarat.
- **Actual sebelum perbaikan:** HTTP 500, `AddonService::canEdit()` menerima null. Middleware hanya memeriksa tipe akun owner.
- **Bukti:** `test_owner_without_a_clinic_is_guided_to_onboarding_from_addons` gagal; stack mengarah ke [SubscriptionAddonController.php:23](../app/Http/Controllers/SubscriptionAddonController.php#L23).
- **Perbaikan terkecil:** Arahkan owner tanpa klinik ke onboarding sebelum memakai service bertipe `Clinic`.
- **Perbaikan/retest terbaru:** GET/POST add-on memakai onboarding.complete sebelum service dipanggil. Owner tanpa klinik diarahkan ke onboarding tanpa membuat payment. Halaman invoice pending tetap dapat dibaca. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-08 — Rollback draft meninggalkan upload tanpa referensi

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi terkontrol:** Upload foto dummy saat menyimpan draft; fake `AuditLogger` agar gagal setelah sinkronisasi record, sehingga transaksi rollback.
- **Expected:** Database rollback dan upload baru dibersihkan.
- **Actual sebelum perbaikan:** Database tidak menyimpan foto, tetapi satu file tetap berada di disk private.
- **Bukti:** `test_failed_draft_transaction_does_not_leave_an_unreferenced_upload` gagal: jumlah file 1, expected 0. Storage memakai fake disk; tidak menyentuh foto pengguna.
- **Sumber:** [VisitWorkflowService.php](../app/Services/VisitWorkflowService.php), `syncRecord()` menyimpan file sebelum transaksi selesai tanpa cleanup rollback.
- **Batas:** Gagal dependensi sengaja disimulasikan; tidak membuktikan audit storage production sedang gagal. Tidak ditemukan jalur publik untuk membaca orphan tersebut.
- **Perbaikan terkecil:** Bersihkan hanya upload baru ketika transaksi gagal; pertahankan file yang sudah tersimpan dan dirujuk histori.
- **Perbaikan/retest terbaru:** Upload baru mendaftarkan cleanup afterRollBack pada transaksi aktif. Regression membuktikan DB rollback, file baru hilang dan file lama tetap ada. Retensi foto final QA-02 tetap PASS; concurrency MySQL belum dibuktikan. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-09 — Escape tidak menutup navigasi mobile

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Pada 375 px, buka navigasi dari tombol hamburger; tekan Escape.
- **Expected:** Drawer menutup dan fokus kembali ke pemicu.
- **Actual sebelum perbaikan:** Drawer tetap terbuka. Source tidak mempunyai handler Escape/focus return pada drawer. Fokus/tab/screen reader lengkap belum diverifikasi.
- **Bukti:** [mobile-navigation-escape.png](evidence/mobile-navigation-escape.png), browser regression, `B-MOBILE-ESCAPE`.
- **Sumber:** [navigation.blade.php:29](../resources/views/livewire/layout/navigation.blade.php#L29).
- **Perbaikan terkecil:** Tambahkan pengelolaan Escape, fokus, dan state expanded pada navigasi yang sudah ada; uji keyboard pada drawer tertutup/terbuka.
- **Perbaikan/retest terbaru:** Drawer memiliki Escape/close, focus return, aria-expanded/controls, closed inert state dan focus trap bawaan Livewire. Lima role lulus Tab/Shift+Tab/Escape dengan KeyboardEvent DOM Chrome; native CDP/perangkat fisik belum terverifikasi. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-10 — Tujuh field pasien baru tidak mempunyai label programatis

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Resepsionis → Booking baru → Pasien baru; periksa accessible name input nama, NIK, lahir, gender, telepon, email, dan alamat.
- **Expected:** Label terhubung melalui `for/id` atau pembungkus label.
- **Actual sebelum perbaikan:** Teks label terpisah tanpa `for/id`, `input.labels.length=0`, tanpa `aria-label/aria-labelledby/title`. Label visual ada, tetapi tidak diasosiasikan ke kontrol.
- **Bukti:** `B-BOOKING-LABELS` mencatat ketujuh nama field; [appointments/form.blade.php](../resources/views/receptionist/appointments/form.blade.php).
- **Batas:** Ini pemeriksaan DOM pada browser; belum uji pembaca layar dengan pengguna.
- **Perbaikan terkecil:** Hubungkan label dan kontrol yang sudah ada. Tidak memerlukan komponen baru.
- **Perbaikan/retest terbaru:** Ketujuh kontrol memakai for/id yang berpasangan. DOM Chrome memverifikasi label untuk Clinic Admin dan Receptionist; screen reader fisik belum diuji. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-11 — Lima paket terpasang masuk rentang advisory keamanan

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Jalankan `composer audit --format=json` tanpa update dependency.
- **Expected:** Tidak ada advisory terbuka pada dependency rilis, atau ada keputusan mitigasi berbukti per advisory.
- **Actual sebelum perbaikan:** Exit 1; 8 advisory pada `symfony/http-foundation` (4), `http-kernel` (1), `mime` (1), `routing` (1), `yaml` (1). Versi foundation/kernel terpasang 7.4.20, mime 7.4.19. Npm audit exit 0 dengan 0 advisory.
- **Bukti:** [composer-audit.json](evidence/composer-audit.json), rincian dan sumber resmi di [SECURITY_AUDIT.md](SECURITY_AUDIT.md).
- **Batas:** Tidak sama dengan 8 celah ClinicOS yang sudah dieksploitasi. Misalnya shortcut `private_ranges` tidak dipakai oleh konfigurasi proxy aplikasi.
- **Perbaikan terkecil:** Review dan perbarui paket kompatibel dalam pekerjaan terpisah yang diotorisasi, lalu ulangi pengujian. Tidak ada dependency diubah pada audit ini.
- **Perbaikan/retest terbaru:** Hanya lima paket Symfony diperbarui pada seri patch 7.4: Foundation/Kernel/Routing/Yaml ke 7.4.23 dan Mime ke 7.4.22. Composer audit serta npm audit 0, suite lengkap PASS. Eksploitabilitas semua jalur aplikasi bukan klaim hasil ini. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-12 — README memberi prasyarat dan gambaran fitur yang keliru

- **Status/severity:** FIXED / P3 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Bandingkan instruksi PHP ^8.3 dan status hanya M1/dashboard placeholder di README dengan composer.json dan source saat ini; bandingkan petunjuk SQLite dengan phpunit.xml MySQL.
- **Expected:** Panduan setup saat ini selaras dengan manifest dan konfigurasi test.
- **Actual sebelum perbaikan:** Checkout memerlukan PHP ^8.5, domain implementasi sudah jauh melewati M1, dan perintah test README tidak menetapkan override SQLite eksplisit.
- **Bukti:** [README.md](../README.md), [composer.json](../composer.json), [phpunit.xml](../phpunit.xml). Laporan PROJECT_ANALYSIS/AGENTS bertanggal lama juga memuat kesenjangan yang kini sudah ditutup; konteks historisnya dipertahankan.
- **Perbaikan terkecil:** Perbarui panduan yang dipakai untuk setup; pertahankan laporan historis sebagai histori bertanggal, bukan mengganti hasil lama dengan hasil baru.
- **Perbaikan/retest terbaru:** README diselaraskan dengan PHP ^8.5, cakupan aktual, default MySQL testing, override SQLite/GD dan perilaku seeder. Instruksi browser opt-in serta kebutuhan delivery/worker dibedakan dari bukti operasional. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-13 - Permission create staf tidak mengikuti role target

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Clinic Admin satu tenant hanya diberi `receptionist.create`; kirim form create staff dengan role Doctor dan profil sah.
- **Expected:** 403 dan tidak ada akun Doctor baru.
- **Actual sebelum perbaikan:** Akun Doctor tersimpan dan request sukses redirect 302. Regression memastikan boolean persistensi, tanpa mencetak hash/password.
- **Akar masalah:** StoreStaffRequest memakai UserPolicy.create yang menerima canAny doctor.create/receptionist.create; role target hanya dibatasi allowlist. Service menggunakan role pilihan tanpa permission yang sesuai target.
- **Bukti:** [StaffLifecycleAuditTest.php](tests/StaffLifecycleAuditTest.php), [hasil PHP](evidence/p1-audit-php.txt), [analisis staf](STAFF_LIFECYCLE_REPORT.md).
- **Batas dan tindak lanjut:** Role Clinic Admin tetap diwajibkan route; bukan bukti Receptionist/Patient default bisa membuat akun. Verifikasi permission per role target dan kasus simetris pada tahap P2; belum diperbaiki.
- **Perbaikan/retest terbaru:** Policy menerima role target dan request mengirimkannya ke gate. Matrix permission per target, audit HTTP dan browser penolakan 403 tanpa akun baru PASS. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-14 - Self-delete dokter yang mempunyai booking menghasilkan error server

- **Status/severity:** FIXED / P2 (pengujian ulang setelah P2).
- **Reproduksi sebelum perbaikan:** Doctor dengan appointment miliknya membuka Hapus akun dan mengirim password sah.
- **Expected:** Penolakan form yang jelas sebelum logout, dengan histori tetap utuh.
- **Actual sebelum perbaikan:** User-delete mencoba cascade Doctor, ditolak FK restrict appointment/entitas klinis dan melempar QueryException. User/data tetap ada, tetapi sesi sudah menjalankan logout/invalidation.
- **Akar masalah:** Form self-delete tidak memeriksa kebutuhan retensi akun klinis sebelum logout/delete. Perlindungan owner QA-03 tidak meliputi kasus ini.
- **Bukti:** [StaffLifecycleAuditTest.php](tests/StaffLifecycleAuditTest.php), [hasil PHP](evidence/p1-audit-php.txt), [analisis staf](STAFF_LIFECYCLE_REPORT.md).
- **Perbaikan berikut:** Tolak penghapusan dengan validation error dan gunakan deaktivasi untuk menghapus akses; jangan cascade/hapus histori klinis. Belum diimplementasikan.
- **Perbaikan/retest terbaru:** Pemeriksaan referensi klinis dan kepemilikan dilakukan sebelum logout/delete dengan row lock. Penolakan mempertahankan sesi, akun dan histori; deaktivasi tetap jalur pencabutan akses. Akun tanpa referensi tetap dapat self-delete. Bukti lengkap: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [hasil PHP/Node](FINAL_QA_REPORT.md).


## QA-15 - Kegagalan verifikasi saat create staf menghasilkan 500 setelah akun commit

- **Status/severity:** FIXED / P2, temuan baru pada penelusuran jalur gagal notifikasi.
- **Reproduksi sebelum perbaikan:** Fault injection dispatcher verifikasi pada create Receptionist; expected redirect dengan status akun sudah dibuat, actual HTTP 500. Registered dikirim setelah transaksi pembuatan user/role selesai, sehingga kegagalan delivery tidak membatalkan akun yang telah tersimpan.
- **Dampak:** Admin dapat menyangka pembuatan gagal dan mencoba membuat ulang email yang sama. Tidak membuktikan histori klinis rusak atau email production sedang gagal.
- **Akar masalah:** Exception listener Registered merambat melewati service setelah commit; pesan sukses juga mengklaim email telah dikirim.
- **Perbaikan:** Exception notifikasi setelah commit dilaporkan melalui handler; akun tetap unverified, respons menjelaskan verifikasi/resend dan tidak mengklaim delivery berhasil.
- **Regression:** StaffManagementTest::test_staff_creation_mail_failure_returns_a_recoverable_created_account, FAIL/500 sebelum fix dan PASS setelahnya; role, password hash, unverified dan exception reported diperiksa. Bukti staff-create-mail-before/after.txt di evidence/p2-retest/.

## Retest akhir

**15 temuan terkonfirmasi FIXED: 4 P1, 10 P2, 1 P3. Tidak ada temuan terkonfirmasi OPEN.** Tambahan kebijakan sesi dan email staf diuji pada PHP dan Chrome. Suite gabungan 363 PHP PASS (2.438 assertion), 7 JavaScript PASS, 3 browser PASS; 0 FAIL/ERROR/SKIP. Semua regression lama dipertahankan, termasuk 22 test audit yang dahulu merah.

BLOCKED/NOT TESTED tidak diubah menjadi FIXED: MySQL concurrency, SMTP/inbox, Reverb/socket aktif, DOKU nyata, staging/cron, backup restore/load, native keyboard dan screen reader. Lihat [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md) untuk keputusan production-readiness dan batas bukti.
