<?php declare(strict_types=1); ?>
<?php $title = $t('ideas.title', 'TropaTT — Идеи'); $publicId = htmlspecialchars((string)($_GET['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<?php if ($publicId !== ''): ?>
<body data-page="ideas" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-idea-detail-page"><div class="crm-page-head crm-idea-detail-head"><div><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="index.php?route=ideas" data-i18n="ideas.breadcrumb_ideas"><?= htmlspecialchars($t('ideas.breadcrumb_ideas', 'Идеи'), ENT_QUOTES, 'UTF-8') ?></a></li><li class="breadcrumb-item active" id="ideaBreadcrumb" data-i18n="ideas.loading"><?= htmlspecialchars($t('ideas.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></li></ol><h1 class="crm-page-title" id="ideaTitle" data-i18n="ideas.loading"><?= htmlspecialchars($t('ideas.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></h1></div><div><span id="ideaStatus" class="badge"></span></div></div>

<div class="row g-4"><div class="col-lg-8">

<style id="pipelineStyleEl" nonce="<?= $csp_nonce ?>">
.pipeline-block { display:none !important; }
.pipeline-block.pipeline-visible { display:block !important; opacity:1; max-height:9999px; transition: opacity 0.4s ease; }
#aiPipelineCard { display:block !important; }
</style>
<div class="crm-card crm-section-card mb-3"><div class="crm-section-head d-flex justify-content-between"><h2 class="h6 mb-0" data-i18n="ideas.section_description"><?= htmlspecialchars($t('ideas.section_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></h2><div><button class="btn btn-sm crm-btn-muted" id="editIdeaBtn" title="<?= htmlspecialchars($t('ideas.btn_edit_idea', 'Редактировать идею'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_edit_idea" aria-label="<?= htmlspecialchars($t('ideas.btn_edit_idea', 'Редактировать идею'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_edit_idea"><i class="fa-solid fa-pen" aria-hidden="true"></i></button> <button class="btn btn-sm crm-btn-danger-icon" id="deleteIdeaBtn" style="display:none" title="<?= htmlspecialchars($t('ideas.btn_delete_idea', 'Удалить идею'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_delete_idea" aria-label="<?= htmlspecialchars($t('ideas.btn_delete_idea', 'Удалить идею'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_delete_idea"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><p id="ideaDesc" class="mb-0" data-i18n="ideas.loading"><?= htmlspecialchars($t('ideas.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></p>
<div id="editIdeaForm" class="p-3 border-top" style="display:none"><div class="mb-2"><input class="form-control form-control-sm" id="editIdeaTitle"></div><div class="mb-2"><textarea class="form-control form-control-sm" id="editIdeaDesc" rows="3" data-crm-visual-editor="1" data-richtext-off="1"></textarea></div><div class="mb-2"><label class="form-label small" data-i18n="ideas.field_visibility"><?= htmlspecialchars($t('ideas.field_visibility', 'Видимость'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select form-select-sm" id="editIdeaVisibility"><option value="public" data-i18n="ideas.opt_public"><?= htmlspecialchars($t('ideas.opt_public', 'Публичная'), ENT_QUOTES, 'UTF-8') ?></option><option value="private" data-i18n="ideas.opt_private"><?= htmlspecialchars($t('ideas.opt_private', 'Приватная'), ENT_QUOTES, 'UTF-8') ?></option></select></div><div class="mb-2"><label class="form-label small" data-i18n="ideas.field_target_date"><?= htmlspecialchars($t('ideas.field_target_date', 'Срок реализации'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control form-control-sm" type="date" id="editIdeaTargetDate"></div><button class="btn btn-sm crm-btn-primary" id="saveIdeaBtn" data-i18n="page.save"><?= htmlspecialchars($t('page.save', 'Сохранить'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-muted" id="cancelEditBtn" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="interviewCard"><div class="crm-section-head d-flex justify-content-between"><h2 class="h6 mb-0" data-i18n="ideas.section_interview"><?= htmlspecialchars($t('ideas.section_interview', 'Вопросы и ответы'), ENT_QUOTES, 'UTF-8') ?></h2><button class="btn btn-sm crm-btn-primary" id="interviewBtn" data-i18n="ideas.btn_ask_ai"><i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_ai', 'Задать вопросы AI'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearInterviewBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_interview', 'Очистить историю вопросов и ответов'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_interview" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_interview', 'Очистить историю вопросов и ответов'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_interview"><i class="fa-solid fa-eraser" aria-hidden="true"></i></button></div><div id="interviewBlock" class="p-3"><p class="text-muted mb-0" id="interviewStatus" data-i18n="ideas.state_interview_idle"><?= htmlspecialchars($t('ideas.state_interview_idle', 'Нажмите «Задать вопросы AI» для уточнения деталей идеи.'), ENT_QUOTES, 'UTF-8') ?></p><div id="interviewQuestions" style="display:none"></div><div id="interviewHistory" class="mt-3 small text-muted" style="display:none"></div></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="clarificationsCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-lightbulb me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_clarifications"><?= htmlspecialchars($t('ideas.section_clarifications', 'Дополнительные уточнения'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="clarificationsStatus"></small></h6><div><button class="btn btn-sm crm-btn-primary" id="clarifyBtn" data-i18n="ideas.btn_clarify"><i class="fa-solid fa-brain me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_clarify', 'Уточнить'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearClarificationsBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_clarifications', 'Удалить уточнения'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_clarifications" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_clarifications', 'Удалить уточнения'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_clarifications"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="clarificationsBody" class="p-3" style="display:none"></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="understandingCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_understanding_card"><?= htmlspecialchars($t('ideas.section_understanding_card', 'Карточка понимания идеи'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="cardUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="buildCardBtn" data-i18n="ideas.btn_build"><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_build', 'Собрать'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearCardBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_card', 'Очистить карточку'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_card" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_card_aria', 'Очистить карточку понимания'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_card_aria"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="understandingCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_card_not_collected"><?= htmlspecialchars($t('ideas.state_card_not_collected', 'Карточка понимания идеи еще не собрана.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="gapQuestionsCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_gap_questions"><?= htmlspecialchars($t('ideas.section_gap_questions', 'Каких данных не хватает'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="gapStatus"></small></h6><div><button class="btn btn-sm crm-btn-primary" id="gapBtn" data-i18n="ideas.btn_clarify"><i class="fa-solid fa-brain me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_clarify', 'Уточнить'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearGapBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_gaps', 'Удалить уточнения по пробелам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_gaps" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_gaps', 'Удалить уточнения по пробелам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_gaps"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="gapBody" class="p-3" style="display:none"></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="refinedCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-clipboard-check me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_refined_card"><?= htmlspecialchars($t('ideas.section_refined_card', 'Уточненная карточка понимания идеи'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="refinedUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="buildRefinedBtn" data-i18n="ideas.btn_build"><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_build', 'Собрать'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearRefinedBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_refined', 'Очистить уточненную карточку'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_refined" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_refined', 'Очистить уточненную карточку'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_refined"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="refinedCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_refined_not_collected"><?= htmlspecialchars($t('ideas.state_refined_not_collected', 'Уточненная карточка еще не собрана.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="potentialCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-chart-simple me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_potential"><?= htmlspecialchars($t('ideas.section_potential', 'Потенциал идеи'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="potentialUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="calcPotentialBtn" data-i18n="ideas.btn_calculate"><i class="fa-solid fa-calculator me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_calculate', 'Рассчитать'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearPotentialBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_potential', 'Очистить расчет'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_potential" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_potential_aria', 'Очистить расчет потенциала'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_potential_aria"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="potentialCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_potential_not_calculated"><?= htmlspecialchars($t('ideas.state_potential_not_calculated', 'Потенциал идеи еще не рассчитан.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="riskCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_risks"><?= htmlspecialchars($t('ideas.section_risks', 'Риски идеи'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="riskUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="calcRiskBtn" data-i18n="ideas.btn_calc_risks"><i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_calc_risks', 'Рассчитать риски'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearRiskBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_risk', 'Очистить риск-отчет'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_risk" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_risk_aria', 'Очистить отчет по рискам'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_risk_aria"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="riskCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_risks_not_calculated"><?= htmlspecialchars($t('ideas.state_risks_not_calculated', 'Риски идеи еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="pitfallsCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-person-digging me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_pitfalls"><?= htmlspecialchars($t('ideas.section_pitfalls', 'Подводные камни'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="pitfallsUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="calcPitfallsBtn" data-i18n="ideas.btn_calculate"><i class="fa-solid fa-magnifying-glass-chart me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_calculate', 'Рассчитать'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearPitfallsBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_pitfalls', 'Очистить подводные камни'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_pitfalls" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_pitfalls', 'Очистить подводные камни'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_pitfalls"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="pitfallsCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_pitfalls_not_calculated"><?= htmlspecialchars($t('ideas.state_pitfalls_not_calculated', 'Подводные камни еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="planCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-list-check me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_plan"><?= htmlspecialchars($t('ideas.section_plan', 'План реализации'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="planUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="buildPlanBtn" data-i18n="ideas.btn_build"><i class="fa-solid fa-play me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_build', 'Собрать'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearPlanBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_plan', 'Очистить план'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_plan" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_plan_aria', 'Очистить план реализации'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_plan_aria"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="planCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_plan_not_collected"><?= htmlspecialchars($t('ideas.state_plan_not_collected', 'План реализации еще не собран.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="finalCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-star me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_final"><?= htmlspecialchars($t('ideas.section_final', 'Итоговая рекомендация'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="finalUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="buildFinalBtn" data-i18n="ideas.btn_build_final"><i class="fa-solid fa-gavel me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_build_final', 'Сформировать'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearFinalBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_final', 'Очистить рекомендацию'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_final" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_final_aria', 'Очистить итоговую рекомендацию'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_final_aria"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="finalCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_final_not_generated"><?= htmlspecialchars($t('ideas.state_final_not_generated', 'Итоговая рекомендация еще не сформирована.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card mb-3 pipeline-block" id="tasksCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-diagram-project me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_tasks"><?= htmlspecialchars($t('ideas.section_tasks', 'Предлагаемые задачи'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="tasksUpdated"></small></h6><div><button class="btn btn-sm crm-btn-success" id="buildTasksBtn" data-i18n="ideas.btn_build"><i class="fa-solid fa-list-tree me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_build', 'Сформировать'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="clearTasksBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_tasks', 'Очистить задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_tasks" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_tasks_aria', 'Очистить предлагаемые задачи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_tasks_aria"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><div id="tasksCardBody" class="p-3"><p class="text-muted mb-0" data-i18n="ideas.state_tasks_not_generated"><?= htmlspecialchars($t('ideas.state_tasks_not_generated', 'Предлагаемые задачи еще не сформированы.'), ENT_QUOTES, 'UTF-8') ?></p></div></div>

<div class="crm-card crm-section-card"><div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="ideas.section_comments"><?= htmlspecialchars($t('ideas.section_comments', 'Комментарии'), ENT_QUOTES, 'UTF-8') ?></h2></div><div id="commentsSection"><p class="text-muted" data-i18n="ideas.loading"><?= htmlspecialchars($t('ideas.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></p></div><div class="p-3 border-top"><textarea class="form-control mb-2" id="commentInput" rows="2" placeholder="<?= htmlspecialchars($t('ideas.placeholder_comment', 'Добавить комментарий...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="ideas.placeholder_comment" data-crm-visual-editor="1" data-richtext-off="1"></textarea><button class="btn crm-btn-primary btn-sm" id="addCommentBtn" data-i18n="ideas.btn_send"><?= htmlspecialchars($t('ideas.btn_send', 'Отправить'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<div class="crm-card crm-section-card mt-3 pipeline-block" id="debugCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-bug me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_ai_logs"><?= htmlspecialchars($t('ideas.section_ai_logs', 'Логи AI'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="debugRefreshed"></small></h6><div><button class="btn btn-sm crm-btn-secondary me-1" id="refreshDebugBtn" title="<?= htmlspecialchars($t('ideas.btn_refresh_logs', 'Обновить логи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_refresh_logs" aria-label="<?= htmlspecialchars($t('ideas.btn_refresh_logs', 'Обновить логи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_refresh_logs"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button><button class="btn btn-sm crm-btn-danger-icon" id="clearDebugBtn" title="<?= htmlspecialchars($t('ideas.btn_clear_logs', 'Очистить логи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_clear_logs" aria-label="<?= htmlspecialchars($t('ideas.btn_clear_logs', 'Очистить логи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_clear_logs"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div></div><pre id="debugLog" style="font-size:0.7rem;max-height:800px;overflow:auto;background:var(--crm-terminal-bg);color:var(--crm-terminal-text);padding:10px;border-radius:0 0 6px 6px;margin:0;line-height:1.4" data-i18n="ideas.loading"><?= htmlspecialchars($t('ideas.loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></pre></div>

</div>

<div class="col-lg-4 crm-idea-side">
<div class="crm-card crm-section-card mb-3 pipeline-block" id="aiPipelineCard"><div class="crm-section-head d-flex justify-content-between"><h6 class="mb-0"><i class="fa-solid fa-play me-1" aria-hidden="true"></i> <span data-i18n="ideas.section_ai_analysis"><?= htmlspecialchars($t('ideas.section_ai_analysis', 'AI-анализ'), ENT_QUOTES, 'UTF-8') ?></span> <small class="text-muted ms-2" id="pipelineStatus"></small></h6><div><button class="btn btn-sm crm-btn-primary" id="startPipelineBtn" data-i18n="ideas.btn_start_pipeline"><i class="fa-solid fa-forward-step me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_start_pipeline', 'Запустить'), ENT_QUOTES, 'UTF-8') ?></button> <button class="btn btn-sm crm-btn-danger-icon" id="resetPipelineBtn" title="<?= htmlspecialchars($t('ideas.btn_reset_pipeline', 'Сбросить прогресс'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_reset_pipeline" aria-label="<?= htmlspecialchars($t('ideas.btn_reset_pipeline_aria', 'Сбросить прогресс AI-анализа'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_reset_pipeline_aria"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button> <button class="btn btn-sm crm-btn-secondary" id="showDebugBtn" title="<?= htmlspecialchars($t('ideas.btn_show_logs', 'Показать логи'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_show_logs" aria-label="<?= htmlspecialchars($t('ideas.btn_show_logs_aria', 'Показать логи AI'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.btn_show_logs_aria"><i class="fa-solid fa-bug" aria-hidden="true"></i></button></div></div><div id="pipelineSteps" class="p-3"></div></div>

<div class="crm-card crm-section-card mb-3"><div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="ideas.section_info"><?= htmlspecialchars($t('ideas.section_info', 'Информация'), ENT_QUOTES, 'UTF-8') ?></h2></div><table class="table crm-table crm-idea-info-table mb-0"><tbody><tr><td data-i18n="ideas.info_author"><?= htmlspecialchars($t('ideas.info_author', 'Автор'), ENT_QUOTES, 'UTF-8') ?></td><td id="ideaAuthor">—</td></tr><tr><td data-i18n="ideas.info_category"><?= htmlspecialchars($t('ideas.info_category', 'Категория'), ENT_QUOTES, 'UTF-8') ?></td><td id="ideaCategory">—</td></tr><tr><td data-i18n="ideas.info_region"><?= htmlspecialchars($t('ideas.info_region', 'Регион'), ENT_QUOTES, 'UTF-8') ?></td><td id="ideaRegion">—</td></tr><tr><td data-i18n="ideas.info_visibility"><?= htmlspecialchars($t('ideas.info_visibility', 'Видимость'), ENT_QUOTES, 'UTF-8') ?></td><td id="ideaVisibility">—</td></tr><tr><td data-i18n="ideas.info_target_date"><?= htmlspecialchars($t('ideas.info_target_date', 'Срок'), ENT_QUOTES, 'UTF-8') ?></td><td id="ideaTargetDate">—</td></tr><tr><td data-i18n="ideas.info_date"><?= htmlspecialchars($t('ideas.info_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?></td><td id="ideaDate">—</td></tr><tr><td data-i18n="ideas.info_votes"><?= htmlspecialchars($t('ideas.info_votes', 'Голоса'), ENT_QUOTES, 'UTF-8') ?></td><td id="ideaVotes">0</td></tr></tbody></table></div>

<div class="crm-card crm-section-card"><div class="crm-section-head"><h2 class="h6 mb-0" data-i18n="ideas.section_voting"><?= htmlspecialchars($t('ideas.section_voting', 'Голосование'), ENT_QUOTES, 'UTF-8') ?></h2></div><button class="btn crm-btn-primary w-100" id="voteBtn" data-i18n="ideas.btn_vote"><i class="fa-solid fa-thumbs-up me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_vote', 'Голосовать'), ENT_QUOTES, 'UTF-8') ?></button></div>

</div></div>
</main></div></div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteIdeaModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" data-i18n="ideas.modal_delete_title"><?= htmlspecialchars($t('ideas.modal_delete_title', 'Удалить идею'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p data-i18n="ideas.modal_delete_body"><?= htmlspecialchars($t('ideas.modal_delete_body', 'Вы уверены, что хотите удалить эту идею? Это действие нельзя отменить.'), ENT_QUOTES, 'UTF-8') ?></p></div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="button" class="btn crm-btn-danger-soft" id="confirmDeleteBtn" data-i18n="page.delete"><?= htmlspecialchars($t('page.delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </div></div>
</div>

<script>
window.CRM = window.CRM || {};
window.CRM.ideaLocale = window.CRM.ideaLocale || function () {
  return String((window.CRM && (window.CRM.locale || window.CRM.currentLocale)) || document.documentElement.lang || 'en-GB').replace('_', '-');
};
// === AI Pipeline: sequential block execution with proper save detection ===
(function(){
  var steps = [
    {id:'interview',       actionEl:'#interviewBtn',        saveEl:'#saveIvAnswersBtn',       statusEl:'#interviewStatus',       cardId:'interviewCard',       desc:'<?= htmlspecialchars($t('ideas.pipeline_interview', 'Вопросы и ответы'), ENT_QUOTES, 'UTF-8') ?>',              type:'questions', parent:'#interviewQuestions'},
    {id:'clarifications',  actionEl:'#clarifyBtn',          saveEl:'#saveClarificationsBtn',  statusEl:'#clarificationsStatus',   cardId:'clarificationsCard',   desc:'<?= htmlspecialchars($t('ideas.pipeline_clarifications', 'Дополнительные уточнения'), ENT_QUOTES, 'UTF-8') ?>',      type:'questions', parent:'#clarificationsBody', saveLabel:'<?= htmlspecialchars($t('ideas.pipeline_clarifications_saved', 'Уточнения сохранены'), ENT_QUOTES, 'UTF-8') ?>'},
    {id:'understanding',   actionEl:'#buildCardBtn',        statusEl:'#cardUpdated',          okCheck:function(){return hasAnalysisResult('#understandingCardBody',['<?= htmlspecialchars($t('ideas.analysis_short_summary', 'Краткое резюме'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_idea_type', 'Тип идеи'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'understandingCard',  desc:'<?= htmlspecialchars($t('ideas.pipeline_understanding', 'Карточка понимания идеи'), ENT_QUOTES, 'UTF-8') ?>',       type:'analysis'},
    {id:'gapQuestions',    actionEl:'#gapBtn',              saveEl:'#saveGapBtn',             statusEl:'#gapStatus',             cardId:'gapQuestionsCard',    desc:'<?= htmlspecialchars($t('ideas.pipeline_gaps', 'Каких данных не хватает'), ENT_QUOTES, 'UTF-8') ?>',       type:'questions', parent:'#gapBody', saveLabel:'<?= htmlspecialchars($t('ideas.pipeline_gaps_saved', 'Уточнения сохранены'), ENT_QUOTES, 'UTF-8') ?>'},
    {id:'refined',         actionEl:'#buildRefinedBtn',     statusEl:'#refinedUpdated',       okCheck:function(){return hasAnalysisResult('#refinedCardBody',['<?= htmlspecialchars($t('ideas.analysis_short_summary', 'Краткое резюме'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_idea_type', 'Тип идеи'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'refinedCard', desc:'<?= htmlspecialchars($t('ideas.pipeline_refined', 'Уточненная карточка'), ENT_QUOTES, 'UTF-8') ?>',          type:'analysis'},
    {id:'potential',       actionEl:'#calcPotentialBtn',    statusEl:'#potentialUpdated',     okCheck:function(){return hasAnalysisResult('#potentialCardBody',['<?= htmlspecialchars($t('ideas.analysis_out_of', 'из 100'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_potential', 'Потенциал'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_verdict', 'Вывод'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'potentialCard', desc:'<?= htmlspecialchars($t('ideas.pipeline_potential', 'Потенциал идеи'), ENT_QUOTES, 'UTF-8') ?>',               type:'analysis'},
    {id:'risks',           actionEl:'#calcRiskBtn',         statusEl:'#riskUpdated',          okCheck:function(){return hasAnalysisResult('#riskCardBody',['<?= htmlspecialchars($t('ideas.analysis_risk_level', 'Уровень риска'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_assessment', 'Оценка'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'riskCard', desc:'<?= htmlspecialchars($t('ideas.pipeline_risks', 'Риски идеи'), ENT_QUOTES, 'UTF-8') ?>',                   type:'analysis'},
    {id:'pitfalls',        actionEl:'#calcPitfallsBtn',     statusEl:'#pitfallsUpdated',      okCheck:function(){return hasAnalysisResult('#pitfallsCardBody',['<?= htmlspecialchars($t('ideas.analysis_complexity', 'Сложность'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_found', 'Найдено'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'pitfallsCard', desc:'<?= htmlspecialchars($t('ideas.pipeline_pitfalls', 'Подводные камни'), ENT_QUOTES, 'UTF-8') ?>',              type:'analysis'},
    {id:'plan',            actionEl:'#buildPlanBtn',        statusEl:'#planUpdated',          okCheck:function(){return hasAnalysisResult('#planCardBody',['<?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_stages', 'Этапы'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_next_7_days', 'Ближайшие 7 дней'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'planCard', desc:'<?= htmlspecialchars($t('ideas.pipeline_plan', 'План реализации'), ENT_QUOTES, 'UTF-8') ?>',              type:'analysis'},
    {id:'final',           actionEl:'#buildFinalBtn',       statusEl:'#finalUpdated',         okCheck:function(){return hasAnalysisResult('#finalCardBody',['<?= htmlspecialchars($t('ideas.analysis_out_of', 'из 100'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_recommendation', 'Рекомендация'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_verdict', 'Вывод'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'finalCard', desc:'<?= htmlspecialchars($t('ideas.pipeline_final', 'Итоговая рекомендация'), ENT_QUOTES, 'UTF-8') ?>',        type:'analysis'},
    {id:'tasks',           actionEl:'#buildTasksBtn',       statusEl:'#tasksUpdated',         okCheck:function(){return hasAnalysisResult('#tasksCardBody',['<?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($t('ideas.analysis_stage', 'Этап'), ENT_QUOTES, 'UTF-8') ?>']);}, cardId:'tasksCard', desc:'<?= htmlspecialchars($t('ideas.pipeline_tasks', 'Предлагаемые задачи'), ENT_QUOTES, 'UTF-8') ?>',          type:'analysis'},
  ];

  var storageKey = 'ai_pipeline_<?=e($publicId)?>';
  var state = {};
  var running = false;
  var pipelineRunToken = 0;
  var saveIntercepted = false;
  var visibleResults = {final:false,tasks:false};

	  function loadState(){try{state=JSON.parse(localStorage.getItem(storageKey)||'{}');}catch(e){state={};}if(!state.steps)state.steps=steps.map(function(s){return{id:s.id,status:'pending'};});}
	  function saveState(){localStorage.setItem(storageKey,JSON.stringify(state));}
	  function successCount(){return state.steps.filter(function(s){return s.status==='success';}).length;}
	  function startButtonLabel(){
	    var done=successCount();
	    if(done===0)return '<?= htmlspecialchars($t('ideas.pipeline_btn_start', 'Запустить'), ENT_QUOTES, 'UTF-8') ?>';
	    if(done>=state.steps.length)return '<?= htmlspecialchars($t('ideas.pipeline_btn_restart', 'Перезапустить'), ENT_QUOTES, 'UTF-8') ?>';
	    return '<?= htmlspecialchars($t('ideas.pipeline_btn_continue', 'Продолжить'), ENT_QUOTES, 'UTF-8') ?>';
	  }
	  function setStartButtonIdle(){
	    var btn=document.getElementById('startPipelineBtn');
	    if(!btn)return;
	    btn.disabled=false;
	    btn.innerHTML='<i class="fa-solid fa-forward-step me-1" aria-hidden="true"></i> '+startButtonLabel();
	  }
	  function setAllSteps(status){
	    state.steps=steps.map(function(s){return{id:s.id,status:status||'pending'};});
	    saveState();
	    renderSteps();
	  }
	  function hasAnalysisResult(selector,markers){
	    var el=document.querySelector(selector);
	    if(!el)return false;
	    var text=String(el.textContent||'').trim();
	    if(text.length<80)return false;
	    if(text.indexOf('AI ')===0||text.indexOf('<?= htmlspecialchars($t('ideas.state_error_prefix', 'Ошибка'), ENT_QUOTES, 'UTF-8') ?>')!==-1||text.indexOf('<?= htmlspecialchars($t('ideas.state_not_prefix', 'еще не'), ENT_QUOTES, 'UTF-8') ?>')!==-1)return false;
	    return markers.some(function(marker){return text.indexOf(marker)!==-1;})||text.length>500;
	  }

	  function renderSteps(){
	    var h='<div class="list-group list-group-flush small">';
    var rows=state.steps.map(function(s,i){return{state:s,index:i,def:steps[i]};});
    rows.forEach(function(row){
      var s=row.state;
      var i=row.index;
      var d=steps.find(function(x){return x.id===s.id;})||{desc:s.id};
      var icon=s.status==='success'?'<span class="crm-pipeline-mark crm-pipeline-mark-success" aria-hidden="true"></span>':s.status==='error'?'<span class="crm-pipeline-mark crm-pipeline-mark-error" aria-hidden="true"></span>':s.status==='running'?'<span class="spinner-border spinner-border-sm me-1"></span>':'<span class="crm-pipeline-mark" aria-hidden="true"></span>';
      var cls=s.status==='running'?'list-group-item pipeline-running':s.status==='error'?'list-group-item pipeline-error':s.status==='success'?'list-group-item pipeline-success':'list-group-item';
      h+='<div class="'+cls+'">'+icon+' <strong>'+(i+1)+'.</strong> '+d.desc+'</div>';
    });
	    h+='</div>';
	    document.getElementById('pipelineSteps').innerHTML=h;
	    document.getElementById('pipelineStatus').textContent=successCount()+'/'+state.steps.length;
	    if(!running)setStartButtonIdle();
	  }
	  function resumePipelineSoon(forceRestart){
	    if(forceRestart){
	      pipelineRunToken++;
	      running=false;
	    }
	    setTimeout(function(){runPipeline();},600);
	  }

	  function syncIdeaAiStepState(stepId,isReady,autoResume){
	    var shouldResume=!!(isReady&&state.awaitingQuestionStep===stepId);
	    state.steps.forEach(function(step){
	      if(step.id===stepId&&isReady){
	        step.status='success';
	      }else if(step.id===stepId&&step.status!=='running'){
	        step.status=isReady?'success':'pending';
	      }
	    });
	    if(isReady&&state.awaitingQuestionStep===stepId){
	      delete state.awaitingQuestionStep;
	      delete state.awaitingQuestionIndex;
	    }
	    saveState();
	    renderSteps();
	    if(isReady&&(autoResume||shouldResume)&&['interview','clarifications','gapQuestions'].indexOf(stepId)!==-1){
	      resumePipelineSoon(!!autoResume);
	    }
	  }
	  function syncIdeaAiResultVisibilityState(kind,isVisible){
	    if(kind==='final'||kind==='tasks'){
	      visibleResults[kind]=!!isVisible;
	      syncIdeaAiStepState(kind,isVisible,false);
	    }
	  }
	  function installIdeaAiPipelineHooks(){
	    window.CRM=window.CRM||{};
	    window.CRM.syncIdeaAiStep=function(stepId,isReady){syncIdeaAiStepState(stepId,isReady,false);};
	    window.CRM.syncIdeaAiResultVisibility=syncIdeaAiResultVisibilityState;
	    window.CRM.resumeIdeaAiPipelineAfterQuestions=resumeIdeaAiPipelineAfterQuestions;
	    if(document.documentElement)document.documentElement.setAttribute('data-idea-ai-pipeline-hooks','ready');
	  }
	  window.CRM_IDEA_AI_PIPELINE={
	    syncStep:function(stepId,isReady){syncIdeaAiStepState(stepId,isReady,false);},
	    syncResultVisibility:syncIdeaAiResultVisibilityState,
	    resumeAfterQuestions:resumeIdeaAiPipelineAfterQuestions
	  };
	  installIdeaAiPipelineHooks();
	  var hookInstallCount=0;
	  var hookInstallTimer=setInterval(function(){
	    installIdeaAiPipelineHooks();
	    hookInstallCount++;
	    if(hookInstallCount>30)clearInterval(hookInstallTimer);
	  },1000);

  function sleep(ms){return new Promise(function(r){setTimeout(r,ms);});}

  function showBlock(cardId){
    var el=document.getElementById(cardId);
    if(!el)return;
    el.classList.add('pipeline-visible');
    setTimeout(function(){el.scrollIntoView({behavior:'smooth',block:'center'});},100);
  }
  function hideBlock(cardId){
    var el=document.getElementById(cardId);
    if(!el)return;
    el.classList.remove('pipeline-visible');
    setTimeout(function(){scrollToPipeline();},400);
  }
  function scrollToPipeline(){
    var el=document.getElementById('aiPipelineCard');
    if(el)el.scrollIntoView({behavior:'smooth',block:'start'});
  }

  // Wait for condition with timeout, polling every 1s
	  function waitFor(checkFn, timeoutMs, desc){
	    return new Promise(function(resolve){
	      var start=Date.now();
	      function poll(){
	        try{var ok=checkFn();if(ok){resolve(true);return;}}catch(e){}
        if(Date.now()-start>timeoutMs){resolve(false);return;}
        setTimeout(poll,1000);
      }
	      poll();
	    });
	  }

	  function hasVisibleQuestions(stepDef){
	    var p=document.querySelector(stepDef.parent);
	    return !!(p&&p.querySelectorAll('div[data-qid]').length>0&&p.style.display!=='none'&&window.getComputedStyle(p).display!=='none');
	  }

	  function questionStepIsComplete(stepDef){
	    if(hasVisibleQuestions(stepDef))return false;
	    var se=document.querySelector(stepDef.statusEl);
	    var text=se?String(se.textContent||''):'';
	    return text.indexOf('<?= htmlspecialchars($t('ideas.state_all_answered', 'Все вопросы отвечены'), ENT_QUOTES, 'UTF-8') ?>')!==-1||
	      text.indexOf('<?= htmlspecialchars($t('ideas.state_limit_reached', 'Достигнут лимит'), ENT_QUOTES, 'UTF-8') ?>')!==-1||
	      text.indexOf('<?= htmlspecialchars($t('ideas.state_answers_saved', 'Ответы сохранены'), ENT_QUOTES, 'UTF-8') ?>')!==-1||
	      text.indexOf(stepDef.saveLabel||'__never__')!==-1||
	      text.indexOf('<?= htmlspecialchars($t('ideas.state_no_clarifications', '— нет уточнений'), ENT_QUOTES, 'UTF-8') ?>')!==-1||
	      text.indexOf('<?= htmlspecialchars($t('ideas.state_no_questions', '— нет вопросов'), ENT_QUOTES, 'UTF-8') ?>')!==-1;
	  }

	  function finishQuestionStep(index, stepDef){
	    state.steps[index].status='success';
	    if(state.awaitingQuestionStep===stepDef.id){
	      delete state.awaitingQuestionStep;
	      delete state.awaitingQuestionIndex;
	    }
	    saveState();
	    renderSteps();
	    if(stepDef.cardId)hideBlock(stepDef.cardId);
	    document.getElementById('pipelineStatus').textContent=stepDef.desc+' <?= htmlspecialchars($t('ideas.state_done', 'готово'), ENT_QUOTES, 'UTF-8') ?>';
	  }

	  function rememberQuestionWait(index,stepDef){
	    state.awaitingQuestionStep=stepDef.id;
	    state.awaitingQuestionIndex=index;
	    state.steps[index].status='running';
	    saveState();
	    renderSteps();
	  }

	  function resumeIdeaAiPipelineAfterQuestions(stepId){
	    var idx=steps.findIndex(function(s){return s.id===stepId;});
	    if(idx<0)return;
	    finishQuestionStep(idx,steps[idx]);
	    syncIdeaAiStepState(stepId,true,true);
	  }

	  async function resetQuestionsForRestart(){
	    if(!window.CRM||!window.CRM.api)return;
	    document.getElementById('pipelineStatus').textContent='<?= htmlspecialchars($t('ideas.state_clearing_qa', 'Очищаю вопросы и ответы...'), ENT_QUOTES, 'UTF-8') ?>';
	    var ideaId='<?=e($publicId)?>';
	    var endpoints=[
	      'api/v1/ideas/'+ideaId+'/interview',
	      'api/v1/ideas/'+ideaId+'/additional-questions',
	      'api/v1/ideas/'+ideaId+'/gap-questions'
	    ];
	    for(var i=0;i<endpoints.length;i++){
	      try{await window.CRM.api.request(endpoints[i],{method:'DELETE',timeoutMs:15000});}catch(e){}
	    }
	    var qs=document.getElementById('interviewQuestions');if(qs){qs.innerHTML='';qs.style.display='none';}
	    var ih=document.getElementById('interviewHistory');if(ih){ih.innerHTML='';ih.style.display='none';}
	    var cs=document.getElementById('clarificationsBody');if(cs){cs.innerHTML='';cs.style.display='none';}
	    var gb=document.getElementById('gapBody');if(gb){gb.innerHTML='';gb.style.display='none';}
	    var st=document.getElementById('interviewStatus');if(st){st.textContent='<?= htmlspecialchars($t('ideas.state_questions_cleared', 'Вопросы очищены. AI задаст новые вопросы.'), ENT_QUOTES, 'UTF-8') ?>';st.style.color='';}
	    var cst=document.getElementById('clarificationsStatus');if(cst){cst.textContent='';cst.style.color='';}
	    var gst=document.getElementById('gapStatus');if(gst){gst.textContent='';gst.style.color='';}
	  }

	  // Run one step
	  async function runStep(index,runToken){
    if(runToken!==pipelineRunToken)return;
    var stepDef=steps[index];
    var s=state.steps[index];
    s.status='running';saveState();renderSteps();
    document.getElementById('pipelineStatus').textContent=stepDef.desc;

    // Show only question blocks; analysis blocks run in background
    if(stepDef.type==='questions'&&stepDef.cardId) showBlock(stepDef.cardId);

    // Scroll to block
    var card=document.getElementById(stepDef.id+'Card')||document.querySelector(stepDef.actionEl)?.closest('.crm-card');
    if(card)card.scrollIntoView({behavior:'smooth',block:'center'});
    await sleep(800);

	    if(stepDef.type==='questions'){
	      // --- Question block: click action, wait for questions, wait for user save ---
	      var actionBtn=document.querySelector(stepDef.actionEl);
	      if(!actionBtn||actionBtn.disabled){s.status='pending';saveState();renderSteps();return;}

	      if(questionStepIsComplete(stepDef)){
	        finishQuestionStep(index,stepDef);
	        return;
	      }

	      // Check if questions already exist (from previous run)
	      var hasExistingQs=hasVisibleQuestions(stepDef);

	      if(!hasExistingQs){
	        actionBtn.click();
	        // Wait for questions to appear, or for API/UI to confirm this step is already closed.
	        var qsAppeared=await waitFor(function(){
	          return hasVisibleQuestions(stepDef)||questionStepIsComplete(stepDef);
	        },180000,'questions appear or complete');
	        if(runToken!==pipelineRunToken)return;
	        if(questionStepIsComplete(stepDef)){
	          finishQuestionStep(index,stepDef);
	          return;
	        }
	        if(!qsAppeared){s.status='error';saveState();renderSteps();return;}
	      }

		      document.getElementById('pipelineStatus').textContent='<?= htmlspecialchars($t('ideas.state_waiting_answers', 'Ожидает ответов:'), ENT_QUOTES, 'UTF-8') ?> '+stepDef.desc;
		      rememberQuestionWait(index,stepDef);

	      // Wait for user to save answers — event-based detection
	      var saveDone=false;var saveError=false;
      var saveBtnEl=document.querySelector(stepDef.saveEl);
      var onSaveSuccess=function(){saveDone=true;};
      var onSaveError=function(){saveError=true;};
      if(saveBtnEl){
        var origClick=saveBtnEl.onclick;
        saveBtnEl.addEventListener('click',function(e){
          // After click, monitor status for completion
          var checkInterval=setInterval(function(){
            var se=document.querySelector(stepDef.statusEl);
            var p=document.querySelector(stepDef.parent);
            // Success: status shows saved OR questions block hidden
            if(se&&(se.textContent.includes('<?= htmlspecialchars($t('ideas.state_answers_saved', 'Ответы сохранены'), ENT_QUOTES, 'UTF-8') ?>')||se.textContent.includes('<?= htmlspecialchars($t('ideas.state_saved', 'сохранен'), ENT_QUOTES, 'UTF-8') ?>')||se.textContent.includes(stepDef.saveLabel||''))){
              clearInterval(checkInterval);saveDone=true;
            }
            if(p&&(p.style.display==='none'||p.offsetHeight===0||window.getComputedStyle(p).display==='none')){
              clearInterval(checkInterval);saveDone=true;
            }
            // Error: status shows error or red color
            if(se&&(se.textContent.includes('<?= htmlspecialchars($t('ideas.state_error_prefix', 'Ошибка'), ENT_QUOTES, 'UTF-8') ?>')||(se.style&&se.style.color==='red'))){
              clearInterval(checkInterval);saveError=true;
            }
          },300);
          // Auto-cleanup after 5 minutes
          setTimeout(function(){clearInterval(checkInterval);},300000);
        },{once:false});
	      }
	      // Wait for completion
	      var saveOk=await waitFor(function(){
	        if(questionStepIsComplete(stepDef))saveDone=true;
	        return saveDone||saveError;
		      },86400000,'save complete');
		      if(runToken!==pipelineRunToken)return;
		      if(saveError){s.status='error';saveState();renderSteps();return;}
		      if(!saveDone){s.status='pending';saveState();renderSteps();return;}
      s.status='success';saveState();renderSteps();
      // Hide question blocks after success
      if(stepDef.cardId) hideBlock(stepDef.cardId);
      scrollToPipeline();
    } else {
      // --- Analysis block: run in background, always process ---
      var actionBtn=document.querySelector(stepDef.actionEl);
      if(!actionBtn||actionBtn.disabled){s.status='pending';saveState();renderSteps();return;}

      actionBtn.click();
      var resultOk=await waitFor(function(){
        return stepDef.okCheck?stepDef.okCheck():false;
      },180000,'analysis result');
      if(runToken!==pipelineRunToken)return;
      s.status=resultOk?'success':'error';
      saveState();renderSteps();
    }

    document.getElementById('pipelineStatus').textContent=s.status==='success'?stepDef.desc+' <?= htmlspecialchars($t('ideas.state_done', 'готово'), ENT_QUOTES, 'UTF-8') ?>':stepDef.desc+' <?= htmlspecialchars($t('ideas.state_stopped', 'остановлено'), ENT_QUOTES, 'UTF-8') ?>';
    if(s.status==='error') running=false;
  }

	  async function runPipeline(){
	    if(running)return;running=true;
	    var runToken=++pipelineRunToken;
	    var wasComplete=state.steps.length>0&&state.steps.every(function(s){return s.status==='success';});
	    document.getElementById('startPipelineBtn').disabled=true;
	    document.getElementById('startPipelineBtn').innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_analyzing', 'Анализ'), ENT_QUOTES, 'UTF-8') ?>';
	    if(wasComplete){
	      await resetQuestionsForRestart();
	      setAllSteps('pending');
	    }

	    for(var i=0;i<steps.length;i++){
      var s=state.steps[i];
      if(s.status==='success')continue;
      if(s.status==='error'){
        if(!confirm('<?= htmlspecialchars($t('ideas.confirm_retry_block', 'Блок «'), ENT_QUOTES, 'UTF-8') ?>'+steps[i].desc+'<?= htmlspecialchars($t('ideas.confirm_retry_block_end', '» ранее завершился ошибкой. Попробовать снова?'), ENT_QUOTES, 'UTF-8') ?>'))continue;
        s.status='pending';saveState();
      }
      await runStep(i,runToken);
      if(runToken!==pipelineRunToken)return;
      if(state.steps[i].status==='error')break;
	    }
	    running=false;
	    setStartButtonIdle();
	    if(state.steps.every(function(s){return s.status==='success';})){
	      document.getElementById('pipelineStatus').textContent='<?= htmlspecialchars($t('ideas.state_all_done', 'Все блоки выполнены'), ENT_QUOTES, 'UTF-8') ?>';
	      visibleResults.final=true;visibleResults.tasks=true;renderSteps();
      setTimeout(function(){showBlock('finalCard');showBlock('tasksCard');},500);
    }
  }

  document.getElementById('startPipelineBtn').addEventListener('click',runPipeline);
	  document.getElementById('showDebugBtn').addEventListener('click',function(){
	    var dbg=document.getElementById('debugCard');if(!dbg)return;
	    dbg.classList.toggle('pipeline-visible');
	    if(dbg.classList.contains('pipeline-visible')) setTimeout(function(){dbg.scrollIntoView({behavior:'smooth',block:'center'});},100);
	  });
	  function resetPipelineUi(){
	    localStorage.removeItem(storageKey);
	    visibleResults.final=false;visibleResults.tasks=false;
	    state.steps=steps.map(function(s){return{id:s.id,status:'pending'};});
	    state.awaitingQuestionIndex=null;
	    saveState();renderSteps();
	    var status=document.getElementById('pipelineStatus');if(status)status.textContent='';
	    var st=document.getElementById('interviewStatus');if(st){st.textContent='<?= htmlspecialchars($t('ideas.state_interview_idle', 'Нажмите «Задать вопросы AI» для уточнения деталей идеи.'), ENT_QUOTES, 'UTF-8') ?>';st.style.color='';}
	    var replacements={
	      interviewQuestions:'',interviewHistory:'',clarificationsBody:'',gapBody:'',debugLog:'<?= htmlspecialchars($t('ideas.state_logs_cleared', 'Логи очищены («Обновить» чтобы загрузить заново)'), ENT_QUOTES, 'UTF-8') ?>'
	    };
	    Object.keys(replacements).forEach(function(id){var el=document.getElementById(id);if(el){el.innerHTML=replacements[id];if(id!=='debugLog')el.style.display='none';}});
	    var textBlocks={
	      understandingCardBody:'<?= htmlspecialchars($t('ideas.state_card_not_collected', 'Карточка понимания идеи еще не собрана.'), ENT_QUOTES, 'UTF-8') ?>',
	      refinedCardBody:'<?= htmlspecialchars($t('ideas.state_refined_not_collected', 'Уточненная карточка еще не собрана.'), ENT_QUOTES, 'UTF-8') ?>',
	      potentialCardBody:'<?= htmlspecialchars($t('ideas.state_potential_not_calculated', 'Потенциал идеи еще не рассчитан.'), ENT_QUOTES, 'UTF-8') ?>',
	      riskCardBody:'<?= htmlspecialchars($t('ideas.state_risks_not_calculated', 'Риски идеи еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?>',
	      pitfallsCardBody:'<?= htmlspecialchars($t('ideas.state_pitfalls_not_calculated', 'Подводные камни еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?>',
	      planCardBody:'<?= htmlspecialchars($t('ideas.state_plan_not_collected', 'План реализации еще не собран.'), ENT_QUOTES, 'UTF-8') ?>',
	      finalCardBody:'<?= htmlspecialchars($t('ideas.state_final_not_generated', 'Итоговая рекомендация еще не сформирована.'), ENT_QUOTES, 'UTF-8') ?>',
	      tasksCardBody:'<?= htmlspecialchars($t('ideas.state_tasks_not_generated', 'Предлагаемые задачи еще не сформированы.'), ENT_QUOTES, 'UTF-8') ?>'
	    };
	    Object.keys(textBlocks).forEach(function(id){var el=document.getElementById(id);if(el)el.innerHTML='<p class="text-muted mb-0">'+textBlocks[id]+'</p>';});
	    ['cardUpdated','refinedUpdated','potentialUpdated','riskUpdated','pitfallsUpdated','planUpdated','finalUpdated','tasksUpdated','clarificationsStatus','gapStatus','debugRefreshed'].forEach(function(id){var el=document.getElementById(id);if(el)el.textContent='';});
	    ['interviewCard','clarificationsCard','understandingCard','gapQuestionsCard','refinedCard','potentialCard','riskCard','pitfallsCard','planCard','finalCard','tasksCard','debugCard'].forEach(hideBlock);
	    showBlock('aiPipelineCard');
	    setStartButtonIdle();
	  }
	  document.getElementById('resetPipelineBtn').addEventListener('click',async function(){
	    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_reset_pipeline', 'Сбросить прогресс AI-анализа и удалить все уже сгенерированные AI-блоки по этой идее?'), ENT_QUOTES, 'UTF-8') ?>'))return;
	    var btn=this;var start=document.getElementById('startPipelineBtn');
	    try{
	      running=false;pipelineRunToken++;
	      btn.disabled=true;if(start)start.disabled=true;
	      var status=document.getElementById('pipelineStatus');if(status)status.textContent='<?= htmlspecialchars($t('ideas.state_resetting', 'Сбрасываю AI-анализ...'), ENT_QUOTES, 'UTF-8') ?>';
	      if(window.CRM&&window.CRM.api){
	        await window.CRM.api.request('api/v1/ideas/<?=e($publicId)?>/reset-analysis',{method:'POST',timeoutMs:30000});
	      }
	      resetPipelineUi();
	    }catch(err){
	      var status=document.getElementById('pipelineStatus');if(status)status.textContent='<?= htmlspecialchars($t('ideas.state_reset_error', 'Ошибка сброса'), ENT_QUOTES, 'UTF-8') ?>';
	      alert('<?= htmlspecialchars($t('ideas.alert_reset_error', 'Не удалось сбросить AI-анализ. Попробуйте еще раз.'), ENT_QUOTES, 'UTF-8') ?>');
	    }finally{
	      btn.disabled=false;if(start)start.disabled=false;setStartButtonIdle();
	    }
	  });

  loadState();renderSteps();
})();
try{(function(){var pid='<?=e($publicId)?>';var idea=null;var currentUserId=null;
function deferHiddenAnalysisLoad(loader){
  if(typeof loader!=='function')return;
  window.setTimeout(function(){
    var run=function(){try{loader();}catch(e){}};
    if(window.requestIdleCallback){
      window.requestIdleCallback(run,{timeout:5000});
    }else{
      run();
    }
  },1800);
}
function normalizeIdeaDescription(value){return String(value||'').replace(/<br\s*\/?>/gi,'\n');}
function renderIdeaDescription(value){var text=String(value||'').trim();if(!text)return '';if(/<[a-z][\s\S]*>/i.test(text)&&window.CRM.VisualEditor&&typeof window.CRM.VisualEditor.sanitizeHtml==='function'){return window.CRM.VisualEditor.sanitizeHtml(text);}return window.CRM.text.escapeHtml(normalizeIdeaDescription(value)).replace(/\n/g,'<br>');}
function hydrateVisualEditorReadonly(root){if(window.CRM.VisualEditor&&typeof window.CRM.VisualEditor.renderReadonly==='function')window.CRM.VisualEditor.renderReadonly(root);}
function refreshVisualEditorScope(root){if(window.CRM.VisualEditor&&typeof window.CRM.VisualEditor.refreshEditors==='function')window.CRM.VisualEditor.refreshEditors(root||document,true);}
function getVisualEditorValue(textarea){var value=textarea?textarea.value:'';if(textarea&&window.CRM.VisualEditor&&typeof window.CRM.VisualEditor.getInstances==='function'){window.CRM.VisualEditor.getInstances().forEach(function(editor){if(editor&&editor._textarea===textarea&&typeof editor.getValue==='function')value=editor.getValue();});}return value;}
function load(){if(!window.CRM||!window.CRM.api){setTimeout(load,200);return;}
window.CRM.api.request('api/v1/ideas/'+pid,{method:'GET'}).then(function(env){idea=env.data.idea||env.data||{};
currentUserId=env.data.current_user_id||null;
document.getElementById('ideaTitle').textContent=idea.title||'<?= htmlspecialchars($t('ideas.state_untitled', 'Без названия'), ENT_QUOTES, 'UTF-8') ?>';
document.getElementById('ideaBreadcrumb').textContent=idea.title||'<?= htmlspecialchars($t('ideas.state_untitled', 'Без названия'), ENT_QUOTES, 'UTF-8') ?>';
document.getElementById('ideaDesc').innerHTML=renderIdeaDescription(idea.description||'');hydrateVisualEditorReadonly(document.getElementById('ideaDesc'));
document.getElementById('ideaAuthor').textContent=idea.author_name||idea.author_login||'—';
document.getElementById('ideaCategory').textContent=idea.category||'—';
document.getElementById('ideaRegion').textContent=idea.region||'—';
document.getElementById('ideaVisibility').innerHTML=(idea.visibility||'public')==='private'?'<span class="badge bg-warning text-dark"><?= htmlspecialchars($t('ideas.badge_private', 'Приватная'), ENT_QUOTES, 'UTF-8') ?></span>':'<span class="badge bg-info"><?= htmlspecialchars($t('ideas.badge_public', 'Публичная'), ENT_QUOTES, 'UTF-8') ?></span>';
document.getElementById('ideaDate').textContent=idea.created_at?new Date(idea.created_at).toLocaleDateString(window.CRM.ideaLocale()):'—';
document.getElementById('ideaTargetDate').textContent=idea.target_date?new Date(idea.target_date).toLocaleDateString(window.CRM.ideaLocale()):'—';
document.getElementById('ideaVotes').textContent=idea.vote_count||0;
var sc=idea.status==='approved'?'bg-success':idea.status==='rejected'?'bg-danger':idea.status==='in_progress'?'bg-info':'bg-secondary';
var statusLabels={new:'<?= htmlspecialchars($t('ideas.status_new', 'Новая'), ENT_QUOTES, 'UTF-8') ?>',draft:'<?= htmlspecialchars($t('ideas.status_draft', 'Черновик'), ENT_QUOTES, 'UTF-8') ?>',approved:'<?= htmlspecialchars($t('ideas.status_approved', 'Одобрена'), ENT_QUOTES, 'UTF-8') ?>',rejected:'<?= htmlspecialchars($t('ideas.status_rejected', 'Отклонена'), ENT_QUOTES, 'UTF-8') ?>',in_progress:'<?= htmlspecialchars($t('ideas.status_in_progress', 'В работе'), ENT_QUOTES, 'UTF-8') ?>'};
document.getElementById('ideaStatus').className='badge '+sc;document.getElementById('ideaStatus').textContent=statusLabels[idea.status]||idea.status||'<?= htmlspecialchars($t('ideas.status_draft', 'Черновик'), ENT_QUOTES, 'UTF-8') ?>';
// Show delete button only for author
if(currentUserId&&idea.author_user_id&&currentUserId==idea.author_user_id){
  document.getElementById('deleteIdeaBtn').style.display='';
}
loadComments();}).catch(function(){document.getElementById('ideaDesc').textContent='<?= htmlspecialchars($t('ideas.state_load_error', 'Ошибка загрузки'), ENT_QUOTES, 'UTF-8') ?>';});}
function loadComments(){window.CRM.api.request('api/v1/ideas/'+pid+'/comments',{method:'GET'}).then(function(env){var items=env.data.items||[];var html='';if(!items.length){html='<p class="text-muted p-3"><?= htmlspecialchars($t('ideas.state_no_comments', 'Нет комментариев'), ENT_QUOTES, 'UTF-8') ?></p>';}else{items.forEach(function(c){var dt=c.created_at?new Date(c.created_at).toLocaleString(window.CRM.ideaLocale()):'';html+='<div class="p-3 border-bottom"><div class="d-flex justify-content-between mb-1"><strong>'+window.CRM.text.escapeHtml(c.author_name||c.author_login||'')+'</strong><small class="text-muted">'+dt+'</small></div><div>'+renderIdeaDescription(c.body||c.text||'')+'</div></div>';});}document.getElementById('commentsSection').innerHTML=html;hydrateVisualEditorReadonly(document.getElementById('commentsSection'));}).catch(function(){document.getElementById('commentsSection').innerHTML='<p class="text-danger p-3"><?= htmlspecialchars($t('ideas.state_comments_load_error', 'Ошибка загрузки комментариев'), ENT_QUOTES, 'UTF-8') ?></p>';});}
document.getElementById('editIdeaBtn').addEventListener('click',function(){var f=document.getElementById('editIdeaForm');if(f.style.display==='none'){f.style.display='';document.getElementById('editIdeaTitle').value=idea.title||'';document.getElementById('editIdeaDesc').value=idea.description||'';document.getElementById('editIdeaVisibility').value=idea.visibility||'public';document.getElementById('editIdeaTargetDate').value=idea.target_date||'';window.setTimeout(function(){refreshVisualEditorScope(f);},0);window.setTimeout(function(){refreshVisualEditorScope(f);},150);}else{f.style.display='none';}});
document.getElementById('cancelEditBtn').addEventListener('click',function(){document.getElementById('editIdeaForm').style.display='none';});
document.getElementById('saveIdeaBtn').addEventListener('click',function(){var t=document.getElementById('editIdeaTitle').value.trim();var editDesc=document.getElementById('editIdeaDesc');var d=getVisualEditorValue(editDesc).trim();var v=document.getElementById('editIdeaVisibility').value;var td=document.getElementById('editIdeaTargetDate').value;if(!t)return;var b=this;b.disabled=true;window.CRM.api.request('api/v1/ideas/'+pid,{method:'PATCH',body:{title:t,description:d,visibility:v,target_date:td}}).then(function(){idea.title=t;idea.description=d;idea.visibility=v;idea.target_date=td;document.getElementById('ideaTitle').textContent=t;document.getElementById('ideaBreadcrumb').textContent=t;document.getElementById('ideaDesc').innerHTML=renderIdeaDescription(d);hydrateVisualEditorReadonly(document.getElementById('ideaDesc'));document.getElementById('ideaVisibility').innerHTML=v==='private'?'<span class="badge bg-warning text-dark"><?= htmlspecialchars($t('ideas.badge_private', 'Приватная'), ENT_QUOTES, 'UTF-8') ?></span>':'<span class="badge bg-info"><?= htmlspecialchars($t('ideas.badge_public', 'Публичная'), ENT_QUOTES, 'UTF-8') ?></span>';document.getElementById('ideaTargetDate').textContent=td?new Date(td).toLocaleDateString(window.CRM.ideaLocale()):'—';document.getElementById('editIdeaForm').style.display='none';b.disabled=false;}).catch(function(err){b.disabled=false;if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.error_save', 'Ошибка сохранения'), ENT_QUOTES, 'UTF-8') ?>');});});
document.getElementById('voteBtn').addEventListener('click',function(){var b=this;b.disabled=true;window.CRM.api.request('api/v1/ideas/'+pid+'/vote',{method:'POST'}).then(function(){load();}).catch(function(err){b.disabled=false;if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.error_vote', 'Ошибка:'), ENT_QUOTES, 'UTF-8') ?> '+(err.envelope&&err.envelope.message||''));});});
document.getElementById('addCommentBtn').addEventListener('click',function(){var input=document.getElementById('commentInput');var text=getVisualEditorValue(input).trim();if(!text)return;var b=this;b.disabled=true;window.CRM.api.request('api/v1/ideas/'+pid+'/comments',{method:'POST',body:{body:text}}).then(function(){input.value='';refreshVisualEditorScope(input.parentElement||document);b.disabled=false;loadComments();}).catch(function(){b.disabled=false;if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.error_send', 'Ошибка отправки'), ENT_QUOTES, 'UTF-8') ?>');});});
// Delete
document.getElementById('deleteIdeaBtn').addEventListener('click',function(){var m=new bootstrap.Modal(document.getElementById('deleteIdeaModal'));m.show();});
document.getElementById('confirmDeleteBtn').addEventListener('click',function(){var b=this;b.disabled=true;window.CRM.api.request('api/v1/ideas/'+pid,{method:'DELETE'}).then(function(){bootstrap.Modal.getInstance(document.getElementById('deleteIdeaModal')).hide();window.location.href='index.php?route=ideas';}).catch(function(err){b.disabled=false;if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.error_delete', 'Ошибка удаления'), ENT_QUOTES, 'UTF-8') ?>');});});

document.getElementById('clearInterviewBtn').addEventListener('click',function(){
  if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_interview', 'Удалить все вопросы и ответы для этой идеи?'), ENT_QUOTES, 'UTF-8') ?>'))return;
  var b=this;b.disabled=true;
  window.CRM.api.request('api/v1/ideas/'+pid+'/interview',{method:'DELETE',timeoutMs:10000}).then(function(){
    document.getElementById('interviewHistory').style.display='none';
    document.getElementById('interviewHistory').innerHTML='';
    document.getElementById('interviewQuestions').style.display='none';
    document.getElementById('interviewQuestions').innerHTML='';
    document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_interview_cleared', 'История очищена. Нажмите «Задать вопросы AI» для начала.'), ENT_QUOTES, 'UTF-8') ?>';
    b.disabled=false;
  }).catch(function(){b.disabled=false;if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.error_clear', 'Ошибка очистки'), ENT_QUOTES, 'UTF-8') ?>');});
});
// Interview: AI diagnostic questions
	function loadInterview(){if(!window.CRM||!window.CRM.api){setTimeout(loadInterview,200);return;}window.CRM.api.request('api/v1/ideas/'+pid+'/questions',{method:'GET'}).then(function(env){var qs=env.data.items||[];renderInterviewHistory(qs);renderInterviewQuestions(qs);var mainQs=qs.filter(function(q){return !q.is_clarification&&!q.is_gap;});var unanswered=mainQs.filter(function(q){return !q.last_answer;});var statusEl=document.getElementById('interviewStatus');if(unanswered.length>0&&document.getElementById('interviewQuestions').style.display!=='none'){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('interview',false);statusEl.textContent='<?= htmlspecialchars($t('ideas.state_questions_count', 'Вопросов:'), ENT_QUOTES, 'UTF-8') ?> '+mainQs.length+' <?= htmlspecialchars($t('ideas.state_out_of', 'из'), ENT_QUOTES, 'UTF-8') ?> 25. <?= htmlspecialchars($t('ideas.state_select_answers', 'Выберите ответы.'), ENT_QUOTES, 'UTF-8') ?>';statusEl.style.color='';}else if(mainQs.length>0&&!unanswered.length){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('interview',true);statusEl.textContent='<?= htmlspecialchars($t('ideas.state_all_answered', 'Все вопросы отвечены.'), ENT_QUOTES, 'UTF-8') ?>';statusEl.style.color='green';}}).catch(function(){});}
function normalizeAnswerOptions(options,allowCustom){
  var list=[];var seen={};var seenKeys={};var hasCustom=false;
  (options||[]).forEach(function(o){
    var key=String((o&&typeof o==='object'?(o.key||o.value||o.label):o)||'').trim();
    var label=String((o&&typeof o==='object'?(o.label||o.value||o.key):o)||'').trim();
    var fp=label.toLowerCase().replace(/\s+/g,' ').replace(/[^\p{L}\p{N}\s]+/gu,'').trim();
    var keyFp=key.toLowerCase();
    if((keyFp==='unknown'&&seenKeys['not_sure'])||(keyFp==='not_sure'&&seenKeys['unknown']))return;
    if(!key||!label||!fp||seen[fp]||seenKeys[keyFp])return;
    seen[fp]=true;seenKeys[keyFp]=true;if(keyFp==='custom')hasCustom=true;list.push({key:key,label:label});
  });
  if(allowCustom&&!hasCustom&&!seen['свой ответ']&&!seen['другое'])list.push({key:'custom',label:'<?= htmlspecialchars($t('ideas.opt_custom_answer', 'Свой ответ'), ENT_QUOTES, 'UTF-8') ?>'});
  return list;
}
function renderAnswerChoices(qid,options,allowCustom){
  var opts=normalizeAnswerOptions(options,allowCustom);
  var safeQid=window.CRM.text.escapeHtml(qid);
  var h='<div class="d-flex flex-wrap gap-2 mt-2">';
  opts.forEach(function(o){
    var key=window.CRM.text.escapeHtml(o.key);
    var label=window.CRM.text.escapeHtml(o.label);
    var isCustom=o.key==='custom';
    h+='<label class="form-check form-check-inline mb-1">';
    h+='<input type="checkbox" class="form-check-input iv-answer'+(isCustom?' iv-answer-custom':'')+'" data-qid="'+safeQid+'" value="'+key+'" data-label="'+label+'">';
    h+=' '+label+'</label>';
  });
  h+='</div>';
  h+='<div class="iv-custom-wrap crm-inline-answer mt-2" style="display:none">';
  h+='<input class="form-control iv-custom" data-qid="'+safeQid+'" placeholder="<?= htmlspecialchars($t('ideas.placeholder_custom_answer', 'Свой ответ...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="ideas.placeholder_custom_answer">';
  h+='<button type="button" class="btn crm-inline-answer-save iv-custom-save" title="<?= htmlspecialchars($t('ideas.btn_save_custom_answer', 'Сохранить свой ответ'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($t('ideas.btn_save_custom_answer', 'Сохранить свой ответ'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-check" aria-hidden="true"></i></button>';
  h+='<span class="crm-inline-answer-state iv-custom-state text-muted" style="display:none"></span>';
  h+='</div>';
  return h;
}
function buildAnswerFromDiv(div){
  var rawQid=div.getAttribute('data-qid')||'';
  if(!rawQid)return null;
  var selected=[];
  div.querySelectorAll('.iv-answer:checked').forEach(function(inp){
    selected.push({key:inp.value,label:inp.getAttribute('data-label')||inp.value});
  });
  var custom=div.querySelector('.iv-custom');
  var customText=custom&&custom.value.trim()?custom.value.trim():'';
  if(customText&&!selected.some(function(o){return o.key==='custom';}))selected.push({key:'custom',label:'<?= htmlspecialchars($t('ideas.opt_custom_answer', 'Свой ответ'), ENT_QUOTES, 'UTF-8') ?>'});
  if(!selected.length&&!customText)return null;
  return {
    question_public_id:rawQid,
    selected_option_key:selected[0]?selected[0].key:null,
    selected_option_label:selected.map(function(o){return o.label;}).join(', '),
    selected_options:selected,
    answer_text:customText||null,
    is_custom:customText?1:0,
    is_unknown:selected.some(function(o){return o.key==='unknown'||o.key==='not_sure';})?1:0
  };
}
function collectAnswersFromContainer(container){
  var answers=[];var seen={};
  var divs=container?container.querySelectorAll('div[data-qid]'):[];
  for(var i=0;i<divs.length;i++){
    var qid=divs[i].getAttribute('data-qid')||'';
    if(!qid||seen[qid])continue;
    seen[qid]=true;
    var answer=buildAnswerFromDiv(divs[i]);
    if(answer)answers.push(answer);
  }
  return answers;
}
function bindQuestionAnswerUi(root){
  if(!root)return;
  root.querySelectorAll('.iv-answer').forEach(function(inp){
    inp.addEventListener('change',function(){
      var div=inp.closest('div[data-qid]');
      if(!div)return;
      if((inp.value==='unknown'||inp.value==='not_sure')&&inp.checked){
        div.querySelectorAll('.iv-answer').forEach(function(other){if(other!==inp&&other.value!=='custom')other.checked=false;});
      }else if(inp.checked&&inp.value!=='custom'){
        div.querySelectorAll('.iv-answer').forEach(function(other){if(other.value==='unknown'||other.value==='not_sure')other.checked=false;});
      }
      var customWrap=div.querySelector('.iv-custom-wrap');
      var customChecked=!!div.querySelector('.iv-answer-custom:checked');
      if(customWrap)customWrap.style.display=customChecked?'flex':'none';
    });
  });
  root.querySelectorAll('.iv-custom-save').forEach(function(btn){
    btn.addEventListener('click',function(e){
      e.preventDefault();e.stopPropagation();
      var div=btn.closest('div[data-qid]');
      var state=div?div.querySelector('.iv-custom-state'):null;
      var answer=div?buildAnswerFromDiv(div):null;
      if(!answer||!answer.answer_text){if(state){state.style.display='';state.textContent='<?= htmlspecialchars($t('ideas.state_enter_text', 'Введите текст'), ENT_QUOTES, 'UTF-8') ?>';}return;}
      btn.disabled=true;if(state){state.style.display='';state.textContent='<?= htmlspecialchars($t('ideas.state_saving', 'Сохраняю...'), ENT_QUOTES, 'UTF-8') ?>';}
      window.CRM.api.request('api/v1/ideas/'+pid+'/interview-answers',{method:'POST',body:{answers:[answer]}}).then(function(){
        if(state)state.textContent='<?= htmlspecialchars($t('ideas.state_saved', 'Сохранено'), ENT_QUOTES, 'UTF-8') ?>';
        btn.disabled=false;
        if(root.id==='clarificationsBody'){
          window.CRM.api.request('api/v1/ideas/'+pid+'/additional-questions',{method:'GET'}).then(function(env){window._renderClarifications(env.data||{});}).catch(function(){});
        }else if(root.id==='gapBody'){
          window.CRM.api.request('api/v1/ideas/'+pid+'/gap-questions',{method:'GET'}).then(function(env){window._renderGaps(env.data||{});}).catch(function(){});
        }else{
          loadInterview();
        }
      }).catch(function(){if(state)state.textContent='<?= htmlspecialchars($t('ideas.state_error', 'Ошибка'), ENT_QUOTES, 'UTF-8') ?>';btn.disabled=false;});
    });
  });
}
function renderInterviewHistory(questions){
  if(!questions.length){document.getElementById('interviewHistory').style.display='none';return;}
  var h='<strong data-i18n="ideas.history_label"><?= htmlspecialchars($t('ideas.history_label', 'История:'), ENT_QUOTES, 'UTF-8') ?></strong><br>';var seen={};
  questions.forEach(function(q){
    var key=q.question_text||q.id;if(seen[key])return;seen[key]=true;
    var la=q.last_answer;var ans='<span class="text-warning">—</span>';
    if(la){
      var selected=[];try{selected=typeof la.selected_options_json==='string'?JSON.parse(la.selected_options_json||'[]'):(la.selected_options_json||[]);}catch(e){selected=[];}
      var labels=(Array.isArray(selected)?selected:[]).map(function(o){return o.label||o.key;}).filter(Boolean);
      if(la.is_unknown&&!labels.length)ans='<span class="text-muted"><?= htmlspecialchars($t('ideas.state_unknown', 'не знаю'), ENT_QUOTES, 'UTF-8') ?></span>';
      else if(labels.length)ans='<strong>'+window.CRM.text.escapeHtml(labels.join(', '))+'</strong>';
      else if(la.selected_option_label)ans='<strong>'+window.CRM.text.escapeHtml(la.selected_option_label)+'</strong>';
      else if(la.selected_option_key)ans='<strong>'+window.CRM.text.escapeHtml(la.selected_option_key)+'</strong>';
      if(la.answer_text)ans+=(labels.length||la.selected_option_label||la.selected_option_key?' · ':'')+'<i>'+window.CRM.text.escapeHtml(la.answer_text)+'</i>';
    }
    h+=window.CRM.text.escapeHtml(q.question_text||'')+' → '+ans+'<br>';
  });
  document.getElementById('interviewHistory').innerHTML=h;document.getElementById('interviewHistory').style.display='';
}document.getElementById('interviewBtn').addEventListener('click',function(){
  var b=this;
  var existingQs=document.getElementById('interviewQuestions').querySelectorAll('div[data-qid]');
  if(existingQs.length>0&&document.getElementById('interviewQuestions').style.display!=='none'){
  } else if(existingQs.length>0){
    document.getElementById('interviewQuestions').style.display='';
    document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_questions_count', 'Вопросов: '), ENT_QUOTES, 'UTF-8') ?>'+existingQs.length+' <?= htmlspecialchars($t('ideas.state_out_of', 'из 25. Выберите ответы.'), ENT_QUOTES, 'UTF-8') ?>';
    return;
  }
  if(window.CRM&&window.CRM.api){
    window.CRM.api.request('api/v1/ideas/'+pid+'/questions',{method:'GET'}).then(function(env){
      var qs=env.data.items||[];
      var unanswered=qs.filter(function(q){return !q.last_answer&&!q.is_clarification&&!q.is_gap;});
      if(unanswered.length>0){
        renderInterviewQuestions(unanswered);
        var mainCount=qs.filter(function(q){return !q.is_clarification&&!q.is_gap;}).length;
        document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_questions_count', 'Вопросов: '), ENT_QUOTES, 'UTF-8') ?>'+mainCount+' <?= htmlspecialchars($t('ideas.state_out_of', 'из 25. Выберите ответы.'), ENT_QUOTES, 'UTF-8') ?>';
        b.innerHTML='<i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_more', 'Задать ещё вопросы AI'), ENT_QUOTES, 'UTF-8') ?>';
        renderInterviewHistory(qs);
        return;
      }
      b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_ai_thinking', 'AI думает...'), ENT_QUOTES, 'UTF-8') ?>';
      document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_generating_questions', 'Генерирую вопросы...'), ENT_QUOTES, 'UTF-8') ?>';
      window.CRM.api.request('api/v1/ideas/'+pid+'/interview',{method:'POST',timeoutMs:300000}).then(function(env2){
        var data=env2.data||{};
        if(data.complete){document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_limit_reached', 'Достигнут лимит вопросов (25).'), ENT_QUOTES, 'UTF-8') ?>';b.disabled=false;b.innerHTML='<i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_ai', 'Задать вопросы AI'), ENT_QUOTES, 'UTF-8') ?>';loadInterview();return;}
        var generatedQuestions=data.questions||[];
        renderInterviewQuestions(generatedQuestions);
        if(generatedQuestions.length){
          document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_questions_count', 'Вопросов: '), ENT_QUOTES, 'UTF-8') ?>'+(data.total||0)+' <?= htmlspecialchars($t('ideas.state_out_of', 'из 25. Выберите ответы.'), ENT_QUOTES, 'UTF-8') ?>';
        }else{
          document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_all_answered', 'Все вопросы отвечены.'), ENT_QUOTES, 'UTF-8') ?>';
          document.getElementById('interviewStatus').style.color='green';
        }
        b.disabled=false;b.innerHTML='<i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_more', 'Задать ещё вопросы AI'), ENT_QUOTES, 'UTF-8') ?>';loadInterview();
      }).catch(function(err){
        b.disabled=false;b.innerHTML='<i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_ai', 'Задать вопросы AI'), ENT_QUOTES, 'UTF-8') ?>';
        window.CRM.api.request('api/v1/ideas/'+pid+'/questions',{method:'GET'}).then(function(env3){
          var qs3=env3.data.items||[];
          var unans3=qs3.filter(function(q){return !q.last_answer&&!q.is_clarification&&!q.is_gap;});
          if(unans3.length>0){
            renderInterviewQuestions(unans3);
            document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_questions_count', 'Вопросов: '), ENT_QUOTES, 'UTF-8') ?>'+qs3.filter(function(q){return !q.is_clarification&&!q.is_gap;}).length+' <?= htmlspecialchars($t('ideas.state_out_of', 'из 25. Выберите ответы.'), ENT_QUOTES, 'UTF-8') ?>';
            renderInterviewHistory(qs3);
          }else{
            document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_try_later', 'Ошибка: попробуйте позже'), ENT_QUOTES, 'UTF-8') ?>';
            document.getElementById('interviewStatus').style.color='red';
          }
        }).catch(function(){
          document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_try_later', 'Ошибка: попробуйте позже'), ENT_QUOTES, 'UTF-8') ?>';
          document.getElementById('interviewStatus').style.color='red';
        });
      });
    }).catch(function(){
      b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_ai_thinking', 'AI думает...'), ENT_QUOTES, 'UTF-8') ?>';
      document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_generating_questions', 'Генерирую вопросы...'), ENT_QUOTES, 'UTF-8') ?>';
      window.CRM.api.request('api/v1/ideas/'+pid+'/interview',{method:'POST',timeoutMs:300000}).then(function(env2){
        var data=env2.data||{};
        if(data.complete){document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_limit_reached', 'Достигнут лимит вопросов (25).'), ENT_QUOTES, 'UTF-8') ?>';b.disabled=false;b.innerHTML='<i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_ai', 'Задать вопросы AI'), ENT_QUOTES, 'UTF-8') ?>';loadInterview();return;}
        var generatedQuestions=data.questions||[];
        renderInterviewQuestions(generatedQuestions);
        if(generatedQuestions.length){
          document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_questions_count', 'Вопросов: '), ENT_QUOTES, 'UTF-8') ?>'+(data.total||0)+' <?= htmlspecialchars($t('ideas.state_out_of', 'из 25. Выберите ответы.'), ENT_QUOTES, 'UTF-8') ?>';
        }else{
          document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_all_answered', 'Все вопросы отвечены.'), ENT_QUOTES, 'UTF-8') ?>';
          document.getElementById('interviewStatus').style.color='green';
        }
        b.disabled=false;b.innerHTML='<i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_more', 'Задать ещё вопросы AI'), ENT_QUOTES, 'UTF-8') ?>';loadInterview();
      }).catch(function(err){
        b.disabled=false;b.innerHTML='<i class="fa-solid fa-comments me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_ask_ai', 'Задать вопросы AI'), ENT_QUOTES, 'UTF-8') ?>';
        window.CRM.api.request('api/v1/ideas/'+pid+'/questions',{method:'GET'}).then(function(env3){
          var qs3=env3.data.items||[];
          var unans3=qs3.filter(function(q){return !q.last_answer&&!q.is_clarification&&!q.is_gap;});
          if(unans3.length>0){
            renderInterviewQuestions(unans3);
            document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_questions_count', 'Вопросов: '), ENT_QUOTES, 'UTF-8') ?>'+qs3.filter(function(q){return !q.is_clarification&&!q.is_gap;}).length+' <?= htmlspecialchars($t('ideas.state_out_of', 'из 25. Выберите ответы.'), ENT_QUOTES, 'UTF-8') ?>';
            renderInterviewHistory(qs3);
          }else{
            document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_try_later', 'Ошибка: попробуйте позже'), ENT_QUOTES, 'UTF-8') ?>';
            document.getElementById('interviewStatus').style.color='red';
          }
        }).catch(function(){
          document.getElementById('interviewStatus').textContent='<?= htmlspecialchars($t('ideas.state_try_later', 'Ошибка: попробуйте позже'), ENT_QUOTES, 'UTF-8') ?>';
          document.getElementById('interviewStatus').style.color='red';
        });
      });
    });
  } else {
    setTimeout(function(){document.getElementById('interviewBtn').click();},200);
  }
});
function renderInterviewQuestions(questions){
  if(!questions||!questions.length){document.getElementById('interviewQuestions').innerHTML='';document.getElementById('interviewQuestions').style.display='none';var emptyCard=document.getElementById('interviewCard');if(emptyCard)emptyCard.classList.remove('pipeline-visible');return;}
  var unanswered=questions.filter(function(q){return !q.last_answer&&!q.is_clarification&&!q.is_gap;});
  if(!unanswered.length){document.getElementById('interviewQuestions').style.display='none';var statusEl=document.getElementById('interviewStatus');var hasMain=questions.some(function(q){return !q.is_clarification&&!q.is_gap;});if(statusEl&&hasMain){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('interview',true);statusEl.textContent='<?= htmlspecialchars($t('ideas.state_all_answered', 'Все вопросы отвечены.'), ENT_QUOTES, 'UTF-8') ?>';statusEl.style.color='green';}var card=document.getElementById('interviewCard');if(card)card.classList.remove('pipeline-visible');return;}
  var card=document.getElementById('interviewCard');if(card)card.classList.add('pipeline-visible');
  var h='';unanswered.forEach(function(q,i){
    var qid=q.public_id||q.id||('q'+i);
    var opts=q.options||(typeof q.options_json==='string'?JSON.parse(q.options_json):(q.options_json||[]));
    h+='<div class="mb-2 p-2 border rounded" data-qid="'+qid+'"><strong>'+(i+1)+'. '+window.CRM.text.escapeHtml(q.question_text||'')+'</strong>';
    if(q.reason)h+='<div class="text-muted small">'+window.CRM.text.escapeHtml(q.reason)+'</div>';
    h+=renderAnswerChoices(qid,opts,q.allow_custom||q.allow_custom_answer);
    h+='</div>';
  });
  h+='<div id="saveBtnPlaceholder"></div>';
  document.getElementById('interviewQuestions').innerHTML=h;document.getElementById('interviewQuestions').style.display='';
  var ph=document.getElementById('saveBtnPlaceholder');
  if(ph){
    var b=document.createElement('button');
    b.className='btn btn-sm crm-btn-primary mt-2';
    b.id='saveIvAnswersBtn';
    var icon=document.createElement('i');
    icon.className='fa-solid fa-paper-plane me-1';
    b.appendChild(icon);
    b.appendChild(document.createTextNode(' <?= htmlspecialchars($t('ideas.btn_save_answers', 'Сохранить ответы'), ENT_QUOTES, 'UTF-8') ?>'));
    b.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();window._saveIvAnswers();});
    ph.parentNode.replaceChild(b,ph);
  }
  bindQuestionAnswerUi(document.getElementById('interviewQuestions'));
}
// Debug polling
window._saveClarifications=function(){
    try{
      var st=document.getElementById('clarificationsStatus');if(st)st.style.color='blue';
      var now=new Date().toLocaleTimeString('ru-RU');
      var body=document.getElementById('clarificationsBody');
      var answers=collectAnswersFromContainer(body);
      if(!answers.length){if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_select_answer', 'Выберите хотя бы один ответ'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';return;}
      if(!window.CRM||!window.CRM.api){if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_api_not_loaded', 'API не загружен'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';return;}
      if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_saving_answers', 'Сохраняю '), ENT_QUOTES, 'UTF-8') ?>'+answers.length+'<?= htmlspecialchars($t('ideas.state_answers_suffix', ' ответов...'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='orange';
      var btn=document.getElementById('saveClarificationsBtn');if(btn)btn.disabled=true;
      window.CRM.api.request('api/v1/ideas/'+pid+'/interview-answers',{method:'POST',body:{answers:answers}}).then(function(){
        if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_clarifications_saved', 'Уточнения сохранены'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='green';
        loadInterview();
        if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.resumeAfterQuestions('clarifications');
        if(!window.CRM||!window.CRM.api)return;
        window.CRM.api.request('api/v1/ideas/'+pid+'/additional-questions',{method:'GET'}).then(function(env){
          window._renderClarifications(env.data||{});
        }).catch(function(){});
      }).catch(function(err){
        if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_save_error', 'Ошибка сохранения'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';
        if(btn)btn.disabled=false;
      });
    }catch(e2){var st2=document.getElementById('clarificationsStatus');if(st2){st2.textContent='<?= htmlspecialchars($t('ideas.state_error_prefix', 'Ошибка: '), ENT_QUOTES, 'UTF-8') ?>'+String(e2.message||'<?= htmlspecialchars($t('ideas.state_unknown', 'неизвестная'), ENT_QUOTES, 'UTF-8') ?>');st2.style.color='red';}}
  };
window._saveIvAnswers=function(e){
    try{
      var st=document.getElementById('interviewStatus');
      var dl=document.getElementById('debugLog');
      var now=new Date().toLocaleTimeString('ru-RU');
      if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_collecting_answers', 'Собираю ответы...'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='';
      if(dl){var old=dl.textContent||'';dl.textContent='['+now+'] <?= htmlspecialchars($t('ideas.debug_saving_answers', 'Сохранение ответов'), ENT_QUOTES, 'UTF-8') ?>'+"\n"+old;}
      var questionsEl=document.getElementById('interviewQuestions');
      var answers=collectAnswersFromContainer(questionsEl);
      if(!answers.length){if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_select_answer', 'Выберите хотя бы один ответ'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';return;}
      if(!window.CRM||!window.CRM.api){if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_api_not_loaded', 'API не загружен'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';return;}
      if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_sending', 'Отправляю '), ENT_QUOTES, 'UTF-8') ?>'+answers.length+'<?= htmlspecialchars($t('ideas.state_answers_suffix', ' ответов...'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='';
      var btn=document.getElementById('saveIvAnswersBtn');
      if(btn)btn.disabled=true;
      window.CRM.api.request('api/v1/ideas/'+pid+'/interview-answers',{method:'POST',body:{answers:answers}}).then(function(){
        if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_answers_saved', 'Ответы сохранены'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='green';if(questionsEl)questionsEl.style.display='none';loadInterview();
        if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.resumeAfterQuestions('interview');
      }).catch(function(err){if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_save_error', 'Ошибка сохранения'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';if(btn)btn.disabled=false;});
    }catch(e2){var st2=document.getElementById('interviewStatus');if(st2){st2.textContent='<?= htmlspecialchars($t('ideas.state_error_prefix', 'Ошибка: '), ENT_QUOTES, 'UTF-8') ?>'+String(e2.message||'<?= htmlspecialchars($t('ideas.state_unknown', 'неизвестная'), ENT_QUOTES, 'UTF-8') ?>');st2.style.color='red';}}
  };
  var _dt=null;
function _refreshDebug(){
  if(!window.CRM||!window.CRM.api){setTimeout(_refreshDebug,300);return;}
  window.CRM.api.request('api/v1/ideas/'+pid+'/debug-log',{method:'GET',timeoutMs:5000}).then(function(env){
    var d=env.data||{};
    var h='<?= htmlspecialchars($t('ideas.debug_idea_label', 'Идея: '), ENT_QUOTES, 'UTF-8') ?>'+((d.idea||{}).title||'—')+' ['+((d.idea||{}).status||'—')+']\n';
    h+='<?= htmlspecialchars($t('ideas.debug_provider', 'Провайдер: '), ENT_QUOTES, 'UTF-8') ?>'+(d.provider||'none')+' | <?= htmlspecialchars($t('ideas.debug_safe_mode', 'Safe mode: '), ENT_QUOTES, 'UTF-8') ?>'+(d.safe_mode?'ON':'OFF')+'\n';
    h+='<?= htmlspecialchars($t('ideas.debug_questions_count', 'Вопросов: '), ENT_QUOTES, 'UTF-8') ?>'+(d.questions_count||0)+' | <?= htmlspecialchars($t('ideas.debug_analyses_count', 'Анализов: '), ENT_QUOTES, 'UTF-8') ?>'+(d.analyses_count||0)+' | <?= htmlspecialchars($t('ideas.debug_iterations_count', 'Итераций: '), ENT_QUOTES, 'UTF-8') ?>'+(d.iterations_count||0)+'\n';
    h+='<?= htmlspecialchars($t('ideas.debug_snapshot', 'Снимок: '), ENT_QUOTES, 'UTF-8') ?>'+(d.snapshot_at||'')+'\n\n';
    h+='<?= htmlspecialchars($t('ideas.debug_heading_questions', '=== ВОПРОСЫ ==='), ENT_QUOTES, 'UTF-8') ?>'+"\n";
    (d.questions||[]).forEach(function(q,i){h+=(i+1)+'. ['+(q.has_answer?'[answered]':'[unanswered]')+'] '+q.text+(q.answer?' → '+q.answer:'')+'\n';});
    h+='\n<?= htmlspecialchars($t('ideas.debug_heading_iterations', '=== AI-ИТЕРАЦИИ ==='), ENT_QUOTES, 'UTF-8') ?>'+"\n";
    (d.iterations||[]).forEach(function(it,i){
      h+='--- #'+it.iteration+' '+it.type+' '+it.created+' ---\n';
      h+='REQ ('+it.req_size+'B): '+(it.req_preview||'—')+'\n';
      h+='RES ('+it.res_size+'B): '+(it.res_preview||'—')+'\n';
    });
    h+='\n<?= htmlspecialchars($t('ideas.debug_heading_analyses', '=== АНАЛИЗЫ ==='), ENT_QUOTES, 'UTF-8') ?>'+"\n";
    (d.analyses||[]).forEach(function(a,i){h+=(i+1)+'. '+a.type+' ['+a.status+'] '+(a.has_result?'[result]':'[no result]')+' '+(a.created||'')+'\n';});
    document.getElementById('debugLog').textContent=h;
    document.getElementById('debugRefreshed').textContent=new Date().toLocaleTimeString('ru-RU');
  }).catch(function(){document.getElementById('debugLog').textContent='<?= htmlspecialchars($t('ideas.state_error_loading_logs', 'Ошибка загрузки логов'), ENT_QUOTES, 'UTF-8') ?>';});
}
document.getElementById('refreshDebugBtn').addEventListener('click',function(){if(!_dt)_startPoll();else _refreshDebug();});
document.getElementById('clearDebugBtn').addEventListener('click',function(){
  if(!window.CRM||!window.CRM.api)return;
  var b=this;b.disabled=true;
  window.CRM.api.request('api/v1/ideas/'+pid+'/debug-log',{method:'DELETE',timeoutMs:5000}).then(function(){
    if(_dt){clearInterval(_dt);_dt=null;}
    var dl=document.getElementById('debugLog');if(dl)dl.textContent='<?= htmlspecialchars($t('ideas.debug_logs_cleared', 'Логи очищены («Обновить» чтобы загрузить заново)'), ENT_QUOTES, 'UTF-8') ?>';
    var dr=document.getElementById('debugRefreshed');if(dr)dr.textContent='';
    b.disabled=false;
  }).catch(function(err){
    var dl=document.getElementById('debugLog');if(dl)dl.textContent='<?= htmlspecialchars($t('ideas.state_error_clearing_logs', 'Ошибка очистки логов: '), ENT_QUOTES, 'UTF-8') ?>'+(err&&err.envelope&&err.envelope.message?err.envelope.message:'');
    b.disabled=false;
  });
});
function _startPoll(){if(_dt)clearInterval(_dt);_refreshDebug();_dt=setInterval(_refreshDebug,10000);}
setTimeout(_startPoll,500);
loadInterview();
// Additional clarifications: load existing or show empty
(function(){
  var card=document.getElementById('clarificationsCard');
  if(!card)return;
  card.setAttribute('data-loaded','1');
  function loadClarifications(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadClarifications,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/additional-questions',{method:'GET'}).then(function(env){
      window._renderClarifications(env.data||{});
    }).catch(function(){});
  }
  document.getElementById('clarifyBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_ai_analyzing', 'AI анализирует...'), ENT_QUOTES, 'UTF-8') ?>';
    document.getElementById('clarificationsStatus').textContent='<?= htmlspecialchars($t('ideas.state_analyzing_idea', 'Анализирую идею...'), ENT_QUOTES, 'UTF-8') ?>';
      window.CRM.api.request('api/v1/ideas/'+pid+'/additional-questions',{method:'POST',timeoutMs:300000}).then(function(env){
      window._renderClarifications(env.data||{});
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-brain me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_clarify', 'Уточнить'), ENT_QUOTES, 'UTF-8') ?>';
    }).catch(function(err){
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-brain me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_clarify', 'Уточнить'), ENT_QUOTES, 'UTF-8') ?>';
      document.getElementById('clarificationsStatus').textContent='<?= htmlspecialchars($t('ideas.state_try_later', 'Ошибка: попробуйте позже'), ENT_QUOTES, 'UTF-8') ?>';
      document.getElementById('clarificationsStatus').style.color='red';
    });
  });
  document.getElementById('clearClarificationsBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_clarifications', 'Удалить все уточнения?'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/additional-questions',{method:'DELETE',timeoutMs:10000}).then(function(){
      document.getElementById('clarificationsBody').style.display='none';
      document.getElementById('clarificationsBody').innerHTML='';
      document.getElementById('clarificationsStatus').textContent='<?= htmlspecialchars($t('ideas.state_no_clarifications', '— нет уточнений'), ENT_QUOTES, 'UTF-8') ?>';
      document.getElementById('clarificationsStatus').style.color='';
      if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('clarifications',true);
      b.disabled=false;
    }).catch(function(){
      b.disabled=false;
      if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.state_delete_error', 'Ошибка удаления'), ENT_QUOTES, 'UTF-8') ?>');
    });
  });
  loadClarifications();
})();
window._renderClarifications=function(data){
  var body=document.getElementById('clarificationsBody');
  var status=document.getElementById('clarificationsStatus');
  var questions=(data.questions||[]).filter(function(q){return !!q.public_id;});
  if(!questions.length){
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('clarifications',true);
    body.style.display='none';
    var card=document.getElementById('clarificationsCard');if(card)card.classList.remove('pipeline-visible');
    if(status)status.textContent='<?= htmlspecialchars($t('ideas.state_no_clarifications', '— нет уточнений'), ENT_QUOTES, 'UTF-8') ?>';
    return;
  }
  if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('clarifications',false);
  var card=document.getElementById('clarificationsCard');if(card)card.classList.add('pipeline-visible');
  if(status)status.textContent=questions.length+' <?= htmlspecialchars($t('ideas.state_questions', 'вопросов'), ENT_QUOTES, 'UTF-8') ?>';
  var h='';
  questions.forEach(function(q,i){
    var qid=q.public_id;
    h+='<div class="mb-3 p-2 border rounded" style="border-left:3px solid var(--crm-idea-clarify) !important" data-qid="'+qid+'">';
    h+='<strong>'+(i+1)+'. '+window.CRM.text.escapeHtml(q.question||'')+'</strong>';
    if(q.why)h+='<div class="text-muted small mb-2">'+window.CRM.text.escapeHtml(q.why)+'</div>';
    h+=renderAnswerChoices(qid,q.answers||[],true);
    h+='</div>';
  });
  h+='<button class="btn btn-sm crm-btn-primary mt-2" id="saveClarificationsBtn"><i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_save_clarifications', 'Сохранить уточнения'), ENT_QUOTES, 'UTF-8') ?></button>';
  body.innerHTML=h;
  body.style.display='';
  bindQuestionAnswerUi(body);
  document.getElementById('saveClarificationsBtn').addEventListener('click',function(e){e.preventDefault();e.stopPropagation();_saveClarifications();});
};
// Understanding card
(function(){
  var cardEl=document.getElementById('understandingCard');
  if(!cardEl)return;
  var body=document.getElementById('understandingCardBody');
  var updatedEl=document.getElementById('cardUpdated');

  function loadCard(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadCard,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/understanding-card',{method:'GET'}).then(function(env){
      renderCard(env.data||{});
    }).catch(function(){});
  }

  function renderCard(data){
    if(data.empty||!data.profile_json){
      if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('understanding',false);
      body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_card_not_collected', 'Карточка понимания идеи еще не собрана.'), ENT_QUOTES, 'UTF-8') ?></p>';
      if(updatedEl)updatedEl.textContent='';
      return;
    }
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('understanding',true);
    var profile=typeof data.profile_json==='string'?JSON.parse(data.profile_json):(data.profile_json||{});
    if(updatedEl&&data.updated_at)updatedEl.textContent='<?= htmlspecialchars($t('ideas.state_updated', 'обновлено '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var c=profile.completeness||{};
    var pct=function(v){return Math.round((+v||0)*100)+'%';};
    var h='';
    if(data.summary)h+='<div class="mb-2"><strong data-i18n="ideas.analysis_short_summary"><?= htmlspecialchars($t('ideas.analysis_short_summary', 'Краткое резюме:'), ENT_QUOTES, 'UTF-8') ?></strong><br>'+escapeHtml(data.summary)+'</div>';
    if(data.idea_type)h+='<div class="mb-1"><strong data-i18n="ideas.analysis_idea_type"><?= htmlspecialchars($t('ideas.analysis_idea_type', 'Тип идеи:'), ENT_QUOTES, 'UTF-8') ?></strong> '+escapeHtml(data.idea_type)+'</div>';
    if(data.specificity_level)h+='<div class="mb-1"><strong data-i18n="ideas.analysis_specificity"><?= htmlspecialchars($t('ideas.analysis_specificity', 'Уровень конкретики:'), ENT_QUOTES, 'UTF-8') ?></strong> '+escapeHtml(data.specificity_level)+'</div>';
    h+='<div class="mb-1"><strong data-i18n="ideas.analysis_completeness"><?= htmlspecialchars($t('ideas.analysis_completeness', 'Полнота данных:'), ENT_QUOTES, 'UTF-8') ?></strong> '+pct(data.completeness_score)+'</div>';
    h+='<div class="mb-1"><strong data-i18n="ideas.analysis_confidence"><?= htmlspecialchars($t('ideas.analysis_confidence', 'Уверенность:'), ENT_QUOTES, 'UTF-8') ?></strong> '+pct(data.confidence_score)+'</div>';
    if(data.next_action)h+='<div class="mb-1"><strong data-i18n="ideas.analysis_next_step"><?= htmlspecialchars($t('ideas.analysis_next_step', 'Следующий шаг:'), ENT_QUOTES, 'UTF-8') ?></strong> '+escapeHtml(data.next_action)+'</div>';
    var lists=[
      ['known_facts','<?= htmlspecialchars($t('ideas.list_known_facts', 'Известные факты'), ENT_QUOTES, 'UTF-8') ?>'],
      ['user_unknowns','<?= htmlspecialchars($t('ideas.list_user_unknowns', 'Что пользователь пока не знает'), ENT_QUOTES, 'UTF-8') ?>'],
      ['missing_facts','<?= htmlspecialchars($t('ideas.list_missing_facts', 'Каких данных не хватает'), ENT_QUOTES, 'UTF-8') ?>'],
      ['assumptions','<?= htmlspecialchars($t('ideas.list_assumptions', 'Предположения'), ENT_QUOTES, 'UTF-8') ?>'],
      ['constraints','<?= htmlspecialchars($t('ideas.list_constraints', 'Ограничения'), ENT_QUOTES, 'UTF-8') ?>'],
      ['early_risks','<?= htmlspecialchars($t('ideas.list_early_risks', 'Предварительные зоны риска'), ENT_QUOTES, 'UTF-8') ?>'],
      ['key_decision_factors','<?= htmlspecialchars($t('ideas.list_key_factors', 'Ключевые факторы для анализа'), ENT_QUOTES, 'UTF-8') ?>'],
    ];
    lists.forEach(function(item){
      var arr=profile[item[0]]||[];
      if(arr.length){
        h+='<div class="mt-2"><strong>'+item[1]+':</strong><ul class="mb-1 small">';
        arr.forEach(function(v){h+='<li>'+escapeHtml(typeof v==='string'?v:(v.label||v.text||JSON.stringify(v)))+'</li>';});
        h+='</ul></div>';
      }
    });
    body.innerHTML=h;
  }

  function escapeHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}

  document.getElementById('buildCardBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_building', 'Собираю...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_analyzing_card', 'AI анализирует идею...'), ENT_QUOTES, 'UTF-8') ?></p>';
      window.CRM.api.request('api/v1/ideas/'+pid+'/understanding-card',{method:'POST',timeoutMs:300000}).then(function(env){
      renderCard(env.data||{});
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Собрать заново'), ENT_QUOTES, 'UTF-8') ?>';
    }).catch(function(err){
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Собрать заново'), ENT_QUOTES, 'UTF-8') ?>';
      body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_card_error', 'Ошибка: AI не смог собрать карточку. Попробуйте позже.'), ENT_QUOTES, 'UTF-8') ?></p>';
    });
  });

  document.getElementById('clearCardBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_card', 'Удалить карточку понимания идеи? Вопросы и ответы останутся.'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/understanding-card',{method:'DELETE',timeoutMs:10000}).then(function(){
      body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_card_not_collected', 'Карточка понимания идеи еще не собрана.'), ENT_QUOTES, 'UTF-8') ?></p>';
      if(updatedEl)updatedEl.textContent='';
      b.disabled=false;
    }).catch(function(){b.disabled=false;});
  });

  deferHiddenAnalysisLoad(loadCard);
})();
// Gap questions (targeted at understanding card gaps)
(function(){
  var gapCard=document.getElementById('gapQuestionsCard');
  if(!gapCard)return;
  var body=document.getElementById('gapBody');
  var status=document.getElementById('gapStatus');

  function loadGaps(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadGaps,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/gap-questions',{method:'GET'}).then(function(env){
      window._renderGaps(env.data||{});
    }).catch(function(){});
  }

  document.getElementById('gapBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_ai_analyzing', 'AI анализирует...'), ENT_QUOTES, 'UTF-8') ?>';
    if(status)status.textContent='<?= htmlspecialchars($t('ideas.state_analyzing_gaps', 'Анализирую пробелы...'), ENT_QUOTES, 'UTF-8') ?>';
      window.CRM.api.request('api/v1/ideas/'+pid+'/gap-questions',{method:'POST',timeoutMs:300000}).then(function(env){
      window._renderGaps(env.data||{});
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-brain me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_clarify', 'Уточнить'), ENT_QUOTES, 'UTF-8') ?>';
    }).catch(function(err){
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-brain me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_clarify', 'Уточнить'), ENT_QUOTES, 'UTF-8') ?>';
      if(status){status.textContent='<?= htmlspecialchars($t('ideas.state_try_later', 'Ошибка: попробуйте позже'), ENT_QUOTES, 'UTF-8') ?>';status.style.color='red';}
    });
  });

  document.getElementById('clearGapBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_gaps', 'Удалить все вопросы по пробелам?'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/gap-questions',{method:'DELETE',timeoutMs:10000}).then(function(){
      body.style.display='none';
      body.innerHTML='';
      if(status)status.textContent='<?= htmlspecialchars($t('ideas.state_no_questions', '— нет вопросов'), ENT_QUOTES, 'UTF-8') ?>';
      if(status)status.style.color='';
      b.disabled=false;
    }).catch(function(){b.disabled=false;if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.state_delete_error', 'Ошибка удаления'), ENT_QUOTES, 'UTF-8') ?>');});
  });

  loadGaps();
})();
window._saveGaps=function(){
    try{
      var st=document.getElementById('gapStatus');if(st)st.style.color='blue';
      var body=document.getElementById('gapBody');
      var answers=collectAnswersFromContainer(body);
      if(!answers.length){if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_select_answer', 'Выберите хотя бы один ответ'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';return;}
      if(!window.CRM||!window.CRM.api){if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_api_not_loaded', 'API не загружен'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';return;}
      if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_saving_answers', 'Сохраняю '), ENT_QUOTES, 'UTF-8') ?>'+answers.length+'<?= htmlspecialchars($t('ideas.state_answers_suffix', ' ответов...'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='orange';
      var btn=document.getElementById('saveGapBtn');if(btn)btn.disabled=true;
      window.CRM.api.request('api/v1/ideas/'+pid+'/interview-answers',{method:'POST',body:{answers:answers}}).then(function(){
        if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_clarifications_saved', 'Уточнения сохранены'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='green';
        loadInterview();
        if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.resumeAfterQuestions('gapQuestions');
        if(!window.CRM||!window.CRM.api)return;
        window.CRM.api.request('api/v1/ideas/'+pid+'/gap-questions',{method:'GET'}).then(function(env){
          window._renderGaps(env.data||{});
        }).catch(function(){});
      }).catch(function(err){
        if(st)st.textContent='<?= htmlspecialchars($t('ideas.state_save_error', 'Ошибка сохранения'), ENT_QUOTES, 'UTF-8') ?>';if(st)st.style.color='red';
        if(btn)btn.disabled=false;
      });
    }catch(e2){var st2=document.getElementById('gapStatus');if(st2){st2.textContent='<?= htmlspecialchars($t('ideas.state_error_prefix', 'Ошибка: '), ENT_QUOTES, 'UTF-8') ?>'+String(e2.message||'<?= htmlspecialchars($t('ideas.state_unknown', 'неизвестная'), ENT_QUOTES, 'UTF-8') ?>');st2.style.color='red';}}
  };
window._renderGaps=function(data){
  var body=document.getElementById('gapBody');
  var status=document.getElementById('gapStatus');
  var questions=(data.questions||[]).filter(function(q){return !!q.public_id;});
  if(!questions.length){
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('gapQuestions',true);
    body.style.display='none';
    var card=document.getElementById('gapQuestionsCard');if(card)card.classList.remove('pipeline-visible');
    if(status)status.textContent='<?= htmlspecialchars($t('ideas.state_no_questions', '— нет вопросов'), ENT_QUOTES, 'UTF-8') ?>';
    return;
  }
  if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('gapQuestions',false);
  var card=document.getElementById('gapQuestionsCard');if(card)card.classList.add('pipeline-visible');
  if(status)status.textContent=questions.length+' <?= htmlspecialchars($t('ideas.state_questions', 'вопросов'), ENT_QUOTES, 'UTF-8') ?>';
  var h='';
  questions.forEach(function(q,i){
    var qid=q.public_id;
    h+='<div class="mb-3 p-2 border rounded" style="border-left:3px solid var(--crm-idea-gap) !important" data-qid="'+qid+'">';
    h+='<strong>'+(i+1)+'. '+window.CRM.text.escapeHtml(q.question||'')+'</strong>';
    if(q.why)h+='<div class="text-muted small mb-2">'+window.CRM.text.escapeHtml(q.why)+'</div>';
    h+=renderAnswerChoices(qid,q.answers||[],true);
    h+='</div>';
  });
  h+='<button class="btn btn-sm crm-btn-primary mt-2" id="saveGapBtn"><i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_save_clarifications', 'Сохранить уточнения'), ENT_QUOTES, 'UTF-8') ?></button>';
  body.innerHTML=h;
  body.style.display='';
  bindQuestionAnswerUi(body);
  document.getElementById('saveGapBtn').addEventListener('click',function(e){e.preventDefault();e.stopPropagation();_saveGaps();});
};
// Refined understanding card
(function(){
  var cardEl=document.getElementById('refinedCard');
  if(!cardEl)return;
  var body=document.getElementById('refinedCardBody');
  var updatedEl=document.getElementById('refinedUpdated');

  function loadRefined(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadRefined,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/refined-card',{method:'GET'}).then(function(env){
      renderRefined(env.data||{});
    }).catch(function(){});
  }

  function renderRefined(data){
    if(data.empty||!data.profile_json){
      if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('refined',false);
      body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_refined_not_collected', 'Уточненная карточка еще не собрана.'), ENT_QUOTES, 'UTF-8') ?></p>';
      if(updatedEl)updatedEl.textContent='';
      return;
    }
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('refined',true);
    var profile=typeof data.profile_json==='string'?JSON.parse(data.profile_json):(data.profile_json||{});
    if(updatedEl&&data.updated_at)updatedEl.textContent='<?= htmlspecialchars($t('ideas.state_updated', 'обновлено '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var c=profile.completeness||{};
    var pct=function(v){return Math.round((+v||0)*100)+'%';};
    var esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
    var h='';
    if(data.summary)h+='<div class="mb-2"><strong data-i18n="ideas.analysis_short_summary"><?= htmlspecialchars($t('ideas.analysis_short_summary', 'Краткое резюме:'), ENT_QUOTES, 'UTF-8') ?></strong><br>'+esc(data.summary)+'</div>';
    if(data.idea_type)h+='<div class="mb-1"><strong data-i18n="ideas.analysis_idea_type"><?= htmlspecialchars($t('ideas.analysis_idea_type', 'Тип идеи:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.idea_type)+'</div>';
    if(data.specificity_level)h+='<div class="mb-1"><strong data-i18n="ideas.analysis_specificity"><?= htmlspecialchars($t('ideas.analysis_specificity', 'Уровень конкретики:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.specificity_level)+'</div>';
    h+='<div class="mb-1"><strong data-i18n="ideas.analysis_completeness"><?= htmlspecialchars($t('ideas.analysis_completeness', 'Полнота данных:'), ENT_QUOTES, 'UTF-8') ?></strong> '+pct(data.completeness_score)+'</div>';
    h+='<div class="mb-1"><strong data-i18n="ideas.analysis_confidence"><?= htmlspecialchars($t('ideas.analysis_confidence', 'Уверенность:'), ENT_QUOTES, 'UTF-8') ?></strong> '+pct(data.confidence_score)+'</div>';
    if(data.next_action)h+='<div class="mb-1"><strong data-i18n="ideas.analysis_next_step"><?= htmlspecialchars($t('ideas.analysis_next_step', 'Следующий шаг:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.next_action)+'</div>';
    var lists=[['known_facts','<?= htmlspecialchars($t('ideas.list_known_facts', 'Известные факты'), ENT_QUOTES, 'UTF-8') ?>'],['user_unknowns','<?= htmlspecialchars($t('ideas.list_user_unknowns_refined', 'Неизвестно пользователю'), ENT_QUOTES, 'UTF-8') ?>'],['missing_facts','<?= htmlspecialchars($t('ideas.list_missing_data', 'Недостающие данные'), ENT_QUOTES, 'UTF-8') ?>'],['assumptions','<?= htmlspecialchars($t('ideas.list_assumptions', 'Предположения'), ENT_QUOTES, 'UTF-8') ?>'],['constraints','<?= htmlspecialchars($t('ideas.list_constraints', 'Ограничения'), ENT_QUOTES, 'UTF-8') ?>'],['early_risks','<?= htmlspecialchars($t('ideas.list_risk_zones', 'Зоны риска'), ENT_QUOTES, 'UTF-8') ?>'],['key_decision_factors','<?= htmlspecialchars($t('ideas.list_key_factors', 'Ключевые факторы'), ENT_QUOTES, 'UTF-8') ?>']];
    lists.forEach(function(item){
      var arr=profile[item[0]]||[];
      if(arr.length){h+='<div class="mt-2"><strong>'+item[1]+':</strong><ul class="mb-1 small">';arr.forEach(function(v){h+='<li>'+esc(typeof v==='string'?v:(v.label||v.text||JSON.stringify(v)))+'</li>';});h+='</ul></div>';}
    });
    body.innerHTML=h;
  }

  document.getElementById('buildRefinedBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_refining', 'Уточняю...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_refining', 'AI пересобирает карточку с учётом ответов...'), ENT_QUOTES, 'UTF-8') ?></p>';
    window.CRM.api.request('api/v1/ideas/'+pid+'/refined-card',{method:'POST',timeoutMs:300000}).then(function(env){
      renderRefined(env.data||{});
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Собрать заново'), ENT_QUOTES, 'UTF-8') ?>';
    }).catch(function(){
      b.disabled=false;b.innerHTML='<i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Собрать заново'), ENT_QUOTES, 'UTF-8') ?>';
      body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_refined_error', 'Ошибка: AI не смог уточнить карточку.'), ENT_QUOTES, 'UTF-8') ?></p>';
    });
  });

  document.getElementById('clearRefinedBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_refined', 'Удалить уточненную карточку?'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/refined-card',{method:'DELETE',timeoutMs:10000}).then(function(){
      body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_refined_not_collected', 'Уточненная карточка еще не собрана.'), ENT_QUOTES, 'UTF-8') ?></p>';
      if(updatedEl)updatedEl.textContent='';
      b.disabled=false;
    }).catch(function(){b.disabled=false;});
  });

  deferHiddenAnalysisLoad(loadRefined);
})();
// Potential score
(function(){
  var card=document.getElementById('potentialCard');
  if(!card)return;
  var body=document.getElementById('potentialCardBody');
  var upd=document.getElementById('potentialUpdated');
  var esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
  var pct=function(v){return Math.round((+v||0)*100)+'%';};
  var levelColor=function(l){return {very_low:'danger',low:'warning',medium:'info',high:'primary',very_high:'success'}[l]||'secondary';};

  function loadPotential(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadPotential,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/potential',{method:'GET'}).then(function(env){renderPotential(env.data||{});}).catch(function(){});
  }

  function renderPotential(data){
    if(data.empty||!data.potential_json){
      if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('potential',false);
      body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_potential_not_calculated', 'Потенциал идеи еще не рассчитан.'), ENT_QUOTES, 'UTF-8') ?></p>';
      if(upd)upd.textContent='';return;
    }
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('potential',true);
    var p=typeof data.potential_json==='string'?JSON.parse(data.potential_json):(data.potential_json||{});
    var pot=p.potential||{};
    var criteria=p.criteria||[];
    if(upd&&data.updated_at)upd.textContent='<?= htmlspecialchars($t('ideas.state_calculation_from', 'расчет от '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var h='';
    h+='<div class="row mb-3 text-center"><div class="col"><h2 class="mb-0 text-'+levelColor(data.potential_level)+'">'+data.potential_score+'</h2><small data-i18n="ideas.analysis_out_of"><?= htmlspecialchars($t('ideas.analysis_out_of', 'из 100'), ENT_QUOTES, 'UTF-8') ?></small></div><div class="col"><span class="badge bg-'+levelColor(data.potential_level)+' fs-6">'+esc(data.potential_level)+'</span></div><div class="col"><small data-i18n="ideas.analysis_completeness_short"><?= htmlspecialchars($t('ideas.analysis_completeness_short', 'Полнота:'), ENT_QUOTES, 'UTF-8') ?></small> '+pct(data.completeness_score)+'<br><small data-i18n="ideas.analysis_confidence_short"><?= htmlspecialchars($t('ideas.analysis_confidence_short', 'Уверенность:'), ENT_QUOTES, 'UTF-8') ?></small> '+pct(data.confidence_score)+'</div></div>';
    if(data.calculation_type)h+='<div class="mb-1"><strong data-i18n="ideas.analysis_calc_type"><?= htmlspecialchars($t('ideas.analysis_calc_type', 'Тип расчета:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.calculation_type)+'</div>';
    if(data.verdict)h+='<div class="mb-2"><strong data-i18n="ideas.analysis_verdict"><?= htmlspecialchars($t('ideas.analysis_verdict', 'Вывод:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.verdict)+'</div>';
    if(pot.summary)h+='<div class="mb-2 text-muted small">'+esc(pot.summary)+'</div>';
    if(data.confidence_score<0.5)h+='<div class="alert alert-warning py-1 small mb-2"><?= htmlspecialchars($t('ideas.alert_preliminary_estimate', 'Оценка предварительная: данных недостаточно.'), ENT_QUOTES, 'UTF-8') ?></div>';
    if(data.completeness_score<0.5)h+='<div class="alert alert-info py-1 small mb-2"><?= htmlspecialchars($t('ideas.alert_collect_more_data', 'Для точной оценки соберите больше данных.'), ENT_QUOTES, 'UTF-8') ?></div>';
    if(criteria.length){h+='<div class="mt-2"><strong data-i18n="ideas.analysis_criteria"><?= htmlspecialchars($t('ideas.analysis_criteria', 'Критерии оценки:'), ENT_QUOTES, 'UTF-8') ?></strong><table class="table table-sm small mb-2"><thead><tr><th data-i18n="ideas.th_criterion"><?= htmlspecialchars($t('ideas.th_criterion', 'Критерий'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_weight"><?= htmlspecialchars($t('ideas.th_weight', 'Вес'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_score"><?= htmlspecialchars($t('ideas.th_score', '0–10'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_contribution"><?= htmlspecialchars($t('ideas.th_contribution', 'Вклад'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody>';criteria.forEach(function(c){h+='<tr><td>'+esc(c.title||c.criterion_id)+'<br><small class="text-muted">'+esc(c.reason||'')+'</small></td><td>'+c.weight+'</td><td>'+c.score+'</td><td>'+Math.round(c.weighted_score||(c.weight*c.score/10))+'</td></tr>';});h+='</tbody></table></div>';}
    var lists=[['strengths','<?= htmlspecialchars($t('ideas.list_strengths', 'Сильные стороны'), ENT_QUOTES, 'UTF-8') ?>','success'],['weaknesses','<?= htmlspecialchars($t('ideas.list_weaknesses', 'Слабые стороны'), ENT_QUOTES, 'UTF-8') ?>','danger'],['growth_factors','<?= htmlspecialchars($t('ideas.list_growth_factors', 'Факторы роста'), ENT_QUOTES, 'UTF-8') ?>','info'],['risk_factors','<?= htmlspecialchars($t('ideas.list_risk_factors', 'Факторы риска'), ENT_QUOTES, 'UTF-8') ?>','warning'],['missing_data','<?= htmlspecialchars($t('ideas.list_missing_data', 'Недостающие данные'), ENT_QUOTES, 'UTF-8') ?>','secondary'],['assumptions','<?= htmlspecialchars($t('ideas.list_assumptions', 'Предположения'), ENT_QUOTES, 'UTF-8') ?>','secondary'],['what_can_improve_score','<?= htmlspecialchars($t('ideas.list_improve_score', 'Что повысит оценку'), ENT_QUOTES, 'UTF-8') ?>','success'],['what_can_reduce_score','<?= htmlspecialchars($t('ideas.list_reduce_score', 'Что снизит оценку'), ENT_QUOTES, 'UTF-8') ?>','danger']];
    lists.forEach(function(item){var arr=p[item[0]]||[];if(arr.length){h+='<div class="mt-1"><strong>'+item[1]+':</strong><ul class="mb-1 small">';arr.forEach(function(v){h+='<li>'+esc(typeof v==='string'?v:(v.label||v.text||JSON.stringify(v)))+'</li>';});h+='</ul></div>';}});
    if(p.recommended_next_step){h+='<div class="mt-2"><strong data-i18n="ideas.analysis_next_step"><?= htmlspecialchars($t('ideas.analysis_next_step', 'Следующий шаг:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(p.recommended_next_step.action)+' — '+esc(p.recommended_next_step.reason||'')+'</div>';}
    body.innerHTML=h;
  }

  document.getElementById('calcPotentialBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_calculating', 'Считаю...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_calculating_potential', 'AI рассчитывает потенциал...'), ENT_QUOTES, 'UTF-8') ?></p>';
    window.CRM.api.request('api/v1/ideas/'+pid+'/potential',{method:'POST',timeoutMs:300000}).then(function(env){renderPotential(env.data||{});b.disabled=false;b.innerHTML='<i class="fa-solid fa-calculator me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_recalculate', 'Пересчитать'), ENT_QUOTES, 'UTF-8') ?>';}).catch(function(){b.disabled=false;b.innerHTML='<i class="fa-solid fa-calculator me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_recalculate', 'Пересчитать'), ENT_QUOTES, 'UTF-8') ?>';body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_potential_error', 'Ошибка расчета потенциала.'), ENT_QUOTES, 'UTF-8') ?></p>';});
  });
  document.getElementById('clearPotentialBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_potential', 'Удалить расчет потенциала?'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/potential',{method:'DELETE',timeoutMs:10000}).then(function(){body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_potential_not_calculated', 'Потенциал идеи еще не рассчитан.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';b.disabled=false;}).catch(function(){b.disabled=false;});
  });
  deferHiddenAnalysisLoad(loadPotential);
})();
// Risk report
(function(){
  var card=document.getElementById('riskCard');
  if(!card)return;
  var body=document.getElementById('riskCardBody');
  var upd=document.getElementById('riskUpdated');
  var esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
  var pct=function(v){return Math.round((+v||0)*100)+'%';};
  var rlColor=function(l){return {critical:'danger',high:'warning',medium:'info',low:'success'}[l]||'secondary';};

  function loadRisk(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadRisk,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/risk-report',{method:'GET'}).then(function(env){renderRisk(env.data||{});}).catch(function(){});
  }

  function renderRisk(data){
    if(data.empty||!data.risk_report_json){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('risks',false);body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_risks_not_calculated', 'Риски идеи еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';return;}
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('risks',true);
    var rr=typeof data.risk_report_json==='string'?JSON.parse(data.risk_report_json):(data.risk_report_json||{});
    var r=rr.risk_report||{};
    var risks=r.risks||[];
    if(upd&&data.updated_at)upd.textContent='<?= htmlspecialchars($t('ideas.state_from', 'от '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var h='';
    h+='<div class="row mb-2 text-center"><div class="col"><strong data-i18n="ideas.analysis_risk_level"><?= htmlspecialchars($t('ideas.analysis_risk_level', 'Уровень риска'), ENT_QUOTES, 'UTF-8') ?></strong><br><span class="badge bg-'+rlColor(data.overall_risk_level)+' fs-6">'+esc(data.overall_risk_level)+'</span></div><div class="col"><strong data-i18n="ideas.analysis_assessment"><?= htmlspecialchars($t('ideas.analysis_assessment', 'Оценка'), ENT_QUOTES, 'UTF-8') ?></strong><br>'+data.overall_risk_score+' / 25</div><div class="col"><strong data-i18n="ideas.analysis_confidence"><?= htmlspecialchars($t('ideas.analysis_confidence', 'Уверенность'), ENT_QUOTES, 'UTF-8') ?></strong><br>'+pct(data.confidence_score)+'</div></div>';
    if(r.summary)h+='<div class="mb-2"><strong data-i18n="ideas.analysis_summary"><?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(r.summary)+'</div>';
    var rd=r.risk_distribution||{};
    h+='<div class="mb-2"><strong data-i18n="ideas.analysis_distribution"><?= htmlspecialchars($t('ideas.analysis_distribution', 'Распределение:'), ENT_QUOTES, 'UTF-8') ?></strong> ';
    if(rd.critical>0)h+='<span class="badge bg-danger me-1"><?= htmlspecialchars($t('ideas.badge_critical', 'Критические:'), ENT_QUOTES, 'UTF-8') ?> '+rd.critical+'</span>';
    if(rd.high>0)h+='<span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($t('ideas.badge_high', 'Высокие:'), ENT_QUOTES, 'UTF-8') ?> '+rd.high+'</span>';
    if(rd.medium>0)h+='<span class="badge bg-info me-1"><?= htmlspecialchars($t('ideas.badge_medium', 'Средние:'), ENT_QUOTES, 'UTF-8') ?> '+rd.medium+'</span>';
    if(rd.low>0)h+='<span class="badge bg-success me-1"><?= htmlspecialchars($t('ideas.badge_low', 'Низкие:'), ENT_QUOTES, 'UTF-8') ?> '+rd.low+'</span>';
    h+='</div>';
    if(data.confidence_score<0.5)h+='<div class="alert alert-warning py-1 small mb-2"><?= htmlspecialchars($t('ideas.alert_preliminary_estimate', 'Оценка предварительная: данных недостаточно.'), ENT_QUOTES, 'UTF-8') ?></div>';
    if(risks.length){h+='<div class="table-responsive"><table class="table table-sm small mb-2"><thead><tr><th data-i18n="ideas.th_risk"><?= htmlspecialchars($t('ideas.th_risk', 'Риск'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_category_short"><?= htmlspecialchars($t('ideas.th_category_short', 'Кат.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_probability"><?= htmlspecialchars($t('ideas.th_probability', 'Вер.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_impact"><?= htmlspecialchars($t('ideas.th_impact', 'Влияние'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_score"><?= htmlspecialchars($t('ideas.th_score', 'Оценка'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_level"><?= htmlspecialchars($t('ideas.th_level', 'Уровень'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody>';
    risks.forEach(function(rk){h+='<tr><td><strong>'+esc(rk.title)+'</strong><br><small>'+esc(rk.description||'')+'</small></td><td>'+esc(rk.category)+'</td><td>'+rk.probability_score+'</td><td>'+rk.impact_score+'</td><td>'+rk.risk_score+'</td><td><span class="badge bg-'+rlColor(rk.risk_level)+'">'+esc(rk.risk_level)+'</span></td></tr>';
    if(rk.mitigation_actions&&rk.mitigation_actions.length)h+='<tr><td colspan="6"><small><strong data-i18n="ideas.analysis_mitigation"><?= htmlspecialchars($t('ideas.analysis_mitigation', 'Снижение:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(rk.mitigation_actions.join('; '))+'</small></td></tr>';});
    h+='</tbody></table></div>';}
    var lists=[['key_risk_drivers','<?= htmlspecialchars($t('ideas.list_key_risk_drivers', 'Ключевые причины'), ENT_QUOTES, 'UTF-8') ?>'],['critical_risks','<?= htmlspecialchars($t('ideas.list_critical_risks', 'Критические риски'), ENT_QUOTES, 'UTF-8') ?>'],['recommended_first_actions','<?= htmlspecialchars($t('ideas.list_first_actions', 'Первоочередные действия'), ENT_QUOTES, 'UTF-8') ?>'],['missing_data_for_better_assessment','<?= htmlspecialchars($t('ideas.list_missing_data', 'Недостающие данные'), ENT_QUOTES, 'UTF-8') ?>'],['assumptions','<?= htmlspecialchars($t('ideas.list_assumptions', 'Предположения'), ENT_QUOTES, 'UTF-8') ?>'],['limitations','<?= htmlspecialchars($t('ideas.list_limitations', 'Ограничения анализа'), ENT_QUOTES, 'UTF-8') ?>']];
    lists.forEach(function(item){var arr=r[item[0]]||[];if(arr.length){h+='<div class="mt-1"><strong>'+item[1]+':</strong><ul class="mb-1 small">';arr.forEach(function(v){h+='<li>'+esc(typeof v==='string'?v:(v.label||v.text||JSON.stringify(v)))+'</li>';});h+='</ul></div>';}});
    body.innerHTML=h;
  }

  document.getElementById('calcRiskBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_calculating_risks', 'Считаю риски...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_analyzing_risks', 'AI анализирует риски...'), ENT_QUOTES, 'UTF-8') ?></p>';
    window.CRM.api.request('api/v1/ideas/'+pid+'/risk-report',{method:'POST',timeoutMs:300000}).then(function(env){renderRisk(env.data||{});b.disabled=false;b.innerHTML='<i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_recalculate', 'Рассчитать заново'), ENT_QUOTES, 'UTF-8') ?>';}).catch(function(){b.disabled=false;b.innerHTML='<i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_recalculate', 'Рассчитать заново'), ENT_QUOTES, 'UTF-8') ?>';body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_risk_error', 'Ошибка расчета рисков.'), ENT_QUOTES, 'UTF-8') ?></p>';});
  });
  document.getElementById('clearRiskBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_risk', 'Удалить риск-отчет? Идея и вопросы не будут удалены.'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/risk-report',{method:'DELETE',timeoutMs:10000}).then(function(){body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_risks_not_calculated', 'Риски идеи еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';b.disabled=false;}).catch(function(){b.disabled=false;});
  });
  deferHiddenAnalysisLoad(loadRisk);
})();
// Pitfalls
(function(){
  var card=document.getElementById('pitfallsCard');
  if(!card)return;
  var body=document.getElementById('pitfallsCardBody');
  var upd=document.getElementById('pitfallsUpdated');
  var esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
  var pct=function(v){return Math.round((+v||0)*100)+'%';};
  var plColor=function(l){return {critical:'danger',high:'warning',medium:'info',low:'success'}[l]||'secondary';};

  function loadPitfalls(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadPitfalls,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/pitfalls',{method:'GET'}).then(function(env){renderPitfalls(env.data||{});}).catch(function(){});
  }

  function renderPitfalls(data){
    if(data.empty||!data.pitfalls_json){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('pitfalls',false);body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_pitfalls_not_calculated', 'Подводные камни еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';return;}
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('pitfalls',true);
    var pj=typeof data.pitfalls_json==='string'?JSON.parse(data.pitfalls_json):(data.pitfalls_json||{});
    var pits=pj.pitfalls||[];
    if(upd&&data.updated_at)upd.textContent='<?= htmlspecialchars($t('ideas.state_from', 'от '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var h='';
    h+='<div class="row mb-2 text-center"><div class="col"><strong data-i18n="ideas.analysis_complexity"><?= htmlspecialchars($t('ideas.analysis_complexity', 'Сложность'), ENT_QUOTES, 'UTF-8') ?></strong><br><span class="badge bg-'+plColor(data.overall_hidden_complexity)+' fs-6">'+esc(data.overall_hidden_complexity)+'</span></div><div class="col"><strong data-i18n="ideas.analysis_found"><?= htmlspecialchars($t('ideas.analysis_found', 'Найдено'), ENT_QUOTES, 'UTF-8') ?></strong><br>'+pits.length+'</div><div class="col"><strong data-i18n="ideas.analysis_confidence"><?= htmlspecialchars($t('ideas.analysis_confidence', 'Уверенность'), ENT_QUOTES, 'UTF-8') ?></strong><br>'+pct(data.data_confidence)+'</div></div>';
    if(data.overall_summary)h+='<div class="mb-2"><strong data-i18n="ideas.analysis_summary"><?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.overall_summary)+'</div>';
    if(data.data_confidence<0.5)h+='<div class="alert alert-warning py-1 small mb-2"><?= htmlspecialchars($t('ideas.alert_preliminary_estimate', 'Оценка приблизительная из-за нехватки данных.'), ENT_QUOTES, 'UTF-8') ?></div>';
    if(pits.length){h+='<div class="table-responsive"><table class="table table-sm small mb-2"><thead><tr><th>#</th><th data-i18n="ideas.th_pitfall"><?= htmlspecialchars($t('ideas.th_pitfall', 'Камень'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_category_short"><?= htmlspecialchars($t('ideas.th_category_short', 'Кат.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_probability"><?= htmlspecialchars($t('ideas.th_probability', 'Вер.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_impact"><?= htmlspecialchars($t('ideas.th_impact', 'Влиян.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_hidden"><?= htmlspecialchars($t('ideas.th_hidden', 'Скрыт.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_urgency"><?= htmlspecialchars($t('ideas.th_urgency', 'Сроч.'), ENT_QUOTES, 'UTF-8') ?></th><th data-i18n="ideas.th_priority"><?= htmlspecialchars($t('ideas.th_priority', 'Приор.'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead><tbody>';
    pits.forEach(function(p,i){var badge=plColor(p.priority_level);h+='<tr><td>'+(i+1)+'</td><td><strong>'+esc(p.title)+'</strong><br><small>'+esc(p.description||'')+'</small>'+(p.why_hidden?'<br><small class="text-warning"><?= htmlspecialchars($t('ideas.label_why_hidden', 'Почему скрыт:'), ENT_QUOTES, 'UTF-8') ?> '+esc(p.why_hidden)+'</small>':'')+(p.consequence?'<br><small class="text-danger"><?= htmlspecialchars($t('ideas.label_consequence', 'Последствия:'), ENT_QUOTES, 'UTF-8') ?> '+esc(p.consequence)+'</small>':'')+(p.mitigation_steps&&p.mitigation_steps.length?'<br><small class="text-success"><?= htmlspecialchars($t('ideas.label_mitigation', 'Снижение:'), ENT_QUOTES, 'UTF-8') ?> '+esc(p.mitigation_steps.join('; '))+'</small>':'')+(p.detection_signals&&p.detection_signals.length?'<br><small class="text-info"><?= htmlspecialchars($t('ideas.label_signals', 'Сигналы:'), ENT_QUOTES, 'UTF-8') ?> '+esc(p.detection_signals.join('; '))+'</small>':'')+(p.validation_steps&&p.validation_steps.length?'<br><small class="text-primary"><?= htmlspecialchars($t('ideas.label_validation', 'Проверка:'), ENT_QUOTES, 'UTF-8') ?> '+esc(p.validation_steps.join('; '))+'</small>':'')+'</td><td>'+esc(p.category)+'</td><td>'+p.probability_score+'</td><td>'+p.impact_score+'</td><td>'+p.hiddenness_score+'</td><td>'+p.urgency_score+'</td><td><span class="badge bg-'+badge+'">'+p.priority_score+' ('+esc(p.priority_level)+')</span></td></tr>';});
    h+='</tbody></table></div>';}
    body.innerHTML=h;
  }

  document.getElementById('calcPitfallsBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_searching_pitfalls', 'Ищу камни...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_searching_pitfalls', 'AI ищет подводные камни...'), ENT_QUOTES, 'UTF-8') ?></p>';
    window.CRM.api.request('api/v1/ideas/'+pid+'/pitfalls',{method:'POST',timeoutMs:300000}).then(function(env){renderPitfalls(env.data||{});b.disabled=false;b.innerHTML='<i class="fa-solid fa-magnifying-glass-chart me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_recalculate', 'Рассчитать заново'), ENT_QUOTES, 'UTF-8') ?>';}).catch(function(){b.disabled=false;b.innerHTML='<i class="fa-solid fa-magnifying-glass-chart me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_recalculate', 'Рассчитать заново'), ENT_QUOTES, 'UTF-8') ?>';body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_pitfalls_error', 'Ошибка поиска подводных камней.'), ENT_QUOTES, 'UTF-8') ?></p>';});
  });
  document.getElementById('clearPitfallsBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_pitfalls', 'Удалить подводные камни? Идея и вопросы не будут удалены.'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/pitfalls',{method:'DELETE',timeoutMs:10000}).then(function(){body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_pitfalls_not_calculated', 'Подводные камни еще не рассчитаны.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';b.disabled=false;}).catch(function(){b.disabled=false;});
  });
  deferHiddenAnalysisLoad(loadPitfalls);
})();
// Implementation plan
(function(){
  var card=document.getElementById('planCard');
  if(!card)return;
  var body=document.getElementById('planCardBody');
  var upd=document.getElementById('planUpdated');
  var esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
  var pct=function(v){return Math.round((+v||0)*100)+'%';};

  function loadPlan(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadPlan,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/implementation-plan',{method:'GET'}).then(function(env){renderPlan(env.data||{});}).catch(function(){});
  }

  function renderPlan(data){
    if(data.empty||!data.plan_json){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('plan',false);body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_plan_not_collected', 'План реализации еще не собран.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';return;}
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncStep('plan',true);
    var pj=typeof data.plan_json==='string'?JSON.parse(data.plan_json):(data.plan_json||{});
    var ip=pj.implementation_plan||{};
    var stages=ip.stages||[];
    var days=ip.next_7_days||{};
    var dTasks=days.tasks||[];
    if(upd&&data.updated_at)upd.textContent='<?= htmlspecialchars($t('ideas.state_from', 'от '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var h='';
    if(data.summary)h+='<div class="mb-2"><strong data-i18n="ideas.analysis_summary"><?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.summary)+'</div>';
    h+='<div class="row mb-2 small"><div class="col"><strong data-i18n="ideas.analysis_plan_type"><?= htmlspecialchars($t('ideas.analysis_plan_type', 'Тип:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.plan_type)+'</div><div class="col"><strong data-i18n="ideas.analysis_horizon"><?= htmlspecialchars($t('ideas.analysis_horizon', 'Горизонт:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.planning_horizon)+'</div><div class="col"><strong data-i18n="ideas.analysis_confidence"><?= htmlspecialchars($t('ideas.analysis_confidence', 'Уверенность:'), ENT_QUOTES, 'UTF-8') ?></strong> '+pct(data.confidence_score)+'</div></div>';
    if(ip.data_limitations&&ip.data_limitations.length){h+='<div class="mb-1"><strong data-i18n="ideas.analysis_limitations"><?= htmlspecialchars($t('ideas.analysis_limitations', 'Ограничения:'), ENT_QUOTES, 'UTF-8') ?></strong><ul class="mb-1 small">';ip.data_limitations.forEach(function(v){h+='<li>'+esc(v)+'</li>';});h+='</ul></div>';}
    if(data.confidence_score<0.5)h+='<div class="alert alert-warning py-1 small mb-2"><?= htmlspecialchars($t('ideas.alert_preliminary_plan', 'План предварительный — недостаточно данных.'), ENT_QUOTES, 'UTF-8') ?></div>';
    if(stages.length){h+='<div class="mt-2"><strong data-i18n="ideas.analysis_stages"><?= htmlspecialchars($t('ideas.analysis_stages', 'Этапы:'), ENT_QUOTES, 'UTF-8') ?></strong>';stages.forEach(function(s,i){h+='<div class="ms-2 mb-2 p-2 border rounded"><strong>'+(i+1)+'. '+esc(s.title)+'</strong> <span class="text-muted">— '+esc(s.goal||'')+'</span>';if(s.tasks&&s.tasks.length){h+='<ul class="mb-0 small">';s.tasks.forEach(function(t){h+='<li><strong>'+esc(t.title)+'</strong> '+esc(t.description||'')+' <span class="badge bg-info">'+esc(t.priority)+'</span> → '+esc(t.expected_result||'')+'</li>';});h+='</ul>';}h+='</div>';});h+='</div>';}
    if(dTasks.length){h+='<div class="mt-2"><strong data-i18n="ideas.analysis_next_7_days"><?= htmlspecialchars($t('ideas.analysis_next_7_days', 'Ближайшие 7 дней:'), ENT_QUOTES, 'UTF-8') ?></strong><ul class="small">';dTasks.forEach(function(t){h+='<li><strong class="text-success">'+esc(t.title)+'</strong><br>'+esc(t.description||'')+' <span class="text-muted">('+esc(t.priority)+', '+esc(t.complexity)+', ~'+esc(t.estimated_time||'?')+')</span></li>';});h+='</ul></div>';}
    if(ip.milestones&&ip.milestones.length){h+='<div class="mt-1"><strong data-i18n="ideas.analysis_milestones"><?= htmlspecialchars($t('ideas.analysis_milestones', 'Контрольные точки:'), ENT_QUOTES, 'UTF-8') ?></strong><ul class="mb-1 small">';ip.milestones.forEach(function(m){h+='<li>'+esc(m.title||m)+'</li>';});h+='</ul></div>';}
    if(ip.risks&&ip.risks.length){h+='<div class="mt-1"><strong data-i18n="ideas.analysis_risks"><?= htmlspecialchars($t('ideas.analysis_risks', 'Риски:'), ENT_QUOTES, 'UTF-8') ?></strong><ul class="mb-1 small">';ip.risks.forEach(function(r){h+='<li>'+esc(typeof r==='string'?r:r.risk)+'</li>';});h+='</ul></div>';}
    body.innerHTML=h;
  }

  document.getElementById('buildPlanBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_building_plan', 'Собираю план...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_building_plan', 'AI составляет план...'), ENT_QUOTES, 'UTF-8') ?></p>';
    window.CRM.api.request('api/v1/ideas/'+pid+'/implementation-plan',{method:'POST',timeoutMs:300000}).then(function(env){renderPlan(env.data||{});b.disabled=false;b.innerHTML='<i class="fa-solid fa-play me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Собрать заново'), ENT_QUOTES, 'UTF-8') ?>';}).catch(function(){b.disabled=false;b.innerHTML='<i class="fa-solid fa-play me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Собрать заново'), ENT_QUOTES, 'UTF-8') ?>';body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_plan_error', 'Ошибка сборки плана.'), ENT_QUOTES, 'UTF-8') ?></p>';});
  });
  document.getElementById('clearPlanBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_plan', 'Удалить план реализации?'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/implementation-plan',{method:'DELETE',timeoutMs:10000}).then(function(){body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_plan_not_collected', 'План реализации еще не собран.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';b.disabled=false;}).catch(function(){b.disabled=false;});
  });
  deferHiddenAnalysisLoad(loadPlan);
})();
// Final recommendation
(function(){
  var card=document.getElementById('finalCard');
  if(!card)return;
  var body=document.getElementById('finalCardBody');
  var upd=document.getElementById('finalUpdated');
  var esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
  var pct=function(v){return Math.round((+v||0)*100)+'%';};
  var stColor=function(s){return {proceed:'success',proceed_with_validation:'info',refine_first:'warning',collect_more_data:'secondary',postpone:'warning',reject_current_form:'danger'}[s]||'secondary';};

  function loadFinal(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadFinal,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/final-recommendation',{method:'GET'}).then(function(env){renderFinal(env.data||{});}).catch(function(){});
  }

  function renderFinal(data){
    if(data.empty||!data.recommendation_json){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncResultVisibility('final',false);body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_final_not_generated', 'Итоговая рекомендация еще не сформирована.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';card.classList.remove('pipeline-visible');return;}
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncResultVisibility('final',true);
    card.classList.add('pipeline-visible');
    var fj=typeof data.recommendation_json==='string'?JSON.parse(data.recommendation_json):(data.recommendation_json||{});
    var fr=fj.final_recommendation||{};
    if(upd&&data.updated_at)upd.textContent='<?= htmlspecialchars($t('ideas.state_from', 'от '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var h='';
    h+='<div class="crm-final-summary">';
    h+='<div><div class="crm-final-label" data-i18n="ideas.analysis_recommendation"><?= htmlspecialchars($t('ideas.analysis_recommendation', 'Рекомендация'), ENT_QUOTES, 'UTF-8') ?></div><div class="crm-final-status crm-final-status-'+esc(data.status||'default')+'">'+esc(data.status_label||data.status)+'</div></div>';
    h+='<div><div class="crm-final-label" data-i18n="ideas.analysis_assessment"><?= htmlspecialchars($t('ideas.analysis_assessment', 'Оценка'), ENT_QUOTES, 'UTF-8') ?></div><div class="crm-final-score">'+esc(data.calculated_recommendation_score)+'</div><div class="crm-final-note" data-i18n="ideas.analysis_out_of"><?= htmlspecialchars($t('ideas.analysis_out_of', 'из 100'), ENT_QUOTES, 'UTF-8') ?></div></div>';
    h+='<div><div class="crm-final-label" data-i18n="ideas.analysis_confidence"><?= htmlspecialchars($t('ideas.analysis_confidence', 'Уверенность'), ENT_QUOTES, 'UTF-8') ?></div><div class="crm-final-score">'+pct(data.confidence_score)+'</div></div>';
    h+='</div>';
    h+='<div class="crm-final-metrics"><div><span data-i18n="ideas.analysis_potential"><?= htmlspecialchars($t('ideas.analysis_potential', 'Потенциал'), ENT_QUOTES, 'UTF-8') ?></span><strong>'+esc(data.potential_score)+'</strong></div><div><span data-i18n="ideas.analysis_feasibility"><?= htmlspecialchars($t('ideas.analysis_feasibility', 'Реализуемость'), ENT_QUOTES, 'UTF-8') ?></span><strong>'+esc(data.feasibility_score)+'</strong></div><div><span data-i18n="ideas.analysis_risk_score"><?= htmlspecialchars($t('ideas.analysis_risk_score', 'Риск'), ENT_QUOTES, 'UTF-8') ?></span><strong>'+esc(data.risk_score)+'</strong></div><div><span data-i18n="ideas.analysis_data_completeness"><?= htmlspecialchars($t('ideas.analysis_data_completeness', 'Данные'), ENT_QUOTES, 'UTF-8') ?></span><strong>'+esc(data.data_completeness_score)+'</strong></div><div><span data-i18n="ideas.analysis_plan_quality"><?= htmlspecialchars($t('ideas.analysis_plan_quality', 'План'), ENT_QUOTES, 'UTF-8') ?></span><strong>'+esc(data.plan_quality_score)+'</strong></div><div><span data-i18n="ideas.analysis_blockers"><?= htmlspecialchars($t('ideas.analysis_blockers', 'Блокеры'), ENT_QUOTES, 'UTF-8') ?></span><strong>'+esc(data.blocker_score)+'</strong></div></div>';
    if(fr.short_verdict)h+='<p class="crm-final-verdict"><strong data-i18n="ideas.analysis_verdict"><?= htmlspecialchars($t('ideas.analysis_verdict', 'Вывод:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(fr.short_verdict)+'</p>';
    if(fr.user_friendly_summary)h+='<p class="crm-final-text">'+esc(fr.user_friendly_summary)+'</p>';
    var lists=[['main_reasons','<?= htmlspecialchars($t('ideas.list_main_reasons', 'Причины'), ENT_QUOTES, 'UTF-8') ?>'],['positive_arguments','<?= htmlspecialchars($t('ideas.list_positive_arguments', 'В пользу'), ENT_QUOTES, 'UTF-8') ?>'],['negative_arguments','<?= htmlspecialchars($t('ideas.list_negative_arguments', 'Против'), ENT_QUOTES, 'UTF-8') ?>'],['critical_blockers','<?= htmlspecialchars($t('ideas.list_critical_blockers', 'Критичные препятствия'), ENT_QUOTES, 'UTF-8') ?>'],['conditions_to_proceed','<?= htmlspecialchars($t('ideas.list_conditions', 'Условия продолжения'), ENT_QUOTES, 'UTF-8') ?>'],['what_to_validate_first','<?= htmlspecialchars($t('ideas.list_validate_first', 'Проверить в первую очередь'), ENT_QUOTES, 'UTF-8') ?>'],['next_best_actions','<?= htmlspecialchars($t('ideas.list_next_actions', 'Ближайшие действия'), ENT_QUOTES, 'UTF-8') ?>'],['what_can_go_wrong','<?= htmlspecialchars($t('ideas.list_what_can_go_wrong', 'Что может пойти не так'), ENT_QUOTES, 'UTF-8') ?>'],['missing_data_that_affects_recommendation','<?= htmlspecialchars($t('ideas.list_missing_data', 'Недостающие данные'), ENT_QUOTES, 'UTF-8') ?>'],['assumptions_used','<?= htmlspecialchars($t('ideas.list_assumptions', 'Предположения'), ENT_QUOTES, 'UTF-8') ?>']];
    lists.forEach(function(item){var arr=fr[item[0]]||[];if(arr.length){h+='<div class="mt-1"><strong>'+item[1]+':</strong><ul class="mb-1 small">';arr.forEach(function(v){h+='<li>'+esc(typeof v==='string'?v:(v.label||v.text||JSON.stringify(v)))+'</li>';});h+='</ul></div>';}});
    body.innerHTML=h;
  }

  document.getElementById('buildFinalBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_building_final', 'Формирую...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_building_final', 'AI формирует итоговую рекомендацию...'), ENT_QUOTES, 'UTF-8') ?></p>';
    window.CRM.api.request('api/v1/ideas/'+pid+'/final-recommendation',{method:'POST',timeoutMs:300000}).then(function(env){renderFinal(env.data||{});b.disabled=false;b.innerHTML='<i class="fa-solid fa-gavel me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild_final', 'Сформировать заново'), ENT_QUOTES, 'UTF-8') ?>';}).catch(function(){b.disabled=false;b.innerHTML='<i class="fa-solid fa-gavel me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild_final', 'Сформировать заново'), ENT_QUOTES, 'UTF-8') ?>';body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_final_error', 'Ошибка формирования рекомендации.'), ENT_QUOTES, 'UTF-8') ?></p>';});
  });
  document.getElementById('clearFinalBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_final', 'Удалить итоговую рекомендацию?'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/final-recommendation',{method:'DELETE',timeoutMs:10000}).then(function(){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncResultVisibility('final',false);body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_final_not_generated', 'Итоговая рекомендация еще не сформирована.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';card.classList.remove('pipeline-visible');b.disabled=false;}).catch(function(){b.disabled=false;});
  });
  loadFinal();
})();
// Suggested tasks — tree from final recommendation + plan
(function(){
  var card=document.getElementById('tasksCard');
  if(!card)return;
  var body=document.getElementById('tasksCardBody');
  var upd=document.getElementById('tasksUpdated');
  var esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};

  function loadTasks(){
    if(!window.CRM||!window.CRM.api){setTimeout(loadTasks,300);return;}
    window.CRM.api.request('api/v1/ideas/'+pid+'/suggested-tasks',{method:'GET'}).then(function(env){renderTasks(env.data||{});}).catch(function(){});
  }

  function renderTask(task,level){
    var h='';
    var prioLabel={high:'<?= htmlspecialchars($t('ideas.prio_high', 'Высокий'), ENT_QUOTES, 'UTF-8') ?>',medium:'<?= htmlspecialchars($t('ideas.prio_medium', 'Средний'), ENT_QUOTES, 'UTF-8') ?>',low:'<?= htmlspecialchars($t('ideas.prio_low', 'Низкий'), ENT_QUOTES, 'UTF-8') ?>'}[task.priority]||task.priority;
    h+='<div class="crm-suggested-task crm-suggested-task-level-'+Math.min(level,3)+'">';
    h+='<div class="crm-suggested-task-head">';
    h+='<strong>'+esc(task.title||task.id)+'</strong>';
    if(task.priority)h+=' <span class="crm-task-meta">'+esc(prioLabel)+'</span>';
    if(task.estimated_time)h+=' <span class="crm-task-meta">'+esc(task.estimated_time)+'</span>';
    h+='</div>';
    if(task.description)h+='<div class="crm-suggested-task-text">'+esc(task.description)+'</div>';
    if(task.expected_outcome)h+='<div class="crm-suggested-task-note"><strong data-i18n="ideas.analysis_result"><?= htmlspecialchars($t('ideas.analysis_result', 'Результат:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(task.expected_outcome)+'</div>';
    if(task.depends_on&&task.depends_on.length)h+='<div class="crm-suggested-task-note"><strong data-i18n="ideas.analysis_depends_on"><?= htmlspecialchars($t('ideas.analysis_depends_on', 'Зависит от:'), ENT_QUOTES, 'UTF-8') ?></strong> '+task.depends_on.map(function(d){return '<code>'+esc(d)+'</code>';}).join(', ')+'</div>';
    h+='</div>';
    if(task.subtasks&&task.subtasks.length){
      task.subtasks.forEach(function(st){h+=renderTask(st,level+1);});
    }
    return h;
  }

  function renderTasks(data){
    if(data.empty||!data.tasks_json){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncResultVisibility('tasks',false);body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_tasks_not_generated', 'Предлагаемые задачи еще не сформированы.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';card.classList.remove('pipeline-visible');return;}
    if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncResultVisibility('tasks',true);
    card.classList.add('pipeline-visible');
    var tj=typeof data.tasks_json==='string'?JSON.parse(data.tasks_json):(data.tasks_json||{});
    var projects=tj.projects||[];
    var flatTasks=tj.tasks||[];
    if(upd&&data.updated_at)upd.textContent='<?= htmlspecialchars($t('ideas.state_from', 'от '), ENT_QUOTES, 'UTF-8') ?>'+data.updated_at.replace('T',' ').substring(0,16);
    var h='';
    if(data.summary)h+='<div class="mb-2"><strong data-i18n="ideas.analysis_summary"><?= htmlspecialchars($t('ideas.analysis_summary', 'Резюме:'), ENT_QUOTES, 'UTF-8') ?></strong> '+esc(data.summary)+'</div>';
    if(projects.length){
      projects.forEach(function(proj){
        h+='<section class="crm-task-project">';
        h+='<h6>'+esc(proj.title||proj.id)+'</h6>';
        if(proj.description)h+='<p>'+esc(proj.description)+'</p>';
        (proj.tasks||[]).forEach(function(t){h+=renderTask(t,0);});
        h+='</section>';
      });
    } else {
      flatTasks.forEach(function(t){h+=renderTask(t,0);});
    }
    body.innerHTML=h||'<p class="text-muted"><?= htmlspecialchars($t('ideas.state_no_tasks', 'Нет задач.'), ENT_QUOTES, 'UTF-8') ?></p>';
    if(projects.length||flatTasks.length){
      var btnHtml='<button class="btn btn-sm crm-btn-primary crm-idea-create-project-btn mt-2" id="createProjectBtn"><i class="fa-solid fa-diagram-project me-1" aria-hidden="true"></i><span data-i18n="ideas.btn_create_project"><?= htmlspecialchars($t('ideas.btn_create_project', 'Создать проект и задачи в CRM'), ENT_QUOTES, 'UTF-8') ?></span></button>';
      body.innerHTML+=btnHtml;
      document.getElementById('createProjectBtn').addEventListener('click',function(){
        var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span><?= htmlspecialchars($t('ideas.state_creating', 'Создаю...'), ENT_QUOTES, 'UTF-8') ?></span>';
        window.CRM.api.request('api/v1/ideas/'+pid+'/create-project-tasks',{method:'POST',timeoutMs:30000}).then(function(env){
          b.disabled=false;b.innerHTML='<i class="fa-solid fa-check me-1" aria-hidden="true"></i><span><?= htmlspecialchars($t('ideas.state_project_created', 'Проект создан'), ENT_QUOTES, 'UTF-8') ?></span>';
          if(window.CRM.br1)window.CRM.br1.notify('success','<?= htmlspecialchars($t('ideas.notify_project_created', 'Проект создан: '), ENT_QUOTES, 'UTF-8') ?>'+(env.data.project_public_id||'')+', <?= htmlspecialchars($t('ideas.notify_tasks_created', 'задач: '), ENT_QUOTES, 'UTF-8') ?>'+(env.data.tasks_created||0));
        }).catch(function(err){
          b.disabled=false;b.innerHTML='<i class="fa-solid fa-diagram-project me-1" aria-hidden="true"></i><span><?= htmlspecialchars($t('ideas.btn_create_project', 'Создать проект и задачи в CRM'), ENT_QUOTES, 'UTF-8') ?></span>';
          var msg=err&&err.envelope&&err.envelope.message?err.envelope.message:'<?= htmlspecialchars($t('ideas.state_project_create_error', 'Ошибка создания проекта'), ENT_QUOTES, 'UTF-8') ?>';
          if(window.CRM.br1)window.CRM.br1.notify('error',msg);
        });
      });
    }
  }

  document.getElementById('buildTasksBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> <?= htmlspecialchars($t('ideas.state_building_tasks', 'Формирую...'), ENT_QUOTES, 'UTF-8') ?>';
    body.innerHTML='<p class="text-muted"><?= htmlspecialchars($t('ideas.state_ai_building_tasks', 'AI формирует дерево задач...'), ENT_QUOTES, 'UTF-8') ?></p>';
    window.CRM.api.request('api/v1/ideas/'+pid+'/suggested-tasks',{method:'POST',timeoutMs:300000}).then(function(env){renderTasks(env.data||{});b.disabled=false;b.innerHTML='<i class="fa-solid fa-list-tree me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Сформировать заново'), ENT_QUOTES, 'UTF-8') ?>';}).catch(function(){b.disabled=false;b.innerHTML='<i class="fa-solid fa-list-tree me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_rebuild', 'Сформировать заново'), ENT_QUOTES, 'UTF-8') ?>';body.innerHTML='<p class="text-danger"><?= htmlspecialchars($t('ideas.state_tasks_error', 'Ошибка формирования задач.'), ENT_QUOTES, 'UTF-8') ?></p>';});
  });
  document.getElementById('clearTasksBtn').addEventListener('click',function(){
    if(!confirm('<?= htmlspecialchars($t('ideas.confirm_clear_tasks', 'Удалить предлагаемые задачи?'), ENT_QUOTES, 'UTF-8') ?>'))return;
    var b=this;b.disabled=true;
    window.CRM.api.request('api/v1/ideas/'+pid+'/suggested-tasks',{method:'DELETE',timeoutMs:10000}).then(function(){if(window.CRM_IDEA_AI_PIPELINE)window.CRM_IDEA_AI_PIPELINE.syncResultVisibility('tasks',false);body.innerHTML='<p class="text-muted mb-0"><?= htmlspecialchars($t('ideas.state_tasks_not_generated', 'Предлагаемые задачи еще не сформированы.'), ENT_QUOTES, 'UTF-8') ?></p>';if(upd)upd.textContent='';card.classList.remove('pipeline-visible');b.disabled=false;}).catch(function(){b.disabled=false;});
  });
  loadTasks();
})();
load();
})();}catch(e){var dl=document.getElementById('debugLog');if(dl)dl.textContent='JS ERROR: '+String(e.message||'unknown')+'\\n'+String(e.stack||'');}
</script>
</body>
<?php else: ?>
<body data-page="ideas" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-ideas-page"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="ideas.list_page_title"><?= htmlspecialchars($t('ideas.list_page_title', 'Идеи'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="ideas.list_subtitle"><?= htmlspecialchars($t('ideas.list_subtitle', 'Предложения по улучшению. Голосуйте, обсуждайте, анализируйте с AI.'), ENT_QUOTES, 'UTF-8') ?></p></div><div><button class="btn crm-btn-primary" id="newIdeaBtn" data-bs-toggle="modal" data-bs-target="#newIdeaModal" data-i18n="ideas.btn_new_idea"><i class="fa-solid fa-lightbulb me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.btn_new_idea', 'Предложить идею'), ENT_QUOTES, 'UTF-8') ?></button></div></div>

