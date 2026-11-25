import test from 'node:test';
import assert from 'node:assert/strict';
import { VoiceScribe } from '../resources/js/voice-scribe.js';

function client(request) {
    return Object.assign(Object.create(VoiceScribe.prototype), {
        pending: [{ number: 1, blob: new Blob(['one'], { type: 'audio/webm' }) },
            { number: 2, blob: new Blob(['two'], { type: 'audio/webm' }) }],
        session: { id: 'fixture-session' }, uploading: null, discard: false,
        request, render() {},
    });
}

test('upload failure retains the same segment for idempotent retry and preserves ordering', async () => {
    const calls = [];
    let fail = true;
    const voice = client(async (path, data) => {
        calls.push({ path, number: data.get('segment_no'), size: data.get('audio').size });
        if (fail) throw new Error('offline');
    });
    const firstBlob = voice.pending[0].blob;
    await assert.rejects(voice.flushUploads(), /offline/);
    assert.equal(voice.pending.length, 2);
    assert.equal(voice.pending[0].blob, firstBlob);
    fail = false;
    await voice.flushUploads();
    assert.deepEqual(calls.map(call => call.number), ['1', '1', '2']);
    assert.equal(voice.pending.length, 0);
});

test('parallel flush calls share the pending upload and cannot send a segment twice', async () => {
    let release;
    let calls = 0;
    const gate = new Promise(resolve => { release = resolve; });
    const voice = client(async () => { calls++; await gate; });
    const first = voice.flushUploads();
    const second = voice.flushUploads();
    assert.equal(calls, 1);
    release();
    await Promise.all([first, second]);
    assert.equal(calls, 2);
    assert.equal(voice.pending.length, 0);
});

test('stop waits for final captured data and confirmed upload before enqueueing processing', async () => {
    const order = [];
    const voice = client(async (path, data) => {
        order.push(path.endsWith('/stop') ? 'stop:' + data.expected_segments : 'upload:' + data.get('segment_no'));
        return { id: 'fixture-session', status: 'processing' };
    });
    voice.nextNumber = 3;
    voice.stopLocal = async () => { await Promise.resolve(); order.push('microphone-stopped'); };
    voice.accept = session => { voice.session = session; };
    await voice.finish();
    assert.deepEqual(order, ['microphone-stopped', 'upload:1', 'upload:2', 'stop:2']);
    assert.equal(voice.session.status, 'processing');
});

test('cancellation discards queued audio before a new provider request', async () => {
    const voice = client(async () => { assert.fail('Cancelled audio must not upload.'); });
    voice.discard = true;
    await voice.flushUploads();
    assert.equal(voice.pending.length, 2);
});

test('microphone denial reports inactive recording with an actionable message', () => {
    const voice = client(async () => {});
    const feedback = {};
    voice.state = 'recording';
    voice.wantRecording = false;
    voice.find = () => feedback;
    voice.error(Object.assign(new Error('denied'), { name: 'NotAllowedError' }));
    assert.equal(voice.state, 'paused');
    assert.match(feedback.textContent, /Izin mikrofon ditolak/);
});

test('server errors never display SQL or debug response content to the doctor', async () => {
    const previousFetch = globalThis.fetch;
    const previousDocument = globalThis.document;
    globalThis.document = { querySelector: () => ({ content: 'test-csrf' }) };
    globalThis.fetch = async () => ({ ok: false, status: 500, json: async () => ({ message: 'SQLSTATE private-test-value', errors: { query: ['private-test-value'] } }) });
    try {
        await assert.rejects(VoiceScribe.prototype.request.call(client(async () => {}), '', {}), error => {
            assert.match(error.message, /Coba lagi atau gunakan RME manual/);
            assert.doesNotMatch(error.message, /SQLSTATE|private-test-value/);
            return true;
        });
    } finally {
        globalThis.fetch = previousFetch;
        globalThis.document = previousDocument;
    }
});

for (const overwriteConfirmed of [false, true]) {
    test('filling the shared SOAP editor ' + (overwriteConfirmed ? 'waits for autosave and uses only selected Voice fields' : 'preserves manual data when replacement is declined'), async () => {
        const previousDocument = globalThis.document;
        const previousWindow = globalThis.window;
        const calls = [];
        const form = { elements: { record_version: { value: '3' }, subjective: { value: 'Isian manual dokter' } }, inert: false };
        const autosave = { submitting: false, hasPendingPhotos: () => false, flush: async () => {
            calls.push('autosave'); form.elements.record_version.value = '4'; return true;
        } };
        form.rmeAutosave = autosave;
        globalThis.document = { querySelector: () => form };
        globalThis.window = { confirm: () => overwriteConfirmed, location: { hash: '', reload: () => calls.push('reload') } };
        const voice = client(async (path, payload) => { calls.push({ path, payload }); return { status: 'completed' }; });
        voice.session.draft = { version: 2, soap: { chief_complaint: null, subjective: 'Hasil Voice', objective: null, assessment: null, plan: 'Rencana Voice' } };
        voice.root = { querySelectorAll: () => [{ dataset: { voiceSelect: 'subjective' } }] };
        voice.find = () => ({ checked: true });
        voice.accept = () => {};
        try {
            await voice.fillSoap();
            assert.equal(form.elements.subjective.value, 'Isian manual dokter');
            assert.equal(form.inert, false);
            assert.equal(autosave.submitting, false);
            if (overwriteConfirmed) {
                assert.equal(calls[0], 'autosave');
                assert.equal(calls[1].path, '/fixture-session/approve');
                assert.deepEqual(calls[1].payload.fields, ['subjective']);
                assert.equal(calls[1].payload.soap.subjective, 'Hasil Voice');
                assert.equal(calls[1].payload.record_version, 4);
                assert.equal(calls[1].payload.confirm_overwrite, true);
                assert.equal(calls[2], 'reload');
                assert.equal(window.location.hash, 'rme-general-heading');
            } else {
                assert.deepEqual(calls, ['autosave']);
            }
        } finally {
            globalThis.document = previousDocument;
            globalThis.window = previousWindow;
        }
    });
}

for (const status of [401, 419]) {
    test(`Voice HTTP${status} stops recording and offers login recovery without losing pending audio`, async () => {
        const previousDocument = globalThis.document;
        const previousFetch = globalThis.fetch;
        globalThis.document = { querySelector: () => ({ content: 'synthetic-csrf' }) };
        globalThis.fetch = async () => ({ ok: false, status, json: async () => ({}) });
        const voice = client(async () => {});
        let stopped = false;
        voice.stopLocal = async () => { stopped = true; };
        try {
            await assert.rejects(VoiceScribe.prototype.request.call(voice), /Masuk kembali di tab baru/);
            assert.equal(voice.loginRequired, true);
            assert.equal(stopped, true);
            assert.equal(voice.pending.length, 2);
        } finally {
            globalThis.document = previousDocument;
            globalThis.fetch = previousFetch;
        }
    });
}
