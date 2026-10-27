# crm.ecommerce-gateway

## 1.2.5

- Журнал синхронизации показывает понятное пустое состояние, если рабочее пространство ещё не содержит витрин, вместо бесконечного индикатора загрузки.
- Фильтр журнала, повтор DLQ и сверка заказов недоступны до выбора витрины.

## 1.2.6

- Некорректный IP/CIDR в allowlist теперь отклоняется при создании или изменении витрины;
  повреждённый непустой allowlist в базе не превращается в разрешение для всех IP.

Универсальный модуль-шлюз для интернет-магазинов: приём заказов, покупок в один клик,
заявок на обратный звонок, обращений из форм обратной связи и произвольных лид-форм
из любой CMS в TropaTT CRM.

## Состояние

| Этап | Содержание | Статус |
|---|---|---|
| E-COM-01 | Архитектурная спецификация протокола (REST & Webhooks) | готово (`docs/web/ecommerce-gateway-tz.md`, OpenAPI) |
| E-COM-02 | Ядро модуля: манифест, миграции БД, аутентификация витрин | готово |
| E-COM-03 | Ingestion API: приём заказов/форм, валидация схем, дедупликация, создание заявок | готово |
| E-COM-04 | Двусторонняя реактивная синхронизация статусов (CRM Events -> CMS Webhooks), Outbox, HMAC подпись, FSM | готово |
| E-COM-05 | UI панели управления витринами: реестр магазинов, маппинг воронок/статусов, аудит-лог | готово |
| E-COM-09 | Кибербезопасность: Fail2ban, защита от SQLi, XSS, ReDoS, антиспам, изоляция ключей | готово |
| E-COM-10 | Гарантия доставки (Transactional Outbox), DLQ, авто-восстановление (Reconciliation) | готово |
| E-COM-11 | Полиморфный Ingestion: звонки, 1 клик, формы связи, динамический маппинг полей | готово |
| E-COM-12 | Мультиязычность интерфейса и локализация шлюза (i18n: ru-ru, en-gb) | готово |
| E-COM-13 | Совместимость с Shared-хостингом (Zero-Daemon, cron/web-cron, MySQL locks, лимиты памяти < 32MB) | готово |
| E-COM-14 | Комплексное QA и сквозное E2E-тестирование: синтетическая имитация полного жизненного цикла | готово |
| E-COM-15 | Релизный гейт качества, деплой на demo.tropatt.com и итоговая верификация | готово |
| Connectors | Референсные плагины для OpenCart 2.3, OpenCart 3.0, OpenCart 4.0, WooCommerce и сборка дистрибутивов | готово |

Проверки: `php -l` по всем файлам модуля, контрактные тесты
`upload/api/tests/unit/ecommerce_gateway_signature_unit.php`,
`ecommerce_gateway_payload_unit.php` (схемы и Markdown-композер),
`ecommerce_gateway_ingest_unit.php` (идемпотентность/контакты, SQLite),
`ecommerce_gateway_status_sync_unit.php` (FSM переходов, эхо-петли, Outbox, HMAC-подпись, retry-политика),
`ecommerce_gateway_ui_unit.php` (веб-роутер, контроллер, шаблоны, ассеты UI),
тест декларации миграций `module_migrations_declared_unit.php`,
покрытие маршрутов `api/scripts/api_coverage_check.php`.

## Поля заказа: контракт приёма

Что именно доходит до карточки заявки — и что молча терялось раньше:

* **Страна доставки** — канонично `delivery_address.country_code` (ISO 3166-1 alpha-2, приводится к
  верхнему регистру). Прежние коннекторы шлют ключ `country`: он по-прежнему принимается.
  Значение разрешается через `CountryResolver`: двухбуквенный токен проходит как код, а
  **название страны** (`Россия`, `Germany`, `Türkiye`, `Belarus`) превращается в код по словарю
  русских и английских названий (регистр, диакритика и пробелы не важны). Это чинит коннекторы,
  которые отдают только локальное название — Webasyst (`shipping_country`), Moguta, Tilda и InSales
  (`country`) — **без их переиздания**. Неизвестное название в `country_code` не попадает
  (никаких обрезанных кодов вроде `НИ`): витрина должна присылать код или название из словаря,
  а читаемое название можно положить в `custom_fields`.
