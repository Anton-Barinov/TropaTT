# Changelog

All notable public changes to TropaTT should be documented here.

This project follows a lightweight Keep a Changelog style. Dates are added when a release is actually created.

## [Unreleased]

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

### Changed

- README positioning expanded to describe TropaTT as CRM, task manager, task tracker, project platform, AI-assisted workspace, and self-hosted work system.
- Update smoke-test marker for verifying update-center delivery without direct demo deploy.
- SHARED_HOSTING_GUIDE.md — added sections on storage protection, trusted proxies, HSTS, nginx configuration.

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
