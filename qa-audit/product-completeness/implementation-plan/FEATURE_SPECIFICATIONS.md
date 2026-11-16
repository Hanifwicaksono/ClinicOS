# Spesifikasi fitur berdasarkan PG-01–PG-18

Tanggal: 14 November 2026. **DRAFT UNTUK PERSETUJUAN DAN IMPLEMENTASI BERIKUTNYA**. Tidak ada fitur atau test aplikasi yang dibuat/dijalankan pada tahap ini. Nama tabel/class/route baru di dokumen ini adalah usulan. Detail D-* pada [keputusan](DECISIONS_REQUIRED.md) mengalahkan default rancangan bila alternatif lain disetujui.

## Kontrak bersama

1. Gunakan Laravel/Blade/Tailwind, Alpine/Livewire sesuai pola existing; jangan menambah framework/dependency. Gunakan services existing; class kecil baru hanya untuk orchestration yang belum ada.
2. Clinic berasal dari akun/resource terotorisasi. Dokter, jadwal, pasien, layanan dan periode harus konsisten relasinya. Patient ClinicScope tidak menggantikan query eksplisit pada console/guest/platform.
3. Lima role tetap. Owner = Clinic Admin dengan account_type dan ownership relation; role itu tidak memberi hak isi medis. Nama permission existing merujuk [seeder](../../../database/seeders/RoleAndPermissionSeeder.php). Policy action baru boleh ditambahkan spesifik tugas; bukan otomatis permission editor atau role baru.
4. Mutasi: validasi request + authorization → transaksi/lock → cek ulang state/version → domain command → audit minimal → dispatch setelah commit. Error/conflict mempertahankan input; replay memakai UUID dengan fingerprint command, tidak membuat record kedua.
5. Tanggal operasional mengikuti timezone klinik; waktu kejadian disimpan UTC. Preview tidak mereservasi slot atau menjadi izin bypass validasi commit.
6. Tidak menimpa histori klinis/komersial, membuat profil pasien pengganti, atau mengirim isi medis ke broadcast/audit metadata. Recovery expired-session/offline editor dan pending file mengikuti CCL-02; jangan menyimpan otomatis isi medis ke localStorage.
7. Semua AC di bawah memerlukan backend dan browser yang disebut. Invariant regresi bersama berada di [fase implementasi](IMPLEMENTATION_PHASES.md). Test yang gagal tidak dihapus; fixture MySQL terisolasi hanya untuk race/constraint yang relevan, bukan load test.

## PG-01 — Reschedule dan reassignment sebelum visit

**Masalah/perilaku:** pengguna memperbaiki dokter, layanan, sesi atau tanggal tanpa kehilangan hubungan booking lama/baru. Rekomendasi D-01: cancel-and-rebook dalam satu transaksi; bukan mengedit snapshot lama.

**Role/permission:** Receptionist dan Clinic Admin/Owner dengan appointment.update + akses tenant, dan queue.manage untuk transisi terkait. Doctor mengajukan kebutuhan perubahan, tidak mendapat appointment.update otomatis. Patient self-service jika D-01 disetujui memakai policy `rescheduleOwn` berbasis kepemilikan, bukan memberi appointment.update global. Guest tahap awal diberi jalur hubungi klinik; perlu persetujuan tersendiri untuk perubahan lewat secret token.

**UI:** detail booking → Ubah jadwal/dokter → pilih target aktif dan alasan → preview old/new (dokter, layanan, waktu lokal, harga, nomor/posisi/check-in) → konfirmasi → tampilkan kode baru serta tautan booking lama yang cancelled dengan alasan penggantian. Booking lama tetap terlihat. Slot habis, konflik atau perubahan harga mengembalikan form dengan input utuh dan opsi pilih ulang. Status CALLED memerlukan konfirmasi eksplisit bila disetujui.

**Backend/database:** usulan `AppointmentChangeService` mengoordinasikan BookingService, QueueWorkflowService, SubscriptionService dan AuditLogger. Jangan memakai cabang create-patient untuk pasien lama: bawa patient_id yang sudah diautorisasi. Tambahkan `appointment_replacements` (old/new/request unik; lihat [database](DATABASE_IMPACT.md)). Command mengunci resource dalam urutan yang diselaraskan dengan start/check-in/cancel; cek ulang **tidak ada visit**, state dan kapasitas. Cancel lama dan book baru atomik, memakai snapshot baru sesuai pilihan harga D-01. Jika salah satu gagal, tidak ada cancel/new queue/notification parsial. Replay hanya mengembalikan hasil command sama; reuse UUID dengan payload berbeda ditolak.

**Dampak/edge:** new queue number mengikuti sesi target, tidak mendaur ulang nomor lama. D-01 harus menentukan reset/preservasi check-in; rekomendasi default BOOKED + check-in eksplisit. Same-session quota full harus menghitung pelepasan booking lama dalam transaksi tanpa membuka kursi dua kali. Booking historis/final tidak berubah. Existing guest cancel cutoff tidak boleh diam-diam dilonggarkan untuk mendukung staff reassignment. Notification lama ditandai berubah/cancelled tanpa membocorkan secret akses booking baru.

