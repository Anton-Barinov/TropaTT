(() => {
  'use strict';

  const api = (path, options = {}) => window.CRM.api.request(`_module/crm.bitrix24-migration${path}`, options).then(envelope => envelope.data || {});
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const state = {connections: [], selected: null, jobs: []};
  const $ = id => document.getElementById(id);
  const message = (text, danger = false) => {
    $('b24Message').textContent = text;
    $('b24Message').className = `small mt-3 ${danger ? 'text-danger' : 'text-success'}`;
  };

  const loadMappings = async () => {
    const target = $('b24Mappings');
    if (!state.selected) { target.innerHTML = ''; return; }
    try {
      const data = await api(`module/bitrix24-migration/connections/${encodeURIComponent(state.selected.public_id)}/user-mappings`);
      const items = data.data?.items || data.items || [];
      target.innerHTML = items.length ? `<div class="small text-muted mb-2">Сопоставление пользователей (CRM public_id)</div>${items.map(item => `<div class="input-group input-group-sm mb-1"><span class="input-group-text flex-grow-1 text-start">${esc(item.display_name || item.bitrix_user_id)}</span><input class="form-control b24-user-map" data-mapping-id="${esc(item.id)}" value="${esc(item.crm_user_public_id || '')}" placeholder="crm_..."><button class="btn crm-btn-secondary" data-mapping-save="${esc(item.id)}">Сохранить</button></div>`).join('')}` : '<div class="small text-muted">Сначала выполните discovery пользователей.</div>';
      target.querySelectorAll('[data-mapping-save]').forEach(button => button.addEventListener('click', () => saveMapping(button.dataset.mappingSave)));
    } catch (error) {
      target.textContent = error.message;
    }
  };

  const saveMapping = async mappingId => {
    const input = document.querySelector(`.b24-user-map[data-mapping-id="${CSS.escape(mappingId)}"]`);
    if (!state.selected || !input) return;
    try {
      await api(`module/bitrix24-migration/connections/${encodeURIComponent(state.selected.public_id)}/user-mappings/${encodeURIComponent(mappingId)}`, {method: 'PATCH', body: JSON.stringify({crm_user_public_id: input.value.trim() || null})});
      message('Сопоставление пользователя сохранено.');
      await loadMappings();
    } catch (error) { message(error.message, true); }
  };

  const connectionAction = async (action, id) => {
    const connection = state.connections.find(item => item.public_id === id);
    if (!connection) return;
    try {
      if (action === 'test') {
        await api(`module/bitrix24-migration/connections/${encodeURIComponent(id)}/test`, {method: 'POST', body: '{}'});
        message('Подключение успешно проверено.');
      } else if (action === 'discover') {
        await api(`module/bitrix24-migration/connections/${encodeURIComponent(id)}/discover`, {method: 'POST', body: JSON.stringify({entities: ['user']})});
        state.selected = connection;
        await loadMappings();
        message('Пользователи обнаружены; выполните их сопоставление с CRM.');
      } else if (action === 'delete') {
        if (!window.confirm('Удалить подключение и историю его миграций?')) return;
        await api(`module/bitrix24-migration/connections/${encodeURIComponent(id)}`, {method: 'DELETE'});
        if (state.selected?.public_id === id) state.selected = null;
        await loadConnections();
        await loadMappings();
        message('Подключение удалено.');
      }
      await loadConnections();
    } catch (error) { message(error.message, true); }
  };

  const loadConnections = async () => {
    try {
      const data = await api('module/bitrix24-migration/connections');
      state.connections = data.data?.connections || data.connections || [];
      $('b24Connections').innerHTML = state.connections.length ? state.connections.map(connection => `<div class="b24-connection-row"><button class="b24-connection ${state.selected?.public_id === connection.public_id ? 'is-selected' : ''}" data-id="${esc(connection.public_id)}"><strong>${esc(connection.name)}</strong><span>${esc(connection.auth_type)} · ${esc(connection.status)}</span></button><div class="btn-group btn-group-sm"><button class="btn crm-btn-secondary" data-connection-action="test" data-id="${esc(connection.public_id)}">Проверить</button><button class="btn crm-btn-secondary" data-connection-action="discover" data-id="${esc(connection.public_id)}">Discovery</button><button class="btn btn-outline-danger" data-connection-action="delete" data-id="${esc(connection.public_id)}">Удалить</button></div></div>`).join('') : '<div class="b24-state">Подключений пока нет.</div>';
      document.querySelectorAll('.b24-connection').forEach(button => button.addEventListener('click', async () => { state.selected = state.connections.find(item => item.public_id === button.dataset.id) || null; $('b24CreateJob').disabled = !state.selected; await loadConnections(); await loadMappings(); }));
      document.querySelectorAll('[data-connection-action]').forEach(button => button.addEventListener('click', () => connectionAction(button.dataset.connectionAction, button.dataset.id)));
    } catch (error) { $('b24Connections').textContent = error.message; }
  };

  const loadJobs = async () => {
    try {
      const data = await api('module/bitrix24-migration/jobs');
      state.jobs = data.data?.items || data.items || [];
      $('b24Jobs').innerHTML = state.jobs.length ? state.jobs.slice(0, 20).map(job => `<div class="b24-job"><div><strong>${esc(job.connection_name || job.public_id)}</strong><span>${esc(job.status)} · ${esc(job.mode)}</span></div><div class="b24-job-actions"><button class="btn btn-sm crm-btn-secondary" data-action="refresh" data-id="${esc(job.public_id)}">Обновить</button>${['draft','failed','cancelled'].includes(job.status) ? `<button class="btn btn-sm crm-btn-primary" data-action="run" data-id="${esc(job.public_id)}">Запустить</button>` : ''}${job.status === 'paused' ? `<button class="btn btn-sm crm-btn-primary" data-action="resume" data-id="${esc(job.public_id)}">Продолжить</button>` : ''}${job.status === 'running' ? `<button class="btn btn-sm crm-btn-secondary" data-action="pause" data-id="${esc(job.public_id)}">Пауза</button>` : ''}${['queued','running','pausing','cancelling'].includes(job.status) ? `<button class="btn btn-sm btn-outline-danger" data-action="cancel" data-id="${esc(job.public_id)}">Отменить</button>` : ''}${['failed','completed_with_warnings','cancelled'].includes(job.status) ? `<button class="btn btn-sm crm-btn-secondary" data-action="retry" data-id="${esc(job.public_id)}">Повторить ошибки</button>` : ''}${['completed','completed_with_warnings','failed','cancelled'].includes(job.status) ? `<button class="btn btn-sm btn-outline-secondary" data-action="rollback" data-id="${esc(job.public_id)}">Rollback</button>` : ''}</div><div class="progress mt-2"><div class="progress-bar" style="width:${Number(job.progress_percent || 0)}%">${Number(job.progress_percent || 0).toFixed(0)}%</div></div></div>`).join('') : '<div class="b24-state">Jobs пока нет.</div>';
      document.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => jobAction(button.dataset.action, button.dataset.id)));
    } catch (error) { $('b24Jobs').textContent = error.message; }
  };

  const jobAction = async (action, id) => {
    try {
      if (action === 'rollback' && !window.confirm('Удалить только объекты, созданные этой миграцией?')) return;
      const suffix = action === 'run' ? 'run' : action === 'pause' ? 'pause' : action === 'resume' ? 'resume' : action === 'cancel' ? 'cancel' : action === 'retry' ? 'retry-failed' : action === 'rollback' ? 'rollback' : '';
      if (suffix) await api(`module/bitrix24-migration/jobs/${encodeURIComponent(id)}/${suffix}`, {method: 'POST', body: '{}'});
      await loadJobs();
    } catch (error) { message(error.message, true); }
  };

  const createConnection = async event => {
    event.preventDefault();
    const form = $('b24ConnectionForm');
    const input = Object.fromEntries(new FormData(form).entries());
    try {
      await api('module/bitrix24-migration/connections', {method: 'POST', body: JSON.stringify(input)});
      bootstrap.Modal.getOrCreateInstance($('b24ConnectionModal')).hide();
      form.reset();
      await loadConnections();
      message('Подключение проверено и сохранено.');
    } catch (error) { $('b24Error').textContent = error.message; $('b24Error').classList.remove('d-none'); }
  };

  const createJob = async () => {
    if (!state.selected) return;
    const entities = [...document.querySelectorAll('.b24-entity:checked')].map(input => input.value);
    try {
      await api('module/bitrix24-migration/jobs', {method: 'POST', body: JSON.stringify({connection_public_id: state.selected.public_id, mode: $('b24Mode').value, entities, target_options: {include_comments: $('b24Comments').checked, include_files: $('b24Files').checked, include_products: $('b24Products').checked, include_archived: $('b24Archived').checked, events_from: $('b24EventsFrom').value, events_to: $('b24EventsTo').value}})});
      message('Job создан. Запустите его из списка.');
      await loadJobs();
    } catch (error) { message(error.message, true); }
  };

  document.addEventListener('DOMContentLoaded', () => {
    $('b24AddConnection')?.addEventListener('click', () => bootstrap.Modal.getOrCreateInstance($('b24ConnectionModal')).show());
    $('b24ConnectionForm')?.addEventListener('submit', createConnection);
    $('b24CreateJob')?.addEventListener('click', createJob);
    $('b24Refresh')?.addEventListener('click', () => { loadConnections(); loadJobs(); });
    $('b24AuthType')?.addEventListener('change', event => { $('b24WebhookFields').classList.toggle('d-none', event.target.value !== 'webhook'); $('b24OAuthFields').classList.toggle('d-none', event.target.value !== 'oauth'); });
    loadConnections();
    loadJobs();
  });
})();
