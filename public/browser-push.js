(() => {
if (window.leadPantherPushLoaded) return;
window.leadPantherPushLoaded = true;
const supported = () => window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
const decodeKey = (key) => Uint8Array.from(atob(key.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - key.length % 4) % 4)), c => c.charCodeAt(0));

async function bounded(promise, message, milliseconds = 12000) {
    let timer;
    try {
        return await Promise.race([promise, new Promise((_, reject) => { timer = setTimeout(() => reject(new Error(message)), milliseconds); })]);
    } finally { clearTimeout(timer); }
}

async function request(node, action, body) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 12000);
    try {
    const response = await fetch(`${node.dataset.base}/${action}`, {
        signal: controller.signal,
        method: body ? 'POST' : 'GET', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    if (response.status === 401 || response.status === 419 || response.redirected) throw new Error('Session expired. Refresh the page and sign in again.');
    if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Push setup returned an invalid response. Refresh the page or check the server routes.');
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Browser notification request failed.');
    return result;
    } catch (error) {
        if (error.name === 'AbortError') throw new Error('Push server did not respond. Click Check Again to retry.');
        throw error;
    } finally { clearTimeout(timer); }
}

function show(node, text, enabled = false, ready = false) {
    node.querySelector('[data-push-status]').textContent = text;
    node.querySelector('[data-push-action="enable"]').disabled = !ready || enabled;
    node.querySelector('[data-push-action="disable"]').disabled = !enabled;
    node.querySelector('[data-push-action="test"]').disabled = !enabled;
    node.querySelector('[data-push-action="retry"]').disabled = false;
}

async function refresh(node) {
    if (!supported()) { show(node, 'Requires HTTPS and a supported browser.'); return; }
    if (Notification.permission === 'denied') { show(node, 'Blocked. Allow notifications in browser site settings.'); return; }
    const config = await request(node, 'config');
    node.pushConfig = config;
    if (!config.configured) { show(node, 'Server setup required.'); return; }
    const registration = await bounded(navigator.serviceWorker.getRegistration(config.workerUrl), 'Browser worker status timed out. Click Check Again.');
    const subscription = registration ? await bounded(registration.pushManager.getSubscription(), 'Browser subscription check timed out. Click Check Again.') : null;
    const enabled = subscription ? (await request(node, 'status', { endpoint: subscription.endpoint })).subscribed : false;
    show(node, enabled ? 'Enabled on this browser' : 'Not enabled on this browser', enabled, true);
}

function initialize() {
    document.querySelectorAll('[data-browser-push]').forEach(node => {
        if (node.pushInitializedV2) return;
        node.pushInitializedV2 = true;
        node.pushInitialized = true;
        refresh(node).catch(error => show(node, error.message || 'Unable to check push status. Click Check Again.'));
    });
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-push-action]');
    const node = button?.closest('[data-browser-push]');
    if (!node || button.disabled) return;
    const action = button.dataset.pushAction;
    node.querySelectorAll('button').forEach(item => item.disabled = true);
    try {
        if (action === 'retry') { await refresh(node); return; }
        if (action === 'enable') {
            // Request permission directly from the user gesture, never on page load.
            node.querySelector('[data-push-status]').textContent = 'Allow notifications in the browser prompt...';
            if (await Notification.requestPermission() !== 'granted') { await refresh(node); return; }
            const config = node.pushConfig || await request(node, 'config');
            if (!config.configured) throw new Error('Server setup required.');
            const registration = await bounded(navigator.serviceWorker.register(config.workerUrl), 'Push worker failed to register. Check browser-push-sw.js on the server.');
            await bounded(navigator.serviceWorker.ready, 'Push worker did not activate. Refresh and try again.');
            let subscription = await bounded(registration.pushManager.getSubscription(), 'Browser subscription check timed out.');
            if (subscription) {
                const owned = (await request(node, 'status', { endpoint: subscription.endpoint })).subscribed;
                const oldKey = subscription.options.applicationServerKey;
                const newKey = decodeKey(config.publicKey);
                const keyMatches = oldKey && new Uint8Array(oldKey).every((byte, i) => byte === newKey[i]) && oldKey.byteLength === newKey.length;
                if (!owned || !keyMatches) { await subscription.unsubscribe(); subscription = null; }
            }
            subscription ||= await bounded(registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeKey(config.publicKey) }), 'Browser push subscription timed out. Check browser/network settings and retry.', 25000);
            await request(node, 'subscribe', subscription.toJSON());
        } else {
            const registration = await navigator.serviceWorker.getRegistration(node.pushConfig.workerUrl);
            const subscription = await registration?.pushManager.getSubscription();
            if (!subscription) throw new Error('Enable browser notifications first.');
            if (action === 'disable') {
                await request(node, 'unsubscribe', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            } else {
                const result = await request(node, 'test', { endpoint: subscription.endpoint });
                await refresh(node);
                node.querySelector('[data-push-status]').textContent = result.message;
                return;
            }
        }
        await refresh(node);
    } catch (error) {
        show(node, error.message || 'Could not update browser notifications. Click Check Again.');
    }
}, true);

document.addEventListener('DOMContentLoaded', initialize);
if ('serviceWorker' in navigator) navigator.serviceWorker.addEventListener('message', event => {
    if (event.data?.type === 'notifications-refresh') window.Livewire?.dispatch('notifications-refresh');
});
document.addEventListener('livewire:navigated', initialize);
new MutationObserver(initialize).observe(document.documentElement, { childList: true, subtree: true });
initialize();
})();
