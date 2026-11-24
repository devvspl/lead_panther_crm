// Lead Panther CRM - Service Worker (PWA & Web Push)
// No offline CRM data caching: CRM responses, lead data and notification details stay private.

const CACHE_NAME = 'leadpanther-pwa-static-v1';
const PRECACHE_ASSETS = [
    '/manifest.json',
    '/offline.html',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/icons/apple-touch-icon.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(PRECACHE_ASSETS).catch(() => {
                // Pre-cache failure should not block install
            });
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            );
        }).then(() => self.clients.claim())
    );
});

// PWA fetch handler: Satisfies PWA install criteria without caching any sensitive CRM data
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Only handle GET requests on same origin
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Static PWA assets: cache-first with network fallback
    const isStaticAsset = url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest.json' ||
        url.pathname === '/offline.html' ||
        url.pathname === '/favicon.ico' ||
        url.pathname === '/apple-touch-icon.png';

    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response && response.status === 200) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
                    }
                    return response;
                }).catch(() => cached);
            })
        );
        return;
    }

    // Navigation requests (HTML pages): Network-first, fallback to clean offline page on connectivity loss.
    // Strictly NO private page caching.
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                const offlinePage = await cache.match('/offline.html');
                if (offlinePage) return offlinePage;
                return new Response(
                    '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lead Panther - Offline</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0A0A0A;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;text-align:center}.card{max-width:360px}.logo{font-size:24px;font-weight:bold;margin-bottom:12px;display:inline-block;padding:8px 16px;background:#171717;border-radius:12px;border:1px solid #262626}h1{font-size:20px;margin:0 0 8px}p{font-size:14px;color:#a3a3a3;line-height:1.5;margin:0 0 20px}button{background:#fff;color:#000;border:none;padding:10px 20px;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer}</style></head><body><div class="card"><div class="logo">LP</div><h1>You are offline</h1><p>Please check your internet connection and try again to access Lead Panther CRM.</p><button onclick="window.location.reload()">Retry Connection</button></div></body></html>',
                    { headers: { 'Content-Type': 'text/html' } }
                );
            })
        );
        return;
    }

    // All other requests (Livewire updates, API calls, dynamic data): let browser handle natively
    return;
});

// Push notification handling
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
            data: { url: target.href }, renotify: false, requireInteraction: true, silent: false });
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
