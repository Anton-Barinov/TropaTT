# crm.kaiten-migration

Односторонняя миграция данных из Kaiten в TropaTT CRM для self-hosted/shared-hosting установки.

## Поддерживаемый охват

- Space → проект CRM.
- Board → проектный модуль проекта; если модуль недоступен, доска остаётся в исходном payload проекта.
- Column → статус задачи CRM (с детерминированным кодом).
- Card/Subcard → задача CRM с `parent_task_public_id`, сроками, приоритетом, описанием, исполнителем, тегами и source metadata.
- Tags → теги CRM.
- Comments → комментарии задачи.
- Attachments → локальные файлы CRM только при явной опции; скачиваются только HTTPS URL исходного tenant-host.
- Custom properties и location history → сохраняются в исходном JSON/job report, поскольку generic custom-field и audit-write контракты CRM не используются фиктивно.
- Пользователи → таблица сопоставлений; автоматическое создание сотрудников не выполняется.

## API Kaiten

Подключение принимает tenant URL вида `https://company.kaiten.ru` (также допускается `/api/latest` или `/api/v1`) и Bearer API token. Клиент использует `/api/latest`, offset/limit до 100 для коллекций и `broken_api=false`, чтобы ID пользователей не меняли тип. Для 401/403/404 запрос завершается с безопасной ошибкой; для 429 и временных 5xx используется ограниченный exponential backoff и `Retry-After`.

## Идемпотентность и выполнение

Ключи источника сохраняются в module-owned `source_mappings` и `job_items`. Повторный импорт использует прежние mapping-и; режим `sync` обновляет доступные проекты/задачи. Job выполняется worker-ом с lease, checkpoint после каждой порции и bounded collection limit (10 000). Pause/cancel и retry failed поддерживаются API. Rollback удаляет только сущности, созданные конкретным job, в обратном порядке; переиспользованные проекты не удаляются.

Токены шифруются через `APP_SECRET`; секреты не возвращаются API и не пишутся в лог. Ссылки вложений проходят HTTPS/tenant-host/SSRF-проверку и ограничение размера. Реальные credentials Kaiten в репозиторий, тесты и demo не добавляются.

## Миграции и тестирование

Модульные таблицы создаются из `api/migration/001_create_tables.sql`; откат — `001_create_tables_rollback.sql`. Для проверки: `php -l` всех PHP-файлов модуля, `node --check web/assets/js/kaiten-migration.js`, `php upload/api/scripts/module.php check crm.kaiten-migration`, локальные unit/integration tests при наличии отдельной MySQL.

## Ограничения

Ответы Kaiten могут отличаться между облачным tenant и коробочной установкой; при отсутствии endpoint-а конкретная сущность попадает в warning, а job продолжает остальные данные. Для restricted-access файлов нужен download URL, доступный тому же tenant; viewer URL не используется как бинарный источник. Удалённые объекты не восстанавливаются, а архивы включаются только флагом job. Реальный импорт требует тестового Kaiten tenant и credentials пользователя с доступом ко всем выбранным пространствам.
