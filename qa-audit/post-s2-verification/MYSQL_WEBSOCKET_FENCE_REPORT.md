# Post-S2 — Actual MySQL WebSocket delivery fence

**VERIFIED untuk single-node Reverb pada stack terpasang.** Seluruh 26 skenario S2 diulang pada MySQL, ditambah 5 skenario fault/recovery: **31 PASS, 0 FAIL pada run final**. Tidak memakai unit mock atau SDK HTTP 200 sebagai bukti fence.

## Lingkungan dan bukti

Dedicated MySQL 8.0.30 baru di loopback 13318, datadir temporary baru, database runtime `clinicos_post_s2_runtime_*`, dan database full-suite terpisah. Reverb 1.12.0 single-process, database worker terpisah, Chrome 158.0.8127.36 melalui Playwright, Echo/Pusher installed. Fixture dummy F1 diimpor ke schema MySQL yang dimigrasikan; `.env`, APP_KEY, DB dan storage pengguna tidak dipakai untuk mutation.

Source S2 produksi tidak diubah. Browser harness menyalin assertions S2 dan mengganti adapter verifikasi SQL SQLite menjadi PDO MySQL; user actions tetap melalui endpoint/UI aplikasi. Scope fault tambahan memakai user database **khusus proses Reverb** dengan privileges per tabel. HTTP/worker memakai koneksi fixture yang tetap sehat. Ini memungkinkan kegagalan authority dan ACK diuji actual tanpa mematikan positive controls.

Bukti final: [26 S2 scenarios](evidence/browser-mysql-final.json), [5 additional scenarios](evidence/browser-mysql-faults-final.json), [matrix ringkas](evidence/security-case-summary.json), [race trace](evidence/mysql-race-summary.json), [wire](evidence/wire.jsonl). Trace hanya metadata/key names dan hash socket, bukan signature, session scope atau isi medis.

## Race pada MySQL

| Titik | S2-15: post-auth callback ditahan | S2-16: admitted write ditahan |
| --- | ---: | ---: |
| Commit revokasi | 1794750127.830404 | 1794750146.117200 |
| Old sends selesai | Callback belum dilepas | 1794750146.789738–1794750146.792774 |
| Actual close kedua old sockets | 1794750128.060654; 1794750128.061169 | 1794750147.441829; 1794750147.442816 |
| Durable ACK MySQL | 1794750128.063300 | 1794750147.446141 |
| Release callbacks lama | 1794750128.345240–1794750128.346811 | Selesai sebelum ACK |
| Aksi event baru dimulai | 1794750128.433000, queue 123 | 1794750147.743000, queue 125 |

S2-15: empat callback sudah lolos fresh authorization, lalu dilepas **sesudah** ACK. Seluruhnya `accepted=false`, `write_calls=0`; nol old private sends sesudah batas. Dua old sockets benar-benar close sebelum ACK.

S2-16: ACK tetap kosong selama empat admitted writes disuspend. Setelah release, semua writes mendahului ACK; actual-close gate juga terpenuhi. Event baru sesudah ACK diterima receptionist/pasien kontrol, tidak pada old sockets. Publikasi baru dan positive controls mencegah false pass karena tidak ada event yang dikirim.

## Matrix keamanan actual

| Kelompok | Kasus | Hasil |
| --- | --- | --- |
| Active old sockets, dua browser | S2-01; role, inherited/direct permission, deactivate, password reset | PASS |
| Reconnect/signature | S2-07/08/09/11/25/26; authorized/revoked reconnect, stale/tampered/cross-socket/native unsigned signature | PASS |
| Multi-tab dan logout scope | S2-10/18 | PASS |
| Delayed worker, control API401/refused, retry | S2-12/13/24 | PASS |
| Authority query failure | S2-14 dan P-01 actual MySQL SELECT denial | PASS |
| Fence races dan rollback | S2-15/16/17 | PASS |
| CCL editor/filter preservation | S2-19/20: caret/node, autosave200→401, unsaved RME dan pending photo | PASS |
| Tenant/public/own notifications | S2-21/22/23: foreign isolation, guest board enam field, appointment dan queue notifications | PASS |
| Actual ACK write denial | P-02, MySQL UPDATE privilege dicabut hanya dari Reverb | PASS |
| Worker restart + delayed notification | P-03 | PASS |
| Abrupt Reverb + worker recovery | P-04 | PASS |
| Notification redelivery | P-05: dua raw frames, satu ID, satu client action, jumlah DB tetap | PASS |

P-01 mencabut SELECT pada `users` untuk koneksi Reverb yang sudah berjalan. Private delivery seluruh target ditolak, public tetap diterima. Grant dipulihkan; binding valid menerima event berikutnya tanpa reload. Ini kegagalan SQL permission actual, bukan substitusi hasil authorization boolean.

P-02 mencabut UPDATE pada `realtime_revocations`. Epoch/request berhasil commit lewat koneksi HTTP yang sehat. Old transports ditutup tetapi ACK tetap NULL dan failure terlihat. Sesudah grant dipulihkan dan retry actual, ACK MySQL baru muncul. HTTP tetap 403 untuk role yang telah dicabut.

Detail restart, batas crash dan waktu ACK pada [CRASH_RECOVERY_TESTS](CRASH_RECOVERY_TESTS.md). Makna ACK tetap server send boundary, bukan janji menarik kembali byte yang sudah ditulis sebelum ACK atau exactly-once jaringan.

## Run diagnostik yang dipertahankan

Percobaan tambahan pertama gagal positive controls karena fault dimulai sebelum kontrol selesai reconnect setelah server restart; auth HTTP200 kemudian ditolak Reverb4009 saat DB authority sedang sengaja tidak tersedia. Harness final menunggu listener dan **seluruh subscription kontrol sukses sebelum fault**, tanpa mengganti authorization atau memaksa logout/reload aplikasi. Timeout observasi reconnect disesuaikan dengan backoff Pusher, tanpa SLA aplikasi baru.

Pemeriksaan awal redelivery juga menunggu seluruh jobs, termasuk control retry tertunda yang sudah ACK. Final memeriksa jobs notification target berdasarkan UUID. Ini mempertahankan assertion delivery dan deduplikasi, sambil tidak salah menilai scheduled control no-op sebagai kehilangan notifikasi. Artefak `browser-mysql-failures-recovery.json` dan `browser-mysql-diagnose.json` tetap disimpan; keduanya bukan hasil final yang hijau.
