import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,readdirSync,existsSync} from 'node:fs';
import {join,resolve,relative} from 'node:path';
import {execFileSync} from 'node:child_process';
import {features,extraEvidence} from './catalog.mjs';
import {findings,missing} from './findings.mjs';

const repo=resolve(import.meta.dirname,'../../..'),out=join(repo,'qa-audit/deep-functional-ux'),ev=join(out,'evidence');
const attempts=JSON.parse(readFileSync(join(ev,'scenarios.json')));
for(const [oldId,newId,note] of [
    ['RME-DENTAL','DENT-01','Entitlement fixture and browser persistence were subsequently executed.'],
    ['BOOK-06-R1','CROSS-05','Missing initial fixture was replaced by a real UI booking in a started session.'],
]){const row=attempts.find(x=>x.id===oldId);if(row){row.superseded_by=newId;row.harness_note=note;}}
for(const row of attempts.filter(x=>x.id==='DENT-02'))row.scenario='Pencabutan add-on ketika editor terbuka: simpan ditolak, reload mempertahankan chart read-only';
writeFileSync(join(ev,'scenarios.json'),JSON.stringify(attempts,null,2));
const latest=attempts.filter(x=>!x.superseded_by);
const pages=JSON.parse(readFileSync(join(ev,'pages.json'))),routes=JSON.parse(readFileSync(join(ev,'routes.json'))),permissions=JSON.parse(readFileSync(join(ev,'roles-permissions.json')));
const checkpoint=JSON.parse(readFileSync(join(ev,'checkpoint.json'))),runtime=JSON.parse(readFileSync(join(ev,'runtime.json')));
const regression=JSON.parse(readFileSync(join(ev,'regression.txt'),'utf8').trim());
assert.equal(regression.result,'passed');
const counts=Object.fromEntries(['PASS','FAIL','BLOCKED','NOT TESTED'].map(status=>[status,latest.filter(r=>r.status===status).length]));
const roles=['Guest','Clinic Owner','Clinic Admin','Receptionist','Doctor','Patient','Platform Admin'];
const actualRoles=[...new Set(permissions.map(x=>x.name))];assert.equal(actualRoles.length,5);
const cell=value=>String(value??'').replaceAll('|','\\|').replaceAll('\n',' ').replace(/\s+/g,' ').trim();
const mdTable=(heads,rows)=>'| '+heads.join(' | ')+' |\n| '+heads.map(()=> '---').join(' | ')+' |\n'+rows.map(row=>'| '+row.map(cell).join(' | ')+' |').join('\n')+'\n';
const evidenceLink=file=>`[${file}](evidence/${file})`;
const scenarioLink=id=>`\`${id}\``;
function current(id){let r=attempts.find(x=>x.id===id);const seen=new Set();while(r?.superseded_by&&!seen.has(r.id)){seen.add(r.id);r=attempts.find(x=>x.id===r.superseded_by);}return r;}
function records(feature){const direct=latest.filter(x=>x.feature===feature);for(const id of extraEvidence[feature]||[]){const r=current(id);if(r&&!direct.some(x=>x.id===r.id))direct.push(r);}return direct;}
function featureFindings(id){return findings.filter(f=>f.feature.split('/').includes(id));}
function featureState(id){const rs=records(id);return rs.some(r=>r.status==='FAIL')?'Ada skenario FAIL':rs.some(r=>r.status==='PASS')?'Aksi terpilih terbukti; belum menyeluruh':rs.some(r=>r.status==='BLOCKED')?'BLOCKED':'NOT TESTED';}
const header=title=>`# ${title}\n\nRun: **13 November 2026, Asia/Bangkok (UTC+7)**. Checkout lokal termasuk perubahan pengguna yang sudah ada. Source aplikasi tidak diperbaiki. Browser: **Playwright + Google Chrome ${checkpoint.browser}, headless nyata**; database/storage/sesi/build disposable.\n\n`;
const caveat='PASS hanya berlaku untuk skenario dan assertion yang dijalankan. Halaman terbuka, snapshot DOM, test backend, fixture status, dan pengujian browser end-to-end adalah bukti berbeda. Tidak ada penilaian production-readiness, beban, stress, benchmark server, deployment, commit, push, atau pembayaran nyata.\n\n';
const write=(file,text)=>writeFileSync(join(out,file),text,'utf8');

