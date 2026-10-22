# Sprint Plan — RME, Resep, dan Odontogram

Status: **Implementasi Tugas 1–3 selesai**  
Fokus: desain alur resep, komponen Blade RME General, odontogram interaktif, dan allergy alert.

## Sasaran sprint

Sprint ini menghasilkan UI klinis yang konsisten dan siap dihubungkan ke API Laravel tanpa membuat ulang aturan medis di frontend. Urutan kerja dimulai dari penyelarasan kontrak data dan aturan finalisasi, kemudian komponen presentasional, interaksi, integrasi, dan bukti.

## Batas scope

- Gunakan pola Blade, PHP, JavaScript, dan design system yang sudah ada.
- Jangan menambah dependency baru kecuali disetujui terpisah.
- Frontend tidak menentukan atau menyamarkan histori medis; backend tetap menjadi sumber kebenaran.
- Odontogram hanya mengelola state dan callback UI, tidak menyimpan langsung ke server.
- Gunakan data dummy pada demo, test, dan screenshot.

## Dependency yang harus dikunci lebih dahulu

| Pemilik/rujukan | Keputusan yang dibutuhkan | Memblokir |
| --- | --- | --- |
| Syifa | Aturan versi RME, perubahan setelah finalisasi, isi alasan koreksi, dan bentuk histori Modification | Tugas 1 dan confirmation finalize pada Tugas 2 |
| Vioni | Kontrak API/data RME General, autosave, resep, dan error response | Integrasi Tugas 1 dan 2 |
| Dhomi | Kontrak data gigi, kode kondisi, dan payload callback dental | Tugas 3 |
| Design System Sprint 3 | Komponen input, status, alert, warna, typography, focus state, dan breakpoint | Tugas 2 dan 3 |
| BA | Field wajib, aturan validasi, copy status, dan acceptance flow RME | Tugas 1 dan 2 |

## 1. Mendesain alur resep serta finalize/Modification

Status: **Selesai**  
Bukti: [`docs/prescription-workflow/README.md`](docs/prescription-workflow/README.md)

### Tujuan

Menyiapkan komponen pemilihan obat, dosis, preview resep, peringatan, finalisasi, serta alasan koreksi.

### Deliverable

- Prototype prescription.
- Confirmation finalize.
- Version history/Modification.

### Acceptance Criteria

- User memahami perbedaan status `draft`, `finalized`, dan `Modification`.
- Histori koreksi, versi sebelumnya, waktu perubahan, dan alasan koreksi tidak disamarkan.
- Finalisasi memiliki confirmation yang menjelaskan bahwa perubahan berikutnya menjadi Modification.
- Peringatan penting dapat dipahami tanpa mengandalkan warna saja.

### Dependency / Koordinasi

Aturan versi RME dari Syifa dan kontrak API backend dari Vioni.

### Rencana pengerjaan

1. Petakan state dan transisi yang diizinkan: draft → finalized → Modification.
2. Sepakati dengan Syifa data yang wajib tampil pada version history dan alasan Modification.
3. Definisikan kontrak data UI untuk obat, dosis, frekuensi, durasi, instruksi, warning, dan versi resep.
4. Buat prototype pemilihan obat dan dosis dengan state kosong, terisi, validation error, dan warning.
5. Buat preview resep yang memperlihatkan data persis sebelum finalisasi.
6. Buat confirmation finalize dengan konsekuensi yang jelas dan fokus keyboard yang benar.
7. Buat tampilan histori versi yang membedakan isi lama, koreksi, alasan, pelaku, dan waktu perubahan.
8. Review alur bersama Syifa dan Vioni sebelum kontrak dianggap siap diintegrasikan.

### Verifikasi

- Walkthrough draft, finalize, dan Modification menggunakan data dummy.
- Uji keyboard pada pemilihan obat, dialog konfirmasi, dan histori versi.
- Pastikan Modification tidak menimpa atau menyembunyikan versi sebelumnya.
- Ambil screenshot setiap state utama untuk bukti review.

## 2. Membangun reusable Laravel Blade form RME General dan status autosave

Status: **Selesai**  
Bukti: [`docs/rme-general-autosave/README.md`](docs/rme-general-autosave/README.md)

### Tujuan

Mengimplementasikan komponen input SOAP/RME General, field error, status `saving`/`saved`, dan confirmation finalize. Vioni menghubungkan data/API.

### Deliverable

- Laravel Blade input components dan form sections.
- Reusable validation presentation.
- Responsive styles.
- Mock state demos.

