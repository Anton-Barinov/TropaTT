# crm.trello-migration

Односторонняя миграция данных из Trello Cloud в TropaTT для self-hosted/shared-hosting установки.

## Поддерживаемый охват

- Board → проект CRM.
- List → статус задачи CRM (режим задаётся `default_list_mode`).
- Card → задача CRM с описанием, сроками, приоритетом, исполнителем, тегами, чек-листами и source-метаданными.
- Comments → комментарии задачи.
- Attachments → локальные файлы CRM при явной опции; скачиваются по HTTPS с проверкой размера и SSRF.
- Архивные карточки включаются опционально (`include_archived_by_default`).
- Пользователи → таблица сопоставлений; автозаведение сотрудников не выполняется.

## API Trello

Подключение принимает API key и token. Для регулярной синхронизации используется cron-опрос (`poll_interval_minutes`); опционально — webhook (`webhook_enabled_by_default`), callback-URL строится из текущего запроса установки. На 401/403/404 — безопасная ошибка, на 429/5xx — ограниченный backoff.

## Идемпотентность и выполнение

Ключи источника сохраняются в module-owned `source_mappings`/`job_items`. Повторный импорт обновляет сущности без дублей. Job выполняется cron-worker'ом (`TrelloWorkerHandler`) с lease и checkpoint; `max_cards_per_job` ограничивает объём одного запуска. Rollback удаляет только сущности конкретного job.

Токены шифруются через `APP_SECRET`; секреты не возвращаются API и не пишутся в лог. Вложения проходят HTTPS/SSRF/размер проверки. Реальные credentials Trello в репозиторий, тесты и demo не добавляются.

## Миграции и тестирование

Таблицы модуля создаются из `api/migration/001_create_tables.sql` (откат — `001_create_tables_rollback.sql`). Проверки: `php -l` всех PHP-файлов модуля, `node --check web/assets/js/trello-migration.js`, `php upload/api/scripts/module.php check crm.trello-migration`.

## Ограничения

Поддерживается Trello Cloud REST API. Удалённые объекты не восстанавливаются. Реальный импорт требует тестовую доску и credentials с доступом к ней.
