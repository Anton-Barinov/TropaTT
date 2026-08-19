window.CRM = window.CRM || {};
window.CRM.i18n = (function () {
  function getMessages() {
    return (window.CRM && window.CRM.messages && typeof window.CRM.messages === 'object') ? window.CRM.messages : {};
  }

  function getByPath(obj, key) {
    var value = obj;
    var parts = String(key || '').split('.');
    for (var i = 0; i < parts.length; i += 1) {
      if (!value || typeof value !== 'object' || !Object.prototype.hasOwnProperty.call(value, parts[i])) {
        return undefined;
      }
      value = value[parts[i]];
    }
    return value;
  }

  function t(key, fallback) {
    var value = getByPath(getMessages(), key);
    if (typeof value === 'string') return value;
    if (typeof fallback === 'string' && fallback !== '') return fallback;
    return String(key || '');
  }

  function applyToDom(root) {
    var base = root || document;

    base.querySelectorAll('[data-i18n]').forEach(function (node) {
      var key = node.getAttribute('data-i18n') || '';
      var fallback = node.getAttribute('data-i18n-fallback') || node.textContent || '';
      var value = t(key, fallback);
      if (/<[a-z][\s\S]*>/i.test(value)) {
        node.innerHTML = value;
      } else {
        node.textContent = value;
      }
    });

    base.querySelectorAll('[data-i18n-placeholder]').forEach(function (node) {
      var key = node.getAttribute('data-i18n-placeholder') || '';
      var fallback = node.getAttribute('placeholder') || '';
      node.setAttribute('placeholder', t(key, fallback));
    });

    base.querySelectorAll('[data-i18n-title]').forEach(function (node) {
      var key = node.getAttribute('data-i18n-title') || '';
      var fallback = node.getAttribute('title') || '';
      node.setAttribute('title', t(key, fallback));
    });

    base.querySelectorAll('[data-i18n-aria-label]').forEach(function (node) {
      var key = node.getAttribute('data-i18n-aria-label') || '';
      var fallback = node.getAttribute('aria-label') || '';
      node.setAttribute('aria-label', t(key, fallback));
    });
  }

  function init() {
    if (window.CRM && window.CRM.locale) {
      var normalized = String(window.CRM.locale || '').toLowerCase();
      if (normalized) document.documentElement.lang = normalized.split('-')[0];
    }
    applyToDom(document);
  }

  return {
    t: t,
    applyToDom: applyToDom,
    init: init
  };
})();
