window.CRM = window.CRM || {};
window.CRM.richtext = (function () {
  var FIELD_MARKER = 'data-crm-richtext-ready';
  var WATCHERS = new WeakMap();

  function descriptionHint() {
    var translated = window.CRM.i18n && typeof window.CRM.i18n.t === 'function'
      ? window.CRM.i18n.t('richtext.description_hint', 'description')
      : 'description';
    return String(translated || 'description').toLowerCase();
  }

  function containsDescriptionHint(value) {
    var text = String(value || '').toLowerCase();
    if (!text) return false;
    var hints = ['description', descriptionHint()];
    return hints.some(function (hint) {
      return hint && text.indexOf(hint) !== -1;
    });
  }

  function hasDescriptionIntent(textarea) {
    var name = String(textarea.getAttribute('name') || '').toLowerCase();
    var id = String(textarea.id || '').toLowerCase();
    var placeholder = String(textarea.getAttribute('placeholder') || '').toLowerCase();
    if (containsDescriptionHint(name)) return true;
    if (containsDescriptionHint(id)) return true;
    if (containsDescriptionHint(placeholder)) return true;

    var wrapper = textarea.closest('.mb-2, .mb-3, .mb-4, .col-12, .col-md-6, .col-md-8, .col-lg-6, .col-lg-8, .crm-card, form');
    if (!wrapper) return false;
    var label = wrapper.querySelector('label');
    if (!label) return false;
    var labelText = String(label.textContent || '').toLowerCase();
    return containsDescriptionHint(labelText);
  }

  function shouldEnhance(textarea) {
    if (!textarea || textarea.tagName !== 'TEXTAREA') return false;
    if (textarea.hasAttribute(FIELD_MARKER)) return false;
    if (textarea.dataset.richtextOff === '1') return false;
    if (textarea.closest('[data-richtext-off="1"]')) return false;
    return hasDescriptionIntent(textarea);
  }

  function normalizeEmpty(html) {
    var compact = String(html || '')
      .replace(/<br\s*\/?>/gi, '')
      .replace(/&nbsp;/gi, '')
      .replace(/\s+/g, '');
    return compact ? html : '';
  }

  function sanitizeLinkHref(href) {
    var value = String(href || '').trim();
    if (!value) return '';
    if (value[0] === '#') return value;
    if (value[0] === '/') return value;
    if (/^https?:\/\//i.test(value)) return value;
    if (/^mailto:/i.test(value)) return value;
    return '';
  }

  function sanitizeHtml(rawHtml) {
    var allowed = {
      A: true,
      P: true,
      BR: true,
      STRONG: true,
      B: true,
      EM: true,
      I: true,
      U: true,
      S: true,
      UL: true,
      OL: true,
      LI: true,
      BLOCKQUOTE: true,
      H3: true,
      H4: true
    };
    var template = document.createElement('template');
    template.innerHTML = String(rawHtml || '');

    function walk(node) {
      var children = Array.prototype.slice.call(node.childNodes || []);
      children.forEach(function (child) {
        if (child.nodeType === Node.ELEMENT_NODE) {
          if (!allowed[child.tagName]) {
            var parent = child.parentNode;
            while (child.firstChild) {
              parent.insertBefore(child.firstChild, child);
            }
            parent.removeChild(child);
            return;
          }

          var attrs = Array.prototype.slice.call(child.attributes || []);
          attrs.forEach(function (attr) {
            var name = String(attr.name || '').toLowerCase();
            if (child.tagName !== 'A') {
              child.removeAttribute(attr.name);
              return;
            }
            if (name !== 'href' && name !== 'target' && name !== 'rel') {
              child.removeAttribute(attr.name);
            }
          });

          if (child.tagName === 'A') {
            var safeHref = sanitizeLinkHref(child.getAttribute('href'));
            if (!safeHref) {
              child.removeAttribute('href');
            } else {
              child.setAttribute('href', safeHref);
              child.setAttribute('target', '_blank');
              child.setAttribute('rel', 'noopener noreferrer');
            }
          }

          walk(child);
        } else if (child.nodeType === Node.COMMENT_NODE) {
          node.removeChild(child);
        }
      });
    }

    walk(template.content);
    return normalizeEmpty(template.innerHTML.trim());
  }

  function htmlFromTextareaValue(value) {
    var source = String(value || '').trim();
    if (!source) return '';
    if (/<[a-z][\s\S]*>/i.test(source)) {
      return sanitizeHtml(source);
    }
    return source
      .split('\n')
      .map(function (line) {
        var safe = String(line || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');
        return '<p>' + (safe || '<br>') + '</p>';
      })
      .join('');
  }

  function syncSource(textarea, editor) {
    var cleanHtml = sanitizeHtml(editor.innerHTML);
    textarea.value = cleanHtml;
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function syncEditor(textarea, editor) {
    editor.innerHTML = htmlFromTextareaValue(textarea.value || '');
  }

  function exec(command, value) {
    try {
      document.execCommand(command, false, value || null);
    } catch (e) {
      // ignore execCommand fallback issues
    }
  }

  function buildToolbar() {
    var toolbar = document.createElement('div');
    toolbar.className = 'crm-rte-toolbar';
    toolbar.innerHTML = ''
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="bold" title="' + window.CRM.i18n.t('richtext.bold', 'Bold') + '"><strong>B</strong></button>'
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="italic" title="' + window.CRM.i18n.t('richtext.italic', 'Italic') + '"><em>I</em></button>'
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="underline" title="' + window.CRM.i18n.t('richtext.underline', 'Underline') + '"><u>U</u></button>'
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="insertUnorderedList" title="' + window.CRM.i18n.t('richtext.bullet_list', 'Bullet list') + '">&bull; ' + window.CRM.i18n.t('richtext.list', 'List') + '</button>'
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="insertOrderedList" title="' + window.CRM.i18n.t('richtext.ordered_list', 'Ordered list') + '">1. ' + window.CRM.i18n.t('richtext.list', 'List') + '</button>'
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="formatBlock" data-rte-value="blockquote" title="' + window.CRM.i18n.t('richtext.blockquote', 'Quote') + '">' + window.CRM.i18n.t('richtext.blockquote', 'Quote') + '</button>'
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="createLink" title="' + window.CRM.i18n.t('richtext.link', 'Link') + '">' + window.CRM.i18n.t('richtext.link', 'Link') + '</button>'
      + '<button type="button" class="btn btn-light btn-sm" data-rte-cmd="removeFormat" title="' + window.CRM.i18n.t('richtext.clear_format', 'Clear formatting') + '">' + window.CRM.i18n.t('richtext.clear', 'Clear') + '</button>';
    return toolbar;
  }

  function enhance(textarea) {
    textarea.setAttribute(FIELD_MARKER, '1');
    textarea.classList.add('crm-rte-source');

    var rows = Number(textarea.getAttribute('rows') || 4);
    var minHeight = Math.max(120, rows * 22 + 38);

    var host = document.createElement('div');
    host.className = 'crm-rte';
    host.style.setProperty('--crm-rte-min-height', String(minHeight) + 'px');

    var toolbar = buildToolbar();
    var editor = document.createElement('div');
    editor.className = 'crm-rte-editor';
    editor.setAttribute('contenteditable', 'true');
    editor.setAttribute('role', 'textbox');
    editor.setAttribute('aria-multiline', 'true');
    editor.setAttribute('data-rte-editor', '1');
    editor.innerHTML = htmlFromTextareaValue(textarea.value || '');

    host.appendChild(toolbar);
    host.appendChild(editor);
    textarea.insertAdjacentElement('afterend', host);

    toolbar.addEventListener('click', function (event) {
      var button = event.target.closest('[data-rte-cmd]');
      if (!button) return;
      event.preventDefault();
      editor.focus();
      var cmd = button.getAttribute('data-rte-cmd');
      if (cmd === 'createLink') {
        var href = window.prompt(window.CRM.i18n.t('richtext.prompt_link', 'Enter link (https://...)'), 'https://');
        if (!href) return;
        var safeHref = sanitizeLinkHref(href);
        if (!safeHref) return;
        exec('createLink', safeHref);
      } else if (cmd === 'formatBlock') {
        var tag = button.getAttribute('data-rte-value') || 'p';
        exec('formatBlock', '<' + tag + '>');
      } else {
        exec(cmd);
      }
      syncSource(textarea, editor);
    });

    editor.addEventListener('input', function () {
      syncSource(textarea, editor);
    });

    editor.addEventListener('blur', function () {
      syncSource(textarea, editor);
    });

    var watcher = window.setInterval(function () {
      if (!document.body.contains(textarea)) {
        window.clearInterval(watcher);
        WATCHERS.delete(textarea);
        return;
      }
      if (document.activeElement === editor) return;
      var next = htmlFromTextareaValue(textarea.value || '');
      var current = sanitizeHtml(editor.innerHTML);
      if (next !== current) {
        editor.innerHTML = next;
      }
    }, 350);
    WATCHERS.set(textarea, watcher);
  }

  function enhanceScope(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('textarea').forEach(function (textarea) {
      if (shouldEnhance(textarea)) enhance(textarea);
    });
  }

  function init() {
    enhanceScope(document);
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof HTMLElement)) return;
          if (node.matches && node.matches('textarea')) {
            enhanceScope(node.parentElement || document);
            return;
          }
          if (node.querySelector) enhanceScope(node);
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  return {
    init: init,
    refreshScope: enhanceScope
  };
})();
