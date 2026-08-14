# TropaTT MCP 服务器参考

> TropaTT CRM 内置 MCP（Model Context Protocol）服务器的完整文档。

## 概述

TropaTT CRM 内置一个 MCP 服务器——一个 JSON-RPC 2.0 接口，通过带权限检查的安全、受控层，让 AI 代理和模型访问 CRM 功能（任务、项目、知识库、聊天、用户、AI 等）。

| | |
|---|---|
| 协议 | JSON-RPC 2.0 |
| 传输 | HTTP POST (request–response) |
| Endpoint | `POST /api/index.php?route=api/v1/mcp` |
| 协议版本 | `2025-06-18` |
| 批量请求 | 支持（JSON 数组） |
| 通知 | 支持（不带 id 的消息） |
| MCP 工具 | 567 |
| MCP 资源 | 5 |
| MCP Prompts | 0 |

---

## 本项目中 MCP 的作用

MCP 镜像 REST API，但在代理与 CRM 之间提供安全层：

- 隔离代理，避免直接访问数据库
- 过滤敏感数据（令牌、密码哈希、API 密钥、本地路径）
- 每个工具都受当前用户 RBAC 权限约束
- 返回紧凑的机器可读响应，而非完整数据库转储
- 除公开字段外，绝不暴露内部数字 ID

---

## 传输与协议

- **Protocol**: JSON-RPC 2.0
- **Endpoint**: `POST /api/index.php?route=api/v1/mcp`
- **Protocol version**: `2025-06-18`
- **Content-Type**: `application/json`
- **Accept**: `application/json, text/event-stream`

### 请求

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

### 批量请求

```json
[
  {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"crm_get_current_user","arguments":{}}},
  {"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"crm_list_tasks","arguments":{"limit":5}}}
]
```

### 响应

```json
{
    "jsonrpc": "2.0",
    "id": "unique-id",
    "result": {}
}
```

### 通知（无响应）

```json
{"jsonrpc":"2.0","method":"notifications/initialized"}
```

---

## 认证与安全

### 认证

- **Authorization: Bearer `<access_token>`**
- 使用与 REST API 相同的令牌
- 支持用户令牌和 API 客户端密钥

### RBAC

每个工具都会检查当前用户的权限；条件可见的工具只有在用户具备相应权限时才会出现在 tools/list 中。

### 危险工具（写/管理）

所有写工具（create/update/delete）都需要相应权限。用户/角色/模块/核心更新/缓存相关工具仅限管理员。密码修改和 2FA 仅限本人。Impersonation 仅限管理员。

### 审计日志

AI 操作通过 AiJobService/AiAuditService 记录；导入/导出和工作流运行也会记录。

---

## 通用 MCP 格式

### 工具列表

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

### 工具调用

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

### 工具调用响应

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

### 错误

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

### 常见错误码

| Code | 原因 |
|------|---------|
| `-32700` | 解析错误（无效 JSON） |
| `-32600` | 无效请求（缺少 method） |
| `-32601` | 方法不存在 |
| `-32602` | 无效参数 |
| `-32603` | 内部错误 |
| `-32002` | 资源不存在 |
| `-32003` | 来源校验失败（CORS） |

---

## MCP 工具注册表

### 个人资料与认证

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_get_current_user` | 当前用户 + 权限 | auth | 无 |
| `crm_get_profile` | 当前用户的个人资料 | auth | 无 |
| `crm_update_profile` | 更新个人资料 | auth | 数据变更 |
| `crm_get_profile_preferences` | 获取个人资料偏好设置 | auth | 无 |
| `crm_update_profile_preferences` | 更新个人资料偏好设置 | auth | 数据变更 |
| `crm_change_profile_password` | 更改密码 + 撤销会话 | auth | 撤销会话 |
| `crm_list_security_sessions` | 列出安全会话 | auth | 无 |
| `crm_revoke_security_session` | 撤销安全会话 | auth | 撤销 |
| `crm_revoke_other_security_sessions` | 撤销其他安全会话 | auth | 撤销 |
| `crm_revoke_device_sessions` | 按指纹撤销会话 | auth | 撤销 |
| `crm_get_menu` | 筛选后的导航 | auth | 无 |
| `crm_get_menu_preferences` | 获取菜单偏好设置 | auth | 无 |
| `crm_save_menu_preferences` | 保存菜单偏好设置 | auth | 数据变更 |
| `crm_get_2fa_status` | 2FA 状态 | auth | 无 |
| `crm_enable_2fa` | 启用 2FA | auth | 数据变更 |
| `crm_disable_2fa` | 禁用 2FA | auth | 数据变更 |
| `crm_request_password_reset` | 请求密码重置 | auth | 邮件 |
| `crm_confirm_password_reset` | 确认密码重置 | auth | 密码变更 |
| `crm_accept_invitation` | 接受邀请 | auth | 创建用户 |
| `crm_start_impersonation` | 开始模拟身份 | user.manage | 会话变更 |
| `crm_get_impersonation_status` | 模拟身份状态 | auth | 无 |
| `crm_stop_impersonation` | 停止模拟身份 | auth | 会话变更 |

### 搜索与导航

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_search` | 全局搜索 | knowledge.view/project.manage/task.manage | 无 |
| `crm_list_api_endpoints` | REST 端点清单 | auth | 无 |

### 任务

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_tasks` | 列出任务 | task.manage | 无 |
| `crm_get_task` | 获取任务 | task.manage | 无 |
| `crm_create_task` | 创建任务 | task.manage | 创建 |
| `crm_update_task` | 更新任务 | task.manage | 更新 |
| `crm_delete_task` | 删除任务（软删除） | task.manage | 删除 |
| `crm_move_task` | 移动任务 | task.manage | 更新 |
| `crm_get_task_board` | 看板 | task.manage | 无 |
| `crm_get_task_by_key` | 按编号获取任务（TASK-123） | task.manage | 无 |
| `crm_bulk_update_tasks` | 批量更新 | task.manage | 更新 |
| `crm_list_task_activity` | 任务变更历史 | task.manage | 无 |
| `crm_list_task_comments` | 列出任务评论 | task.manage | 无 |
| `crm_add_task_comment` | 添加任务评论 | task.manage | 创建 |
| `crm_update_comment` | 更新评论 | task.manage | 更新 |
| `crm_delete_comment` | 删除评论 | task.manage | 删除 |
| `crm_get_comment_draft` | 任务评论草稿 | task.manage | 无 |
| `crm_save_comment_draft` | 保存评论草稿 | task.manage | 数据变更 |
| `crm_delete_comment_draft` | 删除评论草稿 | task.manage | 删除 |
| `crm_list_subtasks` | 列出子任务 | task.manage | 无 |
| `crm_create_subtask` | 创建子任务 | task.manage | 创建 |
| `crm_update_subtask` | 更新子任务 | task.manage | 更新 |
| `crm_delete_subtask` | 删除子任务 | task.manage | 删除 |
| `crm_list_task_relations` | 列出任务关系 | task.manage | 无 |
| `crm_create_task_relation` | 创建任务关系 | task.manage | 创建 |
| `crm_delete_task_relation` | 删除任务关系 | task.manage | 删除 |
| `crm_list_task_checklists` | 列出任务清单 | task.manage | 无 |
| `crm_create_task_checklist` | 创建任务清单 | task.manage | 创建 |
| `crm_update_checklist` | 更新清单 | task.manage | 更新 |
| `crm_list_checklist_items` | 列出清单项 | task.manage | 无 |
| `crm_create_checklist_item` | 创建清单项 | task.manage | 创建 |
| `crm_update_checklist_item` | 更新清单项 | task.manage | 更新 |
| `crm_delete_checklist` | 删除清单 | task.manage | 删除 |
| `crm_delete_checklist_item` | 删除清单项 | task.manage | 删除 |
| `crm_list_task_tags` | 列出任务标签 | task.manage | 无 |
| `crm_attach_task_tag` | 附加任务标签 | task.manage | 创建 |
| `crm_detach_task_tag` | 分离任务标签 | task.manage | 删除 |
| `crm_list_dependencies` | 列出依赖 | task.manage | 无 |
| `crm_create_dependency` | 创建依赖 | task.manage | 创建 |
| `crm_delete_dependency` | 删除依赖 | task.manage | 删除 |

### 项目

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_projects` | 列出项目 | project.manage | 无 |
| `crm_get_project` | 获取项目 | project.manage | 无 |
| `crm_get_project_summary` | 项目摘要 | project.manage | 无 |
| `crm_get_project_timeline` | 获取项目时间线 | project.manage | 无 |
| `crm_get_project_milestones_summary` | 项目里程碑 | project.manage | 无 |
| `crm_get_project_risks` | 项目风险 | project.manage | 无 |
| `crm_get_project_workload` | 项目工作负载 | project.manage | 无 |
| `crm_create_project` | 创建项目 | project.manage | 创建 |
| `crm_update_project` | 更新项目 | project.manage | 更新 |
| `crm_delete_project` | 删除项目 | project.manage | 删除 |
| `crm_list_project_modules` | 列出项目模块 | project.manage | 无 |
| `crm_get_project_module` | 获取项目模块 | project.manage | 无 |
| `crm_create_project_module` | 创建项目模块 | project.manage | 创建 |
| `crm_update_project_module` | 更新项目模块 | project.manage | 更新 |
| `crm_archive_project_module` | 归档项目模块 | project.manage | 更新 |
| `crm_delete_project_module` | 删除项目模块 | project.manage | 删除 |
| `crm_list_project_module_tasks` | 列出项目模块任务 | project.manage | 无 |
| `crm_list_project_module_members` | 列出项目模块成员 | project.manage | 无 |
| `crm_list_project_module_links` | 列出项目模块关联 | project.manage | 无 |
| `crm_add_tasks_to_project_module` | 添加任务到项目模块 | project.manage | 创建 |
| `crm_add_members_to_project_module` | 添加成员到项目模块 | project.manage | 创建 |
| `crm_remove_project_module_task` | 移除项目模块任务 | project.manage | 删除 |
| `crm_remove_project_module_member` | 移除项目模块成员 | project.manage | 删除 |
| `crm_add_project_module_link` | 添加项目模块关联 | project.manage | 创建 |
| `crm_update_project_module_link` | 更新项目模块关联 | project.manage | 更新 |
| `crm_delete_project_module_link` | 删除项目模块关联 | project.manage | 删除 |

