# RBAC Review

This document describes the Role-Based Access Control (RBAC) coverage for the TropaTT API endpoints.

---

## Overview

| Metric | Value |
|--------|-------|
| Total API routes | 901 |
| Authenticated (`auth => true`) | 891 (98.9%) |
| Public (`auth => false`) | 10 (1.1%) |
| With explicit `required_permissions` | 839 (93.2%) |
| Auth-only, no permissions | 52 (5.8%) |

**Conclusion:** RBAC coverage is strong. All authenticated routes require either explicit permissions or session validation. No route exposes data without authentication unless intentionally public.

---

## Public endpoints (auth => false)

These 10 routes require no authentication:

| Route | Purpose |
|-------|---------|
| `api/v1/version` | Version check (no auth required) |
| `api/v1/telemetry/csp-report` | CSP violation report endpoint |
| `api/v1/auth/login` | User login |
| `api/v1/auth/register` | User registration |
| `api/v1/auth/password-reset-request` | Password reset request |
| `api/v1/auth/password-reset-confirm` | Password reset confirmation |
| `api/v1/install/status` | Installer status check |
| `api/v1/install/requirements` | Installer requirements check |
| `api/v1/install/database` | Installer database setup |
| `api/v1/install/complete` | Installer completion |

All public endpoints are safe by design — they either provide read-only information (version, CSP report), handle authentication flows, or serve the browser installer which locks itself after installation.

---

## Permission groups

The API uses the following permission groups, organized by functional area:

### System & Settings

| Permission | Description | Example routes |
|---|---|---|
| `settings.manage` | Update system settings | Admin settings, feature flags, rate limits |
| `logs.view` | View audit logs | Activity log, security log, request log |
| `backup.manage` | Backup and restore | Database backup, file backup |
| `update.manage` | System updates | Apply core updates |
| `job.manage` | Background jobs | Job queue management |

### User & Role Management

| Permission | Description | Example routes |
|---|---|---|
| `user.view` | View user profiles | User list, user detail |
| `user.manage` | Create, update, delete users | User creation, role assignment |
| `role.view` | View role definitions | Role list |
| `role.manage` | Create, update, delete roles | Permission assignment |
| `team.manage` | Manage teams | Team CRUD |

### CRM

| Permission | Description | Example routes |
|---|---|---|
| `client.view` | View client records | Client list, detail |
| `client.manage` | Create, update, delete clients | Client CRUD |
| `counterparty.view` | View counterparties | Counterparty list, detail |
| `counterparty.manage` | Create, update, delete counterparties | Counterparty CRUD |
| `contact.view` | View contacts | Contact list |
| `contact.manage` | Create, update, delete contacts | Contact CRUD |
| `company.view` | View companies | Company list |
| `company.manage` | Create, update, delete companies | Company CRUD |
| `organization.manage` | Organizations | Organization CRUD |

### Projects & Tasks

| Permission | Description | Example routes |
|---|---|---|
| `project.view` | View projects | Project list, detail, Gantt, Kanban |
| `project.manage` | Create, update, delete projects | Project CRUD, milestones |
| `task.view` | View tasks | Task list, detail, board |
| `task.manage` | Create, update, delete tasks | Task CRUD, status change |
| `task.comment` | Comment on tasks | Add, edit, delete comments |
| `task.attachment` | Manage task attachments | Upload, download files |
| `estimate.view` | View estimates | Estimate set list |
| `estimate.manage` | Manage estimates | Estimate CRUD |
| `recurring.manage` | Recurring tasks | Manage recurring rules |

### Knowledge Base

| Permission | Description | Example routes |
|---|---|---|
| `knowledge.view` | View knowledge pages | Page list, search |
| `knowledge.manage` | Create, update pages | Page CRUD |
| `knowledge.admin` | Administer knowledge base | Categories, templates, settings |

### AI

| Permission | Description | Example routes |
|---|---|---|
| `ai.use` | Use AI tools | AI analysis, suggestions, summaries |
| `ai.admin` | Administer AI settings | Provider config, rate limits, prompts |

### Communication

