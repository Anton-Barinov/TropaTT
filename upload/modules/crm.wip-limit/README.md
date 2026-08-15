# crm.wip-limit

Ограничение количества задач в работе (Work In Progress) на пользователя, с уведомлениями при превышении. Эталонный пример модуля TropaTT (`docs/web/05-modules.md`).

## Возможности

- Лимит задач в работе на пользователя (`default_limit`), статусы для учёта задаются `enforce_on_status` (по умолчанию `in_progress`, `review`).
- Превышение лимита подсвечивается в UI и (опционально) уведомляет пользователя (`notify_on_exceed`).
- Роли-исключения (`excluded_role_ids`) — лимит не применяется.
- Hook `task.status_changed` (`Module\Crm\WipLimit\Hook\WipHook::onTaskStatusChanged`) пересчитывает загрузку при смене статуса.

## Архитектура

- `manifest.json` — имя `crm.wip-limit`, вендор `crm`, категория `productivity`.
- `api/` — сервис-провайдер `WipLimitServiceProvider`, контроллер `WipApiController`, маршруты, хук.
- `web/` — страница настроек модуля (`WipPageController` + шаблон), CSS/JS.
- Миграции — `api/migrations/001_create_tables.sql` (откат рядом).

## Миграции и тестирование

Проверки: `php -l` всех PHP-файлов модуля, `node --check web/assets/js/module-wip-limit.js`, `php upload/api/scripts/module.php check crm.wip-limit`.

## Безопасность

Модуль не хранит секретов: лимиты — настройки уровня инсталляции, доступ к настройкам через права ядра CRM.
