# crm.ecommerce-gateway

Универсальный модуль-шлюз для интернет-магазинов: приём заказов, покупок в один клик,
заявок на обратный звонок, обращений из форм обратной связи и произвольных лид-форм
из любой CMS в TropaTT CRM.

## Состояние

| Этап | Содержание | Статус |
|---|---|---|
| E-COM-01 | Архитектурная спецификация протокола (REST & Webhooks) | готово (документы `docs/web/ecommerce-gateway-tz.md`, `docs/web/ecommerce-gateway-openapi.yaml`) |
| E-COM-02 | Ядро модуля: манифест, миграции БД, аутентификация витрин | готово |
| E-COM-03 | Ingestion API: приём заказов/форм, валидация схем, дедупликация, создание заявок | готово |
| E-COM-04 | Двусторонняя реактивная синхронизация статусов (CRM Events -> CMS Webhooks), Outbox, HMAC подпись, защита от эхо-петель, FSM | готово |
| E-COM-05…15 | UI панели управления витринами, безопасность, i18n, shared-хостинг, QA | запланировано (следующий — E-COM-05, панель управления) |

Проверки: `php -l` по всем файлам модуля, контрактные тесты
`upload/api/tests/unit/ecommerce_gateway_signature_unit.php`,
`ecommerce_gateway_payload_unit.php` (схемы и Markdown-композер),
`ecommerce_gateway_ingest_unit.php` (идемпотентность/контакты, SQLite),
`ecommerce_gateway_status_sync_unit.php` (FSM переходов, эхо-петли, Outbox, HMAC-подпись, retry-политика),
тест декларации миграций `module_migrations_declared_unit.php`,
покрытие маршрутов `api/scripts/api_coverage_check.php`.

## Модель безопасности

Витрина — не пользователь CRM, поэтому пользовательская сессия и Bearer-токен здесь
не применяются. Каждый запрос подписывается секретом витрины (HMAC-SHA256), сам секрет
хранится зашифрованным (`APP_SECRET` → HKDF → AES-256-GCM).

```
X-Store-Key: stk_...
X-TropaTT-Timestamp: 1789137342
X-TropaTT-Nonce: 9f2c8e...            (16–64 символа, [A-Za-z0-9_-])
X-TropaTT-Signature: base64(HMAC-SHA256(secret, canonical))

canonical = METHOD \n request_path \n timestamp \n nonce \n hex(sha256(raw_body))
```

Проверки по порядку: наличие и активность витрины → IP-allowlist → окно времени (±300 c)
→ подпись (constant-time) → уникальность nonce (повтор = replay).

## API модуля

Префикс: `/api/index.php?route=/_module/crm.ecommerce-gateway/v1/`

| Метод | Путь | Доступ | Назначение |
|---|---|---|---|
| `GET` | `/ping` | подпись витрины | Проверка связи и версии протокола |
| `POST` | `/orders` | подпись витрины | Приём заказа |
| `POST` | `/quick-orders` | подпись витрины | Покупка в один клик |
| `POST` | `/callbacks` | подпись витрины | Заявка на обратный звонок |
| `POST` | `/feedback` | подпись витрины | Обращение из формы связи |
| `POST` | `/forms` | подпись витрины | Произвольная форма / лид |
| `GET` | `/stores` | `module.ecommerce-gateway.view` | Реестр витрин |
| `POST` | `/stores` | `…manage` + `…secret_manage` | Создать витрину (секрет возвращается один раз) |
| `GET` | `/stores/{public_id}` | `…view` | Карточка витрины |
| `PATCH` | `/stores/{public_id}` | `…manage` | Изменить витрину |
| `DELETE` | `/stores/{public_id}` | `…manage` | Мягко удалить витрину |
| `POST` | `/stores/{public_id}/rotate-secret` | `…manage` + `…secret_manage` | Ротация секрета с grace-периодом 1 час |

## Схема БД (`api/migrations/`)

`001_create_core_tables.sql`: `ecommerce_stores`, `ecommerce_store_forms`,
`ecommerce_status_mappings`, `ecommerce_idempotency`, `ecommerce_ingest_events`,
`ecommerce_order_sync_log`, `ecommerce_security_log`, `ecommerce_nonces`,
`ecommerce_audit_log`.

