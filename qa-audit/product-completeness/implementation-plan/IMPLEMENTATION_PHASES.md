# Fase implementasi yang dapat dikerjakan dan diuji terpisah

Tanggal: 14 November 2026. **Semua fase di sini belum dijalankan.** Tahap sekarang hanya menghasilkan tujuh dokumen rencana. Tidak mengubah source/test/config/dependency, membuka database, menjalankan aplikasi, migration, suite atau browser; tidak commit/push/deploy. Fase bukan audit ulang.

Mulai dari [prioritas](IMPLEMENTATION_PRIORITY.md); spesifikasi per ID di [FEATURE_SPECIFICATIONS.md](FEATURE_SPECIFICATIONS.md), transisi UI di [WORKFLOW_DESIGN.md](WORKFLOW_DESIGN.md), serta schema/gate di [DATABASE_IMPACT.md](DATABASE_IMPACT.md) dan [DECISIONS_REQUIRED.md](DECISIONS_REQUIRED.md). Perbaikan kecil siap berarti siap menerima instruksi implementasi berikutnya, bukan sudah dilaksanakan.

## Definition of ready dan done setiap paket

**Ready:** scope/gate relevan diputuskan, source caller dan schema yang tepat telah dipetakan, AC/migrasi/backward compatibility jelas, fixture testing terisolasi terdefinisi, rencana negative roles/recovery tersedia. Untuk S1, ready hanya berarti eksperimen teknis terarah dengan hasil yang dapat memutuskan desain.

**Done:** implementasi minimum beserta backend/browser regression relevan lulus dengan bukti run aktual; aksi lintas role/state dan recovery benar-benar dilakukan melalui UI; data historis tetap; policy/tenant/version/idempotency teruji; schema/lock MySQL diuji bila relevan; tidak ada failing test dihapus. Laporan menyebut hasil PASS/FAIL/BLOCKED/NOT TESTED aktual dan batas, bukan menyalin angka historis. Setiap subfase punya hasil yang dapat dipakai sendiri dan keputusan lanjut/berhenti tanpa destructive rollback.

Perubahan behavior backend perlu test bermakna, perubahan UI interaktif perlu browser DOM/error/task test. Pure copy tidak memerlukan mirror test baru. Sebelum menulis test saat implementasi, cari/baca testing-best-practices sebagaimana AGENTS; bila tidak tersedia, catat faktanya dan tetap ikuti pola PHPUnit existing. Jika mengubah Volt, aktifkan skill volt-development dan dokumentasi versi yang tepat. PHP yang berubah menjalankan Pint --dirty --format agent. Tidak ada langkah tersebut dieksekusi pada tahap dokumentasi.

## F0 — Rekam keputusan dan baseline

**Input:** 18 PG dan PR-01 existing. **Kesiapan:** dapat dibahas sekarang; persetujuan belum tercatat.

**Paket mandiri:** pilih D-01/02/03A/03B/04/06/07/13 untuk operasional klinis, D-09/10 untuk commercial recovery, PR-01/T untuk revocation. D-05/11/15/16 serta D-17/18/19 tetap ditahan bila tidak dipilih; D-08/12 menyusul untuk pelaporan/reminder. Tidak harus menunggu semua keputusan untuk memulai F1 pada instruksi terpisah.

**Exit:** tiap keputusan menyebut alternatif, aktor/role, states, harga/kuota bila relevan dan AC; baseline QA/DFU/CCL ditandai sumber historis. **Validasi:** review traceability setiap ID dan konsekuensi, tanpa membuat/mengubah data. **Jika ditunda:** fase gated tetap belum ready, fitur existing berjalan.

## F1A — PG-18 bug pending grant

**Ready:** SIAP, tidak memerlukan kebijakan komersial baru. **Ukuran:** S. **Prasyarat:** baseline grant idempotent existing.

**Pekerjaan:** perbaiki boolean/expression pada caller Blade tenant-grant; pertahankan shared button component, server-disabled empty catalog, UUID dan error feedback. Satu paket tanpa migration atau service refactor.

