import {run,client,fixture,sql,base,wire,assert,shot,finish,formSubmit,selectSearch} from './common.mjs';
const t=fixture.tenants.dental;
const owner=await client('closure-owner',t.owner),reception=await client('closure-rec',t.reception),p=owner.page,r=reception.page;
try {
 for(const [role,email] of Object.entries(fixture.empty_notification_accounts)){
  const c=await client(role==='Patient'?'patient':role,email),page=c.page;
  for(const variant of ['duplicate-email','name256'])await run('PROFILE-'+role.replaceAll(' ','-')+'-'+variant,'G07',role,'Tolak perubahan profil '+variant,'verified active; email duplicate / name max255',async()=>{
   await page.goto(base+'/profile');const form=page.locator('form[wire\\:submit="updateProfileInformation"]');const before=sql.prepare('SELECT name,email FROM users WHERE email=?').get(email);
   if(variant==='duplicate-email')await form.locator('#email').fill('patient@clinicos.test');else await form.locator('#name').fill('N'.repeat(256));
   await wire(page,form.getByRole('button',{name:'Simpan profil',exact:true}));const body=await form.innerText();assert.match(body,variant==='duplicate-email'?/taken|digunakan|terdaftar/i:/255/);const after=sql.prepare('SELECT name,email FROM users WHERE email=?').get(email);assert.deepEqual({...after},{...before});return {validation_visible:true,no_write:true};
  });
  await c.context.close();
 }
 for(const value of ['123.45','123456'])await run('SERVICE-PRICE-'+value.replace('.','_'),'G15','Clinic Admin','Simpan tarif '+value,'numeric within server range; fractional/nonmultiple1000',async()=>{
  await p.goto(base+`/admin/services/${t.service_id}/edit`);await p.locator('#price').fill(value);const validity=await p.locator('#price').evaluate(e=>({valid:e.checkValidity(),step:e.step,stepMismatch:e.validity.stepMismatch}));await p.getByRole('button',{name:'Simpan perubahan',exact:true}).click();await p.waitForTimeout(150);await shot(p,'SERVICE-PRICE-'+value);assert.equal(validity.valid,true,'Tarif valid menurut domain diblokir input step='+validity.step);assert.equal(Number(sql.prepare('SELECT price FROM services WHERE id=?').get(t.service_id).price),Number(value));return validity;
 });
 await run('SERVICE-MAX','G15','Clinic Admin','Tolak nominal melebihi 9999999999.99','numeric overflow boundary',async()=>{
  await p.goto(base+`/admin/services/${t.service_id}/edit`);const before=sql.prepare('SELECT price FROM services WHERE id=?').get(t.service_id).price;await p.locator('#price').fill('10000000000');await formSubmit(p,p.getByRole('button',{name:'Simpan perubahan',exact:true}));assert.match(await p.getByRole('alert').innerText(),/9999999999/);assert.equal(sql.prepare('SELECT price FROM services WHERE id=?').get(t.service_id).price,before);return {server_boundary_rejected:true};
 });
 for(const [kind,value] of [['quota','0'],['quota','501'],['end_time','11:00'],['start_time','07:00']])await run('SCHEDULE-BOUND-'+kind+'-'+value.replace(':',''),'G18','Clinic Admin','Tolak jadwal '+kind+'='+value,'new session; min/max/order/outside clinic hours',async()=>{
  await p.goto(base+'/admin/doctor-schedules/create');await p.locator('#doctor_id').selectOption(String(t.doctor_id));await p.locator('#day_of_week').selectOption('1');await p.locator('#start_time').fill('12:00');await p.locator('#end_time').fill('23:59');await p.locator('#quota').fill('10');await p.locator('#'+kind).fill(value);const before=sql.prepare('SELECT COUNT(*) n FROM doctor_schedules WHERE doctor_id=?').get(t.doctor_id).n;const valid=await p.locator('#'+kind).evaluate(e=>e.checkValidity());
  if(!valid){await p.getByRole('button',{name:'Tambah jadwal',exact:true}).click();assert.equal(kind,'quota');}else{await formSubmit(p,p.getByRole('button',{name:'Tambah jadwal',exact:true}));assert.ok((await p.getByRole('alert').innerText()).length>0);}
  assert.equal(sql.prepare('SELECT COUNT(*) n FROM doctor_schedules WHERE doctor_id=?').get(t.doctor_id).n,before);return {native_or_server_rejected:true};
 });
 for(const [field,value] of [['start_time','12:30'],['end_time','23:30']])await run('USED-SESSION-'+field,'G17','Clinic Admin','Ubah '+field+' sesi terpakai','appointments and queues reference schedule',async()=>{
  await p.goto(base+`/admin/doctor-schedules/${t.today_schedule}/edit`);const before=sql.prepare('SELECT start_time,end_time FROM doctor_schedules WHERE id=?').get(t.today_schedule);await p.locator('#'+field).fill(value);await formSubmit(p,p.getByRole('button',{name:'Simpan perubahan',exact:true}));assert.match(await p.getByRole('alert').innerText(),/sudah memiliki booking/);assert.deepEqual({...sql.prepare('SELECT start_time,end_time FROM doctor_schedules WHERE id=?').get(t.today_schedule)},{...before});return {used_time_preserved:true};
 });
 await run('PATIENT-EMPTY','G19','Receptionist','Submit pasien baru dengan field wajib kosong','new patient required fields empty',async()=>{
  await r.goto(base+'/receptionist/patients/create');const before=sql.prepare('SELECT COUNT(*) n FROM patients WHERE clinic_id=?').get(t.clinic).n;await r.getByRole('button',{name:'Simpan pasien',exact:true}).click();assert.equal(await r.locator('form[action$="/patients"]').evaluate(e=>e.checkValidity()),false);assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients WHERE clinic_id=?').get(t.clinic).n,before);return {native_required:true};
 });
 await run('PATIENT-FUTURE-DOB','G19','Receptionist','Tolak tanggal lahir masa depan','new patient; future DOB',async()=>{
  await r.goto(base+'/receptionist/patients/create');await r.locator('#name').fill('QA Future DOB');await r.locator('#birth_date').fill('2099-01-01');await r.locator('#gender').selectOption('MALE');await r.locator('#phone').fill('081277778888');await r.getByRole('button',{name:'Simpan pasien',exact:true}).click();assert.equal(await r.locator('#birth_date').evaluate(e=>e.validity.rangeOverflow),true);assert.equal(sql.prepare("SELECT COUNT(*) n FROM patients WHERE name='QA Future DOB'").get().n,0);return {future_dob_rejected:true};
 });
 const patient=sql.prepare('SELECT name,nik,phone,medical_record_number FROM patients WHERE id=?').get(t.patient_id);
 for(const field of ['name','nik','phone','medical_record_number'])await run('PATIENT-SEARCH-'+field,'G20','Receptionist','Cari pasien berdasarkan '+field,'patient with >30 appointment history',async()=>{
  await r.goto(base+'/receptionist/patients');await r.locator('[name=search]').fill(patient[field]);await formSubmit(r,r.getByRole('button',{name:'Cari',exact:true}));assert.ok(await r.locator(`a[href$="/patients/${t.patient_id}"]`).count());return {search_field:field,owned_patient_found:true};
 });
 await run('PATIENT-LONG-HISTORY','G20','Receptionist','Buka histori pasien dan booking lama','>30 appointments for same patient',async()=>{
  await r.goto(base+`/receptionist/patients/${t.patient_id}`);const links=r.locator('a[href*="/receptionist/appointments/"]').filter({hasNotText:'Buat booking'});const count=await links.count();assert.ok(count>=30);await links.last().click();await r.waitForURL(/\/appointments\/\d+$/);assert.ok((await r.locator('main').innerText()).includes(patient.name));return {history_entries:count,oldest_opened:true};
 });
 await run('AUDIT-COMBINATION','G50','Clinic Admin','Filter aktor/tanggal/aksi, next/previous dan empty','35 fixture events; matching and no-result filters',async()=>{
  await p.goto(base+'/admin/audit-logs');await p.locator('[name=from]').fill(fixture.today);await p.locator('[name=to]').fill(fixture.today);await p.locator('[name=action]').selectOption('qa.closure.fixture');await p.locator('[name=actor]').fill('QA Closure dental.owner');await formSubmit(p,p.getByRole('button',{name:'Terapkan filter',exact:true}));assert.ok((await p.locator('tbody').innerText()).includes('qa.closure.fixture'));await p.locator('a[rel=next]:visible').click();await p.waitForURL(/page=2/);assert.ok((await p.locator('tbody').innerText()).includes('qa.closure.fixture'));await p.locator('a[rel=prev]:visible').click();await p.waitForURL(/page=1/);await p.locator('[name=actor]').fill('TidakAdaAktorQA');await formSubmit(p,p.getByRole('button',{name:'Terapkan filter',exact:true}));assert.match(await p.locator('tbody').innerText(),/Tidak ada aktivitas/);return {filters_combined:true,pagination:true,empty:true};
 });
 await run('AUDIT-METADATA','G51','Clinic Admin','Periksa metadata whitelist dan referensi aman','safe fixture source+appointment_id and unlisted extra metadata',async()=>{
  await p.goto(base+'/admin/audit-logs?action=qa.closure.fixture');const body=await p.locator('tbody').innerText();assert.ok(body.includes('source: fixture'));assert.ok(body.includes('appointment_id:'));assert.ok(!body.includes('discarded_extra')&&!body.includes('must not render'));return {whitelisted_keys_rendered:true,extra_hidden:true};
 });
 for(const [timezone,offset] of [['Asia/Makassar',8],['Asia/Jayapura',9]])await run('CLINIC-TZ-'+offset,'G11','Clinic Admin','Simpan timezone '+timezone+' dan buka dashboard','clinic with existing sessions',async()=>{
  await p.goto(base+`/admin/clinic/${t.clinic}/edit`);await p.locator('#timezone').selectOption(timezone);await formSubmit(p,p.getByRole('button',{name:'Simpan perubahan',exact:true}));assert.equal(sql.prepare('SELECT timezone FROM clinic_settings WHERE clinic_id=?').get(t.clinic).timezone,timezone);await p.goto(base+'/admin/dashboard');assert.ok((await p.locator('main').innerText()).includes(timezone));return {saved_timezone:timezone};
 });
 // Restore through the same UI so later scenarios retain the documented clinic timezone.
 await p.goto(base+`/admin/clinic/${t.clinic}/edit`);await p.locator('#timezone').selectOption('Asia/Jakarta');await formSubmit(p,p.getByRole('button',{name:'Simpan perubahan',exact:true}));
}finally{await finish('closure-administration');}
