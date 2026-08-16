(function () {
  'use strict';

  if (!document.querySelector('[data-page="module-shtab-migration"]')) return;
  var api = window.CRM && window.CRM.api;
  if (!api || typeof api.request !== 'function') return;

  var moduleApi = '_module/crm.shtab-migration';
  var state = { connections: [], file: null, crmUsers: [] };

  function $(id) { return document.getElementById(id); }
  function data(response) { return response && response.data ? response.data : (response || {}); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
  }
  function request(path, options) {
    options = Object.assign({}, options || {});
    if (options.method && options.method !== 'GET') {
      options.idempotent = true;
      options.headers = Object.assign({}, options.headers || {}, {
        'X-Idempotency-Key': api.createIdempotencyKey('shtab')
      });
    }
    return api.request(moduleApi + path, options);
  }
  function message(text, error) {
    var element = $('shtabMessage');
    element.textContent = text;
    element.className = 'small mt-3 ' + (error ? 'text-danger' : 'text-success');
  }
  function selectedConnection() { return $('shtabConnection').value || ''; }
  function toggle() { $('shtabStart').disabled = !state.file || !selectedConnection(); }

  function loadConnections() {
    return request('/connections').then(function (response) {
      state.connections = data(response).connections || [];
      var select = $('shtabConnection');
      select.innerHTML = state.connections.map(function (connection) {
        return '<option value="' + esc(connection.public_id) + '">' + esc(connection.name) + '</option>';
      }).join('') || '<option value="">Сначала создайте профиль</option>';
      $('shtabConnections').innerHTML = state.connections.map(function (connection) {
        return '<div class="shtab-job"><span><strong>' + esc(connection.name) + '</strong><small>Официальный export-only профиль</small></span><span>' + esc(connection.status) + '</span></div>';
      }).join('') || '<span class="text-muted">Профилей пока нет.</span>';
      toggle();
      return loadMappings();
    }).catch(function () { message('Не удалось загрузить профили.', true); });
  }

  function loadMappings() {
    var connection = selectedConnection();
    var target = $('shtabMappings');
    if (!connection) {
      target.textContent = 'Выберите профиль.';
      return Promise.resolve();
    }
    return Promise.all([
      request('/connections/' + encodeURIComponent(connection) + '/user-mappings'),
      request('/connections/' + encodeURIComponent(connection) + '/crm-users')
    ]).then(function (responses) {
      state.crmUsers = data(responses[1]).items || [];
      renderMappings(data(responses[0]).items || []);
    }).catch(function () { target.textContent = 'Не удалось загрузить сопоставления.'; });
  }

  function renderMappings(mappings) {
    var target = $('shtabMappings');
    if (!mappings.length) {
      target.innerHTML = '<span class="text-muted">Сопоставлений пока нет.</span>';
      return;
    }
    target.innerHTML = mappings.map(function (mapping) {
      var options = '<option value="">Не сопоставлен</option>' + state.crmUsers.map(function (user) {
        var label = user.full_name || user.login || user.public_id;
        return '<option value="' + esc(user.public_id) + '"' + (user.public_id === mapping.crm_user_public_id ? ' selected' : '') + '>' + esc(label) + '</option>';
      }).join('');
      var sourceLabel = mapping.display_name || mapping.email || mapping.shtab_user_id;
      return '<div class="shtab-mapping-row"><div><strong>' + esc(sourceLabel) + '</strong><small>' + esc(mapping.email || mapping.shtab_user_id) + '</small></div><select class="form-select form-select-sm" data-mapping-id="' + esc(mapping.id) + '">' + options + '</select></div>';
    }).join('');
    target.querySelectorAll('[data-mapping-id]').forEach(function (select) {
      select.addEventListener('change', function () {
        var path = '/connections/' + encodeURIComponent(selectedConnection()) + '/user-mappings/' + encodeURIComponent(select.dataset.mappingId);
        request(path, { method: 'PATCH', body: { crm_user_public_id: select.value || null } }).then(function () {
          message('Сопоставление пользователя обновлено.');
          return loadMappings();
        }).catch(function () { message('Не удалось обновить сопоставление.', true); });
      });
    });
  }

  function loadJobs() {
    return request('/jobs').then(function (response) {
      var items = data(response).items || [];
      $('shtabJobs').innerHTML = items.map(function (job) {
        var actions = '';
        if (['draft', 'paused'].indexOf(job.status) >= 0) actions += '<button class="btn btn-sm crm-btn-primary" data-action="run">Запустить</button>';
        if (['queued', 'running'].indexOf(job.status) >= 0) actions += '<button class="btn btn-sm crm-btn-secondary" data-action="pause">Пауза</button>';
        if (['draft', 'queued', 'running', 'paused', 'pausing'].indexOf(job.status) >= 0) actions += '<button class="btn btn-sm btn-outline-danger" data-action="cancel">Отменить</button>';
        if (['completed_with_warnings', 'failed', 'cancelled'].indexOf(job.status) >= 0) actions += '<button class="btn btn-sm btn-outline-secondary" data-action="retry">Повторить ошибки</button>';
        if (['completed', 'completed_with_warnings', 'failed', 'cancelled', 'rolled_back_with_warnings'].indexOf(job.status) >= 0) actions += '<button class="btn btn-sm btn-outline-danger" data-action="rollback">Откатить</button>';
        return '<div class="shtab-job"><span><strong>' + esc(job.source_file_name) + '</strong><small>' + esc(job.connection_name) + ' · ' + esc(job.status) + ' · ' + esc(job.current_step || '') + '</small></span><span>' + Number(job.progress_percent || 0).toFixed(0) + '% <span data-job="' + esc(job.public_id) + '">' + actions + '</span></span></div>';
      }).join('') || '<span class="text-muted">Jobs пока нет.</span>';
      $('shtabJobs').querySelectorAll('[data-action]').forEach(function (button) {
        button.addEventListener('click', function () {
          var id = button.parentElement.dataset.job;
          var action = button.dataset.action;
          if (action === 'cancel' && !window.confirm(window.CRM.i18n.t('shtab_migration.confirm_cancel_job', 'Отменить этот job? Уже импортированные данные сохранятся до ручного rollback.'))) return;
          var suffix = action === 'run' ? '/run' : action === 'pause' ? '/pause' : action === 'cancel' ? '/cancel' : action === 'retry' ? '/retry-failed' : '/rollback';
          request('/jobs/' + encodeURIComponent(id) + suffix, { method: 'POST', body: {} }).then(loadJobs).catch(function () { message('Операция job не выполнена.', true); });
        });
      });
    }).catch(function () { message('Не удалось загрузить jobs.', true); });
  }

  function createJob() {
    var file = state.file;
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function () {
      var result = String(reader.result || ''), connection = selectedConnection();
      if (!connection) { message('Создайте и выберите профиль.', true); return; }
      var button = $('shtabStart');
      button.disabled = true;
      request('/jobs', { method: 'POST', body: {
        connection_public_id: connection,
        file_name: file.name,
        content_base64: result,
        entity_type: $('shtabEntity').value,
        mode: $('shtabMode').value
      } }).then(function () {
        message('Job создан.');
        return loadJobs();
      }).catch(function (error) { message(error.message || 'Импорт не создан.', true); }).finally(toggle);
    };
    reader.readAsDataURL(file);
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('shtabConnections') && !$('shtabJobs') && !$('shtabFile')) return;
    $('shtabFile').addEventListener('change', function (event) { state.file = event.target.files[0] || null; toggle(); });
    $('shtabConnection').addEventListener('change', function () { toggle(); loadMappings(); });
    $('shtabStart').addEventListener('click', createJob);
    $('shtabRefresh').addEventListener('click', loadJobs);
    $('shtabAddConnection').addEventListener('click', function () { bootstrap.Modal.getOrCreateInstance($('shtabConnectionModal')).show(); });
    $('shtabConnectionForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var name = new FormData(event.currentTarget).get('name');
      request('/connections', { method: 'POST', body: { name: name } }).then(function () {
        bootstrap.Modal.getOrCreateInstance($('shtabConnectionModal')).hide();
        event.currentTarget.reset();
        return loadConnections();
      }).catch(function () {
        $('shtabConnectionError').textContent = 'Профиль не создан.';
        $('shtabConnectionError').classList.remove('d-none');
      });
    });
    loadConnections();
    loadJobs();
  });
}());