**Uji:** render tidak mengandung directive mentah, browser tanpa Alpine pageerror, invalid form tidak dead-end, pending tombol disabled/keyboard, validation/server-error retry, katalog kosong. Backend replay tetap satu periode di PlatformSubscriptionManagementTest/DFU regression terkait.

**Exit:** PG-18 bisa berstatus FIXED & VERIFIED dengan screenshot/DOM/error log baru dan dedup regression. **Stop/rollback:** kembalikan perubahan template bila perlu, bukan hapus period atau rollback grant. Tidak menunggu PG-09/10/PR-01.

## F1B — PG-17 kandidat lanjutan

**Ready:** SIAP, tanpa kebijakan identitas baru. **Ukuran:** S–M. **Independen F1A.**

**Pekerjaan:** shared matcher tetap; cursor/has_more pada endpoint kandidat, UI Lihat kandidat lain dan buka profil tanpa kehilangan form; token review/input-changed behavior tetap. Tidak auto-merge/link/reactivate, tidak membuat table baru.

**Uji:** 0/1/8/9/10 kandidat, tenant/cursor tamper, legacy telepon sesuai CCL-03, inactive sesuai CCL-04, response stale, shared phone, review token invalid. Browser menemukan kandidat ke-9/10 → profile → booking existing tanpa profil baru, lalu kembali ke input awal.

**Exit:** hasil lanjutan actionable, input/selection tidak hilang, old endpoint default kompatibel. **Stop:** default delapan hasil tetap dapat dipakai dengan copy batas yang jujur; jangan menghapus matcher/review security demi paging.

## S1 — PR-01 spike kompatibilitas protokol

**Ready:** SIAP SPIKE pada instruksi implementasi terpisah. **Ukuran:** dibatasi bukti di bawah; estimasi S2 setelah spike. **Bukan load/readiness test.**

1. Fixture DB/Reverb/worker lokal terisolasi, dua pengguna klinik sama dan satu foreign tenant; tidak menggunakan user DB/credential produksi.
2. Buktikan signed channel_data user_id/version diteruskan pusher-js, diverifikasi Reverb, terlihat pada registry untuk terminateUserConnections; private queue dan users notification keduanya tercakup.
3. Buktikan app-owned ChannelManager/private guard benar-benar dipakai pada subscribe, broadcastToAll, broadcast except/internal dan cached/private paths yang relevan. ChannelBroker melakukan new langsung; bind class yang tidak terpakai bukan hasil sukses.
4. Buktikan termination SDK menutup old socket; stale auth subscribe setelah revoke ditolak melalui fresh state/version. Tidak bergantung pusher:signin yang tidak ada pada EventHandler terpasang.
5. Buktikan denial sebelum private frame baru, public board tetap nonmedis; DB/control fail-closed dan legacy unsigned sockets.

**Exit:** desain/extension points terpilih, tests pembuktian PASS/FAIL aktual, batas/latency ack sesuai PR-01/T ditetapkan; tanpa patch vendor/dependency baru. **Jika gagal:** PR-01 tetap OPEN, catat kendala spesifik dan alternatif server authorization yang dapat dibuktikan. Jangan mengirim client logout lalu menyebutnya solusi. S1 gagal tidak menghalangi F1/kalender/dokumen yang independen.

## S2 — PR-01 revokasi server dan recovery UI

**Gate:** S1 lulus + PR-01/T. **Ukuran:** M–L, dikonfirmasi setelah S1.

**Pekerjaan:** signed identity/version semua private subscriptions, server fresh authorization, account/role/permission mutation hooks/after-commit terminate+retry, delivery recipient checks, UI akses dicabut tanpa clear editor. Daftar caller mencakup StaffAccountService dan API Spatie attach/detach/sync; events_enabled tidak dibiarkan false jika mengandalkan event. Jangan menambah permission editor.

