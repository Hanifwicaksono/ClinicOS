# Pemeriksaan Pasien — Audit, Implementasi, dan QA

## Refinement final — 23 November 2026

Bagian ini melaporkan penyempurnaan setelah implementasi 22 November di bawah. Tidak ada commit/push, dependency, migration, perubahan role, maupun perubahan aturan D-01–D-20. Database aplikasi tidak dipakai untuk verifikasi refinement ini; PHP memakai SQLite in-memory, browser memakai SQLite/storage sementara dan cache konfigurasi terpisah.

### Temuan dan perubahan

| Temuan aktual | Perubahan dan alasan |
| --- | --- |
| Kontainer berhenti pada 1.440 px dan padding desktop 40 px | Halaman pemeriksaan memilih kontainer maksimum 1.680 px, padding desktop 24 px, gap 16 px, sidebar 300 px. Layout aplikasi lain tetap memakai ukuran semula. Sidebar navigasi, empat tab, header pasien dan toolbar AI dipertahankan. |
| Textarea penuh bisa terlalu panjang pada layar lebar | Lebar textarea SOAP dibatasi 95ch, minimum tinggi S/O/A/P 104 px, tetap bisa diperbesar vertikal. Gap field 16 px dan jarak helper dipadatkan. Penjelasan autosave lengkap berada pada panduan; status dan tombol manual tetap terlihat. Tidak menghapus field/helper/validasi. |
| Header belum menjelaskan keadaan riwayat alergi kosong | Satu komponen status dipakai di header dan sidebar: alergi kritis, alergi tercatat, atau alergi belum dikonfirmasi. Catatan nonaktif/sudah digantikan tidak dianggap alergi aktif. Ikon dan teks mendampingi warna. Label keparahan tetap berasal dari enum existing. Banner kritis dipadatkan dan tetap di luar tab/disclosure. |
| Tidak ada data terverifikasi untuk status negatif alergi | Tidak menampilkan “tidak ada alergi yang diketahui” berdasarkan koleksi kosong. Status negatif terkonfirmasi belum tersedia di model, sehingga tidak dibuat melalui inferensi UI. |
| Status awal dapat dibaca seolah perubahan baru tersimpan | Awal editor menampilkan “Autosave aktif”; setelah input “Perubahan belum tersimpan”; “Tersimpan” hanya mengikuti konfirmasi. Foto dan koreksi final tetap memakai status khusus serta submit eksplisit. Penandaan hapus foto existing juga mengirim event perubahan sehingga status tidak tetap “Tersimpan”; autosave teks tidak menerapkan penghapusan foto. |
| Manual save selalu mengirim POST, termasuk setelah autosave mengonfirmasi isi yang sama | Client existing menunggu request aktif. Jika draft sudah dikonfirmasi, tidak berubah dan tidak memiliki foto tertunda, Simpan draft memakai hasil tersebut tanpa POST/reload tambahan. Perubahan baru, foto, finalisasi dan koreksi tetap mengirim request yang diperlukan. Form dikunci selama manual save menunggu; respons tidak valid tidak dianggap berhasil. Tidak membuat autosave/event listener kedua. |
| Beberapa pemanggil flush dapat menunggu request lama sementara revisi berikutnya berjalan | Semua pemanggil menunggu request aktif berikutnya; unit test memastikan dua revisi tetap menghasilkan dua request berurutan dan versi yang benar. Konflik editor lain tetap ditolak oleh backend existing. |
| Riwayat hanya merangkum assessment/rencana | Accordion menampilkan status RME Draft/Final secara eksplisit, diagnosis, tindakan dan jumlah, resep/aturan pakai, assessment dan rencana. Resep historis tetap tidak disebut obat aktif. Relasi dimuat secara batch; query tetap dibatasi klinik/pasien/dokter yang sama dan maksimal sepuluh visit. |
| Relasi RME historis juga harus sesuai dengan visit yang boleh dibaca | Eager loading record historis dibatasi lagi menurut pasien dan dokter yang sama. Test memastikan record dengan relasi dokter tidak konsisten tidak membocorkan assessment/resep. Tidak memperluas hak akses atau mengubah histori. |
| Dialog finalisasi hanya memuat pasien/jumlah obat | Review membaca form terkini: kelengkapan S/A/P, vital beserta unit, diagnosis/tindakan, resep termasuk dosis/frekuensi/jumlah/instruksi, konfirmasi alergi dan peringatan isian. Wajib/opsional dibedakan. Dokter bisa membuka review saat resep belum lengkap; konfirmasi final tetap mempertahankan pembatasan dan validasi. |
| Ringkasan panjang dapat mendorong tombol dialog ke bawah layar mobile | Tinggi dialog dibatasi menurut viewport dinamis; area ringkasan menyusut dan dapat digulir, sedangkan tombol tetap terlihat. Uji browser memeriksa posisi kedua tombol pada keenam ukuran. Fokus validasi diposisikan setelah transisi dialog selesai agar field tidak tertutup tab sticky. |
| Indikator tab baru menandai error setelah respons server | Tab juga mengenali batas input native dan isian resep yang wajib bila obat dicatat. Atribut required pada resep/tindakan mengikuti ketentuan finalisasi existing; Simpan draft tetap memakai formnovalidate. Tab tetap bebas dan tidak membongkar input. |

