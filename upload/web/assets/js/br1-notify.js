/**
 * br1-notify.js — Minimal stub for CRM.br1.notify() and CRM.br1.safeText().
 *
 * Loaded on routes that only need the toast notification utility from br1.js
 * without pulling the full 400KB task-detail module.
 */
window.CRM = window.CRM || {};
window.CRM.br1 = window.CRM.br1 || {};
(function (br1) {
  if (typeof br1.notify === 'function') return;
  br1.notify = function (text, type) {
    if (window.CRM && window.CRM.ui && typeof window.CRM.ui.notify === 'function') {
      return window.CRM.ui.notify(text, type);
    }
    if (typeof type === 'string' && type === 'error') {
      console.error('[CRM]', text);
    } else {
      console.log('[CRM]', text);
    }
  };
  if (typeof br1.safeText !== 'function') {
    br1.safeText = function (value) {
      if (window.CRM && window.CRM.text && typeof window.CRM.text.safeText === 'function') {
        return window.CRM.text.safeText(value);
      }
      return String(value || '');
    };
  }
  if (typeof br1.getProjectPublicIdFromUrl !== 'function') {
    br1.getProjectPublicIdFromUrl = function () {
      return new URLSearchParams(window.location.search).get('project_public_id') || '';
    };
  }
})(window.CRM.br1);
