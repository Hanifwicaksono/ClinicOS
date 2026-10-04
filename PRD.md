# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## ClinicOS — Platform Manajemen Klinik Berbasis SaaS

**STATUS: DRAFT SEMENTARA**

|                         |                                                                              |
| ----------------------- | ---------------------------------------------------------------------------- |
| **Nama Produk**         | ClinicOS                                                                     |
| **Deskripsi**           | Platform manajemen klinik, rekam medis, reservasi, dan antrean berbasis SaaS |
| **Versi Dokumen**       | v0.1                                                                         |
| **Disusun oleh**        | Tim Pengembang Sistem                                                        |
| **Target Pengguna**     | Klinik umum dan klinik skala kecil-menengah                                  |
| **Tanggal**             | 4 Oktober 2026                                                               |
| **Status Pengembangan** | Perencanaan / MVP                                                            |

---

# 1. Ringkasan Produk (Overview)

Operasional klinik skala kecil hingga menengah masih sering menggunakan kombinasi pencatatan manual, spreadsheet, aplikasi pesan instan, dan sistem yang terpisah-pisah untuk mengelola pasien, jadwal dokter, antrean, serta rekam medis. Kondisi tersebut dapat menyebabkan data pasien sulit dikelola secara terpusat, proses pendaftaran membutuhkan waktu, dan tenaga administrasi maupun dokter harus melakukan pencatatan berulang.

**ClinicOS** hadir sebagai platform manajemen klinik berbasis SaaS yang mengintegrasikan proses administrasi dan pelayanan pasien dalam satu sistem. Platform menyediakan manajemen pasien, dokter, jadwal, layanan klinik, booking, nomor antrean, rekam medis, resep, serta dashboard operasional dan keuangan dasar.

Pada MVP, ClinicOS dikembangkan sebagai aplikasi web menggunakan arsitektur **full Laravel**, dengan Laravel sebagai framework utama, **Blade + Livewire** sebagai antarmuka, **Tailwind CSS** untuk styling, dan **MySQL** sebagai basis data.

ClinicOS dirancang agar dapat digunakan oleh beberapa jenis pengguna, yaitu **Clinic Admin, Dokter, Resepsionis, dan Pasien**. Pasien dapat mengakses halaman publik klinik untuk melihat layanan, jadwal dokter, melakukan pendaftaran, serta memperoleh nomor antrean tanpa harus menggunakan aplikasi mobile khusus.

Dalam pengembangan jangka panjang, ClinicOS akan dikembangkan menjadi platform SaaS multi-klinik yang memungkinkan banyak klinik memiliki akun, website, data, pengguna, serta konfigurasi masing-masing secara terisolasi.

---

# 2. Tujuan & Sasaran (Goals)

* Memusatkan pengelolaan data pasien, dokter, jadwal, layanan, antrean, dan rekam medis ke dalam satu platform.
* Mengurangi proses administrasi manual pada proses pendaftaran dan pelayanan pasien.
* Mempermudah pasien melakukan pendaftaran dan mengambil nomor antrean secara online.
* Membantu resepsionis mengelola pasien dan antrean secara lebih terstruktur.
* Membantu dokter mengakses data pasien dan membuat rekam medis secara digital.
* Menyediakan dashboard operasional untuk membantu pengelola klinik memahami aktivitas klinik.
* Menyediakan pencatatan pendapatan dasar berdasarkan layanan dan kunjungan pasien.
* Menyediakan sistem autentikasi dan hak akses berdasarkan peran pengguna.
* Membangun fondasi teknis yang dapat dikembangkan menjadi platform SaaS multi-klinik.
* Menyediakan arsitektur yang memungkinkan pengembangan fitur realtime, mobile application, offline-first, subscription, dan integrasi pihak ketiga pada fase berikutnya.

---

# 3. Pengguna & Peran (Users & Roles)

## 3.1 Clinic Admin

**Clinic Admin** merupakan pengelola operasional suatu klinik.

Memiliki hak akses untuk:

* Mengelola profil klinik.
* Mengelola akun dokter.
* Mengelola akun resepsionis.
* Mengelola jadwal dokter.
* Mengelola layanan dan harga.
* Melihat dan mengelola data pasien.
* Melihat data kunjungan.
* Melihat dashboard klinik.
* Melihat ringkasan pendapatan.
* Melihat laporan.
* Melihat audit log.

---

## 3.2 Dokter

Dokter bertanggung jawab terhadap proses pelayanan medis.

Memiliki hak akses untuk:

* Melihat jadwal praktik sendiri.
* Melihat daftar pasien yang harus dilayani.
* Melihat antrean pasien.
* Memanggil pasien.
* Melihat riwayat pasien sesuai hak akses.
* Membuat rekam medis.
* Mengisi hasil pemeriksaan.
* Mengisi diagnosis.
* Mengisi tindakan.
* Membuat resep.
* Menyelesaikan kunjungan pasien.

Dokter tidak memiliki akses untuk mengubah konfigurasi utama klinik.

---

## 3.3 Resepsionis

Resepsionis bertanggung jawab terhadap proses administrasi pasien.

Memiliki hak akses untuk:

* Mendaftarkan pasien.
* Mencari pasien.
* Memperbarui data administratif pasien.
* Membuat booking.
* Mengelola antrean.
* Memanggil pasien.
* Mengubah status antrean.
* Melihat jadwal dokter.
* Melihat daftar kunjungan.
* Membantu pasien melakukan pendaftaran.

Resepsionis tidak dapat mengubah atau menghapus isi rekam medis dokter.