### File refinement

- `resources/views/layouts/app.blade.php`
- `resources/views/doctor/visits/show.blade.php`
- `resources/views/components/rme/allergy-status.blade.php` (baru)
- `resources/views/components/rme/allergy-editor.blade.php`
- `resources/views/components/rme/autosave-status.blade.php`
- `resources/css/app.css`
- `resources/js/rme-workspace.js`
- `resources/js/rme-autosave.js`
- `app/Http/Controllers/Doctor/VisitController.php`
- `tests/Feature/MedicalRecordAccessTest.php`
- `tests/Feature/VisitWorkflowTest.php`
- `tests/partial-workflows.test.mjs`
- `tests/rme-workspace-browser.mjs`
- `RME_UX_REPORT.md`

Perubahan source yang sudah ada sebelum refinement dipertahankan. Snapshot file dalam lingkup refinement ada di `C:/Users/User/AppData/Local/Temp/clinicos-refinement-before-g7e_2b18`. `.ai/rules` dan skill `testing-best-practices` kembali dicari tetapi tidak ditemukan; tidak diklaim sudah dibaca. Dokumentasi D-01–D-20 ditelusuri sebagai batas requirement, bukan izin mengubah domain.

### Pengujian refinement

- Node: **28 test lulus** pada `tests/partial-workflows.test.mjs` dan `tests/voice-scribe.test.mjs`. Cakupan baru: deduplikasi manual setelah/selama autosave, perubahan selama request, pemanggil flush bersamaan, foto tetap membutuhkan submit, dan HTTP 200 tanpa konfirmasi yang sah tidak menghapus editor.
- PHP akhir: **572 test, 546 lulus, 26 dilewati, 3.685 assertion**, 90,733 detik, exit 0. Termasuk test baru status alergi, ringkasan draft/final dan penolakan data historis dengan relasi yang tidak konsisten. Test akses terfokus juga lulus **5 test, 33 assertion**.
- Browser RME akhir (Chrome headless): **lulus**, 30,739 detik, pada **375, 768, 1024, 1366, 1440, 1920 px**. Tidak ada overflow halaman maupun review finalisasi; kedua tombol review terlihat; fokus validasi SOAP pada 375 px berada di bawah tab sticky. Data lintas tab, catatan panjang, resize vertikal, handler keyboard/fokus, validasi, status alergi, riwayat, draft, upload/hapus foto, offline/reconnect, konflik versi, review/finalisasi dan koreksi diperiksa. Spy request memastikan klik Simpan draft setelah autosave yang bersih menambah **nol** POST dan tidak menaikkan versi/reload. Penghapusan foto terbukti tertunda hingga submit eksplisit.
- Browser Voice: **lulus**, 31,273 detik, termasuk consent, mikrofon sintetis, rekam/jeda/lanjut, review/penerapan field terpilih, pengeditan SOAP dan pencabutan persetujuan. Tidak memakai provider atau kredit nyata.
- Build `npm run build`: **berhasil**, Vite 8.3.4. Pint dan `git diff --check`: **lulus**.