**Uji mandiri:** perubahan rollback tidak memutus user sah; inactive/role revoked/permission revoked/multi-tab/stale-auth/reconnect/reactivation; queue worker backlog dan users notification. Setelah barrier, lakukan check-in baru yang benar-benar menghasilkan QueueUpdated dan diterima browser kontrol; revoked browser tidak menerima private event. Public board tetap. Test 401/419/offline dan filter/cursor/pending file CCL tidak regresi. Fake broadcaster tidak cukup.

**Exit:** bukti old socket/authorize/reconnect/server-guard nyata, pending/failure sesuai kontrak, PR-01 dapat ditutup. HTTP200 terminate saja bukan exit. **Stop:** jangan mengaktifkan kembali revoked user; pertahankan penegakan server yang sudah dibuktikan, status pending/error eksplisit. Cakupan penggantian protokol harus diselesaikan sebelum legacy auth path dianggap aman.

## F2A — PG-09 viewer histori commercial

**Gate:** D-09 allowlist/role. **Ukuran:** M. **Tidak bergantung mutasi F7B.**

**Pekerjaan:** tab timeline Platform + query periods/payment/addon/audit, current/effective/expired/future/revoked dengan tautan sumber. Owner subset dikerjakan hanya bila dipilih D-09, bukan otomatis.

**Uji:** empty/Free/trial/manual grant/current/future/revoked/refunded legacy; ends_at/timezone; policy tenant dan invoice permission terpisah; no period/payment writes. CCL-06 cache current tetap benar dan add-on history owner existing tidak hilang.

**Exit:** pengguna Platform menjelaskan satu kasus multi-periode dari UI, seluruh sumber dapat ditelusuri tanpa SQL; tanpa schema baru. **Stop:** hilangkan entry viewer bila perlu, histori existing tidak disentuh.

## F2B — PG-02 preview dampak read-only

**Gate:** cakupan baca existing dan bentuk preview D-02; tidak memberi izin exception mutasi. **Ukuran:** S–M.

**Pekerjaan:** form preview dokter/klinik/rentang lokal → grouped affected booking/active/historical, dengan next action existing atau penjelasan fitur pemulihan belum tersedia. Tidak ada action fiktif yang diklaim bekerja.

**Uji:** zero/one/multiple, foreign clinic/doctor, midnight/timezone, active/final, input tanggal invalid; assert zero writes. **Exit:** owner/Rec yang sah dapat meninjau dampak. **Stop:** tidak ada data yang perlu dibalik. PG-02 masih PARTIAL sampai F3B/F4.

## F3A — PG-06 makna inactive

**Gate:** D-06. **Ukuran:** S–M. **Bisa dikerjakan tanpa kalender.**

**Pekerjaan:** predicate domain tunggal + copy kanal + reaktivasi explicit existing. Tidak melakukan bulk perubahan is_active atau pencocokan guest berbasis identitas yang belum diverifikasi.

**Uji:** Patient own/staff/guest, foreign/missing/stale IDs, original UUID replay, aktif ulang melalui UI, patient account inactive vs profile inactive, history tetap. CCL-03/04 tetap lulus, tidak ada profil pengganti/auto-link.

**Exit:** hasil tiap kanal sesuai D-06 dan alasan/pemulihan jelas. **Stop:** jangan mass-reactivate pasien agar test hijau; putuskan kompatibilitas jika arti flag berubah.

## F3B — PG-02 calendar blocking

**Gate:** D-02, predicate integrasi shared booking. **Ukuran:** M. **F2B dapat dipakai ulang, F4 belum diperlukan untuk blok baru.**

**Pekerjaan:** migration additive exception + create/cancel/version, guard slot-list dan POST BookingService, privacy alasan, impact list tetap nonmutating terhadap appointment.

**Uji:** interval/timezone/whole-day, overlap/cancel, user role, legacy weekly schedule, current clinic hours, concurrent close-vs-book di MySQL fixture, cached form POST, old UUID replay/history CCL-05. Role klinis tetap dapat membaca/menyelesaikan visit existing.

