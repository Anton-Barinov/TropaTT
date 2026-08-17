# crm.asana-migration

Модуль односторонней миграции Asana Cloud в TropaTT.

## MVP

- PAT connection test через `GET /users/me`;
- workspace/project discovery;
- фоновые jobs с lease, pause/resume/cancel/retry;
- проекты, разделы как проектные модули, задачи и подзадачи;
- исполнители через явное сопоставление CRM users;
- комментарии, теги и вложения с ограничением размера;
- source mappings, dry-run, progress, logs, report и rollback только объектов текущей миграции.

Пользователи Asana не создаются автоматически. Неразрешённый исполнитель остаётся без исполнителя и отражается в предупреждениях/исходном payload. Custom fields и followers пока сохраняются в `source_payload_json` и не материализуются в core-сущности. Зависимости Asana переносятся в CRM dependencies с типом `FS`. Перед постановкой job в очередь выбранный workspace проверяется через Asana API. Исходные элементы импортируются пакетами по 250 записей с checkpoint в `last_source_cursor`, поэтому перезапуск worker не загружает весь job в память и повторяет только незавершённый пакет. Crawler также обрабатывает API-страницы Asana потоково: пользователи, теги, проекты, разделы, задачи, комментарии, вложения и подзадачи не собираются целиком в PHP-массив. Защита от ошибочного/неограниченного ответа Asana сохраняет предел 10 000 элементов на одну коллекцию и возвращает контролируемую ошибку `ASANA_COLLECTION_LIMIT_EXCEEDED`; для действительно больших источников используйте разбиение по проектам. Rollback сохраняет checkpoint только после успешного пакета: при неудачном удалении пакет не помечается пройденным, а job получает warning и может быть повторен без пропуска неудалённых объектов.

## Безопасность

Токены шифруются через `APP_SECRET`, не возвращаются API и не пишутся в логи. API endpoints требуют module permissions и дополнительно проверяют владельца connection/job. Вложения принимаются только по HTTPS URL с разрешённым Asana host и ограничиваются размером.

## Тест

Публично доступные проверки не требуют credentials:

```bash
find upload/modules/crm.asana-migration -name '*.php' -print0 | xargs -0 -n1 php -l
node --check upload/modules/crm.asana-migration/web/assets/js/asana-migration.js
php upload/api/scripts/module.php check crm.asana-migration
php upload/api/scripts/generate_openapi.php
```

Автоматизированные регрессии и live-тест Asana (только чтение, с очисткой временной connection) выполняются в локальном тестовом наборе мейнтейнера и не входят в публичный репозиторий. Используйте только отдельную тестовую БД.