Bukti sebelum: `C:/Users/User/AppData/Local/Temp/clinicos-browser-XFQGLu`.
Bukti RME refinement akhir: `C:/Users/User/AppData/Local/Temp/clinicos-browser-dxd5Cg`.
Bukti Voice: `C:/Users/User/AppData/Local/Temp/clinicos-browser-r3JFit`.
Hasil PHP akhir: `C:/Users/User/AppData/Local/Temp/clinicos-refinement-php-final.json`.
Folder RME akhir menyimpan screenshot workspace, banner kritis dan review finalisasi pada keenam lebar layar serta `rme-workspace-metrics.json`. Screenshot workspace 1920/mobile, banner kritis mobile serta review mobile/desktop diperiksa secara visual. Console exception dipantau oleh harness browser; run yang dilaporkan lulus mengharuskan nol exception JavaScript.

### Perbandingan layout refinement

Kunjungan demo dan viewport tinggi 900 px yang sama, font selesai dimuat. Ini ukuran layout browser, bukan pengukuran waktu kerja dokter.

| Lebar | Lebar form sebelum → sesudah | Sidebar sebelum → sesudah | Posisi field pertama sebelum → sesudah |
| --- | --- | --- | --- |
| 375 | 328 → 328 px | 328 → 328 px, di bawah form | 670 → 664 px |
| 768 | 705 → 705 px | 705 → 705 px, di bawah form | 510 → 492 px |
| 1024 | 641 → 673 px | 641 → 673 px, di bawah form | 478 → 424 px |
| 1366 | 683 → 699 px | 280 → 300 px | 458 → 404 px |
| 1440 | 757 → 773 px | 280 → 300 px | 458 → 404 px |
| 1920 | 1060 → 1253 px | 280 → 300 px | 458 → 404 px |

Pada 1920 px, kontainer bertambah dari 1440 menjadi 1617 px efektif dan form bertambah sekitar **18%**. Textarea keluhan dibatasi **839 px** pada fixture ini, sehingga tidak mengikuti seluruh lebar form 1253 px. Tinggi awal halaman desktop turun dari 1726 ke 1644 px pada 1440/1920 px. Tinggi mobile sedikit bertambah (3123 ke 3140 px) karena status alergi terlihat dan textarea lebih lapang; tidak diklaim semua layar menjadi lebih pendek.

### Batas dan risiko tersisa

- “Tidak ada alergi yang diketahui” memerlukan sumber negatif terkonfirmasi dan aturan pencatatan yang disetujui; belum ditambahkan.
- Review adalah bantuan pemeriksaan, bukan pengesahan otomatis atau pemeriksaan interaksi obat. Ketentuan finalisasi, konfirmasi alergi, koreksi, dan transaksi domain tetap berlaku.
- Belum ada UAT dokter, perangkat mobile nyata, keyboard fisik/screen reader, mikrofon/provider AI nyata, atau pengukuran waktu pelayanan. Uji keyboard browser memakai event DOM seperti harness existing.
- Respons bertumpuk dan konflik versi diuji pada client; run SQLite bukan bukti lock MySQL multi-proses. Test MySQL opt-in tidak dijalankan.
- Regresi operasional browser menyeluruh tanggal 22 November di bawah adalah bukti historis; refinement ini mengulang browser RME/Voice dan suite PHP sesuai perubahan.

## Riwayat implementasi — 22 November 2026

Tanggal: 22 November 2026. Perubahan lokal, tanpa commit/push. Stack tetap Laravel, Blade, Tailwind, Alpine, serta JavaScript existing. Tidak menambah dependency, endpoint, atau migration untuk redesign.

## Audit awal dan dependensi

Checkout sudah berbeda dari PROJECT_ANALYSIS.md Oktober: autosave jaringan dengan `record_version`, versi alergi, persistence odontogram, diagnosis/tindakan, koreksi berhistori, dan Voice Scribe sudah tersedia. Dokumentasi lama tentang fitur presentasional tidak dijadikan gambaran implementasi sekarang.

