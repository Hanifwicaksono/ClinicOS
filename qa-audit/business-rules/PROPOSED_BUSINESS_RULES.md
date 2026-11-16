# Usulan kontrak bisnis dan acceptance criteria

**Semua paket belum disetujui.** P-Dxx merujuk keputusan D-xx asli, bukan gap baru. Untuk setiap paket: masalah, role/prasyarat, state A/Q/V/R, kapasitas/subscription, histori, konflik/error, notifikasi/akses, risiko dan AC dicatat di bawah. Source existing dirujuk melalui ER/E pada [register](EXISTING_BUSINESS_RULES.md). Acceptance criteria adalah rencana uji, **belum dieksekusi** pada tahap analisis ini.

Invariant bersama: resource tenant yang sah; authorization terbaru di commit; final RME dan histori pembayaran tidak dihapus; single operation UUID+intent+actor; preview bukan reservation; failed transaction tidak memublikasikan state yang tidak commit. Raw SQL ACL tidak melewati S2; konflik tidak ditutupi reload yang membuang draft. Untuk paket yang tidak menyentuh A/Q/V/R, “tetap” mencakup keempat resource tersebut.

## P-D01 — Atomic pre-visit replacement (MISSING + DECISION REQUIRED)

**Masalah/role/kondisi:** admin/rec perlu mengganti waktu/dokter tanpa kehilangan booking sah. Rekomendasi BOOKED/WAITING/SKIPPED tanpa Visit; CALLED ditarik dengan aksi sah dahulu. Patient self-service ditunda. IN_PROGRESS/terminal dan foreign tenant ditolak.

**State:** old A/Q CANCELLED beralasan dan tertaut, new A/Q BOOKED, V/R belum ada. Tidak overwrite old service/doctor/time snapshot. Target tarif terkini harus ditampilkan; price berubah setelah preview→409/review ulang. Nomor target baru, tidak menyalin checked_in_at.

**Kapasitas/histori:** atomic release-old/reserve-new; rollback bila target gagal. Perlu pilihan eksplisit carry eligibility grandfather booking lama ketika monthly quota sudah tercapai—rekomendasi mempertahankan satu eligibility tertaut pada satu replacement aktif, bukan backdate created_at atau bypass semua booking. Existing BookingService belum menyediakan carry ini.

**Error/notifikasi/risiko/AC:** conflict target penuh, dua operator, doctor start, blackout baru atau expiry→satu hasil sah/tanpa perubahan parsial; notification sesudah commit membedakan perubahan dari pembatalan murni dan menyebut referensi baru tanpa medis. Replay sama satu replacement, changed intent ditolak. Test harga, same-session move, quota1, chain replacement dan old-visit guard. Persetujuan: product/operasional + commercial untuk carry quota.

## P-D02 — Availability exceptions dan dampak (MISSING + DECISION REQUIRED)

**Masalah/role/kondisi:** admin clinic mengesahkan clinic/doctor unavailable interval lokal `[start,end)`; dokter hanya melapor bila scope ini disetujui. Tanggal/akhir interval/tenant wajib sah. Template weekly, active flag dan blackout bukan hal yang sama.

**State:** exception active/revoked; A/Q/V/R existing tetap, termasuk IN_PROGRESS. New booking/reschedule target overlapping ditolak. Tidak otomatis menandai NO_SHOW atau memindahkan pasien.

Karena appointment hanya tanggal+sesi, rekomendasi awal memblok new booking seluruh sesi yang overlap partial blackout, bukan mengklaim patient-level exact slots. Impact list mencakup semua booking sesi untuk review. Auto-split sesi/kuota tidak masuk paket awal.

**Kapasitas/histori:** tidak mengurangi quota histori; impact list dan follow-through D-01/03 eksplisit. Weekly replacement membutuhkan keputusan tentang effective dates dan overlap inactive template; timezone changes juga menampilkan dampak, tidak rewrite instant lama.

**Error/notifikasi/risiko/AC:** race blackout-vs-book diserialisasi pada authority availability yang sama; stale preview diperiksa ulang. Komunikasi hanya booking terdampak setelah konfirmasi penanganan, bukan pesan dokter cuti ke semua pasien. Uji start/end boundary, overlap dua exceptions, revoke/restore, booking lama utuh, dan no side effect pada RME. Persetujuan owner/operasional/product.

## P-D03 — Pause, interrupted dan handoff (DECISION REQUIRED)

