# ClinicOS — Rencana RME General dan Add-on Odontogram

- Tanggal: 3 November 2026
- Status: implementasi add-on selesai di kode dan MySQL lokal; verifikasi DOKU sandbox nyata dan konkurensi MySQL belum dilakukan
- Pemilik keputusan produk: pengguna
- Sasaran: RME General tersedia pada setiap paket ClinicOS; odontogram menjadi add-on berbayar opsional per klinik.

## 1. Keputusan produk

| Topik | Keputusan |
| --- | --- |
| RME General | Termasuk dalam Free, Individual, dan Team sesuai batas operasional masing-masing paket. |
| Add-on Odontogram | Opsional untuk klinik dengan paket Individual atau Team berbayar yang aktif. Berlaku untuk seluruh dokter yang berwenang di klinik, tanpa harga per dokter. |
| Trial Team | Tidak menyertakan odontogram dan belum dapat membeli add-on. |
| Harga | Rp99.000 per klinik per bulan. |
| Pembelian | Owner dapat memilihnya bersamaan dengan checkout paket berbayar atau membeli kemudian saat paket berbayar aktif. |
| Penagihan | Dibayar sekali di muka untuk sisa periode paket utama. Pembelian di tengah periode memakai prorata hari kalender pada setiap bulan. |
| Akhir masa aktif | Add-on berakhir pada waktu yang sama dengan periode paket utama yang dibelinya. Tidak ada perpanjangan atau tagihan otomatis. Owner memilih lagi saat memperpanjang paket utama. |
| Data lama setelah berakhir | Dokter yang berwenang tetap dapat membaca odontogram tersimpan; perubahan odontogram baru ditolak. RME General tetap mengikuti hak akses dan status paket utama. |
| Refund add-on | Setelah refund tervalidasi, hak mengubah odontogram berhenti. Data klinis, periode, invoice, event, dan audit tetap tersimpan; dokter yang berwenang tetap dapat membaca histori. |

Perubahan paket utama ke Free, expired, atau suspended tidak boleh memberi hak edit odontogram. Add-on bukan pengganti otorisasi medis: hanya dokter yang ditugaskan pada visit/rekam medis yang dapat melihat atau mengubah isi odontogram.

## 2. Keadaan aplikasi saat rencana dibuat

- Paket Free, Individual, dan Team, satu subscription per klinik, periode 1/6/12 bulan, trial Team 14 hari, dan pembayaran DOKU sudah ada.
- RME General dan odontogram saat ini berada pada form pemeriksaan yang sama. Odontogram 32 gigi FDI sudah divalidasi dan disimpan di medical_records.odontogram melalui VisitWorkflowService; komponen selalu ditampilkan, tanpa pemeriksaan entitlement.
- Payment dan SubscriptionPeriod saat ini berasumsi satu invoice untuk satu harga paket. Checkout gabungan dan invoice khusus add-on memerlukan perluasan data serta pemrosesan pembayaran.
- Dokumen PROJECT_ANALYSIS.md dan docs/odontogram-allergy/README.md menggambarkan keadaan lama ketika odontogram belum persisten. Kode dan test RmeGeneralDentalTest saat ini menjadi acuan implementasi terbaru; pembaruan dokumen lama bukan bagian pekerjaan rencana ini.
- Flag premium lain pada SubscriptionPlan.features belum semuanya diterapkan sebagai gate. Implementasi add-on harus memakai pemeriksaan entitlement server-side yang nyata, bukan mengandalkan flag atau tampilan kartu harga.

## 3. Kontrak harga dan periode

