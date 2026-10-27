# Коннектор TropaTT CRM для InSales

Интеграция магазинов на **InSales** с TropaTT CRM (модуль `crm.ecommerce-gateway`): приём заказов по
**webhooks v2**, обратная смена статуса заказа и выгрузка цен/остатков через **Admin API**.

Формат поставки: **`tropatt-insales.zip`** — self-hosted PHP-приложение (PHP 7.4+, cURL), которое ставится на
любой хостинг и регистрируется в панели InSales как приложение.

## 1. Возможности

- **Установка приложения по OAuth2**: `public/install.php` ведёт на `/admin/oauth/authorize`, принимает
  `code`, обменивает его на `access_token`/`refresh_token` (`/admin/oauth/token`), сохраняет токен и
  **автоматически подписывает магазин** на топики `orders/create`, `orders/update`, `orders/destroy`
  (`POST /admin/webhooks.json`).
- **Приём заказов**: `public/webhook.php` проверяет подпись `X-InSales-Webhook-Signature`
  (HMAC-SHA256 тела паролем приложения, принимаются hex и base64), **сразу отвечает** `{"status":"ok"}` и
  пересылает заказ в CRM подписанным запросом.
- **Заказы не теряются**: если CRM недоступен/не настроен — заказ уходит в локальную очередь (`var/queue/`),
  `cron/retry_queue.php` доставляет его позже.
- **Полный маппинг заказа**: `order_lines` (включая `sale_price`, характеристики/варианты, `fields_values`),
  клиент, адрес доставки, скидки, способ оплаты и доставки, `number`/`id` как `external_id`; деньги
  переводятся из decimal-строк в минорные единицы.
- **Обратная синхронизация статусов**: `PUT /admin/orders/{id}.json` меняет `fulfillment_status` по вашему
  маппингу (стадия CRM ↔ статус InSales).
- **Смена токена и лимиты**: токен обновляется за 60 с до истечения (`grant_type=refresh_token`), запросы
  проходят через скользящее окно ~500 запросов / 5 минут (`lib/RateLimiter.php`, состояние в файле).
- **Цены и остатки**: `cron/sync_stock.php` отправляет варианты в `PUT /admin/products/variants_group_update.json`
  (по `id` или `sku`, количество ≥ 0, цена в формате `1990.00`).

## 2. Совместимость

| Компонент | Версия |
|---|---|
| PHP | 7.4 – 8.3 |
| Расширения PHP | `curl`, `hash` (HMAC), `json` |
| InSales | тариф с API и приложениями (webhooks v2, Admin API) |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.1+ |

## 3. Установка

1. Распакуйте архив на свой хостинг (например `/var/www/insales-connector/`) так, чтобы каталог `public/`
   был доступен по HTTPS как `/insales/`.
2. Скопируйте `config.example.php` в `config.php` и заполните: `gateway_url`, `store_key`, `store_secret`
   (из карточки витрины в CRM), `insales_shop`, `insales_client_id`, `insales_client_secret`,
   `insales_app_password` (пароль приложения — им подписываются вебхуки).
3. Создайте приложение в InSales (Настройки → Приложения → Разработчикам) с адресом установки
   `https://your-host/insales/install.php` и адресом вебхука `https://your-host/insales/webhook.php`.
4. Откройте `https://your-host/insales/install.php?shop=my-shop.myinsales.ru` — коннектор получит токен и
   подпишет магазин на топики заказов.
5. Настройте cron: `php cron/retry_queue.php` каждые 5 минут и `php cron/sync_stock.php <файл-или-URL>`
   по расписанию.

### 3.1. Проверка подлинности входящих вебхуков

Схема подписи — HMAC-SHA256 по «сырому» телу запроса паролем приложения (принимаются hex и base64).
Имя заголовка в опубликованной модели вебхуков не зафиксировано, поэтому проверяются все встречающиеся
варианты (`X-InSales-Webhook-Signature`, `X-InSales-Signature`, `X-InSales-Hmac-Sha256`, `X-Signature`),
а также общий секрет в URL или заголовке (`?token=`, `?secret=`, `?key=`, `X-InSales-Webhook-Token`).

Поведение управляется настройкой `webhook_verification`:

| Значение | Что принимается |
|---|---|
| `auto` (по умолчанию) | подпись **или** общий секрет; иначе запрос отклоняется (401) |
| `signature` | только HMAC-подпись |
| `token` | только общий секрет (URL/заголовок) |
| `off` | ничего не проверяется (небезопасно; в журнал пишется предупреждение) |

Отдельный секрет можно задать в `webhook_secret`; если он пуст, используется `insales_app_password`.

### 3.2. Соответствие официальному API (проверка по документации)

