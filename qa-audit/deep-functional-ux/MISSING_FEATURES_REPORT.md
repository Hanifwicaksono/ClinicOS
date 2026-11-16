> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.

# Missing Features, Incomplete Workflow & Product Decisions

Run: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome 158.0.8127.36, headless nyata**; database/storage/sesi/build disposable.

PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.

## DFU-11 — Portal pasien tidak menyediakan jalur untuk membuat booking baru

| Atribut | Detail |
| --- | --- |
| Kategori | Missing Functionality / UX Discoverability |
| Severity | P2 |
| Fitur / role | F10/F11; Patient |
| Kondisi awal | Pasien masuk melalui landing/login umum, atau kembali ke dashboard setelah kunjungan. |
| Langkah reproduksi | 1. Login pasien → dashboard. → 2. Periksa seluruh menu dan link dashboard. → 3. Buka Booking saya. → 4. Cari Buat booking, booking ulang, atau tautan ke website klinik sebelumnya. |
| Hasil aktual | Dashboard menawarkan Lihat booking saya dan profil; daftar hanya berisi detail booking. Tidak ditemukan tautan membuat booking atau kembali ke website klinik pada kedua layar. Booking tetap bekerja bila pasien sudah mengetahui URL website klinik dari luar portal. |
| Hasil yang diharapkan | Ada jalur yang jelas menuju booking berikutnya, setidaknya ke klinik yang telah dikenal pasien. Penemuan lintas klinik dapat tetap menjadi keputusan produk terpisah. |
| Dampak pengguna | Pengguna baru/kembali harus mengetahui URL eksternal atau meminta bantuan untuk memulai tugas utama. |
| Rekomendasi | Tetapkan entry booking dari portal pasien dan empty state; tambahkan tautan klinik/booking ulang sesuai kepemilikan. Hindari menyamakan kebutuhan ini dengan keharusan membangun marketplace klinik. |
| Skenario | UX-02 |
| Bukti | [patient-booking-discovery.json](evidence/patient-booking-discovery.json), [screenshots/UX-02-no-new-booking-action.png](evidence/screenshots/UX-02-no-new-booking-action.png), [navigation.json](evidence/navigation.json) |
| Sumber terkait | [resources/views/dashboard.blade.php:19](../../resources/views/dashboard.blade.php#L19), [resources/views/patient/appointments/index.blade.php:3](../../resources/views/patient/appointments/index.blade.php#L3) |

## MF-02 — Kasir, billing dan receipt pasien

| Atribut | Detail |
| --- | --- |
| Kategori | Product Decision Required; gap requirement, bukan regresi endpoint. |
| Severity | P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime. |
| Fitur/role | Clinic Admin / Receptionist / Patient |
| Kondisi awal | Requirement lebih luas pada PROJECT_ANALYSIS.md/PRD; Payment saat ini adalah subscription SaaS. |
| Langkah reproduksi/penemuan | Menu admin/resepsionis/pasien → cari tagihan layanan, pembayaran pasien dan receipt; telusuri route/model/controller. |
| Hasil aktual | Tidak ditemukan alur tagihan/penerimaan kas pasien. Visit.total_amount hanya estimasi nilai layanan. |
| Hasil diharapkan | Tentukan kebutuhan billing pasien dan histori transaksi secara terpisah dari subscription. |
| Dampak | Kebutuhan penerimaan kas belum diselesaikan oleh dashboard nilai layanan. |
| Bukti | [routes.json](evidence/routes.json), [navigation.json](evidence/navigation.json), [source-controls.json](evidence/source-controls.json); source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis. |
| Rekomendasi | Tentukan kebutuhan billing pasien dan histori transaksi secara terpisah dari subscription. |
| Status pengujian | NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end. |

## MF-03 — Ekspor laporan dan cetak resep/receipt

| Atribut | Detail |
| --- | --- |
| Kategori | Missing Functionality terhadap requirement laporan; cetak resep perlu keputusan produk. |
| Severity | P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime. |
| Fitur/role | Clinic Admin / Doctor |
| Kondisi awal | Ringkasan dashboard dan resep sudah ada; kebutuhan laporan/export lebih luas tercatat pada dokumen. |
| Langkah reproduksi/penemuan | Buka dashboard, audit aktivitas dan resep final; cari ekspor/cetak; periksa route dan source. |
| Hasil aktual | Tidak ditemukan alur export CSV/PDF atau tombol cetak resep/receipt yang didukung aplikasi. |
| Hasil diharapkan | Tentukan keluaran administratif/klinis yang diperlukan dan hak aksesnya sebelum implementasi. |
| Dampak | Pengguna belum dapat menyelesaikan kebutuhan berbagi/cetak dari aplikasi; browser print generik tidak diuji sebagai solusi. |
| Bukti | [routes.json](evidence/routes.json), [navigation.json](evidence/navigation.json), [source-controls.json](evidence/source-controls.json); source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis. |
| Rekomendasi | Tentukan keluaran administratif/klinis yang diperlukan dan hak aksesnya sebelum implementasi. |
| Status pengujian | NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end. |

## MF-04 — Discovery lintas klinik

| Atribut | Detail |
| --- | --- |
| Kategori | Product Decision Required. |
| Severity | P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime. |
| Fitur/role | Patient / Guest |
| Kondisi awal | Website klinik individual ada; discovery luas bukan bagian backend yang ditemukan. |
| Langkah reproduksi/penemuan | Buka landing dan portal pasien; cari directory/pencarian klinik; periksa route. |
| Hasil aktual | Tidak ditemukan directory atau pencarian lintas klinik. |
| Hasil diharapkan | Putuskan apakah masuk scope produk. Jalur booking ulang DFU-11 tetap dapat diperbaiki tanpa discovery luas. |
| Dampak | Pasien tanpa URL klinik tidak mempunyai pencarian mandiri dalam aplikasi. |
| Bukti | [routes.json](evidence/routes.json), [navigation.json](evidence/navigation.json), [source-controls.json](evidence/source-controls.json); source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis. |
| Rekomendasi | Putuskan apakah masuk scope produk. Jalur booking ulang DFU-11 tetap dapat diperbaiki tanpa discovery luas. |
| Status pengujian | NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end. |

## MF-05 — Suspend/unsuspend tenant melalui UI platform

| Atribut | Detail |
| --- | --- |
| Kategori | Incomplete Workflow / Product Decision Required. |
| Severity | P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime. |
| Fitur/role | Platform Admin |
| Kondisi awal | Status suspended ada dan sudah diuji sebagai fixture. |
| Langkah reproduksi/penemuan | Tenant klinik → Detail; cari lifecycle suspend/unsuspend dan periksa route aksi. |
| Hasil aktual | UI menyediakan grant dan downgrade Free, tidak menyediakan suspend/unsuspend/revoke grant langsung. |
| Hasil diharapkan | Tentukan aktor, alasan, notifikasi, dan pemulihan tenant sebelum menambah tindakan lifecycle. |
| Dampak | Operator platform tidak dapat mengelola status suspended dari UI yang tersedia. |
| Bukti | [routes.json](evidence/routes.json), [navigation.json](evidence/navigation.json), [source-controls.json](evidence/source-controls.json); source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis. |
| Rekomendasi | Tentukan aktor, alasan, notifikasi, dan pemulihan tenant sebelum menambah tindakan lifecycle. |
| Status pengujian | NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end. |

## MF-06 — Reminder appointment/mendekati giliran pasien

| Atribut | Detail |
| --- | --- |
| Kategori | Missing Functionality terhadap cakupan lama NOTIF-3; operational delivery belum diuji. |
| Severity | P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime. |
| Fitur/role | Patient / Guest |
| Kondisi awal | Notification center booking/status ada; scheduler yang ditemukan adalah subscription reminder. |
| Langkah reproduksi/penemuan | Buka daftar notifikasi dan tracker; telusuri jobs/notifications/console trigger near-turn/appointment reminder. |
| Hasil aktual | Tidak ditemukan reminder terjadwal appointment atau trigger mendekati giliran. |
| Hasil diharapkan | Putuskan kanal dan waktu reminder; verifikasi pengiriman end-to-end saat tersedia. |
| Dampak | Pasien harus memantau sendiri. Reminder subscription tidak membuktikan reminder kunjungan. |
| Bukti | [routes.json](evidence/routes.json), [navigation.json](evidence/navigation.json), [source-controls.json](evidence/source-controls.json); source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis. |
| Rekomendasi | Putuskan kanal dan waktu reminder; verifikasi pengiriman end-to-end saat tersedia. |
| Status pengujian | NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end. |

## MF-07 — Multi-cabang dan beberapa klinik per owner

| Atribut | Detail |
| --- | --- |
| Kategori | Product Decision Required / outside current implementation. |
| Severity | P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime. |
| Fitur/role | Clinic Owner / Staff |
| Kondisi awal | Dokumen luas menyebut multi-cabang; invarian checkout satu owner/satu klinik. |
| Langkah reproduksi/penemuan | Periksa onboarding, model owner/clinic, navigasi dan route pemilihan cabang. |
| Hasil aktual | Tidak ada branch/organization atau perpindahan tenant multi-klinik owner. |
| Hasil diharapkan | Tetapkan scope sebelum memperluas model dan tenancy. |
| Dampak | Kebutuhan organisasi bercabang belum didukung; bukan alasan untuk menghapus batas tenant sekarang. |
| Bukti | [routes.json](evidence/routes.json), [navigation.json](evidence/navigation.json), [source-controls.json](evidence/source-controls.json); source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis. |
| Rekomendasi | Tetapkan scope sebelum memperluas model dan tenancy. |
| Status pengujian | NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end. |

## MF-08 — Resilient Edge/offline operasional penuh

| Atribut | Detail |
| --- | --- |
| Kategori | Product Decision Required; pengujian Edge tidak dapat dijalankan karena fitur tidak ada. |
| Severity | P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime. |
| Fitur/role | All clinic roles |
| Kondisi awal | Autosave memiliki retry/offline UI; itu berbeda dari operasi klinik offline penuh. |
| Langkah reproduksi/penemuan | Putuskan koneksi pada editor; telusuri source sync/local API/outbox untuk Edge. |
| Hasil aktual | Editor mempertahankan data dan menyimpan saat koneksi pulih, tetapi backend lokal/sync klinik offline tidak ditemukan. |
| Hasil diharapkan | Tetapkan kebutuhan offline operasional; jangan mengklaim mode Edge dari polling atau retained editor. |
| Dampak | Booking/antrean baru tetap memerlukan backend. |
| Bukti | [routes.json](evidence/routes.json), [navigation.json](evidence/navigation.json), [source-controls.json](evidence/source-controls.json); source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis. |
| Rekomendasi | Tetapkan kebutuhan offline operasional; jangan mengklaim mode Edge dari polling atau retained editor. |
| Status pengujian | NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end. |

## Keputusan produk lain yang belum boleh dianggap bug

| ID | Keputusan | Observasi dan konsekuensi | Bukti |
| --- | --- | --- | --- |
| PD-01 | Trial setelah memilih Free | Trial hanya ditawarkan sebelum subscription awal; Free yang sudah dipilih tidak menampilkan trial meskipun pengguna belum mencobanya. Tentukan apakah ini sengaja dan jelaskan sebelum aktivasi. | AUTH-03, AUTH-04-R1; onboarding/plans/index.blade.php |
| PD-02 | Ubah/batal booking vs reschedule | UI mendukung pembatalan dan booking baru. Direct reschedule belum ditemukan; tentukan kebutuhan mempertahankan nomor/histori jika menambahnya. | Route inventory; F11/F12 |
| PD-03 | Penghapusan staf/pasien/medis | Tidak wajib menyediakan destroy. Deactivate/reactivate, terminal state, dan Modification adalah lifecycle yang tersedia dan menjaga histori. Role staf immutable dari edit form. | ADM-07, PAT-05, RME-09-R3, PHOTO-HISTORY, DOCTOR-DELETE-GUARD |
| PD-04 | Cakupan dental/obat | Odontogram32 gigi permanen, nama obat free text, warning interaksi bersifat pengingat. Dentisi anak/katalog/engine klinis perlu requirement eksplisit; tidak dinyatakan cacat tanpa acuan. | F18/F21; source dental-chart/VisitController |