| Area | Bukti yang ditelusuri | Temuan UX / batas |
| --- | --- | --- |
| Editor | `doctor/visits/show.blade.php`, komponen `rme`, CSS/token Tailwind | Form panjang; panduan, AI, identitas, dan administrasi mendahului SOAP. Tombol finalisasi jauh di bawah. |
| Akses | `VisitController`, `VisitPolicy`, `MedicalRecordPolicy`, request dokter | Dokter/tenant tetap diperiksa server. Riwayat dibatasi klinik, pasien, dan dokter yang sama. Tidak memperluas akses medis. |
| Penyimpanan | `rme-autosave.js`, `SaveMedicalRecordRequest`, `VisitWorkflowService` | Debounce 1.200 ms, request berurutan, pemeriksaan versi, retry/offline, konflik/expiry, foto lewat submit eksplisit. Mekanisme dipakai ulang. |
| Finalisasi/koreksi | `CompleteVisitRequest`, `CorrectMedicalRecordRequest`, workflow dan modal existing | S/A/P wajib, aturan resep/alergi, transaksi status terkait, alasan koreksi dan snapshot tetap berlaku. Finalisasi bukan tanda tangan digital bersertifikat. |
| Diagnosis/tindakan | Request, model, `syncRecord`, editor array Alpine | Input terstruktur sudah ada. Tidak ditemukan katalog diagnosis/tindakan resmi untuk pencarian/autocomplete. Kode diagnosis existing divalidasi sebagai string berbatas panjang, bukan diverifikasi terhadap ICD. |
| Resep/alergi | Prescription/items, patient allergies/revisi, allergy editor/banner | Konfirmasi alergi kritis wajib bila ada obat. Tidak ditemukan katalog obat atau mesin interaksi/alergi otomatis; pengingat klinis tidak digambarkan sebagai pemeriksaan otomatis. |
| Tanda vital | `VitalSign`, input form, workflow | Nilai kunjungan ini sudah diisi kembali dari record. Tidak ditemukan sumber triase terpisah yang sah untuk digabungkan otomatis. `updated_at` adalah waktu pencatatan, bukan bukti waktu pengukuran. |
| AI/suara | Voice panel/JS/controller/service/provider/job/test | Kontrol, persetujuan, review dokter, pilihan field, kuota dan kode kegagalan kredit sudah ada. Tidak ada API saldo kredit aktual pada halaman. |
| Konteks | History query, allergy editor, appointment/visit, care continuity | Informasi tersebar; riwayat harus meninggalkan editor untuk melihat ringkasan lebih lanjut. Tidak ada sumber obat aktif longitudinal yang boleh disimpulkan dari resep lama. |

`.ai/rules` dan skill `testing-best-practices` dicari tetapi tidak ditemukan. Skill tersebut tidak diklaim telah dibaca. Mengikuti pola test proyek yang tersedia. Tidak mengubah komponen Volt.

## Perubahan dan alasan

1. Header menyatukan identitas, umur/gender, layanan, status visit/RME, dan tautan riwayat/alergi. Penanda alergi kritis berada pada header; banner lengkap berada di luar tab dan disclosure.
2. Empat tab bebas: SOAP, Tanda vital, Diagnosis & tindakan, Resep. Semua field tetap terpasang dalam satu form; tab hanya menyembunyikan panel. Tidak memakai localStorage, form duplikat, atau autosave kedua.
3. Tab memiliki hubungan ARIA, satu tab stop aktif, ArrowLeft/Right dan Home/End. Status SOAP menghitung tiga field wajib; bagian opsional memakai jumlah entri, bukan klaim kelengkapan klinis.
4. Validasi server menampilkan ringkasan yang bisa menuju field. Submit dan validasi native dapat membuka tab/disclosure berisi field invalid; finalisasi tidak terhalang field tersembunyi yang tidak dapat difokuskan. Autosave tidak memindahkan fokus saat dokter mengetik.
5. Tombol simpan/finalisasi dan status penyimpanan tetap terlihat di bawah viewport. Foto mempunyai status khusus setelah teks tersimpan agar dokter tahu perubahan foto masih perlu submit eksplisit. Perubahan catatan final berlabel koreksi belum disimpan, tetap tanpa autosave.
6. SOAP lebih ringkas dengan ukuran textarea sesuai kebutuhan dan maxlength sesuai aturan server. Foto menjadi disclosure; semua input, upload/paste, penghapusan dan endpoint foto private dipertahankan.
7. Tambah diagnosis/tindakan/obat memfokuskan input baru. Resep mempunyai tombol tambah di bawah daftar, batas jumlah existing, preview dan warning tetap tersedia. Tidak memilih obat/dosis atau mengisi temuan klinis otomatis.
8. Sidebar memprioritaskan alergi dan ringkasan assessment/rencana kunjungan sebelumnya. Resep lama tidak disebut obat aktif. Detail kunjungan dan form jeda dapat diperluas; koordinasi kasus, kontrol, dokumen resep dan validasi layanan tetap tersedia.
9. AI menjadi disclosure dengan status sesi tetap terlihat saat ditutup. Error membuka panel. Mikrofon, persetujuan, simulasi, opsi sesi, transkrip/kandidat, review dan pilihan field SOAP dipertahankan. Login kedaluwarsa memberi tautan masuk di tab baru; pesan kredit habis tetap bersumber dari kode backend.
10. Pengaman test ditambahkan setelah insiden konfigurasi yang dijelaskan di bawah: cache test dipisahkan dan bootstrap menolak environment/database yang bukan target test sebelum trait RefreshDatabase berjalan.

