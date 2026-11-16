import assert from'node:assert/strict';import{readFileSync,writeFileSync,mkdirSync,cpSync,existsSync}from'node:fs';import{join,relative}from'node:path';import{spawn,execFileSync}from'node:child_process';
const e=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(e,'runtime.json'))),base=JSON.parse(readFileSync(r.root+'/env.json')),prior=JSON.parse(readFileSync(join(r.repo,'qa-audit/reverb-revocation-s2/evidence/runtime.json'))),php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'],script=join(import.meta.dirname,'notification-probe.php');
const sleep=ms=>new Promise(r=>setTimeout(r,ms));async function wait(file){for(let i=0;i<300;i++){if(existsSync(file))return;await sleep(25);}throw Error('Barrier did not appear: '+file);}
const results=[];
for(const mode of ['deferred','immediate','configured','mysql']){
 const dir=r.root+'/probe-'+mode+'-'+Date.now();mkdirSync(dir,{recursive:true});const env={...base,PROBE_ROOT:dir,PROBE_MODE:mode,BROADCAST_CONNECTION:'null',DB_CONNECTION:'sqlite',DB_DATABASE:dir+'/audit.sqlite'};cpSync(prior.database,env.DB_DATABASE);
 for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])env[key]=relative(r.repo,dir+'/'+key+'.php');
 if(mode==='mysql'){
  env.DB_CONNECTION='mysql';env.DB_DATABASE='clinicos_post_s2_runtime_probe_'+Date.now();
  execFileSync('C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe',['--no-defaults','--host=127.0.0.1','--port=13318','--user=root'],{input:'CREATE DATABASE '+env.DB_DATABASE+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',stdio:'pipe'});
  execFileSync('php',[...php,r.root+'/artisan.php','migrate','--force','--no-interaction'],{env,cwd:r.repo,stdio:'pipe'});
  execFileSync('php',[...php,join(import.meta.dirname,'import-fixture.php')],{env:{...env,POST_SOURCE_SQLITE:prior.database,POST_INCLUDE_FAILED:'1'},cwd:r.repo,stdio:'pipe'});
 }
 function invoke(action){return JSON.parse(execFileSync('php',[...php,script,action],{env,cwd:r.repo,encoding:'utf8'}));}
 function child(action){const p=spawn('php',[...php,script,action],{env,cwd:r.repo,windowsHide:true,stdio:['ignore','pipe','pipe']});let out='',err='';p.stdout.on('data',x=>out+=x);p.stderr.on('data',x=>err+=x);return new Promise((yes,no)=>p.on('exit',code=>{if(code){writeFileSync(dir+'/'+action+'.error',err);no(Error('Private probe failed: '+mode+'/'+action));}else yes(JSON.parse(out));}));}
 const setup=invoke('setup'),worker=child('worker');await wait(dir+'/reader-ready');const writer=child('writer');await wait(dir+'/writer-ready');
 let writerBlocked=false;if(['immediate','configured'].includes(mode)){await sleep(200);writerBlocked=!existsSync(dir+'/writer-committed');assert.ok(writerBlocked);}else await wait(dir+'/writer-committed');
 const released=Date.now()/1000;writeFileSync(dir+'/release-reader','go');const [beforeRecovery]=await Promise.all([worker,writer]);
 if(mode==='deferred'){assert.equal(beforeRecovery.failed_target,true);assert.equal(beforeRecovery.locked,true);assert.equal(beforeRecovery.processed,0);}else{assert.equal(beforeRecovery.failed_target,false);assert.equal(beforeRecovery.processed,1);}
 assert.equal(beforeRecovery.notifications,setup.notifications);const recovery=mode==='deferred'?invoke('recovery'):null;if(recovery){assert.equal(recovery.failed_target,false);assert.equal(recovery.processed,1);assert.equal(recovery.notifications,setup.notifications);assert.equal(recovery.pending_jobs,0);}
 const result={mode,status:'PASS',setup,beforeRecovery,recovery,writerBlocked,released,writer_committed:Number(readFileSync(dir+'/writer-committed','utf8')),database:mode==='mysql'?'dedicated MySQL':'isolated SQLite WAL'};results.push(result);writeFileSync(join(e,'notification-probe.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({mode,status:'PASS',beforeRecovery,recovery,writerBlocked}));
}
