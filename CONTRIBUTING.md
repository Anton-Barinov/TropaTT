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

Useful checks depend on the area changed. When available in your local checkout:

```bash
php -l path/to/file.php
php api/scripts/test_runner.php fast
php api/scripts/test_runner.php unit
php api/scripts/test_runner.php integration
php api/scripts/test_runner.php openapi
```

If a script is not included in the public install package or your checkout, describe what you tested manually.

## Documentation

Update public documentation when behavior changes. For API route changes, also check OpenAPI compatibility and generated documentation expectations.

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
