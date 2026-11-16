import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,existsSync} from 'node:fs';
import {join,resolve} from 'node:path';
const root=resolve(import.meta.dirname,'..'),evidence=join(root,'evidence');
const files=['ARCHITECTURE_ANALYSIS.md','REVOCATION_REPRODUCTION.md','TECHNICAL_EXPERIMENTS.md','APPROACH_COMPARISON.md','IMPLEMENTATION_PROPOSAL.md','SPIKE_FINAL_REPORT.md'];
const missing=[];let links=0;
for(const file of files){const content=readFileSync(join(root,file),'utf8');for(const [,target]of content.matchAll(/\]\(([^)]+)\)/g)){if(/^(https?:|#)/.test(target))continue;links++;if(!existsSync(resolve(root,target.split('#')[0]))){missing.push({file,target});}}}
const summary=JSON.parse(readFileSync(join(evidence,'summary.json')));const shutdown=JSON.parse(readFileSync(join(evidence,'shutdown.json'),'utf8').replace(/^\uFEFF/,''));
assert.deepEqual(missing,[]);assert.equal(summary.latest_browser.PASS,36);assert.equal(summary.latest_browser.FAIL,0);assert.equal(summary.backend.PASS,15);assert.equal(summary.backend.FAIL,0);assert.deepEqual(summary.source_integrity.changed,[]);assert.equal(summary.queue.pending,0);assert.equal(summary.queue.failed,0);assert.deepEqual(shutdown.loopback_ports_still_listening,[]);
for(const file of ['wire.jsonl','results-notification-types.json']){const content=readFileSync(join(evidence,file),'utf8');assert.ok(!/"(auth|channel_data|password|access_token|subjective|assessment)"\s*:/.test(content),'Sensitive raw field persisted');}
const result={status:'PASS',reports:files.length,local_links_checked:links,missing_links:missing,latest_browser:summary.latest_browser,backend:summary.backend,source_integrity:summary.source_integrity,process_ports_closed:true,raw_sensitive_fields_persisted:false,at:new Date().toISOString()};
writeFileSync(join(evidence,'report-validation.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
