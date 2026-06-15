<?php declare(strict_types=1); ?>
<?php $title = $t('knowledge_page.title', 'TropaTT — Материал базы знаний'); ?>
<?php $pageId = (string)($_GET['id'] ?? ''); ?>
<body data-page="knowledge-page" data-protected="1" data-knowledge-page-id="<?= htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8') ?>"><div class="crm-app">
<aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-knowledge-page-detail"><?php crm_page_head([
  ['label' => $t('page.home', 'Главная'), 'href' => 'index.php?route=dashboard'],
  ['label' => $t('knowledge.page_title', 'База знаний'), 'href' => 'index.php?route=knowledge'],
  ['label' => $t('knowledge_page.page_title', 'Материал'), 'active' => true],
], $t('knowledge_page.page_title', 'Материал'), $t('knowledge_page.subtitle', 'Просмотр, редактирование и публикация знаний команды.'), '<div class="d-flex gap-2 flex-wrap"><a class="btn crm-btn-secondary btn-sm" href="index.php?route=knowledge"><i class="fa-solid fa-arrow-left" style="margin-right:0.3rem"></i>' . htmlspecialchars($t('knowledge.back_to_list', 'К базе знаний'), ENT_QUOTES, 'UTF-8') . '</a><button class="btn crm-btn-secondary btn-sm" type="button" id="knowledgeEditBtn"><i class="fa-solid fa-pen" style="margin-right:0.3rem"></i>' . htmlspecialchars($t('knowledge_page.btn_edit', 'Редактировать'), ENT_QUOTES, 'UTF-8') . '</button></div>'); ?>

