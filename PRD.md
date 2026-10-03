# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## CLINICOS

### Platform SaaS Manajemen Klinik, Rekam Medis, Booking & Antrean Digital

**STATUS: DRAFT**

|                     |                                                               |
| ------------------- | ------------------------------------------------------------- |
| **Nama Produk**     | ClinicFlow                                                    |
| **Versi Dokumen**   | v0.1                                                          |
| **Disusun oleh**    | Tim Pengembang Sistem                                         |
| **Untuk**           | Klinik dan fasilitas pelayanan kesehatan skala kecil-menengah |
| **Tanggal**         | 3 Oktober 2026                                                |
| **Model Produk**    | Software as a Service (SaaS)                                  |
| **Dokumen Terkait** | Product Development Plan / Technical Architecture             |

---

# 1. Ringkasan Produk (Overview)

Pengelolaan operasional klinik masih dapat melibatkan proses administrasi yang terpisah antara pendaftaran pasien, pengelolaan antrean, pencatatan rekam medis, jadwal dokter, dan pelaporan pelayanan. Kondisi tersebut dapat menyebabkan proses pelayanan menjadi kurang terintegrasi dan menyulitkan klinik dalam memperoleh informasi operasional secara cepat.

**ClinicFlow** hadir sebagai platform digital untuk membantu klinik mengelola proses pelayanan pasien secara terintegrasi. Platform menyediakan sistem manajemen pasien, dokter, jadwal praktik, booking, antrean, rekam medis elektronik, serta dashboard operasional klinik.

Pasien dapat mengakses halaman publik milik klinik melalui browser untuk melihat informasi klinik, layanan, dokter, jadwal praktik, melakukan pendaftaran mandiri, dan melakukan booking antrean. Pasien juga dapat memantau status antreannya dan menerima pemberitahuan ketika antrean sudah mendekati gilirannya.

Dokter menggunakan aplikasi web untuk melihat antrean dan melakukan pencatatan rekam medis pasien. Resepsionis mengelola pendaftaran, booking, serta antrean pasien. Admin klinik mengelola data operasional klinik, dokter, layanan, dan pengguna.

Dalam jangka panjang, ClinicFlow dikembangkan menjadi produk **SaaS multi-tenant**, sehingga satu platform dapat digunakan oleh berbagai klinik dengan data yang terisolasi. Platform juga direncanakan memiliki kemampuan **offline-first dan sinkronisasi cloud** agar operasional klinik tetap dapat berjalan ketika koneksi internet mengalami gangguan.

---

# 2. Tujuan & Sasaran (Goals)

* Memusatkan pengelolaan data pasien, dokter, layanan, jadwal, booking, antrean, dan rekam medis dalam satu platform.
* Mempermudah dokter dalam melakukan pencatatan dan melihat riwayat rekam medis pasien.
* Mempermudah resepsionis dalam mengelola pendaftaran dan antrean pasien.
* Memungkinkan pasien melakukan pendaftaran dan booking antrean secara mandiri melalui website klinik.
* Mengurangi waktu tunggu administratif melalui sistem booking dan antrean digital.
* Memberikan informasi operasional klinik melalui dashboard.
* Menyediakan notifikasi kepada pasien ketika antrean sudah mendekati gilirannya.
* Menyediakan fondasi arsitektur yang dapat dikembangkan menjadi platform SaaS multi-tenant.
* Pada fase lanjutan, memungkinkan operasional klinik tetap berjalan secara offline melalui local database dan mekanisme sinkronisasi dengan cloud.
* Menyediakan sistem yang aman dan memiliki kontrol akses sesuai peran pengguna.

---

# 3. Pengguna & Peran (Users & Roles)

## 3.1 Super Admin

Pengelola utama platform ClinicFlow.

Bertanggung jawab atas:

* Pengelolaan klinik yang menggunakan platform.
* Pengelolaan paket SaaS.
* Pengelolaan subscription.
* Monitoring pengguna dan tenant.
* Konfigurasi platform.
* Monitoring kondisi sistem.

**Catatan:** Super Admin merupakan fitur pasca-MVP ketika ClinicFlow mulai beroperasi sebagai SaaS multi-tenant.

---

## 3.2 Admin Klinik

Pengguna yang mengelola sistem pada suatu klinik.

Memiliki tanggung jawab:

* Mengelola profil klinik.
* Mengelola dokter.
* Mengelola resepsionis.
* Mengelola layanan.
* Mengelola jadwal dokter.
* Mengelola pengguna.
* Melihat laporan operasional.
* Mengatur konfigurasi klinik.

---

## 3.3 Dokter

Dokter menggunakan platform untuk menjalankan proses pelayanan medis.

Memiliki kemampuan:

* Melihat daftar pasien.
* Melihat antrean.
* Memanggil pasien.
* Melihat profil pasien.
* Melihat riwayat kunjungan.
* Membuat rekam medis.
* Mencatat keluhan.
* Mencatat pemeriksaan.
* Mencatat diagnosis.
* Mencatat tindakan.
* Membuat resep.
* Melihat statistik pelayanan.

