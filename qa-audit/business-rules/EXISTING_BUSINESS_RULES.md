# ClinicOS — Register aturan bisnis terkini

Tanggal analisis: **15 November 2026, Asia/Bangkok**. Analisis statis checkout, schema migration, UI, service, policy dan laporan verifikasi terakhir. Tidak membaca database pengguna, menjalankan aplikasi/migration atau mengulang regression. **EXISTING berarti terbukti pada source, bukan pengesahan bahwa kebijakannya benar secara klinis.** Bukti runtime yang disebut adalah historis [post-S2](../post-s2-verification/FINAL_VERIFICATION_REPORT.md).

Klasifikasi: **EXISTING** diterapkan; **INCONSISTENT** berbeda lintas jalur/kontrak; **MISSING** kemampuan yang dibutuhkan workflow target belum tersedia; **PROPOSED** rekomendasi; **DECISION REQUIRED** kebijakan belum disetujui; **OPTIONAL** di luar scope awal. Capability MISSING tidak memberi izin memilih kebijakannya. Rule IDs ER hanya indeks analisis, bukan PG baru. ID keputusan tetap D-01–D-20.

## Katalog bukti source

Kode E merujuk file dan class; method spesifik disebut pada baris aturan. Untuk enum/migration/route/Blade, deklarasi adalah bukti dan tidak dibuatkan method fiktif.

