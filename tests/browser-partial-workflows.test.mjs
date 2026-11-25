import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { auditWorkspacePresentation } from './workspace-presentation.mjs';
import { auditVoiceScribe } from './voice-scribe-browser.mjs';
import { auditRmeWorkspace } from './rme-workspace-browser.mjs';

const enabled = process.env.CLINICOS_BROWSER_TEST === '1';

test('clinical and booking workflows in Chrome with an isolated demo database', { skip: !enabled, timeout: process.env.CLINICOS_PRESENTATION_SWEEP === '1' ? 900000 : 300000 }, async () => {
    const { DatabaseSync } = await import('node:sqlite');
    const directory = mkdtempSync(join(tmpdir(), 'clinicos-browser-'));
    const database = join(directory, 'testing.sqlite');
    const root = resolve(import.meta.dirname, '..');
    const baseUrl = 'http://127.0.0.1:18947';
    const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: baseUrl,
        LARAVEL_STORAGE_PATH: join(directory, 'storage'),
        APP_CONFIG_CACHE: join(directory, 'config.php'), APP_ROUTES_CACHE: join(directory, 'routes.php'),
        DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', QUEUE_CONNECTION: 'sync',
        BROADCAST_CONNECTION: 'null', CACHE_STORE: 'array', SESSION_DRIVER: 'database',
        SESSION_DOMAIN: 'null', SESSION_SECURE_COOKIE: 'false', MAIL_MAILER: 'array' };
    if (process.env.CLINICOS_VOICE_BROWSER_TEST === '1') Object.assign(env, { VOICE_ENABLED: 'true', VOICE_PROVIDER: 'fake', VOICE_CLINIC_IDS: '1' });
    const phpBinary = process.env.PHP_BINARY || 'php';
    const php = ['-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', '-d', 'variables_order=EGPCS', '-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=gd'];
    for (const path of ['framework/views', 'framework/cache', 'framework/sessions', 'logs', 'app/private', 'app/public']) {
        mkdirSync(join(env.LARAVEL_STORAGE_PATH, path), { recursive: true });
    }
    for (const command of [['migrate', '--force'], ['db:seed', '--class=DemoSeeder', '--force']]) {
        execFileSync(phpBinary, [...php, 'artisan', ...command, '--no-interaction'], { cwd: root, env, stdio: 'pipe' });
    }
    const server = spawn(phpBinary, [...php, '-S', '127.0.0.1:18947', join(root,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: join(root,'public'), env, stdio: ['ignore','ignore','pipe'] });
    let serverStartupError = '';
    server.stderr.on('data', chunk => { const line=chunk.toString(); if (/failed to listen|unable to load|fatal error/i.test(line)) serverStartupError=line.slice(0,300); });
    server.on('error',error=>{serverStartupError=error.message;});
    const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0',
        ...(process.env.CLINICOS_VOICE_BROWSER_TEST === '1' ? ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] : []),
        `--user-data-dir=${join(directory, 'chrome')}`, 'about:blank',
    ], { stdio: ['ignore', 'ignore', 'pipe'] });
    let socket;
    const sql = new DatabaseSync(database);
    try {
        const endpoint = await new Promise((resolveEndpoint, reject) => {
            const timeout = setTimeout(() => reject(new Error('Chrome did not start')), 15000);
            browser.stderr.on('data', chunk => {
                const match = chunk.toString().match(/DevTools listening on (ws:\/\/[^\s]+)/);
                if (match) { clearTimeout(timeout); resolveEndpoint(match[1]); }
            });
            browser.on('error', reject);
        });
        const targets = await (await fetch(`http://${new URL(endpoint).host}/json/list`)).json();
        socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolveOpen => socket.addEventListener('open', resolveOpen, { once: true }));
        let nextId = 0;
        const pending = new Map();
        const errors = [];
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.id && pending.has(message.id)) {
                const { resolve: done, reject } = pending.get(message.id);
                pending.delete(message.id);
                message.error ? reject(new Error(message.error.message)) : done(message.result);
            }
            if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
            if (message.method === 'Page.javascriptDialogOpening') call('Page.handleJavaScriptDialog', { accept: true }).catch(error => { if (!error.message.includes('No dialog is showing')) errors.push(error.message); });
        });
        function call(method, params = {}) {
            return new Promise((resolveCall, reject) => {
                const id = ++nextId;
                pending.set(id, { resolve: resolveCall, reject });
                socket.send(JSON.stringify({ id, method, params }));
            });
        }
        async function evaluate(expression) {
            const result = await call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error('Browser expression failed: ' + (result.exceptionDetails.exception?.description ?? result.exceptionDetails.text));
            return result.result.value;
        }
        async function waitFor(expression) {
            for (let attempt = 0; attempt < 100; attempt++) {
                try { if (await evaluate(expression)) return; } catch { /* Navigation replaces the execution context. */ }
                await new Promise(resolveWait => setTimeout(resolveWait, 100));
            }
            const status = await evaluate('document.querySelector("[data-rme-autosave-status]")?.innerText ?? ""');
            const failureScreenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,'failure.png'),Buffer.from(failureScreenshot.data,'base64'));
            throw new Error('Browser condition timed out: ' + expression + ' ' + status + ' Path: ' + await evaluate('location.pathname') + ' Server: ' + serverStartupError + ' Feedback: ' + await evaluate('document.body.innerText.slice(-800)'));
        }
        async function go(path, ready) {
            await evaluate('window.__clinicosNavigationPending = true');
            await call('Page.navigate', { url: path.startsWith('http') ? path : baseUrl + path });
            await waitFor('!window.__clinicosNavigationPending && document.readyState === "complete"');
            await waitFor(ready);
            await call('Page.bringToFront');
            await call('Emulation.setFocusEmulationEnabled',{enabled:true});
        }
        async function fill(selector, value) {
            await evaluate(`(() => { const field = document.querySelector(${JSON.stringify(selector)}); field.closest('[data-rme-form]')?.rmeWorkspace?.reveal(field, false); for (let parent = field.parentElement; parent; parent = parent.parentElement) { if (parent.tagName === 'DETAILS') parent.open = true; } field.value = ${JSON.stringify(value)}; field.dispatchEvent(new Event('input', {bubbles:true})); field.dispatchEvent(new Event('change', {bubbles:true})); })()`);
        }
        async function checkQueuePresentation(label) {
            for (const width of [375,768,1024,1440]) {
                await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
                await evaluate('new Promise(resolve=>setTimeout(resolve,250))');
                const layout = await evaluate(`(() => {
                    const numbers=[...document.querySelectorAll('[data-queue-number]')];
                    const fields=[...document.querySelectorAll('[data-queue-patient], [data-queue-doctor], [data-booking-field], [data-queue-id] h3')];
                    const columns=[...document.querySelectorAll('[data-queue-doctor]')].map(node=>node.getBoundingClientRect().left);
                    return { overflow:document.documentElement.scrollWidth>innerWidth+1,
                        numbers:numbers.length,
                        singleLine:numbers.every(node=>{const range=document.createRange();range.selectNodeContents(node);return range.getClientRects().length===1 && node.scrollWidth<=node.clientWidth+1;}),
                        contained:fields.every(node=>node.scrollWidth<=node.clientWidth+1),
                        aligned:innerWidth<1280 || columns.every(left=>Math.abs(left-columns[0])<1) };
                })()`);
                assert.equal(layout.overflow,false,`${label}: page overflow at ${width}px`);
                assert.ok(layout.numbers>0 && layout.singleLine,`${label}: queue number stays horizontal at ${width}px`);
                assert.ok(layout.contained,`${label}: long name stays inside its column at ${width}px`);
                assert.ok(layout.aligned,`${label}: doctor columns align across different actions at ${width}px`);
                const screenshot=await call('Page.captureScreenshot',{format:'png'});
                writeFileSync(join(directory,`${label}-${width}.png`),Buffer.from(screenshot.data,'base64'));
            }
        }
        const secret = readFileSync(join(root, 'database/seeders/DemoUserSeeder.php'), 'utf8').match(/Hash::make\('([^']+)'\)/)?.[1];
        assert.ok(secret, 'Demo credentials must be configured');
        async function login(prefix, ready = 'location.pathname.includes("dashboard")') {
            await call('Page.navigate',{url:'about:blank'});
            await waitFor('location.href === "about:blank" && document.readyState === "complete"');
            await call('Network.clearBrowserCookies');
            await go(prefix === 'patient' ? '/login' : '/admin', 'Boolean(window.Livewire && document.querySelector("#email"))');
            await fill('#email', `${prefix}@clinicos.test`);
            await fill('#password', secret);
            await evaluate('document.querySelector("form").requestSubmit()');
            await waitFor(ready);
        }
        await call('Page.enable');
        await call('Runtime.enable');
        await call('Network.enable');
        await call('Page.bringToFront');
        await call('Emulation.setFocusEmulationEnabled', { enabled: true });
        await login('doctor');
        const visit = sql.prepare("SELECT v.id, v.medical_record_id FROM (SELECT visits.*, medical_records.id AS medical_record_id FROM visits JOIN medical_records ON medical_records.visit_id = visits.id) v JOIN doctors d ON d.id = v.doctor_id JOIN users u ON u.id = d.user_id JOIN queues q ON q.appointment_id=v.appointment_id WHERE date(q.queue_date)=date('now','+7 hours') AND v.status = 'IN_PROGRESS' AND u.email = 'doctor@clinicos.test' LIMIT 1").get();
        assert.ok(visit, 'Demo must contain an active doctor visit');
        if (process.env.CLINICOS_RME_BROWSER_TEST) {
            await auditRmeWorkspace({ sql, call, evaluate, go, waitFor, directory, visit });
            assert.equal(errors.length, 0, 'RME workspace has no JavaScript exceptions');
            console.log(`RME browser evidence: ${directory}`);
            return;
        }
        if (process.env.CLINICOS_VOICE_BROWSER_TEST === '1') {
            await auditVoiceScribe({ sql, call, evaluate, go, waitFor, directory, root, env, php, phpBinary, visit });
            assert.equal(errors.length, 0, 'Voice workflow has no JavaScript exceptions');
            console.log(`Voice browser screenshots: ${directory}`);
            return;
        }
        const activePatient=sql.prepare('SELECT p.id,p.name FROM patients p JOIN visits v ON v.patient_id=p.id WHERE v.id=?').get(visit.id);
        sql.prepare('UPDATE patients SET name=? WHERE id=?').run('Pasien'+ 'a'.repeat(230),activePatient.id);
        await go('/doctor/dashboard','Boolean(document.querySelector("[data-queue-number]"))');
        await checkQueuePresentation('doctor-long-name');
        sql.prepare('UPDATE patients SET name=? WHERE id=?').run(activePatient.name,activePatient.id);

        await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave && document.querySelector("#diagnosis_name_0"))');
        await evaluate('Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 10000))])');
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            await evaluate('new Promise(resolve => setTimeout(resolve, 250))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `RME overflow at ${width}px`);
            const screenshot = await call('Page.captureScreenshot', { format: 'png' });
            writeFileSync(join(directory, `rme-${width}.png`), Buffer.from(screenshot.data, 'base64'));
            await evaluate('document.querySelector("[data-rme-tab=clinical]").click(); document.querySelector("#structured-clinical-heading").scrollIntoView({block:"start"})');
            await evaluate('new Promise(resolve => setTimeout(resolve, 750))');
            const structured = await call('Page.captureScreenshot', { format: 'png' });
            writeFileSync(join(directory, `clinical-${width}.png`), Buffer.from(structured.data, 'base64'));
        }
        const initialVersion = Number(await evaluate('document.querySelector("[name=record_version]").value'));
        await evaluate('document.querySelector("#critical_allergies_acknowledged")?.click()');
        await fill('[name=assessment]', 'Assessment uji browser');
        await fill('#diagnosis_name_0', 'Diagnosis browser');
        await fill('#treatment_name_0', 'Tindakan browser');
        await fill('#treatment_quantity_0', '2');
        await waitFor(`Number(document.querySelector('[name=record_version]').value) > ${initialVersion} && !document.querySelector('[data-rme-form]').rmeAutosave.unsaved`);
        assert.equal(sql.prepare('SELECT assessment FROM medical_records WHERE id = ?').get(visit.medical_record_id).assessment, 'Assessment uji browser');
        assert.equal(sql.prepare('SELECT quantity FROM treatments WHERE medical_record_id = ?').get(visit.medical_record_id).quantity, 2);
        await fill('#vital_systolic', '999');
        await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()');
        assert.equal(await evaluate('document.querySelector("#vital_systolic").getAttribute("aria-invalid")'), 'true');
        await fill('#vital_systolic', '120');
        await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()');
        await waitFor('!document.querySelector("[data-rme-form]").rmeAutosave.unsaved');
        await call('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
        await fill('[name=doctor_notes]', 'Catatan uji koneksi');
        await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()');
        assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.unsaved'), true);
        await call('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
        await evaluate('window.dispatchEvent(new Event("online"))');
        await waitFor('!document.querySelector("[data-rme-form]").rmeAutosave.unsaved');
        assert.equal(sql.prepare('SELECT doctor_notes FROM medical_records WHERE id = ?').get(visit.medical_record_id).doctor_notes, 'Catatan uji koneksi');
        await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave)');
        assert.equal(await evaluate('document.querySelector("#diagnosis_name_0").value'), 'Diagnosis browser');
        await evaluate('document.querySelector("#new-allergy-substance").closest("details").open = true');
        await fill('#new-allergy-substance', 'Pemicu browser');
        await fill('#new-allergy-reaction', 'Reaksi browser');
        await fill('#new-allergy-severity', 'CRITICAL');
        await evaluate('document.querySelector("#new-allergy-substance").closest("form").requestSubmit()');
        await waitFor('document.querySelector("[data-critical-allergy-alert]")?.innerText.includes("Pemicu browser") && Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave)');

        const allergyId = sql.prepare('SELECT id FROM patient_allergies WHERE substance = ?').get('Pemicu browser').id;
        const allergyForm = `form[action$="/allergies/${allergyId}"]`;
        await evaluate(`document.querySelector(${JSON.stringify(allergyForm)}).closest('details').open = true`);
        const secondWriter = await evaluate(`(async () => {
            const form = document.querySelector(${JSON.stringify(allergyForm)});
            const data = new FormData(form);
            data.set('request_id', crypto.randomUUID());
            data.set('reaction', 'Reaksi dari penyunting lain');
            data.set('revision_reason', 'Konfirmasi klinis penyunting lain.');
            return (await fetch(form.action, {method:'POST', body:data, headers:{Accept:'application/json'}})).status;
        })()`);
        assert.equal(secondWriter, 200);
        await fill(`#allergy-${allergyId}-reaction`, 'Input usang tetap tersimpan di formulir');
        await fill(`#allergy-${allergyId}-reason`, 'Alasan dari formulir lama.');
        await evaluate(`document.querySelector(${JSON.stringify(allergyForm)}).requestSubmit()`);
        await waitFor(`document.querySelector(${JSON.stringify(allergyForm)}).querySelector('[role="alert"]').innerText.includes('sudah berubah')`);
        assert.equal(await evaluate(`document.querySelector('#allergy-${allergyId}-reaction').value`), 'Input usang tetap tersimpan di formulir');
        assert.equal(await evaluate(`document.querySelector(${JSON.stringify(allergyForm)}).querySelector('[data-allergy-review]').classList.contains('hidden')`), false);
        assert.equal(sql.prepare('SELECT COUNT(*) AS n FROM patient_allergies WHERE lineage_id = ? AND superseded_by_id IS NULL').get(allergyId).n, 1);
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            await evaluate(`document.querySelector(${JSON.stringify(allergyForm)}).scrollIntoView({block:'center'})`);
            await evaluate('new Promise(resolve => setTimeout(resolve, 450))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `Allergy conflict overflow at ${width}px`);
            const screenshot = await call('Page.captureScreenshot', { format: 'png' });
            writeFileSync(join(directory, `allergy-conflict-${width}.png`), Buffer.from(screenshot.data, 'base64'));
        }
        await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave)');
        assert.equal(await evaluate(`Boolean(document.querySelector(${JSON.stringify(allergyForm)}))`), false);

        await login('receptionist');
        await go('/receptionist/appointments/create', 'Boolean(window.Alpine && document.querySelector("#doctor_id_search")?._x_attributeCleanups?.["@keydown.down.prevent"])');
        await evaluate('document.querySelector("#doctor_id_search").focus()');
        await waitFor('document.querySelector("#doctor_id_search").parentElement.querySelector("[x-ref=results]").offsetParent !== null');
        await evaluate(`document.querySelector('#doctor_id_search').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}));`);
        await waitFor('document.activeElement.tagName === "BUTTON"');
        await evaluate('document.activeElement.click()');
        await fill('[name=appointment_date]', new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date()));
        await waitFor('Alpine.$data(document.querySelector("[data-booking-fields]")).availableServices.length > 0');
        await evaluate('document.querySelector("#service_id_search").focus()');
        await evaluate('document.querySelector("#service_id_search").parentElement.querySelector("[x-ref=results] button").click()');
        await waitFor('Boolean(document.querySelector("[name=service_id]").value)');
        await evaluate('document.querySelector("#doctor_schedule_id_search").focus()');
        await evaluate('document.querySelector("#doctor_schedule_id_search").parentElement.querySelector("[x-ref=results] button").click()');
        await waitFor('Boolean(document.querySelector("[name=doctor_schedule_id]").value)');
        await fill('#doctor_id_search', '');
        await waitFor('document.querySelectorAll("#doctor_id_search ~ div button").length > 1');
        await evaluate('document.querySelector("#doctor_id_search").parentElement.querySelectorAll("[x-ref=results] button")[1].click()');
        await waitFor('!document.querySelector("[name=service_id]").value && !document.querySelector("[name=doctor_schedule_id]").value');
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            await evaluate('new Promise(resolve => setTimeout(resolve, 250))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `Booking overflow at ${width}px`);
            await evaluate('document.querySelector("[data-booking-fields]").scrollIntoView({block:"center"})');
            await evaluate('new Promise(resolve => setTimeout(resolve, 750))');
            const screenshot = await call('Page.captureScreenshot', { format: 'png' });
            writeFileSync(join(directory, `booking-${width}.png`), Buffer.from(screenshot.data, 'base64'));
        }
        await go('/receptionist/patients/create', 'Boolean(window.Alpine && document.querySelector("[data-duplicate-review]"))');
        const existing = sql.prepare('SELECT phone FROM patients ORDER BY id LIMIT 1').get();
        await fill('[name=phone]', existing.phone);
        await waitFor('document.querySelector("[data-duplicate-review] a") !== null');
        assert.ok(await evaluate('Alpine.$data(document.querySelector("[data-duplicate-review]")).candidates.length > 0'));
        await fill('[name=name]', 'Pasien uji browser');
        await fill('[name=birth_date]', '1990-01-01');
        await fill('[name=gender]', 'FEMALE');
        await waitFor('!Alpine.$data(document.querySelector("[data-duplicate-review]")).busy');
        await evaluate('document.querySelector("form").requestSubmit()');
        await waitFor('document.querySelector("[data-duplicate-review] [role=alert]").textContent.length > 0');
        await evaluate('document.querySelector("[name=duplicate_reviewed]").click()');
        await evaluate('document.querySelector("form").requestSubmit()');
        await waitFor('location.pathname.match(/receptionist\\/patients\\/\\d+$/)');
        assert.equal(sql.prepare("SELECT count(*) AS total FROM patients WHERE name = 'Pasien uji browser'").get().total, 1);
        await login('patient');
        const appointment = sql.prepare("SELECT a.id FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = p.user_id WHERE u.email = 'patient@clinicos.test' ORDER BY a.id DESC LIMIT 1").get();
        assert.ok(appointment);
        await go(`/patient/appointments/${appointment.id}`, 'Boolean(document.querySelector("[data-realtime-refresh-root]"))');
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            await evaluate('new Promise(resolve => setTimeout(resolve, 250))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `Patient queue overflow at ${width}px`);
        }
        assert.equal(errors.length, 0, 'No browser JavaScript exceptions');
        const linkedPatient = sql.prepare('SELECT p.id, c.slug FROM patients p JOIN clinics c ON c.id = p.clinic_id JOIN appointments a ON a.patient_id = p.id WHERE a.id = ?').get(appointment.id);
        await login('receptionist');
        await go(`/receptionist/patients/${linkedPatient.id}/edit`, 'Boolean(document.querySelector("input[type=checkbox][name=is_active]"))');
        await evaluate('document.querySelector("input[type=checkbox][name=is_active]").click(); document.querySelector("main form").requestSubmit()');
        await waitFor(`location.pathname === '/receptionist/patients/${linkedPatient.id}'`);
        assert.equal(sql.prepare('SELECT is_active FROM patients WHERE id = ?').get(linkedPatient.id).is_active, 0);
        await login('patient');
        await go(`/clinic/${linkedPatient.slug}/booking`, 'document.body.innerText.includes("Profil pasien Anda di klinik ini nonaktif")');
        assert.equal(await evaluate('document.querySelector("form fieldset").disabled'), true);
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `Inactive booking overflow at ${width}px`);
        }
        await go(`/patient/appointments/${appointment.id}`, 'Boolean(document.querySelector("[data-realtime-refresh-root]"))');
        await login('receptionist');
        await go(`/receptionist/patients/${linkedPatient.id}/edit`, 'Boolean(document.querySelector("input[type=checkbox][name=is_active]"))');
        await evaluate('document.querySelector("input[type=checkbox][name=is_active]").click(); document.querySelector("main form").requestSubmit()');
        await waitFor(`location.pathname === '/receptionist/patients/${linkedPatient.id}'`);
        await login('patient');
        await go(`/clinic/${linkedPatient.slug}/booking`, 'Boolean(document.querySelector("form fieldset"))');
        assert.equal(await evaluate('document.querySelector("form fieldset").disabled'), false);

        const source = sql.prepare(`SELECT a.*, u.name AS doctor_name, u.email AS doctor_email, s.start_time, s.end_time, c.slug AS clinic_slug
            FROM appointments a JOIN queues q ON q.appointment_id=a.id JOIN doctors d ON d.id=a.doctor_id
            JOIN users u ON u.id=d.user_id JOIN doctor_schedules s ON s.id=a.doctor_schedule_id JOIN clinics c ON c.id=a.clinic_id
            WHERE a.status='BOOKED' AND q.status IN ('BOOKED','WAITING','SKIPPED','CALLED')
            AND a.clinic_id=(SELECT clinic_id FROM users WHERE email='receptionist@clinicos.test')
            AND NOT EXISTS(SELECT 1 FROM visits v WHERE v.appointment_id=a.id) ORDER BY a.id LIMIT 1`).get();
        assert.ok(source, 'Fixture must have a booking before the examination');
        const shiftedDate = (date, days) => { const value = new Date(`${date.slice(0,10)}T12:00:00Z`); value.setUTCDate(value.getUTCDate()+days); return value.toISOString().slice(0,10); };
        async function chooseOption(name, label) {
            await fill(`#${name}_search`, label);
            await evaluate(`document.querySelector('#${name}_search').focus()`);
            const options = `document.querySelector('#${name}_search').parentElement.querySelectorAll('[x-ref=results] button')`;
            await waitFor(`[...${options}].some(button=>button.innerText.includes(${JSON.stringify(label)}))`);
            await evaluate(`[...${options}].find(button=>button.innerText.includes(${JSON.stringify(label)})).click()`);
        }
        async function replaceThroughUi(id, targetDate) {
            const replacementSource=sql.prepare('SELECT a.*,u.name AS doctor_name,s.start_time FROM appointments a JOIN doctors d ON d.id=a.doctor_id JOIN users u ON u.id=d.user_id JOIN doctor_schedules s ON s.id=a.doctor_schedule_id WHERE a.id=?').get(id);
            await go(`/appointments/${id}/replace`, 'Boolean(window.Alpine && document.querySelector("[data-booking-fields]"))');
            await chooseOption('doctor_id', replacementSource.doctor_name);
            await fill('#appointment_date', targetDate);
            await chooseOption('service_id', replacementSource.service_name);
            await chooseOption('doctor_schedule_id', replacementSource.start_time.slice(0,5));
            await fill('#reason', 'Perubahan jadwal disepakati pasien pada fixture browser.');
            await evaluate('document.querySelector("main form").requestSubmit()');
            await waitFor('Boolean(document.querySelector("main form[action$=replace]"))');
            if(process.env.CLINICOS_PRESENTATION_SWEEP==='1') {
                for(const width of [375,768,1024,1440]) {
                    await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
                    await evaluate('new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))');
                    assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),`Replacement confirmation stays responsive at ${width}px`);
                    const screenshot=await call('Page.captureScreenshot',{format:'png'});
                    writeFileSync(join(directory,`replacement-confirm-${id}-${width}.png`),Buffer.from(screenshot.data,'base64'));
                }
            }

            await evaluate('document.querySelector("main form[action$=replace]").requestSubmit()');
            await waitFor('location.pathname.match(/receptionist\\/appointments\\/\\d+$/)');
            return sql.prepare('SELECT a.* FROM appointment_replacements r JOIN appointments a ON a.id=r.new_appointment_id WHERE r.old_appointment_id=?').get(id);
        }
        await login('receptionist');
        const replacement = await replaceThroughUi(source.id, shiftedDate(source.appointment_date,7));
        assert.ok(replacement);
        assert.equal(Number(replacement.service_price), Number(source.service_price));
        assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(source.id).status, 'CANCELLED');
        assert.equal(sql.prepare('SELECT status FROM queues WHERE appointment_id=?').get(replacement.id).status, 'BOOKED');
        await login(source.doctor_email.split('@')[0]);
        await go('/doctor-absences', 'Boolean(document.querySelector("#starts_at"))');
        await fill('#starts_at', replacement.appointment_date.slice(0,10)+'T12:00');
        await fill('#ends_at', replacement.appointment_date.slice(0,10)+'T12:15');
        const privateReason = 'Alasan operasional privat pada fixture browser.';
        await fill('#reason', privateReason);
        await evaluate('document.querySelector("main form").requestSubmit()');
        await waitFor('location.pathname.match(/doctor-absences\\/\\d+$/)');
        const absence = sql.prepare('SELECT * FROM doctor_absences ORDER BY id DESC LIMIT 1').get();
        assert.ok(absence); assert.equal(absence.verified_at,null);
        assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(replacement.id).status,'BOOKED');
        for (const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            await evaluate('new Promise(resolve=>setTimeout(resolve,450))');
            assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),`Absence workflow overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'});
            writeFileSync(join(directory,`doctor-absence-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        await login('patient');
        await go(`/clinic/${source.clinic_slug}/booking`,'Boolean(document.querySelector("[data-booking-fields]"))');
        await chooseOption('doctor_id',source.doctor_name);
        await fill('#appointment_date',replacement.appointment_date.slice(0,10));
        await waitFor('Alpine.$data(document.querySelector("[data-booking-fields]")).availableSchedules.length===0');
        assert.equal(await evaluate(`document.body.innerText.includes(${JSON.stringify(privateReason)})`),false);
        await login('admin');
        await go(`/doctor-absences/${absence.id}`,'Boolean(document.querySelector("form[action$=verify]"))');
        await evaluate('document.querySelector("form[action$=verify]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Disahkan:")');
        await login('receptionist');
        await go(`/doctor-absences/${absence.id}`,`Boolean(document.querySelector('#contact-${replacement.id}'))`);
        await fill(`#contact-${replacement.id}`,'Pasien telah dihubungi dan memilih jadwal pengganti.');
        await evaluate(`document.querySelector('#contact-${replacement.id}').closest('form').requestSubmit()`);
        await waitFor('document.body.innerText.includes("Riwayat komunikasi tersimpan")');
        assert.equal(sql.prepare('SELECT COUNT(*) AS n FROM doctor_absence_contacts WHERE doctor_absence_id=?').get(absence.id).n,1);
        const continuation = await replaceThroughUi(replacement.id,shiftedDate(replacement.appointment_date,7));
        assert.ok(continuation);
        await login(source.doctor_email.split('@')[0]);
        await go(`/doctor-absences/${absence.id}`,'Boolean(document.querySelector("form[action$=request-cancel]"))');
        await fill('#reason','Dokter kembali tersedia pada fixture.');
        await evaluate('document.querySelector("form[action$=request-cancel]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Permintaan buka blok:")');
        assert.equal(sql.prepare('SELECT cancelled_at FROM doctor_absences WHERE id=?').get(absence.id).cancelled_at,null);
        await login('admin');
        await go(`/doctor-absences/${absence.id}`,'Boolean(document.querySelector("form[action$=reopen]"))');
        await fill('#reason','Ketersediaan dokter sudah dikonfirmasi.');
        await evaluate('document.querySelector("form[action$=reopen]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Blok dibuka kembali")');
        assert.equal(sql.prepare('SELECT appointment_date FROM appointments WHERE id=?').get(continuation.id).appointment_date,continuation.appointment_date);
        assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(replacement.id).status,'CANCELLED');
        // Synthetic receiver competence/session fixtures live only in this disposable database.
        const care = sql.prepare('SELECT visits.*, appointments.doctor_schedule_id FROM visits JOIN appointments ON appointments.id=visits.appointment_id WHERE visits.id=?').get(visit.id);
        const receiver = sql.prepare("SELECT doctors.id FROM doctors JOIN users ON users.id=doctors.user_id WHERE users.email='dentist@clinicos.test'").get();
        sql.prepare('INSERT OR IGNORE INTO doctor_service (doctor_id,service_id) VALUES (?,?)').run(receiver.id,care.service_id);
        sql.prepare("UPDATE clinic_settings SET opening_time='00:00',closing_time='23:59' WHERE clinic_id=?").run(care.clinic_id);
        const receiverSchedule = Number(sql.prepare("INSERT INTO doctor_schedules (doctor_id,day_of_week,start_time,end_time,quota,is_active,created_at,updated_at) VALUES (?,?,'00:00','23:59',5,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)").run(receiver.id,new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Jakarta',weekday:'short'}).format(new Date()) === 'Sun' ? 0 : ['Mon','Tue','Wed','Thu','Fri','Sat'].indexOf(new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Jakarta',weekday:'short'}).format(new Date()))+1).lastInsertRowid);
        const localDateTime = offset => {
            const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date(Date.now()+offset)).map(part=>[part.type,part.value]));
            return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
        };
        await login('doctor');
        await go(`/doctor/visits/${visit.id}`,'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave)');
        await fill('[name=assessment]','Assessment sebelum jeda yang harus tersimpan');
        const pauseSelector='form[action$="/workflow/pause"]';
        await fill(`${pauseSelector} [name=pause_mode]`,'RELEASE');
        await fill(`${pauseSelector} [name=estimated_return_at]`,localDateTime(3600000));
        await fill(`${pauseSelector} [name=reason]`,'Dokter menilai jeda dengan slot dilepas.');
        await evaluate(`document.querySelector(${JSON.stringify(pauseSelector)}).requestSubmit()`);
                await waitFor(`location.pathname.includes('care-continuity/visits/') || document.querySelector('${pauseSelector} [data-care-error]')?.textContent.length > 0`);
        assert.ok((await evaluate('location.pathname')).includes('care-continuity/visits/'),await evaluate(`document.querySelector('${pauseSelector} [data-care-error]')?.textContent`));
        assert.equal(sql.prepare('SELECT assessment FROM medical_records WHERE id=?').get(visit.medical_record_id).assessment,'Assessment sebelum jeda yang harus tersimpan');
        assert.equal(await evaluate('Boolean(document.querySelector("[name=clinical_delay_assessment]"))'),false,'Same-day resume hides cross-day review');
        assert.equal(await evaluate('Boolean(document.querySelector("form[action$=clear-ready]"))'),false,'Readiness withdrawal is absent before readiness');
        assert.ok(await evaluate('document.querySelector("[data-pause-actions] form[action$=resume]").getBoundingClientRect().height > 0'),'Resume is the visible primary action');
        for (const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false}); await evaluate('new Promise(resolve=>setTimeout(resolve,250))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Pause actions overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,`pause-actions-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        const resumeSelector='form[action$="/workflow/resume"]';
        await fill(`${resumeSelector} [name=doctor_schedule_id]`,String(care.doctor_schedule_id));
        await fill(`${resumeSelector} [name=reason]`,'Pasien siap melanjutkan pemeriksaan sumber.');
        await evaluate(`document.querySelector('${resumeSelector} [name=patient_ready]').click();document.querySelector(${JSON.stringify(resumeSelector)}).requestSubmit()`);
        await waitFor(`!document.querySelector('${resumeSelector}') || document.querySelector('${resumeSelector} [data-care-error]')?.textContent.length > 0`);
        assert.equal(await evaluate(`document.querySelector('${resumeSelector} [data-care-error]')?.textContent ?? ''`),'');
        assert.equal(sql.prepare('SELECT COUNT(*) AS n FROM visits WHERE appointment_id=?').get(care.appointment_id).n,1);
        const leadSelector='form[action$="/leads"]';
        await fill(`${leadSelector} [name=doctor_id]`,String(receiver.id));
        await fill(`${leadSelector} [name=ends_at]`,localDateTime(86400000));
        await fill(`${leadSelector} [name=verification_evidence]`,'Verifikasi kompetensi kasus fixture oleh dokter penanggung jawab.');
        await evaluate(`document.querySelector('${leadSelector} [name=competence_confirmed]').click();document.querySelector(${JSON.stringify(leadSelector)}).requestSubmit()`);
        await waitFor('document.querySelector("form[action$=revoke]") !== null');
        await login('dentist');
        await go(`/care-continuity/visits/${visit.id}`,`Boolean(document.querySelector('form[action$="/workflow/interrupt"]'))`);
        assert.equal(await evaluate('Boolean(document.querySelector("[data-rme-form]"))'),false);
        await fill('form[action$="/workflow/interrupt"] [name=reason]','Penanggung jawab klinis menilai perlu interupsi dan kelanjutan.');
        await evaluate(`document.querySelector('form[action$="/workflow/interrupt"]').requestSubmit()`);
        await waitFor('document.body.innerText.includes("Pelayanan terinterupsi") && Boolean(document.querySelector("form[action$=handoffs]"))');
        const handoffSelector='form[action$=handoffs]';
        await fill(`${handoffSelector} [name=receiving_doctor_id]`,String(receiver.id));
        await fill(`${handoffSelector} [name="summary[context]"]`,'Ringkasan minimum uji handoff browser.');
        await fill(`${handoffSelector} [name="summary[handoff_goal]"]`,'Lanjutkan layanan asal dengan persetujuan pasien.');
        await fill(`${handoffSelector} [name=patient_consent_note]`,'Pasien setuju dokter penerima untuk kasus ini.');
        await fill(`${handoffSelector} [name=expires_at]`,localDateTime(86400000));
        await fill(`${handoffSelector} [name=reason]`,'Rilis ringkasan kasus yang telah ditinjau.');
        await evaluate(`document.querySelector('${handoffSelector} [name=patient_consent]').click();document.querySelector(${JSON.stringify(handoffSelector)}).requestSubmit()`);
        await waitFor('location.pathname.includes("care-continuity/handoffs/") && Boolean(document.querySelector("form[action$=accept]"))');
        const handoffId=Number((await evaluate('location.pathname')).split('/').at(-1));
        await evaluate('document.querySelector("form[action$=accept]").requestSubmit()');
        await waitFor('location.pathname === "/care-continuity"');
        await login('receptionist');
        await go(`/care-continuity/handoffs/${handoffId}`,'Boolean(document.querySelector("form[action$=book]"))');
        assert.equal(await evaluate('document.body.innerText.includes("Ringkasan minimum uji handoff browser.")'),false);
        await fill('form[action$=book] [name=doctor_schedule_id]',String(receiverSchedule));
        await fill('form[action$=book] [name=appointment_date]',localDateTime(0).slice(0,10));
        await evaluate('document.querySelector("form[action$=book]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Booking ") && !document.querySelector("form[action$=book]")');
        const linked=sql.prepare('SELECT target_appointment_id FROM visit_continuations WHERE id=?').get(handoffId);
        const queue=sql.prepare('SELECT id FROM queues WHERE appointment_id=?').get(linked.target_appointment_id);

        const queuePatient=sql.prepare('SELECT p.id,p.name FROM patients p JOIN appointments a ON a.patient_id=p.id WHERE a.id=?').get(linked.target_appointment_id);
        sql.prepare('UPDATE patients SET name=? WHERE id=?').run('Pasien'+ 'b'.repeat(230),queuePatient.id);
        await go('/receptionist/queues',`Boolean(document.querySelector('form[action$="/${queue.id}/check-in"]'))`);
        assert.equal(await evaluate('document.querySelector("main").innerText.includes("Laporan dokter berhalangan") || document.querySelector("main").innerText.includes("Kelanjutan pelayanan dan tugas kasus")'),false);
        await checkQueuePresentation('queue-long-name');
        await go(`/receptionist/appointments/${linked.target_appointment_id}`,'Boolean(document.querySelector("[data-booking-detail]"))');
        assert.equal(await evaluate('document.querySelector("main").innerText.includes("Laporan dokter berhalangan")'),false);
        await checkQueuePresentation('booking-detail-long-name');
        sql.prepare('UPDATE patients SET name=? WHERE id=?').run(queuePatient.name,queuePatient.id);

        await go('/receptionist/queues',`Boolean(document.querySelector('form[action$="/${queue.id}/check-in"]'))`);
        await evaluate(`document.querySelector('form[action$="/${queue.id}/check-in"]').requestSubmit()`);
        await waitFor(`Boolean(document.querySelector('form[action$="/${queue.id}/call"]'))`);
        await login('dentist');
        await go('/doctor/queues',`Boolean(document.querySelector('form[action$="/${queue.id}/call"]'))`);
        await evaluate(`document.querySelector('form[action$="/${queue.id}/call"]').requestSubmit()`);
        await waitFor(`Boolean(document.querySelector('form[action$="/${queue.id}/start"]'))`);
        await evaluate(`document.querySelector('form[action$="/${queue.id}/start"]').requestSubmit()`);
        await waitFor('location.pathname.includes("doctor/visits/") && Boolean(document.querySelector("[data-rme-form]"))');
        const child=sql.prepare('SELECT * FROM visits WHERE appointment_id=?').get(linked.target_appointment_id);
        assert.equal(child.quota_origin_visit_id,visit.id);
        assert.equal(sql.prepare('SELECT status FROM visits WHERE id=?').get(visit.id).status,'INTERRUPTED');
        await go(`/care-continuity/visits/${visit.id}`,'document.body.innerText.includes("Pelayanan terinterupsi")');
        for (const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            await evaluate('new Promise(resolve=>setTimeout(resolve,400))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Continuity overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'});
            writeFileSync(join(directory,`care-continuity-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        await go(`/doctor/visits/${child.id}`,'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave && document.querySelector("#medicine_name_0"))');
        await fill('[name=subjective]','Keluhan sintetis untuk pengujian penerbitan.');
        await fill('[name=assessment]','Assessment sintetis untuk pengujian.');
        await fill('[name=plan]','Rencana sintetis untuk pengujian.');
        await evaluate('document.querySelector("#critical_allergies_acknowledged")?.click()');
        await fill('#medicine_name_0','OBAT SINTETIS UJI BROWSER');
        await fill('#medicine_dosage_0','Dosis fixture');
        await fill('#medicine_frequency_0','Frekuensi fixture');
        await fill('#medicine_quantity_0','1');
        await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()');
        await waitFor('!document.querySelector("[data-rme-form]").rmeAutosave.unsaved');
        await evaluate(`window.dispatchEvent(new CustomEvent('open-modal',{detail:'confirm-finalize-visit'}))`);
        await waitFor('document.querySelector("button[formaction$=complete]")?.offsetParent !== null');
        await evaluate('document.querySelector("button[formaction$=complete]").click()');
        await waitFor('!document.querySelector("[data-rme-autosave]") && Boolean(document.querySelector("[name=correction_reason]"))');
        await go(`/clinical-documents/visits/${child.id}`,'Boolean(document.querySelector("form[action$=issue]"))');
        await evaluate('document.querySelector("[name=clinical_review_confirmed]").click(); document.querySelector("[name=critical_allergies_reviewed]")?.click(); document.querySelector("[name=release_to_patient]").click(); document.querySelector("form[action$=issue]").requestSubmit()');
        await waitFor('location.pathname.startsWith("/clinical-documents/") && !location.pathname.includes("/visits/") || document.querySelector("[data-care-error]")?.textContent.length > 0');
        assert.equal(await evaluate('document.querySelector("[data-care-error]")?.textContent ?? ""'),'');
        const documentId=Number((await evaluate('location.pathname')).split('/').at(-1));
        const issued=sql.prepare('SELECT * FROM issued_prescriptions WHERE id=?').get(documentId);
        assert.equal(issued.status,'ISSUED');
        await evaluate('document.querySelector("form[action$=print]").removeAttribute("target"); document.querySelector("form[action$=print]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Cetak ulang") && document.body.innerText.includes("OBAT SINTETIS UJI BROWSER")');
        for (const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            await evaluate('new Promise(resolve=>setTimeout(resolve,350))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Prescription print overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'});
            writeFileSync(join(directory,`issued-prescription-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        await login('receptionist');
        await go(`/clinical-documents/${documentId}`,'Boolean(document.querySelector("form[action$=handover]"))');
        await evaluate('document.querySelector("[name=recipient_identity_verified]").click(); document.querySelector("form[action$=handover]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Penyerahan terakhir terverifikasi:")');
        assert.equal(sql.prepare("SELECT COUNT(*) AS n FROM audit_logs WHERE action='prescription.handed_over' AND entity_id=?").get(documentId).n,1);
        await login('dentist');
        await go(`/follow-ups/visits/${child.id}/create`,'Boolean(document.querySelector("[name=instructions]"))');
        await fill('[name=service_id]',String(care.service_id));
        await fill('[name=instructions]','Instruksi kontrol sintetis yang dirilis untuk pasien.');
        await fill('[name=range_from]',shiftedDate(localDateTime(0),7));
        await fill('[name=range_until]',shiftedDate(localDateTime(0),14));
        await evaluate('document.querySelector("[name=instructions_approved]").click(); document.querySelector("form[data-care-form]").requestSubmit()');
        await waitFor('location.pathname.startsWith("/follow-ups/") && !location.pathname.includes("/visits/") || document.querySelector("[data-care-error]")?.textContent.length > 0');
        assert.equal(await evaluate('document.querySelector("[data-care-error]")?.textContent ?? ""'),'');
        const followId=Number((await evaluate('location.pathname')).split('/').at(-1));
        await login('receptionist');
        await go(`/follow-ups/${followId}`,'Boolean(document.querySelector("form[action$=book]"))');
        await fill('form[action$=book] [name=doctor_schedule_id]',String(receiverSchedule));
        await fill('form[action$=book] [name=appointment_date]',shiftedDate(localDateTime(0),7));
        await evaluate('document.querySelector("form[action$=book]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Booking kontrol aktif")');
        const followBooking=sql.prepare('SELECT active_appointment_id FROM follow_ups WHERE id=?').get(followId);
        const movedControl=await replaceThroughUi(followBooking.active_appointment_id,shiftedDate(localDateTime(0),14));
        assert.equal(sql.prepare('SELECT active_appointment_id FROM follow_ups WHERE id=?').get(followId).active_appointment_id,movedControl.id);
        await go(`/receptionist/appointments/${movedControl.id}`,'Boolean(document.querySelector("#cancel_reason"))');
        await fill('#cancel_reason','Pasien meminta penjadwalan ulang kontrol setelah pembatalan.');
        await evaluate('document.querySelector("#cancel_reason").closest("form").onsubmit=null; document.querySelector("#cancel_reason").closest("form").requestSubmit()');
        await waitFor('document.body.innerText.includes("Booking berhasil dibatalkan") || document.body.innerText.includes("Booking dibatalkan")');
        await go(`/follow-ups/${followId}`,'document.body.innerText.includes("Perlu review dokter / komunikasi pasien.")');
        assert.equal(sql.prepare('SELECT status FROM follow_ups WHERE id=?').get(followId).status,'OPEN');
        assert.equal(sql.prepare('SELECT COUNT(*) AS n FROM follow_up_bookings WHERE follow_up_id=?').get(followId).n,2);
        for(const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            await evaluate('new Promise(resolve=>setTimeout(resolve,350))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Follow-up overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'});
            writeFileSync(join(directory,`follow-up-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        const patientBill=sql.prepare('SELECT b.* FROM patient_bills b JOIN patient_bill_appointments a ON a.patient_bill_id=b.id WHERE a.appointment_id=?').get(child.appointment_id);
        assert.ok(patientBill,'Completed linked service must have one patient bill');
        const billedMinor=sql.prepare('SELECT SUM(billed_delta_minor) AS amount FROM patient_bill_entries WHERE patient_bill_id=?').get(patientBill.id).amount;
        const halfMinor=Math.floor(billedMinor/2); const discountMinor=Math.floor(billedMinor/10);
        const moneyText=minor=>(minor/100).toFixed(2);
        await login('receptionist');
        await go(`/patient-billing/bills/${patientBill.id}`,'Boolean(document.querySelector("form[action$=transactions]"))');
        await fill('form[action$=transactions] [name=amount]',moneyText(halfMinor));
        await evaluate('document.querySelector("form[action$=transactions] [name=funds_received_confirmed]").click(); document.querySelector("form[action$=transactions]").requestSubmit()');
        await waitFor(`Boolean(document.querySelector('[data-bill-balance] [data-status="PARTIALLY_PAID"]'))`);
        await fill('form[action$=transactions] [name=amount]',moneyText(billedMinor-halfMinor));
        await fill('form[action$=transactions] [name=method]','TRANSFER');
        await evaluate('document.querySelector("form[action$=transactions]").requestSubmit()');
        await waitFor(`Boolean(document.querySelector('[data-transactions] [data-status="PENDING_VERIFICATION"]'))`);
        const pendingPatientPayment=sql.prepare("SELECT * FROM patient_transactions WHERE patient_bill_id=? AND method='TRANSFER' ORDER BY id DESC LIMIT 1").get(patientBill.id);
        await go('/patient-billing/closings','Boolean(document.querySelector("[name=opening_cash]"))');
        await fill('[name=opening_cash]','0'); await fill('[name=counted_cash]',moneyText(halfMinor)); await fill('[name=counted_qris]','0'); await fill('[name=counted_transfer]','0');
        await evaluate('document.querySelector("form[data-care-form]").requestSubmit()');
        await waitFor('location.pathname.startsWith("/patient-billing/closings/")');
        const closed=sql.prepare('SELECT * FROM patient_cash_closings ORDER BY id DESC LIMIT 1').get(); assert.equal(closed.status,'APPROVED');
        const closedSnapshot=closed.snapshot;
        await go(`/patient-billing/bills/${patientBill.id}`,`Boolean(document.querySelector('form[action$="/${pendingPatientPayment.id}/verify"]'))`);
        const verifyPatientSelector=`form[action$="/${pendingPatientPayment.id}/verify"]`;
        await fill(`${verifyPatientSelector} [name=verification_reference]`,'Konfirmasi dana sintetis setelah closing.');
        await evaluate(`document.querySelector('${verifyPatientSelector} [name=funds_received_confirmed]').click();document.querySelector(${JSON.stringify(verifyPatientSelector)}).requestSubmit()`);
        await waitFor(`!document.querySelector('[data-transactions] [data-status="PENDING_VERIFICATION"]') && Boolean(document.querySelector('[data-bill-balance] [data-status="PAID"]'))`);
        assert.equal(sql.prepare('SELECT SUM(cash_delta_minor) AS amount FROM patient_bill_entries WHERE patient_bill_id=?').get(patientBill.id).amount,billedMinor);
        await fill('form[action$=requests] [name=kind]','DISCOUNT'); await fill('form[action$=requests] [name=amount]',moneyText(discountMinor));
        await fill('form[action$=requests] [name=reason]','Permohonan diskon operasional sintetis.');
        await evaluate('document.querySelector("form[action$=requests]").requestSubmit()');
        await waitFor(`Boolean(document.querySelector('[data-bill-proposals] [data-proposal-kind="DISCOUNT"] [data-status="REQUESTED"]'))`);
        const discountProposal=sql.prepare("SELECT id FROM patient_bill_requests WHERE patient_bill_id=? AND kind='DISCOUNT' ORDER BY id DESC LIMIT 1").get(patientBill.id);
        await login('admin');
        await go(`/patient-billing/bills/${patientBill.id}`,`Boolean(document.querySelector('form[action$="/${discountProposal.id}/decide/approve"]'))`);
        await evaluate(`document.querySelector('form[action$="/${discountProposal.id}/decide/approve"]').requestSubmit()`);
        await waitFor(`Boolean(document.querySelector('[data-bill-proposals] [data-proposal-kind="DISCOUNT"] [data-status="APPLIED"]'))`);
        await login('receptionist');
        await go(`/patient-billing/bills/${patientBill.id}`,'Boolean(document.querySelector("form[action$=requests]"))');
        await fill('form[action$=requests] [name=kind]','REFUND'); await fill('form[action$=requests] [name=amount]',moneyText(discountMinor));
        await fill('form[action$=requests] [name=patient_transaction_id]',String(pendingPatientPayment.id)); await fill('form[action$=requests] [name=refund_mode]','EXCESS_ALLOCATED');
        await fill('form[action$=requests] [name=reason]','Refund kelebihan sesudah diskon sah pada fixture.'); await evaluate('document.querySelector("form[action$=requests]").requestSubmit()');
        await waitFor(`Boolean(document.querySelector('[data-bill-proposals] [data-proposal-kind="REFUND"] [data-status="REQUESTED"]'))`);
        const refundProposal=sql.prepare("SELECT id FROM patient_bill_requests WHERE patient_bill_id=? AND kind='REFUND' ORDER BY id DESC LIMIT 1").get(patientBill.id);
        await login('admin');
        await go(`/patient-billing/bills/${patientBill.id}`,`Boolean(document.querySelector('form[action$="/${refundProposal.id}/decide/approve"]'))`);
        await evaluate(`document.querySelector('form[action$="/${refundProposal.id}/decide/approve"]').requestSubmit()`);
        await waitFor('document.body.innerText.includes("Persetujuan belum mencatat uang keluar")');
        assert.equal(sql.prepare('SELECT SUM(cash_delta_minor) AS amount FROM patient_bill_entries WHERE patient_bill_id=?').get(patientBill.id).amount,billedMinor);
        await login('receptionist');
        await go(`/patient-billing/bills/${patientBill.id}`,`Boolean(document.querySelector('form[action$="/${refundProposal.id}/refund"]'))`);
        const completeRefundSelector=`form[action$="/${refundProposal.id}/refund"]`;
        await fill(`${completeRefundSelector} [name=completion_reference]`,'Pengembalian kas sintetis telah selesai.');
        await evaluate(`document.querySelector('${completeRefundSelector} [name=actual_return_confirmed]').click();document.querySelector(${JSON.stringify(completeRefundSelector)}).requestSubmit()`);
        await waitFor(`Boolean(document.querySelector('[data-bill-balance] [data-status="COMPLETED"]')) && !document.body.innerText.includes("Persetujuan belum mencatat uang keluar")`);
        assert.equal(sql.prepare('SELECT SUM(cash_delta_minor) AS amount FROM patient_bill_entries WHERE patient_bill_id=?').get(patientBill.id).amount,billedMinor-discountMinor);
        assert.equal(sql.prepare('SELECT snapshot FROM patient_cash_closings WHERE id=?').get(closed.id).snapshot,closedSnapshot);
        for(const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false}); await evaluate('new Promise(resolve=>setTimeout(resolve,350))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Patient billing overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,`patient-billing-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        const refundDocument=sql.prepare("SELECT id FROM patient_billing_documents WHERE patient_bill_id=? AND kind='REFUND_RECEIPT' ORDER BY id DESC LIMIT 1").get(patientBill.id);
        const financialPrintSelector=`form[action$="/documents/${refundDocument.id}/print"]`;
        await evaluate(`const printForm=document.querySelector(${JSON.stringify(financialPrintSelector)});printForm.querySelector('[name=recipient_identity_verified]').click();printForm.removeAttribute('target');printForm.requestSubmit()`);
        await waitFor('document.body.innerText.includes("Uang dikembalikan pada bukti ini")');
        await login('admin');
        await go('/clinic-reports','Boolean(document.querySelector("[name=kind]"))');
        await fill('[name=kind]','finance'); await fill('[name=range_from]',localDateTime(0).slice(0,10)); await fill('[name=range_until]',localDateTime(0).slice(0,10)); await fill('[name=expires_at]',localDateTime(86400000));
        await evaluate('document.querySelector("form[data-care-form]").requestSubmit()');
        await waitFor('location.pathname.startsWith("/clinic-reports/") && Number(location.pathname.split("/").at(-1)) > 0');
        const reportId=Number((await evaluate('location.pathname')).split('/').at(-1));
        assert.equal(sql.prepare('SELECT status FROM clinic_report_exports WHERE id=?').get(reportId).status,'PENDING');
        execFileSync(phpBinary,[...php,'artisan','queue:work','database','--queue=reports','--once','--tries=1','--no-interaction'],{cwd:root,env,stdio:'pipe',timeout:30000});
        await go(`/clinic-reports/${reportId}`,'document.body.innerText.includes("Unduh CSV agregat")');
        const csv=await evaluate(`(async()=>{const response=await fetch('/clinic-reports/${reportId}/download',{headers:{Accept:'application/json'}});return {status:response.status,body:await response.text()};})()`);
        assert.equal(csv.status,200,csv.body.slice(0,500)); assert.ok(csv.body.includes(`net_cash_minor,${billedMinor-discountMinor},`)); assert.equal(csv.body.includes('Rizky Pratama'),false);
        for(const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false}); await evaluate('new Promise(resolve=>setTimeout(resolve,350))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Clinic report overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,`clinic-report-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }

        await login('patient');
        await go('/notifications','document.body.innerText.includes("Preferensi pengingat")');
        await evaluate('document.querySelector("input[type=checkbox][name=near_turn]").click();document.querySelector("form[action$=preferences]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Preferensi pengingat disimpan.") && !document.querySelector("input[type=checkbox][name=near_turn]").checked');
        assert.equal(JSON.parse(sql.prepare("SELECT reminder_preferences FROM users WHERE email='patient@clinicos.test'").get().reminder_preferences).near_turn,false);
        await go('/notifications','!document.querySelector("input[type=checkbox][name=near_turn]").checked');
        await evaluate('document.querySelector("input[type=checkbox][name=near_turn]").click();document.querySelector("form[action$=preferences]").requestSubmit()');
        await waitFor('document.body.innerText.includes("Preferensi pengingat disimpan.") && document.querySelector("input[type=checkbox][name=near_turn]").checked');
        const reminderQueue=sql.prepare("SELECT q.*,a.patient_id FROM queues q JOIN appointments a ON a.id=q.appointment_id JOIN patients p ON p.id=a.patient_id JOIN users u ON u.id=p.user_id WHERE u.email='patient@clinicos.test' AND a.status='BOOKED' AND NOT EXISTS(SELECT 1 FROM visits v WHERE v.appointment_id=a.id) ORDER BY q.id DESC LIMIT 1").get();
        assert.ok(reminderQueue,'Existing patient booking for reminder browser journey');
        sql.prepare("UPDATE queues SET queue_date=date('now','+7 hours'),status='WAITING',checked_in_at=datetime('now') WHERE id=?").run(reminderQueue.id);
        sql.prepare("UPDATE appointments SET appointment_date=date('now','+7 hours') WHERE id=?").run(reminderQueue.appointment_id);
        // Self-cancellation is tested before the existing session-start cutoff in this isolated fixture.
        const reminderStartsAt=localDateTime(2*60000);
        assert.equal(reminderStartsAt.slice(0,10),localDateTime(0).slice(0,10),'The reminder cancellation fixture needs two minutes before clinic-local midnight');
        sql.prepare("UPDATE doctor_schedules SET start_time=?,end_time='23:59',is_active=1 WHERE id=?").run(reminderStartsAt.slice(11),reminderQueue.doctor_schedule_id);
        sql.prepare("UPDATE queues SET status='SKIPPED' WHERE id<>? AND clinic_id=? AND doctor_schedule_id=? AND status='WAITING'").run(reminderQueue.id,reminderQueue.clinic_id,reminderQueue.doctor_schedule_id);
        execFileSync(phpBinary,[...php,'artisan','patients:queue-reminders','--no-interaction'],{cwd:root,env,stdio:'pipe',timeout:30000});
        execFileSync(phpBinary,[...php,'artisan','queue:work','database','--queue=default','--stop-when-empty','--tries=1','--no-interaction'],{cwd:root,env,stdio:'pipe',timeout:30000});
        const reminder=sql.prepare("SELECT * FROM patient_reminder_deliveries WHERE target_type='queue' AND target_id=? AND category='near_turn'").get(reminderQueue.id);
        assert.ok(reminder?.sent_at,'Near-turn command and real worker persist an inbox reminder');
        await go('/notifications','document.body.innerText.includes("Antrean Anda mendekat")');
        for(const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false}); await evaluate('new Promise(resolve=>setTimeout(resolve,250))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Reminder inbox overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,`patient-reminders-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        await go(`/patient/appointments/${reminderQueue.appointment_id}`,'document.body.innerText.includes("Antrean Anda mendekat")');
        await evaluate('document.querySelector("[data-cancel-booking-open]").click()');
        await waitFor('document.querySelector("[data-cancel-booking-form]").getBoundingClientRect().height > 0 && document.activeElement.id === "cancel_reason"');
        // shortcut: DOM keyboard events exercise the handler; physical keyboard and screen-reader checks remain manual.
        await evaluate('document.activeElement.dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",code:"Escape",bubbles:true}))');
        await waitFor('document.querySelector("[data-cancel-booking-form]").getBoundingClientRect().height === 0 && document.activeElement.matches("[data-cancel-booking-open]")');
        assert.equal(sql.prepare('SELECT status FROM appointments WHERE id=?').get(reminderQueue.appointment_id).status,'BOOKED','Escape retains the booking');
        await evaluate('document.querySelector("[data-cancel-booking-open]").click()');
        await waitFor('document.querySelector("[data-cancel-booking-form]").getBoundingClientRect().height > 0');
        for(const width of [375,768,1024,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false}); await evaluate('new Promise(resolve=>setTimeout(resolve,250))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`Cancellation confirmation overflow at ${width}px`);
            const screenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,`booking-cancel-confirm-${width}.png`),Buffer.from(screenshot.data,'base64'));
        }
        await evaluate('document.querySelector("[data-cancel-booking-form] button[type=submit]").click()');
        await waitFor('document.body.innerText.includes("CANCELLED") || document.body.innerText.includes("Dibatalkan")');
        await go('/notifications','document.body.innerText.includes("Pengingat ini sudah tidak berlaku")');

        for (const role of ['doctor','receptionist','admin','patient']) {
            await login(role);
            assert.equal(await evaluate('Boolean(document.querySelector("main > div.mb-5 > a"))'),false,'Dashboard has no loose module links');
            const nav=await evaluate('document.querySelector("[data-workspace-nav]").innerText');
            assert.ok(nav.includes(role==='patient'?'Kontrol saya':'Kontrol pasien'));
            if(role==='doctor') { assert.equal(nav.includes('Tagihan pasien'),false); assert.ok(nav.includes('Tugas pelayanan')); }
            if(role==='admin') { assert.equal(nav.includes('Resep pasien'),false); assert.ok(nav.includes('Laporan klinik')); }
            for(const width of [375,768,1024,1440]) {
                await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false}); await evaluate('new Promise(resolve=>setTimeout(resolve,250))');
                assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`${role} dashboard overflow at ${width}px`);
                const screenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,`${role}-workspace-${width}.png`),Buffer.from(screenshot.data,'base64'));
            }
            await call('Emulation.setDeviceMetricsOverride',{width:375,height:900,deviceScaleFactor:1,mobile:false});
            await evaluate(`document.querySelector('[aria-label="Buka navigasi"]').focus();document.querySelector('[aria-label="Buka navigasi"]').click()`);
            await waitFor('document.querySelector("#workspace-navigation").getAttribute("aria-modal") === "true" && document.querySelector("#workspace-navigation").getAttribute("aria-hidden") !== "true"');
            const navScreenshot=await call('Page.captureScreenshot',{format:'png'}); writeFileSync(join(directory,`${role}-navigation-375.png`),Buffer.from(navScreenshot.data,'base64'));
            await evaluate(`document.querySelector('#workspace-navigation a[href$="/follow-ups"]').click()`);
            await waitFor(`location.pathname === '/follow-ups' && Boolean(document.querySelector('#workspace-navigation a[aria-current="page"][href$="/follow-ups"]'))`);
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),`${role} follow-up page remains responsive after sidebar navigation`);
            if(role!=='admin') await go('/clinical-documents','document.body.innerText.includes("Resep")');
        }
        if(process.env.CLINICOS_PRESENTATION_SWEEP === '1') {
            await auditWorkspacePresentation({sql,call,evaluate,go,login,waitFor,directory,fixtures:{visit,child,documentId,followId,patientBill,closed,reportId,absence,handoffId}});
        }
        assert.equal(errors.length,0,'Scheduling and clinical workflows have no JavaScript exceptions');
        console.log(`Browser screenshots: ${directory}`);
    } finally {
        socket?.close();
        browser.kill();
        server.kill();
        sql.close();
    }
});
