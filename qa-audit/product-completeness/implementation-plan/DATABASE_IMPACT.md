# Dampak backend dan database

Tanggal: 14 November 2026. **Rancangan additive bersyarat, bukan migration yang telah dibuat/disetujui/dijalankan.** Tidak membuka database aplikasi/testing pada tahap perencanaan; struktur existing dirujuk dari source/model/migration dan [schema fixture audit](../evidence/schema.json). Tipe FK mengikuti tipe kolom existing saat implementation, bukan menebak dari SQLite saja.

## Inventaris perubahan per gap

| ID | Dampak minimum yang diusulkan | Perlu schema baru? | Gate |
| --- | --- | --- | --- |
| PG-01 | AppointmentChange orchestration + link replacement, expected fingerprint dan request UUID | appointment_replacements | D-01 |
| PG-02 | Read-only impact query; exception tanggal untuk klinik/dokter | Tidak untuk preview; availability_exceptions untuk blocking | D-02 |
| PG-03 | State non-completed, workflow events; continuation jika dipilih | Enum/status consumers + workflow_version/event ledger; continuation table hanya model terpilih | D-03A/B |
| PG-04 | Task kontrol dengan relasi episode asal/booking tujuan | follow_up_requests | D-04 |
| PG-05 | Tidak ada delivery kasir dalam scope ini | **Tidak dibuat**; Payment SaaS tidak dipakai ulang | D-05 scope |
| PG-06 | Eligibility predicate terpusat terhadap flag existing | Tidak jika satu makna is_active dipilih | D-06 |
| PG-07 | Snapshot dokumen final dan supersession, endpoint private | clinical_documents | D-07 |
| PG-08 | Query/stream CSV nonmedis dari sumber existing | Tidak untuk versi minimum | D-08 |
| PG-09 | Read projection periods/payment/add-on/audit terotorisasi | Tidak | D-09 |
| PG-10 | Revoke selected manual grant + reversal idempotent | subscription_grant_reversals; revoked_at period sudah ada | D-10 |
| PG-11 | Lifecycle transfer/suspend yang benar-benar dipilih | Belum menetapkan schema siap-eksekusi; mungkin ledger lifecycle terpilih | D-11 scope |
| PG-12 | Persistent dedup dan trace reminder terpilih | appointment_reminder_deliveries atau ekuivalen yang memenuhi unique contract | D-12 |
| PG-13 | Single-current lineage jika dipilih; legacy unresolved | patient_allergy_lineages + nullable relasi/version pada patient_allergies | D-13 |
| PG-14 | Detector zero writes; apply spesifik kategori | Tidak untuk detector; repair ledger hanya setelah apply dipilih | D-14 |
| PG-15 | Password broker/verification existing terlebih dahulu | Tidak otomatis; invite ledger hanya jika kontrak butuh | D-15 scope |
| PG-16 | Copy sesuai kontrak atau entitlement guard | Tidak untuk copy/guard dasar; snapshot invoice lama tetap | D-16 |
| PG-17 | Shared matching query + pagination/token context | Tidak; tidak membuat tabel deduplikasi identitas | Siap |
| PG-18 | Blade/Alpine expression dan pending feedback | Tidak | Siap |
| PR-01 | Signed identity/version, server guards, durable terminate retry | users.realtime_auth_version default 0; job/audit existing | PR-01/T + spike S1 |

## PG-01: replacement tanpa rewrite snapshot

Usulan `appointment_replacements`:

- id; clinic_id; old_appointment_id; new_appointment_id; request_id UUID; request_fingerprint; actor_user_id; reason; created_at. Snapshot preview yang diperlukan untuk audit hanya nilai administratif, bukan SOAP atau token.
- UNIQUE old_appointment_id (satu pengganti langsung untuk satu booking); UNIQUE new_appointment_id; UNIQUE request_id. Rantai A→B→C sah, cabang A→B dan A→C tidak. FK ke appointment/user/clinic; layanan memverifikasi seluruh FK satu clinic dan patient_id sama. FK id saja bukan bukti tenant.
- Tidak menambah enum REPLACED pada Appointment bila cukup CANCELLED + relation/reason. Booking lama mempertahankan doctor/schedule/date/service_name/price asli; perubahan status/alasan adalah tindakan eksplisit teraudit, bukan mengubah fakta historis.
- Idempotency record dan old cancel/new booking/new queue/audit/delivery jobs berada dalam transaksi yang sama. Event old/new baru dapat keluar setelah commit. UUID sama dengan fingerprint berbeda ditolak; link tidak mengembalikan secret booking baru kepada caller asing.

