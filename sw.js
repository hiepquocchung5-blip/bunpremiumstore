// sw.js (MUST BE IN ROOT DIRECTORY)
// PRODUCTION v2.9 - Silent Matrix SW & Error Suppressor

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

    // 1. Skip non-GET (Forms, API POSTs must go to network)
    if (event.request.method !== 'GET') return;
    
    // 2. Skip Admin/API paths from aggressive caching
    if (url.pathname.includes('/admin/') || url.pathname.includes('/api/')) return;

    const isInternal = url.origin === self.location.origin;

    // ⚡️ STRATEGY 1: Image Cache First (Internal only)
    if (isInternal && event.request.destination === 'image') {
        event.respondWith(
            caches.open(IMAGE_CACHE_NAME).then(async cache => {
                const cachedResponse = await cache.match(event.request);
                if (cachedResponse) return cachedResponse;

                try {
                    const networkResponse = await fetch(event.request);
                    if (networkResponse && networkResponse.status === 200) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                } catch (e) {
                    return new Response('', { status: 404 });
                }
            })
        );
        return;
    }

    // ⚡️ STRATEGY 2: Stale-While-Revalidate for CSS/JS
    if (isInternal && (event.request.destination === 'style' || event.request.destination === 'script')) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async cache => {
                const cachedResponse = await cache.match(event.request);
                const fetchPromise = fetch(event.request).then(networkResponse => {
                    if (networkResponse && networkResponse.status === 200) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                }).catch(() => null);

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // ⚡️ DEFAULT: Network First with SILENT Fallback
    // This logic ensures we don't trigger "Uncaught (in promise)" console errors.
    event.respondWith(
        fetch(event.request).catch(async () => {
            const cached = await caches.match(event.request);
            if (cached) return cached;
            
            // Return a neutral response to satisfy the promise silently.
            // Using 204 No Content for a truly silent failure on background resources.
            return new Response(null, { status: 204 });
        })
    );
});

self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) return;

    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'New Update', body: 'New information received.' };
    }

    const options = {
        body: data.body || 'You have a new message.',
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