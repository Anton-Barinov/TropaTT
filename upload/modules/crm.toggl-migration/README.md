# crm.toggl-migration

Модуль односторонней миграции Toggl Track в TropaTT CRM.

## Что переносится

- workspace выбирается явно;
- Toggl clients → counterparties через `service.client`;
- Toggl projects → проекты CRM, включая связь с client;
- Toggl project tasks → задачи CRM, включая completed, parent_id, user_id и tags;
- Toggl workspace tags → глобальные теги CRM;
- workspace users → таблица сопоставлений с активными пользователями CRM;
- Toggl time entries → `work_logs` с исходным user, task, start/stop, округлёнными минутами, описанием, billable и тегами.

Toggl Track не предоставляет полноценную иерархию разделов, комментарии или вложения в том виде, как некоторые project-management системы. Эти сущности намеренно не создаются. Дополнительные поля исходной записи сохраняются в job item payload, а billable/tags — в note worklog.

## API и ограничения

- Core API: `https://api.track.toggl.com/api/v9`.
- Reports API: `POST https://api.track.toggl.com/reports/api/v3/workspace/{workspace_id}/search/time_entries`.
- В текущей версии поддерживается Personal API Token: он передаётся через HTTP Basic Auth как `token:api_token` и шифруется через `APP_SECRET`. OAuth2-поля схемы зарезервированы для совместимости, но OAuth flow намеренно не включён в UI/API этой версии.
- Core-коллекции читаются страницами `page/per_page`; для клиентов при включённых архивах активная и архивная выборки запрашиваются отдельно (`status=active` и `status=archived`), потому что `status=both` не является совместимым значением; Reports API продолжает выдачу по `X-Next-ID` и `X-Next-Row-Number`.
- Диапазон time entries обязателен и разбивается на окна по 31 дню. Однодневное окно отправляется с `end_date` следующего дня согласно требованию Reports API (`end_date > start_date`); повтор на границе не создаёт дубль благодаря уникальному source ID job item.
- 429 и временные ошибки получают retry с backoff, `Retry-After` сохраняется для следующего запроса, между запросами одной connection соблюдается пауза.
- Running entries (`duration < 0` или без `stop`) не превращаются в ложные worklogs: они помечаются skipped с предупреждением. Удалённые Toggl-теги пропускаются, а архивные проекты и задачи включаются одной настройкой.
- Reports API v3 возвращает `seconds` (секунды); Core API v9 использует `duration` (также секунды). Legacy Reports v2 `dur` намеренно не используется. Duration хранится в целых минутах CRM (`round(seconds / 60)`, минимум 1 минута для положительной записи); точные start/stop сохраняются в interval-полях.

## Идемпотентность и rollback

Исходные идентификаторы записываются в `module_toggl_source_mappings` и job items. Для time entry используется реальный Toggl ID; если API его не вернул, строится SHA-256 от workspace/project/task/user/start/description. Повторный import не создаёт дубликаты, а sync обновляет найденный проект, task и worklog. Sync является upsert-синхронизацией выбранного диапазона: удаления в Toggl не удаляют CRM-данные автоматически; архивные изменения попадут в CRM только при включённой загрузке архивных объектов.

Импорт выполняется в порядке client → project → tag → task → time_entry. Job имеет lease, checkpoint и пакетную обработку; падение worker повторяет только незавершённый пакет. Pause/cancel для ещё не взятого worker queued-job завершаются сразу в `paused`/`cancelled`, чтобы job не застрял в переходном статусе. Не сопоставленный пользователь не подменяется оператором: запись времени получает failed/unresolved, чтобы не исказить отчётность. Полный import/sync backend принимает только root-пользователя: штатный `WorklogService` разрешает non-root только собственные записи, а job всегда включает time entries. Non-root может выполнить только `dry_run`.

Rollback удаляет только targets с `created_by_job=1`. Если target используется другим job, он сохраняется и получает состояние `rollback_preserved_shared`; после rollback последнего job его можно удалить безопасно. Worklogs удаляются через штатный `WorklogService`, поэтому сохраняются его проверки доступа и аудит; заранее существующие CRM записи не затрагиваются. Неудачные удаления остаются retryable и попадают в лог. Connection с реально существующими неоткаченными imported targets удалить нельзя: сначала выполните rollback, иначе исходные mappings будут потеряны вместе с возможностью безопасно определить импортированные записи. Проверка jobs/targets и удаление выполняются атомарно под блокировкой connection; параллельное создание job и worker claim в этот момент исключены.

## Установка и UI

Модуль устанавливается штатным module loader и migration runner. В UI нужно создать connection, выбрать workspace, выполнить discovery, выбрать проекты и обязательный диапазон дат, затем поставить job в очередь. `dry_run` загружает граф источника, но не создаёт CRM-сущности.

## Тесты

Офлайн проверки:

```bash
php upload/api/scripts/test_runner.php toggl-unit
```

Опциональный live read-only тест требует отдельную БД и credentials:

```bash
TOGGL_TEST_CONFIRM=1 \
TOGGL_TEST_TOKEN=... \
TOGGL_TEST_DB_DATABASE=crm_toggl_test \
TOGGL_TEST_DB_USER=root \
TOGGL_TEST_DB_PASSWORD= \
APP_SECRET=... \
php upload/api/scripts/test_runner.php toggl
```

Live-тест не создаёт и не удаляет данные в Toggl; CRM connection/job fixtures удаляются после завершения.