### 工作周期 / 冲刺

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_cycles` | 列出周期 | task.manage | 无 |
| `crm_get_cycle` | 获取周期 | task.manage | 无 |
| `crm_get_cycle_summary` | 冲刺摘要 | task.manage | 无 |
| `crm_create_cycle` | 创建周期 | task.manage | 创建 |
| `crm_update_cycle` | 更新周期 | task.manage | 更新 |
| `crm_delete_cycle` | 删除周期 | task.manage | 删除 |
| `crm_start_cycle` | 开始周期 | task.manage | 状态变更 |
| `crm_complete_cycle` | 完成周期 | task.manage | 状态变更 |
| `crm_reopen_cycle` | 重新打开周期 | task.manage | 状态变更 |
| `crm_archive_cycle` | 归档周期 | task.manage | 状态变更 |
| `crm_list_cycle_tasks` | 列出周期任务 | task.manage | 无 |
| `crm_add_tasks_to_cycle` | 添加任务到周期 | task.manage | 创建 |
| `crm_remove_cycle_task` | 移除周期任务 | task.manage | 删除 |
| `crm_transfer_unfinished_cycle_tasks` | 转移未完成周期任务 | task.manage | 更新 |

### 组织、公司、客户、联系人、交易对手

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_organizations` | 列出组织 | organization.manage | 无 |
| `crm_get_organization` | 获取组织 | organization.manage | 无 |
| `crm_create_organization` | 创建组织 | organization.manage | 创建 |
| `crm_update_organization` | 更新组织 | organization.manage | 更新 |
| `crm_delete_organization` | 删除组织 | organization.manage | 删除 |
| `crm_list_organization_members` | 列出组织成员 | organization.manage | 无 |
| `crm_add_organization_member` | 添加组织成员 | organization.manage | 创建 |
| `crm_remove_organization_member` | 移除组织成员 | organization.manage | 删除 |
| `crm_list_companies` | 列出公司 | company.manage | 无 |
| `crm_get_company` | 获取公司 | company.manage | 无 |
| `crm_create_company` | 创建公司 | company.manage | 创建 |
| `crm_update_company` | 更新公司 | company.manage | 更新 |
| `crm_delete_company` | 删除公司 | company.manage | 删除 |
| `crm_list_clients` | 列出客户端 | client.manage | 无 |
| `crm_get_client` | 获取客户端 | client.manage | 无 |
| `crm_create_client` | 创建客户端 | client.manage | 创建 |
| `crm_update_client` | 更新客户端 | client.manage | 更新 |
| `crm_delete_client` | 删除客户端 | client.manage | 删除 |
| `crm_list_contacts` | 列出联系人 | contact.manage | 无 |
| `crm_get_contact` | 获取联系人 | contact.manage | 无 |
| `crm_create_contact` | 创建联系人 | contact.manage | 创建 |
| `crm_update_contact` | 更新联系人 | contact.manage | 更新 |
| `crm_delete_contact` | 删除联系人 | contact.manage | 删除 |
| `crm_list_counterparties` | 列出交易对手 | counterparty.manage | 无 |
| `crm_get_counterparty` | 获取交易对手 | counterparty.manage | 无 |
| `crm_create_counterparty` | 创建交易对手 | counterparty.manage | 创建 |
| `crm_update_counterparty` | 更新交易对手 | counterparty.manage | 更新 |
| `crm_delete_counterparty` | 删除交易对手 | counterparty.manage | 删除 |

### 用户、团队、部门

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_users` | 列出用户 | user.view | 无 |
| `crm_get_user` | 获取用户 | user.view | 无 |
| `crm_create_user` | 创建用户 | user.manage | 创建 |
| `crm_update_user` | 更新用户 | user.manage | 更新 |
| `crm_delete_user` | 删除用户 | user.manage | 删除 |
| `crm_get_user_token_info` | 令牌信息 | user.manage | 无 |
| `crm_rotate_user_token` | 轮换用户令牌 | user.manage | 更新 |
| `crm_revoke_user_token` | 撤销用户令牌 | user.manage | 更新 |
| `crm_get_user_activity` | 获取用户活动 | user.manage | 无 |
| `crm_list_teams` | 列出团队 | auth | 无 |
| `crm_get_team` | 获取团队 | auth | 无 |
| `crm_create_team` | 创建团队 | team.manage | 创建 |
| `crm_update_team` | 更新团队 | team.manage | 更新 |
| `crm_delete_team` | 删除团队 | team.manage | 删除 |
| `crm_list_departments` | 列出部门 | department.manage | 无 |
| `crm_get_department` | 获取部门 | department.manage | 无 |
| `crm_create_department` | 创建部门 | department.manage | 创建 |
| `crm_update_department` | 更新部门 | department.manage | 更新 |
| `crm_delete_department` | 删除部门 | department.manage | 删除 |

### 角色与权限

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_roles` | 列出角色 | role.manage/role.view | 无 |
| `crm_list_permissions` | 列出权限 | role.manage/role.view | 无 |
| `crm_get_role_permissions` | 获取角色权限 | role.manage/role.view | 无 |
| `crm_create_role` | 创建角色 | role.manage | 创建 |
| `crm_update_role` | 更新角色 | role.manage | 更新 |
| `crm_delete_role` | 删除角色 | role.manage | 删除 |
| `crm_set_role_permissions` | 设置角色权限 | role.manage | 更新 |
| `crm_get_admin_role_matrix` | 获取管理角色矩阵 | settings.manage | 无 |
| `crm_update_admin_role_matrix` | 更新管理角色矩阵 | settings.manage | 更新 |