| Bukti | File / class |
| --- | --- |
| E01 | [ClinicRegistrationService](../../app/Services/ClinicRegistrationService.php), [Onboarding ClinicController](../../app/Http/Controllers/Onboarding/ClinicController.php), [StoreOnboardingClinicRequest](../../app/Http/Requests/StoreOnboardingClinicRequest.php) |
| E02 | [register-clinic Volt](../../resources/views/livewire/pages/auth/register-clinic.blade.php), [register pasien](../../resources/views/livewire/pages/auth/register.blade.php), [RegistrationRateLimiter](../../app/Services/RegistrationRateLimiter.php) |
| E03 | [User](../../app/Models/User.php), [Clinic](../../app/Models/Clinic.php), [EnsureOnboardingComplete](../../app/Http/Middleware/EnsureOnboardingComplete.php), [EnsureClinicOwner](../../app/Http/Middleware/EnsureClinicOwner.php) |
| E04 | [RoleAndPermissionSeeder](../../database/seeders/RoleAndPermissionSeeder.php), [routes/web.php](../../routes/web.php), [bootstrap/app.php](../../bootstrap/app.php) |
| E05 | [UserPolicy](../../app/Policies/UserPolicy.php), [ClinicPolicy](../../app/Policies/ClinicPolicy.php), [PatientPolicy](../../app/Policies/PatientPolicy.php), [AppointmentPolicy](../../app/Policies/AppointmentPolicy.php) |
| E06 | [StaffAccountService](../../app/Services/StaffAccountService.php), [StoreStaffRequest](../../app/Http/Requests/Admin/StoreStaffRequest.php), [UpdateStaffRequest](../../app/Http/Requests/Admin/UpdateStaffRequest.php) |
| E07 | [BookingService](../../app/Services/BookingService.php), [StorePublicBookingRequest](../../app/Http/Requests/StorePublicBookingRequest.php), [StoreAppointmentRequest](../../app/Http/Requests/Receptionist/StoreAppointmentRequest.php) |
| E08 | [DoctorScheduleController](../../app/Http/Controllers/Admin/DoctorScheduleController.php), [StoreDoctorScheduleRequest](../../app/Http/Requests/StoreDoctorScheduleRequest.php), [UpdateDoctorScheduleRequest](../../app/Http/Requests/UpdateDoctorScheduleRequest.php) |
| E09 | [ClinicController](../../app/Http/Controllers/Admin/ClinicController.php), [UpdateClinicRequest](../../app/Http/Requests/UpdateClinicRequest.php), [Service request](../../app/Http/Requests/StoreServiceRequest.php) |
| E10 | [PatientController](../../app/Http/Controllers/Receptionist/PatientController.php), [Receptionist AppointmentController](../../app/Http/Controllers/Receptionist/AppointmentController.php), [Patient AppointmentController](../../app/Http/Controllers/Patient/AppointmentController.php), [PublicBookingController](../../app/Http/Controllers/PublicBookingController.php) |
| E11 | [PatientDuplicateService](../../app/Services/PatientDuplicateService.php), [PhoneNumber](../../app/Rules/PhoneNumber.php), [Patient](../../app/Models/Patient.php), [MR number generator](../../app/Services/MedicalRecordNumberGenerator.php) |
| E12 | [QueueWorkflowService](../../app/Services/QueueWorkflowService.php), [QueueController](../../app/Http/Controllers/QueueController.php), [QueuePolicy](../../app/Policies/QueuePolicy.php) |
| E13 | [VisitWorkflowService](../../app/Services/VisitWorkflowService.php), [VisitPolicy](../../app/Policies/VisitPolicy.php), [MedicalRecordPolicy](../../app/Policies/MedicalRecordPolicy.php), [VisitController](../../app/Http/Controllers/Doctor/VisitController.php) |
| E14 | [SaveMedicalRecordRequest](../../app/Http/Requests/Doctor/SaveMedicalRecordRequest.php), [CompleteVisitRequest](../../app/Http/Requests/Doctor/CompleteVisitRequest.php), [CorrectMedicalRecordRequest](../../app/Http/Requests/Doctor/CorrectMedicalRecordRequest.php), [SavePatientAllergyRequest](../../app/Http/Requests/Doctor/SavePatientAllergyRequest.php) |
| E15 | [editor visit](../../resources/views/doctor/visits/show.blade.php), [RmeAutosave](../../resources/js/rme-autosave.js), [PatientAllergy](../../app/Models/PatientAllergy.php) |
| E16 | [SubscriptionService](../../app/Services/SubscriptionService.php), [EnsureSubscriptionWritable](../../app/Http/Middleware/EnsureSubscriptionWritable.php), [SubscriptionPlanSeeder](../../database/seeders/SubscriptionPlanSeeder.php) |
| E17 | [AddonService](../../app/Services/AddonService.php), [SubscriptionPeriod](../../app/Models/SubscriptionPeriod.php) |
| E18 | [DokuPaymentService](../../app/Services/DokuPaymentService.php), [DokuClient](../../app/Services/DokuClient.php), [DokuNotificationController](../../app/Http/Controllers/DokuNotificationController.php) |
| E19 | [ClinicNotificationService](../../app/Services/ClinicNotificationService.php), [DeliverClinicNotification](../../app/Jobs/DeliverClinicNotification.php), [NotificationController](../../app/Http/Controllers/NotificationController.php), [realtime JS](../../resources/js/realtime.js) |
| E20 | [AuthorizationContext](../../app/Services/Realtime/AuthorizationContext.php), [Broadcaster](../../app/Services/Realtime/Broadcaster.php), [PrivateChannel](../../app/Services/Realtime/PrivateChannel.php), [ChannelManager](../../app/Services/Realtime/ChannelManager.php) |
| E21 | [AccessMutations](../../app/Services/Realtime/AccessMutations.php), [DeliveryFence](../../app/Services/Realtime/DeliveryFence.php), [ControlChannel](../../app/Services/Realtime/ControlChannel.php), [RevokeRealtimeAccess](../../app/Jobs/RevokeRealtimeAccess.php), [SessionGrants](../../app/Services/Realtime/SessionGrants.php) |
| E22 | [QueueEtaService](../../app/Services/QueueEtaService.php), [QueueUpdated](../../app/Events/QueueUpdated.php), [PublicQueueController](../../app/Http/Controllers/PublicQueueController.php) |
| E23 | [AuditLogger](../../app/Services/AuditLogger.php), [Admin DashboardController](../../app/Http/Controllers/Admin/DashboardController.php), [Platform TenantController](../../app/Http/Controllers/Platform/TenantController.php) |
| E24 | [delete-user Volt](../../resources/views/livewire/profile/delete-user-form.blade.php), [database config](../../config/database.php), [console schedule](../../routes/console.php) |
| E25 | [appointment schema](../../database/migrations/2026_10_04_081712_create_appointments_table.php), [queue schema](../../database/migrations/2026_10_04_081713_create_queues_table.php), [visit schema](../../database/migrations/2026_10_04_102739_create_visits_table.php), [patient schema](../../database/migrations/2026_10_04_081710_create_patients_table.php) — anonymous migration `up()` |
| E26 | [QueueStatus](../../app/QueueStatus.php), [AppointmentStatus](../../app/AppointmentStatus.php), [VisitStatus](../../app/VisitStatus.php), [MedicalRecordStatus](../../app/MedicalRecordStatus.php), [SubscriptionStatus](../../app/SubscriptionStatus.php), [DentalTooth](../../app/DentalTooth.php), [DentalCondition](../../app/DentalCondition.php) |

