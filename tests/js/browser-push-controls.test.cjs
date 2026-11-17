const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const settle = () => new Promise(resolve => setTimeout(resolve, 25));

function page({ timeout = false, denied = false, mounted = true } = {}) {
    const handlers = {}, observers = [], status = { textContent: 'Checking...' };
    const buttons = Object.fromEntries(['enable', 'disable', 'test', 'retry'].map(action => [action, { disabled: action !== 'retry', dataset: { pushAction: action } }]));
    const node = { dataset: { base: 'https://crm.example.com/notifications/push' },
        querySelector(selector) { return selector === '[data-push-status]' ? status : buttons[selector.match(/"(.*?)"/)[1]]; },
        querySelectorAll() { return Object.values(buttons); },
    };
    for (const button of Object.values(buttons)) button.closest = selector => selector === '[data-push-action]' ? button : node;
    let nodes = mounted ? [node] : [], requests = 0, permissionRequests = 0, subscription = null;
    const notification = { permission: denied ? 'denied' : 'default', async requestPermission() { permissionRequests++; notification.permission = 'granted'; return 'granted'; } };
    const registration = { pushManager: {
        getSubscription: async () => subscription,
        subscribe: async () => subscription = { endpoint: 'https://fcm.googleapis.com/test', toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/test', keys: {} }) },
    } };
    const context = {
        window: { isSecureContext: true, PushManager: function() {}, Notification: notification }, Notification: notification,
        navigator: { serviceWorker: { getRegistration: async () => subscription ? registration : undefined, register: async () => registration, ready: Promise.resolve(registration), addEventListener() {} } },
        document: { documentElement: {}, querySelector: () => ({ content: 'csrf' }), querySelectorAll: () => nodes, addEventListener(name, fn) { handlers[name] = fn; } },
        MutationObserver: class { constructor(fn) { observers.push(fn); } observe() {} },
        AbortController, Uint8Array, atob,
        setTimeout: fn => setTimeout(fn, timeout ? 5 : 1000), clearTimeout,
        fetch: async (url, options) => {
            requests++;
            if (timeout) return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' }))));
            return { ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => url.endsWith('/config')
                ? { configured: true, publicKey: Buffer.alloc(65, 4).toString('base64url'), workerUrl: 'https://crm.example.com/browser-push-sw.js' }
                : { subscribed: true } };
        },
    };
    vm.runInNewContext(fs.readFileSync('public/browser-push.js', 'utf8'), context);
    return { status, buttons, requests: () => requests, permissionRequests: () => permissionRequests,
        async click(action) { await handlers.click({ target: buttons[action] }); },
        mount() { nodes = [node]; observers.forEach(fn => fn()); },
    };
}

test('standalone controls finish Checking and request permission only on Enable click', async () => {
    const instance = page(); await settle();
    assert.equal(instance.status.textContent, 'Not enabled on this browser');
    assert.equal(instance.buttons.enable.disabled, false);
    assert.equal(instance.permissionRequests(), 0);
    await instance.click('enable');
    assert.equal(instance.permissionRequests(), 1);
    assert.equal(instance.status.textContent, 'Enabled on this browser');
});
test('timed-out server request leaves a usable retry control rather than Checking forever', async () => {
    const instance = page({ timeout: true }); await settle();
    assert.match(instance.status.textContent, /did not respond/);
    assert.equal(instance.buttons.retry.disabled, false);
});
test('controls initialize when Livewire adds the notification dropdown after page load', async () => {
    const instance = page({ mounted: false });
    assert.equal(instance.requests(), 0);
    instance.mount(); await settle();
    assert.equal(instance.buttons.enable.disabled, false);
});
test('blocked browser permission shows the site-settings instruction without reprompting', async () => {
    const instance = page({ denied: true }); await settle();
    assert.match(instance.status.textContent, /browser site settings/);
    assert.equal(instance.buttons.enable.disabled, true);
    assert.equal(instance.permissionRequests(), 0);
});