**Acceptance criteria:** (1) authorized replacement menghasilkan tepat satu old→new link dan queue baru; snapshot lama utuh; (2) invalid target/tenant/inactive/jam/cuti/full menyebabkan zero partial writes; (3) race dengan start visit hanya mempunyai satu pemenang yang konsisten; (4) replay/double-click tidak menggandakan; (5) public token lama tidak membuka token/resource baru; (6) harga/status/cutoff/check-in sesuai D-01 yang disetujui, pasien memahami kode mana berlaku.

**Regression:** usulan AppointmentChangeTest + Playwright reschedule/reassign Rec/Patient dan penolakan seluruh role lain; BookingTest, PublicCancellationTest, VisitWorkflowTest, DeepFunctionalRegressionTest dan CoverageClosureRegressionTest terkait DFU-01/02, CCL-03/04/05/08. Dua proses MySQL terisolasi: replace vs start dan dua replacement pada slot terakhir. Browser bukan hanya GET halaman: sampai booking target tersimpan lalu check-in/panggil/start oleh dokter target.

## PG-02 — Dokter berhalangan dan kalender exception

**Masalah/perilaku:** menutup ketersediaan baru pada tanggal tertentu, mengetahui booking terdampak dan menanganinya satu per satu. F2B preview, F3B blocking calendar, dan F4 resolusi adalah tiga bagian PG yang sama.

**Role/permission:** Clinic Admin/Owner memakai schedule.manage/clinic.update sesuai cakupan. Receptionist dengan appointment.view/queue.view melihat daftar dampak nonmedis dan memakai PG-01. Doctor schedule.view dapat melihat; kewenangan mengesahkan cuti menunggu D-02, bukan otomatis diberi schedule.manage.

**UI:** Jadwal → Laporkan dokter berhalangan / exception → pilih dokter atau seluruh klinik, interval lokal dan alasan operasional → preview booking terdampak, dibagi belum mulai/sudah berjalan/historis → simpan blok → daftar Belum ditangani → buka satu booking → pindahkan lewat PG-01 atau cancel beralasan sesuai aturan → catat hasil komunikasi → daftar terselesaikan. Pemeriksaan aktif diarahkan ke PG-03; final hanya read-only. Pembatalan exception membuka ketersediaan berikutnya tanpa menghidupkan appointment cancelled.

**Backend/database:** `availability_exceptions` additive, predicate bersama pada daftar slot dan BookingService; query doctor melalui relasi tenant. Simpan/batalkan exception dan booking serial terhadap klinik/sesi terkait; validasi ulang di POST, bukan hanya menyembunyikan opsi HTML. Preview dapat diturunkan dari Appointment/Queue existing; tidak perlu tabel work queue generik. Alasan cuti pribadi tidak diteruskan ke Patient/public. Queue/read visit existing tidak ditolak hanya karena exception baru dibuat.

**Dampak/edge:** CCL-05 tetap: jam berlaku membatasi booking baru, appointment lama tidak diubah diam-diam. Tanggal batas tengah malam, exception tumpang tindih, timezone berubah, dokter nonaktif, seluruh target penuh, sesi sudah dimulai dan booking bersamaan dengan close harus jelas. Tidak ada tombol auto-cancel/auto-shift seluruh daftar pada versi minimum.

**AC:** (1) preview tidak mengubah data; (2) booking baru di interval blok ditolak server, tanggal lain tetap; (3) daftar dampak mempunyai link/task next-step nonmedis; (4) resolved hanya bila appointment dipindah/cancel sesuai domain, bukan sekadar halaman dibuka; (5) active/final tidak digeser; (6) re-open availability tidak memulihkan booking historis. **Regression:** ClinicManagementTest, DoctorScheduleManagementTest, BookingTest + usulan AvailabilityExceptionTest, browser owner-close/Rec-replace/Doctor-active; MySQL close-vs-book satu slot, CCL-05 replay/history tetap.

## PG-03 — Recovery pelayanan terinterupsi

**Masalah/perilaku:** gangguan tidak dipaksa menjadi COMPLETED atau cancel biasa. D-03A/B harus disetujui; rancangan berikut bersyarat.

**Role/permission:** dokter assigned dengan medical_record.update dan visit.complete sesuai aksi; tambahkan policy action `pause`, `resume`, `interrupt`, `acceptContinuation` yang memeriksa assignment/status, bukan menyamakan semua dengan complete. Pengecualian saat dokter semula tidak tersedia memerlukan kewenangan klinis eksplisit D-03; Owner/Rec tidak menjadi pembaca/penulis medis otomatis. Resepsionis hanya metadata pelayanan tertunda dan instruksi pemindahan administratif.

**UI F5A:** editor → Pelayanan terinterupsi → pilih jeda atau akhiri belum selesai, alasan dan dampak sesi → simpan draft yang versinya masih cocok → konfirmasi → tampil PAUSED dengan Lanjutkan, atau INTERRUPTED terminal dengan hasil “pelayanan belum selesai”. Ketika resume, validasi dokter/hak/sesi/slot aktif dan tampilkan draft semula. Jika draft gagal disimpan karena 401/419/offline/conflict, interupsi belum dikonfirmasi; input tetap dan pemulihan existing berlaku.

