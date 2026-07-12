# Release Checklist

Use this checklist before publishing a TropaTT release.

## Before tagging

- Confirm `README.md`, `CHANGELOG.md`, `ROADMAP.md`, `SECURITY.md`, `SUPPORT.md`, and `CONTRIBUTING.md` reflect the current release.
- Run PHP syntax checks for `api/`, `web/`, `modules/`, and root entry files.
- Verify the browser installer on a clean PHP/MySQL environment.
- Check that `api/.env`, local database overrides, storage folders, logs, backups, screenshots, and private docs are not tracked.
- Review database migrations and installer seed data.
- Confirm OpenAPI generation still matches public API routes.
- Smoke-test login, dashboard, ideas, tasks, Kanban, Gantt, calendar, chat, notifications, admin, and profile.
- Review AI workflows with safe fallback behavior when the configured provider returns invalid JSON or is unavailable.

## Release notes

- State whether the release is stable, preview, or pre-release.
- Mention installation requirements: PHP 8.1+, MySQL-compatible database, HTTPS recommended.
- List known limitations and areas where feedback is requested.
- Include demo link and test credentials only for public demo environments.

## After publishing

- Verify the GitHub release page, tag, source archive, README badges, and Actions tab.
- Open or update follow-up issues for known hardening work.
- Check that the public demo is on the same release or clearly marked as newer than the release.
