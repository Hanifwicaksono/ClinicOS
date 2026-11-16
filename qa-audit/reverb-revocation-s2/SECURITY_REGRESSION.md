# PR-01 S2 — Security regression

**Run final: 26 PASS, 0 FAIL, 0 BLOCKED, 0 NOT TESTED dalam matrix browser S2 terdaftar di bawah.** Batas di luar matrix tetap dicatat pada bagian akhir; angka ini bukan persentase coverage seluruh produk.

Fixture terisolasi baru dari data dummy F1, private storage/cache/build dan app key testing sendiri. Browser actual memakai Playwright/Chrome, Reverb actual, Pusher/Echo actual dan database queue worker terpisah. Mutasi role/permission yang belum mempunyai UI memakai service/model aplikasi pada fixture; deactivate, reset password, retry, logout dan editor memakai UI nyata. Publication berasal dari endpoint workflow aplikasi. SQL hanya digunakan untuk fixture/fault injection dan verifikasi.

| ID | Role, aksi, state yang diuji | Hasil |
| --- | --- | --- |
| S2-01 | Dokter dua browser, receptionist/pasien sah; actual signed delivery; foreign clinic auth 403 | PASS |
| S2-role | Revoke role committed; dua old sockets tidak mendapat private event baru; HTTP auth ditolak; ACK actual | PASS |
| S2-permission | Revoke inherited queue permission; queue auth ditolak; old sockets ditutup; konteks baru sah dapat menerima own notification | PASS |
| S2-direct | Revoke direct permission; authority segar dan ACK old sockets | PASS |
| S2-inactive | Owner deactivate lewat UI; JSON 401; queue dan user-notification lama terputus | PASS |
| S2-password | Owner reset password lewat UI; session HTTP lama invalid; epoch/socket lama dicabut | PASS |
| S2-07 | Authorized disconnect/reconnect dengan sesi HTTP sah; delivery pulih | PASS |
| S2-08 | Reconnect sesudah revoke tidak memperoleh subscription private terlarang | PASS |
| S2-09 | Signature stale pada socket yang sama, data tampered dan replay lintas socket ditolak (4009) | PASS |
| S2-10 | Dua tab satu sesi ikut dicabut, bukan hanya tab yang memulai aksi | PASS |
| S2-11 | Reactivation memakai epoch baru; binding lama tetap ditolak; fresh login sah berhasil | PASS |
| S2-12 | Worker benar-benar dipause, event tertunda, commit revoke, worker dilanjutkan; old private delivery ditolak bahkan sebelum ACK | PASS |
| S2-13 | Signed control 401; pending/failure terlihat di UI admin; retry ACK tanpa mengembalikan hak HTTP | PASS |
| S2-14 | Query authority missing-table fault; nol private payload selama fault; public tetap live; valid subscription pulih tanpa reload | PASS |
| S2-15 | Post-auth callback ditahan; revoke/transport close/ACK; release tertolak; event baru sah tetap terpublikasi | PASS |
| S2-16 | Admitted write ditahan; ACK pending sampai write selesai dan transport close | PASS |
| S2-17 | Rollback security mutation mempertahankan hak dan socket sah | PASS |
| S2-18 | Logout Volt nyata, retained physical socket; hanya scope sesi logout ditolak, browser lain tetap menerima; epoch tetap | PASS |
| S2-19 | Filter, DOM input node dan caret bertahan saat realtime refetch | PASS |
| S2-20 | Autosave actual 200 sebelum reset, 401 sesudah ACK; unsaved RME, pending photo dan URL tetap | PASS |
| S2-21 | Tenant lain menerima queue miliknya, tidak menerima private queue klinik target | PASS |
| S2-22 | Guest public display tetap menerima event enam field nonmedis sesudah revokasi staf | PASS |
| S2-23 | Actual AppointmentStatus dan QueueStatus notification; pasien sah menerima, revoked old sockets nol | PASS |
| S2-24 | Control endpoint refused; fresh delivery tetap menolak; request durable bisa retry sampai ACK | PASS |
| S2-25 | Grant sesi expired ditolak; explicit retry reauthorize active Pusher subscription tanpa reload | PASS |
| S2-26 | HMAC native yang valid tetapi tanpa signed identity ditolak; reserved control tidak dapat disubscribe client | PASS |

Bukti lengkap: [browser-verified-final.json](evidence/browser-verified-final.json), [ringkasan kasus](evidence/security-case-summary.json), [race](evidence/race-summary.json), [control failure UI](evidence/control-failure-visible.png), [editor](evidence/editor-preserved.png), [public display](evidence/public-display.png).

## Regresi backend dan client

Seluruh **27 test backend revokasi baru lulus pada SQLite dan MySQL**. Mencakup epoch, mutation fan-out, konfigurasi queue, signed context, session tombstone termasuk auth/logout race, native manager binding, scope tenant retry, SDK200 tanpa ACK, dan actual-close gate. Lima unit JS baru lulus untuk native auth errors, pemisahan sesi/koneksi, bounded auth retry dan form preservation. Hasil unit tidak menggantikan bukti transport di atas.

## Informasi medis dan isolasi

Tidak ada isi RME/foto dipublikasikan sebagai probe. Trace menyimpan event name, channel, ID fixture, nama field payload dan hash socket; tidak menyimpan auth signature, token booking, session ID atau payload medis. Public whitelist: `queue_id`, `display_number`, `status`, `doctor_id`, `queue_date`, `updated_at`. Private own-notification tidak menjadi bypass policy medis. Bukti screenshot memakai pasien/staf dummy.

## Yang belum diuji

Multi-node, transport WS pada MySQL, proses crash/restart tepat di tengah fence, dan fault khusus kegagalan **write ACK database** belum dieksekusi secara end-to-end. Failure-read DB serta control transport sudah dieksekusi. Code path kegagalan ACK mempertahankan request unacked/floor tertutup, tetapi itu tidak dilabeli sebagai fault-injection PASS. Batas logout per sesi dan frame yang dikirim sebelum ACK dijelaskan pada [AUTHORIZATION_VERSION_REPORT](AUTHORIZATION_VERSION_REPORT.md).