## 1. Registrasi, onboarding, klinik

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-01 | EXISTING | Owner mendaftar sebagai `clinic_owner` dengan role Clinic Admin; pasien sebagai Patient. Pemilik bukan role keenam. Email/terms/password mengikuti form auth dan event Registered. | E02 anonymous Volt `register`; E03 `User::isClinicOwner` |
| ER-02 | EXISTING | Registrasi dibatasi lima percobaan per email+IP dalam window limiter 60 detik; bukan bukti verifikasi identitas pasien. | E02 `RegistrationRateLimiter::hit` |
| ER-03 | EXISTING | Onboarding owner memerlukan auth+verified; tanpa clinic diarahkan setup, tanpa subscription diarahkan plans. Bukan gate wajib subscription bagi seluruh user legacy. | E03 `EnsureOnboardingComplete::handle`; E04 named onboarding routes |
| ER-04 | EXISTING | Clinic/settings dan hubungan owner dibuat transaksional; satu owner–satu clinic melalui unique ownership, satu subscription per clinic. Tidak ada branch entity. | E01 `create/store`; E03 relasi; [ownership migration `up`](../../database/migrations/2026_10_15_085858_add_owner_user_id_to_clinics_table.php) |
| ER-05 | EXISTING | Slug unik, reserved names ditolak; jam H:i mulai < selesai; timezone UI dibatasi WIB/WITA/WIT; prefix antrean alfanumerik maks.5. Overnight clinic hours tidak didukung form sekarang. | E09 `UpdateClinicRequest::rules` |
| ER-06 | EXISTING | Public clinic subdomain `{clinic:slug}` pada base domain; booking memakai route base-domain ber-`clinic/{slug}`. Guest status/cancel token tetap ada; bukan discovery marketplace. | E04 route declarations; E10 `PublicBookingController::status/cancel` |
| ER-07 | INCONSISTENT | `clinic.is_active=false` menutup public/new booking, tetapi bukan suspend menyeluruh queue/visit/admin; tidak ada satu lifecycle closure contract yang mencakup seluruh jalur. | E07 `book`; E10 public `create`; E12/E13; D-11 |

## 2. Ownership, role, permission

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-08 | EXISTING | Lima role tersimpan; izin seeder bukan izin akhir: route role + permission/policy + tenant/resource ownership tetap berlaku. | E04 `RoleAndPermissionSeeder::run`; E05; E12/E13 policies |
| ER-09 | EXISTING | Clinic Admin mengatur metadata klinik/staf/jadwal/layanan; tidak otomatis membaca/menulis RME. Platform hanya metadata tenant/komersial, tanpa impersonation medis. | E04 routes; E05; E13 `view/update` |
| ER-10 | INCONSISTENT | Owner commercial memakai `account_type`; payment controller membandingkan clinic_id, tidak membandingkan clinics.owner_user_id. Owner FK dan owner flag perlu tetap konsisten; isClinicOwner saja bukan bukti kepemilikan resource arbitrary. | E03; E04 subscription routes; [SubscriptionPaymentController](../../app/Http/Controllers/SubscriptionPaymentController.php) `authorizeOwnerPayment`; C-21 |
| ER-11 | EXISTING | Dokter mengelola visit/RME penugasannya; history editor difilter dokter sama. Role permission `visit.view` admin/rec tidak membuka route isi medis. | E13 `VisitController::show`, policies; E04 doctor group |
| ER-12 | EXISTING | Pasien melihat appointment yang `patient.user_id` miliknya; staff query dibatasi clinic, dokter dibatasi assignment pada resource yang relevan. | E05 `AppointmentPolicy::viewOwn/cancelOwn`; E10 patient `index/show` |
| ER-13 | EXISTING | Hanya Patient memiliki ClinicScope; ketika tidak ada logged-in clinic context, itu bukan batas isolasi otomatis. Console/job/Platform/guest perlu filter eksplisit. | [ClinicScope::apply](../../app/Models/Scopes/ClinicScope.php); E07 `resolvePatient`; E19 `handle` |

## 3. Dokter, staf, layanan dan jadwal

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-14 | EXISTING | Provisioning UI/service hanya Doctor/Receptionist, bukan delegated Clinic Admin; create aktif dan seat diperiksa dengan clinic lock. Email baru/berubah memerlukan verification. | E05 `UserPolicy::create`; E06 `create/update` |
| ER-15 | EXISTING | Reactivation account memeriksa seat; status doctor mengikuti status account melalui StaffAccountService. Password reset/email/status terkait memicu security epoch S2. | E06 `update`; E03 `User::save` |
| ER-16 | EXISTING | Dokter, service dan schedule harus valid, aktif, satu clinic dan sesuai layanan; related user harus active. Query book tidak mensyaratkan role Doctor/email verified, sehingga bookable tidak identik login-ready. | E07 `book`; C-22 |
| ER-17 | EXISTING | Weekly day 0–6, H:i, quota 1–500; overlap dicek per dokter/day dengan `start<otherEnd && end>otherStart`, sehingga interval berdampingan boleh. | E08 `StoreDoctorScheduleRequest::rules/withValidator` |
| ER-18 | EXISTING | Jika schedule pernah punya appointment/queue, doctor/day/start/end dibekukan; hanya quota/active masih bisa diubah. Bukan hanya booking masa depan. | E08 `DoctorScheduleController::update`; DFU-02 |
| ER-19 | INCONSISTENT | Penggantian sesi lama diarahkan membuat sesi baru, tetapi overlap validator turut menghitung schedule inactive, dan unique tuple tetap ada. Replacement weekly yang overlap perlu aturan version/effectivity, bukan bypass diam-diam. | E08 validator; [schedule migration `up`](../../database/migrations/2026_10_04_072453_create_doctor_schedules_table.php); D-02 |
| ER-20 | EXISTING | Jam klinik berubah tidak memindahkan histori; booking baru ditolak jika sesi di luar opening/closing terbaru, batas equality diizinkan. | E09 `update`; E07 `validateAppointmentDate`; CCL-05 |
| ER-21 | MISSING | Tidak ada date exception/cuti/dampak terkelola untuk dokter berhalangan; mengubah weekly schedule bukan solusi appointment existing. | E08/E07; route/model/schema search; PG-02/D-02 |