**UI F5B:** dokter berwenang → Ajukan kelanjutan ke dokter lain → pilih target yang sah dan ringkasan handoff yang disetujui → dokter penerima menerima/menolak sebelum memulai → staf memilih sesi sesuai kapasitas → episode lama ditutup jujur dan episode lanjutan tertaut → dokter penerima memulai record sendiri dengan konteks handoff berizin. Target tidak tersedia/menolak meninggalkan permintaan terlihat untuk ditangani; tidak auto-transfer. Jika dipilih model satu visit, UI sama tetapi backend assignment/history berbeda dan harus dispesifikasikan ulang sebelum migrasi.

**Backend/database:** usulan F5A memperluas Visit/Queue dengan PAUSED dan INTERRUPTED, Appointment dengan terminal interrupted yang disetujui. `visit_workflow_events` menyimpan actor/reason/version/before/after nonkonten. RME draft yang interrupted tetap tersimpan read-only; tidak disebut final. Pause/resume/final/interrupt memakai expected record+workflow version dan transaksi yang sama. Resume visit yang berasal IN_PROGRESS sebelum expiry mempertahankan pengecualian pelayanan existing sesuai D-03, tidak diubah menjadi booking baru.

F5B rekomendasi episode lanjutan memakai `visit_continuations` + appointment target baru melalui BookingService; hubungan episode ada pada continuation, **bukan** memanggil PG-01 replacement/cancel atas appointment yang sudah mempunyai visit. Visit/RME/doctor_id lama tidak ditimpa. Handoff medis minimum disimpan private dengan assignment/acceptance policy, tidak di AuditLog/queue payload. Kuota/entitlement untuk episode lanjutan harus dipilih D-03B; tidak membuat free continuation otomatis. Alternatif current-assignee satu visit memerlukan assignment ledger dan attribution per perubahan, sehingga bukan sekadar menambah current_doctor_id.

**Dampak/edge:** enum baru menyentuh isTerminal, active-session constraint, FIFO, ETA, dashboards, notifications, histories, subscription middleware, final Rx Patient dan laporan. PAUSED tidak boleh membekukan satu-satunya slot sesi selamanya; resume tidak boleh mengambil alih visit lain yang sedang aktif. Finalisasi lama yang berlomba dengan interrupt harus gagal/conflict tanpa menimpa. Rekam medis final/correction existing tetap sama. Handover tidak memberikan hak seluruh histori dokter lain.

**AC:** (1) pause mempertahankan seluruh draft/foto/dental dan attribution; (2) interrupted tampil non-completed dan tidak memunculkan Rx final; (3) slot dilepas/diambil sesuai D-03, satu aktif per sesi; (4) resume tidak menghapus pengecualian expiry pelayanan berjalan; (5) handover memerlukan acceptance, target berwenang hanya membaca paket handoff yang disetujui; (6) record lama tetap, target memulai/menyelesaikan record yang sah; (7) replay/conflict/rollback aman dan alasan/aktor tersimpan. Ketika D-03B belum disetujui, PG-03 **belum ditutup sepenuhnya** meskipun F5A lulus.

**Regression:** usulan VisitInterruptionTest dan VisitContinuationTest; VisitWorkflowTest, MedicalRecordCorrectionTest, SubscriptionEnforcementTest, RmeGeneralDentalTest/OdontogramAddonTest, DeepFunctionalRegressionTest DFU-01 dan CCL-02. Browser dua dokter + Rec: pause/resume/decline/accept/terminal dan expiry, tab stale, fail upload, 401/419, private attachment. MySQL race resume-vs-call dan finalize-vs-interrupt; ini concurrency functional terfokus, bukan stress test.

## PG-04 — Follow-up pasien tertaut

**Masalah/perilaku:** instruksi kontrol tidak berhenti sebagai teks; terlihat belum dijadwalkan, sudah booking, benar-benar selesai atau dibatalkan.

**Role/permission:** dokter assigned membuat instruksi; Patient hanya miliknya yang disetujui; Rec appointment.create/update hanya metadata jadwal; Admin/Owner agregat operasional tanpa SOAP. Gunakan policy follow-up yang memvalidasi visit asal dan pemilik, bukan akses semua MedicalRecord.

**UI:** setelah final → Buat tindak lanjut → tanggal/rentang, kebutuhan penjadwalan dan instruksi pasien yang disetujui → tampil OPEN → Patient/Rec Pilih jadwal → BookingService → BOOKED dengan tautan episode asal → visit tujuan selesai menjadi COMPLETED. Batal/no-show diarahkan ke langkah buka ulang atau review sesuai D-04, bukan dianggap kontrol selesai. Tanggal kontrol melewati batas booking existing disimpan sebagai tugas; UI menjelaskan kapan booking dapat dibuat.

**Backend/DB:** `follow_up_requests`, link appointment dan visit asal; event completion/cancel/replacement memperbarui status transaksional/idempotent. Tidak menulis ulang RME final; amendment instruksi punya history/audit sesuai keputusan, payload medis hanya endpoint ber-policy. PG-01 digunakan ketika appointment kontrol dipindah. Task minimum tidak bergantung PG-07 print.

