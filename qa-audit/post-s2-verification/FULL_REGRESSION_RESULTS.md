# Post-S2 — Full regression final

15 November 2026. Gate final dijalankan setelah config SQLite IMMEDIATE diterapkan. Tidak ada test dihapus atau didisable untuk mencapai hasil ini.

| Suite final | PASS | FAIL | ERROR | Bukti |
| --- | ---: | ---: | ---: | --- |
| Full PHPUnit SQLite `tests qa-audit/tests` | **453** | **0** | **0** | [text](evidence/sqlite-config-final.txt), [JUnit](evidence/sqlite-config-final.xml), [exit](evidence/sqlite-config-final-exit.json) |
| Full PHPUnit MySQL dedicated, suite DB terpisah | **453** | **0** | **0** | [text](evidence/mysql-config-final.txt), [JUnit](evidence/mysql-config-final.xml), [exit](evidence/mysql-config-final-exit.json) |
| Seluruh JavaScript unit files | **25** | **0** | **0** | [TAP bersama Chrome](evidence/javascript-chrome-final.tap) |
| Chrome workflows | **2** | **0** | **0** | [exit](evidence/javascript-chrome-final-exit.json), `evidence/legacy-chrome/` |
| Playwright PR-01 actual MySQL: seluruh 26 S2 scenarios | **26** | **0** | **0** | [cases](evidence/browser-mysql-final.json) |
| Playwright MySQL authority/ACK/restart/redelivery | **5** | **0** | **0** | [cases](evidence/browser-mysql-faults-final.json) |
| Playwright PG-17/18 original | **12** | **0** | **0** | [cases](evidence/f1-config-final/scenarios.json), [exit](evidence/f1-config-final/exit.json) |
| Deterministic notification lock/recovery probes | **4 modes** | **0** | **0** | [probe](evidence/notification-probe.json); DEFERRED PASS = failure direproduksi |
| Pint final | **PASS** | — | — | [Pint](evidence/pint-final.json) |

Kedua full PHP suites masing-masing memiliki **2.971 assertions**, 0 skipped. 453 adalah 451 kasus S2 existing ditambah dua factory tests. Jangan menghitung focused tests atau menjalankan database kedua sebagai tambahan jumlah fitur unik.

## Scope regression actual

Full PHP mencakup auth/session/password, staff/account/permission/tenant, booking/appointment/queue, RME/odontogram/foto/koreksi, notification/retry/rollback, subscription/grant/payment/refund, QA/DFU/CCL/F1 dan 27 revocation tests S2. Semua test lama dipertahankan; PR-02 tetap strict terhadap nilai/tipe/field/array order.

Empat file unit JS ditemukan di `tests/`: notification-realtime, partial-workflows, patient-duplicates, realtime-access. Dua browser test files Chrome juga dieksekusi, bukan sekadar dibiarkan skip oleh environment flag. Runner menggunakan copy temporary hanya untuk root/router terisolasi; assertions original sama. PG-17/18 memakai original Playwright script tanpa modifikasi dan fixture/storage terpisah.

Browser PR-01 mempertahankan filter/caret CCL-07 dan error/editor CCL-02, autosave actual, pending photo, seluruh private channel actual dan public board. PG-17 paging/token/tenant/inactive dan PG-18 binding/retry/double-click/history tetap PASS. Ini bukan audit ulang semua role/action produk dari nol atau persentase coverage berdasarkan route.

Private Vite build actual MySQL dan offline legacy build berhasil: [build](evidence/build.txt), [legacy build](evidence/legacy-build.txt). Tidak ada frontend source baru pada paket ini; build workspace produksi tidak diulang sebagai alasan mengubah artefak rilis pengguna.

## Isolasi

SQLite suite memakai in-memory DB; MySQL suite memakai database dedicated yang berbeda dari runtime WebSocket. Cache, view dan storage suites final dipisah menurut run label. Browser runtime memakai private storage/cache/key dan dedicated MySQL; Chrome/F1 juga memakai fixture independen. Tidak menjalankan test pada database pengguna.

## Riwayat failure diagnostik

- PR-02 sebelum fix: 1 assertion FAIL MySQL, semantik sama. PR-03 seed 0 sebelum fix: panjang 31 melampaui 30. Keduanya kemudian 8/8 focused PASS di masing-masing DB.
- Scaffold import test duplikat dan collision cache manifest dua runner diperbaiki pada harness/test baru sebelum valid reproduction; artefak awal tetap disimpan.
- Lima percobaan fault pertama gagal karena kontrol belum reconnect ketika fault dimulai; target queue drain juga sempat menghitung scheduled control retries yang tidak terkait. Diagnosis dan perbaikan barrier harness dijelaskan pada laporan MySQL. Seluruh negative/positive security assertions tetap ada.
- Pengulangan F1 sempat ditolak karena evidence collector menolak scenario ID yang sudah ada. Run final memakai direktori baru `f1-config-final`; original collector tidak dilemahkan. Run yang ditolak bukan regresi aplikasi.
- Full suites sebelum perubahan config juga 453/453 PASS, tetapi artefak `*-config-final` yang dipakai sebagai gate source akhir.

Tidak ada failure aplikasi yang belum diselesaikan pada matrix final. Risiko yang tidak diuji tetap NOT TESTED pada [REMAINING_RISKS](REMAINING_RISKS.md), bukan dikonversi menjadi PASS.
