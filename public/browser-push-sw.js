// No offline page caching: CRM responses and notification details must stay private.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let payload;
        try { payload = event.data.json(); } catch { return; }
        const endpoint = new URL(payload.messageUrl, self.location.origin);
        if (endpoint.origin !== self.location.origin || !endpoint.pathname.includes('/notifications/push/message/')) return;
        // Fetch with the current session. Push payloads contain no lead names or personal details.
        const response = await fetch(endpoint.href, { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const message = await response.json();
        const target = new URL(message.url, self.location.origin);
        if (target.origin !== self.location.origin) return;
        await self.registration.showNotification(message.title, { body: message.body, tag: message.tag,
            data: { url: target.href }, renotify: false });
        for (const client of await self.clients.matchAll({ type: 'window' })) client.postMessage({ type: 'notifications-refresh' });
    })());
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        const target = new URL(event.notification.data?.url || '/', self.location.origin);
        if (target.origin !== self.location.origin) return;
        for (const client of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) {
            if (new URL(client.url).origin === self.location.origin) { await client.navigate(target.href); return client.focus(); }
        }
        return self.clients.openWindow(target.href);
    })());
});
