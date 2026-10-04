# ClinicOS — Development Plan

> **Status:** Development — Clinic Core selesai
> **Version:** 0.1
> **Project:** ClinicOS
> **Architecture:** Full Laravel
> **Database:** MySQL
> **Frontend:** Blade + Livewire + Tailwind CSS

Catatan implementasi 4 Oktober 2026: M1 dan M2 pada [Development-Plan.md](Development-Plan.md) telah diverifikasi dengan 74 tests / 284 assertions, build frontend, kompilasi Blade, dan migration MySQL lokal. Admin dapat mengelola profil klinik, dokter, resepsionis, layanan, dan jadwal dengan isolasi data per klinik. Repository Git lokal tersedia, tetapi remote GitHub belum dikonfigurasi.

---

# 1. Project Overview

ClinicOS adalah platform manajemen klinik berbasis web yang membantu klinik mengelola:

* Data klinik
* Dokter
* Resepsionis
* Pasien
* Jadwal dokter
* Layanan
* Booking
* Antrean
* Rekam medis
* Diagnosis
* Tindakan
* Resep
* Dashboard
* Pendapatan
* Notifikasi
* Audit log

Target pengembangan pertama adalah membuat **MVP yang dapat digunakan oleh satu klinik**.

Arsitektur MVP:

```text
Browser
   │
   ▼
Laravel
   ├── Blade
   ├── Livewire
   ├── Controllers
   ├── Services
   ├── Policies
   ├── Jobs
   └── Events
   │
   ▼
MySQL
```

Realtime:

```text
Laravel
   │
   ▼
Laravel Reverb
   │
   ▼
Browser
```

---

# 2. Development Principles

Pengembangan ClinicOS mengikuti prinsip:

1. **MVP first**
2. Jangan mengembangkan fitur Post-MVP sebelum fitur MVP stabil.
3. Database dirancang sejak awal agar dapat dikembangkan menjadi SaaS.
4. Hak akses harus diterapkan sejak awal.
5. Data medis harus diperlakukan sebagai data sensitif.
6. Business logic tidak diletakkan seluruhnya di Controller.
7. Setiap fitur utama harus dapat diuji secara independen.
8. Perubahan database menggunakan Laravel Migration.
9. Data penting menggunakan database transaction jika diperlukan.
10. Setiap fitur selesai harus diuji sebelum melanjutkan ke fitur berikutnya.

---

# 3. Technology Stack

## 3.1 Core

```text
Laravel
PHP
MySQL
```

## 3.2 Frontend

```text
Blade
Livewire
Tailwind CSS
```

## 3.3 Authentication & Authorization

```text
Laravel Breeze / Fortify
Spatie Laravel Permission
Laravel Policies
Middleware
```

## 3.4 Realtime

```text
Laravel Reverb
Laravel Events
Laravel Broadcasting
```

## 3.5 Background Processing

```text
Laravel Queue
Laravel Scheduler
Laravel Jobs
```

## 3.6 Development Environment

```text
Git
GitHub
Composer
Node.js / NPM
Laravel Artisan
MySQL
```

---

# 4. Development Phases

## Phase 0 — Project Setup

### Tujuan

Mempersiapkan environment dan struktur dasar Laravel.

### Tasks

* [ ] Membuat repository GitHub.
* [x] Membuat project Laravel.
* [x] Mengatur `.env`.
* [x] Membuat database MySQL.
* [x] Menghubungkan Laravel dengan MySQL.
* [x] Menjalankan migration awal.
* [x] Mengatur Git.
* [x] Membuat `.gitignore`.
* [x] Menginstall dependency frontend.
* [x] Mengatur Tailwind CSS.
* [x] Mengatur layout Blade.
* [x] Mengatur struktur folder project.
* [x] Membuat halaman landing sementara.
* [x] Memastikan aplikasi dapat berjalan di local environment.

### Definition of Done

```text
Laravel berhasil dijalankan
        ↓
MySQL terhubung
        ↓
Migration berhasil
        ↓
Blade berhasil dirender
        ↓
Tailwind berhasil
        ↓
Git repository siap
```

---

# 5. Phase 1 — Authentication

### Tujuan

Membuat sistem login dan akun pengguna.

