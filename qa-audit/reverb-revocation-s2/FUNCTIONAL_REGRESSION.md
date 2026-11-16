# PR-01 S2 — Functional regression actual

Tanggal: 15 November 2026. Semua database dan storage aplikasi testing terpisah dari data pengguna. Assertions test lama tidak dihapus atau diubah.

## Gate final

| Suite / artefak final | PASS | FAIL | ERROR | Catatan |
| --- | ---: | ---: | ---: | --- |
| PHP full SQLite, `tests qa-audit/tests` | 451 | 0 | 0 | 2.967 assertions; in-memory DB, private storage/cache/key |
| PHP full MySQL 8.0.30, `tests qa-audit/tests` | 449 | 1 | 1 | 2.958 assertions; dedicated server/database; **suite tidak hijau** |
| Subset test revokasi baru, bagian dari kedua full suites | 27 per DB | 0 | 0 | Jangan dijumlahkan lagi ke total 451 |
| JavaScript unit + Chrome workflow | 27 | 0 | 0 | 25 unit JS (20 existing + 5 baru), 2 Chrome workflow |
| Browser S2 / Reverb / worker actual | 26 | 0 | 0 | Fresh fixture, source final |
| Playwright PG-17/PG-18 original | 12 | 0 | 0 | 8 PG-17, 4 PG-18; original script dan assertions sama |
| Dua proses MySQL + rollback | 2 checks | 0 | 0 | Backend locking, bukan transport WS MySQL |
| Vite build workspace | PASS | — | — | Juga private browser builds |
| Pint final | PASS | — | — | `php vendor/bin/pint --dirty --format agent` |

Tidak menggabungkan test case lintas runner menjadi persentase coverage produk. 451 terdiri dari 424 kasus sebelumnya dan 27 kasus S2 baru; counts mencakup data sets. SQLite pass tidak menutupi failure MySQL.

## Bukti dapat dibaca ulang

- [SQLite text](evidence/php-full-isolated-final.txt), [JUnit](evidence/php-full-isolated-final.xml), [exit/isolation](evidence/php-full-isolated-final-exit.json).
- [MySQL text](evidence/mysql-full-verified-final.txt), [JUnit](evidence/mysql-full-verified-final.xml). Wrapper menyimpan exit PHPUnit dalam output; gate actual PHPUnit adalah **2**, bukan exit wrapper Node.
- [JS/Chrome TAP](evidence/javascript-chrome-final.tap), [exit](evidence/javascript-chrome-final-exit.json), screenshot pada `evidence/legacy-chrome/`.
- [S2 browser](evidence/browser-verified-final.json), [PG-17/18](evidence/f1-regression/scenarios.json), [F1 exit](evidence/f1-regression/exit.json).
- [Concurrency](evidence/mysql-concurrency-final.json), [build](evidence/build-workspace.txt), [Pint](evidence/pint-final.json).

Full PHP mencakup test QA/DFU/CCL/F1 yang tersedia saat ini: booking/queue/visit, appointment cancellation, duplicate matching, subscription/grant/payment, role/policy, timezone, validation dan clinical record workflows. Tidak mengklaim audit browser ulang seluruh fitur produk. Browser relevan secara khusus menguji filter/caret CCL-07, error/session/editor CCL-02, real autosave dan pending photo, notification navigation, serta PG-17/18.

Untuk legacy Chrome, runner membuat copy temporary yang hanya mengubah root resolution dan router private storage; assertions/source test di repo tidak berubah. PG-17/18 memakai original browser script tanpa edit pada fixture dan private HTTP/build tersendiri. Source PG-17/18 tetap identik terhadap snapshot S2.

## Dua temuan historis tetap terbuka

1. **PR-02 — FAIL:** `RmeGeneralDentalTest::test_assigned_doctor_can_persist_general_rme_and_odontogram_in_draft`, strict JSON object key ordering. MySQL menormalkan urutan `condition`/`tooth_number`; nilai sama tetapi `assertSame` gagal. Test tidak dilemahkan dan domain odontogram tidak diubah.
2. **PR-03 — ERROR:** `CoverageClosureRegressionTest::test_platform_reads_effective_subscription_without_changing_history`, data set `expired`. Pembuatan fixture gagal dengan SQLSTATE 22001 / MySQL 1406, `subscription_plans.code` lebih panjang dari schema. Ini factory subscription yang sudah dilaporkan, muncul pada caller/data set berbeda karena Faker. Bukan failure assertion status subscription. Factory tidak diubah dalam scope PR-01.

Acuan historis: [NEW_FINDINGS PR-02/PR-03](../production-readiness/NEW_FINDINGS.md). Keduanya tidak ditutup oleh keberhasilan revokasi.

## Observasi sandbox yang tidak disembunyikan

State akhir runtime WS: **82 revocation requests, 0 unacknowledged, 0 pending jobs, 0 pending transport failures**. Namun terdapat **1 failed job `DeliverClinicNotification` akibat SQLite `database is locked`**. Job itu bukan histori bawaan fixture F1; muncul saat run S2. Tidak dihapus atau di-retry untuk memoles hasil. Seluruh assertion browser terdaftar lulus dengan positive-control publication, tetapi itu tidak membuktikan setiap job selama run sukses. Pemeriksaan final state dan penyebab disimpan pada [fixture-final-state.json](evidence/fixture-final-state.json), [failed-job-provenance.json](evidence/failed-job-provenance.json), [failed-job-cause.json](evidence/failed-job-cause.json).

Observasi ini tetap menjadi batas verifikasi keandalan notifikasi pada harness SQLite dengan proses terpisah. S2 tidak mengubah retry policy notifikasi atau memperluas pekerjaan ke optimasi/concurrency performa. Transport MySQL belum diuji; full backend MySQL dan lock probe tidak menggantikannya.

## Run pengembangan dan infrastruktur

Evidence awal tetap disimpan: smoke menemukan inactive auth mengikuti redirect menjadi HTML 200; source middleware diperbaiki, kemudian final suite membuktikan 401. Extended candidate gagal akibat QA clock recursion dan asumsi endpoint logout; harness memakai native clock dan UI Volt actual pada final run. Kandidat lama sebelum durable grant/final-close fence tidak dipakai sebagai bukti source final.

Run MySQL pertama mengalami masalah bootstrap path/APP_URL pada harness; retest dikonfigurasi benar. `mysql-full-final` sempat terinterupsi karena instance testing berhenti; artefaknya tetap ada. Server dedicated dinyalakan ulang dan **seluruh** suite dijalankan lagi sebagai `mysql-full-verified-final`. Tidak ada test dipilih keluar untuk mendapatkan full-suite hijau.

Tidak ada load/stress, deployment, external payment atau user-data mutation. Multi-node dan fault tambahan yang belum dieksekusi ditandai pada [REMAINING_RISKS](REMAINING_RISKS.md).
