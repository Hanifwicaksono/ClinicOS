import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import { base, client, check, sql, submit, shot, inspect, finish, state, evidence, pages, resultsFile, results, cleanUrl } from './common.mjs';
import { join } from 'node:path';

sql.prepare("UPDATE users SET account_type=NULL WHERE email='operational.admin@clinicos.test'").run();
try {
    await check('RETEST-01','F01','Clinic Admin (non-owner)','Login admin operasional dengan fixture tipe akun sah',['Perbaiki tipe akun fixture menjadi null sesuai domain','Login','Periksa menu dan pembatasan subscription'],async()=>{
        const c=await client('operational-admin','operational.admin@clinicos.test');
        assert.equal(new URL(c.page.url()).pathname,'/admin/dashboard');
        const snap=await inspect(c.page,'operational-admin','Dashboard admin operasional');
        assert.equal(snap.links.some(l=>l.target.endsWith('/subscription/payments')),false);
        for(const link of await c.page.locator('aside nav a').evaluateAll(nodes=>nodes.map(n=>({text:n.textContent.trim(),href:n.href})))) {
            await c.page.goto(link.href); await inspect(c.page,'operational-admin',link.text);
        }
        await c.page.getByRole('button',{name:'Keluar',exact:true}).click(); await c.page.waitForURL(url=>url.pathname==='/');
        await c.context.close();return {login:true,owner_actions_hidden:true};
    });
    const owner=await client('admin');const page=owner.page;
    await check('RETEST-02','F08','Clinic Admin','Jadwal: pesan bertabrakan dan edit/nonaktif tersimpan',['Buka tambah jadwal dokter QA','Buat sesi bertabrakan','Periksa pesan bertabrakan','Edit sesi yang ada dan nonaktifkan'],async()=>{
        const s=state();assert.ok(s.scheduleId);
        await page.goto(base+'/admin/doctor-schedules/create'); await page.locator('#doctor_id').selectOption(String(s.doctorId));
        await page.locator('#day_of_week').selectOption('1');await submit(page,'Tambah jadwal');await page.waitForTimeout(300);
        assert.ok((await page.locator('body').innerText()).includes('bert abrakan'.replace(' ','')));
        await shot(page,'RETEST-02-overlap');
        await page.goto(base+`/admin/doctor-schedules/${s.scheduleId}/edit`);await page.locator('#quota').fill('15');await page.locator('input[type=checkbox][name=is_active]').uncheck();
        await submit(page,'Simpan perubahan','**/admin/doctor-schedules');
        assert.equal(sql.prepare('SELECT is_active FROM doctor_schedules WHERE id=?').get(s.scheduleId).is_active,0);
        return {overlap_rejected:true,edit_deactivate:true};
    });
    await check('RETEST-03','F24','Clinic Admin','Audit: filter action menemukan event yang dibuat melalui UI',['Menu Audit aktivitas','Pilih service.created','Terapkan filter','Periksa baris'],async()=>{
        await page.locator('aside nav').getByRole('link',{name:'Audit aktivitas'}).click();
        await page.locator('select[name=action]').selectOption('service.created');
        await page.getByRole('button',{name:'Terapkan filter'}).click();await page.waitForTimeout(200);
        assert.ok((await page.locator('tbody').innerText()).includes('service.created'));
        return {action_filter:true,screenshot:await shot(page,'RETEST-03-audit-filter')};
    });
    const correction={ 'DISC-LOGIN-operational-admin':['RETEST-01','Invalid account_type in audit fixture; domain only patient/clinic_owner/null.'], 'ADM-09':['RETEST-02','Assertion missed actual Indonesian word bertabrakan; create and rejection already worked.'], 'ADM-15':['RETEST-03','First page contained other recent audit actions; action filter is the correct UI verification.'] };
    for(const r of results) if(correction[r.id]) {r.superseded_by=correction[r.id][0];r.harness_note=correction[r.id][1];}
    writeFileSync(resultsFile,JSON.stringify(results,null,2));
} finally { await finish('2b-harness-corrections'); }
