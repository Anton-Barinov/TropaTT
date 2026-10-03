# Module Development Guide

> 📖 **Full Multilingual Documentation Suite / Полная документация / 完整模块开发指南:**
> - 🇷🇺 [Руководство разработчика модулей (Русский)](docs_modules/modules_ru.md) — 13 подробных глав, архитектура, шина событий, позиции UI, транзакционные очереди и безопасность.
> - 🇬🇧 [Module Developer Guide (English)](docs_modules/modules_en.md) — Full technical specification, manifest reference, service provider lifecycle, and circuit breaker.
> - 🇨🇳 [模块开发者指南 (中文)](docs_modules/modules_zh.md) — 架构原理解析、插槽注册、静态代码安全审查与远程分发。

This guide documents how to build a **self-contained module** for TropaTT. Modules extend the core **without modifying it**: a module declares in `manifest.json` and in its own classes where it wants to connect — events, positions, assets — and the core calls it at the right moment.

A module is a directory under `modules/` named `vendor.name` (for example `crm.wip-limit`). The contents of `upload/` are installed into the server document root, so a module lives at `modules/vendor.name/` next to the core `api/` and `web/` applications.

> **A stock installation ships no modules.** `upload/modules/` contains nothing but its own `.htaccess`: modules are installed on demand from the official marketplace (`marketplace.tropatt.com`) — from **Administration → Modules → Marketplace** in the UI, or by dropping a package into `modules/` yourself. The examples below are published with this documentation instead of being installed with the core, so the guide stays runnable while a fresh install stays lean.

> Reference implementations (published with this guide, not installed with the core):
> - [`crm.position-example`](docs_modules/examples/modules/crm.position-example) — the smallest possible module: one position renderer + route-scoped assets.
> - [`crm.wip-limit`](docs_modules/examples/modules/crm.wip-limit) — full UI example: service provider, event hooks, scoped assets, position renderer, migrations, API + web routes.
> - [`crm.fixture-connector`](docs_modules/examples/modules/crm.fixture-connector) — reference integration connector: domain event subscription, queue job dispatch with `ModuleExecutionContext`, protected & idempotent REST routes, and module MCP tools.
> - `crm.slack-integration` — subscribes to the whole event catalog and fans events out to user-defined rules; install it from the marketplace.

---

## 1. Module structure

```
modules/crm.example/
├── manifest.json              ← the single source of truth
├── README.md                  ← optional module readme
├── api/                       ← API side (PHP, same autoloading as core)
│   ├── config/routes.php      ← API routes (optional)
│   ├── controller/…           ← API controllers (optional)
│   ├── Hook/…                 ← event handlers (optional)
│   ├── Service/…              ← services (optional)
│   ├── migrations/            ← SQL migrations (optional)
│   └── ExampleServiceProvider.php
└── web/                       ← web side
    ├── config/routes.php      ← web routes (optional)
    ├── controller/…           ← page controllers (optional)
    ├── position/…             ← position renderers (optional)
    ├── assets/                ← css/js, loaded per-route or globally
    └── template/page/…        ← page templates (optional)
```

Classes use the namespace `Module\Vendor\Name\…` (vendor/name derived from the module name, with dashes converted to CamelCase). The `ModuleAutoloader` resolves `Module\Crm\Example\Service\Foo` to `modules/crm.example/api/Service/Foo.php` and `modules/crm.example/web/Service/Foo.php`.

## 2. manifest.json

Required fields: `name`, `version`, `vendor`, `title`. The name must match `^[a-z0-9]+\.[a-z0-9\-]+$` and the version must be semver.

