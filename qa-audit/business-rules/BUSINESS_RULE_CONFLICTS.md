# Konflik aturan dan efek samping sebelum implementasi

Ini **analisis statis**, bukan daftar bug baru yang telah direproduksi. Sebagian perbedaan bisa merupakan kebijakan yang disengaja, tetapi belum disahkan sebagai kontrak lintas kanal. ER/E merujuk [register source](EXISTING_BUSINESS_RULES.md). Rekomendasi tidak membuka ulang status verified dari QA sebelumnya.

## Konflik substantif

| ID | Fakta yang bertemu | Risiko bila implementasi naif | Penyelesaian yang direkomendasikan / gate |
| --- | --- | --- | --- |
| C-01 | A tetap BOOKED ketika V/Q sudah IN_PROGRESS; BookingService::cancel memeriksa Visit | Memakai A status saja membatalkan pelayanan aktif dan melanggar DFU-01 | D-01 selalu no-Visit + Q guard; D-03 untuk after-start, tidak reuse cancel |
| C-02 | Schedule berhistori immutable, inactive template masih overlap, booking mengecek current hours | Edit jam lama memindahkan arti histori; replacement template baru bisa tertolak | D-02 calendar exceptions + effectivity weekly replacement yang disepakati; pertahankan DFU-02/CCL-05 |
| C-03 | Explicit patient_id mensyaratkan active, linked self-book tidak | Scope nonaktif tidak konsisten; “fix” fallback membuat orang baru | D-06 pilih makna; pertahankan CCL-04 dan PG-17, tanpa auto-reactivate/link |
| C-04 | Generic appointment cancel sebelum session start; staff queue cancel tanpa cutoff; no-show boleh sejak awal; check-in/call same-day saja | Dua menu memberi hasil berbeda; exception/reschedule dipaksakan lewat cutoff yang salah atau pasien ditandai no-show karena klinik tutup | D-01/02 menetapkan operational override; public cancellation tetap; late-check-in/no-show grace tidak dikarang |
| C-05 | Monthly quota menghitung started visits; booking lama dapat grandfather start; new replacement created_at baru | Cancel-rebook menghilangkan eligibility sah atau membuka bypass quota bila created_at dibackdate | D-01/D-03 explicit audited lineage eligibility; default source belum carry; jangan mengubah created_at untuk menyiasati quota |
| C-06 | Unique visit.appointment_id dan policy mengikuti doctor_id | Handover dengan overwrite mencabut akses author lama, memberi akses raw RME tanpa consent/scope, atau melanggar unique constraint | D-03 pilih episode model; new A+V linked atau assignment-history penuh, bukan FK patch |
| C-07 | Active call dibatasi per sesi, ETA menghitung completed_at−started_at dan jumlah sample | Pause/new sessions dapat memberi dua active examination dokter atau ETA yang menghitung jeda sebagai tindakan | D-03 putuskan active-slot semantics lintas sesi dan pause-duration metric; jangan mengklaim current guard global doctor-wide |
| C-08 | Complete request wajib subjective/assessment/plan; vital/diagnoses tidak semuanya wajib | “Lulus validasi” disalahartikan clinical completeness/otorisasi dokumen yang universal | Clinical lead menilai D-03/07 dan SOP; tidak otomatis menambah mandatory medical fields dari pendapat teknis |
| C-09 | Final photos/revisions dipertahankan, tetapi schema memiliki cascade clinic; no delete policy | Lifecycle tenant/raw repair menghapus clinical history atau memutus foto revisi | D-11/14 no hard delete; retention/release policy terpisah, jangan menyamakan FK cascade dengan izin bisnis |
| C-10 | RME version+allergy hash existing; allergy update tidak expected-parent | Two successors aktif; hash mendeteksi perubahan tetapi tidak memutuskan current assertion | D-13 lineage/clinical adjudication; jangan last-write-wins atau mematikan critical warnings otomatis |
| C-11 | IN_PROGRESS general finish exempt expiry; dental canEdit memerlukan paid+addon current | Menjanjikan finish seluruh dental setelah expiry atau menyamakan manual grant dengan paid addon | D-03/16 continuity scope terpisah; preserve read historical dental; clinician SOP untuk unsaved dental |
| C-12 | Booking replay hanya membandingkan clinic, berbeda dengan grant UUID yang mengikat actor/intent | UUID dianggap sebagai authorization universal; changed payload atau actor tidak diperiksa pada branch booking existing | Untuk command baru, bind actor/tenant/intent/resource+version; characterize existing booking replay sebelum perubahan security terpisah; preserve DFU-12/PG-18 semantics |
| C-13 | Refund record merevoke add-on, main period tidak otomatis; global downgrade merevoke banyak periode | Undo satu grant memotong paid/future periods atau dicatat sebagai uang benar-benar kembali padahal hanya record | D-10 targeted reversal, financial action terpisah; tidak invent fallback/grace |
| C-14 | Queue private dibaca staf nonmedis dan public board nonmedis; old user channels dilindungi S2 | Menaruh SOAP/handoff content di queue payload atau mengandalkan JS logout saat assignment berubah | D-03/04/07 fetch dokumen via policy; push hanya metadata allowlist; pertahankan S2 server fence |
| C-15 | Dashboard menghitung semua appointment periode; reschedule menambah old cancelled+new booked | Gross booking count naik, follow-up bisa ditutup hanya karena book, laporan cash palsu | D-04/08 pilih metrik initial intent/replacement/active/completed terpisah; service value tetap bukan cash |
| C-16 | S2 mencabut private authority/account epoch; clinical responsibility bukan account role | Menonaktifkan dokter untuk “handoff”, atau memulihkan akun agar draft bisa selesai | D-03 narrow clinical delegation; account revocation tetap efektif; in-flight HTTP dan object reassignment harus diuji terpisah, bukan diasumsikan diterminasi WS |
| C-17 | Premium flags tidak enforced; capacity memakai current plan relation sementara price/name period disnapshot | Paywall atau perubahan katalog menyentuh entitlement lama tanpa transisi, padahal metadata price history tampak immutable | D-16 pilih contract; jangan mengubah capacity/flags sebagai bagian viewer D-09. Jika limit katalog kelak diedit, putuskan grandfathering/version dahulu |
| C-18 | Autosave tidak menyimpan files; 401/419/409 menjaga editor; realtime refetch partial | “Recovery” reload/logout paksa membuang draft/pending photo, feedback ditimpa refetch | D-20 dan semua UI baru menjaga CCL-02/07, DFU-03/04; pending file bukan sudah uploaded |
| C-19 | ClinicController::update menghapus public logo lama di dalam DB transaction, sedangkan clinical photo cleanup after-commit/rollback | DB rollback tidak memulihkan file logo lama; jangan menganggap semua file transactional | Risiko teknis statis, bukan bug runtime baru verified. Characterization terpisah sebelum perbaikan; tidak mengubah medical storage/history atau memperluas PG |
| C-20 | StorePatientRequest unique NIK per clinic, BookingService staff-new memakai candidate review tanpa unique NIK yang sama | Normal intake vs walk-in mengizinkan identitas berbeda; blanket unique/merge menyatukan orang tanpa verifikasi | D-06/14 menyepakati identity intake rule; phone shared tetap boleh, tidak bulk rewrite/dedup legacy |
| C-21 | owner_user_id explicit, User::isClinicOwner memakai account_type; owner payment check hanya clinic_id; ClinicRegistrationService tidak mengubah account_type | Legacy/orphan create/transfer dapat menghasilkan FK owner vs commercial flag yang berbeda jika jalur tidak menjaga invariant | D-11/14 diagnosis konsistensi + verifikasi owner; jangan auto-grant ownership saat repair. Tidak ada data pengguna diperiksa |
| C-22 | BookingService memeriksa Doctor.is_active dan User.is_active, bukan role Doctor/verified email; HTTP doctor routes memerlukan role+verified | Dokter dapat terlihat bookable sementara belum dapat mulai pelayanan setelah role dicabut atau verifikasi belum selesai | D-02/15 membedakan bookable, provisioned, verified dan clinically ready; putuskan booking future sebelum invitation/verification. S2 melindungi akses, bukan otomatis menyelaraskan katalog availability |
| C-23 | Appointment menyimpan tanggal+sesi tanpa exact start pasien; usulan exception dapat berupa interval parsial | Sistem menebak pasien yang terdampak atau auto-split sesi/kuota dan melanggar histori | D-02 release awal block seluruh overlapping session untuk new booking, semua existing session bookings masuk preview review; granular split butuh keputusan lain dalam D-02 |