**Edge/dampak:** doctor inactive, pasien nonaktif, kontrol luar 30-hari booking window, duplicate request, dua tab booking, correction source, ganti dokter dan expiry. **AC:** satu task/command, ownership benar; booking bukan completion; cancel tidak hilang; next-action jelas; instruksi klinis tidak bocor ke Rec; tidak auto-book. **Regression:** usulan FollowUpWorkflowTest + browser dokter→Patient/Rec→visit selesai; booking window/CCL-04/PG-01, stale/cancelled link dan private notifications.

## PG-05 — Billing/receipt pasien (ditahan scope)

**Masalah/perilaku:** pembayaran layanan memerlukan sistem terpisah; target saat ini tidak mengotorisasi modul kasir.

**Role/permission/UI:** tetap gunakan estimasi nilai layanan dan prosedur kas eksternal; tidak membuat role kasir/permission/tombol pembayaran baru. Jika D-05 kelak disetujui, pemetaan Rec/kasir/owner dan pemisahan tugas wajib ditentukan lebih dulu.

**Backend/DB:** **tidak ada perubahan yang direncanakan untuk dieksekusi**. Jangan menggunakan Payment SaaS, mengubah Visit.total_amount menjadi kas, menambah tabel invoice/patient-payment, atau memperluas gateway. Alternatif domain terpisah hanya requirement future, bukan paket implementasi yang siap.

**Edge/AC/regression:** nilai layanan, invoice SaaS dan payment history tetap utuh; tidak muncul klaim penerimaan kas/receipt pasien. Jika scope dibuka, spesifikasi baru harus mencakup harga/discount/partial/refund/idempotency/ledger/receipt dan testnya; dokumen ini tidak menyatakan AC finansial tersebut sudah diputuskan. Tetap jalankan Dashboard/Subscription/DOKU tests yang terkena perubahan feature lain.

## PG-06 — Kebijakan pasien nonaktif antar kanal

**Masalah/perilaku:** staff prefill diblokir, self-service masih memakai profil nonaktif; pilih satu makna D-06. Rekomendasi blok booking baru untuk profil yang sudah terhubung, tanpa reaktivasi otomatis.

**Role/permission/UI:** Rec/Admin dengan patient.update melakukan reaktivasi existing bila sah; Patient melihat alasan dan kontak klinik, form tidak berubah menjadi pasien baru. Status booking lama tetap terlihat dan pelayanan existing tidak dicancel otomatis. Guest tidak diverifikasi identitasnya melalui kemiripan telepon/NIK.

**Backend/DB:** satu predicate eligibility di BookingService/resolver dipakai staf dan authenticated Patient; query profil terkait tetap clinic_id/user_id. Tidak perlu migration bila makna satu flag cukup. Jika produk ingin archive dan booking-block terpisah, itu alternatif D-06 dengan schema terpisah yang belum disetujui.

**Edge/AC:** callback/replay UUID lama tidak membuat profil baru; account inactive berbeda dari patient inactive; resource asing 404/403; shared phone tidak account-link; profil tidak diam-diam aktif. Booking lama dan histori klinis utuh. **Regression:** CoverageClosureRegressionTest CCL-03/04, BookingTest, pasien guest/own/staff, inactive→reactivate UI dan dua tenant. Branch guest tidak boleh menjadi identity discovery endpoint.

## PG-07 — Dokumen hasil pelayanan

**Masalah/perilaku:** resep final tersedia tetapi belum menjadi dokumen terbit yang terlacak. Minimum D-07: resep printable Blade; ringkasan hanya bila disetujui.

**Role/permission/UI:** dokter assigned → visit final → Terbitkan resep → preview allowlist → konfirmasi penerbitan → halaman print dengan nomor/versi/waktu → Patient mengambil miliknya jika diizinkan. Rec/Owner/Platform tidak memperoleh full record; jangan memakai MedicalRecordPolicy.view sebagai bypass pasien, gunakan document-specific ownership/scope. Reprint dari histori, dengan label superseded bila source dikoreksi; penerbitan versi baru eksplisit.

**Backend/DB:** `clinical_documents` menyimpan immutable snapshot field yang disetujui, source record/version, issuer dan relasi supersession. GET print membaca snapshot, bukan selalu live prescription. Transaksi memeriksa final+completed dan version sebelum issue; request UUID dedup. Template/print CSS reuse komponen existing; tidak perlu PDF library. File bila kelak diperlukan disimpan private dan dialirkan via endpoint ber-policy.

**Edge/AC:** correction vs issue race; resep kosong, nama/item panjang, page break, data klinik/dokter berubah, record lama, pasien tanpa akun, expiry, superseded reprint. Draft tidak bisa diterbitkan; isi versi lama tidak berubah; hash/version cocok sumber; Patient hanya dokumen yang disetujui miliknya; signed URL tidak otomatis menjadi public bearer access. Tidak mengklaim legalitas/e-signature yang belum diputuskan. **Regression:** usulan ClinicalDocumentTest + browser print preview, ownership enam persona, revision/MedicalRecordCorrectionTest, PatientDashboard tests dan private-photo boundary; bukan print mentah seluruh editor.

## PG-08 — Laporan operasional

**Masalah/perilaku:** owner dapat merekonsiliasi angka periode dengan detail nonmedis dan satu export yang disepakati D-08.

**Role/permission/UI:** Clinic Admin/Owner dashboard.view + hak agregat existing; pilih tanggal lokal → ringkasan dan definisi status → detail pembentuk angka → unduh CSV. Empty menghasilkan laporan nol yang jelas, error rentang mempertahankan filter.