**Masalah/role/kondisi:** pelayanan berhenti atau dokter tidak tersedia. Assigned Doctor pause/resume; admin/rec mengajukan masalah nonmedis; keputusan terminal/handoff oleh klinisi yang ditunjuk SOP, tidak otomatis owner. Penerima Doctor aktif di tenant sama dan harus menerima.

**State:** rekomendasi V/Q PAUSED↔IN_PROGRESS, A tetap BOOKED, R DRAFT/versioned. Interrupted A/Q/V terminal baru, R belum final tetap tersimpan/terkunci. Handoff menutup episode lama secara jelas dan membuat A+V lanjutan tertaut; bukan Visit kedua pada A lama atau overwrite doctor_id. Dokumen final yang sudah ada tidak direopen.

**Kapasitas/subscription/histori:** pause awal hold active slot; start count episode lama tetap. Rekomendasi continuity entitlement yang terbatas pada episode sah yang terinterupsi harus disetujui clinical+commercial; implementasi sekarang menghitung new visit dan memblok new start saat expired. General continuity tidak otomatis memberi dental edit. Simpan author, pause intervals, reason, receiver acceptance dan old/new links.

**Error/notifikasi/risiko/AC:** complete-vs-pause, two receivers, stale autosave, receiver inactive, expiry, original doctor absent → reject/clinical review tanpa draft overwrite atau completion palsu. Staf/pasien hanya status/instruksi yang disetujui; read old RME scope khusus handoff. Uji semua state baru di FIFO, quota, ETA, dashboard, policies dan S2. Detail persetujuan ada pada [clinical workflow](CLINICAL_WORKFLOW_RULES.md).

## P-D04 — Follow-up tertaut (MISSING + DECISION REQUIRED)

**Masalah/role:** clinical plan tidak membuktikan pasien sudah kontrol. Dokter membuat instruksi/due window dan accountable clinician; rec mengatur booking metadata; pasien melihat instruksi allowlist.

**State:** task OPEN→BOOKED→COMPLETED saat linked V completed; CANCELLED explicit. Jika A cancelled/no-show, rekomendasi kembali OPEN/review dengan alasan; A/Q/V/R sendiri tetap mengikuti services existing.

**Kapasitas/histori:** task tidak reserve slot/kuota; booking memeriksa current schedule/subscription. New doctor atau tanggal mengubah task history, bukan old RME. Follow-up bukan privileged bypass expired plan.

**Error/notifikasi/risiko/AC:** dua rec booking task sama→satu active linked booking atau409; stale completion tidak menutup task setelah rebook. Dedup reminder/task event; overdue bukan diagnosis. Uji cancel/no-show, doctor inactive, user tanpa account, tenant lain, dan metadata tanpa SOAP. Klinis+operasional/product menyetujui fields dan ownership.

## P-D05 — Scope kasir (OPTIONAL)

**Rekomendasi:** tetap administrasi klinis+SaaS dengan kasir eksternal. Owner/product/finance menentukan apakah perlu proyek billing pasien terpisah; tidak ada role kasir otomatis.

**State/kapasitas/histori:** A/Q/V/R, service snapshot, visit quota dan subscription tetap. Dashboard service value tidak dinamai uang diterima; Payment existing tetap SaaS. Tidak membuat ledger/receipt/refund pasien atau memindah data lama.

**Error/notifikasi/risiko/AC:** tidak memblok completed clinical visit karena pembayaran pasien yang belum dimodelkan. Uji terminology/field API/dashboard tetap membedakan service value dari cash; tidak ada invoice SaaS dipakai sebagai receipt pasien. Bila scope diperluas, partial/discount/refund/finance authorization harus diputuskan sebelum schema.

## P-D06 — Status pasien dan recovery (INCONSISTENT + DECISION REQUIRED)

**Rekomendasi/role:** nonaktif memblok **booking baru yang terhubung ke profile terverifikasi** di semua kanal; admin/rec dengan patient.update dapat reaktivasi dengan alasan setelah review administrasi. Account inactive tetap masalah terpisah. Guest legacy tidak dicocokkan paksa lewat phone/NIK.

**State:** profile inactive→active hanya eksplisit; A/Q/V/R existing tetap; pelayanan in-progress tidak dibatalkan karena profile toggle. Booking lama perlu label review, bukan auto no-show/new patient.

**Kapasitas/histori:** tak ada quota reset atau duplicate profile; phone bersama sah. Putuskan perbedaan NIK unique staff intake vs review pada walk-in; jangan bulk-dedup legacy atas nama konsistensi.

