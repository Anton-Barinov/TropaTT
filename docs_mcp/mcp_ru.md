# Справочник MCP-сервера TropaTT

> Полная документация MCP-сервера (Model Context Protocol), встроенного в TropaTT CRM.

## Обзор

TropaTT CRM включает встроенный MCP-сервер — интерфейс JSON-RPC 2.0, который предоставляет AI-агентам и моделям доступ к функциям CRM (задачи, проекты, база знаний, чаты, пользователи, AI и т. д.) через безопасный, контролируемый слой с проверкой прав доступа.

| | |
|---|---|
| Протокол | JSON-RPC 2.0 |
| Транспорт | HTTP POST (request–response) |
| Endpoint | `POST /api/index.php?route=api/v1/mcp` |
| Версия протокола | `2025-06-18` |
| Batch-запросы | Поддерживаются (JSON-массив) |
| Notifications | Поддерживаются (сообщения без id) |
| MCP tools | 567 |
| MCP resources | 5 |
| MCP prompts | 0 |

---

## Что такое MCP в этом проекте

MCP дублирует функционал REST API, но обеспечивает безопасный слой между агентами и CRM:

- Изолирует агентов от прямого доступа к базе данных
- Фильтрует чувствительные данные (токены, хеши паролей, ключи API, локальные пути)
- Ограничивает инструменты RBAC-разрешениями текущего пользователя
- Возвращает компактные машиночитаемые ответы вместо полных дампов БД
- Не раскрывает внутренние числовые ID, кроме публичных полей

---

## Transport и протокол

- **Protocol**: JSON-RPC 2.0
- **Endpoint**: `POST /api/index.php?route=api/v1/mcp`
- **Protocol version**: `2025-06-18`
- **Content-Type**: `application/json`
- **Accept**: `application/json, text/event-stream`

### Запрос

```json
{
    "jsonrpc": "2.0",
    "id": "unique-id",
    "method": "tools/call",
    "params": {
        "name": "tool_name",
        "arguments": {}
    }
}
```

### Пакетный запрос

```json
[
  {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"crm_get_current_user","arguments":{}}},
  {"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"crm_list_tasks","arguments":{"limit":5}}}
]
```

### Ответ

```json
{
    "jsonrpc": "2.0",
    "id": "unique-id",
    "result": {}
}
```

### Уведомление (без ответа)

```json
{"jsonrpc":"2.0","method":"notifications/initialized"}
```

---

## Авторизация и безопасность

### Authentication

- **Authorization: Bearer `<access_token>`**
- Используется тот же токен, что и для REST API
- Поддерживаются пользовательские токены и ключи API-клиентов

### RBAC

Каждый tool проверяет permissions текущего пользователя; tools с условием видимости показываются в tools/list только при наличии прав.

### Опасные tools (write/admin)

Все write-tools (create/update/delete) требуют соответствующих разрешений. Работа с пользователями, ролями, модулями, обновлением ядра и кешем — только для администратора. Смена пароля и 2FA — только для текущего пользователя. Impersonation — только для администратора.

### Audit log

AI-действия логируются через AiJobService/AiAuditService; импорт/экспорт и запуски workflow также логируются.

---

## Общие форматы MCP

### Список tools

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "result": {
        "tools": [
            {
                "name": "crm_get_current_user",
                "description": "Get the authenticated CRM user profile and permission codes visible to MCP.",
                "inputSchema": {
                    "type": "object",
                    "properties": {}
                }
            }
        ]
    }
}
```

### Вызов tool

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
        "name": "crm_create_task",
        "arguments": {
            "title": "New task",
            "priority": "high"
        }
    }
}
```

### Ответ вызова tool

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "{\"public_id\":\"abc123\",\"title\":\"New task\",\"status\":\"new\"}"
            }
        ]
    }
}
```

### Ошибка

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "error": {
        "code": -32600,
        "message": "Invalid Request"
    }
}
```

### Коды ошибок

| Code | Причина |
|------|---------|
| `-32700` | Ошибка разбора (невалидный JSON) |
| `-32600` | Invalid Request (отсутствует method) |
| `-32601` | Method not found |
| `-32602` | Invalid params |
| `-32603` | Internal error |
| `-32002` | Resource not found |
| `-32003` | Origin validation failed (CORS) |

---

## Реестр MCP tools

### Профиль и аутентификация

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_current_user` | Текущий пользователь + permissions | auth | нет |
| `crm_get_profile` | Профиль текущего пользователя | auth | нет |
| `crm_update_profile` | Обновить профиль | auth | изменение данных |
| `crm_get_profile_preferences` | Настройки профиля | auth | нет |
| `crm_update_profile_preferences` | Обновить настройки | auth | изменение данных |
| `crm_change_profile_password` | Сменить пароль + revoke сессий | auth | отзыв сессий |
| `crm_list_security_sessions` | Список сессий | auth | нет |
| `crm_revoke_security_session` | Отозвать сессию | auth | отзыв |
| `crm_revoke_other_security_sessions` | Отозвать все др. сессии | auth | отзыв |
| `crm_revoke_device_sessions` | Отозвать сессии по fingerprint | auth | отзыв |
| `crm_get_menu` | Навигация после фильтрации | auth | нет |
| `crm_get_menu_preferences` | Настройки меню | auth | нет |
| `crm_save_menu_preferences` | Сохранить настройки меню | auth | изменение данных |
| `crm_get_2fa_status` | Статус 2FA | auth | нет |
| `crm_enable_2fa` | Включить 2FA | auth | изменение данных |
| `crm_disable_2fa` | Выключить 2FA | auth | изменение данных |
| `crm_request_password_reset` | Запросить сброс пароля | auth | email |
| `crm_confirm_password_reset` | Подтвердить сброс пароля | auth | изменение пароля |
| `crm_accept_invitation` | Принять приглашение | auth | создание пользователя |
| `crm_start_impersonation` | Начать impersonation | user.manage | изменение сессии |
| `crm_get_impersonation_status` | Статус impersonation | auth | нет |
| `crm_stop_impersonation` | Остановить impersonation | auth | изменение сессии |

### Поиск и навигация

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_search` | Глобальный поиск | knowledge.view/project.manage/task.manage | нет |
| `crm_list_api_endpoints` | Инвентарь REST endpoints | auth | нет |

