# Post-S2 — Batas yang masih berlaku

## Status closure

- **PR-02 FIXED & VERIFIED:** semantic strict JSON assertion lintas SQLite/MySQL.
- **PR-03 FIXED & VERIFIED:** bounded factory dan deterministic boundary regression.
- **Notification SQLite reservation failure FIXED & VERIFIED pada interleaving yang direproduksi:** default IMMEDIATE, real worker dan dua proses; copy failed job pulih tanpa duplikasi stored notification.
- **PR-01 tetap FIXED & VERIFIED single-node**, sekarang actual WS fence juga dibuktikan pada MySQL, dengan authority/ACK-write denial serta restart points yang terdaftar.

Tidak ada FAIL/ERROR tersisa pada final test matrix. Seluruh risiko di bawah bukan klaim sudah selesai.

| Batas | Status | Implikasi |
| --- | --- | --- |
| Multi-node, Redis scaling, rolling mixed-node deploy | NOT TESTED / di luar scope | Tidak ada distributed fence. Tetap satu Reverb process/node; scaling fail-closed bukan bukti multi-node aman |
| Semua titik crash dan database failover | NOT TESTED menyeluruh | Diuji worker stop/restart dan terminate Reverb setelah revoke commit sebelum ACK; tidak setiap instruksi write/send/ACK/queue delete |
| MySQL server mati total, disk full/corrupt, mesin power failure | NOT TESTED | SELECT/UPDATE privilege denial membuktikan read/write failure spesifik, bukan seluruh mode kerusakan database |
| Bytes yang ditulis sebelum ACK | Batas protokol | Dapat tiba terlambat di jaringan. Klaim fence adalah private send baru pada old physical sockets setelah ACK |
| Exactly-once WS transport | Tidak dijanjikan | Replay job menghasilkan dua raw frames; client melakukan dedup ID dan database menyimpan satu record. Queue tetap at-least-once |
| SQLite concurrency | Single writer tetap berlaku | IMMEDIATE mencegah snapshot upgrade pada kasus ini; transaksi panjang masih dapat menyebabkan busy timeout. Tidak melakukan load/stress atau menaikkan timeout |
| Mutasi SQL/pivot ACL di luar APIs terkonfigurasi | Di luar boundary S2 | Tidak otomatis bump epoch. Gunakan User/configured Role/Permission mutation paths; tidak ada trigger DB baru |
| Session grant tombstone/history retention | Belum ada policy cleanup baru | Jangan purge tombstone secara sembarang; delayed auth/logout races tetap dilindungi desain S2 |
| Long-lived HTTP/Octane dan upgrade Reverb | NOT TESTED dalam paket ini | Hook internal Reverb diverifikasi untuk installed 1.12.0; upgrade wajib mengulang runtime binding dan race tests |
| SLA reconnect/ACK | Tidak ditetapkan | Pusher memiliki backoff; harness menunggu actual subscription sebelum fault. Tidak mengarang batas waktu produk baru |

## Batas penggunaan hasil

Full suite hijau bukan pernyataan production-ready. Tidak menjalankan deployment, migration pada DB pengguna, multi-node, load/stress, PG-01–PG-16 atau permission editor. Semua syarat rilis/restart dari S2 tetap memerlukan proses rilis yang disetujui; laporan ini tidak menjalankannya.

Original failed-job S2 dan seluruh laporan historis tetap utuh. Probe recovery menggunakan salinan temporary; `.env` dan APP_KEY pengguna tidak berubah. Mutasi privilege/grants hanya pada instance MySQL dedicated yang dibuat untuk audit ini.

State akhir runtime, termasuk pending scheduled control retries bila ada, dilaporkan apa adanya pada [runtime-final-state.json](evidence/runtime-final-state.json). `acknowledged_at` adalah sumber status revocation; scheduled no-op job yang sudah ACK tidak berarti hak akses lama masih berlaku.
