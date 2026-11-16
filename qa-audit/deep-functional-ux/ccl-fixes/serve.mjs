import {readFileSync,writeFileSync,existsSync} from 'node:fs';
import {join} from 'node:path';
import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';
const e=join(import.meta.dirname,'evidence'),r=JSON.parse(readFileSync(join(e,'runtime.json'))),env=JSON.parse(readFileSync(join(r.root,'env.json')));
assert.equal(env.DB_DATABASE,r.database);assert.equal(env.DB_CONNECTION,'sqlite');
env.SESSION_EXPIRE_ON_CLOSE='true';
const args=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd','-d','upload_max_filesize=8M','-d','post_max_size=16M'];
let reverb;
function start(){reverb=spawn('php',[...args,join(r.root,'artisan.php'),'reverb:start','--host=127.0.0.1','--port=18962','--no-interaction'],{cwd:r.repo,env,windowsHide:true,stdio:'ignore'});}
start();
const php=spawn('php',[...args,'-S','127.0.0.1:18961',join(r.root,'router.php')],{cwd:r.repo,env,windowsHide:true,stdio:'ignore'});
const control=join(r.root,'reverb-control.txt');writeFileSync(control,'start');let last='start';
setInterval(()=>{if(!existsSync(control))return;const cmd=readFileSync(control,'utf8').trim();if(cmd===last)return;last=cmd;if(cmd==='stop')reverb?.kill();if(cmd==='start')start();},250);
for(const signal of ['SIGTERM','SIGINT'])process.on(signal,()=>{php.kill();reverb?.kill();process.exit();});
php.on('exit',()=>{reverb?.kill();process.exit();});
writeFileSync(join(e,'server.json'),JSON.stringify({php_pid:php.pid,master_pid:process.pid,isolated:true},null,2));
console.log(JSON.stringify({isolated_server:php.pid,reverb:reverb.pid}));