1. Pada checkout paket baru atau perpanjangan yang menyertakan odontogram, tampilkan dua baris harga: paket utama dan add-on. Harga add-on untuk periode penuh adalah Rp99.000 × jumlah bulan paket utama; contoh 12 bulan penuh Rp1.188.000. Satu pembayaran DOKU mengaktifkan kedua hak secara atomik setelah sukses tervalidasi.
2. Pada pembelian setelah paket aktif, targetkan periode paket utama yang sedang aktif. Awal hak edit adalah saat pembayaran tervalidasi; akhir hak edit sama dengan ends_at periode paket tersebut. Jangan menjadwalkan add-on di atas periode renewal yang belum dipilih owner.
3. Hitung prorata dari hari kalender yang tersisa pada tiap bulan menurut zona waktu klinik: untuk setiap bulan, Rp99.000 × (jumlah hari layanan yang ditagih pada bulan itu ÷ jumlah hari pada bulan itu). Jumlahkan komponen, lalu bulatkan sekali ke rupiah terdekat dengan aturan half-up. Hari aktivasi dihitung satu hari penuh. Harga tidak boleh melebihi harga add-on periode penuh yang bersangkutan.
4. Kutipan harga dihitung di server, menampilkan tanggal awal/akhir, rincian prorata, dan total sebelum owner membuat invoice. Simpan harga, tanggal, produk, dan periode target sebagai snapshot pada invoice; query string atau form tidak menentukan harga atau entitlement.
5. Kutipan hanya berlaku pada tanggal lokal saat dibuat. Invoice yang belum dibayar tidak boleh melewati tanggal lokal berikutnya atau akhir periode paket utama. Jika tanggal berubah sebelum pembayaran, buat kutipan/invoice baru. Notification yang datang terlambat tetap dicatat; jangan mengaktifkan hak tanpa periode paket yang valid.
6. Periksa semantik batas waktu SubscriptionService::endAfterMonths yang saat ini menambah satu hari. Definisikan satu rentang waktu layanan dengan akhir eksklusif dan pakai rentang yang sama untuk harga, tampilan, dan entitlement. Test harus memastikan checkout penuh 1/6/12 bulan tepat Rp99.000/Rp594.000/Rp1.188.000 dan prorata tengah periode tidak lebih mahal daripada harga penuh.
7. Tidak boleh ada dua add-on odontogram aktif atau dua checkout pending untuk klinik dan periode paket yang sama. Perpanjangan paket tidak otomatis memperpanjang add-on; owner dapat mencentangnya lagi pada checkout perpanjangan.

## 4. Data dan pembayaran

- Tambahkan katalog add-on yang dapat diberi kode stabil odontogram, harga bulanan Rp99.000, status aktif, dan histori harga/snapshot sehingga perubahan katalog hanya berlaku untuk pembelian baru.
- Tambahkan periode add-on per klinik yang merujuk periode paket utama, invoice/payment, waktu mulai/akhir, harga snapshot, sumber aktivasi, dan waktu pencabutan bila ada refund. Pertahankan semua periode historis.
- Perluas payments secara additive untuk jenis pembelian paket, add-on, dan gabungan. Simpan item invoice terpisah beserta kode, nama, jumlah, harga, dan periode snapshot. Pertahankan kompatibilitas invoice paket lama; migrasi/backfill item lama tanpa mengubah nominal atau status historis.
- Gunakan DokuPaymentService/DokuClient yang ada. Verifikasi signature, invoice, request ID, jumlah total, klinik, status, dan deduplikasi PaymentEvent sebelum aktivasi. Redirect browser hanya menampilkan status; tidak pernah mengaktifkan hak.
- Untuk invoice gabungan, buat periode paket dan periode add-on dalam satu transaksi setelah pembayaran sah. Untuk pembelian add-on saja, tautkan ke periode paket aktif yang benar. Bila salah satu validasi gagal, jangan aktifkan sebagian hak; simpan event penolakan untuk investigasi.
- Refund tervalidasi mencatat event dan mencabut hak edit add-on sejak waktu refund tanpa menghapus periode atau catatan medis. Pembayaran paket utama dan add-on harus tetap dapat dibedakan di riwayat, rekonsiliasi, dan audit.
- Batasi pembuatan checkout kepada owner klinik target. Platform Admin boleh mengelola katalog dan metadata komersial, tetapi tidak mendapat akses isi medis dari hak tersebut.

## 5. Entitlement dan alur klinis

