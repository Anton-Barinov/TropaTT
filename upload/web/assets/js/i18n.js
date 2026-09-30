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
      var fallback = node.getAttribute('data-i18n-fallback') || '';
      var translated = getByPath(getMessages(), key);

      // If translation is missing in client dictionary and no fallback is given,
      // do not overwrite server-rendered HTML text with a raw string key
      if (typeof translated !== 'string' && (!fallback || fallback === '')) {
        if (node.textContent && node.textContent.trim() !== '') {
          return;
        }
      }
      var value = typeof translated === 'string' ? translated : (fallback || String(key || ''));

      if (/<[a-z][\s\S]*>/i.test(value)) {
        node.innerHTML = value;
        return;
      }

      // Если элемент содержит дочерние теги (иконки, бейджи, счетчики, ссылки, звездочки)
      if (node.children.length > 0) {
        var updated = false;
        for (var i = 0; i < node.childNodes.length; i++) {
          var child = node.childNodes[i];
          if (child.nodeType === Node.TEXT_NODE && child.textContent.trim() !== '') {
            child.textContent = (i === 0) ? value + ' ' : ' ' + value;
            updated = true;
            break;
          }
        }
        if (!updated) {
          // Контейнер может показывать подпись через дочерний элемент
          // (<button data-i18n="…"><span>Создать задачу</span></button>): если
          // элемент уже отображает этот текст, второй раз его не добавляем.
          var existing = String(node.textContent || '').replace(/\s+/g, ' ').trim();
          if (existing !== '' && existing === String(value).replace(/\s+/g, ' ').trim()) {
            return;
          }
          // Если явного текстового узла не было, добавляем текст в начало перед дочерними тегами
          node.insertAdjacentText('afterbegin', value + ' ');
        }
      } else {
        // Простой элемент без дочерних тегов — безопасная замена текста
        node.textContent = value;
      }
    });

    base.querySelectorAll('[data-i18n-placeholder]').forEach(function (node) {
      var key = node.getAttribute('data-i18n-placeholder') || '';
      var fallback = node.getAttribute('placeholder') || '';
      var translated = getByPath(getMessages(), key);
      if (typeof translated !== 'string' && (!fallback || fallback === '')) {
        return;
      }
      node.setAttribute('placeholder', typeof translated === 'string' ? translated : (fallback || String(key || '')));
    });

    base.querySelectorAll('[data-i18n-title]').forEach(function (node) {
      var key = node.getAttribute('data-i18n-title') || '';
      var fallback = node.getAttribute('title') || '';
      var translated = getByPath(getMessages(), key);
      if (typeof translated !== 'string' && (!fallback || fallback === '')) {
        return;
      }
      node.setAttribute('title', typeof translated === 'string' ? translated : (fallback || String(key || '')));
    });

    base.querySelectorAll('[data-i18n-aria-label]').forEach(function (node) {
      var key = node.getAttribute('data-i18n-aria-label') || '';
      var fallback = node.getAttribute('aria-label') || '';
      var translated = getByPath(getMessages(), key);
      if (typeof translated !== 'string' && (!fallback || fallback === '')) {
        return;
      }
      node.setAttribute('aria-label', typeof translated === 'string' ? translated : (fallback || String(key || '')));
    });
  }

  function safeIntlLocale(locale, fallback) {
    var def = fallback || 'ru-RU';
    var raw = String(locale || (window.CRM && (window.CRM.locale || window.CRM.currentLocale)) || document.documentElement.lang || def).replace('_', '-').trim();
    if (!raw) return def;
    var lower = raw.toLowerCase();
    if (lower === 'ru-old' || lower === 'ru-su' || lower.indexOf('ru-') === 0 || lower === 'ru') {
      return 'ru-RU';
    }
    if (lower.indexOf('en-') === 0 || lower === 'en') {
      return 'en-GB';
    }
    if (lower.indexOf('zh-') === 0 || lower === 'zh' || lower === 'cn') {
      return 'zh-CN';
    }
    if (lower.indexOf('he-') === 0 || lower === 'he' || lower === 'iw') {
      return 'he-IL';
    }
    try {
      if (typeof Intl !== 'undefined' && Intl.DateTimeFormat && typeof Intl.DateTimeFormat.supportedLocalesOf === 'function') {
        var supported = Intl.DateTimeFormat.supportedLocalesOf([raw]);
        if (supported && supported.length > 0) {
          return supported[0];
        }
      }
    } catch (e) {
      // Structurally invalid tag rejected by Intl
    }
    return def;
  }

  function formatDate(date, options, locale) {
    var safeLoc = safeIntlLocale(locale, 'ru-RU');
    var d = (date instanceof Date) ? date : new Date(date);
    if (!Number.isFinite(d.getTime())) return '—';
    try {
      return d.toLocaleDateString(safeLoc, options);
    } catch (e) {
      try {
        return d.toLocaleDateString('ru-RU', options);
      } catch (e2) {
        return d.toISOString().slice(0, 10);
      }
    }
  }

  function formatTime(date, options, locale) {
    var safeLoc = safeIntlLocale(locale, 'ru-RU');
    var d = (date instanceof Date) ? date : new Date(date);
    if (!Number.isFinite(d.getTime())) return '—';
    try {
      return d.toLocaleTimeString(safeLoc, options);
    } catch (e) {
      try {
        return d.toLocaleTimeString('ru-RU', options);
      } catch (e2) {
        return d.toTimeString().slice(0, 5);
      }
    }
  }

  function formatDateTime(date, options, locale) {
    var safeLoc = safeIntlLocale(locale, 'ru-RU');
    var d = (date instanceof Date) ? date : new Date(date);
    if (!Number.isFinite(d.getTime())) return '—';
    try {
      return d.toLocaleString(safeLoc, options);
    } catch (e) {
      try {
        return d.toLocaleString('ru-RU', options);
      } catch (e2) {
        return d.toISOString().replace('T', ' ').slice(0, 16);
      }
    }
  }

  function init() {
    if (window.CRM && window.CRM.locale) {
      var normalized = String(window.CRM.locale || '').toLowerCase();
      if (normalized) document.documentElement.lang = normalized.split('-')[0];
    }
    applyToDom(document);
  }

  var api = {
    t: t,
    applyToDom: applyToDom,
    init: init,
    safeIntlLocale: safeIntlLocale,
    formatDate: formatDate,
    formatTime: formatTime,
    formatDateTime: formatDateTime
  };

  if (window.CRM) {
    window.CRM.safeIntlLocale = safeIntlLocale;
  }

  return api;
})();

