<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Справка'; ?>
<body data-page="docs" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content"><div class="crm-page-head"><div><h1 class="crm-page-title">Справка</h1><p class="crm-subtitle">Полное руководство по разделам, функциям и рабочим сценариям TropaTT.</p></div><a class="btn crm-btn-primary" href="index.php?route=dashboard">Открыть главную</a></div>

<div class="row g-3">

<!-- ==================== разделы ==================== -->

<div class="col-12">
<div class="crm-card p-3 mb-3"><h2 class="h5 mb-2">Содержание</h2>
<ol class="mb-0" style="column-count:2;column-gap:2rem">
<li><a href="#docs-dashboard">Главная</a></li>
<li><a href="#docs-tasks">Задачи</a></li>
<li><a href="#docs-subtasks">Подзадачи и чеклисты</a></li>
<li><a href="#docs-projects">Проекты</a></li>
<li><a href="#docs-kanban">Канбан</a></li>
<li><a href="#docs-gantt">Диаграмма Ганта</a></li>
<li><a href="#docs-clients">Клиенты и контрагенты</a></li>
<li><a href="#docs-calendar">Календарь</a></li>
<li><a href="#docs-chats">Чат</a></li>
<li><a href="#docs-planning">Планирование (Мой день / Неделя)</a></li>
<li><a href="#docs-ideas">Идеи и AI-проработка</a></li>
<li><a href="#docs-ai">AI-инструменты</a></li>
<li><a href="#docs-analytics">Аналитика</a></li>
<li><a href="#docs-automation">Автоматизация</a></li>
<li><a href="#docs-sla">SLA и согласования</a></li>
<li><a href="#docs-webhooks">Вебхуки</a></li>
<li><a href="#docs-api">API</a></li>
<li><a href="#docs-roles">Роли и права доступа</a></li>
<li><a href="#docs-admin">Администрирование</a></li>
<li><a href="#docs-modules">Модули</a></li>
<li><a href="#docs-installation">Установка</a></li>
<li><a href="#docs-faq">Частые вопросы</a></li>
</ol>
</div>
</div>

<!-- ==================== 1. Главная ==================== -->
<div class="col-12" id="docs-dashboard">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Главная (Dashboard)</h2><div class="crm-section-note">Обзорный дашборд системы.</div></div></div>
<div class="p-3">
<p>Главная страница открывается сразу после входа. На ней отображаются:</p>
<ul>
<li><strong>Быстрые действия</strong> — кнопки создания проекта, задачи, перехода к списку задач и справочной системе.</li>
<li><strong>Моя сессия</strong> — информация о текущем пользователе, кнопка выхода.</li>
<li><strong>Последние задачи</strong> — список задач, назначенных на текущего пользователя, с фильтрацией по статусу.</li>
</ul>
<p>Дашборд автоматически загружает данные через API; никакой ручной настройки не требуется.</p>
<p><strong>Ссылка:</strong> <a href="index.php?route=dashboard">/web/index.php?route=dashboard</a></p>
</div></section></div>

<!-- ==================== 2. Задачи ==================== -->
<div class="col-12" id="docs-tasks">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Задачи</h2><div class="crm-section-note">Создание, редактирование, иерархия, массовые действия.</div></div></div>
<div class="p-3">
<h3 class="h6">Список задач</h3>
<p>Перейти: <a href="index.php?route=tasks">Задачи</a>. Отображает все задачи, доступные пользователю.</p>
<ul>
<li>Фильтры: по проекту, статусу, приоритету, ответственному, сроку.</li>
<li>Сортировка: по дате создания, сроку, приоритету, названию.</li>
<li>Массовые действия: отметка выполненных, смена статуса, назначение ответственного.</li>
<li>Представления: список, Kanban-доска, Гант.</li>
</ul>

