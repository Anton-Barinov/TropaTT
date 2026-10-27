# Коннектор TropaTT CRM для Shopify

Приложение для **Shopify** (Admin GraphQL API): приём заказов через вебхуки, обратная синхронизация
(fulfillment/cancel/close по стадии CRM) и выгрузка остатков.

Формат поставки: **`tropatt-shopify.zip`** — self-hosted PHP-приложение (PHP 7.4+, cURL), которое
регистрируется в Shopify Partners как публичное или custom-приложение магазина.

## 1. Возможности

- **Установка по OAuth2**: `public/install.php` → `/admin/oauth/authorize` (scopes и `state`),
  `public/callback.php` проверяет `hmac` и `state`, обменивает код на токен
  (`/admin/oauth/access_token`) и **сам подписывает магазин** на топики `ORDERS_CREATE`,
  `ORDERS_UPDATED`, `ORDERS_CANCELLED`, `APP_UNINSTALLED` и обязательные GDPR-топики
  `CUSTOMERS_DATA_REQUEST`, `CUSTOMERS_REDACT`, `SHOP_REDACT`.
- **Expiring offline токен**: обмен кода идёт с `expiring=1`, поэтому возвращаются `access_token`
  (1 час), `refresh_token` (90 дней), `expires_in` и `refresh_token_expires_in`. Публичным приложениям
  non-expiring токены для Admin API запрещены с 2027-01-01, поэтому обновляет токен
  `cron/refresh_token.php` — без участия мерчанта (фоновая задача не может открыть админку).
  Если `X-Shopify-Api-Version` в ответе не совпадает с запрошенной версией, Shopify молча обслужил
  запрос «соседней» версией — клиент пишет это в лог (иначе устаревший пин незаметен).
- **Приём заказов**: `public/webhook.php` проверяет `X-Shopify-Hmac-Sha256`
  (`base64(HMAC-SHA256(raw_body, app_secret))`), **сразу отвечает** `{"status":"ok"}` и пересылает заказ в CRM
  подписанным запросом (HMAC-SHA256, идемпотентность `{store_key}:order:{order_id}`).
- **Приём статусов CRM→Shopify**: `public/status.php` (URL указывается в карточке витрины как
  `webhook_url`) проверяет подпись CRM `X-TropaTT-Signature`
  (`base64(HMAC-SHA256(store_secret, timestamp . '.' . raw_body))`, окно ±300 с, защита от повторного
  применения пакета) и применяет действие по стадии: `fulfill` → `fulfillmentCreate`,
  `cancel` → `orderCancel`, `close` → `orderClose`, `noop`/неизвестное — игнор с ответом 200.
  `event: ping` отвечает `{"code":"PONG"}`.
- **Анти-эхо**: fulfillment/cancel заставляют Shopify прислать `orders/updated`. Ордер, изменённый
  коннектором, помечается на 10 минут (`lib/EchoGuard.php`), и `webhook.php` его обратно в CRM не шлёт —
  иначе стадия может «пинг-понговать» между системами.
- **GDPR и удаление приложения**: `customers/data_request` — ответ с описанием хранения (никакой локальной
  базы клиентов нет, только спул доставки), `customers/redact` — удаление спуленных пакетов покупателя,
  `shop/redact` — очистка спула и **удаление токена**, `app/uninstalled` — удаление токена.
- **Заказы не теряются**: при сбое доставки канонический payload складывается в `var/queue/`, его отправляет
  `cron/retry_queue.php`.
- **Обратная синхронизация**: стадия CRM → действие Shopify (`fulfill` через `fulfillmentCreate`,
  `cancel` через `orderCancel`, `close` через `orderClose`) через `lib/StatusMapper.php` + `lib/StatusApplier.php`.
- **Остатки**: `cron/sync_stock.php` обновляет количества через мутацию `inventorySetQuantities`
  (по `inventory_item_id` и location), **учитывая Leaky Bucket**: клиент читает
  `extensions.cost.throttleStatus` и выдерживает паузу перед следующим вызовом.
