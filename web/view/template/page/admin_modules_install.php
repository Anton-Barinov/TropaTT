<?php declare(strict_types=1); ?>
<?php $title = $t('admin_modules_install.title', 'TropaTT — Установка модуля'); ?>
<body data-page="admin-modules-install" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="admin_modules_install.link_admin"><?= htmlspecialchars($t('admin_modules_install.link_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item"><a href="index.php?route=admin-modules" data-i18n="admin_modules_install.link_modules"><?= htmlspecialchars($t('admin_modules_install.link_modules', 'Модули'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="admin_modules_install.breadcrumb"><?= htmlspecialchars($t('admin_modules_install.breadcrumb', 'Установка'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" data-i18n="admin_modules_install.page_title"><?= htmlspecialchars($t('admin_modules_install.page_title', 'Установка модуля'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="admin_modules_install.subtitle"><?= htmlspecialchars($t('admin_modules_install.subtitle', 'Загрузите модуль из ZIP-архива или укажите URL для установки.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="admin_modules_install.card_url_title"><?= htmlspecialchars($t('admin_modules_install.card_url_title', 'Установка из URL'), ENT_QUOTES, 'UTF-8') ?></h2></div>
      <form id="installUrlForm" onsubmit="return false;">
        <div class="mb-3">
          <label for="moduleUrl" class="form-label" data-i18n="admin_modules_install.label_url"><?= htmlspecialchars($t('admin_modules_install.label_url', 'URL модуля (ZIP-архив)'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="url" class="form-control" id="moduleUrl" name="moduleUrl" placeholder="<?= htmlspecialchars($t('admin_modules_install.placeholder_url', 'https://example.com/modules/crm.hello-1.0.0.zip'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_modules_install.placeholder_url" required>
          <div class="form-text" data-i18n="admin_modules_install.hint_url"><?= htmlspecialchars($t('admin_modules_install.hint_url', 'Поддерживаются только HTTPS-ссылки на ZIP-архивы'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="verifySignature" name="verifySignature" checked>
          <label class="form-check-label" for="verifySignature" data-i18n="admin_modules_install.label_verify"><?= htmlspecialchars($t('admin_modules_install.label_verify', 'Проверять подпись манифеста'), ENT_QUOTES, 'UTF-8') ?></label>
        </div>
        <button type="submit" class="btn crm-btn-primary" id="urlInstallBtn" data-i18n="admin_modules_install.btn_install_url"><i class="fa-solid fa-cloud-arrow-down me-1"></i> <?= htmlspecialchars($t('admin_modules_install.btn_install_url', 'Установить из URL'), ENT_QUOTES, 'UTF-8') ?></button>
        <div id="urlInstallStatus" class="mt-3" style="display:none;"></div>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="admin_modules_install.card_file_title"><?= htmlspecialchars($t('admin_modules_install.card_file_title', 'Установка из файла'), ENT_QUOTES, 'UTF-8') ?></h2></div>
      <form id="installFileForm" onsubmit="return false;">
        <div class="mb-3">
          <label for="moduleFile" class="form-label" data-i18n="admin_modules_install.label_file"><?= htmlspecialchars($t('admin_modules_install.label_file', 'ZIP-архив модуля'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="file" class="form-control" id="moduleFile" name="moduleFile" accept=".zip" required>
          <div class="form-text" data-i18n="admin_modules_install.hint_file_size"><?= htmlspecialchars($t('admin_modules_install.hint_file_size', 'Максимальный размер: 50 MB'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button type="submit" class="btn crm-btn-primary" id="fileInstallBtn" data-i18n="admin_modules_install.btn_install_file"><i class="fa-solid fa-upload me-1"></i> <?= htmlspecialchars($t('admin_modules_install.btn_install_file', 'Установить из файла'), ENT_QUOTES, 'UTF-8') ?></button>
        <div id="fileInstallStatus" class="mt-3" style="display:none;"></div>
      </form>
    </div>
  </div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive mb-3">
  <div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="admin_modules_install.card_discovered_title"><?= htmlspecialchars($t('admin_modules_install.card_discovered_title', 'Обнаруженные модули (не установлены)'), ENT_QUOTES, 'UTF-8') ?></h2></div>
  <table class="table crm-table mb-0"><thead><tr><th data-i18n="admin_modules_install.th_module"><?= htmlspecialchars($t('admin_modules_install.th_module', 'Модуль'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules_install.th_version"><?= htmlspecialchars($t('admin_modules_install.th_version', 'Версия'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="admin_modules_install.th_vendor"><?= htmlspecialchars($t('admin_modules_install.th_vendor', 'Вендор'), ENT_QUOTES, 'UTF-8') ?></th><th style="width:140px" data-i18n="admin_modules_install.th_actions"><?= htmlspecialchars($t('admin_modules_install.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="discoveredTableBody">
  <tr><td colspan="4" class="text-muted" data-i18n="admin_modules_install.loading"><?= htmlspecialchars($t('admin_modules_install.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
  </tbody></table>
</div>

</main></div></div>

<script>
(function () {
    var urlForm = document.getElementById('installUrlForm');
    var fileForm = document.getElementById('installFileForm');
    var urlBtn = document.getElementById('urlInstallBtn');
    var fileBtn = document.getElementById('fileInstallBtn');
    var urlStatus = document.getElementById('urlInstallStatus');
    var fileStatus = document.getElementById('fileInstallStatus');

    function setStatus(el, type, message) {
        el.style.display = 'block';
        var cls = type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-muted';
        el.innerHTML = '<div class="' + cls + ' small">' + (window.CRM && window.CRM.text ? window.CRM.text.escapeHtml(message) : message) + '</div>';
    }

    function setBtnLoading(btn, loading) {
        btn.disabled = loading;
        if (loading) {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + window.CRM.i18n.t('admin_modules_install.installing', 'Установка...');
        } else {
            btn.innerHTML = btn === urlBtn ? '<i class="fa-solid fa-cloud-arrow-down me-1"></i> ' + window.CRM.i18n.t('admin_modules_install.btn_install_url', 'Установить из URL') : '<i class="fa-solid fa-upload me-1"></i> ' + window.CRM.i18n.t('admin_modules_install.btn_install_file', 'Установить из файла');
        }
    }

    function moduleApiAvailable() {
        return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function';
    }

    urlForm.addEventListener('submit', function () {
        if (!moduleApiAvailable()) { setStatus(urlStatus, 'error', window.CRM.i18n.t('admin_modules_install.error_api_unavailable', 'API недоступен. Обновите страницу.')); return; }
        var url = document.getElementById('moduleUrl').value.trim();
        if (!url) { setStatus(urlStatus, 'error', window.CRM.i18n.t('admin_modules_install.error_no_url', 'Введите URL модуля')); return; }

        setBtnLoading(urlBtn, true);
        urlStatus.style.display = 'none';

        window.CRM.api.request('api/v1/modules/install-from-url', {
            method: 'POST',
            timeoutMs: 120000,
            body: { url: url }
        })
        .then(function () {
            setBtnLoading(urlBtn, false);
            setStatus(urlStatus, 'success', window.CRM.i18n.t('admin_modules_install.installed_url', 'Модуль успешно установлен!') + ' <a href="index.php?route=admin-modules">' + window.CRM.i18n.t('admin_modules_install.link_goto_list', 'Перейти к списку') + '</a>');
            loadDiscovered();
        })
        .catch(function (err) {
            setBtnLoading(urlBtn, false);
            setStatus(urlStatus, 'error', window.CRM.i18n.t('admin_modules_install.error', 'Ошибка') + ': ' + (window.CRM.text ? window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || (err.message) || '') : window.CRM.i18n.t('admin_modules_install.error_install_failed', 'Не удалось установить модуль')));
        });
    });

    fileForm.addEventListener('submit', function () {
        if (!moduleApiAvailable()) { setStatus(fileStatus, 'error', window.CRM.i18n.t('admin_modules_install.error_api_unavailable', 'API недоступен. Обновите страницу.')); return; }
        var fileInput = document.getElementById('moduleFile');
        var file = fileInput.files && fileInput.files[0];
        if (!file) { setStatus(fileStatus, 'error', window.CRM.i18n.t('admin_modules_install.error_no_file', 'Выберите файл модуля')); return; }
        if (file.size > 52428800) { setStatus(fileStatus, 'error', window.CRM.i18n.t('admin_modules_install.error_file_too_large', 'Файл слишком большой. Максимум 50 MB')); return; }

        setBtnLoading(fileBtn, true);
        fileStatus.style.display = 'none';

        var reader = new FileReader();
        reader.onload = function () {
            var base64 = reader.result.split(',')[1];
            window.CRM.api.request('api/v1/modules/install-from-file', {
                method: 'POST',
                timeoutMs: 120000,
                body: { file_data: base64, file_name: file.name }
            })
            .then(function () {
                setBtnLoading(fileBtn, false);
                setStatus(fileStatus, 'success', window.CRM.i18n.t('admin_modules_install.installed_file', 'Модуль успешно установлен!') + ' <a href="index.php?route=admin-modules">' + window.CRM.i18n.t('admin_modules_install.link_goto_list', 'Перейти к списку') + '</a>');
                fileInput.value = '';
                loadDiscovered();
            })
            .catch(function (err) {
                setBtnLoading(fileBtn, false);
                setStatus(fileStatus, 'error', window.CRM.i18n.t('admin_modules_install.error', 'Ошибка') + ': ' + (window.CRM.text ? window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || (err.message) || '') : window.CRM.i18n.t('admin_modules_install.error_install_failed', 'Не удалось установить модуль')));
            });
        };
        reader.readAsDataURL(file);
    });

    function loadDiscovered() {
        var tbody = document.getElementById('discoveredTableBody');
        if (!tbody) return;
        if (!moduleApiAvailable()) { tbody.innerHTML = '<tr><td colspan="4" class="text-muted">' + window.CRM.i18n.t('admin_modules_install.error_api_unavailable', 'API недоступен') + '</td></tr>'; return; }

        window.CRM.api.request('api/v1/modules', { method: 'GET', timeoutMs: 15000 })
            .then(function (env) {
                var modules = env.data || [];
                var discovered = modules.filter(function (m) { return m.status === 'not_installed'; });

                if (discovered.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">' + window.CRM.i18n.t('admin_modules_install.empty_discovered', 'Нет обнаруженных модулей. Скопируйте модуль в директорию modules/ или установите через форму выше.') + '</td></tr>';
                    return;
                }

                var rows = '';
                discovered.forEach(function (m) {
                    rows += '<tr>';
                    rows += '<td><strong>' + window.CRM.text.escapeHtml(m.title || m.name) + '</strong><br><small class="text-muted">' + window.CRM.text.escapeHtml(m.name) + '</small></td>';
                    rows += '<td>' + window.CRM.text.escapeHtml(m.version) + '</td>';
                    rows += '<td>' + window.CRM.text.escapeHtml(m.vendor) + '</td>';
                    rows += '<td><button class="btn btn-sm btn-primary module-install" data-name="' + window.CRM.text.escapeHtml(m.name) + '" data-i18n="admin_modules_install.btn_install"><i class="fa-solid fa-download"></i> ' + window.CRM.i18n.t('admin_modules_install.btn_install', 'Установить') + '</button></td>';
                    rows += '</tr>';
                });

                tbody.innerHTML = rows;

                tbody.querySelectorAll('.module-install').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var name = this.getAttribute('data-name');
                        if (!name) return;
                        var btnEl = this;
                        btnEl.disabled = true;
                        btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                        window.CRM.api.request('api/v1/modules/' + encodeURIComponent(name) + '/install', { method: 'POST', timeoutMs: 60000 })
                            .then(function () {
                                if (window.CRM.br1) window.CRM.br1.notify('success', window.CRM.i18n.t('admin_modules_install.module_installed', 'Модуль установлен'));
                                loadDiscovered();
                            })
                            .catch(function (err) {
                                btnEl.disabled = false;
                                btnEl.innerHTML = '<i class="fa-solid fa-download"></i> ' + window.CRM.i18n.t('admin_modules_install.btn_install', 'Установить');
                                if (window.CRM.br1) window.CRM.br1.notify('error', window.CRM.i18n.t('admin_modules_install.error', 'Ошибка') + ': ' + (err.envelope && err.envelope.message || err.message || ''));
                            });
                    });
                });
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="4" class="text-muted">' + window.CRM.i18n.t('admin_modules_install.error_load_list', 'Не удалось загрузить список модулей') + '</td></tr>';
            });
    }

    loadDiscovered();
})();
</script>
</body>
