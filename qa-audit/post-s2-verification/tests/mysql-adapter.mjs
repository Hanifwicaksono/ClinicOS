import {readFileSync}from'node:fs';import{join}from'node:path';import{execFileSync}from'node:child_process';
const r=JSON.parse(readFileSync(join(import.meta.dirname,'../evidence/runtime.json'))),env=JSON.parse(readFileSync(r.root+'/env.json'));
export class DatabaseSync{
 constructor(name){if(name!==env.DB_DATABASE)throw Error('Dedicated MySQL database guard');}
 prepare(query){return Object.fromEntries(['get','all','run'].map(mode=>[mode,(...parameters)=>JSON.parse(execFileSync('php',[join(import.meta.dirname,'sql.php')],{env,input:JSON.stringify({query,mode,parameters}),encoding:'utf8'}))??undefined]));}
 close(){}
}