---

## 3.4 Pasien

Pasien merupakan pengguna publik yang menggunakan layanan klinik.

Pasien dapat:

* Membuka website klinik.
* Melihat informasi klinik.
* Melihat layanan klinik.
* Melihat jadwal dokter.
* Melakukan pendaftaran.
* Mengambil nomor antrean.
* Melihat status antrean.
* Melihat informasi booking.
* Menerima notifikasi terkait antrean dan kunjungan.

---

## 3.5 Super Admin SaaS — Fase Lanjutan

**Super Admin** merupakan administrator platform ClinicOS secara keseluruhan, bukan administrator dari satu klinik.

Hak akses meliputi:

* Mengelola klinik yang terdaftar.
* Mengelola paket subscription.
* Melihat status subscription.
* Mengelola pembayaran.
* Mengelola konfigurasi platform.
* Monitoring penggunaan sistem.
* Mengelola tenant.
* Menangani suspend/aktivasi klinik.

Fitur ini **tidak termasuk MVP awal**.

---

# 4. Ruang Lingkup (Scope)

## 4.1 Termasuk — MVP

MVP ClinicOS mencakup:

* Autentikasi pengguna.
* Role-Based Access Control.
* Manajemen profil klinik.
* Manajemen dokter.
* Manajemen resepsionis.
* Manajemen jadwal dokter.
* Manajemen layanan dan harga.
* Manajemen pasien.
* Website publik klinik.
* Pendaftaran pasien.
* Booking kunjungan.
* Sistem nomor antrean.
* Dashboard resepsionis.
* Dashboard dokter.
* Dashboard klinik.
* Rekam medis dasar.
* Diagnosis.
* Tindakan.
* Resep.
* Riwayat kunjungan pasien.
* Status antrean.
* Notifikasi dasar.
* Ringkasan pendapatan.
* Audit log.
* Validasi dan kontrol akses data.

### Teknologi MVP

* Laravel
* PHP
* Blade
* Livewire
* Tailwind CSS
* MySQL
* Laravel Breeze/Fortify
* Spatie Laravel Permission
* Laravel Reverb
* Laravel Queue
* Laravel Scheduler
* Laravel Filesystem

---

## 4.2 Di Luar Lingkup Awal / Post-MVP

Fitur berikut ditunda ke fase lanjutan:

* SaaS multi-tenant penuh.
* Super Admin platform.
* Subscription management.
* Free trial.
* Payment gateway subscription.
* Multi-cabang.
* Offline-first medical record.
* Local clinic server.
* Sinkronisasi data lokal ke cloud.
* Mobile App pasien.
* Mobile App dokter.
* Push notification.
* WhatsApp notification.
* SMS notification.
* Integrasi BPJS atau sistem eksternal lainnya.
* Advanced analytics.
* Export laporan lanjutan.
* Backup dan disaster recovery tingkat lanjut.
* Two-Factor Authentication.
* Advanced session/device management.
* AI-assisted medical documentation.
* AI chatbot pasien.
* Integrasi laboratorium.
* Integrasi apotek.

---

# 5. Asumsi & Batasan (Assumptions & Constraints)

* **Asumsi Pengembang:** MVP dikembangkan sebagai aplikasi web menggunakan Laravel sebagai framework utama.
* **Asumsi Pengembang:** Antarmuka web menggunakan Blade dan Livewire sehingga tidak membutuhkan frontend SPA terpisah seperti Vue atau React.
* **Asumsi Pengembang:** Database utama menggunakan MySQL.
* **Asumsi Pengembang:** Sistem MVP membutuhkan koneksi internet aktif.
* **Asumsi Pengembang:** Offline-first bukan bagian dari MVP dan akan dikembangkan pada fase lanjutan.
* **Asumsi Pengembang:** Satu instalasi MVP digunakan untuk satu klinik.
* **Asumsi Pengembang:** Pasien dapat menggunakan halaman publik klinik tanpa harus membuat akun pada tahap awal.
* **Batasan Data Medis:** Data rekam medis hanya dapat diakses oleh pengguna yang memiliki hak akses sesuai perannya.
* **Batasan Infrastruktur:** Performa sistem bergantung pada spesifikasi server, database, koneksi internet, dan jumlah pengguna aktif.
* **Batasan MVP:** Sistem belum ditujukan sebagai pengganti seluruh sistem informasi rumah sakit atau sistem klinis berskala enterprise.
* **Batasan Offline:** Data rekam medis tidak dirancang untuk disimpan pada komputer pribadi dokter sebagai database utama.
* **Asumsi Pengembangan Lanjutan:** Apabila fitur offline diperlukan, penyimpanan lokal akan menggunakan perangkat atau server lokal yang dikontrol oleh klinik, bukan laptop pribadi dokter.
* **Asumsi Pengembang:** Backup database dilakukan secara berkala pada lingkungan server.
* **Asumsi Pengembang:** Kebijakan retensi, privasi, dan keamanan data medis perlu ditentukan lebih lanjut berdasarkan kebutuhan operasional dan ketentuan yang berlaku.

---

# 6. Kebutuhan Fungsional (Functional Requirements)

## 6.1 Bersama — Autentikasi dan Manajemen Profil

