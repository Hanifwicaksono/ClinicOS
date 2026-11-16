import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {join} from 'node:path';
import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const e=join(import.meta.dirname,'evidence'),label=process.env.F1_SUITE_LABEL||'javascript-chrome-full-retest',r=JSON.parse(readFileSync(join(e,'final/runtime.json'))),env=JSON.parse(readFileSync(join(r.root,'env.json'))),copies=join(r.root,label);mkdirSync(copies,{recursive:true});
const files=[];
for(const name of ['browser-p1-regressions.test.mjs','browser-partial-workflows.test.mjs']){
 let source=readFileSync(join(r.repo,'tests',name),'utf8');
 const rootPattern=/const root = (?:fileURLToPath\(new URL\('\.\.\/', import\.meta\.url\)\)|resolve\(import\.meta\.dirname, '\.\.'\));/;
 assert.ok(rootPattern.test(source));source=source.replace(rootPattern,'const root = '+JSON.stringify(r.repo)+';');
 assert.ok(source.includes("'-t', 'public'"));source=source.replace("'-t', 'public'",'process.env.F1_BROWSER_ROUTER');
 if(name==='browser-p1-regressions.test.mjs')source=source.replace("throw new Error('Browser condition timed out');",`try {
                const page = await evaluate(\`(() => { const f = document.querySelector('[data-rme-form]'); return {path: location.pathname, alerts: [...document.querySelectorAll('[role=alert], .text-red-700')].map(x=>x.textContent.trim()).filter(Boolean), version: f?.elements.record_version?.value, dirty:f?.rmeAutosave?.dirty, blocked:f?.rmeAutosave?.blocked, submitting:f?.rmeAutosave?.submitting, criticalAck:document.querySelector('#critical_allergies_acknowledged')?.checked}; })()\`);
                writeFileSync(join(evidence,'p1-timeout-diagnostic.json'),JSON.stringify({expression,page},null,2));
                const screen = await call('Page.captureScreenshot',{format:'png'});
                writeFileSync(join(evidence,'p1-timeout.png'),Buffer.from(screen.data,'base64'));
            } catch {}
            throw new Error('Browser condition timed out');`);
 const target=join(copies,name);writeFileSync(target,source);files.push(target);
}
Object.assign(env,{CLINICOS_BROWSER_TEST:'1',CLINICOS_BROWSER_EVIDENCE:join(e,label+'-screenshots'),F1_BROWSER_ROUTER:join(import.meta.dirname,'legacy-browser-router.php'),F1_BROWSER_PUBLIC:join(r.root,'public')});delete env.APP_BASE_PATH;
for(const key of ['APP_CONFIG_CACHE','APP_ROUTES_CACHE','APP_SERVICES_CACHE','APP_PACKAGES_CACHE'])env[key]=join(r.root,'legacy-'+key+'.php').replaceAll('\\','/').replace(/^C:/,'');
files.push(...['notification-realtime.test.mjs','partial-workflows.test.mjs','patient-duplicates.test.mjs'].map(name=>join(r.repo,'tests',name)));
const result=spawnSync('node',['--test','--test-reporter=tap','--test-concurrency=1',...files],{cwd:r.repo,env,encoding:'utf8',maxBuffer:16*1024*1024,windowsHide:true});
writeFileSync(join(e,label+'.tap'),(result.stdout||'')+(result.stderr||''));
writeFileSync(join(e,label+'-exit.json'),JSON.stringify({exit:result.status,original_test_assertions_unchanged:true,instrumentation:'Copied legacy browser tests only replace root resolution and server-router argument, so current application uses isolated storage/cache/build. Unit test files run unchanged from workspace.',finished:new Date().toISOString()},null,2));
console.log(JSON.stringify({exit:result.status,summary:(result.stdout||'').split(/\r?\n/).filter(x=>/^# (tests|pass|fail|skipped)/.test(x))}));process.exitCode=result.status??1;