### Tasks

* [x] Install Laravel authentication.
* [x] Membuat login.
* [x] Membuat logout.
* [x] Membuat password hashing.
* [x] Membuat profile pengguna.
* [x] Membuat status akun aktif/nonaktif.
* [x] Install Spatie Permission.
* [x] Membuat role:

  * [x] Clinic Admin
  * [x] Doctor
  * [x] Receptionist
  * [x] Patient
* [x] Membuat permission dasar.
* [x] Membuat middleware role.
* [ ] Membuat authorization policy.

### Permission awal

```text
clinic.view
clinic.update

doctor.view
doctor.create
doctor.update
doctor.delete

patient.view
patient.create
patient.update

appointment.view
appointment.create
appointment.update
appointment.cancel

queue.view
queue.manage

medical_record.view
medical_record.create
medical_record.update

dashboard.view

audit_log.view
```

### Definition of Done

Setiap role hanya dapat mengakses halaman dan fitur yang sesuai.

---

# 6. Phase 2 — Clinic Management

### Tujuan

Membuat konfigurasi dasar klinik.

### Tasks

* [ ] Membuat migration `clinics`.
* [ ] Membuat model Clinic.
* [ ] Membuat controller/service Clinic.
* [ ] Membuat halaman profil klinik.
* [ ] Membuat edit profil klinik.
* [ ] Menambahkan logo klinik.
* [ ] Menambahkan alamat.
* [ ] Menambahkan nomor telepon.
* [ ] Menambahkan deskripsi.
* [ ] Menambahkan jam operasional.
* [ ] Menambahkan clinic settings.
* [ ] Membuat slug klinik.

### Data utama

```text
Clinic
├── Name
├── Slug
├── Address
├── Phone
├── Description
├── Logo
└── Status
```

### Definition of Done

Clinic Admin dapat membuat dan mengubah informasi kliniknya.

---

# 7. Phase 3 — Doctor Management

### Tujuan

Membuat pengelolaan dokter.

### Tasks

* [ ] Membuat migration `doctors`.
* [ ] Membuat Doctor Model.
* [ ] Membuat relasi User → Doctor.
* [ ] Membuat daftar dokter.
* [ ] Membuat tambah dokter.
* [ ] Membuat edit dokter.
* [ ] Membuat detail dokter.
* [ ] Membuat aktivasi/nonaktivasi dokter.
* [ ] Menambahkan spesialisasi.
* [ ] Menambahkan nomor izin/profil jika dibutuhkan.
* [ ] Membuat jadwal dokter.

### Definition of Done

Clinic Admin dapat membuat dokter dan mengatur status dokter.

---

# 8. Phase 4 — Doctor Schedule

### Tujuan

Mengatur jadwal praktik dokter.

### Tasks

* [ ] Membuat migration `doctor_schedules`.
* [ ] Membuat model DoctorSchedule.
* [ ] Membuat jadwal berdasarkan hari.
* [ ] Menentukan jam mulai.
* [ ] Menentukan jam selesai.
* [ ] Menentukan kuota pasien.
* [ ] Mengaktifkan/nonaktifkan jadwal.
* [ ] Menampilkan jadwal pada dashboard admin.
* [ ] Menampilkan jadwal pada website publik.

### Contoh

```text
Senin
08:00 - 12:00
Kuota: 20 pasien

Rabu
13:00 - 17:00
Kuota: 20 pasien
```

### Definition of Done

Sistem dapat menentukan kapan dokter tersedia untuk booking.

---

# 9. Phase 5 — Service Management

### Tujuan

Mengelola layanan dan harga klinik.

### Tasks

* [ ] Membuat migration `services`.
* [ ] Membuat Service Model.
* [ ] Tambah layanan.
* [ ] Edit layanan.
* [ ] Nonaktifkan layanan.
* [ ] Menentukan harga.
* [ ] Menampilkan layanan pada website publik.

### Contoh

```text
Konsultasi Umum     Rp50.000
Pemeriksaan Gigi    Rp75.000
Medical Check-up    Rp150.000
```

### Definition of Done

Layanan aktif dapat dipilih ketika pasien melakukan booking.

---

# 10. Phase 6 — Patient Management

