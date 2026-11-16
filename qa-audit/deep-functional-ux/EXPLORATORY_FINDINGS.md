> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.

# Exploratory Findings — charter dan langkah aktual

Run: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome 158.0.8127.36, headless nyata**; database/storage/sesi/build disposable.

PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.

| Charter | Persona/tujuan | Langkah aktual dan data | Hasil/evidence |
| --- | --- | --- | --- |
| EX-01 | Pemilik klinik pemula | Buat akun owner → signed mail dummy → klinik → Free; owner kedua memilih Trial awal. Akun QA dan klinik QA baru. | AUTH-02/03/04-R1, CLINIC-*; entry aktif, batas trial setelah Free dicatat PD-01. |
| EX-02 | Resepsionis terburu-buru/salah isi | Buat pasien, teleponabc/31digit, shared-family phone dengan review, cari/edit/deactivate; booking keyboard. | ADM-10/11/12/13, UX-05, PAT-05; DFU-05/06. |
| EX-03 | Pelayanan lintas role | Resepsionis booking/check-in/call/skip/return → dokter start/SOAP/final/Modification → buka ulang. | BOOK-/QUEUE-/RME-; normal flow berjalan; feedback queue DFU-03. |
| EX-04 | Pasien kembali tanpa membaca instruksi | Login umum → dashboard → Booking saya → cari booking baru; akhirnya memakai website klinik yang URL-nya diketahui. | UX-02, BOOK-03-R1; DFU-11. |
| EX-05 | Pasien dan dokter melakukan urutan tidak lazim | Mulai pemeriksaan sebelum jam sesi → pasien membatalkan → dokter mencoba finalisasi. | CROSS-03/04/12; data dummy appointment25/visit16; DFU-01. |
| EX-06 | Dua tab dan koneksi editor putus | Buka record sama di dua tab; edit tab pertama lalu stale tab; offline lalu online; foto manual dan histori. | RME-05/06/07, PHOTO-HISTORY; stale ditolak, data pulih, foto histori200. |
| EX-07 | Admin mengelola data yang sudah dipakai | Edit hari sesi berbooking/final visit; nonaktif layanan, lihat snapshot dan pilihan baru; pulihkan fixture lewat UI. | CROSS-08, SERVICE-05; DFU-02. |
| EX-08 | Operator platform salah klik | Edit harga/toggle katalog; grant/downgrade; refund review dummy; double-click grant satu bulan. | PLAT-*, GRANT-DOUBLE; snapshot aman, grant ganda DFU-12. |
| EX-09 | Pemakai mobile/tablet dan keyboard | 375px booking double-click;768px queue;1024px dokter;1440px admin; lima drawer Tab/ShiftTab/Escape; label filter. | CROSS-01-R1/03, KEY-01, UX-01/03; DFU-09/10. |
| EX-10 | Pasien menunggu perubahan di tab tracker | Booking → BOOKED tracker → resepsionis check-in di browser lain → bring-to-front dan tunggu fallback polling. | ETA-01-R2 PASS; startup fallback memerlukan budget45detik; tidak menguji socket aktif. |

Data yang dipakai seluruhnya dari DemoSeeder atau dibuat melalui UI dengan prefix QA. Tautan verification/reset/booking rahasia hanya berada pada runtime privat; tidak dicetak dalam laporan. Eksplorasi dipandu menu/label lebih dahulu; URL langsung dipakai terpisah untuk pengujian authorization, recovery, dan entry yang tidak dapat ditemukan.

## Catatan bias dan harness

Test terstruktur bukan observasi manusia sungguhan. Source dipakai untuk menemukan feature dan memeriksa persistence, tidak sebagai pengganti langkah browser. Kegagalan selector, sinkronisasi Livewire, fixture account_type tidak sah, SQL join yang salah, timing fallback, dan typo assertion tidak diklaim bug aplikasi. Histori tetap ada di SCENARIO_TEST_MATRIX. Fase dapat dilanjutkan dari evidence/scenarios.json dan checkpoint terbaru tanpa mengulangi PASS; runtime hanya boleh dibuka kembali pada database/storage disposable.
