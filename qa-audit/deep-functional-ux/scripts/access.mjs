import assert from 'node:assert/strict';
import { readFileSync,writeFileSync,existsSync } from 'node:fs';
import {join} from 'node:path';
import { base, client, check, sql, submit, shot, inspect, saveState, finish, credential, runtime, untested } from './common.mjs';

// Record dummy array-transport mail privately so signed links can be followed through UI.
const router=join(runtime.root,'router.php');
let source=readFileSync(router,'utf8');
const hook=`
Illuminate\\Support\\Facades\\Event::listen(Illuminate\\Mail\\Events\\MessageSent::class, function ($event) {
    file_put_contents(getenv('AUDIT_ROOT').'/mail.jsonl', json_encode(['to'=>array_map(fn($address)=>$address->getAddress(),$event->message->getTo()),'html'=>$event->message->getHtmlBody(),'text'=>$event->message->getTextBody()]).PHP_EOL, FILE_APPEND | LOCK_EX);
});
`;
if(!source.includes('/mail.jsonl')) writeFileSync(router,source.replace('$kernel->bootstrap();','$kernel->bootstrap();'+hook));
function inbox(email,part) {
    const file=join(runtime.root,'mail.jsonl');if(!existsSync(file)) return null;
    for(const m of readFileSync(file,'utf8').trim().split('\n').map(JSON.parse).reverse()) {
        if(!m.to.includes(email)) continue;
        const links=(m.html||m.text||'').match(/http:\/\/127\.0\.0\.1:\d+\/[^\s"<>]+/g)||[];
        const link=links.find(url=>url.includes(part));if(link) return link.replaceAll('&amp;','&');
    }
    return null;
}
async function verify(page,email) {
    await page.getByRole('button',{name:'Kirim ulang email verifikasi'}).click();
    await page.waitForTimeout(400);const link=inbox(email,'/verify-email/');assert.ok(link,'Private dummy mail capture unavailable');
    await page.goto(link);await page.waitForURL(url=>url.pathname.includes('/dashboard')||url.pathname==='/onboarding/clinic');
}
const ownerEmail='deep.owner@clinicos.test',patientEmail='deep.account@clinicos.test';
try {
    const c=await client('guest');const p=c.page;
    await check('AUTH-01','F01','Guest','Login password salah memberikan feedback dan tetap dapat dikoreksi',['Buka masuk pasien','Isi email valid/password salah','Klik masuk','Periksa error','Koreksi password'],async()=>{
        await p.goto(base+'/login');await p.locator('#email').fill('patient@clinicos.test');await p.locator('#password').fill('SengajaSalah998');
        await p.locator('button[type=submit]').click();await p.waitForTimeout(500);
        assert.equal(new URL(p.url()).pathname,'/login');assert.ok((await p.locator('body').innerText()).match(/match|cocok|kredensial/i));
        await p.locator('#password').fill(credential);await p.locator('button[type=submit]').click();await p.waitForURL('**/patient/dashboard');
        return {wrong_rejected:true,recovery:true};
    });
    await c.context.close();
    const owner=await client('guest');const op=owner.page;
    await check('AUTH-02','F02','Guest → Clinic Owner','Registrasi owner, terms, verifikasi email dan onboarding melalui UI',['Landing','Daftarkan klinik','Isi akun dummy','Setujui terms','Submit','Kirim ulang verifikasi','Buka tautan mail dummy'],async()=>{
        await op.goto(base+'/');await op.getByRole('link',{name:/Daftar klinik|Daftarkan klinik|Mulai gratis|Mulai sekarang/i}).first().click();
        if(new URL(op.url()).pathname!=='/register/clinic') await op.goto(base+'/register/clinic');
        await op.locator('#name').fill('QA Deep Owner');await op.locator('#email').fill(ownerEmail);await op.locator('#password').fill(credential);await op.locator('#password_confirmation').fill(credential);await op.locator('#accept_terms').check();
        await submit(op,'Buat akun pemilik');await op.waitForURL('**/verify-email');await verify(op,ownerEmail);
        assert.equal(new URL(op.url()).pathname,'/onboarding/clinic');
        const user=sql.prepare('SELECT id,account_type,email_verified_at FROM users WHERE email=?').get(ownerEmail);assert.equal(user.account_type,'clinic_owner');assert.ok(user.email_verified_at);
        saveState({onboardingOwnerId:user.id});await inspect(op,'new-owner','Onboarding klinik baru');
        return {registered:true,verification_link_followed:true,delivery:'private array mail capture'};
    });
    await check('AUTH-03','F04','Clinic Owner','Buat klinik dan aktivasi Free dari onboarding',['Isi identitas klinik','Buat klinik','Pilih Free','Periksa dashboard dan kunjungi ulang onboarding'],async()=>{
        assert.equal(new URL(op.url()).pathname,'/onboarding/clinic','BLOCKED: registrasi owner gagal');
        await op.locator('#name').fill('QA Klinik Baru');await op.locator('#slug').fill('qa-klinik-baru');await op.locator('#phone').fill('081299992222');
        await submit(op,'Buat klinik','**/onboarding/plans');await inspect(op,'new-owner','Paket onboarding');
        const freeForm=op.locator('form[action$="/onboarding/plans/free"]');await freeForm.getByRole('button').click();await op.waitForURL('**/admin/dashboard');
        const row=sql.prepare("SELECT s.status,c.id FROM subscriptions s JOIN clinics c ON c.id=s.clinic_id WHERE c.slug='qa-klinik-baru'").get();assert.equal(row.status,'free');
        saveState({onboardingClinicId:row.id});return {created:true,free_active:true};
    });
    await check('AUTH-04','F04','Clinic Owner','Trial dapat dimulai sekali dan paket trial terlihat',['Buka subscription','Pilih Team trial','Periksa state trial dan ketidaktersediaan pemakaian ulang'],async()=>{
        await op.goto(base+'/onboarding/plans');
        const form=op.locator('form[action$="/onboarding/plans/trial"]');await form.getByRole('button').click();await op.waitForURL('**/admin/dashboard');
        const row=sql.prepare("SELECT status,trial_used_at FROM subscriptions WHERE clinic_id=(SELECT id FROM clinics WHERE slug='qa-klinik-baru')").get();assert.equal(row.status,'trialing');assert.ok(row.trial_used_at);
        await op.goto(base+'/onboarding/plans');assert.equal(await op.locator('form[action$="/onboarding/plans/trial"] button:enabled').count(),0);
        return {trial:true,reuse_hidden:true};
    });
    await owner.context.close();
    const patient=await client('guest');const pp=patient.page;
    await check('AUTH-05','F02','Guest → Patient','Registrasi pasien termasuk password mismatch dan pemulihan',['Buka daftar pasien','Isi konfirmasi password salah','Submit dan periksa error','Koreksi','Verifikasi melalui tautan dummy'],async()=>{
        await pp.goto(base+'/register');await pp.locator('#name').fill('QA Deep Akun Pasien');await pp.locator('#email').fill(patientEmail);await pp.locator('#password').fill(credential);await pp.locator('#password_confirmation').fill('Mismatch-7722!');await pp.locator('#accept_terms').check();
        await submit(pp,'Buat akun');await pp.waitForTimeout(400);assert.equal(sql.prepare('SELECT COUNT(*) n FROM users WHERE email=?').get(patientEmail).n,0);
        await pp.locator('#password_confirmation').fill(credential);await submit(pp,'Buat akun');await pp.waitForURL('**/verify-email');await verify(pp,patientEmail);
        assert.equal(new URL(pp.url()).pathname,'/patient/dashboard');return {mismatch_rejected:true,registered_verified:true};
    });
    await check('AUTH-06','F03','Patient','Profil: ubah nama dan password, password lama salah lalu koreksi',['Kelola profil','Ubah nama','Simpan','Isi password lama salah','Periksa error','Isi password benar'],async()=>{
        await pp.goto(base+'/profile');await pp.locator('#name').fill('QA Deep Akun Pasien Edit');
        const form=pp.locator('form').filter({has:pp.locator('#name')});await form.getByRole('button',{name:/Simpan|Save/}).click();await pp.waitForTimeout(400);
        assert.equal(sql.prepare('SELECT name FROM users WHERE email=?').get(patientEmail).name,'QA Deep Akun Pasien Edit');
        await pp.locator('#current_password').fill('SalahLama001!');await pp.locator('#password').fill('Audit-Changed-773!');await pp.locator('#password_confirmation').fill('Audit-Changed-773!');
        const passForm=pp.locator('form').filter({has:pp.locator('#current_password')});await passForm.getByRole('button',{name:/Simpan|Save/}).click();await pp.waitForTimeout(400);
        assert.ok((await passForm.innerText()).match(/incorrect|salah/i));
        await pp.locator('#current_password').fill(credential);await pp.locator('#password').fill('Audit-Changed-773!');await pp.locator('#password_confirmation').fill('Audit-Changed-773!');await passForm.getByRole('button',{name:/Simpan|Save/}).click();await pp.waitForTimeout(500);
        return {name_persisted:true,password_update_submitted:true};
    });
    await patient.context.close();
    const reset=await client('guest');const rp=reset.page;
    await check('AUTH-07','F01','Patient','Lupa/reset password: tautan dummy, validasi, login password baru',['Login','Lupa kata sandi','Kirim email','Buka tautan lokal','Isi password baru','Masuk ulang'],async()=>{
        await rp.goto(base+'/login');await rp.getByRole('link',{name:/Lupa kata sandi/}).click();await rp.locator('#email').fill(patientEmail);await submit(rp,'Kirim tautan reset');await rp.waitForTimeout(400);
        const link=inbox(patientEmail,'/reset-password/');assert.ok(link,'Private reset mail unavailable');await rp.goto(link);
        await rp.locator('#password').fill(credential);await rp.locator('#password_confirmation').fill(credential);await submit(rp,'Simpan kata sandi baru');await rp.waitForURL('**/login');
        const logged=await client('patient',patientEmail);assert.equal(new URL(logged.page.url()).pathname,'/patient/dashboard');await logged.context.close();
        return {reset_link_followed:true,login_new_password:true,secret_links:'kept only in disposable private runtime'};
    });
    await reset.context.close();
    const staff=await client('new-doctor','deep.doctor@clinicos.test');
    await check('AUTH-08','F06','Doctor','Staf dokter baru dapat memverifikasi dan bekerja',['Login dokter baru','Kirim ulang verifikasi','Buka tautan dummy','Periksa dashboard'],async()=>{
        await verify(staff.page,'deep.doctor@clinicos.test');assert.equal(new URL(staff.page.url()).pathname,'/doctor/dashboard');return {staff_verification:true};
    });
    await staff.context.close();
    untested('AUTH-GOOGLE','F01','Patient','OAuth Google end-to-end','BLOCKED: kredensial eksternal sengaja dikosongkan; tidak memakai akun pengguna.','BLOCKED');
} finally {await finish('3-auth-onboarding-profile');}