| ID         | Kebutuhan Fungsional                                                    | Prioritas   |
| ---------- | ----------------------------------------------------------------------- | ----------- |
| **AUTH-1** | Pengguna dapat melakukan login menggunakan email/username dan password. | **Wajib**   |
| **AUTH-2** | Sistem membatasi akses berdasarkan role pengguna.                       | **Wajib**   |
| **AUTH-3** | Pengguna dapat melakukan logout.                                        | **Wajib**   |
| **AUTH-4** | Pengguna dapat memperbarui informasi profil dasar.                      | **Penting** |
| **AUTH-5** | Pengguna dapat mengubah password.                                       | **Penting** |
| **AUTH-6** | Sistem dapat mengaktifkan atau menonaktifkan akun pengguna.             | **Wajib**   |

---

## 6.2 Clinic Admin — Manajemen Klinik

| ID           | Kebutuhan Fungsional                                                                            | Prioritas   |
| ------------ | ----------------------------------------------------------------------------------------------- | ----------- |
| **CLINIC-1** | Admin dapat melihat dan memperbarui profil klinik.                                              | **Wajib**   |
| **CLINIC-2** | Admin dapat mengatur nama, alamat, nomor telepon, deskripsi, logo, dan informasi kontak klinik. | **Wajib**   |
| **CLINIC-3** | Admin dapat mengatur jam operasional klinik.                                                    | **Wajib**   |
| **CLINIC-4** | Admin dapat mengatur informasi yang ditampilkan pada website publik klinik.                     | **Penting** |

---

## 6.3 Clinic Admin — Manajemen Dokter

| ID        | Kebutuhan Fungsional                                            | Prioritas   |
| --------- | --------------------------------------------------------------- | ----------- |
| **DOC-1** | Admin dapat menambahkan akun dokter.                            | **Wajib**   |
| **DOC-2** | Admin dapat mengubah data dokter.                               | **Wajib**   |
| **DOC-3** | Admin dapat mengaktifkan atau menonaktifkan dokter.             | **Wajib**   |
| **DOC-4** | Admin dapat menentukan spesialisasi atau bidang layanan dokter. | **Penting** |
| **DOC-5** | Admin dapat mengatur jadwal praktik dokter.                     | **Wajib**   |

---

## 6.4 Clinic Admin — Manajemen Layanan

| ID            | Kebutuhan Fungsional                                         | Prioritas |
| ------------- | ------------------------------------------------------------ | --------- |
| **SERVICE-1** | Admin dapat menambahkan layanan klinik.                      | **Wajib** |
| **SERVICE-2** | Admin dapat mengubah nama dan deskripsi layanan.             | **Wajib** |
| **SERVICE-3** | Admin dapat menentukan harga layanan.                        | **Wajib** |
| **SERVICE-4** | Admin dapat mengaktifkan atau menonaktifkan layanan.         | **Wajib** |
| **SERVICE-5** | Sistem menampilkan layanan aktif pada website publik klinik. | **Wajib** |

---

## 6.5 Resepsionis — Manajemen Pasien

| ID        | Kebutuhan Fungsional                                                                     | Prioritas |
| --------- | ---------------------------------------------------------------------------------------- | --------- |
| **PAT-1** | Resepsionis dapat mendaftarkan pasien baru.                                              | **Wajib** |
| **PAT-2** | Sistem menyimpan identitas dasar pasien.                                                 | **Wajib** |
| **PAT-3** | Resepsionis dapat mencari pasien berdasarkan nama, nomor telepon, atau identitas pasien. | **Wajib** |
| **PAT-4** | Resepsionis dapat memperbarui data administratif pasien.                                 | **Wajib** |
| **PAT-5** | Sistem menghasilkan identitas unik pasien.                                               | **Wajib** |
| **PAT-6** | Sistem menampilkan riwayat kunjungan pasien sesuai hak akses.                            | **Wajib** |

---

## 6.6 Pasien — Website Publik Klinik

| ID           | Kebutuhan Fungsional                                       | Prioritas |
| ------------ | ---------------------------------------------------------- | --------- |
| **PUBLIC-1** | Pengguna dapat membuka halaman publik suatu klinik.        | **Wajib** |
| **PUBLIC-2** | Sistem menampilkan profil klinik.                          | **Wajib** |
| **PUBLIC-3** | Sistem menampilkan layanan dan harga klinik.               | **Wajib** |
| **PUBLIC-4** | Sistem menampilkan dokter yang aktif.                      | **Wajib** |
| **PUBLIC-5** | Sistem menampilkan jadwal dokter.                          | **Wajib** |
| **PUBLIC-6** | Pengguna dapat melakukan pendaftaran kunjungan.            | **Wajib** |
| **PUBLIC-7** | Sistem menampilkan nomor antrean setelah booking berhasil. | **Wajib** |

---

## 6.7 Booking dan Sistem Antrean

| ID          | Kebutuhan Fungsional                                       | Prioritas   |
| ----------- | ---------------------------------------------------------- | ----------- |
| **QUEUE-1** | Pasien dapat memilih dokter dan layanan.                   | **Wajib**   |
| **QUEUE-2** | Pasien dapat memilih tanggal kunjungan yang tersedia.      | **Wajib**   |
| **QUEUE-3** | Sistem menghasilkan nomor booking/antrean secara otomatis. | **Wajib**   |
| **QUEUE-4** | Resepsionis dapat melihat daftar antrean hari ini.         | **Wajib**   |
| **QUEUE-5** | Resepsionis dapat memanggil pasien berikutnya.             | **Wajib**   |
| **QUEUE-6** | Sistem menyediakan status antrean.                         | **Wajib**   |
| **QUEUE-7** | Dokter dapat melihat antrean pasiennya.                    | **Wajib**   |
| **QUEUE-8** | Sistem dapat memperbarui status antrean secara realtime.   | **Penting** |