---

## 3.4 Resepsionis

Resepsionis bertanggung jawab terhadap proses administrasi dan antrean.

Memiliki kemampuan:

* Mendaftarkan pasien.
* Mencari pasien.
* Memverifikasi data pasien.
* Membuat booking.
* Membuat nomor antrean.
* Mengubah status antrean.
* Melihat jadwal dokter.
* Memantau pasien yang sedang menunggu.

---

## 3.5 Pasien

Pasien menggunakan website publik klinik.

Memiliki kemampuan:

* Melihat informasi klinik.
* Melihat layanan.
* Melihat dokter.
* Melihat jadwal dokter.
* Melakukan pendaftaran mandiri.
* Melakukan booking.
* Mendapatkan nomor antrean.
* Melihat status antrean.
* Menerima notifikasi antrean.

---

# 4. Ruang Lingkup (Scope)

## 4.1 Termasuk — MVP

MVP berfokus pada **satu klinik** dan proses pelayanan inti.

Fitur yang termasuk:

* Modul autentikasi dan manajemen pengguna.
* Role Admin Klinik, Dokter, Resepsionis, dan Pasien.
* Manajemen profil klinik.
* Manajemen dokter.
* Manajemen jadwal dokter.
* Manajemen layanan dan tarif.
* Manajemen pasien.
* Nomor rekam medis.
* Rekam medis elektronik dasar.
* Riwayat kunjungan pasien.
* Website publik klinik.
* Pendaftaran pasien.
* Booking dokter/layanan.
* Sistem nomor antrean.
* Dashboard dokter.
* Dashboard klinik.
* Tampilan antrean.
* Pembaruan status antrean secara realtime.
* Notifikasi dasar booking dan antrean.
* Audit log untuk aktivitas penting.
* Basic reporting.
* Kontrol akses berbasis role.

---

## 4.2 Di Luar Lingkup Awal / Fase Lanjutan

Fitur berikut tidak menjadi bagian dari MVP:

* Operasional offline penuh.
* Local server klinik.
* Sinkronisasi database lokal dengan cloud.
* Conflict resolution.
* Multi-tenant SaaS penuh.
* Subscription management.
* Payment gateway untuk subscription.
* Multi-branch management.
* Aplikasi mobile pasien.
* Aplikasi mobile dokter.
* Integrasi WhatsApp.
* Integrasi laboratorium.
* Integrasi apotek.
* Advanced billing pasien.
* Advanced analytics.
* AI medical assistant.
* AI diagnosis.
* Integrasi perangkat medis.

---

# 5. Asumsi & Batasan (Assumptions & Constraints)

* **Asumsi Pengembang:** MVP menggunakan aplikasi web responsif yang dapat digunakan melalui browser modern.
* **Asumsi Pengembang:** Backend dikembangkan menggunakan Laravel dan menyediakan RESTful API.
* **Asumsi Pengembang:** Frontend menggunakan Vue.js.
* **Asumsi Pengembang:** Database utama menggunakan PostgreSQL.
* **Asumsi Pengembang:** MVP beroperasi menggunakan koneksi internet dan belum menjadikan offline mode sebagai requirement utama.
* **Asumsi Pengembang:** Pasien tidak diwajibkan menginstal aplikasi mobile untuk menggunakan layanan booking.
* **Asumsi Pengembang:** Setiap pengguna hanya dapat mengakses data sesuai role dan kliniknya.
* **Batasan MVP:** Sistem hanya difokuskan pada satu tenant/klinik untuk mengurangi kompleksitas pengembangan awal.
* **Batasan Rekam Medis:** Struktur rekam medis awal menggunakan format yang dapat dikonfigurasi dan belum mencakup seluruh kebutuhan rekam medis rumah sakit.
* **Batasan Notifikasi:** Notifikasi awal dapat menggunakan mekanisme web/email; integrasi WhatsApp dan SMS ditunda ke fase lanjutan.
* **Batasan Offline:** Offline-first dan sinkronisasi cloud merupakan fitur fase lanjutan sehingga tidak menjadi requirement wajib MVP.
* **Batasan Pembayaran:** MVP belum mencakup payment gateway untuk pembayaran layanan maupun subscription SaaS.
* **Batasan Integrasi:** MVP belum mengintegrasikan sistem eksternal seperti laboratorium, apotek, asuransi, atau sistem kesehatan pihak ketiga.

---

# 6. Kebutuhan Fungsional (Functional Requirements)

## 6.1 Bersama — Autentikasi & Manajemen Pengguna

