import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';

const config = JSON.parse(readFileSync('qa-audit/production-readiness/evidence/runtime.json', 'utf8'));
const require = createRequire(join(config.root, 'tools/package.json'));
const { chromium } = require('playwright');
const evidence = 'qa-audit/production-readiness/evidence';
const base = process.env.APP_URL;
const results = [];
function audit(action, input = {}) {
    try {
        return JSON.parse(execFileSync('php', ['-d', 'extension=gd', 'qa-audit/production-readiness/tests/audit.php', action],
            { env: { ...process.env, APP_ENV: 'testing' }, input: JSON.stringify(input), encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }));
    } catch { throw new Error(`Private audit helper failed (${action})`); }
}
const fixture = process.env.AUDIT_REUSE_FIXTURE === '1' ? JSON.parse(readFileSync(join(config.root, 'fixture.json'))) : audit('fixture');
const users = fixture.main.users;
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const clients = {};
const errors = [];
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function check(name, body) {
    const started = Date.now();
    try {
        const detail = await body();
        results.push({ name, status: 'PASS', duration_ms: Date.now() - started, detail });
    } catch (error) {
        writeFileSync(join(config.root, 'last-browser-error.txt'), error.stack || error.message);
        writeFileSync(join(config.root, `browser-error-${results.length}.txt`), error.stack || error.message);
        results.push({ name, status: error.message.startsWith('Dependency:') ? 'BLOCKED' : 'FAIL', duration_ms: Date.now() - started,
            error_class: error.name, checkpoint: checkpoint, reason: error.message.startsWith('Dependency:') ? error.message : undefined });
    }
    writeFileSync(join(evidence, 'playwright-results.json'), JSON.stringify({ playwright: require('playwright/package.json').version,
        browser: browser.version(), results, page_errors: errors }, null, 2));
    console.log(JSON.stringify({ name, status: results.at(-1).status, checkpoint: results.at(-1).checkpoint }));
}
let checkpoint = 'initialization';
async function login(name, user, patient = false, password = 'password') {
    checkpoint = `login-${name}`;
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push({ client: name, name: error.name }));
    await page.goto(base + (patient ? '/login' : '/admin'));
    await page.waitForFunction(() => Boolean(window.Livewire));
    await page.locator('#email').fill(user.email);
    await page.locator('#password').fill(password);
    await page.locator('form[wire\\:submit="login"] button[type=submit]').click();
    await page.waitForURL(url => url.pathname.includes('dashboard') || url.pathname === '/verify-email', { timeout: 20000 });
    await page.waitForLoadState('domcontentloaded');
    clients[name] = { page, context, user };
    return clients[name];
}
async function csrf(page) { return page.locator('meta[name=csrf-token]').getAttribute('content'); }
async function request(client, path, method = 'GET', data) {
    const token = await csrf(client.page);
    const response = await client.context.request.fetch(base + path, { method, data,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, maxRedirects: 0 });
    return { status: response.status(), response };
}
async function waitUntil(fn, timeout = 12000) {
    const end = Date.now() + timeout;
    while (Date.now() < end) { if (await fn()) return; await sleep(100); }
    throw new Error('Audit condition timed out');
}
async function select(page, name) {
    checkpoint = `booking-select-${name}`;
    await page.locator(`#${name}_search`).click();
    await page.locator(`#${name}_search`).press('ArrowDown');
    await page.waitForFunction(() => document.activeElement?.tagName === 'BUTTON');
    await page.keyboard.press('Enter');
    await waitUntil(async () => Boolean(await page.locator(`[name=${name}]`).inputValue()));
}
function inboxUrl(email, part) {
    if (!existsSync(join(config.root, 'inbox.json'))) return null;
    const inbox = JSON.parse(readFileSync(join(config.root, 'inbox.json')));
    for (const item of [...inbox].reverse()) {
        if (!item.recipients.includes(email)) continue;
        const raw = readFileSync(item.file, 'utf8').replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/g, (_, value) => String.fromCharCode(parseInt(value, 16)));
        const urls = raw.match(/http:\/\/127\.0\.0\.1:\d+\/[^\s"<>]+/g) || [];
        const url = urls.find(value => value.includes(part));
        if (url) return url.replaceAll('&amp;', '&');
    }
    return null;
}
let appointmentId, queueId, visitId, staffId;
const staffEmail = `staff-${randomUUID().slice(0, 8)}@clinicos.test`;
const staffPassword = 'Audit-setup-346!';
async function bookAnother(phone) {
    const response = await request(clients.receptionist, '/receptionist/appointments', 'POST', {
        patient_id: users.patient.patient_id,
        doctor_id: users.doctor.doctor_id, service_id: fixture.main.service_id, doctor_schedule_id: users.doctor.schedule_id,
        appointment_date: fixture.main.date, idempotency_key: randomUUID(), name: 'Synthetic operational patient',
        birth_date: '1990-01-01', gender: 'MALE', phone });
    assert.equal(response.status, 302);
    return audit('main-state').queues.at(-1).id;
}
try {
    await check('UAT: all five roles and owner can open their dashboard', async () => {
        for (const [name, patient] of [['owner', false], ['admin', false], ['receptionist', false], ['receptionist2', false], ['doctor', false], ['doctor2', false], ['patient', true]]) {
            await login(name, users[name], patient);
            assert.ok(new URL(clients[name].page.url()).pathname.includes('dashboard'), 'Dashboard must open');
        }
        await login('platform', fixture.platform);
        await login('other', fixture.other.users.receptionist);
        return { simultaneous_browser_contexts: Object.keys(clients).length, roles: 5, owner_persona: true };
    });
    await check('UAT: desktop/tablet/mobile and native keyboard drawer', async () => {
        checkpoint = 'responsive-native-keyboard';
        const page = clients.receptionist.page;
        const viewports = [];
        for (const width of [375, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 950 });
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'No horizontal page overflow');
            viewports.push(width);
        }
        await page.setViewportSize({ width: 375, height: 950 });
        const trigger = page.locator('[aria-controls="workspace-navigation"]');
        await trigger.click();
        await page.keyboard.press('Escape');
        await waitUntil(async () => await trigger.getAttribute('aria-expanded') === 'false');
        assert.ok(await trigger.evaluate(element => element === document.activeElement), 'Drawer must restore focus');
        await page.setViewportSize({ width: 1440, height: 1000 });
        return { viewports, native_keyboard_escape: true };
    });
    await check('UAT: admin creates staff, real SMTP inbox receives verification, staff verifies', async () => {
        const page = clients.admin.page;
        checkpoint = 'create-staff-ui';
        await page.goto(base + '/admin/staff/create');
        await page.locator('#name').fill('Audit New Receptionist');
        await page.locator('#email').fill(staffEmail);
        await page.locator('#role').selectOption('Receptionist');
        await page.locator('#password').fill(staffPassword);
        await page.getByRole('button', { name: 'Buat akun staf' }).click();
        await page.waitForURL('**/admin/staff');
        staffId = audit('staff-id', { email: staffEmail }).id;
        checkpoint = 'smtp-verification-inbox';
        await waitUntil(() => Boolean(inboxUrl(staffEmail, '/verify-email/')));
        const staff = await login('new-staff', { id: staffId, email: staffEmail }, false, staffPassword);
        assert.equal(new URL(staff.page.url()).pathname, '/verify-email');
        await staff.page.goto(inboxUrl(staffEmail, '/verify-email/'));
        await staff.page.waitForURL(url => url.pathname.includes('dashboard'));
        assert.equal(audit('staff-id', { email: staffEmail }).verified, true);
        return { smtp_received: true, verified_through_signed_link: true };
    });
    await check('UAT: receptionist books through keyboard-operated form', async () => {
        const page = clients.receptionist.page;
        checkpoint = 'booking-form-selection';
        await page.goto(base + '/receptionist/appointments/create');
        await page.locator('#new_patient_name').fill('Synthetic operational patient');
        await page.locator('#new_patient_birth_date').fill('1990-01-01');
        await page.locator('#new_patient_phone').fill('081212120001');
        await select(page, 'doctor_id');
        await page.locator('#appointment_date').fill(fixture.main.date);
        await select(page, 'service_id');
        await select(page, 'doctor_schedule_id');
        await page.getByRole('button', { name: 'Buat booking', exact: true }).click();
        await page.waitForURL(/\/receptionist\/appointments\/\d+$/);
        appointmentId = Number(new URL(page.url()).pathname.split('/').at(-1));
        const state = audit('main-state');
        queueId = state.queues.find(item => item.appointment_id === appointmentId).id;
        assert.equal(state.appointments.find(item => item.id === appointmentId).status, 'BOOKED');
        return { booked: 1, queued: 1, native_keyboard_selection: true };
    });
    await check('Realtime: real sockets, two staff browsers, cross-tenant denial', async () => {
        if (!queueId) throw new Error('Dependency: booking failed');
        checkpoint = 'websocket-subscriptions';
        for (const name of ['receptionist', 'receptionist2', 'other']) {
            const page = clients[name].page;
            await page.waitForFunction(() => Boolean(window.Echo));
            await page.evaluate(clinic => {
                window.auditEvents = [];
                window.auditSubscriptionError = null;
                window.Echo.private(`clinic.${clinic}.queues`).listen('.queue.updated', payload => window.auditEvents.push({ payload, at: Date.now() }))
                    .error(error => window.auditSubscriptionError = error.status);
            }, fixture.main.clinic_id);
        }
        for (const name of ['receptionist', 'receptionist2']) {
            await clients[name].page.waitForFunction(clinic => window.Echo.connector.pusher.channel(`private-clinic.${clinic}.queues`)?.subscribed, fixture.main.clinic_id);
        }
        await clients.other.page.waitForFunction(() => window.auditSubscriptionError === 403);
        const started = Date.now();
        const checked = await request(clients.receptionist, `/queues/${queueId}/check-in`, 'POST');
        assert.equal(checked.status, 200);
        await waitUntil(async () => (await clients.receptionist2.page.evaluate(() => window.auditEvents.length)) > 0);
        const observations = {};
        for (const name of ['receptionist', 'receptionist2']) {
            const received = await clients[name].page.evaluate(() => window.auditEvents.at(-1));
            const keys = Object.keys(received.payload).sort();
            assert.deepEqual(keys, ['display_number', 'doctor_id', 'queue_date', 'queue_id', 'status', 'updated_at']);
            observations[name] = { latency_ms: received.at - started, payload_keys: keys };
        }
        assert.equal(await clients.other.page.evaluate(() => window.auditEvents.length), 0);
        return { ...observations, foreign_tenant_auth: 403, foreign_tenant_events: 0 };
    });
    await check('Realtime: connection loss and real reconnection', async () => {
        checkpoint = 'websocket-reconnect';
        const page = clients.receptionist2.page;
        await page.evaluate(() => window.Echo.disconnect());
        await page.waitForFunction(() => window.Echo.connector.pusher.connection.state === 'disconnected');
        await page.evaluate(() => window.Echo.connector.pusher.connect());
        await page.waitForFunction(clinic => window.Echo.connector.pusher.connection.state === 'connected' && window.Echo.connector.pusher.channel(`private-clinic.${clinic}.queues`)?.subscribed, fixture.main.clinic_id);
        const before = await page.evaluate(() => window.auditEvents.length);
        const called = await request(clients.receptionist, `/queues/${queueId}/call`, 'POST');
        assert.equal(called.status, 200);
        await waitUntil(async () => await page.evaluate(() => window.auditEvents.length) > before);
        return { disconnected: true, reconnected: true, event_after_reconnect: true };
    });
    await check('UAT: doctor starts, saves photo draft, finalizes, and corrects with history', async () => {
        if (!queueId) throw new Error('Dependency: booking failed');
        checkpoint = 'doctor-start';
        const started = await request(clients.doctor, `/doctor/queues/${queueId}/start`, 'POST');
        assert.equal(started.status, 200);
        visitId = audit('main-state').visits.at(-1).id;
        const page = clients.doctor.page;
        await page.goto(base + `/doctor/visits/${visitId}`);
        await page.locator('[name=subjective]').fill('Synthetic subjective audit');
        await page.locator('[name=assessment]').fill('Synthetic assessment audit');
        await page.locator('[name=plan]').fill('Synthetic plan audit');
        await page.locator('#photos').setInputFiles({ name: 'audit.png', mimeType: 'image/png',
            buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==', 'base64') });
        checkpoint = 'draft-photo-save';
        await page.getByRole('button', { name: 'Simpan draft', exact: true }).click();
        await waitUntil(() => audit('main-state').visits.at(-1).version === 1);
        await page.reload();
        assert.equal(audit('main-state').visits.at(-1).photo_count, 1);
        checkpoint = 'finalize-ui';
        await page.getByRole('button', { name: 'Tinjau & finalisasi', exact: true }).click();
        await page.getByRole('button', { name: 'Finalisasi kunjungan', exact: true }).click();
        await waitUntil(() => audit('main-state').visits.at(-1).record_status === 'FINAL');
        await page.reload();
        checkpoint = 'correction-ui';
        await page.locator('[name=correction_reason]').fill('Synthetic operational correction audit');
        await page.locator('[name=assessment]').fill('Synthetic corrected assessment');
        await page.getByRole('button', { name: 'Simpan Modification', exact: true }).click();
        await waitUntil(() => audit('main-state').visits.at(-1).revision_count === 1);
        const state = audit('main-state');
        assert.equal(state.visits.at(-1).status, 'COMPLETED');
        assert.equal(state.queues.find(item => item.id === queueId).status, 'COMPLETED');
        assert.equal(state.appointments.find(item => item.id === appointmentId).status, 'COMPLETED');
        return { draft_uploaded: true, finalized_atomically: true, correction_revision: 1 };
    });
    await check('Security: clinical page and private photo role matrix', async () => {
        if (!visitId) throw new Error('Dependency: visit unavailable');
        checkpoint = 'medical-role-matrix';
        const statuses = {};
        for (const name of ['owner', 'admin', 'receptionist', 'doctor2', 'patient', 'platform', 'other']) {
            const page = await request(clients[name], `/doctor/visits/${visitId}`);
            const photo = await request(clients[name], `/doctor/visits/${visitId}/photos/0`);
            assert.ok([403, 404].includes(page.status));
            assert.ok([403, 404].includes(photo.status));
            statuses[name] = { page: page.status, photo: photo.status };
        }
        assert.equal((await request(clients.doctor, `/doctor/visits/${visitId}/photos/0`)).status, 200);
        assert.equal((await clients.doctor.context.request.get(base + '/storage/medical-records/audit.png')).status(), 404);
        return statuses;
    });
    await check('SMTP: reset link delivery, reset password, old session revoked, new login works', async () => {
        if (!staffId || !clients['new-staff']) throw new Error('Dependency: staff verification unavailable');
        checkpoint = 'request-password-reset';
        const context = await browser.newContext();
        const page = await context.newPage();
        await page.goto(base + '/forgot-password');
        await page.locator('#email').fill(staffEmail);
        await page.locator('form button[type=submit]').click();
        await waitUntil(() => Boolean(inboxUrl(staffEmail, '/reset-password/')));
        checkpoint = 'consume-reset-link';
        await page.goto(inboxUrl(staffEmail, '/reset-password/'));
        await page.locator('#password').fill('Audit-reset-347!');
        await page.locator('#password_confirmation').fill('Audit-reset-347!');
        await page.locator('form button[type=submit]').click();
        await page.waitForURL(url => url.pathname === '/admin');
        const old = await request(clients['new-staff'], '/receptionist/dashboard');
        assert.equal(old.status, 401);
        await login('reset-staff', { id: staffId, email: staffEmail }, false, 'Audit-reset-347!');
        await context.close();
        return { reset_email_received: true, old_session_status: old.status, new_login: true };
    });
    await check('Staff: admin password reset revokes old session and permits fresh login', async () => {
        if (!clients['reset-staff']) throw new Error('Dependency: password-reset staff session unavailable');
        checkpoint = 'admin-reset-password';
        const page = clients.admin.page;
        await page.goto(base + `/admin/staff/${staffId}/edit`);
        await page.locator('#password').fill('Audit-admin-reset-348!');
        await page.getByRole('button', { name: 'Simpan perubahan', exact: true }).click();
        await page.waitForURL('**/admin/staff');
        assert.equal((await request(clients['reset-staff'], '/receptionist/dashboard')).status, 401);
        await login('admin-reset-staff', { id: staffId, email: staffEmail }, false, 'Audit-admin-reset-348!');
        return { old_session_status: 401, fresh_login: true };
    });
    await check('Staff: email change requires new verification and rejects old signed link', async () => {
        if (!clients['admin-reset-staff']) throw new Error('Dependency: admin-reset staff session unavailable');
        checkpoint = 'staff-email-change';
        const oldLink = inboxUrl(staffEmail, '/verify-email/');
        const changedEmail = `changed-${randomUUID().slice(0, 8)}@clinicos.test`;
        const page = clients.admin.page;
        await page.goto(base + `/admin/staff/${staffId}/edit`);
        await page.locator('#email').fill(changedEmail);
        await page.getByRole('button', { name: 'Simpan perubahan', exact: true }).click();
        await page.waitForURL('**/admin/staff');
        assert.equal(audit('staff-id', { email: changedEmail }).verified, false);
        const client = await login('email-changed-staff', { id: staffId, email: changedEmail }, false, 'Audit-admin-reset-348!');
        assert.equal(new URL(client.page.url()).pathname, '/verify-email');
        const oldResponse = await client.context.request.get(oldLink, { maxRedirects: 0 });
        assert.equal(oldResponse.status(), 403);
        await waitUntil(() => Boolean(inboxUrl(changedEmail, '/verify-email/')));
        await client.page.goto(inboxUrl(changedEmail, '/verify-email/'));
        await client.page.waitForURL(url => url.pathname.includes('dashboard'));
        assert.equal(audit('staff-id', { email: changedEmail }).verified, true);
        return { verified_reset_on_edit: true, old_link: 403, new_smtp_link_verified: true };
    });
    await check('Reliability: session expiration preserves unsaved RME and user can recover through login', async () => {
        checkpoint = 'session-expiry-new-visit';
        const queue = await bookAnother('081212120005');
        assert.equal((await request(clients.receptionist, `/queues/${queue}/check-in`, 'POST')).status, 200);
        assert.equal((await request(clients.receptionist, `/queues/${queue}/call`, 'POST')).status, 200);
        assert.equal((await request(clients.doctor, `/doctor/queues/${queue}/start`, 'POST')).status, 200);
        const newVisit = audit('main-state').visits.at(-1).id;
        const page = clients.doctor.page;
        await page.goto(base + `/doctor/visits/${newVisit}`);
        audit('expire-session', { user_id: users.doctor.id });
        checkpoint = 'expired-session-save';
        const response = page.waitForResponse(response => response.url().endsWith(`/doctor/visits/${newVisit}/draft`) && response.request().method() === 'POST');
        await page.locator('[name=subjective]').fill('Synthetic unsaved expiry recovery');
        const expiredStatus = (await response).status();
        assert.ok([401, 419].includes(expiredStatus), 'Expired session must deny the save');
        assert.equal(await page.locator('[name=subjective]').inputValue(), 'Synthetic unsaved expiry recovery');
        assert.equal(audit('main-state').visits.at(-1).version, 0);
        const recovered = await login('recovered-doctor', users.doctor);
        await recovered.page.goto(base + `/doctor/visits/${newVisit}`);
        await recovered.page.locator('[name=subjective]').fill('Synthetic unsaved expiry recovery');
        await recovered.page.locator('[name=assessment]').fill('Synthetic assessment');
        await recovered.page.locator('[name=plan]').fill('Synthetic plan');
        await recovered.page.getByRole('button', { name: 'Tinjau & finalisasi', exact: true }).click();
        await recovered.page.getByRole('button', { name: 'Finalisasi kunjungan', exact: true }).click();
        await waitUntil(() => audit('main-state').visits.at(-1).status === 'COMPLETED');
        return { expired_save: expiredStatus, unsaved_text_retained: true, stale_write_prevented: true, user_relogin_and_finalize: true };
    });
    await check('Realtime security: deactivation revokes HTTP and an already subscribed socket', async () => {
        if (!clients['email-changed-staff']) throw new Error('Dependency: verified changed-email staff unavailable');
        checkpoint = 'deactivate-connected-staff';
        const client = clients['email-changed-staff'];
        await client.page.evaluate(clinic => {
            window.auditDeactivatedEvents = [];
            window.Echo.private(`clinic.${clinic}.queues`).listen('.queue.updated', payload => window.auditDeactivatedEvents.push(Object.keys(payload)));
        }, fixture.main.clinic_id);
        await client.page.waitForFunction(clinic => window.Echo.connector.pusher.channel(`private-clinic.${clinic}.queues`)?.subscribed, fixture.main.clinic_id);
        const page = clients.admin.page;
        await page.goto(base + `/admin/staff/${staffId}/edit`);
        await page.locator('input[type=checkbox][name=is_active]').uncheck();
        await page.getByRole('button', { name: 'Simpan perubahan', exact: true }).click();
        await page.waitForURL('**/admin/staff');
        assert.equal((await request(client, '/receptionist/dashboard')).status, 302);
        const queue = await bookAnother('081212120006');
        assert.equal((await request(clients.receptionist, `/queues/${queue}/check-in`, 'POST')).status, 200);
        await sleep(2000);
        const received = await client.page.evaluate(() => window.auditDeactivatedEvents.length);
        writeFileSync(join(evidence, 'deactivation-revocation.json'), JSON.stringify({ http_denied: true, events_after_deactivation: received,
            socket_state: await client.page.evaluate(() => window.Echo.connector.pusher.connection.state) }, null, 2));
        assert.equal(received, 0, 'Inactive socket must stop receiving private events');
        return { http_denied: true, events_after_deactivation: received };
    });
    await check('Realtime security: permission revocation invalidates an already subscribed socket', async () => {
        checkpoint = 'permission-revocation-open-socket';
        const client = clients.receptionist2;
        const before = await client.page.evaluate(() => window.auditEvents.length);
        audit('revoke', { user_id: users.receptionist2.id });
        const freshAuth = await request(client, '/broadcasting/auth', 'POST', { socket_id: await client.page.evaluate(() => window.Echo.socketId()), channel_name: `private-clinic.${fixture.main.clinic_id}.queues` });
        assert.equal(freshAuth.status, 403);
        const controlBefore = await clients.receptionist.page.evaluate(() => window.auditEvents.length);
        const queue = await bookAnother('081212120002');
        assert.equal((await request(clients.receptionist, `/queues/${queue}/check-in`, 'POST')).status, 200);
        await waitUntil(async () => await clients.receptionist.page.evaluate(() => window.auditEvents.length) > controlBefore);
        await sleep(2500);
        const after = await client.page.evaluate(() => window.auditEvents.length);
        writeFileSync(join(evidence, 'permission-revocation.json'), JSON.stringify({ new_auth_status: freshAuth.status, events_received_after_revocation: after - before,
            connection_state: await client.page.evaluate(() => window.Echo.connector.pusher.connection.state), payload_contains_medical_fields: false }, null, 2));
        assert.equal(after, before, 'Revoked socket must stop receiving private events');
        return { new_auth_status: freshAuth.status, events_after_revocation: after - before };
    });
} finally {
    writeFileSync(join(config.root, 'browser-fixture-state.json'), JSON.stringify({ appointmentId, queueId, visitId, staffId, staffEmail }));
    await browser.close();
}
console.log(JSON.stringify({ passed: results.filter(item => item.status === 'PASS').length, failed: results.filter(item => item.status === 'FAIL').length,
    blocked: results.filter(item => item.status === 'BLOCKED').length }));
