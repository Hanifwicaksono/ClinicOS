# Exploratory test report

> **Status terbaru setelah P2, 11 November 2026:** Penelusuran jalur gagal email menemukan QA-15 / P2; direproduksi 500 setelah commit dan diperbaiki dengan regression. Probe keyboard tanpa event dan run bersamaan dengan build dipisahkan sebagai masalah harness, bukan bug aplikasi tambahan. Lihat [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Riwayat laporan sebelum perbaikan P2

Bagian di bawah mempertahankan checkpoint P1 dan baseline audit; status/angka historis tidak menggantikan hasil terbaru di atas.

> **Pembaruan setelah perbaikan P1, 11 November 2026:** QA-01 - QA-04 sudah FIXED pada pengujian ulang; P2 belum diperbaiki. Hasil terbaru: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Dua bug staf baru QA-13/QA-14 dan risiko sesi setelah reset admin tetap terbuka.

## Baseline audit sebelum perbaikan

Bagian berikut mempertahankan hasil/source yang diamati saat audit awal. Angka, status FAIL P1 dan batas pada baseline ini bukan status terbaru; bukti lama tidak ditimpa.

11 November 2026. Eksplorasi memakai Chrome headless nyata, database demo baru, login melalui UI, form dan request browser, serta pemeriksaan database fixture setelah aksi. Hasil dicatat pada interactive-browser.json; scenario browser berulang tersedia sebagai browser-audit.test.mjs. Tidak ada screenshot atau data pasien asli yang dikumpulkan.

## Persona dan charter

| Persona | Jalur yang dicoba | Hasil aktual | Cakupan role |
| --- | --- | --- | --- |
| Baru, belum memahami aplikasi | Menu setelah login; onboarding owner; Patient membuka booking publik | PASS navigasi; FAIL pilihan dokter publik QA-01; delivery verifikasi BLOCKED | Lima role menu; owner onboarding; Patient booking |
| Berpengalaman | Buka seluruh menu; langsung ke URL area; booking existing patient dan pemeriksaan sampai final | PASS 29 link menu, E03; PASS penolakan dashboard asing | Lima role; alur operasional Receptionist/Doctor |
| Terburu-buru | Simpan draft, finalize, reload, kendali submit/idempotency; public booking tanpa pengetahuan implementasi | PASS autosave/UUID backend; FAIL booking publik tidak menyediakan pilihan bekerja | Doctor/Patient/Receptionist; double click setiap form NOT TESTED |
| Sering salah input | Numeric vital invalid, resep draft belum lengkap, duplicate review, payload array nullable | PASS error/review terpilih; FAIL nullable diagnosis/tindakan menghasilkan 500 | Doctor/Receptionist; bukan semua field semua role |
| Bertindak tidak berurutan | Buka add-on sebelum klinik; ganti dokter sesi terpakai; hapus owner sesudah onboarding | FAIL QA-07/04/03; backend invalid queue transitions PASS | Owner/Clinic Admin; Doctor queue lewat tests |
| Menggunakan aplikasi lama | Ubah role/active flag di tengah sesi; RME offline lalu online; reload draft | PASS HTTP session invalidation, role check dan recovery RME | Lima role inactive, satu live role change; soak jam/hari NOT TESTED |
| Mencari tanpa dokumentasi | Semua link sidebar; buka drawer pada 375px; pilih dokter pada halaman pasien | PASS menu role; FAIL Escape QA-09, booking QA-01; discovery klinik tidak tersedia | Lima role menu, keyboard drawer admin, Patient booking |

Tidak diklaim tujuh persona telah menjalankan semua fitur untuk setiap role. Pengalaman seorang pengguna baru menemukan klinik dari dashboard pasien masih berhenti tanpa tautan klinik/directory; itu gap discovery, bukan hasil pencarian layanan eksternal yang sudah diuji.

## Temuan dan counterexample

- Halaman publik booking mengembalikan 200 dan menampilkan form rapi. Baru setelah interaksi pilihan dokter, terlihat tidak ada runtime Alpine dan tidak ada option yang dirender. Endpoint test yang hijau tidak mendeteksi kegagalan ini.
- Jalur Receptionist → Doctor dapat diselesaikan; check-in tidak membuat visit lebih awal, draft tersimpan, foto dummy terunggah, finalisasi atomik. Koreksi foto setelah finalisasi kemudian menghapus file histori: keberhasilan alur biasa tidak menjamin alur perbaikan aman.
- Mengganti dokter sesi tampak sebagai edit admin biasa. Backend menerima perubahan tetapi booking lama tetap pada dokter lama. Ini masalah antarfitur, bukan validasi foreign key individual.
- Hapus akun owner berhasil dari sudut pandang auth/logout, tetapi proses pengelolaan tenant selanjutnya kehilangan owner. Tidak tersedia UI transfer/pemulihan dalam penelusuran route dan menu.
- CSRF probe same-origin semula mencapai business validation 422. Pemeriksaan terhadap middleware Laravel 13 menunjukkan origin verification memang menerima same-origin. Probe cross-site tanpa token kemudian ditolak 419; tidak dilaporkan sebagai kerentanan.
- Percobaan browser awal memakai perubahan state Alpine secara programatis membuat input pencarian dokter belum terisi. Percobaan tersebut dibuang sebagai masalah harness; pemilihan option UI kemudian berhasil. Timeout akibat redirect indeks klinik juga tidak dilaporkan sebagai bug aplikasi.

## Batas eksperimen

Role/inactive diubah hanya pada database fixture untuk menguji sesi, karena UI role editor memang tidak tersedia. Akun Doctor/owner pada beberapa alur diberi status verified pada fixture; tidak ada klaim inbox email sudah diuji. Worker memakai transport null. Pemeriksaan viewport tidak menggantikan perangkat mobile fisik, screen reader, atau soak.

Eksperimen focus-order lengkap diberi NOT TESTED ketika urutan fokus tidak dibuktikan; hanya kegagalan Escape yang dilaporkan. Setiap bug mempunyai reproduksi dan bukti terpisah di BUG_REPORT, sehingga kesalahan harness tidak dicampur dengan temuan aplikasi.
