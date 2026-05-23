// sw.js (MUST BE IN ROOT DIRECTORY)
// PRODUCTION v2.5 - Advanced Service Worker Matrix, Smart Routing & Image Cache

const CACHE_NAME = 'matrix-static-v1';
const IMAGE_CACHE_NAME = 'matrix-images-v1';

// Assets to cache immediately on install
const PRECACHE_ASSETS = [
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/images/logo.png',
    '/assets/images/favicon.ico'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.map(key => {
                if (key !== CACHE_NAME && key !== IMAGE_CACHE_NAME) return caches.delete(key);
            })
        ))
    );
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // ⚡️ STRATEGY: Image Cache First (For Products & Categories)
    if (event.request.destination === 'image') {
        event.respondWith(
            caches.open(IMAGE_CACHE_NAME).then(cache => {
                return cache.match(event.request).then(response => {
                    return response || fetch(event.request).then(networkResponse => {
                        // Cache a copy of the new image
                        cache.put(event.request, networkResponse.clone());
                        return networkResponse;
                    });
                });
            })
        );
        return;
    }

    // ⚡️ STRATEGY: Stale-While-Revalidate for CSS/JS
    if (event.request.destination === 'style' || event.request.destination === 'script') {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                const fetchPromise = fetch(event.request).then(networkResponse => {
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, networkResponse.clone()));
                    return networkResponse;
                });
                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // Default: Network First
    event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});

self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) return;

    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Secure Transmission', body: 'Encrypted payload received.' };
    }

    const options = {
        body: data.body || 'Incoming transmission...',
        icon: data.icon || '/assets/images/logo.png',
        badge: data.badge || '/assets/images/logo.png',
        vibrate: [100, 50, 100, 50, 200],
        data: { url: data.url || '/index.php?module=user&page=orders' }
    };

    event.waitUntil(self.registration.showNotification(data.title || 'DigitalMarketplaceMM', options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const targetUrl = event.notification.data.url;
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            for (let client of windowClients) {
                if (client.url === targetUrl && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});