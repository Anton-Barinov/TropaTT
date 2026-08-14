# TropaTT MCP Server Reference

> Complete documentation of the MCP (Model Context Protocol) server built into TropaTT CRM.

## Overview

TropaTT CRM ships an embedded MCP server — a JSON-RPC 2.0 interface that gives AI agents and models access to CRM functionality (tasks, projects, knowledge base, chats, users, AI, and more) through a safe, controlled layer with permission checks.

| | |
|---|---|
| Protocol | JSON-RPC 2.0 |
| Transport | HTTP POST (request–response) |
| Endpoint | `POST /api/index.php?route=api/v1/mcp` |
| Protocol version | `2025-06-18` |
| Batch requests | Supported (JSON array) |
| Notifications | Supported (messages without id) |
| MCP tools | 567 |
| MCP resources | 5 |
| MCP prompts | 0 |

---

## What MCP means in this project

MCP mirrors the REST API but provides a safe layer between agents and the CRM:

- Isolates agents from direct database access
- Filters sensitive data (tokens, password hashes, API keys, local paths)
- Scopes every tool to the current user's RBAC permissions
- Returns compact machine-readable responses instead of full DB dumps
- Never exposes internal numeric IDs except public fields

---

## Transport and protocol

- **Protocol**: JSON-RPC 2.0
- **Endpoint**: `POST /api/index.php?route=api/v1/mcp`
- **Protocol version**: `2025-06-18`
- **Content-Type**: `application/json`
- **Accept**: `application/json, text/event-stream`

### Request

```json
{
    "jsonrpc": "2.0",
    "id": "unique-id",
    "method": "tools/call",
    "params": {
        "name": "tool_name",
        "arguments": {}
    }
}
```

### Batch request

```json
[
  {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"crm_get_current_user","arguments":{}}},
  {"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"crm_list_tasks","arguments":{"limit":5}}}
]
```

### Response

```json
{
    "jsonrpc": "2.0",
    "id": "unique-id",
    "result": {}
}
```

### Notification (no response)

```json
{"jsonrpc":"2.0","method":"notifications/initialized"}
```

---

## Authentication and security

### Authentication

- **Authorization: Bearer `<access_token>`**
- The same token is used as for the REST API
- User tokens and API client keys are supported

### RBAC

Every tool checks the current user's permissions; conditionally-visible tools appear in tools/list only when the user has the required permission.

### Dangerous tools (write/admin)

All write tools (create/update/delete) require the matching permission. User/role/module/core-update/cache tools are admin-only. Password change and 2FA are self-only. Impersonation is admin-only.

### Audit log

AI actions are logged via AiJobService/AiAuditService; import/export and workflow runs are also logged.

---

## Common MCP formats

### Tool list

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "result": {
        "tools": [
            {
                "name": "crm_get_current_user",
                "description": "Get the authenticated CRM user profile and permission codes visible to MCP.",
                "inputSchema": {
                    "type": "object",
                    "properties": {}
                }
            }
        ]
    }
}
```

### Tool call

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
        "name": "crm_create_task",
        "arguments": {
            "title": "New task",
            "priority": "high"
        }
    }
}
```

### Tool call response

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "{\"public_id\":\"abc123\",\"title\":\"New task\",\"status\":\"new\"}"
            }
        ]
    }
}
```

### Error

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "error": {
        "code": -32600,
        "message": "Invalid Request"
    }
}
```

### Common error codes

| Code | Reason |
|------|---------|
| `-32700` | Parse error (invalid JSON) |
| `-32600` | Invalid Request (missing method) |
| `-32601` | Method not found |
| `-32602` | Invalid params |
| `-32603` | Internal error |
| `-32002` | Resource not found |
| `-32003` | Origin validation failed (CORS) |

---

## MCP tools registry

### Profile & Auth

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_current_user` | Current user + permissions | auth | none |
| `crm_get_profile` | Current user's profile | auth | none |
| `crm_update_profile` | Update profile | auth | data change |
| `crm_get_profile_preferences` | Get profile preferences | auth | none |
| `crm_update_profile_preferences` | Update profile preferences | auth | data change |
| `crm_change_profile_password` | Change password + revoke sessions | auth | revoke sessions |
| `crm_list_security_sessions` | List security sessions | auth | none |
| `crm_revoke_security_session` | Revoke security session | auth | revoke |
| `crm_revoke_other_security_sessions` | Revoke other security sessions | auth | revoke |
| `crm_revoke_device_sessions` | Revoke sessions by fingerprint | auth | revoke |
| `crm_get_menu` | Navigation after filtering | auth | none |
| `crm_get_menu_preferences` | Get menu preferences | auth | none |
| `crm_save_menu_preferences` | Save menu preferences | auth | data change |
| `crm_get_2fa_status` | 2FA status | auth | none |
| `crm_enable_2fa` | Enable 2FA | auth | data change |
| `crm_disable_2fa` | Disable 2FA | auth | data change |
| `crm_request_password_reset` | Request password reset | auth | email |
| `crm_confirm_password_reset` | Confirm password reset | auth | password change |
| `crm_accept_invitation` | Accept invitation | auth | user creation |
| `crm_start_impersonation` | Start impersonation | user.manage | session change |
| `crm_get_impersonation_status` | Impersonation status | auth | none |
| `crm_stop_impersonation` | Stop impersonation | auth | session change |

### Search & Navigation

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_search` | Global search | knowledge.view/project.manage/task.manage | none |
| `crm_list_api_endpoints` | REST endpoints inventory | auth | none |

