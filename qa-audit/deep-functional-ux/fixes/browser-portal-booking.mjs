import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {execFileSync} from 'node:child_process';
import {base,client,check,sql,selectSearch,shot,finish,runtime} from '../scripts/common.mjs';
const setup=join(runtime.root,'dfu-first-patient.php');
writeFileSync(setup,`<?php
$app=require __DIR__.'/application.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$password=App\\Models\\User::where('email','admin@clinicos.test')->value('password');
$user=App\\Models\\User::factory()->create(['email'=>'fix.first@clinicos.test','password'=>$password,'name'=>'QA First Portal','account_type'=>App\\UserAccountType::Patient]);$user->assignRole('Patient');echo '{}';`);
const env={...process.env,...JSON.parse(readFileSync(join(runtime.root,'env.json')))};
execFileSync('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd',setup],{env,cwd:runtime.repo,encoding:'utf8'});
const c=await client('patient','fix.first@clinicos.test'),p=c.page;
const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
try {
 await check('REG-PORTAL-FIRST','DFU-11/06','Patient baru','Booking pertama dari portal memakai kode klinik dan validasi telepon',['Dashboard → Booking baru','Kode salah ditolak','Kode benar','Telepon abc ditolak','Koreksi dan booking','Buka di riwayat'],async()=>{
  await p.goto(base+'/patient/dashboard');await p.getByRole('link',{name:'Booking baru',exact:true}).click();await p.waitForURL(/\/appointments\/create/);assert.ok((await p.locator('main').innerText()).includes('Belum ada klinik terhubung'));
  await p.locator('#clinic_code').fill('kode-tidak-ada');await p.getByRole('button',{name:'Lanjutkan booking'}).click();await p.getByRole('alert').filter({hasText:'Kode klinik tidak ditemukan'}).waitFor({state:'visible'});assert.equal(await p.locator('#clinic_code').inputValue(),'kode-tidak-ada');
  await p.locator('#clinic_code').fill('klinik-sehat-sentosa');await p.getByRole('button',{name:'Lanjutkan booking'}).click();await p.waitForURL(/\/clinic\/.*\/booking/);
  async function choose(){await selectSearch(p,'doctor_id','QA Fix Doctor');await p.locator('#appointment_date').fill(today);await selectSearch(p,'service_id');await selectSearch(p,'doctor_schedule_id','22:00');}
  await choose();await p.locator('#birth_date').fill('1990-05-10');await p.locator('#gender').selectOption('MALE');await p.locator('#phone').fill('abc');await p.locator('#address').fill('Alamat dummy portal');await p.getByRole('button',{name:'Konfirmasi booking',exact:true}).click();await p.waitForLoadState('domcontentloaded');await p.getByText(/Nomor telepon harus berisi angka/).first().waitFor({state:'visible'});assert.equal(sql.prepare("SELECT COUNT(*) n FROM patients p JOIN users u ON u.id=p.user_id WHERE u.email='fix.first@clinicos.test'").get().n,0);
  await p.locator('#phone').fill('+62 812-3456-7890');await p.getByRole('button',{name:'Konfirmasi booking',exact:true}).click();await p.waitForURL(/\/booking\/\d+\/status/);const id=Number(new URL(p.url()).pathname.split('/').at(-2));assert.equal(sql.prepare('SELECT status FROM queues WHERE appointment_id=?').get(id).status,'BOOKED');await p.goto(base+'/patient/appointments');await p.locator(`a[href$="/patient/appointments/${id}"]`).click();await p.waitForURL(new RegExp('/appointments/'+id+'$'));await shot(p,'REG-PORTAL-FIRST');return {first_booking_id:id,invalid_phone_no_write:true,portal_history:true};
 });
 await check('REG-PORTAL-REPEAT','DFU-11','Patient kembali','Booking ulang memakai klinik dikenal dan profile milik akun yang sama',['Booking saya → Booking baru','Pilih klinik dikenal','Isi sesi','Konfirmasi','Periksa profile tetap satu'],async()=>{
  await p.goto(base+'/patient/appointments');await p.getByRole('link',{name:'Booking baru',exact:true}).click();await p.waitForURL(/\/appointments\/create/);await p.getByRole('link',{name:'Klinik Sehat Sentosa',exact:true}).click();await p.waitForURL(/\/clinic\/.*\/booking/);await selectSearch(p,'doctor_id','QA Fix Doctor');await p.locator('#appointment_date').fill(today);await selectSearch(p,'service_id');await selectSearch(p,'doctor_schedule_id','22:00');await p.getByRole('button',{name:'Konfirmasi booking',exact:true}).click();await p.waitForURL(/\/booking\/\d+\/status/);assert.equal(sql.prepare("SELECT COUNT(*) n FROM patients p JOIN users u ON u.id=p.user_id WHERE u.email='fix.first@clinicos.test'").get().n,1);return {repeat_booking:true,one_owned_profile:true};
 });
}finally{await finish('portal-booking-first-and-repeat');}
