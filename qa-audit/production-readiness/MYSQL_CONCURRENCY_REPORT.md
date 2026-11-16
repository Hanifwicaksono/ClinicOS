# MySQL concurrency report

Status skenario yang dijalankan: **PASS, 9/9**. Aktivasi subscription dari callback provider saat operasional: **BLOCKED**.

## Metode dan bukti concurrency

Setiap kasus membuat fixture klinik tersendiri. Dua proses PHP melakukan bootstrap aplikasi dan membuka koneksi MySQL independen, menulis PID serta `CONNECTION_ID()`, menunggu barrier, lalu dilepas bersama. Semua sembilan pasangan mempunyai waktu eksekusi yang overlap dan connection ID berbeda. Bukan request berurutan dan bukan SQLite.

Pada perebutan slot terakhir dan replay identik, observer ketiga membaca `performance_schema.data_lock_waits`; penantian lock InnoDB terbukti. Hold lock 400 ms sengaja ditambahkan oleh harness pada query lock pertama untuk membuat observasi deterministik. Angka ini tidak dimasukkan ke pengukuran performa.

Bukti lengkap: [mysql-concurrency.json](evidence/mysql-concurrency.json), [harness](tests/concurrency.py), [worker/kernel](tests/audit.php).

| Kasus | Hasil aktual | Gate |
| --- | --- | --- |
| Dua resepsionis, satu kuota tersisa | 302 + 422; satu appointment, satu queue, satu nomor antrean | PASS |
| Dua akun pasien memilih dokter/sesi sama, kuota satu | 302 + 422; satu booking; account type dan role Patient nyata | PASS |
| Dua request booking dengan UUID identik | 302 + 302; satu appointment/queue, tanpa duplikasi patient | PASS |
| Dua sesi dokter penanggung jawab menyimpan versi RME sama | 200 + 409; lock version naik satu, tidak ada lost update | PASS |
| Dua dokter berbeda mencoba RME sama | 200 + 403; dokter yang tidak ditugaskan ditolak | PASS |
| Admin mengganti dokter sesi saat booking berjalan | 302 + 422; tidak ada mismatch dokter appointment/schedule | PASS |
| Owner menonaktifkan resepsionis saat request berjalan | Update sukses; request yang telah diotorisasi dapat selesai; request berikutnya dialihkan ke login | PASS pada HTTP |
| Perubahan entitlement ke Free bersamaan dengan draft aktif | Kedua operasi sukses; draft IN_PROGRESS tetap bisa disimpan, booking baru ditolak 403, histori tetap ada | PASS pada service + HTTP |
| Owner memilih paket Individual melalui checkout DOKU sandbox saat dokter menyimpan | Owner 302, dokter 200; satu pembayaran pending, draft naik satu versi; paket Team yang berjalan tidak berubah sebelum callback sah | PASS |

Perubahan ke Free menguji `SubscriptionService::downgradeToFree()` dari proses independen, bukan menyatakan owner mempunyai tombol downgrade yang sebenarnya merupakan operasi platform. Kasus checkout owner menggunakan HTTP kernel, middleware, owner fixture, dan sandbox nyata. Tidak ada pembayaran sungguhan dilakukan.

Sesi berakhir saat menyimpan diuji terpisah di Playwright: HTTP **401**, teks belum tersimpan tetap ada, versi database tidak berubah, pengguna login kembali lalu finalisasi melalui UI. Laravel dapat menolak dengan 401 pada request browser same-origin; tidak memaksakan semua sesi berakhir menjadi 419. [Bukti browser](evidence/playwright-results.json).

## Integritas dan batas

Tidak ditemukan duplicate booking, duplicate queue, nomor antrean ganda, pelanggaran kuota, lost update, tenant mismatch, atau deadlock tak tertangani pada skenario ini. Satu dokter lain memang harus ditolak, sehingga lost update juga diuji memakai dua sesi dokter penanggung jawab yang sama.

Request yang sudah melewati authorization sebelum deactivation bukan dianggap otomatis dibatalkan. **Socket yang tetap menerima event sesudah revokasi adalah masalah terpisah PR-01**, bukan PASS akses menyeluruh. [Realtime report](REALTIME_TEST_REPORT.md).

**BLOCKED:** pergantian paket yang benar-benar diaktifkan oleh callback DOKU bersamaan dengan operasi klinik, karena receiver sandbox publik belum tersedia. **NOT TESTED:** soak beberapa jam, failover MySQL, jaringan database terputus saat commit, dan proof semua pola deadlock yang mungkin. Hasil sembilan race tidak menjamin tidak ada race lain.

Reproduksi:

```powershell
python qa-audit/production-readiness/tests/concurrency.py
```

Rekomendasi: pertahankan urutan lock dan constraint yang ada, perbaiki revokasi socket, lalu ulangi pada versi MySQL serta stack deployment staging yang akan dipakai.
