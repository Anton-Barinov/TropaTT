# crm.activecollab-migration

Модуль односторонней миграции ActiveCollab Cloud в TropaTT.

## Реализовано

- Personal API Token connection test через `GET /projects?limit=1`; API root задаётся явно для ActiveCollab Cloud или self-hosted;
- discovery account scope → companies, users и projects; ActiveCollab не имеет универсальной workspace-сущности, поэтому `account_id` хранится как scope job; для компаний используется документированный `/companies/all` при включении архивов, а для проектов/задач ActiveCollab v1 не предоставляет общего `/all`-маршрута, поэтому архивная галочка сохраняет только архивные флаги объектов, которые реально вернул API;
- фоновые jobs с lease, pause/resume/cancel/retry;
- компании → counterparties, проекты → projects, task lists → project modules, tasks/subtasks → tasks с иерархией;
- исполнители через явное сопоставление CRM users; общие email не маппятся автоматически; исторические авторы комментариев используются только root-импортом, а non-root импортирует комментарий от имени запускающего пользователя;
- comments/discussions → comments, labels/tags → global tags, files → CRM files с ограничением размера и SSRF-проверкой;
- time records → worklogs, dependencies → CRM dependencies, source mappings, dry-run, progress, logs, report и rollback только объектов текущей миграции.

Пользователи ActiveCollab не создаются автоматически. Страница модуля предоставляет ручное сопоставление `activecollab_user_gid` → `users.public_id`; неразрешённый исполнитель остаётся без исполнителя и отражается в предупреждениях/исходном payload. Custom fields и followers пока сохраняются в `source_payload_json` и не материализуются в core-сущности. Зависимости ActiveCollab переносятся в CRM dependencies с типом `FS`. Перед постановкой job в очередь выбранный workspace проверяется через ActiveCollab API. Исходные элементы импортируются пакетами по 250 записей с checkpoint в `last_source_cursor`, поэтому перезапуск worker не загружает весь job в память и повторяет только незавершённый пакет. Каждый элемент записывается в отдельной транзакции вместе с source mapping: это закрывает окно между созданием CRM-объекта и сохранением mapping при сбое worker (для файлов возможен только временный orphan-файл на storage, без дубликата DB-записи). Crawler также обрабатывает API-страницы ActiveCollab потоково: пользователи, теги, проекты, разделы, задачи, комментарии, вложения и подзадачи не собираются целиком в PHP-массив. Защита от ошибочного/неограниченного ответа ActiveCollab сохраняет предел 10 000 элементов на одну коллекцию и возвращает контролируемую ошибку `ACTIVECOLLAB_COLLECTION_LIMIT_EXCEEDED`; для действительно больших источников используйте разбиение по проектам. Rollback сохраняет checkpoint только после успешного пакета: при неудачном удалении пакет не помечается пройденным, а job получает warning и может быть повторен без пропуска неудалённых объектов.

## Безопасность

Токены шифруются через `APP_SECRET`, не возвращаются API и не пишутся в логи. Запросы используют `X-Angie-AuthApiToken`; OAuth flow намеренно не включён: текущий модуль принимает заранее созданный Personal API Token. API endpoints требуют module permissions и дополнительно проверяют владельца connection/job. Вложения принимаются только по HTTPS URL с разрешённым ActiveCollab host/подписанным S3 URL; DNS-адрес фиксируется через `CURLOPT_RESOLVE`, токен не передаётся на редиректы, а размер ограничивается до записи в CRM.

## Тест

Публично доступные проверки не требуют credentials и не делают сетевых запросов:

```bash
find upload/modules/crm.activecollab-migration -name '*.php' -print0 | xargs -0 -n1 php -l
node --check upload/modules/crm.activecollab-migration/web/assets/js/activecollab-migration.js
php upload/api/scripts/module.php check crm.activecollab-migration
php upload/api/scripts/generate_openapi.php
```

Автоматизированные регрессии и live-тест ActiveCollab выполняются в локальном тестовом наборе мейнтейнера и не входят в публичный репозиторий. Для реального API используйте отдельный стенд и отдельную БД. Перед production-импортом рекомендуется выполнить `dry_run`, затем небольшой job с одним проектом и проверить rollback.

### Candidate workspace-scope patch

New connections and jobs are bound to the active CRM workspace. API roots and account identity are immutable after creation; use a new connection to migrate a different ActiveCollab source. For upgrades, migration `003_workspace_scope.sql` backfills only owners with exactly one CRM workspace membership. Ambiguous legacy rows are retained with NULL `organization_id`. A root administrator can inspect them and explicitly assign each connection to the currently selected workspace; the assignment transaction propagates the workspace to unscoped jobs, mappings, items, logs, rate limits and unresolved records and writes an audit event. No workspace is inferred for ambiguous records.

Candidate regression test (SQLite; no credentials or provider calls):

```bash
php tests/workspace_scope_test.php
```

The marketplace package signature must be regenerated during release packaging after any source changes.
