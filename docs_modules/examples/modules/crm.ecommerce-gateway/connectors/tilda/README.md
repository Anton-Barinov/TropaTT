# Коннектор TropaTT CRM для Tilda Publishing

Интеграция сайтов на **Tilda** с TropaTT CRM (модуль `crm.ecommerce-gateway`): приём заказов из
**Cart Webhook** и выгрузка цен/остатков через **Tilda Store API**.

Формат поставки: **`tropatt-tilda.zip`** — self-hosted PHP-коннектор (без вендорных зависимостей), который
ставится на любой хостинг с PHP 7.4+ и cURL.

> **Почему так, а не плагин:** у Tilda нет плагинной системы — расширения живут либо как встроенные блоки,
> либо как внешние приёмники вебхуков. Поэтому коннектор — это приёмник Cart Webhook (его вызывает Tilda) плюс
> cron-скрипты для исходящей синхронизации.

## 1. Возможности

- **Приём заказов из Tilda**: `public/webhook.php` принимает Cart Webhook, проверяет секретный токен и
  (опционально) IP-allowlist, **сразу отвечает** `{"status":"ok"}` — и только потом пересылает заказ в CRM
  подписанным запросом HMAC-SHA256.
- **Заказы не теряются**: если CRM недоступен или не настроен, заказ сохраняется в локальную файловую очередь
  (`var/queue/`), а `cron/retry_queue.php` доставляет его позже.
- **Полный маппинг корзины**: позиции (включая опции/варианты), скидки и промокоды, доставка (способ, цена,
  адрес), покупатель, комментарий, `payment.sys`, UTM-метки и cookies аналитики (Яндекс.Метрика, Google
  Analytics, Roistat, UTM) — в `custom_fields`; деньги переводятся из decimal-строк Tilda в минорные единицы.
- **Выгрузка цен и остатков**: `cron/sync_stock.php` читает JSON с остатками (файл или URL) и отправляет их
  батчами по 100 в Tilda Store API (`POST /api/v1/store/products/update`).
- **Диагностика**: режим отладки пишет журнал с маскированием секретов, `cron/retry_queue.php` печатает
  статистику очереди.

### Ограничение платформы

У Tilda **нет API смены статуса заказа**, поэтому обратная синхронизация статусов (как в OpenCart/WooCommerce/
Битрикс) для этой платформы невозможна: CRM получает заказы и отдаёт цены/остатки, но не может перевести
заказ Tilda в другой статус. Это ограничение Tilda, а не коннектора.

## 2. Совместимость

| Компонент | Версия |
|---|---|
| PHP | 7.4 – 8.3 |
| Расширения PHP | `curl`, `hash` (HMAC), `json` |
| Tilda | Cart Webhook (форма «Корзина»), Store API (тариф с API) |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

## 3. Установка

1. Распакуйте архив в каталог на своём хостинге, например `/var/www/tilda-connector/`, так, чтобы
   `public/webhook.php` был доступен по HTTPS (например `https://shop.example.com/tilda/webhook.php`).
2. Скопируйте `config.example.php` в `config.php` и заполните:
   - `gateway_url` — префикс Ingestion API CRM
     (`https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1`);
   - `store_key` и `store_secret` — из карточки витрины в CRM;
   - `tilda_secret` — секретный токен, который вы укажете в настройках вебхука Tilda;
   - `tilda_store_api_key` — ключ Tilda Store API (Tilda → Настройки → API), если нужна выгрузка остатков.
3. В Tilda: **Сайт → Настройки → Формы → Корзина → Webhook URL** — укажите URL приёмника и тот же секретный
   токен. Включите отправку данных заказа.
4. Проверьте приём: оформите тестовый заказ и убедитесь, что в CRM появилась заявка (или включите `debug` и
   посмотрите `var/connector.log`).
