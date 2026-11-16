# Environment report

Run: 11 November 2026, Windows lokal. Status lingkungan audit: **PASS setelah koreksi isolasi storage**. Kesetaraan dengan deployment produksi: **BLOCKED**.

## Lingkungan yang benar-benar dijalankan

| Komponen | Aktual | Bukti / batas |
| --- | --- | --- |
| PHP / Laravel | PHP 8.5.5 / Laravel 13.34.0 | CLI dan backend HTTP nyata |
| Livewire / Volt | 3.8.10 / 1.11.2 | Source produksi dipakai tanpa perubahan audit |
| Reverb / Permission / PHPUnit | 1.12.0 / 8.3.0 / 12.5.37 | [versi PHP](evidence/composer-versions.json) |
| Frontend | Vite 8.3.4, Tailwind 3.4.17, Echo 2.6.1, Pusher 8.6.0 | [versi JS](evidence/npm-versions.json) |
| Browser | Playwright 1.65.0, Chrome 157.0.8103.50 | [run browser](evidence/playwright-results.json); library di direktori sementara, dependency proyek tidak diubah |
| MySQL | 8.0.30, port 13316, bind 127.0.0.1 | Instance baru dengan `--no-defaults`, datadir sementara; bukan service/data Laragon pengguna |
| Database utama audit | `clinicos_pr_0thp4gu` | [manifest nonrahasia](evidence/runtime.json); suite PHP memakai suffix `_suite`, restore memakai database lain |
| HTTP | Proxy loopback 13110 menuju empat proses PHP independen 13111-13114 | Bukan satu proses `php -S`; bukan stack Linux/FPM produksi |
| Session / cache / queue | Database audit | Session browser nyata, cache permission bersama, worker database berjalan |
| Reverb | Loopback 13411, kredensial sintetis | WebSocket nyata, bukan broadcast fake |
| SMTP | Loopback 13225, inbox penampung tanpa penerusan | Hanya alamat sintetis domain `.test`; MIME dan URL verifikasi disimpan privat di temporary directory |
| Storage / asset build | Direktori sementara; build Vite terpisah | Path HTTP diikat eksplisit; tidak mengganti `.env`, `APP_KEY`, atau public build pengguna |
| DOKU | HTTPS sandbox saja, override kredensial hanya pada proses probe | Runtime HTTP umum tetap tidak mempunyai kredensial DOKU; tidak ada pembayaran nyata |

`@@datadir` diperiksa terhadap direktori MySQL audit dan `@@port` adalah 13316. Guard bootstrap menolak host/port/nama database di luar lingkungan audit. Tidak menjalankan migration, seeder, `migrate:fresh`, atau query ke database pengguna/produksi. Fixture memakai relasi klinik eksplisit dan paket Team trial, bukan aktivasi pembayaran palsu.

## Migration, foreign key, transaction, index

**PASS:** seluruh migration berhasil dari database kosong, 44 tabel memakai InnoDB, 69 constraint foreign key dan 151 index ditemukan. Pemeriksaan orphan untuk seluruh constraint menghasilkan nol; pemeriksaan relasi tenant appointment/patient/doctor/service juga nol mismatch. Lock dan rollback diuji secara operasional, bukan disimpulkan dari nama index. Bukti: [migration](evidence/migrations.txt), [schema](evidence/schema.json), [concurrency](MYSQL_CONCURRENCY_REPORT.md), [recovery](DISASTER_RECOVERY_REPORT.md).

Schema belum menjadi pengganti validasi tenant pada service/policy: foreign key individual tidak membuktikan seluruh foreign key anak memiliki `clinic_id` yang konsisten.

## Koreksi isolasi yang ditemukan selama audit

Override `LARAVEL_STORAGE_PATH` belum efektif pada request PHP HTTP awal walaupun efektif pada CLI. Tiga foto sintetis audit tersimpan di storage checkout. Ketiganya diidentifikasi hanya melalui referensi database audit, dipindahkan ke private temporary storage dengan validasi absolute path, lalu diuji lagi. Tidak memindahkan/menghapus file pasien asli. Cache/view/log yang sudah ada tidak dihapus untuk membersihkan bukti run awal. Binding eksplisit `useStoragePath()` sekarang dipasang pada bootstrap harness; header HTTP `X-Audit-Storage-Isolated: true` diverifikasi. Run browser final memakai binding ini. Bukti: [storage-isolation-correction.json](evidence/storage-isolation-correction.json).

Queue load awal memakai retry-after uji 5 detik dengan timeout worker 30 detik; seluruh job yang diamati selesai jauh sebelum batas itu. Nilai tersebut tidak layak menjadi konfigurasi produksi. Run recovery final memakai retry-after **90 detik > timeout 30 detik**, sesuai default queue aplikasi. Tidak mengklaim pengujian job panjang atau exactly-once delivery.

## Deployment dan dependency

**BLOCKED:** VPS, HTTPS, DNS wildcard tenant, firewall, process manager, cron, backup offsite, observability, dan secret management produksi tidak diakses. Workflow `.github/workflows/deploy.yml` melakukan update direktori langsung, migration dan optimize; tidak memiliki rollback otomatis, health gate, atau langkah restart queue worker/Reverb. Ini analisis source, bukan bukti bahwa service VPS tidak berjalan.

**PASS dalam cakupan paket:** Composer audit dan npm audit terbaru melaporkan **0 advisory**. [Composer](evidence/composer-audit.json), [npm](evidence/npm-audit.json). Tidak mencakup OS, MySQL binary, konfigurasi server, atau seluruh kemungkinan kerentanan aplikasi.

MySQL 8.0.30 dipakai untuk membuktikan perilaku MySQL yang tersedia lokal. Oracle menyatakan MySQL 8.0 masuk Sustaining Support pada April 2026; hasil ini tidak mensertifikasi versi database produksi yang didukung. Evaluasi versi server staging/produksi diperlukan, tanpa melakukan major upgrade otomatis. [Pengumuman resmi MySQL](https://www.mysql.com/support/eol-notice.html).

## Reproduksi

Skrip di [tests/](tests/) hanya mengoperasikan lingkungan disposable. `runtime.py init` membuat datadir baru; jalankan setelah instance audit sebelumnya dihentikan dan arsipkan evidence sebelum rerun. Sesuaikan lokasi binary MySQL pada manifest bila berpindah mesin.

```powershell
python qa-audit/production-readiness/tests/runtime.py init
python qa-audit/production-readiness/tests/runtime.py setup
python qa-audit/production-readiness/tests/runtime.py php qa-audit/production-readiness/tests/audit.php fixture
python qa-audit/production-readiness/tests/runtime.py start
```

Jangan menyalin kredensial, inbox MIME, SQL dump, URL reset/booking/checkout, atau data private temporary runtime ke laporan publik. Tidak ada commit, push, deploy, atau perubahan konfigurasi produksi dilakukan.

Pint dijalankan hanya pada tiga file PHP harness audit agar tidak memformat perubahan produksi pengguna yang sudah ada. Pemeriksaan sintaks 14 file harness, `git diff --check`, dan fingerprint tracked diff lulus; [final-checks.json](evidence/final-checks.json). Perintah Pint global `--dirty` sengaja tidak dipakai karena cakupannya dapat menyentuh perubahan produksi yang bukan bagian audit ini.

Setelah pengujian, delapan proses service audit dihentikan dengan verifikasi identitas PID, termasuk MySQL audit. Port HTTP/SMTP/Reverb/MySQL audit sudah ditutup. Temporary data dipertahankan untuk reproduksi; service/database pengguna tidak dihentikan. [cleanup.json](evidence/cleanup.json).
