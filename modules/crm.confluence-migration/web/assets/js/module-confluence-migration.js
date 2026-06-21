(function () {
    'use strict';

    const API_PREFIX = '_module/crm.confluence-migration';

    /** @type {{t: function(string, string=): string}} */
    let i18n = { t: function (k, d) { return d || k; } };

    let state = {
        connectionId: null,
        selectedSpaces: [],
        jobId: null,
        pollTimer: null,
    };

    function isConfluenceMigrationPage() {
        return Boolean(
            document.body
            && document.body.dataset
            && document.body.dataset.page === 'module-confluence-migration'
            && document.getElementById('connectionsList')
            && document.getElementById('migrationSteps')
        );
    }

    function init() {
        if (!isConfluenceMigrationPage()) {
            return;
        }

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
        document.getElementById('toMappingsBtn')?.addEventListener('click', runDryRun);
        document.getElementById('backToMappingsBtn')?.addEventListener('click', () => goToStep('settings'));
        document.getElementById('toPreviewFromMappingsBtn')?.addEventListener('click', function () {
            if (state.jobId) {
                goToStep('preview');
                showPreview(state.jobId);
            } else {
                goToStep('settings');
            }
        });
        document.getElementById('backToMappingsFromPreviewBtn')?.addEventListener('click', () => goToStep('mappings'));
        document.getElementById('startImportBtn')?.addEventListener('click', startImport);
        document.getElementById('pauseJobBtn')?.addEventListener('click', pauseJob);
        document.getElementById('resumeJobBtn')?.addEventListener('click', resumeJob);
        document.getElementById('cancelJobBtn')?.addEventListener('click', cancelJob);
        document.getElementById('viewKnowledgeBtn')?.addEventListener('click', openKnowledgeBase);
        document.getElementById('retryFailedBtn')?.addEventListener('click', retryFailed);
        document.getElementById('newMigrationBtn')?.addEventListener('click', resetAll);

        document.getElementById('spaceSearch')?.addEventListener('input', filterSpaces);

        // Mapping tabs
        document.querySelectorAll('[data-mapping-tab]').forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelectorAll('[data-mapping-tab]').forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                var target = tab.dataset.mappingTab;
                document.getElementById('mappingsList').classList.toggle('d-none', target !== 'users');
                document.getElementById('mappingsGroupsList').classList.toggle('d-none', target !== 'groups');
                if (target === 'groups' && state.connectionId) {
                    loadGroupMappings(state.connectionId);
                }
            });
        });

        // Auto-load connections
        loadConnections();
    }

    // --- API helpers ---

    function apiGet(path) {
        return window.CRM.api.request(API_PREFIX + path, { method: 'GET' })
            .then(function (env) { return env.data || {}; });
    }

    function apiPost(path, body) {
        return window.CRM.api.request(API_PREFIX + path, { method: 'POST', body: body })
            .then(function (env) { return env.data || {}; });
    }

    function apiPatch(path, body) {
        return window.CRM.api.request(API_PREFIX + path, { method: 'PATCH', body: body })
            .then(function (env) { return env.data || {}; });
    }

    function apiDelete(path) {
        return window.CRM.api.request(API_PREFIX + path, { method: 'DELETE' })
            .then(function (env) { return env.data || {}; });
    }

    function apiPathWithConn(path) {
        return '/connections/' + state.connectionId + path;
    }

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

        // Load data on step enter
        if (step === 'mappings' && state.connectionId) {
            loadMappings(state.connectionId);
        }
    }

    // --- Connections ---

    function loadConnections() {
        var container = document.getElementById('connectionsList');
        container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.loading', 'Загрузка...') + '</div>';

        apiGet('/connections').then(function (data) {
            var connections = data.connections || [];
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
        var modalEl = document.getElementById('connectionModal');
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
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

        var payload = { name: name, base_url: baseUrl, auth_type: 'api_token', email: email, api_token: token };
        apiPost('/connections', payload).then(function (data) {
            var conn = data.connection || data;
            if (conn && conn.public_id) {
                document.getElementById('testConnectionBtn').dataset.connId = conn.public_id;
            }
            var modalEl = document.getElementById('connectionModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadConnections();
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function testConnection() {
        var connId = document.getElementById('testConnectionBtn').dataset.connId;
        if (!connId) {
            alert(i18n.t('confluence_migration.save_first', 'Сначала сохраните подключение'));
            return;
        }

        var resultEl = document.getElementById('connectionTestResult');
        resultEl.classList.remove('d-none', 'alert-success', 'alert-danger');
        resultEl.className = 'alert alert-info';
        resultEl.textContent = i18n.t('confluence_migration.testing', 'Проверка...');

        apiPost('/connections/' + connId + '/test').then(function (data) {
            resultEl.classList.remove('alert-info');
            var resp = data || {};
            if (resp.success) {
                resultEl.classList.add('alert-success');
                resultEl.textContent = i18n.t('confluence_migration.test_success', 'Подключение успешно');
            } else {
                resultEl.classList.add('alert-danger');
                resultEl.textContent = resp.message || i18n.t('confluence_migration.test_fail', 'Ошибка подключения');
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

        apiPost('/connections/' + connectionId + '/discover', {}).then(function (data) {
            var spaces = data.spaces || [];
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

        apiPost('/jobs', {
            connection_public_id: state.connectionId,
            mode: 'dry_run',
            source_space_keys: state.selectedSpaces,
            options: options,
        }).then(function (data) {
            state.jobId = data.job?.public_id || data.public_id;
            return apiPost('/jobs/' + state.jobId + '/start');
        }).then(function () {
            goToStep('run');
            document.getElementById('currentStepLabel').textContent = i18n.t('confluence_migration.dry_run_progress', 'Пробный прогон...');
            startPolling(state.jobId, true);
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function loadMappings(connectionId) {
        var container = document.getElementById('mappingsList');
        container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.loading', 'Загрузка...') + '</div>';

        apiGet('/connections/' + connectionId + '/user-mappings').then(function (data) {
            var mappings = data.mappings || [];
            if (mappings.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.mapping_no_users', 'Нет пользователей для сопоставления') + '</div>';
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-sm">' +
                '<thead><tr><th>' + i18n.t('confluence_migration.mapping_confluence_user', 'Пользователь Confluence') + '</th>' +
                '<th>' + i18n.t('confluence_migration.mapping_crm_user', 'Пользователь CRM') + '</th>' +
                '<th>' + i18n.t('confluence_migration.mapping_status', 'Статус') + '</th>' +
                '<th></th></tr></thead><tbody>';

            mappings.forEach(function (m) {
                var statusLabel = m.mapping_status === 'unmapped'
                    ? '<span class="badge bg-warning">' + i18n.t('confluence_migration.mapping_unmapped', 'Не сопоставлен') + '</span>'
                    : m.mapping_status === 'auto'
                    ? '<span class="badge bg-success">' + i18n.t('confluence_migration.mapping_auto', 'Авто') + '</span>'
                    : '<span class="badge bg-info">' + i18n.t('confluence_migration.mapping_manual', 'Ручной') + '</span>';

                html += '<tr data-mapping-id="' + m.id + '">' +
                    '<td>' + htmlEscape(m.confluence_display_name || m.confluence_account_id) + '</td>' +
                    '<td>' +
                    '<select class="form-select form-select-sm crm-user-select" data-mapping-id="' + m.id + '" style="min-width:180px">' +
                    '<option value="">— ' + i18n.t('confluence_migration.mapping_unmapped', 'Не сопоставлен') + ' —</option>' +
                    '</select>' +
                    '</td>' +
                    '<td class="mapping-status-cell">' + statusLabel + '</td>' +
                    '<td><button class="btn btn-sm crm-btn-primary save-mapping-btn" data-mapping-id="' + m.id + '">' + i18n.t('confluence_migration.mapping_save', 'Сохранить') + '</button></td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;

            // Load CRM users for each select
            container.querySelectorAll('.save-mapping-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var mid = btn.dataset.mappingId;
                    var select = container.querySelector('.crm-user-select[data-mapping-id="' + mid + '"]');
                    var crmUserPublicId = select ? select.value : '';
                    saveUserMapping(mid, crmUserPublicId);
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function saveUserMapping(mappingId, crmUserPublicId) {
        apiPatch('/connections/' + state.connectionId + '/user-mappings/' + mappingId, {
            crm_user_public_id: crmUserPublicId,
            mapping_status: crmUserPublicId ? 'manual' : 'unmapped',
        }).then(function () {
            var statusCell = document.querySelector('tr[data-mapping-id="' + mappingId + '"] .mapping-status-cell');
            if (statusCell) {
                var badge = crmUserPublicId
                    ? '<span class="badge bg-info">' + i18n.t('confluence_migration.mapping_manual', 'Ручной') + '</span>'
                    : '<span class="badge bg-warning">' + i18n.t('confluence_migration.mapping_unmapped', 'Не сопоставлен') + '</span>';
                statusCell.innerHTML = badge;
            }
        }).catch(function (err) {
            alert(err.message);
        });
    }

    function loadGroupMappings(connectionId) {
        var container = document.getElementById('mappingsGroupsList');
        container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.loading', 'Загрузка...') + '</div>';

        apiGet('/connections/' + connectionId + '/group-mappings').then(function (data) {
            var mappings = data.mappings || [];
            if (mappings.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.mapping_no_groups', 'Нет групп для сопоставления') + '</div>';
                return;
            }
            var html = '<div class="table-responsive"><table class="table table-sm">' +
                '<thead><tr><th>' + i18n.t('confluence_migration.mapping_confluence_user', 'Группа Confluence') + '</th>' +
                '<th>' + i18n.t('confluence_migration.mapping_crm_user', 'Группа CRM') + '</th>' +
                '<th>' + i18n.t('confluence_migration.mapping_status', 'Статус') + '</th></tr></thead><tbody>';
            mappings.forEach(function (m) {
                html += '<tr><td>' + htmlEscape(m.confluence_group_name) + '</td>' +
                    '<td>' + htmlEscape(m.crm_subject_public_id || '—') + '</td>' +
                    '<td>' + htmlEscape(m.mapping_status) + '</td></tr>';
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }).catch(function () {
            container.innerHTML = '<div class="text-muted py-3">' + i18n.t('confluence_migration.mapping_no_groups', 'Нет групп для сопоставления') + '</div>';
        });
    }

    // --- Import ---

    function startImport() {
        var options = gatherOptions();

        apiPost('/jobs', {
            connection_public_id: state.connectionId,
            mode: 'import',
            source_space_keys: state.selectedSpaces,
            options: options,
        }).then(function (data) {
            state.jobId = data.job?.public_id || data.public_id;
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
            var job = data.job || data;
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
                    goToStep('mappings');
                    loadMappings(state.connectionId);
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
            var logs = data.logs || [];
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
            'import_versions': i18n.t('confluence_migration.step_import_versions', 'Импорт версий...'),
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
            var report = data.report || data || {};
            var container = document.getElementById('previewContent');
            var items = report.items || {};

            var html = '<div class="preview-section">' +
                '<h6>' + i18n.t('confluence_migration.preview_items', 'Элементы миграции') + '</h6>' +
                '<div class="d-flex gap-3">' +
                '<span class="preview-stat"><strong>' + (items.imported || 0) + '</strong> ' + i18n.t('confluence_migration.imported', 'Импортировано') + '</span>' +
                '<span class="preview-stat"><strong>' + (items.pending || 0) + '</strong> ' + i18n.t('confluence_migration.pending', 'Ожидает') + '</span>' +
                '<span class="preview-stat"><strong>' + (items.skipped || 0) + '</strong> ' + i18n.t('confluence_migration.skipped', 'Пропущено') + '</span>' +
                '</div></div>';

            if (report.unresolved_links_count > 0) {
                html += '<div class="preview-section mt-3"><p class="text-warning"><i class="fa-solid fa-triangle-exclamation"></i> ' +
                    i18n.t('confluence_migration.preview_unresolved', 'Есть нераспознанные ссылки') + ' (' + report.unresolved_links_count + ')</p></div>';
            }
            if (report.unsupported_macros_count > 0) {
                html += '<div class="preview-section mt-3"><p class="text-warning"><i class="fa-solid fa-puzzle-piece"></i> ' +
                    i18n.t('confluence_migration.preview_macros', 'Неподдерживаемые макросы') + ' (' + report.unsupported_macros_count + ')</p></div>';
            }

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
            var report = data.report || data || {};
            var container = document.getElementById('reportContent');

            var statusBadge = '';
            if (report.status === 'completed') {
                statusBadge = '<span class="badge bg-success fs-6">' + i18n.t('confluence_migration.completed', 'Завершено') + '</span>';
            } else if (report.status === 'failed') {
                statusBadge = '<span class="badge bg-danger fs-6">' + i18n.t('confluence_migration.failed', 'Ошибки') + '</span>';
            } else if (report.status === 'cancelled') {
                statusBadge = '<span class="badge bg-warning fs-6">' + i18n.t('confluence_migration.cancelled', 'Отменено') + '</span>';
            }

            var items = report.items || {};
            var html = '<div class="d-flex justify-content-between align-items-center mb-3">' +
                '<h5 class="mb-0">' + i18n.t('confluence_migration.report_summary', 'Итог') + ' ' + statusBadge + '</h5>' +
                '</div>' +
                '<div class="row mb-3">' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2">' + (items.imported || 0) + '</div><small class="text-muted">' + i18n.t('confluence_migration.imported', 'Импортировано') + '</small></div></div>' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2 text-danger">' + (items.failed || 0) + '</div><small class="text-muted">' + i18n.t('confluence_migration.failed', 'Ошибок') + '</small></div></div>' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2 text-warning">' + (items.skipped || 0) + '</div><small class="text-muted">' + i18n.t('confluence_migration.skipped', 'Пропущено') + '</small></div></div>' +
                '<div class="col-md-3"><div class="crm-card text-center py-3"><div class="h2">' + (report.progress_percent || 0) + '%</div><small class="text-muted">' + i18n.t('confluence_migration.progress', 'Прогресс') + '</small></div></div>' +
                '</div>';

            var unresolvedLinks = report.unresolved_links || [];
            if (unresolvedLinks.length > 0) {
                html += '<h6>' + i18n.t('confluence_migration.report_unresolved_links', 'Нераспознанные ссылки') + ' (' + unresolvedLinks.length + ')</h6>' +
                    '<div class="small text-muted mb-2">' + i18n.t('confluence_migration.report_unresolved_hint', 'Эти ссылки не были преобразованы. Проверьте отчет после завершения.') + '</div>';
            }

            container.innerHTML = html;

            // Load failed items separately
            apiGet('/jobs/' + jobId + '/items?status=failed&limit=50').then(function (itemsData) {
                var failedItems = itemsData.items || [];
                if (failedItems.length > 0) {
                    var failedHtml = '<h6>' + i18n.t('confluence_migration.report_items', 'Элементы с ошибками') + '</h6>' +
                        '<div class="table-responsive"><table class="table table-sm">' +
                        '<thead><tr><th>' + i18n.t('confluence_migration.report_source', 'Источник') + '</th><th>' + i18n.t('confluence_migration.report_type', 'Тип') + '</th><th>' + i18n.t('confluence_migration.report_error', 'Ошибка') + '</th></tr></thead><tbody>';

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
            var job = data.job || data;
            state.jobId = job?.public_id || state.jobId;
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
