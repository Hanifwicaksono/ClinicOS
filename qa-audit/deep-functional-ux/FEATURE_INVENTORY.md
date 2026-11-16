> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.

# Feature Inventory — penemuan independen

Run: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome 158.0.8127.36, headless nyata**; database/storage/sesi/build disposable.

PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.

## Cakupan penemuan

**36 kelompok fitur aktual**, lima role tersimpan, owner sebagai persona Clinic Admin, dan guest/token holder. 51 keluarga URL diobservasi dalam 213 snapshot role/page; setiap snapshot mempunyai probe lebar375/768/1024/1440. 119 route pada registry adalah alat rekonsiliasi, bukan jumlah fitur atau bukti kelulusan.

| ID | Fitur | Aktor | Aksi dan lifecycle | Status/state aktual | Bukti run |
| --- | --- | --- | --- | --- | --- |
| F00 | Landing, harga publik, navigasi awal | Guest, Patient, Clinic Owner | lihat manfaat/paket; pilih pendaftaran/login | anonymous; authenticated; 375/768/1024/1440 | UX-01 |
| F01 | Login, logout, portal, password recovery | All roles | login; wrong password; remember; logout; forgot/reset; role revoked | active/inactive; verified/unverified; role present/revoked | DISC-LOGIN-admin, DISC-LOGOUT-admin, DISC-LOGIN-receptionist, DISC-LOGOUT-receptionist, DISC-LOGIN-doctor, DISC-LOGOUT-doctor, DISC-LOGIN-dentist, DISC-LOGOUT-dentist, DISC-LOGIN-patient, DISC-LOGOUT-patient, DISC-LOGIN-platform, DISC-LOGOUT-platform, DISC-LOGIN-free-owner, DISC-LOGOUT-free-owner, DISC-LOGIN-expired-owner, DISC-LOGOUT-expired-owner, DISC-LOGIN-suspended-owner, DISC-LOGOUT-suspended-owner, RETEST-01, AUTH-GOOGLE, AUTH-01-R2, AUTH-07-R3, KEY-01, ROLE-CHANGE |
| F02 | Registrasi pasien dan pemilik | Guest | daftar; terms; validasi duplicate/mismatch; verify/resend | patient/clinic_owner; unverified/verified | AUTH-02, AUTH-05 |
| F03 | Profil dan lifecycle akun sendiri | All roles | edit nama/email; ganti password; verifikasi ulang; delete dengan password | email changed/unverified; no history/clinical history; owner with clinic | AUTH-06-R1, AUTH-10, AUTH-09-R2, PROFILE-EMAIL-R1, DOCTOR-DELETE-GUARD |
| F04 | Onboarding klinik dan aktivasi awal | Clinic Owner | buat klinik; pilih Free; trial Team sekali; pilih paket berbayar | no clinic; no subscription; free; trialing; active | AUTH-03, AUTH-04-R1 |
| F05 | Profil klinik, jam, logo dan status | Clinic Admin, Clinic Owner | lihat/edit identitas; logo upload/remove; slug; jam; timezone; aktif/nonaktif | active/inactive; logo absent/present; reserved/valid slug | ADM-14, CLINIC-LOGO, CLINIC-STATE, CLINIC-SLUG |
| F06 | Akun dokter/resepsionis dan relasi layanan | Clinic Admin, Clinic Owner | create; edit profil/password/email; assignment layanan; deactivate/reactivate; kapasitas | Doctor/Receptionist; unverified/verified; active/inactive; seat available/full | ADM-04, ADM-05, ADM-06, ADM-07, ADM-08, AUTH-08, SUB-SEATS |
| F07 | Katalog layanan klinik | Clinic Admin, Clinic Owner | create; edit tarif/durasi/deskripsi; deactivate/reactivate; duplicate validation | active/inactive; unused/booked snapshot; duration5..480 | ADM-01, ADM-02, ADM-03, SERVICE-05, BROWSER-01 |
| F08 | Sesi praktik dokter | Clinic Admin, Clinic Owner | create; edit doctor/day/time/quota; overlap; deactivate; booked-session guard | active/inactive; empty/referenced; quota1..500; day0..6 | RETEST-02, BOOK-01, CROSS-08 |
| F09 | Pasien administratif | Clinic Admin, Clinic Owner, Receptionist | create; detail/history; edit; search; pagination; deactivate/reactivate | active/inactive; no/one/many records; identity validation | ADM-10, ADM-11, ADM-13, PAT-05, LIST-01-R1, UX-05-R1 |
| F10 | Website klinik dan penemuan booking | Guest, Patient | lihat klinik/dokter/layanan/sesi; masuk dari booking; cari aksi booking ulang | active/inactive clinic; logged out/in; no prior booking/history | ADM-12, UX-02, DISCOVERY-NEGATIVE |
| F11 | Booking online, walk-in dan pasien terdaftar | Patient, Clinic Admin, Clinic Owner, Receptionist | pilih dokter/date/service/session; new/existing patient; submit; idempotency; quota; snapshot | BOOKED; date today..+30; matching session; full/inactive/unavailable | BOOK-02-R1, BOOK-03-R1, BOOK-04-R1, CROSS-01-R1, BOOK-QUOTA, JOURNEY-02-R1 |
| F12 | Detail dan pembatalan appointment | Patient, Clinic Admin, Clinic Owner, Receptionist, Token holder | list/detail; cancel with reason; confirm/dismiss; reopen | BOOKED/CANCELLED/COMPLETED/NO_SHOW; before/after session; queue IN_PROGRESS | BOOK-05-R1, CROSS-04, CROSS-05, CROSS-06 |
| F13 | Operasional antrean dan filter | Clinic Admin, Clinic Owner, Receptionist, Doctor | check-in; call FIFO; skip; return; no-show; cancel; filter date/doctor/session/status | BOOKED/WAITING/CALLED/IN_PROGRESS/COMPLETED/SKIPPED/CANCELLED/NO_SHOW | QUEUE-01-R1, QUEUE-02-R1, QUEUE-03-R1, QUEUE-04-R1, QUEUE-06-R1, QUEUE-07-R1, CROSS-02, CROSS-07, QUEUE-08, CROSS-03 |
| F14 | Display antrean publik | Guest, All roles | lihat board/display; filter yang tersedia; status nonmedis; responsive | queue empty/active/terminal; no patient identifiers | BOOK-07, WS-RECOVERY |
| F15 | Mulai, buka dan cari pemeriksaan sendiri | Doctor | start dari CALLED; reopen active/final; medical-record index; assigned-doctor policy | CALLED→IN_PROGRESS; IN_PROGRESS/COMPLETED; assigned/unrelated doctor | QUEUE-05-R1, RME-11, RME-12-R2, CROSS-03, CROSS-09 |
| F16 | RME General, vital, diagnosis/tindakan dan autosave | Doctor | SOAP; vitals; structured add/remove; autosave/manual draft; retry; stale version; offline recovery | DRAFT/FINAL; dirty/saving/saved/error/conflict; numeric bounds | RME-01, RME-02, RME-05, RME-06, DENT-03, JOURNEY-02-R1 |
| F17 | Alergi longitudinal pasien | Doctor | create; edit severity/reaction; deactivate; critical banner; acknowledge prescription | MILD/MODERATE/SEVERE/CRITICAL; active/inactive; stale allergy version | RME-03, RME-10 |
| F18 | Resep dokter dan resep final milik pasien | Doctor, Patient | add/remove item; dosage/frequency/quantity/unit/instructions/notes; preview; own final prescription | draft/incomplete/complete; final/modified; critical-allergy acknowledgement | RME-04, JOURNEY-02-R1 |
| F19 | Foto medis privat dan retensi revisi | Doctor | select/paste; preview/remove; manual upload; private view; historical view | pending/saved/removed; draft/final/revision before/after | RME-07, PHOTO-HISTORY |
| F20 | Finalisasi, Modification dan version history | Doctor | confirm/dismiss finalize; complete; correction reason; revision compare; preserve history | DRAFT→FINAL; COMPLETED; FINAL remains FINAL after correction | RME-08-R2, RME-09-R3, CROSS-12, JOURNEY-02-R1 |
| F21 | Odontogram dan entitlement | Doctor | select FDI tooth; apply condition; persist; edit correction; read-only history | 32 permanent teeth; unknown/healthy/caries/filled/missing/crown; active/revoked/expired addon | DENT-01, DENT-02 |
| F22 | Dashboard setiap role dan filter periode | Clinic Admin, Clinic Owner, Receptionist, Doctor, Patient, Platform Admin | lihat tugas/status; periode; shortcut; operational/service-value metrics; prescriptions | empty/populated; valid/reversed range; role-specific content | UX-04, JOURNEY-02-R1 |
| F23 | Notification center | All roles | list; open/mark read; read-all; badge; navigate resource | unread/read; empty/paginated; own/foreign notification | CROSS-10 |
| F24 | Audit aktivitas tenant | Clinic Admin, Clinic Owner | filter tanggal/aksi/aktor; pagination; inspect safe references | empty/populated; filtered/unfiltered; tenant scoped | RETEST-03, UX-03 |
| F25 | Subscription tenant, invoice dan pembatasan operasional | Clinic Owner, All roles (banner/operational rules) | history/detail; plan selection/renewal; read-only banner; seat/booking limits; finish-active-visit exception; reminders | free/trialing/active/expired/suspended; pending/paid/failed/expired/cancelled/review/refunded invoices | SUB-01, SUB-04, SUB-05, SUB-SEATS, JOURNEY-02-R1 |
| F26 | DOKU checkout, notification dan reconciliation | Clinic Owner, Platform Admin, System | checkout; return display; validated notification; reconcile; failure recovery | pending/paid/failed/expired/cancelled/review/refunded; valid/invalid callback | SUB-02, DOKU-SUCCESS, DOKU-RECONCILE, DOKU-WEBHOOK |
| F27 | Pembelian, status dan histori add-on owner | Clinic Owner | eligibility; prorated quote; combined/separate checkout; active/history/invoice; refund/revocation | unavailable/free/trial; paid eligible; pending/review; active/revoked/expired | SUB-03, ADDON-OWNER, DENT-02 |
| F28 | Tenant directory dan detail platform | Platform Admin | search; list; metadata/detail; commercial history | no match/many tenants; free/trialing/active/expired/suspended | PLAT-01 |
| F29 | Katalog paket, harga dan add-on platform | Platform Admin | toggle plans; edit/toggle prices; edit/toggle addon; keep invoice snapshots | active/inactive; valid/invalid amount; old/new checkout snapshot | PLAT-02, PLAT-03, PLAT-04 |
| F30 | Grant dan downgrade manual platform | Platform Admin | paid grant with reason; schedule equal/higher grant; immediate downgrade Free; retain periods | no/current/future periods; active/revoked; reason absent/present | PLAT-05, GRANT-DOUBLE |
| F31 | Pencatatan refund dan event pembayaran | Platform Admin | find invoice; review/paid detail; record manual refund; inspect events/history | paid/review→refunded; repeated/invalid-state refund | PLAT-06 |
| F32 | ETA/confidence pada tracker pasien dan board | Patient, Token holder, Guest | view ETA after check-in; fallback duration; history/confidence; terminal suppression | BOOKED(no ETA); WAITING/CALLED/IN_PROGRESS; terminal; low/medium/high sample confidence | ETA-01-R2 |
| F33 | Pembaruan antrean, polling dan reconnect UI | All queue users | subscribe; refetch; polling; online/offline/reconnect; preserve focus and feedback | connected/reconnecting/disconnected/offline/refetching; stale/current DOM | ETA-01-R2, CROSS-02 |
| F34 | Login/registrasi Google | Guest, Patient | terms acknowledgement; OAuth redirect/callback; linked-account handling | provider configured/unavailable; new/existing account; callback success/failure | AUTH-GOOGLE |
| F35 | Review kandidat pasien duplikat | Clinic Admin, Clinic Owner, Receptionist | detect candidate; identity review; choose existing profile; confirm different person; signed review token | no/one/many candidates; reviewed/unreviewed; shared family phone; inactive candidate | ADM-12 |

