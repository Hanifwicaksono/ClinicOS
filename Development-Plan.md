# ClinicOS — Rencana Login Terpisah dan Subdomain Klinik

Tanggal: 27 Oktober 2026
Status: Implementasi aplikasi lokal selesai; konfigurasi domain produksi dan verifikasi browser belum dijalankan

## Tujuan dan keputusan

- Domain utama ditentukan oleh konfigurasi lingkungan produksi, misalnya `clinicos.com` atau `clinicos.id`; akhiran `.com` tidak wajib.
- `{domain-utama}/login` menjadi pintu masuk pasien. Login Google yang sudah ada tetap khusus pasien.
- `{domain-utama}/admin` menjadi pintu masuk pemilik klinik dan staf. Platform Admin juga masuk dari pintu ini lalu diarahkan ke `/platform`; ia bukan tenant.
- Setiap klinik memakai **satu alamat publik bawaan**: `{slug}.{domain-utama}`, misalnya `kliniksehat.clinicos.id` bila domain utamanya `clinicos.id`. Domain milik klinik yang berbeda dari domain utama tidak termasuk rencana ini.
- Satu klinik tetap satu tenant berdasarkan `clinic_id`. Subdomain memilih klinik untuk halaman publik; pemeriksaan kepemilikan dan policy tetap wajib.
- Pemilik klinik tetap `account_type = clinic_owner`, role `Clinic Admin`, dan relasi `clinics.owner_user_id`. Tidak ada role baru.

## Kondisi checkout

- `routes/auth.php` memiliki satu route login Volt di `/login`. Halaman dan `LoginForm` saat ini menerima kredensial semua role; callback Google sudah membatasi akun pasien.
- `routes/web.php` melayani profil, antrean publik, dan booking di `/clinic/{clinic:slug}`. Belum ada resolver subdomain.
- `clinics.slug` sudah tersedia. Isolasi data klinik memakai query, policy, dan relasi yang divalidasi server-side.
- Checkout mempunyai perubahan aktif pada auth, booking, dan dokumen ini. Sebelum implementasi, baca ulang status/diff Git dan lanjutkan dari perubahan terkini.

## Perilaku URL

| URL | Perilaku |
| --- | --- |
| `{domain-utama}/` | Landing page ClinicOS |
| `{domain-utama}/login` | Login pasien, registrasi pasien, dan Google |
| `{domain-utama}/admin` | Login pemilik klinik, Clinic Admin, Receptionist, Doctor, dan Platform Admin |
| `{slug}.{domain-utama}/` | Profil publik klinik sesuai slug |
| `{slug}.{domain-utama}/queue` | Antrean publik klinik yang sama |
| `{domain-utama}/clinic/{slug}/booking` | Booking pasien setelah login terpusat |
| Area `/admin/*`, `/doctor/*`, `/receptionist/*`, `/patient/*`, `/platform/*` | Tetap pada domain utama |

Tautan “Booking” pada subdomain menuju URL booking di domain utama dengan slug klinik yang sama. Pasien kembali ke booking klinik tersebut setelah login melalui intended URL yang tervalidasi. Sesi login tetap pada domain utama; tidak perlu berbagi cookie dengan semua subdomain.

Gunakan hanya URL publik baru `{slug}.{domain-utama}/` dan `{slug}.{domain-utama}/queue`; URL publik lama `/clinic/{slug}` dan `/clinic/{slug}/queue` tidak perlu dipertahankan atau dialihkan. Link booking dan status booking yang sudah ada tetap berfungsi.

## Tahap implementasi

### 1. Pisahkan pintu masuk akun

1. Tambahkan route guest `GET /admin` tanpa mengganggu `/admin/*` yang sudah ada. Gunakan form autentikasi yang sudah ada; sesuaikan judul, tautan registrasi, dan tombol Google untuk tiap pintu masuk.
2. Pada `/login`, terima hanya akun aktif dengan account type dan role pasien. Pada `/admin`, terima akun aktif pengelola klinik dan Platform Admin. Akun dari kelompok yang salah mendapat pesan login umum dan tidak memperoleh sesi.
3. Pertahankan rate limiting, regenerasi sesi, audit login/logout, verifikasi email, dan redirect dashboard sesuai role. Intended URL harus tetap pada host tepercaya dan sesuai otorisasi.
4. Perbarui tautan landing ClinicOS, halaman klinik, registrasi, reset kata sandi, dan redirect sesi akun nonaktif. Tautan pasien menuju `/login`; tautan pengelola menuju `/admin`. Alur reset kata sandi bersama boleh tetap dipakai.

