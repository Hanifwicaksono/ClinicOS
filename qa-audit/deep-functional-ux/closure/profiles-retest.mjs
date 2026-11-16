import {run,client,fixture,sql,base,wire,assert,finish,supersede} from './common.mjs';
try{
for(const [role,email] of Object.entries(fixture.empty_notification_accounts)){
 const c=await client(role==='Patient'?'patient':role,email),page=c.page;
 for(const variant of ['duplicate-email','name256']){
  const old='PROFILE-'+role.replaceAll(' ','-')+'-'+variant,id=old+'-R2';
  await run(id,'G07',role,'Tolak perubahan profil '+variant,'verified active; duplicate email / max255 name',async()=>{
   await page.goto(base+'/profile');const form=page.locator('form[wire\\:submit="updateProfileInformation"]');const before=sql.prepare('SELECT name,email FROM users WHERE email=?').get(email);
   if(variant==='duplicate-email')await form.locator('#email').fill('patient@clinicos.test');else await form.locator('#name').fill('N'.repeat(256));
   await wire(page,form.getByRole('button',{name:'Simpan profil',exact:true}));assert.match(await form.innerText(),variant==='duplicate-email'?/taken|digunakan|terdaftar/i:/255/);assert.deepEqual({...sql.prepare('SELECT name,email FROM users WHERE email=?').get(email)},{...before});return {validation_visible:true,no_write:true};
  });supersede(old,id,'Harness awal mencari tombol Simpan; label aktual Simpan profil. Submit awal belum dieksekusi.');
 }
 await c.context.close();
}
}finally{await finish('closure-profiles-corrected-selector');}
