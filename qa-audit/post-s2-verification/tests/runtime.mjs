import{readFileSync,writeFileSync,openSync}from'node:fs';import{join}from'node:path';import{spawn,execFileSync}from'node:child_process';
const e=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(e,'runtime.json'))),env=JSON.parse(readFileSync(r.root+'/env.json')),php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];
if(process.argv.includes('--prepare')){
 writeFileSync(join(e,'migrate.txt'),execFileSync('php',[...php,r.root+'/artisan.php','migrate','--force','--no-interaction'],{env,cwd:r.repo,encoding:'utf8'}));
 writeFileSync(join(e,'import.json'),execFileSync('php',[...php,join(import.meta.dirname,'import-fixture.php')],{env,cwd:r.repo,encoding:'utf8'}));
 writeFileSync(join(e,'fixture.json'),execFileSync('php',[...php,join(import.meta.dirname,'control.php'),'setup'],{env,cwd:r.repo,encoding:'utf8'}));
 writeFileSync(join(e,'build.txt'),execFileSync('node',['node_modules/vite/bin/vite.js','build','--outDir',r.root+'/public/build'],{env,cwd:r.repo,encoding:'utf8'}));
}
const p={master:process.pid};for(const[name,args]of Object.entries({http:['-S','127.0.0.1:18961',r.root+'/router.php'],reverb:[r.root+'/artisan.php','reverb:start','--host=127.0.0.1','--port=18962','--no-interaction'],worker:[r.root+'/artisan.php','queue:work','database','--sleep=1','--tries=4','--no-interaction']})){
 const child=spawn('php',[...php,...args],{env,cwd:r.repo,windowsHide:true,stdio:['ignore',openSync(r.root+'/'+name+'.stdout.log','a'),openSync(r.root+'/'+name+'.stderr.log','a')]});p[name]=child.pid;
}
writeFileSync(join(e,'processes.json'),JSON.stringify(p,null,2));console.log(JSON.stringify({isolated:true,mysql:env.DB_DATABASE,processes:p}));setInterval(()=>{},1000);
