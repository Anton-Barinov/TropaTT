/* crm.position-example — route-scoped script (loaded only on the gantt route). */
(function () {
    'use strict';

    var el = document.querySelector('[data-position-example-js]');
    if (!el) {
        return;
    }

    var route = new URLSearchParams(window.location.search).get('route') || 'gantt';
    el.textContent = 'Scoped script loaded on route "' + route + '" at ' + new Date().toLocaleTimeString() + '.';
})();
