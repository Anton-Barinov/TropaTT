# Security Review Checklist

Use this checklist for changes that touch authentication, authorization, files, API endpoints, chat, AI, webhooks, installer behavior, or database access.

## Access control

- Verify every API route has the correct auth requirement.
- Check RBAC for read, create, update, delete, export, and admin actions.
- Confirm users can access only chats, files, projects, tasks, ideas, and counterparties they are allowed to see.
- Test direct URL/API access, not only UI visibility.

## Request safety

- Validate CSRF protection for browser-originating mutations.
- Confirm rate limits for login, password reset, AI calls, webhooks, and file uploads.
- Validate all identifiers server-side; never trust public IDs from the browser without permission checks.
- Keep error responses useful but avoid leaking stack traces, secrets, SQL, filesystem paths, or provider payloads.

## Data and files

- Keep secrets in `.env` or local config files that are not tracked.
- Check uploads for size, type, ownership, and access control.
- Ensure image/file download links are authorized for chat or entity participants.
- Avoid exposing storage paths directly.

## AI workflows

- Provider keys must remain server-side.
- AI responses are untrusted input; parse, validate, and fallback safely.
- AI suggestions should be preview-before-apply for business-changing operations.
- Do not store raw sensitive prompts unless explicitly needed and documented.

## Installer

- Installer must refuse unsafe re-installation once the system is configured.
- Generated credentials and secrets must not be printed in logs.
- Database creation and seeding must be idempotent enough for shared hosting retries.