### Tasks

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_tasks` | List tasks | task.manage | none |
| `crm_get_task` | Get task | task.manage | none |
| `crm_create_task` | Create task | task.manage | create |
| `crm_update_task` | Update task | task.manage | update |
| `crm_delete_task` | Delete task (soft) | task.manage | delete |
| `crm_move_task` | Move task | task.manage | update |
| `crm_get_task_board` | Kanban board | task.manage | none |
| `crm_get_task_by_key` | Get task by key (TASK-123) | task.manage | none |
| `crm_bulk_update_tasks` | Bulk update | task.manage | update |
| `crm_list_task_activity` | Task change history | task.manage | none |
| `crm_list_task_comments` | List task comments | task.manage | none |
| `crm_add_task_comment` | Add task comment | task.manage | create |
| `crm_update_comment` | Update comment | task.manage | update |
| `crm_delete_comment` | Delete comment | task.manage | delete |
| `crm_get_comment_draft` | Task comment draft | task.manage | none |
| `crm_save_comment_draft` | Save comment draft | task.manage | data change |
| `crm_delete_comment_draft` | Delete comment draft | task.manage | delete |
| `crm_list_subtasks` | List subtasks | task.manage | none |
| `crm_create_subtask` | Create subtask | task.manage | create |
| `crm_update_subtask` | Update subtask | task.manage | update |
| `crm_delete_subtask` | Delete subtask | task.manage | delete |
| `crm_list_task_relations` | List task relations | task.manage | none |
| `crm_create_task_relation` | Create task relation | task.manage | create |
| `crm_delete_task_relation` | Delete task relation | task.manage | delete |
| `crm_list_task_checklists` | List task checklists | task.manage | none |
| `crm_create_task_checklist` | Create task checklist | task.manage | create |
| `crm_update_checklist` | Update checklist | task.manage | update |
| `crm_list_checklist_items` | List checklist items | task.manage | none |
| `crm_create_checklist_item` | Create checklist item | task.manage | create |
| `crm_update_checklist_item` | Update checklist item | task.manage | update |
| `crm_delete_checklist` | Delete checklist | task.manage | delete |
| `crm_delete_checklist_item` | Delete checklist item | task.manage | delete |
| `crm_list_task_tags` | List task tags | task.manage | none |
| `crm_attach_task_tag` | Attach task tag | task.manage | create |
| `crm_detach_task_tag` | Detach task tag | task.manage | delete |
| `crm_list_dependencies` | List dependencies | task.manage | none |
| `crm_create_dependency` | Create dependency | task.manage | create |
| `crm_delete_dependency` | Delete dependency | task.manage | delete |

### Projects

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_projects` | List projects | project.manage | none |
| `crm_get_project` | Get project | project.manage | none |
| `crm_get_project_summary` | Project summary | project.manage | none |
| `crm_get_project_timeline` | Get project timeline | project.manage | none |
| `crm_get_project_milestones_summary` | Project milestones | project.manage | none |
| `crm_get_project_risks` | Project risks | project.manage | none |
| `crm_get_project_workload` | Project workload | project.manage | none |
| `crm_create_project` | Create project | project.manage | create |
| `crm_update_project` | Update project | project.manage | update |
| `crm_delete_project` | Delete project | project.manage | delete |
| `crm_list_project_modules` | List project modules | project.manage | none |
| `crm_get_project_module` | Get project module | project.manage | none |
| `crm_create_project_module` | Create project module | project.manage | create |
| `crm_update_project_module` | Update project module | project.manage | update |
| `crm_archive_project_module` | Archive project module | project.manage | update |
| `crm_delete_project_module` | Delete project module | project.manage | delete |
| `crm_list_project_module_tasks` | List project module tasks | project.manage | none |
| `crm_list_project_module_members` | List project module members | project.manage | none |
| `crm_list_project_module_links` | List project module links | project.manage | none |
| `crm_add_tasks_to_project_module` | Add tasks to project module | project.manage | create |
| `crm_add_members_to_project_module` | Add members to project module | project.manage | create |
| `crm_remove_project_module_task` | Remove project module task | project.manage | delete |
| `crm_remove_project_module_member` | Remove project module member | project.manage | delete |
| `crm_add_project_module_link` | Add project module link | project.manage | create |
| `crm_update_project_module_link` | Update project module link | project.manage | update |
| `crm_delete_project_module_link` | Delete project module link | project.manage | delete |

### Work Cycles / Sprints

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_cycles` | List cycles | task.manage | none |
| `crm_get_cycle` | Get cycle | task.manage | none |
| `crm_get_cycle_summary` | Sprint summary | task.manage | none |
| `crm_create_cycle` | Create cycle | task.manage | create |
| `crm_update_cycle` | Update cycle | task.manage | update |
| `crm_delete_cycle` | Delete cycle | task.manage | delete |
| `crm_start_cycle` | Start cycle | task.manage | status change |
| `crm_complete_cycle` | Complete cycle | task.manage | status change |
| `crm_reopen_cycle` | Reopen cycle | task.manage | status change |
| `crm_archive_cycle` | Archive cycle | task.manage | status change |
| `crm_list_cycle_tasks` | List cycle tasks | task.manage | none |
| `crm_add_tasks_to_cycle` | Add tasks to cycle | task.manage | create |
| `crm_remove_cycle_task` | Remove cycle task | task.manage | delete |
| `crm_transfer_unfinished_cycle_tasks` | Transfer unfinished cycle tasks | task.manage | update |

### Organizations, Companies, Clients, Contacts, Counterparties

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_organizations` | List organizations | organization.manage | none |
| `crm_get_organization` | Get organization | organization.manage | none |
| `crm_create_organization` | Create organization | organization.manage | create |
| `crm_update_organization` | Update organization | organization.manage | update |
| `crm_delete_organization` | Delete organization | organization.manage | delete |
| `crm_list_organization_members` | List organization members | organization.manage | none |
| `crm_add_organization_member` | Add organization member | organization.manage | create |
| `crm_remove_organization_member` | Remove organization member | organization.manage | delete |
| `crm_list_companies` | List companies | company.manage | none |
| `crm_get_company` | Get company | company.manage | none |
| `crm_create_company` | Create company | company.manage | create |
| `crm_update_company` | Update company | company.manage | update |
| `crm_delete_company` | Delete company | company.manage | delete |
| `crm_list_clients` | List clients | client.manage | none |
| `crm_get_client` | Get client | client.manage | none |
| `crm_create_client` | Create client | client.manage | create |
| `crm_update_client` | Update client | client.manage | update |
| `crm_delete_client` | Delete client | client.manage | delete |
| `crm_list_contacts` | List contacts | contact.manage | none |
| `crm_get_contact` | Get contact | contact.manage | none |
| `crm_create_contact` | Create contact | contact.manage | create |
| `crm_update_contact` | Update contact | contact.manage | update |
| `crm_delete_contact` | Delete contact | contact.manage | delete |
| `crm_list_counterparties` | List counterparties | counterparty.manage | none |
| `crm_get_counterparty` | Get counterparty | counterparty.manage | none |
| `crm_create_counterparty` | Create counterparty | counterparty.manage | create |
| `crm_update_counterparty` | Update counterparty | counterparty.manage | update |
| `crm_delete_counterparty` | Delete counterparty | counterparty.manage | delete |

### Users, Teams, Departments

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_users` | List users | user.view | none |
| `crm_get_user` | Get user | user.view | none |
| `crm_create_user` | Create user | user.manage | create |
| `crm_update_user` | Update user | user.manage | update |
| `crm_delete_user` | Delete user | user.manage | delete |
| `crm_get_user_token_info` | Token info | user.manage | none |
| `crm_rotate_user_token` | Rotate user token | user.manage | update |
| `crm_revoke_user_token` | Revoke user token | user.manage | update |
| `crm_get_user_activity` | Get user activity | user.manage | none |
| `crm_list_teams` | List teams | auth | none |
| `crm_get_team` | Get team | auth | none |
| `crm_create_team` | Create team | team.manage | create |
| `crm_update_team` | Update team | team.manage | update |
| `crm_delete_team` | Delete team | team.manage | delete |
| `crm_list_departments` | List departments | department.manage | none |
| `crm_get_department` | Get department | department.manage | none |
| `crm_create_department` | Create department | department.manage | create |
| `crm_update_department` | Update department | department.manage | update |
| `crm_delete_department` | Delete department | department.manage | delete |

### Roles & Permissions

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_roles` | List roles | role.manage/role.view | none |
| `crm_list_permissions` | List permissions | role.manage/role.view | none |
| `crm_get_role_permissions` | Get role permissions | role.manage/role.view | none |
| `crm_create_role` | Create role | role.manage | create |
| `crm_update_role` | Update role | role.manage | update |
| `crm_delete_role` | Delete role | role.manage | delete |
| `crm_set_role_permissions` | Set role permissions | role.manage | update |
| `crm_get_admin_role_matrix` | Get admin role matrix | settings.manage | none |
| `crm_update_admin_role_matrix` | Update admin role matrix | settings.manage | update |