**Exit:** tidak ada booking baru di block, tanggal normal tetap, appointment lama tidak berubah. **Stop:** batalkan exception lewat command teraudit bila salah; tidak membuka ulang appointment cancelled. Jangan drop tabel yang sudah punya histori.

## F4 — PG-01 dan dokter absen pra-visit

**Gate:** D-01 + D-06; F3B untuk skenario exception; F2B untuk daftar dampak. **Ukuran:** L.

**Pekerjaan:** replacement service/table/request/policy/UI preview, same patient, old/new link, quota/harga/queue/check-in sesuai keputusan. Selaraskan lock order command yang berinteraksi; jangan menyalin business rules ke Blade/controller. Kelola CALLED hanya bila disetujui; Patient self-service hanya bila D-01 mencakupnya.

**Uji:** replace tanggal/dokter/layanan, same-session full, target inactive/full/outside hours/exception, payload mismatch UUID, foreign data, start-vs-replace/double-replace MySQL. Browser dari exception→booking terdampak→replacement→Patient melihat kode baru→check-in/call/start dokter target. Original no-visit/DFU-01 guard tetap.

**Exit:** tugas dokter berhalangan sebelum visit selesai tanpa direct DB edit; failure zero partial writes, old record dapat ditelusuri. **Stop:** hentikan aksi replacement baru; jangan undo dengan menghapus old/new. Perbaikan salah target memakai replacement berikutnya yang sah, bukan reset histori.

## F5A — PG-03 pause/resume/terminal interrupted

**Gate:** D-03A, model/status/slot/subscription diputuskan. **Ukuran:** L.

**Pekerjaan:** enum readers/constraints dulu, kemudian domain commands/events/policies dan UI. Status reader harus siap sebelum row state baru ditulis. Editor state/version/draft safeguard dan finish-origin-IN_PROGRESS exception dirancang bersama.

**Uji:** draft/foto/dental persisted, stale save/finalize-vs-interrupt/resume-vs-call, 401/419/offline, paused slot release/reacquire, expiry/suspended, role negative. Interrupted tidak dihitung completed dan Rx draft tidak muncul di Patient. Satu aktif per sesi tetap di MySQL.

**Exit:** dokter semula dapat resume atau menutup belum selesai secara jujur, seluruh riwayat utuh. **Stop:** reader state baru harus tetap jika data sudah ada; jangan mengubah interrupted menjadi completed demi rollback. PG-03 belum lengkap untuk pergantian dokter sebelum F5B.

## F5B — PG-03 handover/continuation

**Gate:** D-03B + F5A pada model rekomendasi; availability/BookingService; tarif/kuota/expired continuation disetujui. **Ukuran:** L–XL tergantung model; tidak menambah scope kasir.

**Pekerjaan:** pilih satu model episode lanjutan atau current-assignee, tidak keduanya; request/accept/decline/target booking/clinical packet/policy/history. Dalam model lanjutan, source interrupted dan target record terpisah, attribution/source doctor tetap.

**Uji:** dokter tak hadir, target decline/unavailable/full, actor override yang sah/tidak sah, patient inactive, expiry continuation pilihan D-03B, race dua penerima, old tab save, medical packet privacy dan target finish. Browser dokter A→dokter B + Rec hingga record target final; source tetap non-completed, tidak memakai PG-01 pada source dengan visit.

**Exit:** situasi pergantian dokter setelah pelayanan dimulai mempunyai jalan sah dan dapat ditelusuri; task declined/unresolved tidak hilang. **Stop:** permintaan pending dapat dibatalkan dengan alasan; episode/record yang sudah terjadi tidak dihapus. Tanpa keputusan ini, target abnormal handover masih terbuka dan harus dilaporkan.

## F5C — PG-13 resolusi versi alergi

**Gate:** D-13. **Ukuran:** M–L. **Independen F5A/B.**

**Pekerjaan:** expected-parent conflict + UI, current lineage jika dipilih, legacy unresolved, RME allergy version/critical banner tetap terpadu; tidak backfill berdasarkan kemiripan teks.

