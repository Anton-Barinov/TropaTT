// WIP Limit — task detail sidebar panel hydration.
// Loaded only on the task-detail route via manifest assets.js_routes.
(function () {
    function getApi() {
        return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
    }

    function waitForApi(cb, n) {
        if (getApi()) { cb(); return; }
        if ((n || 0) > 100) { return; }
        setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 100);
    }

    waitForApi(function () {
        var content = document.querySelector('[data-wip-task-panel] [data-wip-task-content]');
        if (!content) { return; }

        getApi().request('_module/crm.wip-limit/summary', { method: 'GET', timeoutMs: 10000 })
            .then(function (env) {
                var items = (env && env.data && env.data.items) || [];
                var over = 0;
                var at = 0;
                items.forEach(function (u) {
                    if (u.over_limit) { over += 1; }
                    else if (u.at_limit) { at += 1; }
                });

                if (over > 0) {
                    content.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>' + over + ' перегружено</span>';
                } else if (at > 0) {
                    content.innerHTML = '<span class="text-warning"><i class="fa-solid fa-circle-info me-1"></i>' + at + ' на пределе</span>';
                } else {
                    content.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Всё в норме</span>';
                }
            })
            .catch(function () {
                content.innerHTML = '<span class="text-muted small">Нет данных</span>';
            });
    });
})();