### Tujuan

Membuat sistem data pasien.

### Tasks

* [x] Membuat migration `patients`.
* [x] Membuat Patient Model.
* [x] Membuat nomor rekam medis.
* [x] Membuat form pasien.
* [x] Membuat daftar pasien.
* [x] Membuat pencarian pasien.
* [x] Membuat detail pasien.
* [x] Membuat edit data pasien.
* [x] Menampilkan riwayat kunjungan.
* [x] Menerapkan authorization.

### Data pasien awal

```text
Medical Record Number
Name
NIK
Birth Date
Gender
Phone
Address
```

### Definition of Done

Resepsionis dapat membuat, mencari, dan memperbarui data pasien.

---

# 11. Phase 7 — Public Clinic Website

### Tujuan

Menyediakan halaman yang dapat digunakan calon pasien tanpa login.

### Halaman

```text
/clinic/{slug}

├── Home
├── About
├── Services
├── Doctors
├── Schedule
└── Booking
```

### Tasks

* [x] Membuat route publik.
* [x] Membuat clinic landing page.
* [x] Menampilkan informasi klinik.
* [x] Menampilkan layanan.
* [x] Menampilkan dokter.
* [x] Menampilkan jadwal.
* [x] Membuat halaman booking.

### Definition of Done

Pengguna dapat membuka website klinik dan melihat informasi layanan serta dokter.

---

# 12. Phase 8 — Booking

### Tujuan

Memungkinkan pasien mendaftarkan kunjungan.

### Tasks

* [x] Membuat migration `appointments`.
* [x] Membuat Appointment Model.
* [x] Membuat form booking.
* [x] Memilih layanan.
* [x] Memilih dokter.
* [x] Memilih tanggal.
* [x] Memvalidasi jadwal dokter.
* [x] Memvalidasi kuota.
* [x] Membuat booking code.
* [x] Menyimpan booking.
* [x] Menampilkan detail booking.
* [x] Membuat status booking.

### Status

```text
BOOKED
CANCELLED
COMPLETED
NO_SHOW
```

### Definition of Done

Pasien dapat melakukan booking dan mendapatkan nomor/identitas booking.

---

# 13. Phase 9 — Queue Management

### Tujuan

Membangun sistem antrean klinik.

### Tasks

* [x] Membuat migration `queues`.
* [x] Membuat Queue Model.
* [x] Generate nomor antrean.
* [x] Menampilkan antrean hari ini.
* [x] Mengubah booking menjadi `WAITING`.
* [x] Memanggil pasien.
* [x] Skip pasien.
* [ ] Memulai pemeriksaan.
* [ ] Menyelesaikan pemeriksaan.
* [x] Membatalkan antrean.
* [x] Menampilkan antrean aktif.

### Status

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

Alternatif:

```text
CANCELLED
SKIPPED
NO_SHOW
```

### Definition of Done

Resepsionis dapat mengelola antrean dari pasien datang sampai selesai.

---

# 14. Phase 10 — Medical Record

### Tujuan

Membuat modul rekam medis digital.

### Tasks

* [x] Membuat migration `visits`.
* [x] Membuat migration `medical_records`.
* [x] Membuat migration `vital_signs`.
* [x] Membuat migration `diagnoses`.
* [x] Membuat migration `treatments`.
* [x] Membuat migration `prescriptions`.
* [x] Membuat migration `prescription_items`.
* [x] Membuat relasi antar model.
* [x] Membuat halaman pemeriksaan dokter.
* [x] Membuat form SOAP.
* [x] Membuat form tanda vital.
* [x] Membuat diagnosis.
* [x] Membuat tindakan.
* [x] Membuat resep.
* [x] Menyimpan rekam medis.
* [x] Mencatat dokter pembuat.
* [x] Mencatat timestamp.
* [x] Membatasi akses rekam medis.

### Struktur pemeriksaan

```text
Patient
   ↓
Visit
   ↓
Medical Record
   ├── Subjective
   ├── Objective
   ├── Assessment
   └── Plan
```

### Definition of Done

Dokter dapat menyelesaikan pemeriksaan dan menyimpan rekam medis pasien.

---

# 15. Phase 11 — Visit & Transaction

