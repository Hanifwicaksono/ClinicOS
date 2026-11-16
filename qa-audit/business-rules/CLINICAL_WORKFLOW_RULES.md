# Rancangan workflow klinis satu lokasi

EXISTING merujuk ER pada [register](EXISTING_BUSINESS_RULES.md); semua state/aksi baru adalah rekomendasi yang belum disetujui. A = appointment, Q = queue, V = visit, R = RME. Ini rancangan aplikasi/SOP untuk dievaluasi klinisi, bukan panduan diagnosis, terapi, atau pengganti keputusan penanggung jawab klinis.

## Alur normal existing

Booking valid membuat A BOOKED + Q BOOKED, belum ada V/R. Check-in Q WAITING, call Q CALLED. Dokter assigned memulai V IN_PROGRESS + R DRAFT + Q IN_PROGRESS, A tetap BOOKED. Simpan draft menaikkan versi R, tidak menyelesaikan pelayanan. Complete memfinalisasi R dan menyelesaikan V/A/Q dalam satu transaksi. Koreksi final menambah revisi beralasan, tidak reopen kunjungan.

Sumber: `BookingService::book`, `QueueWorkflowService::checkIn/call`, `VisitWorkflowService::start/saveDraft/complete/correct`, enums, policy dan requests. Struktur diagnosis/tindakan, allergy warning, odontogram persistent dan autosave kini terintegrasi UI. Dokumen 24 Oktober yang menyatakan semua itu belum ada tidak menjadi fakta current checkout.

## D-01 — Perubahan sebelum pemeriksaan

**PROPOSED:** cancel-and-rebook atomik dengan hubungan old/new dan alasan, oleh admin/rec. Release pertama mencakup BOOKED/WAITING/SKIPPED, tanpa Visit; CALLED harus ditarik melalui aksi skip yang sah sebelum perubahan. Jika dokter mulai terlebih dahulu, semua jalur perubahan menolak tanpa menyentuh V/R. Pengalihan setelah start masuk D-03, bukan D-01.

UI: detail booking → Ubah jadwal/dokter → pilih target service/doctor/session/date yang valid → preview nomor/check-in/harga/kuota → konfirmasi → satu hasil tertaut. Booking lama tetap ada, tidak diedit snapshotnya; target memakai tarif service terkini dengan explicit price-change confirmation. Target diusulkan Q BOOKED sehingga tidak mengklaim pasien sudah hadir di hari/sesi baru. Mempertahankan check-in pada perpindahan hari sama harus pilihan terpisah, bukan efek samping.

BookingService cancel generic tidak dilonggarkan untuk mengizinkan pasien melewati cutoff. Command replacement staf perlu guard tersendiri dan reuse aturan queue cancellation + booking dalam **outer transaction**, bukan dua HTTP actions. Target gagal penuh/tidak aktif/expired → rollback old cancellation dan notifikasi. Replay UUID mengembalikan replacement yang sama; changed intent ditolak. AC: double-click, dua petugas, start-vs-move, stale preview harga dan target quota1 menghasilkan maksimal satu pengganti sah, old history utuh.

Rekomendasi carry eligibility booking lama yang sudah grandfather harus disetujui pada D-01 dan terikat satu replacement aktif. Existing book/start belum mewariskannya otomatis; jangan backdate created_at atau menganggap pembatalan lama membebaskan quota started visits.

## D-02 — Dokter berhalangan, libur dan perubahan jam

**PROPOSED:** exception clinic/doctor berbasis interval lokal `[mulai,selesai)` dan actor/reason; weekly template tetap. Booking baru memeriksa exception dalam transaksi, bukan hanya menyembunyikan slot di browser. Menghapus exception memulihkan eligibility target, tidak menghidupkan booking yang telah dibatalkan.

Appointment current berbasis tanggal+sesi, bukan jam individual. Rekomendasi release awal: partial blackout yang overlap sesi **memblok seluruh sesi itu untuk booking baru**, dan impact preview menampilkan semua booking sesi tersebut sebagai kandidat terdampak tanpa menebak jam pelayanan pasien. Alternatif split sesi/kuota menjadi slot waktu lebih granular adalah scope keputusan D-02 lain; tidak auto-split historical schedule atau menggandakan quota.