- **Защита от блокировок**: cost-aware throttling, батчи по 100 позиций, retry-очередь заказов.

## 2. Совместимость

| Компонент | Версия |
|---|---|
| PHP | 7.4 – 8.3 |
| Расширения PHP | `curl`, `hash` (HMAC), `json` |
| Shopify | Admin API `2026-07` (настраивается; версия должна быть поддерживаемой), публичное или custom-приложение |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

> **Про версию API.** Shopify выпускает новую версию раз в квартал и обслуживает каждую ~12 месяцев.
> Запрос к снятой с поддержки версии не падает: Shopify отвечает **самой старой поддерживаемой** и
> сообщает её в заголовке `X-Shopify-Api-Version` — то есть устаревший пин ломается тихо. Коннектор это
> логирует. Список поддерживаемых версий и даты см. в документации Shopify «API versioning».

## 3. Установка

1. Распакуйте архив на свой хостинг так, чтобы каталог `public/` был доступен по HTTPS как `/shopify/`.
2. Скопируйте `config.example.php` в `config.php` и заполните: `gateway_url`, `store_key`, `store_secret`
   (из карточки витрины в CRM), `shopify_api_key`, `shopify_api_secret` (из Shopify Partners),
   `shopify_shop`, при необходимости `shopify_location_id` и `status_mapping`.
3. В Shopify Partners укажите: **App URL** — `https://your-host/shopify/install.php`,
   **Allowed redirection URL** — `https://your-host/shopify/callback.php`.
4. Установите приложение в магазин (App URL + `?shop=my-shop.myshopify.com`) — коннектор получит токен и
   подпишет магазин на вебхуки.
5. В карточке витрины в CRM укажите **`webhook_url` = `https://your-host/shopify/status.php`** — по нему
   приходят изменения стадий.
6. Настройте cron:
   - `php cron/refresh_token.php` — каждые 20 минут (обновление expiring токена);
   - `php cron/retry_queue.php` — каждые 5 минут;
   - `php cron/sync_stock.php <файл>` — по расписанию.

## 4. Схема обмена

```
  Shopify                                        TropaTT CRM
  ───────                                        ───────────
  orders/create|updated|cancelled ──► /shopify/webhook.php
        │  X-Shopify-Hmac-Sha256 (base64 HMAC тела)
        │  ответ {"status":"ok"} сразу
        ▼
  OrderMapper (canonical E-COM-01) ──► POST /_module/crm.ecommerce-gateway/v1/orders (HMAC-SHA256)
        │  при ошибке — файловая очередь
        ▼
  cron/retry_queue.php ──► повторная доставка

  стадия CRM ──► POST /shopify/status.php  (X-TropaTT-Signature = HMAC(timestamp.body))
        │  StatusMapper: done→fulfill, canceled→cancel, …
        ▼
  fulfillmentCreate / orderCancel / orderClose   ──► lib/EchoGuard.php помечает ордер (анти-эхо)

  cron/sync_stock.php ──► inventorySetQuantities (Leaky Bucket aware)
  cron/refresh_token.php ──► продление expiring offline токена
  GDPR: data_request / redact / shop_redact ──► очистка спула и токена
```

## 5. Файлы

