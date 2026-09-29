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