### 审批

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_approvals` | 列出审批 | approval.manage | 无 |
| `crm_get_approval` | 获取审批 | approval.manage | 无 |
| `crm_create_approval` | 创建审批 | approval.manage | 创建 |
| `crm_approve_request` | 批准请求 | approval.manage | 状态变更 |
| `crm_reject_request` | 拒绝请求 | approval.manage | 状态变更 |

### 日历、里程碑、提醒

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_calendar_events` | 列出日历事件 | task.manage | 无 |
| `crm_get_calendar_agenda` | 每日/每周日程 | task.manage | 无 |
| `crm_create_calendar_event` | 创建日历事件 | task.manage | 创建 |
| `crm_get_calendar_event` | 获取日历事件 | task.manage | 无 |
| `crm_update_calendar_event` | 更新日历事件 | task.manage | 更新 |
| `crm_delete_calendar_event` | 删除日历事件 | task.manage | 删除 |
| `crm_get_calendar_my_month` | 月视图 | task.manage | 无 |
| `crm_list_milestones` | 列出里程碑 | task.manage | 无 |
| `crm_get_milestone` | 获取里程碑 | task.manage | 无 |
| `crm_create_milestone` | 创建里程碑 | task.manage | 创建 |
| `crm_update_milestone` | 更新里程碑 | task.manage | 更新 |
| `crm_delete_milestone` | 删除里程碑 | project.manage | 删除 |
| `crm_list_reminders` | 列出提醒 | task.manage | 无 |
| `crm_get_reminder` | 获取提醒 | task.manage | 无 |
| `crm_create_reminder` | 创建提醒 | task.manage | 创建 |
| `crm_update_reminder` | 更新提醒 | task.manage | 更新 |
| `crm_delete_reminder` | 删除提醒 | task.manage | 删除 |

### 工时

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_worklogs` | 列出工时 | task.manage | 无 |
| `crm_get_worklog` | 获取工时 | task.manage | 无 |
| `crm_create_worklog` | 创建工时 | task.manage | 创建 |
| `crm_update_worklog` | 更新工时 | task.manage | 更新 |
| `crm_delete_worklog` | 删除工时 | task.manage | 删除 |
| `crm_get_worklog_summary` | 按日汇总 | task.manage | 无 |
| `crm_get_worklog_earnings` | 收入/支出 | task.manage | 无 |
| `crm_get_worklog_matrix` | 矩阵（用户 × 天数） | task.manage | 无 |
| `crm_get_worklog_detail` | 获取工时详情 | task.manage | 无 |
| `crm_get_worklog_task_summary` | 任务摘要 | task.manage | 无 |

### 估算

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_estimate_sets` | 列出估算集合 | task.manage | 无 |
| `crm_get_estimate_set` | 获取估算设置 | task.manage | 无 |
| `crm_create_estimate_set` | 创建估算设置 | task.manage | 创建 |
| `crm_update_estimate_set` | 更新估算设置 | task.manage | 更新 |
| `crm_archive_estimate_set` | 归档估算设置 | task.manage | 更新 |
| `crm_delete_estimate_set` | 删除估算设置 | task.manage | 删除 |
| `crm_list_estimate_options` | 列出估算选项 | task.manage | 无 |
| `crm_create_estimate_option` | 创建估算选项 | task.manage | 创建 |
| `crm_update_estimate_option` | 更新估算选项 | task.manage | 更新 |
| `crm_archive_estimate_option` | 归档估算选项 | task.manage | 更新 |
| `crm_delete_estimate_option` | 删除估算选项 | task.manage | 删除 |
| `crm_list_task_estimates` | 列出任务估算 | task.manage | 无 |
| `crm_assign_task_estimate` | 分配任务估算 | task.manage | 创建 |
| `crm_remove_task_estimate` | 移除任务估算 | task.manage | 删除 |
| `crm_get_project_estimate_summary` | 获取项目估算摘要 | task.manage | 无 |
| `crm_get_cycle_estimate_summary` | 获取周期估算摘要 | task.manage | 无 |
| `crm_get_module_estimate_summary` | 获取模块估算摘要 | task.manage | 无 |

### 自定义字段

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_custom_fields` | 列出自定义字段 | task.manage | 无 |
| `crm_get_custom_field` | 获取自定义字段 | task.manage | 无 |
| `crm_create_custom_field` | 创建自定义字段 | task.manage | 创建 |
| `crm_update_custom_field` | 更新自定义字段 | task.manage | 更新 |
| `crm_get_custom_field_values` | 获取自定义字段值 | task.manage | 无 |
| `crm_set_custom_field_values` | 设置自定义字段值 | task.manage | 更新 |

### 模板

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_templates` | 列出模板 | task.manage | 无 |
| `crm_get_template` | 获取模板 | task.manage | 无 |
| `crm_create_template` | 创建模板 | task.manage | 创建 |
| `crm_update_template` | 更新模板 | task.manage | 更新 |
| `crm_apply_template` | 应用模板 | task.manage | 创建实体 |
| `crm_delete_template` | 删除模板 | task.manage | 删除 |

### 文件

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_files` | 列出文件 | task.manage | 无 |
| `crm_get_file` | 获取文件 | task.manage | 无 |
| `crm_upload_file_base64` | 上传文件（base64） | task.manage | 创建 |
| `crm_get_file_download_info` | 下载链接 | task.manage | 无 |
| `crm_delete_file` | 删除文件 | task.manage | 删除 |

### 状态与标签

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_statuses` | 列出状态 | task.manage | 无 |
| `crm_get_status` | 获取状态 | task.manage | 无 |
| `crm_create_status` | 创建状态 | task.manage | 创建 |
| `crm_update_status` | 更新状态 | task.manage | 更新 |
| `crm_delete_status` | 删除状态 | task.manage | 删除 |
| `crm_list_tags` | 列出标签 | task.manage | 无 |
| `crm_get_tag` | 获取标签 | task.manage | 无 |
| `crm_create_tag` | 创建标签 | task.manage | 创建 |
| `crm_update_tag` | 更新标签 | task.manage | 更新 |
| `crm_delete_tag` | 删除标签 | task.manage | 删除 |
| `crm_list_priorities` | 列出优先级 | task.manage | 无 |
| `crm_create_priority` | 创建优先级 | task.manage | 创建 |
| `crm_update_priority` | 更新优先级 | task.manage | 更新 |
| `crm_delete_priority` | 删除优先级 | task.manage | 删除 |

### SLA

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_sla_policies` | 列出 SLA 策略 | task.manage | 无 |
| `crm_get_sla_policy` | 获取 SLA 策略 | task.manage | 无 |
| `crm_create_sla_policy` | 创建 SLA 策略 | task.manage | 创建 |
| `crm_update_sla_policy` | 更新 SLA 策略 | task.manage | 更新 |
| `crm_delete_sla_policy` | 删除 SLA 策略 | settings.manage | 删除 |
| `crm_get_sla_report` | 获取 SLA 报告 | task.manage | 无 |
| `crm_assign_sla_to_task` | 将 SLA 分配给任务 | task.manage | 更新 |

### 重复规则

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_recurring_rules` | 列出重复规则 | task.manage | 无 |
| `crm_get_recurring_rule` | 获取重复规则 | task.manage | 无 |
| `crm_create_recurring_rule` | 创建重复规则 | task.manage | 创建 |
| `crm_update_recurring_rule` | 更新重复规则 | task.manage | 更新 |
| `crm_pause_recurring_rule` | 暂停重复规则 | task.manage | 更新 |
| `crm_resume_recurring_rule` | 恢复重复规则 | task.manage | 更新 |
| `crm_delete_recurring_rule` | 删除重复规则 | task.manage | 删除 |

