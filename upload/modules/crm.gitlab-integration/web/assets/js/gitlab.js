(function () {
    'use strict';

    const API_PREFIX = '_module/crm.gitlab-integration';
    let state = { projects: [] };

    function isPage() {
        return Boolean(document.body && document.body.dataset && document.body.dataset.page === 'module-gitlab-integration');
    }

    function init() {
        if (!isPage()) return;

        document.getElementById('addConnectionBtn')?.addEventListener('click', openConnectionModal);
        document.getElementById('saveConnectionBtn')?.addEventListener('click', saveConnection);
        document.getElementById('addLinkBtn')?.addEventListener('click', openLinkModal);
        document.getElementById('saveLinkBtn')?.addEventListener('click', saveLink);
        document.getElementById('linkConnection')?.addEventListener('change', loadGitlabProjects);

        loadConnections();
        loadLinks();
        loadCrmProjects();
    }

    function api(path, method, body) {
        return window.CRM.api.request(API_PREFIX + path, { method: method, body: body })
            .then(function (env) { return env.data || {}; });
    }

    function coreApi(path, method, body) {
        return window.CRM.api.request(path, { method: method, body: body })
            .then(function (env) { return env.data || {}; });
    }

    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
        });
    }

    // ── Connections ──

    function loadConnections() {
        const container = document.getElementById('connectionsList');
        api('/connections', 'GET').then(function (data) {
            const connections = data.connections || [];
            if (!connections.length) {
                container.innerHTML = '<div class="text-muted py-3">Подключений пока нет.</div>';
                return;
            }
            container.innerHTML = '<div class="list-group">' + connections.map(function (c) {
                const badge = c.last_status === 'success' ? '<span class="badge bg-success ms-2">OK</span>'
                    : (c.last_status === 'failed' ? '<span class="badge bg-danger ms-2">Ошибка</span>' : '');
                return '<div class="list-group-item d-flex justify-content-between align-items-center">' +
                    '<div><strong>' + esc(c.name) + '</strong>' + badge +
                    '<div class="text-muted small">' + esc(c.base_url || '') + '</div></div>' +
                    '<div class="btn-group">' +
                    '<button class="btn btn-sm crm-btn-secondary test-conn-btn" data-id="' + esc(c.public_id) + '"><i class="fa-solid fa-plug"></i> Тест</button>' +
                    '<button class="btn btn-sm crm-btn-danger-soft delete-conn-btn" data-id="' + esc(c.public_id) + '"><i class="fa-solid fa-trash"></i></button>' +
                    '</div></div>';
            }).join('') + '</div>';

            container.querySelectorAll('.test-conn-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    api('/connections/' + btn.dataset.id + '/test', 'POST', {}).then(function () {
                        alert('Подключение успешно.');
                    }).catch(function (err) { alert(err.message); }).finally(function () { btn.disabled = false; loadConnections(); });
                });
            });
            container.querySelectorAll('.delete-conn-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (confirm('Удалить подключение?')) {
                        api('/connections/' + btn.dataset.id, 'DELETE').then(function () { loadConnections(); loadLinks(); })
                            .catch(function (err) { alert(err.message); });
                    }
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + esc(err.message) + '</div>';
        });
    }

    function openConnectionModal() {
        document.getElementById('connName').value = '';
        document.getElementById('connBaseUrl').value = 'https://gitlab.com/api/v4';
        document.getElementById('connToken').value = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('connectionModal')).show();
    }

    function saveConnection() {
        const name = document.getElementById('connName').value.trim();
        const baseUrl = document.getElementById('connBaseUrl').value.trim();
        const token = document.getElementById('connToken').value.trim();
        if (!name || !token) { alert('Заполните название и токен'); return; }
        api('/connections', 'POST', { name: name, base_url: baseUrl, token: token }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('connectionModal')).hide();
            loadConnections();
        }).catch(function (err) { alert(err.message); });
    }

    // ── Links ──

    function loadLinks() {
        const container = document.getElementById('linksList');
        api('/links', 'GET').then(function (data) {
            const links = data.links || [];
            if (!links.length) {
                container.innerHTML = '<div class="text-muted py-3">Связанных проектов пока нет.</div>';
                return;
            }
            container.innerHTML = '<div class="table-responsive"><table class="table table-sm align-middle">' +
                '<thead><tr><th>Проект GitLab</th><th>Проект TropaTT</th><th>Синхронизация</th><th class="text-end">Действия</th></tr></thead><tbody>' +
                links.map(function (l) {
                    const synced = l.last_synced_at ? '<span class="text-muted small">' + esc(l.last_synced_at) + '</span>' : '<span class="text-muted small">ещё не синхронизирован</span>';
                    return '<tr>' +
                        '<td><strong>' + esc(l.project_path) + '</strong>' + (l.is_dirty === '1' || l.is_dirty === 1 ? ' <span class="badge bg-warning text-dark">ожидает</span>' : '') + '</td>' +
                        '<td><code>' + esc(l.project_public_id) + '</code></td>' +
                        '<td>' + synced + '</td>' +
                        '<td class="text-end"><div class="btn-group">' +
                        '<button class="btn btn-sm crm-btn-primary sync-link-btn" data-id="' + esc(l.public_id) + '"><i class="fa-solid fa-rotate"></i> Синхр.</button>' +
                        '<button class="btn btn-sm crm-btn-secondary logs-link-btn" data-id="' + esc(l.public_id) + '"><i class="fa-solid fa-list"></i></button>' +
                        '<button class="btn btn-sm crm-btn-danger-soft delete-link-btn" data-id="' + esc(l.public_id) + '"><i class="fa-solid fa-trash"></i></button>' +
                        '</div></td></tr>';
                }).join('') + '</tbody></table></div>';

            container.querySelectorAll('.sync-link-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    api('/links/' + btn.dataset.id + '/sync', 'POST', {}).then(function (d) {
                        const c = d.counts || {};
                        alert('Синхронизация завершена: создано ' + (c.created || 0) + ', обновлено ' + (c.updated || 0) + ', ошибок ' + (c.failed || 0) + '.');
                    }).catch(function (err) { alert(err.message); }).finally(function () { btn.disabled = false; loadLinks(); });
                });
            });
            container.querySelectorAll('.logs-link-btn').forEach(function (btn) {
                btn.addEventListener('click', function () { loadLogs(btn.dataset.id); });
            });
            container.querySelectorAll('.delete-link-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (confirm('Удалить связь?')) {
                        api('/links/' + btn.dataset.id, 'DELETE').then(function () { loadLinks(); }).catch(function (err) { alert(err.message); });
                    }
                });
            });
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + esc(err.message) + '</div>';
        });
    }

    function openLinkModal() {
        const connSelect = document.getElementById('linkConnection');
        api('/connections', 'GET').then(function (data) {
            const connections = data.connections || [];
            connSelect.innerHTML = connections.map(function (c) {
                return '<option value="' + esc(c.public_id) + '">' + esc(c.name) + ' (' + esc(c.base_url || '') + ')</option>';
            }).join('');
            if (!connections.length) {
                alert('Сначала добавьте подключение.');
                return;
            }
            renderCrmProjects();
            loadGitlabProjects();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('linkModal')).show();
        }).catch(function (err) { alert(err.message); });
    }

    function loadCrmProjects() {
        coreApi('api/v1/projects', 'GET').then(function (data) {
            state.projects = data.items || data.projects || [];
        }).catch(function () { state.projects = []; });
    }

    function renderCrmProjects() {
        const select = document.getElementById('linkCrmProject');
        select.innerHTML = state.projects.map(function (p) {
            const title = p.title || p.name || p.public_id;
            return '<option value="' + esc(p.public_id) + '">' + esc(title) + '</option>';
        }).join('');
        if (!state.projects.length) {
            select.innerHTML = '<option value="">— нет проектов —</option>';
        }
    }

    function loadGitlabProjects() {
        const connectionId = document.getElementById('linkConnection').value;
        const select = document.getElementById('linkProjectPath');
        if (!connectionId) return;
        select.innerHTML = '<option value="">Загрузка...</option>';
        api('/connections/' + connectionId + '/discover', 'POST', {}).then(function (data) {
            const projects = data.projects || [];
            select.innerHTML = projects.map(function (p) {
                return '<option value="' + esc(p.path_with_namespace) + '">' + esc(p.path_with_namespace) + '</option>';
            }).join('');
            if (!projects.length) {
                select.innerHTML = '<option value="">— проекты не найдены —</option>';
            }
        }).catch(function (err) {
            select.innerHTML = '<option value="">— ошибка загрузки —</option>';
            console.error(err);
        });
    }

    function saveLink() {
        const connectionPublicId = document.getElementById('linkConnection').value;
        const projectPath = document.getElementById('linkProjectPath').value;
        const projectPublicId = document.getElementById('linkCrmProject').value;
        if (!connectionPublicId || !projectPath || !projectPublicId) { alert('Заполните все поля'); return; }
        api('/links', 'POST', {
            connection_public_id: connectionPublicId,
            project_path: projectPath,
            project_public_id: projectPublicId,
        }).then(function (data) {
            bootstrap.Modal.getInstance(document.getElementById('linkModal')).hide();
            document.getElementById('webhookUrl').value = data.webhook_url || '';
            document.getElementById('webhookSecret').value = data.webhook_secret || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('webhookModal')).show();
            loadLinks();
        }).catch(function (err) { alert(err.message); });
    }

    function loadLogs(linkPublicId) {
        const container = document.getElementById('logsList');
        container.innerHTML = '<div class="text-muted py-3">Загрузка...</div>';
        api('/links/' + linkPublicId + '/logs?limit=50', 'GET').then(function (data) {
            const logs = data.logs || [];
            if (!logs.length) {
                container.innerHTML = '<div class="text-muted py-3">Журнал пуст.</div>';
                return;
            }
            container.innerHTML = logs.map(function (l) {
                return '<div class="small border-bottom py-1"><span class="text-muted">' + esc(l.created_at) + '</span> <span class="badge ' + (l.level === 'error' ? 'bg-danger' : 'bg-secondary') + '">' + esc(l.level) + '</span> ' + esc(l.message) + '</div>';
            }).join('');
        }).catch(function (err) {
            container.innerHTML = '<div class="text-danger py-3">' + esc(err.message) + '</div>';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
