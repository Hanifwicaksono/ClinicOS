"""Build the requested audit documents from explicit findings; never bootstrap Laravel."""
from pathlib import Path
import hashlib, json, re, collections

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
E = OUT / 'evidence'
def read(path):
    return (ROOT / path).read_text(encoding='utf-8-sig', errors='replace')
def dump(name, value):
    (E / name).write_text(json.dumps(value, ensure_ascii=False, indent=2), encoding='utf-8')
def src(path, pattern=None):
    assert (ROOT / path).exists(), path
    lines = read(path).splitlines()
    matches=[i for i,line in enumerate(lines,1) if pattern and re.search(pattern,line)]
    assert not pattern or matches, (path,pattern)
    number=matches[0] if matches else 1
    return f'[{path}:{number}](../../{path}#L{number})'
def ui(n):
    return f'[UI-{n}](evidence/screenshots/UI-{n}.png)'
def table(headers, rows):
    clean = lambda x: str(x).replace('|', '/').replace('\n', '<br>')
    return '| ' + ' | '.join(headers) + ' |\n| ' + ' | '.join(['---']*len(headers)) + ' |\n' + ''.join('| ' + ' | '.join(map(clean, row)) + ' |\n' for row in rows)
def write(name, content):
    (OUT / name).write_text(content.strip()+'\n', encoding='utf-8')

baseline=json.loads((E/'source-baseline.json').read_text())
qa=json.loads((E/'qa-report-index.json').read_text())
reviews=json.loads((E/'task-review.json').read_text())
current=[r for r in reviews if not r.get('superseded_by')]
assert len({x['id'] for x in reviews}) == len(reviews)
assert all(r['status']=='OBSERVED' for r in current)
routes=json.loads((E/'routes.json').read_text())
schema=json.loads((E/'schema.json').read_text())
roles=json.loads((E/'roles-permissions.json').read_text())['roles']
assert len(roles)==5 and len(routes)==120 and len(schema)==44
scan=[]
for path in baseline:
    if path=='.env':
        continue
    body=read(path)
    scan.append({'file':path,'lines':len(body.splitlines()),
      'methods':re.findall(r'(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(',body),
      'forms':[{'line':body[:m.start()].count('\n')+1,'tag':m.group(0)[:300]} for m in re.finditer(r'<form\b[^>]*>',body)],
      'field_names':sorted(set(re.findall(r'\bname="([\w\[\].-]+)"',body))),
      'named_route_references':sorted(set(re.findall(r"route\(['\"]([\w.-]+)",body))),
      'controls':[{'line':body[:m.start()].count('\n')+1,'tag':m.group(0)[:350]} for m in re.finditer(r'<(?:button|a|x-clinic-button)\b[^>]*>',body)]})
dump('source-scan.json',scan)
docs=[]
for path in ['PROJECT_ANALYSIS.md','PRD.md','Plan.md','Development-Plan.md','design (1).md','README.md']+[str(p.relative_to(ROOT)).replace('\\','/') for p in (ROOT/'docs').rglob('*.md')]:
    body=read(path)
    docs.append({'file':path,'sha256':hashlib.sha256((ROOT/path).read_bytes()).hexdigest(),'lines':len(body.splitlines()),'headings':re.findall(r'^#{1,4} .+',body,re.M),'scope_claims':[{'line':i,'text':s[:500]} for i,s in enumerate(body.splitlines(),1) if re.search(r'(?i)post.mvp|billing|kasir|print|export|offline|React|multi.branch|NOTIF-3|autosave|odontogram',s)]})
dump('product-doc-index.json',docs)
ledger=[]
for entry in qa:
    body=read(entry['file'])
    disposition='Referensi historis; hasil per fitur tunduk pada laporan fix terbaru dan source saat audit.'
    if 'CCL_FIX_' in entry['file']: disposition='Acuan terbaru CCL-01–08 FIXED & VERIFIED; hasil regression 14 November, tidak dijalankan ulang.'
    elif 'FIX_REGRESSION_REPORT' in entry['file']: disposition='Acuan fix DFU-01–12; tetap dipertahankan.'
    elif 'REMAINING_GAPS' in entry['file']: disposition='Daftar keputusan historis dinilai ulang di laporan ini; CCL FAIL digantikan laporan fix; provider dibaca bersama integrasi 11 November.'
    elif 'production-readiness' in entry['file']: disposition='Batas operasional historis; PR-01 tetap risiko terpisah. Tidak mengulang readiness/load/security atau menganggap SQLite membuktikan MySQL.'
    ledger.append({'file':entry['file'],'sha256':entry['sha256'],'lines':len(body.splitlines()),'disposition':disposition,'status_claims':[{'line':i,'text':s[:600]} for i,s in enumerate(body.splitlines(),1) if re.search(r'FIXED|OPEN|BLOCKED|NOT TESTED|NOT VERIFIED|NOT IMPLEMENTED|PARTIAL',s)]})
dump('qa-review-ledger.json',ledger)

S={
 'book':src('app/Services/BookingService.php','function book'),
 'inactive':src('app/Services/BookingService.php','function resolvePatient'),
 'queue':src('app/Services/QueueWorkflowService.php','function cancel'),
 'visit':src('app/Services/VisitWorkflowService.php','function complete'),
 'visitstatus':src('app/VisitStatus.php','enum'),
 'clinic':src('app/Http/Requests/UpdateClinicRequest.php','opening_time'),
 'schedule':src('app/Models/DoctorSchedule.php','Fillable'),
 'clinicalui':src('resources/views/doctor/visits/show.blade.php'),
 'patient':src('app/Http/Controllers/Patient/DashboardController.php','medicalRecords'),
 'dup':src('app/Services/PatientDuplicateService.php','limit\\(8'),
 'sub':src('app/Services/SubscriptionService.php','function grant'),
 'tenant':src('app/Http/Controllers/Platform/TenantController.php','function show'),
 'tenantui':src('resources/views/platform/tenants/show.blade.php','Subscription saat ini'),
 'refund':src('app/Services/DokuPaymentService.php','function recordManualRefund'),
 'addonhist':src('app/Http/Controllers/SubscriptionAddonController.php','addonPeriods'),
 'audit':src('app/Http/Controllers/Admin/AuditLogController.php'),
 'dash':src('app/Http/Controllers/Admin/DashboardController.php','service_value'),
 'notif':src('app/Services/ClinicNotificationService.php','function queueStatusChanged'),
 'cron':src('routes/console.php','subscriptions:queue-reminders'),
 'allergy':src('app/Services/VisitWorkflowService.php','function saveAllergy'),
 'backfill':src('app/Services/LegacyTenantBackfillService.php','function run'),
 'staff':src('app/Http/Controllers/Admin/StaffController.php','function create'),
 'staffsvc':src('app/Services/StaffAccountService.php'),
 'plans':src('database/seeders/SubscriptionPlanSeeder.php','realtime'),
 'channels':src('routes/channels.php'),
 'perms':src('database/seeders/RoleAndPermissionSeeder.php'),
 'routes':src('routes/web.php'),
 'rmehistory':src('app/Http/Controllers/Doctor/MedicalRecordController.php'),
 'patiententry':src('app/Http/Controllers/Patient/AppointmentController.php'),
}

# One canonical row per need. Other documents reference these IDs, not extra findings.
gaps=[]
def gap(id,title,status,priority,scope,roles,problem,workflow,present,missing,impact,workaround,solution,size,deps,ac,evidence,frequency,basis,kind,decision):
    gaps.append(dict(id=id,title=title,status=status,priority=priority,scope=scope,roles=roles,problem=problem,workflow=workflow,present=present,missing=missing,impact=impact,workaround=workaround,solution=solution,size=size,deps=deps,ac=ac,evidence=evidence,frequency=frequency,basis=basis,kind=kind,decision=decision))

gap('PG-01','Perubahan booking terkonfirmasi sebelum pemeriksaan','PARTIAL','P1','Melengkapi alur inti; aturan perubahan perlu disetujui','Receptionist, Patient; Owner/Clinic Admin mengatur kapasitas','Kesalahan dokter/layanan atau waktu tidak dapat diperbaiki sebagai satu perubahan booking.','BOOKED/WAITING → koreksi atau jadwal baru',
 'Booking baru, pembatalan sebelum visit, no-show dan snapshot harga tersedia. Detail UI hanya menyediakan operasi status yang ada.',
 'Tidak ada command reschedule/reassign, relasi pengganti atau preview slot baru yang mempertahankan jejak booking lama.',
 'Staf harus membatalkan dan booking ulang; nomor antrean berubah, kontinuitas alasan/perubahan bergantung catatan manual.',
 'Batalkan booking yang belum memiliki visit, buat booking baru, komunikasikan kode baru; jangan lakukan pada IN_PROGRESS.',
 'Pilih cancel-and-rebook yang dipandu dengan tautan supersedes, atau edit transaksional dengan snapshot revisi. Tampilkan konsekuensi kuota, harga, nomor dan notifikasi sebelum konfirmasi.',
 'L',[], 'Booking tanpa visit dapat dipindahkan sesuai kebijakan yang disetujui; validasi tenant/dokter/layanan/jam/kuota atomik; request berulang tidak menggandakan; booking lama tertaut dan diaudit; IN_PROGRESS/FINAL tidak berubah; pasien menerima status/kode yang jelas.',
 [S['book'],S['queue'],S['routes'],ui('07')], 'Sesekali sampai harian','Inferensi: koreksi administrasi dan permintaan perubahan; volume nyata belum diukur.','Workflow tidak lengkap','D-01')
gap('PG-02','Ketersediaan berdasarkan tanggal dan dampak perubahan jam','PARTIAL','P1','Operasional inti; model pengecualian perlu keputusan','Owner, Clinic Admin, Receptionist, Doctor','Hari libur/cuti dan perubahan sementara sulit dinyatakan tanpa mengubah jadwal mingguan.','Setup jadwal → gangguan jadwal → booking baru + penanganan existing',
 'Jam buka/tutup dan timezone klinik, jadwal mingguan, aktivasi jadwal/klinik, kuota tersedia. CCL-05 menolak booking baru di luar jam terkini tanpa mengubah appointment lama.',
 'Tidak ada exception tanggal/rentang atau daftar appointment terdampak sebelum perubahan; setting bukan reschedule massal.',
 'Operator perlu meninjau booking lama sendiri dan menonaktifkan/mengembalikan jadwal manual; pasien lama bisa tetap memiliki appointment saat klinik tutup.',
 'Nonaktifkan sesi/klinik bila sesuai, cari booking terdampak lalu hubungi pasien; catat kapan jadwal harus dipulihkan.',
 'Tambahkan kalender pengecualian minimum dan preview read-only booking terdampak; penyelesaian appointment existing melalui keputusan eksplisit terpisah, memanfaatkan PG-01.',
 'M–L',['PG-01'],'Exception tanggal memblokir booking baru hanya pada rentang/timezone terkait; hari normal tidak berubah; preview menunjukkan booking terdampak lintas status; menyimpan setting tidak menggeser/membatalkan appointment; restoration dan konflik jadwal dapat diverifikasi.',
 [S['clinic'],S['schedule'],S['book'],ui('12'),ui('13')], 'Mingguan/musiman, perubahan mendadak sesekali','Inferensi dari jadwal berulang vs hari libur/cuti; belum ada data frekuensi klinik.','Workflow tidak lengkap','D-02')
gap('PG-03','Penghentian dan serah terima pemeriksaan aktif','PRODUCT DECISION REQUIRED','P1','Recovery alur inti, perubahan data klinis harus diputuskan','Doctor, Receptionist; Owner memantau nonmedis','Dokter tidak dapat melanjutkan atau pemeriksaan telanjur dimulai tanpa layanan selesai.','CALLED → IN_PROGRESS → interupsi / pergantian dokter',
 'Visit hanya IN_PROGRESS/COMPLETED; start, draft, finalisasi dan koreksi final tersedia. Pembatalan queue/appointment setelah visit sengaja ditolak untuk integritas DFU-01.',
 'Tidak ada status terminal interrupted/aborted atau handover berotorisasi dan alasan; tidak ada pemulihan operasional dari IN_PROGRESS kecuali melanjutkan/finalisasi sah.',
 'Sesi dapat tetap terkunci sampai dokter yang ditugaskan kembali; menandai completed tanpa pelayanan bukan workaround yang dapat diterima.',
 'Pertahankan draft dan hubungi dokter penanggung jawab; jika ada keadaan darurat, prosedur pelayanan luar sistem yang disetujui klinik. Jangan ubah status langsung di database.',
 'Putuskan interrupted terminal vs resume/handover. Tentukan tanggung jawab, akses minimal, persetujuan dan histori sebelum menambah transisi melalui VisitWorkflowService.',
 'L',[],'Interupsi menyimpan aktor/alasan/waktu dan seluruh draft/foto; tidak memalsukan COMPLETED atau menghapus RME; antrean aktif dilepas sesuai keputusan; hanya dokter berwenang mengakses; finalisasi lama/replay ditangani; downgrade tidak menutup hak menyelesaikan pemeriksaan berjalan.',
 [S['visitstatus'],S['visit'],S['queue'],ui('09')], 'Jarang tetapi berdampak saat terjadi','Inferensi gangguan pelayanan; tidak ada insiden nyata atau bahaya klinis yang dibuktikan.','Keputusan bisnis / recovery','D-03')
gap('PG-04','Tindak lanjut klinis menjadi pekerjaan yang dapat diselesaikan','PARTIAL','P2','Pengembangan berikutnya; kebutuhan bentuk tugas harus divalidasi','Doctor, Patient, Receptionist','Rencana kontrol tersimpan sebagai teks tetapi belum menjadi booking/tugas tindak lanjut.','FINAL → instruksi pasien → kunjungan berikutnya / follow-up',
 'SOAP plan, resep final di portal pasien, riwayat booking dan booking baru tersedia. Dokter dapat menulis rencana, pasien dapat booking manual.',
 'Tidak ada request follow-up terstruktur dengan tanggal/penanggung jawab/status atau handoff nonmedis ke resepsionis.',
 'Pasien/staf harus mengingat atau menyalin anjuran; sistem tidak menunjukkan follow-up yang belum selesai.',
 'Dokter memberi instruksi langsung, pasien/staf membuat booking baru dan mencatat hubungan secara manual.',
 'Mulai dari instruksi kontrol minimum yang disetujui pasien dan CTA booking; bila dibutuhkan, tambahkan tugas tertutup. Jangan membuka isi SOAP kepada staf administrasi.',
 'M',['PG-01'],'Dokter membuat follow-up berotorisasi; pasien hanya melihat instruksi yang disetujui; resepsionis hanya metadata penjadwalan; booking pengganti dapat ditautkan; tugas selesai/batal diaudit; final RME tidak diubah diam-diam.',
 [S['clinicalui'],S['patient'],S['patiententry'],ui('10'),ui('11')], 'Per kunjungan yang memerlukan kontrol','Inferensi kebutuhan kontrol, bukan semua visit wajib follow-up.','Workflow tidak lengkap','D-04')
