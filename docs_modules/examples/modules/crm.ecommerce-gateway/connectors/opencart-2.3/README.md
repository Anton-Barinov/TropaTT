# Модуль интеграции TropaTT CRM для OpenCart / ocStore 2.3.x

Официальный модуль двусторонней синхронизации заказов, статусов и остатков между
**OpenCart 2.3.0.x / ocStore 2.3.0.x** и TropaTT CRM (модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt-opencart-2.3.ocmod.zip`** (ocmod-архив для админки магазина).

---

## 1. Возможности

- **Заказы в CRM**: каждый заказ и каждое изменение статуса уходят в TropaTT CRM каноническим payload'ом E-COM-01
  (позиции, скидки, доставка, реквизиты, комментарий покупателя).
- **Обратная синхронизация статусов**: CRM присылает вебхук `order.status_changed` по подписи HMAC, модуль переводит
  заказ в статус по вашему маппингу. Смена статуса модулем не вызывает повторную отправку в CRM (Anti-Echo Loop).
- **Реквизиты юридических лиц**: ИНН, КПП, название организации, юридический адрес, банковские реквизиты
  из кастомных полей (включая поля **SimpleCheckout 4.x / 4.9.x**) уходят в первый класс-блок `billing_entity`.
- **Пакетная синхронизация остатков**: подписанный маршрут `syncStock` — постраничный снимок каталога (`mode=pull`)
  и применение партии остатков в одной транзакции с блокировкой строк (`SELECT ... FOR UPDATE`).
- **Ping-тест** соединения прямо из настроек модуля.
- **Совместимость с PHP 5.6 – 8.x** (без вендорных зависимостей, только cURL и OpenSSL).

## 2. Совместимость

| Компонент | Версия |
|---|---|
| OpenCart / ocStore | 2.3.0.0 – 2.3.0.2 (ветка 2.3.x) |
| PHP | 5.6 – 8.x |
| MySQL / MariaDB | 5.5+ (транзакционные таблицы — InnoDB) |
| Расширения PHP | `curl`, `hash` (HMAC), `json`, `mbstring` |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.1.0+ |

> Это отдельный коннектор: OpenCart 2.3 хранит шаблоны в `.tpl` (PHP), путь расширений — `extension/`,
> токен сессии — `token`, события регистрируются через `model('extension/event')`.
> Для OpenCart 3.0.x используйте `tropatt-opencart-3.ocmod.zip`, для 4.x — `tropatt-opencart-4.ocmod.zip`.

## 3. Схема обмена

```
   OpenCart 2.3.x                                   TropaTT CRM
   ─────────────                                    ───────────
   addOrderHistory() ── event ─┐
                               ├─ POST /_module/crm.ecommerce-gateway/v1/orders ──► Ingestion API ─► заявка/задача
   catalog/model/extension/module/tropatt.php (HMAC-SHA256, idempotency key)         (дедупликация, маппинг)

   extension/module/tropatt/webhook ◄── POST вебхук order.status_changed ─────────── CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        └─ addOrderHistory(новый статус)  (Anti-Echo Loop: событие гасится)

   extension/module/tropatt/syncStock ◄── подписанный запрос mode=pull|push ──────── сверка остатков
```

### 3.1. Как ловится изменение статуса заказа

OpenCart 2.3 сам генерирует событие вокруг каждого вызова метода модели
(`system/engine/loader.php` → `catalog/controller/startup/event.php`). Модуль при установке пишет в таблицу `oc_event`:

| code | trigger | action |
|---|---|---|
| `tropatt_order_history` | `catalog/model/checkout/order/addOrderHistory/after` | `extension/module/tropatt/onOrderHistoryAdd` |

Ядро магазина **не правится**, файл `install.xml` (OCMOD) — только аварийный путь для сборок,
где механизм событий выключен. Формально правило выбора такое:

| Ситуация | Что делать |
|---|---|
| Событие `tropatt_order_history` есть в **Расширения → События** | Ничего. `install.xml` включать не нужно |
| В сборке нет таблицы `oc_event` или событий моделей | Установите архив ещё раз через **Модификаторы → Обновить**, чтобы включить `install.xml` |

Одновременно держать оба механизма нельзя: каждый запрос уйдёт в CRM дважды.
Дубликаты не ломают данные (ключ идемпотентности `{store_key}:order:{order_id}` продлевает
существующую заявку при изменившемся теле и отбрасывает идентичный повтор), но нагрузка удваивается.

## 4. Установка

1. Скачайте `tropatt-opencart-2.3.ocmod.zip`.
2. Админка магазина → **Расширения → Установка расширений** (Installer) → загрузите архив.
3. **Расширения → Модификаторы** (Modifications) → кнопка **Обновить** (Refresh).
   > Нужно только если вы включаете `install.xml`. Если события работают — шаг можно пропустить.
4. **Расширения → Модули** → найдите **TropaTT CRM — Шлюз синхронизации** → **Установить**.
   При установке модуль регистрирует событие `tropatt_order_history`.
5. **Редактировать**: укажите URL шлюза, публичный ключ витрины (`stk_...`) и секрет, нажмите
   **Проверить соединение** (должен прийти `INGESTION_PONG`), затем сохраните.
6. Скопируйте **URL входящих вебхуков** из настроек модуля в карточку витрины в TropaTT CRM.

Витрина создаётся в CRM: **Шлюз интернет-магазинов → Витрины → Создать** (секрет показывается один раз).

## 5. Настройки модуля

| Настройка | Назначение |
|---|---|
| Статус модуля | Выключенный модуль не отправляет заказы и отвечает на вебхуки уведомлением `event ignored` |
| URL шлюза TropaTT | Префикс Ingestion API, например `https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1` |
| Публичный ключ витрины | `stk_...` из карточки витрины |
| Секретный ключ витрины | Секрет витрины (хранится в открытом виде в `oc_setting`, как и остальные настройки OpenCart) |
| URL входящих вебхуков | Вычисляется автоматически из URL магазина |
| URL синхронизации остатков | Вычисляется автоматически, маршрут `extension/module/tropatt/syncStock` |
| Статусы «оплачен» | Список статусов заказа, при которых заказ уходит с `paid = true` |
| Режим отладки | Пишет обмен в `system/storage/logs/tropatt.log` |
| Маппинг статусов | `ID статуса OpenCart → код стадии CRM` (`new`, `in_progress`, `completed`, `cancelled`, …) |

## 6. Контракт API

### 6.1. Исходящие запросы в CRM (магазин → CRM)

```
X-Store-Key: stk_...
X-TropaTT-Timestamp: 1789137342
X-TropaTT-Nonce: 9f2c8e4b...                     (32 hex)
X-TropaTT-Signature: base64(HMAC-SHA256(secret, canonical))
X-TropaTT-Idempotency-Key: {store_key}:order:{order_id}

canonical = METHOD \n path \n timestamp \n nonce \n hex(sha256(raw_body))
```

| Метод | Path | Тело |
|---|---|---|
| `GET` | `/_module/crm.ecommerce-gateway/v1/ping` | пустое; ответ `{"code":"INGESTION_PONG"}` |
| `POST` | `/_module/crm.ecommerce-gateway/v1/orders` | канонический payload заказа |

### 6.2. Входящие запросы из CRM (CRM → магазин)

| Маршрут | Заголовки | Назначение |
|---|---|---|
| `route=extension/module/tropatt/webhook` | `X-TropaTT-Event`, `X-TropaTT-Timestamp`, `X-TropaTT-Signature` | смена статуса заказа |
| `route=extension/module/tropatt/syncStock` | `X-TropaTT-Timestamp`, `X-TropaTT-Signature` | пакетная синхронизация остатков |

Подпись входящих запросов: `base64(HMAC-SHA256(secret, timestamp . '.' . raw_body))`, допуск по времени ±300 c,
сравнение за постоянное время. Админ-сессия (`token`) для этих маршрутов не нужна — витрина не пользователь CRM.

### 6.3. Маппинг полей заказа

| OpenCart 2.3 | Канонический payload |
|---|---|
| `order_id` | `external_id`, `payload.order_number` |
| `order_status_id` (из аргументов события) | `payload.order_status` |
| `date_added` | `payload.created_at_store` (UTC, ISO 8601) |
| `order_product` | `payload.items[]` (`name`, `sku` = `model`, `quantity`, `price`, `line_total`, `tax`) |
| `order_option` | `payload.items[].options[]` («Опция: значение») |
| `order_total.sub_total` | `payload.subtotal` |
| `order_total.shipping` | `payload.delivery_total` |
| `order_total.tax` | `payload.tax_total` |
| `order_total.coupon/voucher/reward/credit/discount` | `payload.discount_total` (сумма приводится к положительной: контракт запрещает отрицательный `amount_minor`) |
| `order.total` | `payload.total` |
| `comment` | `payload.comment` |
| `firstname`/`lastname` → `email` → `telephone` | `payload.customer.full_name` |
| `telephone`, `email` | `payload.customer.phone`, `.email` |
| `payment_method`, `shipping_method` | `payload.payment_method`, `.delivery_method` |
| `shipping_iso_code_2`, `shipping_zone`, `shipping_city`, `shipping_address_1`/`_2`, `shipping_postcode` | `payload.delivery_address.{country_code,region,city,street,house,postal_code}` |
| `payment_company` / кастомные поля «ИНН», «КПП», «организация», «юр. адрес», «р/с», «БИК», «банк» | `payload.billing_entity.{company_name,tax_id,kpp,legal_address,bank_details}` |
| `ip`, `payment_code`, `shipping_code`, `store_name`, прочие кастомные поля | `payload.attributes` |

Особенности реализации в этом коннекторе (в версиях для 3.0/4.0 иначе):

- денежные суммы масштабируются по `decimal_place` валюты магазина (`oc_currency`), поэтому валюты
  без копеек (JPY, KRW) уходят корректно, а вместе с суммой передаётся `currency_minor_unit`;
- `quantity` приводится к целому ≥ 1 (контракт требует целое количество и иначе отклоняет **весь** заказ с HTTP 400),
  а исходное дробное или нулевое значение уходит в опции строки как `opencart_quantity: <значение>`;
  исключение не выдумывается — потеря данных видна в карточке заявки;
- `paid` вычисляется по списку статусов «оплачен», а не жёстко `false`.

### 6.4. `syncStock` — пакетная синхронизация остатков

`mode=pull` (по умолчанию) — постраничный снимок каталога. `page`/`limit` (`limit` ≤ 200, по умолчанию 50).
Строки `COUNT(*)` и страница читаются **в одной транзакции**, поэтому параллельная запись остатков
не сдвигает выборку между запросами (InnoDB, REPEATABLE READ).

```json
{"success":true,"mode":"pull","page":1,"limit":50,"total":4321,"has_more":true,
 "products":[{"product_id":42,"sku":"ART-101","name":"Наушники Pro","quantity":7,
              "price":{"amount_minor":150000,"currency":"RUB","currency_minor_unit":2},
              "status":1,"updated_at":"2026-09-14 09:12:00"}]}
```

`mode=push` — применить партию остатков. Транзакция одна на запрос, каждая затронутая строка берётся
`SELECT ... FOR UPDATE`, поэтому параллельные партии не перемешиваются. `dry_run: true` проверяет
партию и откатывает транзакцию.

```json
{"mode":"push","page":1,"limit":100,
 "items":[{"sku":"ART-101","quantity":12},{"product_id":43,"quantity":0}]}
```

```json
{"success":true,"mode":"push","dry_run":false,"page":1,"limit":100,"total":2,"has_more":false,
 "summary":{"requested":2,"updated":1,"unchanged":0,"skipped":1},
 "results":[{"index":0,"product_id":42,"previous_quantity":7,"quantity":12,"result":"updated"},
            {"index":1,"result":"skipped","reason":"product_not_found","sku":""}]}
```

Результаты строк: `updated`, `would_update` (dry-run), `unchanged`, `skipped`
(`product_not_found`, `invalid_quantity`, `invalid_line`). Ошибка внутри партии откатывает её целиком.

## 7. Файлы

```
upload/admin/controller/extension/module/tropatt.php   настройки, install/uninstall события, ping-тест
upload/admin/view/template/extension/module/tropatt.tpl  интерфейс (OpenCart 2.3 — PHP-шаблоны .tpl)
upload/admin/language/{ru-ru,en-gb}/extension/module/tropatt.php
upload/catalog/controller/extension/module/tropatt.php  событие, вебхук CRM, syncStock
upload/catalog/model/extension/module/tropatt.php       сборка payload, подпись, пакеты остатков
install.xml                                            OCMOD-fallback (аварийный путь)
.github/workflows/lint.yml                             php -l по всему коду (PHP 5.6 / 7.4 / 8.2) + разбор install.xml
```

## 8. Сборка архива

```bash
cd connectors/opencart-2.3
zip -r -X ../dist/tropatt-opencart-2.3.ocmod.zip upload install.xml README.md LICENSE
```

Проверка синтаксиса и содержимого архива:

```bash
for f in $(find upload -name '*.php'); do php -l "$f"; done
unzip -l ../dist/tropatt-opencart-2.3.ocmod.zip
```

## 9. Диагностика

| Симптом | Причина и решение |
|---|---|
| Заказы не уходят, в логе пусто | Модуль выключен либо событие удалено: проверьте **Расширения → События** на строку `tropatt_order_history` и **Модуль статус = Включено** |
| `401 Missing authentication headers` / `Invalid cryptographic signature` | Не совпадает секрет витрины, либо подпись считается по изменённому телу (прокси/модификатор меняет запрос) |
| `401 Timestamp out of tolerance window` | Часы магазина или сервера CRM ушли больше чем на 5 минут |
| `409 conflict` / заказ помечен как дубликат | Один `external_id` пришёл с другим телом, а стратегия витрины в CRM — `reject`; для реактивной синхронизации статусов нужна стратегия `merge` |
| Заказ не переводится в новый статус по вебхуку | Пустой маппинг статусов, либо `external_status` не числовой и `new_status` не совпал ни с одним кодом стадии |
| Статус переводится, но заказ не уходит обратно | Так и задумано: смена статуса модулем гасится флагом Anti-Echo Loop |
| `syncStock` отвечает `product_not_found` | В `oc_product.model` нет такого SKU — сверяйте `sku` из `mode=pull` |
| После включения `install.xml` заказы уходят дважды | Одновременно активны событие и OCMOD-модификация — оставьте один механизм |

---

# TropaTT CRM connector for OpenCart / ocStore 2.3.x (EN)

Officially supported two-way synchronisation of orders, statuses and stock between
**OpenCart 2.3.0.x / ocStore 2.3.0.x** and TropaTT CRM (`crm.ecommerce-gateway`).
Distributed as `tropatt-opencart-2.3.ocmod.zip`.

## Highlights

- Every order and every order-status change is pushed to the CRM as the canonical E-COM-01 payload
  (line items, options, discounts, delivery, legal requisites, customer comment).
- Reverse status sync: the CRM posts an HMAC-signed `order.status_changed` webhook and the module moves the
  OpenCart order to the mapped status. The module's own status write is suppressed (anti-echo loop).
- Legal requisites (INN, KPP, company name, legal address, bank details) are recognised in OpenCart custom
  fields — including **SimpleCheckout 4.x / 4.9.x** fields — and mapped to the first-class `billing_entity` block.
- Signed batch stock endpoint `route=extension/module/tropatt/syncStock`: `mode=pull` (paginated catalogue
  snapshot read in one transaction) and `mode=push` (a batch applied inside one transaction with row locks).
- Connection Ping test in the module settings; PHP 5.6 – 8.x; no vendor dependencies.

## Install

1. **Extensions → Installer** → upload `tropatt-opencart-2.3.ocmod.zip`.
2. **Extensions → Modifications → Refresh** — only if you enable `install.xml` (see below).
3. **Extensions → Modules** → **TropaTT CRM — Sync Gateway** → **Install** (registers the event
   `tropatt_order_history`).
4. **Edit**: set the gateway URL, `stk_...` store key and secret, run **Test Connection** (`INGESTION_PONG`),
   save, then copy the inbound webhook URL into the store card in TropaTT CRM.

## Status-change hook

OpenCart 2.3 triggers events around every model method call, so the connector installs the row
`tropatt_order_history` (`catalog/model/checkout/order/addOrderHistory/after` →
`extension/module/tropatt/onOrderHistoryAdd`) and needs no core file edits. `install.xml` is the fallback
for builds without the event mechanism: enable **either** the event row **or** the OCMOD modification,
never both.

## Order field mapping and the stock contract

See sections 6.3 and 6.4 above (identical in both languages, kept in one place on purpose).

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
