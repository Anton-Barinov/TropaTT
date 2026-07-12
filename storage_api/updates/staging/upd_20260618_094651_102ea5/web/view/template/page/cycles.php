<?php declare(strict_types=1); ?>
<?php $title = $t('cycles.page_title', 'Циклы'); ?>
<body data-page="cycles" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content">
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= htmlspecialchars($t('cycles.page_title', 'Циклы'), ENT_QUOTES, 'UTF-8') ?><span class="crm-badge crm-badge-secondary-outline ms-2 small fw-normal"><?= htmlspecialchars($t('cycles.subtitle_badge', 'Спринты / Итерации'), ENT_QUOTES, 'UTF-8') ?></span></h4>
    <div>
      <button class="btn btn-sm crm-btn-primary" onclick="window.openCycleModal(null)">
        <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t('cycles.btn_create', 'Создать цикл'), ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="row g-2 mb-3">
    <div class="col-auto">
      <select class="form-select form-select-sm" id="cycleProjectFilter" style="min-width:200px;">
        <option value=""><?= htmlspecialchars($t('cycles.filter_all_projects', 'Все проекты'), ENT_QUOTES, 'UTF-8') ?></option>
      </select>
    </div>
    <div class="col-auto">
      <select class="form-select form-select-sm" id="cycleStatusFilter">
        <option value=""><?= htmlspecialchars($t('cycles.filter_all_statuses', 'Все статусы'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="planned"><?= htmlspecialchars($t('cycles.filter_planned', 'Запланированные'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="active"><?= htmlspecialchars($t('cycles.filter_active', 'Активные'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="completed"><?= htmlspecialchars($t('cycles.filter_completed', 'Завершённые'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="archived"><?= htmlspecialchars($t('cycles.filter_archived', 'Архивные'), ENT_QUOTES, 'UTF-8') ?></option>
      </select>
    </div>
    <div class="col-auto">
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" id="cycleSearchInput" placeholder="<?= htmlspecialchars($t('cycles.search_placeholder', 'Поиск циклов...'), ENT_QUOTES, 'UTF-8') ?>" style="min-width:200px;">
        <button class="btn btn-outline-secondary" type="button" onclick="window.loadWorkCycles(1)"><i class="fa-solid fa-search"></i></button>
      </div>
    </div>
  </div>

  <!-- Loading State -->
  <div id="cycleLoadingState" class="text-center py-5 d-none">
    <div class="spinner-border text-muted" role="status"><span class="visually-hidden"><?= htmlspecialchars($t('cycles.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></span></div>
    <div class="text-muted small mt-2"><?= htmlspecialchars($t('cycles.loading_cycles', 'Загрузка циклов...'), ENT_QUOTES, 'UTF-8') ?></div>
  </div>

  <!-- Empty State -->
  <div id="cycleEmptyState" class="text-center py-5 d-none">
    <i class="fa-solid fa-rotate fa-3x text-muted mb-3"></i>
    <h5 class="text-muted"><?= htmlspecialchars($t('cycles.empty_title', 'Нет циклов'), ENT_QUOTES, 'UTF-8') ?></h5>
    <p class="text-muted small"><?= htmlspecialchars($t('cycles.empty_text', 'Создайте первый цикл для планирования задач.'), ENT_QUOTES, 'UTF-8') ?></p>
    <button class="btn btn-sm crm-btn-primary" onclick="window.openCycleModal(null)">
      <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t('cycles.btn_create', 'Создать цикл'), ENT_QUOTES, 'UTF-8') ?>
    </button>
  </div>

  <!-- Error State -->
  <div id="cycleErrorState" class="text-center py-5 d-none">
    <i class="fa-solid fa-triangle-exclamation fa-3x text-danger mb-3"></i>
    <h5 class="text-danger"><?= htmlspecialchars($t('cycles.error_title', 'Ошибка загрузки'), ENT_QUOTES, 'UTF-8') ?></h5>
    <p id="cycleErrorText" class="text-muted small"><?= htmlspecialchars($t('cycles.error_text', 'Не удалось загрузить список циклов.'), ENT_QUOTES, 'UTF-8') ?></p>
    <button class="btn btn-sm crm-btn-secondary" onclick="window.loadWorkCycles(1)"><?= htmlspecialchars($t('cycles.btn_retry', 'Повторить'), ENT_QUOTES, 'UTF-8') ?></button>
  </div>

  <!-- Cycle List -->
  <div id="cycleListContainer">
    <div id="cycleList" class="crm-card-list"></div>
    <div id="cyclePagination" class="d-flex justify-content-center mt-3"></div>
  </div>
</div>

<!-- Cycle Create/Edit Modal -->
<div class="modal fade" id="cycleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cycleModalTitle"><?= htmlspecialchars($t('cycles.modal_create_title', 'Создать цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div id="cycleModalAlert" class="alert alert-danger d-none"></div>
        <form id="cycleForm" onsubmit="return false;">
          <input type="hidden" id="cycleFormPublicId" value="">
          <input type="hidden" id="cycleFormRowVersion" value="">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_name', 'Название'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="cycleFormTitle" required maxlength="255">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?> <span class="text-danger">*</span></label>
              <select class="form-select" id="cycleFormProject"></select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_start', 'Дата начала'), ENT_QUOTES, 'UTF-8') ?></label>
              <input type="datetime-local" class="form-control" id="cycleFormStartAt">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_end', 'Дата окончания'), ENT_QUOTES, 'UTF-8') ?></label>
              <input type="datetime-local" class="form-control" id="cycleFormEndAt">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_owner', 'Владелец'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="cycleFormOwner"></select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label>
              <select class="form-select" id="cycleFormStatus">
                <option value="planned"><?= htmlspecialchars($t('cycles.form_status_planned', 'Запланирован'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="active"><?= htmlspecialchars($t('cycles.form_status_active', 'Активный'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_goal', 'Цель цикла'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="cycleFormGoal" rows="2" maxlength="65535"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label"><?= htmlspecialchars($t('cycles.form_label_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label>
              <textarea class="form-control" id="cycleFormDescription" rows="3" maxlength="65535"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('cycles.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="cycleFormSubmit" onclick="window.saveWorkCycle()"><?= htmlspecialchars($t('cycles.btn_submit_create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Cycle Detail Modal -->
<div class="modal fade" id="cycleDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cycleDetailTitle"><?= htmlspecialchars($t('cycles.modal_detail_title', 'Цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3" id="cycleDetailTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="cycleOverviewTab" data-bs-toggle="tab" data-bs-target="#cycleOverviewPane" type="button" role="tab"><?= htmlspecialchars($t('cycles.tab_overview', 'Обзор'), ENT_QUOTES, 'UTF-8') ?></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cycleTasksTab" data-bs-toggle="tab" data-bs-target="#cycleTasksPane" type="button" role="tab"><?= htmlspecialchars($t('cycles.tab_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cycleSummaryTab" data-bs-toggle="tab" data-bs-target="#cycleSummaryPane" type="button" role="tab"><?= htmlspecialchars($t('cycles.tab_statistics', 'Статистика'), ENT_QUOTES, 'UTF-8') ?></button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="cycleOverviewPane" role="tabpanel">
            <div id="cycleOverviewContent"></div>
          </div>
          <div class="tab-pane fade" id="cycleTasksPane" role="tabpanel">
            <div id="cycleTasksContent"></div>
          </div>
          <div class="tab-pane fade" id="cycleSummaryPane" role="tabpanel">
            <div id="cycleSummaryContent"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Tasks Modal -->
<div class="modal fade" id="addTasksModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($t('cycles.modal_add_tasks_title', 'Добавить задачи в цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="addTaskSearchInput" placeholder="<?= htmlspecialchars($t('cycles.search_tasks_placeholder', 'Поиск задач по названию или ID...'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div id="addTaskResults" class="crm-card-list" style="max-height:400px;overflow-y:auto;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('cycles.btn_close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="addTasksConfirmBtn" onclick="window.confirmAddTasks()"><?= htmlspecialchars($t('cycles.btn_add_selected', 'Добавить выбранные'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Complete Cycle Modal -->
<div class="modal fade" id="completeCycleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($t('cycles.modal_complete_title', 'Завершить цикл'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('cycles.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
      </div>
      <div class="modal-body">
        <div id="completeCycleSummary"></div>
        <div class="mt-3">
          <label class="form-label"><?= htmlspecialchars($t('cycles.label_unfinished_action', 'Действие с незавершёнными задачами:'), ENT_QUOTES, 'UTF-8') ?></label>
          <select class="form-select" id="completeUnfinishedAction">
            <option value="leave"><?= htmlspecialchars($t('cycles.action_leave', 'Оставить в цикле'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="move"><?= htmlspecialchars($t('cycles.action_move', 'Перенести в другой цикл'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="remove"><?= htmlspecialchars($t('cycles.action_remove', 'Убрать из цикла'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="mt-2 d-none" id="completeTargetCycleContainer">
          <label class="form-label"><?= htmlspecialchars($t('cycles.label_target_cycle', 'Целевой цикл:'), ENT_QUOTES, 'UTF-8') ?></label>
          <select class="form-select" id="completeTargetCycle"></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('cycles.btn_cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-warning" id="completeCycleConfirmBtn" onclick="window.confirmCompleteCycle()"><?= htmlspecialchars($t('cycles.btn_complete', 'Завершить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>

<script src="/web/assets/js/work-cycles.js"></script>
<style>
.crm-cycle-card {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  transition: box-shadow 0.15s ease;
}
.crm-cycle-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.crm-cycle-progress {
  height: 6px;
  border-radius: 3px;
  background: #e9ecef;
}
.crm-cycle-progress-bar {
  height: 100%;
  border-radius: 3px;
  background: #4caf50;
  transition: width 0.3s ease;
}
.crm-cycle-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 500;
}
.crm-cycle-badge-planned { background: #e3f2fd; color: #1565c0; }
.crm-cycle-badge-active { background: #e8f5e9; color: #2e7d32; }
.crm-cycle-badge-completed { background: #f3e5f5; color: #7b1fa2; }
.crm-cycle-badge-archived { background: #f5f5f5; color: #616161; }
</style>
</main></div></div>
