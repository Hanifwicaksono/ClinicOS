import assert from 'node:assert/strict';
import { createHash, randomUUID } from 'node:crypto';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';

export async function auditWorkspacePresentation({ sql, call, evaluate, go, login, waitFor, directory, fixtures }) {
    const { visit, child, documentId, followId, patientBill, closed, reportId, absence, handoffId } = fixtures;
    const clinic = sql.prepare('SELECT * FROM clinics WHERE id=?').get(child.clinic_id);
    const staff = sql.prepare("SELECT id,clinic_id FROM users WHERE email='admin@clinicos.test'").get();
    const patientUser = sql.prepare("SELECT id,email_verified_at FROM users WHERE email='patient@clinicos.test'").get();
    const appointment = sql.prepare("SELECT a.id FROM appointments a WHERE a.clinic_id=? AND a.status='BOOKED' AND NOT EXISTS(SELECT 1 FROM visits v WHERE v.appointment_id=a.id) AND NOT EXISTS(SELECT 1 FROM appointment_replacements r WHERE r.old_appointment_id=a.id) ORDER BY a.id DESC LIMIT 1").get(clinic.id);
    const service = sql.prepare('SELECT id FROM services WHERE clinic_id=? LIMIT 1').get(clinic.id);
    const schedule = sql.prepare('SELECT id FROM doctor_schedules WHERE doctor_id=? LIMIT 1').get(child.doctor_id);
    const doctorUser = sql.prepare('SELECT user_id FROM doctors WHERE id=?').get(child.doctor_id);
    const payments = sql.prepare('SELECT id FROM payments WHERE clinic_id=? ORDER BY id').all(clinic.id);
    const publicToken=randomUUID();
    const originalTokenHash=sql.prepare('SELECT access_token_hash FROM appointments WHERE id=?').get(appointment.id).access_token_hash;
    sql.prepare('UPDATE appointments SET access_token_hash=? WHERE id=?').run(createHash('sha256').update(publicToken).digest('hex'),appointment.id);
    const publicOnly=process.env.CLINICOS_PRESENTATION_PUBLIC_ONLY==='1';
    const widths=[375,768,1024,1440,1920,2560];
    const cases = [];
    const failures = [];
    const baselineColumns = new Map();

    async function inspect(label, scenario, capture = true) {
        for (const width of widths) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            await evaluate('new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))');
            const result = await evaluate(`(() => {
                const visible=node=>{const r=node.getBoundingClientRect();return r.width>1&&r.height>1&&r.right>0&&r.left<innerWidth;};
                const label=node=>(node.tagName+' '+(node.getAttribute('name')||node.getAttribute('data-status')||node.innerText||'')).slice(0,85);
                const problems=[];
                if(document.documentElement.scrollWidth>innerWidth+1) problems.push('page-overflow');
                for(const node of document.querySelectorAll('main p,main h1,main h2,main h3,main dd,main td,main dt,main button,main .clinic-input,header button')) {
                    if(!visible(node)||node.matches('input,textarea,select')||getComputedStyle(node).textOverflow==='ellipsis'||Number(getComputedStyle(node).webkitLineClamp)>0) continue;
                    if(node.scrollWidth>node.clientWidth+2) problems.push('overflow: '+label(node));
                }
                for(const node of document.querySelectorAll('main button,main th,main td.text-right a,[data-queue-number],.clinic-queue-number,[data-status]')) {
                    if(!visible(node)) continue;
                    const walker=document.createTreeWalker(node,NodeFilter.SHOW_TEXT);
                    while(walker.nextNode()) {
                        const text=walker.currentNode, value=text.textContent.trim();
                        if(!value||/\\s/.test(value)||value.length>30||!visible(text.parentElement)) continue;
                        const range=document.createRange(),start=text.textContent.indexOf(value);
                        range.setStart(text,start);range.setEnd(text,start+value.length);
                        if(range.getClientRects().length>1) problems.push('split-word: '+value);
                    }
                }
                for(const node of document.querySelectorAll('.clinic-queue-label')) {
                    if(visible(node)&&node.getBoundingClientRect().height>parseFloat(getComputedStyle(node).lineHeight)*2+1) problems.push('queue-label-exceeds-two-lines');
                }
                for(const area of document.querySelectorAll('.clinic-table-scroll')) {
                    if(area.scrollWidth>area.clientWidth+2) {
                        const original=area.scrollLeft;area.scrollLeft=area.scrollWidth;
                        if(area.scrollLeft<=0) problems.push('table-cannot-scroll');
                        const last=area.querySelector('thead tr')?.lastElementChild;
                        if(last&&last.getBoundingClientRect().right>area.getBoundingClientRect().right+2) problems.push('last-table-column-unreachable');
                        area.scrollLeft=original;
                    }
                }
                const columns=[...document.querySelectorAll('main table')].map(table=>[...table.querySelectorAll('thead tr:first-child th')].map(cell=>Math.round(cell.getBoundingClientRect().width)));
                return { title:document.querySelector('h1')?.innerText.slice(0,80), problems:[...new Set(problems)], columns, tableCount:columns.length };
            })()`);
            const key = `${label}:${width}`;
            if (scenario === 'normal') baselineColumns.set(key,result.columns);
            if (scenario === 'long-content' && baselineColumns.has(key)) {
                if (JSON.stringify(result.columns)!==JSON.stringify(baselineColumns.get(key))) result.problems.push('table-columns-changed-with-content');
            }
            cases.push({ label,scenario,width,...result });
            if(result.problems.length) failures.push({ label,scenario,width,problems:result.problems });
            if((capture && [375,1440].includes(width)) || ([1920,2560].includes(width) && /dashboard$|appointments$|^public-\/display|^public-\/$/.test(label)) || result.problems.length) {
                await evaluate('new Promise(resolve=>setTimeout(resolve,250))');
                const screenshot=await call('Page.captureScreenshot',{format:'png'});
                writeFileSync(join(directory,`sweep-${scenario}-${label.replace(/[^a-z0-9-]/gi,'-')}-${width}.png`),Buffer.from(screenshot.data,'base64'));
            }
        }
        writeFileSync(join(directory,'presentation-results.json'),JSON.stringify({ cases,failures },null,2));
    }

    async function page(role,path,scenario='normal') {
        await go(path,'document.readyState === "complete" && Boolean(document.querySelector("main"))');
        const actual=await evaluate('location.pathname');
        const expected=new URL(path,'http://127.0.0.1:18947').pathname;
        const denied=await evaluate('Boolean(document.title.match(/403|500|419/))');
        if(actual!==expected || denied) failures.push({label:`${role}-${path}`,scenario,problem:`unexpected route or denial: ${actual}`});
        await inspect(`${role}-${expected}${new URL(path,'http://127.0.0.1:18947').search}`,scenario);
    }

    // The fixture's patient is explicitly linked to the completed case for patient-only pages.
    const patientLinks=sql.prepare('SELECT id,user_id FROM patients WHERE user_id=? OR id=?').all(patientUser.id,child.patient_id);
    sql.prepare('UPDATE patients SET user_id=NULL WHERE user_id=?').run(patientUser.id);
    sql.prepare('UPDATE patients SET user_id=? WHERE id=?').run(patientUser.id,child.patient_id);
    const authPaths=publicOnly?[]:['/','/login','/admin','/register','/register/clinic','/forgot-password','/reset-password/layout-fixture?email=layout@example.test','/layout-page-not-found'];
    const pages = {
        doctor: ['/doctor/dashboard','/doctor/queues','/doctor/medical-records',`/doctor/visits/${visit.id}`,'/care-continuity',`/care-continuity/visits/${visit.id}`,`/care-continuity/handoffs/${handoffId}`,'/doctor-absences','/follow-ups','/clinical-documents','/notifications','/profile','/confirm-password'],
        dentist: [`/doctor/visits/${child.id}`,`/clinical-documents/visits/${child.id}`,`/clinical-documents/visits/${child.id}/preview`,`/clinical-documents/${documentId}`,`/follow-ups/visits/${child.id}/create`,`/follow-ups/${followId}`,`/patient-billing/visits/${child.id}/clinical-review`],
        receptionist: ['/receptionist/dashboard','/receptionist/queues','/receptionist/appointments','/receptionist/appointments?page=2','/receptionist/appointments/create',`/receptionist/appointments/${appointment.id}`,`/appointments/${appointment.id}/replace`,'/receptionist/patients','/receptionist/patients/create',`/receptionist/patients/${child.patient_id}`,`/receptionist/patients/${child.patient_id}/edit`,'/care-continuity',`/care-continuity/visits/${visit.id}`,`/care-continuity/handoffs/${handoffId}`,'/doctor-absences',`/doctor-absences/${absence.id}`,'/follow-ups',`/follow-ups/${followId}`,'/clinical-documents',`/clinical-documents/${documentId}`,'/patient-billing',`/patient-billing/bills/${patientBill.id}`,'/patient-billing/closings',`/patient-billing/closings/${closed.id}`,'/clinic-reports','/notifications'],
        admin: ['/admin/dashboard',`/admin/clinic/${clinic.id}/edit`,'/admin/staff','/admin/staff/create',`/admin/staff/${doctorUser.user_id}/edit`,'/admin/services','/admin/services/create',`/admin/services/${service.id}/edit`,'/admin/doctor-schedules','/admin/doctor-schedules/create',`/admin/doctor-schedules/${schedule.id}/edit`,'/admin/audit-logs','/doctor-absences',`/doctor-absences/${absence.id}`,'/patient-billing',`/patient-billing/bills/${patientBill.id}`,'/patient-billing/closings',`/patient-billing/closings/${closed.id}`,'/clinic-reports',`/clinic-reports/${reportId}`,'/subscription/addons','/subscription/payments',...payments.map(row=>`/subscription/payments/${row.id}`),'/onboarding/plans','/notifications','/profile'],
        platform: ['/platform/dashboard','/platform/tenants',`/platform/tenants/${clinic.id}`,'/platform/subscription-plans','/platform/payments',...payments.map(row=>`/platform/payments/${row.id}`),'/notifications','/profile'],
        patient: ['/patient/dashboard','/patient/appointments','/patient/appointments/create',`/patient/appointments/${child.appointment_id}`,`/clinic/${clinic.slug}/booking`,'/follow-ups',`/follow-ups/${followId}`,'/clinical-documents',`/clinical-documents/${documentId}`,'/patient-billing',`/patient-billing/bills/${patientBill.id}`,'/notifications','/profile'],
    };

    const changes=[];
    function setLong(table,id,field,value) {
        const original=sql.prepare(`SELECT ${field} AS value FROM ${table} WHERE id=?`).get(id);
        changes.push({table,id,field,value:original.value});
        sql.prepare(`UPDATE ${table} SET ${field}=? WHERE id=?`).run(value,id);
    }
    function restoreLong() {
        for(const row of changes.reverse()) sql.prepare(`UPDATE ${row.table} SET ${row.field}=? WHERE id=?`).run(row.value,row.id);
        changes.length=0;
    }

    try {
        for(const scenario of ['normal','long-content']) {
            if(scenario==='long-content') {
                for(const row of sql.prepare('SELECT id FROM patients').all()) setLong('patients',row.id,'name','Pasien'+ 'a'.repeat(229));
                for(const row of sql.prepare('SELECT id FROM users').all()) setLong('users',row.id,'name','Pengguna dengan nama panjang '+ 'b'.repeat(180));
                setLong('clinics',clinic.id,'name','Klinik '+ 'c'.repeat(180));
                setLong('clinics',clinic.id,'address','Alamat panjang '.repeat(100));
                for(const row of sql.prepare('SELECT id FROM services').all()) { setLong('services',row.id,'name','Layanan '+row.id+' '+ 'd'.repeat(180));setLong('services',row.id,'price',9999999999.99); }
                for(const row of sql.prepare('SELECT id FROM appointments').all()) setLong('appointments',row.id,'service_name','Layanan '+ 'e'.repeat(180));
                for(const row of sql.prepare('SELECT id FROM queues').all()) setLong('queues',row.id,'display_number','WWWWW-'+String(1000+row.id));
                for(const row of sql.prepare('SELECT id FROM doctors').all()) setLong('doctors',row.id,'specialization','Spesialis '+ 'g'.repeat(130));
                for(const row of sql.prepare('SELECT id,data FROM notifications LIMIT 1').all()) { const data=JSON.parse(row.data);data.title='Pemberitahuan '+ 'h'.repeat(140);data.message='Informasi panjang '+ 'i'.repeat(500);setLong('notifications',row.id,'data',JSON.stringify(data)); }
                setLong('follow_ups',followId,'instructions','Instruksi kontrol yang panjang. '.repeat(120));
                for(const row of sql.prepare('SELECT id FROM visits').all()) setLong('visits',row.id,'service_name','Layanan '+ 'f'.repeat(180));
            }
            await call('Network.clearBrowserCookies');
            for(const path of authPaths) await page('guest',path,scenario);
            await go(`/booking/${appointment.id}/status?token=${publicToken}`,'Boolean(document.querySelector("main"))');
            await inspect('public-booking-status',scenario);
            for(const path of [`http://${clinic.slug}.localhost:18947/`,`http://${clinic.slug}.localhost:18947/queue`,`/display/${clinic.slug}`,'/previews/visit-summary']) await page('public',path,scenario);
            for(const [role,paths] of Object.entries(publicOnly?{}:pages)) {
                await login(role);
                for(const path of paths) await page(role,path,scenario);
                if(role==='receptionist') {
                    await go('/receptionist/appointments/create','Boolean(document.querySelector("#doctor_id_search"))');
                    await evaluate('document.querySelector("#doctor_id_search").focus()');
                    await waitFor('document.querySelector("[x-ref=results]").getBoundingClientRect().height>0');
                    await inspect('doctor-choice-open',scenario);
                    await evaluate(`const field=document.querySelector('#doctor_id_search');field.value='layout-no-results-98473';field.dispatchEvent(new Event('input',{bubbles:true}));`);
                    await waitFor('document.querySelector("[x-ref=results]").innerText.includes("tidak ditemukan")');
                    await inspect('doctor-choice-empty',scenario);
                }
                if(role==='dentist') {
                    for(const [path,label] of [[`/clinical-documents/${documentId}`,'issued-prescription-print'],[`/follow-ups/${followId}`,'follow-up-print']]) {
                        await go(path,'Boolean(document.querySelector("form[action$=print]"))');
                        await evaluate(`(() => {const form=document.querySelector('form[action$="/print"]');form.removeAttribute('target');const confirmation=form.querySelector('[name="recipient_identity_verified"]');if(confirmation) confirmation.checked=true;form.requestSubmit();})()`);
                        await waitFor('Boolean(document.querySelector("button[onclick]")) && !document.querySelector("#workspace-navigation")');
                        await inspect(label,scenario);
                    }
                }
                console.log(`Presentation sweep: ${scenario} ${role} (${paths.length} pages)`);
            }
        }
        restoreLong();

        if(!publicOnly) {
        const empty = {
            receptionist: ['/receptionist/patients?search=layout-empty-98473','/receptionist/appointments?date=1901-01-01','/receptionist/queues?date=1901-01-01'],
            doctor: ['/doctor/medical-records?search=layout-empty-98473','/doctor/dashboard?date=1901-01-01'],
            admin: ['/admin/audit-logs?actor=layout-empty-98473'],
            platform: ['/platform/tenants?search=layout-empty-98473','/platform/payments?search=layout-empty-98473'],
        };
        for(const [role,paths] of Object.entries(empty)) { await login(role);for(const path of paths) await page(role,path,'empty-filter'); }

        for(const [role,path] of [['admin','/admin/services/create'],['receptionist','/receptionist/patients/create']]) {
            await login(role);await go(path,'Boolean(document.querySelector("main form"))');
            await evaluate(`(() => {window.__presentationValidationPending=true;const form=document.querySelector('main form');form.noValidate=true;const name=form.querySelector('[name="name"]');if(name) name.value='';HTMLFormElement.prototype.submit.call(form);})()`);
            await waitFor('!window.__presentationValidationPending && document.readyState === "complete"');
            await waitFor('Boolean(document.querySelector("main [role=alert],main .text-red-600,main .text-red-700"))');
            const hasError=await evaluate('Boolean(document.querySelector("main [role=alert],main .text-red-600,main .text-red-700"))');
            assert.ok(hasError,`${role} invalid form returns visible validation`);
            await inspect(`${role}-validation`,'validation-errors');
        }

        sql.prepare('UPDATE users SET clinic_id=NULL WHERE id=?').run(staff.id);
        await login('admin','location.pathname === "/onboarding/clinic"');
        await page('owner','/onboarding/clinic','new-owner');
        sql.prepare('UPDATE users SET clinic_id=? WHERE id=?').run(staff.clinic_id,staff.id);
        sql.prepare('UPDATE users SET email_verified_at=NULL WHERE id=?').run(patientUser.id);
        await login('patient','location.pathname === "/verify-email"');
        await page('patient','/verify-email','unverified');
        sql.prepare('UPDATE users SET email_verified_at=? WHERE id=?').run(patientUser.email_verified_at,patientUser.id);
        }
    } finally {
        restoreLong();
        sql.prepare('UPDATE appointments SET access_token_hash=? WHERE id=?').run(originalTokenHash,appointment.id);
        sql.prepare('UPDATE users SET clinic_id=? WHERE id=?').run(staff.clinic_id,staff.id);
        sql.prepare('UPDATE users SET email_verified_at=? WHERE id=?').run(patientUser.email_verified_at,patientUser.id);
        sql.prepare('UPDATE patients SET user_id=NULL WHERE user_id=?').run(patientUser.id);
        for(const row of patientLinks) sql.prepare('UPDATE patients SET user_id=? WHERE id=?').run(row.user_id,row.id);
        writeFileSync(join(directory,'presentation-results.json'),JSON.stringify({cases,failures},null,2));
    }
    console.log(`Presentation coverage: ${new Set(cases.map(row=>row.label)).size} role/page combinations, ${cases.length} viewport/scenario checks`);
    assert.deepEqual(failures,[],'Every presentation scenario must keep text and controls inside their layout; see presentation-results.json');
}
