# TropaTT CRM — Документация по API

Полный справочник REST API самодостаточной CRM TropaTT: базовый URL, авторизация, права доступа (RBAC), общие соглашения и полный реестр endpoint'ов.

**Язык:** **Русский** · [English](api_en.md) · [中文](api_zh.md)

> Последнее обновление: 2026-08-15. Источник данных: `upload/api/config/routes.php` и файлы маршрутов модулей.

## Обзор

TropaTT предоставляет версионированный JSON REST API. Каждый защищённый endpoint требует bearer-токен, а проверки прав (RBAC) выполняются на стороне сервера.

### Base URL

```
https://{host}/api/v1/
```

### Версионирование

Все основные endpoint'ы начинаются с `/api/v1/`. Некоторые внутренние endpoint'ы не имеют версии:

- `/install/*` — установка
- `/internal/migration/*` — миграции

### Форматы данных

| Формат | Использование |
|--------|--------------|
| `application/json` | Основной формат запросов и ответов |
| `multipart/form-data` | Загрузка файлов |
| Server-Sent Events | Real-time обновления (`/api/v1/events/stream`) |
| JSON-RPC | MCP endpoint (`/api/v1/mcp`) |

## Авторизация

### Bearer Token

Передавайте токен доступа в заголовке `Authorization`:

```
Authorization: Bearer <token>
```

Браузерные сессии используют cookie `session_id` вместе с заголовком `X-CSRF-Token`.

### Публичные endpoint'ы (без авторизации)

`GET /api/v1/version`, `POST /api/v1/auth/login`, `POST /api/v1/security/password-reset`, `POST /api/v1/security/password-reset/confirm`, `POST /api/v1/security/invitations/accept` и endpoint'ы установщика.

### RBAC (права доступа)

Каждый защищённый endpoint объявляет требуемые permissions (например, `task.manage`, `user.view`). Валидный токен без нужного права возвращает `403`, невалидный или отсутствующий токен — `401`.

| Группа | Ключи прав |
|--------|-----------|
| Пользователи | `user.view`, `user.manage` |
| Роли | `role.view`, `role.manage` |
| Команды и отделы | `team.manage`, `department.manage` |
| Проекты | `project.manage` |
| Задачи | `task.manage` |
| Клиенты и компании | `client.manage`, `company.manage`, `contact.manage`, `counterparty.manage` |
| Организации | `organization.manage` |
| Знания | `knowledge.view`, `knowledge.create`, `knowledge.edit`, `knowledge.delete`, `knowledge.publish`, `knowledge.comment`, `knowledge.manage` |
| Настройки | `settings.manage` |
| Вебхуки | `webhook.manage` |
| Логи | `logs.view` |
| AI | `ai.use`, `ai.admin` |
| Импорт / экспорт | `import.manage`, `export.manage` |
| Согласования | `approval.manage` |
| Корзина | `recycle_bin.manage` |
| API-клиенты | `api_client.view`, `api_client.manage` |
| Входящие | `intake.view`, `intake.create`, `intake.manage`, `intake.delete`, `intake.accept` |
| Идеи | `idea.view`, `idea.manage` |
| Чат | `chat.use` |

## Общие соглашения

### Заголовки (headers)

| Header | Обязательный | Описание |
|--------|:---:|----------|
| `Authorization` | Да (для защищённых) | `Bearer <token>` |
| `Content-Type` | Для POST/PUT/PATCH | `application/json` или `multipart/form-data` |
| `Accept` | Нет | `application/json` (по умолчанию) |
| `X-CSRF-Token` | Для cookie-auth | CSRF-токен |
| `X-Request-Id` | Нет | ID запроса (для трейсинга) |
| `X-Correlation-Id` | Нет | ID корреляции |
| `X-Idempotency-Key` | Нет | Ключ идемпотентности |
| `X-Locale` | Нет | Локаль: `ru-ru` или `en-gb` |

### Успешный ответ (2xx)

```json
{
  "success": true,
  "data": { },
  "meta": { "cursor": "next_cursor_string", "has_more": true }
}
```

### Ошибка (4xx/5xx)

```json
{
  "success": false,
  "error": { "code": "ERROR_CODE", "message": "Человекочитаемое описание" }
}
```

### Ошибка валидации (422)

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "errors": { "field_name": ["Сообщение об ошибке"] }
  }
}
```

### HTTP-статусы

| Статус | Использование |
|--------|--------------|
| 200 | Успешный GET/PATCH/PUT/DELETE |
| 201 | Успешный POST (создание) |
| 204 | Успешный DELETE без тела ответа |
| 400 | Неверный запрос / невалидный JSON |
| 401 | Не авторизован / невалидный токен |
| 403 | Недостаточно прав |
| 404 | Ресурс не найден |
| 409 | Конфликт |
| 422 | Ошибка валидации |
| 429 | Rate limit превышен |
| 500 | Внутренняя ошибка сервера |

### Пагинация

Cursor-based: используйте параметр `cursor` и `limit`, читайте `meta.cursor` и `meta.has_more` из ответа.

### Идентификаторы

Все ID — ULID (26 символов). `row_version` — целое число для optimistic locking при обновлении.

### Идемпотентность

Заголовок `X-Idempotency-Key` предотвращает дублирование операций.

## OpenAPI и MCP

- **OpenAPI-спецификация** — машиночитаемая спецификация отдаётся работающей установкой на `GET /api/v1/docs/openapi`.
- **MCP (Model Context Protocol)** — CRM предоставляет JSON-RPC MCP endpoint на `POST /api/v1/mcp` для AI-агентов. См. документацию MCP в репозитории.

## Реестр endpoint'ов

> **Примечание:** многие endpoint'ы имеют два варианта URL — RESTful (`/api/v1/resources`) и alias (`/api/v1/resource/list`, `/api/v1/resource/create`). Alias'ы отмечены пометкой `🔄`.

### Install & Migration

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/install/status` | Статус установки | Нет | — | Проверка, установлена ли CRM |
| GET, POST | `/install/check` | Проверка подключения к БД | Нет | — | Валидация параметров подключения |
| POST | `/install/setup` | Запуск установки | Нет | — | Создание таблиц, root-пользователя |
| GET | `/internal/migration/status` | Статус миграций | Да | `settings.manage` | Текущее состояние миграций |
| POST | `/internal/migration/up` | Применение миграций | Да | `settings.manage` | Запуск pending миграций |
| GET | `/internal/migration/dry-run` | Dry-run миграций | Да | `settings.manage` | Предварительный просмотр изменений |
| GET | `/internal/migration/rollback-check` | Проверка отката | Да | `settings.manage` | Проверка возможности отката |

### Health & Version

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/health/status` | Базовая проверка здоровья | Да | — | Статус сервиса |
| GET | `/api/v1/health/deep` | Глубокая проверка здоровья | Да | — | Проверка БД, кэша, AI |
| GET | `/api/v1/version` | Версия CRM (публичный) | Нет | — | Текущая версия без авторизации |
| POST | `/api/v1/mcp` | Model Context Protocol | Да | — | JSON-RPC для AI-агентов |

### Core Update

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/core/version` | Версия ядра | Да | `settings.manage` | Детальная информация о версии |
| GET | `/api/v1/core/updates/status` | Статус обновлений | Да | `settings.manage` | Доступные обновления |
| POST | `/api/v1/core/updates/check` | Проверка обновлений | Да | `settings.manage` | Запрос на проверку новых версий |
| GET | `/api/v1/core/updates/changes` | Список изменений | Да | `settings.manage` | Changelog между версиями |
| POST | `/api/v1/core/updates/preflight` | Preflight проверка | Да | `settings.manage` | Проверка готовности к обновлению |
| POST | `/api/v1/core/updates/session` | Сессия обновления | Да | `settings.manage` | Создание сессии обновления |
| GET | `/api/v1/core/updates/history` | История обновлений | Да | `settings.manage` | Журнал прошлых обновлений |
| GET | `/api/v1/core/updates/log/{job_id}` | Лог обновления | Да | `settings.manage` | Детали конкретного обновления |
| POST | `/api/v1/core/updates/recovery-key` | Ключ восстановления | Да | `settings.manage` | Выпуск recovery-ключа для сессии обновления |

### Auth

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/auth/login` | Вход в систему | Нет | — | Аутентификация, выдача токена |
| POST | `/api/v1/auth/logout` | Выход из системы | Да | — | Завершение сессии |
| GET | `/api/v1/auth/me` | Текущий пользователь | Да | — | Данные авторизованного пользователя |
| GET | `/api/v1/auth/menu` | Меню пользователя | Да | — | Структура меню для роли |
| GET | `/api/v1/auth/menu/preferences` | Предпочтения меню | Да | — | Настройки видимости пунктов |
| PUT, PATCH | `/api/v1/auth/menu/preferences` | Сохранение предпочтений меню | Да | — | Обновление настроек видимости |
| GET | `/api/v1/roles/{public_id}/menu-template` | Шаблон меню роли | Да | `role.manage` | Шаблон меню для назначения роли |
| PUT, PATCH | `/api/v1/roles/{public_id}/menu-template` | Сохранение шаблона меню роли | Да | `role.manage` | Обновление шаблона |
| GET | `/api/v1/teams/{public_id}/menu-template` | Шаблон меню команды | Да | `team.manage` | Шаблон меню для назначения команды |
| PUT, PATCH | `/api/v1/teams/{public_id}/menu-template` | Сохранение шаблона меню команды | Да | `team.manage` | Обновление шаблона |
| GET | `/api/v1/users/{public_id}/menu-preferences` | Предпочтения меню пользователя (админ) | Да | `user.manage` | Админское управление |
| PUT, PATCH | `/api/v1/users/{public_id}/menu-preferences` | Сохранение предпочтений меню пользователя (админ) | Да | `user.manage` | Админское управление |

### Telemetry

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/telemetry/frontend-event` | Фронтенд-событие | Да | — | Телеметрия с клиента |
| POST | `/api/v1/telemetry/csp-report` | CSP-отчёт | Нет | — | Content Security Policy нарушения |
| POST | `/api/v1/telemetry/login-debug` | Лог отладки входа | Да | `logs.view` | Отладочная информация при входе |

### Users

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/users` 🔄 | Список пользователей | Да | `user.view` | Cursor-based пагинация |
| POST | `/api/v1/users` 🔄 | Создание пользователя | Да | `user.manage` | — |
| GET | `/api/v1/users/{public_id}` 🔄 | Детали пользователя | Да | `user.view` | — |
| PATCH, PUT | `/api/v1/users/{public_id}` 🔄 | Обновление пользователя | Да | `user.manage` | Optimistic locking (`row_version`) |
| DELETE | `/api/v1/users/{public_id}` 🔄 | Деактивация пользователя | Да | `user.manage` | Soft-delete |
| GET | `/api/v1/users/{public_id}/tokens` 🔄 | Токены пользователя | Да | `user.view` | — |
| POST | `/api/v1/users/{public_id}/tokens/rotate` 🔄 | Ротация токена | Да | `user.manage` | — |
| DELETE | `/api/v1/users/{public_id}/tokens` 🔄 | Отзыв токена | Да | `user.manage` | — |
| GET | `/api/v1/users/{public_id}/activity` 🔄 | Активность пользователя | Да | `user.view` | Лента действий |

### Roles

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/roles` 🔄 | Список ролей | Да | `role.view` | — |
| POST | `/api/v1/roles` 🔄 | Создание роли | Да | `role.manage` | Только root (F2-4) |
| PATCH, PUT | `/api/v1/roles/{public_id}` 🔄 | Обновление роли | Да | `role.manage` | Только root (F2-4) |
| DELETE | `/api/v1/roles/{public_id}` 🔄 | Удаление роли | Да | `role.manage` | Только root (F2-4) |

