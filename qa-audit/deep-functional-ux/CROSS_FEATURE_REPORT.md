> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.

# Cross-Feature Functional Report

Run: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome 158.0.8127.36, headless nyata**; database/storage/sesi/build disposable.

PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.

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

## Integrasi yang terbukti pada skenario terpilih

| Alur | Bukti | Hasil dan batas |
| --- | --- | --- |
| Patient → appointment → queue | BOOK-02/03-R1, CROSS-01-R1, BOOK-QUOTA | Transaksi terlihat dari DB: satu appointment/queue; double-click booking satu row; kuota penuh tidak menambah row. |
| Check-in → dokter start → final | QUEUE-01/05-R1, RME-08-R2 | Check-in tidak membuat visit; start membuat visit; final menyamakan RME/visit/appointment/queue. |
| Final → koreksi → foto historis | RME-09-R3, PHOTO-HISTORY | Status tetap FINAL, revision bertambah, foto sebelumnya tetap tersedia lewat link UI ber-policy. |
| Pasien → dokter → resep pasien | JOURNEY-02-R1 | Resep final baru muncul di dashboard pasien sendiri; dokter dapat menyimpan/finalisasi visit aktif saat subscription suspended fixture. |
| Katalog harga → landing → invoice lama | PLAT-02/03/04 | Harga/aktif baru memengaruhi katalog; nominal invoice lama tetap snapshot. |
| Seat deactivate → kapasitas baru | SUB-SEATS | Create seat tambahan ditolak, deactivate staf pertama membuka kapasitas untuk staf kedua. |
| Layanan/pasien inactive → pilihan booking | SERVICE-05, PAT-05 | Layanan/pasien inactive dikecualikan dari pilihan baru; snapshot appointment lama dipertahankan. |
| Role dicabut → sesi/menu | ROLE-CHANGE | Request operasional berikut403, menu berubah; tidak menguji socket yang sudah aktif. |
| Antrean → notification/read badge | CROSS-10 | Notifikasi tersimpan dan read-all memperbarui DB. Broadcast notification aktual belum dijalankan. |
| Add-on dicabut → dental/general | DENT-01/02/03 | Dental persistence dan read-only tetap menyimpan histori; RME General tetap bisa disimpan. Entitlement berasal dari fixture, bukan pembayaran. |
| Check-in → polling tracker → ETA | ETA-01-R2 | Menunggu dan ETA/confidence muncul tanpa reload manual; socket nyata belum diuji. |

Regresi DB/HTTP tambahan:363 test,2438 assertion PASS pada SQLite memory. Hasil itu tidak membuktikan setiap kombinasi UI, lock MySQL, atau layanan eksternal.
