<?php declare(strict_types=1); ?>
<?php $title = $t('project_modules.page_title', 'TropaTT — Модули проектов'); ?>
<body data-page="project-modules" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content">

<div class="crm-page-head">
  <div>
    <h1 class="crm-page-title" data-i18n="project_modules.page_title"><?= htmlspecialchars($t('project_modules.page_title', 'Модули проектов'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="crm-subtitle" data-i18n="project_modules.subtitle"><?= htmlspecialchars($t('project_modules.subtitle', 'Управление функциональными модулями и направлениями внутри проектов.'), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <div class="d-flex gap-2">
    <select id="projectModulesProjectFilter" class="form-select crm-field-w-250" data-i18n-placeholder="project_modules.filter_project_placeholder">
      <option value="" data-i18n="project_modules.filter_all_projects"><?= htmlspecialchars($t('project_modules.filter_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option>
    </select>
    <button id="projectModulesRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="page.refresh"><?= htmlspecialchars($t('page.refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button id="projectModulesCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="project_modules.create_btn"><?= htmlspecialchars($t('project_modules.create_btn', 'Создать модуль'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head">
        <div>
          <h2 class="h6 mb-0" data-i18n="project_modules.section_list_title"><?= htmlspecialchars($t('project_modules.section_list_title', 'Модули'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
      </div>
      <table class="table table-sm crm-table mb-0">
        <thead>
          <tr>
            <th data-i18n="project_modules.th_title"><?= htmlspecialchars($t('project_modules.th_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></th>
            <th data-i18n="project_modules.th_project"><?= htmlspecialchars($t('project_modules.th_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></th>
            <th data-i18n="project_modules.th_status"><?= htmlspecialchars($t('project_modules.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th>
            <th data-i18n="project_modules.th_lead"><?= htmlspecialchars($t('project_modules.th_lead', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></th>
            <th data-i18n="project_modules.th_progress"><?= htmlspecialchars($t('project_modules.th_progress', 'Прогресс'), ENT_QUOTES, 'UTF-8') ?></th>
            <th data-i18n="project_modules.th_tasks"><?= htmlspecialchars($t('project_modules.th_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></th>
            <th data-i18n="project_modules.th_target"><?= htmlspecialchars($t('project_modules.th_target', 'Срок'), ENT_QUOTES, 'UTF-8') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody id="projectModulesBody">
          <tr><td colspan="8" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

</main></div></div>

<!-- Create/Edit Module Modal -->
<div class="modal fade" id="projectModuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="projectModuleModalTitle" data-i18n="project_modules.modal_create_title"><?= htmlspecialchars($t('project_modules.modal_create_title', 'Создать модуль'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="projectModuleForm">
        <input type="hidden" id="projectModulePublicId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="projectModuleTitle" data-i18n="project_modules.field_title"><?= htmlspecialchars($t('project_modules.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleTitle" required maxlength="255" data-i18n-placeholder="project_modules.field_title_placeholder" placeholder="<?= htmlspecialchars($t('project_modules.field_title_placeholder', 'Например: Оплата'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="projectModuleProject" data-i18n="project_modules.field_project"><?= htmlspecialchars($t('project_modules.field_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="projectModuleProject" required>
                <option value="" data-i18n="project_modules.option_select_project"><?= htmlspecialchars($t('project_modules.option_select_project', 'Выберите проект...'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectModuleStatus" data-i18n="project_modules.field_status"><?= htmlspecialchars($t('project_modules.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="projectModuleStatus">
                <option value="planned" data-i18n="project_modules.status_planned"><?= htmlspecialchars($t('project_modules.status_planned', 'Запланирован'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="in_progress" data-i18n="project_modules.status_in_progress"><?= htmlspecialchars($t('project_modules.status_in_progress', 'В работе'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="paused" data-i18n="project_modules.status_paused"><?= htmlspecialchars($t('project_modules.status_paused', 'Приостановлен'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="completed" data-i18n="project_modules.status_completed"><?= htmlspecialchars($t('project_modules.status_completed', 'Завершён'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="cancelled" data-i18n="project_modules.status_cancelled"><?= htmlspecialchars($t('project_modules.status_cancelled', 'Отменён'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectModuleLead" data-i18n="project_modules.field_lead"><?= htmlspecialchars($t('project_modules.field_lead', 'Ответственный'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="projectModuleLead">
                <option value="" data-i18n="project_modules.option_no_lead"><?= htmlspecialchars($t('project_modules.option_no_lead', 'Не назначен'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="projectModuleColor" data-i18n="project_modules.field_color"><?= htmlspecialchars($t('project_modules.field_color', 'Цвет'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleColor" type="color" value="#2563eb" style="height:38px;padding:3px">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="projectModuleStartAt" data-i18n="project_modules.field_start_at"><?= htmlspecialchars($t('project_modules.field_start_at', 'Дата начала'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleStartAt" type="date">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="projectModuleTargetAt" data-i18n="project_modules.field_target_at"><?= htmlspecialchars($t('project_modules.field_target_at', 'Целевая дата'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="projectModuleTargetAt" type="date">
            </div>
            <div class="col-12">
              <label class="form-label" for="projectModuleDescription" data-i18n="project_modules.field_description"><?= htmlspecialchars($t('project_modules.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="projectModuleDescription" rows="3" maxlength="65535"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="projectModuleSaveBtn" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Archive Confirm Modal -->
<div class="modal fade" id="projectModuleArchiveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="project_modules.archive_title"><?= htmlspecialchars($t('project_modules.archive_title', 'Архивировать модуль'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <p data-i18n="project_modules.archive_confirm"><?= htmlspecialchars($t('project_modules.archive_confirm', 'Архивировать этот модуль? Задачи модуля не будут удалены.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="projectModuleArchiveConfirmBtn" data-i18n="project_modules.archive_btn"><?= htmlspecialchars($t('project_modules.archive_btn', 'Архивировать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
