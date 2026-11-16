import {readFileSync, writeFileSync, readdirSync, existsSync} from 'node:fs';
import {join, relative} from 'node:path';
import {createHash} from 'node:crypto';
const repo=process.cwd(), dir=join(repo,'qa-audit/reverb-revocation-s2'), evidence=join(dir,'evidence');
const baseline=JSON.parse(readFileSync(join(evidence,'source-before.json')));
const hash=file=>createHash('sha256').update(readFileSync(join(repo,file))).digest('hex');
const changed=[], missing=[];
for(const [file,before] of Object.entries(baseline)) {
 if(!existsSync(join(repo,file)))missing.push(file);
 else if(hash(file)!==before)changed.push(file);
}
const files=[];
function walk(path){for(const entry of readdirSync(join(repo,path),{withFileTypes:true})){const file=path+'/'+entry.name;if(entry.isDirectory())walk(file);else files.push(file);}}
for(const path of ['app','bootstrap','config','database','resources','routes','tests'])walk(path);
const added=files.filter(file=>!(file in baseline)&&!file.startsWith('bootstrap/cache/'));
const protectedFiles=['.env','composer.json','composer.lock','package.json','package-lock.json',
 'resources/views/platform/tenants/show.blade.php','app/Services/PatientDuplicateService.php',
 'app/Http/Controllers/Receptionist/PatientController.php','resources/js/patient-duplicates.js',
 'resources/views/components/patient-duplicate-review.blade.php'];
const protectedPrefixes=['vendor/','qa-audit/reverb-revocation-spike/'];
const protectedFailures=[...missing,...changed].filter(f=>protectedFiles.includes(f)||protectedPrefixes.some(p=>f.startsWith(p)));
const originalTestChanges=changed.filter(f=>(f.startsWith('tests/')||f.startsWith('qa-audit/tests/'))&&f!=='tests/Feature/RealtimeRevocationTest.php');
const integrity={at:new Date().toISOString(),baseline_files:Object.keys(baseline).length,changed,added,missing,
 protected_files_unchanged:protectedFailures.length===0,protectedFailures,originalTestChanges,
 caveat:'Three initial generated scaffolds (user epoch migration, revocation table/model) and RealtimeRevocationTest existed at snapshot time; their complete S2 implementations are new work.'};
writeFileSync(join(evidence,'source-integrity.json'),JSON.stringify(integrity,null,2));
const browser=JSON.parse(readFileSync(join(evidence,'browser-verified-final.json')));
const wire=readFileSync(join(evidence,'wire.jsonl'),'utf8').trim().split(/\r?\n/).map(line=>JSON.parse(line)).filter(row=>row.run==='verified-final');
const races=browser.filter(row=>['S2-15','S2-16'].includes(row.id)).map(row=>{
 const o=row.observed,begin=Date.parse(row.started)/1000,ack=o.server_ack_at;
 return {id:row.id,status:row.status,commit:o.mutation.committed_at,ack,old_version:Number(o.mutation.epoch)-1,
  transport_closed:wire.filter(w=>w.type==='transport_closed'&&w.at>=o.mutation.committed_at&&w.at<=ack),
  deferred_releases:o.released,admitted_sends:o.old_send_times,pending_while_active:o.pending_while_active,
  post_ack_old_private_sends:o.private_sends_after_ack_to_old_sockets,
  new_event_started:o.after.action_started,new_queue_id:o.after.queue_id};
});
writeFileSync(join(evidence,'race-summary.json'),JSON.stringify(races,null,2));
const cases=browser.map(({id,title,status})=>({id,title,status}));
writeFileSync(join(evidence,'security-case-summary.json'),JSON.stringify(cases,null,2));
console.log(JSON.stringify({integrity,security:{pass:cases.filter(c=>c.status==='PASS').length,fail:cases.filter(c=>c.status==='FAIL').length},races},null,2));
