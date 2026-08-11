# Changelog

All notable public changes to TropaTT should be documented here.

This project follows a lightweight Keep a Changelog style. Dates are added when a release is actually created.

## Unreleased

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
- Server-side smoke test `upload/api/tests/integration/calendar_event_idempotency_smoke.php`: verifies that a repeated `POST /api/v1/calendar/events` with the same `X-Idempotency-Key` returns the saved response (`meta.idempotency_replayed: true`) and does not create a second event (run on MySQL: `CRM_STORAGE_BASE= DB_CONNECTION=mysql php upload/api/tests/integration/calendar_event_idempotency_smoke.php`).

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
