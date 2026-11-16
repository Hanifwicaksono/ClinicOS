import {run,client,fixture,base,assert,finish,supersede} from './common.mjs';
try{
 for(const [role,email,path] of [['Clinic Admin',fixture.tenants.prorata.owner,'/admin/dashboard']]){
  const id='DASHBOARD-SHORTCUTS-'+role.replaceAll(' ','-');
  await run(id+'-R4','G47',role,'Klik semua href shortcut dashboard berbeda','exact href; documented clinic index redirect; DOMContentLoaded awaited',async()=>{
   const c=await client(role,email),p=c.page;const targets=[];
   try{await p.goto(base+path);const links=await p.locator('main a[href]:visible').evaluateAll(nodes=>nodes.map(a=>({href:a.href,raw:a.getAttribute('href')})));const unique=[...new Map(links.filter(a=>new URL(a.href).origin===new URL(base).origin).map(a=>[a.href,a])).values()];assert.ok(unique.length);
    for(const link of unique){await p.goto(base+path);const url=new URL(link.href),expectedPath=url.pathname==='/admin/clinic'?`/admin/clinic/${fixture.tenants.prorata.clinic}/edit`:url.pathname,anchor=p.locator(`main a[href=${JSON.stringify(link.raw)}]:visible`).first();await Promise.all([p.waitForURL(u=>u.pathname===expectedPath&&[...url.searchParams].every(([k,v])=>u.searchParams.get(k)===v),{waitUntil:'domcontentloaded'}),anchor.click()]);assert.ok(!(await p.locator('body').innerText()).includes('403 Forbidden'));targets.push(new URL(p.url()).pathname+new URL(p.url()).search);}
    return {distinct_shortcut_targets:targets};
   }catch(error){throw new Error(error.message+'; current path='+new URL(p.url()).pathname+'; completed='+targets.join(','));}finally{await c.context.close();}
  });supersede(id+'-R3',id+'-R4','ClinicController.index sengaja redirect ke form edit klinik saat klinik sudah ada. Expected URL diselaraskan dengan kontrak source, bukan menganggap redirect sah sebagai bug.');
 }
}finally{await finish('closure-shortcut-href-sync');}
