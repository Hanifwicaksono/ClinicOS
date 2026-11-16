import {readFileSync,writeFileSync,openSync} from 'node:fs';
import {spawn} from 'node:child_process';
import {join} from 'node:path';
import assert from 'node:assert/strict';
const evidence=join(import.meta.dirname,'../evidence');
const runtime=JSON.parse(readFileSync(join(evidence,'runtime.json')));
assert.ok(runtime.root.includes('/clinicos-deep-revocation-'));
const env=JSON.parse(readFileSync(runtime.root+'/env.json'));
const processes=JSON.parse(readFileSync(join(evidence,'processes.json')));
const action=process.argv[2]||'restart';
const names=action==='stop'?['http','reverb','worker']:action==='pause-worker'?['worker']:action==='start-worker'?[]:['reverb','worker'];
for(const name of names)try{process.kill(processes[name]);}catch{}
await new Promise(r=>setTimeout(r,300));
if(!['stop','pause-worker'].includes(action))for(const name of action==='start-worker'?['worker']:['reverb','worker']){
 const args=name==='reverb'?['reverb:start','--host=127.0.0.1','--port=18962','--no-interaction']:['queue:work','database','--sleep=1','--tries=1','--no-interaction'];
 const child=spawn('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd',runtime.root+'/artisan.php',...args],{env,cwd:runtime.repo,windowsHide:true,detached:true,stdio:['ignore',openSync(runtime.root+'/'+name+'.stdout.log','a'),openSync(runtime.root+'/'+name+'.stderr.log','a')]});
 processes[name]=child.pid;child.unref();
}
writeFileSync(join(evidence,'processes.json'),JSON.stringify(processes,null,2));
console.log(JSON.stringify({action,processes}));
