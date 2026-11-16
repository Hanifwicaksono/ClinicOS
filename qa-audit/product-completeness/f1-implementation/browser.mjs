import {client,browser,sql,base,evidence,shot,check,results} from '../../deep-functional-ux/scripts/common.mjs';
import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import assert from 'node:assert/strict';
const f=JSON.parse(readFileSync(join(evidence,'fixture.json'))),t=f.tenants.dental;
const grantPath=`/platform/tenants/${t.clinic}`,catalog='/platform/subscription-plans';
async function run(id,role,scenario,body){if(process.env.F1_ONLY&&!id.startsWith(process.env.F1_ONLY))return;await check('after-'+id,id.slice(0,5),role,scenario,['Aksi UI pada fixture disposable; DB hanya verifikasi','Periksa outcome, negative state dan integritas'],body);}
async function submit(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
async function grantForm(page){await page.goto(base+grantPath);await page.waitForFunction(()=>Boolean(window.Alpine));return page.locator('form[x-data]');}
async function fillGrant(page,reason){const price=sql.prepare("SELECT p.id FROM subscription_prices p JOIN subscription_plans x ON x.id=p.subscription_plan_id WHERE x.code='team' AND p.duration_months=1").get();await page.locator('#subscription_price_id').selectOption(String(price.id));await page.locator('#reason').fill(reason);}
const familyPhone='081299990000';
const family=()=>sql.prepare('SELECT * FROM patients WHERE clinic_id=? AND phone=? ORDER BY id').all(t.clinic,familyPhone);
async function reviewForm(page,email=t.reception){
 await page.goto(base+'/receptionist/patients/create');
 await page.locator('#name').fill('QA F1 different person draft');await page.locator('#birth_date').fill('1991-01-01');await page.locator('#gender').selectOption('FEMALE');await page.locator('#address').fill('QA F1 input must remain');await page.locator('#email').fill('f1.draft@example.test');
 const ready=page.waitForResponse(r=>r.url().endsWith('/patients/duplicates')&&JSON.parse(r.request().postData()||'{}').phone===familyPhone);
 await page.locator('#phone').fill(familyPhone);await (await ready).finished();await page.waitForFunction(()=>{const el=document.querySelector('[data-duplicate-review]');const s=el&&Alpine.$data(el);return s&&!s.busy&&s.candidates.length===8&&s.nextCursor;});
}
async function more(page){await page.getByRole('button',{name:'Lihat kandidat lain',exact:true}).focus();await page.keyboard.press('Enter');}
async function changedPatient(page,id,change){await page.goto(base+`/receptionist/patients/${id}/edit`);await change(page);await submit(page,page.getByRole('button',{name:'Simpan pasien',exact:true}));}
async function choose(page,name){const input=page.locator('#'+name+'_search');await input.click();await input.locator('..').locator('[x-ref=results] button:visible').first().click();}
try{
 await run('PG18-validation','Platform Admin','Native/server validation permits retry and retains UUID',async()=>{
  const c=await client('platform','platform@clinicos.test'),p=c.page,errors=[];p.on('pageerror',e=>errors.push(e.message));
  try{let form=await grantForm(p);const uuid=await form.locator('[name=grant_request_id]').inputValue(),count=sql.prepare('SELECT COUNT(*) n FROM subscription_periods').get().n;
   assert.equal(await p.locator('[x-bind\\:disabled]').getAttribute('x-bind:disabled'),'submitting || catalogEmpty');
   await p.getByRole('button',{name:'Berikan akses',exact:true}).click();assert.equal(await form.locator('button[type=submit]').isDisabled(),false);
   assert.equal(await p.locator('#subscription_price_id').evaluate(x=>x.validity.valid),false);
   await fillGrant(p,'   ');await submit(p,p.getByRole('button',{name:'Berikan akses',exact:true}));form=p.locator('form[x-data]');
   assert.equal(await form.locator('[name=grant_request_id]').inputValue(),uuid);assert.equal(await form.locator('button[type=submit]').isDisabled(),false);
   assert.ok((await p.locator('#reason').locator('..').innerText()).toLowerCase().includes('required'));
   assert.equal(sql.prepare('SELECT COUNT(*) n FROM subscription_periods').get().n,count);assert.deepEqual(errors,[]);
   return {uuid_retained:true,native_validation:true,server_validation:true,retry_enabled:true,periods_unchanged:true,page_errors:errors,screenshot:await shot(p,'PG18-validation')};
  }finally{await c.context.close();}
 });
 await run('PG18-pending','Platform Admin','Real double-click shows pending, creates one audited scheduled grant and preserves history',async()=>{
  const c=await client('platform','platform@clinicos.test'),p=c.page,errors=[];p.on('pageerror',e=>errors.push(e.message));
  try{await grantForm(p);await fillGrant(p,'QA F1 pending grant, one UI action');
   const uuid=await p.locator('[name=grant_request_id]').inputValue(),history=sql.prepare('SELECT * FROM subscription_periods WHERE clinic_id=? ORDER BY id').all(t.clinic),payments=sql.prepare('SELECT COUNT(*) n FROM payments').get().n;
   let state,posts=0;p.on('console',msg=>{if(msg.text().startsWith('F1_STATE:'))state=JSON.parse(msg.text().slice(9));});
   await p.evaluate(()=>document.querySelector('form[x-data]').addEventListener('submit',event=>{const form=event.target;setTimeout(()=>console.log('F1_STATE:'+JSON.stringify({submitting:Alpine.$data(form).submitting,disabled:form.querySelector('button[type=submit]').disabled,text:form.querySelector('button[type=submit]').innerText.trim()})),0);}));
   await p.route('**/platform/tenants/*/subscription-grants',async route=>{posts++;await new Promise(r=>setTimeout(r,1000));await route.continue();});
   const nav=p.waitForNavigation({waitUntil:'domcontentloaded'});await p.getByRole('button',{name:'Berikan akses',exact:true}).dblclick({noWaitAfter:true});
   for(let i=0;i<10&&!state;i++)await p.waitForTimeout(30);
   assert.deepEqual(state,{submitting:true,disabled:true,text:'Memproses…'});
   const cdp=await c.context.newCDPSession(p),capture=await cdp.send('Page.captureScreenshot',{format:'png'});writeFileSync(join(evidence,'screenshots/PG18-pending.png'),Buffer.from(capture.data,'base64'));await cdp.detach();
   await nav;const grant=sql.prepare('SELECT * FROM subscription_periods WHERE grant_request_id=?').all(uuid);assert.equal(grant.length,1);assert.equal(posts,1);
   const audit=sql.prepare("SELECT COUNT(*) n FROM audit_logs WHERE entity_id=? AND action='subscription.granted'").get(grant[0].id).n;assert.equal(audit,1);
   const after=sql.prepare('SELECT * FROM subscription_periods WHERE clinic_id=? AND id!=? ORDER BY id').all(t.clinic,grant[0].id);assert.deepEqual(after,history);assert.equal(sql.prepare('SELECT COUNT(*) n FROM payments').get().n,payments);assert.deepEqual(errors,[]);
   return {pending:state,post_requests:posts,grant_rows:1,audit_rows:audit,history_unchanged:true,payments_unchanged:true,page_errors:errors,screenshot:'screenshots/PG18-pending.png'};
  }finally{await c.context.close();}
 });
 await run('PG18-empty','Platform Admin','Empty catalog remains disabled after Alpine initialization',async()=>{
  const c=await client('platform','platform@clinicos.test'),p=c.page,plans=sql.prepare('SELECT id,is_active FROM subscription_plans').all();
  const toggle=async id=>{await p.goto(base+catalog);await submit(p,p.locator(`form[action$="/subscription-plans/${id}"] button`));};
  try{for(const x of plans.filter(x=>x.is_active))await toggle(x.id);const form=await grantForm(p);assert.equal(await form.locator('button[type=submit]').isDisabled(),true);assert.equal(await p.locator('#subscription_price_id option').count(),1);return {disabled:true,screenshot:await shot(p,'PG18-empty')};}
  finally{for(const x of plans.filter(x=>x.is_active))await toggle(x.id);assert.deepEqual(sql.prepare('SELECT id,is_active FROM subscription_plans').all(),plans);await c.context.close();}
 });
 await run('PG18-back','Platform Admin','Back navigation after simulated HTTP failure resets pending and permits retry',async()=>{
  const c=await client('platform','platform@clinicos.test'),p=c.page;
  try{await grantForm(p);await fillGrant(p,'QA F1 simulated transport failure');const count=sql.prepare('SELECT COUNT(*) n FROM subscription_periods').get().n;
   await p.route('**/platform/tenants/*/subscription-grants',route=>route.fulfill({status:500,contentType:'text/html',body:'<!doctype html><title>QA simulated failure</title><p>QA transport failure</p>'}));
   await submit(p,p.getByRole('button',{name:'Berikan akses',exact:true}));assert.equal(await p.title(),'QA simulated failure');await p.goBack();await p.waitForFunction(()=>Boolean(window.Alpine));assert.equal(await p.locator('form[x-data] button[type=submit]').isDisabled(),false);assert.equal(sql.prepare('SELECT COUNT(*) n FROM subscription_periods').get().n,count);
   return {simulated_http500:true,back_retry_enabled:true,no_backend_write:true,screenshot:await shot(p,'PG18-back')};
  }finally{await c.context.close();}
 });
 await run('PG17-more-booking','Receptionist','Page 2 preserves draft and cursor, reaches candidates9/10 and books an existing profile',async()=>{
  const c=await client('rec',t.reception),p=c.page;
  try{const before=sql.prepare('SELECT COUNT(*) n FROM patients').get().n,rows=family();assert.equal(rows.length,10);await reviewForm(p);
   const values=await p.locator('form').last().evaluate(el=>Object.fromEntries([...new FormData(el)].filter(([k])=>!['_token','duplicate_review_token'].includes(k))));
   await p.locator('[name=duplicate_reviewed]').check();await p.locator('#phone').evaluate(el=>el.setSelectionRange(3,6));await more(p);
   await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).candidates.length===10);
   assert.equal(await p.locator('[name=duplicate_reviewed]').isChecked(),false);assert.equal(await p.getByRole('button',{name:'Lihat kandidat lain',exact:true}).isVisible(),false);
   assert.equal(await p.locator('#phone').evaluate(el=>el.selectionStart),3);
   const actual=await p.locator('[data-duplicate-review] a[href]').evaluateAll(els=>els.map(x=>Number(new URL(x.href).pathname.split('/').pop())));assert.deepEqual(actual,rows.map(x=>x.id));
   const current=await p.locator('form').last().evaluate(el=>Object.fromEntries([...new FormData(el)].filter(([k])=>!['_token','duplicate_review_token','duplicate_reviewed'].includes(k))));delete values.duplicate_reviewed;assert.deepEqual(current,values);
   const viewports=[];for(const width of [375,768,1024,1440]){await p.setViewportSize({width,height:1000});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);viewports.push(width);}
   const screenshot=await shot(p,'PG17-more');
   const popup=c.context.waitForEvent('page');await p.locator('[data-duplicate-review] a[href]').nth(8).click();const profile=await popup;await profile.waitForLoadState('domcontentloaded');assert.equal(new URL(profile.url()).pathname,`/receptionist/patients/${rows[8].id}`);
   await profile.getByRole('link',{name:'Buat booking',exact:true}).click();await profile.waitForURL('**/receptionist/appointments/create?**');await profile.waitForFunction(()=>Boolean(window.Alpine));
   assert.equal(await profile.locator('[name=patient_id]').inputValue(),String(rows[8].id));await choose(profile,'doctor_id');await profile.locator('#appointment_date').fill(f.today);await choose(profile,'service_id');await choose(profile,'doctor_schedule_id');
   await submit(profile,profile.getByRole('button',{name:'Buat booking',exact:true}));
   const appointment=sql.prepare('SELECT patient_id FROM appointments ORDER BY id DESC LIMIT 1').get();assert.equal(appointment.patient_id,rows[8].id);assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients').get().n,before);assert.equal(await p.locator('#address').inputValue(),'QA F1 input must remain');
   return {visible_ids_in_order:true,visible:10,selection_preserved:true,input_preserved:true,keyboard_pagination:true,existing_profile_booked:true,no_new_patient:true,viewports,screenshot};
  }finally{await c.context.close();}
 });
 await run('PG17-inactive','Receptionist','An inactive later candidate opens its profile without activation, linking or data loss',async()=>{
  const c=await client('rec',t.reception),p=c.page,row=family()[9];
  try{await changedPatient(p,row.id,async page=>page.locator('[name=is_active][type=checkbox]').uncheck());await reviewForm(p);await more(p);await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).candidates.length===10);
   const link=p.locator('[data-duplicate-review] a[href]').nth(9);assert.ok((await link.innerText()).includes('Nonaktif'));
   const pending=c.context.waitForEvent('page');await link.click();const profile=await pending;await profile.waitForLoadState('domcontentloaded');assert.ok((await profile.locator('main').innerText()).includes('Pasien nonaktif'));assert.equal(await profile.getByRole('link',{name:'Buat booking',exact:true}).count(),0);
   const actual=sql.prepare('SELECT is_active,user_id FROM patients WHERE id=?').get(row.id);assert.equal(actual.is_active,0);assert.equal(actual.user_id,row.user_id);assert.equal(await p.locator('#address').inputValue(),'QA F1 input must remain');return {inactive_remains_inactive:true,no_auto_link:true,draft_preserved:true,screenshot:await shot(p,'PG17-inactive')};
  }finally{await changedPatient(p,row.id,async page=>{const box=page.locator('[name=is_active][type=checkbox]');if(row.is_active)await box.check();else await box.uncheck();});await c.context.close();}
 });
 await run('PG17-forged','Receptionist','A forged cursor receives real422, clears review authority and permits fresh retry',async()=>{
  const c=await client('rec',t.reception),p=c.page;
  try{await reviewForm(p);await p.locator('[name=duplicate_reviewed]').check();const before=sql.prepare('SELECT COUNT(*) n FROM patients').get().n;
   await p.route('**/patients/duplicates',async route=>{const data=JSON.parse(route.request().postData()||'{}');await route.continue(data.cursor?{postData:JSON.stringify({...data,cursor:'forged-cursor'})}:{});});
   const reply=p.waitForResponse(r=>r.url().endsWith('/patients/duplicates')&&r.status()===422);await more(p);const response=await reply;assert.ok((await response.json()).errors.cursor);await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).message.includes('Periksa kembali'));
   assert.equal(await p.locator('[name=duplicate_review_token]').inputValue(),'');assert.equal(await p.locator('[name=duplicate_reviewed]').isChecked(),false);assert.equal(await p.locator('#address').inputValue(),'QA F1 input must remain');
   await p.getByRole('button',{name:'Periksa kembali',exact:true}).click();await p.waitForFunction(()=>{const s=Alpine.$data(document.querySelector('[data-duplicate-review]'));return !s.busy&&s.candidates.length===8&&s.nextCursor&&!s.message;});assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients').get().n,before);
   return {real_http_status:422,cursor_rejected:true,token_cleared:true,fresh_retry:true,no_patient_write:true,screenshot:await shot(p,'PG17-forged')};
  }finally{await c.context.close();}
 });
 await run('PG17-stale','Receptionist','Editing a later candidate in another browser invalidates the old cursor',async()=>{
  const a=await client('rec',t.reception),b=await client('rec',t.reception),p=a.page,row=family()[9];
  try{await reviewForm(p);await changedPatient(b.page,row.id,async page=>page.locator('#name').fill(row.name+' F1 changed'));
   const reply=p.waitForResponse(r=>r.url().endsWith('/patients/duplicates')&&r.status()===422);await more(p);assert.ok((await (await reply).json()).errors.cursor);await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).message.includes('Periksa kembali'));
   assert.equal(await p.locator('#name').inputValue(),'QA F1 different person draft');await p.getByRole('button',{name:'Periksa kembali',exact:true}).click();await p.waitForFunction(()=>{const s=Alpine.$data(document.querySelector('[data-duplicate-review]'));return !s.busy&&s.nextCursor&&!s.message;});await more(p);await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).candidates.length===10);assert.ok((await p.locator('[data-duplicate-review] a[href]').nth(9).innerText()).includes('F1 changed'));
   return {real_http_status:422,later_record_edit_invalidated_cursor:true,retry_uses_current_snapshot:true,input_preserved:true,screenshot:await shot(p,'PG17-stale')};
  }finally{await changedPatient(b.page,row.id,async page=>page.locator('#name').fill(row.name));await a.context.close();await b.context.close();}
 });
 await run('PG17-foreign','Receptionist','A valid cursor cannot be replayed by another tenant or overridden by clinic_id',async()=>{
  const a=await client('rec',t.reception),b=await client('rec',f.tenants.prorata.reception);
  try{await reviewForm(a.page);const cursor=await a.page.evaluate(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).nextCursor);const p=b.page;
   await p.route('**/patients/duplicates',route=>{const data=JSON.parse(route.request().postData()||'{}');return route.continue({postData:JSON.stringify({...data,cursor,clinic_id:t.clinic})});});
   await p.goto(base+'/receptionist/patients/create');const reply=p.waitForResponse(r=>r.url().endsWith('/patients/duplicates')&&r.status()===422);await p.locator('#phone').fill(familyPhone);assert.ok((await (await reply).json()).errors.cursor);await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).message.includes('Periksa kembali'));assert.equal(await p.locator('[data-duplicate-review] a[href]').count(),0);
   return {real_http_status:422,foreign_cursor_rejected:true,clinic_id_override_ignored:true,no_candidates_leaked:true,screenshot:await shot(p,'PG17-foreign')};
  }finally{await a.context.close();await b.context.close();}
 });
 await run('PG17-roles','All stored roles','Admin can review; Doctor, Patient and Platform cannot use receptionist candidate UI',async()=>{
  const rows=[];
  for(const [role,email,path,allowed]of [['Clinic Admin',t.owner,'/receptionist/patients/create',true],['Doctor',t.doctor,'/receptionist/patients/create',false],['Patient',t.patient,'/receptionist/patients/create',false],['Platform Admin','platform@clinicos.test','/receptionist/patients/create',false]]){
   const c=await client(role==='Patient'?'patient':role,email);try{const response=await c.page.goto(base+path);assert.equal(response.status(),allowed?200:403);if(allowed){await reviewForm(c.page);await more(c.page);await c.page.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).candidates.length===10);}rows.push({role,status:response.status()});}finally{await c.context.close();}
  }
  return {roles:rows};
 });
 await run('PG17-walk-in','Receptionist','Paging in the shared walk-in form preserves patient, doctor, service and schedule inputs without creating records',async()=>{
  const c=await client('rec',t.reception),p=c.page;
  try{const patientCount=sql.prepare('SELECT COUNT(*) n FROM patients').get().n,appointments=sql.prepare('SELECT COUNT(*) n FROM appointments').get().n,count=family().length;
   await p.goto(base+'/receptionist/appointments/create');await p.waitForFunction(()=>Boolean(window.Alpine));await p.getByRole('button',{name:'Pasien baru',exact:true}).click();
   await p.locator('#new_patient_name').fill('QA F1 walk-in unsaved');await p.locator('#new_patient_birth_date').fill('1995-02-02');await p.locator('#new_patient_address').fill('Walk-in input preserved');await p.locator('#notes').fill('QA F1 schedule remains selected');await choose(p,'doctor_id');await p.locator('#appointment_date').fill(f.today);await choose(p,'service_id');await choose(p,'doctor_schedule_id');
   const ready=p.waitForResponse(r=>r.url().endsWith('/patients/duplicates')&&JSON.parse(r.request().postData()||'{}').phone===familyPhone);await p.locator('#new_patient_phone').fill(familyPhone);await (await ready).finished();await p.waitForFunction(()=>{const s=Alpine.$data(document.querySelector('[data-duplicate-review]'));return !s.busy&&s.candidates.length===8&&s.nextCursor;});
   const snapshot=()=>p.locator('main form').evaluate(el=>Object.fromEntries([...new FormData(el)].filter(([k])=>!['_token','idempotency_key','duplicate_review_token','duplicate_reviewed'].includes(k))));const before=await snapshot();await more(p);await p.waitForFunction(expected=>Alpine.$data(document.querySelector('[data-duplicate-review]')).candidates.length===expected,count);assert.deepEqual(await snapshot(),before);
   await p.setViewportSize({width:375,height:1000});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients').get().n,patientCount);assert.equal(sql.prepare('SELECT COUNT(*) n FROM appointments').get().n,appointments);
   return {visible:count,patient_inputs_preserved:true,doctor_service_schedule_preserved:true,no_record_created:true,mobile375_no_overflow:true,screenshot:await shot(p,'PG17-walk-in')};
  }finally{await c.context.close();}
 });
 await run('PG17-review-token','Receptionist','Unreviewed and forged review tokens cannot silently create a patient; explicit review can create a distinct person',async()=>{
  const c=await client('rec',t.reception),p=c.page;
  try{await reviewForm(p);const before=sql.prepare('SELECT COUNT(*) n FROM patients').get().n;let stores=0;p.on('request',req=>{if(req.method()==='POST'&&new URL(req.url()).pathname==='/receptionist/patients')stores++;});
   await p.getByRole('button',{name:'Simpan pasien',exact:true}).click();await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).message.includes('Tinjau kandidat'));assert.equal(stores,0);assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients').get().n,before);
   await p.locator('[name=duplicate_reviewed]').check();let tamper=true;await p.route('**/receptionist/patients',async route=>{if(route.request().method()!=='POST'||!tamper)return route.continue();const data=new URLSearchParams(route.request().postData());data.set('duplicate_review_token','f'.repeat(64));tamper=false;await route.continue({postData:data.toString()});});
   await submit(p,p.getByRole('button',{name:'Simpan pasien',exact:true}));assert.ok((await p.locator('main').innerText()).includes('Ada kandidat pasien terdaftar'));assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients').get().n,before);assert.equal(await p.locator('#address').inputValue(),'QA F1 input must remain');
   await p.waitForFunction(()=>{const s=Alpine.$data(document.querySelector('[data-duplicate-review]'));return !s.busy&&s.candidates.length===8;});await more(p);await p.waitForFunction(()=>Alpine.$data(document.querySelector('[data-duplicate-review]')).candidates.length===10);await p.locator('[name=duplicate_reviewed]').check();await submit(p,p.getByRole('button',{name:'Simpan pasien',exact:true}));
   assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients').get().n,before+1);const created=sql.prepare('SELECT name,user_id FROM patients ORDER BY id DESC LIMIT 1').get();assert.equal(created.name,'QA F1 different person draft');assert.equal(created.user_id,null);
   return {unreviewed_submit_blocked:true,forged_review_rejected:true,inputs_preserved:true,only_explicit_distinct_profile_created:true,no_auto_link:true,screenshot:await shot(p,'PG17-reviewed-create')};
  }finally{await c.context.close();}
 });
}finally{await browser.close();sql.close();}
const active=results.filter(x=>!x.superseded_by);console.log(JSON.stringify({pass:active.filter(x=>x.status==='PASS').length,fail:active.filter(x=>x.status==='FAIL').length}));if(active.some(x=>x.status==='FAIL'))process.exitCode=1;
