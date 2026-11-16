import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import assert from 'node:assert/strict';
import * as core from '../scripts/common.mjs';
export * from '../scripts/common.mjs';
export {assert};
export const fixture=JSON.parse(readFileSync(join(core.evidence,'fixture.json')));
export const clock=()=>readFileSync(join(core.runtime.root,'clock.txt'),'utf8').trim();
export function setClock(value){assert.match(value,/^\d{4}-\d\d-\d\dT/);writeFileSync(join(core.runtime.root,'clock.txt'),value);}
export async function run(id,gap,role,action,state,body){
 const register=JSON.parse(readFileSync(join(core.evidence,'gap-register.json')));
 const parent=register.additional.find(x=>x.id===gap)||register.blocked.find(x=>x.id===gap);
 assert.ok(parent,'Unknown gap '+gap);
 const r=await core.check(id,parent.feature,role,action,[`Prasyarat: ${state}`,'Lakukan tindakan melalui UI nyata','Periksa tampilan dan database read-only'],body);
 Object.assign(r,{gap,action,state,tuple:[parent.feature,role,action,state],clock:clock()});
 writeFileSync(core.resultsFile,JSON.stringify(core.results,null,2));return r;
}
export async function wire(page,button){
 await Promise.all([page.waitForResponse(r=>r.url().includes('/livewire/update')&&r.request().method()==='POST'),button.click()]);
 await page.waitForTimeout(100);
}
export async function saved(page){
 await page.waitForFunction(()=>document.querySelector('[data-rme-form]')?.rmeAutosave&&!document.querySelector('[data-rme-form]').rmeAutosave.unsaved,{},{timeout:12000});
}
export async function openVisit(page,id){await page.goto(core.base+`/doctor/visits/${id}`);await page.waitForFunction(()=>Boolean(document.querySelector('[data-rme-form]')?.rmeAutosave));}
export async function formSubmit(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
export function supersede(oldId,newId,reason){const old=core.results.find(x=>x.id===oldId);assert.ok(old);old.superseded_by=newId;old.harness_note=reason;writeFileSync(core.resultsFile,JSON.stringify(core.results,null,2));}
