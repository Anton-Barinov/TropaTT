# Коннектор TropaTT CRM для Webasyst / Shop-Script

Плагин **TropaTT CRM** для **Shop-Script (Webasyst 7–10)**: двусторонняя синхронизация заказов, покупателей
и статусов с TropaTT CRM (модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt-webasyst.zip`** — архив с каталогом `wa-apps/`, который распаковывается в корень
Webasyst (плагин ставится в `wa-apps/shop/plugins/tropatt/`).

## 1. Возможности

- **Заказы в CRM**: обработчики `order_action.create` (новый заказ) и `order_action.*` (любое действие
  workflow: оплата, отправка, смена состояния) — заказ уходит в CRM подписанным запросом (HMAC-SHA256) с
  идемпотентностью `{store_key}:order:{order_id}`.
- **Данные заказа**: `shopOrderModel::getOrder()` (позиции с `sku`/`sku_options`, суммы), покупатель через
  `waContact` (имя, телефон, e-mail), а также **параметры заказа и UTM-метки из `shop_order_params`** —
  всё уходит в `custom_fields` (`webasyst_param_*`).
- **Магазин не тормозит**: таймаут запроса к CRM ограничен 3 секундами, при сбое заказ складывается в
  локальную очередь, которая дренируется перед следующим событием магазина (после пяти неудач payload уезжает
  в `<queue_dir>/failed/`).
- **Обратная синхронизация статусов**: маршрут `/tropatt/webhook/`
  (`shopTropattPluginFrontendWebhookAction`) проверяет подпись HMAC и окно ±300 с, переводит заказ
  **только через `shopWorkflow::getAction($id)->run($order_id)`** и подавляет эхо флагом в `waStorage`
  (`tropatt/skip_status_sync`), поэтому смена состояния не уходит обратно в CRM.
- **Настройки в админке**: включение, URL шлюза, ключ/секрет витрины, секрет вебхука, стадия CRM по
  умолчанию, таблица маппинга «состояние Shop-Script = стадия CRM», отладка.

## 2. Совместимость

| Компонент | Версия |
|---|---|
| Webasyst / Shop-Script | 7.x – 10.x |
| PHP | 7.4 – 8.2 |
| Расширения PHP | `curl`, `hash` (HMAC), `json` |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

## 3. Установка

1. Распакуйте архив в корень Webasyst так, чтобы плагин оказался в `wa-apps/shop/plugins/tropatt/`.
2. В админке Shop-Script: **Магазин → Настройки → Плагины** → у плагина «TropaTT CRM» нажмите
   **Установить**, затем откройте настройки и заполните поля (URL шлюза, ключ `stk_...`, секрет витрины,
   секрет вебхука, стадия по умолчанию, маппинг состояний).
3. Проверьте маршрут вебхука: `https://shop.example.com/tropatt/webhook/` (маршрут добавляется плагином —
   для этого в `lib/config/plugin.php` объявлен флаг `'frontend' => true`, без него Webasyst не регистрирует
   frontend-маршруты плагина и вебхук отвечает 404; при необходимости очистите кэш Webasyst).
4. Укажите этот URL в карточке витрины в TropaTT CRM.

## 4. Схема обмена

```
  Shop-Script (Webasyst)                          TropaTT CRM
  ─────────────────────                          ───────────
  order_action.create / order_action.*
        │  shopOrderModel + waContact + shop_order_params (UTM)
        ▼
  shopTropattClient (timeout < 3 c) ──► POST /_module/crm.ecommerce-gateway/v1/orders (HMAC-SHA256)
        │  при ошибке — файловая очередь
        ▼
  /tropatt/webhook/ ◄── POST order.status_changed ─── CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        ▼
  shopWorkflow::getAction()->run()  (anti-echo: waStorage 'tropatt/skip_status_sync')
```

## 5. Файлы

```
wa-apps/shop/plugins/tropatt/lib/config/plugin.php     манифест, флаг frontend и обработчики order_action.*
wa-apps/shop/plugins/tropatt/img/tropatt.png           иконка плагина (48×48), объявлена в манифесте
wa-apps/shop/plugins/tropatt/lib/config/settings.php   схема настроек плагина
wa-apps/shop/plugins/tropatt/lib/config/routing.php    маршрут /tropatt/webhook/
wa-apps/shop/plugins/tropatt/lib/shopTropatt.plugin.php  класс плагина (сбор и отправка заказа)
wa-apps/shop/plugins/tropatt/lib/actions/shopTropattPluginFrontendWebhook.action.php  вебхук CRM
wa-apps/shop/plugins/tropatt/lib/classes/tropattOrderMapper.class.php  заказ Shop-Script → payload
wa-apps/shop/plugins/tropatt/lib/classes/tropattStatusMapper.class.php маппинг состояний
wa-apps/shop/plugins/tropatt/lib/classes/tropattClient.class.php       подписанный клиент шлюза
wa-apps/shop/plugins/tropatt/lib/classes/tropattSignature.class.php    HMAC и constant-time сравнение
wa-apps/shop/plugins/tropatt/lib/classes/tropattFileQueue.class.php    локальная очередь
wa-apps/shop/plugins/tropatt/lib/classes/tropattLogger.class.php       журнал с маскированием секретов
wa-apps/shop/plugins/tropatt/locale/{ru_RU,en_US}/LC_MESSAGES/shop_tropatt.po  локализация
.github/workflows/lint.yml                             php -l (7.4–8.2) и проверка манифеста
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-webasyst.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Заказы не уходят в CRM | Плагин включён, настройки заполнены; включите отладку и посмотрите журнал плагина |
| В CRM нет заказа | Проверьте URL шлюза/ключ/секрет; заказ мог попасть в очередь (`wa()->getDataPath('queue')`) — он переотправится автоматически перед следующим событием магазина, после пяти неудач ляжет в `<queue>/failed/` |
| Вебхук отвечает 401 | `webhook_secret` должен совпадать с секретом витрины в CRM; проверьте время сервера (±300 с) |
| Вебхук отвечает 404 | Маршрут `/tropatt/webhook/` не найден: очистите кэш Webasyst, проверьте `routing.php` и флаг `'frontend' => true` в `lib/config/plugin.php` |
| Вебхук отвечает 422 | В маппинге нет пары для стадии CRM либо указано несуществующее действие workflow |
| Статус меняется в CRM, а в Shop-Script нет | Проверьте маппинг и права плагина; состояние меняется только через `shopWorkflow` |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for Webasyst / Shop-Script (EN)

A plugin (`wa-apps/shop/plugins/tropatt/`) that synchronises orders, customers and statuses between
**Shop-Script (Webasyst 7–10)** and the TropaTT CRM e-commerce gateway. Orders are handled on
`order_action.create` / `order_action.*`, collected through `shopOrderModel` + `waContact` (including custom
parameters and UTM marks from `shop_order_params`) and pushed with a sub-3-second signed request; failures are
spooled locally. The CRM webhook (`/tropatt/webhook/`) applies status changes exclusively through
`shopWorkflow::getAction()->run()` with an anti-echo flag in `waStorage`.

## Install

1. Unpack the archive into the Webasyst root so the plugin lands in `wa-apps/shop/plugins/tropatt/`.
2. In Shop-Script: **Shop → Settings → Plugins** → install *TropaTT CRM*, then fill in the settings.
3. Point the store webhook in TropaTT CRM at `https://shop.example.com/tropatt/webhook/` (the route is registered
   by the plugin through the `'frontend' => true` flag in `lib/config/plugin.php`; without it the webhook answers 404).

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