const gapsByFeature={
 F00:['Navigasi Back/Forward landing pada seluruh anchor','Katalog harga kosong/nonaktif seluruhnya dan pemulihan'],
 F01:['Remember-only session dan revokasi password pada browser baru','Reset/verification link kedaluwarsa dan pemakaian ulang lewat UI'],
 F02:['Email registrasi duplikat/case variant melalui UI pasien dan owner','Terms tidak disetujui serta refresh di setiap tahap registrasi'],
 F03:['Email duplikat dan batas panjang nama pada seluruh role','Dua sesi profil aktif saat password/email berubah'],
 F04:['Lanjut onboarding yang ditinggalkan setelah logout/login','Preferred plan dari setiap CTA landing sampai aktivasi'],
 F05:['Timezone WITA/WIT dan perubahan jam operasional terhadap sesi existing','Logo tipe salah, ukuran lebih dari2MB, dan penggantian gagal'],
 F06:['Admin mengganti email/password staf dengan sesi remember aktif','Batas doctor seat dari UI dan semua variasi entitlement paket'],
 F07:['Tarif pecahan/nonkelipatan1000 dan batas nominal server','Layanan dinonaktifkan ketika tab booking lama masih terbuka'],
 F08:['Perubahan jam sesi terpakai serta quota diturunkan di bawah reservasi','Batas quota0/501, akhir sebelum mulai, dan jam di luar operasional melalui UI'],
 F09:['Tanggal lahir masa depan dan seluruh field kosong melalui submit UI','Pasien dengan histori sangat panjang dan setiap filter pencarian identitas'],
 F10:['Semua CTA dokter/layanan website klinik dalam kondisi tanpa dokter/sesi','Alur pengguna pertama tanpa URL klinik dalam studi awam langsung'],
 F11:['Tanggal tepat+30/+31 dan sesi baru saja berakhir','Dua form/tab dengan UUID berbeda untuk pasien/sesi sama'],
 F12:['Replay cancel dari tab stale untuk seluruh status terminal','Tautan status tanpa/salah token dan cancellation error pada template resepsionis lewat browser'],
 F13:['Aksi stale dari dua tab resepsionis dan dokter terhadap queue sama','Filter dokter/sesi dikombinasikan dengan pagination dan status terminal'],
 F14:['Display klinik kosong dan seluruh kontrol filter/kiosk','Public display putus/pulih dari socket nyata'],
 F15:['Ulang Mulai periksa dari tab lama sesudah visit selesai','Semua kombinasi pencarian/filter index rekam medis'],
 F16:['HTTP419/sesi berakhir saat autosave dan pemulihan isi editor','Batas20 diagnosis/30 tindakan serta seluruh nilai vital ekstrem','Refresh/Back/leave sebelum debounce autosave selesai dan ketika foto belum disimpan'],
 F17:['Edit severity lain dan dua editor alergi bersamaan melalui UI','Semua variasi acknowledgement alergi pada resep baru/koreksi'],
 F18:['Hapus/tambah30 obat, jumlah9999/10000, serta resep kosong final','Seluruh variasi preview, instruksi panjang dan koreksi yang mengubah resep pasien'],
 F19:['Paste clipboard, batas10 foto/5MB, tipe salah dan abort upload','Akses foto revisi oleh setiap role/tenant asing melalui browser'],
 F20:['Dua submit finalisasi bersamaan dan tab koreksi stale melalui UI','Semua perbedaan array panjang pada histori koreksi di mobile'],
 F21:['Seluruh32 gigi×6 kondisi dan correction-final dental melalui UI','Expiry berdasarkan ends_at; pencabutan revoked_at sudah diuji, keduanya tidak disamakan'],
 F22:['Rekonsiliasi angka seluruh dashboard terhadap fixture setiap periode/timezone','Semua shortcut, empty/loading dan variasi data panjang per role'],
 F23:['Pagination/empty notification center untuk setiap role','Notifikasi masuk socket dan badge realtime lintas browser'],
 F24:['Kombinasi filter aktor/tanggal, pagination, serta tidak ada hasil','Validasi semua metadata audit yang ditampilkan di browser'],
 F25:['Transisi expiry oleh waktu serta reminder subscription yang dipicu command','Seluruh kombinasi paket, renewal terjadwal dan seat overage pada UI'],
 F26:['Successful checkout/notification/reconcile provider nyata','Repeat/delay callback dan browser-return nyata bersama provider'],
 F27:['Prorata akhir hari/bulan dan renewal gabungan lewat UI','Refund yang mencabut add-on melalui flow provider; fixture revoke saja sudah diuji'],
 F28:['Pencarian owner-email, pagination banyak tenant, dan tenant inactive','Semua metadata commercial history di setiap state tenant'],
 F29:['Harga invalid, price toggle inactive dan addon toggle inactive lewat UI','Katalog diubah ketika owner masih memegang tab checkout lama'],
 F30:['Grant lebih tinggi/setara terjadwal dan downgrade paid dengan overlap','Pemulihan grant ganda dan kebijakan revoke grant yang belum tersedia di UI'],
 F31:['Refund ulang pada tab stale dan seluruh status yang tidak refundable','Reconcile+refund lintas role/tenant pada UI, tanpa transaksi provider nyata'],
 F32:['Confidence medium/high dengan data historis terkontrol di browser','ETA untuk CALLED/IN_PROGRESS/terminal dan seluruh kombinasi antrian depan'],
 F33:['Socket aktif putus/pulih, missed-event dan permission revoked pada koneksi aktif','Fokus input saat refetch/pagination dan semua offline queue actions'],
 F34:['Google OAuth success/callback/error/relink dengan akun dummy provider'],
 F35:['NIK format/normalisasi telepon berbagai negara dan lebih dari8 kandidat','Review token stale setelah kandidat berubah dan navigasi ulang ke profil inactive'],
};
const planned=[];
for(const [id,,fr]of features)for(const [index,scenario]of(gapsByFeature[id]||[]).entries())planned.push({id:`GAP-${id}-${index+1}`,feature:id,role:fr.join(', '),scenario,status:['F26','F34'].includes(id)?'BLOCKED':'NOT TESTED',reason:['F26','F34'].includes(id)?'Memerlukan provider/kredensial dummy eksternal; sengaja tidak memakai akun/transaksi pengguna.':'Belum dieksekusi dalam run ini. Kombinasi dan aksi telah dipetakan; test backend/source bukan pengganti bukti browser skenario ini.'});
writeFileSync(join(ev,'planned-gaps.json'),JSON.stringify(planned,null,2));
const census=new Map();for(const p of pages){const key=new URL(p.url).hostname+new URL(p.url).pathname.replace(/\d+/g,':id');if(!census.has(key))census.set(key,{key,roles:new Set(),labels:new Set(),fields:new Set(),actions:new Set(),probes:0,overflow:[]});const q=census.get(key);q.roles.add(p.role);q.labels.add(p.label);p.fields.forEach(f=>q.fields.add(f.name||f.id||f.type));p.actions.filter(a=>a.visible).forEach(a=>q.actions.add(a.text));q.probes++;q.overflow.push(...p.responsive.filter(v=>v.overflow));}
const pageFamilies=[...census.values()];
function walk(dir){return readdirSync(dir,{withFileTypes:true}).flatMap(e=>e.isDirectory()?walk(join(dir,e.name)):[join(dir,e.name)]);}
const controls=[];for(const file of walk(join(repo,'resources/views')).filter(f=>f.endsWith('.blade.php'))){const text=readFileSync(file,'utf8');for(const match of text.matchAll(/<(form|button|input|select|textarea|summary|x-(?:clinic-button|primary-button|secondary-button|danger-button|text-input))\b[^>]*>/g)){const tag=match[0];const attr=name=>tag.match(new RegExp(`(?:^|\\s)${name}=["']([^"']*)["']`))?.[1];controls.push({file:relative(repo,file).replaceAll('\\','/'),line:text.slice(0,match.index).split('\n').length,tag:match[1],name:attr('name'),id:attr('id'),type:attr('type'),aria_label:attr('aria-label'),conditional_note:'Static candidate; Blade/Alpine may alter visibility/number. Not proof of interaction.'});}}
writeFileSync(join(ev,'source-controls.json'),JSON.stringify(controls,null,2));
writeFileSync(join(ev,'feature-catalog.json'),JSON.stringify(features.map(([id,title,roles,actions,states,entry])=>({id,title,roles,actions,states,entry,evidence:records(id).map(r=>r.id),finding_ids:featureFindings(id).map(f=>f.id)})),null,2));