gap('PG-05','Penyelesaian tagihan layanan pasien dan bukti bayar','MISSING','P1','Wajib hanya jika cakupan RPP operasional sampai kasir dipilih; di luar MVP/plan SaaS lama','Receptionist/kasir yang perlu dipetakan, Owner, Patient','Pelayanan selesai tidak dapat dilanjutkan menjadi tagihan dan penerimaan kas di aplikasi.','COMPLETED → tagihan pasien → pembayaran → receipt / rekonsiliasi',
 'Snapshot harga layanan dan Visit.total_amount tersedia. Payment, invoice dan refund yang ada adalah subscription SaaS, bukan pembayaran pasien.',
 'Tidak ada domain patient billing, pembayaran layanan, sisa tagihan, kuitansi atau rekonsiliasi kas.',
 'Untuk klinik yang memerlukan kasir terintegrasi, workflow berakhir sebelum administrasi keuangan selesai. Dashboard estimasi nilai layanan tidak dapat dipakai sebagai kas diterima.',
 'Gunakan kasir/buku kas eksternal dan rekonsiliasi manual berdasarkan visit tanpa menganggap Payment SaaS sebagai kas pasien.',
 'Putuskan batas kasir tunai minimum, diskon/partial/refund/role; domain terpisah dari SaaS dengan snapshot tagihan dan ledger. Jangan menambah gateway pasien sebelum kebutuhan dipastikan.',
 'XL',[],'Setelah kebijakan disetujui, visit menghasilkan tagihan unik terpisah SaaS; pembayaran idempotent menghasilkan receipt; outstanding/cash dapat direkonsiliasi; void/refund tidak menghapus histori; role dan tenant dibatasi; perubahan harga layanan tidak mengubah tagihan historis.',
 [S['dash'],S['routes'],'[Schema 44 tabel](evidence/schema.json)','[RPP](evidence/rpp-reference.txt)',src('Development-Plan.md','tidak mencakup'),ui('17')], 'Setiap kunjungan berbayar jika kasir masuk scope','Inferensi berdasarkan transaksi layanan; volume pembayaran pasien belum diukur.','Fitur hilang bersyarat scope','D-05')
gap('PG-06','Makna pasien nonaktif konsisten antar kanal','PRODUCT DECISION REQUIRED','P1','Kebijakan inti identitas; bukan membuka kembali DFU/CCL yang sudah diverifikasi','Receptionist, Patient, Clinic Admin/Owner','Status nonaktif memblokir prefill booking staf, tetapi pasien sendiri masih dapat booking dengan profil nonaktif yang sama.','Deaktivasi patient → staff booking vs authenticated patient booking',
 'CCL-04 memberi alasan/pemulihan pada staf. resolvePatient cabang patient_id memfilter is_active; cabang akun Patient mengambil profil terkait tanpa filter tersebut. UI-22 menghasilkan booking baru, profil tetap nonaktif dan jumlah profil tetap satu.',
 'Belum ada kontrak apakah nonaktif berarti arsip administrasi, blok booking semua kanal atau hanya daftar staf; tidak ada penjelasan perbedaan kanal ini.',
 'Staf dan pasien menerima ekspektasi berbeda. Tidak terbukti tercipta duplikat baru atau data hilang, sehingga bukan klaim CCL-04 regresi.',
 'Staf memeriksa profil dan mereaktivasi dengan izin bila sesuai; self-service perlu mengikuti keputusan produk, jangan menyarankan pasien memakai kanal ini untuk menghindari blok.',
 'Pilih blok semua kanal dengan langkah kontak/reaktivasi, atau arsip dengan aturan booking eksplisit. Terapkan satu aturan domain dan copy lintas kanal setelah keputusan.',
 'S–M',[],'Test staff/patient/guest untuk makna nonaktif yang dipilih; profil tidak diganti/diaktifkan/link otomatis; alasan dan langkah pemulihan tersedia; tenant dan kepemilikan terjaga; histori tetap; jika kanal berbeda, perbedaannya eksplisit dan diuji.',
 [S['inactive'],ui('22'),'[Hasil UI-22 termasuk jumlah profil](evidence/task-review.json)'], 'Sesekali, saat pasien lama kembali','Inferensi; proporsi pasien nonaktif kembali belum diketahui.','Kebijakan tidak konsisten; calon bug setelah kontrak ditetapkan','D-06')
gap('PG-07','Dokumen pasien yang disetujui: resep / ringkasan kunjungan','PARTIAL','P1','Bersyarat kebutuhan dokumen saat pelayanan; PRD masih pertanyaan, Plan menaruh print post-MVP','Doctor, Patient; Receptionist hanya dokumen administratif yang diizinkan','Resep tersimpan dan terlihat tetapi belum menjadi dokumen yang dapat diberikan ke apotek/pasien melalui alur print/export.','FINAL → dokumen terbit → pasien / pihak tujuan',
 'Resep terstruktur, preview dokter, resep final pasien, histori koreksi dan endpoint foto private tersedia. Ini tidak sama dengan PDF/resep tercetak.',
 'Tidak ada route/view print atau export dokumen klinis, nomor penerbitan/snapshot revisi dan batas isi dokumen yang disetujui.',
 'Klinik yang harus menyerahkan dokumen tetap menyalin ke media lain. Screenshot/browser-print mentah bukan bukti format dokumen sah/terverifikasi.',
 'Dokter membuat dokumen di luar sistem sesuai prosedur klinik; pasien membaca portal yang diotorisasi.',
 'Putuskan dokumen minimum dan pihak yang boleh menerbitkan/mengambil; mulai printable Blade dengan snapshot final dan identitas/revisi yang sesuai. Jangan membuka full SOAP ke seluruh role.',
 'M',[],'Hanya RME final yang diotorisasi dapat diterbitkan; pasien hanya dokumen miliknya yang disetujui; koreksi menghasilkan versi terlacak; data draft/tenant lain tidak muncul; print/PDF diuji dummy termasuk nama/resep panjang; dokumen bukan klaim tanda tangan/legalitas yang belum ditetapkan.',
 [S['patient'],S['clinicalui'],S['routes'],src('Plan.md','Export PDF/print'),ui('10')], 'Per resep/dokumen yang perlu dibawa keluar sistem','Inferensi workflow apotek/pasien; pilihan format dan penerima belum divalidasi.','Workflow tidak lengkap / scope decision','D-07')
gap('PG-08','Laporan operasional yang dapat direkonsiliasi dan dibawa keluar','PARTIAL','P2','Pengembangan berikutnya, bukan kewajiban export semua tabel','Owner, Clinic Admin; Platform untuk komersial terpisah','Ringkasan dashboard dan audit bisa dibaca tetapi tidak menghasilkan laporan periode yang dapat dipakai untuk pembukuan/manajemen.','Filter periode → verifikasi angka → laporan operasional',
 'Dashboard berfilter tanggal, nilai layanan berlabel bukan pembayaran diterima, audit log berfilter dan paginasi tersedia.',
 'Tidak ada export CSV/PDF laporan yang disetujui, definisi metrik terdokumentasi di UI laporan atau rekonsiliasi antar daftar detail.',
 'Pemilik menyalin angka manual dan tidak memiliki artefak periode. Bukan bukti angka dashboard salah.',
 'Gunakan filter dan audit log, catat hasil manual; kas tetap di sistem terpisah.',
 'Pilih satu laporan operasional minimum dengan definisi status/tanggal/timezone; sediakan detail penjelas dan export terbatas tanpa isi medis. Keuangan kas menunggu PG-05.',
 'M',[],'Total dan detail cocok untuk timezone/status/periode yang sama termasuk nol; export tenant-scoped dan tidak memuat SOAP/foto; batas tanggal valid; service value tidak dinamai cash; export kas hanya tersedia bila ledger pasien ada.',
 [S['dash'],S['audit'],S['routes'],src('Plan.md','Export PDF/print'),ui('16')], 'Mingguan/bulanan','Inferensi pelaporan periodik, belum ada wawancara pemilik.','Workflow tidak lengkap / UX informasi','D-08')
gap('PG-09','Timeline histori komersial lintas periode','PARTIAL','P2','Pengelolaan SaaS yang sudah masuk produk','Platform Admin, Owner dengan batas per klinik','Status efektif terkini tidak cukup menjelaskan rangkaian grant, pembayaran, jadwal dan revoke saat dukungan.','Investigasi invoice → periode lalu / aktif / terjadwal → sumber perubahan',
 'SubscriptionPeriod/Payment/AddonPeriod dan audit tersedia; Platform menampilkan subscription kini, grant/Free dan pembayaran; owner mempunyai invoice serta riwayat add-on (UI-23). CCL-06 menyelaraskan status efektif.',
 'Platform tidak memiliki timeline seluruh periode utama dan tautan sebab-akibat. Riwayat yang ada terpisah dan tidak boleh disebut seluruhnya tidak ada.',
 'Support perlu membaca beberapa halaman atau bantuan developer untuk menjelaskan histori kompleks sebelum koreksi aman.',
 'Cari invoice/payment dan audit dengan ID; eskalasi read-only ke dukungan, jangan koreksi database manual.',
 'Viewer read-only per tenant: source, starts/ends, effective/scheduled/revoked, payment/grant actor/reason dan audit; owner hanya field komersial miliknya.',
 'M',[],'Timeline menampilkan periode aktif/lalu/terjadwal/revoked dengan timezone dan batas eksklusif yang jelas; link invoice/source konsisten; tanpa Payment tidak disebut cash; tenant asing ditolak; tidak mengubah periode/histori ketika dibuka.',
 [S['tenant'],S['tenantui'],S['addonhist'],ui('14'),ui('23')], 'Sesekali pada support, setiap pemeriksaan histori','Inferensi kompleksitas multi-periode yang sudah ada; tiket aktual tidak tersedia.','Workflow tidak lengkap','D-09')
gap('PG-10','Koreksi grant tertentu dan rekonsiliasi refund/akses','PRODUCT DECISION REQUIRED','P1','Recovery komersial inti; policy wajib sebelum mutasi','Platform Admin; Owner menerima dampak','Grant salah/duplikat lama tidak bisa dibatalkan secara spesifik; pencatatan refund tidak menjelaskan penyelesaian dana dan akses paket utama sebagai satu tugas.','Histori grant/payment → pilih koreksi → entitlement + dana + audit',
 'DFU-12 mencegah grant ganda baru; grant dan downgrade global Free tersedia. recordManualRefund menandai Payment refunded dan mencabut AddonPeriod; tidak memanggil transfer refund provider atau revoke periode paket utama tertentu.',
 'Tidak ada single-grant revoke/undo, guided recovery duplicate historis, preview periode yang terpengaruh atau transaksi refund dana provider. Refund lokal adalah pencatatan, bukan pengembalian uang otomatis.',
 'Downgrade Free mengakhiri lebih banyak periode daripada satu grant; refund dan entitlement dapat dipahami keliru tanpa prosedur yang diputuskan.',
 'Platform memeriksa histori, proses pengembalian dana melalui prosedur provider di luar aplikasi bila disetujui dan catat alasan; jangan gunakan Free sebagai undo satu periode.',
 'Pilih compensating adjustment: revoke satu grant yang keliru dengan jejak utuh, penyesuaian jadwal atau credit/refund terpisah. Tampilkan preview hak akses dan status dana; pertahankan periode sah dan visit berjalan.',
 'L',['PG-09'],'Fixture duplicate historis dapat dianalisis tanpa menghapus histori; hanya periode dipilih berubah sesuai keputusan; periode sah mendatang/invoice tetap; aktor/alasan/audit dan idempotency wajib; refund dana vs recorded terpisah; akses add-on/paket utama dan IN_PROGRESS teruji.',
 [S['sub'],S['refund'],S['tenantui'],ui('14'),ui('15')], 'Jarang / ketika salah administrasi atau refund','Inferensi exception, bukan klaim duplikat aktual pada DB pengguna.','Keputusan bisnis / recovery komersial','D-10')
gap('PG-11','Lifecycle tenant dan kepemilikan yang dapat dikelola','PRODUCT DECISION REQUIRED','P2','Governance SaaS; tidak mengharuskan hard delete','Owner, Platform Admin, Clinic Admin','Pemilik berhenti/berganti, klinik ditutup atau perlu suspension belum mempunyai prosedur UI terpandu.','Onboarding → operasi → suspend/transfer/closure → pemulihan',
 'Owner eksplisit satu klinik, owner dapat mengubah is_active klinik; akun/staf dapat dinonaktifkan, state subscription suspended dikenali. Platform UI belum menyediakan transfer ownership/suspend/resume/closure.',
 'Tidak ada approval/identitas penerima owner, efek penutupan pada akses data atau administrasi lifecycle tenant terpadu.',
 'Perubahan owner memerlukan dukungan teknis; menutup booking tidak sama dengan mengakhiri kontrak, retensi atau menghapus data.',
 'Owner menonaktifkan klinik untuk menghentikan booking baru; permintaan lifecycle ditangani dukungan dengan prosedur manual yang disetujui.',
 'Tetapkan state/transisi, siapa menyetujui, hak baca dan kelanjutan visit aktif; implementasikan transfer/suspend yang diperlukan saja. Jangan menambahkan impersonation atau penghapusan histori.',
 'L',[],'Owner lama/baru diverifikasi sesuai keputusan; satu owner satu klinik tetap; suspend/resume memberi alasan dan dampak eksplisit; klinis/payment tidak dihapus; admin platform tetap tanpa isi medis; IN_PROGRESS boleh diselesaikan sesuai invariant; audit dan rollback administratif teruji.',
 [S['tenantui'],S['clinic'],S['sub'],S['perms'],ui('12'),ui('14')], 'Jarang sepanjang lifecycle','Inferensi SaaS berlangganan; belum ada permintaan pemindahan nyata.','Keputusan bisnis / lifecycle','D-11')
gap('PG-12','Informasi mendekati giliran dan reminder janji','PARTIAL','P2','Near-turn disebut PRD penting; kanal/reminder appointment lanjutan perlu keputusan','Patient, Receptionist, Doctor','Pengguna mendapat perubahan status, tetapi belum diingatkan proaktif sebelum giliran/appointment.','BOOKED/WAITING → pengingat → kedatangan tepat waktu',
 'Notifikasi booking/queue database dan queued broadcast, notification center serta ETA tersedia. Command terjadwal saat ini hanya reminder subscription.',
 'Tidak ditemukan trigger near-turn atau scheduled appointment reminder. Email/WhatsApp untuk janji tidak otomatis tersedia hanya karena auth memakai mail.',
 'Pasien perlu memantau halaman atau dihubungi staf; bukan klaim no-show terbukti meningkat.',
 'Pantau portal/display, staf menghubungi pasien melalui prosedur manual dengan persetujuan yang sesuai.',
 'Mulai near-turn kanal dalam aplikasi dengan dedup dan status aktual; putuskan threshold/lead time, consent dan kanal appointment reminder sebelum menambah scheduler/pengiriman eksternal.',
 'M',[],'Threshold/lead time yang diputuskan memicu tepat satu reminder relevan; cancel/no-show/perubahan jadwal menghentikan pesan basi; ownership dicek saat kirim; tidak ada data medis pada broadcast; worker retry tidak menggandakan; near-turn dibedakan dari CALLED.',
 [S['notif'],S['cron'],src('PRD.md','NOTIF-3'),ui('19')], 'Setiap appointment yang memenuhi kondisi','Inferensi frekuensi event; notifikasi status existing tidak dihitung gap baru.','Workflow komunikasi tidak lengkap','D-12')
gap('PG-13','Resolusi versi alergi dari dua editor','PRODUCT DECISION REQUIRED','P1','Konsistensi informasi klinis; model final perlu keputusan','Doctor yang berwenang','Dua editor dapat menghasilkan versi aktif yang berbeda atas alergi sama; pengguna butuh tahu mana berlaku tanpa membuang riwayat.','Allergy edit A/B → append-only versions → interpretasi klinis',
 'Alergi persisten, severity, banner dan recorded_by_user_id tersedia. Perubahan substance/reaction/severity membuat record baru dan menonaktifkan record lama; previous_allergy_id ada pada metadata audit, bukan foreign key lineage pada PatientAllergy. RME memakai allergy_version guard. Bukti coverage lama menyimpan kedua perubahan; tidak ada klaim lost update.',
 'Tidak ada kontrak satu versi current per lineage, conflict resolution/merge atau cara eksplisit menandai cabang mana berlaku setelah sibling versions.',
 'Dokter berpotensi menafsirkan beberapa versi berbeda; bahaya klinis aktual tidak diuji/terbukti. Jangan sekadar last-write-wins atau menghapus versi.',
 'Dokter meninjau semua versi aktif dan mengonfirmasi melalui prosedur klinik; eskalasi konflik, tidak merekayasa satu versi lewat DB.',
 'Putuskan multi-assertion sah vs satu versi current per lineage. Jika satu current, gunakan expected-parent/version dan conflict UI dengan supersede terotorisasi, tetap simpan keduanya.',
 'M',[],'Dua tab dengan parent sama tidak diam-diam kehilangan salah satu input; UI menunjukkan konflik/semua assertion sesuai keputusan; resolusi menyimpan aktor/waktu/alasan dan lineage; critical warning konsisten; role nonmedis tidak mendapat akses; final RME tidak ditulis ulang.',
 [S['allergy'],'[Bukti coverage dan keputusan historis](../deep-functional-ux/REMAINING_GAPS.md)','[Matriks coverage alergi](../deep-functional-ux/COVERAGE_CLOSURE_MATRIX.md)'], 'Jarang pada concurrency, alergi dibaca setiap visit terkait','Inferensi dua tab/editor; bukti historis bukan ukuran prevalensi klinik.','Keputusan bisnis / integritas versi','D-13')
