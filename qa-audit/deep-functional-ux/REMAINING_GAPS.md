# Remaining Gaps

Status ini adalah hasil aktual penutupan daftar, bukan klaim seluruh aplikasi sudah tertutup100%. Seluruh77 parent sudah mempunyai hasil atau alasan; subalur yang belum terbukti tetap terbuka di bawah.

| Parent | Status | Bukti parsial yang sudah ada | Yang belum bisa dinyatakan selesai / cara menutup |
| --- | --- | --- | --- |
| G10 | BLOCKED | Ketiga CTA Free/Individual/Team menjaga preferred plan sampai halaman paket, termasuk refresh. Free aktif melalui UI. | Aktivasi Individual/Team melalui provider belum dapat dieksekusi tanpa kredensial sandbox DOKU; initial paid fixtures bukan bukti checkout. |
| G22 | NOT TESTED | Navigasi agen telah diamati, tetapi bukan studi partisipan awam. | Tidak ada partisipan manusia untuk studi first-use tanpa pengetahuan URL/kode klinik; waktu, keberhasilan mandiri dan pemahaman tidak boleh disimulasikan oleh agen. |
| G29 | NOT TESTED | Display klinik kosong dan kontrol yang benar-benar tersedia sudah diuji; link booking menuju login. | Tidak ditemukan kontrol kiosk/filter display khusus pada UI/source saat ini. Tidak menganggap kontrol fiktif sebagai PASS; requirement/desain kontrol perlu ditetapkan. |
| G33 | FAIL | Logout tab lain menghasilkan401 nyata; editor mempertahankan teks tetapi menyarankan periksa koneksi. Relogin same-origin menghasilkan200. CCL-02. | 419 nyata belum diperoleh: Laravel13 menerima Sec-Fetch-Site same-origin; logout saja berhenti di auth401. Skenario browser/proxy tanpa Fetch Metadata memerlukan lingkungan tersendiri; tidak memalsukan respons419. |
| G47 | NOT TESTED | Seluruh tujuan shortcut berbeda diklik pada5 role; nama255 diperiksa375px pada5 role; notifikasi empty5 role ada di G48. | Empty/loading dashboard lintas seluruh role belum terbukti. Platform memiliki tenant pada initial fixture dan tidak menyediakan UI hapus seluruh tenant untuk membuat state global kosong; menghapus langsung DB selama audit melanggar metode. Diperlukan initial fixture terpisah untuk dashboard global kosong dan kontrak loading (Blade tidak memiliki state loading aplikasi khusus). Shortcut/profile PASS tidak menutup subkasus ini. |
| G54 | BLOCKED | Guard lokal dan fixture pembayaran sah tersedia; tidak ada bukti pembayaran eksternal sukses. | Kredensial sandbox DOKU dan transaksi sandbox tervalidasi tidak tersedia; tidak memakai akun atau pembayaran pengguna. |
| G55 | BLOCKED | Snapshot invoice dan stale UI katalog diuji pada G60/G61. | Repeat/delay callback serta browser-return dengan provider nyata memerlukan DOKU sandbox; backend fake-provider historis tidak dihitung sebagai browser provider PASS. |
| G56 | BLOCKED | Prorata satu hari akhir bulan dan8 kombinasi plan/addon/durasi dihitung lewat UI. | Pembayaran renewal gabungan sampai aktivasi menunggu provider sandbox; pemilihan dan total UI PASS, settlement tidak diuji. |
| G57 | BLOCKED | Refund lokal manual lewat UI membatalkan entitlement dan menjaga histori pada G64. | Aplikasi merekam refund manual; tidak ditemukan flow refund eksternal dari browser. Refund oleh provider dan delivery eventnya memerlukan kontrak serta kredensial sandbox, tidak disamakan dengan revoke fixture/manual. |
| G59 | NOT TESTED | Metadata subscription saat ini terlihat untuk Free/Individual/Team/trial/expiry. | Detail tenant hanya menampilkan subscription saat ini; tidak ada viewer seluruh periode commercial history. Schema/history backend bukan bukti UI. Perlu keputusan tampilan histori dan field yang memang boleh dibuka Platform. |
| G63 | NOT TESTED | Pencegahan grant duplikat DFU-12 tetap FIXED & VERIFIED dan tidak dijalankan ulang. | Tidak ada UI pemulihan duplicate grant historis atau single-grant revoke. Keputusan revoke/refund/penyesuaian periode dibutuhkan; tidak menghapus atau mengubah histori melalui DB saat audit. |
| G65 | BLOCKED | Foreign owner/admin/doctor/receptionist/Patient mendapat403/404 pada GET invoice asing dan tidak melihat tombol refund; tidak ada POST reconcile/refund negatif yang dilakukan lewat form yang tidak tersedia. | Reconcile yang diizinkan kepada Platform Admin memerlukan provider sandbox. Pemeriksaan akses/visibilitas negatif tidak membuktikan authorization POST atau reconcile positif; refund lokal ditutup G64. |
| G68 | NOT TESTED | Socket aktif disconnected/reconnected dan missed-event dibuktikan WS-REAL-RECOVERY-R2. | UI staf mengubah akun, bukan permission role aktif secara terpisah; tidak ada editor custom permission. Revokasi permission pada koneksi socket yang sama belum dapat dibuktikan tanpa mutasi langsung permission DB atau fitur pengelolaan yang belum ada. |
| G70 | BLOCKED | Konfigurasi provider audit sengaja kosong; jalur lokal auth telah diuji terpisah. | Tidak tersedia Google OAuth client sandbox/akun dummy/callback yang diotorisasi; tidak memakai akun pengguna atau menciptakan hasil callback provider. |
| AUTH-GOOGLE | BLOCKED | Provider eksternal belum tersedia; tidak memakai akun atau transaksi pengguna. | kredensial eksternal sengaja dikosongkan; tidak memakai akun pengguna. |
| DOKU-SUCCESS | BLOCKED | Provider eksternal belum tersedia; tidak memakai akun atau transaksi pengguna. | tidak ada kredensial provider dummy untuk audit ini; tidak melakukan pembayaran nyata. |
| DOKU-RECONCILE | BLOCKED | Provider eksternal belum tersedia; tidak memakai akun atau transaksi pengguna. | provider sengaja dinonaktifkan; regression fake-provider berjalan terpisah. |
| DOKU-WEBHOOK | BLOCKED | Provider eksternal belum tersedia; tidak memakai akun atau transaksi pengguna. | tidak ada provider callback nyata pada audit fungsional browser ini; tests/backend saja. |

