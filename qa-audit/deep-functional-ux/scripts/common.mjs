import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { DatabaseSync } from 'node:sqlite';

export const repo = resolve(import.meta.dirname, '../../..');
export const evidence = process.env.CLINICOS_AUDIT_EVIDENCE || join(repo, 'qa-audit/deep-functional-ux/evidence');
export const runtime = JSON.parse(readFileSync(join(evidence, 'runtime.json')));
export const base = runtime.base;
export const sql = new DatabaseSync(runtime.database);
export const credential = readFileSync(join(repo, 'database/seeders/DemoUserSeeder.php'), 'utf8').match(/Hash::make\('([^']+)'\)/)[1];
const { chromium } = await import(pathToFileURL(runtime.playwright).href);
export const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
export const resultsFile = join(evidence, 'scenarios.json');
export const results = existsSync(resultsFile) ? JSON.parse(readFileSync(resultsFile)) : [];
export const pagesFile = join(evidence, 'pages.json');
export const pages = existsSync(pagesFile) ? JSON.parse(readFileSync(pagesFile)) : [];
export const cleanUrl = value => { const url = new URL(value, base); return url.origin + url.pathname.replace(/(reset-password\/)\S+/, '$1[redacted]') + (url.search ? '?[query parameters omitted]' : ''); };
export async function client(role, email = `${role}@clinicos.test`, password = credential) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.setDefaultTimeout(7000);
    page.on('dialog', dialog => dialog.dismiss());
    page.on('pageerror', error => {
        const path = join(evidence, 'page-errors.jsonl');
        const record = { role, page: cleanUrl(page.url()), name: error.name, message: error.message.replace(/https?:\/\/[^\s]+/g, '[url]') };
        writeFileSync(path, JSON.stringify(record) + '\n', { flag: 'a' });
    });
    if (role !== 'guest') {
        await page.goto(base + (role === 'patient' ? '/login' : '/admin'));
        await page.waitForFunction(() => Boolean(window.Livewire));
        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await page.locator('form button[type=submit]').click();
        await page.waitForURL(url => !['/admin', '/login'].includes(url.pathname));
        await page.waitForLoadState('domcontentloaded');
    }
    return { context, page, role };
}
export async function check(id, feature, role, scenario, steps, body) {
    let previous = results.find(item => item.id === id);
    if (previous && process.env.AUDIT_RESUME === '1') {
        while (previous.superseded_by && results.some(item => item.id === previous.superseded_by)) previous = results.find(item => item.id === previous.superseded_by);
        if (previous.status === 'PASS') return previous;
        let attempt = 1;
        while (results.some(item => item.id === id + '-R' + attempt)) attempt++;
        previous.superseded_by = id + '-R' + attempt;
        previous.harness_note = 'Retest after correction of browser synchronization or scenario setup; see replacement evidence.';
        id += '-R' + attempt;
    }
    assert.ok(!results.some(item => item.id === id), 'Scenario IDs must be unique: ' + id);
    const record = { id, feature, role, scenario, steps, started: new Date().toISOString() };
    try {
        record.observed = await body();
        record.status = 'PASS';
    } catch (error) {
        record.status = error.message.startsWith('BLOCKED:') ? 'BLOCKED' : 'FAIL';
        record.reason = error.message.replace(/https?:\/\/[^\s]+/g, '[url]').slice(0, 1800);
        record.error_type = error.name;
        record.failure_screenshots = [];
        const candidates = browser.contexts().flatMap(context => context.pages()).filter(page => !page.isClosed()).slice(-3);
        for (let index = 0; index < candidates.length; index++) {
            try { record.failure_screenshots.push(await shot(candidates[index], id + '-failure-' + index)); } catch { /* A closed page cannot provide evidence. */ }
        }
    }
    results.push(record);
    writeFileSync(resultsFile, JSON.stringify(results, null, 2));
    console.log(JSON.stringify({ id, status: record.status, scenario }));
    return record;
}
export function untested(id, feature, role, scenario, reason, status = 'NOT TESTED') {
    if (!results.some(item => item.id === id)) {
        results.push({ id, feature, role, scenario, status, reason });
        writeFileSync(resultsFile, JSON.stringify(results, null, 2));
    }
}
export async function shot(page, id) {
    mkdirSync(join(evidence, 'screenshots'), { recursive: true });
    await page.screenshot({ path: join(evidence, 'screenshots', id + '.png'), fullPage: true });
    return 'screenshots/' + id + '.png';
}
export async function inspect(page, role, label) {
    await page.waitForTimeout(200);
    const snapshot = await page.evaluate(() => {
        const visible = node => Boolean(node.getClientRects().length) && getComputedStyle(node).visibility !== 'hidden';
        const path = value => { try { const url = new URL(value, location.href); return url.origin + url.pathname; } catch { return ''; } };
        return {
            title: document.title, headings: [...document.querySelectorAll('h1,h2,h3')].map(e => e.textContent.trim()),
            links: [...document.querySelectorAll('a[href]')].map(e => ({ label: e.innerText.trim(), target: path(e.href), visible: visible(e) })),
            actions: [...document.querySelectorAll('button,input[type=submit],summary')].map(e => ({ text: e.innerText.trim() || e.getAttribute('aria-label') || '', type: e.type, visible: visible(e), disabled: e.disabled || false })),
            fields: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].map(e => ({ name: e.name, id: e.id, type: e.type, required: e.required, disabled: e.disabled, visible: visible(e), labels: [...(e.labels || [])].map(l => l.textContent.trim()), aria_label: e.getAttribute('aria-label'), options: e.tagName === 'SELECT' ? [...e.options].map(o => o.text) : undefined })),
            forms: [...document.forms].map(e => ({ method: e.method, action: path(e.action), fields: [...e.elements].map(n => n.name).filter(n => n && !['_token','idempotency_key','allergy_version','record_version'].includes(n)) })),
            dialogs: [...document.querySelectorAll('[role=dialog],dialog,[x-show]')].map(e => ({ tag: e.tagName, label: e.getAttribute('aria-label'), visible: visible(e) })),
            text: document.body.innerText.slice(-15000),
        };
    });
    const responsive = [];
    for (const width of [375, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 950 });
        await page.waitForTimeout(100);
        responsive.push(await page.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth, overflow: document.documentElement.scrollWidth > innerWidth + 1 })));
    }
    const record = { role, label, url: cleanUrl(page.url()), ...snapshot, responsive, observed: new Date().toISOString() };
    pages.push(record);
    writeFileSync(pagesFile, JSON.stringify(pages, null, 2));
    return record;
}
export async function selectSearch(page, name, optionText) {
    const input = page.locator(`#${name}_search`);
    await input.click();
    if (optionText) await input.fill(optionText);
    await input.press('ArrowDown');
    await page.waitForFunction(() => document.activeElement?.tagName === 'BUTTON' && document.activeElement.closest('[x-ref="results"]'));
    await page.keyboard.press('Enter');
    await page.waitForFunction(field => Boolean(document.querySelector(`[name="${field}"]`)?.value), name);
}
export async function submit(page, name, urlPattern) {
    await page.getByRole('button', { name, exact: true }).click();
    if (urlPattern) await page.waitForURL(urlPattern);
    await page.waitForLoadState('domcontentloaded');
}
export function saveState(values) {
    const file = join(evidence, 'journey-state.json');
    const old = existsSync(file) ? JSON.parse(readFileSync(file)) : {};
    writeFileSync(file, JSON.stringify({ ...old, ...values }, null, 2));
}
export function state() {
    const file = join(evidence, 'journey-state.json');
    return existsSync(file) ? JSON.parse(readFileSync(file)) : {};
}
export async function finish(phase) {
    const checkpoint = JSON.stringify({ phase, at: new Date().toISOString(), counts: Object.fromEntries(['PASS','FAIL','BLOCKED','NOT TESTED'].map(s => [s, results.filter(r => r.status === s).length])), browser: browser.version() }, null, 2);
    writeFileSync(join(evidence, 'checkpoint.json'), checkpoint);
    writeFileSync(join(evidence, 'checkpoint-' + phase + '.json'), checkpoint);
    await browser.close();
    sql.close();
}