gap('PG-14','Diagnosis dan pemulihan data legacy tidak konsisten','PRODUCT DECISION REQUIRED','P1','Conditional: hanya jika data legacy terkait ditemukan; keberadaan pada data pengguna NOT VERIFIED','Platform Admin/support; Owner; Doctor untuk perubahan klinis','Pencegahan bug baru tidak menyelesaikan record lama yang mungkin telanjur tidak konsisten.','Deteksi mismatch → tinjauan → rencana perbaikan → audit',
 'Transaksi, locks, constraints dan fix DFU/CCL mencegah kasus baru. LegacyTenantBackfillService menangani owner/subscription legacy secara terbatas, bukan repair appointment/queue/visit/grant generik.',
 'Tidak ada dry-run/report ketidaksesuaian historis dan prosedur koreksi idempotent dengan persetujuan domain yang relevan.',
 'Jika ada data lama, staf tetap menemui exception. Audit ini tidak membuka/menghitung data pengguna sehingga tidak menyatakan korupsi aktual.',
 'Laporkan ID internal yang diperlukan melalui kanal dukungan; investigasi read-only berizin dan keputusan tertulis sebelum mutasi.',
 'Mulai detektor read-only untuk invariant spesifik bekas DFU/CCL, lalu desain repair per kategori; tidak membuat tombol perbaiki-semua atau seed ulang.',
 'M untuk diagnosis; L untuk repair',['PG-09','PG-10'],'Fixture legacy mismatch terdeteksi tanpa mutasi; record sehat tidak ditandai; output tanpa isi medis/token; rencana menunjukkan sebelum/sesudah dan persetujuan; repair per kategori transaksional/idempotent/audited dan mempertahankan histori; dry-run pengguna hanya setelah izin terpisah.',
 [S['backfill'],S['book'],S['queue'],S['sub'],'[Fix DFU](../deep-functional-ux/FIX_REGRESSION_REPORT.md)','[Fix CCL](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md)'], 'Sekali saat ditemukan/migrasi, lalu insidental','Inferensi risiko legacy; DB pengguna sengaja tidak diperiksa.','Recovery bersyarat / keputusan data','D-14')
gap('PG-15','Serah-terima akses staf dan delegasi admin','PARTIAL','P2','Invitation adalah peningkatan; provisioning admin tambahan perlu keputusan','Owner, Clinic Admin, Doctor, Receptionist','Pembuat akun memasukkan password awal dan belum punya invitation acceptance; admin operasional non-owner ada tetapi tidak dapat diprovision lewat form staf biasa.','Owner create staff → verifikasi → aktivasi tugas / delegasi admin',
 'Form membuat Doctor/Receptionist, email verification dan reset password tersedia; Registered dikirim setelah commit. Owner memakai role Clinic Admin. Role non-owner Clinic Admin ada pada fixture/source permission.',
 'Tidak ada undangan set-password yang terikat penerima/expired atau jalur UI delegasi/revoke Clinic Admin yang disepakati.',
 'Distribusi password awal dilakukan manual; delegasi berprivilege tinggi membutuhkan dukungan. Bukan berarti staf tidak dapat login/reset/verifikasi.',
 'Gunakan mekanisme reset password dan verifikasi existing; owner mengerjakan tugas admin. Hindari berbagi akun owner.',
 'Undangan set-password dapat menggantikan distribusi password awal. Pisahkan keputusan perlu tidaknya delegated admin; jangan menambah custom permission editor tanpa kebutuhan.',
 'M',[],'Undangan bila dipilih single-use/expired dan tidak mencetak password; seat/tenant/role tetap diverifikasi; email change/revoke membatalkan link sesuai kontrak; reset existing tidak regresi; hanya owner terotorisasi mendelegasi jika disetujui; tanpa akses medis tambahan.',
 [S['staff'],S['staffsvc'],S['perms'],ui('02'),ui('20'),'[SMTP/verifikasi historis](../production-readiness/INTEGRATION_TEST_REPORT.md)'], 'Setiap rekrutmen/pergantian staf; admin tambahan jarang','Inferensi lifecycle staf, jumlah hiring belum diketahui.','UX onboarding / keputusan akses','D-15')
gap('PG-16','Kontrak paket sesuai pembatasan yang benar-benar berlaku','PRODUCT DECISION REQUIRED','P2','Kejelasan penawaran komersial yang sudah dipakai','Owner, Platform Admin; seluruh role terdampak pembatasan','Katalog menyebut feature flags realtime/management_dashboard, sedangkan jalur akses tidak terlihat memakai flags tersebut.','Pilih paket → pahami hak → akses fitur',
 'Seats, kuota visit, expiry/read-only dan odontogram add-on memiliki guard nyata. SubscriptionPlanSeeder mencantumkan realtime/management_dashboard pada paket berbayar; pencarian app/routes tidak menemukan pemeriksaan kedua key tersebut.',
 'Belum ada keputusan apakah flags adalah hak eksklusif yang harus digate atau metadata penawaran yang perlu diperbarui.',
 'Ekspektasi owner bisa berbeda dari pembatasan aktual. Mematikan fitur Free secara spontan berisiko mengganggu alur yang sudah dipakai.',
 'Dukungan menjelaskan batas yang benar-benar enforced; jangan menjanjikan eksklusivitas tanpa kontrak yang diputuskan.',
 'Pilih koreksi copy/katalog atau enforcement transisi yang dikomunikasikan. Pertahankan hak baca/IN_PROGRESS dan data yang tersimpan, jangan mengasumsikan semua metadata harus menjadi paywall.',
 'S untuk copy; M untuk enforcement',[],'Matriks plan/trial/expired/suspended menyatakan hak yang sama pada katalog/UI/server; kontrak Free ditetapkan eksplisit; odontogram existing tidak berubah; downgrade tidak menghapus data atau memblokir finish IN_PROGRESS; test akses role/tenant untuk aturan yang disetujui.',
 [S['plans'],S['sub'],S['routes'],'[Inventaris pencarian first-party](evidence/source-scan.json)'], 'Setiap pemilihan paket dan akses fitur terkait','Inferensi kontrak komersial, bukan bukti komplain pelanggan.','Keputusan packaging','D-16')
gap('PG-17','Kandidat pasien lebih dari delapan mempunyai jalur lanjutan','PARTIAL','P2','Quick win UX pada kebutuhan deduplikasi yang sudah ada','Receptionist','Pencarian kandidat menampilkan delapan hasil tanpa menjelaskan apakah masih ada hasil lain.','Create patient → duplicate review → pilih profil existing',
 'Matching tenant-scoped NIK/nama+lahir/telepon, review token dan konfirmasi tersedia; nomor keluarga tidak dianggap identitas unik. UI-18: 10 profil dummy sesuai nomor, hanya 8 tautan kandidat.',
 'Tidak ada penanda batas/lebih banyak, pagination atau CTA hasil lengkap pada panel tersebut.',
 'Petugas dapat menganggap daftar lengkap dan membuat profil baru padahal pasien yang dicari di luar delapan; kejadian pada pasien nyata tidak diklaim.',
 'Batalkan create dan gunakan pencarian daftar pasien dengan informasi tambahan; tidak melakukan merge/account-link otomatis.',
 'Tampilkan bahwa hasil dibatasi dan tautkan pencarian pasien dengan kriteria aman; atau paginasi kandidat setelah kebutuhan dipastikan. Pertahankan review token dan batas anti-bocor.',
 'S',[],'Dengan 10 kandidat fixture, panel menjelaskan batas dan menyediakan jalur menemukan kandidat ke-9/10 lewat UI; pilihan profil tetap tenant-authorized; input form tidak hilang tanpa penjelasan; nomor bersama tidak memicu merge; review token/replay/telepon legacy tetap berlaku.',
 [S['dup'],ui('18'),'[Perbandingan 8 terlihat / 10 fixture](evidence/task-review.json)'], 'Sesekali pada nomor keluarga/nomor bersama','Inferensi dari fixture terkontrol; bukan ukuran banyaknya duplikasi produksi.','Peningkatan UX, bukan bug normalisasi CCL','Tidak perlu kebijakan identitas baru untuk copy + tautan')

gap('PG-18','Feedback submit pada form grant Platform Admin','PARTIAL','P2','Bug UI terverifikasi; idempotency backend DFU-12 tetap dipertahankan','Platform Admin','Tombol Berikan akses tetap enabled ketika form sudah submitting; console memuat Alpine SyntaxError.','Detail tenant → pilih paket/alasan → submit grant',
 'Form memiliki x-data submitting, handler submit=true, request UUID dan backend dedup. Tetapi atribut komponen x-clinic-button memakai x-bind:disabled="submitting || @js($subscriptionPrices->isEmpty())"; hasil DOM masih memuat @js literal.',
 'Binding disable tombol tidak berhasil diinisialisasi. UI-28 merekam submitting=true dan button_disabled=false pada submit; request sengaja dicegat/abort sebelum backend untuk membatasi audit pada UI.',
 'Feedback pending gagal dan klik berulang masih dimungkinkan oleh UI. Tidak ada bukti grant backend ganda; ini tidak membuka kembali DFU-12. Prioritas P2 sesuai dampak UX, bukan P0 finansial.',
 'Kirim sekali dan tunggu hasil; jika ragu periksa histori/invoice sebelum mencoba lagi. UUID backend existing menjadi pengaman, bukan alasan mengabaikan feedback.',
 'Saat tahap implementasi diizinkan, serialisasikan boolean di konteks Blade yang benar atau gunakan expression binding yang terkompilasi. Pertahankan disabled server saat katalog kosong dan request UUID/idempotency backend.',
 'S',[],'Rendered DOM tidak memuat directive @js mentah; tidak ada Alpine SyntaxError; valid submit menonaktifkan tombol selama pending; katalog kosong tetap disabled; retry/validation tidak dead-end; replay request backend tetap satu grant. Browser test memeriksa DOM state/error, bukan hanya jumlah periode.',
 [src('resources/views/platform/tenants/show.blade.php','x-bind:disabled'),src('resources/views/components/clinic-button.blade.php','attributes'),ui('28'),'[Console, DOM dan submit observer](evidence/page-error-triage.json)'],
 'Setiap Platform membuka form dan memberikan grant','Bukti fixture dua kali reload + submit observer; frekuensi grant nyata tidak diukur.','Bug implementasi UI','Tidak perlu aturan komersial baru; perbaikan source tidak diizinkan pada audit ini')

ids={g['id'] for g in gaps}
assert len(ids)==18
for g in gaps:
    assert set(g['deps']) <= ids
def visit_dep(id,trail=()):
    assert id not in trail, 'dependency cycle'
    for dep in next(g for g in gaps if g['id']==id)['deps']: visit_dep(dep,trail+(id,))
for id in ids: visit_dep(id)
dump('canonical-gaps.json',gaps)

cap=[]
def c(name,status,fact,evidence,refs='—'):
    cap.append([f'CAP-{len(cap)+1:02}',name,status,fact,'; '.join(evidence),refs])
