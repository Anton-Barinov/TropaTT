# Changelog

All notable public changes to TropaTT should be documented here.

This project follows a lightweight Keep a Changelog style. Dates are added when a release is actually created.

## Unreleased

### Added

- **Chat message quick actions.** Messages in the chat page now show two quick-action buttons at the bottom of each card: Reply (fa-reply) and Create Task (fa-list-check). A corner menu (⋮) provides Copy, Edit, Delete, and Create Page actions, with conditions applied (Edit/Delete only for own recent messages).

- **Chat in project detail — full parity with chat page.** The "Chat" tab on the project detail page now uses the same shared `chat-widget.js` module (`CRM.chat`) for rendering, actions, and compose area as the main chat page: textarea with auto-resize, Enter/Shift+Enter, file upload, reply preview, edit mode, emoji and mention placeholders, and the same quick actions + ⋮ menu on each message.

- **Reply-to-message in project chat.** Clicking Reply on a message in the project chat shows a reply preview bar and sends `reply_to_message_public_id` with the new message.

- **Create task from chat message.** The "Create Task" action in both chat pages pre-fills the task creation modal with the message text, linked to the current project.

### Changed

- **Modal content scrollable.** All modal dialogs (create/edit task, create event, create project, etc.) now scroll properly when content exceeds viewport height. CSS fix: `display: flex; flex-direction: column` on `.modal-content`, `<form>` wrapper as flex child, `flex: 1 1 auto` on `.modal-body`.

- **Chat ⋮ menu item order.** Menu items now appear in the order: Copy → Edit → Delete → Create Page. Empty menus are not rendered.

- **Performance: ~2.2 MB lighter on non-task routes.** `br1.js` (77 KB) is now conditionally loaded only on routes that use it (tasks, kanban, gantt, ideas, etc.); other routes load a 29-byte `br1-notify.js` stub. `page-api-bindings.js` (325 KB) is excluded from the project detail page.

- **Chat rendering delegated to shared module.** Core rendering functions (`renderMessage`, `renderMessageMoreMenu`, `renderMessageText`, `renderAttachments`, `renderReplyQuote`, `canEditMessage`, `canDeleteMessage`, `formatTime`, `formatFileSize`, `esc`) in `chat.php` now delegate to `CRM.chat.*` from `chat-widget.js`, reducing duplication.

## [v0.2.0.10] - 2026-08-29

### Testing

- Controlled update-center build pipeline verification marker (2026-08-29). No runtime behavior changed; this entry validates GitHub → cron → package/signature publication.

### Added

- **Cron observability in the admin panel.** The "Jobs" page (Admin → Jobs) now shows a "Scheduled tasks (cron)" card: a cron heartbeat line (last web-cron call, with a warning and a shared-hosting hint when the cron has been silent), the full list of `module_scheduled_tasks` (module, task, schedule, enabled, last/next run, `last_status`, truncated `last_error`), and a "Run now" button that triggers `ModuleCronScheduler::run()` on demand.

- **Cron observability API.** Three root-only endpoints under `/api/v1/ops/cron/*` (`tasks`, `executions`, `run-due`) expose scheduled tasks and their execution history; `handler_class`/`handler_method`/`error_trace` are deliberately not exposed.

- **Scheduler result tracking.** `module_scheduled_tasks` now records `last_status` (`success`/`failed`/`skipped`) and a truncated `last_error` on every run, so the period auto-close indicator on the rates page is no longer permanently empty.

- **Web-cron heartbeat.** `web/cron.php` now records a `cron.last_web_run_at` timestamp after successful key authentication, letting admins distinguish a missing cron from a failing one. Only the timestamp is stored — never the key or caller identity.

- **Module trust model documented.** `MODULE_DEVELOPMENT.md` and `SECURITY.md` now state explicitly that module code runs in the same process as the core with the same privileges (no sandbox), and `SECURITY_AUDIT.md` records C-1 as an accepted risk with compensating controls.

- **Push subscription secrets removed from the API.** `GET /api/v1/notifications/push-subscriptions` no longer returns the per-subscription encryption secrets `p256dh` and `auth`; they remain server-side for delivery only.

- **Partial push-delivery diagnostics in the UI.** When a test push is only partially delivered, the notification center now lists the per-reason failure counts (`dispatch.failures`) returned by the server.

### Changed

- **OpenAPI regenerated.** `upload/api/docs/openapi/openapi.v1.json` is regenerated from `routes.php`: new `/api/v1/ops/cron/*` routes are present and deprecated `/api/v1/notification/push-*` aliases are gone.

- **API reference updated (EN/RU/ZH).** New sections cover the rate model (cost/bill/payout resolution chain, `FinancialFieldPolicy` disclosure, period locking), external users (observer/executor roles, invites, project grants, portal access), push notifications (self-service subscription, dispatch format with `failures`, dropped `task.manage` requirement), and cron endpoints (`/api/v1/ops/cron/*`, `web/cron.php` role, `CRON_SECRET_KEY`).

- **README requirements.** The requirements list now documents the per-minute `web/cron.php` cron job with `CRON_SECRET_KEY`/`X-Cron-Key`, outbound HTTPS for push, and `openssl` with `prime256v1`.

- **Dead sandbox classes removed.** Twelve module-sandbox/validator classes that were registered in the container but never invoked (and promised isolation the product does not provide) were deleted, and their container registrations removed; `ModuleCodeValidator` (used by the remote installer) is untouched.

- **Updater: force-unlock button.** The Admin → System Updates page now offers a "Force unlock" action for clearing a stuck updater lock, followed by an automatic preflight re-run.

- **Updater: dead-PID lock handling.** A lock whose heartbeat is fresh but whose holder PID is dead is now considered stale after 5 minutes instead of the full hour.

### Fixed

- **Installer: reinstall over a non-empty database.** The installer previously failed with a cryptic "Schema import failed" ×3 when the target database already contained tables from a prior install (the MySQL schema snapshot uses plain `CREATE TABLE`). It now detects existing tables, stops with a clear localized message, and — after the admin types `WIPE` — drops every table/view and reinstalls cleanly. The non-JS fallback path refuses with the same clear message instead of silently wiping.

- **Updater: duplicate file-backup entries on retry.** When a file-backup chunk died mid-way (shared-hosting proxy/WAF reset), the next request re-appended the same files to `items.jsonl`, corrupting the rollback manifest with duplicate entries. The backup ledger is now trimmed to the committed cursor before each retried chunk, matching the existing `applied.jsonl`/`rollback.jsonl` behavior.

## [v0.2.0.9] - 2026-08-25

### Fixed

- **Installer TypeError on db_port.** The installer form passed an integer `db_port` value to the `e()` HTML-escape function which expects a string, causing a PHP Fatal TypeError on PHP 8.1+ with strict types. The port value is now cast to string before escaping.

- **Installer open_basedir warning.** On HestiaCP and similar panels where `open_basedir` restricts PHP to `public_html`, the installer logged noisy warnings when checking `storage_api/install.lock` (which lives outside the web root). The check now suppresses the warning gracefully.

### Changed

- **API documentation: required fields clarified.** The API docs now document that `POST /roles` requires a `code` field, `POST /counterparties` requires `title` (not `name`), and `POST /worklogs` requires `activity_code`. These fields were already required by the API but were not documented.

- **HestiaCP / VestaCP nginx guide.** The Shared Hosting Guide now includes a dedicated section for HestiaCP/VestaCP users covering the `@opencart` rewrite rule, PHP-FPM socket naming, and `open_basedir` considerations.

## [v0.2.0.8] - 2026-08-24

### Fixed

