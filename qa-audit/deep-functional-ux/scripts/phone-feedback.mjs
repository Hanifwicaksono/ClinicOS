import assert from 'node:assert/strict';
import {writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {base,client,check,sql,shot,finish,evidence} from './common.mjs';
const c=await client('receptionist');const p=c.page;
try{
    await check('UX-05','F09','Receptionist','Telepon terlalu panjang memberi petunjuk field, bukan error pemeriksaan kandidat generik',['Pasien baru','Isi identitas valid dan telepon31 digit','Klik Simpan','Periksa respons duplicate review dan pesan UI'],async()=>{
        await p.goto(base+'/receptionist/patients/create');await p.locator('#name').fill('QA Invalid Phone Length');await p.locator('#birth_date').fill('1990-01-01');await p.locator('#gender').selectOption('MALE');await p.locator('#phone').fill('1'.repeat(31));
        const [response]=await Promise.all([p.waitForResponse(r=>r.url().endsWith('/receptionist/patients/duplicates')&&r.request().method()==='POST'),p.getByRole('button',{name:'Simpan pasien'}).click()]);const payload=await response.json();await p.locator('[data-duplicate-review] [role=alert]').filter({hasText:'Kandidat pasien belum dapat diperiksa'}).waitFor({state:'visible'});
        const message=await p.locator('[data-duplicate-review] [role=alert]').innerText();const result={http:response.status(),error_fields:Object.keys(payload.errors||{}),ui_message:message,patient_created:sql.prepare("SELECT COUNT(*) n FROM patients WHERE name='QA Invalid Phone Length'").get().n>0};writeFileSync(join(evidence,'patient-phone-validation-feedback.json'),JSON.stringify(result,null,2));await shot(p,'UX-05-patient-phone-generic-error');assert.equal(result.http,422);assert.ok(result.error_fields.includes('phone'));assert.equal(result.patient_created,false);assert.ok(/phone|telepon|30/.test(message),'Detail error phone max30 disembunyikan oleh pesan gagal pemeriksaan kandidat generik');return result;
    });
}finally{await finish('15-phone-feedback-evidence');}