```
public/install.php          старт OAuth2 (authorize URL + state)
public/callback.php         обмен кода на токен (`expiring=1`) и подписка на вебхуки
public/webhook.php          приём заказов, GDPR-топики, app/uninstalled, анти-эхо
public/status.php           приём статусов CRM→Shopify (подпись, ping, применение действия)
cron/refresh_token.php      продление expiring offline токена
cron/retry_queue.php        доставка спуленных заказов
cron/sync_stock.php         выгрузка остатков (inventorySetQuantities)
config.example.php          шаблон конфигурации (config.php не коммитится)
lib/Config.php              конфигурация приложения и версия API
lib/ShopifySignature.php    проверка вебхука, hmac запроса, state, домен магазина
lib/OAuthClient.php         authorize URL, обмен кода на токен, refresh grant
lib/TokenStore.php          хранение токена, сроки жизни и необходимость refresh
lib/ShopifyClient.php       GraphQL Admin API + cost-aware throttling
lib/GraphQL/Queries.php     документы запросов и мутаций (чистые)
lib/OrderMapper.php         заказ Shopify → канонический payload
lib/StatusMapper.php        стадия CRM → действие Shopify
lib/StatusApplier.php       применение действия (fulfillmentCreate/orderCancel/orderClose)
lib/EchoGuard.php           анти-эхо и защита от повторного применения пакета
lib/StockSync.php           маппинг остатков и батчи
lib/GdprHandler.php         обязательные GDPR-вебхуки
lib/CrmClient.php           подписанный клиент шлюза CRM (+ тестовый транспорт)
lib/FileQueue.php           локальная очередь доставки
lib/SignatureValidator.php  HMAC-подпись и constant-time сравнение
lib/ConnectorLogger.php     журнал с маскированием секретов
.github/workflows/lint.yml  php -l (PHP 7.4–8.3)
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-shopify.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Вебхук отвечает 401 | `shopify_api_secret` должен совпадать с секретом приложения в Shopify Partners |
| Заказы не приходят | Подписки на топики (создаются в `callback.php`), доступность `webhook.php` по HTTPS |
| В CRM нет заказа | Проверьте `gateway_url`/ключ/секрет; заказ мог остаться в `var/queue/` — запустите `cron/retry_queue.php` |
| Статус из CRM не применяется | `webhook_url` в карточке витрины должен указывать на `status.php`; `store_secret` должен совпадать; часы сервера — в пределах ±300 с |
| Статус применяется дважды | Такого быть не должно: одинаковый подписанный пакет применяется один раз (10 минут), проверьте `var/queue/echo/` |
| Стадия «прыгает» между системами | Анти-эхо пишет в лог `skipped the echo`; проверьте права на `var/` |
| Через час всё отваливается с 401 | Не запущен `cron/refresh_token.php` (expiring токен живёт 1 час) |
| Запросы идут не в ту версию API | В логе `shopify api version fallback` — обновите `shopify_api_version` |
| «The app is not installed» | Не выполнен OAuth: откройте `install.php?shop=...` и завершите установку |
| Остатки не обновляются | Нужны `sku`, `inventory_item_id` в JSON и корректный `shopify_location_id`; проверьте лимиты (Leaky Bucket) |
| Shopify отвечает `THROTTLED` | Увеличьте паузы: клиент уже учитывает `throttleStatus`, уменьшите размер батча |
| Приложение удалено из магазина | Токен удаляется автоматически (`app/uninstalled`); для повторной установки откройте `install.php` |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for Shopify (EN)

A self-hosted PHP app for **Shopify** (Admin GraphQL API): OAuth2 install with automatic webhook subscription
(orders + mandatory GDPR topics), signature-verified order intake mapped to the canonical TropaTT contract,
CRM stage → Shopify actions (`fulfillmentCreate` / `orderCancel` / `orderClose`) through a signature-verified
status endpoint, and cost-aware stock sync through `inventorySetQuantities`.

Install uses the authorization code grant with `expiring=1`, so the connector stores an expiring offline
access token plus its refresh token and renews it from `cron/refresh_token.php` — public apps may no longer
call the Admin API with a non-expiring token. The pinned Admin API version must be a supported one: a request
for a retired version is silently served by the oldest supported version, which the client logs via the
`X-Shopify-Api-Version` response header.

## Install

1. Unpack the archive so `public/` is served over HTTPS as `/shopify/`.
2. `cp config.example.php config.php` and fill in the gateway credentials plus the Shopify app key/secret and shop.
3. Set **App URL** = `/shopify/install.php` and **Allowed redirection URL** = `/shopify/callback.php` in Shopify Partners.
4. Set the store's `webhook_url` in the CRM to `/shopify/status.php` and add the cron jobs
   (`cron/refresh_token.php`, `cron/retry_queue.php`, `cron/sync_stock.php`).

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
