# Keputusan produk yang diperlukan

Tanggal audit: **14 November 2026**. Audit produk read-only; tidak ada implementasi/perbaikan. Fakta source/UI dipisahkan dari inferensi kebutuhan dan rekomendasi. Semua UI menggunakan data dummy pada SQLite terisolasi. 

Tidak ada keputusan baru yang ditetapkan diam-diam. Tabel berikut mendokumentasikan alternatif dan konsekuensi; pihak pada kolom pemilik adalah **usulan penanggung jawab keputusan**, bukan bukti persetujuan manusia. Prioritas pada backlog berlaku setelah scope/aturan terkait dipilih.

| Keputusan | Gap / kebutuhan | Alternatif | Konsekuensi | Pemilik keputusan (usulan) | Batas / keluaran keputusan |
| --- | --- | --- | --- | --- | --- |
| D-01 | PG-01 | Cancel-rebook terpandu + supersedes vs edit booking transactional | Pertama mempertahankan fakta lama tetapi kode/queue baru; kedua menjaga identitas booking namun memerlukan revision/kuota/harga rules lebih kompleks. | Owner/operasional + product | Pilih status yang boleh, otoritas, notifikasi, biaya dan nomor baru. Tidak mengubah IN_PROGRESS. |
| D-02 | PG-02 | Simpan setting + daftar dampak vs tolak perubahan bila ada booking; exception tanggal vs weekly edit | Pertahankan CCL-05 sebagai dasar. Mass cancel/shift memerlukan izin per appointment; menolak semua perubahan membatasi recovery. | Owner/operasional | Tidak ada perpindahan appointment historis diam-diam; define timezone dan effective date. |
| D-03 | PG-03 | Interrupted terminal vs pause/resume vs handover dokter | Mempengaruhi tanggung jawab klinis, akses, locking sesi, laporan dan arti completion. Tidak bisa diselesaikan dengan cancel queue biasa. | Penanggung jawab klinis + product | Definisikan aktor, alasan, draft/foto, pasien dan pelepasan sesi. |
| D-04 | PG-04 | Instruksi kontrol + tautan booking vs task follow-up tertutup | Tautan lebih ringan; task butuh owner/status/escalation dan informasi nonmedis bagi resepsionis. | Doctor + operasional + Patient | Tentukan informasi yang disetujui dibagi dan siapa memantau. |
| D-05 | PG-05 | Tetap administrasi klinis + kasir eksternal vs billing pasien minimal dalam produk | Pilihan pertama sah untuk MVP terbatas dengan batas transparan; pilihan kedua memenuhi RPP lebih luas dan butuh ledger/receipt/refund/pemisahan role. | Pemilik produk + Owner/finance | Tidak menyebut service value sebagai cash. Tentukan discount/partial/refund sebelum schema. |
| D-06 | PG-06 | Nonaktif = blok booking semua kanal vs arsip dengan self-service yang diperbolehkan | Blok memerlukan pemulihan berotorisasi; arsip perlu copy dan visibility berbeda. Jangan otomatis reactivate/link identitas. | Owner/operasional + product | Kontrak UI-22 lintas kanal harus eksplisit sebelum menyebut bug atau mengubah perilaku. |
| D-07 | PG-07 | Print resep saja vs ringkasan klinis terpilih / dokumen lain | Cakupan/format/penerima dan tanda otorisasi berbeda; full SOAP tidak otomatis dokumen Patient. | Doctor/penanggung jawab klinis + product | Batas isi, final-only, versi, penerbit dan penerima; aturan formal memerlukan validasi terpisah. |
| D-08 | PG-08 | Satu CSV operasional vs laporan PDF/reconciled finance | CSV ringkas dapat berdiri sendiri; finance menunggu ledger jika mengukur kas. | Owner/admin | Definisi metrik/status/timezone dan field export tanpa medis. |
| D-09 | PG-09 | Viewer Platform seluruh periode + owner subset vs hanya support read-only | Akses lebih mandiri vs beban dukungan; histori invoice/add-on existing tetap dipakai. | Product + Platform/Owner | Field source/period/payment/revoked dan tenant privacy. |
| D-10 | PG-10 | Revoke grant tertentu vs adjustment schedule/credit/refund terpisah | Efek pada sisa periode, dana, add-on dan invoice berbeda. Global Free bukan pengganti keputusan. | Commercial owner + Platform + product | Kontrak dana vs recorded, paket utama vs add-on, future valid periods dan approval/audit. |
| D-11 | PG-11 | Dukungan manual terkendali vs UI transfer/suspend/closure yang dibutuhkan | Manual menekan scope tetapi perlu prosedur; UI butuh pembuktian kepemilikan dan dampak kontrak/retensi. | Owner + Platform + product | Tidak hard delete atau impersonation; hak baca dan IN_PROGRESS tetap dipertimbangkan. |
| D-12 | PG-12 | Near-turn in-app vs reminder terjadwal + kanal eksternal | Lead time dan threshold berbeda; kanal luar memerlukan consent, biaya, delivery dan dedup. | Operasional + Patient/product | Tentukan near-turn ≠ called; stale appointment update/retry. |
| D-13 | PG-13 | Multi-assertion sah vs satu current per lineage dengan conflict resolution | Pertama perlu UI interpretasi; kedua perlu expected-parent/merge dan supersede tanpa menghapus versi. | Penanggung jawab klinis + Doctor | Tidak last-write-wins atau rewrite RME final; tentukan critical banner hasil konflik. |
| D-14 | PG-14 | Read-only diagnosis dulu; manual approval per record vs repair terbatas per kategori | Tidak ada bukti jumlah data pengguna bermasalah. Repair menyeluruh tanpa keputusan berisiko mengubah histori. | Pemilik data + klinis/commercial sesuai jenis | Izin terpisah, dry-run, snapshot/audit, idempotency; tidak seed ulang. |
| D-15 | PG-15 | Invitation set-password; delegated Clinic Admin perlu atau owner cukup | Invitation meminimalkan distribusi password manual; admin tambahan menambah privilege yang harus dapat dicabut. | Owner + product | Jangan membuat generic permission editor hanya untuk provisioning admin. |
| D-16 | PG-16 | Ubah copy/feature metadata vs enforce paywall yang dinyatakan | Copy paling kecil jika hak sekarang memang dimaksudkan; enforcement mengubah kontrak Free dan perlu transisi. | Pemilik produk/commercial | Tidak menutup pelayanan IN_PROGRESS atau menghapus data paid lama. |
| D-17 | Custom permission historis G68 | Role tetap + delegasi yang spesifik vs custom role editor | Lima role sudah bekerja; granular permission belum kebutuhan operasional tervalidasi. PR-01 tidak bergantung keputusan ini. | Owner/product + security | Default pertahankan role tetap. Add role/action matrix hanya jika kasus nyata dibuktikan. |
| D-18 | Kiosk/display historis G29 | Display publik saat ini vs kiosk mode/filter/perangkat | Kebutuhan device, visibilitas dokter dan signage berbeda; public queue sudah nonmedis. | Operasional klinik/product | Default display ada cukup; uji perangkat/kontrol hanya jika kebutuhannya disepakati. |
| D-19 | Scope opsional / post-MVP | Directory/branches/Edge/drug catalog/inventory/claims vs fokus satu klinik web | Setiap domain menambah model dan tanggung jawab, bukan otomatis syarat lengkap MVP. RPP luas bukan izin implementasi. | Pemilik produk + klinik target | Butuh kasus penggunaan dan nilai tervalidasi sebelum backlog implementasi. Native/React tidak masuk scope saat ini. |
| D-20 | First-use / G47 | Definisikan tugas onboarding/loading per role dan studi manusia | Agent review tidak mengukur pemahaman pengguna. Platform empty perlu initial fixture; skeleton bukan otomatis solusi. | Product/UX + partisipan nyata | Uji klinik tanpa setup, admin non-owner empty, zero-tenant platform dan task tanpa kode jika relevan; jangan hapus user DB. |


