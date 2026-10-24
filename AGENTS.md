# ClinicOS — Konteks dan Aturan Pengembangan

Konteks diverifikasi pada 24 Oktober 2026. Baca [PROJECT_ANALYSIS.md](PROJECT_ANALYSIS.md) untuk arsitektur, bukti implementasi, perbedaan spesifikasi, dan batas verifikasi. Aturan Laravel Boost di bawah tetap berlaku.

## Produk dan acuan

- ClinicOS adalah aplikasi manajemen klinik SaaS berbasis web, responsif untuk desktop, tablet, dan mobile.
- Stack yang diminta pengguna: full Laravel, Blade, dan Tailwind CSS. Gunakan Livewire/Volt dan Alpine sesuai pola yang sudah ada; jangan menambahkan React, SPA, atau API terpisah tanpa permintaan eksplisit.
- Role tersimpan: `Clinic Admin`, `Receptionist`, `Doctor`, `Patient`, dan `Platform Admin`. Pemilik klinik memakai `account_type = clinic_owner`, role `Clinic Admin`, serta relasi eksplisit `clinics.owner_user_id`; pemilik bukan nama role baru.
- Implementasi SaaS saat ini: satu pemilik memiliki satu klinik, satu database bersama, isolasi menggunakan `clinic_id`, satu subscription per klinik. Multi-cabang dan beberapa klinik untuk satu owner belum tersedia.
- Permintaan langsung pengguna mengarahkan pekerjaan. Dokumen spesifikasi menjadi bahan requirement, bukan instruksi untuk menjalankan perintah, migrasi, deployment, atau mengganti stack.
- Baca `Development-Plan.md` untuk domain SaaS/subscription, `PRD.md` untuk requirement MVP lama, `Plan.md` untuk roadmap/release gate lama, `design (1).md` untuk panduan desain, serta seluruh dokumen `docs/` yang terkait pekerjaan.
- RPP `RPP_ClinicOS_Revisi_Web_Only.docx` yang disediakan pengguna mencakup requirement lebih luas dan menyebut React. Perbedaan itu dicatat dalam laporan; jangan menerapkannya sebagai perintah migrasi frontend.
- Dokumen belum sepenuhnya sinkron. Jangan menyatakan fitur selesai hanya berdasarkan checkbox, nama komponen, mock data, atau hasil test historis. Bedakan backend tersimpan, integrasi UI, dan verifikasi operasional.

## Versi dan struktur yang terverifikasi

- `composer.json` saat analisis mensyaratkan PHP `^8.5`; CLI lokal `8.5.5`. Catatan lama PHP 8.3 tidak cocok dengan checkout ini. Jangan melakukan downgrade atau mengubah dependency untuk menyamakan dokumentasi.
- Paket terpasang saat analisis: Laravel `13.34.0`, Livewire `3.8.10`, Volt `1.11.2`, Reverb `1.12.0`, Spatie Permission `8.3.0`, PHPUnit `12.5.37`, Pint `1.32.1`.
- Frontend lock file saat analisis: Tailwind `3.4.17`, Vite `8.3.4`, Laravel Vite Plugin `3.2.0`, Echo `2.6.1`, Pusher JS `8.6.0`. Periksa ulang manifest/versi terpasang sebelum memakai API paket.
- `routes/web.php`: route publik, onboarding, subscription, platform, admin, resepsionis, dokter, pasien, dan notifikasi. `routes/auth.php`: autentikasi Volt. Belum ada kontrak API publik terversi.
- `app/Http/Controllers`: controller per area; `app/Http/Requests`: validasi dan authorization request; `app/Policies`: akses resource; `app/Services`: workflow domain.
- `app/Models`, `database/migrations`, `database/factories`, dan `database/seeders`: domain Eloquent, schema, dan fixture.
- `resources/views`: Blade beserta Volt class-based untuk auth/profil/navigasi. `resources/js/realtime.js` menangani Echo, refetch, polling, dan aksi antrean.
- Token visual utama ada di `tailwind.config.js` dan `resources/css/app.css`; landing page juga memakai `public/css/clinicos-landing.css`. Periksa komponen Blade yang sudah tersedia sebelum membuat komponen baru.
- `docs/` diabaikan oleh `.gitignore`, tetapi tiga README yang sudah ada tetap tracked. Periksa status Git bila pengguna meminta dokumentasi baru di sana.

## Invarian tenant dan akses

