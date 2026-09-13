/**
 * E-Commerce Gateway Admin Interface (PRJ-65 / E-COM-05)
 */
(function () {
    'use strict';

    const API_PREFIX = '_module/crm.ecommerce-gateway/v1';

    let state = {
        stores: [],
        currentStore: null,
        projects: [],
        users: [],
        crmStatuses: [
            { code: 'new', title: 'Новый (New)' },
            { code: 'processing', title: 'В обработке (Processing)' },
            { code: 'on_hold', title: 'На удержании (On Hold)' },
            { code: 'shipped', title: 'Отправлен / Доставляется (Shipped)' },
            { code: 'completed', title: 'Завершён / Доставлен (Completed)' },
            { code: 'returned', title: 'Возврат (Returned)' },
            { code: 'refunded', title: 'Возврат средств (Refunded)' },
            { code: 'cancelled', title: 'Отменён (Cancelled)' }
        ]
    };

    function isPage() {
        return Boolean(document.body && document.body.dataset && document.body.dataset.page === 'module-ecommerce-gateway');
    }

    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
        });
    }

    function api(path, options) {
        options = options || {};
        const method = options.method || 'GET';
        const body = options.body;
        const query = options.query;
        return window.CRM.api.request(API_PREFIX + path, { method: method, body: body, query: query })
            .then(function (res) {
                if (res && res.data) return res.data;
                return res || {};
            });
    }

    function showNotice(msg, type) {
        if (window.CRM && window.CRM.ui && window.CRM.ui.showNotice) {
            window.CRM.ui.showNotice(msg, type || 'info');
        } else {
            alert(msg);
        }
    }

    // ── Tab Management ──
    function initTabs() {
        const tabButtons = document.querySelectorAll('#ecomTabs button[data-tab]');
        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetTab = btn.dataset.tab;
                tabButtons.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');

                document.querySelectorAll('.crm-ecom-tab-pane').forEach(function (pane) {
                    pane.classList.remove('active');
                });
                const targetPane = document.getElementById('tab-' + targetTab);
                if (targetPane) targetPane.classList.add('active');

                if (targetTab === 'status_mapping') loadStatusMappings();
                if (targetTab === 'sync_log') loadSyncLog();
            });
        });
    }

    // ── Store Registry & Selection ──
    function loadStores(selectPublicId) {
        return api('/stores').then(function (data) {
            state.stores = data.stores || [];
            renderStoresTable();
            renderStoreSelector(selectPublicId);
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка загрузки витрин', 'error');
        });
    }

    function renderStoreSelector(selectPublicId) {
        const select = document.getElementById('storeSelector');
        if (!select) return;

        if (!state.stores.length) {
            select.innerHTML = '<option value="">Нет созданных витрин</option>';
            state.currentStore = null;
            return;
        }

        let html = '';
        state.stores.forEach(function (store) {
            html += '<option value="' + esc(store.public_id) + '">' + esc(store.name) + ' (' + esc(store.cms_type) + ')</option>';
        });
        select.innerHTML = html;

        let activePublicId = selectPublicId;
        if (!activePublicId && state.currentStore) {
            activePublicId = state.currentStore.public_id;
        }
        if (!activePublicId && state.stores.length) {
            activePublicId = state.stores[0].public_id;
        }

        if (activePublicId) {
            select.value = activePublicId;
            selectStore(activePublicId);
        }
    }

    function selectStore(publicId) {
        const store = state.stores.find(function (s) { return s.public_id === publicId; });
        state.currentStore = store || null;
        if (state.currentStore) {
            populateRoutingForm(state.currentStore);
            populateSecurityForm(state.currentStore);
            loadStatusMappings();
            loadSyncLog();
        }
    }

    function renderStoresTable() {
        const tbody = document.getElementById('storesTableBody');
        if (!tbody) return;

        if (!state.stores.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted p-4 text-center">Витрины не найдены. Нажмите «Добавить витрину» для подключения интернет-магазина.</td></tr>';
            return;
        }

        let html = '';
        state.stores.forEach(function (s) {
            const statusClass = s.status === 'active' ? 'active' : (s.status === 'paused' ? 'paused' : 'disabled');
            const statusLabel = s.status === 'active' ? 'Активен' : (s.status === 'paused' ? 'Пауза' : 'Отключен');
            const lastIngest = s.last_ingest_at ? esc(s.last_ingest_at) : '<span class="text-muted">—</span>';

            html += '<tr>' +
                '<td>' +
                    '<strong>' + esc(s.name) + '</strong>' +
                    (s.store_url ? '<div class="small text-muted"><a href="' + esc(s.store_url) + '" target="_blank" rel="noopener">' + esc(s.store_url) + '</a></div>' : '') +
                '</td>' +
                '<td><span class="badge bg-light text-dark border crm-ecom-badge-cms">' + esc(s.cms_type) + '</span></td>' +
                '<td><code>' + esc(s.api_key) + '</code>' + (s.api_secret_hint ? ' <small class="text-muted">(' + esc(s.api_secret_hint) + ')</small>' : '') + '</td>' +
                '<td><span class="crm-ecom-status-pill ' + statusClass + '">' + statusLabel + '</span></td>' +
                '<td>' + lastIngest + '</td>' +
                '<td class="text-end">' +
                    '<div class="btn-group btn-group-sm">' +
                        '<button type="button" class="btn crm-btn-secondary edit-store-btn" data-id="' + esc(s.public_id) + '" title="Редактировать"><i class="fa-solid fa-pen"></i></button>' +
                        '<button type="button" class="btn crm-btn-secondary ping-store-row-btn" data-id="' + esc(s.public_id) + '" title="Ping Тест"><i class="fa-solid fa-satellite-dish"></i></button>' +
                        '<button type="button" class="btn crm-btn-danger-soft delete-store-btn" data-id="' + esc(s.public_id) + '" title="Удалить"><i class="fa-solid fa-trash"></i></button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.edit-store-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditStoreModal(btn.dataset.id); });
        });
        tbody.querySelectorAll('.ping-store-row-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { testPingStore(btn.dataset.id); });
        });
        tbody.querySelectorAll('.delete-store-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { deleteStore(btn.dataset.id); });
        });
    }

    // ── Store Modals & CRUD ──
    function openAddStoreModal() {
        document.getElementById('storeModalTitle').textContent = 'Подключение новой витрины интернет-магазина';
        document.getElementById('modalStorePublicId').value = '';
        document.getElementById('modalStoreName').value = '';
        document.getElementById('modalStoreCms').value = 'opencart';
        document.getElementById('modalStoreUrl').value = '';
        document.getElementById('modalWebhookUrl').value = '';
        document.getElementById('modalStoreStatus').value = 'active';
        document.getElementById('modalStoreLocale').value = 'ru-ru';
        document.getElementById('modalStoreDesc').value = '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('storeModal')).show();
    }

    function openEditStoreModal(publicId) {
        const store = state.stores.find(function (s) { return s.public_id === publicId; });
        if (!store) return;

        document.getElementById('storeModalTitle').textContent = 'Редактирование витрины: ' + store.name;
        document.getElementById('modalStorePublicId').value = store.public_id;
        document.getElementById('modalStoreName').value = store.name || '';
        document.getElementById('modalStoreCms').value = store.cms_type || 'custom';
        document.getElementById('modalStoreUrl').value = store.store_url || '';
        document.getElementById('modalWebhookUrl').value = store.webhook_url || '';
        document.getElementById('modalStoreStatus').value = store.status || 'active';
        document.getElementById('modalStoreLocale').value = store.locale || 'ru-ru';
        document.getElementById('modalStoreDesc').value = store.description || '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('storeModal')).show();
    }

    function saveStoreModal() {
        const publicId = document.getElementById('modalStorePublicId').value;
        const name = document.getElementById('modalStoreName').value.trim();
        if (!name) {
            showNotice('Введите название витрины', 'warning');
            return;
        }

        const payload = {
            name: name,
            cms_type: document.getElementById('modalStoreCms').value,
            store_url: document.getElementById('modalStoreUrl').value.trim() || null,
            webhook_url: document.getElementById('modalWebhookUrl').value.trim() || null,
            status: document.getElementById('modalStoreStatus').value,
            locale: document.getElementById('modalStoreLocale').value,
            description: document.getElementById('modalStoreDesc').value.trim() || null
        };

        const isNew = !publicId;
        const req = isNew
            ? api('/stores', { method: 'POST', body: payload })
            : api('/stores/' + publicId, { method: 'PATCH', body: payload });

        req.then(function (res) {
            bootstrap.Modal.getInstance(document.getElementById('storeModal')).hide();
            showNotice('Витрина успешно сохранена', 'success');

            if (isNew && res.secret) {
                // Show newly generated secret
                document.getElementById('displayApiKey').textContent = res.store.api_key;
                document.getElementById('displayApiSecret').textContent = res.secret;
                document.getElementById('displaySecretExpiry').textContent = '';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('secretModal')).show();
            }

            const targetId = isNew ? (res.store ? res.store.public_id : null) : publicId;
            loadStores(targetId);
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка сохранения витрины', 'error');
        });
    }

    function deleteStore(publicId) {
        if (!confirm('Вы уверены, что хотите удалить эту витрину? Все интеграции будут приостановлены.')) {
            return;
        }
        api('/stores/' + publicId, { method: 'DELETE' }).then(function () {
            showNotice('Витрина удалена', 'success');
            loadStores();
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка при удалении', 'error');
        });
    }

    function testPingStore(publicId) {
        showNotice('Отправка ping-запроса к витрине...', 'info');
        api('/stores/' + publicId + '/ping-test', { method: 'POST' }).then(function (data) {
            if (data.success) {
                showNotice('Ping успешен: HTTP ' + data.http_code + ' (' + data.duration_ms + ' ms)', 'success');
            } else {
                showNotice('Ping завершился ошибкой: HTTP ' + data.http_code + ' ' + (data.error || ''), 'warning');
            }
        }).catch(function (err) {
            showNotice('Ошибка ping-теста: ' + (err.message || 'Сервер недоступен'), 'error');
        });
    }

    function rotateStoreSecret() {
        if (!state.currentStore) {
            showNotice('Выберите витрину для ротации ключа', 'warning');
            return;
        }
        if (!confirm('Сгенерировать новый секретный ключ HMAC? Предыдущий ключ останется валидным в течение 1 часа (Grace period).')) {
            return;
        }

        api('/stores/' + state.currentStore.public_id + '/rotate-secret', { method: 'POST' }).then(function (res) {
            document.getElementById('displayApiKey').textContent = res.store.api_key;
            document.getElementById('displayApiSecret').textContent = res.secret;
            document.getElementById('displaySecretExpiry').textContent = 'Старый ключ остаётся валиден до: ' + (res.secret_valid_until || '1 час');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('secretModal')).show();
            loadStores(state.currentStore.public_id);
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка ротации ключа', 'error');
        });
    }

    // ── Routing Form ──
    function populateRoutingForm(store) {
        const settings = (store && store.settings) || {};
        document.getElementById('settingDefaultProject').value = settings.default_project_id || '';
        document.getElementById('settingDefaultAssignee').value = settings.default_assignee_id || '';
        document.getElementById('settingOnDuplicate').value = settings.on_duplicate || 'merge';
        document.getElementById('settingDefaultPriority').value = settings.default_priority_code || 'normal';

        const createFor = Array.isArray(settings.create_task_for) ? settings.create_task_for : [];
        document.getElementById('chkTaskOrder').checked = createFor.includes('order');
        document.getElementById('chkTaskQuickOrder').checked = createFor.includes('quick_order');
        document.getElementById('chkTaskCallback').checked = createFor.includes('callback');
        document.getElementById('chkTaskFeedback').checked = createFor.includes('feedback');
    }

    function saveRouting(e) {
        e.preventDefault();
        if (!state.currentStore) {
            showNotice('Витрина не выбрана', 'warning');
            return;
        }

        const createFor = [];
        if (document.getElementById('chkTaskOrder').checked) createFor.push('order');
        if (document.getElementById('chkTaskQuickOrder').checked) createFor.push('quick_order');
        if (document.getElementById('chkTaskCallback').checked) createFor.push('callback');
        if (document.getElementById('chkTaskFeedback').checked) createFor.push('feedback');

        const payload = {
            default_project_id: document.getElementById('settingDefaultProject').value || null,
            default_assignee_id: document.getElementById('settingDefaultAssignee').value || null,
            on_duplicate: document.getElementById('settingOnDuplicate').value,
            default_priority_code: document.getElementById('settingDefaultPriority').value,
            create_task_for: createFor
        };

        api('/stores/' + state.currentStore.public_id, { method: 'PATCH', body: payload }).then(function () {
            showNotice('Параметры маршрутизации сохранены', 'success');
            loadStores(state.currentStore.public_id);
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка сохранения', 'error');
        });
    }

    // ── Status Mappings ──
    function loadStatusMappings() {
        if (!state.currentStore) return;
        const tbody = document.getElementById('statusMappingBody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center p-3">Загрузка маппинга статусов...</td></tr>';

        api('/stores/' + state.currentStore.public_id + '/status-mappings').then(function (data) {
            const mappings = data.mappings || [];
            renderStatusMappings(mappings);
        }).catch(function (err) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger p-3">Ошибка: ' + esc(err.message) + '</td></tr>';
        });
    }

    function renderStatusMappings(mappings) {
        const tbody = document.getElementById('statusMappingBody');
        if (!tbody) return;

        if (!mappings.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center p-3">Нет настроенных статусов. Нажмите «Добавить соответствие» ниже.</td></tr>';
            return;
        }

        let html = '';
        mappings.forEach(function (m) {
            html += createMappingRowHtml(m.entity_scope || 'order', m.external_status || '', m.crm_status_code || 'processing');
        });
        tbody.innerHTML = html;
        bindMappingRowEvents();
    }

    function createMappingRowHtml(scope, extStatus, crmStatus) {
        let crmOptions = '';
        state.crmStatuses.forEach(function (s) {
            const sel = s.code === crmStatus ? ' selected' : '';
            crmOptions += '<option value="' + esc(s.code) + '"' + sel + '>' + esc(s.title) + '</option>';
        });

        return '<tr class="status-mapping-row">' +
            '<td>' +
                '<select class="form-select form-select-sm map-scope">' +
                    '<option value="order"' + (scope === 'order' ? ' selected' : '') + '>Заказ (Order)</option>' +
                    '<option value="callback"' + (scope === 'callback' ? ' selected' : '') + '>Обратный звонок</option>' +
                    '<option value="feedback"' + (scope === 'feedback' ? ' selected' : '') + '>Заявка / Форма</option>' +
                    '<option value="all"' + (scope === 'all' ? ' selected' : '') + '>Любой (All)</option>' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<input type="text" class="form-control form-control-sm map-external font-monospace" placeholder="Напр. Pending, Complete, 1, 5" value="' + esc(extStatus) + '">' +
            '</td>' +
            '<td class="text-center crm-mapping-arrow"><i class="fa-solid fa-arrows-left-right"></i></td>' +
            '<td>' +
                '<select class="form-select form-select-sm map-crm">' + crmOptions + '</select>' +
            '</td>' +
            '<td class="text-end">' +
                '<button type="button" class="btn btn-sm crm-btn-danger-soft remove-mapping-btn" title="Удалить"><i class="fa-solid fa-xmark"></i></button>' +
            '</td>' +
        '</tr>';
    }

    function addMappingRow() {
        const tbody = document.getElementById('statusMappingBody');
        // Clear placeholder row if empty
        if (tbody.querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }
        const tempDiv = document.createElement('tbody');
        tempDiv.innerHTML = createMappingRowHtml('order', '', 'processing');
        const newRow = tempDiv.firstElementChild;
        tbody.appendChild(newRow);
        bindMappingRowEvents();
    }

    function bindMappingRowEvents() {
        document.querySelectorAll('.remove-mapping-btn').forEach(function (btn) {
            btn.onclick = function () {
                const row = btn.closest('tr');
                if (row) row.remove();
            };
        });
    }

    function saveStatusMappings() {
        if (!state.currentStore) {
            showNotice('Витрина не выбрана', 'warning');
            return;
        }

        const rows = document.querySelectorAll('#statusMappingBody tr.status-mapping-row');
        const mappings = [];
        rows.forEach(function (r) {
            const scope = r.querySelector('.map-scope').value;
            const extStatus = r.querySelector('.map-external').value.trim();
            const crmStatus = r.querySelector('.map-crm').value;
            if (extStatus && crmStatus) {
                mappings.push({
                    entity_scope: scope,
                    external_status: extStatus,
                    crm_status_code: crmStatus
                });
            }
        });

        api('/stores/' + state.currentStore.public_id + '/status-mappings', {
            method: 'PUT',
            body: { mappings: mappings }
        }).then(function () {
            showNotice('Маппинг статусов успешно сохранен', 'success');
            loadStatusMappings();
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка сохранения статусов', 'error');
        });
    }

    // ── Security Form ──
    function populateSecurityForm(store) {
        const settings = (store && store.settings) || {};
        document.getElementById('securityRateLimit').value = store.rate_limit_per_minute || 120;
        document.getElementById('securityRetentionDays').value = settings.retention_days || 90;
        document.getElementById('securityIpWhitelist').value = store.ip_whitelist || '';
        document.getElementById('securityAntispamEnabled').checked = settings.antispam_enabled !== 0;
    }

    function saveSecurity(e) {
        e.preventDefault();
        if (!state.currentStore) {
            showNotice('Витрина не выбрана', 'warning');
            return;
        }

        const payload = {
            rate_limit_per_minute: parseInt(document.getElementById('securityRateLimit').value, 10) || 120,
            retention_days: parseInt(document.getElementById('securityRetentionDays').value, 10) || 90,
            ip_whitelist: document.getElementById('securityIpWhitelist').value.trim() || null,
            antispam_enabled: document.getElementById('securityAntispamEnabled').checked ? 1 : 0
        };

        api('/stores/' + state.currentStore.public_id, { method: 'PATCH', body: payload }).then(function () {
            showNotice('Настройки безопасности сохранены', 'success');
            loadStores(state.currentStore.public_id);
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка сохранения безопасности', 'error');
        });
    }

    // ── Sync Log & Outbox ──
    function loadSyncLog() {
        if (!state.currentStore) return;
        const tbody = document.getElementById('syncLogTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-muted p-3">Загрузка журнала синхронизации...</td></tr>';

        const direction = document.getElementById('logDirectionFilter').value || 'all';

        api('/stores/' + state.currentStore.public_id + '/sync-log', {
            query: { direction: direction, limit: 50 }
        }).then(function (data) {
            renderSyncLog(data.items || []);
        }).catch(function (err) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-danger p-3">Ошибка: ' + esc(err.message) + '</td></tr>';
        });
    }

    function renderSyncLog(items) {
        const tbody = document.getElementById('syncLogTableBody');
        if (!tbody) return;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted p-4 text-center">Событий синхронизации пока не зарегистрировано.</td></tr>';
            return;
        }

        let html = '';
        items.forEach(function (it) {
            const isOutbox = it.direction === 'outbound' || it.log_type === 'outbox_webhook';
            const dirBadge = isOutbox
                ? '<span class="badge bg-info text-dark"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Outbox</span>'
                : '<span class="badge bg-primary"><i class="fa-solid fa-arrow-down-left-and-up-right-to-center me-1"></i> Inbound</span>';

            const orderId = it.external_order_id ? '#' + esc(it.external_order_id) : '—';
            const eventDesc = isOutbox ? (esc(it.event_type || 'status_change') + ' (' + esc(it.old_status || '') + ' → ' + esc(it.new_status || '') + ')') : orderId;

            const httpCode = it.http_code ? it.http_code : '—';
            const isSuccess = it.status === 'delivered' || it.status === 'processed' || (it.http_code >= 200 && it.http_code < 400);
            const statusBadge = isSuccess
                ? '<span class="badge bg-success">' + esc(it.status) + ' (' + httpCode + ')</span>'
                : '<span class="badge bg-danger">' + esc(it.status) + ' (' + httpCode + ')</span>';

            const taskCol = it.crm_task_public_id || it.crm_task_id
                ? '<a href="index.php?route=task-detail&task_public_id=' + esc(it.crm_task_public_id || it.crm_task_id) + '">' + esc(it.crm_task_public_id || ('ID ' + it.crm_task_id)) + '</a>'
                : '<span class="text-muted">—</span>';

            const dateStr = it.created_at ? esc(it.created_at) : '—';

            // Store JSON data attributes safely for modal
            const reqPayload = encodeURIComponent(it.request_payload || '{}');
            const resPayload = encodeURIComponent(it.response_payload || it.error_message || '{}');

            const retryBtn = isOutbox && !isSuccess
                ? '<button type="button" class="btn crm-btn-secondary retry-log-btn" data-id="' + esc(it.public_id || it.id) + '" title="Повторить отправку"><i class="fa-solid fa-rotate-right"></i> Retry</button>'
                : '';

            html += '<tr>' +
                '<td>' + dirBadge + '</td>' +
                '<td><strong>' + eventDesc + '</strong></td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + taskCol + '</td>' +
                '<td><small>' + dateStr + '</small></td>' +
                '<td class="text-end">' +
                    '<div class="btn-group btn-group-sm">' +
                        '<button type="button" class="btn crm-btn-secondary view-payload-btn" data-req="' + reqPayload + '" data-res="' + resPayload + '" data-title="Событие ' + esc(it.public_id || it.id) + '" title="Посмотреть JSON"><i class="fa-solid fa-code"></i> JSON</button>' +
                        retryBtn +
                    '</div>' +
                '</td>' +
            '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.view-payload-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openPayloadModal(btn.dataset.title, decodeURIComponent(btn.dataset.req), decodeURIComponent(btn.dataset.res));
            });
        });

        tbody.querySelectorAll('.retry-log-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                retrySyncPacket(btn.dataset.id);
            });
        });
    }

    function openPayloadModal(title, reqJson, resJson) {
        document.getElementById('payloadModalTitle').textContent = title;

        let prettyReq = reqJson;
        let prettyRes = resJson;
        try { prettyReq = JSON.stringify(JSON.parse(reqJson), null, 2); } catch (e) {}
        try { prettyRes = JSON.stringify(JSON.parse(resJson), null, 2); } catch (e) {}

        document.getElementById('modalRequestPayload').textContent = prettyReq || '(пусто)';
        document.getElementById('modalResponsePayload').textContent = prettyRes || '(пусто)';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('payloadModal')).show();
    }

    function retrySyncPacket(logId) {
        if (!state.currentStore) return;
        showNotice('Повторная отправка пакета синхронизации...', 'info');

        api('/stores/' + state.currentStore.public_id + '/sync-log/' + logId + '/retry', { method: 'POST' }).then(function (res) {
            const r = res.result || {};
            if (r.status === 'delivered') {
                showNotice('Пакет успешно доставлен в CMS (HTTP ' + r.http_code + ')', 'success');
            } else {
                showNotice('Повтор выполнен, статус: ' + r.status + ' (' + (r.error || '') + ')', 'warning');
            }
            loadSyncLog();
        }).catch(function (err) {
            showNotice(err.message || 'Ошибка повторной отправки', 'error');
        });
    }

    // ── Preload Projects & Users ──
    function loadMetadata() {
        window.CRM.api.request('projects', { method: 'GET' }).then(function (res) {
            const projects = (res && res.data && res.data.projects) || [];
            state.projects = projects;
            const sel = document.getElementById('settingDefaultProject');
            if (sel) {
                projects.forEach(function (p) {
                    sel.innerHTML += '<option value="' + esc(p.id) + '">' + esc(p.name) + '</option>';
                });
            }
        }).catch(function () {});

        window.CRM.api.request('users', { method: 'GET' }).then(function (res) {
            const users = (res && res.data && res.data.users) || [];
            state.users = users;
            const sel = document.getElementById('settingDefaultAssignee');
            if (sel) {
                users.forEach(function (u) {
                    sel.innerHTML += '<option value="' + esc(u.id) + '">' + esc(u.name) + '</option>';
                });
            }
        }).catch(function () {});
    }

    // ── Entry Point ──
    function init() {
        if (!isPage()) return;

        initTabs();

        document.getElementById('addStoreBtn')?.addEventListener('click', openAddStoreModal);
        document.getElementById('saveStoreModalBtn')?.addEventListener('click', saveStoreModal);
        document.getElementById('storeSelector')?.addEventListener('change', function (e) {
            selectStore(e.target.value);
        });
        document.getElementById('pingStoreBtn')?.addEventListener('click', function () {
            if (state.currentStore) testPingStore(state.currentStore.public_id);
            else showNotice('Выберите витрину', 'warning');
        });
        document.getElementById('rotateSecretBtn')?.addEventListener('click', rotateStoreSecret);
        document.getElementById('routingForm')?.addEventListener('submit', saveRouting);
        document.getElementById('addMappingRowBtn')?.addEventListener('click', addMappingRow);
        document.getElementById('saveStatusMappingsBtn')?.addEventListener('click', saveStatusMappings);
        document.getElementById('securityForm')?.addEventListener('submit', saveSecurity);
        document.getElementById('refreshLogBtn')?.addEventListener('click', loadSyncLog);
        document.getElementById('logDirectionFilter')?.addEventListener('change', loadSyncLog);

        loadMetadata();
        loadStores();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
