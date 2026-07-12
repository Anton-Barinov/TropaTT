# Codex for OSS Application Notes

TropaTT is a self-hosted PHP/MySQL CRM, task manager, project platform, team chat, automation system, REST API, and AI-assisted workflow platform.

This repository is suitable for AI-assisted open-source maintenance because it has broad but understandable surfaces:

- PHP API controllers, services, repositories, migrations, and installer logic.
- Browser-based UI templates and JavaScript page bindings.
- Role-based permissions, CSRF, rate limits, audit logs, webhooks, files, and chat access control.
- OpenAPI generation tooling and public API documentation.
- AI-assisted workflows that need robust parsing, validation, fallback behavior, and user review.

## Useful Codex tasks

- Diagnose CI failures and propose minimal fixes.
- Review API route authorization and RBAC coverage.
- Keep README, OpenAPI docs, installer docs, and release notes consistent.
- Improve installer compatibility for shared hosting.
- Add focused regression tests around security-sensitive endpoints.
- Audit AI workflow fallbacks when providers return invalid JSON, timeout, or fail.

## Rules for agents

- Do not touch `.env`, local database configs, storage folders, logs, screenshots, or private docs.
- Prefer small changes with clear verification.
- Check PHP syntax for touched PHP files.
- For security-sensitive changes, review RBAC, CSRF, rate limits, file access, and OpenAPI impact.
- Keep public docs truthful. Do not claim tests, workflows, integrations, or deployment paths exist unless they are present in the repository.
