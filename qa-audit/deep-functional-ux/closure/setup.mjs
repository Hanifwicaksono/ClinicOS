import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {execFileSync} from 'node:child_process';
const evidence=join(import.meta.dirname,'evidence'),r=JSON.parse(readFileSync(join(evidence,'runtime.json')));
const file=join(r.root,'env.json'),env=JSON.parse(readFileSync(file));
assert.equal(env.DB_CONNECTION,'sqlite');assert.equal(env.DB_DATABASE,r.database);assert.ok(r.root.includes('/Temp/clinicos-deep-'));
const date=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
writeFileSync(join(r.root,'clock.txt'),date+'T02:00:00Z');
const app=join(r.root,'application.php');let source=readFileSync(app,'utf8');
source=source.replace('return $app;',`$app->booted(function () {
    $clock = trim(file_get_contents(getenv('AUDIT_ROOT').'/clock.txt'));
    Carbon\\CarbonImmutable::setTestNow(Carbon\\CarbonImmutable::parse($clock));
    Carbon\\Carbon::setTestNow(Carbon\\Carbon::parse($clock));
    Illuminate\\Support\\Facades\\Event::listen(Illuminate\\Mail\\Events\\MessageSent::class, function ($event) {
        file_put_contents(getenv('AUDIT_ROOT').'/mail.jsonl', json_encode(['to'=>array_map(fn($a)=>$a->getAddress(),$event->message->getTo()),'html'=>$event->message->getHtmlBody(),'text'=>$event->message->getTextBody()]).PHP_EOL, FILE_APPEND|LOCK_EX);
    });
});
return $app;`);
writeFileSync(app,source);
const fixture=join(r.root,'closure-fixtures.php');writeFileSync(fixture,readFileSync(join(import.meta.dirname,'fixtures.php.txt')));
try {const output=execFileSync('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd',fixture],{env,cwd:r.repo,encoding:'utf8',maxBuffer:4*1024*1024});writeFileSync(join(r.root,'setup-output.private'),output);const data=JSON.parse(output);assert.ok(data.dental.addon_id&&data.dental.visit);writeFileSync(join(evidence,'fixture.json'),JSON.stringify(data,null,2));}
catch(error){writeFileSync(join(r.root,'setup-diagnostic.private'),String(error.stdout||'')+String(error.stderr||''));throw new Error('Fixture setup failed; private diagnostic retained in disposable root');}
env.BROADCAST_CONNECTION='reverb';env.REVERB_SERVER_HOST='127.0.0.1';env.REVERB_SERVER_PORT='18962';
writeFileSync(file,JSON.stringify(env));
console.log('Disposable fixtures, controlled clock and mail capture ready; external providers remain disabled.');
