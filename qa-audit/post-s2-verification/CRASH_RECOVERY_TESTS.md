# Post-S2 — Failure dan crash recovery yang dieksekusi

**Seluruh skenario berikut PASS pada MySQL runtime actual.** Ini daftar failure points yang benar-benar diuji, bukan klaim semua jalur crash aman.

| ID | Fault actual | Invariant yang dibuktikan |
| --- | --- | --- |
| P-01 | Cabut SELECT `users` dari koneksi Reverb | Private fail-closed; public positive control berjalan; binding valid pulih sesudah grant dikembalikan |
| P-02 | Cabut UPDATE `realtime_revocations` dari koneksi Reverb | Physical close tidak cukup bila write ACK gagal; durable ACK tetap NULL; HTTP rights tidak dipulihkan; retry sesudah DB privilege pulih berhasil |
| P-03 | Hentikan worker, enqueue event dan revoke, lalu mulai worker baru | Request/epoch durable MySQL; job notification tertunda tidak membocorkan private data ke revoked old sockets; pasien kontrol tetap menerima |
| P-04 | Hentikan worker, commit revoke, terminate proses Reverb dengan old WS aktif, mulai proses baru dan worker | Kedua old physical transports tutup; pending request dipulihkan; node baru ACK dari DB latest authority; old signature/revoked reconnect ditolak |
| S2-12/13/24 | Delayed worker, control401, control connection refused | No false ACK; durable retry dan feedback operator; fresh delivery guard tetap aktif |
| S2-17 | Rollback perubahan role/status | Tidak ada epoch/request committed atau terminasi koneksi sah; event baru tetap diterima |

## Waktu dan state penting

P-02: revoke commit **1794750678.417612**, epoch 46. Saat UPDATE ditolak, ACK NULL dan `failed=true`, walaupun kedua actual transport close telah tercatat. Old sockets menerima nol private frames dari queue 142, sedangkan receptionist/pasien kontrol menerima. Restore privilege **1794750680.716000**, durable ACK **1794750681.192670**. ACK tidak disimpulkan dari respons SDK.

P-03: **5 pending jobs** sebelum worker restart. Revoke commit **1794750691.863772**, epoch 48, ACK belum ada. Worker baru memproses queue 143; pasien menerima own-user notification, old target sockets nol. Setelahnya durable ACK terverifikasi.

P-04: revoke commit **1794750702.670440**, epoch 50, ACK belum ada. Browser melihat physical close old sockets **1794750702.767000** dan **1794750702.768000**. Reverb/worker baru memakai authority MySQL persisted, ACK **1794750706.823029** pada node baru. Event baru queue 144 dimulai **1794750721.471000**, diterima kontrol sah. Revoked new auth 403; replay signature lama 4009; nol old/private target delivery.

Bukti: [mysql-recovery-summary.json](evidence/mysql-recovery-summary.json), [raw final cases](evidence/browser-mysql-faults-final.json), [wire](evidence/wire.jsonl). Waktu memakai real Unix clock untuk ordering; tanggal klinis fixture dibekukan terpisah. Private frozen worker clock dapat membuat control retry yang sudah ACK baru menjadi due setelah waktu fixture menyusul; bukan perpanjangan hak akses.

## Pemisahan kegagalan

Authority SELECT denial dan ACK UPDATE denial memakai privileges MySQL nyata, bukan mock HTTP. Database utama testing tetap hidup, sehingga commit HTTP, observasi state dan authorized controls dapat dibuktikan secara terpisah. Keduanya tidak sama dengan seluruh server MySQL mati atau storage rusak.

P-04 memakai terminasi proses Reverb dari test runner. Ini benar-benar memutus transport OS/browser, tetapi bukan simulasi power failure mesin, OOM nondeterministik, partial TCP kernel failure atau database-primary crash. Restart dilakukan single-node; tidak ada rolling multi-node atau shared registry.

Race admitted/pre-admission menggunakan server barriers pada [MYSQL_WEBSOCKET_FENCE_REPORT](MYSQL_WEBSOCKET_FENCE_REPORT.md). Restart P-04 dilakukan setelah commit, sebelum control ACK; **tidak** mengklaim proses dibunuh tepat di setiap instruksi write/ACK. Fault sebelum/sesudah commit, packet loss tiap boundary, crash setelah send tetapi sebelum queue delete, failover dan semua permutasi shutdown belum seluruhnya dieksekusi.

Tidak ada source Reverb/S2 dirombak untuk membuat eksperimen lulus. Failed attempts diagnostik dipertahankan dan dibedakan dari run final.
