<?php declare(strict_types=1); ?>
<?php $title = $t('approvals.title', 'TropaTT — Согласования'); ?>
<body data-page="approvals" data-protected="1">
<div class="crm-app">
  <aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
  <div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
  <main class="crm-content crm-automation-page">
    <div class="crm-page-head">
      <div>
        <ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=admin" data-i18n="approvals.breadcrumb_admin"><?= htmlspecialchars($t('approvals.breadcrumb_admin', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="approvals.page_title"><?= htmlspecialchars($t('approvals.page_title', 'Согласования'), ENT_QUOTES, 'UTF-8') ?></li></ol>
        <h1 class="crm-page-title" data-i18n="approvals.page_title"><?= htmlspecialchars($t('approvals.page_title', 'Согласования'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="crm-subtitle" data-i18n="approvals.subtitle"><?= htmlspecialchars($t('approvals.subtitle', 'Запросы на утверждение задач и проектов — без переписок, без потерянных решений.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="d-flex gap-2">
        <button id="approvalsRefreshBtn" class="btn crm-btn-secondary" type="button" data-i18n="approvals.btn_refresh"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i><?= htmlspecialchars($t('approvals.btn_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="approvalsCreateBtn" class="btn crm-btn-primary" type="button" data-i18n="approvals.btn_create"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i><?= htmlspecialchars($t('approvals.btn_create', 'Создать запрос'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-file-signature" aria-hidden="true"></i></span><strong data-i18n="approvals.brief_request_title"><?= htmlspecialchars($t('approvals.brief_request_title', 'Запрос'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="approvals.brief_request_text"><?= htmlspecialchars($t('approvals.brief_request_text', 'Сотрудник выбирает задачу или проект, назначает одного или нескольких согласующих. CRM сохраняет запрос и уведомляет участников.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span><strong data-i18n="approvals.brief_decision_title"><?= htmlspecialchars($t('approvals.brief_decision_title', 'Решение'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="approvals.brief_decision_text"><?= htmlspecialchars($t('approvals.brief_decision_text', 'Каждый согласующий видит запрос в своём интерфейсе. Одобрить или отклонить — в один клик. Результат фиксируется с датой и комментарием.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
      <div class="col-lg-4"><div class="crm-card crm-automation-brief-item"><span class="crm-automation-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><strong data-i18n="approvals.brief_control_title"><?= htmlspecialchars($t('approvals.brief_control_title', 'Контроль'), ENT_QUOTES, 'UTF-8') ?></strong><p class="mb-0" data-i18n="approvals.brief_control_text"><?= htmlspecialchars($t('approvals.brief_control_text', 'Доступ ограничен ролями. Аудит решений сохраняется в истории. Никто не сможет сказать «я не видел».'), ENT_QUOTES, 'UTF-8') ?></p></div></div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="crm-card crm-section-card crm-automation-list-toolbar">
          <div class="crm-section-head">
            <div><h2 class="h6 mb-0" data-i18n="approvals.heading_requests"><?= htmlspecialchars($t('approvals.heading_requests', 'Запросы'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" id="approvalsCountBadge"></div></div>
            <div class="d-flex gap-2 align-items-center">
              <div class="btn-group btn-group-sm" role="group" id="approvalsStatusFilter">
                <button type="button" class="btn crm-btn-secondary active" data-approval-filter="all" data-i18n="approvals.filter_all"><?= htmlspecialchars($t('approvals.filter_all', 'Все'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn crm-btn-secondary" data-approval-filter="pending" data-i18n="approvals.filter_pending"><?= htmlspecialchars($t('approvals.filter_pending', 'Ожидают'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn crm-btn-secondary" data-approval-filter="approved" data-i18n="approvals.filter_approved"><?= htmlspecialchars($t('approvals.filter_approved', 'Одобрено'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn crm-btn-secondary" data-approval-filter="rejected" data-i18n="approvals.filter_rejected"><?= htmlspecialchars($t('approvals.filter_rejected', 'Отклонено'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
              <input type="search" id="approvalsSearchInput" class="form-control form-control-sm" placeholder="<?= htmlspecialchars($t('approvals.placeholder_search', 'Поиск...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="approvals.placeholder_search" style="max-width:200px">
            </div>
          </div>
        </div>
        <div class="crm-card crm-section-card p-0 table-responsive crm-automation-table-card">
          <table class="table table-hover align-middle crm-table crm-automation-table mb-0">
            <thead><tr><th data-i18n="approvals.th_request"><?= htmlspecialchars($t('approvals.th_request', 'Запрос'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="approvals.th_entity"><?= htmlspecialchars($t('approvals.th_entity', 'Сущность'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="approvals.th_requested_by"><?= htmlspecialchars($t('approvals.th_requested_by', 'Запросил'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="approvals.th_status"><?= htmlspecialchars($t('approvals.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="approvals.th_date"><?= htmlspecialchars($t('approvals.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></th><th class="text-end" data-i18n="approvals.th_actions"><?= htmlspecialchars($t('approvals.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
            <tbody id="approvalsListBody"><tr><td colspan="6" class="text-muted" data-i18n="approvals.loading"><?= htmlspecialchars($t('approvals.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div class="modal fade" id="approvalsCreateModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header"><div><h5 class="modal-title" data-i18n="approvals.modal_create_title"><?= htmlspecialchars($t('approvals.modal_create_title', 'Создать запрос на согласование'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" data-i18n="approvals.modal_create_subtitle"><?= htmlspecialchars($t('approvals.modal_create_subtitle', 'Выберите задачу или проект и назначьте согласующих.'), ENT_QUOTES, 'UTF-8') ?></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('approvals.modal_close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="approvals.modal_close_aria"></button></div>
          <form id="approvalsCreateForm">
            <div class="modal-body">
              <div class="mb-3"><label class="form-label" data-i18n="approvals.modal_field_title"><?= htmlspecialchars($t('approvals.modal_field_title', 'Название запроса'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required placeholder="<?= htmlspecialchars($t('approvals.modal_placeholder_title', 'Например: Согласование бюджета проекта 1С:ERP'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="approvals.modal_placeholder_title"></div>
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label" data-i18n="approvals.modal_field_entity_type"><?= htmlspecialchars($t('approvals.modal_field_entity_type', 'Тип сущности'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="entity_type" id="approvalsEntityType" required><option value="task" data-i18n="approvals.opt_task"><?= htmlspecialchars($t('approvals.opt_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></option><option value="project" data-i18n="approvals.opt_project"><?= htmlspecialchars($t('approvals.opt_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
                <div class="col-md-8 position-relative"><label class="form-label" for="approvalsEntitySearch" data-i18n="approvals.modal_field_entity_search"><?= htmlspecialchars($t('approvals.modal_field_entity_search', 'Поиск задачи или проекта'), ENT_QUOTES, 'UTF-8') ?></label><input id="approvalsEntitySearch" class="form-control" placeholder="<?= htmlspecialchars($t('approvals.modal_placeholder_entity_search', 'Введите название или public_id...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="approvals.modal_placeholder_entity_search" autocomplete="off"><div id="approvalsEntityResults" class="crm-autocomplete-list d-none"></div><input type="hidden" name="entity_public_id"></div>
              </div>
              <div class="mt-3">
                <label class="form-label" data-i18n="approvals.modal_field_reviewers"><?= htmlspecialchars($t('approvals.modal_field_reviewers', 'Согласующие'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="approvalsReviewersSelect" class="form-select d-none" name="reviewer_public_ids" multiple aria-hidden="true"></select>
                <div id="approvalsReviewersPicker" class="crm-check-picker" aria-live="polite"></div>
                <div id="approvalsReviewersSummary" class="crm-inline-help mt-2"><i class="fa-solid fa-users" aria-hidden="true"></i><span data-i18n="approvals.reviewers_hint"><?= htmlspecialchars($t('approvals.reviewers_hint', 'Выберите одного или нескольких пользователей.'), ENT_QUOTES, 'UTF-8') ?></span></div>
              </div>
              <div class="mt-3"><label class="form-label" data-i18n="approvals.modal_field_comment"><?= htmlspecialchars($t('approvals.modal_field_comment', 'Комментарий к запросу'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="comment" maxlength="1000" rows="3" placeholder="<?= htmlspecialchars($t('approvals.modal_placeholder_comment', 'Например: проверьте бюджет, сроки и готовность к запуску'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="approvals.modal_placeholder_comment"></textarea></div>
            </div>
            <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" id="approvalsCreateSubmitBtn" data-i18n="approvals.modal_btn_submit"><?= htmlspecialchars($t('approvals.modal_btn_submit', 'Отправить на согласование'), ENT_QUOTES, 'UTF-8') ?></button></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="approvalsDetailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header"><div><h5 class="modal-title" id="approvalsDetailTitle" data-i18n="approvals.modal_detail_title"><?= htmlspecialchars($t('approvals.modal_detail_title', 'Детали согласования'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" id="approvalsDetailSubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('approvals.modal_close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="approvals.modal_close_aria"></button></div>
          <div class="modal-body" id="approvalsDetailBody"></div>
          <div class="modal-footer" id="approvalsDetailFooter"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.close"><?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button></div>
        </div>
      </div>
    </div>

    <!-- Approve/Reject Comment Modal -->
    <div class="modal fade" id="approvalsDecisionModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><div><h5 class="modal-title" id="approvalsDecisionTitle" data-i18n="approvals.modal_decision_title"><?= htmlspecialchars($t('approvals.modal_decision_title', 'Подтвердите решение'), ENT_QUOTES, 'UTF-8') ?></h5><div class="crm-modal-subtitle" data-i18n="approvals.modal_decision_subtitle"><?= htmlspecialchars($t('approvals.modal_decision_subtitle', 'Это действие нельзя отменить. Решение будет записано в историю.'), ENT_QUOTES, 'UTF-8') ?></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('approvals.modal_close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="approvals.modal_close_aria"></button></div>
          <form id="approvalsDecisionForm">
            <input type="hidden" name="approval_id"><input type="hidden" name="action">
            <div class="modal-body"><label class="form-label" data-i18n="approvals.modal_field_decision_comment"><?= htmlspecialchars($t('approvals.modal_field_decision_comment', 'Комментарий (необязательно)'), ENT_QUOTES, 'UTF-8') ?></label><textarea id="approvalsDecisionComment" class="form-control" name="comment" rows="2" placeholder="<?= htmlspecialchars($t('approvals.modal_placeholder_decision_comment', 'Причина решения...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="approvals.modal_placeholder_decision_comment"></textarea></div>
            <div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" id="approvalsDecisionSubmitBtn" data-i18n="approvals.modal_btn_confirm"><?= htmlspecialchars($t('approvals.modal_btn_confirm', 'Подтвердить'), ENT_QUOTES, 'UTF-8') ?></button></div>
          </form>
        </div>
      </div>
    </div>

  </main></div></div>
</body>
