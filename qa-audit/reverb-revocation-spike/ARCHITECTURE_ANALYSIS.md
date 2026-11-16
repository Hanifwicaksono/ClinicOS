# PR-01 S1 — Analisis arsitektur

Tanggal eksekusi: 15 November 2026. **Eksperimen terisolasi; bukan implementasi produksi. PR-01 OPEN.**

## Acuan dan versi

Meneruskan [NEW_FINDINGS](../production-readiness/NEW_FINDINGS.md), [REALTIME_TEST_REPORT](../production-readiness/REALTIME_TEST_REPORT.md), serta [spesifikasi](../product-completeness/implementation-plan/FEATURE_SPECIFICATIONS.md), [fase](../product-completeness/implementation-plan/IMPLEMENTATION_PHASES.md) dan [keputusan](../product-completeness/implementation-plan/DECISIONS_REQUIRED.md). PG-17/PG-18 tidak dibuka kembali. PR-01/T dan usulan acknowledgement lima detik **belum menjadi kebijakan yang disetujui**.

Versi di lock file dan implementasi lokal yang diperiksa: PHP CLI 8.5.5, Laravel 13.34.0, Reverb 1.12.0, SDK pusher-php-server 7.3.0, Spatie Permission 8.3.0, Livewire 3.8.10, Echo 2.6.1, pusher-js 8.6.0. Browser aktual Chrome 158.0.8127.36. Rincian mesin ada di [summary.json](evidence/summary.json). Tidak mengasumsikan API dari versi lain.

## Jalur data existing

1. Browser login memakai session HTTP. Echo/Pusher membuka socket tersendiri dan memperoleh `socket_id`.
2. Subscribe private memanggil `/broadcasting/auth` dengan session dan CSRF. Callback channel mengotorisasi **saat permintaan tersebut**, lalu Laravel mengembalikan signature untuk pasangan socket/channel.
3. Reverb memverifikasi signature pada `pusher:subscribe` dan menyimpan subscription dalam registry proses. Tidak ada keterikatan otomatis antara umur session HTTP dan subscription itu.
4. Check-in melalui QueueWorkflowService menyimpan perubahan transaksional. ClinicNotificationService memasukkan `BroadcastClinicQueueUpdate` dan `DeliverClinicNotification` ke database queue di transaksi tersebut; worker terpisah mengirim ke endpoint publikasi Reverb. QueueUpdated juga memiliki kontrak after-commit. Tidak memublikasikan event klinis dari transaksi yang belum committed.
5. Reverb meneruskan payload ke subscription yang telah terdaftar. Implementasi default tidak membaca kembali status/role/permission pengguna ketika mengirim.

Worker eksperimen benar-benar menjalankan kedua job aplikasi tersebut. Tidak menggunakan fake broadcaster atau `sync` sebagai pengganti worker. Pembaruan authority dilakukan pada fixture; aksi check-in memakai authenticated browser fetch ke endpoint aplikasi sebenarnya. Ini pengujian transport/workflow, bukan audit ulang tombol UI.

## Inventaris seluruh private channel ClinicOS

| Keluarga wire | Authorization existing | Publisher / penerima | Batas |
| --- | --- | --- | --- |
| `private-clinic.{clinicId}.queues` | `user.clinic_id` sama, `queue.view`, salah satu Clinic Admin/Receptionist/Doctor | QueueUpdated; staf klinik | Callback tidak berulang saat delivery |
| `private-users.{id}` | ID user sama | Broadcast notification; User::receivesBroadcastNotificationsOn; QueueStatusNotification dan AppointmentStatusNotification | Callback kepemilikan sendiri tidak mengecek role/active; middleware HTTP dapat menolak session inactive/berubah password, tetapi tidak socket lama |

Sumber: [routes/channels.php](../../routes/channels.php), [QueueUpdated](../../app/Events/QueueUpdated.php), [User](../../app/Models/User.php), [ClinicNotificationService](../../app/Services/ClinicNotificationService.php), [DeliverClinicNotification](../../app/Jobs/DeliverClinicNotification.php).

`clinic.{id}.queue-board` adalah **public** dengan enam field nonmedis. Tetap public; penerimaannya tidak dihitung sebagai kebocoran private. Tidak ditemukan keluarga presence/private-cache yang digunakan aplikasi saat ini. Katalog family private ini berasal dari pencarian source, bukan hanya daftar route.

## Extension point yang terverifikasi di implementasi terpasang

