import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

test('all role menus, logout, responsive dashboards and mobile keyboard navigation', {
    skip: process.env.CLINICOS_BROWSER_TEST !== '1', timeout: 180000,
}, async () => {
    const root = fileURLToPath(new URL('../../', import.meta.url));
    const directory = mkdtempSync(join(tmpdir(), 'clinicos-qa-regression-'));
    const evidence = join(root, 'qa-audit/evidence/p2-retest');
    mkdirSync(evidence, { recursive: true });
    const baseUrl = 'http://127.0.0.1:18951';
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
    const server = spawn('php', [...php, '-S', '127.0.0.1:18951', '-t', 'public'], { cwd: root, env, stdio: 'ignore' });
    const chrome = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0',
        `--user-data-dir=${join(directory, 'chrome')}`, 'about:blank',
    ], { stdio: ['ignore', 'ignore', 'pipe'] });
    let socket;
    const steps = [];
    const problems = [];
    const runtimeErrors = [];
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
            if (message.method === 'Runtime.exceptionThrown') runtimeErrors.push(message.params.exceptionDetails.text);
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
        await call('Page.addScriptToEvaluateOnNewDocument', { source: 'window.qaErrors = []; window.addEventListener("error", event => window.qaErrors.push(event.message));' });
        await call('Runtime.enable');
        await call('Page.bringToFront');
        await call('Network.enable');
        await call('Emulation.setFocusEmulationEnabled', { enabled: true });
        for (const role of ['admin', 'receptionist', 'doctor', 'patient', 'platform']) {
            await call('Network.clearBrowserCookies');
            await go(role === 'patient' ? '/login' : '/admin', 'Boolean(window.Livewire && document.querySelector("#email"))');
            await fill('#email', `${role}@clinicos.test`);
            await fill('#password', credential);
            await evaluate('document.querySelector("form").requestSubmit()');
            await waitFor('location.pathname.includes("dashboard")');
            const links = await evaluate('Array.from(document.querySelectorAll("aside nav a"), a => new URL(a.href).pathname)');
            for (const path of links) {
                await go(path);
                const status = await evaluate('performance.getEntriesByType("navigation")[0].responseStatus');
                steps.push({ role, path, status: status === 200 ? 'PASS' : 'FAIL', httpStatus: status });
                assert.equal(status, 200, `${role}: ${path}`);
            }
            await go(`/${role}/dashboard`);
            for (const width of [375, 768, 1024, 1440]) {
                await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
                await evaluate('new Promise(resolve => setTimeout(resolve, 100))');
                const fits = await evaluate('document.documentElement.scrollWidth <= innerWidth + 1');
                steps.push({ role, width, status: fits ? 'PASS' : 'FAIL', action: 'dashboard overflow' });
                assert.ok(fits, `${role}: overflow at ${width}px`);
            }
            await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 900, deviceScaleFactor: 1, mobile: false });
            await evaluate(`document.querySelector('[aria-label="Buka navigasi"]').focus(); document.querySelector('[aria-label="Buka navigasi"]').click()`);
            await waitFor('document.querySelector("aside").contains(document.activeElement)');
            assert.equal(await evaluate(`document.querySelector('[aria-label="Buka navigasi"]').getAttribute('aria-expanded')`), 'true');
            await evaluate(`window.qaFocusNodes = Array.from(document.querySelector('aside').querySelectorAll('a, button')).filter(node => node.offsetParent && !node.disabled); window.qaFocusNodes.at(-1).focus()`);
            await evaluate(`document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', code: 'Tab', bubbles: true, cancelable: true }))`);
            await waitFor('document.activeElement === window.qaFocusNodes[0]');
            await evaluate(`document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', code: 'Tab', shiftKey: true, bubbles: true, cancelable: true }))`);
            await waitFor('document.activeElement === window.qaFocusNodes.at(-1)');
            await evaluate(`document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true, cancelable: true }))`);
            await waitFor(`document.activeElement === document.querySelector('[aria-label="Buka navigasi"]') && document.querySelector('aside').inert`);
            assert.equal(await evaluate(`document.querySelector('[aria-label="Buka navigasi"]').getAttribute('aria-expanded')`), 'false');
            steps.push({ role, action: 'Mobile focus trap, Tab/Shift+Tab, Escape, focus return and closed inert state', status: 'PASS', keyboardMode: 'DOM KeyboardEvent in Chrome' });
            await call('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
            await waitFor('!document.querySelector("aside").inert');
            await evaluate('Array.from(document.querySelectorAll("button")).find(button => button.getAttribute("wire:click") === "logout").click()');
            await waitFor('location.pathname === "/"');
            const redirected = await evaluate(`fetch('/${role}/dashboard').then(response => new URL(response.url).pathname)`);
            steps.push({ role, action: 'logout', status: ['/admin', '/login'].includes(redirected) ? 'PASS' : 'FAIL' });
            assert.ok(['/admin', '/login'].includes(redirected), `${role}: logout must revoke access`);
        }
        await go('/login', 'Boolean(window.Livewire && document.querySelector("#email"))');
        await fill('#email', 'patient@clinicos.test');
        await fill('#password', credential);
        await evaluate('document.querySelector("form").requestSubmit()');
        await waitFor('location.pathname.includes("dashboard")');
        await go('/clinic/klinik-sehat-sentosa/booking');
        await evaluate('document.querySelector("#doctor_id_search").focus()');
        await evaluate('new Promise(resolve => setTimeout(resolve, 200))');
        const hasDoctorChoice = await evaluate(`Boolean(document.querySelector('#doctor_id_search').parentElement.querySelector('[x-ref=results] button')?.offsetParent)`);
        if (hasDoctorChoice) {
            await evaluate(`document.querySelector('#doctor_id_search').parentElement.querySelector('[x-ref=results] button').click()`);
            await evaluate('new Promise(resolve => setTimeout(resolve, 100))');
        }
        const publicBookingReady = hasDoctorChoice && await evaluate('Boolean(document.querySelector("[name=doctor_id]").value)');
        steps.push({ role: 'patient', action: 'choose a doctor in public booking', status: publicBookingReady ? 'PASS' : 'FAIL' });
        if (! publicBookingReady) problems.push('Patient public booking controls are not initialized');
        await call('Network.clearBrowserCookies');
        await go('/admin', 'Boolean(window.Livewire && document.querySelector("#email"))');
        await fill('#email', 'admin@clinicos.test');
        await fill('#password', credential);
        await evaluate('document.querySelector("form").requestSubmit()');
        await waitFor('location.pathname.includes("dashboard")');
        await go('/profile');
        await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 900, deviceScaleFactor: 1, mobile: false });
        await evaluate(`document.querySelector('[aria-label="Buka navigasi"]').click()`);
        await evaluate('new Promise(resolve => setTimeout(resolve, 350))');
        await evaluate(`document.querySelector('[aria-label="Tutup navigasi"]').focus(); window.qaKeys = []; window.addEventListener('keydown', event => window.qaKeys.push(event.key), true)`);
        await call('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
        await call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
        const nativeKeyboard = await evaluate('window.qaKeys.length > 0');
        if (! nativeKeyboard) {
            await evaluate(`document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true, cancelable: true }))`);
        }
        await evaluate('new Promise(resolve => setTimeout(resolve, 350))');
        const closed = await evaluate('document.querySelector("aside").getBoundingClientRect().right <= 1');
        assert.equal(await evaluate(`document.activeElement === document.querySelector('[aria-label="Buka navigasi"]')`), true);
        const navigationState = await evaluate(`(() => { const root = document.querySelector('[data-user-notification-channel]'); const data = Alpine.$data(root); const aside = document.querySelector('aside'); return { open: data.open, desktop: data.desktop, errors: window.qaErrors, keys: window.qaKeys, inert: aside.inert, active: document.activeElement.tagName, bound: Object.keys(root._x_attributeCleanups || {}), transform: getComputedStyle(aside).transform }; })()`);
        steps.push({ role: 'admin', action: 'Escape closes mobile navigation', status: closed ? 'PASS' : 'FAIL', nativeKeyboard, navigationState });
        const screenshot = await call('Page.captureScreenshot', { format: 'png' });
        writeFileSync(join(evidence, 'mobile-navigation-escape.png'), Buffer.from(screenshot.data, 'base64'));
        if (! closed) problems.push('Escape does not close mobile navigation');
        for (const role of ['admin', 'receptionist']) {
            await call('Network.clearBrowserCookies');
            await go('/admin', 'Boolean(window.Livewire && document.querySelector("#email"))');
            await fill('#email', `${role}@clinicos.test`);
            await fill('#password', credential);
            await evaluate('document.querySelector("form").requestSubmit()');
            await waitFor('location.pathname.includes("dashboard")');
            await go('/receptionist/appointments/create');
            await evaluate(`Array.from(document.querySelectorAll('button')).find(button => button.textContent.trim() === 'Pasien baru').click()`);
            const unlabelled = await evaluate(`['name', 'nik', 'birth_date', 'gender', 'phone', 'email', 'address'].filter(name => !document.querySelector('[name="'+name+'"]')?.labels?.length)`);
            steps.push({ role, action: 'QA-10 new patient accessible labels', status: unlabelled.length ? 'FAIL' : 'PASS', unlabelled });
            if (unlabelled.length) problems.push(`${role}: new patient controls lack associated labels`);
        }
        assert.deepEqual(runtimeErrors, [], 'Browser workflows must not raise unhandled JavaScript errors');
        assert.deepEqual(problems, [], 'The supported browser workflows must remain usable');
    } finally {
        writeFileSync(join(evidence, 'browser-audit-steps.json'), JSON.stringify(steps, null, 2));
        socket?.close();
        chrome.kill();
        server.kill();
    }
});
