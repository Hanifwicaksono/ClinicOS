# ClinicOS — Development Plan Akun Pasien, Google OAuth, dan Resep

Tanggal: 22 Oktober 2026

Diperbarui: 25 Oktober 2026

Status: M1–M3 dan verifikasi lokal M4 selesai; deployment production belum dijalankan

Cakupan: booking pasien wajib memakai akun, login Google untuk pasien, akses dashboard pasien dari landing klinik, dan resep pasien setelah visit

## 1. Tujuan

Mengubah alur pasien agar:

1. pasien wajib login sebelum membuat booking dari landing klinik;
2. pasien dapat membuat akun dengan email/kata sandi atau masuk memakai Google;
3. setelah autentikasi, pasien kembali ke halaman booking klinik yang sebelumnya dipilih;
4. pasien dapat membuka dashboard-nya langsung dari landing klinik; dan
5. pasien dapat melihat resep miliknya setelah visit selesai tanpa memperoleh akses ke catatan medis internal dokter; dan
6. setiap akun baru wajib menyetujui syarat dan ketentuan serta kebijakan privasi.

## 2. Kondisi aplikasi saat ini

- Registrasi pasien email/kata sandi, role `Patient`, verifikasi email, dan dashboard pasien sudah tersedia.
- Booking publik masih dapat dibuat guest. `BookingService` sebenarnya sudah dapat memakai user pasien yang login dan membuat satu profil `Patient` per klinik.
- `users.email` sudah unik secara global. Satu `User` dapat mempunyai beberapa profil `Patient`, masing-masing satu per klinik melalui constraint unik `patients(clinic_id, user_id)`.
- Landing klinik sudah mempunyai tombol booking, tetapi belum mempunyai pintasan masuk/dashboard pasien.
- Resep tersimpan melalui `MedicalRecord -> Prescription -> PrescriptionItem`. Visit selesai memakai status `COMPLETED` dan rekam medis final memakai status `FINAL`.
- `laravel/socialite` 5.31.0 sudah terpasang pada M1 untuk integrasi Google OAuth.

## 3. Keputusan produk dan keamanan

### 3.1 Akun dan booking

- Hanya user aktif dengan role dan `account_type` pasien yang dapat membuka serta mengirim form booking publik.
- Guest yang menekan tombol booking diarahkan ke login, lalu dikembalikan ke booking klinik yang sama setelah autentikasi.
- Registrasi email/kata sandi tetap tersedia dan memakai verifikasi email yang sudah ada.
- Google OAuth menjadi pilihan login tambahan, bukan pengganti login email/kata sandi.
- Booking internal oleh resepsionis tetap berjalan seperti sekarang dan tidak dipaksa memakai akun pasien.
- Halaman status/cancel berbasis token untuk booking lama tetap dipertahankan agar link yang sudah dibagikan tidak rusak. Booking baru tetap dapat dikelola dari area pasien.

### 3.2 Google OAuth

- Gunakan paket resmi `laravel/socialite` dan Google OAuth Authorization Code flow dengan state validation bawaan.
- Tambahkan `users.google_id` nullable dan unique. Tidak dibuat tabel provider generik karena hanya Google yang diminta.
- Akun baru dari Google dibuat sebagai `UserAccountType::Patient`, diberi role `Patient`, langsung dianggap terverifikasi karena email berasal dari Google yang terverifikasi, dan memakai password acak yang tidak dapat ditebak.
- Jika email Google cocok dengan akun pasien lama, tautkan `google_id` ke akun tersebut setelah memastikan ID Google belum dipakai akun lain.
- Jika email cocok dengan akun klinik/staf, akun nonaktif, email Google tidak tersedia/tidak terverifikasi, atau `google_id` bertabrakan, tolak login dengan pesan aman; jangan mengubah role atau mengambil alih akun.
- Setelah callback berhasil, regenerasi session dan gunakan intended URL agar pasien kembali ke booking yang dipilih atau ke dashboard.
- Akun Google baru hanya dibuat bila persetujuan syarat dikirim dari halaman registrasi; login akun Google yang sudah ada tidak meminta persetujuan ulang.
- Catat login melalui event autentikasi yang sudah digunakan aplikasi; jangan membuat mekanisme audit kedua.

### 3.3 Profil pasien per klinik