let inventory=header('Feature Inventory — penemuan independen')+caveat;
inventory+=`## Cakupan penemuan\n\n**${features.length} kelompok fitur aktual**, lima role tersimpan, owner sebagai persona Clinic Admin, dan guest/token holder. ${pageFamilies.length} keluarga URL diobservasi dalam ${pages.length} snapshot role/page; setiap snapshot mempunyai probe lebar375/768/1024/1440. ${routes.length} route pada registry adalah alat rekonsiliasi, bukan jumlah fitur atau bukti kelulusan.\n\n`;
inventory+=mdTable(['ID','Fitur','Aktor','Aksi dan lifecycle','Status/state aktual','Bukti run'],features.map(([id,title,rs,actions,states])=>[id,title,rs.join(', '),actions,states,records(id).map(r=>r.id).join(', ')||'NOT TESTED']));
inventory+='\n## Kelengkapan per fitur\n\nKejelasan di bawah adalah evaluasi heuristik berdasarkan interaksi, bukan pengukuran pemahaman pengguna awam. Tidak ada studi pengguna atau screen reader fisik.\n\n';
for(const [id,title,rs,actions,states,entry]of features){const fs=featureFindings(id),rsx=records(id);inventory+=`### ${id} — ${title}\n\n`+mdTable(['Pertanyaan kelengkapan','Jawaban berbasis bukti'],[
 ['Dapat ditemukan?',id==='F10'?'Website klinik ditemukan lewat URL klinik; portal pasien kehilangan entry booking baru (DFU-11).':`Entry/source: ${entry}. Navigasi/label yang benar-benar terlihat dicatat dalam pages.json.`],
 ['Dapat dipahami?', 'Label/form digunakan dalam skenario terstruktur dan eksplorasi; tingkat pemahaman awam belum diukur. '+(fs.length?`Hambatan terkonfirmasi: ${fs.map(f=>f.id).join(', ')}.`:'Tidak ada kesimpulan universal dari label yang tampak jelas.')],
 ['Aksi penting tersedia?',actions+'. Aksi yang belum mempunyai bukti ada pada gap fitur; tidak menuntut delete untuk histori medis.'],
 ['Ada aksi/proses hilang?',id==='F10'?'Entry booking baru/ulang tidak ditemukan di portal.':id==='F28'?'Suspend/unsuspend/revoke UI tidak ditemukan; MF-05.':id==='F18'?'Cetak/katalog obat/engine interaksi belum ditemukan; perlu scope produk.':'Tidak semua kemungkinan bisnis telah ditetapkan; lihat MISSING_FEATURES_REPORT dan gap spesifik.'],
 ['Bisa selesai end-to-end?',`${featureState(id)}. Bukti terpilih: ${rsx.map(r=>r.id+' '+r.status).join('; ')||'Tidak ada eksekusi aksi'}.`],
 ['Data dapat dikelola kembali?', 'Edit/reopen/deactivate/correction mengikuti aksi aktual di atas. Hanya aksi dengan skenario tersimpan yang telah dibuktikan; route tanpa aksi tidak dihitung PASS.'],
 ['Pengguna terjebak?',fs.some(f=>f.id==='DFU-01')?'Ya: pemeriksaan yang dibatalkan pasien tidak dapat difinalisasi.':fs.length?fs.map(f=>f.id+': '+f.impact).join(' '):'Tidak ada stuck path baru yang terkonfirmasi di skenario terpilih; state lain belum menyeluruh.'],
 ['Validasi/error membantu?',fs.some(f=>['DFU-03','DFU-04','DFU-05','DFU-06','DFU-07'].includes(f.id))?'Tidak konsisten; lihat temuan validasi/feedback terkait.':'Sebagian jalur validasi/recovery diuji; seluruh field dan kombinasi tidak disimpulkan lulus.'],
 ['Parsial?', 'Ya dalam arti cakupan verifikasi belum menyeluruh; provider/socket/aksi yang belum dijalankan dipisahkan. Status kode ada bukan berarti seluruh workflow lulus.'],
 ['Menyelesaikan kebutuhan?',fs.length?`Ada hambatan ${fs.map(f=>f.id).join(', ')}. ${gapsByFeature[id]?.join('; ')||''}.`:`Fungsi terpilih dibuktikan, dengan batas: ${gapsByFeature[id]?.join('; ')||'lihat matriks skenario'}.`],
 ])+'\n';}
inventory+='## Inventaris halaman, formulir dan kontrol\n\n'+mdTable(['Keluarga URL','Persona yang diobservasi','Field yang terlihat pada snapshot','Aksi terlihat (contoh, maksimal12)','Probe'],pageFamilies.map(p=>[p.key,[...p.roles].join(', '),[...p.fields].join(', '),[...p.actions].slice(0,12).join('; '),p.probes]));
inventory+=`\nKontrol lengkap dan kondisi terlihat/disabled, form/action, dropdown/modal serta seluruh nama field ada di ${evidenceLink('pages.json')}. Kandidat kontrol dari **seluruh Blade** (termasuk cabang yang belum dirender): ${evidenceLink('source-controls.json')}. Ekstraksi statis berbasis tag mempunyai batas pada markup multiline/dinamis; data runtime dan inspeksi source tetap acuan, bukan hitungan regex.\n\n## Entitas/lifecycle yang sengaja tidak diperlakukan sebagai CRUD hapus\n\nPatient/history: edit/deactivate; staf: deactivate/reactivate; layanan/jadwal: active flag; RME final: Modification beralasan; payment/subscription period/event: histori/refund/grant. Appointment/queue: terminal states, bukan delete. Owner tetap role Clinic Admin dengan owner_user_id; satu owner/satu klinik.\n`;
write('FEATURE_INVENTORY.md',inventory);