## Analisis tindakan bersamaan

Kolom hasil adalah **kontrak yang harus diuji saat implementasi**, bukan klaim bahwa semua race sudah dieksekusi. Row locks/unique constraints existing adalah bukti source; uji epoch MySQL post-S2 tidak membuktikan seluruh race bisnis di tabel ini.

| Tindakan bersamaan | Keputusan/guard | Hasil yang diwajibkan dan regression |
| --- | --- | --- |
| Cancel vs start | DFU-01; queue+clinic+appointment locks | Satu menang: cancelled tanpa Visit atau started dengan cancellation ditolak; tidak A cancelled+V in-progress |
| Dua booking target quota1 | BookingService clinic/schedule lock + reserved count | Maksimal satu booking accepted; tidak duplicate queue number; jangan mengandalkan SQLite untuk MySQL locking |
| Dua petugas reschedule old A sama | D-01 expected version/operation lineage | Satu replacement, kedua409/replay yang sama; tidak dua patients/queues |
| Reschedule vs dokter mulai | D-01/03 | Start menang→move ditolak; move menang→old start ditolak; draft tidak hilang |
| Reschedule vs cancel/no-show | D-01 source-state recheck | Satu terminal intent; old/new link konsisten, tidak replacement active dari old no-show tanpa izin |
| Move antar sesi/doctor berlawanan | Ordered lock design | Hindari inverse target/source locks; rollback/retry aman, tidak publish intermediate state |
| Blackout vs booking/reschedule | D-02 shared availability boundary | Jika blackout efektif lebih dahulu, target ditolak; jika booking lebih dahulu, masuk impact list—tidak silent cancel |
| Weekly update vs booking | DFU-02/CCL-05 | Booking mengikuti sesi yang dikunci; immutable fields tidak berubah setelah history muncul |
| Patient deactivate vs linked booking | D-06 locked patient/context | Aturan pilihan berlaku pada commit; tidak fallback/new profile; existing care tetap |
| Autosave vs complete/correction | R lock_version | Satu versi menang; lainnya409 atau idempotent completed path; no stale overwrite |
| Allergy edit vs prescription final | Patient lock + allergy hash | Klinisi meninjau critical terbaru; final stale ditolak, bukan memakai acknowledgement hash lama |
| Dua edit parent allergy | D-13 expected-parent | Satu successor current, lainnya konflik review; two different lineages tetap boleh |
| Pause/resume vs complete | D-03 state+version | Tidak V PAUSED dengan Q COMPLETED; blocked editor tetap menyimpan input |
| Dua receiver menerima handoff | D-03 request version + unique accepted receiver | Satu penerima/episode, tidak dua aktif dari intent sama |
| Receiver dinonaktifkan saat accept | Fresh account/assignment authority | Authority divalidasi ulang dalam command; no medical access bypass untuk account inactive |
| Expiry/refund vs save/final | Existing general exception + current addon | General in-progress tetap selesai; dental edit yang sudah tidak sah ditolak tanpa menghapus historical chart |
| Grant/revoke vs payment callback | D-10 period lock, UUID/provider dedup | Tidak merevoke paid period lain atau duplicate activation; stale commercial preview409 |
| Publish backlog vs revoke ACK | S2 server guard/fence | Old private sends sesudah ACK nol, controls sah tetap; bukan hanya client disconnect |
| Worker crash setelah publish sebelum job delete | At-least-once notification | DB satu notification ID; raw duplicate mungkin; client action dedup, bukan exactly-once claim |
| Repair vs finalize/refund | D-14 fingerprint/expected version | Repair stale ditolak; tidak menimpa clinical/commercial state terbaru |

