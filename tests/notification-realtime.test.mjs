import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function notificationClient() {
    let receive;
    let initialize;
    const events = [];
    const counter = { textContent: '0', classList: { remove() {} } };
    const element = { dataset: { userNotificationChannel: 'users.7' } };
    const document = {
        querySelector(selector) {
            return selector === '[data-user-notification-channel]' ? element
                : selector === '[data-notification-count]' ? counter : null;
        },
        querySelectorAll() { return []; },
        addEventListener(name, callback) {
            if (name === 'DOMContentLoaded') initialize = callback;
        },
    };
    const window = {
        Echo: { private() { return { notification(callback) { receive = callback; } }; } },
        setInterval() {},
        dispatchEvent(event) { events.push(event); },
    };
    runInNewContext(readFileSync(new URL('../resources/js/realtime.js', import.meta.url), 'utf8'), {
        document, window, console,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    });
    initialize();

    return { receive: (message) => receive(message), counter, events };
}

test('redelivery of the same notification increments the counter and emits the event once', () => {
    const client = notificationClient();
    client.receive({ id: 'notification-a', type: 'appointment.status' });
    client.receive({ id: 'notification-a', type: 'appointment.status' });
    assert.equal(client.counter.textContent, '1');
    assert.equal(client.events.length, 1);
});

test('distinct notifications remain visible and messages without IDs preserve compatibility', () => {
    const client = notificationClient();
    client.receive({ id: 'notification-a' });
    client.receive({ id: 'notification-b' });
    client.receive({ title: 'legacy notification' });
    assert.equal(client.counter.textContent, '3');
    assert.equal(client.events.length, 3);
});
