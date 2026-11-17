const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const settle = () => new Promise(resolve => setTimeout(resolve, 25));

function page({ timeout = false, denied = false, mounted = true, prompt = false, subscribed = false, configured = true, dismissed = false } = {}) {
    const handlers = {}, observers = [], status = { textContent: 'Checking...' };
    const buttons = Object.fromEntries((prompt ? ['enable', 'dismiss'] : ['enable', 'disable', 'test']).map(action => [action, { disabled: action !== 'dismiss', dataset: { pushAction: action } }]));
    const node = { dataset: { base: 'https://crm.example.com/notifications/push' },
        querySelector(selector) { return selector === '[data-push-status]' ? status : buttons[selector.match(/"(.*?)"/)[1]]; },
        querySelectorAll() { return Object.values(buttons); },
    };
    if (prompt) Object.assign(node.dataset, { pushPrompt: 'browser-push-enable-prompt', promptKey: 'prompt-user-session' });
    const modalEvents = [];
    const session = new Map(dismissed ? [['prompt-user-session', '1']] : []);
    for (const button of Object.values(buttons)) button.closest = selector => selector === '[data-push-action]' ? button : node;
    let nodes = mounted ? [node] : [], requests = 0, permissionRequests = 0, subscription = subscribed ? { endpoint: 'https://fcm.googleapis.com/test' } : null;
    const notification = { permission: denied ? 'denied' : 'default', async requestPermission() { permissionRequests++; notification.permission = 'granted'; return 'granted'; } };
    const registration = { pushManager: {
        getSubscription: async () => subscription,
        subscribe: async () => subscription = { endpoint: 'https://fcm.googleapis.com/test', toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/test', keys: {} }) },
    } };
    const context = {
        window: { isSecureContext: true, PushManager: function() {}, Notification: notification,
            dispatchEvent: event => modalEvents.push(event), sessionStorage: { getItem: key => session.get(key), setItem: (key, value) => session.set(key, value) } }, Notification: notification,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        navigator: { serviceWorker: { getRegistration: async () => subscription ? registration : undefined, register: async () => registration, ready: Promise.resolve(registration), addEventListener() {} } },
        document: { readyState: 'complete', documentElement: {}, querySelector: () => ({ content: 'csrf' }), querySelectorAll: () => nodes, addEventListener(name, fn) { handlers[name] = fn; } },
        MutationObserver: class { constructor(fn) { observers.push(fn); } observe() {} },
        AbortController, Uint8Array, atob,
        setTimeout: fn => setTimeout(fn, timeout ? 5 : 1000), clearTimeout,
        fetch: async (url, options) => {
            requests++;
            if (timeout) return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' }))));
            return { ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => url.endsWith('/config')
                ? { configured, publicKey: Buffer.alloc(65, 4).toString('base64url'), workerUrl: 'https://crm.example.com/browser-push-sw.js' }
                : { subscribed: true } };
        },
    };
    vm.runInNewContext(fs.readFileSync('public/browser-push.js', 'utf8'), context);
    return { status, buttons, modalEvents, requests: () => requests, permissionRequests: () => permissionRequests,
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
test('timed-out server request shows refresh guidance rather than Checking forever', async () => {
    const instance = page({ timeout: true }); await settle();
    assert.match(instance.status.textContent, /did not respond/);
    assert.match(instance.status.textContent, /Refresh the page/);
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
test('CRM entry opens the in-app prompt without requesting browser permission automatically', async () => {
    const instance = page({ prompt: true }); await settle();
    assert.equal(instance.modalEvents.filter(event => event.type === 'open-modal').length, 1);
    assert.equal(instance.permissionRequests(), 0);
    await instance.click('enable');
    assert.equal(instance.permissionRequests(), 1);
    assert.equal(instance.modalEvents.at(-1).type, 'close-modal');
});
test('Not Now closes the prompt and a dismissed login session does not show it again', async () => {
    const instance = page({ prompt: true }); await settle();
    await instance.click('dismiss');
    assert.equal(instance.modalEvents.at(-1).type, 'close-modal');
    const dismissed = page({ prompt: true, dismissed: true }); await settle();
    assert.equal(dismissed.modalEvents.length, 0);
});
test('enabled browsers and unconfigured servers do not get an enable prompt', async () => {
    const enabled = page({ prompt: true, subscribed: true });
    const unconfigured = page({ prompt: true, configured: false }); await settle();
    assert.equal(enabled.modalEvents.filter(event => event.type === 'open-modal').length, 0);
    assert.equal(unconfigured.modalEvents.length, 0);
});
