(() => {
  'use strict';

  const API = '/api/v1/_module/crm.mercadopago';
  const api = (path, options = {}) => window.CRM.api.request(`${API}${path}`, options).then(envelope => envelope.data || envelope);
  const $ = id => document.getElementById(id);
  const showMessage = (id, text, isError = false) => {
    const node = $(id);
    if (!node) return;
    node.textContent = text;
    node.className = 'small ' + (isError ? 'text-danger' : 'text-success');
  };
  const checkoutUrlAllowed = raw => {
    try {
      const url = new URL(raw);
      const host = url.hostname.toLowerCase();
      return url.protocol === 'https:' && !url.username && !url.password && (!url.port || url.port === '443')
        && (host === 'mercadopago.com' || host.endsWith('.mercadopago.com')
          || host === 'mercadopago.com.br' || host.endsWith('.mercadopago.com.br'));
    } catch (_) { return false; }
  };
  const money = (amount, currency) => `${String(amount)} ${String(currency)}`;
  let pendingCheckoutKey = '';
  let pendingCheckoutFingerprint = '';
  let connectionVerified = false;
  const checkoutIdempotencyKey = async payload => {
    const canonical = JSON.stringify(payload);
    let fingerprint = canonical;
    if (window.crypto && window.crypto.subtle && window.TextEncoder) {
      const digest = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(canonical));
      fingerprint = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
    }
    if (pendingCheckoutKey && pendingCheckoutFingerprint !== fingerprint) {
      throw new Error('Сначала сверьте незавершённую попытку оплаты. Нельзя менять сумму или реквизиты до разрешения результата.');
    }
    if (!pendingCheckoutKey) {
      pendingCheckoutKey = window.crypto && typeof window.crypto.randomUUID === 'function'
        ? window.crypto.randomUUID().replace(/-/g, '')
        : Array.from(window.crypto.getRandomValues(new Uint8Array(24)), byte => byte.toString(16).padStart(2, '0')).join('');
      pendingCheckoutFingerprint = fingerprint;
      try { sessionStorage.setItem('mp_checkout_retry', JSON.stringify({fingerprint, key: pendingCheckoutKey})); } catch (_) {}
    }
    return pendingCheckoutKey;
  };

  const loadStatus = async () => {
    try {
      const data = await api('/status');
      const configured = data.configured === true;
      $('moduleSetupState').textContent = configured ? (connectionVerified ? 'Подключение Mercado Pago проверено' : 'Ключ сохранён · соединение не проверено') : 'Mercado Pago не настроен';
      $('mpagoStatusMeta').textContent = configured
        ? (connectionVerified ? (data.sandbox_mode ? 'Проверено в тестовом режиме. Реальные списания не выполняются.' : 'Проверен рабочий режим Mercado Pago.') : 'Проверьте токен кнопкой «Проверить подключение» перед созданием ссылки.')
        : 'Добавьте access token, чтобы создавать платёжные ссылки.';
      $('mpagoStatusBox').className = 'alert mb-3 ' + (connectionVerified ? 'alert-success' : 'alert-warning');
      $('mpagoSandboxMode').checked = data.sandbox_mode !== false;
      $('mpagoPublicKey').value = data.public_key || '';
      $('mpagoTestConnectionBtn').disabled = !configured;
      $('mpagoCreatePaymentBtn').disabled = !configured || !connectionVerified;
      return configured;
    } catch (_) {
      $('moduleSetupState').textContent = 'Не удалось загрузить состояние';
      $('mpagoStatusMeta').textContent = 'Проверьте права доступа и активное рабочее пространство.';
      $('mpagoCreatePaymentBtn').disabled = true;
      $('mpagoTestConnectionBtn').disabled = true;
      return false;
    }
  };

  const renderPayments = items => {
    const body = $('mpagoPaymentsBody');
    body.replaceChildren();
    if (!Array.isArray(items) || items.length === 0) {
      const row = body.insertRow();
      const cell = row.insertCell();
      cell.colSpan = 5;
      cell.className = 'text-muted';
      cell.textContent = 'В этом рабочем пространстве пока нет платёжных ссылок.';
      return;
    }
    items.forEach(item => {
      const row = body.insertRow();
      const title = row.insertCell();
      title.textContent = String(item.title || 'Платёж');
      const ref = document.createElement('div');
      ref.className = 'small text-muted';
      ref.textContent = String(item.payment_public_id || '');
      title.appendChild(ref);
      row.insertCell().textContent = money(item.amount, item.currency);
      const statusCell = row.insertCell();
      statusCell.textContent = String(item.status || 'unknown');
      if (item.status_detail) {
        const detail = document.createElement('div');
        detail.className = 'small text-muted';
        detail.textContent = String(item.status_detail);
        statusCell.appendChild(detail);
      }
      row.insertCell().textContent = String(item.created_at || '');
      const actions = row.insertCell();
      const link = document.createElement('a');
      link.className = 'btn crm-btn-secondary btn-sm me-1';
      link.textContent = 'Открыть ссылку';
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      if (checkoutUrlAllowed(String(item.checkout_url || ''))) link.href = item.checkout_url;
      else { link.removeAttribute('href'); link.setAttribute('aria-disabled', 'true'); }
      actions.appendChild(link);

      const verify = document.createElement('button');
      verify.type = 'button';
      verify.className = 'btn crm-btn-secondary btn-sm';
      verify.textContent = 'Обновить статус';
      verify.addEventListener('click', async () => {
        verify.disabled = true;
        try {
          const result = await api('/payments/verify', {
            method: 'POST', body: { payment_public_id: item.payment_public_id }
          });
          showMessage('mpagoPaymentMessage', result.verified ? `Подтверждённый статус: ${result.status}` : 'Платёж пока не найден у провайдера. Повторите сверку после оплаты.');
          await loadPayments();
        } catch (_) {
          showMessage('mpagoPaymentMessage', 'Не удалось проверить платёж. Сверьте ID платежа и права доступа.', true);
        } finally { verify.disabled = false; }
      });
      actions.appendChild(verify);
    });
  };

  const loadPayments = async () => {
    const body = $('mpagoPaymentsBody');
    if (!body) return;
    try {
      const data = await api('/payments?limit=50');
      renderPayments(data.items || []);
    } catch (_) {
      body.replaceChildren();
      const row = body.insertRow();
      const cell = row.insertCell();
      cell.colSpan = 5;
      cell.className = 'text-danger';
      cell.textContent = 'Не удалось загрузить платежи. Проверьте право «Просмотр финансов».';
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (!$('mpagoConfigForm')) return;
    $('mpagoConfigForm').addEventListener('submit', async event => {
      event.preventDefault();
      const button = $('mpagoSaveBtn');
      button.disabled = true;
      showMessage('mpagoMessage', 'Сохранение…');
      try {
        await api('/config', {method:'POST', body:{
          access_token: $('mpagoAccessToken').value,
          public_key: $('mpagoPublicKey').value,
          sandbox_mode: $('mpagoSandboxMode').checked
        }});
        $('mpagoAccessToken').value = '';
        connectionVerified = false;
        showMessage('mpagoMessage', 'Настройки сохранены. Проверьте подключение перед созданием ссылки.');
        await loadStatus();
        await loadPayments();
      } catch (_) {
        showMessage('mpagoMessage', 'Не удалось сохранить настройки. Проверьте ввод, права и APP_KEY.', true);
      } finally { button.disabled = false; }
    });
    $('mpagoTestConnectionBtn').addEventListener('click', async event => {
      const button = event.currentTarget;
      button.disabled = true;
      connectionVerified = false;
      $('mpagoCreatePaymentBtn').disabled = true;
      showMessage('mpagoMessage', 'Проверяем токен через Mercado Pago…');
      try {
        await api('/connection/test', {method:'POST', body:{}});
        connectionVerified = true;
        showMessage('mpagoMessage', 'Mercado Pago подтвердил сохранённый токен.');
      } catch (_) {
        showMessage('mpagoMessage', 'Mercado Pago не подтвердил токен. Проверьте режим, токен и доступ к API.', true);
      } finally {
        button.disabled = false;
        await loadStatus();
      }
    });
    try {
      const saved = JSON.parse(sessionStorage.getItem('mp_checkout_retry') || 'null');
      if (saved && typeof saved.fingerprint === 'string' && typeof saved.key === 'string') {
        pendingCheckoutFingerprint = saved.fingerprint;
        pendingCheckoutKey = saved.key;
      }
    } catch (_) {}
    $('mpagoPaymentForm').addEventListener('submit', async event => {
      event.preventDefault();
      const button = $('mpagoCreatePaymentBtn');
      button.disabled = true;
      showMessage('mpagoPaymentMessage', 'Создание ссылки…');
      try {
        const paymentInput = {
          title: $('mpagoPaymentTitle').value.trim(),
          amount: $('mpagoPaymentAmount').value,
          currency_id: 'BRL',
          external_reference: $('mpagoExternalReference').value.trim()
        };
        const data = await api('/payments', {method:'POST', body:{
          ...paymentInput,
          idempotency_key: await checkoutIdempotencyKey(paymentInput)
        }});
        if (!checkoutUrlAllowed(String(data.init_point_url || ''))) throw new Error('Invalid checkout destination');
        $('mpagoCheckoutLink').href = data.init_point_url;
        $('mpagoPaymentReference').textContent = String(data.payment_public_id || '');
        $('mpagoCheckoutResult').hidden = false;
        showMessage('mpagoPaymentMessage', 'Ссылка создана через Mercado Pago.');
        $('mpagoPaymentForm').reset();
        pendingCheckoutKey = '';
        pendingCheckoutFingerprint = '';
        try { sessionStorage.removeItem('mp_checkout_retry'); } catch (_) {}
        await loadPayments();
      } catch (error) {
        showMessage('mpagoPaymentMessage', error?.message || 'Не удалось подтвердить результат. Повторите запрос с тем же ключом; не создавайте новую ссылку до сверки с Mercado Pago.', true);
      } finally { await loadStatus(); }
    });
    $('mpagoReloadPaymentsBtn').addEventListener('click', loadPayments);
    loadStatus().then(loadPayments);
  });
})();