C='COMPLETE';P='PARTIAL';M='MISSING';D='PRODUCT DECISION REQUIRED';N='NOT REQUIRED';V='NOT VERIFIED'
c('Autentikasi email/password, pemisahan pintu staf/pasien',C,'Login/logout, verifikasi dan reset tersedia; lifecycle lokal sudah dibuktikan. Tidak berarti provider email eksternal tersertifikasi.',[S['routes'],'[Lifecycle historis](../STAFF_LIFECYCLE_REPORT.md)'])
c('Registrasi Patient dan onboarding owner',C,'Akun publik Patient, owner eksplisit dan satu klinik/owner; bukan role Owner baru.',[src('app/Services/ClinicRegistrationService.php'),S['perms']])
c('Google OAuth eksternal',V,'Source callback tersedia; belum ada bukti end-to-end akun dummy provider yang diotorisasi.',[S['routes'],'[Gap provider](../deep-functional-ux/REMAINING_GAPS.md)'])
c('Lima role, policy dan tenant-scoped akses HTTP',C,'Policy/resource authorization tersedia, audit akses historis; tidak mengklaim socket lama otomatis dicabut.',[S['perms'],'[Role/permissions aktual](evidence/roles-permissions.json)'])
c('Revokasi socket aktif',P,'PR-01 OPEN terpisah: HTTP revoked tetapi socket lama menerima event nonmedis; tidak diperbaiki audit ini.',[S['channels'],'[PR-01](../production-readiness/NEW_FINDINGS.md)'],'PR-01 (risiko, bukan gap baru)')
c('Delegasi admin dan invitation staf',P,'Doctor/Receptionist dapat dibuat dengan password awal; reset/verification ada, invitation/delegated admin belum.',[S['staff'],ui('20')],'PG-15')
c('Profil akun dan deaktivasi staf',C,'Edit profil/reset/password, status staf, invalidasi sesi HTTP tersedia. Owner self-delete dibatasi sesuai relasi.',[S['staffsvc'],'[Lifecycle](../STAFF_LIFECYCLE_REPORT.md)'])
c('Custom permission editor',D,'Spatie dan permission seeder bukan UI manajemen role custom. Belum ada kebutuhan delegasi granular yang divalidasi.',[S['perms'],S['routes']],'D-17')
c('Profil, logo dan status aktif klinik',C,'Owner/Admin dapat edit profil dan is_active; logo public, foto medis tetap private.',[S['clinic'],ui('12')])
c('Timezone klinik dan jam operasional dasar',C,'Setting waktu dan tampilan timezone telah diperbaiki DFU/CCL; booking baru memakai jam yang berlaku.',[S['clinic'],S['book'],'[Fix terkini](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md)'])
c('Libur, cuti, jam khusus berbasis tanggal',P,'Ada jadwal mingguan/aktivasi; tidak ada kalender exception dan impact preview.',[S['schedule'],ui('13')],'PG-02')
c('Layanan, harga dan asosiasi dokter',C,'CRUD aktif/nonaktif dan validasi nominal FE/BE CCL-01, layanan dokter tersedia.',[src('app/Http/Controllers/Admin/ServiceController.php'),src('app/Http/Controllers/Admin/StaffController.php')])
c('Jadwal mingguan, kuota dan benturan sesi',C,'DoctorSchedule, validasi hari dan sesi serta kuota booking tersedia; pengecualian tanggal terpisah.',[S['schedule'],S['book']])
c('Pendaftaran pasien, telepon dan kandidat duplikat',C,'Validasi/normalisasi pencarian-simpan terkini; matching bersifat kandidat bukan account linking otomatis.',[S['inactive'],S['dup'],'[CCL](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md)'])
c('Daftar kandidat duplikat lengkap',P,'Panel dibatasi delapan hasil tanpa jalur lanjutan yang jelas; UI-18 memakai sepuluh fixture.',[S['dup'],ui('18')],'PG-17')
c('Kebijakan patient nonaktif lintas kanal',D,'Staf ditolak, pasien sendiri dapat booking profil nonaktif yang sama. Arti status belum diseragamkan.',[S['inactive'],ui('22')],'PG-06')
c('Booking online guest, Patient, staf/walk-in',C,'Workflow transactional sama, clinic/doctor/service/schedule validation dan snapshot; walk-in adalah booking oleh staf lalu check-in.',[S['book'],ui('07'),ui('21-R2')])
c('Jalur booking dari portal Patient',C,'CTA booking baru, klinik terkait dan clinic_code tersedia; tidak mensyaratkan hafal URL.',[S['patiententry'],ui('11')])
c('Status booking rahasia dan akses milik Patient',C,'Guest token hash dan policy appointment; tidak menampilkan token pada laporan ini.',[S['routes'],'[DFU fix](../deep-functional-ux/FIX_REGRESSION_REPORT.md)'])
c('Pencegahan replay/booking ganda dan kuota',C,'UUID/locks/constraint/idempotency backend tersedia dan bukti historis; bukan pengujian concurrency MySQL baru.',[S['book'],'[Batas bukti](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md)'])
c('Cancel/no-show sebelum visit dan histori',C,'Status/alasan diatur domain; cancel after start ditolak untuk integritas.',[S['queue'],'[DFU-01](../deep-functional-ux/FIX_REGRESSION_REPORT.md)'])
c('Reschedule, salah dokter/layanan dan reassignment',P,'Cancel + booking baru tersedia sebagai tindakan terpisah; tidak ada perubahan terpandu atau hubungan pengganti.',[ui('07'),S['book']],'PG-01')
c('Check-in, FIFO panggil, skip dan return',C,'Transisi BOOKED/WAITING/CALLED, skip-return dan satu aktif per sesi tersedia.',[src('app/Services/QueueWorkflowService.php'),ui('08')])
c('Display publik antrean nonmedis',C,'Public queue-board menampilkan nomor/status tanpa identitas/isi medis.',[src('app/Http/Controllers/PublicQueueController.php'),'[Bukti historis](../deep-functional-ux/FINAL_COVERAGE_REPORT.md)'])
c('ETA dan informasi stale/confidence',C,'QueueEtaService terhubung dan historis diuji; bukan janji waktu tunggu pasti.',[src('app/Services/QueueEtaService.php'),'[Coverage ETA](../deep-functional-ux/COVERAGE_CLOSURE_MATRIX.md)'])
c('Kiosk/filter khusus display',D,'Display ada; tidak ada kontrol kiosk khusus. Kebutuhan perangkat/filter belum terbukti wajib.',[S['routes'],'[G29](../deep-functional-ux/REMAINING_GAPS.md)'],'D-18')
c('Realtime, refetch dan polling recovery',C,'Alur tersedia dan recovery sebelumnya dibuktikan; filter/cursor CCL-07 tetap dipertahankan; PR-01 terpisah.',[src('resources/js/realtime.js'),'[CCL](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md)'])
c('Start/resume dan finalisasi visit atomik',C,'Visit dibuat saat dokter start dari CALLED; draft/final appointment/queue bersama.',[S['visit'],ui('09')])
c('Abort/interrupted/handover visit',D,'Hanya IN_PROGRESS/COMPLETED; tak ada transisi recovery selain resume/complete sah.',[S['visitstatus'],ui('09')],'PG-03')
c('SOAP, delapan vital, diagnosis/tindakan terstruktur',C,'Editor dan persistence backend tersedia; catatan lama yang menyebut array tidak ada sudah usang.',[S['clinicalui'],S['visit']])
c('Autosave nyata dan optimistic conflict',C,'Debounce, endpoint, lock_version/allergy_version dan conflict/retry tersedia, bukan hanya status visual.',[S['clinicalui'],src('resources/js/rme-autosave.js'),'[CCL](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md)'])
c('Recovery editor offline / 401 / 419',C,'Pesan expired vs koneksi, teks/file pending bertahan sebelum reload; login tab baru + copy/reload/paste manual. Tidak menjanjikan file bertahan melewati reload.',[src('resources/js/rme-autosave.js'),'[Bukti 401/419](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md)'])
c('Alergi persisten, severity dan banner kritis',C,'PatientAllergy nyata, append-only dan aktor tersedia; bukan array kosong.',[S['allergy'],src('app/Models/PatientAllergy.php')])
c('Resolusi versi alergi antar-editor',D,'Append-only mempertahankan kedua input, current lineage belum diputuskan.',[S['allergy'],'[G38/Gaps](../deep-functional-ux/REMAINING_GAPS.md)'],'PG-13')
c('Resep terstruktur dan preview final',C,'Item dosis/frekuensi/quantity/instruksi tersimpan; Patient hanya resep final miliknya.',[S['clinicalui'],S['patient'],ui('10')])
c('Foto klinis private dan authorization',C,'Upload, storage private dan endpoint ber-policy tersedia, bukti browser historis.',[src('app/Http/Controllers/Doctor/VisitController.php','function photo'),'[Integrasi](../production-readiness/INTEGRATION_TEST_REPORT.md)'])
c('Odontogram persistent + entitlement',C,'32 gigi/6 kondisi tersimpan; paid dummy sah historis, expired read-only dan akses dokter; 192 cell historis PASS bukan run baru.',[S['clinicalui'],src('app/Services/AddonService.php'),'[Coverage dental](../deep-functional-ux/FINAL_COVERAGE_REPORT.md)'])
c('Koreksi RME final dan audit revisi',C,'Alasan minimal 10, snapshot before/after, actor/time dan status tetap final.',[S['visit'],src('app/Models/MedicalRecordRevision.php'),ui('10')])
c('Riwayat medis sesuai dokter yang diotorisasi',C,'Panel recent visit terbatas, daftar RME paginated/search tersedia; batas assigned doctor disengaja, bukan admin medical bypass.',[S['rmehistory'],src('app/Policies/MedicalRecordPolicy.php')])
c('Riwayat appointment dan resep Patient',C,'Patient mempunyai booking/history dan resep final; ini tidak memberi full SOAP otomatis.',[S['patiententry'],S['patient'],ui('05')])
c('Follow-up terstruktur dan booking berikutnya terkait',P,'Plan teks dan booking baru terpisah; tidak ada closed-loop task.',[S['clinicalui'],ui('10'),ui('11')],'PG-04')
c('Dokumen klinis printable/download yang disetujui',P,'Resep preview tersedia; format penerbitan/print/export dan kontrak penerima belum ada.',[S['routes'],S['patient']],'PG-07')
c('Billing/receipt pembayaran layanan pasien',M,'Tidak ada model/tabel/route kasir pasien. Payment adalah SaaS.',[S['dash'],'[Schema](evidence/schema.json)'],'PG-05')
c('Dashboard Owner dan Clinic Admin',C,'Metrik operasional, filter periode, shortcut dan service value tersedia; tidak mengklaim cash.',[S['dash'],ui('01'),ui('02')])
c('Dashboard Receptionist / Doctor / Patient / Platform',C,'Titik mulai sesuai persona dan tautan pekerjaan tersedia; task review agen bukan validasi manusia.',[ui('03'),ui('04'),ui('05'),ui('06')])
c('Empty activity dashboard tenant',P,'Owner/Rec/Doctor/Patient teramati dengan aktivitas kosong; fixture Free over-seat bukan onboarding klinik benar-benar kosong; Admin non-owner empty tidak ditinjau tersendiri.',[ui('24'),ui('25'),ui('26'),ui('27')],'G47: sebagian bukti baru, batas tetap')
c('Dashboard Platform dengan nol tenant dan kontrak loading',V,'Template @empty ada; DB klon punya tenant. Tidak menghapus fixture untuk menciptakan global kosong. Blade SSR tidak otomatis membutuhkan skeleton aplikasi.',[src('resources/views/platform/dashboard.blade.php','Belum ada tenant')],'G47: NOT VERIFIED')
c('Audit log metadata dan filter',C,'Activity filter tenant/actor/action/date dan pagination tersedia; bukan viewer full medical contents.',[S['audit'],ui('16')])
c('Laporan periode dan export operasional',P,'Dashboard/audit dapat dibaca, export belum tersedia.',[S['dash'],S['routes']],'PG-08')
c('Notifikasi event booking/queue dan inbox',C,'Database + job broadcast, recipient recheck, read/read-all inbox tersedia; bukan reminder proaktif.',[S['notif'],ui('19')])
c('Near-turn / scheduled appointment reminder',P,'Notifikasi perubahan status dan ETA tersedia; threshold/reminder terjadwal janji belum.',[S['cron'],S['notif'],src('PRD.md','NOTIF-3')],'PG-12')
c('Free/Individual/Team, trial once, seats/kuota',C,'Plan dan period domain aktif; Free 1 dokter/1 staf/50 visit, paid seats; trial once, expiry/read-only dan finish IN_PROGRESS exception.',[S['plans'],S['sub']])
c('Katalog harga durasi dan invoice snapshot SaaS',C,'Plan/price aktif, snapshot harga/periode, empty katalog CCL-08 tersedia.',[S['plans'],src('app/Services/DokuPaymentService.php')])
c('Flags realtime/management_dashboard vs hak akses',D,'Metadata ada tetapi enforcement key tidak ditemukan pada app/routes; keputusan copy vs entitlement perlu.',[S['plans'],S['sub']],'PG-16')
c('Odontogram add-on prorata dan histori owner',C,'Quote per sisa periode, checkout, revoke add-on dan daftar period/invoice tersedia.',[src('app/Services/AddonService.php'),S['addonhist'],ui('23')])
c('DOKU checkout dan reconciliation pending sandbox',C,'Bukti HTTPS sandbox nyata 11 Nov; pending tidak mengaktifkan subscription. Tidak dijalankan ulang dengan kredensial pengguna.',[src('app/Services/DokuPaymentService.php'),'[Integrasi sandbox](../production-readiness/INTEGRATION_TEST_REPORT.md)'])
c('Provider settlement/callback/refund dana E2E',V,'Handler dan test fake ada; callback eksternal/settlement/refund dana belum terbukti end-to-end. Manual recording bukan funds transfer.',[S['refund'],'[Integrasi](../production-readiness/INTEGRATION_TEST_REPORT.md)'])
c('Reminder subscription dan worker delivery lokal',C,'Scheduler command dan dedup reminder tersedia, loopback SMTP+worker historis PASS; bukan bukti inbox publik.',[S['cron'],'[Integrasi SMTP](../production-readiness/INTEGRATION_TEST_REPORT.md)'])
c('Platform tenant listing/current effective status',C,'Metadata tenant, current subscription, grants dan status efektif CCL-06 tersedia tanpa akses isi medis.',[S['tenant'],ui('14')])
c('Grant subscription idempotent dan Free downgrade',C,'Request UUID/replay dan transaction mencegah double-click, Free downgrade mengakhiri period terkait sesuai kontrak.',[S['sub'],'[DFU-12](../deep-functional-ux/FIX_REGRESSION_REPORT.md)'])
c('Commercial history viewer lengkap',P,'Invoice list/detail, audit dan owner addon history ada; Platform full main-period timeline belum.',[S['tenant'],S['addonhist']],'PG-09')
c('Single grant revoke / recovery duplikat historis',D,'Tidak ada UI satu periode; global downgrade bukan undo aman per grant.',[S['tenantui'],S['sub']],'PG-10')
c('Manual refund pencatatan dan akses',P,'Payment refunded + add-on revoked tersedia; refund provider/kontrak paket utama dan recovery butuh keputusan.',[S['refund'],ui('15')],'PG-10')
c('Transfer owner / tenant suspend / closure',D,'Clinic active switch ada, tetapi tidak sama dengan seluruh lifecycle SaaS.',[S['tenantui'],S['clinic']],'PG-11')
c('Deteksi/repair legacy inkonsisten',D,'Backfill owner/subscription terbatas ada; repair generik tidak ada dan DB pengguna tidak diperiksa.',[S['backfill']],'PG-14')
c('Discovery klinik / multi-cabang / offline Edge',D,'RPP menyebut, source belum menyediakan; bukan wajib MVP terverifikasi. Patient code/related clinic sudah tersedia.',[S['patiententry'],'[RPP](evidence/rpp-reference.txt)'],'D-19')
c('Aplikasi native / migrasi React / SPA',N,'Bertentangan dengan scope web Blade/Tailwind saat ini; responsive web sudah ada.',[src('PROJECT_ANALYSIS.md','full Laravel'),'[RPP](evidence/rpp-reference.txt)'])
c('Platform impersonation / admin full medical bypass',N,'Tidak diperlukan untuk tugas komersial/metadata; melanggar boundary akses yang disepakati.',[src('app/Policies/MedicalRecordPolicy.php'),S['perms']])
c('Hard delete histori klinis / pembayaran',N,'Bukan kemampuan yang perlu ditambah untuk recovery. Gunakan status/revision/compensating adjustment.',[S['visit'],S['refund']])
c('Katalog/interaksi obat, inventori, asuransi/claim eksternal',D,'Resep free-text terstruktur ada; belum ada requirement MVP tervalidasi untuk domain tambahan ini.',[S['clinicalui'],src('docs/prescription-workflow/README.md')],'D-19')
c('First-use usability dan aksesibilitas manusia',V,'Task review agen dan bukti DOM historis tersedia; peserta awam, screen reader/perangkat fisik belum diuji.',[ui('11'),'[Batas studi](../production-readiness/HUMAN_UAT_REPORT.md)'])
c('Feedback pending tombol grant Platform',P,'Ekspresi @js literal memicu Alpine SyntaxError; saat submit tombol tetap enabled. Backend UUID/dedup tetap ada, tidak dibuktikan grant ganda.',[src('resources/views/platform/tenants/show.blade.php','x-bind:disabled'),ui('28'),'[Triage](evidence/page-error-triage.json)'],'PG-18')
dump('capabilities.json',cap)

stats=collections.Counter(x[2] for x in cap)
prior=collections.Counter(g['priority'] for g in gaps)
common='Tanggal audit: **14 November 2026**. Audit produk read-only; tidak ada implementasi/perbaikan. Fakta source/UI dipisahkan dari inferensi kebutuhan dan rekomendasi. Semua UI menggunakan data dummy pada SQLite terisolasi. '
definitions='''
| Status | Makna dalam audit produk |
| --- | --- |
| COMPLETE | Kemampuan terbatas yang disebut tersedia end-to-end dalam source, dengan bukti UI/regression aktual atau historis terkait. Bukan klaim seluruh produk selesai atau seluruh kombinasi telah diuji ulang. |
| PARTIAL | Sebagian proses tersedia, langkah lanjutan/recovery/informasi belum lengkap. |
| MISSING | Kemampuan tidak ditemukan pada domain/schema/routes/UI yang diinventarisasi. Kewajiban implementasinya tetap bergantung scope. |
| NOT REQUIRED | Tidak diperlukan pada scope saat ini atau melanggar batas produk yang telah ditetapkan. |
| PRODUCT DECISION REQUIRED | Kebijakan/batas fitur belum disepakati; tidak aman menetapkan implementasi otomatis. |
| NOT VERIFIED | Bukti operasional/usability yang dibutuhkan belum tersedia. Tidak disamakan dengan fitur tidak ada. |

**FAKTA** = source atau observasi fixture/bukti run yang dirujuk. **INFERENSI KEBUTUHAN** = dugaan tugas/frekuensi/nilai yang masuk akal tetapi belum divalidasi manusia. **REKOMENDASI** = opsi desain, bukan keputusan atau izin implementasi.
'''
scope='''
Urutan acuan: permintaan pengguna dan invariant tenant/medis → source terkini → bukti QA terbaru per fitur → dokumen historis. PRD/Plan lama menempatkan banyak fitur pada post-MVP; Development-Plan berfokus SaaS dan mengecualikan billing pasien; RPP web-only lebih luas mencakup kasir, cabang, discovery/Edge serta menyebut React. Tidak menyatukan semuanya sebagai daftar wajib. Stack tetap Laravel/Blade/Tailwind. Audit tidak mengubah PRD, keputusan komersial atau kebijakan klinis.

Tidak ada skor “persentase produk selesai”: denominator kebutuhan klinik belum disepakati dan menghitung halaman/route tidak mengukur penyelesaian tugas. Hitungan kemampuan di sini adalah inventaris, bukan coverage functional atau skor readiness.
'''