## 4. Pasien dan identitas

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-22 | EXISTING | Profile per clinic; unique clinic+RM dan clinic+user. RM berasal dari clinic ID dan patient ID, bukan NIK/telepon. NIK unik diperiksa pada staff create/update request, bukan universal bukti identitas. | E25 patient `up`; E11 `generate`; StorePatientRequest `rules` |
| ER-23 | EXISTING | Phone syntax mengizinkan digits, leading+, separator; storage Patient menghapus separator, matcher menyetarakan 62/0 untuk kandidat. Bukan canonical E.164/OTP dan nomor keluarga tidak diwajibkan unik. | E11 `PhoneNumber::validate/normalize`, `Patient::phone`, matcher |
| ER-24 | EXISTING | Duplicate candidate berdasarkan NIK ATAU nama+birthdate ATAU phone; hasil hanya kandidat untuk review, bukan konfirmasi orang yang sama. | E11 `matchingQuery/assertReviewed` |
| ER-25 | EXISTING | Paging delapan kandidat dengan cursor encrypted terikat tenant/kriteria/snapshot, expiry 15 menit; stale/foreign/tamper ditolak; review token terpisah tetap wajib untuk create staf bila kandidat ada. | E11 `candidatePage/reviewToken/assertReviewed`; PG-17 |
| ER-26 | EXISTING | Prefill inactive tenant sendiri menuju profil dengan alasan; foreign/missing ditolak. Explicit `patient_id` inactive tidak fallback menjadi orang baru atau auto-reactivate. | E10 receptionist `create`; E07 `resolvePatient`; CCL-04 |
| ER-27 | INCONSISTENT | Staff existing selection mensyaratkan active; self-book pasien yang sudah linked ditemukan tanpa active filter dan dapat dibooking sambil profile tetap inactive. Makna nonaktif belum tunggal. | E07 `resolvePatient` kedua cabang; D-06/PG-06 |
| ER-28 | EXISTING | Public booking UI sekarang auth+verified Patient/account_type Patient. Branch guest `book(...actor=null)` masih di service, tetapi bukan endpoint guest booking baru yang terbuka. Legacy token holder dapat status/cancel sesuai batas. | E04 public booking routes; E07/E10 |
| ER-29 | EXISTING | Tidak auto-merge/link berdasarkan phone/NIK; profile inactive/histori tidak dihapus karena review kandidat. Staff dapat membuat orang berbeda setelah review yang valid. | E11 `assertReviewed`; E10 `store/update`; PG-17 |

## 5. Booking, kuota dan perubahan

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-30 | EXISTING | Book mengunci clinic dan schedule; patient bila baru, appointment dan queue dibuat satu transaksi retry3, UUID unik, service name/price snapshot, nomor queue max+1 per session/date. | E07 `book`; E25 unique constraints |
| ER-31 | EXISTING | Tanggal clinic-local hari ini s.d.+30, sesuai weekday; same-day masih diterima sampai akhir sesi. Appointment menunjuk tanggal+sesi, bukan jam individual pasien; partial blackout tidak dapat dipetakan ke exact patient slot. | E07 `validateAppointmentDate/book`; E25 appointment schema |
| ER-32 | EXISTING | Kapasitas sesi menghitung semua appointment non-CANCELLED, termasuk NO_SHOW/COMPLETED. Membatalkan membebaskan reserved slot; no-show tidak otomatis demikian. | E07 `book` reserved count |
| ER-33 | EXISTING | Replay UUID pada clinic sama mengembalikan booking lama tanpa mengganti snapshot; filter active schedule dijalankan sebelum lookup replay. Changed payload/actor tidak dibandingkan dalam branch replay booking. UUID bukan pengganti resource authorization. | E07 `book/accessToken`; lihat konflik C-12 |
| ER-34 | EXISTING | Cancel BookingService hanya appointment BOOKED, queue nonterminal, tanpa Visit, sebelum session start; token mismatch404; transaksi menyelaraskan appointment+queue dan notifikasi. | E07 `cancel/tokenMatches`; DFU-01 |
| ER-35 | INCONSISTENT | Cancel queue staf menerima BOOKED/WAITING/CALLED/SKIPPED tanpa cutoff session-start; cancel appointment staf tetap memakai BookingService cutoff. Perbedaan wewenang belum dijelaskan sebagai kebijakan override eksplisit. | E12 `cancel`; E10 receptionist `cancel`; E07 `cancel`; D-01 |
| ER-36 | EXISTING | Subscription expired/suspended/seat overage menolak booking baru; kuota bulanan visit juga dapat menolak booking meski sesi masih ada. | E16 `canCreateBooking/assertBookingAvailable` |
| ER-37 | MISSING | Tidak ada atomic reschedule/reassignment command, old/new booking lineage atau preview capacity/price consequences. Cancel dan book terpisah bukan transaksi pengganti. | E07 public methods; E04 routes; PG-01/D-01 |
| ER-38 | DECISION REQUIRED | Cutoff perubahan, state asal, harga target, queue/check-in baru, alasan, Patient self-service dan apakah replacement menghormati grandfather quota perlu ditetapkan bersama. | ER-31–37; D-01; bukan persetujuan dari audit lama |