### Tujuan

Menghubungkan pelayanan medis dengan transaksi.

### Tasks

* [x] Membuat data kunjungan.
* [x] Menghubungkan visit dengan appointment.
* [x] Menghubungkan visit dengan doctor.
* [x] Menghubungkan visit dengan service.
* [x] Menyimpan harga layanan saat transaksi.
* [x] Menghitung total biaya.
* [x] Menyelesaikan visit.
* [x] Mengubah status antrean menjadi `COMPLETED`.

### Definition of Done

Setiap kunjungan selesai memiliki data layanan dan nilai transaksi.

---

# 16. Phase 12 — Dashboard

### Tujuan

Menyediakan informasi ringkas untuk pengguna.

## Clinic Admin Dashboard

Menampilkan:

```text
Pasien Hari Ini
Booking Hari Ini
Kunjungan Selesai
Antrean Aktif
Pendapatan Hari Ini
```

## Doctor Dashboard

Menampilkan:

```text
Jadwal Hari Ini
Pasien Menunggu
Pasien Sedang Diperiksa
Pasien Selesai
```

## Receptionist Dashboard

Menampilkan:

```text
Booking Hari Ini
Antrean
Pasien Menunggu
Pasien Dipanggil
```

### Tasks

* [ ] Membuat dashboard layout.
* [ ] Membuat statistik pasien.
* [ ] Membuat statistik booking.
* [ ] Membuat statistik kunjungan.
* [ ] Membuat statistik antrean.
* [ ] Membuat revenue summary.
* [ ] Membuat filter tanggal.
* [ ] Membuat dashboard sesuai role.

---

# 17. Phase 13 — Notification

### Tujuan

Memberikan informasi kepada pengguna mengenai perubahan penting.

### MVP

* [ ] Booking berhasil.
* [ ] Booking dibatalkan.
* [ ] Pasien dipanggil.
* [ ] Perubahan status antrean.

### Tasks

* [ ] Membuat migration `notifications`.
* [ ] Membuat Notification Model.
* [ ] Membuat Laravel Notification.
* [ ] Membuat notification center.
* [ ] Menandai notification sebagai read.
* [ ] Menghubungkan notification dengan antrean.

---

# 18. Phase 14 — Realtime Queue

### Tujuan

Membuat perubahan antrean dapat diterima tanpa reload halaman.

### Tasks

* [ ] Install Laravel Reverb.
* [ ] Konfigurasi broadcasting.
* [ ] Membuat QueueUpdated Event.
* [ ] Membuat listener pada Livewire.
* [ ] Mengirim event ketika antrean berubah.
* [ ] Mengupdate tampilan antrean secara realtime.
* [ ] Menguji beberapa browser secara bersamaan.

### Flow

```text
Receptionist
     │
     │ Call Patient
     ▼
Laravel
     │
     ▼
QueueUpdated Event
     │
     ▼
Laravel Reverb
     │
     ├──────────► Doctor Browser
     │
     └──────────► Patient Queue Display
```

---

# 19. Phase 15 — Audit Log

### Tujuan

Mencatat aktivitas penting pada sistem.

### Tasks

* [ ] Membuat migration `audit_logs`.
* [ ] Membuat AuditLog Model.
* [ ] Mencatat login.
* [ ] Mencatat logout.
* [ ] Mencatat pembuatan pasien.
* [ ] Mencatat perubahan pasien.
* [ ] Mencatat pembuatan rekam medis.
* [ ] Mencatat perubahan data penting.
* [ ] Membuat halaman audit log.
* [ ] Membatasi akses audit log hanya untuk role tertentu.

---

# 20. Phase 16 — Security Hardening

### Tujuan

Memastikan MVP memiliki keamanan dasar yang memadai.

### Tasks

* [ ] Validasi seluruh input.
* [ ] Form Request Validation.
* [ ] Authorization Policy.
* [ ] Role middleware.
* [ ] CSRF protection.
* [ ] Password hashing.
* [ ] Rate limiting untuk endpoint tertentu.
* [ ] HTTPS pada production.
* [ ] SQL injection prevention melalui Eloquent/Query Builder.
* [ ] XSS protection.
* [ ] File upload validation.
* [ ] Audit log.
* [ ] Session security.
* [ ] Database backup.