### Permissions

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/permissions` 🔄 | Все разрешения системы | Да | `role.view` | Справочник |
| GET | `/api/v1/roles/{public_id}/permissions` 🔄 | Разрешения роли | Да | `role.view` | — |
| PUT, PATCH | `/api/v1/roles/{public_id}/permissions` 🔄 | Назначение разрешений роли | Да | `role.manage` | Только root (F2-4) |
| GET | `/api/v1/admin/role-matrix` 🔄 | Матрица ролей | Да | `role.manage` | — |
| PUT, PATCH | `/api/v1/admin/role-matrix` 🔄 | Обновление матрицы ролей | Да | `role.manage` | Только root (F2-4) |

### API Clients

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/api-clients` 🔄 | Список API-клиентов | Да | `api_client.view` | — |
| POST | `/api/v1/api-clients` 🔄 | Создание API-клиента | Да | `api_client.manage` | — |
| GET | `/api/v1/api-clients/{public_id}` 🔄 | Детали API-клиента | Да | `api_client.view` | — |
| PATCH, PUT | `/api/v1/api-clients/{public_id}` 🔄 | Обновление API-клиента | Да | `api_client.manage` | — |
| DELETE | `/api/v1/api-clients/{public_id}` 🔄 | Удаление API-клиента | Да | `api_client.manage` | — |
| GET | `/api/v1/api-clients/{public_id}/keys` 🔄 | Список ключей клиента | Да | `api_client.view` | — |
| POST | `/api/v1/api-clients/{public_id}/keys` 🔄 | Выпуск ключа | Да | `api_client.manage` | — |
| POST | `/api/v1/api-keys/{public_id}/rotate` 🔄 | Ротация ключа | Да | `api_client.manage` | — |
| POST, DELETE | `/api/v1/api-keys/{public_id}/revoke` 🔄 | Отзыв ключа | Да | `api_client.manage` | — |
| GET | `/api/v1/api-keys/{public_id}/usage` 🔄 | Использование ключа | Да | `api_client.view` | — |

### Teams & Departments

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/teams` 🔄 | Список команд | Да | `team.manage` | — |
| POST | `/api/v1/teams` 🔄 | Создание команды | Да | `team.manage` | — |
| GET | `/api/v1/teams/{public_id}` 🔄 | Детали команды | Да | `team.manage` | — |
| PATCH, PUT | `/api/v1/teams/{public_id}` 🔄 | Обновление команды | Да | `team.manage` | — |
| DELETE | `/api/v1/teams/{public_id}` 🔄 | Удаление команды | Да | `team.manage` | — |
| GET | `/api/v1/departments` 🔄 | Список отделов | Да | `department.manage` | — |
| POST | `/api/v1/departments` 🔄 | Создание отдела | Да | `department.manage` | — |
| GET | `/api/v1/departments/{public_id}` 🔄 | Детали отдела | Да | `department.manage` | — |
| PATCH, PUT | `/api/v1/departments/{public_id}` 🔄 | Обновление отдела | Да | `department.manage` | — |
| DELETE | `/api/v1/departments/{public_id}` 🔄 | Удаление отдела | Да | `department.manage` | — |

### Companies, Clients, Contacts, Counterparties

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/companies` 🔄 | Список компаний | Да | `company.manage` | — |
| POST | `/api/v1/companies` 🔄 | Создание компании | Да | `company.manage` | — |
| GET | `/api/v1/companies/{public_id}` 🔄 | Детали компании | Да | `company.manage` | — |
| PATCH, PUT | `/api/v1/companies/{public_id}` 🔄 | Обновление компании | Да | `company.manage` | — |
| DELETE | `/api/v1/companies/{public_id}` 🔄 | Удаление компании | Да | `company.manage` | — |
| GET | `/api/v1/clients` 🔄 | Список клиентов | Да | `client.manage` | — |
| POST | `/api/v1/clients` 🔄 | Создание клиента | Да | `client.manage` | — |
| GET | `/api/v1/clients/{public_id}` 🔄 | Детали клиента | Да | `client.manage` | — |
| PATCH, PUT | `/api/v1/clients/{public_id}` 🔄 | Обновление клиента | Да | `client.manage` | — |
| DELETE | `/api/v1/clients/{public_id}` 🔄 | Удаление клиента | Да | `client.manage` | — |
| GET | `/api/v1/counterparties` 🔄 | Список контрагентов | Да | `counterparty.manage` | Фильтр по типу, поиску |
| POST | `/api/v1/counterparties` 🔄 | Создание контрагента | Да | `counterparty.manage` | — |
| GET | `/api/v1/counterparties/{public_id}` 🔄 | Детали контрагента | Да | `counterparty.manage` | — |
| PATCH, PUT | `/api/v1/counterparties/{public_id}` 🔄 | Обновление контрагента | Да | `counterparty.manage` | — |
| DELETE | `/api/v1/counterparties/{public_id}` 🔄 | Удаление контрагента | Да | `counterparty.manage` | — |
| GET | `/api/v1/contacts` 🔄 | Список контактов | Да | `contact.manage` | — |
| POST | `/api/v1/contacts` 🔄 | Создание контакта | Да | `contact.manage` | Требуется `full_name` (max 255) |
| GET | `/api/v1/contacts/{public_id}` 🔄 | Детали контакта | Да | `contact.manage` | — |
| PATCH, PUT | `/api/v1/contacts/{public_id}` 🔄 | Обновление контакта | Да | `contact.manage` | — |
| DELETE | `/api/v1/contacts/{public_id}` 🔄 | Удаление контакта | Да | `contact.manage` | — |

### Client Cabinet

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/client-cabinet/projects` 🔄 | Проекты клиента | Да | `client.manage` | Только свои проекты |
| GET | `/api/v1/client-cabinet/projects/{public_id}` 🔄 | Детали проекта клиента | Да | `client.manage` | — |
| GET | `/api/v1/client-cabinet/projects/{public_id}/tasks` 🔄 | Задачи проекта клиента | Да | `client.manage` | — |

### Organizations

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/organizations` 🔄 | Список организаций | Да | `organization.manage` | Фильтр: `q`, `type` |
| POST | `/api/v1/organizations` 🔄 | Создание организации | Да | `organization.manage` | — |
| GET | `/api/v1/organizations/{public_id}` 🔄 | Детали организации | Да | `organization.manage` | — |
| PATCH, PUT | `/api/v1/organizations/{public_id}` 🔄 | Обновление организации | Да | `organization.manage` | Optimistic locking |
| DELETE | `/api/v1/organizations/{public_id}` 🔄 | Удаление организации | Да | `organization.manage` | — |
| GET | `/api/v1/organizations/{public_id}/members` 🔄 | Участники организации | Да | `organization.manage` | — |
| POST | `/api/v1/organizations/{public_id}/members` 🔄 | Добавление участника | Да | `organization.manage` | — |
| DELETE | `/api/v1/organizations/{public_id}/members/{user_public_id}` 🔄 | Удаление участника | Да | `organization.manage` | — |

### Statuses, Priorities, Tags

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/statuses` 🔄 | Список статусов | Да | `task.manage` | Фильтр по `scope` (task/project) |
| POST | `/api/v1/statuses` 🔄 | Создание статуса | Да | `task.manage` | Требуется `title`, `code` (уникальный), `scope` (task/project), `color` (HEX) |
| GET | `/api/v1/statuses/{public_id}` 🔄 | Детали статуса | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/statuses/{public_id}` 🔄 | Обновление статуса | Да | `task.manage` | — |
| DELETE | `/api/v1/statuses/{public_id}` 🔄 | Удаление статуса | Да | `task.manage` | — |
| POST | `/api/v1/statuses/{public_id}/remap-delete` 🔄 | Удаление с переназначением | Да | `task.manage` | Перенос задач на другой статус |
| GET | `/api/v1/priorities` 🔄 | Список приоритетов | Да | `task.manage` | — |
| POST | `/api/v1/priorities` 🔄 | Создание приоритета | Да | `task.manage` | — |
| GET | `/api/v1/priorities/{public_id}` 🔄 | Детали приоритета | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/priorities/{public_id}` 🔄 | Обновление приоритета | Да | `task.manage` | — |
| DELETE | `/api/v1/priorities/{public_id}` 🔄 | Удаление приоритета | Да | `task.manage` | — |
| GET | `/api/v1/tags` 🔄 | Список тегов | Да | `task.manage` | — |
| POST | `/api/v1/tags` 🔄 | Создание тега | Да | `task.manage` | — |
| GET | `/api/v1/tags/{public_id}` 🔄 | Детали тега | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/tags/{public_id}` 🔄 | Обновление тега | Да | `task.manage` | — |
| DELETE | `/api/v1/tags/{public_id}` 🔄 | Удаление тега | Да | `task.manage` | — |

### Task-Tag Binding

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{task_public_id}/tags` 🔄 | Теги задачи | Да | `task.manage` | — |
| POST | `/api/v1/tasks/{task_public_id}/tags/{tag_public_id}` 🔄 | Привязка тега | Да | `task.manage` | — |
| DELETE | `/api/v1/tasks/{task_public_id}/tags/{tag_public_id}` 🔄 | Отвязка тега | Да | `task.manage` | — |

### Projects

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/projects` 🔄 | Список проектов | Да | `project.manage` | Cursor-based, фильтры: `status`, `client_public_id`, `q` |
| POST | `/api/v1/projects` 🔄 | Создание проекта | Да | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}` 🔄 | Детали проекта | Да | `project.manage` | — |
| PATCH, PUT | `/api/v1/projects/{public_id}` 🔄 | Обновление проекта | Да | `project.manage` | Optimistic locking |
| DELETE | `/api/v1/projects/{public_id}` 🔄 | Архивация проекта | Да | `project.manage` | Soft-delete |
| GET | `/api/v1/projects/{public_id}/timeline` 🔄 | Таймлайн (Gantt) | Да | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/summary` 🔄 | Сводка по проекту | Да | `project.manage` | Прогресс, задачи, вехи |
| GET | `/api/v1/projects/{public_id}/milestones-summary` 🔄 | Сводка по вехам | Да | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/risks` 🔄 | Риски проекта | Да | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/workload` 🔄 | Загрузка участников | Да | `project.manage` | — |

### Tasks

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks` 🔄 | Список задач | Да | `task.manage` | Cursor-based, фильтры |
| POST | `/api/v1/tasks` 🔄 | Создание задачи | Да | `task.manage` | — |
| GET | `/api/v1/tasks/board` 🔄 | Канбан-доска | Да | `task.manage` | Группировка по статусам |
| POST | `/api/v1/tasks/bulk` 🔄 | Массовое обновление | Да | `task.manage` | — |
| GET | `/api/v1/tasks/by-key/{task_key}` | Задача по ключу | Да | `task.manage` | Человекочитаемый ключ |
| GET | `/api/v1/tasks/{public_id}` 🔄 | Детали задачи | Да | `task.manage` | С комментариями, файлами и т.д. |
| PATCH, PUT | `/api/v1/tasks/{public_id}` 🔄 | Обновление задачи | Да | `task.manage` | Optimistic locking, `identity_edit_forbidden` |
| DELETE | `/api/v1/tasks/{public_id}` 🔄 | Удаление задачи (корзина) | Да | `task.manage` | Soft-delete |
| POST | `/api/v1/tasks/{public_id}/move` 🔄 | Перемещение на доске | Да | `task.manage` | Тело: `to_status_public_id` (или `to_status`) |
| GET | `/api/v1/tasks/{public_id}/activity` | Активность задачи | Да | `task.manage` | Лента действий |
| GET | `/api/v1/tasks/{public_id}/comments` 🔄 | Комментарии задачи | Да | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/comments` 🔄 | Добавление комментария | Да | `task.manage` | Тело: `body` (string, max 8000). Возвращает созданный комментарий с `public_id` |
| GET | `/api/v1/tasks/{public_id}/files` | Файлы задачи | Да | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/knowledge-pages` | Привязка страницы знаний | Да | `task.manage`, `knowledge.view` | Привязка страницы базы знаний к задаче |

### Task Relations v2

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/relations` | Связи задачи | Да | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/relations` | Создание связи | Да | `task.manage` | — |
| DELETE | `/api/v1/task-relations/{public_id}` | Удаление связи | Да | `task.manage` | — |
| GET | `/api/v1/task-relations/search-tasks` | Поиск задач для связи | Да | `task.manage` | — |

### Comments

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| PATCH, PUT | `/api/v1/comments/{public_id}` 🔄 | Обновление комментария | Да | — | — |
| DELETE | `/api/v1/comments/{public_id}` 🔄 | Удаление комментария | Да | — | — |

### Comment Drafts

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | Получение черновика | Да | `task.manage` | — |
| POST, PUT, PATCH | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | Сохранение черновика | Да | `task.manage` | — |
| DELETE | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | Удаление черновика | Да | `task.manage` | — |

### Subtasks

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/subtasks` 🔄 | Подзадачи задачи | Да | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/subtasks` 🔄 | Создание подзадачи | Да | `task.manage` | — |
| GET | `/api/v1/subtasks/{public_id}` 🔄 | Детали подзадачи | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/subtasks/{public_id}` 🔄 | Обновление подзадачи | Да | `task.manage` | — |
| DELETE | `/api/v1/subtasks/{public_id}` 🔄 | Удаление подзадачи | Да | `task.manage` | — |

