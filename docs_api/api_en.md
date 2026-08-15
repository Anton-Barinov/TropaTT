# TropaTT CRM — API Documentation

Complete reference for the TropaTT self-hosted CRM REST API: base URL, authentication, RBAC permissions, common conventions, and the full endpoint registry.

**Language:** [Русский](api_ru.md) · **English** · [中文](api_zh.md)

> Last updated: 2026-08-15. Source of truth: `upload/api/config/routes.php` and module route files.

## Overview

TropaTT exposes a versioned JSON REST API. Every protected endpoint requires a bearer access token, and RBAC permission checks are enforced server-side.

### Base URL

```
https://{host}/api/v1/
```

### Versioning

All primary endpoints start with `/api/v1/`. Some internal endpoints have no version prefix:

- `/install/*` — browser installer
- `/internal/migration/*` — migrations

### Data formats

| Format | Usage |
|--------|-------|
| `application/json` | Main request/response format |
| `multipart/form-data` | File uploads |
| Server-Sent Events | Real-time updates (`/api/v1/events/stream`) |
| JSON-RPC | MCP endpoint (`/api/v1/mcp`) |

## Authentication

### Bearer token

Pass the access token in the `Authorization` header:

```
Authorization: Bearer <token>
```

Browser sessions use a `session_id` cookie together with the `X-CSRF-Token` header.

### Public endpoints (no auth)

`GET /api/v1/version`, `POST /api/v1/auth/login`, `POST /api/v1/security/password-reset`, `POST /api/v1/security/password-reset/confirm`, `POST /api/v1/security/invitations/accept`, and installer endpoints.

### RBAC permissions

Each protected endpoint declares required permissions (for example `task.manage`, `user.view`). A valid token without the required permission returns `403`, an invalid or missing token returns `401`.

| Group | Permission keys |
|-------|-----------------|
| Users | `user.view`, `user.manage` |
| Roles | `role.view`, `role.manage` |
| Teams & departments | `team.manage`, `department.manage` |
| Projects | `project.manage` |
| Tasks | `task.manage` |
| Clients & companies | `client.manage`, `company.manage`, `contact.manage`, `counterparty.manage` |
| Organizations | `organization.manage` |
| Knowledge | `knowledge.view`, `knowledge.create`, `knowledge.edit`, `knowledge.delete`, `knowledge.publish`, `knowledge.comment`, `knowledge.manage` |
| Settings | `settings.manage` |
| Webhooks | `webhook.manage` |
| Logs | `logs.view` |
| AI | `ai.use`, `ai.admin` |
| Import / export | `import.manage`, `export.manage` |
| Approvals | `approval.manage` |
| Recycle bin | `recycle_bin.manage` |
| API clients | `api_client.view`, `api_client.manage` |
| Intake | `intake.view`, `intake.create`, `intake.manage`, `intake.delete`, `intake.accept` |
| Ideas | `idea.view`, `idea.manage` |
| Chat | `chat.use` |

## Common conventions

### Headers

| Header | Required | Description |
|--------|:---:|-------------|
| `Authorization` | Yes (protected) | `Bearer <token>` |
| `Content-Type` | POST/PUT/PATCH | `application/json` or `multipart/form-data` |
| `Accept` | No | `application/json` (default) |
| `X-CSRF-Token` | Cookie auth | CSRF token |
| `X-Request-Id` | No | Request ID for tracing |
| `X-Correlation-Id` | No | Correlation ID |
| `X-Idempotency-Key` | No | Idempotency key |
| `X-Locale` | No | Locale: `ru-ru` or `en-gb` |

### Success response (2xx)

```json
{
  "success": true,
  "data": { },
  "meta": { "cursor": "next_cursor_string", "has_more": true }
}
```

### Error response (4xx/5xx)

```json
{
  "success": false,
  "error": { "code": "ERROR_CODE", "message": "Human-readable description" }
}
```

### Validation error (422)

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "errors": { "field_name": ["Error message"] }
  }
}
```

### HTTP status codes

| Status | Usage |
|--------|-------|
| 200 | Successful GET/PATCH/PUT/DELETE |
| 201 | Successful POST (created) |
| 204 | Successful DELETE without body |
| 400 | Bad request / invalid JSON |
| 401 | Unauthorized / invalid token |
| 403 | Forbidden (insufficient permissions) |
| 404 | Resource not found |
| 409 | Conflict |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Internal server error |

### Pagination

Cursor-based: use the `cursor` query parameter and `limit`, read `meta.cursor` and `meta.has_more` from the response.

### Identifiers

All IDs are ULIDs (26 characters). `row_version` is an integer used for optimistic locking on updates.

### Idempotency

The `X-Idempotency-Key` header prevents duplicate operations.

## OpenAPI and MCP

- **OpenAPI specification** — a machine-readable spec is served by the running installation at `GET /api/v1/docs/openapi`.
- **MCP (Model Context Protocol)** — the CRM exposes a JSON-RPC MCP endpoint at `POST /api/v1/mcp` for AI agents. See the MCP documentation in the repository.

## Endpoint reference

> **Note:** many endpoints have two URL variants — a RESTful path (`/api/v1/resources`) and an alias (`/api/v1/resource/list`, `/api/v1/resource/create`). Aliases are marked with `🔄`.

### Install & Migration

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/install/status` | Installation status | No | — | Check whether CRM is installed |
| GET, POST | `/install/check` | Check DB connection | No | — | Connection parameter validation |
| POST | `/install/setup` | Start installation | No | — | Create tables, root user |
| GET | `/internal/migration/status` | Migration status | Yes | `settings.manage` | Current migration state |
| POST | `/internal/migration/up` | Apply migrations | Yes | `settings.manage` | Run pending migrations |
| GET | `/internal/migration/dry-run` | Dry-run migrations | Yes | `settings.manage` | Preview changes |
| GET | `/internal/migration/rollback-check` | Check rollback | Yes | `settings.manage` | Check rollback availability |

### Health & Version

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/health/status` | Basic health check | Yes | — | Service status |
| GET | `/api/v1/health/deep` | Deep health check | Yes | — | Check DB, cache, AI |
| GET | `/api/v1/version` | CRM version (public) | No | — | Current version without auth |
| POST | `/api/v1/mcp` | Model Context Protocol | Yes | — | JSON-RPC for AI agents |

### Core Update

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/core/version` | Core version | Yes | `settings.manage` | Detailed version information |
| GET | `/api/v1/core/updates/status` | Update status | Yes | `settings.manage` | Available updates |
| POST | `/api/v1/core/updates/check` | Check updates | Yes | `settings.manage` | Request to check for new versions |
| GET | `/api/v1/core/updates/changes` | List changes | Yes | `settings.manage` | Changelog between versions |
| POST | `/api/v1/core/updates/preflight` | Preflight check | Yes | `settings.manage` | Update readiness check |
| POST | `/api/v1/core/updates/session` | Update session | Yes | `settings.manage` | Create update session |
| GET | `/api/v1/core/updates/history` | Update history | Yes | `settings.manage` | Past updates log |
| GET | `/api/v1/core/updates/log/{job_id}` | Update log | Yes | `settings.manage` | Details of a specific update |
| POST | `/api/v1/core/updates/recovery-key` | Recovery key | Yes | `settings.manage` | Issue a recovery key for an update session |

### Auth

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/auth/login` | Log in | No | — | Authentication, token issuance |
| POST | `/api/v1/auth/logout` | Log out | Yes | — | Session termination |
| GET | `/api/v1/auth/me` | Current user | Yes | — | Authenticated user data |
| GET | `/api/v1/auth/menu` | User menu | Yes | — | Menu structure for a role |
| GET | `/api/v1/auth/menu/preferences` | Menu preferences | Yes | — | Item visibility settings |
| PUT, PATCH | `/api/v1/auth/menu/preferences` | Save menu preferences | Yes | — | Update visibility settings |
| GET | `/api/v1/roles/{public_id}/menu-template` | Role menu template | Yes | `role.manage` | Menu template for a role |
| PUT, PATCH | `/api/v1/roles/{public_id}/menu-template` | Save role menu template | Yes | `role.manage` | Update template |
| GET | `/api/v1/teams/{public_id}/menu-template` | Team menu template | Yes | `team.manage` | Menu template for a team |
| PUT, PATCH | `/api/v1/teams/{public_id}/menu-template` | Save team menu template | Yes | `team.manage` | Update template |
| GET | `/api/v1/users/{public_id}/menu-preferences` | User menu preferences (admin) | Yes | `user.manage` | Admin management |
| PUT, PATCH | `/api/v1/users/{public_id}/menu-preferences` | Save user menu preferences (admin) | Yes | `user.manage` | Admin management |

### Telemetry

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/telemetry/frontend-event` | Frontend event | Yes | — | Client-side telemetry |
| POST | `/api/v1/telemetry/csp-report` | CSP report | No | — | Content Security Policy violations |
| POST | `/api/v1/telemetry/login-debug` | Login debug log | Yes | `logs.view` | Login debug information |

### Users

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/users` 🔄 | List users | Yes | `user.view` | Cursor-based pagination |
| POST | `/api/v1/users` 🔄 | Create user | Yes | `user.manage` | — |
| GET | `/api/v1/users/{public_id}` 🔄 | user details | Yes | `user.view` | — |
| PATCH, PUT | `/api/v1/users/{public_id}` 🔄 | Update user | Yes | `user.manage` | Optimistic locking (`row_version`) |
| DELETE | `/api/v1/users/{public_id}` 🔄 | Deactivate user | Yes | `user.manage` | Soft-delete |
| GET | `/api/v1/users/{public_id}/tokens` 🔄 | User tokens | Yes | `user.view` | — |
| POST | `/api/v1/users/{public_id}/tokens/rotate` 🔄 | Rotate token | Yes | `user.manage` | — |
| DELETE | `/api/v1/users/{public_id}/tokens` 🔄 | Revoke token | Yes | `user.manage` | — |
| GET | `/api/v1/users/{public_id}/activity` 🔄 | User activity | Yes | `user.view` | Activity feed |

### Roles

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/roles` 🔄 | List roles | Yes | `role.view` | — |
| POST | `/api/v1/roles` 🔄 | Create role | Yes | `role.manage` | Root only |
| PATCH, PUT | `/api/v1/roles/{public_id}` 🔄 | Update role | Yes | `role.manage` | Root only |
| DELETE | `/api/v1/roles/{public_id}` 🔄 | Delete role | Yes | `role.manage` | Root only |

### Permissions

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/permissions` 🔄 | All system permissions | Yes | `role.view` | Reference |
| GET | `/api/v1/roles/{public_id}/permissions` 🔄 | Role permissions | Yes | `role.view` | — |
| PUT, PATCH | `/api/v1/roles/{public_id}/permissions` 🔄 | Assign role permissions | Yes | `role.manage` | Root only |
| GET | `/api/v1/admin/role-matrix` 🔄 | Role matrix | Yes | `role.manage` | — |
| PUT, PATCH | `/api/v1/admin/role-matrix` 🔄 | Update role matrix | Yes | `role.manage` | Root only |

### API Clients

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/api-clients` 🔄 | List API clients | Yes | `api_client.view` | — |
| POST | `/api/v1/api-clients` 🔄 | Create API client | Yes | `api_client.manage` | — |
| GET | `/api/v1/api-clients/{public_id}` 🔄 | API client details | Yes | `api_client.view` | — |
| PATCH, PUT | `/api/v1/api-clients/{public_id}` 🔄 | Update API client | Yes | `api_client.manage` | — |
| DELETE | `/api/v1/api-clients/{public_id}` 🔄 | Delete API client | Yes | `api_client.manage` | — |
| GET | `/api/v1/api-clients/{public_id}/keys` 🔄 | List client keys | Yes | `api_client.view` | — |
| POST | `/api/v1/api-clients/{public_id}/keys` 🔄 | Issue key | Yes | `api_client.manage` | — |
| POST | `/api/v1/api-keys/{public_id}/rotate` 🔄 | Rotate key | Yes | `api_client.manage` | — |
| POST, DELETE | `/api/v1/api-keys/{public_id}/revoke` 🔄 | Revoke key | Yes | `api_client.manage` | — |
| GET | `/api/v1/api-keys/{public_id}/usage` 🔄 | Key usage | Yes | `api_client.view` | — |