function authorization(id,role){
 const r=role==='Clinic Owner'?'Clinic Admin':role;
 if(id==='F01')return role==='Guest'?'Login/recovery tanpa autentikasi; logout memerlukan sesi':'Akun sendiri, portal sesuai tipe/role';
 if(id==='F02')return role==='Guest'?'Pendaftaran pasien/owner dan verifikasi akun sendiri':'Halaman guest diarahkan sesuai sesi';
 if(id==='F03')return role==='Guest'?'Autentikasi wajib':'Akun sendiri; delete dibatasi kepemilikan/histori';
 if(['F00','F10','F14'].includes(id))return 'Baca publik klinik aktif; booking publik hanya Patient terverifikasi';
 if(id==='F12'&&role==='Guest')return 'Status/cancel publik hanya dengan token sah; bukan akses daftar internal';
 if(id==='F22')return role==='Guest'?'Autentikasi wajib':'Dashboard role sendiri; content/policy tetap dibatasi';
 if(id==='F23')return role==='Guest'?'Autentikasi wajib':'Notifikasi milik akun sendiri';
 if(id==='F18'&&r==='Patient')return 'Hanya resep FINAL milik sendiri; tidak memperoleh isi SOAP dokter';
 if(id==='F25'&&!['Clinic Owner','Guest'].includes(role))return 'Banner/aturan operasional; history/payment/plan owner tidak otomatis terbuka';
 if(id==='F32')return 'Tracker milik pasien/token atau board publik nonmedis';
 if(id==='F33')return 'Channel sesuai akses; refetch tetap melewati route/policy';
 if(id==='F34')return role==='Guest'?'OAuth pasien dengan terms/kredensial provider':'Bukan pengganti login pengelola';
 if(['F04','F27'].includes(id))return role==='Clinic Owner'?'Owner saja; onboarding/eligibility berlaku':'Tidak boleh mengelola onboarding/add-on owner';
 if(id==='F26')return ['Clinic Owner','Platform Admin'].includes(role)?'Owner invoice sendiri / platform commercial actions; provider harus tervalidasi':'Webhook system memakai signature; user lain tidak mendapat aksi komersial';
 if(['F28','F29','F30','F31'].includes(id))return r==='Platform Admin'?'Role + permission platform sesuai aksi; metadata/komersial saja':'Tidak boleh mengelola platform';
 if(['F15','F16','F17','F19','F20','F21'].includes(id))return r==='Doctor'?'Dokter ditugaskan + policy resource; status/entitlement berlaku':'Tidak memperoleh isi medis dari role ini';
 if(['F05','F06','F07','F08','F24'].includes(id))return r==='Clinic Admin'?'Klinik sendiri + permission/policy; write dibatasi subscription':'Tidak boleh mengelola endpoint admin ini';
 if(['F09','F11','F35'].includes(id))return ['Clinic Admin','Receptionist'].includes(r)?'Administrasi klinik sendiri; resource/foreign key harus konsisten':id==='F11'&&r==='Patient'?'Booking online sebagai pasien sendiri':'Bukan akses CRUD administratif; public view terpisah';
 if(id==='F13')return ['Clinic Admin','Receptionist'].includes(r)?'Queue administrasi tenant; start/complete oleh dokter':r==='Doctor'?'Antrean dokter sendiri; call/start sesuai policy':'Tidak mendapat aksi queue internal';
 return 'Lihat policy dan permission registry';
}
let roleReport=header('Role × Action × State Matrix')+caveat+'Lima role tersimpan: **'+actualRoles.join(', ')+'**. Clinic Owner adalah persona dari Clinic Admin, bukan role keenam. Guest/token holder adalah konteks akses. Dokter lain dibedakan oleh assignment resource.\n\n';
roleReport+='## Permission aktual\n\n'+mdTable(['Role','Permission tersimpan'],actualRoles.map(role=>[role,permissions.filter(p=>p.name===role).map(p=>p.permission).join(', ')]));
roleReport+='\n## Matriks kewenangan dan bukti\n\nSetiap row memuat feature, persona, aksi, dan state yang relevan. Daftar state adalah himpunan domain, **bukan klaim seluruh kombinasi telah diuji**. Kolom bukti mengarah ke skenario aktual; sel tanpa eksekusi tepat adalah NOT TESTED.\n\n';
const matrix=[];for(const [id,title,,actions,states]of features)for(const role of roles)matrix.push([id,role,actions,states,authorization(id,role),records(id).map(r=>r.id).join(', ')||'NOT TESTED']);
roleReport+=mdTable(['Feature','Role/persona','Action','State','Batas akses dari source','Bukti fitur (cek aktor pada skenario)'],matrix);
roleReport+='\nUji non-owner Clinic Admin pada run ini membuktikan login/menu, bukan semua operasi CRUD. Authorization medis lintas role dibuktikan RME-11; invoice owner lintas role/tenant oleh SUB-04; foreign-patient detail oleh DISCOVERY-NEGATIVE; pencabutan role sesi aktif oleh ROLE-CHANGE. Tidak ada bypass medis terkonfirmasi dalam skenario tersebut, dan hasil ini tidak mengesahkan seluruh permission custom.\n';
write('ROLE_ACTION_MATRIX.md',roleReport);

let scenarioReport=header('Scenario Test Matrix')+caveat+`## Eksekusi browser terbaru\n\n${latest.length} skenario aktif setelah supersession: **${counts.PASS} PASS, ${counts.FAIL} FAIL, ${counts.BLOCKED} BLOCKED, ${counts['NOT TESTED']} NOT TESTED**. ${attempts.length-latest.length} attempt awal disimpan terpisah sebagai histori harness, tidak dihitung lagi. FAIL adalah ekspektasi skenario yang tidak terpenuhi, bukan jumlah bug unik.\n\n`;
scenarioReport+=mdTable(['ID','Fitur','Role','Skenario','Status','Langkah aktual','Bukti / hasil / alasan'],latest.map(r=>[r.id,r.feature,r.role,r.scenario,r.status,(r.steps||[]).join(' → '),r.reason||JSON.stringify(r.observed||{})]));
scenarioReport+='\n## Skenario lanjutan yang dipetakan tetapi belum dijalankan\n\nTidak mengarang state domain. Skenario ini memakai lifecycle aktual dan menjelaskan batas run, bukan menduplikasi PASS backend menjadi PASS UI.\n\n'+mdTable(['ID','Fitur','Role','Skenario','Status','Alasan'],planned.map(r=>[r.id,r.feature,r.role,r.scenario,r.status,r.reason]));
scenarioReport+='\n## Histori attempt dan koreksi harness\n\n'+mdTable(['Attempt','Status saat attempt','Diganti oleh','Catatan'],attempts.filter(r=>r.superseded_by).map(r=>[r.id,r.status,r.superseded_by,r.harness_note||'Lihat attempt pengganti']));
scenarioReport+=`\nRaw evidence ${evidenceLink('scenarios.json')}; gap terstruktur ${evidenceLink('planned-gaps.json')}. Tidak ada status PASS untuk OAuth/DOKU sukses atau socket nyata.\n`;
write('SCENARIO_TEST_MATRIX.md',scenarioReport);

