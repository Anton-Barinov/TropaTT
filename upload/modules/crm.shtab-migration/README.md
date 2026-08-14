# crm.shtab-migration

Безопасный импорт **официальных экспортов Shtab.app** в TropaTT.

## Почему нет API-коннектора

На 2026-08-14 Shtab.app не публикует официального REST, OpenAPI или GraphQL API для внешних интеграций. На `https://my.shtab.app/public/docs/` нет стабильного API-контракта, endpoint-ов, схем JSON, токенов, пагинации или rate-limit заголовков. Модуль намеренно не reverse-engineer-ит приватные web-запросы и не хранит логин/пароль Shtab.app.

Источник — экспортированный файл CSV/TXT/XLSX, загруженный оператором. Один connection можно использовать для нескольких файлов: сначала организации/проекты/пользователи, затем задачи, комментарии и прочие сущности.

## Поддерживаемые типы строк

`workspace`, `organization`, `team`, `project`, `tag`, `user`, `task`, `subtask`, `comment`, `contact`, `deal`, `event`, `file`.

Тип выбирается в UI job или берётся из колонки `entity_type`/`type`. Заголовки принимаются на русском и английском (`ID`, `id`, `Идентификатор`, `Название`, `Наименование`, `Описание`, `Проект`, `Исполнитель`, `Срок`, `Комментарий` и т.п.).

## Маппинг

| Shtab export | TropaTT |
|---|---|
| project | project |
| task/subtask | task с `parent_task_public_id` при наличии parent_id |
| tag | CRM tag; task tags attach к задаче |
| user | ручное сопоставление с CRM user; пользователи автоматически не создаются |
| comment | task comment, автор берётся из mapping пользователя |
| contact/deal/event | сохраняются как unresolved warning до отдельного проверенного маппинга |
| file | внешний URL сохраняется в unresolved warning; автоматическое скачивание не выполняется |
| workspace/organization/team | discovery-only warning |

Исходные поля и исходный ID сохраняются в mapping/job payload. Повторная загрузка того же файла не создаёт дубли; изменившийся checksum переводит строку в повторную обработку.

## Безопасность и ограничения

- Shtab credentials не требуются и не принимаются.
- Размер одного файла ограничен 20 MB; файл хранится вне public web root в `upload/storage_api/temp/shtab-migration` (или в `CRM_STORAGE_BASE`). После полностью успешного job файл удаляется, а при `completed_with_warnings` сохраняется для `retry-failed`; при rollback удаляется.
- XLSX читается встроенными `ZipArchive`/`XMLReader` без Composer-зависимости. Для защиты shared-хостинга распакованные XML-части ограничены 16 MiB каждая и 24 MiB суммарно, worksheet обрабатывается построчно с `LIBXML_NONET`, контролем памяти и проверкой malformed XML; если extension отсутствует или экспорт превышает лимит, job завершается понятной ошибкой; CSV остаётся доступным.
- Полный import/sync разрешён только root; `dry_run` доступен для проверки структуры.
- Удаления и архивирование отсутствуют в официальном export-контракте, поэтому CRM-объекты не удаляются автоматически.
- Подзадачи нормализуются в общий тип `task` и импортируются после родителей; это обеспечивает единый mapping и корректную иерархию даже при обратном порядке строк в файле.
- На странице модуля доступно ручное сопоставление импортированных пользователей с активными сотрудниками CRM; те же операции доступны через API.
- Если строка не содержит исходного ID, используется стабильный составной ключ по типу/названию/связям (описание, статус и даты не участвуют). Если один такой ключ встречается с разными данными, строка получает `SOURCE_ID_COLLISION`, не импортируется и не попадает в retry до исправления исходного экспорта; это предотвращает silent overwrite.
- Для проектов используется проверяемый уникальный 10-символьный `task_key_prefix`; коллизии перебираются до создания проекта.
- Финансовые поля и пароли не импортируются; неизвестные поля остаются только в исходном JSON job item. Unsupported и failed сущности дополнительно доступны в `report.unresolved`.
- Импорт выполняется пакетами по 100 элементов с курсором `last_source_cursor`; после тайм-аута или сбоя worker продолжает job с последнего завершённого пакета.
- Retry failed/cancelled jobs атомарно сбрасывает курсор и возвращает job в очередь; failed job без сохранённых items повторно запускает crawl. Прямой запуск failed/cancelled job запрещён, чтобы не обходить этот reset.
- Pause queued job переводится сразу в `paused`, cancel для draft/queued/paused — сразу в `cancelled`; промежуточные статусы используются только для job, который действительно удерживает worker lease. Просроченный lease для `pausing`/`cancelling` также reclaim-ится worker-ом без потери запроса.
- Успешный rollback очищает target из source mapping (`state=rolled_back`), поэтому следующий импорт создаёт новую CRM-цель, а не обращается к удалённой/архивной.

## Проверки

```bash
find upload/modules/crm.shtab-migration -name '*.php' -print0 | xargs -0 -n1 php -l
node --check upload/modules/crm.shtab-migration/web/assets/js/shtab-migration.js
php upload/api/scripts/module.php check crm.shtab-migration
php upload/api/scripts/test_runner.php shtab-unit
# Опциональный MySQL lifecycle test:
SHTAB_TEST_CONFIRM=1 SHTAB_TEST_DB_DATABASE=crm_test php upload/api/scripts/test_runner.php shtab
php upload/api/scripts/generate_openapi.php
```
