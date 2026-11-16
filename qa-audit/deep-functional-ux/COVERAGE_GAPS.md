> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.

# Coverage Gaps & Resume Checkpoint

Run: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome 158.0.8127.36, headless nyata**; database/storage/sesi/build disposable.

PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.

## Cakupan yang dapat dipertanggungjawabkan

| Dimensi | Hasil run | Batas |
| --- | --- | --- |
| Fitur | 36/36 kelompok masuk inventaris; 35 mempunyai minimal satu skenario aksi browser PASS/FAIL. | Satu aksi bukan keseluruhan fitur; kelengkapan per fitur ada pada inventory. |
| Role | 5 role aktual; owner/non-owner admin, dokter gigi, guest, new staff, Free/trial/expired/suspended owner ikut dieksplorasi. | CRUD non-owner dan seluruh permission custom belum menyeluruh. |
| Halaman | 213 snapshot; 51 keluarga URL; probe4 lebar per snapshot. | Resize/DOM adalah probe layout; tidak semua aksi diulang pada tiap viewport. |
| Aksi | 119 skenario browser aktif dieksekusi; 102 PASS / 17 FAIL. | Jumlah klik/semua tombol yang dieksekusi tidak diinstrumentasi; tidak ada persentase palsu action coverage. |
| State | 8 queue states dijalankan/render-filter;4 appointment states;DRAFT/FINAL;Free/trial aktif UI;active/expired/suspended fixture. | 7 payment statuses dibuka, bukan7 provider lifecycle sukses; addon revoked bukan expiry waktu. |
| Skenario belum selesai | 5 aktif BLOCKED; 72 gap lanjutan terdaftar dengan status/alasan. | Gap tambahan tidak ditambahkan ke jumlah eksekusi browser. |
| End-to-end | Staff booking→dokter→final/koreksi; Patient booking→dokter→resep; owner onboarding; platform grant/refund lokal. | Provider Google/DOKU dan socket aktif tidak selesai end-to-end. |
| Backend regression | 363 tests, 2438 assertions, seluruhnya PASS. | SQLite memory; tidak dihitung sebagai UI PASS atau bukti concurrency MySQL. |

## Gap prioritas

| Fitur | Skenario | Status | Alasan |
| --- | --- | --- | --- |
| F00 | Navigasi Back/Forward landing pada seluruh anchor | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F00 | Katalog harga kosong/nonaktif seluruhnya dan pemulihan | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F01 | Remember-only session dan revokasi password pada browser baru | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F01 | Reset/verification link kedaluwarsa dan pemakaian ulang lewat UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F02 | Email registrasi duplikat/case variant melalui UI pasien dan owner | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F02 | Terms tidak disetujui serta refresh di setiap tahap registrasi | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F03 | Email duplikat dan batas panjang nama pada seluruh role | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F03 | Dua sesi profil aktif saat password/email berubah | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F04 | Lanjut onboarding yang ditinggalkan setelah logout/login | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F04 | Preferred plan dari setiap CTA landing sampai aktivasi | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F05 | Timezone WITA/WIT dan perubahan jam operasional terhadap sesi existing | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F05 | Logo tipe salah, ukuran lebih dari2MB, dan penggantian gagal | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F06 | Admin mengganti email/password staf dengan sesi remember aktif | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F06 | Batas doctor seat dari UI dan semua variasi entitlement paket | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F07 | Tarif pecahan/nonkelipatan1000 dan batas nominal server | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F07 | Layanan dinonaktifkan ketika tab booking lama masih terbuka | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F08 | Perubahan jam sesi terpakai serta quota diturunkan di bawah reservasi | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F08 | Batas quota0/501, akhir sebelum mulai, dan jam di luar operasional melalui UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F09 | Tanggal lahir masa depan dan seluruh field kosong melalui submit UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F09 | Pasien dengan histori sangat panjang dan setiap filter pencarian identitas | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F10 | Semua CTA dokter/layanan website klinik dalam kondisi tanpa dokter/sesi | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F10 | Alur pengguna pertama tanpa URL klinik dalam studi awam langsung | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F11 | Tanggal tepat+30/+31 dan sesi baru saja berakhir | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F11 | Dua form/tab dengan UUID berbeda untuk pasien/sesi sama | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F12 | Replay cancel dari tab stale untuk seluruh status terminal | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F12 | Tautan status tanpa/salah token dan cancellation error pada template resepsionis lewat browser | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F13 | Aksi stale dari dua tab resepsionis dan dokter terhadap queue sama | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F13 | Filter dokter/sesi dikombinasikan dengan pagination dan status terminal | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F14 | Display klinik kosong dan seluruh kontrol filter/kiosk | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F14 | Public display putus/pulih dari socket nyata | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F15 | Ulang Mulai periksa dari tab lama sesudah visit selesai | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F15 | Semua kombinasi pencarian/filter index rekam medis | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F16 | HTTP419/sesi berakhir saat autosave dan pemulihan isi editor | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F16 | Batas20 diagnosis/30 tindakan serta seluruh nilai vital ekstrem | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F16 | Refresh/Back/leave sebelum debounce autosave selesai dan ketika foto belum disimpan | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F17 | Edit severity lain dan dua editor alergi bersamaan melalui UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F17 | Semua variasi acknowledgement alergi pada resep baru/koreksi | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F18 | Hapus/tambah30 obat, jumlah9999/10000, serta resep kosong final | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F18 | Seluruh variasi preview, instruksi panjang dan koreksi yang mengubah resep pasien | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F19 | Paste clipboard, batas10 foto/5MB, tipe salah dan abort upload | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F19 | Akses foto revisi oleh setiap role/tenant asing melalui browser | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F20 | Dua submit finalisasi bersamaan dan tab koreksi stale melalui UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F20 | Semua perbedaan array panjang pada histori koreksi di mobile | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F21 | Seluruh32 gigi×6 kondisi dan correction-final dental melalui UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F21 | Expiry berdasarkan ends_at; pencabutan revoked_at sudah diuji, keduanya tidak disamakan | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F22 | Rekonsiliasi angka seluruh dashboard terhadap fixture setiap periode/timezone | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F22 | Semua shortcut, empty/loading dan variasi data panjang per role | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F23 | Pagination/empty notification center untuk setiap role | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F23 | Notifikasi masuk socket dan badge realtime lintas browser | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F24 | Kombinasi filter aktor/tanggal, pagination, serta tidak ada hasil | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F24 | Validasi semua metadata audit yang ditampilkan di browser | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F25 | Transisi expiry oleh waktu serta reminder subscription yang dipicu command | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F25 | Seluruh kombinasi paket, renewal terjadwal dan seat overage pada UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F26 | Successful checkout/notification/reconcile provider nyata | BLOCKED | Memerlukan provider/kredensial dummy eksternal; sengaja tidak memakai akun/transaksi pengguna. |
| F26 | Repeat/delay callback dan browser-return nyata bersama provider | BLOCKED | Memerlukan provider/kredensial dummy eksternal; sengaja tidak memakai akun/transaksi pengguna. |
| F27 | Prorata akhir hari/bulan dan renewal gabungan lewat UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F27 | Refund yang mencabut add-on melalui flow provider; fixture revoke saja sudah diuji | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F28 | Pencarian owner-email, pagination banyak tenant, dan tenant inactive | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F28 | Semua metadata commercial history di setiap state tenant | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F29 | Harga invalid, price toggle inactive dan addon toggle inactive lewat UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F29 | Katalog diubah ketika owner masih memegang tab checkout lama | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F30 | Grant lebih tinggi/setara terjadwal dan downgrade paid dengan overlap | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F30 | Pemulihan grant ganda dan kebijakan revoke grant yang belum tersedia di UI | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F31 | Refund ulang pada tab stale dan seluruh status yang tidak refundable | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F31 | Reconcile+refund lintas role/tenant pada UI, tanpa transaksi provider nyata | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F32 | Confidence medium/high dengan data historis terkontrol di browser | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F32 | ETA untuk CALLED/IN_PROGRESS/terminal dan seluruh kombinasi antrian depan | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F33 | Socket aktif putus/pulih, missed-event dan permission revoked pada koneksi aktif | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F33 | Fokus input saat refetch/pagination dan semua offline queue actions | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F34 | Google OAuth success/callback/error/relink dengan akun dummy provider | BLOCKED | Memerlukan provider/kredensial dummy eksternal; sengaja tidak memakai akun/transaksi pengguna. |
| F35 | NIK format/normalisasi telepon berbagai negara dan lebih dari8 kandidat | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |
| F35 | Review token stale setelah kandidat berubah dan navigasi ulang ke profil inactive | NOT TESTED | Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini. |