- Form booking pasien tidak meminta atau mengirim NIK.
- Nama lengkap, tanggal lahir, jenis kelamin, nomor telepon, email, dan alamat wajib diisi pasien. Catatan booking tetap opsional karena bukan data diri.
- Pada booking pertama di suatu klinik, data diri wajib dari form membuat profil `Patient` dan menautkannya ke user login; kolom `patients.nik` dibiarkan `null`.
- Pada booking berikutnya, gunakan profil pasien yang sudah tertaut pada klinik tersebut dan tampilkan datanya sebagai nilai awal form.
- Jangan menautkan otomatis profil pasien lama yang belum memiliki `user_id` hanya berdasarkan nama, email, NIK, atau nomor telepon. Kesalahan tautan dapat mengekspos resep dan data medis orang lain.
- NIK historis tidak dihapus dari database. Form pengelolaan pasien internal milik resepsionis tidak diubah dalam cakupan ini.
- Migrasi/claim profil pasien lama di luar cakupan ini; data tersebut tetap aman dan link status booking lama tetap berfungsi.

### 3.4 Akses resep

- Dashboard hanya menampilkan resep yang terhubung ke profil `Patient` milik user login.
- Resep baru terlihat bila visit berstatus `COMPLETED` dan rekam medis berstatus `FINAL`.
- Tampilkan klinik, dokter, tanggal visit/finalisasi, catatan resep, serta item obat: nama, dosis, frekuensi, jumlah, satuan, dan instruksi.
- Resep draft, resep pasien lain, SOAP, diagnosis, pemeriksaan, foto, dan catatan internal dokter tidak boleh tampil.
- Koreksi dokter pada rekam medis final otomatis terlihat pada tampilan berikutnya karena dashboard membaca data resep aktif, bukan membuat salinan.

## 4. Area perubahan

### Dependency dan konfigurasi