### Status antrean

```text
BOOKED
   ↓
WAITING
   ↓
CALLED
   ↓
IN_PROGRESS
   ↓
COMPLETED
```

Status tambahan:

```text
CANCELLED
SKIPPED
NO_SHOW
```

---

## 6.8 Dokter — Rekam Medis

| ID        | Kebutuhan Fungsional                                                  | Prioritas |
| --------- | --------------------------------------------------------------------- | --------- |
| **MR-1**  | Dokter dapat membuka data pasien yang sedang dilayani.                | **Wajib** |
| **MR-2**  | Dokter dapat melihat riwayat kunjungan pasien sesuai hak akses.       | **Wajib** |
| **MR-3**  | Dokter dapat membuat rekam medis baru.                                | **Wajib** |
| **MR-4**  | Dokter dapat mengisi keluhan utama pasien.                            | **Wajib** |
| **MR-5**  | Dokter dapat mencatat tanda vital pasien.                             | **Wajib** |
| **MR-6**  | Dokter dapat mencatat hasil pemeriksaan fisik.                        | **Wajib** |
| **MR-7**  | Dokter dapat mencatat diagnosis.                                      | **Wajib** |
| **MR-8**  | Dokter dapat mencatat tindakan/perawatan.                             | **Wajib** |
| **MR-9**  | Dokter dapat membuat resep.                                           | **Wajib** |
| **MR-10** | Dokter dapat menambahkan catatan medis.                               | **Wajib** |
| **MR-11** | Sistem menyimpan waktu dan identitas dokter yang membuat rekam medis. | **Wajib** |
| **MR-12** | Sistem membatasi perubahan rekam medis berdasarkan hak akses.         | **Wajib** |

### Struktur dasar rekam medis

```text
Subjective
Objective
Assessment
Plan
```

Ditambah:

```text
Keluhan Utama
Tanda Vital
Pemeriksaan Fisik
Diagnosis
Tindakan
Resep
Catatan Dokter
```

---

## 6.9 Dokter — Penyelesaian Kunjungan

| ID          | Kebutuhan Fungsional                                        | Prioritas |
| ----------- | ----------------------------------------------------------- | --------- |
| **VISIT-1** | Dokter dapat memulai sesi pemeriksaan pasien.               | **Wajib** |
| **VISIT-2** | Sistem mengubah status antrean menjadi `IN_PROGRESS`.       | **Wajib** |
| **VISIT-3** | Dokter dapat menyimpan rekam medis.                         | **Wajib** |
| **VISIT-4** | Dokter dapat menyelesaikan kunjungan pasien.                | **Wajib** |
| **VISIT-5** | Sistem mengubah status antrean menjadi `COMPLETED`.         | **Wajib** |
| **VISIT-6** | Sistem mencatat layanan yang diberikan dan nilai transaksi. | **Wajib** |

---

## 6.10 Dashboard Klinik

| ID         | Kebutuhan Fungsional                                        | Prioritas   |
| ---------- | ----------------------------------------------------------- | ----------- |
| **DASH-1** | Sistem menampilkan jumlah pasien hari ini.                  | **Wajib**   |
| **DASH-2** | Sistem menampilkan jumlah booking hari ini.                 | **Wajib**   |
| **DASH-3** | Sistem menampilkan jumlah kunjungan yang selesai.           | **Wajib**   |
| **DASH-4** | Sistem menampilkan antrean aktif.                           | **Wajib**   |
| **DASH-5** | Sistem menampilkan pendapatan hari ini.                     | **Wajib**   |
| **DASH-6** | Sistem menampilkan statistik kunjungan berdasarkan periode. | **Penting** |
| **DASH-7** | Sistem menampilkan layanan yang paling banyak digunakan.    | **Penting** |

---

## 6.11 Notifikasi

| ID          | Kebutuhan Fungsional                                                 | Prioritas   |
| ----------- | -------------------------------------------------------------------- | ----------- |
| **NOTIF-1** | Sistem memberikan notifikasi setelah booking berhasil.               | **Wajib**   |
| **NOTIF-2** | Sistem menampilkan perubahan status antrean kepada pengguna.         | **Penting** |
| **NOTIF-3** | Sistem memberikan informasi ketika antrean pasien mendekati giliran. | **Penting** |
| **NOTIF-4** | Sistem menyediakan pusat notifikasi pada aplikasi web.               | **Penting** |

---

## 6.12 Audit Log dan Keamanan

| ID          | Kebutuhan Fungsional                            | Prioritas   |
| ----------- | ----------------------------------------------- | ----------- |
| **AUDIT-1** | Sistem mencatat aktivitas login dan logout.     | **Wajib**   |
| **AUDIT-2** | Sistem mencatat pembuatan pasien.               | **Wajib**   |
| **AUDIT-3** | Sistem mencatat perubahan data pasien.          | **Wajib**   |
| **AUDIT-4** | Sistem mencatat pembuatan rekam medis.          | **Wajib**   |
| **AUDIT-5** | Sistem mencatat perubahan data penting.         | **Wajib**   |
| **AUDIT-6** | Admin dapat melihat audit log sesuai hak akses. | **Penting** |

---

# 7. Alur Pengguna Utama (Key User Flows)

## 7.1 Alur Pendaftaran Pasien Melalui Website