### Teams & Departments

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/teams` 🔄 | List teams | Yes | `team.manage` | — |
| POST | `/api/v1/teams` 🔄 | Create team | Yes | `team.manage` | — |
| GET | `/api/v1/teams/{public_id}` 🔄 | team details | Yes | `team.manage` | — |
| PATCH, PUT | `/api/v1/teams/{public_id}` 🔄 | Update team | Yes | `team.manage` | — |
| DELETE | `/api/v1/teams/{public_id}` 🔄 | Delete team | Yes | `team.manage` | — |
| GET | `/api/v1/departments` 🔄 | List departments | Yes | `department.manage` | — |
| POST | `/api/v1/departments` 🔄 | Create department | Yes | `department.manage` | — |
| GET | `/api/v1/departments/{public_id}` 🔄 | department details | Yes | `department.manage` | — |
| PATCH, PUT | `/api/v1/departments/{public_id}` 🔄 | Update department | Yes | `department.manage` | — |
| DELETE | `/api/v1/departments/{public_id}` 🔄 | Delete department | Yes | `department.manage` | — |

### Companies, Clients, Contacts, Counterparties

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/companies` 🔄 | List companies | Yes | `company.manage` | — |
| POST | `/api/v1/companies` 🔄 | Create company | Yes | `company.manage` | — |
| GET | `/api/v1/companies/{public_id}` 🔄 | company details | Yes | `company.manage` | — |
| PATCH, PUT | `/api/v1/companies/{public_id}` 🔄 | Update company | Yes | `company.manage` | — |
| DELETE | `/api/v1/companies/{public_id}` 🔄 | Delete company | Yes | `company.manage` | — |
| GET | `/api/v1/clients` 🔄 | List clients | Yes | `client.manage` | — |
| POST | `/api/v1/clients` 🔄 | Create client | Yes | `client.manage` | — |
| GET | `/api/v1/clients/{public_id}` 🔄 | client details | Yes | `client.manage` | — |
| PATCH, PUT | `/api/v1/clients/{public_id}` 🔄 | Update client | Yes | `client.manage` | — |
| DELETE | `/api/v1/clients/{public_id}` 🔄 | Delete client | Yes | `client.manage` | — |
| GET | `/api/v1/counterparties` 🔄 | List counterparties | Yes | `counterparty.manage` | Filter by type, search |
| POST | `/api/v1/counterparties` 🔄 | Create counterparty | Yes | `counterparty.manage` | — |
| GET | `/api/v1/counterparties/{public_id}` 🔄 | counterparty details | Yes | `counterparty.manage` | — |
| PATCH, PUT | `/api/v1/counterparties/{public_id}` 🔄 | Update counterparty | Yes | `counterparty.manage` | — |
| DELETE | `/api/v1/counterparties/{public_id}` 🔄 | Delete counterparty | Yes | `counterparty.manage` | — |
| GET | `/api/v1/contacts` 🔄 | List contacts | Yes | `contact.manage` | — |
| POST | `/api/v1/contacts` 🔄 | Create contact | Yes | `contact.manage` | Requires `full_name` (max 255) |
| GET | `/api/v1/contacts/{public_id}` 🔄 | contact details | Yes | `contact.manage` | — |
| PATCH, PUT | `/api/v1/contacts/{public_id}` 🔄 | Update contact | Yes | `contact.manage` | — |
| DELETE | `/api/v1/contacts/{public_id}` 🔄 | Delete contact | Yes | `contact.manage` | — |

### Client Cabinet

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/client-cabinet/projects` 🔄 | Client projects | Yes | `client.manage` | Own projects only |
| GET | `/api/v1/client-cabinet/projects/{public_id}` 🔄 | client project details | Yes | `client.manage` | — |
| GET | `/api/v1/client-cabinet/projects/{public_id}/tasks` 🔄 | Client project tasks | Yes | `client.manage` | — |

### Organizations

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/organizations` 🔄 | List organizations | Yes | `organization.manage` | Filter: `q`, `type` |
| POST | `/api/v1/organizations` 🔄 | Create organization | Yes | `organization.manage` | — |
| GET | `/api/v1/organizations/{public_id}` 🔄 | organization details | Yes | `organization.manage` | — |
| PATCH, PUT | `/api/v1/organizations/{public_id}` 🔄 | Update organization | Yes | `organization.manage` | Optimistic locking |
| DELETE | `/api/v1/organizations/{public_id}` 🔄 | Delete organization | Yes | `organization.manage` | — |
| GET | `/api/v1/organizations/{public_id}/members` 🔄 | Organization members | Yes | `organization.manage` | — |
| POST | `/api/v1/organizations/{public_id}/members` 🔄 | Add member | Yes | `organization.manage` | — |
| DELETE | `/api/v1/organizations/{public_id}/members/{user_public_id}` 🔄 | Delete member | Yes | `organization.manage` | — |

### Statuses, Priorities, Tags

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/statuses` 🔄 | List statuses | Yes | `task.manage` | Filter by `scope` (task/project) |
| POST | `/api/v1/statuses` 🔄 | Create status | Yes | `task.manage` | Requires `title`, `code` (unique), `scope` (task/project), `color` (HEX) |
| GET | `/api/v1/statuses/{public_id}` 🔄 | status details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/statuses/{public_id}` 🔄 | Update status | Yes | `task.manage` | — |
| DELETE | `/api/v1/statuses/{public_id}` 🔄 | Delete status | Yes | `task.manage` | — |
| POST | `/api/v1/statuses/{public_id}/remap-delete` 🔄 | Delete with reassignment | Yes | `task.manage` | Move tasks to another status |
| GET | `/api/v1/priorities` 🔄 | List priorities | Yes | `task.manage` | — |
| POST | `/api/v1/priorities` 🔄 | Create priority | Yes | `task.manage` | — |
| GET | `/api/v1/priorities/{public_id}` 🔄 | priority details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/priorities/{public_id}` 🔄 | Update priority | Yes | `task.manage` | — |
| DELETE | `/api/v1/priorities/{public_id}` 🔄 | Delete priority | Yes | `task.manage` | — |
| GET | `/api/v1/tags` 🔄 | List tags | Yes | `task.manage` | — |
| POST | `/api/v1/tags` 🔄 | Create tag | Yes | `task.manage` | — |
| GET | `/api/v1/tags/{public_id}` 🔄 | tag details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/tags/{public_id}` 🔄 | Update tag | Yes | `task.manage` | — |
| DELETE | `/api/v1/tags/{public_id}` 🔄 | Delete tag | Yes | `task.manage` | — |

### Task-Tag Binding

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{task_public_id}/tags` 🔄 | Task tags | Yes | `task.manage` | — |
| POST | `/api/v1/tasks/{task_public_id}/tags/{tag_public_id}` 🔄 | Attach tag | Yes | `task.manage` | — |
| DELETE | `/api/v1/tasks/{task_public_id}/tags/{tag_public_id}` 🔄 | Detach tag | Yes | `task.manage` | — |

### Projects

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/projects` 🔄 | List projects | Yes | `project.manage` | Cursor-based, filters: `status`, `client_public_id`, `q` |
| POST | `/api/v1/projects` 🔄 | Create project | Yes | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}` 🔄 | project details | Yes | `project.manage` | — |
| PATCH, PUT | `/api/v1/projects/{public_id}` 🔄 | Update project | Yes | `project.manage` | Optimistic locking |
| DELETE | `/api/v1/projects/{public_id}` 🔄 | Archive project | Yes | `project.manage` | Soft-delete |
| GET | `/api/v1/projects/{public_id}/timeline` 🔄 | Timeline (Gantt) | Yes | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/summary` 🔄 | Project summary | Yes | `project.manage` | Progress, tasks, milestones |
| GET | `/api/v1/projects/{public_id}/milestones-summary` 🔄 | Milestones summary | Yes | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/risks` 🔄 | Project risks | Yes | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/workload` 🔄 | Upload members | Yes | `project.manage` | — |

### Tasks

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks` 🔄 | List tasks | Yes | `task.manage` | Cursor-based, filters |
| POST | `/api/v1/tasks` 🔄 | Create task | Yes | `task.manage` | — |
| GET | `/api/v1/tasks/board` 🔄 | Kanban board | Yes | `task.manage` | Grouped by statuses |
| POST | `/api/v1/tasks/bulk` 🔄 | Bulk update | Yes | `task.manage` | — |
| GET | `/api/v1/tasks/by-key/{task_key}` | Task by key | Yes | `task.manage` | Human-readable key |
| GET | `/api/v1/tasks/{public_id}` 🔄 | task details | Yes | `task.manage` | With comments, files, etc. |
| PATCH, PUT | `/api/v1/tasks/{public_id}` 🔄 | Update task | Yes | `task.manage` | Optimistic locking, `identity_edit_forbidden` |
| DELETE | `/api/v1/tasks/{public_id}` 🔄 | Delete task (recycle bin) | Yes | `task.manage` | Soft-delete |
| POST | `/api/v1/tasks/{public_id}/move` 🔄 | Move task on board | Yes | `task.manage` | Body: `to_status_public_id` (or `to_status`) |
| GET | `/api/v1/tasks/{public_id}/activity` | Task activity | Yes | `task.manage` | Activity feed |
| GET | `/api/v1/tasks/{public_id}/comments` 🔄 | Task comments | Yes | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/comments` 🔄 | Add comment | Yes | `task.manage` | Body: `body` (string, max 8000). Returns the created comment with `public_id` |
| GET | `/api/v1/tasks/{public_id}/files` | Task files | Yes | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/knowledge-pages` | Attach knowledge page | Yes | `task.manage`, `knowledge.view` | Attach a knowledge page to a task |

### Task Relations

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/relations` | Task relations | Yes | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/relations` | Create relation | Yes | `task.manage` | — |
| DELETE | `/api/v1/task-relations/{public_id}` | Delete relation | Yes | `task.manage` | — |
| GET | `/api/v1/task-relations/search-tasks` | Search tasks for relation | Yes | `task.manage` | — |

### Comments

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| PATCH, PUT | `/api/v1/comments/{public_id}` 🔄 | Update comment | Yes | — | — |
| DELETE | `/api/v1/comments/{public_id}` 🔄 | Delete comment | Yes | — | — |

### Comment Drafts

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | Get draft | Yes | `task.manage` | — |
| POST, PUT, PATCH | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | Save draft | Yes | `task.manage` | — |
| DELETE | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | Delete draft | Yes | `task.manage` | — |

### Subtasks

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/subtasks` 🔄 | Task subtasks | Yes | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/subtasks` 🔄 | Create subtask | Yes | `task.manage` | — |
| GET | `/api/v1/subtasks/{public_id}` 🔄 | subtask details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/subtasks/{public_id}` 🔄 | Update subtask | Yes | `task.manage` | — |
| DELETE | `/api/v1/subtasks/{public_id}` 🔄 | Delete subtask | Yes | `task.manage` | — |

### Checklists

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/checklists` 🔄 | Task checklists | Yes | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/checklists` 🔄 | Create checklist | Yes | `task.manage` | — |
| GET | `/api/v1/checklists/{public_id}` 🔄 | checklist details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/checklists/{public_id}` 🔄 | Update checklist | Yes | `task.manage` | — |
| DELETE | `/api/v1/checklists/{public_id}` 🔄 | Delete checklist | Yes | `task.manage` | — |
| GET | `/api/v1/checklists/{public_id}/items` 🔄 | Checklist items | Yes | `task.manage` | — |
| POST | `/api/v1/checklists/{public_id}/items` 🔄 | Add item | Yes | `task.manage` | — |
| GET | `/api/v1/checklist-items/{public_id}` 🔄 | item details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/checklist-items/{public_id}` 🔄 | Update item | Yes | `task.manage` | — |
| DELETE | `/api/v1/checklist-items/{public_id}` 🔄 | Delete item | Yes | `task.manage` | — |