| Field | Type | Description |
|---|---|---|
| `name` | string | `vendor.name`, e.g. `crm.example` |
| `version` | string | Semver, e.g. `1.0.0` |
| `vendor` | string | Vendor namespace, e.g. `crm` |
| `title` / `description` | string | Human-readable name/description |
| `author` / `author_url` | string | Module author |
| `license` | string | e.g. `MIT` |
| `category` | string | `migration`, `integration`, `calendar`, `diagram`, `productivity`, … |
| `core_version` | string | Required core, e.g. `>=1.0.0` |
| `dependencies` | array | Module names this module depends on |
| `require_permissions` | array | Permission codes granted/required by the module |
| `events` | array | Subscribed domain events (optional) |
| `api_routes` | string | Path to the API routes file (optional) |
| `web_routes` | string | Path to the web routes file (optional) |
| `mcp_tools` | array | Declared module MCP tools (optional, see §8) |
| `migrations` | string | Directory with SQL migrations (optional) |
| `service_provider` | string | FQCN of the service provider (optional) |
| `assets` | object | Scoped/global assets, see §4 |
| `positions` | object | Position renderers, see §3 |
| `web_hooks` | object | Render-phase web hooks, see §5 |
| `config_defaults` | object | Default config values |

Minimal example (`crm.position-example`):

```json
{
  "name": "crm.position-example",
  "version": "1.0.0",
  "vendor": "crm",
  "title": "Position example",
  "core_version": ">=1.0.0",
  "assets": {
    "css_routes": { "gantt": "web/assets/css/position-example.css" },
    "js_routes":  { "gantt": "web/assets/js/position-example.js" }
  },
  "positions": {
    "gantt.content.after": [
      { "renderer": "Module\\Crm\\PositionExample\\Position\\GanttDemoPanel::render", "priority": 10 }
    ]
  }
}
```

## 3. Events

### 3.1 Catalog

Event names live in one place — `Api\System\Library\Module\ModuleEvents` — so module authors never hard-code a typo.

| Event | Dispatched from | Payload |
|---|---|---|
| `task.created` | `TaskController` | `task_id`, `task_public_id`, `status_code`, `assignee_id`, `actor_id` |
| `task.updated` (also once per task from a bulk update, with `bulk: true`) | `TaskController` | `task_id`, `task_public_id`, `status_code`, `assignee_id`, `actor_id` |
| `task.status_changed` | `TaskController` | `task_id`, `task_public_id`, `old_status`, `new_status`, `assignee_id`, `actor_id` |
| `task.assignee_changed` | `TaskController` | `task_id`, `task_public_id`, `old_assignee_id`, `new_assignee_id`, `status_code`, `actor_id` |
| `task.deleted` | `TaskController` | `task_id`, `task_public_id`, `status_code`, `assignee_id`, `actor_id` |
| `comment.added` | `TaskController` | `comment_public_id`, `task_public_id`, `project_public_id`, `author_id`, `actor_id` |
| `file.uploaded` | `FileController`, `KnowledgeController`, `ChatController` | `file_public_id`, `entity_type`, `entity_public_id`, `uploader_public_id`, `size_bytes`, `actor_id` |
| `tag.created` / `tag.updated` / `tag.deleted` | `TagController` | `tag_public_id`, `code`, `title`, `actor_id` |
| `status.created` / `status.updated` / `status.deleted` | `StatusController` | `status_public_id`, `scope`, `code`, `title`, `actor_id` |
| `priority.created` / `priority.updated` / `priority.deleted` | `PriorityController` | `priority_public_id`, `code`, `title`, `actor_id` |
| `custom_field.created` / `custom_field.updated` / `custom_field.deleted` | `CustomFieldController` | `field_public_id`, `scope`, `code`, `type`, `actor_id` |
| `project.created` / `project.updated` / `project.deleted` | `ProjectController` | `project_id`, `project_public_id`, `actor_id` |
| `user.created` / `user.updated` / `user.deleted` | `UserController` | `user_id`, `user_public_id`, `actor_id` |
| `cycle.created` / `cycle.started` / `cycle.completed` / `cycle.reopened` / `cycle.archived` / `cycle.deleted` | `WorkCycleController` | `cycle_id`, `cycle_public_id`, `title`, `project_public_id`, `status`, `actor_id` |
| `client.created` / `client.updated` / `client.deleted` | `ClientController` | `client_public_id`, `title`, `actor_id` |
| `counterparty.created` / `counterparty.updated` / `counterparty.deleted` | `CounterpartyController` | `counterparty_public_id`, `title`, `actor_id` |
| `contact.created` / `contact.updated` / `contact.deleted` | `ContactController` | `contact_public_id`, `full_name`, `actor_id` |
| `company.created` / `company.updated` / `company.deleted` | `CompanyController` | `company_public_id`, `title`, `actor_id` |
| `organization.created` / `organization.updated` / `organization.deleted` | `OrganizationController` | `organization_public_id`, `title`, `actor_id` |
| `intake.created` / `intake.updated` / `intake.accepted` / `intake.rejected` / `intake.reopened` / `intake.deleted` | `IntakeController` / `IntakeService` | `intake_public_id`, `organization_id`, `title`, `status`, `actor_id`, `task_public_id` (when accepted) |
| `calendar_event.created` / `calendar_event.updated` / `calendar_event.deleted` | `CalendarController` | `calendar_event_public_id`, `organization_id`, `title`, `start_at`, `end_at`, `actor_id` |
| `worklog.created` / `worklog.updated` / `worklog.deleted` | `WorklogController` / `WorklogService` | `worklog_public_id`, `task_public_id`, `user_public_id`, `minutes`, `date`, `actor_id` (financial fields excluded) |
| `knowledge_page.created` / `knowledge_page.updated` / `knowledge_page.published` / `knowledge_page.archived` / `knowledge_page.deleted` | `KnowledgeController` / `KnowledgeService` | `page_public_id`, `space_public_id`, `title`, `status`, `actor_id` (body content excluded) |
| `render.before` / `render.after` | web `Controller::render()` | see §5 |

