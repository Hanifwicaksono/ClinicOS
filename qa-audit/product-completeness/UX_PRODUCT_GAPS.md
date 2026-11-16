# UX produk dan task-based review

Tanggal audit: **14 November 2026**. Audit produk read-only; tidak ada implementasi/perbaikan. Fakta source/UI dipisahkan dari inferensi kebutuhan dan rekomendasi. Semua UI menggunakan data dummy pada SQLite terisolasi. 

## Metode dan hasil aktual

**28 task review agen OBSERVED**, **0 task aktif NOT VERIFIED**; 29 catatan total termasuk satu percobaan harness tersupersede. Ini adalah observasi ketersediaan kontrol dan jalur tugas, **bukan 28 functional PASS**, bukan usability study manusia atau angka task success rate. Sebagian tugas sengaja mencari kemampuan yang ternyata tidak ada. HTTP200 tidak dipakai sebagai bukti kelengkapan bisnis.

Chrome headless, viewport desktop1440×1000; source dan screenshot dibaca untuk task terkait. Tidak mengulang seluruh matriks mobile/keyboard/DOM yang sebelumnya PASS; native keyboard, screen reader, perangkat sentuh, istilah awam dan waktu penyelesaian manusia belum dinilai ulang. Empty activity dan hasil pencarian kandidat adalah probe tambahan, bukan load test.

| Task | Persona | Tujuan review | Bukti aktif |
| --- | --- | --- | --- |
| UI-01 | Clinic Owner | Tentukan titik mulai, informasi dan menu persona Clinic Owner | [OBSERVED](evidence/screenshots/UI-01.png) |
| UI-02 | Clinic Admin | Tentukan titik mulai, informasi dan menu persona Clinic Admin | [OBSERVED](evidence/screenshots/UI-02.png) |
| UI-03 | Receptionist | Tentukan titik mulai, informasi dan menu persona Receptionist | [OBSERVED](evidence/screenshots/UI-03.png) |
| UI-04 | Doctor | Tentukan titik mulai, informasi dan menu persona Doctor | [OBSERVED](evidence/screenshots/UI-04.png) |
| UI-05 | Patient | Tentukan titik mulai, informasi dan menu persona Patient | [OBSERVED](evidence/screenshots/UI-05.png) |
| UI-06 | Platform Admin | Tentukan titik mulai, informasi dan menu persona Platform Admin | [OBSERVED](evidence/screenshots/UI-06.png) |
| UI-07 | Receptionist | Cari jalur koreksi dokter/layanan atau reschedule booking yang sudah dikonfirmasi | [OBSERVED](evidence/screenshots/UI-07.png) |
| UI-08 | Receptionist | Cari penanganan dokter tidak hadir dan handoff antrean | [OBSERVED](evidence/screenshots/UI-08.png) |
| UI-09 | Doctor | Cari penghentian/transfer pemeriksaan yang sudah IN_PROGRESS | [OBSERVED](evidence/screenshots/UI-09.png) |
| UI-10 | Doctor | Cari dokumen pasien, tindak lanjut dan langkah setelah finalisasi | [OBSERVED](evidence/screenshots/UI-10.png) |
| UI-11 | Patient | Mulai kunjungan baru tanpa mengingat URL klinik | [OBSERVED](evidence/screenshots/UI-11.png) |
| UI-12 | Clinic Owner | Atur hari libur/perubahan jam khusus dan akses status aktif klinik | [OBSERVED](evidence/screenshots/UI-12.png) |
| UI-13 | Clinic Owner | Cari pengecualian jadwal berbasis tanggal dan dampak booking existing | [OBSERVED](evidence/screenshots/UI-13.png) |
| UI-14 | Platform Admin | Lihat commercial history, koreksi grant dan kontrol lifecycle tenant | [OBSERVED](evidence/screenshots/UI-14.png) |
| UI-15 | Platform Admin | Bedakan refund manual, invoice dan pengembalian uang provider | [OBSERVED](evidence/screenshots/UI-15.png) |
| UI-16 | Clinic Owner | Cari bukti perubahan yang dapat direkonsiliasi atau diekspor | [OBSERVED](evidence/screenshots/UI-16.png) |
| UI-17 | Receptionist | Cari penyelesaian administrasi layanan/kasir pada pekerjaan resepsionis | [OBSERVED](evidence/screenshots/UI-17.png) |
| UI-18 | Receptionist | Tinjau seluruh kandidat ketika nomor keluarga dimiliki lebih dari8 pasien | [OBSERVED](evidence/screenshots/UI-18.png) |
| UI-19 | Patient | Cari informasi mendekati giliran, reminder appointment dan instruksi setelah pelayanan | [OBSERVED](evidence/screenshots/UI-19.png) |
| UI-20 | Clinic Owner | Pahami provisioning akun, peran yang bisa dibuat dan setup akses staf | [OBSERVED](evidence/screenshots/UI-20.png) |
| UI-22 | Receptionist / Patient | Amati arti pasien nonaktif pada booking staf dibanding self-service | [OBSERVED](evidence/screenshots/UI-22.png) |
| UI-21-R2 | Guest | Informasi klinik dan titik mulai pengunjung, setelah koreksi identitas guest pada harness | [OBSERVED](evidence/screenshots/UI-21-R2.png) |
| UI-23 | Clinic Owner | Bedakan riwayat add-on owner yang tersedia dari timeline periode utama Platform yang belum tersedia | [OBSERVED](evidence/screenshots/UI-23.png) |
| UI-24 | Clinic Owner | Tinjau empty activity dashboard pada tenant fixture kosong; bukan onboarding klinik tanpa konfigurasi | [OBSERVED](evidence/screenshots/UI-24.png) |
| UI-25 | Receptionist | Tinjau empty activity dashboard pada tenant fixture kosong; bukan onboarding klinik tanpa konfigurasi | [OBSERVED](evidence/screenshots/UI-25.png) |
| UI-26 | Doctor | Tinjau empty activity dashboard pada tenant fixture kosong; bukan onboarding klinik tanpa konfigurasi | [OBSERVED](evidence/screenshots/UI-26.png) |
| UI-27 | Patient | Tinjau empty activity dashboard pada tenant fixture kosong; bukan onboarding klinik tanpa konfigurasi | [OBSERVED](evidence/screenshots/UI-27.png) |
| UI-28 | Platform Admin | Triage ekspresi Alpine dan feedback tombol grant saat submit pending; request dicegat sebelum backend | [OBSERVED](evidence/screenshots/UI-28.png) |


