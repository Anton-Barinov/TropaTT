(() => {
  'use strict';
  const api = (path, options = {}) => {
    if (window.CRM?.api?.request) {
      return window.CRM.api.request(path, options).then(envelope => {
        if (envelope?.success === false) throw new Error(envelope.message || envelope.code || 'Ошибка API');
        return envelope?.data !== undefined ? envelope.data : envelope;
      });
    }
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    const body = options.body == null ? undefined : (typeof options.body === 'string' ? options.body : JSON.stringify(options.body));
    if (body !== undefined && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    return fetch(path, { ...options, method, credentials: 'same-origin', headers, body }).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) throw new Error(data.message || data.code || 'Ошибка API');
      return data.data !== undefined ? data.data : data;
    });
  };
  const $ = id => document.getElementById(id);
  const message = (text, error = false) => {
    const node = $('wappMessage'); if (!node) return;
    node.textContent = text; node.className = `small mt-3 ${error ? 'text-danger' : 'text-success'}`;
  };
  let selectedConversation = '';
  let retryKeys = new Map();

  const loadStatus = async () => {
    const badge = $('wappStatusBadge'), notice = $('wappCapabilityNotice');
    if (badge) badge.textContent = 'Проверка…';
    try {
      const status = await api('_module/crm.whatsapp-business/config');
      const configured = status.configured === true;
      if (badge) { badge.textContent = configured ? 'Подключение настроено' : 'Не настроено'; badge.className = `badge ${configured ? 'text-bg-success' : 'text-bg-warning'}`; }
      if ($('wappPhoneNumberId')) $('wappPhoneNumberId').value = status.phone_number_id || '';
      if ($('wappWabaId')) $('wappWabaId').value = status.waba_id || '';
      if ($('wappWebhookUrl')) $('wappWebhookUrl').value = `${window.location.origin}${status.webhook_url || ''}`;
      if (notice) { notice.className = `alert ${configured ? 'alert-success' : 'alert-warning'}`; notice.textContent = configured ? 'Подключение настроено. Meta может подтвердить webhook и отправлять подписанные события. Ответы доступны в течение 24 часов после последнего сообщения клиента.' : 'Укажите реквизиты Meta WhatsApp Business Platform. Секреты шифруются сервером и после сохранения повторно не отображаются.'; }
    } catch (_) {
      if (badge) { badge.textContent = 'Состояние недоступно'; badge.className = 'badge text-bg-danger'; }
      if (notice) { notice.className = 'alert alert-danger'; notice.textContent = 'Не удалось проверить настройки. Проверьте права доступа и повторите запрос.'; }
      message('Не удалось загрузить настройки подключения.', true);
    }
  };

  const loadConversation = async id => {
    selectedConversation = id;
    const pane = $('wappConversation'); if (!pane) return;
    pane.replaceChildren(); pane.textContent = 'Загружаю переписку…';
    try {
      const data = await api(`_module/crm.whatsapp-business/inbox/${encodeURIComponent(id)}`);
      pane.replaceChildren();
      const messages = document.createElement('div'); messages.className = 'wapp-messages';
      for (const item of (Array.isArray(data.messages) ? data.messages : [])) {
        const bubble = document.createElement('article'); bubble.className = `wapp-message ${item.direction === 'outbound' ? 'is-outbound' : 'is-inbound'}`;
        const body = document.createElement('div'); body.className = 'wapp-message-text'; body.textContent = String(item.message_text || '');
        const meta = document.createElement('div'); meta.className = 'wapp-message-meta'; meta.textContent = `${item.direction === 'outbound' ? 'Ответ команды' : 'Сообщение клиента'} · ${String(item.created_at || '')}${item.delivery_status ? ` · ${String(item.delivery_status)}` : ''}`;
        bubble.append(body, meta); messages.append(bubble);
      }
      if (!data.messages?.length) messages.textContent = 'Сообщений пока нет.';
      pane.append(messages);
      const form = document.createElement('form'); form.className = 'wapp-reply-form';
      const textarea = document.createElement('textarea'); textarea.className = 'form-control'; textarea.rows = 3; textarea.maxLength = 4096; textarea.required = true; textarea.placeholder = 'Напишите ответ клиенту'; textarea.setAttribute('aria-label', 'Текст ответа');
      const controls = document.createElement('div'); controls.className = 'd-flex justify-content-between align-items-center gap-2 mt-2';
      const hint = document.createElement('small'); hint.className = 'text-muted'; hint.textContent = 'Ответ отправляется через WhatsApp Cloud API.';
      const button = document.createElement('button'); button.type = 'submit'; button.className = 'btn crm-btn-primary'; button.textContent = 'Отправить';
      controls.append(hint, button); form.append(textarea, controls);
      form.addEventListener('submit', async event => {
        event.preventDefault(); const text = textarea.value.trim(); if (!text || button.disabled) return;
        const keyId = `${id}\0${text}`; let key = retryKeys.get(keyId);
        if (!key) { key = (globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}-${Math.random()}`).replace(/[^A-Za-z0-9._:-]/g, '').padEnd(16, '0'); retryKeys.set(keyId, key); }
        button.disabled = true; message('Отправляю ответ…');
        try {
          await api(`_module/crm.whatsapp-business/inbox/${encodeURIComponent(id)}/reply`, { method: 'POST', headers: { 'Idempotency-Key': key }, body: { text } });
          retryKeys.delete(keyId); textarea.value = ''; message('Ответ отправлен.'); await loadConversation(id); await loadInbox();
        } catch (error) { message(error instanceof Error ? error.message : 'Не удалось подтвердить отправку. Перед повтором проверьте переписку.', true); }
        finally { button.disabled = false; }
      });
      pane.append(form);
      messages.scrollTop = messages.scrollHeight;
    } catch (_) { pane.textContent = 'Не удалось загрузить переписку. Проверьте доступ к рабочему пространству.'; }
  };

  const loadInbox = async () => {
    const state = $('wappInboxState'), list = $('wappInboxList'); if (!state || !list) return;
    state.textContent = 'Загружаю диалоги…'; list.replaceChildren();
    try {
      const result = await api('_module/crm.whatsapp-business/inbox');
      const items = Array.isArray(result?.items) ? result.items : [];
      state.textContent = items.length ? `Диалогов: ${items.length}` : 'В этом рабочем пространстве пока нет диалогов.';
      for (const item of items) {
        const card = document.createElement('button'); card.type = 'button'; card.className = `wapp-conversation-card ${selectedConversation === item.public_id ? 'is-selected' : ''}`;
        const heading = document.createElement('strong'); heading.textContent = String(item.contact_name || 'Контакт WhatsApp');
        const phone = document.createElement('span'); phone.className = 'small text-muted'; phone.textContent = String(item.contact_phone || 'Телефон не указан');
        const snippet = document.createElement('span'); snippet.className = 'small'; snippet.textContent = String(item.last_message || 'Нет сообщений');
        const meta = document.createElement('span'); meta.className = 'small text-muted'; meta.textContent = `${String(item.status || 'open')} · ${String(item.last_message_at || '')}`;
        card.append(heading, phone, snippet, meta); card.addEventListener('click', () => void loadConversation(String(item.public_id))); list.append(card);
      }
      if (!items.length) { selectedConversation = ''; $('wappConversation')?.replaceChildren(Object.assign(document.createElement('div'), { textContent: 'Новые обращения появятся здесь после подключения webhook в Meta.' })); }
      else if (selectedConversation && items.some(item => item.public_id === selectedConversation)) void loadConversation(selectedConversation);
    } catch (_) { state.textContent = 'Не удалось загрузить очередь WhatsApp. Проверьте права доступа.'; state.className = 'text-danger'; }
  };

  document.addEventListener('DOMContentLoaded', () => {
    const form = $('wappConfigForm'); if (!form) return;
    form.addEventListener('submit', async event => {
      event.preventDefault(); const button = $('wappSaveBtn'); if (button?.disabled) return;
      button.disabled = true; message('Шифрую и сохраняю реквизиты…');
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await api('_module/crm.whatsapp-business/config', { method: 'POST', body: payload });
        for (const field of ['wappApiToken', 'wappVerifyToken', 'wappAppSecret']) if ($(field)) $(field).value = '';
        message('Реквизиты сохранены в зашифрованном виде.'); await loadStatus();
      } catch (_) { message('Не удалось сохранить настройки. Проверьте реквизиты и права доступа.', true); }
      finally { button.disabled = false; }
    });
    $('wappRefreshBtn')?.addEventListener('click', loadStatus); $('wappInboxRefresh')?.addEventListener('click', loadInbox);
    $('wappCopyWebhook')?.addEventListener('click', async () => {
      const url = $('wappWebhookUrl')?.value || ''; if (!url) return;
      try { await navigator.clipboard.writeText(url); message('Webhook URL скопирован.'); }
      catch { $('wappWebhookUrl')?.select(); message('Выделите URL и скопируйте вручную.', true); }
    });
    void loadStatus(); void loadInbox();
  });
})();
