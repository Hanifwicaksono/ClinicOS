# PR-01 S1 — Eksperimen dan bukti

Seluruh pendekatan di perbandingan telah dijalankan pada server nyata. **PASS assertion berarti hipotesis teruji, termasuk reproduksi bahwa pendekatan tertentu tidak aman bila berdiri sendiri.** Itu bukan PASS gate produksi.

## Kode yang dijalankan

| File eksperimen | Fungsi |
| --- | --- |
| [runtime.mjs](experiments/runtime.mjs) | Snapshot hash, cek port kosong, clone fixture F1 dummy/private storage, environment privat, start tiga proses |
| [Spike.php](experiments/Spike.php) | Driver signed auth, fresh authority/epoch, guarded ChannelManager/private channel, trace metadata, fault/control hook khusus spike |
| [control.php](experiments/control.php) | Mutasi melalui API Spatie dan StaffAccountService, pemanggilan SDK terminate, setup fixture, rollback |
| [browser.mjs](experiments/browser.mjs), [extended-cases.mjs](experiments/extended-cases.mjs) | Chrome/Playwright nyata, kontrol positif, raw wire observation, negative cases, editor/filter |
| [backend.php](experiments/backend.php), [backend.mjs](experiments/backend.mjs) | 15 assert-based checks pada backend/database clone; mutasi pemeriksaan di-rollback |
| [processes.mjs](experiments/processes.mjs) | Kelola hanya proses eksperimen; pause/restart worker untuk backlog; shutdown |
| [summarize.mjs](experiments/summarize.mjs) | Rekonsiliasi run, hasil terbaru per skenario, hash source, versi dan jumlah pending/failed job |

Mode eksperimen memisahkan baseline, signed-only, subscribe-only, producer, post-send, fingerprint, lease, dan guard/invalidate. Tidak ada mode ini didaftarkan dalam aplikasi produksi. Fault pause, hardcoded target fixture, control ping dan switch file adalah alat pembuktian; **jangan disalin sebagai infrastruktur produksi**.

## Matriks 36 skenario unik — hasil assertion terbaru