Tidak mengubah kebijakan tenant, pemilihan dokter, status antrean/visit, transaksi klinis, aturan koreksi, entitlement odontogram, atau workflow commercial. Perubahan source pengguna yang sudah ada pada Voice Scribe dan file lain dipertahankan. Insiden data demo dijelaskan terpisah di bawah.

## File pekerjaan ini

- `resources/views/doctor/visits/show.blade.php`
- `resources/views/doctor/visits/voice-panel.blade.php` (sudah merupakan file lokal pengguna)
- `resources/views/components/rme/autosave-status.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/js/rme-workspace.js` (baru)
- `resources/js/rme-autosave.js`
- `resources/js/voice-scribe.js` (sudah merupakan file lokal pengguna)
- `tests/rme-workspace-browser.mjs` (baru)
- `tests/browser-partial-workflows.test.mjs`
- `tests/voice-scribe-browser.mjs`
- `tests/partial-workflows.test.mjs`
- `tests/voice-scribe.test.mjs`
- `tests/TestCase.php`
- `phpunit.xml`
- `RME_UX_REPORT.md` (laporan ini)

## Bukti eksekusi

Pengujian browser memakai database SQLite dan storage sementara terpisah, seed sintetis, Chrome headless, dan provider Voice fake. Pengujian PHP yang berhasil memakai SQLite in-memory serta jalur cache test tersendiri. Insiden run pertama dijelaskan terpisah dan tidak disembunyikan dari hasil ini.

| Pemeriksaan | Hasil terbaru |
| --- | --- |
| Baseline klinis sebelum redesign, setelah cache diisolasi | 32 test, 289 assertion, lulus |
| PHP terfokus (visit, RME, akses, koreksi, partial workflows, voice/provider) | 76 test, 644 assertion, lulus |
| Suite PHP penuh | 570 test: 544 lulus, 26 dilewati; 3.665 assertion; 97,687 detik; exit 0. Test MySQL opt-in termasuk yang dilewati. |
| Node autosave/voice | 23 test lulus; mencakup pending foto, koreksi tanpa autosave, request bersamaan, offline, versi usang, HTTP 401/419, dan approval field terpilih. |
| PHP setelah penyesuaian label/status terakhir | 23 test klinis, 224 assertion, lulus. |
| Browser workspace RME | Lulus; tab/FormData tetap sama, handler keyboard/fokus, tambah/hapus item, persist/reload, validasi field tersembunyi, offline/reconnect, editor usang, alergi/resep, foto, finalisasi dan koreksi. Run terakhir 25,040 detik. |
| Browser Voice | Lulus; persetujuan, mikrofon sintetis, pause/resume/stop, review, field terpilih, draft manual dan pencabutan persetujuan; empat lebar layar dan nama panjang. Run 30,583 detik. |
| Browser workflow existing penuh | Lulus, 211,316 detik: alur klinis/koreksi, alergi, booking, jeda/kelanjutan, resep terbit, kontrol, billing/refund, laporan, pengingat dan navigasi role. |
| Build | `npm run build`: Vite 8.3.4 berhasil. |
| Formatter/whitespace | `vendor/bin/pint --dirty --format agent`: passed; `git diff --check`: passed. |
| Pengaman konfigurasi | Probe bootstrap dengan cache aplikasi menghasilkan penolakan yang diharapkan sebelum RefreshDatabase. |

