# State transition matrix

Status **EXISTING** berarti transisi ditemukan pada method yang disebut; bukan hasil run baru. A/Q/V/R = appointment/queue/visit/RME. `—` berarti belum ada resource atau tidak berubah, bukan state baru. Sumber file lengkap ada pada katalog E [register](EXISTING_BUSINESS_RULES.md).

## Transisi existing

| Aksi / pelaku | Prasyarat | A sebelum→sesudah | Q sebelum→sesudah | V / R | Guard, efek dan bukti |
| --- | --- | --- | --- | --- | --- |
| Book: admin/rec atau Patient authenticated | Clinic/doctor/service/schedule valid; slot, tanggal, entitlement | —→BOOKED | —→BOOKED | Belum dibuat | Snapshot, UUID, queue number dan notification atomik; BookingService::book |
| Replay book | UUID existing clinic sama, schedule awal lookup masih lolos | Tetap | Tetap | Tetap | Tidak rewrite payload; bukan operasi reschedule; BookingService::book |
| Check-in: admin/rec | Hari queue, queue.manage | BOOKED→BOOKED | BOOKED→WAITING | Belum dibuat | checked_in_at; QueueWorkflowService::checkIn/transition |
| Call: admin/rec, Doctor assigned | Hari queue, FIFO, tidak ada CALLED/IN_PROGRESS lain pada sesi sama | Tetap BOOKED | WAITING→CALLED | Belum dibuat | Schedule+queue lock; `call` |
| Skip: actor queue.call | Hari queue | Tetap | CALLED→SKIPPED | Belum dibuat | skipped_at; `skip` |
| Return: actor queue.call | Hari queue | Tetap | SKIPPED→WAITING | Belum dibuat | Nomor lama dipertahankan; `returnToWaiting` |
| Appointment cancel: Patient/legacy token atau staf route appointment | BOOKED, belum visit, sebelum session start, entitlement writable | BOOKED→CANCELLED | BOOKED/WAITING/CALLED/SKIPPED→CANCELLED | Tetap tidak ada | Booked service cancel checks queue/visit juga; dua status notification; BookingService::cancel |
| Queue cancel: admin/rec | queue.manage, empat state pre-visit; tidak ada cutoff jam di method | BOOKED→CANCELLED pada jalur normal | BOOKED/WAITING/CALLED/SKIPPED→CANCELLED | Tidak dibuat | Reason nullable; queue notification; QueueWorkflowService::cancel |
| No-show: admin/rec | Awal sesi telah tiba; empat state pre-visit | BOOKED→NO_SHOW | BOOKED/WAITING/CALLED/SKIPPED→NO_SHOW | Tidak dibuat | Tidak otomatis melepaskan reserved quota booking; `markNoShow` |
| Start: Doctor assigned | CALLED, hari queue, start entitlement | Tetap BOOKED | CALLED→IN_PROGRESS | —→IN_PROGRESS; —→DRAFT | Unique visit/appointment; VisitWorkflowService::start |
| Save/autosave: Doctor assigned | IN_PROGRESS, DRAFT, expected R/allergy version | Tetap | Tetap IN_PROGRESS | V tetap; R DRAFT→DRAFT, version+1 | Data/foto menurut mode submit; `saveDraft/syncRecord` |
| Complete: Doctor assigned | IN_PROGRESS, Q IN_PROGRESS; fields/version/allergy valid | BOOKED→COMPLETED | IN_PROGRESS→COMPLETED | IN_PROGRESS→COMPLETED; DRAFT→FINAL | Atomik, completed_at, event setelah commit; `complete` |
| Complete replay | Route/request policy masih valid, V telah completed | Tetap | Tetap | Tetap COMPLETED/FINAL | Service tidak menyelesaikan dua kali; request validation tetap lebih dahulu |
| Correct final: Doctor assigned | Completed, permission, reason10–500, expected versions, writable subscription | COMPLETED→COMPLETED | Tetap COMPLETED | V tetap; R FINAL→FINAL version+1 | Before/after revision; `correct/snapshot` |
| Save allergy | Assigned V IN_PROGRESS; patient lock | Tidak berubah | Tidak berubah | R tidak otomatis diubah; allergy hash berubah | Clinical detail edit membuat assertion baru, old inactive; `saveAllergy` |
| Deactivate account | Admin sah; readonly seat recovery owner | Tidak otomatis berubah | Tidak otomatis berubah | Draft/final/history tetap | Epoch+revocation; physician tidak lagi dapat HTTP; StaffAccountService::update/User::save |
| Inactive clinic/schedule | Admin sah | Histori tetap | Histori tetap | Tetap | Booking baru ditolak sesuai guard; bukan reschedule/abort otomatis |
| Subscription expiry | Effective end tercapai | Tetap | Tetap | Existing IN_PROGRESS general save/finish exception | Not all writes exempt; completed correction tidak dikecualikan; middleware |
| Revoke private rights | Supported security mutation committed | — | — | — | HTTP latest; WS pending→confirmed hanya actual close+write fence+durable ACK; S2 |