### 已保存视图与便签

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_saved_views` | 列出已保存视图 | task.manage | 无 |
| `crm_get_saved_view` | 获取已保存视图 | task.manage | 无 |
| `crm_create_saved_view` | 创建已保存视图 | task.manage | 创建 |
| `crm_update_saved_view` | 更新已保存视图 | task.manage | 更新 |
| `crm_archive_saved_view` | 归档已保存视图 | task.manage | 更新 |
| `crm_duplicate_saved_view` | 复制已保存视图 | task.manage | 创建 |
| `crm_pin_saved_view` | 置顶已保存视图 | task.manage | 更新 |
| `crm_touch_saved_view` | 标记为已使用 | task.manage | 更新 |
| `crm_get_saved_view_task_filters` | 视图过滤器 | task.manage | 无 |
| `crm_delete_saved_view` | 删除已保存视图 | task.manage | 删除 |
| `crm_list_sticky_notes` | 列出便签便签 | task.manage | 无 |
| `crm_get_sticky_note` | 获取便签便签 | task.manage | 无 |
| `crm_create_sticky_note` | 创建便签便签 | task.manage | 创建 |
| `crm_update_sticky_note` | 更新便签便签 | task.manage | 更新 |
| `crm_archive_sticky_note` | 归档便签便签 | task.manage | 更新 |
| `crm_unarchive_sticky_note` | 取消归档便签便签 | task.manage | 更新 |
| `crm_delete_sticky_note` | 删除便签便签 | task.manage | 删除 |
| `crm_convert_sticky_to_task` | 便签转任务 | task.manage | 创建 |
| `crm_convert_sticky_to_page` | 便签转知识页面 | task.manage | 创建 |
| `crm_reorder_sticky_notes` | 重新排序便签便签 | task.manage | 更新 |

### 知识库

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_get_knowledge_overview` | 知识库概览 | knowledge.view | 无 |
| `crm_list_knowledge_pages` | 列出知识页面 | knowledge.view | 无 |
| `crm_get_knowledge_page` | 获取知识页面 | knowledge.view | 无 |
| `crm_list_knowledge_spaces` | 列出知识空间 | knowledge.view | 无 |
| `crm_list_knowledge_spaces_tree` | 空间树 | knowledge.view | 无 |
| `crm_get_knowledge_space` | 获取知识空间 | knowledge.view | 无 |
| `crm_get_knowledge_tree` | 页面树 | knowledge.view | 无 |
| `crm_search_knowledge` | 搜索知识 | knowledge.view | 无 |
| `crm_list_knowledge_recent` | 最近页面 | knowledge.view | 无 |
| `crm_list_knowledge_popular` | 热门页面 | knowledge.view | 无 |
| `crm_list_knowledge_review_queue` | 审查队列 | knowledge.view | 无 |
| `crm_list_knowledge_outdated` | 过时页面 | knowledge.view | 无 |
| `crm_list_knowledge_favorites` | 列出知识收藏 | knowledge.view | 无 |
| `crm_get_knowledge_entity_pages` | 实体页面 | knowledge.view | 无 |
| `crm_get_knowledge_suggest` | 建议 | knowledge.view | 无 |
| `crm_get_knowledge_analytics` | 获取知识分析 | knowledge.view | 无 |
| `crm_list_knowledge_page_versions` | 列出知识页面版本 | knowledge.view | 无 |
| `crm_get_knowledge_page_version` | 获取知识页面版本 | knowledge.view | 无 |
| `crm_diff_knowledge_page_version` | 差异知识页面版本 | knowledge.view | 无 |
| `crm_create_knowledge_space` | 创建知识空间 | knowledge.manage | 创建 |
| `crm_update_knowledge_space` | 更新知识空间 | knowledge.manage | 更新 |
| `crm_archive_knowledge_space` | 归档知识空间 | knowledge.manage | 更新 |
| `crm_restore_knowledge_space` | 恢复知识空间 | knowledge.manage | 更新 |
| `crm_create_knowledge_page` | 创建知识页面 | knowledge.create | 创建 |
| `crm_update_knowledge_page` | 更新知识页面 | knowledge.edit | 更新 |
| `crm_publish_knowledge_page` | 发布知识页面 | knowledge.publish | 状态变更 |
| `crm_archive_knowledge_page` | 归档知识页面 | knowledge.publish | 状态变更 |
| `crm_restore_knowledge_page` | 恢复知识页面 | knowledge.publish | 状态变更 |
| `crm_request_knowledge_review` | 请求知识审查 | knowledge.review | 更新 |
| `crm_approve_knowledge_review` | 批准知识审查 | knowledge.review | 状态变更 |
| `crm_reject_knowledge_review` | 拒绝知识审查 | knowledge.review | 状态变更 |
| `crm_duplicate_knowledge_page` | 复制知识页面 | knowledge.create | 创建 |
| `crm_move_knowledge_page` | 移动知识页面 | knowledge.manage | 更新 |
| `crm_lock_knowledge_page` | 锁定知识页面 | knowledge.manage | 更新 |
| `crm_unlock_knowledge_page` | 解锁知识页面 | knowledge.manage | 更新 |
| `crm_lock_knowledge_page_version` | 锁定知识页面版本 | knowledge.manage | 更新 |
| `crm_unlock_knowledge_page_version` | 解锁知识页面版本 | knowledge.manage | 更新 |
| `crm_delete_knowledge_page` | 删除知识页面 | knowledge.manage | 删除 |
| `crm_restore_knowledge_page_version` | 恢复知识页面版本 | knowledge.publish | 更新 |
| `crm_list_knowledge_templates` | 列出知识模板 | knowledge.view | 无 |
| `crm_create_knowledge_template` | 创建知识模板 | knowledge.create | 创建 |
| `crm_export_knowledge_all` | 导出知识全部 | knowledge.view | 无 |
| `crm_export_knowledge_page` | 导出知识页面 | knowledge.view | 无 |
| `crm_export_knowledge_space` | 导出知识空间 | knowledge.view | 无 |
| `crm_import_knowledge_pages` | 导入知识页面 | knowledge.create | 创建 |
| `crm_list_knowledge_comments` | 列出知识评论 | knowledge.view | 无 |
| `crm_add_knowledge_comment` | 添加知识评论 | knowledge.comment | 创建 |
| `crm_delete_knowledge_comment` | 删除知识评论 | knowledge.comment | 删除 |
| `crm_resolve_knowledge_comment` | 解决知识评论 | knowledge.comment | 更新 |
| `crm_reopen_knowledge_comment` | 重新打开知识评论 | knowledge.comment | 更新 |
| `crm_list_knowledge_page_links` | 列出知识页面关联 | knowledge.view | 无 |
| `crm_delete_knowledge_page_link` | 删除知识页面关联 | knowledge.edit | 删除 |
| `crm_list_knowledge_page_tags` | 列出知识页面标签 | knowledge.view | 无 |
| `crm_attach_knowledge_page_tag` | 附加知识页面标签 | knowledge.edit | 创建 |
| `crm_detach_knowledge_page_tag` | 分离知识页面标签 | knowledge.edit | 删除 |
| `crm_link_knowledge_page_entity` | 关联知识页面实体 | knowledge.edit | 创建 |
| `crm_list_knowledge_files` | 列出知识文件 | knowledge.view | 无 |
| `crm_upload_knowledge_file_base64` | 上传知识文件（base64） | knowledge.edit | 创建 |
| `crm_delete_knowledge_file` | 删除知识文件 | knowledge.delete | 删除 |
| `crm_get_knowledge_page_draft` | 获取知识页面草稿 | knowledge.edit | 无 |
| `crm_save_knowledge_page_draft` | 保存知识页面草稿 | knowledge.edit | 创建/更新 |
| `crm_delete_knowledge_draft` | 删除知识草稿 | knowledge.edit | 删除 |
| `crm_favorite_knowledge_page` | 加入收藏 | knowledge.view | 创建 |
| `crm_unfavorite_knowledge_page` | 取消收藏 | knowledge.view | 删除 |
| `crm_subscribe_knowledge_page` | 订阅知识页面 | knowledge.view | 创建 |
| `crm_unsubscribe_knowledge_page` | 取消订阅知识页面 | knowledge.view | 删除 |
| `crm_get_knowledge_space_permissions` | 获取知识空间权限 | knowledge.manage | 无 |
| `crm_add_knowledge_space_permission` | 添加知识空间权限 | knowledge.manage | 创建 |
| `crm_remove_knowledge_space_permission` | 移除知识空间权限 | knowledge.manage | 删除 |
| `crm_get_knowledge_page_permissions` | 获取知识页面权限 | knowledge.manage | 无 |
| `crm_add_knowledge_page_permission` | 添加知识页面权限 | knowledge.manage | 创建 |
| `crm_remove_knowledge_page_permission` | 移除知识页面权限 | knowledge.manage | 删除 |
| `crm_get_admin_knowledge_settings` | 获取管理知识设置 | settings.manage | 无 |
| `crm_update_admin_knowledge_settings` | 更新管理知识设置 | settings.manage | 更新 |
| `crm_reindex_knowledge` | 重建索引知识 | settings.manage | 操作 |
| `crm_rebuild_knowledge_permissions` | 重建知识权限 | settings.manage | 操作 |
| `crm_cleanup_knowledge_drafts` | 清理知识草稿 | settings.manage | 删除 |