---

# 21. Phase 17 — Testing

### Unit Test

* [ ] User authentication.
* [ ] Role permission.
* [ ] Patient creation.
* [ ] Booking.
* [ ] Queue number generation.
* [ ] Queue status transition.
* [ ] Medical record creation.
* [ ] Revenue calculation.

### Feature Test

* [ ] Patient registration.
* [ ] Patient booking.
* [ ] Receptionist queue management.
* [ ] Doctor examination.
* [ ] Medical record creation.
* [ ] Visit completion.
* [ ] Dashboard calculation.

### Authorization Test

Pastikan:

```text
Doctor
    X mengubah data klinik

Receptionist
    X mengubah rekam medis dokter

Patient
    X mengakses dashboard admin

Clinic Admin
    ✓ mengelola dokter
    ✓ mengelola layanan
    ✓ melihat dashboard
```

---

# 22. Phase 18 — UI/UX Polish

### Tasks

* [ ] Membuat design system sederhana.
* [ ] Menentukan typography.
* [ ] Membuat button component.
* [ ] Membuat input component.
* [ ] Membuat modal component.
* [ ] Membuat table component.
* [ ] Membuat badge/status component.
* [ ] Membuat notification component.
* [ ] Membuat responsive navigation.
* [ ] Membuat mobile responsive.
* [ ] Empty state.
* [ ] Loading state.
* [ ] Error state.
* [ ] Confirmation dialog.

---

# 23. Phase 19 — Deployment

### Environment

```text
Development
     ↓
Testing
     ↓
Production
```

### Tasks

* [ ] Menyiapkan VPS.
* [ ] Install Linux.
* [ ] Install PHP.
* [ ] Install MySQL.
* [ ] Install Composer.
* [ ] Install Node.js.
* [ ] Install Nginx.
* [ ] Konfigurasi domain.
* [ ] Konfigurasi HTTPS.
* [ ] Setup environment production.
* [ ] Setup database production.
* [ ] Setup storage.
* [ ] Setup queue worker.
* [ ] Setup scheduler.
* [ ] Setup Reverb.
* [ ] Setup database backup.
* [ ] Deploy Laravel.
* [ ] Menjalankan migration production.
* [ ] Menjalankan optimization Laravel.

---

# 24. MVP Final Testing

Sebelum MVP dinyatakan selesai, lakukan simulasi penuh.

## Scenario 1 — Pasien Baru

```text
Pasien
 ↓
Website Klinik
 ↓
Booking
 ↓
Nomor Antrean
 ↓
Datang ke Klinik
 ↓
Waiting
```

## Scenario 2 — Pasien Lama

```text
Pasien
 ↓
Booking
 ↓
Sistem menemukan pasien
 ↓
Booking baru
 ↓
Queue
```

## Scenario 3 — Pemeriksaan

```text
Queue
 ↓
Called
 ↓
In Progress
 ↓
Doctor
 ↓
Medical Record
 ↓
Diagnosis
 ↓
Treatment
 ↓
Prescription
 ↓
Completed
```

## Scenario 4 — Dashboard

```text
Completed Visit
       ↓
Transaction
       ↓
Revenue
       ↓
Dashboard
```

---

# 25. MVP Definition of Done

MVP dianggap selesai jika seluruh flow utama berikut berjalan:

```text
                    ┌─────────────┐
                    │   Patient   │
                    └──────┬──────┘
                           │
                        Booking
                           │
                           ▼
                    ┌─────────────┐
                    │    Queue    │
                    └──────┬──────┘
                           │
                     Receptionist
                           │
                           ▼
                    ┌─────────────┐
                    │    Doctor   │
                    └──────┬──────┘
                           │
                      Examination
                           │
                           ▼
                    ┌─────────────┐
                    │   Medical   │
                    │    Record   │
                    └──────┬──────┘
                           │
                       Completed
                           │
                           ▼
                    ┌─────────────┐
                    │  Dashboard  │
                    └─────────────┘
```

Checklist:

