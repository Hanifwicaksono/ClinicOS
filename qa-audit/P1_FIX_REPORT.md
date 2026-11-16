# Perbaikan QA-01–QA-04

> **Checkpoint P1 historis.** QA-01 sampai QA-04 tetap PASS pada pengujian akhir P2. P2 kemudian diselesaikan; status terbaru ada pada [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Pernyataan bahwa P2 belum dimulai di bawah merujuk checkpoint P1.

Pengujian ulang 11 November 2026, Asia/Bangkok. Acuan audit awal: `b82237d932478df9ed02240cb698acff9b4ec669`. Keempat P1 **FIXED pada skenario yang diuji**, setelah reproduksi pada source sebelum perbaikan, perubahan terbatas, regression PHP, dan Chrome E2E. Perbaikan P2 belum dimulai.

## Hasil per masalah

| ID | Akar masalah yang diverifikasi | Perubahan aktual | Bukti hasil |
| --- | --- | --- | --- |
| QA-01 | Layout publik tidak memuat runtime Alpine/Livewire. Setelah runtime diperbaiki, ditemukan masalah kedua: eager load layanan tidak mengambil `is_active`, sedangkan form menyaring atribut itu; semua pilihan layanan hilang. | Layout memuat runtime Livewire yang sudah terpasang; query layanan mengikuti pola query aktif pada booking resepsionis. Tidak menambahkan instance Alpine atau dependency. | BookingTest 11/11; Chrome memilih dokter, layanan dan sesi, submit, memeriksa kepemilikan pasien dan tepat satu queue. |
| QA-02 | `syncRecord()` menghapus file setelah commit tanpa membedakan draft dan final. Revision hanya menyimpan path; endpoint hanya membaca array foto terkini. | Foto yang dikeluarkan dari record final dipertahankan. Endpoint foto yang sama menerima ID revision dan pilihan snapshot tervalidasi, mencari revision melalui record yang sudah diotorisasi. UI histori menyediakan tautan sebelum/sesudah. Respons private/no-store. | Reproduksi audit awal gagal karena file hilang; sekarang PASS. Regression membaca kedua snapshot, menolak revision asing, indeks/path tidak ada, snapshot invalid dan semua role selain dokter yang ditugaskan. Chrome upload manual → finalize → hapus lewat koreksi → baca foto historis 200; endpoint terkini 404. |
| QA-03 | Penghapusan profil langsung menghapus user; FK owner memakai null-on-delete. Tidak ada penolakan berdasarkan kepemilikan aktual. | Kunci user dalam transaksi; periksa relasi `ownedClinic` sebelum logout dan delete. Tolak dengan validation error dan penjelasan. Akun lama dengan `account_type = null` juga terlindungi. | ProfileTest dan reproduksi audit PASS; owner tetap login, user dan owner_user_id tetap ada. Browser juga memeriksa subscription dan akses halaman pembayaran tetap tersedia. Kandidat owner tanpa klinik dan akun biasa tetap dapat menghapus diri. |
| QA-04 | Update schedule mengganti dokter tanpa memeriksa appointment/queue yang sudah merujuk schedule. | Transaksi mengunci clinic lalu schedule, sesuai urutan BookingService; authorization diperiksa lagi. Penggantian dokter ditolak bila ada appointment atau queue, termasuk histori cancelled/completed. Perubahan kuota dengan dokter yang sama dan reassignment sesi kosong tetap bekerja. | DoctorScheduleManagementTest dan reproduksi audit PASS, termasuk visit aktif; Chrome mencoba dokter pengganti yang belum memiliki jadwal, mendapat error, lalu memeriksa relasi appointment/queue tetap sama. Update kuota berhasil. |

Sumber: [layout publik](../resources/views/components/public-layout.blade.php), [PublicBookingController](../app/Http/Controllers/PublicBookingController.php), [VisitWorkflowService](../app/Services/VisitWorkflowService.php), [VisitController](../app/Http/Controllers/Doctor/VisitController.php), [histori RME](../resources/views/doctor/visits/show.blade.php), [penghapusan profil](../resources/views/livewire/profile/delete-user-form.blade.php), [DoctorScheduleController](../app/Http/Controllers/Admin/DoctorScheduleController.php).

## Regression dan dampak antarfitur

- Suite aplikasi `tests/`: **315 PASS, 0 FAIL, 2.149 assertion**. Termasuk sembilan kasus tambahan pada suite default; test lama tetap dipertahankan.
- Suite audit eksplisit: **22 kasus, 15 PASS, 7 FAIL, 130 assertion**. Tiga reproduksi backend P1 sekarang PASS. Lima kegagalan lama QA-05/06/07/08 dan dua temuan staf baru tetap terlihat.
- Total PHP unik dari dua run: **337 kasus, 330 PASS, 7 FAIL, 2.279 assertion**. Ini penjumlahan run terpisah, bukan klaim menjalankan recorder route gabungan kembali.
- JavaScript unit/state: **7/7 PASS**. Browser workflow klinis existing: PASS. Browser P1 + siklus staf baru: PASS. Browser audit lima role tetap FAIL hanya pada QA-09 (Escape); pilihan dokter publik sudah PASS, 55/56 langkah audit PASS.
- Lima role tetap menjalankan login/menu/logout dan matriks 65 kombinasi endpoint. Regression baru juga menguji penolakan akses foto historis untuk owner/Clinic Admin, Receptionist, Patient, Platform Admin, dokter lain, dan dokter tenant lain.
- Form booking dan histori foto diperiksa pada 375, 768, 1024 dan 1440 px. Halaman klinik subdomain dan display antrean memakai layout publik yang sama dan lulus pemeriksaan browser; nama pasien aktif tidak tampil pada layar antrean.
- Penghapusan foto draft tetap membersihkan file. Finalisasi, SOAP/resep, allergy acknowledgement, autosave, stale conflict dan offline/reconnect existing tetap lulus. Snapshot komersial, subscription read-only dan pengecualian pelayanan aktif tetap lulus suite existing.
- Build Vite, Pint dirty/explicit audit tests, dan `git diff --check` PASS. Tidak ada migrasi, pergantian stack, atau perubahan dependency proyek.

## Bukti run terbaru

| Bukti | Isi |
| --- | --- |
| [p1-existing-php.txt](evidence/p1-existing-php.txt), [JUnit](evidence/p1-existing-php.xml) | Suite aplikasi 315/315 |
| [p1-audit-php.txt](evidence/p1-audit-php.txt), [JUnit](evidence/p1-audit-php.xml) | 22 regression audit; kegagalan P2 dipertahankan |
| [p1-browser-run.txt](evidence/p1-browser-run.txt), [langkah P1/staf](evidence/p1-retest/p1-browser-steps.json) | Chrome nyata, fixture DB dan storage sementara |
| [p1-existing-browsers.txt](evidence/p1-existing-browsers.txt), [langkah lima role](evidence/p1-retest/browser-audit-steps.json) | Workflow existing PASS; QA-09 tetap FAIL |
| [p1-js.txt](evidence/p1-js.txt), [p1-build.txt](evidence/p1-build.txt) | Node dan build |
| `p1-photo-before.txt`, `p1-owner-before.txt`, `p1-schedule-before.txt` di evidence | Reproduksi backend sebelum perbaikan |

Bukti audit awal pada `evidence/browser-audit-steps.json`, `interactive-browser.json`, `audit-regression.*` dan `phpunit-combined.*` tidak ditimpa. Hasil pengujian ulang berada pada nama/path baru.

## Skill Volt

Perubahan QA-03 sempat ditulis dan diuji sebelum jawaban penundaan Volt masuk. Pekerjaan Volt kemudian ditahan sampai prasyarat skill dipenuhi. Sumber resmi [volt-development Laravel Boost](https://github.com/laravel/boost/blob/main/.ai/volt/skill/volt-development/SKILL.blade.php) tersedia; templat dirender menjadi `C:/Users/User/.codex/skills/volt-development/SKILL.md` dan dibaca sebelum verifikasi akhir. Penyesuaian hanya mengubah pembungkus Blade dokumentasi menjadi Markdown dan memperluas contoh perintah Artisan. Tidak mengubah dependency proyek.

Versi terpasang dikonfirmasi Livewire 3.8.10 dan Volt 1.11.2. Komponen tetap class-based sesuai repository. Tool `search-docs` tidak tersedia pada sesi ini; dokumentasi [Volt Livewire 3.x](https://livewire.laravel.com/docs/3.x/volt) dan source paket terpasang dipakai sebagai sumber resmi. Skill testing belum tersedia; pengujian mengikuti persetujuan pengguna memakai pola repository.

## Batas hasil dan tahap berikut

SQLite tidak membuktikan concurrency/lock MySQL dua proses. Pengiriman SMTP, Reverb/socket aktif, DOKU nyata, scheduler produksi, dan perangkat mobile fisik belum diuji. Foto yang sudah terhapus sebelum perbaikan tidak dipulihkan oleh perubahan ini; pemulihan memerlukan backup yang masih tersedia. Perubahan hari/jam pada schedule terpakai tidak diberi kontrak pemindahan booking baru.

Pemeriksaan staf menemukan dua bug P2 tambahan serta risiko sesi setelah reset password; lihat [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md). Tahap ini berhenti pada checkpoint empat P1. QA-05–QA-14 dan keputusan kebijakan sesi belum diperbaiki.