Perintah PHP aman setelah perubahan:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/bin/phpunit --no-progress --do-not-cache-result
```

`phpunit.xml` sekarang memaksa jalur cache khusus `bootstrap/cache/config.phpunit.php`. Pengaman bootstrap juga memeriksa nama database efektif setelah parsing konfigurasi koneksi. Database yang diizinkan: SQLite `:memory:` atau MySQL `clinicos_testing`, pada environment `testing`.

```powershell
node --test tests/partial-workflows.test.mjs tests/voice-scribe.test.mjs
$env:CLINICOS_BROWSER_TEST = '1'
$env:CLINICOS_VOICE_BROWSER_TEST = '1'
$env:CLINICOS_RME_BROWSER_TEST = 'after'
node --test tests/browser-partial-workflows.test.mjs
```

Hapus `CLINICOS_RME_BROWSER_TEST` untuk menjalankan mode browser Voice; hapus juga `CLINICOS_VOICE_BROWSER_TEST` untuk workflow browser existing penuh. Environment tersebut hanya untuk proses test, bukan pengaturan aplikasi.

Bukti lokal di folder sementara (berisi fixture sintetis):

- Baseline: `C:/Users/User/AppData/Local/Temp/clinicos-browser-o1GYIC`.
- Workspace final: `C:/Users/User/AppData/Local/Temp/clinicos-browser-XmezFJ`.
- Voice: `C:/Users/User/AppData/Local/Temp/clinicos-browser-6PuvGa`.
- Workflow operasional penuh: `C:/Users/User/AppData/Local/Temp/clinicos-browser-HH1Xsv`.
- Hasil suite PHP: `C:/Users/User/AppData/Local/Temp/clinicos-rme-phpunit-results.json`.

Setiap folder workspace memuat `rme-workspace-metrics.json` dan screenshot pada 375, 768, 1024, 1440 px. Screenshot desktop, tablet dan mobile diperiksa secara visual; keempat lebar diperiksa otomatis terhadap overflow. Uji handler keyboard menggunakan event DOM; percobaan CDP key dispatch pada host ini tidak mengirim event ke halaman, sehingga bukan bukti keyboard fisik/screen reader.

Run browser operasional penuh pertama berhenti pada assertion fixture lama yang mengharuskan 20 menit sebelum tengah malam klinik. Rentang fixture pembatalan pengingat dipersingkat menjadi dua menit (tetap sebelum sesi dimulai), lalu run penuh diulang dan lulus. Tidak mengubah cutoff pembatalan pada aplikasi.

## Perbandingan efisiensi

Pengukuran nyata pada kunjungan demo awal yang sama, viewport tinggi 900 px, font selesai dimuat, Voice fake aktif, sebelum input QA. Posisi diukur dari atas dokumen pada scroll awal; aksi baru memakai sticky positioning. Ini pengukuran layout, bukan UAT dokter atau durasi pelayanan.

| Lebar | Tinggi halaman sebelum → sesudah | Posisi field SOAP pertama sebelum → sesudah | Posisi tombol finalisasi sebelum → sesudah |
| --- | --- | --- | --- |
| 375 | 8.873 → 3.123 px | 2.472 → 670 px | 7.606 → 823 px |
| 768 | 6.617 → 2.425 px | 1.810 → 510 px | 5.428 → 837 px |
| 1024 | 6.465 → 2.429 px | 1.802 → 478 px | 5.240 → 837 px |
| 1440 | 5.262 → 1.726 px | 1.738 → 458 px | 5.176 → 837 px |

- Tinggi awal halaman turun sekitar 65% pada mobile dan 67% pada desktop. Field pertama masuk viewport awal. Ini bukan pengurangan panjang seluruh konten: panel lain tetap tersedia melalui tab/disclosure.
- Membuka bagian tertentu sekarang satu pemilihan tab; sebelumnya perlu menemukan bagian lewat scrolling. Untuk meninjau keempat bagian dari SOAP diperlukan tiga perpindahan tab. Perubahan jumlah klik total belum diukur pada dokter nyata.
- Dialog finalisasi tetap membutuhkan dua aktivasi (tinjau, konfirmasi); jumlah itu dipertahankan demi keselamatan.
- Nilai vital yang tersimpan tetap terisi pada kedua versi; tidak ada klaim pengurangan input ulang karena backend sudah menyediakannya.
- Ringkasan assessment/rencana riwayat dapat dibuka tanpa meninggalkan editor. Tidak diukur waktu klinis menemukan informasi atau tingkat kesalahan pengguna.
- Durasi run browser adalah durasi QA otomatis, bukan waktu menyelesaikan pemeriksaan. Benchmark waktu kerja/klik/scroll keseluruhan dengan dokter belum dilakukan.

## Insiden database demo dan pengaman

Run PHPUnit pertama mengikuti override SQLite proses, tetapi `bootstrap/cache/config.php` yang sudah ada memuat environment local dan koneksi MySQL `clinicdb`. Cache mengalahkan override. Trait RefreshDatabase kemudian membuat ulang tabel database aplikasi. Run menghasilkan 3 kegagalan assertion dan 29 error karena request berstatus 419/visit tidak terbentuk.

Pemeriksaan metadata mengonfirmasi tabel dibuat ulang pada waktu run dan jumlah akun/pasien/visit/klinik menjadi nol. Pengguna diberi tahu segera setelah penyebab ditemukan dan mengonfirmasi bahwa data sebelumnya adalah **data demo**. Tidak mengklaim bahwa database aplikasi tidak tersentuh.

Binary log disalin ke folder sementara privat `C:/Users/User/AppData/Local/Temp/clinicos-recovery-99aeafd0379046bd9f908d8c0b3e2d76`. Percobaan replay hanya berjalan pada server MySQL terpisah port 18949 dan belum menghasilkan pemulihan lengkap karena schema awal tidak tercakup pada log yang tersedia. Server terisolasi dihentikan.

Pengguna kemudian secara eksplisit menyetujui pengisian dataset demo pengganti. Dataset dari DemoSeeder disiapkan pada server terisolasi dan diimpor ke aplikasi dalam transaksi setelah memastikan seluruh tabel tujuan masih kosong: **10 akun, 12 pasien, 16 kunjungan, 5 klinik**, tersebar pada 37 tabel yang diisi. Jumlah utama diverifikasi sebelum commit; cache permission direset. Pemeriksaan relasi dokter/akun dan appointment/pasien/dokter tidak menemukan mismatch tenant. Ini penggantian dataset demo, bukan pemulihan penyesuaian demo lama.

Berkas `demo-replacement.sql` berada di folder privat tersebut. Impor hanya berisi data, tanpa DDL dan tanpa penggantian histori migration. Server MySQL pemulihan terisolasi sudah dihentikan.

Pencegahan: jalur cache PHPUnit dipisahkan, bootstrap menolak target non-test sebelum migrasi, dan penolakan tersebut benar-benar dieksekusi sebagai probe. Semua run PHP berikutnya diisolasi dan berhasil sebagaimana tabel bukti di atas.

## Keputusan dan masalah terbuka

- **Decision required:** sumber dan lisensi katalog diagnosis/obat/tindakan, serta aturan pemilihan kode yang valid. Tidak menambahkan autocomplete berisi data buatan.
- **Decision required:** sumber daftar obat aktif, triase/vital lintas tahap, dan aturan penggabungan. Tidak menyalin vital kunjungan lama atau menyimpulkan obat aktif.
- **Decision required:** integrasi saldo kredit provider bila saldo aktual memang perlu ditampilkan. Error kredit habis existing dipertahankan; tidak membuat angka saldo.
- Finalisasi existing memakai transaksi, pelaku/waktu dan aturan koreksi; tidak menambahkan tanda tangan digital atau mengubah status pelayanan interupsi.
- Belum ada validasi usability dengan dokter, pengukuran durasi klinis, keyboard fisik, screen reader, mikrofon nyata, provider AI nyata, atau klaim kepatuhan klinis.
- Stale editor/in-flight request diuji; run SQLite dan browser tidak membuktikan lock/konkurensi multi-proses MySQL. Tidak menjalankan test MySQL opt-in pada tugas ini.
- Deployment, Reverb lintas perangkat, scheduler/mail production, dan billing nyata berada di luar verifikasi perubahan UI ini.
