import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,readdirSync,existsSync} from 'node:fs';
import {join,resolve,dirname,relative} from 'node:path';
import {execFileSync} from 'node:child_process';
import {features} from './catalog.mjs';
import {findings} from './findings.mjs';
const repo=resolve(import.meta.dirname,'../../..'),out=join(repo,'qa-audit/deep-functional-ux'),evidence=join(out,'evidence');
const reports=['FEATURE_INVENTORY.md','ROLE_ACTION_MATRIX.md','SCENARIO_TEST_MATRIX.md','FUNCTIONAL_BUG_REPORT.md','MISSING_FEATURES_REPORT.md','UX_PROBLEMS_REPORT.md','EXPLORATORY_FINDINGS.md','CROSS_FEATURE_REPORT.md','COVERAGE_GAPS.md','FINAL_FUNCTIONAL_UX_REPORT.md'];
const scenarios=JSON.parse(readFileSync(join(evidence,'scenarios.json'))),latest=scenarios.filter(r=>!r.superseded_by),gaps=JSON.parse(readFileSync(join(evidence,'planned-gaps.json'))),pages=JSON.parse(readFileSync(join(evidence,'pages.json')));
assert.equal(new Set(scenarios.map(r=>r.id)).size,scenarios.length,'Scenario IDs unique');
for(const r of [...scenarios,...gaps]){assert.ok(['PASS','FAIL','BLOCKED','NOT TESTED'].includes(r.status));assert.ok(r.observed||r.reason||r.steps,'Evidence or reason required');if(r.superseded_by)assert.ok(scenarios.some(s=>s.id===r.superseded_by));}
for(const f of findings){assert.ok(f.pre&&f.actual&&f.expected&&f.impact&&f.fix&&f.severity&&f.roles);for(const file of f.evidence)assert.ok(existsSync(join(evidence,file)),f.id+' evidence missing '+file);}
for(const r of latest.filter(x=>x.status==='FAIL'))assert.ok(findings.some(f=>f.scenarios.includes(r.id)),'Unclassified active failure: '+r.id);
assert.equal(features.length,36);
for(const [id]of features){assert.ok(readFileSync(join(out,'FEATURE_INVENTORY.md'),'utf8').includes('### '+id+' — '));assert.ok(gaps.some(g=>g.feature===id));}
for(const p of pages)assert.deepEqual(p.responsive.map(x=>x.width),[375,768,1024,1440]);
const links=[];for(const report of reports){const file=join(out,report),text=readFileSync(file,'utf8');assert.ok(text.length>300);for(const match of text.matchAll(/\[[^\]]+\]\(([^)]+)\)/g)){const target=match[1].split('#')[0];if(target.startsWith('http'))continue;const path=resolve(dirname(file),target);assert.ok(existsSync(path),`${report} link missing ${target}`);links.push(target);}}
const sensitive=[];for(const file of readdirSync(evidence).filter(f=>/\.(json|jsonl|txt|xml)$/.test(f))){const text=readFileSync(join(evidence,file),'utf8');if(/(?:[?&](?:token|signature)=)[A-Za-z0-9%+/_=-]{20,}/i.test(text))sensitive.push(file);}
assert.deepEqual(sensitive,[],'Secret-bearing URLs must not be published');
const baseline=readFileSync(join(evidence,'baseline-git-status.txt'),'utf8').trim().split(/\r?\n/).map(l=>l.trim()).sort();const status=execFileSync('git',['status','--short'],{cwd:repo,encoding:'utf8'});const final=status.trim().split(/\r?\n/).map(l=>l.trim()).sort();assert.deepEqual(final,baseline,'Existing Git status paths must be preserved');
writeFileSync(join(evidence,'final-git-status.txt'),status);
const packages=JSON.parse(readFileSync(join(repo,'composer.lock'),'utf8')).packages;
const runtime=JSON.parse(readFileSync(join(evidence,'runtime.json')));const playwright=JSON.parse(readFileSync(join(dirname(runtime.playwright),'package.json'),'utf8'));
const versions={node:process.version,php:execFileSync('php',['-r','echo PHP_VERSION;'],{encoding:'utf8'}),playwright:playwright.version,chrome:JSON.parse(readFileSync(join(evidence,'checkpoint.json'))).browser,laravel:packages.find(p=>p.name==='laravel/framework').version,livewire:packages.find(p=>p.name==='livewire/livewire').version,volt:packages.find(p=>p.name==='livewire/volt').version,vite:JSON.parse(readFileSync(join(repo,'node_modules/vite/package.json'))).version};writeFileSync(join(evidence,'versions.json'),JSON.stringify(versions,null,2));
const result={checked_at:new Date().toISOString(),reports:reports.length,local_links_checked:links.length,feature_groups:features.length,active_scenarios:latest.length,classified_active_failures:latest.filter(r=>r.status==='FAIL').length,findings:findings.length,gaps:gaps.length,responsive_snapshots:pages.length,secret_url_scan:'PASS (pattern check, not OCR)',git_status_paths_preserved:true,source_write_scope:'Only qa-audit/deep-functional-ux artefacts/scripts were written; no byte-level initial hash snapshot was captured.',versions};writeFileSync(join(evidence,'artifact-check.json'),JSON.stringify(result,null,2));
const checkpoint=JSON.parse(readFileSync(join(evidence,'checkpoint.json')));writeFileSync(join(evidence,'checkpoint.json'),JSON.stringify({...checkpoint,phase:'reports-verified-exhaustive-coverage-still-open',at:new Date().toISOString(),artefact_check:'PASS'},null,2));console.log(JSON.stringify(result));
