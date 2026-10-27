(() => {
  'use strict';

  const prefix = '_module/crm.public-forms';
  const MAX_FIELDS = 40;
  const $ = id => document.getElementById(id);
  const api = async (path, options = {}) => {
    if (!window.CRM?.api?.request) throw new Error('API is unavailable');
    const envelope = await window.CRM.api.request(prefix + path, options);
    if (envelope?.success === false) {
      const error = new Error(envelope.message || envelope.code || 'Ошибка API');
      error.code = String(envelope.code || '');
      throw error;
    }
    return envelope?.data || envelope || {};
  };
  const message = (text, error = false, target = 'pformMessage') => {
    const node = $(target);
    if (!node) return;
    node.textContent = text;
    node.className = 'small mt-3 ' + (error ? 'text-danger' : 'text-success');
  };
  const textCell = (row, value, className = '') => {
    const cell = row.insertCell();
    if (className) cell.className = className;
    cell.textContent = String(value ?? '');
    return cell;
  };
  const makeButton = (label, action, className = 'btn crm-btn-secondary btn-sm') => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.dataset.action = action;
    button.textContent = label;
    return button;
  };
  const emptyRow = (tbody, span, text) => {
    tbody.replaceChildren();
    const row = tbody.insertRow();
    const cell = row.insertCell();
    cell.colSpan = span;
    cell.className = 'text-muted';
    cell.textContent = text;
  };

  const updateFieldLimit = () => {
    const button = $('pformAddFieldBtn');
    const count = $('pformFields')?.children.length || 0;
    if (button) {
      button.disabled = count >= MAX_FIELDS;
      button.title = count >= MAX_FIELDS ? `Максимум полей: ${MAX_FIELDS}` : '';
    }
  };
  const addField = (field = {}) => {
    const container = $('pformFields');
    if (!container) return false;
    if (container.children.length >= MAX_FIELDS) {
      message(`В форме можно создать не более ${MAX_FIELDS} полей.`, true, 'pformEditorMessage');
      updateFieldLimit();
      return false;
    }
    const row = document.createElement('div');
    row.className = 'pform-field-row';
    row.dataset.fieldRow = '1';
    const nameWrap = document.createElement('div');
    const nameLabel = document.createElement('label');
    nameLabel.className = 'form-label';
    nameLabel.textContent = 'Системное имя';
    const name = document.createElement('input');
    name.className = 'form-control';
    name.maxLength = 64;
    name.pattern = '[A-Za-z][A-Za-z0-9_-]{0,63}';
    name.required = true;
    name.value = String(field.name || 'field_' + (container.children.length + 1));
    nameLabel.appendChild(name);
    nameWrap.appendChild(nameLabel);

    const labelWrap = document.createElement('div');
    const label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = 'Подпись';
    const labelInput = document.createElement('input');
    labelInput.className = 'form-control';
    labelInput.maxLength = 120;
    labelInput.required = true;
    labelInput.value = String(field.label || 'Новое поле');
    label.appendChild(labelInput);
    labelWrap.appendChild(label);

    const typeWrap = document.createElement('div');
    const typeLabel = document.createElement('label');
    typeLabel.className = 'form-label';
    typeLabel.textContent = 'Тип';
    const type = document.createElement('select');
    type.className = 'form-select';
    [['text', 'Текст'], ['email', 'Электронная почта'], ['tel', 'Телефон'], ['number', 'Число'], ['date', 'Дата'], ['url', 'Ссылка'], ['textarea', 'Длинный текст']].forEach(([value, caption]) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = caption;
      type.appendChild(option);
    });
    type.value = String(field.type || 'text');
    typeLabel.appendChild(type);
    typeWrap.appendChild(typeLabel);

    const requiredWrap = document.createElement('label');
    requiredWrap.className = 'form-check pform-required-field';
    const required = document.createElement('input');
    required.type = 'checkbox';
    required.className = 'form-check-input';
    required.checked = field.required === true;
    const requiredText = document.createElement('span');
    requiredText.className = 'form-check-label';
    requiredText.textContent = 'Обязательно';
    requiredWrap.append(required, requiredText);

    const remove = makeButton('Удалить', 'remove-field', 'btn crm-btn-danger-soft btn-sm');
    remove.setAttribute('aria-label', 'Удалить поле формы');
    row.append(nameWrap, labelWrap, typeWrap, requiredWrap, remove);
    row._pformControls = {name, label: labelInput, type, required};
    container.appendChild(row);
    updateFieldLimit();
    return true;
  };

  const collectFields = () => Array.from(document.querySelectorAll('[data-field-row]')).map(row => ({
    name: row._pformControls.name.value.trim(),
    label: row._pformControls.label.value.trim(),
    type: row._pformControls.type.value,
    required: row._pformControls.required.checked
  }));

  const makeSlug = value => {
    const translit = {а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'e',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'kh',ц:'ts',ч:'ch',ш:'sh',щ:'shch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya'};
    const text = String(value || '').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[а-яё]/g, character => translit[character] || '');
    return text.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 64);
  };

  const applySchedule = schedule => {
    for (let day=1; day<=7; day++) {
      const interval = Array.isArray(schedule?.[day]) ? schedule[day][0] : null;
      const enabled = document.querySelector(`[data-schedule-enabled="${day}"]`);
      const start = document.querySelector(`[data-schedule-start="${day}"]`);
      const end = document.querySelector(`[data-schedule-end="${day}"]`);
      if (enabled) enabled.checked = Boolean(interval);
      if (start) start.value = String(interval?.start || '09:00');
      if (end) end.value = String(interval?.end || '17:00');
    }
  };
  const collectSchedule = () => {
    const schedule = {};
    for (let day=1; day<=7; day++) {
      if (!document.querySelector(`[data-schedule-enabled="${day}"]`)?.checked) continue;
      schedule[day] = [{
        start: document.querySelector(`[data-schedule-start="${day}"]`)?.value || '',
        end: document.querySelector(`[data-schedule-end="${day}"]`)?.value || ''
      }];
    }
    return schedule;
  };
  const loadCalendarUsers = async selectedId => {
    const select = $('pformCalendarUser');
    if (!select) return;
    const data = await api('/manage/calendar-users');
    select.replaceChildren(new Option('Выберите участника', ''));
    (Array.isArray(data.items) ? data.items : []).forEach(user => {
      const option = new Option(String(user.full_name || 'Участник'), String(user.public_id || ''));
      select.add(option);
    });
    select.value = String(selectedId || '');
  };
  const loadRoutingOptions = async (selectedProject = '', selectedAssignee = '') => {
    const projectSelect = $('pformTargetProject');
    const assigneeSelect = $('pformTargetAssignee');
    if (!projectSelect || !assigneeSelect) return;
    const data = await api('/manage/routing-options');
    projectSelect.replaceChildren(new Option('Не связывать с проектом', ''));
    assigneeSelect.replaceChildren(new Option('Не назначать', ''));
    (Array.isArray(data.projects) ? data.projects : []).forEach(project => {
      projectSelect.add(new Option(String(project.title || 'Проект'), String(project.public_id || '')));
    });
    (Array.isArray(data.assignees) ? data.assignees : []).forEach(user => {
      assigneeSelect.add(new Option(String(user.full_name || 'Участник'), String(user.public_id || '')));
    });
    projectSelect.value = String(selectedProject || '');
    assigneeSelect.value = String(selectedAssignee || '');
  };

  const editor = $('pformEditor');
  const openEditor = async (publicId = '') => {
    let selectedProject = '';
    let selectedAssignee = '';
    $('pformEditorForm').reset();
    $('pformPublicId').value = publicId;
    $('pformSlug').dataset.manual = publicId ? '1' : '0';
    $('pformEditorTitle').textContent = publicId ? 'Редактировать форму' : 'Новая форма';
    $('pformSaveBtn').textContent = publicId ? 'Сохранить изменения' : 'Сохранить черновик';
    $('pformFields').replaceChildren();
    $('pformConsent').value = 'Я согласен на обработку персональных данных.';
    message('', false, 'pformEditorMessage');
    if (publicId) {
      try {
        const data = await api('/manage/forms/' + encodeURIComponent(publicId));
        const form = data.form || {};
        $('pformTitle').value = String(form.title || '');
        $('pformDescription').value = String(form.description || '');
        $('pformSlug').value = String(form.slug || '');
        $('pformConsent').value = String(form.consent_text || '');
        $('pformType').value = String(form.form_type || 'intake');
        selectedProject = String(form.target_project_public_id || '');
        selectedAssignee = String(form.target_assignee_user_public_id || '');
        $('pformCalendarUser').value = String(form.calendar_user_public_id || '');
        $('pformTimezone').value = String(form.booking_timezone || 'UTC');
        $('pformSlotDuration').value = String(form.slot_duration_minutes || 30);
        $('pformBuffer').value = String(form.buffer_minutes ?? 15);
        applySchedule(form.booking_schedule || {});
        (Array.isArray(form.fields_schema) ? form.fields_schema : []).forEach(addField);
      } catch (_) {
        message('Не удалось загрузить форму для редактирования.', true, 'pformMessage');
        return;
      }
    } else {
      $('pformType').value = 'intake';
      $('pformCalendarUser').value = '';
      $('pformTimezone').value = 'Europe/Moscow';
      $('pformSlotDuration').value = '30';
      $('pformBuffer').value = '15';
      applySchedule({1:[{start:'09:00',end:'17:00'}],2:[{start:'09:00',end:'17:00'}],3:[{start:'09:00',end:'17:00'}],4:[{start:'09:00',end:'17:00'}],5:[{start:'09:00',end:'17:00'}]});
      $('pformTitle').value = '';
      $('pformDescription').value = '';
      $('pformSlug').value = 'contact-' + Math.random().toString(36).slice(2, 8);
      addField({name: 'email', label: 'Электронная почта', type: 'email', required: true});
    }
    $('pformBookingConfig').hidden = $('pformType').value !== 'booking';
    $('pformRoutingConfig').hidden = $('pformType').value !== 'intake';
    try {
      await Promise.all([loadCalendarUsers($('pformCalendarUser').value), loadRoutingOptions(selectedProject, selectedAssignee)]);
    } catch (_) { message('Не удалось загрузить участников или проекты рабочего пространства.', true, 'pformEditorMessage'); return; }
    editor.showModal();
    $('pformTitle').focus();
  };

  const loadForms = async () => {
    const tbody = $('pformRows');
    if (!tbody) return;
    try {
      const data = await api('/manage/forms');
      tbody.replaceChildren();
      const forms = Array.isArray(data.items) ? data.items : [];
      if (!forms.length) return emptyRow(tbody, 5, 'Форм пока нет. Создайте черновик первой формы.');
      forms.forEach(form => {
        const row = tbody.insertRow();
        row.dataset.publicId = String(form.public_id || '');
        const title = row.insertCell();
        const strong = document.createElement('strong');
        strong.textContent = String(form.title || 'Без названия');
        const slug = document.createElement('div');
        slug.className = 'small text-muted';
        slug.textContent = String(form.slug || '');
        title.append(strong, slug);
        const state = textCell(row, form.is_published ? 'Опубликована' : 'Черновик');
        state.className = form.is_published ? 'text-success fw-semibold' : 'text-muted';
        textCell(row, form.submission_count || 0);
        textCell(row, form.updated_at || form.created_at || '');
        const actions = row.insertCell();
        actions.className = 'text-end';
        actions.classList.add('pform-row-actions');
        const edit = makeButton('Изменить', 'edit-form');
        const publish = makeButton(form.is_published ? 'Снять с публикации' : 'Опубликовать', 'toggle-publish');
        actions.append(edit, document.createTextNode(' '), publish);
        if (form.is_published) {
          actions.append(document.createTextNode(' '), makeButton('Копировать ссылку', 'copy-link'));
        }
      });
    } catch (_) {
      emptyRow(tbody, 5, 'Не удалось загрузить формы. Проверьте доступ к просмотру настроек рабочего пространства.');
    }
  };

  const loadSubmissions = async () => {
    const tbody = $('pformSubmissionRows');
    if (!tbody) return;
    try {
      const data = await api('/manage/submissions?limit=50');
      tbody.replaceChildren();
      const items = Array.isArray(data.items) ? data.items : [];
      if (!items.length) return emptyRow(tbody, 3, 'Ответов пока нет.');
      items.forEach(item => {
        const row = tbody.insertRow();
        textCell(row, item.form_title || 'Форма');
        const answerCell = row.insertCell();
        const list = document.createElement('dl');
        list.className = 'pform-answer-list mb-0';
        Object.entries(item.data || {}).forEach(([key, value]) => {
          const term = document.createElement('dt');
          term.textContent = String(item.field_labels?.[key] || key);
          const description = document.createElement('dd');
          description.textContent = String(value ?? '');
          list.append(term, description);
        });
        answerCell.appendChild(list);
        textCell(row, item.created_at || '');
      });
    } catch (error) {
      const forbidden = error?.code === 'FORBIDDEN' || error?.code === 'PERMISSION_DENIED';
      $('pformPermissionHint').hidden = !forbidden;
      emptyRow(tbody, 3, forbidden
        ? 'Ответы скрыты: требуется право «Управление клиентами». '
        : 'Не удалось загрузить ответы. Проверьте соединение и повторите попытку.');
    }
  };

  const safePublicUrl = slug => {
    const url = new URL(window.location.pathname, window.location.origin);
    url.searchParams.set('route', 'public-form');
    url.searchParams.set('slug', slug);
    return url.toString();
  };

  const publicApiUrl = route => {
    const apiBase = String(window.CRM?.config?.apiBaseUrl || '/api/index.php');
    const url = new URL(apiBase, window.location.origin);
    url.searchParams.set('route', route);
    return url.toString();
  };

  const initManager = () => {
    if (!$('pformRows')) return;
    $('pformCreateBtn')?.addEventListener('click', () => openEditor());
    $('pformRefreshBtn')?.addEventListener('click', loadForms);
    $('pformRefreshSubmissionsBtn')?.addEventListener('click', loadSubmissions);
    $('pformAddFieldBtn')?.addEventListener('click', () => addField());
    $('pformType')?.addEventListener('change', () => {
      $('pformBookingConfig').hidden = $('pformType').value !== 'booking';
      $('pformRoutingConfig').hidden = $('pformType').value !== 'intake';
    });
    $('pformCloseBtn')?.addEventListener('click', () => editor.close());
    $('pformCancelBtn')?.addEventListener('click', () => editor.close());
    $('pformFields')?.addEventListener('click', event => {
      const button = event.target.closest('[data-action="remove-field"]');
      if (button) {
        button.closest('[data-field-row]')?.remove();
        updateFieldLimit();
      }
    });
    $('pformTitle')?.addEventListener('input', event => {
      if ($('pformPublicId').value) return;
      const slug = $('pformSlug');
      if (slug.dataset.manual !== '1') slug.value = makeSlug(event.target.value) || 'contact-form-' + Math.random().toString(36).slice(2, 6);
    });
    $('pformSlug')?.addEventListener('input', event => { event.target.dataset.manual = '1'; });
    $('pformEditorForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const publicId = $('pformPublicId').value;
      const fields = collectFields();
      const bookingSchedule = collectSchedule();
      if (!fields.length || !fields.every(field => field.name && field.label)) {
        return message('Добавьте хотя бы одно поле и заполните его имя и подпись.', true, 'pformEditorMessage');
      }
      const button = $('pformSaveBtn');
      button.disabled = true;
      try {
        await api(publicId ? '/manage/forms/' + encodeURIComponent(publicId) : '/manage/forms', {
          method: publicId ? 'PATCH' : 'POST',
          body: {
            title: $('pformTitle').value.trim(),
            description: $('pformDescription').value.trim(),
            slug: $('pformSlug').value.trim(),
            form_type: $('pformType').value,
            fields_schema: fields,
            consent_text: $('pformConsent').value.trim(),
            target_project_public_id: $('pformType').value === 'intake' ? ($('pformTargetProject').value.trim() || null) : null,
            target_assignee_user_public_id: $('pformType').value === 'intake' ? ($('pformTargetAssignee').value.trim() || null) : null,
            calendar_user_public_id: $('pformType').value === 'booking' ? ($('pformCalendarUser').value.trim() || null) : null,
            slot_duration_minutes: Number($('pformSlotDuration').value),
            buffer_minutes: Number($('pformBuffer').value),
            booking_timezone: $('pformTimezone').value.trim(),
            booking_schedule: bookingSchedule
          }
        });
        editor.close();
        message(publicId ? 'Изменения формы сохранены.' : 'Черновик создан. Проверьте форму и опубликуйте её.');
        await loadForms();
      } catch (_) {
        message('Не удалось сохранить. Проверьте адрес, обязательные поля и права доступа.', true, 'pformEditorMessage');
      } finally { button.disabled = false; }
    });
    $('pformRows')?.addEventListener('click', async event => {
      const button = event.target.closest('button[data-action]');
      const row = button?.closest('tr[data-public-id]');
      if (!button || !row) return;
      const id = row.dataset.publicId;
      button.disabled = true;
      try {
        if (button.dataset.action === 'edit-form') return await openEditor(id);
        if (button.dataset.action === 'toggle-publish') {
          const publish = button.textContent.trim() !== 'Снять с публикации';
          await api('/manage/forms/' + encodeURIComponent(id) + '/publish', {method: 'POST', body: {is_published: publish}});
          message(publish ? 'Форма опубликована.' : 'Форма снята с публикации.');
          await loadForms();
        }
        if (button.dataset.action === 'copy-link') {
          const slug = row.querySelector('.small')?.textContent || '';
          const url = safePublicUrl(slug);
          if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(url);
          else window.prompt('Скопируйте публичную ссылку:', url);
          message('Публичная ссылка скопирована.');
        }
      } catch (_) {
        message('Действие не выполнено. Проверьте права и попробуйте ещё раз.', true);
      } finally { button.disabled = false; }
    });
    loadForms();
    loadSubmissions();
  };

  const makeIdempotencyKey = () => {
    if (window.crypto?.getRandomValues) {
      const bytes = new Uint8Array(18);
      window.crypto.getRandomValues(bytes);
      return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }
    return 'public-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 16);
  };

  const initPublicForm = async () => {
    const body = document.body;
    const slug = body.dataset.formSlug || '';
    const state = $('publicFormState');
    if (!state || !slug) return;
    const endpoint = publicApiUrl(prefix + '/forms/' + encodeURIComponent(slug));
    try {
      const response = await fetch(endpoint, {method: 'GET', headers: {Accept: 'application/json'}, credentials: 'omit'});
      const envelope = await response.json();
      const data = envelope?.data || envelope || {};
      const form = data.form;
      if (!response.ok || !form) throw new Error('not found');
      $('publicFormHeading').textContent = String(form.title || 'Форма обращения');
      const booking = form.form_type === 'booking';
      $('publicFormBooking').hidden = !booking;
      if (booking) {
        const dateInput = $('publicBookingDate');
        const slotSelect = $('publicBookingSlot');
        const slotStatus = $('publicBookingSlotStatus');
        dateInput.required = true;
        slotSelect.required = true;
        const timezone = String(form.booking_timezone || 'UTC');
        const now = new Date();
        const localToday = new Intl.DateTimeFormat('en-CA', {timeZone: timezone, year:'numeric', month:'2-digit', day:'2-digit'}).format(now);
        dateInput.min = localToday;
        dateInput.value = localToday;
        let slotRequestId = 0;
        const loadSlots = async () => {
          const requestId = ++slotRequestId;
          slotSelect.disabled = true;
          slotSelect.replaceChildren(new Option('Загрузка времени…', ''));
          if (slotStatus) slotStatus.textContent = 'Загружаем доступное время…';
          try {
            const slotUrl = publicApiUrl(prefix + '/forms/' + encodeURIComponent(slug) + '/slots');
            const query = new URL(slotUrl); query.searchParams.set('from', dateInput.value); query.searchParams.set('to', dateInput.value);
            const slotResponse = await fetch(query, {headers:{Accept:'application/json'}, credentials:'omit'});
            const slotEnvelope = await slotResponse.json(); const slotData = slotEnvelope?.data || slotEnvelope || {};
            if (!slotResponse.ok) throw new Error('slots unavailable');
            if (requestId !== slotRequestId) return;
            const items = Array.isArray(slotData.items) ? slotData.items : [];
            slotSelect.replaceChildren(new Option(items.length ? 'Выберите время' : 'Свободного времени нет', ''));
            items.forEach(slot => {
              const start = new Date(String(slot.starts_at));
              if (Number.isNaN(start.getTime())) return;
              const label = new Intl.DateTimeFormat(undefined, {timeZone: timezone, dateStyle:'medium', timeStyle:'short'}).format(start);
              slotSelect.add(new Option(label, String(slot.starts_at)));
            });
            slotSelect.disabled = items.length === 0;
            if (slotStatus) slotStatus.textContent = items.length ? 'Время показано в часовом поясе формы.' : 'На эту дату свободных интервалов нет. Выберите другую дату.';
          } catch (_) {
            if (requestId !== slotRequestId) return;
            slotSelect.replaceChildren(new Option('Не удалось загрузить время', ''));
            slotSelect.disabled = true;
            if (slotStatus) slotStatus.textContent = 'Не удалось загрузить доступное время. Повторите попытку позже.';
          }
        };
        dateInput.addEventListener('change', loadSlots);
        await loadSlots();
      } else {
        $('publicBookingDate').required = false;
        $('publicBookingSlot').required = false;
        $('publicBookingSlot').disabled = false;
      }
      $('publicFormDescription').textContent = String(form.description || '');
      $('publicFormDescription').hidden = !form.description;
      $('publicFormConsentLabel').textContent = String(form.consent_text || 'Я согласен на обработку персональных данных.');
      const fieldsRoot = $('publicFormFields');
      const allowedTypes = ['text', 'email', 'tel', 'number', 'date', 'url', 'textarea'];
      (Array.isArray(form.fields_schema) ? form.fields_schema : []).forEach(field => {
        const name = String(field.name || '');
        if (!/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(name) || ['consent_agreed', '_hp_website', '_t_token', '_idempotency_key'].includes(name)) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-3';
        const label = document.createElement('label');
        label.className = 'form-label';
        label.htmlFor = 'public-field-' + name;
        label.textContent = String(field.label || name) + (field.required ? ' *' : '');
        const type = allowedTypes.includes(String(field.type)) ? String(field.type) : 'text';
        const control = type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
        if (type !== 'textarea') control.type = type;
        control.className = 'form-control';
        control.id = 'public-field-' + name;
        control.name = name;
        control.maxLength = 4096;
        control.required = field.required === true;
        wrapper.append(label, control);
        fieldsRoot.appendChild(wrapper);
      });
      if (!fieldsRoot.children.length) throw new Error('invalid schema');
      $('publicFormContent').hidden = false;
      state.hidden = true;
      let idempotencyKey = makeIdempotencyKey();
      $('publicFormSubmission').addEventListener('input', event => {
        if (event.target.name !== '_hp_website') idempotencyKey = makeIdempotencyKey();
      });
      $('publicFormSubmission').addEventListener('submit', async event => {
        event.preventDefault();
        const formElement = event.currentTarget;
        if (!formElement.reportValidity()) return;
        const button = $('publicFormSubmitBtn');
        button.disabled = true;
        const result = $('publicFormResult');
        result.className = 'mt-3 text-muted';
        result.textContent = 'Отправляем обращение…';
        const payload = Object.fromEntries(new FormData(formElement).entries());
        payload.consent_agreed = $('publicFormConsent').checked ? '1' : '';
        try {
          const submitUrl = publicApiUrl(prefix + '/forms/' + encodeURIComponent(slug) + '/submit');
          const submit = await fetch(submitUrl, {
            method: 'POST', credentials: 'omit',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': idempotencyKey},
            body: JSON.stringify(payload)
          });
          const responseData = await submit.json().catch(() => ({}));
          if (!submit.ok || responseData?.success === false) throw new Error(responseData?.message || 'Не удалось отправить форму.');
          result.className = 'alert alert-success mt-3';
          result.textContent = form.form_type === 'booking' ? 'Спасибо. Встреча добавлена в календарь.' : 'Спасибо. Ваше обращение получено.';
          formElement.hidden = true;
        } catch (_) {
          result.className = 'alert alert-danger mt-3';
          result.textContent = 'Не удалось подтвердить отправку. Не меняйте данные и выбранное время и повторите попытку, чтобы не создать дубликат.';
          button.disabled = false;
        }
      });
    } catch (_) {
      state.className = 'alert alert-warning mt-4';
      state.textContent = 'Эта форма не найдена или пока не опубликована.';
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.page === 'public-form') initPublicForm();
    else initManager();
  });
})();
