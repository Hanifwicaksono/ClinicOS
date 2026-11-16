# ClinicOS — Post-S2 final verification

Tanggal: **15 November 2026**. **PR-02 dan PR-03 FIXED & VERIFIED. PR-01 tetap FIXED & VERIFIED single-node, dengan cakupan actual MySQL WebSocket yang diperluas.** Tidak menyatakan production-ready atau multi-node safe.

## Perubahan source terkecil

1. `tests/Feature/RmeGeneralDentalTest.php`: urutkan key setiap object odontogram pada salinan assertion; tetap `assertSame` untuk seluruh field/nilai/tipe dan urutan array.
2. `database/factories/SubscriptionPlanFactory.php`: kode fixture 25 karakter ASCII, tetap unique; schema VARCHAR(30) tidak diubah.
3. `tests/Feature/SubscriptionPlanFactoryTest.php`: dua regression tests, seed 0 yang dahulu menghasilkan 31 karakter dan boundary deterministik 30 karakter.
4. `config/database.php`: hanya SQLite transaction mode menjadi IMMEDIATE, berdasarkan reproduksi actual queue reservation race. MySQL, retry/backoff dan timeout tidak diubah.

Tidak ada migration, vendor/dependency, S2 service/guard/fence, PG-17/18 atau fitur klinis/komersial yang diubah. Snapshot membandingkan seluruh source dan laporan sebelum/sesudah: [source-integrity](evidence/source-integrity.json). Perubahan lama yang sudah dirty sebelum sesi dipertahankan.

## Temuan dan hasil

**PR-02:** MySQL mengurutkan object keys berbeda sementara nilai/type/urutan gigi benar. Assertion diperbaiki tanpa mengubah data produksi atau melemahkan perbandingan semantik.

**PR-03:** Faker slug tidak membatasi panjang. Seed 0 mereproduksi 31 karakter; bounded factory dan fixture boundary kini lulus pada kedua database.

**Notifikasi:** worker SQLite DEFERRED sudah membaca job, proses lain commit, lalu queue reservation gagal saat menulis. Framework menandai job failed sebelum handler, sehingga backoff handler tidak terpakai. Config IMMEDIATE menghindari snapshot upgrade pada probe actual. Salinan failed job dapat di-retry; jumlah stored notification tetap 203. MySQL control tidak mengalami failure yang sama. Actual duplicate delivery di browser menghasilkan dua raw frames dengan satu notification ID, **satu client action** dan tanpa record DB tambahan.

**Fence MySQL:** seluruh 26 S2 scenarios diulang, termasuk multi-tab/two browsers, old sockets, authorized/foreign controls, stale/reconnect denial, rollback, own notifications, public board, filters/editor dan deterministic races. Post-auth callbacks yang dilepas sesudah ACK ditolak tanpa raw write. Admitted writes menahan ACK sampai selesai dan actual transports close. Event baru sesudah ACK tidak masuk old sockets.

**Failure/recovery tambahan:** SELECT authority dan UPDATE ACK benar-benar ditolak oleh MySQL untuk koneksi Reverb. Failed ACK write tidak menghasilkan ACK palsu. Worker/Reverb restart dengan committed pending revoke pulih melalui durable state, dan revoked old signature tetap ditolak. Ini membuktikan titik restart yang diuji, bukan semua titik crash.

## Hasil final actual

| Gate | Hasil |
| --- | --- |
| Full PHPUnit SQLite | **453 PASS, 0 FAIL, 0 ERROR**, 2.971 assertions |
| Full PHPUnit MySQL | **453 PASS, 0 FAIL, 0 ERROR**, 2.971 assertions |
| Seluruh JS unit + Chrome workflows | **27 PASS**: 25 unit + 2 Chrome |
| Playwright PR-01 actual MySQL | **31 PASS**: 26 S2 + 5 tambahan |
| Original Playwright PG-17/18 | **12 PASS** |
| Notification deterministic probes | **4 modes PASS**, termasuk expected reproduction DEFERRED dan configured IMMEDIATE |
| Pint dan private Vite builds | **PASS** |

Tidak ada test dihapus, dinonaktifkan atau dipilih keluar dari full suite. Hasil diagnostik sebelum fix/harness correction tetap disimpan dan dibedakan dari final gate. Tidak menjumlahkan run lintas database sebagai coverage fitur unik.

## Laporan dan bukti

- [MYSQL_REGRESSION_FIXES](MYSQL_REGRESSION_FIXES.md): reproduksi dan perubahan PR-02/03.
- [NOTIFICATION_JOB_INVESTIGATION](NOTIFICATION_JOB_INVESTIGATION.md): lock root cause, actual workers, recovery dan deduplication.
- [MYSQL_WEBSOCKET_FENCE_REPORT](MYSQL_WEBSOCKET_FENCE_REPORT.md): actual frames, race timeline dan positive controls.
- [CRASH_RECOVERY_TESTS](CRASH_RECOVERY_TESTS.md): fault/restart points yang benar-benar dieksekusi.
- [FULL_REGRESSION_RESULTS](FULL_REGRESSION_RESULTS.md): counts, JUnit/TAP/browser evidence dan historical attempts.
- [REMAINING_RISKS](REMAINING_RISKS.md): single-node, crash/DB/transport limits dan hal yang belum diuji.

Daftar failure aplikasi pada final matrix: **tidak ada**. Multi-node, seluruh crash permutations, total database failure dan network exactly-once tetap tidak diklaim. Data pengguna, `.env`, APP_KEY dan laporan historis tidak diubah. Tidak ada commit, push, deploy, load/stress atau PG-01–PG-16.

State akhir MySQL runtime: **94 revocations seluruhnya ACK, 0 pending jobs, 0 failed jobs, 0 pending transport failures**: [runtime-final-state](evidence/runtime-final-state.json). Seluruh proses testing milik sesi ini dihentikan dan tidak ada listener testing tersisa pada port yang diperiksa: [cleanup](evidence/cleanup.json). Ringkasan mesin tersedia pada [summary.json](evidence/summary.json).