Relasi ini **hanya pra-visit**. Episode lanjutan PG-03 bukan PG-01 replacement terhadap appointment yang telah mempunyai visit.

## PG-02: exception, bukan perubahan massal appointment

Usulan `availability_exceptions`: id, clinic_id, nullable doctor_id (null = seluruh klinik), starts_at UTC, ends_at UTC eksklusif, timezone saat input, reason operasional privat, created_by_user_id, cancelled_at/by/reason, lock_version, timestamps. Minimal hanya blok unavailable; tidak memperluas jam/kapasitas di luar clinic settings. Dukungan override jam tambahan perlu D-02 tersendiri.

Index pencarian clinic_id/doctor_id/starts_at/ends_at dan cancelled_at sesuai query yang benar-benar digunakan. End harus setelah start; doctor berasal klinik sama; interval tidak kosong. Duplicate/overlap tidak otomatis membatalkan exception sebelumnya: validasi atau union blocking menurut D-02, dengan pesan yang jelas.

Impact dihitung dari tanggal appointment dan sesi/queue snapshot yang tersedia. Tidak menambahkan kolom “sudah ditangani” yang bisa bertentangan dengan status booking; derive dari replacement/cancel/terminal, dan simpan catatan komunikasi hanya jika memang dipilih D-02. Jika jadwal underlying sudah berubah, tampilkan “perlu ditinjau” dan jangan menebak waktu appointment lama.

## PG-03: dua paket schema yang harus dipilih

**F5A rekomendasi**: tambah status PAUSED/INTERRUPTED pada Visit dan Queue; appointment INTERRUPTED terminal jika D-03A menyetujuinya. Usulan workflow_version pada visit + `visit_workflow_events` berisi clinic_id, visit_id, actor, request UUID/fingerprint, from/to state, reason, expected version dan waktu. Draft tetap di medical_records, bukan dipindah ke AuditLog. Tambah paused_at/interrupted_at hanya bila query/status memerlukan; event menjadi riwayat utama.

Semua enum readers harus mengenali nilai baru sebelum command dapat menulisnya: active-slot constraint/index/generated expression queue, isTerminal, FIFO/return/start, dashboard/ETA, notifications, RME policies, reports, subscription guards. Inspect definisi constraint MySQL aktual saat membuat migration; jangan menganggap menambah PHP enum cukup. PAUSED bukan COMPLETED dan bukan CANCELLED; INTERRUPTED tidak menambah jumlah layanan selesai. RME draft interrupted menjadi read-only berdasarkan visit state; tidak dipalsukan FINAL. Existing final/revision tetap berlaku.

**F5B alternatif yang direkomendasikan, menunggu D-03B**: `visit_continuations` dengan clinic_id, from_visit_id, nullable target_appointment_id/to_visit_id, requesting/receiving doctor, requester/acceptor, status (requested/accepted/declined/cancelled/linked), reason, private handoff snapshot yang disetujui, request UUID/fingerprint, timestamps/version. UNIQUE target episode bila sudah ditautkan; satu continuation efektif dari satu source sesuai aturan D-03B, bukan unique semua request sehingga penolakan tidak menghalangi pengajuan baru. Dilarang mengubah from_visit.doctor_id, original author atau Rx draft menjadi milik dokter baru.

