# PR-01 S2 — Perubahan implementasi

Tanggal: 15 November 2026. Scope: satu proses/node Reverb. Implementasi belum di-deploy dan migration tidak dijalankan pada database pengguna. Acuan S1 dipertahankan di [direktori spike](../reverb-revocation-spike/). PG-17/PG-18 tidak diubah.

## Akar masalah dan perubahan

Reverb menyimpan private subscription setelah authorization HTTP awal. Session, perubahan password, role, permission, dan status akun tidak otomatis membatalkan registry tersebut. Terminasi SDK saja juga tidak membuktikan write buffer telah tertutup. S2 menambahkan versi authority yang transaksional, signed identity, pemeriksaan authority segar sebelum private delivery, fence pada write terakhir, invalidasi subscription, dan ACK setelah transport lama benar-benar ditutup.

HTTP tetap memakai middleware/policy existing. Kegagalan control tidak memulihkan hak HTTP. Operator menerima status terpisah untuk revokasi transport.

## Seluruh file aplikasi yang disentuh S2

| Area | File | Perubahan |
| --- | --- | --- |
| Model | `app/Models/User.php` | Security save/delete dan enam API mutasi role/permission dibungkus transaksi; epoch dikelola server |
| Model baru | `app/Models/Role.php`, `app/Models/Permission.php` | API mutasi Spatie yang dikonfigurasi masuk boundary transaksi dan fan-out epoch |
| Model baru | `app/Models/RealtimeRevocation.php` | Durable request/ACK/failure history |
| Service baru | `app/Services/Realtime/AccessMutations.php` | Lock, perbandingan authority, epoch, request dan database job atomik |
| Service baru | `app/Services/Realtime/AuthorizationContext.php` | Pembacaan authority tanpa cache; seluruh family private actual |
| Service baru | `app/Services/Realtime/SessionGrants.php` | Grant sesi durable, TTL dan logout tombstone |
| Service baru | `app/Services/Realtime/Broadcaster.php` | Signed identity melalui SDK Pusher terpasang |
| Service baru | `app/Services/Realtime/ChannelManager.php` | Factory channel runtime, pin identitas socket, callback close |
| Service baru | `app/Services/Realtime/PrivateChannel.php` | Verifikasi subscribe dan guard seluruh delivery private |
| Service baru | `app/Services/Realtime/DeliveryFence.php` | Floor epoch, admitted writes, pending ACK dan actual-close gate |
| Service baru | `app/Services/Realtime/ControlChannel.php` | Signed server publisher control; client tidak dapat subscribe |
| Provider baru | `app/Providers/RealtimeServiceProvider.php` | Binding kontrak Reverb dan broadcaster, logout/rollback listeners |
| Job baru | `app/Jobs/RevokeRealtimeAccess.php` | Retry dan validasi durable ACK, error disanitasi |
| Job existing | `app/Jobs/DeliverClinicNotification.php` | Penerima inactive/unverified/tidak berhak tidak menerima broadcast baru; histori tersimpan tetap ada |
| Middleware | `app/Http/Middleware/CheckUserIsActive.php` | Auth private akun inactive mengembalikan JSON 401, bukan HTML login 200 setelah redirect |
| Middleware baru | `app/Http/Middleware/RefreshRealtimeSession.php` | Memperpanjang grant sesi aktif pada request; kegagalan renewal tidak mengubah respons write klinis yang sudah commit |
| Bootstrap | `bootstrap/app.php`, `bootstrap/providers.php` | Registrasi middleware/provider |
| Konfigurasi | `config/permission.php` | Model ACL aplikasi dan cache metadata request-local (`array`) |
| Seeder | `database/seeders/RoleAndPermissionSeeder.php` | Memakai model ACL yang dikonfigurasi |
| Routing | `routes/channels.php`, `routes/web.php` | Fresh private authorization dan endpoint retry tenant-scoped |
| Controller | `app/Http/Controllers/Admin/StaffController.php` | Status revokasi terakhir dalam tenant |
| Controller baru | `app/Http/Controllers/Admin/RealtimeRevocationController.php` | Retry oleh admin berwenang, termasuk subscription read-only |
| UI | `resources/views/admin/staff/index.blade.php` | Pending, failed, confirmed, dan retry |
| UI | `resources/views/layouts/app.blade.php` | Feedback akses/koneksi dan retry tanpa menghapus draft |
| JS baru | `resources/js/realtime-access.js` | Authorizer status-aware, bounded automatic retry, explicit reauthorization |
| JS existing | `resources/js/echo.js`, `resources/js/realtime.js` | Integrasi feedback dan penghentian refetch tidak berhak; filter/caret dipertahankan |

## Migration additive

1. `database/migrations/2026_11_15_091253_add_realtime_authorization_version_to_users.php`: `users.realtime_auth_version`, default 1.
2. `database/migrations/2026_11_15_091254_create_realtime_revocations_table.php`: request UUID, user/tenant/version, node ACK, timestamps dan sanitized error; unique `(user_id, auth_version)`. Tanpa cascade yang menghapus histori ketika user dihapus.
3. `database/migrations/2026_11_15_102907_create_realtime_session_grants_table.php`: HMAC scope, user, expiry, revoked tombstone. Tidak menyimpan session ID mentah.

Ketiganya hanya diaplikasikan pada fixture disposable. Tidak ada migration klinis/komersial, dependency baru atau vendor patch.

## Test dan artefak

- Baru: `tests/Feature/RealtimeRevocationTest.php` (27 test), `tests/realtime-access.test.mjs` (5 test).
- Harness S2 seluruhnya di `qa-audit/reverb-revocation-s2/tests/`: bootstrap/fixture, fault injection, barriers, browser, MySQL, regression runners dan ringkasan evidence. Tidak dimuat bootstrap produksi.
- Build Vite workspace dan build private testing berhasil; output build merupakan artefak turunan. `bootstrap/cache/services.php` juga diregenerasi oleh Laravel, bukan perubahan domain.
- [source-integrity.json](evidence/source-integrity.json) mencatat perubahan terhadap snapshot sesi, bukan terhadap Git HEAD yang sudah memuat pekerjaan terdahulu. Scaffold migration epoch, migration/model revocation dan test sempat dibuat sebelum snapshot; semuanya diklasifikasikan sebagai pekerjaan S2 baru.
- Hash PG-17/18, seluruh test lama yang masuk snapshot, vendor, manifests, lock files, `.env`, serta laporan/eksperimen S1 tetap sama. Tidak ada reset checkout, commit, push atau deploy.
- Skill `testing-best-practices` dicari tetapi tidak tersedia pada katalog/path lokal; test mengikuti konvensi PHPUnit/JS existing dan aturan AGENTS. Tidak mengklaim skill tersebut telah dibaca. Tidak ada perubahan source Volt.

Rincian hasil aktual: [FUNCTIONAL_REGRESSION](FUNCTIONAL_REGRESSION.md) dan [SECURITY_REGRESSION](SECURITY_REGRESSION.md).