### 知识库 AI

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_create_knowledge_ai_summary` | AI 页面摘要 | knowledge.view | AI 调用 |
| `crm_create_knowledge_ai_explanation` | AI 页面解释 | knowledge.view | AI 调用 |
| `crm_find_knowledge_ai_similar` | 查找相似页面 | knowledge.view | AI 调用 |
| `crm_create_knowledge_ai_checklist` | AI 清单 | knowledge.view | AI 调用 |
| `crm_create_knowledge_ai_faq_from_comments` | 从评论生成 AI FAQ | knowledge.view | AI 调用 |
| `crm_create_knowledge_ai_suggest_for_task` | 任务建议 | knowledge.view | AI 调用 |
| `crm_find_knowledge_ai_duplicates` | 查找重复项 | knowledge.manage | AI 调用 |
| `crm_find_knowledge_ai_orphans` | 查找孤立项 | knowledge.manage | AI 调用 |
| `crm_suggest_knowledge_ai_structure` | 建议结构 | knowledge.manage | AI 调用 |

### 想法

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_ideas` | 列出想法 | idea.manage | 无 |
| `crm_get_idea` | 获取想法 | idea.manage | 无 |
| `crm_create_idea` | 创建想法 | idea.manage | 创建 |
| `crm_update_idea` | 更新想法 | idea.manage | 更新 |
| `crm_delete_idea` | 删除想法 | idea.manage | 删除 |
| `crm_vote_idea` | 投票想法 | idea.manage | 创建/删除 |
| `crm_update_idea_status` | 更新想法状态 | idea.manage | 更新 |
| `crm_list_idea_comments` | 列出想法评论 | idea.manage | 无 |
| `crm_add_idea_comment` | 添加想法评论 | idea.manage | 创建 |

### 聊天

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_chats` | 列出聊天 | project.manage/task.manage | 无 |
| `crm_get_chat` | 获取聊天 | auth | 无 |
| `crm_create_chat` | 创建聊天 | auth | 创建 |
| `crm_get_chat_participants` | 获取聊天参与者 | auth | 无 |
| `crm_list_chat_messages` | 列出聊天消息 | project.manage/task.manage | 无 |
| `crm_send_chat_message` | 发送聊天消息 | project.manage/task.manage | 创建 |
| `crm_edit_chat_message` | 编辑聊天消息 | auth | 更新 |
| `crm_delete_chat_message` | 删除聊天消息 | auth | 删除 |
| `crm_upload_chat_attachment` | 上传聊天附件 | auth | 创建 |
| `crm_download_chat_attachment` | 下载聊天附件 | auth | 无 |
| `crm_list_chat_attachments` | 列出聊天附件 | auth | 无 |
| `crm_get_chat_settings` | 获取聊天设置 | auth | 无 |
| `crm_update_chat_settings` | 更新聊天设置 | auth | 更新 |
| `crm_mark_chat_read` | 标记聊天已读 | auth | 更新 |
| `crm_get_chat_unread_count` | 获取聊天未读数 | auth | 无 |
| `crm_archive_chat` | 归档聊天 | auth | 更新 |
| `crm_restore_chat` | 恢复聊天 | auth | 更新 |

### 通知

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_notifications` | 列出通知 | auth | 无 |
| `crm_get_notification_counters` | 获取通知计数器 | auth | 无 |
| `crm_create_notification` | 创建通知 | settings.manage | 创建 |
| `crm_mark_notification_read` | 标记通知已读 | auth | 更新 |
| `crm_mark_notification_unread` | 标记通知未读 | auth | 更新 |
| `crm_mark_all_notifications_read` | 标记全部通知已读 | auth | 更新 |

### 推送订阅

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_push_subscriptions` | 列出推送订阅 | auth | 无 |
| `crm_create_push_subscription` | 创建推送订阅 | auth | 创建 |
| `crm_delete_push_subscription` | 删除推送订阅 | auth | 删除 |
| `crm_send_push_test` | 发送推送测试 | auth | 发送 |

### 收藏、订阅、回应、提及

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_favorites` | 列出收藏 | auth | 无 |
| `crm_create_favorite` | 创建收藏 | auth | 创建 |
| `crm_delete_favorite` | 删除收藏 | auth | 删除 |
| `crm_list_subscriptions` | 列出订阅 | auth | 无 |
| `crm_create_subscription` | 创建订阅 | auth | 创建 |
| `crm_delete_subscription` | 删除订阅 | auth | 删除 |
| `crm_list_reactions` | 列出回应 | auth | 无 |
| `crm_add_reaction` | 添加回应 | auth | 创建 |
| `crm_remove_reaction` | 移除回应 | auth | 删除 |
| `crm_list_mentions` | 列出提及 | auth | 无 |
| `crm_add_mention` | 添加提及 | project.manage/task.manage | 创建 |
| `crm_delete_mention` | 删除提及 | project.manage/task.manage | 删除 |

### 活动流

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_get_activity_feed` | 获取活动流 | logs.view/project.manage/task.manage | 无 |
| `crm_get_activity_history` | 获取活动历史 | logs.view/project.manage/task.manage | 无 |

### 客户门户

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_client_cabinet_projects` | 列出客户端门户项目 | client.manage | 无 |
| `crm_get_client_cabinet_project` | 获取客户端门户项目 | client.manage | 无 |
| `crm_list_client_cabinet_project_tasks` | 列出客户端门户项目任务 | client.manage | 无 |

### 收件箱

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_intake_items` | 列出收件箱项 | intake.manage/intake.view | 无 |
| `crm_get_intake_item` | 获取收件箱项 | intake.manage/intake.view | 无 |
| `crm_create_intake_item` | 创建收件箱项 | intake.create/intake.manage | 创建 |
| `crm_update_intake_item` | 更新收件箱项 | intake.manage | 更新 |
| `crm_delete_intake_item` | 删除收件箱项 | intake.delete/intake.manage | 删除 |
| `crm_accept_intake_item` | 接受收件箱项 | intake.accept/intake.manage | 创建 |
| `crm_reject_intake_item` | 拒绝收件箱项 | intake.manage | 更新 |
| `crm_snooze_intake_item` | 推迟收件箱项 | intake.manage | 更新 |
| `crm_duplicate_intake_item` | 复制收件箱项 | intake.manage | 更新 |
| `crm_reopen_intake_item` | 重新打开收件箱项 | intake.manage | 更新 |

### Webhook

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_webhooks` | 列出 Webhook | webhook.manage | 无 |
| `crm_list_webhook_deliveries` | 列出 Webhook 投递 | webhook.manage | 无 |
| `crm_create_webhook` | 创建 Webhook | webhook.manage | 创建 |
| `crm_update_webhook` | 更新 Webhook | webhook.manage | 更新 |
| `crm_delete_webhook` | 删除 Webhook | webhook.manage | 删除 |
| `crm_test_webhook` | 测试 Webhook | webhook.manage | 发送 |

