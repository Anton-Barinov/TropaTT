<?php declare(strict_types=1); ?>
<?php $title = $t('docs.title', 'TropaTT — Справка'); ?>
<?php
$docsLocale = strtolower((string)($locale ?? 'ru-ru'));
if (!str_starts_with($docsLocale, 'ru')) {
  $isZhDocs = str_starts_with($docsLocale, 'zh');
  $docsCopy = $isZhDocs ? [
    'subtitle' => 'TropaTT 各模块、功能和工作流程的实用指南。',
    'dashboard' => '主页',
    'intro_title' => '如何使用 TropaTT',
    'intro_text' => 'TropaTT 是一个自托管的 CRM 与工作平台：任务、项目、看板、甘特图、日历、团队聊天、自动化、REST API 和 AI 辅助流程集中在一个产品中。',
    'work_title' => '日常工作流程',
    'work_items' => [
      '从主页查看风险、任务和快速操作。',
      '在任务、看板和甘特图中规划执行进度。',
      '在项目中跟踪团队、客户、截止日期和结果。',
      '在聊天、通知和日历中保持团队同步。',
    ],
    'ai_title' => 'AI 功能',
    'ai_items' => [
      'AI 想法分析：从原始想法生成建议、风险、计划和任务。',
      'AI 日计划和周计划：按优先级和负载安排工作。',
      '任务、项目、客户和分析页面中的 AI 摘要与解释。',
    ],
    'admin_title' => '管理与扩展',
    'admin_items' => [
      '在管理区配置用户、角色、状态、自动化规则和系统设置。',
      '通过 REST API、OpenAPI 文档和 webhook 集成外部系统。',
      '模块化结构允许添加自定义模块而不破坏核心功能。',
    ],
    'install_title' => '安装',
    'install_text' => '将项目上传到 PHP/MySQL 主机，打开 /web/install.php，并按照浏览器安装向导完成数据库和管理员账户配置。',
  ] : [
    'subtitle' => 'Practical guide to TropaTT sections, features and workflows.',
    'dashboard' => 'Open dashboard',
    'intro_title' => 'How to Use TropaTT',
    'intro_text' => 'TropaTT is a self-hosted CRM and work platform: tasks, projects, Kanban, Gantt, calendar, team chat, automation, REST API and AI-assisted workflows in one product.',
    'work_title' => 'Daily Workflow',
    'work_items' => [
      'Start from Dashboard to review risks, tasks and quick actions.',
      'Plan execution in Tasks, Kanban and Gantt.',
      'Track teams, clients, deadlines and results in Projects.',
      'Keep the team aligned with Chats, Notifications and Calendar.',
    ],
    'ai_title' => 'AI Tools',
    'ai_items' => [
      'AI idea analysis turns a raw idea into recommendations, risks, plans and tasks.',
      'AI day and week planning orders work by priority and workload.',
      'AI summaries and explanations are available for tasks, projects, clients and analytics.',
    ],
    'admin_title' => 'Administration and Extensions',
    'admin_items' => [
      'Configure users, roles, statuses, automation rules and system settings in Administration.',
      'Integrate external systems through REST API, OpenAPI documentation and webhooks.',
      'The modular structure lets you add custom modules without breaking the core.',
    ],
    'install_title' => 'Installation',
    'install_text' => 'Upload the project to a PHP/MySQL host, open /web/install.php and follow the browser installer to configure the database and administrator account.',
  ];
?>
<body data-page="docs" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title"><?= htmlspecialchars($t('docs.page_title', $isZhDocs ? '文档' : 'Documentation'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle"><?= htmlspecialchars($docsCopy['subtitle'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn crm-btn-primary" href="index.php?route=dashboard"><?= htmlspecialchars($docsCopy['dashboard'], ENT_QUOTES, 'UTF-8') ?></a></div>
<div class="row g-3">
<?php foreach (['intro', 'work', 'ai', 'admin', 'install'] as $docsBlock): ?>
  <div class="col-12">
    <section class="crm-card crm-section-card mb-3">
      <div class="crm-section-head"><div><h2 class="h5 mb-0"><?= htmlspecialchars($docsCopy[$docsBlock . '_title'], ENT_QUOTES, 'UTF-8') ?></h2></div></div>
      <div class="p-3">
        <?php if (isset($docsCopy[$docsBlock . '_text'])): ?><p class="mb-0"><?= htmlspecialchars($docsCopy[$docsBlock . '_text'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if (isset($docsCopy[$docsBlock . '_items'])): ?><ul class="mb-0"><?php foreach ($docsCopy[$docsBlock . '_items'] as $docsItem): ?><li><?= htmlspecialchars($docsItem, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
    </section>
  </div>
<?php endforeach; ?>
</div></main></div></div></body>
<?php return; ?>
<?php } ?>
<body data-page="docs" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title" data-i18n="docs.page_title"><?= htmlspecialchars($t('docs.page_title', 'Справка'), ENT_QUOTES, 'UTF-8') ?></h1><p class="crm-subtitle" data-i18n="docs.subtitle"><?= htmlspecialchars($t('docs.subtitle', 'Полное руководство по разделам, функциям и рабочим сценариям TropaTT.'), ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn crm-btn-primary" href="index.php?route=dashboard" data-i18n="docs.btn_dashboard"><?= htmlspecialchars($t('docs.btn_dashboard', 'Открыть главную'), ENT_QUOTES, 'UTF-8') ?></a></div>

<div class="row g-3">

<!-- ==================== разделы ==================== -->

<div class="col-12">
<div class="crm-card p-3 mb-3"><h2 class="h5 mb-2" data-i18n="docs.toc_heading"><?= htmlspecialchars($t('docs.toc_heading', 'Содержание'), ENT_QUOTES, 'UTF-8') ?></h2>
<ol class="mb-0" style="column-count:2;column-gap:2rem">
<li><a href="#docs-dashboard" data-i18n="docs.toc_dashboard"><?= htmlspecialchars($t('docs.toc_dashboard', 'Главная'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-tasks" data-i18n="docs.toc_tasks"><?= htmlspecialchars($t('docs.toc_tasks', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-subtasks" data-i18n="docs.toc_subtasks"><?= htmlspecialchars($t('docs.toc_subtasks', 'Подзадачи и чеклисты'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-projects" data-i18n="docs.toc_projects"><?= htmlspecialchars($t('docs.toc_projects', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-kanban" data-i18n="docs.toc_kanban"><?= htmlspecialchars($t('docs.toc_kanban', 'Канбан'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-gantt" data-i18n="docs.toc_gantt"><?= htmlspecialchars($t('docs.toc_gantt', 'Диаграмма Ганта'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-clients" data-i18n="docs.toc_clients"><?= htmlspecialchars($t('docs.toc_clients', 'Клиенты и контрагенты'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-calendar" data-i18n="docs.toc_calendar"><?= htmlspecialchars($t('docs.toc_calendar', 'Календарь'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-chats" data-i18n="docs.toc_chats"><?= htmlspecialchars($t('docs.toc_chats', 'Чат'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-planning" data-i18n="docs.toc_planning"><?= htmlspecialchars($t('docs.toc_planning', 'Планирование (Мой день / Неделя)'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-ideas" data-i18n="docs.toc_ideas"><?= htmlspecialchars($t('docs.toc_ideas', 'Идеи и AI-проработка'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-ai" data-i18n="docs.toc_ai"><?= htmlspecialchars($t('docs.toc_ai', 'AI-инструменты'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-analytics" data-i18n="docs.toc_analytics"><?= htmlspecialchars($t('docs.toc_analytics', 'Аналитика'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-automation" data-i18n="docs.toc_automation"><?= htmlspecialchars($t('docs.toc_automation', 'Автоматизация'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-sla" data-i18n="docs.toc_sla"><?= htmlspecialchars($t('docs.toc_sla', 'SLA и согласования'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-webhooks" data-i18n="docs.toc_webhooks"><?= htmlspecialchars($t('docs.toc_webhooks', 'Вебхуки'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-api" data-i18n="docs.toc_api"><?= htmlspecialchars($t('docs.toc_api', 'API'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-roles" data-i18n="docs.toc_roles"><?= htmlspecialchars($t('docs.toc_roles', 'Роли и права доступа'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-admin" data-i18n="docs.toc_admin"><?= htmlspecialchars($t('docs.toc_admin', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-modules" data-i18n="docs.toc_modules"><?= htmlspecialchars($t('docs.toc_modules', 'Модули'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-installation" data-i18n="docs.toc_installation"><?= htmlspecialchars($t('docs.toc_installation', 'Установка'), ENT_QUOTES, 'UTF-8') ?></a></li>
<li><a href="#docs-faq" data-i18n="docs.toc_faq"><?= htmlspecialchars($t('docs.toc_faq', 'Частые вопросы'), ENT_QUOTES, 'UTF-8') ?></a></li>
</ol>
</div>
</div>

<!-- ==================== 1. Главная ==================== -->
<div class="col-12" id="docs-dashboard">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.dashboard_title"><?= htmlspecialchars($t('docs.dashboard_title', 'Главная (Dashboard)'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.dashboard_note"><?= htmlspecialchars($t('docs.dashboard_note', 'Обзорный дашборд системы.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.dashboard_intro"><?= htmlspecialchars($t('docs.dashboard_intro', 'Главная страница открывается сразу после входа. На ней отображаются:'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.dashboard_item1"><?= htmlspecialchars($t('docs.dashboard_item1', '<strong>Быстрые действия</strong> — кнопки создания проекта, задачи, перехода к списку задач и справочной системе.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.dashboard_item2"><?= htmlspecialchars($t('docs.dashboard_item2', '<strong>Моя сессия</strong> — информация о текущем пользователе, кнопка выхода.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.dashboard_item3"><?= htmlspecialchars($t('docs.dashboard_item3', '<strong>Последние задачи</strong> — список задач, назначенных на текущего пользователя, с фильтрацией по статусу.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<p data-i18n="docs.dashboard_auto"><?= htmlspecialchars($t('docs.dashboard_auto', 'Дашборд автоматически загружает данные через API; никакой ручной настройки не требуется.'), ENT_QUOTES, 'UTF-8') ?></p>
<p><strong data-i18n="docs.dashboard_link_label"><?= htmlspecialchars($t('docs.dashboard_link_label', 'Ссылка:'), ENT_QUOTES, 'UTF-8') ?></strong> <a href="index.php?route=dashboard" data-i18n="docs.dashboard_link"><?= htmlspecialchars($t('docs.dashboard_link', '/web/index.php?route=dashboard'), ENT_QUOTES, 'UTF-8') ?></a></p>
</div></section></div>

<!-- ==================== 2. Задачи ==================== -->
<div class="col-12" id="docs-tasks">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.tasks_title"><?= htmlspecialchars($t('docs.tasks_title', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.tasks_note"><?= htmlspecialchars($t('docs.tasks_note', 'Создание, редактирование, иерархия, массовые действия.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<h3 class="h6" data-i18n="docs.tasks_list_heading"><?= htmlspecialchars($t('docs.tasks_list_heading', 'Список задач'), ENT_QUOTES, 'UTF-8') ?></h3>
<p data-i18n="docs.tasks_list_intro"><?= htmlspecialchars($t('docs.tasks_list_intro', 'Перейти:'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=tasks" data-i18n="docs.tasks_list_link"><?= htmlspecialchars($t('docs.tasks_list_link', 'Задачи'), ENT_QUOTES, 'UTF-8') ?></a>. <span data-i18n="docs.tasks_list_text"><?= htmlspecialchars($t('docs.tasks_list_text', 'Отображает все задачи, доступные пользователю.'), ENT_QUOTES, 'UTF-8') ?></span></p>
<ul>
<li data-i18n="docs.tasks_filter_item"><?= htmlspecialchars($t('docs.tasks_filter_item', 'Фильтры: по проекту, статусу, приоритету, ответственному, сроку.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_sort_item"><?= htmlspecialchars($t('docs.tasks_sort_item', 'Сортировка: по дате создания, сроку, приоритету, названию.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_bulk_item"><?= htmlspecialchars($t('docs.tasks_bulk_item', 'Массовые действия: отметка выполненных, смена статуса, назначение ответственного.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_view_item"><?= htmlspecialchars($t('docs.tasks_view_item', 'Представления: список, Kanban-доска, Гант.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>

<h3 class="h6" data-i18n="docs.tasks_card_heading"><?= htmlspecialchars($t('docs.tasks_card_heading', 'Карточка задачи'), ENT_QUOTES, 'UTF-8') ?></h3>
<p data-i18n="docs.tasks_card_text"><?= htmlspecialchars($t('docs.tasks_card_text', 'При клике на задачу открывается карточка. Содержит:'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.tasks_card_item1"><?= htmlspecialchars($t('docs.tasks_card_item1', 'Название, описание, статус, приоритет, срок, ответственный, проект.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_card_item2"><?= htmlspecialchars($t('docs.tasks_card_item2', 'Комментарии с файлами и @упоминаниями.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_card_item3"><?= htmlspecialchars($t('docs.tasks_card_item3', 'Чеклисты (подзадачи внутри задачи).'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_card_item4"><?= htmlspecialchars($t('docs.tasks_card_item4', 'История изменений.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_card_item5"><?= htmlspecialchars($t('docs.tasks_card_item5', 'AI-инструменты: сводка задачи, улучшение описания, следующие действия, чеклист.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>

<h3 class="h6" data-i18n="docs.tasks_hierarchy_heading"><?= htmlspecialchars($t('docs.tasks_hierarchy_heading', 'Иерархия задач'), ENT_QUOTES, 'UTF-8') ?></h3>
<p data-i18n="docs.tasks_hierarchy_text"><?= htmlspecialchars($t('docs.tasks_hierarchy_text', 'Задачи могут быть вложенными: родительская задача — дочерние (подзадачи).'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.tasks_hierarchy_item1"><?= htmlspecialchars($t('docs.tasks_hierarchy_item1', 'Создание подзадачи: на странице карточки задачи кнопка «Создать подзадачу» через API.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_hierarchy_item2"><?= htmlspecialchars($t('docs.tasks_hierarchy_item2', 'Навигация: на карточке задачи отображаются родительская задача и список подзадач.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_hierarchy_item3"><?= htmlspecialchars($t('docs.tasks_hierarchy_item3', 'Чеклисты: внутри задачи можно создать чеклист с пунктами.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>

<h3 class="h6" data-i18n="docs.tasks_statuses_heading"><?= htmlspecialchars($t('docs.tasks_statuses_heading', 'Статусы и приоритеты'), ENT_QUOTES, 'UTF-8') ?></h3>
<ul>
<li data-i18n="docs.tasks_statuses_item1"><?= htmlspecialchars($t('docs.tasks_statuses_item1', 'Статусы: new, in_progress, done, cancelled, on_hold, archived (настраиваются в админке).'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_statuses_item2"><?= htmlspecialchars($t('docs.tasks_statuses_item2', 'Приоритеты: low, medium, high (настраиваются в админке).'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.tasks_statuses_item3"><?= htmlspecialchars($t('docs.tasks_statuses_item3', 'WIP-лимиты (опциональный модуль): ограничение количества активных задач.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 3. Подзадачи и чеклисты ==================== -->
<div class="col-12" id="docs-subtasks">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.subtasks_title"><?= htmlspecialchars($t('docs.subtasks_title', 'Подзадачи и чеклисты'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.subtasks_note"><?= htmlspecialchars($t('docs.subtasks_note', 'Детальная разбивка задач на подпункты.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.subtasks_p1"><?= htmlspecialchars($t('docs.subtasks_p1', '<strong>Подзадачи</strong> — полноценные задачи с собственными статусами, приоритетами и сроками, привязанные к родительской задаче.'), ENT_QUOTES, 'UTF-8') ?></p>
<p data-i18n="docs.subtasks_p2"><?= htmlspecialchars($t('docs.subtasks_p2', '<strong>Чеклисты</strong> — простые списки внутри задачи. Каждый пункт чеклиста имеет название и статус (выполнено/нет).'), ENT_QUOTES, 'UTF-8') ?></p>
<p data-i18n="docs.subtasks_diff"><?= htmlspecialchars($t('docs.subtasks_diff', 'Разница: подзадачи имеют полный lifecycle (статус, срок, ответственный), чеклисты — только флаг выполнения.'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 4. Проекты ==================== -->
<div class="col-12" id="docs-projects">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.projects_title"><?= htmlspecialchars($t('docs.projects_title', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.projects_note"><?= htmlspecialchars($t('docs.projects_note', 'Управление проектами, вехи, риски, команда.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.projects_link_text"><?= htmlspecialchars($t('docs.projects_link_text', 'Перейти:'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=projects" data-i18n="docs.projects_link"><?= htmlspecialchars($t('docs.projects_link', 'Проекты'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.projects_item1"><?= htmlspecialchars($t('docs.projects_item1', '<strong>Список проектов</strong> — все проекты с фильтром по статусу, ответственному, дате.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.projects_item2"><?= htmlspecialchars($t('docs.projects_item2', '<strong>Карточка проекта</strong> — содержит вкладки: задачи, Канбан, Гант, участники, риски, настройки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.projects_item3"><?= htmlspecialchars($t('docs.projects_item3', '<strong>Вехи (milestones)</strong> — ключевые даты проекта, отображаются на Ганте.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.projects_item4"><?= htmlspecialchars($t('docs.projects_item4', '<strong>Риски проекта</strong> — список рисков с оценкой вероятности и влияния.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.projects_item5"><?= htmlspecialchars($t('docs.projects_item5', '<strong>Шаблоны проектов</strong> — повторяющиеся проекты можно создавать из шаблонов.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<p data-i18n="docs.projects_statuses"><?= htmlspecialchars($t('docs.projects_statuses', 'Статусы проекта: planning, active, on_hold, completed, cancelled.'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 5. Канбан ==================== -->
<div class="col-12" id="docs-kanban">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.kanban_title"><?= htmlspecialchars($t('docs.kanban_title', 'Канбан'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.kanban_note"><?= htmlspecialchars($t('docs.kanban_note', 'Доска для управления потоком задач.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.kanban_intro"><?= htmlspecialchars($t('docs.kanban_intro', 'Канбан-доска отображает задачи в колонках по статусам. Поддерживает drag-and-drop для перемещения задач между статусами.'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.kanban_item1"><?= htmlspecialchars($t('docs.kanban_item1', 'Колонки: статусы new, in_progress, done, on_hold, cancelled (настраиваются).'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.kanban_item2"><?= htmlspecialchars($t('docs.kanban_item2', 'WIP-лимиты (опционально) ограничивают количество задач в колонке.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.kanban_item3"><?= htmlspecialchars($t('docs.kanban_item3', 'Фильтры: по проекту, ответственному, приоритету, тегам.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.kanban_item4"><?= htmlspecialchars($t('docs.kanban_item4', 'Массовые действия: перемещение группы задач.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<p data-i18n="docs.kanban_note2"><?= htmlspecialchars($t('docs.kanban_note2', 'Доступен из карточки проекта (вкладка Kanban).'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 6. Гант ==================== -->
<div class="col-12" id="docs-gantt">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.gantt_title"><?= htmlspecialchars($t('docs.gantt_title', 'Диаграмма Ганта'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.gantt_note"><?= htmlspecialchars($t('docs.gantt_note', 'Временная шкала проекта с зависимостями.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.gantt_intro"><?= htmlspecialchars($t('docs.gantt_intro', 'Гант отображает задачи проекта на временной шкале. Позволяет планировать сроки, отслеживать зависимости и перегрузку ресурсов.'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.gantt_item1"><?= htmlspecialchars($t('docs.gantt_item1', 'Каждая задача отображается как полоса на временной шкале.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.gantt_item2"><?= htmlspecialchars($t('docs.gantt_item2', 'Зависимости: задача B зависит от задачи A — на Ганте это отображается связью.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.gantt_item3"><?= htmlspecialchars($t('docs.gantt_item3', 'Критический путь: последовательность задач, определяющая минимальную длительность проекта.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 7. Клиенты и контрагенты ==================== -->
<div class="col-12" id="docs-clients">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.clients_title"><?= htmlspecialchars($t('docs.clients_title', 'Клиенты и контрагенты'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.clients_note"><?= htmlspecialchars($t('docs.clients_note', 'CRM: управление клиентами, компаниями и контактами.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<ul>
<li data-i18n="docs.clients_item1"><?= htmlspecialchars($t('docs.clients_item1', '<strong>Клиенты</strong> — физические и юридические лица. Карточка клиента содержит контакты, проекты, историю коммуникаций.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.clients_item2"><?= htmlspecialchars($t('docs.clients_item2', '<strong>Контрагенты</strong> — компании, контакты, организации. Используются для B2B-отношений.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.clients_item3"><?= htmlspecialchars($t('docs.clients_item3', '<strong>Контакты</strong> — отдельные люди внутри компаний. Могут быть привязаны к задачам и проектам.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.clients_item4"><?= htmlspecialchars($t('docs.clients_item4', '<strong>Компании</strong> — юридические лица с реквизитами (ИНН, КПП, банк, адрес).'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.clients_item5"><?= htmlspecialchars($t('docs.clients_item5', '<strong>Настраиваемые поля</strong> — для адаптации CRM под отрасль бизнеса.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<p data-i18n="docs.clients_link_text"><?= htmlspecialchars($t('docs.clients_link_text', 'Перейти:'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=clients" data-i18n="docs.clients_link"><?= htmlspecialchars($t('docs.clients_link', 'Клиенты'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
</div></section></div>

<!-- ==================== 8. Календарь ==================== -->
<div class="col-12" id="docs-calendar">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.calendar_title"><?= htmlspecialchars($t('docs.calendar_title', 'Календарь'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.calendar_note"><?= htmlspecialchars($t('docs.calendar_note', 'События, задачи, планы на день/неделю.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.calendar_link_text"><?= htmlspecialchars($t('docs.calendar_link_text', 'Перейти:'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=calendar" data-i18n="docs.calendar_link"><?= htmlspecialchars($t('docs.calendar_link', 'Календарь'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.calendar_item1"><?= htmlspecialchars($t('docs.calendar_item1', 'Создание событий с указанием даты, времени, описания.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.calendar_item2"><?= htmlspecialchars($t('docs.calendar_item2', 'Привязка событий к задачам и проектам.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.calendar_item3"><?= htmlspecialchars($t('docs.calendar_item3', 'Режимы: день, неделя, месяц.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.calendar_item4"><?= htmlspecialchars($t('docs.calendar_item4', 'Повестка дня (agenda) — список событий и задач на выбранную дату.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.calendar_item5"><?= htmlspecialchars($t('docs.calendar_item5', 'Настройка бизнес-часов для расчёта SLA.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.calendar_item6"><?= htmlspecialchars($t('docs.calendar_item6', 'AI-повестка: автоматическая подготовка повестки дня через AI.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 9. Чат ==================== -->
<div class="col-12" id="docs-chats">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.chats_title"><?= htmlspecialchars($t('docs.chats_title', 'Командный чат'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.chats_note"><?= htmlspecialchars($t('docs.chats_note', 'Встроенный мессенджер внутри CRM.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.chats_link_text"><?= htmlspecialchars($t('docs.chats_link_text', 'Перейти:'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=chat" data-i18n="docs.chats_link"><?= htmlspecialchars($t('docs.chats_link', 'Чат'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.chats_item1"><?= htmlspecialchars($t('docs.chats_item1', 'Двухпанельный интерфейс: список чатов слева, переписка справа.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.chats_item2"><?= htmlspecialchars($t('docs.chats_item2', 'Типы чатов: проектные, общие, личные, групповые.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.chats_item3"><?= htmlspecialchars($t('docs.chats_item3', 'Сообщения с вложениями (файлы, изображения) и @упоминаниями.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.chats_item4"><?= htmlspecialchars($t('docs.chats_item4', 'URL-роутинг: каждый чат имеет собственный URL для прямой ссылки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.chats_item5"><?= htmlspecialchars($t('docs.chats_item5', 'Автовосстановление: система запоминает последний активный чат.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.chats_item6"><?= htmlspecialchars($t('docs.chats_item6', 'Живое обновление: новые сообщения появляются без перезагрузки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.chats_item7"><?= htmlspecialchars($t('docs.chats_item7', 'Отправка: Enter отправляет, Shift+Enter — новая строка.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 10. Планирование ==================== -->
<div class="col-12" id="docs-planning">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.planning_title"><?= htmlspecialchars($t('docs.planning_title', 'Планирование — Мой день и Моя неделя'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.planning_note"><?= htmlspecialchars($t('docs.planning_note', 'Персональные планы с AI-помощью.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.planning_my_day"><?= htmlspecialchars($t('docs.planning_my_day', '<strong>Мой день</strong> — список задач, запланированных на сегодня. AI-план на день расставляет приоритеты на основе дедлайнов и загрузки.'), ENT_QUOTES, 'UTF-8') ?></p>
<p data-i18n="docs.planning_my_week"><?= htmlspecialchars($t('docs.planning_my_week', '<strong>Моя неделя</strong> — обзор задач на неделю с календарём и AI-предложениями по фокус-блокам времени.'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.planning_item1"><?= htmlspecialchars($t('docs.planning_item1', 'AI-план на день: генерируется автоматически, учитывает сроки и приоритеты.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.planning_item2"><?= htmlspecialchars($t('docs.planning_item2', 'AI-план на неделю: предлагает события и фокус-блоки с учётом загрузки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.planning_item3"><?= htmlspecialchars($t('docs.planning_item3', 'AI-планы всегда preview-only — применяются только после подтверждения.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 11. Идеи и AI-проработка ==================== -->
<div class="col-12" id="docs-ideas">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.ideas_title"><?= htmlspecialchars($t('docs.ideas_title', 'Идеи и AI-проработка'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.ideas_note"><?= htmlspecialchars($t('docs.ideas_note', 'Полный цикл: от сырой идеи до плана задач.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.ideas_link_text"><?= htmlspecialchars($t('docs.ideas_link_text', 'Перейти:'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=ideas" data-i18n="docs.ideas_link"><?= htmlspecialchars($t('docs.ideas_link', 'Идеи'), ENT_QUOTES, 'UTF-8') ?></a>. <span data-i18n="docs.ideas_intro"><?= htmlspecialchars($t('docs.ideas_intro', 'Модуль для работы с новыми идеями, проектами и предложениями.'), ENT_QUOTES, 'UTF-8') ?></span></p>

<h3 class="h6" data-i18n="docs.ideas_lifecycle_heading"><?= htmlspecialchars($t('docs.ideas_lifecycle_heading', 'Жизненный цикл идеи'), ENT_QUOTES, 'UTF-8') ?></h3>
<ol>
<li data-i18n="docs.ideas_step1"><?= htmlspecialchars($t('docs.ideas_step1', '<strong>Создание идеи</strong> — заголовок, краткое описание, категория, регион, целевая дата.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step2"><?= htmlspecialchars($t('docs.ideas_step2', '<strong>AI-интервью</strong> — система задаёт вопросы для сбора контекста.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step3"><?= htmlspecialchars($t('docs.ideas_step3', '<strong>Карточка понимания</strong> — AI обобщает собранную информацию.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step4"><?= htmlspecialchars($t('docs.ideas_step4', '<strong>Дополнительные уточнения</strong> — AI выявляет вопросы, которые остались неосвещёнными.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step5"><?= htmlspecialchars($t('docs.ideas_step5', '<strong>Каких данных не хватает</strong> — анализ пробелов в информации.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step6"><?= htmlspecialchars($t('docs.ideas_step6', '<strong>Уточнённая карточка</strong> — финальная версия карточки с учётом всех данных.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step7"><?= htmlspecialchars($t('docs.ideas_step7', '<strong>Потенциал идеи</strong> — оценка реализуемости, масштаба и влияния.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step8"><?= htmlspecialchars($t('docs.ideas_step8', '<strong>Риски и подводные камни</strong> — анализ возможных проблем.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step9"><?= htmlspecialchars($t('docs.ideas_step9', '<strong>План реализации</strong> — пошаговый план с этапами и задачами.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step10"><?= htmlspecialchars($t('docs.ideas_step10', '<strong>Итоговая рекомендация</strong> — сводка и вердикт AI.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ideas_step11"><?= htmlspecialchars($t('docs.ideas_step11', '<strong>Предлагаемые задачи</strong> — автоматически сгенерированный список задач для превращения идеи в проект.'), ENT_QUOTES, 'UTF-8') ?></li>
</ol>
<p data-i18n="docs.ideas_restart_note"><?= htmlspecialchars($t('docs.ideas_restart_note', 'Каждый шаг можно перезапустить. AI-результаты всегда preview-only до подтверждения.'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 12. AI-инструменты ==================== -->
<div class="col-12" id="docs-ai">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.ai_title"><?= htmlspecialchars($t('docs.ai_title', 'AI-инструменты'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.ai_note"><?= htmlspecialchars($t('docs.ai_note', '20+ AI-сценариев для ускорения работы.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.ai_intro"><?= htmlspecialchars($t('docs.ai_intro', 'AI-инструменты подключаются через админку: провайдеры (OpenAI, DeepSeek, Anthropic, Google), модели, intent-настройки.'), ENT_QUOTES, 'UTF-8') ?></p>

<h3 class="h6" data-i18n="docs.ai_full_list_heading"><?= htmlspecialchars($t('docs.ai_full_list_heading', 'Полный список AI-инструментов'), ENT_QUOTES, 'UTF-8') ?></h3>
<div class="row g-2">
<div class="col-md-6"><ul>
<li data-i18n="docs.ai_tool1"><?= htmlspecialchars($t('docs.ai_tool1', 'AI-проработка идей (полный цикл)'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool2"><?= htmlspecialchars($t('docs.ai_tool2', 'Декомпозиция задач на подзадачи'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool3"><?= htmlspecialchars($t('docs.ai_tool3', 'План на день'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool4"><?= htmlspecialchars($t('docs.ai_tool4', 'План на неделю'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool5"><?= htmlspecialchars($t('docs.ai_tool5', 'Сводка задачи'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool6"><?= htmlspecialchars($t('docs.ai_tool6', 'Следующие действия'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool7"><?= htmlspecialchars($t('docs.ai_tool7', 'Генерация чеклистов'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool8"><?= htmlspecialchars($t('docs.ai_tool8', 'Проверка качества задачи'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool9"><?= htmlspecialchars($t('docs.ai_tool9', 'Черновик комментария'), ENT_QUOTES, 'UTF-8') ?></li>
</ul></div>
<div class="col-md-6"><ul>
<li data-i18n="docs.ai_tool10"><?= htmlspecialchars($t('docs.ai_tool10', 'Приоритизация задач'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool11"><?= htmlspecialchars($t('docs.ai_tool11', 'Сводка проекта'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool12"><?= htmlspecialchars($t('docs.ai_tool12', 'Сводка рисков проекта'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool13"><?= htmlspecialchars($t('docs.ai_tool13', 'Клиентский отчёт'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool14"><?= htmlspecialchars($t('docs.ai_tool14', 'Сводка клиента'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool15"><?= htmlspecialchars($t('docs.ai_tool15', 'Подготовка к встрече'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool16"><?= htmlspecialchars($t('docs.ai_tool16', 'Проверка данных клиента'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool17"><?= htmlspecialchars($t('docs.ai_tool17', 'Объяснение KPI'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool18"><?= htmlspecialchars($t('docs.ai_tool18', 'Повестка календаря'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_tool19"><?= htmlspecialchars($t('docs.ai_tool19', 'Ежедневный дайджест'), ENT_QUOTES, 'UTF-8') ?></li>
</ul></div>
</div>

<h3 class="h6 mt-3" data-i18n="docs.ai_settings_heading"><?= htmlspecialchars($t('docs.ai_settings_heading', 'Настройка AI'), ENT_QUOTES, 'UTF-8') ?></h3>
<p data-i18n="docs.ai_settings_intro"><?= htmlspecialchars($t('docs.ai_settings_intro', 'Администрирование AI —'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=admin-ai" data-i18n="docs.ai_settings_link"><?= htmlspecialchars($t('docs.ai_settings_link', 'AI-помощник'), ENT_QUOTES, 'UTF-8') ?></a>:</p>
<ul>
<li data-i18n="docs.ai_settings_item1"><?= htmlspecialchars($t('docs.ai_settings_item1', 'Провайдеры: подключение API-ключей OpenAI, DeepSeek, Anthropic, Google.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_settings_item2"><?= htmlspecialchars($t('docs.ai_settings_item2', 'Intent-настройки: для каждого AI-сценария можно выбрать провайдера, модель, ограничить токены, задать права доступа.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_settings_item3"><?= htmlspecialchars($t('docs.ai_settings_item3', 'Feature-флаги: каждый AI-сценарий можно включить/отключить.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_settings_item4"><?= htmlspecialchars($t('docs.ai_settings_item4', 'Retention: политика хранения AI-логов и результатов.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.ai_settings_item5"><?= htmlspecialchars($t('docs.ai_settings_item5', 'Usage-лимиты: ограничение количества запросов.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<p data-i18n="docs.ai_security_note"><?= htmlspecialchars($t('docs.ai_security_note', 'AI-запросы идут через бэкенд — ключи провайдеров никогда не попадают в браузер.'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 13. Аналитика ==================== -->
<div class="col-12" id="docs-analytics">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.analytics_title"><?= htmlspecialchars($t('docs.analytics_title', 'Аналитика'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.analytics_note"><?= htmlspecialchars($t('docs.analytics_note', 'Дашборды, KPI, загрузка команды.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.analytics_intro"><?= htmlspecialchars($t('docs.analytics_intro', 'Аналитические страницы отображают данные на основе реального исполнения задач и проектов:'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.analytics_item1"><?= htmlspecialchars($t('docs.analytics_item1', 'Дашборды: обзорные панели с KPI.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.analytics_item2"><?= htmlspecialchars($t('docs.analytics_item2', 'Аналитика проектов: прогресс, бюджет, сроки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.analytics_item3"><?= htmlspecialchars($t('docs.analytics_item3', 'Аналитика задач: распределение по статусам, приоритетам, исполнителям.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.analytics_item4"><?= htmlspecialchars($t('docs.analytics_item4', 'Загрузка команды: сколько задач у каждого члена команды, перегрузка.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.analytics_item5"><?= htmlspecialchars($t('docs.analytics_item5', 'Сигналы рисков: просроченные задачи, задачи без ответственного, нарушенные SLA.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<p data-i18n="docs.analytics_realtime"><?= htmlspecialchars($t('docs.analytics_realtime', 'Данные обновляются в реальном времени через API.'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 14. Автоматизация ==================== -->
<div class="col-12" id="docs-automation">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.automation_title"><?= htmlspecialchars($t('docs.automation_title', 'Автоматизация'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.automation_note"><?= htmlspecialchars($t('docs.automation_note', 'Workflow-правила, SLA, согласования, вебхуки.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.automation_link_text"><?= htmlspecialchars($t('docs.automation_link_text', 'Настройка —'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=admin-workflow" data-i18n="docs.automation_link"><?= htmlspecialchars($t('docs.automation_link', 'Workflow'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.automation_item1"><?= htmlspecialchars($t('docs.automation_item1', '<strong>Workflow-правила</strong> — автоматические действия при наступлении условий.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.automation_item2"><?= htmlspecialchars($t('docs.automation_item2', '<strong>SLA-управление</strong> — определение ожиданий по времени реакции/решения с отслеживанием нарушений.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.automation_item3"><?= htmlspecialchars($t('docs.automation_item3', '<strong>Согласования (approvals)</strong> — многошаговые цепочки утверждения для контролируемых изменений.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.automation_item4"><?= htmlspecialchars($t('docs.automation_item4', '<strong>Фоновые задачи (jobs)</strong> — запланированная обработка: импорт, экспорт, AI-задачи.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 15. SLA ==================== -->
<div class="col-12" id="docs-sla">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.sla_title"><?= htmlspecialchars($t('docs.sla_title', 'SLA и согласования'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.sla_note"><?= htmlspecialchars($t('docs.sla_note', 'Управление уровнем сервиса.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.sla_link_text"><?= htmlspecialchars($t('docs.sla_link_text', 'Настройка —'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=admin-sla" data-i18n="docs.sla_link"><?= htmlspecialchars($t('docs.sla_link', 'SLA'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.sla_item1"><?= htmlspecialchars($t('docs.sla_item1', 'SLA-политики задают целевое время реакции и решения по типам задач.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.sla_item2"><?= htmlspecialchars($t('docs.sla_item2', 'Согласования — пошаговый процесс утверждения изменений.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.sla_item3"><?= htmlspecialchars($t('docs.sla_item3', 'При нарушении SLA отправляются уведомления ответственному.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 16. Вебхуки ==================== -->
<div class="col-12" id="docs-webhooks">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.webhooks_title"><?= htmlspecialchars($t('docs.webhooks_title', 'Вебхуки'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.webhooks_note"><?= htmlspecialchars($t('docs.webhooks_note', 'Отправка событий во внешние системы.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.webhooks_link_text"><?= htmlspecialchars($t('docs.webhooks_link_text', 'Настройка —'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=admin-webhooks" data-i18n="docs.webhooks_link"><?= htmlspecialchars($t('docs.webhooks_link', 'Вебхуки'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.webhooks_item1"><?= htmlspecialchars($t('docs.webhooks_item1', 'События: создание/обновление/удаление задач, проектов, клиентов.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.webhooks_item2"><?= htmlspecialchars($t('docs.webhooks_item2', 'Формат: POST с JSON-телом события.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.webhooks_item3"><?= htmlspecialchars($t('docs.webhooks_item3', 'Повторные попытки: при ошибке отправки вебхук повторяется с экспоненциальной задержкой.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.webhooks_item4"><?= htmlspecialchars($t('docs.webhooks_item4', 'Логирование: история отправки вебхуков доступна в логах.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 17. API ==================== -->
<div class="col-12" id="docs-api">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.api_title"><?= htmlspecialchars($t('docs.api_title', 'API'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.api_note"><?= htmlspecialchars($t('docs.api_note', 'REST API, 743 эндпоинта, OpenAPI 3.1.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.api_intro"><?= htmlspecialchars($t('docs.api_intro', 'API — программный доступ ко всем функциям CRM. Каждый эндпоинт документирован и доступен через Bearer-токен.'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
<li data-i18n="docs.api_item1"><?= htmlspecialchars($t('docs.api_item1', '<strong>База:</strong> /api/index.php?route=api/v1/...'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.api_item2"><?= htmlspecialchars($t('docs.api_item2', '<strong>Аутентификация:</strong> POST /api/v1/auth/login → Bearer-токен.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.api_item3"><?= htmlspecialchars($t('docs.api_item3', '<strong>Документация OpenAPI:</strong> генерируется из кода, спецификация 3.1.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.api_item4"><?= htmlspecialchars($t('docs.api_item4', '<strong>Эндпоинты:</strong> 743 (695 route records), покрытие UI — 70%.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.api_item5"><?= htmlspecialchars($t('docs.api_item5', '<strong>Идемпотентность:</strong> заголовок X-Idempotency-Key для безопасных повторных запросов.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.api_item6"><?= htmlspecialchars($t('docs.api_item6', '<strong>API-ключи:</strong> создаются в админке для доступа без Bearer-токена.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 18. Роли и права ==================== -->
<div class="col-12" id="docs-roles">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.roles_title"><?= htmlspecialchars($t('docs.roles_title', 'Роли и права доступа'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.roles_note"><?= htmlspecialchars($t('docs.roles_note', 'RBAC — управление доступом на основе ролей.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.roles_link_text"><?= htmlspecialchars($t('docs.roles_link_text', 'Настройка —'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=admin-roles" data-i18n="docs.roles_link"><?= htmlspecialchars($t('docs.roles_link', 'Роли'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.roles_item1"><?= htmlspecialchars($t('docs.roles_item1', '<strong>Права (permissions)</strong> — granular access control для каждой сущности.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.roles_item2"><?= htmlspecialchars($t('docs.roles_item2', '<strong>Роли</strong> — наборы прав, которые назначаются пользователям.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.roles_item3"><?= htmlspecialchars($t('docs.roles_item3', 'Пользователь может иметь несколько ролей.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.roles_item4"><?= htmlspecialchars($t('docs.roles_item4', 'Доступ к разделам UI управляется правами.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.roles_item5"><?= htmlspecialchars($t('docs.roles_item5', 'Доступ к API-эндпоинтам управляется правами.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.roles_item6"><?= htmlspecialchars($t('docs.roles_item6', 'Администратор: имеет все права (super_admin / admin).'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<p data-i18n="docs.roles_builtin"><?= htmlspecialchars($t('docs.roles_builtin', 'Встроенные права: task.manage, project.manage, client.manage, user.manage, team.manage, ai.use, ai.admin, logs.view и другие.'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 19. Администрирование ==================== -->
<div class="col-12" id="docs-admin">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.admin_title"><?= htmlspecialchars($t('docs.admin_title', 'Администрирование'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.admin_note"><?= htmlspecialchars($t('docs.admin_note', 'Управление системой.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.admin_link_text"><?= htmlspecialchars($t('docs.admin_link_text', 'Перейти:'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=admin" data-i18n="docs.admin_link"><?= htmlspecialchars($t('docs.admin_link', 'Админка'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<div class="row g-2">
<div class="col-md-6"><ul>
<li data-i18n="docs.admin_item1"><?= htmlspecialchars($t('docs.admin_item1', '<strong>Пользователи</strong> — создание, блокировка, смена пароля.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item2"><?= htmlspecialchars($t('docs.admin_item2', '<strong>Роли</strong> — управление правами доступа.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item3"><?= htmlspecialchars($t('docs.admin_item3', '<strong>Статусы</strong> — настройка статусов задач.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item4"><?= htmlspecialchars($t('docs.admin_item4', '<strong>Приоритеты</strong> — настройка приоритетов.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item5"><?= htmlspecialchars($t('docs.admin_item5', '<strong>SLA</strong> — SLA-политики.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item6"><?= htmlspecialchars($t('docs.admin_item6', '<strong>Workflow</strong> — workflow-правила.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item7"><?= htmlspecialchars($t('docs.admin_item7', '<strong>Вебхуки</strong> — настройка вебхуков.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item8"><?= htmlspecialchars($t('docs.admin_item8', '<strong>API-клиенты</strong> — управление API-ключами.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul></div>
<div class="col-md-6"><ul>
<li data-i18n="docs.admin_item9"><?= htmlspecialchars($t('docs.admin_item9', '<strong>AI-помощник</strong> — провайдеры, модели, intent-настройки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item10"><?= htmlspecialchars($t('docs.admin_item10', '<strong>Логи</strong> — аудит, actions, ошибки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item11"><?= htmlspecialchars($t('docs.admin_item11', '<strong>Модули</strong> — управление модулями.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item12"><?= htmlspecialchars($t('docs.admin_item12', '<strong>Шаблоны</strong> — шаблоны задач.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item13"><?= htmlspecialchars($t('docs.admin_item13', '<strong>Настройки</strong> — системные настройки.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item14"><?= htmlspecialchars($t('docs.admin_item14', '<strong>Теги</strong> — управление тегами.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item15"><?= htmlspecialchars($t('docs.admin_item15', '<strong>Кастомные поля</strong> — настройка дополнительных полей.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.admin_item16"><?= htmlspecialchars($t('docs.admin_item16', '<strong>Напоминания</strong> — настройка системных напоминаний.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul></div>
</div>
</div></section></div>

<!-- ==================== 20. Модули ==================== -->
<div class="col-12" id="docs-modules">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.modules_title"><?= htmlspecialchars($t('docs.modules_title', 'Модули'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.modules_note"><?= htmlspecialchars($t('docs.modules_note', 'Расширение функциональности без изменения ядра.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<p data-i18n="docs.modules_intro"><?= htmlspecialchars($t('docs.modules_intro', 'Модульная система позволяет добавлять бизнес-логику без модификации ядра CRM. Управление —'), ENT_QUOTES, 'UTF-8') ?> <a href="index.php?route=admin-modules" data-i18n="docs.modules_link"><?= htmlspecialchars($t('docs.modules_link', 'Модули'), ENT_QUOTES, 'UTF-8') ?></a>.</p>
<ul>
<li data-i18n="docs.modules_item1"><?= htmlspecialchars($t('docs.modules_item1', 'Модули — это PHP-пакеты, которые регистрируются в системе.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.modules_item2"><?= htmlspecialchars($t('docs.modules_item2', '19 CLI-команд для управления модулями.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.modules_item3"><?= htmlspecialchars($t('docs.modules_item3', 'Пример модуля: WIP-лимиты (ограничение количества задач в работе).'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.modules_item4"><?= htmlspecialchars($t('docs.modules_item4', 'Модули могут добавлять свои маршруты, сущности и UI-элементы.'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
</div></section></div>

<!-- ==================== 21. Установка ==================== -->
<div class="col-12" id="docs-installation">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.installation_title"><?= htmlspecialchars($t('docs.installation_title', 'Установка'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.installation_note"><?= htmlspecialchars($t('docs.installation_note', 'Браузерный установщик для PHP/MySQL.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<h3 class="h6" data-i18n="docs.installation_requirements_heading"><?= htmlspecialchars($t('docs.installation_requirements_heading', 'Требования'), ENT_QUOTES, 'UTF-8') ?></h3>
<ul>
<li data-i18n="docs.installation_req1"><?= htmlspecialchars($t('docs.installation_req1', 'PHP 8.1 или новее'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_req2"><?= htmlspecialchars($t('docs.installation_req2', 'MySQL (пустая готовая БД)'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_req3"><?= htmlspecialchars($t('docs.installation_req3', 'Веб-сервер с поддержкой PHP (Apache, Nginx)'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_req4"><?= htmlspecialchars($t('docs.installation_req4', 'Права записи для api/ и storage/'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<h3 class="h6" data-i18n="docs.installation_quickstart_heading"><?= htmlspecialchars($t('docs.installation_quickstart_heading', 'Быстрый старт'), ENT_QUOTES, 'UTF-8') ?></h3>
<ol>
<li data-i18n="docs.installation_step1"><?= htmlspecialchars($t('docs.installation_step1', 'Загрузить файлы на хостинг.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_step2"><?= htmlspecialchars($t('docs.installation_step2', 'Создать пустую MySQL-базу.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_step3"><?= htmlspecialchars($t('docs.installation_step3', 'Открыть домен в браузере — установщик запустится автоматически.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_step4"><?= htmlspecialchars($t('docs.installation_step4', 'Указать данные MySQL, URL сайта, часовой пояс, данные администратора.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_step5"><?= htmlspecialchars($t('docs.installation_step5', 'Установщик создаст api/.env, схему БД, справочники, администратора.'), ENT_QUOTES, 'UTF-8') ?></li>
<li data-i18n="docs.installation_step6"><?= htmlspecialchars($t('docs.installation_step6', 'Войти и начать работу.'), ENT_QUOTES, 'UTF-8') ?></li>
</ol>
<h3 class="h6" data-i18n="docs.installation_shared_heading"><?= htmlspecialchars($t('docs.installation_shared_heading', 'Шаред-хостинг'), ENT_QUOTES, 'UTF-8') ?></h3>
<p data-i18n="docs.installation_shared_text"><?= htmlspecialchars($t('docs.installation_shared_text', 'Загрузить api/, web/, modules/, index.php → создать MySQL-базу → открыть домен → следовать установщику → готово.'), ENT_QUOTES, 'UTF-8') ?></p>
<p data-i18n="docs.installation_cost"><?= htmlspecialchars($t('docs.installation_cost', 'Стоимость: от $3/мес (200–300 ₽/мес).'), ENT_QUOTES, 'UTF-8') ?></p>
</div></section></div>

<!-- ==================== 22. FAQ ==================== -->
<div class="col-12" id="docs-faq">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0" data-i18n="docs.faq_title"><?= htmlspecialchars($t('docs.faq_title', 'Частые вопросы'), ENT_QUOTES, 'UTF-8') ?></h2><div class="crm-section-note" data-i18n="docs.faq_note"><?= htmlspecialchars($t('docs.faq_note', 'Ответы на основные вопросы.'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
<div class="p-3">
<div class="mb-3">
<p data-i18n="docs.faq_q1"><?= htmlspecialchars($t('docs.faq_q1', '<strong>Что такое TropaTT?</strong><br>Бесплатная open-source self-hosted CRM, таск-менеджер и платформа управления проектами.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q2"><?= htmlspecialchars($t('docs.faq_q2', '<strong>Это CRM или таск-менеджер?</strong><br>И то, и другое. CRM для клиентов и контрагентов, таск-менеджер для задач, плюс управление проектами, чат, календарь и AI.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q3"><?= htmlspecialchars($t('docs.faq_q3', '<strong>Подходит ли фрилансерам?</strong><br>Да. Нет минимального размера команды. Один пользователь получает CRM, задачи, AI-план на день и чат без платы за место.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q4"><?= htmlspecialchars($t('docs.faq_q4', '<strong>Работает на shared-хостинге?</strong><br>Да. Достаточно PHP 8.1+ и MySQL. Браузерный установщик всё настраивает.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q5"><?= htmlspecialchars($t('docs.faq_q5', '<strong>Есть ли лимиты по пользователям и задачам?</strong><br>Нет. Безлимитные пользователи, задачи, проекты, клиенты. Единственное ограничение — мощность сервера.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q6"><?= htmlspecialchars($t('docs.faq_q6', '<strong>Где хранятся данные?</strong><br>100% на вашем сервере. Система никогда не синхронизирует данные в облако.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q7"><?= htmlspecialchars($t('docs.faq_q7', '<strong>Какие AI-инструменты доступны?</strong><br>20+ инструментов: проработка идей, план на день/неделю, сводки, чеклисты, риски, подготовка к встречам.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q8"><?= htmlspecialchars($t('docs.faq_q8', '<strong>Есть ли API?</strong><br>Да, 743 REST API эндпоинта с OpenAPI 3.1 спецификацией, сгенерированной из кода.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p data-i18n="docs.faq_q9"><?= htmlspecialchars($t('docs.faq_q9', '<strong>Можно ли кастомизировать?</strong><br>Да. PHP-код, модули, вебхуки, API, workflow-правила, кастомные поля, роли и права доступа.'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<div class="mb-3">
<p><strong data-i18n="docs.faq_q10"><?= htmlspecialchars($t('docs.faq_q10', 'Кто разработчик?'), ENT_QUOTES, 'UTF-8') ?></strong><br><span data-i18n="docs.faq_q10_answer"><?= htmlspecialchars($t('docs.faq_q10_answer', '<strong>Антон Баринов</strong>, PHP-разработчик.'), ENT_QUOTES, 'UTF-8') ?></span> <a href="https://github.com/Anton-Barinov">GitHub</a>.</p>
</div>
</div></section></div>

</div><!-- /row -->
</main></div></div>
