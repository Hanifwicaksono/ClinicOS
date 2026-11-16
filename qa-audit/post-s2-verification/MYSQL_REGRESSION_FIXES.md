# Post-S2 — PR-02 dan PR-03

Tanggal: 15 November 2026. Perubahan dibanding source awal sesi, bukan seluruh dirty diff Git dari pekerjaan sebelumnya.

## PR-02 — FIXED & VERIFIED

Test yang gagal: `RmeGeneralDentalTest::test_assigned_doctor_can_persist_general_rme_and_odontogram_in_draft`.

Reproduksi MySQL terbaru sebelum perubahan: [before-pr02-valid.txt](evidence/before-pr02-valid.txt), [JUnit](evidence/before-pr02-valid.xml), exit 1. Data actual memiliki dua elemen gigi yang sama, field/nilai/tipe yang sama dan urutan elemen yang sama. MySQL menyimpan object key dengan `condition` sebelum `tooth_number`; assertion sebelumnya mengharapkan kebalikannya.

Perubahan hanya pada assertion `tests/Feature/RmeGeneralDentalTest.php`: salin hasil odontogram melalui `array_map`, lakukan `ksort` pada setiap object, lalu tetap gunakan **`assertSame`** terhadap expected lengkap. Tidak menyortir array gigi, membuang field, melakukan coercion tipe, atau memilih subset field. Extra/missing fields, nilai berbeda, angka menjadi string, serta urutan gigi tertukar tetap gagal. Tidak ada perubahan serialization model, request, database JSON atau data klinis untuk memenuhi test.

## PR-03 — FIXED & VERIFIED

Schema tetap `subscription_plans.code VARCHAR(30) UNIQUE`, pada `2026_10_15_091158_create_subscription_domain_tables.php`. Factory sebelumnya menggunakan `fake()->unique()->slug(2)`: dua kata tidak menjamin 30 karakter. Seed 0 menghasilkan panjang 31, direproduksi oleh regression baru sebelum perbaikan: [before-pr03-valid.txt](evidence/before-pr03-valid.txt), exit 1.

`database/factories/SubscriptionPlanFactory.php` kini menggunakan `fake()->unique()->lexify('plan-????????????????????')`: 25 karakter ASCII dengan ruang keunikan yang cukup untuk fixture. Tidak memotong slug yang dapat meningkatkan collision, dan tidak memperlebar schema produksi.

Test baru `tests/Feature/SubscriptionPlanFactoryTest.php` mencakup seed overflow 0 dan persist/fresh code; fixture deterministik tepat 30 karakter juga harus tersimpan utuh. Faker seed/unique state dibersihkan setelah pemeriksaan deterministik. Test tidak mengandalkan SQLite untuk membuktikan constraint panjang MySQL.

## Verifikasi kedua perbaikan

| Database | Focused suite | Hasil |
| --- | --- | --- |
| SQLite | RmeGeneralDentalTest + SubscriptionPlanFactoryTest | 8 PASS, 56 assertions |
| MySQL 8.0.30 dedicated | Suite yang sama | 8 PASS, 56 assertions |

Bukti: [SQLite](evidence/after-pr02-pr03-sqlite.txt), [MySQL](evidence/after-pr02-pr03-mysql.txt), beserta JUnit dan exit JSON masing-masing. Full suites setelah integrasi tersedia pada [FULL_REGRESSION_RESULTS](FULL_REGRESSION_RESULTS.md).

Run diagnostik pertama test factory sempat gagal bootstrap karena duplicate import pada scaffold test baru; diperbaiki sebelum run reproduksi yang valid. Run MySQL awal juga menemukan collision cache manifest dua runner; runner berikutnya menggunakan path cache berbeda. Keduanya disimpan dalam evidence dan tidak disebut sebagai reproduksi PR-02/03.

## File source pada paket ini

- `tests/Feature/RmeGeneralDentalTest.php`: hanya normalisasi key object pada assertion PR-02.
- `database/factories/SubscriptionPlanFactory.php`: generator code dibatasi schema.
- `tests/Feature/SubscriptionPlanFactoryTest.php`: dua regression tests baru.

Perubahan SQLite untuk kegagalan queue dibahas terpisah pada [NOTIFICATION_JOB_INVESTIGATION](NOTIFICATION_JOB_INVESTIGATION.md). Tidak ada migration, vendor/dependency, klinis, commercial workflow atau S2 service yang diubah oleh paket PR-02/03.