### Checklists

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/checklists` 🔄 | Чеклисты задачи | Да | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/checklists` 🔄 | Создание чеклиста | Да | `task.manage` | — |
| GET | `/api/v1/checklists/{public_id}` 🔄 | Детали чеклиста | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/checklists/{public_id}` 🔄 | Обновление чеклиста | Да | `task.manage` | — |
| DELETE | `/api/v1/checklists/{public_id}` 🔄 | Удаление чеклиста | Да | `task.manage` | — |
| GET | `/api/v1/checklists/{public_id}/items` 🔄 | Пункты чеклиста | Да | `task.manage` | — |
| POST | `/api/v1/checklists/{public_id}/items` 🔄 | Добавление пункта | Да | `task.manage` | — |
| GET | `/api/v1/checklist-items/{public_id}` 🔄 | Детали пункта | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/checklist-items/{public_id}` 🔄 | Обновление пункта | Да | `task.manage` | — |
| DELETE | `/api/v1/checklist-items/{public_id}` 🔄 | Удаление пункта | Да | `task.manage` | — |

### Work Cycles

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/cycles` | Список циклов | Да | `task.manage` | — |
| POST | `/api/v1/cycles` | Создание цикла | Да | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}` | Детали цикла | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/cycles/{public_id}` | Обновление цикла | Да | `project.manage` | — |
| DELETE | `/api/v1/cycles/{public_id}` | Удаление цикла | Да | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/start` | Запуск цикла | Да | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/complete` | Завершение цикла | Да | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/reopen` | Переоткрытие цикла | Да | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/archive` | Архивация цикла | Да | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/tasks` | Задачи цикла | Да | `task.manage` | — |
| POST | `/api/v1/cycles/{public_id}/tasks` | Добавление задач в цикл | Да | `project.manage` | — |
| DELETE | `/api/v1/cycles/{public_id}/tasks/{task_public_id}` | Удаление задачи из цикла | Да | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/summary` | Сводка по циклу | Да | `task.manage` | — |
| POST | `/api/v1/cycles/{public_id}/transfer-unfinished` | Перенос незавершённых | Да | `project.manage` | — |

### Project Modules

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/project-modules` | Список модулей проекта | Да | `project.manage` | — |
| POST | `/api/v1/project-modules` | Создание модуля | Да | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}` | Детали модуля | Да | `project.manage` | — |
| PATCH, PUT | `/api/v1/project-modules/{public_id}` | Обновление модуля | Да | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}` | Удаление модуля | Да | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/archive` | Архивация модуля | Да | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/tasks` | Задачи модуля | Да | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/tasks` | Добавление задач в модуль | Да | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}/tasks/{task_public_id}` | Удаление задачи из модуля | Да | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/members` | Участники модуля | Да | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/members` | Добавление участника | Да | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}/members/{user_public_id}` | Удаление участника | Да | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/links` | Ссылки модуля | Да | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/links` | Добавление ссылки | Да | `project.manage` | — |
| PATCH, PUT | `/api/v1/project-module-links/{public_id}` | Обновление ссылки | Да | `project.manage` | — |
| DELETE | `/api/v1/project-module-links/{public_id}` | Удаление ссылки | Да | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/summary` | Сводка по модулю | Да | `project.manage` | — |

### Milestones

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/milestones` 🔄 | Список вех | Да | `project.manage` | Требуется `project_public_id` или `project_public_ids` (через запятую) |
| POST | `/api/v1/milestones` 🔄 | Создание вехи | Да | `project.manage` | Требуется `title`, `project_public_id`. Опционально: `due_at` (YYYY-MM-DD) |
| GET | `/api/v1/milestones/{public_id}` 🔄 | Детали вехи | Да | `project.manage` | — |
| PATCH, PUT | `/api/v1/milestones/{public_id}` 🔄 | Обновление вехи | Да | `project.manage` | — |
| DELETE | `/api/v1/milestones/{public_id}` 🔄 | Удаление вехи | Да | `project.manage` | — |

### Dependencies

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/dependencies` 🔄 | Список зависимостей | Да | `task.manage` | — |
| POST | `/api/v1/dependencies` 🔄 | Создание зависимости | Да | `task.manage` | Типы: FS, SS, FF, SF, BLOCKS |
| DELETE | `/api/v1/dependencies/{public_id}` 🔄 | Удаление зависимости | Да | `task.manage` | — |

### Files

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/files` | Загрузка файла | Да | `task.manage` | `multipart/form-data`, max 20MB |
| GET | `/api/v1/files/{public_id}` | Метаданные файла | Да | `task.manage` | — |
| GET | `/api/v1/files/{public_id}/download` | Скачивание файла | Да | `task.manage` | Binary response |
| DELETE | `/api/v1/files/{public_id}` | Удаление файла | Да | `task.manage` | — |

### Templates

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/template/tasks` 🔄 | Список шаблонов задач | Да | `task.manage` | — |
| POST | `/api/v1/template/tasks` 🔄 | Создание шаблона задачи | Да | `task.manage` | — |
| GET | `/api/v1/template/tasks/{public_id}` 🔄 | Детали шаблона задачи | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/template/tasks/{public_id}` 🔄 | Обновление шаблона задачи | Да | `task.manage` | — |
| DELETE | `/api/v1/template/tasks/{public_id}` 🔄 | Удаление шаблона задачи | Да | `task.manage` | — |
| POST | `/api/v1/template/tasks/{public_id}/apply` 🔄 | Применение шаблона задачи | Да | `task.manage` | Создание задачи из шаблона |
| GET | `/api/v1/template/projects` 🔄 | Список шаблонов проектов | Да | `project.manage` | — |
| POST | `/api/v1/template/projects` 🔄 | Создание шаблона проекта | Да | `project.manage` | — |
| GET | `/api/v1/template/projects/{public_id}` 🔄 | Детали шаблона проекта | Да | `project.manage` | — |
| PATCH, PUT | `/api/v1/template/projects/{public_id}` 🔄 | Обновление шаблона проекта | Да | `project.manage` | — |
| DELETE | `/api/v1/template/projects/{public_id}` 🔄 | Удаление шаблона проекта | Да | `project.manage` | — |
| POST | `/api/v1/template/projects/{public_id}/apply` 🔄 | Применение шаблона проекта | Да | `project.manage` | Создание проекта из шаблона |

### Notifications

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/notifications` 🔄 | Список уведомлений | Да | `task.manage` | — |
| POST | `/api/v1/notifications` 🔄 | Создание уведомления | Да | `task.manage` | — |
| GET | `/api/v1/notifications/counters` 🔄 | Счётчик непрочитанных | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/notifications/{public_id}/read` 🔄 | Отметить как прочитанное | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/notifications/{public_id}/unread` 🔄 | Отметить как непрочитанное | Да | `task.manage` | — |
| POST | `/api/v1/notifications/mark-all-read` 🔄 | Отметить все прочитанными | Да | `task.manage` | — |
| GET | `/api/v1/notifications/push-subscriptions` 🔄 | Push-подписки | Да | `task.manage` | — |
| POST | `/api/v1/notifications/push-subscriptions` 🔄 | Создание push-подписки | Да | `task.manage` | — |
| DELETE | `/api/v1/notifications/push-subscriptions/{public_id}` 🔄 | Удаление push-подписки | Да | `task.manage` | — |
| POST | `/api/v1/notifications/push-test` 🔄 | Тест push-уведомления | Да | `task.manage` | — |

### Reminders

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/reminders` 🔄 | Список напоминаний | Да | `task.manage` | — |
| POST | `/api/v1/reminders` 🔄 | Создание напоминания | Да | `task.manage` | — |
| GET | `/api/v1/reminders/{public_id}` 🔄 | Детали напоминания | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/reminders/{public_id}` 🔄 | Обновление напоминания | Да | `task.manage` | — |
| DELETE | `/api/v1/reminders/{public_id}` 🔄 | Удаление напоминания | Да | `task.manage` | — |

### Calendar

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/calendar/events` 🔄 | События календаря | Да | `task.manage` | Параметры: `from`, `to` |
| POST | `/api/v1/calendar/events` 🔄 | Создание события | Да | `task.manage` | — |
| GET | `/api/v1/calendar/events/{public_id}` 🔄 | Детали события | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/calendar/events/{public_id}` 🔄 | Обновление события | Да | `task.manage` | — |
| DELETE | `/api/v1/calendar/events/{public_id}` 🔄 | Удаление события | Да | `task.manage` | — |
| GET | `/api/v1/calendar/my-day` 🔄 | Мой день | Да | `task.manage` | Агрегация за день |
| GET | `/api/v1/calendar/my-week` 🔄 | Моя неделя | Да | `task.manage` | Агрегация за неделю |
| GET | `/api/v1/calendar/my-month` 🔄 | Мой месяц | Да | `task.manage` | Агрегация за месяц |
| GET | `/api/v1/calendar/business` 🔄 | Бизнес-календари | Да | `settings.manage` | — |
| POST | `/api/v1/calendar/business` 🔄 | Создание бизнес-календаря | Да | `settings.manage` | — |
| GET | `/api/v1/calendar/business/{public_id}` 🔄 | Детали бизнес-календаря | Да | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/business/{public_id}` 🔄 | Обновление бизнес-календаря | Да | `settings.manage` | — |
| DELETE | `/api/v1/calendar/business/{public_id}` 🔄 | Удаление бизнес-календаря | Да | `settings.manage` | — |
| GET | `/api/v1/calendar/holidays` 🔄 | Праздничные дни | Да | `settings.manage` | — |
| POST | `/api/v1/calendar/holidays` 🔄 | Создание праздничного дня | Да | `settings.manage` | — |
| GET | `/api/v1/calendar/holidays/{public_id}` 🔄 | Детали праздничного дня | Да | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/holidays/{public_id}` 🔄 | Обновление праздничного дня | Да | `settings.manage` | — |
| DELETE | `/api/v1/calendar/holidays/{public_id}` 🔄 | Удаление праздничного дня | Да | `settings.manage` | — |
| GET | `/api/v1/calendar/working-hours` 🔄 | Рабочие часы | Да | `settings.manage` | — |
| POST | `/api/v1/calendar/working-hours` 🔄 | Создание рабочих часов | Да | `settings.manage` | — |
| GET | `/api/v1/calendar/working-hours/{public_id}` 🔄 | Детали рабочих часов | Да | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/working-hours/{public_id}` 🔄 | Обновление рабочих часов | Да | `settings.manage` | — |
| DELETE | `/api/v1/calendar/working-hours/{public_id}` 🔄 | Удаление рабочих часов | Да | `settings.manage` | — |

### Page Data (Frontend API)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/pages/my-day` | Данные "Мой день" | Да | `task.manage` | Данные для SPA-страницы |
| GET | `/api/v1/pages/kanban` | Данные канбана | Да | `task.manage` | Данные для SPA-страницы |
| GET | `/api/v1/pages/my-week` | Данные "Моя неделя" | Да | `task.manage` | Данные для SPA-страницы |

### Worklogs

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/worklogs` 🔄 | Список записей времени | Да | `task.manage` | — |
| POST | `/api/v1/worklogs` 🔄 | Создание записи времени | Да | `task.manage` | Требуется `task_public_id`, `minutes_spent` (int, минуты), `logged_at` (YYYY-MM-DD) |
| GET | `/api/v1/worklogs/summary` | Сводка по времени | Да | `task.manage` | — |
| GET | `/api/v1/worklogs/earnings` | Доходы по времени | Да | `task.manage` | — |
| GET | `/api/v1/worklogs/matrix` | Матрица времени | Да | `task.manage` | — |
| GET | `/api/v1/worklogs/detail` | Детали по времени | Да | `task.manage` | Требуется `project_public_id` |
| GET | `/api/v1/worklogs/{public_id}` 🔄 | Детали записи | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/worklogs/{public_id}` 🔄 | Обновление записи | Да | `task.manage` | Обновляемые поля: `minutes_spent`, `description`, `logged_at` |
| DELETE | `/api/v1/worklogs/{public_id}` 🔄 | Удаление записи | Да | `task.manage` | — |
| GET | `/api/v1/worklogs/task/{public_id}` | Время по задаче | Да | `task.manage` | — |

