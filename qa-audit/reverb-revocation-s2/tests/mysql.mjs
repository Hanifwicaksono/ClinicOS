import {readFileSync,writeFileSync,mkdirSync,openSync} from 'node:fs';
import {join} from 'node:path';
import {spawn,execFileSync} from 'node:child_process';
import net from 'node:net';
const evidence=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(evidence,'runtime.json'))),root=r.root+'/mysql',mysqlBase='C:/laragon/bin/mysql/mysql-8.0.30-winx64';
mkdirSync(root+'/data',{recursive:true});
await new Promise((yes,no)=>{const s=net.createServer();s.once('error',no);s.listen(13318,'127.0.0.1',()=>s.close(yes));});
execFileSync(mysqlBase+'/bin/mysqld.exe',['--no-defaults','--initialize-insecure','--basedir='+mysqlBase,'--datadir='+root+'/data'],{windowsHide:true,stdio:'pipe'});
const child=spawn(mysqlBase+'/bin/mysqld.exe',['--no-defaults','--basedir='+mysqlBase,'--datadir='+root+'/data','--port=13318','--bind-address=127.0.0.1','--mysqlx=0','--innodb-buffer-pool-size=64M','--log-error='+root+'/error.log'],{windowsHide:true,detached:true,stdio:['ignore',openSync(root+'/stdout.log','a'),openSync(root+'/stderr.log','a')]});child.unref();
const cli=mysqlBase+'/bin/mysql.exe',args=['--no-defaults','--host=127.0.0.1','--port=13318','--user=root','--batch','--skip-column-names'];
for(let i=0;i<100;i++){try{execFileSync(cli,args,{input:'SELECT 1;',stdio:'pipe'});break;}catch{await new Promise(r=>setTimeout(r,100));}}
const database='clinicos_s2_'+Date.now();execFileSync(cli,args,{input:`CREATE DATABASE ${database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`,stdio:'pipe'});
const env=JSON.parse(readFileSync(r.root+'/env.json'));Object.assign(env,{DB_CONNECTION:'mysql',DB_HOST:'127.0.0.1',DB_PORT:'13318',DB_DATABASE:database,DB_USERNAME:'root',DB_PASSWORD:'',DB_URL:'',APP_ENV:'testing',LARAVEL_STORAGE_PATH:r.root+'/storage-mysql',BROADCAST_CONNECTION:'null',QUEUE_CONNECTION:'sync',CACHE_STORE:'array',SESSION_DRIVER:'array'});
mkdirSync(r.root+'/storage-mysql/framework/views',{recursive:true});mkdirSync(r.root+'/storage-mysql/framework/cache/data',{recursive:true});mkdirSync(r.root+'/storage-mysql/framework/sessions',{recursive:true});mkdirSync(r.root+'/storage-mysql/logs',{recursive:true});
writeFileSync(r.root+'/mysql-env.json',JSON.stringify(env));writeFileSync(join(evidence,'mysql-runtime.json'),JSON.stringify({pid:child.pid,port:13318,database,datadir:root+'/data',isolated:true,version:'8.0.30'},null,2));console.log(JSON.stringify({isolated:true,pid:child.pid,port:13318,database}));