<h3 class="h6">Карточка задачи</h3>
<p>При клике на задачу открывается карточка. Содержит:</p>
<ul>
<li>Название, описание, статус, приоритет, срок (<code>due_at</code>), ответственный, проект.</li>
<li>Комментарии с файлами и @упоминаниями.</li>
<li>Чеклисты (подзадачи внутри задачи).</li>
<li>История изменений.</li>
<li>AI-инструменты: сводка задачи, улучшение описания, следующие действия, чеклист.</li>
</ul>

<h3 class="h6">Иерархия задач</h3>
<p>Задачи могут быть вложенными: родительская задача — дочерние (подзадачи).</p>
<ul>
<li>Создание подзадачи: на странице карточки задачи кнопка «Создать подзадачу» через <code>POST /api/v1/tasks/{id}/subtasks</code>.</li>
<li>Навигация: на карточке задачи отображаются родительская задача и список подзадач.</li>
<li>Чеклисты: внутри задачи можно создать чеклист с пунктами.</li>
</ul>

<h3 class="h6">Статусы и приоритеты</h3>
<ul>
<li>Статусы: new, in_progress, done, cancelled, on_hold, archived (настраиваются в админке).</li>
<li>Приоритеты: low, medium, high (настраиваются в админке).</li>
<li>WIP-лимиты (опциональный модуль): ограничение количества активных задач.</li>
</ul>
</div></section></div>

<!-- ==================== 3. Подзадачи и чеклисты ==================== -->
<div class="col-12" id="docs-subtasks">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Подзадачи и чеклисты</h2><div class="crm-section-note">Детальная разбивка задач на подпункты.</div></div></div>
<div class="p-3">
<p><strong>Подзадачи</strong> — полноценные задачи с собственными статусами, приоритетами и сроками, привязанные к родительской задаче. Создаются через API <code>POST /api/v1/tasks/{public_id}/subtasks</code> с указанием <code>parent_task_public_id</code>.</p>
<p><strong>Чеклисты</strong> — простые списки внутри задачи. Каждый пункт чеклиста имеет название и статус (выполнено/нет). Используются для отслеживания мелких шагов без создания полноценных подзадач.</p>
<p>Разница: подзадачи имеют полный lifecycle (статус, срок, ответственный), чеклисты — только флаг выполнения.</p>
</div></section></div>

<!-- ==================== 4. Проекты ==================== -->
<div class="col-12" id="docs-projects">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Проекты</h2><div class="crm-section-note">Управление проектами, вехи, риски, команда.</div></div></div>
<div class="p-3">
<p>Перейти: <a href="index.php?route=projects">Проекты</a>.</p>
<ul>
<li><strong>Список проектов</strong> — все проекты с фильтром по статусу, ответственному, дате.</li>
<li><strong>Карточка проекта</strong> — содержит вкладки: задачи, Канбан, Гант, участники, риски, настройки.</li>
<li><strong>Вехи (milestones)</strong> — ключевые даты проекта, отображаются на Ганте.</li>
<li><strong>Риски проекта</strong> — список рисков с оценкой вероятности и влияния.</li>
<li><strong>Шаблоны проектов</strong> — повторяющиеся проекты можно создавать из шаблонов.</li>
</ul>
<p>Статусы проекта: planning, active, on_hold, completed, cancelled.</p>
</div></section></div>

<!-- ==================== 5. Канбан ==================== -->
<div class="col-12" id="docs-kanban">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Канбан</h2><div class="crm-section-note">Доска для управления потоком задач.</div></div></div>
<div class="p-3">
<p>Канбан-доска отображает задачи в колонках по статусам. Поддерживает drag-and-drop для перемещения задач между статусами.</p>
<ul>
<li>Колонки: статусы new, in_progress, done, on_hold, cancelled (настраиваются).</li>
<li>WIP-лимиты (опционально) ограничивают количество задач в колонке.</li>
<li>Фильтры: по проекту, ответственному, приоритету, тегам.</li>
<li>Массовые действия: перемещение группы задач.</li>
</ul>
<p>Доступен из карточки проекта (вкладка Kanban).</p>
</div></section></div>

