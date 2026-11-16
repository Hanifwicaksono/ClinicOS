# Product gap analysis — daftar kanonis

Tanggal audit: **14 November 2026**. Audit produk read-only; tidak ada implementasi/perbaikan. Fakta source/UI dipisahkan dari inferensi kebutuhan dan rekomendasi. Semua UI menggunakan data dummy pada SQLite terisolasi. 

**18 gap unik**, termasuk satu bug UI baru PG-18; bukan 18 bug baru. Setiap ID dipakai ulang pada UX/workflow/backlog agar satu kebutuhan tidak dihitung berkali-kali. Fitur yang tak ada tidak otomatis wajib MVP. Tidak ada P0 yang dapat dibuktikan dari bukti audit ini; P1 recovery/policy berpotensi penting tetapi tidak menyatakan bahaya klinis nyata tanpa bukti.


| Status | Makna dalam audit produk |
| --- | --- |
| COMPLETE | Kemampuan terbatas yang disebut tersedia end-to-end dalam source, dengan bukti UI/regression aktual atau historis terkait. Bukan klaim seluruh produk selesai atau seluruh kombinasi telah diuji ulang. |
| PARTIAL | Sebagian proses tersedia, langkah lanjutan/recovery/informasi belum lengkap. |
| MISSING | Kemampuan tidak ditemukan pada domain/schema/routes/UI yang diinventarisasi. Kewajiban implementasinya tetap bergantung scope. |
| NOT REQUIRED | Tidak diperlukan pada scope saat ini atau melanggar batas produk yang telah ditetapkan. |
| PRODUCT DECISION REQUIRED | Kebijakan/batas fitur belum disepakati; tidak aman menetapkan implementasi otomatis. |
| NOT VERIFIED | Bukti operasional/usability yang dibutuhkan belum tersedia. Tidak disamakan dengan fitur tidak ada. |

**FAKTA** = source atau observasi fixture/bukti run yang dirujuk. **INFERENSI KEBUTUHAN** = dugaan tugas/frekuensi/nilai yang masuk akal tetapi belum divalidasi manusia. **REKOMENDASI** = opsi desain, bukan keputusan atau izin implementasi.


| ID | Kebutuhan | Status | Prioritas | Jenis |
| --- | --- | --- | --- | --- |
| PG-01 | Perubahan booking terkonfirmasi sebelum pemeriksaan | PARTIAL | P1 | Workflow tidak lengkap |
| PG-02 | Ketersediaan berdasarkan tanggal dan dampak perubahan jam | PARTIAL | P1 | Workflow tidak lengkap |
| PG-03 | Penghentian dan serah terima pemeriksaan aktif | PRODUCT DECISION REQUIRED | P1 | Keputusan bisnis / recovery |
| PG-04 | Tindak lanjut klinis menjadi pekerjaan yang dapat diselesaikan | PARTIAL | P2 | Workflow tidak lengkap |
| PG-05 | Penyelesaian tagihan layanan pasien dan bukti bayar | MISSING | P1 | Fitur hilang bersyarat scope |
| PG-06 | Makna pasien nonaktif konsisten antar kanal | PRODUCT DECISION REQUIRED | P1 | Kebijakan tidak konsisten; calon bug setelah kontrak ditetapkan |
| PG-07 | Dokumen pasien yang disetujui: resep / ringkasan kunjungan | PARTIAL | P1 | Workflow tidak lengkap / scope decision |
| PG-08 | Laporan operasional yang dapat direkonsiliasi dan dibawa keluar | PARTIAL | P2 | Workflow tidak lengkap / UX informasi |
| PG-09 | Timeline histori komersial lintas periode | PARTIAL | P2 | Workflow tidak lengkap |
| PG-10 | Koreksi grant tertentu dan rekonsiliasi refund/akses | PRODUCT DECISION REQUIRED | P1 | Keputusan bisnis / recovery komersial |
| PG-11 | Lifecycle tenant dan kepemilikan yang dapat dikelola | PRODUCT DECISION REQUIRED | P2 | Keputusan bisnis / lifecycle |
| PG-12 | Informasi mendekati giliran dan reminder janji | PARTIAL | P2 | Workflow komunikasi tidak lengkap |
| PG-13 | Resolusi versi alergi dari dua editor | PRODUCT DECISION REQUIRED | P1 | Keputusan bisnis / integritas versi |
| PG-14 | Diagnosis dan pemulihan data legacy tidak konsisten | PRODUCT DECISION REQUIRED | P1 | Recovery bersyarat / keputusan data |
| PG-15 | Serah-terima akses staf dan delegasi admin | PARTIAL | P2 | UX onboarding / keputusan akses |
| PG-16 | Kontrak paket sesuai pembatasan yang benar-benar berlaku | PRODUCT DECISION REQUIRED | P2 | Keputusan packaging |
| PG-17 | Kandidat pasien lebih dari delapan mempunyai jalur lanjutan | PARTIAL | P2 | Peningkatan UX, bukan bug normalisasi CCL |
| PG-18 | Feedback submit pada form grant Platform Admin | PARTIAL | P2 | Bug implementasi UI |



## PG-01 — Perubahan booking terkonfirmasi sebelum pemeriksaan

**PARTIAL · P1 · Workflow tidak lengkap**

Scope: Melengkapi alur inti; aturan perubahan perlu disetujui. Gate keputusan: **D-01**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Perubahan booking terkonfirmasi sebelum pemeriksaan |
| 2. Role terkait | Receptionist, Patient; Owner/Clinic Admin mengatur kapasitas |
| 3. Masalah pengguna (inferensi) | Kesalahan dokter/layanan atau waktu tidak dapat diperbaiki sebagai satu perubahan booking. |
| 4. Workflow terdampak | BOOKED/WAITING → koreksi atau jadwal baru |
| 5. Implementasi tersedia (FAKTA) | Booking baru, pembatalan sebelum visit, no-show dan snapshot harga tersedia. Detail UI hanya menyediakan operasi status yang ada. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada command reschedule/reassign, relasi pengganti atau preview slot baru yang mempertahankan jejak booking lama. |
| 7. Dampak bila tidak dibuat (inferensi) | Staf harus membatalkan dan booking ulang; nomor antrean berubah, kontinuitas alasan/perubahan bergantung catatan manual. |
| 8. Workaround sekarang | Batalkan booking yang belum memiliki visit, buat booking baru, komunikasikan kode baru; jangan lakukan pada IN_PROGRESS. |
| 9. Opsi solusi (REKOMENDASI) | Pilih cancel-and-rebook yang dipandu dengan tautan supersedes, atau edit transaksional dengan snapshot revisi. Tampilkan konsekuensi kuota, harga, nomor dan notifikasi sebelum konfirmasi. |
| 10. Kompleksitas relatif | L |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Booking tanpa visit dapat dipindahkan sesuai kebijakan yang disetujui; validasi tenant/dokter/layanan/jam/kuota atomik; request berulang tidak menggandakan; booking lama tertaut dan diaudit; IN_PROGRESS/FINAL tidak berubah; pasien menerima status/kode yang jelas. |

