# ClinicOS

Aplikasi manajemen klinik SaaS berbasis Laravel, Blade, Livewire/Volt, Alpine dan Tailwind CSS. MySQL digunakan untuk operasional; pengujian lokal dapat memakai SQLite terisolasi.

## Cakupan implementasi

Role: Clinic Admin, Receptionist, Doctor, Patient dan Platform Admin. Pemilik klinik memakai Clinic Admin dengan relasi owner eksplisit. Implementasi mencakup onboarding satu klinik per owner, subscription/DOKU, staf, layanan/jadwal, booking, antrean, pemeriksaan, RME/resep, koreksi berhistori, notifikasi dan audit aktivitas. Isi medis tetap dibatasi kepada dokter yang ditugaskan.

Penonaktifan staf mencabut akses tanpa menghapus histori. Penggantian password oleh admin mencabut sesi lama pada request berikutnya; staf dapat login dengan password baru. Perubahan email staf mengharuskan verifikasi alamat baru. Penghapusan owner dan akun yang dirujuk histori klinis ditolak.

Belum tersedia billing/kasir layanan pasien, discovery klinik, multi-cabang, atau Resilient Edge Mode. Konfigurasi mail, worker, scheduler dan Reverb bukan bukti layanan tersebut sudah berjalan.

Acuan: [PROJECT_ANALYSIS.md](PROJECT_ANALYSIS.md), [Development-Plan.md](Development-Plan.md), [PRD.md](PRD.md), [Plan.md](Plan.md). Dokumen lama menyimpan konteks historis; status pengujian terbaru ada pada [qa-audit/FINAL_QA_REPORT.md](qa-audit/FINAL_QA_REPORT.md).

## Menjalankan instalasi lokal baru

Prasyarat: PHP **^8.5** sesuai composer.json, Composer, Node yang sesuai engine Vite terpasang, npm, MySQL, serta extension PHP yang diperlukan. Versi paket dikunci dalam composer.lock dan package-lock.json; gunakan lock file untuk instalasi.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate --no-interaction
```

Buat database lokal dan atur koneksi serta mail pada `.env` sebelum migration. Perintah Copy-Item dan key:generate hanya untuk instalasi baru: pertahankan `.env` dan APP_KEY instalasi yang sudah dipakai.

```powershell
php artisan migrate --no-interaction
php artisan db:seed --class=RoleAndPermissionSeeder --no-interaction
php artisan db:seed --class=SubscriptionPlanSeeder --no-interaction
npm run build
php artisan serve --no-interaction
```

Untuk database yang sudah berisi data, review migration/seeder dahulu dan buat backup. Jangan menjalankan migrate:fresh atau seed demo pada database klinik pengguna.

DemoSeeder hanya untuk database demo terpisah dengan APP_ENV=local. DemoUserSeeder memakai updateOrCreate dan **dapat mengganti password, role, status aktif serta verifikasi akun demo yang sudah ada**. Akun staf normal dibuat melalui menu Tim klinik; password awal ditentukan admin, lalu staf memverifikasi email. Tidak ada pengiriman inbox nyata ketika memakai mailer array/log.

## Pengujian terisolasi

phpunit.xml menunjuk MySQL `clinicos_testing`, bukan database aplikasi. Untuk SQLite memory pada PowerShell, jalankan override berikut dalam proses shell terpisah; tidak perlu mengubah `.env`:

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP ('clinicos-tests-' + [guid]::NewGuid().ToString('N') + '.php')
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/bin/phpunit tests qa-audit/tests/FullSystemAuditTest.php qa-audit/tests/StaffLifecycleAuditTest.php --no-progress --do-not-cache-result
```

Flag extension Windows diperlukan bila SQLite/GD belum aktif secara global; hilangkan flag tersebut jika extension sudah aktif. PHPUnit dipanggil langsung: pada checkout ini penggunaan flag --no-interaction melalui artisan test tidak didukung runner. Pulihkan/hapus override proses sebelum menjalankan perintah aplikasi dalam shell yang sama.

```powershell
node --test tests/notification-realtime.test.mjs tests/partial-workflows.test.mjs
npm run build
$env:CLINICOS_BROWSER_TEST = '1'
node --test tests/browser-partial-workflows.test.mjs tests/browser-p1-regressions.test.mjs qa-audit/tests/browser-audit.test.mjs
```

Browser test memakai Chrome headless nyata melalui DevTools, database demo sementara, dan mailer lokal. Chrome dicari pada path Windows standar atau CHROME_PATH. Tanpa CLINICOS_BROWSER_TEST=1, test browser berstatus SKIP. Test render PHP tidak menggantikan E2E. SQLite tidak membuktikan locking/concurrency MySQL; delivery SMTP/Reverb/DOKU dan konfigurasi produksi perlu diuji terpisah.

## Lisensi

Kerangka Laravel menggunakan [lisensi MIT](https://opensource.org/licenses/MIT).