### Задачи

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_tasks` | Список задач | task.manage | нет |
| `crm_get_task` | Получить задачу | task.manage | нет |
| `crm_create_task` | Создать задачу | task.manage | создание |
| `crm_update_task` | Обновить задачу | task.manage | изменение |
| `crm_delete_task` | Удалить задачу (soft) | task.manage | удаление |
| `crm_move_task` | Переместить задачу | task.manage | изменение |
| `crm_get_task_board` | Kanban доска | task.manage | нет |
| `crm_get_task_by_key` | Задача по ключу (TASK-123) | task.manage | нет |
| `crm_bulk_update_tasks` | Массовое обновление | task.manage | изменение |
| `crm_list_task_activity` | История изменений задачи | task.manage | нет |
| `crm_list_task_comments` | Комментарии к задаче | task.manage | нет |
| `crm_add_task_comment` | Добавить комментарий | task.manage | создание |
| `crm_update_comment` | Обновить комментарий | task.manage | изменение |
| `crm_delete_comment` | Удалить комментарий | task.manage | удаление |
| `crm_get_comment_draft` | Черновик комментария задачи | task.manage | нет |
| `crm_save_comment_draft` | Сохранить черновик комментария | task.manage | изменение данных |
| `crm_delete_comment_draft` | Удалить черновик комментария | task.manage | удаление |
| `crm_list_subtasks` | Подзадачи | task.manage | нет |
| `crm_create_subtask` | Создать подзадачу | task.manage | создание |
| `crm_update_subtask` | Обновить подзадачу | task.manage | изменение |
| `crm_delete_subtask` | Удалить подзадачу | task.manage | удаление |
| `crm_list_task_relations` | Связи между задачами | task.manage | нет |
| `crm_create_task_relation` | Создать связь | task.manage | создание |
| `crm_delete_task_relation` | Удалить связь | task.manage | удаление |
| `crm_list_task_checklists` | Чеклисты задачи | task.manage | нет |
| `crm_create_task_checklist` | Создать чеклист | task.manage | создание |
| `crm_update_checklist` | Обновить чеклист | task.manage | изменение |
| `crm_list_checklist_items` | Элементы чеклиста | task.manage | нет |
| `crm_create_checklist_item` | Создать элемент | task.manage | создание |
| `crm_update_checklist_item` | Обновить элемент | task.manage | изменение |
| `crm_delete_checklist` | Удалить чеклист | task.manage | удаление |
| `crm_delete_checklist_item` | Удалить элемент чеклиста | task.manage | удаление |
| `crm_list_task_tags` | Теги задачи | task.manage | нет |
| `crm_attach_task_tag` | Прикрепить тег | task.manage | создание |
| `crm_detach_task_tag` | Открепить тег | task.manage | удаление |
| `crm_list_dependencies` | Зависимости задач | task.manage | нет |
| `crm_create_dependency` | Создать зависимость | task.manage | создание |
| `crm_delete_dependency` | Удалить зависимость | task.manage | удаление |

### Проекты

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_projects` | Список проектов | project.manage | нет |
| `crm_get_project` | Получить проект | project.manage | нет |
| `crm_get_project_summary` | Сводка проекта | project.manage | нет |
| `crm_get_project_timeline` | Таймлайн проекта | project.manage | нет |
| `crm_get_project_milestones_summary` | Вехи проекта | project.manage | нет |
| `crm_get_project_risks` | Риски проекта | project.manage | нет |
| `crm_get_project_workload` | Нагрузка проекта | project.manage | нет |
| `crm_create_project` | Создать проект | project.manage | создание |
| `crm_update_project` | Обновить проект | project.manage | изменение |
| `crm_delete_project` | Удалить проект (soft) | project.manage | удаление |
| `crm_list_project_modules` | Модули проекта | project.manage | нет |
| `crm_get_project_module` | Получить модуль | project.manage | нет |
| `crm_create_project_module` | Создать модуль | project.manage | создание |
| `crm_update_project_module` | Обновить модуль | project.manage | изменение |
| `crm_archive_project_module` | Архивировать модуль | project.manage | изменение |
| `crm_delete_project_module` | Удалить модуль | project.manage | удаление |
| `crm_list_project_module_tasks` | Задачи модуля | project.manage | нет |
| `crm_list_project_module_members` | Участники модуля | project.manage | нет |
| `crm_list_project_module_links` | Ссылки модуля | project.manage | нет |
| `crm_add_tasks_to_project_module` | Добавить задачи в модуль | project.manage | создание |
| `crm_add_members_to_project_module` | Добавить участников | project.manage | создание |
| `crm_remove_project_module_task` | Убрать задачу из модуля | project.manage | удаление |
| `crm_remove_project_module_member` | Убрать участника из модуля | project.manage | удаление |
| `crm_add_project_module_link` | Добавить ссылку модуля | project.manage | создание |
| `crm_update_project_module_link` | Обновить ссылку модуля | project.manage | изменение |
| `crm_delete_project_module_link` | Удалить ссылку модуля | project.manage | удаление |

### Рабочие циклы / Спринты

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_cycles` | Список спринтов | task.manage | нет |
| `crm_get_cycle` | Получить спринт | task.manage | нет |
| `crm_get_cycle_summary` | Сводка спринта | task.manage | нет |
| `crm_create_cycle` | Создать спринт | task.manage | создание |
| `crm_update_cycle` | Обновить спринт | task.manage | изменение |
| `crm_delete_cycle` | Удалить спринт | task.manage | удаление |
| `crm_start_cycle` | Начать спринт | task.manage | изменение статуса |
| `crm_complete_cycle` | Завершить спринт | task.manage | изменение статуса |
| `crm_reopen_cycle` | Переоткрыть спринт | task.manage | изменение статуса |
| `crm_archive_cycle` | Архивировать спринт | task.manage | изменение статуса |
| `crm_list_cycle_tasks` | Задачи спринта | task.manage | нет |
| `crm_add_tasks_to_cycle` | Добавить задачи в спринт | task.manage | создание |
| `crm_remove_cycle_task` | Убрать задачу из спринта | task.manage | удаление |
| `crm_transfer_unfinished_cycle_tasks` | Перенести незавершённые задачи | task.manage | изменение |

### Организации, компании, клиенты, контакты, контрагенты

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_organizations` | Список организаций | organization.manage | нет |
| `crm_get_organization` | Получить организацию | organization.manage | нет |
| `crm_create_organization` | Создать организацию | organization.manage | создание |
| `crm_update_organization` | Обновить организацию | organization.manage | изменение |
| `crm_delete_organization` | Удалить организацию | organization.manage | удаление |
| `crm_list_organization_members` | Участники организации | organization.manage | нет |
| `crm_add_organization_member` | Добавить участника | organization.manage | создание |
| `crm_remove_organization_member` | Убрать участника | organization.manage | удаление |
| `crm_list_companies` | Список компаний | company.manage | нет |
| `crm_get_company` | Получить компанию | company.manage | нет |
| `crm_create_company` | Создать компанию | company.manage | создание |
| `crm_update_company` | Обновить компанию | company.manage | изменение |
| `crm_delete_company` | Удалить компанию | company.manage | удаление |
| `crm_list_clients` | Список клиентов | client.manage | нет |
| `crm_get_client` | Получить клиента | client.manage | нет |
| `crm_create_client` | Создать клиента | client.manage | создание |
| `crm_update_client` | Обновить клиента | client.manage | изменение |
| `crm_delete_client` | Удалить клиента | client.manage | удаление |
| `crm_list_contacts` | Список контактов | contact.manage | нет |
| `crm_get_contact` | Получить контакт | contact.manage | нет |
| `crm_create_contact` | Создать контакт | contact.manage | создание |
| `crm_update_contact` | Обновить контакт | contact.manage | изменение |
| `crm_delete_contact` | Удалить контакт | contact.manage | удаление |
| `crm_list_counterparties` | Список контрагентов | counterparty.manage | нет |
| `crm_get_counterparty` | Получить контрагента | counterparty.manage | нет |
| `crm_create_counterparty` | Создать контрагента | counterparty.manage | создание |
| `crm_update_counterparty` | Обновить контрагента | counterparty.manage | изменение |
| `crm_delete_counterparty` | Удалить контрагента | counterparty.manage | удаление |

