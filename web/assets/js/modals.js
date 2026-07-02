window.CRM = window.CRM || {};
window.CRM.modals = (function () {
  function ensureProjectQuickPreviewDrawer() {
    if (document.getElementById('projectQuickPreviewDrawer')) return;
    document.body.insertAdjacentHTML('beforeend', '\
<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="projectQuickPreviewDrawer" aria-labelledby="projectQuickPreviewDrawerTitle">\
  <div class="offcanvas-header"><h5 id="projectQuickPreviewDrawerTitle">' + window.CRM.i18n.t('js.modal.project_preview', 'Project Quick Preview') + '</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
  <div class="offcanvas-body"><p class="text-muted mb-0">' + window.CRM.i18n.t('js.modal.loading_project', 'Loading project data...') + '</p></div>\
</div>');
  }

  function buildOverlays() {
    return '\
<div class="toast-container position-fixed top-0 end-0 p-3 crm-toast-container">\
  <div id="toastSuccess" class="toast align-items-center crm-toast is-success" role="status" aria-live="polite" aria-atomic="true">\
    <div class="d-flex">\
      <div class="toast-body">' + window.CRM.i18n.t('js.modal.saved', 'Changes saved successfully') + '</div>\
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button>\
    </div>\
  </div>\
</div>\
\
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">' + window.CRM.i18n.t('js.modal.create_task', 'Create Task') + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
    <form id="createTaskForm">\
      <div class="modal-body"><div class="row g-3">\
        <div class="col-md-8"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_title', 'Title') + '</label><input class="form-control" name="title" maxlength="255" placeholder="' + window.CRM.i18n.t('js.modal.placeholder_task', 'E.g.: Prepare Q2 presentation') + '"></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_project', 'Project') + '</label><select class="form-select" name="project_public_id"><option value="">' + window.CRM.i18n.t('js.modal.no_project', 'No project') + '</option></select></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_status', 'Status') + '</label><select class="form-select" name="status"><option value="new">' + window.CRM.i18n.t('js.modal.status_new', 'New') + '</option></select></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_priority', 'Priority') + '</label><select class="form-select" name="priority"><option value="normal">' + window.CRM.i18n.t('js.modal.priority_normal', 'Normal') + '</option><option value="low">' + window.CRM.i18n.t('js.modal.priority_low', 'Low') + '</option><option value="high">' + window.CRM.i18n.t('js.modal.priority_high', 'High') + '</option><option value="urgent">' + window.CRM.i18n.t('js.modal.priority_urgent', 'Urgent') + '</option></select></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_assignee', 'Assignee') + '</label><select class="form-select" name="assignee_user_public_id"><option value="">' + window.CRM.i18n.t('js.modal.no_assignee', 'Not assigned') + '</option></select></div>\
<div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_start', 'Start date') + '</label><input class="form-control" name="start_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_due', 'Deadline') + '</label><input class="form-control" name="due_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_end', 'Planned completion') + '</label><input class="form-control" name="end_at" type="date"></div>\
        <div class="col-12"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_tags', 'Tags') + '</label><select class="form-select" name="tag_public_ids" multiple size="5"></select><div class="form-text">' + window.CRM.i18n.t('js.modal.tags_hint', 'Select multiple tags.') + '</div></div>\
        <div class="col-12"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_description', 'Description') + '</label><textarea class="form-control" name="description" rows="5" data-crm-visual-editor="1" data-richtext-off="1" placeholder="' + window.CRM.i18n.t('js.modal.placeholder_desc', 'Context, steps, risks, acceptance criteria') + '"></textarea></div>\
      </div></div>\
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">' + window.CRM.i18n.t('js.modal.cancel', 'Cancel') + '</button><button type="submit" class="btn crm-btn-primary">' + window.CRM.i18n.t('js.modal.create', 'Create') + '</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">' + window.CRM.i18n.t('js.modal.edit_task', 'Edit Task') + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
    <form id="editTaskForm">\
      <div class="modal-body"><div class="row g-3">\
        <div class="col-md-8"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_title', 'Title') + '</label><input class="form-control" name="title" maxlength="255" placeholder="' + window.CRM.i18n.t('js.modal.placeholder_task', 'E.g.: Prepare Q2 presentation') + '"></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_project', 'Project') + '</label><select class="form-select" name="project_public_id"><option value="">' + window.CRM.i18n.t('js.modal.no_project', 'No project') + '</option></select></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_status', 'Status') + '</label><select class="form-select" name="status"><option value="new">' + window.CRM.i18n.t('js.modal.status_new', 'New') + '</option></select></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_priority', 'Priority') + '</label><select class="form-select" name="priority"><option value="normal">' + window.CRM.i18n.t('js.modal.priority_normal', 'Normal') + '</option><option value="low">' + window.CRM.i18n.t('js.modal.priority_low', 'Low') + '</option><option value="high">' + window.CRM.i18n.t('js.modal.priority_high', 'High') + '</option><option value="urgent">' + window.CRM.i18n.t('js.modal.priority_urgent', 'Urgent') + '</option></select></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_assignee', 'Assignee') + '</label><select class="form-select" name="assignee_user_public_id"><option value="">' + window.CRM.i18n.t('js.modal.no_assignee', 'Not assigned') + '</option></select></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_start', 'Start date') + '</label><input class="form-control" name="start_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_due', 'Deadline') + '</label><input class="form-control" name="due_at" type="date"></div>\
        <div class="col-md-4"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_end', 'Planned completion') + '</label><input class="form-control" name="end_at" type="date"></div>\
        <div class="col-12"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_tags', 'Tags') + '</label><select class="form-select" name="tag_public_ids" multiple size="5"></select><div class="form-text">' + window.CRM.i18n.t('js.modal.tags_hint', 'Select multiple tags.') + '</div></div>\
        <div class="col-12"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_description', 'Description') + '</label><textarea class="form-control" name="description" rows="5" data-crm-visual-editor="1" data-richtext-off="1" placeholder="' + window.CRM.i18n.t('js.modal.placeholder_desc', 'Context, steps, risks, acceptance criteria') + '"></textarea></div>\
      </div></div>\
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">' + window.CRM.i18n.t('js.modal.cancel', 'Cancel') + '</button><button type="submit" class="btn crm-btn-primary">' + window.CRM.i18n.t('js.modal.save', 'Save') + '</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="modal fade" id="assignUserModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">' + window.CRM.i18n.t('js.modal.assign_user', 'Assign User') + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
    <form id="assignUserForm">\
      <div class="modal-body">\
        <div class="mb-3"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_task', 'Task') + '</label><select class="form-select" name="task_public_id"><option value="">' + window.CRM.i18n.t('js.modal.no_tasks_available', 'No tasks available') + '</option></select></div>\
        <div class="mb-2"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_employee', 'Employee') + '</label><select class="form-select" name="assignee_user_public_id"><option value="">' + window.CRM.i18n.t('js.modal.no_assign', 'Do not assign') + '</option></select></div>\
        <div class="small text-muted" data-assign-user-hint>' + window.CRM.i18n.t('js.modal.assign_hint', 'Select a task and new assignee.') + '</div>\
      </div>\
      <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">' + window.CRM.i18n.t('js.modal.cancel', 'Cancel') + '</button><button type="submit" class="btn crm-btn-primary">' + window.CRM.i18n.t('js.modal.assign', 'Assign') + '</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title" id="deleteConfirmTitle">' + window.CRM.i18n.t('js.modal.confirm_title', 'Confirm action') + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
    <div class="modal-body">\
      <div class="crm-alert crm-alert--warning mb-0" id="deleteConfirmBody">' + window.CRM.i18n.t('js.modal.confirm_delete_body', 'This action cannot be undone. Check the list of objects before deleting.') + '</div>\
    </div>\
    <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">' + window.CRM.i18n.t('js.modal.cancel', 'Cancel') + '</button><button class="btn crm-btn-danger-soft" type="button" id="deleteConfirmSubmitBtn">' + window.CRM.i18n.t('js.modal.delete', 'Delete') + '</button></div>\
  </div></div>\
</div>\
\
<div class="modal fade" id="calendarEventModal" tabindex="-1" aria-hidden="true">\
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">\
    <div class="modal-header"><h5 class="modal-title">' + window.CRM.i18n.t('js.modal.create_event', 'Create Event') + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
    <form id="calendarEventForm" novalidate>\
      <div class="modal-body"><div class="row g-3">\
        <input type="hidden" name="task_public_id">\
        <div class="col-12 d-none" data-calendar-task-context></div>\
        <div class="col-12"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_title', 'Title') + '</label><input class="form-control" name="title" maxlength="255" placeholder="' + window.CRM.i18n.t('js.modal.placeholder_event', 'E.g.: Client demo') + '"></div>\
        <div class="col-md-6"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_start_date', 'Start date') + '</label><input class="form-control" type="date" name="starts_at_date"></div>\
        <div class="col-md-6"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_start_time', 'Start time') + '</label><input class="form-control" type="time" name="starts_at_time"></div>\
        <div class="col-md-6"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_end_date', 'End date') + '</label><input class="form-control" type="date" name="ends_at_date"></div>\
        <div class="col-md-6"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_end_time', 'End time') + '</label><input class="form-control" type="time" name="ends_at_time"></div>\
        <div class="col-12"><label class="form-label">' + window.CRM.i18n.t('js.modal.label_description', 'Description') + '</label><textarea class="form-control" name="description" rows="3" data-crm-visual-editor="1" data-richtext-off="1" placeholder="' + window.CRM.i18n.t('js.modal.placeholder_event_desc', 'Brief event context') + '"></textarea></div>\
      </div></div>\
      <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal">' + window.CRM.i18n.t('js.modal.cancel', 'Cancel') + '</button><button class="btn crm-btn-primary" type="submit">' + window.CRM.i18n.t('js.modal.create', 'Create') + '</button></div>\
    </form>\
  </div></div>\
</div>\
\
<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="quickTaskDrawer" aria-labelledby="quickTaskDrawerTitle">\
  <div class="offcanvas-header"><h5 id="quickTaskDrawerTitle">' + window.CRM.i18n.t('js.modal.quick_task_view', 'Quick task view') + '</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
  <div class="offcanvas-body" data-quick-task-body><div class="text-muted">' + window.CRM.i18n.t('js.modal.loading_task', 'Loading task data...') + '</div></div>\
</div>\
\
<div class="offcanvas offcanvas-end crm-drawer-wide" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasTitle">\
  <div class="offcanvas-header"><h5 id="filterOffcanvasTitle">' + window.CRM.i18n.t('js.modal.filters', 'Filters') + '</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' + window.CRM.i18n.t('js.modal.close', 'Close') + '"></button></div>\
  <div class="offcanvas-body"><div class="text-muted">' + window.CRM.i18n.t('js.modal.loading_filters', 'Loading filter parameters for the current page...') + '</div></div>\
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
