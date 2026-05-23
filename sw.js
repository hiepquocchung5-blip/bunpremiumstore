// sw.js (MUST BE IN ROOT DIRECTORY)
// PRODUCTION v2.7 - Hardened Matrix SW & Response Stability Fix

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

    // Skip non-GET requests (Critical for forms/AJAX)
    if (event.request.method !== 'GET') return;
    
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
                    // Fail gracefully, maybe return a placeholder?
                    return new Response('', { status: 404 });
                }
            })
        );
        return;
    }

    // ⚡️ STRATEGY 2: Stale-While-Revalidate for CSS/JS (Internal only)
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

                // ENSURE WE ALWAYS RETURN A VALID RESPONSE
                return cachedResponse || fetchPromise || new Response('', { status: 503 });
            })
        );
        return;
    }

    // Default Strategy: Network First with Graceful Fallback
    event.respondWith(
        fetch(event.request).catch(async () => {
            const cached = await caches.match(event.request);
            // ⚡️ CRITICAL FIX: If no cache match, return a valid 404 Response object 
            // instead of 'undefined' to prevent the "Failed to convert to Response" error.
            return cached || new Response('Offline: Resource not in Matrix Cache', {
                status: 503,
                statusText: 'Service Unavailable',
                headers: new Headers({ 'Content-Type': 'text/plain' })
            });
        })
    );
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