# Final Coverage Report

Coverage gap closure selesai mengklasifikasikan **77/77 skenario terdaftar** (72 tambahan+5 BLOCKED historis). Hasil: **52 PASS, 8 FAIL, 11 BLOCKED, 6 NOT TESTED**. Ada **8 akar masalah baru**, masih OPEN. Seluruh skenario mempunyai hasil aktual atau alasan eksplisit; ini bukan pernyataan bahwa seluruh fitur bebas bug.

[Matrix lengkap](COVERAGE_CLOSURE_MATRIX.md) · [Temuan baru](NEW_FUNCTIONAL_FINDINGS.md) · [Gap tersisa](REMAINING_GAPS.md) · [Ringkasan terstruktur](closure/evidence/coverage-summary.json)

## Angka yang dapat dipertanggungjawabkan

| Ukuran | Hasil / arti |
| --- | --- |
| Parent tambahan72 | 51 PASS; 8 FAIL; 7 BLOCKED; 6 NOT TESTED |
| 5 BLOCKED sebelumnya | WS-RECOVERY menjadi PASS; Google OAuth dan3 skenario DOKU tetap BLOCKED |
| Browser terbaru | 254 grup assertion aktif: 245 PASS / 9 FAIL |
| Tuple aktual | 254 kombinasi unik fitur×role/persona×aksi×state; 245 PASS / 9 FAIL |
| Dental | 192/192 sel32 gigi×6 kondisi PASS, di dalam6 grup kondisi; final correction, histori mobile dan ends_at expiry juga PASS |
| Percobaan historis run ini | 337 catatan total; 83 superseded karena prasyarat/assertion/sinkronisasi runner yang diperbaiki. Semua dipertahankan dan dapat ditelusuri. |
| Kelompok fitur dengan assertion baru | 34/36; keberadaan satu assertion bukan100% coverage fitur |
| Role | Kelima role tersimpan; owner sebagai persona Clinic Admin; guest/token holder dan System command terpisah |
| Source produksi | 377 hash sama termasuk.env dan manifests; tidak ada source produksi baru; status Git di luar qa-audit sama |

**Cara menghitung coverage:** deduplikasi tuple eksplisit pada [executed-tuples.json](closure/evidence/executed-tuples.json), bukan route, halaman atau jumlah klik. Parent72 pada baseline berisi deskripsi luas, tanpa denominator semua tuple atomik. Karena itu persentase seluruh kombinasi produk tidak dapat dihitung dengan jujur.77/77=100% disposisi daftar, bukan100% functional coverage. Dental192 dilaporkan terpisah agar tidak menggandakan6 workflow kondisi. Parent BLOCKED/NOT TESTED tetap demikian walau mempunyai subkasus PASS.

## Hasil utama

- Dental memakai entitlement dummy yang sah: initial Payment Paid, PaymentItem/addon dan periode klinik terkait, diaktifkan melalui AddonService; bukan sekadar flag front-end. Pilih32×6, save/reload, koreksi FINAL dan snapshot, read-only sesudah ends_at tanpa revoked_at, serta kelanjutan RME General IN_PROGRESS terbukti. Ini bukan pembayaran DOKU eksternal.
- Reverb dan database worker benar-benar berjalan. Frame publik nonmedis, badge lintas browser, server disconnect/reconnect dan missed-event tanpa reload berhasil. Timeout reconnect awal12s adalah batas runner; retest mengikuti backoff nyata sekitar17s, bukan perbaikan source.
- Validasi auth/profile/staf/schedule/pasien, stale booking/queue, terminal cancel, structured RME/vital, alergi, resep, foto, finalisasi/revisi, audit, subscription/grant/refund manual serta ETA diperluas. Matriks menjelaskan aksi, role, state dan hasil masing-masing.
- Foto tepat5MB dan putus transfer nyata melalui throttling/offline Chrome: tidak ada write parsial DB, pilihan tetap ada dan retry berhasil. Foto revisi hanya dapat dibuka dokter yang berhak.

## Temuan baru

| ID | Masalah | Parent |
| --- | --- | --- |
| CCL-01 | Input tarif step1000 bertentangan dengan nominal yang diterima server | G15 |
| CCL-02 | Autosave401 memberi saran koneksi, tanpa pemulihan login | G33 |
| CCL-03 | Telepon bertitik hilang dari kandidat normalisasi | G71 |
| CCL-04 | Pasien nonaktif berubah ke form booking pasien baru tanpa alasan | G72 |
| CCL-05 | Jam klinik baru tidak membatasi booking pada sesi existing | G11 |
| CCL-06 | List tenant tetap active setelah ends_at | G52 |
| CCL-07 | Realtime refetch menghapus teks filter belum dikirim | G69 |
| CCL-08 | Semua paket nonaktif: tidak ada penjelasan empty state | G02 |

