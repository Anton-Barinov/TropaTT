# crm.clickup-migration

Модуль односторонней миграции ClickUp Cloud в TropaTT. Реализация рассчитана на PHP 8.1+, MySQL/InnoDB и cron-worker без Redis/Composer-зависимостей.

## Поддерживаемый scope

- ClickUp API v2, Personal API Token и OAuth2 authorization code.
- Team → Space → Folder → List → Task/Subtask.
- Статусы задач, приоритеты, сроки, исполнители, теги и custom fields. Для синхронизации поддерживаются фильтры по обновлению и периоду завершения.
- Checklists/checklist items, task comments, task attachments.
- Time entries (только завершённые записи с положительной длительностью) через workspace endpoint по каждому участнику, с 90-дневным разбиением окна; dependencies и optional Goals discovery.
- ClickUp Docs намеренно не импортируются: публичный API не гарантирует сохранение rich-text/wiki-структуры; job отчётливо показывает warning.

## Маппинг

| ClickUp | TropaTT |
|---|---|
| Space | Project |
| Folder/List | Project module |
| Task/Subtask | Task + `parent_task_public_id` |
| Status | безопасный CRM status (`new`, `in_progress`, `blocked`, `done`); исходный status остаётся в source payload |
| Priority/due/assignees | priority, due/start, CRM user mapping |
| Tag | CRM tag + task association |
| Checklist | CRM checklist/checklist item |
| Comment | task comment с исходной датой |
| Attachment | CRM file при официальном HTTPS URL, иначе безопасное предупреждение без скачивания |
| Time entry | CRM worklog при root worker и сопоставленном пользователе |
| Dependency | CRM dependency после создания обеих задач |
| Goal | discovery-only warning, если в CRM нет эквивалентной сущности |
| Team/User | workspace members сохраняются в mapping UI; CRM-пользователи не создаются автоматически, назначения выполняются после сопоставления email/пользователя; при нескольких assignees сохраняется первый mapped с warning |
| Custom field | сохраняется в source payload и кратком описании задачи |

## Идемпотентность и безопасность

- Source ID хранится в `module_clickup_source_mappings` с уникальным ключом `(connection_id, source_type, source_id)`.
- Повторный import не создаёт дубликаты; `sync` обновляет targets по mapping.
- Root-only политика действует для реального import/sync и rollback; `dry_run` можно создать без записи в CRM.
- Time entries загружаются только для выбранного Space и выбранного периода; при пустом списке участников отчёт содержит явное предупреждение о неполной видимости.
- PAT/client secret/access token хранятся только в зашифрованных полях и никогда не возвращаются API или job log.
- Токен передаётся в `Authorization` без query string. HTTP 429 обрабатывается по `Retry-After` с exponential backoff.
- Скачивание вложений ограничено точным allowlist официальных ClickUp-hosts (`attachments.clickup.com`, `files.clickup.com`, `app.clickup.com`), HTTPS/443, публичным DNS-адресом, лимитом размера и максимумом redirect; CloudFront и внешние URL отклоняются намеренно.
- Для объектов без native ID используется стабильный ID job-item, поэтому повторный запуск не дублирует synthetic attachments/checklist items/time entries.
- Комментарии получают сопоставленного автора ClickUp, если mapping доступен; иначе используется root-worker и в отчёте остаётся warning.
- Rollback удаляет только targets, созданные данным job. Общие targets сохраняются; checklist items удаляются до checklist. Пользовательские изменения описания не перезаписываются. Повторный rollback после `rollback_failed`/`rolled_back_with_warnings` начинает сканирование заново и повторяет неудачные элементы.

## Job lifecycle

`draft → queued → running → completed/completed_with_warnings`, с pause/resume/cancel/retry-failed/rollback. Сырые объекты сначала записываются в job items, затем импортируются пакетами. Parent tasks получают более высокий приоритет, чем subtasks; зависимости импортируются последними.

Основные module routes:

- `GET/POST/PATCH/DELETE /connections`, `POST /connections/{id}/test`;
- `GET /connections/{id}/projects` — безопасное discovery teams/spaces;
- `GET/POST /jobs`, lifecycle endpoints и `/items`, `/logs`, `/report`;
- OAuth: `POST /oauth/authorize-url`, `POST /oauth/exchange`.

## Проверки

Без credentials live-запросы не выполняются. Локальные проверки:

```bash
find upload/modules/crm.clickup-migration -name '*.php' -print0 | xargs -0 -n1 php -l
node --check upload/modules/crm.clickup-migration/web/assets/js/clickup-migration.js
php upload/api/scripts/module.php check crm.clickup-migration
php upload/api/scripts/generate_openapi.php
```

Опциональный live smoke-test должен использовать отдельный ClickUp token с read-only доступом и не создавать/изменять объекты ClickUp. Перед production import требуется отдельный тестовый CRM database и проверка rollback.
