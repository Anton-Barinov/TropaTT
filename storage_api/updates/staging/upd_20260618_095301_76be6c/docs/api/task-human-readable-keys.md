# Человекочитаемые ключи задач (Task Human-Readable Keys)

## Что такое task_key

`task_key` — это короткий человекочитаемый идентификатор задачи в формате:

```text
PROJECT-123
```

Где `PROJECT` — код проекта (`task_key_prefix`), а `123` — порядковый номер задачи внутри области нумерации.

## Отличие от public_id

| Поле         | Формат          | Назначение                              |
|-------------|-----------------|-----------------------------------------|
| `public_id` | `tsk_...`       | Стабильный API-идентификатор            |
| `task_key`  | `CRM-123`       | Человекочитаемый ключ для обсуждений    |

`public_id` остаётся основным идентификатором для API-вызовов. `task_key` — дополнительное поле.

## Формат ключа

```regex
^[A-Z][A-Z0-9]{1,9}-[1-9][0-9]*$
```

- Prefix: 2–10 символов, только заглавные латиница и цифры, первый символ — буква
- Разделитель: `-`
- Номер: от 1

Примеры: `CRM-1`, `WEB-42`, `DEVOPS-100`, `TASK-1`

## Где возвращается task_key

Task key возвращается во всех task endpoint'ах:

| Endpoint                                          | Поле                          |
|---------------------------------------------------|-------------------------------|
| `GET /api/v1/tasks`                               | `items[].task_key`            |
| `POST /api/v1/tasks`                              | `task.task_key`               |
| `GET /api/v1/tasks/{public_id}`                   | `task.task_key`               |
| `PATCH /api/v1/tasks/{public_id}`                 | `task.task_key`               |
| `GET /api/v1/tasks/board`                         | `board[].columns[].tasks[].task_key` |
| `GET /api/v1/tasks/by-key/{task_key}`             | `task.task_key`               |
| `POST /api/v1/tasks/{public_id}/move`             | `task.task_key`               |

## Project task_key_prefix

| Endpoint                                          | Поле                          |
|---------------------------------------------------|-------------------------------|
| `GET /api/v1/projects`                            | `items[].task_key_prefix`     |
| `POST /api/v1/projects`                           | `project.task_key_prefix`     |
| `GET /api/v1/projects/{public_id}`                | `project.task_key_prefix`     |
| `PATCH /api/v1/projects/{public_id}`              | `project.task_key_prefix`     |

### Создание проекта с prefix

```bash
curl -X POST "https://demo.tropatt.com/api/index.php?route=api/v1/projects" \
  -H "Content-Type: application/json" \
  --data '{"title": "CRM System", "task_key_prefix": "CRM"}'
```

Если `task_key_prefix` не указан, он генерируется автоматически из названия проекта.

### Ошибки валидации prefix

| Код                                  | HTTP | Описание                              |
|--------------------------------------|------|---------------------------------------|
| `PROJECT_TASK_PREFIX_INVALID`        | 422  | Неверный формат prefix                |
| `PROJECT_TASK_PREFIX_ALREADY_EXISTS` | 409  | Prefix уже используется другим проектом |
| `PROJECT_TASK_PREFIX_RESERVED`       | 422  | Prefix зарезервирован для системы     |

Зарезервированные prefix: `TASK`, `SYS`, `API`.

## Поиск задачи по ключу

### Endpoint: `GET /api/v1/tasks/by-key/{task_key}`

```bash
curl "https://demo.tropatt.com/api/index.php?route=api/v1/tasks/by-key/CRM-1"
```

**Успешный ответ (200):**

```json
{
  "success": true,
  "code": "TASK_DETAIL",
  "data": {
    "task": {
      "public_id": "tsk_...",
      "task_key": "CRM-1",
      "title": "Prepare contract",
      "project_public_id": "prj_..."
    }
  }
}
```

**Ошибки:**

| Код               | HTTP | Описание                       |
|-------------------|------|--------------------------------|
| `TASK_KEY_INVALID`| 422  | Неверный формат ключа          |
| `TASK_NOT_FOUND`  | 404  | Задача не найдена или нет доступа |

### Поиск в списке задач

```bash
curl "https://demo.tropatt.com/api/index.php?route=api/v1/tasks&search=CRM-1"
```

Если поисковый запрос выглядит как task key (`CRM-123`), выполняется точный поиск по `task_key`. При обычном поиске `task_key` также участвует в `LIKE`-поиске.

## Поведение при смене prefix проекта

При изменении `task_key_prefix` проекта:
- Старые ключи задач НЕ меняются
- Новые задачи получают новый prefix, но номер продолжает счётчик проекта

Пример:
```
Было: CRM-1, CRM-2
Prefix изменён на WEB
Следующая задача: WEB-3
```

## Задачи без проекта

Задачи без `project_id` получают глобальный prefix `TASK`:
```
TASK-1, TASK-2, TASK-3
```

## Защита от редактирования

Поля `task_key`, `task_key_prefix`, `task_sequence_number` нельзя изменить через `PATCH /api/v1/tasks/{public_id}`. При попытке вернётся ошибка:

```json
{
  "success": false,
  "code": "TASK_KEY_FIELD_NOT_EDITABLE",
  "message": "Task key fields are not editable"
}
```
