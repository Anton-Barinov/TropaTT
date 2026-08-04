window.CRM = window.CRM || {};
/*
 * Rich-text field enhancement — DELEGATED path.
 *
 * Plain <textarea>s with a "description"-like intent are handed off to the
 * full visual editor (CRM.VisualEditor) by marking them with
 * data-crm-visual-editor="1" and calling VisualEditor.initScope().
 *
 * The old self-contained RTE implementation (enhance/buildToolbar and its
 * sanitizer/exec/sync helpers) was dead code: nothing called it after the
 * delegation was introduced, and it duplicated the VisualEditor's own
 * sanitizer and toolbar with a weaker allow-list. It has been removed.
 */
window.CRM.richtext = (function () {
  var FIELD_MARKER = 'data-crm-richtext-ready';

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
    if (textarea.getAttribute('data-crm-visual-editor') === '1') return false;
    if (textarea.dataset.richtextOff === '1') return false;
    if (textarea.closest('[data-richtext-off="1"]')) return false;
    return hasDescriptionIntent(textarea);
  }

  // Mark the field and delegate rendering/editing to the visual editor. The
  // visual-editor.js script initialises asynchronously (it may load after this
  // file), so it scans for textareas marked with data-crm-visual-editor on its
  // own init and via a MutationObserver; the direct initScope call below just
  // covers the case where VisualEditor already finished loading.
  function handoffToVisualEditor(textarea) {
    textarea.setAttribute(FIELD_MARKER, '1');
    textarea.setAttribute('data-crm-visual-editor', '1');
    textarea.setAttribute('data-richtext-off', '1');
    if (window.CRM.VisualEditor && typeof window.CRM.VisualEditor.initScope === 'function') {
      window.CRM.VisualEditor.initScope(textarea.parentElement || document);
    }
  }

  function enhanceScope(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('textarea').forEach(function (textarea) {
      if (shouldEnhance(textarea)) handoffToVisualEditor(textarea);
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
