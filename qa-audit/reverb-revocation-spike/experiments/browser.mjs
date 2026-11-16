import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,existsSync,unlinkSync} from 'node:fs';
import {join,resolve} from 'node:path';
import {execFileSync} from 'node:child_process';
import {pathToFileURL} from 'node:url';
import {DatabaseSync} from 'node:sqlite';
const evidence=resolve(import.meta.dirname,'../evidence');
const runtime=JSON.parse(readFileSync(join(evidence,'runtime.json')));
const fixture=JSON.parse(readFileSync(join(evidence,'fixture.json')));
const env=JSON.parse(readFileSync(join(runtime.root,'env.json')));
const credential=readFileSync(join(runtime.repo,'database/seeders/DemoUserSeeder.php'),'utf8').match(/Hash::make\('([^']+)'\)/)[1];
const {chromium}=await import(pathToFileURL(runtime.playwright).href);
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const sql=new DatabaseSync(runtime.database);
const php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];
const control=command=>JSON.parse(execFileSync('php',[...php,join(import.meta.dirname,'control.php'),command],{cwd:runtime.repo,env,encoding:'utf8'}));
const mode=name=>writeFileSync(join(runtime.root,'mode.txt'),name);
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const results=[];let queueIndex=Number(process.env.SPIKE_QUEUE_START||0);let targetA,targetB,rec,patient,foreign;
async function until(fn,message,timeout=12000){const start=Date.now();while(Date.now()-start<timeout){if(await fn())return;await sleep(100);}throw new Error(message);}
async function login(role,email){
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();page.setDefaultTimeout(10000);
 const client={context,page,role,frames:[],auth:[],protocol:[]};page.on('dialog',dialog=>dialog.dismiss());
 page.on('websocket',ws=>{ws.on('framereceived',({payload})=>{try{const frame=JSON.parse(payload);let data=typeof frame.data==='string'?JSON.parse(frame.data):frame.data;if(frame.event?.startsWith('pusher')){client.protocol.push({event:frame.event,channel:frame.channel,code:data?.code,at:Date.now()/1000,...(frame.event==='pusher:spike_ack'?{ack:data}:{})});return;}if(!frame.channel)return;client.frames.push({at:Date.now()/1000,channel:frame.channel,event:frame.event,queue_id:data?.queue_id??null,appointment_id:data?.appointment_id??null,notification_type:data?.type??null,keys:Object.keys(data||{}).sort()});}catch{}});});
 page.on('response',response=>{if(new URL(response.url()).pathname==='/broadcasting/auth')client.auth.push({at:Date.now()/1000,status:response.status()});});
 await page.goto(runtime.base+(role==='patient'?'/login':'/admin'));await page.waitForFunction(()=>Boolean(window.Livewire));
 await page.locator('#email').fill(email);await page.locator('#password').fill(credential);await page.locator('form button[type=submit]').click();
 await page.waitForURL(url=>!['/admin','/login'].includes(url.pathname));await page.waitForFunction(()=>Boolean(window.Echo));
 return client;
}
async function request(client,path,body={}){return client.page.evaluate(async({path,body})=>{const response=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(body)});let json;try{json=await response.json();}catch{}return {status:response.status,auth:Boolean(json?.auth),data:json};},{path,body});}
async function subscribe(client,channels,expect=true){
 await client.page.evaluate(channels=>{for(const channel of channels){window.Echo.connector.pusher.subscribe(channel).bind_global(()=>{});}},channels);
 if(expect)await until(()=>client.page.evaluate(channels=>channels.every(name=>window.Echo.connector.pusher.channel(name)?.subscribed),channels),'Private subscription acknowledgement missing');
}
const clinic='private-clinic.6.queues';const own='private-users.'+fixture.target;
async function freshTargets(currentMode){
 for(const c of [targetA,targetB])if(c)await c.context.close();
 mode(currentMode);control('restore');
 for(const c of [rec,patient])if(c){await c.page.reload();await c.page.waitForFunction(()=>Boolean(window.Echo));}
 if(rec)await subscribe(rec,[clinic,'clinic.6.queue-board']);if(patient)await subscribe(patient,[`private-users.${fixture.patient}`]);
 targetA=await login('doctor','closure.dental.doctor@clinicos.test');targetB=await login('doctor','closure.dental.doctor@clinicos.test');
 for(const c of [targetA,targetB])await subscribe(c,[clinic,own]);
}
async function emit(label){
 const id=fixture.queues[queueIndex++];assert.ok(id,'Fixture queue exhausted');
 const response=await request(rec,`/queues/${id}/check-in`);assert.equal(response.status,200,`Check-in ${label} HTTP ${response.status}`);
 await until(()=>rec.frames.some(f=>f.queue_id===id&&f.channel===clinic),'Authorized same-tenant control did not receive fresh event '+id);
 await until(()=>patient.frames.some(f=>f.queue_id===id&&f.channel===`private-users.${fixture.patient}`),'Patient notification control did not receive fresh event '+id);
 await sleep(350);
 assert.equal(foreign.frames.filter(f=>f.queue_id===id&&f.channel.startsWith('private-')&&(f.channel===clinic||f.channel===own)).length,0,'Foreign private delivery');
 const views=Object.fromEntries([['targetA',targetA],['targetB',targetB],['control',rec],['patient',patient],['foreign',foreign]].map(([key,c])=>[key,c.frames.filter(f=>f.queue_id===id)]));
 for(const frames of Object.values(views))for(const f of frames)if(f.channel.endsWith('.queues')||f.channel.endsWith('.queue-board'))assert.deepEqual(f.keys,['display_number','doctor_id','queue_date','queue_id','status','updated_at']);
 return {queue_id:id,published_after:Date.now()/1000,views};
}
const privateCount=(emission,name)=>emission.views[name].filter(f=>f.channel===clinic||f.channel===own).length;
async function freshAuth(client,channel=clinic){const socket=await client.page.evaluate(()=>window.Echo.socketId());return request(client,'/broadcasting/auth',{socket_id:socket,channel_name:channel});}
async function test(id,title,body){if(process.env.SPIKE_ONLY&&!process.env.SPIKE_ONLY.split(',').includes(id))return;const record={id,title,started:new Date().toISOString()};try{record.observed=await body();record.status='PASS';}catch(error){record.status='FAIL';record.error=String(error.message).slice(0,900);console.log(error.stack?.split('\n').slice(0,3).join('\n'));}results.push(record);writeFileSync(join(evidence,'results'+(process.env.SPIKE_RUN_SUFFIX||'')+'.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({id,status:record.status,title}));}

try{
 mode('baseline');control('restore');
 rec=await login('reception','closure.dental.reception@clinicos.test');patient=await login('patient','closure.dental.patient@clinicos.test');foreign=await login('foreign','closure.prorata.reception@clinicos.test');
 await subscribe(rec,[clinic,'clinic.6.queue-board']);await subscribe(patient,[`private-users.${fixture.patient}`]);
 if(process.env.SPIKE_EXTENDED==='1'){
  const {run}=await import('./extended-cases.mjs');
  await run({runtime,fixture,evidence,env,browser,sql,control,mode,sleep,until,login,subscribe,request,freshTargets,emit,privateCount,freshAuth,test,clients:()=>({targetA,targetB,rec,patient,foreign}),nextQueue:()=>fixture.queues[queueIndex++]});
 }else{
 await test('S1-01','Foreign tenant and foreign user channel authorization',async()=>{const a=await freshAuth(foreign,clinic),b=await freshAuth(foreign,own);assert.equal(a.status,403);assert.equal(b.status,403);return {clinic_auth:a.status,user_auth:b.status};});
 for(const change of ['role','permission','inactive','password'])await test('S1-B-'+change,'Baseline old sockets after '+change,async()=>{
  await freshTargets('baseline');const before=await emit('before-'+change);assert.ok(privateCount(before,'targetA')>=2);assert.ok(privateCount(before,'targetB')>=2);
  const mutation=control(change);const auth=await freshAuth(targetA);assert.equal(auth.auth,false);const after=await emit('after-'+change);
  for(const name of ['targetA','targetB']){assert.ok(privateCount(after,name)>=2);assert.ok(after.views[name].filter(f=>f.channel.startsWith('private-')).every(f=>f.at>mutation.barrier));}
  return {security_result:'FAIL',mutation,new_clinic_auth:auth.status,before,after};
 });
 await test('S1-02','Native termination endpoint without signed user identity',async()=>{await freshTargets('baseline');control('role');const termination=control('terminate');assert.equal(termination.accepted,true);const after=await emit('unidentified-terminate');assert.ok(privateCount(after,'targetA')>=2);assert.ok(privateCount(after,'targetB')>=2);return {security_result:'FAIL',termination,after};});
 await test('S1-03','Signed identity terminates old sockets; native user-channel reconnect remains authorized',async()=>{await freshTargets('signed');const old=await Promise.all([targetA,targetB].map(c=>c.page.evaluate(()=>window.Echo.socketId())));control('role');const termination=control('terminate');assert.equal(termination.accepted,true);await until(async()=>{const ids=await Promise.all([targetA,targetB].map(c=>c.page.evaluate(()=>window.Echo.socketId())));return ids.every((id,i)=>id&&id!==old[i]);},'Both terminated sockets must reconnect with different IDs');const after=await emit('identified-terminate');for(const name of ['targetA','targetB']){assert.ok(!after.views[name].some(f=>f.channel===clinic));assert.ok(after.views[name].some(f=>f.channel===own));}return {security_result:'FAIL as standalone revocation',both_old_sockets_terminated:true,termination,after};});
 if(process.env.SPIKE_SMOKE!=='1'){
  await test('S1-04','Subscribe-time reauthorization alone leaves old subscriptions live',async()=>{await freshTargets('subscribe');const mutation=control('role');const after=await emit('subscribe-only');assert.ok(privateCount(after,'targetA')>=2);return {security_result:'FAIL',mutation,after};});
  await test('S1-05','Producer recipient check cannot protect shared clinic channel',async()=>{await freshTargets('producer');control('inactive');const after=await emit('producer-only');assert.ok(after.views.targetA.some(f=>f.channel===clinic));assert.ok(!after.views.targetA.some(f=>f.channel===own));return {security_result:'FAIL',after};});
  await test('S1-06','MessageSent disconnect occurs after the first private frame',async()=>{await freshTargets('postsend');control('role');const after=await emit('postsend');assert.ok(privateCount(after,'targetA')>=1);return {security_result:'FAIL',after};});
  await test('S1-07','Derived authority fingerprint permits old binding after remove/restore',async()=>{await freshTargets('fingerprint');control('role');control('restore');const after=await emit('fingerprint-ABA');assert.ok(privateCount(after,'targetA')>=2);return {security_result:'FAIL for monotonic epoch invalidation',after};});
  for(const change of ['role','permission','direct','inactive','password'])await test('S1-G-'+change,'Before-send guard, both tabs, after '+change,async()=>{
   await freshTargets('guard');if(change==='direct'){control('direct-setup');await targetA.page.reload();await targetB.page.reload();for(const c of [targetA,targetB])await subscribe(c,[clinic,own]);}
   const before=await emit('guard-before-'+change);assert.ok(privateCount(before,'targetA')>=2);
   const mutation=control(change);const termination=control('terminate-failure');assert.equal(termination.accepted,false);
   const auth=await freshAuth(targetA);assert.equal(auth.auth,false);const after=await emit('guard-after-'+change);
   assert.equal(privateCount(after,'targetA'),0);assert.equal(privateCount(after,'targetB'),0);
   return {security_result:'PASS',mutation,termination_failure:termination,new_clinic_auth:auth.status,before,after};
  });
  await test('S1-08','Version invalidates old binding even after privilege is restored',async()=>{await freshTargets('guard');const oldEpoch=control('role').epoch;const restored=control('restore');const after=await emit('epoch-ABA');assert.equal(privateCount(after,'targetA'),0);assert.notEqual(oldEpoch,restored.epoch);await targetA.page.reload();await subscribe(targetA,[clinic,own]);const recovered=await emit('epoch-fresh-auth');assert.ok(privateCount(recovered,'targetA')>=2);return {old_epoch:oldEpoch,restored_epoch:restored.epoch,after,recovered};});
  await test('S1-09','Rollback preserves authorization and socket delivery',async()=>{await freshTargets('guard');const rollback=control('rollback');assert.equal(rollback.version_unchanged,true);const after=await emit('rollback');assert.ok(privateCount(after,'targetA')>=2);return {rollback,after};});
  await test('S1-10','Legacy unsigned private subscriptions are denied by guard',async()=>{await freshTargets('baseline');mode('guard');for(const c of [rec,patient]){await c.page.reload();await c.page.waitForFunction(()=>Boolean(window.Echo));}await subscribe(rec,[clinic,'clinic.6.queue-board']);await subscribe(patient,[`private-users.${fixture.patient}`]);const after=await emit('legacy-unsigned');assert.equal(privateCount(after,'targetA'),0);assert.equal(privateCount(after,'targetB'),0);return {after};});
 }
 }
}finally{
 writeFileSync(join(evidence,'checkpoint'+(process.env.SPIKE_RUN_SUFFIX||'')+'.json'),JSON.stringify({at:new Date().toISOString(),browser:browser.version(),queue_index:queueIndex,counts:{PASS:results.filter(r=>r.status==='PASS').length,FAIL:results.filter(r=>r.status==='FAIL').length},failed_jobs:sql.prepare('select count(*) n from failed_jobs').get().n},null,2));
 await browser.close();sql.close();
}