* **`custom_fields`** — свободный мешок платформенных данных (комментарий покупателя, IP,
  промокоды, значения полей магазина, аналитические cookies). Сохраняется валидатором
  (ключи в т.ч. кириллические, один уровень вложенности) и рендерится в описание заявки
  блоком «Дополнительные поля магазина». До исправления мешок отбрасывался целиком: 12 из
  поставляемых коннекторов заполняли его напрасно.
* **Статусы** — внешний статус берётся только из настроенного маппинга витрины. Если
  маппинга для стадии CRM нет, событие не отправляется вовсе: раньше в магазин уезжал сам
  CRM-код (для WooCommerce это не `wc-*` статус, и заказ молча сбрасывался в `pending`,
  хотя CRM фиксировала 200).

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
| `POST` | `/stock` | подпись витрины | Приём остатков из витрины (встречная сторона `syncStock`) |
| `GET` | `/stores` | `module.ecommerce-gateway.view` | Реестр витрин |
| `POST` | `/stores` | `…manage` + `…secret_manage` | Создать витрину (секрет возвращается один раз) |
| `GET` | `/stores/{public_id}` | `…view` | Карточка витрины |
| `PATCH` | `/stores/{public_id}` | `…manage` | Изменить витрину |
| `DELETE` | `/stores/{public_id}` | `…manage` | Мягко удалить витрину |
| `POST` | `/stores/{public_id}/rotate-secret` | `…manage` + `…secret_manage` | Ротация секрета с grace-периодом 1 час |
| `GET` | `/stores/{public_id}/status-mappings` | `…view` | Список маппингов статусов CMS ↔ CRM |
| `PUT`, `POST` | `/stores/{public_id}/status-mappings` | `…manage` | Сохранение маппингов статусов |
| `GET` | `/stores/{public_id}/sync-log` | `…view` | Журнал синхронизации (inbound/outbox) |
| `POST` | `/stores/{public_id}/sync-log/{log_id}/retry` | `…manage` | Ручной повтор отправки события Outbox |
| `POST` | `/stores/{public_id}/ping-test` | `…manage` | Проверка доступности витрины (Ping-тест) |
| `GET` | `/stores/{public_id}/outbox/dlq` | `…view` | Реестр событий Dead Letter Queue (DLQ) |
| `POST` | `/stores/{public_id}/outbox/dlq/replay` | `…manage` | Перезапуск событий из Dead Letter Queue |
| `POST` | `/stores/{public_id}/reconciliation/run` | `…manage` | Запуск периодической сверки заказов (Reconciliation) |

## Веб-интерфейс панели управления

Маршрут: `/web/index.php?route=module-ecommerce-gateway`
- **Витрины и Настройки**: реестр магазинов, создание, ротация ключей, Ping-тест.
- **Маршрутизация**: правила создания задач под `order`, `quick_order`, `callback`, `feedback`, стратегии дедупликации (`merge`/`reject`/`create`).
- **Маппинг статусов**: интерактивное сопоставление статусов витрины со стадиями задач CRM.
- **Безопасность**: IP-whitelist, лимиты частоты запросов, хранение логов, антиспам.

Пустой allowlist разрешает запросы с любых IP. Если список задан, каждая запись должна быть
валидным IPv4/IPv6 адресом или CIDR; при хотя бы одной ошибочной записи сохранение отклоняется,
а при некорректном значении в старой/повреждённой строке входящий запрос блокируется.
- **Журнал синхронизации**: аудит входящих пакетов и исходящих вебхуков с инспектором JSON и кнопкой Retry.

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

`004_create_stock_tables.sql`: `ecommerce_stock` (одна строка на `(store_id, sku)`,
уникальный ключ `uq_ecommerce_stock_store_sku`) и `ecommerce_stock_sync_log`
(журнал принятых пакетов остатков с counters и `request_id`) — принимающая сторона
коннекторного `syncStock`.

`003_create_outbox_tables.sql`: таблица Transactional Outbox `ecommerce_outbox_events` (E-COM-04)
для гарантированной асинхронной доставки исходящих вебхуков (`order.status_changed`) в витрины CMS
с экспоненциальным бэкоффом (`base_delay * 2^retry`) и защитой от эхо-петель (`StatusSyncContext`).

## Приём остатков (встречная сторона `syncStock`)

