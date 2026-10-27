(() => {
  'use strict';

  const $ = id => document.getElementById(id);
  const state = { requests: [], selectedId: '' };
  const api = (path, options = {}) => window.CRM.api.request(path, options).then(envelope => envelope.data || envelope);

  const message = (text, isError = false) => {
    const node = $('cportMessage');
    node.textContent = text;
    node.className = 'small mt-3 ' + (isError ? 'text-danger' : 'text-success');
  };

  const textNode = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = String(text ?? '');
    return node;
  };

  const formatDate = value => {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  };

  const selectedRequest = () => state.requests.find(item => String(item.public_id) === state.selectedId) || null;

  const renderRequestList = () => {
    const list = $('cportRequestList');
    const query = $('cportSearch').value.trim().toLocaleLowerCase();
    const status = $('cportStatusFilter').value;
    const visible = state.requests.filter(item => {
      const text = `${item.title || ''} ${item.category || ''} ${item.public_id || ''}`.toLocaleLowerCase();
      return (!query || text.includes(query)) && (!status || String(item.service_status || '') === status);
    });
    $('cportRequestCount').textContent = String(visible.length);
    list.replaceChildren();
    if (!visible.length) {
      list.appendChild(textNode('div', 'list-group-item text-muted py-4 text-center', state.requests.length ? 'По выбранным фильтрам обращений нет.' : 'В этом рабочем пространстве обращений пока нет.'));
      return;
    }
    visible.forEach(item => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'list-group-item list-group-item-action cport-request-item' + (String(item.public_id) === state.selectedId ? ' active' : '');
      button.setAttribute('aria-pressed', String(item.public_id) === state.selectedId ? 'true' : 'false');
      const heading = document.createElement('div');
      heading.className = 'd-flex justify-content-between align-items-start gap-2';
      heading.appendChild(textNode('strong', 'cport-request-title', item.title || 'Без темы'));
      heading.appendChild(textNode('span', 'badge text-bg-light flex-shrink-0', item.service_status || '—'));
      button.appendChild(heading);
      const meta = document.createElement('div');
      meta.className = 'small text-muted mt-2';
      meta.textContent = `${item.category || 'Общее'} · ${formatDate(item.created_at)}`;
      button.appendChild(meta);
      const sla = textNode('div', 'small mt-1 cport-sla-' + String(item.sla_status || 'unknown').replace(/[^a-z0-9_-]/gi, ''), `SLA: ${item.sla_status || 'не указан'}`);
      button.appendChild(sla);
      button.addEventListener('click', () => selectRequest(String(item.public_id)));
      list.appendChild(button);
    });
  };

  const renderFilters = () => {
    const select = $('cportStatusFilter');
    const oldValue = select.value;
    const statuses = [...new Set(state.requests.map(item => String(item.service_status || '')).filter(Boolean))].sort();
    select.replaceChildren(new Option('Все статусы', ''));
    statuses.forEach(value => select.add(new Option(value, value)));
    select.value = statuses.includes(oldValue) ? oldValue : '';
  };

  const loadRequests = async () => {
    $('cportRequestList').replaceChildren(textNode('div', 'list-group-item text-muted', 'Загрузка очереди…'));
    try {
      const data = await api('/api/v1/client-portal/requests');
      state.requests = Array.isArray(data.items) ? data.items : [];
      if (!state.requests.some(item => String(item.public_id) === state.selectedId)) state.selectedId = '';
      renderFilters();
      renderRequestList();
      if (state.selectedId) await renderDetail();
      else {
        $('cportDetail').hidden = true;
        $('cportDetailEmpty').hidden = false;
      }
      message('Очередь обновлена.');
    } catch (_) {
      state.requests = [];
      renderRequestList();
      message('Не удалось загрузить обращения. Проверьте право client.manage и активное рабочее пространство.', true);
    }
  };

  const renderMessages = items => {
    const panel = $('cportMessages');
    panel.replaceChildren();
    if (!items.length) {
      panel.appendChild(textNode('p', 'text-muted mb-0', 'В переписке пока нет сообщений.'));
      return;
    }
    items.forEach(item => {
      const article = document.createElement('article');
      article.className = 'cport-message';
      const meta = document.createElement('div');
      meta.className = 'd-flex flex-wrap justify-content-between gap-2 small text-muted mb-2';
      meta.appendChild(textNode('span', '', item.author_type === 'client' ? 'Клиент' : 'Команда'));
      if (Object.prototype.hasOwnProperty.call(item, 'is_client_visible')) {
        meta.appendChild(textNode('span', item.is_client_visible ? 'cport-visibility-public' : 'cport-visibility-internal', item.is_client_visible ? 'Видно клиенту' : 'Внутренняя заметка'));
      }
      meta.appendChild(textNode('time', '', formatDate(item.created_at)));
      article.appendChild(meta);
      article.appendChild(textNode('p', 'mb-0 cport-message-text', item.message_text || ''));
      panel.appendChild(article);
    });
  };

  const renderDetail = async () => {
    const item = selectedRequest();
    if (!item) return;
    const requestId = state.selectedId;
    $('cportDetailEmpty').hidden = true;
    $('cportDetail').hidden = false;
    $('cportDetailTitle').textContent = item.title || 'Без темы';
    $('cportDetailStatus').textContent = item.service_status || '—';
    $('cportRequestMeta').textContent = `${item.category || 'Общее'} · ${item.public_id}`;
    $('cportSlaMeta').textContent = `Создано: ${formatDate(item.created_at)} · Ответ до: ${formatDate(item.sla_response_due_at)} · SLA: ${item.sla_status || 'не указан'}`;
    $('cportDescription').textContent = 'Загрузка описания…';
    $('cportMessages').replaceChildren(textNode('p', 'text-muted mb-0', 'Загрузка переписки…'));
    try {
      const requestData = await api(`/api/v1/client-portal/requests/${encodeURIComponent(requestId)}`);
      if (state.selectedId !== requestId) return;
      const request = requestData.request;
      $('cportDescription').textContent = request && typeof request.description === 'string' ? request.description : 'Описание отсутствует.';
      if (request) {
        $('cportDetailTitle').textContent = request.title || item.title || 'Без темы';
        $('cportDetailStatus').textContent = request.service_status || item.service_status || '—';
        $('cportSlaMeta').textContent = `Создано: ${formatDate(request.created_at)} · Ответ до: ${formatDate(request.sla_response_due_at)} · Решить до: ${formatDate(request.sla_resolution_due_at)} · SLA: ${request.sla_status || 'не указан'}`;
      }
    } catch (_) {
      if (state.selectedId === requestId) $('cportDescription').textContent = 'Не удалось загрузить описание обращения.';
    }
    try {
      const data = await api(`/api/v1/client-portal/requests/${encodeURIComponent(requestId)}/messages`);
      if (state.selectedId !== requestId) return;
      renderMessages(Array.isArray(data.items) ? data.items : []);
    } catch (_) {
      if (state.selectedId === requestId) $('cportMessages').replaceChildren(textNode('p', 'text-danger mb-0', 'Не удалось загрузить переписку. Проверьте доступ к обращению.'));
    }
  };

  const selectRequest = async publicId => {
    state.selectedId = publicId;
    renderRequestList();
    await renderDetail();
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (!$('cportRequestList')) return;
    $('cportRefreshBtn').addEventListener('click', loadRequests);
    $('cportSearch').addEventListener('input', renderRequestList);
    $('cportStatusFilter').addEventListener('change', renderRequestList);
    $('cportReplyForm').addEventListener('submit', async event => {
      event.preventDefault();
      if (!state.selectedId) return;
      const button = $('cportSendReplyBtn');
      button.disabled = true;
      try {
        await api(`/api/v1/client-portal/requests/${encodeURIComponent(state.selectedId)}/messages`, {
          method: 'POST',
          body: { message_text: $('cportReplyText').value.trim(), is_client_visible: $('cportClientVisible').checked }
        });
        $('cportReplyText').value = '';
        message($('cportClientVisible').checked ? 'Ответ отправлен клиенту.' : 'Внутренняя заметка сохранена.');
        await renderDetail();
      } catch (_) {
        message('Не удалось отправить сообщение. Проверьте права доступа и текст сообщения.', true);
      } finally {
        button.disabled = false;
      }
    });
    loadRequests();
  });
})();