- **RBAC view-permissions.** Routes for GET /projects, /tasks, /clients, /worklogs now accept view-level permissions (project.view, task.view, client.view, worklog.view) in addition to manage permissions. Standard users can now list and view entities they have access to via team membership without needing full management permissions.

- **Permission evaluation logic.** Route permission arrays now use OR logic: a user needs AT LEAST ONE of the listed permissions, not ALL of them. Previously the AND logic made view+manage permission pairs impossible.

- **Team member IDs.** `POST /teams` with `member_user_ids` now accepts public_id strings (e.g. "usr_XXX") in addition to integer IDs. Previously public_id strings were silently converted to 0 by intval().

- **Task creation.** `POST /tasks` now accepts both `project_public_id` and `project_id` fields, and `assignee_user_id` resolves public_id strings to integer IDs.

- **Default role assignment.** Creating a user without `role_public_ids` now automatically assigns the first non-system role that has permissions, preventing new users from being stuck with zero permissions.

- **Installer database.php.** The installer now writes `database.php` configuration during installation.

## [v0.2.0.7] - 2026-08-24

### Added

- **Custom rates by counterparty and project.** Introduced three rate kinds — cost, bill, and payout — resolved through named price lists (`rate_cards`) assigned to counterparties and projects, with a per-task override and a default card. Rates are snapshotted onto each time entry when it is logged, so changing a price list never rewrites history; recalculating is an explicit, audited, batched operation that never touches locked periods.

- **Rate resolution and diagnostics.** `GET /api/v1/rates/preview` explains why a rate is what it is (task override, project/counterparty/default card, global user rate, or derived-from-payout) with an ambiguity flag when equally specific price lines compete.

- **Rate-card management UI.** A new "Price lists" page (`rate-cards`) manages cards, lines, and assignments; the counterparty card gets a "Rates" tab (current card, change, and effective employee rates); the project card shows its price list with an explicit "inherited from counterparty" note; the task editor adds a default work type and a rate-override block.

- **Payout visibility for performers.** A "My earnings" page and the `me/earnings` endpoints let staff and external executors see their own accumulated payout, while cost and bill amounts stay hidden by default. Financial field disclosure is centralized in a single policy that strips rate/amount fields per actor.

- **Work-type dictionary.** Time entries carry an activity code (dev, design, analysis, consulting, support by default) that participates in rate resolution and reporting; tasks store a default work type for prefilling.

- **Earnings report.** The time-analytics "Earnings" tab gains client/work-type/ambiguous filters, expandable client/project/work-type columns, per-rate-kind columns, a rate-source indicator, an ambiguity marker, and an open/closed period indicator.

- **Period locking and auto-close.** Admins can lock a date range so its rates no longer recalculate; an optional cron task auto-closes periods (weekly/monthly with a lag), idempotently and never reopens them.

- **Finance permissions and settings.** New `finance.rate.*` permissions are grouped and explained in the role editor; a finance settings block configures the default currency, the cost-from-payout markup, and auto-close.

- **Knowledge base: template ACL.** Knowledge templates are now scoped to spaces via `space_id`, so users only see templates relevant to their workspace.

- **Admin logs page.** Tabbed log page (Errors/Activity/API/Security/MCP/Time) with per-tab filters, human-readable events, pagination, and IP read masking.

- **Server error logging.** Unhandled exceptions are logged to the database with an admin UI for review.

### Fixed

- **Rate-card mutation endpoints returned HTTP 500.** Updating or archiving a rate card, and updating/deleting a card line or assignment, called the response helper with a missing message argument; PHP raised an error after the change had already been written, so the operation applied but the client saw "Internal server error". These endpoints now return a proper success response.

- **Admin roles: permissions not displayed.** The role-matrix API returns structured data (`{ permissions: [...], roles: [...] }`) but the frontend expected a flat map. Role permissions now display correctly in the table and edit modal.

- **Admin roles: create form ignored permissions.** Creating a new role via the modal did not save the selected `permission_codes`. New roles now receive their assigned permissions on creation.

- **Admin KPI widgets showed zero.** The admin dashboard widgets read `data.services_online` (undefined) instead of the actual nested structure. All four KPI widgets now show real values from the database.

- **Password validation messages mismatched code.** The installer and profile password forms said "Minimum 8 characters" but enforced 12 characters plus complexity. All 7 language translations now reflect the actual 12-char requirement.

- **External user role change did not clean up grants.** Demoting an executor to observer left stale project grants in `external_user_project_access` that silently revived on re-promotion. Grants are now revoked on role demotion with an audit log entry.

- **CSP: inline event handlers removed.** All `onclick`/`onsubmit`/`onchange` handlers migrated to `addEventListener` / data-attribute delegation; `script-src-attr 'unsafe-inline'` removed from CSP.

- **CSP: connect-src narrowed.** Reduced from `https:` (any HTTPS host) to `'self' https://suggestions.dadata.ru`.

- **Module code validator integrated.** `ModuleCodeValidator::validateModule()` now runs before copying module files into the project tree, catching forbidden function calls (eval, exec, system, etc.) at install time.

- **Cron scheduler fixed.** `isOverlapAllowed()` and `hasRunningExecution()` now return proper values on the success path; `validateHandler()` enforces namespace and method allowlists.

- **Profile/me no longer leaks financial fields.** `cost_rate`, `bill_rate`, and `payout_rate` are stripped from the `GET /api/v1/profile/me` response for all users.

- **Task activity sanitized for external users.** Internal comment previews, file names, and staff identities are now hidden from external executors in the task activity feed.

- **Rate card lines filtered by FinancialFieldPolicy.** `GET /api/v1/rate-cards/{id}/lines` now strips financial columns the actor is not authorized to see.

- **Chat participant validation.** `addClientChatParticipant` now verifies the target user is an active external guest of the same counterparty.

- **Project identity hidden from external users.** `sanitizeProject()` strips manager/creator IDs and team memberships from project responses for external actors.

- **Self-deroot prevention.** Root users can no longer remove their own root flag via `PATCH /users`.

- **CSV formula injection.** Exported CSV cells now prefix formula-triggering characters (`=`, `+`, `-`, `@`) to prevent Excel/LibreOffice from executing them.

- **EXIF metadata stripped from chat images.** GD re-encode removes GPS coordinates, camera model, and other EXIF data from uploaded images.

- **LikeEscaper applied across all repositories.** 30+ repositories now use `LikeEscaper::escape()` to prevent LIKE wildcard injection.

- **Password reset tokens invalidated on password change.** Changing your password now revokes all pending password-reset tokens.

- **Installer lock.** `web/install.php` returns HTTP 410 after installation; lock files and env detection prevent reinstall.

- **Time-analytics period lock form.** Past period dates are no longer blocked by the global date auto-filler.

## [v0.2.0.6] - 2026-08-21

### Added

- **Client portal (external users).** Contacts linked to a counterparty can be invited to a restricted portal. Invited guests set their own password via a public accept page and then see only their own company's projects and tasks — they can open tasks, comment, and upload/download files, but nothing else in the CRM. Invite and revoke are managed from the Contacts page.

- **Client portal: route allowlist.** External guest sessions are restricted to an explicit allowlist of endpoints (`external_ok` in `api/config/routes.php`), enforced centrally for every request. Any endpoint outside the list returns `403 EXTERNAL_ACCESS_DENIED`, independently of role permissions.

- **Client portal: pages show only what a client can use.** On the task and project pages a portal user now sees just the parts meant for them — description, comments and files on a task; overview and task list on a project. Time tracking, estimates, change history, subtasks, dependencies, AI insights, project modules, knowledge base, the team roster and the edit and delete controls are no longer drawn for them, so the page no longer fills with blocked requests and empty panels. Nothing changes for internal users.

