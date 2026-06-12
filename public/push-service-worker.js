self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};

    try {
        payload = event.data.json();
    } catch (error) {
        payload = {};
    }

    const payloadData = payload.data || {};
    const title = payload.title || payloadData.title || 'Notifikasi PRIME';
    const body = payload.body || payloadData.message || payload.message || 'Ada pembaruan maintenance baru.';
    const targetUrl = payloadData.target_url || payload.target_url || '/dashboard';
    const notificationId = payloadData.notification_id || payload.notification_id || null;
    const tag = payload.tag || (notificationId ? `prime-notification-${notificationId}` : 'prime-notification');
    const options = {
        body,
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge || '/favicon.ico',
        data: {
            notification_id: notificationId,
            target_url: targetUrl,
        },
        tag,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const relativeTarget = event.notification.data?.target_url || '/dashboard';
    const targetUrl = new URL(relativeTarget, self.location.origin).href;

    event.waitUntil((async () => {
        const clientList = await clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        for (const client of clientList) {
            if (client.url === targetUrl && 'focus' in client) {
                await client.focus();
                return;
            }
        }

        if (clients.openWindow) {
            await clients.openWindow(targetUrl);
        }
    })());
});
