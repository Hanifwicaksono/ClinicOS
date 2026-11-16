import {client,browser,sql,base,evidence,shot} from '../deep-functional-ux/scripts/common.mjs';
import {writeFileSync,readFileSync} from 'node:fs';
import {join} from 'node:path';
const f=JSON.parse(readFileSync(join(evidence,'fixture.json'))),records=[];
try{
 const c=await client('platform','platform@clinicos.test');
 c.page.on('pageerror',error=>records.push({kind:'pageerror',message:error.message,stack:error.stack}));
 c.page.on('console',msg=>{if(msg.type()==='error'||msg.type()==='warning')records.push({kind:'console',message:msg.text().slice(0,500),location:msg.location()});});
 for(let attempt=1;attempt<=2;attempt++){
   const response=await c.page.goto(base+`/platform/tenants/${f.tenants.dental.clinic}`);await c.page.waitForTimeout(500);
   records.push({attempt,status:response.status(),title:await c.page.title(),bindings:await c.page.locator('[x-data],[x-bind\\:disabled]').evaluateAll(els=>els.map(x=>({tag:x.tagName,data:x.getAttribute('x-data'),disabled:x.getAttribute('x-bind:disabled')}))),body_ready:await c.page.locator('h1').innerText()});
 }
 let intercepted=0,pendingState;
 c.page.on('console',msg=>{if(msg.text().startsWith('QA_GRANT_STATE:'))pendingState=JSON.parse(msg.text().slice('QA_GRANT_STATE:'.length));});
 await c.page.evaluate(()=>document.querySelector('form[x-data]').addEventListener('submit',event=>{const form=event.target;setTimeout(()=>console.log('QA_GRANT_STATE:'+JSON.stringify({submitting:window.Alpine.$data(form).submitting,button_disabled:form.querySelector('button[type=submit]').disabled})),0);}));
 await c.page.route('**/platform/tenants/*/subscription-grants',async route=>{intercepted++;await new Promise(resolve=>setTimeout(resolve,500));await route.abort();});
 await c.page.locator('#subscription_price_id').selectOption({index:1});
 await c.page.locator('#reason').fill('QA product audit: pending button binding only, request blocked before backend');
 const button=c.page.getByRole('button',{name:'Berikan akses',exact:true});
 await shot(c.page,'UI-28');
 await button.click({noWaitAfter:true});await c.page.waitForTimeout(650);
 const pending={intercepted_requests:intercepted,...pendingState,backend_request_blocked:true};
 records.push({pending_ui:pending,screenshot:'screenshots/UI-28.png',screenshot_note:'Captured immediately before submit; submit state proven by event observer JSON, not network settlement.'});
 const tasksFile=join(evidence,'task-review.json'),tasks=JSON.parse(readFileSync(tasksFile));
 if(!tasks.some(x=>x.id==='UI-28'))tasks.push({id:'UI-28',role:'Platform Admin',task:'Triage ekspresi Alpine dan feedback tombol grant saat submit pending; request dicegat sebelum backend',status:'OBSERVED',observed:{...pending,screenshot:'screenshots/UI-28.png'}});
 writeFileSync(tasksFile,JSON.stringify(tasks,null,2));
 await c.context.close();writeFileSync(join(evidence,'page-error-triage.json'),JSON.stringify(records,null,2));console.log(JSON.stringify(pending));
}finally{await browser.close();sql.close();}