### Work Cycles

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/cycles` | List cycles | Yes | `task.manage` | — |
| POST | `/api/v1/cycles` | Create cycle | Yes | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}` | cycle details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/cycles/{public_id}` | Update cycle | Yes | `project.manage` | — |
| DELETE | `/api/v1/cycles/{public_id}` | Delete cycle | Yes | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/start` | Start cycle | Yes | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/complete` | Complete cycle | Yes | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/reopen` | Reopen cycle | Yes | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/archive` | Archive cycle | Yes | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/tasks` | Cycle tasks | Yes | `task.manage` | — |
| POST | `/api/v1/cycles/{public_id}/tasks` | Add tasks to cycle | Yes | `project.manage` | — |
| DELETE | `/api/v1/cycles/{public_id}/tasks/{task_public_id}` | Delete task from cycle | Yes | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/summary` | Cycle summary | Yes | `task.manage` | — |
| POST | `/api/v1/cycles/{public_id}/transfer-unfinished` | Transfer unfinished | Yes | `project.manage` | — |

### Project Modules

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/project-modules` | List project modules | Yes | `project.manage` | — |
| POST | `/api/v1/project-modules` | Create module | Yes | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}` | module details | Yes | `project.manage` | — |
| PATCH, PUT | `/api/v1/project-modules/{public_id}` | Update module | Yes | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}` | Delete module | Yes | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/archive` | Archive module | Yes | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/tasks` | Module tasks | Yes | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/tasks` | Add tasks to module | Yes | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}/tasks/{task_public_id}` | Delete task from module | Yes | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/members` | Module members | Yes | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/members` | Add member | Yes | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}/members/{user_public_id}` | Delete member | Yes | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/links` | Module links | Yes | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/links` | Add link | Yes | `project.manage` | — |
| PATCH, PUT | `/api/v1/project-module-links/{public_id}` | Update link | Yes | `project.manage` | — |
| DELETE | `/api/v1/project-module-links/{public_id}` | Delete link | Yes | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/summary` | Module summary | Yes | `project.manage` | — |

### Milestones

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/milestones` 🔄 | List milestones | Yes | `project.manage` | Requires `project_public_id` or `project_public_ids` (comma-separated) |
| POST | `/api/v1/milestones` 🔄 | Create milestone | Yes | `project.manage` | Requires `title`, `project_public_id`. Optional: `due_at` (YYYY-MM-DD) |
| GET | `/api/v1/milestones/{public_id}` 🔄 | milestone details | Yes | `project.manage` | — |
| PATCH, PUT | `/api/v1/milestones/{public_id}` 🔄 | Update milestone | Yes | `project.manage` | — |
| DELETE | `/api/v1/milestones/{public_id}` 🔄 | Delete milestone | Yes | `project.manage` | — |

### Dependencies

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/dependencies` 🔄 | List dependencies | Yes | `task.manage` | — |
| POST | `/api/v1/dependencies` 🔄 | Create dependency | Yes | `task.manage` | Types: FS, SS, FF, SF, BLOCKS |
| DELETE | `/api/v1/dependencies/{public_id}` 🔄 | Delete dependency | Yes | `task.manage` | — |

### Files

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/files` | Upload file | Yes | `task.manage` | `multipart/form-data`, max 20MB |
| GET | `/api/v1/files/{public_id}` | File metadata | Yes | `task.manage` | — |
| GET | `/api/v1/files/{public_id}/download` | Download file | Yes | `task.manage` | Binary response |
| DELETE | `/api/v1/files/{public_id}` | Delete file | Yes | `task.manage` | — |

### Templates

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/template/tasks` 🔄 | List task templates | Yes | `task.manage` | — |
| POST | `/api/v1/template/tasks` 🔄 | Create task template | Yes | `task.manage` | — |
| GET | `/api/v1/template/tasks/{public_id}` 🔄 | task template details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/template/tasks/{public_id}` 🔄 | Update task template | Yes | `task.manage` | — |
| DELETE | `/api/v1/template/tasks/{public_id}` 🔄 | Delete task template | Yes | `task.manage` | — |
| POST | `/api/v1/template/tasks/{public_id}/apply` 🔄 | Apply task template | Yes | `task.manage` | Create a task from a template |
| GET | `/api/v1/template/projects` 🔄 | List project templates | Yes | `project.manage` | — |
| POST | `/api/v1/template/projects` 🔄 | Create project template | Yes | `project.manage` | — |
| GET | `/api/v1/template/projects/{public_id}` 🔄 | project template details | Yes | `project.manage` | — |
| PATCH, PUT | `/api/v1/template/projects/{public_id}` 🔄 | Update project template | Yes | `project.manage` | — |
| DELETE | `/api/v1/template/projects/{public_id}` 🔄 | Delete project template | Yes | `project.manage` | — |
| POST | `/api/v1/template/projects/{public_id}/apply` 🔄 | Apply project template | Yes | `project.manage` | Create a project from a template |

### Notifications

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/notifications` 🔄 | List notifications | Yes | `task.manage` | — |
| POST | `/api/v1/notifications` 🔄 | Create notification | Yes | `task.manage` | — |
| GET | `/api/v1/notifications/counters` 🔄 | Unread counter | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/notifications/{public_id}/read` 🔄 | Mark as read | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/notifications/{public_id}/unread` 🔄 | Mark as unread | Yes | `task.manage` | — |
| POST | `/api/v1/notifications/mark-all-read` 🔄 | Mark all as read | Yes | `task.manage` | — |
| GET | `/api/v1/notifications/push-subscriptions` 🔄 | Push subscriptions | Yes | `task.manage` | — |
| POST | `/api/v1/notifications/push-subscriptions` 🔄 | Create push subscription | Yes | `task.manage` | — |
| DELETE | `/api/v1/notifications/push-subscriptions/{public_id}` 🔄 | Delete push subscription | Yes | `task.manage` | — |
| POST | `/api/v1/notifications/push-test` 🔄 | Test push notification | Yes | `task.manage` | — |

### Reminders

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/reminders` 🔄 | List reminders | Yes | `task.manage` | — |
| POST | `/api/v1/reminders` 🔄 | Create reminder | Yes | `task.manage` | — |
| GET | `/api/v1/reminders/{public_id}` 🔄 | reminder details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/reminders/{public_id}` 🔄 | Update reminder | Yes | `task.manage` | — |
| DELETE | `/api/v1/reminders/{public_id}` 🔄 | Delete reminder | Yes | `task.manage` | — |

### Calendar

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/calendar/events` 🔄 | Calendar events | Yes | `task.manage` | Parameters: `from`, `to` |
| POST | `/api/v1/calendar/events` 🔄 | Create event | Yes | `task.manage` | — |
| GET | `/api/v1/calendar/events/{public_id}` 🔄 | event details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/calendar/events/{public_id}` 🔄 | Update event | Yes | `task.manage` | — |
| DELETE | `/api/v1/calendar/events/{public_id}` 🔄 | Delete event | Yes | `task.manage` | — |
| GET | `/api/v1/calendar/my-day` 🔄 | My Day | Yes | `task.manage` | Daily aggregation |
| GET | `/api/v1/calendar/my-week` 🔄 | My Week | Yes | `task.manage` | Weekly aggregation |
| GET | `/api/v1/calendar/my-month` 🔄 | My Month | Yes | `task.manage` | Monthly aggregation |
| GET | `/api/v1/calendar/business` 🔄 | Business calendars | Yes | `settings.manage` | — |
| POST | `/api/v1/calendar/business` 🔄 | Create business calendar | Yes | `settings.manage` | — |
| GET | `/api/v1/calendar/business/{public_id}` 🔄 | business calendar details | Yes | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/business/{public_id}` 🔄 | Update business calendar | Yes | `settings.manage` | — |
| DELETE | `/api/v1/calendar/business/{public_id}` 🔄 | Delete business calendar | Yes | `settings.manage` | — |
| GET | `/api/v1/calendar/holidays` 🔄 | Holidays | Yes | `settings.manage` | — |
| POST | `/api/v1/calendar/holidays` 🔄 | Create holiday | Yes | `settings.manage` | — |
| GET | `/api/v1/calendar/holidays/{public_id}` 🔄 | holiday details | Yes | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/holidays/{public_id}` 🔄 | Update holiday | Yes | `settings.manage` | — |
| DELETE | `/api/v1/calendar/holidays/{public_id}` 🔄 | Delete holiday | Yes | `settings.manage` | — |
| GET | `/api/v1/calendar/working-hours` 🔄 | Working hours | Yes | `settings.manage` | — |
| POST | `/api/v1/calendar/working-hours` 🔄 | Create working hours | Yes | `settings.manage` | — |
| GET | `/api/v1/calendar/working-hours/{public_id}` 🔄 | working hours details | Yes | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/working-hours/{public_id}` 🔄 | Update working hours | Yes | `settings.manage` | — |
| DELETE | `/api/v1/calendar/working-hours/{public_id}` 🔄 | Delete working hours | Yes | `settings.manage` | — |

### Page Data (Frontend API)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/pages/my-day` | My Day data | Yes | `task.manage` | SPA page data |
| GET | `/api/v1/pages/kanban` | Kanban data | Yes | `task.manage` | SPA page data |
| GET | `/api/v1/pages/my-week` | My Week data | Yes | `task.manage` | SPA page data |

### Worklogs

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/worklogs` 🔄 | List time entries | Yes | `task.manage` | — |
| POST | `/api/v1/worklogs` 🔄 | Create time entry | Yes | `task.manage` | Requires `task_public_id`, `minutes_spent` (int, minutes), `logged_at` (YYYY-MM-DD) |
| GET | `/api/v1/worklogs/summary` | Time summary | Yes | `task.manage` | — |
| GET | `/api/v1/worklogs/earnings` | Time earnings | Yes | `task.manage` | — |
| GET | `/api/v1/worklogs/matrix` | Time matrix | Yes | `task.manage` | — |
| GET | `/api/v1/worklogs/detail` | time entry details | Yes | `task.manage` | Requires `project_public_id` |
| GET | `/api/v1/worklogs/{public_id}` 🔄 | record details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/worklogs/{public_id}` 🔄 | Update record | Yes | `task.manage` | Updatable fields: `minutes_spent`, `description`, `logged_at` |
| DELETE | `/api/v1/worklogs/{public_id}` 🔄 | Delete record | Yes | `task.manage` | — |
| GET | `/api/v1/worklogs/task/{public_id}` | Time by task | Yes | `task.manage` | — |

### Dashboard & Analytics

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/dashboard/summary` 🔄 | Dashboard summary | Yes | `task.manage` | Overdue tasks, unassigned |
| GET | `/api/v1/analytics/summary` 🔄 | Analytics summary | Yes | `task.manage` | — |
| GET | `/api/v1/analytics/projects` 🔄 | Analytics by projects | Yes | `task.manage` | — |
| GET | `/api/v1/analytics/users` 🔄 | Analytics by users | Yes | `task.manage` | — |
| GET | `/api/v1/dashboard/widgets` | Dashboard widgets | Yes | — | Current user widgets |
| PUT | `/api/v1/dashboard/widgets` | Save widgets | Yes | — | Update current user widgets |

### Search

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/search/global` 🔄 | Global search | Yes | `task.manage` | Across all entities |
| GET | `/api/v1/search/suggestions` | Search suggestions | Yes | `task.manage` | — |
| GET | `/api/v1/search/tasks` 🔄 | Search tasks | Yes | `task.manage` | — |
| GET | `/api/v1/search/projects` 🔄 | Search projects | Yes | `task.manage` | — |
| GET | `/api/v1/search/clients` 🔄 | Search clients | Yes | `task.manage` | — |
| GET | `/api/v1/search/counterparties` | Search counterparties | Yes | `task.manage` | — |

