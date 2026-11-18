const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function worker(response) {
    const handlers = {}, shown = [], opened = [];
    let fetched = 0;
    const self = {
        location: { origin: 'https://crm.example.com' },
        addEventListener: (name, fn) => handlers[name] = fn,
        registration: { showNotification: async (...args) => shown.push(args) },
        clients: { matchAll: async () => [], openWindow: async url => opened.push(url) },
    };
    vm.runInNewContext(fs.readFileSync('public/browser-push-sw.js', 'utf8'), {
        self, URL, fetch: async () => { fetched++; return response; },
    });
    return { shown, opened, fetched: () => fetched, async push(messageUrl) {
        let result;
        handlers.push({ data: { json: () => ({ messageUrl }) }, waitUntil: promise => result = promise });
        await result;
    }, async click(url) {
        let result;
        handlers.notificationclick({ notification: { close() {}, data: { url } }, waitUntil: promise => result = promise });
        await result;
    } };
}
test('current authenticated notification renders and click stays on the CRM origin', async () => {
    const instance = worker({ ok: true, json: async () => ({ title: 'Assigned', body: 'Your team update', url: 'https://crm.example.com/notifications/123/open', tag: '123' }) });
    await instance.push('https://crm.example.com/notifications/push/message/123');
    assert.equal(instance.shown[0][0], 'Assigned');
    assert.equal(instance.shown[0][1].requireInteraction, true);
    assert.equal(instance.shown[0][1].silent, false);
    await instance.click('https://crm.example.com/notifications/123/open');
    assert.deepEqual(instance.opened, ['https://crm.example.com/notifications/123/open']);
});
test('logged out or another account does not display private push contents', async () => {
    const instance = worker({ ok: false });
    await instance.push('https://crm.example.com/notifications/push/message/123');
    assert.equal(instance.shown.length, 0);
});
test('foreign fetch and click targets are rejected', async () => {
    const instance = worker({ ok: true });
    await instance.push('https://other.example.com/notifications/push/message/123');
    await instance.click('https://other.example.com/');
    assert.equal(instance.fetched(), 0);
    assert.equal(instance.opened.length, 0);
});
