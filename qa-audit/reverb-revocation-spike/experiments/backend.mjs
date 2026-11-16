import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {execFileSync} from 'node:child_process';
const evidence=join(import.meta.dirname,'../evidence');const runtime=JSON.parse(readFileSync(join(evidence,'runtime.json')));const env=JSON.parse(readFileSync(runtime.root+'/env.json'));
const php=['-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd'];
writeFileSync(runtime.root+'/mode.txt','guard');execFileSync('php',[...php,join(import.meta.dirname,'control.php'),'restore'],{env});
const result=execFileSync('php',[...php,join(import.meta.dirname,'backend.php')],{env,encoding:'utf8'});writeFileSync(join(evidence,'backend-checks.json'),JSON.stringify(JSON.parse(result),null,2));console.log(result);