### Пользователи, команды, департаменты

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_users` | Список пользователей | user.view | нет |
| `crm_get_user` | Получить пользователя | user.view | нет |
| `crm_create_user` | Создать пользователя | user.manage | создание |
| `crm_update_user` | Обновить пользователя | user.manage | изменение |
| `crm_delete_user` | Удалить пользователя | user.manage | удаление |
| `crm_get_user_token_info` | Инфо о токене | user.manage | нет |
| `crm_rotate_user_token` | Ротация токена | user.manage | изменение |
| `crm_revoke_user_token` | Отозвать токен | user.manage | изменение |
| `crm_get_user_activity` | Активность пользователя | user.manage | нет |
| `crm_list_teams` | Список команд | auth | нет |
| `crm_get_team` | Получить команду | auth | нет |
| `crm_create_team` | Создать команду | team.manage | создание |
| `crm_update_team` | Обновить команду | team.manage | изменение |
| `crm_delete_team` | Удалить команду | team.manage | удаление |
| `crm_list_departments` | Список департаментов | department.manage | нет |
| `crm_get_department` | Получить департамент | department.manage | нет |
| `crm_create_department` | Создать департамент | department.manage | создание |
| `crm_update_department` | Обновить департамент | department.manage | изменение |
| `crm_delete_department` | Удалить департамент | department.manage | удаление |

### Роли и разрешения

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_roles` | Список ролей | role.manage/role.view | нет |
| `crm_list_permissions` | Реестр разрешений | role.manage/role.view | нет |
| `crm_get_role_permissions` | Разрешения роли | role.manage/role.view | нет |
| `crm_create_role` | Создать роль | role.manage | создание |
| `crm_update_role` | Обновить роль | role.manage | изменение |
| `crm_delete_role` | Удалить роль | role.manage | удаление |
| `crm_set_role_permissions` | Установить разрешения роли | role.manage | изменение |
| `crm_get_admin_role_matrix` | Матрица ролей | settings.manage | нет |
| `crm_update_admin_role_matrix` | Обновить матрицу | settings.manage | изменение |

### Согласования

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_approvals` | Список согласований | approval.manage | нет |
| `crm_get_approval` | Получить согласование | approval.manage | нет |
| `crm_create_approval` | Создать согласование | approval.manage | создание |
| `crm_approve_request` | Одобрить | approval.manage | изменение статуса |
| `crm_reject_request` | Отклонить | approval.manage | изменение статуса |

### Календарь, вехи, напоминания

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_calendar_events` | События календаря | task.manage | нет |
| `crm_get_calendar_agenda` | Дневн./нед. план | task.manage | нет |
| `crm_create_calendar_event` | Создать событие | task.manage | создание |
| `crm_get_calendar_event` | Получить событие | task.manage | нет |
| `crm_update_calendar_event` | Обновить событие | task.manage | изменение |
| `crm_delete_calendar_event` | Удалить событие | task.manage | удаление |
| `crm_get_calendar_my_month` | Месячный вид | task.manage | нет |
| `crm_list_milestones` | Список вех | task.manage | нет |
| `crm_get_milestone` | Получить веху | task.manage | нет |
| `crm_create_milestone` | Создать веху | task.manage | создание |
| `crm_update_milestone` | Обновить веху | task.manage | изменение |
| `crm_delete_milestone` | Удалить веху | project.manage | удаление |
| `crm_list_reminders` | Список напоминаний | task.manage | нет |
| `crm_get_reminder` | Получить напоминание | task.manage | нет |
| `crm_create_reminder` | Создать напоминание | task.manage | создание |
| `crm_update_reminder` | Обновить напоминание | task.manage | изменение |
| `crm_delete_reminder` | Удалить напоминание | task.manage | удаление |

### Трудозатраты

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_worklogs` | Список трудозатрат | task.manage | нет |
| `crm_get_worklog` | Получить запись | task.manage | нет |
| `crm_create_worklog` | Создать запись | task.manage | создание |
| `crm_update_worklog` | Обновить запись | task.manage | изменение |
| `crm_delete_worklog` | Удалить запись | task.manage | удаление |
| `crm_get_worklog_summary` | Сводка по дням | task.manage | нет |
| `crm_get_worklog_earnings` | Доходы/расходы | task.manage | нет |
| `crm_get_worklog_matrix` | Матрица (пользователи x дни) | task.manage | нет |
| `crm_get_worklog_detail` | Детали | task.manage | нет |
| `crm_get_worklog_task_summary` | Сводка по задаче | task.manage | нет |

> `crm_get_worklog_earnings` возвращает построчный расчёт по снапшотам ставок с разрезом клиент/проект/вид работ и фильтрами `client_public_id`, `activity_code`, `only_ambiguous`. Финансовые поля (ставки и суммы) отдаются через единую политику `FinancialFieldPolicy` и скрываются вместе — по классам раскрытия (см. раздел «Финансы» в API-справочнике).

### Оценки

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_estimate_sets` | Список наборов оценок | task.manage | нет |
| `crm_get_estimate_set` | Получить набор | task.manage | нет |
| `crm_create_estimate_set` | Создать набор | task.manage | создание |
| `crm_update_estimate_set` | Обновить набор | task.manage | изменение |
| `crm_archive_estimate_set` | Архивировать набор | task.manage | изменение |
| `crm_delete_estimate_set` | Удалить набор | task.manage | удаление |
| `crm_list_estimate_options` | Варианты оценок | task.manage | нет |
| `crm_create_estimate_option` | Создать вариант | task.manage | создание |
| `crm_update_estimate_option` | Обновить вариант | task.manage | изменение |
| `crm_archive_estimate_option` | Архивировать вариант | task.manage | изменение |
| `crm_delete_estimate_option` | Удалить вариант | task.manage | удаление |
| `crm_list_task_estimates` | Оценки задачи | task.manage | нет |
| `crm_assign_task_estimate` | Назначить оценку | task.manage | создание |
| `crm_remove_task_estimate` | Убрать оценку | task.manage | удаление |
| `crm_get_project_estimate_summary` | Сводка по проекту | task.manage | нет |
| `crm_get_cycle_estimate_summary` | Сводка по спринту | task.manage | нет |
| `crm_get_module_estimate_summary` | Сводка по модулю | task.manage | нет |

### Кастомные поля

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_custom_fields` | Список кастомных полей | task.manage | нет |
| `crm_get_custom_field` | Получить поле | task.manage | нет |
| `crm_create_custom_field` | Создать поле | task.manage | создание |
| `crm_update_custom_field` | Обновить поле | task.manage | изменение |
| `crm_get_custom_field_values` | Значения для сущности | task.manage | нет |
| `crm_set_custom_field_values` | Установить значения | task.manage | изменение |

### Шаблоны

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_templates` | Список шаблонов | task.manage | нет |
| `crm_get_template` | Получить шаблон | task.manage | нет |
| `crm_create_template` | Создать шаблон | task.manage | создание |
| `crm_update_template` | Обновить шаблон | task.manage | изменение |
| `crm_apply_template` | Применить шаблон | task.manage | создание сущности |
| `crm_delete_template` | Удалить шаблон | task.manage | удаление |

### Файлы

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_files` | Список файлов | task.manage | нет |
| `crm_get_file` | Метаданные файла | task.manage | нет |
| `crm_upload_file_base64` | Загрузить файл (base64) | task.manage | создание |
| `crm_get_file_download_info` | URL для скачивания | task.manage | нет |
| `crm_delete_file` | Удалить файл | task.manage | удаление |

### Статусы и теги

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_statuses` | Список статусов | task.manage | нет |
| `crm_get_status` | Получить статус | task.manage | нет |
| `crm_create_status` | Создать статус | task.manage | создание |
| `crm_update_status` | Обновить статус | task.manage | изменение |
| `crm_delete_status` | Удалить статус | task.manage | удаление |
| `crm_list_tags` | Список тегов | task.manage | нет |
| `crm_get_tag` | Получить тег | task.manage | нет |
| `crm_create_tag` | Создать тег | task.manage | создание |
| `crm_update_tag` | Обновить тег | task.manage | изменение |
| `crm_delete_tag` | Удалить тег | task.manage | удаление |
| `crm_list_priorities` | Список приоритетов | task.manage | нет |
| `crm_create_priority` | Создать приоритет | task.manage | создание |
| `crm_update_priority` | Обновить приоритет | task.manage | изменение |
| `crm_delete_priority` | Удалить приоритет | task.manage | удаление |