| ID     | Kebutuhan Fungsional                                                                | Prioritas   |
| ------ | ----------------------------------------------------------------------------------- | ----------- |
| AUTH-1 | Pengguna dapat melakukan login menggunakan email/username dan kata sandi.           | **Wajib**   |
| AUTH-2 | Sistem membatasi akses berdasarkan role pengguna.                                   | **Wajib**   |
| AUTH-3 | Pengguna dapat logout dari sistem.                                                  | **Wajib**   |
| AUTH-4 | Pengguna dapat memperbarui informasi profil dasar dan kata sandi.                   | **Penting** |
| AUTH-5 | Admin Klinik dapat membuat, mengaktifkan, dan menonaktifkan akun staf.              | **Wajib**   |
| AUTH-6 | Sistem menolak akses pengguna terhadap fitur yang tidak sesuai dengan hak aksesnya. | **Wajib**   |

---

## 6.2 Admin Klinik — Manajemen Klinik

| ID       | Kebutuhan Fungsional                                                        | Prioritas   |
| -------- | --------------------------------------------------------------------------- | ----------- |
| CLINIC-1 | Admin dapat melihat dan mengubah informasi profil klinik.                   | **Wajib**   |
| CLINIC-2 | Admin dapat mengatur alamat dan informasi kontak klinik.                    | **Wajib**   |
| CLINIC-3 | Admin dapat mengatur jam operasional klinik.                                | **Wajib**   |
| CLINIC-4 | Admin dapat mengunggah logo klinik.                                         | **Penting** |
| CLINIC-5 | Admin dapat mengatur informasi yang ditampilkan pada website publik klinik. | **Wajib**   |

---

## 6.3 Admin Klinik — Manajemen Dokter

| ID    | Kebutuhan Fungsional                                         | Prioritas   |
| ----- | ------------------------------------------------------------ | ----------- |
| DOC-1 | Admin dapat menambahkan data dokter.                         | **Wajib**   |
| DOC-2 | Admin dapat mengubah data dokter.                            | **Wajib**   |
| DOC-3 | Admin dapat mengaktifkan atau menonaktifkan dokter.          | **Wajib**   |
| DOC-4 | Admin dapat menentukan spesialisasi dokter.                  | **Wajib**   |
| DOC-5 | Admin dapat menentukan jadwal praktik dokter.                | **Wajib**   |
| DOC-6 | Admin dapat menentukan tarif konsultasi atau layanan dokter. | **Penting** |

---

## 6.4 Admin Klinik — Manajemen Layanan

| ID        | Kebutuhan Fungsional                                    | Prioritas |
| --------- | ------------------------------------------------------- | --------- |
| SERVICE-1 | Admin dapat menambahkan layanan klinik.                 | **Wajib** |
| SERVICE-2 | Admin dapat mengubah informasi layanan.                 | **Wajib** |
| SERVICE-3 | Admin dapat menentukan tarif layanan.                   | **Wajib** |
| SERVICE-4 | Admin dapat mengaktifkan atau menonaktifkan layanan.    | **Wajib** |
| SERVICE-5 | Pasien dapat melihat layanan aktif pada website klinik. | **Wajib** |

---

## 6.5 Resepsionis — Manajemen Pasien

| ID    | Kebutuhan Fungsional                                                                                     | Prioritas |
| ----- | -------------------------------------------------------------------------------------------------------- | --------- |
| PAT-1 | Resepsionis dapat mendaftarkan pasien baru.                                                              | **Wajib** |
| PAT-2 | Sistem menghasilkan nomor rekam medis pasien.                                                            | **Wajib** |
| PAT-3 | Resepsionis dapat mencari pasien berdasarkan nama, nomor rekam medis, atau informasi identitas tertentu. | **Wajib** |
| PAT-4 | Resepsionis dapat memperbarui data administratif pasien sesuai hak akses.                                | **Wajib** |
| PAT-5 | Sistem menyimpan riwayat kunjungan pasien.                                                               | **Wajib** |
| PAT-6 | Pasien dapat melakukan pendaftaran mandiri melalui website.                                              | **Wajib** |

---

## 6.6 Dokter — Rekam Medis Elektronik

| ID     | Kebutuhan Fungsional                                                 | Prioritas   |
| ------ | -------------------------------------------------------------------- | ----------- |
| EMR-1  | Dokter dapat membuka data pasien dari antrean aktif.                 | **Wajib**   |
| EMR-2  | Dokter dapat melihat riwayat kunjungan pasien.                       | **Wajib**   |
| EMR-3  | Dokter dapat mencatat keluhan utama pasien.                          | **Wajib**   |
| EMR-4  | Dokter dapat mencatat tanda vital pasien.                            | **Wajib**   |
| EMR-5  | Dokter dapat mencatat hasil pemeriksaan.                             | **Wajib**   |
| EMR-6  | Dokter dapat mencatat diagnosis.                                     | **Wajib**   |
| EMR-7  | Dokter dapat mencatat tindakan/perawatan.                            | **Wajib**   |
| EMR-8  | Dokter dapat mencatat resep.                                         | **Wajib**   |
| EMR-9  | Dokter dapat memberikan catatan tambahan.                            | **Penting** |
| EMR-10 | Sistem menyimpan waktu dan identitas dokter pada setiap rekam medis. | **Wajib**   |
| EMR-11 | Sistem mencatat aktivitas perubahan rekam medis dalam audit log.     | **Wajib**   |

