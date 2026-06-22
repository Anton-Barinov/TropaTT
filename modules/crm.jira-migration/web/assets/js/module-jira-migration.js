(function() {
    'use strict';

    const BASE_API = 'api/v1/modules/jira-migration';
    let selectedConnection = null;
    let selectedProjects = [];
    let currentJobId = null;
    let pollTimer = null;

    const steps = ['connections', 'source', 'settings', 'preview', 'run', 'report'];

    function showStep(stepId) {
        document.querySelectorAll('.migration-step').forEach(el => el.classList.add('d-none'));
        const el = document.getElementById('step-' + stepId);
        if (el) el.classList.remove('d-none');

        document.querySelectorAll('#migrationSteps .nav-link').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.step === stepId) {
                link.classList.add('active');
            }
            if (steps.indexOf(link.dataset.step) <= steps.indexOf(stepId)) {
                link.classList.remove('disabled');
            } else {
                link.classList.add('disabled');
            }
        });
    }

    // ── Step 1: Connections ──

    async function loadConnections() {
        const list = document.getElementById('connectionsList');
        list.innerHTML = '<div class="text-muted py-3">Загрузка...</div>';

        try {
            const resp = await window.CRM.api.request(BASE_API + '/connections');
            const connections = resp.data?.connections || [];
            
            if (connections.length === 0) {
                list.innerHTML = '<div class="text-muted py-3">' + window.CRM.i18n.t('jira_migration.no_connections', 'Нет подключений. Создайте новое к Jira Cloud.') + '</div>';
                document.getElementById('toSourceBtn').style.display = 'none';
                return;
            }

            let html = '<div class="list-group">';
            connections.forEach(conn => {
                const statusClass = conn.status === 'active' ? 'connection-card' : 'connection-card status-failed';
                html += '<div class="list-group-item ' + statusClass + '" data-id="' + conn.public_id + '">';
                html += '<div class="d-flex w-100 justify-content-between">';
                html += '<h6 class="mb-1">' + window.CRM.escapeHtml(conn.name) + '</h6>';
                html += '<small>' + window.CRM.escapeHtml(conn.site_url || '') + '</small>';
                html += '</div>';
                html += '<small class="text-muted">' + (conn.email ? window.CRM.escapeHtml(conn.email) : '') + '</small>';
                html += '<div class="mt-2">';
                html += '<button class="btn crm-btn-sm crm-btn-secondary me-1 select-connection-btn" data-id="' + conn.public_id + '">' + window.CRM.i18n.t('jira_migration.select', 'Выбрать') + '</button>';
                html += '<button class="btn crm-btn-sm crm-btn-danger-soft delete-connection-btn" data-id="' + conn.public_id + '"><i class="fa-solid fa-trash"></i></button>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            list.innerHTML = html;

            document.querySelectorAll('.select-connection-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    selectedConnection = this.dataset.id;
                    document.getElementById('toSourceBtn').style.display = 'inline-block';
                });
            });

            document.querySelectorAll('.delete-connection-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    if (!confirm(window.CRM.i18n.t('jira_migration.confirm_delete_connection', 'Удалить подключение?'))) return;
                    await window.CRM.api.request(BASE_API + '/connections/' + this.dataset.id, { method: 'DELETE' });
                    loadConnections();
                });
            });

        } catch (err) {
            list.innerHTML = '<div class="text-danger py-3">' + window.CRM.i18n.t('jira_migration.load_error', 'Ошибка загрузки') + ': ' + err.message + '</div>';
        }
    }

    document.getElementById('addConnectionBtn').addEventListener('click', function() {
        document.getElementById('connName').value = '';
        document.getElementById('connSiteUrl').value = '';
        document.getElementById('connEmail').value = '';
        document.getElementById('connApiToken').value = '';
        document.getElementById('connectionTestResult').classList.add('d-none');
        document.getElementById('connectionModalTitle').textContent = window.CRM.i18n.t('jira_migration.new_connection', 'Новое подключение');
        new bootstrap.Modal(document.getElementById('connectionModal')).show();
    });

    document.getElementById('testConnectionBtn').addEventListener('click', async function() {
        const siteUrl = document.getElementById('connSiteUrl').value.trim();
        const email = document.getElementById('connEmail').value.trim();
        const token = document.getElementById('connApiToken').value;

        if (!siteUrl) {
            alert(window.CRM.i18n.t('jira_migration.fill_base_url', 'Укажите URL'));
            return;
        }

        // Create temporary connection to test
        try {
            const name = document.getElementById('connName').value.trim() || 'Test';
            const resp = await window.CRM.api.request(BASE_API + '/connections', {
                method: 'POST',
                body: JSON.stringify({ name: name + ' (test)', site_url: siteUrl, email: email, api_token: token })
            });

            if (resp.data?.connection?.public_id) {
                const testResp = await window.CRM.api.request(BASE_API + '/connections/' + resp.data.connection.public_id + '/test', { method: 'POST' });
                const result = document.getElementById('connectionTestResult');
                result.classList.remove('d-none');

                if (testResp.success) {
                    result.className = 'alert alert-success mt-3';
                    result.textContent = window.CRM.i18n.t('jira_migration.test_success', 'Подключение успешно') + ': ' + (testResp.data?.user?.display_name || '') + ' (' + (testResp.data?.projects_count || 0) + ' projects)';
                } else {
                    result.className = 'alert alert-danger mt-3';
                    result.textContent = window.CRM.i18n.t('jira_migration.test_fail', 'Ошибка подключения') + ': ' + (testResp.error?.message || 'Unknown error');
                }
            }
        } catch (err) {
            const result = document.getElementById('connectionTestResult');
            result.classList.remove('d-none');
            result.className = 'alert alert-danger mt-3';
            result.textContent = window.CRM.i18n.t('jira_migration.test_fail', 'Ошибка подключения') + ': ' + err.message;
        }
    });

    document.getElementById('saveConnectionBtn').addEventListener('click', async function() {
        const name = document.getElementById('connName').value.trim();
        const siteUrl = document.getElementById('connSiteUrl').value.trim();
        const email = document.getElementById('connEmail').value.trim();
        const token = document.getElementById('connApiToken').value;

        if (!name || !siteUrl) {
            alert(window.CRM.i18n.t('jira_migration.fill_required', 'Заполните название и URL'));
            return;
        }

        try {
            await window.CRM.api.request(BASE_API + '/connections', {
                method: 'POST',
                body: JSON.stringify({ name: name, site_url: siteUrl, email: email, api_token: token })
            });
            bootstrap.Modal.getInstance(document.getElementById('connectionModal')).hide();
            loadConnections();
        } catch (err) {
            alert(window.CRM.i18n.t('jira_migration.error', 'Ошибка') + ': ' + err.message);
        }
    });

    document.getElementById('toSourceBtn').addEventListener('click', function() {
        showStep('source');
        loadProjects();
    });

    // ── Step 2: Source Selection ──

    async function loadProjects() {
        const list = document.getElementById('projectsList');
        list.innerHTML = '<div class="text-muted py-3">Загрузка...</div>';

        try {
            const resp = await window.CRM.api.request(BASE_API + '/discover', {
                method: 'POST',
                body: JSON.stringify({ connection_public_id: selectedConnection })
            });

            const projects = resp.data?.projects || [];
            selectedProjects = [];

            if (projects.length === 0) {
                list.innerHTML = '<div class="text-muted py-3">' + window.CRM.i18n.t('jira_migration.no_projects', 'Нет доступных проектов') + '</div>';
                return;
            }

            let html = '';
            projects.forEach(proj => {
                html += '<div class="list-group-item list-group-item-action project-item" data-key="' + proj.key + '">';
                html += '<div class="d-flex w-100 justify-content-between">';
                html += '<div><input type="checkbox" class="form-check-input project-check me-2" value="' + proj.key + '">';
                html += '<strong>' + window.CRM.escapeHtml(proj.name) + '</strong>';
                html += '<small class="text-muted ms-2">(' + window.CRM.escapeHtml(proj.key) + ')</small></div>';
                html += '</div></div>';
            });
            list.innerHTML = html;

            list.querySelectorAll('.project-check').forEach(cb => {
                cb.addEventListener('change', function() {
                    if (this.checked) {
                        if (!selectedProjects.includes(this.value)) selectedProjects.push(this.value);
                    } else {
                        selectedProjects = selectedProjects.filter(k => k !== this.value);
                    }
                    document.getElementById('toSettingsBtn').disabled = selectedProjects.length === 0;
                });
            });

            list.querySelectorAll('.project-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.type !== 'checkbox') {
                        const cb = this.querySelector('.project-check');
                        if (cb) cb.click();
                    }
                });
            });

            document.getElementById('toSettingsBtn').disabled = true;

        } catch (err) {
            list.innerHTML = '<div class="text-danger py-3">' + window.CRM.i18n.t('jira_migration.load_error', 'Ошибка загрузки') + ': ' + err.message + '</div>';
        }
    }

    document.getElementById('backToConnectionsBtn').addEventListener('click', function() {
        showStep('connections');
    });

    document.getElementById('toSettingsBtn').addEventListener('click', function() {
        showStep('settings');
    });

    // ── Step 3: Settings → Preview ──

    document.getElementById('backToSourceBtn').addEventListener('click', function() {
        showStep('source');
    });

    document.getElementById('toPreviewBtn').addEventListener('click', async function() {
        showStep('preview');
        const content = document.getElementById('previewContent');
        content.innerHTML = '<div class="text-muted py-3">' + window.CRM.i18n.t('jira_migration.loading', 'Загрузка...') + '</div>';

        try {
            const resp = await window.CRM.api.request(BASE_API + '/dry-run', {
                method: 'POST',
                body: JSON.stringify({
                    connection_public_id: selectedConnection,
                    project_keys: selectedProjects
                })
            });

            const summary = resp.data?.summary || {};
            const html = `
                <div class="row">
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value">${summary.total_projects || 0}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.projects', 'проектов')}</div>
                    </div>
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value">${summary.total_issues || 0}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.issues', 'задач')}</div>
                    </div>
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value">${summary.total_attachments_estimate || 0}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.attachments', 'вложений')}</div>
                    </div>
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value">${summary.total_comments_estimate || 0}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.comments', 'комментариев')}</div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4 preview-stat">
                        <div class="stat-value">${summary.total_subtasks || 0}</div>
                        <div class="stat-label">Подзадачи</div>
                    </div>
                    <div class="col-md-4 preview-stat">
                        <div class="stat-value">${summary.total_epics || 0}</div>
                        <div class="stat-label">Epic</div>
                    </div>
                    <div class="col-md-4 preview-stat">
                        <div class="stat-value">${summary.total_worklogs_estimate || 0}</div>
                        <div class="stat-label">Worklogs</div>
                    </div>
                </div>
                ${summary.warnings && summary.warnings.length ? `
                    <div class="alert alert-warning mt-3">
                        <strong>Предупреждения:</strong>
                        <ul>${summary.warnings.map(w => '<li>' + window.CRM.escapeHtml(w) + '</li>').join('')}</ul>
                    </div>
                ` : ''}
            `;
            content.innerHTML = html;

            // Store job ID for import
            if (resp.data?.job?.public_id) {
                currentJobId = resp.data.job.public_id;
            }
        } catch (err) {
            content.innerHTML = '<div class="text-danger py-3">' + window.CRM.i18n.t('jira_migration.load_error', 'Ошибка загрузки') + ': ' + err.message + '</div>';
        }
    });

    document.getElementById('backToSettingsBtn').addEventListener('click', function() {
        showStep('settings');
    });

    // ── Start Import ──

    document.getElementById('startImportBtn').addEventListener('click', async function() {
        showStep('run');

        try {
            const resp = await window.CRM.api.request(BASE_API + '/jobs', {
                method: 'POST',
                body: JSON.stringify({
                    connection_public_id: selectedConnection,
                    mode: 'import',
                    project_keys: selectedProjects,
                    options: {
                        import_attachments: document.getElementById('optImportAttachments').checked,
                        import_comments: document.getElementById('optImportComments').checked,
                        import_worklogs: document.getElementById('optImportWorklogs').checked,
                        import_sprints: document.getElementById('optImportSprints').checked,
                        import_links: document.getElementById('optImportLinks').checked,
                        import_labels: document.getElementById('optImportLabels').checked,
                    }
                })
            });

            currentJobId = resp.data?.job?.public_id;
            if (currentJobId) {
                await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/run', { method: 'POST' });
                startPolling();
            }
        } catch (err) {
            document.getElementById('jobLog').innerHTML += '<div class="log-entry error">Error: ' + err.message + '</div>';
        }
    });

    // ── Polling ──

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollJob, 2000);
        pollJob();
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollJob() {
        if (!currentJobId) return;

        try {
            const resp = await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId);
            const job = resp.data?.job;
            if (!job) return;

            // Update progress
            const percent = job.progress_percent || 0;
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressPercent').textContent = Math.round(percent) + '%';
            document.getElementById('currentStepLabel').textContent = job.current_step || 'Running...';

            // Update stats from report
            const reportResp = await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/report');
            const report = reportResp.data?.report;
            if (report?.items) {
                document.getElementById('statImported').textContent = (report.items.imported || 0) + (report.items.updated || 0);
                document.getElementById('statFailed').textContent = report.items.failed || 0;
                document.getElementById('statSkipped').textContent = report.items.skipped || 0;
            }

            // Update logs
            const logResp = await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/logs?limit=10');
            const logs = logResp.data?.logs || [];
            const logEl = document.getElementById('jobLog');
            logs.forEach(log => {
                const line = document.createElement('div');
                line.className = 'log-entry ' + (log.level || 'info');
                line.textContent = (log.created_at || '') + ' [' + (log.level || '') + '] ' + (log.message || '');
                logEl.appendChild(line);
            });
            logEl.scrollTop = logEl.scrollHeight;

            // Check final states
            if (['completed', 'failed', 'cancelled'].includes(job.status)) {
                stopPolling();
                document.getElementById('pauseJobBtn').style.display = 'none';
                document.getElementById('resumeJobBtn').style.display = 'none';
                document.getElementById('cancelJobBtn').style.display = 'none';

                if (job.status === 'completed') {
                    setTimeout(() => showReport(), 1000);
                }
            }

            // Show pause/resume buttons based on status
            if (job.status === 'paused') {
                document.getElementById('pauseJobBtn').style.display = 'none';
                document.getElementById('resumeJobBtn').style.display = 'inline-block';
            } else if (job.status === 'running') {
                document.getElementById('pauseJobBtn').style.display = 'inline-block';
                document.getElementById('resumeJobBtn').style.display = 'none';
            }

        } catch (err) {
            // Polling errors are normal - job might still be processing
        }
    }

    // ── Job controls ──

    document.getElementById('pauseJobBtn').addEventListener('click', async function() {
        if (!currentJobId) return;
        try {
            await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/pause', { method: 'POST' });
        } catch (err) {
            console.error('Pause failed:', err);
        }
    });

    document.getElementById('resumeJobBtn').addEventListener('click', async function() {
        if (!currentJobId) return;
        try {
            await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/run', { method: 'POST' });
            startPolling();
        } catch (err) {
            console.error('Resume failed:', err);
        }
    });

    document.getElementById('cancelJobBtn').addEventListener('click', async function() {
        if (!currentJobId) return;
        if (!confirm(window.CRM.i18n.t('jira_migration.confirm_cancel', 'Отменить миграцию?'))) return;
        try {
            await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/cancel', { method: 'POST' });
        } catch (err) {
            console.error('Cancel failed:', err);
        }
    });

    // ── Report ──

    async function showReport() {
        showStep('report');
        const content = document.getElementById('reportContent');
        content.innerHTML = '<div class="text-muted py-3">Загрузка...</div>';

        try {
            const resp = await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/report');
            const report = resp.data?.report || {};

            const items = report.items || {};
            const totalItems = Object.values(items).reduce((a, b) => a + b, 0);

            content.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value">${items.imported || 0}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.imported', 'Импортировано')}</div>
                    </div>
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value text-danger">${items.failed || 0}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.failed', 'Ошибок')}</div>
                    </div>
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value text-warning">${items.skipped || 0}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.skipped', 'Пропущено')}</div>
                    </div>
                    <div class="col-md-3 preview-stat">
                        <div class="stat-value">${totalItems}</div>
                        <div class="stat-label">${window.CRM.i18n.t('jira_migration.preview_totals', 'Всего')}</div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>${window.CRM.i18n.t('jira_migration.report_summary', 'Итог')}</strong></p>
                        <ul class="list-unstyled">
                            <li>Mode: ${report.mode || 'N/A'}</li>
                            <li>Status: ${report.status || 'N/A'}</li>
                            <li>Progress: ${Math.round(report.progress_percent || 0)}%</li>
                            <li>Unresolved: ${report.unresolved_count || 0}</li>
                            <li>Unsupported fields: ${report.unsupported_count || 0}</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Timeline</strong></p>
                        <ul class="list-unstyled">
                            <li>Started: ${report.started_at || 'N/A'}</li>
                            <li>Finished: ${report.finished_at || 'N/A'}</li>
                            <li>Created: ${report.created_at || 'N/A'}</li>
                        </ul>
                    </div>
                </div>
            `;
        } catch (err) {
            content.innerHTML = '<div class="text-danger py-3">Error: ' + err.message + '</div>';
        }
    }

    document.getElementById('retryFailedBtn').addEventListener('click', async function() {
        if (!currentJobId) return;
        try {
            await window.CRM.api.request(BASE_API + '/jobs/' + currentJobId + '/retry-failed', { method: 'POST' });
            showStep('run');
            startPolling();
        } catch (err) {
            console.error('Retry failed:', err);
        }
    });

    document.getElementById('newMigrationBtn').addEventListener('click', function() {
        currentJobId = null;
        selectedProjects = [];
        showStep('connections');
        loadConnections();
    });

    document.getElementById('openProjectsBtn').addEventListener('click', function() {
        window.location.href = 'index.php?route=projects';
    });

    // ── Init ──

    document.addEventListener('DOMContentLoaded', function() {
        loadConnections();
    });

})();