### Mentions & Reactions

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/mentions` 🔄 | List mentions | Yes | `task.manage` | — |
| POST | `/api/v1/mentions` 🔄 | Create mention | Yes | `task.manage` | — |
| DELETE | `/api/v1/mentions/{public_id}` 🔄 | Delete mention | Yes | `task.manage` | — |
| GET | `/api/v1/reactions` 🔄 | List reactions | Yes | `task.manage` | — |
| POST | `/api/v1/reactions` 🔄 | Add reaction | Yes | `task.manage` | — |
| DELETE | `/api/v1/reactions/{public_id}` 🔄 | Delete reaction | Yes | `task.manage` | — |

### Subscriptions & Favorites

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/subscriptions` 🔄 | List subscriptions | Yes | `task.manage` | — |
| POST | `/api/v1/subscriptions` 🔄 | Create subscription | Yes | `task.manage` | — |
| DELETE | `/api/v1/subscriptions/{public_id}` 🔄 | Delete subscription | Yes | `task.manage` | — |
| GET | `/api/v1/favorites` 🔄 | List favorites | Yes | `task.manage` | — |
| POST | `/api/v1/favorites` 🔄 | Add favorite | Yes | `task.manage` | — |
| DELETE | `/api/v1/favorites/{public_id}` 🔄 | Delete from favorites | Yes | `task.manage` | — |

### Saved Views

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/views` 🔄 | List saved views | Yes | `task.manage` | — |
| POST | `/api/v1/views` 🔄 | Create saved view | Yes | `task.manage` | — |
| GET | `/api/v1/views/{public_id}` | saved view details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/views/{public_id}` 🔄 | Update saved view | Yes | `task.manage` | — |
| DELETE | `/api/v1/views/{public_id}` 🔄 | Delete saved view | Yes | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/archive` | Archive saved view | Yes | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/duplicate` | Duplicate saved view | Yes | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/pin` | Pin saved view | Yes | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/touch-last-used` | Update last_used_at | Yes | `task.manage` | — |
| GET | `/api/v1/views/{public_id}/task-filters` | Saved view task filters | Yes | `task.manage` | — |

### Activity & Audit

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/activity/feed` 🔄 | Activity feed | Yes | `logs.view` | — |
| GET | `/api/v1/history/entity/{entity_type}/{public_id}` 🔄 | Entity history | Yes | `logs.view` | — |
| GET | `/api/v1/audit/list` 🔄 | List audit | Yes | `logs.view` | — |
| GET | `/api/v1/audit/user/{public_id}` 🔄 | Audit by user | Yes | `logs.view` | — |
| GET | `/api/v1/audit/entity/{entity_type}/{public_id}` 🔄 | Audit by entity | Yes | `logs.view` | — |

### Logs

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/logs/request` | Request logs | Yes | `logs.view` | — |
| GET | `/api/v1/logs/security` | Security logs | Yes | `logs.view` | — |
| GET | `/api/v1/logs/audit` | Audit logs | Yes | `logs.view` | — |
| GET | `/api/v1/logs/frontend-errors/chart` | Frontend error chart | Yes | `logs.view` | Frontend error aggregation |

### Settings & Feature Flags

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/settings` 🔄 | List settings | Yes | `settings.manage` | — |
| GET | `/api/v1/settings/{name}` 🔄 | Setting value | Yes | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/settings/{name}` 🔄 | Set setting | Yes | `settings.manage` | — |
| GET | `/api/v1/retention/metadata` 🔄 | Retention metadata | Yes | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/retention/metadata` 🔄 | Set retention | Yes | `settings.manage` | — |
| GET | `/api/v1/feature-flags` 🔄 | List feature flags | Yes | `feature_flag.manage` | — |
| PATCH, PUT | `/api/v1/feature-flags/{public_id}` 🔄 | Update feature flag | Yes | `feature_flag.manage` | — |

### Custom Fields

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/custom-fields` 🔄 | List fields | Yes | `settings.manage` | — |
| POST | `/api/v1/custom-fields` 🔄 | Create field | Yes | `settings.manage` | — |
| GET | `/api/v1/custom-fields/{public_id}` 🔄 | field details | Yes | `settings.manage` | — |
| PATCH, PUT | `/api/v1/custom-fields/{public_id}` 🔄 | Update field | Yes | `settings.manage` | — |
| DELETE | `/api/v1/custom-fields/{public_id}` 🔄 | Delete field | Yes | `settings.manage` | — |
| GET | `/api/v1/custom-fields/values` 🔄 | Field values | Yes | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/custom-fields/values` 🔄 | Set values | Yes | `settings.manage` | — |

### Workflow Rules

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/workflow/rules` 🔄 | List rules | Yes | `settings.manage` | — |
| POST | `/api/v1/workflow/rules` 🔄 | Create rule | Yes | `settings.manage` | — |
| GET | `/api/v1/workflow/rules/{public_id}` 🔄 | rule details | Yes | `settings.manage` | — |
| PATCH, PUT | `/api/v1/workflow/rules/{public_id}` 🔄 | Update rule | Yes | `settings.manage` | — |
| DELETE | `/api/v1/workflow/rules/{public_id}` 🔄 | Delete rule | Yes | `settings.manage` | — |
| POST | `/api/v1/workflow/rules/{public_id}/run-test` 🔄 | Test rule run | Yes | `settings.manage` | — |
| GET | `/api/v1/workflow/runs` 🔄 | Run history | Yes | `settings.manage` | — |

### SLA

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/sla/policies` 🔄 | List SLA policies | Yes | `settings.manage` | — |
| POST | `/api/v1/sla/policies` 🔄 | Create SLA policy | Yes | `settings.manage` | — |
| GET | `/api/v1/sla/policies/{public_id}` 🔄 | SLA policy details | Yes | `settings.manage` | — |
| PATCH, PUT | `/api/v1/sla/policies/{public_id}` 🔄 | Update SLA policy | Yes | `settings.manage` | — |
| DELETE | `/api/v1/sla/policies/{public_id}` 🔄 | Delete SLA policy | Yes | `settings.manage` | — |
| GET | `/api/v1/sla/report` 🔄 | SLA report | Yes | `settings.manage` | — |
| POST | `/api/v1/sla/assign/{public_id}` 🔄 | Assign SLA to task | Yes | `settings.manage` | — |

### Approvals

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/approvals` 🔄 | List approvals | Yes | `approval.manage` | — |
| POST | `/api/v1/approvals` 🔄 | Create approval | Yes | `approval.manage` | — |
| GET | `/api/v1/approvals/{public_id}` 🔄 | approval details | Yes | `approval.manage` | — |
| POST | `/api/v1/approvals/{public_id}/approve` 🔄 | Approval | Yes | `approval.manage` | — |
| POST | `/api/v1/approvals/{public_id}/reject` 🔄 | Reject approval | Yes | `approval.manage` | — |

### Webhooks

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/webhooks` 🔄 | List webhooks | Yes | `webhook.manage` | — |
| POST | `/api/v1/webhooks` 🔄 | Create webhook | Yes | `webhook.manage` | Requires `endpoint` (URL, max 2048), `events` (array of strings) |
| PATCH, PUT | `/api/v1/webhooks/{public_id}` 🔄 | Update webhook | Yes | `webhook.manage` | — |
| DELETE | `/api/v1/webhooks/{public_id}` 🔄 | Delete webhook | Yes | `webhook.manage` | — |
| GET | `/api/v1/webhooks/deliveries` 🔄 | All deliveries | Yes | `webhook.manage` | — |
| GET | `/api/v1/webhooks/{public_id}/deliveries` 🔄 | Webhook deliveries | Yes | `webhook.manage` | — |
| POST | `/api/v1/webhooks/{public_id}/test` 🔄 | Test webhook | Yes | `webhook.manage` | — |

### Import & Export

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/import/jobs` | List import jobs | Yes | `import.manage` | — |
| POST | `/api/v1/import/jobs` | Create import job | Yes | `import.manage` | `multipart/form-data` |
| GET | `/api/v1/import/jobs/{public_id}` | Import job status | Yes | `import.manage` | — |
| POST | `/api/v1/import/jobs/{public_id}/cancel` | Cancel import | Yes | `import.manage` | — |
| POST | `/api/v1/import/jobs/{public_id}/retry` | Retry import | Yes | `import.manage` | — |
| GET | `/api/v1/export/jobs` | List export jobs | Yes | `export.manage` | — |
| POST | `/api/v1/export/jobs` | Create export job | Yes | `export.manage` | — |
| GET | `/api/v1/export/jobs/{public_id}` | Export job status | Yes | `export.manage` | — |
| GET | `/api/v1/export/jobs/{public_id}/download` | Download export | Yes | `export.manage` | Binary |
| POST | `/api/v1/export/jobs/{public_id}/cancel` | Cancel export | Yes | `export.manage` | — |
| POST | `/api/v1/export/jobs/{public_id}/retry` | Retry export | Yes | `export.manage` | — |

### Recycle Bin

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/recycle-bin` 🔄 | List recycle bin | Yes | `recycle_bin.manage` | — |
| POST | `/api/v1/recycle-bin/{public_id}/restore` 🔄 | Restore | Yes | `recycle_bin.manage` | — |
| DELETE, POST | `/api/v1/recycle-bin/{public_id}/purge` 🔄 | Purge (permanent delete) | Yes | `recycle_bin.manage` | — |

### Recurring Tasks

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/recurring` 🔄 | List recurring rules | Yes | `task.manage` | Filters: `project_public_id`, `is_active` |
| POST | `/api/v1/recurring` 🔄 | Create rule | Yes | `task.manage` | — |
| GET | `/api/v1/recurring/{public_id}` 🔄 | rule details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/recurring/{public_id}` 🔄 | Update rule | Yes | `task.manage` | — |
| DELETE | `/api/v1/recurring/{public_id}` 🔄 | Delete rule | Yes | `task.manage` | — |
| POST | `/api/v1/recurring/{public_id}/pause` 🔄 | Pause rule | Yes | `task.manage` | — |
| POST | `/api/v1/recurring/{public_id}/resume` 🔄 | Resume rule | Yes | `task.manage` | — |

### Estimate Sets

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/estimate-sets` | List estimate sets | Yes | `project.manage` | — |
| POST | `/api/v1/estimate-sets` | Create estimate set | Yes | `project.manage` | — |
| GET | `/api/v1/estimate-sets/{public_id}` | estimate set details | Yes | `project.manage` | — |
| PATCH, PUT | `/api/v1/estimate-sets/{public_id}` | Update estimate set | Yes | `project.manage` | — |
| POST | `/api/v1/estimate-sets/{public_id}/archive` | Archive estimate set | Yes | `project.manage` | — |
| DELETE | `/api/v1/estimate-sets/{public_id}` | Delete estimate set | Yes | `project.manage` | — |
| GET | `/api/v1/estimate-sets/{public_id}/options` | Set options | Yes | `project.manage` | — |
| POST | `/api/v1/estimate-sets/{public_id}/options` | Create option | Yes | `project.manage` | — |
| PATCH, PUT | `/api/v1/estimate-options/{public_id}` | Update option | Yes | `project.manage` | — |
| POST | `/api/v1/estimate-options/{public_id}/archive` | Archive option | Yes | `project.manage` | — |
| DELETE | `/api/v1/estimate-options/{public_id}` | Delete option | Yes | `project.manage` | — |
| GET | `/api/v1/tasks/{public_id}/estimates` | Task estimates | Yes | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/estimates` | Assign estimate | Yes | `task.manage` | — |
| DELETE | `/api/v1/tasks/{public_id}/estimates/{set_public_id}` | Delete estimate | Yes | `task.manage` | — |
| GET | `/api/v1/projects/{public_id}/estimate-summary` | Project estimate summary | Yes | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/estimate-summary` | Cycle estimate summary | Yes | `task.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/estimate-summary` | Module estimate summary | Yes | `project.manage` | — |

