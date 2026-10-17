(() => {
  'use strict';
  const api = (path, options = {}) => {
    if (window.CRM?.api?.request) return window.CRM.api.request(path, options).then(result => result.data || result);
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    const url = new URL('/api/index.php', window.location.origin);
    url.searchParams.set('route', path);
    Object.entries(options.query || {}).forEach(([key, value]) => url.searchParams.set(key, String(value)));
    const body = options.body === undefined ? undefined : JSON.stringify(options.body);
    if (body) headers['Content-Type'] = 'application/json';
    return fetch(url.toString(), { ...options, method, headers, body, credentials: 'same-origin' }).then(async response => {
      const result = await response.json().catch(() => ({}));
      if (!response.ok || result.success === false) throw new Error(result.message || result.code || 'Запрос не выполнен');
      return result.data || result;
    });
  };
  const $ = id => document.getElementById(id);
  const grid = $('kitsGrid');
  if (!grid) return;
  const labels = {
    statuses: 'Статусы задач', roles: 'Роли доступа', project_templates: 'Шаблоны проектов',
    task_templates: 'Шаблоны задач', knowledge_spaces: 'Пространства базы знаний'
  };
  let selectedKit = null;
  let preview = null;
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const message = (text, kind = 'info') => {
    const node = $('skitsMessage');
    if (!node) return;
    node.textContent = text;
    node.className = `alert alert-${kind} mt-3`;
    node.classList.remove('d-none');
  };
  const loadKits = async () => {
    grid.innerHTML = '<div class="crm-card"><div class="crm-card-body">Загрузка наборов…</div></div>';
    try {
      const result = await api('_module/crm.starter-kits/kits');
      const kits = Array.isArray(result.kits) ? result.kits : [];
      if (!kits.length) {
        grid.innerHTML = '<div class="crm-card"><div class="crm-card-body">В каталоге пока нет стартовых наборов.</div></div>';
        return;
      }
      grid.innerHTML = kits.map(kit => {
        const counts = Object.entries(kit.components_count || {}).filter(([, count]) => Number(count) > 0).map(([key, count]) => `<li>${escapeHtml(labels[key] || key)}: ${Number(count)}</li>`).join('');
        return `<article class="crm-card crm-starter-kit-card"><div class="crm-card-body"><div class="d-flex align-items-start justify-content-between gap-2"><h3 class="h5 mb-2">${escapeHtml(kit.title)}</h3><span class="badge text-bg-light">${escapeHtml(kit.version)}</span></div><p class="text-muted">${escapeHtml(kit.description)}</p><ul class="small">${counts || '<li>Состав уточняется</li>'}</ul><button type="button" class="btn crm-btn-primary" data-preview-kit="${escapeHtml(kit.kit_code)}">Проверить состав</button></div></article>`;
      }).join('');
      grid.querySelectorAll('[data-preview-kit]').forEach(button => button.addEventListener('click', () => showPreview(button.dataset.previewKit)));
    } catch (error) {
      grid.innerHTML = '<div class="crm-card"><div class="crm-card-body text-danger">Не удалось загрузить стартовые наборы.</div></div>';
      message(error.message || 'Ошибка загрузки каталога', 'danger');
    }
  };
  const renderPreview = data => {
    preview = data;
    const panel = $('skitsPreviewPanel');
    panel.classList.remove('d-none');
    $('skitsPreviewDescription').textContent = `${data.title} · версия ${data.version}${data.is_already_applied ? ' · этот набор уже применялся ранее' : ''}`;
    const rows = Object.keys(labels).map(key => {
      const create = data.will_create?.[key] || [];
      const conflicts = data.conflicts?.[key] || [];
      return `<section class="mb-3"><h3 class="h6">${escapeHtml(labels[key])}</h3><p class="small mb-1">Будет добавлено: ${create.length}; пропущено из-за совпадений: ${conflicts.length}</p>${conflicts.length ? `<ul class="small text-muted">${conflicts.map(item => `<li>${escapeHtml(item.title || item.name || item.code || item.slug)} — уже существует</li>`).join('')}</ul>` : ''}</section>`;
    }).join('');
    $('skitsPreviewContent').innerHTML = rows;
    $('skitsComponentChoices').innerHTML = Object.keys(labels).map(key => {
      const count = (data.will_create?.[key] || []).length;
      const canManageRoles = Boolean(data.can_manage_global_roles);
      const disabled = count === 0 || (key === 'roles' && !canManageRoles) ? 'disabled' : '';
      const notice = key === 'roles' ? (canManageRoles ? ' (общие для CRM)' : ' (только главный администратор)') : '';
      return `<label class="form-check"><input class="form-check-input" type="checkbox" value="${key}" ${disabled}><span class="form-check-label">${escapeHtml(labels[key])}${notice} · ${count}</span></label>`;
    }).join('');
    const apply = $('skitsApplyBtn');
    apply.disabled = true;
    $('skitsComponentChoices').querySelectorAll('input').forEach(input => input.addEventListener('change', () => {
      apply.disabled = !$('skitsComponentChoices').querySelector('input:checked');
    }));
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  const showPreview = async kitId => {
    selectedKit = kitId;
    $('skitsPreviewContent').textContent = 'Проверяем состав и возможные совпадения…';
    $('skitsPreviewPanel').classList.remove('d-none');
    $('skitsApplyBtn').disabled = true;
    try {
      const result = await api('_module/crm.starter-kits/preview', { method: 'POST', body: { kit_id: kitId } });
      if (!result.preview) throw new Error('Сервер не вернул предпросмотр');
      renderPreview(result.preview);
    } catch (error) {
      $('skitsPreviewContent').textContent = 'Не удалось получить предпросмотр. Набор не применён.';
      message(error.message || 'Ошибка предпросмотра', 'danger');
    }
  };
  const applyKit = async () => {
    if (!selectedKit || !preview) return;
    const selected = [...$('skitsComponentChoices').querySelectorAll('input:checked')].map(input => input.value);
    if (!selected.length) return;
    const warning = selected.includes('roles') ? '\n\nВы выбрали роли доступа. Роли общие для CRM и будут видны в других рабочих пространствах; они не назначаются новым пользователям автоматически.' : '';
    if (!window.confirm(`Применить выбранные компоненты к текущему рабочему пространству? Это действие создаст новые данные и не удаляет существующие.${warning}`)) return;
    const button = $('skitsApplyBtn');
    button.disabled = true;
    button.textContent = 'Применяем…';
    try {
      const result = await api('_module/crm.starter-kits/apply', { method: 'POST', body: { kit_id: selectedKit, selected_components: selected } });
      const total = Object.values(result.applied_objects || {}).reduce((sum, count) => sum + Number(count || 0), 0);
      message(`Набор применён. Создано объектов: ${total}; пропущено совпадений: ${Number(result.conflicts_skipped || 0)}.`, 'success');
      await showPreview(selectedKit);
    } catch (error) {
      message(`Не удалось применить набор: ${error.message || 'ошибка API'}`, 'danger');
    } finally {
      button.textContent = 'Применить выбранное';
      button.disabled = !$('skitsComponentChoices').querySelector('input:checked');
    }
  };
  $('skitsRefreshBtn')?.addEventListener('click', loadKits);
  $('skitsClosePreview')?.addEventListener('click', () => $('skitsPreviewPanel').classList.add('d-none'));
  $('skitsApplyBtn')?.addEventListener('click', applyKit);
  loadKits();
})();
