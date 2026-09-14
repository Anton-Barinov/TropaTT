// WIP Limit — task detail sidebar panel hydration + inline limit editor.
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
        var panel = document.querySelector('[data-wip-task-panel]');
        if (!panel) { return; }

        var content = panel.querySelector('[data-wip-task-content]');
        var editor = panel.querySelector('[data-wip-assignee-editor]');
        var nameEl = panel.querySelector('[data-wip-assignee-name]');
        var input = panel.querySelector('[data-wip-limit-input]');
        var saveBtn = panel.querySelector('[data-wip-save]');
        var statusEl = panel.querySelector('[data-wip-status]');
        var taskPublicId = panel.getAttribute('data-task-public-id');

        if (!content || !editor || !input || !saveBtn || !statusEl || !taskPublicId) { return; }

        function renderLoad(html) {
            content.innerHTML = html;
        }

        function setStatus(type, msg) {
            statusEl.textContent = msg || '';
            statusEl.className = 'form-text' + (type ? ' text-' + type : '');
        }

        function load() {
            renderLoad('<span class="text-muted small">Загрузка…</span>');
            editor.classList.add('d-none');

            getApi().request('_module/crm.wip-limit/limits-for-task/' + encodeURIComponent(taskPublicId), { method: 'GET', timeoutMs: 10000 })
                .then(function (env) {
                    var w = (env && env.data) || {};
                    if (!w.has_assignee) {
                        renderLoad('<span class="text-muted small">Без исполнителя</span>');
                        editor.classList.add('d-none');
                        return;
                    }

                    input.setAttribute('data-user-id', String(w.user_id));

                    var status;
                    if (w.over_limit) {
                        status = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Перегружен: ' + w.current_count + '/' + w.limit_value + '</span>';
                    } else if (w.at_limit) {
                        status = '<span class="text-warning"><i class="fa-solid fa-circle-info me-1"></i>На пределе: ' + w.current_count + '/' + w.limit_value + '</span>';
                    } else {
                        status = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>' + w.current_count + '/' + w.limit_value + '</span>';
                    }
                    renderLoad(status);

                    nameEl.textContent = w.full_name || w.login || ('#' + w.user_id);
                    input.value = w.limit_value;
                    editor.classList.remove('d-none');
                })
                .catch(function () {
                    renderLoad('<span class="text-muted small">Нет данных</span>');
                    editor.classList.add('d-none');
                });
        }

        saveBtn.addEventListener('click', function () {
            var max = parseInt(input.value, 10);
            if (!(max >= 1)) {
                setStatus('danger', 'Лимит должен быть ≥ 1');
                return;
            }

            var userId = parseInt(input.getAttribute('data-user-id') || '0', 10);
            if (!(userId > 0)) {
                setStatus('danger', 'Нет исполнителя');
                return;
            }

            saveBtn.disabled = true;
            setStatus('', 'Сохранение…');

            getApi().request('_module/crm.wip-limit/limits', { method: 'POST', timeoutMs: 10000, body: { user_id: userId, max_tasks: max } })
                .then(function () {
                    saveBtn.disabled = false;
                    setStatus('success', 'Сохранено');
                    load();
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    setStatus('danger', 'Не удалось сохранить');
                });
        });

        load();
    });
})();
