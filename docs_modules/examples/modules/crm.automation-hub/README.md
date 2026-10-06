# crm.automation-hub

Двусторонняя интеграция процессов TropaTT CRM с платформами n8n и Make.

## Возможности
- Готовые сценарии и шаблоны интеграций (Intake to Task, Contact Sync).
- HMAC-SHA256 подпись каждого вебхука с заголовками `X-Signature-SHA256` и `X-Signature-Timestamp`.
- Автоматическая дедупликация повторных событий по `correlation_id`.
- Журнал аудита доставки с фиксацией HTTP-кода ответа и ошибок.
- Изоляция по организациям (Multi-tenancy).
- Интеграция с MCP через `automation_list_recipes` и `automation_test_webhook`.