| Что проверено | Источник | Результат |
|---|---|---|
| Базовый путь Admin API `/admin/…`, Basic Auth `api_key:api_password` | `insales/insales_api` (gem), `gwinn/saas-connector` | подтверждено |
| Смена статуса заказа: `PUT /admin/orders/{id}.json`, поле `fulfillment_status` | `pomeo/node-insales` (apiary blueprint) | подтверждено |
| Топики вебхуков: `orders/create`, `orders/update` | `gwinn/saas-connector` (`Webhook` model: «For now only 'orders/update' and 'orders/create' are available») | подтверждено; `orders/destroy` подписывается как best-effort и не ломает установку |
| Групповое обновление остатков: `PUT /admin/products/variants_group_update.json` | `pomeo/node-insales` (apiary), `AntonLeontev/pull.ru-app`, `stap780/t2` | **исправлено**: было `POST /admin/variants_group_update.json` |
| Лимит 500 запросов / 5 минут и заголовок `API-Usage-Limit: n/500` | `insales/insales-docs-source`, `docs/InSales API/01 readme.md` | учтено: серверные сигналы (`API-Usage-Limit`, `Retry-After` при 429/503) теперь сохраняются и учитываются лимитером |

## 4. Схема обмена

```
  InSales                                        TropaTT CRM
  ───────                                        ───────────
  orders/create|update|destroy ──► /insales/webhook.php
        │  X-InSales-Webhook-Signature (HMAC-SHA256)
        │  ответ {"status":"ok"} сразу
        ▼
  InSalesMapper (canonical E-COM-01) ──► POST /v1/orders (HMAC-SHA256, idempotency key)
        │  при ошибке — файловая очередь
        ▼
  cron/retry_queue.php ──► повторная доставка

  CRM (стадия задачи) ──► PUT /admin/orders/{id}.json (fulfillment_status)
  cron/sync_stock.php ──► PUT /admin/products/variants_group_update.json (цены и остатки)
```

## 5. Файлы

```
public/install.php          OAuth2-установка приложения и подписка на вебхуки
public/webhook.php          приём заказов (orders/create|update|destroy)
cron/retry_queue.php        доставка заказов из локальной очереди
cron/sync_stock.php         выгрузка цен и остатков в InSales
config.example.php          шаблон конфигурации (config.php не коммитится)
lib/OAuthClient.php         authorize URL, обмен кода, refresh токена
lib/TokenStore.php          хранение токена и проверка срока действия
lib/WebhookVerifier.php     проверка подписи вебхука (hex/base64)
lib/InSalesMapper.php       заказ InSales → канонический payload E-COM-01
lib/InSalesClient.php       Admin API: заказ, статус, варианты, лимиты
lib/RateLimiter.php         скользящее окно ~500 запросов / 5 минут
lib/StatusMapper.php        маппинг стадий CRM и статусов InSales
lib/CrmClient.php           подписанный клиент шлюза CRM
lib/FileQueue.php           файловая очередь на случай недоступности CRM
lib/SignatureValidator.php  HMAC-подпись и constant-time сравнение
lib/ConnectorLogger.php     журнал с маскированием секретов
.github/workflows/lint.yml  php -l (PHP 7.4–8.3)
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-insales.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Вебхук отвечает 401 | `insales_app_password` должен совпадать с паролем приложения в InSales |
| Заказы не приходят | Подписки на топики (`install.php`), доступность `webhook.php` по HTTPS, журнал при `debug` |
| В CRM нет заказа | Проверьте `gateway_url`/ключ/секрет; заказ мог остаться в `var/queue/` — запустите `cron/retry_queue.php` |
| `The application is not installed` | Не выполнен шаг OAuth: откройте `install.php?shop=...` и завершите установку |
| «Token refresh failed» | Проверьте `insales_client_id`/`insales_client_secret`, переустановите приложение |
| 429 или «rate limit reached» | Лимит InSales (~500 запросов / 5 мин); уменьшите `rate_limit` или подождите окно |
| Остатки не обновляются | В JSON-источнике нужны `sku` (или `id`) и `quantity`; проверьте права приложения на товары |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for InSales (EN)

A self-hosted PHP application that connects **InSales** shops with the TropaTT CRM e-commerce gateway:
OAuth2 install with automatic `orders/*` webhook subscription, signature-verified order intake, reactive
status updates (`PUT /admin/orders/{id}.json`) and price/stock synchronisation
(`PUT /admin/products/variants_group_update.json`). Tokens are refreshed automatically and every API call goes through
a sliding-window rate limiter for the platform's ~500 requests / 5 minutes limit.

## Install

1. Unpack the archive on a PHP 7.4+ host with cURL and expose `public/` over HTTPS as `/insales/`.
2. `cp config.example.php config.php` and fill in the gateway URL, store key/secret and the InSales application
   credentials (`insales_shop`, `insales_client_id`, `insales_client_secret`, `insales_app_password`).
3. Register the application in InSales with install URL `/insales/install.php` and webhook URL `/insales/webhook.php`.
4. Open `https://your-host/insales/install.php?shop=my-shop.myinsales.ru` to obtain the token and subscribe the shop.
5. Add the cron jobs (`cron/retry_queue.php`, `cron/sync_stock.php`).

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