<!-- ==================== 6. Гант ==================== -->
<div class="col-12" id="docs-gantt">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Диаграмма Ганта</h2><div class="crm-section-note">Временная шкала проекта с зависимостями.</div></div></div>
<div class="p-3">
<p>Гант отображает задачи проекта на временной шкале. Позволяет планировать сроки, отслеживать зависимости и перегрузку ресурсов.</p>
<ul>
<li>Каждая задача отображается как полоса на временной шкале (от <code>start_at</code> до <code>end_at</code>).</li>
<li>Зависимости: задача <code>B</code> зависит от задачи <code>A</code> — на Ганте это отображается связью.</li>
<li>Критический путь: последовательность задач, определяющая минимальную длительность проекта.</li>
</ul>
</div></section></div>

<!-- ==================== 7. Клиенты и контрагенты ==================== -->
<div class="col-12" id="docs-clients">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Клиенты и контрагенты</h2><div class="crm-section-note">CRM: управление клиентами, компаниями и контактами.</div></div></div>
<div class="p-3">
<ul>
<li><strong>Клиенты</strong> — физические и юридические лица. Карточка клиента содержит контакты, проекты, историю коммуникаций.</li>
<li><strong>Контрагенты</strong> — компании, контакты, организации. Используются для B2B-отношений.</li>
<li><strong>Контакты</strong> — отдельные люди внутри компаний. Могут быть привязаны к задачам и проектам.</li>
<li><strong>Компании</strong> — юридические лица с реквизитами (ИНН, КПП, банк, адрес).</li>
<li><strong>Настраиваемые поля</strong> — для адаптации CRM под отрасль бизнеса.</li>
</ul>
<p>Перейти: <a href="index.php?route=clients">Клиенты</a>.</p>
</div></section></div>

<!-- ==================== 8. Календарь ==================== -->
<div class="col-12" id="docs-calendar">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Календарь</h2><div class="crm-section-note">События, задачи, планы на день/неделю.</div></div></div>
<div class="p-3">
<p>Перейти: <a href="index.php?route=calendar">Календарь</a>.</p>
<ul>
<li>Создание событий с указанием даты, времени, описания.</li>
<li>Привязка событий к задачам и проектам.</li>
<li>Режимы: день, неделя, месяц.</li>
<li>Повестка дня (agenda) — список событий и задач на выбранную дату.</li>
<li>Настройка бизнес-часов для расчёта SLA.</li>
<li>AI-повестка: автоматическая подготовка повестки дня через AI.</li>
</ul>
</div></section></div>

<!-- ==================== 9. Чат ==================== -->
<div class="col-12" id="docs-chats">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Командный чат</h2><div class="crm-section-note">Встроенный мессенджер внутри CRM.</div></div></div>
<div class="p-3">
<p>Перейти: <a href="index.php?route=chats">Чат</a>.</p>
<ul>
<li>Двухпанельный интерфейс: список чатов слева, переписка справа.</li>
<li>Типы чатов: проектные, общие, личные, групповые.</li>
<li>Сообщения с вложениями (файлы, изображения) и @упоминаниями.</li>
<li>URL-роутинг: каждый чат имеет собственный URL для прямой ссылки.</li>
<li>Автовосстановление: система запоминает последний активный чат.</li>
<li>Живое обновление: новые сообщения появляются без перезагрузки.</li>
<li>Отправка: Enter отправляет, Shift+Enter — новая строка.</li>
</ul>
</div></section></div>

<!-- ==================== 10. Планирование ==================== -->
<div class="col-12" id="docs-planning">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Планирование — Мой день и Моя неделя</h2><div class="crm-section-note">Персональные планы с AI-помощью.</div></div></div>
<div class="p-3">
<p><strong>Мой день</strong> (<a href="index.php?route=my-day">открыть</a>) — список задач, запланированных на сегодня. AI-план на день расставляет приоритеты на основе дедлайнов и загрузки.</p>
<p><strong>Моя неделя</strong> (<a href="index.php?route=my-week">открыть</a>) — обзор задач на неделю с календарём и AI-предложениями по фокус-блокам времени.</p>
<ul>
<li>AI-план на день: генерируется автоматически, учитывает сроки и приоритеты.</li>
<li>AI-план на неделю: предлагает события и фокус-блоки с учётом загрузки.</li>
<li>AI-планы всегда preview-only — применяются только после подтверждения.</li>
</ul>
</div></section></div>

