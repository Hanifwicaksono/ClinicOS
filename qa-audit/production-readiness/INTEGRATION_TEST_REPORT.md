# Integration test report

Gate keseluruhan: **BLOCKED** untuk integrasi provider end-to-end. SMTP lokal, workflow autentikasi, scheduler/worker, checkout dan reconciliation sandbox telah diuji nyata.

| Integrasi / skenario | Status | Bukti dan batas |
| --- | --- | --- |
| SMTP TCP dan penerimaan inbox testing | PASS | Symfony mailer mengirim MIME ke sink loopback; sink tidak meneruskan email |
| Staff create, signed verification, login | PASS | Receptionist baru dibuat admin melalui UI, login unverified, link dari inbox, kemudian dashboard |
| Password reset mandiri | PASS | Email reset dari inbox dipakai di browser; sesi lama 401, login password baru berhasil |
| Password reset oleh admin | PASS | UI admin mengganti password; sesi lama ditolak, fresh login berhasil |
| Email staf diubah | PASS | Verified timestamp direset, link lama 403, email baru diterima dan diverifikasi melalui UI |
| Scheduler -> queue -> inbox | PASS | `schedule:test` menjalankan entry asli dua kali; satu reminder, satu email, sent/mailed tersimpan, nol failed |
| Queue delivery dan restart | PASS | Job bertahan ketika worker berhenti dan habis setelah restart; lihat recovery |
| DOKU sandbox checkout | PASS | Request HTTPS nyata; URL checkout HTTPS diterima dan invoice pending; tidak membuka/membayar dengan instrumen pembayaran |
| DOKU backend reconciliation | PASS | GET status provider nyata diproses; subscription tetap tidak berubah untuk pembayaran pending |
| Owner checkout bersamaan dengan pelayanan | PASS | Dua proses/koneksi MySQL; pembayaran pending dan draft dokter sukses tanpa aktivasi prematur |
| Callback DOKU dari provider | BLOCKED | Tidak ada endpoint notifikasi audit yang dapat dijangkau publik |
| Payment failed/timeout/duplicate webhook dari sandbox nyata | BLOCKED | Belum ada siklus pembayaran/callback sandbox lengkap; tidak diganti klaim fake test |
| Signature invalid, mismatch invoice/amount, replay/failure handling lokal | PASS dalam regression suite terkait | HTTP fake/fixture pada suite PHP; bukan bukti provider mengirim callback nyata |
| Private photo upload dan policy | PASS | Upload browser; assigned doctor 200, tujuh persona lain 403; endpoint private diuji kembali dari hasil restore |
| Kegagalan filesystem nyata | PASS | Root disk diarahkan ke file penghalang di proses audit; save gagal, versi/foto database tidak berubah, file lama utuh |

Bukti: [Playwright](evidence/playwright-results.json), [integration](evidence/integration-results.json), [sandbox probe](evidence/doku-sandbox-probe.json), [scheduler](evidence/scheduler.txt), [concurrency](evidence/mysql-concurrency.json), [recovery](evidence/recovery-results.json), [PHP](evidence/php-main.xml).

## Keamanan dan metode

Konfigurasi lokal mempunyai credential sandbox yang tidak menyerupai placeholder. Probe memakai nilai tersebut hanya di environment proses, dengan guard host persis `https://api-sandbox.doku.com`. Runtime HTTP umum tetap menonaktifkan credential DOKU. Tidak menyalin credential, signature, URL checkout, token reset/verifikasi/booking atau MIME ke evidence publik. Semua owner/customer memakai identitas sintetis domain `.test`; tidak ada pembayaran nyata atau email kepada pengguna sungguhan.

Invoice pending dan reconciliation tidak mengaktifkan subscription. Redirect browser tidak dipakai sebagai bukti pembayaran. Callback terkontrol dalam test lokal tidak diperlakukan sebagai callback yang benar-benar dikirim provider.

Pada SMTP, staf Doctor memakai notification path yang sama tetapi siklus inbox nyata lengkap dijalankan pada Receptionist; Doctor mempunyai regression coverage PHP. **NOT TESTED:** inbox eksternal, TLS SMTP eksternal, SPF/DKIM/DMARC, reputation/deliverability, dan cron daemon VPS. Scheduler diuji dengan `schedule:test` pada due-date fixture, bukan menunggu pergantian jam atau membuktikan cron terpasang.

## Reproduksi dan sisa pekerjaan

```powershell
python qa-audit/production-readiness/tests/integration.py
python qa-audit/production-readiness/tests/doku_probe.py
```

`integration.py` memperbarui hasil lokal; jalankan probe sesudahnya untuk memperbarui bukti sandbox. Sediakan receiver HTTPS publik khusus staging yang terhubung ke database audit terisolasi untuk melanjutkan callback sukses/gagal, timeout, replay dan duplicate webhook. Jangan menunjuk callback staging ke aplikasi/database produksi. Tidak membuat tunnel/deploy tanpa izin.