### Sticky Notes

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/sticky-notes` | List sticky notes | Yes | `task.manage` | — |
| POST | `/api/v1/sticky-notes` | Create sticky note | Yes | `task.manage` | — |
| GET | `/api/v1/sticky-notes/{public_id}` | sticky note details | Yes | `task.manage` | — |
| PATCH, PUT | `/api/v1/sticky-notes/{public_id}` | Update sticky note | Yes | `task.manage` | — |
| DELETE | `/api/v1/sticky-notes/{public_id}` | Delete sticky note | Yes | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/archive` | Archive sticky note | Yes | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/unarchive` | Unarchive sticky note | Yes | `task.manage` | — |
| POST | `/api/v1/sticky-notes/reorder` | Reorder sticky notes | Yes | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/convert-to-task` | Convert to task | Yes | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/convert-to-page` | Convert to knowledge page | Yes | `task.manage` | — |

### Knowledge Base

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/overview` | Knowledge base overview | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/search` | Search knowledge | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/recent` | Recent pages | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/popular` | Popular pages | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/review-queue` | Review queue | Yes | `knowledge.publish` | — |
| GET | `/api/v1/knowledge/outdated` | Outdated pages | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/favorites` | Favorite pages | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/suggest` | Suggestions | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/analytics` | Knowledge analytics | Yes | `knowledge.analytics_view` | — |
| GET | `/api/v1/knowledge/templates` | Page templates | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/templates` | Create template | Yes | `knowledge.template_manage` | — |
| GET | `/api/v1/knowledge/entities/{entity_type}/{entity_public_id}/pages` | Entity pages | Yes | `knowledge.view` | — |

### Knowledge — Spaces

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/spaces` | Spaces | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces-tree` | Spaces tree | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/spaces` | Create space | Yes | `knowledge.manage` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}` | space details | Yes | `knowledge.view` | — |
| PATCH, PUT | `/api/v1/knowledge/spaces/{public_id}` | Update space | Yes | `knowledge.manage` | — |
| DELETE | `/api/v1/knowledge/spaces/{public_id}` | Archive space | Yes | `knowledge.manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/archive` | Archive (alt.) | Yes | `knowledge.manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/restore` | Restore | Yes | `knowledge.manage` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/tree` | Pages tree | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/permissions` | Space permissions | Yes | `knowledge.permission_manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/permissions` | Add permission | Yes | `knowledge.permission_manage` | — |
| DELETE | `/api/v1/knowledge/permissions/{permission_id}` | Delete permission | Yes | `knowledge.permission_manage` | — |

### Knowledge — Pages

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages` | List pages | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages` | Create page | Yes | `knowledge.create` | — |
| GET | `/api/v1/knowledge/pages/{public_id}` | page details | Yes | `knowledge.view` | — |
| PATCH, PUT | `/api/v1/knowledge/pages/{public_id}` | Update page | Yes | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}` | Delete page | Yes | `knowledge.delete` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/publish` | Publish page | Yes | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/archive` | Archive page | Yes | `knowledge.delete` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/restore` | Restore page | Yes | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/move` | Move page | Yes | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/copy` | Duplicate page | Yes | `knowledge.create` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/duplicate` | Duplicate page | Yes | `knowledge.create` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/request-review` | Request review | Yes | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/approve` | Approve review | Yes | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/review` | Approve (alt.) | Yes | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/reject` | Reject review | Yes | `knowledge.publish` | — |

### Knowledge — Page Drafts, Links, Tags, Files

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/draft` | Draft | Yes | `knowledge.edit` | — |
| POST, PUT, PATCH | `/api/v1/knowledge/pages/{public_id}/draft` | Save draft | Yes | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/draft` | Delete draft | Yes | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/links` | Page links | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/links` | Add link | Yes | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/links/{link_public_id}` | Delete link | Yes | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/tags` | Page tags | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/tags/{tag_public_id}` | Attach tag | Yes | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/tags/{tag_public_id}` | Detach tag | Yes | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/files` | Page files | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/files` | Upload file | Yes | `knowledge.edit` | `multipart/form-data` |
| DELETE | `/api/v1/knowledge/files/{file_public_id}` | Delete file | Yes | `knowledge.edit` | — |

### Knowledge — Comments

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/comments` | Page comments | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/comments` | Add comment | Yes | `knowledge.comment` | — |
| DELETE | `/api/v1/knowledge/comments/{comment_public_id}` | Delete comment | Yes | `knowledge.comment` | — |
| POST | `/api/v1/knowledge/comments/{comment_public_id}/resolve` | Resolve comment | Yes | `knowledge.comment` | — |
| POST | `/api/v1/knowledge/comments/{comment_public_id}/reopen` | Reopen comment | Yes | `knowledge.comment` | — |

### Knowledge — Favorites & Subscriptions

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/knowledge/pages/{public_id}/favorite` | Add favorite | Yes | `knowledge.view` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/favorite` | Delete from favorites | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/subscribe` | Subscribe to page | Yes | `knowledge.view` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/subscribe` | Unsubscribe from page | Yes | `knowledge.view` | — |

### Knowledge — Export & Import

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/export` | Export all pages | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/export` | Export page | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/export` | Export space | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/import` | Import pages | Yes | `knowledge.import` | — |

### Knowledge — Page Versions & Locking

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/versions` | Page versions | Yes | `knowledge.view` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}` | version details | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}/restore` | Restore version | Yes | `knowledge.publish` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}/diff` | Version diff | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/lock` | Lock page | Yes | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/unlock` | Unlock page | Yes | `knowledge.publish` | — |

### Knowledge — AI

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/knowledge/pages/{public_id}/ai/summary` | AI page summary | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/explain` | AI explanation | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/similar` | Similar pages | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/checklist` | AI checklist | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/faq-from-comments` | FAQ from comments | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/ai/suggest-for-task/{task_public_id}` | Task suggestions | Yes | `knowledge.view` | — |
| POST | `/api/v1/knowledge/ai/admin/find-duplicates` | Search duplicates | Yes | `knowledge.admin` | — |
| GET | `/api/v1/knowledge/ai/admin/find-orphans` | Search orphans | Yes | `knowledge.admin` | — |
| POST | `/api/v1/knowledge/ai/admin/suggest-structure/{public_id}` | Structure suggestion | Yes | `knowledge.admin` | — |

### Knowledge — Admin

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/admin/knowledge/settings` | Knowledge settings | Yes | `knowledge.admin` | — |
| PATCH, PUT | `/api/v1/admin/knowledge/settings` | Update settings | Yes | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/reindex` | Reindex | Yes | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/rebuild-permissions` | Recalculate permissions | Yes | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/cleanup-drafts` | Clean up drafts | Yes | `knowledge.admin` | — |

### Intake Items

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/intake-items` | List intake items | Yes | `intake.view` | — |
| POST | `/api/v1/intake-items` | Create intake item | Yes | `intake.create` | — |
| GET | `/api/v1/intake-items/{public_id}` | intake item details | Yes | `intake.view` | — |
| PATCH, PUT | `/api/v1/intake-items/{public_id}` | Update intake item | Yes | `intake.manage` | — |
| DELETE | `/api/v1/intake-items/{public_id}` | Delete intake item | Yes | `intake.delete` | — |
| POST | `/api/v1/intake-items/{public_id}/accept` | Accept intake item | Yes | `intake.accept` | — |
| POST | `/api/v1/intake-items/{public_id}/reject` | Reject intake item | Yes | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/snooze` | Snooze intake item | Yes | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/duplicate` | Duplicate intake item | Yes | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/reopen` | Reopen intake item | Yes | `intake.manage` | — |
| GET | `/api/v1/intake-items/{public_id}/activities` | Intake activity | Yes | `intake.view` | — |

### OPS / Admin

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ops/system` 🔄 | System info | Yes | `logs.view` | — |
| GET | `/api/v1/ops/metrics` 🔄 | Metrics | Yes | `logs.view` | — |
| POST | `/api/v1/ops/jobs/run` 🔄 | Start background jobs | Yes | `logs.view` | — |
| GET | `/api/v1/admin/widgets/summary` 🔄 | Summary widget | Yes | `logs.view` | — |
| GET | `/api/v1/admin/widgets/system` 🔄 | System widget | Yes | `logs.view` | — |
| GET | `/api/v1/admin/cache` | Cache statistics | Yes | `settings.manage` | — |
| POST | `/api/v1/admin/cache/clear` | Clear cache | Yes | `settings.manage` | — |

### Docs & Events

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/docs/openapi` | OpenAPI specification | Yes | `logs.view` | — |
| GET | `/api/v1/docs/schema` | JSON Schema | Yes | `logs.view` | — |
| GET | `/api/v1/events/stream` | SSE stream | Yes | — | Real-time updates |
| POST | `/api/v1/visual-editor/upload-image` | Upload image | Yes | — | `multipart/form-data`, for the visual editor |

### Security (Sessions, Invitations, 2FA, Impersonation)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/security/sessions` 🔄 | List sessions | Yes | — | — |
| DELETE | `/api/v1/security/sessions/{public_id}` 🔄 | Revoke session | Yes | — | — |
| POST | `/api/v1/security/sessions/revoke-others` 🔄 | Revoke other sessions | Yes | — | — |
| POST | `/api/v1/security/sessions/revoke-device` 🔄 | Revoke device sessions | Yes | — | — |
| GET | `/api/v1/security/invitations` 🔄 | List invitations | Yes | `user.manage` | — |
| POST | `/api/v1/security/invitations` 🔄 | Create invitation | Yes | `user.manage` | — |
| POST | `/api/v1/security/invitations/accept` | Accept invitation | No | — | Public |
| GET | `/api/v1/security/invitations/{public_id}` 🔄 | invitation details | Yes | `user.manage` | — |
| POST | `/api/v1/security/password-reset` 🔄 | Request password reset | No | — | Public |
| POST | `/api/v1/security/password-reset/confirm` 🔄 | Confirm reset | No | — | Public |
| GET | `/api/v1/security/2fa/status` | 2FA status | Yes | — | — |
| POST | `/api/v1/security/2fa/enable` | Enable 2FA | Yes | — | — |
| POST | `/api/v1/security/2fa/disable` | Disable 2FA | Yes | — | — |
| POST | `/api/v1/security/2fa/verify` | Check 2FA code | No | — | Internal challenge endpoint (public, sessionless) |
| POST | `/api/v1/security/impersonation/start` | Start impersonation | Yes | `user.manage` | — |
| GET | `/api/v1/security/impersonation/status` | Impersonation status | Yes | — | — |
| POST | `/api/v1/security/impersonation/stop` | Stop impersonation | Yes | — | — |

