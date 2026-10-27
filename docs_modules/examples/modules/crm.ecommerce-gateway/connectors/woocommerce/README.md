# Официальный плагин TropaTT CRM для WooCommerce / WordPress

Модуль интеграции для интернет-магазинов на WordPress + WooCommerce. Обеспечивает прямую двустороннюю синхронизацию заказов, контактных данных и статусов с TropaTT CRM (`crm.ecommerce-gateway`).

## Возможности
- Двусторонняя реактивная синхронизация статусов заказов через REST API с защитой от эхо-петель (`Tropatt_Client::$suppress_echo`).
- Передача деталей заказов: товары, артикулы (SKU), количество, цены в минорных единицах, налоги, скидки, способы и стоимость доставки.
- Полная криптографическая защита HMAC-SHA256 в соответствии со спецификацией протокола Ingestion API.
- **Заказы из Cart/Checkout-блоков тоже уезжают в CRM**: блочный чекаут работает через Store API и не вызывает старый хук `woocommerce_checkout_order_processed` — плагин подписан на `woocommerce_store_api_checkout_order_processed` (WC 7.2+), а повторная отправка того же заказа в одном запросе подавлена.
- **Входящий статус проверяется перед применением**: неизвестный статус (например, CRM-код `in_progress`) больше не пишется в заказ — WooCommerce не умеет его отображать и сваливает заказ в `pending`, при этом CRM считала доставку успешной. Теперь шлюз отвечает `422 TROPATT_UNKNOWN_STATUS` с перечнем известных статусов, а заказ не трогается.
- **Асинхронная доставка через Action Scheduler**: заказ уходит из фоновой задачи, а не в запросе оформления, поэтому медленный или недоступный CRM не тормозит чекаут; при отсутствии Action Scheduler включается синхронный fallback.
- **Совместимость с HPOS** (High-Performance Order Storage) и Cart/Checkout Blocks: плагин объявляет совместимость через `FeaturesUtil::declare_compatibility`, поэтому WooCommerce не блокирует включение HPOS на сайте.
- **Работа с кэшем**: REST-эндпоинт приёма статусов отдаёт `Cache-Control: no-store`, `DONOTCACHEPAGE` и заголовки для WP Rocket / LiteSpeed Cache / W3 Total Cache, чтобы кэш не «съедал» смену статуса.
- **Мультивалютность**: суммы берутся в валюте заказа, включая режимы WOOCS и WPML/WCML (`_woocs_order_currency`, `_wcml_order_currency`).
- **Кастомные поля чекаута**: публичные метаданные заказа и поля сторонних плагинов чекаута (Checkout Field Editor и др.) передаются в CRM в `custom_fields`.
- **Защита от повторов**: повторная подписанная посылка в окне толерантности отклоняется (HTTP 409).
- REST-маршрут приёма статусов — `/wp-json/tropatt/v1/status` (старый `/webhook` сохранён как алиас).
- Встроенная кнопка быстрой проверки соединения (Ping-тест) в админке WooCommerce.

## Установка
1. Загрузите архив `tropatt-woocommerce.zip` через меню **Плагины** -> **Добавить новый** -> **Загрузить плагин**.
2. Активируйте плагин **TropaTT CRM E-Commerce Gateway**.
3. Перейдите в **WooCommerce** -> **Настройки** -> вкладка **TropaTT CRM**.
4. Введите URL шлюза, публичный ключ витрины (`stk_...`) и секретный ключ, полученные в TropaTT CRM.
5. Скопируйте сгенерированный URL входящих вебхуков в карточку витрины в CRM и сохраните настройки.

## Сборка архива

```bash
bash build.sh
```

Скрипт собирает `dist/tropatt-woocommerce.zip` (плагин упаковывается в каталог `tropatt-ecommerce/`, как требует установщик WordPress) и проверяет целостность архива.

## Лицензия

AGPL-3.0, та же лицензия, что и у проекта TropaTT (см. `LICENSE`).

---

# TropaTT CRM connector for WooCommerce / WordPress (EN)

A plugin for two-way order and status synchronisation between WooCommerce and the [TropaTT](https://github.com/Anton-Barinov/TropaTT) self-hosted CRM (module `crm.ecommerce-gateway`), with HMAC-SHA256 signed requests and echo-loop protection.

## Install

1. Run `bash build.sh` (or download `tropatt-woocommerce.zip` from the releases page).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the archive and activate *TropaTT CRM E-Commerce Gateway*.
3. Open **WooCommerce → Settings → TropaTT CRM**, fill in the gateway URL, the store public key (`stk_...`) and the secret, then run the connection test and save.

## Build

```bash
bash build.sh
```

## License

AGPL-3.0, the same licence as the TropaTT project (see `LICENSE`).
