import {client,browser,sql,base,evidence,shot} from '../../deep-functional-ux/scripts/common.mjs';
import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import assert from 'node:assert/strict';
const f=JSON.parse(readFileSync(join(evidence,'fixture.json'))),t=f.tenants.dental,rows=[];
try{
 const c=await client('platform','platform@clinicos.test'),p=c.page,errors=[];p.on('pageerror',error=>errors.push(error.message));
 await p.goto(base+`/platform/tenants/${t.clinic}`);await p.waitForTimeout(100);
 const raw=await p.getByRole('button',{name:'Berikan akses',exact:true}).getAttribute('x-bind:disabled');
 assert.ok(raw.includes('@js'));assert.ok(errors.some(x=>x.includes('Invalid or unexpected token')));
 await p.locator('#subscription_price_id').selectOption({index:1});await p.locator('#reason').fill('QA F1 before: blocked request, no commercial change');
 let observed,requests=0;p.on('console',msg=>{if(msg.text().startsWith('F1_STATE:'))observed=JSON.parse(msg.text().slice(9));});
 await p.evaluate(()=>document.querySelector('form[x-data]').addEventListener('submit',event=>{const form=event.target;setTimeout(()=>console.log('F1_STATE:'+JSON.stringify({submitting:Alpine.$data(form).submitting,disabled:form.querySelector('button[type=submit]').disabled})),0);}));
 await p.route('**/platform/tenants/*/subscription-grants',async route=>{requests++;await new Promise(r=>setTimeout(r,500));await route.abort();});
 const screenshot=await shot(p,'F1A-before');await p.getByRole('button',{name:'Berikan akses',exact:true}).click({noWaitAfter:true});await p.waitForTimeout(650);
 assert.deepEqual(observed,{submitting:true,disabled:false});
 rows.push({id:'PG-18-before',status:'FAIL',root_reproduced:true,errors,raw_binding:raw,observed,requests,request_blocked_before_backend:true,screenshot});await c.context.close();
 const rec=await client('rec',t.reception);await rec.page.goto(base+'/receptionist/patients/create');
 await rec.page.locator('#name').fill('QA F1 candidate review');await rec.page.locator('#birth_date').fill('1991-01-01');
 const ready=rec.page.waitForResponse(r=>r.url().endsWith('/patients/duplicates')&&JSON.parse(r.request().postData()||'{}').phone==='081299990000');
 await rec.page.locator('#phone').fill('081299990000');await (await ready).finished();await rec.page.waitForFunction(()=>document.querySelectorAll('[data-duplicate-review] a[href]').length===8);
 const existing=sql.prepare('SELECT COUNT(*) n FROM patients WHERE clinic_id=? AND phone=?').get(t.clinic,'081299990000').n;
 assert.ok(existing>=10);assert.equal(await rec.page.getByRole('button',{name:'Lihat kandidat lain'}).count(),0);
 rows.push({id:'PG-17-before',status:'FAIL',root_reproduced:true,visible:8,fixture_matches:existing,pagination_controls:0,screenshot:await shot(rec.page,'F1B-before')});await rec.context.close();
}finally{writeFileSync(join(evidence,'before.json'),JSON.stringify(rows,null,2));await browser.close();sql.close();}
assert.equal(rows.length,2);console.log(JSON.stringify(rows.map(({id,status,root_reproduced})=>({id,status,root_reproduced}))));
