# Changelog

All notable public changes to TropaTT should be documented here.

This project follows a lightweight Keep a Changelog style. Dates are added when a release is actually created.

## Unreleased

### Added
- Admin → Logs: hourly histogram of frontend API errors (`frontend_api_error`) with a transport-vs-HTTP breakdown, so transport errors that survived automatic retries can be monitored in the UI without SQL. Ranges: 24h / 48h / 7 days. Backed by a new root-only endpoint `GET /api/v1/logs/frontend-errors/chart`.

### Fixed
- Kanban load-more no longer rebuilds the whole board, so scrolling one column to load more tasks no longer resets every column's scroll position to the top. Newly fetched cards are appended into their own column only; a full re-render (with scroll restoration, both vertical column position and horizontal board position) happens solely when a brand-new status column appears. A fully-deduplicated load-more chunk (data shifted server-side between requests) now advances to the next page instead of re-requesting the same one.

### Added
- Migration `20260810_000001_tags_description`: adds the `description` column to the `tags` table for installs created before the column existed. Previously the column was only defined in the fresh-install schema (`CREATE TABLE IF NOT EXISTS` never alters an existing table), so tag create/read on upgraded databases failed with SQLSTATE 42S22 (`Unknown column 'description'`).
- Kanban: tag filter dropdown in the board filter bar (in addition to tag chips on cards). Tag options load from the full tags catalog, and the filter is resolved server-side over the whole dataset, exactly like the other kanban filters. The card-tag chip click and the dropdown stay in sync.
- Tasks page: visible tag filter in the filter bar (was only reachable via the URL). Supports the "Без тегов" (no tags) option.
- Tag filters now support the `__none` marker (tasks without any tag), with `tag_public_id=id1,__none` unions, matching the assignee/project/cycle filters.
- Saved views now read/write the tag filter through the tasks-page tag select (was a hidden chip element).

### Fixed
- **Network layer survives TLS 1.3 0-RTT anti-replay on any host** (no server configuration required): 425 Too Early and same-URL 307/opaque-redirect responses are automatically re-sent with the request body intact (any method), with an extra retry for the fresh-connection early-data case. In Chrome the 307 is surfaced as an opaque-redirect response (status 0), which is now also detected and retried — previously such hosts caused "network error" on page loads and failed PATCH/POST saves. Together with the existing retry-on-timeout/network/5xx and the status-bar "Retrying data load…" hint, the CRM recovers from transient host issues purely in code.

## [Unreleased]

### Added

- **Due-date filters follow the user's timezone**: the web UI resolves the `due` presets (`today`/`week`/`overdue`) in the browser's local timezone and sends explicit `due_at_from`/`due_at_to` bounds (plus `exclude_statuses` for overdue), so the filter matches what the user sees regardless of the server's timezone. `due_at_from`/`due_at_to` now also accept full timestamps, not just dates.
- **Server-side task filters cover the full dataset**: the task list API now resolves comma-separated multi-select filters (assignees, managers, projects, cycles, tags), `__none`-style "no value" markers (task without assignee/manager/project/cycle), due-date presets (`due=overdue|today|week`) and `exclude_statuses` directly in SQL. The kanban board and the tasks page now send every filter to the API, so filters apply to ALL matching tasks (not just the currently loaded cards/pages) and the kanban column counters are exact for the active filter set.
- Kanban filter dropdowns (assignee, manager, project, cycle, tag) are now populated from the full user/project/cycle/tag catalogs instead of the loaded cards, so every filter value stays available regardless of pagination or loading state.
- KPI quick views on the tasks page (Active / Overdue / SLA week) are resolved server-side instead of filtering just the current page of 50 tasks.

### Added

