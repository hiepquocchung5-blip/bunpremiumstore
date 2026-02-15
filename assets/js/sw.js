// assets/sw.js

self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const data = event.data ? event.data.json() : {};
    const title = data.title || 'DigitalMarketplaceMM';
    const message = data.body || 'You have a new notification.';
    const icon = data.icon || 'https://digitalmarketplacemm.com/assets/images/logo.png'; // Update URL
    const link = data.url || 'https://digitalmarketplacemm.com/index.php?module=user&page=orders';

    const options = {
        body: message,
        icon: icon,
        badge: icon,
        data: { url: link }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url));
});