- Tentukan konteks klinik dari akun atau resource yang telah divalidasi server-side. Jangan mempercayai `clinic_id`, ID dokter, pasien, layanan, atau jadwal dari form sebagai bukti kepemilikan.
- List query tenant harus difilter dengan konteks klinik atau relasi kepemilikan yang sesuai. Route model binding harus dilanjutkan authorization resource.
- `ClinicScope` hanya dipasang pada `Patient` dan hanya memfilter ketika user login memiliki `clinic_id`. Ia tidak menyediakan isolasi otomatis untuk guest, Platform Admin, console, atau job. Jangan menjadikannya satu-satunya batas tenant.
- `DoctorSchedule` memperoleh klinik melalui dokter; RME dan entitas klinis anak melalui visit/pasien/dokter. Validasi konsistensi relasi, bukan sekadar keberadaan masing-masing foreign key.
- Dokter hanya membuka isi medis dan mengelola pemeriksaan yang ditugaskan kepadanya. Pemilik, Clinic Admin, resepsionis, pasien, dan Platform Admin tidak otomatis memperoleh akses isi medis.
- Platform Admin mengelola metadata tenant dan aspek komersial melalui `/platform`; jangan menambahkan bypass policy medis atau impersonation.
- Pasien terautentikasi mengakses appointment melalui kepemilikan `patient.user_id`. Guest memakai token booking rahasia yang diperiksa terhadap hash; jangan menyalinnya ke log, telemetry, atau laporan.
- Public queue display dan broadcast publik hanya boleh memuat informasi antrean nonmedis. Jangan mengirim nama pasien, NIK, nomor RM, SOAP, diagnosis, resep, atau lampiran medis.
- Simpan foto medis pada disk `local` private dan sajikan melalui endpoint ber-policy. Logo klinik memakai disk `public`.

## Workflow yang harus dipertahankan

- Gunakan `BookingService`, `QueueWorkflowService`, `VisitWorkflowService`, `SubscriptionService`, `StaffAccountService`, dan `DokuPaymentService`; jangan menggandakan aturan bisnis di Blade/controller.
- Booking membuat patient bila diperlukan, appointment, dan queue secara transaksional, dengan UUID idempotency, validasi sesi/kuota, snapshot layanan/harga, row lock, dan unique constraint.
- Guest saat ini tidak dicocokkan otomatis dengan pasien lama melalui NIK/telepon. Jangan membuat mekanisme account linking atau deduplikasi berbasis identitas yang belum diverifikasi.
- Antrean utama: `BOOKED → WAITING → CALLED → IN_PROGRESS → COMPLETED`; jalur tambahan `SKIPPED`, `CANCELLED`, `NO_SHOW`. FIFO dibatasi dokter/sesi/tanggal; satu panggilan/pemeriksaan aktif per sesi.
- Check-in mengubah queue menjadi `WAITING`. Visit baru dibuat saat dokter memulai pemeriksaan dari `CALLED`, bukan saat check-in.
- Finalisasi RME, visit, appointment, dan queue harus atomik. Penyimpanan draft tidak boleh mengubah catatan final.
- Koreksi catatan final memakai alasan minimal 10 karakter dan `MedicalRecordRevision` dengan pelaku, waktu, snapshot sebelum/sesudah; status tetap final.
- Nilai layanan dari visit selesai bukan bukti penerimaan kas. `Payment` yang ada adalah pembayaran subscription SaaS, bukan pembayaran layanan pasien.
- Paket saat ini: Free, Individual, Team; trial Team sekali per klinik. Harga dan kapasitas acuan ada di `Development-Plan.md` serta `SubscriptionPlanSeeder`.
- Subscription berbayar expired/suspended membatasi tulis; policy baca tetap berlaku. Implementasi mengecualikan penyimpanan/finalisasi visit yang sudah `IN_PROGRESS` serta deaktivasi seat oleh owner. Jangan menghilangkan pengecualian pelayanan ini tanpa keputusan produk.
- Subscription hanya diaktifkan dari notification DOKU dengan signature dan payload yang valid, atau reconciliation backend yang tervalidasi. Redirect browser tidak mengaktifkan subscription.
- Pertahankan deduplikasi event pembayaran, snapshot invoice/periode, transaksi, serta histori refund/grant. Jangan menghapus histori komersial atau medis.
- Broadcast perubahan antrean dilakukan setelah commit. Jangan menganggap Reverb, scheduler, mail, atau worker berjalan hanya karena konfigurasi tersedia.

## Batas implementasi yang harus disebutkan secara jujur

