# Roadmap

This roadmap describes public preview priorities. It does not promise dates.

Status labels:

- **Planned** — intended direction.
- **In progress** — active work.
- **Needs feedback** — requires real user or contributor input.
- **Done** — already available in the public preview.

## Public Preview Hardening

- **In progress:** improve installation, first-run reliability, and public documentation.
- **Needs feedback:** collect reports from shared hosting, VPS, local host, and office/home server deployments.
- **Done:** release tags (`v0.2.0.x`) are published as GitHub Releases with upgrade notes, and the update server serves the latest `main` build to installed copies.

## Installer And Deployment

- **Done:** browser-based installer for PHP/MySQL deployments.
- **Done:** installation troubleshooting, shared-hosting, and upgrade references are published (`INSTALL_TROUBLESHOOTING.md`, `SHARED_HOSTING_GUIDE.md`, `UPDATES.md`).
- **Planned:** deployment checklist for shared hosting and VPS.

## Security And Permissions

- **Done:** RBAC, CSRF-aware web flows, Bearer API access, file quarantine concepts, and admin/security surfaces.
- **Done:** public security policy with reporting, response process and responsible-disclosure rules (`SECURITY.md`).
- **Planned:** more public security hardening notes and permission review checklists.

## API And OpenAPI Compatibility

- **Done:** REST API and generated OpenAPI documentation.
- **Done:** route definitions, generated OpenAPI output and frontend API usage are checked in CI (`openapi-ci.yml` plus `api_coverage_check.php`).
- **Planned:** clearer compatibility policy for public API changes.

## Tests And CI

- **Done:** custom test runner and integration test workflow in maintainer environment.
- **Done:** four public checks run on every push and pull request — PHP syntax on 8.1/8.2, client-portal security contract, OpenAPI consistency, web frontend tests. The live release gate itself runs on the maintainer machine because the test suite is intentionally not published.
- **Needs feedback:** which checks contributors can realistically run on shared hosting and local setups.

## Documentation

- **Done:** trilingual README (English / Русский / 中文), support guide, contribution guide, security policy, changelog and roadmap are published.
- **Done:** installation troubleshooting, shared-hosting guide and self-update reference are published; the browser installer covers first-run setup.
- **Needs feedback:** examples for freelancers, agencies, service companies, and B2B teams.

## Contributor Experience

- **Done:** issue templates and pull request template.
- **In progress:** clarify code ownership areas and security-sensitive review expectations.
- **Planned:** small first issues after public preview feedback.

## AI Workflows

- **Done:** AI-assisted workflows including idea analysis, plans, summaries, risk review, and semantic search.
- **In progress:** safer previews, clearer permissions, and better admin configuration guidance.
- **Needs feedback:** real-world prompts and workflow examples from non-developer users.

## UI And Accessibility

- **In progress:** visual consistency and practical usability across CRM pages.
- **Planned:** accessibility review, keyboard navigation improvements, and clearer empty states.
- **Needs feedback:** screenshots and reports from different browsers and screen sizes.
