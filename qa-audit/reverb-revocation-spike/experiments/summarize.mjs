import {readFileSync,writeFileSync,readdirSync} from 'node:fs';
import {join} from 'node:path';
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {DatabaseSync} from 'node:sqlite';
const evidence=join(import.meta.dirname,'../evidence');const runtime=JSON.parse(readFileSync(join(evidence,'runtime.json')));
const before=JSON.parse(readFileSync(join(evidence,'source-before.json')));const changed=[];
for(const [path,hash] of Object.entries(before))try{if(createHash('sha256').update(readFileSync(join(runtime.repo,path))).digest('hex')!==hash)changed.push(path);}catch{changed.push(path);}
writeFileSync(join(evidence,'source-integrity.json'),JSON.stringify({at:new Date().toISOString(),checked:Object.keys(before).length,changed,scope:'Existing application, tests, config, migrations, resources, public build, .env hash only, dependency manifests, installed Reverb/broadcasting/Spatie/Echo/Pusher sources, historical QA Markdown'},null,2));
writeFileSync(join(evidence,'git-after.txt'),execFileSync('git',['status','--short'],{cwd:runtime.repo}));
const runs=['smoke','main','extended','supplemental','transport-r1','final-guard','post-format','notification-types'];const raw=[],latest=new Map();
for(const run of runs)for(const r of JSON.parse(readFileSync(join(evidence,'results-'+run+'.json')))){const result={...r,run,canonical_id:r.id.replace(/-R\d+$/,'')};raw.push(result);latest.set(result.canonical_id,result);}
const wire=readFileSync(join(evidence,'wire.jsonl'),'utf8').trim().split(/\r?\n/).map(JSON.parse);
const sql=new DatabaseSync(runtime.database);
const backend=JSON.parse(readFileSync(join(evidence,'backend-checks.json')));
const lock=JSON.parse(readFileSync(join(runtime.repo,'composer.lock')));
const packageLock=JSON.parse(readFileSync(join(runtime.repo,'package-lock.json')));
const summary={at:new Date().toISOString(),runs:Object.fromEntries(runs.map(run=>[run,{PASS:raw.filter(r=>r.run===run&&r.status==='PASS').length,FAIL:raw.filter(r=>r.run===run&&r.status==='FAIL').length}])),raw_browser:{PASS:raw.filter(r=>r.status==='PASS').length,FAIL:raw.filter(r=>r.status==='FAIL').length},latest_browser:{PASS:[...latest.values()].filter(r=>r.status==='PASS').length,FAIL:[...latest.values()].filter(r=>r.status==='FAIL').length},backend:{PASS:backend.PASS,FAIL:backend.FAIL},failures:raw.filter(r=>r.status==='FAIL').map(r=>({id:r.id,run:r.run,error:r.error})),matrix:[...latest.values()].map(r=>({id:r.canonical_id,title:r.title,assertion_status:r.status,security_result:r.observed?.security_result||'See scenario assertion',evidence:'results-'+r.run+'.json'})),wire:{counts:Object.fromEntries([...new Set(wire.map(r=>r.type))].map(type=>[type,wire.filter(r=>r.type===type).length])),sent_queue_events:[...new Set(wire.filter(r=>r.type==='sent'&&r.queue_id>0).map(r=>r.queue_id))].length},queue:{pending:sql.prepare('select count(*) n from jobs').get().n,failed:sql.prepare('select count(*) n from failed_jobs').get().n},versions:{php:execFileSync('php',['-r','echo PHP_VERSION;'],{encoding:'utf8'}),node:process.version,php_packages:lock.packages.filter(p=>['laravel/framework','laravel/reverb','pusher/pusher-php-server','spatie/laravel-permission','livewire/livewire'].includes(p.name)).map(p=>({name:p.name,version:p.version})),js_packages:Object.fromEntries(['laravel-echo','pusher-js','vite','tailwindcss'].map(name=>[name,packageLock.packages['node_modules/'+name].version]))},source_integrity:{checked:Object.keys(before).length,changed},pr01_status:'OPEN; S1 only; S2 not started'};
writeFileSync(join(evidence,'summary.json'),JSON.stringify(summary,null,2));sql.close();console.log(JSON.stringify({...summary,matrix:undefined},null,2));
