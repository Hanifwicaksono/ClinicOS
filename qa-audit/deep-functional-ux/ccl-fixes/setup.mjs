import {readFileSync,writeFileSync,copyFileSync} from 'node:fs';
import {join} from 'node:path';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
const e=join(import.meta.dirname,'evidence'),r=JSON.parse(readFileSync(join(e,'runtime.json'))),env=JSON.parse(readFileSync(join(r.root,'env.json')));
assert.equal(env.DB_CONNECTION,'sqlite');assert.equal(env.DB_DATABASE,r.database);
writeFileSync(join(r.root,'clock.txt'),'2026-11-14T02:00:00Z');
const app=join(r.root,'application.php');let source=readFileSync(app,'utf8');assert.ok(!source.includes('setTestNow'));
source=source.replace('return $app;',`$app->booted(function () {
    $clock = trim(file_get_contents(getenv('AUDIT_ROOT').'/clock.txt'));
    Carbon\\CarbonImmutable::setTestNow(Carbon\\CarbonImmutable::parse($clock));
    Carbon\\Carbon::setTestNow(Carbon\\Carbon::parse($clock));
});
return $app;`);writeFileSync(app,source);
copyFileSync(join(import.meta.dirname,'../closure/fixtures.php.txt'),join(r.root,'ccl-fixtures.php'));
const php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];
try{const data=JSON.parse(execFileSync('php',[...php,join(r.root,'ccl-fixtures.php')],{cwd:r.repo,env,encoding:'utf8',maxBuffer:4*1024*1024}));assert.ok(data.entitlement_domain_verified);writeFileSync(join(e,'fixture.json'),JSON.stringify(data,null,2));}catch(error){writeFileSync(join(r.root,'fixture-error.private'),String(error.stdout||'')+String(error.stderr||''));throw Error('Isolated setup failed; private diagnostic retained.');}
env.SESSION_EXPIRE_ON_CLOSE='true';env.BROADCAST_CONNECTION='reverb';env.REVERB_SERVER_HOST='127.0.0.1';env.REVERB_SERVER_PORT='18962';writeFileSync(join(r.root,'env.json'),JSON.stringify(env));
const router=join(r.root,'router.php');let routeSource=readFileSync(router,'utf8');
routeSource=routeSource.replace('$response = $kernel->handle',"if (($_SERVER['HTTP_X_CCL_LEGACY_CSRF'] ?? '') === '1') { unset($_SERVER['HTTP_SEC_FETCH_SITE']); }\n$response = $kernel->handle");
writeFileSync(router,routeSource);
for(const file of ['serve.mjs','worker.mjs'])copyFileSync(join(import.meta.dirname,'../closure',file),join(import.meta.dirname,file));
console.log('New isolated fixture ready; clock fixed14Nov09:00WIB; provider credentials disabled.');
