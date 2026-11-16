# PR-01 S1 — Usulan implementasi S2 untuk dievaluasi

**Dokumen usulan, bukan izin mulai S2. Tidak ada perubahan produksi/migration yang diterapkan. PR-01 OPEN.**

## Keputusan teknis yang didukung bukti

Gunakan identity/version yang ditandatangani broadcaster aplikasi, guarded private channel melalui ChannelManager aplikasi, fresh eligibility saat subscribe dan setiap delivery, serta invalidasi/terminasi server setelah commit. Pertahankan Echo/Pusher, Blade/Alpine, database worker dan channel nonmedis existing. Tidak perlu vendor patch, package baru, framework baru atau permission editor.

Rekomendasi batas efektif: administrator mendapat status perubahan akses tersimpan, lalu konfirmasi revokasi realtime setelah server yang relevan mengakui epoch baru dan melewati pengiriman yang telah diotorisasi sebelumnya. Bila control API gagal, HTTP tetap dicabut, guard tetap fail-closed dan status realtime pending/failed dengan retry; jangan mengaktifkan akun kembali.

S1-20 membuktikan read-check-send biasa dapat mengirim frame sesudah commit jika izin diperiksa sebelum commit. Jika keputusan PR-01/T mensyaratkan **nol send sesudah database commit**, S2 memerlukan serialization/fencing yang lebih kuat antara mutation dan send, bukan sekadar menyebut commit sebagai barrier. Alternatif itu belum dibuktikan pada MySQL. Jika memilih acknowledgement sebagai batas efektif, frame yang telah dikirim sebelum ack harus diklasifikasikan demikian walaupun baru tiba di browser kemudian.

## Lingkup perubahan produksi yang diperlukan

| Area / caller | Perubahan yang diusulkan | Invariant dan risiko |
| --- | --- | --- |
| users / migration additive | `realtime_auth_version` durable, default valid untuk existing/new users; integer diperlakukan sebagai string di signed data | Tidak menghapus histori/session/medical. Versi hanya naik; restore hak tidak menghidupkan epoch lama. Table spike tidak dipindah apa adanya |
| Broadcaster/provider aplikasi | Signed channel_data untuk **semua** private channel, user_id berasal dari authenticated user dan versi durable; deny context hilang | Native HMAC mencakup data. Jangan menerima user/tenant/version dari input sebagai authority. HTTP auth dan subscribe guard harus konsisten |
| Reverb ChannelManager/private channel | Bind app-owned manager yang benar-benar dipakai factory; guard subscribe, normal/all/except/internal send; unknown authority menolak send | Pelihara except, internal event, public behavior, registry dan cleanup; jangan memfilter registry hingga native terminate tidak menemukan socket. Jangan memanggil parent broadcast secara rekursif melalui override broadcastToAll |
| Shared authorization context | Fresh user, active, tenant dan permission dari DB authoritative; version compare; keputusan deny definitif dibedakan dari read unavailable | Jangan memakai cache model/Spatie proses panjang sebagai satu-satunya authority. Tidak ada fallback allow ketika DB error |
| StaffAccountService + user password/active/clinic callers | Security change dan version increment atomik; after-commit cleanup/request acknowledgement | Observer yang menulis versi setelah autocommit password memiliki crash gap. Gunakan version pada User UPDATE yang sama atau transaksi eksplisit yang mencakup keduanya; uji urutan boot provider |
| Spatie mutations | Attach/detach/sync role/direct permission dan perubahan permission Role menaikkan versi anggota; aktifkan package events bila dipakai, bungkus pivot/version atomik | Role permission dapat memengaruhi user lintas tenant secara sah. Jangan hanya menangkap User::updated; pivot tidak men-trigger itu. Direct revoke bukan pencabutan hak inherited |
| Password broker / profile password / staff reset | Semua jalur model save harus masuk security-version boundary | Reset via StaffAccountService diuji S1; email broker/profil end-to-end wajib S2, bukan dianggap otomatis verified |
| Jobs/notification eligibility | Periksa kembali recipient aktif dan hubungan/role relevan pada DeliverClinicNotification; jangan hanya membership user_id | Guard transport tetap utama pada shared queues. QueueStatus dan AppointmentStatus serta semua penerima harus dicakup |
| Revocation control dan retry | Gunakan native SDK terminate sebagai cleanup; simpan request/status/epoch secara durable, retry idempotent setelah commit dan ack server terautentikasi | Native terminate user-wide, tidak epoch-selective. Stale retry harus dicegah mengganggu login baru; HTTP200 tidak membuktikan socket sudah ditutup. Spike control ping bukan endpoint produksi |
| realtime.js / editor UI | Informasi reconnect/access revoked/pending yang tepat; refetch saat pulih; jangan reload/clear editor/file untuk enforcement | Definitive invalidation tanpa disconnect dapat membuat Pusher mengira subscribed. Pulihkan pengguna sah melalui reauth/snapshot, pertahankan filter/fokus dan unsaved editor |

