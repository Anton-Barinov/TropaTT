(() => {
  'use strict';

  const api = (path, options = {}) => fetch(`../api/index.php?route=${encodeURIComponent(`_module/crm.todoist-migration${path}`)}`, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    ...options,
  }).then(async response => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false) {
      throw new Error(data.error?.message || data.message || 'Ошибка API');
    }
    return data.data || data;
  });

  const $ = id => document.getElementById(id);
  let selectedConnection = null;
  let projects = [];

  const escapeHtml = value => String(value).replace(/[&<>'"]/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  }[char]));

  const message = (text, bad = false) => {
    const el = $('todoistActionMessage');
    if (!el) return;
    el.textContent = text;
    el.className = `small mt-3 ${bad ? 'text-danger' : 'text-success'}`;
  };

  const renderConnections = items => {
    const root = $('todoistConnections');
    root.innerHTML = '';
    if (!items.length) {
      root.textContent = 'Подключений пока нет.';
      selectedConnection = null;
      $('todoistDiscover').disabled = true;
      $('todoistStart').disabled = true;
      return;
    }
    items.forEach(item => {
      const row = document.createElement('div');
      row.className = 'todoist-connection';
      row.innerHTML = `<div><strong>${escapeHtml(item.name)}</strong><div class="text-muted small">${escapeHtml(item.account_name || item.status || '')}</div></div><button class="btn btn-sm crm-btn-secondary" type="button">Выбрать</button>`;
      row.querySelector('button').addEventListener('click', () => selectConnection(item));
      root.appendChild(row);
    });
    if (selectedConnection) {
      const current = items.find(item => item.public_id === selectedConnection.public_id);
      if (current) selectConnection(current, false);
    }
  };

  const selectConnection = (item, refresh = true) => {
    selectedConnection = item;
    $('todoistDiscover').disabled = false;
    $('todoistStart').disabled = false;
    if (refresh) loadProjects();
    message(`Выбрано: ${item.name}`);
  };

  const loadConnections = () => api('/connections')
    .then(data => renderConnections(data.connections || data.items || []))
    .catch(error => { $('todoistConnections').textContent = error.message; });

  const loadProjects = () => {
    if (!selectedConnection) return Promise.resolve();
    $('todoistProjects').textContent = 'Загрузка проектов…';
    return api(`/connections/${encodeURIComponent(selectedConnection.public_id)}/projects`)
      .then(data => {
        projects = data.projects || [];
        const root = $('todoistProjects');
        root.innerHTML = '';
        if (!projects.length) {
          root.textContent = 'Проектов не найдено.';
          return;
        }
        projects.forEach(project => {
          const row = document.createElement('label');
          row.className = 'todoist-project';
          row.innerHTML = `<input type="checkbox" value="${escapeHtml(String(project.id))}" checked> <span>${escapeHtml(project.name || 'Без названия')}</span>`;
          root.appendChild(row);
        });
      })
      .catch(error => { $('todoistProjects').textContent = error.message; });
  };

  const jobAction = (publicId, action, successMessage) => api(`/jobs/${encodeURIComponent(publicId)}/${action}`, { method: 'POST' })
    .then(() => { message(successMessage); loadJobs(); })
    .catch(error => message(error.message, true));

  const jobActions = job => {
    const id = encodeURIComponent(job.public_id || '');
    const status = String(job.status || '');
    const actions = [];
    if (['draft', 'paused', 'failed', 'cancelled'].includes(status)) actions.push(`<button class="btn btn-sm crm-btn-primary" data-job-action="run" data-job-id="${id}" type="button">Запустить</button>`);
    if (['queued', 'running'].includes(status)) {
      actions.push(`<button class="btn btn-sm crm-btn-secondary" data-job-action="pause" data-job-id="${id}" type="button">Пауза</button>`);
      actions.push(`<button class="btn btn-sm btn-outline-danger" data-job-action="cancel" data-job-id="${id}" type="button">Отменить</button>`);
    }
    if (['completed_with_warnings', 'failed', 'cancelled'].includes(status)) actions.push(`<button class="btn btn-sm crm-btn-secondary" data-job-action="retry-failed" data-job-id="${id}" type="button">Повторить ошибки</button>`);
    if (['completed', 'completed_with_warnings', 'failed', 'cancelled', 'rollback_failed', 'rolled_back_with_warnings'].includes(status)) actions.push(`<button class="btn btn-sm btn-outline-danger" data-job-action="rollback" data-job-id="${id}" type="button">Откатить</button>`);
    return actions.join(' ');
  };

  const loadJobs = () => api('/jobs')
    .then(data => {
      const root = $('todoistJobs');
      const items = data.items || [];
      root.innerHTML = items.length ? items.map(job => `<div class="todoist-connection"><div><strong>${escapeHtml(job.connection_name || 'Todoist')}</strong><div class="text-muted small">${escapeHtml(job.current_step || '')}</div><div class="mt-2 d-flex gap-1 flex-wrap">${jobActions(job)}</div></div><span class="todoist-status">${escapeHtml(job.status || '')}</span></div>`).join('') : 'Jobs пока нет.';
      root.querySelectorAll('[data-job-action]').forEach(button => button.addEventListener('click', () => jobAction(button.dataset.jobId, button.dataset.jobAction, 'Состояние job обновлено.')));
    })
    .catch(error => { $('todoistJobs').textContent = error.message; });

  const addConnection = () => {
    const modal = bootstrap.Modal.getOrCreateInstance($('todoistConnectionModal'));
    $('todoistConnectionForm').reset();
    $('todoistPatFields')?.classList.remove('d-none');
    $('todoistOauthFields')?.classList.add('d-none');
    $('todoistConnectionError').classList.add('d-none');
    modal.show();
  };

  const submitConnection = event => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const error = $('todoistConnectionError');
    const oauth = form.get('auth_type') === 'oauth2';
    const payload = oauth
      ? { name: form.get('name'), client_id: form.get('client_id'), client_secret: form.get('client_secret'), code: form.get('code'), state: form.get('state'), redirect_uri: form.get('redirect_uri') }
      : { name: form.get('name'), access_token: form.get('access_token') };
    api(oauth ? '/oauth/exchange' : '/connections', { method: 'POST', body: JSON.stringify(payload) })
      .then(data => {
        bootstrap.Modal.getInstance($('todoistConnectionModal'))?.hide();
        if (data.connection) selectConnection(data.connection);
        return loadConnections();
      })
      .catch(e => { error.textContent = e.message; error.classList.remove('d-none'); });
  };

  const authorizeOAuth = () => {
    const form = new FormData($('todoistConnectionForm'));
    api('/oauth/authorize-url', { method: 'POST', body: JSON.stringify({ client_id: form.get('client_id'), redirect_uri: form.get('redirect_uri') }) })
      .then(data => {
        if (data.authorization_url) window.open(data.authorization_url, '_blank', 'noopener');
        message('Authorization URL открыт в новой вкладке. После callback заполните code и state.');
      })
      .catch(e => { const error = $('todoistConnectionError'); error.textContent = e.message; error.classList.remove('d-none'); });
  };

  const createJob = () => {
    if (!selectedConnection) return;
    const projectIds = [...document.querySelectorAll('#todoistProjects input:checked')].map(input => input.value);
    const includeCompleted = $('todoistCompleted').checked;
    const sourceScope = includeCompleted ? {
      completed_since: $('todoistCompletedSince')?.value || '',
      completed_until: $('todoistCompletedUntil')?.value || '',
    } : {};
    const targetOptions = {
      include_completed: includeCompleted,
      include_comments: $('todoistComments').checked,
      include_attachments: $('todoistAttachments').checked,
    };
    const button = $('todoistStart');
    button.disabled = true;
    api('/jobs', { method: 'POST', body: JSON.stringify({ connection_public_id: selectedConnection.public_id, project_ids: projectIds, mode: $('todoistMode').value, source_scope: sourceScope, target_options: targetOptions }) })
      .then(data => {
        const publicId = data.job?.public_id;
        if (!publicId) throw new Error('API не вернул идентификатор job');
        return api(`/jobs/${encodeURIComponent(publicId)}/run`, { method: 'POST' });
      })
      .then(() => { message('Job создан и поставлен в очередь.'); loadJobs(); })
      .catch(error => message(error.message, true))
      .finally(() => { button.disabled = false; });
  };

  document.addEventListener('DOMContentLoaded', () => {
    // This module's page may not be mounted (e.g. stale cached HTML that
    // still references this script). Never touch DOM that does not exist.
    if (!$('todoistConnections') && !$('todoistJobs')) return;
    $('todoistAddConnection')?.addEventListener('click', addConnection);
    $('todoistConnectionForm')?.addEventListener('submit', submitConnection);
    $('todoistOAuthAuthorize')?.addEventListener('click', authorizeOAuth);
    $('todoistAuthType')?.addEventListener('change', event => {
      const oauth = event.target.value === 'oauth2';
      $('todoistPatFields')?.classList.toggle('d-none', oauth);
      $('todoistOauthFields')?.classList.toggle('d-none', !oauth);
    });
    $('todoistCompleted')?.addEventListener('change', event => $('todoistCompletedRange')?.classList.toggle('d-none', !event.target.checked));
    $('todoistDiscover')?.addEventListener('click', loadProjects);
    $('todoistStart')?.addEventListener('click', createJob);
    $('todoistRefreshJobs')?.addEventListener('click', loadJobs);
    loadConnections();
    loadJobs();
  });
})();
