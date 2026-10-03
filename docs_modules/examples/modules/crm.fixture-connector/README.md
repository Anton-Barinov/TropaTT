# Reference Connector Module (`crm.fixture-connector`)

Эталонный интеграционный коннектор для TropaTT CRM, демонстрирующий реализацию сквозного сценария интеграции:
**Входящее событие / Webhook → Транзакционная очередь (Job) → Контекст рабочего пространства (`ModuleExecutionContext`) → REST API маршруты → Инструменты MCP (`ModuleMcpToolInterface`) → Диагностика и мониторинг очереди**.

---

## 1. Архитектура и сквозной поток данных

```
  [Внешний Webhook / Call]               [Событие ядра CRM (Domain Event)]
             │                                         │
             ▼                                         ▼
   Module REST Route                         FixtureConnectorServiceProvider
  (/_module/crm.fixture-connector/webhook)   (ModuleEvents::INTAKE_CREATED)
             │                                         │
             ├─────────────────┬───────────────────────┘
                               ▼
               ModuleJobDispatcher::dispatch()
             (сохранение в module_job_contexts с
              ModuleExecutionContext: org_id, actor, correlation)
                               │
                               ▼
                   Штатный Web-Cron / Manual Tick
                   (POST /api/v1/modules/queue/tick)
                               │
                               ▼
                        SyncIntakeJob
              (WorkspaceModuleJobInterface::handle)
                               │
                               ▼
            Идемпотентная запись в таблицу модуля:
                mod_crm_fixture_connector_log
```

---

## 2. Структура модуля

```
modules/crm.fixture-connector/
├── manifest.json                               # Декларативный манифест модуля и регистрация MCP
├── README.md                                    # Документация архитектуры
└── api/
    ├── FixtureConnectorServiceProvider.php     # Провайдер, подписка на события ядра
    ├── config/
    │   └── routes.php                          # REST-маршруты модуля (status, webhook, sync)
    ├── controller/
    │   └── FixtureConnectorController.php      # Обработчики REST-маршрутов
    ├── Job/
    │   └── SyncIntakeJob.php                   # Обработчик фоновой задачи в контексте воркспейса
    ├── mcp/
    │   ├── PingTool.php                        # MCP-инструмент: диагностика и пинг воркспейса
    │   └── SecureActionTool.php                # MCP-инструмент: безопасное действие с маскированием токенов
    └── migrations/
        ├── 001_create_fixture_connector_log.sql          # Применение схемы (префикс mod_*)
        └── 001_create_fixture_connector_log_rollback.sql # Скрипт отката
```

---

## 3. Манифест `manifest.json`

Манифест определяет метаданные, зависимости, требуемые права, маршруты, миграции и инструменты MCP:

```json
{
    "name": "crm.fixture-connector",
    "version": "1.0.0",
    "vendor": "crm",
    "title": "Fixture Connector Module",
    "description": "Reference connector module for workspace-aware execution, REST routes, and MCP tools",
    "core_version": ">=1.0.0",
    "license": "MIT",
    "category": "integrations",
    "require_permissions": [
        "settings.view",
        "settings.edit"
    ],
    "service_provider": "Module\\Crm\\FixtureConnector\\FixtureConnectorServiceProvider",
    "migrations": "api/migrations",
    "events": [
        "intake.created",
        "task.created"
    ],
    "api_routes": "api/config/routes.php",
    "mcp_tools": [
        {
            "name": "fixture_connector_ping",
            "description": "Ping the fixture connector and return workspace diagnostic status",
            "handler": "Module\\Crm\\FixtureConnector\\Mcp\\PingTool",
            "permissions": ["settings.view"],
            "mode": "all",
            "workspace_required": true,
            "input_schema": {
                "type": "object",
                "properties": {
                    "echo": {
                        "type": "string",
                        "description": "Optional echo text"
                    }
                },
                "additionalProperties": false
            }
        }
    ]
}
```

---

## 4. Контекст исполнения (`ModuleExecutionContext`)

Каждая точка входа модуля (REST, Webhook, Job, MCP) выполняется в строго изолированном контексте:

```php
$context = new ModuleExecutionContext(
    moduleName: 'crm.fixture-connector',
    organizationId: $orgId,
    organizationPublicId: $orgPublicId,
    actorPublicId: $actorPublicId,
    source: 'event', // 'api' | 'mcp' | 'cron' | 'event'
    correlationId: $correlationId
);
```

- **Инвариант изоляции:** Доступ к чужим воркспейсам блокируется на уровне ядра (`fail-closed`).
- **Сквозная трассировка:** `correlationId` сохраняется на всех этапах цепочки.

---

## 5. Фоновые задачи (`WorkspaceModuleJobInterface`)

Обработчик задачи реализует интерфейс `WorkspaceModuleJobInterface`:

```php
final class SyncIntakeJob implements WorkspaceModuleJobInterface
{
    public function handle(array $payload, ModuleExecutionContext $context, Container $container): void
    {
        $orgId = $context->organizationId;
        // Бизнес-логика строго в рамках $orgId
    }
}
```

### Диспетчеризация задачи:

```php
ModuleJobDispatcher::dispatch(
    container: $container,
    moduleName: 'crm.fixture-connector',
    handlerClass: SyncIntakeJob::class,
    payload: ['intake_public_id' => $id],
    context: $context
);
```

---

## 6. Запуск очереди на Shared-Хостинге (Zero-Daemon)

Модульная система не требует фоновых демонов, systemd, Redis или супервайзера.
1. **Штатный крон:** регулярный вызов Web-Cron (`/web/index.php?route=cron` или CLI `upload/api/scripts/cron.php`).
2. **Ручной запуск (Manual Tick):** в административной панели или через REST API:
   `POST /api/v1/modules/queue/tick`
   За один вызов обрабатывается ограниченный пакет задач (до 25 задач / до 15 секунд), укладываясь в лимиты хостинга.
3. **Диагностика очереди:**
   `GET /api/v1/modules/diagnostics` возвращает статус модулей, количество задач в очереди (`pending`, `failed`, `paused_legacy`) и ошибки.

---

## 7. REST-маршруты и валидация (`ModuleRouteValidator`)

Все маршруты модуля проверяются ядром:
- Префикс: `/_module/<module_name>/<path>`
- `workspace_required: true` — требует авторизации и выбора организации.
- `idempotency: true` — защищает от дубликатов запросов через `ConnectorIdempotencyStore`.
- Контроллер обязан находиться в пространстве имен `Module\`.

---

## 8. Инструменты MCP (`ModuleMcpToolInterface`)

Инструменты MCP модуля регистрируются в манифесте и исполняются в изолированном контексте:
- Профиль по умолчанию `core` (27 базовых инструментов) остаётся неизменным.
- Инструменты модулей доступны при запросе профилей `all`, `modules` или конкретного модуля `module:crm.fixture-connector`.
- Вывод инструментов автоматически очищается от секретов (токены, пароли, ключи) через `redactSensitiveOutput`.

---

## 9. Жизненный цикл, обновление и удаление

1. **Установка:** Применение миграций `api/migrations/*.sql` через `ModuleMigrationRunner`.
2. **Активация:** Регистрация маршрутов, хуков и инструментов MCP.
3. **Обновление:** Применение новых миграций. Идемпотентный DDL (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`).
4. **Деактивация:** Мгновенное скрытие инструментов MCP и маршрутов REST. Очередь приостанавливается.
5. **Удаление:** Деактивация модуля. Таблицы модуля **не удаляются автоматически**, сохраняя бизнес-данные клиента. При необходимости отката миграции запускаются вручную через `*_rollback.sql`.
