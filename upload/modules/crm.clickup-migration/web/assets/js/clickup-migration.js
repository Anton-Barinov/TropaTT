(function () {
  'use strict';
  if (!document.querySelector('[data-page="module-clickup-migration"]')) return;
  var api = window.CRM && window.CRM.api;
  if (!api || typeof api.request !== 'function') return;
  var moduleApi = '_module/crm.clickup-migration';
  var state = { connection: null, spaces: [] };
  var $ = function (selector) { return document.querySelector(selector); };
  var esc = function (value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch]; }); };
  var data = function (envelope) { return envelope && envelope.data && typeof envelope.data === 'object' ? envelope.data : {}; };
  var message = function (text, danger) { var el = $('#clickupActionMessage'); if (!el) return; el.textContent = text; el.className = 'small mt-3 ' + (danger ? 'text-danger' : 'text-success'); };
  var request = function (path, options) {
    var opts = Object.assign({}, options || {});
    if (opts.method && opts.method !== 'GET') { opts.idempotent = true; opts.headers = Object.assign({}, opts.headers || {}, { 'X-Idempotency-Key': api.createIdempotencyKey('clickup') }); }
    return api.request(moduleApi + path, opts);
  };
  function loadConnections() {
    return request('/connections', { method: 'GET' }).then(function (envelope) {
      var items = data(envelope).connections || [];
      var root = $('#clickupConnections');
      if (!items.length) { root.innerHTML = '<div class="text-muted">Подключений пока нет.</div>'; return; }
      root.innerHTML = items.map(function (item) { return '<button type="button" class="clickup-connection" data-id="' + esc(item.public_id) + '"><span><strong>' + esc(item.name) + '</strong><small>' + esc(item.status || 'draft') + '</small></span><i class="fa-solid fa-chevron-right"></i></button>'; }).join('');
      root.querySelectorAll('[data-id]').forEach(function (button) { button.addEventListener('click', function () { var item = items.find(function (x) { return x.public_id === button.dataset.id; }); if (item) selectConnection(item); }); });
    }).catch(function () { $('#clickupConnections').innerHTML = '<div class="text-danger">Не удалось загрузить подключения.</div>'; });
  }
  function selectConnection(connection) { state.connection = connection; $('#clickupDiscover').disabled = false; $('#clickupStart').disabled = false; $('#clickupProjects').textContent = 'Нажмите «Обновить» для загрузки пространств.'; message('Выбрано подключение: ' + connection.name); }
  function discover() {
    if (!state.connection) return;
    $('#clickupDiscover').disabled = true; $('#clickupProjects').textContent = 'Загрузка пространств ClickUp…';
    request('/connections/' + encodeURIComponent(state.connection.public_id) + '/projects', { method: 'GET' }).then(function (envelope) {
      state.spaces = data(envelope).spaces || data(envelope).projects || [];
      var root = $('#clickupProjects');
      if (!state.spaces.length) { root.textContent = 'Доступных пространств нет.'; return; }
      root.innerHTML = state.spaces.map(function (space) { return '<label class="clickup-project"><input type="checkbox" value="' + esc(space.id) + '" checked><span><strong>' + esc(space.name || space.id) + '</strong><small>Space · ' + esc(space._team_id || '') + '</small></span></label>'; }).join('');
      message('Пространства загружены. Выберите нужные.');
    }).catch(function () { $('#clickupProjects').innerHTML = '<div class="text-danger">Не удалось получить пространства.</div>'; message('Ошибка discovery.', true); }).finally(function () { $('#clickupDiscover').disabled = false; });
  }
  function jobAction(id, action) { return request('/jobs/' + encodeURIComponent(id) + '/' + action, { method: 'POST', body: {} }).then(function () { message('Состояние job обновлено.'); return loadJobs(); }).catch(function () { message('Операция с job не выполнена.', true); }); }
  function loadJobs() {
    return request('/jobs', { method: 'GET' }).then(function (envelope) {
      var items = data(envelope).items || [], root = $('#clickupJobs');
      if (!items.length) { root.innerHTML = '<div class="text-muted">Jobs пока нет.</div>'; return; }
      root.innerHTML = items.slice(0, 20).map(function (job) { var status = String(job.status || ''); var actions = ''; if (['draft','paused','failed','cancelled'].indexOf(status) >= 0) actions += '<button class="btn btn-sm crm-btn-primary" data-action="run">Запустить</button>'; if (['queued','running'].indexOf(status) >= 0) actions += '<button class="btn btn-sm crm-btn-secondary" data-action="pause">Пауза</button><button class="btn btn-sm btn-outline-danger" data-action="cancel">Отменить</button>'; if (['completed','completed_with_warnings','failed','cancelled'].indexOf(status) >= 0) actions += '<button class="btn btn-sm crm-btn-secondary" data-action="retry-failed">Повторить ошибки</button><button class="btn btn-sm btn-outline-danger" data-action="rollback">Откатить</button>'; return '<div class="clickup-connection"><span><strong>' + esc(job.connection_name || 'ClickUp') + '</strong><small>' + esc(status) + ' · ' + esc(job.current_step || '') + '</small><div class="mt-2 d-flex gap-1 flex-wrap" data-job="' + esc(job.public_id) + '">' + actions + '</div></span><b>' + Number(job.progress_percent || 0).toFixed(0) + '%</b></div>'; }).join('');
      root.querySelectorAll('[data-action]').forEach(function (button) { button.addEventListener('click', function () { jobAction(button.parentElement.dataset.job, button.dataset.action); }); });
    }).catch(function () { $('#clickupJobs').innerHTML = '<div class="text-danger">Не удалось загрузить jobs.</div>'; });
  }
  function createJob() {
    if (!state.connection) return;
    var spaces = Array.from(document.querySelectorAll('#clickupProjects input:checked')).map(function (input) { return input.value; });
    if (!spaces.length) { message('Выберите хотя бы одно пространство.', true); return; }
    var sourceScope = { space_ids: spaces, include_comments: $('#clickupComments').checked, include_attachments: $('#clickupAttachments').checked, include_completed: $('#clickupCompleted').checked, include_closed: $('#clickupCompleted').checked, updated_since: $('#clickupUpdatedSince') ? $('#clickupUpdatedSince').value : '', completed_since: $('#clickupCompletedSince') ? $('#clickupCompletedSince').value : '', completed_until: $('#clickupCompletedUntil') ? $('#clickupCompletedUntil').value : '', include_archived: $('#clickupArchived') ? $('#clickupArchived').checked : false, time_start_date: $('#clickupTimeStart') ? $('#clickupTimeStart').value : '', time_end_date: $('#clickupTimeEnd') ? $('#clickupTimeEnd').value : '' };
    var options = { include_comments: $('#clickupComments').checked, include_attachments: $('#clickupAttachments').checked, include_time_tracking: true, include_archived: $('#clickupArchived') ? $('#clickupArchived').checked : false, include_goals: false, include_closed: $('#clickupCompleted').checked, include_completed: $('#clickupCompleted').checked, updated_since: $('#clickupUpdatedSince') ? $('#clickupUpdatedSince').value : '', include_archived: $('#clickupArchived') ? $('#clickupArchived').checked : false, time_start_date: $('#clickupTimeStart') ? $('#clickupTimeStart').value : '', time_end_date: $('#clickupTimeEnd') ? $('#clickupTimeEnd').value : '', spaces_per_run: 1, max_attachment_size_mb: 20 };
    var button = $('#clickupStart'); button.disabled = true; message('Создание job…');
    request('/jobs', { method: 'POST', body: { connection_public_id: state.connection.public_id, space_ids: spaces, source_scope: sourceScope, target_options: options, mode: $('#clickupMode').value } }).then(function (envelope) { var job = data(envelope).job; if (!job || !job.public_id) throw new Error('JOB_ID_MISSING'); return request('/jobs/' + encodeURIComponent(job.public_id) + '/run', { method: 'POST', body: {} }); }).then(function () { message('Job создан и поставлен в очередь.'); return loadJobs(); }).catch(function () { message('Не удалось запустить миграцию.', true); }).finally(function () { button.disabled = false; });
  }
  function showModal() { var modal = $('#clickupConnectionModal'); if (window.bootstrap && modal) window.bootstrap.Modal.getOrCreateInstance(modal).show(); }
  function submitConnection(event) { event.preventDefault(); var form = event.currentTarget, error = $('#clickupConnectionError'), body = Object.fromEntries(new FormData(form).entries()), oauth = body.auth_type === 'oauth2'; error.classList.add('d-none'); var payload = oauth ? { name: body.name, client_id: body.client_id, client_secret: body.client_secret, code: body.code, state: body.state, redirect_uri: body.redirect_uri } : { name: body.name, access_token: body.access_token }; request(oauth ? '/oauth/exchange' : '/connections', { method: 'POST', body: payload }).then(function () { window.bootstrap.Modal.getOrCreateInstance($('#clickupConnectionModal')).hide(); form.reset(); message('Подключение сохранено и проверено.'); return loadConnections(); }).catch(function () { error.textContent = 'Не удалось проверить ClickUp credentials.'; error.classList.remove('d-none'); }); }
  function authorizeOAuth() { var formEl = $('#clickupConnectionForm'), form = new FormData(formEl); var body = { client_id: form.get('client_id'), redirect_uri: form.get('redirect_uri') }; request('/oauth/authorize-url', { method: 'POST', body: body }).then(function (envelope) { var result = data(envelope); if (result.authorization_url) window.open(result.authorization_url, '_blank', 'noopener'); if (result.default_redirect_uri && !body.redirect_uri) { var input = formEl.querySelector('[name="redirect_uri"]'); if (input) input.value = result.default_redirect_uri; } }).catch(function () { var error=$('#clickupConnectionError'); error.textContent='Не удалось сформировать OAuth URL.'; error.classList.remove('d-none'); }); }
  document.addEventListener('DOMContentLoaded', function () { if (!$('#clickupAddConnection') && !$('#clickupConnections') && !$('#clickupJobs')) return; $('#clickupAddConnection')?.addEventListener('click', showModal); $('#clickupConnectionForm')?.addEventListener('submit', submitConnection); $('#clickupOAuthAuthorize')?.addEventListener('click', authorizeOAuth); $('#clickupAuthType')?.addEventListener('change', function (event) { var oauth=event.target.value==='oauth2'; $('#clickupPatFields')?.classList.toggle('d-none',oauth); $('#clickupOauthFields')?.classList.toggle('d-none',!oauth); }); $('#clickupDiscover')?.addEventListener('click', discover); $('#clickupStart')?.addEventListener('click', createJob); $('#clickupRefreshJobs')?.addEventListener('click', loadJobs); loadConnections(); loadJobs(); });
})();
