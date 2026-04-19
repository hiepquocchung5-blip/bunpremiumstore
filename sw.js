// sw.js (MUST BE IN ROOT DIRECTORY)
// PRODUCTION v2.0 - Advanced Service Worker Matrix & Smart Routing

self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    // Failsafe Payload Decryption
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        console.error('[SW] Payload decryption failed. Matrix interference detected:', e);
        data = { 
            title: 'Secure Transmission', 
            body: 'Encrypted payload received.' 
        };
    }

    const title = data.title || 'DigitalMarketplaceMM';
    const message = data.body || 'Incoming transmission received from the matrix.';
    const icon = data.icon || 'https://digitalmarketplacemm.com/assets/images/logo.png';
    
    // Ideal badge is a 96x96 white-with-transparent-background PNG 
    // Fallback to icon if badge is not specifically provided
    const badge = data.badge || icon; 
    
    const link = data.url || 'https://digitalmarketplacemm.com/index.php?module=user&page=orders';

    const options = {
        body: message,
        icon: icon,
        badge: badge,
        vibrate: [100, 50, 100, 50, 200], // Cyberpunk stutter pulse
        data: { url: link },
        timestamp: Date.now(),
        requireInteraction: false, 
        actions: [
            { action: 'open', title: 'Access Terminal' },
            { action: 'close', title: 'Dismiss' }
        ]
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    // 1. Close the notification automatically when clicked
    event.notification.close();

    // 2. Handle Action Buttons
    if (event.action === 'close') {
        return; // Abort routing if dismissed
    }

    // 3. Smart Tab Routing Logic
    const targetUrl = event.notification.data.url;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            
            // Check if there is already a window/tab open with the target URL
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                // If it's open, just focus it to prevent opening multiple identical tabs
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            
            // If the target URL is not open, initialize a new window
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});