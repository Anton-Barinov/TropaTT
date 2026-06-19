# Agent Integration Guide

This guide explains how external AI agents can work with a TropaTT installation.

Use the current customer host in all examples. `demo.tropatt.com` is only a demo environment.

## Recommended Integration Model

Use MCP first, then fall back to the REST API only when a required operation is not exposed as an MCP tool yet.

```text
Agent client -> TropaTT MCP endpoint -> existing CRM services/RBAC/MySQL
Agent client -> TropaTT REST API     -> existing CRM services/RBAC/MySQL
```

Do not connect agents directly to MySQL. The CRM must remain MySQL-only, but all access should go through the application layer so permissions, visibility rules, audit behavior and validation keep working.

## MCP Endpoint

```text
POST https://your-domain.example/api/index.php?route=api/v1/mcp
```

Required headers:

```http
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-06-18
Authorization: Bearer <access_token>
```

The endpoint speaks raw JSON-RPC 2.0.

## Getting an Access Token

Use the regular login endpoint:

```bash
curl -sS -X POST 'https://your-domain.example/api/index.php?route=api/v1/auth/login' \
  -H 'Content-Type: application/json' \
  --data '{"login":"admin","password":"adminadmin"}'
```

Use the returned `data.access_token` as the bearer token. For production, create a dedicated CRM user for each external agent and give it only the roles it needs.

## Agent Startup Sequence

1. Call `initialize`.
2. Call `resources/list`.
3. Read `tropatt://server/about`.
4. Read `tropatt://server/tools`.
5. Read `tropatt://user/current`.
6. Use read tools before write tools.
7. Use public identifiers from tool responses for follow-up actions.

Example:

```bash
curl -sS -X POST 'https://your-domain.example/api/index.php?route=api/v1/mcp' \
  -H 'Authorization: Bearer <access_token>' \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"resources/read","params":{"uri":"tropatt://server/tools"}}'
```

## Useful Agent Workflows

### Daily Planning

1. `crm_get_current_user`
2. `crm_get_calendar_agenda`
3. `crm_list_tasks`
4. `crm_update_task` only after the user confirms changes

### Project Review

1. `crm_list_projects`
2. `crm_get_project`
3. `crm_list_tasks` with `project_public_id`
4. `crm_list_cycles` with `project_public_id`
5. `crm_list_cycle_tasks`

### Knowledge Assistant

1. `crm_search`
2. `crm_list_knowledge_pages`
3. `crm_get_knowledge_page`
4. `crm_create_knowledge_page` only when the user asks to draft or publish CRM knowledge

### Idea Processing

1. `crm_list_ideas`
2. `crm_get_idea`
3. `crm_add_idea_comment` for notes and decisions
4. Direct REST API can be used for the full AI idea pipeline until more pipeline-specific MCP tools are added.

### Team Chat Assistant

1. `crm_list_chats`
2. `crm_list_chat_messages`
3. `crm_send_chat_message` only for explicit user-requested messages

## Safety Rules

- Never expose or request passwords, token hashes, API keys or local filesystem paths.
- Never assume `demo.tropatt.com`; use the installation host.
- Never perform destructive actions without explicit user confirmation.
- Prefer read-only tools when the user asks for analysis.
- Use dedicated least-privilege agent users in production.
- Store bearer tokens only in the agent client's secure secret store.
- Do not bypass the API with direct database access.

## REST API Fallback

The REST API has a much wider surface than MCP. Use it when an agent needs a feature that is not exposed as a tool yet.

Core REST examples:

```text
GET  /api/index.php?route=api/v1/tasks
GET  /api/index.php?route=api/v1/projects
GET  /api/index.php?route=api/v1/cycles
GET  /api/index.php?route=api/v1/knowledge/pages
GET  /api/index.php?route=api/v1/ideas
GET  /api/index.php?route=api/v1/chats
```

When adding new MCP tools, keep them thin wrappers over existing services and document each tool in `docs/maintainer/mcp-integration.md`.
