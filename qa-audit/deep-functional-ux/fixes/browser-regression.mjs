import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {execFileSync} from 'node:child_process';
import {base,client,check,sql,shot,inspect,finish,state,saveState,results,resultsFile,runtime,evidence} from '../scripts/common.mjs';
const env={...process.env,...JSON.parse(readFileSync(join(runtime.root,'env.json')))};
const s=state();
// Only the disposable staff fixture is verified; staff creation deliberately sends verification mail.
sql.prepare("UPDATE users SET email_verified_at=CURRENT_TIMESTAMP WHERE email='deep.doctor@clinicos.test'").run();
const dc=await client('deep-doctor','deep.doctor@clinicos.test');
try {
 await check('REG-HANDOFF','F15','Doctor','Dokter terverifikasi memulai visit dari antrean CALLED',['Verifikasi fixture staf dummy','Buka antrean sendiri','Mulai periksa','Periksa visit dan editor'],async()=>{
  const p=dc.page;await p.goto(base+'/doctor/queues');await p.locator(`form[action$="/queues/${s.staffQueueId}/start"] button`).click();await p.waitForURL(/\/doctor\/visits\/\d+$/);const id=Number(new URL(p.url()).pathname.split('/').at(-1));saveState({journeyVisitId:id});assert.equal(sql.prepare('SELECT status FROM visits WHERE id=?').get(id).status,'IN_PROGRESS');assert.equal(sql.prepare('SELECT status FROM queues WHERE id=?').get(s.staffQueueId).status,'IN_PROGRESS');return {visit_id:id,assigned_doctor_handoff:true};
 });
 const roles=[['admin','admin@clinicos.test'],['operational-admin','operational.admin@clinicos.test'],['receptionist','receptionist@clinicos.test'],['doctor','doctor@clinicos.test'],['patient','fix.patient@clinicos.test'],['platform','platform@clinicos.test']];
 for(const [role,email] of roles){
  await check('REG-ACCESS-'+role,'F01/F20/F25',role,'Akses role dan batas isi medis tetap berlaku',['Login','Buka tujuan role','Coba visit yang tidak ditugaskan'],async()=>{
   const c=await client(role,email);const response=await c.page.goto(base+`/doctor/visits/${state().journeyVisitId}`);assert.ok([403,404].includes(response.status()),`${role}: unexpected status ${response.status()}`);if(role==='operational-admin'){const commercial=await c.page.goto(base+'/subscription/payments');assert.equal(commercial.status(),403);}await c.context.close();return {medical_access_denied:true};
  });
 }
 const source=readFileSync(join(runtime.root,'dfu-fixture.php'),'utf8');
 const prefix=source.slice(0,source.indexOf('$result ='));
 const setup=join(runtime.root,'dfu-late.php');
 writeFileSync(setup,prefix+`
$schedule = $doctor->schedules()->create(['day_of_week'=>now('Asia/Jakarta')->dayOfWeek,'start_time'=>'08:00','end_time'=>'09:00','quota'=>100,'is_active'=>true]);
Carbon\\CarbonImmutable::setTestNow(Carbon\\CarbonImmutable::now('Asia/Jakarta')->startOfDay()->addHours(7));
$result = app(App\\Services\\BookingService::class)->book($clinic,['patient_id'=>$patient->id,'doctor_id'=>$doctor->id,'doctor_schedule_id'=>$schedule->id,'service_id'=>$service->id,'appointment_date'=>now('Asia/Jakarta')->toDateString(),'idempotency_key'=>(string)Illuminate\\Support\\Str::uuid()],$receptionist,'RECEPTIONIST');
Carbon\\CarbonImmutable::setTestNow();
echo json_encode(['id'=>$result['appointment']->id,'token'=>$result['access_token']]);`);
 const late=JSON.parse(execFileSync('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd',setup],{env,cwd:runtime.repo,encoding:'utf8'}));
 for(const [role,path] of [['patient',`/patient/appointments/${late.id}`],['receptionist',`/receptionist/appointments/${late.id}`],['guest',`/booking/${late.id}/status?token=${late.token}`]]){
  await check('REG-LATE-'+role,'DFU-04',role,'Pembatalan setelah awal sesi ditolak dengan penjelasan',['Buka booking yang melewati awal sesi','Batalkan','Periksa error, state dan reload'],async()=>{
   const c=await client(role,role==='patient'?'fix.patient@clinicos.test':undefined);const p=c.page;p.removeAllListeners('dialog');p.on('dialog',d=>d.accept());await p.goto(base+path);await p.getByRole('button',{name:'Batalkan booking',exact:true}).click();await p.waitForLoadState('domcontentloaded');await p.getByRole('alert').filter({hasText:'setelah sesi dimulai'}).waitFor({state:'visible'});assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(late.id).status,'BOOKED');await shot(p,'REG-LATE-'+role);await p.reload();assert.equal(await p.getByRole('button',{name:'Batalkan booking',exact:true}).count(),1);await c.context.close();return {rejection_visible:true,state_preserved:true};
  });
 }
 await check('REG-MOBILE-KEYBOARD','DFU-09/10/11','Patient','Booking baru dapat dinavigasi keyboard dan responsif',['Buka pilihan klinik','375/768/1024/1440','Klik label dan ketik kode','Lanjut dengan Enter'],async()=>{
  const c=await client('patient','fix.patient@clinicos.test');const p=c.page;await p.goto(base+'/patient/appointments/create');const metrics=await inspect(p,'Patient','Booking baru setelah perbaikan');assert.ok(metrics.responsive.every(x=>!x.overflow));await p.getByText('Kode klinik',{exact:true}).click();assert.equal(await p.locator('#clinic_code').evaluate(e=>e===document.activeElement),true);await p.keyboard.type('klinik-sehat-sentosa');await p.keyboard.press('Enter');await p.waitForURL(/\/clinic\/klinik-sehat-sentosa\/booking/);await c.context.close();return {keyboard:true,responsive:metrics.responsive};
 });
 const replacements={'QUEUE-05':'REG-HANDOFF','BOOK-06':'REG-LATE-patient'};
 for(const r of results)if(replacements[r.id]){r.superseded_by=replacements[r.id];r.harness_note=r.id==='QUEUE-05'?'Staf baru belum terverifikasi; fixture diverifikasi sebelum handoff.':'Fixture sesi lampau ditambahkan; kasus kini diuji melalui browser.';}
 writeFileSync(resultsFile,JSON.stringify(results,null,2));
}finally{await finish('regression-roles-and-late-cancellation');}