### Approvals

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_approvals` | List approvals | approval.manage | none |
| `crm_get_approval` | Get approval | approval.manage | none |
| `crm_create_approval` | Create approval | approval.manage | create |
| `crm_approve_request` | Approve request | approval.manage | status change |
| `crm_reject_request` | Reject request | approval.manage | status change |

### Calendar, Milestones, Reminders

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_calendar_events` | List calendar events | task.manage | none |
| `crm_get_calendar_agenda` | Daily/weekly agenda | task.manage | none |
| `crm_create_calendar_event` | Create calendar event | task.manage | create |
| `crm_get_calendar_event` | Get calendar event | task.manage | none |
| `crm_update_calendar_event` | Update calendar event | task.manage | update |
| `crm_delete_calendar_event` | Delete calendar event | task.manage | delete |
| `crm_get_calendar_my_month` | Month view | task.manage | none |
| `crm_list_milestones` | List milestones | task.manage | none |
| `crm_get_milestone` | Get milestone | task.manage | none |
| `crm_create_milestone` | Create milestone | task.manage | create |
| `crm_update_milestone` | Update milestone | task.manage | update |
| `crm_delete_milestone` | Delete milestone | project.manage | delete |
| `crm_list_reminders` | List reminders | task.manage | none |
| `crm_get_reminder` | Get reminder | task.manage | none |
| `crm_create_reminder` | Create reminder | task.manage | create |
| `crm_update_reminder` | Update reminder | task.manage | update |
| `crm_delete_reminder` | Delete reminder | task.manage | delete |

### Worklogs

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_worklogs` | List worklogs | task.manage | none |
| `crm_get_worklog` | Get worklog | task.manage | none |
| `crm_create_worklog` | Create worklog | task.manage | create |
| `crm_update_worklog` | Update worklog | task.manage | update |
| `crm_delete_worklog` | Delete worklog | task.manage | delete |
| `crm_get_worklog_summary` | Daily summary | task.manage | none |
| `crm_get_worklog_earnings` | Income/expenses | task.manage | none |
| `crm_get_worklog_matrix` | Matrix (users x days) | task.manage | none |
| `crm_get_worklog_detail` | Get worklog detail | task.manage | none |
| `crm_get_worklog_task_summary` | Task summary | task.manage | none |

### Estimates

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_estimate_sets` | List estimate sets | task.manage | none |
| `crm_get_estimate_set` | Get estimate set | task.manage | none |
| `crm_create_estimate_set` | Create estimate set | task.manage | create |
| `crm_update_estimate_set` | Update estimate set | task.manage | update |
| `crm_archive_estimate_set` | Archive estimate set | task.manage | update |
| `crm_delete_estimate_set` | Delete estimate set | task.manage | delete |
| `crm_list_estimate_options` | List estimate options | task.manage | none |
| `crm_create_estimate_option` | Create estimate option | task.manage | create |
| `crm_update_estimate_option` | Update estimate option | task.manage | update |
| `crm_archive_estimate_option` | Archive estimate option | task.manage | update |
| `crm_delete_estimate_option` | Delete estimate option | task.manage | delete |
| `crm_list_task_estimates` | List task estimates | task.manage | none |
| `crm_assign_task_estimate` | Assign task estimate | task.manage | create |
| `crm_remove_task_estimate` | Remove task estimate | task.manage | delete |
| `crm_get_project_estimate_summary` | Get project estimate summary | task.manage | none |
| `crm_get_cycle_estimate_summary` | Get cycle estimate summary | task.manage | none |
| `crm_get_module_estimate_summary` | Get module estimate summary | task.manage | none |

### Custom Fields

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_custom_fields` | List custom fields | task.manage | none |
| `crm_get_custom_field` | Get custom field | task.manage | none |
| `crm_create_custom_field` | Create custom field | task.manage | create |
| `crm_update_custom_field` | Update custom field | task.manage | update |
| `crm_get_custom_field_values` | Get custom field values | task.manage | none |
| `crm_set_custom_field_values` | Set custom field values | task.manage | update |

### Templates

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_templates` | List templates | task.manage | none |
| `crm_get_template` | Get template | task.manage | none |
| `crm_create_template` | Create template | task.manage | create |
| `crm_update_template` | Update template | task.manage | update |
| `crm_apply_template` | Apply template | task.manage | entity creation |
| `crm_delete_template` | Delete template | task.manage | delete |

### Files

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_files` | List files | task.manage | none |
| `crm_get_file` | Get file | task.manage | none |
| `crm_upload_file_base64` | Upload file (base64) | task.manage | create |
| `crm_get_file_download_info` | Download URL | task.manage | none |
| `crm_delete_file` | Delete file | task.manage | delete |

### Statuses & Tags

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_statuses` | List statuses | task.manage | none |
| `crm_get_status` | Get status | task.manage | none |
| `crm_create_status` | Create status | task.manage | create |
| `crm_update_status` | Update status | task.manage | update |
| `crm_delete_status` | Delete status | task.manage | delete |
| `crm_list_tags` | List tags | task.manage | none |
| `crm_get_tag` | Get tag | task.manage | none |
| `crm_create_tag` | Create tag | task.manage | create |
| `crm_update_tag` | Update tag | task.manage | update |
| `crm_delete_tag` | Delete tag | task.manage | delete |
| `crm_list_priorities` | List priorities | task.manage | none |
| `crm_create_priority` | Create priority | task.manage | create |
| `crm_update_priority` | Update priority | task.manage | update |
| `crm_delete_priority` | Delete priority | task.manage | delete |

### SLA

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_sla_policies` | List SLA policies | task.manage | none |
| `crm_get_sla_policy` | Get SLA policy | task.manage | none |
| `crm_create_sla_policy` | Create SLA policy | task.manage | create |
| `crm_update_sla_policy` | Update SLA policy | task.manage | update |
| `crm_delete_sla_policy` | Delete SLA policy | settings.manage | delete |
| `crm_get_sla_report` | Get SLA report | task.manage | none |
| `crm_assign_sla_to_task` | Assign SLA to task | task.manage | update |

### Recurring Rules

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_recurring_rules` | List recurring rules | task.manage | none |
| `crm_get_recurring_rule` | Get recurring rule | task.manage | none |
| `crm_create_recurring_rule` | Create recurring rule | task.manage | create |
| `crm_update_recurring_rule` | Update recurring rule | task.manage | update |
| `crm_pause_recurring_rule` | Pause recurring rule | task.manage | update |
| `crm_resume_recurring_rule` | Resume recurring rule | task.manage | update |
| `crm_delete_recurring_rule` | Delete recurring rule | task.manage | delete |

