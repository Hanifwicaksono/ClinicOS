import {readFileSync,writeFileSync} from 'node:fs';
import {join,resolve} from 'node:path';
import {execFileSync} from 'node:child_process';
const repo=resolve(import.meta.dirname,'../../..');const evidence=join(repo,'qa-audit/deep-functional-ux/evidence');const runtime=JSON.parse(readFileSync(join(evidence,'runtime.json')));const file=join(runtime.root,'env.json');const env=JSON.parse(readFileSync(file));
Object.assign(env,{VITE_REVERB_APP_KEY:'qa-deep-isolated',VITE_REVERB_HOST:'127.0.0.1',VITE_REVERB_PORT:'18962',VITE_REVERB_SCHEME:'http'});
writeFileSync(file,JSON.stringify(env));
const output=execFileSync('node',['node_modules/vite/bin/vite.js','build','--outDir',join(runtime.root,'public/build')],{cwd:repo,env,encoding:'utf8',stdio:['ignore','pipe','pipe']});writeFileSync(join(evidence,'build-frontend-isolation.txt'),output);
writeFileSync(join(evidence,'frontend-isolation.json'),JSON.stringify({at:new Date().toISOString(),change:'Explicit disposable WebSocket host/key, no running Reverb instance. Initial bundle inherited Vite env, so initial ETA automatic-refresh result is inconclusive and must be retested.',php_broadcast:'null',socket_tested:false},null,2));
console.log('Disposable frontend WebSocket target isolated; application source unchanged.');
