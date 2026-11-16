import {run,client,fixture,sql,base,openVisit,saved,wire,assert,shot,finish,credential,browser} from './common.mjs';
const t=fixture.tenants.dental,c=await client('closure-doctor',t.doctor),p=c.page;
const limits=fixture.exams.limits;
async function ack(page){if(await page.locator('#critical_allergies_acknowledged').count())await page.locator('#critical_allergies_acknowledged').check();}
async function addRx(page,index,name='QA Dummy Medicine'){
 if(!await page.locator('#medicine_name_'+index).count())await page.getByRole('button',{name:'+ Tambah obat',exact:true}).click();
 await page.locator('#medicine_name_'+index).fill(name);await page.locator('#medicine_dosage_'+index).fill('1 tablet');await page.locator('#medicine_frequency_'+index).fill('1 kali sehari');await page.locator('#medicine_quantity_'+index).fill('1');await page.locator('#medicine_unit_'+index).fill('tablet');
}
try {
 await openVisit(p,limits.visit);
 for(const [field,min,max,step] of [['weight_kg',0.1,500,0.1],['height_cm',10,300,0.1],['systolic',40,300,1],['diastolic',20,200,1],['pulse',20,250,1],['respiratory_rate',5,100,1],['temperature_c',30,45,0.1],['oxygen_saturation',40,100,1]]){
  await run('VITAL-BOUND-'+field,'G34','Doctor','Simpan batas min/max dan tolak luar rentang '+field,'DRAFT / numeric bounds',async()=>{
   const f=p.locator('#vital_'+field),observed=[];
   for(const value of [min,max,min-step,max+step]){
    const rounded=Number(value.toFixed(1));await f.fill(String(rounded));
    if(rounded>=min&&rounded<=max){await saved(p);const row=sql.prepare('SELECT '+field+' value FROM vital_signs WHERE medical_record_id=?').get(limits.record);assert.equal(Number(row.value),rounded);observed.push({value:rounded,persisted:true});}
    else {await p.waitForFunction(id=>document.getElementById(id).getAttribute('aria-invalid')==='true','vital_'+field);assert.equal(Number(sql.prepare('SELECT '+field+' value FROM vital_signs WHERE medical_record_id=?').get(limits.record).value),max);observed.push({value:rounded,rejected:true});}
   }
   await f.fill(String(min));await saved(p);return {boundaries:observed};
  });
 }
 await run('DIAGNOSIS-20','G34','Doctor','Tambah20 diagnosis, batas tombol dan persistence','DRAFT / 20 structured diagnoses',async()=>{
  for(let i=0;i<20;i++){if(!await p.locator('#diagnosis_name_'+i).count())await p.getByRole('button',{name:'+ Tambah diagnosis',exact:true}).click();await p.locator('#diagnosis_name_'+i).fill('QA Diagnosis '+i);}
  await saved(p);assert.equal(sql.prepare('SELECT COUNT(*) n FROM diagnoses WHERE medical_record_id=?').get(limits.record).n,20);assert.equal(await p.getByRole('button',{name:'+ Tambah diagnosis',exact:true}).isDisabled(),true);return {persisted:20,add_disabled:true};
 });
 await run('TREATMENT-30','G34','Doctor','Tambah30 tindakan, batas tombol dan persistence','DRAFT / 30 structured treatments',async()=>{
  for(let i=0;i<30;i++){if(!await p.locator('#treatment_name_'+i).count())await p.getByRole('button',{name:'+ Tambah tindakan',exact:true}).click();await p.locator('#treatment_name_'+i).fill('QA Tindakan '+i);}
  await saved(p);assert.equal(sql.prepare('SELECT COUNT(*) n FROM treatments WHERE medical_record_id=?').get(limits.record).n,30);assert.equal(await p.getByRole('button',{name:'+ Tambah tindakan',exact:true}).isDisabled(),true);return {persisted:30,add_disabled:true};
 });
 const rx=fixture.exams.prescription;await openVisit(p,rx.visit);
 await run('PRESCRIPTION-ACK-DRAFT','G37','Doctor','Resep baru memerlukan acknowledgement alergi kritis','active CRITICAL allergy; new prescription / unchecked then checked',async()=>{
  await addRx(p,0);await p.waitForFunction(()=>/alergi kritis/i.test(document.querySelector('[data-rme-autosave-status]').innerText));assert.equal(sql.prepare('SELECT COUNT(*) n FROM prescriptions WHERE medical_record_id=?').get(rx.record).n,0);await ack(p);await saved(p);assert.equal(sql.prepare('SELECT COUNT(*) n FROM prescription_items i JOIN prescriptions r ON r.id=i.prescription_id WHERE r.medical_record_id=?').get(rx.record).n,1);return {unchecked_rejected:true,checked_saved:true};
 });
 await run('PRESCRIPTION-30-31','G38','Doctor','Tambah30 obat, tolak31, hapus agar dapat disimpan','DRAFT + acknowledged allergy; max30 prescription items',async()=>{
  for(let i=1;i<30;i++)await addRx(p,i,'QA Dummy Medicine '+i);await saved(p);assert.equal(sql.prepare('SELECT COUNT(*) n FROM prescription_items i JOIN prescriptions r ON r.id=i.prescription_id WHERE r.medical_record_id=?').get(rx.record).n,30);
  await addRx(p,30,'QA Medicine31');await p.waitForFunction(()=>/30/.test(document.querySelector('[data-rme-autosave-status]').innerText));assert.equal(sql.prepare('SELECT COUNT(*) n FROM prescription_items i JOIN prescriptions r ON r.id=i.prescription_id WHERE r.medical_record_id=?').get(rx.record).n,30);await p.getByRole('button',{name:'Hapus obat',exact:true}).last().click();await saved(p);return {max30_saved:true,item31_rejected:true,recovered_by_removal:true};
 });
 await run('PRESCRIPTION-QUANTITY','G38','Doctor','Jumlah9999 diterima;10000 ditolak; instruksi panjang tampil di preview','DRAFT / prescription quantity boundary and instructions2000',async()=>{
  await p.locator('#medicine_quantity_0').fill('9999');await p.locator('#medicine_instructions_0').fill('Instruksi dummy panjang '.repeat(80));await saved(p);assert.equal(Number(sql.prepare('SELECT i.quantity FROM prescription_items i JOIN prescriptions r ON r.id=i.prescription_id WHERE r.medical_record_id=? ORDER BY i.sort_order LIMIT 1').get(rx.record).quantity),9999);await p.locator('#medicine_quantity_0').fill('10000');await p.waitForFunction(()=>document.querySelector('#medicine_quantity_0').getAttribute('aria-invalid')==='true');await p.locator('#medicine_quantity_0').fill('1');await saved(p);assert.ok((await p.locator('main').innerText()).includes('Instruksi dummy panjang'));return {quantity9999:true,quantity10000_rejected:true,long_instructions_preview:true};
 });
 await run('PRESCRIPTION-EMPTY-FINAL','G38','Doctor','Hapus seluruh obat dan finalisasi tanpa resep','DRAFT with Rx30 → empty Rx → FINAL',async()=>{
  while(await p.getByRole('button',{name:'Hapus obat',exact:true}).count())await p.getByRole('button',{name:'Hapus obat',exact:true}).last().click();await saved(p);await p.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await p.getByRole('button',{name:'Finalisasi kunjungan',exact:true}).click();await p.locator('#correction_reason').waitFor({state:'visible'});assert.equal(sql.prepare('SELECT status FROM medical_records WHERE id=?').get(rx.record).status,'FINAL');assert.equal(sql.prepare('SELECT COUNT(*) n FROM prescription_items i JOIN prescriptions r ON r.id=i.prescription_id WHERE r.medical_record_id=?').get(rx.record).n,0);return {empty_prescription_finalized:true};
 });
 await run('PRESCRIPTION-ACK-CORRECTION','G37','Doctor','Koreksi final dengan obat baru tetap memerlukan review alergi','FINAL / new Rx in Modification / acknowledgement unchecked then checked',async()=>{
  await p.reload();await p.locator('#correction_reason').fill('Menambahkan resep dummy pada Modification untuk pengujian');await addRx(p,0,'QA Corrected Prescription');await p.getByRole('button',{name:'Simpan Modification',exact:true}).click();await p.waitForFunction(()=>/alergi kritis/i.test(document.querySelector('[data-rme-autosave-status]').innerText));await ack(p);await p.getByRole('button',{name:'Simpan Modification',exact:true}).click();await p.waitForFunction(()=>document.querySelector('#medicine_name_0')?.value==='QA Corrected Prescription');await p.waitForTimeout(300);assert.equal(sql.prepare('SELECT COUNT(*) n FROM medical_record_revisions WHERE medical_record_id=?').get(rx.record).n,1);return {unchecked_rejected:true,acknowledged_correction_saved:true};
 });
 await run('PATIENT-CORRECTED-PRESCRIPTION','G39','Patient','Resep yang dikoreksi tampil pada portal pasien','FINAL + corrected prescription',async()=>{
  const pc=await client('patient',t.patient);await pc.page.goto(base+'/patient/dashboard');assert.ok((await pc.page.locator('main').innerText()).includes('QA Corrected Prescription'));await pc.context.close();return {corrected_patient_prescription:true};
 });
 await run('AUTOSAVE-419-RECOVERY','G33','Doctor','Logout tab kedua memicu419; copy, login dan paste memulihkan draft','live editor + same-session logout / expired CSRF',async()=>{
  const d=await client('doctor-expired-session',t.doctor),page=d.page;await openVisit(page,fixture.exams.leave.visit);const logout=await d.context.newPage();await logout.goto(base+'/profile');await logout.getByRole('button',{name:'Keluar',exact:true}).click();await logout.waitForURL(/\/$/);const value='Draft dummy dipulihkan sesudah sesi berakhir';await page.locator('[name=subjective]').fill(value);await page.waitForFunction(()=>/Sesi berakhir/.test(document.querySelector('[data-rme-autosave-status]').innerText));assert.equal(await page.locator('[name=subjective]').inputValue(),value);await logout.goto(base+'/admin');await logout.locator('#email').fill(t.doctor);await logout.locator('#password').fill(credential);await wire(logout,logout.locator('form button[type=submit]'));await logout.waitForURL(/doctor\/dashboard/);page.removeAllListeners('dialog');page.on('dialog',d=>d.accept());await page.reload();await page.locator('[name=subjective]').fill(value);await saved(page);assert.equal(sql.prepare('SELECT subjective FROM medical_records WHERE id=?').get(fixture.exams.leave.record).subjective,value);await d.context.close();return {actual419:true,pending_text_retained:true,relogin_manual_copy_recovery:true};
 });
 const allergy=fixture.exams.allergy;await openVisit(p,allergy.visit);const allergyId=sql.prepare('SELECT id FROM patient_allergies WHERE patient_id=? ORDER BY id LIMIT 1').get(t.patient_id).id;
 for(const severity of ['MILD','MODERATE','SEVERE','CRITICAL'])await run('ALLERGY-SEVERITY-'+severity,'G36','Doctor','Ubah severity '+severity,'active longitudinal allergy',async()=>{
  await p.locator('details').filter({has:p.locator(`#allergy-${allergyId}-severity`)}).locator('summary').click();const form=p.locator(`form[action$="/allergies/${allergyId}"]`);await p.locator(`#allergy-${allergyId}-severity`).selectOption(severity);await form.getByRole('button',{name:'Perbarui alergi',exact:true}).click();await p.waitForLoadState('domcontentloaded');await p.waitForFunction(()=>!document.querySelector('[data-allergy-form][data-submitting="true"]'));assert.equal(sql.prepare('SELECT severity FROM patient_allergies WHERE id=?').get(allergyId).severity,severity);return {saved_severity:severity};
 });
 const stale=await c.context.newPage();await openVisit(stale,allergy.visit);
 await run('ALLERGY-TWO-EDITORS','G36','Doctor','Dua editor alergi mengubah reaksi; amati hasil dan stale RME guard','two browser tabs same allergy; no invented version policy',async()=>{
  await p.locator('details').filter({has:p.locator(`#allergy-${allergyId}-severity`)}).locator('summary').click();await p.locator(`#allergy-${allergyId}-reaction`).fill('Reaksi editor A');await p.locator(`form[action$="/allergies/${allergyId}"]`).getByRole('button',{name:'Perbarui alergi'}).click();await p.waitForLoadState('domcontentloaded');await p.waitForTimeout(250);
  await stale.locator('details').filter({has:stale.locator(`#allergy-${allergyId}-severity`)}).locator('summary').click();await stale.locator(`#allergy-${allergyId}-reaction`).fill('Reaksi editor B stale');await stale.locator(`form[action$="/allergies/${allergyId}"]`).getByRole('button',{name:'Perbarui alergi'}).click();await stale.waitForLoadState('domcontentloaded');await stale.waitForTimeout(300);const value=sql.prepare('SELECT reaction FROM patient_allergies WHERE id=?').get(allergyId).reaction;assert.ok(['Reaksi editor A','Reaksi editor B stale'].includes(value));return {saved_reaction:value,last_write_wins:value==='Reaksi editor B stale',product_note:'Allergy editor does not expose optimistic version; RME uses allergy_version separately.'};
 });await stale.close();
}finally{await finish('closure-clinical-boundaries');}
