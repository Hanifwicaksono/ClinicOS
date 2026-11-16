# UX dan accessibility audit

> **Status terbaru setelah P2, 11 November 2026:** QA-09 dan QA-10 FIXED: drawer/fokus/inert/Tab/Escape pada lima role melalui DOM Chrome, tujuh label form pada dua role pendaftaran. Pesan self-delete/verification menjelaskan langkah berikut tanpa error FK atau klaim delivery palsu. Native keyboard dan screen reader NOT TESTED. Lihat [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Riwayat laporan sebelum perbaikan P2

Bagian di bawah mempertahankan checkpoint P1 dan baseline audit; status/angka historis tidak menggantikan hasil terbaru di atas.

> **Pembaruan setelah perbaikan P1, 11 November 2026:** QA-01 - QA-04 sudah FIXED pada pengujian ulang; P2 belum diperbaiki. Hasil terbaru: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Dua bug staf baru QA-13/QA-14 dan risiko sesi setelah reset admin tetap terbuka.

## Pengalaman pengguna setelah perbaikan P1

Booking publik kini diuji sampai submit berhasil, termasuk layanan yang sebelumnya tetap kosong setelah runtime diperbaiki. Histori koreksi menyediakan tautan foto sebelum/sesudah melalui akses private. Hapus owner menampilkan penjelasan dan error form tanpa logout; mengganti dokter sesi terpakai memberi instruksi membuat sesi baru. Booking dan histori foto lulus pemeriksaan overflow 375/768/1024/1440 px.

QA-09 (Escape drawer) tetap FAIL pada browser; QA-10 tidak diperbaiki. Siklus staf biasa lulus browser, tetapi QA-14 masih menimbulkan error server ketika dokter dengan booking menghapus diri. Delivery inbox nyata tidak diklaim selesai. Lihat [hasil P1](P1_FIX_REPORT.md) dan [siklus staf](STAFF_LIFECYCLE_REPORT.md).

## Baseline audit sebelum perbaikan

Bagian berikut mempertahankan hasil/source yang diamati saat audit awal. Angka, status FAIL P1 dan batas pada baseline ini bukan status terbaru; bukti lama tidak ditimpa.

Audit browser 11 November 2026, Chrome headless. Lima role berhasil login, membuka 29 link menu, dan logout. Empat viewport 375/768/1024/1440 px diperiksa pada dashboard lima role; browser existing juga memeriksa RME, booking staf, dan tracker pasien. Ini bukan pemeriksaan semua halaman pada semua perangkat.

| Area/tugas manusia | Hasil | Bukti/implikasi |
| --- | --- | --- |
| Patient memilih dokter pada booking publik | FAIL | QA-01: HTTP 200, field tampak, option tidak dirender; tugas berhenti sebelum submit |
| Receptionist mendaftar pasien existing dan menyerahkan pemeriksaan ke Doctor | PASS | Browser berhasil booking → check-in → call → start → draft → final |
| Owner membuat klinik dan mengaktifkan Free | PASS pada fixture verified | Browser onboarding selesai; email delivery tetap BLOCKED |
| Owner membuka add-on sebelum membuat klinik | FAIL | QA-07: 500 tanpa panduan menyelesaikan prasyarat |
| Owner menghapus akun setelah onboarding | FAIL | QA-03: sukses auth/logout menyembunyikan tenant tanpa owner |
| Admin memindahkan sesi yang sudah mempunyai booking | FAIL | QA-04: UI menerima edit tanpa memberi mekanisme menjaga assignment booking lama |
| Doctor memperbaiki foto dalam record final | FAIL | QA-02: snapshot mengesankan histori tersedia, file lama hilang |
| Autosave, invalid vital, offline/retry | PASS untuk skenario existing | Feedback invalid dan status unsaved tampil; data tersimpan setelah reconnect |
| Incomplete prescription draft vs final | PASS | Draft boleh belum lengkap, finalisasi ditolak sebelum field wajib lengkap |
| Keyboard pilihan dokter staf | PASS untuk ArrowDown/pilih existing browser | Bukan audit ARIA combobox seluruh pembaca layar |
| Escape pada drawer mobile | FAIL | QA-09; [screenshot](evidence/mobile-navigation-escape.png) masih menunjukkan drawer terbuka setelah Escape |
| Label pasien baru pada form booking staf | FAIL | QA-10: 7 field tanpa label programatis; nama field tidak menggantikan accessible name |
| Menu role/URL langsung | PASS yang diuji | Semua menu terpilih dapat dibuka; dashboard role lain ditolak backend 403 |
| Data kosong/loading/error pada semua halaman | NOT TESTED secara penuh | Existing tests mencakup state tertentu; menu smoke tidak membuktikan semua state |
| Semua filter, fixed sort, page awal/akhir | NOT TESTED secara penuh | Beberapa list memiliki fixed sort dan pagination, bukan kontrol sort yang bisa diubah |
| Fokus drawer tertutup/terbuka, trap, return secara menyeluruh | NOT TESTED | Hanya Escape direproduksi; tidak mengklaim semua urutan fokus salah |
| Kontras terukur, zoom 200/400%, reduced motion, screen reader | NOT TESTED | Tidak ada pengukuran WCAG lengkap atau pengguna screen reader |
| Perangkat mobile fisik, keyboard virtual, orientasi | NOT TESTED | Emulasi viewport saja |
| Discovery dari dashboard pasien tanpa mengetahui URL klinik | FAIL terhadap cakupan discovery | Tidak tersedia directory/tautan booking baru dari daftar klinik; lihat MISSING_FEATURES |

## Perbaikan UX yang paling mendesak

1. Pulihkan pilihan dokter/layanan/sesi pada booking publik dan buktikan booking dapat diselesaikan dari tautan klinik sampai konfirmasi.
2. Jelaskan dan amankan perubahan sesi terpakai, penghapusan owner, serta lampiran historis sebelum menampilkan sukses.
3. Arahkan halaman add-on tanpa klinik ke onboarding. Hubungkan tujuh label form booking dengan kontrolnya.
4. Lengkapi keyboard drawer yang ada, termasuk Escape/fokus/expanded state. Tidak perlu mengganti shell UI.

## Cakupan per halaman

Seluruh 88 file view dipetakan pada lampiran view di SYSTEM_INVENTORY. Kolom browser menunjukkan jalur yang benar-benar dibuka, fixture/render test, atau NOT TESTED. Komponen bersama dan layout bukan halaman independen; pemakaian komponen tidak otomatis menguji semua state/prop-nya.

Build dan Blade compilation PASS membuktikan bundling/kompilasi, tetapi QA-01 tetap gagal. Status render dan status usability sengaja dipisahkan.
