import test from 'node:test';
import assert from 'node:assert/strict';
import { RmeAutosave } from '../resources/js/rme-autosave.js';
import { bookingSelection } from '../resources/js/booking-form.js';

class Form extends EventTarget {
    action = 'http://localhost/draft';
    elements = { record_version: { value: 0 } };
    isConnected = true;
    inert = false;
    fields = { subjective: 'Draft', 'photos[]': 'pending-photo', 'removed_photos[]': '0' };
    hasAttribute() { return true; }
    querySelector() { return null; }
    querySelectorAll() { return []; }
}

globalThis.FormData = class extends Map {
    constructor(form) { super(Object.entries({ ...form.fields, record_version: form.elements.record_version.value })); }
};

function response(status, payload) {
    return { ok: status >= 200 && status < 300, status, json: async () => payload };
}

for (const status of [401, 419]) {
    for (const mode of ['autosave', 'manual']) {
        test(`${mode} HTTP${status} retains editor data and offers session recovery`, async () => {
            const form = new Form();
            const events = [];
            let navigated = false;
            form.addEventListener('rme-autosave-state', event => events.push(event.detail));
            const client = new RmeAutosave(form, {
                request: async () => response(status, {}),
                navigate: () => { navigated = true; },
            });
            client.markDirty();
            if (mode === 'autosave') await client.flush();
            else await client.submit({ preventDefault() {}, stopImmediatePropagation() {}, submitter: null });
            assert.equal(events.at(-1).state, 'expired');
            assert.match(events.at(-1).message, /Sesi berakhir.*Salin perubahan.*masuk kembali/);
            assert.equal(client.blocked, true);
            assert.equal(client.unsaved, true);
            assert.equal(form.inert, false);
            assert.equal(form.fields.subjective, 'Draft');
            assert.equal(form.fields['photos[]'], 'pending-photo');
            assert.equal(navigated, false);
            client.destroy();
        });
    }
}

test('a network failure remains retryable and is distinct from session expiry', async () => {
    const form = new Form();
    const events = [];
    form.addEventListener('rme-autosave-state', event => events.push(event.detail));
    let online = false;
    const client = new RmeAutosave(form, { request: async () => {
        if (!online) throw new TypeError('Failed to fetch');
        return response(200, {record_version: 1});
    } });
    client.markDirty();
    assert.equal(await client.flush(), false);
    assert.equal(events.at(-1).state, 'error');
    assert.equal(client.blocked, false);
    assert.equal(client.sessionExpired, false);
    assert.equal(client.unsaved, true);
    online = true;
    assert.equal(await client.flush(), true);
    assert.equal(client.unsaved, false);
    client.destroy();
});

test('autosave updates the version and keeps photos for the explicit save', async () => {
    const form = new Form();
    const client = new RmeAutosave(form, { request: async (url, options) => {
        assert.equal(options.body.has('photos[]'), false);
        assert.equal(options.body.has('removed_photos[]'), false);
        assert.equal(options.body.get('subjective'), 'Draft');
        return response(200, { record_version: 1 });
    } });
    client.markDirty();
    assert.equal(await client.flush(), true);
    assert.equal(form.elements.record_version.value, 1);
    assert.equal(client.unsaved, false);
    client.destroy();
});

test('network failures retain changes and can be retried; conflict stops automatic writes', async () => {
    let calls = 0;
    const client = new RmeAutosave(new Form(), { request: async () => {
        if (++calls === 1) throw new Error('offline');
        return response(409, { message: 'Versi berubah' });
    } });
    client.markDirty();
    assert.equal(await client.flush(), false);
    assert.equal(client.unsaved, true);
    assert.equal(await client.flush(), false);
    assert.equal(client.blocked, true);
    await client.flush();
    assert.equal(calls, 2);
    client.destroy();
});