### Struktur dasar rekam medis

```text
Visit
│
├── Keluhan
├── Tanda Vital
├── Pemeriksaan
├── Diagnosis
├── Tindakan
├── Resep
└── Catatan Dokter
```

---

## 6.7 Pasien — Booking

| ID     | Kebutuhan Fungsional                                   | Prioritas   |
| ------ | ------------------------------------------------------ | ----------- |
| BOOK-1 | Pasien dapat melihat dokter yang tersedia.             | **Wajib**   |
| BOOK-2 | Pasien dapat melihat jadwal praktik dokter.            | **Wajib**   |
| BOOK-3 | Pasien dapat memilih layanan.                          | **Wajib**   |
| BOOK-4 | Pasien dapat memilih tanggal kunjungan.                | **Wajib**   |
| BOOK-5 | Sistem hanya menampilkan slot yang tersedia.           | **Wajib**   |
| BOOK-6 | Pasien dapat melakukan booking.                        | **Wajib**   |
| BOOK-7 | Sistem menghasilkan nomor booking/antrean.             | **Wajib**   |
| BOOK-8 | Pasien dapat membatalkan booking sesuai aturan klinik. | **Penting** |
| BOOK-9 | Sistem mencegah double booking pada slot yang sama.    | **Wajib**   |

---

## 6.8 Resepsionis — Booking & Antrean

| ID      | Kebutuhan Fungsional                                     | Prioritas   |
| ------- | -------------------------------------------------------- | ----------- |
| QUEUE-1 | Resepsionis dapat melihat daftar booking hari ini.       | **Wajib**   |
| QUEUE-2 | Resepsionis dapat mendaftarkan pasien walk-in.           | **Wajib**   |
| QUEUE-3 | Sistem dapat menghasilkan nomor antrean.                 | **Wajib**   |
| QUEUE-4 | Resepsionis dapat mengubah status kedatangan pasien.     | **Wajib**   |
| QUEUE-5 | Resepsionis dapat memanggil pasien berikutnya.           | **Wajib**   |
| QUEUE-6 | Resepsionis dapat melewati pasien yang tidak hadir.      | **Penting** |
| QUEUE-7 | Sistem dapat menampilkan status antrean secara realtime. | **Wajib**   |

---

## 6.9 Dokter — Antrean

| ID          | Kebutuhan Fungsional                                               | Prioritas |
| ----------- | ------------------------------------------------------------------ | --------- |
| QUEUE-DOC-1 | Dokter dapat melihat antrean pasien miliknya.                      | **Wajib** |
| QUEUE-DOC-2 | Dokter dapat memanggil pasien berikutnya.                          | **Wajib** |
| QUEUE-DOC-3 | Dokter dapat mengubah status pasien menjadi sedang diperiksa.      | **Wajib** |
| QUEUE-DOC-4 | Dokter dapat menyelesaikan kunjungan setelah rekam medis disimpan. | **Wajib** |

Status antrean:

```text
BOOKED
WAITING
CALLED
IN_PROGRESS
COMPLETED
SKIPPED
CANCELLED
NO_SHOW
```

---

## 6.10 Pasien — Monitoring Antrean

| ID          | Kebutuhan Fungsional                                                     | Prioritas   |
| ----------- | ------------------------------------------------------------------------ | ----------- |
| QUEUE-PAT-1 | Pasien dapat melihat nomor antreannya.                                   | **Wajib**   |
| QUEUE-PAT-2 | Pasien dapat melihat nomor yang sedang dilayani.                         | **Wajib**   |
| QUEUE-PAT-3 | Pasien dapat melihat jumlah antrean sebelum gilirannya.                  | **Wajib**   |
| QUEUE-PAT-4 | Sistem memberikan pemberitahuan ketika antrean mendekati giliran pasien. | **Penting** |

---

## 6.11 Dashboard Klinik

| ID     | Kebutuhan Fungsional                                                        | Prioritas   |
| ------ | --------------------------------------------------------------------------- | ----------- |
| DASH-1 | Sistem menampilkan jumlah pasien hari ini.                                  | **Wajib**   |
| DASH-2 | Sistem menampilkan jumlah booking hari ini.                                 | **Wajib**   |
| DASH-3 | Sistem menampilkan jumlah kunjungan selesai.                                | **Wajib**   |
| DASH-4 | Sistem menampilkan antrean aktif.                                           | **Wajib**   |
| DASH-5 | Sistem menampilkan pendapatan hari ini berdasarkan transaksi yang tercatat. | **Penting** |
| DASH-6 | Admin dapat melihat statistik berdasarkan rentang tanggal.                  | **Penting** |

---

## 6.12 Audit Log