### Profile

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/profile/me` 🔄 | My profile | Yes | — | — |
| PATCH, PUT | `/api/v1/profile/me` 🔄 | Update profile | Yes | — | — |
| GET | `/api/v1/profile/preferences` 🔄 | My preferences | Yes | — | — |
| PATCH, PUT | `/api/v1/profile/preferences` 🔄 | Update preferences | Yes | — | — |
| POST | `/api/v1/profile/change-password` 🔄 | Change password | Yes | — | — |

### AI — Providers & Models

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/providers` | List AI providers | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/providers` | Create provider | Yes | `ai.admin` | — |
| GET | `/api/v1/ai/providers/{public_id}` | provider details | Yes | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/providers/{public_id}` | Update provider | Yes | `ai.admin` | — |
| DELETE | `/api/v1/ai/providers/{public_id}` | Delete provider | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/providers/{public_id}/test` | Test provider | Yes | `ai.admin` | — |
| PUT | `/api/v1/ai/providers/{public_id}/secret` | Set secret | Yes | `ai.admin` | — |
| DELETE | `/api/v1/ai/providers/{public_id}/secret` | Delete secret | Yes | `ai.admin` | — |
| GET | `/api/v1/ai/models` | List models | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/models/sync` | Sync models | Yes | `ai.admin` | — |
| GET | `/api/v1/ai/retention-policies` | AI retention policies | Yes | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/retention-policies/{policy_code}` | Update policy | Yes | `ai.admin` | — |

### AI — Settings & Preferences

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/settings` | AI settings | Yes | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/settings` | Update AI settings | Yes | `ai.admin` | — |
| GET | `/api/v1/ai/preferences` | AI preferences | Yes | `ai.use` | — |
| PATCH, PUT | `/api/v1/ai/preferences` | Update preferences | Yes | `ai.use` | — |
| GET | `/api/v1/ai/action-types` | AI action types | Yes | `ai.use` | — |
| GET | `/api/v1/ai/availability` | AI availability | Yes | — | — |

### AI — Actions & Suggestions

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/ai/actions/{action_type}` | Execute AI action | Yes | `ai.use` | Dynamic type |
| POST | `/api/v1/ai/tasks/{task_public_id}/summary` | AI task summary | Yes | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/decompose` | AI task decomposition | Yes | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/checklist` | AI task checklist | Yes | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/quality` | AI quality assessment | Yes | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/next-action` | AI next action | Yes | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/comment-draft` | AI comment draft | Yes | `ai.use` | — |
| POST | `/api/v1/ai/tasks/priority` | AI prioritization | Yes | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/summary` | AI project summary | Yes | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/risks` | AI project risks | Yes | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/client-report` | AI client report | Yes | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/summary` | AI client summary | Yes | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/meeting-prep` | AI meeting preparation | Yes | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/data-quality` | AI client data quality | Yes | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/client-safe-report` | AI safe report | Yes | `ai.use` | — |
| POST | `/api/v1/ai/calendar/events/{event_public_id}/agenda` | AI meeting agenda | Yes | `ai.use` | — |
| POST | `/api/v1/ai/dashboard/digest` | AI dashboard digest | Yes | `ai.use` | — |
| POST | `/api/v1/ai/analytics/kpi-explanation` | AI KPI explanation | Yes | `ai.use` | — |
| POST | `/api/v1/ai/analytics/risks-explanation` | AI risk explanation | Yes | `ai.use` | — |
| POST | `/api/v1/ai/analytics/team-workload-summary` | AI workload summary | Yes | `ai.use` | — |
| POST | `/api/v1/ai/admin/log-review` | AI log review | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/admin/webhook-health` | AI webhook health | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/admin/workflow-audit` | AI workflow audit | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/my-day/plan` | AI day plan | Yes | `ai.use` | — |
| POST | `/api/v1/ai/my-week/plan` | AI week plan | Yes | `ai.use` | — |
| POST | `/api/v1/ai/search/semantic` | Semantic search | Yes | `ai.use` | — |

### AI — Suggestions Management

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/suggestions` | List suggestions | Yes | `ai.use` | — |
| GET | `/api/v1/ai/suggestions/{public_id}` | suggestion details | Yes | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/dismiss` | Reject suggestion | Yes | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/apply-preview` | Preview apply | Yes | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/confirm` | Apply suggestion | Yes | `ai.use` | — |

### AI — Intent, Prompts, Schemas, Usage, Jobs

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/intent-settings` | Intent settings | Yes | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/intent-settings/{intent_code}` | Update intent | Yes | `ai.admin` | — |
| GET | `/api/v1/ai/prompt-templates` | Prompt templates | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/prompt-templates` | Create template | Yes | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/prompt-templates/{public_id}` | Update template | Yes | `ai.admin` | — |
| GET | `/api/v1/ai/json-schemas` | JSON schemas | Yes | `ai.admin` | — |
| POST | `/api/v1/ai/json-schemas` | Create schema | Yes | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/json-schemas/{public_id}` | Update schema | Yes | `ai.admin` | — |
| GET | `/api/v1/ai/usage` | AI usage | Yes | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/audit` | AI audit | Yes | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/jobs` | AI tasks | Yes | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/jobs/{public_id}` | AI job details | Yes | `ai.view_cron_results` | — |
| POST | `/api/v1/ai/jobs/{public_id}/retry` | Retry AI job | Yes | `ai.manage_cron_jobs` | — |
| POST | `/api/v1/ai/jobs/{job_code}/dry-run` | Dry-run AI job | Yes | `ai.manage_cron_jobs` | — |
| POST | `/api/v1/ai/jobs/{job_code}/run-once` | Run once | Yes | `ai.manage_cron_jobs` | — |

### Modules

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/modules` | List modules | Yes | `settings.manage` | — |
| GET | `/api/v1/modules/{name}` | Module info | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/install` | Set module | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/activate` | Activate module | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/deactivate` | Deactivate module | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/uninstall` | Delete module | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/purge` | Purge module (delete files) | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/bulk` | Bulk action on modules | Yes | `settings.manage` | Body: `action` + `modules[]` |
| GET | `/api/v1/modules/{name}/config` | Module configuration | Yes | `settings.manage` | — |
| PUT | `/api/v1/modules/{name}/config` | Update configuration | Yes | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/health` | Module health check | Yes | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/migrations` | Module migrations | Yes | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/errors` | Module errors | Yes | `settings.manage` | — |
| DELETE | `/api/v1/modules/{name}/errors` | Clear errors | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/install-from-url` | Set from URL | Yes | `settings.manage` | — |
| POST | `/api/v1/modules/install-from-file` | Set from file | Yes | `settings.manage` | `multipart/form-data` |

### Ideas

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ideas` | List ideas | Yes | `idea.view` | Filters: `status`, `q` |
| POST | `/api/v1/ideas` | Create idea | Yes | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}` | idea details | Yes | `idea.view` | — |
| PATCH | `/api/v1/ideas/{public_id}` | Update idea | Yes | `idea.manage` | — |
| DELETE | `/api/v1/ideas/{public_id}` | Delete idea | Yes | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/vote` | Vote | Yes | `idea.manage` | — |
| PATCH | `/api/v1/ideas/{public_id}/status` | Change status | Yes | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/reset-analysis` | Reset AI analysis | Yes | `idea.manage` | — |
| GET, DELETE | `/api/v1/ideas/{public_id}/debug-log` | Debug log | Yes | `ai.admin` | — |
| GET | `/api/v1/ideas/{public_id}/questions` | Interview questions | Yes | `idea.view` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/additional-questions` | Additional questions | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/gap-questions` | Gap questions | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/understanding-card` | Understanding card | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/refined-card` | Refined card | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/potential` | Potential assessment | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/risk-report` | Risk report | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/pitfalls` | Pitfalls analysis | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/implementation-plan` | Implementation plan | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/final-recommendation` | Final recommendation | Yes | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/suggested-tasks` | Suggested tasks | Yes | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/create-project-tasks` | Create tasks from idea | Yes | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}/ai-iterations` | AI iterations | Yes | `idea.view` | — |
| POST, DELETE | `/api/v1/ideas/{public_id}/interview` | AI interview | Yes | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/interview-answers` | Save answers | Yes | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/comments` | Add comment | Yes | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}/comments` | Idea comments | Yes | `idea.view` | — |

### Chat

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/chats` | List chats | Yes | `chat.use` | — |
| POST | `/api/v1/chats` | Create chat | Yes | `chat.use` | — |
| GET | `/api/v1/chats/unread-count` | Unread messages | Yes | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}` | chat details | Yes | `chat.use` | — |
| PATCH | `/api/v1/chats/{public_id}/settings` | Chat settings | Yes | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}/participants` | Chat participants | Yes | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}/messages` | Chat messages | Yes | `chat.use` | Cursor-based |
| POST | `/api/v1/chats/{public_id}/messages` | Send message | Yes | `chat.use` | — |
| PATCH | `/api/v1/chats/{public_id}/messages/{message_public_id}` | Edit message | Yes | `chat.use` | — |
| DELETE | `/api/v1/chats/{public_id}/messages/{message_public_id}` | Delete message | Yes | `chat.use` | Soft-delete |
| POST | `/api/v1/chats/{public_id}/attachments` | Upload attachment | Yes | `chat.use` | `multipart/form-data` |
| GET | `/api/v1/chats/{public_id}/attachments/{file_public_id}/download` | Download attachment | Yes | `chat.use` | Binary |
| POST | `/api/v1/chats/{public_id}/read` | Mark as read | Yes | `chat.use` | — |
| POST | `/api/v1/chats/{public_id}/archive` | Archive chat | Yes | `chat.use` | — |
| POST | `/api/v1/chats/{public_id}/restore` | Restore chat | Yes | `chat.use` | — |

### Module: ActiveCollab migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.activecollab-migration/connections` | List connections | Yes | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/connections` | Create connection | Yes | `module.activecollab-migration.manage`, `module.activecollab-migration.secret_manage` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}` | connection details | Yes | `module.activecollab-migration.view` | — |
| PATCH | `/_module/crm.activecollab-migration/connections/{public_id}` | Update connection | Yes | `module.activecollab-migration.manage`, `module.activecollab-migration.secret_manage` | — |
| DELETE | `/_module/crm.activecollab-migration/connections/{public_id}` | Delete connection | Yes | `module.activecollab-migration.delete` | — |
| POST | `/_module/crm.activecollab-migration/connections/{public_id}/test` | Test connection | Yes | `module.activecollab-migration.manage` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}/workspaces` | List workspaces | Yes | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/connections/{public_id}/discover` | Discover data | Yes | `module.activecollab-migration.run` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.activecollab-migration.view` | — |
| PATCH | `/_module/crm.activecollab-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.activecollab-migration.manage` | — |
| GET | `/_module/crm.activecollab-migration/jobs` | List migration tasks | Yes | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/jobs` | Create migration task | Yes | `module.activecollab-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}` | migration task details | Yes | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/run` | Start task | Yes | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.activecollab-migration.delete` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/items` | Task items | Yes | `module.activecollab-migration.view` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.activecollab-migration.view` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/report` | Task report | Yes | `module.activecollab-migration.report_view` | — |

### Module: Asana migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.asana-migration/connections` | List connections | Yes | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/connections` | Create connection | Yes | `module.asana-migration.manage`, `module.asana-migration.secret_manage` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}` | connection details | Yes | `module.asana-migration.view` | — |
| PATCH | `/_module/crm.asana-migration/connections/{public_id}` | Update connection | Yes | `module.asana-migration.manage`, `module.asana-migration.secret_manage` | — |
| DELETE | `/_module/crm.asana-migration/connections/{public_id}` | Delete connection | Yes | `module.asana-migration.delete` | — |
| POST | `/_module/crm.asana-migration/connections/{public_id}/test` | Test connection | Yes | `module.asana-migration.manage` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}/workspaces` | List workspaces | Yes | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/connections/{public_id}/discover` | Discover data | Yes | `module.asana-migration.run` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.asana-migration.view` | — |
| PATCH | `/_module/crm.asana-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.asana-migration.manage` | — |
| GET | `/_module/crm.asana-migration/jobs` | List migration tasks | Yes | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/jobs` | Create migration task | Yes | `module.asana-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}` | migration task details | Yes | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/run` | Start task | Yes | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.asana-migration.delete` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/items` | Task items | Yes | `module.asana-migration.view` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.asana-migration.view` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/report` | Task report | Yes | `module.asana-migration.report_view` | — |

### Module: Bitrix24 migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.bitrix24-migration/connections` | List connections | Yes | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/connections` | Create connection | Yes | `module.bitrix24-migration.manage`, `module.bitrix24-migration.secret_manage` | — |
| GET | `/_module/crm.bitrix24-migration/connections/{public_id}` | connection details | Yes | `module.bitrix24-migration.view` | — |
| PATCH | `/_module/crm.bitrix24-migration/connections/{public_id}` | Update connection | Yes | `module.bitrix24-migration.manage`, `module.bitrix24-migration.secret_manage` | — |
| DELETE | `/_module/crm.bitrix24-migration/connections/{public_id}` | Delete connection | Yes | `module.bitrix24-migration.delete` | — |
| POST | `/_module/crm.bitrix24-migration/connections/{public_id}/test` | Test connection | Yes | `module.bitrix24-migration.manage` | — |
| POST | `/_module/crm.bitrix24-migration/connections/{public_id}/discover` | Discover data | Yes | `module.bitrix24-migration.run` | — |
| GET | `/_module/crm.bitrix24-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.bitrix24-migration.view` | — |
| PATCH | `/_module/crm.bitrix24-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.bitrix24-migration.manage` | — |
| GET | `/_module/crm.bitrix24-migration/jobs` | List migration tasks | Yes | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/jobs` | Create migration task | Yes | `module.bitrix24-migration.run` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}` | migration task details | Yes | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/run` | Start task | Yes | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.bitrix24-migration.delete` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/items` | Task items | Yes | `module.bitrix24-migration.view` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.bitrix24-migration.view` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/report` | Task report | Yes | `module.bitrix24-migration.report_view` | — |