## 6. Check-in dan queue

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-39 | EXISTING | Check-in BOOKED→WAITING, timestamp; tidak membuat Visit. Harus hari queue menurut timezone clinic, tetapi tidak mengecek current opening/end hour. | E12 `checkIn/transition/ensureOperationalDate` |
| ER-40 | EXISTING | Call WAITING→CALLED hanya FIFO queue_number per clinic/doctor/schedule/date; satu CALLED/IN_PROGRESS per sesi, schedule lock. Bukan satu active visit lintas seluruh sesi dokter. | E12 `call` |
| ER-41 | EXISTING | Skip CALLED→SKIPPED, return SKIPPED→WAITING pada hari queue; return mempertahankan queue_number, dapat kembali di depan nomor yang lebih tinggi. | E12 `skip/returnToWaiting/transition` |
| ER-42 | EXISTING | Staff cancel dari empat state pre-visit mengubah appointment dan queue menjadi CANCELLED; reason nullable max500. IN_PROGRESS/terminal ditolak. | E12 service/controller `cancel` |
| ER-43 | EXISTING | No-show dari BOOKED/WAITING/CALLED/SKIPPED setelah awal sesi; tidak mensyaratkan akhir sesi atau hari sama. Appointment NO_SHOW dan queue NO_SHOW, tanpa Visit/RME baru. | E12 `markNoShow` |
| ER-44 | EXISTING | Check-in/cancel/no-show memerlukan queue.manage (admin/rec); dokter punya call/skip/return dan start miliknya, bukan semua aksi staf. | E12 policy/controller; E04 routes/seeder |
| ER-45 | EXISTING | Terminal queue = COMPLETED/CANCELLED/NO_SHOW. SKIPPED bukan terminal; tidak ada undo terminal generik. | E26 `QueueStatus::isTerminal`; E12 transition methods |
| ER-46 | EXISTING | ETA memakai maksimum20 visit selesai dokter; fallback durasi service, count orang di depan sesi sama; confidence berdasarkan jumlah sampel. Bukan jaminan appointment start atau triage klinis. | E22 `QueueEtaService::forAppointment/estimate` |
| ER-47 | DECISION REQUIRED | Aturan late check-in, recall priority, no-show grace, overlap active lintas sesi dan pelepasan slot saat pause perlu diputuskan jika PG-01–03 mengubah perilaku. | ER-31/39–43; D-01/02/03, tidak menjadi PG tambahan |