function detail(f){return `## ${f.id} — ${f.title}\n\n`+mdTable(['Atribut','Detail'],[
 ['Kategori',f.category],['Severity',f.severity],['Fitur / role',`${f.feature}; ${f.roles}`],['Kondisi awal',f.pre],['Langkah reproduksi',f.steps.map((s,i)=>`${i+1}. ${s}`).join(' → ')],['Hasil aktual',f.actual],['Hasil yang diharapkan',f.expected],['Dampak pengguna',f.impact],['Rekomendasi',f.fix],['Skenario',f.scenarios.join(', ')],['Bukti',f.evidence.map(evidenceLink).join(', ')],['Sumber terkait',f.source.map(s=>{const [path,line]=s.split(':');return `[${s}](../../${path}${line?'#L'+line:''})`;}).join(', ')],
 ])+'\n';}
const functional=findings.filter(f=>!['DFU-09','DFU-10','DFU-11'].includes(f.id));
write('FUNCTIONAL_BUG_REPORT.md',header('Functional Bug Report')+caveat+'Temuan terkonfirmasi OPEN; tidak ada perbaikan source pada tugas audit ini. Kelompok akar masalah dipakai agar empat assertion feedback queue tidak dihitung empat bug.\n\n'+functional.map(detail).join('')+'## Authorization/Role Issue\n\nTidak ada temuan bypass hak medis/invoice pada skenario role negatif yang dijalankan. Ini bukan audit keamanan menyeluruh. Batas UI dan coverage role ada pada ROLE_ACTION_MATRIX.md.\n');
let missingReport=header('Missing Features, Incomplete Workflow & Product Decisions')+caveat+detail(findings.find(f=>f.id==='DFU-11'));
for(const [id,title,roles,pre,steps,actual,fix,impact,category]of missing)missingReport+=`## ${id} — ${title}\n\n`+mdTable(['Atribut','Detail'],[['Kategori',category],['Severity','P2 gap kebutuhan bila requirement ini masuk scope rilis; prioritas memerlukan keputusan produk. Tidak dihitung sebagai bug runtime.'],['Fitur/role',roles],['Kondisi awal',pre],['Langkah reproduksi/penemuan',steps],['Hasil aktual',actual],['Hasil diharapkan',fix],['Dampak',impact],['Bukti',`${evidenceLink('routes.json')}, ${evidenceLink('navigation.json')}, ${evidenceLink('source-controls.json')}; source app/routes/views dan PROJECT_ANALYSIS.md sebagai acuan requirement historis.`],['Rekomendasi',fix],['Status pengujian','NOT TESTED untuk workflow yang tidak mempunyai implementasi; ketiadaan UI/source adalah hasil penemuan, bukan PASS end-to-end.']])+'\n';
missingReport+='## Keputusan produk lain yang belum boleh dianggap bug\n\n'+mdTable(['ID','Keputusan','Observasi dan konsekuensi','Bukti'],[
 ['PD-01','Trial setelah memilih Free','Trial hanya ditawarkan sebelum subscription awal; Free yang sudah dipilih tidak menampilkan trial meskipun pengguna belum mencobanya. Tentukan apakah ini sengaja dan jelaskan sebelum aktivasi.','AUTH-03, AUTH-04-R1; onboarding/plans/index.blade.php'],
 ['PD-02','Ubah/batal booking vs reschedule','UI mendukung pembatalan dan booking baru. Direct reschedule belum ditemukan; tentukan kebutuhan mempertahankan nomor/histori jika menambahnya.','Route inventory; F11/F12'],
 ['PD-03','Penghapusan staf/pasien/medis','Tidak wajib menyediakan destroy. Deactivate/reactivate, terminal state, dan Modification adalah lifecycle yang tersedia dan menjaga histori. Role staf immutable dari edit form.','ADM-07, PAT-05, RME-09-R3, PHOTO-HISTORY, DOCTOR-DELETE-GUARD'],
 ['PD-04','Cakupan dental/obat','Odontogram32 gigi permanen, nama obat free text, warning interaksi bersifat pengingat. Dentisi anak/katalog/engine klinis perlu requirement eksplisit; tidak dinyatakan cacat tanpa acuan.','F18/F21; source dental-chart/VisitController'],
 ]);
write('MISSING_FEATURES_REPORT.md',missingReport);
write('UX_PROBLEMS_REPORT.md',header('UX Problems Report')+caveat+findings.filter(f=>f.category.includes('UX')||['DFU-01','DFU-02','DFU-12'].includes(f.id)).map(detail).join('')+'## Observasi UX dengan dampak lebih rendah\n\n'+mdTable(['ID','Role / kondisi / langkah','Aktual dan dampak','Ekspektasi / rekomendasi','Severity / bukti'],[
 ['UX-N01','Owner expired/suspended → Tambah layanan → isi form → simpan','Banner hanya-baca ada, tetapi tombol create/save tetap aktif; setelah bekerja pengguna berakhir pada403. Backend benar menolak.','Hadirkan disabled/read-only action atau penjelasan kontekstual sebelum input.','P3; SUB-05; screenshots/SUB-05-expired-blocked-write.png'],
 ['UX-N02','Resepsionis → pasien inactive → detail','Tombol Buat booking tetap ada meskipun search mengecualikan pasien inactive. Pengguna baru mengetahui prasyarat setelah berpindah form.','Tampilkan status dan alasan reaktivasi dekat aksi; jangan menghapus histori.','P3; PAT-05'],
 ['UX-N03','Role campuran → baca label/status','Terdapat campuran BOOKED/CANCELLED, Check-in/No-show, Finalized/Modification, dan istilah Indonesia. Tidak dilakukan studi pemahaman istilah.','Uji istilah bersama staf/pasien; gunakan label konsisten tanpa mengubah enum backend.','P3 suggestion; pages.json; belum terbukti menyebabkan kegagalan tugas awam'],
 ])+'\nKeyboard native drawer lima role, Escape/focus return, dialog finalize/delete, pilihan jadwal lewat keyboard dan pagination sudah dijalankan. Pemeriksaan screen reader, zoom200%, touch keyboard perangkat fisik, kontras terukur dan seluruh loading/error state belum menyeluruh. Resize/DOM tidak disamakan dengan usability study.\n');

