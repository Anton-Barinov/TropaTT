window.CRM = window.CRM || {};
window.CRM.tabLeader = (function () {
  var LS_KEY = 'crm.tabLeader';
  var LS_EVENT_KEY = 'crm.tabLeader.event';
  var BC_NAME = 'crm-tab-leader';
  var HEARTBEAT_MS = 10000;
  var LEADER_TTL_MS = 30000;

  var tabId = generateId();
  var becomeLeaderCallbacks = [];
  var loseLeaderCallbacks = [];
  var messageListeners = {};
  var heartbeatTimer = null;
  var isLeaderFlag = false;
  var bc = null;
  var debug = false;
  var initDone = false;

  function generateId() {
    var p1 = Date.now().toString(36);
    var p2 = Math.random().toString(36).slice(2, 10);
    var p3 = Math.random().toString(36).slice(2, 6);
    return p1 + '-' + p2 + '-' + p3;
  }

  function dlog(msg) {
    if (debug || (window.DEBUG_TAB_LEADER === true)) {
      console.log('[TabLeader][' + tabId.slice(0, 8) + '] ' + msg);
    }
  }

  function safeGetItem(key) {
    try {
      return localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function safeSetItem(key, value) {
    try {
      localStorage.setItem(key, value);
      return true;
    } catch (e) {
      return false;
    }
  }

  function safeRemoveItem(key) {
    try {
      localStorage.removeItem(key);
    } catch (e) {}
  }

  function getLeader() {
    var raw = safeGetItem(LS_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function writeLeader(reason) {
    var data = {
      tabId: tabId,
      ts: Date.now(),
      reason: reason || ''
    };
    safeSetItem(LS_KEY, JSON.stringify(data));
    return data;
  }

  function clearLeader() {
    safeRemoveItem(LS_KEY);
  }

  function notifyBecomeLeader() {
    dlog('notifyBecomeLeader: ' + becomeLeaderCallbacks.length + ' callbacks');
    for (var i = 0; i < becomeLeaderCallbacks.length; i++) {
      try {
        becomeLeaderCallbacks[i]();
      } catch (e) {
        if (window.console) console.error('[TabLeader] onBecomeLeader callback error', e);
      }
    }
  }

  function notifyLoseLeader() {
    dlog('notifyLoseLeader: ' + loseLeaderCallbacks.length + ' callbacks');
    for (var i = 0; i < loseLeaderCallbacks.length; i++) {
      try {
        loseLeaderCallbacks[i]();
      } catch (e) {
        if (window.console) console.error('[TabLeader] onLoseLeader callback error', e);
      }
    }
  }

  function becomeLeader(reason) {
    if (isLeaderFlag) {
      writeLeader(reason);
      return;
    }
    isLeaderFlag = true;
    writeLeader(reason);
    dlog('Became leader: ' + reason);
    notifyBecomeLeader();
    broadcast('leader-changed', { tabId: tabId });
  }

  function stepDown(reason) {
    if (!isLeaderFlag) return;
    isLeaderFlag = false;
    clearLeader();
    dlog('Lost leadership: ' + reason);
    notifyLoseLeader();
    broadcast('leader-changed', { tabId: tabId, steppingDown: true });
  }

  function checkAndElect() {
    var leader = getLeader();
    var now = Date.now();

    if (!leader) {
      becomeLeader('no-leader');
      return;
    }

    if (leader.tabId === tabId) {
      if (!isLeaderFlag) {
        isLeaderFlag = true;
        dlog('Recognized as leader (existing record)');
        notifyBecomeLeader();
      }
      writeLeader('heartbeat');
      return;
    }

    var elapsed = now - Number(leader.ts || 0);
    if (elapsed > LEADER_TTL_MS) {
      becomeLeader('leader-stale-' + Math.floor(elapsed / 1000) + 's');
      return;
    }

    if (isLeaderFlag) {
      stepDown('overridden-by-' + leader.tabId);
    }
  }

  function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
      dlog('visibilitychange -> visible');
      checkAndElect();
    }
  }

  function onWindowFocus() {
    dlog('window focus');
    checkAndElect();
  }

  var _storageDebounceTimer = null;

  function onStorageEvent(e) {
    if (e.key === LS_KEY) {
      dlog('storage event: leader changed');
      if (_storageDebounceTimer) return;
      _storageDebounceTimer = window.setTimeout(function () {
        _storageDebounceTimer = null;
        checkAndElect();
      }, 200);
    }
    if (e.key === LS_EVENT_KEY && e.newValue) {
      handleMessageEvent(e.newValue);
    }
  }

  function handleMessageEvent(raw) {
    if (!raw) return;
    var msg;
    try {
      msg = JSON.parse(raw);
    } catch (e) {
      return;
    }
    if (!msg || !msg.type) return;
    if (msg.tabId === tabId) return;
    var listeners = messageListeners[msg.type];
    if (!listeners || !listeners.length) return;
    for (var i = 0; i < listeners.length; i++) {
      try {
        listeners[i](msg.payload || {}, msg);
      } catch (e) {
        if (window.console) console.error('[TabLeader] message callback error', e);
      }
    }
  }

  function onBcMessage(event) {
    var msg = event.data;
    if (!msg || !msg.type) return;
    if (msg.tabId === tabId) return;
    if (msg.type === 'leader-changed') {
      dlog('bc: leader changed, re-electing');
      checkAndElect();
    }
    var listeners = messageListeners[msg.type];
    if (!listeners || !listeners.length) return;
    for (var i = 0; i < listeners.length; i++) {
      try {
        listeners[i](msg.payload || {}, msg);
      } catch (e) {
        if (window.console) console.error('[TabLeader] bc callback error', e);
      }
    }
  }

  function broadcast(type, payload) {
    var msg = { type: type, tabId: tabId, ts: Date.now(), payload: payload };
    if (bc) {
      try { bc.postMessage(msg); } catch (e) {}
      return;
    }
    safeSetItem(LS_EVENT_KEY, JSON.stringify(msg));
  }

  function startHeartbeat() {
    stopHeartbeat();
    heartbeatTimer = window.setInterval(function () {
      if (isLeaderFlag) {
        writeLeader('heartbeat');
      } else {
        var leader = getLeader();
        if (!leader) {
          checkAndElect();
        } else if (leader.tabId !== tabId) {
          var elapsed = Date.now() - Number(leader.ts || 0);
          if (elapsed > LEADER_TTL_MS) {
            checkAndElect();
          }
        }
      }
    }, HEARTBEAT_MS);
  }

  function stopHeartbeat() {
    if (heartbeatTimer) {
      window.clearInterval(heartbeatTimer);
      heartbeatTimer = null;
    }
  }

  function initChannel() {
    if (typeof BroadcastChannel !== 'undefined') {
      try {
        bc = new BroadcastChannel(BC_NAME);
        bc.addEventListener('message', onBcMessage);
        dlog('BroadcastChannel initialized');
      } catch (e) {
        bc = null;
        dlog('BroadcastChannel failed');
      }
    }
    window.addEventListener('storage', onStorageEvent);
  }

  return {
    init: function () {
      if (initDone) return;
      initDone = true;
      initChannel();
      checkAndElect();
      startHeartbeat();
      document.addEventListener('visibilitychange', onVisibilityChange);
      window.addEventListener('focus', onWindowFocus);
      dlog('init complete, tabId=' + tabId);
    },

    isLeader: function () {
      return isLeaderFlag;
    },

    getTabId: function () {
      return tabId;
    },

    getLeader: function () {
      return getLeader();
    },

    onBecomeLeader: function (callback) {
      if (typeof callback !== 'function') return;
      becomeLeaderCallbacks.push(callback);
    },

    onLoseLeader: function (callback) {
      if (typeof callback !== 'function') return;
      loseLeaderCallbacks.push(callback);
    },

    onMessage: function (type, callback) {
      if (!type || typeof callback !== 'function') return;
      if (!messageListeners[type]) {
        messageListeners[type] = [];
      }
      messageListeners[type].push(callback);
    },

    broadcast: function (type, payload) {
      broadcast(type, payload);
    },

    setDebug: function (enabled) {
      debug = !!enabled;
    }
  };
})();
