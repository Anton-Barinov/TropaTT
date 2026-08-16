# crm.wip-limit

Ограничение количества задач в работе (Work In Progress) на пользователя. Живой подсчёт WIP-нагрузки, индивидуальные лимиты и контроль превышения. Эталонный пример модуля TropaTT (`docs/web/05-modules.md`).

## Возможности

- **Живой подсчёт WIP**: текущая нагрузка всегда вычисляется напрямую из таблицы `tasks` (`assignee_user_id` + `status_code`), поэтому счётчик не расходится при создании/удалении/переназначении задач.
- Индивидуальный лимит на пользователя (`crm_wip_limits.max_tasks`); при отсутствии записи используется `default_limit` (по умолчанию `5`).
- Статусы для учёта задаются через `enforce_on_status` (по умолчанию `in_progress`, `review`).
- Контроль превышения: при переходе задачи в «рабочий» статус хук `task.status_changed`/`task.assignee_changed` сверяет нагрузку с лимитом и логирует превышение (`notify_on_exceed`).
- Роли-исключения (`excluded_role_ids`) — лимит не применяется к пользователям этих ролей.
- Сводная таблица «пользователь → лимит → текущая нагрузка» с индикатором перегрузки.
- **Инлайн-редактирование из карточки задачи**: панель WIP в сайдбаре показывает текущую нагрузку исполнителя и позволяет сразу изменить его лимит без перехода на страницу модуля.

## Архитектура

- `manifest.json` — имя `crm.wip-limit`, вендор `crm`, категория `productivity`, версия `1.3.0`.
- `api/Service/WipLimitService.php` — живой движок подсчёта и контроля WIP.
- `api/Hook/WipHook.php` + `api/WipLimitServiceProvider.php` — регистрация хуков на `task.status_changed` / `task.assignee_changed` через ядерный `HookManager` (`boot()`).
- `api/controller/WipApiController.php` — REST-эндпоинты (`/limits`, `/limits/{user_id}`, `/limits-for-task/{task_public_id}`, `/summary`).
- `web/position/TaskSidebarPanel.php` — панель WIP в сайдбаре карточки задачи (позиция `task.detail.sidebar` из `manifest.json`).
- `web/assets/js/wip-task-sidebar.js` — гидратация панели и сохранение лимита инлайн; подключается только на маршруте `task-detail` (`assets.js_routes`).
- `web/` — страница настроек модуля (`WipPageController` + шаблон), CSS/JS.
- Миграции — `api/migrations/` (`001_create_tables.sql` создаёт `crm_wip_limits`; `002_drop_wip_counts.sql` убирает устаревший денормализованный счётчик; откаты рядом).

## Миграции и тестирование

Проверки: `php -l` всех PHP-файлов модуля, `node --check web/assets/js/module-wip-limit.js`, `php upload/api/scripts/module.php check crm.wip-limit`.

## Безопасность

Модуль не хранит секретов: лимиты — настройки уровня инсталляции, доступ к настройкам через права ядра CRM.
