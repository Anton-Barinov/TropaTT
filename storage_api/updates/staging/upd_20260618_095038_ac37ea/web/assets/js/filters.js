window.CRM = window.CRM || {};
window.CRM.filters = (function () {
  function init() {
    document.querySelectorAll('[data-filter-reset]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var scope = btn.closest('form') || document;
        scope.querySelectorAll('input, select, textarea').forEach(function (field) {
          if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
          else field.value = '';
        });
      });
    });

  }

  return { init: init };
})();
