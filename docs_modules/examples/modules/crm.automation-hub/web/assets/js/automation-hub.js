(() => {
  'use strict';

  const api = (path, options = {}) => {
    if (window.CRM?.api?.request) {
      return window.CRM.api.request(path, options).then(envelope => envelope.data || envelope);
    }
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && window.CRM?.api?.getCsrfToken) {
      headers['X-CSRF-Token'] = window.CRM.api.getCsrfToken();
    }
    const hasBody = options.body !== undefined && options.body !== null;
    if (hasBody && typeof options.body !== 'string' && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }
    return fetch(path, {
      ...options,
      method,
      credentials: 'same-origin',
      headers,
      body: hasBody ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined,
    }).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) throw new Error(data.message || data.code || 'Ошибка API');
      return data.data || data;
    });
  };

  const $ = id => document.getElementById(id);
  const showMessage = (text, isError = false) => {
    const el = $('athubMessage');
    if (!el) return;
    el.textContent = text;
    el.className = 'small mt-3 ' + (isError ? 'text-danger' : 'text-success');
  };

  const renderRecipes = recipes => {
    const list = $('athubRecipesList');
    if (!list) return;
    list.replaceChildren();
    if (!Array.isArray(recipes) || recipes.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'text-muted';
      empty.textContent = 'В этом рабочем пространстве пока нет подключений.';
      list.append(empty);
      return;
    }

    recipes.forEach(recipe => {
      const card = document.createElement('article');
      card.className = 'crm-card p-3';
      const row = document.createElement('div');
      row.className = 'd-flex flex-wrap justify-content-between align-items-center gap-3';
      const details = document.createElement('div');
      details.className = 'min-w-0';
      const heading = document.createElement('h3');
      heading.className = 'h6 mb-1';
      heading.textContent = String(recipe.title || 'Подключение');
      const meta = document.createElement('div');
      meta.className = 'small text-muted text-break';
      meta.textContent = `${String(recipe.platform || '')} · ${String(recipe.recipe_key || '')} · ${String(recipe.webhook_endpoint_host || '')}`;
      const state = document.createElement('div');
      state.className = 'small ' + (Number(recipe.is_active) === 1 ? 'text-success' : 'text-muted');
      state.textContent = Number(recipe.is_active) === 1 ? 'Активно' : 'Отключено';
      details.append(heading, meta, state);

      const test = document.createElement('button');
      test.type = 'button';
      test.className = 'btn crm-btn-secondary';
      test.textContent = 'Отправить тестовый ping';
      test.disabled = Number(recipe.is_active) !== 1;
      test.setAttribute('aria-label', `Отправить тестовый ping: ${String(recipe.title || '')}`);
      test.addEventListener('click', async () => {
        test.disabled = true;
        test.textContent = 'Отправка…';
        try {
          const result = await api('_module/crm.automation-hub/test-webhook', {
            method: 'POST',
            body: { recipe_public_id: recipe.public_id },
          });
          test.textContent = result?.ok ? `Доставлено · HTTP ${Number(result.http_code) || 0}` : `Не доставлено · HTTP ${Number(result?.http_code) || 0}`;
          test.classList.toggle('text-danger', !result?.ok);
          test.classList.toggle('text-success', Boolean(result?.ok));
        } catch (error) {
          test.textContent = 'Ошибка · повторить';
          showMessage(error instanceof Error ? error.message : 'Тестовый ping не выполнен.', true);
        } finally {
          test.disabled = false;
        }
      });

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = Number(recipe.is_active) === 1 ? 'btn crm-btn-secondary' : 'btn crm-btn-primary';
      toggle.textContent = Number(recipe.is_active) === 1 ? 'Приостановить' : 'Возобновить';
      toggle.setAttribute('aria-label', `${Number(recipe.is_active) === 1 ? 'Приостановить' : 'Возобновить'} подключение: ${String(recipe.title || '')}`);
      toggle.addEventListener('click', async () => {
        toggle.disabled = true;
        try {
          await api('_module/crm.automation-hub/recipes/active', {
            method: 'POST',
            body: { recipe_public_id: recipe.public_id, active: Number(recipe.is_active) !== 1 },
          });
          showMessage(Number(recipe.is_active) === 1 ? 'Подключение приостановлено; исходящие события больше не отправляются.' : 'Подключение возобновлено.');
          await loadRecipes();
        } catch (error) {
          showMessage(error instanceof Error ? error.message : 'Не удалось изменить состояние подключения.', true);
        } finally {
          toggle.disabled = false;
        }
      });

      const actions = document.createElement('div');
      actions.className = 'd-flex flex-wrap gap-2';
      actions.append(toggle, test);
      row.append(details, actions);
      card.append(row);
      list.append(card);
    });
  };

  let loading = false;
  const loadRecipes = async () => {
    if (loading) return;
    loading = true;
    const refresh = $('athubRefreshBtn');
    const status = $('athubStatusMeta');
    if (refresh) refresh.disabled = true;
    if (status) status.textContent = 'Загрузка подключений…';
    try {
      const result = await api('_module/crm.automation-hub/recipes');
      renderRecipes(result.recipes || []);
      if (status) status.textContent = 'Список подключений обновлён.';
    } catch (error) {
      if ($('athubRecipesList')) {
        $('athubRecipesList').replaceChildren();
        const message = document.createElement('div');
        message.className = 'alert alert-danger mb-0';
        message.setAttribute('role', 'alert');
        message.textContent = error instanceof Error ? error.message : 'Не удалось загрузить список подключений.';
        $('athubRecipesList').append(message);
      }
      if (status) status.textContent = 'Список не загружен. Проверьте доступ и повторите попытку.';
    } finally {
      loading = false;
      if (refresh) refresh.disabled = false;
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    const form = $('athubConfigForm');
    if (!form) return;

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('athubSaveBtn');
      if (submit?.disabled) return;
      const values = new FormData(form);
      const payload = Object.fromEntries(values.entries());
      if (submit) {
        submit.disabled = true;
        submit.textContent = 'Сохранение…';
      }
      showMessage('Сохраняю подключение…');
      try {
        await api('_module/crm.automation-hub/recipes', { method: 'POST', body: payload });
        form.reset();
        showMessage('Подключение сохранено. Секрет сохранён и больше не отображается.');
        await loadRecipes();
      } catch (error) {
        showMessage(error instanceof Error ? error.message : 'Не удалось сохранить подключение.', true);
      } finally {
        if (submit) {
          submit.disabled = false;
          submit.textContent = 'Сохранить подключение';
        }
      }
    });
    $('athubRefreshBtn')?.addEventListener('click', loadRecipes);
    loadRecipes();
  });
})();