1. Pasien membuka website publik ClinicOS milik suatu klinik.
2. Pasien melihat informasi klinik.
3. Pasien memilih layanan.
4. Pasien melihat dokter yang tersedia.
5. Pasien memilih dokter.
6. Pasien memilih tanggal kunjungan.
7. Sistem menampilkan jadwal yang tersedia.
8. Pasien mengisi data diri.
9. Sistem memeriksa apakah pasien sudah terdaftar.
10. Jika belum, sistem membuat data pasien baru.
11. Jika sudah, sistem menggunakan data pasien yang sudah tersedia.
12. Sistem membuat booking.
13. Sistem menghasilkan nomor antrean.
14. Sistem menampilkan detail booking kepada pasien.
15. Sistem menyimpan booking dengan status `BOOKED`.

---

## 7.2 Alur Kedatangan Pasien

1. Pasien datang ke klinik.
2. Resepsionis membuka dashboard antrean.
3. Resepsionis melihat daftar booking.
4. Resepsionis memverifikasi data pasien.
5. Sistem mengubah status menjadi `WAITING`.
6. Pasien masuk ke antrean aktif.
7. Resepsionis dapat melihat urutan pasien.

---

## 7.3 Alur Pemanggilan Pasien oleh Dokter

1. Dokter login ke ClinicOS.
2. Dokter membuka dashboard.
3. Sistem menampilkan antrean dokter.
4. Dokter memilih pasien berikutnya.
5. Sistem mengubah status pasien menjadi `CALLED`.
6. Pasien mendapatkan informasi bahwa gilirannya dipanggil.
7. Dokter memulai pemeriksaan.
8. Sistem mengubah status menjadi `IN_PROGRESS`.

---

## 7.4 Alur Pembuatan Rekam Medis

1. Dokter membuka pasien yang sedang diperiksa.
2. Sistem menampilkan data dasar pasien.
3. Sistem menampilkan riwayat kunjungan sesuai hak akses.
4. Dokter mengisi keluhan utama.
5. Dokter mengisi tanda vital.
6. Dokter mengisi pemeriksaan fisik.
7. Dokter menentukan diagnosis.
8. Dokter memasukkan tindakan.
9. Dokter membuat resep jika diperlukan.
10. Dokter menambahkan catatan.
11. Dokter menyimpan rekam medis.
12. Sistem mencatat identitas dokter dan waktu pembuatan.
13. Dokter menyelesaikan kunjungan.
14. Sistem mengubah status menjadi `COMPLETED`.
15. Dashboard klinik diperbarui.

---

## 7.5 Alur Dashboard Pendapatan

1. Clinic Admin login.
2. Admin membuka dashboard.
3. Sistem mengambil data kunjungan yang telah selesai.
4. Sistem mengambil harga layanan yang digunakan.
5. Sistem menghitung total pendapatan.
6. Sistem menampilkan pendapatan berdasarkan periode.
7. Admin dapat memilih filter tanggal.
8. Sistem memperbarui ringkasan dashboard.

---

## 7.6 Alur Antrean Realtime

1. Resepsionis membuka halaman antrean.
2. Dokter membuka dashboard antrean.
3. Resepsionis memilih pasien berikutnya.
4. Sistem memperbarui status antrean.
5. Laravel mengirim event realtime melalui Laravel Reverb.
6. Browser dokter dan halaman antrean pasien menerima perubahan.
7. Nomor antrean terbaru ditampilkan tanpa perlu reload halaman.

---

# 8. Model Data (High-Level)