### 工作流规则

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_workflow_rules` | 列出工作流规则 | settings.manage | 无 |
| `crm_get_workflow_rule` | 获取工作流规则 | settings.manage | 无 |
| `crm_create_workflow_rule` | 创建工作流规则 | settings.manage | 创建 |
| `crm_update_workflow_rule` | 更新工作流规则 | settings.manage | 更新 |
| `crm_delete_workflow_rule` | 删除工作流规则 | settings.manage | 删除 |
| `crm_list_workflow_runs` | 列出工作流运行记录 | settings.manage | 无 |
| `crm_run_workflow_rule_test` | 运行工作流规则测试 | settings.manage | 执行 |

### AI

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_get_ai_settings` | 全局 AI 设置 | settings.manage | 无 |
| `crm_update_ai_settings` | 更新 AI 设置 | settings.manage | 更新 |
| `crm_get_ai_preferences` | 获取 AI 偏好设置 | ai.use | 无 |
| `crm_update_ai_preferences` | 更新 AI 偏好设置 | ai.use | 更新 |
| `crm_get_ai_availability` | 获取 AI 可用性 | ai.use | 无 |
| `crm_list_ai_action_types` | 列出 AI 操作类型 | ai.use | 无 |
| `crm_execute_ai_action` | 执行 AI 操作 | ai.use | AI 调用 |
| `crm_list_ai_providers` | 列出 AI 提供商 | settings.manage | 无 |
| `crm_get_ai_provider` | 获取 AI 提供商 | settings.manage | 无 |
| `crm_list_ai_models` | 列出 AI 模型 | settings.manage | 无 |
| `crm_list_ai_intents` | 列出 AI 意图 | settings.manage | 无 |
| `crm_update_ai_intent` | 更新 AI 意图 | settings.manage | 更新 |
| `crm_list_ai_prompts` | 列出 AI 提示词 | settings.manage | 无 |
| `crm_create_ai_prompt` | 创建 AI 提示词 | settings.manage | 创建 |
| `crm_update_ai_prompt` | 更新 AI 提示词 | settings.manage | 更新 |
| `crm_list_ai_json_schemas` | 列出 AI JSON 模式 | settings.manage | 无 |
| `crm_create_ai_json_schema` | 创建 AI JSON 模式 | settings.manage | 创建 |
| `crm_update_ai_json_schema` | 更新 AI JSON 模式 | settings.manage | 更新 |
| `crm_list_ai_usage` | 列出 AI 使用量 | settings.manage | 无 |
| `crm_list_ai_audit` | 列出 AI 审计 | settings.manage | 无 |
| `crm_list_ai_jobs` | 列出 AI 作业 | settings.manage | 无 |
| `crm_get_ai_job` | 获取 AI 作业 | settings.manage | 无 |
| `crm_retry_ai_job` | 重试 AI 作业 | settings.manage | 操作 |
| `crm_dry_run_ai_job` | 试运行 AI 作业 | settings.manage | 无 |
| `crm_run_once_ai_job` | 运行一次 AI 作业 | settings.manage | 执行 |
| `crm_search_ai_semantic` | 语义搜索 | settings.manage | AI 调用 |
| `crm_list_ai_retention_policies` | 列出 AI 保留策略 | settings.manage | 无 |
| `crm_list_ai_suggestions` | 列出 AI 建议 | ai.use | 无 |
| `crm_get_ai_suggestion` | 获取 AI 建议 | ai.use | 无 |
| `crm_dismiss_ai_suggestion` | 忽略建议 | ai.use | 更新 |
| `crm_preview_apply_ai_suggestion` | 预览建议 | ai.use | 无 |
| `crm_confirm_ai_suggestion` | 应用建议 | ai.use | 更新 |
| `crm_create_ai_dashboard_digest` | AI 仪表盘摘要 | ai.use | AI 调用 |
| `crm_create_ai_my_day_plan` | AI 每日计划 | ai.use | AI 调用 |
| `crm_create_ai_my_week_plan` | AI 每周计划 | ai.use | AI 调用 |
| `crm_create_ai_task_summary` | AI 任务摘要 | task.manage | AI 调用 |
| `crm_create_ai_task_next_action` | AI 下一步行动 | task.manage | AI 调用 |
| `crm_create_ai_task_decomposition` | AI 任务分解 | task.manage | AI 调用 |
| `crm_create_ai_task_checklist` | AI 任务清单 | task.manage | AI 调用 |
| `crm_create_ai_task_quality` | AI 质量审查 | task.manage | AI 调用 |
| `crm_create_ai_project_summary` | AI 项目摘要 | project.manage | AI 调用 |
| `crm_create_ai_project_risks` | AI 项目风险 | project.manage | AI 调用 |
| `crm_create_ai_analytics_kpi_explanation` | AI KPI 解释 | ai.use | AI 调用 |
| `crm_create_ai_analytics_risks_explanation` | AI 风险解释 | ai.use | AI 调用 |
| `crm_create_ai_analytics_team_workload_summary` | AI 团队工作负载摘要 | ai.use | AI 调用 |

### 分析

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_get_analytics_summary` | 获取分析摘要 | analytics.view/task.manage | 无 |
| `crm_list_analytics_projects` | 列出分析项目 | analytics.view/task.manage | 无 |
| `crm_list_analytics_users` | 列出分析用户 | analytics.view/task.manage | 无 |

### 仪表盘

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_get_dashboard_summary` | 获取仪表盘摘要 | auth | 无 |
| `crm_get_health_status` | 轻量健康状态 | auth | 无 |
| `crm_get_dashboard_widgets` | 小部件目录与已启用小部件 | auth | 无 |
| `crm_save_dashboard_widgets` | 保存小部件布局 | auth | 数据变更 |