| Titik | Implementasi lokal | Bukti / implikasi |
| --- | --- | --- |
| Driver broadcasting aplikasi | BroadcastManager::extend, PusherBroadcaster::validAuthenticationResponse/getPusher | Driver `spike` hanya di private bootstrap eksperimen; parent driver tetap memakai SDK Reverb existing |
| Signed identity | SDK authorizeChannel(channel, socket, custom_data); pusher-js Channel.subscribe meneruskan channel_data | Reverb InteractsWithPrivateChannels memasukkan data itu dalam HMAC. Identitas tidak dipercaya tanpa signature. S1-03/12 |
| Identitas registry | Channel::subscribe, ChannelConnection::data, ArrayChannelManager::connections | user_id pada signed channel_data terlihat oleh terminate endpoint. Manager memilih wrapper dengan user_id bila socket punya public + private channel |
| Terminasi server | Factory memasang POST `/apps/{appId}/users/{userId}/terminate_connections`; UsersTerminateController; SDK terminateUserConnections | Ada dan bekerja. HTTP 200 juga mungkin tanpa satu pun koneksi yang cocok. User-wide, bukan pemilihan berdasarkan versi. S1-02/03/13 |
| Factory channel | Kontrak ChannelManager; ArrayChannelManager::findOrCreate; ChannelBroker::create | ChannelBroker memakai `new PrivateChannel` langsung. Mengikat class PrivateChannel saja tidak cukup. Subclass manager eksperimen benar-benar membuat guarded private channel |
| Pre-send guard | Channel::broadcast, broadcastToAll, broadcastInternally dan subscription->send | Aplikasi dapat menyediakan subclass melalui manager. Ketiga jalur diuji nyata S1-17; publikasi worker membuktikan broadcast normal |
| MessageSent | Connection::send mengirim frame **sebelum** dispatch event | Cocok untuk bukti waktu; terlambat sebagai batas keamanan. S1-06 membuktikan frame pertama sudah keluar |
| MessageReceived / ping | Event setelah handler protokol selesai | Dipakai **hanya di eksperimen** untuk control acknowledgement, invalidasi dan memilih jalur broadcast. Bukan endpoint administrasi produksi yang diusulkan |
| Pusher sign-in | EventHandler hanya menangani established, subscribe, unsubscribe, ping, pong | `pusher:signin` benar-benar diuji: error 4200. Jangan menganggap Echo.signin dapat dipakai tanpa perubahan protokol |

Source vendor: `vendor/laravel/reverb/src/Protocols/Pusher/{EventHandler,EventDispatcher,Managers/ArrayChannelManager,Channels/Channel,Channels/ChannelBroker}.php`, `Servers/Reverb/Factory.php`, `Protocols/Pusher/Http/Controllers/UsersTerminateController.php`; broadcaster framework dan SDK terpasang. Ini hasil pembacaan source dan eksekusi, bukan janji stabilitas API internal lintas upgrade.

## Mutasi akses dan cache

[StaffAccountService::update](../../app/Services/StaffAccountService.php) mengunci user/clinic dalam transaksi, mengubah active/password/remember_token, sinkronisasi profil dokter, dan menulis audit. Ia tidak mengakhiri socket. AuthenticateSession memeriksa perubahan password pada HTTP berikutnya; CheckUserIsActive memeriksa akun aktif.

Spatie menyimpan role dan direct permission pada pivot terpisah. Mencabut direct `queue.view` tidak menghapus hak yang masih diwarisi dari role: dibuktikan backend check. Eksperimen permission menggunakan pencabutan pada role Doctor atau setup direct-only agar pencabutan efektif, sementara kontrol Receptionist tetap berhak.

`config/permission.php` existing menetapkan `events_enabled=false`. Provider eksperimen mengaktifkannya **di runtime privat saja** dan mendengar attach/detach role/permission, termasuk fanout perubahan permission Role ke semua anggotanya. Query guard membaca user/pivot/version langsung, sehingga tidak mengandalkan cache Spatie dalam proses panjang.

Hook Eloquent pertama eksperimen dipasang sebelum DatabaseServiceProvider menginisialisasi model dispatcher; akibatnya password tidak menaikkan versi. Bukti gagal dipertahankan. Listener dispatcher `eloquent.updated: User` memperbaiki eksperimen dan retest berhasil. Di S2 provider harus didaftarkan pada urutan boot yang tepat, mutation/version harus atomik, dan caller password broker/profil/staf harus diuji; jangan menyalin hook tanpa verifikasi.

## Ruang eksperimen

Semua kode baru di [experiments](experiments/). Provider itu hanya diregistrasikan dalam bootstrap di temporary directory, tidak dalam bootstrap aplikasi. SQLite/WAL dan storage merupakan clone fixture sintetis F1 final, dengan APP_KEY baru yang tidak dilaporkan; Reverb dan worker sungguhan memakai environment privat yang sama. Tidak ada dependency baru atau edit vendor. Hash 1.094 file existing tetap identik, termasuk F1, `.env` (hash saja), schema dan laporan historis: [source-integrity.json](evidence/source-integrity.json).

Carbon dibekukan 14 November untuk tanggal fixture. Barrier, lease, wire trace dan browser memakai waktu nyata 15 November (`microtime(true)`/Date.now); timestamp klinis beku bukan alat menentukan urutan revokasi.