### Acceptance Criteria

- Form menampilkan state `draft`, `saving`, `saved`, `error`, dan `finalized` secara jelas.
- Semua input dan aksi dapat dinavigasi dengan keyboard.
- Struktur komponen dapat dihubungkan ke API tanpa perubahan desain besar.
- Error terhubung ke field yang benar dan dapat dibaca teknologi bantu.

### Dependency / Koordinasi

Design System Sprint 3, aturan BA, aturan finalize dari Syifa, dan kontrak API dari Vioni.

### Rencana pengerjaan

1. Audit komponen Blade/input yang sudah ada dan gunakan kembali pola proyek.
2. Kunci daftar field SOAP/RME General, field wajib, format nilai, dan copy error bersama BA.
3. Definisikan props minimum untuk input, section, validation message, autosave status, dan finalize confirmation.
4. Bangun komponen Blade presentasional tanpa coupling ke endpoint tertentu.
5. Susun section SOAP/RME General menggunakan komponen tersebut.
6. Tambahkan mock state demo untuk draft, saving, saved, error, dan finalized.
7. Sediakan hook/atribut yang stabil agar Vioni dapat menghubungkan autosave dan API tanpa mengubah markup utama.
8. Terapkan responsive layout serta focus, label, description, dan error association yang accessible.

### Verifikasi

- Feature/component test untuk render props, old input, error, dan setiap status form.
- Uji urutan Tab, focus indicator, label, error association, dan confirmation finalize.
- Uji visual pada mobile, tablet, dan desktop.
- Pastikan demo memakai data dummy dan tidak melakukan request produksi.

## 3. Membangun Laravel Blade odontogram interaktif dan allergy alert

Status: **Selesai**  
Bukti: [`docs/odontogram-allergy/README.md`](docs/odontogram-allergy/README.md)

### Tujuan

Menerjemahkan desain odontogram dan peringatan alergi menjadi komponen Blade/PHP yang menerima data dari controller Laravel. Interaksi pemilihan gigi memakai JavaScript seperlunya, sedangkan integrasi data dikoordinasikan dengan Vioni.

### Deliverable

- `DentalChart` component.
- State gigi terpilih.
- Legend status.
- Critical allergy banner.
- Responsive dan accessible UI.

### Acceptance Criteria

- Gigi dan kondisi terpilih tampak jelas.
- Callback komponen dapat dites.
- Alert kritis jelas, memiliki teks/ikon yang bermakna, dan dapat diakses keyboard.
- Komponen tidak menyimpan data langsung ke server.

### Dependency / Koordinasi

Input dokter, kontrak data dental dari Dhomi, kontrak integrasi dari Vioni, dan Design System Sprint 3.

### Rencana pengerjaan

1. Sepakati dengan Dhomi struktur data gigi, kode kondisi, legend, dan bentuk callback.
2. Audit aset serta komponen alert yang sudah ada agar tidak menduplikasi design system.
3. Buat `DentalChart` sebagai komponen presentasional yang menerima data dari controller.
4. Tambahkan JavaScript minimum untuk memilih gigi, memilih kondisi, memperbarui state lokal, dan memicu callback.
5. Tampilkan selected, unselected, disabled, dan kondisi gigi dengan label selain warna.
6. Tambahkan legend yang mengikuti kontrak kondisi dental.
7. Tambahkan critical allergy banner dengan isi dari data input, bukan aturan diagnosis di frontend.
8. Buat mock state untuk odontogram kosong, beberapa kondisi, gigi terpilih, read-only, dan allergy alert.

### Verifikasi

- Feature/component test untuk render data controller, selected state, legend, dan allergy banner.
- Test callback pada interaksi pemilihan tanpa request penyimpanan langsung.
- Uji keyboard untuk setiap gigi yang interaktif dan aksi pemilihan kondisi.
- Uji visual pada mobile, tablet, dan desktop tanpa horizontal overflow yang tidak terkontrol.

## Urutan pelaksanaan

| Tahap | Pekerjaan | Stop condition |
| --- | --- | --- |
| 0 | Kunci aturan Syifa/BA dan kontrak Vioni/Dhomi | Tidak ada field, transisi, atau payload kritis yang masih ambigu. |
| 1 | Prototype alur resep | Draft, finalize, dan Modification dapat direview end-to-end dengan histori terlihat. |
| 2 | Komponen Blade RME General | Seluruh mock state dan validation presentation dapat dirender serta dinavigasi keyboard. |
| 3 | Odontogram dan allergy alert | Selection, legend, callback, dan alert bekerja tanpa penyimpanan langsung. |
| 4 | Integrasi | Vioni dapat menghubungkan data/API tanpa perubahan markup besar. |
| 5 | Verifikasi dan dokumentasi | Test relevan lulus dan screenshot seluruh acceptance state tersedia. |

