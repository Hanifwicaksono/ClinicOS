import assert from 'node:assert/strict';
import {writeFileSync} from 'node:fs';
import {base,client,check,sql,shot,finish,state,results,resultsFile} from '../scripts/common.mjs';
const s=state(),c=await client('deep-doctor','deep.doctor@clinicos.test'),p=c.page;
try {
 await check('REG-FINALIZE','F20','Doctor','Finalisasi, dialog keyboard, serta state atomik setelah validasi alergi',['Buka visit','Acknowledgement bila alergi kritis aktif','Escape dialog','Konfirmasi finalisasi','Periksa empat status'],async()=>{
  await p.goto(base+`/doctor/visits/${s.journeyVisitId}`);
  if(await p.locator('#critical_allergies_acknowledged').count())await p.locator('#critical_allergies_acknowledged').check();
  await p.waitForFunction(()=>!document.querySelector('[data-rme-form]').rmeAutosave.unsaved);
  await p.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await p.getByRole('dialog',{name:'Konfirmasi finalisasi kunjungan'}).waitFor({state:'visible'});await p.keyboard.press('Escape');await p.getByRole('dialog',{name:'Konfirmasi finalisasi kunjungan'}).waitFor({state:'hidden'});
  await p.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await p.getByRole('button',{name:'Finalisasi kunjungan',exact:true}).click();await p.locator('#correction_reason').waitFor({state:'visible'});
  const row=sql.prepare('SELECT v.status visit,a.status appointment,q.status queue,m.status record FROM visits v JOIN appointments a ON a.id=v.appointment_id JOIN queues q ON q.appointment_id=a.id JOIN medical_records m ON m.visit_id=v.id WHERE v.id=?').get(s.journeyVisitId);assert.deepEqual({...row},{visit:'COMPLETED',appointment:'COMPLETED',queue:'COMPLETED',record:'FINAL'});return {...row};
 });
 await check('REG-MODIFICATION','F20','Doctor','Koreksi beralasan mempertahankan final dan menyimpan histori',['Alasan pendek diblok','Isi alasan sah','Ubah assessment','Simpan dan buka histori'],async()=>{
  await p.locator('#correction_reason').fill('pendek');assert.equal(await p.locator('#correction_reason').evaluate(e=>e.validity.tooShort||!e.checkValidity()),true);
  await p.locator('#correction_reason').fill('Koreksi dummy regresi setelah perbaikan DFU');await p.locator('[name=assessment]').fill('Assessment setelah Modification');await p.getByRole('button',{name:'Simpan Modification',exact:true}).click();await p.waitForLoadState('domcontentloaded');await p.getByText('Lihat semua perubahan',{exact:true}).click();assert.ok((await p.locator('main').innerText()).includes('Assessment setelah Modification'));await shot(p,'REG-MODIFICATION');const row=sql.prepare('SELECT m.status,(SELECT COUNT(*) FROM medical_record_revisions r WHERE r.medical_record_id=m.id) revisions FROM medical_records m WHERE visit_id=?').get(s.journeyVisitId);assert.equal(row.status,'FINAL');assert.equal(row.revisions,1);return {...row};
 });
 await check('REG-REOPEN','F15','Doctor','Rekam medis final dapat ditemukan dan dibuka kembali',['Menu Rekam medis','Cari pasien','Buka detail'],async()=>{await p.goto(base+'/doctor/medical-records');const name=sql.prepare('SELECT p.name FROM patients p JOIN visits v ON v.patient_id=p.id WHERE v.id=?').get(s.journeyVisitId).name;await p.locator('[name=search]').fill(name);await p.locator('form').filter({has:p.locator('[name=search]')}).getByRole('button').click();await p.waitForLoadState('domcontentloaded');await p.locator(`a[href$="/doctor/visits/${s.journeyVisitId}"]`).first().click();await p.locator('#correction_reason').waitFor({state:'visible'});return {final_reopened:true};});
 for(const [old,id] of [['RME-08','REG-FINALIZE'],['RME-09','REG-MODIFICATION'],['RME-12','REG-REOPEN']]){const r=results.find(x=>x.id===old);r.superseded_by=id;r.harness_note='Retest memenuhi acknowledgement alergi setelah reload; skenario awal tidak memenuhinya.';}
 writeFileSync(resultsFile,JSON.stringify(results,null,2));
}finally{await finish('clinical-corrected-preconditions');}
