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

/**
 * Page-load retry: when the CRM web entry point (web/index.php) answers with a
 * server error (500/502/503/504) or the connection drops, the navigation is
 * re-requested a few times with a growing delay instead of immediately showing
 * the browser's error page. On shared hosting this hides transient PHP-FPM
 * hiccups, ondemand pool cold starts and nginx 5xx blips without any server
 * configuration — and it works even when PHP never ran (nginx 502/503).
 *
 * Only top-level GET navigations inside the /web/ scope are intercepted; API
 * fetches (mode 'cors'/'same-origin') and static assets are passed through.
 */
var PAGE_RETRY_ATTEMPTS = 3;
var PAGE_RETRY_DELAYS_MS = [3000, 5000, 8000];

function pageRetrySleep(ms) {
  return new Promise(function (resolve) {
    setTimeout(resolve, Math.max(0, Number(ms) || 0));
  });
}

function pageRetryIsServerError(status) {
  var code = Number(status) || 0;
  return code >= 500 && code <= 599;
}

function pageRetryPickLanguage(request) {
  var header = request && request.headers ? String(request.headers.get('Accept-Language') || '') : '';
  return header.toLowerCase().indexOf('ru') === 0 ? 'ru' : 'en';
}

function pageRetryFallbackHtml(request) {
  var lang = pageRetryPickLanguage(request);
  var messages = lang === 'ru'
    ? {
        title: 'Сервер временно недоступен',
        heading: 'Сервер временно недоступен',
        body: 'Не удалось загрузить страницу после нескольких попыток. Проверьте подключение к интернету или попробуйте ещё раз через несколько секунд.',
        button: 'Попробовать снова'
      }
    : {
        title: 'Server temporarily unavailable',
        heading: 'Server temporarily unavailable',
        body: 'The page could not be loaded after several attempts. Check your internet connection or try again in a few seconds.',
        button: 'Try again'
      };

  var url = request ? request.url : location.href;
  // The URL is embedded inside an inline <script> in this HTML. Escape it for
  // BOTH the HTML context (a query string containing </script> would break out
  // of the tag) and the JS string context (quotes, backslash). \u003c etc. are
  // decoded by the JS parser after the HTML parser has already seen the text,
  // so this closes the script-breakout vector completely.
  var retryUrl = String(url)
    .replace(/\\/g, '\\\\')
    .replace(/\\"/g, '\\\\"')
    .replace(/'/g, "\\'")
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026');

  return '<!doctype html><html lang="' + lang + '"><head><meta charset="UTF-8">'
    + '<meta name="viewport" content="width=device-width, initial-scale=1">'
    + '<title>' + messages.title + '</title>'
    + '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f6f8;color:#1f2937;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;box-sizing:border-box}'
    + '.card{max-width:440px;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:32px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06)}'
    + 'h1{font-size:20px;margin:0 0 12px}p{font-size:14px;line-height:1.55;color:#4b5563;margin:0 0 24px}'
    + 'button{appearance:none;border:0;background:#2563eb;color:#fff;font-size:14px;font-weight:600;padding:10px 22px;border-radius:8px;cursor:pointer}'
    + 'button:hover{background:#1d4ed8}button:disabled{opacity:.6;cursor:default}</style></head>'
    + '<body><div class="card"><h1>' + messages.heading + '</h1><p>' + messages.body + '</p>'
    + '<button id="retryBtn" type="button">' + messages.button + '</button></div>'
    + '<script>document.getElementById("retryBtn").addEventListener("click",function(){'
    + 'document.getElementById("retryBtn").disabled=true;window.location.href="' + retryUrl + '";});</script>'
    + '</body></html>';
}

function pageRetryNavigation(request) {
  var attempt = 0;

  function attemptOnce() {
    attempt += 1;
    return fetch(request).then(function (response) {
      if (pageRetryIsServerError(response.status) && attempt < PAGE_RETRY_ATTEMPTS) {
        return pageRetrySleep(PAGE_RETRY_DELAYS_MS[attempt - 1]).then(attemptOnce);
      }
      // After exhausting the retries we pass the real 5xx response through:
      // the CRM serves its own recoverable 500 page (index.php catch) and an
      // intentional 503 maintenance page, both of which explain what is
      // happening — hiding them behind a generic fallback would be worse.
      return response;
    }).catch(function () {
      // Network-level failure (connection drop, DNS, TLS) — there is no real
      // response body to show, so fall back to the retry page. Re-try a few
      // times first; the fetch() inside a service worker never auto-follows
      // the original request's redirect mode, so this is a clean re-request.
      if (attempt < PAGE_RETRY_ATTEMPTS) {
        return pageRetrySleep(PAGE_RETRY_DELAYS_MS[attempt - 1]).then(attemptOnce);
      }
      return new Response(pageRetryFallbackHtml(request), {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
      });
    });
  }

  return attemptOnce();
}

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (!request) return;
  if (request.method !== 'GET') return;
  if (request.mode !== 'navigate') return;

  // Only same-origin page loads inside the web app scope.
  var requestUrl = null;
  try {
    requestUrl = new URL(request.url);
  } catch (e) {
    return;
  }
  if (requestUrl.origin !== self.location.origin) return;
  if (requestUrl.pathname.indexOf('/web/') === -1 && requestUrl.pathname.indexOf('/web') !== 0) return;

  event.respondWith(pageRetryNavigation(request));
});
