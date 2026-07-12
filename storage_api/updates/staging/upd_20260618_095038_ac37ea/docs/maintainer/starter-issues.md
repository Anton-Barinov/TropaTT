# Starter Issues

Suggested public backlog for early contributors and reviewers.

## Documentation

- Add screenshots for dashboard, CRM, tasks, Kanban, Gantt, chat, and installer.
- Improve shared hosting installation guide.
- Add installation troubleshooting page.
- Document webhook security model.
- Add release checklist examples for preview and stable releases.

## CI and quality

- Restore and keep GitHub Actions PHP lint workflow green.
- Add fast integration test workflow with MySQL service.
- Add OpenAPI consistency check to CI.
- Add installer smoke test for clean database setup.

## Security

- Review RBAC coverage for all API endpoints.
- Add focused tests for chat file access.
- Add tests for CSRF-protected browser mutations.
- Review AI provider key handling and logging.

## Product hardening

- Improve AI idea analysis resilience for malformed provider JSON.
- Add better progress feedback for long-running AI steps.
- Improve task hierarchy conversion from AI suggestions.
- Review chat polling and notification load on shared hosting.