UI admin: tanggal/rentang + dokter/clinic → preview dampak yang memisahkan booked/waiting/called/in-progress → simpan exception → daftar tugas penanganan → masing-masing pasien dihubungi/ditangani melalui D-01 atau D-03. Tidak ada mass cancel/shift otomatis. In-progress diberi label perlu keputusan klinis; blackout tidak menjadikan pelayanan selesai/tidak hadir.

Existing doctor/day/start/end schedule berhistori dibekukan DFU-02. Old inactive template masih dihitung overlap oleh validator. Sebelum weekly replacement, pilih effectivity/versioning dan overlap hanya antar template yang berlaku; jangan membuka edit schedule lama agar appointment historis tampak berpindah jam. Perubahan timezone juga perlu preview karena instant/cutoff dan batas bulan berubah, bukan rewrite tanggal historis otomatis.

AC: boundary interval, clinic timezone, exception-vs-new-book, dua exception overlap, restore, target dokter nonaktif, dan appointment yang sudah ada tidak berubah saat save setting/exception.

## D-03A — Pause/resume dan pelayanan berhenti

**PROPOSED, wajib persetujuan klinis:** bedakan dua situasi:

- Pause sementara dalam episode yang sama: V/Q PAUSED (state baru), R tetap DRAFT dengan versi. Dokter assigned dapat resume menjadi IN_PROGRESS. Rekomendasi awal mempertahankan active-slot reservation agar tidak ada dua pasien yang dianggap sedang diperiksa pada sesi sama; pelepasan slot saat pause harus keputusan eksplisit lain. Durasi pause dicatat agar ETA tidak menghitung waktu jeda sebagai durasi tindakan biasa.
- Pelayanan benar-benar berhenti: V/Q/A INTERRUPTED (state baru yang berbeda dari COMPLETED/NO_SHOW/CANCELLED); R yang belum final tetap tersimpan dan ditandai belum final/read-only menurut SOP. Tidak memalsukan complete demi melepas antrean. Penanggung jawab klinis menentukan siapa boleh mengesahkan ketika dokter asal tidak tersedia; admin/rec hanya mengajukan penanganan operasional, bukan mengarang isi RME.

Owner harus memilih state mapping ini secara eksplisit sebelum migration atau enum dibuat. Autosave lama harus berhenti/409 setelah status berubah, tanpa kehilangan draft browser; resume menerima versi terbaru, bukan menimpa draft lain. Final RME tidak boleh diubah menjadi draft atau dihapus demi recovery.

Terminal interrupted juga memerlukan **catatan penghentian oleh klinisi berwenang** dan cara menyelesaikan dokumentasi parsial. Isi minimum, penandatanganan/finalitas catatan parsial dan koreksinya harus disahkan klinisi; draft yang terkunci tidak boleh dianggap seluruh kewajiban dokumentasi sudah selesai. Paket D-03 belum ready sampai bagian ini dipilih. Terms akun/SaaS bukan catatan persetujuan tindakan medis atau persetujuan handoff pasien.

Subscription: existing exception hanya mengenali `VisitStatus::InProgress` dan route tertentu. Jika PAUSED dipilih, resume/finish harus diputuskan sebagai kelanjutan pelayanan lama, bukan terblokir akibat perbedaan enum. Ini tidak mencabut account-security/tenant/assignment rules. Akun dokter inactive tetap tidak boleh dipulihkan secara implisit hanya untuk menyelesaikan visit.

AC: pause-vs-complete, resume dua tab, expiry saat pause, inactive doctor, queue active-call predicate, terminal interruption vs stale autosave, reason/actor/history dan metrik selesai tidak bertambah palsu.

## D-03B — Handover setelah pemeriksaan dimulai

**Rekomendasi untuk disetujui:** tutup episode lama secara jelas sebagai interrupted dan buat appointment+visit lanjutan tertaut setelah dokter penerima menerima handoff. Unique `visits.appointment_id` berarti tidak dapat sekadar membuat visit kedua pada appointment lama. Author, isi, foto dan revision lama tidak disalin sebagai tulisan dokter penerima. Ringkasan handoff yang diizinkan disimpan/ditautkan dengan author, waktu dan acknowledgement.

