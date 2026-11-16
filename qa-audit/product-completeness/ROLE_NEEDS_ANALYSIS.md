# Kebutuhan seluruh role

Tanggal audit: **14 November 2026**. Audit produk read-only; tidak ada implementasi/perbaikan. Fakta source/UI dipisahkan dari inferensi kebutuhan dan rekomendasi. Semua UI menggunakan data dummy pada SQLite terisolasi. 

FAKTA discovery otomatis: **Clinic Admin, Doctor, Patient, Platform Admin, Receptionist**. Owner dipisahkan sebagai persona karena `account_type=clinic_owner` + `clinics.owner_user_id`, tetap memakai role Clinic Admin. Tidak menciptakan role/permission baru. Daftar kebutuhan di bawah adalah analisis tugas, bukan bukti wawancara manusia.


## Clinic Owner (role Clinic Admin + account_type/owner relation)

| Dimensi | Analisis |
| --- | --- |
| Tujuan utama | Menyiapkan dan mengelola satu klinik serta kontrak SaaS. |
| Tanggung jawab harian | Memantau layanan, staff, kapasitas, subscription dan exception operasional. |
| Informasi yang dibutuhkan | Aktivitas/status agregat, jam/sesi, seats/kuota, invoice dan akses add-on; bukan isi SOAP. |
| Tindakan | Edit clinic/layanan/staf/jadwal; aktivasi/nonaktif; beli paket/add-on, baca audit. |
| Akses seharusnya | Tenant sendiri dan owner-only commercial endpoints; tidak otomatis isi medis. |
| Hubungan antar-role | Mendelegasikan operasional ke Admin/Rec, kapasitas dengan Doctor, kontrak dengan Platform. |
| Proses berulang | Cek dashboard, ubah jadwal, pantau kuota dan reminder; frekuensi belum diukur. |
| Kesalahan / pemulihan | Katalog kosong, limit seats, expired/suspended sudah punya jalur; jam berubah perlu review booking existing. |
| Tugas belum didukung | Kalender khusus, laporan export, lifecycle owner dan tugas kasir jika dipilih. |
| Langkah manual | Koordinasi libur, laporan/kas eksternal dan permintaan transfer melalui dukungan. |

Bukti: UI-01,12,13,16,20,23,24; [task review](evidence/task-review.json), [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1). Gap terkait: **PG-02,05,08,09,11,15,16**. Kebutuhan/frekuensi adalah inferensi agen sampai divalidasi pemilik peran.

## Clinic Admin non-owner

| Dimensi | Analisis |
| --- | --- |
| Tujuan utama | Menjalankan administrasi tenant tanpa mengambil hak komersial owner. |
| Tanggung jawab harian | Mengatur staf dokter/resepsionis, layanan, jadwal, clinic settings dan aktivitas. |
| Informasi yang dibutuhkan | Metrik dan resource klinik, alasan pembatasan paket; owner sebagai kontak pemulihan. |
| Tindakan | Aksi administrasi yang diberi permission; gunakan owner untuk pembelian/delegasi yang belum tersedia. |
| Akses seharusnya | Tenant-scoped; tidak memperoleh SaaS owner actions atau isi medis hanya karena admin. |
| Hubungan antar-role | Owner menentukan kapasitas/kontrak; Rec mengoperasikan booking; Doctor bertanggung jawab klinis. |
| Proses berulang | Kelola jadwal/layanan/staf; navigasi admin sama basis owner dengan hak berbeda. |
| Kesalahan / pemulihan | Akun nonaktif/session expired dan seats; tidak mengatasi limit lewat role baru. |
| Tugas belum didukung | UI provisioning admin tambahan belum ada; perubahan appointment dan impact jadwal belum terpandu. |
| Langkah manual | Owner melakukan provisioning, komunikasi perubahan kalender secara manual. |

