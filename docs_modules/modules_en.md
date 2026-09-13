# TropaTT CRM Module Developer Guide

> Comprehensive technical guide to designing, developing, integrating, packaging, and distributing extension modules for TropaTT CRM.

---

## Table of Contents

1. [Module Subsystem Architecture](#1-module-subsystem-architecture)
2. [Module Anatomy and Directory Structure](#2-module-anatomy-and-directory-structure)
3. [Manifest Specification (manifest.json)](#3-manifest-specification-manifestjson)
4. [Service Provider (AbstractModuleServiceProvider) and Dependency Injection](#4-service-provider-abstractmoduleserviceprovider-and-dependency-injection)
5. [Event Bus and Complete ModuleEvents Catalog](#5-event-bus-and-complete-moduleevents-catalog)
6. [Web UI Integration: Position Slots (PositionRegistry) and Templates](#6-web-ui-integration-position-slots-positionregistry-and-templates)
7. [Web and REST API Routing](#7-web-and-rest-api-routing)
8. [Asset Management (CSS, JS)](#8-asset-management-css-js)
9. [Database Migrations and Schema Lifecycle](#9-database-migrations-and-schema-lifecycle)
10. [Background Tasks: Cron Scheduler and Transactional Job Dispatcher](#10-background-tasks-cron-scheduler-and-transactional-job-dispatcher)
11. [Security, Static Code Validation, and Fault Isolation (Circuit Breaker)](#11-security-static-code-validation-and-fault-isolation-circuit-breaker)
12. [Packaging, Versioning, and Remote Installation](#12-packaging-versioning-and-remote-installation)
13. [Complete Reference Module: Acme Telegram Notifier](#13-complete-reference-module-acme-telegram-notifier)

---

## 1. Module Subsystem Architecture

The TropaTT CRM module subsystem is designed according to **Zero-Daemon** principles, optimal performance under shared-hosting resource constraints (< 32 MB RAM, execution timeouts up to 25 seconds), and strict isolation of third-party extensions from the core.

### Core Architectural Principles

- **Zero-Core-Edits:** Modules extend CRM capabilities strictly via declarative contracts (`manifest.json`), UI injection points (`PositionRegistry`), event hooks (`HookManager`), and route definitions (`api_routes`, `web_routes`). Modifying the core codebase is forbidden.
- **Fault Tolerance:** Any failure or uncaught exception originating inside a module must never break core user flows. The core wraps each hook execution in `try-catch` blocks and employs an automated **Circuit Breaker**.
- **Static Security Audit:** Prior to installation and activation, every PHP file in the module is scanned by `ModuleCodeValidator` using lexical token analysis (`token_get_all`). Dangerous functions and system execution calls (`eval`, `exec`, `shell_exec`, `passthru`, `file_put_contents`, etc.) cause immediate rejection.
- **Transactional Schema Migrations:** Modules can manage their own database tables and indices. Schema state transitions are tracked in the system table `module_migrations`, supporting automated rollbacks upon uninstallation.
- **PSR-4 Compliant Autoloading:** Module classes are automatically registered under standard namespaces `Module\<VendorName>\<ModuleName>\` by the core runtime class loader.

### Architecture Diagram

```
+-----------------------------------------------------------------------------------+
|                                 TropaTT Core                                      |
|                                                                                   |
|   +-------------------+       +--------------------+       +------------------+   |
|   |   FrontController |       |    PositionRegistry|       |   HookManager    |   |
|   |   (Web & REST)    |       |  (UI Slots Engine) |       |  (Event Bus)     |   |
|   +---------+---------+       +---------+----------+       +--------+---------+   |
|             |                           |                           |             |
+-------------|---------------------------|---------------------------|-------------+
              |                           |                           |
              v                           v                           v
+-----------------------------------------------------------------------------------+
|                             PluginManager Runtime                                 |
|                                                                                   |
|  +--------------------+   +-----------------------+   +------------------------+  |
|  | ModuleCodeValidator|   | ModuleCircuitBreaker  |   | ModuleMigrationRunner  |  |
|  +--------------------+   +-----------------------+   +------------------------+  |
|                                                                                   |
|  +--------------------+   +-----------------------+   +------------------------+  |
|  | ModuleAssetManager |   |  ModuleCronScheduler  |   |  ModuleJobDispatcher   |  |
|  +--------------------+   +-----------------------+   +------------------------+  |
+-------------------------------------+---------------------------------------------+
                                      |
                                      v
+-----------------------------------------------------------------------------------+
|                        Extension Module (Vendor / Package)                        |
|                                                                                   |
|  +---------------+  +--------------------------+  +----------------------------+  |
|  | manifest.json |  | ServiceProvider.php      |  | Controllers & Models       |  |
|  +---------------+  +--------------------------+  +----------------------------+  |
|  +---------------+  +--------------------------+  +----------------------------+  |
|  | migrations/*.sql | assets/ (css, js)       |  | templates/ (*.twig / *.php)|  |
|  +---------------+  +--------------------------+  +----------------------------+  |
+-----------------------------------------------------------------------------------+
```

---

## 2. Module Anatomy and Directory Structure

Each module resides in its dedicated directory inside the CRM modules directory:
`modules/{vendor}.{name}/` (or `upload/modules/{vendor}.{name}/`).

The naming convention follows `{vendor}.{name}`, with lowercase alphanumeric characters separated by a period (for example, `acme.telegram_notifier`, `retail.cdek_shipping`).

### Canonical Directory Tree

```
modules/acme.telegram_notifier/
├── manifest.json                  # Declarative module manifest (mandatory)
├── ServiceProvider.php            # Service provider, lifecycle hooks & registration
├── api_routes.php                 # REST API route definitions
├── web_routes.php                 # Web interface route definitions
├── controllers/                   # Module controllers
│   ├── ApiSettingsController.php
│   └── WebSettingsController.php
├── models/                        # Domain models and data access
│   └── TelegramLogModel.php
├── templates/                     # View templates (HTML/Twig/PHP)
│   ├── settings.php
│   └── sidebar_widget.php
├── assets/                        # Public static assets
│   ├── css/
│   │   └── widget.css
│   └── js/
│       └── notifier.js
├── migrations/                    # Database migration scripts
│   ├── 001_create_telegram_logs.up.sql
│   └── 001_create_telegram_logs.down.sql
└── cron/                          # Scheduled cron task handlers
    └── NotificationQueueHandler.php
```

---

## 3. Manifest Specification (manifest.json)

The `manifest.json` file is the central entry point of any module. It is parsed by `Api\System\Library\Module\Manifest` during module discovery.

### Manifest Fields Reference

| Field | Type | Required | Description | Example |
|---|---|:---:|---|---|
| `name` | `string` | **Yes** | Unique system module identifier in `{vendor}.{name}` format | `"acme.telegram_notifier"` |
| `version` | `string` | **Yes** | Semantic version string (`SemVer`) | `"1.2.0"` |
| `vendor` | `string` | **Yes** | Organization, vendor, or brand name | `"Acme Software Ltd"` |
| `author` | `string` | **Yes** | Author name or handle | `"Anton Barinov"` |
| `author_url` | `string` | No | Link to developer profile or project site | `"https://github.com/Anton-Barinov"` |
| `title` | `string` | **Yes** | Human-readable title displayed in the CRM admin UI | `"Telegram Notifier"` |
| `description` | `string` | **Yes** | Concise description of the module features | `"Sends real-time notifications for tasks to Telegram chats."` |
| `category` | `string` | No | Module catalog category (`integrations`, `crm`, `finance`, `tools`, `reports`) | `"integrations"` |
| `core_version` | `string` | No | Required core CRM version constraint (default: `>=1.0.0`) | `">=1.0.0"` |
| `dependencies` | `array` | No | List of required module names | `["acme.core_tools"]` |
| `require_permissions` | `array` | No | List of system RBAC permissions required by the module | `["tasks.read", "settings.manage"]` |
| `service_provider` | `string` | No | FQCN or filename of the service provider | `"Module\\Acme\\TelegramNotifier\\ServiceProvider"` |
| `api_routes` | `string` | No | Relative path to REST API route definitions file | `"api_routes.php"` |
| `web_routes` | `string` | No | Relative path to Web route definitions file | `"web_routes.php"` |
| `migrations` | `string` | No | Relative path to SQL migrations directory | `"migrations"` |
| `hooks` | `object` | No | Declarative event listeners map | `{"task.created": [{"handler": "...", "priority": 10}]}` |
| `positions` | `object` | No | Declarative UI slot renderers | `{"task.detail.sidebar": [{"renderer": "...", "priority": 10}]}` |
| `menu_items` | `array` | No | Navigation sidebar menu items | See structure below |
| `config_defaults` | `object` | No | Default configuration key-value pairs | `{"bot_token": "", "chat_id": ""}` |
| `assets` | `object` | No | Asset registration for CSS and JS files | See asset management section |
| `web_hooks` | `array` | No | External inbound webhook configurations | `[{"route": "telegram/webhook", "action": "handle"}]` |

### Complete manifest.json Example:

```json
{
  "name": "acme.telegram_notifier",
  "version": "1.0.0",
  "vendor": "Acme Software Ltd",
  "author": "Anton Barinov",
  "author_url": "https://github.com/Anton-Barinov",
  "title": "Telegram Notifier",
  "description": "Instant notifications on task status changes and comments in Telegram",
  "category": "integrations",
  "core_version": ">=1.0.0",
  "dependencies": [],
  "require_permissions": [
    "tasks.read",
    "tasks.edit"
  ],
  "service_provider": "Module\\Acme\\TelegramNotifier\\ServiceProvider",
  "api_routes": "api_routes.php",
  "web_routes": "web_routes.php",
  "migrations": "migrations",
  "config_defaults": {
    "telegram_bot_token": "",
    "telegram_default_chat_id": "",
    "notify_on_status_change": 1,
    "notify_on_comment": 1
  },
  "menu_items": [
    {
      "route": "module/acme_telegram/settings",
      "label": "Telegram Settings",
      "icon": "bi bi-telegram",
      "permission": "settings.manage",
      "parent": "settings"
    }
  ],
  "assets": {
    "css": [
      "assets/css/widget.css"
    ],
    "js": [
      "assets/js/notifier.js"
    ],
    "css_routes": {
      "task-detail": "assets/css/widget.css"
    }
  },
  "positions": {
    "task.detail.sidebar": [
      {
        "renderer": "Module\\Acme\\TelegramNotifier\\Controllers\\WebSettingsController::renderSidebarWidget",
        "priority": 15,
        "key": "acme_telegram_sidebar"
      }
    ]
  },
  "hooks": {
    "task.status_changed": [
      {
        "handler": "Module\\Acme\\TelegramNotifier\\Handlers\\StatusChangeHandler::handle",
        "priority": 10
      }
    ],
    "comment.added": [
      {
        "handler": "Module\\Acme\\TelegramNotifier\\Handlers\\CommentHandler::handle",
        "priority": 5
      }
    ]
  }
}
```

---

## 4. Service Provider (AbstractModuleServiceProvider) and Dependency Injection

The `ServiceProvider` class acts as the bridge connecting your module to the CRM core. Developers extend `Api\System\Library\Module\AbstractModuleServiceProvider`, which implements `ModuleServiceProviderInterface`.

### Lifecycle Methods

1. **`register(Container $container): void`**
   - Invoked during container construction before routing and request dispatching.
   - Used to bind module services, singletons, API clients, and repositories into the dependency container.
   - **Crucial:** Never query other modules or trigger side-effects in `register()`, as peer services may not yet be loaded.

2. **`boot(Container $container): void`**
   - Invoked after all core subsystems and active modules have completed registration.
   - Used for dynamic hook subscriptions, environment checks, and runtime initializations.

### Declarative Provider Contract

| Method | Return Type | Description |
|---|---|---|
| `getHooks()` | `array` | Event subscriptions map: `[event => [['handler' => ..., 'priority' => 10]]]` |
| `getPermissions()` | `array<int, string>` | Unique permission keys contributed to the CRM RBAC table |
| `getMenuItems()` | `array<int, array>` | Navigation items published to the sidebar menu |
| `getConfig()` | `array<string, mixed>` | Dynamic configuration settings |
| `getAssets()` | `array<string, mixed>` | Registered CSS and JS files |
| `getScheduledTasks()` | `array<int, ScheduledTask>` | Array of `ScheduledTask` instances for the cron scheduler |

### Complete ServiceProvider.php Example

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ModuleEvents;
use Api\System\Library\Module\ScheduledTask;
use Module\Acme\TelegramNotifier\Services\TelegramService;
use Module\Acme\TelegramNotifier\Cron\NotificationQueueHandler;

class ServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(TelegramService::class, static function (Container $c): TelegramService {
            $config = $c->get('config');
            $pdo = $c->get(\PDO::class);
            return new TelegramService(
                botToken: (string)($config['telegram_bot_token'] ?? ''),
                pdo: $pdo
            );
        });
    }

    public function boot(Container $container): void
    {
        // Runtime bootstrapping logic
    }

    public function getHooks(): array
    {
        return [
            ModuleEvents::TASK_STATUS_CHANGED => [
                [
                    'handler' => '\\Module\\Acme\\TelegramNotifier\\Handlers\\StatusChangeHandler::handle',
                    'priority' => 20,
                ],
            ],
            ModuleEvents::COMMENT_ADDED => [
                [
                    'handler' => '\\Module\\Acme\\TelegramNotifier\\Handlers\\CommentHandler::handle',
                    'priority' => 10,
                ],
            ],
        ];
    }

    public function getPermissions(): array
    {
        return [
            'acme.telegram.manage',
            'acme.telegram.view_logs',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module/acme_telegram/settings',
                'label' => 'Telegram Bot',
                'icon' => 'bi bi-telegram',
                'permission' => 'acme.telegram.manage',
                'parent' => 'settings',
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                moduleName: 'acme.telegram_notifier',
                taskName: 'flush_telegram_queue',
                schedule: '*/2 * * * *', // Every 2 minutes
                handlerClass: NotificationQueueHandler::class,
                handlerMethod: 'run'
            ),
        ];
    }
}
```

---

## 5. Event Bus and Complete ModuleEvents Catalog

TropaTT CRM provides an event bus defined in `ModuleEvents` (`Api\System\Library\Module\ModuleEvents`). At key domain lifecycle milestones, the core dispatches events via `HookManager`.

### ModuleEvents Catalog Reference

```php
namespace Api\System\Library\Module;

final class ModuleEvents
{
    // Task lifecycle (dispatched from TaskController)
    public const TASK_CREATED          = 'task.created';
    public const TASK_UPDATED          = 'task.updated';
    public const TASK_STATUS_CHANGED   = 'task.status_changed';
    public const TASK_ASSIGNEE_CHANGED = 'task.assignee_changed';
    public const TASK_DELETED          = 'task.deleted';

    // Project lifecycle (dispatched from ProjectController)
    public const PROJECT_CREATED       = 'project.created';
    public const PROJECT_UPDATED       = 'project.updated';
    public const PROJECT_DELETED       = 'project.deleted';

    // User lifecycle (dispatched from UserController)
    public const USER_CREATED          = 'user.created';
    public const USER_UPDATED          = 'user.updated';
    public const USER_DELETED          = 'user.deleted';

    // Work cycle / sprint lifecycle (dispatched from WorkCycleController)
    public const CYCLE_CREATED         = 'cycle.created';
    public const CYCLE_STARTED         = 'cycle.started';
    public const CYCLE_COMPLETED       = 'cycle.completed';
    public const CYCLE_REOPENED        = 'cycle.reopened';
    public const CYCLE_ARCHIVED        = 'cycle.archived';
    public const CYCLE_DELETED         = 'cycle.deleted';

    // CRM records (clients, counterparties, contacts, companies, organizations)
    public const CLIENT_CREATED        = 'client.created';
    public const CLIENT_UPDATED        = 'client.updated';
    public const CLIENT_DELETED        = 'client.deleted';
    public const COUNTERPARTY_CREATED  = 'counterparty.created';
    public const COUNTERPARTY_UPDATED  = 'counterparty.updated';
    public const COUNTERPARTY_DELETED  = 'counterparty.deleted';
    public const CONTACT_CREATED       = 'contact.created';
    public const CONTACT_UPDATED       = 'contact.updated';
    public const CONTACT_DELETED       = 'contact.deleted';
    public const COMPANY_CREATED       = 'company.created';
    public const COMPANY_UPDATED       = 'company.updated';
    public const COMPANY_DELETED       = 'company.deleted';
    public const ORGANIZATION_CREATED  = 'organization.created';
    public const ORGANIZATION_UPDATED  = 'organization.updated';
    public const ORGANIZATION_DELETED  = 'organization.deleted';

    // Taxonomy and classifications
    public const TAG_CREATED           = 'tag.created';
    public const TAG_UPDATED           = 'tag.updated';
    public const TAG_DELETED           = 'tag.deleted';
    public const STATUS_CREATED        = 'status.created';
    public const STATUS_UPDATED        = 'status.updated';
    public const STATUS_DELETED        = 'status.deleted';
    public const PRIORITY_CREATED      = 'priority.created';
    public const PRIORITY_UPDATED      = 'priority.updated';
    public const PRIORITY_DELETED      = 'priority.deleted';
    public const CUSTOM_FIELD_CREATED  = 'custom_field.created';
    public const CUSTOM_FIELD_UPDATED  = 'custom_field.updated';
    public const CUSTOM_FIELD_DELETED  = 'custom_field.deleted';

    // Collaboration, chat, and files
    public const COMMENT_ADDED         = 'comment.added';
    public const FILE_UPLOADED         = 'file.uploaded';
    public const CHAT_MESSAGE_CREATED  = 'chat.message_created';
    public const CHAT_MESSAGE_UPDATED  = 'chat.message_updated';
    public const CHAT_MESSAGE_DELETED  = 'chat.message_deleted';

    // Core web template rendering (Web\Controller)
    public const RENDER_BEFORE         = 'render.before';
    public const RENDER_AFTER          = 'render.after';
}
```

### Event Handler Implementation

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Handlers;

final class StatusChangeHandler
{
    /**
     * @param array{
     *     task_id: int,
     *     task_public_id: string,
     *     old_status: string,
     *     new_status: string,
     *     user_id: int,
     *     title: string
     * } $payload
     */
    public static function handle(array $payload): void
    {
        $taskKey = $payload['task_key'] ?? ('Task #' . ($payload['task_id'] ?? 0));
        $oldStatus = $payload['old_status'] ?? 'unknown';
        $newStatus = $payload['new_status'] ?? 'unknown';

        // Push to transactional background queue
        // (does not block client HTTP response!)
        \Module\Acme\TelegramNotifier\Services\TelegramQueue::push([
            'text' => sprintf("Task %s changed status from %s to %s", $taskKey, $oldStatus, $newStatus),
        ]);
    }
}
```

---

## 6. Web UI Integration: Position Slots (PositionRegistry) and Templates

To inject custom UI widgets without editing core templates, TropaTT uses `Web\System\Module\PositionRegistry`.

### How Position Slots Work

Core templates invoke the helper function:
`<?= module_position('task.detail.sidebar', ['task' => $task, 'public_id' => $publicId]) ?>`

The registry aggregates all registered renderers for that slot, sorts them in descending order of `priority` (e.g. 100 before 10), and wraps the returned HTML in a container with a designated `key`.

### Supported Core Slots

| Slot Position | Core Template Context | Context Array ($context) |
|---|---|---|
| `task.detail.sidebar` | Task detail view sidebar | `['task' => array, 'task_id' => int, 'public_id' => string]` |
| `task.detail.tabs` | Additional tab panels in task details | `['task' => array, 'task_id' => int]` |
| `project.detail.sidebar` | Project details sidebar | `['project' => array, 'project_id' => int]` |
| `client.detail.sidebar` | CRM client details sidebar | `['client' => array, 'client_id' => int]` |
| `dashboard.widgets.top` | Main dashboard top widget row | `['user_id' => int, 'dashboard_data' => array]` |
| `header.actions.right` | Top navigation bar right quick-action buttons | `['current_user' => array]` |

### Slot Renderer Example

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Controllers;

final class WebSettingsController
{
    /**
     * Render sidebar widget inside task details.
     * @param array<string, mixed> $context
     * @return string HTML output
     */
    public static function renderSidebarWidget(array $context): string
    {
        $task = $context['task'] ?? [];
        $taskId = (int)($task['id'] ?? 0);
        $taskKey = htmlspecialchars((string)($task['task_key'] ?? ''), ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <div class="card mb-3 acme-telegram-widget" data-task-id="<?= $taskId ?>">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold small"><i class="bi bi-telegram text-primary me-1"></i> Telegram Chat</span>
                <span class="badge bg-success-subtle text-success">Connected</span>
            </div>
            <div class="card-body py-2 small">
                <p class="text-muted mb-2">Notifications for <strong><?= $taskKey ?></strong> are delivered to team Telegram.</p>
                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="AcmeTelegram.testNotification(<?= $taskId ?>)">
                    Send Test Alert
                </button>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
```

---

## 7. Web and REST API Routing

Modules declare REST API endpoints via `api_routes.php` and web pages via `web_routes.php`.

### REST API Routes (`api_routes.php`)

```php
<?php
declare(strict_types=1);

use Module\Acme\TelegramNotifier\Controllers\ApiTelegramController;

return [
    'POST /api/v1/module/acme-telegram/test' => [
        'controller' => ApiTelegramController::class,
        'action'     => 'sendTest',
        'permission' => 'acme.telegram.manage',
        'rate_limit' => 30, // 30 requests per minute
    ],
    'GET /api/v1/module/acme-telegram/logs' => [
        'controller' => ApiTelegramController::class,
        'action'     => 'listLogs',
        'permission' => 'acme.telegram.view_logs',
    ],
];
```

### API Controller

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Controllers;

use Api\System\Library\Support\ApiResponse;
use Api\System\Library\Container;

final class ApiTelegramController
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function sendTest(array $params): array
    {
        $chatId = (string)($params['chat_id'] ?? '');
        if ($chatId === '') {
            return ApiResponse::error('CHAT_ID_REQUIRED', 'Telegram chat_id is required', 400);
        }

        /** @var \Module\Acme\TelegramNotifier\Services\TelegramService $telegram */
        $telegram = $this->container->get(\Module\Acme\TelegramNotifier\Services\TelegramService::class);
        $result = $telegram->sendMessage($chatId, "Test alert from TropaTT CRM!");

        if (!$result['success']) {
            return ApiResponse::error('SEND_FAILED', $result['error'] ?? 'Delivery failed', 502);
        }

        return ApiResponse::success(['delivered' => true, 'timestamp' => time()]);
    }
}
```

---

## 8. Asset Management (CSS, JS)

The core manages static assets using `Web\System\Module\ModuleAssetManager`.

### Assets Manifest Configuration

- **`assets.css`**: Array of CSS stylesheets loaded globally on all CRM pages.
- **`assets.js`**: Array of JavaScript scripts loaded globally.
- **`assets.css_routes`**: Dictionary `{"route_name": "path.css"}` for route-specific CSS loading.
- **`assets.js_routes`**: Dictionary for route-specific JS loading.

```json
"assets": {
  "css": [
    "assets/css/global_badge.css"
  ],
  "js": [
    "assets/js/core_notifier.js"
  ],
  "css_routes": {
    "task-detail": "assets/css/widget.css",
    "module/acme_telegram/settings": "assets/css/settings.css"
  },
  "js_routes": {
    "task-detail": "assets/js/widget_interaction.js"
  }
}
```

---

## 9. Database Migrations and Schema Lifecycle

Migrations are orchestrated by `Api\System\Library\Module\ModuleMigrationRunner`.

### Migration Conventions

1. Scripts reside in the directory designated in `manifest.json` (`migrations/`).
2. Filenames follow standard version and direction patterns:
   - `{version}_{name}.up.sql` — forward migration.
   - `{version}_{name}.down.sql` — rollback migration.
3. Examples:
   - `001_create_telegram_tables.up.sql`
   - `001_create_telegram_tables.down.sql`

### Example Migration (001_create_telegram_tables.up.sql)

```sql
CREATE TABLE IF NOT EXISTS `module_acme_telegram_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` BIGINT UNSIGNED NULL,
    `chat_id` VARCHAR(64) NOT NULL,
    `message_text` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `response_payload` JSON NULL,
    `error_message` VARCHAR(512) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status_created` (`status`, `created_at`),
    KEY `idx_task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 10. Background Tasks: Cron Scheduler and Transactional Job Dispatcher

### 1. Cron Scheduler (`ModuleCronScheduler`)

Modules register periodic tasks in `ServiceProvider::getScheduledTasks()`.

```php
new ScheduledTask(
    moduleName: 'acme.telegram_notifier',
    taskName: 'telegram_retry_queue',
    schedule: '*/5 * * * *', // Every 5 minutes
    handlerClass: NotificationQueueHandler::class,
    handlerMethod: 'run'
)
```

**Security Constraints:**
- **Namespace Allowlist:** Handler classes must belong to `Api\...` or `Module\...`.
- **Method Allowlist:** Allowed execution methods: `run`, `execute`, `handle`, `process`, `freshnessScan`, `draftsCleanup`, `versionsCleanup`, `reindexSearch`, `captureDaily`, `autoClosePeriods`, `dispatchQueue`.
- Inactive modules have their cron tasks safely skipped (`skipped`) to prevent runtime failures.

### 2. Transactional Job Dispatcher (`ModuleJobDispatcher`)

For asynchronous operations without long daemons:

```php
use Api\System\Library\Module\ModuleJobDispatcher;

$dispatcher = $container->get(ModuleJobDispatcher::class);
$jobId = $dispatcher->dispatch(
    moduleName: 'acme.telegram_notifier',
    jobName: 'send_telegram_http_message',
    payload: [
        'chat_id' => '-100123456789',
        'text' => 'Hello from transactional background queue!',
    ],
    delay: 10
);
```

---

## 11. Security, Static Code Validation, and Fault Isolation (Circuit Breaker)

### 1. Static Code Validation (`ModuleCodeValidator`)

`ModuleCodeValidator` scans all module files using lexical token analysis (`token_get_all`).
The following functions and constructs are strictly forbidden:

```php
private array $forbiddenFunctions = [
    'eval', 'exec', 'system', 'shell_exec', 'passthru',
    'popen', 'proc_open', 'pcntl_exec', 'assert',
    'create_function', 'include', 'file_put_contents',
    'unlink', 'rmdir', 'chmod', 'chown',
    'dl', 'ffi',
];
```

### 2. Cascade Failure Prevention (`ModuleCircuitBreaker`)

- **State `CLOSED`:** Normal module operation.
- **Error Threshold:** Upon **5 consecutive failures**, the Circuit Breaker trips to **`OPEN`**.
- In the `OPEN` state, all module hooks are bypassed immediately to protect the core.
- **Recovery Timeout:** After 60 seconds, the breaker enters **`HALF_OPEN`**, permitting up to 3 test requests. Success resets to `CLOSED`, while further errors return to `OPEN`.

---

## 12. Packaging, Versioning, and Remote Installation

### Distribution Archive Structure

```
module-acme-telegram-1.0.0.zip
├── manifest.json
├── ServiceProvider.php
├── api_routes.php
├── migrations/
└── ...
```

### Remote Installation (`ModuleRemoteInstaller`)

```php
use Api\System\Library\Module\ModuleRemoteInstaller;

$installer = $container->get(ModuleRemoteInstaller::class);
$moduleName = $installer->installFromUrl(
    url: 'https://marketplace.tropatt.com/downloads/acme.telegram_notifier-1.0.0.zip',
    verifySignature: true
);
```

**Installation Workflow:**
1. SSRF URL validation via `UrlSafetyValidator` (private IP ranges blocked).
2. Download into temporary sandbox.
3. ZIP archive integrity and `manifest.json` verification.
4. Static code audit via `ModuleCodeValidator`.
5. Version and dependency verification (`core_version`, `dependencies`).
6. Unpack to `modules/{vendor}.{name}`.
7. Execute schema migrations via `ModuleMigrationRunner`.
8. Seed configuration defaults.
9. Register in active module catalog.

---

## 13. Complete Reference Module: Acme Telegram Notifier

Refer to Section 13 in the Russian documentation or inspect `/modules/acme.telegram_notifier/` for a production-ready reference implementation.

---

*Documentation updated for TropaTT CRM 2026 Core Release.*