### Saved Views & Sticky Notes

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_saved_views` | List saved views | task.manage | none |
| `crm_get_saved_view` | Get saved view | task.manage | none |
| `crm_create_saved_view` | Create saved view | task.manage | create |
| `crm_update_saved_view` | Update saved view | task.manage | update |
| `crm_archive_saved_view` | Archive saved view | task.manage | update |
| `crm_duplicate_saved_view` | Duplicate saved view | task.manage | create |
| `crm_pin_saved_view` | Pin saved view | task.manage | update |
| `crm_touch_saved_view` | Mark as used | task.manage | update |
| `crm_get_saved_view_task_filters` | View filters | task.manage | none |
| `crm_delete_saved_view` | Delete saved view | task.manage | delete |
| `crm_list_sticky_notes` | List sticky notes | task.manage | none |
| `crm_get_sticky_note` | Get sticky note | task.manage | none |
| `crm_create_sticky_note` | Create sticky note | task.manage | create |
| `crm_update_sticky_note` | Update sticky note | task.manage | update |
| `crm_archive_sticky_note` | Archive sticky note | task.manage | update |
| `crm_unarchive_sticky_note` | Unarchive sticky note | task.manage | update |
| `crm_delete_sticky_note` | Delete sticky note | task.manage | delete |
| `crm_convert_sticky_to_task` | Sticky note to task | task.manage | create |
| `crm_convert_sticky_to_page` | Sticky note to knowledge page | task.manage | create |
| `crm_reorder_sticky_notes` | Reorder sticky notes | task.manage | update |

### Knowledge Base

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_knowledge_overview` | Knowledge base overview | knowledge.view | none |
| `crm_list_knowledge_pages` | List knowledge pages | knowledge.view | none |
| `crm_get_knowledge_page` | Get knowledge page | knowledge.view | none |
| `crm_list_knowledge_spaces` | List knowledge spaces | knowledge.view | none |
| `crm_list_knowledge_spaces_tree` | Spaces tree | knowledge.view | none |
| `crm_get_knowledge_space` | Get knowledge space | knowledge.view | none |
| `crm_get_knowledge_tree` | Pages tree | knowledge.view | none |
| `crm_search_knowledge` | Search knowledge | knowledge.view | none |
| `crm_list_knowledge_recent` | Recent pages | knowledge.view | none |
| `crm_list_knowledge_popular` | Popular pages | knowledge.view | none |
| `crm_list_knowledge_review_queue` | Review queue | knowledge.view | none |
| `crm_list_knowledge_outdated` | Outdated pages | knowledge.view | none |
| `crm_list_knowledge_favorites` | List knowledge favorites | knowledge.view | none |
| `crm_get_knowledge_entity_pages` | Entity pages | knowledge.view | none |
| `crm_get_knowledge_suggest` | Suggestions | knowledge.view | none |
| `crm_get_knowledge_analytics` | Get knowledge analytics | knowledge.view | none |
| `crm_list_knowledge_page_versions` | List knowledge page versions | knowledge.view | none |
| `crm_get_knowledge_page_version` | Get knowledge page version | knowledge.view | none |
| `crm_diff_knowledge_page_version` | Diff knowledge page version | knowledge.view | none |
| `crm_create_knowledge_space` | Create knowledge space | knowledge.manage | create |
| `crm_update_knowledge_space` | Update knowledge space | knowledge.manage | update |
| `crm_archive_knowledge_space` | Archive knowledge space | knowledge.manage | update |
| `crm_restore_knowledge_space` | Restore knowledge space | knowledge.manage | update |
| `crm_create_knowledge_page` | Create knowledge page | knowledge.create | create |
| `crm_update_knowledge_page` | Update knowledge page | knowledge.edit | update |
| `crm_publish_knowledge_page` | Publish knowledge page | knowledge.publish | status change |
| `crm_archive_knowledge_page` | Archive knowledge page | knowledge.publish | status change |
| `crm_restore_knowledge_page` | Restore knowledge page | knowledge.publish | status change |
| `crm_request_knowledge_review` | Request knowledge review | knowledge.review | update |
| `crm_approve_knowledge_review` | Approve knowledge review | knowledge.review | status change |
| `crm_reject_knowledge_review` | Reject knowledge review | knowledge.review | status change |
| `crm_duplicate_knowledge_page` | Duplicate knowledge page | knowledge.create | create |
| `crm_move_knowledge_page` | Move knowledge page | knowledge.manage | update |
| `crm_lock_knowledge_page` | Lock knowledge page | knowledge.manage | update |
| `crm_unlock_knowledge_page` | Unlock knowledge page | knowledge.manage | update |
| `crm_lock_knowledge_page_version` | Lock knowledge page version | knowledge.manage | update |
| `crm_unlock_knowledge_page_version` | Unlock knowledge page version | knowledge.manage | update |
| `crm_delete_knowledge_page` | Delete knowledge page | knowledge.manage | delete |
| `crm_restore_knowledge_page_version` | Restore knowledge page version | knowledge.publish | update |
| `crm_list_knowledge_templates` | List knowledge templates | knowledge.view | none |
| `crm_create_knowledge_template` | Create knowledge template | knowledge.create | create |
| `crm_export_knowledge_all` | Export knowledge all | knowledge.view | none |
| `crm_export_knowledge_page` | Export knowledge page | knowledge.view | none |
| `crm_export_knowledge_space` | Export knowledge space | knowledge.view | none |
| `crm_import_knowledge_pages` | Import knowledge pages | knowledge.create | create |
| `crm_list_knowledge_comments` | List knowledge comments | knowledge.view | none |
| `crm_add_knowledge_comment` | Add knowledge comment | knowledge.comment | create |
| `crm_delete_knowledge_comment` | Delete knowledge comment | knowledge.comment | delete |
| `crm_resolve_knowledge_comment` | Resolve knowledge comment | knowledge.comment | update |
| `crm_reopen_knowledge_comment` | Reopen knowledge comment | knowledge.comment | update |
| `crm_list_knowledge_page_links` | List knowledge page links | knowledge.view | none |
| `crm_delete_knowledge_page_link` | Delete knowledge page link | knowledge.edit | delete |
| `crm_list_knowledge_page_tags` | List knowledge page tags | knowledge.view | none |
| `crm_attach_knowledge_page_tag` | Attach knowledge page tag | knowledge.edit | create |
| `crm_detach_knowledge_page_tag` | Detach knowledge page tag | knowledge.edit | delete |
| `crm_link_knowledge_page_entity` | Link knowledge page entity | knowledge.edit | create |
| `crm_list_knowledge_files` | List knowledge files | knowledge.view | none |
| `crm_upload_knowledge_file_base64` | Upload knowledge file (base64) | knowledge.edit | create |
| `crm_delete_knowledge_file` | Delete knowledge file | knowledge.delete | delete |
| `crm_get_knowledge_page_draft` | Get knowledge page draft | knowledge.edit | none |
| `crm_save_knowledge_page_draft` | Save knowledge page draft | knowledge.edit | create/update |
| `crm_delete_knowledge_draft` | Delete knowledge draft | knowledge.edit | delete |
| `crm_favorite_knowledge_page` | Add to favorites | knowledge.view | create |
| `crm_unfavorite_knowledge_page` | Remove from favorites | knowledge.view | delete |
| `crm_subscribe_knowledge_page` | Subscribe knowledge page | knowledge.view | create |
| `crm_unsubscribe_knowledge_page` | Unsubscribe knowledge page | knowledge.view | delete |
| `crm_get_knowledge_space_permissions` | Get knowledge space permissions | knowledge.manage | none |
| `crm_add_knowledge_space_permission` | Add knowledge space permission | knowledge.manage | create |
| `crm_remove_knowledge_space_permission` | Remove knowledge space permission | knowledge.manage | delete |
| `crm_get_knowledge_page_permissions` | Get knowledge page permissions | knowledge.manage | none |
| `crm_add_knowledge_page_permission` | Add knowledge page permission | knowledge.manage | create |
| `crm_remove_knowledge_page_permission` | Remove knowledge page permission | knowledge.manage | delete |
| `crm_get_admin_knowledge_settings` | Get admin knowledge settings | settings.manage | none |
| `crm_update_admin_knowledge_settings` | Update admin knowledge settings | settings.manage | update |
| `crm_reindex_knowledge` | Reindex knowledge | settings.manage | operation |
| `crm_rebuild_knowledge_permissions` | Rebuild knowledge permissions | settings.manage | operation |
| `crm_cleanup_knowledge_drafts` | Cleanup knowledge drafts | settings.manage | delete |