## 7. Visit dan RME

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-48 | EXISTING | Start hanya CALLED, dokter assigned, hari queue dan entitlement/quota sah; existing visit dikembalikan setelah ensureDoctor, tidak membuat kedua. | E13 `start`; E25 unique visit.appointment_id |
| ER-49 | EXISTING | Saat start: visit IN_PROGRESS, RME DRAFT, queue IN_PROGRESS; appointment tetap BOOKED sampai terminal. Jangan menambahkan status appointment IN_PROGRESS seolah sudah ada. | E13 `start`; E26 enums |
| ER-50 | EXISTING | Complete oleh dokter assigned mengubah RME FINAL + visit/appointment/queue COMPLETED atomik; complete yang sudah selesai mengembalikan existing. Validasi request tetap berjalan sebelum service. | E13 `complete`; E14 `CompleteVisitRequest::rules` |
| ER-51 | MISSING | Tidak ada PAUSED/resume/INTERRUPTED terminal. Browser terputus bukan pause domain; draft tetap dan visit dapat memblok sesi. | E26 VisitStatus/QueueStatus; E13 public methods; PG-03 |
| ER-52 | DECISION REQUIRED | Recovery dokter tidak tersedia dan handover author/responsibility/access bukan overwrite doctor_id; satu visit per appointment membatasi model episode lanjutan. | E13 `ensureDoctor`; E25 visit `up`; D-03 |
| ER-53 | EXISTING | Draft save hanya IN_PROGRESS+DRAFT; final edit langsung ditolak. RME version dan hash allergy terkini wajib; stale 409 sebelum overwrite. | E13 `saveDraft/syncRecord`; E14 rules |
| ER-54 | EXISTING | Complete wajib subjective, assessment, plan; data vital/diagnosis tidak semuanya wajib. Ini validation contract software, bukan standar minimal klinis universal. | E14 `CompleteVisitRequest::rules` |
| ER-55 | EXISTING | Koreksi hanya completed visit, dokter assigned, alasan10–500 karakter; before/after snapshot dan actor/time; status tetap FINAL, tidak reopened. | E13 `correct/snapshot`; E14 correct rules |
| ER-56 | EXISTING | Autosave nyata debounce1.200ms, satu request in-flight, response version memperbarui form; 409/401/403/419 memblok automatic overwrite/retry sesuai state. | E15 `RmeAutosave::flush/submit`; E13 controller `save` |
| ER-57 | EXISTING | Photos/removal tidak ikut autosave; manual submit menyimpan. Draft/file pending tetap di editor saat error; tidak ada janji survive reload atau offline browser close. | E15 `flush/hasPendingPhotos/unsaved`; CCL-02 |
| ER-58 | EXISTING | Foto private disk local, endpoint policy, no-store; upload rollback dibersihkan; removed draft files dihapus sesudah commit, revisi final mempertahankan file historis. | E13 `syncRecord`, `VisitController::photo` |
| ER-59 | EXISTING | Upload per request max10 image jpg/jpeg/png/webp max5MB; bukan batas kumulatif seluruh lifetime record atau pemeriksaan malware tersertifikasi. | E14 `SaveMedicalRecordRequest::rules` |
| ER-60 | EXISTING | RME/visit policy melarang delete/forceDelete; history revision dibaca melalui resource ber-policy. Schema cascade clinic tetap ada, bukan izin legal hapus clinic/history. | E13 policies; E25 migrations |
| ER-61 | EXISTING | History editor hanya sepuluh visit terdahulu pasien oleh dokter sama; dokter pengganti tidak otomatis punya hak baca medical record dokter lama. | E13 `VisitController::show`; policies |
| ER-62 | DECISION REQUIRED | Pause/abort completion, medical document release, signature/identity attestation dan retensi/recovery klinis memerlukan penanggung jawab klinis; source validation bukan otoritas klinis. | D-03/07/13/14; [CLINICAL_WORKFLOW_RULES](CLINICAL_WORKFLOW_RULES.md) |

## 8. Diagnosis, alergi, resep dan odontogram

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-63 | EXISTING | Diagnosis/treatment terstruktur kini ada di editor, bukan backend saja. Primary diagnosis pertama ditetapkan bila tak ada pilihan; array yang dikirim mengganti set; omission menjaga set existing untuk kedua collection. | E15 editor `diagnoses/treatments`; E13 `syncRecord` |
| ER-64 | EXISTING | Vital ranges, jumlah items, free-text medication/dose/frequency/quantity divalidasi; tidak ada drug interaction checker atau otomatis menentukan resep aman. | E14 rules; E15 editor |
| ER-65 | EXISTING | Allergy hanya dokter assigned pada visit IN_PROGRESS; patient locked; clinical change membuat record versi baru dan menonaktifkan versi lama; bukan hard delete. | E13 `saveAllergy`; E14 SavePatientAllergyRequest |
| ER-66 | EXISTING | Active critical allergy menuntut acknowledgement bila ada medicine item, actor/time tercatat; allergy hash diperiksa lagi saat save/final. | E11 `criticalAllergies/allergyVersion`; E13 `syncRecord` |
| ER-67 | INCONSISTENT | Allergy endpoint tidak memakai expected-parent/lineage version. Dua edit terhadap old record dapat menyimpan dua active successor; RME version/hash tidak menentukan allergy mana yang current. | E13 `saveAllergy` vs `syncRecord`; PG-13/D-13 |
| ER-68 | EXISTING | Odontogram persistent, nomor FDI32 dari enum, kondisi enum, unik per request; disimpan terurut tooth_number. General RME terpisah dari hak dental edit. | E14 rules; E13 `syncRecord`; E15 show |
| ER-69 | EXISTING | Edit dental memerlukan current paid Individual/Team period source payment, paid Payment dan active addon period yang sama. Manual grant/trial saja tidak memenuhi syarat; historical dental masih terbaca sesuai clinical policy. | E17 `currentPaidPeriod/canEdit`; E13 show/sync |
| ER-70 | DECISION REQUIRED | Ketika add-on habis di tengah pelayanan, general dapat selesai namun dental baru tidak boleh ditulis. Perlu SOP penyimpanan/handoff dental yang belum tersimpan; bukan alasan membuka entitlement otomatis. | E16 exception; E17; D-03/07/16 |

