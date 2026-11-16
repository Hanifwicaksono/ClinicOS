import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {spawnSync} from 'node:child_process';
const evidence=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(evidence,'runtime.json'))),env=JSON.parse(readFileSync(r.root+'/env.json'));
const result=spawnSync('node',['node_modules/vite/bin/vite.js','build','--outDir',r.root+'/public/build'],{env,cwd:r.repo,encoding:'utf8'});writeFileSync(join(evidence,'build-final.txt'),result.stdout+result.stderr);if(result.status)throw new Error('Private build failed');console.log('Private testing build PASS');