- Satu pemeriksaan server-side menentukan hak edit odontogram dari klinik milik visit, periode paket Individual/Team berbayar yang aktif, periode add-on yang aktif pada waktu sekarang, serta tidak adanya pencabutan refund. Jangan mempercayai clinic_id, plan, harga, atau status dari form.
- Pada halaman visit, dokter yang ditugaskan melihat editor odontogram hanya saat hak edit aktif. Tanpa hak itu, odontogram historis yang sudah ada tampil hanya-baca; jika belum ada data, tampilkan keterangan singkat bahwa add-on belum aktif. Jangan menampilkan CTA pembelian di dalam isi medis.
- Form yang menyembunyikan odontogram bukan batas keamanan. SaveMedicalRecordRequest, CompleteVisitRequest, CorrectMedicalRecordRequest, dan VisitWorkflowService harus menolak payload odontogram yang dibuat atau diubah saat hak edit tidak aktif, termasuk request manual. Field odontogram yang tidak dikirim tidak boleh mengosongkan data lama.
- Saat add-on berakhir di tengah visit aktif, dokter tetap bisa menyimpan dan memfinalisasi RME General sesuai pengecualian pelayanan yang sudah ada. Odontogram draft tetap tersimpan dan dapat dibaca, tetapi perubahan berikutnya ditolak. Finalisasi/koreksi General tidak boleh menimpa odontogram tersebut.
- Koreksi odontogram pada RME final hanya diperbolehkan selama hak edit aktif dan tetap memerlukan alasan serta MedicalRecordRevision sebelum/sesudah. Otorisasi dokter atas pasien/visit tetap wajib pada semua jalur baca dan tulis.
- Tidak ada data odontogram, identitas pasien, atau isi medis pada halaman owner/platform, invoice, log pembayaran, notifikasi komersial, maupun broadcast publik.

## 6. Pengalaman owner dan dokter

- Landing page dan pemilihan paket menyebut “RME General termasuk” pada semua paket; Individual/Team menampilkan “Odontogram opsional +Rp99.000/klinik/bulan”. Free dan trial Team menjelaskan bahwa add-on belum tersedia.
- Checkout berbayar mempunyai pilihan add-on yang tidak tercentang otomatis. Ringkasan menampilkan harga paket, add-on, durasi, total, tanggal akhir, dan ketentuan tidak diperpanjang otomatis sebelum owner menuju DOKU.
- Setelah paket aktif, owner memiliki halaman add-on pada area subscription: status, harga, tanggal akhir paket, kutipan prorata, tombol checkout, invoice pending, dan riwayat. Bila paket tidak memenuhi syarat, jelaskan syarat tanpa membuat checkout.
- Pada perpanjangan paket, pilihan add-on muncul lagi secara eksplisit. Jika owner tidak memilihnya, periode baru hanya membawa RME General. Periode add-on lama tetap tercatat.
- Dokter melihat status “Odontogram aktif” atau “Histori odontogram hanya-baca” pada halaman pemeriksaan. Pesan tidak menyiratkan bahwa draft tersimpan otomatis; simpan RME tetap mengikuti mekanisme submit yang ada.
- Platform Admin dapat melihat status komersial add-on dan mengubah harga/aktif katalog untuk invoice baru. Halaman platform tidak menampilkan isi odontogram.
- Pertahankan gaya, komponen Blade/Livewire, dan palet ClinicOS; uji mobile, keyboard/fokus, serta state pending, gagal, aktif, berakhir, dan refunded.

## 7. Tahap pengerjaan