<!-- Leaderboards -->
<div class="row g-3 mb-3" id="leaderboardsRow" style="display:none">
  <div class="col-md-6">
    <div class="crm-card crm-section-card"><div class="crm-section-head"><h6 class="mb-0" data-i18n="ideas.leaderboard_votes_title"><i class="fa-solid fa-trophy text-warning me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.leaderboard_votes_title', 'Топ по голосам'), ENT_QUOTES, 'UTF-8') ?></h6></div>
    <div class="p-2"><div class="btn-group btn-group-sm w-100 mb-2" role="group" id="votesPeriodBtns">
      <button class="btn crm-btn-secondary active" data-period="today" data-i18n="ideas.period_today"><?= htmlspecialchars($t('ideas.period_today', 'Сегодня'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" data-period="week" data-i18n="ideas.period_week"><?= htmlspecialchars($t('ideas.period_week', 'Неделя'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" data-period="month" data-i18n="ideas.period_month"><?= htmlspecialchars($t('ideas.period_month', 'Месяц'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" data-period="all" data-i18n="ideas.period_all"><?= htmlspecialchars($t('ideas.period_all', 'Всё время'), ENT_QUOTES, 'UTF-8') ?></button>
    </div><div id="topVotesList" class="small"></div></div></div>
  </div>
  <div class="col-md-6">
    <div class="crm-card crm-section-card"><div class="crm-section-head"><h6 class="mb-0" data-i18n="ideas.leaderboard_comments_title"><i class="fa-solid fa-comments text-info me-1" aria-hidden="true"></i> <?= htmlspecialchars($t('ideas.leaderboard_comments_title', 'Топ по комментариям'), ENT_QUOTES, 'UTF-8') ?></h6></div>
    <div class="p-2"><div class="btn-group btn-group-sm w-100 mb-2" role="group" id="commentsPeriodBtns">
      <button class="btn crm-btn-secondary active" data-period="today" data-i18n="ideas.period_today"><?= htmlspecialchars($t('ideas.period_today', 'Сегодня'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" data-period="week" data-i18n="ideas.period_week"><?= htmlspecialchars($t('ideas.period_week', 'Неделя'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" data-period="month" data-i18n="ideas.period_month"><?= htmlspecialchars($t('ideas.period_month', 'Месяц'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="btn crm-btn-secondary" data-period="all" data-i18n="ideas.period_all"><?= htmlspecialchars($t('ideas.period_all', 'Всё время'), ENT_QUOTES, 'UTF-8') ?></button>
    </div><div id="topCommentsList" class="small"></div></div></div>
  </div>
</div>

<div class="crm-card crm-section-card p-0 table-responsive crm-table-wrap crm-ideas-table-wrap"><table class="table crm-table crm-ideas-table mb-0"><colgroup><col class="crm-ideas-col-title"><col class="crm-ideas-col-author"><col class="crm-ideas-col-region"><col class="crm-ideas-col-date"><col class="crm-ideas-col-visibility"><col class="crm-ideas-col-votes"><col class="crm-ideas-col-actions"></colgroup><thead><tr><th class="sortable" data-sort="title" style="cursor:pointer" data-i18n="ideas.th_idea"><?= htmlspecialchars($t('ideas.th_idea', 'Идея'), ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-sort text-muted ms-1" aria-hidden="true"></i></th><th class="sortable" data-sort="author" style="cursor:pointer" data-i18n="ideas.th_author"><?= htmlspecialchars($t('ideas.th_author', 'Автор'), ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-sort text-muted ms-1" aria-hidden="true"></i></th><th class="sortable" data-sort="region" style="cursor:pointer" data-i18n="ideas.th_region"><?= htmlspecialchars($t('ideas.th_region', 'Регион'), ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-sort text-muted ms-1" aria-hidden="true"></i></th><th class="sortable" data-sort="date" style="cursor:pointer" data-i18n="ideas.th_date"><?= htmlspecialchars($t('ideas.th_date', 'Дата'), ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-sort-down text-primary ms-1" aria-hidden="true"></i></th><th class="sortable" data-sort="visibility" style="cursor:pointer" data-i18n="ideas.th_visibility"><?= htmlspecialchars($t('ideas.th_visibility', 'Видимость'), ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-sort text-muted ms-1" aria-hidden="true"></i></th><th class="sortable" data-sort="votes" style="cursor:pointer" data-i18n="ideas.th_votes"><?= htmlspecialchars($t('ideas.th_votes', 'Голоса'), ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-sort text-muted ms-1" aria-hidden="true"></i></th><th class="crm-table-actions-col" aria-label="<?= htmlspecialchars($t('ideas.th_actions', 'Действия'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="ideas.th_actions"></th></tr></thead><tbody id="ideasBody"><tr><td colspan="7" class="text-muted" data-i18n="ideas.state_loading"><?= htmlspecialchars($t('ideas.state_loading', 'Загрузка...'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div>

<!-- New Idea Modal -->
<div class="modal fade" id="newIdeaModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" data-i18n="ideas.modal_new_title"><?= htmlspecialchars($t('ideas.modal_new_title', 'Новая идея'), ENT_QUOTES, 'UTF-8') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('page.close', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="page.close"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label" for="newIdeaTitle" data-i18n="ideas.field_title"><?= htmlspecialchars($t('ideas.field_title', 'Заголовок'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="newIdeaTitle" placeholder="<?= htmlspecialchars($t('ideas.placeholder_idea_title', 'Кратко опишите идею'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="ideas.placeholder_idea_title"></div>
      <div class="mb-3"><label class="form-label" data-i18n="ideas.field_description"><?= htmlspecialchars($t('ideas.field_description', 'Описание'), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" id="newIdeaDesc" rows="3" placeholder="<?= htmlspecialchars($t('ideas.placeholder_idea_desc', 'Подробнее об идее'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="ideas.placeholder_idea_desc" data-crm-visual-editor="1" data-richtext-off="1"></textarea></div>
      <div class="mb-3"><label class="form-label" data-i18n="ideas.field_category"><?= htmlspecialchars($t('ideas.field_category', 'Категория'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" id="newIdeaCat"><option value="product" data-i18n="ideas.cat_product"><?= htmlspecialchars($t('ideas.cat_product', 'Продукт'), ENT_QUOTES, 'UTF-8') ?></option><option value="process" data-i18n="ideas.cat_process"><?= htmlspecialchars($t('ideas.cat_process', 'Процесс'), ENT_QUOTES, 'UTF-8') ?></option><option value="tech" data-i18n="ideas.cat_tech"><?= htmlspecialchars($t('ideas.cat_tech', 'Технологии'), ENT_QUOTES, 'UTF-8') ?></option><option value="other" data-i18n="ideas.cat_other"><?= htmlspecialchars($t('ideas.cat_other', 'Другое'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
      <div class="mb-3"><label class="form-label" for="newIdeaRegion" data-i18n="ideas.field_region"><?= htmlspecialchars($t('ideas.field_region', 'Регион реализации'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" id="newIdeaRegion" placeholder="<?= htmlspecialchars($t('ideas.placeholder_region', 'Например: ru, kz, Санкт-Петербург...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="ideas.placeholder_region"></div>
      <div class="mb-3"><label class="form-label" data-i18n="ideas.field_visibility"><?= htmlspecialchars($t('ideas.field_visibility', 'Видимость'), ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" id="newIdeaVisibility"><option value="public" data-i18n="ideas.opt_public_all"><?= htmlspecialchars($t('ideas.opt_public_all', 'Публичная — видят все'), ENT_QUOTES, 'UTF-8') ?></option><option value="private" data-i18n="ideas.opt_private_only"><?= htmlspecialchars($t('ideas.opt_private_only', 'Приватная — вижу только я'), ENT_QUOTES, 'UTF-8') ?></option></select></div>
      <div class="mb-3"><label class="form-label" for="newIdeaTargetDate" data-i18n="ideas.field_target_date"><?= htmlspecialchars($t('ideas.field_target_date', 'Срок реализации'), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" type="date" id="newIdeaTargetDate"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="page.cancel"><?= htmlspecialchars($t('page.cancel', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button><button type="button" class="btn crm-btn-primary" id="submitIdeaBtn" data-i18n="page.create"><?= htmlspecialchars($t('page.create', 'Создать'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </div></div>
</div>

</main></div></div>
<script>
(function(){var body=document.getElementById('ideasBody');var currentPeriodVotes='today';var currentPeriodComments='today';var allItems=[];var sortField=getCookie('ideas_sort_field')||'date';var sortAsc=getCookie('ideas_sort_asc')==='1';
  function setSortCookie(f,asc){document.cookie='ideas_sort_field='+f+';path=/;max-age=31536000';document.cookie='ideas_sort_asc='+(asc?'1':'0')+';path=/;max-age=31536000';}
  function getCookie(n){var m=document.cookie.match('(^|;)\\s*'+n+'\\s*=\\s*([^;]+)');return m?m.pop():null;}
  function escapeHtml(value){
    if(window.CRM&&window.CRM.text&&typeof window.CRM.text.escapeHtml==='function')return window.CRM.text.escapeHtml(value);
    return String(value==null?'':value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function ideaLocale(){
    if(window.CRM&&typeof window.CRM.ideaLocale==='function')return window.CRM.ideaLocale();
    return String((window.CRM&&(window.CRM.locale||window.CRM.currentLocale))||document.documentElement.lang||'en-GB').replace('_','-');
  }
  function refreshVisualEditorScope(root){if(window.CRM.VisualEditor&&typeof window.CRM.VisualEditor.refreshEditors==='function')window.CRM.VisualEditor.refreshEditors(root||document,true);}
  function getVisualEditorValue(textarea){var value=textarea?textarea.value:'';if(textarea&&window.CRM.VisualEditor&&typeof window.CRM.VisualEditor.getInstances==='function'){window.CRM.VisualEditor.getInstances().forEach(function(editor){if(editor&&editor._textarea===textarea&&typeof editor.getValue==='function')value=editor.getValue();});}return value;}
  function load(){if(!window.CRM||!window.CRM.api){setTimeout(load,200);return;}
  window.CRM.api.request('api/v1/ideas',{method:'GET'}).then(function(env){allItems=((env&&env.data&&env.data.items)||[]);
  renderTable(sortItems(allItems,sortField,sortAsc));
  loadLeaderboards();
  }).catch(function(error){if(window.console&&console.error)console.error('[CRM] Ideas list load failed',error);body.innerHTML='<tr><td colspan="7" class="text-danger"><?= htmlspecialchars($t('ideas.state_load_error', 'Ошибка загрузки'), ENT_QUOTES, 'UTF-8') ?></td></tr>';});}
function sortItems(items,field,asc){
return items.slice().sort(function(a,b){
var va=a[field]||'',vb=b[field]||'';
if(field==='author'){va=a.author_name||a.author_login||'';vb=b.author_name||b.author_login||'';}
if(field==='votes'){va=parseInt(a.vote_count)||0;vb=parseInt(b.vote_count)||0;}
if(field==='region'){va=a.region||'';vb=b.region||'';}
if(field==='visibility'){va=a.visibility||'public';vb=b.visibility||'public';}
if(field==='date'){va=a.created_at||'';vb=b.created_at||'';if(va&&vb)return asc?va.localeCompare(vb):vb.localeCompare(va);}
if(typeof va==='number')return asc?va-vb:vb-va;
return asc?va.localeCompare(vb):vb.localeCompare(va);
});}
function renderTable(items){
if(!items.length){body.innerHTML='<tr><td colspan="7" class="text-muted"><?= htmlspecialchars($t('ideas.state_no_ideas', 'Нет идей. Предложите первую!'), ENT_QUOTES, 'UTF-8') ?></td></tr>';return;}
body.innerHTML=items.map(function(i){
var visibilityBadge=i.visibility==='private'?' <span class="badge bg-warning text-dark" style="font-size:0.65rem"><?= htmlspecialchars($t('ideas.badge_private', 'приватная'), ENT_QUOTES, 'UTF-8') ?></span>':'';
  var region=i.region?'<small class="text-muted">'+escapeHtml(i.region)+'</small>':'<small class="text-muted">—</small>';
  var delBtn='<button class="btn btn-sm crm-btn-danger-icon idea-del-btn" data-pid="'+i.public_id+'" title="<?= htmlspecialchars($t('ideas.btn_delete', 'Удалить'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-title="ideas.btn_delete"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
  var dateStr=i.created_at?new Date(i.created_at).toLocaleDateString(ideaLocale()):'';
  return'<tr><td><a href="index.php?route=idea-detail&id='+encodeURIComponent(i.public_id)+'" class="text-decoration-none crm-idea-title-link"><strong>'+escapeHtml(i.title)+'</strong>'+visibilityBadge+'</a></td><td>'+escapeHtml(i.author_name||i.author_login)+'</td><td>'+region+'</td><td><small>'+dateStr+'</small></td><td>'+(i.visibility==='private'?'<span class="badge bg-warning text-dark"><?= htmlspecialchars($t('ideas.badge_private', 'Приватная'), ENT_QUOTES, 'UTF-8') ?></span>':'<span class="badge bg-info"><?= htmlspecialchars($t('ideas.badge_public', 'Публичная'), ENT_QUOTES, 'UTF-8') ?></span>')+'</td><td>'+escapeHtml(i.vote_count)+'</td><td class="crm-table-actions crm-ideas-actions">'+delBtn+'</td></tr>';
}).join('');
document.querySelectorAll('.idea-del-btn').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();if(!confirm('<?= htmlspecialchars($t('ideas.confirm_delete', 'Удалить идею?'), ENT_QUOTES, 'UTF-8') ?>'))return;var pid=this.dataset.pid;this.disabled=true;window.CRM.api.request('api/v1/ideas/'+pid,{method:'DELETE'}).then(function(){allItems=allItems.filter(function(x){return x.public_id!==pid;});renderTable(sortItems(allItems,sortField,sortAsc));}).catch(function(err){if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.state_delete_error', 'Ошибка удаления'), ENT_QUOTES, 'UTF-8') ?>');});});});
}
function loadLeaderboards(){
document.getElementById('leaderboardsRow').style.display='';
loadTopVotes(currentPeriodVotes);
loadTopComments(currentPeriodComments);
}
function loadTopVotes(period){
  window.CRM.api.request('api/v1/ideas',{method:'GET',query:{sort:'votes',period:period,limit:5}}).then(function(env){var items=((env&&env.data&&env.data.items)||[]);var h='';if(!items.length){h='<div class="crm-empty-state py-2 px-2"><strong class="small"><?= htmlspecialchars($t('ideas.state_no_votes_period', 'За выбранный период голосов нет'), ENT_QUOTES, 'UTF-8') ?></strong><p class="text-muted small mb-0"><?= htmlspecialchars($t('ideas.state_ideas_may_exist', 'Идеи ниже могут быть заполнены, но в этом периоде по ним не голосовали.'), ENT_QUOTES, 'UTF-8') ?></p></div>';}else{items.forEach(function(i,n){h+='<div class="d-flex justify-content-between align-items-center py-1 border-bottom"><div><span class="badge bg-secondary me-1">'+(n+1)+'</span> <a href="index.php?route=idea-detail&id='+encodeURIComponent(i.public_id)+'" class="text-decoration-none small">'+escapeHtml(i.title||'')+'</a></div><span class="badge bg-success">'+escapeHtml(i.vote_count)+'</span></div>';});}document.getElementById('topVotesList').innerHTML=h;}).catch(function(error){if(window.console&&console.warn)console.warn('[CRM] Ideas votes leaderboard failed',error);});}
  function loadTopComments(period){
  window.CRM.api.request('api/v1/ideas',{method:'GET',query:{sort:'comments',period:period,limit:5}}).then(function(env){var items=((env&&env.data&&env.data.items)||[]);var h='';if(!items.length){h='<div class="crm-empty-state py-2 px-2"><strong class="small"><?= htmlspecialchars($t('ideas.state_no_comments_period', 'За выбранный период комментариев нет'), ENT_QUOTES, 'UTF-8') ?></strong><p class="text-muted small mb-0"><?= htmlspecialchars($t('ideas.state_comments_may_exist', 'Список идей ниже может быть заполнен, но обсуждений за этот период не было.'), ENT_QUOTES, 'UTF-8') ?></p></div>';}else{items.forEach(function(i,n){h+='<div class="d-flex justify-content-between align-items-center py-1 border-bottom"><div><span class="badge bg-secondary me-1">'+(n+1)+'</span> <a href="index.php?route=idea-detail&id='+encodeURIComponent(i.public_id)+'" class="text-decoration-none small">'+escapeHtml(i.title||'')+'</a></div><span class="badge bg-info">'+escapeHtml(i.comment_count)+'</span></div>';});}document.getElementById('topCommentsList').innerHTML=h;}).catch(function(error){if(window.console&&console.warn)console.warn('[CRM] Ideas comments leaderboard failed',error);});}
document.getElementById('votesPeriodBtns').addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;this.querySelectorAll('button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');currentPeriodVotes=b.dataset.period;loadTopVotes(currentPeriodVotes);});
document.getElementById('commentsPeriodBtns').addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;this.querySelectorAll('button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');currentPeriodComments=b.dataset.period;loadTopComments(currentPeriodComments);});
document.getElementById('submitIdeaBtn').addEventListener('click',function(){
var t=document.getElementById('newIdeaTitle').value.trim();
var newIdeaDesc=document.getElementById('newIdeaDesc');
var d=getVisualEditorValue(newIdeaDesc).trim();
var c=document.getElementById('newIdeaCat').value;
var r=document.getElementById('newIdeaRegion').value.trim();
var v=document.getElementById('newIdeaVisibility').value;
var td=document.getElementById('newIdeaTargetDate').value;
if(!t){if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.state_enter_title', 'Введите заголовок'), ENT_QUOTES, 'UTF-8') ?>');return;}
var b=this;b.disabled=true;
window.CRM.api.request('api/v1/ideas',{method:'POST',body:{title:t,description:d,category:c,region:r,visibility:v,target_date:td}}).then(function(env){
bootstrap.Modal.getInstance(document.getElementById('newIdeaModal')).hide();
document.getElementById('newIdeaTitle').value='';document.getElementById('newIdeaDesc').value='';refreshVisualEditorScope(document.getElementById('newIdeaModal'));document.getElementById('newIdeaRegion').value='';document.getElementById('newIdeaTargetDate').value='';
b.disabled=false;load();
if(window.CRM.br1)window.CRM.br1.notify('success','<?= htmlspecialchars($t('ideas.notify_idea_created', 'Идея создана'), ENT_QUOTES, 'UTF-8') ?>');
}).catch(function(err){b.disabled=false;if(window.CRM.br1)window.CRM.br1.notify('error','<?= htmlspecialchars($t('ideas.state_error_prefix', 'Ошибка: '), ENT_QUOTES, 'UTF-8') ?>'+(err.envelope&&err.envelope.message||''));});
});
function updateSortIcon(){
document.querySelectorAll('.sortable i').forEach(function(ic){ic.className='fa-solid fa-sort text-muted ms-1';});
var th=document.querySelector('.sortable[data-sort="'+sortField+'"]');if(th){var icon=th.querySelector('i');icon.className='fa-solid fa-sort-'+(sortAsc?'up':'down')+' text-primary ms-1';}}
document.querySelectorAll('.sortable').forEach(function(th){th.addEventListener('click',function(){var f=this.dataset.sort;if(sortField===f){sortAsc=!sortAsc;}else{sortField=f;sortAsc=false;}setSortCookie(sortField,sortAsc);renderTable(sortItems(allItems,sortField,sortAsc));updateSortIcon();});});
updateSortIcon();
renderTable(sortItems(allItems,sortField,sortAsc));
load();
})();
</script>
</body>
<?php endif; ?>