* [ ] Authentication berjalan.
* [ ] Role & permission berjalan.
* [ ] Clinic management berjalan.
* [ ] Doctor management berjalan.
* [ ] Doctor schedule berjalan.
* [ ] Service management berjalan.
* [ ] Patient management berjalan.
* [ ] Public clinic website berjalan.
* [ ] Booking berjalan.
* [ ] Queue berjalan.
* [ ] Medical record berjalan.
* [ ] Prescription berjalan.
* [ ] Visit completion berjalan.
* [ ] Dashboard berjalan.
* [ ] Notification dasar berjalan.
* [ ] Audit log berjalan.
* [ ] Authorization berjalan.
* [ ] Database backup tersedia.
* [ ] Responsive UI selesai.
* [ ] Testing utama selesai.
* [ ] Production deployment berhasil.

---

# 26. Post-MVP Plan

Setelah MVP stabil, pengembangan dilanjutkan secara bertahap.

## Post-MVP 1 — Advanced Clinic Features

* [ ] Advanced reporting.
* [ ] PDF export.
* [ ] Excel export.
* [ ] Advanced dashboard.
* [ ] Patient history improvement.
* [ ] Prescription printing.
* [ ] Email notification.
* [ ] WhatsApp notification.

---

## Post-MVP 2 — SaaS

* [ ] Multi-tenant architecture.
* [ ] Super Admin.
* [ ] Clinic registration.
* [ ] Subscription plans.
* [ ] Free trial.
* [ ] Subscription status.
* [ ] Invoice.
* [ ] Payment gateway.
* [ ] Subscription renewal.
* [ ] Upgrade/downgrade.

---

## Post-MVP 3 — Multi-Branch

* [ ] Branch management.
* [ ] Doctor branch assignment.
* [ ] Patient branch.
* [ ] Branch dashboard.
* [ ] Branch revenue.
* [ ] Cross-branch reporting.

---

## Post-MVP 4 — Offline-First

Arsitektur:

```text
             Cloud
               ↕
         Sync Engine
               ↕
        Local Clinic Server
          ↙           ↘
 Receptionist        Doctor
```

Tasks:

* [ ] Local database.
* [ ] Local authentication.
* [ ] Offline patient data.
* [ ] Offline medical records.
* [ ] Offline queue.
* [ ] Sync engine.
* [ ] Retry mechanism.
* [ ] Sync status.
* [ ] Conflict detection.
* [ ] Conflict resolution.
* [ ] Backup local database.

**Catatan:** database lokal sebaiknya berada pada perangkat/server yang dikontrol klinik, bukan komputer pribadi dokter.

---

## Post-MVP 5 — Mobile Application

### Patient App

* [ ] Login.
* [ ] Booking.
* [ ] Queue.
* [ ] Notification.
* [ ] Visit history.
* [ ] Profile.

### Doctor App

* [ ] Login.
* [ ] Schedule.
* [ ] Queue.
* [ ] Patient.
* [ ] Medical record.
* [ ] Notification.

Teknologi:

```text
Flutter
      ↓
Laravel API
      ↓
MySQL
```

---

## Post-MVP 6 — Advanced Security

* [ ] Two-Factor Authentication.
* [ ] Device management.
* [ ] Session management.
* [ ] Advanced audit log.
* [ ] Encryption at rest.
* [ ] Key management.
* [ ] Automated backup.
* [ ] Disaster recovery.
* [ ] Security monitoring.
* [ ] Penetration testing.

---

## Post-MVP 7 — AI

Kemungkinan fitur:

* [ ] Medical history summarization.
* [ ] SOAP documentation assistance.
* [ ] Administrative chatbot.
* [ ] Patient FAQ.
* [ ] Report summarization.

AI hanya berfungsi sebagai **alat bantu** dan tidak menggantikan keputusan klinis dokter.

---

# 27. Recommended Development Order

Urutan pengerjaan yang direkomendasikan:

