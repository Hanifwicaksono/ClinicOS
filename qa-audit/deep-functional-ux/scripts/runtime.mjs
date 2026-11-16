import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { randomBytes } from 'node:crypto';
import assert from 'node:assert/strict';

const repo = resolve(import.meta.dirname, '../../..');
const evidence = process.env.CLINICOS_AUDIT_EVIDENCE || join(repo, 'qa-audit/deep-functional-ux/evidence');
mkdirSync(evidence, { recursive: true });
const root = mkdtempSync(join(tmpdir(), 'clinicos-deep-')).replaceAll('\\', '/');
const base = 'http://127.0.0.1:18961';
const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: base,
    APP_KEY: 'base64:' + randomBytes(32).toString('base64'), AUDIT_ROOT: root, AUDIT_REPO: repo,
    APP_CONFIG_CACHE: join(root, 'config.php'), APP_ROUTES_CACHE: join(root, 'routes.php'),
    APP_SERVICES_CACHE: join(root, 'services.php'), APP_PACKAGES_CACHE: join(root, 'packages.php'),
    LARAVEL_STORAGE_PATH: join(root, 'storage'), DB_CONNECTION: 'sqlite', DB_DATABASE: join(root, 'audit.sqlite'), DB_URL: '',
    QUEUE_CONNECTION: 'sync', BROADCAST_CONNECTION: 'null', CACHE_STORE: 'array',
    SESSION_DRIVER: 'database', SESSION_COOKIE: 'clinicos_deep_audit', SESSION_DOMAIN: 'null', SESSION_SECURE_COOKIE: 'false',
    MAIL_MAILER: 'array', DOKU_CLIENT_ID: '', DOKU_SECRET_KEY: '', GOOGLE_CLIENT_ID: '', GOOGLE_CLIENT_SECRET: '',
    REVERB_APP_ID: 'qa-deep-isolated', REVERB_APP_KEY: 'qa-deep-isolated', REVERB_APP_SECRET: 'qa-deep-disposable', REVERB_HOST: '127.0.0.1', REVERB_PORT: '18962', REVERB_SCHEME: 'http',
    VITE_REVERB_APP_KEY: 'qa-deep-isolated', VITE_REVERB_HOST: '127.0.0.1', VITE_REVERB_PORT: '18962', VITE_REVERB_SCHEME: 'http',
};
for (const key of ['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'LARAVEL_STORAGE_PATH', 'DB_DATABASE']) {
    env[key] = env[key].replaceAll('\\', '/');
}
for (const directory of ['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private', 'app/public']) {
    mkdirSync(join(root, 'storage', directory), { recursive: true });
}
mkdirSync(join(root, 'public'), { recursive: true });
const bootstrap = `<?php
require getenv('AUDIT_REPO').'/vendor/autoload.php';
if (getenv('DB_CONNECTION') !== 'sqlite' || !str_starts_with(getenv('DB_DATABASE'), getenv('AUDIT_ROOT')) || !str_contains(getenv('AUDIT_ROOT'), 'clinicos-deep-')) { throw new RuntimeException('Audit isolation guard'); }
$app = require getenv('AUDIT_REPO').'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->useStoragePath(getenv('AUDIT_ROOT').'/storage');
$app->usePublicPath(getenv('AUDIT_ROOT').'/public');
return $app;
`;
writeFileSync(join(root, 'application.php'), bootstrap);
writeFileSync(join(root, 'artisan.php'), `<?php
$app = require __DIR__.'/application.php';
exit($app->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput));
`);
writeFileSync(join(root, 'router.php'), `<?php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$public = str_starts_with($path, '/build/') ? __DIR__.'/public' : (str_starts_with($path, '/storage/') ? __DIR__.'/public' : getenv('AUDIT_REPO').'/public');
if (str_starts_with($path, '/storage/')) { $public = __DIR__.'/storage/app/public'; $path = substr($path, 8); }
$file = realpath($public.$path);
if ($path !== '/' && $file && is_file($file) && str_starts_with($file, realpath($public).DIRECTORY_SEPARATOR) && !str_ends_with($file, '.php')) {
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    header('Content-Type: '.(['js'=>'application/javascript','css'=>'text/css','png'=>'image/png','svg'=>'image/svg+xml','woff2'=>'font/woff2','jpg'=>'image/jpeg'][$extension] ?? 'application/octet-stream'));
    readfile($file); return;
}
$app = require __DIR__.'/application.php';
$kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class);
$kernel->bootstrap();
if (!str_starts_with(config('filesystems.disks.local.root'), getenv('AUDIT_ROOT')) || config('database.default') !== 'sqlite') { throw new RuntimeException('Storage/database isolation guard'); }
$response = $kernel->handle($request = Illuminate\\Http\\Request::capture());
$response->headers->set('X-Audit-Isolated', 'true');
$response->send();
$kernel->terminate($request, $response);
`);
const php = ['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=gd'];
function command(name, args, file) {
    try {
        const output = execFileSync(name, args, { cwd: repo, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
        writeFileSync(join(evidence, file), output);
    } catch (error) {
        writeFileSync(join(root, file + '.private'), String(error.stdout) + String(error.stderr));
        throw new Error(`${file} failed; private diagnostic retained in temporary runtime`);
    }
}
command('php', [...php, join(root, 'artisan.php'), 'migrate', '--force', '--no-interaction'], 'migrations.txt');
command('php', [...php, join(root, 'artisan.php'), 'db:seed', '--class=DemoSeeder', '--force', '--no-interaction'], 'fixtures.txt');
command('php', [...php, join(root, 'artisan.php'), 'route:list', '--json', '--no-interaction'], 'routes.json');
command('node', ['node_modules/vite/bin/vite.js', 'build', '--outDir', join(root, 'public/build')], 'build.txt');
const { DatabaseSync } = await import('node:sqlite');
const db = new DatabaseSync(env.DB_DATABASE);
assert.ok(db.prepare('SELECT COUNT(*) AS n FROM users').get().n > 5);
const roles = db.prepare('SELECT r.name, p.name AS permission FROM roles r JOIN role_has_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id ORDER BY r.name,p.name').all();
writeFileSync(join(evidence, 'roles-permissions.json'), JSON.stringify(roles, null, 2));
const original = db.prepare("SELECT * FROM users WHERE email='admin@clinicos.test'").get();
db.prepare('INSERT INTO users(name,email,password,email_verified_at,is_active,clinic_id,account_type,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)')
    .run('QA Operational Admin', 'operational.admin@clinicos.test', original.password, original.email_verified_at, 1, original.clinic_id, null, original.created_at, original.updated_at);
const operationalId = db.prepare("SELECT id FROM users WHERE email='operational.admin@clinicos.test'").get().id;
db.prepare("INSERT INTO model_has_roles(role_id,model_type,model_id) SELECT id,'App\\Models\\User',? FROM roles WHERE name='Clinic Admin'").run(operationalId);
db.close();
writeFileSync(join(root, 'env.json'), JSON.stringify(env));
const runtime = { root, repo, base, database: env.DB_DATABASE, started: new Date().toISOString(), playwright: 'C:/Users/User/AppData/Local/Temp/clinicos-readiness-0_thp4gu/tools/node_modules/playwright/index.mjs' };
writeFileSync(join(evidence, 'runtime.json'), JSON.stringify(runtime, null, 2));
writeFileSync(join(evidence, 'baseline-git-status.txt'), execFileSync('git', ['status', '--short'], { cwd: repo }));
const server = spawn('php', [...php, '-S', '127.0.0.1:18961', join(root, 'router.php')], { cwd: repo, env, stdio: ['ignore', 'ignore', 'ignore'], windowsHide: true });
process.on('SIGINT', () => { server.kill(); process.exit(); });
process.on('SIGTERM', () => { server.kill(); process.exit(); });
server.on('exit', () => process.exit());
console.log(JSON.stringify({ runtime: 'ready', isolated: true, root, server_pid: server.pid }));
