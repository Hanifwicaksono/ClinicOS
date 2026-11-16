import {client,browser,sql,base,evidence,shot} from '../deep-functional-ux/scripts/common.mjs';
import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import assert from 'node:assert/strict';
const f=JSON.parse(readFileSync(join(evidence,'fixture.json'))),file=join(evidence,'task-review.json'),rows=JSON.parse(readFileSync(file));
async function observe(id,role,email,route,task){
 const row={id,role,task,started:new Date().toISOString()};let c;
 try{c=await client(role==='Guest'?'guest':role==='Patient'?'patient':role,email);const response=await c.page.goto(base+route);assert.equal(response.status(),200);await c.page.waitForFunction(()=>Boolean(window.Alpine));row.observed={route,status:response.status(),headings:await c.page.locator('h1,h2,h3').allTextContents(),text:await c.page.locator('main').innerText(),screenshot:await shot(c.page,id)};row.status='OBSERVED';}
 catch(error){row.status='NOT VERIFIED';row.reason=error.message.slice(0,400);}
 finally{await c?.context.close();rows.push(row);writeFileSync(file,JSON.stringify(rows,null,2));console.log(JSON.stringify({id,status:row.status}));}
}
try{
 const old=rows.find(x=>x.id==='UI-21');old.superseded_by='UI-21-R2';old.harness_note='Guest was passed as uppercase to a lowercase-only helper. Login timeout was a harness error; no product failure inferred.';
 await observe('UI-21-R2','Guest','',`/clinic/${f.tenants.dental.slug}`,'Informasi klinik dan titik mulai pengunjung, setelah koreksi identitas guest pada harness');
 await observe('UI-23','Clinic Owner',f.tenants.dental.owner,'/subscription/addons','Bedakan riwayat add-on owner yang tersedia dari timeline periode utama Platform yang belum tersedia');
 for(const [id,role,email,route]of [['UI-24','Clinic Owner',f.tenants.empty.owner,'/admin/dashboard'],['UI-25','Receptionist',f.tenants.empty.reception,'/receptionist/dashboard'],['UI-26','Doctor',f.tenants.empty.doctor,'/doctor/dashboard'],['UI-27','Patient',f.tenants.empty.patient,'/patient/dashboard']])await observe(id,role,email,route,'Tinjau empty activity dashboard pada tenant fixture kosong; bukan onboarding klinik tanpa konfigurasi');
 assert.equal(sql.prepare('SELECT is_active FROM patients WHERE id=?').get(f.tenants.dental.patient_id).is_active,1,'UI-22 must restore activation');
}finally{await browser.close();sql.close();}
