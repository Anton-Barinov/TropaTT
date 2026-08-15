# crm.jira-migration

Односторонняя миграция данных из Jira Cloud в TropaTT для self-hosted/shared-hosting установки.

## Поддерживаемый охват

- Проект Jira → проект CRM.
- Issue (задача/эпик/подзадача) → задача CRM с `parent_task_public_id`, сроками, приоритетом, описанием, исполнителем, тегами и source-метаданными.
- Sprint → связь с задачами через mapping.
- Комментарии → комментарии задачи.
- Вложения → локальные файлы CRM при явной опции; скачиваются по HTTPS с проверкой host/SSRF/размера.
- ADF (Atlassian Document Format) → HTML через `JiraAdfRenderer`; ссылки переписываются через `JiraLinkRewriter`.
- Пользователи → таблица сопоставлений (по email/`accountId` через `JiraIdentityResolver`); автозаведение сотрудников не выполняется.

## API Jira

Подключение принимает host вида `https://<site>.atlassian.net` (allowlist `*.atlassian.net`, настраивается `custom_domain_allowlist`) и API token. Задачи выбираются через JQL (`jql_default_max_results`), уважаются `X-RateLimit-*` через `JiraRateLimitGuard`, на 401/403/404 — безопасная ошибка, на 429/5xx — ограниченный backoff с `Retry-After`.

## Идемпотентность и выполнение

Ключи источника сохраняются в module-owned `source_mappings`/`job_items`. Повторный импорт использует прежние mapping-и; режим `sync` обновляет доступные сущности. Job выполняется cron-worker'ом (`JiraWorkerHandler`) с lease и checkpoint; `import_issue_limit` ограничивает объём одного запуска. Rollback удаляет только сущности конкретного job.

Токены шифруются через `APP_SECRET` (aes-256-gcm); секреты не возвращаются API и не пишутся в лог. Вложения проходят HTTPS/host/SSRF/размер проверки. Реальные credentials Jira в репозиторий, тесты и demo не добавляются.

## Миграции и тестирование

Таблицы модуля создаются из `api/migration/001_create_tables.sql` (откат — `001_create_tables_rollback.sql`). Проверки: `php -l` всех PHP-файлов модуля, `node --check web/assets/js/module-jira-migration.js`, `php upload/api/scripts/module.php check crm.jira-migration`.

## Ограничения

Поддерживается Jira Cloud (REST API v3/v2 через `JiraClient`); Server/Data Center требует отдельной проверки. Удалённые объекты не восстанавливаются. Реальный импорт требует тестовый Jira-сайт и API token с доступом к выбранным проектам.
