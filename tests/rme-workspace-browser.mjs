import assert from 'node:assert/strict';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';

export async function auditRmeWorkspace({ call, evaluate, go, waitFor, directory, visit, sql }) {
    await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeAutosave && document.querySelector("#diagnosis_name_0"))');
    await evaluate('Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 5000))])');
    const measurements = [];
    for (const width of [375, 768, 1024, 1366, 1440, 1920]) {
        await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        await evaluate('window.scrollTo(0, 0); new Promise(resolve => setTimeout(resolve, 350))');
        const measurement = await evaluate(`(() => {
            const top = selector => Math.round(document.querySelector(selector).getBoundingClientRect().top + scrollY);
            const rect = selector => Math.round(document.querySelector(selector).getBoundingClientRect().width);
            return { width: innerWidth, documentHeight: document.documentElement.scrollHeight,
                contentWidth: rect('#main-content'), formWidth: rect('[data-rme-form]'), contextWidth: rect('#patient-context'),
                chiefWidth: rect('#chief_complaint'),
                firstSoapTop: top('#chief_complaint'), finalizeTop: Math.round([...document.querySelectorAll('button')].find(button => button.textContent.includes('Tinjau &')).getBoundingClientRect().top + scrollY),
                overflow: document.documentElement.scrollWidth > innerWidth + 1 };
        })()`);
        assert.equal(measurement.overflow, false, `RME overflow at ${width}px`);
        measurements.push(measurement);
        const screenshot = await call('Page.captureScreenshot', { format: 'png' });
        writeFileSync(join(directory, `rme-workspace-${width}.png`), Buffer.from(screenshot.data, 'base64'));
    }
    writeFileSync(join(directory, 'rme-workspace-metrics.json'), JSON.stringify(measurements, null, 2));
    console.log(JSON.stringify(measurements));
    if (process.env.CLINICOS_RME_BROWSER_TEST === 'baseline') return;

    await waitFor('document.querySelector("[data-rme-form]").dataset.workspaceReady === "true"');
    const tab = name => `[data-rme-tab="${name}"]`;
    const click = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const fill = (selector, value) => evaluate(`(() => {
        const field = document.querySelector(${JSON.stringify(selector)});
        field.value = ${JSON.stringify(value)};
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    })()`);
    const flush = async () => {
        assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()'), true);
        await waitFor('!document.querySelector("[data-rme-form]").rmeAutosave.unsaved');
    };
    assert.ok(await evaluate('document.querySelector("[data-rme-autosave-status]").innerText.includes("Autosave aktif")'), 'Initial editor does not claim a new save acknowledgement');
    assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-allergy-state]")].map(node => node.dataset.allergyState)'), ['unconfirmed', 'unconfirmed']);
    await evaluate(`window.__rmeSaves = 0; const originalFetch = window.fetch; window.fetch = (...args) => {
        if (String(args[0]) === document.querySelector('[data-rme-form]').action && args[1]?.method === 'POST') window.__rmeSaves++;
        return originalFetch(...args);
    }`);
    const beforeTabs = await evaluate('JSON.stringify([...new FormData(document.querySelector("[data-rme-form]"))].filter(([key]) => key !== "photos[]"))');
    for (const name of ['vital', 'clinical', 'prescription', 'soap']) {
        await click(tab(name));
        assert.equal(await evaluate(`document.querySelector('[data-rme-panel="${name}"]').hidden`), false);
        assert.equal(await evaluate('document.querySelectorAll("[data-rme-panel]:not([hidden])").length'), 1);
    }
    assert.equal(await evaluate('JSON.stringify([...new FormData(document.querySelector("[data-rme-form]"))].filter(([key]) => key !== "photos[]"))'), beforeTabs, 'Tabs preserve every form field without starting a write');
    await call('Page.bringToFront');
    await call('Emulation.setFocusEmulationEnabled', { enabled: true });
    await evaluate('document.querySelector("[data-rme-tab=soap]").focus()');
    for (const [key, selected] of [['ArrowRight', 'vital'], ['End', 'prescription'], ['Home', 'soap']]) {
        // shortcut: exercise the DOM keyboard handler; physical keyboard/screen reader testing remains manual.
        await evaluate(`document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: ${JSON.stringify(key)}, bubbles: true }))`);
        assert.equal(await evaluate('document.activeElement.dataset.rmeTab'), selected);
    }
    await fill('#subjective', 'Keluhan simulasi oleh dokter.');
    await waitFor('document.querySelector("[data-rme-autosave-status]").innerText.includes("Perubahan belum tersimpan")');
    const longNote = 'Catatan sintetis untuk pemeriksaan panjang. '.repeat(150);
    await fill('#subjective', longNote);
    await click(tab('vital'));
    assert.equal(await evaluate('document.querySelector("#subjective").value.length'), longNote.length);
    await click(tab('soap'));
    assert.equal(await evaluate('getComputedStyle(document.querySelector("#subjective")).resize'), 'vertical');
    await fill('#subjective', 'Keluhan simulasi oleh dokter.');
    await click(tab('vital'));
    await fill('#vital_weight_kg', '62');
    await click(tab('clinical'));
    await click('[data-rme-add=diagnosis]');
    await waitFor('document.activeElement.id === "diagnosis_code_1"');
    await fill('#diagnosis_name_1', 'Diagnosis tambahan sintetis');
    await fill('#diagnosis_code_1', 'Z00.0');
    await evaluate('[...document.querySelectorAll("[data-rme-row=diagnosis]")].at(-1).querySelector("button").click()');
    await waitFor('!document.querySelector("#diagnosis_name_1")');
    await click('[data-rme-add=treatment]');
    await waitFor('document.activeElement.id === "treatment_name_1"');
    await fill('#treatment_name_1', 'Tindakan sintetis');
    await fill('#treatment_quantity_1', '2');
    await evaluate('[...document.querySelectorAll("[data-rme-row=treatment]")].at(-1).querySelector("button").click()');
    await waitFor('!document.querySelector("#treatment_name_1")');
    await flush();
    const savedRequests = await evaluate('window.__rmeSaves');
    const savedVersion = await evaluate('document.querySelector("[name=record_version]").value');
    await evaluate('window.__dedupMarker = true; document.querySelector("[data-rme-form]").requestSubmit(document.querySelector("[data-rme-form] button[formnovalidate]"))');
    await waitFor('!document.querySelector("[data-rme-form]").rmeAutosave.submitting');
    assert.equal(await evaluate('window.__rmeSaves'), savedRequests, 'Clean manual save does not repeat the acknowledged autosave');
    assert.equal(await evaluate('document.querySelector("[name=record_version]").value'), savedVersion);
    assert.equal(await evaluate('window.__dedupMarker'), true, 'Clean manual save keeps the current editor mounted');
    assert.ok(await evaluate('document.querySelector("[data-history-record-status]") !== null'), 'Fixture includes a visit history');
    await evaluate('document.querySelector("[data-history-record-status]").closest("details").open = true');
    assert.ok(await evaluate('document.querySelector("#patient-context").innerText.includes("Tindakan tercatat") && document.querySelector("#patient-context").innerText.includes("Resep kunjungan")'));
    assert.equal(sql.prepare('SELECT subjective FROM medical_records WHERE id=?').get(visit.medical_record_id).subjective, 'Keluhan simulasi oleh dokter.');
    assert.equal(Number(sql.prepare('SELECT weight_kg FROM vital_signs WHERE medical_record_id=?').get(visit.medical_record_id).weight_kg), 62);

    // A manual submit must reveal a server-invalid field even while its tab is hidden.
    await fill('#vital_systolic', '999');
    await click(tab('soap'));
    await evaluate('document.querySelector("[data-rme-form]").requestSubmit([...document.querySelectorAll("button")].find(button => button.textContent.trim() === "Simpan draft"))');
    await waitFor('document.querySelector("#vital_systolic").getAttribute("aria-invalid") === "true" && document.activeElement.id === "vital_systolic"');
    assert.equal(await evaluate('document.querySelector("[data-rme-panel=vital]").hidden'), false);
    assert.ok(await evaluate('document.querySelector("[data-rme-tab=vital]").innerText.includes("perlu diperiksa")'));
    await fill('#vital_systolic', '120');
    await flush();

    await call('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    await fill('#doctor_notes', 'Catatan dipertahankan ketika offline.');
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()'), false);
    await click(tab('prescription'));
    assert.equal(await evaluate('document.querySelector("#doctor_notes").value'), 'Catatan dipertahankan ketika offline.');
    await call('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    await evaluate('window.dispatchEvent(new Event("online"))');
    await waitFor('!document.querySelector("[data-rme-form]").rmeAutosave.unsaved');
    const originalVersion = await evaluate('document.querySelector("[name=record_version]").value');
    const competingWrite = await evaluate(`(async () => {
        const form = document.querySelector('[data-rme-form]');
        const data = new FormData(form); data.set('doctor_notes', 'Versi penyunting lain.');
        return (await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } })).status;
    })()`);
    assert.equal(competingWrite, 200);
    await fill('#doctor_notes', 'Perubahan editor lama tetap tersedia.');
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()'), false);
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.blocked'), true);
    assert.equal(await evaluate('document.querySelector("#doctor_notes").value'), 'Perubahan editor lama tetap tersedia.');
    assert.equal(await evaluate('document.querySelector("[name=record_version]").value'), originalVersion);
    assert.equal(sql.prepare('SELECT doctor_notes FROM medical_records WHERE id=?').get(visit.medical_record_id).doctor_notes, 'Versi penyunting lain.');

    // A critical allergy remains outside every collapsible/tabbed clinical panel.
    const patient = sql.prepare('SELECT patient_id,doctor_id FROM visits WHERE id=?').get(visit.id);
    sql.prepare("INSERT INTO patient_allergies(patient_id,substance,reaction,severity,is_active,created_at,updated_at) VALUES(?, 'Pemicu sintetis', 'Reaksi sintetis', 'CRITICAL', 1, datetime('now'), datetime('now'))").run(patient.patient_id);
    await go(`/doctor/visits/${visit.id}`, 'Boolean(document.querySelector("[data-rme-form]")?.rmeWorkspace)');
    await click(tab('prescription'));
    assert.equal(await evaluate('document.querySelector("[data-critical-allergy-alert]").closest("[data-rme-panel], details")'), null);
    assert.deepEqual(await evaluate('[...document.querySelectorAll("[data-allergy-state]")].map(node => node.dataset.allergyState)'), ['critical', 'critical']);
    for (const width of [375, 768, 1024, 1366, 1440, 1920]) {
        await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        await evaluate('window.scrollTo(0, 0); new Promise(resolve => setTimeout(resolve, 150))');
        assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth + 1'), false, `Critical allergy overflow at ${width}px`);
        const shot = await call('Page.captureScreenshot', { format: 'png' });
        writeFileSync(join(directory, `rme-critical-${width}.png`), Buffer.from(shot.data, 'base64'));
    }
    await fill('#medicine_name_0', 'Obat sintetis');
    assert.equal(await evaluate('document.querySelector("#medicine_dosage_0").value'), '', 'No automatic medicine dose');
    await click('[data-rme-review]');
    await waitFor('document.querySelector("[data-rme-form] [role=dialog]").getBoundingClientRect().height > 0');
    assert.ok(await evaluate('document.querySelector("[data-rme-review-prescription]").innerText.includes("Dosis belum diisi")'));
    assert.ok(await evaluate('document.querySelector("[data-rme-review-allergy]").innerText.includes("belum diberikan")'));
    for (const width of [375, 768, 1024, 1366, 1440, 1920]) {
        await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        await evaluate('new Promise(resolve => setTimeout(resolve, 200))');
        assert.equal(await evaluate('document.querySelector("[data-rme-form] [role=dialog]").scrollWidth > document.querySelector("[data-rme-form] [role=dialog]").clientWidth + 1'), false, `Finalization review overflow at ${width}px`);
        assert.equal(await evaluate('[...document.querySelectorAll("[data-rme-form] [role=dialog] button")].every(button => { const rect = button.getBoundingClientRect(); return rect.top >= 0 && rect.bottom <= innerHeight; })'), true, `Review actions remain visible at ${width}px`);
        const shot = await call('Page.captureScreenshot', { format: 'png' });
        writeFileSync(join(directory, `rme-review-${width}.png`), Buffer.from(shot.data, 'base64'));
    }
    await evaluate('document.querySelector("[data-rme-form] [role=dialog] button").click()');
    await waitFor('document.querySelector("[data-rme-form] [role=dialog]").getBoundingClientRect().height === 0');
    await fill('#medicine_dosage_0', '1 tablet');
    await fill('#medicine_frequency_0', '1 kali sehari');
    await fill('#medicine_quantity_0', '2');
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()'), false, 'Prescription without allergy acknowledgement is rejected');
    await click('#critical_allergies_acknowledged');
    await flush();
    await click('[data-rme-add=medicine]');
    await waitFor('document.activeElement.id === "medicine_name_1"');
    await fill('#medicine_name_1', 'Obat dihapus');
    await evaluate('[...document.querySelectorAll("[data-rme-row=medicine]")].at(-1).querySelector("button").click()');
    await flush();
    assert.equal(sql.prepare('SELECT COUNT(*) AS n FROM prescription_items WHERE prescription_id=(SELECT id FROM prescriptions WHERE medical_record_id=?)').get(visit.medical_record_id).n, 1);
    await click(tab('soap'));
    await fill('#assessment', 'Penilaian simulasi dokter.');
    await fill('#plan', 'Rencana simulasi dokter.');
    await flush();
    await evaluate(`(async () => {
        const canvas = document.createElement('canvas'); canvas.width = canvas.height = 2;
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        const transfer = new DataTransfer(); transfer.items.add(new File([blob], 'synthetic.png', { type: 'image/png' }));
        const input = document.querySelector('#photos'); input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    })()`);
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()'), true);
    await waitFor('document.querySelector("[data-rme-autosave-status]").innerText.includes("Foto belum disimpan")');
    await click(tab('vital'));
    assert.equal(await evaluate('new FormData(document.querySelector("[data-rme-form]")).get("photos[]").name'), 'synthetic.png');
    await evaluate('document.querySelector("[data-rme-form]").requestSubmit([...document.querySelectorAll("button")].find(button => button.textContent.trim() === "Simpan draft"))');
    await waitFor('Boolean(document.querySelector("[data-rme-form]")?.rmeWorkspace) && !document.querySelector("[data-rme-form]").rmeAutosave.hasPendingPhotos()');
    assert.ok(JSON.parse(sql.prepare('SELECT photos FROM medical_records WHERE id=?').get(visit.medical_record_id).photos).length > 0);
    await click(tab('prescription'));
    await click('#critical_allergies_acknowledged');
    await click(tab('soap'));
    await evaluate('document.querySelector("[data-rme-photo-details]").open = true');
    await click('[aria-label="Hapus synthetic.png"]');
    await waitFor('document.querySelector("[data-rme-form]").rmeAutosave.hasPendingPhotos()');
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()'), true);
    await waitFor('document.querySelector("[data-rme-autosave-status]").innerText.includes("Foto belum disimpan")');
    assert.ok(JSON.parse(sql.prepare('SELECT photos FROM medical_records WHERE id=?').get(visit.medical_record_id).photos).length > 0, 'Text autosave does not apply a pending photo deletion');
    await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 900, deviceScaleFactor: 1, mobile: false });
    await fill('#subjective', '');
    await click(tab('vital'));
    await evaluate('[...document.querySelectorAll("button")].find(button => button.textContent.includes("Tinjau &")).click()');
    await waitFor('document.querySelector("[data-rme-form] [role=dialog]").getBoundingClientRect().height > 0');
    assert.ok(await evaluate('document.querySelector("[data-rme-review-vital]").innerText.includes("120")'));
    assert.ok(await evaluate('document.querySelector("[data-rme-review-prescription]").innerText.includes("Obat sintetis")'));
    assert.ok(await evaluate('document.querySelector("[data-rme-review-allergy]").innerText.includes("sudah dikonfirmasi")'));
    await evaluate('document.querySelector("button[formaction]").click()');
    try {
        await waitFor('document.activeElement.id === "subjective" && document.querySelector("[data-rme-form] [role=dialog]").getBoundingClientRect().height === 0');
    } catch (error) {
        const focus = await evaluate('({ active: document.activeElement.id, inert: document.querySelector("[data-rme-form]").inert, soapHidden: document.querySelector("[data-rme-panel=soap]").hidden, subjectiveMissing: document.querySelector("#subjective").validity.valueMissing, dialogHeight: document.querySelector("[data-rme-form] [role=dialog]").getBoundingClientRect().height })');
        throw new Error(error.message + ' Focus state: ' + JSON.stringify(focus));
    }
    await waitFor('document.querySelector("#subjective").getBoundingClientRect().top >= document.querySelector("[data-rme-tabs]").getBoundingClientRect().bottom');
    assert.equal(sql.prepare('SELECT status FROM medical_records WHERE id=?').get(visit.medical_record_id).status, 'DRAFT');
    await fill('#subjective', 'Keluhan simulasi oleh dokter.');
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.flush()'), true);
    assert.equal(await evaluate('document.querySelector("[data-rme-form]").rmeAutosave.hasPendingPhotos()'), true);
    await click(tab('vital'));
    await evaluate('[...document.querySelectorAll("button")].find(button => button.textContent.includes("Tinjau &")).click()');
    await waitFor('document.querySelector("[data-rme-form] [role=dialog]").getBoundingClientRect().height > 0');
    await evaluate('document.querySelector("button[formaction]").click()');
    await waitFor('!document.querySelector("[data-rme-autosave]") && Boolean(document.querySelector("#correction_reason"))');
    assert.equal(sql.prepare('SELECT status FROM medical_records WHERE id=?').get(visit.medical_record_id).status, 'FINAL');
    assert.equal(sql.prepare('SELECT status FROM visits WHERE id=?').get(visit.id).status, 'COMPLETED');
    assert.equal(JSON.parse(sql.prepare('SELECT photos FROM medical_records WHERE id=?').get(visit.medical_record_id).photos).length, 0, 'Explicit finalization applies the pending photo deletion');
    await click(tab('prescription'));
    await click('#critical_allergies_acknowledged');
    await click(tab('soap'));
    await fill('#assessment', 'Koreksi penilaian simulasi.');
    await fill('#correction_reason', 'Koreksi setelah peninjauan ulang simulasi.');
    await waitFor('document.querySelector("[data-rme-autosave-status]").innerText.includes("Koreksi belum disimpan")');
    await evaluate('[...document.querySelectorAll("button")].find(button => button.textContent.trim() === "Simpan Modification").click()');
    await waitFor('document.body.innerText.includes("Final · dikoreksi")');
    assert.equal(sql.prepare('SELECT COUNT(*) AS n FROM medical_record_revisions WHERE medical_record_id=?').get(visit.medical_record_id).n, 1);
    console.log('RME checks passed: tabs, keyboard, row focus/add/remove, draft reload, hidden validation, offline/recovery, stale editor, critical allergy, prescription, finalization, correction.');
}
