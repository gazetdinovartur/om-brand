/* Web Push service worker — new chronicle posts */
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
