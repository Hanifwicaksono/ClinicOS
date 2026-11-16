# PR-01 S2 — Batas dan pekerjaan tersisa

## Kontrak yang terverifikasi

PR-01 ditutup **hanya untuk single-node Reverb pada stack terpasang dan mutation APIs aplikasi yang diinventarisasi**. Bukti actual mencakup old sockets, signed replay/reconnect, private queue/user notifications, cross-tenant, failure-read DB/control, durable retry dan dua race ACK. Ini tidak menyatakan seluruh deployment atau seluruh produk production-ready.

## Batas yang harus dipertahankan

| Area | Status | Risiko / tindakan berikutnya |
| --- | --- | --- |
| Multi-node Reverb, Redis scaling | NOT TESTED / unsupported | Tidak ada distributed fence/ACK. Scaling enabled fail-closed; lebih dari satu node tanpa flag scaling juga **tidak aman diklaim**. Harus tetap satu node/process penerima traffic |
| Actual WS/worker dengan MySQL | NOT TESTED | Transport final memakai SQLite; MySQL diuji full backend dan concurrent writers. Sebelum perluasan klaim runtime, ulangi matrix transport pada MySQL |
| Process restart/crash tepat di tengah ACK | NOT TESTED end-to-end | ACK callback failure tidak membuka floor; restart menutup transport, guard DB tetap diperlukan. Jangan menyamakan analisis dengan fault-injection PASS |
| Database write ACK gagal | NOT TESTED end-to-end | Code mempertahankan durable unacked state dan retry; read authority DB failure sudah actual PASS, tetapi failure write ACK terpisah belum dipaksakan |
| Direct SQL / raw pivot / base Spatie model mutasi | Di luar boundary | Tidak otomatis bump epoch. Tidak ditemukan caller produksi demikian; caller baru wajib memakai User/configured Role/Permission APIs. Jangan bypass demi maintenance |
| Session grant/revocation retention | Belum ada cleanup policy | Tombstone melindungi delayed authorization. Jangan purge revoked scopes sembarangan; histori tidak dihapus dalam S2 |
| Ordinary logout | Per sesi | Deny future auth/delivery scope sesi; bukan global user epoch/termination ACK. Browser lain tetap sah. Admitted pre-logout frame tidak diretract |
| In-flight bytes | Batas protokol | ACK menunggu write completion dan actual transport close. Byte sebelum ACK dapat terlambat tiba; zero late network arrival bukan janji yang dapat diberikan |
| Reverb internal APIs | Risiko upgrade | Manager/channel/close extension points diverifikasi pada 1.12.0. Upgrade dependency wajib mengulang binding dan deterministic race tests |
| Queue config | Harus transactional DB connection yang sama | Boundary menolak konfigurasi yang memutus atomisitas. Worker control tetap diperlukan agar pending diselesaikan; tidak ada SLA ACK baru yang dikarang |
| Permission metadata | Request-local cache | HTTP tetap ber-policy; Reverb fresh DB. Long-lived HTTP/Octane worker bukan scope yang diuji |
| Failure UI | Dapat sementara pending/failed | SDK200 dapat mendahului actual close ACK; label baru confirmed sesudah durable ACK, aman di-retry |

## Dua failure historis di luar PR-01

- **PR-02 OPEN:** MySQL JSON key order strict assertion, satu FAIL.
- **PR-03 OPEN:** Faker subscription plan code melampaui schema, satu ERROR pada full MySQL run final.

Keduanya tidak diperbaiki tanpa perluasan scope. Test tetap dipertahankan. Full regression MySQL belum hijau.

Selain itu, **satu job notifikasi fixture S2 gagal karena lock SQLite** dan tetap tercatat. Ini belum diselesaikan dalam scope S2; tidak dihitung sebagai keberhasilan seluruh job meskipun 26 kasus browser lulus. Rincian dan bukti ada pada [FUNCTIONAL_REGRESSION](FUNCTIONAL_REGRESSION.md).

## Integrasi yang diperlukan sebelum pemakaian nyata

Tiga migration additive harus dijalankan melalui proses rilis yang disetujui; semua aplikasi/worker/Reverb harus menggunakan source yang sama. **Restart Reverb diperlukan** agar tidak ada registry/channel native lama yang melewati manager baru. Restart worker untuk mengambil job/provider baru. Menjalankan dua generasi Reverb bersamaan tidak tercakup kontrak single-node.

Jangan mengaktifkan feature ini pada node lama tanpa migration atau menyimpulkan rolling deployment multi-node aman. Dokumen ini menjelaskan syarat integrasi, bukan menjalankan deployment. Database pengguna, `.env` dan app key pengguna tidak diubah; tidak ada commit/push/deploy.

## Tidak dikerjakan

Load/stress, multi-node, permission editor, fitur klinis/komersial baru, PR-02/03 fixes, perubahan PG-17/18, dan optimasi query/performa tidak dikerjakan. Keputusan produk di luar S2 tetap belum disetujui.