### Dashboard & Analytics

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/dashboard/summary` 🔄 | Сводка дашборда | Да | `task.manage` | Задачи, просроченные, без исполнителя |
| GET | `/api/v1/analytics/summary` 🔄 | Сводка аналитики | Да | `task.manage` | — |
| GET | `/api/v1/analytics/projects` 🔄 | Аналитика по проектам | Да | `task.manage` | — |
| GET | `/api/v1/analytics/users` 🔄 | Аналитика по пользователям | Да | `task.manage` | — |
| GET | `/api/v1/dashboard/widgets` | Виджеты дашборда | Да | — | Текущие виджеты пользователя |
| PUT | `/api/v1/dashboard/widgets` | Сохранение виджетов | Да | — | Обновление виджетов текущего пользователя |

### Search

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/search/global` 🔄 | Глобальный поиск | Да | `task.manage` | По всем сущностям |
| GET | `/api/v1/search/suggestions` | Подсказки поиска | Да | `task.manage` | — |
| GET | `/api/v1/search/tasks` 🔄 | Поиск задач | Да | `task.manage` | — |
| GET | `/api/v1/search/projects` 🔄 | Поиск проектов | Да | `task.manage` | — |
| GET | `/api/v1/search/clients` 🔄 | Поиск клиентов | Да | `task.manage` | — |
| GET | `/api/v1/search/counterparties` | Поиск контрагентов | Да | `task.manage` | — |

### Mentions & Reactions

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/mentions` 🔄 | Список упоминаний | Да | `task.manage` | — |
| POST | `/api/v1/mentions` 🔄 | Создание упоминания | Да | `task.manage` | — |
| DELETE | `/api/v1/mentions/{public_id}` 🔄 | Удаление упоминания | Да | `task.manage` | — |
| GET | `/api/v1/reactions` 🔄 | Список реакций | Да | `task.manage` | — |
| POST | `/api/v1/reactions` 🔄 | Добавление реакции | Да | `task.manage` | — |
| DELETE | `/api/v1/reactions/{public_id}` 🔄 | Удаление реакции | Да | `task.manage` | — |

### Subscriptions & Favorites

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/subscriptions` 🔄 | Список подписок | Да | `task.manage` | — |
| POST | `/api/v1/subscriptions` 🔄 | Создание подписки | Да | `task.manage` | — |
| DELETE | `/api/v1/subscriptions/{public_id}` 🔄 | Удаление подписки | Да | `task.manage` | — |
| GET | `/api/v1/favorites` 🔄 | Список избранного | Да | `task.manage` | — |
| POST | `/api/v1/favorites` 🔄 | Добавление в избранное | Да | `task.manage` | — |
| DELETE | `/api/v1/favorites/{public_id}` 🔄 | Удаление из избранного | Да | `task.manage` | — |

### Saved Views

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/views` 🔄 | Список представлений | Да | `task.manage` | — |
| POST | `/api/v1/views` 🔄 | Создание представления | Да | `task.manage` | — |
| GET | `/api/v1/views/{public_id}` | Детали представления | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/views/{public_id}` 🔄 | Обновление представления | Да | `task.manage` | — |
| DELETE | `/api/v1/views/{public_id}` 🔄 | Удаление представления | Да | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/archive` | Архивация представления | Да | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/duplicate` | Дублирование представления | Да | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/pin` | Закрепление представления | Да | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/touch-last-used` | Обновление last_used_at | Да | `task.manage` | — |
| GET | `/api/v1/views/{public_id}/task-filters` | Фильтры задач представления | Да | `task.manage` | — |

### Activity & Audit

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/activity/feed` 🔄 | Лента активности | Да | `logs.view` | — |
| GET | `/api/v1/history/entity/{entity_type}/{public_id}` 🔄 | История сущности | Да | `logs.view` | — |
| GET | `/api/v1/audit/list` 🔄 | Список аудита | Да | `logs.view` | — |
| GET | `/api/v1/audit/user/{public_id}` 🔄 | Аудит по пользователю | Да | `logs.view` | — |
| GET | `/api/v1/audit/entity/{entity_type}/{public_id}` 🔄 | Аудит по сущности | Да | `logs.view` | — |

### Logs

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/logs/request` | Request-логи | Да | `logs.view` | — |
| GET | `/api/v1/logs/security` | Security-логи | Да | `logs.view` | — |
| GET | `/api/v1/logs/audit` | Audit-логи | Да | `logs.view` | — |
| GET | `/api/v1/logs/frontend-errors/chart` | График ошибок фронтенда | Да | `logs.view` | Агрегация ошибок фронтенда |

### Settings & Feature Flags

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/settings` 🔄 | Список настроек | Да | `settings.manage` | — |
| GET | `/api/v1/settings/{name}` 🔄 | Значение настройки | Да | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/settings/{name}` 🔄 | Установка настройки | Да | `settings.manage` | — |
| GET | `/api/v1/retention/metadata` 🔄 | Метаданные retention | Да | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/retention/metadata` 🔄 | Установка retention | Да | `settings.manage` | — |
| GET | `/api/v1/feature-flags` 🔄 | Список feature flags | Да | `feature_flag.manage` | — |
| PATCH, PUT | `/api/v1/feature-flags/{public_id}` 🔄 | Обновление feature flag | Да | `feature_flag.manage` | — |

### Custom Fields

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/custom-fields` 🔄 | Список полей | Да | `settings.manage` | — |
| POST | `/api/v1/custom-fields` 🔄 | Создание поля | Да | `settings.manage` | — |
| GET | `/api/v1/custom-fields/{public_id}` 🔄 | Детали поля | Да | `settings.manage` | — |
| PATCH, PUT | `/api/v1/custom-fields/{public_id}` 🔄 | Обновление поля | Да | `settings.manage` | — |
| DELETE | `/api/v1/custom-fields/{public_id}` 🔄 | Удаление поля | Да | `settings.manage` | — |
| GET | `/api/v1/custom-fields/values` 🔄 | Значения полей | Да | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/custom-fields/values` 🔄 | Установка значений | Да | `settings.manage` | — |

### Workflow Rules

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/workflow/rules` 🔄 | Список правил | Да | `settings.manage` | — |
| POST | `/api/v1/workflow/rules` 🔄 | Создание правила | Да | `settings.manage` | — |
| GET | `/api/v1/workflow/rules/{public_id}` 🔄 | Детали правила | Да | `settings.manage` | — |
| PATCH, PUT | `/api/v1/workflow/rules/{public_id}` 🔄 | Обновление правила | Да | `settings.manage` | — |
| DELETE | `/api/v1/workflow/rules/{public_id}` 🔄 | Удаление правила | Да | `settings.manage` | — |
| POST | `/api/v1/workflow/rules/{public_id}/run-test` 🔄 | Тестовый запуск правила | Да | `settings.manage` | — |
| GET | `/api/v1/workflow/runs` 🔄 | История запусков | Да | `settings.manage` | — |

### SLA

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/sla/policies` 🔄 | Список политик SLA | Да | `settings.manage` | — |
| POST | `/api/v1/sla/policies` 🔄 | Создание политики SLA | Да | `settings.manage` | — |
| GET | `/api/v1/sla/policies/{public_id}` 🔄 | Детали политики SLA | Да | `settings.manage` | — |
| PATCH, PUT | `/api/v1/sla/policies/{public_id}` 🔄 | Обновление политики SLA | Да | `settings.manage` | — |
| DELETE | `/api/v1/sla/policies/{public_id}` 🔄 | Удаление политики SLA | Да | `settings.manage` | — |
| GET | `/api/v1/sla/report` 🔄 | Отчёт SLA | Да | `settings.manage` | — |
| POST | `/api/v1/sla/assign/{public_id}` 🔄 | Назначение SLA на задачу | Да | `settings.manage` | — |

### Approvals

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/approvals` 🔄 | Список согласований | Да | `approval.manage` | — |
| POST | `/api/v1/approvals` 🔄 | Создание согласования | Да | `approval.manage` | — |
| GET | `/api/v1/approvals/{public_id}` 🔄 | Детали согласования | Да | `approval.manage` | — |
| POST | `/api/v1/approvals/{public_id}/approve` 🔄 | Согласование | Да | `approval.manage` | — |
| POST | `/api/v1/approvals/{public_id}/reject` 🔄 | Отклонение согласования | Да | `approval.manage` | — |

### Webhooks

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/webhooks` 🔄 | Список вебхуков | Да | `webhook.manage` | — |
| POST | `/api/v1/webhooks` 🔄 | Создание вебхука | Да | `webhook.manage` | Требуется `endpoint` (URL, max 2048), `events` (массив строк) |
| PATCH, PUT | `/api/v1/webhooks/{public_id}` 🔄 | Обновление вебхука | Да | `webhook.manage` | — |
| DELETE | `/api/v1/webhooks/{public_id}` 🔄 | Удаление вебхука | Да | `webhook.manage` | — |
| GET | `/api/v1/webhooks/deliveries` 🔄 | Все доставки | Да | `webhook.manage` | — |
| GET | `/api/v1/webhooks/{public_id}/deliveries` 🔄 | Доставки вебхука | Да | `webhook.manage` | — |
| POST | `/api/v1/webhooks/{public_id}/test` 🔄 | Тест вебхука | Да | `webhook.manage` | — |

### Import & Export

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/import/jobs` | Список задач импорта | Да | `import.manage` | — |
| POST | `/api/v1/import/jobs` | Создание задачи импорта | Да | `import.manage` | `multipart/form-data` |
| GET | `/api/v1/import/jobs/{public_id}` | Статус задачи импорта | Да | `import.manage` | — |
| POST | `/api/v1/import/jobs/{public_id}/cancel` | Отмена импорта | Да | `import.manage` | — |
| POST | `/api/v1/import/jobs/{public_id}/retry` | Повтор импорта | Да | `import.manage` | — |
| GET | `/api/v1/export/jobs` | Список задач экспорта | Да | `export.manage` | — |
| POST | `/api/v1/export/jobs` | Создание задачи экспорта | Да | `export.manage` | — |
| GET | `/api/v1/export/jobs/{public_id}` | Статус задачи экспорта | Да | `export.manage` | — |
| GET | `/api/v1/export/jobs/{public_id}/download` | Скачивание экспорта | Да | `export.manage` | Binary |
| POST | `/api/v1/export/jobs/{public_id}/cancel` | Отмена экспорта | Да | `export.manage` | — |
| POST | `/api/v1/export/jobs/{public_id}/retry` | Повтор экспорта | Да | `export.manage` | — |

### Recycle Bin

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/recycle-bin` 🔄 | Список корзины | Да | `recycle_bin.manage` | — |
| POST | `/api/v1/recycle-bin/{public_id}/restore` 🔄 | Восстановление | Да | `recycle_bin.manage` | — |
| DELETE, POST | `/api/v1/recycle-bin/{public_id}/purge` 🔄 | Очистка (перманентное удаление) | Да | `recycle_bin.manage` | — |

### Recurring Tasks

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/recurring` 🔄 | Список правил повторения | Да | `task.manage` | Фильтры: `project_public_id`, `is_active` |
| POST | `/api/v1/recurring` 🔄 | Создание правила | Да | `task.manage` | — |
| GET | `/api/v1/recurring/{public_id}` 🔄 | Детали правила | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/recurring/{public_id}` 🔄 | Обновление правила | Да | `task.manage` | — |
| DELETE | `/api/v1/recurring/{public_id}` 🔄 | Удаление правила | Да | `task.manage` | — |
| POST | `/api/v1/recurring/{public_id}/pause` 🔄 | Приостановка правила | Да | `task.manage` | — |
| POST | `/api/v1/recurring/{public_id}/resume` 🔄 | Возобновление правила | Да | `task.manage` | — |

### Estimate Sets

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/estimate-sets` | Список наборов оценок | Да | `project.manage` | — |
| POST | `/api/v1/estimate-sets` | Создание набора | Да | `project.manage` | — |
| GET | `/api/v1/estimate-sets/{public_id}` | Детали набора | Да | `project.manage` | — |
| PATCH, PUT | `/api/v1/estimate-sets/{public_id}` | Обновление набора | Да | `project.manage` | — |
| POST | `/api/v1/estimate-sets/{public_id}/archive` | Архивация набора | Да | `project.manage` | — |
| DELETE | `/api/v1/estimate-sets/{public_id}` | Удаление набора | Да | `project.manage` | — |
| GET | `/api/v1/estimate-sets/{public_id}/options` | Опции набора | Да | `project.manage` | — |
| POST | `/api/v1/estimate-sets/{public_id}/options` | Создание опции | Да | `project.manage` | — |
| PATCH, PUT | `/api/v1/estimate-options/{public_id}` | Обновление опции | Да | `project.manage` | — |
| POST | `/api/v1/estimate-options/{public_id}/archive` | Архивация опции | Да | `project.manage` | — |
| DELETE | `/api/v1/estimate-options/{public_id}` | Удаление опции | Да | `project.manage` | — |
| GET | `/api/v1/tasks/{public_id}/estimates` | Оценки задачи | Да | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/estimates` | Назначение оценки | Да | `task.manage` | — |
| DELETE | `/api/v1/tasks/{public_id}/estimates/{set_public_id}` | Удаление оценки | Да | `task.manage` | — |
| GET | `/api/v1/projects/{public_id}/estimate-summary` | Сводка оценок проекта | Да | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/estimate-summary` | Сводка оценок цикла | Да | `task.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/estimate-summary` | Сводка оценок модуля | Да | `project.manage` | — |

