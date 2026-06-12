window.CRM = window.CRM || {};
window.CRM.notificationsPush = (function () {
  var started = false;
  var swRegistration = null;
  var recentIds = new Set();
  var SETTINGS_KEY = 'crm_push_notifications_enabled';

  function isProtectedPage() {
    var body = document.body;
    return !!(body && body.getAttribute('data-protected') === '1');
  }

  function isEnabled() {
    try {
      var raw = window.localStorage.getItem(SETTINGS_KEY);
      if (raw === null || raw === '') return true;
      return String(raw).toLowerCase() !== 'off';
    } catch (e) {
      return true;
    }
  }

  function setEnabled(enabled) {
    try {
      window.localStorage.setItem(SETTINGS_KEY, enabled ? 'on' : 'off');
    } catch (e) {
      void e;
    }
  }

  function isVisibleNow() {
    return document.visibilityState === 'visible';
  }

  function apiRequest(route, options) {
    if (!window.CRM.api || typeof window.CRM.api.request !== 'function') {
      return Promise.resolve(null);
    }
    return window.CRM.api.request(route, options || {});
  }

  function registerSeen(publicId) {
    var id = String(publicId || '').trim();
    if (!id) return false;
    if (recentIds.has(id)) return false;
    recentIds.add(id);
    if (recentIds.size > 150) {
      var arr = Array.from(recentIds);
      recentIds = new Set(arr.slice(arr.length - 100));
    }
    return true;
  }

  function base64UrlToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(base64);
    var output = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i += 1) {
      output[i] = raw.charCodeAt(i);
    }
    return output;
  }

  function getConfiguredVapidPublicKey() {
    var cfg = window.CRM && window.CRM.config ? window.CRM.config : {};
    var key = String((cfg.pushVapidPublicKey || cfg.push_vapid_public_key || '') || '').trim();
    return key;
  }

  async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    try {
      var cfg = window.CRM && window.CRM.config ? window.CRM.config : {};
      var version = String((cfg.assetsVersion || '') || '').trim();
      var swUrl = '/web/push-sw.js';
      if (version) {
        swUrl += '?v=' + encodeURIComponent(version);
      }
      swRegistration = await navigator.serviceWorker.register(swUrl, { scope: '/web/' });
      return swRegistration;
    } catch (e) {
      return null;
    }
  }

  async function syncSubscriptionWithApi(registration) {
    if (!registration || !registration.pushManager) return;
    if (!isEnabled()) return;
    if (Notification.permission !== 'granted') return;

    try {
      var sub = await registration.pushManager.getSubscription();
      if (!sub) {
        var vapid = getConfiguredVapidPublicKey();
        if (!vapid) return;
        sub = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: base64UrlToUint8Array(vapid)
        });
      }
      if (!sub) return;
      var json = sub.toJSON ? sub.toJSON() : {};
      var keys = json.keys || {};
      await apiRequest('api/v1/notifications/push-subscriptions', {
        method: 'POST',
        body: {
          endpoint: String(json.endpoint || ''),
          p256dh: String(keys.p256dh || ''),
          auth: String(keys.auth || ''),
          user_agent: String(navigator.userAgent || ''),
          device_label: String(navigator.platform || '')
        }
      });
    } catch (e) {
      void e;
    }
  }

  function buildNotificationPayload(item) {
    var title = String(item && item.title ? item.title : window.CRM.i18n.t('js.notify.new_notification', 'New notification'));
    var body = String(item && item.body ? item.body : '');
    var link = String(item && item.link ? item.link : 'index.php?route=notifications');
    return {
      title: title,
      options: {
        body: body,
        icon: '/web/assets/favicon.svg',
        badge: '/web/assets/favicon.svg',
        tag: String(item && item.public_id ? item.public_id : ('ntf-' + Date.now())),
        renotify: false,
        data: {
          link: link,
          notification_public_id: String(item && item.public_id ? item.public_id : '')
        }
      }
    };
  }

  async function showSystemNotification(item) {
    if (!isEnabled()) return;
    if (window.CRM.notificationsRealtime && typeof window.CRM.notificationsRealtime.isChannelEnabled === 'function') {
      var category = String(item && item.category || 'system');
      if (!window.CRM.notificationsRealtime.isChannelEnabled(category, 'push')) {
        return;
      }
    }
    if (Notification.permission !== 'granted') return;
    if (isVisibleNow()) return;

    var payload = buildNotificationPayload(item);
    if (swRegistration && typeof swRegistration.showNotification === 'function') {
      try {
        await swRegistration.showNotification(payload.title, payload.options);
        return;
      } catch (e) {
        void e;
      }
    }

    try {
      var native = new Notification(payload.title, payload.options);
      native.onclick = function () {
        if (payload.options && payload.options.data && payload.options.data.link) {
          window.location.href = String(payload.options.data.link);
        }
      };
    } catch (e2) {
      void e2;
    }
  }

  async function requestPermissionAndEnable() {
    if (!('Notification' in window)) return false;
    try {
      var permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        setEnabled(false);
        return false;
      }
      setEnabled(true);
      if (swRegistration) {
        await syncSubscriptionWithApi(swRegistration);
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function bindProfileToggle() {
    var toggle = document.getElementById('profileNotifyPushComments');
    if (!toggle || toggle.dataset.pushBound === '1') return;
    toggle.dataset.pushBound = '1';

    if (isEnabled()) {
      toggle.checked = true;
    }

    toggle.addEventListener('change', function () {
      if (!toggle.checked) {
        setEnabled(false);
        return;
      }
      requestPermissionAndEnable();
    });
  }

  async function start() {
    if (started) return;
    if (!isProtectedPage()) return;
    started = true;
    bindProfileToggle();
    var reg = await registerServiceWorker();
    if (reg) {
      await syncSubscriptionWithApi(reg);
    }
  }

  function handleNotificationCreated(item) {
    if (!registerSeen(item && item.public_id)) return;
    showSystemNotification(item);
  }

  return {
    start: start,
    handleNotificationCreated: handleNotificationCreated,
    requestPermissionAndEnable: requestPermissionAndEnable,
    isEnabled: isEnabled,
    setEnabled: setEnabled
  };
})();
