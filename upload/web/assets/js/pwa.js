/**
 * PWA bootstrap (window.CRM.pwa).
 *
 * Registers the app service worker (/web/push-sw.js, the same worker that also
 * handles push notifications and the page-load retry) on every protected page
 * so the app shell is precached and static assets are served from the cache.
 * The worker URL carries the ?v= asset version, so every deploy installs the
 * fresh worker automatically — no manual "update" step needed.
 *
 * Also powers the "Install app" button on the profile page: listens for the
 * browser's beforeinstallprompt event (Chrome/Edge/Android) and prompts the
 * install dialog when the user clicks it. On browsers without the event
 * (Safari iOS etc.) the button simply stays hidden and the user can use the
 * native "Add to Home Screen" menu — no button, no confusion.
 */
window.CRM.pwa = (function () {
  var started = false;
  var deferredPrompt = null;

  function isProtectedPage() {
    var body = document.body;
    return !!(body && body.getAttribute('data-protected') === '1');
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    try {
      var cfg = window.CRM && window.CRM.config ? window.CRM.config : {};
      var version = String((cfg.assetsVersion || '') || '').trim();
      var swUrl = '/web/push-sw.js';
      if (version) {
        swUrl += '?v=' + encodeURIComponent(version);
      }
      return navigator.serviceWorker.register(swUrl, { scope: '/web/' });
    } catch (e) {
      return null;
    }
  }

  function installButton() {
    return document.getElementById('profileInstallAppBtn');
  }

  function showInstallButton() {
    var btn = installButton();
    if (btn) btn.classList.remove('d-none');
  }

  function hideInstallButton() {
    var btn = installButton();
    if (btn) btn.classList.add('d-none');
  }

  function bindInstallButton() {
    var btn = installButton();
    if (!btn || btn.dataset.pwaBound === '1') return;
    btn.dataset.pwaBound = '1';
    btn.addEventListener('click', function () {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () {
        deferredPrompt = null;
        hideInstallButton();
      });
    });
  }

  function start() {
    if (started) return;
    if (!isProtectedPage()) return;
    started = true;

    bindInstallButton();
    registerServiceWorker();

    window.addEventListener('beforeinstallprompt', function (event) {
      event.preventDefault();
      deferredPrompt = event;
      showInstallButton();
    });

    window.addEventListener('appinstalled', function () {
      deferredPrompt = null;
      hideInstallButton();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  return {
    start: start,
    registerServiceWorker: registerServiceWorker
  };
})();
