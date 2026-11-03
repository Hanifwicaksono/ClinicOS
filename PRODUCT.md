# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

ClinicOS terutama ditujukan untuk klinik kecil dan menengah di Indonesia.

- Pemilik klinik adalah pembeli dan pengelola akun klinik.
- Resepsionis menjalankan pendaftaran pasien, appointment, check-in, dan antrean harian.
- Dokter menjalankan pemeriksaan, rekam medis elektronik, resep, dan koreksi catatan final untuk pasien yang ditugaskan kepadanya.
- Pasien membuat dan memantau appointment miliknya.
- Platform Admin mengelola metadata tenant dan aspek komersial tanpa memperoleh akses otomatis ke isi medis.

## Product Purpose

ClinicOS menyatukan operasional klinik dari booking, antrean, pemeriksaan, hingga rekam medis dan resep dalam satu aplikasi SaaS berbasis web. Produk berhasil ketika staf dapat menjalankan alur pelayanan harian secara konsisten, pasien memperoleh status yang jelas, dan data setiap klinik serta catatan medis tetap terisolasi sesuai kewenangan.

## Positioning

Nilai utama ClinicOS adalah satu alur terintegrasi: booking menghasilkan appointment dan antrean, antrean mengarahkan pemeriksaan dokter, lalu pemeriksaan menghasilkan RME dan resep. Alur tersebut berjalan dalam konteks tenant klinik yang sama, dengan antrean realtime dan kontrol akses berdasarkan peran.

## Operating Context

- Digunakan melalui browser desktop, tablet, dan mobile di lingkungan operasional klinik.
- Pemilik menyiapkan klinik, staf, dokter, layanan, jadwal, dan subscription.
- Resepsionis mengelola pasien, appointment, check-in, dan pergerakan antrean.
- Dokter memulai pemeriksaan dari antrean yang dipanggil, menyimpan draft, memfinalisasi RME, serta mencatat koreksi beralasan.
- Pasien menggunakan halaman publik dan area terautentikasi untuk booking serta status appointment.
- Tampilan antrean publik hanya memuat informasi nonmedis.

## Capabilities and Constraints

- Aplikasi adalah monolith Laravel dengan Blade, Tailwind CSS, Livewire/Volt, dan Alpine sesuai pola yang sudah ada; bukan SPA atau frontend React terpisah.
- Lima role yang dipertahankan adalah `Clinic Admin`, `Receptionist`, `Doctor`, `Patient`, dan `Platform Admin`. Pemilik klinik menggunakan role `Clinic Admin` dengan relasi kepemilikan eksplisit.
- SaaS saat ini memakai satu database bersama dengan isolasi tenant berbasis `clinic_id` dan relasi kepemilikan.
- Implementasi saat ini mendukung satu pemilik untuk satu klinik dan satu subscription per klinik; multi-cabang belum tersedia.
- Workflow inti yang harus tetap terintegrasi adalah booking, appointment, antrean, visit, RME, resep, notifikasi, audit, subscription, dan pembayaran subscription DOKU.
- Antrean realtime menggunakan Reverb/Echo dengan pemulihan koneksi dan fallback polling.
- Foto medis disimpan secara privat dan hanya disajikan melalui endpoint yang diotorisasi.
- Bahasa antarmuka utama adalah Bahasa Indonesia.
- Privasi data medis dan isolasi data klinik adalah batas produk yang tidak boleh dikompromikan.

## Brand Commitments

- Nama produk adalah ClinicOS.
- Gunakan identitas dan aset ClinicOS yang sudah ada di repository; perubahan identitas merek memerlukan keputusan eksplisit.
- Suara produk harus jelas, ringkas, profesional, dan mudah dipahami oleh staf klinik serta pasien di Indonesia.

## Evidence on Hand

- Implementasi fitur dan arsitektur tersedia di source Laravel, route, service, model, policy, migration, dan test repository.
- Requirement dan batas implementasi terdokumentasi di `PROJECT_ANALYSIS.md`, `Development-Plan.md`, `PRD.md`, `Plan.md`, dan dokumentasi terkait di `docs/`.
- Aset merek tersedia di `public/assets/logo/` dan token visual incumbent berada di `tailwind.config.js`, `resources/css/app.css`, serta `public/css/clinicos-landing.css`.
- Repository belum menyediakan testimonial, studi kasus pelanggan, sertifikasi, atau benchmark produksi yang boleh dijadikan klaim pemasaran.

## Product Principles

1. Pertahankan kesinambungan alur dari booking sampai rekam medis; jangan membuat modul yang saling terputus.
2. Batasi setiap data dan tindakan ke klinik, resource, serta peran yang telah divalidasi server-side.
3. Utamakan kejelasan status dan pemulihan kegagalan untuk pekerjaan klinik yang sensitif terhadap waktu.
4. Sajikan antarmuka responsif yang tetap efisien bagi resepsionis, dokter, pemilik, dan pasien.
5. Bedakan fitur yang tersimpan, terintegrasi di UI, dan telah diverifikasi operasional agar produk tidak membuat klaim berlebihan.