### Knowledge AI

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_create_knowledge_ai_summary` | AI page summary | knowledge.view | AI call |
| `crm_create_knowledge_ai_explanation` | AI page explanation | knowledge.view | AI call |
| `crm_find_knowledge_ai_similar` | Find similar pages | knowledge.view | AI call |
| `crm_create_knowledge_ai_checklist` | AI checklist | knowledge.view | AI call |
| `crm_create_knowledge_ai_faq_from_comments` | AI FAQ from comments | knowledge.view | AI call |
| `crm_create_knowledge_ai_suggest_for_task` | Suggestions for a task | knowledge.view | AI call |
| `crm_find_knowledge_ai_duplicates` | Find duplicates | knowledge.manage | AI call |
| `crm_find_knowledge_ai_orphans` | Find orphans | knowledge.manage | AI call |
| `crm_suggest_knowledge_ai_structure` | Suggest structure | knowledge.manage | AI call |

### Ideas

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_ideas` | List ideas | idea.manage | none |
| `crm_get_idea` | Get idea | idea.manage | none |
| `crm_create_idea` | Create idea | idea.manage | create |
| `crm_update_idea` | Update idea | idea.manage | update |
| `crm_delete_idea` | Delete idea | idea.manage | delete |
| `crm_vote_idea` | Vote idea | idea.manage | create/delete |
| `crm_update_idea_status` | Update idea status | idea.manage | update |
| `crm_list_idea_comments` | List idea comments | idea.manage | none |
| `crm_add_idea_comment` | Add idea comment | idea.manage | create |

### Chats

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_chats` | List chats | project.manage/task.manage | none |
| `crm_get_chat` | Get chat | auth | none |
| `crm_create_chat` | Create chat | auth | create |
| `crm_get_chat_participants` | Get chat participants | auth | none |
| `crm_list_chat_messages` | List chat messages | project.manage/task.manage | none |
| `crm_send_chat_message` | Send chat message | project.manage/task.manage | create |
| `crm_edit_chat_message` | Edit chat message | auth | update |
| `crm_delete_chat_message` | Delete chat message | auth | delete |
| `crm_upload_chat_attachment` | Upload chat attachment | auth | create |
| `crm_download_chat_attachment` | Download chat attachment | auth | none |
| `crm_list_chat_attachments` | List chat attachments | auth | none |
| `crm_get_chat_settings` | Get chat settings | auth | none |
| `crm_update_chat_settings` | Update chat settings | auth | update |
| `crm_mark_chat_read` | Mark chat read | auth | update |
| `crm_get_chat_unread_count` | Get chat unread count | auth | none |
| `crm_archive_chat` | Archive chat | auth | update |
| `crm_restore_chat` | Restore chat | auth | update |

### Notifications

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_notifications` | List notifications | auth | none |
| `crm_get_notification_counters` | Get notification counters | auth | none |
| `crm_create_notification` | Create notification | settings.manage | create |
| `crm_mark_notification_read` | Mark notification read | auth | update |
| `crm_mark_notification_unread` | Mark notification unread | auth | update |
| `crm_mark_all_notifications_read` | Mark all notifications read | auth | update |

### Push Subscriptions

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_push_subscriptions` | List push subscriptions | auth | none |
| `crm_create_push_subscription` | Create push subscription | auth | create |
| `crm_delete_push_subscription` | Delete push subscription | auth | delete |
| `crm_send_push_test` | Send push test | auth | send |

### Favorites, Subscriptions, Reactions, Mentions

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_favorites` | List favorites | auth | none |
| `crm_create_favorite` | Create favorite | auth | create |
| `crm_delete_favorite` | Delete favorite | auth | delete |
| `crm_list_subscriptions` | List subscriptions | auth | none |
| `crm_create_subscription` | Create subscription | auth | create |
| `crm_delete_subscription` | Delete subscription | auth | delete |
| `crm_list_reactions` | List reactions | auth | none |
| `crm_add_reaction` | Add reaction | auth | create |
| `crm_remove_reaction` | Remove reaction | auth | delete |
| `crm_list_mentions` | List mentions | auth | none |
| `crm_add_mention` | Add mention | project.manage/task.manage | create |
| `crm_delete_mention` | Delete mention | project.manage/task.manage | delete |

### Activity Feed

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_activity_feed` | Get activity feed | logs.view/project.manage/task.manage | none |
| `crm_get_activity_history` | Get activity history | logs.view/project.manage/task.manage | none |

### Client Cabinet

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_client_cabinet_projects` | List client cabinet projects | client.manage | none |
| `crm_get_client_cabinet_project` | Get client cabinet project | client.manage | none |
| `crm_list_client_cabinet_project_tasks` | List client cabinet project tasks | client.manage | none |