write('PRODUCT_CAPABILITY_INVENTORY.md',f'''# Inventaris kemampuan aktual ClinicOS

{common}

{definitions}

{scope}

## Bukti dan cakupan penelusuran

Inventaris **{len(baseline)} file baseline** first-party beserta manifest/hash `.env` (isinya tidak dibaca/dicetak untuk laporan), **{len(routes)} route**, **{len(schema)} tabel aplikasi/framework**, **{len(roles)} role tersimpan**. Meliputi controllers, FormRequests, policies, middleware, services, models/enum, migrations/factories/seeders, jobs/events/notifications/commands, Blade/Volt, JS/CSS/config dan tests terkait. Vendor, node_modules, generated assets, data pasien pengguna dan infrastruktur deployment bukan objek pembacaan domain menyeluruh.

Seluruh route dan form statis: [routes](evidence/routes.json), [source methods/forms/field names](evidence/source-scan.json). Database yang diperiksa adalah schema clone fixture, bukan isi database pengguna: [44 tabel dan relasi](evidence/schema.json). Lima role dan permission aktual: [snapshot](evidence/roles-permissions.json). Sebanyak {len(docs)} dokumen produk/arsitektur dan {len(qa)} laporan QA diindeks dan ditinjau klaim relevannya: [produk](evidence/product-doc-index.json), [QA + disposisi](evidence/qa-review-ledger.json). RPP dibaca sebagai teks paragraf/tabel: [ekstrak](evidence/rpp-reference.txt); diagram tersemat tidak divalidasi visual.

## Kemampuan

{table(['ID','Kemampuan / batas','Status','FAKTA dan batas interpretasi','Bukti','Gap / keputusan'],cap)}

## Hitungan status

{table(['Status','Jumlah'],stats.items())}

Total **{len(cap)} kemampuan terdefinisi**, tidak dibobot sama dan tidak digunakan sebagai persentase kelengkapan. Status COMPLETE tetap mempertahankan batas pada selnya. Ketidakadaan route saja tidak cukup: gap kasir/reschedule/print ditriangulasi dengan schema, service/controller, formulir dan task UI.
''')

role_rows=[
 ('Clinic Owner (role Clinic Admin + account_type/owner relation)',
  'Menyiapkan dan mengelola satu klinik serta kontrak SaaS.',
  'Memantau layanan, staff, kapasitas, subscription dan exception operasional.',
  'Aktivitas/status agregat, jam/sesi, seats/kuota, invoice dan akses add-on; bukan isi SOAP.',
  'Edit clinic/layanan/staf/jadwal; aktivasi/nonaktif; beli paket/add-on, baca audit.',
  'Tenant sendiri dan owner-only commercial endpoints; tidak otomatis isi medis.',
  'Mendelegasikan operasional ke Admin/Rec, kapasitas dengan Doctor, kontrak dengan Platform.',
  'Cek dashboard, ubah jadwal, pantau kuota dan reminder; frekuensi belum diukur.',
  'Katalog kosong, limit seats, expired/suspended sudah punya jalur; jam berubah perlu review booking existing.',
  'Kalender khusus, laporan export, lifecycle owner dan tugas kasir jika dipilih.',
  'Koordinasi libur, laporan/kas eksternal dan permintaan transfer melalui dukungan.',
  'UI-01,12,13,16,20,23,24','PG-02,05,08,09,11,15,16'),
 ('Clinic Admin non-owner',
  'Menjalankan administrasi tenant tanpa mengambil hak komersial owner.',
  'Mengatur staf dokter/resepsionis, layanan, jadwal, clinic settings dan aktivitas.',
  'Metrik dan resource klinik, alasan pembatasan paket; owner sebagai kontak pemulihan.',
  'Aksi administrasi yang diberi permission; gunakan owner untuk pembelian/delegasi yang belum tersedia.',
  'Tenant-scoped; tidak memperoleh SaaS owner actions atau isi medis hanya karena admin.',
  'Owner menentukan kapasitas/kontrak; Rec mengoperasikan booking; Doctor bertanggung jawab klinis.',
  'Kelola jadwal/layanan/staf; navigasi admin sama basis owner dengan hak berbeda.',
  'Akun nonaktif/session expired dan seats; tidak mengatasi limit lewat role baru.',
  'UI provisioning admin tambahan belum ada; perubahan appointment dan impact jadwal belum terpandu.',
  'Owner melakukan provisioning, komunikasi perubahan kalender secara manual.',
  'UI-02; source permission/policy','PG-01,02,08,11,15'),
 ('Receptionist',
  'Membuat pasien/booking, menerima kedatangan dan mengatur antrean.',
  'Cari pasien, periksa kandidat duplikat, booking, check-in, call/skip/return/cancel/no-show.',
  'Identitas administrasi, status aktif, jadwal/sesi/kuota, nomor dan posisi; tidak isi medis.',
  'Buat/edit patient yang diizinkan; pilih profil existing; jalankan transisi pra-visit.',
  'Resource klinik sendiri; cancel tidak boleh menghilangkan visit aktif/final.',
  'Menerima kedatangan Patient, berkoordinasi Doctor untuk mulai, Owner/Admin untuk kapasitas.',
  'Pencarian, booking, check-in/call berulang per pasien (inferensi urutan kerja, bukan volume terukur).',
  'Inactive patient menjelaskan reaktivasi; duplicate review/queue conflict/error tidak boleh memicu profil baru.',
  'Reschedule/reassign, dokter absen, follow-up scheduling terhubung, kasir bila scope dipilih.',
  'Cancel-rebook pra-visit, telepon pasien, cari seluruh daftar pasien, gunakan kas eksternal.',
  'UI-03,07,08,17,18,22,25','PG-01,02,03,04,05,06,12,17'),
 ('Doctor',
  'Melayani pasien yang ditugaskan dan menyelesaikan catatan yang akurat.',
  'Pantau antrean, start, lihat histori berizin, draft/autosave SOAP/vital/diagnosis/tindakan/resep, final/koreksi.',
  'Identitas relevan, alergi/versi, histori yang diotorisasi, status save/conflict, entitlement dental.',
  'Edit draft, foto private, odontogram bila berhak, acknowledgement, final/correction beralasan.',
  'Assigned visit/RME dan histori sesuai policy; owner/platform tidak menjadi dokter pengganti otomatis.',
  'Rec mengatur kedatangan, Patient menerima resep/instruksi, Owner menjaga entitlement tanpa membaca medis.',
  'Per visit: review → catat → final; alergi dibaca saat relevan; actual frequency belum diukur.',
  '401/419/offline/conflict mempertahankan editor; finish visit IN_PROGRESS tetap diizinkan saat paket terbatas.',
  'Interupsi/handover, resolusi sibling allergy, dokumen pasien dan follow-up tertutup.',
  'Koordinasi manual, prosedur klinik luar sistem, instruksi kontrol dan dokumen eksternal.',
  'UI-04,09,10,26; bukti dental historis','PG-03,04,07,13'),
 ('Patient',
  'Booking dan mengetahui kedatangan/giliran serta resep final miliknya.',
  'Pilih klinik terkait/kode, booking, lihat/cancel pra-visit, pantau antrean dan notification center.',
  'Informasi klinik, jadwal, status booking/giliran, alasan kegagalan, instruksi yang diizinkan.',
  'Aksi hanya melalui profil/appointment miliknya; guest memakai secret booking token.',
  'Kepemilikan patient.user_id; resep final milik sendiri; tidak full SOAP atau pasien lain.',
  'Rec untuk perubahan/aktivasi, Doctor untuk klinis, Owner tidak menjadi penerima semua isi medis.',
  'Per episode booking/kunjungan dan monitoring; belum ada pengukuran perilaku nyata.',
  'Session expiry dan validasi; nonaktif antar kanal belum konsisten; kode klinik perlu diketahui pada first use.',
  'Reschedule terpandu, reminder proaktif, dokumen terbit dan follow-up terhubung.',
  'Hubungi klinik, pantau portal/display, booking baru manual; jangan menganggap directory wajib.',
  'UI-05,11,19,22,27','PG-01,04,06,07,12'),
 ('Platform Admin',
  'Mengelola metadata tenant, penawaran SaaS dan exception komersial.',
  'Lihat tenant/current effective status, katalog, invoice/payment, reconcile/grant/manual refund dan audit.',
  'Owner mapping, paket/period/source, invoice/events dan alasan perubahan; tanpa catatan medis.',
  'Grant idempotent/Free, ubah catalog, reconcile provider dan record refund beralasan sesuai izin.',
  'Permission platform.*; tidak impersonation atau bypass medical policy.',
  'Owner untuk kontrak, provider untuk pembayaran, support untuk legacy correction dengan persetujuan.',
  'Monitoring tenant/payment periodik dan ticket exception insidental (inferensi).',
  'Provider pending/review/expired dan kegagalan reconcile; jangan aktivasi hanya dari browser return.',
  'Timeline utama, single-grant recovery, lifecycle tenant, diagnosis legacy dan kontrak refund dana/akses.',
  'Kombinasi invoice/audit/manual support; funds refund eksternal; tanpa edit DB spontan.',
  'UI-06,14,15; global-empty belum browser','PG-09,10,11,14,16'),
]
role_text=''
labels=['Tujuan utama','Tanggung jawab harian','Informasi yang dibutuhkan','Tindakan','Akses seharusnya','Hubungan antar-role','Proses berulang','Kesalahan / pemulihan','Tugas belum didukung','Langkah manual']
for row in role_rows:
    role_text+=f'\n## {row[0]}\n\n'+table(['Dimensi','Analisis'],zip(labels,row[1:11]))+f'\nBukti: {row[11]}; [task review](evidence/task-review.json), {S["perms"]}. Gap terkait: **{row[12]}**. Kebutuhan/frekuensi adalah inferensi agen sampai divalidasi pemilik peran.\n'
write('ROLE_NEEDS_ANALYSIS.md',f'''# Kebutuhan seluruh role

{common}

FAKTA discovery otomatis: **{', '.join(r['name'] for r in roles)}**. Owner dipisahkan sebagai persona karena `account_type=clinic_owner` + `clinics.owner_user_id`, tetap memakai role Clinic Admin. Tidak menciptakan role/permission baru. Daftar kebutuhan di bawah adalah analisis tugas, bukan bukti wawancara manusia.

{role_text}

## Aktor di luar stored roles

Guest dapat melihat klinik/display dan booking/status dengan token; tidak otomatis dicocokkan identitas lama. Scheduler/worker mengirim reminder/delivery, provider melakukan validasi pembayaran, support bukan role medis baru. Kasir belum role tersimpan; bila PG-05 disetujui, putuskan apakah Receptionist atau role terpisah berdasarkan pemisahan tugas, jangan menciptakannya sekarang.

## Batas akses yang tidak menjadi gap

Owner/Admin/Rec/Platform tidak berhak membuka seluruh SOAP/foto hanya agar workflow administrasi lebih cepat. Akses histori lintas dokter juga memerlukan keputusan klinis tersendiri; daftar RME paginated sudah tersedia bagi dokter yang diotorisasi. Editor permission generik bukan prasyarat menjalankan lima role yang ada. PR-01 tetap risiko revokasi socket terpisah.
''')

workflow_rows=[
 ['W-01','Owner/Admin → klinik → layanan → staf → jadwal → buka booking','COMPLETE untuk setup dasar; PARTIAL exception tanggal','Setting, active switch, seats, jadwal mingguan; jam baru menjaga booking baru.','PG-02; contoh UI-12/13'],
 ['W-02','Guest/Patient/Rec → pasien → sesi → booking → queue BOOKED','COMPLETE jalur dasar; kebijakan inactive belum seragam','Snapshot/idempotency/kuota dan portal booking tersedia.','PG-06,17; UI-11/18/22'],
 ['W-03','Booking salah / dokter absen → pilih pengganti → konfirmasi','PARTIAL','Cancel pra-visit + booking baru terpisah; tidak ada hubungan/preview reschedule.','PG-01,02; UI-07/08'],
 ['W-04','BOOKED → WAITING → CALLED → IN_PROGRESS → COMPLETED','COMPLETE jalur utama','Skip/return/no-show/cancel pra-visit, satu aktif, start oleh dokter, finalisasi atomik.','DFU dipertahankan; UI-08/09'],
 ['W-05','IN_PROGRESS → dokter berhalangan / pelayanan terhenti','PRODUCT DECISION REQUIRED','Tidak ada abort/handover; draft tidak boleh dipaksa completed atau dibatalkan via DB.','PG-03; UI-09'],
 ['W-06','Histori berizin → alergi → draft → diagnosis/tindakan/resep/foto/dental → final','COMPLETE jalur dasar; versi allergy belum selesai','Autosave/lock conflict nyata, add-on sah dan private policy; perlu resolusi dua editor.','PG-13; UI-09/10 + coverage dental historis'],
 ['W-07','Final → koreksi beralasan → revision tetap final','COMPLETE','Snapshot before/after dan aktor/waktu; tidak hard delete catatan final.','VisitWorkflowService + bukti fix historis'],
 ['W-08','Final → resep portal → dokumen / follow-up / next booking','PARTIAL','Resep final terlihat; dokumen terbit dan follow-up terhubung belum.','PG-04,07; UI-05/10/11'],
 ['W-09','Visit selesai → tagihan → kas → kuitansi → rekonsiliasi','MISSING bersyarat scope RPP','Visit.total_amount hanya service value; bukan kas pasien.','PG-05; UI-17 + schema'],
 ['W-10','Owner → dashboard/audit → laporan keputusan periode','PARTIAL','Metrik dan filter tersedia, export/reconciled report belum.','PG-08; UI-01/16'],
 ['W-11','Owner → paket/add-on → invoice → checkout → valid payment → entitlement','Source/backend tersedia; settlement eksternal NOT VERIFIED','Pending checkout/reconcile sandbox historis nyata; return browser tidak mengaktifkan.','Provider evidence terpisah; PG-16 packaging'],
 ['W-12','Platform → histori tenant → koreksi grant/refund → akses konsisten','PARTIAL / PRODUCT DECISION REQUIRED','Current effective status/invoice/refund recording tersedia; full timeline dan single-period recovery belum.','PG-09/10; UI-14/15/23'],
 ['W-13','Tenant aktif → ownership change / suspend / closure / restore','PRODUCT DECISION REQUIRED','Clinic deactivation memblokir booking, bukan seluruh lifecycle kontrak.','PG-11; UI-12/14'],
 ['W-14','Record lama mismatch → deteksi → keputusan → repair teraudit','PRODUCT DECISION REQUIRED; real user incidence NOT VERIFIED','Backfill terbatas ada; tidak memodifikasi database pengguna.','PG-14'],
]
write('CLINIC_WORKFLOW_MAP.md',f'''# Peta workflow operasional

{common}

## Lifecycle nyata dan cabang yang belum lengkap

```mermaid
flowchart TD
    Setup[Owner/Admin: klinik, layanan, staf, jadwal] --> Booking[Guest/Patient/Rec: booking transactional]
    Setup -. PG-02 kalender exception .-> Impact[Review booking terdampak]
    Booking --> B[BOOKED]
    B --> W[WAITING setelah check-in]
    W --> C[CALLED]
    C --> I[IN_PROGRESS: dokter start visit]
    W --> S[SKIPPED]
    C --> S
    S --> W
    B --> X[CANCELLED / NO_SHOW sebelum visit]
    W --> X
    C --> X
    Booking -. PG-01 .-> Rebook[Reschedule/reassign terpandu]
    I --> RME[Draft/autosave: SOAP, vital, alergi, Rx, foto, dental]
    RME --> F[Finalisasi atomik: RME FINAL, visit/appointment/queue COMPLETED]
    I -. PG-03 keputusan .-> Interrupt[Interrupted / handover]
    F --> Rev[Koreksi beralasan: histori revisi, tetap FINAL]
    F --> Rx[Patient: resep final milik sendiri]
    Rx -. PG-07 .-> Doc[Dokumen pasien yang disetujui]
    F -. PG-04 .-> Follow[Tindak lanjut dan booking berikutnya terkait]
    F -. PG-05 scope RPP .-> Bill[Billing pasien, kas, receipt]
    F --> Metrics[Owner/Admin: service value dan audit]
    Metrics -. PG-08 .-> Reports[Laporan periode dan export]
```

Diagram adalah peta alur bisnis ringkas, bukan semua guard enum. Otorisasi dan QueueWorkflowService menentukan cabang cancel/no-show yang benar-benar diizinkan; **tidak ada panah I → CANCELLED**. Garis putus-putus adalah kebutuhan/keputusan, belum implementasi.

{table(['ID','Perjalanan / handoff','Status aktual','Yang tersedia / batas','Gap / bukti'],workflow_rows)}

## Lifecycle SaaS terpisah dari pelayanan

```mermaid
flowchart LR
    Owner[Owner] --> Plan[Paket / add-on]
    Plan --> Invoice[Invoice SaaS snapshot]
    Invoice --> Pending[Checkout / pending]
    Pending --> Valid[Callback atau reconcile tervalidasi]
    Valid --> Period[SubscriptionPeriod / AddonPeriod]
    Period --> Effective[Status efektif + seats/kuota + hak edit]
    Effective --> Expiry[Expired/suspended: batas tulis, baca tetap berpolicy]
    Expiry --> Finish[Visit IN_PROGRESS boleh diselesaikan]
    Period -. PG-09 .-> Timeline[Timeline histori komersial]
    Timeline -. PG-10 .-> Adjust[Koreksi satu grant / refund dan akses]
```

Pembayaran subscription tidak menjadi pembayaran kunjungan. Pengembalian dana provider tidak dibuktikan oleh perubahan status Payment lokal. Platform tidak mendapatkan isi RME untuk mengelola periode ini.
''')

