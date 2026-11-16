# Dependensi dan urutan implementasi setelah keputusan

Tahap sekarang hanya analisis/dokumentasi. “Siap teknis” berarti tidak membutuhkan aturan bisnis baru; **bukan izin mulai coding pada sesi ini**. Status rencana lama yang menyebut PG-17/18 atau PR-01 OPEN telah disusul hasil F1/S2/post-S2 dan tidak disalin sebagai current status.

## Paket yang dapat disiapkan tanpa memilih kebijakan baru

| Paket teknis | Batas yang aman | Keluaran yang dapat diuji terpisah setelah instruksi implementasi |
| --- | --- | --- |
| T1 Characterization | Current transitions/permissions/price/quota; fixture terisolasi, tanpa perbaikan policy diam-diam | Test source-state matrix, replay intent/actor boundary, lock order dan risiko file rollback yang belum direproduksi |
| T2 Read-only impact preview | Booking metadata yang sudah boleh dilihat admin/rec, tenant sendiri; tidak SOAP/handoff clinical payload | Daftar affected current schedules/appointments; tidak menyimpan exception, memindah booking atau memilih kebijakan pasien |
| T3 UX sesuai behavior existing | Labels/empty/loading/error recovery, focus/caret/draft preservation; tanpa cutoffs/hak baru | Browser keyboard/mobile/errors, 401/419/409 dan pending vs confirmed yang akurat |
| T4 Reuse guards existing | Dalam desain fitur baru gunakan PhoneNumber, duplicate service, version/hash, policies, S2 dan domain services | Tidak menggandakan normalisasi, bypass quota atau membuat permissive private channel |

T1/T2 bukan PG baru. Viewer komersial full/audience baru tetap memerlukan field allowlist D-09; print dokumen dan clinical handoff tidak disamakan dengan read-only metadata preview.

## Urutan yang direkomendasikan

| Fase | Paket original | Gate sebelum implementasi | Dependensi / exit mandiri |
| --- | --- | --- | --- |
| BR-F0 | Kontrak keputusan | D-01/D-02/D-03 dipilih untuk jalur yang akan dibangun; D-06/D-13 dibahas sebagai risiko lintas kanal/klinis | Catat actor, state, price, quota, history, patient communications dan AC; tidak harus menunggu seluruh20 keputusan |
| BR-F1A | PG-02 impact preview teknis | Instruksi implementasi; metadata audience existing saja | T2 dapat berdiri sendiri, old appointments tidak berubah; bukan kalender exception selesai |
| BR-F1B | PG-06 inactive policy | D-06 | Konsistensi eligibility/race, recovery explicit, CCL-04/PG-17 utuh; tidak auto-link |
| BR-F2A | PG-02 date exception | D-02, weekly effectivity/overlap dan timezone impact | New booking menolak blackout, old appointments tetap; independent manual follow-through masih mungkin |
| BR-F2B | PG-01 atomic reschedule/reassign | D-01 lengkap: status/cutoff/price/queue/check-in/carry quota | Reuse existing availability; bila F2A sudah ada wajib recheck exception. Tidak bergantung PG-03 untuk pre-visit |
| BR-F3A | PG-03 pause/terminal interruption | D-03A klinis, A/Q/V/R mapping, clinical closure note/finality, slot/ETA/expiry | Bisa selesai tanpa handoff lintas dokter; no fake completed, draft/history tetap |
| BR-F3B | PG-13 allergy lineage | D-13 klinis | Dapat berjalan paralel; harus diintegrasikan sebelum handoff membuka multi-editor clinical context |
| BR-F3C | PG-03 handoff/continuation | D-03B + clinical recipient/access/acceptance + quota/expiry commercial + model unique visit | Perlu F2 target availability, F3A dan konflik alergi F3B/approved adjudication. Tidak menunggu permission editor |
| BR-F4A | PG-07 issued documents | D-07, version/reprint/release SOP | Final/correction/history versions teruji; tidak full SOAP portal |
| BR-F4B | PG-04 follow-up | D-04, patient instruction allowlist | Existing booking dapat dipakai; F2B memberi rebook yang lebih baik. Complete hanya actual completed care |
| BR-F5A | PG-09 commercial history | D-09 fields/audience | Independent dari clinical recovery; no writes financial truth |
| BR-F5B | PG-10 targeted reversal | D-10 fallback/grace/period scope/addon/funds/approval | Sebaiknya F5A tersedia agar reviewer dapat melihat chronology; preserve F1 grant idempotency |
| BR-F5C | PG-14 fixture diagnostics → repair per kategori | D-14; real-data approval terpisah | Detector read-only dahulu; clinical/commercial repairs gated masing-masing, tidak perlu seluruh generic repair engine |
| BR-F6 | PG-08 / PG-12 | D-08 metrics/privacy dan D-12 threshold/channel/cadence | Status baru/replacement didefinisikan dahulu; patient reminder tidak disamakan subscription reminder |
| Optional | PG-05 / PG-11 / PG-15 / PG-16 | D-05/11/15/16 dan scope yang benar-benar dipilih | Tidak otomatis menjadi dependency semua fase klinis; packaging rights existing tetap sampai keputusan baru |
| Dipertahankan | PG-17 / PG-18; PR-01/02/03 | Sudah verified pada scope laporan terakhir | Tidak implement ulang atau reopen hanya karena audit lama menyebut OPEN |
| Di luar fase | D-17/18/19 expansions | Persetujuan scope baru | Custom editor, managed kiosk, branch/Edge/domain lain tidak disisipkan |

