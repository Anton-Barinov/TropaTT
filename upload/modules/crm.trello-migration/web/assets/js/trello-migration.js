(function () {
  'use strict';
  if (!document.querySelector('[data-page="module-trello-migration"]')) return;

  var api = window.CRM && window.CRM.api;
  if (!api || typeof api.request !== 'function') return;
  var state = { connection: null, boards: [], selectedBoards: [], job: null, poll: null };
  // Module API routes are mounted by Router::addManyFromModule() under
  // /_module/{vendor}.{name}/, just like the Jira/Confluence modules.
  var moduleApi = '_module/crm.trello-migration';
  var qs = function (selector) { return document.querySelector(selector); };
  var esc = function (value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch]; }); };
  var data = function (envelope) { return envelope && envelope.data && typeof envelope.data === 'object' ? envelope.data : {}; };
  var message = function (text, danger) { var el = qs('#trelloActionMessage'); if (!el) return; el.className = 'small mt-3 ' + (danger ? 'text-danger' : 'text-success'); el.textContent = text; };
  var request = function (route, options) {
    var opts = Object.assign({}, options || {});
    if (opts.method && opts.method !== 'GET') {
      opts.idempotent = true;
      opts.headers = Object.assign({}, opts.headers || {}, { 'X-Idempotency-Key': api.createIdempotencyKey('trello') });
    }
    return api.request(route, opts);
  };

  function showConnectionModal() {
    var modal = qs('#trelloConnectionModal');
    if (window.bootstrap && modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  async function loadConnections() {
    var target = qs('#trelloConnections');
    try {
      var envelope = await request(moduleApi + '/connections', { method: 'GET' });
      var items = data(envelope).connections || [];
      if (!items.length) { target.innerHTML = '<div class="text-muted">Подключений пока нет.</div>'; return; }
      target.innerHTML = items.map(function (item) {
        return '<button type="button" class="trello-connection ' + (state.connection && state.connection.public_id === item.public_id ? 'is-active' : '') + '" data-connection="' + esc(item.public_id) + '"><span><strong>' + esc(item.name) + '</strong><small>' + esc(item.status || 'draft') + '</small></span><i class="fa-solid fa-chevron-right"></i></button>';
      }).join('');
      target.querySelectorAll('[data-connection]').forEach(function (button) { button.addEventListener('click', function () { selectConnection(items.find(function (x) { return x.public_id === button.dataset.connection; })); }); });
    } catch (error) { target.innerHTML = '<div class="text-danger">Не удалось загрузить подключения.</div>'; }
  }

  async function selectConnection(connection) {
    state.connection = connection; state.boards = []; state.selectedBoards = []; state.job = null;
    qs('#trelloDiscover').disabled = false; qs('#trelloStart').disabled = true;
    qs('#trelloBoards').textContent = 'Нажмите «Обновить доски». Приложение не хранит данные Trello в браузере.';
    qs('#trelloBoardOptions').classList.add('d-none');
    await loadConnections();
  }

  async function discover() {
    if (!state.connection) return;
    var target = qs('#trelloBoards'); target.textContent = 'Загрузка досок…'; qs('#trelloDiscover').disabled = true;
    try {
      var envelope = await request(moduleApi + '/connections/' + encodeURIComponent(state.connection.public_id) + '/discover', { method: 'POST', body: {} });
      state.boards = data(envelope).boards || [];
      renderBoards(); message('Доски загружены. Выберите одну или несколько.');
    } catch (error) { target.innerHTML = '<div class="text-danger">Не удалось получить доски Trello. Проверьте подключение и права токена.</div>'; message('Ошибка discovery.', true); }
    qs('#trelloDiscover').disabled = false;
  }

  function renderBoards() {
    var target = qs('#trelloBoards');
    if (!state.boards.length) { target.innerHTML = '<div class="text-muted">Доступных досок нет.</div>'; return; }
    target.innerHTML = state.boards.map(function (board) {
      return '<label class="trello-board"><input type="checkbox" value="' + esc(board.id) + '"><span><strong>' + esc(board.name || board.id) + '</strong><small>' + (board.closed ? 'Архивная' : 'Активная') + ' · ' + esc(board.dateLastActivity || '') + '</small></span></label>'; }).join('');
    target.querySelectorAll('input').forEach(function (input) { input.addEventListener('change', function () { state.selectedBoards = Array.from(target.querySelectorAll('input:checked')).map(function (x) { return x.value; }); qs('#trelloStart').disabled = state.selectedBoards.length === 0; }); });
  }

  async function createAndStart() {
    if (!state.connection || !state.selectedBoards.length) return;
    var button = qs('#trelloStart'); button.disabled = true; message('Создание job…');
    try {
      var options = { download_attachments: !!qs('#trelloAttachments').checked, include_archived: !!qs('#trelloArchived').checked, default_list_mode: qs('#trelloListMode').value, max_attachment_size_mb: 20 };
      var created = await request(moduleApi + '/jobs', { method: 'POST', body: { connection_public_id: state.connection.public_id, board_ids: state.selectedBoards, mode: qs('#trelloMode').value, options: options } });
      state.job = data(created).job;
      await request(moduleApi + '/jobs/' + encodeURIComponent(state.job.public_id) + '/run', { method: 'POST', body: {} });
      message('Job поставлен в очередь.'); await loadJobs(); startPolling();
    } catch (error) { message('Не удалось запустить миграцию.', true); } finally { button.disabled = false; }
  }

  async function loadJobs() {
    var target = qs('#trelloJobs');
    try {
      var envelope = await request(moduleApi + '/jobs', { method: 'GET' });
      var items = data(envelope).items || [];
      if (!items.length) { target.innerHTML = '<div class="text-muted">Запущенных миграций нет.</div>'; return; }
      target.innerHTML = items.slice(0, 10).map(function (job) { return '<button type="button" class="trello-job ' + (state.job && state.job.public_id === job.public_id ? 'is-active' : '') + '" data-job="' + esc(job.public_id) + '"><span><strong>' + esc(job.mode) + '</strong><small>' + esc(job.status) + '</small></span><b>' + Number(job.progress_percent || 0).toFixed(0) + '%</b></button>'; }).join('');
      target.querySelectorAll('[data-job]').forEach(function (button) { button.addEventListener('click', function () { state.job = items.find(function (x) { return x.public_id === button.dataset.job; }); startPolling(); }); });
    } catch (error) { target.innerHTML = '<div class="text-danger">Не удалось загрузить jobs.</div>'; }
  }

  async function refreshJob() {
    if (!state.job) return;
    try {
      var envelope = await request(moduleApi + '/jobs/' + encodeURIComponent(state.job.public_id), { method: 'GET', noCache: true });
      state.job = data(envelope).job || state.job; renderProgress();
      if (['completed', 'completed_with_warnings', 'failed', 'cancelled', 'rolled_back'].indexOf(String(state.job.status)) >= 0) { stopPolling(); await loadReport(); await loadJobs(); }
    } catch (error) { /* next poll retries */ }
  }

  function startPolling() { stopPolling(); refreshJob(); state.poll = window.setInterval(refreshJob, 4000); }
  function stopPolling() { if (state.poll) { window.clearInterval(state.poll); state.poll = null; } }
  function renderProgress() {
    var target = qs('#trelloProgress'); if (!target || !state.job) return; target.classList.remove('d-none');
    var percent = Math.max(0, Math.min(100, Number(state.job.progress_percent || 0)));
    target.innerHTML = '<div class="d-flex justify-content-between small mb-1"><span>' + esc(state.job.current_step || 'Ожидание') + '</span><strong>' + percent.toFixed(0) + '%</strong></div><div class="progress mb-2"><div class="progress-bar" style="width:' + percent + '%"></div></div><div class="text-muted small">' + esc(state.job.status || '') + '</div>';
  }

  async function loadReport() {
    if (!state.job) return;
    try {
      var envelope = await request(moduleApi + '/jobs/' + encodeURIComponent(state.job.public_id) + '/report', { method: 'GET', noCache: true });
      var report = data(envelope).report || {}; var counts = report.items || {};
      message('Готово: импортировано ' + (counts.imported || 0) + ', с предупреждениями ' + (counts.failed || 0) + '.');
    } catch (error) { message('Отчёт пока недоступен.', true); }
  }

  qs('#trelloAddConnection').addEventListener('click', showConnectionModal);
  qs('#trelloDiscover').addEventListener('click', discover);
  qs('#trelloStart').addEventListener('click', createAndStart);
  qs('#trelloRefreshJobs').addEventListener('click', loadJobs);
  qs('#trelloConnectionForm').addEventListener('submit', async function (event) {
    event.preventDefault(); var form = event.currentTarget; var errorBox = qs('#trelloConnectionError'); errorBox.classList.add('d-none');
    var body = Object.fromEntries(new FormData(form).entries());
    try {
      var created = await request(moduleApi + '/connections', { method: 'POST', body: body });
      var connection = data(created).connection || {};
      if (String(connection.status || '') !== 'active') throw new Error('TRELLO_CONNECTION_TEST_FAILED');
      form.reset(); window.bootstrap.Modal.getOrCreateInstance(qs('#trelloConnectionModal')).hide(); await loadConnections(); message('Подключение сохранено и проверено.');
    } catch (error) {
      errorBox.textContent = 'Не удалось сохранить или проверить подключение. Проверьте API key, token и APP_SECRET.';
      errorBox.classList.remove('d-none');
    }
  });
  loadConnections(); loadJobs();
})();
