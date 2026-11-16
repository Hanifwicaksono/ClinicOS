import assert from 'node:assert/strict';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { base, browser, client, check, inspect, finish, shot, evidence, cleanUrl } from './common.mjs';

const seeds = [
    ['admin', 'admin@clinicos.test'], ['operational-admin', 'operational.admin@clinicos.test'],
    ['receptionist', 'receptionist@clinicos.test'], ['doctor', 'doctor@clinicos.test'],
    ['dentist', 'dentist@clinicos.test'], ['patient', 'patient@clinicos.test'], ['platform', 'platform@clinicos.test'],
    ['free-owner', 'owner.free@clinicos.test'], ['expired-owner', 'owner.expired@clinicos.test'], ['suspended-owner', 'owner.suspended@clinicos.test'],
];
const navigation = [];
try {
    for (const [role, email] of seeds) {
        let current;
        await check('DISC-LOGIN-' + role, 'F01', role, 'Login valid lalu dashboard sesuai persona', ['Buka login dari UI', 'Isi kredensial dummy', 'Klik masuk', 'Periksa tujuan dashboard'], async () => {
            current = await client(role, email);
            assert.ok(current.page.url().includes('/dashboard'));
            return { destination: cleanUrl(current.page.url()) };
        });
        if (!current) continue;
        const { page, context } = current;
        const initial = await inspect(page, role, 'dashboard');
        const nav = await page.locator('aside nav a').evaluateAll(nodes => nodes.map(n => ({ label: n.textContent.trim(), url: n.href })));
        navigation.push({ role, links: nav.map(n => ({ label: n.label, url: cleanUrl(n.url) })) });
        const visited = new Set([new URL(page.url()).pathname]);
        const queue = nav.map(n => ({ ...n, depth: 0 }));
        const allowed = new Set(['127.0.0.1','klinik-sehat-sentosa.localhost']);
        let inspected = 0;
        while (queue.length && inspected < 80) {
            const item = queue.shift();
            const url = new URL(item.url);
            if (!allowed.has(url.hostname) || visited.has(url.pathname) || /verify-email|auth\/google|reset-password|booking\/\d+\/status|previews\//.test(url.pathname)) continue;
            visited.add(url.pathname);
            try {
                await page.goto(item.url, { waitUntil: 'domcontentloaded' });
                const snap = await inspect(page, role, item.label || url.pathname);
                inspected++;
                if (snap.responsive.some(v => v.overflow)) await shot(page, `overflow-${role}-${inspected}`);
                if (item.depth < 2) {
                    const distinct = new Set();
                    for (const link of snap.links.filter(n => n.visible)) {
                        const target = new URL(link.target);
                        // Pagination is inspected once; numeric detail pages are sampled up to 3 per resource.
                        const family = target.pathname.replace(/\d+/g, ':id');
                        const count = [...distinct].filter(value => value.startsWith(family + '|')).length;
                        if (count >= 3) continue;
                        distinct.add(family + '|' + target.pathname);
                        if (!visited.has(target.pathname)) queue.push({ label: link.label, url: link.target, depth: item.depth + 1 });
                    }
                }
            } catch (error) {
                navigation.push({ role, failure: url.pathname, error_type: error.name });
            }
        }
        await page.goto(base + '/profile');
        await inspect(page, role, 'Profil akun');
        await page.goto(base + '/dashboard');
        await page.waitForLoadState('domcontentloaded');
        await check('DISC-LOGOUT-' + role, 'F01', role, 'Logout melalui navigasi dan login ulang', ['Klik Keluar pada navigasi', 'Periksa landing', 'Buka dashboard kembali'], async () => {
            await page.getByRole('button', { name: 'Keluar', exact: true }).click();
            await page.waitForURL(url => url.pathname === '/');
            await page.goto(base + '/dashboard');
            assert.ok(['/admin','/login'].includes(new URL(page.url()).pathname));
            return { protected_after_logout: true };
        });
        await context.close();
    }
    const guest = await client('guest');
    for (const path of ['/', '/login', '/admin', '/register', '/register/clinic', '/forgot-password', '/clinic/klinik-sehat-sentosa', '/display/klinik-sehat-sentosa', '/clinic/klinik-sehat-sentosa/booking']) {
        await guest.page.goto(base + path);
        await inspect(guest.page, 'guest', path);
    }
    await guest.context.close();
    writeFileSync(join(evidence, 'navigation.json'), JSON.stringify(navigation, null, 2));
} finally { await finish('1-discovery-access-responsive'); }