**Error/notifikasi/risiko/AC:** deactivation-vs-book diperiksa ulang under lock; stale form tidak fallback. Pesan menjelaskan kontak klinik, bukan membocorkan alasan klinis blacklist. Uji linked inactive Patient, staff prefill, foreign, duplicate review/token/paging dan reactivation tanpa account linking. Product/operasional menyetujui makna dan alasan, klinis bila pembatasan terkait klinis.

## P-D07 — Dokumen hasil pelayanan (MISSING + DECISION REQUIRED)

**Rekomendasi/role:** mulai resep final issued/printable; ringkasan pasien allowlist diputuskan terpisah. Dokter penerbit berwenang; rec hanya distribusi metadata bila disetujui; pasien mendapat dokumen terpilih setelah ownership/identity check.

**State:** R FINAL/version tertentu→issued; correction membuat dokumen lama superseded dan versi issued baru. A/Q/V/R tidak diubah oleh print. Draft tidak disebut final; reprint tidak memalsukan issuer/time atau menghapus versi lama.

**Kapasitas/histori:** baca/print dokumen histori tidak otomatis memerlukan dental edit/current paid package; perlu keputusan bila commercial gating ingin ditambahkan. Tidak mengubah visit count. Field, nomor, signature/authorization, release recipient dan recall SOP memerlukan klinis/product.

**Error/notifikasi/risiko/AC:** issue-vs-correction memakai expected version; stale issuance ditolak. Patient/foreign/admin tidak memperoleh raw SOAP/foto dari URL tebakan; document copies tetap punya version identity. Uji print final, correction/reprint, expired add-on, revoked account dan audit release tanpa payload medis di notification.

## P-D08 — Laporan operasional (PROPOSED + DECISION REQUIRED)

**Rekomendasi/role:** satu CSV operational untuk owner/admin berwenang, periode clinic-local, definisi metrik dari current source. Pilih aggregate atau field nonmedis minimum; tidak membuka clinical contents atau finance cash report.

**State/kapasitas/histori:** A/Q/V/R dan quota tidak berubah; completed, interrupted proposal, no-show dan booking replacement dilaporkan terpisah agar tidak double count. Snapshot/definisi periode dicatat; bukan rewrite historis dashboard.

**Error/notifikasi/risiko/AC:** invalid range/tenant/oversized range menolak jelas tanpa blank export; kesepakatan maksimum range/row diperlukan, bukan angka arbitrer. Uji UTC-local month boundaries, cancelled replacement, no cash claim, CSV injection dan role privacy. Background export notification hanya bila dipilih; file storage/expiry juga perlu pilihan. Owner/product memilih tujuan/allowlist.

## P-D09 — Timeline komersial (PROPOSED + DECISION REQUIRED)

**Rekomendasi/role:** Platform tenant read-only timeline current/future/ended/revoked main/add-on periods; owner clinic subset. Payment link tetap butuh permission payment.view; tenant.view bukan bypass invoice access.

**State/kapasitas/histori:** A/Q/V/R dan entitlement tidak dimutasi; effective status melalui statusAt/refreshStatus, bukan label cache lama. Timeline mempertahankan actor/source/reason/price/invoice snapshot yang diizinkan.

**Error/notifikasi/risiko/AC:** query stale/future boundary tidak mengaktifkan periode. Uji scheduled start, revoked period, trial→Free, tenant isolation, empty state dan field allowlist tanpa clinical PII. Tidak perlu notification baru hanya karena melihat histori. Product/commercial menyetujui audience/fields; teknis read-only aman setelah scope field dipilih.

## P-D10 — Reversal grant dan refund (DECISION REQUIRED)

**Rekomendasi/role:** Platform permission khusus melakukan reversal satu manual grant dengan alasan/expected version; refund dana dan refund-record terpisah. Bukan global downgrade Free sebagai undo.

**State:** period yang dipilih revoked/reversed, period sah lain tetap; subscription effective dihitung ulang sesuai fallback yang disetujui. A/Q/V/R tetap, ongoing care exception dipertahankan. Paid period reversal memerlukan kontrak lain, tidak masuk otomatis.

**Kapasitas/histori:** jangan reshift future paid periods diam-diam; add-on terkait ditangani explicit. Current seat overage/read-only ditampilkan; tidak menghapus data karena paket turun. Reversal history append, replay idempotent.

**Error/notifikasi/risiko/AC:** concurrent grant/revoke/payment callback→satu chronology konsisten; stale target409, bukan revoke period baru. Uji future grant, current grant, unrelated valid paid period, addon, manual refund vs actual funds, retries dan IN_PROGRESS continuity. Commercial/product memutus fallback/grace, approval satu/dua pihak dan komunikasi owner sebelum coding.