### Module: ClickUp migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.clickup-migration/oauth/authorize-url` | OAuth authorization URL | Yes | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| POST | `/_module/crm.clickup-migration/oauth/exchange` | Exchange OAuth code | Yes | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| GET | `/_module/crm.clickup-migration/connections` | List connections | Yes | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/connections` | Create connection | Yes | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}` | connection details | Yes | `module.clickup-migration.view` | — |
| PATCH | `/_module/crm.clickup-migration/connections/{public_id}` | Update connection | Yes | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| DELETE | `/_module/crm.clickup-migration/connections/{public_id}` | Delete connection | Yes | `module.clickup-migration.delete` | — |
| POST | `/_module/crm.clickup-migration/connections/{public_id}/test` | Test connection | Yes | `module.clickup-migration.manage` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}/projects` | Discover data | Yes | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.clickup-migration.view` | — |
| PATCH | `/_module/crm.clickup-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.clickup-migration.manage` | — |
| GET | `/_module/crm.clickup-migration/jobs` | List migration tasks | Yes | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/jobs` | Create migration task | Yes | `module.clickup-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}` | migration task details | Yes | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/run` | Start task | Yes | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.clickup-migration.delete` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/items` | Task items | Yes | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/report` | Task report | Yes | `module.clickup-migration.report_view` | — |

### Module: Confluence migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.confluence-migration/connections` | List connections | Yes | — | — |
| POST | `/_module/crm.confluence-migration/connections` | Create connection | Yes | `module.confluence-migration.manage`, `module.confluence-migration.secret_manage` | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}` | connection details | Yes | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}` | Update connection | Yes | `module.confluence-migration.manage`, `module.confluence-migration.secret_manage` | — |
| DELETE | `/_module/crm.confluence-migration/connections/{public_id}` | Delete connection | Yes | `module.confluence-migration.delete` | — |
| POST | `/_module/crm.confluence-migration/connections/{public_id}/test` | Test connection | Yes | `module.confluence-migration.manage` | — |
| POST | `/_module/crm.confluence-migration/connections/{public_id}/discover` | Discover spaces | Yes | `module.confluence-migration.run` | — |
| GET | `/_module/crm.confluence-migration/jobs` | List migration tasks | Yes | — | — |
| POST | `/_module/crm.confluence-migration/jobs` | Create migration task | Yes | `module.confluence-migration.run`, `knowledge.import`, `knowledge.create`, `knowledge.edit`, `knowledge.publish` | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}` | migration task details | Yes | — | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/start` | Start task | Yes | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.confluence-migration.run` | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/items` | Task items | Yes | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/logs` | Task logs | Yes | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/report` | Task report | Yes | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/unresolved-links` | Unresolved links | Yes | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/unsupported-macros` | Unsupported macros | Yes | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/download-report` | Download report | Yes | — | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}/user-mappings` | User mappings | Yes | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.confluence-migration.manage` | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}/group-mappings` | Group mappings | Yes | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}/group-mappings/{mapping_id}` | Update group mapping | Yes | `module.confluence-migration.manage` | — |
| GET | `/_module/crm.confluence-migration/settings` | Module settings | Yes | — | — |
| PATCH | `/_module/crm.confluence-migration/settings` | Update settings | Yes | `module.confluence-migration.manage` | — |

### Module: Draw.io diagrams (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.drawio/diagrams` | List diagrams | Yes | `module.drawio.view` | — |
| POST | `/_module/crm.drawio/diagrams` | Create diagram | Yes | `module.drawio.manage` | — |
| GET | `/_module/crm.drawio/diagrams/{public_id}` | Diagram details | Yes | `module.drawio.view` | — |
| PATCH | `/_module/crm.drawio/diagrams/{public_id}` | Update diagram | Yes | `module.drawio.manage` | — |
| DELETE | `/_module/crm.drawio/diagrams/{public_id}` | Delete diagram | Yes | `module.drawio.manage` | — |

### Module: GitHub (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.github-integration/connections` | List connections | Yes | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/connections` | Create connection | Yes | `module.github-integration.manage`, `module.github-integration.secret_manage` | — |
| PATCH | `/_module/crm.github-integration/connections/{public_id}` | Update connection | Yes | `module.github-integration.manage` | — |
| DELETE | `/_module/crm.github-integration/connections/{public_id}` | Delete connection | Yes | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/connections/{public_id}/test` | Test connection | Yes | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/connections/{public_id}/discover` | Discover repositories | Yes | `module.github-integration.manage` | — |
| GET | `/_module/crm.github-integration/links` | List repo links | Yes | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/links` | Create repo link | Yes | `module.github-integration.manage`, `project.manage`, `task.manage` | — |
| DELETE | `/_module/crm.github-integration/links/{public_id}` | Delete repo link | Yes | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/links/{public_id}/sync` | Sync now | Yes | `module.github-integration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.github-integration/links/{public_id}/logs` | Link logs | Yes | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/webhook/{public_id}` | Incoming webhook | No | — | HMAC-verified |

### Module: GitLab (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.gitlab-integration/connections` | List connections | Yes | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/connections` | Create connection | Yes | `module.gitlab-integration.manage`, `module.gitlab-integration.secret_manage` | — |
| PATCH | `/_module/crm.gitlab-integration/connections/{public_id}` | Update connection | Yes | `module.gitlab-integration.manage` | — |
| DELETE | `/_module/crm.gitlab-integration/connections/{public_id}` | Delete connection | Yes | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/connections/{public_id}/test` | Test connection | Yes | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/connections/{public_id}/discover` | Discover projects | Yes | `module.gitlab-integration.manage` | — |
| GET | `/_module/crm.gitlab-integration/links` | List project links | Yes | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/links` | Create project link | Yes | `module.gitlab-integration.manage`, `project.manage`, `task.manage` | — |
| DELETE | `/_module/crm.gitlab-integration/links/{public_id}` | Delete project link | Yes | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/links/{public_id}/sync` | Sync now | Yes | `module.gitlab-integration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.gitlab-integration/links/{public_id}/logs` | Link logs | Yes | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/webhook/{public_id}` | Incoming webhook | No | — | Token-verified |

### Module: Google Calendar (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.google-calendar/oauth/start` | OAuth start | Yes | `module.google-calendar.manage` | — |
| GET | `/_module/crm.google-calendar/oauth/callback` | OAuth callback | Yes | `module.google-calendar.manage` | — |
| GET | `/_module/crm.google-calendar/connections` | List connections | Yes | `module.google-calendar.view` | — |
| PUT | `/_module/crm.google-calendar/credentials` | Save credentials | Yes | `module.google-calendar.manage` | — |
| DELETE | `/_module/crm.google-calendar/credentials` | Delete credentials | Yes | `module.google-calendar.manage` | — |
| DELETE | `/_module/crm.google-calendar/connections/{public_id}` | Disable | Yes | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/connections/{public_id}/test` | Test connection | Yes | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/connections/{public_id}/sync` | Synchronization | Yes | `module.google-calendar.sync` | — |
| PATCH | `/_module/crm.google-calendar/calendars/{public_id}` | Update calendar | Yes | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/webhook` | Receive webhook | No | — | — |

### Module: Jira migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.jira-migration/connections` | List connections | Yes | — | — |
| POST | `/_module/crm.jira-migration/connections` | Create connection | Yes | `module.jira-migration.manage`, `module.jira-migration.secret_manage` | — |
| GET | `/_module/crm.jira-migration/connections/{public_id}` | connection details | Yes | — | — |
| PATCH | `/_module/crm.jira-migration/connections/{public_id}` | Update connection | Yes | `module.jira-migration.manage`, `module.jira-migration.secret_manage` | — |
| DELETE | `/_module/crm.jira-migration/connections/{public_id}` | Delete connection | Yes | `module.jira-migration.delete` | — |
| POST | `/_module/crm.jira-migration/connections/{public_id}/test` | Test connection | Yes | `module.jira-migration.manage` | — |
| POST | `/_module/crm.jira-migration/discover` | Discover data | Yes | `module.jira-migration.view` | — |
| POST | `/_module/crm.jira-migration/dry-run` | Dry-run migration | Yes | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs` | List migration tasks | Yes | — | — |
| POST | `/_module/crm.jira-migration/jobs` | Create migration task | Yes | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}` | migration task details | Yes | — | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/run` | Start task | Yes | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/items` | Task items | Yes | — | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/logs` | Task logs | Yes | — | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/report` | Task report | Yes | — | — |
| GET | `/_module/crm.jira-migration/mappings` | Mappings | Yes | — | — |
| POST | `/_module/crm.jira-migration/mappings/discover` | Discover mappings | Yes | `module.jira-migration.manage` | — |
| PATCH | `/_module/crm.jira-migration/mappings/{public_id}` | Update mapping | Yes | `module.jira-migration.manage` | — |
| GET | `/_module/crm.jira-migration/unresolved` | Unresolved items | Yes | — | — |

### Module: Kaiten migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.kaiten-migration/connections` | List connections | Yes | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/connections` | Create connection | Yes | `module.kaiten-migration.manage`, `module.kaiten-migration.secret_manage` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}` | connection details | Yes | `module.kaiten-migration.view` | — |
| PATCH | `/_module/crm.kaiten-migration/connections/{public_id}` | Update connection | Yes | `module.kaiten-migration.manage`, `module.kaiten-migration.secret_manage` | — |
| DELETE | `/_module/crm.kaiten-migration/connections/{public_id}` | Delete connection | Yes | `module.kaiten-migration.delete` | — |
| POST | `/_module/crm.kaiten-migration/connections/{public_id}/test` | Test connection | Yes | `module.kaiten-migration.manage` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/spaces` | List spaces | Yes | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/workspaces` | List workspaces | Yes | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/connections/{public_id}/discover` | Discover data | Yes | `module.kaiten-migration.run` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.kaiten-migration.view` | — |
| PATCH | `/_module/crm.kaiten-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.kaiten-migration.manage` | — |
| GET | `/_module/crm.kaiten-migration/jobs` | List migration tasks | Yes | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/jobs` | Create migration task | Yes | `module.kaiten-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}` | migration task details | Yes | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/run` | Start task | Yes | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.kaiten-migration.delete` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/items` | Task items | Yes | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/report` | Task report | Yes | `module.kaiten-migration.report_view` | — |

### Module: Linear migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.linear-migration/connections` | List connections | Yes | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/connections` | Create connection | Yes | `module.linear-migration.manage`, `module.linear-migration.secret_manage` | — |
| GET | `/_module/crm.linear-migration/connections/{public_id}` | Connection details | Yes | `module.linear-migration.view` | — |
| PATCH | `/_module/crm.linear-migration/connections/{public_id}` | Update connection | Yes | `module.linear-migration.manage` | — |
| DELETE | `/_module/crm.linear-migration/connections/{public_id}` | Delete connection | Yes | `module.linear-migration.delete` | — |
| POST | `/_module/crm.linear-migration/connections/{public_id}/test` | Test connection | Yes | `module.linear-migration.manage` | — |
| POST | `/_module/crm.linear-migration/connections/{public_id}/discover` | Discover data | Yes | `module.linear-migration.run` | — |
| GET | `/_module/crm.linear-migration/jobs` | List migration jobs | Yes | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/jobs` | Create migration job | Yes | `module.linear-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}` | Job details | Yes | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/jobs/{public_id}/run` | Run job | Yes | `module.linear-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}/items` | Job items | Yes | `module.linear-migration.view` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}/logs` | Job logs | Yes | `module.linear-migration.view` | — |

