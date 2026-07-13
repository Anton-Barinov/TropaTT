# AGENTS.md - TropaTT

This file gives coding agents and automated contributors the durable repository instructions for TropaTT.

TropaTT is a self-hosted PHP/MySQL CRM and work platform. It includes CRM records, tasks, projects, Kanban, Gantt, calendar, team chat, automation, REST API, OpenAPI generation, a browser installer, and AI-assisted workflows.

Follow these instructions for every change unless a more specific `AGENTS.md` exists in a subdirectory.

## Project Structure

- `index.php` - root entry point.
- `api/` - PHP API application, controllers, models, services, configuration, scripts, language files.
- `web/` - PHP web application, browser installer, page controllers, templates, assets, page routes.
- `modules/` - optional modules and module examples.
- `.github/` - GitHub workflows, issue templates, and pull request template.
- `README.md`, `SECURITY.md`, `CONTRIBUTING.md`, `SUPPORT.md`, `CHANGELOG.md`, `ROADMAP.md`, `CODE_OF_CONDUCT.md` - public project documentation.

Do not assume private local documentation, screenshots, runtime storage, or local environment files are present in public clones.

## Runtime Assumptions

- PHP 8.1+ is the baseline.
- MySQL is the required database for real installations.
- The public browser installer is `web/install.php`.
- API requests route through `api/index.php`.
- Web pages route through `web/index.php`.
- Public configuration examples should use `api/.env.example`.

Do not introduce SQLite as an installation target unless the project maintainers explicitly request it.

## Safe Commands