## Rencana testing akhir

Jalankan hanya test yang mencakup komponen dan flow yang berubah, kemudian formatter/build sesuai jenis file yang disentuh.

```powershell
php artisan test --compact <test-terkait>
vendor/bin/pint --dirty --format agent
npm run build
```

Checklist manual:

- Draft, saving, saved, error, finalized, dan Modification memiliki label yang berbeda.
- Histori Modification tetap memperlihatkan versi dan alasan sebelumnya.
- Semua kontrol utama dapat digunakan dengan keyboard dan memiliki focus indicator.
- Error form terhubung dengan field terkait.
- Odontogram tidak menyimpan data langsung ke server.
- Allergy alert tidak hanya mengandalkan warna.
- Tidak ada data pasien nyata pada mock, test, atau screenshot.

## Hasil Pengerjaan / Bukti / Dokumentasi

Isi bagian ini selama implementasi. Jangan menandai item selesai tanpa link atau hasil yang dapat diperiksa.

| Tugas | Hasil kerja | Hasil testing | Bukti / dokumentasi | Status |
| --- | --- | --- | --- | --- |
| 1. Resep, finalize, Modification | Editor obat, preview, warning, modal finalisasi, dan histori sebelum/sesudah | 11 test, 114 assertion; Vite build dan Pint lulus | [`docs/prescription-workflow/README.md`](docs/prescription-workflow/README.md) | Selesai |
| 2. Form RME General dan autosave | Komponen section/input/textarea/error, seluruh field SOAP dan tanda vital, status draft/saving/saved/error/finalized, serta hook API | 13 test, 142 assertion; Vite build dan Pint lulus | [`docs/rme-general-autosave/README.md`](docs/rme-general-autosave/README.md) | Selesai |
| 3. Odontogram dan allergy alert | Odontogram 32 gigi FDI, selection/kondisi/read-only/disabled, callback lokal, legend, dan critical allergy banner | 16 test, 172 assertion; Vite build dan Pint lulus | [`docs/odontogram-allergy/README.md`](docs/odontogram-allergy/README.md) | Selesai |

### Properti screenshot wajib

| Bukti | Viewport | State yang harus terlihat | Nama file yang disarankan |
| --- | --- | --- | --- |
| Prescription draft | 1440 × 900 | Pemilihan obat, dosis, warning, dan preview | `prescription-draft-desktop.png` |
| Finalize confirmation | 1440 × 900 | Konsekuensi finalisasi dan tombol fokus | `prescription-finalize-confirmation.png` |
| Modification history | 1440 × 900 | Versi lama, koreksi, alasan, pelaku, dan waktu | `prescription-modification-history.png` |
| RME autosave | 390 × 844 dan 1440 × 900 | Saving, saved, dan error | `rme-autosave-mobile.png`, `rme-autosave-desktop.png` |
| RME finalized | 1440 × 900 | State read-only/finalized dan aksi Modification | `rme-finalized-desktop.png` |
| Odontogram | 390 × 844 dan 1440 × 900 | Gigi terpilih, kondisi, dan legend | `odontogram-mobile.png`, `odontogram-desktop.png` |
| Allergy alert | 390 × 844 dan 1440 × 900 | Alert kritis, teks alergi, ikon, dan fokus | `allergy-alert-mobile.png`, `allergy-alert-desktop.png` |

Setiap bukti harus mencatat:

- commit/PR atau path implementasi;
- viewport dan browser;
- data dummy yang digunakan;
- state yang sedang ditampilkan;
- hasil test terkait;
- catatan accessibility atau keterbatasan yang masih terbuka.

## Definition of Done

- Tiga deliverable memenuhi acceptance criteria masing-masing.
- Dependency dan keputusan dari Syifa, Vioni, Dhomi, Design System Sprint 3, dan BA tercatat.
- Test relevan lulus.
- Build dan formatter lulus bila file terkait berubah.
- Screenshot dengan properti bukti lengkap tersedia.
- Tidak ada histori klinis yang disamarkan dan tidak ada data pasien nyata dalam dokumentasi.