### SLA

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_sla_policies` | Список SLA | task.manage | нет |
| `crm_get_sla_policy` | Получить SLA | task.manage | нет |
| `crm_create_sla_policy` | Создать SLA | task.manage | создание |
| `crm_update_sla_policy` | Обновить SLA | task.manage | изменение |
| `crm_delete_sla_policy` | Удалить SLA | settings.manage | удаление |
| `crm_get_sla_report` | Отчёт SLA | task.manage | нет |
| `crm_assign_sla_to_task` | Назначить SLA задаче | task.manage | изменение |

### Повторяющиеся правила

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_recurring_rules` | Список правил | task.manage | нет |
| `crm_get_recurring_rule` | Получить правило | task.manage | нет |
| `crm_create_recurring_rule` | Создать правило | task.manage | создание |
| `crm_update_recurring_rule` | Обновить правило | task.manage | изменение |
| `crm_pause_recurring_rule` | Пауза правила | task.manage | изменение |
| `crm_resume_recurring_rule` | Возобновить правило | task.manage | изменение |
| `crm_delete_recurring_rule` | Удалить правило | task.manage | удаление |

### Сохранённые представления и стикеры

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_saved_views` | Список представлений | task.manage | нет |
| `crm_get_saved_view` | Получить представление | task.manage | нет |
| `crm_create_saved_view` | Создать представление | task.manage | создание |
| `crm_update_saved_view` | Обновить представление | task.manage | изменение |
| `crm_archive_saved_view` | Архивировать | task.manage | изменение |
| `crm_duplicate_saved_view` | Дублировать | task.manage | создание |
| `crm_pin_saved_view` | Закрепить | task.manage | изменение |
| `crm_touch_saved_view` | Пометить как используемое | task.manage | изменение |
| `crm_get_saved_view_task_filters` | Фильтры представления | task.manage | нет |
| `crm_delete_saved_view` | Удалить | task.manage | удаление |
| `crm_list_sticky_notes` | Список стикеров | task.manage | нет |
| `crm_get_sticky_note` | Получить стикер | task.manage | нет |
| `crm_create_sticky_note` | Создать стикер | task.manage | создание |
| `crm_update_sticky_note` | Обновить стикер | task.manage | изменение |
| `crm_archive_sticky_note` | Архивировать стикер | task.manage | изменение |
| `crm_unarchive_sticky_note` | Восстановить стикер | task.manage | изменение |
| `crm_delete_sticky_note` | Удалить стикер | task.manage | удаление |
| `crm_convert_sticky_to_task` | Стикер → задача | task.manage | создание |
| `crm_convert_sticky_to_page` | Стикер → страница знаний | task.manage | создание |
| `crm_reorder_sticky_notes` | Переупорядочить стикеры | task.manage | изменение |

### База знаний

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_knowledge_overview` | Обзор базы знаний | knowledge.view | нет |
| `crm_list_knowledge_pages` | Список страниц | knowledge.view | нет |
| `crm_get_knowledge_page` | Получить страницу | knowledge.view | нет |
| `crm_list_knowledge_spaces` | Список пространств | knowledge.view | нет |
| `crm_list_knowledge_spaces_tree` | Дерево пространств | knowledge.view | нет |
| `crm_get_knowledge_space` | Получить пространство | knowledge.view | нет |
| `crm_get_knowledge_tree` | Дерево страниц | knowledge.view | нет |
| `crm_search_knowledge` | Поиск по базе знаний | knowledge.view | нет |
| `crm_list_knowledge_recent` | Недавние страницы | knowledge.view | нет |
| `crm_list_knowledge_popular` | Популярные страницы | knowledge.view | нет |
| `crm_list_knowledge_review_queue` | Очередь ревью | knowledge.view | нет |
| `crm_list_knowledge_outdated` | Устаревшие страницы | knowledge.view | нет |
| `crm_list_knowledge_favorites` | Избранное | knowledge.view | нет |
| `crm_get_knowledge_entity_pages` | Страницы сущности | knowledge.view | нет |
| `crm_get_knowledge_suggest` | Предложения | knowledge.view | нет |
| `crm_get_knowledge_analytics` | Аналитика | knowledge.view | нет |
| `crm_list_knowledge_page_versions` | Версии страницы | knowledge.view | нет |
| `crm_get_knowledge_page_version` | Получить версию | knowledge.view | нет |
| `crm_diff_knowledge_page_version` | Дифф версии | knowledge.view | нет |
| `crm_create_knowledge_space` | Создать пространство | knowledge.manage | создание |
| `crm_update_knowledge_space` | Обновить пространство | knowledge.manage | изменение |
| `crm_archive_knowledge_space` | Архивировать пространство | knowledge.manage | изменение |
| `crm_restore_knowledge_space` | Восстановить пространство | knowledge.manage | изменение |
| `crm_create_knowledge_page` | Создать страницу | knowledge.create | создание |
| `crm_update_knowledge_page` | Обновить страницу | knowledge.edit | изменение |
| `crm_publish_knowledge_page` | Опубликовать | knowledge.publish | изменение статуса |
| `crm_archive_knowledge_page` | Архивировать | knowledge.publish | изменение статуса |
| `crm_restore_knowledge_page` | Восстановить | knowledge.publish | изменение статуса |
| `crm_request_knowledge_review` | Запросить ревью | knowledge.review | изменение |
| `crm_approve_knowledge_review` | Одобрить ревью | knowledge.review | изменение статуса |
| `crm_reject_knowledge_review` | Отклонить ревью | knowledge.review | изменение статуса |
| `crm_duplicate_knowledge_page` | Дублировать страницу | knowledge.create | создание |
| `crm_move_knowledge_page` | Переместить страницу | knowledge.manage | изменение |
| `crm_lock_knowledge_page` | Заблокировать | knowledge.manage | изменение |
| `crm_unlock_knowledge_page` | Разблокировать | knowledge.manage | изменение |
| `crm_lock_knowledge_page_version` | Заблокировать версию | knowledge.manage | изменение |
| `crm_unlock_knowledge_page_version` | Разблокировать версию | knowledge.manage | изменение |
| `crm_delete_knowledge_page` | Удалить страницу | knowledge.manage | удаление |
| `crm_restore_knowledge_page_version` | Восстановить версию | knowledge.publish | изменение |
| `crm_list_knowledge_templates` | Список шаблонов | knowledge.view | нет |
| `crm_create_knowledge_template` | Создать шаблон | knowledge.create | создание |
| `crm_export_knowledge_all` | Экспорт всей базы | knowledge.view | нет |
| `crm_export_knowledge_page` | Экспорт страницы | knowledge.view | нет |
| `crm_export_knowledge_space` | Экспорт пространства | knowledge.view | нет |
| `crm_import_knowledge_pages` | Импорт страниц | knowledge.create | создание |
| `crm_list_knowledge_comments` | Комментарии страницы | knowledge.view | нет |
| `crm_add_knowledge_comment` | Добавить комментарий | knowledge.comment | создание |
| `crm_delete_knowledge_comment` | Удалить комментарий | knowledge.comment | удаление |
| `crm_resolve_knowledge_comment` | Разрешить ветку | knowledge.comment | изменение |
| `crm_reopen_knowledge_comment` | Переоткрыть ветку | knowledge.comment | изменение |
| `crm_list_knowledge_page_links` | Ссылки страницы | knowledge.view | нет |
| `crm_delete_knowledge_page_link` | Удалить ссылку | knowledge.edit | удаление |
| `crm_list_knowledge_page_tags` | Теги страницы | knowledge.view | нет |
| `crm_attach_knowledge_page_tag` | Прикрепить тег | knowledge.edit | создание |
| `crm_detach_knowledge_page_tag` | Открепить тег | knowledge.edit | удаление |
| `crm_link_knowledge_page_entity` | Связать с сущностью | knowledge.edit | создание |
| `crm_list_knowledge_files` | Файлы страницы | knowledge.view | нет |
| `crm_upload_knowledge_file_base64` | Загрузить файл | knowledge.edit | создание |
| `crm_delete_knowledge_file` | Удалить файл | knowledge.delete | удаление |
| `crm_get_knowledge_page_draft` | Черновик | knowledge.edit | нет |
| `crm_save_knowledge_page_draft` | Сохранить черновик | knowledge.edit | создание/изменение |
| `crm_delete_knowledge_draft` | Удалить черновик | knowledge.edit | удаление |
| `crm_favorite_knowledge_page` | В избранное | knowledge.view | создание |
| `crm_unfavorite_knowledge_page` | Убрать из избранного | knowledge.view | удаление |
| `crm_subscribe_knowledge_page` | Подписаться | knowledge.view | создание |
| `crm_unsubscribe_knowledge_page` | Отписаться | knowledge.view | удаление |
| `crm_get_knowledge_space_permissions` | Разрешения пространства | knowledge.manage | нет |
| `crm_add_knowledge_space_permission` | Добавить разрешение | knowledge.manage | создание |
| `crm_remove_knowledge_space_permission` | Убрать разрешение | knowledge.manage | удаление |
| `crm_get_knowledge_page_permissions` | Разрешения страницы | knowledge.manage | нет |
| `crm_add_knowledge_page_permission` | Добавить разрешение | knowledge.manage | создание |
| `crm_remove_knowledge_page_permission` | Убрать разрешение | knowledge.manage | удаление |
| `crm_get_admin_knowledge_settings` | Настройки знаний | settings.manage | нет |
| `crm_update_admin_knowledge_settings` | Обновить настройки | settings.manage | изменение |
| `crm_reindex_knowledge` | Реиндексация | settings.manage | операция |
| `crm_rebuild_knowledge_permissions` | Пересборка прав | settings.manage | операция |
| `crm_cleanup_knowledge_drafts` | Очистка черновиков | settings.manage | удаление |

