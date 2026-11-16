import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
const repo = resolve(import.meta.dirname, '../../..');
const evidence = join(repo, 'qa-audit/deep-functional-ux/evidence');
const root = mkdtempSync(join(tmpdir(), 'clinicos-deep-regression-'));
const runtime = JSON.parse(readFileSync(join(evidence, 'runtime.json')));
const env = { ...JSON.parse(readFileSync(join(runtime.root, 'env.json'))),
    APP_ENV: 'testing', APP_URL: 'http://localhost', DB_DATABASE: ':memory:', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', LARAVEL_STORAGE_PATH: join(root, 'storage'),
    APP_CONFIG_CACHE: join(root, 'config.php').replaceAll('\\','/').slice(2),
    APP_ROUTES_CACHE: join(root, 'routes.php').replaceAll('\\','/').slice(2),
    APP_SERVICES_CACHE: join(root, 'services.php').replaceAll('\\','/').slice(2),
    APP_PACKAGES_CACHE: join(root, 'packages.php').replaceAll('\\','/').slice(2),
};
for (const dir of ['framework/views','framework/cache/data','framework/sessions','logs','app/private','app/public']) mkdirSync(join(root,'storage',dir),{recursive:true});
const result = spawnSync('php', ['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd', 'vendor/bin/phpunit', 'tests', 'qa-audit/tests', '--no-progress','--do-not-cache-result','--log-junit', join(evidence,'regression.xml')], { cwd: repo, env, encoding:'utf8', timeout:300000 });
const output = ((result.stdout || '') + (result.stderr || '')).replace(/where \\"id\\" = [A-Za-z0-9]+/g, 'where [session identifier redacted]');
writeFileSync(join(evidence,'regression.txt'), output);
writeFileSync(join(evidence,'regression-summary.json'), JSON.stringify({ exit:result.status, signal:result.signal, error:result.error?.code, database:'SQLite :memory:', storage:'disposable', at:new Date().toISOString() },null,2));
console.log(JSON.stringify({exit:result.status,signal:result.signal}));
