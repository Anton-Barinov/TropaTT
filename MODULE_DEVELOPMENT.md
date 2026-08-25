# Module Development Guide

This guide documents how to build a **self-contained module** for TropaTT. Modules extend the core **without modifying it**: a module declares in `manifest.json` and in its own classes where it wants to connect — events, positions, assets — and the core calls it at the right moment.

A module is a directory under `modules/` named `vendor.name` (for example `crm.wip-limit`). The contents of `upload/` are installed into the server document root, so a module lives at `modules/vendor.name/` next to the core `api/` and `web/` applications.

> **Reference implementations** in the main repository:
> - [`crm.position-example`](upload/modules/crm.position-example) — the smallest possible module: one position renderer + route-scoped assets.
> - [`crm.wip-limit`](upload/modules/crm.wip-limit) — full example: service provider, event hooks, scoped assets, position renderer, migrations, API + web routes.
> - [`crm.slack-integration`](upload/modules/crm.slack-integration) — subscribes to the whole event catalog and fans events out to user-defined rules.

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
| `api_routes` | string | Path to the API routes file (optional) |
| `web_routes` | string | Path to the web routes file (optional) |
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
| `task.updated` | `TaskController` | `task_id`, `task_public_id`, `status_code`, `assignee_id`, `actor_id` |
| `task.status_changed` | `TaskController` | `task_id`, `task_public_id`, `old_status`, `new_status`, `assignee_id`, `actor_id` |
| `task.assignee_changed` | `TaskController` | `task_id`, `task_public_id`, `old_assignee_id`, `new_assignee_id`, `status_code`, `actor_id` |
| `task.deleted` | `TaskController` | `task_id`, `task_public_id`, `status_code`, `assignee_id`, `actor_id` |
| `comment.added` | `TaskController` | `comment_public_id`, `task_public_id`, `project_public_id`, `author_id`, `actor_id` |
| `file.uploaded` | `FileController` | `file_public_id`, `entity_type`, `entity_public_id`, `uploader_public_id`, `size_bytes`, `actor_id` |
| `project.created` / `project.updated` / `project.deleted` | `ProjectController` | `project_id`, `project_public_id`, `actor_id` |
| `user.created` / `user.updated` / `user.deleted` | `UserController` | `user_id`, `user_public_id`, `actor_id` |
| `cycle.created` / `cycle.started` / `cycle.completed` / `cycle.reopened` / `cycle.archived` / `cycle.deleted` | `WorkCycleController` | `cycle_id`, `cycle_public_id`, `title`, `project_public_id`, `status`, `actor_id` |
| `render.before` / `render.after` | web `Controller::render()` | see §5 |

The payload is passed **by reference** to every handler, so a module can both observe it and (when the event semantics allow) enrich it. Handlers that throw are isolated — a broken module never breaks the core request.

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

## 7. Routes, service provider and permissions

- **API routes** — `api/config/routes.php` returns an array of route definitions. They are auto-prefixed with `/_module/vendor.name/`.
- **Web routes** — `web/config/routes.php` adds page routes to the web router.
- **Service provider** — extend `Api\System\Library\Module\AbstractModuleServiceProvider` and implement `register()`/`boot()`, plus optional `getPermissions()`, `getMenuItems()`, `getScheduledTasks()`, `getConfig()`.
- **Migrations** — SQL files in the directory named by `migrations`; applied/rolled back by the module manager.

## 8. Checklist for a self-contained module

1. `manifest.json` declares `positions`, `web_hooks`, and scoped `assets` (not global css/js unless required).
2. Event handlers subscribe in `boot()` through `HookManager`, using `ModuleEvents::*` constants, and wrap their logic in `try/catch`.
3. Position renderers and web hooks are public static methods returning strings; user-controlled data is escaped with `htmlspecialchars`.
4. CSS/JS are loaded via `css_routes`/`js_routes` keyed by the exact `?route=` value, so they never leak onto unrelated pages.
5. No core files are modified — everything a module needs is reachable through the manifest, the service provider, and the extension points above.

## 9. Trust model and module security

**Modules are trusted.** Module code executes in the same PHP process as the core, with the same filesystem permissions, database access, and network capabilities. There is **no sandbox, no isolation, no resource limits** enforced on module code at runtime.

This is an **explicitly accepted design trade-off** (2026-08-25, C-1). The barriers that do exist are:

1. **Installation gate:** Only the root (admin) user can install modules. The `MODULE_SIGNING_KEY` environment variable is required for remote package installation and must match the server-side signing key — unset or mismatched key → install fails closed.
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
