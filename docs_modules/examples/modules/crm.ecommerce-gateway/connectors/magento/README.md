# Коннектор TropaTT CRM для Magento 2 / Adobe Commerce

Модуль **`Tropatt_Crm`** для **Magento 2 / Adobe Commerce**: двусторонняя синхронизация заказов,
покупателей и статусов с TropaTT CRM (модуль `crm.ecommerce-gateway`).

Формат поставки: **`tropatt-magento.zip`** — архив с каталогом `app/`, который распаковывается в корень
проекта (модуль ставится в `app/code/Tropatt/Crm/`).

## 1. Возможности

- **Асинхронная отправка заказов**: интерсептор на `OrderRepositoryInterface::save()` публикует сообщение в
  очередь **Message Queue** (`tropatt.order.sync`), а консьюмер загружает заказ заново и отправляет его в CRM
  подписанным запросом (HMAC-SHA256, идемпотентность `{store_key}:order:{order_id}`). Чекаут не ждёт CRM.
- **Полный маппинг заказа**: позиции (SKU, количество, цены с налогом, опции товара), суммы
  `grand_total`/`subtotal`/`shipping`/`tax`, способ оплаты и доставки, адрес, покупатель, `store_id`,
  комментарий покупателя — в `custom_fields`.
- **Обратная синхронизация статусов**: фронтенд-контроллер `POST /tropatt/webhook/index`
  (`Controller/Webhook/Index.php`, anonymous — аутентификация по HMAC подписи **сырого тела**) читает заголовки
  `X-TropaTT-Signature`/`X-TropaTT-Timestamp` и проверяет подпись и окно ±300 с (`Model/InboundWebhook.php`),
  затем меняет статус через `OrderRepositoryInterface::save()` и **подавляет эхо**
  (`Model/Registry/EchoGuard.php`).

  > **Почему контроллер, а не REST-эндпоинт:** сервис-контракт (`webapi.xml`) получает параметры только из
  > тела/URL запроса, поэтому прежний `POST /V1/tropatt/webhook` не видел ни заголовков, ни сырого тела:
  > Magento отвечал `InputException` (400) до входа в метод, а HMAC по десериализованному телу не совпал бы с
  > подписью по сырому телу. Контроллер читает запрос целиком (`Request\Http::getHeader()/getContent()`).
- **Остатки (MSI)**: `Model/StockSync.php` обновляет источники через **`SourceItemsSaveInterface`**
  (`SourceItemInterface`), команда `bin/magento tropatt:sync-stock <file.json> [source_code]`.
- **Настройки в админке**: `Stores → Configuration → TropaTT CRM` (включение, URL шлюза, ключ/секрет витрины,
  секрет вебхука, стадия по умолчанию, маппинг статусов, код источника MSI), ACL-ресурс `Tropatt_Crm::config`.
- **PHP 7.4 / 8.1+**, без внешних зависимостей.

## 2. Совместимость

| Компонент | Версия |
|---|---|
| Magento / Adobe Commerce | 2.4.x |
| PHP | 7.4, 8.1, 8.2 |
| Очередь | RabbitMQ (Message Queue framework); при недоступности брокера сообщения не публикуются |
| TropaTT CRM | модуль `crm.ecommerce-gateway` 1.2.0+ |

## 3. Установка

1. Распакуйте архив в корень проекта (получится `app/code/Tropatt/Crm/`).
2. Выполните:
   ```bash
   bin/magento module:enable Tropatt_Crm
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```
3. Для очереди запустите консьюмера (или настройте supervisor):
   ```bash
   bin/magento queue:consumers:start tropattOrderSync
   ```
4. Откройте **Stores → Configuration → TropaTT CRM**, включите модуль и заполните: URL шлюза, публичный ключ
   витрины (`stk_...`), секрет витрины, секрет вебхука, стадию CRM по умолчанию, маппинг статусов и код
   источника MSI (`default`).
5. Укажите URL вебхука в карточке витрины в TropaTT CRM: `https://shop.example.com/tropatt/webhook/index`.
   При обновлении с версии ниже 1.0.2 замените прежний URL `https://shop.example.com/rest/V1/tropatt/webhook`
   (этот REST-маршрут удалён: он не мог проверить подпись и всегда отвечал 400).

## 4. Схема обмена