### 管理

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_settings` | 列出设置 | settings.manage | 无 |
| `crm_get_setting` | 获取设置 | settings.manage | 无 |
| `crm_get_retention_metadata` | 获取保留元数据 | settings.manage | 无 |
| `crm_set_retention_metadata` | 设置保留元数据 | settings.manage | 更新 |
| `crm_list_feature_flags` | 列出功能标志 | settings.manage | 无 |
| `crm_update_feature_flag` | 更新功能标志 | settings.manage | 更新 |
| `crm_list_modules` | 列出模块 | settings.manage | 无 |
| `crm_get_module` | 获取模块 | settings.manage | 无 |
| `crm_install_module` | 安装模块 | settings.manage | 安装 |
| `crm_activate_module` | 激活模块 | settings.manage | 更新 |
| `crm_deactivate_module` | 停用模块 | settings.manage | 更新 |
| `crm_uninstall_module` | 卸载模块 | settings.manage | 删除 |
| `crm_get_module_config` | 获取模块配置 | settings.manage | 无 |
| `crm_update_module_config` | 更新模块配置 | settings.manage | 更新 |
| `crm_get_module_health` | 获取模块健康 | settings.manage | 无 |
| `crm_get_module_migrations` | 获取模块迁移 | settings.manage | 无 |
| `crm_get_module_errors` | 获取模块错误 | settings.manage | 无 |
| `crm_clear_module_errors` | 清除模块错误 | settings.manage | 删除 |
| `crm_install_module_from_url` | 从 URL 安装模块 | settings.manage | 安装 |
| `crm_install_module_from_file` | 从文件安装模块 | settings.manage | 安装 |
| `crm_get_cache_stats` | 获取缓存统计 | settings.manage | 无 |
| `crm_clear_cache` | 清除缓存 | settings.manage | 删除 |
| `crm_get_ops_system` | 系统快照 | settings.manage | 无 |
| `crm_get_ops_metrics` | 获取运维指标 | settings.manage | 无 |
| `crm_run_ops_jobs` | 运行队列 | settings.manage | 执行 |
| `crm_get_core_version` | 获取核心版本 | settings.manage | 无 |
| `crm_get_core_update_status` | 获取核心更新状态 | settings.manage | 无 |
| `crm_check_core_update` | 检查更新 | settings.manage | 无 |
| `crm_run_core_update_preflight` | 更新预检 | settings.manage | 无 |
| `crm_get_core_update_changes` | 获取核心更新变更 | settings.manage | 无 |
| `crm_get_core_update_session` | 更新会话 | settings.manage | 创建 |
| `crm_get_core_update_history` | 获取核心更新历史 | settings.manage | 无 |
| `crm_get_core_update_log` | 获取核心更新日志 | settings.manage | 无 |

### 日志与审计

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_audit_log` | 列出审计日志 | logs.view/settings.manage | 无 |
| `crm_list_security_log` | 列出安全日志 | logs.view/settings.manage | 无 |
| `crm_list_request_logs` | 列出请求日志 | logs.view | 无 |
| `crm_get_frontend_errors_chart` | 前端错误图表 | logs.view | 无 |
| `crm_get_admin_summary_widget` | 摘要小部件 | logs.view | 无 |
| `crm_get_admin_system_widget` | 系统小部件 | logs.view | 无 |
| `crm_get_openapi_spec` | OpenAPI 规范 | logs.view | 无 |

### API 客户端与密钥

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_api_clients` | 列出 API 客户端 | api_client.manage/api_client.view | 无 |
| `crm_get_api_client` | 获取 API 客户端 | api_client.manage/api_client.view | 无 |
| `crm_list_api_client_keys` | 列出 API 客户端密钥 | api_client.manage/api_client.view | 无 |
| `crm_create_api_client` | 创建 API 客户端 | api_client.manage | 创建 |
| `crm_update_api_client` | 更新 API 客户端 | api_client.manage | 更新 |
| `crm_delete_api_client` | 删除 API 客户端 | api_client.manage | 删除 |
| `crm_issue_api_client_key` | 签发 API 客户端密钥 | api_client.manage | 创建 |
| `crm_rotate_api_key` | 轮换 API 密钥 | api_client.manage | 更新 |
| `crm_revoke_api_key` | 撤销 API 密钥 | api_client.manage | 更新 |
| `crm_get_api_key_usage` | 密钥使用量 | api_client.view | 无 |

### 导入 / 导出

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_import_jobs` | 列出导入作业 | import.manage | 无 |
| `crm_get_import_job` | 获取导入作业 | import.manage | 无 |
| `crm_create_import_job` | 创建导入作业 | import.manage | 创建 |
| `crm_cancel_import_job` | 取消导入作业 | import.manage | 更新 |
| `crm_retry_import_job` | 重试导入作业 | import.manage | 执行 |
| `crm_list_export_jobs` | 列出导出作业 | export.manage | 无 |
| `crm_get_export_job` | 获取导出作业 | export.manage | 无 |
| `crm_create_export_job` | 创建导出作业 | export.manage | 创建 |
| `crm_cancel_export_job` | 取消导出作业 | export.manage | 更新 |
| `crm_retry_export_job` | 重试导出作业 | export.manage | 执行 |
| `crm_download_export_job` | 下载导出作业 | export.manage | 无 |

### 回收站

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_recycle_bin` | 列出回收站 | recycle_bin.manage | 无 |
| `crm_restore_recycle_bin_item` | 恢复回收站项 | recycle_bin.manage | 恢复 |
| `crm_purge_recycle_bin_item` | 永久清除 | recycle_bin.manage | 删除 |

### 业务日历

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_business_calendars` | 列出业务日历 | settings.manage | 无 |
| `crm_create_business_calendar` | 创建业务日历 | settings.manage | 创建 |
| `crm_get_business_calendar` | 获取业务日历 | settings.manage | 无 |
| `crm_update_business_calendar` | 更新业务日历 | settings.manage | 更新 |
| `crm_delete_business_calendar` | 删除业务日历 | settings.manage | 删除 |
| `crm_list_holidays` | 列出假期 | settings.manage | 无 |
| `crm_create_holiday` | 创建假期 | settings.manage | 创建 |
| `crm_get_holiday` | 获取假期 | settings.manage | 无 |
| `crm_update_holiday` | 更新假期 | settings.manage | 更新 |
| `crm_delete_holiday` | 删除假期 | settings.manage | 删除 |
| `crm_list_working_hours` | 列出工作时间 | settings.manage | 无 |
| `crm_create_working_hours` | 创建工作时间 | settings.manage | 创建 |
| `crm_get_working_hours` | 获取工作时间 | settings.manage | 无 |
| `crm_update_working_hours` | 更新工作时间 | settings.manage | 更新 |
| `crm_delete_working_hours` | 删除工作时间 | settings.manage | 删除 |

### 邀请

| Tool | 用途 | Permission | 副作用 |
|------|-----------|------------|--------------|
| `crm_list_invitations` | 列出邀请 | user.manage | 无 |
| `crm_create_invitation` | 创建邀请 | user.manage | 邮件 |

---

## MCP 资源

MCP 资源为只读，通过 tropatt:// URI 提供。

| 资源 URI | 用途 | MIME | Auth | 优先级 |
|-------------|-----------|------|------|-----------|
| ``tropatt://server/about`` | MCP 服务器概述 | text/markdown | auth | 1.0 |
| ``tropatt://server/tools`` | 可用工具列表 | application/json | auth | 0.95 |
| ``tropatt://user/current`` | 当前用户 | application/json | auth | 0.9 |
| ``tropatt://server/api-map`` | API 领域映射 | text/markdown | auth | 0.8 |
| ``tropatt://server/api-endpoints`` | REST 端点清单 | application/json | auth | 0.75 |

---

## MCP Prompts

当前 MCP 版本不支持 Prompts。

---

## 危险 MCP 工具

> 所有写工具（create/update/delete）都需要相应权限。用户/角色/模块/核心更新/缓存相关工具仅限管理员。密码修改和 2FA 仅限本人。Impersonation 仅限管理员。

| Tool | 作用 | 危险原因 | Audit |
|------|-------------|-------------------|-------|
| `crm_start_impersonation` | 模拟其他用户身份 | 以他人身份获得完整访问权限 | 是 |
| `crm_create_user` | 创建用户 | 创建账户 | 是 |
| `crm_update_user` | 更新用户（密码、角色、root） | 更改访问权限 | 是 |
| `crm_delete_user` | 删除用户 | 账户丢失 | 是 |
| `crm_rotate_user_token` | 轮换 API 令牌 | 新令牌生效，旧令牌失效 | 是 |
| `crm_set_role_permissions` | 设置角色权限 | 更改 RBAC | 是 |
| `crm_create_role` | 创建角色 | 新角色 | 是 |
| `crm_delete_role` | 删除角色 | 角色丢失 | 是 |
| `crm_install_module` | 安装模块 | 服务器上的代码 | 是 |
| `crm_uninstall_module` | 卸载模块 | 模块数据丢失 | 是 |
| `crm_install_module_from_url` | 从 URL 安装 | 外部代码 | 是 |
| `crm_install_module_from_file` | 从文件安装 | 外部代码 | 是 |
| `crm_clear_cache` | 清除缓存 | 可能影响运行 | 是 |
| `crm_run_ops_jobs` | 运行队列 | 执行作业 | 是 |
| `crm_run_core_update_preflight` | 更新预检 | 准备更新 | 是 |
| `crm_purge_recycle_bin_item` | 永久清除 | 不可逆删除 | 是 |
| `crm_execute_ai_action` | 执行 AI 操作 | AI 可能修改数据 | 是 |
| `crm_create_import_job` | 导入数据 | 批量修改数据 | 是 |
| `crm_create_webhook` | 创建 Webhook | 外部调用 | 是 |
| `crm_change_profile_password` | 更改密码 | 撤销所有会话 | 是 |
| `crm_enable_2fa` | 启用 2FA | 无应用则无法登录 | 是 |
| `crm_request_password_reset` | 请求密码重置 | 包含令牌的邮件 | 是 |

