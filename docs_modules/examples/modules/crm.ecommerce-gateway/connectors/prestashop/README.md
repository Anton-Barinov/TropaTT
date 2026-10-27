# Коннектор TropaTT CRM для PrestaShop 1.6 / 1.7 / 8.x

Модуль **TropaTT CRM** для **PrestaShop**: двусторонняя синхронизация заказов, покупателей и статусов с
TropaTT CRM (модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt-prestashop.zip`** — архив с каталогом `tropatt/`, который распаковывается в
`modules/` магазина (модуль ставится в `modules/tropatt/`).

## 1. Возможности

- **Заказы в CRM**: хуки `actionValidateOrder` (новый заказ), `actionOrderStatusPostUpdate` (смена статуса) и
  `actionObjectOrderUpdateAfter` (любое обновление) — заказ уходит в CRM подписанным запросом (HMAC-SHA256) с
  идемпотентностью `{store_key}:order:{order_id}`.
- **Полный маппинг заказа**: позиции с артикулами (`product_reference`/`reference`), **комбинациями**
  (`id_product_attribute` и атрибуты), ценами **с налогом и без** (`unit_price_tax_incl` /
  `unit_price_tax_excl`, в `custom_fields` — общая сумма без налога), доставка, оплата, адрес и заказчик
  (`Customer`/`Address`/`Country`), заметка менеджера, **Multi-Store** (`id_shop`).
- **Обратная синхронизация статусов**: фронт-контроллер `modules/tropatt/webhook` проверяет подпись HMAC и
  окно ±300 с, переводит заказ в состояние через `setCurrentState()` + `save()` с подавлением эха
  (`Configuration::updateValue('TROPATT_SKIP_SYNC', 1)`), поэтому смена статуса не уходит обратно в CRM.
- **Обновление остатков**: `TropattStockSync` + `cron/sync_stock.php` вызывают
  **`StockAvailable::setQuantity()`** по ссылке (или `product_id`) с учётом комбинации и магазина (Multi-Store).
  Поиск товара по ссылке работает на всех поддерживаемых версиях: `Product::getIdByReference()` есть только
  с 1.7, поэтому на 1.6 используется прямой запрос по индексу `reference` (раньше вызов падал с фатальной
  ошибкой и ни один остаток не применялся).
- **Магазин не тормозит**: таймаут запроса ограничен (по умолчанию 5 с), при сбое канонический payload
  складывается в локальную очередь `modules/tropatt/var/queue/`.
- **Настройки в админке**: включение, URL шлюза, ключ/секрет витрины, секрет вебхука, стадия по умолчанию,
  таблица маппинга «id состояния PrestaShop = стадия CRM», отладка и готовый URL вебхука.

## 2. Совместимость

| Компонент | Версия |
|---|---|
| PrestaShop | 1.6.0.x – 8.2.x |
| PHP | 7.1 – 8.2 |
| Расширения PHP | `curl` (есть fallback на потоки), `hash` (HMAC), `json` |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

## 3. Установка

1. Распакуйте архив в каталог `modules/` магазина (получится `modules/tropatt/`).
2. В админке: **Модули → Менеджер модулей** → найдите «TropaTT CRM — e-commerce gateway» → **Установить**.
3. Откройте **Настроить** и заполните: URL шлюза, публичный ключ витрины (`stk_...`), секрет витрины, секрет
   вебхука, стадию CRM по умолчанию и маппинг «id состояния PrestaShop = стадия CRM».
4. Скопируйте URL вебхука из настроек в карточку витрины в TropaTT CRM:
   `https://shop.example.com/index.php?fc=module&module=tropatt&controller=webhook`.
5. Для синхронизации остатков настройте cron:
   `php /path/to/modules/tropatt/cron/sync_stock.php /path/to/stock.json [id_shop]`.

## 4. Схема обмена

```
  PrestaShop                                     TropaTT CRM
  ──────────                                     ───────────
  actionValidateOrder / actionOrderStatusPostUpdate / actionObjectOrderUpdateAfter
        │  Order + Customer + Address + Currency → canonical E-COM-01
        ▼
  TropattClient ──► POST /_module/crm.ecommerce-gateway/v1/orders (HMAC-SHA256, idempotency key)
        │  при ошибке — файловая очередь
        ▼
  modules/tropatt/webhook ◄── POST order.status_changed ─── CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        ▼
  setCurrentState() + save()  (anti-echo: Configuration TROPATT_SKIP_SYNC)

  cron/sync_stock.php ──► StockAvailable::setQuantity() (остатки по складу и комбинации)
```

## 5. Файлы

```
tropatt/tropatt.php                          модуль: хуки, форма настроек, отправка заказа
tropatt/config.xml                           метаданные модуля
tropatt/controllers/front/webhook.php        приём вебхуков CRM
tropatt/cron/sync_stock.php                  обновление остатков из JSON
tropatt/classes/TropattOrderMapper.php       заказ PrestaShop → канонический payload
tropatt/classes/TropattStockSync.php         StockAvailable::setQuantity() по ссылке/комбинации
tropatt/classes/TropattStatusMapper.php      маппинг состояний PrestaShop ↔ стадии CRM
tropatt/classes/TropattClient.php            подписанный клиент шлюза (cURL + потоки)
tropatt/classes/TropattSignature.php         HMAC-подпись и constant-time сравнение
tropatt/classes/TropattFileQueue.php         локальная очередь недоставленных заказов
tropatt/classes/TropattLogger.php            журнал с маскированием секретов
tropatt/classes/TropattConfig.php            настройки (Configuration) и флаг анти-эха
tropatt/translations/{ru,en}.php             локализация
.github/workflows/lint.yml                   php -l (7.1–8.2) и проверка манифеста
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-prestashop.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Заказы не уходят в CRM | Модуль включён, настройки заполнены; включите отладку и посмотрите `modules/tropatt/var/tropatt.log` |
| В CRM нет заказа | Проверьте URL шлюза/ключ/секрет; заказ мог попасть в `modules/tropatt/var/queue/` |
| Вебхук отвечает 401 | `TROPATT_WEBHOOK_SECRET` должен совпадать с секретом витрины в CRM; проверьте время сервера (±300 с) |
| Вебхук отвечает 404 | Заказ не найден по `external_order_id` |
| Вебхук отвечает 422 | В маппинге нет пары для стадии CRM либо указан несуществующий id состояния |
| Остатки не обновляются | В JSON-источнике нужны `sku` (или `product_id`) и `quantity`; проверьте права и `id_shop` при Multi-Store |
| Цены в CRM отличаются от магазина | В CRM уходит цена с налогом; цена без налога передаётся в опциях позиции (`price_tax_excl_minor`) и в `custom_fields` |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for PrestaShop 1.6 / 1.7 / 8.x (EN)

A module (`modules/tropatt/`) that synchronises orders, customers and statuses between **PrestaShop** and the
TropaTT CRM e-commerce gateway. Orders are hooked on `actionValidateOrder` / `actionOrderStatusPostUpdate` /
`actionObjectOrderUpdateAfter`, mapped with product references, combinations and both tax-inclusive and
tax-exclusive prices (including Multi-Store `id_shop`), and pushed with an HMAC-SHA256 signature; failures are
spooled locally. The CRM webhook applies state changes through `setCurrentState()` with echo suppression, and
stock is pushed with `StockAvailable::setQuantity()`.

## Install

1. Unpack the archive into the shop's `modules/` directory.
2. **Modules → Module Manager** → install *TropaTT CRM — e-commerce gateway* and fill in the settings.
3. Point the store webhook in TropaTT CRM at
   `https://shop.example.com/index.php?fc=module&module=tropatt&controller=webhook`.
4. Add a cron job for `cron/sync_stock.php` if you want price/stock synchronisation.

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