- `composer.json` / `composer.lock`: tambahkan versi `laravel/socialite` yang kompatibel dengan Laravel 13 setelah dependency disetujui.
- `.env.example`: tambahkan `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, dan `GOOGLE_REDIRECT_URI` tanpa nilai rahasia.
- `config/services.php`: tambahkan konfigurasi Google.
- Google Cloud Console/deployment: daftarkan redirect URI untuk setiap environment; kredensial tidak masuk repository.

### Database dan model

- Migration additive baru: kolom `google_id` nullable unique pada `users`; tidak mengubah atau menghapus data user/pasien lama.
- Migration additive baru: `terms_accepted_at` nullable untuk menyimpan waktu persetujuan akun baru tanpa memblokir akun lama.
- `app/Models/User.php`: izinkan atribut Google dan timestamp persetujuan yang diperlukan serta pertahankan relasi `patientProfiles` yang sudah ada.
- `database/factories/UserFactory.php`: state Google hanya bila test membutuhkannya; jangan mengubah default factory.

### Auth dan navigasi

- Tambahkan controller Google redirect/callback di `app/Http/Controllers/Auth`.
- Tambahkan dua route guest bernama untuk redirect dan callback di `routes/auth.php`, dengan throttle yang sesuai pada callback.
- Tambahkan tombol “Lanjutkan dengan Google” pada halaman login dan registrasi pasien.
- Wajibkan checkbox persetujuan pada registrasi pasien, Google, dan pemilik klinik; simpan timestamp persetujuannya.
- Ubah registrasi pasien agar memakai intended redirect, bukan selalu berhenti di dashboard.
- Ubah `resources/views/public/clinic.blade.php`:
  - guest melihat aksi masuk/daftar untuk booking;
  - pasien login melihat “Buat booking” dan “Dashboard pasien”;
  - role non-pasien tidak diberi aksi booking pasien.

### Booking pasien

- Lindungi route create/store booking publik dengan `auth`, `verified`, role pasien, dan middleware subscription yang sudah ada.
- Perketat `StorePublicBookingRequest::authorize()` agar request hanya berasal dari pasien aktif yang berhak membuat appointment.
- Hapus rule dan input `nik` dari booking publik; ubah `email` serta `address` menjadi wajib, sementara `name`, `birth_date`, `gender`, dan `phone` tetap wajib.
- `PublicBookingController::create()` memuat profil pasien untuk klinik aktif agar form dapat diprefill.
- `PublicBookingController::store()` selalu mengirim user login sebagai actor ke `BookingService`.
- Batasi data profil yang diteruskan `BookingService` pada field wajib tersebut sehingga booking pasien baru tidak mengisi NIK.
- Pertahankan transaksi, idempotency key, validasi jadwal/layanan, kuota, audit, dan notifikasi yang sudah ada.
- Hapus asumsi/copy UI bahwa akun pasien bersifat opsional.

### Dashboard dan resep

- Perluas `Patient\DashboardController` dengan query resep yang dibatasi oleh `patients.user_id = auth()->id()`, visit selesai, dan rekam medis final.
- Eager-load hanya relasi yang ditampilkan (`items`, klinik, dokter, visit) dan gunakan pagination agar dashboard tidak memuat seluruh riwayat sekaligus.
- Perbarui dashboard pasien untuk menampilkan kartu/daftar resep lengkap beserta empty state.
- Jangan menambahkan endpoint rekam medis umum. Query pasien hanya mengeluarkan data resep yang dibutuhkan.
- Tambahkan tautan dashboard pasien pada navigasi/landing tanpa mengubah dashboard role lain.

## 5. Tahapan implementasi

### M1 — Google OAuth pasien

- [x] Minta persetujuan dan pasang `laravel/socialite` versi kompatibel.
- [x] Tambahkan migration `google_id`, konfigurasi Google, dan env example.
- [x] Implementasikan redirect/callback, pembuatan akun pasien, linking akun pasien lama, intended redirect, serta penanganan kegagalan.
- [x] Tambahkan tombol Google pada login dan registrasi pasien.
- [x] Pastikan login password, reset password, verifikasi email, role lain, dan audit autentikasi tetap berfungsi.

Kriteria selesai: pasien baru/lama dapat login dengan Google; akun staf/owner tidak berubah role; callback gagal tidak membuat akun parsial.

Hasil M1: Socialite 5.31.0 terpasang, 38 test auth / 211 assertion lulus, build Vite lulus, dan verifikasi OAuth nyata dijadwalkan pada M4 setelah kredensial Google tersedia. Constraint PHP Composer dikoreksi dari `^8.5` menjadi `^8.3` agar sesuai dengan runtime dan aturan proyek.

### M2 — Booking wajib akun

- [x] Pasang middleware autentikasi/verifikasi/role pada create dan store booking publik.
- [x] Bawa intended URL melalui login, registrasi, verifikasi email, dan callback Google.
- [x] Hapus NIK dari form/validasi booking pasien dan wajibkan nama, tanggal lahir, jenis kelamin, nomor telepon, email, serta alamat.
- [x] Prefill profil pasien yang sudah terhubung dan buat profil baru hanya untuk booking pertama di klinik tersebut.
- [x] Perbarui CTA landing klinik dan copy form booking.
- [x] Pertahankan status/cancel token untuk booking lama.
- [x] Wajibkan persetujuan syarat pada seluruh registrasi dan simpan `terms_accepted_at`.

Kriteria selesai: guest tidak dapat membuka/mengirim booking; form pasien tidak memuat NIK dan menolak data diri wajib yang kosong; pasien login membuat appointment, queue, dan profil tertaut secara atomik; resepsionis tetap dapat membuat booking internal.

Hasil M2: booking publik wajib akun pasien terverifikasi, profil pasien diprefill/diperbarui tanpa mengubah NIK historis, landing klinik memiliki CTA login/dashboard, link status/cancel lama dipertahankan, migration lokal selesai, 69 test / 424 assertion lulus, dan build Vite lulus.

### M3 — Resep di dashboard pasien

- [x] Tambahkan query resep terotorisasi dan terpaginasikan pada dashboard pasien.
- [x] Tampilkan informasi resep dan empty state tanpa membuka isi rekam medis lain.
- [x] Pastikan draft/in-progress dan data pasien lain tidak pernah ikut dalam response.
- [x] Pastikan koreksi resep final tampil tanpa menduplikasi data.

Kriteria selesai: pasien hanya melihat resep sendiri setelah visit selesai dan tidak dapat melihat resep pasien lain melalui URL/request langsung.

Hasil M3: dashboard pasien menampilkan resep aktif dari seluruh profil klinik milik user, hanya untuk visit `COMPLETED` dan rekam medis `FINAL`. Query hanya mengambil data resep yang ditampilkan, dipaginasi 10 item, tanpa endpoint rekam medis baru. Sebanyak 30 test dashboard / 75 assertion lulus dan build Vite lulus.

### M4 — Verifikasi dan rilis

- [x] Jalankan formatter PHP: `vendor/bin/pint --dirty --format agent`.
- [x] Jalankan test feature yang relevan dengan `php artisan test --compact`.
- [x] Jalankan `npm run build` karena halaman auth, landing, booking, dan dashboard berubah.
- [x] Verifikasi manual OAuth di environment yang memiliki kredensial dan redirect URI Google.
- [ ] Deploy migration additive terlebih dahulu, lalu konfigurasi env dan kode aplikasi; tidak menjalankan `migrate:fresh` atau menghapus data.

Kriteria selesai: test dan build lulus, OAuth nyata berhasil pada origin deployment, dan booking/resep lama tidak hilang.

Hasil verifikasi lokal M4: Laravel Pint, Composer validation, Vite production build, `git diff --check`, dua route Google, tiga konfigurasi Google, dan seluruh 35 migration lulus. Full regression menjalankan 207 test / 1.176 assertion tanpa kegagalan. OAuth nyata berhasil melewati Google dan callback: akun pasien aktif dibuat dengan `google_id`, persetujuan syarat tersimpan, role `Patient` terpasang, event `auth.login` tercatat, dan session database dibuat. Deployment production belum dijalankan.

## 6. Test minimum

### Google OAuth

- Akun Google baru membuat satu user pasien terverifikasi dan role `Patient`.
- Email Google yang cocok menautkan akun pasien lama tanpa membuat duplikat.
- Akun staf/owner, user nonaktif, email tidak terverifikasi, callback error, dan konflik `google_id` ditolak.
- Callback berhasil meregenerasi session, memicu audit login, dan kembali ke intended URL.
- Akun Google baru ditolak tanpa persetujuan syarat; akun lama tetap dapat login tanpa persetujuan ulang.

### Booking

- Guest pada create/store booking diarahkan ke login dan tidak membuat data.
- Pasien belum terverifikasi diarahkan ke verifikasi email.
- Form booking pasien tidak menampilkan/mengirim NIK dan menolak nama, tanggal lahir, jenis kelamin, nomor telepon, email, atau alamat yang kosong.
- Booking pasien baru menyimpan `patients.nik = null` tanpa menghapus NIK historis.
- Pasien login membuat atau memakai profil di klinik yang benar; profil klinik lain tidak pernah dipakai.
- Re-submit idempotent tidak menggandakan patient, appointment, atau queue.
- Booking internal resepsionis dan link status/cancel lama tetap lulus.
- Registrasi pasien dan pemilik klinik ditolak tanpa checkbox persetujuan; registrasi berhasil menyimpan `terms_accepted_at`.

### Resep

- Dashboard pasien menampilkan item resep dari visit selesai dan rekam medis final miliknya.
- Resep draft/in-progress tidak tampil.
- Resep user/pasien lain dan klinik lain tidak tampil.
- Dashboard menangani pasien tanpa profil/resep serta resep tanpa item tetapi memiliki catatan.
- Koreksi resep final tercermin pada dashboard.

## 7. Risiko dan mitigasi

- **Kebocoran data medis:** seluruh query resep dimulai dari user login menuju profil pasien miliknya dan difilter final/completed; test lintas user/klinik wajib ada.
- **Pengambilalihan akun:** Google hanya boleh menautkan email terverifikasi ke akun bertipe pasien; konflik provider atau tipe akun ditolak.
- **Duplikasi pasien:** constraint `(clinic_id, user_id)` dipertahankan dan pembuatan profil tetap di dalam transaksi booking.
- **Redirect OAuth salah:** setiap environment harus mempunyai callback URI yang sama persis dengan Google Console dan `GOOGLE_REDIRECT_URI`.
- **Booking lama rusak:** endpoint status/cancel token dan data guest historis tidak dihapus.
- **Dependency baru:** implementasi tidak dimulai sebelum versi Socialite diperiksa dan perubahan dependency disetujui.

## 8. Di luar cakupan

- Login provider selain Google.
- Penggabungan atau claim otomatis profil pasien historis yang belum tertaut user.
- Menghapus kolom atau nilai NIK historis dari database dan mengubah form pasien internal milik resepsionis.
- Mengubah dashboard atau metode login dokter, resepsionis, Clinic Admin, dan Platform Admin.
- Menampilkan SOAP, diagnosis, foto, catatan dokter, atau rekam medis lengkap kepada pasien.
- Unduh/cetak PDF resep, notifikasi resep baru, dan pengiriman resep lewat email/WhatsApp.