gap_text=''
for g in gaps:
    dep=', '.join(g['deps']) or 'Tidak ada gap lain yang harus selesai; tetap memakai domain/policy existing.'
    fields=[('1. Nama kebutuhan',g['title']),('2. Role terkait',g['roles']),('3. Masalah pengguna (inferensi)',g['problem']),('4. Workflow terdampak',g['workflow']),('5. Implementasi tersedia (FAKTA)',g['present']),('6. Kemampuan belum tersedia / belum diputuskan (FAKTA)',g['missing']),('7. Dampak bila tidak dibuat (inferensi)',g['impact']),('8. Workaround sekarang',g['workaround']),('9. Opsi solusi (REKOMENDASI)',g['solution']),('10. Kompleksitas relatif',g['size']),('11. Dependensi',dep),('12. Acceptance criteria yang dapat diuji',g['ac'])]
    bug_status='\n\nStatus perbaikan bug: **OPEN — tidak diperbaiki pada audit ini**.' if g['id']=='PG-18' else ''
    gap_text+=f'\n## {g["id"]} — {g["title"]}\n\n**{g["status"]} · {g["priority"]} · {g["kind"]}**{bug_status}\n\nScope: {g["scope"]}. Gate keputusan: **{g["decision"]}**.\n\n'+table(['Dimensi','Analisis'],fields)+f'\nBukti: {"; ".join(g["evidence"])}.\n\nFrekuensi perkiraan: **{g["frequency"]}**. Dasar: {g["basis"]} Nilai pengguna adalah penyelesaian masalah pada butir 3; dampak dan risiko pada butir 7, bukan hasil pengukuran outcome manusia.\n'
write('MISSING_FEATURES_REPORT.md',f'''# Product gap analysis — daftar kanonis

{common}

**{len(gaps)} gap unik**, termasuk satu bug UI baru PG-18; bukan {len(gaps)} bug baru. Setiap ID dipakai ulang pada UX/workflow/backlog agar satu kebutuhan tidak dihitung berkali-kali. Fitur yang tak ada tidak otomatis wajib MVP. Tidak ada P0 yang dapat dibuktikan dari bukti audit ini; P1 recovery/policy berpotensi penting tetapi tidak menyatakan bahaya klinis nyata tanpa bukti.

{definitions}

{table(['ID','Kebutuhan','Status','Prioritas','Jenis'],[[g['id'],g['title'],g['status'],g['priority'],g['kind']] for g in gaps])}

{gap_text}

## Bukan gap produk baru

- DFU-01–12 dan CCL-01–08 tetap **FIXED & VERIFIED** menurut laporan fix terbaru dan source yang dipertahankan. Audit ini tidak mengulang semua PASS, tidak memperbaiki source dan tidak menyebut bug lama OPEN lagi.
- PR-01: revokasi socket aktif **OPEN** historis, risiko keamanan terpisah; bukan “tambahkan custom permission editor”. PR-02/03 adalah portability assertion/factory MySQL historis, bukan fitur klinik yang hilang.
- Kiosk, custom permission, discovery, branches/Edge dan domain obat/inventori perlu keputusan manfaat/scope; tidak diberi severity tinggi hanya karena belum ada.
- Hard delete histori, impersonation platform, native app dan React migration **NOT REQUIRED** pada scope saat ini.
- OAuth, settlement/callback/refund provider, studi manusia dan empty Platform dashboard bukan otomatis MISSING: bukti operasionalnya **NOT VERIFIED**.
''')

write('INCOMPLETE_WORKFLOWS.md',f'''# Workflow yang belum tertutup

{common}

Setiap baris mengacu gap kanonis pada [analisis lengkap](MISSING_FEATURES_REPORT.md). Tidak menambah jumlah temuan. Kemampuan existing tetap dipakai dan tidak dihapus.

{table(['Gap','Mulai / kondisi','Titik putus faktual','Langkah manual sekarang','Handoff yang diperlukan'],[[g['id'],g['workflow'],g['missing'],g['workaround'],g['solution']] for g in gaps])}

## Batas pemulihan yang wajib dijaga

1. Booking pra-visit dapat cancel/rebook saat kebijakan mengizinkan; **visit aktif tidak boleh dibatalkan demi membebaskan antrean**. PG-01 dan PG-03 berbeda karena waktu, kepemilikan dan histori klinis berbeda.
2. Perubahan jam kini sudah aman untuk booking baru. **Booking lama yang tidak sesuai bukan otomatis dibatalkan/diubah**. PG-02 adalah preview dan penanganan eksplisit, bukan pembalikan CCL-05.
3. Final RME hanya berubah lewat koreksi beralasan dan revision; dokumen/follow-up tidak boleh menulis ulang fakta klinis. Patient hanya melihat data yang disetujui, resepsionis cukup instruksi penjadwalan.
4. PG-05 dan PG-08 berbeda: ledger kas menyelesaikan uang pasien, export operasional menyelesaikan pemakaian informasi. Report service value dapat dibuat tanpa billing, report kas tidak.
5. PG-09 viewer read-only adalah prasyarat PG-10 koreksi; **downgrade global Free bukan single-grant undo**, recorded refund bukan bukti dana kembali. Invoice dan periode harus tetap terlacak.
6. PG-14 bukan izin mengubah data lama. Real user incidence tidak diperiksa; diagnosis harus read-only lebih dulu, repair memerlukan keputusan domain dan izin terpisah.

## Workaround yang bukan solusi aman

Tidak menandai visit selesai tanpa pelayanan, menimpa alergi terakhir secara diam-diam, membuat pasien pengganti ketika nonaktif, berbagi akun owner, menghapus Payment/Period/RME, melakukan seed ulang atau memperlakukan screenshot resep sebagai dokumen klinis yang sudah disetujui. Audit tidak menyarankan satu pun tindakan tersebut.
''')

ux_by_role=[
 ['Owner','Dashboard dan menu administrasi/subscription terlihat (UI-01/23).','Kalender exception, laporan, histori/perbaikan komersial perlu langkah lintas halaman; gunakan workflow terpandu setelah policy.','PG-02,08,09,10,11,15,16'],
 ['Clinic Admin','Dashboard dan resource administrasi tersedia (UI-02).','Beda hak owner/admin perlu dijelaskan saat limit; tidak perlu custom editor tanpa kasus delegasi.','PG-02,08,15'],
 ['Receptionist','Dashboard mengikuti kedatangan/antrean (UI-03/08); active filter/cursor sudah CCL-07.','Booking salah perlu cancel-rebook; 8 dari 10 kandidat tanpa penanda lengkap; pemulihan harus dekat sumber masalah.','PG-01,02,06,17'],
 ['Doctor','Start/resume/editor/final dan status save tersedia (UI-04/09/10).','Tidak ada jalan interupsi/handover; resep final belum dokumen; versi allergy perlu interpretasi eksplisit.','PG-03,04,07,13'],
 ['Patient','CTA booking baru/daftar klinik terkait/kode tersedia (UI-05/11).','First-use tanpa kode belum divalidasi manusia; reschedule/reminder/follow-up tetap manual.','PG-01,04,06,07,12'],
 ['Platform Admin','Menu tenant/katalog/payment dan current effective status ada (UI-06/14/15).','Histori tersebar; istilah refund harus jelas pencatatan vs dana dan akses; perlu preview sebelum koreksi.','PG-09,10,11,14,16'],
]
write('UX_PRODUCT_GAPS.md',f'''# UX produk dan task-based review

{common}

## Metode dan hasil aktual

**{len(current)} task review agen OBSERVED**, **0 task aktif NOT VERIFIED**; {len(reviews)} catatan total termasuk satu percobaan harness tersupersede. Ini adalah observasi ketersediaan kontrol dan jalur tugas, **bukan {len(current)} functional PASS**, bukan usability study manusia atau angka task success rate. Sebagian tugas sengaja mencari kemampuan yang ternyata tidak ada. HTTP200 tidak dipakai sebagai bukti kelengkapan bisnis.

Chrome headless, viewport desktop1440×1000; source dan screenshot dibaca untuk task terkait. Tidak mengulang seluruh matriks mobile/keyboard/DOM yang sebelumnya PASS; native keyboard, screen reader, perangkat sentuh, istilah awam dan waktu penyelesaian manusia belum dinilai ulang. Empty activity dan hasil pencarian kandidat adalah probe tambahan, bukan load test.

{table(['Task','Persona','Tujuan review','Bukti aktif'],[[r['id'],r['role'],r['task'],f"[{r['status']}]({ 'evidence/'+r['observed']['screenshot']})"] for r in current])}

Catatan UI-21 awal: helper menerima `Guest` padahal mengharuskan `guest`, lalu mencoba login tanpa akun. Timeout itu **kesalahan harness**, diperbaiki pada runner audit; [UI-21-R2](evidence/screenshots/UI-21-R2.png) dan JSON supersedes disimpan. Tidak dinyatakan bug produk. Catatan semua percobaan triage UI-28: [batas harness](evidence/triage-harness-notes.json).

## Analisis per persona

{table(['Persona','Titik mulai / urutan kerja (FAKTA)','Kejelasan / recovery (INFERENSI + REKOMENDASI)','Gap yang sama'],ux_by_role)}

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

UI-24/25/26/27 menunjukkan empty activity Owner/Rec/Doctor/Patient dengan pesan dan CTA nyata. Fixture memiliki konfigurasi/staf, bahkan Free over-seat banner pada beberapa persona; **bukan** klinik baru tanpa service/jadwal, bukan Admin non-owner empty yang dibuktikan terpisah. Platform zero-tenant tetap **NOT VERIFIED browser**: template {src('resources/views/platform/dashboard.blade.php','Belum ada tenant')} ada, clone memuat tenant, tidak ada aksi UI aman untuk menghilangkan semua tenant. Perlu initial fixture zero-tenant tersendiri bila akan menutup seluruh G47, bukan menghapus DB.

Blade SSR tidak memiliki kontrak “loading dashboard aplikasi” terpisah; loading tombol/refetch dan reconnect berbeda. Skeleton baru bukan kebutuhan wajib tanpa bukti masalah. Kontrak loading lambat/error per task dan studi manusia perlu dipilih sebelum menilai kekurangan desain. Hasil review ini menutup sebagian pertanyaan produk G47, **tidak mengubah seluruh G47 menjadi PASS**.
''')

dependencies=[
 ['Identity/tenant + role/policy','Semua resource query, link, background delivery, file dan export','clinic_id + relasi yang divalidasi; jangan mengandalkan Patient ClinicScope pada guest/console.','Semua gap'],
 ['Clinic timezone/hours + doctor/service + schedule','Booking/session/queue quota dan reschedule','Snapshot historis dan local-date konsisten; setting baru hanya booking baru kecuali keputusan eksplisit.','PG-01,02,06'],
 ['Booking/Queue workflow','Check-in → doctor start → visit','Satu aktif per sesi, UUID replay, after-commit broadcast; cancel tidak melewati visit aktif.','PG-01,03'],
 ['Visit + RME + versions','Final/correction, allergy, photo/dental dan aftercare','Dokter assigned, lock/version, private storage; recipients nonmedis tidak menerima isi klinis.','PG-03,04,07,13'],
 ['Entitlement efektif + period ledger','Seats/kuota, read-only, dental edit dan grant/refund','Current cache bukan histori; expiry tidak memutus penyelesaian visit yang sudah berjalan.','PG-09,10,11,16'],
 ['SubscriptionPayment vs patient service value','Commercial support vs kasir/reports','Jangan reuse Payment SaaS untuk uang pasien; invoice snapshot ≠ receipt kunjungan.','PG-05,08,10'],
 ['Queue events + notification worker','Inbox/status dan usulan near-turn/reminder','Recipient ownership dicek saat delivery, dedup/retry, tidak ada PII publik.','PG-12; PR-01 terpisah'],
 ['Audit + period/source links','Penjelasan commercial dan repair legacy','Append history, alasan/aktor, read-only diagnosis sebelum compensating adjustment.','PG-09,10,14'],
 ['Provider/SMTP/scheduler runtime','Checkout settlement, reminder dan lifecycle verifikasi','Source config bukan bukti provider callback/inbox/scheduler produksi aktif.','NOT VERIFIED eksternal'],
]
write('CROSS_FEATURE_DEPENDENCIES.md',f'''# Dependensi dan integritas lintas fitur

{common}

{table(['Sumber kemampuan','Handoff ke','Invariant / ketergantungan tersembunyi','Gap'],dependencies)}

## Urutan gap yang saling bergantung

```mermaid
flowchart LR
    D01[Keputusan perubahan booking] --> PG01[PG-01 Reschedule/reassign]
    PG01 --> PG02[PG-02 Penanganan appointment terdampak]
    PG01 --> PG04[PG-04 Follow-up tertaut]
    PG09[PG-09 Timeline read-only] --> PG10[PG-10 Koreksi grant/refund]
    PG09 --> PG14[PG-14 Diagnosis/repair legacy komersial]
    PG10 --> PG14
    D03[Keputusan tanggung jawab klinis] --> PG03[PG-03 Interupsi/handover]
    D13[Keputusan lineage alergi] --> PG13[PG-13 Resolusi versi]
    D05[Scope kasir] --> PG05[PG-05 Ledger pasien]
    PG05 --> CashReport[Laporan kas; bagian lanjutan PG-08]
    PG08[PG-08 Laporan operasional] --> CashReport
```

Dependensi PG-02 pada PG-01 berlaku untuk **penyelesaian booking lama**; kalender exception + preview read-only dapat dikembangkan lebih dulu tanpa reschedule. PG-14 diagnosis read-only dapat dimulai sebelum koreksi PG-10, tetapi repair komersial menunggu kontraknya. PG-08 laporan service value tidak menunggu billing, sedangkan laporan kas wajib bergantung ledger PG-05.

## Data yang tersedia tetapi pemakaian berikutnya belum lengkap

SubscriptionPeriod/grant reason tersimpan tetapi full timeline Platform belum; Rx final tersimpan tetapi dokumen terbit belum; plan SOAP tersimpan tetapi tugas kontrol belum; appointment terdampak perubahan jam tetap sah historis tetapi tidak otomatis masuk daftar aksi; Visit.total_amount belum punya ledger kas; sibling allergy versions belum punya kontrak resolusi current. Referensi faktual dan AC masing-masing: [gap kanonis](MISSING_FEATURES_REPORT.md).

## Ketergantungan runtime dan akses yang tidak boleh disembunyikan

Worker/reverb/scheduler, transport mail, provider sandbox/callback, private disk dan schema constraints diperlukan menurut jalur fitur. Runtime audit sengaja memakai mail array/broadcast null dan dummy-only DB; indikator reconnect pada screenshot bukan temuan produk baru. Riwayat delivery Reverb, loopback SMTP dan checkout sandbox sebelumnya tidak membuktikan operasi deployment sekarang.

PR-01 berhubungan dengan boundary authorization channel lama, bukan ketiadaan UI permission. Jangan menunggu custom-role feature untuk mengerjakan risiko revokasi; lakukan track keamanan terpisah dengan bukti old-socket/reconnect/HTTP dan tanpa mengubah cakupan audit ini. PG-18 hanya binding feedback grant; backend idempotency tetap invariant lintas fitur.
''')

