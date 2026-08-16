(function () {
    'use strict';

    const API_PREFIX = '_module/crm.slack-integration';

    function isSlackPage() {
        return Boolean(document.body && document.body.dataset && document.body.dataset.page === 'module-slack-integration');
    }

    function init() {
        if (!isSlackPage()) return;

        document.getElementById('addConnectionBtn')?.addEventListener('click', openConnectionModal);
        document.getElementById('saveConnectionBtn')?.addEventListener('click', saveConnection);
        document.getElementById('addRuleBtn')?.addEventListener('click', openRuleModal);
        document.getElementById('saveRuleBtn')?.addEventListener('click', saveRule);

        loadConnections();
        loadRules();
        loadDeliveries();
    }

    function api(path, method, body, query) {
        return window.CRM.api.request(API_PREFIX + path, { method: method, body: body, query: query })
            .then(function (env) { return env.data || {}; });
    }

    function loadConnections() {
        const container = document.getElementById('connectionsList');
        api('/connections', 'GET').then(function (data) {
            const connections = data.connections || [];
            if (connections.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">Нет подключений.</div>';
            } else {
                container.innerHTML = '<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>' +
                    '<th>Название</th><th>Канал</th><th>Статус</th><th class="text-end">Действия</th></tr></thead><tbody>' +
                    connections.map(function (c) {
                        const badge = c.last_status === 'success'
                            ? '<span class="badge bg-success">OK</span>'
                            : (c.last_status === 'failed' ? '<span class="badge bg-danger">Ошибка</span>' : '<span class="badge bg-secondary">—</span>');
                        return '<tr><td><strong>' + htmlEscape(c.name) + '</strong><br><code class="text-muted small">' + htmlEscape(c.public_id) + '</code></td>' +
                            '<td>' + htmlEscape(c.channel || '—') + '</td>' +
                            '<td>' + badge + '</td>' +
                            '<td class="text-end"><div class="btn-group">' +
                            '<button class="btn btn-sm crm-btn-secondary test-connection-btn" data-id="' + htmlEscape(c.public_id) + '"><i class="fa-solid fa-paper-plane"></i></button>' +
                            '<button class="btn btn-sm crm-btn-danger-soft delete-connection-btn" data-id="' + htmlEscape(c.public_id) + '"><i class="fa-solid fa-trash"></i></button>' +
                            '</div></td></tr>';
                    }).join('') + '</tbody></table></div>';
            }

            container.querySelectorAll('.test-connection-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    api('/connections/' + btn.dataset.id + '/test', 'POST', {}).then(function () {
                        alert(window.CRM.i18n.t('slack_integration.test_sent', 'Тестовое сообщение отправлено'));
                        loadConnections();
                    }).catch(function (err) { alert(err.message); });
                });
            });
            container.querySelectorAll('.delete-connection-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (confirm(window.CRM.i18n.t('slack_integration.confirm_delete_connection', 'Удалить подключение?'))) {
                        api('/connections/' + btn.dataset.id, 'DELETE').then(function () { loadConnections(); })
                            .catch(function (err) { alert(err.message); });
                    }
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function openConnectionModal() {
        document.getElementById('connName').value = '';
        document.getElementById('connChannel').value = '';
        document.getElementById('connWebhook').value = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('connectionModal')).show();
    }

    function saveConnection() {
        const name = document.getElementById('connName').value.trim();
        const webhook = document.getElementById('connWebhook').value.trim();
        const channel = document.getElementById('connChannel').value.trim();
        if (!name || !webhook) { alert(window.CRM.i18n.t('slack_integration.fill_name_webhook', 'Заполните название и Webhook URL')); return; }

        api('/connections', 'POST', { name: name, channel: channel, webhook_url: webhook }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('connectionModal')).hide();
            loadConnections();
        }).catch(function (err) { alert(err.message); });
    }

    function loadRules() {
        const container = document.getElementById('rulesList');
        api('/rules', 'GET').then(function (data) {
            const rules = data.rules || [];
            if (rules.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">Нет правил.</div>';
            } else {
                container.innerHTML = '<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>' +
                    '<th>Событие</th><th>Подключение</th><th>Шаблон</th><th class="text-end">Действия</th></tr></thead><tbody>' +
                    rules.map(function (r) {
                        return '<tr><td><code>' + htmlEscape(r.event_code) + '</code></td>' +
                            '<td>' + htmlEscape(r.connection_name || '—') + '</td>' +
                            '<td class="text-muted small">' + htmlEscape(r.text_template || '') + '</td>' +
                            '<td class="text-end"><button class="btn btn-sm crm-btn-danger-soft delete-rule-btn" data-id="' + htmlEscape(r.public_id) + '"><i class="fa-solid fa-trash"></i></button></td></tr>';
                    }).join('') + '</tbody></table></div>';
            }
            container.querySelectorAll('.delete-rule-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (confirm(window.CRM.i18n.t('slack_integration.confirm_delete_rule', 'Удалить правило?'))) {
                        api('/rules/' + btn.dataset.id, 'DELETE').then(function () { loadRules(); })
                            .catch(function (err) { alert(err.message); });
                    }
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function openRuleModal() {
        const select = document.getElementById('ruleConnection');
        document.getElementById('ruleText').value = '';
        api('/connections', 'GET').then(function (data) {
            const connections = data.connections || [];
            select.innerHTML = connections.map(function (c) {
                return '<option value="' + htmlEscape(c.public_id) + '">' + htmlEscape(c.name) + '</option>';
            }).join('');
        }).catch(function () {});
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ruleModal')).show();
    }

    function saveRule() {
        const connectionPublicId = document.getElementById('ruleConnection').value;
        const eventCode = document.getElementById('ruleEvent').value;
        const text = document.getElementById('ruleText').value.trim();
        if (!connectionPublicId || !text) { alert(window.CRM.i18n.t('slack_integration.fill_connection_template', 'Заполните подключение и шаблон')); return; }

        api('/rules', 'POST', { connection_public_id: connectionPublicId, event_code: eventCode, text_template: text }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('ruleModal')).hide();
            loadRules();
        }).catch(function (err) { alert(err.message); });
    }

    function loadDeliveries() {
        const container = document.getElementById('deliveriesList');
        api('/deliveries', 'GET', undefined, { limit: 20 }).then(function (data) {
            const deliveries = data.deliveries || [];
            if (deliveries.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">Нет доставок.</div>';
            } else {
                container.innerHTML = '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr>' +
                    '<th>Время</th><th>Подключение</th><th>Событие</th><th>Статус</th><th>Попытки</th></tr></thead><tbody>' +
                    deliveries.map(function (d) {
                        const badge = d.status === 'sent' ? '<span class="badge bg-success">sent</span>'
                            : (d.status === 'failed' ? '<span class="badge bg-danger">failed</span>'
                            : '<span class="badge bg-secondary">' + htmlEscape(d.status) + '</span>');
                        return '<tr><td class="text-muted small">' + htmlEscape(d.created_at || '') + '</td>' +
                            '<td>' + htmlEscape(d.connection_name || '—') + '</td>' +
                            '<td><code>' + htmlEscape(d.event_code || '—') + '</code></td>' +
                            '<td>' + badge + '</td>' +
                            '<td>' + (d.attempts || 0) + '</td></tr>';
                    }).join('') + '</tbody></table></div>';
            }
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
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
