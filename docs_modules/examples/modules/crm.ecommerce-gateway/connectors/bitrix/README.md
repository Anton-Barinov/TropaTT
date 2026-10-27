# Модуль интеграции TropaTT CRM для 1C-Битрикс: Управление сайтом

Официальный модуль двусторонней синхронизации заказов, покупателей и статусов между
**1C-Битрикс: Управление сайтом (редакции «Малый бизнес» и «Бизнес», Sale 16+, D7)** и TropaTT CRM
(модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt.crm-1.0.1.zip`** — архив с каталогом `tropatt.crm/`, который распаковывается
в `/bitrix/modules/`.

## 1. Возможности

- **Заказы в CRM**: новый заказ и каждая смена статуса попадают в локальную очередь (`b_tropatt_queue`) и
  отправляются в TropaTT CRM каноническим payload'ом E-COM-01 (позиции, доставка, оплата, покупатель,
  реквизиты юрлиц).
- **Асинхронная доставка**: отправкой занимается CAgent `\Tropatt\Crm\Agent\QueueAgent::processQueue()`
  (раз в минуту, батчами по 20), поэтому недоступный CRM не тормозит оформление заказа; неудачные попытки
  повторяются с backoff до `Максимум попыток`, после чего строка остаётся в очереди со статусом `ERROR`.
- **Обратная синхронизация статусов**: CRM вызывает `POST /tropatt/v1/webhook`, модуль проверяет подпись
  HMAC-SHA256 (окно ±300 с, защита от повторов) и переводит заказ в статус по вашему маппингу. Смена статуса
  модулем не вызывает повторную отправку в CRM (anti-echo).
- **Реквизиты юридических лиц**: свойства `COMPANY`, `INN`, `KPP`, `LEGAL_ADDRESS` уходят в первый
  класс-блок `billing_entity`.
- **Маппинг статусов**: таблица «`STATUS_ID` Битрикса ↔ код стадии CRM» в настройках модуля.
- **Ping-тест** соединения прямо со страницы настроек.
- **PHP 7.4 – 8.2**, только штатные средства Битрикса (D7 HTTP-клиент) с cURL-fallback.

## 2. Совместимость

| Компонент | Версия |
|---|---|
| 1C-Битрикс: Управление сайтом | 16.x и новее («Малый бизнес», «Бизнес»), модуль `sale` 16+ |
| PHP | 7.4 – 8.2 |
| MySQL / MariaDB | 5.6+ (таблица очереди — InnoDB) |
| Расширения PHP | `curl` или HTTP-клиент Битрикса, `hash` (HMAC), `json` |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

> Для других платформ используйте соответствующие коннекторы: `tropatt-opencart-1.5`, `tropatt-opencart-2.0-2.2`,
> `tropatt-opencart-2.3`, `tropatt-opencart-3.0`, `tropatt-opencart-4.0`, `tropatt-woocommerce`.

## 3. Установка

1. Распакуйте архив в корень сайта так, чтобы модуль оказался в `/bitrix/modules/tropatt.crm/`
   (в архиве уже лежит каталог `tropatt.crm/`).
2. Откройте **Настройки → Настройки продукта → Модули** и нажмите **Установить** у модуля
   «TropaTT CRM — шлюз интернет-магазина».
   При установке создаётся таблица `b_tropatt_queue`, регистрируются обработчики `OnSaleOrderSaved` и
   `OnSaleStatusOrderChange`, добавляется CAgent и правило URL-rewrite `/tropatt/v1/webhook`.
3. Откройте настройки модуля (**Настройки → Настройки продукта → Настройки модулей → TropaTT CRM**):
   включите модуль, укажите URL шлюза, публичный ключ витрины (`stk_...`) и секрет, нажмите
   **Проверить соединение** (должен прийти `PONG`).
   URL шлюза можно вставить в том виде, в каком его показывает карточка витрины в CRM
   (`https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1`) — модуль сам определяет,
   что суффикс `/_module/crm.ecommerce-gateway/v1` уже есть, и дописывает только путь запроса (`/orders`,
   `/ping`). Без этого получался удвоенный путь
   (`…?route=/_module/crm.ecommerce-gateway/v1/_module/crm.ecommerce-gateway/v1/orders`), и CRM отвечала 404 —
   заказы в CRM не попадали и проверка соединения всегда падала.
4. Скопируйте **URL входящих вебхуков** из настроек в карточку витрины в TropaTT CRM.
5. На вкладке «Маппинг статусов» сопоставьте `STATUS_ID` заказов Битрикса со стадиями задач CRM.

Витрина создаётся в CRM: **Шлюз интернет-магазинов → Витрины → Создать** (секрет показывается один раз).

## 4. Как это работает

```
  1C-Битрикс (Sale, D7)                          TropaTT CRM
  ─────────────────────                          ───────────
  OnSaleOrderSaved / OnSaleStatusOrderChange
        │  addExecPostCommitHandler (после коммита)
        ▼
  b_tropatt_queue ──► CAgent processQueue() ──► POST /v1/orders (HMAC-SHA256, idempotency key)
                                                       │  дедупликация, заявка/задача
  /tropatt/v1/webhook ◄── POST order.status_changed ───┘  CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        ▼
  Order::setField('STATUS_ID') + save()  (anti-echo: событие гасится)
```

## 5. Файлы

```
install/index.php              установка: регистрация модуля, таблица очереди, события, CAgent, URL-rewrite
install/version.php            версия модуля
include.php                    автозагрузка классов модуля
options.php                    страница настроек (вкладки «Основные» и «Маппинг статусов»)
webhook.php                    публичная точка входа /tropatt/v1/webhook
lib/Config.php                 настройки модуля и значения по умолчанию
lib/SignatureValidator.php     canonical-подпись, HMAC, окно толерантности, constant-time сравнение
lib/Client.php                 HTTP-клиент шлюза (Bitrix HttpClient + cURL fallback, retry)
lib/OrderMapper.php            заказ Битрикса → канонический payload E-COM-01
lib/StatusMapper.php           двусторонний маппинг STATUS_ID ↔ стадия CRM
lib/ConnectorLogger.php        журнал с маскированием секретов
lib/Queue.php                  очередь b_tropatt_queue (дедупликация, попытки, статистика)
lib/OrderHandler.php           обработчики событий Sale и применение статуса с anti-echo
lib/WebhookHandler.php         приём вебхуков CRM (подпись, окно, защита от повторов)
lib/Agent/QueueAgent.php       CAgent: доставка очереди в CRM
lang/{ru,en}/…                 языковые файлы
.github/workflows/lint.yml     php -l (PHP 7.4–8.2) + проверка манифеста модуля
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt.crm-<версия>.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Заказы не появляются в CRM | Модуль включён, настройки заполнены, в списке агентов есть `QueueAgent::processQueue()` (Настройки → Инструменты → Агенты) |
| В очереди растут `ERROR` | Откройте журнал (режим отладки), проверьте доступность URL шлюза и правильность ключа/секрета |
| Вебхук отвечает 401 | Секрет витрины в CRM и в модуле должен совпадать; проверьте время на сервере (окно ±300 с) |
| Вебхук отвечает 409 | Этот пакет уже обработан (защита от повторов), повторная отправка того же события не нужна |
| Вебхук отвечает 404 | Правило URL-rewrite `/tropatt/v1/webhook` не добавлено — переустановите модуль |
| Статус в CRM меняется, а в Битриксе нет | В маппинге нет пары для стадии CRM либо статус `STATUS_ID` указан неверно |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for 1C-Bitrix (EN)

A D7 module for two-way order and status synchronisation between **1C-Bitrix: Site Management** (Sale 16+, editions «Малый бизнес» and «Бизнес») and the [TropaTT](https://github.com/Anton-Barinov/TropaTT) self-hosted CRM (module `crm.ecommerce-gateway`).

### Install

1. Unpack the archive so the module lands in `/bitrix/modules/tropatt.crm/`.
2. **Settings → Product settings → Modules** → install *TropaTT CRM — store gateway* (creates `b_tropatt_queue`, registers the sale event handlers, the CAgent and the `/tropatt/v1/webhook` rewrite rule).
3. Open the module settings, enable it, fill in the gateway URL, store key (`stk_...`) and secret, run **Test connection**.
4. Copy the inbound webhook URL into the store card in TropaTT CRM and map the order statuses.

### Highlights

- Orders are queued in `b_tropatt_queue` and delivered by a CAgent with retries, so a slow CRM never blocks checkout.
- HMAC-SHA256 signing with idempotency key `{store_key}:order:{order_id}`; the CRM webhook receiver verifies the signature, the ±300 s window and replays.
- Legal-entity requisites (`COMPANY`, `INN`, `KPP`, `LEGAL_ADDRESS`) are mapped to the first-class `billing_entity` block.

### License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