const charters=[
 ['EX-01','Pemilik klinik pemula','Buat akun owner → signed mail dummy → klinik → Free; owner kedua memilih Trial awal. Akun QA dan klinik QA baru.','AUTH-02/03/04-R1, CLINIC-*; entry aktif, batas trial setelah Free dicatat PD-01.'],
 ['EX-02','Resepsionis terburu-buru/salah isi','Buat pasien, teleponabc/31digit, shared-family phone dengan review, cari/edit/deactivate; booking keyboard.','ADM-10/11/12/13, UX-05, PAT-05; DFU-05/06.'],
 ['EX-03','Pelayanan lintas role','Resepsionis booking/check-in/call/skip/return → dokter start/SOAP/final/Modification → buka ulang.','BOOK-/QUEUE-/RME-; normal flow berjalan; feedback queue DFU-03.'],
 ['EX-04','Pasien kembali tanpa membaca instruksi','Login umum → dashboard → Booking saya → cari booking baru; akhirnya memakai website klinik yang URL-nya diketahui.','UX-02, BOOK-03-R1; DFU-11.'],
 ['EX-05','Pasien dan dokter melakukan urutan tidak lazim','Mulai pemeriksaan sebelum jam sesi → pasien membatalkan → dokter mencoba finalisasi.','CROSS-03/04/12; data dummy appointment25/visit16; DFU-01.'],
 ['EX-06','Dua tab dan koneksi editor putus','Buka record sama di dua tab; edit tab pertama lalu stale tab; offline lalu online; foto manual dan histori.','RME-05/06/07, PHOTO-HISTORY; stale ditolak, data pulih, foto histori200.'],
 ['EX-07','Admin mengelola data yang sudah dipakai','Edit hari sesi berbooking/final visit; nonaktif layanan, lihat snapshot dan pilihan baru; pulihkan fixture lewat UI.','CROSS-08, SERVICE-05; DFU-02.'],
 ['EX-08','Operator platform salah klik','Edit harga/toggle katalog; grant/downgrade; refund review dummy; double-click grant satu bulan.','PLAT-*, GRANT-DOUBLE; snapshot aman, grant ganda DFU-12.'],
 ['EX-09','Pemakai mobile/tablet dan keyboard','375px booking double-click;768px queue;1024px dokter;1440px admin; lima drawer Tab/ShiftTab/Escape; label filter.','CROSS-01-R1/03, KEY-01, UX-01/03; DFU-09/10.'],
 ['EX-10','Pasien menunggu perubahan di tab tracker','Booking → BOOKED tracker → resepsionis check-in di browser lain → bring-to-front dan tunggu fallback polling.','ETA-01-R2 PASS; startup fallback memerlukan budget45detik; tidak menguji socket aktif.'],
 ];
write('EXPLORATORY_FINDINGS.md',header('Exploratory Findings — charter dan langkah aktual')+caveat+mdTable(['Charter','Persona/tujuan','Langkah aktual dan data','Hasil/evidence'],charters)+'\nData yang dipakai seluruhnya dari DemoSeeder atau dibuat melalui UI dengan prefix QA. Tautan verification/reset/booking rahasia hanya berada pada runtime privat; tidak dicetak dalam laporan. Eksplorasi dipandu menu/label lebih dahulu; URL langsung dipakai terpisah untuk pengujian authorization, recovery, dan entry yang tidak dapat ditemukan.\n\n## Catatan bias dan harness\n\nTest terstruktur bukan observasi manusia sungguhan. Source dipakai untuk menemukan feature dan memeriksa persistence, tidak sebagai pengganti langkah browser. Kegagalan selector, sinkronisasi Livewire, fixture account_type tidak sah, SQL join yang salah, timing fallback, dan typo assertion tidak diklaim bug aplikasi. Histori tetap ada di SCENARIO_TEST_MATRIX. Fase dapat dilanjutkan dari evidence/scenarios.json dan checkpoint terbaru tanpa mengulangi PASS; runtime hanya boleh dibuka kembali pada database/storage disposable.\n');
write('CROSS_FEATURE_REPORT.md',header('Cross-Feature Functional Report')+caveat+findings.filter(f=>f.category.includes('Cross-Feature')).map(detail).join('')+'## Integrasi yang terbukti pada skenario terpilih\n\n'+mdTable(['Alur','Bukti','Hasil dan batas'],[
 ['Patient → appointment → queue','BOOK-02/03-R1, CROSS-01-R1, BOOK-QUOTA','Transaksi terlihat dari DB: satu appointment/queue; double-click booking satu row; kuota penuh tidak menambah row.'],
 ['Check-in → dokter start → final','QUEUE-01/05-R1, RME-08-R2','Check-in tidak membuat visit; start membuat visit; final menyamakan RME/visit/appointment/queue.'],
 ['Final → koreksi → foto historis','RME-09-R3, PHOTO-HISTORY','Status tetap FINAL, revision bertambah, foto sebelumnya tetap tersedia lewat link UI ber-policy.'],
 ['Pasien → dokter → resep pasien','JOURNEY-02-R1','Resep final baru muncul di dashboard pasien sendiri; dokter dapat menyimpan/finalisasi visit aktif saat subscription suspended fixture.'],
 ['Katalog harga → landing → invoice lama','PLAT-02/03/04','Harga/aktif baru memengaruhi katalog; nominal invoice lama tetap snapshot.'],
 ['Seat deactivate → kapasitas baru','SUB-SEATS','Create seat tambahan ditolak, deactivate staf pertama membuka kapasitas untuk staf kedua.'],
 ['Layanan/pasien inactive → pilihan booking','SERVICE-05, PAT-05','Layanan/pasien inactive dikecualikan dari pilihan baru; snapshot appointment lama dipertahankan.'],
 ['Role dicabut → sesi/menu','ROLE-CHANGE','Request operasional berikut403, menu berubah; tidak menguji socket yang sudah aktif.'],
 ['Antrean → notification/read badge','CROSS-10','Notifikasi tersimpan dan read-all memperbarui DB. Broadcast notification aktual belum dijalankan.'],
 ['Add-on dicabut → dental/general','DENT-01/02/03','Dental persistence dan read-only tetap menyimpan histori; RME General tetap bisa disimpan. Entitlement berasal dari fixture, bukan pembayaran.'],
 ['Check-in → polling tracker → ETA','ETA-01-R2','Menunggu dan ETA/confidence muncul tanpa reload manual; socket nyata belum diuji.'],
 ])+'\nRegresi DB/HTTP tambahan:363 test,2438 assertion PASS pada SQLite memory. Hasil itu tidak membuktikan setiap kombinasi UI, lock MySQL, atau layanan eksternal.\n');

