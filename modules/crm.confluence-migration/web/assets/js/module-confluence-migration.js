(function () {
    'use strict';

    const API_BASE = '/api/v1/module/crm.confluence-migration';

    /** @type {{t: function(string, string=): string}} */
    let i18n = { t: function (k, d) { return d || k; } };

    let state = {
        connectionId: null,
        selectedSpaces: [],
        jobId: null,
        pollTimer: null,
    };

    function init() {
        if (typeof window.lang_messages !== 'undefined') {
            const i18nObj = {
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
            i18n = i18nObj;
        }

        // Step navigation
        document.getElementById('addConnectionBtn')?.addEventListener('click', openConnectionModal);
        document.getElementById('saveConnectionBtn')?.addEventListener('click', saveConnection);
        document.getElementById('testConnectionBtn')?.addEventListener('click', testConnection);
        document.getElementById('toSourceBtn')?.addEventListener('click', () => goToStep('source'));
        document.getElementById('backToConnectionsBtn')?.addEventListener('click', () => goToStep('connections'));
        document.getElementById('toSettingsBtn')?.addEventListener('click', () => goToStep('settings'));
        document.getElementById('backToSourceBtn')?.addEventListener('click', () => goToStep('source'));
        document.getElementById('runDryRunBtn')?.addEventListener('click', runDryRun);
        document.getElementById('backToSettingsBtn')?.addEventListener('click', () => goToStep('settings'));
        document.getElementById('startImportBtn')?.addEventListener('click', startImport);
        document.getElementById('pauseJobBtn')?.addEventListener('click', pauseJob);
        document.getElementById('resumeJobBtn')?.addEventListener('click', resumeJob);
        document.getElementById('cancelJobBtn')?.addEventListener('click', cancelJob);
        document.getElementById('viewKnowledgeBtn')?.addEventListener('click', openKnowledgeBase);
        document.getElementById('retryFailedBtn')?.addEventListener('click', retryFailed);
        document.getElementById('newMigrationBtn')?.addEventListener('click', resetAll);

        document.getElementById('spaceSearch')?.addEventListener('input', filterSpaces);

        // Auto-load connections
        loadConnections();
    }

    // --- API helpers ---

    function apiUrl(path) {
        return API_BASE + path;
    }

    function apiFetch(method, path, body) {
        const opts = {
            method: method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        };
        if (body !== undefined) {
            opts.body = JSON.stringify(body);
        }
        return fetch(apiUrl(path), opts).then(function (r) {
            if (!r.ok) {
                return r.json().then(function (err) { throw new Error(err.error || err.message || 'Request failed'); });
            }
            return r.json();
        });
    }

    function apiGet(path) { return apiFetch('GET', path); }
    function apiPost(path, body) { return apiFetch('POST', path, body); }
    function apiPatch(path, body) { return apiFetch('PATCH', path, body); }
    function apiDelete(path) { return apiFetch('DELETE', path); }

    // --- Step navigation ---

    function goToStep(step) {
        document.querySelectorAll('.migration-step').forEach(function (el) { el.classList.add('d-none'); });
        document.getElementById('step-' + step)?.classList.remove('d-none');

        document.querySelectorAll('#migrationSteps .nav-link').forEach(function (link) {
            link.classList.remove('active');
            link.classList.remove('disabled');
        });

        var found = false;
        document.querySelectorAll('#migrationSteps .nav-link').forEach(function (link) {
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
        var container = document.getElementById('connectionsList');
        container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.loading', 'Загрузка...') + '</div>';

        apiGet('/connections').then(function (data) {
            var connections = data.data || [];
            if (connections.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.no_connections', 'Нет подключений. Создайте новое.') + '</div>';
                return;
            }

            var html = '';
            connections.forEach(function (conn) {
                var statusBadge = '';
                if (conn.last_check_status === 'success') {
                    statusBadge = '<span class="badge bg-success ms-2">OK</span>';
                } else if (conn.last_check_status === 'error') {
                    statusBadge = '<span class="badge bg-danger ms-2">' + i18n.t('confluence_migration.error', 'Ошибка') + '</span>';
                }
                html += '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-conn-id="' + conn.public_id + '">' +
                    '<div><strong>' + htmlEscape(conn.name) + '</strong><br><small class="text-muted">' + htmlEscape(conn.base_url) + '</small>' + statusBadge + '</div>' +
                    '<div class="btn-group">' +
                    '<button class="btn btn-sm crm-btn-secondary select-connection-btn" data-conn-id="' + conn.public_id + '">' + i18n.t('confluence_migration.select', 'Выбрать') + '</button>' +
                    '<button class="btn btn-sm crm-btn-danger-soft delete-connection-btn" data-conn-id="' + conn.public_id + '"><i class="fa-solid fa-trash"></i></button>' +
                    '</div></div>';
            });
            container.innerHTML = html;

            container.querySelectorAll('.select-connection-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    state.connectionId = btn.dataset.connId;
                    loadSpaces(state.connectionId);
                    document.getElementById('toSourceBtn').style.display = '';
                });
            });

            container.querySelectorAll('.delete-connection-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (confirm(i18n.t('confluence_migration.confirm_delete_connection', 'Удалить подключение?'))) {
                        apiDelete('/connections/' + btn.dataset.connId).then(function () {
                            loadConnections();
                        }).catch(function (err) {
                            alert(err.message);
                        });
                    }
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + i18n.t('confluence_migration.load_error', 'Ошибка загрузки') + ': ' + htmlEscape(err.message) + '</div>';
        });
    }

    function openConnectionModal() {
        document.getElementById('connectionModalTitle').textContent = i18n.t('confluence_migration.new_connection', 'Новое подключение');
        document.getElementById('connName').value = '';
        document.getElementById('connBaseUrl').value = '';
        document.getElementById('connEmail').value = '';
        document.getElementById('connApiToken').value = '';
        document.getElementById('connectionTestResult').classList.add('d-none');
        document.getElementById('testConnectionBtn').dataset.connId = '';
        var modal = new bootstrap.Modal(document.getElementById('connectionModal'));
        modal.show();
    }

    function saveConnection() {
        var name = document.getElementById('connName').value.trim();
        var baseUrl = document.getElementById('connBaseUrl').value.trim();
        var email = document.getElementById('connEmail').value.trim();
        var token = document.getElementById('connApiToken').value;

        if (!name || !baseUrl) {
            alert(i18n.t('confluence_migration.fill_required', 'Заполните название и URL'));
            return;
        }

        var payload = { name: name, base_url: baseUrl, email: email, api_token: token };
        apiPost('/connections', payload).then(function () {
            var modal = bootstrap.Modal.getInstance(document.getElementById('connectionModal'));
            if (modal) modal.hide();
            loadConnections();
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function testConnection() {
        var baseUrl = document.getElementById('connBaseUrl').value.trim();
        var email = document.getElementById('connEmail').value.trim();
        var token = document.getElementById('connApiToken').value;

        if (!baseUrl) {
            alert(i18n.t('confluence_migration.fill_base_url', 'Укажите URL'));
            return;
        }

        var resultEl = document.getElementById('connectionTestResult');
        resultEl.classList.remove('d-none', 'alert-success', 'alert-danger');
        resultEl.className = 'alert alert-info';
        resultEl.textContent = i18n.t('confluence_migration.testing', 'Проверка...');

        apiPost('/connections/test', { base_url: baseUrl, email: email, api_token: token }).then(function (data) {
            resultEl.classList.remove('alert-info');
            if (data.success) {
                resultEl.classList.add('alert-success');
                resultEl.textContent = i18n.t('confluence_migration.test_success', 'Подключение успешно');
            } else {
                resultEl.classList.add('alert-danger');
                resultEl.textContent = data.message || i18n.t('confluence_migration.test_fail', 'Ошибка подключения');
            }
        }).catch(function (err) {
            resultEl.classList.remove('alert-info');
            resultEl.classList.add('alert-danger');
            resultEl.textContent = err.message;
        });
    }

    // --- Spaces ---

    function loadSpaces(connectionId) {
        var container = document.getElementById('spacesList');
        container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.loading', 'Загрузка...') + '</div>';

        apiGet('/connections/' + connectionId + '/discover').then(function (data) {
            var spaces = data.data || [];
            if (spaces.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.no_spaces', 'Нет доступных пространств') + '</div>';
                return;
            }

            var html = '';
            spaces.forEach(function (space) {
                html += '<label class="space-checkbox-label mb-2 space-item" data-key="' + htmlEscape(space.key) + '">' +
                    '<input type="checkbox" class="form-check-input space-checkbox" value="' + htmlEscape(space.key) + '"' +
                    ' data-name="' + htmlEscape(space.name) + '"' +
                    ' data-pages="' + (space.pages || 0) + '">' +
                    '<div><strong>' + htmlEscape(space.name) + '</strong><br>' +
                    '<small class="text-muted">' + htmlEscape(space.key) + ' — ' + (space.pages || 0) + ' ' + i18n.t('confluence_migration.pages', 'страниц') + '</small></div>' +
                    '</label>';
            });
            container.innerHTML = html;

            container.querySelectorAll('.space-checkbox').forEach(function (cb) {
                cb.addEventListener('change', updateSpaceSelection);
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + i18n.t('confluence_migration.load_error', 'Ошибка загрузки') + ': ' + htmlEscape(err.message) + '</div>';
        });
    }

    function filterSpaces() {
        var query = document.getElementById('spaceSearch').value.toLowerCase();
        document.querySelectorAll('.space-item').forEach(function (item) {
            var key = item.dataset.key.toLowerCase();
            item.style.display = key.indexOf(query) !== -1 ? '' : 'none';
        });
    }

    function updateSpaceSelection() {
        var selected = [];
        document.querySelectorAll('.space-checkbox:checked').forEach(function (cb) {
            selected.push(cb.value);
        });
        state.selectedSpaces = selected;
        document.getElementById('toSettingsBtn').disabled = selected.length === 0;
    }

    // --- Dry Run ---

    function runDryRun() {
        var options = gatherOptions();
        options.mode = 'dry_run';

        apiPost('/jobs', {
            connection_id: state.connectionId,
            mode: 'dry_run',
            source_space_keys: state.selectedSpaces,
            options: options,
        }).then(function (data) {
            state.jobId = data.data.public_id;
            return apiPost('/jobs/' + state.jobId + '/start');
        }).then(function () {
            goToStep('run');
            document.getElementById('currentStepLabel').textContent = i18n.t('confluence_migration.dry_run_progress', 'Пробный прогон...');
            startPolling(state.jobId, true);
        }).catch(function (err) {
            alert(err.message);
        });
    }

    // --- Import ---

    function startImport() {
        var options = gatherOptions();
        options.mode = 'full';

        apiPost('/jobs', {
            connection_id: state.connectionId,
            mode: 'full',
            source_space_keys: state.selectedSpaces,
            options: options,
        }).then(function (data) {
            state.jobId = data.data.public_id;
            return apiPost('/jobs/' + state.jobId + '/start');
        }).then(function () {
            goToStep('run');
            document.getElementById('currentStepLabel').textContent = i18n.t('confluence_migration.import_progress', 'Импорт...');
            startPolling(state.jobId, false);
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function gatherOptions() {
        return {
            import_attachments: document.getElementById('optImportAttachments').checked,
            import_comments: document.getElementById('optImportComments').checked,
            import_labels: document.getElementById('optImportLabels').checked,
            publish_pages: document.getElementById('optPublishPages').checked,
            duplicate_mode: document.getElementById('optDuplicateMode').value,
            macro_handling: document.getElementById('optMacroHandling').value,
        };
    }

    // --- Polling ---

    function startPolling(jobId, isDryRun) {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
        }
        state.pollTimer = setInterval(function () { pollJob(jobId, isDryRun); }, 2000);
        pollJob(jobId, isDryRun);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function pollJob(jobId, isDryRun) {
        apiGet('/jobs/' + jobId).then(function (data) {
            var job = data.data;
            if (!job) return;

            // Progress bar
            var pct = parseFloat(job.progress_percent || 0);
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressPercent').textContent = pct.toFixed(0) + '%';

            // Stats
            var stats = job.stats || {};
            document.getElementById('statImported').textContent = stats.imported || 0;
            document.getElementById('statFailed').textContent = stats.failed || 0;
            document.getElementById('statSkipped').textContent = stats.skipped || 0;

            // Step
            if (job.current_step) {
                document.getElementById('currentStepLabel').textContent = stepLabel(job.current_step);
            }

            // Logs
            loadLogs(jobId);

            // Status transitions
            if (job.status === 'completed' || job.status === 'failed' || job.status === 'cancelled') {
                stopPolling();
                if (isDryRun) {
                    showPreview(jobId);
                } else {
                    showReport(jobId);
                }
            } else if (job.status === 'paused') {
                document.getElementById('pauseJobBtn').style.display = 'none';
                document.getElementById('resumeJobBtn').style.display = '';
            } else {
                document.getElementById('pauseJobBtn').style.display = '';
                document.getElementById('resumeJobBtn').style.display = 'none';
            }
        }).catch(function () {
            // continue polling
        });
    }

    function loadLogs(jobId) {
        apiGet('/jobs/' + jobId + '/logs?limit=50').then(function (data) {
            var logs = data.data || [];
            var container = document.getElementById('jobLog');
            var html = '';
            logs.forEach(function (log) {
                html += '<div class="log-' + log.level + '">[' + log.created_at + '] ' + htmlEscape(log.message) + '</div>';
            });
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        }).catch(function () {});
    }

    function stepLabel(step) {
        var labels = {
            'connecting': i18n.t('confluence_migration.step_connecting', 'Подключение к Confluence...'),
            'discovering': i18n.t('confluence_migration.step_discovering', 'Получение списка пространств...'),
            'crawling': i18n.t('confluence_migration.step_crawling', 'Сбор данных...'),
            'import_spaces': i18n.t('confluence_migration.step_import_spaces', 'Импорт пространств...'),
            'import_pages': i18n.t('confluence_migration.step_import_pages', 'Импорт страниц...'),
            'import_attachments': i18n.t('confluence_migration.step_import_attachments', 'Импорт вложений...'),
            'import_labels': i18n.t('confluence_migration.step_import_labels', 'Импорт тегов...'),
            'import_comments': i18n.t('confluence_migration.step_import_comments', 'Импорт комментариев...'),
            'import_content': i18n.t('confluence_migration.step_import_content', 'Перенос содержимого...'),
            'publishing': i18n.t('confluence_migration.step_publishing', 'Публикация...'),
            'reindexing': i18n.t('confluence_migration.step_reindexing', 'Индексация...'),
            'done': i18n.t('confluence_migration.step_done', 'Завершено'),
        };
        return labels[step] || step;
    }

    // --- Preview ---

    function showPreview(jobId) {
        goToStep('preview');
        apiGet('/jobs/' + jobId + '/report').then(function (data) {
            var report = data.data || {};
            var container = document.getElementById('previewContent');

            var html = '<div class="preview-section">' +
                '<h6>' + i18n.t('confluence_migration.preview_spaces', 'Пространства для переноса') + '</h6>';

            var spaces = report.spaces || [];
            if (spaces.length > 0) {
                html += '<ul>';
                spaces.forEach(function (s) {
                    html += '<li><strong>' + htmlEscape(s.name) + '</strong> (' + htmlEscape(s.key) + ') — ' +
                        (s.pages || 0) + ' ' + i18n.t('confluence_migration.pages', 'страниц') + ', ' +
                        (s.attachments || 0) + ' ' + i18n.t('confluence_migration.attachments', 'вложений') + '</li>';
                });
                html += '</ul>';
            } else {
                html += '<p class="text-muted">—</p>';
            }

            html += '</div><div class="preview-section">' +
                '<h6>' + i18n.t('confluence_migration.preview_totals', 'Всего') + '</h6>' +
                '<div class="d-flex gap-3">' +
                '<span class="preview-stat"><strong>' + (report.total_pages || 0) + '</strong> ' + i18n.t('confluence_migration.pages', 'страниц') + '</span>' +
                '<span class="preview-stat"><strong>' + (report.total_attachments || 0) + '</strong> ' + i18n.t('confluence_migration.attachments', 'вложений') + '</span>' +
                '<span class="preview-stat"><strong>' + (report.total_comments || 0) + '</strong> ' + i18n.t('confluence_migration.comments', 'комментариев') + '</span>' +
                '</div></div>';

            container.innerHTML = html;
        }).catch(function (err) {
            document.getElementById('previewContent').innerHTML = '<div class="text-danger">' + htmlEscape(err.message) + '</div>';
        });
    }

    // --- Job control ---

    function pauseJob() {
        if (!state.jobId) return;
        apiPost('/jobs/' + state.jobId + '/pause').then(function () {
            document.getElementById('pauseJobBtn').style.display = 'none';
            document.getElementById('resumeJobBtn').style.display = '';
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function resumeJob() {
        if (!state.jobId) return;
        apiPost('/jobs/' + state.jobId + '/resume').then(function () {
            document.getElementById('pauseJobBtn').style.display = '';
            document.getElementById('resumeJobBtn').style.display = 'none';
            startPolling(state.jobId, false);
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function cancelJob() {
        if (!state.jobId) return;
        if (!confirm(i18n.t('confluence_migration.confirm_cancel', 'Отменить миграцию?'))) return;
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
            var report = data.data || {};
            var container = document.getElementById('reportContent');

            var statusBadge = '';
            if (report.status === 'completed') {
                statusBadge = '<span class="badge bg-success fs-6">' + i18n.t('confluence_migration.completed', 'Завершено') + '</span>';
            } else if (report.status === 'failed') {
                statusBadge = '<span class="badge bg-danger fs-6">' + i18n.t('confluence_migration.failed', 'Ошибки') + '</span>';
            } else if (report.status === 'cancelled') {
                statusBadge = '<span class="badge bg-warning fs-6">' + i18n.t('confluence_migration.cancelled', 'Отменено') + '</span>';
            }

            var html = '<div class="d-flex justify-content-between align-items-center mb-3">' +
                '<h5 class="mb-0">' + i18n.t('confluence_migration.report_summary', 'Итог') + ' ' + statusBadge + '</h5>' +
                '</div>' +
                '<div class="row mb-3">' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2">' + (report.stats?.imported || 0) + '</div><small class="text-muted">' + i18n.t('confluence_migration.imported', 'Импортировано') + '</small></div></div>' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2 text-danger">' + (report.stats?.failed || 0) + '</div><small class="text-muted">' + i18n.t('confluence_migration.failed', 'Ошибок') + '</small></div></div>' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2 text-warning">' + (report.stats?.skipped || 0) + '</div><small class="text-muted">' + i18n.t('confluence_migration.skipped', 'Пропущено') + '</small></div></div>' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2">' + (report.total_time || '—') + '</div><small class="text-muted">' + i18n.t('confluence_migration.total_time', 'Время') + '</small></div></div>' +
                '</div>';

            var items = report.items || [];
            if (items.length > 0) {
                html += '<h6>' + i18n.t('confluence_migration.report_items', 'Элементы с ошибками') + '</h6>' +
                    '<div class="table-responsive"><table class="table table-sm">' +
                    '<thead><tr><th>' + i18n.t('confluence_migration.report_source', 'Источник') + '</th><th>' + i18n.t('confluence_migration.report_type', 'Тип') + '</th><th>' + i18n.t('confluence_migration.report_status', 'Статус') + '</th><th>' + i18n.t('confluence_migration.report_error', 'Ошибка') + '</th></tr></thead><tbody>';

                items.forEach(function (item) {
                    if (item.status === 'failed') {
                        html += '<tr><td>' + htmlEscape(item.source_key || item.source_id) + '</td>' +
                            '<td>' + htmlEscape(item.source_type) + '</td>' +
                            '<td><span class="badge bg-danger">' + i18n.t('confluence_migration.failed', 'Ошибка') + '</span></td>' +
                            '<td><small class="text-muted">' + htmlEscape(item.error_message || '') + '</small></td></tr>';
                    }
                });

                html += '</tbody></table></div>';
            }

            var unresolvedLinks = report.unresolved_links || [];
            if (unresolvedLinks.length > 0) {
                html += '<h6>' + i18n.t('confluence_migration.report_unresolved_links', 'Нераспознанные ссылки') + ' (' + unresolvedLinks.length + ')</h6>' +
                    '<div class="small text-muted mb-2">' + i18n.t('confluence_migration.report_unresolved_hint', 'Эти ссылки не были преобразованы. Проверьте отчет после завершения.') + '</div>';
            }

            container.innerHTML = html;
        }).catch(function (err) {
            document.getElementById('reportContent').innerHTML = '<div class="text-danger">' + htmlEscape(err.message) + '</div>';
        });
    }

    function openKnowledgeBase() {
        window.location.href = 'index.php?route=knowledge-list';
    }

    function retryFailed() {
        if (!state.jobId) return;
        apiPost('/jobs/' + state.jobId + '/retry-failed').then(function (data) {
            state.jobId = data.data.public_id;
            startPolling(state.jobId, false);
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function resetAll() {
        state.connectionId = null;
        state.selectedSpaces = [];
        state.jobId = null;
        stopPolling();
        goToStep('connections');
        document.getElementById('toSourceBtn').style.display = 'none';
        document.getElementById('pauseJobBtn').style.display = '';
        document.getElementById('resumeJobBtn').style.display = 'none';
        loadConnections();
    }

    // --- Utils ---

    function htmlEscape(str) {
        if (typeof str !== 'string') return String(str || '');
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
