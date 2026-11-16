# Feature test matrix

> **Status terbaru setelah P2, 11 November 2026:** Run terakhir: 363 PHP PASS (2.438 assertion), 7 JavaScript PASS dan 3 Chrome PASS; total 373, 0 FAIL/ERROR/SKIP. Seluruh 22 regression audit lama juga PASS. Bukti: evidence/p2-retest/php-all.xml dan node-all.txt. Lihat [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Riwayat laporan sebelum perbaikan P2

Bagian di bawah mempertahankan checkpoint P1 dan baseline audit; status/angka historis tidak menggantikan hasil terbaru di atas.

> **Pembaruan setelah perbaikan P1, 11 November 2026:** QA-01 - QA-04 sudah FIXED pada pengujian ulang; P2 belum diperbaiki. Hasil terbaru: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Dua bug staf baru QA-13/QA-14 dan risiko sesi setelah reset admin tetap terbuka.

## Run pengujian ulang terbaru

| Run | Kasus | PASS | FAIL | Bukti |
| --- | ---: | ---: | ---: | --- |
| PHP aplikasi | 315 | 315 | 0 | [hasil](evidence/p1-existing-php.txt) |
| PHP audit eksplisit | 22 | 15 | 7 | [hasil](evidence/p1-audit-php.txt) |
| Node unit/state | 7 | 7 | 0 | [hasil](evidence/p1-js.txt) |
| Chrome P1 dan staf | 1 | 1 | 0 | [hasil](evidence/p1-browser-run.txt); observasi sesi OPEN terpisah |
| Chrome workflow existing | 1 | 1 | 0 | [hasil](evidence/p1-existing-browsers.txt) |
| Chrome audit lima role | 1 | 0 | 1 | [hasil](evidence/p1-existing-browsers.txt); hanya QA-09 |
| Total unik | 347 | 339 | 8 | Penjumlahan run terpisah; 2.279 assertion PHP |

Sembilan kasus baru berada pada suite aplikasi default. Test reproduksi audit tetap dijalankan eksplisit; tidak ada test lama dihapus. Matriks source/baseline per fitur di bawah dipertahankan sebagai histori; delta fungsi dan peran tersedia pada [P1_FIX_REPORT.md](P1_FIX_REPORT.md).

## Baseline audit sebelum perbaikan

Bagian berikut mempertahankan hasil/source yang diamati saat audit awal. Angka, status FAIL P1 dan batas pada baseline ini bukan status terbaru; bukti lama tidak ditimpa.

Audit 11 November 2026. Status yang dipakai: **PASS**, **FAIL**, **BLOCKED**, **NOT TESTED**. PASS berlaku untuk assertion/skenario yang dieksekusi, bukan seluruh fitur. Source statis tidak dihitung sebagai eksekusi browser.

## Hasil run terbaru

| Run | Dieksekusi | PASS | FAIL | Bukti |
| --- | ---: | ---: | ---: | --- |
| PHPUnit existing, SQLite memory | 306 | 306 | 0 | phpunit.xml, phpunit.txt |
| PHPUnit regression audit | 20 | 12 | 8 | audit-regression.xml/txt |
| PHPUnit gabungan dengan pencatat route | 326 | 318 | 8 | phpunit-combined.xml/txt; 2.098 assertion |
| Node unit/state tests existing | 7 | 7 | 0 | node-tests.txt |
| Chrome workflow existing, opt-in diaktifkan | 1 | 1 | 0 | browser-existing.txt |
| Chrome regression audit | 1 | 0 | 1 | browser-regression.txt; dua kegagalan perilaku pada satu test |

**Jumlah automated test unik pada run gabungan + JavaScript/browser: 335; PASS 326, FAIL 9.** Run baseline/regression terpisah tidak dijumlahkan lagi. Test audit sengaja menegaskan perilaku yang diharapkan, sehingga tetap merah selama implementasi belum diperbaiki. Delapan assertion backend gagal mewakili tujuh kelompok bug; dua dataset nullable berbagi akar masalah.

Browser regression mencatat 56 langkah: 54 PASS, 2 FAIL (29 kunjungan menu, 20 pemeriksaan overflow dashboard, 5 logout, pilihan dokter publik, Escape). Langkah bukan test independen dan tidak ditambahkan ke angka 335. Interactive-browser.json adalah catatan eksperimen tambahan, termasuk lifecycle akun dan perjalanan lintas role; juga tidak ditambahkan agar tidak menghitung ulang cakupan yang sama.

## Pemetaan fitur dan jenis pemeriksaan

| Fitur | Kasus positif/aksi | Negatif/integritas yang benar-benar diperiksa | Batas |
| --- | --- | --- | --- |
| Auth/profil | PASS login/logout lima role, register, verify/reset/password/profile melalui test | PASS wrong password, inactive session, portal salah, rate limit, signed token, XSS nama | FAIL owner deletion; transport email/Google BLOCKED |
| Onboarding | PASS resume, clinic/settings, Free/trial dan katalog | PASS one owner/clinic, terms, akses sebelum verify | FAIL add-on sebelum klinik; full email journey BLOCKED |
| Clinic/staff | PASS create/edit clinic, create staff, password, service assignment, status gate | PASS foreign tenant edit, invalid role/service/seat | Semua edge logo/role transition/deactivate cascades NOT TESTED |
| Schedule/service | PASS create, overlap rejection, create service dan harga | PASS jam/tenant terpilih; FAIL reassignment sesi terpakai | Semua update/quota race/jam berubah NOT TESTED; MySQL concurrency BLOCKED |
| Patient/duplicates | PASS create/search/review/token/tenant, patient existing booking | PASS changed duplicate token dan foreign candidates ditolak | Semua edit/phone normalization/pagination extremes NOT TESTED |
| Booking | PASS endpoint atomik, idempotency, snapshot, invalid relasi, quota; PASS UI staf | PASS guest ditolak, salah sesi/layanan, status tanpa token, target subscription | FAIL UI pasien publik; MySQL parallel quota BLOCKED |
| Appointment/queue | PASS list/detail/check-in/call/skip/return/cancel/no-show; lintas role browser sampai final | PASS ownership, FIFO, duplicate/invalid transition, foreign doctor, data rollback | Semua filter/page/Back dan dua proses bersamaan belum dibuktikan |
| RME/autosave | PASS draft nested data, final, manual photo, structured editor, offline recovery, stale version dikirim | PASS foreign doctor, final draft edit, numeric validation, rollback DB | FAIL versi dihilangkan, null arrays, retention foto, orphan upload |
| Alergi/odontogram | PASS persistence, edit/deactivate history, critical acknowledgement, paid entitlement/read-only | PASS wrong tenant/Doctor/role, inactive addon, expiry/refund, malformed dental values | Versi alergi dihilangkan dan race dua request NOT TESTED |
| Resep/pasien | PASS incomplete draft dan final validation, preview, own finalized prescription | PASS draft/foreign prescriptions tidak muncul | Katalog/interaksi/cetak belum ada; medication safety klinis tidak disimpulkan |
| ETA/public queue | PASS riwayat/fallback/confidence, payload nonmedis, tanggal dan own tracker | PASS history dokter lain diabaikan, patient ownership | Accuracy/calibration/beban polling NOT TESTED |
| Notification/audit/jobs | PASS DB transaction, failure/retry, safe metadata, foreign notification denial, filter audit | PASS enqueue/storage failure rollback; 15 job lokal selesai dengan broadcaster null | Read-all ownership tambahan PASS; delivery Reverb/mail nyata BLOCKED |
| SaaS/payment/add-on/platform | PASS pricing snapshots, periods, refund, grants, expiry/seat exceptions, DOKU fake/dedup/reconcile | PASS invalid signature/amount/request/invoice/tenant, redirect bukan aktivasi | Checkout/reconcile nyata BLOCKED; katalog add-on update dan reconcile platform PENDING fake PASS; variasi UI lain NOT TESTED |
| UX/browser | PASS lima menu role, empat viewport dashboard; RME/booking staf/tracker existing responsive | FAIL public booking, Escape drawer, 7 accessible labels | Screen reader, seluruh halaman di seluruh viewport, soak NOT TESTED |

## Coverage yang dapat dihitung

Route recorder hanya menyimpan nama route, domain template, URI template, method, status, dan ID test. Ia tidak menyimpan URL konkret/query, token, request/response body, identitas pasien, atau isi medis. Matching memakai **nama route** ketika tersedia: registry dapat menulis `{clinic:slug}` sementara runtime menormalkannya menjadi `{clinic}`. Nama dan method mencegah dua route booking hilang dari hitungan.

Jumlah request dan coverage route akhir tersedia di [route-coverage-summary.json](evidence/route-coverage-summary.json). Request yang ditolak juga dihitung sebagai route dieksekusi; tidak otomatis berarti alur bisnis PASS. Angka ini bukan branch/line coverage dan bukan bukti semua parameter/role/status sudah diuji. Route belum teramati dicantumkan pada lampiran route di bawah dan SYSTEM_INVENTORY; sebagian GET juga dibuka di browser secara terpisah.

Execution coverage pada himpunan charter E2E yang sebanding: **23/25 (92%)**, terdiri dari 17 PASS, 6 FAIL, 2 BLOCKED; metode/batas tiap charter ada di E2E_WORKFLOW_REPORT. Seluruh 335 automated test dijalankan dengan pass rate **97,31%**. Delapan belas charter lanjutan di bawah tidak dijumlahkan dengan jumlah automated test karena granularitasnya berbeda. Tidak ada satu persentase yang diklaim mewakili seluruh kemungkinan perilaku ClinicOS.

Route coverage teramati adalah **108/117 baris (92,31%)**, termasuk 107/107 baris route aplikasi, dari 699 request. Semua alias method, parameter, role/status dan branch tetap belum lengkap. Event::fake pada test tertentu dapat menekan event recorder; status positif yang tercatat adalah bukti minimum. Ini bukan line coverage.

## Skenario lanjutan yang belum dieksekusi

| ID | Skenario | Status | Penyebab/batas |
| --- | --- | --- | --- |
| U01 | Booking dua proses pada kuota terakhir di MySQL | BLOCKED | MySQL lokal menolak koneksi; database aplikasi tidak diubah/diseed |
| U02 | Dua call/start/finalize/checkout bersamaan pada MySQL | BLOCKED | Sama; SQLite dan request berurutan tidak membuktikan row lock/deadlock |
| U03 | DOKU sandbox lengkap checkout → notify → reconcile → refund | BLOCKED | Tidak menggunakan kredensial/transaksi eksternal nyata; fake saja |
| U04 | Google OAuth nyata dan callback browser | BLOCKED | Provider/session eksternal tidak dijalankan; Socialite fake saja |
| U05 | SMTP verifikasi, reset, dan reminder sampai inbox | BLOCKED | Mail array/fake; tidak mengirim pesan eksternal |
| U06 | Reverb nyata lintas browser, putus/pulih, revoke channel aktif | BLOCKED | Tidak ada transport WebSocket aktif dalam harness; broadcaster null/fake |
| U07 | Staging/production HTTPS, domain, deployment, restore backup | BLOCKED | Tidak mengakses atau mengubah deployment/data produksi |
| U08 | Pembaca layar dan perangkat sentuh fisik seluruh role | NOT TESTED | DOM/keyboard Chrome dan emulasi viewport saja |
| U09 | Soak beberapa jam/hari, suspend OS, expiry sesi alami | NOT TESTED | Sesi audit bukan pengujian endurance |
| U10 | Seluruh search/filter/sort/page, first/last/empty di semua list | NOT TESTED | Beberapa kasus existing dan menu smoke saja; sebagian list memang fixed sort |
| U11 | Seluruh input ekstrem, file limits, charset/collation, malformed payload | NOT TESTED | Validasi terpilih diuji; bukan fuzz menyeluruh |
| U12 | Seluruh form double click, refresh, Back/Forward, offline dan retry | NOT TESTED | Booking/idempotency dan RME offline diuji; tidak semua form |
| U13 | Setiap permission dicabut × role × resource/status, semua multi-role | NOT TESTED | 65 kombinasi endpoint baru, existing boundaries, dan satu live role change |
| U14 | Dampak semua deaktivasi/edit jam/dokter/layanan pada booking masa depan | NOT TESTED | Reassignment dokter terbukti; seluruh kombinasi lain belum dijalankan |
| U15 | Upgrade/rollback migration MySQL pada data historis terisi | BLOCKED | Migrasi hanya pada SQLite fixture baru; tidak menyentuh DB aplikasi |
| U16 | Akurasi ETA dan latency/load pada penggunaan klinik nyata | NOT TESTED | Data sintetis; belum benchmark/conformance lapangan |
| U17 | Pentest/fuzz komprehensif, rate limit multi-worker, polyglot uploads | NOT TESTED | Audit terarah dan dependency scan; bukan penetrasi lengkap |
| U18 | Lifecycle delete dokter/pasien/SSO dan recovery seluruh peran | NOT TESTED | Owner deletion dan akun inactive diuji; matriks deletion lain belum lengkap |

## Hasil setiap automated test

Lampiran berikut dihasilkan dari JUnit run gabungan. Nama dataset disertakan; setiap kasus memiliki hasil aktual, bukan hasil historis.


| ID | Skenario PHPUnit (dataset termasuk) | Hasil | Test |
| --- | --- | --- | --- |
| PHP-001 | Tests.Feature.Admin.ClinicManagementTest :: test_clinic_admin_can_create_clinic_with_operating_settings | PASS | [tests/Feature/Admin/ClinicManagementTest.php](../tests/Feature/Admin/ClinicManagementTest.php) |
| PHP-002 | Tests.Feature.Admin.ClinicManagementTest :: test_clinic_admin_cannot_edit_another_clinic | PASS | [tests/Feature/Admin/ClinicManagementTest.php](../tests/Feature/Admin/ClinicManagementTest.php) |
| PHP-003 | Tests.Feature.Admin.ClinicManagementTest :: test_clinic_admin_can_remove_the_current_logo | PASS | [tests/Feature/Admin/ClinicManagementTest.php](../tests/Feature/Admin/ClinicManagementTest.php) |
| PHP-004 | Tests.Feature.Admin.DoctorScheduleManagementTest :: test_clinic_admin_can_create_schedule_within_operating_hours | PASS | [tests/Feature/Admin/DoctorScheduleManagementTest.php](../tests/Feature/Admin/DoctorScheduleManagementTest.php) |
| PHP-005 | Tests.Feature.Admin.DoctorScheduleManagementTest :: test_overlapping_schedule_is_rejected | PASS | [tests/Feature/Admin/DoctorScheduleManagementTest.php](../tests/Feature/Admin/DoctorScheduleManagementTest.php) |
| PHP-006 | Tests.Feature.Admin.ServiceManagementTest :: test_clinic_admin_can_create_service_for_own_clinic | PASS | [tests/Feature/Admin/ServiceManagementTest.php](../tests/Feature/Admin/ServiceManagementTest.php) |
| PHP-007 | Tests.Feature.Admin.ServiceManagementTest :: test_clinic_admin_cannot_edit_service_from_another_clinic | PASS | [tests/Feature/Admin/ServiceManagementTest.php](../tests/Feature/Admin/ServiceManagementTest.php) |
| PHP-008 | Tests.Feature.Admin.StaffManagementTest :: test_clinic_admin_can_create_doctor_and_assign_services | PASS | [tests/Feature/Admin/StaffManagementTest.php](../tests/Feature/Admin/StaffManagementTest.php) |
| PHP-009 | Tests.Feature.Admin.StaffManagementTest :: test_clinic_admin_can_change_staff_password | PASS | [tests/Feature/Admin/StaffManagementTest.php](../tests/Feature/Admin/StaffManagementTest.php) |
| PHP-010 | Tests.Feature.Admin.StaffManagementTest :: test_clinic_admin_cannot_edit_staff_from_another_clinic | PASS | [tests/Feature/Admin/StaffManagementTest.php](../tests/Feature/Admin/StaffManagementTest.php) |
| PHP-011 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_detail_and_cancel_require_ownership with data set "own without clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-012 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_detail_and_cancel_require_ownership with data set "own with clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-013 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_detail_and_cancel_require_ownership with data set "other patient without clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-014 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_detail_and_cancel_require_ownership with data set "other patient same clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-015 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_detail_and_cancel_require_ownership with data set "other clinic without account clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-016 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_detail_and_cancel_require_ownership with data set "other clinic with account clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-017 | Tests.Feature.AppointmentAuthorizationTest :: test_staff_endpoint_preserves_roles_and_tenant_boundaries with data set "receptionist own clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-018 | Tests.Feature.AppointmentAuthorizationTest :: test_staff_endpoint_preserves_roles_and_tenant_boundaries with data set "admin own clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-019 | Tests.Feature.AppointmentAuthorizationTest :: test_staff_endpoint_preserves_roles_and_tenant_boundaries with data set "receptionist other clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-020 | Tests.Feature.AppointmentAuthorizationTest :: test_staff_endpoint_preserves_roles_and_tenant_boundaries with data set "admin other clinic" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-021 | Tests.Feature.AppointmentAuthorizationTest :: test_staff_endpoint_preserves_roles_and_tenant_boundaries with data set "doctor has no receptionist endpoint" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-022 | Tests.Feature.AppointmentAuthorizationTest :: test_staff_endpoint_preserves_roles_and_tenant_boundaries with data set "platform has no receptionist endpoint" | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-023 | Tests.Feature.AppointmentAuthorizationTest :: test_multi_role_user_uses_ownership_on_patient_routes_and_staff_authority_on_staff_routes | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-024 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_cannot_cancel_another_patient_in_the_same_clinic_directly | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-025 | Tests.Feature.AppointmentAuthorizationTest :: test_repeated_patient_cancel_keeps_validation_contract_and_creates_no_second_effect | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-026 | Tests.Feature.AppointmentAuthorizationTest :: test_patient_ownership_does_not_override_revoked_permissions | PASS | [tests/Feature/AppointmentAuthorizationTest.php](../tests/Feature/AppointmentAuthorizationTest.php) |
| PHP-027 | Tests.Feature.AuditLogViewerTest :: test_admin_can_filter_only_their_clinics_safe_audit_data | PASS | [tests/Feature/AuditLogViewerTest.php](../tests/Feature/AuditLogViewerTest.php) |
| PHP-028 | Tests.Feature.AuditLogViewerTest :: test_non_admin_cannot_open_audit_viewer | PASS | [tests/Feature/AuditLogViewerTest.php](../tests/Feature/AuditLogViewerTest.php) |
| PHP-029 | Tests.Feature.Auth.AccountSecurityTest :: test_inactive_users_cannot_start_a_session_even_with_remember_me | PASS | [tests/Feature/Auth/AccountSecurityTest.php](../tests/Feature/Auth/AccountSecurityTest.php) |
| PHP-030 | Tests.Feature.Auth.AccountSecurityTest :: test_an_existing_session_is_invalidated_when_the_account_is_deactivated | PASS | [tests/Feature/Auth/AccountSecurityTest.php](../tests/Feature/Auth/AccountSecurityTest.php) |
| PHP-031 | Tests.Feature.Auth.AccountSecurityTest :: test_login_is_rate_limited_after_five_failed_attempts | PASS | [tests/Feature/Auth/AccountSecurityTest.php](../tests/Feature/Auth/AccountSecurityTest.php) |
| PHP-032 | Tests.Feature.Auth.AccountSecurityTest :: test_registration_errors_do_not_create_a_user_or_a_login_audit | PASS | [tests/Feature/Auth/AccountSecurityTest.php](../tests/Feature/Auth/AccountSecurityTest.php) |
| PHP-033 | Tests.Feature.Auth.AuthenticationAuditTest :: test_successful_login_records_the_actor_and_only_safe_metadata | PASS | [tests/Feature/Auth/AuthenticationAuditTest.php](../tests/Feature/Auth/AuthenticationAuditTest.php) |
| PHP-034 | Tests.Feature.Auth.AuthenticationAuditTest :: test_failed_login_does_not_record_a_successful_login | PASS | [tests/Feature/Auth/AuthenticationAuditTest.php](../tests/Feature/Auth/AuthenticationAuditTest.php) |
| PHP-035 | Tests.Feature.Auth.AuthenticationAuditTest :: test_logout_is_recorded_before_the_session_is_invalidated | PASS | [tests/Feature/Auth/AuthenticationAuditTest.php](../tests/Feature/Auth/AuthenticationAuditTest.php) |
| PHP-036 | Tests.Feature.Auth.AuthenticationAuditTest :: test_audit_history_is_retained_when_an_account_is_deleted | PASS | [tests/Feature/Auth/AuthenticationAuditTest.php](../tests/Feature/Auth/AuthenticationAuditTest.php) |
| PHP-037 | Tests.Feature.Auth.AuthenticationTest :: test_login_screen_can_be_rendered | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-038 | Tests.Feature.Auth.AuthenticationTest :: test_users_can_authenticate_using_the_login_screen | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-039 | Tests.Feature.Auth.AuthenticationTest :: test_users_can_not_authenticate_with_invalid_password | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-040 | Tests.Feature.Auth.AuthenticationTest :: test_admin_login_accepts_staff_and_hides_patient_google_login | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-041 | Tests.Feature.Auth.AuthenticationTest :: test_login_portals_reject_accounts_from_the_other_group | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-042 | Tests.Feature.Auth.AuthenticationTest :: test_owner_and_platform_admin_can_use_admin_login | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-043 | Tests.Feature.Auth.AuthenticationTest :: test_navigation_menu_can_be_rendered | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-044 | Tests.Feature.Auth.AuthenticationTest :: test_users_can_logout | PASS | [tests/Feature/Auth/AuthenticationTest.php](../tests/Feature/Auth/AuthenticationTest.php) |
| PHP-045 | Tests.Feature.Auth.EmailVerificationTest :: test_email_verification_screen_can_be_rendered | PASS | [tests/Feature/Auth/EmailVerificationTest.php](../tests/Feature/Auth/EmailVerificationTest.php) |
| PHP-046 | Tests.Feature.Auth.EmailVerificationTest :: test_email_can_be_verified | PASS | [tests/Feature/Auth/EmailVerificationTest.php](../tests/Feature/Auth/EmailVerificationTest.php) |
| PHP-047 | Tests.Feature.Auth.EmailVerificationTest :: test_email_is_not_verified_with_invalid_hash | PASS | [tests/Feature/Auth/EmailVerificationTest.php](../tests/Feature/Auth/EmailVerificationTest.php) |
| PHP-048 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_patient_authentication_pages_show_google_option | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-049 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_patient_can_be_redirected_to_google | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-050 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_verified_google_account_creates_and_logs_in_a_patient | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-051 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_account_links_an_existing_patient_by_verified_email | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-052 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_callback_returns_to_the_intended_page | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-053 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_registration_requires_terms_for_a_new_patient | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-054 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_login_rejects_non_patient_accounts | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-055 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_login_rejects_inactive_patients | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-056 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_login_rejects_an_unverified_email | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-057 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_login_rejects_conflicting_accounts | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-058 | Tests.Feature.Auth.GoogleAuthenticationTest :: test_google_provider_failure_does_not_create_a_user | PASS | [tests/Feature/Auth/GoogleAuthenticationTest.php](../tests/Feature/Auth/GoogleAuthenticationTest.php) |
| PHP-059 | Tests.Feature.Auth.PasswordConfirmationTest :: test_confirm_password_screen_can_be_rendered | PASS | [tests/Feature/Auth/PasswordConfirmationTest.php](../tests/Feature/Auth/PasswordConfirmationTest.php) |
| PHP-060 | Tests.Feature.Auth.PasswordConfirmationTest :: test_password_can_be_confirmed | PASS | [tests/Feature/Auth/PasswordConfirmationTest.php](../tests/Feature/Auth/PasswordConfirmationTest.php) |
| PHP-061 | Tests.Feature.Auth.PasswordConfirmationTest :: test_password_is_not_confirmed_with_invalid_password | PASS | [tests/Feature/Auth/PasswordConfirmationTest.php](../tests/Feature/Auth/PasswordConfirmationTest.php) |
| PHP-062 | Tests.Feature.Auth.PasswordResetTest :: test_reset_password_link_screen_can_be_rendered | PASS | [tests/Feature/Auth/PasswordResetTest.php](../tests/Feature/Auth/PasswordResetTest.php) |
| PHP-063 | Tests.Feature.Auth.PasswordResetTest :: test_reset_password_link_can_be_requested | PASS | [tests/Feature/Auth/PasswordResetTest.php](../tests/Feature/Auth/PasswordResetTest.php) |
| PHP-064 | Tests.Feature.Auth.PasswordResetTest :: test_reset_password_screen_can_be_rendered | PASS | [tests/Feature/Auth/PasswordResetTest.php](../tests/Feature/Auth/PasswordResetTest.php) |
| PHP-065 | Tests.Feature.Auth.PasswordResetTest :: test_password_can_be_reset_with_valid_token | PASS | [tests/Feature/Auth/PasswordResetTest.php](../tests/Feature/Auth/PasswordResetTest.php) |
| PHP-066 | Tests.Feature.Auth.PasswordUpdateTest :: test_password_can_be_updated | PASS | [tests/Feature/Auth/PasswordUpdateTest.php](../tests/Feature/Auth/PasswordUpdateTest.php) |
| PHP-067 | Tests.Feature.Auth.PasswordUpdateTest :: test_correct_password_must_be_provided_to_update_password | PASS | [tests/Feature/Auth/PasswordUpdateTest.php](../tests/Feature/Auth/PasswordUpdateTest.php) |
| PHP-068 | Tests.Feature.Auth.RegistrationTest :: test_registration_screen_can_be_rendered | PASS | [tests/Feature/Auth/RegistrationTest.php](../tests/Feature/Auth/RegistrationTest.php) |
| PHP-069 | Tests.Feature.Auth.RegistrationTest :: test_new_users_can_register | PASS | [tests/Feature/Auth/RegistrationTest.php](../tests/Feature/Auth/RegistrationTest.php) |
| PHP-070 | Tests.Feature.Auth.RegistrationTest :: test_registration_returns_to_the_intended_page | PASS | [tests/Feature/Auth/RegistrationTest.php](../tests/Feature/Auth/RegistrationTest.php) |
| PHP-071 | Tests.Feature.Auth.RegistrationTest :: test_patient_must_accept_terms_to_register | PASS | [tests/Feature/Auth/RegistrationTest.php](../tests/Feature/Auth/RegistrationTest.php) |
| PHP-072 | Tests.Feature.BookingConcurrencyTest :: test_locked_slot_never_exceeds_quota_and_numbers_remain_unique | PASS | [tests/Feature/BookingConcurrencyTest.php](../tests/Feature/BookingConcurrencyTest.php) |
| PHP-073 | Tests.Feature.BookingTest :: test_public_booking_form_has_a_submit_button | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-074 | Tests.Feature.BookingTest :: test_receptionist_booking_form_has_a_submit_button | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-075 | Tests.Feature.BookingTest :: test_receptionist_patient_search_returns_only_active_patients_from_their_clinic | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-076 | Tests.Feature.BookingTest :: test_guest_cannot_open_or_submit_public_booking | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-077 | Tests.Feature.BookingTest :: test_authenticated_patient_booking_creates_patient_appointment_and_queue_atomically | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-078 | Tests.Feature.BookingTest :: test_public_booking_requires_all_patient_identity_fields | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-079 | Tests.Feature.BookingTest :: test_idempotency_key_returns_same_booking_without_duplicate_rows | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-080 | Tests.Feature.BookingTest :: test_service_must_belong_to_selected_doctor_and_failure_leaves_no_partial_data | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-081 | Tests.Feature.BookingTest :: test_status_requires_secret_token_and_cancellation_releases_capacity | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-082 | Tests.Feature.BookingTest :: test_receptionist_can_book_an_explicit_existing_patient | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-083 | Tests.Feature.BookingTest :: test_existing_patient_profile_is_prefilled_and_updated_without_nik | PASS | [tests/Feature/BookingTest.php](../tests/Feature/BookingTest.php) |
| PHP-084 | Tests.Feature.ClinicButtonTest :: test_button_submits_forms_by_default | PASS | [tests/Feature/ClinicButtonTest.php](../tests/Feature/ClinicButtonTest.php) |
| PHP-085 | Tests.Feature.ClinicButtonTest :: test_button_type_can_be_overridden | PASS | [tests/Feature/ClinicButtonTest.php](../tests/Feature/ClinicButtonTest.php) |
| PHP-086 | Tests.Feature.ClinicNotificationDeliveryTest :: test_cross_clinic_public_cancel_commits_and_notifies_only_target_recipients | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-087 | Tests.Feature.ClinicNotificationDeliveryTest :: test_transport_failure_after_commit_is_retryable_without_http_or_business_failure | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-088 | Tests.Feature.ClinicNotificationDeliveryTest :: test_enqueue_failure_rolls_back_business_notifications_and_jobs | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-089 | Tests.Feature.ClinicNotificationDeliveryTest :: test_notification_storage_failure_rolls_back_cancellation | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-090 | Tests.Feature.ClinicNotificationDeliveryTest :: test_queue_database_must_be_the_business_connection | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-091 | Tests.Feature.ClinicNotificationDeliveryTest :: test_outer_rollback_emits_nothing_and_removes_all_delivery_records | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-092 | Tests.Feature.ClinicNotificationDeliveryTest :: test_sync_driver_cannot_replace_the_transactional_database_queue | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-093 | Tests.Feature.ClinicNotificationDeliveryTest :: test_delivery_rejects_a_recipient_or_tenant_that_does_not_match_the_stored_notification | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-094 | Tests.Feature.ClinicNotificationDeliveryTest :: test_delivery_retry_keeps_notification_id_and_does_not_repeat_business_effects | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-095 | Tests.Feature.ClinicNotificationDeliveryTest :: test_queue_delivery_is_minimal_after_commit_and_retry_has_no_database_effects | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-096 | Tests.Feature.ClinicNotificationDeliveryTest :: test_notification_center_keeps_recipient_ownership_with_committed_delivery | PASS | [tests/Feature/ClinicNotificationDeliveryTest.php](../tests/Feature/ClinicNotificationDeliveryTest.php) |
| PHP-097 | Tests.Feature.DashboardAccessTest :: test_users_reach_their_own_dashboard with data set "platform" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-098 | Tests.Feature.DashboardAccessTest :: test_users_reach_their_own_dashboard with data set "admin" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-099 | Tests.Feature.DashboardAccessTest :: test_users_reach_their_own_dashboard with data set "doctor" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-100 | Tests.Feature.DashboardAccessTest :: test_users_reach_their_own_dashboard with data set "receptionist" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-101 | Tests.Feature.DashboardAccessTest :: test_users_reach_their_own_dashboard with data set "patient" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-102 | Tests.Feature.DashboardAccessTest :: test_guests_are_redirected_to_login with data set "platform" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-103 | Tests.Feature.DashboardAccessTest :: test_guests_are_redirected_to_login with data set "admin" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-104 | Tests.Feature.DashboardAccessTest :: test_guests_are_redirected_to_login with data set "doctor" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-105 | Tests.Feature.DashboardAccessTest :: test_guests_are_redirected_to_login with data set "receptionist" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-106 | Tests.Feature.DashboardAccessTest :: test_guests_are_redirected_to_login with data set "patient" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-107 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "admin cannot use doctor route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-108 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "admin cannot use receptionist route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-109 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "admin cannot use patient route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-110 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "doctor cannot use admin route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-111 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "doctor cannot use receptionist route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-112 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "doctor cannot use patient route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-113 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "receptionist cannot use admin route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-114 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "receptionist cannot use doctor route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-115 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "receptionist cannot use patient route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-116 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "patient cannot use admin route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-117 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "patient cannot use doctor route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-118 | Tests.Feature.DashboardAccessTest :: test_users_cannot_open_another_roles_dashboard with data set "patient cannot use receptionist route" | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-119 | Tests.Feature.DashboardAccessTest :: test_users_without_a_role_receive_an_access_message_without_a_redirect_loop | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-120 | Tests.Feature.DashboardAccessTest :: test_multiple_roles_use_a_stable_dashboard_priority | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-121 | Tests.Feature.DashboardAccessTest :: test_unverified_users_must_verify_email_before_opening_a_dashboard | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-122 | Tests.Feature.DashboardAccessTest :: test_a_role_without_dashboard_permission_cannot_open_its_dashboard | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-123 | Tests.Feature.DashboardAccessTest :: test_the_dashboard_escapes_the_users_name | PASS | [tests/Feature/DashboardAccessTest.php](../tests/Feature/DashboardAccessTest.php) |
| PHP-124 | Tests.Feature.DemoSeederTest :: test_demo_seeder_builds_a_complete_idempotent_and_tenant_consistent_workspace | PASS | [tests/Feature/DemoSeederTest.php](../tests/Feature/DemoSeederTest.php) |
| PHP-125 | Tests.Feature.DentalChartComponentTest :: test_dental_chart_renders_state_legend_keyboard_controls_callbacks_and_persistence_fields | PASS | [tests/Feature/DentalChartComponentTest.php](../tests/Feature/DentalChartComponentTest.php) |
| PHP-126 | Tests.Feature.DentalChartComponentTest :: test_dental_chart_supports_read_only_state | PASS | [tests/Feature/DentalChartComponentTest.php](../tests/Feature/DentalChartComponentTest.php) |
| PHP-127 | Tests.Feature.DentalChartComponentTest :: test_critical_allergy_banner_only_renders_supplied_data | PASS | [tests/Feature/DentalChartComponentTest.php](../tests/Feature/DentalChartComponentTest.php) |
| PHP-128 | Tests.Feature.DokuPaymentTest :: test_owner_checkout_creates_a_snapshot_without_activating_access | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-129 | Tests.Feature.DokuPaymentTest :: test_invalid_notification_signature_is_rejected | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-130 | Tests.Feature.DokuPaymentTest :: test_valid_success_notification_activates_once_across_replays | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-131 | Tests.Feature.DokuPaymentTest :: test_mismatched_amount_or_request_never_activates_subscription | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-132 | Tests.Feature.DokuPaymentTest :: test_unknown_invoice_or_mismatched_clinic_is_rejected | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-133 | Tests.Feature.DokuPaymentTest :: test_gateway_failure_records_failed_invoice_without_damaging_existing_subscription | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-134 | Tests.Feature.DokuPaymentTest :: test_checkout_failed_notification_is_ignored_and_out_of_order_event_cannot_downgrade_paid | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-135 | Tests.Feature.DokuPaymentTest :: test_expired_notification_closes_pending_payment_without_activating_access | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-136 | Tests.Feature.DokuPaymentTest :: test_owner_can_reconcile_a_pending_payment | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-137 | Tests.Feature.DokuPaymentTest :: test_payment_history_is_private_to_owner_and_available_to_platform | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-138 | Tests.Feature.DokuPaymentTest :: test_platform_manual_refund_preserves_paid_period | PASS | [tests/Feature/DokuPaymentTest.php](../tests/Feature/DokuPaymentTest.php) |
| PHP-139 | Tests.Feature.ExampleTest :: test_landing_page_contains_accessible_mobile_navigation | PASS | [tests/Feature/ExampleTest.php](../tests/Feature/ExampleTest.php) |
| PHP-140 | Tests.Feature.ExampleTest :: test_local_proxy_https_header_is_trusted | PASS | [tests/Feature/ExampleTest.php](../tests/Feature/ExampleTest.php) |
| PHP-141 | Tests.Feature.LandingPageTest :: test_landing_page_shows_subscription_prices_without_platform_statistics | PASS | [tests/Feature/LandingPageTest.php](../tests/Feature/LandingPageTest.php) |
| PHP-142 | Tests.Feature.LegacyTenantBackfillTest :: test_backfill_maps_owner_and_grants_team_once_without_payment_or_medical_changes | PASS | [tests/Feature/LegacyTenantBackfillTest.php](../tests/Feature/LegacyTenantBackfillTest.php) |
| PHP-143 | Tests.Feature.LegacyTenantBackfillTest :: test_ambiguous_owner_stops_the_entire_backfill_before_writes | PASS | [tests/Feature/LegacyTenantBackfillTest.php](../tests/Feature/LegacyTenantBackfillTest.php) |
| PHP-144 | Tests.Feature.ManagementDashboardTest :: test_admin_dashboard_uses_completed_visit_snapshots_and_isolates_clinics | PASS | [tests/Feature/ManagementDashboardTest.php](../tests/Feature/ManagementDashboardTest.php) |
| PHP-145 | Tests.Feature.ManagementDashboardTest :: test_doctor_and_receptionist_dashboards_are_date_and_role_scoped | PASS | [tests/Feature/ManagementDashboardTest.php](../tests/Feature/ManagementDashboardTest.php) |
| PHP-146 | Tests.Feature.ManagementDashboardTest :: test_historical_and_read_only_dashboards_hide_queue_actions | PASS | [tests/Feature/ManagementDashboardTest.php](../tests/Feature/ManagementDashboardTest.php) |
| PHP-147 | Tests.Feature.MedicalRecordAccessTest :: test_only_assigned_doctor_can_open_medical_record | PASS | [tests/Feature/MedicalRecordAccessTest.php](../tests/Feature/MedicalRecordAccessTest.php) |
| PHP-148 | Tests.Feature.MedicalRecordAccessTest :: test_admin_and_receptionist_cannot_open_medical_contents | PASS | [tests/Feature/MedicalRecordAccessTest.php](../tests/Feature/MedicalRecordAccessTest.php) |
| PHP-149 | Tests.Feature.MedicalRecordAccessTest :: test_other_doctor_cannot_start_a_called_queue | PASS | [tests/Feature/MedicalRecordAccessTest.php](../tests/Feature/MedicalRecordAccessTest.php) |
| PHP-150 | Tests.Feature.MedicalRecordCorrectionTest :: test_final_record_rejects_draft_edits_and_correction_without_reason | PASS | [tests/Feature/MedicalRecordCorrectionTest.php](../tests/Feature/MedicalRecordCorrectionTest.php) |
| PHP-151 | Tests.Feature.MedicalRecordCorrectionTest :: test_versioned_correction_records_reason_before_and_after_snapshot | PASS | [tests/Feature/MedicalRecordCorrectionTest.php](../tests/Feature/MedicalRecordCorrectionTest.php) |
| PHP-152 | Tests.Feature.MedicalRecordCorrectionTest :: test_history_hides_revision_without_medical_record_changes | PASS | [tests/Feature/MedicalRecordCorrectionTest.php](../tests/Feature/MedicalRecordCorrectionTest.php) |
| PHP-153 | Tests.Feature.NotificationCenterTest :: test_booking_and_queue_changes_notify_linked_patient_and_doctor | PASS | [tests/Feature/NotificationCenterTest.php](../tests/Feature/NotificationCenterTest.php) |
| PHP-154 | Tests.Feature.NotificationCenterTest :: test_user_can_read_only_their_own_notification | PASS | [tests/Feature/NotificationCenterTest.php](../tests/Feature/NotificationCenterTest.php) |
| PHP-155 | Tests.Feature.OdontogramAddonTest :: test_combined_checkout_activates_both_only_after_valid_payment_and_refund_revokes_addon | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-156 | Tests.Feature.OdontogramAddonTest :: test_owner_can_buy_prorated_addon_after_paid_plan_is_active | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-157 | Tests.Feature.OdontogramAddonTest :: test_full_period_addon_price_matches_one_six_and_twelve_months | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-158 | Tests.Feature.OdontogramAddonTest :: test_late_addon_payment_is_flagged_for_review_without_granting_access | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-159 | Tests.Feature.OdontogramAddonTest :: test_free_and_trial_cannot_purchase_odontogram | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-160 | Tests.Feature.OdontogramAddonTest :: test_manual_grant_does_not_unlock_paid_addon_checkout | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-161 | Tests.Feature.OdontogramAddonTest :: test_second_checkout_for_same_period_is_blocked_and_pending_invoice_is_visible | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-162 | Tests.Feature.OdontogramAddonTest :: test_addon_checkout_due_date_stops_at_local_midnight | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-163 | Tests.Feature.OdontogramAddonTest :: test_prorata_uses_remaining_calendar_days_in_clinic_timezone | PASS | [tests/Feature/OdontogramAddonTest.php](../tests/Feature/OdontogramAddonTest.php) |
| PHP-164 | Tests.Feature.OwnerOnboardingTest :: test_owner_registration_is_separate_and_requires_email_verification | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-165 | Tests.Feature.OwnerOnboardingTest :: test_owner_must_accept_terms_to_register | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-166 | Tests.Feature.OwnerOnboardingTest :: test_owner_creates_exactly_one_clinic_and_resumes_at_plan_selection | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-167 | Tests.Feature.OwnerOnboardingTest :: test_invalid_clinic_data_does_not_create_a_partial_tenant | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-168 | Tests.Feature.OwnerOnboardingTest :: test_clinic_slug_must_be_a_valid_unreserved_subdomain_label | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-169 | Tests.Feature.OwnerOnboardingTest :: test_free_activation_is_idempotent_and_unlocks_tenant_routes | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-170 | Tests.Feature.OwnerOnboardingTest :: test_trial_activation_is_idempotent_and_creates_one_period | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-171 | Tests.Feature.OwnerOnboardingTest :: test_paid_checkout_creates_pending_payment_but_never_activates_from_redirect | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-172 | Tests.Feature.OwnerOnboardingTest :: test_non_owner_cannot_open_owner_onboarding | PASS | [tests/Feature/OwnerOnboardingTest.php](../tests/Feature/OwnerOnboardingTest.php) |
| PHP-173 | Tests.Feature.PartialWorkflowCompletionTest :: test_autosave_persists_nested_data_and_rejects_stale_save_and_finalize | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-174 | Tests.Feature.PartialWorkflowCompletionTest :: test_autosave_requires_version_and_validates_without_mutation | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-175 | Tests.Feature.PartialWorkflowCompletionTest :: test_incomplete_prescription_is_saved_as_draft_without_inventing_quantity | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-176 | Tests.Feature.PartialWorkflowCompletionTest :: test_autosave_forbids_other_doctors_and_final_records | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-177 | Tests.Feature.PartialWorkflowCompletionTest :: test_allergy_changes_preserve_history_and_invalidate_stale_editors | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-178 | Tests.Feature.PartialWorkflowCompletionTest :: test_allergy_endpoints_reject_administrative_roles_and_invalid_values | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-179 | Tests.Feature.PartialWorkflowCompletionTest :: test_correction_of_allergy_details_preserves_the_previous_clinical_values | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-180 | Tests.Feature.PartialWorkflowCompletionTest :: test_duplicate_review_requires_confirmation_and_is_tenant_scoped | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-181 | Tests.Feature.PartialWorkflowCompletionTest :: test_walk_in_uses_duplicate_review_without_auto_linking | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-182 | Tests.Feature.PartialWorkflowCompletionTest :: test_duplicate_candidates_match_name_and_birth_date_and_forbid_patient_access | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-183 | Tests.Feature.PartialWorkflowCompletionTest :: test_patient_eta_is_owned_refreshable_and_hidden_outside_active_today | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-184 | Tests.Feature.PartialWorkflowCompletionTest :: test_forms_render_shared_filters_and_structured_editor | PASS | [tests/Feature/PartialWorkflowCompletionTest.php](../tests/Feature/PartialWorkflowCompletionTest.php) |
| PHP-185 | Tests.Feature.PatientManagementTest :: test_receptionist_can_register_and_search_patient_in_own_clinic | PASS | [tests/Feature/PatientManagementTest.php](../tests/Feature/PatientManagementTest.php) |
| PHP-186 | Tests.Feature.PatientManagementTest :: test_receptionist_cannot_view_patient_from_another_clinic | PASS | [tests/Feature/PatientManagementTest.php](../tests/Feature/PatientManagementTest.php) |
| PHP-187 | Tests.Feature.PatientPrescriptionDashboardTest :: test_patient_sees_only_their_final_prescriptions_from_completed_visits | PASS | [tests/Feature/PatientPrescriptionDashboardTest.php](../tests/Feature/PatientPrescriptionDashboardTest.php) |
| PHP-188 | Tests.Feature.PatientPrescriptionDashboardTest :: test_dashboard_handles_no_prescription_and_a_note_only_prescription | PASS | [tests/Feature/PatientPrescriptionDashboardTest.php](../tests/Feature/PatientPrescriptionDashboardTest.php) |
| PHP-189 | Tests.Feature.PatientPrescriptionDashboardTest :: test_dashboard_reads_the_current_corrected_prescription | PASS | [tests/Feature/PatientPrescriptionDashboardTest.php](../tests/Feature/PatientPrescriptionDashboardTest.php) |
| PHP-190 | Tests.Feature.PlatformAccessTest :: test_platform_admin_can_view_nonmedical_tenant_dashboard_and_detail | PASS | [tests/Feature/PlatformAccessTest.php](../tests/Feature/PlatformAccessTest.php) |
| PHP-191 | Tests.Feature.PlatformAccessTest :: test_platform_dashboard_surfaces_operational_exceptions | PASS | [tests/Feature/PlatformAccessTest.php](../tests/Feature/PlatformAccessTest.php) |
| PHP-192 | Tests.Feature.PlatformAccessTest :: test_platform_admin_cannot_open_tenant_or_medical_routes | PASS | [tests/Feature/PlatformAccessTest.php](../tests/Feature/PlatformAccessTest.php) |
| PHP-193 | Tests.Feature.PlatformAccessTest :: test_tenant_users_cannot_open_platform_routes | PASS | [tests/Feature/PlatformAccessTest.php](../tests/Feature/PlatformAccessTest.php) |
| PHP-194 | Tests.Feature.PlatformAccessTest :: test_clinic_owner_is_explicit | PASS | [tests/Feature/PlatformAccessTest.php](../tests/Feature/PlatformAccessTest.php) |
| PHP-195 | Tests.Feature.PlatformSubscriptionManagementTest :: test_platform_admin_can_manage_catalog_and_grant_audited_access | PASS | [tests/Feature/PlatformSubscriptionManagementTest.php](../tests/Feature/PlatformSubscriptionManagementTest.php) |
| PHP-196 | Tests.Feature.PlatformSubscriptionManagementTest :: test_grant_requires_a_reason_and_an_active_catalog_entry | PASS | [tests/Feature/PlatformSubscriptionManagementTest.php](../tests/Feature/PlatformSubscriptionManagementTest.php) |
| PHP-197 | Tests.Feature.PlatformSubscriptionManagementTest :: test_tenant_owner_cannot_manage_platform_catalog_or_grants | PASS | [tests/Feature/PlatformSubscriptionManagementTest.php](../tests/Feature/PlatformSubscriptionManagementTest.php) |
| PHP-198 | Tests.Feature.PlatformSubscriptionManagementTest :: test_price_edit_rejects_invalid_amount_without_changing_catalog | PASS | [tests/Feature/PlatformSubscriptionManagementTest.php](../tests/Feature/PlatformSubscriptionManagementTest.php) |
| PHP-199 | Tests.Feature.PlatformSubscriptionManagementTest :: test_grant_downgrade_to_individual_applies_now_and_cancels_queued_access | PASS | [tests/Feature/PlatformSubscriptionManagementTest.php](../tests/Feature/PlatformSubscriptionManagementTest.php) |
| PHP-200 | Tests.Feature.PlatformSubscriptionManagementTest :: test_platform_admin_can_downgrade_paid_team_to_free_without_erasing_invoice_or_addon_history | PASS | [tests/Feature/PlatformSubscriptionManagementTest.php](../tests/Feature/PlatformSubscriptionManagementTest.php) |
| PHP-201 | Tests.Feature.ProfileTest :: test_profile_page_is_displayed | PASS | [tests/Feature/ProfileTest.php](../tests/Feature/ProfileTest.php) |
| PHP-202 | Tests.Feature.ProfileTest :: test_profile_information_can_be_updated | PASS | [tests/Feature/ProfileTest.php](../tests/Feature/ProfileTest.php) |
| PHP-203 | Tests.Feature.ProfileTest :: test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged | PASS | [tests/Feature/ProfileTest.php](../tests/Feature/ProfileTest.php) |
| PHP-204 | Tests.Feature.ProfileTest :: test_user_can_delete_their_account | PASS | [tests/Feature/ProfileTest.php](../tests/Feature/ProfileTest.php) |
| PHP-205 | Tests.Feature.ProfileTest :: test_correct_password_must_be_provided_to_delete_account | PASS | [tests/Feature/ProfileTest.php](../tests/Feature/ProfileTest.php) |
| PHP-206 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "guest free" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-207 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "guest individual" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-208 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "guest team" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-209 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "guest trial" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-210 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "guest suspended" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-211 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "guest expired" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-212 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "active actor suspended target" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-213 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "active actor expired target" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-214 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "suspended actor active target" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-215 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "expired actor active target" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-216 | Tests.Feature.PublicCancellationTest :: test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users with data set "active actor active target" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-217 | Tests.Feature.PublicCancellationTest :: test_invalid_tokens_change_no_resource with data set "missing" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-218 | Tests.Feature.PublicCancellationTest :: test_invalid_tokens_change_no_resource with data set "wrong format" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-219 | Tests.Feature.PublicCancellationTest :: test_invalid_tokens_change_no_resource with data set "wrong hash" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-220 | Tests.Feature.PublicCancellationTest :: test_invalid_tokens_change_no_resource with data set "another booking" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-221 | Tests.Feature.PublicCancellationTest :: test_repeat_cancellation_is_validation_error_without_duplicate_side_effects | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-222 | Tests.Feature.PublicCancellationTest :: test_service_itself_rejects_suspended_target_before_writing | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-223 | Tests.Feature.PublicCancellationTest :: test_started_session_cannot_be_cancelled | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-224 | Tests.Feature.PublicCancellationTest :: test_public_booking_reuses_owned_target_profile_with_another_auth_clinic | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-225 | Tests.Feature.PublicCancellationTest :: test_free_target_with_excess_seats_cannot_be_cancelled_by_another_writable_clinic | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-226 | Tests.Feature.PublicCancellationTest :: test_read_only_target_allows_assigned_doctor_to_save_and_complete_active_visit with data set "expired" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-227 | Tests.Feature.PublicCancellationTest :: test_read_only_target_allows_assigned_doctor_to_save_and_complete_active_visit with data set "suspended" | PASS | [tests/Feature/PublicCancellationTest.php](../tests/Feature/PublicCancellationTest.php) |
| PHP-228 | Tests.Feature.PublicClinicTest :: test_active_clinic_public_page_lists_only_active_services_and_doctors | PASS | [tests/Feature/PublicClinicTest.php](../tests/Feature/PublicClinicTest.php) |
| PHP-229 | Tests.Feature.PublicClinicTest :: test_inactive_clinic_public_page_is_not_accessible | PASS | [tests/Feature/PublicClinicTest.php](../tests/Feature/PublicClinicTest.php) |
| PHP-230 | Tests.Feature.PublicClinicTest :: test_public_urls_resolve_only_the_clinic_named_by_the_subdomain | PASS | [tests/Feature/PublicClinicTest.php](../tests/Feature/PublicClinicTest.php) |
| PHP-231 | Tests.Feature.PublicClinicTest :: test_reserved_clinic_subdomain_is_not_public | PASS | [tests/Feature/PublicClinicTest.php](../tests/Feature/PublicClinicTest.php) |
| PHP-232 | Tests.Feature.PublicClinicTest :: test_public_page_displays_the_clinic_logo_when_available | PASS | [tests/Feature/PublicClinicTest.php](../tests/Feature/PublicClinicTest.php) |
| PHP-233 | Tests.Feature.PublicClinicTest :: test_clinic_page_links_guests_to_login_and_patients_to_their_dashboard | PASS | [tests/Feature/PublicClinicTest.php](../tests/Feature/PublicClinicTest.php) |
| PHP-234 | Tests.Feature.PublicQueueComponentsTest :: test_queue_number_display_renders_only_public_props | PASS | [tests/Feature/PublicQueueComponentsTest.php](../tests/Feature/PublicQueueComponentsTest.php) |
| PHP-235 | Tests.Feature.PublicQueueComponentsTest :: test_public_queue_screen_renders_mock_queue_data_responsively | PASS | [tests/Feature/PublicQueueComponentsTest.php](../tests/Feature/PublicQueueComponentsTest.php) |
| PHP-236 | Tests.Feature.PublicQueueComponentsTest :: test_public_queue_screen_has_a_no_data_state | PASS | [tests/Feature/PublicQueueComponentsTest.php](../tests/Feature/PublicQueueComponentsTest.php) |
| PHP-237 | Tests.Feature.PublicQueueComponentsTest :: test_queue_number_display_renders_eta_confidence_and_stale_states | PASS | [tests/Feature/PublicQueueComponentsTest.php](../tests/Feature/PublicQueueComponentsTest.php) |
| PHP-238 | Tests.Feature.PublicQueueComponentsTest :: test_queue_number_display_does_not_present_invalid_eta_as_a_promise | PASS | [tests/Feature/PublicQueueComponentsTest.php](../tests/Feature/PublicQueueComponentsTest.php) |
| PHP-239 | Tests.Feature.PublicQueueComponentsTest :: test_public_queue_screen_renders_every_connection_state_with_text | PASS | [tests/Feature/PublicQueueComponentsTest.php](../tests/Feature/PublicQueueComponentsTest.php) |
| PHP-240 | Tests.Feature.PublicQueueComponentsTest :: test_status_badge_renders_correct_label_and_styling | PASS | [tests/Feature/PublicQueueComponentsTest.php](../tests/Feature/PublicQueueComponentsTest.php) |
| PHP-241 | Tests.Feature.QueueEtaServiceTest :: test_it_falls_back_to_service_duration_and_counts_only_active_queues_in_the_same_session | PASS | [tests/Feature/QueueEtaServiceTest.php](../tests/Feature/QueueEtaServiceTest.php) |
| PHP-242 | Tests.Feature.QueueEtaServiceTest :: test_it_uses_nineteen_synthetic_durations_for_eta_range_and_medium_confidence | PASS | [tests/Feature/QueueEtaServiceTest.php](../tests/Feature/QueueEtaServiceTest.php) |
| PHP-243 | Tests.Feature.QueueEtaServiceTest :: test_it_uses_only_the_twenty_most_recent_completed_visits | PASS | [tests/Feature/QueueEtaServiceTest.php](../tests/Feature/QueueEtaServiceTest.php) |
| PHP-244 | Tests.Feature.QueueEtaServiceTest :: test_it_ignores_history_from_another_doctor_and_incomplete_visits | PASS | [tests/Feature/QueueEtaServiceTest.php](../tests/Feature/QueueEtaServiceTest.php) |
| PHP-245 | Tests.Feature.QueueManagementTest :: test_receptionist_can_view_and_check_in_own_clinic_queue | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-246 | Tests.Feature.QueueManagementTest :: test_queue_actions_return_json_for_staff_ui | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-247 | Tests.Feature.QueueManagementTest :: test_queue_json_action_returns_validation_error_for_stale_status | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-248 | Tests.Feature.QueueManagementTest :: test_queue_json_action_returns_forbidden_for_another_clinic | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-249 | Tests.Feature.QueueManagementTest :: test_only_one_patient_can_be_called_in_the_same_doctor_session | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-250 | Tests.Feature.QueueManagementTest :: test_called_queue_can_be_skipped_returned_and_called_again | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-251 | Tests.Feature.QueueManagementTest :: test_doctor_only_sees_and_calls_own_queue | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-252 | Tests.Feature.QueueManagementTest :: test_cancellation_and_no_show_are_synchronized_with_appointment | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-253 | Tests.Feature.QueueManagementTest :: test_no_show_is_rejected_before_session_starts | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-254 | Tests.Feature.QueueManagementTest :: test_public_queue_board_contains_no_patient_identity | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-255 | Tests.Feature.QueueManagementTest :: test_public_queue_display_route_accessible_by_slug_and_id_and_aborts_404_for_invalid | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-256 | Tests.Feature.QueueManagementTest :: test_staff_cannot_manage_queue_from_another_clinic | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-257 | Tests.Feature.QueueManagementTest :: test_future_queue_cannot_be_checked_in_early | PASS | [tests/Feature/QueueManagementTest.php](../tests/Feature/QueueManagementTest.php) |
| PHP-258 | Tests.Feature.QueueRealtimeTest :: test_queue_event_has_private_staff_and_minimal_public_payload | PASS | [tests/Feature/QueueRealtimeTest.php](../tests/Feature/QueueRealtimeTest.php) |
| PHP-259 | Tests.Feature.QueueRealtimeTest :: test_private_queue_channel_rejects_other_clinic_and_patient_accounts | PASS | [tests/Feature/QueueRealtimeTest.php](../tests/Feature/QueueRealtimeTest.php) |
| PHP-260 | Tests.Feature.QueueRealtimeTest :: test_queue_transition_dispatches_realtime_event | PASS | [tests/Feature/QueueRealtimeTest.php](../tests/Feature/QueueRealtimeTest.php) |
| PHP-261 | Tests.Feature.RmeGeneralDentalTest :: test_assigned_doctor_can_persist_general_rme_and_odontogram_in_draft | PASS | [tests/Feature/RmeGeneralDentalTest.php](../tests/Feature/RmeGeneralDentalTest.php) |
| PHP-262 | Tests.Feature.RmeGeneralDentalTest :: test_invalid_general_and_odontogram_payload_is_rejected_without_mutating_record | PASS | [tests/Feature/RmeGeneralDentalTest.php](../tests/Feature/RmeGeneralDentalTest.php) |
| PHP-263 | Tests.Feature.RmeGeneralDentalTest :: test_critical_allergy_is_visible_and_prescription_requires_recorded_acknowledgement | PASS | [tests/Feature/RmeGeneralDentalTest.php](../tests/Feature/RmeGeneralDentalTest.php) |
| PHP-264 | Tests.Feature.RmeGeneralDentalTest :: test_doctor_from_another_tenant_cannot_write_general_or_dental_record | PASS | [tests/Feature/RmeGeneralDentalTest.php](../tests/Feature/RmeGeneralDentalTest.php) |
| PHP-265 | Tests.Feature.RmeGeneralDentalTest :: test_final_record_rejects_direct_edit_and_dental_correction_creates_revision | PASS | [tests/Feature/RmeGeneralDentalTest.php](../tests/Feature/RmeGeneralDentalTest.php) |
| PHP-266 | Tests.Feature.RmeGeneralDentalTest :: test_general_rme_stays_writable_and_existing_odontogram_is_read_only_after_refund | PASS | [tests/Feature/RmeGeneralDentalTest.php](../tests/Feature/RmeGeneralDentalTest.php) |
| PHP-267 | Tests.Feature.RolePermissionTest :: test_roles_enforce_the_agreed_permission_boundaries with data set "platform admin manages commercial metadata but has no tenant or medical access" | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-268 | Tests.Feature.RolePermissionTest :: test_roles_enforce_the_agreed_permission_boundaries with data set "admin manages clinic but has no automatic medical access" | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-269 | Tests.Feature.RolePermissionTest :: test_roles_enforce_the_agreed_permission_boundaries with data set "doctor examines and calls but cannot configure clinic" | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-270 | Tests.Feature.RolePermissionTest :: test_roles_enforce_the_agreed_permission_boundaries with data set "receptionist manages administration but not medical records" | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-271 | Tests.Feature.RolePermissionTest :: test_roles_enforce_the_agreed_permission_boundaries with data set "patient books but cannot manage other users or medical data" | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-272 | Tests.Feature.RolePermissionTest :: test_reseeding_removes_old_automatic_medical_access_without_creating_demo_users | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-273 | Tests.Feature.RolePermissionTest :: test_medical_access_can_be_granted_explicitly_without_changing_the_admin_role | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-274 | Tests.Feature.RolePermissionTest :: test_production_seeding_does_not_create_known_demo_accounts | PASS | [tests/Feature/RolePermissionTest.php](../tests/Feature/RolePermissionTest.php) |
| PHP-275 | Tests.Feature.SecurityHeadersTest :: test_web_responses_include_security_headers | PASS | [tests/Feature/SecurityHeadersTest.php](../tests/Feature/SecurityHeadersTest.php) |
| PHP-276 | Tests.Feature.SubscriptionEnforcementTest :: test_free_rejects_new_and_reactivated_seats_at_the_server | PASS | [tests/Feature/SubscriptionEnforcementTest.php](../tests/Feature/SubscriptionEnforcementTest.php) |
| PHP-277 | Tests.Feature.SubscriptionEnforcementTest :: test_owner_can_deactivate_an_excess_seat_and_restore_free_operations | PASS | [tests/Feature/SubscriptionEnforcementTest.php](../tests/Feature/SubscriptionEnforcementTest.php) |
| PHP-278 | Tests.Feature.SubscriptionEnforcementTest :: test_individual_limits_staff_while_team_allows_unlimited_accounts | PASS | [tests/Feature/SubscriptionEnforcementTest.php](../tests/Feature/SubscriptionEnforcementTest.php) |
| PHP-279 | Tests.Feature.SubscriptionEnforcementTest :: test_existing_booking_can_start_after_free_quota_closes_new_booking | PASS | [tests/Feature/SubscriptionEnforcementTest.php](../tests/Feature/SubscriptionEnforcementTest.php) |
| PHP-280 | Tests.Feature.SubscriptionEnforcementTest :: test_expired_subscription_blocks_new_writes_but_keeps_reads_and_active_visit_completion | PASS | [tests/Feature/SubscriptionEnforcementTest.php](../tests/Feature/SubscriptionEnforcementTest.php) |
| PHP-281 | Tests.Feature.SubscriptionEnforcementTest :: test_banner_hides_pending_payment_details_from_non_owner_staff | PASS | [tests/Feature/SubscriptionEnforcementTest.php](../tests/Feature/SubscriptionEnforcementTest.php) |
| PHP-282 | Tests.Feature.SubscriptionHardeningTest :: test_sensitive_routes_use_their_named_rate_limits | PASS | [tests/Feature/SubscriptionHardeningTest.php](../tests/Feature/SubscriptionHardeningTest.php) |
| PHP-283 | Tests.Feature.SubscriptionHardeningTest :: test_registration_is_limited_by_email_and_ip | PASS | [tests/Feature/SubscriptionHardeningTest.php](../tests/Feature/SubscriptionHardeningTest.php) |
| PHP-284 | Tests.Feature.SubscriptionHardeningTest :: test_payment_event_and_audit_logs_exclude_secrets_tokens_signatures_and_medical_data | PASS | [tests/Feature/SubscriptionHardeningTest.php](../tests/Feature/SubscriptionHardeningTest.php) |
| PHP-285 | Tests.Feature.SubscriptionLifecycleTest :: test_catalog_seeder_creates_the_agreed_plans_and_prices_idempotently | PASS | [tests/Feature/SubscriptionLifecycleTest.php](../tests/Feature/SubscriptionLifecycleTest.php) |
| PHP-286 | Tests.Feature.SubscriptionLifecycleTest :: test_team_trial_can_only_be_used_once_and_transitions_to_free | PASS | [tests/Feature/SubscriptionLifecycleTest.php](../tests/Feature/SubscriptionLifecycleTest.php) |
| PHP-287 | Tests.Feature.SubscriptionLifecycleTest :: test_paid_renewal_stacks_periods_and_keeps_price_snapshots | PASS | [tests/Feature/SubscriptionLifecycleTest.php](../tests/Feature/SubscriptionLifecycleTest.php) |
| PHP-288 | Tests.Feature.SubscriptionLifecycleTest :: test_paid_activation_rejects_a_mismatched_snapshot | PASS | [tests/Feature/SubscriptionLifecycleTest.php](../tests/Feature/SubscriptionLifecycleTest.php) |
| PHP-289 | Tests.Feature.SubscriptionLifecycleTest :: test_late_renewal_starts_when_payment_succeeds | PASS | [tests/Feature/SubscriptionLifecycleTest.php](../tests/Feature/SubscriptionLifecycleTest.php) |
| PHP-290 | Tests.Feature.SubscriptionReminderTest :: test_scheduler_queues_each_timezone_milestone_once_and_job_notifies_only_owner | PASS | [tests/Feature/SubscriptionReminderTest.php](../tests/Feature/SubscriptionReminderTest.php) |
| PHP-291 | Tests.Feature.SubscriptionReminderTest :: test_job_has_bounded_retries_and_records_failure_without_error_details | PASS | [tests/Feature/SubscriptionReminderTest.php](../tests/Feature/SubscriptionReminderTest.php) |
| PHP-292 | Tests.Feature.SubscriptionReminderTest :: test_revoked_period_does_not_queue_or_send_an_old_expiry_reminder | PASS | [tests/Feature/SubscriptionReminderTest.php](../tests/Feature/SubscriptionReminderTest.php) |
| PHP-293 | Tests.Feature.VisitSummaryComponentTest :: test_it_renders_visit_date_doctor_status_and_responsive_contract_from_props | PASS | [tests/Feature/VisitSummaryComponentTest.php](../tests/Feature/VisitSummaryComponentTest.php) |
| PHP-294 | Tests.Feature.VisitSummaryComponentTest :: test_it_renders_custom_empty_state_from_props | PASS | [tests/Feature/VisitSummaryComponentTest.php](../tests/Feature/VisitSummaryComponentTest.php) |
| PHP-295 | Tests.Feature.VisitSummaryComponentTest :: test_local_preview_renders_populated_and_empty_mock_states | PASS | [tests/Feature/VisitSummaryComponentTest.php](../tests/Feature/VisitSummaryComponentTest.php) |
| PHP-296 | Tests.Feature.VisitWorkflowTest :: test_assigned_doctor_starts_exactly_one_visit_from_called_queue | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-297 | Tests.Feature.VisitWorkflowTest :: test_start_and_complete_support_json_for_staff_ui | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-298 | Tests.Feature.VisitWorkflowTest :: test_doctor_can_save_complete_draft_with_nested_clinical_data | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-299 | Tests.Feature.VisitWorkflowTest :: test_prescription_editor_explains_draft_preview_and_finalize | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-300 | Tests.Feature.VisitWorkflowTest :: test_rme_general_form_renders_reusable_fields_and_autosave_contract | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-301 | Tests.Feature.VisitWorkflowTest :: test_rme_general_form_links_validation_error_and_preserves_old_input | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-302 | Tests.Feature.VisitWorkflowTest :: test_doctor_can_save_simplified_record_with_private_photo_and_without_vital_signs | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-303 | Tests.Feature.VisitWorkflowTest :: test_completion_atomically_finalizes_record_visit_appointment_and_queue | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-304 | Tests.Feature.VisitWorkflowTest :: test_incomplete_record_cannot_finalize_and_preserves_active_workflow | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-305 | Tests.Feature.VisitWorkflowTest :: test_finalization_failure_rolls_back_all_related_state | PASS | [tests/Feature/VisitWorkflowTest.php](../tests/Feature/VisitWorkflowTest.php) |
| PHP-306 | Tests.Unit.ExampleTest :: test_that_true_is_true | PASS | [tests/Unit/ExampleTest.php](../tests/Unit/ExampleTest.php) |
| PHP-307 | Tests.Feature.FullSystemAuditTest :: test_schedule_edit_preserves_the_doctor_of_existing_bookings | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-308 | Tests.Feature.FullSystemAuditTest :: test_final_record_photo_remains_available_in_revision_history | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-309 | Tests.Feature.FullSystemAuditTest :: test_failed_draft_transaction_does_not_leave_an_unreferenced_upload | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-310 | Tests.Feature.FullSystemAuditTest :: test_clinic_owner_cannot_delete_the_only_owner_account | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-311 | Tests.Feature.FullSystemAuditTest :: test_owner_without_a_clinic_is_guided_to_onboarding_from_addons | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-312 | Tests.Feature.FullSystemAuditTest :: test_final_correction_requires_a_version_to_prevent_stale_overwrites | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-313 | Tests.Feature.FullSystemAuditTest :: test_nested_fields_are_handled_without_a_server_error with data set "nullable diagnoses" | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-314 | Tests.Feature.FullSystemAuditTest :: test_nested_fields_are_handled_without_a_server_error with data set "nullable treatments" | FAIL | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-315 | Tests.Feature.FullSystemAuditTest :: test_nested_fields_are_handled_without_a_server_error with data set "scalar diagnosis item" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-316 | Tests.Feature.FullSystemAuditTest :: test_nested_fields_are_handled_without_a_server_error with data set "scalar treatment item" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-317 | Tests.Feature.FullSystemAuditTest :: test_nested_fields_are_handled_without_a_server_error with data set "scalar prescription item" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-318 | Tests.Feature.FullSystemAuditTest :: test_each_role_is_checked_at_operational_endpoints with data set "Clinic Admin" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-319 | Tests.Feature.FullSystemAuditTest :: test_each_role_is_checked_at_operational_endpoints with data set "Receptionist" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-320 | Tests.Feature.FullSystemAuditTest :: test_each_role_is_checked_at_operational_endpoints with data set "Doctor" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-321 | Tests.Feature.FullSystemAuditTest :: test_each_role_is_checked_at_operational_endpoints with data set "Patient" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-322 | Tests.Feature.FullSystemAuditTest :: test_each_role_is_checked_at_operational_endpoints with data set "Platform Admin" | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-323 | Tests.Feature.FullSystemAuditTest :: test_admin_without_a_clinic_can_open_the_setup_form | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-324 | Tests.Feature.FullSystemAuditTest :: test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-325 | Tests.Feature.FullSystemAuditTest :: test_read_all_notifications_does_not_mark_another_accounts_notifications | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |
| PHP-326 | Tests.Feature.FullSystemAuditTest :: test_platform_commercial_actions_preserve_invoice_and_owner_history | PASS | [qa-audit/tests/FullSystemAuditTest.php](../qa-audit/tests/FullSystemAuditTest.php) |

### JavaScript dan browser

| ID | Skenario | Hasil | Test |
| --- | --- | --- | --- |
| JS-001 | redelivery of the same notification increments the counter and emits the event once | PASS | [tests/notification-realtime.test.mjs](../tests/notification-realtime.test.mjs) |
| JS-002 | distinct notifications remain visible and messages without IDs preserve compatibility | PASS | [tests/notification-realtime.test.mjs](../tests/notification-realtime.test.mjs) |
| JS-003 | autosave updates the version and keeps photos for the explicit save | PASS | [tests/partial-workflows.test.mjs](../tests/partial-workflows.test.mjs) |
| JS-004 | network failures retain changes and can be retried; conflict stops automatic writes | PASS | [tests/partial-workflows.test.mjs](../tests/partial-workflows.test.mjs) |
| JS-005 | typing during an autosave is saved in a following request with the new version | PASS | [tests/partial-workflows.test.mjs](../tests/partial-workflows.test.mjs) |
| JS-006 | finalize waits for an active autosave and retains the form on a stale conflict | PASS | [tests/partial-workflows.test.mjs](../tests/partial-workflows.test.mjs) |
| JS-007 | booking filters services by doctor and sessions by the selected date | PASS | [tests/partial-workflows.test.mjs](../tests/partial-workflows.test.mjs) |
| BR-001 | Clinical/booking/duplicate/offline/responsive workflow | PASS | tests/browser-partial-workflows.test.mjs |
| BR-002 | Lima role/menu/logout/viewport, dokter publik, Escape | FAIL QA-01/QA-09 | qa-audit/tests/browser-audit.test.mjs |


### Eksperimen browser tambahan

Tidak dijumlahkan ke denominator automated 335 agar tidak menghitung ulang tugas yang sama. Data lengkap di interactive-browser.json.

| ID | Hasil | Observasi nonmedis |
| --- | --- | --- |
| B-ROLE-admin | PASS | {"dashboard":"/admin/dashboard","pages":[{"path":"/admin/dashboard","resolved":"/admin/dashboard","status":200,"heading":"Dashboard"},{"path":"/subscription/payments","resolved":"/subscription/payments","status":200,"heading":"Riwayat pembayaran"},{"path":"/subscription/addons","resolved":"/subscription/addons","status... (lengkap di JSON) |
| B-ROLE-receptionist | PASS | {"dashboard":"/receptionist/dashboard","pages":[{"path":"/receptionist/dashboard","resolved":"/receptionist/dashboard","status":200,"heading":"Dashboard Resepsionis"},{"path":"/receptionist/patients","resolved":"/receptionist/patients","status":200,"heading":"Pasien"},{"path":"/receptionist/appointments","resolved":"/r... (lengkap di JSON) |
| B-ROLE-doctor | PASS | {"dashboard":"/doctor/dashboard","pages":[{"path":"/doctor/dashboard","resolved":"/doctor/dashboard","status":200,"heading":"Dashboard Dokter"},{"path":"/doctor/queues","resolved":"/doctor/queues","status":200,"heading":"Antrean pasien"},{"path":"/doctor/medical-records","resolved":"/doctor/medical-records","status":20... (lengkap di JSON) |
| B-ROLE-patient | PASS | {"dashboard":"/patient/dashboard","pages":[{"path":"/patient/dashboard","resolved":"/patient/dashboard","status":200,"heading":"Dashboard Pasien"},{"path":"/patient/appointments","resolved":"/patient/appointments","status":200,"heading":"Booking saya"},{"path":"/notifications","resolved":"/notifications","status":200,"... (lengkap di JSON) |
| B-ROLE-platform | PASS | {"dashboard":"/platform/dashboard","pages":[{"path":"/platform/dashboard","resolved":"/platform/dashboard","status":200,"heading":"Dashboard platform"},{"path":"/platform/tenants","resolved":"/platform/tenants","status":200,"heading":"Tenant klinik"},{"path":"/platform/subscription-plans","resolved":"/platform/subscrip... (lengkap di JSON) |
| B-DENY-admin | PASS | [{"target":"receptionist","status":403},{"target":"doctor","status":403},{"target":"patient","status":403},{"target":"platform","status":403}] |
| B-RESP-admin | PASS | [{"width":375,"overflow":-15},{"width":768,"overflow":-15},{"width":1024,"overflow":-15},{"width":1440,"overflow":-15}] |
| B-LOGOUT-admin | PASS | {"redirect":"/admin"} |
| B-DENY-receptionist | PASS | [{"target":"admin","status":403},{"target":"doctor","status":403},{"target":"patient","status":403},{"target":"platform","status":403}] |
| B-RESP-receptionist | PASS | [{"width":375,"overflow":-15},{"width":768,"overflow":-15},{"width":1024,"overflow":-15},{"width":1440,"overflow":-15}] |
| B-LOGOUT-receptionist | PASS | {"redirect":"/admin"} |
| B-DENY-doctor | PASS | [{"target":"admin","status":403},{"target":"receptionist","status":403},{"target":"patient","status":403},{"target":"platform","status":403}] |
| B-RESP-doctor | PASS | [{"width":375,"overflow":-15},{"width":768,"overflow":-15},{"width":1024,"overflow":0},{"width":1440,"overflow":0}] |
| B-LOGOUT-doctor | PASS | {"redirect":"/admin"} |
| B-DENY-patient | PASS | [{"target":"admin","status":403},{"target":"receptionist","status":403},{"target":"doctor","status":403},{"target":"platform","status":403}] |
| B-RESP-patient | PASS | [{"width":375,"overflow":-15},{"width":768,"overflow":-15},{"width":1024,"overflow":0},{"width":1440,"overflow":0}] |
| B-LOGOUT-patient | PASS | {"redirect":"/login"} |
| B-DENY-platform | PASS | [{"target":"admin","status":403},{"target":"receptionist","status":403},{"target":"doctor","status":403},{"target":"patient","status":403}] |
| B-RESP-platform | PASS | [{"width":375,"overflow":-15},{"width":768,"overflow":-15},{"width":1024,"overflow":-15},{"width":1440,"overflow":-15}] |
| B-LOGOUT-platform | PASS | {"redirect":"/admin"} |
| B-MOBILE-ESCAPE | FAIL | {"drawerOpen":true} |
| B-STAFF-CREATE | PASS | {"created":true,"verified":false} |
| B-SCHEDULE-REASSIGN | FAIL | {"appointment_doctor":1,"queue_doctor":1,"schedule_doctor":3} |
| B-SCHEDULE-CREATE | PASS | {"created":true} |
| B-BOOKING-EXISTING-PATIENT | PASS | {"created":true,"queueStatus":"BOOKED"} |
| B-CHECKIN-CALL | PASS | {"queueStatus":"CALLED","visitsBeforeStart":0} |
| B-VISIT-DRAFT | PASS | {"visitCount":1,"recordStatus":"DRAFT"} |
| B-PHOTO-SAVE | PASS | {"fileExists":true} |
| B-E2E-FINALIZE | PASS | {"visit":"COMPLETED","record":"FINAL","appointment":"COMPLETED","queue":"COMPLETED"} |
| B-PHOTO-HISTORY | FAIL | {"revisionRetainsReference":true,"fileExists":false} |
| B-INACTIVE-admin | PASS | {"redirect":"/admin"} |
| B-INACTIVE-receptionist | PASS | {"redirect":"/admin"} |
| B-INACTIVE-doctor | PASS | {"redirect":"/admin"} |
| B-INACTIVE-patient | PASS | {"redirect":"/login"} |
| B-INACTIVE-platform | PASS | {"redirect":"/admin"} |
| B-ROLE-CHANGED | PASS | {"status":403} |
| B-CSRF | PASS | {"crossSiteWithoutToken":419,"sameOriginReachedValidation":422} |
| B-PUBLIC-SUBDOMAIN | PASS | {"status":200} |
| B-OWNER-REGISTER | PASS | {"verificationRequired":true,"created":true} |
| B-OWNER-ONBOARD-FREE | PASS | {"subscriptionStatus":"free"} |
| B-OWNER-DELETE | FAIL | {"userExists":false,"clinicRemains":true,"ownerIsNull":true} |
| B-PUBLIC-BOOKING-UI | FAIL | {"httpStatus":200,"alpineAvailable":false,"selectionFactoryAvailable":true,"initialized":false,"renderedDoctorOptions":0} |
| B-BOOKING-LABELS | FAIL | {"unlabelledFields":["name","nik","birth_date","gender","phone","email","address"]} |
| B-WORKER-LOCAL | PASS | {"pendingBefore":15,"pendingAfter":0,"failedJobs":0,"broadcastTransport":"null"} |
| B-MOBILE-HIDDEN-FOCUS | NOT TESTED | {"reason":"Complete tab-order and focus-return sequence was not established; Escape has separate reproduced evidence."} |
| B-SEED-PERMISSIONS | PASS | {"roles":5,"permissions":40} |

### Pemetaan seluruh route

PASS hanya berarti request tercatat; 500 terkontrol dapat merupakan ekspektasi rollback. Bug aplikasi tetap FAIL pada matriks fitur. Nama route dipakai untuk binding yang dinormalisasi.

| Route | Method registry | Fitur | Observasi | HTTP yang terlihat | Contoh test ID (maks 2; semua di JSONL) |
| --- | --- | --- | --- | --- | --- |
| public.clinic.show | GET\|HEAD | F13 | PASS: request tercatat | 200,404 | Tests\Feature\PublicClinicTest::test_active_clinic_public_page_lists_only_active_services_and_doctors; Tests\Feature\PublicClinicTest::test_inactive_clinic_public_page_is_not_accessible |
| welcome | GET\|HEAD | F00 | PASS: request tercatat | 200 | Tests\Feature\ExampleTest::test_landing_page_contains_accessible_mobile_navigation; Tests\Feature\ExampleTest::test_local_proxy_https_header_is_trusted |
| boost.browser-logs | POST | F33 | NOT TESTED pada recorder PHP |  |  |
| admin.login | GET\|HEAD | F01 | PASS: request tercatat | 200 | Tests\Feature\Auth\AuthenticationTest::test_admin_login_accepts_staff_and_hides_patient_google_login |
| admin.audit-logs.index | GET\|HEAD | F28 | PASS: request tercatat | 200,403 | Tests\Feature\AuditLogViewerTest::test_admin_can_filter_only_their_clinics_safe_audit_data; Tests\Feature\AuditLogViewerTest::test_non_admin_cannot_open_audit_viewer |
| admin.clinic.index | GET\|HEAD | F08 | PASS: request tercatat | 302 | Tests\Feature\FullSystemAuditTest::test_admin_without_a_clinic_can_open_the_setup_form; Tests\Feature\FullSystemAuditTest::test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit |
| admin.clinic.store | POST | F08 | PASS: request tercatat | 302 | Tests\Feature\Admin\ClinicManagementTest::test_clinic_admin_can_create_clinic_with_operating_settings |
| admin.clinic.create | GET\|HEAD | F08 | PASS: request tercatat | 200 | Tests\Feature\FullSystemAuditTest::test_admin_without_a_clinic_can_open_the_setup_form |
| admin.clinic.update | PUT\|PATCH | F08 | PASS: request tercatat | 302 | Tests\Feature\Admin\ClinicManagementTest::test_clinic_admin_can_remove_the_current_logo |
| admin.clinic.edit | GET\|HEAD | F08 | PASS: request tercatat | 200,403 | Tests\Feature\Admin\ClinicManagementTest::test_clinic_admin_cannot_edit_another_clinic; Tests\Feature\Admin\ClinicManagementTest::test_clinic_admin_can_remove_the_current_logo |
| admin.dashboard | GET\|HEAD | F06 | PASS: request tercatat | 200,302,403 | Tests\Feature\DashboardAccessTest::test_users_reach_their_own_dashboard#admin; Tests\Feature\DashboardAccessTest::test_guests_are_redirected_to_login#admin |
| admin.doctor-schedules.index | GET\|HEAD | F10 | PASS: request tercatat | 200,403 | Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Receptionist |
| admin.doctor-schedules.store | POST | F10 | PASS: request tercatat | 302 | Tests\Feature\Admin\DoctorScheduleManagementTest::test_clinic_admin_can_create_schedule_within_operating_hours; Tests\Feature\Admin\DoctorScheduleManagementTest::test_overlapping_schedule_is_rejected |
| admin.doctor-schedules.create | GET\|HEAD | F10 | PASS: request tercatat | 200 | Tests\Feature\FullSystemAuditTest::test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit |
| admin.doctor-schedules.update | PUT\|PATCH | F10 | PASS: request tercatat | 302 | Tests\Feature\FullSystemAuditTest::test_schedule_edit_preserves_the_doctor_of_existing_bookings |
| admin.doctor-schedules.edit | GET\|HEAD | F10 | PASS: request tercatat | 200 | Tests\Feature\FullSystemAuditTest::test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit |
| admin.services.index | GET\|HEAD | F11 | PASS: request tercatat | 200,403 | Tests\Feature\SubscriptionEnforcementTest::test_expired_subscription_blocks_new_writes_but_keeps_reads_and_active_visit_completion; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin |
| admin.services.store | POST | F11 | PASS: request tercatat | 302,403 | Tests\Feature\Admin\ServiceManagementTest::test_clinic_admin_can_create_service_for_own_clinic; Tests\Feature\SubscriptionEnforcementTest::test_owner_can_deactivate_an_excess_seat_and_restore_free_operations |
| admin.services.create | GET\|HEAD | F11 | PASS: request tercatat | 200 | Tests\Feature\FullSystemAuditTest::test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit |
| admin.services.update | PUT\|PATCH | F11 | PASS: request tercatat | 302 | Tests\Feature\FullSystemAuditTest::test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit |
| admin.services.edit | GET\|HEAD | F11 | PASS: request tercatat | 403 | Tests\Feature\Admin\ServiceManagementTest::test_clinic_admin_cannot_edit_service_from_another_clinic |
| admin.staff.index | GET\|HEAD | F09 | PASS: request tercatat | 200,403 | Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Receptionist |
| admin.staff.store | POST | F09 | PASS: request tercatat | 302 | Tests\Feature\Admin\StaffManagementTest::test_clinic_admin_can_create_doctor_and_assign_services; Tests\Feature\SubscriptionEnforcementTest::test_free_rejects_new_and_reactivated_seats_at_the_server |
| admin.staff.create | GET\|HEAD | F09 | PASS: request tercatat | 200 | Tests\Feature\Admin\StaffManagementTest::test_clinic_admin_can_create_doctor_and_assign_services |
| admin.staff.update | PUT\|PATCH | F09 | PASS: request tercatat | 302 | Tests\Feature\Admin\StaffManagementTest::test_clinic_admin_can_change_staff_password; Tests\Feature\SubscriptionEnforcementTest::test_free_rejects_new_and_reactivated_seats_at_the_server |
| admin.staff.edit | GET\|HEAD | F09 | PASS: request tercatat | 403 | Tests\Feature\Admin\StaffManagementTest::test_clinic_admin_cannot_edit_staff_from_another_clinic |
| auth.google.redirect | GET\|HEAD | F04 | PASS: request tercatat | 302 | Tests\Feature\Auth\GoogleAuthenticationTest::test_patient_can_be_redirected_to_google |
| auth.google.callback | GET\|HEAD | F04 | PASS: request tercatat | 302 | Tests\Feature\Auth\GoogleAuthenticationTest::test_verified_google_account_creates_and_logs_in_a_patient; Tests\Feature\Auth\GoogleAuthenticationTest::test_google_account_links_an_existing_patient_by_verified_email |
| public.booking.cancel | POST | F16 | PASS: request tercatat | 302,403,404,422,500 | Tests\Feature\BookingTest::test_status_requires_secret_token_and_cancellation_releases_capacity; Tests\Feature\ClinicNotificationDeliveryTest::test_cross_clinic_public_cancel_commits_and_notifies_only_target_recipients |
| public.booking.status | GET\|HEAD | F16 | PASS: request tercatat | 200,404 | Tests\Feature\BookingTest::test_status_requires_secret_token_and_cancellation_releases_capacity; Tests\Feature\PublicCancellationTest::test_public_cancel_uses_target_subscription_for_guests_and_logged_in_users#guest free |
| (unnamed) | GET\|POST\|HEAD | F33 | NOT TESTED pada recorder PHP |  |  |
| public.booking.create | GET\|HEAD | F14 | PASS: request tercatat | 200,302,404 | Tests\Feature\BookingTest::test_public_booking_form_has_a_submit_button; Tests\Feature\BookingTest::test_guest_cannot_open_or_submit_public_booking |
| public.booking.store | POST | F14 | PASS: request tercatat | 302,403 | Tests\Feature\BookingTest::test_guest_cannot_open_or_submit_public_booking; Tests\Feature\BookingTest::test_authenticated_patient_booking_creates_patient_appointment_and_queue_atomically |
| password.confirm | GET\|HEAD | F04 | PASS: request tercatat | 200 | Tests\Feature\Auth\PasswordConfirmationTest::test_confirm_password_screen_can_be_rendered |
| dashboard | GET\|HEAD | F06 | PASS: request tercatat | 200,302 | Tests\Feature\Auth\AuthenticationTest::test_navigation_menu_can_be_rendered; Tests\Feature\Auth\RegistrationTest::test_new_users_can_register |
| public.queue.display | GET\|HEAD | F26 | PASS: request tercatat | 200,404 | Tests\Feature\QueueManagementTest::test_public_queue_display_route_accessible_by_slug_and_id_and_aborts_404_for_invalid |
| doctor.dashboard | GET\|HEAD | F06 | PASS: request tercatat | 200,302,403 | Tests\Feature\DashboardAccessTest::test_users_reach_their_own_dashboard#doctor; Tests\Feature\DashboardAccessTest::test_guests_are_redirected_to_login#doctor |
| doctor.medical-records.index | GET\|HEAD | F19/F20/F21 | PASS: request tercatat | 200,403 | Tests\Feature\PlatformAccessTest::test_platform_admin_cannot_open_tenant_or_medical_routes; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin |
| doctor.queues.index | GET\|HEAD | F18 | PASS: request tercatat | 200,403 | Tests\Feature\QueueManagementTest::test_doctor_only_sees_and_calls_own_queue; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin |
| doctor.visits.start | POST | F19/F20/F21 | PASS: request tercatat | 200,302,403 | Tests\Feature\MedicalRecordAccessTest::test_only_assigned_doctor_can_open_medical_record; Tests\Feature\MedicalRecordAccessTest::test_admin_and_receptionist_cannot_open_medical_contents |
| doctor.visits.show | GET\|HEAD | F19/F20/F21 | PASS: request tercatat | 200,403 | Tests\Feature\MedicalRecordAccessTest::test_only_assigned_doctor_can_open_medical_record; Tests\Feature\MedicalRecordAccessTest::test_admin_and_receptionist_cannot_open_medical_contents |
| doctor.visits.allergies.store | POST | F22 | PASS: request tercatat | 200,403,422 | Tests\Feature\PartialWorkflowCompletionTest::test_allergy_changes_preserve_history_and_invalidate_stale_editors; Tests\Feature\PartialWorkflowCompletionTest::test_allergy_endpoints_reject_administrative_roles_and_invalid_values |
| doctor.visits.allergies.update | PUT | F22 | PASS: request tercatat | 200,404 | Tests\Feature\PartialWorkflowCompletionTest::test_allergy_changes_preserve_history_and_invalidate_stale_editors; Tests\Feature\PartialWorkflowCompletionTest::test_correction_of_allergy_details_preserves_the_previous_clinical_values |
| doctor.visits.complete | PUT | F19/F20/F21 | PASS: request tercatat | 200,302,409,422 | Tests\Feature\MedicalRecordCorrectionTest::test_final_record_rejects_draft_edits_and_correction_without_reason; Tests\Feature\MedicalRecordCorrectionTest::test_versioned_correction_records_reason_before_and_after_snapshot |
| doctor.visits.correct | PUT | F25 | PASS: request tercatat | 200,302 | Tests\Feature\MedicalRecordCorrectionTest::test_final_record_rejects_draft_edits_and_correction_without_reason; Tests\Feature\MedicalRecordCorrectionTest::test_versioned_correction_records_reason_before_and_after_snapshot |
| doctor.visits.save | PUT | F19/F20/F21 | PASS: request tercatat | 200,302,403,409,422,500 | Tests\Feature\MedicalRecordAccessTest::test_only_assigned_doctor_can_open_medical_record; Tests\Feature\MedicalRecordAccessTest::test_admin_and_receptionist_cannot_open_medical_contents |
| doctor.visits.photos.show | GET\|HEAD | F24 | PASS: request tercatat | 200,403,404 | Tests\Feature\VisitWorkflowTest::test_doctor_can_save_simplified_record_with_private_photo_and_without_vital_signs |
| password.request | GET\|HEAD | F04 | PASS: request tercatat | 200 | Tests\Feature\Auth\PasswordResetTest::test_reset_password_link_screen_can_be_rendered |
| (unnamed) | GET\|HEAD | F33 | NOT TESTED pada recorder PHP |  |  |
| (unnamed) | GET\|HEAD | F33 | NOT TESTED pada recorder PHP |  |  |
| livewire.preview-file | GET\|HEAD | F33 | NOT TESTED pada recorder PHP |  |  |
| default.livewire.update | POST | F33 | PASS: request tercatat | 200 | Tests\Feature\Auth\AccountSecurityTest::test_inactive_users_cannot_start_a_session_even_with_remember_me; Tests\Feature\Auth\AccountSecurityTest::test_login_is_rate_limited_after_five_failed_attempts |
| livewire.upload-file | POST | F33 | NOT TESTED pada recorder PHP |  |  |
| login | GET\|HEAD | F01 | PASS: request tercatat | 200 | Tests\Feature\Auth\AuthenticationTest::test_login_screen_can_be_rendered; Tests\Feature\Auth\GoogleAuthenticationTest::test_patient_authentication_pages_show_google_option |
| notifications.index | GET\|HEAD | F27 | PASS: request tercatat | 200,302 | Tests\Feature\ClinicNotificationDeliveryTest::test_notification_center_keeps_recipient_ownership_with_committed_delivery; Tests\Feature\NotificationCenterTest::test_user_can_read_only_their_own_notification |
| notifications.read-all | POST | F27 | PASS: request tercatat | 302 | Tests\Feature\FullSystemAuditTest::test_read_all_notifications_does_not_mark_another_accounts_notifications |
| notifications.read | POST | F27 | PASS: request tercatat | 302,404 | Tests\Feature\ClinicNotificationDeliveryTest::test_notification_center_keeps_recipient_ownership_with_committed_delivery; Tests\Feature\NotificationCenterTest::test_user_can_read_only_their_own_notification |
| onboarding.clinic.create | GET\|HEAD | F07 | PASS: request tercatat | 200,302,403 | Tests\Feature\OwnerOnboardingTest::test_owner_creates_exactly_one_clinic_and_resumes_at_plan_selection; Tests\Feature\OwnerOnboardingTest::test_non_owner_cannot_open_owner_onboarding |
| onboarding.clinic.store | POST | F07 | PASS: request tercatat | 302 | Tests\Feature\OwnerOnboardingTest::test_owner_creates_exactly_one_clinic_and_resumes_at_plan_selection; Tests\Feature\OwnerOnboardingTest::test_invalid_clinic_data_does_not_create_a_partial_tenant |
| onboarding.plans.index | GET\|HEAD | F29 | PASS: request tercatat | 200,403 | Tests\Feature\OwnerOnboardingTest::test_owner_creates_exactly_one_clinic_and_resumes_at_plan_selection; Tests\Feature\OwnerOnboardingTest::test_non_owner_cannot_open_owner_onboarding |
| onboarding.plans.free | POST | F29 | PASS: request tercatat | 302 | Tests\Feature\OwnerOnboardingTest::test_free_activation_is_idempotent_and_unlocks_tenant_routes |
| onboarding.plans.paid | POST | F29 | PASS: request tercatat | 302 | Tests\Feature\DokuPaymentTest::test_owner_checkout_creates_a_snapshot_without_activating_access; Tests\Feature\DokuPaymentTest::test_gateway_failure_records_failed_invoice_without_damaging_existing_subscription |
| onboarding.plans.trial | POST | F29 | PASS: request tercatat | 302 | Tests\Feature\OwnerOnboardingTest::test_trial_activation_is_idempotent_and_creates_one_period |
| patient.appointments.index | GET\|HEAD | F17 | PASS: request tercatat | 200,403 | Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Receptionist |
| patient.appointments.show | GET\|HEAD | F17 | PASS: request tercatat | 200,403 | Tests\Feature\AppointmentAuthorizationTest::test_patient_detail_and_cancel_require_ownership#own without clinic; Tests\Feature\AppointmentAuthorizationTest::test_patient_detail_and_cancel_require_ownership#own with clinic |
| patient.appointments.cancel | POST | F17 | PASS: request tercatat | 302,403,422 | Tests\Feature\AppointmentAuthorizationTest::test_patient_detail_and_cancel_require_ownership#own without clinic; Tests\Feature\AppointmentAuthorizationTest::test_patient_detail_and_cancel_require_ownership#own with clinic |
| patient.dashboard | GET\|HEAD | F06 | PASS: request tercatat | 200,302,403 | Tests\Feature\DashboardAccessTest::test_users_reach_their_own_dashboard#patient; Tests\Feature\DashboardAccessTest::test_guests_are_redirected_to_login#patient |
| doku.notifications | POST | F30 | PASS: request tercatat | 200,401,422 | Tests\Feature\DokuPaymentTest::test_invalid_notification_signature_is_rejected; Tests\Feature\DokuPaymentTest::test_valid_success_notification_activates_once_across_replays |
| platform.addon-prices.update | PATCH | F32 | PASS: request tercatat | 302 | Tests\Feature\FullSystemAuditTest::test_platform_commercial_actions_preserve_invoice_and_owner_history |
| platform.dashboard | GET\|HEAD | F32 | PASS: request tercatat | 200,302,403 | Tests\Feature\DashboardAccessTest::test_users_reach_their_own_dashboard#platform; Tests\Feature\DashboardAccessTest::test_guests_are_redirected_to_login#platform |
| platform.payments.index | GET\|HEAD | F30 | PASS: request tercatat | 200,403 | Tests\Feature\DokuPaymentTest::test_payment_history_is_private_to_owner_and_available_to_platform; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin |
| platform.payments.show | GET\|HEAD | F30 | PASS: request tercatat | 200 | Tests\Feature\FullSystemAuditTest::test_platform_commercial_actions_preserve_invoice_and_owner_history |
| platform.payments.reconcile | POST | F30 | PASS: request tercatat | 302 | Tests\Feature\FullSystemAuditTest::test_platform_commercial_actions_preserve_invoice_and_owner_history |
| platform.payments.refund | POST | F30 | PASS: request tercatat | 302 | Tests\Feature\DokuPaymentTest::test_platform_manual_refund_preserves_paid_period |
| platform.subscription-plans.index | GET\|HEAD | F32 | PASS: request tercatat | 200,403 | Tests\Feature\PlatformSubscriptionManagementTest::test_platform_admin_can_manage_catalog_and_grant_audited_access; Tests\Feature\PlatformSubscriptionManagementTest::test_tenant_owner_cannot_manage_platform_catalog_or_grants |
| platform.subscription-plans.update | PATCH | F32 | PASS: request tercatat | 302,403 | Tests\Feature\PlatformSubscriptionManagementTest::test_platform_admin_can_manage_catalog_and_grant_audited_access; Tests\Feature\PlatformSubscriptionManagementTest::test_tenant_owner_cannot_manage_platform_catalog_or_grants |
| platform.subscription-prices.update | PATCH | F32 | PASS: request tercatat | 302,403 | Tests\Feature\PlatformSubscriptionManagementTest::test_platform_admin_can_manage_catalog_and_grant_audited_access; Tests\Feature\PlatformSubscriptionManagementTest::test_tenant_owner_cannot_manage_platform_catalog_or_grants |
| platform.tenants.index | GET\|HEAD | F32 | PASS: request tercatat | 200,403 | Tests\Feature\PlatformAccessTest::test_platform_admin_can_view_nonmedical_tenant_dashboard_and_detail; Tests\Feature\PlatformAccessTest::test_tenant_users_cannot_open_platform_routes |
| platform.tenants.show | GET\|HEAD | F32 | PASS: request tercatat | 200,403 | Tests\Feature\PlatformAccessTest::test_platform_admin_can_view_nonmedical_tenant_dashboard_and_detail; Tests\Feature\PlatformAccessTest::test_tenant_users_cannot_open_platform_routes |
| platform.subscription-grants.store | POST | F32 | PASS: request tercatat | 302,403 | Tests\Feature\PlatformSubscriptionManagementTest::test_platform_admin_can_manage_catalog_and_grant_audited_access; Tests\Feature\PlatformSubscriptionManagementTest::test_grant_requires_a_reason_and_an_active_catalog_entry |
| previews.visit-summary | GET\|HEAD | F33 | PASS: request tercatat | 200 | Tests\Feature\VisitSummaryComponentTest::test_local_preview_renders_populated_and_empty_mock_states |
| profile | GET\|HEAD | F05 | PASS: request tercatat | 200,302 | Tests\Feature\Auth\AccountSecurityTest::test_an_existing_session_is_invalidated_when_the_account_is_deactivated; Tests\Feature\OwnerOnboardingTest::test_owner_creates_exactly_one_clinic_and_resumes_at_plan_selection |
| public.queue.index | GET\|HEAD | F26 | PASS: request tercatat | 200 | Tests\Feature\QueueManagementTest::test_public_queue_board_contains_no_patient_identity |
| queues.call | POST | F18 | PASS: request tercatat | 200,302,403,422 | Tests\Feature\QueueManagementTest::test_queue_actions_return_json_for_staff_ui; Tests\Feature\QueueManagementTest::test_queue_json_action_returns_validation_error_for_stale_status |
| queues.cancel | POST | F18 | PASS: request tercatat | 302 | Tests\Feature\QueueManagementTest::test_cancellation_and_no_show_are_synchronized_with_appointment |
| queues.check-in | POST | F18 | PASS: request tercatat | 200,302,403 | Tests\Feature\QueueManagementTest::test_receptionist_can_view_and_check_in_own_clinic_queue; Tests\Feature\QueueManagementTest::test_queue_actions_return_json_for_staff_ui |
| queues.no-show | POST | F18 | PASS: request tercatat | 302 | Tests\Feature\QueueManagementTest::test_cancellation_and_no_show_are_synchronized_with_appointment; Tests\Feature\QueueManagementTest::test_no_show_is_rejected_before_session_starts |
| queues.return | POST | F18 | PASS: request tercatat | 302 | Tests\Feature\QueueManagementTest::test_called_queue_can_be_skipped_returned_and_called_again |
| queues.skip | POST | F18 | PASS: request tercatat | 200,302 | Tests\Feature\QueueManagementTest::test_queue_actions_return_json_for_staff_ui; Tests\Feature\QueueManagementTest::test_called_queue_can_be_skipped_returned_and_called_again |
| receptionist.appointments.index | GET\|HEAD | F17 | PASS: request tercatat | 200,403 | Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Receptionist |
| receptionist.appointments.store | POST | F17 | PASS: request tercatat | 302,422 | Tests\Feature\BookingTest::test_receptionist_can_book_an_explicit_existing_patient; Tests\Feature\PartialWorkflowCompletionTest::test_walk_in_uses_duplicate_review_without_auto_linking |
| receptionist.appointments.create | GET\|HEAD | F17 | PASS: request tercatat | 200 | Tests\Feature\BookingTest::test_receptionist_booking_form_has_a_submit_button; Tests\Feature\PartialWorkflowCompletionTest::test_forms_render_shared_filters_and_structured_editor |
| receptionist.appointments.show | GET\|HEAD | F17 | PASS: request tercatat | 200,403 | Tests\Feature\AppointmentAuthorizationTest::test_staff_endpoint_preserves_roles_and_tenant_boundaries#receptionist own clinic; Tests\Feature\AppointmentAuthorizationTest::test_staff_endpoint_preserves_roles_and_tenant_boundaries#admin own clinic |
| receptionist.appointments.cancel | POST | F17 | PASS: request tercatat | 302,403 | Tests\Feature\AppointmentAuthorizationTest::test_staff_endpoint_preserves_roles_and_tenant_boundaries#receptionist own clinic; Tests\Feature\AppointmentAuthorizationTest::test_staff_endpoint_preserves_roles_and_tenant_boundaries#admin own clinic |
| receptionist.dashboard | GET\|HEAD | F06 | PASS: request tercatat | 200,302,403 | Tests\Feature\DashboardAccessTest::test_users_reach_their_own_dashboard#receptionist; Tests\Feature\DashboardAccessTest::test_guests_are_redirected_to_login#receptionist |
| receptionist.patients.index | GET\|HEAD | F12 | PASS: request tercatat | 200,403 | Tests\Feature\PatientManagementTest::test_receptionist_can_register_and_search_patient_in_own_clinic; Tests\Feature\FullSystemAuditTest::test_each_role_is_checked_at_operational_endpoints#Clinic Admin |
| receptionist.patients.store | POST | F12 | PASS: request tercatat | 302,422 | Tests\Feature\PartialWorkflowCompletionTest::test_duplicate_review_requires_confirmation_and_is_tenant_scoped; Tests\Feature\PatientManagementTest::test_receptionist_can_register_and_search_patient_in_own_clinic |
| receptionist.patients.create | GET\|HEAD | F12 | PASS: request tercatat | 200 | Tests\Feature\PartialWorkflowCompletionTest::test_forms_render_shared_filters_and_structured_editor |
| receptionist.patients.duplicates | POST | F12 | PASS: request tercatat | 200,403 | Tests\Feature\PartialWorkflowCompletionTest::test_duplicate_review_requires_confirmation_and_is_tenant_scoped; Tests\Feature\PartialWorkflowCompletionTest::test_walk_in_uses_duplicate_review_without_auto_linking |
| receptionist.patients.search | GET\|HEAD | F12 | PASS: request tercatat | 200 | Tests\Feature\BookingTest::test_receptionist_patient_search_returns_only_active_patients_from_their_clinic |
| receptionist.patients.show | GET\|HEAD | F12 | PASS: request tercatat | 404 | Tests\Feature\PatientManagementTest::test_receptionist_cannot_view_patient_from_another_clinic |
| receptionist.patients.update | PUT\|PATCH | F12 | PASS: request tercatat | 302 | Tests\Feature\FullSystemAuditTest::test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit |
| receptionist.patients.edit | GET\|HEAD | F12 | PASS: request tercatat | 200 | Tests\Feature\FullSystemAuditTest::test_catalog_and_patient_updates_persist_without_repricing_an_existing_visit |
| receptionist.queues.index | GET\|HEAD | F18 | PASS: request tercatat | 200,403 | Tests\Feature\QueueManagementTest::test_receptionist_can_view_and_check_in_own_clinic_queue; Tests\Feature\QueueManagementTest::test_staff_cannot_manage_queue_from_another_clinic |
| register | GET\|HEAD | F02 | PASS: request tercatat | 200 | Tests\Feature\Auth\GoogleAuthenticationTest::test_patient_authentication_pages_show_google_option; Tests\Feature\Auth\RegistrationTest::test_registration_screen_can_be_rendered |
| register.clinic | GET\|HEAD | F03 | PASS: request tercatat | 200 | Tests\Feature\OwnerOnboardingTest::test_owner_registration_is_separate_and_requires_email_verification |
| password.reset | GET\|HEAD | F04 | PASS: request tercatat | 200 | Tests\Feature\Auth\PasswordResetTest::test_reset_password_screen_can_be_rendered |
| storage.local | GET\|HEAD | F33 | NOT TESTED pada recorder PHP |  |  |
| storage.local.upload | PUT | F33 | NOT TESTED pada recorder PHP |  |  |
| subscription.addons.index | GET\|HEAD | F31 | PASS: request tercatat | 200,500 | Tests\Feature\OdontogramAddonTest::test_owner_can_buy_prorated_addon_after_paid_plan_is_active; Tests\Feature\OdontogramAddonTest::test_second_checkout_for_same_period_is_blocked_and_pending_invoice_is_visible |
| subscription.addons.store | POST | F31 | PASS: request tercatat | 302 | Tests\Feature\OdontogramAddonTest::test_owner_can_buy_prorated_addon_after_paid_plan_is_active; Tests\Feature\OdontogramAddonTest::test_free_and_trial_cannot_purchase_odontogram |
| subscription.payments.index | GET\|HEAD | F30 | PASS: request tercatat | 200 | Tests\Feature\FullSystemAuditTest::test_platform_commercial_actions_preserve_invoice_and_owner_history |
| subscription.payments.show | GET\|HEAD | F30 | PASS: request tercatat | 200,403,404 | Tests\Feature\DokuPaymentTest::test_owner_checkout_creates_a_snapshot_without_activating_access; Tests\Feature\DokuPaymentTest::test_payment_history_is_private_to_owner_and_available_to_platform |
| subscription.payments.reconcile | POST | F30 | PASS: request tercatat | 302 | Tests\Feature\DokuPaymentTest::test_owner_can_reconcile_a_pending_payment |
| (unnamed) | GET\|HEAD | F33 | NOT TESTED pada recorder PHP |  |  |
| verification.notice | GET\|HEAD | F04 | PASS: request tercatat | 200 | Tests\Feature\Auth\EmailVerificationTest::test_email_verification_screen_can_be_rendered |
| verification.verify | GET\|HEAD | F04 | PASS: request tercatat | 403 | Tests\Feature\Auth\EmailVerificationTest::test_email_is_not_verified_with_invalid_hash |

### Route tanpa observasi PHP

Sembilan baris berikut adalah framework/health/tooling, bukan fitur bisnis aplikasi. Beberapa asset Livewire dimuat Chrome, tetapi tidak dihitung sebagai request dari recorder PHPUnit. Tidak ada klaim seluruh method/variasi framework diuji.

| Route | Method | URI | Batas |
| --- | --- | --- | --- |
| boost.browser-logs | POST | _boost/browser-logs | NOT TESTED pada recorder PHP |
| (unnamed) | GET\|POST\|HEAD | broadcasting/auth | NOT TESTED pada recorder PHP |
| (unnamed) | GET\|HEAD | livewire/livewire.js | NOT TESTED pada recorder PHP |
| (unnamed) | GET\|HEAD | livewire/livewire.min.js.map | NOT TESTED pada recorder PHP |
| livewire.preview-file | GET\|HEAD | livewire/preview-file/{filename} | NOT TESTED pada recorder PHP |
| livewire.upload-file | POST | livewire/upload-file | NOT TESTED pada recorder PHP |
| storage.local | GET\|HEAD | storage/{path} | NOT TESTED pada recorder PHP |
| storage.local.upload | PUT | storage/{path} | NOT TESTED pada recorder PHP |
| (unnamed) | GET\|HEAD | up | NOT TESTED pada recorder PHP |

### Pemetaan seluruh view

PASS render berarti request 200 tercatat; bukan bukti seluruh state/role. Komponen yang tidak diuji independen tetap NOT TESTED untuk seluruh state-nya.

| View | Entry point | Hasil terbatas |
| --- | --- | --- |
| resources/views/admin/audit-logs/index.blade.php | admin.audit-logs.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/clinic/form.blade.php | admin.clinic.create, admin.clinic.edit, onboarding.clinic.create | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/dashboard.blade.php | admin.dashboard | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/schedules/form.blade.php | admin.doctor-schedules.create, admin.doctor-schedules.edit | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/schedules/index.blade.php | admin.doctor-schedules.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/services/form.blade.php | admin.services.create, admin.services.edit | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/services/index.blade.php | admin.services.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/staff/form.blade.php | admin.staff.create, admin.staff.edit | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/admin/staff/index.blade.php | admin.staff.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/components/action-message.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/application-logo.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/appointment-queue-status.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/auth-session-status.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/booking-fields.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/clinic-button.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/critical-allergy-banner.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/danger-button.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/dental-chart.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/dropdown.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/dropdown-link.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/input-error.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/input-label.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/medical-record-modification.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/modal.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/nav-link.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/patient-duplicate-review.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/primary-button.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/public-layout.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/public-queue-screen.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/queue-card.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/queue-number-display.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/receptionist/search-select.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/responsive-nav-link.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/rme/allergy-editor.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/rme/allergy-fields.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/rme/autosave-status.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/rme/input-field.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/rme/section.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/rme/textarea-field.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/secondary-button.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/status-badge.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/text-input.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/components/visit-summary.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/dashboard.blade.php | dashboard, patient.dashboard | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/doctor/dashboard.blade.php | doctor.dashboard | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/doctor/medical-records/index.blade.php | doctor.medical-records.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/doctor/visits/show.blade.php | doctor.visits.show | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/errors/404.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/layouts/app.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/layouts/guest.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/livewire/layout/navigation.blade.php | Komposisi layout/page; bukan route mandiri | FAIL QA-09 Escape; menu render PASS |
| resources/views/livewire/pages/auth/confirm-password.blade.php | password.confirm | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/livewire/pages/auth/forgot-password.blade.php | password.request | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/livewire/pages/auth/login.blade.php | login, admin.login | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/livewire/pages/auth/register.blade.php | register | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/livewire/pages/auth/register-clinic.blade.php | register.clinic | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/livewire/pages/auth/reset-password.blade.php | password.reset | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/livewire/pages/auth/verify-email.blade.php | verification.notice | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/livewire/profile/delete-user-form.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/livewire/profile/update-password-form.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/livewire/profile/update-profile-information-form.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/livewire/welcome/navigation.blade.php | Komposisi layout/page; bukan route mandiri | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/notifications/index.blade.php | notifications.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/onboarding/plans/index.blade.php | onboarding.plans.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/patient/appointments/index.blade.php | patient.appointments.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/patient/appointments/show.blade.php | patient.appointments.show | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/platform/dashboard.blade.php | platform.dashboard | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/platform/subscription-plans/index.blade.php | platform.subscription-plans.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/platform/tenants/index.blade.php | platform.tenants.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/platform/tenants/show.blade.php | platform.tenants.show | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/previews/visit-summary.blade.php | previews.visit-summary | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/profile.blade.php | profile | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/public/booking.blade.php | public.booking.create | FAIL QA-01 browser; HTTP render ada |
| resources/views/public/booking-status.blade.php | public.booking.status | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/public/clinic.blade.php | public.clinic.show | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/public/queue.blade.php | public.queue.index, public.queue.display | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/queues/index.blade.php | doctor.queues.index, receptionist.queues.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/receptionist/appointments/form.blade.php | receptionist.appointments.create | FAIL QA-10 labels; existing booking PASS |
| resources/views/receptionist/appointments/index.blade.php | receptionist.appointments.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/receptionist/appointments/show.blade.php | receptionist.appointments.show | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/receptionist/dashboard.blade.php | receptionist.dashboard | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/receptionist/patients/form.blade.php | receptionist.patients.create, receptionist.patients.edit | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/receptionist/patients/index.blade.php | receptionist.patients.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/receptionist/patients/show.blade.php | receptionist.patients.show | NOT TESTED sebagai halaman/state independen; lihat assertion/bukti browser terkait |
| resources/views/subscription/addons/index.blade.php | subscription.addons.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/subscription/payments/index.blade.php | subscription.payments.index, platform.payments.index | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/subscription/payments/show.blade.php | subscription.payments.show, platform.payments.show | PASS render pada request 200 teramati; seluruh state NOT TESTED |
| resources/views/welcome.blade.php | welcome | PASS render pada request 200 teramati; seluruh state NOT TESTED |
