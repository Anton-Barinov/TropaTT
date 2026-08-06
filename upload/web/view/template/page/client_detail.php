<?php declare(strict_types=1); ?>
<?php $title = $t('client_detail.title', 'TropaTT — Карточка клиента'); ?>
<body data-page="clients" data-protected="1"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-client-detail-page"><div class="crm-page-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=dashboard" data-i18n="page.dashboard"><?= htmlspecialchars($t('page.dashboard', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item"><a href="index.php?route=clients" data-i18n="clients.page_title"><?= htmlspecialchars($t('clients.page_title', 'Клиенты'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" data-i18n="client_detail.breadcrumb"><?= htmlspecialchars($t('client_detail.breadcrumb', 'Карточка клиента'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" id="clientDetailTitle" data-i18n="client_detail.loading_title"><?= htmlspecialchars($t('client_detail.loading_title', 'Загрузка клиента...'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" id="clientDetailSubtitle" data-i18n="client_detail.loading_subtitle"><?= htmlspecialchars($t('client_detail.loading_subtitle', 'Загрузка параметров клиента...'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="d-flex gap-2 flex-wrap"><button class="btn crm-btn-primary" id="clientDetailCreateTaskBtn" type="button" data-i18n="client_detail.btn_create_task"><?= htmlspecialchars($t('client_detail.btn_create_task', 'Создать задачу'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-secondary" id="clientDetailCreateProjectBtn" type="button" data-i18n="client_detail.btn_create_project"><?= htmlspecialchars($t('client_detail.btn_create_project', 'Создать проект'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-secondary" id="clientDetailEditBtn" type="button" data-i18n="client_detail.btn_edit"><?= htmlspecialchars($t('client_detail.btn_edit', 'Редактировать клиента'), ENT_QUOTES, 'UTF-8') ?></button><a class="btn crm-btn-secondary" href="index.php?route=clients" data-i18n="client_detail.link_back"><?= htmlspecialchars($t('client_detail.link_back', 'Назад к списку'), ENT_QUOTES, 'UTF-8') ?></a></div></div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="client_detail.section_profile"><?= htmlspecialchars($t('client_detail.section_profile', 'Профиль клиента'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="client_detail.section_profile_note"><?= htmlspecialchars($t('client_detail.section_profile_note', 'Основные реквизиты, контакты и юридическая информация.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="clientDetailProfile"><div class="text-muted" data-i18n="client_detail.loading_profile"><?= htmlspecialchars($t('client_detail.loading_profile', 'Загрузка карточки...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="client_detail.section_tasks"><?= htmlspecialchars($t('client_detail.section_tasks', 'Связанные задачи'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="client_detail.section_tasks_note"><?= htmlspecialchars($t('client_detail.section_tasks_note', 'Последние задачи по этому клиенту.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="table-responsive"><table class="table crm-table mb-0"><thead><tr><th data-i18n="client_detail.th_task"><?= htmlspecialchars($t('client_detail.th_task', 'Задача'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="client_detail.th_status"><?= htmlspecialchars($t('client_detail.th_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="client_detail.th_project"><?= htmlspecialchars($t('client_detail.th_project', 'Проект'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="client_detail.th_updated"><?= htmlspecialchars($t('client_detail.th_updated', 'Обновлена'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody id="clientDetailTasksBody"><tr><td colspan="4" class="text-muted" data-i18n="client_detail.loading_tasks"><?= htmlspecialchars($t('client_detail.loading_tasks', 'Загрузка задач...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="crm-card crm-section-card mb-3" id="clientAiCard" data-requires-ai-use="1" data-ai-state="idle">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="client_detail.section_ai"><?= htmlspecialchars($t('client_detail.section_ai', 'AI по клиенту'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="client_detail.section_ai_note"><?= htmlspecialchars($t('client_detail.section_ai_note', 'Сводка по клиенту с предпросмотром перед любыми действиями.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="d-flex flex-wrap gap-2 mb-2">
        <button class="btn btn-sm crm-btn-primary" type="button" id="clientAiSummaryBtn" data-i18n="client_detail.ai_summary_btn"><?= htmlspecialchars($t('client_detail.ai_summary_btn', 'AI-сводка'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiMeetingPrepBtn" data-i18n="client_detail.ai_meeting_prep_btn"><?= htmlspecialchars($t('client_detail.ai_meeting_prep_btn', 'Подготовка к встрече'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiDataQualityBtn" data-i18n="client_detail.ai_data_quality_btn"><?= htmlspecialchars($t('client_detail.ai_data_quality_btn', 'Качество данных'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiSafeReportBtn" data-i18n="client_detail.ai_safe_report_btn"><?= htmlspecialchars($t('client_detail.ai_safe_report_btn', 'Client-safe отчёт'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm btn-light" type="button" id="clientAiPreviewBtn" disabled data-i18n="client_detail.ai_preview_btn"><?= htmlspecialchars($t('client_detail.ai_preview_btn', 'Предпросмотр'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm crm-btn-muted" type="button" id="clientAiDismissBtn" disabled data-i18n="client_detail.ai_dismiss_btn"><?= htmlspecialchars($t('client_detail.ai_dismiss_btn', 'Отклонить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="small text-muted mb-2" id="clientAiState" data-i18n="client_detail.ai_state_idle"><?= htmlspecialchars($t('client_detail.ai_state_idle', 'AI-сводка клиента не сформирована.'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="crm-metric-tile mb-2"><small class="text-muted d-block" data-i18n="client_detail.ai_summary_label"><?= htmlspecialchars($t('client_detail.ai_summary_label', 'Резюме'), ENT_QUOTES, 'UTF-8') ?></small><div id="clientAiSummaryText" data-i18n="client_detail.ai_summary_empty"><?= htmlspecialchars($t('client_detail.ai_summary_empty', 'Нажмите «AI-сводка», чтобы получить предложение.'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="crm-metric-tile mb-2"><small class="text-muted d-block" data-i18n="client_detail.ai_facts_label"><?= htmlspecialchars($t('client_detail.ai_facts_label', 'Факты из CRM'), ENT_QUOTES, 'UTF-8') ?></small><div id="clientAiFacts">—</div></div>
      <div class="crm-metric-tile"><small class="text-muted d-block" data-i18n="client_detail.ai_inferences_label"><?= htmlspecialchars($t('client_detail.ai_inferences_label', 'AI-инференсы'), ENT_QUOTES, 'UTF-8') ?></small><div id="clientAiInferences">—</div></div>
      <div class="crm-metric-tile mt-2 d-none" id="clientAiReportDraftWrap"><small class="text-muted d-block" data-i18n="client_detail.ai_report_draft_label"><?= htmlspecialchars($t('client_detail.ai_report_draft_label', 'Client-safe draft'), ENT_QUOTES, 'UTF-8') ?></small><pre class="mb-0 small" id="clientAiReportDraftText"></pre></div>
    </div>
    <div class="crm-card crm-section-card">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="client_detail.section_summary"><?= htmlspecialchars($t('client_detail.section_summary', 'Сводка'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="client_detail.section_summary_note"><?= htmlspecialchars($t('client_detail.section_summary_note', 'Быстрые метрики по карточке клиента.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="clientDetailSummary"><div class="text-muted" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="client_detail.section_knowledge"><?= htmlspecialchars($t('client_detail.section_knowledge', 'База знаний'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="client_detail.section_knowledge_note"><?= htmlspecialchars($t('client_detail.section_knowledge_note', 'Связанные страницы и документация клиента.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="clientKnowledgeList"><div class="text-muted small" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="mt-2"><a class="btn btn-sm crm-btn-primary" href="index.php?route=knowledge" data-i18n="client_detail.btn_knowledge"><?= htmlspecialchars($t('client_detail.btn_knowledge', 'Перейти в базу знаний'), ENT_QUOTES, 'UTF-8') ?></a></div>
    </div>
    <div class="crm-card crm-section-card mt-3">
      <div class="crm-section-head"><div><h2 class="h6 mb-0" data-i18n="client_detail.section_extra"><?= htmlspecialchars($t('client_detail.section_extra', 'Дополнительные поля'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="client_detail.section_extra_note"><?= htmlspecialchars($t('client_detail.section_extra_note', 'Кастомные поля клиента из API.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div id="clientDetailExtra" class="small" data-i18n="page.loading"><?= htmlspecialchars($t('page.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</div>
</main></div></div>

<div class="modal fade" id="clientDetailEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" data-i18n="client_detail.edit_modal_title"><?= htmlspecialchars($t('client_detail.edit_modal_title', 'Редактировать клиента'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div><form id="clientDetailEditForm"><div class="modal-body"><input type="hidden" name="public_id"><div class="row g-3">
  <div class="col-md-8"><label class="form-label" data-i18n="clients.field_title"><?= htmlspecialchars($t('clients.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="title" maxlength="255" required></div>
  <div class="col-md-4"><label class="form-label" data-i18n="clients.field_type"><?= htmlspecialchars($t('clients.field_type', 'Тип клиента'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" name="client_type"><option value="individual" data-i18n="clients.type_individual"><?= htmlspecialchars($t('clients.type_individual', 'Физлицо'), ENT_QUOTES, 'UTF-8') ?></option><option value="sole_proprietor" data-i18n="clients.type_sole_proprietor"><?= htmlspecialchars($t('clients.type_sole_proprietor', 'ИП'), ENT_QUOTES, 'UTF-8') ?></option><option value="legal_entity" data-i18n="clients.type_legal_entity"><?= htmlspecialchars($t('clients.type_legal_entity', 'Юрлицо'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
  <div class="col-md-6"><label class="form-label" data-i18n="clients.field_legal_name"><?= htmlspecialchars($t('clients.field_legal_name', 'Юридическое наименование / ФИО ИП'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="legal_name" maxlength="255"></div>
  <div class="col-md-3"><label class="form-label" data-i18n="clients.field_inn"><?= htmlspecialchars($t('clients.field_inn', 'ИНН'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="tax_inn" maxlength="12"></div>
  <div class="col-md-3"><label class="form-label" data-i18n="clients.field_status"><?= htmlspecialchars($t('clients.field_status', 'Статус'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="status" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="clients.field_email"><?= htmlspecialchars($t('clients.field_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="email" name="email" maxlength="190"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="clients.field_phone"><?= htmlspecialchars($t('clients.field_phone', 'Телефон'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="phone" maxlength="64"></div>
  <div class="col-md-4"><label class="form-label" data-i18n="clients.field_website"><?= htmlspecialchars($t('clients.field_website', 'Сайт'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="website" maxlength="2048"></div>
  <div class="col-12"><label class="form-label" data-i18n="clients.field_notes"><?= htmlspecialchars($t('clients.field_notes', 'Комментарий'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="notes" rows="3"></textarea></div>
  <div class="col-12"><label class="form-label" data-i18n="clients.field_extra_attributes"><?= htmlspecialchars($t('clients.field_extra_attributes', 'Дополнительные поля (JSON)'), ENT_QUOTES, 'UTF-8') ?></label><div class="form-text mb-1" data-i18n="clients.extra_attributes_hint"><?= htmlspecialchars($t('clients.extra_attributes_hint', 'Укажите пары «поле — значение» в JSON. После сохранения они видны в карточке и доступны для поиска.'), ENT_QUOTES, 'UTF-8') ?></div><textarea class="form-control" name="extra_attributes_text" rows="3"></textarea></div>
</div></div><div class="modal-footer"><button class="btn crm-btn-secondary" type="button" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button class="btn crm-btn-primary" type="submit" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button></div></form></div></div></div>
<script>
(function () {
  var urlParams = new URLSearchParams(window.location.search);
  var clientId = urlParams.get('client_public_id') || urlParams.get('id');
  if (!clientId) return;
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(cb, n) {
    if (getApi()) { cb(); return; }
    if ((n || 0) > 80) return;
    window.setTimeout(function () { waitForApi(cb, (n || 0) + 1); }, 50);
  }
  waitForApi(async function () {
    var api = getApi();
    var listEl = document.getElementById('clientKnowledgeList');
    if (!listEl) return;
    try {
      var envelope = await api.request('api/v1/knowledge/entities/client/' + encodeURIComponent(clientId) + '/pages', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      if (!items.length) {
        listEl.innerHTML = '<div class="text-muted small"><?= htmlspecialchars($t('client_detail.knowledge_empty', 'Нет связанных страниц'), ENT_QUOTES, 'UTF-8') ?></div>';
      } else {
        listEl.innerHTML = '<ul class="list-unstyled mb-0">' + items.map(function (p) {
          return '<li class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + encodeURIComponent(p.public_id) + '">' + (function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch]; }); })(p.title || '') + '</a> <span class="text-muted small">(' + (function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch]; }); })(p.relation_type || 'related') + ')</span></li>';
        }).join('') + '</ul>';
      }
    } catch (e) {
      listEl.innerHTML = '<div class="text-muted small">—</div>';
    }
  });
})();
</script>
</body>
