# Security Policy

TropaTT is a self-hosted PHP/MySQL work platform that handles business data: clients, counterparties, tasks, projects, files, chats, notifications, API keys, webhooks, automation rules, audit logs, and optional AI provider configuration.

Security issues should not be reported as public GitHub issues.

## Supported Status

TropaTT is currently in public preview. Security reports for the current `main` branch and the latest public preview release are welcome.

## Reporting A Vulnerability

Please use GitHub private vulnerability reporting / Security Advisories for this repository when available. If that is not available, contact the maintainer through the GitHub profile:

- Maintainer: [Anton-Barinov](https://github.com/Anton-Barinov)

TODO for maintainer: add a confirmed private security contact email before announcing a stable release.

## What To Include

Please include:

- affected version, commit, or branch;
- deployment type: local, shared hosting, VPS, cloud VM, office/home server;
- PHP version, MySQL version, and web server;
- clear reproduction steps;
- expected result and actual result;
- affected route, page, API endpoint, or module;
- sanitized logs, screenshots, or HTTP requests when useful;
- impact assessment if known;
- whether authentication, admin rights, or a special role is required.

Remove secrets before sending logs: passwords, session cookies, CSRF tokens, Bearer tokens, AI provider keys, webhook secrets, database credentials, private client data, and uploaded files.

## Security-Sensitive Areas

Please pay special attention to:

- authentication and session handling;
- CSRF protection;
- Bearer-token REST API access;
- RBAC and permission checks;
- admin impersonation and auditability;
- file uploads, storage and quarantine;
- webhooks and external HTTP calls;
- installer lock files and environment configuration;
- database migrations;
- AI provider keys;
- server-side AI requests;
- logging, masking and sensitive error handling.
- **module code:** modules execute in the same process as core with full access. There is no runtime sandbox. The barriers are: root-only install gate, `MODULE_SIGNING_KEY` requirement (fail-closed), `ModuleCodeValidator` code scanning at install time, and `.htaccess` on `modules/`. Install only modules from trusted sources — you are responsible for the code you activate. See `MODULE_DEVELOPMENT.md` §9.

## Response Process

The maintainer will try to:

1. acknowledge the report;
2. reproduce and assess severity;
3. prepare a fix or mitigation;
4. credit the reporter if requested and appropriate;
5. publish release notes after users have a reasonable update path.

No fixed SLA is promised during public preview.

## Responsible Disclosure

Please give the maintainer reasonable time to investigate and fix the issue before public disclosure. Do not access, modify, delete, or exfiltrate data that is not yours. Do not run destructive tests against public installations.
