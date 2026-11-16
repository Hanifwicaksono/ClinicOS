# E2E workflow report

> **Status terbaru setelah P2, 11 November 2026:** Chrome: P1, create/verifikasi/password/email/staff lifecycle, role/permission, deaktivasi/reaktivasi, self-delete, menu lima role, responsive dan keyboard DOM PASS. Native keyboard/provider nyata tidak diklaim diuji. Bukti langkah ada pada evidence/p2-retest/. Lihat [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Riwayat laporan sebelum perbaikan P2

Bagian di bawah mempertahankan checkpoint P1 dan baseline audit; status/angka historis tidak menggantikan hasil terbaru di atas.

> **Pembaruan setelah perbaikan P1, 11 November 2026:** QA-01 - QA-04 sudah FIXED pada pengujian ulang; P2 belum diperbaiki. Hasil terbaru: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Dua bug staf baru QA-13/QA-14 dan risiko sesi setelah reset admin tetap terbuka.

## Pengujian ulang yang dijalankan

| Alur | Hasil terbaru |
| --- | --- |
| E05 booking pasien publik | PASS Chrome sampai konfirmasi; appointment/patient ownership dan queue diverifikasi di DB fixture. |
| E12 foto setelah final/koreksi | PASS Chrome dan PHP; foto histori 200, foto terkini yang dihapus 404, role tidak berwenang ditolak. |
| E15 dokter jadwal terpakai | PASS penolakan reassignment dan update kuota; PHP juga mencakup visit aktif dan histori cancelled/completed. |
| E22 penghapusan owner | PASS penolakan sebelum logout, ownership/subscription tetap utuh. |
| E02/E23 siklus staf Doctor dan Receptionist | PASS lokal: create, unverified gate, resend/signed verify, password setup/login, permission/role revoke, nonaktif memutus sesi, reaktif, self-delete tanpa referensi klinis. Inbox nyata tetap BLOCKED. |
| Sesi setelah password diganti admin | OPEN: sesi lama kedua role masih 200; kebijakan revokasi belum diubah. |
| QA-13/QA-14 | Dua regression PHP tetap FAIL; tidak termasuk keberhasilan P1 di atas. |

Bukti: [langkah browser baru](evidence/p1-retest/p1-browser-steps.json), [hasil suite](FINAL_QA_REPORT.md). Hasil worker, coverage route dan charter lain pada tabel baseline berikut tidak diklaim sudah dijalankan ulang melalui browser.

## Baseline audit sebelum perbaikan

Bagian berikut mempertahankan hasil/source yang diamati saat audit awal. Angka, status FAIL P1 dan batas pada baseline ini bukan status terbaru; bukti lama tidak ditimpa.

Audit lokal terisolasi, 11 November 2026. “Browser” berarti Chrome nyata melalui DevTools Protocol, pola yang sudah ada di repository; Playwright tidak terpasang dan tidak ditambahkan. “Feature/service” berarti Laravel HTTP kernel/transaksi/fake provider, bukan browser atau delivery eksternal.

| ID | Perjalanan dan perpindahan tanggung jawab | Bukti/metode | Hasil |
| --- | --- | --- | --- |
| E01 | Owner baru → persetujuan → verifikasi email → klinik → paket | Browser register; email verification required; pengiriman inbox tidak dijalankan | BLOCKED untuk email end-to-end |
| E01a | Owner dengan fixture verified → clinic/settings → Free → dashboard | B-OWNER-REGISTER, B-OWNER-ONBOARD-FREE; database sementara | PASS untuk subalur terisolasi |
| E02 | Admin → buat akun Doctor → verifikasi → login Doctor | Browser staff create berhasil, status awal unverified; verification diatur hanya di fixture | BLOCKED untuk delivery verifikasi nyata; create PASS |
| E03 | Receptionist pilih pasien existing → booking → check-in → call → Doctor start → draft → upload → finalize | Browser interaktif, B-BOOKING-EXISTING-PATIENT sampai B-E2E-FINALIZE | PASS: satu visit; record FINAL, visit/appointment/queue COMPLETED |
| E04 | Pasien baru → duplicate review → konfirmasi manual → patient tersimpan → walk-in booking | Browser existing memeriksa review dan create; PartialWorkflowCompletionTest memeriksa booking baru tanpa auto-link | PASS untuk langkah yang disebut; bukan repeat E03 penuh |
| E05 | Patient → klinik publik → pilih dokter/layanan/sesi → booking → token status | Browser baru menemukan pilihan dokter tidak bekerja | FAIL QA-01; langkah setelah pilihan dokter BLOCKED oleh QA-01 |
| E05a | Request Patient valid → patient profile → appointment + queue → replay UUID → snapshot harga | BookingTest dan PublicCancellationTest, Laravel HTTP | PASS backend; tidak membatalkan FAIL UI E05 |
| E06 | Pemegang token → baca status → cancel sebelum sesi → kuota kembali tersedia | BookingTest/PublicCancellationTest; token salah/guest/aktor beda tenant | PASS feature/service; browser cancel belum dijalankan |
| E07 | Patient portal → list/detail milik sendiri → cancel; pasien/tenant lain ditolak | AppointmentAuthorizationTest/PublicCancellationTest | PASS feature; IDOR historis AUD-01 tidak direproduksi lagi |
| E08 | BOOKED→WAITING→CALLED→skip→return, FIFO dan satu active call | QueueManagementTest; E03 browser untuk jalur utama | PASS; SQL concurrency MySQL BLOCKED |
| E09 | Dokter tulis → autosave → numeric validation → offline → reconnect/retry → reload | Browser existing + Node RmeAutosave tests + PartialWorkflowCompletionTest | PASS; foto memang melalui simpan manual |
| E10 | Draft resep belum lengkap → simpan → lengkapi → finalize → resep final pasien sendiri | Backend tests dan UI resep existing | PASS untuk persistence/ownership; cetak/interaksi obat belum ada |
| E11 | Alergi kritis → acknowledgement → perubahan alergi → stale editor ditolak | Browser existing, RmeGeneralDentalTest, PartialWorkflowCompletionTest | PASS dengan versi dikirim; seluruh race/allergy version omission NOT TESTED |
| E12 | Final record → Modification beralasan → revision → baca ulang histori | MedicalRecordCorrectionTest; browser correction foto | FAIL QA-02 pada foto; teks/resep/reason ordinary path PASS |
| E13 | Dua editor → stale version → simpan/finalisasi/koreksi | Existing stale draft/finalize 409; regression omission | FAIL QA-05 ketika version dihilangkan; MySQL parallelism BLOCKED |
| E14 | Upload draft → kegagalan audit storage → DB rollback → disk cleanup | Dependency failure terkontrol, fake local disk | FAIL QA-08, file orphan tersisa |
| E15 | Edit sesi terpakai → booking/queue/visit tetap satu dokter | Browser edit dari admin dan regression active visit | FAIL QA-04 |
| E16 | Trial → expiry → Free; paid period → expiry/read-only → renewal; owner deactivate seats; dokter selesaikan visit aktif | SubscriptionLifecycle/Enforcement/PlatformSubscriptionManagement tests | PASS feature/service; jadwal/lifetime service nyata tidak diasumsikan aktif |
| E17 | Owner pilih paid/combined addon → invoice pending → redirect → notification valid → period/addon → refund | DokuPaymentTest/OdontogramAddonTest memakai Http fake | PASS kontrak backend; DOKU sandbox/real BLOCKED |
| E18 | Add-on aktif → General/Dental tersimpan → expired/refunded → histori baca, edit dental ditolak | OdontogramAddonTest dan RmeGeneralDentalTest | PASS feature/service; General tetap bisa diselesaikan sesuai pengecualian |
| E19 | Platform → katalog/grant/downgrade/refund → audit → data tenant nonmedis | PlatformAccess/PlatformSubscriptionManagement/DokuPayment tests | PASS yang diuji; reconcile PENDING/katalog add-on tambahan PASS dengan fake |
| E20 | Booking/queue commit → notifikasi DB/job → worker → retry saat transport gagal | ClinicNotificationDeliveryTest; worker lokal menghabiskan 15 job, 0 gagal, broadcaster null | PASS database/job/fake; Reverb delivery BLOCKED |
| E21 | Scheduler reminder → milestone → DB notification/mail idempotent | SubscriptionReminderTest; schedule:list terisolasi | PASS fake/registry; actual cron dan inbox BLOCKED |
| E22 | Owner → hapus akun → klinik masih dapat dikelola/ditutup secara sah | Browser setelah onboarding + regression | FAIL QA-03: owner hilang dan klinik tetap ada |
| E23 | Login → perubahan role/inactive pada sesi aktif → URL langsung → logout | Browser lima role; live role change Doctor→Receptionist; AccountSecurityTest | PASS pada request HTTP berikutnya; socket existing BLOCKED |

## Integritas yang terbukti dan batasnya

E03 membuktikan visit baru dibuat saat Doctor mulai dari CALLED, bukan saat check-in. Finalisasi menutup empat entitas secara konsisten. Existing test juga memaksa kegagalan finalisasi dan memeriksa rollback database; enqueue/notification failure tidak meninggalkan cancellation setengah jadi.

Itu belum menjamin integritas filesystem: QA-02 dan QA-08 membuktikan dua kegagalan yang berbeda. Snapshot harga, invoice, event dedup, dan periode komersial dipertahankan dalam test; tidak ada pembayaran nyata, seed pada database pengguna, maupun perubahan histori pengguna.

Tidak ada pengujian concurrency MySQL dua proses. Test bernama BookingConcurrencyTest mengirim request berurutan; hasilnya tidak dipakai untuk menyatakan locking aman di production. Pembatalan token, OTP/OAuth eksternal, backup/restore, dan delivery realtime tidak diberi status browser PASS hanya berdasarkan render atau fake.