## Penilaian ulang keputusan historis

| Historis | Hasil audit saat ini | Disposisi |
| --- | --- | --- |
| G59 commercial viewer | PARTIAL: invoice/current status + owner addon history ada; full Platform main-period timeline belum. | PG-09 / D-09 |
| G63 duplicate grant lama / single revoke | PRODUCT DECISION REQUIRED: pencegahan baru DFU-12 sudah selesai, recovery berbeda. | PG-10 / D-10; tidak menghitung duplikat pengguna |
| G29 kiosk/display | PRODUCT DECISION REQUIRED, bukan display hilang. | D-18; P3 opsional |
| G68 custom permission | PRODUCT DECISION REQUIRED, role/policy yang ada tetap. | D-17; PR-01 track terpisah |
| G47 empty/loading | PARTIAL bukti tenant; global Platform empty dan clean-onboarding belum browser. | D-20; jangan tandai PASS penuh |
| Jam operasional CCL-05 | Booking baru sudah mengikuti jam; historical unchanged. Exception/date-impact belum. | PG-02 / D-02, bukan bug CCL dibuka ulang |
| Versi alergi dua editor | Kedua versi tersimpan; resolusi lineage/current belum diputuskan. | PG-13 / D-13, tidak mengklaim lost update |
| Data legacy inkonsisten | Pencegahan tersedia; data pengguna tidak diperiksa; diagnosis/repair belum terpandu. | PG-14 / D-14 |
| PR-01 socket revocation | OPEN risiko keamanan existing; tidak diuji ulang dan tidak diperbaiki. | Terpisah dari product gaps |


## Scope yang tidak diperluas

Tidak menambah React/SPA/native app, custom permission, payment pasien, provider baru, auto-renewal, AI, multibranch atau Edge hanya karena menarik. Billing pasien, dokumen dan lifecycle bisa penting pada scope klinik target, tetapi pilihan format, alur dan kewajibannya belum disepakati. Validasi yurisdiksi/kebijakan dokumen/retensi dilakukan terpisah jika akan diputuskan, tanpa klaim kepatuhan dalam audit ini.