## 9. Dokumen dan follow-up

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-71 | MISSING | Belum ada issued clinical document domain/route produksi dengan nomor, revision/penerbit, release dan recall. Preview resep di editor/local preview bukan issued document pasien. | E15; E04 preview local/testing dan absence print/export route; PG-07 |
| ER-72 | MISSING | Belum ada follow-up task linked, owner/status/due window, book/complete/cancel trace. SOAP plan free text dan appointment baru tidak menutup loop follow-up. | E13 syncRecord; E25 model/schema; PG-04 |
| ER-73 | OPTIONAL | Full medical export, remote messages, signature provider, portal full SOAP, billing receipt bukan otomatis cakupan dokumen minimum. | E04/E15; D-05/07/19 |

## 10. Subscription, add-on, histori komersial

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-74 | EXISTING | Seeder reference: Free1 doctor/1 receptionist/50 monthly visits; Individual1/3/unlimited visits; Team unlimited. Harga/duration dari katalog aktif; bukan pembacaan nilai DB pengguna. | E16 seeder `run` |
| ER-75 | EXISTING | Trial Team sekali sebelum paid/manual/legacy history; end memakai kalender lokal helper dengan14 hari lalu batas midnight berikutnya, bukan janji tepat336 jam. | E16 `startTrial/endAfterDays` |
| ER-76 | EXISTING | Effective status berdasarkan periode `[starts_at,ends_at)`, revoked_at, suspended precedence; trial selesai→Free, paid selesai→Expired. Cache Subscription dapat direfresh, period history tetap. | E16 `statusAt/refreshStatus/periodAt`; CCL-06 |
| ER-77 | EXISTING | Null subscription tetap diizinkan service untuk legacy; owner onboarding mencegah owner baru melewati setup. Jangan menyimpulkan global no-subscription denial. | E16 `operationalBlockReason/canCreateBooking`; E03 onboarding |
| ER-78 | EXISTING | Monthly quota menghitung **visit started_at** di bulan clinic-local, semua status, bukan booked/paid/completed. Appointment yang dibuat sebelum quota tercapai punya exception start tertentu. | E16 `visitsInMonth/assertVisitCanStart` |
| ER-79 | EXISTING | Active Doctor/Receptionist seats dihitung; overage memblok writes. Owner dapat deactivation-only untuk recovery; ongoing IN_PROGRESS save/complete/allergy diizinkan saat read-only, koreksi completed tidak dikecualikan. | E16 `activeSeatCount`; middleware `canFinishVisit/canDeactivateSeat`; E06 update |
| ER-80 | EXISTING | Grant UUID mengikat actor/clinic/plan/duration/reason; sama replay satu period, beda maksud ditolak; active catalog wajib. Upgrade/same rank append setelah latest end, downgrade lower-rank dapat revoke current/future sekaligus. | E16 `grant/nextPeriodStart/revokeFuturePeriods`; DFU-12/PG-18 |
| ER-81 | EXISTING | Paket payment diaktifkan dari signed webhook atau reconciliation tervalidasi; event Request-ID dedup, invoice/amount/items/request/customer/status diperiksa. Browser redirect bukan activation. | E18 `verifyNotification`, `__invoke`, `processProviderPayload/activatePaid` |
| ER-82 | EXISTING | Paid/refunded terminal handling mencegah event out-of-order; late SUCCESS atas EXPIRED menjadi REVIEW; manual refund mencatat refund, bukan mengirim uang provider. | E18 `processProviderPayload/recordManualRefund` |
| ER-83 | INCONSISTENT | Refund mencabut add-on, tetapi tidak otomatis merevoke main SubscriptionPeriod; main status vs financial state perlu kebijakan terpisah. Global downgradeToFree bukan single-grant undo dan dapat memotong period sah lain. | E18 refund; E16 `periodAt/downgradeToFree`; D-10 |
| ER-84 | INCONSISTENT | Katalog menandai realtime/management_dashboard premium; jalur akses actual memakai role/permission/tenant, bukan gate kedua flags ini. Dental gate berbeda dan benar-benar enforced. | E16 seeder; E04 routes; E20; D-16 |