- API resilience: all GET/HEAD data loads now **automatically retry once** on transient failures — network errors, timeouts, 429/5xx responses and unexpected HTML answers — so intermittent data-load errors recover without a manual page reload. Other methods (POST/PATCH/DELETE, e.g. AI with idempotency keys) opt in with `retry: true`; the default can be disabled per call with `retry: false`. The retry respects the server `Retry-After` header for rate limits and is tuned via `maxRetries` / `retryDelayMs`.
- API resilience: **425 Too Early** (TLS 1.3 0-RTT anti-replay, RFC 8470) is now treated as retryable, so installs on hosts with `ssl_early_data` anti-replay recover automatically instead of surfacing an error.
- Telemetry: network drops, timeouts and unexpected HTML answers that survive their automatic retries are now recorded as `frontend_api_error` events (Admin → Logs → security log) — previously only HTTP error responses were captured, so the classic "network error" page-load failures were invisible in the data.
- Status bar: while the automatic retry is in progress the page shows "Повторная попытка загрузки данных…" instead of a dead loading state.
- Kanban: global setting **`kanban_max_cards`** (Admin → System settings). `0` (default) loads every task the user can see at once — the board is no longer capped at 100 cards; a positive value renders that many cards first and then **auto-loads more on scroll** until everything is loaded.
- Kanban: column counters are always the **real totals** — the API now returns full per-status counts (`status_counts` in `pages/kanban` and in the tasks list meta via `with_status_counts=1`), so a chip can show "2 847" from the very first render while the cards themselves load in chunks. The result summary line ("Shown X of Y…") was removed — the board just works.
- Kanban: chunk loading is now triggered by **scrolling close to the bottom of a column** (~5 cards away) with a capture-phase scroll listener, so it keeps firing after every re-render; if the whole board still fits on screen it auto-fills the next chunks until a column becomes scrollable. This fixes the load-more that previously stopped after the first chunk.
- Gantt: global setting **`gantt_max_tasks`** (Admin → System settings). `0` (default) shows every accessible task — the chart is no longer capped at 200; a positive value shows only the latest N tasks.
- Task list API: `limit=0` now means "unlimited" (offset mode), used by the kanban board endpoint and the Gantt chart.
- Settings: changing `kanban_max_cards` / `gantt_max_tasks` now invalidates the cached board/chart payloads, so the new limit applies on the very next page load.

### Fixed

- Dashboard "Active Cycles" widget: rendered the literal text `false` whenever at least one active cycle existed — a misplaced closing quote turned the widget's HTML string concatenation into a relational comparison (`html += ... > ...`), so the whole widget body collapsed to `"false"`. The cycle cards, progress bars and task counters now render correctly.
- API file cache: the per-call TTL is now respected (capped by the global `api_file_cache_ttl`). Previously the global value silently replaced every per-call TTL, so the kanban board and dashboard payloads (intended to refresh every 30–45 s) stayed stale for the whole global TTL — e.g. 1 hour on installations that raised `api_file_cache_ttl`.
- Task mutations (create/update/delete/move/bulk, module add/remove) now also invalidate the page cache, so the kanban board and its column counters reflect changes immediately instead of waiting for the cache to expire.
- API client: TLS 1.3 0-RTT anti-replay (RFC 8470) is now tolerated **for every request method and without any server configuration**. The client fetches with `redirect: 'manual'` so the browser can no longer auto-follow the anti-replay `307` and drop PATCH/POST bodies (which used to surface as `422 VALIDATION_ERROR`), and the request loop re-sends any `425 Too Early` / `307` response itself — body intact — because such a response means the request never reached the application, so the retry can never duplicate work.
- API client: default request timeout raised from 15 s to 30 s (AI calls keep 300 s) so slow shared hosts — cold PHP-FPM pools, on-demand workers — answer instead of surfacing a "network error"; the automatic retry then recovers genuine timeouts without a page reload.

## [0.2.0.1] - 2026-08-10

### Added

- Color themes: new **sepia** (warm parchment) theme as a pure token block in `themes.css` — added a theme without touching any component styles, proving the token architecture. Registered in the header allowlist, `api.js` THEMES, the profile theme select and all 7 locale files.
- Kanban: horizontal scroll navigation — always-visible scrollbar, floating left/right scroll buttons, and a keyboard fallback (Left/Right arrows scroll the board by one column; typing contexts and open modals/offcanvas are ignored).
- Kanban: scroll buttons fade-in animation when hovering the board edge (reduced-motion safe).
- Theme architecture: all colors unified into CSS variables — zero hardcoded colors in base CSS/JS/PHP templates; themes only swap token sets, so adding a new theme means defining variables only.

### Changed

- Profile theme select order: light, sepia, dark, contrast.
- Footer is no longer sticky — it flows naturally at the page bottom, freeing vertical space.
- `.crm-metric-tile` gets explicit padding and aligns with the system card visual style; my-day/my-week tiles use `box-shadow: none !important` and `min-width: 105px`.
- Status/error toast tokens are now per-theme (sepia loading popup uses warm brown instead of fixed green).
- Switches: enabled toggles no longer show the hover/focus glow ring (`box-shadow: none`).
- All `var(--token, #hex)` fallbacks removed — no raw hex colors remain outside token definitions.
- Visual-editor tokens (`--crm-ve-*`) moved to `:root`/theme scope so rendered content (comments, descriptions, ideas) keeps spoiler styling outside the editor.