Transisi invalid harus menolak tanpa mutasi parsial; 409/version conflict bukan izin memilih last-write-wins. Check-in tidak berarti visit sudah ada; appointment BOOKED tidak membuktikan belum ada visit—itulah alasan DFU-01 juga memeriksa visit/queue.

## State baru: PROPOSED, belum diimplementasikan/disetujui

| Keputusan | Mapping rekomendasi | Efek quota/slot | Hak/konflik yang perlu diputuskan |
| --- | --- | --- | --- |
| D-01 replacement | A/Q lama→CANCELLED dengan alasan+replacement link; A/Q baru→BOOKED; V/R tidak ada | Old reservation dilepas hanya jika target berhasil; target capacity diverifikasi | BOOKED/WAITING/SKIPPED; CALLED harus ditarik sah dahulu; Patient self-service belum dibuka |
| D-02 exception | Resource blackout ACTIVE→REVOKED/expired sesuai interval; A/Q/V/R **tidak** berubah otomatis | Target overlapping diblok booking baru | Existing impacted diproses eksplisit; tidak pakai NO_SHOW untuk pasien yang kliniknya tutup |
| D-03 pause/resume | V/Q IN_PROGRESS↔PAUSED; A tetap BOOKED; R DRAFT tetap/versioned | Rekomendasi hold active slot; monthly started count tetap satu | State baru harus masuk active-call query, middleware continuity dan UI |
| D-03 interrupted | A/Q/V→INTERRUPTED, bukan completed; R belum final tetap tersimpan/terkunci menurut SOP | Slot dilepas; tidak menghasilkan completed metric palsu | Pelaku klinis, alasan, unfinished documentation dan pasien diberi tahu apa |
| D-03 handover | Old episode interrupted; handoff requested→accepted/rejected; new linked A/Q/V dibuat sesuai target setelah accepted | Episode/quota entitlement continuity harus disetujui; tidak backdate created_at | Unique visit/appointment membutuhkan A baru atau schema model alternatif yang disepakati |
| D-04 follow-up | OPEN→BOOKED→COMPLETED; CANCELLED explicit; cancel/no-show booking→review/OPEN jika dipilih | Task sendiri tidak reserve slot | Complete task berdasarkan actual completed visit, tidak otomatis saat book |
| D-07 issued document | Final R version→issued document; correction→superseded+issued version baru | Tidak mengubah visit quota | Reprint versi lama tetap teridentifikasi; patient release allowlist |
| D-10 targeted reversal | Selected manual-grant period→revoked/reversed dengan histori; period sah lain tetap | Entitlement effective dihitung ulang, bukan global Free | Fallback, add-on, scheduled periods, approval dana terpisah |
| D-13 allergy lineage | Parent current→superseded + child current atomik | Tidak mengubah visit quota | Satu current per lineage; stale expected-parent409 |
| D-14 repair | Diagnosed→preview approved→applied atau stale/rejected | Tergantung kategori, harus explicit | Tidak mengubah final R/commercial truth otomatis |

State proposals tidak boleh ditambahkan ke enum hanya untuk memperbaiki tampilan. Pemilik perlu menyetujui makna A/Q/V/R bersama; jika mapping alternatif dipilih, revisi kontrak/metrik/AC sebelum coding.

## Transisi komersial existing

| Peristiwa | Status / hasil | Syarat / histori |
| --- | --- | --- |
| Start trial | Trialing Team | Sekali per clinic, sebelum paid/grant/legacy; trial history tetap |
| Trial berakhir | Free | Refresh cache menurut periode; tidak reset trial_used |
| Paid period aktif / end | Active / Expired | Interval start-inclusive/end-exclusive; scheduled future tidak aktif lebih awal |
| Suspended | Suspended mengambil precedence | Enum/domain ada, UI suspend lifecycle belum menjadi workflow lengkap |
| Same/higher-rank manual grant | Period baru di nextPeriodStart | UUID intent dedup; tidak selalu upgrade seketika |
| Lower-rank grant / downgrade Free | Revoke current/future periods sesuai service; apply baru | Bukan selective reversal; D-10 jangan reuse sebagai undo |
| Signed payment SUCCESS | Paid + activate validated period/items | Amount/request/invoice/tenant valid; replay/out-of-order tidak double activate |
| Expired payment menerima SUCCESS terlambat | Review | Tidak otomatis activate |
| Refund recorded/provider REFUNDED | Refunded; addon revoke | Main period tidak otomatis direvoke; D-10 memutus kebijakan terpisah |

Sumber: SubscriptionService::startTrial/grant/statusAt/refreshStatus/activatePaid/downgradeToFree; DokuPaymentService::processProviderPayload/recordManualRefund; AddonService::activate/revoke.
