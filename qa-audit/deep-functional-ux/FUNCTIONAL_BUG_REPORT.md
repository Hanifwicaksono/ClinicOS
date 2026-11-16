> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.

# Functional Bug Report

Run: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome 158.0.8127.36, headless nyata**; database/storage/sesi/build disposable.

PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.

Temuan terkonfirmasi OPEN; tidak ada perbaikan source pada tugas audit ini. Kelompok akar masalah dipakai agar empat assertion feedback queue tidak dihitung empat bug.

## DFU-12 — Double-click grant memberi dua periode dari satu tindakan pengguna

| Atribut | Detail |
| --- | --- |
| Kategori | Functional Bug / Cross-Feature Inconsistency |
| Severity | P2 |
| Fitur / role | F30/F25; Platform Admin, Clinic Owner |
| Kondisi awal | Tenant QA Free tersedia. Platform Admin memilih Individual satu bulan dan mengisi satu alasan grant. |
| Langkah reproduksi | 1. Buka detail tenant QA. → 2. Pilih Individual satu bulan dan isi satu alasan. → 3. Double-click Berikan akses menggunakan input pointer Chrome dengan jarak 10 ms. → 4. Hitung periode subscription yang baru tersimpan. |
| Hasil aktual | Satu double-click membuat dua periode subscription. Form grant tidak mengunci submit dan tidak membawa idempotency key; permintaan kedua dapat dianggap grant berikutnya dan menambah masa akses. |
| Hasil yang diharapkan | Satu maksud pemberian akses menghasilkan satu grant. Pengulangan transport/double-click tidak menambahkan periode kedua. |
| Dampak pengguna | Klinik mendapat masa akses lebih panjang daripada yang dimaksud operator; histori komersial memuat grant ganda dan perlu koreksi administratif. |
| Rekomendasi | Tambahkan proteksi submit UI dan idempotency server untuk grant dengan identitas permintaan yang sama. Pertahankan kemungkinan operator melakukan grant baru secara sengaja dengan permintaan baru. |
| Skenario | GRANT-DOUBLE |
| Bukti | [grant-double-click.json](evidence/grant-double-click.json), [screenshots/GRANT-DOUBLE-result.png](evidence/screenshots/GRANT-DOUBLE-result.png) |
| Sumber terkait | [resources/views/platform/tenants/show.blade.php](../../resources/views/platform/tenants/show.blade.php), [app/Http/Controllers/Platform/SubscriptionGrantController.php](../../app/Http/Controllers/Platform/SubscriptionGrantController.php), [app/Services/SubscriptionService.php](../../app/Services/SubscriptionService.php) |

## DFU-01 — Pembatalan pasien memutus pemeriksaan yang sudah berjalan

