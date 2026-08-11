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

function pageRetryReadCookie(request, name) {
  var header = request && request.headers ? String(request.headers.get('Cookie') || '') : '';
  var parts = header.split(';');
  for (var i = 0; i < parts.length; i += 1) {
    var eq = parts[i].indexOf('=');
    if (eq <= 0) continue;
    if (parts[i].slice(0, eq).trim().toLowerCase() === name) {
      try {
        return decodeURIComponent(parts[i].slice(eq + 1).trim());
      } catch (e) {
        return parts[i].slice(eq + 1).trim();
      }
    }
  }
  return '';
}

function pageRetryPickLanguage(request) {
  // The CRM stores the user's explicitly chosen locale in the crm_locale
  // cookie (set by api.js setPreferredLocale / the login language switch).
  // The intercepted navigation request carries that cookie, so the user's
  // choice wins over the browser's Accept-Language: a Russian CRM user whose
  // browser is English-first must still see the retry page in Russian.
  var cookie = pageRetryReadCookie(request, 'crm_locale').toLowerCase().replace(/_/g, '-');
  if (cookie.indexOf('ru') === 0) return 'ru';
  if (cookie.indexOf('en') === 0) return 'en';

  // Fallback: the browser's Accept-Language, honoured by q-priority so that
  // "en-US,en;q=0.9,ru;q=0.8" resolves to 'en' while "ru,en;q=0.8" → 'ru'.
  var header = request && request.headers ? String(request.headers.get('Accept-Language') || '') : '';
  var parts = header.split(',');
  var best = '';
  var bestQ = -1;
  for (var i = 0; i < parts.length; i += 1) {
    var tokens = parts[i].split(';');
    var tag = tokens[0].trim().toLowerCase();
    if (!tag) continue;
    var q = 1;
    if (tokens[1]) {
      var m = /q\s*=\s*([0-9.]+)/.exec(tokens[1]);
      q = m ? parseFloat(m[1]) : NaN;
      if (isNaN(q)) q = 0;
    }
    if (q > bestQ) {
      bestQ = q;
      best = tag;
    }
  }
  return best.indexOf('ru') === 0 ? 'ru' : 'en';
}