The payload is passed **by reference** to every handler, so a module can both observe it and (when the event semantics allow) enrich it. Handlers that throw are isolated — a broken module never breaks the core request.

> CRM-record events fire for **every** write path, including MCP: the `crm_crm` mega-tool actions
> `create/update/delete_client|counterparty|contact|company|organization` delegate to these controllers
> instead of calling the services directly, so module hooks and webhook subscriptions behave exactly as
> on the REST/UI path (see `McpController` and `mcp_entity_mutation_parity_unit.php`).

### 3.2 Subscribing

Subscribe in the service provider's `boot(Container $container)` method via the core `HookManager`:

```php
final class ExampleServiceProvider extends AbstractModuleServiceProvider
{
    private ?Container $container = null;

    public function register(Container $container): void
    {
        $this->container = $container;
    }

    public function boot(Container $container): void
    {
        $this->container = $container;

        /** @var HookManager $hooks */
        $hooks = $container->get('hook.manager');

        $hooks->register(
            ModuleEvents::TASK_STATUS_CHANGED,
            function (array &$context): void {
                try {
                    $this->makeService()->onStatusChanged($context);
                } catch (\Throwable $e) {
                    error_log('[example] task.status_changed failed: ' . $e->getMessage());
                }
            },
            100 // priority; higher runs first
        );
    }
}
```

To listen to many events, register one handler per event name (see `SlackHook::EVENTS` in `crm.slack-integration` for a complete example).

Core code dispatches events only through `ModuleHookDispatcher::dispatch($container, ModuleEvents::…, $payload)` — never by calling `HookManager` directly.

## 4. Scoped assets

The `assets` block controls when a module's CSS and JS are loaded. Prefer route-scoped assets so a module's styles/scripts load only where the module is actually used, not globally.

```json
"assets": {
  "css":          [ "web/assets/css/global.css" ],        // every page (use rarely)
  "js":           [ "web/assets/js/global.js" ],           // every page (use rarely)
  "css_routes":   { "gantt": "web/assets/css/panel.css" }, // only the gantt route
  "js_routes":    { "gantt": "web/assets/js/panel.js" }    // only the gantt route
}
```