### Intake

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_intake_items` | List intake items | intake.manage/intake.view | none |
| `crm_get_intake_item` | Get intake item | intake.manage/intake.view | none |
| `crm_create_intake_item` | Create intake item | intake.create/intake.manage | create |
| `crm_update_intake_item` | Update intake item | intake.manage | update |
| `crm_delete_intake_item` | Delete intake item | intake.delete/intake.manage | delete |
| `crm_accept_intake_item` | Accept intake item | intake.accept/intake.manage | create |
| `crm_reject_intake_item` | Reject intake item | intake.manage | update |
| `crm_snooze_intake_item` | Snooze intake item | intake.manage | update |
| `crm_duplicate_intake_item` | Duplicate intake item | intake.manage | update |
| `crm_reopen_intake_item` | Reopen intake item | intake.manage | update |

### Webhooks

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_webhooks` | List webhooks | webhook.manage | none |
| `crm_list_webhook_deliveries` | List webhook deliveries | webhook.manage | none |
| `crm_create_webhook` | Create webhook | webhook.manage | create |
| `crm_update_webhook` | Update webhook | webhook.manage | update |
| `crm_delete_webhook` | Delete webhook | webhook.manage | delete |
| `crm_test_webhook` | Test webhook | webhook.manage | send |

### Workflow Rules

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_workflow_rules` | List workflow rules | settings.manage | none |
| `crm_get_workflow_rule` | Get workflow rule | settings.manage | none |
| `crm_create_workflow_rule` | Create workflow rule | settings.manage | create |
| `crm_update_workflow_rule` | Update workflow rule | settings.manage | update |
| `crm_delete_workflow_rule` | Delete workflow rule | settings.manage | delete |
| `crm_list_workflow_runs` | List workflow runs | settings.manage | none |
| `crm_run_workflow_rule_test` | Run workflow rule test | settings.manage | execute |

### AI

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_ai_settings` | Global AI settings | settings.manage | none |
| `crm_update_ai_settings` | Update AI settings | settings.manage | update |
| `crm_get_ai_preferences` | Get AI preferences | ai.use | none |
| `crm_update_ai_preferences` | Update AI preferences | ai.use | update |
| `crm_get_ai_availability` | Get AI availability | ai.use | none |
| `crm_list_ai_action_types` | List AI action types | ai.use | none |
| `crm_execute_ai_action` | Execute AI action | ai.use | AI call |
| `crm_list_ai_providers` | List AI providers | settings.manage | none |
| `crm_get_ai_provider` | Get AI provider | settings.manage | none |
| `crm_list_ai_models` | List AI models | settings.manage | none |
| `crm_list_ai_intents` | List AI intents | settings.manage | none |
| `crm_update_ai_intent` | Update AI intent | settings.manage | update |
| `crm_list_ai_prompts` | List AI prompts | settings.manage | none |
| `crm_create_ai_prompt` | Create AI prompt | settings.manage | create |
| `crm_update_ai_prompt` | Update AI prompt | settings.manage | update |
| `crm_list_ai_json_schemas` | List AI JSON schemas | settings.manage | none |
| `crm_create_ai_json_schema` | Create AI JSON schema | settings.manage | create |
| `crm_update_ai_json_schema` | Update AI JSON schema | settings.manage | update |
| `crm_list_ai_usage` | List AI usage | settings.manage | none |
| `crm_list_ai_audit` | List AI audit | settings.manage | none |
| `crm_list_ai_jobs` | List AI jobs | settings.manage | none |
| `crm_get_ai_job` | Get AI job | settings.manage | none |
| `crm_retry_ai_job` | Retry AI job | settings.manage | operation |
| `crm_dry_run_ai_job` | Dry-run AI job | settings.manage | none |
| `crm_run_once_ai_job` | Run AI job once | settings.manage | execute |
| `crm_search_ai_semantic` | Semantic search | settings.manage | AI call |
| `crm_list_ai_retention_policies` | List AI retention policies | settings.manage | none |
| `crm_list_ai_suggestions` | List AI suggestions | ai.use | none |
| `crm_get_ai_suggestion` | Get AI suggestion | ai.use | none |
| `crm_dismiss_ai_suggestion` | Dismiss suggestion | ai.use | update |
| `crm_preview_apply_ai_suggestion` | Preview suggestion | ai.use | none |
| `crm_confirm_ai_suggestion` | Apply suggestion | ai.use | update |
| `crm_create_ai_dashboard_digest` | AI dashboard digest | ai.use | AI call |
| `crm_create_ai_my_day_plan` | AI day plan | ai.use | AI call |
| `crm_create_ai_my_week_plan` | AI week plan | ai.use | AI call |
| `crm_create_ai_task_summary` | AI task summary | task.manage | AI call |
| `crm_create_ai_task_next_action` | AI next action | task.manage | AI call |
| `crm_create_ai_task_decomposition` | AI task decomposition | task.manage | AI call |
| `crm_create_ai_task_checklist` | AI task checklist | task.manage | AI call |
| `crm_create_ai_task_quality` | AI quality review | task.manage | AI call |
| `crm_create_ai_project_summary` | AI project summary | project.manage | AI call |
| `crm_create_ai_project_risks` | AI project risks | project.manage | AI call |
| `crm_create_ai_analytics_kpi_explanation` | AI KPI explanation | ai.use | AI call |
| `crm_create_ai_analytics_risks_explanation` | AI risks explanation | ai.use | AI call |
| `crm_create_ai_analytics_team_workload_summary` | AI team workload summary | ai.use | AI call |

### Analytics

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_analytics_summary` | Get analytics summary | analytics.view/task.manage | none |
| `crm_list_analytics_projects` | List analytics projects | analytics.view/task.manage | none |
| `crm_list_analytics_users` | List analytics users | analytics.view/task.manage | none |

### Dashboard

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_get_dashboard_summary` | Get dashboard summary | auth | none |
| `crm_get_health_status` | Lightweight health status | auth | none |
| `crm_get_dashboard_widgets` | Widget catalog and active widgets | auth | none |
| `crm_save_dashboard_widgets` | Save widget layout | auth | data change |

