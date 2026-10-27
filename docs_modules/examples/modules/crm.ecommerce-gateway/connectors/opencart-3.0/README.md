# Модуль интеграции TropaTT CRM для OpenCart 3.0.x / ocStore 3.0.x

Официальный модуль двусторонней синхронизации заказов и статусов между OpenCart 3.0.x / ocStore 3.0.x и TropaTT CRM (`crm.ecommerce-gateway`).

## Возможности
- Двусторонняя реактивная синхронизация статусов заказов через вебхуки с защитой от эхо-петель (Anti-Echo Loop).
- Надежная криптографическая подпись каждого пакета HMAC-SHA256 (`X-Store-Key`, `X-TropaTT-Timestamp`, `X-TropaTT-Nonce`, `X-TropaTT-Signature`).
- Передача товарных позиций, стоимости доставки, скидок, реквизитов покупателя и комментариев.
- Страна доставки уходит в CRM как ISO 3166-1 alpha-2 (`delivery_address.country_code`): код берётся из справочника `oc_country` по `shipping_country_id`, читаемое название сохраняется в `custom_fields` — раньше в CRM уезжало название и терялось на приёме.
- Интерактивный Ping-тест соединения прямо из панели управления модулем.
- Совместимость с PHP 7.1 – 8.1+.
- Чистая архитектура OpenCart 3: события `catalog/model/checkout/order/addOrderHistory/after` регистрируются автоматически без модификации файлов ядра.

## Установка
1. Скачайте архив `tropatt-opencart-3.ocmod.zip`.
2. В панели управления OpenCart перейдите в **Установка расширений** (Installer) и загрузите архив.
3. Перейдите в **Модификаторы** (Modifications) и нажмите кнопку **Обновить** (Refresh).
4. Перейдите в **Модули / Расширения** -> **Модули**, найдите **TropaTT CRM — Шлюз синхронизации** и нажмите **Установить**.
5. Нажмите **Редактировать**, укажите URL шлюза, публичный ключ витрины (`stk_...`) и секретный ключ, нажмите **Проверить соединение** и сохраните настройки.

## Сборка архива

```bash
bash build.sh
```

Скрипт собирает `dist/tropatt-opencart-3.ocmod.zip` из исходников репозитория и проверяет целостность архива (`unzip -t`).

## Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for OpenCart / ocStore 3.0.x (EN)

A module for two-way order and status synchronisation between OpenCart / ocStore 3.0.x and the [TropaTT](https://github.com/Anton-Barinov/TropaTT) self-hosted CRM (module `crm.ecommerce-gateway`), with HMAC-SHA256 signed requests and echo-loop protection.

## Install

1. Download `tropatt-opencart-3.ocmod.zip` (or run `bash build.sh`).
2. Upload it through the platform installer, then enable the module and open its settings.
3. Fill in the gateway URL, the store public key (`stk_...`) and the store secret from TropaTT CRM, run **Test connection** and save.

## Build

```bash
bash build.sh
```

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