- `css_routes` / `js_routes` map a **route name** to a file. The route name is the value of `?route=` (e.g. `tasks`, `kanban`, `task-detail`, `gantt`, `calendar`, `counterparties`, `module-wip-limit`).
- Files are resolved relative to the module directory and served as `modules/vendor.name/…`.

## 5. Positions (content slots)

Named slots in core templates where modules contribute ready HTML. A core template calls:

```php
<?= module_position('task.detail.sidebar', ['task_public_id' => $id]) ?>
```

A module declares renderers in `manifest.json`:

```json
"positions": {
  "task.detail.sidebar": [
    { "renderer": "Module\\Crm\\Example\\Position\\Panel::render", "priority": 10 }
  ]
}
```

A renderer is a **public static** method `(array $context): string` that returns HTML:

```php
final class Panel
{
    /** @param array<string, mixed> $context */
    public static function render(array $context): string
    {
        $task = (string)($context['task_public_id'] ?? '');
        return '<div class="crm-card">' . htmlspecialchars($task, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
```

Available core positions:

| Position | Page |
|---|---|
| `task.detail.sidebar` | task detail sidebar |
| `tasks.list.after` | after the task list |
| `kanban.board.after` | after the Kanban board |
| `dashboard.content.after` | after the dashboard widget grid |
| `project.detail.sidebar` | project detail sidebar |
| `profile.content.after` | after the profile grid |
| `gantt.content.after` | after the Gantt grid |
| `calendar.content.after` | after the calendar |
| `counterparties.content.after` | after the counterparties list |

Modules can add new positions in any core template with `module_position('name', $context)`.

## 6. Web hooks (render phase)

The web `Controller::render()` dispatches two hooks around page rendering. Declare handlers in `manifest.json`:

```json
"web_hooks": {
  "render.before": [ { "handler": "Module\\Crm\\Example\\Hooks::beforeRender", "priority": 10 } ],
  "render.after":  [ { "handler": "Module\\Crm\\Example\\Hooks::afterRender",  "priority": 10 } ]
}
```

- `render.before` — context `template`, `data` (by reference; can add/change template variables).
- `render.after` — context `template`, `html` (by reference; can append or replace the full page HTML).

Handlers are stateless public static methods resolved by `Web\System\Module\ModuleExtensionResolver`.

## 7. REST routes, validation and connector primitives

- **API routes** — `api/config/routes.php` returns an array of route definitions. They are auto-prefixed with `/_module/vendor.name/`.
- **Validation (`ModuleRouteValidator`)** — All module routes pass strict fail-closed validation:
  - Allowed HTTP methods: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`.
  - Path traversal (e.g. `..`) is strictly prohibited.
  - Controllers must be located under the `Module\` namespace.
  - Wildcard permissions (e.g. `all`, `*`, `superadmin`) are rejected.
  - Optional `workspace_required: true` ensures the route cannot be invoked without an authorized organization context.
  - Optional `idempotency: true` enforces duplicate protection via `ConnectorIdempotencyStore`.
- **Shared Connector Primitives**:
  - `ConnectorCredentialStore` — provides AES-256-GCM encrypted credential storage per module and organization (`setSecret`, `getSecret`, `hasSecret`, `deleteSecret`).
  - `ConnectorIdempotencyStore` — tracks incoming webhooks and requests (`claim`, `complete`, `fail`) scoped to `(module, org_id, source, key)`.
  - `SignatureService` — raw body HMAC signature verification with replay protection window.
- **Service provider** — extend `Api\System\Library\Module\AbstractModuleServiceProvider` and implement `register()`/`boot()`, plus optional `getPermissions()`, `getMenuItems()`, `getScheduledTasks()`, `getConfig()`.
- **Web routes** — `web/config/routes.php` adds page routes to the web router.

## 8. Module-owned MCP tools

Modules can expose Model Context Protocol (MCP) tools for AI agents by declaring them in `manifest.json`:

```json
"mcp_tools": [
  {
    "name": "fixture_connector_ping",
    "description": "Ping the connector and return workspace diagnostics",
    "handler": "Module\\Crm\\FixtureConnector\\Mcp\\PingTool",
    "permissions": ["settings.view"],
    "mode": "all",
    "workspace_required": true,
    "input_schema": {
      "type": "object",
      "properties": {
        "echo": { "type": "string", "description": "Optional echo text" }
      },
      "additionalProperties": false
    }
  }
]
```

### 8.1 Tool implementation contract

The handler class must implement `Api\System\Library\Module\Mcp\ModuleMcpToolInterface`:

```php
namespace Module\Crm\FixtureConnector\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;

