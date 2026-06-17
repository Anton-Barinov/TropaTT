<?php declare(strict_types=1); ?>
<?php $title = $t('intake.page_title', 'TropaTT — Входящие'); ?>
<body data-page="intake" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content">

<div class="crm-page-head">
  <div>
    <h1 class="crm-page-title" data-i18n="intake.page_title"><?= htmlspecialchars($t('intake.page_title', 'Входящие'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="crm-subtitle" data-i18n="intake.subtitle"><?= htmlspecialchars($t('intake.subtitle', 'Сбор и обработка входящих заявок: фиксация, triage, принятие в задачу или отклонение.'), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <div class="d-flex gap-2">
    <select id="intakeStatusFilter" class="form-select crm-field-w-180">
      <option value="" data-i18n="intake.filter_all_statuses"><?= htmlspecialchars($t('intake.filter_all_statuses', 'Все статусы'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="pending" data-i18n="intake.status_pending"><?= htmlspecialchars($t('intake.status_pending', 'Ожидает'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="snoozed" data-i18n="intake.status_snoozed"><?= htmlspecialchars($t('intake.status_snoozed', 'Отложено'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="accepted" data-i18n="intake.status_accepted"><?= htmlspecialchars($t('intake.status_accepted', 'Принято'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="rejected" data-i18n="intake.status_rejected"><?= htmlspecialchars($t('intake.status_rejected', 'Отклонено'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="duplicate" data-i18n="intake.status_duplicate"><?= htmlspecialchars($t('intake.status_duplicate', 'Дубликат'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
    <select id="intakeSourceFilter" class="form-select crm-field-w-160">
      <option value="" data-i18n="intake.filter_all_sources"><?= htmlspecialchars($t('intake.filter_all_sources', 'Все источники'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="manual" data-i18n="intake.source_manual"><?= htmlspecialchars($t('intake.source_manual', 'Вручную'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="email" data-i18n="intake.source_email"><?= htmlspecialchars($t('intake.source_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="api" data-i18n="intake.source_api"><?= htmlspecialchars($t('intake.source_api', 'API'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="webhook" data-i18n="intake.source_webhook"><?= htmlspecialchars($t('intake.source_webhook', 'Webhook'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="ai" data-i18n="intake.source_ai"><?= htmlspecialchars($t('intake.source_ai', 'AI'), ENT_QUOTES, 'UTF-8') ?></option>
      <option value="import" data-i18n="intake.source_import"><?= htmlspecialchars($t('intake.source_import', 'Импорт'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
    <button id="intakeRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button id="intakeCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="intake.create_btn"><?= htmlspecialchars($t('intake.create_btn', 'Создать заявку'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head">
        <div>
          <h2 class="h6 mb-0" data-i18n="intake.section_list_title"><?= htmlspecialchars($t('intake.section_list_title', 'Заявки'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <span id="intakeTotalCount" class="badge bg-secondary"></span>
      </div>
      <div class="table-responsive">
      <table class="table table-sm crm-table mb-0">
        <thead>
          <tr>
            <th style="width:90px" data-i18n="intake.th_id"><?= htmlspecialchars($t('intake.th_id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
            <th data-i18n="intake.th_title"><?= htmlspecialchars($t('intake.th_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:100px" data-i18n="intake.th_status"><?= htmlspecialchars($t('intake.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:70px" data-i18n="intake.th_priority"><?= htmlspecialchars($t('intake.th_priority', 'Приор.'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:90px" data-i18n="intake.th_source"><?= htmlspecialchars($t('intake.th_source', 'Источник'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:140px" data-i18n="intake.th_project"><?= htmlspecialchars($t('intake.th_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:130px" data-i18n="intake.th_client"><?= htmlspecialchars($t('intake.th_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:130px" data-i18n="intake.th_assignee"><?= htmlspecialchars($t('intake.th_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:100px" data-i18n="intake.th_due"><?= htmlspecialchars($t('intake.th_due', 'Срок'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:90px" data-i18n="intake.th_created"><?= htmlspecialchars($t('intake.th_created', 'Создана'), ENT_QUOTES, 'UTF-8') ?></th>
            <th style="width:180px"></th>
          </tr>
        </thead>
        <tbody id="intakeBody">
          <tr><td colspan="11" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

</main></div></div>

<!-- Create/Edit Intake Modal -->
<div class="modal fade" id="intakeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="intakeModalTitle" data-i18n="intake.modal_create_title"><?= htmlspecialchars($t('intake.modal_create_title', 'Создать заявку'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="intakeForm">
        <input type="hidden" id="intakePublicId" value="">
        <input type="hidden" id="intakeRowVersion" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="intakeTitle" data-i18n="intake.field_title"><?= htmlspecialchars($t('intake.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <input class="form-control" id="intakeTitle" required maxlength="255" data-i18n-placeholder="intake.field_title_placeholder" placeholder="<?= htmlspecialchars($t('intake.field_title_placeholder', 'Краткое описание заявки'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="intakeDescription" data-i18n="intake.field_description"><?= htmlspecialchars($t('intake.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="intakeDescription" rows="4" maxlength="65535"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="intakePriority" data-i18n="intake.field_priority"><?= htmlspecialchars($t('intake.field_priority', 'Приоритет'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="intakePriority">
                <option value="low" data-i18n="intake.priority_low"><?= htmlspecialchars($t('intake.priority_low', 'Низкий'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="normal" selected data-i18n="intake.priority_normal"><?= htmlspecialchars($t('intake.priority_normal', 'Средний'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="high" data-i18n="intake.priority_high"><?= htmlspecialchars($t('intake.priority_high', 'Высокий'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="urgent" data-i18n="intake.priority_urgent"><?= htmlspecialchars($t('intake.priority_urgent', 'Срочный'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="intakeSource" data-i18n="intake.field_source"><?= htmlspecialchars($t('intake.field_source', 'Источник'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="intakeSource">
                <option value="manual" data-i18n="intake.source_manual"><?= htmlspecialchars($t('intake.source_manual', 'Вручную'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="email" data-i18n="intake.source_email"><?= htmlspecialchars($t('intake.source_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="api" data-i18n="intake.source_api"><?= htmlspecialchars($t('intake.source_api', 'API'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="webhook" data-i18n="intake.source_webhook"><?= htmlspecialchars($t('intake.source_webhook', 'Webhook'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="ai" data-i18n="intake.source_ai"><?= htmlspecialchars($t('intake.source_ai', 'AI'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="intakeAssign" data-i18n="intake.field_assignee"><?= htmlspecialchars($t('intake.field_assignee', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="intakeAssign">
                <option value="" data-i18n="intake.option_no_assignee"><?= htmlspecialchars($t('intake.option_no_assignee', 'Не назначен'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="intakeProject" data-i18n="intake.field_project"><?= htmlspecialchars($t('intake.field_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="intakeProject">
                <option value="" data-i18n="intake.option_no_project"><?= htmlspecialchars($t('intake.option_no_project', 'Не выбран'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="intakeClient" data-i18n="intake.field_client"><?= htmlspecialchars($t('intake.field_client', 'Клиент'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="intakeClient">
                <option value="" data-i18n="intake.option_no_client"><?= htmlspecialchars($t('intake.option_no_client', 'Не выбран'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="intakeDueAt" data-i18n="intake.field_due_date"><?= htmlspecialchars($t('intake.field_due_date', 'Срок'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="intakeDueAt" type="date">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="intakeSourceRef" data-i18n="intake.field_source_ref"><?= htmlspecialchars($t('intake.field_source_ref', 'Ссылка источника'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="intakeSourceRef" maxlength="500" data-i18n-placeholder="intake.field_source_ref_placeholder" placeholder="<?= htmlspecialchars($t('intake.field_source_ref_placeholder', 'Email или внешняя ссылка'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="intakeSaveBtn" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Accept Modal -->
<div class="modal fade" id="intakeAcceptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="intake.accept_title"><?= htmlspecialchars($t('intake.accept_title', 'Принять заявку'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="intakeAcceptForm">
        <input type="hidden" id="intakeAcceptPublicId" value="">
        <input type="hidden" id="intakeAcceptRowVersion" value="">
        <div class="modal-body">
          <p id="intakeAcceptItemTitle" class="mb-3 fw-bold"></p>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="intakeAcceptProject" data-i18n="intake.accept_field_project"><?= htmlspecialchars($t('intake.accept_field_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <select class="form-select" id="intakeAcceptProject" required>
                <option value="" data-i18n="intake.option_no_project"><?= htmlspecialchars($t('intake.option_no_project', 'Не выбран'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="intakeAcceptConfirmBtn" data-i18n="intake.accept_confirm_btn"><?= htmlspecialchars($t('intake.accept_confirm_btn', 'Принять и создать задачу'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="intakeRejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="intake.reject_title"><?= htmlspecialchars($t('intake.reject_title', 'Отклонить заявку'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="intakeRejectForm">
        <input type="hidden" id="intakeRejectPublicId" value="">
        <input type="hidden" id="intakeRejectRowVersion" value="">
        <div class="modal-body">
          <p id="intakeRejectItemTitle" class="mb-3 fw-bold"></p>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="intakeRejectReason" data-i18n="intake.reject_field_reason"><?= htmlspecialchars($t('intake.reject_field_reason', 'Причина отклонения'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <textarea class="form-control" id="intakeRejectReason" rows="3" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-danger" id="intakeRejectConfirmBtn" data-i18n="intake.reject_confirm_btn"><?= htmlspecialchars($t('intake.reject_confirm_btn', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Snooze Modal -->
<div class="modal fade" id="intakeSnoozeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="intake.snooze_title"><?= htmlspecialchars($t('intake.snooze_title', 'Отложить заявку'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="intakeSnoozeForm">
        <input type="hidden" id="intakeSnoozePublicId" value="">
        <input type="hidden" id="intakeSnoozeRowVersion" value="">
        <div class="modal-body">
          <p id="intakeSnoozeItemTitle" class="mb-3 fw-bold"></p>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="intakeSnoozeUntil" data-i18n="intake.snooze_field_date"><?= htmlspecialchars($t('intake.snooze_field_date', 'Отложить до'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <input class="form-control" id="intakeSnoozeUntil" type="date" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="intakeSnoozeConfirmBtn" data-i18n="intake.snooze_confirm_btn"><?= htmlspecialchars($t('intake.snooze_confirm_btn', 'Отложить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Duplicate Modal -->
<div class="modal fade" id="intakeDuplicateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="intake.duplicate_title"><?= htmlspecialchars($t('intake.duplicate_title', 'Отметить как дубликат'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="intakeDuplicateForm">
        <input type="hidden" id="intakeDuplicatePublicId" value="">
        <input type="hidden" id="intakeDuplicateRowVersion" value="">
        <div class="modal-body">
          <p id="intakeDuplicateItemTitle" class="mb-3 fw-bold"></p>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="intakeDuplicateTarget" data-i18n="intake.duplicate_field_target"><?= htmlspecialchars($t('intake.duplicate_field_target', 'ID заявки-дубликата'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <input class="form-control" id="intakeDuplicateTarget" required data-i18n-placeholder="intake.duplicate_field_target_placeholder" placeholder="<?= htmlspecialchars($t('intake.duplicate_field_target_placeholder', 'Введите public_id заявки'), ENT_QUOTES, 'UTF-8') ?>">
              <div class="form-text" data-i18n="intake.duplicate_field_target_hint"><?= htmlspecialchars($t('intake.duplicate_field_target_hint', 'Укажите public_id существующей заявки, дубликатом которой является текущая'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="intakeDuplicateConfirmBtn" data-i18n="intake.duplicate_confirm_btn"><?= htmlspecialchars($t('intake.duplicate_confirm_btn', 'Отметить дубликатом'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reopen Confirm Modal -->
<div class="modal fade" id="intakeReopenModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="intake.reopen_title"><?= htmlspecialchars($t('intake.reopen_title', 'Восстановить заявку'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="intakeReopenForm">
        <input type="hidden" id="intakeReopenPublicId" value="">
        <input type="hidden" id="intakeReopenRowVersion" value="">
        <div class="modal-body">
          <p id="intakeReopenItemTitle" class="mb-3 fw-bold"></p>
          <p data-i18n="intake.reopen_confirm"><?= htmlspecialchars($t('intake.reopen_confirm', 'Вернуть эту заявку в статус «Ожидает» для повторного рассмотрения?'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="intakeReopenConfirmBtn" data-i18n="intake.reopen_confirm_btn"><?= htmlspecialchars($t('intake.reopen_confirm_btn', 'Восстановить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Activities Modal -->
<div class="modal fade" id="intakeActivitiesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="intake.activities_title"><?= htmlspecialchars($t('intake.activities_title', 'История заявки'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body p-0">
        <div id="intakeActivitiesBody" class="p-3">
          <p class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.close"><?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="intakeDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="intake.delete_title"><?= htmlspecialchars($t('intake.delete_title', 'Удалить заявку'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <p id="intakeDeleteItemTitle" class="mb-3 fw-bold"></p>
        <p data-i18n="intake.delete_confirm"><?= htmlspecialchars($t('intake.delete_confirm', 'Удалить эту заявку? Это действие необратимо.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-danger" id="intakeDeleteConfirmBtn" data-i18n="intake.delete_confirm_btn"><?= htmlspecialchars($t('intake.delete_confirm_btn', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