**Backend/DB:** reuse query operasional DashboardController, ekstrak shared query hanya bila report menambah pemanggil; streaming CSV allowlist, tanpa model/table generik baru. Tidak menambah laporan kas tanpa PG-05. Audit export hanya actor/criteria/count, tidak isi medis.

**Edge/AC:** from>to, midnight/clinic timezone, cancelled/replaced/PAUSED/INTERRUPTED tidak dihitung completed, formula CSV dari nilai teks harus di-escape, long names, foreign tenant. Total dan detail sama; label estimasi nilai layanan tetap bukan cash. **Regression:** DashboardAccessTest/CCL-07 tanggal, usulan OperationalReportTest dan browser filter→detail→download→rekonsiliasi; schema/enum PG-03 jika dipilih.

## PG-09 — Histori administrasi komersial

**Masalah/perilaku:** Platform menelusuri semua periode dan sumber, termasuk current/scheduled/expired/revoked tanpa bantuan query developer. Existing owner invoice/add-on history tetap dipakai.

**Role/permission/UI:** platform.tenant.view membuka tab Riwayat pada tenant; link invoice memerlukan platform.payment.view. Filter source/period/status; detail menampilkan actor/reason komersial sesuai D-09 dan link invoice/audit. Owner subset belum diaktifkan sebelum D-09 memilihnya; tidak ada aksi revoke pada fase read-only.

**Backend/DB:** extend TenantController/read query atas SubscriptionPeriod, AddonPeriod, Payment dan AuditLog; pagination dan source linkage eksplisit, tanpa migration. Current effective badge menggunakan SubscriptionService.statusAt/semantik period; opening timeline tidak menulis ulang period/payment. Cache refresh existing CCL-06 boleh tetap pada jalur existing, jangan menjadikan viewer mutator histori.

**Edge/AC:** no payment/manual grant, future boundary ends_at eksklusif, revoked, refund recording vs dana, Free/trial, missing legacy actor. Timeline benar lintas timezone/period dan link terotorisasi; no SOAP/foto. **Regression:** PlatformSubscriptionManagementTest, SubscriptionEnforcementTest, CCL-06, usulan CommercialHistoryTest + browser empty/current/future/refund/revoked; owner/admin/Doctor/Rec/Patient negatif.

## PG-10 — Single-grant revoke dan pemulihan komersial

**Masalah/perilaku:** koreksi satu kesalahan tanpa global Free atau menghapus histori. D-10 minimum yang direkomendasikan: hanya manual grant yang dipilih, tidak menafsirkan semua refund sebagai revoke paket utama.

**Role/permission/UI:** Platform dengan platform.subscription.grant, policy period/clinic + alasan; dari timeline pilih grant → preview before/after seluruh periode dan add-on yang benar-benar bergantung → konfirmasi dampak/UUID → hasil reversal/audit. Jika berkaitan dana, tampilkan terpisah “pencatatan refund” dan prosedur dana provider; platform.payment.manage hanya pada aksi payment, bukan syarat viewer tenant umum.

**Backend/DB:** SubscriptionService command terpilih dengan clinic/period locks, expected fingerprint, `subscription_grant_reversals` dan revoked_at existing. Jangan reschedule periode lain otomatis. AddonService hanya menangani entitlement yang terikat periode yang direvoke sesuai D-10. Refresh current cache melalui service existing. Future/paid/refunded/revoked policy berbeda; period source paid ditolak pada aksi manual-grant sampai ada keputusan spesifik.

**Edge/AC:** request dua tab, duplicate historis bukan bukti duplikat nyata, periode sudah berjalan/terjadwal, gap akses, paid future sah, addon terpisah, grant/reconcile berlomba. Hanya target berubah, future sah dan invoice tetap; alasan/actor/request unik; refund dana tidak dipalsukan; IN_PROGRESS dapat selesai. Jika fallback/current-access belum diputuskan, mutasi belum siap. **Regression:** usulan GrantReversalTest, PlatformSubscriptionManagementTest, OdontogramAddonTest, SubscriptionEnforcementTest, DOKU-related tests, DFU-12 dan CCL-06; browser preview/reload/replay dan pelayanan berjalan bersamaan. Provider fake ≠ provider refund E2E.

## PG-11 — Lifecycle tenant/owner (bersyarat)

**Masalah/perilaku:** transfer/suspend/closure tidak sama dengan clinic is_active; jangan menyatukan seluruhnya menjadi CRUD tenant baru.

**Role/permission/UI:** Owner mengajukan, Platform memverifikasi/mengesahkan sesuai D-11; UI preview efek kontrak dan akses, konfirmasi, riwayat. Existing clinic.update tetap hanya mengelola pengaturan klinik. Permission mutasi lifecycle spesifik baru hanya bila model kewenangan dipilih, bukan perluasan platform.tenant.view.

**Backend/DB:** gunakan owner_user_id/account_type/subscription existing; bila dipilih transfer, ledger transfer/verifikasi diperlukan. Suspension membutuhkan alasan/effective/recovery yang tersimpan dan service, bukan overwrite status cache yang bisa hilang pada refresh. Belum ada migration siap-eksekusi.

