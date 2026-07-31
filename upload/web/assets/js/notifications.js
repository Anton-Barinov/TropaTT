window.CRM = window.CRM || {};
window.CRM.notifications = (function () {
  function init() {
    // Notification state is managed by API + page-api-bindings + realtime stream.
    // Keep this module as a stable entry point for backward compatibility.
  }

  return { init: init };
})();
