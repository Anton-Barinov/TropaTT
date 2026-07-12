window.CRM = window.CRM || {};
window.CRM.drawers = (function () {
  function init() {
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-open-drawer]');
      if (!trigger) return;
      var drawer = document.getElementById(trigger.dataset.openDrawer);
      if (!drawer) return;
      bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
    });
  }

  return { init: init };
})();
