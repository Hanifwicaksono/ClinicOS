import {readFileSync,writeFileSync} from 'node:fs';
import {join} from 'node:path';
import assert from 'node:assert/strict';
import * as core from '../scripts/common.mjs';
export * from '../scripts/common.mjs';
export {assert};
export const fixture=JSON.parse(readFileSync(join(core.evidence,'fixture.json')));
export const phase=process.env.CCL_PHASE||'after';
export const setClock=value=>writeFileSync(join(core.runtime.root,'clock.txt'),value);
export async function run(id,role,scenario,body){if(process.env.CCL_ONLY&&!process.env.CCL_ONLY.split(',').includes(id))return;return core.check(phase+'-'+id,id.slice(0,6),role,scenario,['Prasyarat dan aksi UI pada runner; DB hanya verifikasi','Bandingkan expected behavior dengan respons dan data tersimpan'],body);}
export async function submit(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
export async function wire(page,button){await Promise.all([page.waitForResponse(r=>r.url().endsWith('/livewire/update')&&r.request().method()==='POST'),button.click()]);}
export async function openVisit(page,id){await page.goto(core.base+`/doctor/visits/${id}`);await page.waitForFunction(()=>Boolean(document.querySelector('[data-rme-form]')?.rmeAutosave));}
export async function saved(page){await page.waitForFunction(()=>document.querySelector('[data-rme-form]')?.rmeAutosave?.unsaved===false,{},{timeout:12000});}