### Sticky Notes

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/sticky-notes` | Список стикеров | Да | `task.manage` | — |
| POST | `/api/v1/sticky-notes` | Создание стикера | Да | `task.manage` | — |
| GET | `/api/v1/sticky-notes/{public_id}` | Детали стикера | Да | `task.manage` | — |
| PATCH, PUT | `/api/v1/sticky-notes/{public_id}` | Обновление стикера | Да | `task.manage` | — |
| DELETE | `/api/v1/sticky-notes/{public_id}` | Удаление стикера | Да | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/archive` | Архивация стикера | Да | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/unarchive` | Разархивация стикера | Да | `task.manage` | — |
| POST | `/api/v1/sticky-notes/reorder` | Перестановка стикеров | Да | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/convert-to-task` | Конвертация в задачу | Да | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/convert-to-page` | Конвертация в страницу знаний | Да | `task.manage` | — |

### Knowledge Base

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/overview` | Обзор базы знаний | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/search` | Поиск по знаниям | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/recent` | Недавние страницы | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/popular` | Популярные страницы | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/review-queue` | Очередь ревью | Да | `knowledge.publish` | — |
| GET | `/api/v1/knowledge/outdated` | Устаревшие страницы | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/favorites` | Избранные страницы | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/suggest` | Подсказки | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/analytics` | Аналитика знаний | Да | `knowledge.analytics_view` | — |
| GET | `/api/v1/knowledge/templates` | Шаблоны страниц | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/templates` | Создание шаблона | Да | `knowledge.template_manage` | — |
| GET | `/api/v1/knowledge/entities/{entity_type}/{entity_public_id}/pages` | Страницы сущности | Да | `knowledge.view` | — |

### Knowledge — Spaces

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/spaces` | Пространства | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces-tree` | Дерево пространств | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/spaces` | Создание пространства | Да | `knowledge.manage` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}` | Детали пространства | Да | `knowledge.view` | — |
| PATCH, PUT | `/api/v1/knowledge/spaces/{public_id}` | Обновление пространства | Да | `knowledge.manage` | — |
| DELETE | `/api/v1/knowledge/spaces/{public_id}` | Архивация пространства | Да | `knowledge.manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/archive` | Архивация (альт.) | Да | `knowledge.manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/restore` | Восстановление | Да | `knowledge.manage` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/tree` | Дерево страниц | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/permissions` | Права пространства | Да | `knowledge.permission_manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/permissions` | Добавление права | Да | `knowledge.permission_manage` | — |
| DELETE | `/api/v1/knowledge/permissions/{permission_id}` | Удаление права | Да | `knowledge.permission_manage` | — |

### Knowledge — Pages

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages` | Список страниц | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages` | Создание страницы | Да | `knowledge.create` | — |
| GET | `/api/v1/knowledge/pages/{public_id}` | Детали страницы | Да | `knowledge.view` | — |
| PATCH, PUT | `/api/v1/knowledge/pages/{public_id}` | Обновление страницы | Да | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}` | Удаление страницы | Да | `knowledge.delete` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/publish` | Публикация страницы | Да | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/archive` | Архивация страницы | Да | `knowledge.delete` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/restore` | Восстановление страницы | Да | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/move` | Перемещение страницы | Да | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/copy` | Копирование страницы | Да | `knowledge.create` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/duplicate` | Дублирование страницы | Да | `knowledge.create` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/request-review` | Запрос ревью | Да | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/approve` | Одобрение ревью | Да | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/review` | Одобрение (альт.) | Да | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/reject` | Отклонение ревью | Да | `knowledge.publish` | — |

### Knowledge — Page Drafts, Links, Tags, Files

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/draft` | Черновик | Да | `knowledge.edit` | — |
| POST, PUT, PATCH | `/api/v1/knowledge/pages/{public_id}/draft` | Сохранение черновика | Да | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/draft` | Удаление черновика | Да | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/links` | Ссылки страницы | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/links` | Добавление ссылки | Да | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/links/{link_public_id}` | Удаление ссылки | Да | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/tags` | Теги страницы | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/tags/{tag_public_id}` | Привязка тега | Да | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/tags/{tag_public_id}` | Отвязка тега | Да | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/files` | Файлы страницы | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/files` | Загрузка файла | Да | `knowledge.edit` | `multipart/form-data` |
| DELETE | `/api/v1/knowledge/files/{file_public_id}` | Удаление файла | Да | `knowledge.edit` | — |

### Knowledge — Comments

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/comments` | Комментарии страницы | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/comments` | Добавление комментария | Да | `knowledge.comment` | — |
| DELETE | `/api/v1/knowledge/comments/{comment_public_id}` | Удаление комментария | Да | `knowledge.comment` | — |
| POST | `/api/v1/knowledge/comments/{comment_public_id}/resolve` | Разрешение комментария | Да | `knowledge.comment` | — |
| POST | `/api/v1/knowledge/comments/{comment_public_id}/reopen` | Переоткрытие комментария | Да | `knowledge.comment` | — |

### Knowledge — Favorites & Subscriptions

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/knowledge/pages/{public_id}/favorite` | Добавление в избранное | Да | `knowledge.view` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/favorite` | Удаление из избранного | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/subscribe` | Подписка на страницу | Да | `knowledge.view` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/subscribe` | Отписка от страницы | Да | `knowledge.view` | — |

### Knowledge — Export & Import

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/export` | Экспорт всех страниц | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/export` | Экспорт страницы | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/export` | Экспорт пространства | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/import` | Импорт страниц | Да | `knowledge.import` | — |

### Knowledge — Page Versions & Locking

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/versions` | Версии страницы | Да | `knowledge.view` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}` | Детали версии | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}/restore` | Восстановление версии | Да | `knowledge.publish` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}/diff` | Diff версий | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/lock` | Блокировка страницы | Да | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/unlock` | Разблокировка страницы | Да | `knowledge.publish` | — |

### Knowledge — AI

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/knowledge/pages/{public_id}/ai/summary` | AI-резюме страницы | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/explain` | AI-объяснение | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/similar` | Похожие страницы | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/checklist` | AI-чеклист | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/faq-from-comments` | FAQ из комментариев | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/ai/suggest-for-task/{task_public_id}` | Подсказки для задачи | Да | `knowledge.view` | — |
| POST | `/api/v1/knowledge/ai/admin/find-duplicates` | Поиск дублей | Да | `knowledge.admin` | — |
| GET | `/api/v1/knowledge/ai/admin/find-orphans` | Поиск осиротевших | Да | `knowledge.admin` | — |
| POST | `/api/v1/knowledge/ai/admin/suggest-structure/{public_id}` | Предложение структуры | Да | `knowledge.admin` | — |

### Knowledge — Admin

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/admin/knowledge/settings` | Настройки знаний | Да | `knowledge.admin` | — |
| PATCH, PUT | `/api/v1/admin/knowledge/settings` | Обновление настроек | Да | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/reindex` | Переиндексация | Да | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/rebuild-permissions` | Пересчёт прав | Да | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/cleanup-drafts` | Очистка черновиков | Да | `knowledge.admin` | — |

### Intake Items

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/intake-items` | Список входящих | Да | `intake.view` | — |
| POST | `/api/v1/intake-items` | Создание входящего | Да | `intake.create` | — |
| GET | `/api/v1/intake-items/{public_id}` | Детали входящего | Да | `intake.view` | — |
| PATCH, PUT | `/api/v1/intake-items/{public_id}` | Обновление входящего | Да | `intake.manage` | — |
| DELETE | `/api/v1/intake-items/{public_id}` | Удаление входящего | Да | `intake.delete` | — |
| POST | `/api/v1/intake-items/{public_id}/accept` | Принятие входящего | Да | `intake.accept` | — |
| POST | `/api/v1/intake-items/{public_id}/reject` | Отклонение входящего | Да | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/snooze` | Отсрочка входящего | Да | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/duplicate` | Дублирование входящего | Да | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/reopen` | Переоткрытие входящего | Да | `intake.manage` | — |
| GET | `/api/v1/intake-items/{public_id}/activities` | Активность входящего | Да | `intake.view` | — |

### OPS / Admin

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ops/system` 🔄 | Информация о системе | Да | `logs.view` | — |
| GET | `/api/v1/ops/metrics` 🔄 | Метрики | Да | `logs.view` | — |
| POST | `/api/v1/ops/jobs/run` 🔄 | Запуск фоновых задач | Да | `logs.view` | — |
| GET | `/api/v1/admin/widgets/summary` 🔄 | Виджет-сводка | Да | `logs.view` | — |
| GET | `/api/v1/admin/widgets/system` 🔄 | Виджет системы | Да | `logs.view` | — |
| GET | `/api/v1/admin/cache` | Статистика кэша | Да | `settings.manage` | — |
| POST | `/api/v1/admin/cache/clear` | Очистка кэша | Да | `settings.manage` | — |

### Docs & Events

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/docs/openapi` | OpenAPI-спецификация | Да | `logs.view` | — |
| GET | `/api/v1/docs/schema` | JSON Schema | Да | `logs.view` | — |
| GET | `/api/v1/events/stream` | SSE-поток | Да | — | Real-time обновления |
| POST | `/api/v1/visual-editor/upload-image` | Загрузка изображения | Да | — | `multipart/form-data`, для визуального редактора |

### Security (Sessions, Invitations, 2FA, Impersonation)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/security/sessions` 🔄 | Список сессий | Да | — | — |
| DELETE | `/api/v1/security/sessions/{public_id}` 🔄 | Отзыв сессии | Да | — | — |
| POST | `/api/v1/security/sessions/revoke-others` 🔄 | Отзыв других сессий | Да | — | — |
| POST | `/api/v1/security/sessions/revoke-device` 🔄 | Отзыв по устройству | Да | — | — |
| GET | `/api/v1/security/invitations` 🔄 | Список приглашений | Да | `user.manage` | — |
| POST | `/api/v1/security/invitations` 🔄 | Создание приглашения | Да | `user.manage` | — |
| POST | `/api/v1/security/invitations/accept` | Принятие приглашения | Нет | — | Публичный |
| GET | `/api/v1/security/invitations/{public_id}` 🔄 | Детали приглашения | Да | `user.manage` | — |
| POST | `/api/v1/security/password-reset` 🔄 | Запрос сброса пароля | Нет | — | Публичный |
| POST | `/api/v1/security/password-reset/confirm` 🔄 | Подтверждение сброса | Нет | — | Публичный |
| GET | `/api/v1/security/2fa/status` | Статус 2FA | Да | — | — |
| POST | `/api/v1/security/2fa/enable` | Включение 2FA | Да | — | — |
| POST | `/api/v1/security/2fa/disable` | Отключение 2FA | Да | — | — |
| POST | `/api/v1/security/2fa/verify` | Проверка 2FA-кода | Нет | — | Внутренний challenge-эндпоинт (публичный, без сессии) |
| POST | `/api/v1/security/impersonation/start` | Начало имперсонации | Да | `user.manage` | — |
| GET | `/api/v1/security/impersonation/status` | Статус имперсонации | Да | — | — |
| POST | `/api/v1/security/impersonation/stop` | Остановка имперсонации | Да | — | — |