---

## MCP → REST API 映射

MCP 通过安全层镜像 REST API。下面是关键工具与 REST 端点的映射，以及仅存在于 MCP 或仅存在于 REST 的项目。

| MCP 工具 | 类型 | REST 端点 | 控制器 | 差异 |
|----------|-----|---------------|------------|---------|
| `crm_search` | 工具 | `GET /api/v1/search` | SearchController | 紧凑响应 |
| `crm_list_tasks` | 工具 | `GET /api/v1/tasks` | TaskController::list | 简化过滤 |
| `crm_get_task` | 工具 | `GET /api/v1/tasks/{id}` | TaskController::get | 无内部 ID |
| `crm_create_task` | 工具 | `POST /api/v1/tasks` | TaskController::create | 创建者为当前用户 |
| `crm_update_task` | 工具 | `PATCH /api/v1/tasks/{id}` | TaskController::update | 无敏感字段 |
| `crm_delete_task` | 工具 | `DELETE /api/v1/tasks/{id}` | TaskController::delete | 软删除 |
| `crm_list_projects` | 工具 | `GET /api/v1/projects` | ProjectController::list | - |
| `crm_get_project` | 工具 | `GET /api/v1/projects/{id}` | ProjectController::get | - |
| `crm_create_project` | 工具 | `POST /api/v1/projects` | ProjectController::create | - |
| `crm_list_users` | 工具 | `GET /api/v1/users` | UserController::list | 无密码/令牌 |
| `crm_get_user` | 工具 | `GET /api/v1/users/{id}` | UserController::get | 无密码 |
| `crm_list_chats` | 工具 | `GET /api/v1/chats` | ChatController::list | - |
| `crm_send_chat_message` | 工具 | `POST /api/v1/chats/{id}/messages` | ChatController::sendMessage | - |
| `crm_list_knowledge_pages` | 工具 | `GET /api/v1/knowledge/pages` | KnowledgeController::list | - |
| `crm_create_knowledge_page` | 工具 | `POST /api/v1/knowledge/pages` | KnowledgeController::create | - |
| `crm_list_api_endpoints` | 工具 | `无直接对应` | McpController::apiEndpointsIndex | 仅 MCP，读取 routes.php |

---

## 仅 MCP 项目（无 REST 对应）

| 工具 | 用途 | 备注 |
|------|-----------|-------------|
| `crm_list_api_endpoints` | REST 端点清单 | 返回 routes.php 中的完整路由列表 |
| `crm_get_knowledge_overview` | 知识库概览 | 聚合摘要 |
| `crm_get_knowledge_tree` | 页面树 | 递归结构 |
| `crm_get_knowledge_suggest` | 页面建议 | AI 驱动 |
| `crm_get_knowledge_analytics` | 知识库分析 | 聚合指标 |
| `crm_create_knowledge_ai_summary` | AI 页面摘要 | AI 驱动 |
| `crm_create_knowledge_ai_explanation` | AI 解释 | AI 驱动 |
| `crm_find_knowledge_ai_similar` | 相似页面 | AI 语义驱动 |
| `crm_create_knowledge_ai_checklist` | AI 清单 | AI 驱动 |
| `crm_create_knowledge_ai_faq_from_comments` | 从评论生成 AI FAQ | AI 驱动 |
| `crm_create_knowledge_ai_suggest_for_task` | 任务建议 | AI 驱动 |
| `crm_find_knowledge_ai_duplicates` | 重复项 | AI 驱动 |
| `crm_find_knowledge_ai_orphans` | 孤立项 | AI 驱动 |
| `crm_suggest_knowledge_ai_structure` | 建议结构 | AI 驱动 |
| `crm_get_project_summary` | 项目摘要 | 聚合数据 |
| `crm_get_project_timeline` | 项目时间线 | 类甘特图 |
| `crm_get_project_risks` | 项目风险 | 聚合数据 |
| `crm_get_project_workload` | 项目工作负载 | 聚合数据 |
| `crm_get_cycle_summary` | 冲刺摘要 | 聚合数据 |
| `crm_get_task_board` | 看板 | 按状态分组 |
| `crm_get_activity_feed` | 活动流 | 聚合信息流 |
| `crm_get_activity_history` | 活动历史 | 按实体 |
| `crm_get_dashboard_summary` | 仪表盘摘要 | 聚合计数器 |
| `crm_execute_ai_action` | 执行 AI 操作 | AI 驱动 |
| `crm_search_ai_semantic` | 语义搜索 | AI 驱动 |
| `crm_create_ai_dashboard_digest` | AI 摘要 | AI 驱动 |
| `crm_create_ai_my_day_plan` | AI 每日计划 | AI 驱动 |
| `crm_create_ai_my_week_plan` | AI 每周计划 | AI 驱动 |
| `crm_create_ai_task_summary` | AI 任务摘要 | AI 驱动 |
| `crm_create_ai_task_next_action` | AI 下一步行动 | AI 驱动 |
| `crm_create_ai_task_decomposition` | AI 任务分解 | AI 驱动 |
| `crm_create_ai_task_checklist` | AI 任务清单 | AI 驱动 |
| `crm_create_ai_task_quality` | AI 质量审查 | AI 驱动 |
| `crm_create_ai_project_summary` | AI 项目摘要 | AI 驱动 |
| `crm_create_ai_project_risks` | AI 项目风险 | AI 驱动 |
| `crm_create_ai_analytics_kpi_explanation` | AI KPI 解释 | AI 驱动 |
| `crm_create_ai_analytics_risks_explanation` | AI 风险解释 | AI 驱动 |
| `crm_create_ai_analytics_team_workload_summary` | AI 团队工作负载摘要 | AI 驱动 |
| `crm_get_saved_view_task_filters` | 视图过滤器 | 简化格式 |
| `crm_get_file_download_info` | 下载链接 | 无本地路径 |
| `crm_download_chat_attachment` | 下载附件 | 无本地路径 |
| `crm_download_export_job` | 下载导出 | 无本地路径 |

---

## 无 MCP 对应的 REST 端点

| REST 端点 | 用途 | 需要 MCP 覆盖 | 备注 |
|--------------|-----------|-------------------|-------------|
| `POST /api/v1/auth/login` | 登录 | 否 | MCP 使用 bearer 令牌 |
| `POST /api/v1/auth/logout` | 登出 | 否 | 会话通过 REST 管理 |
| `GET /api/v1/install/status` | 安装状态 | 否 | 仅限安装程序 |
| `POST /api/v1/internal/migration/*` | 迁移 | 否 | 内部操作 |
| `POST /api/v1/system/core-update/*` | 核心更新 | 部分 | MCP：crm_run_core_update_preflight、crm_get_core_update_session |
| `GET /api/v1/export/download/{id}` | 导出下载 | 是 | MCP：crm_download_export_job |
| `POST /api/v1/file/download/{id}` | 文件下载 | 是 | MCP：crm_get_file_download_info |
| `POST /api/v1/file/upload` | 文件上传 | 是 | MCP：crm_upload_file_base64 |

---

* 数据来源：McpController.php 和 mcp_permissions.php 权限注册表。文档与代码保持同步。