**Edge/AC/regression:** satu owner satu klinik, penerima sudah memiliki klinik, akun lama inactive, visit berjalan, future invoice, pending payment, resume. Tidak hard delete/impersonation; approval dan audit utuh. Usulan TenantLifecycleTest + browser kedua owner/Platform dan HTTP/PR-01 revoke, subscription + onboarding baseline. **Ditahan scope D-11**.

## PG-12 — Near-turn dan reminder appointment

**Masalah/perilaku:** pasien mengetahui mendekati giliran, bukan hanya saat CALLED. D-12 memilih threshold dan kanal; tahap minimum in-app.

**Role/permission/UI:** Patient hanya miliknya dan Doctor penerima yang relevan; Rec memantau status nonmedis. Notification center menampilkan next action dan waktu relevan; cancel/replacement tidak mengirim reminder untuk kode lama. Tidak menambah kanal WhatsApp/mail appointment otomatis.

**Backend/DB:** evaluasi near-turn setelah transisi queue committed melalui ClinicNotificationService; dedup persistent `appointment_reminder_deliveries` keyed appointment/kind/rule version/recipient. Jika scheduled reminder dipilih, command+job sendiri memakai scheduler existing, status/ownership diperiksa ulang saat kirim. Rule versi/config disetujui, bukan magic constant di Blade.

**Edge/AC:** skip/return, replace, paused, completed, queue dengan zero/one patient, backlog/retry, clock lokal, unsubscribe/revoke, recipient berubah. Satu pesan per kondisi yang disepakati; tidak ada data medis publik, tidak mengirim status basi, tidak mengklaim email eksternal sudah terbukti. **Regression:** notification/queue tests, tests/notification-realtime.test.mjs, usulan AppointmentReminderTest, browser inbox next-step/duplicate/status-changed dan PR-01 worker backlog.

## PG-13 — Konflik versi alergi

**Masalah/perilaku:** kedua input tidak hilang tetapi current assertion harus jelas menurut D-13. Existing allergy_version RME tidak otomatis menyelesaikan sibling edits.

**Role/permission/UI:** hanya Doctor berwenang pada patient/visit; editor kirim expected-parent/version → jika stale tampil dua versi dan input lokal → pilih review/supersede dengan alasan bila diizinkan. Owner/Admin/Rec/Patient/Platform tidak mendapat editor/allergy history klinis ini.

**Backend/DB:** saveAllergy di VisitWorkflowService menggunakan lock pasien/current lineage. Jika single-current dipilih, additive nullable `supersedes_id`/`lineage_id` atau tabel lineage current pointer + optimistic version; legacy dibiarkan unresolved, bukan ditebak dari teks. Simpan actor/alasan relasi versi; RME allergyVersion dan critical banner dihitung ulang. Pilihan multi-assertion dapat menghindari current pointer tetapi wajib UI interpretasi yang jelas.

**Edge/AC:** dua tab mengedit parent sama, deactivate-vs-edit, zat sama bukan otomatis alergi sama, legacy branches, critical→noncritical dan finalisasi bersamaan. Tidak silent last-write-wins; lineage/aktor dapat ditelusuri; final RME/revision tidak ditulis ulang; stale editor tetap memerlukan ack versi alergi yang benar. **Regression:** RmeGeneralDentalTest, allergy/critical acknowledgement + correction tests, usulan AllergyConflictTest, dua browser editor dan API tenant/role negatif. Fixture concurrency bukan studi keselamatan klinis manusia.

## PG-14 — Diagnosis dan repair legacy

**Masalah/perilaku:** pencegahan baru tidak menjawab kasus lama; keberadaannya pada data pengguna belum diverifikasi dan tidak diperiksa pada tahap ini.

**Role/permission/UI:** detector administratif bagi Platform/Owner sesuai dataset nonmedis; detail klinis hanya dokter berwenang. Hasil menampilkan kategori, ID internal minimum dan next step, bukan dump SOAP/token. Awali dry-run fixture; UI repair bukan tombol perbaiki-semua. Izin mengakses real data terpisah dari izin membangun detector.

**Backend/DB:** domain-specific read queries: appointment/queue/date/doctor mismatch, visit active vs terminal queue, grant overlap/source, orphan owner/subscription. Detector zero writes termasuk tidak memanggil refreshStatus/grantLegacy yang dapat menulis. Repair komersial memakai PG-10; klinis memakai D-03/policy dan persetujuan khusus; fingerprint sebelum/after dan audit, tidak menyalin LegacyTenantBackfillService menjadi general repair.

**Edge/AC:** data berubah antara dry-run/apply harus conflict; healthy legacy exception bukan false positive; report tidak memperbaiki otomatis; idempotent request tidak menduplikasi adjustment; histori tetap. Perlu repair ledger hanya saat apply disetujui. **Regression:** usulan LegacyIntegrityReportTest dengan fixtures rusak/sehat/sumber tak lengkap, zero-write assertion, browser read-only role boundary; repair-specific tests setelah kategori/keputusan dipilih. PG-14 belum complete hanya karena detector dibuat.

## PG-15 — Invitation dan delegasi (opsional)

**Masalah/perilaku:** mengurangi distribusi password awal; ini tidak berarti reset/verifikasi existing hilang. Delegasi Clinic Admin terpisah dari invitation.

