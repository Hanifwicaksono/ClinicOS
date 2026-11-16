import {run,client,fixture,sql,base,assert,finish,formSubmit,evidence,supersede} from './common.mjs';
import {writeFileSync} from 'node:fs';import {join} from 'node:path';
try{
 await run('AUDIT-ALL-SAFE-METADATA-R3','G51','Clinic Admin','Periksa semua key metadata whitelist dari event UI nyata','each event viewed by its own clinic owner; read-only database selection',async()=>{
  const keys=['from','to','source','appointment_id','queue_id','visit_id','revision_id'],tenants=Object.values(fixture.tenants),rows=sql.prepare('SELECT clinic_id,action,metadata FROM audit_logs ORDER BY id DESC').all().filter(x=>tenants.some(t=>t.clinic===x.clinic_id)),proof=[];
  for(const key of keys){const row=rows.find(x=>Object.hasOwn(JSON.parse(x.metadata||'{}'),key));assert.ok(row,'Tidak ada event '+key);const expected=String(JSON.parse(row.metadata)[key]),t=tenants.find(t=>t.clinic===row.clinic_id),c=await client('audit-owner',t.owner),a=c.page;
   try{await a.goto(base+'/admin/audit-logs');await a.locator('#audit_from').fill('2026-11-01');await a.locator('#audit_to').fill('2026-11-30');await a.locator('#audit_action').selectOption(row.action);await formSubmit(a,a.getByRole('button',{name:'Terapkan filter',exact:true}));let found=false;for(let page=0;page<15;page++){if((await a.locator('tbody').innerText()).includes(key+': '+expected)){found=true;break;}const next=a.locator('a[rel=next]:visible').first();if(!await next.count())break;await Promise.all([a.waitForNavigation({waitUntil:'domcontentloaded'}),next.click()]);}assert.ok(found,'Metadata '+key+' tidak terlihat');proof.push({key,value:expected,action:row.action,clinic:row.clinic_id});}finally{await c.context.close();}
  }writeFileSync(join(evidence,'audit-safe-metadata.json'),JSON.stringify(proof,null,2));return {all_whitelisted_keys:keys,extra_fields_hidden_reference:'AUDIT-METADATA'};
 });supersede('AUDIT-ALL-SAFE-METADATA-R2','AUDIT-ALL-SAFE-METADATA-R3','Pagination Laravel memuat dua link rel=next untuk desktop/mobile. Retest memilih link visible pertama; seluruh7 key kemudian benar-benar terlihat. Tidak mengubah source atau metadata database.');
}finally{await finish('closure-audit-metadata-whitelist');}