final class PingTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        return [
            'status' => 'ok',
            'organization_id' => $context->organizationId,
            'echo' => $arguments['echo'] ?? 'pong',
        ];
    }
}
```

### 8.2 Profiles and discovery

- **Default profile (`core`)**: Contains only the core 27 mega-tools. Module tools are excluded to prevent token bloat and inadvertent prompt contamination.
- **Discovery profiles**:
  - `all`: Core tools + all tools of all active modules.
  - `modules`: Tools of all active modules only.
  - `module:<vendor.name>`: Tools belonging to a specific module (e.g. `module:crm.fixture-connector`).
- **Safety guarantees**:
  - Strict JSON Schema with `additionalProperties: false`.
  - Module MCP tools fail closed if the module is disabled or uninstalled.
  - Automatic sensitive data redaction: passwords, API keys, tokens, and authorization headers are sanitized in results before transmission.

## 9. Background jobs and queue execution (Zero-Daemon)

TropaTT operates on standard PHP shared hosting without long-running daemons, Redis, or root/CLI access.

### 9.1 Workspace-aware job handler

Background jobs must implement `Api\System\Library\Module\WorkspaceModuleJobInterface`:

```php
namespace Module\Crm\FixtureConnector\Job;

use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Api\System\Library\Module\WorkspaceModuleJobInterface;

final class SyncIntakeJob implements WorkspaceModuleJobInterface
{
    public function handle(array $payload, ModuleExecutionContext $context, Container $container): void
    {
        $orgId = $context->organizationId;
        // All database mutations must be scoped to $orgId
    }
}
```

### 9.2 Dispatching jobs

Dispatch jobs using `ModuleJobDispatcher::dispatch()` with an immutable `ModuleExecutionContext`:

```php
$context = new ModuleExecutionContext(
    moduleName: 'crm.fixture-connector',
    organizationId: $orgId,
    organizationPublicId: $orgPublicId,
    actorPublicId: $actorId,
    source: 'event',
    correlationId: $correlationId
);

