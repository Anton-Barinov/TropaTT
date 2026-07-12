self.addEventListener('push', function (event) {
  var payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (e) {
    payload = {};
  }

  var title = String(payload.title || 'Новое уведомление');
  var options = {
    body: String(payload.body || ''),
    icon: '/web/assets/favicon.svg',
    badge: '/web/assets/favicon.svg',
    data: {
      link: String(payload.link || 'index.php?route=notifications'),
      notification_public_id: String(payload.notification_public_id || '')
    },
    tag: String(payload.notification_public_id || ('push-' + Date.now()))
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var link = event.notification && event.notification.data && event.notification.data.link
    ? String(event.notification.data.link)
    : 'index.php?route=notifications';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientsArr) {
      for (var i = 0; i < clientsArr.length; i += 1) {
        var client = clientsArr[i];
        if (client && 'focus' in client) {
          client.navigate(link);
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(link);
      }
      return Promise.resolve();
    })
  );
});