| **Entitas**            | **Field Utama**                                                                                                                      | **Keterangan**                       |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------ |
| **users**              | `id`, `name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`                                                   | Menyimpan akun pengguna sistem.      |
| **clinics**            | `id`, `name`, `slug`, `address`, `phone`, `description`, `logo`, `status`, `created_at`, `updated_at`                                | Menyimpan informasi klinik.          |
| **clinic_settings**    | `id`, `clinic_id`, `opening_time`, `closing_time`, `timezone`, `queue_prefix`                                                        | Menyimpan konfigurasi klinik.        |
| **doctors**            | `id`, `user_id`, `clinic_id`, `specialization`, `license_number`, `phone`, `bio`, `status`                                           | Menyimpan profil dokter.             |
| **doctor_schedules**   | `id`, `doctor_id`, `day_of_week`, `start_time`, `end_time`, `quota`, `status`                                                        | Menyimpan jadwal praktik dokter.     |
| **patients**           | `id`, `clinic_id`, `medical_record_number`, `name`, `nik`, `birth_date`, `gender`, `phone`, `address`, `created_at`, `updated_at`    | Menyimpan data pasien.               |
| **services**           | `id`, `clinic_id`, `name`, `description`, `price`, `status`                                                                          | Menyimpan layanan klinik.            |
| **appointments**       | `id`, `clinic_id`, `patient_id`, `doctor_id`, `service_id`, `appointment_date`, `booking_code`, `status`, `notes`                    | Menyimpan data booking pasien.       |
| **queues**             | `id`, `appointment_id`, `queue_number`, `queue_date`, `status`, `called_at`, `completed_at`                                          | Menyimpan informasi antrean.         |
| **visits**             | `id`, `clinic_id`, `patient_id`, `doctor_id`, `appointment_id`, `service_id`, `started_at`, `completed_at`, `status`, `total_amount` | Menyimpan data kunjungan pasien.     |
| **medical_records**    | `id`, `visit_id`, `patient_id`, `doctor_id`, `subjective`, `objective`, `assessment`, `plan`, `created_at`, `updated_at`             | Menyimpan rekam medis pasien.        |
| **vital_signs**        | `id`, `medical_record_id`, `weight`, `height`, `temperature`, `blood_pressure`, `pulse`, `respiratory_rate`, `oxygen_saturation`     | Menyimpan tanda vital pasien.        |
| **diagnoses**          | `id`, `medical_record_id`, `diagnosis_code`, `diagnosis_name`, `notes`                                                               | Menyimpan diagnosis.                 |
| **treatments**         | `id`, `medical_record_id`, `name`, `description`, `notes`                                                                            | Menyimpan tindakan medis.            |
| **prescriptions**      | `id`, `medical_record_id`, `doctor_id`, `notes`                                                                                      | Menyimpan resep.                     |
| **prescription_items** | `id`, `prescription_id`, `medicine_name`, `dosage`, `frequency`, `quantity`, `instructions`                                          | Menyimpan detail obat dalam resep.   |
| **notifications**      | `id`, `user_id`, `type`, `title`, `message`, `read_at`                                                                               | Menyimpan notifikasi pengguna.       |
| **audit_logs**         | `id`, `user_id`, `clinic_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `created_at`                                       | Mencatat aktivitas penting pengguna. |

### Entitas Post-MVP

| **Entitas**            | **Keterangan**                          |
| ---------------------- | --------------------------------------- |
| **subscription_plans** | Paket berlangganan ClinicOS.            |
| **subscriptions**      | Subscription masing-masing klinik.      |
| **invoices**           | Tagihan subscription.                   |
| **payments**           | Riwayat pembayaran subscription.        |
| **branches**           | Cabang klinik.                          |
| **sync_records**       | Riwayat sinkronisasi perangkat lokal.   |
| **sync_conflicts**     | Konflik data pada sistem offline-first. |
| **devices**            | Perangkat yang terdaftar pada klinik.   |

---

# 9. Kebutuhan Non-Fungsional (Non-Functional Requirements)

### Responsivitas & Kompatibilitas Perangkat

Web ClinicOS harus memiliki desain responsif yang dapat digunakan pada:

* Desktop.
* Laptop.
* Tablet.
* Smartphone.

Sistem harus kompatibel dengan browser modern seperti:

* Google Chrome.
* Mozilla Firefox.
* Microsoft Edge.
* Safari.

---

### Performa

* Halaman utama dashboard diharapkan dapat dimuat dengan cepat pada kondisi server normal.
* Query database harus menggunakan indexing pada kolom yang sering digunakan untuk pencarian.
* Sistem harus menghindari query database berulang yang tidak diperlukan.
* Proses berat seperti pengiriman notifikasi dan pembuatan laporan dapat menggunakan Laravel Queue.

---

### Keamanan & Kontrol Akses

* Seluruh komunikasi client-server wajib menggunakan HTTPS pada production.
* Password pengguna harus disimpan menggunakan hashing yang aman.
* Sistem menggunakan Role-Based Access Control.
* Pengguna hanya dapat mengakses data sesuai role dan kliniknya.
* Data rekam medis harus memiliki pembatasan akses yang lebih ketat dibandingkan data administratif.
* Sistem mencatat aktivitas penting melalui audit log.
* Akses terhadap endpoint dan resource harus divalidasi menggunakan middleware dan policy Laravel.

---

### Integritas Data

* Database menggunakan foreign key untuk menjaga hubungan antarentitas.
* Operasi penting menggunakan database transaction.
* Data penting tidak dihapus secara permanen tanpa mekanisme yang sesuai.
* Sistem menggunakan soft delete pada entitas tertentu apabila diperlukan.
* Backup database dilakukan secara berkala.

---

### Skalabilitas

Arsitektur Laravel harus memungkinkan pengembangan dari:

```text
1 Klinik
    ↓
Beberapa Klinik
    ↓
Multi-Tenant SaaS
    ↓
Banyak Klinik + Banyak Cabang
```

Database dan aplikasi harus dirancang agar penambahan jumlah pasien, kunjungan, dan klinik tidak mengharuskan perubahan total terhadap arsitektur sistem.

---

### Maintainability

Kode harus mengikuti struktur dan konvensi Laravel yang konsisten.

Komponen utama dipisahkan menjadi:

```text
Models
Controllers
Services
Policies
Requests
Jobs
Events
Listeners
Livewire Components
Blade Views
```

Business logic yang kompleks tidak ditempatkan seluruhnya di Controller.

---

# 10. Integrasi Pihak Ketiga

| **Layanan**                  | **Fungsi**                                                  | **Catatan**                                |
| ---------------------------- | ----------------------------------------------------------- | ------------------------------------------ |
| **MySQL**                    | Penyimpanan database utama.                                 | Wajib untuk MVP.                           |
| **Laravel Reverb**           | Komunikasi realtime untuk antrean dan event aplikasi.       | MVP / sesuai kebutuhan.                    |
| **Laravel Queue**            | Pemrosesan background job seperti notifikasi dan laporan.   | MVP.                                       |
| **Email Service**            | Pengiriman email booking dan notifikasi.                    | Fase MVP/Lanjutan.                         |
| **WhatsApp API**             | Pengiriman notifikasi antrean dan booking melalui WhatsApp. | Post-MVP.                                  |
| **Payment Gateway**          | Pembayaran subscription ClinicOS.                           | Post-MVP.                                  |
| **Cloud Storage**            | Penyimpanan file atau dokumen medis tertentu.               | Post-MVP.                                  |
| **Firebase Cloud Messaging** | Push notification untuk mobile application.                 | Post-MVP.                                  |
| **AI API**                   | Bantuan dokumentasi dan ringkasan data medis.               | Post-MVP dan memerlukan evaluasi keamanan. |

---

# 11. Fitur Usulan / Fase Lanjutan

## 11.1 SaaS Multi-Tenant

ClinicOS dikembangkan dari:

```text
ClinicOS
    │
    └── Klinik A
