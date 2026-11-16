import {readFileSync,writeFileSync,openSync} from 'node:fs';
import {spawn} from 'node:child_process';
import {join} from 'node:path';
import assert from 'node:assert/strict';
const evidence=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(evidence,'runtime.json'))),env=JSON.parse(readFileSync(r.root+'/env.json')),p=JSON.parse(readFileSync(join(evidence,'processes.json'))),action=process.argv[2]||'restart';assert.ok(r.root.includes('/clinicos-deep-s2-'));
const names=action==='stop'?['http','reverb','worker','master']:['reverb','worker'];for(const name of names)try{process.kill(p[name]);}catch{}
await new Promise(r=>setTimeout(r,300));
if(action!=='stop')for(const name of ['reverb','worker']){const args=name==='reverb'?['reverb:start','--host=127.0.0.1','--port=18962','--no-interaction']:['queue:work','database','--sleep=1','--tries=4','--no-interaction'];const child=spawn('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd',r.root+'/artisan.php',...args],{env,cwd:r.repo,windowsHide:true,detached:true,stdio:['ignore',openSync(r.root+'/'+name+'.stdout.log','a'),openSync(r.root+'/'+name+'.stderr.log','a')]});p[name]=child.pid;child.unref();}
writeFileSync(join(evidence,'processes.json'),JSON.stringify(p,null,2));console.log(JSON.stringify({action,pids:p}));
