import {readFileSync,writeFileSync,mkdirSync,mkdtempSync,cpSync,readdirSync,statSync} from 'node:fs';
import {join,resolve} from 'node:path';
import {tmpdir} from 'node:os';
import {randomBytes,createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';
const repo=resolve(import.meta.dirname,'../../..'),evidence=join(import.meta.dirname,'../evidence');mkdirSync(evidence,{recursive:true});
const hashes={};function scan(dir){for(const name of readdirSync(join(repo,dir))){const p=join(dir,name).replaceAll('\\','/');if(p.includes('qa-audit/reverb-revocation-s2'))continue;if(statSync(join(repo,p)).isDirectory())scan(p);else hashes[p]=createHash('sha256').update(readFileSync(join(repo,p))).digest('hex');}}
for(const p of ['app','bootstrap','config','database','resources','routes','tests','qa-audit/reverb-revocation-spike','vendor/laravel/reverb','vendor/spatie/laravel-permission'])scan(p);
for(const p of ['.env','composer.json','composer.lock','package.json','package-lock.json'])hashes[p]=createHash('sha256').update(readFileSync(join(repo,p))).digest('hex');
writeFileSync(join(evidence,'source-before.json'),JSON.stringify(hashes,null,2));writeFileSync(join(evidence,'git-before.txt'),execFileSync('git',['status','--short'],{cwd:repo}));
const old=JSON.parse(readFileSync(join(repo,'qa-audit/product-completeness/f1-implementation/evidence/final/runtime.json')));
const root=mkdtempSync(join(tmpdir(),'clinicos-deep-s2-')).replaceAll('\\','/');cpSync(old.root,root,{recursive:true});
const env=JSON.parse(readFileSync(root+'/env.json'));for(const [k,v]of Object.entries(env))if(typeof v==='string')env[k]=v.replaceAll(old.root,root);
Object.assign(env,{AUDIT_ROOT:root,AUDIT_REPO:repo,S2_EVIDENCE:evidence,APP_KEY:'base64:'+randomBytes(32).toString('base64'),DB_CONNECTION:'sqlite',DB_DATABASE:root+'/audit.sqlite',DB_URL:'',DB_USERNAME:'',DB_PASSWORD:'',QUEUE_CONNECTION:'database',BROADCAST_CONNECTION:'reverb',MAIL_MAILER:'array',REVERB_APP_ID:'qa-deep',REVERB_APP_KEY:'qa-deep-isolated',REVERB_APP_SECRET:'qa-deep-disposable',REVERB_HOST:'127.0.0.1',REVERB_PORT:'18962',REVERB_SCHEME:'http',REVERB_SERVER_HOST:'127.0.0.1',REVERB_SERVER_PORT:'18962',REVERB_SCALING_ENABLED:'false',DOKU_CLIENT_ID:'',DOKU_SECRET_KEY:'',GOOGLE_CLIENT_ID:'',GOOGLE_CLIENT_SECRET:''});
writeFileSync(root+'/env.json',JSON.stringify(env));writeFileSync(join(evidence,'runtime.json'),JSON.stringify({...old,root,repo,database:env.DB_DATABASE,base:'http://127.0.0.1:18961',started:new Date().toISOString(),provenance:'New disposable clone; no user DB connection'},null,2));console.log(JSON.stringify({isolated:true,root}));
