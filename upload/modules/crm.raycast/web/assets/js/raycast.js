(function () {
    'use strict';

    const API_PREFIX = '_module/crm.raycast';

    function isRaycastPage() {
        return Boolean(document.body && document.body.dataset && document.body.dataset.page === 'module-raycast');
    }

    function init() {
        if (!isRaycastPage()) return;

        const container = document.getElementById('raycastConfig');
        window.CRM.api.request(API_PREFIX + '/config', { method: 'GET' })
            .then(function (env) {
                const cfg = env.data || {};
                const url = cfg.mcp_url || '';

                container.innerHTML =
                    '<div class="mb-3">' +
                    '<label class="form-label text-muted">URL эндпоинта MCP</label>' +
                    '<div class="input-group">' +
                    '<input type="text" class="form-control font-monospace" id="mcpUrlInput" readonly value="' + htmlEscape(url) + '">' +
                    '<button class="btn crm-btn-primary" id="copyMcpUrlBtn" type="button"><i class="fa-solid fa-copy"></i> Копировать</button>' +
                    '</div>' +
                    '<div class="form-text">Метод: <code>POST</code> · Маршрут: <code>' + htmlEscape(cfg.mcp_route || '') + '</code> · Авторизация: <code>Bearer token</code></div>' +
                    '</div>' +
                    '<div><span class="badge bg-success">' + htmlEscape(cfg.status || 'available') + '</span> ' +
                    '<span class="text-muted">Сервер: ' + htmlEscape(cfg.server_name || 'TropaTT') + '</span></div>';

                document.getElementById('copyMcpUrlBtn').addEventListener('click', function () {
                    const input = document.getElementById('mcpUrlInput');
                    input.select();
                    input.setSelectionRange(0, 99999);
                    try {
                        document.execCommand('copy');
                    } catch (e) {
                        navigator.clipboard && navigator.clipboard.writeText(input.value);
                    }
                });
            })
            .catch(function (err) {
                container.innerHTML = '<div class="text-danger">' + htmlEscape(err.message || 'Ошибка загрузки конфигурации') + '</div>';
            });
    }

    function htmlEscape(str) {
        if (typeof str !== 'string') return String(str || '');
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