### AI базы знаний

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_create_knowledge_ai_summary` | AI-сводка страницы | knowledge.view | вызов AI |
| `crm_create_knowledge_ai_explanation` | AI-объяснение страницы | knowledge.view | вызов AI |
| `crm_find_knowledge_ai_similar` | Похожие страницы | knowledge.view | вызов AI |
| `crm_create_knowledge_ai_checklist` | AI-чеклист | knowledge.view | вызов AI |
| `crm_create_knowledge_ai_faq_from_comments` | AI-FAQ | knowledge.view | вызов AI |
| `crm_create_knowledge_ai_suggest_for_task` | Предложения для задачи | knowledge.view | вызов AI |
| `crm_find_knowledge_ai_duplicates` | Дубликаты | knowledge.manage | вызов AI |
| `crm_find_knowledge_ai_orphans` | Сироты | knowledge.manage | вызов AI |
| `crm_suggest_knowledge_ai_structure` | Предложение структуры | knowledge.manage | вызов AI |

### Идеи

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_ideas` | Список идей | idea.manage | нет |
| `crm_get_idea` | Получить идею | idea.manage | нет |
| `crm_create_idea` | Создать идею | idea.manage | создание |
| `crm_update_idea` | Обновить идею | idea.manage | изменение |
| `crm_delete_idea` | Удалить идею | idea.manage | удаление |
| `crm_vote_idea` | Голосовать | idea.manage | создание/удаление |
| `crm_update_idea_status` | Обновить статус | idea.manage | изменение |
| `crm_list_idea_comments` | Комментарии | idea.manage | нет |
| `crm_add_idea_comment` | Добавить комментарий | idea.manage | создание |

### Чаты

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_chats` | Список чатов | project.manage/task.manage | нет |
| `crm_get_chat` | Получить чат | auth | нет |
| `crm_create_chat` | Создать чат | auth | создание |
| `crm_get_chat_participants` | Участники | auth | нет |
| `crm_list_chat_messages` | Сообщения | project.manage/task.manage | нет |
| `crm_send_chat_message` | Отправить сообщение | project.manage/task.manage | создание |
| `crm_edit_chat_message` | Редактировать сообщение | auth | изменение |
| `crm_delete_chat_message` | Удалить сообщение | auth | удаление |
| `crm_upload_chat_attachment` | Загрузить вложение | auth | создание |
| `crm_download_chat_attachment` | Скачать вложение | auth | нет |
| `crm_list_chat_attachments` | Список вложений | auth | нет |
| `crm_get_chat_settings` | Настройки чата | auth | нет |
| `crm_update_chat_settings` | Обновить настройки | auth | изменение |
| `crm_mark_chat_read` | Пометить прочитанным | auth | изменение |
| `crm_get_chat_unread_count` | Непрочитанные | auth | нет |
| `crm_archive_chat` | Архивировать чат | auth | изменение |
| `crm_restore_chat` | Восстановить чат | auth | изменение |

### Уведомления

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_notifications` | Список уведомлений | auth | нет |
| `crm_get_notification_counters` | Счётчики | auth | нет |
| `crm_create_notification` | Создать уведомление | settings.manage | создание |
| `crm_mark_notification_read` | Пометить прочитанным | auth | изменение |
| `crm_mark_notification_unread` | Пометить непрочитанным | auth | изменение |
| `crm_mark_all_notifications_read` | Пометить все | auth | изменение |

### Push-подписки

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_push_subscriptions` | Список подписок | auth | нет |
| `crm_create_push_subscription` | Создать подписку | auth | создание |
| `crm_delete_push_subscription` | Удалить подписку | auth | удаление |
| `crm_send_push_test` | Тест push | auth | отправка |

### Избранное, подписки, реакции, упоминания

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_favorites` | Избранное | auth | нет |
| `crm_create_favorite` | Добавить в избранное | auth | создание |
| `crm_delete_favorite` | Убрать из избранного | auth | удаление |
| `crm_list_subscriptions` | Подписки | auth | нет |
| `crm_create_subscription` | Подписаться | auth | создание |
| `crm_delete_subscription` | Отписаться | auth | удаление |
| `crm_list_reactions` | Реакции | auth | нет |
| `crm_add_reaction` | Добавить реакцию | auth | создание |
| `crm_remove_reaction` | Убрать реакцию | auth | удаление |
| `crm_list_mentions` | Упоминания | auth | нет |
| `crm_add_mention` | Упомянуть | project.manage/task.manage | создание |
| `crm_delete_mention` | Удалить упоминание | project.manage/task.manage | удаление |

### Лента активности

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_activity_feed` | Лента активности | logs.view/project.manage/task.manage | нет |
| `crm_get_activity_history` | История активности | logs.view/project.manage/task.manage | нет |

### Кабинет клиента

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_client_cabinet_projects` | Проекты кабинета клиента | client.manage | нет |
| `crm_get_client_cabinet_project` | Проект кабинета | client.manage | нет |
| `crm_list_client_cabinet_project_tasks` | Задачи проекта | client.manage | нет |

