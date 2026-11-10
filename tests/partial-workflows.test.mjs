import test from 'node:test';
import assert from 'node:assert/strict';
import { RmeAutosave } from '../resources/js/rme-autosave.js';
import { bookingSelection } from '../resources/js/booking-form.js';

class Form extends EventTarget {
    action = 'http://localhost/draft';
    elements = { record_version: { value: 0 } };
    isConnected = true;
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