Bukti: UI-02; source permission/policy; [task review](evidence/task-review.json), [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1). Gap terkait: **PG-01,02,08,11,15**. Kebutuhan/frekuensi adalah inferensi agen sampai divalidasi pemilik peran.

## Receptionist

| Dimensi | Analisis |
| --- | --- |
| Tujuan utama | Membuat pasien/booking, menerima kedatangan dan mengatur antrean. |
| Tanggung jawab harian | Cari pasien, periksa kandidat duplikat, booking, check-in, call/skip/return/cancel/no-show. |
| Informasi yang dibutuhkan | Identitas administrasi, status aktif, jadwal/sesi/kuota, nomor dan posisi; tidak isi medis. |
| Tindakan | Buat/edit patient yang diizinkan; pilih profil existing; jalankan transisi pra-visit. |
| Akses seharusnya | Resource klinik sendiri; cancel tidak boleh menghilangkan visit aktif/final. |
| Hubungan antar-role | Menerima kedatangan Patient, berkoordinasi Doctor untuk mulai, Owner/Admin untuk kapasitas. |
| Proses berulang | Pencarian, booking, check-in/call berulang per pasien (inferensi urutan kerja, bukan volume terukur). |
| Kesalahan / pemulihan | Inactive patient menjelaskan reaktivasi; duplicate review/queue conflict/error tidak boleh memicu profil baru. |
| Tugas belum didukung | Reschedule/reassign, dokter absen, follow-up scheduling terhubung, kasir bila scope dipilih. |
| Langkah manual | Cancel-rebook pra-visit, telepon pasien, cari seluruh daftar pasien, gunakan kas eksternal. |

Bukti: UI-03,07,08,17,18,22,25; [task review](evidence/task-review.json), [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1). Gap terkait: **PG-01,02,03,04,05,06,12,17**. Kebutuhan/frekuensi adalah inferensi agen sampai divalidasi pemilik peran.

## Doctor

| Dimensi | Analisis |
| --- | --- |
| Tujuan utama | Melayani pasien yang ditugaskan dan menyelesaikan catatan yang akurat. |
| Tanggung jawab harian | Pantau antrean, start, lihat histori berizin, draft/autosave SOAP/vital/diagnosis/tindakan/resep, final/koreksi. |
| Informasi yang dibutuhkan | Identitas relevan, alergi/versi, histori yang diotorisasi, status save/conflict, entitlement dental. |
| Tindakan | Edit draft, foto private, odontogram bila berhak, acknowledgement, final/correction beralasan. |
| Akses seharusnya | Assigned visit/RME dan histori sesuai policy; owner/platform tidak menjadi dokter pengganti otomatis. |
| Hubungan antar-role | Rec mengatur kedatangan, Patient menerima resep/instruksi, Owner menjaga entitlement tanpa membaca medis. |
| Proses berulang | Per visit: review → catat → final; alergi dibaca saat relevan; actual frequency belum diukur. |
| Kesalahan / pemulihan | 401/419/offline/conflict mempertahankan editor; finish visit IN_PROGRESS tetap diizinkan saat paket terbatas. |
| Tugas belum didukung | Interupsi/handover, resolusi sibling allergy, dokumen pasien dan follow-up tertutup. |
| Langkah manual | Koordinasi manual, prosedur klinik luar sistem, instruksi kontrol dan dokumen eksternal. |

Bukti: UI-04,09,10,26; bukti dental historis; [task review](evidence/task-review.json), [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1). Gap terkait: **PG-03,04,07,13**. Kebutuhan/frekuensi adalah inferensi agen sampai divalidasi pemilik peran.

## Patient