Catatan UI-21 awal: helper menerima `Guest` padahal mengharuskan `guest`, lalu mencoba login tanpa akun. Timeout itu **kesalahan harness**, diperbaiki pada runner audit; [UI-21-R2](evidence/screenshots/UI-21-R2.png) dan JSON supersedes disimpan. Tidak dinyatakan bug produk. Catatan semua percobaan triage UI-28: [batas harness](evidence/triage-harness-notes.json).

## Analisis per persona

| Persona | Titik mulai / urutan kerja (FAKTA) | Kejelasan / recovery (INFERENSI + REKOMENDASI) | Gap yang sama |
| --- | --- | --- | --- |
| Owner | Dashboard dan menu administrasi/subscription terlihat (UI-01/23). | Kalender exception, laporan, histori/perbaikan komersial perlu langkah lintas halaman; gunakan workflow terpandu setelah policy. | PG-02,08,09,10,11,15,16 |
| Clinic Admin | Dashboard dan resource administrasi tersedia (UI-02). | Beda hak owner/admin perlu dijelaskan saat limit; tidak perlu custom editor tanpa kasus delegasi. | PG-02,08,15 |
| Receptionist | Dashboard mengikuti kedatangan/antrean (UI-03/08); active filter/cursor sudah CCL-07. | Booking salah perlu cancel-rebook; 8 dari 10 kandidat tanpa penanda lengkap; pemulihan harus dekat sumber masalah. | PG-01,02,06,17 |
| Doctor | Start/resume/editor/final dan status save tersedia (UI-04/09/10). | Tidak ada jalan interupsi/handover; resep final belum dokumen; versi allergy perlu interpretasi eksplisit. | PG-03,04,07,13 |
| Patient | CTA booking baru/daftar klinik terkait/kode tersedia (UI-05/11). | First-use tanpa kode belum divalidasi manusia; reschedule/reminder/follow-up tetap manual. | PG-01,04,06,07,12 |
| Platform Admin | Menu tenant/katalog/payment dan current effective status ada (UI-06/14/15). | Histori tersebar; istilah refund harus jelas pencatatan vs dana dan akses; perlu preview sebelum koreksi. | PG-09,10,11,14,16 |


