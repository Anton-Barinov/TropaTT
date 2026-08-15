(function () {
    'use strict';

    const API_PREFIX = '_module/crm.linear-migration';

    let state = { connectionId: null, selectedTeams: [], jobId: null, pollTimer: null };

    function isLinearPage() {
        return Boolean(document.body && document.body.dataset && document.body.dataset.page === 'module-linear-migration');
    }

    function init() {
        if (!isLinearPage()) return;

        document.getElementById('addConnectionBtn')?.addEventListener('click', openConnectionModal);
        document.getElementById('saveConnectionBtn')?.addEventListener('click', saveConnection);
        document.getElementById('toSourceBtn')?.addEventListener('click', function () { goTo('source'); loadTeams(); });
        document.getElementById('backToConnectionsBtn')?.addEventListener('click', function () { goTo('connections'); });
        document.getElementById('toRunBtn')?.addEventListener('click', function () { goTo('run'); });
        document.getElementById('startImportBtn')?.addEventListener('click', startImport);

        loadConnections();
    }

    function api(path, method, body) {
        return window.CRM.api.request(API_PREFIX + path, { method: method, body: body })
            .then(function (env) { return env.data || {}; });
    }

    function goTo(step) {
        ['connections', 'source', 'run'].forEach(function (s) {
            document.getElementById('step-' + s).classList.toggle('d-none', s !== step);
        });
    }

    function loadConnections() {
        const container = document.getElementById('connectionsList');
        api('/connections', 'GET').then(function (data) {
            const connections = data.connections || [];
            if (connections.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">Нет подключений.</div>';
                return;
            }
            container.innerHTML = '<div class="list-group">' + connections.map(function (c) {
                const badge = c.last_check_status === 'success' ? '<span class="badge bg-success ms-2">OK</span>'
                    : (c.last_check_status === 'failed' ? '<span class="badge bg-danger ms-2">Ошибка</span>' : '');
                return '<div class="list-group-item d-flex justify-content-between align-items-center">' +
                    '<div><strong>' + htmlEscape(c.name) + '</strong>' + badge + '</div>' +
                    '<div class="btn-group">' +
                    '<button class="btn btn-sm crm-btn-secondary select-conn-btn" data-id="' + htmlEscape(c.public_id) + '">Выбрать</button>' +
                    '<button class="btn btn-sm crm-btn-danger-soft delete-conn-btn" data-id="' + htmlEscape(c.public_id) + '"><i class="fa-solid fa-trash"></i></button>' +
                    '</div></div>';
            }).join('') + '</div>';

            container.querySelectorAll('.select-conn-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    state.connectionId = btn.dataset.id;
                    document.getElementById('toSourceBtn').style.display = '';
                });
            });
            container.querySelectorAll('.delete-conn-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (confirm('Удалить подключение?')) {
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
        document.getElementById('connApiKey').value = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('connectionModal')).show();
    }

    function saveConnection() {
        const name = document.getElementById('connName').value.trim();
        const apiKey = document.getElementById('connApiKey').value.trim();
        if (!name || !apiKey) { alert('Заполните название и API-ключ'); return; }
        api('/connections', 'POST', { name: name, api_key: apiKey }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('connectionModal')).hide();
            loadConnections();
        }).catch(function (err) { alert(err.message); });
    }

    function loadTeams() {
        const container = document.getElementById('teamsList');
        container.innerHTML = '<div class="text-muted py-3">Загрузка...</div>';
        api('/connections/' + state.connectionId + '/discover', 'POST', {}).then(function (data) {
            const teams = data.teams || [];
            if (teams.length === 0) {
                container.innerHTML = '<div class="text-muted py-3">Команды не найдены.</div>';
                return;
            }
            container.innerHTML = teams.map(function (t) {
                return '<label class="list-group-item"><input type="checkbox" class="form-check-input me-2 team-checkbox" value="' + htmlEscape(t.id) + '">' +
                    '<strong>' + htmlEscape(t.name) + '</strong> <code class="text-muted">' + htmlEscape(t.key) + '</code></label>';
            }).join('');
            container.querySelectorAll('.team-checkbox').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    state.selectedTeams = Array.from(document.querySelectorAll('.team-checkbox:checked')).map(function (x) { return x.value; });
                    document.getElementById('toRunBtn').disabled = state.selectedTeams.length === 0;
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + htmlEscape(err.message) + '</div>';
        });
    }

    function startImport() {
        const dryRun = document.getElementById('optDryRun').checked;
        api('/jobs', {
            connection_public_id: state.connectionId,
            mode: dryRun ? 'dry_run' : 'import',
            source_team_ids: state.selectedTeams,
            options: {},
        }).then(function (data) {
            state.jobId = data.job && data.job.public_id ? data.job.public_id : data.public_id;
            return runChunk();
        }).catch(function (err) { alert(err.message); });
    }

    function runChunk() {
        return api('/jobs/' + state.jobId + '/run', 'POST', {}).then(function (data) {
            updateProgress(data);
            if (!data.done) {
                return new Promise(function (resolve) { setTimeout(resolve, 300); }).then(runChunk);
            }
        });
    }

    function updateProgress(data) {
        const job = data.job || {};
        const pct = parseFloat(job.progress_percent || 0);
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressPercent').textContent = pct.toFixed(0) + '%';
        document.getElementById('currentStepLabel').textContent = job.current_step || '';
        loadLogs();
    }

    function loadLogs() {
        api('/jobs/' + state.jobId + '/logs?limit=50', 'GET').then(function (data) {
            const logs = data.logs || [];
            const container = document.getElementById('jobLog');
            container.innerHTML = logs.map(function (l) {
                return '<div class="log-' + l.level + '">[' + l.created_at + '] ' + htmlEscape(l.message) + '</div>';
            }).join('');
            container.scrollTop = container.scrollHeight;
        }).catch(function () {});
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