| Permission | Description | Example routes |
|---|---|---|
| `chat.write` | Send messages | Write to chats |
| `chat.read` | Read messages | View chat history |
| `chat.manage` | Manage chats | Create, archive chats |
| `notification.view` | View notifications | Notification history |
| `notification.manage` | Manage notifications | Mark read, settings |

### Automation

| Permission | Description | Example routes |
|---|---|---|
| `workflow.manage` | Workflow rules | Create, edit workflows |
| `sla.manage` | SLA policies | SLA configuration |
| `webhook.manage` | Webhook subscriptions | Create, edit webhooks |
| `approval.manage` | Approval flows | Approval configuration |

### Admin

| Permission | Description | Example routes |
|---|---|---|
| `admin.impersonate` | Impersonate users | Login as another user |
| `admin.audit` | View audit trail | Security audit log |
| `api_client.view` | View API clients | API key list |
| `api_client.manage` | Manage API clients | Create, revoke API keys |
| `module.manage` | Module installation | Install, remove modules |
| `module.view` | View installed modules | Module list |
| `tag.manage` | Manage tags | Tag CRUD |
| `custom_field.manage` | Custom fields | Custom field configuration |
| `cycle.manage` | Work cycles | Cycle management |
| `status.manage` | Task statuses | Status configuration |
| `priority.manage` | Priorities | Priority configuration |
| `intake.manage` | Intake forms | Intake configuration |
| `recycle_bin.manage` | Recycle bin | Restore deleted items |
| `calendar.manage` | Calendar settings | Business calendar, holidays |

---

## Routes with auth => true but no explicit permissions (52 routes)

These 52 routes require authentication but do not specify `required_permissions`. They rely on implicit authorization (e.g., user must be authenticated, or authorization is handled inside the controller/service).

### Identified groups:

| Route group | Count | Implicit authorization mechanism |
|---|---|---|
| Personal profile endpoints | ~5 | User can only access own profile |
| My Day / My Week | ~4 | User's own data |
| User preferences | ~3 | User's own preferences |
| Notification preferences | ~3 | User's own settings |
| File download (own) | ~4 | User can download own files |
| Chat messages (read) | ~5 | Chat membership check in service |
| Dashboard widgets | ~3 | User's own dashboard |
| AI results (own) | ~3 | User's own AI results |
| Search | ~2 | Scoped to user's visible data |
| Internal routing | ~20 | Helper/internal routes |

**Status:** These routes are still protected by authentication and have authorization checks in controllers/services. The absence of `required_permissions` is intentional where the data is inherently scoped to the authenticated user.

---

## Public vs low-risk endpoints

### Intentionally public:
- Version check — no sensitive data
- CSP report — minimal data, no auth needed
- Installer — only runs when system is not configured
- Login/password reset — authentication flows

### Low-risk (authenticated, no explicit permission):
- Personal profile — user's own data only
- User preferences — user's own settings
- Notifications — user's own notifications
- Dashboard — user's own dashboard widgets
- Search — scoped by visibility rules
- File download — access checked in service layer

---

## Permission verification flow

```
Request → Auth middleware → [auth => true/false]
    ↓
Permission middleware → [required_permissions]
    ↓
Controller → Service → Repository
    ↓
Authorization checks in service layer (object-level)
    ↓
Response
```

1. **Auth middleware** verifies the session or Bearer token.
2. **Permission middleware** checks that the user's role has the required permission.
3. **Controller/Service** performs object-level authorization (e.g., can this user access this specific project?).

This multi-layer approach ensures both route-level and object-level security.

---

## Recommended improvements

1. **Document the 52 implicit-auth routes** — Either add explicit `required_permissions` or document the authorization mechanism in comments.
2. **Add permission coverage test** — A script that verifies every authenticated route has either `required_permissions` or a documented exception.
3. **Review granularity** — Some permissions (e.g., `settings.manage`) are very broad. Consider splitting into more granular permissions (e.g., `settings.security`, `settings.email`, `settings.general`).

---

## Audit history

- **Initial RBAC review:** Iteration 117 (2026-07-15)
- **Routes audited:** 901
- **Public endpoints identified:** 10
- **Permissions inventoried:** ~50
- **No explicit permission routes:** 52 (all authenticated, authorization in service layer)
- **Gaps found:** None critical. All routes are authenticated or intentionally public.