| Dimensi | Analisis |
| --- | --- |
| Tujuan utama | Booking dan mengetahui kedatangan/giliran serta resep final miliknya. |
| Tanggung jawab harian | Pilih klinik terkait/kode, booking, lihat/cancel pra-visit, pantau antrean dan notification center. |
| Informasi yang dibutuhkan | Informasi klinik, jadwal, status booking/giliran, alasan kegagalan, instruksi yang diizinkan. |
| Tindakan | Aksi hanya melalui profil/appointment miliknya; guest memakai secret booking token. |
| Akses seharusnya | Kepemilikan patient.user_id; resep final milik sendiri; tidak full SOAP atau pasien lain. |
| Hubungan antar-role | Rec untuk perubahan/aktivasi, Doctor untuk klinis, Owner tidak menjadi penerima semua isi medis. |
| Proses berulang | Per episode booking/kunjungan dan monitoring; belum ada pengukuran perilaku nyata. |
| Kesalahan / pemulihan | Session expiry dan validasi; nonaktif antar kanal belum konsisten; kode klinik perlu diketahui pada first use. |
| Tugas belum didukung | Reschedule terpandu, reminder proaktif, dokumen terbit dan follow-up terhubung. |
| Langkah manual | Hubungi klinik, pantau portal/display, booking baru manual; jangan menganggap directory wajib. |

Bukti: UI-05,11,19,22,27; [task review](evidence/task-review.json), [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1). Gap terkait: **PG-01,04,06,07,12**. Kebutuhan/frekuensi adalah inferensi agen sampai divalidasi pemilik peran.

## Platform Admin

| Dimensi | Analisis |
| --- | --- |
| Tujuan utama | Mengelola metadata tenant, penawaran SaaS dan exception komersial. |
| Tanggung jawab harian | Lihat tenant/current effective status, katalog, invoice/payment, reconcile/grant/manual refund dan audit. |
| Informasi yang dibutuhkan | Owner mapping, paket/period/source, invoice/events dan alasan perubahan; tanpa catatan medis. |
| Tindakan | Grant idempotent/Free, ubah catalog, reconcile provider dan record refund beralasan sesuai izin. |
| Akses seharusnya | Permission platform.*; tidak impersonation atau bypass medical policy. |
| Hubungan antar-role | Owner untuk kontrak, provider untuk pembayaran, support untuk legacy correction dengan persetujuan. |
| Proses berulang | Monitoring tenant/payment periodik dan ticket exception insidental (inferensi). |
| Kesalahan / pemulihan | Provider pending/review/expired dan kegagalan reconcile; jangan aktivasi hanya dari browser return. |
| Tugas belum didukung | Timeline utama, single-grant recovery, lifecycle tenant, diagnosis legacy dan kontrak refund dana/akses. |
| Langkah manual | Kombinasi invoice/audit/manual support; funds refund eksternal; tanpa edit DB spontan. |

Bukti: UI-06,14,15; global-empty belum browser; [task review](evidence/task-review.json), [database/seeders/RoleAndPermissionSeeder.php:1](../../database/seeders/RoleAndPermissionSeeder.php#L1). Gap terkait: **PG-09,10,11,14,16**. Kebutuhan/frekuensi adalah inferensi agen sampai divalidasi pemilik peran.


## Aktor di luar stored roles

Guest dapat melihat klinik/display dan booking/status dengan token; tidak otomatis dicocokkan identitas lama. Scheduler/worker mengirim reminder/delivery, provider melakukan validasi pembayaran, support bukan role medis baru. Kasir belum role tersimpan; bila PG-05 disetujui, putuskan apakah Receptionist atau role terpisah berdasarkan pemisahan tugas, jangan menciptakannya sekarang.

## Batas akses yang tidak menjadi gap

Owner/Admin/Rec/Platform tidak berhak membuka seluruh SOAP/foto hanya agar workflow administrasi lebih cepat. Akses histori lintas dokter juga memerlukan keputusan klinis tersendiri; daftar RME paginated sudah tersedia bagi dokter yang diotorisasi. Editor permission generik bukan prasyarat menjalankan lima role yang ada. PR-01 tetap risiko revokasi socket terpisah.