Old visit dapat tetap PAUSED sambil menunggu; acceptance harus menyelesaikan transisi old→INTERRUPTED dan membuat/menautkan episode baru secara atomik sesuai keputusan. Jika target gagal dibuat, old episode/permintaan tidak setengah selesai. Packet handoff hanya tersedia untuk assigned recipient yang menerima sesuai policy; belum tentu seluruh SOAP source. Existing unique visit per appointment tetap. New appointment memakai BookingService; **bukan memanggil PG-01 cancel-for-replacement pada visit aktif**. Kuota, slot/check-in, tariff/continuation dan entitlement expired belum diputuskan: tidak menambah flag bypass sebelum keputusan.

Jika dipilih satu visit dengan current assignee, ganti desain ini dengan assignment history + attribution konten dan policy per fase; jangan membuat kedua model sekaligus. Nullable current_doctor_id saja tidak cukup menjaga penulis isi lama. Paket handover menunggu pilihan ini; tidak membenarkan tabel spekulatif.

## PG-04 dan PG-07: data setelah pelayanan

`follow_up_requests`: id, clinic_id, source_visit_id, responsible_doctor_id, patient_id, due_from/due_to sesuai presisi D-04, instruction yang memang boleh dilihat pasien, scheduling metadata nonmedis, status/version, nullable target_appointment_id/completed_visit_id, created_by, cancellation reason, request UUID/fingerprint, timestamps. Satu request idempotent bukan satu task seumur hidup per pasien. PII/clinical instruction tidak disalin ke audit/broadcast. Perubahan link setelah reschedule diaudit; selesai hanya setelah visit tujuan selesai menurut D-04. Appointment cancelled/no-show tidak menghilangkan task. Tidak membuat appointment otomatis di luar batas booking yang ada.

`clinical_documents`: id/identifier, clinic_id, source_medical_record_id, source version, type, issuer_user_id, issued_at, immutable approved_payload, nullable supersedes_document_id, request UUID/fingerprint, timestamps. Unique request UUID; nomor manusia dan dedup source-version-type ditentukan D-07 (reprint tidak harus menjadi penerbitan baru). Reprint membaca snapshot, bukan live mutable clinic/prescription. Supersession tidak mengganti payload lama. Endpoint ber-policy dan private response; tidak menggunakan public storage untuk artefak medis. HTML print dari snapshot cukup untuk minimum; file/PDF backend dan tanda tangan tidak otomatis dibuat.

## PG-10: correction terpilih, bukan menghapus period

`subscription_grant_reversals`: id, clinic_id, subscription_period_id, actor_user_id, request_id/fingerprint, reason, effective_at, before/after administratif, timestamps. UNIQUE period untuk reversal final satu target dan UNIQUE request_id. SubscriptionPeriod.revoked_at sudah ada; jangan menambah kolom revoke kedua. Simpan snapshot source/price/dates yang diperlukan untuk audit; jangan memindahkan masa berlaku semua period agar terlihat contiguous.

Pengaruh AddonPeriod dibatasi yang terkait target subscription_period_id. Payment/payment events/invoice snapshots tetap; record reversal bukan bukti dana provider kembali. Hak setelah periode aktif direvoke dan refund paket utama menunggu D-10. Operasi tidak menggunakan downgradeToFree global sebagai undo. Audit metadata dan reversal tidak memuat isi klinis.

## PG-13: pilihan single-current yang tidak menebak legacy

Usulan bila D-13 memilih single-current: `patient_allergy_lineages` (patient_id, current_allergy_id nullable, lock_version, actor/timestamps) dan nullable lineage_id/supersedes_id/version pada patient_allergies. Satu pointer current di lineage, version unik per lineage, dan semua row patient sama. Update status active/current dan child baru harus atomik. Jika perlu alasan resolusi, simpan pada event/history klinis yang ber-policy, bukan public metadata.

Record legacy dibiarkan lineage null/unresolved; banner tetap menampilkan informasi aktif yang ada. Jangan menyatukan berdasarkan substance, waktu, nama atau kedekatan ID. Relasi previous_allergy_id audit hanya bukti pendukung, bukan izin backfill otomatis. Resolusi dilakukan dokter dengan review eksplisit. allergyVersion yang dipakai optimistic RME mencakup state/versi yang memengaruhi warning; koreksi tidak menulis ulang FINAL history. Alternatif multi-assertion menggunakan schema lebih sedikit tetapi tetap membutuhkan UX conflict/interpretation yang disetujui.