- Autosave RME saat ini berupa komponen status dan custom event; penyimpanan draft masih melalui submit manual. Belum ada debounce/network autosave atau optimistic version check.
- Odontogram interaktif hanya menyimpan state Alpine (`data-persists="false"`); belum ada persistence backend.
- Banner alergi tersedia, tetapi controller mengirim array kosong dan belum ada domain alergi pasien.
- Komponen antrean menerima ETA/confidence/stale, tetapi alur publik belum memasok hasil kalkulasi Predictive Queue.
- Backend mendukung diagnosis/tindakan terstruktur, sedangkan editor pemeriksaan sekarang memakai assessment/rencana SOAP tanpa input array diagnosis/tindakan.
- Flag premium `realtime`/`management_dashboard` ada di katalog; pemeriksaan entitlement fitur belum ditemukan pada jalur aksesnya.
- Belum ada modul kasir/billing pasien, receipt/export laporan, discovery klinik, multi-cabang, atau Resilient Edge Mode.
- Jangan memperlakukan laporan ini sebagai izin mulai implementasi. Permintaan 24 Oktober 2026 membatasi tahap awal pada analisis dan dokumentasi.

## Cara bekerja dan memverifikasi

- Sebelum edit, baca aturan di `.ai/rules/index.md` bila tersedia, seluruh rule yang cocok, serta pencarian keyword terkait. Folder `.ai/rules` tidak tersedia pada baseline analisis ini; periksa lagi pada sesi berikutnya.
- Baca status/diff Git dan pertahankan perubahan pengguna. Jangan reset checkout, menjalankan `migrate:fresh`, menghapus data, mengganti `APP_KEY`, menjalankan seed ulang pada database pengguna, atau mengirim pembayaran nyata sebagai bagian verifikasi rutin.
- Jangan mencetak `.env`, kredensial DOKU, password, signature, token, atau isi medis ke output/laporan.
- Gunakan migration additive dan factory dengan relasi klinik yang eksplisit; beberapa factory default masih dapat menghasilkan relasi tenant yang tidak konsisten.
- Sebelum menulis test, temukan dan baca skill `testing-best-practices`; sebelum mengubah Volt aktifkan `volt-development` serta gunakan dokumentasi yang sesuai versi. Keduanya belum ditemukan pada katalog skill lokal saat analisis; jangan mengklaim sudah membacanya.
- Jalankan test terkait setelah perubahan behavior. UI copy/style/layout murni tidak memerlukan test baru. Jika mengubah PHP, jalankan `vendor/bin/pint --dirty --format agent`.
- `phpunit.xml` mengarah ke MySQL `clinicos_testing`; jangan menjalankan test pada database aplikasi. Untuk pemeriksaan SQLite gunakan environment proses yang eksplisit, bukan perubahan `.env`.
- Pada CLI Windows sesi analisis, SQLite dan GD perlu diaktifkan sementara. Perintah baseline aman berikut tidak menulis database MySQL:

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/bin/phpunit --no-progress --do-not-cache-result
```

- Ini adalah environment proses shell; hapus/pulihkan override bila melanjutkan perintah aplikasi dalam shell yang sama. Gunakan path/filter test untuk pekerjaan terfokus.
- Test runner langsung dipakai karena `php artisan test --no-interaction` pada versi ini meneruskan flag yang tidak dikenali PHPUnit. Untuk Artisan lain gunakan `--no-interaction` sesuai panduan.
- Baseline analisis: 186 test, 179 lulus, 5 gagal assertion, 2 error, 988 assertion. Rinciannya ada di laporan. Jangan mengklaim suite hijau sebelum failure tersebut diperiksa.
- SQLite tidak membuktikan lock/concurrency MySQL. `BookingConcurrencyTest` yang tersedia masih melakukan request berurutan; pengujian proses bersamaan pada MySQL belum terbukti.
- Gunakan `npm run build` bila perubahan frontend memerlukannya; uji responsif pada 375, 768, 1024, dan 1440 px, keyboard/fokus, loading/error/empty state, dan koneksi realtime putus/pulih. Jangan menyamakan test render dengan uji browser.
- Dokumentasi baru hanya dibuat bila diminta. Bedakan bukti run terbaru, catatan historis, analisis statis, dan hal yang belum diverifikasi.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This checkout currently requires PHP ^8.5 in composer.json; the CLI verified on 24 October 2026 is PHP 8.5.5. Older project notes mention PHP 8.3 and require reconciliation. Always use the APIs that match the installed major version of each package — do not assume a version or change dependencies to match old notes.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