## Kelengkapan per fitur

Kejelasan di bawah adalah evaluasi heuristik berdasarkan interaksi, bukan pengukuran pemahaman pengguna awam. Tidak ada studi pengguna atau screen reader fisik.

### F00 — Landing, harga publik, navigasi awal

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /; welcome.blade.php. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-10. |
| Aksi penting tersedia? | lihat manfaat/paket; pilih pendaftaran/login. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: UX-01 FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-10: Geser halaman dan clipping kecil mengganggu kestabilan navigasi pada layar sempit. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-10. Navigasi Back/Forward landing pada seluruh anchor; Katalog harga kosong/nonaktif seluruhnya dan pemulihan. |

### F01 — Login, logout, portal, password recovery

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /login; /admin; /forgot-password; /reset-password/{token}. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | login; wrong password; remember; logout; forgot/reset; role revoked. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: DISC-LOGIN-admin PASS; DISC-LOGOUT-admin PASS; DISC-LOGIN-receptionist PASS; DISC-LOGOUT-receptionist PASS; DISC-LOGIN-doctor PASS; DISC-LOGOUT-doctor PASS; DISC-LOGIN-dentist PASS; DISC-LOGOUT-dentist PASS; DISC-LOGIN-patient PASS; DISC-LOGOUT-patient PASS; DISC-LOGIN-platform PASS; DISC-LOGOUT-platform PASS; DISC-LOGIN-free-owner PASS; DISC-LOGOUT-free-owner PASS; DISC-LOGIN-expired-owner PASS; DISC-LOGOUT-expired-owner PASS; DISC-LOGIN-suspended-owner PASS; DISC-LOGOUT-suspended-owner PASS; RETEST-01 PASS; AUTH-GOOGLE BLOCKED; AUTH-01-R2 PASS; AUTH-07-R3 PASS; KEY-01 PASS; ROLE-CHANGE PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Remember-only session dan revokasi password pada browser baru; Reset/verification link kedaluwarsa dan pemakaian ulang lewat UI. |