**Uji:** dua tab update parent sama, deactivation, severity/critical change, explicit resolve dan stale finalization, role/tenant, old final revision tidak berubah. **Exit:** pengguna dapat melihat/resolusi konflik menurut keputusan tanpa lost input/overwriting. **Stop:** jangan menghapus sibling legacy; fallback UI harus mempertahankan informasi yang belum terselesaikan.

## F6A — PG-07 dokumen final

**Gate:** D-07. **Ukuran:** M. **Tidak menunggu billing atau handover.**

**Pekerjaan:** approved snapshot + issue/reprint/supersession + policy dan printable Blade. Patient access hanya yang disetujui; PDF server/e-signature bukan default.

**Uji:** final-only, correction-vs-issue/version, reprint snapshot lama, denied role/tenant, private endpoint, text/resep panjang/print page breaks, empty/error/retry. **Exit:** dokter dan Patient yang diizinkan dapat memakai dokumen tanpa copy ke aplikasi lain, source version jelas. **Stop:** hentikan penerbitan baru bila perlu, pembacaan histori tetap aman; tidak drop snapshot.

## F6B — PG-04 follow-up tertutup

**Gate:** D-04; BookingService; F4 untuk integrasi penggantian target. **Ukuran:** M.

**Pekerjaan:** task open/booked/completed/cancelled, payload pasien vs metadata admin, CTA booking dan link episode. Completion event idempotent; no-show/cancel mengikuti keputusan reopen/review.

**Uji:** future beyond booking horizon, Patient/Rec booking, target replace/cancel/no-show/complete, inactive/suspended, duplicate task/dua tab, patient without account, source correction. **Exit:** satu pasien kontrol dapat ditelusuri dari rencana dokter hingga visit target benar-benar selesai atau cancelled beralasan; no automatic booking/medical share. **Stop:** task tetap tersimpan dengan state/next-action, bukan dihapus saat target gagal.

## F7A — PG-14 detector pada fixture

**Gate:** kategori invariant D-14 minimum; tidak memerlukan izin data nyata untuk membuat fixture. **Ukuran:** M.

**Pekerjaan:** read-only detector administratif/klinis berpolicy, report kategori dan fingerprint, dataset sehat/rusak eksplisit. Jangan memakai service refresh/backfill yang menulis ketika dry-run.

**Uji:** healthy false-positive, broken cross-relations, partial legacy data, zero writes, payload tanpa SOAP/token/credential, unauthorized viewers. **Exit:** laporan fixture deterministik, daftar keputusan repair yang diperlukan. **Stop:** tidak ada data yang perlu dipulihkan. Tidak mengklaim data pengguna bersih/bermasalah.

## F7B — PG-10 single-grant correction

**Gate:** D-10 + F2A. **Ukuran:** L. **Independen F7A bila bukan repair legacy.**

**Pekerjaan:** preview + reversal selected manual grant + related addon effect + refresh cache + audit/request uniqueness. Tidak melaksanakan transfer refund dana provider atau paid-period revoke yang belum dipilih.

**Uji:** current/future/revoked manual grant, source paid ditolak, duplicate fixture lama, legitimate future period tidak bergeser, period/refund/addon/expiry boundaries, replay/grant-vs-reversal MySQL. Browser Platform correction sambil dokter menyimpan/finalisasi IN_PROGRESS, CCL-06 dan DFU-12 tetap.

**Exit:** satu grant salah dapat dikoreksi dari UI dan dijelaskan melalui timeline, tanpa menghapus invoice atau periode sah lain. **Stop:** tidak menghapus reversal; reversal berikutnya/compensation hanya jika kontrak mengizinkan, bukan DB reset.

## F7C — PG-14 repair kategori yang disetujui

**Gate:** D-14 + izin dataset + F7A, serta domain command yang sah (F7B untuk komersial, D-03/clinical command untuk klinis). **Ukuran:** L per kategori; bukan general repair engine.

**Pekerjaan:** dry-run fingerprint → review → apply UUID melalui service → before/after/audit. Hanya kategori yang dipilih; mismatch lain tetap report BLOCKED/needs-review.