| ID      | Kebutuhan Fungsional                                 | Prioritas   |
| ------- | ---------------------------------------------------- | ----------- |
| AUDIT-1 | Sistem mencatat aktivitas login dan logout.          | **Wajib**   |
| AUDIT-2 | Sistem mencatat pembuatan rekam medis.               | **Wajib**   |
| AUDIT-3 | Sistem mencatat perubahan rekam medis.               | **Wajib**   |
| AUDIT-4 | Sistem mencatat pembuatan dan perubahan data pasien. | **Wajib**   |
| AUDIT-5 | Admin dapat melihat audit log sesuai hak akses.      | **Penting** |

---

# 7. Alur Pengguna Utama (Key User Flows)

## 7.1 Alur Pendaftaran dan Booking Pasien

1. Pasien membuka website klinik.
2. Pasien melihat informasi layanan dan dokter.
3. Pasien memilih layanan.
4. Pasien memilih dokter.
5. Sistem menampilkan jadwal yang tersedia.
6. Pasien memilih tanggal dan waktu.
7. Pasien memasukkan data diri.
8. Sistem memvalidasi data.
9. Pasien mengonfirmasi booking.
10. Sistem membuat booking.
11. Sistem menghasilkan nomor antrean.
12. Pasien mendapatkan informasi booking dan nomor antrean.

---

## 7.2 Alur Pasien Walk-In

1. Pasien datang ke klinik.
2. Resepsionis mencari data pasien.
3. Jika pasien belum terdaftar, resepsionis membuat data pasien.
4. Resepsionis memilih layanan dan dokter.
5. Sistem memeriksa ketersediaan antrean.
6. Sistem membuat nomor antrean.
7. Pasien masuk ke antrean.
8. Pasien menunggu hingga dipanggil.

---

## 7.3 Alur Pemeriksaan Dokter

1. Dokter login.
2. Dokter membuka dashboard.
3. Sistem menampilkan antrean pasien.
4. Dokter memilih pasien berikutnya.
5. Status pasien berubah menjadi `IN_PROGRESS`.
6. Dokter membuka profil pasien.
7. Dokter melihat riwayat kunjungan.
8. Dokter melakukan pemeriksaan.
9. Dokter mengisi rekam medis.
10. Dokter mencatat diagnosis.
11. Dokter mencatat tindakan.
12. Dokter membuat resep jika diperlukan.
13. Dokter menyimpan rekam medis.
14. Sistem mencatat aktivitas ke audit log.
15. Kunjungan ditandai `COMPLETED`.

---

## 7.4 Alur Monitoring Antrean Pasien

1. Pasien membuka halaman antrean.
2. Sistem menampilkan nomor antrean pasien.
3. Sistem menampilkan nomor yang sedang dilayani.
4. Sistem menghitung jumlah antrean di depan pasien.
5. Ketika antrean mendekati nomor pasien, sistem mengirimkan notifikasi.
6. Ketika pasien dipanggil, sistem mengubah status menjadi `CALLED`.
7. Pasien datang ke ruang pemeriksaan.

---

## 7.5 Alur Pengelolaan Klinik

1. Admin login.
2. Admin membuka dashboard.
3. Admin mengelola data dokter.
4. Admin mengatur jadwal dokter.
5. Admin mengelola layanan.
6. Admin mengelola pengguna.
7. Admin memantau jumlah pasien dan kunjungan.
8. Admin melihat laporan operasional.

---

# 8. Model Data (High-Level)

| Entitas                | Field Utama                                                                                                       | Keterangan                  |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------- | --------------------------- |
| **users**              | `id`, `clinic_id`, `name`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`              | Data akun pengguna sistem.  |
| **clinics**            | `id`, `name`, `slug`, `logo`, `address`, `phone`, `email`, `operating_hours`, `status`                            | Data klinik.                |
| **doctors**            | `id`, `clinic_id`, `user_id`, `name`, `specialty`, `license_number`, `consultation_fee`, `status`                 | Data dokter.                |
| **doctor_schedules**   | `id`, `doctor_id`, `day`, `start_time`, `end_time`, `quota`                                                       | Jadwal praktik dokter.      |
| **patients**           | `id`, `clinic_id`, `medical_record_number`, `name`, `date_of_birth`, `gender`, `phone`, `address`, `allergies`    | Data pasien.                |
| **services**           | `id`, `clinic_id`, `name`, `description`, `price`, `status`                                                       | Daftar layanan klinik.      |
| **appointments**       | `id`, `clinic_id`, `patient_id`, `doctor_id`, `service_id`, `appointment_date`, `appointment_time`, `status`      | Data booking pasien.        |
| **queues**             | `id`, `clinic_id`, `appointment_id`, `queue_number`, `status`, `called_at`, `completed_at`                        | Data antrean pasien.        |
| **visits**             | `id`, `clinic_id`, `patient_id`, `doctor_id`, `appointment_id`, `started_at`, `completed_at`, `status`            | Data kunjungan pasien.      |
| **medical_records**    | `id`, `visit_id`, `doctor_id`, `chief_complaint`, `vital_signs`, `examination`, `diagnosis`, `treatment`, `notes` | Rekam medis pasien.         |
| **prescriptions**      | `id`, `medical_record_id`, `doctor_id`, `notes`                                                                   | Data resep.                 |
| **prescription_items** | `id`, `prescription_id`, `medicine_name`, `dosage`, `frequency`, `quantity`, `instructions`                       | Detail obat/resep.          |
| **notifications**      | `id`, `user_id`, `type`, `title`, `message`, `read_at`                                                            | Notifikasi sistem.          |
| **audit_logs**         | `id`, `clinic_id`, `user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `created_at`                      | Riwayat aktivitas pengguna. |

