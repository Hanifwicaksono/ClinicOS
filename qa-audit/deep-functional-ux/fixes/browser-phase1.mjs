import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {execFileSync} from 'node:child_process';
import {base,client,check,sql,shot,finish,runtime,evidence} from '../scripts/common.mjs';
const label=process.env.DFU_BROWSER_LABEL||'before';
const setup=join(runtime.root,'dfu-fixture.php');
writeFileSync(setup,`<?php
$app = require __DIR__.'/application.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$clinic = App\\Models\\Clinic::where('slug','klinik-sehat-sentosa')->firstOrFail();
$password = App\\Models\\User::where('email','admin@clinicos.test')->value('password');
$doctorUser = App\\Models\\User::firstOrCreate(['email'=>'fix.doctor@clinicos.test'], ['name'=>'QA Fix Doctor','password'=>$password,'clinic_id'=>$clinic->id,'email_verified_at'=>now(),'account_type'=>null,'is_active'=>true]);
$doctorUser->assignRole('Doctor');
$doctor = App\\Models\\Doctor::firstOrCreate(['user_id'=>$doctorUser->id],['clinic_id'=>$clinic->id,'specialization'=>'Dokter Umum','is_active'=>true]);
$service = $clinic->services()->where('is_active',true)->firstOrFail(); $doctor->services()->syncWithoutDetaching([$service->id]);
$schedule = $doctor->schedules()->firstOrCreate(['day_of_week'=>now('Asia/Jakarta')->dayOfWeek,'start_time'=>'22:00','end_time'=>'23:59'],['quota'=>100,'is_active'=>true]);
$patientUser = App\\Models\\User::where('email','fix.patient@clinicos.test')->first();
if (!$patientUser) { $patientUser = App\\Models\\User::factory()->forClinic($clinic)->create(['email'=>'fix.patient@clinicos.test','name'=>'QA Fix Patient','password'=>$password,'account_type'=>App\\UserAccountType::Patient]); $patientUser->assignRole('Patient'); }
$patient = App\\Models\\Patient::firstOrCreate(['clinic_id'=>$clinic->id,'user_id'=>$patientUser->id],['name'=>'QA Fix Patient','medical_record_number'=>'QA-FIX-001','birth_date'=>'1990-01-01','gender'=>'MALE','phone'=>'081277778888','email'=>$patientUser->email,'address'=>'Alamat dummy','is_active'=>true]);
$receptionist = App\\Models\\User::where('email','receptionist@clinicos.test')->firstOrFail();
$result = app(App\\Services\\BookingService::class)->book($clinic,['patient_id'=>$patient->id,'doctor_id'=>$doctor->id,'doctor_schedule_id'=>$schedule->id,'service_id'=>$service->id,'appointment_date'=>now('Asia/Jakarta')->toDateString(),'idempotency_key'=>(string)Illuminate\\Support\\Str::uuid()],$receptionist,'RECEPTIONIST');
$queue = $result['appointment']->queue;
app(App\\Services\\QueueWorkflowService::class)->checkIn($queue,$receptionist);
app(App\\Services\\QueueWorkflowService::class)->call($queue->fresh(),$receptionist);
$visit = app(App\\Services\\VisitWorkflowService::class)->start($queue->fresh(),$doctorUser)['visit'];
echo json_encode(['clinic_id'=>$clinic->id,'doctor_id'=>$doctor->id,'schedule_id'=>$schedule->id,'appointment_id'=>$result['appointment']->id,'queue_id'=>$queue->id,'visit_id'=>$visit->id]);
`);
const env={...process.env,...JSON.parse(readFileSync(join(runtime.root,'env.json')))};
const fixture=JSON.parse(execFileSync('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd',setup],{env,cwd:runtime.repo,encoding:'utf8',stdio:['ignore','pipe','pipe']}));
writeFileSync(join(evidence,label+'-phase1-fixture.json'),JSON.stringify(fixture,null,2));
try{
    const patient=await client('patient','fix.patient@clinicos.test');
    await check(label+'-DFU-01','DFU-01','Patient → Doctor','Pembatalan tidak memutus visit aktif; dokter tetap dapat finalisasi',['Buka booking yang sedang diperiksa sebelum jam sesi','Coba batal bila tombol tampil','Periksa state','Dokter finalisasi'],async()=>{
        const p=patient.page;await p.goto(base+`/patient/appointments/${fixture.appointment_id}`);const button=p.getByRole('button',{name:'Batalkan booking',exact:true});
        if(await button.count()){p.removeAllListeners('dialog');p.once('dialog',d=>d.accept());await button.click();await p.waitForTimeout(300);}
        assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(fixture.appointment_id).status,'BOOKED');
        assert.equal(sql.prepare('SELECT status FROM queues WHERE id=?').get(fixture.queue_id).status,'IN_PROGRESS');
        const dc=await client('fix-doctor','fix.doctor@clinicos.test');const dp=dc.page;await dp.goto(base+`/doctor/visits/${fixture.visit_id}`);await dp.locator('[name=subjective]').fill('Catatan dummy regression');await dp.locator('[name=assessment]').fill('Assessment dummy regression');await dp.locator('[name=plan]').fill('Rencana dummy regression');await dp.waitForFunction(()=>!document.querySelector('[data-rme-form]').rmeAutosave.unsaved);await dp.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await dp.getByRole('button',{name:'Finalisasi kunjungan',exact:true}).click();await dp.locator('#correction_reason').waitFor({state:'visible'});await dc.context.close();return {cancel_rejected:true,finalized:true};
    });
    const owner=await client('admin');
    await check(label+'-DFU-02','DFU-02','Clinic Admin','Hari sesi terpakai tidak berubah',['Edit sesi ber-appointment','Ubah hari','Simpan','Periksa konsistensi'],async()=>{
        const p=owner.page;const old=sql.prepare('SELECT day_of_week FROM doctor_schedules WHERE id=?').get(fixture.schedule_id).day_of_week;
        try{await p.goto(base+`/admin/doctor-schedules/${fixture.schedule_id}/edit`);await p.locator('#day_of_week').selectOption(String((old+1)%7));await p.getByRole('button',{name:'Simpan perubahan',exact:true}).click();await p.waitForTimeout(300);await shot(p,label+'-DFU-02');assert.equal(sql.prepare('SELECT day_of_week FROM doctor_schedules WHERE id=?').get(fixture.schedule_id).day_of_week,old);return {day_preserved:true};}
        finally{sql.prepare('UPDATE doctor_schedules SET day_of_week=? WHERE id=?').run(old,fixture.schedule_id);}
    });
    const platform=await client('platform');
    await check(label+'-DFU-12','DFU-12','Platform Admin','Double-click hanya memberi satu grant',['Pilih Team sebulan','Isi alasan','Double-click','Periksa periode dan event'],async()=>{
        const p=platform.page;await p.goto(base+`/platform/tenants/${fixture.clinic_id}`);const price=sql.prepare("SELECT sp.id FROM subscription_prices sp JOIN subscription_plans p ON p.id=sp.subscription_plan_id WHERE p.code='team' AND duration_months=1").get();await p.locator('#subscription_price_id').selectOption(String(price.id));await p.locator('#reason').fill('Satu grant dummy '+label);
        const before=sql.prepare('SELECT COUNT(*) n FROM subscription_periods WHERE clinic_id=?').get(fixture.clinic_id).n;const button=p.getByRole('button',{name:'Berikan akses'});await button.scrollIntoViewIfNeeded();const rect=await button.boundingBox();await p.mouse.click(rect.x+rect.width/2,rect.y+rect.height/2,{clickCount:2,delay:10});await p.waitForTimeout(600);const added=sql.prepare('SELECT COUNT(*) n FROM subscription_periods WHERE clinic_id=?').get(fixture.clinic_id).n-before;await shot(p,label+'-DFU-12');assert.equal(added,1);return {added};
    });
}finally{await finish(label+'-phase1');}
