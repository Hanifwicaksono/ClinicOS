import {run,client,fixture,sql,base,openVisit,saved,assert,shot,finish,setClock,clock,evidence} from './common.mjs';
import {writeFileSync} from 'node:fs';
import {join} from 'node:path';
const t=fixture.tenants.dental,c=await client('closure-dentist',t.doctor),p=c.page,visit=fixture.dental.visit,record=fixture.dental.record;
const teeth=[...Array.from({length:8},(_,i)=>18-i),...Array.from({length:8},(_,i)=>21+i),...Array.from({length:8},(_,i)=>48-i),...Array.from({length:8},(_,i)=>31+i)];
const conditions=[['unknown','Belum dicatat'],['healthy','Sehat'],['caries','Karies'],['filled','Tambalan'],['missing','Hilang'],['crown','Mahkota']];
const cells=[];
try {
 await openVisit(p,visit);
 assert.equal(await p.locator('[data-dental-chart]').getAttribute('data-read-only'),'false','Fixture must pass entitlement guard');
 for(const [value,label] of conditions){
  await run('DENT-32-'+value,'G44','Doctor (dentist)','Pilih kondisi '+label+' pada seluruh 32 gigi lalu reload','paid parent + paid addon active / DRAFT',async()=>{
   for(const id of teeth){await p.locator(`[data-tooth-id="${id}"]`).click();await p.getByRole('button',{name:label,exact:true}).click();}
   await saved(p);const row=sql.prepare('SELECT odontogram FROM medical_records WHERE id=?').get(record);const data=JSON.parse(row.odontogram);assert.equal(data.length,32);
   await p.reload();await p.waitForFunction(()=>Boolean(document.querySelector('[data-rme-form]')?.rmeAutosave));
   for(const id of teeth){assert.equal(data.find(x=>x.tooth_number===id)?.condition,value);assert.ok((await p.locator(`[data-tooth-id="${id}"]`).getAttribute('aria-label')).includes(label));cells.push({feature:'F21',role:'Doctor',action:'select/save/reload tooth '+id,state:'DRAFT + entitlement active + '+value,status:'PASS'});}
   return {teeth:32,condition:value,persisted_after_reload:true,screenshot:await shot(p,'DENT-'+value)};
  });
 }
 await run('DENT-FINAL-CORRECTION','G44','Doctor (dentist)','Finalisasi dental, koreksi seluruh gigi, buka histori','DRAFT → FINAL → Modification; paid addon active',async()=>{
  await p.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await p.getByRole('button',{name:'Finalisasi kunjungan',exact:true}).click();await p.locator('#correction_reason').waitFor({state:'visible'});
  assert.equal(sql.prepare('SELECT status FROM medical_records WHERE id=?').get(record).status,'FINAL');
  await p.locator('#correction_reason').fill('Koreksi seluruh kondisi gigi dummy untuk pengujian histori dental');
  for(const id of teeth){await p.locator(`[data-tooth-id="${id}"]`).click();await p.getByRole('button',{name:'Sehat',exact:true}).click();}
  await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}),p.getByRole('button',{name:'Simpan Modification',exact:true}).click()]);
  const row=sql.prepare('SELECT status,odontogram FROM medical_records WHERE id=?').get(record);assert.equal(row.status,'FINAL');assert.ok(JSON.parse(row.odontogram).every(x=>x.condition==='healthy'));
  const revision=sql.prepare('SELECT before_data,after_data FROM medical_record_revisions WHERE medical_record_id=? ORDER BY id DESC LIMIT 1').get(record);assert.ok(revision);assert.equal(JSON.parse(revision.before_data).odontogram.length,32);assert.equal(JSON.parse(revision.after_data).odontogram.length,32);return {final_preserved:true,correction_history_32:true,screenshot:await shot(p,'DENT-final-correction')};
 });
 await run('DENT-HISTORY-MOBILE','G43','Doctor (dentist)','Buka dan baca perbedaan array 32 gigi pada mobile','FINAL + correction history32 / viewport375',async()=>{
  await p.setViewportSize({width:375,height:950});await p.getByText('Lihat semua perubahan',{exact:true}).click();await p.waitForTimeout(250);const text=await p.locator('main').innerText();assert.ok(/odontogram|gigi/i.test(text));const widths=await p.evaluate(()=>({viewport:innerWidth,document:document.documentElement.scrollWidth}));assert.ok(widths.document<=widths.viewport+1);return {widths,screenshot:await shot(p,'DENT-history-mobile')};
 });
 const expiry=fixture.expiry,ec=await client('closure-expiry-doctor',fixture.tenants.expiry.doctor),ep=ec.page;
 await run('DENT-EXPIRES-ENDS-AT','G45','Doctor','Edit stale dental setelah ends_at lewat, reload read-only, simpan RME General','active09:00 → expired09:31; revoked_at NULL',async()=>{
  const original=clock();try{
   await openVisit(ep,expiry.visit);assert.equal(await ep.locator('[data-dental-chart]').getAttribute('data-read-only'),'false');await ep.locator('[data-tooth-id="11"]').click();await ep.getByRole('button',{name:'Karies',exact:true}).click();await saved(ep);
   setClock(fixture.today+'T02:31:00Z');await ep.locator('[data-tooth-id="11"]').click();await ep.getByRole('button',{name:'Tambalan',exact:true}).click();await ep.waitForFunction(()=>/Gagal disimpan|Perlu ditinjau/.test(document.querySelector('[data-rme-autosave-status]').innerText));
   const data=JSON.parse(sql.prepare('SELECT odontogram FROM medical_records WHERE id=?').get(expiry.record).odontogram);assert.equal(data.find(x=>x.tooth_number===11).condition,'caries');assert.equal(sql.prepare('SELECT revoked_at FROM addon_periods WHERE id=?').get(fixture.tenants.expiry.addon).revoked_at,null);
   ep.removeAllListeners('dialog');ep.on('dialog',d=>d.accept());await ep.reload();assert.equal(await ep.locator('[data-dental-chart]').getAttribute('data-read-only'),'true');assert.equal(await ep.locator('[data-tooth-id="11"]').isEnabled(),false);
   await ep.locator('[name=assessment]').fill('General tetap dapat disimpan sesudah ends_at add-on');await saved(ep);assert.equal(sql.prepare('SELECT assessment FROM medical_records WHERE id=?').get(expiry.record).assessment,'General tetap dapat disimpan sesudah ends_at add-on');return {expired_by_time:true,revoked_at:null,dental_unchanged:true,general_in_progress_saved:true,screenshot:await shot(ep,'DENT-expired-by-time')};
  }finally{setClock(original);await ec.context.close();}
 });
}finally{writeFileSync(join(evidence,'dental-cells.json'),JSON.stringify(cells,null,2));await finish('closure-dental');}
