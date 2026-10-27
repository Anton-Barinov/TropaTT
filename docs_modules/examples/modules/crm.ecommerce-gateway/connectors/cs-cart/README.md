# Коннектор TropaTT CRM для CS-Cart и Multi-Vendor

Аддон `tropatt` для **CS-Cart 4.x** (включая **Multi-Vendor** и **Ultimate**): двусторонняя синхронизация
заказов, покупателей и статусов с TropaTT CRM (модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt-cs-cart.zip`** — архив с каталогом `app/`, который распаковывается в корень
магазина (аддон ставится в `app/addons/tropatt/`).

## 1. Возможности

- **Заказы в CRM**: хуки `place_order`, `change_order_status`, `update_order` — заказ уходит в CRM
  подписанным запросом (HMAC-SHA256), деньги переводятся в минорные единицы, позиции передаются с
  артикулами и опциями (`product_options`).
- **Полный маппинг**: доставка и способ оплаты, адрес (`s_address`/`b_address`), налоги (`taxes`/
  `tax_total`), купоны, витрина (`storefront_id`), комментарий менеджера (`notes`).
- **Multi-Vendor**: при включённой опции `split_by_vendor` заказ разделяется по вендорам (`company_id` в
  позициях) и отправляется отдельными заявками с `external_id = <order_id>-<vendor_id>`; в `custom_fields`
  уходит `cs_cart_vendor_id`.
- **Обратная синхронизация статусов**: вебхук CRM `index.php?dispatch=tropatt.status_update` (алиас `tropatt.webhook`) проверяет подпись
  HMAC и окно ±300 с, применяет статус CS-Cart по маппингу и **подавляет эхо**
  (`Registry::set('runtime.tropatt_skip_status_sync', true)`) — смена статуса не уходит обратно в CRM.
- **Заказы не теряются**: если шлюз недоступен, канонический payload складывается в локальную очередь
  (`queue_dir`) и **переотправляется автоматически** — очередь дренируется перед следующим событием
  магазина (новый заказ или смена статуса). Неудачные попытки считаются прямо в имени файла, а после
  пяти неудач payload уезжает в `<queue_dir>/failed/`, чтобы не крутиться вечно и не теряться.
- **Настройки в админке**: включение, URL шлюза, ключ/секрет витрины, секрет вебхука, стадия по умолчанию,
  разделение по вендорам, отладка и таблица маппинга статусов (`CS-Cart статус = стадия CRM`).

## 2. Совместимость

| Компонент | Версия |
|---|---|
| CS-Cart / Multi-Vendor | 4.11+ (схема аддонов 3.0), редакции MULTIVENDOR и ULTIMATE |
| PHP | 7.4 – 8.2 |
| Расширения PHP | `curl`, `hash` (HMAC), `json` |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

## 3. Установка

1. Распакуйте архив в корень магазина так, чтобы аддон оказался в `app/addons/tropatt/`.
2. В админке: **Администрирование → Аддоны** → найдите «TropaTT CRM — e-commerce gateway» → **Установить**
   (при необходимости нажмите «Обновить кэш»/очистите кэш шаблонов).
3. Откройте настройки аддона и заполните: URL шлюза, публичный ключ витрины (`stk_...`), секрет витрины,
   секрет вебхука (он же указывается в CRM для подписи вебхуков), стадию по умолчанию, при необходимости —
   разделение заказов по вендорам.
4. На вкладке маппинга укажите соответствия «статус CS-Cart = код стадии CRM» (по одному в строке).
5. В карточке витрины в TropaTT CRM укажите URL вебхука:
   `https://shop.example.com/index.php?dispatch=tropatt.status_update`.

## 4. Схема обмена

```
  CS-Cart                                        TropaTT CRM
  ───────                                        ───────────
  place_order / change_order_status / update_order
        │  fn_get_order_info() → canonical E-COM-01
        ▼
  CrmClient ──► POST /_module/crm.ecommerce-gateway/v1/orders (HMAC-SHA256, idempotency key)
        │  при ошибке — файловая очередь
        ▼
  index.php?dispatch=tropatt.webhook ◄── POST order.status_changed ─── CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        ▼
  fn_change_order_status(...)  (anti-echo: Registry::set('runtime.tropatt_skip_status_sync', true))
```

## 5. Файлы

```
app/addons/tropatt/addon.xml                    манифест аддона и настройки
app/addons/tropatt/init.php                     регистрация хуков и автозагрузка
app/addons/tropatt/func.php                     обработчики хуков (очередь и отправка заказа)
app/addons/tropatt/controllers/frontend/status_update.php  вебхук CRM (dispatch=tropatt.status_update)
app/addons/tropatt/controllers/frontend/webhook.php        алиас (dispatch=tropatt.webhook)
app/addons/tropatt/lib/Config.php               настройки аддона (из реестра или массива)
app/addons/tropatt/lib/OrderMapper.php          заказ CS-Cart → канонический payload, разделение по вендорам
app/addons/tropatt/lib/StatusMapper.php         маппинг статусов CS-Cart ↔ стадии CRM
app/addons/tropatt/lib/CrmClient.php            подписанный клиент шлюза
app/addons/tropatt/lib/SignatureValidator.php   HMAC-подпись и constant-time сравнение
app/addons/tropatt/lib/FileQueue.php            локальная очередь недоставленных заказов
app/addons/tropatt/lib/ConnectorLogger.php      журнал с маскированием секретов
app/addons/tropatt/var/langs/{ru,en}/addons.po  языковые файлы
.github/workflows/lint.yml                      php -l (7.4–8.2) и проверка addon.xml
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-cs-cart.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Заказы не уходят в CRM | Аддон включён, настройки заполнены, в `var/log/`/журнале аддона нет ошибок; включите отладку |
| В CRM нет заказа | Проверьте URL шлюза/ключ/секрет; заказ мог попасть в `queue_dir` — он уйдёт в CRM при следующем событии магазина; застрявшие лежат в `<queue_dir>/failed/` |
| Вебхук отвечает 401 | `webhook_secret` в настройках аддона должен совпадать с секретом витрины в CRM; проверьте время сервера (±300 с) |
| Вебхук отвечает 404 | Заказ не найден: проверьте `external_order_id` (для Multi-Vendor это `<order_id>-<vendor_id>`) |
| Статус меняется в CRM, но не в CS-Cart | В маппинге нет пары для стадии CRM или указан несуществующий статус CS-Cart |
| Дубли заявок в Multi-Vendor | Включите `split_by_vendor` — иначе позиции разных вендоров уходят одной заявкой |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for CS-Cart / Multi-Vendor (EN)

An addon (`app/addons/tropatt/`) that synchronises orders, customers and statuses between **CS-Cart 4.x**
(including **Multi-Vendor** and **Ultimate**) and the TropaTT CRM e-commerce gateway. Orders are pushed with
HMAC-SHA256 signing on `place_order` / `change_order_status` / `update_order`, optionally split per vendor
(`company_id`), and the CRM webhook (`dispatch=tropatt.status_update`) applies status changes with echo suppression.

## Install

1. Unpack the archive into the shop root so the addon lands in `app/addons/tropatt/`.
2. **Administration → Add-ons** → install *TropaTT CRM — e-commerce gateway*.
3. Fill in the gateway URL, store key/secret and the webhook secret, then map the CS-Cart statuses to CRM stages.
4. Point the store webhook in TropaTT CRM at `https://shop.example.com/index.php?dispatch=tropatt.status_update`.

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