### 2. Tambahkan subdomain klinik

1. Gunakan `clinics.slug` untuk mencocokkan host `{slug}.{domain-utama}` ke satu klinik aktif. Tolak host tak dikenal, slug cadangan seperti `www`, dan klinik tidak aktif; jangan jatuh ke data klinik lain.
2. Tempatkan route subdomain sebelum route domain utama yang berpotensi bertabrakan. Profil dan antrean publik hanya mengambil data dari klinik hasil resolusi host. Jangan menerima `clinic_id` dari browser sebagai bukti tenant.
3. Perbarui URL dan tautan profil/antrean publik agar memakai subdomain. Booking dan halaman status pasien tetap memakai domain utama. Pastikan email/notifikasi tidak menghasilkan host yang keliru.
4. Pertahankan batas data antrean publik tanpa identitas atau isi medis. Policy medis, subscription, dan workflow booking tetap berlaku.

### 3. Siapkan domain produksi

1. Pilih domain utama yang dimiliki/dikuasai untuk produksi. Konfigurasikan domain tersebut dan wildcard `*.{domain-utama}` pada DNS, web server/proxy, dan sertifikat HTTPS sebelum mengaktifkan tautan subdomain.
2. Setel `APP_URL`, pola host subdomain, dan host tepercaya sesuai domain tiap lingkungan. Jangan mengaktifkan cookie sesi untuk semua subdomain; sesi login tetap pada domain utama.
3. Audit slug klinik yang sudah ada terhadap nama host cadangan. Uji tautan booking dan status booking yang pernah dibagikan.
4. Aktifkan secara bertahap setelah DNS/HTTPS dan verifikasi operasional siap. Deployment serta perubahan DNS dilakukan terpisah dari edit kode lokal.

## Verifikasi dan syarat selesai

- `/login` menerima pasien dan menolak pengelola; `/admin` menerima pengelola/Platform Admin dan menolak pasien. Login gagal tidak menghasilkan sesi, dan rate limit tetap bekerja.
- Login Google pasien, registrasi pasien/pemilik, reset kata sandi, verifikasi email, dan redirect setelah login tetap bekerja.
- `klinik-a.{domain-utama}` hanya menampilkan klinik A. Host tak dikenal, slug cadangan, dan klinik nonaktif tidak menampilkan klinik lain. Tautan booking dari A selalu menuju booking A.
- Booking, status booking lama, dan antrean publik tetap bekerja. Akses tenant dan medis lintas klinik tetap ditolak.
- Jalankan test feature terfokus memakai SQLite in-memory dengan environment proses eksplisit. Uji browser desktop/mobile dan DNS/HTTPS dilakukan terpisah di lingkungan yang memiliki wildcard domain.
- Jika file PHP berubah, jalankan `vendor/bin/pint --dirty --format agent`. Jika frontend berubah, jalankan `npm run build`.

### Hasil implementasi lokal — 27 Oktober 2026

- Route login pasien `/login` dan pengelola `/admin` memakai form yang sama dengan pembatasan role sebelum sesi dibuat. Google tetap hanya tampil pada login pasien; reset kata sandi dan sesi akun nonaktif kembali ke pintu masuk yang sesuai.
- Profil dan antrean publik memakai host `{slug}.{domain-utama}`. Booking serta area aplikasi tetap pada host utama. Slug baru dibatasi menjadi label DNS kecil yang valid dan nama subdomain cadangan ditolak.
- Tes terfokus login, Google, booking, klinik publik, antrean, onboarding, dan proxy lulus pada SQLite in-memory. Pint dan `npm run build` selesai.
- Suite penuh sebelum penyesuaian tes proxy: 262 tes, 257 lulus, 3 gagal, 2 error. Tes proxy kemudian lulus saat dijalankan ulang. Kegagalan/error yang tersisa adalah `ManagementDashboardTest`, `SecurityHeadersTest`, dan dua `NotificationCenterTest` pada SQLite; belum diperbaiki dalam pekerjaan domain ini.
- DNS wildcard, HTTPS, domain produksi yang dipilih, dan uji browser belum dapat diverifikasi dari checkout lokal. Jika `APP_URL` lokal memakai alamat IP, URL klinik memakai `{slug}.localhost`; produksi harus memakai nama host domain utama yang dipilih.

## Di luar cakupan

- Domain milik klinik, misalnya `kliniksehat.com`, termasuk verifikasi DNS dan sertifikatnya.
- Login atau dashboard pada subdomain klinik, berbagi sesi antarsubdomain, dan multi-cabang.
- Perubahan stack, role, data klinis, aturan booking, atau paket subscription.
