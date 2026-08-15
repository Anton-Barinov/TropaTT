# GitLab Integration

Синхронизация Merge Requests GitLab с задачами TropaTT.

## Возможности

- Подключение к GitLab.com или self-managed GitLab (`{base_url}/api/v4`).
- Связь «проект GitLab → проект TropaTT».
- Merge Requests → задачи (заголовок, описание, статус, метки → теги, исполнитель).
- Обсуждения MR (notes) → комментарии задачи.
- Входящий webhook (проверка `X-Gitlab-Token`) + cron-опрос как fallback для shared-хостинга без публичного webhook.
- Идемпотентная синхронизация: повторный запуск обновляет задачу, не дублируя её.

## Требования

- Personal/Project Access Token с правами `read_api` (или `api` для webhook).
- Для self-managed GitLab — базовый URL API, например `https://gitlab.example.com/api/v4`.

## Безопасность

- Токен и webhook-секрет хранятся зашифрованными (AES-256-GCM на `APP_SECRET`).
- Входящий webhook проверяет `X-Gitlab-Token` перед приёмом события.
- Синхронизируются только явно связанные проекты.

## Ограничения

- Синхронизация односторонняя: GitLab → TropaTT.
- Milestones и ревью-заметки (diff-обсуждения) не импортируются (только обычные заметки).