<!-- ==================== 11. Идеи и AI-проработка ==================== -->
<div class="col-12" id="docs-ideas">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Идеи и AI-проработка</h2><div class="crm-section-note">Полный цикл: от сырой идеи до плана задач.</div></div></div>
<div class="p-3">
<p>Перейти: <a href="index.php?route=ideas">Идеи</a>. Модуль для работы с новыми идеями, проектами и предложениями.</p>

<h3 class="h6">Жизненный цикл идеи</h3>
<ol>
<li><strong>Создание идеи</strong> — заголовок, краткое описание, категория (product / business / other), регион, целевая дата.</li>
<li><strong>AI-интервью</strong> — система задаёт вопросы для сбора контекста. Ответы сохраняются и используются для анализа.</li>
<li><strong>Карточка понимания</strong> — AI обобщает собранную информацию.</li>
<li><strong>Дополнительные уточнения</strong> — AI выявляет вопросы, которые остались неосвещёнными.</li>
<li><strong>Каких данных не хватает</strong> — анализ пробелов в информации.</li>
<li><strong>Уточнённая карточка</strong> — финальная версия карточки с учётом всех данных.</li>
<li><strong>Потенциал идеи</strong> — оценка реализуемости, масштаба и влияния.</li>
<li><strong>Риски и подводные камни</strong> — анализ возможных проблем.</li>
<li><strong>План реализации</strong> — пошаговый план с этапами и задачами.</li>
<li><strong>Итоговая рекомендация</strong> — сводка и вердикт AI.</li>
<li><strong>Предлагаемые задачи</strong> — автоматически сгенерированный список задач для превращения идеи в проект.</li>
</ol>
<p>Каждый шаг можно перезапустить. AI-результаты всегда preview-only до подтверждения.</p>
</div></section></div>

<!-- ==================== 12. AI-инструменты ==================== -->
<div class="col-12" id="docs-ai">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">AI-инструменты</h2><div class="crm-section-note">20+ AI-сценариев для ускорения работы.</div></div></div>
<div class="p-3">
<p>AI-инструменты подключаются через админку: провайдеры (OpenAI, DeepSeek, Anthropic, Google), модели, intent-настройки.</p>

<h3 class="h6">Полный список AI-инструментов</h3>
<div class="row g-2">
<div class="col-md-6"><ul>
<li>AI-проработка идей (полный цикл)</li>
<li>Декомпозиция задач на подзадачи</li>
<li>План на день</li>
<li>План на неделю</li>
<li>Сводка задачи</li>
<li>Следующие действия</li>
<li>Генерация чеклистов</li>
<li>Проверка качества задачи</li>
<li>Черновик комментария</li>
</ul></div>
<div class="col-md-6"><ul>
<li>Приоритизация задач</li>
<li>Сводка проекта</li>
<li>Сводка рисков проекта</li>
<li>Клиентский отчёт</li>
<li>Сводка клиента</li>
<li>Подготовка к встрече</li>
<li>Проверка данных клиента</li>
<li>Объяснение KPI</li>
<li>Повестка календаря</li>
<li>Ежедневный дайджест</li>
</ul></div>
</div>

<h3 class="h6 mt-3">Настройка AI</h3>
<p>Администрирование AI — <a href="index.php?route=admin-ai">AI-помощник</a>:</p>
<ul>
<li>Провайдеры: подключение API-ключей OpenAI, DeepSeek, Anthropic, Google.</li>
<li>Intent-настройки: для каждого AI-сценария можно выбрать провайдера, модель, ограничить токены, задать права доступа.</li>
<li>Feature-флаги: каждый AI-сценарий можно включить/отключить.</li>
<li>Retention: политика хранения AI-логов и результатов.</li>
<li>Usage-лимиты: ограничение количества запросов.</li>
</ul>
<p>AI-запросы идут через бэкенд — ключи провайдеров никогда не попадают в браузер.</p>
</div></section></div>

