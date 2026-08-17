# crm.todoist-migration

Модуль односторонней миграции Todoist Cloud в TropaTT.

## Техническое задание и границы MVP

### Источники и аутентификация

- Основной транспорт — актуальный Todoist API v1: `https://api.todoist.com/api/v1`. REST API v2 и Sync API v9 не используются: официальный SDK Todoist также указывает API v1 как текущую версию.
- Personal API Token передаётся только в `Authorization: Bearer ...`, хранится зашифрованным через `APP_SECRET` и никогда не возвращается API/логам.
- Personal API Token и OAuth2 поддерживаются одинаково. OAuth exchange выполняется через `POST /oauth/exchange` с `client_id`, `client_secret`, `code`, `redirect_uri`; access/refresh tokens сохраняются только в зашифрованных полях connection. OAuth authorize URL использует `https://todoist.com/oauth/authorize`, token exchange — `https://todoist.com/oauth/access_token`.
- Завершённые задачи не берутся из обычного `/tasks`: при включённой опции используется API v1 `/tasks/completed/by_completion_date` с обязательными `since/until`, постраничной обработкой по `next_cursor` и окнами не более 90 дней.

### Маппинг Todoist → CRM

| Todoist | TropaTT |
|---|---|
| project | `projects`; исходный `id`, `parent_id`, цвет, порядок и archived сохраняются в payload/mapping |
| section | `project_modules`, включая пустые секции и `order` |
| task | `tasks`; `content` → title, `description`, `due` → due_at, `priority` → priority_code, `assignee_id` → mapped CRM user |
| parent_id | subtask relation через `parent_task_public_id` |
| label | CRM `tags`; привязки к задачам |
| `is_completed` / completed history | status `done`; `completed_at` хранится в исходном payload |
| `due.is_recurring`, `due.string` | due_at + полный recurrence payload; безопасные формы `every day/week/month/N` преобразуются в RRULE, остальные сохраняются с warning |
| task comment | CRM comment на импортированную задачу |
| project comment | добавляется в описание импортированного проекта с маркером источника, потому что CRM comments привязаны к task |
| comment.attachment | CRM file на task либо project при включённой загрузке и успешной SSRF/size-проверке |
| project collaborators / assignee_id | ручное сопоставление в `module_todoist_user_mappings`; неизвестный пользователь не назначается молча |

### Порядок и идемпотентность

1. Проверка PAT через `/projects`/`/tasks` и сохранение account metadata.
2. Потоковое discovery: collaborators → labels → projects → sections → active tasks → completed history (опционально) → comments → attachments.
3. Все source entities сначала попадают в `job_items`; импорт идёт batch-ами по 100 записей в порядке project → section → label → task → subtask → comment → attachment. Если mapping родительской задачи отсутствует, подзадача не создаётся ошибочно без иерархии: item помечается failed и может быть безопасно повторён после импорта родителя.
4. `last_source_cursor` хранит либо `{phase:crawl, after_project_id, tasks_total}`, либо `{phase:import, priority, id}` и обновляется после завершённого batch. Падение worker повторяет незавершённый batch, но не загружает весь job в память.
5. `source_mappings` уникальны по connection/type/source_id. Повторный импорт с тем же checksum не создаёт дубль; `sync` обновляет существующую сущность.
6. Ошибки одной сущности становятся `failed`, пишутся в безопасный job log и не раскрывают токены/полные exception messages клиенту.
7. Rollback удаляет только объекты с `created_by_job=1`, а изменения описания существующего проекта восстанавливает из сохранённого before/after snapshot только если описание не менялось после импорта; при пользовательском изменении item получает `rollback_failed` без перезаписи данных. Просроченный rollback lease можно безопасно продолжить.

### Пагинация, rate limits и ошибки

Todoist API v1 возвращает объект с `results`/`items` и `next_cursor`; client поддерживает также совместимые массивы и defensive offset fallback, ограничивает число страниц защитным пределом и обрабатывает collections потоково. HTTP 429 учитывается один раз, использует `Retry-After` и exponential backoff только между попытками; 401/403/404 превращаются в типизированные ошибки.

### Архивы и ограничения

- По умолчанию импортируются активные проекты/tasks. `include_completed` включается явно; `include_archived` сохраняется как совместимая настройка и выдаёт warning, поскольку API v1 listing возвращает активные проекты, а общего списка архивных проектов нет.
- API v1 не является источником полной истории удалённых задач; completed history ограничена API-окнами до 90 дней. Модуль разбивает заданный диапазон на последовательные окна; если даты не заданы, импортируются последние 90 дней с warning.
- Повторяющиеся due rules преобразуются только для однозначных шаблонов (`every day`, `every N days/weeks/months`, `every weekday`); неизвестные natural-language правила сохраняются в `source_payload_json` и дают warning.
- Todoist comments поддерживают attachment metadata; скачивание выключено по умолчанию, ограничено размером, HTTPS, портом 443 и allowlist `files.todoist.com`/Todoist/CloudFront. DNS-адрес фиксируется через cURL `CURLOPT_RESOLVE`, bearer token не передаётся на CloudFront redirect.
- Для shared-hosting рабочий процесс рассчитан на cron/worker с коротким lease, без очередей Redis и без Composer. Discovery обрабатывает по одному проекту за worker-вызов (настраивается `target_options.projects_per_run`), сохраняет `phase=crawl/after_project_id` и продолжает с очередного проекта; `max_tasks` учитывается суммарно по job.

## API module routes

- `POST /oauth/authorize-url` и `POST /oauth/exchange` — OAuth2 flow. Authorization state хранится в session до 10 минут, redirect URI должен быть HTTPS и совпадать на обоих шагах. `GET/POST/PATCH/DELETE /connections` — connections и зашифрованные credentials.
- `POST /connections/{id}/test`, `GET /connections/{id}/projects`, `GET /connections/{id}/user-mappings`, `PATCH .../user-mappings/{mapping_id}`.
- `GET/POST /jobs`, `GET /jobs/{id}`, lifecycle `run/pause/resume/cancel/retry-failed/rollback`.
- `GET /jobs/{id}/items`, `/logs`, `/report`.

Все routes защищены auth, permission middleware и дополнительной owner/object-level проверкой controller.

## Тестирование

Публично доступные проверки не требуют Todoist или MySQL:

```bash
find upload/modules/crm.todoist-migration -name '*.php' -print0 | xargs -0 -n1 php -l
node --check upload/modules/crm.todoist-migration/web/assets/js/todoist-migration.js
php upload/api/scripts/module.php check crm.todoist-migration
php upload/api/scripts/generate_openapi.php
```

Deterministic unit-регрессии и опциональный live-тест чтения Todoist (не пишет в Todoist и очищает тестовую connection) выполняются в локальном тестовом наборе мейнтейнера и не входят в публичный репозиторий. Применяемые проверки: PHP lint, JS syntax, module circular-dependency check, unit suite, opt-in API test, OpenAPI generation и ручной browser smoke test.
