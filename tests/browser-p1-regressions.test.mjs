import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

test('P1 regressions and staff security lifecycle in Chrome', {
    skip: process.env.CLINICOS_BROWSER_TEST !== '1', timeout: 180000,
}, async () => {
    const root = fileURLToPath(new URL('../', import.meta.url));
    const directory = mkdtempSync(join(tmpdir(), 'clinicos-qa-regression-'));
    const evidence = process.env.CLINICOS_BROWSER_EVIDENCE || join(root, 'qa-audit/evidence/p2-retest');
    mkdirSync(evidence, { recursive: true });
    const baseUrl = 'http://127.0.0.1:18953';
    const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: baseUrl,
        LARAVEL_STORAGE_PATH: join(directory, 'storage'),
        APP_CONFIG_CACHE: join(directory, 'config.php'), APP_ROUTES_CACHE: join(directory, 'routes.php'),
        DB_CONNECTION: 'sqlite', DB_DATABASE: join(directory, 'testing.sqlite'), DB_URL: '',
        QUEUE_CONNECTION: 'database', BROADCAST_CONNECTION: 'null', CACHE_STORE: 'array',
        SESSION_DRIVER: 'database', SESSION_DOMAIN: 'null', SESSION_SECURE_COOKIE: 'false',
        MAIL_MAILER: 'array', DOKU_CLIENT_ID: '', DOKU_SECRET_KEY: '' };
    const php = ['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=gd'];
    for (const path of ['framework/views', 'framework/cache', 'framework/sessions', 'logs', 'app/private', 'app/public']) {
        mkdirSync(join(env.LARAVEL_STORAGE_PATH, path), { recursive: true });
    }
    for (const command of [['migrate', '--force'], ['db:seed', '--class=DemoSeeder', '--force']]) {
        execFileSync('php', [...php, 'artisan', ...command, '--no-interaction'], { cwd: root, env, stdio: 'pipe' });
    }
    const server = spawn('php', [...php, '-S', '127.0.0.1:18953', '-t', 'public'], { cwd: root, env, stdio: 'ignore' });
    const chrome = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0',
        `--user-data-dir=${join(directory, 'chrome')}`, 'about:blank',
    ], { stdio: ['ignore', 'ignore', 'pipe'] });
    let socket;
    const steps = [];
    const { DatabaseSync } = await import('node:sqlite');
    const sql = new DatabaseSync(env.DB_DATABASE);
    try {
        const endpoint = await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('Chrome did not start')), 15000);
            chrome.stderr.on('data', chunk => {
                const match = chunk.toString().match(/DevTools listening on (ws:\/\/[^\s]+)/);
                if (match) { clearTimeout(timer); resolve(match[1]); }
            });
            chrome.on('error', reject);
        });
        const targets = await (await fetch(`http://${new URL(endpoint).host}/json/list`)).json();
        socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
        await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
        let nextId = 0;
        const pending = new Map();
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (! pending.has(message.id)) return;
            const { resolve, reject } = pending.get(message.id);
            pending.delete(message.id);
            message.error ? reject(new Error(message.error.message)) : resolve(message.result);
        });
        function call(method, params = {}) {
            return new Promise((resolve, reject) => {
                const id = ++nextId;
                pending.set(id, { resolve, reject });
                socket.send(JSON.stringify({ id, method, params }));
            });
        }
        async function evaluate(expression) {
            const result = await call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error('Browser expression failed');
            return result.result.value;
        }
        async function waitFor(expression) {
            for (let attempt = 0; attempt < 100; attempt++) {
                try { if (await evaluate(expression)) return; } catch { /* Navigation replaces the context. */ }
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            throw new Error('Browser condition timed out');
        }
        async function go(path, ready = 'document.readyState === "complete"') {
            await call('Page.navigate', { url: baseUrl + path });
            await new Promise(resolve => setTimeout(resolve, 200));
            await waitFor(ready);
        }
        async function fill(selector, value) {
            await evaluate(`(() => { const field = document.querySelector(${JSON.stringify(selector)});
                field.value = ${JSON.stringify(value)}; field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true })); })()`);
        }
        const credential = readFileSync(join(root, 'database/seeders/DemoUserSeeder.php'), 'utf8').match(/Hash::make\('([^']+)'\)/)?.[1];
        assert.ok(credential, 'Demo credential fixture is required');
        await call('Page.enable');
        await call('Runtime.enable');
        await call('Network.enable');
        await call('Emulation.setFocusEmulationEnabled', { enabled: true });
        async function login(role, email = `${role}@clinicos.test`, password = credential, remember = false) {
            await call('Network.clearBrowserCookies');
            await go(role === 'patient' ? '/login' : '/admin', 'Boolean(window.Livewire && document.querySelector("#email"))');
            await fill('#email', email);
            await fill('#password', password);
            if (remember) await evaluate('document.querySelector("#remember").click()');
            await evaluate('document.querySelector("form").requestSubmit()');
            await waitFor('location.pathname.includes("dashboard") || location.pathname === "/verify-email"');
        }
        async function restoreCookies(cookies) {
            await call('Network.clearBrowserCookies');
            await call('Network.setCookies', { cookies: cookies.map(({ name, value, domain, path, secure, httpOnly, sameSite }) => ({ name, value, domain, path, secure, httpOnly, sameSite })) });
        }
        async function selectFirst(name) {
            await waitFor(`Alpine.$data(document.querySelector('#${name}_search')).options.length > 0`);
            await evaluate(`document.querySelector('#${name}_search').focus()`);
            await waitFor(`Boolean(document.querySelector('#${name}_search').parentElement.querySelector('[x-ref=results] button')?.offsetParent)`);
            await evaluate(`document.querySelector('#${name}_search').parentElement.querySelector('[x-ref=results] button').click()`);
            await waitFor(`Boolean(document.querySelector('[name=${name}]').value)`);
        }
        await login('patient');
        await go('/clinic/klinik-sehat-sentosa/booking', 'Boolean(window.Alpine && window.bookingSelection)');
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `Booking fits ${width}px`);
        }
        await selectFirst('doctor_id');
        await selectFirst('service_id');
        const day = await evaluate('Alpine.$data(document.querySelector("[data-booking-fields]")).selectedDoctor.schedules[0].day');
        const date = new Date(await evaluate('document.querySelector("#appointment_date").min') + 'T00:00:00Z');
        date.setUTCDate(date.getUTCDate() + ((Number(day) - date.getUTCDay() + 7) % 7) + 7);
        await fill('#appointment_date', date.toISOString().slice(0, 10));
        await selectFirst('doctor_schedule_id');
        const selection = await evaluate('Object.fromEntries(["doctor_id", "service_id", "doctor_schedule_id", "idempotency_key"].map(name => [name, document.querySelector(`[name=${name}]`).value]))');
        await fill('#birth_date', '1995-01-01');
        await fill('#gender', 'FEMALE');
        await fill('#phone', '081234567890');
        await fill('#address', 'Synthetic browser fixture');
        await evaluate('document.querySelector("form").requestSubmit()');
        await waitFor('location.pathname.startsWith("/booking/") && Boolean(document.querySelector("#cancel_reason"))');
        const booking = sql.prepare('SELECT a.*, p.user_id AS patient_user_id FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.idempotency_key = ?').get(selection.idempotency_key);
        assert.ok(booking, 'Browser submission persists the booking');
        assert.equal(booking.doctor_id, Number(selection.doctor_id));
        assert.equal(booking.service_id, Number(selection.service_id));
        assert.equal(booking.doctor_schedule_id, Number(selection.doctor_schedule_id));
        assert.equal(booking.patient_user_id, sql.prepare('SELECT id FROM users WHERE email = ?').get('patient@clinicos.test').id);
        assert.equal(sql.prepare('SELECT COUNT(*) AS count FROM queues WHERE appointment_id = ?').get(booking.id).count, 1);
        steps.push({ issue: 'QA-01', status: 'PASS', action: 'Patient selects doctor, service, session and submits booking; persisted ownership and queue verified; 375/768/1024/1440px' });
        await go('/clinic/klinik-sehat-sentosa', 'location.hostname.startsWith("klinik-sehat-sentosa.") && Boolean(window.Alpine)');
        await go('/display/klinik-sehat-sentosa', 'Boolean(window.Alpine && document.querySelector("[data-realtime-public]"))');
        assert.equal(await evaluate('performance.getEntriesByType("navigation")[0].responseStatus'), 200);
        const activePatient = sql.prepare("SELECT p.name FROM patients p JOIN visits v ON v.patient_id = p.id WHERE v.status = 'IN_PROGRESS' LIMIT 1").get();
        assert.equal(await evaluate(`document.querySelector('[data-realtime-public]').innerText.includes(${JSON.stringify(activePatient.name)})`), false);
        steps.push({ area: 'public layout', status: 'PASS', action: 'Clinic subdomain and public queue display initialize; queue omits the active patient name' });

        await login('doctor');
        const visit = sql.prepare("SELECT v.id, m.id AS record_id FROM visits v JOIN medical_records m ON m.visit_id = v.id JOIN doctors d ON d.id = v.doctor_id JOIN users u ON u.id = d.user_id WHERE v.status = 'IN_PROGRESS' AND u.email = ? LIMIT 1").get('doctor@clinicos.test');
        assert.ok(visit, 'Demo provides an assigned active visit');
        await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave)');
        await evaluate(`(() => {
            document.querySelector('#critical_allergies_acknowledged')?.click();
            const bytes = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aPioAAAAASUVORK5CYII='), value => value.charCodeAt(0));
            Alpine.$data(document.querySelector('[data-rme-form]')).addPhotos([new File([bytes], 'history.png', { type: 'image/png' })]);
        })()`);
        await evaluate('document.querySelector("[data-rme-form]").requestSubmit()');
        await waitFor(`Boolean(document.querySelector('button[aria-label="Hapus history.png"]'))`);
        const photos = JSON.parse(sql.prepare('SELECT photos FROM medical_records WHERE id = ?').get(visit.record_id).photos);
        assert.equal(photos.length, 1, 'Draft upload is persisted');
        await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave)');
        await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.textContent.includes('Tinjau & finalisasi')).click()`);
        await waitFor('Boolean(document.querySelector("button[formaction]")?.offsetParent)');
        await evaluate('document.querySelector("[data-rme-form]").requestSubmit(document.querySelector("button[formaction]"))');
        await waitFor('Boolean(document.querySelector("[name=correction_reason]"))');
        assert.equal(sql.prepare('SELECT status FROM medical_records WHERE id = ?').get(visit.record_id).status, 'FINAL');
        await evaluate(`document.querySelector('button[aria-label="Hapus history.png"]').click()`);
        await fill('[name=correction_reason]', 'Synthetic attachment retention regression');
        await evaluate('document.querySelector("[data-rme-form]").requestSubmit()');
        await waitFor('Boolean(document.querySelector("[data-revision-photo]"))');
        assert.deepEqual(JSON.parse(sql.prepare('SELECT photos FROM medical_records WHERE id = ?').get(visit.record_id).photos), []);
        await evaluate('document.querySelector("[data-revision-photo]").closest("details").open = true');
        const historicalPhoto = await evaluate('document.querySelector("[data-revision-photo]").href');
        assert.equal(await evaluate(`fetch(${JSON.stringify(historicalPhoto)}).then(response => response.status)`), 200);
        assert.equal(await evaluate(`fetch('/doctor/visits/${visit.id}/photos/0').then(response => response.status)`), 404);
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), `Photo history fits ${width}px`);
        }
        steps.push({ issue: 'QA-02', status: 'PASS', action: 'Doctor uploads draft photo, finalizes, removes through reasoned correction and reads the historical private photo; current photo is 404' });

        await login('admin');
        const owner = sql.prepare('SELECT id FROM users WHERE email = ?').get('admin@clinicos.test');
        const ownedClinic = sql.prepare('SELECT id, owner_user_id FROM clinics WHERE owner_user_id = ?').get(owner.id);
        const subscription = sql.prepare('SELECT id FROM subscriptions WHERE clinic_id = ?').get(ownedClinic.id);
        await go('/profile', 'Boolean(window.Livewire && Array.from(document.forms).some(form => form.getAttribute("wire:submit") === "deleteUser"))');
        await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === 'Hapus akun').click()`);
        await waitFor('Boolean(document.querySelector("#password")?.offsetParent)');
        await fill('#password', credential);
        await evaluate(`Array.from(document.forms).find(form => form.getAttribute('wire:submit') === 'deleteUser').requestSubmit()`);
        await waitFor('document.body.innerText.includes("Hubungi pengelola platform")');
        assert.ok(sql.prepare('SELECT id FROM users WHERE id = ?').get(owner.id));
        assert.equal(sql.prepare('SELECT owner_user_id FROM clinics WHERE id = ?').get(ownedClinic.id).owner_user_id, owner.id);
        assert.ok(sql.prepare('SELECT id FROM subscriptions WHERE id = ?').get(subscription.id));
        await go('/subscription/payments');
        assert.equal(await evaluate('performance.getEntriesByType("navigation")[0].responseStatus'), 200);
        steps.push({ issue: 'QA-03', status: 'PASS', action: 'Owner self-delete rejected with explanation; user, clinic ownership, subscription and logged-in commercial access retained' });

        await go('/admin/staff/create', 'Boolean(window.Alpine && document.querySelector("#specialization"))');
        await fill('#name', 'Synthetic replacement doctor');
        await fill('#email', 'audit-staff@clinicos.test');
        await fill('#password', credential);
        await fill('#specialization', 'Synthetic QA');
        await evaluate(`document.querySelector('input[name="service_ids[]"]').click()`);
        await evaluate('document.querySelector("main form").requestSubmit()');
        await waitFor('location.pathname === "/admin/staff"');
        const replacement = sql.prepare('SELECT d.id, d.user_id FROM doctors d JOIN users u ON u.id = d.user_id WHERE u.email = ?').get('audit-staff@clinicos.test');
        assert.ok(replacement, 'Staff creation provides a same-clinic replacement without overlapping sessions');
        const originalSchedule = sql.prepare('SELECT doctor_id, quota FROM doctor_schedules WHERE id = ?').get(booking.doctor_schedule_id);
        const editSchedule = `/admin/doctor-schedules/${booking.doctor_schedule_id}/edit`;
        await go(editSchedule);
        await fill('#doctor_id', String(replacement.id));
        await evaluate('document.querySelector("main form").requestSubmit()');
        await waitFor('document.body.innerText.includes("Dokter, hari, dan jam sesi yang sudah memiliki booking atau antrean tidak dapat diganti.")');
        assert.equal(sql.prepare('SELECT doctor_id FROM doctor_schedules WHERE id = ?').get(booking.doctor_schedule_id).doctor_id, originalSchedule.doctor_id);
        assert.equal(sql.prepare('SELECT doctor_id FROM appointments WHERE id = ?').get(booking.id).doctor_id, originalSchedule.doctor_id);
        assert.equal(sql.prepare('SELECT doctor_id FROM queues WHERE appointment_id = ?').get(booking.id).doctor_id, originalSchedule.doctor_id);
        await fill('#doctor_id', String(originalSchedule.doctor_id));
        await fill('#quota', String(originalSchedule.quota + 1));
        await evaluate('document.querySelector("main form").requestSubmit()');
        await waitFor('location.pathname === "/admin/doctor-schedules"');
        assert.equal(sql.prepare('SELECT quota FROM doctor_schedules WHERE id = ?').get(booking.doctor_schedule_id).quota, originalSchedule.quota + 1);
        steps.push({ issue: 'QA-04', status: 'PASS', action: 'Admin creates replacement staff, attempts booked-session reassignment and receives validation; doctor links unchanged; same-doctor quota update succeeds' });

        const adminCookies = (await call('Network.getAllCookies')).cookies;
        for (const [role, email] of [['doctor', 'audit-staff@clinicos.test'], ['receptionist', 'audit-receptionist@clinicos.test']]) {
            if (role === 'receptionist') {
                await restoreCookies(adminCookies);
                await go('/admin/staff/create', 'Boolean(window.Alpine && document.querySelector("#role"))');
                await fill('#role', 'Receptionist');
                await fill('#name', 'Synthetic receptionist');
                await fill('#email', email);
                await fill('#password', credential);
                await evaluate('document.querySelector("main form").requestSubmit()');
                await waitFor('location.pathname === "/admin/staff"');
            }
            const staff = sql.prepare('SELECT id, email_verified_at FROM users WHERE email = ?').get(email);
            assert.equal(staff.email_verified_at, null);
            await login(role, email);
            assert.equal(await evaluate('location.pathname'), '/verify-email');
            await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.getAttribute('wire:click') === 'sendVerification').click()`);
            await waitFor('document.body.innerText.includes("Tautan verifikasi baru telah dikirim")');
            const verificationUrl = execFileSync('php', [...php, '-r',
                `require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $user = App\\Models\\User::query()->findOrFail(${staff.id}); echo (new Illuminate\\Auth\\Notifications\\VerifyEmail)->toMail($user)->actionUrl;`,
            ], { cwd: root, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
            const signed = new URL(verificationUrl);
            await go(signed.pathname + signed.search, `location.pathname === '/${role}/dashboard'`);
            assert.ok(sql.prepare('SELECT email_verified_at FROM users WHERE id = ?').get(staff.id).email_verified_at);
            const changedPassword = credential + '-changed';
            await go('/profile');
            await fill('#update_password_current_password', credential);
            await fill('#update_password_password', changedPassword);
            await fill('#update_password_password_confirmation', changedPassword);
            await evaluate(`Array.from(document.forms).find(form => form.getAttribute('wire:submit') === 'updatePassword').requestSubmit()`);
            await waitFor('document.querySelector("#update_password_password").value === ""');
            await login(role, email, changedPassword);
            const staffCookies = (await call('Network.getAllCookies')).cookies;
            const roleRecord = sql.prepare('SELECT r.id FROM roles r JOIN model_has_roles m ON m.role_id = r.id WHERE m.model_id = ?').get(staff.id);
            const permission = sql.prepare('SELECT id FROM permissions WHERE name = ?').get('dashboard.view');
            sql.prepare('DELETE FROM role_has_permissions WHERE role_id = ? AND permission_id = ?').run(roleRecord.id, permission.id);
            assert.equal(await evaluate(`fetch('/${role}/dashboard').then(response => response.status)`), 403);
            sql.prepare('INSERT INTO role_has_permissions (role_id, permission_id) VALUES (?, ?)').run(roleRecord.id, permission.id);
            assert.equal(await evaluate(`fetch('/${role}/dashboard').then(response => response.status)`), 200);
            sql.prepare('DELETE FROM model_has_roles WHERE model_id = ?').run(staff.id);
            assert.equal(await evaluate(`fetch('/${role}/dashboard').then(response => response.status)`), 403);
            sql.prepare('INSERT INTO model_has_roles (role_id, model_type, model_id) VALUES (?, ?, ?)').run(roleRecord.id, 'App\\Models\\User', staff.id);
            await restoreCookies(adminCookies);
            await go(`/admin/staff/${staff.id}/edit`);
            await evaluate('document.querySelector("input[type=checkbox][name=is_active]").click()');
            await evaluate('document.querySelector("main form").requestSubmit()');
            await waitFor('location.pathname === "/admin/staff"');
            assert.equal(sql.prepare('SELECT is_active FROM users WHERE id = ?').get(staff.id).is_active, 0);
            if (role === 'doctor') assert.equal(sql.prepare('SELECT is_active FROM doctors WHERE user_id = ?').get(staff.id).is_active, 0);
            await restoreCookies(staffCookies);
            await go(`/${role}/dashboard`, 'location.pathname === "/admin"');
            await fill('#email', email);
            await fill('#password', changedPassword);
            await evaluate('document.querySelector("form").requestSubmit()');
            await waitFor('document.body.innerText.includes("akun tidak aktif")');
            await restoreCookies(adminCookies);
            await go(`/admin/staff/${staff.id}/edit`);
            await evaluate('document.querySelector("input[type=checkbox][name=is_active]").click()');
            await evaluate('document.querySelector("main form").requestSubmit()');
            await waitFor('location.pathname === "/admin/staff"');
            await login(role, email, changedPassword, true);
            assert.equal(await evaluate('location.pathname'), `/${role}/dashboard`);
            steps.push({ area: 'staff', role, status: 'PASS', action: 'Creation, unverified login gate, resend, signed verification, self password setup/login, live permission and role revocation, deactivation revokes existing session/new login, reactivation' });
            const activeCookies = (await call('Network.getAllCookies')).cookies;
            const rememberedCookies = activeCookies.filter(cookie => cookie.name.startsWith('remember_'));
            assert.ok(rememberedCookies.length, 'Remember-me fixture must have a recaller cookie');
            await restoreCookies(adminCookies);
            await go(`/admin/staff/${staff.id}/edit`);
            const adminPassword = credential + '-admin-reset';
            await fill('#password', adminPassword);
            await evaluate('document.querySelector("main form").requestSubmit()');
            await waitFor('location.pathname === "/admin/staff"');
            await restoreCookies(activeCookies);
            const existingSession = await evaluate(`fetch('/${role}/dashboard').then(response => ({ path: new URL(response.url).pathname, redirected: response.redirected }))`);
            assert.equal(existingSession.path, '/admin', 'Old session must redirect to staff login after admin password change');
            assert.equal(existingSession.redirected, true);
            await restoreCookies(rememberedCookies);
            assert.equal(await evaluate(`fetch('/${role}/dashboard').then(response => new URL(response.url).pathname)`), '/admin', 'Old remember-me cookie must not restore access');
            steps.push({ area: 'staff password/session', role, status: 'PASS', action: 'Admin password replacement revokes the old session; browser redirects to staff login' });
            await login(role, email, adminPassword);
            const beforeEmailCookies = (await call('Network.getAllCookies')).cookies;
            await restoreCookies(adminCookies);
            await go(`/admin/staff/${staff.id}/edit`);
            const changedEmail = `updated-${role}@clinicos.test`;
            await fill('#email', changedEmail);
            await evaluate('document.querySelector("main form").requestSubmit()');
            await waitFor('location.pathname === "/admin/staff"');
            assert.equal(sql.prepare('SELECT email_verified_at FROM users WHERE id = ?').get(staff.id).email_verified_at, null);
            await restoreCookies(beforeEmailCookies);
            await go(`/${role}/dashboard`, 'location.pathname === "/verify-email"');
            assert.equal(await evaluate(`fetch(${JSON.stringify(verificationUrl)}).then(response => response.status)`), 403);
            const newVerification = execFileSync('php', [...php, '-r',
                `require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $user = App\\Models\\User::query()->findOrFail(${staff.id}); echo (new Illuminate\\Auth\\Notifications\\VerifyEmail)->toMail($user)->actionUrl;`,
            ], { cwd: root, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
            const newSigned = new URL(newVerification);
            await go(newSigned.pathname + newSigned.search, `location.pathname === '/${role}/dashboard'`);
            assert.ok(sql.prepare('SELECT email_verified_at FROM users WHERE id = ?').get(staff.id).email_verified_at);
            await login(role, changedEmail, adminPassword);
            steps.push({ area: 'staff changed email', role, status: 'PASS', action: 'New address requires verification, old signed link is rejected, new signed link and new-address login work' });
            await go('/profile');
            await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === 'Hapus akun').click()`);
            await waitFor('Boolean(document.querySelector("#password")?.offsetParent)');
            await fill('#password', adminPassword);
            await evaluate(`Array.from(document.forms).find(form => form.getAttribute('wire:submit') === 'deleteUser').requestSubmit()`);
            await waitFor('location.pathname === "/"');
            assert.equal(sql.prepare('SELECT COUNT(*) AS count FROM users WHERE id = ?').get(staff.id).count, 0);
            assert.equal(await evaluate(`fetch('/${role}/dashboard').then(response => new URL(response.url).pathname)`), '/admin');
            assert.equal(sql.prepare('SELECT COUNT(*) AS count FROM appointments WHERE id = ?').get(booking.id).count, 1);
            steps.push({ area: 'staff self-delete', role, status: 'PASS', action: 'Staff without clinical references self-deletes; user is removed, dashboard requires login, existing unrelated booking remains' });
        }
        await login('doctor');
        const historyBefore = sql.prepare('SELECT COUNT(*) AS count FROM visits').get().count;
        await go('/profile');
        await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === 'Hapus akun').click()`);
        await waitFor('Boolean(document.querySelector("#password")?.offsetParent)');
        await fill('#password', credential);
        await evaluate(`Array.from(document.forms).find(form => form.getAttribute('wire:submit') === 'deleteUser').requestSubmit()`);
        await waitFor('document.body.innerText.includes("menonaktifkan akses")');
        assert.equal(sql.prepare('SELECT COUNT(*) AS count FROM visits').get().count, historyBefore);
        assert.equal(await evaluate(`fetch('/doctor/dashboard').then(response => new URL(response.url).pathname)`), '/doctor/dashboard');
        steps.push({ issue: 'QA-14', status: 'PASS', action: 'Doctor with clinical history receives form error before logout; history and authenticated access remain' });

        await restoreCookies(adminCookies);
        const adminRole = sql.prepare('SELECT id FROM roles WHERE name = ?').get('Clinic Admin');
        const doctorCreate = sql.prepare('SELECT id FROM permissions WHERE name = ?').get('doctor.create');
        sql.prepare('DELETE FROM role_has_permissions WHERE role_id = ? AND permission_id = ?').run(adminRole.id, doctorCreate.id);
        await go('/admin/staff/create');
        await fill('#name', 'Synthetic restricted create');
        await fill('#email', 'denied-doctor@clinicos.test');
        await fill('#password', credential);
        await fill('#specialization', 'Synthetic QA');
        await evaluate('document.querySelector("main form").requestSubmit()');
        await waitFor('document.readyState === "complete" && performance.getEntriesByType("navigation")[0].responseStatus === 403');
        assert.equal(sql.prepare('SELECT COUNT(*) AS count FROM users WHERE email = ?').get('denied-doctor@clinicos.test').count, 0);
        steps.push({ issue: 'QA-13', status: 'PASS', action: 'Admin retaining receptionist.create cannot submit Doctor creation; 403 and no account persisted' });
    } finally {
        writeFileSync(join(evidence, 'p1-browser-steps.json'), JSON.stringify(steps, null, 2));
        sql.close();
        socket?.close();
        chrome.kill();
        server.kill();
    }
});
