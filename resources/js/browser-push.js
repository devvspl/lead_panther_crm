const supported = () => window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
const decodeKey = (key) => Uint8Array.from(atob(key.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - key.length % 4) % 4)), c => c.charCodeAt(0));

async function request(node, action, body) {
    const response = await fetch(`${node.dataset.base}/${action}`, {
        method: body ? 'POST' : 'GET', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Browser notification request failed.');
    return result;
}

function show(node, text, enabled = false, ready = false) {
    node.querySelector('[data-push-status]').textContent = text;
    node.querySelector('[data-push-action="enable"]').disabled = !ready || enabled;
    node.querySelector('[data-push-action="disable"]').disabled = !enabled;
    node.querySelector('[data-push-action="test"]').disabled = !enabled;
}

async function refresh(node) {
    if (!supported()) { show(node, 'Requires HTTPS and a supported browser.'); return; }
    if (Notification.permission === 'denied') { show(node, 'Blocked. Allow notifications in browser site settings.'); return; }
    const config = await request(node, 'config');
    node.pushConfig = config;
    if (!config.configured) { show(node, 'Server setup required.'); return; }
    const registration = await navigator.serviceWorker.getRegistration(config.workerUrl);
    const subscription = await registration?.pushManager.getSubscription();
    const enabled = subscription ? (await request(node, 'status', { endpoint: subscription.endpoint })).subscribed : false;
    show(node, enabled ? 'Enabled on this browser' : 'Not enabled on this browser', enabled, true);
}

function initialize() {
    document.querySelectorAll('[data-browser-push]').forEach(node => {
        if (node.pushInitialized) return;
        node.pushInitialized = true;
        refresh(node).catch(() => show(node, 'Unable to check push status. Reopen this panel to retry.'));
    });
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-push-action]');
    const node = button?.closest('[data-browser-push]');
    if (!node || button.disabled) return;
    const action = button.dataset.pushAction;
    node.querySelectorAll('button').forEach(item => item.disabled = true);
    try {
        if (action === 'enable') {
            // Request permission directly from the user gesture, never on page load.
            if (await Notification.requestPermission() !== 'granted') { await refresh(node); return; }
            const config = node.pushConfig || await request(node, 'config');
            if (!config.configured) throw new Error('Server setup required.');
            const registration = await navigator.serviceWorker.register(config.workerUrl);
            await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                const owned = (await request(node, 'status', { endpoint: subscription.endpoint })).subscribed;
                const oldKey = subscription.options.applicationServerKey;
                const newKey = decodeKey(config.publicKey);
                const keyMatches = oldKey && new Uint8Array(oldKey).every((byte, i) => byte === newKey[i]) && oldKey.byteLength === newKey.length;
                if (!owned || !keyMatches) { await subscription.unsubscribe(); subscription = null; }
            }
            subscription ||= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeKey(config.publicKey) });
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
        await refresh(node).catch(() => {});
        node.querySelector('[data-push-status]').textContent = error.message || 'Could not update browser notifications.';
    }
});

document.addEventListener('DOMContentLoaded', initialize);
if ('serviceWorker' in navigator) navigator.serviceWorker.addEventListener('message', event => {
    if (event.data?.type === 'notifications-refresh') window.Livewire?.dispatch('notifications-refresh');
});
document.addEventListener('livewire:navigated', initialize);
new MutationObserver(initialize).observe(document.documentElement, { childList: true, subtree: true });
initialize();
