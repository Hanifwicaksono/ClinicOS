import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {join,relative} from 'node:path';
import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const evidence=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(evidence,'runtime.json'))),env=JSON.parse(readFileSync(r.root+'/env.json')),mode=process.argv[2];
let command='php',args,label;
if(mode==='php'){
 label='php-full-isolated-final';Object.assign(env,{APP_ENV:'testing',APP_URL:'http://localhost',DB_CONNECTION:'sqlite',DB_DATABASE:':memory:',DB_URL:'',SESSION_DRIVER:'array',CACHE_STORE:'array',QUEUE_CONNECTION:'sync',BROADCAST_CONNECTION:'null',BCRYPT_ROUNDS:'4',LARAVEL_STORAGE_PATH:r.root+'/phpunit-storage',VIEW_COMPILED_PATH:r.root+'/phpunit-storage/framework/views'});
 for(const path of ['framework/views','framework/cache/data','framework/sessions','logs','app/private','app/public'])mkdirSync(env.LARAVEL_STORAGE_PATH+'/'+path,{recursive:true});mkdirSync(r.root+'/phpunit-cache',{recursive:true});for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])env[key]=relative(r.repo,r.root+'/phpunit-cache/'+key+'.php');
 args=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd','vendor/bin/phpunit','tests','qa-audit/tests','--no-progress','--do-not-cache-result','--log-junit',join(evidence,label+'.xml')];
}else if(mode==='chrome'){
 label='javascript-chrome-final';command='node';const copies=r.root+'/legacy-tests';mkdirSync(copies,{recursive:true});const files=[];
 for(const name of ['browser-p1-regressions.test.mjs','browser-partial-workflows.test.mjs']){let source=readFileSync(join(r.repo,'tests',name),'utf8');const pattern=/const root = (?:fileURLToPath\(new URL\('\.\.\/', import\.meta\.url\)\)|resolve\(import\.meta\.dirname, '\.\.'\));/;assert.ok(pattern.test(source));source=source.replace(pattern,'const root = '+JSON.stringify(r.repo)+';');assert.ok(source.includes("'-t', 'public'"));source=source.replace("'-t', 'public'",'process.env.F1_BROWSER_ROUTER');const file=join(copies,name);writeFileSync(file,source);files.push(file);}
 Object.assign(env,{CLINICOS_BROWSER_TEST:'1',CLINICOS_BROWSER_EVIDENCE:join(evidence,'legacy-chrome'),F1_BROWSER_ROUTER:join(r.repo,'qa-audit/product-completeness/f1-implementation/legacy-browser-router.php'),F1_BROWSER_PUBLIC:r.root+'/legacy-public'});delete env.APP_BASE_PATH;
 for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])env[key]=join(r.root,'legacy-'+key+'.php').replace(/^C:/,'');
 files.push(...['notification-realtime.test.mjs','partial-workflows.test.mjs','patient-duplicates.test.mjs','realtime-access.test.mjs'].map(name=>join(r.repo,'tests',name)));args=['--test','--test-reporter=tap','--test-concurrency=1',...files];
}else throw new Error('Unknown regression mode');
const result=spawnSync(command,args,{env,cwd:r.repo,encoding:'utf8',maxBuffer:16*1024*1024,windowsHide:true});writeFileSync(join(evidence,label+(mode==='chrome'?'.tap':'.txt')),(result.stdout||'')+(result.stderr||''));writeFileSync(join(evidence,label+'-exit.json'),JSON.stringify({exit:result.status,storage_isolated:true,mode,assertions_unchanged:true,at:new Date().toISOString()},null,2));console.log(JSON.stringify({mode,exit:result.status,tail:(result.stdout||'').slice(-1700)}));