Bukti: [app/Services/BookingService.php:35](../../app/Services/BookingService.php#L35); [app/Services/QueueWorkflowService.php:85](../../app/Services/QueueWorkflowService.php#L85); [routes/web.php:1](../../routes/web.php#L1); [UI-07](evidence/screenshots/UI-07.png).

Frekuensi perkiraan: **Sesekali sampai harian**. Dasar: Inferensi: koreksi administrasi dan permintaan perubahan; volume nyata belum diukur. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-02 — Ketersediaan berdasarkan tanggal dan dampak perubahan jam

**PARTIAL · P1 · Workflow tidak lengkap**

Scope: Operasional inti; model pengecualian perlu keputusan. Gate keputusan: **D-02**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Ketersediaan berdasarkan tanggal dan dampak perubahan jam |
| 2. Role terkait | Owner, Clinic Admin, Receptionist, Doctor |
| 3. Masalah pengguna (inferensi) | Hari libur/cuti dan perubahan sementara sulit dinyatakan tanpa mengubah jadwal mingguan. |
| 4. Workflow terdampak | Setup jadwal → gangguan jadwal → booking baru + penanganan existing |
| 5. Implementasi tersedia (FAKTA) | Jam buka/tutup dan timezone klinik, jadwal mingguan, aktivasi jadwal/klinik, kuota tersedia. CCL-05 menolak booking baru di luar jam terkini tanpa mengubah appointment lama. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada exception tanggal/rentang atau daftar appointment terdampak sebelum perubahan; setting bukan reschedule massal. |
| 7. Dampak bila tidak dibuat (inferensi) | Operator perlu meninjau booking lama sendiri dan menonaktifkan/mengembalikan jadwal manual; pasien lama bisa tetap memiliki appointment saat klinik tutup. |
| 8. Workaround sekarang | Nonaktifkan sesi/klinik bila sesuai, cari booking terdampak lalu hubungi pasien; catat kapan jadwal harus dipulihkan. |
| 9. Opsi solusi (REKOMENDASI) | Tambahkan kalender pengecualian minimum dan preview read-only booking terdampak; penyelesaian appointment existing melalui keputusan eksplisit terpisah, memanfaatkan PG-01. |
| 10. Kompleksitas relatif | M–L |
| 11. Dependensi | PG-01 |
| 12. Acceptance criteria yang dapat diuji | Exception tanggal memblokir booking baru hanya pada rentang/timezone terkait; hari normal tidak berubah; preview menunjukkan booking terdampak lintas status; menyimpan setting tidak menggeser/membatalkan appointment; restoration dan konflik jadwal dapat diverifikasi. |

Bukti: [app/Http/Requests/UpdateClinicRequest.php:39](../../app/Http/Requests/UpdateClinicRequest.php#L39); [app/Models/DoctorSchedule.php:6](../../app/Models/DoctorSchedule.php#L6); [app/Services/BookingService.php:35](../../app/Services/BookingService.php#L35); [UI-12](evidence/screenshots/UI-12.png); [UI-13](evidence/screenshots/UI-13.png).

Frekuensi perkiraan: **Mingguan/musiman, perubahan mendadak sesekali**. Dasar: Inferensi dari jadwal berulang vs hari libur/cuti; belum ada data frekuensi klinik. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-03 — Penghentian dan serah terima pemeriksaan aktif

**PRODUCT DECISION REQUIRED · P1 · Keputusan bisnis / recovery**

Scope: Recovery alur inti, perubahan data klinis harus diputuskan. Gate keputusan: **D-03**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Penghentian dan serah terima pemeriksaan aktif |
| 2. Role terkait | Doctor, Receptionist; Owner memantau nonmedis |
| 3. Masalah pengguna (inferensi) | Dokter tidak dapat melanjutkan atau pemeriksaan telanjur dimulai tanpa layanan selesai. |
| 4. Workflow terdampak | CALLED → IN_PROGRESS → interupsi / pergantian dokter |
| 5. Implementasi tersedia (FAKTA) | Visit hanya IN_PROGRESS/COMPLETED; start, draft, finalisasi dan koreksi final tersedia. Pembatalan queue/appointment setelah visit sengaja ditolak untuk integritas DFU-01. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada status terminal interrupted/aborted atau handover berotorisasi dan alasan; tidak ada pemulihan operasional dari IN_PROGRESS kecuali melanjutkan/finalisasi sah. |
| 7. Dampak bila tidak dibuat (inferensi) | Sesi dapat tetap terkunci sampai dokter yang ditugaskan kembali; menandai completed tanpa pelayanan bukan workaround yang dapat diterima. |
| 8. Workaround sekarang | Pertahankan draft dan hubungi dokter penanggung jawab; jika ada keadaan darurat, prosedur pelayanan luar sistem yang disetujui klinik. Jangan ubah status langsung di database. |
| 9. Opsi solusi (REKOMENDASI) | Putuskan interrupted terminal vs resume/handover. Tentukan tanggung jawab, akses minimal, persetujuan dan histori sebelum menambah transisi melalui VisitWorkflowService. |
| 10. Kompleksitas relatif | L |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Interupsi menyimpan aktor/alasan/waktu dan seluruh draft/foto; tidak memalsukan COMPLETED atau menghapus RME; antrean aktif dilepas sesuai keputusan; hanya dokter berwenang mengakses; finalisasi lama/replay ditangani; downgrade tidak menutup hak menyelesaikan pemeriksaan berjalan. |

Bukti: [app/VisitStatus.php:5](../../app/VisitStatus.php#L5); [app/Services/VisitWorkflowService.php:135](../../app/Services/VisitWorkflowService.php#L135); [app/Services/QueueWorkflowService.php:85](../../app/Services/QueueWorkflowService.php#L85); [UI-09](evidence/screenshots/UI-09.png).

Frekuensi perkiraan: **Jarang tetapi berdampak saat terjadi**. Dasar: Inferensi gangguan pelayanan; tidak ada insiden nyata atau bahaya klinis yang dibuktikan. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-04 — Tindak lanjut klinis menjadi pekerjaan yang dapat diselesaikan

**PARTIAL · P2 · Workflow tidak lengkap**

Scope: Pengembangan berikutnya; kebutuhan bentuk tugas harus divalidasi. Gate keputusan: **D-04**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Tindak lanjut klinis menjadi pekerjaan yang dapat diselesaikan |
| 2. Role terkait | Doctor, Patient, Receptionist |
| 3. Masalah pengguna (inferensi) | Rencana kontrol tersimpan sebagai teks tetapi belum menjadi booking/tugas tindak lanjut. |
| 4. Workflow terdampak | FINAL → instruksi pasien → kunjungan berikutnya / follow-up |
| 5. Implementasi tersedia (FAKTA) | SOAP plan, resep final di portal pasien, riwayat booking dan booking baru tersedia. Dokter dapat menulis rencana, pasien dapat booking manual. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada request follow-up terstruktur dengan tanggal/penanggung jawab/status atau handoff nonmedis ke resepsionis. |
| 7. Dampak bila tidak dibuat (inferensi) | Pasien/staf harus mengingat atau menyalin anjuran; sistem tidak menunjukkan follow-up yang belum selesai. |
| 8. Workaround sekarang | Dokter memberi instruksi langsung, pasien/staf membuat booking baru dan mencatat hubungan secara manual. |
| 9. Opsi solusi (REKOMENDASI) | Mulai dari instruksi kontrol minimum yang disetujui pasien dan CTA booking; bila dibutuhkan, tambahkan tugas tertutup. Jangan membuka isi SOAP kepada staf administrasi. |
| 10. Kompleksitas relatif | M |
| 11. Dependensi | PG-01 |
| 12. Acceptance criteria yang dapat diuji | Dokter membuat follow-up berotorisasi; pasien hanya melihat instruksi yang disetujui; resepsionis hanya metadata penjadwalan; booking pengganti dapat ditautkan; tugas selesai/batal diaudit; final RME tidak diubah diam-diam. |

Bukti: [resources/views/doctor/visits/show.blade.php:1](../../resources/views/doctor/visits/show.blade.php#L1); [app/Http/Controllers/Patient/DashboardController.php:17](../../app/Http/Controllers/Patient/DashboardController.php#L17); [app/Http/Controllers/Patient/AppointmentController.php:1](../../app/Http/Controllers/Patient/AppointmentController.php#L1); [UI-10](evidence/screenshots/UI-10.png); [UI-11](evidence/screenshots/UI-11.png).

Frekuensi perkiraan: **Per kunjungan yang memerlukan kontrol**. Dasar: Inferensi kebutuhan kontrol, bukan semua visit wajib follow-up. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-05 — Penyelesaian tagihan layanan pasien dan bukti bayar

**MISSING · P1 · Fitur hilang bersyarat scope**

Scope: Wajib hanya jika cakupan RPP operasional sampai kasir dipilih; di luar MVP/plan SaaS lama. Gate keputusan: **D-05**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Penyelesaian tagihan layanan pasien dan bukti bayar |
| 2. Role terkait | Receptionist/kasir yang perlu dipetakan, Owner, Patient |
| 3. Masalah pengguna (inferensi) | Pelayanan selesai tidak dapat dilanjutkan menjadi tagihan dan penerimaan kas di aplikasi. |
| 4. Workflow terdampak | COMPLETED → tagihan pasien → pembayaran → receipt / rekonsiliasi |
| 5. Implementasi tersedia (FAKTA) | Snapshot harga layanan dan Visit.total_amount tersedia. Payment, invoice dan refund yang ada adalah subscription SaaS, bukan pembayaran pasien. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada domain patient billing, pembayaran layanan, sisa tagihan, kuitansi atau rekonsiliasi kas. |
| 7. Dampak bila tidak dibuat (inferensi) | Untuk klinik yang memerlukan kasir terintegrasi, workflow berakhir sebelum administrasi keuangan selesai. Dashboard estimasi nilai layanan tidak dapat dipakai sebagai kas diterima. |
| 8. Workaround sekarang | Gunakan kasir/buku kas eksternal dan rekonsiliasi manual berdasarkan visit tanpa menganggap Payment SaaS sebagai kas pasien. |
| 9. Opsi solusi (REKOMENDASI) | Putuskan batas kasir tunai minimum, diskon/partial/refund/role; domain terpisah dari SaaS dengan snapshot tagihan dan ledger. Jangan menambah gateway pasien sebelum kebutuhan dipastikan. |
| 10. Kompleksitas relatif | XL |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Setelah kebijakan disetujui, visit menghasilkan tagihan unik terpisah SaaS; pembayaran idempotent menghasilkan receipt; outstanding/cash dapat direkonsiliasi; void/refund tidak menghapus histori; role dan tenant dibatasi; perubahan harga layanan tidak mengubah tagihan historis. |

Bukti: [app/Http/Controllers/Admin/DashboardController.php:81](../../app/Http/Controllers/Admin/DashboardController.php#L81); [routes/web.php:1](../../routes/web.php#L1); [Schema 44 tabel](evidence/schema.json); [RPP](evidence/rpp-reference.txt); [Development-Plan.md:95](../../Development-Plan.md#L95); [UI-17](evidence/screenshots/UI-17.png).

Frekuensi perkiraan: **Setiap kunjungan berbayar jika kasir masuk scope**. Dasar: Inferensi berdasarkan transaksi layanan; volume pembayaran pasien belum diukur. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-06 — Makna pasien nonaktif konsisten antar kanal

**PRODUCT DECISION REQUIRED · P1 · Kebijakan tidak konsisten; calon bug setelah kontrak ditetapkan**

Scope: Kebijakan inti identitas; bukan membuka kembali DFU/CCL yang sudah diverifikasi. Gate keputusan: **D-06**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Makna pasien nonaktif konsisten antar kanal |
| 2. Role terkait | Receptionist, Patient, Clinic Admin/Owner |
| 3. Masalah pengguna (inferensi) | Status nonaktif memblokir prefill booking staf, tetapi pasien sendiri masih dapat booking dengan profil nonaktif yang sama. |
| 4. Workflow terdampak | Deaktivasi patient → staff booking vs authenticated patient booking |
| 5. Implementasi tersedia (FAKTA) | CCL-04 memberi alasan/pemulihan pada staf. resolvePatient cabang patient_id memfilter is_active; cabang akun Patient mengambil profil terkait tanpa filter tersebut. UI-22 menghasilkan booking baru, profil tetap nonaktif dan jumlah profil tetap satu. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Belum ada kontrak apakah nonaktif berarti arsip administrasi, blok booking semua kanal atau hanya daftar staf; tidak ada penjelasan perbedaan kanal ini. |
| 7. Dampak bila tidak dibuat (inferensi) | Staf dan pasien menerima ekspektasi berbeda. Tidak terbukti tercipta duplikat baru atau data hilang, sehingga bukan klaim CCL-04 regresi. |
| 8. Workaround sekarang | Staf memeriksa profil dan mereaktivasi dengan izin bila sesuai; self-service perlu mengikuti keputusan produk, jangan menyarankan pasien memakai kanal ini untuk menghindari blok. |
| 9. Opsi solusi (REKOMENDASI) | Pilih blok semua kanal dengan langkah kontak/reaktivasi, atau arsip dengan aturan booking eksplisit. Terapkan satu aturan domain dan copy lintas kanal setelah keputusan. |
| 10. Kompleksitas relatif | S–M |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Test staff/patient/guest untuk makna nonaktif yang dipilih; profil tidak diganti/diaktifkan/link otomatis; alasan dan langkah pemulihan tersedia; tenant dan kepemilikan terjaga; histori tetap; jika kanal berbeda, perbedaannya eksplisit dan diuji. |

Bukti: [app/Services/BookingService.php:232](../../app/Services/BookingService.php#L232); [UI-22](evidence/screenshots/UI-22.png); [Hasil UI-22 termasuk jumlah profil](evidence/task-review.json).

Frekuensi perkiraan: **Sesekali, saat pasien lama kembali**. Dasar: Inferensi; proporsi pasien nonaktif kembali belum diketahui. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-07 — Dokumen pasien yang disetujui: resep / ringkasan kunjungan

**PARTIAL · P1 · Workflow tidak lengkap / scope decision**

Scope: Bersyarat kebutuhan dokumen saat pelayanan; PRD masih pertanyaan, Plan menaruh print post-MVP. Gate keputusan: **D-07**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Dokumen pasien yang disetujui: resep / ringkasan kunjungan |
| 2. Role terkait | Doctor, Patient; Receptionist hanya dokumen administratif yang diizinkan |
| 3. Masalah pengguna (inferensi) | Resep tersimpan dan terlihat tetapi belum menjadi dokumen yang dapat diberikan ke apotek/pasien melalui alur print/export. |
| 4. Workflow terdampak | FINAL → dokumen terbit → pasien / pihak tujuan |
| 5. Implementasi tersedia (FAKTA) | Resep terstruktur, preview dokter, resep final pasien, histori koreksi dan endpoint foto private tersedia. Ini tidak sama dengan PDF/resep tercetak. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada route/view print atau export dokumen klinis, nomor penerbitan/snapshot revisi dan batas isi dokumen yang disetujui. |
| 7. Dampak bila tidak dibuat (inferensi) | Klinik yang harus menyerahkan dokumen tetap menyalin ke media lain. Screenshot/browser-print mentah bukan bukti format dokumen sah/terverifikasi. |
| 8. Workaround sekarang | Dokter membuat dokumen di luar sistem sesuai prosedur klinik; pasien membaca portal yang diotorisasi. |
| 9. Opsi solusi (REKOMENDASI) | Putuskan dokumen minimum dan pihak yang boleh menerbitkan/mengambil; mulai printable Blade dengan snapshot final dan identitas/revisi yang sesuai. Jangan membuka full SOAP ke seluruh role. |
| 10. Kompleksitas relatif | M |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Hanya RME final yang diotorisasi dapat diterbitkan; pasien hanya dokumen miliknya yang disetujui; koreksi menghasilkan versi terlacak; data draft/tenant lain tidak muncul; print/PDF diuji dummy termasuk nama/resep panjang; dokumen bukan klaim tanda tangan/legalitas yang belum ditetapkan. |

Bukti: [app/Http/Controllers/Patient/DashboardController.php:17](../../app/Http/Controllers/Patient/DashboardController.php#L17); [resources/views/doctor/visits/show.blade.php:1](../../resources/views/doctor/visits/show.blade.php#L1); [routes/web.php:1](../../routes/web.php#L1); [Plan.md:337](../../Plan.md#L337); [UI-10](evidence/screenshots/UI-10.png).

Frekuensi perkiraan: **Per resep/dokumen yang perlu dibawa keluar sistem**. Dasar: Inferensi workflow apotek/pasien; pilihan format dan penerima belum divalidasi. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-08 — Laporan operasional yang dapat direkonsiliasi dan dibawa keluar

**PARTIAL · P2 · Workflow tidak lengkap / UX informasi**

Scope: Pengembangan berikutnya, bukan kewajiban export semua tabel. Gate keputusan: **D-08**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Laporan operasional yang dapat direkonsiliasi dan dibawa keluar |
| 2. Role terkait | Owner, Clinic Admin; Platform untuk komersial terpisah |
| 3. Masalah pengguna (inferensi) | Ringkasan dashboard dan audit bisa dibaca tetapi tidak menghasilkan laporan periode yang dapat dipakai untuk pembukuan/manajemen. |
| 4. Workflow terdampak | Filter periode → verifikasi angka → laporan operasional |
| 5. Implementasi tersedia (FAKTA) | Dashboard berfilter tanggal, nilai layanan berlabel bukan pembayaran diterima, audit log berfilter dan paginasi tersedia. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada export CSV/PDF laporan yang disetujui, definisi metrik terdokumentasi di UI laporan atau rekonsiliasi antar daftar detail. |
| 7. Dampak bila tidak dibuat (inferensi) | Pemilik menyalin angka manual dan tidak memiliki artefak periode. Bukan bukti angka dashboard salah. |
| 8. Workaround sekarang | Gunakan filter dan audit log, catat hasil manual; kas tetap di sistem terpisah. |
| 9. Opsi solusi (REKOMENDASI) | Pilih satu laporan operasional minimum dengan definisi status/tanggal/timezone; sediakan detail penjelas dan export terbatas tanpa isi medis. Keuangan kas menunggu PG-05. |
| 10. Kompleksitas relatif | M |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Total dan detail cocok untuk timezone/status/periode yang sama termasuk nol; export tenant-scoped dan tidak memuat SOAP/foto; batas tanggal valid; service value tidak dinamai cash; export kas hanya tersedia bila ledger pasien ada. |

Bukti: [app/Http/Controllers/Admin/DashboardController.php:81](../../app/Http/Controllers/Admin/DashboardController.php#L81); [app/Http/Controllers/Admin/AuditLogController.php:1](../../app/Http/Controllers/Admin/AuditLogController.php#L1); [routes/web.php:1](../../routes/web.php#L1); [Plan.md:337](../../Plan.md#L337); [UI-16](evidence/screenshots/UI-16.png).

Frekuensi perkiraan: **Mingguan/bulanan**. Dasar: Inferensi pelaporan periodik, belum ada wawancara pemilik. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-09 — Timeline histori komersial lintas periode

**PARTIAL · P2 · Workflow tidak lengkap**

Scope: Pengelolaan SaaS yang sudah masuk produk. Gate keputusan: **D-09**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Timeline histori komersial lintas periode |
| 2. Role terkait | Platform Admin, Owner dengan batas per klinik |
| 3. Masalah pengguna (inferensi) | Status efektif terkini tidak cukup menjelaskan rangkaian grant, pembayaran, jadwal dan revoke saat dukungan. |
| 4. Workflow terdampak | Investigasi invoice → periode lalu / aktif / terjadwal → sumber perubahan |
| 5. Implementasi tersedia (FAKTA) | SubscriptionPeriod/Payment/AddonPeriod dan audit tersedia; Platform menampilkan subscription kini, grant/Free dan pembayaran; owner mempunyai invoice serta riwayat add-on (UI-23). CCL-06 menyelaraskan status efektif. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Platform tidak memiliki timeline seluruh periode utama dan tautan sebab-akibat. Riwayat yang ada terpisah dan tidak boleh disebut seluruhnya tidak ada. |
| 7. Dampak bila tidak dibuat (inferensi) | Support perlu membaca beberapa halaman atau bantuan developer untuk menjelaskan histori kompleks sebelum koreksi aman. |
| 8. Workaround sekarang | Cari invoice/payment dan audit dengan ID; eskalasi read-only ke dukungan, jangan koreksi database manual. |
| 9. Opsi solusi (REKOMENDASI) | Viewer read-only per tenant: source, starts/ends, effective/scheduled/revoked, payment/grant actor/reason dan audit; owner hanya field komersial miliknya. |
| 10. Kompleksitas relatif | M |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Timeline menampilkan periode aktif/lalu/terjadwal/revoked dengan timezone dan batas eksklusif yang jelas; link invoice/source konsisten; tanpa Payment tidak disebut cash; tenant asing ditolak; tidak mengubah periode/histori ketika dibuka. |

Bukti: [app/Http/Controllers/Platform/TenantController.php:45](../../app/Http/Controllers/Platform/TenantController.php#L45); [resources/views/platform/tenants/show.blade.php:13](../../resources/views/platform/tenants/show.blade.php#L13); [app/Http/Controllers/SubscriptionAddonController.php:46](../../app/Http/Controllers/SubscriptionAddonController.php#L46); [UI-14](evidence/screenshots/UI-14.png); [UI-23](evidence/screenshots/UI-23.png).

Frekuensi perkiraan: **Sesekali pada support, setiap pemeriksaan histori**. Dasar: Inferensi kompleksitas multi-periode yang sudah ada; tiket aktual tidak tersedia. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-10 — Koreksi grant tertentu dan rekonsiliasi refund/akses

**PRODUCT DECISION REQUIRED · P1 · Keputusan bisnis / recovery komersial**

Scope: Recovery komersial inti; policy wajib sebelum mutasi. Gate keputusan: **D-10**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Koreksi grant tertentu dan rekonsiliasi refund/akses |
| 2. Role terkait | Platform Admin; Owner menerima dampak |
| 3. Masalah pengguna (inferensi) | Grant salah/duplikat lama tidak bisa dibatalkan secara spesifik; pencatatan refund tidak menjelaskan penyelesaian dana dan akses paket utama sebagai satu tugas. |
| 4. Workflow terdampak | Histori grant/payment → pilih koreksi → entitlement + dana + audit |
| 5. Implementasi tersedia (FAKTA) | DFU-12 mencegah grant ganda baru; grant dan downgrade global Free tersedia. recordManualRefund menandai Payment refunded dan mencabut AddonPeriod; tidak memanggil transfer refund provider atau revoke periode paket utama tertentu. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada single-grant revoke/undo, guided recovery duplicate historis, preview periode yang terpengaruh atau transaksi refund dana provider. Refund lokal adalah pencatatan, bukan pengembalian uang otomatis. |
| 7. Dampak bila tidak dibuat (inferensi) | Downgrade Free mengakhiri lebih banyak periode daripada satu grant; refund dan entitlement dapat dipahami keliru tanpa prosedur yang diputuskan. |
| 8. Workaround sekarang | Platform memeriksa histori, proses pengembalian dana melalui prosedur provider di luar aplikasi bila disetujui dan catat alasan; jangan gunakan Free sebagai undo satu periode. |
| 9. Opsi solusi (REKOMENDASI) | Pilih compensating adjustment: revoke satu grant yang keliru dengan jejak utuh, penyesuaian jadwal atau credit/refund terpisah. Tampilkan preview hak akses dan status dana; pertahankan periode sah dan visit berjalan. |
| 10. Kompleksitas relatif | L |
| 11. Dependensi | PG-09 |
| 12. Acceptance criteria yang dapat diuji | Fixture duplicate historis dapat dianalisis tanpa menghapus histori; hanya periode dipilih berubah sesuai keputusan; periode sah mendatang/invoice tetap; aktor/alasan/audit dan idempotency wajib; refund dana vs recorded terpisah; akses add-on/paket utama dan IN_PROGRESS teruji. |

Bukti: [app/Services/SubscriptionService.php:96](../../app/Services/SubscriptionService.php#L96); [app/Services/DokuPaymentService.php:333](../../app/Services/DokuPaymentService.php#L333); [resources/views/platform/tenants/show.blade.php:13](../../resources/views/platform/tenants/show.blade.php#L13); [UI-14](evidence/screenshots/UI-14.png); [UI-15](evidence/screenshots/UI-15.png).

Frekuensi perkiraan: **Jarang / ketika salah administrasi atau refund**. Dasar: Inferensi exception, bukan klaim duplikat aktual pada DB pengguna. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-11 — Lifecycle tenant dan kepemilikan yang dapat dikelola

**PRODUCT DECISION REQUIRED · P2 · Keputusan bisnis / lifecycle**

Scope: Governance SaaS; tidak mengharuskan hard delete. Gate keputusan: **D-11**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Lifecycle tenant dan kepemilikan yang dapat dikelola |
| 2. Role terkait | Owner, Platform Admin, Clinic Admin |
| 3. Masalah pengguna (inferensi) | Pemilik berhenti/berganti, klinik ditutup atau perlu suspension belum mempunyai prosedur UI terpandu. |
| 4. Workflow terdampak | Onboarding → operasi → suspend/transfer/closure → pemulihan |
| 5. Implementasi tersedia (FAKTA) | Owner eksplisit satu klinik, owner dapat mengubah is_active klinik; akun/staf dapat dinonaktifkan, state subscription suspended dikenali. Platform UI belum menyediakan transfer ownership/suspend/resume/closure. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada approval/identitas penerima owner, efek penutupan pada akses data atau administrasi lifecycle tenant terpadu. |
| 7. Dampak bila tidak dibuat (inferensi) | Perubahan owner memerlukan dukungan teknis; menutup booking tidak sama dengan mengakhiri kontrak, retensi atau menghapus data. |
| 8. Workaround sekarang | Owner menonaktifkan klinik untuk menghentikan booking baru; permintaan lifecycle ditangani dukungan dengan prosedur manual yang disetujui. |
| 9. Opsi solusi (REKOMENDASI) | Tetapkan state/transisi, siapa menyetujui, hak baca dan kelanjutan visit aktif; implementasikan transfer/suspend yang diperlukan saja. Jangan menambahkan impersonation atau penghapusan histori. |
| 10. Kompleksitas relatif | L |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Owner lama/baru diverifikasi sesuai keputusan; satu owner satu klinik tetap; suspend/resume memberi alasan dan dampak eksplisit; klinis/payment tidak dihapus; admin platform tetap tanpa isi medis; IN_PROGRESS boleh diselesaikan sesuai invariant; audit dan rollback administratif teruji. |

Bukti: [resources/views/platform/tenants/show.blade.php:13](../../resources/views/platform/tenants/show.blade.php#L13); [app/Http/Requests/UpdateClinicRequest.php:39](../../app/Http/Requests/UpdateClinicRequest.php#L39); [app/Services/SubscriptionService.php:96](../../app/Services/SubscriptionService.php#L96); [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1); [UI-12](evidence/screenshots/UI-12.png); [UI-14](evidence/screenshots/UI-14.png).

Frekuensi perkiraan: **Jarang sepanjang lifecycle**. Dasar: Inferensi SaaS berlangganan; belum ada permintaan pemindahan nyata. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-12 — Informasi mendekati giliran dan reminder janji

**PARTIAL · P2 · Workflow komunikasi tidak lengkap**

Scope: Near-turn disebut PRD penting; kanal/reminder appointment lanjutan perlu keputusan. Gate keputusan: **D-12**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Informasi mendekati giliran dan reminder janji |
| 2. Role terkait | Patient, Receptionist, Doctor |
| 3. Masalah pengguna (inferensi) | Pengguna mendapat perubahan status, tetapi belum diingatkan proaktif sebelum giliran/appointment. |
| 4. Workflow terdampak | BOOKED/WAITING → pengingat → kedatangan tepat waktu |
| 5. Implementasi tersedia (FAKTA) | Notifikasi booking/queue database dan queued broadcast, notification center serta ETA tersedia. Command terjadwal saat ini hanya reminder subscription. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ditemukan trigger near-turn atau scheduled appointment reminder. Email/WhatsApp untuk janji tidak otomatis tersedia hanya karena auth memakai mail. |
| 7. Dampak bila tidak dibuat (inferensi) | Pasien perlu memantau halaman atau dihubungi staf; bukan klaim no-show terbukti meningkat. |
| 8. Workaround sekarang | Pantau portal/display, staf menghubungi pasien melalui prosedur manual dengan persetujuan yang sesuai. |
| 9. Opsi solusi (REKOMENDASI) | Mulai near-turn kanal dalam aplikasi dengan dedup dan status aktual; putuskan threshold/lead time, consent dan kanal appointment reminder sebelum menambah scheduler/pengiriman eksternal. |
| 10. Kompleksitas relatif | M |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Threshold/lead time yang diputuskan memicu tepat satu reminder relevan; cancel/no-show/perubahan jadwal menghentikan pesan basi; ownership dicek saat kirim; tidak ada data medis pada broadcast; worker retry tidak menggandakan; near-turn dibedakan dari CALLED. |

Bukti: [app/Services/ClinicNotificationService.php:40](../../app/Services/ClinicNotificationService.php#L40); [routes/console.php:11](../../routes/console.php#L11); [PRD.md:440](../../PRD.md#L440); [UI-19](evidence/screenshots/UI-19.png).

Frekuensi perkiraan: **Setiap appointment yang memenuhi kondisi**. Dasar: Inferensi frekuensi event; notifikasi status existing tidak dihitung gap baru. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-13 — Resolusi versi alergi dari dua editor

**PRODUCT DECISION REQUIRED · P1 · Keputusan bisnis / integritas versi**

Scope: Konsistensi informasi klinis; model final perlu keputusan. Gate keputusan: **D-13**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Resolusi versi alergi dari dua editor |
| 2. Role terkait | Doctor yang berwenang |
| 3. Masalah pengguna (inferensi) | Dua editor dapat menghasilkan versi aktif yang berbeda atas alergi sama; pengguna butuh tahu mana berlaku tanpa membuang riwayat. |
| 4. Workflow terdampak | Allergy edit A/B → append-only versions → interpretasi klinis |
| 5. Implementasi tersedia (FAKTA) | Alergi persisten, severity, banner dan recorded_by_user_id tersedia. Perubahan substance/reaction/severity membuat record baru dan menonaktifkan record lama; previous_allergy_id ada pada metadata audit, bukan foreign key lineage pada PatientAllergy. RME memakai allergy_version guard. Bukti coverage lama menyimpan kedua perubahan; tidak ada klaim lost update. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada kontrak satu versi current per lineage, conflict resolution/merge atau cara eksplisit menandai cabang mana berlaku setelah sibling versions. |
| 7. Dampak bila tidak dibuat (inferensi) | Dokter berpotensi menafsirkan beberapa versi berbeda; bahaya klinis aktual tidak diuji/terbukti. Jangan sekadar last-write-wins atau menghapus versi. |
| 8. Workaround sekarang | Dokter meninjau semua versi aktif dan mengonfirmasi melalui prosedur klinik; eskalasi konflik, tidak merekayasa satu versi lewat DB. |
| 9. Opsi solusi (REKOMENDASI) | Putuskan multi-assertion sah vs satu versi current per lineage. Jika satu current, gunakan expected-parent/version dan conflict UI dengan supersede terotorisasi, tetap simpan keduanya. |
| 10. Kompleksitas relatif | M |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Dua tab dengan parent sama tidak diam-diam kehilangan salah satu input; UI menunjukkan konflik/semua assertion sesuai keputusan; resolusi menyimpan aktor/waktu/alasan dan lineage; critical warning konsisten; role nonmedis tidak mendapat akses; final RME tidak ditulis ulang. |

Bukti: [app/Services/VisitWorkflowService.php:392](../../app/Services/VisitWorkflowService.php#L392); [Bukti coverage dan keputusan historis](../deep-functional-ux/REMAINING_GAPS.md); [Matriks coverage alergi](../deep-functional-ux/COVERAGE_CLOSURE_MATRIX.md).

Frekuensi perkiraan: **Jarang pada concurrency, alergi dibaca setiap visit terkait**. Dasar: Inferensi dua tab/editor; bukti historis bukan ukuran prevalensi klinik. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-14 — Diagnosis dan pemulihan data legacy tidak konsisten

**PRODUCT DECISION REQUIRED · P1 · Recovery bersyarat / keputusan data**

Scope: Conditional: hanya jika data legacy terkait ditemukan; keberadaan pada data pengguna NOT VERIFIED. Gate keputusan: **D-14**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Diagnosis dan pemulihan data legacy tidak konsisten |
| 2. Role terkait | Platform Admin/support; Owner; Doctor untuk perubahan klinis |
| 3. Masalah pengguna (inferensi) | Pencegahan bug baru tidak menyelesaikan record lama yang mungkin telanjur tidak konsisten. |
| 4. Workflow terdampak | Deteksi mismatch → tinjauan → rencana perbaikan → audit |
| 5. Implementasi tersedia (FAKTA) | Transaksi, locks, constraints dan fix DFU/CCL mencegah kasus baru. LegacyTenantBackfillService menangani owner/subscription legacy secara terbatas, bukan repair appointment/queue/visit/grant generik. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada dry-run/report ketidaksesuaian historis dan prosedur koreksi idempotent dengan persetujuan domain yang relevan. |
| 7. Dampak bila tidak dibuat (inferensi) | Jika ada data lama, staf tetap menemui exception. Audit ini tidak membuka/menghitung data pengguna sehingga tidak menyatakan korupsi aktual. |
| 8. Workaround sekarang | Laporkan ID internal yang diperlukan melalui kanal dukungan; investigasi read-only berizin dan keputusan tertulis sebelum mutasi. |
| 9. Opsi solusi (REKOMENDASI) | Mulai detektor read-only untuk invariant spesifik bekas DFU/CCL, lalu desain repair per kategori; tidak membuat tombol perbaiki-semua atau seed ulang. |
| 10. Kompleksitas relatif | M untuk diagnosis; L untuk repair |
| 11. Dependensi | PG-09, PG-10 |
| 12. Acceptance criteria yang dapat diuji | Fixture legacy mismatch terdeteksi tanpa mutasi; record sehat tidak ditandai; output tanpa isi medis/token; rencana menunjukkan sebelum/sesudah dan persetujuan; repair per kategori transaksional/idempotent/audited dan mempertahankan histori; dry-run pengguna hanya setelah izin terpisah. |

Bukti: [app/Services/LegacyTenantBackfillService.php:21](../../app/Services/LegacyTenantBackfillService.php#L21); [app/Services/BookingService.php:35](../../app/Services/BookingService.php#L35); [app/Services/QueueWorkflowService.php:85](../../app/Services/QueueWorkflowService.php#L85); [app/Services/SubscriptionService.php:96](../../app/Services/SubscriptionService.php#L96); [Fix DFU](../deep-functional-ux/FIX_REGRESSION_REPORT.md); [Fix CCL](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md).

Frekuensi perkiraan: **Sekali saat ditemukan/migrasi, lalu insidental**. Dasar: Inferensi risiko legacy; DB pengguna sengaja tidak diperiksa. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-15 — Serah-terima akses staf dan delegasi admin

**PARTIAL · P2 · UX onboarding / keputusan akses**

Scope: Invitation adalah peningkatan; provisioning admin tambahan perlu keputusan. Gate keputusan: **D-15**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Serah-terima akses staf dan delegasi admin |
| 2. Role terkait | Owner, Clinic Admin, Doctor, Receptionist |
| 3. Masalah pengguna (inferensi) | Pembuat akun memasukkan password awal dan belum punya invitation acceptance; admin operasional non-owner ada tetapi tidak dapat diprovision lewat form staf biasa. |
| 4. Workflow terdampak | Owner create staff → verifikasi → aktivasi tugas / delegasi admin |
| 5. Implementasi tersedia (FAKTA) | Form membuat Doctor/Receptionist, email verification dan reset password tersedia; Registered dikirim setelah commit. Owner memakai role Clinic Admin. Role non-owner Clinic Admin ada pada fixture/source permission. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada undangan set-password yang terikat penerima/expired atau jalur UI delegasi/revoke Clinic Admin yang disepakati. |
| 7. Dampak bila tidak dibuat (inferensi) | Distribusi password awal dilakukan manual; delegasi berprivilege tinggi membutuhkan dukungan. Bukan berarti staf tidak dapat login/reset/verifikasi. |
| 8. Workaround sekarang | Gunakan mekanisme reset password dan verifikasi existing; owner mengerjakan tugas admin. Hindari berbagi akun owner. |
| 9. Opsi solusi (REKOMENDASI) | Undangan set-password dapat menggantikan distribusi password awal. Pisahkan keputusan perlu tidaknya delegated admin; jangan menambah custom permission editor tanpa kebutuhan. |
| 10. Kompleksitas relatif | M |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Undangan bila dipilih single-use/expired dan tidak mencetak password; seat/tenant/role tetap diverifikasi; email change/revoke membatalkan link sesuai kontrak; reset existing tidak regresi; hanya owner terotorisasi mendelegasi jika disetujui; tanpa akses medis tambahan. |

Bukti: [app/Http/Controllers/Admin/StaffController.php:31](../../app/Http/Controllers/Admin/StaffController.php#L31); [app/Services/StaffAccountService.php:1](../../app/Services/StaffAccountService.php#L1); [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1); [UI-02](evidence/screenshots/UI-02.png); [UI-20](evidence/screenshots/UI-20.png); [SMTP/verifikasi historis](../production-readiness/INTEGRATION_TEST_REPORT.md).

Frekuensi perkiraan: **Setiap rekrutmen/pergantian staf; admin tambahan jarang**. Dasar: Inferensi lifecycle staf, jumlah hiring belum diketahui. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-16 — Kontrak paket sesuai pembatasan yang benar-benar berlaku

**PRODUCT DECISION REQUIRED · P2 · Keputusan packaging**

Scope: Kejelasan penawaran komersial yang sudah dipakai. Gate keputusan: **D-16**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Kontrak paket sesuai pembatasan yang benar-benar berlaku |
| 2. Role terkait | Owner, Platform Admin; seluruh role terdampak pembatasan |
| 3. Masalah pengguna (inferensi) | Katalog menyebut feature flags realtime/management_dashboard, sedangkan jalur akses tidak terlihat memakai flags tersebut. |
| 4. Workflow terdampak | Pilih paket → pahami hak → akses fitur |
| 5. Implementasi tersedia (FAKTA) | Seats, kuota visit, expiry/read-only dan odontogram add-on memiliki guard nyata. SubscriptionPlanSeeder mencantumkan realtime/management_dashboard pada paket berbayar; pencarian app/routes tidak menemukan pemeriksaan kedua key tersebut. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Belum ada keputusan apakah flags adalah hak eksklusif yang harus digate atau metadata penawaran yang perlu diperbarui. |
| 7. Dampak bila tidak dibuat (inferensi) | Ekspektasi owner bisa berbeda dari pembatasan aktual. Mematikan fitur Free secara spontan berisiko mengganggu alur yang sudah dipakai. |
| 8. Workaround sekarang | Dukungan menjelaskan batas yang benar-benar enforced; jangan menjanjikan eksklusivitas tanpa kontrak yang diputuskan. |
| 9. Opsi solusi (REKOMENDASI) | Pilih koreksi copy/katalog atau enforcement transisi yang dikomunikasikan. Pertahankan hak baca/IN_PROGRESS dan data yang tersimpan, jangan mengasumsikan semua metadata harus menjadi paywall. |
| 10. Kompleksitas relatif | S untuk copy; M untuk enforcement |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Matriks plan/trial/expired/suspended menyatakan hak yang sama pada katalog/UI/server; kontrak Free ditetapkan eksplisit; odontogram existing tidak berubah; downgrade tidak menghapus data atau memblokir finish IN_PROGRESS; test akses role/tenant untuk aturan yang disetujui. |

Bukti: [database/seeders/SubscriptionPlanSeeder.php:29](../../database/seeders/SubscriptionPlanSeeder.php#L29); [app/Services/SubscriptionService.php:96](../../app/Services/SubscriptionService.php#L96); [routes/web.php:1](../../routes/web.php#L1); [Inventaris pencarian first-party](evidence/source-scan.json).

Frekuensi perkiraan: **Setiap pemilihan paket dan akses fitur terkait**. Dasar: Inferensi kontrak komersial, bukan bukti komplain pelanggan. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-17 — Kandidat pasien lebih dari delapan mempunyai jalur lanjutan

**PARTIAL · P2 · Peningkatan UX, bukan bug normalisasi CCL**

Scope: Quick win UX pada kebutuhan deduplikasi yang sudah ada. Gate keputusan: **Tidak perlu kebijakan identitas baru untuk copy + tautan**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Kandidat pasien lebih dari delapan mempunyai jalur lanjutan |
| 2. Role terkait | Receptionist |
| 3. Masalah pengguna (inferensi) | Pencarian kandidat menampilkan delapan hasil tanpa menjelaskan apakah masih ada hasil lain. |
| 4. Workflow terdampak | Create patient → duplicate review → pilih profil existing |
| 5. Implementasi tersedia (FAKTA) | Matching tenant-scoped NIK/nama+lahir/telepon, review token dan konfirmasi tersedia; nomor keluarga tidak dianggap identitas unik. UI-18: 10 profil dummy sesuai nomor, hanya 8 tautan kandidat. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Tidak ada penanda batas/lebih banyak, pagination atau CTA hasil lengkap pada panel tersebut. |
| 7. Dampak bila tidak dibuat (inferensi) | Petugas dapat menganggap daftar lengkap dan membuat profil baru padahal pasien yang dicari di luar delapan; kejadian pada pasien nyata tidak diklaim. |
| 8. Workaround sekarang | Batalkan create dan gunakan pencarian daftar pasien dengan informasi tambahan; tidak melakukan merge/account-link otomatis. |
| 9. Opsi solusi (REKOMENDASI) | Tampilkan bahwa hasil dibatasi dan tautkan pencarian pasien dengan kriteria aman; atau paginasi kandidat setelah kebutuhan dipastikan. Pertahankan review token dan batas anti-bocor. |
| 10. Kompleksitas relatif | S |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Dengan 10 kandidat fixture, panel menjelaskan batas dan menyediakan jalur menemukan kandidat ke-9/10 lewat UI; pilihan profil tetap tenant-authorized; input form tidak hilang tanpa penjelasan; nomor bersama tidak memicu merge; review token/replay/telepon legacy tetap berlaku. |

Bukti: [app/Services/PatientDuplicateService.php:42](../../app/Services/PatientDuplicateService.php#L42); [UI-18](evidence/screenshots/UI-18.png); [Perbandingan 8 terlihat / 10 fixture](evidence/task-review.json).

Frekuensi perkiraan: **Sesekali pada nomor keluarga/nomor bersama**. Dasar: Inferensi dari fixture terkontrol; bukan ukuran banyaknya duplikasi produksi. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.

## PG-18 — Feedback submit pada form grant Platform Admin

**PARTIAL · P2 · Bug implementasi UI**

Status perbaikan bug: **OPEN — tidak diperbaiki pada audit ini**.

Scope: Bug UI terverifikasi; idempotency backend DFU-12 tetap dipertahankan. Gate keputusan: **Tidak perlu aturan komersial baru; perbaikan source tidak diizinkan pada audit ini**.

| Dimensi | Analisis |
| --- | --- |
| 1. Nama kebutuhan | Feedback submit pada form grant Platform Admin |
| 2. Role terkait | Platform Admin |
| 3. Masalah pengguna (inferensi) | Tombol Berikan akses tetap enabled ketika form sudah submitting; console memuat Alpine SyntaxError. |
| 4. Workflow terdampak | Detail tenant → pilih paket/alasan → submit grant |
| 5. Implementasi tersedia (FAKTA) | Form memiliki x-data submitting, handler submit=true, request UUID dan backend dedup. Tetapi atribut komponen x-clinic-button memakai x-bind:disabled="submitting // @js($subscriptionPrices->isEmpty())"; hasil DOM masih memuat @js literal. |
| 6. Kemampuan belum tersedia / belum diputuskan (FAKTA) | Binding disable tombol tidak berhasil diinisialisasi. UI-28 merekam submitting=true dan button_disabled=false pada submit; request sengaja dicegat/abort sebelum backend untuk membatasi audit pada UI. |
| 7. Dampak bila tidak dibuat (inferensi) | Feedback pending gagal dan klik berulang masih dimungkinkan oleh UI. Tidak ada bukti grant backend ganda; ini tidak membuka kembali DFU-12. Prioritas P2 sesuai dampak UX, bukan P0 finansial. |
| 8. Workaround sekarang | Kirim sekali dan tunggu hasil; jika ragu periksa histori/invoice sebelum mencoba lagi. UUID backend existing menjadi pengaman, bukan alasan mengabaikan feedback. |
| 9. Opsi solusi (REKOMENDASI) | Saat tahap implementasi diizinkan, serialisasikan boolean di konteks Blade yang benar atau gunakan expression binding yang terkompilasi. Pertahankan disabled server saat katalog kosong dan request UUID/idempotency backend. |
| 10. Kompleksitas relatif | S |
| 11. Dependensi | Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing. |
| 12. Acceptance criteria yang dapat diuji | Rendered DOM tidak memuat directive @js mentah; tidak ada Alpine SyntaxError; valid submit menonaktifkan tombol selama pending; katalog kosong tetap disabled; retry/validation tidak dead-end; replay request backend tetap satu grant. Browser test memeriksa DOM state/error, bukan hanya jumlah periode. |

Bukti: [resources/views/platform/tenants/show.blade.php:24](../../resources/views/platform/tenants/show.blade.php#L24); [resources/views/components/clinic-button.blade.php:20](../../resources/views/components/clinic-button.blade.php#L20); [UI-28](evidence/screenshots/UI-28.png); [Console, DOM dan submit observer](evidence/page-error-triage.json).

Frekuensi perkiraan: **Setiap Platform membuka form dan memberikan grant**. Dasar: Bukti fixture dua kali reload + submit observer; frekuensi grant nyata tidak diukur. Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.


## Bukan gap produk baru

- DFU-01–12 dan CCL-01–08 tetap **FIXED & VERIFIED** menurut laporan fix terbaru dan source yang dipertahankan. Audit ini tidak mengulang semua PASS, tidak memperbaiki source dan tidak menyebut bug lama OPEN lagi.
- PR-01: revokasi socket aktif **OPEN** historis, risiko keamanan terpisah; bukan “tambahkan custom permission editor”. PR-02/03 adalah portability assertion/factory MySQL historis, bukan fitur klinik yang hilang.
- Kiosk, custom permission, discovery, branches/Edge dan domain obat/inventori perlu keputusan manfaat/scope; tidak diberi severity tinggi hanya karena belum ada.
- Hard delete histori, impersonation platform, native app dan React migration **NOT REQUIRED** pada scope saat ini.
- OAuth, settlement/callback/refund provider, studi manusia dan empty Platform dashboard bukan otomatis MISSING: bukti operasionalnya **NOT VERIFIED**.