## Temuan yang masih gagal

Delapan akar masalah CCL-01–CCL-08 tetap OPEN.9 assertion FAIL tidak diperbaiki karena audit melarang perubahan produksi. [Reproduksi dan bukti](NEW_FUNCTIONAL_FINDINGS.md). Sesudah perbaikan terpisah, ulangi case FAIL terkait dan dependensinya; tidak perlu mengulang semua PASS.

## Keputusan produk yang belum boleh diasumsikan

- G29: tentukan apakah kiosk/filter khusus memang fitur yang diwajibkan dan kontrolnya. Display kosong yang ada sudah lulus.
- G59: tentukan data periode/histori komersial yang harus tersedia pada UI Platform; backend history saja bukan viewer.
- G63: pilih pemulihan duplicate grant lama (revoke period tertentu, refund atau penyesuaian terjadwal) dengan histori/audit utuh. Pencegahan replay DFU-12 sudah diverifikasi sebelumnya.
- G68: perlu jalur pengelolaan permission yang sah untuk membuktikan revokasi koneksi aktif; deaktivasi staf/remember-token adalah kontrak berbeda.
- CCL-05: putuskan penanganan sesi existing ketika jam operasional berubah; jangan menggeser atau membatalkan appointment lama diam-diam.
- Dua editor alergi menyimpan versi append-only tanpa kehilangan kedua perubahan; kebijakan satu versi aktif/merge tidak diciptakan dalam audit.

## Batas bukti yang berlaku pada PASS

Kelas ekuivalensi dibatasi kontrak/source saat ini:8 queue states,4 severity,5 role tersimpan,3 paket+trial,8 vital,6 keluarga pencarian RME dan4 keluarga array koreksi. Istilah “semua kombinasi” tidak berarti setiap string, tanggal, jumlah antrean atau permutasi tanpa batas. G67 memeriksa0/1/2 aktif, front status aktif/tidak aktif dan campuran; tidak melakukan load/stress test. G37 memeriksa cabang acknowledgement kritis/resep kosong, bukan klaim studi keselamatan klinis.

SQLite/PHP server tunggal tidak membuktikan row locking atau concurrency MySQL. Dua browser submit/replay adalah bukti UI idempotency, bukan stress test. Notifikasi/reminder memakai worker dan command terisolasi; scheduler OS, email provider dan operasi produksi tidak diuji.

Mobile375 digunakan pada profil lima role dan histori panjang/dental. Probe empat viewport dari audit/regression sebelumnya dipertahankan; audit ini tidak mengulang seluruh aksi pada375/768/1024/1440. Tidak ada screen reader, perangkat sentuh fisik, studi partisipan awam atau klaim aksesibilitas menyeluruh.

G47 membutuhkan fixture empty dashboard dan assertion loading yang didefinisikan per role; tidak ada state loading aplikasi khusus pada Blade saat ini. G33/419 membutuhkan browser/proxy tanpa Fetch Metadata yang memicu419 nyata, bukan response stub. Untuk Google/DOKU gunakan sandbox dummy dengan callback terdaftar; jangan memakai kredensial pengguna.
