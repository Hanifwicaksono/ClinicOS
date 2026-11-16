import {spawnSync} from 'node:child_process';
import {mkdirSync,mkdtempSync,writeFileSync,readFileSync,existsSync} from 'node:fs';
import {join,resolve} from 'node:path';
import {tmpdir} from 'node:os';
import {randomBytes} from 'node:crypto';
const repo=resolve(import.meta.dirname,'../../..');
const label=process.env.DFU_TEST_LABEL||'php';const evidence=join(import.meta.dirname,'evidence');mkdirSync(evidence,{recursive:true});
const root=mkdtempSync(join(tmpdir(),'clinicos-dfu-tests-'));
const env={...process.env,APP_ENV:'testing',APP_URL:'http://localhost',APP_KEY:'base64:'+randomBytes(32).toString('base64'),DB_CONNECTION:'sqlite',DB_DATABASE:':memory:',DB_URL:'',SESSION_DRIVER:'array',QUEUE_CONNECTION:'sync',BROADCAST_CONNECTION:'null',CACHE_STORE:'array',MAIL_MAILER:'array',LARAVEL_STORAGE_PATH:join(root,'storage'),DOKU_CLIENT_ID:'',DOKU_SECRET_KEY:''};
for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])env[key]=join(root,key+'.php').replaceAll('\\','/').slice(2);
for(const path of ['framework/views','framework/cache/data','framework/sessions','logs','app/private','app/public'])mkdirSync(join(root,'storage',path),{recursive:true});
const args=process.argv.slice(2);const result=spawnSync('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd','vendor/bin/phpunit',...args,'--no-progress','--do-not-cache-result','--log-junit',join(evidence,label+'.xml')],{cwd:repo,env,encoding:'utf8',timeout:300000,maxBuffer:20*1024*1024});
writeFileSync(join(evidence,label+'.txt'),(result.stdout||'')+(result.stderr||''));
let summary;try{const data=JSON.parse((result.stdout||'').trim());summary={tests:data.tests,passed:data.passed,failed:data.failed,result:data.result,assertions:data.assertions,failures:data.failures?.map(f=>({test:f.test,message:f.message?.split('\n')[0]}))};}catch{summary={exit:result.status,error:result.error?.code};}
writeFileSync(join(evidence,label+'-summary.json'),JSON.stringify(summary,null,2));console.log(JSON.stringify(summary));