| Atribut | Detail |
| --- | --- |
| Kategori | Cross-Feature Inconsistency / Incomplete Workflow |
| Severity | P1 |
| Fitur / role | F12/F13/F15/F20; Patient, Receptionist, Doctor |
| Kondisi awal | Klinik writable; sesi hari ini belum mencapai jam mulai; booking sudah di-check-in, dipanggil, dan dimulai dokter dari UI. Ini dapat terjadi karena start visit hanya mensyaratkan tanggal dan CALLED. |
| Langkah reproduksi | 1. Pasien buat booking untuk sesi yang mulai dua jam lagi. → 2. Resepsionis check-in dan panggil; dokter tekan Mulai periksa sebelum jam sesi. → 3. Pasien buka Booking saya → detail → Batalkan booking → setujui. → 4. Dokter lengkapi SOAP lalu Finalisasi kunjungan. |
| Hasil aktual | Appointment #25 dan queue menjadi CANCELLED, visit #16 tetap IN_PROGRESS. Finalisasi berikutnya ditolak: Status antrean tidak sesuai untuk finalisasi. Visit tertinggal tanpa jalur penyelesaian yang ditawarkan UI. |
| Hasil yang diharapkan | Pembatalan appointment harus memeriksa queue/visit di transaksi yang sama dan menolak saat pemeriksaan berlangsung, atau memakai workflow penghentian pelayanan yang secara eksplisit ditetapkan produk. |
| Dampak pengguna | Pelayanan yang sudah dimulai tidak dapat ditutup oleh dokter; status dokter, pasien, antrean dan visit saling bertentangan. |
| Rekomendasi | Perbaiki aturan cancel bersama di BookingService untuk semua caller, dengan lock dan pemeriksaan visit/queue. Tentukan pemulihan data yang telanjur tidak konsisten; pertahankan histori medis. |
| Skenario | CROSS-03, CROSS-04, CROSS-12 |
| Bukti | [in-progress-cancellation.json](evidence/in-progress-cancellation.json), [cancelled-visit-finalize.json](evidence/cancelled-visit-finalize.json), [screenshots/CROSS-04-patient-cancelled-active.png](evidence/screenshots/CROSS-04-patient-cancelled-active.png), [screenshots/CROSS-12-finalization-stuck.png](evidence/screenshots/CROSS-12-finalization-stuck.png) |
| Sumber terkait | [app/Services/BookingService.php:182](../../app/Services/BookingService.php#L182), [app/Services/BookingService.php:207](../../app/Services/BookingService.php#L207), [app/Services/VisitWorkflowService.php:143](../../app/Services/VisitWorkflowService.php#L143) |

## DFU-02 — Edit hari sesi terpakai membuat tanggal appointment tidak sesuai sesi

| Atribut | Detail |
| --- | --- |
| Kategori | Cross-Feature Inconsistency / Functional Bug |
| Severity | P2 |
| Fitur / role | F08/F11/F12; Clinic Admin, Receptionist, Patient, Doctor |
| Kondisi awal | Sesi QA dokter telah dirujuk appointment dan visit selesai. Guard existing hanya melarang perubahan dokter. |
| Langkah reproduksi | 1. Buka Jadwal dokter → edit sesi terpakai. → 2. Dengan dokter tetap sama, ubah hari Jumat menjadi Sabtu dan simpan. → 3. Buka detail booking Jumat yang sebelumnya memakai sesi tersebut. → 4. Bandingkan appointment_date dan doctor_schedule.day_of_week. |
| Hasil aktual | Update diterima. Appointment tetap hari Jumat (5), sesi kini hari Sabtu (6); relasi yang awalnya wajib cocok saat booking menjadi tidak konsisten. Jadwal fixture dipulihkan melalui UI sesudah bukti disimpan. |
| Hasil yang diharapkan | Perubahan sesi yang dirujuk tidak boleh diam-diam mengubah makna booking/histori. Bekukan atribut sesi terpakai, simpan snapshot, atau lakukan reschedule eksplisit dengan konsekuensi yang ditetapkan produk. |
| Dampak pengguna | Pasien dan staf dapat mengikuti tanggal yang berbeda dengan sesi dokter; filter sesi dan histori tidak lagi mempunyai acuan hari yang konsisten. |
| Rekomendasi | Perluas aturan sesi terpakai ke hari/jam, serta tetapkan apakah perubahan hanya berlaku untuk booking baru atau harus memigrasikan booking mendatang. Jangan mengubah histori final diam-diam. |
| Skenario | CROSS-08 |
| Bukti | [schedule-date-mismatch.json](evidence/schedule-date-mismatch.json), [screenshots/CROSS-08-old-booking-after-session-day-edit.png](evidence/screenshots/CROSS-08-old-booking-after-session-day-edit.png) |
| Sumber terkait | [app/Http/Controllers/Admin/DoctorScheduleController.php:71](../../app/Http/Controllers/Admin/DoctorScheduleController.php#L71), [app/Http/Requests/StoreDoctorScheduleRequest.php:72](../../app/Http/Requests/StoreDoctorScheduleRequest.php#L72) |

## DFU-03 — Refresh otomatis menghapus alasan penolakan aksi antrean

| Atribut | Detail |
| --- | --- |
| Kategori | UX/Usability Issue / Cross-Feature Inconsistency |
| Severity | P2 |
| Fitur / role | F13/F33; Receptionist, Clinic Admin, Doctor |
| Kondisi awal | Antrean menampilkan form aksi; lakukan aksi valid secara UI tetapi belum memenuhi prasyarat: FIFO, panggilan aktif, atau no-show sebelum jam sesi. |
| Langkah reproduksi | 1. Klik No-show sebelum sesi mulai dan setujui dialog. → 2. Server mengembalikan 422 dengan alasan yang benar. → 3. Tunggu refresh region otomatis. → 4. Coba membaca feedback; ulangi pada pelanggaran FIFO/panggilan aktif. |
| Hasil aktual | Alasan ditampilkan lalu region diganti setelah debounce 250 ms. Setelah 600 ms kotak feedback kosong. Respons 422 tersimpan di queue-error-feedback.json, sedangkan feedback hasil DOM adalah string kosong. |
| Hasil yang diharapkan | Pesan penolakan tetap tersedia hingga aksi pengguna berikutnya atau dismissal; refetch data tidak menghilangkan konteks error. |
| Dampak pengguna | Staf tidak memahami mengapa klik tidak bekerja dan cenderung mengulang aksi atau salah menafsirkan state antrean. |
| Rekomendasi | Pertahankan feedback di luar region yang diganti atau pulihkan state feedback setelah refetch; uji FIFO, active-call dan no-show sebagai pesan yang tetap terbaca. |
| Skenario | QUEUE-02-R1, QUEUE-04-R1, QUEUE-07-R1, CROSS-02 |
| Bukti | [queue-error-feedback.json](evidence/queue-error-feedback.json), [screenshots/CROSS-02-error-disappeared.png](evidence/screenshots/CROSS-02-error-disappeared.png) |
| Sumber terkait | [resources/js/realtime.js:142](../../resources/js/realtime.js#L142), [resources/js/realtime.js:252](../../resources/js/realtime.js#L252), [resources/js/realtime.js:278](../../resources/js/realtime.js#L278) |

## DFU-04 — Pembatalan yang ditolak tidak memberi penjelasan pada halaman booking

| Atribut | Detail |
| --- | --- |
| Kategori | Functional Bug / UX/Usability Issue |
| Severity | P2 |
| Fitur / role | F12; Patient, Token holder; Receptionist template has same omission |
| Kondisi awal | Booking masih BOOKED, tetapi sesi telah dimulai. Tombol Batalkan booking masih tampil. |
| Langkah reproduksi | 1. Buat booking sesi hari ini yang sudah dimulai tetapi belum berakhir. → 2. Buka detail /patient/appointments/{id}. → 3. Klik Batalkan booking dan setujui. → 4. Ulangi melalui tautan konfirmasi bertoken milik sendiri. |
| Hasil aktual | Backend mempertahankan BOOKED dan mengirim validation error melalui redirect. Kedua halaman kembali tanpa menampilkan alasan setelah sesi dimulai. Form tetap menawarkan tombol yang sama. |
| Hasil yang diharapkan | Tampilkan alasan penolakan yang dapat dibaca, serta sesuaikan ketersediaan tombol dengan aturan waktu/status saat ini. |
| Dampak pengguna | Pasien tidak tahu apakah pembatalan berhasil dan tidak mempunyai langkah pemulihan yang jelas. |
| Rekomendasi | Render validation summary/field errors pada detail pasien dan konfirmasi publik; gunakan aturan eligibility yang sama untuk tombol. Template resepsionis perlu ditinjau dan ditest terpisah. |
| Skenario | CROSS-05, CROSS-06 |
| Bukti | [screenshots/CROSS-05-no-cancellation-feedback.png](evidence/screenshots/CROSS-05-no-cancellation-feedback.png), [screenshots/CROSS-06-token-status-no-feedback.png](evidence/screenshots/CROSS-06-token-status-no-feedback.png) |
| Sumber terkait | [resources/views/patient/appointments/show.blade.php:3](../../resources/views/patient/appointments/show.blade.php#L3), [resources/views/public/booking-status.blade.php:6](../../resources/views/public/booking-status.blade.php#L6), [app/Services/BookingService.php:194](../../app/Services/BookingService.php#L194) |

## DFU-05 — Error format telepon disamarkan sebagai kegagalan pemeriksaan kandidat pasien

| Atribut | Detail |
| --- | --- |
| Kategori | Functional Bug / UX/Usability Issue |
| Severity | P2 |
| Fitur / role | F09/F35; Receptionist, Clinic Admin |
| Kondisi awal | Form Pasien baru, identitas lain valid dan tidak menyerupai kandidat duplikat. |
| Langkah reproduksi | 1. Isi nama QA Invalid Phone Length, lahir valid, dan gender. → 2. Isi telepon dengan 31 digit. → 3. Klik Simpan pasien. → 4. Periksa pesan serta row database. |
| Hasil aktual | Endpoint duplicate review menolak phone max:30 dengan422. JavaScript mengganti detail tersebut menjadi Kandidat pasien belum dapat diperiksa. Periksa data dan coba lagi. Submit pasien diblok sebelum mencapai store; tidak ada pasien baru. Tidak ada petunjuk field phone atau batas30. Attempt awal salah menunggu navigasi; reproduksi pengganti memeriksa respons duplicate review dan UI secara tepat. |
| Hasil yang diharapkan | Pesan panjang telepon tampil dekat kontrol dan/atau di summary, dengan fokus yang membantu pemulihan. |
| Dampak pengguna | Resepsionis diarahkan mengulang pemeriksaan kandidat, padahal yang harus diperbaiki adalah panjang telepon. |
| Rekomendasi | Tampilkan detail validation errors dari endpoint pemeriksaan kandidat dan asosiasikan dengan field. Pertahankan pesan generik untuk kegagalan jaringan/server yang tidak mempunyai validasi aman. |
| Skenario | UX-05, UX-05-R1 |
| Bukti | [patient-phone-validation-feedback.json](evidence/patient-phone-validation-feedback.json), [screenshots/UX-05-patient-phone-generic-error.png](evidence/screenshots/UX-05-patient-phone-generic-error.png) |
| Sumber terkait | [resources/js/patient-duplicates.js:50](../../resources/js/patient-duplicates.js#L50), [app/Http/Controllers/Receptionist/PatientController.php:84](../../app/Http/Controllers/Receptionist/PatientController.php#L84), [resources/views/receptionist/patients/form.blade.php:9](../../resources/views/receptionist/patients/form.blade.php#L9) |

## DFU-06 — Telepon yang sama sekali bukan nomor diterima sebagai data pasien

| Atribut | Detail |
| --- | --- |
| Kategori | Functional Bug |
| Severity | P2 |
| Fitur / role | F09; Receptionist, Clinic Admin |
| Kondisi awal | Pasien baru dengan nama, tanggal lahir dan gender valid. |
| Langkah reproduksi | 1. Isi telepon abc. → 2. Klik Simpan pasien. → 3. Buka ulang detail pasien. |
| Hasil aktual | Pasien QA Deep Telepon Invalid berhasil dibuat dan telepon abc tersimpan. Rule hanya required/string/max:30. |
| Hasil yang diharapkan | Field kontak menolak teks tanpa nomor; batas format harus mendukung nomor lokal/internasional dan telepon bersama keluarga. |
| Dampak pengguna | Data kontak tidak dapat dipakai untuk komunikasi atau pencocokan kandidat yang diharapkan staf. |
| Rekomendasi | Tetapkan format kontak yang diterima dan validasi server-side secara ramah. Jangan menjadikan telepon unik atau melakukan account linking otomatis. |
| Skenario | ADM-11 |
| Bukti | [screenshots/ADM-11-invalid-phone.png](evidence/screenshots/ADM-11-invalid-phone.png) |
| Sumber terkait | [app/Http/Requests/Receptionist/StorePatientRequest.php:35](../../app/Http/Requests/Receptionist/StorePatientRequest.php#L35), [app/Http/Requests/Receptionist/UpdatePatientRequest.php:39](../../app/Http/Requests/Receptionist/UpdatePatientRequest.php#L39) |

## DFU-07 — Filter dashboard dengan rentang tanggal terbalik gagal tanpa pesan

| Atribut | Detail |
| --- | --- |
| Kategori | Functional Bug / UX/Usability Issue |
| Severity | P2 |
| Fitur / role | F22; Clinic Admin |
| Kondisi awal | Dashboard admin dengan periode valid. |
| Langkah reproduksi | 1. Isi Dari 14 November 2026 dan Sampai 13 November 2026. → 2. Klik Terapkan. → 3. Periksa halaman tujuan, nilai filter, dan pesan validasi spesifik tanggal. |
| Hasil aktual | Validasi controller menolak to < from, lalu halaman kembali ke dashboard tanpa pesan error tanggal. Assertion awal yang terlalu umum diperbaiki karena sempat cocok dengan kata diperiksa; UX-04 memakai assertion spesifik. |
| Hasil yang diharapkan | Jelaskan bahwa tanggal Sampai harus sama atau setelah Dari dan pertahankan input yang perlu dikoreksi. |
| Dampak pengguna | Admin dapat membaca ringkasan periode lama sambil mengira filter baru sudah diterapkan. |
| Rekomendasi | Tampilkan summary/field error dan batasi pasangan tanggal pada UI; tetap pertahankan validasi backend. |
| Skenario | UX-04 |
| Bukti | [screenshots/UX-04-invalid-dashboard-no-feedback.png](evidence/screenshots/UX-04-invalid-dashboard-no-feedback.png) |
| Sumber terkait | [app/Http/Controllers/Admin/DashboardController.php:28](../../app/Http/Controllers/Admin/DashboardController.php#L28), [resources/views/admin/dashboard.blade.php:52](../../resources/views/admin/dashboard.blade.php#L52) |

## DFU-08 — Waktu visit tampil UTC sementara konteks klinik memakai WIB

| Atribut | Detail |
| --- | --- |
| Kategori | Cross-Feature Inconsistency / UX/Usability Issue |
| Severity | P2 |
| Fitur / role | F15/F22; Doctor, Clinic Admin |
| Kondisi awal | Klinik timezone Asia/Jakarta. Visit dibuat dari UI pada instant 2026-11-13T07:31:04Z. |
| Langkah reproduksi | 1. Buka pemeriksaan journey yang telah dibuat. → 2. Lihat panel Kunjungan → Mulai. → 3. Bandingkan dengan konversi instant DB ke timezone klinik dan tampilan dashboard admin. |
| Hasil aktual | Panel visit menampilkan 07:31; waktu yang sesuai Asia/Jakarta adalah 14:31. Template memformat started_at/completed_at langsung, sementara dashboard admin melakukan timezone($timezone). |
| Hasil yang diharapkan | Timestamp operasional memakai timezone klinik yang konsisten atau diberi label timezone yang jelas. |
| Dampak pengguna | Dokter/pengelola dapat salah memahami jam layanan dan mencocokkan histori dengan antrean atau laporan. |
| Rekomendasi | Konversi timestamp pada presentasi visit dan audit titik tampilan waktu lain. Jangan mengubah instant UTC yang tersimpan. Queue-card juga memakai format langsung, tetapi cakupan browser utama temuan ini adalah panel visit. |
| Skenario | CROSS-09 |
| Bukti | [visit-timezone.json](evidence/visit-timezone.json), [screenshots/CROSS-09-visit-timezone.png](evidence/screenshots/CROSS-09-visit-timezone.png) |
| Sumber terkait | [resources/views/doctor/visits/show.blade.php:411](../../resources/views/doctor/visits/show.blade.php#L411), [resources/views/admin/dashboard.blade.php:122](../../resources/views/admin/dashboard.blade.php#L122), [resources/views/components/queue-card.blade.php:88](../../resources/views/components/queue-card.blade.php#L88) |

## Authorization/Role Issue

Tidak ada temuan bypass hak medis/invoice pada skenario role negatif yang dijalankan. Ini bukan audit keamanan menyeluruh. Batas UI dan coverage role ada pada ROLE_ACTION_MATRIX.md.
