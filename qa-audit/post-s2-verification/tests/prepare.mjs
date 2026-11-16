import {readFileSync,writeFileSync,mkdirSync,mkdtempSync,cpSync,readdirSync} from 'node:fs';
import {join,resolve,relative} from 'node:path';
import {tmpdir} from 'node:os';
import {createHash,randomBytes} from 'node:crypto';
import {execFileSync} from 'node:child_process';
const repo=resolve(import.meta.dirname,'../../..'), evidence=resolve(import.meta.dirname,'../evidence');mkdirSync(evidence,{recursive:true});
const hashes={};function scan(dir){for(const entry of readdirSync(join(repo,dir),{withFileTypes:true})){const file=dir+'/'+entry.name;if(file.startsWith('qa-audit/post-s2-verification/'))continue;if(entry.isDirectory())scan(file);else hashes[file]=createHash('sha256').update(readFileSync(join(repo,file))).digest('hex');}}
for(const dir of ['app','bootstrap','config','database','resources','routes','tests','qa-audit','vendor/laravel/reverb','vendor/spatie/laravel-permission'])scan(dir);
for(const file of ['.env','composer.json','composer.lock','package.json','package-lock.json'])hashes[file]=createHash('sha256').update(readFileSync(join(repo,file))).digest('hex');
writeFileSync(join(evidence,'source-before.json'),JSON.stringify(hashes,null,2));writeFileSync(join(evidence,'git-before.txt'),execFileSync('git',['status','--short'],{cwd:repo}));
const prior=JSON.parse(readFileSync(join(repo,'qa-audit/reverb-revocation-s2/evidence/runtime.json'))), source=JSON.parse(readFileSync(join(repo,'qa-audit/product-completeness/f1-implementation/evidence/final/runtime.json')));
const root=mkdtempSync(join(tmpdir(),'clinicos-post-s2-')).replaceAll('\\','/');cpSync(source.root,root,{recursive:true});
const env=JSON.parse(readFileSync(prior.root+'/env.json'));for(const[k,v]of Object.entries(env))if(typeof v==='string')env[k]=v.replaceAll(prior.root,root);
Object.assign(env,{AUDIT_ROOT:root,AUDIT_REPO:repo,S2_EVIDENCE:evidence,S2_RUN_ID:'post-s2-mysql',APP_KEY:'base64:'+randomBytes(32).toString('base64'),DB_CONNECTION:'mysql',DB_HOST:'127.0.0.1',DB_PORT:'13318',DB_DATABASE:'clinicos_post_s2_runtime_'+Date.now(),DB_USERNAME:'root',DB_PASSWORD:'',DB_URL:'',APP_URL:'http://127.0.0.1:18961'});
writeFileSync(root+'/env.json',JSON.stringify(env));const suite={...env,APP_ENV:'testing',APP_URL:'http://localhost',DB_DATABASE:env.DB_DATABASE.replace('runtime','suite'),QUEUE_CONNECTION:'sync',BROADCAST_CONNECTION:'null',SESSION_DRIVER:'array',CACHE_STORE:'array',BCRYPT_ROUNDS:'4',LARAVEL_STORAGE_PATH:root+'/suite-storage',VIEW_COMPILED_PATH:root+'/suite-storage/framework/views'};
for(const p of ['framework/views','framework/cache/data','framework/sessions','logs','app/private','app/public'])mkdirSync(suite.LARAVEL_STORAGE_PATH+'/'+p,{recursive:true});mkdirSync(root+'/suite-cache',{recursive:true});
for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])suite[key]=relative(repo,root+'/suite-cache/'+key+'.php');
writeFileSync(root+'/suite-env.json',JSON.stringify(suite));
writeFileSync(join(evidence,'runtime.json'),JSON.stringify({root,repo,base:env.APP_URL,database:env.DB_DATABASE,sqlite:root+'/audit.sqlite',suite_database:suite.DB_DATABASE,playwright:prior.playwright,started:new Date().toISOString(),provenance:'Fresh pristine synthetic F1 clone, dedicated MySQL runtime and separate suite database'},null,2));
let application=readFileSync(root+'/application.php','utf8');application=application.replace('return $app;',`require getenv('AUDIT_REPO').'/qa-audit/reverb-revocation-s2/tests/Hooks.php';\n$app->register(S2Tests\\Hooks::class);\nreturn $app;`);writeFileSync(root+'/application.php',application);
for(const name of ['browser.mjs','browser-extended.mjs','control.php','regression.mjs','legacy-build.mjs','f1-regression.mjs']){let text=readFileSync(join(repo,'qa-audit/reverb-revocation-s2/tests',name),'utf8');if(name==='control.php')text=text.replace("DB::statement('PRAGMA journal_mode=WAL');",'');if(name==='browser.mjs')text=text.replace("from 'node:sqlite'","from './mysql-adapter.mjs'");writeFileSync(join(import.meta.dirname,name),text);}
console.log(JSON.stringify({root,isolated:true}));
