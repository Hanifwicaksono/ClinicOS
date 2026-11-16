import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const config = JSON.parse(readFileSync('qa-audit/production-readiness/evidence/runtime.json', 'utf8'));
const require = createRequire(join(config.root, 'tools/package.json'));
const { chromium } = require('playwright');
const audit = (action, input = {}) => JSON.parse(execFileSync('php', ['qa-audit/production-readiness/tests/audit.php', action],
    { env: { ...process.env, APP_ENV: 'testing' }, input: JSON.stringify(input), encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }));
const fixture = audit('race-fixture', { case: 'human-day' }).fixture;
const platform = JSON.parse(readFileSync(join(config.root, 'fixture.json'))).platform;
const base = process.env.APP_URL;
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const pages = {}, steps = [], errors = [];
let checkpoint = 'opening';
const started = Date.now();
let result;
async function until(check) {
    const end = Date.now() + 15000;
    while (Date.now() < end) { if (await check()) return; await new Promise(resolve => setTimeout(resolve, 100)); }
    throw new Error('Day checkpoint timed out');
}
async function choose(page, name) {
    await page.locator(`#${name}_search`).click();
    await page.locator(`#${name}_search`).press('ArrowDown');
    await page.waitForFunction(() => document.activeElement?.tagName === 'BUTTON');
    await page.keyboard.press('Enter');
    await until(async () => Boolean(await page.locator(`[name=${name}]`).inputValue()));
}
try {
    for (const name of ['owner', 'admin', 'receptionist', 'doctor', 'patient', 'platform']) {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.name));
        page.on('dialog', dialog => dialog.accept());
        pages[name] = page;
        await page.goto(base + (name === 'patient' ? '/login' : '/admin'));
        await page.locator('#email').fill(name === 'platform' ? platform.email : fixture.users[name].email);
        await page.locator('#password').fill('password');
        await page.locator('form[wire\\:submit="login"] button[type=submit]').click();
        await page.waitForURL(url => url.pathname.includes('dashboard'));
    }
    await pages.admin.goto(base + '/admin/doctor-schedules');
    await pages.owner.goto(base + '/onboarding/plans');
    await pages.platform.goto(base + '/platform/tenants');
    steps.push('Opening: owner reviews subscription, admin reviews schedules, platform reviews tenants');
    checkpoint = 'patient-booking';
    const patient = pages.patient;
    await patient.goto(base + `/clinic/${fixture.slug}/booking`);
    await choose(patient, 'doctor_id');
    await patient.locator('#appointment_date').fill(fixture.date);
    await choose(patient, 'service_id');
    await choose(patient, 'doctor_schedule_id');
    await patient.locator('#name').fill('Synthetic day patient');
    await patient.locator('#birth_date').fill('1990-01-01');
    await patient.locator('#gender').selectOption('MALE');
    await patient.locator('#phone').fill('081233330001');
    await patient.locator('#address').fill('Synthetic clinic day address');
    await patient.getByRole('button', { name: 'Konfirmasi booking', exact: true }).click();
    await patient.waitForURL(url => /\/booking\/\d+\/status$/.test(url.pathname));
    const appointment = Number(new URL(patient.url()).pathname.split('/')[2]);
    const state = () => audit('lookup-booking', { clinic_id: fixture.clinic_id, appointment_id: appointment });
    const queue = state().queue_id;
    steps.push('Patient books through the actual public form and receives queue confirmation');
    checkpoint = 'reception-queue-ui';
    const reception = pages.receptionist;
    await reception.goto(base + '/receptionist/queues');
    await reception.locator(`form[action$="/queues/${queue}/check-in"] button`).click();
    await reception.locator(`form[action$="/queues/${queue}/call"] button`).click();
    await reception.locator(`form[action$="/queues/${queue}/skip"] button`).click();
    await reception.locator(`form[action$="/queues/${queue}/return"] button`).click();
    await reception.locator(`form[action$="/queues/${queue}/call"] button`).click();
    await until(() => state().queue_status === 'CALLED');
    steps.push('Receptionist checks in, calls, skips a missed patient, returns and calls again through UI');
    checkpoint = 'doctor-start-ui';
    const doctor = pages.doctor;
    await doctor.goto(base + '/doctor/queues');
    await doctor.locator(`form[action$="/doctor/queues/${queue}/start"] button`).click();
    await doctor.waitForURL(url => /\/doctor\/visits\/\d+$/.test(url.pathname));
    await doctor.locator('[name=subjective]').fill('Synthetic day subjective');
    await doctor.getByRole('button', { name: 'Tinjau & finalisasi', exact: true }).click();
    await doctor.getByRole('button', { name: 'Finalisasi kunjungan', exact: true }).click();
    await new Promise(resolve => setTimeout(resolve, 500));
    assert.equal(state().record_status, 'DRAFT', 'Incomplete clinical form must not become final');
    await doctor.getByRole('button', { name: 'Kembali periksa', exact: true }).click();
    steps.push('Human error: incomplete finalization is refused without corrupting the draft');
    checkpoint = 'doctor-finalize-ui';
    await doctor.locator('[name=assessment]').fill('Synthetic day assessment');
    await doctor.locator('[name=plan]').fill('Synthetic day plan');
    await doctor.getByRole('button', { name: 'Tinjau & finalisasi', exact: true }).click();
    await doctor.getByRole('button', { name: 'Finalisasi kunjungan', exact: true }).click();
    await until(() => state().record_status === 'FINAL');
    assert.equal(state().appointment_status, 'COMPLETED');
    assert.equal(state().queue_status, 'COMPLETED');
    steps.push('Doctor finalizes; appointment, visit, record and queue reach final states');
    checkpoint = 'closing';
    const response = await patient.goto(base + `/patient/appointments/${appointment}`);
    assert.equal(response.status(), 200);
    await pages.admin.goto(base + '/admin/dashboard');
    await pages.owner.goto(base + '/admin/dashboard');
    const counts = audit('state', { clinic_id: fixture.clinic_id });
    assert.equal(counts.appointments, 1);
    assert.equal(counts.queues, 1);
    assert.equal(counts.visits, 1);
    assert.equal(errors.length, 0);
    steps.push('Closing: patient reviews own appointment, admin/owner review operational dashboards');
    result = { status: 'PASS', duration_ms: Date.now() - started, steps, roles: 5, owner_persona: true,
        developer_data_repairs_during_workflow: 0, business_clock_advanced: false, real_eight_hour_shift: 'NOT TESTED', page_errors: errors };
} catch (error) {
    writeFileSync(join(config.root, 'day-error.txt'), error.stack || error.message);
    result = { status: 'FAIL', duration_ms: Date.now() - started, checkpoint, steps, error_class: error.name, page_errors: errors };
} finally {
    await browser.close();
}
writeFileSync('qa-audit/production-readiness/evidence/clinic-day.json', JSON.stringify(result, null, 2));
console.log(JSON.stringify({ clinic_day: result.status, checkpoint: result.checkpoint, steps: result.steps.length }));