### Profile

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/profile/me` 🔄 | Мой профиль | Да | — | — |
| PATCH, PUT | `/api/v1/profile/me` 🔄 | Обновление профиля | Да | — | — |
| GET | `/api/v1/profile/preferences` 🔄 | Мои предпочтения | Да | — | — |
| PATCH, PUT | `/api/v1/profile/preferences` 🔄 | Обновление предпочтений | Да | — | — |
| POST | `/api/v1/profile/change-password` 🔄 | Смена пароля | Да | — | — |

### AI — Providers & Models

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/providers` | Список AI-провайдеров | Да | `ai.admin` | — |
| POST | `/api/v1/ai/providers` | Создание провайдера | Да | `ai.admin` | — |
| GET | `/api/v1/ai/providers/{public_id}` | Детали провайдера | Да | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/providers/{public_id}` | Обновление провайдера | Да | `ai.admin` | — |
| DELETE | `/api/v1/ai/providers/{public_id}` | Удаление провайдера | Да | `ai.admin` | — |
| POST | `/api/v1/ai/providers/{public_id}/test` | Тест провайдера | Да | `ai.admin` | — |
| PUT | `/api/v1/ai/providers/{public_id}/secret` | Установка секрета | Да | `ai.admin` | — |
| DELETE | `/api/v1/ai/providers/{public_id}/secret` | Удаление секрета | Да | `ai.admin` | — |
| GET | `/api/v1/ai/models` | Список моделей | Да | `ai.admin` | — |
| POST | `/api/v1/ai/models/sync` | Синхронизация моделей | Да | `ai.admin` | — |
| GET | `/api/v1/ai/retention-policies` | Политики хранения AI | Да | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/retention-policies/{policy_code}` | Обновление политики | Да | `ai.admin` | — |

### AI — Settings & Preferences

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/settings` | AI-настройки | Да | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/settings` | Обновление AI-настроек | Да | `ai.admin` | — |
| GET | `/api/v1/ai/preferences` | AI-предпочтения | Да | `ai.use` | — |
| PATCH, PUT | `/api/v1/ai/preferences` | Обновление предпочтений | Да | `ai.use` | — |
| GET | `/api/v1/ai/action-types` | Типы AI-действий | Да | `ai.use` | — |
| GET | `/api/v1/ai/availability` | Доступность AI | Да | — | — |

### AI — Actions & Suggestions

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/ai/actions/{action_type}` | Выполнение AI-действия | Да | `ai.use` | Динамический тип |
| POST | `/api/v1/ai/tasks/{task_public_id}/summary` | AI-резюме задачи | Да | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/decompose` | AI-декомпозиция задачи | Да | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/checklist` | AI-чеклист задачи | Да | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/quality` | AI-оценка качества | Да | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/next-action` | AI-следующее действие | Да | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/comment-draft` | AI-черновик комментария | Да | `ai.use` | — |
| POST | `/api/v1/ai/tasks/priority` | AI-приоритизация | Да | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/summary` | AI-резюме проекта | Да | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/risks` | AI-риски проекта | Да | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/client-report` | AI-отчёт клиенту | Да | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/summary` | AI-резюме клиента | Да | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/meeting-prep` | AI-подготовка к встрече | Да | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/data-quality` | AI-качество данных клиента | Да | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/client-safe-report` | AI-safe-отчёт | Да | `ai.use` | — |
| POST | `/api/v1/ai/calendar/events/{event_public_id}/agenda` | AI-повестка встречи | Да | `ai.use` | — |
| POST | `/api/v1/ai/dashboard/digest` | AI-дайджест дашборда | Да | `ai.use` | — |
| POST | `/api/v1/ai/analytics/kpi-explanation` | AI-объяснение KPI | Да | `ai.use` | — |
| POST | `/api/v1/ai/analytics/risks-explanation` | AI-объяснение рисков | Да | `ai.use` | — |
| POST | `/api/v1/ai/analytics/team-workload-summary` | AI-сводка нагрузки | Да | `ai.use` | — |
| POST | `/api/v1/ai/admin/log-review` | AI-ревью логов | Да | `ai.admin` | — |
| POST | `/api/v1/ai/admin/webhook-health` | AI-здоровье вебхуков | Да | `ai.admin` | — |
| POST | `/api/v1/ai/admin/workflow-audit` | AI-аудит workflow | Да | `ai.admin` | — |
| POST | `/api/v1/ai/my-day/plan` | AI-план дня | Да | `ai.use` | — |
| POST | `/api/v1/ai/my-week/plan` | AI-план недели | Да | `ai.use` | — |
| POST | `/api/v1/ai/search/semantic` | Семантический поиск | Да | `ai.use` | — |

### AI — Suggestions Management

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/suggestions` | Список подсказок | Да | `ai.use` | — |
| GET | `/api/v1/ai/suggestions/{public_id}` | Детали подсказки | Да | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/dismiss` | Отклонение подсказки | Да | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/apply-preview` | Предпросмотр применения | Да | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/confirm` | Применение подсказки | Да | `ai.use` | — |

### AI — Intent, Prompts, Schemas, Usage, Jobs

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/intent-settings` | Настройки интентов | Да | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/intent-settings/{intent_code}` | Обновление интента | Да | `ai.admin` | — |
| GET | `/api/v1/ai/prompt-templates` | Шаблоны промптов | Да | `ai.admin` | — |
| POST | `/api/v1/ai/prompt-templates` | Создание шаблона | Да | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/prompt-templates/{public_id}` | Обновление шаблона | Да | `ai.admin` | — |
| GET | `/api/v1/ai/json-schemas` | JSON-схемы | Да | `ai.admin` | — |
| POST | `/api/v1/ai/json-schemas` | Создание схемы | Да | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/json-schemas/{public_id}` | Обновление схемы | Да | `ai.admin` | — |
| GET | `/api/v1/ai/usage` | Использование AI | Да | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/audit` | Аудит AI | Да | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/jobs` | AI-задачи | Да | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/jobs/{public_id}` | Детали AI-задачи | Да | `ai.view_cron_results` | — |
| POST | `/api/v1/ai/jobs/{public_id}/retry` | Повтор AI-задачи | Да | `ai.manage_cron_jobs` | — |
| POST | `/api/v1/ai/jobs/{job_code}/dry-run` | Dry-run AI-задачи | Да | `ai.manage_cron_jobs` | — |
| POST | `/api/v1/ai/jobs/{job_code}/run-once` | одноразовый запуск | Да | `ai.manage_cron_jobs` | — |

### Modules

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/modules` | Список модулей | Да | `settings.manage` | — |
| GET | `/api/v1/modules/{name}` | Информация о модуле | Да | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/install` | Установка модуля | Да | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/activate` | Активация модуля | Да | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/deactivate` | Деактивация модуля | Да | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/uninstall` | Удаление модуля | Да | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/purge` | Полное удаление модуля (с файлами) | Да | `settings.manage` | — |
| POST | `/api/v1/modules/bulk` | Массовое действие над модулями | Да | `settings.manage` | Body: `action` + `modules[]` |
| GET | `/api/v1/modules/{name}/config` | Конфигурация модуля | Да | `settings.manage` | — |
| PUT | `/api/v1/modules/{name}/config` | Обновление конфигурации | Да | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/health` | Health check модуля | Да | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/migrations` | Миграции модуля | Да | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/errors` | Ошибки модуля | Да | `settings.manage` | — |
| DELETE | `/api/v1/modules/{name}/errors` | Очистка ошибок | Да | `settings.manage` | — |
| POST | `/api/v1/modules/install-from-url` | Установка из URL | Да | `settings.manage` | — |
| POST | `/api/v1/modules/install-from-file` | Установка из файла | Да | `settings.manage` | `multipart/form-data` |

### Ideas

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ideas` | Список идей | Да | `idea.view` | Фильтры: `status`, `q` |
| POST | `/api/v1/ideas` | Создание идеи | Да | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}` | Детали идеи | Да | `idea.view` | — |
| PATCH | `/api/v1/ideas/{public_id}` | Обновление идеи | Да | `idea.manage` | — |
| DELETE | `/api/v1/ideas/{public_id}` | Удаление идеи | Да | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/vote` | Голосование | Да | `idea.manage` | — |
| PATCH | `/api/v1/ideas/{public_id}/status` | Смена статуса | Да | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/reset-analysis` | Сброс AI-анализа | Да | `idea.manage` | — |
| GET, DELETE | `/api/v1/ideas/{public_id}/debug-log` | Debug-лог | Да | `ai.admin` | — |
| GET | `/api/v1/ideas/{public_id}/questions` | Вопросы интервью | Да | `idea.view` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/additional-questions` | Доп. вопросы | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/gap-questions` | Gap-вопросы | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/understanding-card` | Карточка понимания | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/refined-card` | Уточнённая карточка | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/potential` | Оценка потенциала | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/risk-report` | Отчёт о рисках | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/pitfalls` | Анализ pitfalls | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/implementation-plan` | План реализации | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/final-recommendation` | Финальная рекомендация | Да | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/suggested-tasks` | Предложенные задачи | Да | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/create-project-tasks` | Создание задач из идеи | Да | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}/ai-iterations` | Итерации AI | Да | `idea.view` | — |
| POST, DELETE | `/api/v1/ideas/{public_id}/interview` | AI-интервью | Да | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/interview-answers` | Сохранение ответов | Да | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/comments` | Добавление комментария | Да | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}/comments` | Комментарии идеи | Да | `idea.view` | — |

### Chat

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/chats` | Список чатов | Да | `chat.use` | — |
| POST | `/api/v1/chats` | Создание чата | Да | `chat.use` | — |
| GET | `/api/v1/chats/unread-count` | Непрочитанные сообщения | Да | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}` | Детали чата | Да | `chat.use` | — |
| PATCH | `/api/v1/chats/{public_id}/settings` | Настройки чата | Да | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}/participants` | Участники чата | Да | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}/messages` | Сообщения чата | Да | `chat.use` | Cursor-based |
| POST | `/api/v1/chats/{public_id}/messages` | Отправка сообщения | Да | `chat.use` | — |
| PATCH | `/api/v1/chats/{public_id}/messages/{message_public_id}` | Редактирование сообщения | Да | `chat.use` | — |
| DELETE | `/api/v1/chats/{public_id}/messages/{message_public_id}` | Удаление сообщения | Да | `chat.use` | Soft-delete |
| POST | `/api/v1/chats/{public_id}/attachments` | Загрузка вложения | Да | `chat.use` | `multipart/form-data` |
| GET | `/api/v1/chats/{public_id}/attachments/{file_public_id}/download` | Скачивание вложения | Да | `chat.use` | Binary |
| POST | `/api/v1/chats/{public_id}/read` | Отметить как прочитанный | Да | `chat.use` | — |
| POST | `/api/v1/chats/{public_id}/archive` | Архивация чата | Да | `chat.use` | — |
| POST | `/api/v1/chats/{public_id}/restore` | Восстановление чата | Да | `chat.use` | — |

