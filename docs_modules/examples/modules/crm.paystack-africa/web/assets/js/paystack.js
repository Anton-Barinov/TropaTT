(() => {
  'use strict';

  const API = '/api/v1/_module/crm.paystack-africa';
  const $ = id => document.getElementById(id);
  const setText = (id, text) => { const node = $(id); if (node) node.textContent = String(text ?? ''); };
  const setMessage = (id, text, error = false) => {
    const node = $(id);
    if (!node) return;
    node.textContent = text;
    node.className = `small mt-3 mb-0 ${error ? 'text-danger' : 'text-success'}`;
  };
  const api = async (path, options = {}) => {
    if (window.CRM?.api?.request) {
      const result = await window.CRM.api.request(`${API}${path}`, options);
      if (result?.success === false) throw new Error(result.message || 'Request failed');
      return result?.data ?? result;
    }
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) {
      headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    }
    const hasBody = options.body !== undefined && options.body !== null;
    if (hasBody && typeof options.body !== 'string') headers['Content-Type'] = 'application/json';
    const response = await fetch(`${API}${path}`, {
      ...options, method, headers, credentials: 'same-origin',
      body: hasBody ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.success === false) throw new Error(result.message || result.code || 'Request failed');
    return result.data ?? result;
  };

  let pendingReference = '';
  let pendingFingerprint = '';
  const paymentReference = async payload => {
    const canonical = JSON.stringify(payload);
    const bytes = new TextEncoder().encode(canonical);
    const digest = await window.crypto.subtle.digest('SHA-256', bytes);
    const fingerprint = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
    if (pendingReference && pendingFingerprint !== fingerprint) {
      throw new Error('Resolve the existing Paystack attempt before changing payment details.');
    }
    if (!pendingReference) {
      pendingReference = `pstk-${Array.from(window.crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('')}`;
      pendingFingerprint = fingerprint;
      try { sessionStorage.setItem('paystack_checkout_retry', JSON.stringify({fingerprint, reference: pendingReference})); } catch (_) {}
    }
    return pendingReference;
  };
  const clearPendingReference = () => {
    pendingReference = '';
    pendingFingerprint = '';
    try { sessionStorage.removeItem('paystack_checkout_retry'); } catch (_) {}
  };

  const showRecoveryAction = reference => {
    const normalized = typeof reference === 'string' ? reference.trim() : '';
    const container = $('pystkRecoveryActions');
    const button = $('pystkVerifyAttemptBtn');
    if (!container || !button || !/^[A-Za-z0-9.=-]{1,100}$/.test(normalized)) return;
    setText('pystkRecoveryReference', `Reference: ${normalized}`);
    button.dataset.reference = normalized;
    container.hidden = false;
  };
  const hideRecoveryAction = () => {
    const container = $('pystkRecoveryActions');
    const button = $('pystkVerifyAttemptBtn');
    if (container) container.hidden = true;
    if (button) delete button.dataset.reference;
  };

  function renderTransactions(items) {
    const body = $('pystkTransactionsBody');
    if (!body) return;
    body.replaceChildren();
    if (!Array.isArray(items) || items.length === 0) {
      const row = body.insertRow();
      const cell = row.insertCell();
      cell.colSpan = 6;
      cell.className = 'text-muted';
      cell.textContent = 'Транзакций пока нет.';
      return;
    }
    items.forEach(item => {
      const row = body.insertRow();
      const reference = document.createElement('code');
      reference.textContent = item.reference || '—';
      row.insertCell().append(reference);
      row.insertCell().textContent = item.customer_email || '—';
      row.insertCell().textContent = `${item.amount_major ?? '—'} ${item.currency || ''}`.trim();
      row.insertCell().textContent = item.status || '—';
      row.insertCell().textContent = item.created_at || '—';
      const actionCell = row.insertCell();
      if (item.status !== 'success' && typeof item.reference === 'string') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn crm-btn-secondary btn-sm';
        button.textContent = 'Проверить';
        button.addEventListener('click', async () => {
          button.disabled = true;
          try {
            const checked = await api('/transactions/verify', { method: 'POST', body: { reference: item.reference } });
            setMessage('pystkPaymentMessage', `Статус ${item.reference}: ${checked.status}.`);
            await loadTransactions();
          } catch (_) {
            setMessage('pystkPaymentMessage', 'Не удалось проверить платёж. Проверьте подключение и попробуйте позже.', true);
          } finally {
            button.disabled = false;
          }
        });
        actionCell.append(button);
      } else {
        actionCell.textContent = '—';
      }
    });
  }

  async function loadTransactions() {
    const result = await api('/transactions?limit=30');
    renderTransactions(result.items);
  }

  async function loadSettings() {
    const settings = await api('/settings');
    const configured = settings.configured === true;
    const mode = settings.mode === 'live' ? 'Рабочая' : 'Тестовая';
    setText('pystkStatusBadge', configured ? 'Ключи сохранены' : 'Не настроено');
    const badge = $('pystkStatusBadge');
    if (badge) badge.className = `badge ${configured ? 'text-bg-success' : 'text-bg-secondary'}`;
    setText('pystkStatusMeta', configured ? 'Формат ключей проверен; подключение проверяется отдельно.' : 'Ключи не сохранены');
    setText('pystkModeStatus', mode);
    setText('pystkPublicKeyStatus', settings.public_key || 'Не задан');
    setText('pystkSecretStatus', settings.secret_key_configured ? 'Сохранён и зашифрован' : 'Не задан');
    setText('pystkWebhookPath', settings.webhook_path || 'Недоступен');
    const formMode = $('pystkMode');
    if (formMode) formMode.value = settings.mode || 'test';
    const publicInput = $('pystkPublicKey');
    if (publicInput && settings.public_key) publicInput.value = settings.public_key;
    const warning = $('pystkLiveWarning');
    if (warning) warning.hidden = settings.mode !== 'live';
    const createButton = $('pystkCreatePaymentBtn');
    if (createButton) createButton.disabled = !configured;
    if (configured) await loadTransactions();
    else renderTransactions([]);
    return configured;
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!$('pystkConfigForm')) return;
    $('pystkMode')?.addEventListener('change', event => {
      const warning = $('pystkLiveWarning');
      if (warning) warning.hidden = event.target.value !== 'live';
    });
    $('pystkConfigForm').addEventListener('submit', async event => {
      event.preventDefault();
      const button = $('pystkSaveBtn');
      button.disabled = true;
      setMessage('pystkMessage', 'Сохраняем настройки…');
      try {
        await api('/settings', { method: 'POST', body: {
          mode: $('pystkMode').value,
          public_key: $('pystkPublicKey').value.trim(),
          secret_key: $('pystkSecretKey').value
        }});
        $('pystkSecretKey').value = '';
        setMessage('pystkMessage', 'Ключи сохранены в зашифрованном виде. Проверьте подключение отдельно.');
        await loadSettings();
      } catch (_) {
        setMessage('pystkMessage', 'Не удалось сохранить настройки. Проверьте ключи, права доступа и конфигурацию APP_KEY.', true);
      } finally {
        button.disabled = false;
      }
    });
    $('pystkRefreshBtn')?.addEventListener('click', () => loadSettings().catch(() => setMessage('pystkMessage', 'Не удалось загрузить настройки.', true)));
    $('pystkTestConnectionBtn')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      button.disabled = true;
      setMessage('pystkMessage', 'Проверяем соединение с Paystack…');
      try {
        await api('/connection/test', { method: 'POST', body: {} });
        setMessage('pystkMessage', 'Paystack принял сохранённые ключи.');
      } catch (_) {
        setMessage('pystkMessage', 'Paystack не подтвердил ключи. Проверьте режим, ключи и исходящее HTTPS-соединение.', true);
      } finally {
        button.disabled = false;
      }
    });
    try {
      const saved = JSON.parse(sessionStorage.getItem('paystack_checkout_retry') || 'null');
      if (saved && typeof saved.fingerprint === 'string' && typeof saved.reference === 'string') {
        pendingFingerprint = saved.fingerprint;
        pendingReference = saved.reference;
        showRecoveryAction(pendingReference);
        setMessage('pystkPaymentMessage', `Есть незавершённая попытка ${pendingReference}. Проверьте её статус; повторно платёж создаваться не будет.`, true);
      }
    } catch (_) {}
    $('pystkVerifyAttemptBtn')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      const reference = button.dataset.reference || pendingReference;
      if (!reference) {
        setMessage('pystkPaymentMessage', 'Reference незавершённой попытки не найден. Обновите список транзакций.', true);
        return;
      }
      button.disabled = true;
      setMessage('pystkPaymentMessage', `Проверяем существующую попытку ${reference}. Новый платёж не создаётся.`);
      try {
        const checked = await api('/transactions/verify', { method: 'POST', body: { reference } });
        if (checked.status === 'success') {
          setMessage('pystkPaymentMessage', `Paystack подтвердил успешный платёж ${reference}. Не создавайте повторную оплату.`);
          clearPendingReference();
          hideRecoveryAction();
        } else {
          setMessage('pystkPaymentMessage', `Статус ${reference}: ${checked.status}. Paystack не предоставляет URL оплаты через проверку статуса. Не создавайте новый платёж, пока не сверите эту попытку в Paystack.` , true);
        }
        await loadTransactions();
      } catch (_) {
        setMessage('pystkPaymentMessage', `Paystack пока не подтвердил статус ${reference}. Попробуйте проверить позже; новая попытка оплаты не отправлялась.`, true);
      } finally {
        button.disabled = false;
      }
    });
    $('pystkPaymentForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const button = $('pystkCreatePaymentBtn');
      button.disabled = true;
      setMessage('pystkPaymentMessage', 'Создаём платёжную ссылку…');
      if ($('pystkCheckoutLink')) { $('pystkCheckoutLink').replaceChildren(); $('pystkCheckoutLink').hidden = true; }
      try {
        const payload = {
          email: $('pystkCustomerEmail').value.trim(),
          amount: $('pystkAmount').value,
          currency: $('pystkCurrency').value
        };
        const payment = await api('/transactions', { method: 'POST', body: {...payload, reference: await paymentReference(payload)} });
        if (payment.authorization_url) {
          const checkout = new URL(payment.authorization_url);
          if (checkout.protocol !== 'https:' || checkout.hostname !== 'checkout.paystack.com') throw new Error('Invalid checkout host');
          const anchor = document.createElement('a');
          anchor.href = checkout.href;
          anchor.target = '_blank';
          anchor.rel = 'noopener noreferrer';
          anchor.className = 'btn crm-btn-secondary';
          anchor.textContent = 'Открыть оплату Paystack';
          $('pystkCheckoutLink').append(anchor);
          $('pystkCheckoutLink').hidden = false;
          setMessage('pystkPaymentMessage', `Ссылка готова. Референс: ${payment.reference}. Создание ссылки не означает, что платёж оплачен.`);
          clearPendingReference();
          hideRecoveryAction();
        } else {
          const reference = payment.reference || pendingReference;
          setMessage('pystkPaymentMessage', `Paystack подтвердил существующую попытку (${payment.status}), но URL оплаты не восстановлен. Не создавайте новый платёж; проверьте статус этой попытки ниже.`, true);
          showRecoveryAction(reference);
        }
        await loadTransactions();
      } catch (_) {
        setMessage('pystkPaymentMessage', `Результат не подтверждён. Проверьте существующую попытку ${pendingReference || 'по reference из списка транзакций'}; новый платёж не создавайте.`, true);
        showRecoveryAction(pendingReference);
        await loadTransactions().catch(() => {});
      } finally {
        button.disabled = false;
      }
    });
    $('pystkReloadTransactionsBtn')?.addEventListener('click', () => loadTransactions().catch(() => setMessage('pystkPaymentMessage', 'Не удалось загрузить список транзакций.', true)));
    loadSettings().catch(() => {
      setText('pystkStatusBadge', 'Ошибка загрузки');
      setText('pystkStatusMeta', 'Проверьте права доступа и попробуйте обновить страницу.');
      setMessage('pystkMessage', 'Не удалось загрузить состояние Paystack.', true);
    });
  });
})();