Коннекторы OpenCart/WooCommerce умеют отдавать остатки (`syncStock?mode=pull`)
и принимать пакет остатков (`mode=push`), но в CRM не было принимающей стороны.
`POST /v1/stock` — это она: тот же подписанный транспорт, что и у заказов
(HMAC-подпись `StoreAuthService`, Nonce, tolerance, IP-whitelist).

Тело запроса — пакет до 500 строк; принимаются оба вида строки:

```json
{
  "items": [
    { "sku": "AAA-1", "quantity": 12, "name": "Товар A",
      "price": { "amount_minor": 199900, "currency": "RUB" }, "status": 1 }
  ]
}
```

Канонический вид коннектора (`name`, вложенный `price`, `status` = включён ли товар)
и «плоский» вид (`title`, `price_minor`, `currency`, `is_active`) равнозначны. Массив
`products` (страница `mode=pull`) и `lines` тоже принимаются, поэтому витрина может
переслать свою страницу в CRM дословно. `dry_run: true` проверяет пакет без записи в БД.

- **Идемпотентность**: ключ на каждый SKU — `{store_key}:stock:{sku}` (явный
  `X-TropaTT-Idempotency-Key` учитывается только для пакета из одной строки), хеш тела
  включает `sku`, `quantity`, `price_minor`, `currency`. Повтор без изменений → строка
  `unchanged` (`skipped`), изменённое количество применяется. Ключ, привязанный к другому
  SKU, → строка `failed` (`idempotency_conflict`).
- **Валидация**: пустой/отсутствующий массив → `400 STOCK_ITEMS_REQUIRED`, больше 500 строк
  → `400 STOCK_BATCH_TOO_LARGE`, ошибки строк собираются в `errors` по индексам
  (`items.3.quantity`) и возвращаются как `422 INGESTION_VALIDATION_FAILED`; валидные строки
  пакета при этом применяются (частичное применение).
- **Хранение**: `ecommerce_stock` — одна строка на `(store_id, sku)` (upsert, поэтому
  повторная и неупорядоченная отправка не плодит дубликатов); `ecommerce_stock_sync_log` —
  журнал пакетов с counters и `request_id` для сверки. Ключ витрины в журнал не попадает.
- **Ответ**: `data.counters` (`total` / `applied` / `skipped` / `failed`), `data.items`
  (по строке: `result` = `applied` / `unchanged` / `failed` / `would_apply`, `reason`),
  `data.summary` (`items`, `quantity`, `out_of_stock`, `last_synced_at`) и `data.log_public_id`.

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


## Референсные модули интеграции (Connectors)

В директории `connectors/` представлены готовые к установке модули для популярных CMS:
- `connectors/opencart-2.3/`: официальное расширение для OpenCart 2.3.0.x / ocStore 2.3.0.x — самая массовая версия в РФ/СНГ (шаблоны `.tpl`, токен `token`, события через `model('extension/event')`, OCMOD-fallback в `install.xml`, распознавание реквизитов из полей SimpleCheckout, постраничная синхронизация остатков `syncStock` с блокировкой строк).
- `connectors/opencart-3.0/`: официальное расширение для OpenCart 3.0.x / ocStore 3.0.x (Twig, user_token, события `catalog/model/checkout/order/addOrderHistory/after`, защита от эхо-петель).
- `connectors/opencart-4.0/`: официальное расширение для OpenCart 4.0.x (PSR-4 пространства имен `Opencart\...`, вызовы событий по ссылке).
- `connectors/woocommerce/`: официальный плагин WordPress / WooCommerce (хуки оформления и изменения статусов, REST-эндпоинт `/tropatt/v1/webhook`).
- Кроме OpenCart и WooCommerce, исходники включают коннекторы Bitrix, CS-Cart, InSales, Magento, Moguta, PrestaShop, Shopify, Tilda и Webasyst.
- `connectors/dist/`: готовые zip-архивы всех 15 коннекторов для установки на соответствующие CMS. Архивы в этой папке должны собираться из исходников, а не копироваться вручную.

Чтобы пересобрать и проверить дистрибутивы, выполните из каталога `connectors/`:

```bash
bash build-all.sh
```

Скрипт запускает сборщик каждого коннектора, публикует текущие архивы в `connectors/dist/`, удаляет оттуда устаревшие версии Bitrix и проверяет целостность и совпадение release-файлов с результатами сборки.