Existing lock order belum seragam: book clinic→schedule, queue call schedule→queue, visit start queue→clinic, cancel booking clinic→appointment→queue. Ini risiko deadlock statis yang ditangani sebagian oleh transaction retry, bukan bukti corruption yang terjadi. Command multi-resource baru harus merancang urutan konsisten dan menguji actual MySQL sebelum mengklaim concurrency aman; jangan merefaktor semua services pada tahap analisis.

## Perlindungan hasil audit verified

| Temuan | Kontrak yang harus dipertahankan |
| --- | --- |
| DFU-01 | Visit existing/IN_PROGRESS tidak dapat generic cancel; recovery memakai policy/state yang disetujui |
| DFU-02 | Doctor/day/start/end sesi berhistori immutable |
| DFU-03 | Action error tidak hilang karena realtime refresh |
| DFU-04 | Cancel failure dan input reason tetap terlihat |
| DFU-05 | Server phone errors tampil, tidak hilang di duplicate UI |
| DFU-06 | Shared phone validation, tidak menerima alphabet sebagai nomor |
| DFU-07 | Effective date-range divalidasi dan old filter tetap |
| DFU-08 | Timestamp clinic-local; jangan rewrite instants historis |
| DFU-09 | Programmatic labels/focus untuk controls baru/lama |
| DFU-10 | Tidak horizontal overflow mobile; jangan menyembunyikan data dengan global overflow hack |
| DFU-11 | Patient memiliki jalur booking existing yang jelas; bukan janji guest intake baru |
| DFU-12 | Grant UUID replay/different intent, histori dan single audit tetap |
| CCL-01 | Price input/backend range konsisten; jangan invent rounding/cash policy |
| CCL-02 | 401/419 beda dari offline; draft/pending photo tidak dibuang |
| CCL-03 | Satu separator normalizer, lookup legacy 62/0, tanpa rewrite massal |
| CCL-04 | Inactive/foreign bukan silent new patient |
| CCL-05 | Current clinic hours menolak new out-of-hours; old booking tetap |
| CCL-06 | Status efektif period, bukan cache label stale; future/history utuh |
| CCL-07 | Filter DOM/value/caret dipertahankan |
| CCL-08 | Empty catalog informatif, checkout tidak ditawarkan |
| PG-17 | Paging9/10+, cursor forged/stale/foreign ditolak, review token dan no auto-merge/reactivate |
| PG-18 | Valid Alpine binding, disabled pending, validation retry, UUID/history tetap |
| PR-01 | Server epoch, fresh guards, actual-close delivery fence ACK; single-node, draft preservation |
| PR-02 | Semantik JSON strict, tipe/field dan meaningful array order; bukan object key order |
| PR-03 | Factory code sesuai schema, deterministic boundary; jangan memperbesar kolom untuk Faker |

Referensi status: [DFU](../deep-functional-ux/FIX_REGRESSION_REPORT.md), [CCL](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md), [post-S2 regression](../post-s2-verification/FULL_REGRESSION_RESULTS.md). Temuan tersebut tetap verified pada scope historisnya; analisis ini tidak menjalankan ulang test dan tidak menandai skenario PG baru PASS.