```

menjadi:

```text
ClinicOS
    ├── Klinik A
    ├── Klinik B
    ├── Klinik C
    └── Klinik D
```

Setiap klinik memiliki:

* Data pasien sendiri.
* Dokter sendiri.
* Resepsionis sendiri.
* Layanan sendiri.
* Jadwal sendiri.
* Rekam medis sendiri.
* Dashboard sendiri.

Data antar-klinik harus terisolasi.

---

## 11.2 Subscription

ClinicOS dapat menyediakan beberapa paket:

```text
FREE / TRIAL
BASIC
PRO
BUSINESS
```

Fitur:

* Free trial.
* Subscription bulanan.
* Subscription tahunan.
* Upgrade.
* Downgrade.
* Invoice.
* Payment history.
* Expired subscription.
* Automatic subscription status.

---

## 11.3 Multi-Branch

Satu akun klinik dapat mempunyai beberapa cabang:

```text
Klinik Sehat
├── Cabang Batam Center
├── Cabang Sekupang
└── Cabang Bengkong
```

Data dapat difilter berdasarkan cabang.

---

## 11.4 Offline-First

Pada fase lanjutan, ClinicOS dapat dikembangkan menjadi:

```text
                 Cloud Server
                      ↕
                Sync Engine
                      ↕
              Local Clinic Server
                 ↙         ↘
          Receptionist     Doctor
```

Tujuannya agar operasional klinik tetap dapat berjalan ketika koneksi internet terganggu.

**Catatan:** penyimpanan lokal sebaiknya menggunakan perangkat/server yang dikontrol oleh klinik, bukan komputer pribadi dokter sebagai database utama.

---

## 11.5 Mobile Application

Aplikasi mobile dapat dikembangkan menggunakan Flutter:

### Patient App

* Booking.
* Nomor antrean.
* Status antrean.
* Riwayat kunjungan.
* Notifikasi.
* Profil pasien.

### Doctor App

* Jadwal.
* Antrean.
* Data pasien.
* Rekam medis.
* Notifikasi.

---

## 11.6 Advanced Analytics

Dashboard lanjutan dapat menampilkan:

* Jumlah pasien harian.
* Pertumbuhan pasien bulanan.
* Pasien baru vs pasien lama.
* Pendapatan bulanan.
* Pendapatan per layanan.
* Pendapatan per dokter.
* Jam tersibuk.
* Tingkat pembatalan.
* Tingkat no-show.
* Jumlah kunjungan per dokter.
* Layanan paling populer.

---

## 11.7 Notifikasi Multi-Channel

Sistem dapat diperluas dengan:

```text
ClinicOS
   ├── Web Notification
   ├── Push Notification
   ├── Email
   ├── WhatsApp
   └── SMS
```

Contoh:

> "Nomor antrean Anda A-023. Saat ini dokter sedang melayani A-020."

---

## 11.8 AI-Assisted Medical Documentation

Fitur AI dapat membantu dokter:

* Merangkum riwayat pasien.
* Membantu menyusun dokumentasi.
* Mengubah catatan menjadi struktur SOAP.
* Meringkas kunjungan sebelumnya.

AI **tidak dimaksudkan untuk menggantikan keputusan klinis dokter**.

---

# 12. Pertanyaan Terbuka / TBD

* Apakah pasien wajib membuat akun atau cukup menggunakan nomor telepon/kode booking?
* Apakah satu pasien dapat memiliki riwayat pada beberapa klinik yang berbeda?
* Apakah dokter dapat bekerja pada lebih dari satu klinik?
* Apakah pasien dapat memilih jam praktik atau hanya mengambil nomor antrean?
* Apakah antrean online dan antrean pasien datang langsung menggunakan satu sistem antrean?
* Bagaimana kebijakan pembatalan booking?
* Apakah klinik membutuhkan pembayaran langsung melalui sistem?
* Apakah resep hanya berupa catatan atau perlu terhubung dengan modul apotek?
* Apakah klinik membutuhkan pencetakan resep atau kartu pasien?
* Apakah sistem membutuhkan upload dokumen atau foto pendukung rekam medis?
* Berapa lama data rekam medis harus disimpan?
* Apakah fitur offline wajib untuk versi pertama atau cukup menjadi Post-MVP?
* Apakah satu klinik nantinya dapat memiliki banyak cabang?
* Model subscription seperti apa yang akan digunakan?
* Apakah setiap paket subscription memiliki batas jumlah dokter, pasien, atau transaksi?
* Layanan notifikasi apa yang akan digunakan untuk WhatsApp/email/push notification?
* Bagaimana kebijakan backup dan disaster recovery?
* Bagaimana mekanisme ekspor dan migrasi data apabila klinik berhenti menggunakan ClinicOS?

---

# 13. Glosarium

* **ClinicOS:** Platform digital untuk membantu klinik mengelola operasional, pasien, dokter, antrean, booking, dan rekam medis.
* **SaaS (Software as a Service):** Model penyediaan perangkat lunak di mana pengguna menggunakan aplikasi melalui internet dan umumnya membayar berdasarkan subscription.
* **Clinic Admin:** Pengguna yang mengelola operasional dan konfigurasi suatu klinik.
* **Doctor:** Pengguna yang bertanggung jawab melakukan pemeriksaan dan membuat rekam medis pasien.
* **Receptionist:** Pengguna yang bertanggung jawab terhadap administrasi pasien dan antrean.
* **Patient:** Pengguna yang menerima layanan klinik dan melakukan booking.
* **Super Admin:** Administrator pada tingkat platform ClinicOS yang mengelola seluruh tenant/klinik.
* **Booking:** Reservasi kunjungan pasien sebelum datang ke klinik.
* **Queue:** Sistem nomor antrean yang menentukan urutan pelayanan pasien.
* **Medical Record:** Catatan medis pasien yang dibuat dan dikelola oleh tenaga medis yang berwenang.
* **SOAP:** Struktur dokumentasi medis yang terdiri dari Subjective, Objective, Assessment, dan Plan.
* **RBAC (Role-Based Access Control):** Mekanisme pemberian hak akses berdasarkan role pengguna.
* **Livewire:** Framework Laravel untuk membuat antarmuka web interaktif tanpa harus membangun SPA frontend secara penuh.
* **Blade:** Template engine bawaan Laravel yang digunakan untuk membuat halaman web.
* **Laravel Reverb:** Infrastruktur realtime yang dapat digunakan Laravel untuk mengirim event secara langsung kepada client.
* **MySQL:** Sistem manajemen basis data relasional yang digunakan sebagai database utama ClinicOS.
* **Multi-Tenant:** Arsitektur SaaS yang memungkinkan satu platform melayani banyak organisasi/klinik dengan isolasi data.
* **Offline-First:** Pendekatan pengembangan sistem yang memungkinkan aplikasi tetap berfungsi ketika tidak terdapat koneksi internet dan melakukan sinkronisasi ketika koneksi tersedia.
* **Audit Log:** Catatan aktivitas pengguna yang digunakan untuk membantu pelacakan perubahan dan aktivitas sistem.
* **Post-MVP:** Fitur atau pengembangan yang dilakukan setelah fitur inti MVP berhasil dibuat dan diuji.

---

# Product Roadmap

## Phase 1 — MVP

```text
Laravel
+
Blade
+
Livewire
+
Tailwind
+
MySQL
```

Fokus:

```text
Authentication
       ↓