### Admin

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_settings` | List settings | settings.manage | none |
| `crm_get_setting` | Get setting | settings.manage | none |
| `crm_get_retention_metadata` | Get retention metadata | settings.manage | none |
| `crm_set_retention_metadata` | Set retention metadata | settings.manage | update |
| `crm_list_feature_flags` | List feature flags | settings.manage | none |
| `crm_update_feature_flag` | Update feature flag | settings.manage | update |
| `crm_list_modules` | List modules | settings.manage | none |
| `crm_get_module` | Get module | settings.manage | none |
| `crm_install_module` | Install module | settings.manage | install |
| `crm_activate_module` | Activate module | settings.manage | update |
| `crm_deactivate_module` | Deactivate module | settings.manage | update |
| `crm_uninstall_module` | Uninstall module | settings.manage | delete |
| `crm_get_module_config` | Get module config | settings.manage | none |
| `crm_update_module_config` | Update module config | settings.manage | update |
| `crm_get_module_health` | Get module health | settings.manage | none |
| `crm_get_module_migrations` | Get module migrations | settings.manage | none |
| `crm_get_module_errors` | Get module errors | settings.manage | none |
| `crm_clear_module_errors` | Clear module errors | settings.manage | delete |
| `crm_install_module_from_url` | Install module from URL | settings.manage | install |
| `crm_install_module_from_file` | Install module from file | settings.manage | install |
| `crm_get_cache_stats` | Get cache stats | settings.manage | none |
| `crm_clear_cache` | Clear cache | settings.manage | delete |
| `crm_get_ops_system` | System snapshot | settings.manage | none |
| `crm_get_ops_metrics` | Get ops metrics | settings.manage | none |
| `crm_run_ops_jobs` | Run queues | settings.manage | execute |
| `crm_get_core_version` | Get core version | settings.manage | none |
| `crm_get_core_update_status` | Get core update status | settings.manage | none |
| `crm_check_core_update` | Check for update | settings.manage | none |
| `crm_run_core_update_preflight` | Update preflight | settings.manage | none |
| `crm_get_core_update_changes` | Get core update changes | settings.manage | none |
| `crm_get_core_update_session` | Update session | settings.manage | create |
| `crm_get_core_update_history` | Get core update history | settings.manage | none |
| `crm_get_core_update_log` | Get core update log | settings.manage | none |

### Logs & Audit

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_audit_log` | List audit log | logs.view/settings.manage | none |
| `crm_list_security_log` | List security log | logs.view/settings.manage | none |
| `crm_list_request_logs` | List request logs | logs.view | none |
| `crm_get_frontend_errors_chart` | Frontend errors chart | logs.view | none |
| `crm_get_admin_summary_widget` | Summary widget | logs.view | none |
| `crm_get_admin_system_widget` | System widget | logs.view | none |
| `crm_get_openapi_spec` | OpenAPI spec | logs.view | none |

### API Clients & Keys

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_api_clients` | List API clients | api_client.manage/api_client.view | none |
| `crm_get_api_client` | Get API client | api_client.manage/api_client.view | none |
| `crm_list_api_client_keys` | List API client keys | api_client.manage/api_client.view | none |
| `crm_create_api_client` | Create API client | api_client.manage | create |
| `crm_update_api_client` | Update API client | api_client.manage | update |
| `crm_delete_api_client` | Delete API client | api_client.manage | delete |
| `crm_issue_api_client_key` | Issue API client key | api_client.manage | create |
| `crm_rotate_api_key` | Rotate API key | api_client.manage | update |
| `crm_revoke_api_key` | Revoke API key | api_client.manage | update |
| `crm_get_api_key_usage` | Key usage | api_client.view | none |

### Import / Export

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_import_jobs` | List import jobs | import.manage | none |
| `crm_get_import_job` | Get import job | import.manage | none |
| `crm_create_import_job` | Create import job | import.manage | create |
| `crm_cancel_import_job` | Cancel import job | import.manage | update |
| `crm_retry_import_job` | Retry import job | import.manage | execute |
| `crm_list_export_jobs` | List export jobs | export.manage | none |
| `crm_get_export_job` | Get export job | export.manage | none |
| `crm_create_export_job` | Create export job | export.manage | create |
| `crm_cancel_export_job` | Cancel export job | export.manage | update |
| `crm_retry_export_job` | Retry export job | export.manage | execute |
| `crm_download_export_job` | Download export job | export.manage | none |

### Recycle Bin

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_recycle_bin` | List recycle bin | recycle_bin.manage | none |
| `crm_restore_recycle_bin_item` | Restore recycle bin item | recycle_bin.manage | restore |
| `crm_purge_recycle_bin_item` | Purge permanently | recycle_bin.manage | delete |

### Business Calendars

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_business_calendars` | List business calendars | settings.manage | none |
| `crm_create_business_calendar` | Create business calendar | settings.manage | create |
| `crm_get_business_calendar` | Get business calendar | settings.manage | none |
| `crm_update_business_calendar` | Update business calendar | settings.manage | update |
| `crm_delete_business_calendar` | Delete business calendar | settings.manage | delete |
| `crm_list_holidays` | List holidays | settings.manage | none |
| `crm_create_holiday` | Create holiday | settings.manage | create |
| `crm_get_holiday` | Get holiday | settings.manage | none |
| `crm_update_holiday` | Update holiday | settings.manage | update |
| `crm_delete_holiday` | Delete holiday | settings.manage | delete |
| `crm_list_working_hours` | List working hours | settings.manage | none |
| `crm_create_working_hours` | Create working hours | settings.manage | create |
| `crm_get_working_hours` | Get working hours | settings.manage | none |
| `crm_update_working_hours` | Update working hours | settings.manage | update |
| `crm_delete_working_hours` | Delete working hours | settings.manage | delete |

### Invitations

| Tool | Purpose | Permission | Side effects |
|------|-----------|------------|--------------|
| `crm_list_invitations` | List invitations | user.manage | none |
| `crm_create_invitation` | Create invitation | user.manage | email |

---

## MCP resources

MCP resources are read-only and served under the tropatt:// URI scheme.

| Resource URI | Purpose | MIME | Auth | Priority |
|-------------|-----------|------|------|-----------|
| ``tropatt://server/about`` | MCP server overview | text/markdown | auth | 1.0 |
| ``tropatt://server/tools`` | List of available tools | application/json | auth | 0.95 |
| ``tropatt://user/current`` | Current user | application/json | auth | 0.9 |
| ``tropatt://server/api-map`` | API domain map | text/markdown | auth | 0.8 |
| ``tropatt://server/api-endpoints`` | REST endpoints inventory | application/json | auth | 0.75 |

---

## MCP prompts

Prompts are not supported in the current MCP version.

---

## Dangerous MCP tools

> All write tools (create/update/delete) require the matching permission. User/role/module/core-update/cache tools are admin-only. Password change and 2FA are self-only. Impersonation is admin-only.

| Tool | What it does | Why it is dangerous | Audit |
|------|-------------|-------------------|-------|
| `crm_start_impersonation` | Impersonate another user | Full access under someone else's identity | yes |
| `crm_create_user` | Create a user | Creates an account | yes |
| `crm_update_user` | Update a user (password, roles, root) | Changes access rights | yes |
| `crm_delete_user` | Delete a user | Account loss | yes |
| `crm_rotate_user_token` | Rotate API token | New token, old one stops working | yes |
| `crm_set_role_permissions` | Set role permissions | Changes RBAC | yes |
| `crm_create_role` | Create a role | New role | yes |
| `crm_delete_role` | Delete a role | Role loss | yes |
| `crm_install_module` | Install a module | Code on the server | yes |
| `crm_uninstall_module` | Uninstall a module | Module data loss | yes |
| `crm_install_module_from_url` | Install from URL | External code | yes |
| `crm_install_module_from_file` | Install from file | External code | yes |
| `crm_clear_cache` | Clear cache | Can disrupt operation | yes |
| `crm_run_ops_jobs` | Run queues | Executes jobs | yes |
| `crm_run_core_update_preflight` | Update preflight | Prepares an update | yes |
| `crm_purge_recycle_bin_item` | Purge permanently | Irreversible deletion | yes |
| `crm_execute_ai_action` | Execute AI action | AI can change data | yes |
| `crm_create_import_job` | Import data | Bulk data changes | yes |
| `crm_create_webhook` | Create a webhook | External calls | yes |
| `crm_change_profile_password` | Change password | Revokes all sessions | yes |
| `crm_enable_2fa` | Enable 2FA | Lockout without app | yes |
| `crm_request_password_reset` | Request password reset | Email with token | yes |

