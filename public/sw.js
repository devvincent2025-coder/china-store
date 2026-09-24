// Two unrelated jobs share this one file only because a page can only have one active service
// worker: (1) showing a browser notification for each web push event (unchanged from before — see
// resources/js/utils/pushNotifications.js for registration/subscription, and
// app/Notifications/Concerns/BuildsDatabasePayload's toWebPush() for the payload shape), and
// (2) a minimal offline app-shell cache that's what actually makes the install prompt available at
// all — Chrome's installability criteria require a fetch handler, not just a manifest.

const CACHE_VERSION = 'cin-shell-v1';
const APP_SHELL = ['/', '/manifest.json', '/images/pwa/icon-192.png', '/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(APP_SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

// Network-first for page navigations (so a buyer always gets live prices/stock/order status when
// online — this is a marketplace, not a content site, stale-while-revalidate would be actively
// wrong here), falling back to the cached shell only when genuinely offline. Cache-first for
// static, fingerprinted build assets (JS/CSS/fonts/images), since those never change under the
// same URL — see Vite's content-hashed filenames in public/build/assets.
self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    const isStaticAsset = url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/');

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const clone = response.clone();
                caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
                return response;
            }))
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/').then((cached) => cached || Response.error()))
        );
    }
});

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'ChinaInNigeria', body: event.data.text() };
    }

    const title = payload.title || 'ChinaInNigeria';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/images/pwa/icon-192.png',
        badge: '/images/pwa/icon-192.png',
        data: payload.data || {},
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url;
    if (!url) return;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