ModuleJobDispatcher::dispatch(
    container: $container,
    moduleName: 'crm.fixture-connector',
    handlerClass: SyncIntakeJob::class,
    payload: ['intake_public_id' => $id],
    context: $context
);
```

### 9.3 Shared-hosting execution and observability

- **Batch processing**: Jobs are executed in bounded batches (default: 25 jobs or 15 seconds) during scheduled cron or manual ticks.
- **Manual tick API**: `POST /api/v1/modules/queue/tick` allows processing pending jobs on systems where system cron is unavailable or delayed.
- **Diagnostics API**: `GET /api/v1/modules/diagnostics` provides health status, pending/failed/paused counts, and error summaries per module.
- **Paused legacy jobs**: Older jobs created without an authorized workspace context are marked as `paused_legacy` for manual review, preventing cross-tenant leakage.

## 10. Database migrations and lifecycle

- SQL files in the directory named by `migrations` (e.g. `api/migrations/`); applied/rolled back by the module manager.
- Uninstall keeps your tables (the owner's data must survive), so a re-install runs the migrations again against the schema your module left behind: write them so that re-running is harmless — `CREATE TABLE IF NOT EXISTS`, `DROP ... IF EXISTS`, and guarded `ALTER`s.
- The runner tolerates DDL that is already applied (duplicate table/column/key/constraint, "nothing to drop") and applies a multi-clause `ALTER TABLE` clause by clause, so the parts still missing are added; a statement that fails for any other reason — invalid SQL, or an `ALTER` against a table that does not exist — still fails the migration and is not recorded as applied.
- All module tables must use the prefix `mod_<vendor>_<name>_` and include `organization_id` for multi-tenant data isolation.

## 11. Checklist for a self-contained module

1. `manifest.json` declares `positions`, `web_hooks`, `mcp_tools`, `events`, and scoped `assets` (not global css/js unless required).
2. Event handlers subscribe in `boot()` through `HookManager`, using `ModuleEvents::*` constants, and wrap their logic in `try/catch`.
3. Background jobs implement `WorkspaceModuleJobInterface` and receive `ModuleExecutionContext`.
4. REST routes define explicit HTTP methods, `workspace_required`, and `idempotency` where applicable.
5. MCP tools implement `ModuleMcpToolInterface` with strict JSON schemas (`additionalProperties: false`).
6. Position renderers and web hooks are public static methods returning strings; user-controlled data is escaped with `htmlspecialchars`.
7. CSS/JS are loaded via `css_routes`/`js_routes` keyed by the exact `?route=` value, so they never leak onto unrelated pages.
8. Migrations use `CREATE TABLE IF NOT EXISTS` with `mod_<vendor>_<name>_` prefix and `organization_id`.
9. No core files are modified — everything a module needs is reachable through the manifest, the service provider, and the extension points above.

## 12. Trust model and module security

**Modules are trusted.** Module code executes in the same PHP process as the core, with the same filesystem permissions, database access, and network capabilities. There is **no sandbox, no isolation, no resource limits** enforced on module code at runtime.

For deferred work, module authors must pass an explicit `ModuleExecutionContext` to `ModuleJobDispatcher::dispatch()`. Build the context from an authorized workspace resolver, never from an unchecked request field. A handler must live under its own `Module\\Vendor\\Name\\...` namespace, have a no-argument constructor, and implement `WorkspaceModuleJobInterface`. The worker rechecks the module's active state and the stored workspace identity. Existing rows with no authoritative context pause as `paused_legacy`. The web/CLI cron and root-only admin job runner execute short batches; no daemon, Redis or shell access is needed on shared hosting. Without cron, interactive work remains available and an administrator can run the pending jobs manually.

This is an **explicitly accepted design trade-off** (2026-08-25, C-1). The barriers that do exist are:

1. **Installation gate:** Only the root (admin) user can install modules. Direct URL installs and uploaded ZIP packages require the `MODULE_SIGNING_KEY` environment variable, which must match the server-side signing key — unset or mismatched key → install fails closed. Installs from the official marketplace do not need the key: the archive is verified against the sha256 returned by the marketplace install-request (fetched over TLS from the configured `base_url`), so one-click installs work on a fresh installation.
2. **Code validation:** `ModuleCodeValidator` runs before files from a remote package are written to disk — dangerous constructs (`eval`, `exec`, `shell_exec`, etc.) cause immediate rejection.
3. **Filesystem isolation:** `upload/modules/.htaccess` denies direct web access to module PHP files.
4. **Core stability:** Event handlers that throw are caught and isolated — a broken module cannot crash core requests.

**What this means for module authors:**

- You are writing **trusted code with full access to the installation's data and infrastructure**.
- Treat your module as you would core code: no hardcoded credentials, no unfiltered user input, no calls to external services with plain-text secrets in logs.
- Use the core Config, Log, and DB utilities rather than rolling your own.

**What this means for installers:**

- You are responsible for the modules you install. Review the source code.
- Prefer modules from trusted authors. Check that the module does not exfiltrate data, log secrets, or weaken access controls.
- Module permissions (`manifest.json::require_permissions`) are granted at activation time — review them before activating.