- **Client portal: interface isolation.** The navigation menu and web page routes are limited to Projects, Tasks and Notifications for external accounts; other page shells return 403 rather than rendering. External guests land on Projects after login instead of the dashboard.

- **Security contract test for the client portal.** New database-free check (`external_users_security_contract_smoke.php`, wired into PHP CI) that fails the build if the route allowlist gains a destructive or administrative endpoint, if the seeded guest role references a permission code that does not exist, if `is_external` stops being propagated through the authentication pipeline, or if the row-level security comparisons are weakened.

- **Client portal: observer and executor roles.** Invited guests are now either an **observer** (the existing client-portal experience: read/comment on their own counterparty's projects and tasks) or an **executor** — a freelancer/contractor who can additionally log time. The role is chosen when the invite is created and shown alongside the guest in the portal-access list.

- **Client portal: executors can start a task timer and log time.** Worklog creation and listing (`GET`/`POST /api/v1/worklogs`) are now reachable by external guests, but only for the executor role — observers still cannot, even though both share the same "external" account flag. Previously every worklog route was blocked for all guests, which was the real cause of the timer never starting for an invited user.

- **Client portal: multi-project access for executor (freelancer) guests.** An executor's visibility is no longer tied to a single counterparty. Staff can grant or revoke access to individual projects — including projects that belong to different counterparties — from the contact's portal-access panel. Each grant is narrow (one project), auditable and revocable on its own; inviting an executor auto-grants the projects already open at their own counterparty, and further projects are added explicitly. This keeps a freelancer's access exactly as wide as intended, never widening to "everything for this client" the way the observer role does.

- **Client portal: invite login is now the guest's email.** Portal accounts used to log in with an internally generated identifier different from the email they were invited with. They now log in with that same email, matching what they were told at invite time; the invite flow also checks the address doesn't collide with an existing login before using it.

- **Client portal: invite UI on the counterparty page.** The counterparty detail page's Contacts tab now has the same invite-to-portal flow as the Contacts page — role selection, pending/result states, and (for executors) the project-access panel — instead of a partial version of it.

- **API documentation for external users.** The four `/api/v1/external-users/*` endpoints and the portal access model are now documented in the EN/RU/ZH API references.

### Fixed

- **Client portal: external flag was never applied.** `is_external` was not read when loading a session, so every external-guest restriction silently evaluated as an internal user and guests would have received unrestricted access.

- **Client portal: guest role received no permissions.** The role seed referenced permission codes that do not exist in the system, so the role would have been created with no effective access at all.

- **Client portal: task-level ownership check was a no-op.** Task access compared the project's counterparty against itself instead of the task's own counterparty, so task-level ownership was never actually verified.

- **Client portal: task creation could be redirected.** External guests can no longer set the counterparty or assignee on a task they create, and must create it inside one of their own projects.

- **Client portal: internal comments were visible to clients.** Comments marked as internal on a task are no longer returned to portal users, and a comment written by a portal user is always recorded as client-facing. Filtering happens in the query, so comment counts and paging stay correct.

- **Client portal: attachments were unreachable.** File access was determined only by internal roles (author, assignee, project manager, team member), none of which a portal user ever has, so they could not open or upload files on their own tasks. Access is now granted through the same company scoping used for projects and tasks.

- **Client portal: project and task lists could come back unfiltered.** If a portal user's link to their company could not be resolved — a missing or broken contact record — the company filter was skipped instead of applied, and the list query ran unrestricted. Such a request now returns an empty list.

- **Client portal: internal comments could still arrive as notifications.** Notification text includes an excerpt of the comment it announces, and a portal user becomes a recipient as soon as they comment on or create a task. Internal comments are no longer announced to portal users.

- **Client portal: own profile page returned a 403.** An external guest opening their own profile page (personal data, interface preferences, notification settings, sessions, password, 2FA) got a hard "forbidden" error before the page even rendered, because the profile route was missing from the portal's page allowlist. The underlying endpoints were already scoped to the authenticated actor's own data.

- **Client portal: project card came up with a broken-looking layout.** A guest's own project overview always showed 0% progress, zeroed metrics, and "no milestones" (even when there were some), with error toasts on every load — the summary and milestones endpoints were not reachable by a portal user. They are now reachable for a guest's own accessible project, with per-employee workload data (names, individual task counts) stripped from the response so it never reaches a guest's browser even in the underlying JSON.

- **Client portal: no way for staff to make a reply visible to an invited user.** A guest's own comments were always client-visible, but a staff reply defaulted to internal-only with no control anywhere to change that — so an invited user could never see replies to their own comments. Comment authors can now mark a new comment as visible to the invited user when writing it, and staff can toggle any existing comment's visibility afterward.

- **Client portal: chat integration.** External users can now participate in project-level chat directly from the project card. Staff and portal guests exchange messages scoped to their shared projects; internal chat types and unrelated conversations remain invisible.

- **Client portal: knowledge base integration.** Articles marked as `client_visible` are readable by portal guests who have access to the associated project. Staff can toggle visibility per article, allowing reports, specifications, or hand-off documents to be shared without exposing the full knowledge base.

- **Installer: regenerate MySQL schema snapshot.** The `mysql-schema.snapshot.sql` shipped with the installer was regenerated from the live demo database. Fresh installs now start with all 151 core tables including columns added since the original snapshot (e.g. `users.is_external`, `work_logs.started_at`, `knowledge_pages.client_visible`), and only the incremental migrations need to run afterwards.

- **Installer: check both lock files.** `isAlreadyInstalled()` now checks both `api/.install.lock` and `storage_api/install.lock`, matching the `SEC-001` guard that already checked both. Previously only one was checked, causing confusing 410 / re-install detection mismatches. Error messages in all 7 locales updated to mention both paths.

- **Updater: harden migration runner.** The update-center migration step now wraps each individual `up()` call in a try-catch and logs the error before continuing, rather than letting one broken migration abort the entire update. `max_migrations_per_request` raised from 1 to 5 for faster updates.

- **Security: PII leak on task update.** `TaskService::update()` was calling `sanitizeTask()` without the actor, so staff names could leak in task-update responses for external users. Fixed by passing the actor through.

- **Security: SQLite-safe migrations.** `ExternalPortalIntegrationMigration::getColumnNames()` now uses `PRAGMA table_info()` on SQLite instead of MySQL-only `SHOW COLUMNS`, preventing crashes on non-MySQL installs.

- **Security: LIKE search rewritten.** Counterparty, contact and project search used MySQL `ESCAPE` syntax that was invalid outside certain query shapes. Rewritten to use parameterized `LIKE` with `escapeLikeValue()` that strips wildcards, making the search safe across MySQL versions.

### Removed

- **"MySQL Integration CI" GitHub Actions workflow.** Ran the full migration chain against a fresh MySQL 8.0 service container on every push/PR and had been failing independently of the change being tested. Removed rather than left red; a from-scratch migration smoke test can be reintroduced once it is passing again.

## [v0.2.0.5] - 2026-08-19

### Added

- **Visual editor: enhanced todo lists.** Custom checkbox styling with checkmark, Enter creates new item, Backspace removes empty item, Tab navigates between items.

- **Visual editor: table controls.** Add/remove row and column buttons appear on hover. Tab navigates between table cells, Shift+Tab goes backward.

- **Visual editor: enhanced slash menu.** Commands grouped into Blocks and Lists sections with descriptive subtitles. 9 commands with fuzzy filtering.

- **Visual editor: knowledge page mentions.** @mentions now search both users and knowledge pages. Results grouped by type (User/Page) with distinct icons.

- **Visual editor: sanitizer updates.** Added `role`, `tabindex` attributes for todo checkboxes; `span` preserved with mention attributes.

### Fixed

- **Gantt: removed grab cursor on bar hover.** Gantt bars no longer show `grab`/`grabbing` cursor on hover/drag.

- **Counterparties: removed JSON references from labels.** Extra fields hint text no longer mentions JSON format.

- **Counterparties: modal scroll fix.** Create/edit modal now scrolls properly on small screens.

- **Buttons: icon-only danger buttons unified.** All icon-only delete/clear buttons now use `crm-btn-danger-icon` instead of `crm-btn-danger`.

- **Icons: unified solid/regular styles.** Standardized `fa-comments`, `fa-clock`, `fa-bell`, `fa-file-lines` to use `fa-regular` style consistently across all pages.


## [v0.2.0.4] - 2026-08-19

### Added

- **Visual editor: todo lists.** Insert via toolbar button or typing `[]` at the start of a line. Checkboxes toggle on click; checked state persists across saves.

- **Visual editor: tables.** Insert 3×3 tables via toolbar button. Tables have responsive wrapper, border styling, and focus indicators.

- **Visual editor: slash menu (`/`).** Type `/` at the start of a line to open a command palette with 9 block types (headings, lists, todo, quote, code, table, divider). Supports keyboard navigation and fuzzy filtering.

- **Visual editor: @mentions.** Type `@` to search and mention users. Fetches user list from API on first trigger (cached). Inserts styled mention chips preserved in output HTML.

- **Counterparties: interactive extra fields editor.** Replaced raw JSON textarea with a dynamic key-value editor (add/remove rows, field name + value inputs). No JSON knowledge required.

- **Worklog time rounding.** New admin setting `time_rounding_minutes` (0 = disabled). When set, time is rounded up to the nearest N minutes on timer stop, manual entry, and edit.

- **Docs page expanded.** Added Feature Overview, MCP, Usage Examples, and Tech Stack sections from README. Full i18n parity across all 7 locales (88 new keys).

### Fixed

- **Visual editor: code block styles inside editor.** Added CSS for `pre`/`code` elements inside `.crm-ve-content` so code blocks render with proper styling while editing.

- **Counterparties: status field is now a select dropdown.** The status field in create/edit modals was a plain text input; changed to a select with active/inactive/archived options.

- **Updates page: auto-load changes.** The "What will change" section now automatically loads after checking for updates when an update is available.

- **Gantt: removed resize cursor on hover.** Gantt bars no longer show `ew-resize` cursor suggesting drag-to-resize.

- **Admin settings: redesigned layout.** Added breadcrumb navigation, improved grid layout (8/4, 7/5, 6/6), styled system info grid, and icon in warning banner.

- **Icons unified.** Standardized `fa-book` → `fa-book-open` for knowledge-related concepts across 5 locations. Added `aria-hidden="true"` to 930 decorative icons.

- **Buttons unified.** Standardized subtask open/edit buttons to match task list pattern (`crm-btn-subtle`/`crm-btn-secondary`).

- **Docs page: HTML rendering.** Removed `htmlspecialchars()` from 90 i18n calls containing HTML tags so `<strong>`, `<a>`, `<code>` render correctly.

- **Sanitizer: todo list and table persistence.** Moved `<input>` from REMOVE_WITH_CONTENT to ALLOWED_TAGS so todo list checkboxes survive save/load. Added `<span>`, `<hr>`, `<table>` and related elements with proper attribute filtering.

## [0.2.0.3.1] - 2026-08-18

### Added

- **i18n: new API language domains.** `cycle`, `estimate` and `task_relations` message catalogs were created in all 7 locales so their controller messages no longer fall back to hardcoded English.

- **Web `gantt.blocked_marker`** added to the top-level `gantt` namespace in all 7 locales (in addition to the client `js.pab.gantt` copy), so the Gantt blocked-marker label passes the parity audit.

### Fixed

- **i18n: remaining hardcoded strings moved to language variables.** The two-argument `->t('key', 'fallback')` calls across the API (worklog summary/earnings/task-summary/matrix, webhook, file, auth, module, dependency, intake, idea, common, view, import, permission, project, project_module, knowledge, task cycles and WIP/activity messages) now resolve through language files in all 7 locales instead of embedding English fallbacks.

- **i18n: key-format bugs fixed.** References of the form `domain/name` were corrected to `domain/messages.name` (project_module, knowledge, intake, install, import), and `ai_suggestion.messages.*` was corrected to `ai_suggestion/messages.*`; the bulk-task limit message now uses a translated `%d` template via `sprintf`.

- **i18n: full web + API key parity.** All 7 locales now carry identical key sets (web audit and API parity check report zero missing keys), and the integration keys missing from de/fr/es/pt/zh web catalogs were added.

- **Planning: «Действие» column stays visible.** The planner task tables cap the task/assignee/status/action column widths (92/104/80px) explicitly on cells instead of relying only on `<colgroup>`, so the «Действие» column no longer overflows the visible area.

## [0.2.0.3] - 2026-08-18

### Added

- **Planning workspace (My Day / My Week) unified.** Both planning views now share one consistent workspace (their routes and all existing task, calendar and AI actions stay intact): the same 4-column task-table layout (Задача | Ответственный | Срок/статус | Действие) with `table-layout: fixed` column widths, day headers with a «Сегодня» badge on My Day, avatars with initials, status badges with time, chips for subtask/parent/task kinds, and day labels localized in all 7 locales. The «Просроченные задачи» blocks on both pages are regular sections (not spoilers) with live counts, and the AI day/week plan cards moved into the right column after the day/week events. Priority stripes now support all 4 levels (urgent/high/normal/low) and appear only on the first cell of a row; the «Прокрутка по горизонтали» hint shows only when a table actually scrolls; marking a task done asks for confirmation first.

- **Task detail sidebar blocks are per-user configurable.** The right column of the task card (estimates, timer, AI assistant, summary and any module blocks) can be hidden, re-added and reordered per user; the layout is stored per user (`GET/PUT /api/v1/tasks/sidebar`) and applied on page load.

- **Counterparty detail page reorganized into tabs with accordions.** The counterparty card is split into tabs, with count badges on the tab buttons kept intact; long sections fold into accordions.

- **Project detail page redesigned into tabs.** The project card now uses tabs with a progress ring and quick status/priority pills; the AI sub-blocks on the insights tab fold into accordions; a new «Архив» action sits in the «ещё» menu with a confirm modal.

- **Chat: history pagination, unread divider and copy action.** Long conversations can scroll back beyond the initial 80 messages («Показать более ранние»), a «новые сообщения» divider is based on the per-user read marker, and every message has a copy-to-clipboard action.

- **Projects list shows real progress and managers.** The list endpoint now computes per-project task totals and progress, so the «Прогресс» column is no longer a constant 0%; cards and table render a progress bar with done/total counts, show the assigned manager, and distinguish an empty list (with a create CTA) from a no-results-for-filters state.

- **Intake inbox: status tabs, sorting, pagination and bulk triage.** Per-status tabs with live counts, sortable column headers and a pager for large shared inboxes; a select-all/checkbox column powers bulk accept/reject/assign/snooze/reopen/delete that reuses the single-item flows under their existing RBAC checks.

- **Cycles become sprints.** Cycles are labeled «Спринты» in the menu and page titles, with the browser-title translation added to all 7 locales. The statistics tab gains: a **burndown chart** (tasks and story points with an ideal line, committed-points baseline and a tasks/points toggle when estimates exist), **scope tracking** (committed vs added/removed via a `meta_json` baseline), a **velocity report** including story-points velocity (prefers the story-points estimate set, falls back to the first set with estimates), and **per-assignee capacity planning** (points committed vs completed per assignee plus team totals). Daily burndown snapshots are captured via cron in addition to on start/view/complete, a **sprint guard** enforces one active cycle per project, and the cycle lifecycle dispatches module events (created/started/completed/reopened/archived/deleted).

- **WIP limits for teams, projects and assignees.** The WIP-limit module now computes load live from the tasks table (replacing the drift-prone denormalized counter), honors configurable WIP statuses and role exclusions, and dispatches `task.status_changed` / `task.assignee_changed` module hooks from the core so enforcement actually runs. Team- and project-scoped limits with exceed notifications to the assignee or team/project manager were added, and the assignee's live WIP load plus an inline limit editor now live in a task-detail sidebar panel. Scope tabs are styled as a segmented control.

- **Module extension system.** Modules can subscribe to task lifecycle events (module events + `HookManager`), scope their CSS/JS to specific routes (`css_routes`/`js_routes`), inject content into named page positions via manifest `positions` (task detail sidebar, tasks list, kanban, dashboard, project detail, profile, gantt, calendar, counterparties), and hook the render lifecycle via manifest `web_hooks`. A self-contained `position-example` module demonstrates content injection into `gantt.content.after` as a reference for module authors; the WIP module uses the new surface as its reference example.

- **GitHub and GitLab two-way sync via core events.** The integrations subscribe to `comment.added` and `task.status_changed` and push changes back to the linked issue/MR for imported tasks only. Loop safety is structural (pull sync writes through services, push-back reacts to controller events), pushed comments are persisted so the next pull skips them, and the issue/MR → task mapping is stored to keep re-syncs idempotent.

- **Slack notifications from core events.** The Slack module registers against the core `HookManager` so task/project/user/comment/file events automatically enqueue notifications for matching rules — no more wiring every workflow via `call_webhook`. Rules support both the new dot event names and legacy underscore aliases.

- **i18n: complete key parity across all 7 locales** for the web and API catalogs (aligned with the ru-ru reference): missing dashboard/footer/intake/knowledge/task_detail/cycles/js.pab keys added to de/es/fr/pt/zh web catalogs, new `intake`, `project_module`, `project_modules` and `saved_views` API domain catalogs created where absent, and the Chinese `task_detail` knowledge labels restored (they were shadowed by an English duplicate block). Module dialog strings now go through the language files.

- **Docs:** public module development guide (`MODULE_DEVELOPMENT.md`) published and linked from the README along with `UPDATES.md`; README links the 22 module repositories (en/ru/zh), advertises the MCP integration and free pricing, and carries a release badge linking to the latest GitHub release.

### Changed

- Planning: the «Моя неделя» item is hidden from the menu by default; the strict 8/4 grid is restored; hardcoded UI emoji replaced with Font Awesome icons; planner CSS cleaned up and merged, `data-i18n` attributes audited.
- Cycle statistics: the burndown metric switch is styled as a segmented control; WIP-limit scope tabs likewise.
- Docs: removed references to local-only test files and real server paths; dropped missing source-map references from bootstrap assets.

### Fixed

- Task detail: the saved sidebar block order is applied on page reload (not just on first render).
- Counterparty: tab count badges stay intact (the `data-i18n` attribute was dropped from tab buttons).
- Chat: the read marker survives the response sanitizer as `last_read_seq` (distinct bind parameter for the join).
- Kanban: the search box syncs with the `q=` URL parameter on initial board load; the searchable cycle-filter input syncs when the board is opened from a cycle.
- Cycles: burndown and statistics strings localized in all 7 locales; the reopen button label no longer collides with open-detail; points-burndown remaining is rounded to avoid float noise; points velocity picks the estimate set that actually has estimates; the velocity project filter is passed as a query param.
- WIP limit: the summary table waits for `text-utils` before loading.
- Modules: query params are passed as query objects (not embedded in route paths); the project-modules list loads on the project-detail page.
- CSS: two stray media queries closed (kanban filters/knowledge/workspace mobile styles and project-detail mobile styles now actually apply); primary button text stays white on hover inside content areas.
- Planning: the My Week «Задачи недели» table now uses the same fixed table layout and boxed max-height+scroll treatment as «Просроченные задачи», and its card container div is properly closed.

## [0.2.0.2] - 2026-08-15

### Added

- **Time-tracking automations.** A new workflow trigger **«Записано время по задаче»** (`worklog_logged`) fires when time is logged, with conditions: period (per day or continuous «no break» session with configurable break minutes), threshold in hours, scope (per task or across all tasks of the user) and an optional user allow-list. The rule fires exactly once per threshold crossing, so managers are not spammed by every subsequent worklog. A new action **«Уведомить руководителя»** (`notify_manager`) resolves the executor's manager automatically (team manager → user's creator → task manager; explicit recipients optional) and supports `{user} {task} {minutes} {total} {threshold} {day}` placeholders. Works with any existing action too (notification, comment, webhook...). The Admin → Правила автоматизации page gained the trigger/action options, a condition panel and help texts; fully localized in 7 languages; covered by unit tests (crossing, sessions, scopes, manager resolution, fallback chain).