### Module: Notion migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.notion-migration/connections` | List connections | Yes | — | — |
| POST | `/_module/crm.notion-migration/connections` | Create connection | Yes | `module.notion-migration.manage`, `module.notion-migration.secret_manage` | — |
| GET | `/_module/crm.notion-migration/connections/{public_id}` | Connection details | Yes | — | — |
| PATCH | `/_module/crm.notion-migration/connections/{public_id}` | Update connection | Yes | `module.notion-migration.manage`, `module.notion-migration.secret_manage` | — |
| DELETE | `/_module/crm.notion-migration/connections/{public_id}` | Delete connection | Yes | `module.notion-migration.delete` | — |
| POST | `/_module/crm.notion-migration/connections/{public_id}/test` | Test connection | Yes | `module.notion-migration.manage` | — |
| POST | `/_module/crm.notion-migration/connections/{public_id}/discover` | Discover objects | Yes | `module.notion-migration.run` | — |
| GET | `/_module/crm.notion-migration/jobs` | List migration jobs | Yes | — | — |
| POST | `/_module/crm.notion-migration/jobs` | Create migration job | Yes | `module.notion-migration.run`, `knowledge.import`, `knowledge.create`, `knowledge.edit`, `knowledge.publish` | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}` | Job details | Yes | — | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/start` | Start job | Yes | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/pause` | Pause job | Yes | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/resume` | Resume job | Yes | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/cancel` | Cancel job | Yes | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.notion-migration.run` | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/items` | Job items | Yes | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/logs` | Job logs | Yes | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/report` | Job report | Yes | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/download-report` | Download report | Yes | — | — |
| GET | `/_module/crm.notion-migration/connections/{public_id}/user-mappings` | User mappings | Yes | — | — |
| PATCH | `/_module/crm.notion-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.notion-migration.manage` | — |
| GET | `/_module/crm.notion-migration/settings` | Module settings | Yes | — | — |
| PATCH | `/_module/crm.notion-migration/settings` | Update settings | Yes | `module.notion-migration.manage` | — |

### Module: Raycast (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.raycast/config` | MCP connection config | Yes | `module.raycast.view` | — |

### Module: Shtab.app migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.shtab-migration/connections` | List connections | Yes | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/connections` | Create connection | Yes | `module.shtab-migration.manage` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}` | connection details | Yes | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/test` | Test connection | Yes | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/crm-users` | CRM users | Yes | `module.shtab-migration.view` | — |
| PATCH | `/_module/crm.shtab-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.shtab-migration.manage` | — |
| DELETE | `/_module/crm.shtab-migration/connections/{public_id}` | Delete connection | Yes | `module.shtab-migration.delete` | — |
| GET | `/_module/crm.shtab-migration/jobs` | List migration tasks | Yes | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/jobs` | Create migration task | Yes | `module.shtab-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}` | migration task details | Yes | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/run` | Start task | Yes | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.shtab-migration.delete` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/items` | Task items | Yes | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/report` | Task report | Yes | `module.shtab-migration.report_view` | — |

### Module: Slack notifications (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.slack-integration/connections` | List connections | Yes | `module.slack-integration.view` | — |
| POST | `/_module/crm.slack-integration/connections` | Create connection | Yes | `module.slack-integration.manage`, `module.slack-integration.secret_manage` | — |
| GET | `/_module/crm.slack-integration/connections/{public_id}` | Connection details | Yes | `module.slack-integration.view` | — |
| PATCH | `/_module/crm.slack-integration/connections/{public_id}` | Update connection | Yes | `module.slack-integration.manage` | — |
| DELETE | `/_module/crm.slack-integration/connections/{public_id}` | Delete connection | Yes | `module.slack-integration.manage` | — |
| POST | `/_module/crm.slack-integration/connections/{public_id}/test` | Test connection | Yes | `module.slack-integration.manage` | — |
| GET | `/_module/crm.slack-integration/rules` | List notification rules | Yes | `module.slack-integration.view` | — |
| POST | `/_module/crm.slack-integration/rules` | Create rule | Yes | `module.slack-integration.manage` | — |
| DELETE | `/_module/crm.slack-integration/rules/{public_id}` | Delete rule | Yes | `module.slack-integration.manage` | — |
| POST | `/_module/crm.slack-integration/notify` | Send notification (workflow) | No | — | Server-side |
| GET | `/_module/crm.slack-integration/deliveries` | List deliveries | Yes | `module.slack-integration.view` | — |

### Module: Todoist migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.todoist-migration/oauth/authorize-url` | OAuth authorization URL | Yes | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| POST | `/_module/crm.todoist-migration/oauth/exchange` | Exchange OAuth code | Yes | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| GET | `/_module/crm.todoist-migration/connections` | List connections | Yes | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/connections` | Create connection | Yes | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}` | connection details | Yes | `module.todoist-migration.view` | — |
| PATCH | `/_module/crm.todoist-migration/connections/{public_id}` | Update connection | Yes | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| DELETE | `/_module/crm.todoist-migration/connections/{public_id}` | Delete connection | Yes | `module.todoist-migration.delete` | — |
| POST | `/_module/crm.todoist-migration/connections/{public_id}/test` | Test connection | Yes | `module.todoist-migration.manage` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}/projects` | Discover data | Yes | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.todoist-migration.view` | — |
| PATCH | `/_module/crm.todoist-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.todoist-migration.manage` | — |
| GET | `/_module/crm.todoist-migration/jobs` | List migration tasks | Yes | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/jobs` | Create migration task | Yes | `module.todoist-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}` | migration task details | Yes | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/run` | Start task | Yes | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.todoist-migration.delete` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/items` | Task items | Yes | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/report` | Task report | Yes | `module.todoist-migration.report_view` | — |

### Module: Toggl migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.toggl-migration/connections` | List connections | Yes | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/connections` | Create connection | Yes | `module.toggl-migration.manage`, `module.toggl-migration.secret_manage` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}` | connection details | Yes | `module.toggl-migration.view` | — |
| PATCH | `/_module/crm.toggl-migration/connections/{public_id}` | Update connection | Yes | `module.toggl-migration.manage`, `module.toggl-migration.secret_manage` | — |
| DELETE | `/_module/crm.toggl-migration/connections/{public_id}` | Delete connection | Yes | `module.toggl-migration.delete` | — |
| POST | `/_module/crm.toggl-migration/connections/{public_id}/test` | Test connection | Yes | `module.toggl-migration.manage` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}/workspaces` | List workspaces | Yes | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/connections/{public_id}/discover` | Discover data | Yes | `module.toggl-migration.run` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.toggl-migration.view` | — |
| PATCH | `/_module/crm.toggl-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.toggl-migration.manage` | — |
| GET | `/_module/crm.toggl-migration/jobs` | List migration tasks | Yes | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/jobs` | Create migration task | Yes | `module.toggl-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}` | migration task details | Yes | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/run` | Start task | Yes | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.toggl-migration.delete` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/items` | Task items | Yes | `module.toggl-migration.view` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.toggl-migration.view` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/report` | Task report | Yes | `module.toggl-migration.report_view` | — |

### Module: Trello migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.trello-migration/connections` | List connections | Yes | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/connections` | Create connection | Yes | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}` | connection details | Yes | `module.trello-migration.view` | — |
| PATCH | `/_module/crm.trello-migration/connections/{public_id}` | Update connection | Yes | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| DELETE | `/_module/crm.trello-migration/connections/{public_id}` | Delete connection | Yes | `module.trello-migration.delete` | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/test` | Test connection | Yes | `module.trello-migration.manage` | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/discover` | Discover data | Yes | `module.trello-migration.run` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.trello-migration.view` | — |
| PATCH | `/_module/crm.trello-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.trello-migration.manage` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}/board-configs` | Board configurations | Yes | `module.trello-migration.view` | — |
| PUT | `/_module/crm.trello-migration/connections/{public_id}/board-configs/{board_id}` | Save board configuration | Yes | `module.trello-migration.manage` | — |
| GET | `/_module/crm.trello-migration/jobs` | List migration tasks | Yes | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/jobs` | Create migration task | Yes | `module.trello-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}` | migration task details | Yes | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/run` | Start task | Yes | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.trello-migration.delete` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/items` | Task items | Yes | `module.trello-migration.view` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.trello-migration.view` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/report` | Task report | Yes | `module.trello-migration.report_view` | — |
| POST | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | Receive webhook | No | — | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/webhooks` | Create webhook | Yes | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| DELETE | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | Delete webhook | Yes | `module.trello-migration.manage` | — |
| HEAD | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | Check webhook | No | — | — |

### Module: WIP Limit (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.wip-limit/limits` | List limits | Yes | — | — |
| GET | `/_module/crm.wip-limit/limits/{user_id}` | User limit | Yes | — | — |
| POST | `/_module/crm.wip-limit/limits` | Set limit | Yes | — | — |
| DELETE | `/_module/crm.wip-limit/limits/{user_id}` | Delete limit | Yes | — | — |
| GET | `/_module/crm.wip-limit/summary` | Limits summary | Yes | — | — |

### Module: Worksection migration (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.worksection-migration/connections` | List connections | Yes | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/connections` | Create connection | Yes | `module.worksection-migration.manage`, `module.worksection-migration.secret_manage` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}` | connection details | Yes | `module.worksection-migration.view` | — |
| PATCH | `/_module/crm.worksection-migration/connections/{public_id}` | Update connection | Yes | `module.worksection-migration.manage`, `module.worksection-migration.secret_manage` | — |
| DELETE | `/_module/crm.worksection-migration/connections/{public_id}` | Delete connection | Yes | `module.worksection-migration.delete` | — |
| POST | `/_module/crm.worksection-migration/connections/{public_id}/test` | Test connection | Yes | `module.worksection-migration.manage` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}/workspaces` | List workspaces | Yes | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/connections/{public_id}/discover` | Discover data | Yes | `module.worksection-migration.run` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}/user-mappings` | User mappings | Yes | `module.worksection-migration.view` | — |
| PATCH | `/_module/crm.worksection-migration/connections/{public_id}/user-mappings/{mapping_id}` | Update user mapping | Yes | `module.worksection-migration.manage` | — |
| GET | `/_module/crm.worksection-migration/jobs` | List migration tasks | Yes | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/jobs` | Create migration task | Yes | `module.worksection-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}` | migration task details | Yes | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/run` | Start task | Yes | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/pause` | Pause task | Yes | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/resume` | Resume task | Yes | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/cancel` | Cancel task | Yes | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/retry-failed` | Retry failed items | Yes | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/rollback` | Rollback task | Yes | `module.worksection-migration.delete` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/items` | Task items | Yes | `module.worksection-migration.view` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/logs` | Task logs | Yes | `module.worksection-migration.view` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/report` | Task report | Yes | `module.worksection-migration.report_view` | — |

### Module: Yandex.Calendar (if installed)

| Method | Endpoint | Description | Auth | Permissions | Notes |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.yandex-calendar/connections` | Connection | Yes | `module.yandex-calendar.manage` | — |
| GET | `/_module/crm.yandex-calendar/connections` | List connections | Yes | `module.yandex-calendar.view` | — |
| DELETE | `/_module/crm.yandex-calendar/connections/{public_id}` | Disable | Yes | `module.yandex-calendar.manage` | — |
| POST | `/_module/crm.yandex-calendar/connections/{public_id}/test` | Test connection | Yes | `module.yandex-calendar.manage` | — |
| POST | `/_module/crm.yandex-calendar/connections/{public_id}/sync` | Synchronization | Yes | `module.yandex-calendar.sync` | — |
| PATCH | `/_module/crm.yandex-calendar/calendars/{public_id}` | Update calendar | Yes | `module.yandex-calendar.manage` | — |