<!-- ==================== 13. Аналитика ==================== -->
<div class="col-12" id="docs-analytics">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Аналитика</h2><div class="crm-section-note">Дашборды, KPI, загрузка команды.</div></div></div>
<div class="p-3">
<p>Аналитические страницы отображают данные на основе реального исполнения задач и проектов:</p>
<ul>
<li>Дашборды: обзорные панели с KPI.</li>
<li>Аналитика проектов: прогресс, бюджет, сроки.</li>
<li>Аналитика задач: распределение по статусам, приоритетам, исполнителям.</li>
<li>Загрузка команды: сколько задач у каждого члена команды, перегрузка.</li>
<li>Сигналы рисков: просроченные задачи, задачи без ответственного, нарушенные SLA.</li>
</ul>
<p>Данные обновляются в реальном времени через API.</p>
</div></section></div>

<!-- ==================== 14. Автоматизация ==================== -->
<div class="col-12" id="docs-automation">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Автоматизация</h2><div class="crm-section-note">Workflow-правила, SLA, согласования, вебхуки.</div></div></div>
<div class="p-3">
<p>Настройка — <a href="index.php?route=admin-workflow">Workflow</a>.</p>
<ul>
<li><strong>Workflow-правила</strong> — автоматические действия при наступлении условий (смена статуса, обновление поля, наступление даты).</li>
<li><strong>SLA-управление</strong> — определение ожиданий по времени реакции/решения с отслеживанием нарушений.</li>
<li><strong>Согласования (approvals)</strong> — многошаговые цепочки утверждения для контролируемых изменений.</li>
<li><strong>Фоновые задачи (jobs)</strong> — запланированная обработка: импорт, экспорт, AI-задачи.</li>
</ul>
</div></section></div>

<!-- ==================== 15. SLA ==================== -->
<div class="col-12" id="docs-sla">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">SLA и согласования</h2><div class="crm-section-note">Управление уровнем сервиса.</div></div></div>
<div class="p-3">
<p>Настройка — <a href="index.php?route=admin-sla">SLA</a>.</p>
<ul>
<li>SLA-политики задают целевое время реакции и решения по типам задач.</li>
<li>Согласования — пошаговый процесс утверждения изменений (например, изменение бюджета проекта требует одобрения руководителя).</li>
<li>При нарушении SLA отправляются уведомления ответственному.</li>
</ul>
</div></section></div>

<!-- ==================== 16. Вебхуки ==================== -->
<div class="col-12" id="docs-webhooks">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Вебхуки</h2><div class="crm-section-note">Отправка событий во внешние системы.</div></div></div>
<div class="p-3">
<p>Настройка — <a href="index.php?route=admin-webhooks">Вебхуки</a>.</p>
<ul>
<li>События: создание/обновление/удаление задач, проектов, клиентов.</li>
<li>Формат: POST с JSON-телом события.</li>
<li>Повторные попытки: при ошибке отправки вебхук повторяется с экспоненциальной задержкой.</li>
<li>Логирование: история отправки вебхуков доступна в логах.</li>
</ul>
</div></section></div>

<!-- ==================== 17. API ==================== -->
<div class="col-12" id="docs-api">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">API</h2><div class="crm-section-note">REST API, 743 эндпоинта, OpenAPI 3.1.</div></div></div>
<div class="p-3">
<p>API — программный доступ ко всем функциям CRM. Каждый эндпоинт документирован и доступен через Bearer-токен.</p>
<ul>
<li><strong>База:</strong> <code>/api/index.php?route=api/v1/...</code></li>
<li><strong>Аутентификация:</strong> <code>POST /api/v1/auth/login</code> → Bearer-токен.</li>
<li><strong>Документация OpenAPI:</strong> генерируется из кода, спецификация 3.1.</li>
<li><strong>Эндпоинты:</strong> 743 (695 route records), покрытие UI — 70% (521/743).</li>
<li><strong>Идемпотентность:</strong> заголовок <code>X-Idempotency-Key</code> для безопасных повторных запросов.</li>
<li><strong>API-ключи:</strong> создаются в админке (<code>admin → API-клиенты</code>) для доступа без Bearer-токена.</li>
</ul>
</div></section></div>

