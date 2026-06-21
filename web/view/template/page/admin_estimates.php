<?php declare(strict_types=1); ?>
<?php $title = $t('admin_estimates.title', 'TropaTT — Наборы оценок'); ?>
<body data-page="admin-estimates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-estimates-page">
<div class="crm-page-head">
  <div>
    <h1 class="crm-page-title" data-i18n="admin_estimates.page_title"><?= htmlspecialchars($t('admin_estimates.page_title', 'Наборы оценок'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="crm-subtitle" data-i18n="admin_estimates.subtitle"><?= htmlspecialchars($t('admin_estimates.subtitle', 'Управление наборами оценок: T-shirt sizes, Story Points, Complexity, Risk и произвольные наборы.'), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <div class="d-flex gap-2">
    <button id="adminEstimatesRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="admin_estimates.refresh_btn"><?= htmlspecialchars($t('admin_estimates.refresh_btn', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
    <button id="adminEstimatesCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="admin_estimates.create_btn"><?= htmlspecialchars($t('admin_estimates.create_btn', 'Создать набор'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>
</div>

<div class="alert alert-info mb-3" role="alert" data-i18n="admin_estimates.alert_info">
  <?= htmlspecialchars($t('admin_estimates.alert_info', 'Наборы оценок используются для оценки задач. Каждый набор содержит несколько опций (значений). Набор может быть глобальным или привязанным к проекту.'), ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head">
        <div>
          <h2 class="h6 mb-0" data-i18n="admin_estimates.section_list_title"><?= htmlspecialchars($t('admin_estimates.section_list_title', 'Наборы оценок'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="crm-section-note" data-i18n="admin_estimates.section_list_note"><?= htmlspecialchars($t('admin_estimates.section_list_note', 'Все доступные наборы оценок в системе.'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm crm-table mb-0">
          <thead>
            <tr>
              <th data-i18n="admin_estimates.th_name"><?= htmlspecialchars($t('admin_estimates.th_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></th>
              <th data-i18n="admin_estimates.th_code"><?= htmlspecialchars($t('admin_estimates.th_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></th>
              <th data-i18n="admin_estimates.th_type"><?= htmlspecialchars($t('admin_estimates.th_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></th>
              <th data-i18n="admin_estimates.th_scope"><?= htmlspecialchars($t('admin_estimates.th_scope', 'Область'), ENT_QUOTES, 'UTF-8') ?></th>
              <th data-i18n="admin_estimates.th_options"><?= htmlspecialchars($t('admin_estimates.th_options', 'Опции'), ENT_QUOTES, 'UTF-8') ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody id="adminEstimatesBody">
            <tr><td colspan="6" class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</main></div></div>

<!-- Create/Edit Set Modal -->
<div class="modal fade" id="estimateSetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="estimateSetModalTitle" data-i18n="admin_estimates.modal_create_title"><?= htmlspecialchars($t('admin_estimates.modal_create_title', 'Создать набор оценок'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <form id="estimateSetForm">
        <input type="hidden" name="public_id" id="estimateSetPublicId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="estimateSetName" data-i18n="admin_estimates.field_name"><?= htmlspecialchars($t('admin_estimates.field_name', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="estimateSetName" name="name" required maxlength="255" placeholder="<?= htmlspecialchars($t('admin_estimates.field_name_placeholder', 'Например: T-shirt Size'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_estimates.field_name_placeholder">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="estimateSetCode" data-i18n="admin_estimates.field_code"><?= htmlspecialchars($t('admin_estimates.field_code', 'Код'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" id="estimateSetCode" name="code" maxlength="64" placeholder="<?= htmlspecialchars($t('admin_estimates.field_code_placeholder', 'auto-generated'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="admin_estimates.field_code_placeholder">
              <div class="form-text" data-i18n="admin_estimates.field_code_hint"><?= htmlspecialchars($t('admin_estimates.field_code_hint', 'Оставьте пустым для автогенерации.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="estimateSetType" data-i18n="admin_estimates.field_type"><?= htmlspecialchars($t('admin_estimates.field_type', 'Тип оценки'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="estimateSetType" name="estimate_type" required>
                <option value="tshirt" data-i18n="admin_estimates.type_tshirt"><?= htmlspecialchars($t('admin_estimates.type_tshirt', 'T-shirt Size'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="complexity" data-i18n="admin_estimates.type_complexity"><?= htmlspecialchars($t('admin_estimates.type_complexity', 'Complexity'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="risk" data-i18n="admin_estimates.type_risk"><?= htmlspecialchars($t('admin_estimates.type_risk', 'Risk'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="story_points" data-i18n="admin_estimates.type_sp"><?= htmlspecialchars($t('admin_estimates.type_sp', 'Story Points'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="custom" data-i18n="admin_estimates.type_custom"><?= htmlspecialchars($t('admin_estimates.type_custom', 'Custom'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="estimateSetScope" data-i18n="admin_estimates.field_scope"><?= htmlspecialchars($t('admin_estimates.field_scope', 'Область'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="estimateSetScope" name="scope_type" required>
                <option value="global" data-i18n="admin_estimates.scope_global"><?= htmlspecialchars($t('admin_estimates.scope_global', 'Global'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="project" data-i18n="admin_estimates.scope_project"><?= htmlspecialchars($t('admin_estimates.scope_project', 'Project'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-md-4" id="estimateSetProjectField" style="display:none">
              <label class="form-label" for="estimateSetProject" data-i18n="admin_estimates.field_project"><?= htmlspecialchars($t('admin_estimates.field_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="estimateSetProject" name="project_public_id">
                <option value="" data-i18n="admin_estimates.option_no_project"><?= htmlspecialchars($t('admin_estimates.option_no_project', '—'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="estimateSetDescription" data-i18n="admin_estimates.field_description"><?= htmlspecialchars($t('admin_estimates.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="estimateSetDescription" name="description" rows="2" maxlength="1000"></textarea>
            </div>
          </div>

          <hr class="my-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 mb-0" data-i18n="admin_estimates.options_title"><?= htmlspecialchars($t('admin_estimates.options_title', 'Опции набора'), ENT_QUOTES, 'UTF-8') ?></h3>
            <button type="button" class="btn btn-sm crm-btn-secondary" id="estimateSetAddOptionBtn" data-i18n="admin_estimates.add_option_btn"><?= htmlspecialchars($t('admin_estimates.add_option_btn', '+ Добавить опцию'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
          <div id="estimateSetOptionsList" class="mb-3">
            <div class="text-muted small" data-i18n="admin_estimates.no_options"><?= htmlspecialchars($t('admin_estimates.no_options', 'Нет опций. Добавьте хотя бы одну опцию.'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="btn crm-btn-primary" id="estimateSetSaveBtn" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Options Sub-Modal (for editing options inline is done in the main modal) -->

<!-- Archive Confirm Modal -->
<div class="modal fade" id="estimateArchiveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-i18n="admin_estimates.archive_title"><?= htmlspecialchars($t('admin_estimates.archive_title', 'Архивировать набор'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button>
      </div>
      <div class="modal-body">
        <p data-i18n="admin_estimates.archive_confirm"><?= htmlspecialchars($t('admin_estimates.archive_confirm', 'Архивировать этот набор? Задачи с этой оценкой не потеряют данные.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-primary" id="estimateArchiveConfirmBtn" data-i18n="admin_estimates.archive_btn"><?= htmlspecialchars($t('admin_estimates.archive_btn', 'Архивировать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