Alternatif satu visit dengan assignment history mungkin, tetapi seluruh read/write policy saat ini mengikuti doctor_id tunggal dan belum memisahkan author dari responsible doctor. Alternatif tersebut memerlukan desain lebih luas; tidak aman diselesaikan dengan UPDATE doctor_id.

Perlu keputusan: scope old RME yang boleh dilihat penerima, siapa klinisi reviewer, persetujuan/komunikasi pasien, penolakan penerima, tanggal/sesi target, serta quota/subscription continuation. Rekomendasi komersial yang perlu persetujuan terpisah adalah satu recovery entitlement terikat episode lama yang sudah sah dimulai, agar tidak ada tagihan/kuota ganda atas recovery yang sama. **Belum ada entitlement ini di source**; tanpa keputusan, existing new-visit quota tetap berlaku dan paket ini belum ready.

Tidak boleh ada blanket bypass untuk semua booking saat expired. Dental edit juga tidak otomatis ikut entitlement general; bila add-on berakhir, data historis tetap terbaca tetapi input dental baru tetap tunduk keputusan packaging/continuity. Dokter penerima yang dinonaktifkan sebelum accept/commit membatalkan handoff secara aman, bukan mengubah author lama.

## RME, alergi dan hasil pelayanan

Existing expected R version dan allergy hash harus dibaca ulang untuk save/final/correction. Tidak auto-merge SOAP/foto setelah 409. Dokter membandingkan konten sebelum submit ulang. Numeric vital validation adalah rentang input software; tidak membuktikan kelayakan klinis untuk semua usia/kondisi dan tidak boleh dijadikan alert terapi otomatis tanpa keputusan klinis.

**D-13:** usulkan immutable allergy lineage dengan satu current per lineage dan expected-parent. Dua revisi parent sama: satu commit, lainnya409 untuk clinical review; tidak last-write-wins, tidak menghapus assertion lama. Dua alergi berbeda boleh tetap aktif. Penentuan kapan dua assertion merupakan revisi kondisi yang sama tidak boleh hanya fuzzy substance matching. Untuk legacy tanpa lineage, jangan menebak rantai atau mematikan critical warnings otomatis.

**D-07:** mulai resep final printable/issued version dengan issuer/record version/waktu, dan ringkasan pasien allowlist terpisah setelah klinis menyetujui fields. Draft tidak diterbitkan sebagai final. Reprint mempertahankan versi; koreksi menghasilkan superseded document yang terlacak, bukan menulis ulang cetakan lama. Hak pasien memperoleh dokumen yang dipilih tidak sama dengan akses portal penuh ke SOAP/foto/revisi.

**D-04:** follow-up task dibuat dokter dengan instruksi pasien terpilih, penanggung jawab dan due window. Board staf hanya metadata operasional. BOOKED task ditautkan ke appointment; baru COMPLETED setelah visit terkait benar-benar completed, bukan sekadar booking/check-in. Cancel/no-show diarahkan review/reopen sesuai pilihan owner, tanpa mengganti clinical plan diam-diam. Task tidak memesan slot atau memotong quota sebelum booking benar-benar terjadi.

## Otoritas di luar source

Jika klinik beroperasi di Indonesia, JDIH Kemenkes mencatat PMK24/2022 tentang Rekam Medis berstatus berlaku; naskahnya menempatkan keamanan data serta pemberian hak akses dalam kebijakan fasilitas. Ini alasan meminta penanggung jawab klinis menilai dokumen, koreksi, handoff dan akses—bukan menyimpulkan ClinicOS sudah patuh hanya dari policy PHP. [JDIH resmi](https://jdih.kemkes.go.id/documents/peraturan-menteri-kesehatan-nomor-24-tahun-2022), [naskah resmi](https://jdih.kemkes.go.id/common/dokumen/2022permenkes024.pdf). Diperiksa15 November2026; telaah ini tidak menetapkan aturan retensi, tanda tangan atau pelepasan data hukum secara menyeluruh.

Tidak ada alur baru pada dokumen ini yang boleh dijalankan pada pasien sebelum kebijakan relevan disetujui dan implementasi/regression diselesaikan.