## Batas lingkungan dan cara melanjutkan

- Database browser adalah SQLite file sementara; regression memakai SQLite :memory:, bukan database aplikasi/MySQL pengguna. Storage/public build dipisah melalui bootstrap/router audit. Factory/default relasi tidak diasumsikan benar tanpa fixture eksplisit.
- Mail verification/reset ditangkap dari array transport ke berkas privat sementara dan tautannya dibuka di Chrome; pengiriman SMTP/inbox pengguna nyata belum dibuktikan. Mail dummy privat dibersihkan setelah pengujian.
- DOKU/Google credentials dikosongkan. Aktivasi add-on untuk uji dental memakai fixture entitlement; tidak membuktikan purchase atau callback sukses.
- Build frontend awal mewarisi konfigurasi Vite; sebelum retest polling digunakan target socket disposable yang eksplisit. ETA-01/R1 memiliki budget16detik dan tidak membuktikan gagal fungsional; ETA-01-R2 foreground/budget45detik PASS. Tidak ada klaim latency SLA.
- Skill testing-best-practices tidak ditemukan pada pencarian katalog lokal; tidak diklaim dibaca. Tidak ada test PHP baru atau source Volt yang diubah. Panduan Ponytail dipakai; volt-development dibaca sebagai konteks komponen auth/profil. Tool search-docs tidak tersedia pada sesi ini; tidak ada API Volt baru diimplementasikan.
- Tidak menjalankan Pint terhadap perubahan PHP pengguna, karena audit hanya menambah artefak/scripts JS dan tidak mengubah source PHP.
- Perangkat fisik, screen reader, zoom200%, usability study awam, semua kombinasi field/status/tab tidak selesai.
- Snapshot/page census dan skenario awal tersimpan pada JSON. Checkpoint bernama per fase tersedia sejak pembaruan harness di fase auth; checkpoint awal yang sempat ditimpa dapat ditelusuri dari started timestamps dan hasil skenario, bukan checkpoint historis yang direkayasa.

Server audit telah dihentikan. Database/storage dummy dipertahankan untuk checkpoint; environment privat dipangkas ke nilai disposable eksplisit dan kredensial eksternal tetap kosong. Untuk melanjutkan fixture yang sama: jalankan `node qa-audit/deep-functional-ux/scripts/resume-runtime.mjs`, lalu tambah/jalankan fase yang diperlukan. `runtime.mjs` membuat fixture baru; gunakan direktori evidence baru bila ingin audit ulang bersih. AUDIT_RESUME=1 melewati PASS dan menambahkan attempt untuk skenario sebelumnya gagal; jangan menjalankan semua fase ulang pada data yang telah dihapus/diubah tanpa memeriksa precondition. Evidence JSON dan status adalah sumber checkpoint, bukan sekadar nama file laporan.
