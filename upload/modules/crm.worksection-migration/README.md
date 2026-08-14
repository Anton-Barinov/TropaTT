# crm.worksection-migration

Односторонняя миграция данных из Worksection в TropaTT CRM для self-hosted/shared-hosting установки.

## Поддерживаемый охват

- Account URL → workspace-ключ job; Worksection — одноаккаунтный сервис, поэтому аккаунт идентифицируется по `account_url` (host).
- Project → проект CRM (с `client_public_id` из project group, если группа имеет client).
- Project group (папка проектов) → компания CRM только при наличии client; папка без client пропускается с предупреждением.
- Task/Subtask → задача CRM с `parent_task_public_id`; завершённые задачи и подзадачи загружаются через `search_tasks` (`status=all`), поскольку обычные list-эндпоинты скрывают завершённые задачи.
- Task relations → зависимости CRM после создания обеих задач.
- Tags → теги CRM.
- Comments → комментарии задачи; автор берётся из mapping (только root-импорт может использовать исторического автора).
- Files → локальные файлы CRM через action `download` (id_file); скачивание ограничено HTTPS, host-ом аккаунта, публичным DNS-адресом и лимитом размера.
- Time records (costs) → worklogs только при root-импорте и сопоставленном пользователе; суммы/финансовые поля не импортируются.
- Пользователи → таблица сопоставлений; автоматическое создание сотрудников не выполняется.

## API Worksection

Подключение принимает account URL вида `https://company.worksection.com` и один из двух способов аутентификации:

- **Административный API key** — endpoint `/api/admin/v2/`, подпись `hash` = md5 отсортированных url-escaped `k=v` пар + ключ (реализована как в официальном SDK).
- **OAuth2 access token** — endpoint `/api/oauth2`, `Authorization: Bearer`.

Запросы отправляются как **POST с параметрами в query string**: форма-encoded тела Worksection отклоняет (`invalid JSON`). Документированный лимит — **1 запрос/секунду**; клиент соблюдает его между запросами и дополнительно обрабатывает `Retry-After`/exponential backoff для 429 и временных 5xx. Ответы коллекций ограничены 10 000 записей; превышение завершается ошибкой `WORKSECTION_COLLECTION_LIMIT_EXCEEDED` вместо тихой потери данных.

Комментарии/файлы берутся из встроенных `extra=files/comments` ответа `search_tasks`, когда они присутствуют, иначе — отдельными эндпоинтами (`get_comments`, `get_files`) только при включённых опциях, чтобы не тратить квоту запросов.

## Идемпотентность и выполнение

Ключи источника сохраняются в module-owned `source_mappings` и `job_items` с уникальным ключом `(connection_id, workspace_gid, source_type, source_id)`. Повторный импорт использует прежние mapping-и; режим `sync` обновляет проекты/задачи. Job выполняется worker-ом с lease, checkpoint после каждой порции и bounded collection limit. Pause/cancel/retry/rollback поддерживаются API; rollback удаляет только сущности, созданные данным job, в обратном порядке.

Ключи шифруются через `APP_SECRET` (AES-256-GCM); секреты не возвращаются API и не пишутся в лог. Ссылки/скачивания вложений проходят HTTPS/host/SSRF-проверку и ограничение размера. Реальные credentials Worksection в репозиторий, тесты и demo не добавляются.

## Миграции и тестирование

Модульные таблицы создаются из `api/migration/001_create_tables.sql`; откат — `001_create_tables_rollback.sql`. Проверки:

```bash
find upload/modules/crm.worksection-migration -name '*.php' -print0 | xargs -0 -n1 php -l
node --check upload/modules/crm.worksection-migration/web/assets/js/worksection-migration.js
php upload/api/scripts/module.php check crm.worksection-migration
php upload/api/scripts/test_runner.php worksection-unit
# Опциональный MySQL lifecycle test:
WORKSECTION_TEST_CONFIRM=1 WORKSECTION_TEST_DB_DATABASE=crm_test php upload/api/scripts/test_runner.php worksection
php upload/api/scripts/generate_openapi.php
```

## Ограничения

Worksection не документирует фильтр по `updated_at`/`modified_since` для коллекций; инкрементальность достигается идемпотентными source mappings и режимом `sync`. Папки проектов без client не создают компаний. Историческая авторская атрибуция комментариев/учёта времени возможна только при root-импорте с сопоставленными пользователями. Реальный импорт требует тестового аккаунта Worksection и credentials с доступом ко всем выбранным проектам.