const measured=features.filter(([id])=>records(id).some(r=>['PASS','FAIL'].includes(r.status))).length;
let coverage=header('Coverage Gaps & Resume Checkpoint')+caveat+'## Cakupan yang dapat dipertanggungjawabkan\n\n'+mdTable(['Dimensi','Hasil run','Batas'],[
 ['Fitur',`${features.length}/${features.length} kelompok masuk inventaris; ${measured} mempunyai minimal satu skenario aksi browser PASS/FAIL.`,'Satu aksi bukan keseluruhan fitur; kelengkapan per fitur ada pada inventory.'],
 ['Role','5 role aktual; owner/non-owner admin, dokter gigi, guest, new staff, Free/trial/expired/suspended owner ikut dieksplorasi.','CRUD non-owner dan seluruh permission custom belum menyeluruh.'],
 ['Halaman',`${pages.length} snapshot; ${pageFamilies.length} keluarga URL; probe4 lebar per snapshot.`,'Resize/DOM adalah probe layout; tidak semua aksi diulang pada tiap viewport.'],
 ['Aksi',`${counts.PASS+counts.FAIL} skenario browser aktif dieksekusi; ${counts.PASS} PASS / ${counts.FAIL} FAIL.`,'Jumlah klik/semua tombol yang dieksekusi tidak diinstrumentasi; tidak ada persentase palsu action coverage.'],
 ['State','8 queue states dijalankan/render-filter;4 appointment states;DRAFT/FINAL;Free/trial aktif UI;active/expired/suspended fixture.','7 payment statuses dibuka, bukan7 provider lifecycle sukses; addon revoked bukan expiry waktu.'],
 ['Skenario belum selesai',`${counts.BLOCKED} aktif BLOCKED; ${planned.length} gap lanjutan terdaftar dengan status/alasan.`,'Gap tambahan tidak ditambahkan ke jumlah eksekusi browser.'],
 ['End-to-end','Staff booking→dokter→final/koreksi; Patient booking→dokter→resep; owner onboarding; platform grant/refund lokal.','Provider Google/DOKU dan socket aktif tidak selesai end-to-end.'],
 ['Backend regression',`${regression.tests} tests, ${regression.assertions} assertions, seluruhnya PASS.`,'SQLite memory; tidak dihitung sebagai UI PASS atau bukti concurrency MySQL.'],
 ]);
coverage+='\n## Gap prioritas\n\n'+mdTable(['Fitur','Skenario','Status','Alasan'],planned.map(r=>[r.feature,r.scenario,r.status,r.reason]));
coverage+='\n## Batas lingkungan dan cara melanjutkan\n\n- Database browser adalah SQLite file sementara; regression memakai SQLite :memory:, bukan database aplikasi/MySQL pengguna. Storage/public build dipisah melalui bootstrap/router audit. Factory/default relasi tidak diasumsikan benar tanpa fixture eksplisit.\n- Mail verification/reset ditangkap dari array transport ke berkas privat sementara dan tautannya dibuka di Chrome; pengiriman SMTP/inbox pengguna nyata belum dibuktikan. Mail dummy privat dibersihkan setelah pengujian.\n- DOKU/Google credentials dikosongkan. Aktivasi add-on untuk uji dental memakai fixture entitlement; tidak membuktikan purchase atau callback sukses.\n- Build frontend awal mewarisi konfigurasi Vite; sebelum retest polling digunakan target socket disposable yang eksplisit. ETA-01/R1 memiliki budget16detik dan tidak membuktikan gagal fungsional; ETA-01-R2 foreground/budget45detik PASS. Tidak ada klaim latency SLA.\n- Skill testing-best-practices tidak ditemukan pada pencarian katalog lokal; tidak diklaim dibaca. Tidak ada test PHP baru atau source Volt yang diubah. Panduan Ponytail dipakai; volt-development dibaca sebagai konteks komponen auth/profil. Tool search-docs tidak tersedia pada sesi ini; tidak ada API Volt baru diimplementasikan.\n- Tidak menjalankan Pint terhadap perubahan PHP pengguna, karena audit hanya menambah artefak/scripts JS dan tidak mengubah source PHP.\n- Perangkat fisik, screen reader, zoom200%, usability study awam, semua kombinasi field/status/tab tidak selesai.\n- Snapshot/page census dan skenario awal tersimpan pada JSON. Checkpoint bernama per fase tersedia sejak pembaruan harness di fase auth; checkpoint awal yang sempat ditimpa dapat ditelusuri dari started timestamps dan hasil skenario, bukan checkpoint historis yang direkayasa.\n\nServer audit telah dihentikan. Database/storage dummy dipertahankan untuk checkpoint; environment privat dipangkas ke nilai disposable eksplisit dan kredensial eksternal tetap kosong. Untuk melanjutkan fixture yang sama: jalankan `node qa-audit/deep-functional-ux/scripts/resume-runtime.mjs`, lalu tambah/jalankan fase yang diperlukan. `runtime.mjs` membuat fixture baru; gunakan direktori evidence baru bila ingin audit ulang bersih. AUDIT_RESUME=1 melewati PASS dan menambahkan attempt untuk skenario sebelumnya gagal; jangan menjalankan semua fase ulang pada data yang telah dihapus/diubah tanpa memeriksa precondition. Evidence JSON dan status adalah sumber checkpoint, bukan sekadar nama file laporan.\n';
write('COVERAGE_GAPS.md',coverage);