## Bug UI baru PG-18: submit grant

FAKTA: dua reload detail tenant menghasilkan Alpine `Invalid or unexpected token`; DOM masih berisi `submitting || @js($subscriptionPrices->isEmpty())`. Langkah reproduksi: login Platform → Tenant → detail tenant → pilih paket dan alasan → klik Berikan akses. Submit observer mencatat `submitting=true` tetapi `button_disabled=false`. Satu POST dicegat dan dibatalkan sebelum backend; ini membuktikan state UI, bukan hasil transaksi provider/backend. [Bukti console/DOM](evidence/page-error-triage.json), [screenshot sebelum submit](evidence/screenshots/UI-28.png). Tidak ada source diperbaiki.

Percobaan awal inspeksi pending menunggu navigasi yang sengaja ditahan lalu timeout; bukan bug aplikasi tambahan. Observer event dipakai pada run akhir, request diblokir dan bukti final dipisahkan dari percobaan harness. Idempotency backend DFU-12 tidak dibuka kembali. AC dan akar masalah lengkap pada PG-18.

## Recovery, istilah dan informasi

- **Status proses tersedia:** booking/queue/visit, save/conflict, subscription dan service value telah memiliki state/copy; CCL expired-session vs offline, error form, label dan mobile DFU tetap dipertahankan. Tidak membatalkan bukti fix hanya karena laporan baseline masih menyebut FAIL.
- **Langkah berulang:** cancel+rebook, menyalin laporan, instruksi kontrol dan distribusi password awal adalah perpindahan kerja nyata pada source/UI. Rekomendasi menutup handoff terlebih dahulu, bukan menambah menu untuk setiap model.
- **Informasi tersembunyi:** full main-period timeline Platform dan kandidat setelah hasil ke-8. Riwayat add-on owner sudah tersedia (UI-23), sehingga tidak dihitung hilang lagi.
- **Istilah:** label estimasi nilai layanan sudah menjelaskan bukan kas; manual refund harus dijelaskan sebagai pencatatan dan efeknya pada add-on/paket utama. Tidak ada studi pemahaman istilah dengan partisipan.
- **URL:** Patient sudah punya CTA booking dan clinic_code. Directory publik bukan syarat memperbaiki DFU-11 lagi; kebutuhan pengguna baru tanpa kode adalah pertanyaan first-use, belum hasil studi.

## Empty/loading dashboard: penilaian ulang G47

UI-24/25/26/27 menunjukkan empty activity Owner/Rec/Doctor/Patient dengan pesan dan CTA nyata. Fixture memiliki konfigurasi/staf, bahkan Free over-seat banner pada beberapa persona; **bukan** klinik baru tanpa service/jadwal, bukan Admin non-owner empty yang dibuktikan terpisah. Platform zero-tenant tetap **NOT VERIFIED browser**: template [resources/views/platform/dashboard.blade.php:76](../../resources/views/platform/dashboard.blade.php#L76) ada, clone memuat tenant, tidak ada aksi UI aman untuk menghilangkan semua tenant. Perlu initial fixture zero-tenant tersendiri bila akan menutup seluruh G47, bukan menghapus DB.

Blade SSR tidak memiliki kontrak “loading dashboard aplikasi” terpisah; loading tombol/refetch dan reconnect berbeda. Skeleton baru bukan kebutuhan wajib tanpa bukti masalah. Kontrak loading lambat/error per task dan studi manusia perlu dipilih sebelum menilai kekurangan desain. Hasil review ini menutup sebagian pertanyaan produk G47, **tidak mengubah seluruh G47 menjadi PASS**.