| Tahap | Pekerjaan | Syarat selesai |
| --- | --- | --- |
| 1. Domain dan migrasi | Katalog, periode add-on, item invoice, relasi, factory, seeder idempotent, serta kompatibilitas pembayaran lama. | Migrasi additive/rollback aman; histori paket dan odontogram lama utuh; harga penuh dan prorata teruji. |
| 2. Checkout dan siklus | Pilihan add-on saat checkout paket, pembelian setelah aktif, snapshot invoice, DOKU notification/reconciliation, deduplikasi, refund, dan audit. | Pending/redirect tidak memberi hak; sukses sah mengaktifkan tepat satu periode; invoice gabungan atomik; refund menghentikan edit. |
| 3. Gerbang klinis | Entitlement dari klinik visit, UI editor/histori, validasi request, pengamanan di VisitWorkflowService, finalisasi dan koreksi. | Free/trial/tanpa add-on tidak dapat menulis odontogram lewat UI atau request manual; General tetap berjalan; data lama terbaca sesuai policy. |
| 4. Antarmuka komersial | Copy paket/landing, checkout opt-in, halaman add-on owner, ringkasan invoice, status dan riwayat platform. | Harga dan periode jelas sebelum bayar; tidak ada tagihan otomatis; tidak ada isi medis pada layar komersial. |
| 5. Verifikasi rilis | Test unit/feature, build frontend, formatter, browser responsif, simulasi DOKU sandbox, dan review migrasi pada data berisi odontogram lama. | Semua test terkait lulus; skenario tenant, waktu, gagal bayar, refund, renewal, dan data lama terbukti; tidak memakai pembayaran nyata untuk test rutin. |

## 8. Matriks verifikasi wajib

- Free, Individual, dan Team tetap dapat membuat RME General sesuai hak dokter dan batas paket.
- Free, trial Team, paket expired/suspended, Individual/Team tanpa add-on, serta add-on berakhir/refunded tidak dapat membuat atau mengubah odontogram melalui draft, finalisasi, koreksi, atau request buatan.
- Dokter yang ditugaskan dapat membaca odontogram historis setelah add-on berakhir/refunded; dokter klinik lain, owner, resepsionis, pasien, dan Platform Admin tidak memperoleh akses medis.
- Pembayaran paket + add-on mengaktifkan keduanya tepat sekali; gagal bayar, jumlah salah, signature salah, event duplikat/terlambat, dan redirect browser tidak mengaktifkan hak.
- Pembelian add-on terpisah menempel pada periode paket yang tepat; tidak ada periode tumpang tindih atau pembayaran ganda saat dua checkout bersamaan.
- Harga penuh 1/6/12 bulan, prorata lintas bulan/tahun kabisat, zona waktu klinik, batas tanggal lokal, dan pembulatan rupiah sesuai ringkasan checkout.
- Renewal tanpa pilihan add-on menghentikan hak edit pada akhir periode lama; renewal dengan pilihan membuat periode baru tanpa mengubah data atau invoice lama.
- Jika add-on kedaluwarsa saat visit berlangsung, RME General tetap dapat diselesaikan, odontogram lama tidak tertimpa, dan audit koreksi final tetap lengkap.
- Jalankan test terfokus dengan database test terpisah, lalu npm run build dan pemeriksaan UI pada 375, 768, 1024, dan 1440 px. Verifikasi MySQL khusus untuk lock/konkurensi karena SQLite in-memory tidak membuktikannya.

## 9. Batas rencana

Rencana ini tidak mencakup auto-renewal, harga per dokter, paket dental baru, perubahan domain diagnosis/resep, billing pasien, aplikasi SPA, atau penghapusan data odontogram.

## 10. Hasil implementasi dan verifikasi

- Katalog harga, periode add-on, item invoice, checkout gabungan/terpisah, refund, entitlement server-side, tampilan owner/dokter/platform, dan copy paket sudah diterapkan. Invoice add-on yang dibayar setelah masa kutipan masuk status `review` tanpa memberi hak edit dan dapat direfund setelah ditinjau.
- Migrasi additive telah dijalankan pada MySQL lokal. Lima invoice lama tetap ada dan seluruhnya memiliki item paket hasil backfill. Seeder idempotent menghasilkan harga odontogram Rp99.000/bulan.
- Suite SQLite in-memory: 287 test lulus, 1.796 assertion. `npm run build`, `vendor/bin/pint --dirty --format agent`, dan `git diff --check` lulus. Landing diperiksa pada lebar 375, 768, 1024, dan 1440 px tanpa overflow horizontal.
- Belum ada pembayaran nyata atau uji DOKU sandbox end-to-end. Lock dan transaksi checkout belum dibuktikan dengan dua proses bersamaan pada MySQL; ini menjadi gate sebelum rilis produksi.