const sev=Object.fromEntries(['P1','P2','P3'].map(s=>[s,findings.filter(f=>f.severity===s).length]));
let finalReport=header('Final Functional & UX Report')+caveat+`## Kesimpulan\n\n**Belum dapat dinyatakan seluruh fitur aman dipakai manusia pada seluruh kondisi.** Jalur utama terpilih berjalan, tetapi audit menemukan **${findings.length} kelompok temuan terkonfirmasi OPEN: ${sev.P1} P1, ${sev.P2} P2, ${sev.P3} P3**. Cakupan inventaris sudah memuat seluruh kelompok fitur/role yang ditemukan; eksekusi exhaustive dan layanan eksternal tetap mempunyai gap eksplisit. Ini kesimpulan fungsional/UX, bukan penilaian kesiapan production.\n\n`;
finalReport+='## Prioritas perbaikan\n\n'+mdTable(['ID','Severity','Masalah','Dampak'],[...findings].sort((a,b)=>a.severity.localeCompare(b.severity)||a.id.localeCompare(b.id)).map(f=>[f.id,f.severity,f.title,f.impact]));
finalReport+='\nPaling dulu: **DFU-01**, karena pembatalan pasien membuat dokter tidak dapat menyelesaikan visit. Berikutnya **DFU-12** (grant ganda) dan **DFU-02** (sesi/booking berbeda hari). Temuan lain terutama validasi, feedback, aksesibilitas, waktu dan entry booking pasien. Source tidak diperbaiki selama audit.\n\n';
finalReport+='## Apa yang sudah terbukti berfungsi\n\nRegistrasi dan verifikasi owner/pasien/staf dengan mail dummy; onboarding Free/trial; CRUD/lifecycle terpilih clinic/staff/service/schedule/patient; review duplikat keluarga; booking online/walk-in dan kuota; check-in/FIFO/call/skip/return/no-show/cancel; dokter start; SOAP/vital/structured/autosave/offline/conflict; resep final pasien; foto privat dan retensi koreksi; odontogram persistence dan revocation; pembatasan subscription dengan pengecualian visit aktif; katalog/snapshot/grant/downgrade/refund dummy; notification read; audit filter; role negative; keyboard drawer lima role. Bukti ini terikat pada skenario, bukan status PASS global setiap fitur.\n\n';
finalReport+='## Angka dan bukti run\n\n'+mdTable(['Ukuran','Hasil'],[['Fitur aktual / role',`${features.length} kelompok;5 role;owner persona tambahan;guest/token context`],['Browser terbaru',`${latest.length} skenario aktif: ${counts.PASS} PASS, ${counts.FAIL} FAIL, ${counts.BLOCKED} BLOCKED, ${counts['NOT TESTED']} NOT TESTED`],['Snapshot halaman',`${pages.length} observasi,${pageFamilies.length} keluarga URL,4 viewport probes masing-masing`],['Backend',`${regression.tests}/${regression.tests} PASS;${regression.assertions} assertion;SQLite memory`],['Gap terencana',`${planned.length} skenario tambahan berstatus/alasan; tidak dihitung sebagai eksekusi`],['Source baseline','Perubahan pengguna dipertahankan; tambahan hanya qa-audit/deep-functional-ux/'],['Bukti raw',`${evidenceLink('scenarios.json')}; ${evidenceLink('regression.txt')}; ${evidenceLink('pages.json')}; ${evidenceLink('checkpoint.json')}`]]);
finalReport+='\n## Belum lengkap atau belum terverifikasi\n\nDOKU sukses/notification/reconcile, Google OAuth, socket Reverb aktif putus/pulih, mail delivery nyata, seluruh kombinasi aksi/role/state, browser/perangkat fisik dan studi usability belum dituntaskan. Billing pasien, export/cetak, discovery luas, suspend/unsuspend UI, reminder appointment, multi-cabang dan Edge tidak menjadi fitur selesai hanya karena ada mock, enum, atau laporan lama. Lihat keputusan scope pada laporan missing dan gap per fitur. Tidak ada pengujian load/stress/deployment atau transaksi nyata.\n\n';
finalReport+='## Laporan pendamping\n\n'+['FEATURE_INVENTORY.md','ROLE_ACTION_MATRIX.md','SCENARIO_TEST_MATRIX.md','FUNCTIONAL_BUG_REPORT.md','MISSING_FEATURES_REPORT.md','UX_PROBLEMS_REPORT.md','EXPLORATORY_FINDINGS.md','CROSS_FEATURE_REPORT.md','COVERAGE_GAPS.md'].map(f=>`- [${f}](${f})`).join('\n')+'\n\nAudit historis pada qa-audit/ dan PROJECT_ANALYSIS.md dipakai sebagai referensi. Klaim Oktober yang sudah berubah (autosave, alergi, persistence dental, structured editor, ETA, katalog platform) tidak diulang sebagai kekurangan saat ini. Seluruh temuan baru berasal dari checkout lokal dan bukti run ini.\n';
write('FINAL_FUNCTIONAL_UX_REPORT.md',finalReport);

const metrics={date:'2026-11-13',timezone:'Asia/Bangkok',features:features.length,roles:actualRoles,page_observations:pages.length,page_families:pageFamilies.length,route_registry:routes.length,active_scenarios:latest.length,counts,attempts:attempts.length,superseded_attempts:attempts.length-latest.length,planned_gap_scenarios:planned.length,unique_findings:findings.length,severity:sev,backend:{tests:regression.tests,assertions:regression.assertions,result:regression.result},coverage_claim:'Inventory complete for discovered groups; exhaustive execution incomplete; see explicit gaps.'};
writeFileSync(join(ev,'coverage-summary.json'),JSON.stringify(metrics,null,2));
writeFileSync(join(ev,'findings.json'),JSON.stringify(findings,null,2));
writeFileSync(join(ev,'checkpoint.json'),JSON.stringify({...checkpoint,phase:'reports-written-verification-pending',at:new Date().toISOString(),counts,features:features.length,findings:findings.length},null,2));
console.log(JSON.stringify(metrics));