5. Настройте cron:
   - каждые 5 минут — `php /path/to/connector/cron/retry_queue.php` (доставка очереди);
   - по расписанию (например раз в 15 минут) — `php /path/to/connector/cron/sync_stock.php <файл-или-URL>`.

## 4. Схема обмена

```
  Tilda (Cart Webhook)                       TropaTT CRM
  ────────────────────                       ───────────
  POST /tilda/webhook.php ──► 200 {"status":"ok"} (ответ сразу, < 100 мс)
        │  секретный токен + IP-allowlist
        ▼
  TildaMapper (canonical E-COM-01) ──► POST /v1/orders (HMAC-SHA256, idempotency key)
        │  при ошибке — файловая очередь
        ▼
  cron/retry_queue.php ──► повторная доставка

  cron/sync_stock.php ──► Tilda Store API /api/v1/store/products/update (цены и остатки)
```

## 5. Файлы

```
public/webhook.php          приёмник Cart Webhook (Tilda вызывает его)
cron/retry_queue.php        доставка заказов из локальной очереди
cron/sync_stock.php         выгрузка цен и остатков в Tilda Store API
config.example.php          шаблон конфигурации (config.php не коммитится)
lib/Config.php              конфигурация и значения по умолчанию
lib/TildaMapper.php         корзина Tilda → канонический payload E-COM-01
lib/CrmClient.php           подписанный клиент шлюза CRM
lib/StoreApiClient.php      клиент Tilda Store API (products/update, батчи по 100)
lib/FileQueue.php           файловая очередь на случай недоступности CRM
lib/IpAllowlist.php         проверка IP/CIDR (диапазоны Tilda настраиваются)
lib/SignatureValidator.php  HMAC-подпись и constant-time сравнение
lib/ConnectorLogger.php     журнал с маскированием секретов
.github/workflows/lint.yml  php -l (PHP 7.4–8.3)
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-tilda.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Tilda получает не `{"status":"ok"}` | Вебхук вызывается методом POST; при ошибке в ответе `error` с причиной |
| Ответ 401 | `tilda_secret` в `config.php` и в настройках вебхука Tilda должны совпадать |
| Ответ 403 | IP отправителя не входит в `allowed_ips` (очистите список, чтобы отключить проверку) |
| В CRM нет заказа | Включите `debug`, посмотрите `var/connector.log`; проверьте `gateway_url`, ключ и секрет; заказ мог остаться в `var/queue/` — запустите `cron/retry_queue.php` |
| Остатки не обновляются в Tilda | Проверьте `tilda_store_api_key` и формат JSON-источника (`sku`, `quantity`, `price_minor`), запустите `cron/sync_stock.php` вручную |
| Нужно ограничить доступ по IP | Укажите диапазоны серверов Tilda в `allowed_ips` (поддерживаются CIDR и одиночные адреса) |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for Tilda Publishing (EN)

Tilda has no plugin system, so the connector is a self-hosted PHP receiver for the **Cart Webhook** plus cron
scripts for outbound synchronisation. The receiver validates the secret token and an optional CIDR allowlist,
answers `{"status":"ok"}` immediately and then forwards the order to the CRM with an HMAC-SHA256 signature;
failed forwards are spooled to `var/queue/` and retried by `cron/retry_queue.php`. Prices and stock are pushed
to the Tilda Store API by `cron/sync_stock.php`.

**Note:** Tilda exposes no order-status API, so reactive status sync (available for OpenCart, WooCommerce and
1C-Bitrix) is not possible on this platform.

## Install

1. Unpack the archive on a PHP 7.4+ host with cURL and expose `public/webhook.php` over HTTPS.
2. `cp config.example.php config.php` and fill in the gateway URL, store key/secret, the Tilda webhook secret
   and the Store API key.
3. In Tilda: **Site → Settings → Forms → Cart → Webhook URL** — point it at the receiver and set the same secret.
4. Add the cron jobs for `cron/retry_queue.php` (every 5 minutes) and `cron/sync_stock.php` (as needed).

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