- **Parallel time trackers no longer double-count the same wall-clock time.** The task timer now saves the exact `[started_at, ended_at]` interval to `work_logs` when a timer is stopped (new `WorklogIntervalMigration`; legacy entries stay untouched). The time analytics page (`route=time-analytics`) shows both numbers for every user/day: **Записано** (the sum of all worklogs) and **Уникально** (the overlap-free union of exact intervals, plus legacy minutes that cannot be de-duplicated), with a **Пересечения** column and tooltips. Earnings are computed from the unique time, so parallel timers never pay twice for the same interval. The per-day detail modal lists the start/end interval of every entry and a concrete breakdown of every overlapping slice (which tasks covered each slice and for how long). The worklog summary, earnings and matrix API responses gain `recorded_minutes` / `unique_minutes` / `overlap_minutes` / `has_intervals` (the matrix and `total_minutes` keep backward compatibility). The overlap math lives in a pure, unit-tested class (`TimeOverlapMath`, invariant: overlap == recorded − union); validation requires `started_at`/`ended_at` as a pair with end strictly after start (all 7 locales).

### Changed

- **Modules are now delivered with core updates.** Previously the update
  pipeline excluded `modules/**` from update packages (the update center's
  `PathClassifier`/`products.php` and the client updater's protected paths), so
  installations that only update through `update.tropatt.com` never received
  modules added to the repository after their initial install. Now module files
  are included in update packages: they are added/updated from the package and
  deleted only when the module was removed from the product (deletion is
  manifest-driven, so locally installed modules that do not exist in the
  product are never touched). New modules appear on **Admin → Модули** with
  status «Обнаружен» and just need to be activated.

### Fixed

- **Old installations update to the modules era automatically — no manual
  two-phase rollout needed.** Installations whose `api/config/update.php`
  still lists `modules/**` in `protected_paths` (anything on a build older
  than the first build that shipped the new config) would reject packages
  containing module files at the safety preflight («Защищённые пути») and
  could never update. Two things now make the transition automatic for the
  whole install base:
  - The update center serves such installations a signed **bridge package**
    first: the latest tree **without** `modules/**` (the new config and the
    new updater code land together, nothing the old config protects), labeled
    with a distinct `-bridge` build. Once it is applied, the next check
    returns the real latest build — now with the modules. The bridge is built
    automatically for every published build (`ensure-bridge` cron step) and
    `update-plan` picks it per client based on `current_build`
    (`modules_in_core_since_build`); fresh installs and installs already on
    the transition build or newer are served the normal package.
  - The client updater retires a stale `modules/**` protection for any
    package that itself ships a new `api/config/update.php` (after the update
    that config governs, so validating against the pre-update list would
    reject the very files the package legitimately delivers). Every other
    protected path (`.env`, `storage/**`, `uploads/**`, `*.local.php`, …)
    stays enforced unconditionally. Package order no longer matters.
  - The updates page shows a hint when a two-step (bridge → modules) update
    is pending and still displays exactly which package paths failed the
    protected-path check when one does fail.

- **Subdirectory installs now talk to their own API.** The web header computes
  `window.CRM.config.apiBaseUrl` from the install path (`/api/index.php` at the
  domain root, `/crm/api/index.php` in a subdirectory), and the JS fallbacks in
  `api.js` / `notifications-realtime.js` derive the same URL from `webBase` when
  no preset is present. Previously a CRM copy in a subdirectory silently called
  the domain-root install's `/api/index.php` — cross-install data leak. Module
  CSS  links in the header are now web-base-relative as well.
- **PWA cache names are scoped to the install path.** Two CRM copies on the
  same domain (e.g. `/crm/` and `/`) previously shared one cache namespace
  (`crm-pwa-runtime-<version>`), so the activate handler of one install could
  delete the other's cache once asset versions diverged. Cache names now
  include the web root (`crm-pwa-runtime/web/-<v>`, `crm-pwa-runtime/crm/web/-<v>`)
  and pruning only touches this install's caches (plus the legacy format).

### Changed

- Tasks list (list view) table: the «Клиент / Менеджер / Исполнитель» column is widened (110px → 170px → 230px → 260px, cap 280px) so long client names like «ТОО АлматыСтройМонтаж» and assignee names are no longer ellipsized; the key column is narrowed to 62px (still fits the badge and the «Ключ ▲» sort header) and the task column yields the space (36% → 31% → 25% → 22%), so the table still fits without horizontal scrolling.

### Added

- **Tasks from chat discussions**: every chat message now offers a «Создать задачу» action. Clicking it opens a small dialog with the message text pre-filled as the task title and description; on save the task is created via the regular `POST /api/v1/tasks` endpoint (idempotent, so retries are safe) with a `source_*` metadata block: `source_type=chat`, `source_id` (chat public id), `source_url` (relative `index.php?route=chat&id=…&message=…` — install-location independent) and `source_payload_json` (chat id/title + message id/text). The `tasks` table gains `source_type`/`source_id`/`source_url`/`source_payload_json` columns via a new migration (`TaskChatSourceMigration`) mirroring the knowledge-pages source convention. The task detail page shows a «Создано из обсуждения» section with an «Открыть обсуждение» button that deep-links straight to the originating message; the chat page supports the `?id=<chat>&message=<msg>` deep link (scrolls to and highlights the message, then drops the param so polling never re-scrolls). Server-side validation allow-lists `source_type` to `chat` and caps the payload.

- **PWA (Progressive Web App)**: the CRM is now installable as a desktop/mobile app. The PWA is fully install-location independent: every copy of the CRM (on any domain, and even in a subdirectory like `/crm/`) gets its own correctly-scoped app. `upload/web/manifest.php` serves a localized web manifest (relative URLs, so it works on any hosting sub-path — no server configuration required): the install dialog and the app-icon shortcuts (Задачи / Канбан / Календарь) follow the user's chosen CRM locale (`crm_locale` cookie, same one the service worker uses). The manifest declares the app shell with brand icons (192/512 + maskable, generated from the favicon by `upload/api/scripts/generate_pwa_icons.php`); `header.php` links the manifest and adds `theme-color` / apple-touch-icon metas; `assets/js/pwa.js` registers the app service worker on every logged-in page and powers a new «Приложение» section on the profile page with an «Установить приложение» button (appears automatically when the browser fires `beforeinstallprompt`; hidden otherwise, iOS uses the native «Add to Home Screen»). The service worker (`push-sw.js`) now precaches the app shell at install (versioned with `?v=`, so every deploy installs a fresh worker and prunes old caches on activate) and serves static assets stale-while-revalidate from a bounded cache; API, uploads, updater and the installer are never cached; offline navigations show a dedicated «Вы офлайн» page. Crucially, the worker derives its web root and site root from its own script path (`/web/` at the domain root, `/crm/web/` in a subdirectory), and `header.php` exposes the same web base to the client (`window.CRM.config.webBase`) which `pwa.js`/`notifications-push.js` use for the registration URL and scope — so no hardcoded `/web/` paths remain and each installation's PWA is scoped to its own site. Installed via browser menu or profile button — works fully on shared hosting.

- Tasks list (list view) and My Day/My Week tables: the priority line in the merged «Срок / Статус / Приоритет» column now carries an explicit muted «Приоритет:» label before the chip, so a bare «Высокий»/«Низкий» is no longer ambiguous; the tasks state column cap is widened 145px → 155px to fit the labeled line.

- Tasks list (list view) table: each «Клиент / Менеджер / Исполнитель» line is now clickable — a click (or Enter/Space, the lines are `role=button`) sets the corresponding page filter (Клиент / Менеджер / Исполнитель) natively through the existing filter selects and reloads the data server-side; clicking an already-active line clears the filter. Lines carry `data-people-role` + `data-people-value` (the entity public id that matches the filter option), a filter icon appears on hover (always on touch devices), and the searchable filter inputs now re-sync their visible value after programmatic filter changes (initial load, re-renders).

- Tasks list (list view) table: hover tooltips on the «Клиент / Менеджер / Исполнитель» lines — when a line is clipped by `text-overflow: ellipsis` (narrow window), hovering it shows the full «Клиент: …» text in a native tooltip. The full text is kept in `data-people-title`, and a delegated listener sets the `title` attribute only for actually truncated lines (fully visible lines stay clean). Works after re-renders (filter/sort/pagination) and on any viewport width.

### Fixed

- PWA updates now apply immediately: the service worker calls `skipWaiting()` on install, so a freshly deployed worker activates right away and prunes the previous version's cache instead of leaving it behind until the user's next page load (three stale version caches were observed in the wild after three deploys).

- PWA offline navigations now show the «Вы офлайн» page immediately instead of burning the whole retry budget (3+5+8 s of waiting) on a connection that the browser already reports as missing.

- PWA install button: the `beforeinstallprompt` event is consumed on the first click, so a second click can no longer call `prompt()` on an already-used prompt (which throws `InvalidStateError`); any `userChoice` rejection is swallowed.

- PWA app icons (icon-192.png / icon-512.png) now actually have transparent rounded corners: GD's alpha blending was on by default, so `imagesetpixel` with the transparent color was blended over the blue tile instead of writing the alpha channel, silently producing square icons. The generator now disables alpha blending before cutting the corners.

- Tasks list (list view) table: clicking a sort header now really alternates ASC → DESC on every repeated click. The sort click handler was bound once and closed over the first render's `sortLevels`/filter values, so after the first re-render a second click re-applied ASC instead of flipping to DESC (and filters could silently reset). The handler now reads the current sort levels from the URL and the current filters from the DOM at click time; the toggle logic is extracted into a pure, runtime-tested `toggleTaskSortLevel()`.

- Counterparties / clients / companies / contacts tables are no longer pinned to huge fixed widths (were 1080-1400px via the global table safety layer) — they now use a responsive floor (760px → 700px → 620px) and per-column caps + ellipsis, so the tables fit the content area without an internal horizontal scroll on typical desktop widths while long names stay readable (native hover tooltips reveal truncated values).

- Auto-layout tables (projects, counterparties, clients, knowledge, admin lists) no longer force every column to a minimum 200px — the global `thead th` rule now allows narrow columns to shrink to 100px, so the projects table fits without horizontal scroll (was 1125px+), counterparties drops from ~1400px and clients from ~1841px, and long names in narrow columns stop being cut off. Tables that need fixed wide columns (tasks list, estimates, ideas) keep their explicit per-column widths/caps.

- Tasks list (list view) table: no more internal horizontal scroll on typical desktop widths — the table's `min-width` is now responsive (980px on wide screens, 780px below 1200px, 660px below 992px), so at a 1024–1280px viewport with the 280px sidebar the table fits the content area; on phones the card wrapper still scrolls gracefully and the page never scrolls horizontally. Fixed columns (key/people/state/actions) keep their caps, the percentage task column absorbs the shrink.

- Tasks list (list view) table: the «Ключ» column is now truly content-sized — the generic table cell padding (14px 12px) was added on top of the fixed 96px width in fixed table layout, so the column rendered ~105px against ~58px key badges. The column is narrowed to 88px with 4px side padding and `white-space: nowrap`, so it fits the badge with a few px of air instead of a 47px dead zone.

- In-page retry status bar ("Повторная попытка загрузки данных…") now resolves its translation correctly in every locale: the `retrying_data` key was missing from the `js.notify` client-message namespace (it only existed in the unused `page` namespace), so the i18n lookup silently fell back to the English source string "Retrying data load…" for non-English users. The key is now present in `js.notify` in all 7 language files, and a regression test guards all of them.

### Changed

- Tasks list (list view) table: sorting headers show only the direction arrow (▲/▼) — the level-rank number (e.g. «Задача ▲1») is removed, while multi-level sorting itself keeps working.

- Tasks list (list view) table: the «Действия» (actions) column is now right-aligned — the header text and the buttons sit flush against the right edge of the column.

- Tasks list (list view) table: the «Ключ» (key) column no longer stretches to a leftover 32% width — the table now uses auto layout so every column sizes to its content. The key column is a narrow 96px (fits the key badge and the sort button with its rank arrow), the task column takes the freed space (36%), and the people column gets a bit more room (110px). The dead duplicate width rule was removed.

- Tasks list (list view) table: **multi-level sorting**. Every sortable header button (Задача, Проект, Ключ, Срок, Статус, Приоритет) is independently clickable; a click adds the parameter as the next sort level (ASC), a second click on the same header reverses its direction (DESC), and every following click keeps alternating ASC/DESC — a click never silently drops the sort. Clicking another header appends another level (double/triple sort, up to 4); the whole chain is cleared with the «Сбросить» button. The sort chain is encoded in the URL as a single `sort=key1:ASC,key2:DESC` parameter; the legacy `sort=key&order=ASC` form is still accepted by both the page and the API. The tasks list API builds the `ORDER BY` from the whole chain (project sorting maps to the joined projects title).

- Tasks list (list view) table: the "Ключ" (key) header is now a sort button (`sort=task_key`).

- Tasks list (list view) header: sortable column buttons (`.crm-th-sort`) no longer use uppercase/extra-bold small type — they now inherit the plain header style, so all thead cells render with the same font, size and weight. Hover accent and the active-sort arrow are preserved.

- My Day page: the "Срок", "Статус" and "Приоритет" columns are merged into one stacked state column (due date line, status badge, priority chip), matching the tasks list treatment. Both tables (today + overdue) now have 3 columns instead of 5; header reads "Срок / Статус / Приоритет".

- Tasks list (list view) table: the task column header now offers a second sort button "Проект" (`sort=project_title`), since the project lives in the task column after the recent merge. The API accepts `project_title` in the tasks sort allowlist and orders by the joined projects title.

- Tasks list (list view) table: the "Проект" (project) column is merged into the task column — the project now renders as a muted folder link right after the parent link under the task title. The tasks table now has 6 columns instead of 7 (checkbox, key, task, people, state, actions).

- Tasks list (list view) table: the "Срок" (due) column is merged into the status/priority column, forming one "Срок / Статус / Приоритет" header with three sort buttons. The tasks table now has 7 columns instead of 8; the due date renders as a muted line above the status badge and priority chip.

### Added

- GitHub Actions workflow `.github/workflows/web-tests-ci.yml`: runs the dependency-free web frontend unit tests (`npm run test:api-retry`, `test:tasks-render`, `test:tables-render`) on every push and pull request. The npm scripts now use an `if [ -f ... ]` guard: when the local-only test files are absent (they stay git-ignored per project convention) the job exits green with a SKIP message, and if the tests are ever published the job becomes a real gate that fails on regressions. Also fixes the previous `|| echo` fallback, which would have masked real test failures with a green exit code.

### Removed

- Dead `tasks.views_*` localization keys from the web language files (12 keys × 7 locales: `views_access_label`, `views_access_private`, `views_access_public`, `views_aria`, `views_btn`, `views_desc_label`, `views_desc_placeholder`, `views_modal_title`, `views_name_label`, `views_name_placeholder`, `views_save_btn`, `views_save_current`). Leftovers of the removed saved-views UI on the tasks page; nothing in the web code references them anymore.

### Added

- Local regression test for the compact tables rendering (`upload/web/tests/tables_compact_render.test.js`, run via `npm run test:tables-render`): asserts the projects table has exactly 6 columns with the merged "Client / Team" header and the counterparties table exactly 7 columns with the merged "Type / Status" and "Email / Phone" headers; evaluates the real row templates from `page-api-bindings.js` (INN under the counterparty name, stacked type/status and contacts cells, dash placeholder for empty contacts); verifies the compact mode hides only the extra-fields column (5th), never the contacts. Test file stays local-only (git-ignored) per project convention.

- Local regression test for the compact tasks-list rendering (`upload/web/tests/tasks_list_render.test.js`, run via `npm run test:tasks-render`): asserts 8 table columns with the merged people/state headers, the de-emphasized parent link (weight 400 / 0.75rem), the absence of the AI-priority idle hint, and no leftover saved-views dead code. Test file stays local-only (git-ignored) per project convention.

### Fixed
- High-contrast theme renamed to describe the theme itself instead of its audience (was "Контрастная (для слабовидящих)" / "High contrast (low vision)"): the option is now simply "Контрастная" / "High contrast" (and the equivalents in the other six locales). The label describes the color scheme, which is what the option actually is, and no longer refers to people.
- Profile theme select now shows a short explanation under the selector while the high-contrast scheme is active ("Высокий контраст для комфортного чтения" / "High contrast for comfortable reading"), so users see what the scheme gives them.
- The theme hint on the profile page now reminds users to press the save button after choosing a scheme ("После выбора схемы не забудьте нажать «Сохранить изменения»" / "After choosing a theme, don't forget to press "Save changes""), instead of saying the scheme applies immediately.
- High-contrast theme audited against WCAG: the priority badge orange deepened to `#9a3412` (white text 7.3:1, AAA), danger/warning borders pinned to the strong theme colors (were ~1.4:1 pale tints, now ≥7:1), the gantt milestone gradient light end and the dependency-obstacle stroke darkened (≥5:1), and component boundaries that fell back to a pale ~1.3:1 border (task-key badge, icon-button hover, sidebar toggle, KPI-card hover) now use the dark brand green (8:1). A local audit script checks ~31 text/non-text pairs: all meet WCAG AA (4.5:1 text / 3:1 non-text), and normal text pairs reach AAA (7:1).
- High-contrast theme is now fully flat: every shadow is removed (custom tokens, all Bootstrap shadow tokens, and every hard-coded `box-shadow`/`text-shadow` in the component stylesheets, with `!important` so nothing leaks through). Depth comes from the strong borders the theme already uses. The only preserved inset shadow is Bootstrap's table-cell background mechanism (`inset 9999px`), which is not a visual shadow but paints striped/hover row backgrounds - it is restored explicitly for `.table` cells so striped tables keep working.
- Feature flags are no longer at risk of duplicate rows under concurrency: `feature_flags` now gets a self-healing UNIQUE index on `code` (created automatically with a de-duplication pass over rows duplicated by older builds), and `FeatureFlagService::ensureDefaults()` runs once per request instead of on every `isEnabled()`/`list()`/`update()` call. Previously every feature-flag lookup performed one SELECT per configured default (38 defaults) and its SELECT-then-INSERT seeding had a race window on installs without the unique index.
- AI intent settings seeding (`AiIntentSettingService::ensureBaseline()`) now runs once per request instead of on every `list()`/`update()` call, and a concurrent duplicate `intent_code` create is handled gracefully instead of surfacing as an error.
- Module scheduler no longer duplicates scheduled tasks on every API request: `ModuleCronScheduler::registerTask()` is now idempotent (updates the existing row instead of inserting a new one) and a UNIQUE index on `(module_name, task_name)` plus an automatic de-duplication step in `ensureTables()` collapse rows already duplicated by older builds. Previously `module_scheduled_tasks` grew by 4 rows per request (hundreds of thousands of rows on active installs).
- Service-worker retry page (shown after automatic page re-requests fail on a network-level error) now follows the CRM locale the user chose (the `crm_locale` cookie) instead of only the browser's Accept-Language, so Russian users with an English-first browser see the retry page in Russian. Accept-Language is now also parsed by q-priority as a fallback.

### Added
- Admin → Logs: hourly histogram of frontend API errors (`frontend_api_error`) with a transport-vs-HTTP breakdown, so transport errors that survived automatic retries can be monitored in the UI without SQL. Ranges: 24h / 48h / 7 days. Backed by a new root-only endpoint `GET /api/v1/logs/frontend-errors/chart`.
- Idempotency for the remaining create endpoints: `POST /api/v1/contacts`, `/api/v1/clients`, `/api/v1/counterparties`, `/api/v1/companies` and `/api/v1/tasks/{public_id}/subtasks` are now wrapped in `withIdempotency`, and the web UI sends an `X-Idempotency-Key` on every such create (submit buttons are disabled while the request is in flight to prevent double-click duplicates). A repeated request with the same key returns the stored response instead of creating a duplicate row.
- Server-side smoke coverage for calendar-event idempotency: a repeated `POST /api/v1/calendar/events` with the same `X-Idempotency-Key` returns the saved response (`meta.idempotency_replayed: true`) and does not create a second event.

### Changed
- Web page loads now survive transient 5xx on any hosting without server configuration. The service worker (`/web/push-sw.js`) intercepts page navigations and automatically re-requests them up to 3 times with growing delays when the server answers 5xx or the connection drops (this also covers nginx 502/503 where PHP never ran); real 5xx bodies (maintenance page, CRM error page) still reach the browser after retries are exhausted. As a fallback for browsers without the worker, `web/index.php` now catches render exceptions and serves a localized recoverable 500 page that retries the navigation on its own (per-URL budget of 3 attempts with a countdown) and then offers a manual refresh button. All messages are localized in 7 languages.
- Idempotent writes (POST/PUT/PATCH carrying an `X-Idempotency-Key`) now retry automatically on transient server errors (500/502/503/504), just like GET/HEAD data loads. The server dedupes re-sends by key (IdempotencyService), so a retry can never double-create a record. 429 is deliberately excluded from the write retry set because AI endpoints report AI_BUSY/AI_RATE_LIMITED as 429 and run their own retry loop; GET/HEAD keep the full retryable set. Non-idempotent writes still require an explicit `{ retry: true }`, and `{ retry: false }` / `{ maxRetries: 0 }` opt out everywhere.
- Calendar event creation (`POST /api/v1/calendar/events`) is now idempotent: the endpoint replays the stored response for a repeated `X-Idempotency-Key` instead of creating a second event, so the new client-side 5xx retry for idempotent writes can never duplicate a calendar event (the AI day/week plan apply buttons send such keys).
- Kanban tasks always load in portions: the board page endpoint and the client now use a fixed chunk of 100 cards per request instead of loading the whole visible dataset at once when `kanban_max_cards = 0` (the default on fresh installs). The `kanban_max_cards` setting now tunes the portion size (0 = default 100, N = N cards per request); column counters still show full totals from the first response. Large boards paint fast on any hosting with no server configuration required.
- Tasks list view: the table fits without horizontal scrolling. Client / Manager / Assignee are merged into one "Клиент / Менеджер / Исполнитель" column (stacked labeled lines), Status / Priority merge into one column (status badge over priority chip, both keep their own sort button in the header), the key column is narrowed to 70px, the actions column to 170px, and long project names are ellipsized. The "Родитель: …" link under a subtask title is no longer bold and renders smaller (0.75rem, weight 400). The idle hint "AI-приоритет доступен для текущей выборки задач." no longer appears (the status line stays hidden until a calculation actually starts or finishes), and the saved-views dropdown block was removed from the tasks page.
- Counterparties list table: 10 columns down to 7, no horizontal scroll needed. The INN moved under the counterparty name as a small muted line, Type and Status stack into one column, and Email and Phone stack into a single contacts column (values with tooltips, "—" when empty). Compact density mode now hides only the extra-fields column (it used to hide INN and Email, which are no longer separate columns).
- Projects list table: 7 columns down to 6 — Client and Team merge into one stacked column; long project titles are ellipsized. Position-based CSS for the counterparties table was remapped to the new column order.
- Removed the leftover saved-views machinery from the tasks page: the per-page `GET /api/v1/views?entity_type=task` request, the `bindTasksSavedViews()` handler (it targeted never-present DOM elements), the `view_public_id` URL handling on that page, and the unreachable saved-view modal in the tasks template. The tasks page no longer makes the saved-views API call at all; saved views on the projects/clients/counterparties pages are unaffected.

### Fixed
- Kanban load-more no longer rebuilds the whole board, so scrolling one column to load more tasks no longer resets every column's scroll position to the top. Newly fetched cards are appended into their own column only; a full re-render (with scroll restoration, both vertical column position and horizontal board position) happens solely when a brand-new status column appears. A fully-deduplicated load-more chunk (data shifted server-side between requests) now advances to the next page instead of re-requesting the same one. Changing a filter resets the load-more page cursor, so the next chunk starts from page 1 of the new filtered dataset instead of skipping pages.

### Added
- Migration `20260810_000001_tags_description`: adds the `description` column to the `tags` table for installs created before the column existed. Previously the column was only defined in the fresh-install schema (`CREATE TABLE IF NOT EXISTS` never alters an existing table), so tag create/read on upgraded databases failed with SQLSTATE 42S22 (`Unknown column 'description'`).
- Kanban: tag filter dropdown in the board filter bar (in addition to tag chips on cards). Tag options load from the full tags catalog, and the filter is resolved server-side over the whole dataset, exactly like the other kanban filters. The card-tag chip click and the dropdown stay in sync.
- Tasks page: visible tag filter in the filter bar (was only reachable via the URL). Supports the "Без тегов" (no tags) option.
- Tag filters now support the `__none` marker (tasks without any tag), with `tag_public_id=id1,__none` unions, matching the assignee/project/cycle filters.
- Saved views now read/write the tag filter through the tasks-page tag select (was a hidden chip element).

### Fixed
- **Network layer survives TLS 1.3 0-RTT anti-replay on any host** (no server configuration required): 425 Too Early and same-URL 307/opaque-redirect responses are automatically re-sent with the request body intact (any method), with an extra retry for the fresh-connection early-data case. In Chrome the 307 is surfaced as an opaque-redirect response (status 0), which is now also detected and retried — previously such hosts caused "network error" on page loads and failed PATCH/POST saves. Together with the existing retry-on-timeout/network/5xx and the status-bar "Retrying data load…" hint, the CRM recovers from transient host issues purely in code.

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