<!-- ==================== 18. Роли и права ==================== -->
<div class="col-12" id="docs-roles">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Роли и права доступа</h2><div class="crm-section-note">RBAC — управление доступом на основе ролей.</div></div></div>
<div class="p-3">
<p>Настройка — <a href="index.php?route=admin-roles">Роли</a>.</p>
<ul>
<li><strong>Права (permissions)</strong> — granular access control для каждой сущности.</li>
<li><strong>Роли</strong> — наборы прав, которые назначаются пользователям.</li>
<li>Пользователь может иметь несколько ролей.</li>
<li>Доступ к разделам UI управляется правами.</li>
<li>Доступ к API-эндпоинтам управляется правами (required_permissions).</li>
<li>Администратор: имеет все права (super_admin / admin).</li>
</ul>
<p>Встроенные права: task.manage, project.manage, client.manage, user.manage, team.manage, ai.use, ai.admin, logs.view и другие.</p>
</div></section></div>

<!-- ==================== 19. Администрирование ==================== -->
<div class="col-12" id="docs-admin">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Администрирование</h2><div class="crm-section-note">Управление системой.</div></div></div>
<div class="p-3">
<p>Перейти: <a href="index.php?route=admin">Админка</a>.</p>
<div class="row g-2">
<div class="col-md-6"><ul>
<li><strong>Пользователи</strong> — создание, блокировка, смена пароля.</li>
<li><strong>Роли</strong> — управление правами доступа.</li>
<li><strong>Статусы</strong> — настройка статусов задач.</li>
<li><strong>Приоритеты</strong> — настройка приоритетов.</li>
<li><strong>SLA</strong> — SLA-политики.</li>
<li><strong>Workflow</strong> — workflow-правила.</li>
<li><strong>Вебхуки</strong> — настройка вебхуков.</li>
<li><strong>API-клиенты</strong> — управление API-ключами.</li>
</ul></div>
<div class="col-md-6"><ul>
<li><strong>AI-помощник</strong> — провайдеры, модели, intent-настройки.</li>
<li><strong>Логи</strong> — аудит, actions, ошибки.</li>
<li><strong>Модули</strong> — управление модулями.</li>
<li><strong>Шаблоны</strong> — шаблоны задач.</li>
<li><strong>Настройки</strong> — системные настройки.</li>
<li><strong>Теги</strong> — управление тегами.</li>
<li><strong>Кастомные поля</strong> — настройка дополнительных полей.</li>
<li><strong>Напоминания</strong> — настройка системных напоминаний.</li>
</ul></div>
</div>
</div></section></div>

<!-- ==================== 20. Модули ==================== -->
<div class="col-12" id="docs-modules">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Модули</h2><div class="crm-section-note">Расширение функциональности без изменения ядра.</div></div></div>
<div class="p-3">
<p>Модульная система позволяет добавлять бизнес-логику без модификации ядра CRM. Управление — <a href="index.php?route=admin-modules">Модули</a>.</p>
<ul>
<li>Модули — это PHP-пакеты, которые регистрируются в системе.</li>
<li>19 CLI-команд для управления модулями.</li>
<li>Пример модуля: WIP-лимиты (ограничение количества задач в работе).</li>
<li>Модули могут добавлять свои маршруты, сущности и UI-элементы.</li>
</ul>
</div></section></div>