test('text autosave keeps pending photos visible as unsaved work', async () => {
    const form = new Form();
    form.querySelector = selector => selector.includes('file') ? { files: [new Blob(['pending'])] } : null;
    const states = [];
    form.addEventListener('rme-autosave-state', event => states.push(event.detail.state));
    const client = new RmeAutosave(form, { request: async () => response(200, { record_version: 1 }) });
    client.markDirty();
    await client.flush();
    assert.equal(states.at(-1), 'photos');
    assert.equal(client.unsaved, true);
    client.destroy();
});

test('editing a final record is labelled as an unsaved correction without autosaving', () => {
    const form = new Form();
    form.hasAttribute = () => false;
    const states = [];
    form.addEventListener('rme-autosave-state', event => states.push(event.detail.state));
    const client = new RmeAutosave(form, { request: () => assert.fail('Final records must never autosave') });
    client.markDirty();
    assert.equal(states.at(-1), 'amending');
    assert.equal(client.timer, null);
    assert.equal(client.unsaved, true);
    client.destroy();
});

test('typing during an autosave is saved in a following request with the new version', async () => {
    let release;
    const sent = [];
    const form = new Form();
    const client = new RmeAutosave(form, { request: async (url, options) => {
        sent.push([...options.body]);
        if (sent.length === 1) await new Promise(resolve => { release = resolve; });
        return response(200, { record_version: sent.length });
    } });
    client.markDirty();
    const first = client.flush();
    form.fields.subjective = 'Terbaru';
    client.markDirty();
    release();
    await first;
    assert.equal(client.unsaved, true);
    await client.flush();
    assert.equal(new Map(sent[1]).get('record_version'), 1);
    assert.equal(new Map(sent[1]).get('subjective'), 'Terbaru');
    assert.equal(client.unsaved, false);
    client.destroy();
});

test('finalize waits for an active autosave and retains the form on a stale conflict', async () => {
    let release;
    let count = 0;
    const form = new Form();
    const client = new RmeAutosave(form, { request: async (url, options) => {
        if (++count === 1) {
            await new Promise(resolve => { release = resolve; });
            return response(200, { record_version: 1 });
        }
        assert.equal(url, 'http://localhost/complete');
        assert.equal(options.body.get('record_version'), 1);
        assert.equal(options.body.get('photos[]'), 'pending-photo');
        return response(409, { message: 'Konflik' });
    } });
    client.markDirty();
    const saving = client.flush();
    const submitting = client.submit({ preventDefault() {}, stopImmediatePropagation() {}, submitter: { getAttribute: () => 'http://localhost/complete' } });
    release();
    await Promise.all([saving, submitting]);
    assert.equal(form.inert, false);
    assert.equal(client.blocked, true);
    client.destroy();
});

test('manual save reuses a confirmed autosave and does not send the same draft twice', async () => {
    let release;
    let requests = 0;
    const form = new Form();
    const client = new RmeAutosave(form, { request: async () => {
        requests++;
        await new Promise(resolve => { release = resolve; });
        return response(200, { record_version: 1 });
    }, navigate: () => assert.fail('A clean confirmed draft does not need a reload') });
    client.markDirty();
    const saving = client.flush();
    const event = { preventDefault() {}, stopImmediatePropagation() {}, submitter: null };
    const manual = client.submit(event);
    assert.equal(form.inert, true);
    release();
    await Promise.all([saving, manual]);
    await client.submit(event);
    assert.equal(requests, 1);
    assert.equal(form.inert, false);
    assert.equal(client.unsaved, false);
    client.destroy();
});

