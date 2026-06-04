window.CRM = window.CRM || {};
window.CRM.modals = (function () {
  function ensureProjectQuickPreviewDrawer() {
    if (document.getElementById('projectQuickPreviewDrawer')) return;
    document.body.insertAdjacentHTML('beforeend', '\
<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="projectQuickPreviewDrawer" aria-labelledby="projectQuickPreviewDrawerTitle">\
  <div class="offcanvas-header"><h5 id="projectQuickPreviewDrawerTitle">Быстрый обзор проекта</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button></div>\
  <div class="offcanvas-body"><p class="text-muted mb-0">Данные проекта загружаются...</p></div>\
</div>');
  }

  function buildOverlays() {
    return '\
<div class="toast-container position-fixed top-0 end-0 p-3 crm-toast-container">\
  <div id="toastSuccess" class="toast align-items-center crm-toast is-success" role="status" aria-live="polite" aria-atomic="true">\
    <div class="d-flex">\
      <div class="toast-body">Изменения успешно сохранены</div>\
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Закрыть"></button>\
    </div>\
  </div>\
</div>\
\
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">Создать задачу</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>\
    <form id="createTaskForm">\
      <div class="modal-body"><div class="row g-3">\
        <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" placeholder="Например: Подготовить презентацию Q2"></div>\
        <div class="col-md-4"><label class="form-label">Проект</label><select class="form-select" name="project_public_id"><option value="">Без проекта</option></select></div>\
        <div class="col-md-4"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="new">Новая</option></select></div>\
        <div class="col-md-4"><label class="form-label">Приоритет</label><select class="form-select" name="priority"><option value="normal">Нормальный</option><option value="low">Низкий</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select></div>\
        <div class="col-md-4"><label class="form-label">Исполнитель</label><select class="form-select" name="assignee_user_public_id"><option value="">Не назначен</option></select></div>\
        <div class="col-md-4"><label class="form-label">Начало</label><input class="form-control" name="start_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">Дедлайн</label><input class="form-control" name="due_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">Плановое завершение</label><input class="form-control" name="end_at" type="date"></div>\
        <div class="col-12"><label class="form-label">Теги</label><select class="form-select" name="tag_public_ids" multiple size="5"></select><div class="form-text">Можно выбрать несколько тегов.</div></div>\
        <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="5" placeholder="Контекст, шаги, риски, критерии готовности"></textarea></div>\
      </div></div>\
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">Создать проект</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>\
    <form id="createProjectForm" novalidate>\
      <div class="modal-body"><div class="row g-3">\
        <div class="col-md-8"><label class="form-label">Название проекта</label><input class="form-control" name="title" maxlength="255" placeholder="Запуск клиентского портала"></div>\
        <div class="col-md-4"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="active">Активный</option><option value="new">К выполнению</option><option value="in_progress">В работе</option><option value="blocked">Блокирован</option><option value="done">Завершен</option></select></div>\
        <div class="col-md-6"><label class="form-label">Клиент</label><select class="form-select" name="client_public_id"><option value="">Без клиента</option></select></div>\
        <div class="col-md-6"><label class="form-label">Команда</label><select class="form-select" name="team_public_id"><option value="">Команда не назначена</option></select></div>\
        <div class="col-md-6"><label class="form-label">Менеджер проекта</label><select class="form-select" name="manager_user_public_id"><option value="">Без менеджера</option></select></div>\
        <div class="col-md-6"><label class="form-label">Приоритет</label><select class="form-select" name="priority"><option value="normal">Нормальный</option><option value="low">Низкий</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select></div>\
        <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="4" placeholder="Цели, контекст и основные ожидания по проекту"></textarea></div>\
        <div class="col-12"><div class="form-text" data-project-create-hint>Проект будет создан сразу в рабочей модели API, включая клиента, команду и менеджера.</div></div>\
      </div></div>\
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Создать</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">Редактировать задачу</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>\
    <form id="editTaskForm">\
      <div class="modal-body"><div class="row g-3">\
        <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" placeholder="Например: Подготовить презентацию Q2"></div>\
        <div class="col-md-4"><label class="form-label">Проект</label><select class="form-select" name="project_public_id"><option value="">Без проекта</option></select></div>\
        <div class="col-md-4"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="new">Новая</option></select></div>\
        <div class="col-md-4"><label class="form-label">Приоритет</label><select class="form-select" name="priority"><option value="normal">Нормальный</option><option value="low">Низкий</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select></div>\
        <div class="col-md-4"><label class="form-label">Исполнитель</label><select class="form-select" name="assignee_user_public_id"><option value="">Не назначен</option></select></div>\
        <div class="col-md-4"><label class="form-label">Начало</label><input class="form-control" name="start_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">Дедлайн</label><input class="form-control" name="due_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">Плановое завершение</label><input class="form-control" name="end_at" type="date"></div>\
        <div class="col-12"><label class="form-label">Теги</label><select class="form-select" name="tag_public_ids" multiple size="5"></select><div class="form-text">Можно выбрать несколько тегов.</div></div>\
        <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="5" placeholder="Контекст, шаги, риски, критерии готовности"></textarea></div>\
      </div></div>\
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Сохранить</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="modal fade" id="assignUserModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">Назначить исполнителя</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>\
    <form id="assignUserForm">\
      <div class="modal-body">\
        <div class="mb-3"><label class="form-label">Задача</label><select class="form-select" name="task_public_id"><option value="">Нет доступных задач</option></select></div>\
        <div class="mb-2"><label class="form-label">Сотрудник</label><select class="form-select" name="assignee_user_public_id"><option value="">Не назначать</option></select></div>\
        <div class="small text-muted" data-assign-user-hint>Выберите задачу и нового исполнителя.</div>\
      </div>\
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn crm-btn-primary">Назначить</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title" id="deleteConfirmTitle">Подтвердите действие</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>\
    <div class="modal-body">\
      <div class="crm-alert crm-alert--warning mb-0" id="deleteConfirmBody">Действие необратимо. Проверьте список объектов перед удалением.</div>\
    </div>\
    <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-danger-soft" type="button" id="deleteConfirmSubmitBtn">Удалить</button></div>\
  </div></div>\
</div>\
\
<div class="modal fade" id="calendarEventModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">Создать событие</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>\
    <form id="calendarEventForm" novalidate>\
      <div class="modal-body"><div class="row g-3">\
        <input type="hidden" name="task_public_id">\
        <div class="col-12 d-none" data-calendar-task-context></div>\
        <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" maxlength="255" placeholder="Например: Демо с клиентом"></div>\
        <div class="col-md-6"><label class="form-label">Дата начала</label><input class="form-control" type="date" name="starts_at_date"></div>\
        <div class="col-md-6"><label class="form-label">Время начала</label><input class="form-control" type="time" name="starts_at_time"></div>\
        <div class="col-md-6"><label class="form-label">Дата окончания</label><input class="form-control" type="date" name="ends_at_date"></div>\
        <div class="col-md-6"><label class="form-label">Время окончания</label><input class="form-control" type="time" name="ends_at_time"></div>\
        <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="3" placeholder="Короткий контекст события"></textarea></div>\
      </div></div>\
      <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn crm-btn-primary" type="submit">Создать</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="quickTaskDrawer" aria-labelledby="quickTaskDrawerTitle">\
  <div class="offcanvas-header"><h5 id="quickTaskDrawerTitle">Быстрый просмотр задачи</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button></div>\
  <div class="offcanvas-body" data-quick-task-body><div class="text-muted">Данные задачи загружаются...</div></div>\
</div>\
\
<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasTitle">\
  <div class="offcanvas-header"><h5 id="filterOffcanvasTitle">Фильтры</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть"></button></div>\
  <div class="offcanvas-body"><div class="text-muted">Параметры фильтрации загружаются для текущей страницы...</div></div>\
</div>';
  }

  function injectGlobalOverlays() {
    if (document.getElementById('createTaskModal')) {
      ensureProjectQuickPreviewDrawer();
      return;
    }
    document.body.insertAdjacentHTML('beforeend', buildOverlays());
    ensureProjectQuickPreviewDrawer();
  }

  function bindActions() {
    injectGlobalOverlays();

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-open-modal]');
      if (trigger) {
        if (trigger.disabled || trigger.getAttribute('data-permission-hidden') === '1') {
          e.preventDefault();
          return;
        }
        var modalEl = document.getElementById(trigger.dataset.openModal);
        if (modalEl) {
          modalEl.classList.remove('d-none');
          modalEl.removeAttribute('data-permission-hidden');
          modalEl.removeAttribute('aria-hidden');
          modalEl.style.display = '';
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
      }

      var deleteTrigger = e.target.closest('[data-confirm-delete]');
      if (deleteTrigger) {
        var deleteModal = document.getElementById('deleteConfirmModal');
        if (deleteModal) bootstrap.Modal.getOrCreateInstance(deleteModal).show();
      }
    });
  }

  function initEscapeForCustom() {
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      document.querySelectorAll('.offcanvas.show').forEach(function (node) {
        bootstrap.Offcanvas.getOrCreateInstance(node).hide();
      });
    });
  }

  return { bindActions: bindActions, initEscapeForCustom: initEscapeForCustom };
})();
