# crm.wip-limit

Ограничение количества задач в работе (Work In Progress) на пользователя, команду и проект. Живой подсчёт WIP-нагрузки, индивидуальные лимиты, контроль превышения и уведомления. Эталонный пример модуля TropaTT (см. [руководство по разработке модулей](../../../MODULE_DEVELOPMENT.md)).

## Возможности

- **Живой подсчёт WIP**: текущая нагрузка всегда вычисляется напрямую из таблицы `tasks` (`assignee_user_id`/`project_id` + `status_code`), поэтому счётчик не расходится при создании/удалении/переназначении задач.
- Индивидуальный лимит на пользователя (`crm_wip_limits.max_tasks`); при отсутствии записи используется `default_limit` (по умолчанию `5`).
- **Лимиты на команду и проект** (`crm_wip_scope_limits`, `scope_type` = `team`/`project`); при отсутствии записи используются `team_default_limit`/`project_default_limit` (по умолчанию `10`).
- Статусы для учёта задаются через `enforce_on_status` (по умолчанию `in_progress`, `review`).
- **Уведомления через событийную систему**: при превышении лимита (пользователь/команда/проект) модуль отправляет in-app уведомление исполнителю и/или менеджеру команды/проекта через ядерный `ModuleNotificationDispatcher` (`notify_on_exceed`, дедупликация 15 минут).
- Роли-исключения (`excluded_role_ids`) — лимит не применяется к пользователям этих ролей.
- Сводные таблицы «пользователь/команда/проект → лимит → текущая нагрузка» с индикатором перегрузки (вкладки на странице модуля).
- **Инлайн-редактирование из карточки задачи**: панель WIP в сайдбаре показывает текущую нагрузку исполнителя и позволяет сразу изменить его лимит без перехода на страницу модуля.

## Архитектура

- `manifest.json` — имя `crm.wip-limit`, вендор `crm`, категория `productivity`, версия `1.4.0`.
- `api/Service/WipLimitService.php` — живой движок подсчёта WIP по пользователям/командам/проектам.
- `api/Service/WipNotifier.php` — уведомления о превышении через ядерный `ModuleNotificationDispatcher`.
- `api/Hook/WipHook.php` + `api/WipLimitServiceProvider.php` — регистрация хуков на `task.status_changed` / `task.assignee_changed` через ядерный `HookManager` (`boot()`).
- `api/controller/WipApiController.php` — REST-эндпоинты (`/limits`, `/limits/{user_id}`, `/limits-for-task/{task_public_id}`, `/summary`, `/scopes/{scope_type}`, `/scopes`).
- `web/position/TaskSidebarPanel.php` — панель WIP в сайдбаре карточки задачи (позиция `task.detail.sidebar` из `manifest.json`).
- `web/assets/js/wip-task-sidebar.js` — гидратация панели и сохранение лимита инлайн; подключается только на маршруте `task-detail` (`assets.js_routes`).
- `web/` — страница настроек модуля (`WipPageController` + шаблон) с вкладками «Пользователи / Команды / Проекты», CSS/JS.
- Миграции — `api/migrations/` (`001_create_tables.sql` создаёт `crm_wip_limits`; `002_drop_wip_counts.sql` убирает устаревший денормализованный счётчик; `003_create_scope_limits.sql` создаёт `crm_wip_scope_limits`; откаты рядом).

## Миграции и тестирование

Проверки: `php -l` всех PHP-файлов модуля, `node --check web/assets/js/module-wip-limit.js`, `php upload/api/scripts/module.php check crm.wip-limit`.

## Безопасность

Модуль не хранит секретов: лимиты — настройки уровня инсталляции, доступ к настройкам через права ядра CRM. Уведомления отправляются только зарегистрированным пользователям CRM (исполнитель, менеджер команды/проекта).