<div class="crm-knowledge-detail-layout">
  <div class="crm-knowledge-center">
    <aside class="crm-card crm-section-card crm-knowledge-tree crm-knowledge-tree-sidebar">
      <h3 class="h6"><?= htmlspecialchars($t('knowledge_page.tree_title', 'Страницы раздела'), ENT_QUOTES, 'UTF-8') ?></h3>
      <div id="knowledgeTree" class="crm-knowledge-tree-list"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
    </aside>
    <article class="crm-card crm-section-card crm-knowledge-article">
      <div id="knowledgePageState" class="text-muted p-3"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div>
      <div id="knowledgePageView" class="d-none">
        <div class="crm-knowledge-article-head">
          <div><div class="crm-eyebrow" id="knowledgePageSpace"></div><h2 id="knowledgePageTitle"></h2></div>
          <span class="crm-badge" id="knowledgePageStatus"></span>
        </div>
        <div class="crm-knowledge-content" id="knowledgePageContent"></div>
      </div>
      <form id="knowledgePageEditor" class="d-none">
        <div class="crm-knowledge-writing-topbar">
          <div>
            <div class="crm-knowledge-writing-label"><?= htmlspecialchars($t('knowledge_page.editor_mode_title', 'Редактирование материала'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="small text-muted" id="knowledgeAutosaveStatus"></div>
          </div>
          <div class="crm-knowledge-writing-actions">
            <button class="btn crm-btn-secondary" type="button" id="knowledgeCancelEditBtn"><?= htmlspecialchars($t('common.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn crm-btn-secondary" type="button" id="knowledgeSaveDraftBtn"><?= htmlspecialchars($t('knowledge_page.btn_save_draft', 'Сохранить черновик'), ENT_QUOTES, 'UTF-8') ?></button>
            <button class="btn crm-btn-primary" type="submit"><?= htmlspecialchars($t('knowledge_page.btn_save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </div>
        <div class="crm-knowledge-writing-canvas">
          <div class="crm-knowledge-edit-meta">
            <input id="knowledgeEditTitle" class="crm-knowledge-title-input" name="title" required placeholder="<?= htmlspecialchars($t('knowledge.field_title', 'Название'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="crm-knowledge-edit-review">
              <label class="small text-muted"><?= htmlspecialchars($t('knowledge_page.review_due_at_label', 'Проверка до'), ENT_QUOTES, 'UTF-8') ?></label>
              <input id="knowledgeEditReviewDue" class="form-control form-control-sm" type="date" name="review_due_at" style="width:200px">
            </div>
          </div>
          <div class="crm-knowledge-toolbar" role="toolbar" aria-label="<?= htmlspecialchars($t('knowledge_page.editor_toolbar', 'Панель форматирования'), ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="bold" title="<?= htmlspecialchars($t('knowledge_page.bold', 'Жирный'), ENT_QUOTES, 'UTF-8') ?>"><b>B</b></button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="italic" title="<?= htmlspecialchars($t('knowledge_page.italic', 'Курсив'), ENT_QUOTES, 'UTF-8') ?>"><i>I</i></button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="h2" title="<?= htmlspecialchars($t('knowledge_page.h2', 'Заголовок H2'), ENT_QUOTES, 'UTF-8') ?>">H2</button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="h3" title="<?= htmlspecialchars($t('knowledge_page.h3', 'Заголовок H3'), ENT_QUOTES, 'UTF-8') ?>">H3</button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="ul" title="<?= htmlspecialchars($t('knowledge_page.ul', 'Список'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-list-ul"></i></button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="ol" title="<?= htmlspecialchars($t('knowledge_page.ol', 'Нумерованный список'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-list-ol"></i></button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="blockquote" title="<?= htmlspecialchars($t('knowledge_page.blockquote', 'Цитата'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-quote-right"></i></button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="code" title="<?= htmlspecialchars($t('knowledge_page.code', 'Код'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-code"></i></button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="link" title="<?= htmlspecialchars($t('knowledge_page.link', 'Ссылка'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-link"></i></button>
            <button type="button" class="btn btn-sm crm-btn-secondary" data-editor-cmd="checklist" title="<?= htmlspecialchars($t('knowledge_page.checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-check-square"></i></button>
          </div>
          <div class="crm-knowledge-visual-wrap">
            <button class="crm-knowledge-block-add" type="button" id="knowledgeBlockAddBtn" aria-label="<?= htmlspecialchars($t('knowledge_page.block_add', 'Добавить блок'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-plus"></i></button>
            <div id="knowledgeVisualEditor" class="crm-knowledge-visual-editor" contenteditable="true" spellcheck="true" data-placeholder="<?= htmlspecialchars($t('knowledge_page.editor_placeholder', 'Начните писать материал...'), ENT_QUOTES, 'UTF-8') ?>"></div>
            <div id="knowledgeBlockMenu" class="crm-knowledge-block-menu d-none">
              <label class="crm-knowledge-block-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input id="knowledgeBlockSearch" type="search" placeholder="<?= htmlspecialchars($t('knowledge_page.block_search', 'Найти блок'), ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <button type="button" data-editor-block="p"><i class="fa-solid fa-align-left"></i><span><?= htmlspecialchars($t('knowledge_page.block_text', 'Текст'), ENT_QUOTES, 'UTF-8') ?></span></button>
              <button type="button" data-editor-block="h2"><strong>H2</strong><span><?= htmlspecialchars($t('knowledge_page.block_h2', 'Подзаголовок H2'), ENT_QUOTES, 'UTF-8') ?></span></button>
              <button type="button" data-editor-block="h3"><strong>H3</strong><span><?= htmlspecialchars($t('knowledge_page.block_h3', 'Подзаголовок H3'), ENT_QUOTES, 'UTF-8') ?></span></button>
              <button type="button" data-editor-block="ul"><i class="fa-solid fa-list-ul"></i><span><?= htmlspecialchars($t('knowledge_page.ul', 'Список'), ENT_QUOTES, 'UTF-8') ?></span></button>
              <button type="button" data-editor-block="blockquote"><i class="fa-solid fa-quote-right"></i><span><?= htmlspecialchars($t('knowledge_page.blockquote', 'Цитата'), ENT_QUOTES, 'UTF-8') ?></span></button>
              <button type="button" data-editor-block="link"><i class="fa-solid fa-link"></i><span><?= htmlspecialchars($t('knowledge_page.link', 'Ссылка'), ENT_QUOTES, 'UTF-8') ?></span></button>
            </div>
          </div>
          <textarea id="knowledgeEditContent" class="crm-knowledge-editor-source" name="content_html" rows="1" tabindex="-1" aria-hidden="true"></textarea>
        </div>
      </form>
    </article>
    <section id="knowledgeCommentsSection" class="crm-card crm-section-card crm-knowledge-comments crm-knowledge-work-card">
      <div class="crm-section-head"><h3 class="h5 mb-0"><?= htmlspecialchars($t('knowledge_page.comments_title', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?></h3></div>
      <div id="knowledgeCommentsList"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="crm-knowledge-comment-form">
        <div id="knowledgeCommentReplyIndicator" class="small text-muted d-none" style="margin-bottom:6px">
          <span id="knowledgeCommentReplyLabel"></span>
          <button type="button" class="btn btn-sm crm-btn-secondary" id="knowledgeCommentCancelReply" style="font-size:0.7rem;margin-left:8px"><?= htmlspecialchars($t('knowledge_page.comments_cancel_reply', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <textarea id="knowledgeCommentInput" class="form-control" rows="3" placeholder="<?= htmlspecialchars($t('knowledge_page.comments_placeholder', 'Напишите комментарий...'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
        <button class="btn crm-btn-primary" type="button" id="knowledgeCommentSendBtn"><?= htmlspecialchars($t('knowledge_page.comments_send', 'Отправить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </section>
    <section id="knowledgeAttachmentsSection" class="crm-card crm-section-card crm-knowledge-attachments crm-knowledge-work-card">
      <div class="crm-section-head"><h3 class="h5 mb-0"><?= htmlspecialchars($t('knowledge_page.attachments_title', 'Файлы'), ENT_QUOTES, 'UTF-8') ?></h3></div>
      <div id="knowledgeAttachmentsList"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="crm-knowledge-file-form">
        <input type="file" id="knowledgeFileInput" class="form-control" multiple>
        <button class="btn crm-btn-primary" type="button" id="knowledgeFileUploadBtn"><?= htmlspecialchars($t('knowledge_page.attachments_upload', 'Загрузить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </section>
  </div>
  <aside class="crm-card crm-section-card crm-knowledge-side">
    <div class="crm-knowledge-side-actions">
      <button class="btn crm-btn-primary btn-sm w-100" type="button" id="knowledgePublishBtn"><?= htmlspecialchars($t('knowledge_page.btn_publish', 'Опубликовать'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary btn-sm w-100" type="button" id="knowledgeReviewBtn"><?= htmlspecialchars($t('knowledge_page.btn_request_review', 'На проверку'), ENT_QUOTES, 'UTF-8') ?></button>
      <div class="dropdown w-100">
        <button class="btn crm-btn-secondary btn-sm w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-download" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_export', 'Экспорт'), ENT_QUOTES, 'UTF-8') ?></button>
        <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="#" data-export-format="json">JSON</a></li><li><a class="dropdown-item" href="#" data-export-format="markdown">Markdown</a></li></ul>
      </div>
    </div>
    <hr>
    <dl class="crm-knowledge-meta">
      <dt><?= htmlspecialchars($t('knowledge.field_space', 'Раздел'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaSpace">—</dd>
      <dt><?= htmlspecialchars($t('knowledge.field_type', 'Тип'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaType">—</dd>
      <dt><?= htmlspecialchars($t('knowledge_page.updated_at', 'Обновлено'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaUpdated">—</dd>
      <dt><?= htmlspecialchars($t('knowledge_page.views', 'Просмотры'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaViews">0</dd>
      <dt><?= htmlspecialchars($t('knowledge_page.review_due_at_label', 'Проверка до'), ENT_QUOTES, 'UTF-8') ?></dt><dd id="knowledgeMetaReviewDue">—</dd>
    </dl>
    <hr>
    <div class="crm-knowledge-side-tags">
      <h3 class="h6 mb-2"><?= htmlspecialchars($t('knowledge_page.tags_title', 'Теги'), ENT_QUOTES, 'UTF-8') ?></h3>
      <div id="knowledgeTagsList" class="mb-2 d-flex flex-wrap gap-1"><span class="text-muted small">—</span></div>
      <div class="input-group input-group-sm">
        <select id="knowledgeTagSelect" class="form-select" style="min-width:80px"><option value=""><?= htmlspecialchars($t('knowledge_page.tag_select_hint', 'Выбрать тег...'), ENT_QUOTES, 'UTF-8') ?></option></select>
        <button class="btn crm-btn-primary" type="button" id="knowledgeTagAddBtn" disabled>+</button>
      </div>
    </div>
    <hr>
    <div class="d-flex gap-2">
      <button class="btn btn-sm crm-btn-secondary flex-1" type="button" id="knowledgeFavBtn"><i class="fa-regular fa-star" style="margin-right:0.25rem"></i><?= htmlspecialchars($t('knowledge_page.favorite_add', 'В избранное'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn btn-sm crm-btn-secondary flex-1" type="button" id="knowledgeSubBtn"><i class="fa-regular fa-bell" style="margin-right:0.25rem"></i><?= htmlspecialchars($t('knowledge_page.subscribe', 'Подписаться'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="knowledgeTocContainer" class="d-none">
      <hr>
      <h3 class="h6"><?= htmlspecialchars($t('knowledge_page.toc_title', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></h3>
      <nav id="knowledgeToc" class="crm-knowledge-toc"></nav>
    </div>
    <div id="knowledgeAiSection">
      <hr>
      <h3 class="h6"><?= htmlspecialchars($t('knowledge_page.ai_title', 'AI'), ENT_QUOTES, 'UTF-8') ?></h3>
      <div class="d-grid gap-1">
        <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgeAiSummaryBtn"><i class="fa-solid fa-wand-magic-sparkles" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_ai_summary', 'Краткое содержание'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgeAiExplainBtn"><i class="fa-solid fa-lightbulb" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_ai_explain', 'Объяснить проще'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgeAiChecklistBtn"><i class="fa-solid fa-list-check" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_ai_checklist', 'Чеклист'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgeAiSimilarBtn"><i class="fa-solid fa-copy" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_ai_similar', 'Похожие страницы'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgeAiFaqBtn"><i class="fa-solid fa-circle-question" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_ai_faq', 'FAQ из комментариев'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div id="knowledgeAiResult" class="crm-knowledge-ai-result d-none mt-2">
        <div id="knowledgeAiResultTitle" class="fw-bold small mb-1"></div>
        <div id="knowledgeAiResultBody" class="small text-muted crm-knowledge-ai-body"></div>
      </div>
    </div>
    <hr>
    <div class="crm-knowledge-side-meta-collapsed">
      <h3 class="h6 mb-2"><?= htmlspecialchars($t('knowledge_page.versions_title', 'Версии'), ENT_QUOTES, 'UTF-8') ?></h3>
      <div id="knowledgeVersions" class="crm-knowledge-version-list"><div class="text-muted small"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <div id="knowledgeDiffContainer" class="d-none mt-2"><h4 class="h6"><?= htmlspecialchars($t('knowledge_page.diff_title', 'Сравнение'), ENT_QUOTES, 'UTF-8') ?></h4><div id="knowledgeDiffContent" class="crm-knowledge-diff small"></div></div>
    </div>
    <hr>
    <div class="d-grid gap-2">
      <button class="btn btn-sm crm-btn-secondary" type="button" id="knowledgePermissionsBtn"><i class="fa-solid fa-shield-halved" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_permissions', 'Доступ'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn btn-sm crm-btn-danger-soft" type="button" id="knowledgeArchiveBtn"><i class="fa-solid fa-box-archive" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.btn_archive', 'В архив'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </aside>
</div>
</main></div></div>

<div class="modal fade" id="knowledgePagePermissionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?= htmlspecialchars($t('knowledge_page.permissions_title', 'Права доступа к материалу'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button></div>
    <div class="modal-body">
      <div id="knowledgePagePermList" class="mb-3"><div class="text-muted"><?= htmlspecialchars($t('knowledge.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></div></div>
      <hr>
      <h6><?= htmlspecialchars($t('admin_knowledge.permissions_add_title', 'Добавить доступ'), ENT_QUOTES, 'UTF-8') ?></h6>
      <div class="row g-2">
        <div class="col-md-4">
          <select id="knowledgePagePermSubjectType" class="form-select">
            <option value="user"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_user', 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="role"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_role', 'Роль'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="team"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_team', 'Команда'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="department"><?= htmlspecialchars($t('admin_knowledge.permissions_subject_department', 'Отдел'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="col-md-4">
          <select id="knowledgePagePermSubjectId" class="form-select" style="max-width:100%"><option value=""><?= htmlspecialchars($t('admin_knowledge.permissions_select_subject', 'Выберите...'), ENT_QUOTES, 'UTF-8') ?></option></select>
        </div>
        <div class="col-md-2">
          <select id="knowledgePagePermAccessLevel" class="form-select">
            <option value="view"><?= htmlspecialchars($t('admin_knowledge.permissions_level_view', 'Просмотр'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="comment"><?= htmlspecialchars($t('admin_knowledge.permissions_level_comment', 'Комментирование'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="edit"><?= htmlspecialchars($t('admin_knowledge.permissions_level_edit', 'Редактирование'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="manage"><?= htmlspecialchars($t('admin_knowledge.permissions_level_manage', 'Управление'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn crm-btn-primary w-100" type="button" id="knowledgePagePermAddBtn"><?= htmlspecialchars($t('common.add', 'Добавить'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </div></div>
</div>

<!-- AI Result Modal -->
<div class="modal fade" id="knowledgeAiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="knowledgeAiModalTitle"><?= htmlspecialchars($t('knowledge_page.ai_title', 'AI'), ENT_QUOTES, 'UTF-8') ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>"></button>
    </div>
    <div class="modal-body">
      <div id="knowledgeAiModalLoader" class="crm-ai-loader d-none">
        <div class="crm-ai-loader-spinner"><div class="crm-spinner"></div></div>
        <div class="crm-ai-loader-text"><?= htmlspecialchars($t('knowledge_page.ai_loading_title', 'AI обрабатывает...'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div id="knowledgeAiModalBody" class="crm-knowledge-ai-modal-body"></div>
    </div>
    <div class="modal-footer" id="knowledgeAiModalFooter">
      <button class="btn crm-btn-secondary" type="button" id="knowledgeAiModalCopyBtn"><i class="fa-regular fa-clipboard" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.ai_copy', 'Копировать'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" type="button" id="knowledgeAiModalInsertBtn" style="display:none"><i class="fa-solid fa-file-import" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.ai_insert', 'Вставить в материал'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" type="button" id="knowledgeAiModalRefreshBtn"><i class="fa-solid fa-rotate" style="margin-right:0.3rem"></i><?= htmlspecialchars($t('knowledge_page.ai_refresh', 'Обновить'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="btn crm-btn-primary" data-bs-dismiss="modal"><?= htmlspecialchars($t('common.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div></div>
</div>

<script>
(function () {
  var pageId = document.body.getAttribute('data-knowledge-page-id') || '';
  var i18n = window.CRM && window.CRM.i18n;
  var t = function (key, fallback) { return i18n && i18n.t ? i18n.t(key, fallback) : fallback; };
  var current = null, savedEditorRange = null;
  var isFav = false, isSub = false, autoTimer = null;
  var els = {
    state: document.getElementById('knowledgePageState'),
    view: document.getElementById('knowledgePageView'),
    editor: document.getElementById('knowledgePageEditor'),
    title: document.getElementById('knowledgePageTitle'),
    space: document.getElementById('knowledgePageSpace'),
    status: document.getElementById('knowledgePageStatus'),
    content: document.getElementById('knowledgePageContent'),
    editTitle: document.getElementById('knowledgeEditTitle'),
    editContent: document.getElementById('knowledgeEditContent'),
    editPreview: document.getElementById('knowledgeEditorPreview'),
    editReviewDue: document.getElementById('knowledgeEditReviewDue'),
    metaReviewDue: document.getElementById('knowledgeMetaReviewDue'),
    visualEditor: document.getElementById('knowledgeVisualEditor'),
    blockAddBtn: document.getElementById('knowledgeBlockAddBtn'),
    blockMenu: document.getElementById('knowledgeBlockMenu'),
    blockSearch: document.getElementById('knowledgeBlockSearch'),
    metaSpace: document.getElementById('knowledgeMetaSpace'),
    metaType: document.getElementById('knowledgeMetaType'),
    metaUpdated: document.getElementById('knowledgeMetaUpdated'),
    metaViews: document.getElementById('knowledgeMetaViews'),
    versions: document.getElementById('knowledgeVersions'),
    tree: document.getElementById('knowledgeTree'),
    favBtn: document.getElementById('knowledgeFavBtn'),
    subBtn: document.getElementById('knowledgeSubBtn'),
    commentsList: document.getElementById('knowledgeCommentsList'),
    commentInput: document.getElementById('knowledgeCommentInput'),
    commentSend: document.getElementById('knowledgeCommentSendBtn'),
    diffContainer: document.getElementById('knowledgeDiffContainer'),
    diffContent: document.getElementById('knowledgeDiffContent'),
    autosaveStatus: document.getElementById('knowledgeAutosaveStatus'),
    attachList: document.getElementById('knowledgeAttachmentsList'),
    attachInput: document.getElementById('knowledgeFileInput'),
    attachUploadBtn: document.getElementById('knowledgeFileUploadBtn'),
    tagsList: document.getElementById('knowledgeTagsList'),
    tagSelect: document.getElementById('knowledgeTagSelect'),
    tagAddBtn: document.getElementById('knowledgeTagAddBtn'),
    tocContainer: document.getElementById('knowledgeTocContainer'),
    toc: document.getElementById('knowledgeToc'),
    permBtn: document.getElementById('knowledgePermissionsBtn'),
    permModal: document.getElementById('knowledgePagePermissionsModal'),
    permList: document.getElementById('knowledgePagePermList'),
    permSubjectType: document.getElementById('knowledgePagePermSubjectType'),
    permSubjectId: document.getElementById('knowledgePagePermSubjectId'),
    permAccessLevel: document.getElementById('knowledgePagePermAccessLevel'),
    permAddBtn: document.getElementById('knowledgePagePermAddBtn')
  };
  function editorWrapTag(ta, before, after) {
    var start = ta.selectionStart, end = ta.selectionEnd;
    var text = ta.value;
    var selected = text.substring(start, end) || '';
    ta.value = text.substring(0, start) + before + selected + after + text.substring(end);
    ta.selectionStart = ta.selectionEnd = start + before.length + selected.length;
    ta.focus();
    ta.dispatchEvent(new Event('input', {bubbles: true}));
  }
  function editorInsertLineBefore(ta, prefix) {
    var start = ta.selectionStart;
    var text = ta.value;
    var lineStart = text.lastIndexOf('\n', start - 1) + 1;
    var lineEnd = text.indexOf('\n', start);
    if (lineEnd === -1) lineEnd = text.length;
    var line = text.substring(lineStart, lineEnd);
    ta.value = text.substring(0, lineStart) + prefix + line + '\n' + text.substring(lineEnd);
    ta.selectionStart = ta.selectionEnd = lineStart + prefix.length + line.length + 1;
    ta.focus();
    ta.dispatchEvent(new Event('input', {bubbles: true}));
  }
  function syncHiddenContent() {
    if (!els.editContent || !els.visualEditor) return;
    els.editContent.value = sanitizePreviewHtml(els.visualEditor.innerHTML || '');
  }
  function rememberEditorSelection() {
    if (!els.visualEditor || !window.getSelection) return;
    var selection = window.getSelection();
    if (!selection || !selection.rangeCount) return;
    var range = selection.getRangeAt(0);
    if (els.visualEditor.contains(range.commonAncestorContainer)) savedEditorRange = range.cloneRange();
  }
  function restoreEditorSelection() {
    if (!savedEditorRange || !window.getSelection) return;
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedEditorRange);
  }
  function focusVisualEditor() {
    if (!els.visualEditor) return;
    els.visualEditor.focus();
    restoreEditorSelection();
  }
  function openBlockMenu() {
    if (!els.blockMenu) return;
    rememberEditorSelection();
    els.blockMenu.classList.remove('d-none');
    if (els.blockSearch) {
      els.blockSearch.value = '';
      filterBlockMenu('');
      window.setTimeout(function () { els.blockSearch.focus(); }, 20);
    }
  }
  function closeBlockMenu() {
    if (els.blockMenu) els.blockMenu.classList.add('d-none');
    focusVisualEditor();
  }
  function filterBlockMenu(query) {
    if (!els.blockMenu) return;
    var needle = String(query || '').toLowerCase().trim();
    els.blockMenu.querySelectorAll('[data-editor-block]').forEach(function (btn) {
      var text = (btn.textContent || '').toLowerCase();
      btn.style.display = !needle || text.indexOf(needle) !== -1 ? '' : 'none';
    });
  }
  function insertVisualHtml(html) {
    focusVisualEditor();
    document.execCommand('insertHTML', false, html);
    syncHiddenContent();
    startAutosave();
  }
  function runVisualCommand(cmd) {
    if (!els.visualEditor) return false;
    focusVisualEditor();
    switch (cmd) {
      case 'bold': document.execCommand('bold', false, null); break;
      case 'italic': document.execCommand('italic', false, null); break;
      case 'h2': document.execCommand('formatBlock', false, 'h2'); break;
      case 'h3': document.execCommand('formatBlock', false, 'h3'); break;
      case 'ul': document.execCommand('insertUnorderedList', false, null); break;
      case 'ol': document.execCommand('insertOrderedList', false, null); break;
      case 'blockquote': document.execCommand('formatBlock', false, 'blockquote'); break;
      case 'code': insertVisualHtml('<code>' + esc(window.getSelection ? window.getSelection().toString() || t('knowledge_page.code', 'Код') : t('knowledge_page.code', 'Код')) + '</code>'); return true;
      case 'link': {
        var url = prompt(t('knowledge_page.link_prompt', 'Введите URL:'), 'https://');
        if (url) document.execCommand('createLink', false, url);
        break;
      }
      case 'checklist': insertVisualHtml('<p><label><input type="checkbox"> ' + esc(t('knowledge_page.checklist', 'Чеклист')) + '</label></p>'); return true;
      default: return false;
    }
    syncHiddenContent();
    startAutosave();
    updateToolbarState();
    return true;
  }
  function updateToolbarState() {
    if (!els.visualEditor || els.editor.classList.contains('d-none')) return;
    var map = {
      bold: 'bold',
      italic: 'italic',
      ul: 'insertUnorderedList',
      ol: 'insertOrderedList'
    };
    document.querySelectorAll('[data-editor-cmd]').forEach(function (btn) {
      var cmd = btn.getAttribute('data-editor-cmd');
      var active = false;
      try {
        if (map[cmd]) active = document.queryCommandState(map[cmd]);
        if (cmd === 'h2' || cmd === 'h3' || cmd === 'blockquote') {
          var block = String(document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
          active = block === cmd || (cmd === 'blockquote' && block === 'blockquote');
        }
      } catch (e) {}
      btn.classList.toggle('is-active', active);
    });
  }
  document.querySelectorAll('[data-editor-cmd]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cmd = btn.getAttribute('data-editor-cmd');
      if (runVisualCommand(cmd)) return;
      if (!els.editContent) return;
      var ta = els.editContent;
      switch (cmd) {
        case 'bold': editorWrapTag(ta, '<strong>', '</strong>'); break;
        case 'italic': editorWrapTag(ta, '<em>', '</em>'); break;
        case 'h2': editorInsertLineBefore(ta, '<h2>'); editorWrapTag(ta, '', '</h2>'); break;
        case 'h3': editorInsertLineBefore(ta, '<h3>'); editorWrapTag(ta, '', '</h3>'); break;
        case 'ul': editorInsertLineBefore(ta, '<ul><li>'); editorWrapTag(ta, '', '</li></ul>'); break;
        case 'ol': editorInsertLineBefore(ta, '<ol><li>'); editorWrapTag(ta, '', '</li></ol>'); break;
        case 'blockquote': editorInsertLineBefore(ta, '<blockquote><p>'); editorWrapTag(ta, '', '</p></blockquote>'); break;
        case 'code': editorWrapTag(ta, '<code>', '</code>'); break;
        case 'link': {
          var url = prompt(t('knowledge_page.link_prompt', 'Введите URL:'), 'https://');
          if (url) editorWrapTag(ta, '<a href="' + esc(url) + '">', '</a>');
          break;
        }
        case 'checklist': editorInsertLineBefore(ta, '<p><input type="checkbox"> '); editorWrapTag(ta, '', '</p>'); break;
      }
    });
  });
  function getApi() {
    return window.CRM && window.CRM.api && typeof window.CRM.api.request === 'function' ? window.CRM.api : null;
  }
  function waitForApi(callback, attempts) {
    if (getApi()) {
      callback();
      return;
    }
    if ((attempts || 0) > 80) {
      els.state.textContent = t('knowledge_page.load_error', 'Не удалось загрузить материал.');
      return;
    }
    window.setTimeout(function () { waitForApi(callback, (attempts || 0) + 1); }, 50);
  }
  async function request(route, options) {
    var api = getApi();
    if (!api) throw new Error('CRM_API_NOT_READY');
    return api.request(route, options);
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }
  function sanitizePreviewHtml(html) {
    var template = document.createElement('template');
    template.innerHTML = String(html || '');
    template.content.querySelectorAll('script, iframe, object, embed').forEach(function (node) { node.remove(); });
    template.content.querySelectorAll('*').forEach(function (node) {
      Array.prototype.slice.call(node.attributes || []).forEach(function (attr) {
        var name = attr.name.toLowerCase();
        var value = String(attr.value || '');
        if (name.indexOf('on') === 0 || ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(value))) {
          node.removeAttribute(attr.name);
        }
      });
    });
    return template.innerHTML;
  }
  function updateEditorPreview() {
    if (!els.editPreview || !els.editContent) return;
    var html = els.editContent.value || '<p class="text-muted">' + esc(t('knowledge_page.empty_content', 'Содержание пока не заполнено.')) + '</p>';
    els.editPreview.innerHTML = sanitizePreviewHtml(html);
  }
  function renderPermissionList(items) {
    if (!els.permList) return;
    if (!items || !items.length) {
      els.permList.innerHTML = '<div class="text-muted">' + esc(t('admin_knowledge.permissions_empty', 'Нет назначенных прав доступа')) + '</div>';
      return;
    }
    var typeMap = {
      user: t('admin_knowledge.permissions_subject_user', 'Пользователь'),
      role: t('admin_knowledge.permissions_subject_role', 'Роль'),
      team: t('admin_knowledge.permissions_subject_team', 'Команда'),
      department: t('admin_knowledge.permissions_subject_department', 'Отдел')
    };
    els.permList.innerHTML = '<table class="table crm-table mb-0"><thead><tr><th>' + esc(t('admin_knowledge.permissions_th_subject', 'Субъект')) + '</th><th>' + esc(t('admin_knowledge.permissions_th_level', 'Уровень')) + '</th><th>' + esc(t('admin_knowledge.permissions_th_created', 'Добавлено')) + '</th><th></th></tr></thead><tbody>' + items.map(function (p) {
      var label = p.user_name || p.role_title || p.team_title || p.department_title || p.user_public_id || p.role_public_id || p.team_public_id || p.department_public_id || p.subject_id;
      var permissionId = p.permission_key || '';
      return '<tr><td><strong>' + esc(typeMap[p.subject_type] || p.subject_type || '') + ': ' + esc(label) + '</strong></td><td>' + esc(p.access_level) + '</td><td class="small text-muted">' + esc(p.created_at || '') + '</td><td><button class="btn btn-sm crm-btn-danger-soft" data-page-perm-delete="' + esc(permissionId) + '">' + esc(t('common.delete', 'Удалить')) + '</button></td></tr>';
    }).join('') + '</tbody></table>';
  }
  async function loadPagePermissions() {
    if (!els.permList || !pageId) return;
    els.permList.innerHTML = '<div class="text-muted">' + esc(t('knowledge.loading', 'Загрузка...')) + '</div>';
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/permissions', { method: 'GET' });
      renderPermissionList(envelope.data && envelope.data.items || []);
    } catch (e) {
      els.permList.innerHTML = '<div class="text-muted">' + esc(t('knowledge.load_error', 'Не удалось загрузить базу знаний.')) + '</div>';
    }
  }
  async function loadPagePermissionSubjects(type) {
    if (!els.permSubjectId) return;
    els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_loading_subjects', 'Загрузка...')) + '</option>';
    try {
      var envelope, items;
      if (type === 'user') {
        envelope = await request('api/v1/users', { method: 'GET', query: { limit: 200 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_user', 'Выберите пользователя...')) + '</option>' + items.map(function (u) {
          var label = u.name || u.full_name || u.login || u.email || u.public_id || '';
          return '<option value="' + esc(u.public_id || u.id || '') + '">' + esc(label) + '</option>';
        }).join('');
      } else if (type === 'role') {
        envelope = await request('api/v1/roles', { method: 'GET', query: { limit: 50 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_role', 'Выберите роль...')) + '</option>' + items.map(function (r) {
          return '<option value="' + esc(r.public_id || r.id || '') + '">' + esc(r.title || r.code || r.public_id || '') + '</option>';
        }).join('');
      } else if (type === 'team') {
        envelope = await request('api/v1/teams', { method: 'GET', query: { limit: 200 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_team', 'Выберите команду...')) + '</option>' + items.map(function (team) {
          return '<option value="' + esc(team.public_id || team.id || '') + '">' + esc(team.title || team.public_id || '') + '</option>';
        }).join('');
      } else if (type === 'department') {
        envelope = await request('api/v1/departments', { method: 'GET', query: { limit: 200 } });
        items = envelope.data && envelope.data.items || [];
        els.permSubjectId.innerHTML = '<option value="">' + esc(t('admin_knowledge.permissions_select_department', 'Выберите отдел...')) + '</option>' + items.map(function (department) {
          return '<option value="' + esc(department.public_id || department.id || '') + '">' + esc(department.title || department.public_id || '') + '</option>';
        }).join('');
      }
    } catch (e) {
      els.permSubjectId.innerHTML = '<option value="">' + esc(t('knowledge.load_error', 'Ошибка')) + '</option>';
    }
  }
  async function addPagePermission() {
    if (!els.permSubjectType || !els.permSubjectId || !els.permAccessLevel || !pageId) return;
    var rawSubjectId = els.permSubjectId.value;
    if (!rawSubjectId) return;
    var subjectId = parseInt(rawSubjectId, 10);
    var body = { subject_type: els.permSubjectType.value, access_level: els.permAccessLevel.value };
    if (/^\d+$/.test(rawSubjectId) && subjectId > 0) {
      body.subject_id = subjectId;
    } else {
      body.subject_public_id = rawSubjectId;
    }
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/permissions', { method: 'POST', body: body, idempotent: true });
    await loadPagePermissions();
  }
  function showEditor(show) {
    var layout = document.querySelector('.crm-knowledge-detail-layout');
    if (layout) layout.classList.toggle('is-editing', show);
    document.body.classList.toggle('crm-knowledge-writing-mode', show);
    els.editor.classList.toggle('d-none', !show);
    els.view.classList.toggle('d-none', show);
    if (show && current) {
      els.editTitle.value = current.title || '';
      if (els.editReviewDue) els.editReviewDue.value = current.review_due_at ? current.review_due_at.substring(0, 10) : '';
      els.editContent.value = current.content_html || '';
      if (els.visualEditor) {
        els.visualEditor.innerHTML = sanitizePreviewHtml(current.content_html || '<p></p>');
      }
      updateEditorPreview();
      window.setTimeout(focusVisualEditor, 80);
    }
  }
  function render(page) {
    current = page;
    els.state.classList.add('d-none');
    els.view.classList.remove('d-none');
    els.title.textContent = page.title || '';
    els.space.textContent = page.space_title || '';
    var statusMap = { draft: 'crm-badge-secondary', review: 'crm-badge-warning', published: 'crm-badge-success', archived: 'crm-badge-light', needs_update: 'crm-badge-danger' };
    var statusLabels = { draft: t('knowledge.status_draft', 'Черновик'), review: t('knowledge.status_review', 'На проверке'), published: t('knowledge.status_published', 'Опубликовано'), archived: t('knowledge.status_archived', 'В архиве'), needs_update: t('knowledge.status_needs_update', 'Требует обновления') };
    els.status.className = 'crm-badge ' + (statusMap[page.status] || 'crm-badge-secondary');
    els.status.textContent = statusLabels[page.status] || page.status || '';
    els.content.innerHTML = page.content_html || '<p class="text-muted">' + esc(t('knowledge_page.empty_content', 'Содержание пока не заполнено.')) + '</p>';
    renderToc();
    els.metaSpace.textContent = page.space_title || '—';
    els.metaType.textContent = page.page_type || '—';
    els.metaUpdated.textContent = page.updated_at || '—';
    els.metaViews.textContent = String(page.views_count || 0);
    els.metaReviewDue.textContent = page.review_due_at ? page.review_due_at.substring(0, 10) : '—';
    updateFavSubButtons();
    loadTree(page);
    loadComments();
    loadAttachments();
  }
  function renderToc() {
    if (!els.tocContainer || !els.toc) return;
    var headings = els.content.querySelectorAll('h2, h3');
    if (!headings.length) {
      els.tocContainer.classList.add('d-none');
      return;
    }
    var html = [];
    headings.forEach(function (h) {
      if (!h.id) h.id = 'toc-heading-' + Math.random().toString(36).substring(2, 8);
      var level = h.tagName === 'H2' ? 'toc-h2' : 'toc-h3';
      html.push('<a class="' + level + '" href="#' + h.id + '">' + esc(h.textContent || '') + '</a>');
    });
    els.toc.innerHTML = html.join('');
    els.tocContainer.classList.remove('d-none');
    els.toc.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var target = document.getElementById(this.getAttribute('href').substring(1));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    initTocScroll();
  }
  function renderVersions(items) {
    if (!items || !items.length) {
      els.versions.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.no_versions', 'Версий пока нет.')) + '</div>';
      return;
    }
    els.versions.innerHTML = items.map(function (item) {
      return '<div class="crm-knowledge-version"><div><strong>v' + esc(item.version_number) + '</strong><span>' + esc(item.created_at || '') + '</span>' + (item.change_summary ? '<br><span class="text-muted">' + esc(item.change_summary) + '</span>' : '') + '</div><div class="d-flex gap-1 mt-1"><button class="btn btn-sm crm-btn-secondary" data-restore-version="' + esc(item.version_number) + '" style="font-size:0.78rem;padding:0.12rem 0.45rem">' + esc(t('knowledge_page.restore', 'Восстановить')) + '</button><button class="btn btn-sm crm-btn-secondary" data-diff-version="' + esc(item.version_number) + '" style="font-size:0.78rem;padding:0.12rem 0.45rem">' + esc(t('knowledge_page.btn_diff', 'Сравнить')) + '</button></div></div>';
    }).join('');
  }
  function updateFavSubButtons() {
    isFav = current && current.is_favorited ? true : false;
    isSub = current && current.is_subscribed ? true : false;
    els.favBtn.textContent = isFav ? t('knowledge_page.favorite_remove', 'Из избранного') : t('knowledge_page.favorite_add', 'В избранное');
    els.subBtn.textContent = isSub ? t('knowledge_page.unsubscribe', 'Отписаться') : t('knowledge_page.subscribe', 'Подписаться');
  }
  async function loadTree(page) {
    if (!page || !page.space_public_id) { els.tree.innerHTML = '<div class="text-muted small">—</div>'; return; }
    try {
      var envelope = await request('api/v1/knowledge/spaces/' + encodeURIComponent(page.space_public_id) + '/tree', { method: 'GET', query: { depth: 10 } });
      var items = envelope.data && envelope.data.items || [];
      els.tree.innerHTML = renderTreeNodes(items);
      initTreeToggles();
    } catch (e) { els.tree.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  function initTreeToggles() {
    if (!els.tree) return;
    els.tree.querySelectorAll('[data-tree-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var li = btn.closest('.crm-knowledge-tree-item');
        if (!li) return;
        var isOpen = btn.classList.contains('is-open');
        btn.classList.toggle('is-open', !isOpen);
        li.classList.toggle('is-collapsed', isOpen);
      });
    });
    els.tree.querySelectorAll('.crm-knowledge-tree-item').forEach(function (li) {
      var active = li.querySelector('.crm-knowledge-tree-active');
      if (!active) return;
      var parent = li.parentElement;
      while (parent && parent !== els.tree) {
        if (parent.classList && parent.classList.contains('crm-knowledge-tree-item')) {
          parent.classList.remove('is-collapsed');
          var toggle = parent.querySelector(':scope > .crm-knowledge-tree-item-row > [data-tree-toggle]');
          if (toggle) toggle.classList.add('is-open');
        }
        parent = parent.parentElement;
      }
    });
  }
  function renderTreeNodes(nodes, depth) {
    if (!nodes || !nodes.length) return '';
    depth = depth || 0;
    var html = '<ul class="crm-knowledge-tree-list">';
    nodes.forEach(function (n) {
      var active = n.public_id === pageId ? ' crm-knowledge-tree-active' : '';
      var hasChildren = n.children && n.children.length;
      var toggleHtml = hasChildren ? '<button type="button" class="crm-knowledge-tree-toggle is-open" data-tree-toggle aria-label="Toggle"><i class="fa-solid fa-chevron-right"></i></button>' : '';
      html += '<li class="crm-knowledge-tree-item' + active + '">';
      html += '<div class="crm-knowledge-tree-item-row">' + toggleHtml + '<a href="index.php?route=knowledge-page&amp;id=' + esc(n.public_id) + '">' + esc(n.title) + '</a></div>';
      if (hasChildren) html += renderTreeNodes(n.children, depth + 1);
      html += '</li>';
    });
    html += '</ul>';
    return html;
  }
  async function loadComments() {
    if (!pageId) return;
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/comments', { method: 'GET' });
      var items = envelope.data && envelope.data.items || [];
      renderComments(items);
    } catch (e) { els.commentsList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  var replyToId = null;
  var replyToName = '';
  function renderComments(items) {
    if (!items || !items.length) {
      els.commentsList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.comments_empty', 'Комментариев пока нет')) + '</div>';
      return;
    }
    var byParent = {};
    items.forEach(function (c) { var pid = c.parent_id || 'root'; if (!byParent[pid]) byParent[pid] = []; byParent[pid].push(c); });
    function renderThread(parentId, depth) {
      var kids = byParent[parentId] || [];
      if (!kids.length) return '';
      return kids.map(function (c) {
        var ml = Math.min(depth * 24, 72);
        var resolved = c.resolved_at ? ' <span class="text-success small">' + esc(t('knowledge_page.comments_resolved', ' resolved')) + '</span>' : '';
        var resolveBtn = c.resolved_at
          ? '<button class="btn btn-sm crm-btn-secondary" data-comment-reopen="' + esc(c.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_reopen', 'Открыть')) + '</button>'
          : '<button class="btn btn-sm crm-btn-secondary" data-comment-resolve="' + esc(c.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_resolve', 'Решено')) + '</button>';
        var replyBtn = '<button class="btn btn-sm crm-btn-secondary" data-comment-reply="' + esc(c.public_id) + '" data-comment-reply-name="' + esc(c.user_name || '') + '" style="font-size:0.7rem">' + esc(t('knowledge_page.comments_reply', 'Ответить')) + '</button>';
        var childrenHtml = renderThread(c.id, depth + 1);
        return '<div class="crm-knowledge-comment' + (c.resolved_at ? ' crm-knowledge-comment-resolved' : '') + '" style="margin-left:' + ml + 'px"><div class="crm-knowledge-comment-head"><strong>' + esc(c.user_name || t('common.unknown', 'Неизвестно')) + '</strong><span class="text-muted small">' + esc(c.created_at || '') + '</span>' + resolved + '</div><div class="crm-knowledge-comment-body">' + esc(c.body) + '</div><div class="crm-knowledge-comment-actions">' + replyBtn + resolveBtn + '</div>' + childrenHtml + '</div>';
      }).join('');
    }
    els.commentsList.innerHTML = renderThread('root', 0);
  }
  async function loadAttachments() {
    if (!pageId) return;
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/files', { method: 'GET' });
      renderAttachments(envelope.data && envelope.data.items || []);
    } catch (e) { els.attachList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.load_error', 'Ошибка')) + '</div>'; }
  }
  function renderAttachments(items) {
    if (!items || !items.length) {
      els.attachList.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.attachments_empty', 'Файлов пока нет')) + '</div>';
      return;
    }
    els.attachList.innerHTML = '<ul class="crm-knowledge-attach-list">' + items.map(function (f) {
      var isImage = (f.mime_type || '').indexOf('image/') === 0;
      var sizeLabel = f.size_bytes >= 1048576 ? (f.size_bytes / 1048576).toFixed(1) + ' MB' : f.size_bytes >= 1024 ? (f.size_bytes / 1024).toFixed(0) + ' KB' : f.size_bytes + ' B';
      var preview = isImage ? '<a href="api/v1/files/' + esc(f.public_id) + '/download" target="_blank" rel="noopener"><img src="api/v1/files/' + esc(f.public_id) + '/download" alt="' + esc(f.original_name) + '" style="max-width:120px;max-height:80px;border-radius:4px;object-fit:cover;display:block;margin-bottom:4px" loading="lazy"></a>' : '';
      return '<li>' + preview + '<div class="crm-knowledge-attach-info"><a href="api/v1/files/' + esc(f.public_id) + '/download" target="_blank" rel="noopener">' + esc(f.original_name) + '</a> <span class="text-muted small">(' + sizeLabel + ')</span> <button class="btn btn-sm crm-btn-danger-soft" data-attach-delete="' + esc(f.public_id) + '" style="font-size:0.7rem">' + esc(t('knowledge_page.attachments_delete', 'Удалить')) + '</button></div></li>';
    }).join('') + '</ul>';
  }
  async function uploadFile(file) {
    var formData = new FormData();
    formData.append('file', file);
    formData.append('entity_type', 'knowledge_page');
    formData.append('entity_public_id', pageId);
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/files', { method: 'POST', body: formData, idempotent: true });
      await loadAttachments();
    } catch (e) {}
  }
  async function load() {
    if (!pageId) {
      els.state.textContent = t('knowledge_page.no_id', 'Не указан материал базы знаний.');
      return;
    }
    try {
      var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'GET' });
      render(envelope.data && envelope.data.page || {});
      var versions = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions', { method: 'GET' });
      renderVersions(versions.data && versions.data.items || []);
    } catch (e) {
      els.state.textContent = t('knowledge_page.load_error', 'Не удалось загрузить материал.');
    }
  }
  async function patch(body) {
    var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId), { method: 'PATCH', body: Object.assign({ row_version: current && current.row_version }, body) });
    render(envelope.data && envelope.data.page || {});
  }
  function startAutosave() {
    if (autoTimer) clearTimeout(autoTimer);
    autoTimer = setTimeout(function () {
      if (els.editor.classList.contains('d-none')) return;
      syncHiddenContent();
      if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saving', 'Сохранение...');
      var draftBody = { title: els.editTitle.value, content_html: els.editContent.value };
      if (els.editReviewDue) {
        var rv = els.editReviewDue.value;
        if (rv) draftBody.review_due_at = rv;
      }
      request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/draft', {
        method: 'POST', body: draftBody, idempotent: true
      }).then(function () {
        if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saved', 'Сохранено');
      }).catch(function () {
        if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_error', 'Ошибка сохранения');
      });
    }, 3000);
  }
  document.getElementById('knowledgeEditBtn').addEventListener('click', function () { showEditor(true); });
  document.getElementById('knowledgeCancelEditBtn').addEventListener('click', function () { showEditor(false); });
  document.getElementById('knowledgeSaveDraftBtn').addEventListener('click', async function () {
    syncHiddenContent();
    var draftBody = { title: els.editTitle.value, content_html: els.editContent.value };
    if (els.editReviewDue) {
      var rv = els.editReviewDue.value;
      if (rv) draftBody.review_due_at = rv;
    }
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/draft', { method: 'POST', body: draftBody, idempotent: true });
    if (els.autosaveStatus) els.autosaveStatus.textContent = t('knowledge_page.autosave_saved', 'Сохранено');
  });
  els.editContent && els.editContent.addEventListener('input', function () { updateEditorPreview(); startAutosave(); });
  els.visualEditor && els.visualEditor.addEventListener('input', function () { syncHiddenContent(); startAutosave(); });
  els.visualEditor && els.visualEditor.addEventListener('keyup', function () { rememberEditorSelection(); updateToolbarState(); });
  els.visualEditor && els.visualEditor.addEventListener('mouseup', function () { rememberEditorSelection(); updateToolbarState(); });
  els.visualEditor && els.visualEditor.addEventListener('focus', function () { rememberEditorSelection(); updateToolbarState(); });
  els.visualEditor && els.visualEditor.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      document.getElementById('knowledgeSaveDraftBtn').click();
      return;
    }
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      els.editor.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      return;
    }
    if (e.key === '/') {
      e.preventDefault();
      openBlockMenu();
      return;
    }
    if (e.key === 'Escape') {
      closeBlockMenu();
    }
  });
  els.editTitle && els.editTitle.addEventListener('input', startAutosave);
  els.blockAddBtn && els.blockAddBtn.addEventListener('click', function () {
    if (!els.blockMenu) return;
    if (els.blockMenu.classList.contains('d-none')) openBlockMenu();
    else closeBlockMenu();
  });
  els.blockSearch && els.blockSearch.addEventListener('input', function () { filterBlockMenu(els.blockSearch.value); });
  els.blockSearch && els.blockSearch.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      closeBlockMenu();
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      var firstVisible = Array.prototype.slice.call(els.blockMenu.querySelectorAll('[data-editor-block]')).find(function (btn) {
        return btn.style.display !== 'none';
      });
      if (firstVisible) firstVisible.click();
    }
  });
  document.addEventListener('click', function (e) {
    if (!els.blockMenu || els.blockMenu.classList.contains('d-none')) return;
    if (e.target.closest('#knowledgeBlockMenu') || e.target.closest('#knowledgeBlockAddBtn')) return;
    els.blockMenu.classList.add('d-none');
  });
  document.querySelectorAll('[data-editor-block]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var type = btn.getAttribute('data-editor-block');
      if (els.blockMenu) els.blockMenu.classList.add('d-none');
      if (type === 'p') insertVisualHtml('<p>' + esc(t('knowledge_page.block_text', 'Текст')) + '</p>');
      if (type === 'h2') insertVisualHtml('<h2>' + esc(t('knowledge_page.block_h2', 'Подзаголовок H2')) + '</h2>');
      if (type === 'h3') insertVisualHtml('<h3>' + esc(t('knowledge_page.block_h3', 'Подзаголовок H3')) + '</h3>');
      if (type === 'ul') insertVisualHtml('<ul><li>' + esc(t('knowledge_page.ul', 'Список')) + '</li></ul>');
      if (type === 'blockquote') insertVisualHtml('<blockquote><p>' + esc(t('knowledge_page.blockquote', 'Цитата')) + '</p></blockquote>');
      if (type === 'link') {
        var url = prompt(t('knowledge_page.link_prompt', 'Введите URL:'), 'https://');
        if (url) insertVisualHtml('<p><a href="' + esc(url) + '">' + esc(url) + '</a></p>');
      }
    });
  });
  document.addEventListener('selectionchange', updateToolbarState);
  els.editor.addEventListener('submit', async function (event) {
    event.preventDefault();
    syncHiddenContent();
    var patchBody = { title: els.editTitle.value, content_html: els.editContent.value };
    if (els.editReviewDue) {
      var rv = els.editReviewDue.value;
      if (rv) patchBody.review_due_at = rv;
    }
    await patch(patchBody);
    showEditor(false);
  });
  document.getElementById('knowledgePublishBtn').addEventListener('click', async function () {
    var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/publish', { method: 'POST', body: {}, idempotent: true });
    render(envelope.data && envelope.data.page || {});
    load();
  });
  document.addEventListener('click', function (e) {
    var exportItem = e.target.closest('[data-export-format]');
    if (exportItem && current) {
      e.preventDefault();
      var fmt = exportItem.getAttribute('data-export-format') || 'json';
      (async function () {
        try {
          var envelope = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/export?format=' + fmt, { method: 'GET' });
          var data = envelope.data || {};
          var filename = data.filename || (current.slug || pageId) + '.' + fmt;
          var blobContent = fmt === 'json' ? JSON.stringify(data, null, 2) : (data.content || '');
          var blob = new Blob([blobContent], { type: fmt === 'json' ? 'application/json' : 'text/markdown' });
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = url; a.download = filename;
          document.body.appendChild(a); a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        } catch (e) { alert(t('knowledge_page.export_error', 'Ошибка экспорта')); }
      })();
      return;
    }
  });
  document.getElementById('knowledgeReviewBtn').addEventListener('click', async function () {
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/request-review', { method: 'POST', body: {}, idempotent: true });
    load();
  });
  document.getElementById('knowledgeArchiveBtn').addEventListener('click', async function () {
    await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/archive', { method: 'POST', body: {}, idempotent: true });
    load();
  });
  els.favBtn.addEventListener('click', async function () {
    if (isFav) {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/favorite', { method: 'DELETE', idempotent: true });
    } else {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/favorite', { method: 'POST', idempotent: true });
    }
    isFav = !isFav;
    updateFavSubButtons();
  });
  els.subBtn.addEventListener('click', async function () {
    if (isSub) {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/subscribe', { method: 'DELETE', idempotent: true });
    } else {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/subscribe', { method: 'POST', idempotent: true });
    }
    isSub = !isSub;
    updateFavSubButtons();
  });
  els.commentSend.addEventListener('click', async function () {
    var body = (els.commentInput.value || '').trim();
    if (!body) return;
    els.commentInput.disabled = true;
    try {
      var payload = { body: body };
      if (replyToId) payload.parent_public_id = replyToId;
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/comments', { method: 'POST', body: payload, idempotent: true });
      els.commentInput.value = '';
      replyToId = null; replyToName = '';
      updateReplyIndicator();
      loadComments();
    } catch (e) {}
    els.commentInput.disabled = false;
    els.commentInput.focus();
  });
  function updateReplyIndicator() {
    var indicator = document.getElementById('knowledgeCommentReplyIndicator');
    var label = document.getElementById('knowledgeCommentReplyLabel');
    if (!indicator || !label) return;
    if (replyToId) {
      indicator.classList.remove('d-none');
      label.textContent = t('knowledge_page.comments_reply_to', 'Ответ на') + ' ' + esc(replyToName);
    } else {
      indicator.classList.add('d-none');
    }
  }
  document.getElementById('knowledgeCommentCancelReply').addEventListener('click', function () {
    replyToId = null; replyToName = '';
    updateReplyIndicator();
    els.commentInput.focus();
  });
  els.commentInput && els.commentInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); els.commentSend.click(); }
  });
  els.attachUploadBtn && els.attachUploadBtn.addEventListener('click', async function () {
    var files = els.attachInput && els.attachInput.files;
    if (!files || !files.length) return;
    for (var i = 0; i < files.length; i++) { await uploadFile(files[i]); }
    els.attachInput.value = '';
  });
  document.addEventListener('click', function (e) {
    var restoreBtn = e.target.closest('[data-restore-version]');
    if (restoreBtn) {
      var v = restoreBtn.getAttribute('data-restore-version');
      if (v && confirm(t('knowledge_page.restore_confirm', 'Восстановить эту версию? Текущее содержимое будет заменено.'))) {
        request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions/' + encodeURIComponent(v) + '/restore', { method: 'POST', idempotent: true }).then(function () { load(); });
      }
      return;
    }
    var diffBtn = e.target.closest('[data-diff-version]');
    if (diffBtn) {
      var vNum = parseInt(diffBtn.getAttribute('data-diff-version'), 10);
      if (current && current.row_version) {
        request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/versions/diff?from=' + (vNum - 1) + '&to=' + vNum, { method: 'GET' }).then(function (envelope) {
          var diff = envelope.data || {};
          els.diffContainer.classList.remove('d-none');
          els.diffContent.innerHTML = '<div class="text-muted small">' + esc(t('knowledge_page.diff_version', 'Версия')) + ' ' + vNum + ': ' + esc(diff.text_changed ? t('knowledge_page.diff_changed', 'Есть изменения') : t('knowledge_page.diff_unchanged', 'Без изменений')) + '</div>';
        });
      }
      return;
    }
    var resolveBtn = e.target.closest('[data-comment-resolve]');
    if (resolveBtn) {
      request('api/v1/knowledge/comments/' + encodeURIComponent(resolveBtn.getAttribute('data-comment-resolve')) + '/resolve', { method: 'POST', idempotent: true }).then(loadComments);
      return;
    }
    var reopenBtn = e.target.closest('[data-comment-reopen]');
    if (reopenBtn) {
      request('api/v1/knowledge/comments/' + encodeURIComponent(reopenBtn.getAttribute('data-comment-reopen')) + '/reopen', { method: 'POST', idempotent: true }).then(loadComments);
      return;
    }
    var replyBtn = e.target.closest('[data-comment-reply]');
    if (replyBtn) {
      replyToId = replyBtn.getAttribute('data-comment-reply');
      replyToName = replyBtn.getAttribute('data-comment-reply-name') || '';
      updateReplyIndicator();
      els.commentInput.focus();
      return;
    }
    var delBtn = e.target.closest('[data-attach-delete]');
    if (delBtn) {
      var fId = delBtn.getAttribute('data-attach-delete');
      if (fId && confirm(t('knowledge_page.attachments_delete_confirm', 'Удалить этот файл?'))) {
        request('api/v1/knowledge/files/' + encodeURIComponent(fId), { method: 'DELETE', idempotent: true }).then(loadAttachments);
      }
      return;
    }
    var pagePermDelBtn = e.target.closest('[data-page-perm-delete]');
    if (pagePermDelBtn) {
      var permId = pagePermDelBtn.getAttribute('data-page-perm-delete');
      if (permId && confirm(t('admin_knowledge.permissions_delete_confirm', 'Удалить это право доступа?'))) {
        request('api/v1/knowledge/page-permissions/' + encodeURIComponent(permId), { method: 'DELETE', idempotent: true }).then(loadPagePermissions);
      }
    }
  });

  // ===== AI SECTION =====
  var aiBtnIds = {
    summary: 'knowledgeAiSummaryBtn',
    explain: 'knowledgeAiExplainBtn',
    checklist: 'knowledgeAiChecklistBtn',
    similar: 'knowledgeAiSimilarBtn',
    'faq-from-comments': 'knowledgeAiFaqBtn'
  };
  var aiBtnLabels = {
    summary: function () { return t('knowledge_page.btn_ai_summary', 'Краткое содержание'); },
    explain: function () { return t('knowledge_page.btn_ai_explain', 'Объяснить проще'); },
    checklist: function () { return t('knowledge_page.btn_ai_checklist', 'Чеклист'); },
    similar: function () { return t('knowledge_page.btn_ai_similar', 'Похожие страницы'); },
    'faq-from-comments': function () { return t('knowledge_page.btn_ai_faq', 'FAQ из комментариев'); }
  };
  var aiBtnIcons = {
    summary: 'fa-wand-magic-sparkles',
    explain: 'fa-lightbulb',
    checklist: 'fa-list-check',
    similar: 'fa-copy',
    'faq-from-comments': 'fa-circle-question'
  };
  var aiResultTitles = {
    summary: function () { return t('knowledge_page.ai_summary_title', 'Краткое содержание'); },
    explain: function () { return t('knowledge_page.ai_explain_title', 'Объяснение'); },
    checklist: function () { return t('knowledge_page.ai_checklist_title', 'Чеклист'); },
    similar: function () { return t('knowledge_page.ai_similar_title', 'Похожие страницы'); },
    'faq-from-comments': function () { return t('knowledge_page.ai_faq_title', 'FAQ из комментариев'); }
  };
  var aiActionTypes = {
    summary: 'text',
    explain: 'text',
    checklist: 'checklist',
    similar: 'links',
    'faq-from-comments': 'faq'
  };

  var modalEl = document.getElementById('knowledgeAiModal');
  var modalTitleEl = document.getElementById('knowledgeAiModalTitle');
  var modalBodyEl = document.getElementById('knowledgeAiModalBody');
  var modalLoaderEl = document.getElementById('knowledgeAiModalLoader');
  var sidebarResultEl = document.getElementById('knowledgeAiResult');
  var sidebarTitleEl = document.getElementById('knowledgeAiResultTitle');
  var sidebarBodyEl = document.getElementById('knowledgeAiResultBody');

  var lastAiData = null;
  var lastAiAction = null;
  var aiInProgress = false;

  function showAiLoading(show) {
    if (show) {
      if (modalLoaderEl) modalLoaderEl.classList.remove('d-none');
      if (modalBodyEl) modalBodyEl.classList.add('d-none');
    } else {
      if (modalLoaderEl) modalLoaderEl.classList.add('d-none');
      if (modalBodyEl) modalBodyEl.classList.remove('d-none');
    }
  }

  function renderAiInSidebar(action, data) {
    if (!sidebarResultEl || !sidebarTitleEl || !sidebarBodyEl) return;
    sidebarResultEl.classList.remove('d-none');
    sidebarTitleEl.textContent = (aiResultTitles[action] || function () { return ''; })();

    if (action === 'similar') {
      var items = data.items || [];
      if (!items.length) {
        sidebarBodyEl.innerHTML = '<em>' + esc(t('knowledge_page.ai_no_similar', 'Похожих страниц не найдено')) + '</em>';
      } else {
        sidebarBodyEl.innerHTML = items.slice(0, 5).map(function (item) {
          return '<div class="mb-1"><a href="index.php?route=knowledge-page&amp;id=' + esc(item.public_id) + '" class="text-decoration-none">' + esc(item.title) + '</a></div>';
        }).join('') + (items.length > 5 ? '<div class="text-muted small">+' + (items.length - 5) + ' ' + esc(t('knowledge_page.ai_more', 'ещё')) + '</div>' : '');
      }
    } else if (action === 'checklist') {
      var checklistItems = data.items || [];
      if (!checklistItems.length) {
        sidebarBodyEl.innerHTML = '<em>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</em>';
      } else {
        sidebarBodyEl.innerHTML = '<ul class="mb-0 ps-3">' + checklistItems.slice(0, 4).map(function (item) {
          return '<li style="font-size:0.75rem"><label><input type="checkbox" class="me-1">' + esc(item) + '</label></li>';
        }).join('') + (checklistItems.length > 4 ? '<li class="text-muted small">+' + (checklistItems.length - 4) + ' ' + esc(t('knowledge_page.ai_more', 'ещё')) + '</li>' : '') + '</ul>';
      }
    } else if (action === 'faq-from-comments') {
      var faqItems = data.items || [];
      if (!faqItems.length) {
        sidebarBodyEl.innerHTML = '<em>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</em>';
      } else {
        sidebarBodyEl.innerHTML = '<div class="small">' + faqItems.slice(0, 2).map(function (item) {
          return '<div class="mb-1"><strong>' + esc(item.question || '') + '</strong></div>';
        }).join('') + (faqItems.length > 2 ? '<div class="text-muted">+' + (faqItems.length - 2) + ' ' + esc(t('knowledge_page.ai_more', 'ещё')) + '</div>' : '') + '</div>';
      }
    } else {
      var text = data.summary || data.explanation || data.text || '';
      var isFallbackOrError = data.mode === 'fallback' || data.mode === 'error';
      sidebarBodyEl.innerHTML = text ? '<div style="font-size:0.75rem;line-height:1.4">' + (isFallbackOrError ? '<span class="text-muted" style="font-size:0.65rem">' + (data.mode === 'error' ? esc(t('knowledge_page.ai_error_short', 'Ошибка AI')) : esc(t('knowledge_page.ai_fallback_note_short', 'Структура документа'))) + '</span><br>' : '') + textToHtml(text.substring(0, 300)) + (text.length > 300 ? '<span class="text-muted">…</span>' : '') + '</div>' : '<em>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</em>';
    }
  }

  function textToHtml(text) {
    if (!text) return '';
    // Escape HTML first
    var safe = esc(text);
    // Convert markdown-style formatting
    // Bold: **text** or __text__
    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/__(.+?)__/g, '<strong>$1</strong>');
    // Italic: *text* or _text_ (bold already converted, so remaining * are italic)
    safe = safe.replace(/\*(\S(?:.*?\S)?)\*/g, '<em>$1</em>');
    safe = safe.replace(/_(\S(?:.*?\S)?)_/g, '<em>$1</em>');
    // Inline code: `code`
    safe = safe.replace(/`([^`]+)`/g, '<code>$1</code>');
    // URLs
    safe = safe.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');

    var lines = safe.split('\n');
    var html = [];
    var inUl = false;
    var inOl = false;
    var inParagraph = false;

    function closeUl() { if (inUl) { html.push('</ul>'); inUl = false; } }
    function closeOl() { if (inOl) { html.push('</ol>'); inOl = false; } }
    function closeParagraph() { if (inParagraph) { html.push('</p>'); inParagraph = false; } }

    for (var i = 0; i < lines.length; i++) {
      var line = lines[i];
      var trimmed = line.trim();

      // Empty line = paragraph break
      if (trimmed === '') {
        closeUl(); closeOl(); closeParagraph();
        continue;
      }

      // Unordered list item
      if (/^[-*]\s/.test(trimmed)) {
        closeOl(); closeParagraph();
        if (!inUl) { html.push('<ul>'); inUl = true; }
        html.push('<li>' + trimmed.substring(2).trim() + '</li>');
        continue;
      }

      // Ordered list item
      if (/^\d+\.\s/.test(trimmed)) {
        closeUl(); closeParagraph();
        if (!inOl) { html.push('<ol>'); inOl = true; }
        html.push('<li>' + trimmed.replace(/^\d+\.\s*/, '') + '</li>');
        continue;
      }

      // Heading-like: ## or ### or **text** as heading
      if (/^#{1,3}\s/.test(trimmed)) {
        closeUl(); closeOl(); closeParagraph();
        var level = trimmed.match(/^#+/)[0].length;
        var hContent = trimmed.replace(/^#+\s*/, '');
        html.push('<h' + Math.min(level + 1, 4) + '>' + hContent + '</h' + Math.min(level + 1, 4) + '>');
        continue;
      }

      // Regular paragraph line
      closeUl(); closeOl();
      if (!inParagraph) { html.push('<p>'); inParagraph = true; }
      else { html.push('<br>'); }
      html.push(line);
    }

    closeUl(); closeOl(); closeParagraph();
    return html.join('\n');
  }

  function renderAiInModal(action, data) {
    if (!modalBodyEl) return;
    showAiLoading(false);
    var title = (aiResultTitles[action] || function () { return ''; })();
    if (modalTitleEl) modalTitleEl.textContent = title;

    if (action === 'similar') {
      var items = data.items || [];
      if (!items.length) {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-solid fa-copy"></i><p>' + esc(t('knowledge_page.ai_no_similar', 'Похожих страниц не найдено')) + '</p></div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-similar-list">' + items.map(function (item) {
          return '<a class="crm-ai-similar-item" href="index.php?route=knowledge-page&amp;id=' + esc(item.public_id) + '"><strong>' + esc(item.title) + '</strong><span class="crm-badge crm-badge-secondary">' + esc(item.page_type) + '</span><span class="crm-ai-similar-space">' + esc(item.space_title || '') + '</span></a>';
        }).join('') + '</div>';
      }
    } else if (action === 'checklist') {
      var checklistItems = data.items || [];
      if (!checklistItems.length) {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-solid fa-list-check"></i><p>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</p></div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-checklist"><ul>' + checklistItems.map(function (item) {
          return '<li><label><input type="checkbox" class="me-2">' + esc(item) + '</label></li>';
        }).join('') + '</ul></div>';
      }
    } else if (action === 'faq-from-comments') {
      var faqItems = data.items || [];
      if (!faqItems.length) {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-solid fa-circle-question"></i><p>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</p></div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-faq">' + faqItems.map(function (item) {
          return '<details class="crm-ai-faq-item" open><summary><strong>' + esc(item.question || '') + '</strong></summary><p>' + esc(item.answer || '') + '</p></details>';
        }).join('') + '</div>';
      }
    } else if (data.mode === 'error' && data.error_details) {
      var errDetails = data.error_details;
      var errCode = errDetails.code || '';
      var errMsg = errDetails.message || '';
      var errCategory = errDetails.category || '';
      var errHttpStatus = errDetails.http_status || 0;
      var retryable = errDetails.retryable !== false;

      var categoryLabels = {
        'configuration': t('knowledge_page.ai_error_config', 'Ошибка конфигурации'),
        'auth': t('knowledge_page.ai_error_auth', 'Ошибка авторизации'),
        'billing': t('knowledge_page.ai_error_billing', 'Проблема с оплатой'),
        'rate_limited': t('knowledge_page.ai_error_rate', 'Превышен лимит запросов'),
        'network': t('knowledge_page.ai_error_network', 'Ошибка сети'),
        'provider_error': t('knowledge_page.ai_error_provider', 'Ошибка провайдера'),
        'http_error': t('knowledge_page.ai_error_http', 'HTTP ошибка'),
        'invalid_response': t('knowledge_page.ai_error_response', 'Некорректный ответ')
      };
      var categoryLabel = categoryLabels[errCategory] || errCategory || t('knowledge_page.ai_error_unknown', 'Неизвестная ошибка');

      var errorHtml = '<div class="crm-ai-error">';
      errorHtml += '<div class="crm-ai-error-icon"><i class="fa-solid fa-circle-exclamation"></i></div>';
      errorHtml += '<h4>' + esc(categoryLabel) + '</h4>';
      if (errMsg) errorHtml += '<p class="crm-ai-error-msg">' + esc(errMsg) + '</p>';
      errorHtml += '<div class="crm-ai-error-details small text-muted">';
      if (errCode) errorHtml += '<span class="crm-ai-error-code">Код: ' + esc(errCode) + '</span>';
      if (errHttpStatus) errorHtml += '<span class="crm-ai-error-http">HTTP: ' + errHttpStatus + '</span>';
      if (!retryable) errorHtml += '<span class="crm-ai-error-nonretryable">' + esc(t('knowledge_page.ai_error_nonretryable', 'Требуется настройка')) + '</span>';
      errorHtml += '</div>';
      errorHtml += '<div class="crm-ai-error-actions mt-2">';
      errorHtml += '<a href="index.php?route=admin-ai" class="btn btn-sm crm-btn-secondary"><i class="fa-solid fa-gear"></i> ' + esc(t('knowledge_page.ai_check_settings', 'Проверить настройки AI')) + '</a>';
      errorHtml += '</div></div>';
      modalBodyEl.innerHTML = errorHtml;

      // Hide copy/insert buttons, show fallback note
      var insertBtn = document.getElementById('knowledgeAiModalInsertBtn');
      if (insertBtn) insertBtn.style.display = 'none';
    } else {
      var text = data.summary || data.explanation || data.text || '';
      if (text) {
        var fallbackNote = '';
        if (data.mode === 'fallback') {
          fallbackNote = '<div class="crm-ai-fallback-note"><i class="fa-solid fa-info-circle"></i> ' + esc(t('knowledge_page.ai_fallback_note', 'AI временно недоступен. Показана структура документа.')) + '</div>';
        }
        modalBodyEl.innerHTML = fallbackNote + '<div class="crm-ai-text-content">' + textToHtml(text) + '</div>';
      } else {
        modalBodyEl.innerHTML = '<div class="crm-ai-empty"><i class="fa-solid fa-wand-magic-sparkles"></i><p>' + esc(t('knowledge_page.ai_no_result', 'Нет результата')) + '</p></div>';
      }
    }
  }

  function getAiPlainText(action, data) {
    if (!data) return '';
    if (action === 'similar') {
      var items = data.items || [];
      return items.map(function (item) { return item.title + ' (' + item.page_type + ')'; }).join('\n');
    } else if (action === 'checklist') {
      var items = data.items || [];
      return items.map(function (item) { return '[ ] ' + item; }).join('\n');
    } else if (action === 'faq-from-comments') {
      var items = data.items || [];
      return items.map(function (item) { return 'Q: ' + (item.question || '') + '\nA: ' + (item.answer || ''); }).join('\n\n');
    }
    return data.summary || data.explanation || data.text || '';
  }

  function getAiInsertHtml(action, data) {
    if (!data) return '';
    if (action === 'checklist') {
      var items = data.items || [];
      return items.map(function (item) { return '<p><label><input type="checkbox"> ' + esc(item) + '</label></p>'; }).join('\n');
    } else if (action === 'faq-from-comments') {
      var items = data.items || [];
      return items.map(function (item) {
        return '<h3>' + esc(item.question || '') + '</h3><p>' + esc(item.answer || '') + '</p>';
      }).join('\n');
    } else if (action === 'summary') {
      var text = data.summary || data.text || '';
      return text ? '<blockquote><p><strong>' + esc(t('knowledge_page.ai_summary_title', 'Краткое содержание')) + '</strong></p><p>' + esc(text) + '</p></blockquote>' : '';
    } else if (action === 'explain') {
      var text = data.explanation || data.text || '';
      return text ? '<blockquote><p><strong>' + esc(t('knowledge_page.ai_explain_title', 'Объяснение')) + '</strong></p><p>' + esc(text) + '</p></blockquote>' : '';
    }
    return '';
  }

  async function handleAiAction(action) {
    if (aiInProgress) return;
    aiInProgress = true;

    var btn = document.getElementById(aiBtnIds[action] || '');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="crm-ai-btn-spinner"></span>'; }

    if (modalEl && modalBodyEl) {
      modalBodyEl.innerHTML = '';
      modalBodyEl.classList.add('d-none');
      showAiLoading(true);
      if (modalTitleEl) modalTitleEl.textContent = t('knowledge_page.ai_loading_title', 'AI обрабатывает…');
      var modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(modalEl);
      if (modal) modal.show();
    }

    try {
      var url = 'api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/ai/' + action;
      var envelope = await request(url, { method: 'POST', body: {} });
      var data = envelope.data || {};

      lastAiData = data;
      lastAiAction = action;

      renderAiInModal(action, data);
      renderAiInSidebar(action, data);

      // Update insert button visibility
      var insertBtn = document.getElementById('knowledgeAiModalInsertBtn');
      if (insertBtn) {
        var isEditing = els.editor && !els.editor.classList.contains('d-none');
        var canInsert = action === 'checklist' || action === 'faq-from-comments' || action === 'summary' || action === 'explain';
        insertBtn.style.display = (isEditing && canInsert) ? '' : 'none';
      }
    } catch (e) {
      lastAiData = null;
      lastAiAction = null;
      var errMsg = e && e.message || '';
      var errStatus = e && e.status || '';
      if (modalBodyEl) {
        showAiLoading(false);
        modalBodyEl.innerHTML = '<div class="crm-ai-empty" style="color:var(--crm-danger)"><i class="fa-solid fa-triangle-exclamation"></i><p>' + esc(t('knowledge_page.ai_error', 'Ошибка AI')) + '</p>' + (errMsg ? '<p class="small text-muted">' + esc(errMsg) + (errStatus ? ' (HTTP ' + errStatus + ')' : '') + '</p>' : '<p class="small text-muted">' + esc(t('knowledge_page.ai_error_unknown', 'Неизвестная ошибка. Проверьте настройки AI-провайдера.')) + '</p>') + '<a href="index.php?route=admin-ai" class="btn btn-sm crm-btn-secondary mt-2"><i class="fa-solid fa-gear"></i> ' + esc(t('knowledge_page.ai_check_settings', 'Проверить настройки AI')) + '</a></div>';
      }
      if (sidebarBodyEl) {
        sidebarBodyEl.innerHTML = '<em class="text-danger">' + esc(t('knowledge_page.ai_error', 'Ошибка AI')) + (errMsg ? ': ' + esc(errMsg) : '') + '</em>';
      }
    }

    if (btn) {
      btn.disabled = false;
      var icon = aiBtnIcons[action] || 'fa-wand-magic-sparkles';
      var label = (aiBtnLabels[action] || function () { return action; })();
      btn.innerHTML = '<i class="fa-solid ' + icon + '" style="margin-right:0.3rem"></i>' + label;
    }
    aiInProgress = false;
  }

  // AI button click handlers
  document.getElementById('knowledgeAiSummaryBtn') && document.getElementById('knowledgeAiSummaryBtn').addEventListener('click', function () { handleAiAction('summary'); });
  document.getElementById('knowledgeAiExplainBtn') && document.getElementById('knowledgeAiExplainBtn').addEventListener('click', function () { handleAiAction('explain'); });
  document.getElementById('knowledgeAiChecklistBtn') && document.getElementById('knowledgeAiChecklistBtn').addEventListener('click', function () { handleAiAction('checklist'); });
  document.getElementById('knowledgeAiSimilarBtn') && document.getElementById('knowledgeAiSimilarBtn').addEventListener('click', function () { handleAiAction('similar'); });
  document.getElementById('knowledgeAiFaqBtn') && document.getElementById('knowledgeAiFaqBtn').addEventListener('click', function () { handleAiAction('faq-from-comments'); });

  // Modal copy button
  document.getElementById('knowledgeAiModalCopyBtn') && document.getElementById('knowledgeAiModalCopyBtn').addEventListener('click', function () {
    var text = getAiPlainText(lastAiAction, lastAiData);
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        var btn = document.getElementById('knowledgeAiModalCopyBtn');
        if (btn) { btn.innerHTML = '<i class="fa-regular fa-check-circle" style="margin-right:0.3rem"></i>' + esc(t('knowledge_page.ai_copied', 'Скопировано')); }
        window.setTimeout(function () {
          if (btn) btn.innerHTML = '<i class="fa-regular fa-clipboard" style="margin-right:0.3rem"></i>' + esc(t('knowledge_page.ai_copy', 'Копировать'));
        }, 2000);
      });
    } else {
      // Fallback
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  });

  // Modal insert button
  document.getElementById('knowledgeAiModalInsertBtn') && document.getElementById('knowledgeAiModalInsertBtn').addEventListener('click', function () {
    var html = getAiInsertHtml(lastAiAction, lastAiData);
    if (!html) return;
    if (els.visualEditor) {
      insertVisualHtml(html);
    } else if (els.editContent) {
      els.editContent.value += '\n' + html;
      els.editContent.dispatchEvent(new Event('input', { bubbles: true }));
    }
    var btn = document.getElementById('knowledgeAiModalInsertBtn');
    if (btn) { btn.innerHTML = '<i class="fa-regular fa-check-circle" style="margin-right:0.3rem"></i>' + esc(t('knowledge_page.ai_inserted', 'Вставлено')); }
    window.setTimeout(function () {
      if (btn) btn.style.display = 'none';
    }, 1500);
  });

  // Modal refresh button
  document.getElementById('knowledgeAiModalRefreshBtn') && document.getElementById('knowledgeAiModalRefreshBtn').addEventListener('click', function () {
    if (lastAiAction) handleAiAction(lastAiAction);
  });

  var allTags = [];
  async function loadTagOptions() {
    try {
      var env = await request('api/v1/tags', { method: 'GET' });
      allTags = env.data && env.data.items || [];
    } catch (e) { allTags = []; }
  }
  async function loadTags() {
    if (!els.tagsList || !pageId) return;
    try {
      var env = await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/tags', { method: 'GET' });
      var items = env.data && env.data.items || [];
      if (!items.length) {
        els.tagsList.innerHTML = '<span class="text-muted small">—</span>';
      } else {
        els.tagsList.innerHTML = items.map(function (tag) {
          var color = tag.color || '#6c757d';
          return '<span class="crm-badge" style="background:' + color + ';color:#fff;cursor:default">' + esc(tag.title || tag.code || '') + ' <i class="fa-solid fa-xmark" style="cursor:pointer;margin-left:2px" data-tag-id="' + esc(tag.public_id || '') + '" title="' + esc(t('knowledge_page.tag_remove', 'Удалить тег')) + '"></i></span>';
        }).join('');
      }
      var usedIds = {};
      items.forEach(function (tag) { usedIds[tag.public_id] = true; });
      if (els.tagSelect) {
        els.tagSelect.innerHTML = '<option value="">' + esc(t('knowledge_page.tag_select_hint', 'Выбрать тег...')) + '</option>'
          + allTags.filter(function (tag) { return !usedIds[tag.public_id]; }).map(function (tag) {
            return '<option value="' + esc(tag.public_id) + '">' + esc(tag.title || tag.code || '') + '</option>';
          }).join('');
        els.tagAddBtn.disabled = true;
      }
    } catch (e) { els.tagsList.innerHTML = '<span class="text-muted small">—</span>'; }
  }
  if (els.tagSelect) els.tagSelect.addEventListener('change', function () {
    els.tagAddBtn.disabled = !els.tagSelect.value;
  });
  if (els.tagAddBtn) els.tagAddBtn.addEventListener('click', async function () {
    var tagId = els.tagSelect && els.tagSelect.value;
    if (!tagId || !pageId) return;
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/tags/' + encodeURIComponent(tagId), { method: 'POST', idempotent: true });
      await loadTags();
    } catch (e) {}
  });
  if (els.tagsList) els.tagsList.addEventListener('click', async function (e) {
    var delIcon = e.target.closest('[data-tag-id]');
    if (!delIcon || !pageId) return;
    var tagId = delIcon.getAttribute('data-tag-id');
    if (!tagId) return;
    try {
      await request('api/v1/knowledge/pages/' + encodeURIComponent(pageId) + '/tags/' + encodeURIComponent(tagId), { method: 'DELETE', idempotent: true });
      await loadTags();
    } catch (e) {}
  });
  if (els.permBtn) els.permBtn.addEventListener('click', function () {
    loadPagePermissions();
    loadPagePermissionSubjects(els.permSubjectType ? els.permSubjectType.value : 'user');
    var modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(els.permModal);
    modal && modal.show();
  });
  if (els.permSubjectType) els.permSubjectType.addEventListener('change', function () {
    loadPagePermissionSubjects(this.value);
  });
  if (els.permAddBtn) els.permAddBtn.addEventListener('click', function () {
    addPagePermission().catch(function () {});
  });
  var tocScrollHandler = null;
  function initTocScroll() {
    if (tocScrollHandler) document.removeEventListener('scroll', tocScrollHandler);
    if (!els.toc) return;
    var links = els.toc.querySelectorAll('a');
    if (!links.length) return;
    tocScrollHandler = function () {
      var active = '';
      links.forEach(function (link) {
        var id = link.getAttribute('href') && link.getAttribute('href').substring(1);
        if (!id) return;
        var el = document.getElementById(id);
        if (el) {
          var rect = el.getBoundingClientRect();
          if (rect.top < 120) active = id;
        }
      });
      links.forEach(function (link) {
        var id = link.getAttribute('href') && link.getAttribute('href').substring(1);
        link.classList.toggle('toc-active', id === active);
      });
    };
    document.addEventListener('scroll', tocScrollHandler, { passive: true });
    tocScrollHandler();
  }
  waitForApi(async function () {
    await loadTagOptions();
    await load();
    await loadTags();
  });
})();
</script>
</body>