### Entitas Pasca-MVP

| Entitas                | Fungsi                           |
| ---------------------- | -------------------------------- |
| **subscriptions**      | Status subscription klinik.      |
| **subscription_plans** | Paket SaaS.                      |
| **invoices**           | Tagihan subscription.            |
| **payments**           | Pembayaran.                      |
| **branches**           | Cabang klinik.                   |
| **sync_records**       | Data sinkronisasi offline/cloud. |
| **sync_conflicts**     | Konflik sinkronisasi.            |
| **devices**            | Perangkat lokal klinik.          |

---

# 9. Kebutuhan Non-Fungsional (Non-Functional Requirements)

## 9.1 Responsivitas & Kompatibilitas

* Website pasien harus responsif pada perangkat desktop, tablet, dan smartphone.
* Dashboard klinik harus dapat digunakan pada desktop/laptop.
* Sistem harus kompatibel dengan browser modern seperti Google Chrome, Mozilla Firefox, Safari, dan Microsoft Edge.
* Antarmuka harus tetap usable pada resolusi layar umum perangkat klinik.

---

## 9.2 Performa

* Halaman dashboard utama harus dapat dimuat dengan cepat pada kondisi jaringan normal.
* API harus memberikan response yang konsisten untuk operasi umum.
* Sistem antrean realtime harus memperbarui status tanpa pengguna harus melakukan refresh halaman secara manual.
* Query database harus menggunakan indexing pada kolom yang sering digunakan untuk pencarian dan filtering.

---

## 9.3 Keamanan

* Seluruh komunikasi antara client dan server harus menggunakan HTTPS/TLS.
* Password harus disimpan menggunakan hashing yang aman.
* Endpoint API harus dilindungi autentikasi.
* Sistem harus menggunakan Role-Based Access Control (RBAC).
* Pengguna hanya dapat mengakses data sesuai hak aksesnya.
* Rekam medis tidak boleh dapat diakses oleh pasien lain atau klinik lain.
* Aktivitas penting terhadap rekam medis harus dicatat dalam audit log.

---

## 9.4 Privasi & Integritas Data

* Sistem harus melakukan validasi input.
* Data rekam medis tidak boleh dihapus secara permanen melalui operasi biasa.
* Perubahan data penting harus dapat ditelusuri melalui audit log.
* Database harus memiliki mekanisme backup.
* Data sensitif harus memiliki kontrol akses yang ketat.

---

## 9.5 Skalabilitas

Arsitektur aplikasi harus memungkinkan pengembangan dari:

```text
1 Klinik
    ↓
10 Klinik
    ↓
100 Klinik
    ↓
1000+ Klinik
```

tanpa perlu membangun aplikasi yang berbeda untuk setiap klinik.

---

## 9.6 Offline — Pasca-MVP

Pada fase offline-first:

* Sistem harus tetap dapat digunakan ketika internet terputus.
* Data lokal harus tersimpan secara aman.
* Data yang belum tersinkronisasi harus memiliki status yang jelas.
* Sistem harus melakukan sinkronisasi ketika koneksi kembali tersedia.
* Konflik data harus dapat dideteksi dan diselesaikan dengan mekanisme yang telah ditentukan.

---

# 10. Integrasi Pihak Ketiga

| Layanan                      | Fungsi                     | Status        |
| ---------------------------- | -------------------------- | ------------- |
| **PostgreSQL**               | Database utama             | MVP           |
| **Laravel Reverb**           | Realtime queue update      | MVP           |
| **Email Provider**           | Booking dan notification   | MVP           |
| **Firebase Cloud Messaging** | Push notification          | Pasca-MVP     |
| **WhatsApp API**             | Notifikasi WhatsApp        | Pasca-MVP     |
| **Midtrans/Xendit**          | Pembayaran subscription    | Pasca-MVP     |
| **S3-compatible Storage**    | Penyimpanan file/dokumen   | MVP/Pasca-MVP |
| **Cloud VPS**                | Hosting platform           | MVP           |
| **Local Server**             | Operasional offline klinik | Pasca-MVP     |

---

# 11. Fitur Usulan / Fase Lanjutan

## 11.1 Offline-First Clinic

ClinicFlow dikembangkan agar klinik tetap dapat beroperasi ketika internet tidak tersedia.

Arsitektur:

```text
                 CLOUD
                   ▲
                   │
             Sync Engine
                   │
                   ▼
             LOCAL SERVER
              /         \
             /           \
         Doctor       Receptionist
```