Existing queue job/audit dapat menjadi dasar durable retry/status, tetapi kesesuaian histori/ack correlation perlu dirancang sebelum memilih tambahan schema di luar column versi. Jangan menambah outbox atau tabel lain hanya karena spike menggunakan table tersendiri.

## Alur yang diharapkan

1. User sah membuka halaman, memperoleh signed identity/version melalui authorization existing yang diperkuat. Reverb memeriksa HMAC **dan fresh context** sebelum menambah registry.
2. Admin/owner mengubah akses lewat jalur yang sah; role/permission juga dapat berubah melalui API Spatie yang sah tanpa UI baru. Mutation + epoch atomic; rollback tidak menerbitkan revocation final.
3. Setelah commit, durable control meminta cleanup/ack. HTTP memakai authority baru segera. Setiap delivery private membaca authority terbaru; binding lama tidak mendapat event baru meskipun kontrol terminate gagal.
4. Server mengakui epoch setelah boundary in-flight yang disepakati. Hanya saat bukti server sesuai kontrak tersedia status realtime boleh confirmed. Failure tetap pending/failed, retry tidak mengubah hak kembali.
5. Client yang sah reconnect dengan signature/epoch baru. Client revoked ditolak server. Teks RME, foto pending, filter dan session pengguna lain tetap; frame yang telah dikirim tidak bisa ditarik dari jaringan/browser.

## Keputusan yang masih diperlukan

- PR-01/T: commit versus acknowledgement sebagai batas efektif, timeout dan UI pending/error. Lima detik hanya usulan fixture, belum SLA operasi.
- Cakupan logout, password reset pribadi/broker, perubahan tenant dan active/role/permission; tidak mengasumsikan seluruh logout harus memutus semua device tanpa persetujuan.
- Eligibility notifikasi per role/event setelah role staf hilang tetapi akun tetap aktif. Callback ownership existing terlalu longgar untuk revocation staf; prototype mensyaratkan role sah dan epoch. Jangan mengubah hak baca patient/admin atau membuat blanket ban lintas jenis notifikasi tanpa menelusuri policy. `queue.view` hilang tidak otomatis berarti semua notifikasi akun harus dilarang pada authorization **baru** yang masih sah.
- Target deployment S2 single Reverb node atau beberapa node; definisi ack seluruh node dan perilaku node unreachable. Redis/scaling belum terbukti dalam S1.
- Strategi old unsigned subscriptions dan transisi proses: server menolak legacy bindings sebelum menyatakan fixed; jangan bergantung client mau reconnect. Authority-store unavailable tidak boleh membuka delivery, tetapi frame yang dijatuhkan membutuhkan snapshot recovery.

## Paket S2 yang dapat diuji terpisah, setelah evaluasi/izin

1. **Boundary versi dan mutation:** migration additive, current/old epoch, seluruh caller user/pivot, boot order dan rollback. Belum mengklaim transport fixed.
2. **Subscribe dan pre-send guard:** signed identity semua family, fresh policy, native SDK, semua jalur broadcast, legacy sockets, read-failure recovery.
3. **Durable cleanup/ack dan recovery UI:** pending/confirmed/failed sesuai kontrak, retry, reconnect/reauth, snapshot, draft/file/filter preservation.
4. **Regression nyata dan gate PR-01:** seluruh matrix below, suite aplikasi terkait dan hasil baru; barulah evaluasi FIXED & VERIFIED.

## Pengujian wajib S2

| Kelompok | Acceptance / bukti yang wajib ada |
| --- | --- |
| Mutation atomicity | Password/active/clinic dan epoch atomic, role/direct/role-permission attach/detach/sync, rollback, no-op/duplicate hooks, exception/crash antara perubahan dan cleanup; MySQL transaksi/locking nyata |
| Old socket | Dua browser + multi-tab; event baru dari check-in sesudah boundary efektif; kontrol sah benar-benar menerima, target nol frame private; server send timestamps dibanding ack |
| Auth/reconnect | Private clinic + users untuk semua role relevan; inactive/roleless/stale/foreign/forged/legacy/missing version; auth-issued-before-revoke replay pada socket sama, cross-socket reuse, disconnect/reconnect, reactivation dan rapid ABA |
| Delivery | Worker nyata, backlog sebelum mutation; QueueUpdated private+public, QueueStatus dan AppointmentStatus; all/except/internal; tidak menambah nama/PII/isi medis ke public |
| Failure | Terminate 401 dan network failure, worker retry, authority DB failure/recovery, process restart/pending ack, stale retry vs fresh login; beberapa node jika itu target yang dipilih |
| Recovery UI | HTTP401/419 vs offline/403, editor autosave dan pending file tanpa kehilangan data, filter/caret/fokus saat refetch, notifikasi/status jujur, user sah tidak dipaksa reload terus |
| Existing regression | Backend/JS/Playwright relevan QA/DFU/CCL dan PG-17/18, full suite sesuai scope implementasi, actual MySQL behavior; histori klinis/komersial dan isolasi tenant tetap |

Tidak ada S2 code atau production migration dalam spike ini. Hasil S1 cukup untuk memilih desain paling layak; belum cukup untuk menutup PR-01 atau menyatakan seluruh acceptance produksi lulus.
