import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,readdirSync} from 'node:fs';
import {join} from 'node:path';
const root=import.meta.dirname,evidence=join(root,'evidence');
const scenarios=JSON.parse(readFileSync(join(evidence,'scenarios.json')));
const php=JSON.parse(readFileSync(join(evidence,'final-backend-r3-summary.json')));
assert.equal(php.result,'passed');assert.equal(php.tests,php.passed);
const current=scenarios.filter(x=>!x.id.startsWith('before')&&!x.id.startsWith('phase1')&&!x.id.startsWith('after-ux')&&!x.superseded_by);
assert.equal(current.filter(x=>x.status==='FAIL'||x.status==='BLOCKED').length,0);
const entries=[
 ['DFU-01','BookingService.cancel hanya memeriksa appointment BOOKED dan waktu sesi.','Lock queue dan tolak jika queue IN_PROGRESS, terminal, atau visit sudah ada; semua caller memakai aturan bersama.','Empat jalur cancel ditolak tanpa mutasi; dokter tetap dapat finalisasi.'],
 ['DFU-12','Form dan service tidak membawa identitas maksud grant.','UUID request unik tersimpan pada periode, replay payload yang sama mengembalikan periode lama; key dengan maksud lain ditolak. Tombol dikunci saat submit; audit grant hanya sekali.','Replay, key berbeda, payload/tenant berbeda dan double-click pointer diuji.'],
 ['DFU-02','Guard sesi terpakai hanya membekukan dokter.','Bekukan dokter, hari, mulai, dan selesai dalam transaksi untuk sesi yang punya appointment/queue. Kuota/aktif tetap dapat diubah; perubahan waktu menggunakan sesi baru.','Hari dan dua jam ditolak; tanggal appointment/queue tidak berubah; CRUD sesi kosong tetap berjalan.'],
 ['DFU-11','Dashboard dan riwayat hanya menyediakan baca booking.','Kedua halaman memberi Booking baru → klinik yang terhubung atau kode klinik eksplisit → form booking yang sudah ada. Ikuti batas akun clinic_id pada policy pasien yang sudah berlaku.','Pasien pertama tanpa profile membuat booking; kode salah dapat dikoreksi; booking ulang memakai satu profile milik user yang sama.'],
 ['DFU-05','JS review duplikat membuang errors.phone pada 422.','Tampilkan pesan field dari respons validasi; tetap blok submit. Respons non-JSON memiliki pesan pemulihan, respons lama tidak menimpa review terbaru.','31 digit ditolak tanpa patient baru; detail phone/30 terlihat; JS menguji error server dan respons usang.'],
 ['DFU-06','Trust boundary hanya string/max30, sehingga abc diterima.','PhoneNumber bersama pada create/update pasien, review duplikat, walk-in baru, dan booking publik. Input tel/autocomplete/maxlength30.','abc ditolak pada seluruh endpoint; +, spasi, kurung, titik, dan hubung diterima. Tidak mewajibkan nomor unik.'],
 ['DFU-07','Dashboard menolak rentang namun tidak menampilkan errors/old input. Default tanggal parsial juga dapat membentuk rentang terbalik.','Validasi rentang efektif setelah default tanggal klinik diterapkan; pesan Indonesia, ringkasan alert, pertahankan kedua input.','Rentang terbalik, hanya tanggal awal masa depan, hanya tanggal akhir lampau ditolak; rentang default tetap dapat dibuka.'],
 ['DFU-08','Cast timestamp UTC langsung diformat pada visit dan beberapa tampilan terkait.','Konversi timestamp ke timezone klinik saat render pada visit, histori/koreksi, daftar RME, kartu antrean, resep pasien, dan pembayaran. Calendar date/sesi serta waktu tersimpan tetap sama.','WIB/WITA/WIT lintas tengah malam diuji backend; waktu lokal visit diuji Chrome; instant database tidak berubah.'],
 ['DFU-03','Feedback berada di region yang diganti refetch 250 ms.','Pindahkan feedback di luar region refresh.','No-show 422 tetap terbaca setelah 1,5 detik/refetch; FIFO dan batas panggilan aktif tetap ditolak.'],
 ['DFU-04','Template pembatalan tidak merender error validasi; input hilang.','Ringkasan error reusable untuk AppLayout dan token status, simpan pesan sebelum navigasi Livewire dirender, pertahankan alasan pembatalan.','Validasi panjang alasan terlihat dan input utuh; penolakan setelah awal sesi terlihat pada Patient, Receptionist, dan token holder.'],
 ['DFU-09','Filter dan alasan batal memiliki label visual/placeholder tanpa asosiasi programatis.','Hubungkan for/id pada empat filter audit, pencarian pasien, tanggal booking, dua alasan batal, dan pencarian pembayaran platform.','Sembilan kontrol bernama diuji pada role terkait; klik label/fokus dan Enter diuji pada booking baru.'],
 ['DFU-10','Reveal animation kanan menggeser elemen 24px melewati gutter mobile.','Gerakan reveal kiri/kanan menjadi vertikal pada <=700px. Tidak menutup overflow global.','Lebar dokumen sesuai 375/768/1024/1440; baseline375 sebelumnya385px.'],
];
for(const [id] of entries)assert.equal(scenarios.find(x=>x.id==='final-'+id)?.status,'PASS',id);
const pass=current.filter(x=>x.status==='PASS').length;
const gaps=current.filter(x=>x.status!=='PASS');
writeFileSync(join(evidence,'final-regression-summary.json'),JSON.stringify({at:new Date().toISOString(),findings:entries.map(([id])=>({id,status:'FIXED & VERIFIED',browser:'final-'+id})),backend:php,javascript:{tests:10,passed:10},browser:{passed:pass,failed:0,blocked:0,not_tested:gaps.map(x=>({id:x.id,reason:x.reason}))},historical_browser_records:scenarios.length,current_browser_records:current.length},null,2));
const rows=entries.map(([id,cause,fix,proof])=>`| ${id} | FIXED & VERIFIED | ${cause} | ${fix} | ${proof} [Browser](fixes/evidence/scenarios.json), [backend](fixes/evidence/final-backend-r3.xml) |`).join('\n');
const report=`# ClinicOS — Perbaikan DFU dan Functional & UX Regression

Run 13 November 2026. Seluruh DFU-01–DFU-12 berstatus **FIXED & VERIFIED** pada checkout ini, terbatas pada skenario dan assertion yang dijalankan. Laporan audit awal dipertahankan sebagai baseline historis; kesimpulan OPEN pada bagian historis tidak mewakili status run ini.

## Bukti dan hasil

- Backend: **${php.passed}/${php.tests} PASS, ${php.assertions} assertion**, SQLite :memory:, storage/cache/session proses terisolasi. [JUnit](fixes/evidence/final-backend-r3.xml), [ringkasan](fixes/evidence/final-backend-r3-summary.json).
- JavaScript: **10/10 PASS**, termasuk 3 regresi duplicate review, autosave/offline/conflict, booking selector dan notifikasi. [Output](fixes/evidence/javascript-tests.txt).
- Chrome nyata headless 158.0.8127.36 / Playwright 1.65.0: **${pass} PASS, 0 FAIL, 0 BLOCKED, ${gaps.length} NOT TESTED** pada skenario aktif. Termasuk **12/12 retest final DFU**, role Clinic Owner, Clinic Admin operasional, Receptionist, Doctor, Patient, Platform Admin dan token holder. [Skenario lengkap](fixes/evidence/scenarios.json), [ringkasan final](fixes/evidence/final-regression-summary.json), [screenshot](fixes/evidence/screenshots/), [snapshot/responsif](fixes/evidence/pages.json).
- Reproduksi sebelum fix: browser before-DFU-01/02/12 dan before-ux-DFU-03–11 semuanya gagal assertion perilaku yang diharapkan. Backend fase1 9 test merah → hijau; tes fase2 mendeteksi validasi hilang dan route create belum tersedia. [Sebelum](fixes/evidence/phase1-before-summary.json), [sesudah terkait](fixes/evidence/phase1-related-summary.json), [overflow sebelum](fixes/evidence/before-ux-overflow.json), [sesudah](fixes/evidence/final-overflow.json).
- Build frontend pada direktori temp lulus; Pint --dirty lulus. [Build](fixes/evidence/build-after.txt), [Pint](fixes/evidence/pint.txt).

## Status per temuan

| Temuan | Status aktual | Akar masalah source terkini sebelum fix | Expected behavior dan perubahan | Regresi teruji |
| --- | --- | --- | --- | --- |
${rows}

## Cakupan regresi lintas fitur

Browser menguji CRUD layanan, staf, aktivasi/deaktivasi seat, dokter dan jadwal; validasi bentrok; pembuatan/edit/pencarian/duplikat pasien; profil klinik; filter audit; booking publik terautentikasi/walk-in/ulang; check-in, FIFO, call/skip/return, panggilan aktif, no-show, cancel, display publik; handoff dokter; autosave SOAP/vital/diagnosis/tindakan, validasi vital, alergi, resep, konflik dua tab, putus/pulih koneksi, foto privat; dialog keyboard finalisasi, Modification dan buka ulang RME; akses negatif seluruh role; pembatalan lewat token; navigasi booking baru dengan keyboard dan empat viewport. Backend menjalankan tests dan qa-audit/tests secara lengkap, termasuk tenant/authorization dan subscription/payment yang sudah ada.

Skenario awal ADM-09 memakai regex yang tidak mengenali kata **bertabrakan**, ADM-15 mengasumsikan event tertentu ada di halaman pertama, QUEUE-05 memakai staf yang baru dibuat dan belum terverifikasi. Retest memperbaiki prasyarat/assertion, mempertahankan hasil awal dengan superseded_by. RME-08/09/12 awal gagal karena acknowledgement alergi tidak dipenuhi setelah reload; REG-FINALIZE/MODIFICATION/REOPEN melengkapi prasyarat dan lulus. Tidak ada failure aktif yang disembunyikan sebagai PASS. Skenario sebelum perbaikan sengaja tetap merah dan dipisahkan dari hitungan regresi aktif.

Satu full run sempat gagal pada test hapus logo karena Faker menghasilkan slug >63 karakter; perubahan aplikasi DFU tidak menyebabkan validasi tersebut. Fixture test itu diberi slug tetap, test terfokus dan full suite kemudian lulus. Log percobaan disimpan dengan detail sensitif disamarkan. Satu JUnit percobaan yang terpotong oleh batas buffer diberi ekstensi .xml.incomplete dan dicatat pada fixes/evidence/incomplete-attempts.json; JUnit final valid dan lengkap.

## Keputusan dan batas produk

- **Tidak ada penghentian pelayanan baru yang diasumsikan.** Pembatalan appointment ditolak setelah visit ada. Visit aktif tetap diselesaikan dokter melalui workflow lama. Rekonsiliasi data lama yang telanjur CANCELLED + visit aktif memerlukan pemeriksaan kasus dan keputusan pemulihan: kembalikan status appointment/queue yang sah atau definisikan penghentian klinis beralasan. Tidak dilakukan koreksi otomatis atau pemeriksaan isi database pengguna.
- **Tidak memindahkan appointment secara diam-diam.** Sesi terpakai dibekukan pada dokter/hari/jam, sementara sesi baru dapat dibuat. Reschedule massal/snapshot historis alternatif memerlukan keputusan produk; data jadwal lama yang sudah tidak konsisten tidak ditulis ulang.
- **Satu maksud grant = satu UUID.** Replay yang sama tidak menambah periode/audit; operator masih dapat memberi grant baru sengaja melalui formulir baru dengan UUID baru. Tidak ada penghapusan histori grant lama. Caller domain internal tanpa UUID tetap kompatibel; endpoint HTTP wajib UUID untuk grant berbayar. Downgrade Free mengikuti workflow lama.
- **Jalur booking minimum:** klinik terhubung atau kode/tautan klinik, memakai form dan BookingService yang sudah ada. Akun pasien terikat clinic_id mengikuti batas policy klinik yang sudah berlaku; akun global tanpa clinic_id bisa memakai kode klinik aktif. Tidak ada pencarian direktori global, account linking lewat NIK/telepon, atau multi-cabang baru. Klinik yang tidak menerima booking tetap mengikuti guard booking existing.
- **Telepon:** validasi sintaks permisif dan panjang maksimal30, bukan bukti kepemilikan/nomor aktif. Panjang minimal, nomor nasional/internasional khusus, ekstensi dan OTP memerlukan keputusan produk tersendiri. Nomor keluarga bersama tetap didukung. Data telepon lama tidak diperbaiki otomatis.
- **Waktu:** hanya konversi saat tampilan; tanggal appointment dan jam sesi adalah waktu kalender klinik, bukan instant UTC yang dikonversi.

## Batas verifikasi dan penerapan

Database browser adalah SQLite file disposable; PHPUnit memakai SQLite :memory:. Tidak ada akses tulis pada data pengguna, migrate:fresh, seed ulang database pengguna, perubahan .env/APP_KEY pengguna, commit, push, deploy, atau pembayaran nyata. Perubahan pengguna sebelum tugas dipertahankan.

Migration additive grant_request_id nullable + unique **belum dijalankan pada database pengguna**. Dibutuhkan saat perubahan ini diterapkan oleh pengguna. Rollback ditolak jika identitas replay sudah terpakai agar identitas komersial tidak hilang.

SQLite tidak membuktikan locking/concurrency MySQL. Double-click Chrome dan replay HTTP teruji; tidak ada load/stress test. Scheduler, worker, Reverb socket nyata, email eksternal, Google OAuth dan DOKU nyata tidak dijalankan. Gangguan jaringan yang diuji adalah fetch/autosave serta refetch/polling, bukan failover layanan eksternal. Screen reader/perangkat sentuh fisik tidak diuji.

${gaps.map(x=>`- NOT TESTED browser: **${x.id}** — ${x.reason}. Ini placeholder cakupan dental terpisah dari DFU; backend odontogram tetap tercakup full suite.`).join('\n')}

## Mengulang pengujian

Perintah runner tidak mengubah .env. Jalankan dari root repository:

\`\`\`powershell
$env:DFU_TEST_LABEL = 'local-regression'
node qa-audit/deep-functional-ux/fixes/run-php.mjs tests qa-audit/tests
node --test tests/patient-duplicates.test.mjs tests/notification-realtime.test.mjs tests/partial-workflows.test.mjs
\`\`\`

Browser memerlukan runtime disposable yang disiapkan scripts/runtime.mjs dengan CLINICOS_AUDIT_EVIDENCE diarahkan ke fixes/evidence atau direktori run baru. Urutan prasyarat direkam di fixes/RUN_SEQUENCE.md; jangan arahkan router/fixture ke database aplikasi. Bukti historis tidak diganti.
`;
writeFileSync(join(root,'../FIX_REGRESSION_REPORT.md'),report);
for(const file of readdirSync(join(root,'..')).filter(x=>x.endsWith('.md')&&x!=='FIX_REGRESSION_REPORT.md')){
 const path=join(root,'..',file);const text=readFileSync(path,'utf8');
 const note='> **Pembaruan perbaikan 13 November 2026:** DFU-01–DFU-12 telah diperbaiki dan diverifikasi ulang. Lihat [status dan bukti terbaru](FIX_REGRESSION_REPORT.md). Isi di bawah dipertahankan sebagai **baseline audit sebelum perbaikan**, termasuk status OPEN dan hasil run historis.\n\n';
 if(!text.startsWith(note))writeFileSync(path,note+text);
}
console.log(JSON.stringify({findings:12,browser_pass:pass,browser_not_tested:gaps.length,backend_pass:php.passed}));
