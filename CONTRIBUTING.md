# Contributing To TropaTT

Thank you for considering a contribution to TropaTT.

TropaTT is a self-hosted PHP/MySQL work platform that combines CRM, task management, project tracking, Kanban, Gantt, calendar, team chat, automation, REST API, installer flows, and AI-assisted workflows.

## Requirements

- PHP 8.1+
- MySQL
- Web server capable of running PHP
- Writable configuration and storage directories for local installation
- Browser for web UI checks

## Project Layout

- `api/` — API core, controllers, services, repositories, config, migrations, scripts.
- `web/` — web UI, installer, page controllers, templates, CSS, JavaScript modules.
- `modules/` — optional business modules.
- `.github/` — public issue and pull request templates.

Internal docs, local runtime storage, tests, screenshots, and private development artifacts may exist in maintainer worktrees but are not necessarily part of the public install package.

## Local Setup Overview

1. Clone the repository.
2. Create an empty MySQL database.
3. Configure a local domain or PHP-capable web server.
4. Open the site in a browser.
5. Complete the browser installer.
6. Log in with the administrator account created during installation.

## Coding Style

- Prefer clear PHP 8.1-compatible code.
- Follow the existing controller, service, repository, and response patterns.
- Keep API responses compatible with the existing JSON envelope.
- Keep web UI behavior in existing vanilla JavaScript modules unless a change clearly requires otherwise.
- Avoid introducing external dependencies unless there is a strong reason and the maintainer agrees first.
- Keep changes focused. Do not mix unrelated refactors with feature or bug fixes.

## Security Expectations

Every contribution should consider:

- input validation;
- authentication and authorization;
- CSRF requirements for web flows;
- Bearer-token API access rules;
- RBAC and permission checks;
- file upload and download permissions;
- sensitive data in logs and error messages;
- AI provider keys and server-side AI request handling;
- webhooks and external HTTP calls;
- installer behavior and environment configuration.

## Running Checks

Useful checks depend on the area changed. Public clones always contain these:

```bash
php -l path/to/file.php
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=crm DB_USERNAME=root DB_PASSWORD=secret php upload/api/scripts/ci_mysql_smoke.php
php upload/api/scripts/generate_openapi.php
php upload/api/scripts/api_coverage_check.php
```

The public CI workflows run the MySQL smoke test and OpenAPI consistency check without private secrets.

A self-contained local test runner (`upload/api/scripts/test_runner.php`) and its suite (`upload/api/tests/`) exist only in maintainer worktrees and are intentionally not published to GitHub; do not add them as a public CI dependency. If a check you normally run is not included in a public clone, describe what you tested manually.

## Documentation

Update public documentation when behavior changes. For API route changes, also check OpenAPI compatibility and generated documentation expectations.

### Public CI checks

- `php upload/api/scripts/generate_openapi.php` generates the ignored runtime artifact at `upload/api/docs/openapi/openapi.v1.json`.
- `php upload/api/scripts/api_coverage_check.php` compares every normal route/method with the generated OpenAPI paths. Install and internal migration routes are explicitly excluded.
- `php upload/api/scripts/ci_mysql_smoke.php` applies all migrations twice against MySQL and verifies the core tables plus the knowledge-link uniqueness index. Set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in the environment; never commit credentials.

The corresponding workflows are `.github/workflows/openapi-ci.yml` and `.github/workflows/mysql-ci.yml`. The generated OpenAPI JSON remains untracked by design and is regenerated in CI.

## Pull Request Checklist

- [ ] No secrets committed.
- [ ] Input validation checked.
- [ ] Authorization checked.
- [ ] CSRF/auth requirements checked.
- [ ] Migration impact reviewed.
- [ ] API compatibility reviewed.
- [ ] OpenAPI/docs updated if needed.
- [ ] Tests added or updated when practical.
- [ ] Installer impact checked if relevant.
- [ ] No raw sensitive data in logs/errors.
- [ ] AI provider keys are never exposed to the browser.
- [ ] Screenshots added for meaningful UI changes.

## Changes That Require Extra Care

- database schema and migrations;
- installer lock files and `.env` generation;
- authentication, sessions, CSRF, Bearer tokens;
- roles, permissions, and admin impersonation;
- file uploads, storage, quarantine, and downloads;
- webhooks and outbound HTTP;
- AI provider configuration and prompt context;
- generated OpenAPI documentation;
- chat message delivery and attachment access;
- notification delivery and push subscriptions.

## Proposing Features

Open a feature request with:

- problem being solved;
- target users;
- affected pages or API routes;
- security or permission implications;
- migration or installer impact;
- suggested UI/API behavior.

## Reporting Bugs

Use the bug report template and include:

- version or commit;
- deployment type;
- PHP/MySQL/web server versions;
- browser;
- steps to reproduce;
- expected and actual behavior;
- sanitized logs and screenshots if relevant.