`002_idempotency_response.sql`: добавляет в `ecommerce_idempotency` колонки
`response_json` (снимок ответа первого приёма) и `updated_at` — чтобы
идемпотентный повтор вернул **ровно те же** `intake_item_public_id` и
`task_public_id`, что и первая обработка (`§8.2`), не восстанавливая их по
связям задним числом.

`003_create_outbox_tables.sql`: таблица Transactional Outbox `ecommerce_outbox_events` (E-COM-04)
для гарантированной асинхронной доставки исходящих вебхуков (`order.status_changed`) в витрины CMS
с экспоненциальным бэкоффом (`base_delay * 2^retry`) и защитой от эхо-петель (`StatusSyncContext`).

## Приём заявок (E-COM-03)

Пайплайн одного запроса: разбор JSON → валидация схемы → захват ключа
идемпотентности → подбор/создание контакта → создание заявки (`intake_items`,
`source_type = api|webhook`, `external_source = stk_...`, `external_id = external_id`) →
опциональная задача → связывание вероятного дубля → снимок ответа → журнал
`ecommerce_ingest_events`.

- **Идемпотентность** (`§8`): ключ = `X-TropaTT-Idempotency-Key` либо
  `{store_id}:{type}:{external_id}`. Повтор с тем же телом → `200 INGESTION_DUPLICATE` и те же сущности;
  тот же `external_id` с новым телом → `on_duplicate` витрины: `merge` (дополняет заявку),
  `reject` (`409`), `create` (новая заявка, ключ с хешем тела). Ключ, привязанный к другому
  `external_id`, → `409 INGESTION_EXTERNAL_ID_CONFLICT`.
- **Контакты** (`§9.2`): поиск по телефону, затем по email; телефон нормализуется в E.164,
  email — в lowercase. Ненайденный контакт создаётся минимальным (`cnt_...`), без владельца.
- **Деньги** (`§6.1`): только целые минорные единицы; дробное значение →
  `422 INGESTION_AMOUNT_NOT_INTEGER`.
- **Ответ**: конверт CRM с `meta.locale`, `meta.protocol_version`, `meta.server_time`,
  `meta.idempotency_key`; в `data` — `intake_item_public_id`, `task_public_id`,
  `contact_public_id`, `counterparty_public_id`, `duplicate`, `received_at`.
- **Вложения**: inline `content_base64` не декодируется и не сохраняется в журнале
  (маскируется); скачивание по URL не выполняется (SSRF, `§9.6`). Полноценная загрузка — E-COM-11.

Что ещё не сделано в приёме (следующие этапы): динамический маппинг полей в кастомные поля CRM
(E-COM-11), антиспам/rate-limit/Fail2ban (E-COM-09), исходящие вебхуки (E-COM-04/10),
локализация сообщений (E-COM-12).

### Поля витрины (`ecommerce_stores`)

Профиль: `name`, `cms_type` (opencart / woocommerce / bitrix / insales / custom), `store_url`,
`description`, `status` (`active` / `paused` / `disabled`), `locale` (ru-ru / en-gb).

Безопасность: `api_key` (уникальный публичный ключ), `api_secret_encrypted` +
`api_secret_hint`, `secondary_api_secret_encrypted` + `secondary_secret_expires_at`
(поддержка ротации секрета без простоя), `ip_whitelist` (IP/CIDR через запятую),
`rate_limit_per_minute` (по умолчанию 120).

Синхронизация: `webhook_url`, `webhook_secret_encrypted`, `last_ingest_at`, `last_error`.

Операционные настройки — в `settings_json` (`default_project_id`, `default_assignee_id`,
`default_priority_code`, `create_task_for`, `on_duplicate`, `allow_inline_files`,
`antispam_enabled`, `retention_days`, `tags`).

> **Почему секрет шифруется, а не хешируется:** протокол подписывает запросы HMAC-SHA256,
> поэтому сервер обязан уметь воспроизвести секрет. Хеш сделал бы проверку подписи невозможной;
> в открытом виде хранится только маскированный `api_secret_hint`.

## Права

`module.ecommerce-gateway.view`, `module.ecommerce-gateway.manage`,
`module.ecommerce-gateway.secret_manage`, `module.ecommerce-gateway.run`.

## Зависимости ядра

PHP 8.1+, MySQL 8.x/MariaDB, расширения `openssl` (AES-256-GCM) и `pdo_mysql`.
Никаких внешних PHP-пакетов, демонов и очередей.
