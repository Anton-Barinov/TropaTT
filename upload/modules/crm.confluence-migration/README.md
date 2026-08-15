# crm.confluence-migration

Односторонняя миграция пространств и страниц из Confluence Cloud в базу знаний TropaTT.

## Поддерживаемый охват

- Space → пространство базы знаний CRM.
- Page → страница базы знаний (иерархия parent/child сохраняется).
- Макросы Confluence → HTML через `ConfluenceMacroRenderer`; ссылки переписываются через `ConfluenceLinkRewriter`.
- Вложения → локальные файлы CRM при явной опции (allowlist MIME-типов и deny-список расширений в `config_defaults`).
- Комментарии → комментарии страницы.
- Пользователи → таблица сопоставлений; автозаведение сотрудников не выполняется.

## API Confluence

Подключение принимает host вида `https://<site>.atlassian.net` (allowlist `*.atlassian.net`, настраивается `custom_domain_allowlist`) и API token. Выборка страниц порциями (`default_batch_size`), на 401/403/404 — безопасная ошибка, на 429/5xx — ограниченный backoff.

## Идемпотентность и выполнение

Ключи источника сохраняются в module-owned `source_mappings`/`job_items`. Повторный импорт обновляет страницы без дублей. Job выполняется cron-worker'ом (`ConfluenceWorkerHandler`) с lease и checkpoint; `dry_run_sample_limit` ограничивает пробный прогон. Rollback удаляет только сущности конкретного job.

Токены шифруются через `APP_SECRET`; секреты не возвращаются API и не пишутся в лог. Вложения проходят HTTPS/SSRF/размер/MIME проверки. Реальные credentials Confluence в репозиторий, тесты и demo не добавляются.

## Миграции и тестирование

Таблицы модуля создаются из `api/migration/001_create_tables.sql` и `002_rate_limits.sql` (откаты рядом). Проверки: `php -l` всех PHP-файлов модуля, `node --check web/assets/js/module-confluence-migration.js`, `php upload/api/scripts/module.php check crm.confluence-migration`.

## Ограничения

Поддерживается Confluence Cloud (REST API v2 через `ConfluenceClient`); Server/Data Center требует отдельной проверки. Удалённые объекты не восстанавливаются. Реальный импорт требует тестовое пространство и credentials с доступом к нему.