### Module: Миграция из ActiveCollab (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.activecollab-migration/connections` | Список подключений | Да | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/connections` | Создание подключения | Да | `module.activecollab-migration.manage`, `module.activecollab-migration.secret_manage` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}` | Детали подключения | Да | `module.activecollab-migration.view` | — |
| PATCH | `/_module/crm.activecollab-migration/connections/{public_id}` | Обновление подключения | Да | `module.activecollab-migration.manage`, `module.activecollab-migration.secret_manage` | — |
| DELETE | `/_module/crm.activecollab-migration/connections/{public_id}` | Удаление подключения | Да | `module.activecollab-migration.delete` | — |
| POST | `/_module/crm.activecollab-migration/connections/{public_id}/test` | Тест подключения | Да | `module.activecollab-migration.manage` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}/workspaces` | Список рабочих пространств | Да | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.activecollab-migration.run` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.activecollab-migration.view` | — |
| PATCH | `/_module/crm.activecollab-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.activecollab-migration.manage` | — |
| GET | `/_module/crm.activecollab-migration/jobs` | Список задач миграции | Да | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/jobs` | Создание задачи миграции | Да | `module.activecollab-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.activecollab-migration.delete` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.activecollab-migration.view` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.activecollab-migration.view` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.activecollab-migration.report_view` | — |

### Module: Миграция из Asana (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.asana-migration/connections` | Список подключений | Да | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/connections` | Создание подключения | Да | `module.asana-migration.manage`, `module.asana-migration.secret_manage` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}` | Детали подключения | Да | `module.asana-migration.view` | — |
| PATCH | `/_module/crm.asana-migration/connections/{public_id}` | Обновление подключения | Да | `module.asana-migration.manage`, `module.asana-migration.secret_manage` | — |
| DELETE | `/_module/crm.asana-migration/connections/{public_id}` | Удаление подключения | Да | `module.asana-migration.delete` | — |
| POST | `/_module/crm.asana-migration/connections/{public_id}/test` | Тест подключения | Да | `module.asana-migration.manage` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}/workspaces` | Список рабочих пространств | Да | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.asana-migration.run` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.asana-migration.view` | — |
| PATCH | `/_module/crm.asana-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.asana-migration.manage` | — |
| GET | `/_module/crm.asana-migration/jobs` | Список задач миграции | Да | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/jobs` | Создание задачи миграции | Да | `module.asana-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.asana-migration.delete` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.asana-migration.view` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.asana-migration.view` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.asana-migration.report_view` | — |

### Module: Миграция из Битрикс24 (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.bitrix24-migration/connections` | Список подключений | Да | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/connections` | Создание подключения | Да | `module.bitrix24-migration.manage`, `module.bitrix24-migration.secret_manage` | — |
| GET | `/_module/crm.bitrix24-migration/connections/{public_id}` | Детали подключения | Да | `module.bitrix24-migration.view` | — |
| PATCH | `/_module/crm.bitrix24-migration/connections/{public_id}` | Обновление подключения | Да | `module.bitrix24-migration.manage`, `module.bitrix24-migration.secret_manage` | — |
| DELETE | `/_module/crm.bitrix24-migration/connections/{public_id}` | Удаление подключения | Да | `module.bitrix24-migration.delete` | — |
| POST | `/_module/crm.bitrix24-migration/connections/{public_id}/test` | Тест подключения | Да | `module.bitrix24-migration.manage` | — |
| POST | `/_module/crm.bitrix24-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.bitrix24-migration.run` | — |
| GET | `/_module/crm.bitrix24-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.bitrix24-migration.view` | — |
| PATCH | `/_module/crm.bitrix24-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.bitrix24-migration.manage` | — |
| GET | `/_module/crm.bitrix24-migration/jobs` | Список задач миграции | Да | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/jobs` | Создание задачи миграции | Да | `module.bitrix24-migration.run` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.bitrix24-migration.delete` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.bitrix24-migration.view` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.bitrix24-migration.view` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.bitrix24-migration.report_view` | — |

### Module: Миграция из ClickUp (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.clickup-migration/oauth/authorize-url` | OAuth URL авторизации | Да | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| POST | `/_module/crm.clickup-migration/oauth/exchange` | Обмен OAuth-кода | Да | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| GET | `/_module/crm.clickup-migration/connections` | Список подключений | Да | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/connections` | Создание подключения | Да | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}` | Детали подключения | Да | `module.clickup-migration.view` | — |
| PATCH | `/_module/crm.clickup-migration/connections/{public_id}` | Обновление подключения | Да | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| DELETE | `/_module/crm.clickup-migration/connections/{public_id}` | Удаление подключения | Да | `module.clickup-migration.delete` | — |
| POST | `/_module/crm.clickup-migration/connections/{public_id}/test` | Тест подключения | Да | `module.clickup-migration.manage` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}/projects` | Обнаружение данных | Да | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.clickup-migration.view` | — |
| PATCH | `/_module/crm.clickup-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.clickup-migration.manage` | — |
| GET | `/_module/crm.clickup-migration/jobs` | Список задач миграции | Да | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/jobs` | Создание задачи миграции | Да | `module.clickup-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.clickup-migration.delete` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.clickup-migration.report_view` | — |

### Module: Миграция из Confluence (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.confluence-migration/connections` | Список подключений | Да | — | — |
| POST | `/_module/crm.confluence-migration/connections` | Создание подключения | Да | `module.confluence-migration.manage`, `module.confluence-migration.secret_manage` | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}` | Детали подключения | Да | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}` | Обновление подключения | Да | `module.confluence-migration.manage`, `module.confluence-migration.secret_manage` | — |
| DELETE | `/_module/crm.confluence-migration/connections/{public_id}` | Удаление подключения | Да | `module.confluence-migration.delete` | — |
| POST | `/_module/crm.confluence-migration/connections/{public_id}/test` | Тест подключения | Да | `module.confluence-migration.manage` | — |
| POST | `/_module/crm.confluence-migration/connections/{public_id}/discover` | Обнаружение пространств | Да | `module.confluence-migration.run` | — |
| GET | `/_module/crm.confluence-migration/jobs` | Список задач миграции | Да | — | — |
| POST | `/_module/crm.confluence-migration/jobs` | Создание задачи миграции | Да | `module.confluence-migration.run`, `knowledge.import`, `knowledge.create`, `knowledge.edit`, `knowledge.publish` | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}` | Детали задачи миграции | Да | — | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/start` | Запуск задачи | Да | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.confluence-migration.run` | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/items` | Элементы задачи | Да | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/logs` | Логи задачи | Да | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/report` | Отчёт задачи | Да | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/unresolved-links` | Неразрешённые ссылки | Да | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/unsupported-macros` | Неподдерживаемые макросы | Да | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/download-report` | Скачивание отчёта | Да | — | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.confluence-migration.manage` | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}/group-mappings` | Маппинги групп | Да | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}/group-mappings/{mapping_id}` | Обновление маппинга группы | Да | `module.confluence-migration.manage` | — |
| GET | `/_module/crm.confluence-migration/settings` | Настройки модуля | Да | — | — |
| PATCH | `/_module/crm.confluence-migration/settings` | Обновление настроек | Да | `module.confluence-migration.manage` | — |

### Module: Диаграммы draw.io (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.drawio/diagrams` | Список диаграмм | Да | `module.drawio.view` | — |
| POST | `/_module/crm.drawio/diagrams` | Создание диаграммы | Да | `module.drawio.manage` | — |
| GET | `/_module/crm.drawio/diagrams/{public_id}` | Детали диаграммы | Да | `module.drawio.view` | — |
| PATCH | `/_module/crm.drawio/diagrams/{public_id}` | Обновление диаграммы | Да | `module.drawio.manage` | — |
| DELETE | `/_module/crm.drawio/diagrams/{public_id}` | Удаление диаграммы | Да | `module.drawio.manage` | — |

### Module: GitHub (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.github-integration/connections` | Список подключений | Да | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/connections` | Создание подключения | Да | `module.github-integration.manage`, `module.github-integration.secret_manage` | — |
| PATCH | `/_module/crm.github-integration/connections/{public_id}` | Обновление подключения | Да | `module.github-integration.manage` | — |
| DELETE | `/_module/crm.github-integration/connections/{public_id}` | Удаление подключения | Да | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/connections/{public_id}/test` | Тест подключения | Да | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/connections/{public_id}/discover` | Обнаружение репозиториев | Да | `module.github-integration.manage` | — |
| GET | `/_module/crm.github-integration/links` | Список связей с репозиториями | Да | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/links` | Создание связи с репозиторием | Да | `module.github-integration.manage`, `project.manage`, `task.manage` | — |
| DELETE | `/_module/crm.github-integration/links/{public_id}` | Удаление связи | Да | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/links/{public_id}/sync` | Синхронизация сейчас | Да | `module.github-integration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.github-integration/links/{public_id}/logs` | Логи связи | Да | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/webhook/{public_id}` | Входящий вебхук | Нет | — | HMAC-проверка |

### Module: GitLab (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.gitlab-integration/connections` | Список подключений | Да | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/connections` | Создание подключения | Да | `module.gitlab-integration.manage`, `module.gitlab-integration.secret_manage` | — |
| PATCH | `/_module/crm.gitlab-integration/connections/{public_id}` | Обновление подключения | Да | `module.gitlab-integration.manage` | — |
| DELETE | `/_module/crm.gitlab-integration/connections/{public_id}` | Удаление подключения | Да | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/connections/{public_id}/test` | Тест подключения | Да | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/connections/{public_id}/discover` | Обнаружение проектов | Да | `module.gitlab-integration.manage` | — |
| GET | `/_module/crm.gitlab-integration/links` | Список связей с проектами | Да | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/links` | Создание связи с проектом | Да | `module.gitlab-integration.manage`, `project.manage`, `task.manage` | — |
| DELETE | `/_module/crm.gitlab-integration/links/{public_id}` | Удаление связи | Да | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/links/{public_id}/sync` | Синхронизация сейчас | Да | `module.gitlab-integration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.gitlab-integration/links/{public_id}/logs` | Логи связи | Да | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/webhook/{public_id}` | Входящий вебхук | Нет | — | Проверка токена |

### Module: Google Calendar (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.google-calendar/oauth/start` | OAuth-старт | Да | `module.google-calendar.manage` | — |
| GET | `/_module/crm.google-calendar/oauth/callback` | OAuth-callback | Да | `module.google-calendar.manage` | — |
| GET | `/_module/crm.google-calendar/connections` | Список подключений | Да | `module.google-calendar.view` | — |
| PUT | `/_module/crm.google-calendar/credentials` | Сохранение credentials | Да | `module.google-calendar.manage` | — |
| DELETE | `/_module/crm.google-calendar/credentials` | Удаление credentials | Да | `module.google-calendar.manage` | — |
| DELETE | `/_module/crm.google-calendar/connections/{public_id}` | Отключение | Да | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/connections/{public_id}/test` | Тест подключения | Да | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/connections/{public_id}/sync` | Синхронизация | Да | `module.google-calendar.sync` | — |
| PATCH | `/_module/crm.google-calendar/calendars/{public_id}` | Обновление календаря | Да | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/webhook` | Приём webhook | Нет | — | — |

### Module: Миграция из Jira (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.jira-migration/connections` | Список подключений | Да | — | — |
| POST | `/_module/crm.jira-migration/connections` | Создание подключения | Да | `module.jira-migration.manage`, `module.jira-migration.secret_manage` | — |
| GET | `/_module/crm.jira-migration/connections/{public_id}` | Детали подключения | Да | — | — |
| PATCH | `/_module/crm.jira-migration/connections/{public_id}` | Обновление подключения | Да | `module.jira-migration.manage`, `module.jira-migration.secret_manage` | — |
| DELETE | `/_module/crm.jira-migration/connections/{public_id}` | Удаление подключения | Да | `module.jira-migration.delete` | — |
| POST | `/_module/crm.jira-migration/connections/{public_id}/test` | Тест подключения | Да | `module.jira-migration.manage` | — |
| POST | `/_module/crm.jira-migration/discover` | Обнаружение данных | Да | `module.jira-migration.view` | — |
| POST | `/_module/crm.jira-migration/dry-run` | Dry-run миграции | Да | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs` | Список задач миграции | Да | — | — |
| POST | `/_module/crm.jira-migration/jobs` | Создание задачи миграции | Да | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}` | Детали задачи миграции | Да | — | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/items` | Элементы задачи | Да | — | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/logs` | Логи задачи | Да | — | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/report` | Отчёт задачи | Да | — | — |
| GET | `/_module/crm.jira-migration/mappings` | Маппинги | Да | — | — |
| POST | `/_module/crm.jira-migration/mappings/discover` | Обнаружение маппингов | Да | `module.jira-migration.manage` | — |
| PATCH | `/_module/crm.jira-migration/mappings/{public_id}` | Обновление маппинга | Да | `module.jira-migration.manage` | — |
| GET | `/_module/crm.jira-migration/unresolved` | Неразрешённые элементы | Да | — | — |

### Module: Миграция из Kaiten (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.kaiten-migration/connections` | Список подключений | Да | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/connections` | Создание подключения | Да | `module.kaiten-migration.manage`, `module.kaiten-migration.secret_manage` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}` | Детали подключения | Да | `module.kaiten-migration.view` | — |
| PATCH | `/_module/crm.kaiten-migration/connections/{public_id}` | Обновление подключения | Да | `module.kaiten-migration.manage`, `module.kaiten-migration.secret_manage` | — |
| DELETE | `/_module/crm.kaiten-migration/connections/{public_id}` | Удаление подключения | Да | `module.kaiten-migration.delete` | — |
| POST | `/_module/crm.kaiten-migration/connections/{public_id}/test` | Тест подключения | Да | `module.kaiten-migration.manage` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/spaces` | Список пространств | Да | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/workspaces` | Список рабочих пространств | Да | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.kaiten-migration.run` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.kaiten-migration.view` | — |
| PATCH | `/_module/crm.kaiten-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.kaiten-migration.manage` | — |
| GET | `/_module/crm.kaiten-migration/jobs` | Список задач миграции | Да | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/jobs` | Создание задачи миграции | Да | `module.kaiten-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.kaiten-migration.delete` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.kaiten-migration.report_view` | — |