Fitur:

* Local database.
* Local authentication.
* Offline patient access.
* Offline queue.
* Offline medical record.
* Sync queue.
* Automatic synchronization.
* Conflict detection.
* Conflict resolution.

---

## 11.2 Multi-Tenant SaaS

Platform dikembangkan agar satu sistem dapat digunakan banyak klinik.

```text
ClinicFlow
│
├── Klinik A
├── Klinik B
├── Klinik C
└── Klinik D
```

Setiap klinik memiliki data yang terisolasi.

---

## 11.3 Subscription Management

Fitur:

* Paket Basic.
* Paket Professional.
* Paket Enterprise.
* Free trial.
* Monthly subscription.
* Annual subscription.
* Upgrade.
* Downgrade.
* Subscription expiration.
* Invoice.
* Payment history.

---

## 11.4 Multi-Branch

Untuk klinik yang memiliki beberapa cabang:

```text
Clinic Group
│
├── Branch A
├── Branch B
└── Branch C
```

Fitur:

* Branch management.
* Dokter per cabang.
* Jadwal per cabang.
* Antrean per cabang.
* Laporan per cabang.
* Cross-branch analytics.

---

## 11.5 Advanced Notification

* Push notification.
* WhatsApp notification.
* SMS.
* Email.
* Queue approaching notification.
* Appointment reminder.
* Custom notification template.

---

## 11.6 Advanced Analytics

* Revenue analytics.
* Patient growth.
* New vs returning patients.
* Doctor workload.
* Service performance.
* Cancellation rate.
* No-show rate.
* Peak hours.
* Monthly reports.
* Export PDF.
* Export Excel.

---

## 11.7 Mobile Application

Aplikasi mobile dapat dikembangkan menggunakan Flutter setelah penggunaan platform web tervalidasi.

### Patient App

* Booking.
* Queue tracking.
* Notifications.
* Appointment history.
* Profile.

### Doctor App

* Dashboard.
* Queue.
* Patient.
* Medical record.
* Prescription.
* Notifications.

---

## 11.8 Integrasi Eksternal

Potensi integrasi:

* WhatsApp.
* Payment gateway.
* Laboratory.
* Pharmacy.
* Accounting.
* Insurance.
* External health systems.

---

## 11.9 AI Features

Fitur AI merupakan fitur jangka panjang dan bukan bagian dari MVP.

Kemungkinan:

* AI-assisted medical documentation.
* Automatic summarization of medical history.
* Medical note assistance.
* Administrative chatbot.
* Patient FAQ assistant.

AI tidak digunakan sebagai pengganti keputusan medis dokter.

---

# 12. Pertanyaan Terbuka / TBD

* Apakah rekam medis akan menggunakan format SOAP sebagai format standar utama?
* Apakah nomor rekam medis bersifat unik hanya dalam satu klinik atau harus unik secara global?
* Apakah pasien harus membuat akun untuk melakukan booking?
* Apakah pasien dapat melakukan booking tanpa login menggunakan nomor telepon/email?
* Apakah satu pasien dapat terdaftar di beberapa klinik?
* Bagaimana kebijakan pembatalan booking?
* Berapa lama sebelum jadwal pasien dapat melakukan booking?
* Apakah klinik membutuhkan sistem walk-in dan online queue secara bersamaan?
* Bagaimana aturan pasien yang tidak hadir (*no-show*)?
* Apakah dokter dapat mengubah rekam medis yang sudah diselesaikan?
* Jika dokter dapat mengubahnya, apakah perubahan harus menghasilkan audit trail baru?
* Apakah resepsionis diperbolehkan melihat rekam medis atau hanya data administratif pasien?
* Apakah admin klinik dapat melihat seluruh isi rekam medis?
* Bagaimana kebijakan backup dan retensi data rekam medis?
* Berapa lama data rekam medis harus disimpan?
* Apakah setiap klinik membutuhkan custom domain?
* Apakah sistem subscription akan dihitung berdasarkan jumlah dokter, pengguna, pasien, atau fitur?
* Apakah klinik dapat memiliki beberapa cabang dalam satu akun?
* Bagaimana mekanisme sinkronisasi offline pada fase lanjutan?
* Bagaimana conflict resolution ketika data rekam medis berubah pada dua perangkat?
* Apakah local server menggunakan PostgreSQL atau database embedded seperti SQLite?
* Apakah pasien membutuhkan notifikasi WhatsApp atau cukup push notification?
* Apakah pembayaran pasien termasuk dalam scope produk?
* Apakah sistem akan diintegrasikan dengan layanan kesehatan atau sistem eksternal tertentu?

---

# 13. Glosarium