PG-02 tidak harus menunggu seluruh PG-01: bisa memblok booking baru dan menampilkan impact tanpa memindahkan pasien. PG-01 dapat memakai jadwal existing sebelum date-exception dirilis, tetapi harus mempunyai satu availability validation path supaya integrasi berikutnya tidak membuka booking pada blackout. Ini memecah dependency melingkar dari backlog lama tanpa menganggap resolusi pasien existing otomatis tersedia.

## Dampak database yang perlu dirancang, belum dibuat

- D-01: replacement relation/operation UUID+intent+expected-state; lineage eligibility bila disetujui. Jangan reuse UUID booking lama atau backdate created_at.
- D-02: availability exception/effective template representation; scope doctor/clinic dan timezone/interval auditable. Jangan mengedit historical session FK untuk memindahkan pasien.
- D-03: enum/state mapping, pause intervals/clinical closure/handoff acceptance, old/new episode relations. Unique visit/appointment berarti continuation perlu appointment baru pada opsi A; jangan drop constraint tanpa memilih opsi B dan membuktikan seluruh callers.
- D-04: linked follow-up task/due/actor/history; tidak mengubah clinical plan lama menjadi task otomatis.
- D-07: issued document record-version/issuer/reprint/supersession/release references; tidak mengganti medical revision.
- D-10: targeted reversal/audit/expected period; tidak delete Payment/Period atau merewrite semua future schedule.
- D-13: lineage/current uniqueness/expected-parent; legacy mapping harus explicit dan clinical review, bukan fuzzy auto-supersede.
- D-14: diagnosis/approval/fingerprint/evidence per kategori jika dipilih; tidak melakukan migration repair pengguna otomatis.

Tidak ada migration, schema edit atau command dibuat oleh laporan ini. Semua perubahan berikutnya additive dan harus menjaga histori.

## Gate test setiap paket

1. Characterize behavior existing dan reproduce gap/risiko yang akan diubah pada fixture; keputusan disetujui menjadi expected behavior.
2. Backend positive/negative role, tenant, state, boundary waktu, rollback, UUID/expected version dan actual MySQL multi-process untuk race yang relevan.
3. Browser actual aksi akhir-ke-akhir termasuk error/retry/empty/loading, dua tab/editor, mobile/keyboard dan preservasi foto/input.
4. Integrasi jobs/notifications setelah commit, delayed/replay, authorized controls dan old-socket security sesuai S2 bila jalur terdampak.
5. Full relevant suites sesudah integrasi; tidak menghapus failing test, tidak memakai data pengguna. Catat actual PASS/FAIL/BLOCKED/NOT TESTED dan batas. **Tidak dijalankan pada tahap analisis ini.**

Baseline historis terakhir:453 PHP PASS pada SQLite dan MySQL,27 JS/Chrome PASS,31 browser PR-01 MySQL PASS,12 PG-17/18 PASS, dari [post-S2](../post-s2-verification/FULL_REGRESSION_RESULTS.md). Angka ini tidak membuktikan state PG-01–16 yang belum diimplementasikan atau approval klinis.