### Intake

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_intake_items` | Список intake | intake.manage/intake.view | нет |
| `crm_get_intake_item` | Получить intake | intake.manage/intake.view | нет |
| `crm_create_intake_item` | Создать intake | intake.create/intake.manage | создание |
| `crm_update_intake_item` | Обновить intake | intake.manage | изменение |
| `crm_delete_intake_item` | Удалить intake | intake.delete/intake.manage | удаление |
| `crm_accept_intake_item` | Принять + создать задачу | intake.accept/intake.manage | создание |
| `crm_reject_intake_item` | Отклонить | intake.manage | изменение |
| `crm_snooze_intake_item` | Отложить | intake.manage | изменение |
| `crm_duplicate_intake_item` | Пометить как дубликат | intake.manage | изменение |
| `crm_reopen_intake_item` | Переоткрыть | intake.manage | изменение |

### Вебхуки

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_webhooks` | Список вебхуков | webhook.manage | нет |
| `crm_list_webhook_deliveries` | Доставки | webhook.manage | нет |
| `crm_create_webhook` | Создать вебхук | webhook.manage | создание |
| `crm_update_webhook` | Обновить вебхук | webhook.manage | изменение |
| `crm_delete_webhook` | Удалить вебхук | webhook.manage | удаление |
| `crm_test_webhook` | Тестовый вызов | webhook.manage | отправка |

### Правила workflow

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_workflow_rules` | Список правил | settings.manage | нет |
| `crm_get_workflow_rule` | Получить правило | settings.manage | нет |
| `crm_create_workflow_rule` | Создать правило | settings.manage | создание |
| `crm_update_workflow_rule` | Обновить правило | settings.manage | изменение |
| `crm_delete_workflow_rule` | Удалить правило | settings.manage | удаление |
| `crm_list_workflow_runs` | Логи выполнения | settings.manage | нет |
| `crm_run_workflow_rule_test` | Тест правила | settings.manage | выполнение |

### AI

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_ai_settings` | Глобальные настройки AI | settings.manage | нет |
| `crm_update_ai_settings` | Обновить настройки AI | settings.manage | изменение |
| `crm_get_ai_preferences` | Настройки AI пользователя | ai.use | нет |
| `crm_update_ai_preferences` | Обновить настройки | ai.use | изменение |
| `crm_get_ai_availability` | Доступность AI | ai.use | нет |
| `crm_list_ai_action_types` | Типы действий | ai.use | нет |
| `crm_execute_ai_action` | Выполнить AI действие | ai.use | вызов AI |
| `crm_list_ai_providers` | Список провайдеров | settings.manage | нет |
| `crm_get_ai_provider` | Получить провайдер | settings.manage | нет |
| `crm_list_ai_models` | Список моделей | settings.manage | нет |
| `crm_list_ai_intents` | Список intents | settings.manage | нет |
| `crm_update_ai_intent` | Обновить intent | settings.manage | изменение |
| `crm_list_ai_prompts` | Список промптов | settings.manage | нет |
| `crm_create_ai_prompt` | Создать промпт | settings.manage | создание |
| `crm_update_ai_prompt` | Обновить промпт | settings.manage | изменение |
| `crm_list_ai_json_schemas` | Список JSON схем | settings.manage | нет |
| `crm_create_ai_json_schema` | Создать JSON схему | settings.manage | создание |
| `crm_update_ai_json_schema` | Обновить JSON схему | settings.manage | изменение |
| `crm_list_ai_usage` | Логи использования AI | settings.manage | нет |
| `crm_list_ai_audit` | AI аудит | settings.manage | нет |
| `crm_list_ai_jobs` | AI задачи | settings.manage | нет |
| `crm_get_ai_job` | Получить AI задачу | settings.manage | нет |
| `crm_retry_ai_job` | Повторить AI задачу | settings.manage | операция |
| `crm_dry_run_ai_job` | Dry-run AI задачи | settings.manage | нет |
| `crm_run_once_ai_job` | Запустить AI задачу | settings.manage | выполнение |
| `crm_search_ai_semantic` | Семантический поиск | settings.manage | вызов AI |
| `crm_list_ai_retention_policies` | Политики хранения | settings.manage | нет |
| `crm_list_ai_suggestions` | AI предложения | ai.use | нет |
| `crm_get_ai_suggestion` | Получить предложение | ai.use | нет |
| `crm_dismiss_ai_suggestion` | Отклонить предложение | ai.use | изменение |
| `crm_preview_apply_ai_suggestion` | Предпросмотр | ai.use | нет |
| `crm_confirm_ai_suggestion` | Применить предложение | ai.use | изменение |
| `crm_create_ai_dashboard_digest` | AI-дайджест | ai.use | вызов AI |
| `crm_create_ai_my_day_plan` | AI-план дня | ai.use | вызов AI |
| `crm_create_ai_my_week_plan` | AI-план недели | ai.use | вызов AI |
| `crm_create_ai_task_summary` | AI-сводка задачи | task.manage | вызов AI |
| `crm_create_ai_task_next_action` | AI-следующее действие | task.manage | вызов AI |
| `crm_create_ai_task_decomposition` | AI-декомпозиция | task.manage | вызов AI |
| `crm_create_ai_task_checklist` | AI-чеклист | task.manage | вызов AI |
| `crm_create_ai_task_quality` | AI-ревью качества | task.manage | вызов AI |
| `crm_create_ai_project_summary` | AI-сводка проекта | project.manage | вызов AI |
| `crm_create_ai_project_risks` | AI-риски проекта | project.manage | вызов AI |
| `crm_create_ai_analytics_kpi_explanation` | AI-объяснение KPI | ai.use | вызов AI |
| `crm_create_ai_analytics_risks_explanation` | AI-объяснение рисков | ai.use | вызов AI |
| `crm_create_ai_analytics_team_workload_summary` | AI-нагрузка команды | ai.use | вызов AI |

### Аналитика

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_analytics_summary` | Сводка аналитики | analytics.view/task.manage | нет |
| `crm_list_analytics_projects` | Аналитика проектов | analytics.view/task.manage | нет |
| `crm_list_analytics_users` | Нагрузка пользователей | analytics.view/task.manage | нет |

### Дашборд

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_dashboard_summary` | Сводка дашборда | auth | нет |
| `crm_get_health_status` | Лёгкий статус здоровья | auth | нет |
| `crm_get_dashboard_widgets` | Каталог и активные виджеты | auth | нет |
| `crm_save_dashboard_widgets` | Сохранить раскладку виджетов | auth | изменение данных |

