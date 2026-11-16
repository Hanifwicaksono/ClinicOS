import {readFileSync,writeFileSync,mkdirSync,mkdtempSync,cpSync,readdirSync,statSync,openSync} from 'node:fs';
import {join,resolve} from 'node:path';
import {tmpdir} from 'node:os';
import {randomBytes,createHash} from 'node:crypto';
import {execFileSync,spawn} from 'node:child_process';
import net from 'node:net';
import assert from 'node:assert/strict';

const repo=resolve(import.meta.dirname,'../../..');
const evidence=join(import.meta.dirname,'../evidence');mkdirSync(evidence,{recursive:true});
const hashes={};
function hashTree(dir){for(const name of readdirSync(join(repo,dir))){const p=join(dir,name).replaceAll('\\','/');if(statSync(join(repo,p)).isDirectory())hashTree(p);else hashes[p]=createHash('sha256').update(readFileSync(join(repo,p))).digest('hex');}}
for(const dir of ['app','bootstrap','config','database','routes','resources','tests','public/build','vendor/laravel/reverb','vendor/laravel/framework/src/Illuminate/Broadcasting','vendor/spatie/laravel-permission','node_modules/laravel-echo','node_modules/pusher-js'])hashTree(dir);
function historical(dir){for(const name of readdirSync(join(repo,dir))){const p=join(dir,name).replaceAll('\\','/');if(p.includes('reverb-revocation-spike'))continue;if(statSync(join(repo,p)).isDirectory())historical(p);else if(p.endsWith('.md'))hashes[p]=createHash('sha256').update(readFileSync(join(repo,p))).digest('hex');}}
historical('qa-audit');for(const p of ['.env','composer.json','composer.lock','package.json','package-lock.json'])hashes[p]=createHash('sha256').update(readFileSync(join(repo,p))).digest('hex');
writeFileSync(join(evidence,'source-before.json'),JSON.stringify(hashes,null,2));
writeFileSync(join(evidence,'git-before.txt'),execFileSync('git',['status','--short'],{cwd:repo}));
for(const port of [18961,18962])await new Promise((yes,no)=>{const server=net.createServer();server.once('error',no);server.listen(port,'127.0.0.1',()=>server.close(yes));});
const previous=JSON.parse(readFileSync(join(repo,'qa-audit/product-completeness/f1-implementation/evidence/final/runtime.json')));
const root=mkdtempSync(join(tmpdir(),'clinicos-deep-revocation-')).replaceAll('\\','/');cpSync(previous.root,root,{recursive:true});
const env=JSON.parse(readFileSync(join(root,'env.json')));
for(const [key,value] of Object.entries(env))if(typeof value==='string')env[key]=value.replaceAll(previous.root,root);
Object.assign(env,{AUDIT_ROOT:root,AUDIT_REPO:repo,SPIKE_EVIDENCE:evidence,APP_KEY:'base64:'+randomBytes(32).toString('base64'),DB_CONNECTION:'sqlite',DB_DATABASE:root+'/audit.sqlite',DB_URL:'',DB_USERNAME:'',DB_PASSWORD:'',QUEUE_CONNECTION:'database',BROADCAST_CONNECTION:'spike',MAIL_MAILER:'array',REVERB_APP_ID:'qa-deep',REVERB_APP_KEY:'qa-deep-isolated',REVERB_APP_SECRET:'qa-deep-disposable',REVERB_HOST:'127.0.0.1',REVERB_PORT:'18962',REVERB_SCHEME:'http',REVERB_SERVER_HOST:'127.0.0.1',REVERB_SERVER_PORT:'18962',REVERB_SCALING_ENABLED:'false',DOKU_CLIENT_ID:'',DOKU_SECRET_KEY:'',GOOGLE_CLIENT_ID:'',GOOGLE_CLIENT_SECRET:''});
assert.ok(env.DB_DATABASE.startsWith(root));writeFileSync(join(root,'env.json'),JSON.stringify(env));writeFileSync(join(root,'mode.txt'),'baseline');
let application=readFileSync(join(root,'application.php'),'utf8');application=application.replace('return $app;',`require getenv('AUDIT_REPO').'/qa-audit/reverb-revocation-spike/experiments/Spike.php';\n$app->register(RevocationSpike\\SpikeProvider::class);\nreturn $app;`);writeFileSync(join(root,'application.php'),application);
const runtime={root,repo,base:'http://127.0.0.1:18961',ws:'ws://127.0.0.1:18962',database:env.DB_DATABASE,playwright:previous.playwright,started:new Date().toISOString(),provenance:'Clone of retained synthetic F1 final SQLite/private storage; no user database connection'};
writeFileSync(join(evidence,'runtime.json'),JSON.stringify(runtime,null,2));
const php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];
writeFileSync(join(evidence,'fixture.json'),execFileSync('php',[...php,join(import.meta.dirname,'control.php'),'setup'],{cwd:repo,env,encoding:'utf8'}));
const processes={};
for(const [name,args] of Object.entries({http:['-S','127.0.0.1:18961',root+'/router.php'],reverb:[root+'/artisan.php','reverb:start','--host=127.0.0.1','--port=18962','--no-interaction'],worker:[root+'/artisan.php','queue:work','database','--sleep=1','--tries=1','--no-interaction']})){
const child=spawn('php',[...php,...args],{cwd:repo,env,windowsHide:true,stdio:['ignore',openSync(join(root,name+'.stdout.log'),'a'),openSync(join(root,name+'.stderr.log'),'a')]});processes[name]=child.pid;
}
writeFileSync(join(evidence,'processes.json'),JSON.stringify(processes,null,2));
console.log(JSON.stringify({isolated:true,root,processes}));
const children=Object.values(processes);for(const signal of ['SIGINT','SIGTERM'])process.on(signal,()=>{for(const pid of children)try{process.kill(pid);}catch{}process.exit();});
setInterval(()=>{},1000);