| ID | Yang benar-benar dieksekusi | Assertion | Kesimpulan keamanan / perilaku |
| --- | --- | --- | --- |
| S1-01 | Foreign clinic dan foreign users auth | PASS | Keduanya 403 |
| S1-B-role | Baseline role dicabut | PASS | Revokasi FAIL; dua socket lama menerima frame baru |
| S1-B-permission | Baseline role permission dicabut | PASS | Revokasi FAIL |
| S1-B-inactive | Baseline inactive lewat service | PASS | HTTP 401; socket lama masih menerima |
| S1-B-password | Baseline reset password lewat service | PASS | HTTP 401; socket lama masih menerima |
| S1-02 | SDK terminate, identity default kosong | PASS | Request diterima tetapi koneksi tidak teridentifikasi; FAIL standalone |
| S1-03 | Signed data + native terminate + auto-reconnect | PASS | Dua old socket ditutup; fresh own-user subscription masih diterima mode signed-only; FAIL standalone |
| S1-04 | Pemeriksaan hanya saat subscribe | PASS | Existing bindings tetap menerima; FAIL standalone |
| S1-05 | Producer memeriksa recipient users | PASS | Notification inactive terblokir, shared clinic masih bocor; FAIL standalone |
| S1-06 | Disconnect pada MessageSent | PASS | Frame pertama sudah keluar; FAIL standalone |
| S1-07 | Fingerprint state, cabut lalu restore tanpa event di tengah | PASS | Old binding hidup kembali pada state identik; tidak memenuhi invalidasi monotonik |
| S1-G-role | Guard sebelum send + role revoke + terminate 401 | PASS | Nol private baru di A/B, kontrol tetap menerima |
| S1-G-permission | Guard + role-permission revoke + terminate 401 | PASS | Nol private baru di A/B |
| S1-G-direct | Direct-only queue.view dicabut + terminate 401 | PASS | Nol private baru di A/B |
| S1-G-inactive | Guard + inactive + terminate 401 | PASS | Hook versi yang telah diperbaiki juga terverifikasi |
| S1-G-password | Guard + password + terminate 401 | PASS | Hook Eloquent awal gagal; retest R1 dan final berhasil |
| S1-08 | Epoch cabut/restore dan authorization baru | PASS | Binding epoch lama ditolak; reload/auth baru sah menerima |
| S1-09 | Rollback mutation role dan epoch | PASS | Hak/versi tidak berubah; old binding sah tetap menerima |
| S1-10 | Legacy unsigned bindings saat guard aktif | PASS | Old bindings tidak menerima frame private |
| S1-11 | Signed auth sebelum revoke, subscribe sesudah | PASS | Stale replay 4009, nol private baru |
| S1-12 | Tamper signed user_id | PASS | Native HMAC menolak 4009; browser sah lainnya tetap menerima |
| S1-13 | Native terminate dengan guard dan reconnect dua browser | PASS | Socket ID berubah; private subscribe revoked ditolak |
| S1-14 | Disconnect/reconnect pengguna sah | PASS | Fresh auth 200 dan dua private deliveries |
| S1-15 | Invalidasi server tanpa JS logout | PASS | Empat private bindings A/B dihapus, nol delivery |
| S1-16 | Signed lease 5 detik tanpa fresh check | PASS | Ada delivery selama lease, nol sesudah expiry; FAIL immediate revocation |
| S1-17 | broadcastToAll, broadcast dengan except, broadcastInternally | PASS | Masing-masing target menerima tiga sebelum revoke, nol sesudah; sender dikecualikan pada except |
| S1-18 | Query authority store benar-benar throw | PASS | Semua private fail-closed; public terus. Versi final pulih pada event berikut tanpa reload pengguna sah |
| S1-19 | Worker sungguhan dihentikan, job commit, revoke, worker dijalankan | PASS | Backlog hanya diterima kontrol saat dipublikasikan setelah revoke |
| S1-20 | Pause deterministik antara check dan send; local fence | PASS | Ada send sesudah commit dari pre-authorized frame; nol private pada event baru sesudah server ack |
| S1-21 | Event riil memicu refetch daftar queue | PASS | Isi filter, node, fokus dan selection 3–9 dipertahankan |
| S1-22 | Autosave 200 lalu password reset, autosave 401 | PASS | Teks/file pending dan URL editor tetap; tidak ada reload/logout JS untuk keamanan |
| S1-23 | Terminate endpoint tidak terjangkau, bukan hanya 401 | PASS | SDK membungkus ConnectException; guard tetap menolak |
| S1-24 | Dua tab berbagi context/session | PASS | Keduanya menerima sebelum, nol private sesudah revoke |
| S1-25 | Foreign tenant check-in dan subscribe kliniknya | PASS | Kontrol foreign menerima; tidak ada private cross-frame ke tenant utama |
| S1-26 | Kirim pusher:signin ke Reverb terpasang | PASS | Error 4200; koneksi sah tetap dapat menerima event berikutnya |
| S1-27 | Cancel appointment lewat endpoint asli setelah role target dicabut | PASS | Patient menerima AppointmentStatus + QueueStatus; dua browser target tidak menerima keduanya; whitelist delapan key AppointmentStatus |

Pemetaan setiap ID ke artifact terbaru tersedia di [summary.json](evidence/summary.json). Initial run tetap ada, tidak ditimpa. R1 canonical mengacu skenario yang sama, bukan tambahan coverage.

## Hitungan eksekusi dan kegagalan awal

