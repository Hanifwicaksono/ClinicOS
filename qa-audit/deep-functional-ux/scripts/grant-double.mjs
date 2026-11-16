import assert from 'node:assert/strict';
import {writeFileSync} from 'node:fs';
import {join} from 'node:path';
import {base,client,check,sql,shot,finish,state,evidence} from './common.mjs';
const s=state();const c=await client('platform');const p=c.page;
try{
    await check('GRANT-DOUBLE','F30','Platform Admin','Double-click grant tidak membuat dua periode dari satu maksud pengguna',['Buka tenant QA Free','Pilih Individual satu bulan','Isi satu alasan','Double-click Berikan akses','Periksa jumlah periode baru'],async()=>{
        await p.goto(base+`/platform/tenants/${s.onboardingClinicId}`);const price=sql.prepare("SELECT sp.id FROM subscription_prices sp JOIN subscription_plans p ON p.id=sp.subscription_plan_id WHERE p.code='individual' AND duration_months=1").get();await p.locator('#subscription_price_id').selectOption(String(price.id));await p.locator('#reason').fill('Satu grant satu bulan, audit double click manusia');
        const before=sql.prepare('SELECT COUNT(*) n FROM subscription_periods WHERE clinic_id=?').get(s.onboardingClinicId).n;const button=p.getByRole('button',{name:'Berikan akses'});await button.scrollIntoViewIfNeeded();const box=await button.boundingBox();await p.mouse.click(box.x+box.width/2,box.y+box.height/2,{clickCount:2,delay:10});await p.waitForTimeout(1500);const after=sql.prepare('SELECT COUNT(*) n FROM subscription_periods WHERE clinic_id=?').get(s.onboardingClinicId).n;const result={clinic_id:s.onboardingClinicId,before,after,added:after-before};writeFileSync(join(evidence,'grant-double-click.json'),JSON.stringify(result,null,2));await shot(p,'GRANT-DOUBLE-result');assert.equal(after-before,1,'Double-click memberi lebih dari satu periode subscription');return result;
    });
}finally{await finish('14-grant-double-click');}