* **ClinicFlow:** Nama platform SaaS untuk manajemen klinik.
* **SaaS (Software as a Service):** Model distribusi perangkat lunak di mana pengguna menggunakan aplikasi melalui layanan berlangganan tanpa harus mengelola aplikasi secara mandiri.
* **Clinic Management System:** Sistem yang digunakan untuk mengelola operasional klinik.
* **EMR (Electronic Medical Record):** Rekam medis pasien yang disimpan dalam bentuk digital.
* **Booking:** Proses pemesanan jadwal atau antrean pelayanan oleh pasien.
* **Queue:** Sistem pengelolaan antrean pasien.
* **RBAC (Role-Based Access Control):** Mekanisme pembatasan akses berdasarkan role pengguna.
* **Audit Log:** Catatan aktivitas pengguna dalam sistem.
* **Multi-Tenant:** Arsitektur yang memungkinkan satu aplikasi digunakan oleh banyak organisasi dengan data yang terisolasi.
* **Offline-First:** Pendekatan pengembangan aplikasi yang memungkinkan aplikasi tetap berfungsi ketika koneksi internet tidak tersedia dan melakukan sinkronisasi ketika koneksi kembali.
* **Local Server:** Server yang berada di lingkungan klinik dan digunakan untuk menyimpan serta menyediakan data ketika koneksi internet tidak tersedia.
* **Synchronization:** Proses menyamakan data antara database lokal dan database cloud.
* **Conflict Resolution:** Mekanisme penyelesaian ketika terdapat perubahan data yang berbeda pada dua sumber data.
* **Subscription:** Layanan berlangganan yang memberikan akses terhadap platform berdasarkan paket tertentu.
* **Tenant:** Satu organisasi/klinik yang menggunakan platform ClinicFlow dalam arsitektur multi-tenant.
* **PWA (Progressive Web App):** Aplikasi web yang dapat memberikan pengalaman menyerupai aplikasi native dan mendukung kemampuan tertentu seperti instalasi serta notifikasi.
* **SOAP:** Format pencatatan medis yang terdiri dari Subjective, Objective, Assessment, dan Plan.
* **No-Show:** Kondisi ketika pasien telah melakukan booking tetapi tidak datang sesuai jadwal.
* **Realtime:** Kemampuan sistem memperbarui informasi secara langsung tanpa pengguna melakukan refresh secara manual.

---

# 14. Product Roadmap

```text
                    CLINICFLOW
                        │
                        ▼
                     MVP v1
                        │
        ┌───────────────┼────────────────┐
        │               │                │
     Patient          Doctor         Reception
        │               │                │
     Booking        Medical Record     Queue
        │               │                │
        └───────────────┼────────────────┘
                        │
                        ▼
                Core Clinic System
                        │
                        ▼
                 POST-MVP PHASE 1
                        │
                 Offline-First
                        │
                        ▼
                 POST-MVP PHASE 2
                        │
                  Multi-Tenant
                        │
                        ▼
                 POST-MVP PHASE 3
                        │
                   Subscription
                        │
                        ▼
                 POST-MVP PHASE 4
                        │
              Payment & Notification
                        │
                        ▼
                 POST-MVP PHASE 5
                        │
              Advanced Analytics
                        │
                        ▼
                 POST-MVP PHASE 6
                        │
              Multi-Branch & Mobile
                        │
                        ▼
                  FULL SaaS
```

---

# 15. MVP Success Criteria

MVP dinyatakan berhasil apabila satu klinik dapat menjalankan proses berikut secara end-to-end:

```text
Admin membuat dokter
        ↓
Admin membuat jadwal
        ↓
Pasien membuka website
        ↓
Pasien melakukan pendaftaran
        ↓
Pasien melakukan booking
        ↓
Sistem membuat nomor antrean
        ↓
Resepsionis melihat antrean
        ↓
Dokter memanggil pasien
        ↓
Dokter membuka data pasien
        ↓
Dokter melakukan pemeriksaan
        ↓
Dokter membuat rekam medis
        ↓
Dokter menyelesaikan kunjungan
        ↓
Data masuk ke riwayat pasien
        ↓
Dashboard diperbarui
```

Jika seluruh alur tersebut dapat berjalan dengan baik, maka **MVP ClinicFlow telah memenuhi fungsi utama produk**.

---

# 16. Batasan Produk MVP

MVP ClinicFlow **tidak bertujuan menjadi sistem rumah sakit atau platform kesehatan yang lengkap**.

Fokus MVP adalah:

> **Menyediakan sistem digital yang membantu satu klinik mengelola pasien, dokter, rekam medis, booking, dan antrean dalam satu platform.**

Fitur seperti:

* Offline-first,
* Cloud synchronization,
* Multi-tenant,
* Subscription,
* Payment gateway,
* Mobile application,
* Multi-branch,
* Advanced analytics,
* AI,

merupakan pengembangan **pasca-MVP**.

Dengan pembatasan tersebut, tim dapat memvalidasi kebutuhan produk dan alur operasional klinik terlebih dahulu sebelum membangun kompleksitas SaaS secara penuh.

---

*Dokumen ini merupakan draft PRD dan dapat berubah berdasarkan hasil validasi kebutuhan dengan calon pengguna klinik, dokter, resepsionis, dan pasien.*