### Fixed

- Dark theme: remaining light artifacts and unreadable dark-on-dark text eliminated — modal/offcanvas/drawer/popover surfaces, buttons, tables, calendar, gantt, chat, knowledge, dashboard builder, badges and bootstrap `box-shadow` glows now follow theme tokens.
- Contrast theme: no blue accents remain — links/checks/avatars/gantt/calendar/buttons use brand dark green, plain chips and labels stay readable, button surfaces are white with black borders (WCAG AA).
- Sepia theme: gantt uses warm brown tints instead of blue accents.
- Visual editor spoiler: `<details class="crm-ve-spoiler">` in saved content (comments, descriptions, ideas) now renders styled and collapsible in every theme.
- Projects page: missing project status translations for Russian locale (and all 7 locales) — statuses no longer fall back to English.
- pages.css: restored the missing `@media` wrapper around `.crm-automation-page .crm-section-head` mobile rules (orphan closing brace since an earlier commit; braces balanced again).

## [0.2.0] - 2026-08-09

### Added

- Security: Environment capabilities endpoint (`/api/v1/health/deep` → `environment` section) with server detection, storage location check, extension availability, and installation warnings.
- Security: Storage protection — uploaded files stored as `.bin` on disk, dangerous extensions (`.php`, `.phtml`, `.phar`, etc.) rejected with `FILE_TYPE_FORBIDDEN`, upload quarantine with forced `Content-Disposition: attachment`.
- Security: SSRF protection — DNS-based URL validation with private IP blocking, IP pinning via `CURLOPT_RESOLVE` for webhooks and module downloads.
- Security: MCP permission registry — 548 tools registered with explicit permissions, fail-closed for unregistered tools.
- Security: REST route authorization — 52 self-service routes explicitly annotated (`authz_note`), contract test for mutating route authorization.
- Security: Trusted proxy support — configurable `CRM_TRUSTED_PROXIES`, `clientIp()` vs `remoteAddr()` separation, CIDR-based matching.
- Security: Conditional HSTS — configurable via env, `includeSubDomains` off by default, not sent over HTTP.
- Security: CSP — `style-src` without `'unsafe-inline'`, nonce-based for `<style>` blocks, `style-src-attr 'unsafe-inline'` for legacy compatibility.
- Security: install/status returns 404 after installation (prevents product fingerprinting).
- Security: PathGuard — handles directory entries in update packages (fixes update mechanism).
- Security: Composite 2FA rate limiting key (IP + login token) prevents cross-user DoS behind shared proxies.
- Security: Storage self-check in health endpoint — verifies that upload storage is not web-accessible.
- Public repository maintenance files: security policy, contribution guide, roadmap, support guide, issue templates, and pull request template.
- Time tracking analytics dashboard with per-user time and earnings reports.
- Worklog summary API (`GET /api/v1/worklogs/summary`, `earnings`, `matrix`, `detail` endpoints).
- Per-user hourly rates (`cost_rate`, `bill_rate`) set by admin in user profile.
- Admin tags page with CRUD, description, and usage count.
- Tags in task list/board API response (via `JSON_ARRAYAGG` subquery).
- Kanban tag filter and tag chips on cards.
- Clickable tag chips → filtered task list.
- Workflow trigger conditions by task tag.
- Searchable selects (`makeSelectSearchable()`) for all user, project, client, and tag selects.
- Reset button on time analytics filter toolbars.
- Day-of-week labels on analytics date columns.
- Weekend row highlighting on all analytics tables.
- Column hover with intersection cell emphasis.
- Editable team names → open edit modal.
- Notification badge in sidebar nav (collapsed mode).
- Clients: «Create task» / «Create project» action buttons on the client detail page — they open the global create modals with the current client pre-filled.
- Clients: hybrid contact role field — a select with free-text entry; roles not present in the preset list are stored and displayed as plain text.
- Projects: admin statuses page now shows two independent tables — task statuses and project statuses (split by scope).
- Projects: the project workflow edit form loads statuses from the admin dictionary, so «Планирование»/«Активный» and any custom project statuses are available as transitions.
- Projects: completing a project that still has open tasks is blocked with a choice modal — «Close all tasks» (paged bulk close) or «Move to another project» (paged per-task reassignment); `open_task_count` is returned in the `PROJECT_HAS_OPEN_TASKS` error meta.
- UI: user-selectable color themes — dark and high-contrast (low-vision) schemes in addition to the default light theme; per-user choice on the profile page, applied instantly without flash and persisted across devices (profile preferences).