## P-D11 — Lifecycle tenant/ownership (OPTIONAL + DECISION REQUIRED)

**Rekomendasi:** pertahankan lifecycle existing dan prosedur support terverifikasi; transfer/suspend/closure UI hanya bila kebutuhan dipilih owner/product. Tidak impersonation/hard-delete.

**State/role:** owner/platform commercial terverifikasi; clinic visibility, subscription suspended dan account disabled harus dibedakan. A/Q/V/R/history tidak dihapus/auto-completed; in-progress care dan lawful document access perlu klinisi/penanggung jawab data.

**Kapasitas/error/AC:** owner transfer tidak menciptakan clinic/seat subscription kedua; stale owner authorization ditolak dan S2 epoch/session ikut berubah bila context berubah. Uji bukti current owner, target account valid, two transfer requests, pending payments, old owner lost write, clinical history unchanged. Notifikasi hanya pihak sah dengan audit. Retention/akses setelah closure menunggu keputusan khusus.

## P-D12 — Near-turn/reminder (PROPOSED + DECISION REQUIRED)

**Rekomendasi:** in-app dulu, pisahkan near-turn trigger dari appointment reminder due-time. Threshold jumlah pasien atau estimasi menit belum ditetapkan; owner/operasional/product memilih angka dan cadence, klinisi menilai pesan yang bermakna klinis.

**State/role:** worker memeriksa current A/Q, recipient verified dan tenant; cancelled/completed/rescheduled tidak menerima reminder stale. A/Q/V/R tetap. Task follow-up tidak otomatis sama dengan near-turn.

**Kapasitas/histori:** reminder tidak reserve quota; ETA bukan janji pelayanan. Dedup operation/recipient/event/window; replay tidak spam; privacy tidak memasukkan diagnosis. Uji backlog, skip/return, reschedule, revoked multi-tab, user tanpa account dan threshold boundaries. SMS/WhatsApp/consent/biaya provider tidak ditambahkan otomatis.

## P-D13 — Allergy lineage (INCONSISTENT + DECISION REQUIRED)

**Rekomendasi/role:** clinician assigned membuat revisi expected-parent; satu current per lineage, distinct allergies tetap beberapa. Staf/pasien tidak mengesahkan clinical equivalence assertion.

**State:** current parent→superseded + current child atomik; stale second edit409. A/Q/V tetap; R final/revision tidak ditulis ulang, allergy hash berubah dan editor harus review sebelum save/final.

**Kapasitas/histori:** tidak mengubah quota/entitlement; preserve original assertion/actor/reaction/severity. Legacy tanpa parent tidak ditebak/merged otomatis.

**Error/notifikasi/risiko/AC:** concurrent edit/deactivate/allergy add vs prescription final memerlukan patient lock+version; critical warning tidak hilang karena conflict. Uji dua edit parent sama, dua alergi berbeda, source inactive, stale R editor dan retained historical acknowledgement. Clinical lead memilih lineage rules, deactivation authority dan proses adjudication.

## P-D14 — Diagnosis/repair legacy (PROPOSED + DECISION REQUIRED)

**Rekomendasi/role:** detector read-only untuk invariant spesifik pada fixture dahulu; repair command per kategori dengan expected fingerprint, dry-run dan persetujuan actor yang berwenang. Platform tidak otomatis melihat isi medis.

**State/kapasitas:** A/Q/V/R dan entitlement tetap saat diagnosis. Repair hanya state/relasi yang disetujui; tidak menyebut finalized palsu, menghapus histori, reset quota atau seed ulang. Clinical/commercial classification menentukan reviewer.

**Error/notifikasi/risiko/AC:** stale record setelah preview ditolak; satu repair UUID idempotent; backup/snapshot aman dan audit tanpa PHI. Uji inconsistency fixtures, valid records untouched, concurrent finalize/refund, rollback, repeated repair dan tenant mismatch. Tidak ada klaim jumlah data pengguna rusak atau izin mengakses real data pada analisis ini.

## P-D15 — Invitation/delegation (OPTIONAL)

**Rekomendasi/role:** bila dipilih, owner/admin provisioning Doctor/Receptionist memakai invitation set-password, tanpa delegated Clinic Admin baru. Lifetime/reinvite/revoke dan reservation seat memerlukan product choice.

**State/kapasitas:** invitation pending→accepted/revoked/expired; account/verification aktif sesuai kontrak; A/Q/V/R tetap. Jangan menambah seat aktif tanpa kontrol atau melepaskan semua jadwal saat invitation kedaluwarsa.

