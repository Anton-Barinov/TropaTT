<?php declare(strict_types=1); ?>
<?php $title = $t('rate_cards.title', 'TropaTT — Прайс-листы'); ?>
<body data-page="rate-cards" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-rate-cards-page">
  <div class="crm-page-head">
    <div>
      <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="nav.admin"><?= htmlspecialchars($t('nav.admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="rate_cards.page_title"><?= htmlspecialchars($t('rate_cards.page_title', 'Прайс-листы'), ENT_QUOTES, 'UTF-8') ?></li></ol>
      <h1 class="crm-page-title" data-i18n="rate_cards.page_title"><?= htmlspecialchars($t('rate_cards.page_title', 'Прайс-листы'), ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="crm-subtitle" data-i18n="rate_cards.subtitle"><?= htmlspecialchars($t('rate_cards.subtitle', 'Ставки себестоимости, продажи и вознаграждения по контрагентам и проектам.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="d-flex gap-2"><button class="btn crm-btn-primary" id="rateCardCreateOpenBtn" type="button" data-i18n="rate_cards.create_btn"><?= htmlspecialchars($t('rate_cards.create_btn', 'Создать прайс'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-secondary" id="rateCardCreateForProjectBtn" type="button" data-i18n="rate_cards.create_for_project_btn"><?= htmlspecialchars($t('rate_cards.create_for_project_btn', 'Создать для проекта'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </div>

  <!-- Rate cards list -->
  <div id="rateCardsList" class="crm-rate-cards-list mb-3">
    <div class="text-muted p-3" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
  </div>

  <!-- Assignments section -->
  <div class="crm-card crm-section-card">
    <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="rate_cards.assignments_title"><?= htmlspecialchars($t('rate_cards.assignments_title', 'Привязки к контрагентам и проектам'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="rate_cards.assignments_note"><?= htmlspecialchars($t('rate_cards.assignments_note', 'К каким контрагентам или проектам привязаны прайс-листы.'), ENT_QUOTES, 'UTF-8') ?></div></div><div><button class="btn btn-sm crm-btn-secondary" id="rateAssignmentCreateOpenBtn" type="button" data-i18n="rate_cards.assign_btn"><?= htmlspecialchars($t('rate_cards.assign_btn', 'Привязать прайс'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
    <div id="rateAssignmentsList" class="px-3 pb-3">
      <div class="text-muted small" data-i18n="rate_cards.assignments_loading"><?= htmlspecialchars($t('rate_cards.assignments_loading', 'Загрузка привязок...'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</main></div></div>

<!-- Unified rate card modal -->
<div class="modal fade" id="rateCardUnifiedModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="rateCardUnifiedTitle" data-i18n="rate_cards.modal_card_title"><?= htmlspecialchars($t('rate_cards.modal_card_title', 'Прайс-лист'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
  <div class="modal-body p-0">
    <!-- Tabs -->
    <ul class="nav nav-tabs px-3 pt-3" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rateTabInfo" type="button" role="tab" data-i18n="rate_cards.tab_info"><?= htmlspecialchars($t('rate_cards.tab_info', 'Основное'), ENT_QUOTES, 'UTF-8') ?></button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rateTabLines" type="button" role="tab" data-i18n="rate_cards.tab_lines"><?= htmlspecialchars($t('rate_cards.tab_lines', 'Строки прайса'), ENT_QUOTES, 'UTF-8') ?> <span class="badge text-bg-secondary ms-1" id="rateTabLinesCount">0</span></button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rateTabAssignments" type="button" role="tab" data-i18n="rate_cards.tab_assignments"><?= htmlspecialchars($t('rate_cards.tab_assignments', 'Привязки'), ENT_QUOTES, 'UTF-8') ?> <span class="badge text-bg-secondary ms-1" id="rateTabAssignCount">0</span></button></li>
    </ul>
    <div class="tab-content p-3">
      <!-- Tab: Info -->
      <div class="tab-pane fade show active" id="rateTabInfo" role="tabpanel">
        <form id="rateCardUnifiedForm">
          <input type="hidden" name="public_id">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label" data-i18n="rate_cards.field_title"><?= htmlspecialchars($t('rate_cards.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('rate_cards.placeholder_title', 'Например: Стандартный, Энтерпрайз'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="rate_cards.placeholder_title">
            </div>
            <div class="col-md-4">
              <label class="form-label" data-i18n="rate_cards.field_currency"><?= htmlspecialchars($t('rate_cards.field_currency', 'Валюта'), ENT_QUOTES, 'UTF-8') ?></label>
              <input class="form-control" name="currency_code" maxlength="8" placeholder="RUB">
              <div class="form-text" data-i18n="rate_cards.field_currency_hint"><?= htmlspecialchars($t('rate_cards.field_currency_hint', 'Пусто — валюта организации'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
          <div class="mb-3 mt-3">
            <label class="form-label" data-i18n="rate_cards.field_description"><?= htmlspecialchars($t('rate_cards.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea class="form-control" name="description" rows="2" placeholder="<?= htmlspecialchars($t('rate_cards.placeholder_description', 'Для каких договоров или условий этот прайс'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="rate_cards.placeholder_description"></textarea>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="rateCardIsDefault" name="is_default" value="1">
            <label class="form-check-label" for="rateCardIsDefault" data-i18n="rate_cards.field_default"><?= htmlspecialchars($t('rate_cards.field_default', 'Прайс по умолчанию'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="form-text" data-i18n="rate_cards.field_default_hint"><?= htmlspecialchars($t('rate_cards.field_default_hint', 'Применяется, когда нет привязки к контрагенту или проекту'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </form>
      </div>
      <!-- Tab: Lines -->
      <div class="tab-pane fade" id="rateTabLines" role="tabpanel">
        <div id="rateLinesList" class="mb-3">
          <div class="text-muted small" data-i18n="rate_cards.lines_loading"><?= htmlspecialchars($t('rate_cards.lines_loading', 'Загрузка строк...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <!-- Add line form -->
        <div class="crm-card p-3" style="border:1px dashed var(--bs-border-color);background:var(--bs-body-bg)">
          <h6 class="mb-2" data-i18n="rate_cards.add_line_title"><?= htmlspecialchars($t('rate_cards.add_line_title', 'Добавить строку'), ENT_QUOTES, 'UTF-8') ?></h6>
          <form id="rateCardLineForm">
            <input type="hidden" name="public_id">
            <div class="row g-2 mb-2">
              <div class="col-md-3 col-6">
                <label class="form-label small" data-i18n="rate_cards.th_user"><?= htmlspecialchars($t('rate_cards.th_user', 'Сотрудник'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select form-select-sm" name="user_public_id"><option value=""><?= htmlspecialchars($t('rate_cards.any_user', 'Любой'), ENT_QUOTES, 'UTF-8') ?></option></select>
              </div>
              <div class="col-md-3 col-6">
                <label class="form-label small" data-i18n="rate_cards.th_role"><?= htmlspecialchars($t('rate_cards.th_role', 'Роль'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select form-select-sm" name="role_code"><option value=""><?= htmlspecialchars($t('rate_cards.any_role', 'Любая'), ENT_QUOTES, 'UTF-8') ?></option></select>
              </div>
              <div class="col-md-3 col-6">
                <label class="form-label small" data-i18n="rate_cards.th_activity"><?= htmlspecialchars($t('rate_cards.th_activity', 'Вид работ'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select form-select-sm" name="activity_code"><option value=""><?= htmlspecialchars($t('rate_cards.any_activity', 'Любой'), ENT_QUOTES, 'UTF-8') ?></option></select>
              </div>
            </div>
            <div class="row g-2 align-items-end">
              <div class="col-md-2 col-4">
                <label class="form-label small text-danger" data-i18n="rate_cards.th_cost"><?= htmlspecialchars($t('rate_cards.th_cost', 'Себестоимость'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control form-control-sm" name="cost_rate" type="number" min="0" step="0.01" placeholder="—">
              </div>
              <div class="col-md-2 col-4">
                <label class="form-label small text-primary" data-i18n="rate_cards.th_bill"><?= htmlspecialchars($t('rate_cards.th_bill', 'Продажа'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control form-control-sm" name="bill_rate" type="number" min="0" step="0.01" placeholder="—">
              </div>
              <div class="col-md-2 col-4">
                <label class="form-label small text-success" data-i18n="rate_cards.th_payout"><?= htmlspecialchars($t('rate_cards.th_payout', 'Вознаграждение'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control form-control-sm" name="payout_rate" type="number" min="0" step="0.01" placeholder="—">
              </div>
              <div class="col-md-3 col-6">
                <label class="form-label small" data-i18n="rate_cards.th_period"><?= htmlspecialchars($t('rate_cards.th_period', 'Период'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="input-group input-group-sm"><input class="form-control" name="effective_from" type="date"><input class="form-control" name="effective_to" type="date" placeholder="∞"></div>
              </div>
              <div class="col-md-3 col-6 d-grid">
                <button class="btn btn-sm crm-btn-primary" type="submit" data-i18n="rate_cards.add_line"><?= htmlspecialchars($t('rate_cards.add_line', 'Добавить'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </div>
            <div class="form-text mt-1" data-i18n="rate_cards.line_hint"><?= htmlspecialchars($t('rate_cards.line_hint', 'Пустая ячейка ставки — «наследуется», не 0. Заполните хотя бы одну ставку.'), ENT_QUOTES, 'UTF-8') ?></div>
          </form>
        </div>
      </div>
      <!-- Tab: Assignments (for this card) -->
      <div class="tab-pane fade" id="rateTabAssignments" role="tabpanel">
        <div id="rateCardAssignmentsList">
          <div class="text-muted small" data-i18n="rate_cards.lines_loading"><?= htmlspecialchars($t('rate_cards.lines_loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
    </div>
  </div>
</div></div></div>

<!-- Create card for project modal -->
<div class="modal fade" id="rateCardCreateProjectModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="rate_cards.create_for_project_modal_title"><?= htmlspecialchars($t('rate_cards.create_for_project_modal_title', 'Создать прайс для проекта'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div><form id="rateCardCreateProjectForm"><div class="modal-body"><div class="mb-3"><label class="form-label" data-i18n="rate_cards.field_title"><?= htmlspecialchars($t('rate_cards.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('rate_cards.placeholder_project_card_name', 'Например: Прайс для проекта CRM'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="rate_cards.placeholder_project_card_name"></div><div class="mb-3"><label class="form-label" data-i18n="rate_cards.field_scope_ref"><?= htmlspecialchars($t('rate_cards.field_scope_ref', 'Проект'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="project_public_id" required><option value=""><?= htmlspecialchars($t('rate_cards.select_project', 'Выберите проект'), ENT_QUOTES, 'UTF-8') ?></option></select></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>

<!-- Assignment creation modal (separate, simpler) -->
<div class="modal fade" id="rateAssignmentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="rate_cards.modal_assign_title"><?= htmlspecialchars($t('rate_cards.modal_assign_title', 'Привязать прайс'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="rateAssignmentForm"><div class="modal-body"><div class="mb-3"><label class="form-label" data-i18n="rate_cards.th_card"><?= htmlspecialchars($t('rate_cards.th_card', 'Прайс'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="rate_card_public_id" required></select></div><div class="mb-3"><label class="form-label" data-i18n="rate_cards.field_scope_type"><?= htmlspecialchars($t('rate_cards.field_scope_type', 'Тип области'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="scope_type" required><option value="counterparty" data-i18n="rate_cards.scope_counterparty"><?= htmlspecialchars($t('rate_cards.scope_counterparty', 'Контрагент'), ENT_QUOTES, 'UTF-8') ?></option><option value="project" data-i18n="rate_cards.scope_project"><?= htmlspecialchars($t('rate_cards.scope_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></option></select></div><div class="mb-3"><label class="form-label" data-i18n="rate_cards.field_scope_ref"><?= htmlspecialchars($t('rate_cards.field_scope_ref', 'Контрагент / проект'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="scope_ref" required></select></div><div class="row g-2"><div class="col-6"><label class="form-label" data-i18n="rate_cards.field_from"><?= htmlspecialchars($t('rate_cards.field_from', 'С'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="effective_from" type="date"></div><div class="col-6"><label class="form-label" data-i18n="rate_cards.field_to"><?= htmlspecialchars($t('rate_cards.field_to', 'По'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="effective_to" type="date"></div></div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>