Clinic Management
       ↓
Doctor & Schedule
       ↓
Patient
       ↓
Booking
       ↓
Queue
       ↓
Medical Record
       ↓
Dashboard
       ↓
Audit Log
```

---

## Phase 2 — Clinic Enhancement

* Realtime queue.
* Advanced notification.
* Report export.
* Advanced dashboard.
* Email notification.
* Improved patient portal.
* Payment pasien.
* Prescription printing.

---

## Phase 3 — SaaS

```text
Clinic A ─┐
Clinic B ─┤
Clinic C ─┼──→ ClinicOS SaaS
Clinic D ─┘
```

Fitur:

* Multi-tenant.
* Super Admin.
* Subscription.
* Free trial.
* Payment gateway.
* Invoice.
* Multi-branch.

---

## Phase 4 — Advanced Platform

* Offline-first.
* Local clinic server.
* Synchronization engine.
* Flutter mobile application.
* WhatsApp notification.
* Advanced analytics.
* Third-party integrations.
* AI-assisted documentation.

---

# MVP Success Criteria

MVP dianggap berhasil apabila:

1. Clinic Admin dapat membuat dan mengelola data klinik.
2. Clinic Admin dapat membuat akun dokter dan resepsionis.
3. Clinic Admin dapat mengatur jadwal dokter.
4. Clinic Admin dapat membuat layanan dan harga.
5. Resepsionis dapat mendaftarkan pasien.
6. Pasien dapat melakukan booking melalui website publik.
7. Sistem dapat menghasilkan nomor antrean.
8. Resepsionis dapat mengelola antrean.
9. Dokter dapat melihat pasien yang harus dilayani.
10. Dokter dapat membuat rekam medis.
11. Dokter dapat mencatat diagnosis, tindakan, dan resep.
12. Dokter dapat menyelesaikan kunjungan.
13. Dashboard dapat menampilkan statistik dasar klinik.
14. Sistem dapat menghitung ringkasan pendapatan.
15. Hak akses antar-role berjalan dengan benar.
16. Aktivitas penting tercatat dalam audit log.
17. Data antar pengguna tidak dapat diakses tanpa permission yang sesuai.
18. Sistem dapat dijalankan menggunakan Laravel + MySQL tanpa frontend SPA terpisah.

---

# Batasan Produk MVP

MVP ClinicOS **tidak ditujukan untuk langsung menjadi platform SaaS klinik enterprise**.

MVP difokuskan pada pembuktian bahwa proses inti berikut dapat berjalan dalam satu sistem:

```text
Pasien
   ↓
Booking
   ↓
Antrean
   ↓
Resepsionis
   ↓
Dokter
   ↓
Pemeriksaan
   ↓
Rekam Medis
   ↓
Kunjungan Selesai
   ↓
Dashboard Klinik
```

Fitur seperti **multi-tenant, subscription, offline-first, mobile application, WhatsApp, payment gateway, AI, dan multi-cabang** sengaja ditempatkan pada fase Post-MVP agar pengembangan versi pertama tetap terkontrol dan dapat diselesaikan secara realistis.

---

*Dokumen ini merupakan draft sementara dan dapat berubah seiring proses validasi kebutuhan klinik, hasil pengujian MVP, dan pembahasan lebih lanjut dengan calon pengguna/klien.*
