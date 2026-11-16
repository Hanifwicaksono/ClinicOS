import {readFileSync,writeFileSync,openSync} from 'node:fs';
import {join} from 'node:path';
import {spawn,execFileSync} from 'node:child_process';
import net from 'node:net';
const evidence=join(import.meta.dirname,'../evidence'),runtime=JSON.parse(readFileSync(join(evidence,'runtime.json'))),env=JSON.parse(readFileSync(runtime.root+'/env.json'));
for(const port of [18961,18962])await new Promise((yes,no)=>{const server=net.createServer();server.once('error',no);server.listen(port,'127.0.0.1',()=>server.close(yes));});
let application=readFileSync(runtime.root+'/application.php','utf8');
if(!application.includes('/reverb-revocation-s2/tests/Hooks.php'))application=application.replace('return $app;',`require getenv('AUDIT_REPO').'/qa-audit/reverb-revocation-s2/tests/Hooks.php';\n$app->register(S2Tests\\Hooks::class);\nreturn $app;`);
writeFileSync(runtime.root+'/application.php',application);
const php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];
writeFileSync(join(evidence,'migrate.txt'),execFileSync('php',[...php,runtime.root+'/artisan.php','migrate','--no-interaction','--force'],{env,cwd:runtime.repo,encoding:'utf8'}));
writeFileSync(join(evidence,'fixture.json'),execFileSync('php',[...php,join(import.meta.dirname,'control.php'),'setup'],{env,cwd:runtime.repo,encoding:'utf8'}));
writeFileSync(join(evidence,'build.txt'),execFileSync('node',['node_modules/vite/bin/vite.js','build','--outDir',runtime.root+'/public/build'],{env,cwd:runtime.repo,encoding:'utf8'}));
const processes={master:process.pid};
for(const [name,args]of Object.entries({http:['-S','127.0.0.1:18961',runtime.root+'/router.php'],reverb:[runtime.root+'/artisan.php','reverb:start','--host=127.0.0.1','--port=18962','--no-interaction'],worker:[runtime.root+'/artisan.php','queue:work','database','--sleep=1','--tries=4','--no-interaction']})){
const child=spawn('php',[...php,...args],{cwd:runtime.repo,env,windowsHide:true,stdio:['ignore',openSync(runtime.root+'/'+name+'.stdout.log','a'),openSync(runtime.root+'/'+name+'.stderr.log','a')]});processes[name]=child.pid;
}
writeFileSync(join(evidence,'processes.json'),JSON.stringify(processes,null,2));console.log(JSON.stringify({isolated:true,processes}));setInterval(()=>{},1000);
