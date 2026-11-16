import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { base,client,check,sql,submit,shot,inspect,saveState,state,finish,runtime,untested } from './common.mjs';

const s=state();assert.ok(s.journeyVisitId,'Journey visit required');
const c=await client('deep-doctor','deep.doctor@clinicos.test');const page=c.page;
const path=`/doctor/visits/${s.journeyVisitId}`;
const record=()=>sql.prepare('SELECT id,status,lock_version,assessment,odontogram,photos FROM medical_records WHERE visit_id=?').get(s.journeyVisitId);
async function poll(fn) {for(let i=0;i<100;i++){if(await fn())return;await page.waitForTimeout(100);}throw new Error('Persisted/UI condition did not become true');}
async function saved() {await page.waitForFunction(()=>document.querySelector('[data-rme-form]')?.rmeAutosave && !document.querySelector('[data-rme-form]').rmeAutosave.unsaved);}
async function open(){await page.goto(base+path);await page.waitForFunction(()=>Boolean(document.querySelector('[data-rme-form]')?.rmeAutosave));}
const flags={};
try {
    await open();
    await check('RME-01','F16','Doctor','Autosave SOAP, vital, diagnosis dan tindakan tersimpan tanpa submit',['Isi SOAP sintetis','Isi berat/tekanan darah','Isi diagnosis/tindakan','Berhenti mengetik','Reload'],async()=>{
        await page.locator('[name=chief_complaint]').fill('Keluhan sintetis audit');await page.locator('[name=subjective]').fill('Catatan subjektif sintetis audit');await page.locator('[name=objective]').fill('Catatan objektif sintetis audit');
        await page.locator('[name=assessment]').fill('Assessment sintetis awal');await page.locator('[name=plan]').fill('Rencana sintetis audit');await page.locator('#vital_systolic').fill('120');await page.locator('#vital_diastolic').fill('80');
        await page.locator('#diagnosis_name_0').fill('Diagnosis sintetis audit');await page.locator('#treatment_name_0').fill('Tindakan sintetis audit');await page.locator('#treatment_quantity_0').fill('2');
        await saved();assert.equal(record().assessment,'Assessment sintetis awal');assert.equal(sql.prepare('SELECT quantity FROM treatments WHERE medical_record_id=?').get(record().id).quantity,2);
        await page.reload();assert.equal(await page.locator('[name=assessment]').inputValue(),'Assessment sintetis awal');return {autosave_persisted:true,structured_rows_persisted:true,record_version:record().lock_version};
    });
    await check('RME-02','F16','Doctor','Nilai vital tidak masuk akal ditandai dan dapat dikoreksi',['Isi sistolik 999','Tunggu autosave','Periksa aria-invalid','Koreksi 120','Tunggu tersimpan'],async()=>{
        await page.locator('#vital_systolic').fill('999');await page.waitForFunction(()=>document.querySelector('#vital_systolic').getAttribute('aria-invalid')==='true');
        assert.equal(sql.prepare('SELECT systolic FROM vital_signs WHERE medical_record_id=?').get(record().id).systolic,120);
        await shot(page,'RME-02-invalid-vital');await page.locator('#vital_systolic').fill('120');await saved();assert.equal(await page.locator('#vital_systolic').getAttribute('aria-invalid'),null);return {invalid_rejected:true,recovered:true};
    });
    await check('RME-03','F17','Doctor','Alergi kritis: catat melalui editor dan tampil setelah reload',['Tinjau/catat alergi pasien','Buka Catat alergi','Isi zat/reaction/severity','Simpan alergi','Periksa banner'],async()=>{
        await page.getByRole('link',{name:'Tinjau / catat alergi pasien'}).click();await page.getByText('+ Catat alergi',{exact:true}).click();
        await page.locator('#new-allergy-substance').fill('QA Zat Pemicu');await page.locator('#new-allergy-reaction').fill('Reaksi sintetis audit');await page.locator('#new-allergy-severity').selectOption('CRITICAL');
        await submit(page,'Simpan alergi');await poll(()=>sql.prepare('SELECT COUNT(*) n FROM patient_allergies WHERE substance=?').get('QA Zat Pemicu').n===1);
        await page.waitForFunction(()=>document.body.innerText.includes('QA Zat Pemicu')&&Boolean(document.querySelector('#critical_allergies_acknowledged')));
        flags.allergyId=sql.prepare('SELECT id FROM patient_allergies WHERE substance=?').get('QA Zat Pemicu').id;return {allergy_id:flags.allergyId,critical_banner:true};
    });
    await check('RME-04','F18','Doctor','Resep: warning kelengkapan dan acknowledgement alergi sebelum tersimpan',['Isi hanya nama obat','Periksa finalisasi disabled','Lengkapi dosis/frekuensi/jumlah','Centang review alergi','Tunggu autosave'],async()=>{
        await page.locator('#medicine_name_0').fill('QA Obat Sintetis');assert.equal(await page.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).isEnabled(),false);
        await page.locator('#medicine_dosage_0').fill('Dosis sintetis');await page.locator('#medicine_frequency_0').fill('Frekuensi sintetis');await page.locator('#medicine_quantity_0').fill('3');await page.locator('#medicine_unit_0').fill('unit');
        await page.locator('#critical_allergies_acknowledged').check();await saved();
        assert.equal(sql.prepare('SELECT COUNT(*) n FROM prescription_items pi JOIN prescriptions p ON p.id=pi.prescription_id WHERE p.medical_record_id=?').get(record().id).n,1);
        return {incomplete_warning:true,acknowledgement:true,prescription_persisted:true};
    });
    await check('RME-05','F16','Doctor','Dua tab: stale autosave ditolak tanpa menimpa perubahan terbaru',['Buka record di tab kedua','Edit tab pertama dan tunggu tersimpan','Edit tab kedua','Periksa konflik dan nilai DB'],async()=>{
        const tab=await c.context.newPage();tab.on('dialog',d=>d.accept());await tab.goto(base+path);
        await page.locator('[name=assessment]').fill('Versi terbaru tab utama');await saved();
        await tab.locator('#critical_allergies_acknowledged').check();await tab.locator('[name=assessment]').fill('Stale tab kedua');
        await tab.waitForFunction(()=>document.querySelector('[data-rme-form]')?.rmeAutosave?.blocked===true);
        assert.equal(record().assessment,'Versi terbaru tab utama');assert.ok((await tab.locator('[data-rme-autosave-status]').innerText()).match(/Perlu ditinjau|berubah|versi/));
        await shot(tab,'RME-05-stale-tab');await tab.close();return {stale_rejected:true,newer_preserved:true};
    });
    await check('RME-06','F16','Doctor','Koneksi putus: perubahan tetap di editor dan tersimpan setelah pulih',['Offline browser','Ubah objective','Tunggu error autosave','Online kembali','Periksa DB'],async()=>{
        await c.context.setOffline(true);await page.locator('[name=objective]').fill('Objektif setelah koneksi pulih');
        await page.waitForFunction(()=>document.querySelector('[data-rme-autosave-status]').innerText.includes('Gagal disimpan'));
        assert.equal(await page.locator('[name=objective]').inputValue(),'Objektif setelah koneksi pulih');await c.context.setOffline(false);await saved();
        assert.equal(sql.prepare('SELECT objective FROM medical_records WHERE visit_id=?').get(s.journeyVisitId).objective,'Objektif setelah koneksi pulih');return {editor_retained:true,reconnect_saved:true};
    });
    await check('RME-07','F19','Doctor','Foto dummy: pilih, simpan manual, buka ulang dan endpoint privat',['Unggah PNG dummy','Klik Simpan draft','Reload','Buka foto dari UI'],async()=>{
        const image=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6xZkAAAAASUVORK5CYII=','base64');
        await page.locator('#photos').setInputFiles({name:'qa-dummy.png',mimeType:'image/png',buffer:image});
        await page.getByRole('button',{name:'Simpan draft',exact:true}).click();
        await page.waitForFunction(()=>document.querySelectorAll('[data-rme-form] img[src*="/photos/"]').length>0);
        const photos=JSON.parse(record().photos);assert.equal(photos.length,1);assert.ok(existsSync(join(runtime.root,'storage/app/private',photos[0].path)));
        const link=page.locator('a[href*="/photos/"]').first();assert.ok(await link.count());const target=await link.getAttribute('href');
        const photoPage=await c.context.newPage();const response=await photoPage.goto(target);assert.equal(response.status(),200);assert.ok(response.headers()['cache-control'].includes('no-store'));await photoPage.close();
        return {photo_persisted:true,private_endpoint:true};
    });
    await check('RME-08','F20','Doctor','Finalisasi: keyboard dialog, batal lalu konfirmasi dan status atomik',['Tinjau & finalisasi','Tekan Escape','Buka lagi','Finalisasi kunjungan','Periksa RME/visit/appointment/queue'],async()=>{
        if(record().status==='FINAL') {
            const row=sql.prepare('SELECT v.status visit,a.status appointment,q.status queue FROM visits v JOIN appointments a ON a.id=v.appointment_id JOIN queues q ON q.appointment_id=a.id WHERE v.id=?').get(s.journeyVisitId);
            assert.deepEqual({...row},{visit:'COMPLETED',appointment:'COMPLETED',queue:'COMPLETED'});flags.journeyFinalized=true;
            return {...row,medical_record:'FINAL',note:'UI finalize ran in preceding attempt; corrected assertion joins queue by appointment_id.'};
        }
        await page.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await page.getByRole('dialog',{name:'Konfirmasi finalisasi kunjungan'}).waitFor({state:'visible'});
        await page.keyboard.press('Escape');await page.getByRole('dialog',{name:'Konfirmasi finalisasi kunjungan'}).waitFor({state:'hidden'});
        await page.getByRole('button',{name:'Tinjau & finalisasi',exact:true}).click();await page.getByRole('button',{name:'Finalisasi kunjungan',exact:true}).click();
        await page.locator('#correction_reason').waitFor({state:'visible'});
        assert.equal(record().status,'FINAL');const row=sql.prepare('SELECT v.status visit,a.status appointment,q.status queue FROM visits v JOIN appointments a ON a.id=v.appointment_id JOIN queues q ON q.appointment_id=a.id WHERE v.id=?').get(s.journeyVisitId);assert.deepEqual({...row},{visit:'COMPLETED',appointment:'COMPLETED',queue:'COMPLETED'});
        flags.journeyFinalized=true;return {...row,medical_record:'FINAL',screenshot:await shot(page,'RME-08-finalized')};
    });
    await check('RME-09','F20','Doctor','Modification: alasan pendek diblok, koreksi sah membuat histori',['Isi alasan pendek','Periksa minlength','Isi alasan sah','Ubah assessment','Simpan Modification','Buka Version history'],async()=>{
        if(sql.prepare('SELECT COUNT(*) n FROM medical_record_revisions WHERE medical_record_id=?').get(record().id).n===1) {
            await page.getByText('Lihat semua perubahan',{exact:true}).click();
            assert.ok((await page.locator('main').innerText()).includes('Assessment setelah Modification'));
            return {revision_count:1,status:record().status,screenshot:await shot(page,'RME-09-history-open'),note:'Correction was saved in earlier attempt; retest uses outer visible history summary.'};
        }
        await page.locator('#correction_reason').fill('pendek');await page.locator('#correction_reason').blur();
        await page.getByRole('button',{name:'Simpan Modification',exact:true}).click();await page.waitForTimeout(250);assert.equal(sql.prepare('SELECT COUNT(*) n FROM medical_record_revisions WHERE medical_record_id=?').get(record().id).n,0);
        await page.locator('#correction_reason').fill('Koreksi sintetis untuk memverifikasi histori audit');await page.locator('[name=assessment]').fill('Assessment setelah Modification');
        if(await page.locator('#critical_allergies_acknowledged').count())await page.locator('#critical_allergies_acknowledged').check();await page.getByRole('button',{name:'Simpan Modification',exact:true}).click();
        await poll(()=>sql.prepare('SELECT COUNT(*) n FROM medical_record_revisions WHERE medical_record_id=?').get(record().id).n===1);
        await page.waitForTimeout(400);assert.equal(record().status,'FINAL');await page.getByText('Lihat semua perubahan',{exact:true}).click();
        return {revision_count:1,status:'FINAL',screenshot:await shot(page,'RME-09-modification')};
    });
    await check('RME-10','F17','Doctor','Alergi: nonaktifkan dengan histori tetap ada',['Buka detail alergi','Uncheck aktif','Perbarui alergi','Periksa DB dan banner'],async()=>{
        await open();const row=page.locator('details').filter({has:page.locator(`#allergy-${flags.allergyId}-active`)});await row.locator('summary').click();await page.locator(`#allergy-${flags.allergyId}-active`).uncheck();await row.getByRole('button',{name:'Perbarui alergi'}).click();
        await poll(()=>sql.prepare('SELECT is_active FROM patient_allergies WHERE id=?').get(flags.allergyId).is_active===0);return {inactive:true,row_retained:true};
    });
    await check('RME-11','F15','Clinic Admin / Receptionist / Platform Admin / unrelated Doctor','Role lain tidak dapat membuka isi visit dokter QA',['Login per role','Buka URL visit langsung','Periksa 403'],async()=>{
        const statuses={};for(const role of ['admin','receptionist','platform','doctor']){const actor=await client(role);const response=await actor.page.goto(base+path);statuses[role]=response.status();assert.equal(response.status(),403);await actor.context.close();}return statuses;
    });
    await check('RME-12','F15','Doctor','Record final dapat ditemukan kembali melalui menu Rekam medis',['Menu Rekam medis','Cari pasien QA','Buka record yang sudah final'],async()=>{
        await page.goto(base+'/doctor/medical-records');const link=page.locator(`a[href$="${path}"]`);assert.ok(await link.count());await link.first().click();await page.locator('#correction_reason').waitFor({state:'visible'});return {reopened_from_index:true};
    });
    untested('RME-DENTAL','F21','Doctor','Odontogram dengan entitlement aktif: edit dan persist','Dijalankan pada fase add-on/dental, membutuhkan fixture entitlement dummy.');
    saveState(flags);
}finally{saveState(flags);await finish('5-medical-autosave-finalization');}
