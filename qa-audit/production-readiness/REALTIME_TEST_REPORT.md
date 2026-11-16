# Realtime test report

Gate keseluruhan: **FAIL**. Koneksi, delivery normal, reconnect, dan isolasi tenant baru lulus; revokasi socket aktif gagal.

| Pemeriksaan | Bukti aktual | Status |
| --- | --- | --- |
| Reverb dan database worker benar-benar hidup | TCP/WebSocket nyata, Echo browser menerima `queue.updated` dari worker | PASS |
| Dua browser staf menerima perubahan setelah check-in | Dua konteks browser berbeda, payload whitelist enam key | PASS |
| Tenant lain subscribe private clinic channel | Authorization 403 dan nol event diterima | PASS |
| Payload antrean tidak berisi identitas/isi medis | Hanya queue_id, display_number, status, doctor_id, queue_date, updated_at | PASS pada event yang diuji |
| Disconnect dan reconnect | Socket ditutup, Pusher reconnect, subscribe ulang dan event berikutnya diterima | PASS |
| Role dicabut ketika socket aktif | Subscribe baru 403; socket lama tetap connected dan menerima satu event check-in berikutnya | FAIL — PR-01 |
| Staf dinonaktifkan melalui UI | HTTP berikutnya dialihkan ke login; socket lama masih menerima satu event berikutnya | FAIL — PR-01 |
| Burst 40 check-in | 40 event diterima, nol hilang; latency p50 1,270 s / p95 2,283 s / p99 2,590 s | FAIL terhadap asumsi p95 <= 2 s |

Bukti: [browser results](evidence/playwright-results.json), [permission revocation](evidence/permission-revocation.json), [deactivation](evidence/deactivation-revocation.json), [load](evidence/load-results.json).

## Reproduksi PR-01 dan akar masalah

1. Login dua browser resepsionis dan tunggu private clinic channel ter-subscribe.
2. Cabut role browser kedua menggunakan API Spatie pada fixture, atau nonaktifkan akun melalui UI admin.
3. Buktikan akses HTTP/authorization channel baru ditolak.
4. Buat booking dan lakukan check-in dari pengguna yang masih berwenang.
5. Buktikan browser kontrol menerima event baru; browser yang kehilangan akses juga masih menerimanya.

Authorization channel berjalan ketika subscribe. Tidak ada mekanisme server untuk mencabut subscription lama pada perubahan role/status akun. Penolakan HTTP tidak otomatis mengakhiri koneksi Reverb. Severity **P2 / Medium**: akses private masih hidup setelah revokasi; tidak ditemukan isi medis pada event yang diamati. Tidak mengubah source produksi selama audit. [Temuan lengkap](NEW_FINDINGS.md).

Channel `clinic.{id}.queue-board` memang public dan membawa metadata antrean nonmedis yang sama. Sifat public itu tidak dianggap kebocoran lintas tenant; private clinic channel tetap harus memenuhi kontrak revokasi akses.

## Batas dan tindak lanjut

**NOT TESTED:** revokasi channel user notification yang sudah aktif, perpindahan tenant ketika socket hidup, TLS/wss deployment, proxy idle timeout, horizontal Reverb/Redis, restart node Reverb saat event sedang dipublikasikan, dan koneksi ribuan socket. Tidak menyamakan authorization private clinic channel dengan audit semua channel.

Terapkan revokasi/otorisasi ulang yang ditegakkan server; jangan mengandalkan browser untuk disconnect. Profilkan queue polling, fanout dan refetch UI untuk target latency, lalu retest di staging. Referensi operasi: [dokumentasi Reverb Laravel 13](https://laravel.com/framework/docs/13.x/reverb).

```powershell
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/browser.mjs
```

Run awal dengan selector/probe yang tidak tepat tetap diarsipkan. Hanya run final dengan positive control event yang dipakai untuk gate revokasi.