### Changed

- Security: dashboard widget permissions are now server-authoritative — the client widget loader trusts the permission-filtered `active` list from `GET /api/v1/dashboard/widgets` instead of duplicating permission checks in JavaScript (single source of truth; catalog and active widgets are filtered fail-closed server-side).
- Security: `system_health` dashboard widget is now gated behind `settings.manage` (the `/api/v1/health/status` endpoint stays auth-only for liveness checks).
- Security: active sessions listing (`GET /api/v1/security/sessions`) no longer requires `logs.view` — it is a self-service endpoint scoped to the actor's own sessions.
- Security: worklog visibility model unified across widgets — `AnalyticsService::visibleUserIds()` now includes the user hierarchy (`descendantIds()`), matching `WorklogService::getVisibleUserIds()`.
- README positioning expanded to describe TropaTT as CRM, task manager, task tracker, project platform, AI-assisted workspace, and self-hosted work system.
- Update smoke-test marker for verifying update-center delivery without direct demo deploy.
- SHARED_HOSTING_GUIDE.md — added sections on storage protection, trusted proxies, HSTS, nginx configuration.
- Updates now run as a resumable step machine so they work on any shared/virtual hosting: file backup, file apply, DB dump, migrations, DB restore and file rollback are split into many short HTTP requests (configurable `steps` budgets in `api/config/update.php`, ~20s per request by default) instead of one long request that shared hosts cut at the proxy/PHP-FPM timeout. The lock uses a heartbeat across steps, apply/rollback tokens support multi-step continuation with a sliding expiry, package download streams to disk, and the admin-updates page drives the step loop with progress display.
- Installer: the post-install «update available» notice now queries the update-center `update-plan` (build comparison from `current_build=0`) instead of a semver comparison that could never fire because every build ships the same VERSION — fresh installs now see the hint to update when a newer build is published.

### Fixed

- Security: IDOR in the batch milestones endpoint (`GET /api/v1/milestones?project_public_ids=...`) — `MilestoneService::listByProjectIds()` now performs object-level authorization via `ProjectService::get()` for every project and drops inaccessible ones (fail-closed); the batch size is capped at 100 to prevent authorization-query amplification.
- Security: worklog matrix response (`GET /api/v1/worklogs/matrix`) leaked org-wide team/project names — the `teams`/`projects` option lists are now scoped to the actor's accessible teams and projects (same visibility rule as the analytics breakdown).
- Security: `WorklogRepository::listTeams()` leaked the full team list when a non-root user had no accessible teams — an empty access list now means "no teams" (fail-closed) instead of falling through to "all teams"; `listTeams()` takes an explicit `$actorIsRoot` flag like `listProjects()`.
- Database: schema creation on SQLite used MySQL-only `UNIQUE KEY` table constraints, which failed with `near "KEY": syntax error` when the updater applied a pending `InitialSchemaMigration` on a SQLite install. Constraints now use the portable `CONSTRAINT ... UNIQUE (...)` form (verified on MySQL 9.6 and SQLite).
- Updates: added an end-to-end step-machine test with a real pending migration (`InitialSchemaMigration`) — asserts a real DB snapshot is taken, the migration is applied across step requests, and rollback restores the database to its pre-migration state.
- i18n: the admin-updates (Updates) page is now fully translated into German, Spanish, French, Brazilian Portuguese and Chinese — all 7 locales are complete for the updates section.
- Clients: the «Compact view» toggle active state is readable again (solid background + white text instead of green-on-green); added missing `clients.normal_view`/`compact_view` keys to the Russian locale.
- i18n: added missing EN keys for contact roles and project statuses (i18n key parity test).

## [0.1.0] - First Public Preview

### Added

- Public preview of TropaTT.
- Self-hosted PHP/MySQL CRM and work platform.
- Task management, project tracking, Kanban, Gantt, calendar, and team chat.
- Workflow automation and REST API.
- Generated OpenAPI documentation.
- AI-assisted workflows including idea analysis, task decomposition, daily and weekly planning, summaries, risk review, and semantic search.
- Browser installer for PHP/MySQL hosting.
- Integration test coverage and custom test runner in the maintainer development workflow.
- Early feedback welcome.