function pageRetryFallbackHtml(request, offline) {
  var lang = pageRetryPickLanguage(request);
  var messages;
  if (lang === 'ru') {
    messages = offline
      ? {
          title: 'Нет соединения с интернетом',
          heading: 'Вы офлайн',
          body: 'Нет подключения к интернету. Проверьте сеть и попробуйте снова.',
          button: 'Попробовать снова'
        }
      : {
          title: 'Сервер временно недоступен',
          heading: 'Сервер временно недоступен',
          body: 'Не удалось загрузить страницу после нескольких попыток. Проверьте подключение к интернету или попробуйте ещё раз через несколько секунд.',
          button: 'Попробовать снова'
        };
  } else {
    messages = offline
      ? {
          title: 'No internet connection',
          heading: 'You are offline',
          body: 'No internet connection. Check your network and try again.',
          button: 'Try again'
        }
      : {
          title: 'Server temporarily unavailable',
          heading: 'Server temporarily unavailable',
          body: 'The page could not be loaded after several attempts. Check your internet connection or try again in a few seconds.',
          button: 'Try again'
        };
  }

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
  // If the browser already reports that we are offline, do not burn the retry
  // budget (and the user's time): serve the offline page right away. The
  // server-retry path below is for transient server failures, not for a
  // missing network connection.
  if (self.navigator && self.navigator.onLine === false) {
    return Promise.resolve(new Response(pageRetryFallbackHtml(request, true), {
      status: 503,
      headers: { 'Content-Type': 'text/html; charset=utf-8' }
    }));
  }

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
      var offline = !!(self.navigator && self.navigator.onLine === false);
      return new Response(pageRetryFallbackHtml(request, offline), {
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

/**
 * ===== PWA: offline app-shell caching =====
 *
 * The app shell (CSS/JS/icons) is precached at install time and every static
 * asset under /web/assets/ is afterwards served stale-while-revalidate, so the
 * interface loads fast from the cache on repeat visits and stays usable during
 * short network drops. Nothing sensitive is ever cached: API calls, uploads
 * and the installer are always passed through to the network.
 *
 * Asset URLs carry the ?v= version query (see header.php assetsVersion), so
 * every deploy registers a fresh worker URL, precaches the new files and
 * prunes the old caches on activate. The runtime cache is bounded so it can
 * never grow without limit on long-lived installs.
 */
var PWA_RUNTIME_CACHE_LIMIT = 200;
var PWA_CACHE_PREFIX = 'crm-pwa-runtime';

function pwaVersionFromUrl() {
  var search = self.location && self.location.search ? String(self.location.search) : '';
  var m = /[?&]v=([^&]+)/.exec(search);
  return m ? decodeURIComponent(m[1]) : '';
}

function pwaCacheName(version) {
  var v = String(version || '') || pwaVersionFromUrl();
  return v === '' ? PWA_CACHE_PREFIX : PWA_CACHE_PREFIX + '-' + v;
}

function pwaPrecachePaths() {
  return [
    '/web/assets/favicon.svg',
    '/web/assets/icons/icon-192.png',
    '/web/assets/icons/icon-512.png',
    '/web/assets/css/bootstrap.min.css',
    '/web/assets/vendor/fontawesome/css/all.min.css',
    '/web/assets/css/tokens.css',
    '/web/assets/css/layout.css',
    '/web/assets/css/components.css',
    '/web/assets/css/pages.css',
    '/web/assets/css/animations.css',
    '/web/assets/css/responsive.css',
    '/web/assets/css/ui.css',
    '/web/assets/css/visual-editor.css',
    '/web/assets/css/themes.css',
    '/web/assets/vendor/bootstrap/bootstrap.bundle.min.js',
    '/web/assets/js/api.js',
    '/web/assets/js/i18n.js',
    '/web/assets/js/tab-leader.js',
    '/web/assets/js/navigation.js',
    '/web/assets/js/ui.js',
    '/web/assets/js/modals.js',
    '/web/assets/js/drawers.js',
    '/web/assets/js/tabs.js',
    '/web/assets/js/filters.js',
    '/web/assets/js/tables.js',
    '/web/assets/js/text-utils.js',
    '/web/assets/js/error-utils.js',
    '/web/assets/js/list-utils.js',
    '/web/assets/js/notifications.js',
    '/web/assets/js/visual-editor.js',
    '/web/assets/js/br1.js',
    '/web/assets/js/page-api-bindings.js',
    '/web/assets/js/app.js'
  ];
}

function pwaBuildAssetUrl(path, version) {
  var p = String(path || '');
  if (p.charAt(0) !== '/') p = '/' + p;
  var url = self.location.origin + p;
  var v = String(version || '') || pwaVersionFromUrl();
  return v === '' ? url : url + (url.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(v);
}

function pwaIsStaticAsset(urlString) {
  var url = null;
  try {
    url = new URL(urlString);
  } catch (e) {
    return false;
  }
  if (url.origin !== self.location.origin) return false;
  var path = url.pathname;
  if (path.indexOf('/api/') === 0) return false;      // API responses are never cached (sensitive)
  if (path.indexOf('/storage') === 0) return false;   // user uploads/downloads
  if (path.indexOf('/updater') === 0) return false;   // update packages
  if (path === '/web/install.php') return false;      // installer
  return path.indexOf('/web/assets/') === 0;          // versioned app-shell assets
}

function pwaTrimRuntimeCache(cache) {
  try {
    cache.keys().then(function (keys) {
      if (keys.length > PWA_RUNTIME_CACHE_LIMIT) {
        var excess = keys.slice(0, keys.length - PWA_RUNTIME_CACHE_LIMIT);
        for (var i = 0; i < excess.length; i += 1) {
          cache.delete(excess[i]);
        }
      }
    }).catch(function () {});
  } catch (e) {}
}

self.addEventListener('install', function (event) {
  if (!self.caches) return;
  var version = pwaVersionFromUrl();
  event.waitUntil(
    caches.open(pwaCacheName(version)).then(function (cache) {
      return Promise.all(pwaPrecachePaths().map(function (path) {
        return cache.add(pwaBuildAssetUrl(path, version)).catch(function () {
          // A single missing asset must not block activation; runtime
          // stale-while-revalidate fills the gap on the next load.
        });
      }));
    })
  );
});

self.addEventListener('activate', function (event) {
  var tasks = [self.clients.claim()];
  if (self.caches) {
    var keep = pwaCacheName();
    tasks.push(
      caches.keys().then(function (names) {
        return Promise.all(names.map(function (name) {
          if (name.indexOf(PWA_CACHE_PREFIX) === 0 && name !== keep) {
            return caches.delete(name);
          }
        }));
      })
    );
  }
  event.waitUntil(Promise.all(tasks));
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (!request || request.method !== 'GET') return;
  if (!pwaIsStaticAsset(request.url)) return;

  var cacheName = pwaCacheName();
  event.respondWith(
    caches.open(cacheName).then(function (cache) {
      return cache.match(request).then(function (cached) {
        var network = fetch(request).then(function (response) {
          if (response && response.status === 200 && response.type === 'basic') {
            cache.put(request, response.clone());
            pwaTrimRuntimeCache(cache);
          }
          return response;
        }).catch(function () {
          // Offline: fall back to the cached copy, if any.
          return cached;
        });
        return cached || network;
      });
    })
  );
});
