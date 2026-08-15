# Модуль crm.linear-migration — Миграция из Linear

Односторонний перенос данных из [Linear](https://linear.app) в TropaTT.

## Маппинг

- `Team` → команда CRM (учитывается в маппинге пользователей).
- `Project` → проект CRM.
- `Issue` → задача CRM: `identifier` + `title` → название, `description` → описание, `state.type` → статус (`started`→`in_progress`, `completed`/`canceled`→`done`, иначе `new`), `priority` → приоритет, `assignee` → исполнитель (по e-mail), `dueDate` → срок, `labels` → теги, `parent` → родительская задача, `comments` → комментарии.
- `Label` → тег CRM.

## Идемпотентность

Повторный запуск не дублирует данные: соответствие хранится по `connection_id + source_type + source_id` → `target_public_id`.

## Обработка

Импорт выполняется порциями в рамках HTTP-запроса (chunked), с контрольной точкой прогресса. Это безопасно на shared-хостинге: сервисы ядра (проекты/задачи/теги/комментарии) доступны только в контексте запроса, поэтому cron-воркер не используется для записи.

## API-ключ Linear

1. Linear → Settings → Security & access → Personal API keys.
2. Создайте ключ (`lin_api_...`) и вставьте в подключение.

## Права

- `module.linear-migration.view` — просмотр.
- `module.linear-migration.manage` — управление подключениями.
- `module.linear-migration.secret_manage` — запись секретов.
- `module.linear-migration.run` — запуск импорта.