**Uji:** data berubah sejak preview → conflict, replay, rollback penuh saat gagal, healthy untouched, zero history delete, clinical vs commercial actor boundary. **Exit:** kategori tersebut pulih dan bukti tersimpan; bukan semua legacy otomatis selesai. **Stop:** data pengguna tidak disentuh tanpa izin khusus; schema/histori repair tidak dihapus.

## F8A — PG-08 laporan operasional

**Gate:** D-08. **Ukuran:** M. **Tidak menunggu PG-05.**

**Pekerjaan:** shared metrics/detail + CSV allowlist, no cash/clinical content, timezone/status definitions. **Uji:** empty/range/error, status PG-03 bila tersedia, formula escaping, detail-total equivalence, tenant roles, filter/kursor existing. **Exit:** owner menyelesaikan laporan periode nonkas dari UI; angka cocok sumber. **Stop:** nonaktifkan export jika salah; tidak mengubah data sumber agar cocok.

## F8B — PG-12 reminder terpilih

**Gate:** D-12, semantics queue/booking yang sudah diimplementasikan; S2 untuk klaim revocation private aman. **Ukuran:** M.

**Pekerjaan:** near-turn in-app + persistent dedup dan recheck saat delivery; scheduled reminder sebagai subpaket jika dipilih, tanpa provider/kanal baru otomatis. **Uji:** threshold/rule, skip-return/reschedule/pause/cancel/no-show, recipient changed/inactive, clock/worker retry/backlog, click next-action ke booking milik sendiri. **Exit:** pesan tepat/relevan dan tidak duplikat/basi pada rule disetujui. **Stop:** hentikan evaluasi/kirim baru, histori delivery tetap; jangan replay semua reminder lama.

## O1–O4 — Scope bersyarat, bukan pekerjaan otomatis

| Paket | ID | Kondisi memulai / artefak yang masih perlu | Uji minimum bila dipilih |
| --- | --- | --- | --- |
| O1 | PG-05 | D-05 memilih kasir; spesifikasi ledger, role, harga, payment/receipt/refund terpisah SaaS dahulu | Finansial idempotency/reconciliation/negative roles dan histori; tidak dibuat sekarang |
| O2 | PG-11 | D-11 memilih satu lifecycle, bukan semua sekaligus; verifikasi owner/hak baca/kontrak jelas | Transfer/suspend/resume/closure pilihan, satu owner satu klinik, no medical bypass, PR-01 |
| O3 | PG-15 | D-15 memilih invitation; delegated admin perlu persetujuan berbeda | Broker/verification/reset/session baseline, accept/revoke/expire/resend/seat, owner boundary |
| O4 | PG-16 | D-16 memilih copy atau enforcement + transisi; jangan seed ulang invoice/period | Matrix Free/paid/trial/expired/suspended/future, service continuation dan dental tetap |

Kiosk, multibranch dan permission editor tidak dibuat dari paket opsional ini. Mereka tetap D-17/18/19 historis dan bukan ID PG tambahan. Tidak ada fase opsional dijadikan “selesai” dengan sekadar ditulis rencana.

## Regression lintas fase dan pelaporan

