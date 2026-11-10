import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const enabled = process.env.CLINICOS_BROWSER_TEST === '1';

test('clinical and booking workflows in Chrome with an isolated demo database', { skip: !enabled, timeout: 180000 }, async () => {
    const { DatabaseSync } = await import('node:sqlite');
    const directory = mkdtempSync(join(tmpdir(), 'clinicos-browser-'));
    const database = join(directory, 'testing.sqlite');
    const root = resolve(import.meta.dirname, '..');
    const baseUrl = 'http://127.0.0.1:18947';
    const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: baseUrl,
        APP_CONFIG_CACHE: join(directory, 'config.php'), APP_ROUTES_CACHE: join(directory, 'routes.php'),
        DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', QUEUE_CONNECTION: 'sync',
        BROADCAST_CONNECTION: 'null', CACHE_STORE: 'array', SESSION_DRIVER: 'database',
        SESSION_DOMAIN: 'null', SESSION_SECURE_COOKIE: 'false', MAIL_MAILER: 'array' };
    const php = ['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=gd'];
    for (const command of [['migrate', '--force'], ['db:seed', '--class=DemoSeeder', '--force']]) {
        execFileSync('php', [...php, 'artisan', ...command, '--no-interaction'], { cwd: root, env, stdio: 'pipe' });
    }
    const server = spawn('php', [...php, '-S', '127.0.0.1:18947', '-t', 'public'], { cwd: root, env, stdio: 'ignore' });
    const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0',
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
            if (message.method === 'Page.javascriptDialogOpening') call('Page.handleJavaScriptDialog', { accept: true });
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
            throw new Error('Browser condition timed out: ' + expression + ' ' + status);
        }
        async function go(path, ready) {
            await call('Page.navigate', { url: baseUrl + path });
            await waitFor(ready);
        }
        async function fill(selector, value) {
            await evaluate(`(() => { const field = document.querySelector(${JSON.stringify(selector)}); field.value = ${JSON.stringify(value)}; field.dispatchEvent(new Event('input', {bubbles:true})); field.dispatchEvent(new Event('change', {bubbles:true})); })()`);
        }
        const secret = readFileSync(join(root, 'database/seeders/DemoUserSeeder.php'), 'utf8').match(/Hash::make\('([^']+)'\)/)?.[1];
        assert.ok(secret, 'Demo credentials must be configured');
        async function login(prefix) {
            await call('Network.clearBrowserCookies');
            await go(prefix === 'patient' ? '/login' : '/admin', 'Boolean(window.Livewire && document.querySelector("#email"))');
            await fill('#email', `${prefix}@clinicos.test`);
            await fill('#password', secret);
            await evaluate('document.querySelector("form").requestSubmit()');
            await waitFor('location.pathname.includes("dashboard")');
        }
        await call('Page.enable');
        await call('Runtime.enable');
        await call('Network.enable');
        await call('Page.bringToFront');
        await call('Emulation.setFocusEmulationEnabled', { enabled: true });
        await login('doctor');
        const visit = sql.prepare("SELECT v.id, v.medical_record_id FROM (SELECT visits.*, medical_records.id AS medical_record_id FROM visits JOIN medical_records ON medical_records.visit_id = visits.id) v JOIN doctors d ON d.id = v.doctor_id JOIN users u ON u.id = d.user_id WHERE v.status = 'IN_PROGRESS' AND u.email = 'doctor@clinicos.test' LIMIT 1").get();
        assert.ok(visit, 'Demo must contain an active doctor visit');
        await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave && document.querySelector("#diagnosis_name_0"))');
        await evaluate('Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 10000))])');
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            await evaluate('new Promise(resolve => setTimeout(resolve, 250))');
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `RME overflow at ${width}px`);
            const screenshot = await call('Page.captureScreenshot', { format: 'png' });
            writeFileSync(join(directory, `rme-${width}.png`), Buffer.from(screenshot.data, 'base64'));
            await evaluate('document.querySelector("#structured-clinical-heading").scrollIntoView({block:"start"})');
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
        console.log(`Browser screenshots: ${directory}`);
    } finally {
        socket?.close();
        browser.kill();
        server.kill();
        sql.close();
    }
});
