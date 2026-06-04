<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Установка модуля'; ?>
<body data-page="admin-modules-install" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin">Админка</a></li><li class="breadcrumb-item"><a href="index.php?route=admin-modules">Модули</a></li><li class="breadcrumb-item active">Установка</li></ol><h1 class="crm-page-title">Установка модуля</h1><p class="crm-subtitle">Загрузите модуль из ZIP-архива или укажите URL для установки.</p></div></div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><h2 class="h6 mb-0">Установка из URL</h2></div>
      <form id="installUrlForm" onsubmit="return false;">
        <div class="mb-3">
          <label for="moduleUrl" class="form-label">URL модуля (ZIP-архив)</label>
          <input type="url" class="form-control" id="moduleUrl" name="moduleUrl" placeholder="https://example.com/modules/crm.hello-1.0.0.zip" required>
          <div class="form-text">Поддерживаются только HTTPS-ссылки на ZIP-архивы</div>
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="verifySignature" name="verifySignature" checked>
          <label class="form-check-label" for="verifySignature">Проверять подпись манифеста</label>
        </div>
        <button type="submit" class="btn crm-btn-primary" id="urlInstallBtn"><i class="fa-solid fa-cloud-arrow-down me-1"></i> Установить из URL</button>
        <div id="urlInstallStatus" class="mt-3" style="display:none;"></div>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><h2 class="h6 mb-0">Установка из файла</h2></div>
      <form id="installFileForm" onsubmit="return false;">
        <div class="mb-3">
          <label for="moduleFile" class="form-label">ZIP-архив модуля</label>
          <input type="file" class="form-control" id="moduleFile" name="moduleFile" accept=".zip" required>
          <div class="form-text">Максимальный размер: 50 MB</div>
        </div>
        <button type="submit" class="btn crm-btn-primary" id="fileInstallBtn"><i class="fa-solid fa-upload me-1"></i> Установить из файла</button>
        <div id="fileInstallStatus" class="mt-3" style="display:none;"></div>
      </form>
    </div>
  </div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive mb-3">
  <div class="crm-section-head"><h2 class="h6 mb-0">Обнаруженные модули (не установлены)</h2></div>
  <table class="table crm-table mb-0"><thead><tr><th>Модуль</th><th>Версия</th><th>Вендор</th><th style="width:140px">Действия</th></tr></thead><tbody id="discoveredTableBody">
  <tr><td colspan="4" class="text-muted">Загрузка...</td></tr>
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
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Установка...';
        } else {
            btn.innerHTML = btn === urlBtn ? '<i class="fa-solid fa-cloud-arrow-down me-1"></i> Установить из URL' : '<i class="fa-solid fa-upload me-1"></i> Установить из файла';
        }
    }

    function moduleApiAvailable() {
        return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function';
    }

    urlForm.addEventListener('submit', function () {
        if (!moduleApiAvailable()) { setStatus(urlStatus, 'error', 'API недоступен. Обновите страницу.'); return; }
        var url = document.getElementById('moduleUrl').value.trim();
        if (!url) { setStatus(urlStatus, 'error', 'Введите URL модуля'); return; }

        setBtnLoading(urlBtn, true);
        urlStatus.style.display = 'none';

        window.CRM.api.request('api/v1/modules/install-from-url', {
            method: 'POST',
            timeoutMs: 120000,
            body: { url: url }
        })
        .then(function () {
            setBtnLoading(urlBtn, false);
            setStatus(urlStatus, 'success', 'Модуль успешно установлен! <a href="index.php?route=admin-modules">Перейти к списку</a>');
            loadDiscovered();
        })
        .catch(function (err) {
            setBtnLoading(urlBtn, false);
            setStatus(urlStatus, 'error', 'Ошибка: ' + (window.CRM.text ? window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || (err.message) || '') : 'Не удалось установить модуль'));
        });
    });

    fileForm.addEventListener('submit', function () {
        if (!moduleApiAvailable()) { setStatus(fileStatus, 'error', 'API недоступен. Обновите страницу.'); return; }
        var fileInput = document.getElementById('moduleFile');
        var file = fileInput.files && fileInput.files[0];
        if (!file) { setStatus(fileStatus, 'error', 'Выберите файл модуля'); return; }
        if (file.size > 52428800) { setStatus(fileStatus, 'error', 'Файл слишком большой. Максимум 50 MB'); return; }

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
                setStatus(fileStatus, 'success', 'Модуль успешно установлен! <a href="index.php?route=admin-modules">Перейти к списку</a>');
                fileInput.value = '';
                loadDiscovered();
            })
            .catch(function (err) {
                setBtnLoading(fileBtn, false);
                setStatus(fileStatus, 'error', 'Ошибка: ' + (window.CRM.text ? window.CRM.text.escapeHtml((err.envelope && err.envelope.message) || (err.message) || '') : 'Не удалось установить модуль'));
            });
        };
        reader.readAsDataURL(file);
    });

    function loadDiscovered() {
        var tbody = document.getElementById('discoveredTableBody');
        if (!tbody) return;
        if (!moduleApiAvailable()) { tbody.innerHTML = '<tr><td colspan="4" class="text-muted">API недоступен</td></tr>'; return; }

        window.CRM.api.request('api/v1/modules', { method: 'GET', timeoutMs: 15000 })
            .then(function (env) {
                var modules = env.data || [];
                var discovered = modules.filter(function (m) { return m.status === 'not_installed'; });

                if (discovered.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Нет обнаруженных модулей. Скопируйте модуль в директорию modules/ или установите через форму выше.</td></tr>';
                    return;
                }

                var rows = '';
                discovered.forEach(function (m) {
                    rows += '<tr>';
                    rows += '<td><strong>' + window.CRM.text.escapeHtml(m.title || m.name) + '</strong><br><small class="text-muted">' + window.CRM.text.escapeHtml(m.name) + '</small></td>';
                    rows += '<td>' + window.CRM.text.escapeHtml(m.version) + '</td>';
                    rows += '<td>' + window.CRM.text.escapeHtml(m.vendor) + '</td>';
                    rows += '<td><button class="btn btn-sm btn-primary module-install" data-name="' + window.CRM.text.escapeHtml(m.name) + '"><i class="fa-solid fa-download"></i> Установить</button></td>';
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
                                if (window.CRM.br1) window.CRM.br1.notify('success', 'Модуль установлен');
                                loadDiscovered();
                            })
                            .catch(function (err) {
                                btnEl.disabled = false;
                                btnEl.innerHTML = '<i class="fa-solid fa-download"></i> Установить';
                                if (window.CRM.br1) window.CRM.br1.notify('error', 'Ошибка: ' + (err.envelope && err.envelope.message || err.message || ''));
                            });
                    });
                });
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Не удалось загрузить список модулей</td></tr>';
            });
    }

    loadDiscovered();
})();
</script>
</body>
