# TropaTT MCP Integration

This document describes the first MCP layer for TropaTT CRM. It is intended for maintainers and coding agents.

For practical client setup, agent workflows and REST fallback rules, also read `docs/maintainer/agent-integration-guide.md`.

## Endpoint

Use the current installation host. Do not hardcode the public demo domain.

```text
POST /api/index.php?route=api/v1/mcp
```

Examples:

```text
https://example.com/api/index.php?route=api/v1/mcp
https://demo.tropatt.com/api/index.php?route=api/v1/mcp
```

The endpoint returns raw JSON-RPC 2.0, not the regular CRM API envelope.

## Authentication

MCP uses the same CRM authentication boundary as the REST API.

- Use `Authorization: Bearer <access_token>`.
- The token must belong to a real CRM user or API client accepted by the existing API auth flow.
- Tool visibility and execution are restricted by existing RBAC permissions.

Recommended headers:

```http
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-06-18
Authorization: Bearer <access_token>
```

## Supported MCP Methods

- `initialize`
- `notifications/initialized`
- `ping`
- `resources/list`
- `resources/read`
- `tools/list`
- `tools/call`

## MCP Resources

Resources are read-only context blocks that help MCP clients understand the CRM installation before calling tools. The server uses a custom `tropatt://` URI scheme and does not expose local files.

| URI | MIME type | Purpose |
| --- | --- | --- |
| `tropatt://server/about` | `text/markdown` | Overview, endpoint, auth rules and recommended agent workflow. |
| `tropatt://server/tools` | `application/json` | Tool list visible to the current authenticated user. |
| `tropatt://server/api-map` | `text/markdown` | High-level map of CRM API domains and safety notes. |
| `tropatt://user/current` | `application/json` | Sanitized current user profile and permissions. |

Example:

```bash
curl -sS -X POST 'https://example.com/api/index.php?route=api/v1/mcp' \
  -H 'Authorization: Bearer <access_token>' \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":2,"method":"resources/read","params":{"uri":"tropatt://server/tools"}}'
```

## First MCP Tools

The first version exposes only safe operational tools. It intentionally does not expose admin settings, API keys, migrations, logs, webhooks, secrets, security sessions or destructive system operations.

| Tool | Permission | Purpose |
| --- | --- | --- |
| `crm_search` | any of `task.manage`, `project.manage`, `knowledge.view` | Search CRM tasks, projects, counterparties, contacts and published knowledge pages visible to the current user. |
| `crm_list_tasks` | `task.manage` | List tasks with common filters. |
| `crm_get_task` | `task.manage` | Read one task by `public_id`. |
| `crm_create_task` | `task.manage` | Create a task as the authenticated user. |
| `crm_update_task` | `task.manage` | Update safe task fields. |
| `crm_add_task_comment` | `task.manage` | Add a comment to a visible task. |
| `crm_list_cycles` | `task.manage` | List visible work cycles/sprints. |
| `crm_get_cycle` | `task.manage` | Read one visible work cycle/sprint. |
| `crm_create_cycle` | `task.manage` | Create a work cycle/sprint for an accessible project. |
| `crm_update_cycle` | `task.manage` | Update safe work cycle/sprint fields. |
| `crm_list_cycle_tasks` | `task.manage` | List tasks assigned to a visible work cycle/sprint. |
| `crm_add_tasks_to_cycle` | `task.manage` | Add existing tasks to a visible planned or active work cycle/sprint. |
| `crm_list_users` | `user.view` | List users for assignment and collaboration lookup. |
| `crm_list_projects` | `project.manage` | List visible projects. |
| `crm_get_project` | `project.manage` | Read one project by `public_id`. |
| `crm_list_knowledge_pages` | `knowledge.view` | List visible knowledge pages. |
| `crm_get_knowledge_page` | `knowledge.view` | Read one knowledge page by `public_id`. |
| `crm_create_knowledge_page` | `knowledge.create` | Create a knowledge page in an accessible space. |
| `crm_get_current_user` | authenticated user | Read the authenticated user profile visible to MCP. |
| `crm_list_calendar_events` | `task.manage` | List calendar events visible to the current user. |
| `crm_get_calendar_agenda` | `task.manage` | Read current user day/week agenda. |
| `crm_create_calendar_event` | `task.manage` | Create a calendar event for the current user. |
| `crm_list_ideas` | authenticated user | List visible ideas. |
| `crm_get_idea` | authenticated user | Read one visible idea. |
| `crm_create_idea` | authenticated user | Create a new idea as the authenticated user. |
| `crm_add_idea_comment` | authenticated user | Add a comment to a visible idea. |
| `crm_list_chats` | authenticated participant | List chats where the current user is a participant. |
| `crm_list_chat_messages` | authenticated participant | Read messages from a chat where the user is a participant. |
| `crm_send_chat_message` | authenticated participant | Send a text message to a chat where the user is a participant. |

## API Endpoint Inventory Summary

Current core API route inventory from `api/config/routes.php` contains 899 route rows. The WIP limit module adds 5 module routes.

Main functional groups detected in the API:

- System/install/health/core update: install status, migration status, version, health, core updates.
- Auth/profile/security: login, logout, current user, menu preferences, sessions, invitations, password reset, profile preferences.
- Users/RBAC/teams/departments: users, roles, permissions, teams, departments, menu templates.
- CRM entities: organizations, companies, clients, counterparties, contacts.
- Work management: projects, project modules, tasks, subtasks, checklists, milestones, dependencies, worklogs, estimates, cycles.
- Planning and views: dashboard, calendar, saved views, custom fields, recurring tasks, approvals, workflow rules.
- Collaboration: comments, mentions, reactions, favorites, subscriptions, notifications, chats, files.
- Knowledge base: spaces, pages, comments, permissions, versions, locks, tags, files, export/import, AI helpers.
- Ideas and AI: ideas, AI analysis pipeline, AI settings, providers, prompts, jobs, availability, semantic search.
- Automation/integrations/admin: webhooks, import/export, modules, logs, audit, settings, feature flags, retention, recycle bin.
- Module routes: `/_module/crm.wip-limit/limits`, `/_module/crm.wip-limit/summary`.

To print the full endpoint list locally:

```bash
php -r '$routes=require "api/config/routes.php"; foreach($routes as $r){$m=implode(",", $r["methods"]??["GET"]); echo $m."\t".($r["pattern"]??"")."\t".($r["controller"]??"")."::".($r["action"]??"").PHP_EOL;}'
```

## Smoke Test

```bash
curl -sS -X POST 'https://example.com/api/index.php?route=api/v1/mcp' \
  -H 'Authorization: Bearer <access_token>' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"test"}}}'
```

Expected response shape:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2025-06-18",
    "capabilities": {
      "resources": {
        "listChanged": false
      },
      "tools": {
        "listChanged": false
      }
    },
    "serverInfo": {
      "name": "TropaTT CRM",
      "version": "0.1.0"
    }
  }
}
```

## Design Rules For Future MCP Tools

- Keep MCP as a thin layer over existing services.
- Do not query MySQL directly from MCP tools unless a service/repository already owns that domain.
- Never expose secrets, token hashes, passwords, API keys, local paths or raw internal numeric IDs.
- Reuse existing RBAC permissions for every tool.
- Prefer read-only tools first; add write tools only when the action is safe, obvious and already supported by the REST API.
- Keep tool payloads compact. AI clients should receive useful summaries and public identifiers, not unbounded database dumps.
- Do not hardcode `demo.tropatt.com`; all examples must work on any installed host.