**Error/histori/notifikasi/AC:** satu recipient/intent, token single-use, no clear-text password, resend tidak melahirkan dua akun; klinik/role tidak dapat diganti lewat URL. Log lifecycle tanpa token. Uji invite dua kali, expired token, disabled user dan seat penuh. Admin delegation/custom permission memerlukan keputusan terpisah, tidak terselip di invitation.

## P-D16 — Kontrak paket (INCONSISTENT + DECISION REQUIRED)

**Rekomendasi:** selaraskan copy/katalog dengan rights yang sudah enforced; jangan mematikan realtime/dashboard Free sebagai efek samping audit. Alternatif paywall perlu commercial approval dan transisi/grandfathering eksplisit.

**State/role:** commercial/product menentukan penawaran; A/Q/V/R dan existing permissions tetap pada pilihan copy. Dental edit tetap memakai AddonService existing; paid/manual/trial tidak disamakan. Price/period history tidak ditulis ulang.

**Error/kapasitas/notifikasi/AC:** uji Free/trial/paid/grant/expired/overage/addon, renewal/future periods, akhir kalender trial dan IN_PROGRESS continuity. Copy tidak menjanjikan rights yang tidak ada; jika paywall dipilih, signal access change melalui server S2 dengan draft preservation, bukan reload/logout. Tidak ada price/policy baru dianggap disetujui.

## P-D17 — Permission customization (OPTIONAL)

**Rekomendasi:** pertahankan lima role dan capability spesifik teruji. Custom role editor tidak diperlukan untuk PR-01/handoff spesifik. Owner/product/security memilih scope jika kebutuhan nyata muncul.

**State/impact:** A/Q/V/R, seat/plan/history tetap. Jika nanti dipilih, ACL mutations wajib configured S2 APIs dan tenant boundaries; revoke tidak memulihkan HTTP saat transport pending.

**AC/risiko:** tidak ada owner/admin otomatis menjadi dokter; role naming tidak mengubah authorship. Negative role/tenant tests dan stale socket harus tetap lulus. Tidak merancang schema/editor generik sekarang; notifikasi hanya security status sesuai source.

## P-D18 — Display/kiosk (OPTIONAL)

**Rekomendasi:** public display nonmedis existing cukup untuk scope awal. Device management/kiosk action tidak ditambahkan tanpa kebutuhan klinik dan owner/product approval.

**State/impact:** read-only display tidak mengubah A/Q/V/R, quota atau entitlement; public payload whitelist tetap enam field. Histori/device access policy hanya bila scope diperluas.

**AC/risiko:** tidak menampilkan nama/RM/NIK/SOAP, tidak memberi tombol call/check-in publik, disconnect/reconnect display tidak memalsukan state queue. Uji public access dan whitelist jika UI display disentuh; privacy/real device assumptions tidak disimpulkan dari desktop browser saja.

## P-D19 — Domain opsional lainnya (OPTIONAL)

**Rekomendasi:** tunda directory, multi-branch, Edge, inventory/claims dan drug catalogue sebagai proyek terpisah yang dipilih satu per satu. Tidak menggabungkannya ke PG-01–16 dengan alasan kelengkapan.

**State/impact:** tidak ada perubahan A/Q/V/R, quota, data historis, user access atau notification pada scope sekarang. Identity across clinics bukan auto-merge; Edge bukan polling offline.

**AC/risiko:** backlog clinical+SaaS satu lokasi tetap dapat selesai tanpa models branch/ledger/inventory; acceptance scope tidak diam-diam menambahkan external clinical integration. Expansion perlu use case, actor, data owner, commercial/clinical risk dan persetujuan baru sebelum schema.

## P-D20 — UX dan pembuktian pemahaman (PROPOSED; sebagian teknis aman)

**Rekomendasi:** pesan status/empty/loading/error berdasarkan source, task path per role dan draft preservation menjadi acceptance minimum. Human usability study terpisah dengan peserta nyata, consent dan tugas yang dipilih product/UX; agent review bukan studi manusia.

**State/impact:** tidak mengubah A/Q/V/R, quota, price, permission atau history. Read-only impact previews tidak memberi izin mengeksekusi perubahan yang masih gated.

**Error/notifikasi/AC:** 401/419 ≠ offline, 409 ≠ sukses; old input/caret/keyboard/mobile dipertahankan; pending transport bukan confirmed. Uji 375/768/1024/1440, empty catalog, no linked clinic, failed request, screen labels dan keyboard path. Copy yang hanya menjelaskan behavior existing aman secara kebijakan tanpa persetujuan bisnis baru, **tetap menunggu instruksi implementasi terpisah**; studi/aksi baru memerlukan scope approval.
