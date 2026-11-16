import {readFileSync,writeFileSync,mkdirSync}from'node:fs';import{join,relative}from'node:path';import{spawnSync}from'node:child_process';
const e=resolveEvidence(),r=JSON.parse(readFileSync(join(e,'runtime.json'))),env=JSON.parse(readFileSync(r.root+'/suite-env.json'));
function resolveEvidence(){return join(import.meta.dirname,'../evidence');}
const db=process.argv[2],label=process.argv[3]||db+'-full';if(db==='sqlite')Object.assign(env,{DB_CONNECTION:'sqlite',DB_DATABASE:':memory:',DB_URL:''});else if(db!=='mysql')throw Error('Database required');
const cache=r.root+'/suite-'+label;mkdirSync(cache,{recursive:true});for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])env[key]=relative(r.repo,cache+'/'+key+'.php');
env.LARAVEL_STORAGE_PATH=cache+'/storage';env.VIEW_COMPILED_PATH=cache+'/storage/framework/views';for(const path of ['framework/views','framework/cache/data','framework/sessions','logs','app/private','app/public'])mkdirSync(env.LARAVEL_STORAGE_PATH+'/'+path,{recursive:true});
const filters=process.argv.slice(4),args=filters.length?filters:['tests','qa-audit/tests'];
const out=spawnSync('php',['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd','vendor/bin/phpunit',...args,'--no-progress','--do-not-cache-result','--log-junit',join(e,label+'.xml')],{cwd:r.repo,env,encoding:'utf8',maxBuffer:24*1024*1024,windowsHide:true});
writeFileSync(join(e,label+'.txt'),out.stdout+out.stderr);writeFileSync(join(e,label+'-exit.json'),JSON.stringify({exit:out.status,db,storage_isolated:true,at:new Date().toISOString()},null,2));console.log(JSON.stringify({label,exit:out.status,tail:out.stdout.slice(-500)}));
