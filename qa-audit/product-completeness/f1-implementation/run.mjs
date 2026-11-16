import {readFileSync,writeFileSync,mkdirSync,readdirSync} from 'node:fs';
import {join} from 'node:path';
import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const e=join(import.meta.dirname,'evidence'),r=JSON.parse(readFileSync(join(e,'runtime.json'))),env=JSON.parse(readFileSync(join(r.root,'env.json')));
const [mode,label='run',...args]=process.argv.slice(2);assert.ok(r.root.includes('clinicos-deep-f1-'));
const php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];
let command='php',parameters;
if(mode==='make-test')parameters=[...php,join(r.root,'artisan.php'),'make:test','--phpunit','F1ImplementationTest','--no-interaction'];
else if(mode==='build'){command='node';parameters=['node_modules/vite/bin/vite.js','build','--outDir',join(r.root,'public/build')];}
else if(mode==='js'){command='node';parameters=['--test','--test-concurrency=1',...(args.length?args:readdirSync(join(r.repo,'tests')).filter(x=>x.endsWith('.test.mjs')).map(x=>'tests/'+x))];}
else if(mode==='php'){
 Object.assign(env,{APP_ENV:'testing',APP_URL:'http://localhost',DB_DATABASE:':memory:',DB_URL:'',DB_CONNECTION:'sqlite',SESSION_DRIVER:'array',CACHE_STORE:'array',QUEUE_CONNECTION:'sync',BROADCAST_CONNECTION:'null',MAIL_MAILER:'array',BCRYPT_ROUNDS:'4',VIEW_COMPILED_PATH:join(r.root,'phpunit/views')});
 mkdirSync(env.VIEW_COMPILED_PATH,{recursive:true});
 for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])env[key]=join(r.root,'phpunit',key+'.php').replaceAll('\\','/').replace(/^C:/,'');
 parameters=[...php,'vendor/bin/phpunit','--no-progress','--do-not-cache-result','--log-junit',join(e,label+'.xml'),...args];
}else throw Error('Unknown mode');
const result=spawnSync(command,parameters,{cwd:r.repo,env,encoding:'utf8',maxBuffer:16*1024*1024,windowsHide:true});
const sanitize=text=>text.replace(/((?:csrf-token" content|data-csrf|name="_token" value)=")[^"]+/g,'$1[redacted]');
writeFileSync(join(e,label+'.txt'),sanitize((result.stdout||'')+(result.stderr||'')));
writeFileSync(join(e,label+'-exit.json'),JSON.stringify({mode,status:result.status,error:result.error?.message||null,command:mode==='php'?'phpunit':mode,args,finished:new Date().toISOString()},null,2));
console.log(JSON.stringify({mode,label,exit:result.status,log:label+'.txt',summary:(result.stdout||'').split(/\r?\n/).filter(x=>/^(OK|Tests:|FAILURES|ERRORS|# tests|# pass|# fail)/.test(x))}));process.exitCode=result.status??1;