### F02 — Registrasi pasien dan pemilik

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /register; /register/clinic; /verify-email. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | daftar; terms; validasi duplicate/mismatch; verify/resend. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: AUTH-02 PASS; AUTH-05 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Email registrasi duplikat/case variant melalui UI pasien dan owner; Terms tidak disetujui serta refresh di setiap tahap registrasi. |

### F03 — Profil dan lifecycle akun sendiri

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /profile; livewire/profile/*. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | edit nama/email; ganti password; verifikasi ulang; delete dengan password. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: AUTH-06-R1 PASS; AUTH-10 PASS; AUTH-09-R2 PASS; PROFILE-EMAIL-R1 PASS; DOCTOR-DELETE-GUARD PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Email duplikat dan batas panjang nama pada seluruh role; Dua sesi profil aktif saat password/email berubah. |

### F04 — Onboarding klinik dan aktivasi awal

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /onboarding/clinic; /onboarding/plans. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | buat klinik; pilih Free; trial Team sekali; pilih paket berbayar. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: AUTH-03 PASS; AUTH-04-R1 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Lanjut onboarding yang ditinggalkan setelah logout/login; Preferred plan dari setiap CTA landing sampai aktivasi. |

### F05 — Profil klinik, jam, logo dan status

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /admin/clinic; /admin/clinic/{id}/edit. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | lihat/edit identitas; logo upload/remove; slug; jam; timezone; aktif/nonaktif. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: ADM-14 PASS; CLINIC-LOGO PASS; CLINIC-STATE PASS; CLINIC-SLUG PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Timezone WITA/WIT dan perubahan jam operasional terhadap sesi existing; Logo tipe salah, ukuran lebih dari2MB, dan penggantian gagal. |

### F06 — Akun dokter/resepsionis dan relasi layanan

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /admin/staff; /admin/staff/create; /admin/staff/{id}/edit. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | create; edit profil/password/email; assignment layanan; deactivate/reactivate; kapasitas. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: ADM-04 PASS; ADM-05 PASS; ADM-06 PASS; ADM-07 PASS; ADM-08 PASS; AUTH-08 PASS; SUB-SEATS PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Admin mengganti email/password staf dengan sesi remember aktif; Batas doctor seat dari UI dan semua variasi entitlement paket. |

### F07 — Katalog layanan klinik

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /admin/services; /admin/services/create; /admin/services/{id}/edit. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | create; edit tarif/durasi/deskripsi; deactivate/reactivate; duplicate validation. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: ADM-01 PASS; ADM-02 PASS; ADM-03 PASS; SERVICE-05 PASS; BROWSER-01 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Tarif pecahan/nonkelipatan1000 dan batas nominal server; Layanan dinonaktifkan ketika tab booking lama masih terbuka. |

### F08 — Sesi praktik dokter

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /admin/doctor-schedules; create; {id}/edit. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-02. |
| Aksi penting tersedia? | create; edit doctor/day/time/quota; overlap; deactivate; booked-session guard. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: RETEST-02 PASS; BOOK-01 PASS; CROSS-08 FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-02: Pasien dan staf dapat mengikuti tanggal yang berbeda dengan sesi dokter; filter sesi dan histori tidak lagi mempunyai acuan hari yang konsisten. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-02. Perubahan jam sesi terpakai serta quota diturunkan di bawah reservasi; Batas quota0/501, akhir sebelum mulai, dan jam di luar operasional melalui UI. |

### F09 — Pasien administratif

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /receptionist/patients; create; {id}; {id}/edit; search. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-05, DFU-06. |
| Aksi penting tersedia? | create; detail/history; edit; search; pagination; deactivate/reactivate. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: ADM-10 PASS; ADM-11 FAIL; ADM-13 PASS; PAT-05 PASS; LIST-01-R1 PASS; UX-05-R1 FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-05: Resepsionis diarahkan mengulang pemeriksaan kandidat, padahal yang harus diperbaiki adalah panjang telepon. DFU-06: Data kontak tidak dapat dipakai untuk komunikasi atau pencocokan kandidat yang diharapkan staf. |
| Validasi/error membantu? | Tidak konsisten; lihat temuan validasi/feedback terkait. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-05, DFU-06. Tanggal lahir masa depan dan seluruh field kosong melalui submit UI; Pasien dengan histori sangat panjang dan setiap filter pencarian identitas. |

### F10 — Website klinik dan penemuan booking

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Website klinik ditemukan lewat URL klinik; portal pasien kehilangan entry booking baru (DFU-11). |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-11. |
| Aksi penting tersedia? | lihat klinik/dokter/layanan/sesi; masuk dari booking; cari aksi booking ulang. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Entry booking baru/ulang tidak ditemukan di portal. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: ADM-12 PASS; UX-02 FAIL; DISCOVERY-NEGATIVE PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-11: Pengguna baru/kembali harus mengetahui URL eksternal atau meminta bantuan untuk memulai tugas utama. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-11. Semua CTA dokter/layanan website klinik dalam kondisi tanpa dokter/sesi; Alur pengguna pertama tanpa URL klinik dalam studi awam langsung. |

### F11 — Booking online, walk-in dan pasien terdaftar

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /clinic/{slug}/booking; /receptionist/appointments/create. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-02, DFU-11. |
| Aksi penting tersedia? | pilih dokter/date/service/session; new/existing patient; submit; idempotency; quota; snapshot. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: BOOK-02-R1 PASS; BOOK-03-R1 PASS; BOOK-04-R1 PASS; CROSS-01-R1 PASS; BOOK-QUOTA PASS; JOURNEY-02-R1 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-02: Pasien dan staf dapat mengikuti tanggal yang berbeda dengan sesi dokter; filter sesi dan histori tidak lagi mempunyai acuan hari yang konsisten. DFU-11: Pengguna baru/kembali harus mengetahui URL eksternal atau meminta bantuan untuk memulai tugas utama. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-02, DFU-11. Tanggal tepat+30/+31 dan sesi baru saja berakhir; Dua form/tab dengan UUID berbeda untuk pasien/sesi sama. |

### F12 — Detail dan pembatalan appointment

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /patient/appointments; /receptionist/appointments; /booking/{id}/status. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-01, DFU-02, DFU-04. |
| Aksi penting tersedia? | list/detail; cancel with reason; confirm/dismiss; reopen. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: BOOK-05-R1 PASS; CROSS-04 FAIL; CROSS-05 FAIL; CROSS-06 FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Ya: pemeriksaan yang dibatalkan pasien tidak dapat difinalisasi. |
| Validasi/error membantu? | Tidak konsisten; lihat temuan validasi/feedback terkait. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-01, DFU-02, DFU-04. Replay cancel dari tab stale untuk seluruh status terminal; Tautan status tanpa/salah token dan cancellation error pada template resepsionis lewat browser. |

### F13 — Operasional antrean dan filter

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /receptionist/queues; /doctor/queues; /queues/{id}/{action}. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-01, DFU-03. |
| Aksi penting tersedia? | check-in; call FIFO; skip; return; no-show; cancel; filter date/doctor/session/status. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: QUEUE-01-R1 PASS; QUEUE-02-R1 FAIL; QUEUE-03-R1 PASS; QUEUE-04-R1 FAIL; QUEUE-06-R1 PASS; QUEUE-07-R1 FAIL; CROSS-02 FAIL; CROSS-07 PASS; QUEUE-08 PASS; CROSS-03 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Ya: pemeriksaan yang dibatalkan pasien tidak dapat difinalisasi. |
| Validasi/error membantu? | Tidak konsisten; lihat temuan validasi/feedback terkait. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-01, DFU-03. Aksi stale dari dua tab resepsionis dan dokter terhadap queue sama; Filter dokter/sesi dikombinasikan dengan pagination dan status terminal. |

### F14 — Display antrean publik

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /display/{slugOrToken}; {clinic}.localhost/queue. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | lihat board/display; filter yang tersedia; status nonmedis; responsive. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: BOOK-07 PASS; WS-RECOVERY BLOCKED. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Display klinik kosong dan seluruh kontrol filter/kiosk; Public display putus/pulih dari socket nyata. |

### F15 — Mulai, buka dan cari pemeriksaan sendiri

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /doctor/queues; /doctor/medical-records; /doctor/visits/{id}. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-01, DFU-08. |
| Aksi penting tersedia? | start dari CALLED; reopen active/final; medical-record index; assigned-doctor policy. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: QUEUE-05-R1 PASS; RME-11 PASS; RME-12-R2 PASS; CROSS-03 PASS; CROSS-09 FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Ya: pemeriksaan yang dibatalkan pasien tidak dapat difinalisasi. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-01, DFU-08. Ulang Mulai periksa dari tab lama sesudah visit selesai; Semua kombinasi pencarian/filter index rekam medis. |

### F16 — RME General, vital, diagnosis/tindakan dan autosave

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /doctor/visits/{id}; /draft; resources/js/rme-autosave.js. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | SOAP; vitals; structured add/remove; autosave/manual draft; retry; stale version; offline recovery. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: RME-01 PASS; RME-02 PASS; RME-05 PASS; RME-06 PASS; DENT-03 PASS; JOURNEY-02-R1 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: HTTP419/sesi berakhir saat autosave dan pemulihan isi editor; Batas20 diagnosis/30 tindakan serta seluruh nilai vital ekstrem; Refresh/Back/leave sebelum debounce autosave selesai dan ketika foto belum disimpan. |

### F17 — Alergi longitudinal pasien

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /doctor/visits/{id}/allergies; allergy editor. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | create; edit severity/reaction; deactivate; critical banner; acknowledge prescription. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: RME-03 PASS; RME-10 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Edit severity lain dan dua editor alergi bersamaan melalui UI; Semua variasi acknowledgement alergi pada resep baru/koreksi. |

### F18 — Resep dokter dan resep final milik pasien

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /doctor/visits/{id}; /patient/dashboard (Rx only). Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | add/remove item; dosage/frequency/quantity/unit/instructions/notes; preview; own final prescription. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Cetak/katalog obat/engine interaksi belum ditemukan; perlu scope produk. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: RME-04 PASS; JOURNEY-02-R1 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Hapus/tambah30 obat, jumlah9999/10000, serta resep kosong final; Seluruh variasi preview, instruksi panjang dan koreksi yang mengubah resep pasien. |

### F19 — Foto medis privat dan retensi revisi

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /doctor/visits/{id}/photos/{index}; revision photo links. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | select/paste; preview/remove; manual upload; private view; historical view. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: RME-07 PASS; PHOTO-HISTORY PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Paste clipboard, batas10 foto/5MB, tipe salah dan abort upload; Akses foto revisi oleh setiap role/tenant asing melalui browser. |

### F20 — Finalisasi, Modification dan version history

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /doctor/visits/{id}/complete; /correct; version history. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-01. |
| Aksi penting tersedia? | confirm/dismiss finalize; complete; correction reason; revision compare; preserve history. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: RME-08-R2 PASS; RME-09-R3 PASS; CROSS-12 FAIL; JOURNEY-02-R1 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Ya: pemeriksaan yang dibatalkan pasien tidak dapat difinalisasi. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-01. Dua submit finalisasi bersamaan dan tab koreksi stale melalui UI; Semua perbedaan array panjang pada histori koreksi di mobile. |

### F21 — Odontogram dan entitlement

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: dental-chart.blade.php; medical_records.odontogram. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | select FDI tooth; apply condition; persist; edit correction; read-only history. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: DENT-01 PASS; DENT-02 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Seluruh32 gigi×6 kondisi dan correction-final dental melalui UI; Expiry berdasarkan ends_at; pencabutan revoked_at sudah diuji, keduanya tidak disamakan. |

### F22 — Dashboard setiap role dan filter periode

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /admin/dashboard; /receptionist/dashboard; /doctor/dashboard; /patient/dashboard; /platform/dashboard. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-07, DFU-08. |
| Aksi penting tersedia? | lihat tugas/status; periode; shortcut; operational/service-value metrics; prescriptions. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: UX-04 FAIL; JOURNEY-02-R1 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-07: Admin dapat membaca ringkasan periode lama sambil mengira filter baru sudah diterapkan. DFU-08: Dokter/pengelola dapat salah memahami jam layanan dan mencocokkan histori dengan antrean atau laporan. |
| Validasi/error membantu? | Tidak konsisten; lihat temuan validasi/feedback terkait. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-07, DFU-08. Rekonsiliasi angka seluruh dashboard terhadap fixture setiap periode/timezone; Semua shortcut, empty/loading dan variasi data panjang per role. |

### F23 — Notification center

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /notifications; /notifications/read-all; /notifications/{id}/read. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | list; open/mark read; read-all; badge; navigate resource. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: CROSS-10 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Pagination/empty notification center untuk setiap role; Notifikasi masuk socket dan badge realtime lintas browser. |

### F24 — Audit aktivitas tenant

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /admin/audit-logs. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | filter tanggal/aksi/aktor; pagination; inspect safe references. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: RETEST-03 PASS; UX-03 FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Kombinasi filter aktor/tanggal, pagination, serta tidak ada hasil; Validasi semua metadata audit yang ditampilkan di browser. |

### F25 — Subscription tenant, invoice dan pembatasan operasional

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /subscription/payments; /onboarding/plans; SubscriptionService. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-12. |
| Aksi penting tersedia? | history/detail; plan selection/renewal; read-only banner; seat/booking limits; finish-active-visit exception; reminders. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: SUB-01 PASS; SUB-04 PASS; SUB-05 PASS; SUB-SEATS PASS; JOURNEY-02-R1 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-12: Klinik mendapat masa akses lebih panjang daripada yang dimaksud operator; histori komersial memuat grant ganda dan perlu koreksi administratif. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-12. Transisi expiry oleh waktu serta reminder subscription yang dipicu command; Seluruh kombinasi paket, renewal terjadwal dan seat overage pada UI. |

### F26 — DOKU checkout, notification dan reconciliation

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: DokuPaymentService; DokuClient; /payments/doku/notification; payments/{id}/reconcile. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | checkout; return display; validated notification; reconcile; failure recovery. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: SUB-02 PASS; DOKU-SUCCESS BLOCKED; DOKU-RECONCILE BLOCKED; DOKU-WEBHOOK BLOCKED. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Successful checkout/notification/reconcile provider nyata; Repeat/delay callback dan browser-return nyata bersama provider. |

### F27 — Pembelian, status dan histori add-on owner

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /subscription/addons; /subscription/addons/odontogram. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | eligibility; prorated quote; combined/separate checkout; active/history/invoice; refund/revocation. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: SUB-03 PASS; ADDON-OWNER PASS; DENT-02 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Prorata akhir hari/bulan dan renewal gabungan lewat UI; Refund yang mencabut add-on melalui flow provider; fixture revoke saja sudah diuji. |

### F28 — Tenant directory dan detail platform

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /platform/tenants; /platform/tenants/{id}. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | search; list; metadata/detail; commercial history. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Suspend/unsuspend/revoke UI tidak ditemukan; MF-05. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: PLAT-01 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Pencarian owner-email, pagination banyak tenant, dan tenant inactive; Semua metadata commercial history di setiap state tenant. |

### F29 — Katalog paket, harga dan add-on platform

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /platform/subscription-plans; subscription-prices/{id}; addon-prices/{id}. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | toggle plans; edit/toggle prices; edit/toggle addon; keep invoice snapshots. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: PLAT-02 PASS; PLAT-03 PASS; PLAT-04 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Harga invalid, price toggle inactive dan addon toggle inactive lewat UI; Katalog diubah ketika owner masih memegang tab checkout lama. |

### F30 — Grant dan downgrade manual platform

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /platform/tenants/{id}/subscription-grants. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-12. |
| Aksi penting tersedia? | paid grant with reason; schedule equal/higher grant; immediate downgrade Free; retain periods. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: PLAT-05 PASS; GRANT-DOUBLE FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-12: Klinik mendapat masa akses lebih panjang daripada yang dimaksud operator; histori komersial memuat grant ganda dan perlu koreksi administratif. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-12. Grant lebih tinggi/setara terjadwal dan downgrade paid dengan overlap; Pemulihan grant ganda dan kebijakan revoke grant yang belum tersedia di UI. |

### F31 — Pencatatan refund dan event pembayaran

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /platform/payments; /platform/payments/{id}/refund. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | find invoice; review/paid detail; record manual refund; inspect events/history. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: PLAT-06 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Refund ulang pada tab stale dan seluruh status yang tidak refundable; Reconcile+refund lintas role/tenant pada UI, tanpa transaksi provider nyata. |

### F32 — ETA/confidence pada tracker pasien dan board

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: QueueEtaService; appointment-queue-status; public queue. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | view ETA after check-in; fallback duration; history/confidence; terminal suppression. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: ETA-01-R2 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Confidence medium/high dengan data historis terkontrol di browser; ETA untuk CALLED/IN_PROGRESS/terminal dan seluruh kombinasi antrian depan. |

### F33 — Pembaruan antrean, polling dan reconnect UI

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: resources/js/realtime.js; queue-realtime.js. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-03. |
| Aksi penting tersedia? | subscribe; refetch; polling; online/offline/reconnect; preserve focus and feedback. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Ada skenario FAIL. Bukti terpilih: ETA-01-R2 PASS; CROSS-02 FAIL. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-03: Staf tidak memahami mengapa klik tidak bekerja dan cenderung mengulang aksi atau salah menafsirkan state antrean. |
| Validasi/error membantu? | Tidak konsisten; lihat temuan validasi/feedback terkait. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-03. Socket aktif putus/pulih, missed-event dan permission revoked pada koneksi aktif; Fokus input saat refetch/pagination dan semua offline queue actions. |

### F34 — Login/registrasi Google

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: /auth/google; /auth/google/callback. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Tidak ada kesimpulan universal dari label yang tampak jelas. |
| Aksi penting tersedia? | terms acknowledgement; OAuth redirect/callback; linked-account handling. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | BLOCKED. Bukti terpilih: AUTH-GOOGLE BLOCKED. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh. |
| Validasi/error membantu? | Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Fungsi terpilih dibuktikan, dengan batas: Google OAuth success/callback/error/relink dengan akun dummy provider. |

### F35 — Review kandidat pasien duplikat

| Pertanyaan kelengkapan | Jawaban berbasis bukti |
| --- | --- |
| Dapat ditemukan? | Entry/source: PatientDuplicateService; /receptionist/patients/duplicates; patient-duplicate-review. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json. |
| Dapat dipahami? | Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. Hambatan terkonfirmasi: DFU-05. |
| Aksi penting tersedia? | detect candidate; identity review; choose existing profile; confirm different person; signed review token. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis. |
| Ada aksi/proses hilang? | Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik. |
| Bisa selesai end-to-end? | Aksi terpilih terbukti; belum menyeluruh. Bukti terpilih: ADM-12 PASS. |
| Data dapat dikelola kembali? | Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS. |
| Pengguna terjebak? | DFU-05: Resepsionis diarahkan mengulang pemeriksaan kandidat, padahal yang harus diperbaiki adalah panjang telepon. |
| Validasi/error membantu? | Tidak konsisten; lihat temuan validasi/feedback terkait. |
| Parsial? | Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus. |
| Menyelesaikan kebutuhan? | Ada hambatan DFU-05. NIK format/normalisasi telepon berbagai negara dan lebih dari8 kandidat; Review token stale setelah kandidat berubah dan navigasi ulang ke profil inactive. |

## Inventaris halaman, formulir dan kontrol

| Keluarga URL | Persona yang diobservasi | Field yang terlihat pada snapshot | Aksi terlihat (contoh, maksimal12) | Probe |
| --- | --- | --- | --- | --- |
| 127.0.0.1/admin/dashboard | admin, free-owner, expired-owner, suspended-owner, operational-admin | from, to | Keluar; Terapkan | 10 |
| 127.0.0.1/subscription/payments | admin, free-owner, expired-owner, suspended-owner |  | Keluar | 4 |
| 127.0.0.1/subscription/addons | admin, free-owner, expired-owner, suspended-owner |  | Keluar | 4 |
| 127.0.0.1/admin/clinic/:id/edit | admin, free-owner, expired-owner, suspended-owner, operational-admin | name, slug, email, phone, address, description, logo, opening_time, closing_time, timezone, queue_prefix, is_active | Keluar; Simpan perubahan | 5 |
| 127.0.0.1/admin/staff | admin, free-owner, expired-owner, suspended-owner, operational-admin |  | Keluar | 5 |
| 127.0.0.1/admin/services | admin, free-owner, expired-owner, suspended-owner, operational-admin |  | Keluar | 5 |
| 127.0.0.1/admin/doctor-schedules | admin, free-owner, expired-owner, suspended-owner, operational-admin |  | Keluar | 5 |
| 127.0.0.1/receptionist/patients | admin, receptionist, free-owner, expired-owner, suspended-owner, operational-admin | search | Keluar; Cari | 6 |
| 127.0.0.1/receptionist/appointments | admin, receptionist, free-owner, expired-owner, suspended-owner, operational-admin | date | Keluar; Tampilkan | 6 |
| 127.0.0.1/receptionist/queues | admin, receptionist, free-owner, expired-owner, suspended-owner, operational-admin | date, doctor_id_search, doctor_schedule_id_search, status | Keluar; Terapkan filter; Check-in; No-show; Batalkan; Panggil; Kembalikan; Lewati | 6 |
| 127.0.0.1/admin/audit-logs | admin, free-owner, expired-owner, suspended-owner, operational-admin | from, to, action, actor | Keluar; Terapkan filter | 5 |
| 127.0.0.1/notifications | admin, receptionist, doctor, dentist, patient, platform, free-owner, expired-owner, suspended-owner, operational-admin |  | Keluar; Tandai semua dibaca; Ringkasan operasional tersedia Data kunjungan dan antrean demo hari ini siap ditinjau. 1 minute ago; Subscription segera berakhir Paket Team akan berakhir dalam 7 hari. 1 minute ago; Pasien sudah check-in Antrean KS-102 sekarang berstatus Menunggu. 2 minutes ago; Pemeriksaan aktif Antrean KS-104 siap dilanjutkan pada halaman pemeriksaan. 2 minutes ago; Booking berhasil dibuat Booking KLI-183IZGJF di Klinik Sehat Sentosa telah dikonfirmasi. 2 minutes ago; Antrean dokter gigi dipanggil Antrean KS-103 telah dipanggil. 3 minutes ago; Booking berhasil dibuat Booking demo Anda telah dikonfirmasi oleh Klinik Sehat Sentosa. 3 minutes ago; Pembayaran demo diproses Invoice DEMO-INV-PAID berhasil diproses sebagai data demonstrasi. 3 minutes ago | 10 |
| 127.0.0.1/profile | admin, receptionist, doctor, dentist, patient, platform, free-owner, expired-owner, suspended-owner | name, email, current_password, password, password_confirmation | Keluar; Simpan profil; Simpan kata sandi; Hapus akun | 18 |
| 127.0.0.1/onboarding/plans | admin, free-owner, expired-owner, suspended-owner, new-owner | odontogram | Keluar; Bayar paket Rp 149.000; Bayar paket Rp 799.000; Bayar paket Rp 1.399.000; Bayar paket Rp 499.000; Bayar paket Rp 2.699.000; Bayar paket Rp 4.999.000; Gunakan Free; Coba Team 14 hari | 5 |
| 127.0.0.1/subscription/payments/:id | admin, owner |  | Keluar | 9 |
| 127.0.0.1/admin/staff/create | admin, free-owner, expired-owner, suspended-owner | name, email, phone, role, password, specialization, license_number, bio, service_ids[] | Keluar; Buat akun staf | 4 |
| 127.0.0.1/admin/staff/:id/edit | admin | name, email, phone, password, specialization, license_number, bio, service_ids[], is_active | Keluar; Simpan perubahan | 3 |
| 127.0.0.1/admin/services/create | admin, free-owner, expired-owner, suspended-owner | name, price, duration_minutes, description, is_active | Keluar; Tambah layanan | 4 |
| 127.0.0.1/admin/services/:id/edit | admin | name, price, duration_minutes, description, is_active | Keluar; Simpan perubahan | 3 |
| 127.0.0.1/admin/doctor-schedules/create | admin, free-owner, expired-owner, suspended-owner | doctor_id, day_of_week, quota, start_time, end_time, is_active | Keluar; Tambah jadwal | 4 |
| 127.0.0.1/admin/doctor-schedules/:id/edit | admin | doctor_id, day_of_week, quota, start_time, end_time, is_active | Keluar; Simpan perubahan | 3 |
| 127.0.0.1/receptionist/patients/create | admin, receptionist, free-owner, expired-owner, suspended-owner | name, nik, birth_date, gender, phone, email, address, is_active, duplicate_reviewed | Keluar; Periksa kembali; Simpan pasien | 5 |
| 127.0.0.1/receptionist/patients/:id | admin, receptionist |  | Keluar | 6 |
| 127.0.0.1/receptionist/appointments/create | admin, receptionist, free-owner, expired-owner, suspended-owner | patient_search, name, nik, birth_date, gender, phone, email, address, doctor_id_search, appointment_date, service_id_search, doctor_schedule_id_search, notes, duplicate_reviewed | Keluar; Pasien terdaftar; Pasien baru; Periksa kembali; Buat booking | 5 |
| 127.0.0.1/receptionist/appointments/:id | admin, receptionist | cancel_reason | Keluar; Batalkan booking | 20 |
| 127.0.0.1/receptionist/patients/:id/edit | admin, receptionist | name, nik, birth_date, gender, phone, email, address, is_active | Keluar; Simpan pasien | 6 |
| 127.0.0.1/receptionist/dashboard | receptionist | date, doctor_id | Keluar; Tampilkan; Panggil; Check-in | 2 |
| 127.0.0.1/doctor/dashboard | doctor, dentist | date | Keluar; Tampilkan; Mulai pemeriksaan | 4 |
| 127.0.0.1/doctor/queues | doctor, dentist | date, doctor_schedule_id_search, status | Keluar; Terapkan filter; Panggil; Kembalikan; Lewati; Mulai periksa | 2 |
| 127.0.0.1/doctor/medical-records | doctor, dentist | search, status, from, to | Keluar; Terapkan | 2 |
| 127.0.0.1/doctor/visits/:id | doctor, dentist, deep-doctor, doctor-dental | chief_complaint, subjective, objective, assessment, plan, physical_examination, photos[], vital_signs[weight_kg], vital_signs[height_cm], vital_signs[systolic], vital_signs[diastolic], vital_signs[pulse], vital_signs[respiratory_rate], vital_signs[temperature_c], vital_signs[oxygen_saturation], diagnoses[0][code], diagnoses[0][name], diagnoses[0][notes], diagnoses[0][is_primary], treatments[0][name], treatments[0][quantity], treatments[0][description], prescription_items[0][medicine_name], prescription_items[0][dosage], prescription_items[0][frequency], prescription_items[0][quantity], prescription_items[0][unit], prescription_items[0][instructions], prescription_notes, doctor_notes, substance, reaction, severity, is_active, correction_reason, treatments[1][name], treatments[1][quantity], treatments[1][description] | Keluar; Hapus diagnosis 1; + Tambah diagnosis; Hapus tindakan 1; + Tambah tindakan; + Tambah obat; Hapus obat; Simpan draft; Tinjau & finalisasi; + Catat alergi; ; Simpan Modification | 7 |
| 127.0.0.1/patient/dashboard | patient |  | Keluar | 2 |
| 127.0.0.1/patient/appointments | patient |  | Keluar | 1 |
| 127.0.0.1/patient/appointments/:id | patient | cancel_reason | Keluar; Batalkan booking | 2 |
| 127.0.0.1/platform/dashboard | platform |  | Keluar | 2 |
| 127.0.0.1/platform/tenants | platform | search | Keluar; Cari | 1 |
| 127.0.0.1/platform/subscription-plans | platform | monthly_amount, is_active, amount | Keluar; Simpan add-on; Nonaktifkan paket; Simpan harga | 1 |
| 127.0.0.1/platform/payments | platform | search | Keluar; Cari | 1 |
| 127.0.0.1/platform/tenants/:id | platform | subscription_price_id, reason | Keluar; Berikan akses; Terapkan Free sekarang | 4 |
| 127.0.0.1/platform/payments/:id | platform | reason | Keluar; Tandai refunded | 3 |
| 127.0.0.1/ | guest |  | Apakah ada paket gratis? +; Apakah paket dapat diubah ketika klinik berkembang? +; Apakah akses staf dan dokter dibedakan? +; Bagaimana cara mulai menggunakan ClinicOS? + | 1 |
| 127.0.0.1/login | guest | email, password, remember | Lihat kata sandi; Masuk | 2 |
| 127.0.0.1/admin | guest | email, password, remember | Lihat kata sandi; Masuk | 1 |
| 127.0.0.1/register | guest | name, email, password, password_confirmation, accept_terms | Buat akun; Daftar dengan Google | 1 |
| 127.0.0.1/register/clinic | guest | name, email, password, password_confirmation, accept_terms | Buat akun pemilik | 1 |
| 127.0.0.1/forgot-password | guest | email | Kirim tautan reset | 1 |
| klinik-sehat-sentosa.localhost/ | guest |  |  | 1 |
| 127.0.0.1/display/klinik-sehat-sentosa | guest |  |  | 2 |
| 127.0.0.1/verify-email | new-staff |  | Kirim ulang email verifikasi; Keluar | 1 |
| 127.0.0.1/onboarding/clinic | new-owner | name, slug, email, phone, address, description, logo, opening_time, closing_time, timezone, queue_prefix | Keluar; Buat klinik | 1 |
| 127.0.0.1/booking/:id/status | patient-mobile | cancel_reason | Batalkan booking | 1 |

Kontrol lengkap dan kondisi terlihat/disabled, form/action, dropdown/modal serta seluruh nama field ada di [pages.json](evidence/pages.json). Kandidat kontrol dari **seluruh Blade** (termasuk cabang yang belum dirender): [source-controls.json](evidence/source-controls.json). Ekstraksi statis berbasis tag mempunyai batas pada markup multiline/dinamis; data runtime dan inspeksi source tetap acuan, bukan hitungan regex.

## Entitas/lifecycle yang sengaja tidak diperlakukan sebagai CRUD hapus

Patient/history: edit/deactivate; staf: deactivate/reactivate; layanan/jadwal: active flag; RME final: Modification beralasan; payment/subscription period/event: histori/refund/grant. Appointment/queue: terminal states, bukan delete. Owner tetap role Clinic Admin dengan owner_user_id; satu owner/satu klinik.
