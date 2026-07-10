window.CRM = window.CRM || {};
window.CRM.notificationsRealtime = (function () {
  var source = null;
  var reconnectTimer = null;
  var reconnectAttempt = 0;
  var pollTimer = null;
  var started = false;
  var lastEventId = 0;
  var recentNotificationIds = new Set();
  var refreshLock = false;
  var lifecycleBound = false;
  var audioCtx = null;
  var audioUnlocked = false;
  var SOUND_KEY = 'crm_notifications_sound';
  var QUIET_HOURS_KEY = 'crm_notifications_quiet_hours';
  var LAST_SOUND_TS_KEY = 'crm_notifications_sound_last_ts';
  var LAST_SOUND_ID_KEY = 'crm_notifications_sound_last_id';
  var CHANNEL_MATRIX_KEY = 'crm_notifications_channel_matrix';
  var DEFAULT_CATEGORIES = ['tasks', 'projects', 'comments', 'mentions', 'approvals', 'reminders', 'sla', 'security', 'system'];
  var CRITICAL_CATEGORIES = ['security'];
  var POLL_INTERVAL_VISIBLE_MS = 45000;
  var POLL_INTERVAL_HIDDEN_MS = 120000;

  function isProtectedPage() {
    var body = document.body;
    return !!(body && body.getAttribute('data-protected') === '1');
  }

  function streamUrl() {
    var base = window.CRM && window.CRM.config && window.CRM.config.apiBaseUrl
      ? String(window.CRM.config.apiBaseUrl)
      : (window.location.protocol + '//' + window.location.host + '/api/index.php');
    var url = new URL(base, window.location.origin);
    url.searchParams.set('route', 'api/v1/events/stream');
    if (lastEventId > 0) {
      url.searchParams.set('after_id', String(lastEventId));
    }
    return url.toString();
  }

  function notify(text, type) {
    if (window.CRM && window.CRM.br1 && typeof window.CRM.br1.notify === 'function') {
      window.CRM.br1.notify(String(text || ''), type);
      return;
    }
    if (typeof window.notify === 'function') {
      window.notify(String(text || ''), type);
    }
  }

  function normalizeCategory(category) {
    var raw = String(category || '').trim().toLowerCase();
    return raw || 'system';
  }

  function defaultChannelMatrix() {
    var matrix = {};
    DEFAULT_CATEGORIES.forEach(function (category) {
      matrix[category] = { in_app: true, sound: true, push: true };
    });
    CRITICAL_CATEGORIES.forEach(function (category) {
      if (!matrix[category]) matrix[category] = { in_app: true, sound: true, push: true };
      matrix[category].in_app = true;
    });
    return matrix;
  }

  function getChannelMatrix() {
    try {
      var raw = window.localStorage.getItem(CHANNEL_MATRIX_KEY);
      if (!raw) return defaultChannelMatrix();
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return defaultChannelMatrix();
      var merged = defaultChannelMatrix();
      Object.keys(parsed).forEach(function (category) {
        var key = normalizeCategory(category);
        var row = parsed[category];
        if (!row || typeof row !== 'object') return;
        merged[key] = {
          in_app: row.in_app !== false,
          sound: row.sound !== false,
          push: row.push !== false
        };
      });
      CRITICAL_CATEGORIES.forEach(function (category) {
        merged[category].in_app = true;
      });
      return merged;
    } catch (e) {
      return defaultChannelMatrix();
    }
  }

  function setChannelMatrix(matrix) {
    var current = defaultChannelMatrix();
    if (matrix && typeof matrix === 'object') {
      Object.keys(matrix).forEach(function (category) {
        var key = normalizeCategory(category);
        var row = matrix[category];
        if (!row || typeof row !== 'object') return;
        current[key] = {
          in_app: row.in_app !== false,
          sound: row.sound !== false,
          push: row.push !== false
        };
      });
    }
    CRITICAL_CATEGORIES.forEach(function (category) {
      current[category].in_app = true;
    });
    try {
      window.localStorage.setItem(CHANNEL_MATRIX_KEY, JSON.stringify(current));
    } catch (e) {
      void e;
    }
  }

  function isChannelEnabled(category, channel) {
    var normalizedCategory = normalizeCategory(category);
    var normalizedChannel = String(channel || '').trim().toLowerCase();
    if (!normalizedChannel) return true;
    if (normalizedChannel === 'in_app' && CRITICAL_CATEGORIES.indexOf(normalizedCategory) !== -1) {
      return true;
    }
    var matrix = getChannelMatrix();
    var row = matrix[normalizedCategory] || matrix.system || { in_app: true, sound: true, push: true };
    if (normalizedChannel === 'in_app') return row.in_app !== false;
    if (normalizedChannel === 'sound') return row.sound !== false;
    if (normalizedChannel === 'push') return row.push !== false;
    return true;
  }

  function isSoundEnabled() {
    try {
      var value = window.localStorage.getItem(SOUND_KEY);
      if (value === null || value === '') return true;
      return String(value).toLowerCase() !== 'off';
    } catch (e) {
      return true;
    }
  }

  function setSoundEnabled(enabled) {
    try {
      window.localStorage.setItem(SOUND_KEY, enabled ? 'on' : 'off');
    } catch (e) {
      void e;
    }
  }

  function getQuietHours() {
    try {
      var raw = window.localStorage.getItem(QUIET_HOURS_KEY);
      if (!raw) return { enabled: false, start: '22:00', end: '08:00', timezone: '' };
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') {
        return { enabled: false, start: '22:00', end: '08:00', timezone: '' };
      }
      return {
        enabled: !!parsed.enabled,
        start: /^\d{2}:\d{2}$/.test(String(parsed.start || '')) ? String(parsed.start) : '22:00',
        end: /^\d{2}:\d{2}$/.test(String(parsed.end || '')) ? String(parsed.end) : '08:00',
        timezone: String(parsed.timezone || '').trim()
      };
    } catch (e) {
      return { enabled: false, start: '22:00', end: '08:00', timezone: '' };
    }
  }

  function setQuietHours(config) {
    var normalized = {
      enabled: !!(config && config.enabled),
      start: config && /^\d{2}:\d{2}$/.test(String(config.start || '')) ? String(config.start) : '22:00',
      end: config && /^\d{2}:\d{2}$/.test(String(config.end || '')) ? String(config.end) : '08:00',
      timezone: config ? String(config.timezone || '').trim() : ''
    };
    try {
      window.localStorage.setItem(QUIET_HOURS_KEY, JSON.stringify(normalized));
    } catch (e) {
      void e;
    }
  }

  function parseHm(value, fallbackMinutes) {
    var raw = String(value || '').trim();
    var m = raw.match(/^(\d{2}):(\d{2})$/);
    if (!m) return fallbackMinutes;
    var hours = Number(m[1]);
    var mins = Number(m[2]);
    if (!Number.isFinite(hours) || !Number.isFinite(mins) || hours < 0 || hours > 23 || mins < 0 || mins > 59) {
      return fallbackMinutes;
    }
    return hours * 60 + mins;
  }

  function currentMinutesForTimezone(tz) {
    var date = new Date();
    var timezone = String(tz || '').trim();
    if (!timezone) {
      return date.getHours() * 60 + date.getMinutes();
    }
    try {
      var parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        hourCycle: 'h23',
        hour: '2-digit',
        minute: '2-digit'
      }).formatToParts(date);
      var hour = 0;
      var minute = 0;
      parts.forEach(function (part) {
        if (part.type === 'hour') hour = Number(part.value || 0);
        if (part.type === 'minute') minute = Number(part.value || 0);
      });
      if (!Number.isFinite(hour) || !Number.isFinite(minute)) {
        return date.getHours() * 60 + date.getMinutes();
      }
      return hour * 60 + minute;
    } catch (e) {
      return date.getHours() * 60 + date.getMinutes();
    }
  }

  function isWithinQuietHours() {
    var config = getQuietHours();
    if (!config.enabled) return false;
    var start = parseHm(config.start, 22 * 60);
    var end = parseHm(config.end, 8 * 60);
    var now = currentMinutesForTimezone(config.timezone);
    if (start === end) return true;
    if (start < end) return now >= start && now < end;
    return now >= start || now < end;
  }

  function markSoundPlayed(publicId) {
    var ts = Date.now();
    try {
      window.localStorage.setItem(LAST_SOUND_TS_KEY, String(ts));
      if (publicId) {
        window.localStorage.setItem(LAST_SOUND_ID_KEY, String(publicId));
      }
    } catch (e) {
      void e;
    }
  }

  function shouldSkipSound(publicId, category) {
    if (!isSoundEnabled()) return true;
    if (!isChannelEnabled(category, 'sound')) return true;
    if (isWithinQuietHours()) return true;
    try {
      var lastTs = Number(window.localStorage.getItem(LAST_SOUND_TS_KEY) || 0);
      var lastId = String(window.localStorage.getItem(LAST_SOUND_ID_KEY) || '');
      var now = Date.now();
      if (publicId && lastId && lastId === String(publicId) && (now - lastTs) < 120000) {
        return true;
      }
      if ((now - lastTs) < 500) {
        return true;
      }
    } catch (e) {
      void e;
    }
    return false;
  }

  function ensureAudioUnlocked() {
    if (audioUnlocked) return;
    audioUnlocked = true;
    var unlock = function () {
      try {
        if (!audioCtx && (window.AudioContext || window.webkitAudioContext)) {
          var Ctx = window.AudioContext || window.webkitAudioContext;
          audioCtx = new Ctx();
        }
        if (audioCtx && typeof audioCtx.resume === 'function') {
          audioCtx.resume();
        }
      } catch (e) {
        void e;
      }
      window.removeEventListener('pointerdown', unlock, true);
      window.removeEventListener('keydown', unlock, true);
      window.removeEventListener('touchstart', unlock, true);
    };

    window.addEventListener('pointerdown', unlock, true);
    window.addEventListener('keydown', unlock, true);
    window.addEventListener('touchstart', unlock, true);
  }

  function playNotificationSound(publicId, category) {
    if (shouldSkipSound(publicId, category)) return false;
    try {
      if (!audioCtx && (window.AudioContext || window.webkitAudioContext)) {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        audioCtx = new Ctx();
      }
      if (!audioCtx) return;
      if (audioCtx.state === 'suspended' && typeof audioCtx.resume === 'function') {
        audioCtx.resume();
      }
      if (audioCtx.state !== 'running') return false;

      var now = audioCtx.currentTime;
      var osc = audioCtx.createOscillator();
      var gain = audioCtx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, now);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.exponentialRampToValueAtTime(0.09, now + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.24);
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start(now);
      osc.stop(now + 0.25);
      markSoundPlayed(publicId || '');
      return true;
    } catch (e) {
      void e;
      return false;
    }
  }

  var _lastUiRefresh = 0;
  var _uiRefreshDebounceMs = 5000;

  function scheduleUiRefresh() {
    var now = Date.now();
    if (now - _lastUiRefresh < _uiRefreshDebounceMs) return;
    _lastUiRefresh = now;
    if (refreshLock) return;
    refreshLock = true;
    window.setTimeout(async function () {
      try {
        if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.refreshNotificationsWidgets === 'function') {
          await window.CRM.pageApiBindings.refreshNotificationsWidgets();
        }
        if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.refreshNotificationsCenterIfActive === 'function') {
          await window.CRM.pageApiBindings.refreshNotificationsCenterIfActive();
        }
      } catch (e) {
        void e;
      } finally {
        refreshLock = false;
      }
    }, 120);
  }

  function registerSeenNotification(publicId) {
    var id = String(publicId || '').trim();
    if (!id) return false;
    if (recentNotificationIds.has(id)) return false;
    recentNotificationIds.add(id);
    if (recentNotificationIds.size > 200) {
      var arr = Array.from(recentNotificationIds);
      recentNotificationIds = new Set(arr.slice(arr.length - 120));
    }
    return true;
  }

  function handleNotificationCreated(rawData, eventId) {
    if (eventId > 0) lastEventId = Math.max(lastEventId, eventId);
    var payload = rawData && typeof rawData === 'object' ? rawData : {};
    var item = payload.notification && typeof payload.notification === 'object' ? payload.notification : null;
    if (!item) {
      scheduleUiRefresh();
      return;
    }

    var wasNew = registerSeenNotification(item.public_id);
    if (wasNew) {
      var rawTitle = String(item.title || window.CRM.i18n.t('js.notify.new_notification', 'New notification'));
      var title = rawTitle;
      if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.notificationText === 'function') {
        title = window.CRM.pageApiBindings.notificationText(item.title, rawTitle) || rawTitle;
      }
      var category = normalizeCategory(item.category || 'system');
      if (isChannelEnabled(category, 'in_app')) {
        notify(title);
      }
      playNotificationSound(item.public_id || '', category);
      if (window.CRM.notificationsPush && typeof window.CRM.notificationsPush.handleNotificationCreated === 'function') {
        window.CRM.notificationsPush.handleNotificationCreated(item);
      }
    }

    scheduleUiRefresh();
  }

  function handleNotificationState(rawData) {
    var payload = rawData && typeof rawData === 'object' ? rawData : {};
    if (payload && (payload.type === 'notification.state' || payload.type === 'notification.updated')) {
      scheduleUiRefresh();
    }
  }

  function parseEventPayload(evt) {
    if (!evt || typeof evt.data !== 'string') return null;
    try {
      return JSON.parse(evt.data);
    } catch (e) {
      return null;
    }
  }

  function parseEventId(raw) {
    var value = String(raw || '').trim();
    if (!value) return 0;
    var match = value.match(/(\d+)/);
    if (!match) return 0;
    return Number(match[1] || 0) || 0;
  }

  function stopPolling() {
    if (pollTimer) {
      window.clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function isSseEnabled() {
    var config = window.CRM && window.CRM.config ? window.CRM.config : {};
    return String(config.realtimeTransport || 'poll').toLowerCase() === 'sse';
  }

  function pollIntervalMs() {
    return document.visibilityState === 'visible'
      ? POLL_INTERVAL_VISIBLE_MS
      : POLL_INTERVAL_HIDDEN_MS;
  }

  function ensurePollingFallback(immediate) {
    if (pollTimer) return;
    if (window.CRM && window.CRM.tabLeader && !window.CRM.tabLeader.isLeader()) return;
    if (immediate) {
      scheduleUiRefresh();
    }
    var poll = function () {
      if (window.CRM && window.CRM.tabLeader && !window.CRM.tabLeader.isLeader()) return;
      scheduleUiRefresh();
      pollTimer = window.setTimeout(poll, pollIntervalMs());
    };
    pollTimer = window.setTimeout(poll, pollIntervalMs());
  }

  function closeSource() {
    if (source) {
      source.close();
      source = null;
    }
  }

  function reconnectDelayMs(attempt) {
    if (attempt <= 1) return 1000;
    if (attempt === 2) return 2000;
    if (attempt === 3) return 5000;
    if (attempt <= 5) return 10000;
    return 30000;
  }

  function scheduleReconnect() {
    if (reconnectTimer) return;
    reconnectAttempt += 1;
    var delay = reconnectDelayMs(reconnectAttempt);
    reconnectTimer = window.setTimeout(function () {
      reconnectTimer = null;
      connect();
    }, delay);
  }

  function connect() {
    if (!started || !isProtectedPage()) return;
    if (window.CRM && window.CRM.tabLeader && !window.CRM.tabLeader.isLeader()) {
      ensurePollingFallback(true);
      return;
    }
    if (!isSseEnabled() || !window.EventSource) {
      ensurePollingFallback(true);
      return;
    }

    closeSource();

    try {
      source = new EventSource(streamUrl(), { withCredentials: true });
    } catch (e) {
      ensurePollingFallback(true);
      scheduleReconnect();
      return;
    }

    source.addEventListener('stream.ready', function () {
      reconnectAttempt = 0;
      stopPolling();
    });

    source.addEventListener('notification.created', function (evt) {
      var eventId = parseEventId(evt.lastEventId);
      var payload = parseEventPayload(evt);
      handleNotificationCreated(payload, eventId);
    });

    source.addEventListener('notification.state', function (evt) {
      var payload = parseEventPayload(evt);
      handleNotificationState(payload);
    });

    source.addEventListener('notification.updated', function (evt) {
      var payload = parseEventPayload(evt);
      handleNotificationState(payload);
    });

    source.addEventListener('stream.rotate', function () {
      closeSource();
      scheduleReconnect();
    });

    source.addEventListener('ping', function () {
      reconnectAttempt = 0;
    });

    source.onerror = function () {
      closeSource();
      if (window.CRM && window.CRM.tabLeader && !window.CRM.tabLeader.isLeader()) return;
      ensurePollingFallback(true);
      scheduleReconnect();
    };
  }

  function startLeaderAware() {
    if (window.CRM && window.CRM.tabLeader) {
      if (window.CRM.tabLeader.isLeader()) {
        connect();
      }
    } else {
      connect();
    }
  }

  function leaderBecameLeader() {
    if (!isProtectedPage()) return;
    started = true;
    ensureAudioUnlocked();
    connect();
  }

  function leaderLostLeader() {
    closeSource();
    stopPolling();
    if (reconnectTimer) {
      window.clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
    reconnectAttempt = 0;
  }

  function start() {
    if (started) return;
    if (!isProtectedPage()) return;
    started = true;
    ensureAudioUnlocked();
    if (window.CRM && window.CRM.tabLeader) {
      window.CRM.tabLeader.onBecomeLeader(leaderBecameLeader);
      window.CRM.tabLeader.onLoseLeader(leaderLostLeader);
    }
    startLeaderAware();
  }

  function stop() {
    started = false;
    closeSource();
    stopPolling();
    if (reconnectTimer) {
      window.clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
  }

  function bindLifecycle() {
    if (lifecycleBound) return;
    lifecycleBound = true;

    window.addEventListener('pagehide', function () {
      stop();
    });
    window.addEventListener('beforeunload', function () {
      stop();
    });
    document.addEventListener('visibilitychange', function () {
      if (!started || isSseEnabled()) return;
      stopPolling();
      ensurePollingFallback(true);
    });
  }

  return {
    start: function () {
      bindLifecycle();
      start();
    },
    stop: stop,
    connect: connect,
    isSoundEnabled: isSoundEnabled,
    setSoundEnabled: setSoundEnabled,
    getQuietHours: getQuietHours,
    setQuietHours: setQuietHours,
    isWithinQuietHours: isWithinQuietHours,
    getChannelMatrix: getChannelMatrix,
    setChannelMatrix: setChannelMatrix,
    isChannelEnabled: isChannelEnabled,
    playTestSound: function () {
      ensureAudioUnlocked();
      return playNotificationSound('test-sound', 'system');
    }
  };
})();
