import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {join} from 'node:path';
import {spawnSync} from 'node:child_process';
const e=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(e,'runtime.json'))),env=JSON.parse(readFileSync(r.root+'/env.json'));env.VITE_REVERB_PORT='18965';env.VITE_REVERB_APP_KEY='isolated-legacy-offline';mkdirSync(r.root+'/legacy-public',{recursive:true});const result=spawnSync('node',['node_modules/vite/bin/vite.js','build','--outDir',r.root+'/legacy-public/build'],{env,cwd:r.repo,encoding:'utf8'});writeFileSync(join(e,'legacy-build.txt'),result.stdout+result.stderr);if(result.status)throw new Error('Legacy offline build failed');console.log('Isolated legacy build PASS');
