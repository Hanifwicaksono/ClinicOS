import assert from 'node:assert/strict';
import {writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {base,client,check,sql,shot,inspect,finish,state,saveState,evidence} from './common.mjs';
const c=await client('doctor');const p=c.page;const s=state();
// This is a synthetic entitlement fixture, not evidence of successful DOKU purchase.
const period=sql.prepare('SELECT * FROM subscription_periods WHERE clinic_id=1 LIMIT 1').get();
sql.prepare("UPDATE subscription_periods SET source='payment' WHERE id=?").run(period.id);
sql.prepare("INSERT INTO addon_periods(clinic_id,subscription_period_id,payment_id,code,amount,starts_at,ends_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)")
    .run(1,period.id,period.payment_id,'odontogram',99000,period.starts_at,period.ends_at,period.starts_at,period.starts_at);
const addon=sql.prepare('SELECT id FROM addon_periods WHERE payment_id=?').get(period.payment_id);
const visit=sql.prepare("SELECT v.id,v.patient_id,p.user_id,u.email,m.id record_id FROM visits v JOIN doctors d ON d.id=v.doctor_id JOIN users du ON du.id=d.user_id JOIN patients p ON p.id=v.patient_id LEFT JOIN users u ON u.id=p.user_id JOIN medical_records m ON m.visit_id=v.id WHERE v.status='IN_PROGRESS' AND du.email='doctor@clinicos.test' ORDER BY p.user_id IS NULL,v.id LIMIT 1").get();assert.ok(visit);
writeFileSync(join(evidence,'entitlement-fixture.json'),JSON.stringify({synthetic:true,clinic_id:1,subscription_period_id:period.id,addon_period_id:addon.id,provider_checkout_executed:false,visit_id:visit.id},null,2));
async function open(){await p.goto(base+`/doctor/visits/${visit.id}`);await p.waitForFunction(()=>Boolean(document.querySelector('[data-rme-form]')?.rmeAutosave));}
async function saved(){await p.waitForFunction(()=>!document.querySelector('[data-rme-form]').rmeAutosave.unsaved);}
try{
    await check('DENT-01','F21','Doctor','Odontogram entitlement aktif: pilih kondisi, autosave, reload',['Buka visit draft dokter','Pilih Gigi11','Klik Karies','Tunggu autosave','Reload'],async()=>{
        await open();assert.equal(await p.locator('[data-dental-chart]').getAttribute('data-read-only'),'false');if(await p.locator('#critical_allergies_acknowledged').count())await p.locator('#critical_allergies_acknowledged').check();
        await p.locator('[data-tooth-id="11"]').click();await p.getByRole('button',{name:'Karies',exact:true}).click();await saved();
        const data=JSON.parse(sql.prepare('SELECT odontogram FROM medical_records WHERE id=?').get(visit.record_id).odontogram);assert.equal(data.find(t=>t.tooth_number===11).condition,'caries');await p.reload();assert.ok((await p.locator('[data-tooth-id="11"]').getAttribute('aria-label')).includes('Karies'));
        await inspect(p,'doctor-dental','Odontogram aktif');return {tooth:11,persisted_condition:'caries',screenshot:await shot(p,'DENT-01-persisted')};
    });
    await check('DENT-02','F21','Doctor','Expiry add-on ketika editor terbuka: simpan ditolak, reload mempertahankan chart read-only',['Cabut entitlement dummy','Edit kondisi dari tab lama','Tunggu error','Reload','Periksa kondisi tersimpan dan disabled'],async()=>{
        sql.prepare("UPDATE addon_periods SET revoked_at=datetime('now') WHERE id=?").run(addon.id);await p.locator('[data-tooth-id="11"]').click();await p.getByRole('button',{name:'Tambalan',exact:true}).click();
        await p.waitForFunction(()=>document.querySelector('[data-rme-autosave-status]').innerText.match(/Gagal disimpan|Perlu ditinjau/));
        const data=JSON.parse(sql.prepare('SELECT odontogram FROM medical_records WHERE id=?').get(visit.record_id).odontogram);assert.equal(data.find(t=>t.tooth_number===11).condition,'caries');p.removeAllListeners('dialog');p.on('dialog',d=>d.accept());await p.reload();assert.equal(await p.locator('[data-dental-chart]').getAttribute('data-read-only'),'true');assert.equal(await p.locator('[data-tooth-id="11"]').isEnabled(),false);await shot(p,'DENT-02-revoked-read-only');return {stale_edit_rejected:true,history_preserved:true,readonly:true};
    });
    await check('DENT-03','F16','Doctor','RME General tetap dapat disimpan setelah add-on berakhir',['Odontogram read-only','Edit SOAP umum','Tunggu autosave','Periksa tersimpan'],async()=>{
        if(await p.locator('#critical_allergies_acknowledged').count())await p.locator('#critical_allergies_acknowledged').check();await p.locator('[name=assessment]').fill('Assessment general setelah add-on berakhir');await saved();assert.equal(sql.prepare('SELECT assessment FROM medical_records WHERE id=?').get(visit.record_id).assessment,'Assessment general setelah add-on berakhir');return {general_saved:true};
    });
    await check('CROSS-12','F20','Doctor','Visit yang dibatalkan pasien tidak membuat dokter terjebak finalisasi',['Buka draft visit yang queue-nya dibatalkan','Lengkapi SOAP','Tinjau dan finalisasi','Periksa hasil dan pesan'],async()=>{
        const actor=await client('deep-doctor','deep.doctor@clinicos.test');const dp=actor.page;await dp.goto(base+`/doctor/visits/${s.earlyVisitId}`);await dp.locator('[name=subjective]').fill('Subjektif sintetis untuk pemeriksaan dampak pembatalan');await dp.locator('[name=assessment]').fill('Assessment sintetis dampak pembatalan');await dp.locator('[name=plan]').fill('Rencana sintetis dampak pembatalan');await dp.waitForFunction(()=>!document.querySelector('[data-rme-form]').rmeAutosave.unsaved);await dp.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await dp.getByRole('button',{name:'Finalisasi kunjungan',exact:true}).click();await dp.waitForTimeout(500);await shot(dp,'CROSS-12-finalization-stuck');
        const status=sql.prepare('SELECT status FROM visits WHERE id=?').get(s.earlyVisitId).status;const feedback=await dp.locator('[data-rme-autosave-status]').innerText();writeFileSync(join(evidence,'cancelled-visit-finalize.json'),JSON.stringify({visit_id:s.earlyVisitId,status,feedback},null,2));assert.equal(status,'COMPLETED','Visit tetap IN_PROGRESS; finalisasi ditolak karena antrean sudah CANCELLED');await actor.context.close();return {completed:true};
    });
    saveState({dentalVisitId:visit.id});
}finally{sql.prepare('UPDATE addon_periods SET revoked_at=NULL WHERE id=?').run(addon.id);await finish('9-dental-entitlement-and-cancel-impact');}
