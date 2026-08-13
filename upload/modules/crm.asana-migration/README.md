# crm.asana-migration

Модуль односторонней миграции Asana Cloud в TropaTT CRM.

## MVP

- PAT connection test через `GET /users/me`;
- workspace/project discovery;
- фоновые jobs с lease, pause/resume/cancel/retry;
- проекты, разделы как проектные модули, задачи и подзадачи;
- исполнители через явное сопоставление CRM users;
- комментарии, теги и вложения с ограничением размера;
- source mappings, dry-run, progress, logs, report и rollback только объектов текущей миграции.

Пользователи Asana не создаются автоматически. Неразрешённый исполнитель остаётся без исполнителя и отражается в предупреждениях/исходном payload. Custom fields и followers пока сохраняются в `source_payload_json` и не материализуются в core-сущности. Зависимости Asana переносятся в CRM dependencies с типом `FS`. Перед постановкой job в очередь выбранный workspace проверяется через Asana API. Для защиты shared-hosting от незаметного частичного импорта job с более чем 10 000 исходных элементов завершается ошибкой `ASANA_ITEM_LIMIT_EXCEEDED`; такой job нужно ограничить по проектам/задачам или разбить на несколько импортов.

## Безопасность

Токены шифруются через `APP_SECRET`, не возвращаются API и не пишутся в логи. API endpoints требуют module permissions и дополнительно проверяют владельца connection/job. Вложения принимаются только по HTTPS URL с разрешённым Asana host и ограничиваются размером.

## Тест

Без credentials тест ничего не выполняет и завершается как skipped:

```bash
ASANA_TEST_CONFIRM=1 \
ASANA_TEST_TOKEN=... \
ASANA_TEST_DB_DATABASE=crm_asana_test \
ASANA_TEST_DB_USER=root \
ASANA_TEST_DB_PASSWORD= \
APP_SECRET=... \
php upload/api/tests/AsanaMigrationIntegrationTest.php
```

Используйте только отдельную тестовую БД. Тест выполняет только чтение Asana и удаляет временную connection из CRM.