## Menjaga bukti perbaikan sebelumnya

DFU-01–DFU-12 tetap menggunakan [FIX_REGRESSION_REPORT](FIX_REGRESSION_REPORT.md): backend378 PASS/2572 assertion, JavaScript10 PASS, browser65 PASS dan dental yang sebelumnya NOT TESTED. Angka tersebut **historis, tidak dijalankan ulang dalam audit ini** dan tidak digabung dengan hitungan closure. Placeholder dental lama kini ditutup bukti baru G44/G45. Pesan pembatalan resepsionis menggunakan REG-LATE-receptionist yang sudah verified. Perubahan kode produksi selama closure nol, sehingga suite backend tidak diulang tanpa alasan; ini tidak menyatakan ada run backend baru.

PASS lama hanya dipakai sebagai dependensi journey atau diberi criterion baru yang belum diuji (misalnya katalog guard aman berbeda dari empty-state explanation). Retest aktif hanya mengganti percobaan dengan prasyarat/assertion keliru;9 FAIL produk tetap terlihat dan dipetakan ke8 akar masalah.

## Lingkungan, isolasi dan batas

Chrome158.0.8127.36 nyata/headless, Playwright1.65, aplikasi Laravel lokal checkout saat ini. Server audit127.0.0.1:18961, Reverb18962, SQLite file disposable, storage/sesi/cache/build serta mail capture privat terpisah. Clock aplikasi umumnya13Nov2026 09:00WIB, dimajukan secara terkontrol untuk expiry, reset, reminder dan batas hari/bulan lalu dipulihkan; tidak mengubah clock OS. PHP QA upload8MB/post16MB agar batas aplikasi5MB benar-benar diuji.

Initial factory/seed fixture hanya pada DB disposable yang baru dibuat; relasi tenant/entitlement eksplisit. Setelah setup, tindakan pengguna lewat browser UI; DB hanya verifikasi/menentukan resource dummy, tanpa POST bisnis langsung. Command subscriptions:queue-reminders adalah aksi System yang memang ada dalam register; tidak menggantikan UI pengguna. Fault jaringan dan kontrol Reverb hanya pada runtime audit. Capture mail bukan bukti delivery email eksternal.

Tidak ada perubahan.env/APP_KEY pengguna, data pengguna, commit, push, deploy, pembayaran nyata, load/stress test atau penilaian production-readiness. Perubahan pengguna sebelum audit dipertahankan. [Bukti hash/status](closure/evidence/production-integrity.json).

Google/DOKU sandbox, studi partisipan awam, permission revoke socket aktif, full commercial-history UI dan pemulihan grant lama tetap memiliki batas yang dijelaskan pada [REMAINING_GAPS](REMAINING_GAPS.md). Server PHP tunggal/SQLite tidak membuktikan concurrency MySQL. Mobile dan aksesibilitas dibatasi assertion yang dicatat; tidak mengklaim semua viewport/perangkat/screen reader teruji.

## Artefak dan pengulangan terarah

[Hasil dan rantai retest](closure/evidence/scenarios.json) · [77 status parent](closure/evidence/closure-parent-results.json) · [192 sel dental](closure/evidence/dental-cells.json) · [Transisi socket](closure/evidence/reverb-recovery-transitions.json) · [Metadata aman](closure/evidence/audit-safe-metadata.json)

Runner berada pada [closure](closure/), memakai helper audit yang sudah tersedia. Jangan menjalankan ulang setup pada DB hasil run: banyak scenario mengubah state secara sah. Untuk audit baru gunakan runtime disposable baru, setup sekali, dan jalankan runner berurutan sesuai dependensi; jangan mengeksekusi dua runner penulis scenarios.json atau pengubah clock bersamaan. Jangan menyalin env runtime ke.env aplikasi.

Hash produksi dan link/angka laporan diverifikasi oleh [reports.mjs](closure/reports.mjs). HTTP/Reverb/worker audit sudah dihentikan; capture mail dengan tautan reset/verifikasi dihapus dan env sementara dibersihkan dari environment pengguna yang terwarisi. Database dummy disimpan privat sebagai bukti. [Retensi runtime](closure/evidence/runtime-retention.json) · [Pemeriksaan privasi artefak](closure/evidence/privacy-check.json).
