import assert from 'node:assert/strict';
import { base, client, check, sql, submit, shot, inspect, saveState, state, finish, credential } from './common.mjs';

const owner = await client('admin');
const reception = await client('receptionist');
const page = owner.page;
const prefix = 'QA Deep ';
const flags = {};
try {
    await check('ADM-01','F07','Clinic Admin','Layanan: create melalui menu lalu buka ulang', ['Menu Layanan','Tambah layanan','Isi nama/tarif/durasi','Simpan','Edit kembali'], async () => {
        await page.locator('aside nav').getByRole('link',{name:'Layanan',exact:true}).click();
        await page.getByRole('link',{name:/Tambah layanan/}).click();
        await page.locator('#name').fill(prefix + 'Konsultasi');
        await page.locator('#price').fill('85000');
        await page.locator('#duration_minutes').fill('25');
        await page.locator('#description').fill('Layanan sintetis untuk audit interaksi.');
        await submit(page,'Tambah layanan','**/admin/services');
        const record = sql.prepare('SELECT id,name,price FROM services WHERE name=?').get(prefix+'Konsultasi');
        assert.ok(record);
        flags.serviceId=record.id;
        await page.getByRole('row').filter({hasText:record.name}).getByRole('link',{name:'Edit',exact:true}).click();
        assert.equal(await page.locator('#price').inputValue(),'85000.00');
        return { service_id:record.id, persisted:true };
    });
    await check('ADM-02','F07','Clinic Admin','Layanan: duplicate ditolak dan data form dipertahankan',['Tambah layanan dengan nama yang sama','Klik simpan','Periksa pesan serta nilai form'],async()=>{
        await page.goto(base+'/admin/services/create');
        await page.locator('#name').fill(prefix+'Konsultasi');
        await page.locator('#price').fill('85000');
        await submit(page,'Tambah layanan');
        await page.waitForTimeout(300);
        assert.equal(sql.prepare('SELECT COUNT(*) n FROM services WHERE name=?').get(prefix+'Konsultasi').n,1);
        assert.ok((await page.locator('body').innerText()).match(/taken|digunakan|sudah/i));
        assert.equal(await page.locator('#name').inputValue(),prefix+'Konsultasi');
        return { duplicate_rejected:true, screenshot:await shot(page,'ADM-02-duplicate-service') };
    });
    await check('ADM-03','F07','Clinic Admin','Layanan: empty, batas durasi dan harga negatif diblok browser',['Kosongkan nama','Isi harga negatif','Isi durasi 481','Periksa validitas kontrol'],async()=>{
        await page.goto(base+'/admin/services/create');
        await page.locator('#name').fill(''); await page.locator('#price').fill('-1'); await page.locator('#duration_minutes').fill('481');
        assert.equal(await page.locator('#name').evaluate(e=>e.validity.valueMissing),true);
        assert.equal(await page.locator('#price').evaluate(e=>e.validity.rangeUnderflow),true);
        assert.equal(await page.locator('#duration_minutes').evaluate(e=>e.validity.rangeOverflow),true);
        return { native_boundary_validation:true, server_validation:'covered separately by existing regression' };
    });
    await check('ADM-04','F06','Clinic Admin','Staf: tambah resepsionis lalu edit dan buka ulang',['Tim klinik','Tambah staf','Pilih Resepsionis','Isi akun dummy','Simpan','Edit telepon'],async()=>{
        await page.locator('aside nav').getByRole('link',{name:'Tim klinik'}).click();
        await page.getByRole('link',{name:/Tambah staf/}).click();
        await page.locator('#name').fill(prefix+'Resepsionis');
        await page.locator('#email').fill('deep.receptionist@clinicos.test');
        await page.locator('#role').selectOption('Receptionist');
        await page.locator('#password').fill(credential);
        await submit(page,'Buat akun staf','**/admin/staff');
        const user=sql.prepare('SELECT id,email_verified_at FROM users WHERE email=?').get('deep.receptionist@clinicos.test');
        assert.ok(user); assert.equal(user.email_verified_at,null); flags.staffId=user.id;
        await page.getByRole('row').filter({hasText:'deep.receptionist@clinicos.test'}).getByRole('link',{name:'Edit',exact:true}).click();
        await page.locator('#phone').fill('081299991234');
        await submit(page,'Simpan perubahan','**/admin/staff');
        assert.equal(sql.prepare('SELECT phone FROM users WHERE id=?').get(user.id).phone,'081299991234');
        return {user_id:user.id,verification_required:true};
    });
    await check('ADM-05','F06','Receptionist','Staf baru diarahkan ke verifikasi, tidak terjebak login',['Login akun staf baru','Periksa verifikasi dan tombol kirim ulang','Logout'],async()=>{
        const fresh=await client('new-staff','deep.receptionist@clinicos.test');
        assert.equal(new URL(fresh.page.url()).pathname,'/verify-email');
        const snap=await inspect(fresh.page,'new-staff','Verifikasi staf baru');
        assert.ok(snap.actions.some(a=>/kirim ulang|resend/i.test(a.text)));
        await fresh.context.close();
        return {awaiting_verification:true,mail_delivery:'BLOCKED: array mail sink, external delivery excluded'};
    });
    await check('ADM-06','F06','Clinic Admin','Staf: email duplikat dan password pendek ditolak',['Form tambah staf','Email yang sudah ada','Password pendek','Simpan'],async()=>{
        await page.goto(base+'/admin/staff/create');
        await page.locator('#name').fill(prefix+'Duplikat');
        await page.locator('#email').fill('deep.receptionist@clinicos.test');
        await page.locator('#role').selectOption('Receptionist');
        await page.locator('#password').fill('short');
        await submit(page,'Buat akun staf');
        await page.waitForTimeout(300);
        assert.ok((await page.locator('body').innerText()).match(/taken|sudah|characters|karakter/i));
        assert.equal(sql.prepare('SELECT COUNT(*) n FROM users WHERE email=?').get('deep.receptionist@clinicos.test').n,1);
        return {errors_visible:true,screenshot:await shot(page,'ADM-06-staff-invalid')};
    });
    await check('ADM-07','F06','Clinic Admin','Staf: deaktivasi dan aktivasi ulang dapat dikelola dari UI',['Edit staf baru','Nonaktifkan','Simpan','Login staf ditolak','Aktifkan ulang'],async()=>{
        assert.ok(flags.staffId,'BLOCKED: staf belum dibuat');
        await page.goto(base+`/admin/staff/${flags.staffId}/edit`);
        await page.locator('input[type=checkbox][name=is_active]').uncheck();
        await submit(page,'Simpan perubahan','**/admin/staff');
        assert.equal(sql.prepare('SELECT is_active FROM users WHERE id=?').get(flags.staffId).is_active,0);
        const guest=await client('guest'); await guest.page.goto(base+'/admin');
        await guest.page.locator('#email').fill('deep.receptionist@clinicos.test'); await guest.page.locator('#password').fill(credential);
        await guest.page.locator('button[type=submit]').click(); await guest.page.waitForTimeout(600);
        assert.equal(new URL(guest.page.url()).pathname,'/admin');
        await guest.context.close();
        await page.goto(base+`/admin/staff/${flags.staffId}/edit`);
        await page.locator('input[type=checkbox][name=is_active]').check();
        await submit(page,'Simpan perubahan','**/admin/staff');
        assert.equal(sql.prepare('SELECT is_active FROM users WHERE id=?').get(flags.staffId).is_active,1);
        return {deactivate_reactivate:true,history_retained:true};
    });
    await check('ADM-08','F06','Clinic Admin','Dokter: create dengan profil profesi dan relasi layanan',['Tambah staf Dokter','Isi spesialisasi/SIP','Pilih layanan','Simpan','Buka ulang'],async()=>{
        await page.goto(base+'/admin/staff/create');
        await page.locator('#name').fill(prefix+'Dokter'); await page.locator('#email').fill('deep.doctor@clinicos.test');
        await page.locator('#role').selectOption('Doctor'); await page.locator('#password').fill(credential);
        await page.locator('#specialization').fill('Dokter Umum'); await page.locator('#license_number').fill('QA-SIP-001');
        if(flags.serviceId) await page.locator(`input[name="service_ids[]"][value="${flags.serviceId}"]`).check();
        await submit(page,'Buat akun staf','**/admin/staff');
        const doctor=sql.prepare('SELECT d.id,d.user_id FROM doctors d JOIN users u ON u.id=d.user_id WHERE u.email=?').get('deep.doctor@clinicos.test');
        assert.ok(doctor); flags.doctorId=doctor.id; flags.doctorUserId=doctor.user_id;
        assert.ok(sql.prepare('SELECT 1 FROM doctor_service WHERE doctor_id=? AND service_id=?').get(doctor.id,flags.serviceId));
        return {doctor_id:doctor.id,service_link:true};
    });
    await check('ADM-09','F08','Clinic Admin','Jadwal: create/edit/nonaktif dan validasi bentrok',['Jadwal dokter','Tambah jadwal','Pilih dokter baru','Isi sesi','Simpan','Ulangi sesi bentrok'],async()=>{
        assert.ok(flags.doctorId,'BLOCKED: dokter belum dibuat');
        await page.locator('aside nav').getByRole('link',{name:'Jadwal dokter',exact:true}).click();
        await page.getByRole('link',{name:/Tambah jadwal/}).click();
        await page.locator('#doctor_id').selectOption(String(flags.doctorId));
        await page.locator('#day_of_week').selectOption('1'); await page.locator('#start_time').fill('08:00'); await page.locator('#end_time').fill('12:00');
        await page.locator('#quota').fill('12'); await submit(page,'Tambah jadwal','**/admin/doctor-schedules');
        const session=sql.prepare('SELECT id FROM doctor_schedules WHERE doctor_id=? ORDER BY id DESC LIMIT 1').get(flags.doctorId); flags.scheduleId=session.id;
        await page.goto(base+'/admin/doctor-schedules/create'); await page.locator('#doctor_id').selectOption(String(flags.doctorId));
        await page.locator('#day_of_week').selectOption('1'); await submit(page,'Tambah jadwal'); await page.waitForTimeout(300);
        assert.equal(sql.prepare('SELECT COUNT(*) n FROM doctor_schedules WHERE doctor_id=?').get(flags.doctorId).n,1);
        assert.ok((await page.locator('body').innerText()).match(/bentrok|overlap|bertumpang/i));
        await page.goto(base+`/admin/doctor-schedules/${session.id}/edit`); await page.locator('#quota').fill('15');
        await page.locator('input[type=checkbox][name=is_active]').uncheck(); await submit(page,'Simpan perubahan','**/admin/doctor-schedules');
        const persisted=sql.prepare('SELECT quota,is_active FROM doctor_schedules WHERE id=?').get(session.id); assert.equal(persisted.quota,15);assert.equal(persisted.is_active,0);
        return {schedule_id:session.id,overlap_rejected:true,deactivated:true};
    });
    const rp=reception.page;
    await check('ADM-10','F09','Receptionist','Pasien: create melalui menu dan buka ulang identitas',['Menu Pasien','Pasien baru','Isi identitas dummy','Simpan','Edit alamat'],async()=>{
        await rp.locator('aside nav').getByRole('link',{name:'Pasien',exact:true}).click();
        await rp.getByRole('link',{name:/Pasien baru|Tambah pasien/}).click();
        await rp.locator('#name').fill(prefix+'Pasien'); await rp.locator('#birth_date').fill('1992-04-12');
        await rp.locator('#gender').selectOption('FEMALE'); await rp.locator('#phone').fill('081299990001');
        await rp.locator('#email').fill('deep.patient@clinicos.test');
        await submit(rp,'Simpan pasien',/\/receptionist\/patients\/\d+$/);
        const patient=sql.prepare('SELECT id,medical_record_number FROM patients WHERE name=?').get(prefix+'Pasien');assert.ok(patient); flags.patientId=patient.id;
        await rp.getByRole('link',{name:'Edit pasien',exact:true}).click();await rp.locator('#address').fill('Jalan Uji Fungsional 13');
        await submit(rp,'Simpan pasien',/\/receptionist\/patients\/\d+$/);
        assert.equal(sql.prepare('SELECT address FROM patients WHERE id=?').get(patient.id).address,'Jalan Uji Fungsional 13');
        return {patient_id:patient.id,number_created:true,edit_persisted:true};
    });
    await check('ADM-11','F09','Receptionist','Pasien: telepon berformat salah ditolak dan error membantu',['Pasien baru','Isi nama/lahir/gender valid','Telepon abc','Simpan'],async()=>{
        await rp.goto(base+'/receptionist/patients/create'); await rp.locator('#name').fill(prefix+'Telepon Invalid');
        await rp.locator('#birth_date').fill('1991-01-01'); await rp.locator('#gender').selectOption('MALE'); await rp.locator('#phone').fill('abc');
        await submit(rp,'Simpan pasien');await rp.waitForTimeout(400);
        const stored=sql.prepare('SELECT id FROM patients WHERE name=?').get(prefix+'Telepon Invalid');
        const screenshot=await shot(rp,'ADM-11-invalid-phone');
        assert.equal(Boolean(stored),false,`Telepon abc diterima dan pasien tersimpan; bukti ${screenshot}`);
        return {invalid_phone_rejected:true};
    });
    await check('ADM-12','F10','Receptionist','Duplikat: kandidat tampil, tanpa persetujuan tidak menyimpan',['Buat pasien dengan telepon pasien QA yang ada','Periksa kandidat','Simpan tanpa review','Centang review dan simpan'],async()=>{
        await rp.goto(base+'/receptionist/patients/create'); await rp.locator('#name').fill(prefix+'Anggota Keluarga');
        await rp.locator('#birth_date').fill('2001-01-01'); await rp.locator('#gender').selectOption('MALE');await rp.locator('#phone').fill('081299990001');
        await rp.getByRole('button',{name:'Periksa kembali'}).click();
        await rp.locator('[data-duplicate-review] a').first().waitFor({state:'visible'});
        await submit(rp,'Simpan pasien');await rp.waitForTimeout(300);
        assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients WHERE name=?').get(prefix+'Anggota Keluarga').n,0);
        await rp.getByRole('button',{name:'Periksa kembali'}).click(); await rp.locator('input[name=duplicate_reviewed]').check();
        await submit(rp,'Simpan pasien',/\/receptionist\/patients\/\d+$/);
        assert.equal(sql.prepare('SELECT COUNT(*) n FROM patients WHERE name=?').get(prefix+'Anggota Keluarga').n,1);
        return {review_required:true,shared_phone_supported:true};
    });
    await check('ADM-13','F09','Receptionist','Pencarian pasien: hasil dan empty state dari filter UI',['Menu Pasien','Cari nama QA','Cari teks tanpa kecocokan','Periksa empty state'],async()=>{
        await rp.goto(base+'/receptionist/patients'); await rp.locator('[name=search]').fill(prefix+'Pasien');
        await rp.getByRole('button',{name:/Cari|Terapkan/}).click();await rp.waitForTimeout(200);
        assert.ok((await rp.locator('main').innerText()).includes(prefix+'Pasien'));
        await rp.locator('[name=search]').fill('TIDAK_ADA_884499');await rp.getByRole('button',{name:/Cari|Terapkan/}).click();await rp.waitForTimeout(200);
        assert.ok((await rp.locator('main').innerText()).match(/tidak|belum/i));
        return {search:true,empty_state:true};
    });
    await check('ADM-14','F05','Clinic Admin','Profil klinik: edit kontak lalu verifikasi halaman publik',['Profil klinik','Ubah deskripsi','Simpan','Buka website publik'],async()=>{
        await page.locator('aside nav').getByRole('link',{name:'Profil klinik',exact:true}).click();
        await page.locator('#description').fill('Deskripsi audit manusia lintas halaman.');await submit(page,'Simpan perubahan');await page.waitForTimeout(400);
        assert.equal(sql.prepare("SELECT description FROM clinics WHERE slug='klinik-sehat-sentosa'").get().description,'Deskripsi audit manusia lintas halaman.');
        const guest=await client('guest');await guest.page.goto(base+'/clinic/klinik-sehat-sentosa');
        assert.ok((await guest.page.locator('body').innerText()).includes('Deskripsi audit manusia lintas halaman.'));
        await guest.context.close(); return {cross_page_updated:true};
    });
    await check('ADM-15','F24','Clinic Admin','Audit aktivitas: tindakan admin muncul dan filter dapat digunakan',['Menu Audit aktivitas','Cari event layanan/staf','Gunakan filter'],async()=>{
        await page.locator('aside nav').getByRole('link',{name:'Audit aktivitas'}).click();
        const text=await page.locator('main').innerText(); assert.ok(/service.created|staff.created|patient.created/.test(text));
        await inspect(page,'Clinic Admin','Audit aktivitas setelah mutasi');
        return {events_present:true};
    });
    saveState(flags);
} finally { saveState(flags); await finish('2-administration-patients'); }