### Администрирование

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_settings` | Список настроек | settings.manage | нет |
| `crm_get_setting` | Получить настройку | settings.manage | нет |
| `crm_get_retention_metadata` | Метаданные retention | settings.manage | нет |
| `crm_set_retention_metadata` | Обновить метаданные retention | settings.manage | изменение |
| `crm_list_feature_flags` | Feature flags | settings.manage | нет |
| `crm_update_feature_flag` | Обновить flag | settings.manage | изменение |
| `crm_list_modules` | Список модулей | settings.manage | нет |
| `crm_get_module` | Получить модуль | settings.manage | нет |
| `crm_install_module` | Установить модуль | settings.manage | установка |
| `crm_activate_module` | Активировать модуль | settings.manage | изменение |
| `crm_deactivate_module` | Деактивировать модуль | settings.manage | изменение |
| `crm_uninstall_module` | Удалить модуль | settings.manage | удаление |
| `crm_get_module_config` | Конфиг модуля | settings.manage | нет |
| `crm_update_module_config` | Обновить конфиг | settings.manage | изменение |
| `crm_get_module_health` | Здоровье модуля | settings.manage | нет |
| `crm_get_module_migrations` | Миграции модуля | settings.manage | нет |
| `crm_get_module_errors` | Ошибки модуля | settings.manage | нет |
| `crm_clear_module_errors` | Очистить ошибки | settings.manage | удаление |
| `crm_install_module_from_url` | Установить из URL | settings.manage | установка |
| `crm_install_module_from_file` | Установить из файла | settings.manage | установка |
| `crm_get_cache_stats` | Статистика кеша | settings.manage | нет |
| `crm_clear_cache` | Очистить кеш | settings.manage | удаление |
| `crm_get_ops_system` | Системный снимок | settings.manage | нет |
| `crm_get_ops_metrics` | Метрики | settings.manage | нет |
| `crm_run_ops_jobs` | Запустить очереди | settings.manage | выполнение |
| `crm_get_core_version` | Версия ядра | settings.manage | нет |
| `crm_get_core_update_status` | Статус обновления | settings.manage | нет |
| `crm_check_core_update` | Проверить обновление | settings.manage | нет |
| `crm_run_core_update_preflight` | Preflight обновления | settings.manage | нет |
| `crm_get_core_update_changes` | Изменения обновления | settings.manage | нет |
| `crm_get_core_update_session` | Сессия обновления | settings.manage | создание |
| `crm_get_core_update_history` | История обновлений | settings.manage | нет |
| `crm_get_core_update_log` | Лог обновления | settings.manage | нет |

### Логи и аудит

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_audit_log` | Аудит-лог | logs.view/settings.manage | нет |
| `crm_list_security_log` | Безопасный лог | logs.view/settings.manage | нет |
| `crm_list_request_logs` | HTTP логи | logs.view | нет |
| `crm_get_frontend_errors_chart` | График frontend-ошибок | logs.view | нет |
| `crm_get_admin_summary_widget` | Виджет-сводка | logs.view | нет |
| `crm_get_admin_system_widget` | Виджет-система | logs.view | нет |
| `crm_get_openapi_spec` | OpenAPI спека | logs.view | нет |

### API-клиенты и ключи

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_api_clients` | Список клиентов API | api_client.manage/api_client.view | нет |
| `crm_get_api_client` | Получить клиента | api_client.manage/api_client.view | нет |
| `crm_list_api_client_keys` | Ключи клиента | api_client.manage/api_client.view | нет |
| `crm_create_api_client` | Создать клиента | api_client.manage | создание |
| `crm_update_api_client` | Обновить клиента | api_client.manage | изменение |
| `crm_delete_api_client` | Удалить клиента | api_client.manage | удаление |
| `crm_issue_api_client_key` | Выдать ключ | api_client.manage | создание |
| `crm_rotate_api_key` | Ротация ключа | api_client.manage | изменение |
| `crm_revoke_api_key` | Отозвать ключ | api_client.manage | изменение |
| `crm_get_api_key_usage` | Использование ключа | api_client.view | нет |

### Импорт / Экспорт

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_import_jobs` | Список импортов | import.manage | нет |
| `crm_get_import_job` | Получить импорт | import.manage | нет |
| `crm_create_import_job` | Создать импорт | import.manage | создание |
| `crm_cancel_import_job` | Отменить импорт | import.manage | изменение |
| `crm_retry_import_job` | Повторить импорт | import.manage | выполнение |
| `crm_list_export_jobs` | Список экспортов | export.manage | нет |
| `crm_get_export_job` | Получить экспорт | export.manage | нет |
| `crm_create_export_job` | Создать экспорт | export.manage | создание |
| `crm_cancel_export_job` | Отменить экспорт | export.manage | изменение |
| `crm_retry_export_job` | Повторить экспорт | export.manage | выполнение |
| `crm_download_export_job` | Скачать экспорт | export.manage | нет |

### Корзина

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_recycle_bin` | Список корзины | recycle_bin.manage | нет |
| `crm_restore_recycle_bin_item` | Восстановить | recycle_bin.manage | восстановление |
| `crm_purge_recycle_bin_item` | Удалить навсегда | recycle_bin.manage | удаление |

### Бизнес-календари

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_business_calendars` | Список бизнес-календарей | settings.manage | нет |
| `crm_create_business_calendar` | Создать | settings.manage | создание |
| `crm_get_business_calendar` | Получить | settings.manage | нет |
| `crm_update_business_calendar` | Обновить | settings.manage | изменение |
| `crm_delete_business_calendar` | Удалить | settings.manage | удаление |
| `crm_list_holidays` | Праздники | settings.manage | нет |
| `crm_create_holiday` | Создать праздник | settings.manage | создание |
| `crm_get_holiday` | Получить праздник | settings.manage | нет |
| `crm_update_holiday` | Обновить праздник | settings.manage | изменение |
| `crm_delete_holiday` | Удалить праздник | settings.manage | удаление |
| `crm_list_working_hours` | Рабочие часы | settings.manage | нет |
| `crm_create_working_hours` | Создать правило | settings.manage | создание |
| `crm_get_working_hours` | Получить правило | settings.manage | нет |
| `crm_update_working_hours` | Обновить правило | settings.manage | изменение |
| `crm_delete_working_hours` | Удалить правило | settings.manage | удаление |

### Приглашения