Use these commands from the repository root when available:

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
php api/scripts/generate_openapi.php
```

Before reporting that a change is ready, run at least the PHP syntax check for any PHP change.

If you add or change GitHub Actions, also check the workflow YAML manually and keep workflows free of private secrets.

If additional test runners are added later, document the exact command in this file and in the relevant GitHub issue or pull request.

## PHP Rules

- Keep code compatible with PHP 8.1 unless the public requirements are intentionally changed.
- Prefer typed methods, explicit validation, and small service methods over large controller-only changes.
- Use existing repository patterns before adding new abstractions.
- Keep controller code thin when a service or repository already owns the behavior.
- Do not add new Composer dependencies unless they are clearly necessary and documented.
- Do not commit generated vendor directories.
- Avoid changing public routes without updating API/OpenAPI expectations.

## Database Rules

- MySQL is the source of truth.
- Never hard-code database credentials.
- Use parameterized queries or existing repository/query helpers.
- Do not concatenate user input into SQL.
- Preserve install-time behavior for fresh databases.
- Treat migrations and schema changes as high risk: document the expected upgrade path and rollback risk.

## Security Rules

Security-sensitive changes must be reviewed with extra care.

Always check:

- Authentication: unauthenticated endpoints must be intentional.
- RBAC: every API endpoint that reads or writes protected data needs a server-side permission check.
- Object-level authorization: users must not access another team's, project's, chat's, file's, or counterparty's data by guessing IDs.
- CSRF: browser-triggered state-changing actions must be protected by the existing CSRF/session model.
- XSS: never render user-controlled data as trusted HTML unless it is sanitized by an approved path.
- SQL injection: all dynamic database access must use parameters or explicit allowlists.
- File access: uploads/downloads must verify chat, project, team, or record membership before serving content.
- Secrets: tokens, API keys, passwords, session IDs, database credentials, and private paths must not be logged or committed.
- Webhooks: verify authentication/signing/replay rules before accepting external input.
- AI features: do not send secrets or unnecessary personal data to AI providers; respect feature flags and data minimization.

If a change touches auth, permissions, files, chat, webhooks, AI, installer, or admin areas, mention the security checks performed in the pull request.

- **Financial data stripping**: API responses that include financial fields (`cost_rate`, `bill_rate`, `cost_amount`, `bill_amount`) MUST strip them for non-root users. Always add `unset($item['cost_rate'], $item['bill_rate'], $item['cost_amount'], $item['bill_amount'])` before returning earnings/task-summary data to non-root actors. This applies to both REST controllers (`WorklogController`) and MCP tool wrappers (`McpController`). When adding new financial fields to any API response, verify that non-root stripping is in place.

## API and OpenAPI Rules

- Keep `api/config/routes.php` and API controllers consistent.
- If an endpoint is added, removed, or changed, update or regenerate OpenAPI output when the repository contains the required generator/artifact.
- Run `php api/scripts/generate_openapi.php` after API contract changes when possible.
- Do not document an endpoint as public unless it is intentionally public and safe.
- Keep response envelopes and error formats consistent with nearby endpoints.
- Check RBAC and object-level access for every endpoint, not only the web UI path.

## Web UI Rules

- Keep the UI quiet, practical, and consistent with the existing CRM style.
- Avoid ornamental shadows, excessive borders, unnecessary cards, large decorative gradients, and inconsistent table treatments.
- Use existing web assets and page patterns before adding new UI systems.
- If a page uses API data, handle loading, empty, error, and permission states.
- Do not rely on client-side hiding as an authorization mechanism.
- For visual changes, verify the affected page in a browser when possible.

## Installer Rules

- The installer must remain usable on ordinary shared hosting.
- Do not require shell access for basic installation.
- Do not expose credentials, full local paths, or stack traces to users.
- Keep install steps clear: requirements, database connection, admin account, schema setup, and completion.
- Any installer change must preserve fresh-install behavior on MySQL.

## Files and Secrets

Never commit:

- `.env` files with real values.
- `api/.env`, `api/.env.local`, or local database overrides.
- `api/config/database.local.php` or other local-only credentials.
- Runtime storage, logs, backups, uploaded private files, screenshots with private data, or test output.
- Personal Codex skills, local tool state, IDE metadata, or machine-specific paths.

Use placeholders such as `YOUR_DATABASE_NAME` and `YOUR_API_KEY` in docs and examples.

## GitHub and CI Rules

- Keep `.github/workflows/php-ci.yml` fast and reliable.
- Public CI must not depend on private services or local-only files.
- New workflows should avoid secrets unless the secret is clearly documented and optional.
- Prefer small pull requests with focused scope.
- Link changes to existing issues when possible.

## Documentation Rules

Update public documentation when changing:

- Installation behavior.
- Public configuration.
- API endpoints or OpenAPI output.
- Security model, RBAC, CSRF, webhook behavior, or file access.
- AI feature behavior or provider requirements.
- Release process or contributor workflow.

Do not reference private local documentation in public files unless the referenced file is also committed.

## Agent Workflow

1. Read the relevant files before editing.
2. Keep the change narrowly scoped to the request or issue.
3. Preserve existing behavior unless the task explicitly changes it.
4. Apply the security checklist for affected areas.
5. Run the safest available checks.
6. Report what changed, what was verified, and any remaining risks.

When unsure, prefer a smaller change with a clear follow-up issue over a broad rewrite.

## Tags System

Tags are managed at `/web/index.php?route=admin-tags` and are stored in the `tags` table with columns: `id`, `public_id`, `code`, `title`, `color`, `description`, `created_at`.

Task-tag associations are stored in `entity_tags` (`entity_type`, `entity_public_id`, `tag_id`).

API endpoints:
- `GET /api/v1/tags` — list all tags (returns `usage_count` subquery)
- `POST /api/v1/tags` — create (`code`, `title`, `color`, `description`); if `code` is omitted it is auto-generated from `title`
- `PATCH /api/v1/tags/{public_id}` — update
- `DELETE /api/v1/tags/{public_id}` — delete
- `GET /api/v1/tasks/{task_public_id}/tags` — tags for a task
- `POST /api/v1/tasks/{task_public_id}/tags/{tag_public_id}` — attach tag to task
- `DELETE /api/v1/tasks/{task_public_id}/tags/{tag_public_id}` — detach tag from task
- `GET /api/v1/tasks?tag_public_id=x` — filter tasks by tag

The task list/board/detail API responses now include a `tags` field — a JSON array of `{public_id, code, title, color}` objects via `JSON_ARRAYAGG` subquery.

## Searchable Select

Replace plain `<select>` (especially with many options) with a searchable select using `makeSelectSearchable()` in `page-api-bindings.js`. The function:
- Hides the original `<select>` (`display: none`)
- Creates an `<input>` with a `.crm-searchable-dropdown` for filtering
- For single selects: clicking an option sets the value and hides the dropdown
- For multi-selects (`multiple`): selected items display as `.crm-searchable-tag` chips with remove buttons
- Changes are synced to the hidden select via `dispatchEvent(new Event('change', {bubbles: true}))`
- A MutationObserver on the select tracks `childList` changes (for dynamic option population)

Auto-apply via `applySearchableSelects()` which runs on `DOMContentLoaded` and on every DOM mutation. Add new selectors to this function's arrays.

CSS classes: `.crm-searchable-select`, `.crm-searchable-input`, `.crm-searchable-dropdown`, `.crm-searchable-item`, `.crm-searchable-tag`, `.crm-searchable-tags`.

## Workflow Tag Conditions

Workflow rules for `task_status_changed` trigger can now be filtered by `condition_tag_public_id`. The condition is stored in `payload.condition_tag_public_id` in the rule's payload.

The `WorkflowService::matchesTriggerConditions()` checks `$context['task_tags']` (array of tag public_id strings). The TaskController passes tags via `$tagService->listTaskTags()` in `fireWorkflowTrigger()`.

## Known CSS Caveats

- Bootstrap 5.3 uses `box-shadow: inset 0 0 0 9999px var(--bs-table-accent-bg)` for table cell backgrounds, not `background-color`. Inline styles via `element.style.setProperty('background-color', ..., 'important')` must be used to override Bootstrap table hover.
- CSS variables defined on `:root` (in `tokens.css`) are available in all stylesheets loaded after it.
- The `data-user-name` attribute on elements causes the browser to replace the element's `textContent` with the attribute value. Never use `data-user-name` — use `data-uname` on a sibling element instead.

## Known PHP Caveats

- `QueryBuilder::groupBy()` accepts a single string or array. Passing multiple string arguments (e.g. `->groupBy('u.id', 'DATE(w.logged_at)')`) silently ignores all but the first. Always pass an array: `->groupBy(['u.id', 'DATE(w.logged_at)'])`.
- `QueryBuilder::whereRaw()` replaces `?` placeholders with `:pN` named bindings. Named placeholders like `:team_pub` are NOT supported — use `?` only.
- `QueryBuilder::count()` generates separate SQL from `toSql()` and does not use the `columns` property. Subqueries in the SELECT are not executed for count queries.
