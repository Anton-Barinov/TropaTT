# TropaTT CRM Module Developer Guide

> Comprehensive technical guide to designing, developing, integrating, packaging, and distributing extension modules for TropaTT CRM. Completely synchronized with the core codebase (2026 release series).

---

## Table of Contents

1. [Module Subsystem Architecture](#1-module-subsystem-architecture)
2. [Module Anatomy, Directory Structure, and Autoloading (PSR-4 / ModuleAutoloader)](#2-module-anatomy-directory-structure-and-autoloading-psr-4--moduleautoloader)
3. [Manifest Specification (manifest.json)](#3-manifest-specification-manifestjson)
4. [Service Provider (AbstractModuleServiceProvider), DI Container, and RBAC](#4-service-provider-abstractmoduleserviceprovider-di-container-and-rbac)
5. [Event Bus: Complete ModuleEvents Catalog and HookManager](#5-event-bus-complete-moduleevents-catalog-and-hookmanager)
6. [Web UI Integration: Position Slots (PositionRegistry), Render Hooks, and i18n](#6-web-ui-integration-position-slots-positionregistry-render-hooks-and-i18n)
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
- **Fault Tolerance:** Any failure or uncaught exception originating inside a module must never break core user flows. The core wraps each hook execution in `try-catch` blocks and employs an automated **Circuit Breaker** (`ModuleCircuitBreaker`) that trips upon 5 consecutive failures.
- **Static Security Audit:** Prior to installation and activation, every PHP file in the module is scanned by `ModuleCodeValidator` using lexical token analysis (`token_get_all`). Dangerous functions and system execution calls (`eval`, `exec`, `shell_exec`, `passthru`, `file_put_contents`, `unlink`, `dl`, `ffi`, etc.) cause immediate rejection.
- **Transactional Schema Migrations:** Modules can manage their own database tables and indices. Schema state transitions are tracked in the system table `module_migrations`, supporting automated rollbacks upon uninstallation (`_rollback.sql` or `.down.sql`).
- **Standardized Namespace Scheme:** Module classes are automatically registered under standard namespaces `Module\<VendorName>\<ModuleName>\...` by the core runtime class loader `ModuleAutoloader`.

### Architectural Scheme

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

## 2. Module Anatomy, Directory Structure, and Autoloading (PSR-4 / ModuleAutoloader)

Each module resides in its dedicated directory inside the CRM modules directory:
`modules/{vendor}.{name}/` (in git/server root: `upload/modules/{vendor}.{name}/`).

A stock installation ships that directory empty — only its `.htaccess` is present. Modules are
installed on demand from the official marketplace (`marketplace.tropatt.com`) or by copying a
package into `modules/`; the ready-made examples live in `docs_modules/examples/modules/`.

### Module Naming Convention
- Identifier pattern: `^[a-z0-9]+\.[a-z0-9\-]+$` (lowercase letters, numbers, and dashes, max 64 chars).
- Examples: `crm.wip-limit`, `crm.slack-integration`, `acme.telegram-notifier`.

### Class Autoloading (`ModuleAutoloader`)
1. Vendor and module names with dashes are converted to **CamelCase**:
   - Directory: `modules/acme.telegram-notifier/`
   - Base namespace: `Module\Acme\TelegramNotifier\`
2. Classes are resolved across two subdirectories: first `api/`, then `web/`:
   - `Module\Acme\TelegramNotifier\Service\Notifier` is looked up in:
     1. `modules/acme.telegram-notifier/api/Service/Notifier.php`
     2. `modules/acme.telegram-notifier/api/service/Notifier.php`
     3. `modules/acme.telegram-notifier/web/Service/Notifier.php`
     4. `modules/acme.telegram-notifier/web/service/Notifier.php`

### Canonical Directory Tree

```
modules/acme.telegram-notifier/
├── manifest.json                      # Declarative module manifest (mandatory)
├── README.md                          # Module documentation and usage
├── api/                               # Backend & REST API logic
│   ├── TelegramNotifierServiceProvider.php  # Module Service Provider
│   ├── config/
│   │   └── routes.php                 # REST API route definitions
│   ├── controller/
│   │   └── ApiTelegramController.php  # API Controller
│   ├── service/
│   │   ├── TelegramService.php        # Business service
│   │   └── TelegramQueue.php          # Outbox queue
│   ├── hook/
│   │   └── StatusHook.php             # Core event listeners
│   ├── cron/
│   │   └── QueueWorkerHandler.php     # Scheduled cron handler
│   └── migrations/                    # SQL migrations
│       ├── 001_create_tables.sql
│       └── 001_create_tables_rollback.sql
└── web/                               # Frontend & UI positions
    ├── config/
    │   └── routes.php                 # Web page routes
    ├── controller/
    │   └── WebSettingsController.php  # Web page controller
    ├── position/
    │   └── TaskSidebarPanel.php       # UI slot renderer for task sidebar
    ├── template/
    │   └── page/
    │       └── settings.php           # Page view template
    ├── language/                      # i18n translations
    │   ├── ru-ru.php
    │   └── en-us.php
    └── assets/                        # Public static assets
        ├── css/
        │   └── widget.css
        └── js/
            └── widget.js
```

---

## 3. Manifest Specification (manifest.json)

The `manifest.json` file is parsed and validated by `Api\System\Library\Module\PluginManager`.

| Field | Type | Required | Description | Example |
|---|---|:---:|---|---|
| `name` | `string` | **Yes** | Unique module identifier (`^[a-z0-9]+\.[a-z0-9\-]+$`) | `"acme.telegram-notifier"` |
| `version` | `string` | **Yes** | Semantic version string (`SemVer`: `X.Y.Z`) | `"1.2.0"` |
| `vendor` | `string` | **Yes** | Organization or vendor handle | `"acme"` |
| `author` | `string` | **Yes** | Author name or handle | `"Anton Barinov"` |
| `author_url` | `string` | No | Link to developer profile or project site | `"https://github.com/Anton-Barinov"` |
| `title` | `string` | **Yes** | Human-readable title displayed in the CRM UI | `"Telegram Notifier"` |
| `description` | `string` | **Yes** | Concise description of the module | `"Sends instant Telegram alerts on task updates."` |
| `license` | `string` | No | License identifier | `"MIT"` |
| `category` | `string` | No | Catalog category (`integration`, `productivity`, `crm`, `finance`, `migration`) | `"integration"` |
| `core_version` | `string` | No | Required core version constraint (default: `>=1.0.0`) | `">=1.0.0"` |
| `dependencies` | `array` | No | Dependent module objects `[{"name": "..."}]` | `[]` |
| `require_permissions` | `array` | No | Required core RBAC permissions | `["tasks.read"]` |
| `service_provider` | `string` | No | FQCN of the service provider | `"Module\\Acme\\TelegramNotifier\\TelegramNotifierServiceProvider"` |
| `api_routes` | `string` | No | Relative path to REST API routes file | `"api/config/routes.php"` |
| `web_routes` | `string` | No | Relative path to Web routes file | `"web/config/routes.php"` |
| `migrations` | `string` | No | Relative path to SQL migrations directory | `"api/migrations/"` |
| `positions` | `object` | No | UI slot renderers map | See Section 6 |
| `assets` | `object` | No | CSS and JS assets map | See Section 8 |

---

## 4. Service Provider (AbstractModuleServiceProvider), DI Container, and RBAC

The service provider extends `Api\System\Library\Module\AbstractModuleServiceProvider`.

### Lifecycle Methods
- **`register(Container $container): void`**: Binds services and singletons into the container. Never execute side effects or query other modules here.
- **`boot(Container $container): void`**: Executed after all providers are registered. Used to subscribe to hooks in `HookManager`.

### RBAC Permission Registration
Permission strings returned by `getPermissions()` are automatically registered by the core into `PermissionRepository::ensureRegistry()`, making them immediately assignable to user roles in the CRM settings.

---

## 5. Event Bus: Complete ModuleEvents Catalog and HookManager

Hook handlers receive the event context **by reference**:
```php
function (array &$context): void
```

All 35+ core events are defined as constants in `Api\System\Library\Module\ModuleEvents`:
- **Task Lifecycle:** `TASK_CREATED`, `TASK_UPDATED`, `TASK_STATUS_CHANGED`, `TASK_ASSIGNEE_CHANGED`, `TASK_DELETED`
- **Project Lifecycle:** `PROJECT_CREATED`, `PROJECT_UPDATED`, `PROJECT_DELETED`
- **User Lifecycle:** `USER_CREATED`, `USER_UPDATED`, `USER_DELETED`
- **Cycle / Sprint:** `CYCLE_CREATED`, `CYCLE_STARTED`, `CYCLE_COMPLETED`, `CYCLE_REOPENED`, `CYCLE_ARCHIVED`, `CYCLE_DELETED`
- **CRM Entities:** `CLIENT_*`, `COUNTERPARTY_*`, `CONTACT_*`, `COMPANY_*`, `ORGANIZATION_*`
- **Collaboration & Chat:** `COMMENT_ADDED`, `FILE_UPLOADED`, `CHAT_MESSAGE_CREATED`, `CHAT_MESSAGE_UPDATED`, `CHAT_MESSAGE_DELETED`
- **Web Rendering:** `RENDER_BEFORE`, `RENDER_AFTER`

---

## 6. Web UI Integration: Position Slots (PositionRegistry), Render Hooks, and i18n

### Supported Template Slots
- `task.detail.sidebar`: Sidebar of task detail page (`view/template/page/task_detail.php`).
- `project.detail.sidebar`: Sidebar of project details.
- `gantt.content.after`: Under Gantt chart.
- `kanban.board.after`: Under Kanban board.
- `tasks.list.after`: Under task list table.
- `calendar.content.after`: Under calendar view.
- `counterparties.content.after`: Under counterparties list.
- `profile.content.after`: Under profile page.
- `dashboard.content.after`: Under dashboard widgets.

### Slot Renderer Implementation
```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Position;

final class TaskSidebarPanel
{
    public static function render(array $context): string
    {
        $taskPublicId = htmlspecialchars((string)($context['task_public_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        return '<div class="crm-card mb-3" data-task-public-id="' . $taskPublicId . '">'
            . '<div class="crm-side-card-head"><h2 class="h6 mb-0">Telegram Notifier</h2></div>'
            . '<div class="p-3 small text-muted">Telegram alerts active for this task.</div>'
            . '</div>';
    }
}
```

---

## 7. Web and REST API Routing

### REST API Routing (`api/config/routes.php`)
**Core Prefixing Rule:** The core router automatically mounts module API routes under:
`/_module/{vendor}.{name}`

```php
<?php
declare(strict_types=1);

use Module\Acme\TelegramNotifier\Controller\ApiTelegramController;

return [
    [
        'methods'    => ['GET'],
        'route'      => '/settings', // Mounted as /_module/acme.telegram-notifier/settings
        'controller' => ApiTelegramController::class,
        'action'     => 'getSettings',
        'auth'       => true,
    ],
];
```

Controllers return `Api\System\Library\Http\JsonResponse::success(...)` or `JsonResponse::error(...)`.

### Web Interface Routing (`web/config/routes.php`)
```php
<?php
declare(strict_types=1);

use Module\Acme\TelegramNotifier\Controller\WebSettingsController;

return [
    'module-telegram-settings' => [WebSettingsController::class, 'index'],
];
```

---

## 8. Asset Management (CSS, JS)

Declared in `manifest.json` under `assets`:
- `css` & `js`: Global resources loaded on every CRM view.
- `css_routes` & `js_routes`: Scoped resources loaded only on specific route keys (e.g. `"task-detail"`).

---

## 9. Database Migrations and Schema Lifecycle

Migrations are located in `api/migrations/`.
Supported file conventions:
- `001_create_tables.sql` (up) and `001_create_tables_rollback.sql` (down)
- `001_init.up.sql` (up) and `001_init.down.sql` (down)

All executions are recorded transactionally in `module_migrations`.

---

## 10. Background Tasks: Cron Scheduler and Transactional Job Dispatcher

- **`ModuleCronScheduler`**: Handler classes must be under `Api\...` or `Module\...` namespaces. Allowed method names: `run`, `execute`, `handle`, `process`, `freshnessScan`, `draftsCleanup`, `versionsCleanup`, `reindexSearch`, `captureDaily`, `autoClosePeriods`, `dispatchQueue`.
- **`ModuleJobDispatcher`**: Enqueue transactional jobs with delay into `module_jobs` without external daemons.

---

## 11. Security, Static Code Validation, and Fault Isolation (Circuit Breaker)

- **`ModuleCodeValidator`**: Lexical analysis disallows `eval`, `exec`, `system`, `shell_exec`, `passthru`, `popen`, `proc_open`, `pcntl_exec`, `assert`, `create_function`, `include`, `file_put_contents`, `unlink`, `rmdir`, `chmod`, `chown`, `dl`, `ffi`.
- **`ModuleCircuitBreaker`**: 5 consecutive failures switch state to `OPEN`, isolating the module. After 60 seconds, `HALF_OPEN` permits trial requests.

---

## 12. Packaging, Versioning, and Remote Installation

Package as a flat ZIP archive containing `manifest.json` at root. Installable via `ModuleRemoteInstaller::installFromUrl()` with SSRF protection.

---

## 13. Complete Reference Module: Acme Telegram Notifier

### manifest.json
```json
{
  "name": "acme.telegram-notifier",
  "version": "1.0.0",
  "vendor": "acme",
  "author": "Anton Barinov",
  "author_url": "https://github.com/Anton-Barinov",
  "title": "Telegram Notifier",
  "description": "Telegram notifications for task events in TropaTT CRM",
  "category": "integration",
  "license": "MIT",
  "core_version": ">=1.0.0",
  "dependencies": [],
  "require_permissions": ["tasks.read"],
  "service_provider": "Module\\Acme\\TelegramNotifier\\TelegramNotifierServiceProvider",
  "api_routes": "api/config/routes.php",
  "web_routes": "web/config/routes.php",
  "migrations": "api/migrations/",
  "config_defaults": {
    "bot_token": "",
    "chat_id": ""
  },
  "positions": {
    "task.detail.sidebar": [
      {
        "renderer": "Module\\Acme\\TelegramNotifier\\Position\\TaskSidebarPanel::render",
        "priority": 15,
        "key": "telegram_panel"
      }
    ]
  },
  "hooks": {}
}
```

### api/TelegramNotifierServiceProvider.php
```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class TelegramNotifierServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }
}
```

---

*Documentation updated for TropaTT CRM 2026 Core Release.*