decision_options=[
 ['D-01','PG-01','Cancel-rebook terpandu + supersedes vs edit booking transactional','Pertama mempertahankan fakta lama tetapi kode/queue baru; kedua menjaga identitas booking namun memerlukan revision/kuota/harga rules lebih kompleks.','Owner/operasional + product','Pilih status yang boleh, otoritas, notifikasi, biaya dan nomor baru. Tidak mengubah IN_PROGRESS.'],
 ['D-02','PG-02','Simpan setting + daftar dampak vs tolak perubahan bila ada booking; exception tanggal vs weekly edit','Pertahankan CCL-05 sebagai dasar. Mass cancel/shift memerlukan izin per appointment; menolak semua perubahan membatasi recovery.','Owner/operasional','Tidak ada perpindahan appointment historis diam-diam; define timezone dan effective date.'],
 ['D-03','PG-03','Interrupted terminal vs pause/resume vs handover dokter','Mempengaruhi tanggung jawab klinis, akses, locking sesi, laporan dan arti completion. Tidak bisa diselesaikan dengan cancel queue biasa.','Penanggung jawab klinis + product','Definisikan aktor, alasan, draft/foto, pasien dan pelepasan sesi.'],
 ['D-04','PG-04','Instruksi kontrol + tautan booking vs task follow-up tertutup','Tautan lebih ringan; task butuh owner/status/escalation dan informasi nonmedis bagi resepsionis.','Doctor + operasional + Patient','Tentukan informasi yang disetujui dibagi dan siapa memantau.'],
 ['D-05','PG-05','Tetap administrasi klinis + kasir eksternal vs billing pasien minimal dalam produk','Pilihan pertama sah untuk MVP terbatas dengan batas transparan; pilihan kedua memenuhi RPP lebih luas dan butuh ledger/receipt/refund/pemisahan role.','Pemilik produk + Owner/finance','Tidak menyebut service value sebagai cash. Tentukan discount/partial/refund sebelum schema.'],
 ['D-06','PG-06','Nonaktif = blok booking semua kanal vs arsip dengan self-service yang diperbolehkan','Blok memerlukan pemulihan berotorisasi; arsip perlu copy dan visibility berbeda. Jangan otomatis reactivate/link identitas.','Owner/operasional + product','Kontrak UI-22 lintas kanal harus eksplisit sebelum menyebut bug atau mengubah perilaku.'],
 ['D-07','PG-07','Print resep saja vs ringkasan klinis terpilih / dokumen lain','Cakupan/format/penerima dan tanda otorisasi berbeda; full SOAP tidak otomatis dokumen Patient.','Doctor/penanggung jawab klinis + product','Batas isi, final-only, versi, penerbit dan penerima; aturan formal memerlukan validasi terpisah.'],
 ['D-08','PG-08','Satu CSV operasional vs laporan PDF/reconciled finance','CSV ringkas dapat berdiri sendiri; finance menunggu ledger jika mengukur kas.','Owner/admin','Definisi metrik/status/timezone dan field export tanpa medis.'],
 ['D-09','PG-09','Viewer Platform seluruh periode + owner subset vs hanya support read-only','Akses lebih mandiri vs beban dukungan; histori invoice/add-on existing tetap dipakai.','Product + Platform/Owner','Field source/period/payment/revoked dan tenant privacy.'],
 ['D-10','PG-10','Revoke grant tertentu vs adjustment schedule/credit/refund terpisah','Efek pada sisa periode, dana, add-on dan invoice berbeda. Global Free bukan pengganti keputusan.','Commercial owner + Platform + product','Kontrak dana vs recorded, paket utama vs add-on, future valid periods dan approval/audit.'],
 ['D-11','PG-11','Dukungan manual terkendali vs UI transfer/suspend/closure yang dibutuhkan','Manual menekan scope tetapi perlu prosedur; UI butuh pembuktian kepemilikan dan dampak kontrak/retensi.','Owner + Platform + product','Tidak hard delete atau impersonation; hak baca dan IN_PROGRESS tetap dipertimbangkan.'],
 ['D-12','PG-12','Near-turn in-app vs reminder terjadwal + kanal eksternal','Lead time dan threshold berbeda; kanal luar memerlukan consent, biaya, delivery dan dedup.','Operasional + Patient/product','Tentukan near-turn ≠ called; stale appointment update/retry.'],
 ['D-13','PG-13','Multi-assertion sah vs satu current per lineage dengan conflict resolution','Pertama perlu UI interpretasi; kedua perlu expected-parent/merge dan supersede tanpa menghapus versi.','Penanggung jawab klinis + Doctor','Tidak last-write-wins atau rewrite RME final; tentukan critical banner hasil konflik.'],
 ['D-14','PG-14','Read-only diagnosis dulu; manual approval per record vs repair terbatas per kategori','Tidak ada bukti jumlah data pengguna bermasalah. Repair menyeluruh tanpa keputusan berisiko mengubah histori.','Pemilik data + klinis/commercial sesuai jenis','Izin terpisah, dry-run, snapshot/audit, idempotency; tidak seed ulang.'],
 ['D-15','PG-15','Invitation set-password; delegated Clinic Admin perlu atau owner cukup','Invitation meminimalkan distribusi password manual; admin tambahan menambah privilege yang harus dapat dicabut.','Owner + product','Jangan membuat generic permission editor hanya untuk provisioning admin.'],
 ['D-16','PG-16','Ubah copy/feature metadata vs enforce paywall yang dinyatakan','Copy paling kecil jika hak sekarang memang dimaksudkan; enforcement mengubah kontrak Free dan perlu transisi.','Pemilik produk/commercial','Tidak menutup pelayanan IN_PROGRESS atau menghapus data paid lama.'],
 ['D-17','Custom permission historis G68','Role tetap + delegasi yang spesifik vs custom role editor','Lima role sudah bekerja; granular permission belum kebutuhan operasional tervalidasi. PR-01 tidak bergantung keputusan ini.','Owner/product + security','Default pertahankan role tetap. Add role/action matrix hanya jika kasus nyata dibuktikan.'],
 ['D-18','Kiosk/display historis G29','Display publik saat ini vs kiosk mode/filter/perangkat','Kebutuhan device, visibilitas dokter dan signage berbeda; public queue sudah nonmedis.','Operasional klinik/product','Default display ada cukup; uji perangkat/kontrol hanya jika kebutuhannya disepakati.'],
 ['D-19','Scope opsional / post-MVP','Directory/branches/Edge/drug catalog/inventory/claims vs fokus satu klinik web','Setiap domain menambah model dan tanggung jawab, bukan otomatis syarat lengkap MVP. RPP luas bukan izin implementasi.','Pemilik produk + klinik target','Butuh kasus penggunaan dan nilai tervalidasi sebelum backlog implementasi. Native/React tidak masuk scope saat ini.'],
 ['D-20','First-use / G47','Definisikan tugas onboarding/loading per role dan studi manusia','Agent review tidak mengukur pemahaman pengguna. Platform empty perlu initial fixture; skeleton bukan otomatis solusi.','Product/UX + partisipan nyata','Uji klinik tanpa setup, admin non-owner empty, zero-tenant platform dan task tanpa kode jika relevan; jangan hapus user DB.'],
]
write('PRODUCT_DECISIONS_REQUIRED.md',f'''# Keputusan produk yang diperlukan

{common}

Tidak ada keputusan baru yang ditetapkan diam-diam. Tabel berikut mendokumentasikan alternatif dan konsekuensi; pihak pada kolom pemilik adalah **usulan penanggung jawab keputusan**, bukan bukti persetujuan manusia. Prioritas pada backlog berlaku setelah scope/aturan terkait dipilih.

{table(['Keputusan','Gap / kebutuhan','Alternatif','Konsekuensi','Pemilik keputusan (usulan)','Batas / keluaran keputusan'],decision_options)}

## Penilaian ulang keputusan historis

{table(['Historis','Hasil audit saat ini','Disposisi'],[
 ['G59 commercial viewer','PARTIAL: invoice/current status + owner addon history ada; full Platform main-period timeline belum.','PG-09 / D-09'],
 ['G63 duplicate grant lama / single revoke','PRODUCT DECISION REQUIRED: pencegahan baru DFU-12 sudah selesai, recovery berbeda.','PG-10 / D-10; tidak menghitung duplikat pengguna'],
 ['G29 kiosk/display','PRODUCT DECISION REQUIRED, bukan display hilang.','D-18; P3 opsional'],
 ['G68 custom permission','PRODUCT DECISION REQUIRED, role/policy yang ada tetap.','D-17; PR-01 track terpisah'],
 ['G47 empty/loading','PARTIAL bukti tenant; global Platform empty dan clean-onboarding belum browser.','D-20; jangan tandai PASS penuh'],
 ['Jam operasional CCL-05','Booking baru sudah mengikuti jam; historical unchanged. Exception/date-impact belum.','PG-02 / D-02, bukan bug CCL dibuka ulang'],
 ['Versi alergi dua editor','Kedua versi tersimpan; resolusi lineage/current belum diputuskan.','PG-13 / D-13, tidak mengklaim lost update'],
 ['Data legacy inkonsisten','Pencegahan tersedia; data pengguna tidak diperiksa; diagnosis/repair belum terpandu.','PG-14 / D-14'],
 ['PR-01 socket revocation','OPEN risiko keamanan existing; tidak diuji ulang dan tidak diperbaiki.','Terpisah dari product gaps'],
 ])}

## Scope yang tidak diperluas

Tidak menambah React/SPA/native app, custom permission, payment pasien, provider baru, auto-renewal, AI, multibranch atau Edge hanya karena menarik. Billing pasien, dokumen dan lifecycle bisa penting pada scope klinik target, tetapi pilihan format, alur dan kewajibannya belum disepakati. Validasi yurisdiksi/kebijakan dokumen/retensi dilakukan terpisah jika akan diputuskan, tanpa klaim kepatuhan dalam audit ini.
''')

# Dependency-aware development order. These are proposals, not authorizations.
order=['PG-18','PG-17','PG-06','PG-09','PG-01','PG-02','PG-03','PG-13','PG-10','PG-14','PG-07','PG-04','PG-12','PG-08','PG-15','PG-16','PG-11','PG-05']
assert set(order)==ids
done=set()
for id in order:
    g=next(g for g in gaps if g['id']==id)
    assert set(g['deps'])<=done, (id,g['deps'])
    done.add(id)
write('PRIORITIZED_BACKLOG.md',f'''# Backlog pengembangan yang diprioritaskan

{common}

**P0: 0 dibuktikan; P1: {prior['P1']} item; P2: {prior['P2']} item; P3: 0 gap kanonis wajib.** Kiosk/custom permission/branches/Edge adalah kandidat opsional terpisah, bukan item wajib tambahan. Tidak ada estimasi effort hari tanpa validasi tim. S/M/L/XL relatif terhadap pola domain sekarang, bukan komitmen jadwal.

P1 = menutup tugas inti/recovery atau kebutuhan bersyarat scope. PG-05/07 khususnya **bukan otomatis MVP wajib** hanya karena berlabel P1: jadi prioritas saat klinik target memerlukan penyelesaian kas/dokumen. PG-14 berlaku jika data terkait ditemukan. Tidak ada bukti audit untuk mengklaim pelayanan saat ini selalu terhenti atau membahayakan pasien.

Urutan di bawah mengikuti dependensi dan pekerjaan kecil yang aman; urutan berbeda dari severity. Viewer P2 dapat lebih dulu daripada mutasi P1 agar koreksi dapat ditinjau. Setiap baris memiliki AC penuh dan bukti pada [daftar kanonis](MISSING_FEATURES_REPORT.md).

{table(['Urut','Gap / prioritas','Scope / nilai pengguna','Dampak operasional dan risiko bila absen','Frekuensi perkiraan + dasar','Kompleksitas','Dependensi / gate'],[[i,g['id']+' '+g['priority']+' — '+g['title'],g['scope']+'; '+g['problem'],g['impact'],g['frequency']+'. '+g['basis'],g['size'],(', '.join(g['deps']) or 'Domain existing')+'; '+g['decision']] for i,id in enumerate(order,1) for g in gaps if g['id']==id])}

## Pengelompokan scope

| Kelompok | Item | Makna |
| --- | --- | --- |
| Menutup administrasi inti / recovery | PG-01,02,03,06,13,17,18 | Prioritaskan kebijakan dan jalur pengguna; PG-18 bug feedback UI, core/idempotency existing tetap dipertahankan. |
| Pengelolaan SaaS yang sudah tersedia | PG-09,10,14,16 | Viewer lalu recovery; packaging dan legacy memerlukan keputusan; tidak menghapus komersial. |
| Lanjutan efisiensi / aftercare | PG-04,08,12,15 | Nilai diperkirakan, pilih setelah observasi klinik target; invitation/reset bukan fitur auth baru. |
| Bersyarat cakupan klinik lebih luas | PG-05,07,11 | Kasir/dokumen/lifecycle diputuskan menurut klaim produk dan proses klinik; jangan diam-diam memperluas MVP. |
| P3 opsional belum masuk implementasi | D-17,18,19 | Custom permissions/kiosk/directory/cabang/Edge/domain tambahan hanya jika manfaat terbukti. |
| Validasi, bukan missing feature | D-20 dan provider E2E | Studi manusia, fixture empty dan sandbox integration diperlukan untuk bukti; tidak dihitung feature gap. |
| Risiko existing terpisah | PR-01; PR-02/03 verifikasi | Jangan diklaim selesai oleh roadmap produk atau hasil SQLite historis. |

## Aturan siap dikembangkan

Pemilik keputusan menyetujui scope/alternatif; AC gap diterjemahkan menjadi backend + browser scenario dengan fixture isolasi; tenant/policy/versions/history invariants menjadi negative cases; migration hanya additive bila diperlukan; replay/rollback administratif tidak menghapus data; laporan sebelum/sesudah dibuat terpisah dari audit historis. Audit ini tidak memberi izin implementasi, commit atau deploy.
''')

