# ClinicOS

Aplikasi manajemen klinik berbasis Laravel, Blade, Livewire, Tailwind CSS, dan MySQL.

## Status pengembangan

M1 — Foundation sudah diimplementasikan: authentication, email verification, dashboard empat role, pembatasan akun nonaktif, registrasi Patient, matriks permission, dan audit login/logout. Dashboard masih halaman awal; modul klinik dan statistik belum tersedia.

Acuan pengembangan: [PRD.md](PRD.md), [Plan.md](Plan.md), dan [Development-Plan.md](Development-Plan.md).

## Menjalankan aplikasi lokal

Prasyarat: PHP sesuai composer.json yaitu versi ^8.3, Composer, Node/NPM, MySQL, serta extension PHP untuk dependency aplikasi dan test database. Gunakan konfigurasi `.env` lokal dan database sendiri; jangan menggunakan database production untuk test.

1.Install paket PHP dan Javascript
```sh
composer install
npm install
```
2.Buat file konfigurasi data penting
```sh
cp .env.example .env
php artisan key:generate
```
3.Buat database pada MySQL sesuai dengan DB_DATABASE yang ada di file .env yaitu `clinicos`

4.Membuat struktur tabel di database
```sh
php artisan migrate --seed
```
4.Menjalankan projek
```sh
npm run build
php artisan serve
```


Buat `.env` dari `.env.example` jika belum tersedia dan isi konfigurasi MySQL sebelum menjalankan migration. Jalankan `key:generate` hanya pada instalasi baru; jangan mengganti APP_KEY aplikasi yang sudah digunakan.

Untuk database lokal yang sudah ada, gunakan migration bertahap dan sinkronisasi role:

```sh
php artisan migrate
php artisan db:seed --class=RoleAndPermissionSeeder
```

Akun demo dibuat melalui DemoUserSeeder hanya pada environment `local`. Seeder tidak mengganti password atau status verifikasi akun yang sudah ada. Pengguna baru perlu memverifikasi email sebelum dashboard; bila mail driver memakai `log`, tautan ada di `storage/logs/laravel.log`.

Untuk login dengan role Admin, bisa menggunakan akun    `admin@clinicos.test` dengan password `password`

## Pengujian

```sh
composer test
npm run build
```

PHP CLI perlu driver SQLite untuk konfigurasi phpunit.xml. Pada PHP Windows lokal yang belum mengaktifkannya secara global, command berikut telah diverifikasi:

```sh
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --no-progress
```

Flags pada proses `php artisan test` tidak diteruskan ke child test runner. Jalankan PHPUnit langsung seperti di atas bila menggunakan extension sementara. Uji integrasi MySQL untuk konkurensi booking/antrean akan ditambahkan bersama modul terkait.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