**Role/permission/UI:** Owner dengan izin staf existing memilih Undang jika D-15 dipilih → staf menerima tautan set password single-use → verifikasi → login tugas. Admin delegate tidak diundang otomatis dari role dropdown. Resend/revoke/expired mempunyai next action, tidak menampilkan password.

**Backend/DB:** reuse Laravel password broker/verification bila memenuhi kontrak invitation; gunakan invite ledger baru hanya jika membutuhkan state acceptance/revoke yang broker tidak simpan. StaffAccountService, seat reservation dan invalidasi link ketika email/inactive berubah tetap domain. Delegasi memerlukan policy owner-only dan pencabutan PR-01, bukan role editor umum.

**Edge/AC/regression:** email berubah sebelum accept, double accept, seat habis, invited account inactive, reset lama, owner self-lockout. Link tidak berlaku setelah revoke/expired, role/tenant ditentukan server dan sesi HTTP reset tetap invalid. StaffManagementTest, ProfileTest, browser-p1-regressions, usulan StaffInvitationTest dan browser inbox dummy; provider inbox eksternal tetap batas bukti. **O3 menunggu D-15**.

## PG-16 — Packaging subscription

**Masalah/perilaku:** penawaran dan hak enforced harus sama; metadata realtime/management_dashboard belum menjadi guard nyata.

**Role/permission/UI:** Owner melihat matriks hak; Platform mengelola catalog; role klinis diberi alasan bila kontrak yang disepakati membatasi. Pilih copy-only atau enforcement melalui D-16, bukan otomatis menambahkan paywall.

**Backend/DB:** copy-only tidak perlu schema dan tidak menulis ulang invoice/period snapshots. Enforcement bila dipilih memakai SubscriptionService/feature access terpusat, query tenant dan current period, pengecualian read/IN_PROGRESS, tanpa mengubah AddonService dental. Tidak seed ulang katalog pengguna untuk mengubah kontrak.

**Edge/AC/regression:** Free/Individual/Team/trial/expired/suspended/future, downgrade, pemilik over-seat, odontogram read-only, realtime reconnect. Copy/UI/server sepakat; data paid lama tetap; clinical finish tidak terblokir. SubscriptionEnforcementTest, PlatformSubscriptionManagementTest, OdontogramAddonTest dan browser plan-state matrix yang terkena perubahan, tanpa mengulang seluruh audit. **O4 menunggu D-16**.

## PG-17 — Hasil kandidat pasien lanjutan

**Masalah/perilaku:** petugas tidak menyangka delapan hasil adalah seluruh pasien cocok. Tidak mengubah matching menjadi unique identity.

**Role/permission/UI:** Rec dengan patient.view/create, tenant sendiri. Panel menulis “Menampilkan 8 kandidat; masih ada hasil lain” bila benar → Lihat kandidat lain pada panel yang sama → pilih Buka profil di tab baru agar form asli/input/cursor tetap → booking profil existing atau kembali melanjutkan pasien baru setelah review. Tidak auto-merge atau auto-reactivate.

**Backend/DB:** reuse predicate PatientDuplicateService untuk candidate page, stable id ordering, has_more dan cursor terikat tenant/kriteria; default delapan dan review-token existing tetap kompatibel. Endpoint POST duplikasi existing dapat diperluas; kriteria NIK/telepon tidak perlu dipindah ke URL. Token acknowledgement harus diperiksa ulang terhadap kandidat/kriteria terkini; cursor bukan bukti izin. Tidak perlu schema. Refactor query sekali bila dibagi dengan paging, jangan membuat normalisasi kedua.

**Edge/AC:** 0/1/8/9/10 hasil, kandidat inactive, normalisasi 62/0/dot/whitespace, input berubah saat response lama tiba, cursor stale/forged/foreign. Kandidat 9/10 bisa dijangkau browser; daftar lanjutan tidak menghilangkan input; review masih diperlukan dan consent bukan linking. **Regression:** PatientDuplicateService/PatientController tests terkait, tests/patient-duplicates.test.mjs, CCL-03/04, usulan paging cases dan browser create→more→profile→booking tanpa duplicate patient. Ukuran S–M mencakup kontrak token, bukan hanya menambah tombol.

## PG-18 — Bug binding tombol grant

**Masalah/perilaku:** @js pada atribut custom component terkirim literal, Alpine gagal parse dan tombol tidak disabled saat submit. Bukti [UI-28/console](../evidence/page-error-triage.json); backend DFU-12 tetap idempotent.

**Role/permission/UI:** Platform subscription grant existing. Form valid → tombol disabled + feedback “Memproses…” → sukses redirect/histori; validation/provider/server error memulihkan form yang boleh dicoba ulang. Empty catalog tetap disabled. Invalid native form tidak meninggalkan submitting=true secara permanen.

**Backend/DB:** hitung boolean katalog pada konteks Blade yang benar lalu pasok expression Alpine yang valid; jaga `x-clinic-button` dan disabled server. Tidak perlu mengubah SubscriptionService, grant_request_id/constraint, route atau schema. Jangan membuat perbaikan komponen global bila cukup satu caller template.

