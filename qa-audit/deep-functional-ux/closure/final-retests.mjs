import {run,client,fixture,sql,base,assert,finish,wire,supersede,runtime} from './common.mjs';
import {readFileSync} from 'node:fs';
import {join} from 'node:path';
const t=fixture.tenants.prorata;
try {
 for(const [role,email,path] of [['Clinic Admin',t.owner,'/admin/dashboard'],['Receptionist',t.reception,'/receptionist/dashboard'],['Doctor',t.doctor,'/doctor/dashboard'],['Patient',t.patient,'/patient/dashboard'],['Platform Admin','platform@clinicos.test','/platform/dashboard']]) {
  const old='DASHBOARD-SHORTCUTS-'+role.replaceAll(' ','-'),id=old+'-R2';
  await run(id,'G47',role,'Klik semua tujuan shortcut dashboard berbeda','role dashboard; wait for actual navigation commit',async()=>{
   const c=await client(role==='Patient'?'patient':role,email),p=c.page;
   try {
    await p.goto(base+path);const links=await p.locator('main a[href]:visible').evaluateAll(nodes=>nodes.map(a=>({href:a.href,text:a.innerText.trim()})));
    const unique=[...new Map(links.filter(a=>new URL(a.href).origin===new URL(base).origin).map(a=>[a.href,a])).values()];assert.ok(unique.length>0);
    for(const link of unique){await p.goto(base+path);const expected=new URL(link.href);const anchor=p.locator('main a[href]:visible').filter({hasText:link.text}).first();await Promise.all([p.waitForURL(u=>u.pathname===expected.pathname&&[...expected.searchParams].every(([k,v])=>u.searchParams.get(k)===v)),anchor.click()]);await p.waitForLoadState('domcontentloaded');assert.ok(!(await p.locator('body').innerText()).includes('403 Forbidden'));}
    return {distinct_shortcut_targets:unique.map(a=>new URL(a.href).pathname+new URL(a.href).search),visible_links_inspected:links.length};
   }finally{await c.context.close();}
  });
  supersede(old,id,'Assertion lama membaca URL sebelum navigasi hasil klik selesai. Retest menunggu URL tujuan dan DOM selesai dimuat; tidak mengubah aplikasi.');
 }
 await run('PROFILE-TWO-EMAIL-SESSIONS','G08','Patient','Ubah email sesi A; sesi B memerlukan verifikasi lalu pulih','two authenticated browser contexts / same user ID / email changes',async()=>{
  const email=t.patient,newEmail='closure.profile.changed@clinicos.test',a=await client('patient',email),b=await client('patient',email),id=sql.prepare('SELECT id FROM users WHERE email=?').get(email).id;
  try{
   await a.page.goto(base+'/profile');await a.page.locator('#email').fill(newEmail);await wire(a.page,a.page.getByRole('button',{name:'Simpan profil',exact:true}));assert.equal(sql.prepare('SELECT email_verified_at FROM users WHERE id=?').get(id).email_verified_at,null);
   await b.page.goto(base+'/patient/dashboard');await b.page.waitForURL(/verify-email/);await a.page.goto(base+'/verify-email');await wire(a.page,a.page.getByRole('button',{name:'Kirim ulang email verifikasi',exact:true}));
   const mails=readFileSync(join(runtime.root,'mail.jsonl'),'utf8').trim().split('\n').map(JSON.parse).reverse(),mail=mails.find(x=>x.to.includes(newEmail));assert.ok(mail);const link=(mail.html||mail.text).match(/http:\/\/127\.0\.0\.1:\d+\/[^\s"<>]+/g).find(x=>x.includes('/verify-email/')).replaceAll('&amp;','&');await a.page.goto(link);await a.page.waitForURL(/patient\/dashboard/);await b.page.goto(base+'/patient/dashboard');assert.equal(new URL(b.page.url()).pathname,'/patient/dashboard');assert.ok(sql.prepare('SELECT email_verified_at FROM users WHERE id=?').get(id).email_verified_at);
   return {same_user_id:id,second_session_requires_verification:true,second_session_restored_after_valid_verification:true,policy:'Email change requires verification; existing authenticated sessions remain attached to same account.'};
  }finally{await a.context.close();await b.context.close();}
 });
}finally{await finish('closure-final-shortcut-and-two-session-verification');}
