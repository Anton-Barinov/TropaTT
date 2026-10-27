# Коннектор TropaTT CRM для Moguta.CMS

Плагин **TropaTT CRM** для **Moguta.CMS**: двусторонняя синхронизация заказов, покупателей и статусов с
TropaTT CRM (модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt-moguta.zip`** — архив с каталогом `mg-plugins/`, который распаковывается в корень
магазина (плагин ставится в `mg-plugins/tropatt/`).

## 1. Возможности

- **Заказы в CRM**: документированные хуки движка `models_order_addorder` и `models_order_updateorder`
  (события `Models_Order::addOrder()` / `Models_Order::updateOrder()`) — заказ уходит в CRM подписанным запросом
  (HMAC-SHA256) с идемпотентностью `{store_key}:order:{order_id}`.
- **Магазин не тормозит**: запрос к CRM ограничен **таймаутом < 3 с** (настраивается, но жёстко ограничен
  тремя секундами), а при сбое заказ складывается в локальную очередь — оформление заказа никогда не падает.
  Очередь дренируется автоматически перед следующим событием магазина (неудачные попытки считаются в имени
  файла, после пяти — `<queue_dir>/failed/`).
  из-за недоступного CRM.
- **Разбор корзины Moguta**: `order_content` читается и как **JSON**, и как **serialized**-массив (а также
  готовым массивом), позиции передаются с количеством, ценой, суммой, артикулом (`code`/`sku`) и вариантами
  (`variants`/`properties`); дополнительные поля (`add_fields`) уходят в `custom_fields`.
- **Обратная синхронизация статусов**: эндпоинт `index.php?ajax=tropattWebhook` проверяет подпись HMAC и окно
  ±300 с, применяет статус через `Models_Order::updateOrder()` и **подавляет эхо** (глобальный флаг
  `tropatt_crm_skip_status_sync`).
- **Настройки в админке** (`pagePlugin.php`): включение, URL шлюза, ключ/секрет витрины, секрет вебхука,
  стадия по умолчанию, таблица маппинга статусов, отладка и готовый URL вебхука.

## 2. Совместимость

| Компонент | Версия |
|---|---|
| Moguta.CMS | 7.x – 10.x |
| PHP | 7.4 – 8.2 |
| Расширения PHP | `curl`, `hash` (HMAC), `json` |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

## 3. Установка

1. Распакуйте архив в корень магазина так, чтобы плагин оказался в `mg-plugins/tropatt/`.
   Точка входа плагина — `mg-plugins/tropatt/index.php`: Moguta распознаёт плагин только по этому имени файла
   ([документация](https://wiki.moguta.ru/devhelp/plugins/kak-sdelat-plagin)), папка должна состоять только из
   строчных букв.
2. В админке Moguta откройте **Плагины** — плагин «TropaTT CRM — шлюз интернет-магазина» появится в списке;
   включите его.
3. Перейдите в настройки плагина и заполните: URL шлюза, публичный ключ витрины (`stk_...`), секрет витрины,
   секрет вебхука, стадию CRM по умолчанию и маппинг «статус Moguta = стадия CRM» (по одному в строке).
4. Скопируйте URL вебхука из настроек в карточку витрины в TropaTT CRM
   (`https://shop.example.com/index.php?ajax=tropattWebhook`).

## 4. Схема обмена

```
  Moguta.CMS                                     TropaTT CRM
  ──────────                                     ───────────
  models_order_addorder / models_order_updateorder
        (события Models_Order::addOrder() / updateOrder())
        │  order_content (JSON/serialized) → canonical E-COM-01
        ▼
  CrmClient (timeout < 3 c) ──► POST /_module/crm.ecommerce-gateway/v1/orders (HMAC-SHA256)
        │  при ошибке/таймауте — файловая очередь
        ▼
  index.php?ajax=tropattWebhook ◄── POST order.status_changed ─── CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        ▼
  Models_Order::updateOrder()  (anti-echo: $GLOBALS['tropatt_crm_skip_status_sync'])
```

## 5. Файлы

```
mg-plugins/tropatt/index.php          точка входа плагина (имя обязательно), хуки, отправка заказа
mg-plugins/tropatt/pagePlugin.php     страница настроек в админке Moguta
mg-plugins/tropatt/ajax.php           приём вебхуков CRM (?ajax=tropattWebhook)
mg-plugins/tropatt/lib/Config.php     настройки (mgGetSetting) и лимит таймаута
mg-plugins/tropatt/lib/OrderMapper.php  заказ Moguta → канонический payload E-COM-01
mg-plugins/tropatt/lib/StatusMapper.php маппинг статусов Moguta ↔ стадии CRM
mg-plugins/tropatt/lib/CrmClient.php  подписанный клиент шлюза (таймаут ≤ 3 с)
mg-plugins/tropatt/lib/SignatureValidator.php  HMAC-подпись и constant-time сравнение
mg-plugins/tropatt/lib/FileQueue.php  локальная очередь недоставленных заказов
mg-plugins/tropatt/lib/ConnectorLogger.php  журнал с маскированием секретов
.github/workflows/lint.yml            php -l (7.4–8.2) и проверка манифеста плагина
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-moguta.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Заказы не уходят в CRM | Плагин включён, настройки заполнены; включите отладку и посмотрите журнал |
| В CRM нет заказа | Проверьте URL шлюза/ключ/секрет; заказ мог попасть в очередь — он переотправится автоматически перед следующим событием магазина, а после пяти неудач ляжет в `<queue_dir>/failed/` |
| Вебхук отвечает 401 | `tropatt_webhook_secret` должен совпадать с секретом витрины в CRM; проверьте время сервера (±300 с) |
| Вебхук отвечает 404 | Заказ не найден по `external_order_id` |
| Статус в CRM меняется, а в Moguta нет | В маппинге нет пары для стадии CRM или указан несуществующий статус Moguta |
| Оформление заказа «зависает» | Проверьте доступность шлюза: таймаут ограничен 3 секундами, при недоступности CRM заказ уходит в очередь |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for Moguta.CMS (EN)

A plugin (`mg-plugins/tropatt/`) that synchronises orders, customers and statuses between **Moguta.CMS** and the
TropaTT CRM e-commerce gateway. Orders are hooked on the documented engine events
`models_order_addorder` / `models_order_updateorder` (`Models_Order::addOrder()` / `updateOrder()`), mapped from the
`order_content` (JSON or serialized) into the canonical payload and pushed with a **sub-3-second** signed
request; a failure is spooled locally. The CRM webhook (`index.php?ajax=tropattWebhook`) applies status changes
with echo suppression.

## Install

1. Unpack the archive into the shop root so the plugin lands in `mg-plugins/tropatt/`.
2. Enable the plugin in the Moguta admin and fill in the gateway URL, store key/secret and the webhook secret.
3. Map the Moguta statuses to CRM stages and copy the webhook URL into the store card in TropaTT CRM.

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