**Edge/AC:** rendered DOM tanpa @js mentah/Alpine SyntaxError, pending tombol benar-benar disabled, empty catalog tidak terbuka oleh Alpine, keyboard Enter/double-click/retry aman, request UUID dipertahankan sesuai hasil. Tidak mengklaim UI disable menggantikan dedup server. **Regression:** render assertion terarah di PlatformSubscriptionManagementTest/DeepFunctionalRegressionTest dan browser delay request nyata pada fixture, check pageerror + disabled DOM, replay server tetap satu periode. Ini **SIAP** pada tahap implementasi berikutnya; tetap OPEN sekarang.

## PR-01 — Revokasi WebSocket existing, di luar 18 PG

**Masalah/perilaku:** akun/role kehilangan izin HTTP tetapi private socket lama masih menerima event nonmedis. Rencana tidak mengubah public queue-board menjadi private dan tidak menambah permission editor.

**Fakta teknis lokal untuk rancangan:** [channels](../../../routes/channels.php) memakai boolean authorization subscribe, [QueueUpdated](../../../app/Events/QueueUpdated.php) menyiarkan private queue + public board, [DeliverClinicNotification](../../../app/Jobs/DeliverClinicNotification.php) memeriksa hubungan recipient tetapi bukan terminasi socket. Reverb1.12.0 mempunyai UsersTerminateController dan SDK Pusher mempunyai terminateUserConnections, tetapi identitas user harus ada pada connection data. Private auth Laravel default tidak menyertakan user_id; Reverb EventHandler versi ini tidak menyediakan handler pusher:signin. Jangan menganggap memanggil SDK terminate saja atau Echo.signin otomatis menyelesaikannya.

**S1 spike yang direkomendasikan:** pada fixture, gunakan app-owned PusherBroadcaster/auth response untuk menyertakan signed `channel_data` (user_id server-side dan realtime_auth_version) pada **semua private channel**. Reverb memverifikasi signature termasuk channel_data; pusher-js meneruskan field tersebut. Bind app-owned ChannelManager/guarded private channel melalui kontrak package yang terpasang, tanpa edit vendor; validasi fresh user/status/tenant/permission/version saat subscribe dan sebelum frame private dikirim. Buktikan replay signature lama setelah revoke ditolak. Ini opsi implementasi yang perlu spike, bukan klaim extension sudah bekerja.

**Role/permission/UI:** Admin/Owner menonaktifkan staf via jalur sah; role/permission bisa berubah lewat API Spatie yang sah tanpa UI baru. Semua penerima private harus punya izin terkini; users.{id} milik user aktif saja. Jika akses dicabut, browser menampilkan status dan berhenti refetch yang tak berizin, tetapi teks/file editor yang belum tersimpan tidak dihapus/reload otomatis. Staff yang masih berwenang reauth/reconnect seperti existing.

**Backend/DB:** proposed users.realtime_auth_version naik bersama perubahan security context; perubahan role/permission dan user active ditangkap pada seluruh caller, termasuk sync/detach dan efek role-permission ke user anggota. config/permission.php events_enabled saat ini false; jika event package dipakai harus diaktifkan dan diuji, bukan hanya menulis listener. Revalidasi server memakai state/permission fresh pada process panjang, tidak cache otorisasi basi. Setelah commit, panggil terminateUserConnections untuk user/version; retry durable lewat queue/audit existing. Guard per-delivery adalah batas otorisasi walaupun transport terminate gagal; jangan melaporkan pencabutan tuntas hanya dari HTTP200 control API. Delivery job juga menolak penerima inactive/tidak lagi berhak.

**Dampak/edge:** multi-tab, old unsigned sockets, auth-issued-before-revoke tapi subscribe setelahnya, revoke lalu reactivate cepat, pending jobs, queue/public board pada socket sama, role berubah tetapi ada permission lain, permission-cache process panjang, kontrol server down dan DB read fail. Private guard fail-closed; public nonmedis tetap berjalan. Existing unsigned private subscriptions harus ditolak/diakhiri server sebelum klaim fixed; tidak mengandalkan semua browser mau reconnect. Frame yang telah dikirim sebelum barrier tidak dapat ditarik kembali.

**AC:** (1) old socket tidak menerima event private yang diotorisasi setelah revocation barrier; (2) active control browser tetap menerima dan public board nonmedis tetap; (3) subscribe/reconnect/stale signature/inactive user ditolak server; (4) seluruh channel users notifications terlindungi, bukan queue saja; (5) control/API failure dan DB failure tidak membuka delivery private atau menghapus editor; (6) hook perubahan role/permission bekerja tanpa permission editor; (7) cross-tenant dan signature tamper ditolak; (8) SLA acknowledgement dan state pending sesuai PR-01/T. PR-01 tetap OPEN sampai S2 nyata lulus.

**Regression:** backend auth/version/after-commit/rollback/role-cache tests, JS reconnect/editor-preservation, Playwright + Reverb + worker nyata pada DB testing terisolasi. Dua browser berwenang lalu satu revoked; aksi check-in baru harus terbukti memicu QueueUpdated dan diterima kontrol, nol event private pada revoked. Ulangi deactivate, permission detach, syncRoles, multi-tab, notification, buffered job, stale subscribe dan reactivation. Backend fake dan client-only disconnect tidak cukup. Tidak menjalankan beban atau mengubah package/vendor untuk spike.
