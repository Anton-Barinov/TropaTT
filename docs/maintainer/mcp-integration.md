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
| `crm_list_teams` | authenticated user | List teams visible to the current user. |
| `crm_get_team` | authenticated user | Read one visible team. |
| `crm_create_team` | `team.manage` | Create a team. |
| `crm_update_team` | `team.manage` | Update safe team fields. |
| `crm_list_departments` | `department.manage` | List visible departments. |
| `crm_get_department` | `department.manage` | Read one department. |
| `crm_create_department` | `department.manage` | Create a department. |
| `crm_update_department` | `department.manage` | Update safe department fields. |
| `crm_list_counterparties` | `counterparty.manage` | List visible counterparties. |
| `crm_get_counterparty` | `counterparty.manage` | Read one visible counterparty. |
| `crm_create_counterparty` | `counterparty.manage` | Create a counterparty. |
| `crm_update_counterparty` | `counterparty.manage` | Update safe counterparty fields. |
| `crm_list_companies` | `company.manage` | List visible organization companies. |
| `crm_get_company` | `company.manage` | Read one visible company. |
| `crm_create_company` | `company.manage` | Create an organization company. |
| `crm_update_company` | `company.manage` | Update safe company fields. |
| `crm_list_clients` | `client.manage` | List visible clients. |
| `crm_get_client` | `client.manage` | Read one visible client. |
| `crm_create_client` | `client.manage` | Create a client. |
| `crm_update_client` | `client.manage` | Update safe client fields. |
| `crm_list_contacts` | `contact.manage` | List visible contacts. |
| `crm_get_contact` | `contact.manage` | Read one visible contact. |
| `crm_create_contact` | `contact.manage` | Create a contact linked to a counterparty, company or client. |
| `crm_update_contact` | `contact.manage` | Update safe contact fields. |
| `crm_list_approvals` | `approval.manage` | List visible approval requests. |
| `crm_get_approval` | `approval.manage` | Read one approval request. |
| `crm_create_approval` | `approval.manage` | Create an approval request for an entity and reviewers. |
| `crm_approve_request` | `approval.manage` | Approve a request where the current user is a reviewer. |
| `crm_reject_request` | `approval.manage` | Reject a request where the current user is a reviewer. |
| `crm_list_recurring_rules` | `task.manage` | List recurring task/project/reminder/calendar rules. |
| `crm_get_recurring_rule` | `task.manage` | Read one recurring rule. |
| `crm_create_recurring_rule` | `task.manage` | Create a recurring rule with RRULE validation. |
| `crm_update_recurring_rule` | `task.manage` | Update a recurring rule. |
| `crm_pause_recurring_rule` | `task.manage` | Pause a recurring rule. |
| `crm_resume_recurring_rule` | `task.manage` | Resume a recurring rule. |
| `crm_list_workflow_rules` | `settings.manage` | List automation workflow rules. |
| `crm_get_workflow_rule` | `settings.manage` | Read one workflow rule. |
| `crm_create_workflow_rule` | `settings.manage` | Create an automation workflow rule. |
| `crm_update_workflow_rule` | `settings.manage` | Update an automation workflow rule. |
| `crm_list_workflow_runs` | `settings.manage` | List workflow execution logs. |
| `crm_run_workflow_rule_test` | `settings.manage` | Run the workflow test harness for a rule. |
| `crm_list_projects` | `project.manage` | List visible projects. |
| `crm_get_project` | `project.manage` | Read one project by `public_id`. |
| `crm_list_knowledge_pages` | `knowledge.view` | List visible knowledge pages. |
| `crm_get_knowledge_page` | `knowledge.view` | Read one knowledge page by `public_id`. |
| `crm_create_knowledge_page` | `knowledge.create` | Create a knowledge page in an accessible space. |
| `crm_get_current_user` | authenticated user | Read the authenticated user profile visible to MCP. |
| `crm_list_calendar_events` | `task.manage` | List calendar events visible to the current user. |
| `crm_get_calendar_agenda` | `task.manage` | Read current user day/week agenda. |
| `crm_create_calendar_event` | `task.manage` | Create a calendar event for the current user. |
| `crm_list_milestones` | `task.manage` | List milestones for an accessible project. |
| `crm_get_milestone` | `task.manage` | Read one milestone. |
| `crm_create_milestone` | `task.manage` | Create a project milestone. |
| `crm_update_milestone` | `task.manage` | Update a project milestone. |
| `crm_list_reminders` | `task.manage` | List current-user reminders. |
| `crm_get_reminder` | `task.manage` | Read one current-user reminder. |
| `crm_create_reminder` | `task.manage` | Create a reminder for the current user. |
| `crm_update_reminder` | `task.manage` | Update a current-user reminder. |
| `crm_delete_reminder` | `task.manage` | Delete a current-user reminder. |
| `crm_list_saved_views` | `task.manage` | List saved views available to the current user. |
| `crm_get_saved_view` | `task.manage` | Read one saved view. |
| `crm_create_saved_view` | `task.manage` | Create a saved view. |
| `crm_update_saved_view` | `task.manage` | Update a saved view. |
| `crm_archive_saved_view` | `task.manage` | Archive a saved view. |
| `crm_duplicate_saved_view` | `task.manage` | Duplicate a saved view. |
| `crm_pin_saved_view` | `task.manage` | Update current-user pin preference for a saved view. |
| `crm_get_saved_view_task_filters` | `task.manage` | Resolve task filters for a saved view. |
| `crm_list_sticky_notes` | `task.manage` | List sticky notes visible to the current user. |
| `crm_get_sticky_note` | `task.manage` | Read one sticky note. |
| `crm_create_sticky_note` | `task.manage` | Create a sticky note. |
| `crm_update_sticky_note` | `task.manage` | Update a sticky note. |
| `crm_archive_sticky_note` | `task.manage` | Archive a sticky note. |
| `crm_unarchive_sticky_note` | `task.manage` | Unarchive a sticky note. |
| `crm_list_estimate_sets` | `task.manage` | List estimate sets available to the current user. |
| `crm_get_estimate_set` | `task.manage` | Read one estimate set. |
| `crm_create_estimate_set` | `task.manage` | Create an estimate set. |
| `crm_update_estimate_set` | `task.manage` | Update an estimate set. |
| `crm_list_estimate_options` | `task.manage` | List options inside an estimate set. |
| `crm_create_estimate_option` | `task.manage` | Create an estimate option. |
| `crm_update_estimate_option` | `task.manage` | Update an estimate option. |
| `crm_list_task_estimates` | `task.manage` | List estimates assigned to a visible task. |
| `crm_assign_task_estimate` | `task.manage` | Assign or update a task estimate. |
| `crm_remove_task_estimate` | `task.manage` | Remove an estimate set assignment from a task. |
| `crm_get_project_estimate_summary` | `task.manage` | Read estimate summary for a project. |
| `crm_get_cycle_estimate_summary` | `task.manage` | Read estimate summary for a work cycle/sprint. |
| `crm_get_module_estimate_summary` | `task.manage` | Read estimate summary for a project module. |
| `crm_list_custom_fields` | `task.manage` | List custom field definitions. |
| `crm_get_custom_field` | `task.manage` | Read one custom field definition. |
| `crm_create_custom_field` | `task.manage` | Create a custom field definition. |
| `crm_update_custom_field` | `task.manage` | Update a custom field definition. |
| `crm_get_custom_field_values` | `task.manage` | Read custom field values for an entity. |
| `crm_set_custom_field_values` | `task.manage` | Set custom field values for an entity. |
| `crm_list_sla_policies` | `task.manage` | List SLA policies. |
| `crm_get_sla_policy` | `task.manage` | Read one SLA policy. |
| `crm_create_sla_policy` | `task.manage` | Create an SLA policy. |
| `crm_update_sla_policy` | `task.manage` | Update an SLA policy. |
| `crm_get_sla_report` | `task.manage` | Read SLA report summary. |
| `crm_assign_sla_to_task` | `task.manage` | Assign an SLA policy to a task. |
| `crm_list_templates` | `task.manage` | List task or project templates. |
| `crm_get_template` | `task.manage` | Read one task or project template. |
| `crm_create_template` | `task.manage` | Create a task or project template. |
| `crm_update_template` | `task.manage` | Update a task or project template. |
| `crm_apply_template` | `task.manage` | Apply a task or project template. |
| `crm_list_files` | `task.manage` | List files linked to a visible task, project or knowledge page. |
| `crm_get_file` | `task.manage` | Read file metadata by public id. |
| `crm_upload_file_base64` | `task.manage` | Upload a small base64-encoded file through JSON-RPC. |
| `crm_get_file_download_info` | `task.manage` | Get a safe API download URL without exposing local storage paths. |
| `crm_delete_file` | `task.manage` | Soft-delete a file when CRM rules allow it. |
| `crm_list_statuses` | `task.manage` | List status dictionary entries. |
| `crm_get_status` | `task.manage` | Read one status dictionary entry. |
| `crm_create_status` | `task.manage` | Create a status dictionary entry. |
| `crm_update_status` | `task.manage` | Update safe status fields. |
| `crm_list_tags` | `task.manage` | List tags. |
| `crm_get_tag` | `task.manage` | Read one tag. |
| `crm_create_tag` | `task.manage` | Create a tag. |
| `crm_update_tag` | `task.manage` | Update safe tag fields. |
| `crm_list_task_tags` | `task.manage` | List tags attached to a visible task. |
| `crm_attach_task_tag` | `task.manage` | Attach a tag to a visible task. |
| `crm_detach_task_tag` | `task.manage` | Detach a tag from a visible task. |
| `crm_list_task_checklists` | `task.manage` | List checklists for a visible task. |
| `crm_create_task_checklist` | `task.manage` | Create a checklist on a visible task. |
| `crm_update_checklist` | `task.manage` | Update a checklist title. |
| `crm_list_checklist_items` | `task.manage` | List checklist items. |
| `crm_create_checklist_item` | `task.manage` | Create a checklist item. |
| `crm_update_checklist_item` | `task.manage` | Update checklist item text, order or completion state. |
| `crm_list_dependencies` | `task.manage` | List visible task dependencies. |
| `crm_create_dependency` | `task.manage` | Create a dependency between visible tasks. |
| `crm_list_worklogs` | `task.manage` | List time tracking entries visible to the current user. |
| `crm_get_worklog` | `task.manage` | Read one time tracking entry by `public_id`. |
| `crm_create_worklog` | `task.manage` | Log spent time against a task or as a standalone work entry. |
| `crm_update_worklog` | `task.manage` | Update a visible time tracking entry. |
| `crm_get_worklog_summary` | `task.manage` | Read time tracking totals grouped by day. |
| `crm_list_ideas` | authenticated user | List visible ideas. |
| `crm_get_idea` | authenticated user | Read one visible idea. |
| `crm_create_idea` | authenticated user | Create a new idea as the authenticated user. |
| `crm_add_idea_comment` | authenticated user | Add a comment to a visible idea. |
| `crm_list_chats` | authenticated participant | List chats where the current user is a participant. |
| `crm_list_chat_messages` | authenticated participant | Read messages from a chat where the user is a participant. |
| `crm_send_chat_message` | authenticated participant | Send a text message to a chat where the user is a participant. |
| `crm_list_notifications` | authenticated user | List current-user notifications. |
| `crm_get_notification_counters` | authenticated user | Read current-user notification counters. |
| `crm_create_notification` | authenticated user | Create a notification using existing CRM notification rules. |
| `crm_mark_notification_read` | authenticated user | Mark one current-user notification as read. |
| `crm_mark_notification_unread` | authenticated user | Mark one current-user notification as unread. |
| `crm_mark_all_notifications_read` | authenticated user | Mark all current-user notifications as read, optionally by category. |
| `crm_list_favorites` | authenticated user | List current-user favorites. |
| `crm_create_favorite` | authenticated user | Add a visible task, project or comment to favorites. |
| `crm_delete_favorite` | authenticated user | Remove a favorite by public id. |
| `crm_list_subscriptions` | authenticated user | List current-user subscriptions. |
| `crm_create_subscription` | authenticated user | Subscribe to a visible task, project or comment. |
| `crm_delete_subscription` | authenticated user | Remove a subscription by public id. |
| `crm_list_reactions` | authenticated user | List reactions on visible tasks, projects or comments. |
| `crm_add_reaction` | authenticated user | Add or update the current user reaction. |
| `crm_remove_reaction` | authenticated user | Remove a reaction by public id. |
| `crm_list_mentions` | authenticated user | List mentions visible to the current user. |
| `crm_add_mention` | authenticated user | Mention a user on a visible task, project or comment. |
| `crm_delete_mention` | authenticated user | Delete a mention by public id when CRM rules allow it. |

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

## Progress Log

- 2026-06-20: Expanded MCP coverage for project summaries, activity feed/history, knowledge spaces/pages/versions/comments/files/reviews/permissions/admin settings, chat read/write helpers, push subscriptions, and admin role matrix access.
- 2026-06-20: Kept the implementation thin over existing services and controllers; next pass should verify each new tool against the demo host and prune any overbroad payloads.
- 2026-06-20: Added knowledge base coverage for overview, spaces tree, page tree, search, recent/popular/review/outdated/favorites, page links, page tags, delete page and delete draft flows.
- 2026-06-20: Verified the new knowledge MCP tools on demo.tropatt.com with admin/adminadmin and confirmed the tree/search/tag/link calls return real data.
- 2026-06-20: Expanded knowledge MCP coverage further with create/update/archive/restore space flows, page draft read/write, page update, favorites/subscriptions, entity-page lookups, knowledge analytics/suggestions, template list/create, export/import, and knowledge-page file upload/link helpers.