### Module: Миграция из Linear (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.linear-migration/connections` | Список подключений | Да | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/connections` | Создание подключения | Да | `module.linear-migration.manage`, `module.linear-migration.secret_manage` | — |
| GET | `/_module/crm.linear-migration/connections/{public_id}` | Детали подключения | Да | `module.linear-migration.view` | — |
| PATCH | `/_module/crm.linear-migration/connections/{public_id}` | Обновление подключения | Да | `module.linear-migration.manage` | — |
| DELETE | `/_module/crm.linear-migration/connections/{public_id}` | Удаление подключения | Да | `module.linear-migration.delete` | — |
| POST | `/_module/crm.linear-migration/connections/{public_id}/test` | Тест подключения | Да | `module.linear-migration.manage` | — |
| POST | `/_module/crm.linear-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.linear-migration.run` | — |
| GET | `/_module/crm.linear-migration/jobs` | Список задач миграции | Да | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/jobs` | Создание задачи миграции | Да | `module.linear-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}` | Детали задачи | Да | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.linear-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.linear-migration.view` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.linear-migration.view` | — |

### Module: Миграция из Notion (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.notion-migration/connections` | Список подключений | Да | — | — |
| POST | `/_module/crm.notion-migration/connections` | Создание подключения | Да | `module.notion-migration.manage`, `module.notion-migration.secret_manage` | — |
| GET | `/_module/crm.notion-migration/connections/{public_id}` | Детали подключения | Да | — | — |
| PATCH | `/_module/crm.notion-migration/connections/{public_id}` | Обновление подключения | Да | `module.notion-migration.manage`, `module.notion-migration.secret_manage` | — |
| DELETE | `/_module/crm.notion-migration/connections/{public_id}` | Удаление подключения | Да | `module.notion-migration.delete` | — |
| POST | `/_module/crm.notion-migration/connections/{public_id}/test` | Тест подключения | Да | `module.notion-migration.manage` | — |
| POST | `/_module/crm.notion-migration/connections/{public_id}/discover` | Обнаружение объектов | Да | `module.notion-migration.run` | — |
| GET | `/_module/crm.notion-migration/jobs` | Список задач миграции | Да | — | — |
| POST | `/_module/crm.notion-migration/jobs` | Создание задачи миграции | Да | `module.notion-migration.run`, `knowledge.import`, `knowledge.create`, `knowledge.edit`, `knowledge.publish` | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}` | Детали задачи | Да | — | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/start` | Запуск задачи | Да | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.notion-migration.run` | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/items` | Элементы задачи | Да | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/logs` | Логи задачи | Да | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/report` | Отчёт задачи | Да | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/download-report` | Скачивание отчёта | Да | — | — |
| GET | `/_module/crm.notion-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | — | — |
| PATCH | `/_module/crm.notion-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга | Да | `module.notion-migration.manage` | — |
| GET | `/_module/crm.notion-migration/settings` | Настройки модуля | Да | — | — |
| PATCH | `/_module/crm.notion-migration/settings` | Обновление настроек | Да | `module.notion-migration.manage` | — |

### Module: Raycast (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.raycast/config` | Конфигурация MCP-подключения | Да | `module.raycast.view` | — |

### Module: Миграция из Shtab.app (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.shtab-migration/connections` | Список подключений | Да | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/connections` | Создание подключения | Да | `module.shtab-migration.manage` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}` | Детали подключения | Да | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/test` | Тест подключения | Да | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/crm-users` | CRM-пользователи | Да | `module.shtab-migration.view` | — |
| PATCH | `/_module/crm.shtab-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.shtab-migration.manage` | — |
| DELETE | `/_module/crm.shtab-migration/connections/{public_id}` | Удаление подключения | Да | `module.shtab-migration.delete` | — |
| GET | `/_module/crm.shtab-migration/jobs` | Список задач миграции | Да | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/jobs` | Создание задачи миграции | Да | `module.shtab-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.shtab-migration.delete` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.shtab-migration.report_view` | — |

### Module: Уведомления в Slack (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.slack-integration/connections` | Список подключений | Да | `module.slack-integration.view` | — |
| POST | `/_module/crm.slack-integration/connections` | Создание подключения | Да | `module.slack-integration.manage`, `module.slack-integration.secret_manage` | — |
| GET | `/_module/crm.slack-integration/connections/{public_id}` | Детали подключения | Да | `module.slack-integration.view` | — |
| PATCH | `/_module/crm.slack-integration/connections/{public_id}` | Обновление подключения | Да | `module.slack-integration.manage` | — |
| DELETE | `/_module/crm.slack-integration/connections/{public_id}` | Удаление подключения | Да | `module.slack-integration.manage` | — |
| POST | `/_module/crm.slack-integration/connections/{public_id}/test` | Тест подключения | Да | `module.slack-integration.manage` | — |
| GET | `/_module/crm.slack-integration/rules` | Список правил уведомлений | Да | `module.slack-integration.view` | — |
| POST | `/_module/crm.slack-integration/rules` | Создание правила | Да | `module.slack-integration.manage` | — |
| DELETE | `/_module/crm.slack-integration/rules/{public_id}` | Удаление правила | Да | `module.slack-integration.manage` | — |
| POST | `/_module/crm.slack-integration/notify` | Отправка уведомления (workflow) | Нет | — | Серверный вызов |
| GET | `/_module/crm.slack-integration/deliveries` | Список доставок | Да | `module.slack-integration.view` | — |

### Module: Миграция из Todoist (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.todoist-migration/oauth/authorize-url` | OAuth URL авторизации | Да | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| POST | `/_module/crm.todoist-migration/oauth/exchange` | Обмен OAuth-кода | Да | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| GET | `/_module/crm.todoist-migration/connections` | Список подключений | Да | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/connections` | Создание подключения | Да | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}` | Детали подключения | Да | `module.todoist-migration.view` | — |
| PATCH | `/_module/crm.todoist-migration/connections/{public_id}` | Обновление подключения | Да | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| DELETE | `/_module/crm.todoist-migration/connections/{public_id}` | Удаление подключения | Да | `module.todoist-migration.delete` | — |
| POST | `/_module/crm.todoist-migration/connections/{public_id}/test` | Тест подключения | Да | `module.todoist-migration.manage` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}/projects` | Обнаружение данных | Да | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.todoist-migration.view` | — |
| PATCH | `/_module/crm.todoist-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.todoist-migration.manage` | — |
| GET | `/_module/crm.todoist-migration/jobs` | Список задач миграции | Да | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/jobs` | Создание задачи миграции | Да | `module.todoist-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.todoist-migration.delete` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.todoist-migration.report_view` | — |

### Module: Миграция из Toggl (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.toggl-migration/connections` | Список подключений | Да | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/connections` | Создание подключения | Да | `module.toggl-migration.manage`, `module.toggl-migration.secret_manage` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}` | Детали подключения | Да | `module.toggl-migration.view` | — |
| PATCH | `/_module/crm.toggl-migration/connections/{public_id}` | Обновление подключения | Да | `module.toggl-migration.manage`, `module.toggl-migration.secret_manage` | — |
| DELETE | `/_module/crm.toggl-migration/connections/{public_id}` | Удаление подключения | Да | `module.toggl-migration.delete` | — |
| POST | `/_module/crm.toggl-migration/connections/{public_id}/test` | Тест подключения | Да | `module.toggl-migration.manage` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}/workspaces` | Список рабочих пространств | Да | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.toggl-migration.run` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.toggl-migration.view` | — |
| PATCH | `/_module/crm.toggl-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.toggl-migration.manage` | — |
| GET | `/_module/crm.toggl-migration/jobs` | Список задач миграции | Да | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/jobs` | Создание задачи миграции | Да | `module.toggl-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.toggl-migration.delete` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.toggl-migration.view` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.toggl-migration.view` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.toggl-migration.report_view` | — |

### Module: Миграция из Trello (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.trello-migration/connections` | Список подключений | Да | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/connections` | Создание подключения | Да | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}` | Детали подключения | Да | `module.trello-migration.view` | — |
| PATCH | `/_module/crm.trello-migration/connections/{public_id}` | Обновление подключения | Да | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| DELETE | `/_module/crm.trello-migration/connections/{public_id}` | Удаление подключения | Да | `module.trello-migration.delete` | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/test` | Тест подключения | Да | `module.trello-migration.manage` | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.trello-migration.run` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.trello-migration.view` | — |
| PATCH | `/_module/crm.trello-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.trello-migration.manage` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}/board-configs` | Конфигурации досок | Да | `module.trello-migration.view` | — |
| PUT | `/_module/crm.trello-migration/connections/{public_id}/board-configs/{board_id}` | Сохранение конфигурации доски | Да | `module.trello-migration.manage` | — |
| GET | `/_module/crm.trello-migration/jobs` | Список задач миграции | Да | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/jobs` | Создание задачи миграции | Да | `module.trello-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.trello-migration.delete` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.trello-migration.view` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.trello-migration.view` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.trello-migration.report_view` | — |
| POST | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | Приём webhook | Нет | — | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/webhooks` | Создание webhook | Да | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| DELETE | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | Удаление webhook | Да | `module.trello-migration.manage` | — |
| HEAD | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | Проверка webhook | Нет | — | — |

### Module: WIP Limit (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.wip-limit/limits` | Список лимитов | Да | — | — |
| GET | `/_module/crm.wip-limit/limits/{user_id}` | Лимит пользователя | Да | — | — |
| POST | `/_module/crm.wip-limit/limits` | Установка лимита | Да | — | — |
| DELETE | `/_module/crm.wip-limit/limits/{user_id}` | Удаление лимита | Да | — | — |
| GET | `/_module/crm.wip-limit/summary` | Сводка по лимитам | Да | — | — |

### Module: Миграция из Worksection (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.worksection-migration/connections` | Список подключений | Да | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/connections` | Создание подключения | Да | `module.worksection-migration.manage`, `module.worksection-migration.secret_manage` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}` | Детали подключения | Да | `module.worksection-migration.view` | — |
| PATCH | `/_module/crm.worksection-migration/connections/{public_id}` | Обновление подключения | Да | `module.worksection-migration.manage`, `module.worksection-migration.secret_manage` | — |
| DELETE | `/_module/crm.worksection-migration/connections/{public_id}` | Удаление подключения | Да | `module.worksection-migration.delete` | — |
| POST | `/_module/crm.worksection-migration/connections/{public_id}/test` | Тест подключения | Да | `module.worksection-migration.manage` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}/workspaces` | Список рабочих пространств | Да | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/connections/{public_id}/discover` | Обнаружение данных | Да | `module.worksection-migration.run` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}/user-mappings` | Маппинги пользователей | Да | `module.worksection-migration.view` | — |
| PATCH | `/_module/crm.worksection-migration/connections/{public_id}/user-mappings/{mapping_id}` | Обновление маппинга пользователя | Да | `module.worksection-migration.manage` | — |
| GET | `/_module/crm.worksection-migration/jobs` | Список задач миграции | Да | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/jobs` | Создание задачи миграции | Да | `module.worksection-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}` | Детали задачи миграции | Да | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/run` | Запуск задачи | Да | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/pause` | Пауза задачи | Да | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/resume` | Возобновление задачи | Да | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/cancel` | Отмена задачи | Да | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/retry-failed` | Повтор неудачных элементов | Да | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/rollback` | Откат задачи | Да | `module.worksection-migration.delete` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/items` | Элементы задачи | Да | `module.worksection-migration.view` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/logs` | Логи задачи | Да | `module.worksection-migration.view` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/report` | Отчёт задачи | Да | `module.worksection-migration.report_view` | — |

### Module: Яндекс.Календарь (если установлен)

| Метод | Endpoint | Назначение | Auth | Permissions | Описание |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.yandex-calendar/connections` | Подключение | Да | `module.yandex-calendar.manage` | — |
| GET | `/_module/crm.yandex-calendar/connections` | Список подключений | Да | `module.yandex-calendar.view` | — |
| DELETE | `/_module/crm.yandex-calendar/connections/{public_id}` | Отключение | Да | `module.yandex-calendar.manage` | — |
| POST | `/_module/crm.yandex-calendar/connections/{public_id}/test` | Тест подключения | Да | `module.yandex-calendar.manage` | — |
| POST | `/_module/crm.yandex-calendar/connections/{public_id}/sync` | Синхронизация | Да | `module.yandex-calendar.sync` | — |
| PATCH | `/_module/crm.yandex-calendar/calendars/{public_id}` | Обновление календаря | Да | `module.yandex-calendar.manage` | — |