| Tool | Назначение | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_invitations` | Список приглашений | user.manage | нет |
| `crm_create_invitation` | Создать приглашение | user.manage | email |

---

## Реестр MCP resources

Ресурсы MCP — только для чтения, доступны по URI tropatt://.

| Resource URI | Назначение | MIME | Auth | Приоритет |
|-------------|-----------|------|------|-----------|
| ``tropatt://server/about`` | Описание MCP-сервера | text/markdown | auth | 1.0 |
| ``tropatt://server/tools`` | Список доступных tools | application/json | auth | 0.95 |
| ``tropatt://user/current`` | Текущий пользователь | application/json | auth | 0.9 |
| ``tropatt://server/api-map`` | Карта API-доменов | text/markdown | auth | 0.8 |
| ``tropatt://server/api-endpoints`` | Инвентарь REST endpoints | application/json | auth | 0.75 |

---

## Реестр MCP prompts

Prompts не поддерживаются в текущей версии MCP.

---

## Опасные MCP tools

> Все write-tools (create/update/delete) требуют соответствующих разрешений. Работа с пользователями, ролями, модулями, обновлением ядра и кешем — только для администратора. Смена пароля и 2FA — только для текущего пользователя. Impersonation — только для администратора.

| Tool | Что делает | Почему опасен | Audit |
|------|-------------|-------------------|-------|
| `crm_start_impersonation` | Имперсонация другого пользователя | Полный доступ от чужого имени | да |
| `crm_create_user` | Создание пользователя | Создание учётной записи | да |
| `crm_update_user` | Обновление пользователя (пароль, роли, root) | Изменение прав доступа | да |
| `crm_delete_user` | Удаление пользователя | Потеря учётной записи | да |
| `crm_rotate_user_token` | Ротация API токена | Новый токен, старый перестаёт работать | да |
| `crm_set_role_permissions` | Установка разрешений роли | Изменение RBAC | да |
| `crm_create_role` | Создание роли | Новая роль | да |
| `crm_delete_role` | Удаление роли | Потеря роли | да |
| `crm_install_module` | Установка модуля | Код на сервере | да |
| `crm_uninstall_module` | Удаление модуля | Потеря данных модуля | да |
| `crm_install_module_from_url` | Установка из URL | Внешний код | да |
| `crm_install_module_from_file` | Установка из файла | Внешний код | да |
| `crm_clear_cache` | Очистка кеша | Может нарушить работу | да |
| `crm_run_ops_jobs` | Запуск очередей | Выполнение jobs | да |
| `crm_run_core_update_preflight` | Preflight обновления | Подготовка к обновлению | да |
| `crm_purge_recycle_bin_item` | Навсегда удалить | Необратимое удаление | да |
| `crm_execute_ai_action` | Выполнение AI действия | AI может менять данные | да |
| `crm_create_import_job` | Импорт данных | Массовое изменение данных | да |
| `crm_create_webhook` | Создание вебхука | Внешние вызовы | да |
| `crm_change_profile_password` | Смена пароля | Revoke всех сессий | да |
| `crm_enable_2fa` | Включение 2FA | Блокировка без app | да |
| `crm_request_password_reset` | Запрос сброса пароля | Email с токеном | да |

---

## Соответствие MCP → REST API

MCP дублирует функционал REST API поверх безопасного слоя. Ниже — соответствие основных tools и REST endpoint'ов, а также items, существующие только в MCP или только в REST.

| MCP Tool | Тип | REST Endpoint | Controller | Отличия |
|----------|-----|---------------|------------|---------|
| `crm_search` | tool | `GET /api/v1/search` | SearchController | Компактный ответ |
| `crm_list_tasks` | tool | `GET /api/v1/tasks` | TaskController::list | Фильтры упрощены |
| `crm_get_task` | tool | `GET /api/v1/tasks/{id}` | TaskController::get | Без internal IDs |
| `crm_create_task` | tool | `POST /api/v1/tasks` | TaskController::create | Создатель = текущий пользователь |
| `crm_update_task` | tool | `PATCH /api/v1/tasks/{id}` | TaskController::update | Без sensitive полей |
| `crm_delete_task` | tool | `DELETE /api/v1/tasks/{id}` | TaskController::delete | Soft delete |
| `crm_list_projects` | tool | `GET /api/v1/projects` | ProjectController::list | - |
| `crm_get_project` | tool | `GET /api/v1/projects/{id}` | ProjectController::get | - |
| `crm_create_project` | tool | `POST /api/v1/projects` | ProjectController::create | - |
| `crm_list_users` | tool | `GET /api/v1/users` | UserController::list | Без паролей/токенов |
| `crm_get_user` | tool | `GET /api/v1/users/{id}` | UserController::get | Без паролей |
| `crm_list_chats` | tool | `GET /api/v1/chats` | ChatController::list | - |
| `crm_send_chat_message` | tool | `POST /api/v1/chats/{id}/messages` | ChatController::sendMessage | - |
| `crm_list_knowledge_pages` | tool | `GET /api/v1/knowledge/pages` | KnowledgeController::list | - |
| `crm_create_knowledge_page` | tool | `POST /api/v1/knowledge/pages` | KnowledgeController::create | - |
| `crm_list_api_endpoints` | tool | `нет прямого` | McpController::apiEndpointsIndex | MCP-only, читает routes.php |

---

## Items только в MCP (без REST-аналога)

| Tool | Назначение | Комментарий |
|------|-----------|-------------|
| `crm_list_api_endpoints` | Инвентарь REST endpoints | Возвращает полный список маршрутов из routes.php |
| `crm_get_knowledge_overview` | Обзор базы знаний | Агрегированная сводка |
| `crm_get_knowledge_tree` | Дерево страниц | Рекурсивная структура |
| `crm_get_knowledge_suggest` | Предложения страниц | AI-powered |
| `crm_get_knowledge_analytics` | Аналитика базы знаний | Агрегированные метрики |
| `crm_create_knowledge_ai_summary` | AI-сводка страницы | AI-powered |
| `crm_create_knowledge_ai_explanation` | AI-объяснение | AI-powered |
| `crm_find_knowledge_ai_similar` | Похожие страницы | AI-powered semantic |
| `crm_create_knowledge_ai_checklist` | AI-чеклист | AI-powered |
| `crm_create_knowledge_ai_faq_from_comments` | AI-FAQ из комментариев | AI-powered |
| `crm_create_knowledge_ai_suggest_for_task` | Предложения для задачи | AI-powered |
| `crm_find_knowledge_ai_duplicates` | Дубликаты | AI-powered |
| `crm_find_knowledge_ai_orphans` | Сироты | AI-powered |
| `crm_suggest_knowledge_ai_structure` | Предложение структуры | AI-powered |
| `crm_get_project_summary` | Сводка проекта | Агрегированные данные |
| `crm_get_project_timeline` | Таймлайн проекта | Gantt-like |
| `crm_get_project_risks` | Риски проекта | Агрегированные данные |
| `crm_get_project_workload` | Нагрузка проекта | Агрегированные данные |
| `crm_get_cycle_summary` | Сводка спринта | Агрегированные данные |
| `crm_get_task_board` | Kanban доска | Группировка по статусам |
| `crm_get_activity_feed` | Лента активности | Агрегированный feed |
| `crm_get_activity_history` | История активности | По сущности |
| `crm_get_dashboard_summary` | Сводка дашборда | Агрегированные счётчики |
| `crm_execute_ai_action` | Выполнение AI действия | AI-powered |
| `crm_search_ai_semantic` | Семантический поиск | AI-powered |
| `crm_create_ai_dashboard_digest` | AI-дайджест | AI-powered |
| `crm_create_ai_my_day_plan` | AI-план дня | AI-powered |
| `crm_create_ai_my_week_plan` | AI-план недели | AI-powered |
| `crm_create_ai_task_summary` | AI-сводка задачи | AI-powered |
| `crm_create_ai_task_next_action` | AI-следующее действие | AI-powered |
| `crm_create_ai_task_decomposition` | AI-декомпозиция | AI-powered |
| `crm_create_ai_task_checklist` | AI-чеклист | AI-powered |
| `crm_create_ai_task_quality` | AI-ревью качества | AI-powered |
| `crm_create_ai_project_summary` | AI-сводка проекта | AI-powered |
| `crm_create_ai_project_risks` | AI-риски проекта | AI-powered |
| `crm_create_ai_analytics_kpi_explanation` | AI-объяснение KPI | AI-powered |
| `crm_create_ai_analytics_risks_explanation` | AI-объяснение рисков | AI-powered |
| `crm_create_ai_analytics_team_workload_summary` | AI-нагрузка команды | AI-powered |
| `crm_get_saved_view_task_filters` | Фильтры представления | Упрощённый формат |
| `crm_get_file_download_info` | URL скачивания | Без локальных путей |
| `crm_download_chat_attachment` | Скачивание вложения | Без локальных путей |
| `crm_download_export_job` | Скачивание экспорта | Без локальных путей |

---

## REST endpoint'ы без MCP-аналога

| REST Endpoint | Назначение | Нужно MCP покрытие | Комментарий |
|--------------|-----------|-------------------|-------------|
| `POST /api/v1/auth/login` | Вход | Нет | MCP использует bearer token |
| `POST /api/v1/auth/logout` | Выход | Нет | Сессии управляются через REST |
| `GET /api/v1/install/status` | Статус установки | Нет | Только для установщика |
| `POST /api/v1/internal/migration/*` | Миграции | Нет | Внутренние операции |
| `POST /api/v1/system/core-update/*` | Core update | Частично | MCP: crm_run_core_update_preflight, crm_get_core_update_session |
| `GET /api/v1/export/download/{id}` | Скачивание экспорта | Да | MCP: crm_download_export_job |
| `POST /api/v1/file/download/{id}` | Скачивание файла | Да | MCP: crm_get_file_download_info |
| `POST /api/v1/file/upload` | Загрузка файла | Да | MCP: crm_upload_file_base64 |

---

* Источник данных: контроллер McpController.php и реестр прав mcp_permissions.php. Документация синхронизирована с кодом.