---

## MCP → REST API mapping

MCP mirrors the REST API through a safe layer. Below is the mapping of key tools to REST endpoints, plus items that exist only in MCP or only in REST.

| MCP Tool | Type | REST Endpoint | Controller | Differences |
|----------|-----|---------------|------------|---------|
| `crm_search` | tool | `GET /api/v1/search` | SearchController | Compact response |
| `crm_list_tasks` | tool | `GET /api/v1/tasks` | TaskController::list | Simplified filters |
| `crm_get_task` | tool | `GET /api/v1/tasks/{id}` | TaskController::get | No internal IDs |
| `crm_create_task` | tool | `POST /api/v1/tasks` | TaskController::create | Creator = current user |
| `crm_update_task` | tool | `PATCH /api/v1/tasks/{id}` | TaskController::update | No sensitive fields |
| `crm_delete_task` | tool | `DELETE /api/v1/tasks/{id}` | TaskController::delete | Soft delete |
| `crm_list_projects` | tool | `GET /api/v1/projects` | ProjectController::list | - |
| `crm_get_project` | tool | `GET /api/v1/projects/{id}` | ProjectController::get | - |
| `crm_create_project` | tool | `POST /api/v1/projects` | ProjectController::create | - |
| `crm_list_users` | tool | `GET /api/v1/users` | UserController::list | No passwords/tokens |
| `crm_get_user` | tool | `GET /api/v1/users/{id}` | UserController::get | No passwords |
| `crm_list_chats` | tool | `GET /api/v1/chats` | ChatController::list | - |
| `crm_send_chat_message` | tool | `POST /api/v1/chats/{id}/messages` | ChatController::sendMessage | - |
| `crm_list_knowledge_pages` | tool | `GET /api/v1/knowledge/pages` | KnowledgeController::list | - |
| `crm_create_knowledge_page` | tool | `POST /api/v1/knowledge/pages` | KnowledgeController::create | - |
| `crm_list_api_endpoints` | tool | `none` | McpController::apiEndpointsIndex | MCP-only, reads routes.php |

---

## MCP-only items (no REST counterpart)

| Tool | Purpose | Note |
|------|-----------|-------------|
| `crm_list_api_endpoints` | REST endpoints inventory | Returns the full route list from routes.php |
| `crm_get_knowledge_overview` | Knowledge base overview | Aggregated summary |
| `crm_get_knowledge_tree` | Pages tree | Recursive structure |
| `crm_get_knowledge_suggest` | Page suggestions | AI-powered |
| `crm_get_knowledge_analytics` | Knowledge base analytics | Aggregated metrics |
| `crm_create_knowledge_ai_summary` | AI page summary | AI-powered |
| `crm_create_knowledge_ai_explanation` | AI explanation | AI-powered |
| `crm_find_knowledge_ai_similar` | Similar pages | AI-powered semantic |
| `crm_create_knowledge_ai_checklist` | AI checklist | AI-powered |
| `crm_create_knowledge_ai_faq_from_comments` | AI FAQ from comments | AI-powered |
| `crm_create_knowledge_ai_suggest_for_task` | Suggestions for a task | AI-powered |
| `crm_find_knowledge_ai_duplicates` | Duplicates | AI-powered |
| `crm_find_knowledge_ai_orphans` | Orphans | AI-powered |
| `crm_suggest_knowledge_ai_structure` | Suggest structure | AI-powered |
| `crm_get_project_summary` | Project summary | Aggregated data |
| `crm_get_project_timeline` | Project timeline | Gantt-like |
| `crm_get_project_risks` | Project risks | Aggregated data |
| `crm_get_project_workload` | Project workload | Aggregated data |
| `crm_get_cycle_summary` | Sprint summary | Aggregated data |
| `crm_get_task_board` | Kanban board | Grouped by status |
| `crm_get_activity_feed` | Activity feed | Aggregated feed |
| `crm_get_activity_history` | Activity history | Per entity |
| `crm_get_dashboard_summary` | Dashboard summary | Aggregated counters |
| `crm_execute_ai_action` | Execute AI action | AI-powered |
| `crm_search_ai_semantic` | Semantic search | AI-powered |
| `crm_create_ai_dashboard_digest` | AI digest | AI-powered |
| `crm_create_ai_my_day_plan` | AI day plan | AI-powered |
| `crm_create_ai_my_week_plan` | AI week plan | AI-powered |
| `crm_create_ai_task_summary` | AI task summary | AI-powered |
| `crm_create_ai_task_next_action` | AI next action | AI-powered |
| `crm_create_ai_task_decomposition` | AI task decomposition | AI-powered |
| `crm_create_ai_task_checklist` | AI task checklist | AI-powered |
| `crm_create_ai_task_quality` | AI quality review | AI-powered |
| `crm_create_ai_project_summary` | AI project summary | AI-powered |
| `crm_create_ai_project_risks` | AI project risks | AI-powered |
| `crm_create_ai_analytics_kpi_explanation` | AI KPI explanation | AI-powered |
| `crm_create_ai_analytics_risks_explanation` | AI risks explanation | AI-powered |
| `crm_create_ai_analytics_team_workload_summary` | AI team workload summary | AI-powered |
| `crm_get_saved_view_task_filters` | View filters | Simplified format |
| `crm_get_file_download_info` | Download URL | No local paths |
| `crm_download_chat_attachment` | Download attachment | No local paths |
| `crm_download_export_job` | Download export | No local paths |

---

## REST endpoints without an MCP counterpart

| REST Endpoint | Purpose | MCP coverage needed | Note |
|--------------|-----------|-------------------|-------------|
| `POST /api/v1/auth/login` | Login | No | MCP uses a bearer token |
| `POST /api/v1/auth/logout` | Logout | No | Sessions are managed via REST |
| `GET /api/v1/install/status` | Install status | No | Installer only |
| `POST /api/v1/internal/migration/*` | Migrations | No | Internal operations |
| `POST /api/v1/system/core-update/*` | Core update | Partial | MCP: crm_run_core_update_preflight, crm_get_core_update_session |
| `GET /api/v1/export/download/{id}` | Export download | Yes | MCP: crm_download_export_job |
| `POST /api/v1/file/download/{id}` | File download | Yes | MCP: crm_get_file_download_info |
| `POST /api/v1/file/upload` | File upload | Yes | MCP: crm_upload_file_base64 |

---

* Data source: McpController.php and the mcp_permissions.php permission registry. Documentation is kept in sync with the code.
