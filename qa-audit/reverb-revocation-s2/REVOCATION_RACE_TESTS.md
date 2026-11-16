# PR-01 S2 — Race, concurrency dan rollback

Tanggal eksekusi final: 15 November 2026. Status berikut berasal dari source final, bukan keberhasilan spike S1 atau kandidat S2 sebelumnya.

## Race A: authorize lama → hold → commit → ACK → release

**PASS — S2-15.** Fixture memakai dua browser akun dokter yang sama, kontrol receptionist dalam klinik yang sama, pasien, dan browser tenant lain. Aksi queue actual menghasilkan event melalui database worker. Hook testing menghentikan callback **setelah fresh authority dibaca**, sebelum final admission gate. Hook dipasang hanya pada bootstrap private testing; closure kemudian dilepas melalui parent `DeliveryFence` produksi.

| Titik | Waktu server, Unix detik |
| --- | ---: |
| Mutation role committed, epoch 25 → 26 | 1794740324.927037 |
| Old physical socket pertama benar-benar close | 1794740325.243688 |
| Old physical socket kedua benar-benar close | 1794740325.244132 |
| Durable ACK disimpan | 1794740325.245483 |
| Empat callback lama dilepas | 1794740325.361148–1794740325.362546 |
| Aksi event baru, queue 123, dimulai | 1794740325.403 |

Seluruh empat callback lama menghasilkan `accepted=false`, `write_calls=0`. Tidak ada private MessageSent sesudah ACK pada kedua old socket; browser lama juga tidak menerima event baru. Receptionist/pasien sah menerima event baru, sehingga nol pada socket target bukan akibat publisher mati. Frame public dipisahkan dari private.

## Race B: write sudah admitted → revoke → ACK harus pending

**PASS — S2-16.** Fault hook menggunakan Fiber untuk menahan closure **di dalam** admitted write. Ini memaksa interleaving yang tidak biasa terjadi pada write sinkron Reverb, tanpa memodifikasi vendor.

| Titik | Waktu server, Unix detik |
| --- | ---: |
| Mutation committed, epoch 27 → 28 | 1794740334.084380 |
| ACK saat write ditahan | Tidak ada; pending |
| Empat write lama selesai setelah release | 1794740334.588039–1794740334.589917 |
| Kedua old transport close | 1794740335.056539; 1794740335.057376 |
| Durable ACK | 1794740335.059196 |
| Event baru, queue 125, dimulai | 1794740335.077 |

Semua write yang sudah admitted mendahului ACK. Control tidak mengonfirmasi saat counter aktif. Tidak ada private event baru pada old socket setelah ACK; kontrol sah tetap memperoleh event. Pengiriman sebelum ACK tidak diklasifikasikan sebagai pelanggaran.

Bukti: [race-summary.json](evidence/race-summary.json), [browser final](evidence/browser-verified-final.json), [wire.jsonl](evidence/wire.jsonl) dengan `run=verified-final`, serta assertion pada `tests/browser-extended.mjs`. Socket disimpan sebagai hash. Timestamp trace memakai real clock; tanggal klinis fixture yang dibekukan bukan security clock.

## Locking MySQL dua proses

**PASS — dua pemeriksaan backend, source final.** Dedicated MySQL 8.0.30, loopback 13318, database bernama `clinicos_s2_*`, datadir temporary terpisah dari Laragon pengguna.

Dua proses PHP terpisah terlebih dahulu membaca user epoch 2, lalu menunggu file barrier yang sama. Sesudah release, proses A menyimpan epoch 3 dan proses B epoch 4. Hasil akhir menyimpan kedua perubahan security (inactive dan password berubah), versi akhir 4, serta request versions `[3,4]`. Tidak ada lost bump atau penggunaan ulang epoch stale.

Transaksi rollback berikutnya tidak mengubah epoch, authority, jumlah request atau database jobs. [mysql-concurrency-final.json](evidence/mysql-concurrency-final.json) memuat hasil actual. Script menolak port/database/barrier di luar fixture; tidak memakai database pengguna.

Ini membuktikan locking transaksi MySQL, **bukan** delivery fence WebSocket pada MySQL. Run transport actual memakai SQLite dengan worker dan Reverb terpisah. Tidak ada load/stress atau klaim concurrency multi-node.

## Rollback pada koneksi nyata

**PASS — S2-17.** Role dan is_active diubah dalam transaksi yang kemudian rollback. Tidak ada epoch/request committed atau terminasi pengguna sah. Event baru tetap diterima browser yang sudah tersambung. Test backend juga memeriksa outer rollback dan model stale yang dipakai kembali setelah rollback.

## Riwayat run

Kandidat sebelum durable session-grant table dan actual-close ACK disimpan untuk diagnosis, tetapi bukan bukti final. `browser-verified-final.json` adalah run final 26 PASS. Run MySQL `mysql-full-final` terinterupsi ketika instance testing berhenti; run ulang actual adalah `mysql-full-verified-final`. Tidak ada failure lama yang dihapus untuk membuat angka terlihat hijau.
