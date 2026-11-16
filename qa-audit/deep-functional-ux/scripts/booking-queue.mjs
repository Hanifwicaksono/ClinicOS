import assert from 'node:assert/strict';
import { base,client,check,sql,submit,shot,inspect,saveState,state,finish,selectSearch,credential } from './common.mjs';

const owner=await client('admin'), reception=await client('receptionist'), patient=await client('patient'), doctor=await client('deep-doctor','deep.doctor@clinicos.test');
const p=reception.page, pp=patient.page, dp=doctor.page, op=owner.page;
const s=state();const ids={...s};
const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
const tomorrow=new Date(today+'T12:00:00Z');tomorrow.setUTCDate(tomorrow.getUTCDate()+1);
const tomorrowDate=tomorrow.toISOString().slice(0,10);
const day=new Date(today+'T12:00:00Z').getUTCDay();
const hour=Number(new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Jakarta',hour:'2-digit',hour12:false}).format(new Date()));
const later=String(Math.min(22,hour+2)).padStart(2,'0')+':00';
function queueFor(appointmentId) {return sql.prepare('SELECT id,status FROM queues WHERE appointment_id=?').get(appointmentId);}
async function queueButton(page,id,action,name,accept=false) {
    await page.goto(base+(page===dp?'/doctor/queues':'/receptionist/queues'));
    if(accept) {page.removeAllListeners('dialog');page.once('dialog',d=>d.accept());}
    await page.locator(`form[action$="/queues/${id}/${action}"]`).getByRole('button',{name,exact:true}).click();
    await page.waitForTimeout(500);
}
async function staffBooking(name) {
    await p.goto(base+'/receptionist/appointments');await p.getByRole('link',{name:/Booking baru|Buat booking|Tambah booking/}).first().click();
    await p.locator('#new_patient_name').fill(name);await p.locator('#new_patient_birth_date').fill('1991-07-05');await p.locator('#new_patient_phone').fill('0812777'+String(Date.now()).slice(-6));
    await selectSearch(p,'doctor_id','QA Deep Dokter');await p.locator('#appointment_date').fill(today);
    await selectSearch(p,'service_id');await selectSearch(p,'doctor_schedule_id');
    await submit(p,'Buat booking',/\/receptionist\/appointments\/\d+$/);
    return Number(new URL(p.url()).pathname.split('/').at(-1));
}
async function publicBooking(date=today) {
    await pp.goto(base+'/clinic/klinik-sehat-sentosa');await pp.getByRole('link',{name:/Booking|Daftar|kunjungan/i}).first().click();
    await pp.waitForURL('**/clinic/klinik-sehat-sentosa/booking');
    await selectSearch(pp,'doctor_id','QA Deep Dokter');await pp.locator('#appointment_date').fill(date);
    await selectSearch(pp,'service_id');await selectSearch(pp,'doctor_schedule_id');
    await pp.locator('#birth_date').fill('1990-05-10');await pp.locator('#gender').selectOption('MALE');await pp.locator('#phone').fill('081266665555');await pp.locator('#address').fill('Alamat pasien dummy untuk audit');
    await submit(pp,'Konfirmasi booking',/\/booking\/\d+\/status/);
    return Number(new URL(pp.url()).pathname.split('/').at(-2));
}
try {
    await check('BOOK-01','F08','Clinic Admin','Sesi dokter baru hari ini dapat dibuat dari UI',['Jadwal dokter','Tambah jadwal dokter QA terverifikasi','Sesi hari ini mulai dua jam ke depan','Simpan'],async()=>{
        await op.goto(base+'/admin/doctor-schedules/create');await op.locator('#doctor_id').selectOption(String(s.doctorId));await op.locator('#day_of_week').selectOption(String(day));
        await op.locator('#start_time').fill(later);await op.locator('#end_time').fill('23:59');await op.locator('#quota').fill('20');await submit(op,'Tambah jadwal','**/admin/doctor-schedules');
        const row=sql.prepare('SELECT id FROM doctor_schedules WHERE doctor_id=? AND day_of_week=? AND is_active=1').get(s.doctorId,day);assert.ok(row);ids.todayScheduleId=row.id;
        return {schedule_id:row.id,date:today,start:later};
    });
    await check('BOOK-02','F11','Receptionist','Walk-in: buat booking dan periksa patient/appointment/queue tersimpan',['Menu Booking & antrean','Booking baru','Isi pasien baru','Pilih dokter, tanggal, layanan, sesi memakai keyboard','Buat booking'],async()=>{
        ids.staffAppointmentId=await staffBooking('QA Deep Journey Utama');ids.staffQueueId=queueFor(ids.staffAppointmentId).id;
        assert.equal(queueFor(ids.staffAppointmentId).status,'BOOKED');assert.equal(sql.prepare('SELECT COUNT(*) n FROM visits WHERE appointment_id=?').get(ids.staffAppointmentId).n,0);
        return {appointment_id:ids.staffAppointmentId,queue_id:ids.staffQueueId,visit_not_created_yet:true};
    });
    await check('BOOK-03','F11','Patient','Booking publik terautentikasi dari halaman klinik sampai konfirmasi',['Buka website klinik','Klik booking','Pilih dokter dan sesi','Lengkapi identitas','Konfirmasi','Lihat Booking saya'],async()=>{
        ids.patientAppointmentId=await publicBooking();ids.patientQueueId=queueFor(ids.patientAppointmentId).id;
        assert.equal(queueFor(ids.patientAppointmentId).status,'BOOKED');
        await shot(pp,'BOOK-03-confirmation');
        await pp.goto(base+'/patient/appointments');const link=pp.locator(`a[href$="/patient/appointments/${ids.patientAppointmentId}"]`);await link.click();
        assert.ok((await pp.locator('main').innerText()).includes('QA Deep Dokter'));
        return {appointment_id:ids.patientAppointmentId,listed_in_patient_portal:true};
    });
    await check('QUEUE-01','F13','Receptionist','Check-in mengubah BOOKED ke WAITING tanpa membuat visit',['Antrean hari ini','Cari booking QA','Klik Check-in','Periksa status dan DB'],async()=>{
        assert.ok(ids.staffQueueId,'BLOCKED: booking gagal');await queueButton(p,ids.staffQueueId,'check-in','Check-in');
        await p.waitForFunction(()=>document.body.innerText.includes('Menunggu'));
        assert.equal(queueFor(ids.staffAppointmentId).status,'WAITING');assert.equal(sql.prepare('SELECT COUNT(*) n FROM visits WHERE appointment_id=?').get(ids.staffAppointmentId).n,0);
        return {queue:'WAITING',visit_count:0};
    });
    await check('QUEUE-02','F13','Receptionist','FIFO: panggil urutan kedua ditolak tanpa merusak state',['Check-in pasien kedua','Klik Panggil pada urutan kedua','Periksa pesan urutan paling awal'],async()=>{
        assert.ok(ids.patientQueueId,'BLOCKED: booking pasien gagal');await queueButton(p,ids.patientQueueId,'check-in','Check-in');
        await queueButton(p,ids.patientQueueId,'call','Panggil');
        assert.equal(queueFor(ids.patientAppointmentId).status,'WAITING');
        assert.ok((await p.locator('[data-queue-action-feedback]').innerText()).match(/paling awal/));
        return {fifo_rejected:true,screenshot:await shot(p,'QUEUE-02-fifo-error')};
    });
    await check('QUEUE-03','F13','Receptionist','Call, skip, return, call lagi mengikuti lifecycle',['Panggil pertama','Lewati','Kembalikan','Panggil ulang'],async()=>{
        await queueButton(p,ids.staffQueueId,'call','Panggil');assert.equal(queueFor(ids.staffAppointmentId).status,'CALLED');
        await queueButton(p,ids.staffQueueId,'skip','Lewati');assert.equal(queueFor(ids.staffAppointmentId).status,'SKIPPED');
        await queueButton(p,ids.staffQueueId,'return','Kembalikan');assert.equal(queueFor(ids.staffAppointmentId).status,'WAITING');
        await queueButton(p,ids.staffQueueId,'call','Panggil');assert.equal(queueFor(ids.staffAppointmentId).status,'CALLED');return {transitions:['CALLED','SKIPPED','WAITING','CALLED']};
    });
    await check('QUEUE-04','F13','Receptionist','Satu panggilan aktif: panggilan kedua ditolak dengan feedback',['Sementara panggilan pertama aktif','Klik panggil pasien kedua','Periksa state dan pesan'],async()=>{
        await queueButton(p,ids.patientQueueId,'call','Panggil');assert.equal(queueFor(ids.patientAppointmentId).status,'WAITING');
        assert.ok((await p.locator('[data-queue-action-feedback]').innerText()).includes('panggilan aktif'));return {active_call_limit:true};
    });
    await check('QUEUE-05','F15','Doctor','Handoff resepsionis → dokter membuat visit ketika mulai periksa',['Login dokter QA','Antrean saya','Mulai periksa pasien yang dipanggil','Periksa visit dan editor'],async()=>{
        await dp.goto(base+'/doctor/queues');await dp.locator(`form[action$="/queues/${ids.staffQueueId}/start"]`).getByRole('button',{name:'Mulai periksa'}).click();
        await dp.waitForURL(/\/doctor\/visits\/\d+$/);ids.journeyVisitId=Number(new URL(dp.url()).pathname.split('/').at(-1));
        assert.equal(queueFor(ids.staffAppointmentId).status,'IN_PROGRESS');assert.equal(sql.prepare('SELECT status FROM visits WHERE id=?').get(ids.journeyVisitId).status,'IN_PROGRESS');
        await inspect(dp,'deep-doctor','Editor journey baru');return {visit_id:ids.journeyVisitId,queue:'IN_PROGRESS'};
    });
    await check('QUEUE-06','F13','Receptionist','Cancel antrean kedua memperbarui appointment dan portal pasien',['Antrean hari ini','Klik Batalkan pada pasien kedua','Setujui dialog','Buka ulang portal pasien'],async()=>{
        await queueButton(p,ids.patientQueueId,'cancel','Batalkan',true);
        assert.equal(queueFor(ids.patientAppointmentId).status,'CANCELLED');assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(ids.patientAppointmentId).status,'CANCELLED');
        await pp.goto(base+`/patient/appointments/${ids.patientAppointmentId}`);assert.ok((await pp.locator('main').innerText()).includes('CANCELLED'));
        assert.equal(await pp.getByRole('button',{name:'Batalkan booking',exact:true}).count(),0);return {appointment:'CANCELLED',queue:'CANCELLED',cancel_action_removed:true};
    });
    await check('BOOK-04','F11','Receptionist','Pasien terdaftar dapat dipilih untuk booking berulang',['Detail pasien QA','Buat booking','Pilih dokter/sesi','Simpan'],async()=>{
        await p.goto(base+`/receptionist/patients/${s.patientId}`);await p.getByRole('link',{name:'Buat booking',exact:true}).click();
        assert.ok(await p.locator('[name=patient_id]').inputValue());await selectSearch(p,'doctor_id','QA Deep Dokter');await p.locator('#appointment_date').fill(today);await selectSearch(p,'service_id');await selectSearch(p,'doctor_schedule_id');
        await submit(p,'Buat booking',/\/receptionist\/appointments\/\d+$/);ids.existingAppointmentId=Number(new URL(p.url()).pathname.split('/').at(-1));ids.existingQueueId=queueFor(ids.existingAppointmentId).id;
        assert.equal(sql.prepare('SELECT patient_id FROM appointments WHERE id=?').get(ids.existingAppointmentId).patient_id,s.patientId);return {existing_patient_reused:true,appointment_id:ids.existingAppointmentId};
    });
    await check('QUEUE-07','F13','Receptionist','No-show sebelum sesi dimulai: batal dialog aman dan konfirmasi memberi alasan penolakan',['Klik No-show','Dismiss dialog','Periksa tidak berubah','Klik ulang dan accept','Periksa error waktu'],async()=>{
        await p.goto(base+'/receptionist/queues');p.removeAllListeners('dialog');p.once('dialog',d=>d.dismiss());
        await p.locator(`form[action$="/queues/${ids.existingQueueId}/no-show"] button`).click();assert.equal(queueFor(ids.existingAppointmentId).status,'BOOKED');
        await queueButton(p,ids.existingQueueId,'no-show','No-show',true);assert.equal(queueFor(ids.existingAppointmentId).status,'BOOKED');
        assert.ok((await p.locator('[data-queue-action-feedback]').innerText()).includes('setelah sesi dimulai'));return {dismiss_safe:true,premature_no_show_rejected:true};
    });
    await check('BOOK-05','F12','Receptionist','Pembatalan booking sebelum sesi dimulai tersimpan setelah reload',['Detail booking pasien terdaftar','Isi alasan','Klik batalkan','Accept','Reload'],async()=>{
        await p.goto(base+`/receptionist/appointments/${ids.existingAppointmentId}`);await p.locator('[name=cancel_reason]').fill('Pasien meminta jadwal ulang');p.removeAllListeners('dialog');p.once('dialog',d=>d.accept());
        await submit(p,'Batalkan booking');await p.waitForTimeout(400);await p.reload();assert.equal(queueFor(ids.existingAppointmentId).status,'CANCELLED');return {cancelled:true};
    });
    await check('BOOK-06','F12','Patient','Pembatalan setelah sesi dimulai menampilkan alasan penolakan',['Buka booking demo hari ini yang sesinya sudah dimulai','Klik batalkan dan accept','Periksa feedback'],async()=>{
        const row=sql.prepare("SELECT a.id FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN users u ON u.id=p.user_id JOIN doctor_schedules ds ON ds.id=a.doctor_schedule_id WHERE u.email='patient@clinicos.test' AND a.appointment_date=? AND a.status='BOOKED' AND ds.start_time='00:00' LIMIT 1").get(today);
        assert.ok(row,'BLOCKED: tidak ada fixture booking dimulai');await pp.goto(base+`/patient/appointments/${row.id}`);pp.removeAllListeners('dialog');pp.once('dialog',d=>d.accept());await submit(pp,'Batalkan booking');await pp.waitForTimeout(500);
        assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(row.id).status,'BOOKED');
        const text=await pp.locator('main').innerText();await shot(pp,'BOOK-06-cancellation-no-feedback');
        assert.ok(text.includes('setelah sesi dimulai'),'Pembatalan ditolak tetapi alasan validasi tidak tampil pada detail booking');return {rejected_with_message:true};
    });
    await check('BOOK-07','F14','Guest','Display publik tidak menampilkan identitas pasien dan tersedia di mobile',['Buka display publik','Periksa nomor antrean','Periksa tidak ada nama pasien QA/NIK/RM','Resize 375'],async()=>{
        const guest=await client('guest');await guest.page.goto(base+'/display/klinik-sehat-sentosa');const snap=await inspect(guest.page,'guest','Display publik dengan journey berjalan');
        assert.ok(!snap.text.includes('QA Deep Journey Utama'));assert.equal(snap.responsive.some(x=>x.overflow),false);await guest.context.close();return {no_patient_name:true,responsive:true};
    });
    saveState(ids);
} finally {saveState(ids);await finish('4-booking-queue-handoff');}
