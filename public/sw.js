/* Service worker: Web Push (chronicle) + library offline packs */
const LIBRARY_OFFLINE_CACHE = 'om-library-offline-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET') {
        return;
    }
    if (!url.pathname.match(/^\/library\/[^/]+\/offline-pack$/)) {
        return;
    }

    event.respondWith((async () => {
        try {
            const network = await fetch(event.request);
            if (network.ok) {
                const cache = await caches.open(LIBRARY_OFFLINE_CACHE);
                await cache.put(event.request, network.clone());
            }
            return network;
        } catch {
            const cache = await caches.open(LIBRARY_OFFLINE_CACHE);
            const cached = await cache.match(event.request);
            if (cached) {
                return cached;
            }
            return new Response(JSON.stringify({ ok: false, message: 'offline' }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' },
            });
        }
    })());
});

self.addEventListener('push', (event) => {
    let data = { title: 'Новая запись', body: '', url: '/' };
    try {
        if (event.data) {
            const parsed = event.data.json();
            data = { ...data, ...parsed };
        }
    } catch {
        try {
            const text = event.data ? event.data.text() : '';
            if (text) {
                data.body = text;
            }
        } catch {
            /* ignore */
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Новая запись', {
            body: data.body || 'Новая запись в хронике',
            data: { url: data.url || '/' },
            renotify: false,
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        (async () => {
            const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            for (const client of clientsList) {
                if ('focus' in client) {
                    await client.focus();
                    if ('navigate' in client && typeof client.navigate === 'function') {
                        try {
                            await client.navigate(targetUrl);
                            return;
                        } catch {
                            /* fall through */
                        }
                    }
                    return;
                }
            }
            if (self.clients.openWindow) {
                await self.clients.openWindow(targetUrl);
            }
        })(),
    );
});
