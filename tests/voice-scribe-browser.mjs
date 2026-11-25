import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';

export async function auditVoiceScribe({ sql, call, evaluate, go, waitFor, directory, root, env, php, phpBinary, visit }) {
    const worker = spawn(phpBinary, [...php, 'artisan', 'queue:work', 'voice', '--queue=voice', '--sleep=1', '--tries=3', '--timeout=1200', '--no-interaction'],
        { cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    worker.stderr.on('data', () => {});
    const button = action => '[data-voice-action="' + action + '"]';
    const click = async selector => { await evaluate('document.querySelector(' + JSON.stringify(selector) + ').click()'); };
    const check = async selector => {
        await evaluate('(() => { const input=document.querySelector(' + JSON.stringify(selector) + '); input.checked=true; input.dispatchEvent(new Event("input",{bubbles:true})); })()');
    };
    try {
        const patient = sql.prepare('SELECT patient_id FROM visits WHERE id=?').get(visit.id);
        sql.prepare('UPDATE patients SET name=? WHERE id=?').run('a'.repeat(240), patient.patient_id);
        await go('/doctor/visits/' + visit.id, 'Boolean(document.querySelector("[data-voice-panel]") && document.querySelector("[data-rme-form]")?.rmeAutosave)');
        await waitFor('document.querySelector("[data-voice-status]").textContent === "Menunggu persetujuan"');
        await click('[data-rme-voice-disclosure] > summary');
        await click('.voice-patient-context > summary');
        assert.equal(await evaluate('document.querySelector(".voice-patient-context").open'), true);
        assert.ok(await evaluate('document.querySelector(".voice-patient-context").textContent.includes("a".repeat(240))'));
        await click('.voice-patient-context > summary');
        assert.equal(await evaluate('document.querySelector(' + JSON.stringify(button('start')) + ').disabled'), true);
        assert.equal(sql.prepare('SELECT count(*) AS count FROM voice_sessions').get().count, 0);
        assert.equal(await evaluate('getComputedStyle(document.querySelector(\'[data-voice-action="pause"]\')).display'), 'none');
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
            await evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
            await evaluate('new Promise(resolve => setTimeout(resolve, 350))');
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth+1'), false, 'Voice page overflow at ' + width);
            assert.equal(await evaluate('(() => { const panel=document.querySelector("[data-voice-panel]"); return panel.scrollWidth > panel.clientWidth+1; })()'), false, 'Long patient name overflows Voice panel at ' + width);
            const shot = await call('Page.captureScreenshot', { format: 'png' });
            writeFileSync(join(directory, 'voice-consent-' + width + '.png'), Buffer.from(shot.data, 'base64'));
        }
        await check('[data-voice-patient]');
        await check('[data-voice-agreement]');
        await click(button('start'));
        await waitFor('document.querySelector("[data-voice-microphone]").textContent.includes("Mikrofon aktif")');
        await evaluate('new Promise(resolve => setTimeout(resolve, 1500))');
        await click(button('pause'));
        await waitFor('document.querySelector("[data-voice-status]").textContent === "Dijeda" && !document.querySelector(' + JSON.stringify(button('resume')) + ').disabled');
        assert.equal(await evaluate('document.querySelector("[data-voice-microphone]").textContent'), 'Mikrofon tidak aktif.');
        assert.equal(sql.prepare('SELECT consent_status FROM voice_sessions').get().consent_status, 'granted');
        await click(button('resume'));
        await waitFor('document.querySelector("[data-voice-microphone]").textContent.includes("Mikrofon aktif")');
        await evaluate('new Promise(resolve => setTimeout(resolve, 1200))');
        await click(button('stop'));
        await waitFor('document.querySelector("[data-voice-status]").textContent === "Perlu review dokter"');
        assert.equal(sql.prepare('SELECT count(*) AS count FROM voice_segments').get().count, 2);
        assert.equal(sql.prepare('SELECT count(*) AS count FROM ai_clinical_drafts').get().count, 1);
        assert.equal(await evaluate('document.querySelectorAll("[data-voice-panel] textarea").length'), 0);
        assert.equal(await evaluate('document.querySelectorAll("textarea[name=subjective]").length'), 1);
        assert.ok(await evaluate('document.querySelector("[data-voice-value=subjective]").textContent.includes("Demam")'));
        assert.equal(await evaluate('document.querySelector("[data-voice-select=assessment]").disabled'), true);
        assert.equal(await evaluate('document.querySelector("[data-voice-summary]").open'), false);
        for (const width of [375, 768, 1024, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 1100, deviceScaleFactor: 1, mobile: false });
            await evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
            await evaluate('new Promise(resolve => setTimeout(resolve, 350))');
            assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth+1'), false, 'Voice review overflow at ' + width);
            const shot = await call('Page.captureScreenshot', { format: 'png' });
            writeFileSync(join(directory, 'voice-review-' + width + '.png'), Buffer.from(shot.data, 'base64'));
        }
        const before = sql.prepare('SELECT objective,assessment,plan,lock_version FROM medical_records WHERE id=?').get(visit.medical_record_id);
        await click('[data-voice-tab=transcript]');
        assert.ok(await evaluate('document.querySelector("[data-voice-transcript]").textContent.includes("SIMULASI")'));
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".voice-result")).whiteSpace'), 'pre-wrap');
        await evaluate('document.querySelector("[data-voice-tab=transcript]").focus()');
        await evaluate('document.activeElement.dispatchEvent(new KeyboardEvent("keydown", {key:"ArrowRight", bubbles:true}))');
        assert.equal(await evaluate('document.activeElement.dataset.voiceTab'), 'diagnoses');
        await check('[data-voice-select=subjective]');
        assert.ok(await evaluate('document.querySelector("[data-voice-selection-status]").textContent.includes("1 kolom" )'));
        await check('[data-voice-confirm]');
        await evaluate('window.confirm = () => true');
        await click(button('approve'));
        try {
            await waitFor('document.querySelector("[data-voice-status]")?.textContent === "Sudah diterapkan ke draft RME"');
        } catch (error) {
            throw new Error(error.message + ' Voice: ' + await evaluate('document.querySelector("[data-voice-error]")?.textContent'));
        }
        const after = sql.prepare('SELECT subjective,objective,assessment,plan,status FROM medical_records WHERE id=?').get(visit.medical_record_id);
        assert.equal(after.subjective, 'Demam tiga hari.');
        assert.equal(after.objective, before.objective);
        assert.equal(after.assessment, before.assessment);
        assert.equal(after.plan, before.plan);
        assert.equal(after.status, 'DRAFT');
        assert.equal(await evaluate('location.hash'), '#rme-general-heading');
        await waitFor('Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave)');
        await click('[data-rme-tab=vital]');
        await click(button('manual'));
        await waitFor('document.activeElement.id === "chief_complaint" && !document.querySelector("[data-rme-panel=soap]").hidden');
        assert.equal(await evaluate('document.querySelector("textarea[name=subjective]").value'), 'Demam tiga hari.');
        const version = await evaluate('Number(document.querySelector("[name=record_version]").value)');
        await evaluate('(() => { const field=document.querySelector("textarea[name=subjective]"); field.value="Koreksi dokter pada form SOAP existing."; field.dispatchEvent(new Event("input",{bubbles:true})); })()');
        await waitFor('Number(document.querySelector("[name=record_version]").value) > ' + version + ' && !document.querySelector("[data-rme-form]").rmeAutosave.unsaved');
        assert.equal(sql.prepare('SELECT subjective FROM medical_records WHERE id=?').get(visit.medical_record_id).subjective, 'Koreksi dokter pada form SOAP existing.');
        assert.equal(sql.prepare('SELECT count(*) AS count FROM ai_review_logs WHERE action=?').get('approved').count, 1);
        await evaluate('window.confirm = () => true');
        await click('[data-rme-voice-disclosure] > summary');
        await click('[data-voice-options] > summary');
        await click(button('withdraw'));
        await waitFor('document.querySelector(' + JSON.stringify(button('withdraw')) + ').hidden');
        assert.equal(sql.prepare('SELECT consent_status FROM voice_sessions').get().consent_status, 'withdrawn');
        assert.equal(sql.prepare('SELECT count(*) AS count FROM voice_segments WHERE audio_path IS NOT NULL').get().count, 0);
        assert.equal(sql.prepare('SELECT count(*) AS count FROM voice_transcripts WHERE transcript_text IS NOT NULL').get().count, 0);
        assert.equal(sql.prepare('SELECT count(*) AS count FROM ai_review_logs WHERE changes IS NOT NULL').get().count, 0);
        assert.equal(sql.prepare('SELECT subjective FROM medical_records WHERE id=?').get(visit.medical_record_id).subjective, 'Koreksi dokter pada form SOAP existing.');
    } finally {
        worker.kill();
    }
}
