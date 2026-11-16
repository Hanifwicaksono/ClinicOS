# PR-01 S2 — Fence dan makna acknowledgement

## Jalur runtime yang dipakai

Stack yang diperiksa: Laravel 13.34.0, Reverb 1.12.0, pusher-php-server 7.3.0, Echo 2.6.1, pusher-js 8.6.0, Spatie Permission 8.3.0, PHP 8.5.5. API diverifikasi dari source dependency terpasang pada S1/S2; tidak menambah hook vendor.

Provider mengikat **kontrak ChannelManager Reverb** ke singleton manager aplikasi. Factory ini membuat guarded private channel dan reserved server control channel. Binding class channel saja tidak cukup. Driver `reverb` memakai BroadcastManager/Pusher SDK existing untuk signed custom data. Real worker memublikasikan QueueUpdated dan kedua jenis user notification melalui instance Reverb actual; bukan fake broadcaster.

Private families actual: `private-clinic.{id}.queues` dan `private-users.{id}`. Queue memerlukan active, verified, staff role, `queue.view`, clinic sama. User channel memerlukan active, verified, role dikenal dan user yang sama. Semua identitas harus cocok dengan epoch/context fresh dan sesi durable. Unknown private dan unused presence ditolak. Public `clinic.{id}.queue-board` tetap native public dengan enam field nonmedis.

Normal broadcast dan `broadcastToAll` masuk guard; inherited internal broadcast memanggil broadcast yang dioverride. Setelah fresh authorization, closure write harus melewati `DeliveryFence::send` terakhir. Memeriksa hak sebelum send saja tidak menjadi ACK.

## Urutan revokasi

1. Commit mutation menyimpan authority/epoch, durable request dan job secara atomik.
2. Sejak commit, fresh HTTP auth, private subscribe dan delivery mengecek authority terbaru. Control sedang pending tidak mengembalikan hak.
3. Database worker mengirim event `access.revoke` pada reserved control channel melalui **publisher HTTP API Reverb yang signed**. Client tidak dapat subscribe/menggunakan channel ini sebagai RPC.
4. Reverb membaca request/current epoch, menaikkan floor lokal secara monotonic, dan memblokir callbacks epoch lama. Jika admitted write masih aktif, control gagal/pending tanpa ACK.
5. Manager mencari physical socket yang dipin pada epoch lama, termasuk socket tanpa private membership tersisa. Private subscriptions lama diinvalidasi. Koneksi baru dengan epoch sah tidak ikut diakhiri.
6. Fence mendaftarkan daftar close yang ditunggu **sebelum** meminta disconnect. Native `disconnect` hanya meminta graceful drain/end; itu belum cukup untuk ACK.
7. Callback native Reverb saat transport benar-benar close memanggil manager `unsubscribeFromAll` dan `DeliveryFence::closed`. ACK database baru ditulis ketika seluruh transport lama tutup dan tidak ada admitted send.
8. Worker memeriksa row ACK actual. SDK HTTP 200 tanpa ACK menyebabkan pending/failure/retry, bukan konfirmasi sukses.

ACK adalah batas server bagi **private frame baru pada physical socket lama**. Frame yang sudah ditulis sebelum batas dapat tiba terlambat karena jaringan; tidak ada klaim menarik kembali byte yang sudah terkirim. Pengiriman private sah pada socket baru dengan signed epoch terbaru juga bukan kebocoran old socket.

## Kegagalan dan retry

| Kegagalan | Perilaku |
| --- | --- |
| Control 401 atau endpoint tidak terhubung | Request belum ACK; hak HTTP tetap terbaru; fresh private guard tetap menolak; operator melihat failure dan bisa retry |
| Authority DB tidak dapat dibaca | Keputusan `unavailable`; private payload tidak dikirim, public tetap berjalan; feedback tidak menghapus draft |
| Write sedang admitted | Floor sudah maju; ACK ditahan. Retry setelah write selesai melakukan invalidasi/close |
| Disconnect belum menghasilkan actual close | Tetap pending. Tidak ada timer yang memalsukan konfirmasi |
| ACK DB write gagal | Floor tidak dibuka kembali; request durable tetap unacked, retry dapat membangun konfirmasi ulang |
| SDK respons lebih cepat daripada callback close | Job dapat sementara mencatat pending/failure; actual-close callback membersihkannya saat benar-benar ACK |
| Duplicate/out-of-order control | Floor tidak turun; request lama dapat dikonfirmasi oleh fence versi lebih tinggi; row yang sudah ACK tidak mengulang transport |
| Scaling Reverb dinyalakan | Protected auth/delivery/control fail-closed; tidak berubah menjadi dukungan multi-node |

## Bukti invariant

[S2-15 dan S2-16](REVOCATION_RACE_TESTS.md) memakai barriers server dan actual WS frames. Final run memeriksa waktu transport close, durable ACK query, release closure lama, MessageSent metadata, dan event **baru sesudah ACK**. Unit test juga menolak SDK200-only ACK dan menunggu setiap koneksi, tetapi bukan satu-satunya bukti.

Tidak ada forced JavaScript logout, reload editor, atau vendor terminate endpoint yang dianggap sebagai mekanisme utama. Batas operasi single-node dan restart dicatat pada [REMAINING_RISKS](REMAINING_RISKS.md).