| Run browser | PASS | FAIL |
| --- | ---: | ---: |
| smoke | 6 | 1 |
| main | 18 | 1 |
| extended | 14 | 0 |
| supplemental | 3 | 1 |
| transport-r1 | 1 | 0 |
| final-guard | 6 | 0 |
| post-format | 2 | 0 |
| notification-types | 1 | 0 |
| **Seluruh attempt, termasuk retest** | **51** | **3** |
| **Hasil terbaru per skenario unik** | **36** | **0** |

Tiga FAIL awal dipertahankan: (a) smoke menganggap terminasi melarang notification reconnect, tetapi callback own-user existing masih mengizinkannya; expectation diperbaiki menjadi bukti kelemahan pendekatan, (b) hook Eloquent eksperimen belum terdaftar saat password berubah; listener diperbaiki dan browser retest, (c) harness mengharapkan ConnectException langsung, sedangkan SDK membungkusnya dalam ApiErrorException; retest memeriksa cause serta nol frame. Tidak menyebut dua masalah pertama sekadar “harness hijau”.

Setelah Pint, satu startup CLI sempat error karena import Kernel berada setelah penggunaan alias. Tidak ada assertion backend yang berjalan pada invocation itu. Import dipindah ke atas; Pint PASS, backend 15/15 dan dua browser checks PASS. Catatan: [harness-incidents.json](evidence/harness-incidents.json).

Backend terbaru: **15 PASS / 0 FAIL** di [backend-checks.json](evidence/backend-checks.json): valid context; foreign clinic/user; unknown family; unsigned/empty/stale/missing authority; atomic active/password/clinic version hooks; role-permission fanout; inherited vs direct permission; syncRoles; rollback. Ini assert-based integration checks, **bukan full PHPUnit suite**. Skill testing-best-practices dicari tetapi tidak tersedia di skill lokal; pola assert eksplisit digunakan untuk spike tanpa dependency.

## Fault dan bukti

Fault authority menggunakan query tabel eksperimen yang tidak ada, menghasilkan exception database nyata pada jalur pre-send. Ini membuktikan handling kegagalan pembacaan authority, **bukan simulasi seluruh database down**. Frame private dijatuhkan ketika otorisasi tidak diketahui; tidak ada fallback stale-cache. Pada revisi final, unknown/read-failure tidak menghapus binding yang mungkin masih sah; event berikutnya setelah store pulih berhasil tanpa reload. Drop selama fault tidak direplay otomatis; UI perlu refetch snapshot saat pulih di S2.

Wire trace hanya memuat jenis aksi, waktu nyata, channel, hashed socket ID, user fixture ID/epoch, queue_id dan **nama key payload**. Tidak menyimpan auth signature, token, password, SOAP atau nilai medis. [editor-preserved.png](evidence/editor-preserved.png) adalah screenshot fixture sintetis. Tidak merekam HAR yang dapat membawa credential.

Queue akhir: **0 pending / 0 failed**. Trace mencakup 74 queue_id positif berbeda, bukan 74 skenario coverage; beberapa event adalah kontrol sebelum/sesudah dan retest. Tidak ada pengukuran throughput/latency production, TLS, scaling Redis atau proses bersamaan MySQL.

## Menjalankan ulang

Jangan menjalankan browser saja pada fixture yang telah terpakai: queue yang sudah check-in tidak dapat dipakai ulang. `node experiments/runtime.mjs` membuat clone baru dari fixture dummy F1 retained (path dependency tercatat dalam script), menjalankan isolation guard dan memeriksa kedua port kosong. Mode awal baseline, lalu browser default menjalankan kelompok main. Extended/supplemental dan retest memakai env proses `SPIKE_EXTENDED`, `SPIKE_SUPPLEMENTAL`, `SPIKE_QUEUE_START`, `SPIKE_RUN_SUFFIX`, `SPIKE_ONLY`; posisi queue harus mengikuti checkpoint dan nama artifact baru agar bukti lama tidak tertimpa. Runtime saat ini telah dihentikan setelah bukti selesai. Ini harness spike lokal, bukan runner CI portable; S2 perlu fixtures mandiri dalam suite aplikasi.
