# Disaster recovery report

Gate pemulihan lokal terisolasi: **PASS, 7/7 pemeriksaan**. RPO/RTO produksi dan runbook operasional: **NOT TESTED**.

## Bukti aktual

| Pemeriksaan | Hasil | Gate |
| --- | --- | --- |
| Kegagalan storage nyata | Root disk di proses uji berupa file penghalang; upload gagal, versi RME tidak berubah, tidak ada photo reference baru; hash file lama tetap | PASS |
| Transaksi booking sengaja gagal | Patient, appointment, queue, notification, jobs dan audit log kembali ke jumlah sebelum transaksi; koneksi tetap dapat dipakai | PASS |
| Worker dihentikan lalu dihidupkan | Dua job bertahan di database saat worker mati, kemudian drain; nol failed job | PASS |
| Backup/restore MySQL | Dump hanya database audit; restore ke database audit lain; checksum seluruh 44 tabel sama | PASS |
| Pemulihan private file | Empat file dipulihkan ke direktori lain di luar webroot; semua SHA-256 cocok | PASS |
| Restart aplikasi | Empat backend dihentikan setelah identitas proses diverifikasi; PID baru hidup dan health endpoint 200 | PASS |
| Aplikasi membaca hasil restore | HTTP kernel pada database hasil restore dan disk hasil restore mengembalikan foto private 200 untuk dokter penanggung jawab | PASS |

Hasil numerik terbaru, termasuk durasi backup/restore dan PID, ada pada [recovery-results.json](evidence/recovery-results.json). File dump, MIME inbox dan byte private tidak disalin ke repository. Backup awal diarsipkan sebagai `recovery-initial-run.json`; run final memakai database restore baru serta retry-after 90 s > timeout worker 30 s.

## Cara reproduksi

1. Gunakan instance audit dari environment report dan jalankan browser fixture agar terdapat foto klinis sintetis.
2. Tunggu job yang berjalan drain; hentikan hanya worker audit yang command line-nya cocok dengan PID yang direkam.
3. Injeksi exception setelah operasi booking dalam outer transaction; bandingkan enam tabel sebelum/sesudah.
4. Buat booking ketika worker berhenti, buktikan job tetap ada, restart worker, tunggu drain dan periksa failed jobs.
5. Quiesce backend audit/worker, jalankan `mysqldump --single-transaction`, buat database restore baru, import, dan bandingkan checksum semua tabel.
6. Salin file private ke backup dan direktori restore baru, bandingkan hash, restart backend dan uji endpoint foto dari data hasil restore.

```powershell
python qa-audit/production-readiness/tests/recovery.py
```

Tidak menghentikan MySQL pengguna, tidak menimpa database asal, tidak menghapus pasien atau histori. Nama database restore dan path temporary divalidasi. Kegagalan transaksi memakai fixture sintetis tersendiri.

## Batas yang belum terbukti

**NOT TESTED:** kill worker di tengah pengiriman SMTP/provider sesudah side effect tetapi sebelum ack, exactly-once delivery eksternal, crash mysqld saat commit, corrupt backup, incremental/binlog point-in-time restore, offsite restore di mesin lain, backup encryption/retention/key escrow, kehilangan satu disk/server, dan pemulihan VPS dengan process manager sesungguhnya.

Backup+restore lokal yang cepat tidak menetapkan RTO/RPO bisnis. Database dump dan file harus diambil pada titik konsisten; quiesce audit di run ini tidak membuktikan backup terjadwal tanpa downtime saat klinik sibuk. Restart `/up` sendiri bukan bukti seluruh alur sehat, sehingga ditambah schema/orphan check serta endpoint foto hasil restore.

Rekomendasi sebelum GO: tetapkan RPO/RTO, siapkan backup offsite terenkripsi dan restore drill berkala, uji crash worker di titik side effect, dokumentasikan supervisor/cron/restart, serta buat release/rollback yang dapat diuji di staging. Source/deployment produksi tidak diubah otomatis.
