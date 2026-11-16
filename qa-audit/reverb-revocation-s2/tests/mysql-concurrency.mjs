import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,existsSync,mkdirSync} from 'node:fs';
import {join} from 'node:path';
import {spawn,execFileSync} from 'node:child_process';
const evidence=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(evidence,'runtime.json'))),env=JSON.parse(readFileSync(r.root+'/mysql-env.json')),script=join(import.meta.dirname,'mysql-concurrency.php');
const barrier=r.root+'/mysql/concurrency-'+Date.now();mkdirSync(barrier);env.S2_CONCURRENCY_ROOT=barrier;
const invoke=(...args)=>JSON.parse(execFileSync('php',['-d','extension=gd',script,...args],{env,cwd:r.repo,encoding:'utf8'}));
const setup=invoke('setup');
function writer(name){const child=spawn('php',['-d','extension=gd',script,name,String(setup.user_id)],{env,cwd:r.repo,windowsHide:true,stdio:['ignore','pipe','pipe']});let out='',err='';child.stdout.on('data',b=>out+=b);child.stderr.on('data',b=>err+=b);return new Promise((yes,no)=>child.on('exit',code=>code?no(new Error('Isolated writer failed: '+err.slice(0,200))):yes(JSON.parse(out))));}
const a=writer('a'),b=writer('b');for(let i=0;i<150;i++){if(existsSync(barrier+'/ready-a')&&existsSync(barrier+'/ready-b'))break;await new Promise(r=>setTimeout(r,100));}
assert.ok(existsSync(barrier+'/ready-a')&&existsSync(barrier+'/ready-b'));writeFileSync(barrier+'/release','go');
const writers=await Promise.all([a,b]);const verified=invoke('verify',String(setup.user_id));assert.ok(writers.every(w=>w.loaded_epoch===setup.version));assert.deepEqual(writers.map(w=>w.saved_epoch).sort(),[setup.version+1,setup.version+2]);assert.equal(verified.version,setup.version+2);assert.deepEqual(verified.versions,[setup.version+1,setup.version+2]);assert.equal(verified.active,false);assert.equal(verified.password_changed,true);assert.equal(verified.rollback_valid,true);
const result={status:'PASS',database:'dedicated MySQL 8.0.30 on loopback 13318',setup,writers,verified,tests:{concurrent_stale_writers:'PASS',atomic_rollback:'PASS'},transport_ack_on_mysql:'NOT TESTED by this backend-only concurrency probe'};writeFileSync(join(evidence,process.env.S2_CONCURRENCY_REPORT||'mysql-concurrency.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
