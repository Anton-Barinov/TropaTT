(function () {
    'use strict';

    const API_PREFIX = '_module/crm.notion-migration';

    let i18n = { t: function (k, d) { return d || k; } };

    let state = {
        connectionId: null,
        selectedObjects: [],
        jobId: null,
        pollTimer: null,
    };

    function isNotionMigrationPage() {
        return Boolean(
            document.body
            && document.body.dataset
            && document.body.dataset.page === 'module-notion-migration'
            && document.getElementById('connectionsList')
            && document.getElementById('migrationSteps')
        );
    }

    function init() {
        if (!isNotionMigrationPage()) {
            return;
        }

        if (typeof window.lang_messages !== 'undefined') {
            i18n = {
                t: function (key, def) {
                    const parts = key.split('.');
                    let val = window.lang_messages;
                    for (let i = 0; i < parts.length; i++) {
                        if (typeof val !== 'object' || val === null || !(parts[i] in val)) {
                            return def || key;
                        }
                        val = val[parts[i]];
                    }
                    return typeof val === 'string' ? val : (def || key);
                }
            };
        }

        document.getElementById('addConnectionBtn')?.addEventListener('click', openConnectionModal);
        document.getElementById('saveConnectionBtn')?.addEventListener('click', saveConnection);
        document.getElementById('testConnectionBtn')?.addEventListener('click', testConnection);
        document.getElementById('toObjectsBtn')?.addEventListener('click', () => goToStep('objects'));
        document.getElementById('backToConnectionsBtn')?.addEventListener('click', () => goToStep('connections'));
        document.getElementById('toSettingsBtn')?.addEventListener('click', () => goToStep('settings'));
        document.getElementById('backToObjectsBtn')?.addEventListener('click', () => goToStep('objects'));
        document.getElementById('startImportBtn')?.addEventListener('click', startImport);
        document.getElementById('cancelJobBtn')?.addEventListener('click', cancelJob);
        document.getElementById('viewKnowledgeBtn')?.addEventListener('click', openKnowledgeBase);
        document.getElementById('retryFailedBtn')?.addEventListener('click', retryFailed);
        document.getElementById('newMigrationBtn')?.addEventListener('click', resetAll);

        document.getElementById('objectSearch')?.addEventListener('input', filterObjects);

        loadConnections();
    }

    // --- API helpers ---

    function apiGet(path, query) {
        return window.CRM.api.request(API_PREFIX + path, { method: 'GET', query: query })
            .then(function (env) { return env.data || {}; });
    }

    function apiPost(path, body) {
        return window.CRM.api.request(API_PREFIX + path, { method: 'POST', body: body })
            .then(function (env) { return env.data || {}; });
    }

    function apiDelete(path) {
        return window.CRM.api.request(API_PREFIX + path, { method: 'DELETE' })
            .then(function (env) { return env.data || {}; });
    }

    // --- Step navigation ---

    function goToStep(step) {
        document.querySelectorAll('.migration-step').forEach(function (el) { el.classList.add('d-none'); });
        document.getElementById('step-' + step)?.classList.remove('d-none');

        let found = false;
        document.querySelectorAll('#migrationSteps .nav-link').forEach(function (link) {
            link.classList.remove('active');
            if (!found && link.dataset.step === step) {
                link.classList.add('active');
                found = true;
            } else if (!found) {
                link.classList.remove('disabled');
            } else {
                link.classList.add('disabled');
            }
        });
    }

    // --- Connections ---

    function loadConnections() {
        const container = document.getElementById('connectionsList');
        container.innerHTML = '<div class="text-muted py-3">' + i18n.t('notion_migration.loading', 'Загрузка...') + '</div>';

        apiGet('/connections').then(function (data) {
            const connections = data.connections || [];
            if (connections.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">' + i18n.t('notion_migration.no_connections', 'Нет подключений. Создайте новое.') + '</div>';
                return;
            }

            let html = '';
            connections.forEach(function (conn) {
                let statusBadge = '';
                if (conn.last_check_status === 'success') {
                    statusBadge = '<span class="badge bg-success ms-2">OK</span>';
                } else if (conn.last_check_status === 'failed') {
                    statusBadge = '<span class="badge bg-danger ms-2">' + i18n.t('notion_migration.error', 'Ошибка') + '</span>';
                }
                html += '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">' +
                    '<div><strong>' + htmlEscape(conn.name) + '</strong>' + statusBadge + '</div>' +
                    '<div class="btn-group">' +
                    '<button class="btn btn-sm crm-btn-secondary select-connection-btn" data-conn-id="' + conn.public_id + '">' + i18n.t('notion_migration.select', 'Выбрать') + '</button>' +
                    '<button class="btn btn-sm crm-btn-danger-soft delete-connection-btn" data-conn-id="' + conn.public_id + '"><i class="fa-solid fa-trash"></i></button>' +
                    '</div></div>';
            });
            container.innerHTML = html;

            container.querySelectorAll('.select-connection-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    state.connectionId = btn.dataset.connId;
                    loadObjects(state.connectionId);
                    document.getElementById('toObjectsBtn').style.display = '';
                });
            });

            container.querySelectorAll('.delete-connection-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (confirm(i18n.t('notion_migration.confirm_delete_connection', 'Удалить подключение?'))) {
                        apiDelete('/connections/' + btn.dataset.connId).then(function () {
                            loadConnections();
                        }).catch(function (err) {
                            alert(err.message);
                        });
                    }
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function openConnectionModal() {
        document.getElementById('connectionModalTitle').textContent = i18n.t('notion_migration.new_connection', 'Новое подключение');
        document.getElementById('connName').value = '';
        document.getElementById('connToken').value = '';
        document.getElementById('connectionTestResult').classList.add('d-none');
        document.getElementById('testConnectionBtn').dataset.connId = '';
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('connectionModal'));
        modal.show();
    }

    function saveConnection() {
        const name = document.getElementById('connName').value.trim();
        const token = document.getElementById('connToken').value.trim();

        if (!name || !token) {
            alert(i18n.t('notion_migration.fill_required', 'Заполните название и токен'));
            return;
        }

        apiPost('/connections', { name: name, token: token }).then(function (data) {
            const conn = data.connection || data;
            if (conn && conn.public_id) {
                document.getElementById('testConnectionBtn').dataset.connId = conn.public_id;
            }
            const modal = bootstrap.Modal.getInstance(document.getElementById('connectionModal'));
            if (modal) modal.hide();
            loadConnections();
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function testConnection() {
        const connId = document.getElementById('testConnectionBtn').dataset.connId;
        if (!connId) {
            alert(i18n.t('notion_migration.save_first', 'Сначала сохраните подключение'));
            return;
        }

        const resultEl = document.getElementById('connectionTestResult');
        resultEl.classList.remove('d-none', 'alert-success', 'alert-danger');
        resultEl.className = 'alert alert-info';
        resultEl.textContent = i18n.t('notion_migration.testing', 'Проверка...');

        apiPost('/connections/' + connId + '/test').then(function () {
            resultEl.classList.remove('alert-info');
            resultEl.classList.add('alert-success');
            resultEl.textContent = i18n.t('notion_migration.test_success', 'Подключение успешно');
        }).catch(function (err) {
            resultEl.classList.remove('alert-info');
            resultEl.classList.add('alert-danger');
            resultEl.textContent = err.message;
        });
    }

    // --- Objects ---

    function loadObjects(connectionId) {
        const container = document.getElementById('objectsList');
        container.innerHTML = '<div class="text-muted py-3">' + i18n.t('notion_migration.loading', 'Загрузка...') + '</div>';

        apiPost('/connections/' + connectionId + '/discover', {}).then(function (data) {
            const pages = data.pages || [];
            const databases = data.databases || [];
            if (pages.length === 0 && databases.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">' + i18n.t('notion_migration.no_objects', 'Нет доступных объектов. Подключите интеграцию к страницам в Notion.') + '</div>';
                return;
            }

            let html = '';
            databases.forEach(function (db) {
                html += objectRow('database', db.id, db.title, i18n.t('notion_migration.database', 'База данных'));
            });
            pages.forEach(function (p) {
                html += objectRow('page', p.id, p.title, i18n.t('notion_migration.page', 'Страница'));
            });
            container.innerHTML = html;

            container.querySelectorAll('.object-checkbox').forEach(function (cb) {
                cb.addEventListener('change', updateObjectSelection);
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function objectRow(type, id, title, typeLabel) {
        return '<label class="list-group-item object-item" data-title="' + htmlEscape(title || '') + '">' +
            '<input type="checkbox" class="form-check-input me-2 object-checkbox" value="' + htmlEscape(id) + '">' +
            '<div><strong>' + htmlEscape(title || id) + '</strong><br>' +
            '<small class="text-muted">' + htmlEscape(typeLabel) + '</small></div>' +
            '</label>';
    }

    function filterObjects() {
        const query = document.getElementById('objectSearch').value.toLowerCase();
        document.querySelectorAll('.object-item').forEach(function (item) {
            const title = (item.dataset.title || '').toLowerCase();
            item.style.display = title.indexOf(query) !== -1 ? '' : 'none';
        });
    }

    function updateObjectSelection() {
        const selected = [];
        document.querySelectorAll('.object-checkbox:checked').forEach(function (cb) {
            selected.push(cb.value);
        });
        state.selectedObjects = selected;
        document.getElementById('toSettingsBtn').disabled = selected.length === 0;
    }

    // --- Import ---

    function startImport() {
        const dryRun = document.getElementById('optDryRun').checked;
        const options = {
            include_comments: document.getElementById('optIncludeComments').checked,
            publish_pages: document.getElementById('optPublishPages').checked,
        };

        apiPost('/jobs', {
            connection_public_id: state.connectionId,
            mode: dryRun ? 'dry_run' : 'import',
            source_object_ids: state.selectedObjects,
            options: options,
        }).then(function (data) {
            state.jobId = data.job?.public_id || data.public_id;
            return apiPost('/jobs/' + state.jobId + '/start');
        }).then(function () {
            goToStep('run');
            document.getElementById('currentStepLabel').textContent = dryRun
                ? i18n.t('notion_migration.dry_run_progress', 'Пробный прогон...')
                : i18n.t('notion_migration.import_progress', 'Импорт...');
            startPolling(state.jobId);
        }).catch(function (err) {
            alert(err.message);
        });
    }

    // --- Polling ---

    function startPolling(jobId) {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
        }
        state.pollTimer = setInterval(function () { pollJob(jobId); }, 2500);
        pollJob(jobId);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function pollJob(jobId) {
        apiGet('/jobs/' + jobId).then(function (data) {
            const job = data.job || data;
            if (!job) return;

            const pct = parseFloat(job.progress_percent || 0);
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressPercent').textContent = pct.toFixed(0) + '%';

            const stats = job.stats || {};
            document.getElementById('statImported').textContent = stats.imported || 0;
            document.getElementById('statFailed').textContent = stats.failed || 0;
            document.getElementById('statSkipped').textContent = stats.skipped || 0;

            if (job.current_step) {
                document.getElementById('currentStepLabel').textContent = stepLabel(job.current_step);
            }

            loadLogs(jobId);

            if (job.status === 'completed' || job.status === 'failed' || job.status === 'cancelled') {
                stopPolling();
                showReport(jobId);
            }
        }).catch(function () {
            // continue polling
        });
    }

    function loadLogs(jobId) {
        apiGet('/jobs/' + jobId + '/logs', { limit: 50 }).then(function (data) {
            const logs = data.logs || [];
            const container = document.getElementById('jobLog');
            let html = '';
            logs.forEach(function (log) {
                html += '<div class="log-' + log.level + '">[' + log.created_at + '] ' + htmlEscape(log.message) + '</div>';
            });
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        }).catch(function () {});
    }

    function stepLabel(step) {
        const labels = {
            'crawl': i18n.t('notion_migration.step_crawl', 'Сбор данных...'),
            'dry_run_complete': i18n.t('notion_migration.step_dry_run_complete', 'Пробный прогон завершён'),
            'import_databases': i18n.t('notion_migration.step_import_databases', 'Импорт баз данных...'),
            'import_pages_shell': i18n.t('notion_migration.step_import_pages', 'Импорт страниц...'),
            'import_content': i18n.t('notion_migration.step_import_content', 'Перенос содержимого...'),
            'import_comments': i18n.t('notion_migration.step_import_comments', 'Импорт комментариев...'),
            'publish': i18n.t('notion_migration.step_publish', 'Публикация...'),
            'completed': i18n.t('notion_migration.step_done', 'Завершено'),
        };
        return labels[step] || step;
    }

    // --- Job control ---

    function cancelJob() {
        if (!state.jobId) return;
        if (!confirm(i18n.t('notion_migration.confirm_cancel', 'Отменить миграцию?'))) return;
        apiPost('/jobs/' + state.jobId + '/cancel').then(function () {
            stopPolling();
        }).catch(function (err) {
            alert(err.message);
        });
    }

    // --- Report ---

    function showReport(jobId) {
        goToStep('report');
        apiGet('/jobs/' + jobId + '/report').then(function (data) {
            const report = data.report || data || {};
            const container = document.getElementById('reportContent');

            let statusBadge = '';
            if (report.status === 'completed') {
                statusBadge = '<span class="badge bg-success fs-6">' + i18n.t('notion_migration.completed', 'Завершено') + '</span>';
            } else if (report.status === 'failed') {
                statusBadge = '<span class="badge bg-danger fs-6">' + i18n.t('notion_migration.failed', 'Ошибки') + '</span>';
            } else if (report.status === 'cancelled') {
                statusBadge = '<span class="badge bg-warning fs-6">' + i18n.t('notion_migration.cancelled', 'Отменено') + '</span>';
            }

            const items = report.items || {};
            container.innerHTML = '<div class="d-flex justify-content-between align-items-center mb-3">' +
                '<h5 class="mb-0">' + i18n.t('notion_migration.report_summary', 'Итог') + ' ' + statusBadge + '</h5>' +
                '</div>' +
                '<div class="row mb-3">' +
                '<div class="col-md-4"><div class="crm-card text-center py-3"><div class="h2">' + (items.imported || 0) + '</div><small class="text-muted">' + i18n.t('notion_migration.imported', 'Импортировано') + '</small></div></div>' +
                '<div class="col-md-4"><div class="crm-card text-center py-3"><div class="h2 text-danger">' + (items.failed || 0) + '</div><small class="text-muted">' + i18n.t('notion_migration.failed', 'Ошибок') + '</small></div></div>' +
                '<div class="col-md-4"><div class="crm-card text-center py-3"><div class="h2 text-warning">' + (items.skipped || 0) + '</div><small class="text-muted">' + i18n.t('notion_migration.skipped', 'Пропущено') + '</small></div></div>' +
                '</div>';

            apiGet('/jobs/' + jobId + '/items', { status: 'failed', limit: 50 }).then(function (itemsData) {
                const failedItems = itemsData.items || [];
                if (failedItems.length > 0) {
                    let failedHtml = '<h6>' + i18n.t('notion_migration.report_items', 'Элементы с ошибками') + '</h6>' +
                        '<div class="table-responsive"><table class="table table-sm">' +
                        '<thead><tr><th>' + i18n.t('notion_migration.report_source', 'Источник') + '</th><th>' + i18n.t('notion_migration.report_type', 'Тип') + '</th><th>' + i18n.t('notion_migration.report_error', 'Ошибка') + '</th></tr></thead><tbody>';
                    failedItems.forEach(function (item) {
                        failedHtml += '<tr><td>' + htmlEscape(item.source_key || item.source_id) + '</td>' +
                            '<td>' + htmlEscape(item.source_type) + '</td>' +
                            '<td><small class="text-muted">' + htmlEscape(item.error_message || '') + '</small></td></tr>';
                    });
                    failedHtml += '</tbody></table></div>';
                    container.innerHTML += failedHtml;
                }
            }).catch(function () {});
        }).catch(function (err) {
            document.getElementById('reportContent').innerHTML = '<div class="text-danger">' + htmlEscape(err.message) + '</div>';
        });
    }

    function openKnowledgeBase() {
        window.location.href = 'index.php?route=knowledge';
    }

    function retryFailed() {
        if (!state.jobId) return;
        apiPost('/jobs/' + state.jobId + '/retry-failed').then(function (data) {
            const job = data.job || data;
            state.jobId = job?.public_id || state.jobId;
            startPolling(state.jobId);
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function resetAll() {
        state.connectionId = null;
        state.selectedObjects = [];
        state.jobId = null;
        stopPolling();
        goToStep('connections');
        document.getElementById('toObjectsBtn').style.display = 'none';
        document.getElementById('toSettingsBtn').disabled = true;
        loadConnections();
    }

    // --- Utils ---

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
