# PR-01 S2 — Authorization version dan mutation boundary

## Kontrak tersimpan

`users.realtime_auth_version` dimulai pada 1. Security save membaca ulang epoch dengan row lock, lalu menyimpan `epoch + 1` dalam UPDATE yang sama dengan perubahan security. Epoch bukan input mass assignment; upaya menurunkannya melalui model existing ditolak. Model stale tidak menimpa epoch terbaru. Setelah outer rollback, model yang dipakai kembali juga membaca epoch database sebelum save berikutnya.

Perubahan ACL melalui model yang dikonfigurasi membungkus pivot writes, lock user terdampak, pembacaan authority sebelum/sesudah, bump, UUID revocation request, dan database queue job dalam transaksi yang sama. Job menggunakan database connection bisnis yang sama dan `beforeCommit`; consumer tidak melihat row uncommitted. Konfigurasi driver/koneksi queue yang memutus atomisitas ditolak dengan rollback. Commit baru menjadikan hak dan revocation terlihat. Rollback mengembalikan semuanya dan membersihkan metadata permission lokal.

Initial role grant dari akun roleless pada epoch 1 menaikkan epoch tetapi tidak membuat request terminasi/ACK palsu: akun tersebut belum mungkin memperoleh private authorization. Grant berikutnya, revoke dan regrant tetap menaikkan epoch; ABA tidak menghidupkan signature lama.

## Inventaris mutation paths

| Jalur | Boundary actual | Hasil |
| --- | --- | --- |
| `StaffAccountService::create/update`, deactivate/reactivate, reset password admin | `User::save`, `assignRole` | Account fields dan epoch atomik; service tidak diduplikasi |
| Change/reset password auth/profil existing | Eloquent `User::save/update` | Semua sesi private epoch lama tidak sah; HTTP AuthenticateSession tetap berlaku |
| Perubahan email/verifikasi, clinic, account_type, is_active; delete | Override User save/delete | Signed identity lama tidak berlaku; delete rollback tidak membatalkan user sah |
| `User::assignRole/removeRole/syncRoles` | `AccessMutations::change` | Perubahan membership termasuk perubahan dengan effective permission yang sama tetap tercatat |
| `User::givePermissionTo/revokePermissionTo/syncPermissions` | Boundary yang sama | Direct permission terpisah dari inherited permission; bump tetap terjadi ketika graph berubah |
| Configured Role: grant/revoke/sync permissions, assign/remove/sync models, rename/save/delete | `App\Models\Role` | Fan-out ke user terdampak; tenant tidak menjadi batas definisi role global |
| Configured Permission: assign/remove/sync roles, rename/save/delete | `App\Models\Permission` | Direct dan inherited members tercakup |
| Registrasi pasien/owner dan Google login | `User::assignRole(Role::firstOrCreate(...))` | Tiga caller masih mengimpor base Spatie Role untuk lookup/create saja; assignment tetap lewat User wrapper |
| Seeder role/permission dan demo | Configured Role/Permission, User syncRoles | Tidak ada seeder dijalankan pada database pengguna |
| Logout biasa | Logout listener → durable session tombstone | Hanya sesi logout; epoch user tidak berubah dan browser lain tetap sah |
| Nonsecurity name/phone/remember token save, noop | Tidak bump | Tidak memutus sesi lain tanpa alasan |

Tidak ditemukan first-party raw SQL/pivot mutation ACL pada workflow aplikasi. **Raw SQL, relationship pivot writes langsung, atau pemanggilan base Spatie model untuk mutasi di luar boundary bukan API yang dijamin.** Penulis baru wajib memakai model/API yang dikonfigurasi. S2 tidak memasang trigger database dan tidak menambahkan permission editor.

## Signed binding dan scope sesi

SDK Pusher existing menandatangani socket ID, channel, dan custom `channel_data`. Data server memuat `user_id`, `version`, `clinic_id`, `app_id`, `channel`, `session_scope`. Raw session ID tidak dikirim; scope adalah HMAC dengan app key existing. Signature native tanpa binding juga ditolak. Data dari form bukan authority.

Manager mem-pin user/version/session pada physical socket pertama dan mempertahankannya sampai transport close, termasuk ketika semua private channel di-unsubscribe. Identitas tidak dapat di-upgrade di socket lama; browser boleh memperoleh socket baru dengan authority baru yang sah.

Grant sesi tersimpan dengan TTL mengikuti session lifetime; request HTTP aktif memperpanjang grant existing yang belum expired/revoked. Logout menulis tombstone **walaupun grant pertama belum dibuat**. Delayed authorization sebelum logout tidak dapat menghidupkan scope lagi. Setelah expiry, fresh authorized HTTP auth dapat memperpanjang scope yang tidak dicabut melalui explicit retry. Tidak ada auto-revival tombstone.

Logout per sesi memiliki kontrak pemeriksaan delivery/auth selanjutnya, bukan user-wide termination ACK. Frame yang sudah admitted sebelum logout tidak dapat ditarik kembali. User-wide security mutation memakai fence/ACK yang dijelaskan terpisah.

## Bukti

- 27 test baru dalam [RealtimeRevocationTest](../../tests/Feature/RealtimeRevocationTest.php); ringkasan full suites di [FUNCTIONAL_REGRESSION](FUNCTIONAL_REGRESSION.md).
- Browser `S2-role`, `permission`, `direct`, `inactive`, `password`, `09`, `10`, `11`, `17`, `18`, `25`, `26`: PASS, [run final](evidence/browser-verified-final.json).
- Hasil lock dua proses dan rollback MySQL final dirujuk pada [REVOCATION_RACE_TESTS](REVOCATION_RACE_TESTS.md). Transport WebSocket actual diuji pada SQLite, bukan diklaim sebagai transport MySQL.