<!-- ==================== 21. Установка ==================== -->
<div class="col-12" id="docs-installation">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Установка</h2><div class="crm-section-note">Браузерный установщик для PHP/MySQL.</div></div></div>
<div class="p-3">
<h3 class="h6">Требования</h3>
<ul>
<li>PHP 8.1 или новее</li>
<li>MySQL (пустая готовая БД)</li>
<li>Веб-сервер с поддержкой PHP (Apache, Nginx)</li>
<li>Права записи для <code>api/</code> и <code>storage/</code></li>
</ul>
<h3 class="h6">Быстрый старт</h3>
<ol>
<li>Загрузить файлы на хостинг.</li>
<li>Создать пустую MySQL-базу.</li>
<li>Открыть домен в браузере — установщик запустится автоматически.</li>
<li>Указать данные MySQL, URL сайта, часовой пояс, данные администратора.</li>
<li>Установщик создаст <code>api/.env</code>, схему БД, справочники, администратора.</li>
<li>Войти и начать работу.</li>
</ol>
<h3 class="h6">Шаред-хостинг</h3>
<p>Загрузить <code>api/</code>, <code>web/</code>, <code>modules/</code>, <code>index.php</code> → создать MySQL-базу → открыть домен → следовать установщику → готово.</p>
<p>Стоимость: от $3/мес (200–300 ₽/мес).</p>
</div></section></div>

<!-- ==================== 22. FAQ ==================== -->
<div class="col-12" id="docs-faq">
<section class="crm-card crm-section-card mb-3">
<div class="crm-section-head"><div><h2 class="h5 mb-0">Частые вопросы</h2><div class="crm-section-note">Ответы на основные вопросы.</div></div></div>
<div class="p-3">
<div class="mb-3">
<p><strong>Что такое TropaTT?</strong><br>Бесплатная open-source self-hosted CRM, таск-менеджер и платформа управления проектами. Объединяет клиентов, задачи, проекты, Канбан, Гант, календарь, чат, AI-инструменты и автоматизацию.</p>
</div>
<div class="mb-3">
<p><strong>Это CRM или таск-менеджер?</strong><br>И то, и другое. CRM для клиентов и контрагентов, таск-менеджер для задач, плюс управление проектами, чат, календарь и AI.</p>
</div>
<div class="mb-3">
<p><strong>Подходит ли фрилансерам?</strong><br>Да. Нет минимального размера команды. Один пользователь получает CRM, задачи, AI-план на день и чат без платы за место.</p>
</div>
<div class="mb-3">
<p><strong>Работает на shared-хостинге?</strong><br>Да. Достаточно PHP 8.1+ и MySQL. Браузерный установщик всё настраивает.</p>
</div>
<div class="mb-3">
<p><strong>Есть ли лимиты по пользователям и задачам?</strong><br>Нет. Безлимитные пользователи, задачи, проекты, клиенты. Единственное ограничение — мощность сервера.</p>
</div>
<div class="mb-3">
<p><strong>Где хранятся данные?</strong><br>100% на вашем сервере. Система никогда не синхронизирует данные в облако.</p>
</div>
<div class="mb-3">
<p><strong>Какие AI-инструменты доступны?</strong><br>20+ инструментов: проработка идей, план на день/неделю, сводки, чеклисты, риски, подготовка к встречам. Подключаются провайдеры OpenAI, DeepSeek, Anthropic, Google.</p>
</div>
<div class="mb-3">
<p><strong>Есть ли API?</strong><br>Да, 743 REST API эндпоинта с OpenAPI 3.1 спецификацией, сгенерированной из кода.</p>
</div>
<div class="mb-3">
<p><strong>Можно ли кастомизировать?</strong><br>Да. PHP-код, модули, вебхуки, API, workflow-правила, кастомные поля, роли и права доступа.</p>
</div>
<div class="mb-3">
<p><strong>Кто разработчик?</strong><br><strong>Антон Баринов</strong>, PHP-разработчик. <a href="https://github.com/Anton-Barinov">GitHub</a>.</p>
</div>
</div></section></div>

</div><!-- /row -->
</main></div></div>