## PG-12 dan PR-01: delivery dan revokasi

Reminder ledger memiliki clinic_id/appointment_id/recipient_id, kind, rule_version, scheduling key/waktu target, status sent/skipped/failed, unique dedup key dan timestamps. Bentuk key dipilih dari semantik D-12 (termasuk return/reschedule), bukan memberi jaminan exactly-once transport tanpa desain delivery. Recheck status/recipient saat send, retry idempotent. Payload hanya status/next-action yang disetujui.

PR-01 mengusulkan integer users.realtime_auth_version default 0. Signed channel_data membawa versi dan user id server-side, bukan permission/NIK/isi medis. Fresh server authorization tetap diperiksa walau version belum berubah karena mutasi Spatie di jalur lain. Version naik bersama jalur mutasi yang dikendalikan; event role/permission menangani fan-out affected users dan invalidasi cache process panjang. Existing jobs/audit cukup untuk retry/result terminasi bila kontrak pending disetujui; jangan membuat generic outbox/revocation product baru tanpa kebutuhan.

Reverb ChannelBroker melakukan instantiation langsung, sehingga bind PrivateChannel class saja belum menjamin guard dipakai. Spike S1 harus membuktikan binding ChannelManager yang benar pada subscribe dan semua jalur delivery; registry connections harus tetap menyimpan identitas untuk terminate API. Jangan memfilter registry sehingga koneksi revoked tidak lagi dapat ditemukan untuk diputus. SQL/state-read failure fail-closed pada private delivery; bukan membocorkan payload sambil berharap client disconnect.

## Locks, race dan kompatibilitas

Sebelum PG-01/02/03 diterapkan, petakan lock order dari BookingService, QueueWorkflowService dan VisitWorkflowService. Saat ini booking mengambil clinic sebelum schedule, sedangkan start mengambil queue sebelum clinic. Jangan menambahkan urutan baru lalu mengklaim deadlock-free. Rekomendasi selaraskan affected commands pada urutan konsisten clinic → appointment/queue → schedule IDs terurut → visit/record/lineage yang diperlukan, tetapi seluruh jalur yang berinteraksi harus diuji dan penyesuaian sekecil mungkin dicatat. Terapkan bounded transaction retry existing dan recheck state, bukan retry yang mengirim notifikasi dua kali.

Pada replacement: same-session cancel/rebook menghitung quota dalam transaksi; pada pause/resume: active-slot constraint harus tetap satu; pada reversal: grant/reconcile/refresh status tidak mengaktifkan kembali target yang sudah sah direvoke. Pembuktian memakai dua proses/koneksi MySQL fixture, bukan request berurutan SQLite.

## Aturan migration dan pembatalan perubahan

- Nullable/additive terlebih dahulu; new table FK + unique request + index yang diperlukan. Seeder factory selalu relasi clinic eksplisit. Jangan menjalankan migrate:fresh/seed ulang/database rewrite pada data pengguna.
- Null legacy fields dibaca dengan fallback existing yang terdokumentasi; tidak menebak lineage, penerimaan kas atau duplicate grant. Backfill hanya setelah dry-run/persetujuan khusus, sebagai pekerjaan terpisah.
- Penambahan enum/status tidak mudah dibatalkan setelah row baru ada. Fase harus bisa dihentikan sebelum aktivasi command; rollback setelah data baru berarti menonaktifkan aksi baru sambil mempertahankan reader/histori, bukan drop kolom/table atau mengubah interrupted menjadi completed.
- PG-18/17/09/08/06 tidak memerlukan destructive migration. O1/O2/O3/O4 tidak boleh membuat schema spekulatif untuk scope yang belum dipilih.
- Verifikasi schema/unique/lock di DB testing yang jelas terisolasi. SQLite untuk behavioral tests tidak membuktikan constraint/row locks MySQL. Tidak ada perintah migration atau pengujian DB yang dijalankan pada tahap perencanaan.