| Baseline VERIFIED yang harus dipertahankan | Fase paling terdampak | Bukti yang wajib diproduksi saat implementasi |
| --- | --- | --- |
| QA auth/staff role/HTTP session, tenant/medis/private photos | S2, F5B, F6A, O2/O3 | Negative role/resource, session/reset/inactive + private socket nyata, bukan sekadar menu tersembunyi |
| DFU-01 cancel vs visit dan atomic finalization | F4, F5A/B | Race start/cancel/replace, queue/visit/appointment/RME konsisten |
| DFU-02 day/date/session | F3B/F4/F5B | Local day, wrong schedule, copied session dan queue date konsisten |
| DFU-03/04 error feedback | Seluruh form baru | Validation/domain error tidak hilang pada refetch, input tetap |
| DFU-05/06 + CCL-03/04 patient phone/inactive | F1B/F3A/F4/F6B | Normalisasi simpan/validator/kandidat legacy sama; tidak profil baru diam-diam |
| DFU-07/08 tanggal/timezone | F2/F3B/F4/F6/F8 | from/to invalid dan batas timezone klinik |
| DFU-09/10 label/mobile | Seluruh UI baru | Label/fokus/keyboard, overflow375/768/1024/1440 pada layar yang berubah |
| DFU-11 booking portal dan DFU-12 grant idempotency | F4/F6B dan F1A/F7B | Entry Patient tetap reachable; double-click/replay satu grant |
| CCL-01 harga; CCL-02 expired editor | F4/F6A; S2/F5/F6 | Rentang FE/BE tetap; 401/419 vs offline, teks/file sebelum reload tetap |
| CCL-05 booking current hours, histori tidak berubah | F2B/F3B/F4 | Old UUID/history utuh, new booking di luar jam ditolak |
| CCL-06 effective subscription; CCL-07 filter/cursor; CCL-08 catalog empty | F2A/F7B/O4; S2/F4; F1A/O4 | Effective boundary/future/suspended, preserve state refetch, empty packages next-action |
| Autosave/version, dental entitlement, allergies, final/correction/history | F5/F6/O4 | Draft/final/correction/private storage dan optimistic guard tetap, paid dummy sah, expiry read-only |

Jalankan narrow PHP/JS/Playwright yang relevan tiap paket; perluas ke seluruh suite setelah integrasi state/schema besar atau sebelum menandai milestone klinis/komersial selesai. Bila PHP berubah, Pint sesuai AGENTS; bila asset berubah, build lalu browser atas build baru. SQLite in-memory diberi override proses eksplisit; MySQL hanya database testing terisolasi, tidak database aplikasi. Jangan memakai APP_KEY/.env pengguna atau mengirim pembayaran nyata.

Per milestone simpan: file/caller yang berubah, decision yang disetujui, test backend/JS/browser yang benar-benar berjalan dengan PASS/FAIL/BLOCKED/NOT TESTED, screenshot/log tersanitasi, hasil data-integrity dan batas. PHP/JS/browser mempunyai unit berbeda; jangan dijumlahkan menjadi persentase kelengkapan. PR-02/03 historis jika muncul pada MySQL diinvestigasi sebagai test/factory concern, tidak disembunyikan atau dihitung gap produk baru.

## Exit target operasional

Target klinis dinyatakan tercapai hanya bila task normal dan abnormal terpilih benar-benar ditutup: booking→replacement→check-in→dokter target; absen saat pemeriksaan→recovery/handover sah; final→dokumen disetujui→follow-up sampai selesai/cancel beralasan; semuanya tenant/role-safe. Jika D-03B/D-07/D-04 belum dipilih, catat target terkait masih tertahan, bukan klaim end-to-end selesai. Histori/recovery commercial dan PR-01 ditutup dengan bukti masing-masing, bukan disimpulkan dari functional PHP hijau.

Tahap perencanaan ini cukup diverifikasi pada cakupan 18 ID, gate/dependensi/AC, validitas rujukan dan keutuhan source serta laporan historis. Pengujian aplikasi, validasi manusia dan provider E2E sengaja tidak dijalankan atau diklaim baru.

## Verifikasi dokumen tahap ini

Pemeriksaan struktur/rujukan **PASS**: tepat tujuh file keluaran; PG-01–PG-18 masing-masing sekali pada tabel induk prioritas dan dampak database serta mempunyai spesifikasi; PR-01 terpisah; D-01–D-20 ditelusuri; 27 tautan lokal valid. Sebanyak 496 file baseline source/test/config/manifest/dokumen dan laporan sebelumnya identik menurut SHA-256. Ini pemeriksaan dokumen/integritas, **bukan** hasil suite PHP/JS/Playwright baru. Tidak ada database atau runtime aplikasi dibuka oleh verifikasi ini.