roadmap=[
 ['0 — Kontrak produk dan validasi','D-01/02/03/05/06/07/10/13/16; studi D-20','Tidak ada source/DB change. Pilih scope klinik sampai kasir/dokumen, makna nonaktif, interupsi dan refund; PR-01 dicatat track keamanan tersendiri.','Keputusan alternatif/alasan/aktor disetujui; task manusia dirancang tanpa menyebut agent sebagai partisipan.','Bukan delivery feature; basis agar pekerjaan berikutnya tidak mengubah aturan klinis/komersial.'],
 ['1 — Quick wins dan informasi read-only','PG-18, PG-17, PG-06 setelah D-06, PG-09; PG-16 copy bila dipilih','Binding pending grant, copy + pencarian lanjutan, aturan inactive terpusat, main-period viewer. PG-18/17/09 dapat tanpa schema baru; PG-06 mengikuti keputusan tanpa auto-link/reactivate.','AC PG-18/17/06/09; tombol pending disabled tanpa Alpine error, UUID tetap dedup; 10 kandidat, negative tenant, inactive tiap kanal, future/revoked timeline.','Risiko token duplicate review, legacy telepon, visibility owner/platform; jangan mematikan Free tanpa keputusan.'],
 ['2 — Perubahan operasional sebelum visit','PG-01 → PG-02','Relasi booking pengganti/revision dan exception tanggal mungkin migration additive; impact preview dapat lebih awal. Minimal UI menunjukkan old/new dan alasan.','AC PG-01/02: quota/timezone/current hours, replay, snapshot harga, existing untouched, aktif/final ditolak.','Risiko nomor antrean, notifikasi basi dan rekonsiliasi status; pertahankan DFU-01/02 dan CCL-05.'],
 ['3 — Recovery klinis','PG-03, PG-13 setelah keputusan klinis','Status interupsi/assignment history dan allergy lineage resolution mungkin schema additive; tidak cukup mengubah enum tanpa workflow/policy.','AC PG-03/13: dua editor, draft/foto utuh, aktor/alasan, assigned-doctor access, slot release, optimistic conflicts.','Kompatibilitas laporan, old enum readers, critical warning, notification privacy dan finish IN_PROGRESS saat expired.'],
 ['4 — Exception komersial dan legacy','PG-10 setelah PG-09; PG-14 diagnosis dulu','Compensating adjustment per period, preview entitlement/dana; repair per kategori hanya jika data ditemukan/diizinkan. Tambahan adjustment/audit schema ditentukan kontrak, bukan delete.','AC PG-10/14: duplicate lama fixture, valid future period tetap, main vs addon, refund idempotent, dry-run zero mutation.','Risiko jadwal periode/kuota/add-on dan payment reconciliation; dana eksternal membutuhkan sandbox tervalidasi, tidak cukup local recording.'],
 ['5 — Hasil pelayanan dapat dipakai','PG-07, PG-04, PG-12','Printable dokumen minimum + follow-up tertaut; reminder in-app lebih dulu jika dipilih. Follow-up/delivery dedup mungkin model baru, print dapat tanpa ledger baru.','AC PG-07/04/12: final-only, approved payload, correct recipient, no stale reminder, aftercare tertutup.','Risiko membocorkan SOAP ke admin/Patient, dokumen versi lama, worker retry dan perubahan booking.'],
 ['6 — Manajemen dan lifecycle terpilih','PG-08, PG-15, PG-16 enforcement jika dipilih, PG-11','Satu report operasional, invitation/delegasi spesifik, packaging sesuai kontrak, tenant transfer/suspend yang diperlukan. Hindari UI generik per model.','AC masing-masing PG, tambahan empty role/state dan onboarding sebenarnya.','Report cash belum boleh ada tanpa PG-05; role/invitation/suspend tidak membuka medische bypass atau menghilangkan histori.'],
 ['7 — Kasir bila cakupan disetujui','PG-05 → laporan cash PG-08','Domain tagihan/pembayaran/receipt pasien terpisah SaaS; schema additive dan rekonsiliasi eksplisit. Jangan menambah gateway/claim/inventory otomatis.','AC PG-05: posted ledger, outstanding, cash reconcile, void/refund audit, tenant/role dan replay.','XL; konsekuensi komersial/klinis, jangan migrasikan Visit.total_amount historis menjadi penerimaan kas otomatis.'],
 ['Opsional — Setelah nilai tervalidasi','D-17/18/19','Kiosk, permission granular, discovery/cabang/Edge dan domain obat/inventori bukan exit gate default.','Requirement dan acceptance tersendiri sebelum estimasi/implementasi.','Setiap domain dapat melampaui scope; native/React tetap tidak diperlukan.'],
]
write('DEVELOPMENT_ROADMAP.md',f'''# Roadmap pengembangan berbasis dependensi

{common}

Roadmap ini usulan urutan nilai/dependensi, bukan jadwal, persetujuan implementasi atau production-readiness plan. Prioritas dan gate lengkap: [backlog](PRIORITIZED_BACKLOG.md), [keputusan](PRODUCT_DECISIONS_REQUIRED.md). Seluruh **{len(gaps)} ID gap** tercakup; AC penuh tiap item ada di [gap kanonis](MISSING_FEATURES_REPORT.md).

{table(['Tahap','Item','Perubahan minimum / database','Exit criteria / regression yang harus dibuat','Risiko kompatibilitas'],roadmap)}

## Penyederhanaan UI yang disarankan

Tempatkan aksi pemulihan pada konteks pekerjaan: detail booking untuk perubahan, kalender untuk impact, editor untuk interupsi/versi, tenant timeline untuk correction, final visit untuk dokumen/follow-up. Gunakan komponen Blade/Tailwind/Alpine yang ada. Jangan menambah menu baru bila satu CTA/form terarah cukup. Tidak menambahkan framework frontend atau generic admin builder.

## Strategi verifikasi saat implementasi berikutnya

Backend: validasi domain/authorization/tenant, atomic transitions, versions, snapshot, idempotency dan batas waktu. Browser: satu task sukses ditambah failure/recovery kritis per gap, role negatif, state empty/loading/error yang benar-benar memiliki kontrak, viewport375/768/1024/1440 untuk UI yang berubah, fokus/label/keyboard. Database testing terisolasi; MySQL-specific lock/schema hanya lewat fixture MySQL tersendiri bila change memerlukannya. Tidak menganggap SQLite cukup untuk concurrency.

Jalankan suite PHP/JS/Playwright relevan dan baseline DFU/CCL yang benar-benar terkena dependensi setelah perubahan; jangan menyalin hasil historis sebagai hasil run baru. Bukti provider harus membedakan fake/backend/sandbox checkout/settlement; manusia harus benar-benar partisipan jika mengklaim usability validated. PR-01 mempunyai retest keamanan sendiri dan tidak boleh disamarkan sebagai fitur produk baru. Tidak ada load/stress atau deployment otomatis dalam roadmap ini.
''')

write('FINAL_PRODUCT_AUDIT.md',f'''# Hasil akhir audit kelengkapan produk ClinicOS

{common}

## Jawaban produk

**ClinicOS sudah mendukung alur utama administrasi klinis satu klinik per owner: setup → booking → antrean → pemeriksaan → RME/resep final → monitoring. Belum lengkap untuk seluruh operasional klinik sehari-hari secara tertutup**, terutama saat jadwal berubah/dokter berhalangan, pelayanan terinterupsi, hasil perlu dibawa menjadi dokumen/tindak lanjut, dan exception komersial membutuhkan recovery mandiri. Jika cakupan mencakup kasir sesuai RPP, billing/receipt pasien masih belum ada dan tetap membutuhkan sistem luar.

Ini bukan kesimpulan bahwa semua kebutuhan RPP wajib dibuat untuk MVP. **MVP terbatas administrasi klinis + SaaS** dapat tetap menjadi target yang sah dengan batas transparan; klaim “operasional lengkap sampai kasir/aftercare/lifecycle” belum didukung kemampuan sekarang. Keputusan scope dan kebutuhan manusia belum divalidasi; audit memberi opsi dan AC agar keputusan bisa konkret.

## Fakta, bukti dan jumlah aktual

- **{len(cap)} kemampuan** diklasifikasikan, **{len(gaps)} gap unik**: **P0 0, P1 {prior['P1']}, P2 {prior['P2']}**. P1 mencakup item bersyarat scope/decision; tidak menyatakan sembilan blocker pasti di semua klinik.
- **{len(baseline)} baseline file**, **{len(routes)} route**, **{len(schema)} tabel**, **5 role tersimpan / 6 persona** (Owner dipisahkan konseptual); source, schema fixture, UI dan {len(qa)} laporan QA direkonsiliasi.
- **{len(current)} task review browser aktif OBSERVED** dari {len(reviews)} catatan (1 harness tersupersede), pada seluruh persona + guest. **Bukan functional PASS atau human usability study.** [Bukti task/screenshot](evidence/task-review.json).
- UI-18 membuktikan 8 dari 10 kandidat ditampilkan; UI-22 membuktikan inactive patient dapat booking self-service dengan profil sama, sedangkan staf dialihkan ke profile. Keduanya tidak menghapus fix normalisasi/inactive fallback CCL.
- **PG-18, satu bug UI baru P2, OPEN**: directive @js mentah pada binding disable tombol grant memicu Alpine SyntaxError. Saat submit UI, submitting=true tetapi tombol tetap enabled; POST dicegat sebelum backend. Tidak membuktikan grant ganda atau menggugurkan idempotency DFU-12. [Bukti](evidence/page-error-triage.json).
- Riwayat add-on/invoice owner **sudah ada**; gap timeline Platform lebih sempit. Autosave, alergi, odontogram persisten, structured diagnosis/treatment, ETA dan entry Patient **sudah ada**, bertentangan dengan catatan arsitektur Oktober yang kini usang.

{table(['Status kemampuan','Jumlah'],stats.items())}

Hitungan ini tidak menjadi persentase completeness: unit berbeda ukuran, beberapa status adalah scope/verification, dan kebutuhan klinik target belum menjadi denominator yang disepakati.

## Fokus yang disarankan

1. Putuskan makna inactive, perubahan booking/jadwal, interupsi visit, versi allergy dan koreksi grant/refund. Selesaikan quick win kandidat + viewer histori tanpa memperluas izin medis.
2. Lengkapi reschedule/reassign pra-visit dan kalender exception dengan preview dampak; pertahankan appointment historis serta DFU-01/02 dan CCL-05.
3. Rancang recovery klinis/komersial yang teraudit; viewer lebih dulu daripada mutasi, diagnosis legacy read-only sebelum repair berizin.
4. Pilih dokumen/follow-up/reminder/report minimum untuk klinik target; tambahkan kasir hanya jika scope sampai penerimaan pembayaran disetujui. Jangan menyebut nilai layanan sebagai kas.

## Status historis dan risiko terpisah

**DFU-01–12 dan CCL-01–08 tetap FIXED & VERIFIED**, tidak dibuka kembali dari laporan FAIL lama. Run terakhir yang dipertahankan, **bukan run audit ini**: [CCL fix regression](../deep-functional-ux/CCL_FIX_REGRESSION_REPORT.md) mencatat **PHP402 PASS /0 FAIL /0 ERROR, 2718 assertions; JavaScript15 PASS; Playwright28 active PASS; Chrome2 workflow PASS**. Audit produk tidak mengubah source atau menjalankan ulang seluruh suite. Bukti dental192 cells dan recovery Reverb historis juga tidak dihitung sebagai run baru.

**PR-01 tetap OPEN sebagai risiko keamanan existing terpisah**: private socket lama tidak dicabut setelah role/account revoked, payload yang diamati nonmedis; tidak ada klaim kebocoran medis lintas tenant. [Temuan PR](../production-readiness/NEW_FINDINGS.md). PR-02/03 assertion/factory MySQL tetap utang verifikasi historis; PHP SQLite hijau bukan bukti semua concern MySQL hilang. Tidak ada audit keamanan/load/readiness baru.

Integrasi historis 11 Nov membuktikan checkout/reconcile pending DOKU sandbox HTTPS dan mail ke sink SMTP loopback. **Settlement/callback provider eksternal/refund dana dan inbox publik belum terbukti**; Google OAuth E2E belum. Tidak memakai kredensial/pembayaran pengguna pada audit ini. [Batas integrasi](../production-readiness/INTEGRATION_TEST_REPORT.md).

## Batas audit dan hal yang belum diverifikasi

Tidak memeriksa data pengguna atau mengukur jumlah legacy mismatch, tidak memvalidasi manusia/clinical safety outcome, yurisdiksi/legalitas dokumen, deployment, concurrency baru, load/stress atau provider settlement. Browser review desktop bertujuan task discoverability, bukan ulang seluruh QA mobile/aksesibilitas. Empty activity tenant teramati tetapi clean-new-clinic, Admin non-owner empty, global zero-tenant Platform dan kontrak loading spesifik belum dibuktikan penuh; G47 tidak ditutup sebagai PASS menyeluruh. Keputusan kiosk/custom permission/branches/Edge tetap opsional bersyarat, bukan blocker MVP otomatis.

## Dokumen untuk tindakan berikutnya

{table(['Dokumen','Kegunaan'],[
 ['[PRODUCT_CAPABILITY_INVENTORY.md](PRODUCT_CAPABILITY_INVENTORY.md)','Kemampuan aktual, status dan bukti per capability.'],
 ['[ROLE_NEEDS_ANALYSIS.md](ROLE_NEEDS_ANALYSIS.md)','10 dimensi kebutuhan setiap role, Owner vs Admin, manual steps dan akses.'],
 ['[CLINIC_WORKFLOW_MAP.md](CLINIC_WORKFLOW_MAP.md)','Lifecycle operasional dan SaaS, termasuk cabang putus.'],
 ['[MISSING_FEATURES_REPORT.md](MISSING_FEATURES_REPORT.md)',f'{len(gaps)} gap kanonis dengan 12 dimensi, bukti dan acceptance criteria.'],
 ['[INCOMPLETE_WORKFLOWS.md](INCOMPLETE_WORKFLOWS.md)','Titik putus dan workaround, tanpa menambah jumlah gap.'],
 ['[UX_PRODUCT_GAPS.md](UX_PRODUCT_GAPS.md)','Task review browser, per-role UX dan batas studi/empty/loading.'],
 ['[CROSS_FEATURE_DEPENDENCIES.md](CROSS_FEATURE_DEPENDENCIES.md)','Dependensi, handoff, data yang belum dipakai dan invariant.'],
 ['[PRODUCT_DECISIONS_REQUIRED.md](PRODUCT_DECISIONS_REQUIRED.md)','20 keputusan/validasi, alternatif dan konsekuensi termasuk historis.'],
 ['[PRIORITIZED_BACKLOG.md](PRIORITIZED_BACKLOG.md)','Prioritas, value/risk/frequency/complexity/dependencies dan urutan.'],
 ['[DEVELOPMENT_ROADMAP.md](DEVELOPMENT_ROADMAP.md)','Tahap implementasi bersyarat, risiko schema/compatibility dan regression.'],
 ])}

## Integritas pekerjaan

**Pemeriksaan integritas aktual PASS:** 439 file baseline termasuk source/test/manifest dan `.env` identik; 40 laporan QA historis utuh; 11 dokumen dan seluruh tautan lokal diperiksa. [Hasil pemeriksaan hash dan dokumen](evidence/audit-validation.json). Runtime lokal hanya klon disposable dengan APP_KEY/private storage/cache/build sendiri, mail array, broadcast null dan provider kosong; perubahan UI-22 hanya pada pasien/booking dummy dan status aktif dipulihkan. Seluruh tabel klinis serta Payment/SubscriptionPeriod/AddonPeriod identik dengan fixture asal; hanya satu appointment dummy baru dan queue terkait untuk UI-22. Tidak commit, push atau deploy.
''')

dump('report-stats.json',{'capabilities':len(cap),'capability_statuses':dict(stats),'gaps':len(gaps),'priorities':dict(prior),'browser_current_tasks':len(current),'browser_records':len(reviews),'qa_reports':len(qa),'product_documents':len(docs),'baseline_files':len(baseline),'routes':len(routes),'tables':len(schema),'stored_roles':5,'personas':6,'decisions':len(decision_options)})
print(json.dumps({'documents':11,'capabilities':len(cap),'gaps':len(gaps),'priorities':dict(prior),'browser_tasks':len(current)},ensure_ascii=False))
