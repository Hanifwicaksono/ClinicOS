# PR-01 S2 — Laporan akhir

15 November 2026. **PR-01: FIXED & VERIFIED untuk single-node Reverb 1.12.0, seluruh private channel ClinicOS actual, dan mutation APIs aplikasi yang diinventarisasi.** Tidak ada klaim multi-node atau deployment production-ready. Implementasi belum di-deploy.

## Mekanisme yang diimplementasikan

Security mutation menyimpan durable monotonic user epoch, revocation request dan database queue job dalam transaksi yang sama. Signed channel identity mengikat user, epoch, tenant, app/channel dan durable session scope. Manager/channel aplikasi benar-benar dipakai Reverb runtime, memeriksa authority fresh, mem-pin identitas physical socket, menginvalidasi old subscriptions dan meminta terminasi server.

Final delivery fence menolak callback epoch lama. ACK tidak berasal dari respons HTTP SDK: ACK baru disimpan setelah tidak ada admitted write dan **seluruh old physical transports benar-benar close**. Gagal control/authority tidak memulihkan hak; operator berwenang melihat pending/failure dan bisa retry. Feedback membedakan expired session dari koneksi dan mempertahankan editor/foto pending.

Tiga migration additive: epoch user, durable revocation/ACK history, durable session grants/logout tombstones. **33 file aplikasi/migration** dan **2 file test** disentuh/ditambahkan S2; daftar lengkap ada pada [IMPLEMENTATION_CHANGES](IMPLEMENTATION_CHANGES.md). Vendor/dependency tidak berubah.

## Bukti keamanan utama

- Dua browser dan multi-tab: old sockets berhenti menerima private queue serta own-notification sesudah committed revoke/ACK; signed stale/tampered/cross-socket payload ditolak. Reconnect tidak mengembalikan hak yang dicabut. User/tenant yang masih berwenang tetap menerima event baru.
- **S2-15:** empat post-auth callbacks ditahan, revoke commit, kedua old transports close, ACK tersimpan, callback dilepas sesudah ACK: seluruhnya `accepted=false`, nol raw write dan nol old private send sesudah ACK. Event baru kemudian diterima kontrol sah.
- **S2-16:** empat admitted writes ditahan; ACK tetap pending. Semua write selesai dan old transport close **sebelum** ACK; event baru sesudah ACK tidak masuk old sockets.
- MySQL dua proses membaca epoch 2 bersamaan, menyimpan 3 dan 4; kedua field security dan kedua request tersimpan. Rollback tidak meninggalkan epoch/request/job atau terminasi socket sah.
- Control401, connection refused, delayed worker, authority-read DB failure dan operator retry diuji actual. State akhir: 82 revocations, seluruhnya ACK, nol pending transport failures/jobs.

Bukti: [race trace ringkas](evidence/race-summary.json), [browser final](evidence/browser-verified-final.json), [MySQL concurrency](evidence/mysql-concurrency-final.json). Detail makna ACK pada [SERVER_DELIVERY_FENCE_REPORT](SERVER_DELIVERY_FENCE_REPORT.md).

## Hasil pengujian actual

| Gate | Hasil |
| --- | --- |
| Full PHP SQLite, storage terisolasi | **451 PASS**, 0 FAIL/ERROR, 2.967 assertions |
| Full PHP MySQL dedicated | **449 PASS, 1 FAIL, 1 ERROR**, 2.958 assertions |
| Backend revocation baru, subset kedua full suites | **27/27 PASS pada masing-masing DB** |
| JavaScript + Chrome | **27/27 PASS**: 25 unit JS + 2 workflow Chrome |
| Browser S2 dengan Reverb/worker actual | **26/26 PASS** |
| Original Playwright PG-17/18 | **12/12 PASS** |
| Concurrent MySQL + rollback | **2/2 checks PASS** |
| Vite build dan Pint | **PASS** |

**Full suite MySQL belum hijau:** PR-02 JSON object key ordering tetap FAIL; PR-03 fixture `subscription_plans.code` overflow tetap ERROR. Tidak ada assertion lama dilemahkan atau failure disembunyikan. Rincian dan seluruh artefak final/historis pada [FUNCTIONAL_REGRESSION](FUNCTIONAL_REGRESSION.md).

Satu `DeliverClinicNotification` job fixture SQLite juga habis retry akibat `database is locked`; histori tetap disimpan. Ini batas keandalan sandbox yang belum diselesaikan, bukan false ACK revokasi. Nol pending jobs tidak berarti seluruh job sukses.

## Batas dan status terbuka

Transport WebSocket final diuji pada SQLite; MySQL dipakai untuk backend suite dan concurrent row locking, **bukan actual WS fence**. Multi-node, crash/restart tepat saat ACK dan fault khusus database write ACK belum dieksekusi end-to-end. Raw SQL/pivot ACL mutation di luar configured model APIs tidak dijamin menaikkan epoch. Logout biasa scoped per sesi, bukan pencabutan global.

PR-02, PR-03 dan observasi failed notification SQLite tetap terbuka. Risiko, kebutuhan restart/migration sebelum integrasi nyata, serta batas retention ada pada [REMAINING_RISKS](REMAINING_RISKS.md). Tidak ada SLA ACK atau keputusan D-01–D-20 yang dianggap disetujui.

## Pelestarian dan keselamatan pekerjaan

Hash source PG-17/18, test lama, vendor, lock/manifests, `.env` dan S1 dipertahankan: [source-integrity](evidence/source-integrity.json). Laporan historis tidak ditimpa. Testing memakai fixture dummy dan storage temporary terisolasi; tidak mengubah data, key, session atau storage pengguna. Tidak ada commit, push, deploy, pembayaran nyata, load/stress atau fitur lain.

Seluruh proses HTTP/Reverb/worker dan dedicated MySQL milik testing telah dihentikan; tidak tersisa listener testing pada port yang diperiksa: [cleanup](evidence/cleanup.json). Ringkasan mesin: [summary.json](evidence/summary.json).

Delapan laporan S2 ini merupakan hasil implementasi dan bukti run terbaru; S1 tetap bukti kompatibilitas historis, bukan dasar tunggal penutupan PR-01.
