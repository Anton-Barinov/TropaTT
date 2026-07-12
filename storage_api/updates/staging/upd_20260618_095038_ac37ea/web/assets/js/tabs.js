window.CRM = window.CRM || {};
window.CRM.tabs = (function () {
  function init() {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (el) {
      el.addEventListener('shown.bs.tab', function (ev) {
        var targetSel = ev.target.getAttribute('data-bs-target');
        if (!targetSel) return;
        var pane = document.querySelector(targetSel);
        if (pane) pane.classList.add('crm-anim-in');
      });
    });
  }

  return { init: init };
})();
