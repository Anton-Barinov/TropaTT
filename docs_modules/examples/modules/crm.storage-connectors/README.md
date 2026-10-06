# Модуль «Внешние хранилища» (crm.storage-connectors)

Модуль предоставляет возможность интеграции внешних S3-совместимых объектных хранилищ и Nextcloud/WebDAV для файлов задач, проектов и страниц базы знаний.

## Поддерживаемые провайдеры
1. **S3-Compatible Storage**:
   - Реализация стандарта AWS SigV4 на нативном PHP (cURL / hash_hmac).
   - Поддержка произвольных S3 endpoints: MinIO, Selectel, Timeweb Cloud S3, Cloudflare R2, AWS S3.
   - Поддержка создания временных подписанных ссылок (Pre-signed GET URLs, до 300 секунд).
2. **Nextcloud / WebDAV**:
   - Методы `PROPFIND`, `PUT`, `GET`, `DELETE` поверх HTTPS.
   - Поддержка передачи контрольных сумм в заголовках `OC-Checksum: SHA256:...`.

## Безопасность и архитектура
- **Все проверки доступа (RBAC) сохраняются**: скачивание и предпросмотр файлов происходят через штатный `FileService` CRM с проверкой прав на сущность (задачу/проект). Публичный доступ к бакету не требуется.
- **Шифрование ключей**: Access Key, Secret Key и пароли приложений шифруются через KeyGuard (AES-GCM).
- **Изоляция по workspace (Multi-Tenancy)**: настройки каждого клиента/организации хранятся в `crm_storage_configs` с изоляцией по `organization_id`.
- **Локальный fallback**: при сетевом сбое внешнего провайдера файлы временно сохраняются в локальное хранилище CRM (`upload/storage/`), не прерывая работу пользователей.

## REST API
- `GET /api/v1/modules/crm.storage-connectors/preflight` — проверка окружения хостинга.
- `GET /api/v1/modules/crm.storage-connectors/config` — получение текущих настроек хранилища.
- `POST /api/v1/modules/crm.storage-connectors/config` — сохранение настроек и реквизитов.
- `POST /api/v1/modules/crm.storage-connectors/test-connection` — тест соединения с провайдером.
- `POST /api/v1/modules/crm.storage-connectors/migrate-batch` — фоновый перенос файлов пачками.

## MCP Инструменты
- `storage_preflight_check`
- `storage_config_status`
- `storage_test_connection`
