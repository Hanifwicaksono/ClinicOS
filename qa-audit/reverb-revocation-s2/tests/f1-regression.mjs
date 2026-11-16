import {readFileSync,writeFileSync,mkdirSync,mkdtempSync,cpSync,openSync} from 'node:fs';
import {join} from 'node:path';
import {tmpdir} from 'node:os';
import {randomBytes} from 'node:crypto';
import {spawn,spawnSync,execFileSync} from 'node:child_process';
import net from 'node:net';
const evidence=join(import.meta.dirname,'../evidence/f1-regression');mkdirSync(evidence,{recursive:true});
const main=JSON.parse(readFileSync(join(import.meta.dirname,'../evidence/runtime.json'))),source=JSON.parse(readFileSync(join(main.repo,'qa-audit/deep-functional-ux/ccl-fixes/evidence/runtime.json')));
await new Promise((yes,no)=>{const s=net.createServer();s.once('error',no);s.listen(18963,'127.0.0.1',()=>s.close(yes));});
const root=mkdtempSync(join(tmpdir(),'clinicos-deep-s2-f1-')).replaceAll('\\','/');cpSync(source.root,root,{recursive:true});const env=JSON.parse(readFileSync(main.root+'/env.json'));for(const [k,v]of Object.entries(env))if(typeof v==='string')env[k]=v.replaceAll(main.root,root);Object.assign(env,{AUDIT_ROOT:root,APP_KEY:'base64:'+randomBytes(32).toString('base64'),DB_DATABASE:root+'/audit.sqlite',APP_URL:'http://127.0.0.1:18963',BROADCAST_CONNECTION:'null',CLINICOS_AUDIT_EVIDENCE:evidence});writeFileSync(root+'/env.json',JSON.stringify(env));cpSync(main.root+'/legacy-public/build',root+'/public/build',{recursive:true});
writeFileSync(join(evidence,'runtime.json'),JSON.stringify({...main,root,base:env.APP_URL,database:env.DB_DATABASE,provenance:'Independent fresh CCL dummy clone for unchanged F1 Playwright scenarios'},null,2));cpSync(join(main.repo,'qa-audit/deep-functional-ux/ccl-fixes/evidence/fixture.json'),join(evidence,'fixture.json'));
const php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];writeFileSync(join(evidence,'migrate.txt'),execFileSync('php',[...php,root+'/artisan.php','migrate','--force','--no-interaction'],{cwd:main.repo,env,encoding:'utf8'}));
const server=spawn('php',[...php,'-S','127.0.0.1:18963',root+'/router.php'],{cwd:main.repo,env,windowsHide:true,stdio:['ignore',openSync(root+'/http.out','a'),openSync(root+'/http.err','a')]});
try{await new Promise(r=>setTimeout(r,700));const result=spawnSync('node',[join(main.repo,'qa-audit/product-completeness/f1-implementation/browser.mjs')],{cwd:main.repo,env,encoding:'utf8',maxBuffer:16*1024*1024,windowsHide:true});writeFileSync(join(evidence,'run.txt'),result.stdout+result.stderr);writeFileSync(join(evidence,'exit.json'),JSON.stringify({exit:result.status,original_script_and_assertions_unchanged:true,at:new Date().toISOString()},null,2));console.log(JSON.stringify({exit:result.status,tail:result.stdout.slice(-2200)}));}finally{server.kill();}
