# Human acceptance and clinic-day simulation

Alur hari klinik terkompresi melalui UI: **PASS**. Gate penerimaan menyeluruh: **FAIL karena PR-01**. Acceptance oleh manusia klinisi dan shift delapan jam nyata: **NOT TESTED**.

## Skenario normal melalui Playwright

Satu klinik sintetis baru dengan seluruh role tersimpan: Clinic Admin, Receptionist, Doctor, Patient dan Platform Admin, ditambah owner persona pada role Clinic Admin. Enam konteks browser berjalan bersamaan. Tidak mengubah database lewat SQL untuk memperbaiki atau melompati langkah workflow.

| Tahap | Aksi UI yang benar-benar dilakukan | Status |
| --- | --- | --- |
| Pembukaan | Admin membuka jadwal; owner meninjau subscription; platform admin membuka tenant metadata | PASS |
| Booking pasien | Patient login, memilih dokter/layanan/sesi dengan keyboard, mengisi form publik, menerima konfirmasi dan nomor antrean | PASS |
| Kedatangan | Resepsionis menekan Check-in dan Panggil melalui tombol queue UI | PASS |
| Kesalahan manusia | Pasien dilewati, dikembalikan ke WAITING, lalu dipanggil lagi melalui UI | PASS |
| Pemeriksaan | Dokter menekan Mulai periksa, membuka RME, mengisi subjektif | PASS |
| Validasi | Upaya finalisasi tidak lengkap tetap DRAFT; dokter menekan Kembali periksa, melengkapi assessment/rencana | PASS |
| Finalisasi | Finalisasi UI membuat appointment/queue/visit COMPLETED dan RME FINAL | PASS |
| Penutupan | Pasien membuka appointment miliknya; admin dan owner meninjau dashboard; tepat satu appointment/queue/visit tersimpan | PASS |

Durasi alur otomatis sekitar 30,6 detik. Ini kompresi urutan aktivitas pada waktu sistem yang sama, **bukan delapan jam operasional atau pengujian buka/tutup jam klinik**. Fixture menggunakan jam 00:00-23:59 untuk menjalankan alur pada waktu audit lokal. Tidak mengklaim fitur closing cash/kasir, receipt, atau export yang belum tersedia.

Bukti: [clinic-day.json](evidence/clinic-day.json), [day.mjs](tests/day.mjs). Nol page error JavaScript dan nol developer data repair selama workflow. Run awal yang tidak menutup dialog validasi sebelum kembali mengedit diarsipkan; run final menggunakan tombol Kembali periksa yang memang tersedia.

## Skenario pendamping

Suite [browser.mjs](tests/browser.mjs) menjalankan **14 kasus: 12 PASS, 2 FAIL**. Tambahan clinic-day di atas **1 PASS**; total Playwright **13 PASS, 2 FAIL, 0 BLOCKED**.

| Skenario | Status |
| --- | --- |
| Sembilan konteks browser login, lima role dan owner persona | PASS |
| Responsive 375/768/1024/1440 px; native keyboard Escape dan focus return pada drawer | PASS |
| Admin membuat staf, inbox menerima verifikasi, signed verification, login | PASS |
| Upload foto, draft, finalisasi dan koreksi beralasan dengan revision history | PASS |
| Owner/admin/receptionist/patient/platform/dokter lain/tenant lain ditolak pada RME dan foto private | PASS |
| Reset password mandiri dan admin; sesi lama ditolak; fresh login sukses | PASS |
| Perubahan email: verified direset, link lama ditolak, link baru dari SMTP berhasil | PASS |
| Sesi berakhir saat autosave | PASS: server 401, teks dipertahankan, tidak ada write, login ulang dan finalisasi UI berhasil |
| Dua resepsionis/pasien berebut kuota dan dua sesi dokter menulis bersamaan | PASS pada harness MySQL independen; bukan sekadar render browser |
| Penonaktifan staf dan pencabutan role ketika socket masih aktif | FAIL pada socket; HTTP berikutnya sudah menolak |

Playwright memakai Chrome nyata, bukan DOM emulator atau keyboard event JavaScript pengganti. Beberapa aksi suite pendamping memakai request authenticated browser context untuk mengisolasi protokol; clinic-day khusus di atas memakai tombol/form UI untuk booking, check-in, call, skip/return dan start/finalize.

## Yang belum diterima

**BLOCKED:** perubahan paket yang benar-benar diaktifkan oleh callback DOKU saat operasional; checkout owner dan reconciliation pending sudah diuji, tetapi provider callback belum dapat menjangkau receiver audit.

**NOT TESTED:** sign-off dokter/resepsionis manusia, shift nyata, screen reader, semua kombinasi browser/mobile OS, proses klinik ketika banyak pasien datang berjam-jam, cetak dokumen, billing pasien, serta semua keadaan error eksternal. Load singkat dan race MySQL melengkapi busy-condition evidence, tidak menggantikan penerimaan manusia.

```powershell
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/browser.mjs
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/day.mjs
```

Rekomendasi: perbaiki PR-01, ulangi failure scenario tanpa menghapus test, kemudian lakukan sesi UAT bersama staf klinik di staging dengan volume dan perangkat yang akan dipakai.
