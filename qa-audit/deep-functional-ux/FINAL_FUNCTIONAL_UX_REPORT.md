> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.

# Final Functional & UX Report

Run: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome 158.0.8127.36, headless nyata**; database/storage/sesi/build disposable.

PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.

## Kesimpulan

**Belum dapat dinyatakan seluruh fitur aman dipakai manusia pada seluruh kondisi.** Jalur utama terpilih berjalan, tetapi audit menemukan **12 kelompok temuan terkonfirmasi OPEN: 1 P1, 10 P2, 1 P3**. Cakupan inventaris sudah memuat seluruh kelompok fitur/role yang ditemukan; eksekusi exhaustive dan layanan eksternal tetap mempunyai gap eksplisit. Ini kesimpulan fungsional/UX, bukan penilaian kesiapan production.

## Prioritas perbaikan

| ID | Severity | Masalah | Dampak |
| --- | --- | --- | --- |
| DFU-01 | P1 | Pembatalan pasien memutus pemeriksaan yang sudah berjalan | Pelayanan yang sudah dimulai tidak dapat ditutup oleh dokter; status dokter, pasien, antrean dan visit saling bertentangan. |
| DFU-02 | P2 | Edit hari sesi terpakai membuat tanggal appointment tidak sesuai sesi | Pasien dan staf dapat mengikuti tanggal yang berbeda dengan sesi dokter; filter sesi dan histori tidak lagi mempunyai acuan hari yang konsisten. |
| DFU-03 | P2 | Refresh otomatis menghapus alasan penolakan aksi antrean | Staf tidak memahami mengapa klik tidak bekerja dan cenderung mengulang aksi atau salah menafsirkan state antrean. |
| DFU-04 | P2 | Pembatalan yang ditolak tidak memberi penjelasan pada halaman booking | Pasien tidak tahu apakah pembatalan berhasil dan tidak mempunyai langkah pemulihan yang jelas. |
| DFU-05 | P2 | Error format telepon disamarkan sebagai kegagalan pemeriksaan kandidat pasien | Resepsionis diarahkan mengulang pemeriksaan kandidat, padahal yang harus diperbaiki adalah panjang telepon. |
| DFU-06 | P2 | Telepon yang sama sekali bukan nomor diterima sebagai data pasien | Data kontak tidak dapat dipakai untuk komunikasi atau pencocokan kandidat yang diharapkan staf. |
| DFU-07 | P2 | Filter dashboard dengan rentang tanggal terbalik gagal tanpa pesan | Admin dapat membaca ringkasan periode lama sambil mengira filter baru sudah diterapkan. |
| DFU-08 | P2 | Waktu visit tampil UTC sementara konteks klinik memakai WIB | Dokter/pengelola dapat salah memahami jam layanan dan mencocokkan histori dengan antrean atau laporan. |
| DFU-09 | P2 | Sejumlah kontrol filter dan alasan pembatalan tidak mempunyai label programatis | Pengguna keyboard/teknologi bantu sulit mengenali tujuan kontrol. Audit tidak mengklaim uji pembaca layar fisik. |
| DFU-11 | P2 | Portal pasien tidak menyediakan jalur untuk membuat booking baru | Pengguna baru/kembali harus mengetahui URL eksternal atau meminta bantuan untuk memulai tugas utama. |
| DFU-12 | P2 | Double-click grant memberi dua periode dari satu tindakan pengguna | Klinik mendapat masa akses lebih panjang daripada yang dimaksud operator; histori komersial memuat grant ganda dan perlu koreksi administratif. |
| DFU-10 | P3 | Landing mobile mempunyai overflow horizontal | Geser halaman dan clipping kecil mengganggu kestabilan navigasi pada layar sempit. |

Paling dulu: **DFU-01**, karena pembatalan pasien membuat dokter tidak dapat menyelesaikan visit. Berikutnya **DFU-12** (grant ganda) dan **DFU-02** (sesi/booking berbeda hari). Temuan lain terutama validasi, feedback, aksesibilitas, waktu dan entry booking pasien. Source tidak diperbaiki selama audit.

## Apa yang sudah terbukti berfungsi

Registrasi dan verifikasi owner/pasien/staf dengan mail dummy; onboarding Free/trial; CRUD/lifecycle terpilih clinic/staff/service/schedule/patient; review duplikat keluarga; booking online/walk-in dan kuota; check-in/FIFO/call/skip/return/no-show/cancel; dokter start; SOAP/vital/structured/autosave/offline/conflict; resep final pasien; foto privat dan retensi koreksi; odontogram persistence dan revocation; pembatasan subscription dengan pengecualian visit aktif; katalog/snapshot/grant/downgrade/refund dummy; notification read; audit filter; role negative; keyboard drawer lima role. Bukti ini terikat pada skenario, bukan status PASS global setiap fitur.

## Angka dan bukti run

| Ukuran | Hasil |
| --- | --- |
| Fitur aktual / role | 36 kelompok;5 role;owner persona tambahan;guest/token context |
| Browser terbaru | 124 skenario aktif: 102 PASS, 17 FAIL, 5 BLOCKED, 0 NOT TESTED |
| Snapshot halaman | 213 observasi,51 keluarga URL,4 viewport probes masing-masing |
| Backend | 363/363 PASS;2438 assertion;SQLite memory |
| Gap terencana | 72 skenario tambahan berstatus/alasan; tidak dihitung sebagai eksekusi |
| Source baseline | Perubahan pengguna dipertahankan; tambahan hanya qa-audit/deep-functional-ux/ |
| Bukti raw | [scenarios.json](evidence/scenarios.json); [regression.txt](evidence/regression.txt); [pages.json](evidence/pages.json); [checkpoint.json](evidence/checkpoint.json) |

## Belum lengkap atau belum terverifikasi

DOKU sukses/notification/reconcile, Google OAuth, socket Reverb aktif putus/pulih, mail delivery nyata, seluruh kombinasi aksi/role/state, browser/perangkat fisik dan studi usability belum dituntaskan. Billing pasien, export/cetak, discovery luas, suspend/unsuspend UI, reminder appointment, multi-cabang dan Edge tidak menjadi fitur selesai hanya karena ada mock, enum, atau laporan lama. Lihat keputusan scope pada laporan missing dan gap per fitur. Tidak ada pengujian load/stress/deployment atau transaksi nyata.

## Laporan pendamping

- [FEATURE_INVENTORY.md](FEATURE_INVENTORY.md)
- [ROLE_ACTION_MATRIX.md](ROLE_ACTION_MATRIX.md)
- [SCENARIO_TEST_MATRIX.md](SCENARIO_TEST_MATRIX.md)
- [FUNCTIONAL_BUG_REPORT.md](FUNCTIONAL_BUG_REPORT.md)
- [MISSING_FEATURES_REPORT.md](MISSING_FEATURES_REPORT.md)
- [UX_PROBLEMS_REPORT.md](UX_PROBLEMS_REPORT.md)
- [EXPLORATORY_FINDINGS.md](EXPLORATORY_FINDINGS.md)
- [CROSS_FEATURE_REPORT.md](CROSS_FEATURE_REPORT.md)
- [COVERAGE_GAPS.md](COVERAGE_GAPS.md)

Audit historis pada qa-audit/ dan PROJECT_ANALYSIS.md dipakai sebagai referensi. Klaim Oktober yang sudah berubah (autosave, alergi, persistence dental, structured editor, ETA, katalog platform) tidak diulang sebagai kekurangan saat ini. Seluruh temuan baru berasal dari checkout lokal dan bukti run ini.