test('manual save retains changes made during an autosave and uses its confirmed version', async () => {
    let release;
    const sent = [];
    const form = new Form();
    let navigated = false;
    const client = new RmeAutosave(form, { request: async (url, options) => {
        sent.push([...options.body]);
        if (sent.length === 1) {
            await new Promise(resolve => { release = resolve; });
            return response(200, { record_version: 1 });
        }
        return response(200, { redirect: '/draft' });
    }, navigate: () => { navigated = true; } });
    client.markDirty();
    const saving = client.flush();
    form.fields.subjective = 'Newer manual changes';
    client.markDirty();
    const manual = client.submit({ preventDefault() {}, stopImmediatePropagation() {}, submitter: null });
    release();
    await Promise.all([saving, manual]);
    assert.equal(sent.length, 2);
    assert.equal(new Map(sent[1]).get('record_version'), 1);
    assert.equal(new Map(sent[1]).get('subjective'), 'Newer manual changes');
    assert.equal(navigated, true);
    client.destroy();
});

test('concurrent flush callers wait for the next pending revision without duplicate requests', async () => {
    const releases = [];
    let requests = 0;
    const client = new RmeAutosave(new Form(), { request: async () => {
        const version = ++requests;
        await new Promise(resolve => { releases.push(resolve); });
        return response(200, { record_version: version });
    } });
    client.markDirty();
    const first = client.flush();
    client.markDirty();
    const second = client.flush();
    let thirdFinished = false;
    const third = client.flush().then(() => { thirdFinished = true; });
    releases[0]();
    await first;
    assert.equal(requests, 2);
    assert.equal(thirdFinished, false);
    releases[1]();
    await Promise.all([second, third]);
    assert.equal(requests, 2);
    assert.equal(client.form.elements.record_version.value, 2);
    client.destroy();
});

test('pending photos still require a manual request after text autosave', async () => {
    const form = new Form();
    form.querySelector = selector => selector.includes('file') ? { files: [new Blob(['pending'])] } : null;
    let requests = 0;
    const client = new RmeAutosave(form, { request: async () => ++requests === 1
        ? response(200, { record_version: 1 }) : response(200, { redirect: '/draft' }), navigate() {} });
    client.markDirty();
    await client.flush();
    await client.submit({ preventDefault() {}, stopImmediatePropagation() {}, submitter: null });
    assert.equal(requests, 2);
    client.destroy();
});

test('a successful HTTP status without a save confirmation retains the manual editor', async () => {
    const form = new Form();
    const states = [];
    form.addEventListener('rme-autosave-state', event => states.push(event.detail.state));
    const client = new RmeAutosave(form, { request: async () => response(200, {}), navigate: () => assert.fail('No valid acknowledgement') });
    client.markDirty();
    await client.submit({ preventDefault() {}, stopImmediatePropagation() {}, submitter: null });
    assert.equal(states.at(-1), 'error');
    assert.equal(client.unsaved, true);
    assert.equal(form.inert, false);
    client.destroy();
});

test('booking filters services by doctor and sessions by the selected date', () => {
    const state = bookingSelection([
        { id: 1, services: [{ id: 10 }], schedules: [{ id: 11, day: 1 }, { id: 12, day: 2 }] },
        { id: 2, services: [{ id: 20 }], schedules: [{ id: 21, day: 2 }] },
    ], { doctor_id: 1, appointment_date: '2026-11-10' });
    assert.deepEqual(state.availableServices, [{ id: 10 }]);
    assert.deepEqual(state.availableSchedules, [{ id: 12, day: 2 }]);
    state.doctorId = '2';
    assert.deepEqual(state.availableServices, [{ id: 20 }]);
    assert.deepEqual(state.availableSchedules, [{ id: 21, day: 2 }]);
    state.date = '2026-11-11';
    assert.deepEqual(state.availableSchedules, []);
});

test('booking excludes dates blocked by doctor absence without hiding other sessions', () => {
    const state = bookingSelection([{ id: 1, schedules: [
        { id: 11, day: 3, blocked_dates: ['2026-11-18'] }, { id: 12, day: 3, blocked_dates: [] },
    ] }], { doctor_id: 1, appointment_date: '2026-11-18' });
    assert.deepEqual(state.availableSchedules.map(schedule => schedule.id), [12]);
    state.date = '2026-11-25';
    assert.deepEqual(state.availableSchedules.map(schedule => schedule.id), [11, 12]);
});
