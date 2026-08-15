# Модуль crm.slack-integration — Уведомления в Slack

Отправка уведомлений TropaTT в каналы Slack через [Incoming Webhooks](https://api.slack.com/messaging/webhooks).

## Возможности

- Подключения (Slack Incoming Webhook URL, хранится зашифрованным — aes-256-gcm на `APP_SECRET`).
- Тестовая отправка сообщения.
- Очередь доставки с cron-обработчиком и retry (подходит для shared-хостинга, где нельзя держать долгие запросы).
- Правила-шаблоны сообщений (событие → подключение + шаблон с плейсхолдерами).
- Endpoint `POST /_module/crm.slack-integration/notify` для вызова из workflow (`call_webhook`) и внешних webhook.

## Как настроить событийные уведомления

1. Создайте Incoming Webhook в Slack и добавьте подключение в модуле.
2. (Опционально) создайте правило-шаблон для события.
3. В разделе «Автоматизация / Workflow» создайте правило с действием `call_webhook` и URL:

   `https://ваш-домен/api/index.php?route=_module/crm.slack-integration/notify&connection_public_id=SLK_PUBLIC_ID`

   либо `&rule_public_id=SLR_PUBLIC_ID` (тогда текст берётся из шаблона правила).

Плейсхолдеры шаблона: `{event}`, `{task}`, `{user}`, `{status}`, `{project}`.

## Безопасность

- Webhook URL — секрет: шифруется, не возвращается в API и не пишется в логи.
- Разрешены только хосты `hooks.slack.com` (конфигурируется через `allowed_webhook_hosts`).
- Доступ по объектному уровню: соединение принадлежит `created_by_user_id`; управление — через право `module.slack-integration.manage`; root видит всё.

## Права

- `module.slack-integration.view` — просмотр подключений и доставок.
- `module.slack-integration.manage` — управление подключениями и правилами.
- `module.slack-integration.secret_manage` — запись/чтение секретов (создание/изменение подключений).
