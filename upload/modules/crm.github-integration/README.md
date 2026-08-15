# Модуль crm.github-integration — Синхронизация GitHub

Синхронизация GitHub issues и pull requests с задачами TropaTT (+ GitHub Enterprise Server).

## Маппинг

- Репозиторий → проект CRM (связь «репозиторий ↔ проект»).
- Issue/PR → задача CRM: `#number title` → название, `body` → описание, `state` (`open`/`closed`) → статус (`new`/`done`), `labels` → теги, `assignees` → исполнитель (первый), комментарии → комментарии.

## Идемпотентность

Повторная синхронизация не дублирует задачи: соответствие хранится по `link_id + source_type + source_id` → `target_public_id`.

## Синхронизация

- Ручная — кнопка «Синхронизировать» (обработка порциями в HTTP-запросе, безопасно на shared-хостинге).
- Webhook (push/issue) — приёмник `POST /_module/crm.github-integration/webhook` с проверкой подписи `X-Hub-Signature-256` (HMAC-SHA256 секрета ссылки). Webhook помечает репозиторий «грязным» для следующей синхронизации и возвращает 200 сразу.

## Безопасность

- PAT хранится зашифрованным (aes-256-gcm на `APP_SECRET`), не возвращается в API и не логируется.
- Секрет webhook хранится зашифрованным; подпись проверяется `hash_equals`.
- Синхронизируются только явно привязанные репозитории.
- Base URL разрешён только для `api.github.com` / `*.github.com` / явно указанного GHES-хоста (настраивается).

## Права

- `module.github-integration.view` — просмотр.
- `module.github-integration.manage` — управление подключениями и связями.
- `module.github-integration.secret_manage` — запись секретов.
- `module.github-integration.run` — запуск синхронизации.