```text
1. Project Setup
        ↓
2. Authentication
        ↓
3. Role & Permission
        ↓
4. Clinic Management
        ↓
5. Doctor Management
        ↓
6. Doctor Schedule
        ↓
7. Service Management
        ↓
8. Patient Management
        ↓
9. Public Clinic Website
        ↓
10. Booking
        ↓
11. Queue
        ↓
12. Visit
        ↓
13. Medical Record
        ↓
14. Prescription
        ↓
15. Transaction
        ↓
16. Dashboard
        ↓
17. Notification
        ↓
18. Realtime
        ↓
19. Audit Log
        ↓
20. Security
        ↓
21. Testing
        ↓
22. UI/UX Polish
        ↓
23. Deployment
        ↓
24. MVP Release
```

---

# 28. Prioritas Pengembangan

## P0 — Wajib untuk MVP

```text
Authentication
Role & Permission
Clinic
Doctor
Schedule
Service
Patient
Booking
Queue
Visit
Medical Record
Prescription
Dashboard
```

## P1 — Penting untuk MVP

```text
Notification
Realtime Queue
Audit Log
Security Hardening
Responsive UI
Testing
```

## P2 — Post-MVP

```text
Multi-Tenant
Subscription
Payment
Multi-Branch
Offline-First
Mobile App
WhatsApp
Advanced Analytics
AI
```

---

# 29. Project Milestone

## Milestone 1 — Foundation

```text
[ ] Laravel
[ ] MySQL
[ ] Authentication
[ ] Role
[ ] Permission
```

## Milestone 2 — Clinic Core

```text
[x] Clinic
[x] Doctor
[x] Schedule
[x] Service
```

## Milestone 3 — Patient & Booking

```text
[ ] Patient
[ ] Public Website
[ ] Booking
```

## Milestone 4 — Queue

```text
[ ] Queue
[ ] Receptionist
[ ] Queue Status
[ ] Calling
```

## Milestone 5 — Medical

```text
[x] Visit
[x] Medical Record
[x] Diagnosis
[x] Treatment
[x] Prescription
```

## Milestone 6 — Management

```text
[ ] Dashboard
[ ] Revenue
[ ] Notification
[ ] Audit Log
```

## Milestone 7 — Release

```text
[ ] Testing
[ ] Security
[ ] Responsive
[ ] Deployment
[ ] MVP Release
```

---

# 30. Final Target Architecture

```text
                         CLINICOS
                             │
                 ┌───────────┴───────────┐
                 │                       │
              Public                  Internal
              Website                  App
                 │                       │
                 └───────────┬───────────┘
                             │
                         Laravel
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                  │
       Blade              Livewire          Laravel API
          │                  │                  │
          └──────────────────┼──────────────────┘
                             │
                     Business Logic
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                  │
        MySQL             Reverb             Queue
          │                  │                  │
          │                  │                  │
      Database           Realtime         Background Jobs
```

Target jangka panjang:

```text
                        ClinicOS SaaS
                              │
             ┌────────────────┼────────────────┐
             │                │                │
          Web App         Mobile App      Public Website
             │                │                │
             └────────────────┼────────────────┘
                              │
                           Laravel
                              │
                 ┌────────────┼────────────┐
                 │            │            │
              MySQL        Reverb        Queue
                 │
             Multi-Tenant
                 │
       ┌─────────┼─────────┐
       │         │         │
    Clinic A  Clinic B  Clinic C
```

---

# 31. Current Development Target

Untuk tahap sekarang, **jangan langsung mengerjakan seluruh roadmap**.

Target pertama adalah:

> **Menyelesaikan MVP ClinicOS yang dapat menjalankan satu siklus pelayanan pasien secara lengkap dari booking sampai rekam medis dan dashboard.**

Flow utama yang harus menjadi prioritas:

```text
Clinic Admin
    ↓
Setup Clinic
    ↓
Create Doctor
    ↓
Create Schedule
    ↓
Create Service
    ↓
        Patient
          ↓
        Booking
          ↓
        Queue
          ↓
    Receptionist
          ↓
        Doctor
          ↓
      Examination
          ↓
    Medical Record
          ↓
      Completed
          ↓
       Revenue
          ↓
      Dashboard
```

Setelah flow tersebut stabil, baru lanjutkan ke **realtime, notification, audit log, security hardening, testing, dan deployment**.

Setelah MVP benar-benar selesai, pengembangan dapat dilanjutkan ke **SaaS multi-tenant, subscription, multi-branch, offline-first, mobile application, dan fitur lanjutan lainnya**.
