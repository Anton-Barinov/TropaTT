# Модуль интеграции TropaTT CRM для OpenCart / ocStore 1.5.x

Официальный модуль двусторонней синхронизации заказов, статусов и остатков между
**OpenCart 1.5.x / ocStore 1.5.x** и TropaTT CRM (модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt-opencart-1.5.ocmod.zip`** — zip-архив с полезной нагрузкой `upload/`
(контроллеры, модель, шаблон, языковые файлы) и vQmod-модификатором `vqmod/xml/tropatt.xml`.

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
- **Совместимость с PHP 5.3 – 5.6** (без вендорных зависимостей, только cURL и OpenSSL; для PHP < 7.0 есть fallback для `random_bytes`, для PHP < 5.6 — для `hash_equals`).

## 2. Совместимость

| Компонент | Версия |
|---|---|
| OpenCart / ocStore | 1.5.4.x – 1.5.6.4 / ocStore 1.5.5.1.x (ветка 1.5.x) |
| PHP | 5.3 – 5.6 |
| MySQL / MariaDB | 5.5+ (транзакционные таблицы — InnoDB) |
| Расширения PHP | `curl`, `hash` (HMAC), `json`, `mbstring` |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.1.0+ |

> Это отдельный коннектор: в OpenCart 1.5 нет ни OCMOD (появился в 2.0), ни таблицы событий `oc_event`
> (появилась в 2.3), поэтому хук ставится **vQmod**-модификатором `vqmod/xml/tropatt.xml`; шаблоны — `.tpl` (PHP),
> пути расширений — `module/` (без префикса `extension/`), токен сессии — `token`.
> Для 2.0–2.2 используйте `tropatt-opencart-2.0-2.2.ocmod.zip`, для 2.3.x — `tropatt-opencart-2.3.ocmod.zip`, для 3.0.x —
> `tropatt-opencart-3.ocmod.zip`, для 4.x — `tropatt-opencart-4.ocmod.zip`.

## 3. Схема обмена

```
   OpenCart 1.5                                 TropaTT CRM
   ────────────────                                 ───────────
   confirm() / update() ── vQmod hook ─┐
                               ├─ POST /_module/crm.ecommerce-gateway/v1/orders ──► Ingestion API ─► заявка/задача
   catalog/model/module/tropatt.php (HMAC-SHA256, idempotency key)         (дедупликация, маппинг)

   module/tropatt/webhook ◄── POST вебхук order.status_changed ─────────── CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        └─ update(новый статус)  (Anti-Echo Loop: флаг в модели гасит push)

   module/tropatt/syncStock ◄── подписанный запрос mode=pull|push ──────── сверка остатков
```

### 3.1. Как ловится изменение статуса заказа

В OpenCart 1.5 нет механизма событий модели, поэтому хук ставит **vQmod**: файл `vqmod/xml/tropatt.xml`
вставляет вызов `handleOrderStatusChange($order_id, $order_status_id)` сразу после сигнатуры **двух** методов
`catalog/model/checkout/order.php`, которые меняют статус заказа в витрине 1.5.6.4:

```
	public function confirm($order_id, $order_status_id, $comment = '', $notify = false)   // строка 159
	public function update($order_id, $order_status_id, $comment = '', $notify = false)    // строка 583
```

Метода `addOrderHistory()` в этом файле нет — он существует только в админской модели OpenCart 2.x
с другой сигнатурой (`addOrderHistory($order_id, $data)`), поэтому прежняя строка поиска не совпадала,
модификация молча не применялась и заказы из 1.5 в CRM не уходили. Исходники OpenCart при этом не правятся —
vQmod работает через свою кэшированную копию файлов.

| Файл | Что делает |
|---|---|
| `vqmod/xml/tropatt.xml` | вставляет хук в `confirm()` и `update()` (обязателен для 1.5.x) |
| `upload/catalog/model/module/tropatt.php` | собирает payload и отправляет его в CRM |
| `upload/catalog/controller/module/tropatt.php` | принимает вебхуки статусов и `syncStock` |

| Ситуация | Что делать |
|---|---|
| Файл `vqmod/xml/tropatt.xml` лежит в магазине, в `vqmod/vqcache/` есть `vq2-catalog_model_checkout_order.php` | Хук установлен |
| В `vqmod/vqcache/` нет файла с `tropatt` | Убедитесь, что vQmod установлен, откройте витрину и админку (vQmod пересоберёт кэш) |

Повторная отправка не создаёт дублей: ключ идемпотентности `{store_key}:order:{order_id}` продлевает
существующую заявку при изменившемся теле и отбрасывает идентичный повтор.

## 4. Установка

1. Скачайте `tropatt-opencart-1.5.ocmod.zip` и распакуйте его в корень магазина (FTP/файловый менеджер
   хостинга): каталог `upload/` кладётся поверх корня OpenCart, `vqmod/xml/tropatt.xml` — в `vqmod/xml/`.
   Если vQmod ещё не установлен, поставьте его (это стандартное требование для модификаций OpenCart 1.5).
2. Откройте витрину и админку по одному разу, чтобы vQmod пересобрал кэш (`vqmod/vqcache/`).
3. **Расширения → Модули** → найдите **TropaTT CRM — Шлюз синхронизации** → **Установить**.
   Метод `install()` в этой версии пустой: вся установка — это vQmod-модификатор выше.
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
| URL синхронизации остатков | Вычисляется автоматически, маршрут `module/tropatt/syncStock` |
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
| `route=module/tropatt/webhook` | `X-TropaTT-Event`, `X-TropaTT-Timestamp`, `X-TropaTT-Signature` | смена статуса заказа |
| `route=module/tropatt/syncStock` | `X-TropaTT-Timestamp`, `X-TropaTT-Signature` | пакетная синхронизация остатков |

Подпись входящих запросов: `base64(HMAC-SHA256(secret, timestamp . '.' . raw_body))`, допуск по времени ±300 c,
сравнение за постоянное время. Админ-сессия (`token`) для этих маршрутов не нужна — витрина не пользователь CRM.

### 6.3. Маппинг полей заказа

| OpenCart 1.5 | Канонический payload |
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
upload/admin/controller/module/tropatt.php            настройки, ping-тест (install/uninstall пустые — хук ставит vQmod)
upload/admin/view/template/module/tropatt.tpl         интерфейс (OpenCart 1.5 — PHP-шаблоны .tpl, Bootstrap 3)
upload/admin/language/{russian,english,ru-ru,en-gb}/module/tropatt.php  языковые файлы (1.5.x — russian/english)
upload/catalog/controller/module/tropatt.php          вебхук CRM, syncStock, альтернатива vQmod (fallbackOrderHistory)
upload/catalog/model/module/tropatt.php               сборка payload, подпись, пакеты остатков, echo-флаг
vqmod/xml/tropatt.xml                                 vQmod-модификатор: вставляет хук в confirm() и update() (обязателен)
.github/workflows/lint.yml                             php -l по всему коду (PHP 5.6 / 7.4 / 8.2) + разбор vqmod/xml/tropatt.xml
```

## 8. Сборка архива

```bash
cd connectors/opencart-1.5
zip -r -X ../dist/tropatt-opencart-1.5.ocmod.zip upload vqmod README.md LICENSE
```

Проверка синтаксиса и содержимого архива:

```bash
for f in $(find upload -name '*.php'); do php -l "$f"; done
unzip -l ../dist/tropatt-opencart-1.5.ocmod.zip
```

## 9. Диагностика

| Симптом | Причина и решение |
|---|---|
| Заказы не уходят, в логе пусто | Модуль выключен либо vQmod-модификатор не применён: убедитесь, что vQmod установлен, `vqmod/xml/tropatt.xml` скопирован и в `vqmod/vqcache/` появился файл `vq2-catalog_model_checkout_order.php` со строкой `handleOrderStatusChange`, затем проверьте, что **Статус модуля = Включён** |
| `401 Missing authentication headers` / `Invalid cryptographic signature` | Не совпадает секрет витрины, либо подпись считается по изменённому телу (прокси/модификатор меняет запрос) |
| `401 Timestamp out of tolerance window` | Часы магазина или сервера CRM ушли больше чем на 5 минут |
| `409 conflict` / заказ помечен как дубликат | Один `external_id` пришёл с другим телом, а стратегия витрины в CRM — `reject`; для реактивной синхронизации статусов нужна стратегия `merge` |
| Заказ не переводится в новый статус по вебхуку | Пустой маппинг статусов, либо `external_status` не числовой и `new_status` не совпал ни с одним кодом стадии |
| Статус переводится, но заказ не уходит обратно | Так и задумано: смена статуса модулем гасится флагом Anti-Echo Loop |
| `syncStock` отвечает `product_not_found` | В `oc_product.model` нет такого SKU — сверяйте `sku` из `mode=pull` |
| Заказы уходят дважды | Одновременно применён vQmod-модификатор и ручная правка `catalog/model/checkout/order.php` из раздела «Альтернатива vQmod» — оставьте один механизм |

---

# TropaTT CRM connector for OpenCart / ocStore 1.5.x (EN)

Officially supported two-way synchronisation of orders, statuses and stock between
**OpenCart / ocStore 1.5.x** and TropaTT CRM (`crm.ecommerce-gateway`).
Distributed as `tropatt-opencart-1.5.ocmod.zip`.

## Highlights

- Every order and every order-status change is pushed to the CRM as the canonical E-COM-01 payload
  (line items, options, discounts, delivery, legal requisites, customer comment).
- Reverse status sync: the CRM posts an HMAC-signed `order.status_changed` webhook and the module moves the
  OpenCart order to the mapped status. The module's own status write is suppressed (anti-echo loop).
- Legal requisites (INN, KPP, company name, legal address, bank details) are recognised in OpenCart custom
  fields — including **SimpleCheckout 4.x / 4.9.x** fields — and mapped to the first-class `billing_entity` block.
- Signed batch stock endpoint `route=module/tropatt/syncStock`: `mode=pull` (paginated catalogue
  snapshot read in one transaction) and `mode=push` (a batch applied inside one transaction with row locks).
- Connection Ping test in the module settings; PHP 5.3 – 5.6; no vendor dependencies.

## Install

1. Make sure **vQmod** is installed (`vqmod/vqmod.php` is included from `index.php` and `admin/index.php`).
2. Unpack the archive into the shop root and copy `vqmod/xml/tropatt.xml` into `vqmod/xml/`.
3. Open the storefront and the admin once so vQmod rebuilds its cache, then install the module under
   **Extensions → Modules → TropaTT CRM — Sync Gateway**.
4. **Edit**: set the gateway URL, `stk_...` store key and secret, run **Test Connection** (`INGESTION_PONG`),
   save, then copy the inbound webhook URL into the store card in TropaTT CRM.

## Status-change hook

OpenCart 1.5 has no model event table and no OCMOD, so the hook is installed by vQmod: `vqmod/xml/tropatt.xml`
inserts `handleOrderStatusChange($order_id, $order_status_id)` right after the signature of both status-writing
methods of `catalog/model/checkout/order.php` — `confirm()` (line 159 of the official 1.5.6.4 source) and
`update()` (line 583). The storefront sources are never edited; vQmod works on its own cached copy, and
`vqmod/vqcache/vq2-catalog_model_checkout_order.php` must contain the injected call.

`ModelModuleTropatt::$suppress_echo` (switched by the webhook through `suppressEcho()`) stops the push while the
module itself is writing the status the CRM just sent, so a CRM status change is never echoed straight back.
On installs where vQmod cannot be used, `module/tropatt/fallbackOrderHistory` is the supported manual
replacement for the injected call.

## Order field mapping and the stock contract

See sections 6.3 and 6.4 above (identical in both languages, kept in one place on purpose).

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
