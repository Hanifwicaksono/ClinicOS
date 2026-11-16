# Post-S2 — Investigasi failed notification job

## Kesimpulan

Penyebab kasus historis adalah **SQLite WAL + transaksi DEFERRED + dua koneksi/proses** yang membuat snapshot baca tidak dapat dinaikkan menjadi transaksi tulis. Failure terjadi pada reservasi queue, sebelum `DeliverClinicNotification::handle` berjalan. Pada Laravel terpasang, `DatabaseQueue::pop` menangkap error ini setelah menemukan job record dan menandai job gagal. Backoff `[60,300]` dan `tries=3` milik handler tidak menyelesaikan failure sebelum handler tersebut.

Perbaikan terkecil: `config/database.php` mengubah mode transaksi **SQLite** dari `DEFERRED` menjadi **`IMMEDIATE`**, memakai dukungan native Laravel/PHP terpasang. Write reservation diperoleh di awal transaksi, sehingga writer lain menunggu sebelum worker membuat snapshot. Tidak mengubah mode/constraint MySQL, vendor, retry count, backoff atau timeout produksi. Ini memperbaiki penggunaan SQLite multi-process; bukan klaim bahwa MySQL mempunyai global writer lock yang sama.

## Bukti asal

Failed job asli S2 dibaca secara read-only. Metadata stack disimpan pada [notification-original-stack.json](evidence/notification-original-stack.json): `PDOStatement::execute` → `Connection::update` → `DatabaseQueue::markJobAsReserved` → `marshalJob` → `pop` → worker. Ini bukan failure penyimpanan isi medis, pembacaan notification, atau HTTP publisher di dalam handler. Source Laravel `DatabaseQueue.php` terpasang diperiksa untuk memastikan catch/fail tersebut benar-benar tersedia.

Original database/evidence S2 tidak di-retry, dihapus atau ditulis ulang. Probe memakai salinan temporary. Notification ID lama dipertahankan ketika payload direplay; queue payload UUID baru dipakai agar failed-job logging tidak bertabrakan dengan histori copy yang sudah ada.

## Reproduksi deterministik

Worker artisan actual berhenti pada barrier tepat setelah SELECT jobs dalam transaksi. Proses PHP kedua memperbarui satu timestamp user fixture yang tidak terkait authority. Tidak ada loop volume, load/stress, atau sleep yang dipakai sebagai pengganti urutan transaksi.

| Mode | Urutan actual | Hasil |
| --- | --- | --- |
| SQLite WAL, override DEFERRED sebelum perbaikan | Worker SELECT → writer lain commit → worker reserve/update | Lock direproduksi; failed target, handler 0 kali |
| Recovery copy DEFERRED | `queue:retry` UUID target → worker actual sekali, tanpa barrier | Handler 1 kali, target selesai, jumlah notification tetap 203 |
| SQLite WAL, IMMEDIATE comparator | Worker SELECT dengan write reservation → writer lain tertahan → reader dilepas | Handler 1 kali, tidak ada failed target |
| SQLite WAL, **configured default setelah perbaikan** | Sama, tanpa override transaction mode pada probe | Writer tertahan, handler 1 kali, tidak ada failed target |
| MySQL dedicated, barrier yang sama | Queue row lock → writer user yang tidak terkait commit → worker lanjut | Handler 1 kali, tidak ada lock failure/failed target |

Semua pemeriksaan PASS pada [notification-probe.json](evidence/notification-probe.json). PASS DEFERRED berarti **berhasil mereproduksi bug**, bukan menilai perilaku gagal tersebut dapat diterima. [Probe sebelum config fix](evidence/notification-probe-before-config.json) dipertahankan.

Dalam seluruh run, jumlah notification tersimpan tetap **203**; probe tidak mengulangi booking/appointment/clinical mutation. `queue:retry` menghapus failed-row target hanya pada salinan recovery melalui mekanisme framework normal; histori original S2 tetap utuh. Tidak ada silent loss database notification: record sudah tersimpan transaksional sebelum broadcast job. Sebelum recovery, realtime delivery memang belum terlaksana dan tercatat sebagai failure.

## Deduplikasi dan batas delivery

`ClinicNotificationService` menyimpan satu notification UUID dan enqueue dalam transaksi bisnis. `DeliverClinicNotification` membaca record existing, tidak membuat notification baru pada retry. Existing backend test `ClinicNotificationDeliveryTest` memeriksa repeated delivery, transport retry dan rollback; ikut full suite kedua database.

Pengujian browser MySQL tambahan `P-05` mereplay actual delivery job dengan notification ID yang sama: raw WS boleh terduplikasi pada sistem queue at-least-once, sedangkan client existing menduplikasi berdasarkan ID sehingga satu action/counter dan satu record database. Hasil actual dirujuk pada [MYSQL_WEBSOCKET_FENCE_REPORT](MYSQL_WEBSOCKET_FENCE_REPORT.md), bukan dijanjikan exactly-once network transport.

Masih mungkin terjadi MySQL row deadlock/lock timeout atau network failure yang berbeda. Probe ini tidak membuktikan semua contention/crash aman dan tidak mengubah kebijakan retry untuk kasus yang belum direproduksi. SQLite IMMEDIATE juga tidak membuat SQLite menjadi multi-writer; transaksi panjang tetap dapat menyebabkan timeout. Batas tersebut tetap dicatat pada [REMAINING_RISKS](REMAINING_RISKS.md).
