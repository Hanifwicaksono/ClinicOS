import {run,client,fixture,base,assert,finish,openVisit,saved,supersede,evidence} from './common.mjs';
import {readFileSync} from 'node:fs';import {join} from 'node:path';
try{
 for(const kind of ['draft','pending-photo'])for(const action of ['refresh','back']){
  const old='LEAVE-'+kind+'-'+action;
  await run(old+'-R3','G35','Doctor','Batalkan navigasi browser '+action,'new context per case / '+kind+' before debounce / browser navigation API',async()=>{
   const c=await client('leave-doctor',fixture.tenants.dental.doctor),p=c.page,v=fixture.exams.leave;
   try{await p.goto(base+'/doctor/medical-records');await openVisit(p,v.visit);if(kind==='pending-photo')await p.locator('#photos').setInputFiles({name:'pending.png',mimeType:'image/png',buffer:readFileSync(join(evidence,'upload-dummy.png'))});await p.locator('[name=objective]').fill('Dummy unsaved '+kind+' '+action+' R2');assert.equal(await p.locator('[data-rme-form]').evaluate(f=>f.rmeAutosave.unsaved),true);let dialogs=0;p.removeAllListeners('dialog');p.on('dialog',d=>{dialogs++;d.dismiss();});
    try{if(action==='refresh')await p.reload({waitUntil:'domcontentloaded',timeout:1500});else await p.goBack({waitUntil:'domcontentloaded',timeout:1500});}catch(error){assert.match(error.message,/ERR_ABORTED|Navigation|Timeout/);assert.ok(dialogs>0,'Timeout hanya sah jika dialog beforeunload benar-benar terjadi dan dibatalkan');}
    assert.ok(dialogs>0);assert.equal(new URL(p.url()).pathname,`/doctor/visits/${v.visit}`);assert.equal(await p.locator('[name=objective]').inputValue(),'Dummy unsaved '+kind+' '+action+' R2');if(kind==='pending-photo'){assert.equal(await p.locator('#photos').evaluate(e=>e.files.length),1);await p.getByRole('button',{name:'Hapus foto yang dipilih',exact:true}).click();}await saved(p);return {native_beforeunload_dialog_dismissed:true,editor_retained:true,photo_retained:kind==='pending-photo'};
   }finally{await c.context.close();}
  });supersede(old+'-R2',old+'-R3','Dialog beforeunload dibatalkan dengan benar sehingga navigasi tidak commit dan Playwright berakhir Timeout. Retest menerima pembatalan hanya bila dialog nyata teramati, URL/editor/foto tetap utuh, dan penyimpanan berikutnya berhasil.');
 }
}finally{await finish('closure-native-navigation-boundaries');}