## 11. Notifikasi, realtime dan recovery

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-85 | EXISTING | Notification DB dan jobs masuk transaksi bisnis; target pasien linked/dokter resource yang cocok. Failed enqueue rollback bisnis; delivery failure setelah commit bukan alasan rollback pelayanan. | E19 `queueStatusChanged/notify/databaseQueue`; delivery tests historis |
| ER-86 | EXISTING | Delivery mengecek kembali resource/tenant, active/verified/role; stored notification history tidak dihapus karena akses delivery dicabut. Retry memakai notification ID sama; client dedup. | E19 `DeliverClinicNotification::handle`, JS subscribeNotifications |
| ER-87 | EXISTING | Private queue clinic dan own-user notifications signed user/epoch/tenant/app/channel/session; fresh auth+delivery, stale/foreign denied. Public queue board hanya enam field nonmedis. | E20 `decision/permits/subscribe/broadcast`; E22 QueueUpdated |
| ER-88 | EXISTING | Security epoch + request + database job atomik pada supported User/Role/Permission APIs. Old socket floor+active writes+actual close mendahului ACK; SDK200 saja tidak cukup. | E21 `change/record/send/advance/closed/broadcast`; PR-01 single-node |
| ER-89 | EXISTING | Logout ordinary mencabut satu durable session scope; password/status/role/perms bump user epoch. Grant tombstone mencegah delayed auth resurrect; rights HTTP tidak dipulihkan jika control gagal. | E21 SessionGrants; RealtimeServiceProvider logout; User save |
| ER-90 | MISSING | Near-turn reminder dan scheduled patient appointment reminder belum tersedia; scheduler existing mengurus subscription owner, bukan clinical follow-up. | E24 console declarations; QueueSubscriptionReminders `handle`; D-12 |
| ER-91 | EXISTING | Notification center hanya user sendiri; auth/revocation feedback tidak menghapus editor. Realtime refetch menjaga DOM/value/caret filter; status errors di luar refresh region. | E19 controller `index/read/readAll`; realtime JS; CCL-02/07 |

## 12. Aturan tambahan dan batas data

| ID | Status | Aturan actual dan kondisi | Bukti |
| --- | --- | --- | --- |
| ER-92 | EXISTING | Service-value dashboard = jumlah total_amount completed visits, bukan kas diterima. Unique patients berdasarkan appointment periode, bukan seluruh pasien yang pernah datang. | E23 AdminDashboardController `index`; D-05/08 |
| ER-93 | EXISTING | Calendar dates tidak diubah timezone; instants ditampilkan clinic-local dan filter periode dikonversi UTC. Tidak ada snapshot timezone/jam per seluruh histori appointment. | E07; E23 index; E15 UI; D-02 |
| ER-94 | EXISTING | Audit menyimpan actor/action/entity/metadata; clinical revision menyimpan snapshot khusus ber-policy. Audit DB bukan otomatis immutable/tamper-evident compliance store. | E23 `AuditLogger::recordForClinic/recordForPlatform`; E13 snapshot |
| ER-95 | EXISTING | Owner tidak dapat delete akun selama punya clinic; dokter/aktor yang terkait clinical history ditolak delete; pasien account deletion tidak sama dengan penghapusan clinical profile (user FK nullable). | E24 Volt `deleteUser`; E25 patient up |
| ER-96 | EXISTING | SQLite transaksi IMMEDIATE untuk mencegah queue read-snapshot upgrade race; MySQL berbeda. Queue at-least-once masih dapat menghasilkan raw frame ganda dengan ID sama. | E24 config; [post-S2 notification investigation](../post-s2-verification/NOTIFICATION_JOB_INVESTIGATION.md) |
| ER-97 | DECISION REQUIRED | Retention, patient access/document release, pemilik data saat tenant closure dan prosedur clinical emergency access belum ditetapkan lengkap oleh source. | ER-60/62/71/95; D-03/07/11/14 |
| ER-98 | OPTIONAL | Kasir, kiosk control, custom permission editor, multi-branch, inventory/claims/Edge/catalogue obat bukan otomatis requirement target clinical admin+SaaS satu lokasi. | D-05/17/18/19; domain/routes inventory |
| ER-99 | PROPOSED | Read-only impact preview, pesan alasan/cara pemulihan sesuai guard dan version conflict yang tidak menghapus input aman sebagai prinsip teknis; field privacy/aksi baru tetap gated. | ER-20/53/57; D-20; [dependencies](IMPLEMENTATION_DEPENDENCIES.md) |
| ER-100 | DECISION REQUIRED | Repair legacy harus berdasarkan diagnosis/fingerprint aktual dan persetujuan per kategori; analisis ini tidak membuktikan data pengguna rusak atau memberi izin mass repair. | E25 constraints + services; D-14 |

Semua rekomendasi, impact state, alternatif dan AC berada di [PROPOSED_BUSINESS_RULES](PROPOSED_BUSINESS_RULES.md), [DECISION_COMPARISON](DECISION_COMPARISON.md) dan [DECISIONS_FOR_OWNER](DECISIONS_FOR_OWNER.md). Tidak menyalin klaim lama bahwa autosave, structured diagnosis, alergi atau odontogram masih sekadar UI.
