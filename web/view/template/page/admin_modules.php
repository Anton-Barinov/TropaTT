<?php declare(strict_types=1); ?>
<?php $title = $t('admin_modules.title', 'TropaTT — Модули'); ?>
<body data-page="admin-modules" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page crm-admin-modules-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_modules.link_admin"><?= htmlspecialchars($t('admin_modules.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_modules.breadcrumb"><?= htmlspecialchars($t('admin_modules.breadcrumb', 'Модули'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_modules.page_title"><?= htmlspecialchars($t('admin_modules.page_title', 'Модули'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_modules.subtitle"><?= htmlspecialchars($t('admin_modules.subtitle', 'Управление модулями расширения: установка, активация, деактивация и удаление.'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="crm-page-actions"><a class="btn crm-btn-primary" href="index.php?route=admin-modules-install" data-i18n="admin_modules.link_install_module"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t('admin_modules.link_install_module', 'Установить модуль'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<div class="crm-card crm-section-card p-0 table-responsive mb-3 crm-admin-modules-table-card"><table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_modules.th_module"><?= htmlspecialchars($t('admin_modules.th_module', 'Модуль'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_version"><?= htmlspecialchars($t('admin_modules.th_version', 'Версия'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_vendor"><?= htmlspecialchars($t('admin_modules.th_vendor', 'Вендор'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules.th_status"><?= htmlspecialchars($t('admin_modules.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:220px" data-i18n="admin_modules.th_actions"><?= htmlspecialchars($t('admin_modules.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="moduleTableBody">
<tr><td colspan="5" class="text-muted" data-i18n="admin_modules.loading"><?= htmlspecialchars($t('admin_modules.loading', 'Загрузка списка модулей...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
</tbody></table></div>

</main></div></div>

<script>
(function () {
    var tableBody = document.getElementById('moduleTableBody');
    if (!tableBody) return;

    function loadModules() {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-muted">' + window.CRM.i18n.t('admin_modules.loading', 'Загрузка...') + '</td></tr>';

        if (!window.CRM || !window.CRM.api || typeof window.CRM.api.request !== 'function') {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-muted">' + window.CRM.i18n.t('admin_modules.waiting_api', 'Ожидание инициализации API...') + '</td></tr>';
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(loadModules);
            } else {
                setTimeout(loadModules, 200);
            }
            return;
        }

        window.CRM.api.request('api/v1/modules', { method: 'GET', timeoutMs: 30000 })
            .then(function (env) {
                var modules = env.data || [];
                if (modules.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-muted">' + window.CRM.i18n.t('admin_modules.empty', 'Модули не найдены.') + ' <a href="index.php?route=admin-modules-install">' + window.CRM.i18n.t('admin_modules.link_install_first', 'Установить первый модуль') + '</a></td></tr>';
                    return;
                }

                var rows = '';
                modules.forEach(function (m) {
                    var statusClass = m.is_active ? 'badge bg-success' : (m.status === 'installed' ? 'badge bg-warning' : 'badge bg-secondary');
                    var statusText = m.is_active ? window.CRM.i18n.t('admin_modules.state_active', 'Активен') : (m.status === 'installed' ? window.CRM.i18n.t('admin_modules.state_installed', 'Установлен') : window.CRM.i18n.t('admin_modules.state_discovered', 'Обнаружен'));
                    var actions = '';
                    if (m.status === 'not_installed') {
                        actions = '<button class="btn btn-sm btn-primary module-install me-1" data-name="' + window.CRM.text.escapeHtml(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_install', 'Установить модуль') + '" data-i18n-title="admin_modules.title_install" aria-label="' + window.CRM.i18n.t('admin_modules.aria_install', 'Установить модуль') + ' ' + window.CRM.text.escapeHtml(m.name) + '" data-i18n-aria-label="admin_modules.aria_install"><i class="fa-solid fa-download"></i> ' + window.CRM.i18n.t('admin_modules.btn_install', 'Установить') + '</button>';
                    } else if (m.is_active) {
                        actions = '<button class="btn btn-sm btn-outline-warning module-deact me-1" data-name="' + window.CRM.text.escapeHtml(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_deactivate', 'Деактивировать модуль') + '" data-i18n-title="admin_modules.title_deactivate" aria-label="' + window.CRM.i18n.t('admin_modules.aria_deactivate', 'Деактивировать модуль') + ' ' + window.CRM.text.escapeHtml(m.name) + '" data-i18n-aria-label="admin_modules.aria_deactivate"><i class="fa-solid fa-pause"></i></button>';
                        actions += '<button class="btn btn-sm btn-outline-danger module-remove" data-name="' + window.CRM.text.escapeHtml(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_remove', 'Удалить модуль') + '" data-i18n-title="admin_modules.title_remove" aria-label="' + window.CRM.i18n.t('admin_modules.aria_remove', 'Удалить модуль') + ' ' + window.CRM.text.escapeHtml(m.name) + '" data-i18n-aria-label="admin_modules.aria_remove"><i class="fa-solid fa-trash"></i></button>';
                    } else {
                        actions = '<button class="btn btn-sm btn-outline-success module-act me-1" data-name="' + window.CRM.text.escapeHtml(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_activate', 'Активировать модуль') + '" data-i18n-title="admin_modules.title_activate" aria-label="' + window.CRM.i18n.t('admin_modules.aria_activate', 'Активировать модуль') + ' ' + window.CRM.text.escapeHtml(m.name) + '" data-i18n-aria-label="admin_modules.aria_activate"><i class="fa-solid fa-play"></i></button>';
                        actions += '<button class="btn btn-sm btn-outline-danger module-remove" data-name="' + window.CRM.text.escapeHtml(m.name) + '" title="' + window.CRM.i18n.t('admin_modules.title_remove', 'Удалить модуль') + '" data-i18n-title="admin_modules.title_remove" aria-label="' + window.CRM.i18n.t('admin_modules.aria_remove', 'Удалить модуль') + ' ' + window.CRM.text.escapeHtml(m.name) + '" data-i18n-aria-label="admin_modules.aria_remove"><i class="fa-solid fa-trash"></i></button>';
                    }

                    rows += '<tr>';
                    rows += '<td><a href="index.php?route=admin-module-detail&module=' + encodeURIComponent(m.name) + '" class="text-decoration-none"><strong>' + window.CRM.text.escapeHtml(m.title || m.name) + '</strong></a><br><small class="text-muted">' + window.CRM.text.escapeHtml(m.name) + '</small>';
                    if (m.description) rows += '<br><small class="text-muted">' + window.CRM.text.escapeHtml(m.description) + '</small>';
                    rows += '</td>';
                    rows += '<td>' + window.CRM.text.escapeHtml(m.version) + '</td>';
                    rows += '<td>' + window.CRM.text.escapeHtml(m.vendor) + '</td>';
                    rows += '<td><span class="' + statusClass + '">' + statusText + '</span></td>';
                    rows += '<td>' + actions + '</td>';
                    rows += '</tr>';
                });

                tableBody.innerHTML = rows;
                bindActions();
            })
            .catch(function (err) {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-danger">' + window.CRM.i18n.t('admin_modules.error_load', 'Ошибка загрузки') + ': ' + window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || (err.message) || window.CRM.i18n.t('admin_modules.unknown_error', 'Неизвестная ошибка')) + '</td></tr>';
            });
    }

    function bindActions() {
        tableBody.querySelectorAll('.module-install').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_install_title', 'Установить модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_install_msg', 'Модуль {name} будет установлен и сможет добавить новые возможности в CRM.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_install', 'Установить'),
                    actionClass: 'crm-btn-primary'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
                    moduleAction(name, 'install', btnEl);
                });
            });
        });
        tableBody.querySelectorAll('.module-act').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_activate_title', 'Активировать модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_activate_msg', 'Модуль {name} начнет работать в CRM сразу после активации.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_activate', 'Активировать'),
                    actionClass: 'crm-btn-primary'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    moduleAction(name, 'activate', btnEl);
                });
            });
        });
        tableBody.querySelectorAll('.module-deact').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_deactivate_title', 'Деактивировать модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_deactivate_msg', 'Модуль {name} перестанет работать, но останется установленным.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_deactivate', 'Деактивировать'),
                    actionClass: 'crm-btn-danger-soft'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    moduleAction(name, 'deactivate', btnEl);
                });
            });
        });
        tableBody.querySelectorAll('.module-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = this.getAttribute('data-name');
                if (!name) return;
                var btnEl = this;
                confirmModuleAction({
                    title: window.CRM.i18n.t('admin_modules.confirm_remove_title', 'Удалить модуль?'),
                    message: window.CRM.i18n.t('admin_modules.confirm_remove_msg', 'Модуль {name} будет удален, а его миграции будут откачены. Это действие нельзя выполнить случайно.').replace('{name}', name),
                    actionText: window.CRM.i18n.t('admin_modules.btn_remove', 'Удалить'),
                    actionClass: 'crm-btn-danger-soft'
                }).then(function (ok) {
                    if (!ok) return;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    moduleAction(name, 'remove', btnEl);
                });
            });
        });
    }

    function confirmModuleAction(options) {
        var modal = document.getElementById('crmConfirmModal');
        var title = document.getElementById('crmConfirmTitle');
        var body = document.getElementById('crmConfirmBody');
        var action = document.getElementById('crmConfirmActionBtn');
        var escapeHtml = window.CRM && window.CRM.text && window.CRM.text.escapeHtml
            ? window.CRM.text.escapeHtml
            : function (value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char] || char;
                });
            };

        if (!modal || !title || !body || !action) {
            if (window.CRM && window.CRM.br1 && typeof window.CRM.br1.notify === 'function') {
                window.CRM.br1.notify('error', window.CRM.i18n.t('admin_modules.error_confirm_open', 'Не удалось открыть окно подтверждения'));
            }
            return Promise.resolve(false);
        }

        return new Promise(function (resolve) {
            var backdrop = document.createElement('div');
            var originalClass = action.className;
            var settled = false;

            function cleanup(result) {
                if (settled) return;
                settled = true;
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
                action.className = originalClass;
                action.removeEventListener('click', onConfirm);
                modal.removeEventListener('click', onModalClick);
                document.removeEventListener('keydown', onKeydown);
                modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach(function (btn) {
                    btn.removeEventListener('click', onCancel);
                });
                if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
                resolve(result);
            }

            function onConfirm() { cleanup(true); }
            function onCancel() { cleanup(false); }
            function onModalClick(event) {
                if (event.target === modal) cleanup(false);
            }
            function onKeydown(event) {
                if (event.key === 'Escape') cleanup(false);
            }

            title.textContent = options.title || window.CRM.i18n.t('admin_modules.confirm_default_title', 'Подтвердите действие');
            body.innerHTML = '<p>' + escapeHtml(options.message || window.CRM.i18n.t('admin_modules.confirm_default_msg', 'Продолжить?')) + '</p>';
            action.textContent = options.actionText || window.CRM.i18n.t('admin_modules.btn_confirm', 'Подтвердить');
            action.className = 'btn ' + (options.actionClass || 'crm-btn-danger-soft');
            action.addEventListener('click', onConfirm);
            modal.addEventListener('click', onModalClick);
            document.addEventListener('keydown', onKeydown);
            modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach(function (btn) {
                btn.addEventListener('click', onCancel);
            });

            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
            document.body.classList.add('modal-open');
            modal.removeAttribute('aria-hidden');
            modal.style.display = 'block';
            modal.classList.add('show');
            action.focus();
        });
    }

    function moduleAction(name, action, btnEl) {
        var endpoints = {
            install: 'api/v1/modules/' + encodeURIComponent(name) + '/install',
            activate: 'api/v1/modules/' + encodeURIComponent(name) + '/activate',
            deactivate: 'api/v1/modules/' + encodeURIComponent(name) + '/deactivate',
            remove: 'api/v1/modules/' + encodeURIComponent(name) + '/uninstall',
        };

        window.CRM.api.request(endpoints[action], { method: 'POST', timeoutMs: 60000 })
            .then(function () {
                try { localStorage.removeItem('crm_menu_items'); } catch (e) {}
                if (window.CRM.navigation && typeof window.CRM.navigation.refreshMenu === 'function') {
                    window.CRM.navigation.refreshMenu();
                }
                if (window.CRM.br1 && typeof window.CRM.br1.notify === 'function') {
                    window.CRM.br1.notify('success', window.CRM.i18n.t('admin_modules.action_success', 'Действие выполнено успешно'));
                }
                loadModules();
            })
            .catch(function (err) {
                if (btnEl) {
                    btnEl.disabled = false;
                    if (action === 'install') btnEl.innerHTML = '<i class="fa-solid fa-download"></i> ' + window.CRM.i18n.t('admin_modules.btn_install', 'Установить');
                    else if (action === 'activate') btnEl.innerHTML = '<i class="fa-solid fa-play"></i>';
                    else if (action === 'deactivate') btnEl.innerHTML = '<i class="fa-solid fa-pause"></i>';
                    else btnEl.innerHTML = '<i class="fa-solid fa-trash"></i>';
                }
                if (window.CRM.br1 && typeof window.CRM.br1.notify === 'function') {
                    window.CRM.br1.notify('error', window.CRM.i18n.t('admin_modules.error_action', 'Ошибка') + ': ' + window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || (err.message) || ''));
                }
            });
    }

    loadModules();
})();
</script>
</body>
