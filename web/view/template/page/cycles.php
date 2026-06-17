<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Циклы<span class="crm-badge crm-badge-secondary-outline ms-2 small fw-normal">Спринты / Итерации</span></h4>
    <div>
      <button class="btn btn-sm crm-btn-primary" onclick="window.openCycleModal(null)">
        <i class="fa-solid fa-plus"></i> Создать цикл
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="row g-2 mb-3">
    <div class="col-auto">
      <select class="form-select form-select-sm" id="cycleProjectFilter" style="min-width:200px;">
        <option value="">Все проекты</option>
      </select>
    </div>
    <div class="col-auto">
      <select class="form-select form-select-sm" id="cycleStatusFilter">
        <option value="">Все статусы</option>
        <option value="planned">Запланированные</option>
        <option value="active">Активные</option>
        <option value="completed">Завершённые</option>
        <option value="archived">Архивные</option>
      </select>
    </div>
    <div class="col-auto">
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" id="cycleSearchInput" placeholder="Поиск циклов..." style="min-width:200px;">
        <button class="btn btn-outline-secondary" type="button" onclick="window.loadWorkCycles(1)"><i class="fa-solid fa-search"></i></button>
      </div>
    </div>
  </div>

  <!-- Loading State -->
  <div id="cycleLoadingState" class="text-center py-5 d-none">
    <div class="spinner-border text-muted" role="status"><span class="visually-hidden">Загрузка...</span></div>
    <div class="text-muted small mt-2">Загрузка циклов...</div>
  </div>

  <!-- Empty State -->
  <div id="cycleEmptyState" class="text-center py-5 d-none">
    <i class="fa-solid fa-rotate fa-3x text-muted mb-3"></i>
    <h5 class="text-muted">Нет циклов</h5>
    <p class="text-muted small">Создайте первый цикл для планирования задач.</p>
    <button class="btn btn-sm crm-btn-primary" onclick="window.openCycleModal(null)">
      <i class="fa-solid fa-plus"></i> Создать цикл
    </button>
  </div>

  <!-- Error State -->
  <div id="cycleErrorState" class="text-center py-5 d-none">
    <i class="fa-solid fa-triangle-exclamation fa-3x text-danger mb-3"></i>
    <h5 class="text-danger">Ошибка загрузки</h5>
    <p id="cycleErrorText" class="text-muted small">Не удалось загрузить список циклов.</p>
    <button class="btn btn-sm crm-btn-secondary" onclick="window.loadWorkCycles(1)">Повторить</button>
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
        <h5 class="modal-title" id="cycleModalTitle">Создать цикл</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="cycleModalAlert" class="alert alert-danger d-none"></div>
        <form id="cycleForm" onsubmit="return false;">
          <input type="hidden" id="cycleFormPublicId" value="">
          <input type="hidden" id="cycleFormRowVersion" value="">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Название <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="cycleFormTitle" required maxlength="255">
            </div>
            <div class="col-md-6">
              <label class="form-label">Проект <span class="text-danger">*</span></label>
              <select class="form-select" id="cycleFormProject"></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Дата начала</label>
              <input type="datetime-local" class="form-control" id="cycleFormStartAt">
            </div>
            <div class="col-md-6">
              <label class="form-label">Дата окончания</label>
              <input type="datetime-local" class="form-control" id="cycleFormEndAt">
            </div>
            <div class="col-md-6">
              <label class="form-label">Владелец</label>
              <select class="form-select" id="cycleFormOwner"></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Статус</label>
              <select class="form-select" id="cycleFormStatus">
                <option value="planned">Запланирован</option>
                <option value="active">Активный</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Цель цикла</label>
              <textarea class="form-control" id="cycleFormGoal" rows="2" maxlength="65535"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Описание</label>
              <textarea class="form-control" id="cycleFormDescription" rows="3" maxlength="65535"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="cycleFormSubmit" onclick="window.saveWorkCycle()">Создать</button>
      </div>
    </div>
  </div>
</div>

<!-- Cycle Detail Modal -->
<div class="modal fade" id="cycleDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cycleDetailTitle">Цикл</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3" id="cycleDetailTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="cycleOverviewTab" data-bs-toggle="tab" data-bs-target="#cycleOverviewPane" type="button" role="tab">Обзор</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cycleTasksTab" data-bs-toggle="tab" data-bs-target="#cycleTasksPane" type="button" role="tab">Задачи</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cycleSummaryTab" data-bs-toggle="tab" data-bs-target="#cycleSummaryPane" type="button" role="tab">Статистика</button>
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
        <h5 class="modal-title">Добавить задачи в цикл</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="addTaskSearchInput" placeholder="Поиск задач по названию или ID...">
        </div>
        <div id="addTaskResults" class="crm-card-list" style="max-height:400px;overflow-y:auto;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal">Закрыть</button>
        <button type="button" class="btn btn-sm crm-btn-primary" id="addTasksConfirmBtn" onclick="window.confirmAddTasks()">Добавить выбранные</button>
      </div>
    </div>
  </div>
</div>

<!-- Complete Cycle Modal -->
<div class="modal fade" id="completeCycleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Завершить цикл</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="completeCycleSummary"></div>
        <div class="mt-3">
          <label class="form-label">Действие с незавершёнными задачами:</label>
          <select class="form-select" id="completeUnfinishedAction">
            <option value="leave">Оставить в цикле</option>
            <option value="move">Перенести в другой цикл</option>
            <option value="remove">Убрать из цикла</option>
          </select>
        </div>
        <div class="mt-2 d-none" id="completeTargetCycleContainer">
          <label class="form-label">Целевой цикл:</label>
          <select class="form-select" id="completeTargetCycle"></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-sm btn-warning" id="completeCycleConfirmBtn" onclick="window.confirmCompleteCycle()">Завершить</button>
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
