import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, existsSync, unlinkSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { join } from 'node:path';
import { execFileSync, spawn } from 'node:child_process';

const config = JSON.parse(readFileSync('qa-audit/production-readiness/evidence/runtime.json', 'utf8'));
const fixture = JSON.parse(readFileSync(join(config.root, 'fixture.json')));
const require = createRequire(join(config.root, 'tools/package.json'));
const { chromium } = require('playwright');
const base = process.env.APP_URL;
const env = { ...process.env, APP_ENV: 'testing' };
const audit = (action, input = {}) => JSON.parse(execFileSync('php', ['qa-audit/production-readiness/tests/audit.php', action],
    { input: JSON.stringify(input), env, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }));
const data = audit('load-data', { clinic_id: fixture.main.clinic_id });
console.log(JSON.stringify({ synthetic_patients: data.patients }));
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const clients = [];
const routes = {
    receptionist: ['/receptionist/dashboard', '/receptionist/patients', '/receptionist/appointments', '/receptionist/queues'],
    doctor: ['/doctor/dashboard', '/doctor/queues', '/doctor/medical-records'],
    admin: ['/admin/dashboard', '/admin/staff', '/admin/doctor-schedules'],
    patient: ['/patient/dashboard', '/patient/appointments'],
    owner: ['/admin/dashboard', '/subscription/payments'],
};
const pct = (values, p) => values.length ? [...values].sort((a, b) => a - b)[Math.max(0, Math.ceil(values.length * p) - 1)] : null;
const summary = values => ({ samples: values.length, p50_ms: pct(values, .5), p95_ms: pct(values, .95), p99_ms: pct(values, .99) });
const stages = [];
let monitor;
try {
    for (let index = 0; index < 20; index++) {
        const role = Object.keys(routes)[index % 5];
        const context = await browser.newContext();
        const page = await context.newPage();
        await page.goto(base + (role === 'patient' ? '/login' : '/admin'));
        await page.locator('#email').fill(fixture.main.users[role].email);
        await page.locator('#password').fill('password');
        await page.locator('form[wire\\:submit="login"] button[type=submit]').click();
        await page.waitForURL(url => url.pathname.includes('dashboard'));
        const cookies = (await context.cookies()).map(cookie => `${cookie.name}=${cookie.value}`).join('; ');
        clients.push({ context, page, role, cookies });
    }
    if (existsSync(join(config.root, 'monitor.stop'))) unlinkSync(join(config.root, 'monitor.stop'));
    monitor = spawn('python', ['qa-audit/production-readiness/tests/monitor.py'], { stdio: 'ignore', windowsHide: true });
    for (const concurrency of [1, 5, 10, 20]) {
        const duration = 15000;
        const start = Date.now();
        const latencies = [], queryTimes = [], dbTotals = [], queryCounts = [], statuses = {};
        let networkErrors = 0;
        await Promise.all(clients.slice(0, concurrency).map(async (client, index) => {
            let sequence = index;
            while (Date.now() - start < duration) {
                const path = routes[client.role][sequence++ % routes[client.role].length];
                const at = performance.now();
                try {
                    const response = await fetch(base + path, { headers: { Cookie: client.cookies, Accept: 'text/html' }, redirect: 'manual', signal: AbortSignal.timeout(30000) });
                    await response.arrayBuffer();
                    latencies.push(performance.now() - at);
                    statuses[response.status] = (statuses[response.status] || 0) + 1;
                    assert.equal(response.headers.get('x-audit-storage-isolated'), 'true');
                    if (response.headers.has('x-audit-query-times')) {
                        queryTimes.push(...response.headers.get('x-audit-query-times').split(',').filter(Boolean).map(Number));
                        dbTotals.push(Number(response.headers.get('x-audit-db-time-ms')));
                        queryCounts.push(Number(response.headers.get('x-audit-query-count')));
                    }
                } catch { networkErrors++; latencies.push(performance.now() - at); }
            }
        }));
        const elapsed = Date.now() - start;
        const serverErrors = Object.entries(statuses).filter(([status]) => Number(status) >= 500).reduce((sum, [, count]) => sum + count, 0) + networkErrors;
        const stage = { concurrency, duration_ms: elapsed, requests: latencies.length, throughput_rps: latencies.length / (elapsed / 1000),
            response: summary(latencies), individual_query: summary(queryTimes), database_per_request: summary(dbTotals),
            mean_query_count: queryCounts.reduce((a, b) => a + b, 0) / queryCounts.length, statuses, network_errors: networkErrors,
            server_error_rate: serverErrors / latencies.length,
            provisional_latency_gate: pct(latencies, .95) <= 2000 && pct(latencies, .99) <= 5000 ? 'PASS' : 'FAIL',
            provisional_error_gate: serverErrors / latencies.length <= .001 && Object.keys(statuses).every(status => status === '200') ? 'PASS' : 'FAIL' };
        stages.push(stage);
        writeFileSync('qa-audit/production-readiness/evidence/load-results.json', JSON.stringify({ stages, fixture_patients: data.patients,
            unique_accounts: 5, independent_sessions: 20, load_shape: 'closed loop; no think time; 15 seconds per stage; authenticated reads',
            state_after: audit('state', { clinic_id: fixture.main.clinic_id }) }, null, 2));
        console.log(JSON.stringify({ concurrency, requests: stage.requests, p95_ms: stage.response.p95_ms, p99_ms: stage.response.p99_ms, errors: serverErrors }));
    }
    const before = audit('state', { clinic_id: fixture.main.clinic_id });
    const writes = [], writeStatuses = {}, sockets = [];
    const receptionists = clients.filter(client => client.role === 'receptionist');
    const receiver = receptionists[0].page;
    await receiver.evaluate(clinic => {
        window.auditLoadEvents = [];
        window.Echo.private(`clinic.${clinic}.queues`).listen('.queue.updated', payload => window.auditLoadEvents.push({ id: payload.queue_id, at: Date.now() }));
    }, fixture.main.clinic_id);
    await receiver.waitForFunction(clinic => window.Echo.connector.pusher.channel(`private-clinic.${clinic}.queues`)?.subscribed, fixture.main.clinic_id);
    const writeStart = performance.now();
    await Promise.all(receptionists.map(async client => {
        const token = await client.page.locator('meta[name=csrf-token]').getAttribute('content');
        for (let index = 0; index < 10; index++) {
            const at = performance.now();
            const response = await fetch(base + '/receptionist/appointments', { method: 'POST', redirect: 'manual',
                headers: { Cookie: client.cookies, Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ patient_id: fixture.main.users.patient.patient_id, doctor_id: fixture.main.users.doctor.doctor_id,
                    doctor_schedule_id: fixture.main.users.doctor.schedule_id, service_id: fixture.main.service_id,
                    appointment_date: fixture.main.date, idempotency_key: randomUUID() }) });
            await response.arrayBuffer();
            writeStatuses[response.status] = (writeStatuses[response.status] || 0) + 1;
            writes.push(performance.now() - at);
            if (response.status === 302) {
                const appointment = Number(new URL(response.headers.get('location'), base).pathname.split('/').at(-1));
                const state = audit('main-state');
                const queue = state.queues.find(item => item.appointment_id === appointment).id;
                const sent = Date.now();
                const checkin = await fetch(base + `/queues/${queue}/check-in`, { method: 'POST', redirect: 'manual',
                    headers: { Cookie: client.cookies, Accept: 'application/json', 'X-CSRF-TOKEN': token } });
                assert.equal(checkin.status, 200);
                sockets.push({ queue, sent });
            }
        }
    }));
    await new Promise(resolve => setTimeout(resolve, 2500));
    const events = await receiver.evaluate(() => window.auditLoadEvents);
    const realtime = sockets.map(item => { const event = events.find(event => event.id === item.queue); return event ? event.at - item.sent : null; });
    const after = audit('state', { clinic_id: fixture.main.clinic_id });
    const load = JSON.parse(readFileSync('qa-audit/production-readiness/evidence/load-results.json'));
    load.write_burst = { concurrent_sessions: receptionists.length, requests: writes.length, response: summary(writes), statuses: writeStatuses,
        elapsed_ms_including_fixture_observations: performance.now() - writeStart, appointments_added: after.appointments - before.appointments,
        queues_added: after.queues - before.queues, websocket_latency: summary(realtime.filter(value => value !== null)),
        websocket_missing: realtime.filter(value => value === null).length,
        integrity_gate: after.appointments - before.appointments === writes.length && after.queues - before.queues === writes.length
            && new Set(after.queue_numbers).size === after.queue_numbers.length && after.mismatched_doctor === 0 ? 'PASS' : 'FAIL' };
    load.state_after = after;
    writeFileSync('qa-audit/production-readiness/evidence/load-results.json', JSON.stringify(load, null, 2));
    console.log(JSON.stringify({ write_requests: writes.length, write_statuses: writeStatuses, websocket_samples: realtime.length,
        websocket_missing: load.write_burst.websocket_missing, integrity_gate: load.write_burst.integrity_gate }));
} finally {
    writeFileSync(join(config.root, 'monitor.stop'), 'stop');
    if (monitor && monitor.exitCode === null) await new Promise(resolve => monitor.on('exit', resolve));
    await browser.close();
}