```
  Magento 2                                      TropaTT CRM
  ─────────                                      ───────────
  OrderRepositoryInterface::save() ──► interceptor ──► Message Queue (tropatt.order.sync)
                                                          │ consumer: заказ заново + canonical E-COM-01
                                                          ▼
                            POST /_module/crm.ecommerce-gateway/v1/orders (HMAC-SHA256)
                                                          │
  POST /tropatt/webhook/index ◄── order.status_changed ─┘  CRM Events / Outbox
        │  подпись base64(HMAC(secret, timestamp.'.'.body)), окно ±300 c
        ▼
  OrderRepositoryInterface::save()  (anti-echo: EchoGuard)

  bin/magento tropatt:sync-stock ──► SourceItemsSaveInterface (остатки MSI)
```

## 5. Файлы

```
app/code/Tropatt/Crm/registration.php        регистрация модуля
app/code/Tropatt/Crm/etc/{module,di,webapi,communication,queue_topology,queue_consumer}.xml  конфигурация
app/code/Tropatt/Crm/etc/adminhtml/system.xml  настройки в админке, etc/acl.xml — права
app/code/Tropatt/Crm/Api/WebhookInterface.php  контракт вебхука
app/code/Tropatt/Crm/Model/Webhook.php         приём вебхуков CRM (подпись, статус, анти-эхо)
app/code/Tropatt/Crm/Model/Registry/EchoGuard.php  флаг подавления эха
app/code/Tropatt/Crm/Model/Mapper/OrderMapper.php  заказ Magento → канонический payload
app/code/Tropatt/Crm/Model/Queue/{Publisher,Consumer}.php  публикация и обработка очереди
app/code/Tropatt/Crm/Plugin/OrderPlugin.php    интерсептор сохранения заказа
app/code/Tropatt/Crm/Model/Http/Client.php     подписанный клиент шлюза
app/code/Tropatt/Crm/Model/{Signature,Config,StatusMapper,StockSync}.php  подпись, настройки, статусы, MSI
app/code/Tropatt/Crm/Console/Command/SyncStockCommand.php  команда синхронизации остатков
app/code/Tropatt/Crm/i18n/{ru_RU,en_US}.csv    локализация
.github/workflows/lint.yml                     php -l (7.4/8.1/8.2) и разбор XML
```

## 6. Сборка архива

```bash
bash build.sh   # dist/tropatt-magento.zip
```

## 7. Диагностика

| Симптом | Что проверить |
|---|---|
| Заказы не уходят в CRM | Модуль включён, настройки заполнены, консьюмер `tropattOrderSync` запущен, RabbitMQ доступен |
| Сообщения копятся в очереди | Проверьте логи консьюмера (`var/log/`), доступность URL шлюза и правильность ключа/секрета |
| Вебхук отвечает 401 | `webhook_secret` в настройках должен совпадать с секретом витрины в CRM; проверьте время сервера (±300 с) и что запрос отправлен методом POST на `/tropatt/webhook/index` |
| Вебхук отвечает 404 | Заказ не найден по `external_order_id` (или запрос пришёл не методом POST — роутер отдаёт 404) |
| Вебхук отвечает 422 | В маппинге нет пары для стадии CRM либо указан несуществующий статус Magento |
| Остатки не обновляются | Проверьте код источника MSI и наличие SKU; запустите `bin/magento tropatt:sync-stock` вручную |

## 8. Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for Magento 2 / Adobe Commerce (EN)

The `Tropatt_Crm` module connects Magento 2 with the TropaTT CRM e-commerce gateway. Order saves are published
to the Message Queue (`tropatt.order.sync`) by an interceptor on `OrderRepositoryInterface`, and the consumer
delivers the canonical payload with HMAC-SHA256 signing. The CRM webhook (`POST /tropatt/webhook/index`, a
frontend controller that reads the `X-TropaTT-Signature`/`X-TropaTT-Timestamp` headers and the raw body) applies
status changes with echo suppression, and stock is pushed through the Multi-Source Inventory API
(`SourceItemsSaveInterface`) from `bin/magento tropatt:sync-stock`.

## Install

1. Unpack the archive into the project root (`app/code/Tropatt/Crm/`).
2. `bin/magento module:enable Tropatt_Crm && bin/magento setup:upgrade && bin/magento setup:di:compile && bin/magento cache:flush`.
3. Start the consumer: `bin/magento queue:consumers:start tropattOrderSync`.
4. Configure **Stores → Configuration → TropaTT CRM** and point the store webhook at
   `https://shop.example.com/tropatt/webhook/index` (upgrading from a build below 1.0.2: replace the removed
   `https://shop.example.com/rest/V1/tropatt/webhook`, which could not verify the signature and always answered 400).

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
