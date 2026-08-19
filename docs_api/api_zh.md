# TropaTT CRM — API 文档

TropaTT 自托管 CRM 的完整 REST API 参考：基础 URL、认证、RBAC 权限、通用约定以及完整端点清单。

**语言：** [Русский](api_ru.md) · [English](api_en.md) · **中文**

> 最后更新：2026-08-15。权威来源：`upload/api/config/routes.php` 及模块路由文件。

## 概述

TropaTT 提供版本化的 JSON REST API。所有受保护端点都需要 Bearer 访问令牌，并在服务端强制执行 RBAC 权限校验。

### 基础 URL

```
https://{host}/api/v1/
```

### 版本

所有主要端点都以 `/api/v1/` 开头。部分内部端点没有版本前缀：

- `/install/*` — 浏览器安装器
- `/internal/migration/*` — 迁移

### 数据格式

| 格式 | 用途 |
|------|------|
| `application/json` | 主要请求/响应格式 |
| `multipart/form-data` | 文件上传 |
| Server-Sent Events | 实时更新（`/api/v1/events/stream`） |
| JSON-RPC | MCP 端点（`/api/v1/mcp`） |

## 认证

### Bearer 令牌

在 `Authorization` 头中传递访问令牌：

```
Authorization: Bearer <token>
```

浏览器会话使用 `session_id` Cookie 以及 `X-CSRF-Token` 头。

### 公开端点（无需认证）

`GET /api/v1/version`、`POST /api/v1/auth/login`、`POST /api/v1/security/password-reset`、`POST /api/v1/security/password-reset/confirm`、`POST /api/v1/security/invitations/accept` 以及安装器端点。

### RBAC 权限

每个受保护端点都声明所需权限（例如 `task.manage`、`user.view`）。令牌有效但缺少所需权限返回 `403`；令牌无效或缺失返回 `401`。

| 分组 | 权限键 |
|------|--------|
| 用户 | `user.view`、`user.manage` |
| 角色 | `role.view`、`role.manage` |
| 团队与部门 | `team.manage`、`department.manage` |
| 项目 | `project.manage` |
| 任务 | `task.manage` |
| 客户与公司 | `client.manage`、`company.manage`、`contact.manage`、`counterparty.manage` |
| 组织 | `organization.manage` |
| 知识 | `knowledge.view`、`knowledge.create`、`knowledge.edit`、`knowledge.delete`、`knowledge.publish`、`knowledge.comment`、`knowledge.manage` |
| 设置 | `settings.manage` |
| Webhook | `webhook.manage` |
| 日志 | `logs.view` |
| AI | `ai.use`、`ai.admin` |
| 导入 / 导出 | `import.manage`、`export.manage` |
| 审批 | `approval.manage` |
| 回收站 | `recycle_bin.manage` |
| API 客户端 | `api_client.view`、`api_client.manage` |
| 收件 | `intake.view`、`intake.create`、`intake.manage`、`intake.delete`、`intake.accept` |
| 想法 | `idea.view`、`idea.manage` |
| 聊天 | `chat.use` |

## 通用约定

### Headers（请求头）

| 请求头 | 必需 | 说明 |
|--------|:---:|------|
| `Authorization` | 是（受保护） | `Bearer <token>` |
| `Content-Type` | POST/PUT/PATCH | `application/json` 或 `multipart/form-data` |
| `Accept` | 否 | `application/json`（默认） |
| `X-CSRF-Token` | Cookie 认证 | CSRF 令牌 |
| `X-Request-Id` | 否 | 用于追踪的请求 ID |
| `X-Correlation-Id` | 否 | 关联 ID |
| `X-Idempotency-Key` | 否 | 幂等键 |
| `X-Locale` | 否 | 语言：`ru-ru` 或 `en-gb` |

### 成功响应（2xx）

```json
{
  "success": true,
  "data": { },
  "meta": { "cursor": "next_cursor_string", "has_more": true }
}
```

### 错误响应（4xx/5xx）

```json
{
  "success": false,
  "error": { "code": "ERROR_CODE", "message": "人类可读的描述" }
}
```

### 校验错误（422）

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "errors": { "field_name": ["错误消息"] }
  }
}
```

### HTTP 状态码

| 状态 | 用途 |
|------|------|
| 200 | 成功的 GET/PATCH/PUT/DELETE |
| 201 | 成功的 POST（已创建） |
| 204 | 成功且无响应体的 DELETE |
| 400 | 错误请求 / 无效 JSON |
| 401 | 未认证 / 无效令牌 |
| 403 | 权限不足 |
| 404 | 资源不存在 |
| 409 | 冲突 |
| 422 | 校验错误 |
| 429 | 超过速率限制 |
| 500 | 服务器内部错误 |

### 分页

基于游标：使用 `cursor` 查询参数和 `limit`，从响应中读取 `meta.cursor` 和 `meta.has_more`。

### 标识符

所有 ID 均为 ULID（26 个字符）。`row_version` 是用于更新时乐观锁的整数。

### 幂等性

`X-Idempotency-Key` 请求头可防止重复操作。

## OpenAPI 与 MCP

- **OpenAPI 规范** — 运行中的实例在 `GET /api/v1/docs/openapi` 提供机器可读的规范。
- **MCP（Model Context Protocol）** — CRM 在 `POST /api/v1/mcp` 暴露面向 AI 代理的 JSON-RPC MCP 端点。参见仓库中的 MCP 文档。

## 端点参考

> **注意：** 许多端点有两种 URL 变体 — RESTful 路径（`/api/v1/resources`）和别名（`/api/v1/resource/list`、`/api/v1/resource/create`）。别名以 `🔄` 标记。

### 安装与迁移

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/install/status` | 安装状态 | 否 | — | 检查 CRM 是否已安装 |
| GET, POST | `/install/check` | 检查数据库连接 | 否 | — | 连接参数验证 |
| POST | `/install/setup` | 启动安装 | 否 | — | 创建表、root 用户 |
| GET | `/internal/migration/status` | 迁移状态 | 是 | `settings.manage` | 当前迁移状态 |
| POST | `/internal/migration/up` | 应用迁移 | 是 | `settings.manage` | 运行待处理迁移 |
| GET | `/internal/migration/dry-run` | 试运行迁移 | 是 | `settings.manage` | 预览更改 |
| GET | `/internal/migration/rollback-check` | 检查回滚 | 是 | `settings.manage` | 检查回滚可行性 |

### 健康与版本

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/health/status` | 基本健康检查 | 是 | — | 服务状态 |
| GET | `/api/v1/health/deep` | 深度健康检查 | 是 | — | 检查数据库、缓存、AI |
| GET | `/api/v1/version` | CRM 版本（公开） | 否 | — | 无需认证的当前版本 |
| POST | `/api/v1/mcp` | Model Context Protocol | 是 | — | 面向 AI 代理的 JSON-RPC |

### 核心更新

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/core/version` | 核心版本 | 是 | `settings.manage` | 详细的版本信息 |
| GET | `/api/v1/core/updates/status` | 更新状态 | 是 | `settings.manage` | 可用更新 |
| POST | `/api/v1/core/updates/check` | 检查更新 | 是 | `settings.manage` | 请求检查新版本 |
| GET | `/api/v1/core/updates/changes` | 变更列表 | 是 | `settings.manage` | 版本间变更日志 |
| POST | `/api/v1/core/updates/preflight` | 预检检查 | 是 | `settings.manage` | 更新就绪检查 |
| POST | `/api/v1/core/updates/session` | 更新会话 | 是 | `settings.manage` | 创建更新会话 |
| GET | `/api/v1/core/updates/history` | 更新历史 | 是 | `settings.manage` | 历史更新日志 |
| GET | `/api/v1/core/updates/log/{job_id}` | 更新日志 | 是 | `settings.manage` | 特定更新的详情 |
| POST | `/api/v1/core/updates/recovery-key` | 恢复密钥 | 是 | `settings.manage` | 为更新会话签发恢复密钥 |

### 认证

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/auth/login` | 登录 | 否 | — | 认证，签发令牌 |
| POST | `/api/v1/auth/logout` | 退出登录 | 是 | — | 会话终止 |
| GET | `/api/v1/auth/me` | 当前用户 | 是 | — | 已认证用户数据 |
| GET | `/api/v1/auth/menu` | 用户菜单 | 是 | — | 角色菜单结构 |
| GET | `/api/v1/auth/menu/preferences` | 菜单偏好 | 是 | — | 条目可见性设置 |
| PUT, PATCH | `/api/v1/auth/menu/preferences` | 保存菜单偏好 | 是 | — | 更新可见性设置 |
| GET | `/api/v1/roles/{public_id}/menu-template` | 角色菜单模板 | 是 | `role.manage` | 角色菜单模板 |
| PUT, PATCH | `/api/v1/roles/{public_id}/menu-template` | 保存角色菜单模板 | 是 | `role.manage` | 更新模板 |
| GET | `/api/v1/teams/{public_id}/menu-template` | 团队菜单模板 | 是 | `team.manage` | 团队菜单模板 |
| PUT, PATCH | `/api/v1/teams/{public_id}/menu-template` | 保存团队菜单模板 | 是 | `team.manage` | 更新模板 |
| GET | `/api/v1/users/{public_id}/menu-preferences` | 用户菜单偏好（管理员） | 是 | `user.manage` | 管理员管理 |
| PUT, PATCH | `/api/v1/users/{public_id}/menu-preferences` | 保存用户菜单偏好（管理员） | 是 | `user.manage` | 管理员管理 |

### 遥测

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/telemetry/frontend-event` | 前端事件 | 是 | — | 客户端遥测 |
| POST | `/api/v1/telemetry/csp-report` | CSP 报告 | 否 | — | Content Security Policy 违规 |
| POST | `/api/v1/telemetry/login-debug` | 登录调试日志 | 是 | `logs.view` | 登录调试信息 |

### 用户

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/users` 🔄 | 用户列表 | 是 | `user.view` | 基于游标的分页 |
| POST | `/api/v1/users` 🔄 | 创建用户 | 是 | `user.manage` | — |
| GET | `/api/v1/users/{public_id}` 🔄 | 用户详情 | 是 | `user.view` | — |
| PATCH, PUT | `/api/v1/users/{public_id}` 🔄 | 更新用户 | 是 | `user.manage` | 乐观锁（`row_version`） |
| DELETE | `/api/v1/users/{public_id}` 🔄 | 停用用户 | 是 | `user.manage` | 软删除 |
| GET | `/api/v1/users/{public_id}/tokens` 🔄 | 用户令牌 | 是 | `user.view` | — |
| POST | `/api/v1/users/{public_id}/tokens/rotate` 🔄 | 轮换令牌 | 是 | `user.manage` | — |
| DELETE | `/api/v1/users/{public_id}/tokens` 🔄 | 撤销令牌 | 是 | `user.manage` | — |
| GET | `/api/v1/users/{public_id}/activity` 🔄 | 用户活动 | 是 | `user.view` | 活动流 |

### 角色

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/roles` 🔄 | 角色列表 | 是 | `role.view` | — |
| POST | `/api/v1/roles` 🔄 | 创建角色 | 是 | `role.manage` | 仅 root |
| PATCH, PUT | `/api/v1/roles/{public_id}` 🔄 | 更新角色 | 是 | `role.manage` | 仅 root |
| DELETE | `/api/v1/roles/{public_id}` 🔄 | 删除角色 | 是 | `role.manage` | 仅 root |

### 权限

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/permissions` 🔄 | 所有系统权限 | 是 | `role.view` | 参考 |
| GET | `/api/v1/roles/{public_id}/permissions` 🔄 | 角色权限 | 是 | `role.view` | — |
| PUT, PATCH | `/api/v1/roles/{public_id}/permissions` 🔄 | 分配角色权限 | 是 | `role.manage` | 仅 root |
| GET | `/api/v1/admin/role-matrix` 🔄 | 角色矩阵 | 是 | `role.manage` | — |
| PUT, PATCH | `/api/v1/admin/role-matrix` 🔄 | 更新角色矩阵 | 是 | `role.manage` | 仅 root |

### API 客户端

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/api-clients` 🔄 | API 客户端列表 | 是 | `api_client.view` | — |
| POST | `/api/v1/api-clients` 🔄 | 创建API 客户端 | 是 | `api_client.manage` | — |
| GET | `/api/v1/api-clients/{public_id}` 🔄 | API 客户端详情 | 是 | `api_client.view` | — |
| PATCH, PUT | `/api/v1/api-clients/{public_id}` 🔄 | 更新API 客户端 | 是 | `api_client.manage` | — |
| DELETE | `/api/v1/api-clients/{public_id}` 🔄 | 删除API 客户端 | 是 | `api_client.manage` | — |
| GET | `/api/v1/api-clients/{public_id}/keys` 🔄 | 客户端密钥列表 | 是 | `api_client.view` | — |
| POST | `/api/v1/api-clients/{public_id}/keys` 🔄 | 签发密钥 | 是 | `api_client.manage` | — |
| POST | `/api/v1/api-keys/{public_id}/rotate` 🔄 | 轮换密钥 | 是 | `api_client.manage` | — |
| POST, DELETE | `/api/v1/api-keys/{public_id}/revoke` 🔄 | 撤销密钥 | 是 | `api_client.manage` | — |
| GET | `/api/v1/api-keys/{public_id}/usage` 🔄 | 密钥使用 | 是 | `api_client.view` | — |

### 团队与部门

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/teams` 🔄 | 团队列表 | 是 | `team.manage` | — |
| POST | `/api/v1/teams` 🔄 | 创建团队 | 是 | `team.manage` | — |
| GET | `/api/v1/teams/{public_id}` 🔄 | 团队详情 | 是 | `team.manage` | — |
| PATCH, PUT | `/api/v1/teams/{public_id}` 🔄 | 更新团队 | 是 | `team.manage` | — |
| DELETE | `/api/v1/teams/{public_id}` 🔄 | 删除团队 | 是 | `team.manage` | — |
| GET | `/api/v1/departments` 🔄 | 部门列表 | 是 | `department.manage` | — |
| POST | `/api/v1/departments` 🔄 | 创建部门 | 是 | `department.manage` | — |
| GET | `/api/v1/departments/{public_id}` 🔄 | 部门详情 | 是 | `department.manage` | — |
| PATCH, PUT | `/api/v1/departments/{public_id}` 🔄 | 更新部门 | 是 | `department.manage` | — |
| DELETE | `/api/v1/departments/{public_id}` 🔄 | 删除部门 | 是 | `department.manage` | — |

### 公司、客户、联系人、交易方

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/companies` 🔄 | 公司列表 | 是 | `company.manage` | — |
| POST | `/api/v1/companies` 🔄 | 创建公司 | 是 | `company.manage` | — |
| GET | `/api/v1/companies/{public_id}` 🔄 | 公司详情 | 是 | `company.manage` | — |
| PATCH, PUT | `/api/v1/companies/{public_id}` 🔄 | 更新公司 | 是 | `company.manage` | — |
| DELETE | `/api/v1/companies/{public_id}` 🔄 | 删除公司 | 是 | `company.manage` | — |
| GET | `/api/v1/clients` 🔄 | 客户列表 | 是 | `client.manage` | — |
| POST | `/api/v1/clients` 🔄 | 创建客户 | 是 | `client.manage` | — |
| GET | `/api/v1/clients/{public_id}` 🔄 | 客户详情 | 是 | `client.manage` | — |
| PATCH, PUT | `/api/v1/clients/{public_id}` 🔄 | 更新客户 | 是 | `client.manage` | — |
| DELETE | `/api/v1/clients/{public_id}` 🔄 | 删除客户 | 是 | `client.manage` | — |
| GET | `/api/v1/counterparties` 🔄 | 交易方列表 | 是 | `counterparty.manage` | 按类型、搜索过滤 |
| POST | `/api/v1/counterparties` 🔄 | 创建交易方 | 是 | `counterparty.manage` | — |
| GET | `/api/v1/counterparties/{public_id}` 🔄 | 交易方详情 | 是 | `counterparty.manage` | — |
| PATCH, PUT | `/api/v1/counterparties/{public_id}` 🔄 | 更新交易方 | 是 | `counterparty.manage` | — |
| DELETE | `/api/v1/counterparties/{public_id}` 🔄 | 删除交易方 | 是 | `counterparty.manage` | — |
| GET | `/api/v1/contacts` 🔄 | 联系人列表 | 是 | `contact.manage` | — |
| POST | `/api/v1/contacts` 🔄 | 创建联系人 | 是 | `contact.manage` | 需要 `full_name`（最大 255） |
| GET | `/api/v1/contacts/{public_id}` 🔄 | 联系人详情 | 是 | `contact.manage` | — |
| PATCH, PUT | `/api/v1/contacts/{public_id}` 🔄 | 更新联系人 | 是 | `contact.manage` | — |
| DELETE | `/api/v1/contacts/{public_id}` 🔄 | 删除联系人 | 是 | `contact.manage` | — |

### 外部用户（客户端门户账号）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/external-users/invite` | 邀请联系人加入客户门户 | 是 | `contact.manage` | 为联系人创建 `external_guest` 账户；要求已关联交易方且邮箱有效 |
| POST | `/api/v1/external-users/accept` | 接受邀请并设置密码 | 否 | — | 公开接口；需要邀请 `token` 和 `password`（至少 8 位） |
| GET | `/api/v1/external-users` | 外部（门户）用户列表 | 是 | `contact.manage` | — |
| POST | `/api/v1/external-users/{public_id}/deactivate` | 撤销门户访问权限 | 是 | `contact.manage` | 停用外部用户账户 |

**访问模型：** 外部用户（`is_external = true`）通过行级安全策略仅能看到自己所属交易方的项目和任务（`ProjectService`/`TaskService` 在 SQL 层按 `client_public_id` 过滤，而非在 PHP 数组中过滤）。除权限校验外，`routes.php` 中的硬性路由白名单（`external_ok`，在 `App::run()` 中集中校验）将外部会话限制在 `auth/me`、`auth/logout`、`auth/menu`、`projects`（列表/详情）、`tasks`（列表/详情/创建/评论/文件）、`files`（上传/获取/下载）和 `notifications` 范围内——其余任何接口即便权限检查本身允许，也会返回 `403 EXTERNAL_ACCESS_DENIED`。前端页面同样在页面级别做了镜像限制：外部账户的导航菜单和可访问路由仅限于「项目」「任务」「通知」。

### 客户门户

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/client-cabinet/projects` 🔄 | 客户项目 | 是 | `client.manage` | 仅自己的项目 |
| GET | `/api/v1/client-cabinet/projects/{public_id}` 🔄 | 客户项目详情 | 是 | `client.manage` | — |
| GET | `/api/v1/client-cabinet/projects/{public_id}/tasks` 🔄 | 客户项目任务 | 是 | `client.manage` | — |

### 组织

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/organizations` 🔄 | 组织列表 | 是 | `organization.manage` | 过滤器：`q`、`type` |
| POST | `/api/v1/organizations` 🔄 | 创建组织 | 是 | `organization.manage` | — |
| GET | `/api/v1/organizations/{public_id}` 🔄 | 组织详情 | 是 | `organization.manage` | — |
| PATCH, PUT | `/api/v1/organizations/{public_id}` 🔄 | 更新组织 | 是 | `organization.manage` | 乐观锁 |
| DELETE | `/api/v1/organizations/{public_id}` 🔄 | 删除组织 | 是 | `organization.manage` | — |
| GET | `/api/v1/organizations/{public_id}/members` 🔄 | 组织成员 | 是 | `organization.manage` | — |
| POST | `/api/v1/organizations/{public_id}/members` 🔄 | 添加成员 | 是 | `organization.manage` | — |
| DELETE | `/api/v1/organizations/{public_id}/members/{user_public_id}` 🔄 | 删除成员 | 是 | `organization.manage` | — |

### 状态、优先级、标签

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/statuses` 🔄 | 状态列表 | 是 | `task.manage` | 按 `scope`（task/project）过滤 |
| POST | `/api/v1/statuses` 🔄 | 创建状态 | 是 | `task.manage` | 需要 `title`、`code`（唯一）、`scope`（task/project）、`color`（HEX） |
| GET | `/api/v1/statuses/{public_id}` 🔄 | 状态详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/statuses/{public_id}` 🔄 | 更新状态 | 是 | `task.manage` | — |
| DELETE | `/api/v1/statuses/{public_id}` 🔄 | 删除状态 | 是 | `task.manage` | — |
| POST | `/api/v1/statuses/{public_id}/remap-delete` 🔄 | 删除并重新分配 | 是 | `task.manage` | 将任务移至其他状态 |
| GET | `/api/v1/priorities` 🔄 | 优先级列表 | 是 | `task.manage` | — |
| POST | `/api/v1/priorities` 🔄 | 创建优先级 | 是 | `task.manage` | — |
| GET | `/api/v1/priorities/{public_id}` 🔄 | 优先级详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/priorities/{public_id}` 🔄 | 更新优先级 | 是 | `task.manage` | — |
| DELETE | `/api/v1/priorities/{public_id}` 🔄 | 删除优先级 | 是 | `task.manage` | — |
| GET | `/api/v1/tags` 🔄 | 标签列表 | 是 | `task.manage` | — |
| POST | `/api/v1/tags` 🔄 | 创建标签 | 是 | `task.manage` | — |
| GET | `/api/v1/tags/{public_id}` 🔄 | 标签详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/tags/{public_id}` 🔄 | 更新标签 | 是 | `task.manage` | — |
| DELETE | `/api/v1/tags/{public_id}` 🔄 | 删除标签 | 是 | `task.manage` | — |

### 任务-标签绑定

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{task_public_id}/tags` 🔄 | 任务标签 | 是 | `task.manage` | — |
| POST | `/api/v1/tasks/{task_public_id}/tags/{tag_public_id}` 🔄 | 关联标签 | 是 | `task.manage` | — |
| DELETE | `/api/v1/tasks/{task_public_id}/tags/{tag_public_id}` 🔄 | 取消关联标签 | 是 | `task.manage` | — |

### 项目

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/projects` 🔄 | 项目列表 | 是 | `project.manage` | 基于游标，过滤器：`status`、`client_public_id`、`q` |
| POST | `/api/v1/projects` 🔄 | 创建项目 | 是 | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}` 🔄 | 项目详情 | 是 | `project.manage` | — |
| PATCH, PUT | `/api/v1/projects/{public_id}` 🔄 | 更新项目 | 是 | `project.manage` | 乐观锁 |
| DELETE | `/api/v1/projects/{public_id}` 🔄 | 归档项目 | 是 | `project.manage` | 软删除 |
| GET | `/api/v1/projects/{public_id}/timeline` 🔄 | 时间线（甘特图） | 是 | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/summary` 🔄 | 项目摘要 | 是 | `project.manage` | 进度、任务、里程碑 |
| GET | `/api/v1/projects/{public_id}/milestones-summary` 🔄 | 里程碑摘要 | 是 | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/risks` 🔄 | 项目风险 | 是 | `project.manage` | — |
| GET | `/api/v1/projects/{public_id}/workload` 🔄 | 上传成员 | 是 | `project.manage` | — |

### 任务

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks` 🔄 | 任务列表 | 是 | `task.manage` | 基于游标，带过滤器 |
| POST | `/api/v1/tasks` 🔄 | 创建任务 | 是 | `task.manage` | — |
| GET | `/api/v1/tasks/board` 🔄 | 看板 | 是 | `task.manage` | 按状态分组 |
| POST | `/api/v1/tasks/bulk` 🔄 | 批量更新 | 是 | `task.manage` | — |
| GET | `/api/v1/tasks/by-key/{task_key}` | 按键获取任务 | 是 | `task.manage` | 人类可读的键 |
| GET | `/api/v1/tasks/{public_id}` 🔄 | 任务详情 | 是 | `task.manage` | 包含评论、文件等 |
| PATCH, PUT | `/api/v1/tasks/{public_id}` 🔄 | 更新任务 | 是 | `task.manage` | 乐观锁，`identity_edit_forbidden` |
| DELETE | `/api/v1/tasks/{public_id}` 🔄 | 删除任务（回收站） | 是 | `task.manage` | 软删除 |
| POST | `/api/v1/tasks/{public_id}/move` 🔄 | 移动看板上的任务 | 是 | `task.manage` | 请求体：`to_status_public_id`（或 `to_status`） |
| GET | `/api/v1/tasks/{public_id}/activity` | 任务活动 | 是 | `task.manage` | 活动流 |
| GET | `/api/v1/tasks/{public_id}/comments` 🔄 | 任务评论 | 是 | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/comments` 🔄 | 添加评论 | 是 | `task.manage` | 请求体：`body`（字符串，最大 8000）。返回带 `public_id` 的新评论 |
| GET | `/api/v1/tasks/{public_id}/files` | 任务文件 | 是 | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/knowledge-pages` | 关联知识页面 | 是 | `task.manage`, `knowledge.view` | 将知识页面关联到任务 |

### 任务关联

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/relations` | 任务关联 | 是 | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/relations` | 创建关联 | 是 | `task.manage` | — |
| DELETE | `/api/v1/task-relations/{public_id}` | 删除关联 | 是 | `task.manage` | — |
| GET | `/api/v1/task-relations/search-tasks` | 搜索关联任务 | 是 | `task.manage` | — |

### 评论

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| PATCH, PUT | `/api/v1/comments/{public_id}` 🔄 | 更新评论 | 是 | — | — |
| DELETE | `/api/v1/comments/{public_id}` 🔄 | 删除评论 | 是 | — | — |

### 评论草稿

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | 获取草稿 | 是 | `task.manage` | — |
| POST, PUT, PATCH | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | 保存草稿 | 是 | `task.manage` | — |
| DELETE | `/api/v1/tasks/{public_id}/comment-draft` 🔄 | 删除草稿 | 是 | `task.manage` | — |

### 子任务

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/subtasks` 🔄 | 任务子任务 | 是 | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/subtasks` 🔄 | 创建子任务 | 是 | `task.manage` | — |
| GET | `/api/v1/subtasks/{public_id}` 🔄 | 子任务详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/subtasks/{public_id}` 🔄 | 更新子任务 | 是 | `task.manage` | — |
| DELETE | `/api/v1/subtasks/{public_id}` 🔄 | 删除子任务 | 是 | `task.manage` | — |

### 清单

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/tasks/{public_id}/checklists` 🔄 | 任务清单 | 是 | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/checklists` 🔄 | 创建清单 | 是 | `task.manage` | — |
| GET | `/api/v1/checklists/{public_id}` 🔄 | 清单详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/checklists/{public_id}` 🔄 | 更新清单 | 是 | `task.manage` | — |
| DELETE | `/api/v1/checklists/{public_id}` 🔄 | 删除清单 | 是 | `task.manage` | — |
| GET | `/api/v1/checklists/{public_id}/items` 🔄 | 清单条目 | 是 | `task.manage` | — |
| POST | `/api/v1/checklists/{public_id}/items` 🔄 | 添加条目 | 是 | `task.manage` | — |
| GET | `/api/v1/checklist-items/{public_id}` 🔄 | 条目详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/checklist-items/{public_id}` 🔄 | 更新条目 | 是 | `task.manage` | — |
| DELETE | `/api/v1/checklist-items/{public_id}` 🔄 | 删除条目 | 是 | `task.manage` | — |

### 工作周期

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/cycles` | 周期列表 | 是 | `task.manage` | — |
| POST | `/api/v1/cycles` | 创建周期 | 是 | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}` | 周期详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/cycles/{public_id}` | 更新周期 | 是 | `project.manage` | — |
| DELETE | `/api/v1/cycles/{public_id}` | 删除周期 | 是 | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/start` | 启动周期 | 是 | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/complete` | 完成周期 | 是 | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/reopen` | 重新打开周期 | 是 | `project.manage` | — |
| POST | `/api/v1/cycles/{public_id}/archive` | 归档周期 | 是 | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/tasks` | 周期任务 | 是 | `task.manage` | — |
| POST | `/api/v1/cycles/{public_id}/tasks` | 添加任务到周期 | 是 | `project.manage` | — |
| DELETE | `/api/v1/cycles/{public_id}/tasks/{task_public_id}` | 删除周期中的任务 | 是 | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/summary` | 周期摘要 | 是 | `task.manage` | — |
| POST | `/api/v1/cycles/{public_id}/transfer-unfinished` | 转移未完成项 | 是 | `project.manage` | — |

### 项目模块

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/project-modules` | 项目模块列表 | 是 | `project.manage` | — |
| POST | `/api/v1/project-modules` | 创建模块 | 是 | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}` | 模块详情 | 是 | `project.manage` | — |
| PATCH, PUT | `/api/v1/project-modules/{public_id}` | 更新模块 | 是 | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}` | 删除模块 | 是 | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/archive` | 归档模块 | 是 | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/tasks` | 模块任务 | 是 | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/tasks` | 添加任务到模块 | 是 | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}/tasks/{task_public_id}` | 删除模块中的任务 | 是 | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/members` | 模块成员 | 是 | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/members` | 添加成员 | 是 | `project.manage` | — |
| DELETE | `/api/v1/project-modules/{public_id}/members/{user_public_id}` | 删除成员 | 是 | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/links` | 模块链接 | 是 | `project.manage` | — |
| POST | `/api/v1/project-modules/{public_id}/links` | 添加链接 | 是 | `project.manage` | — |
| PATCH, PUT | `/api/v1/project-module-links/{public_id}` | 更新链接 | 是 | `project.manage` | — |
| DELETE | `/api/v1/project-module-links/{public_id}` | 删除链接 | 是 | `project.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/summary` | 模块摘要 | 是 | `project.manage` | — |

### 里程碑

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/milestones` 🔄 | 里程碑列表 | 是 | `project.manage` | 需要 `project_public_id` 或 `project_public_ids`（逗号分隔） |
| POST | `/api/v1/milestones` 🔄 | 创建里程碑 | 是 | `project.manage` | 需要 `title`、`project_public_id`。可选：`due_at`（YYYY-MM-DD） |
| GET | `/api/v1/milestones/{public_id}` 🔄 | 里程碑详情 | 是 | `project.manage` | — |
| PATCH, PUT | `/api/v1/milestones/{public_id}` 🔄 | 更新里程碑 | 是 | `project.manage` | — |
| DELETE | `/api/v1/milestones/{public_id}` 🔄 | 删除里程碑 | 是 | `project.manage` | — |

### 依赖

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/dependencies` 🔄 | 依赖列表 | 是 | `task.manage` | — |
| POST | `/api/v1/dependencies` 🔄 | 创建依赖 | 是 | `task.manage` | 类型：FS、SS、FF、SF、BLOCKS |
| DELETE | `/api/v1/dependencies/{public_id}` 🔄 | 删除依赖 | 是 | `task.manage` | — |

### 文件

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/files` | 上传文件 | 是 | `task.manage` | `multipart/form-data`，最大 20MB |
| GET | `/api/v1/files/{public_id}` | 文件元数据 | 是 | `task.manage` | — |
| GET | `/api/v1/files/{public_id}/download` | 下载文件 | 是 | `task.manage` | 二进制响应 |
| DELETE | `/api/v1/files/{public_id}` | 删除文件 | 是 | `task.manage` | — |

### 模板

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/template/tasks` 🔄 | 任务模板列表 | 是 | `task.manage` | — |
| POST | `/api/v1/template/tasks` 🔄 | 创建任务模板 | 是 | `task.manage` | — |
| GET | `/api/v1/template/tasks/{public_id}` 🔄 | 任务模板详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/template/tasks/{public_id}` 🔄 | 更新任务模板 | 是 | `task.manage` | — |
| DELETE | `/api/v1/template/tasks/{public_id}` 🔄 | 删除任务模板 | 是 | `task.manage` | — |
| POST | `/api/v1/template/tasks/{public_id}/apply` 🔄 | 应用任务模板 | 是 | `task.manage` | 从模板创建任务 |
| GET | `/api/v1/template/projects` 🔄 | 项目模板列表 | 是 | `project.manage` | — |
| POST | `/api/v1/template/projects` 🔄 | 创建项目模板 | 是 | `project.manage` | — |
| GET | `/api/v1/template/projects/{public_id}` 🔄 | 项目模板详情 | 是 | `project.manage` | — |
| PATCH, PUT | `/api/v1/template/projects/{public_id}` 🔄 | 更新项目模板 | 是 | `project.manage` | — |
| DELETE | `/api/v1/template/projects/{public_id}` 🔄 | 删除项目模板 | 是 | `project.manage` | — |
| POST | `/api/v1/template/projects/{public_id}/apply` 🔄 | 应用项目模板 | 是 | `project.manage` | 从模板创建项目 |

### 通知

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/notifications` 🔄 | 通知列表 | 是 | `task.manage` | — |
| POST | `/api/v1/notifications` 🔄 | 创建通知 | 是 | `task.manage` | — |
| GET | `/api/v1/notifications/counters` 🔄 | 未读计数 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/notifications/{public_id}/read` 🔄 | 标记为已读 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/notifications/{public_id}/unread` 🔄 | 标记为未读 | 是 | `task.manage` | — |
| POST | `/api/v1/notifications/mark-all-read` 🔄 | 全部标记为已读 | 是 | `task.manage` | — |
| GET | `/api/v1/notifications/push-subscriptions` 🔄 | 推送订阅 | 是 | `task.manage` | — |
| POST | `/api/v1/notifications/push-subscriptions` 🔄 | 创建推送订阅 | 是 | `task.manage` | — |
| DELETE | `/api/v1/notifications/push-subscriptions/{public_id}` 🔄 | 删除推送订阅 | 是 | `task.manage` | — |
| POST | `/api/v1/notifications/push-test` 🔄 | 测试推送通知 | 是 | `task.manage` | — |

### 提醒

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/reminders` 🔄 | 提醒列表 | 是 | `task.manage` | — |
| POST | `/api/v1/reminders` 🔄 | 创建提醒 | 是 | `task.manage` | — |
| GET | `/api/v1/reminders/{public_id}` 🔄 | 提醒详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/reminders/{public_id}` 🔄 | 更新提醒 | 是 | `task.manage` | — |
| DELETE | `/api/v1/reminders/{public_id}` 🔄 | 删除提醒 | 是 | `task.manage` | — |

### 日历

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/calendar/events` 🔄 | 日历事件 | 是 | `task.manage` | 参数：`from`、`to` |
| POST | `/api/v1/calendar/events` 🔄 | 创建事件 | 是 | `task.manage` | — |
| GET | `/api/v1/calendar/events/{public_id}` 🔄 | 事件详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/calendar/events/{public_id}` 🔄 | 更新事件 | 是 | `task.manage` | — |
| DELETE | `/api/v1/calendar/events/{public_id}` 🔄 | 删除事件 | 是 | `task.manage` | — |
| GET | `/api/v1/calendar/my-day` 🔄 | 我的一天 | 是 | `task.manage` | 按天聚合 |
| GET | `/api/v1/calendar/my-week` 🔄 | 我的一周 | 是 | `task.manage` | 按周聚合 |
| GET | `/api/v1/calendar/my-month` 🔄 | 我的一个月 | 是 | `task.manage` | 按月聚合 |
| GET | `/api/v1/calendar/business` 🔄 | 业务日历 | 是 | `settings.manage` | — |
| POST | `/api/v1/calendar/business` 🔄 | 创建业务日历 | 是 | `settings.manage` | — |
| GET | `/api/v1/calendar/business/{public_id}` 🔄 | 业务日历详情 | 是 | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/business/{public_id}` 🔄 | 更新业务日历 | 是 | `settings.manage` | — |
| DELETE | `/api/v1/calendar/business/{public_id}` 🔄 | 删除业务日历 | 是 | `settings.manage` | — |
| GET | `/api/v1/calendar/holidays` 🔄 | 假期 | 是 | `settings.manage` | — |
| POST | `/api/v1/calendar/holidays` 🔄 | 创建假期 | 是 | `settings.manage` | — |
| GET | `/api/v1/calendar/holidays/{public_id}` 🔄 | 假期详情 | 是 | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/holidays/{public_id}` 🔄 | 更新假期 | 是 | `settings.manage` | — |
| DELETE | `/api/v1/calendar/holidays/{public_id}` 🔄 | 删除假期 | 是 | `settings.manage` | — |
| GET | `/api/v1/calendar/working-hours` 🔄 | 工作时间 | 是 | `settings.manage` | — |
| POST | `/api/v1/calendar/working-hours` 🔄 | 创建工作时间 | 是 | `settings.manage` | — |
| GET | `/api/v1/calendar/working-hours/{public_id}` 🔄 | 工作时间详情 | 是 | `settings.manage` | — |
| PATCH, PUT | `/api/v1/calendar/working-hours/{public_id}` 🔄 | 更新工作时间 | 是 | `settings.manage` | — |
| DELETE | `/api/v1/calendar/working-hours/{public_id}` 🔄 | 删除工作时间 | 是 | `settings.manage` | — |

### 页面数据（前端 API）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/pages/my-day` | "我的一天"数据 | 是 | `task.manage` | SPA 页面数据 |
| GET | `/api/v1/pages/kanban` | 看板数据 | 是 | `task.manage` | SPA 页面数据 |
| GET | `/api/v1/pages/my-week` | "我的一周"数据 | 是 | `task.manage` | SPA 页面数据 |

### 工时记录

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/worklogs` 🔄 | 时间记录列表 | 是 | `task.manage` | — |
| POST | `/api/v1/worklogs` 🔄 | 创建时间记录 | 是 | `task.manage` | 需要 `task_public_id`、`minutes_spent`（整数，分钟）、`logged_at`（YYYY-MM-DD） |
| GET | `/api/v1/worklogs/summary` | 时间摘要 | 是 | `task.manage` | — |
| GET | `/api/v1/worklogs/earnings` | 时间收益 | 是 | `task.manage` | — |
| GET | `/api/v1/worklogs/matrix` | 时间矩阵 | 是 | `task.manage` | — |
| GET | `/api/v1/worklogs/detail` | 时间记录详情 | 是 | `task.manage` | 需要 `project_public_id` |
| GET | `/api/v1/worklogs/{public_id}` 🔄 | 记录详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/worklogs/{public_id}` 🔄 | 更新记录 | 是 | `task.manage` | 可更新字段：`minutes_spent`、`description`、`logged_at` |
| DELETE | `/api/v1/worklogs/{public_id}` 🔄 | 删除记录 | 是 | `task.manage` | — |
| GET | `/api/v1/worklogs/task/{public_id}` | 按任务的时间 | 是 | `task.manage` | — |

### 仪表盘与分析

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/dashboard/summary` 🔄 | 仪表盘摘要 | 是 | `task.manage` | 逾期任务、未分配 |
| GET | `/api/v1/analytics/summary` 🔄 | 分析摘要 | 是 | `task.manage` | — |
| GET | `/api/v1/analytics/projects` 🔄 | 按项目分析 | 是 | `task.manage` | — |
| GET | `/api/v1/analytics/users` 🔄 | 按用户分析 | 是 | `task.manage` | — |
| GET | `/api/v1/dashboard/widgets` | 仪表盘小组件 | 是 | — | 当前用户小组件 |
| PUT | `/api/v1/dashboard/widgets` | 保存小组件 | 是 | — | 更新当前用户小组件 |

### 搜索

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/search/global` 🔄 | 全局搜索 | 是 | `task.manage` | 跨所有实体 |
| GET | `/api/v1/search/suggestions` | 搜索建议 | 是 | `task.manage` | — |
| GET | `/api/v1/search/tasks` 🔄 | 搜索任务 | 是 | `task.manage` | — |
| GET | `/api/v1/search/projects` 🔄 | 搜索项目 | 是 | `task.manage` | — |
| GET | `/api/v1/search/clients` 🔄 | 搜索客户 | 是 | `task.manage` | — |
| GET | `/api/v1/search/counterparties` | 搜索交易方 | 是 | `task.manage` | — |

### 提及与回应

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/mentions` 🔄 | 提及列表 | 是 | `task.manage` | — |
| POST | `/api/v1/mentions` 🔄 | 创建提及 | 是 | `task.manage` | — |
| DELETE | `/api/v1/mentions/{public_id}` 🔄 | 删除提及 | 是 | `task.manage` | — |
| GET | `/api/v1/reactions` 🔄 | 回应列表 | 是 | `task.manage` | — |
| POST | `/api/v1/reactions` 🔄 | 添加回应 | 是 | `task.manage` | — |
| DELETE | `/api/v1/reactions/{public_id}` 🔄 | 删除回应 | 是 | `task.manage` | — |

### 订阅与收藏

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/subscriptions` 🔄 | 订阅列表 | 是 | `task.manage` | — |
| POST | `/api/v1/subscriptions` 🔄 | 创建订阅 | 是 | `task.manage` | — |
| DELETE | `/api/v1/subscriptions/{public_id}` 🔄 | 删除订阅 | 是 | `task.manage` | — |
| GET | `/api/v1/favorites` 🔄 | 收藏列表 | 是 | `task.manage` | — |
| POST | `/api/v1/favorites` 🔄 | 添加收藏 | 是 | `task.manage` | — |
| DELETE | `/api/v1/favorites/{public_id}` 🔄 | 删除从收藏 | 是 | `task.manage` | — |

### 已保存视图

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/views` 🔄 | 已保存视图列表 | 是 | `task.manage` | — |
| POST | `/api/v1/views` 🔄 | 创建已保存视图 | 是 | `task.manage` | — |
| GET | `/api/v1/views/{public_id}` | 已保存视图详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/views/{public_id}` 🔄 | 更新已保存视图 | 是 | `task.manage` | — |
| DELETE | `/api/v1/views/{public_id}` 🔄 | 删除已保存视图 | 是 | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/archive` | 归档已保存视图 | 是 | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/duplicate` | 复制已保存视图 | 是 | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/pin` | 固定视图 | 是 | `task.manage` | — |
| POST | `/api/v1/views/{public_id}/touch-last-used` | 更新last_used_at | 是 | `task.manage` | — |
| GET | `/api/v1/views/{public_id}/task-filters` | 视图任务过滤器 | 是 | `task.manage` | — |

### 活动与审计

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/activity/feed` 🔄 | 活动流 | 是 | `logs.view` | — |
| GET | `/api/v1/history/entity/{entity_type}/{public_id}` 🔄 | 实体历史 | 是 | `logs.view` | — |
| GET | `/api/v1/audit/list` 🔄 | 审计列表 | 是 | `logs.view` | — |
| GET | `/api/v1/audit/user/{public_id}` 🔄 | 按用户审计 | 是 | `logs.view` | — |
| GET | `/api/v1/audit/entity/{entity_type}/{public_id}` 🔄 | 按实体审计 | 是 | `logs.view` | — |

### 日志

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/logs/request` | 请求日志 | 是 | `logs.view` | — |
| GET | `/api/v1/logs/security` | 安全日志 | 是 | `logs.view` | — |
| GET | `/api/v1/logs/audit` | 审计日志 | 是 | `logs.view` | — |
| GET | `/api/v1/logs/frontend-errors/chart` | 前端错误图表 | 是 | `logs.view` | 前端错误聚合 |

### 设置与功能开关

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/settings` 🔄 | 设置列表 | 是 | `settings.manage` | — |
| GET | `/api/v1/settings/{name}` 🔄 | 设置值 | 是 | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/settings/{name}` 🔄 | 设置设置 | 是 | `settings.manage` | — |
| GET | `/api/v1/retention/metadata` 🔄 | 保留元数据 | 是 | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/retention/metadata` 🔄 | 设置保留 | 是 | `settings.manage` | — |
| GET | `/api/v1/feature-flags` 🔄 | 功能开关列表 | 是 | `feature_flag.manage` | — |
| PATCH, PUT | `/api/v1/feature-flags/{public_id}` 🔄 | 更新功能开关 | 是 | `feature_flag.manage` | — |

### 自定义字段

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/custom-fields` 🔄 | 字段列表 | 是 | `settings.manage` | — |
| POST | `/api/v1/custom-fields` 🔄 | 创建字段 | 是 | `settings.manage` | — |
| GET | `/api/v1/custom-fields/{public_id}` 🔄 | 字段详情 | 是 | `settings.manage` | — |
| PATCH, PUT | `/api/v1/custom-fields/{public_id}` 🔄 | 更新字段 | 是 | `settings.manage` | — |
| DELETE | `/api/v1/custom-fields/{public_id}` 🔄 | 删除字段 | 是 | `settings.manage` | — |
| GET | `/api/v1/custom-fields/values` 🔄 | 字段值 | 是 | `settings.manage` | — |
| POST, PUT, PATCH | `/api/v1/custom-fields/values` 🔄 | 设置值 | 是 | `settings.manage` | — |

### 工作流规则

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/workflow/rules` 🔄 | 规则列表 | 是 | `settings.manage` | — |
| POST | `/api/v1/workflow/rules` 🔄 | 创建规则 | 是 | `settings.manage` | — |
| GET | `/api/v1/workflow/rules/{public_id}` 🔄 | 规则详情 | 是 | `settings.manage` | — |
| PATCH, PUT | `/api/v1/workflow/rules/{public_id}` 🔄 | 更新规则 | 是 | `settings.manage` | — |
| DELETE | `/api/v1/workflow/rules/{public_id}` 🔄 | 删除规则 | 是 | `settings.manage` | — |
| POST | `/api/v1/workflow/rules/{public_id}/run-test` 🔄 | 测试规则运行 | 是 | `settings.manage` | — |
| GET | `/api/v1/workflow/runs` 🔄 | 运行历史 | 是 | `settings.manage` | — |

### SLA

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/sla/policies` 🔄 | SLA 策略列表 | 是 | `settings.manage` | — |
| POST | `/api/v1/sla/policies` 🔄 | 创建SLA 策略 | 是 | `settings.manage` | — |
| GET | `/api/v1/sla/policies/{public_id}` 🔄 | SLA 策略详情 | 是 | `settings.manage` | — |
| PATCH, PUT | `/api/v1/sla/policies/{public_id}` 🔄 | 更新SLA 策略 | 是 | `settings.manage` | — |
| DELETE | `/api/v1/sla/policies/{public_id}` 🔄 | 删除SLA 策略 | 是 | `settings.manage` | — |
| GET | `/api/v1/sla/report` 🔄 | SLA 报告 | 是 | `settings.manage` | — |
| POST | `/api/v1/sla/assign/{public_id}` 🔄 | 分配任务 SLA | 是 | `settings.manage` | — |

### 审批

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/approvals` 🔄 | 审批列表 | 是 | `approval.manage` | — |
| POST | `/api/v1/approvals` 🔄 | 创建审批 | 是 | `approval.manage` | — |
| GET | `/api/v1/approvals/{public_id}` 🔄 | 审批详情 | 是 | `approval.manage` | — |
| POST | `/api/v1/approvals/{public_id}/approve` 🔄 | 审批 | 是 | `approval.manage` | — |
| POST | `/api/v1/approvals/{public_id}/reject` 🔄 | 拒绝审批 | 是 | `approval.manage` | — |

### Webhook

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/webhooks` 🔄 | Webhook列表 | 是 | `webhook.manage` | — |
| POST | `/api/v1/webhooks` 🔄 | 创建Webhook | 是 | `webhook.manage` | 需要 `endpoint`（URL，最大 2048）、`events`（字符串数组） |
| PATCH, PUT | `/api/v1/webhooks/{public_id}` 🔄 | 更新Webhook | 是 | `webhook.manage` | — |
| DELETE | `/api/v1/webhooks/{public_id}` 🔄 | 删除Webhook | 是 | `webhook.manage` | — |
| GET | `/api/v1/webhooks/deliveries` 🔄 | 所有投递 | 是 | `webhook.manage` | — |
| GET | `/api/v1/webhooks/{public_id}/deliveries` 🔄 | Webhook 投递 | 是 | `webhook.manage` | — |
| POST | `/api/v1/webhooks/{public_id}/test` 🔄 | 测试Webhook | 是 | `webhook.manage` | — |

### 导入与导出

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/import/jobs` | 导入任务列表 | 是 | `import.manage` | — |
| POST | `/api/v1/import/jobs` | 创建导入任务 | 是 | `import.manage` | `multipart/form-data` |
| GET | `/api/v1/import/jobs/{public_id}` | 导入任务状态 | 是 | `import.manage` | — |
| POST | `/api/v1/import/jobs/{public_id}/cancel` | 取消导入 | 是 | `import.manage` | — |
| POST | `/api/v1/import/jobs/{public_id}/retry` | 重试导入 | 是 | `import.manage` | — |
| GET | `/api/v1/export/jobs` | 导出任务列表 | 是 | `export.manage` | — |
| POST | `/api/v1/export/jobs` | 创建导出任务 | 是 | `export.manage` | — |
| GET | `/api/v1/export/jobs/{public_id}` | 导出任务状态 | 是 | `export.manage` | — |
| GET | `/api/v1/export/jobs/{public_id}/download` | 下载导出 | 是 | `export.manage` | 二进制 |
| POST | `/api/v1/export/jobs/{public_id}/cancel` | 取消导出 | 是 | `export.manage` | — |
| POST | `/api/v1/export/jobs/{public_id}/retry` | 重试导出 | 是 | `export.manage` | — |

### 回收站

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/recycle-bin` 🔄 | 回收站列表 | 是 | `recycle_bin.manage` | — |
| POST | `/api/v1/recycle-bin/{public_id}/restore` 🔄 | 恢复 | 是 | `recycle_bin.manage` | — |
| DELETE, POST | `/api/v1/recycle-bin/{public_id}/purge` 🔄 | 清除（永久删除） | 是 | `recycle_bin.manage` | — |

### 重复任务

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/recurring` 🔄 | 重复规则列表 | 是 | `task.manage` | 过滤器：`project_public_id`、`is_active` |
| POST | `/api/v1/recurring` 🔄 | 创建规则 | 是 | `task.manage` | — |
| GET | `/api/v1/recurring/{public_id}` 🔄 | 规则详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/recurring/{public_id}` 🔄 | 更新规则 | 是 | `task.manage` | — |
| DELETE | `/api/v1/recurring/{public_id}` 🔄 | 删除规则 | 是 | `task.manage` | — |
| POST | `/api/v1/recurring/{public_id}/pause` 🔄 | 暂停规则 | 是 | `task.manage` | — |
| POST | `/api/v1/recurring/{public_id}/resume` 🔄 | 恢复规则 | 是 | `task.manage` | — |

### 评估集

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/estimate-sets` | 评估集列表 | 是 | `project.manage` | — |
| POST | `/api/v1/estimate-sets` | 创建评估集 | 是 | `project.manage` | — |
| GET | `/api/v1/estimate-sets/{public_id}` | 评估集详情 | 是 | `project.manage` | — |
| PATCH, PUT | `/api/v1/estimate-sets/{public_id}` | 更新评估集 | 是 | `project.manage` | — |
| POST | `/api/v1/estimate-sets/{public_id}/archive` | 归档评估集 | 是 | `project.manage` | — |
| DELETE | `/api/v1/estimate-sets/{public_id}` | 删除评估集 | 是 | `project.manage` | — |
| GET | `/api/v1/estimate-sets/{public_id}/options` | 集合选项 | 是 | `project.manage` | — |
| POST | `/api/v1/estimate-sets/{public_id}/options` | 创建选项 | 是 | `project.manage` | — |
| PATCH, PUT | `/api/v1/estimate-options/{public_id}` | 更新选项 | 是 | `project.manage` | — |
| POST | `/api/v1/estimate-options/{public_id}/archive` | 归档选项 | 是 | `project.manage` | — |
| DELETE | `/api/v1/estimate-options/{public_id}` | 删除选项 | 是 | `project.manage` | — |
| GET | `/api/v1/tasks/{public_id}/estimates` | 任务估算 | 是 | `task.manage` | — |
| POST | `/api/v1/tasks/{public_id}/estimates` | 分配估算 | 是 | `task.manage` | — |
| DELETE | `/api/v1/tasks/{public_id}/estimates/{set_public_id}` | 删除估算 | 是 | `task.manage` | — |
| GET | `/api/v1/projects/{public_id}/estimate-summary` | 项目估算摘要 | 是 | `project.manage` | — |
| GET | `/api/v1/cycles/{public_id}/estimate-summary` | 周期估算摘要 | 是 | `task.manage` | — |
| GET | `/api/v1/project-modules/{public_id}/estimate-summary` | 模块估算摘要 | 是 | `project.manage` | — |

### 便签

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/sticky-notes` | 便签列表 | 是 | `task.manage` | — |
| POST | `/api/v1/sticky-notes` | 创建便签 | 是 | `task.manage` | — |
| GET | `/api/v1/sticky-notes/{public_id}` | 便签详情 | 是 | `task.manage` | — |
| PATCH, PUT | `/api/v1/sticky-notes/{public_id}` | 更新便签 | 是 | `task.manage` | — |
| DELETE | `/api/v1/sticky-notes/{public_id}` | 删除便签 | 是 | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/archive` | 归档便签 | 是 | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/unarchive` | 取消归档便签 | 是 | `task.manage` | — |
| POST | `/api/v1/sticky-notes/reorder` | 重新排序便签 | 是 | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/convert-to-task` | 转换为任务 | 是 | `task.manage` | — |
| POST | `/api/v1/sticky-notes/{public_id}/convert-to-page` | 转换为知识页面 | 是 | `task.manage` | — |

### 知识库

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/overview` | 知识库概览 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/search` | 搜索知识 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/recent` | 最近页面 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/popular` | 热门页面 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/review-queue` | 审核队列 | 是 | `knowledge.publish` | — |
| GET | `/api/v1/knowledge/outdated` | 过时页面 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/favorites` | 收藏页面 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/suggest` | 建议 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/analytics` | 知识分析 | 是 | `knowledge.analytics_view` | — |
| GET | `/api/v1/knowledge/templates` | 页面模板 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/templates` | 创建模板 | 是 | `knowledge.template_manage` | — |
| GET | `/api/v1/knowledge/entities/{entity_type}/{entity_public_id}/pages` | 实体页面 | 是 | `knowledge.view` | — |

### 知识 — 空间

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/spaces` | 空间 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces-tree` | 空间树 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/spaces` | 创建空间 | 是 | `knowledge.manage` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}` | 空间详情 | 是 | `knowledge.view` | — |
| PATCH, PUT | `/api/v1/knowledge/spaces/{public_id}` | 更新空间 | 是 | `knowledge.manage` | — |
| DELETE | `/api/v1/knowledge/spaces/{public_id}` | 归档空间 | 是 | `knowledge.manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/archive` | 归档（备选） | 是 | `knowledge.manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/restore` | 恢复 | 是 | `knowledge.manage` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/tree` | 页面树 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/permissions` | 空间权限 | 是 | `knowledge.permission_manage` | — |
| POST | `/api/v1/knowledge/spaces/{public_id}/permissions` | 添加权限 | 是 | `knowledge.permission_manage` | — |
| DELETE | `/api/v1/knowledge/permissions/{permission_id}` | 删除权限 | 是 | `knowledge.permission_manage` | — |

### 知识 — 页面

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages` | 页面列表 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages` | 创建页面 | 是 | `knowledge.create` | — |
| GET | `/api/v1/knowledge/pages/{public_id}` | 页面详情 | 是 | `knowledge.view` | — |
| PATCH, PUT | `/api/v1/knowledge/pages/{public_id}` | 更新页面 | 是 | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}` | 删除页面 | 是 | `knowledge.delete` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/publish` | 发布页面 | 是 | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/archive` | 归档页面 | 是 | `knowledge.delete` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/restore` | 恢复页面 | 是 | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/move` | 移动页面 | 是 | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/copy` | 复制页面 | 是 | `knowledge.create` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/duplicate` | 复制页面 | 是 | `knowledge.create` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/request-review` | 请求审核 | 是 | `knowledge.edit` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/approve` | 批准审核 | 是 | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/review` | 批准（备选） | 是 | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/reject` | 拒绝审核 | 是 | `knowledge.publish` | — |

### 知识 — 页面草稿、链接、标签、文件

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/draft` | 草稿 | 是 | `knowledge.edit` | — |
| POST, PUT, PATCH | `/api/v1/knowledge/pages/{public_id}/draft` | 保存草稿 | 是 | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/draft` | 删除草稿 | 是 | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/links` | 页面链接 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/links` | 添加链接 | 是 | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/links/{link_public_id}` | 删除链接 | 是 | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/tags` | 页面标签 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/tags/{tag_public_id}` | 关联标签 | 是 | `knowledge.edit` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/tags/{tag_public_id}` | 取消关联标签 | 是 | `knowledge.edit` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/files` | 页面文件 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/files` | 上传文件 | 是 | `knowledge.edit` | `multipart/form-data` |
| DELETE | `/api/v1/knowledge/files/{file_public_id}` | 删除文件 | 是 | `knowledge.edit` | — |

### 知识 — 评论

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/comments` | 页面评论 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/comments` | 添加评论 | 是 | `knowledge.comment` | — |
| DELETE | `/api/v1/knowledge/comments/{comment_public_id}` | 删除评论 | 是 | `knowledge.comment` | — |
| POST | `/api/v1/knowledge/comments/{comment_public_id}/resolve` | 解决评论 | 是 | `knowledge.comment` | — |
| POST | `/api/v1/knowledge/comments/{comment_public_id}/reopen` | 重新打开评论 | 是 | `knowledge.comment` | — |

### 知识 — 收藏与订阅

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/knowledge/pages/{public_id}/favorite` | 添加收藏 | 是 | `knowledge.view` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/favorite` | 删除从收藏 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/subscribe` | 订阅页面 | 是 | `knowledge.view` | — |
| DELETE | `/api/v1/knowledge/pages/{public_id}/subscribe` | 取消订阅页面 | 是 | `knowledge.view` | — |

### 知识 — 导出与导入

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/export` | 导出所有页面 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/export` | 导出页面 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/spaces/{public_id}/export` | 导出空间 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/import` | 导入页面 | 是 | `knowledge.import` | — |

### 知识 — 页面版本与锁定

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/knowledge/pages/{public_id}/versions` | 页面版本 | 是 | `knowledge.view` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}` | 版本详情 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}/restore` | 恢复版本 | 是 | `knowledge.publish` | — |
| GET | `/api/v1/knowledge/pages/{public_id}/versions/{version_public_id}/diff` | 版本差异 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/lock` | 锁定页面 | 是 | `knowledge.publish` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/unlock` | 解锁页面 | 是 | `knowledge.publish` | — |

### 知识 — AI

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/knowledge/pages/{public_id}/ai/summary` | AI 页面摘要 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/explain` | AI 说明 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/similar` | 相似页面 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/checklist` | AI 清单 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/pages/{public_id}/ai/faq-from-comments` | 从评论生成 FAQ | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/ai/suggest-for-task/{task_public_id}` | 任务建议 | 是 | `knowledge.view` | — |
| POST | `/api/v1/knowledge/ai/admin/find-duplicates` | 搜索重复项 | 是 | `knowledge.admin` | — |
| GET | `/api/v1/knowledge/ai/admin/find-orphans` | 搜索孤立项 | 是 | `knowledge.admin` | — |
| POST | `/api/v1/knowledge/ai/admin/suggest-structure/{public_id}` | 结构建议 | 是 | `knowledge.admin` | — |

### 知识 — 管理

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/admin/knowledge/settings` | 知识设置 | 是 | `knowledge.admin` | — |
| PATCH, PUT | `/api/v1/admin/knowledge/settings` | 更新设置 | 是 | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/reindex` | 重建索引 | 是 | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/rebuild-permissions` | 重新计算权限 | 是 | `knowledge.admin` | — |
| POST | `/api/v1/admin/knowledge/cleanup-drafts` | 清理草稿 | 是 | `knowledge.admin` | — |

### 收件项

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/intake-items` | 收件项列表 | 是 | `intake.view` | — |
| POST | `/api/v1/intake-items` | 创建收件项 | 是 | `intake.create` | — |
| GET | `/api/v1/intake-items/{public_id}` | 收件项详情 | 是 | `intake.view` | — |
| PATCH, PUT | `/api/v1/intake-items/{public_id}` | 更新收件项 | 是 | `intake.manage` | — |
| DELETE | `/api/v1/intake-items/{public_id}` | 删除收件项 | 是 | `intake.delete` | — |
| POST | `/api/v1/intake-items/{public_id}/accept` | 接受收件项 | 是 | `intake.accept` | — |
| POST | `/api/v1/intake-items/{public_id}/reject` | 拒绝收件项 | 是 | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/snooze` | 推迟收件项 | 是 | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/duplicate` | 复制收件项 | 是 | `intake.manage` | — |
| POST | `/api/v1/intake-items/{public_id}/reopen` | 重新打开收件项 | 是 | `intake.manage` | — |
| GET | `/api/v1/intake-items/{public_id}/activities` | 收件活动 | 是 | `intake.view` | — |

### 运维 / 管理

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ops/system` 🔄 | 系统信息 | 是 | `logs.view` | — |
| GET | `/api/v1/ops/metrics` 🔄 | 指标 | 是 | `logs.view` | — |
| POST | `/api/v1/ops/jobs/run` 🔄 | 启动后台任务 | 是 | `logs.view` | — |
| GET | `/api/v1/admin/widgets/summary` 🔄 | 摘要小组件 | 是 | `logs.view` | — |
| GET | `/api/v1/admin/widgets/system` 🔄 | 系统小组件 | 是 | `logs.view` | — |
| GET | `/api/v1/admin/cache` | 缓存统计 | 是 | `settings.manage` | — |
| POST | `/api/v1/admin/cache/clear` | 清除缓存 | 是 | `settings.manage` | — |

### 文档与事件

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/docs/openapi` | OpenAPI 规范 | 是 | `logs.view` | — |
| GET | `/api/v1/docs/schema` | JSON Schema | 是 | `logs.view` | — |
| GET | `/api/v1/events/stream` | SSE 流 | 是 | — | 实时更新 |
| POST | `/api/v1/visual-editor/upload-image` | 上传图片 | 是 | — | `multipart/form-data`，用于可视化编辑器 |

### 安全（会话、邀请、2FA、模拟）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/security/sessions` 🔄 | 会话列表 | 是 | — | — |
| DELETE | `/api/v1/security/sessions/{public_id}` 🔄 | 撤销会话 | 是 | — | — |
| POST | `/api/v1/security/sessions/revoke-others` 🔄 | 撤销其他会话 | 是 | — | — |
| POST | `/api/v1/security/sessions/revoke-device` 🔄 | 撤销设备会话 | 是 | — | — |
| GET | `/api/v1/security/invitations` 🔄 | 邀请列表 | 是 | `user.manage` | — |
| POST | `/api/v1/security/invitations` 🔄 | 创建邀请 | 是 | `user.manage` | — |
| POST | `/api/v1/security/invitations/accept` | 接受邀请 | 否 | — | 公开 |
| GET | `/api/v1/security/invitations/{public_id}` 🔄 | 邀请详情 | 是 | `user.manage` | — |
| POST | `/api/v1/security/password-reset` 🔄 | 请求重置密码 | 否 | — | 公开 |
| POST | `/api/v1/security/password-reset/confirm` 🔄 | 确认重置 | 否 | — | 公开 |
| GET | `/api/v1/security/2fa/status` | 2FA 状态 | 是 | — | — |
| POST | `/api/v1/security/2fa/enable` | 启用 2FA | 是 | — | — |
| POST | `/api/v1/security/2fa/disable` | 禁用 2FA | 是 | — | — |
| POST | `/api/v1/security/2fa/verify` | 检查2FA 验证码 | 否 | — | 内部 challenge 端点（公开，无会话） |
| POST | `/api/v1/security/impersonation/start` | 开始模拟 | 是 | `user.manage` | — |
| GET | `/api/v1/security/impersonation/status` | 模拟状态 | 是 | — | — |
| POST | `/api/v1/security/impersonation/stop` | 停止模拟 | 是 | — | — |

### 个人资料

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/profile/me` 🔄 | 我的资料 | 是 | — | — |
| PATCH, PUT | `/api/v1/profile/me` 🔄 | 更新个人资料 | 是 | — | — |
| GET | `/api/v1/profile/preferences` 🔄 | 我的偏好 | 是 | — | — |
| PATCH, PUT | `/api/v1/profile/preferences` 🔄 | 更新偏好 | 是 | — | — |
| POST | `/api/v1/profile/change-password` 🔄 | 更改密码 | 是 | — | — |

### AI — 提供商与模型

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/providers` | AI 提供商列表 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/providers` | 创建提供商 | 是 | `ai.admin` | — |
| GET | `/api/v1/ai/providers/{public_id}` | 提供商详情 | 是 | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/providers/{public_id}` | 更新提供商 | 是 | `ai.admin` | — |
| DELETE | `/api/v1/ai/providers/{public_id}` | 删除提供商 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/providers/{public_id}/test` | 测试提供商 | 是 | `ai.admin` | — |
| PUT | `/api/v1/ai/providers/{public_id}/secret` | 设置密钥 | 是 | `ai.admin` | — |
| DELETE | `/api/v1/ai/providers/{public_id}/secret` | 删除密钥 | 是 | `ai.admin` | — |
| GET | `/api/v1/ai/models` | 模型列表 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/models/sync` | 同步模型 | 是 | `ai.admin` | — |
| GET | `/api/v1/ai/retention-policies` | AI 保留策略 | 是 | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/retention-policies/{policy_code}` | 更新策略 | 是 | `ai.admin` | — |

### AI — 设置与偏好

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/settings` | AI 设置 | 是 | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/settings` | 更新AI 设置 | 是 | `ai.admin` | — |
| GET | `/api/v1/ai/preferences` | AI 偏好 | 是 | `ai.use` | — |
| PATCH, PUT | `/api/v1/ai/preferences` | 更新偏好 | 是 | `ai.use` | — |
| GET | `/api/v1/ai/action-types` | AI 操作类型 | 是 | `ai.use` | — |
| GET | `/api/v1/ai/availability` | AI 可用性 | 是 | — | — |

### AI — 操作与建议

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/api/v1/ai/actions/{action_type}` | 执行 AI 操作 | 是 | `ai.use` | 动态类型 |
| POST | `/api/v1/ai/tasks/{task_public_id}/summary` | AI 任务摘要 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/decompose` | AI 任务拆解 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/checklist` | AI 任务清单 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/quality` | AI 质量评估 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/next-action` | AI 下一步行动 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/tasks/{task_public_id}/comment-draft` | AI 评论草稿 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/tasks/priority` | AI 优先级排序 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/summary` | AI 项目摘要 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/risks` | AI 项目风险 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/projects/{project_public_id}/client-report` | AI 客户报告 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/summary` | AI 客户摘要 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/meeting-prep` | AI 会议准备 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/data-quality` | AI 客户数据质量 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/clients/{client_public_id}/client-safe-report` | AI 安全报告 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/calendar/events/{event_public_id}/agenda` | AI 会议议程 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/dashboard/digest` | AI 仪表盘摘要 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/analytics/kpi-explanation` | AI KPI 说明 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/analytics/risks-explanation` | AI 风险说明 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/analytics/team-workload-summary` | AI 负载摘要 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/admin/log-review` | AI 日志审查 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/admin/webhook-health` | AI Webhook 健康 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/admin/workflow-audit` | AI 工作流审计 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/my-day/plan` | AI 每日计划 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/my-week/plan` | AI 每周计划 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/search/semantic` | 语义搜索 | 是 | `ai.use` | — |

### AI — 建议管理

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/suggestions` | 建议列表 | 是 | `ai.use` | — |
| GET | `/api/v1/ai/suggestions/{public_id}` | 建议详情 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/dismiss` | 拒绝建议 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/apply-preview` | 预览应用 | 是 | `ai.use` | — |
| POST | `/api/v1/ai/suggestions/{public_id}/confirm` | 应用建议 | 是 | `ai.use` | — |

### AI — 意图、提示词、架构、用量、任务

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ai/intent-settings` | 意图设置 | 是 | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/intent-settings/{intent_code}` | 更新意图 | 是 | `ai.admin` | — |
| GET | `/api/v1/ai/prompt-templates` | 提示词模板 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/prompt-templates` | 创建模板 | 是 | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/prompt-templates/{public_id}` | 更新模板 | 是 | `ai.admin` | — |
| GET | `/api/v1/ai/json-schemas` | JSON 架构 | 是 | `ai.admin` | — |
| POST | `/api/v1/ai/json-schemas` | 创建架构 | 是 | `ai.admin` | — |
| PATCH, PUT | `/api/v1/ai/json-schemas/{public_id}` | 更新架构 | 是 | `ai.admin` | — |
| GET | `/api/v1/ai/usage` | AI 使用 | 是 | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/audit` | AI 审计 | 是 | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/jobs` | AI 任务 | 是 | `ai.view_cron_results` | — |
| GET | `/api/v1/ai/jobs/{public_id}` | AI 任务详情 | 是 | `ai.view_cron_results` | — |
| POST | `/api/v1/ai/jobs/{public_id}/retry` | 重试AI 任务 | 是 | `ai.manage_cron_jobs` | — |
| POST | `/api/v1/ai/jobs/{job_code}/dry-run` | 试运行 AI 任务 | 是 | `ai.manage_cron_jobs` | — |
| POST | `/api/v1/ai/jobs/{job_code}/run-once` | 单次运行 | 是 | `ai.manage_cron_jobs` | — |

### 模块

`GET /api/v1/modules` 和 `GET /api/v1/modules/{name}` 返回的模块对象包含作者元数据字段 `author`（作者显示名）和 `author_url`（作者链接，如 GitHub 主页），以及 `name`、`version`、`vendor`、`title`、`description`、`category`、`is_active`、`status`、`installed_at`、`activated_at`。

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/modules` | 模块列表 | 是 | `settings.manage` | — |
| GET | `/api/v1/modules/{name}` | 模块信息 | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/install` | 设置模块 | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/activate` | 激活模块 | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/deactivate` | 停用模块 | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/uninstall` | 删除模块 | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/{name}/purge` | 彻底删除模块（含文件） | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/bulk` | 批量操作模块 | 是 | `settings.manage` | Body: `action` + `modules[]` |
| GET | `/api/v1/modules/{name}/config` | 模块配置 | 是 | `settings.manage` | — |
| PUT | `/api/v1/modules/{name}/config` | 更新配置 | 是 | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/health` | 模块健康检查 | 是 | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/migrations` | 模块迁移 | 是 | `settings.manage` | — |
| GET | `/api/v1/modules/{name}/errors` | 模块错误 | 是 | `settings.manage` | — |
| DELETE | `/api/v1/modules/{name}/errors` | 清除错误 | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/install-from-url` | 设置从 URL | 是 | `settings.manage` | — |
| POST | `/api/v1/modules/install-from-file` | 设置从文件 | 是 | `settings.manage` | `multipart/form-data` |

### 想法

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/ideas` | 想法列表 | 是 | `idea.view` | 过滤器：`status`、`q` |
| POST | `/api/v1/ideas` | 创建想法 | 是 | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}` | 想法详情 | 是 | `idea.view` | — |
| PATCH | `/api/v1/ideas/{public_id}` | 更新想法 | 是 | `idea.manage` | — |
| DELETE | `/api/v1/ideas/{public_id}` | 删除想法 | 是 | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/vote` | 投票 | 是 | `idea.manage` | — |
| PATCH | `/api/v1/ideas/{public_id}/status` | 更改状态 | 是 | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/reset-analysis` | 重置 AI 分析 | 是 | `idea.manage` | — |
| GET, DELETE | `/api/v1/ideas/{public_id}/debug-log` | 调试日志 | 是 | `ai.admin` | — |
| GET | `/api/v1/ideas/{public_id}/questions` | 访谈问题 | 是 | `idea.view` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/additional-questions` | 补充问题 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/gap-questions` | 差距问题 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/understanding-card` | 理解卡片 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/refined-card` | 精炼卡片 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/potential` | 潜力评估 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/risk-report` | 风险报告 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/pitfalls` | 风险点分析 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/implementation-plan` | 实施计划 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/final-recommendation` | 最终建议 | 是 | `idea.manage` | — |
| GET, POST, DELETE | `/api/v1/ideas/{public_id}/suggested-tasks` | 建议任务 | 是 | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/create-project-tasks` | 创建由想法生成任务 | 是 | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}/ai-iterations` | AI 迭代 | 是 | `idea.view` | — |
| POST, DELETE | `/api/v1/ideas/{public_id}/interview` | AI 访谈 | 是 | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/interview-answers` | 保存答案 | 是 | `idea.manage` | — |
| POST | `/api/v1/ideas/{public_id}/comments` | 添加评论 | 是 | `idea.manage` | — |
| GET | `/api/v1/ideas/{public_id}/comments` | 想法评论 | 是 | `idea.view` | — |

### 聊天

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/api/v1/chats` | 聊天列表 | 是 | `chat.use` | — |
| POST | `/api/v1/chats` | 创建聊天 | 是 | `chat.use` | — |
| GET | `/api/v1/chats/unread-count` | 未读消息 | 是 | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}` | 聊天详情 | 是 | `chat.use` | — |
| PATCH | `/api/v1/chats/{public_id}/settings` | 聊天设置 | 是 | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}/participants` | 聊天参与者 | 是 | `chat.use` | — |
| GET | `/api/v1/chats/{public_id}/messages` | 聊天消息 | 是 | `chat.use` | 基于游标 |
| POST | `/api/v1/chats/{public_id}/messages` | 发送消息 | 是 | `chat.use` | — |
| PATCH | `/api/v1/chats/{public_id}/messages/{message_public_id}` | 编辑消息 | 是 | `chat.use` | — |
| DELETE | `/api/v1/chats/{public_id}/messages/{message_public_id}` | 删除消息 | 是 | `chat.use` | 软删除 |
| POST | `/api/v1/chats/{public_id}/attachments` | 上传附件 | 是 | `chat.use` | `multipart/form-data` |
| GET | `/api/v1/chats/{public_id}/attachments/{file_public_id}/download` | 下载附件 | 是 | `chat.use` | 二进制 |
| POST | `/api/v1/chats/{public_id}/read` | 标记为已读 | 是 | `chat.use` | — |
| POST | `/api/v1/chats/{public_id}/archive` | 归档聊天 | 是 | `chat.use` | — |
| POST | `/api/v1/chats/{public_id}/restore` | 恢复聊天 | 是 | `chat.use` | — |

### 模块：ActiveCollab 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.activecollab-migration/connections` | 连接列表 | 是 | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/connections` | 创建连接 | 是 | `module.activecollab-migration.manage`, `module.activecollab-migration.secret_manage` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}` | 连接详情 | 是 | `module.activecollab-migration.view` | — |
| PATCH | `/_module/crm.activecollab-migration/connections/{public_id}` | 更新连接 | 是 | `module.activecollab-migration.manage`, `module.activecollab-migration.secret_manage` | — |
| DELETE | `/_module/crm.activecollab-migration/connections/{public_id}` | 删除连接 | 是 | `module.activecollab-migration.delete` | — |
| POST | `/_module/crm.activecollab-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.activecollab-migration.manage` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}/workspaces` | 工作区列表 | 是 | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.activecollab-migration.run` | — |
| GET | `/_module/crm.activecollab-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.activecollab-migration.view` | — |
| PATCH | `/_module/crm.activecollab-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.activecollab-migration.manage` | — |
| GET | `/_module/crm.activecollab-migration/jobs` | 迁移任务列表 | 是 | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/jobs` | 创建迁移任务 | 是 | `module.activecollab-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.activecollab-migration.view` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.activecollab-migration.run` | — |
| POST | `/_module/crm.activecollab-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.activecollab-migration.delete` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.activecollab-migration.view` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.activecollab-migration.view` | — |
| GET | `/_module/crm.activecollab-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.activecollab-migration.report_view` | — |

### 模块：Asana 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.asana-migration/connections` | 连接列表 | 是 | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/connections` | 创建连接 | 是 | `module.asana-migration.manage`, `module.asana-migration.secret_manage` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}` | 连接详情 | 是 | `module.asana-migration.view` | — |
| PATCH | `/_module/crm.asana-migration/connections/{public_id}` | 更新连接 | 是 | `module.asana-migration.manage`, `module.asana-migration.secret_manage` | — |
| DELETE | `/_module/crm.asana-migration/connections/{public_id}` | 删除连接 | 是 | `module.asana-migration.delete` | — |
| POST | `/_module/crm.asana-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.asana-migration.manage` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}/workspaces` | 工作区列表 | 是 | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.asana-migration.run` | — |
| GET | `/_module/crm.asana-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.asana-migration.view` | — |
| PATCH | `/_module/crm.asana-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.asana-migration.manage` | — |
| GET | `/_module/crm.asana-migration/jobs` | 迁移任务列表 | 是 | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/jobs` | 创建迁移任务 | 是 | `module.asana-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.asana-migration.view` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.asana-migration.run` | — |
| POST | `/_module/crm.asana-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.asana-migration.delete` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.asana-migration.view` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.asana-migration.view` | — |
| GET | `/_module/crm.asana-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.asana-migration.report_view` | — |

### 模块：Bitrix24 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.bitrix24-migration/connections` | 连接列表 | 是 | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/connections` | 创建连接 | 是 | `module.bitrix24-migration.manage`, `module.bitrix24-migration.secret_manage` | — |
| GET | `/_module/crm.bitrix24-migration/connections/{public_id}` | 连接详情 | 是 | `module.bitrix24-migration.view` | — |
| PATCH | `/_module/crm.bitrix24-migration/connections/{public_id}` | 更新连接 | 是 | `module.bitrix24-migration.manage`, `module.bitrix24-migration.secret_manage` | — |
| DELETE | `/_module/crm.bitrix24-migration/connections/{public_id}` | 删除连接 | 是 | `module.bitrix24-migration.delete` | — |
| POST | `/_module/crm.bitrix24-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.bitrix24-migration.manage` | — |
| POST | `/_module/crm.bitrix24-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.bitrix24-migration.run` | — |
| GET | `/_module/crm.bitrix24-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.bitrix24-migration.view` | — |
| PATCH | `/_module/crm.bitrix24-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.bitrix24-migration.manage` | — |
| GET | `/_module/crm.bitrix24-migration/jobs` | 迁移任务列表 | 是 | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/jobs` | 创建迁移任务 | 是 | `module.bitrix24-migration.run` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.bitrix24-migration.view` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.bitrix24-migration.run` | — |
| POST | `/_module/crm.bitrix24-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.bitrix24-migration.delete` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.bitrix24-migration.view` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.bitrix24-migration.view` | — |
| GET | `/_module/crm.bitrix24-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.bitrix24-migration.report_view` | — |

### 模块：ClickUp 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.clickup-migration/oauth/authorize-url` | OAuth 授权 URL | 是 | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| POST | `/_module/crm.clickup-migration/oauth/exchange` | 交换OAuth 代码 | 是 | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| GET | `/_module/crm.clickup-migration/connections` | 连接列表 | 是 | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/connections` | 创建连接 | 是 | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}` | 连接详情 | 是 | `module.clickup-migration.view` | — |
| PATCH | `/_module/crm.clickup-migration/connections/{public_id}` | 更新连接 | 是 | `module.clickup-migration.manage`, `module.clickup-migration.secret_manage` | — |
| DELETE | `/_module/crm.clickup-migration/connections/{public_id}` | 删除连接 | 是 | `module.clickup-migration.delete` | — |
| POST | `/_module/crm.clickup-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.clickup-migration.manage` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}/projects` | 发现数据 | 是 | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.clickup-migration.view` | — |
| PATCH | `/_module/crm.clickup-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.clickup-migration.manage` | — |
| GET | `/_module/crm.clickup-migration/jobs` | 迁移任务列表 | 是 | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/jobs` | 创建迁移任务 | 是 | `module.clickup-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.clickup-migration.view` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.clickup-migration.run` | — |
| POST | `/_module/crm.clickup-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.clickup-migration.delete` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.clickup-migration.view` | — |
| GET | `/_module/crm.clickup-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.clickup-migration.report_view` | — |

### 模块：Confluence 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.confluence-migration/connections` | 连接列表 | 是 | — | — |
| POST | `/_module/crm.confluence-migration/connections` | 创建连接 | 是 | `module.confluence-migration.manage`, `module.confluence-migration.secret_manage` | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}` | 连接详情 | 是 | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}` | 更新连接 | 是 | `module.confluence-migration.manage`, `module.confluence-migration.secret_manage` | — |
| DELETE | `/_module/crm.confluence-migration/connections/{public_id}` | 删除连接 | 是 | `module.confluence-migration.delete` | — |
| POST | `/_module/crm.confluence-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.confluence-migration.manage` | — |
| POST | `/_module/crm.confluence-migration/connections/{public_id}/discover` | 发现空间 | 是 | `module.confluence-migration.run` | — |
| GET | `/_module/crm.confluence-migration/jobs` | 迁移任务列表 | 是 | — | — |
| POST | `/_module/crm.confluence-migration/jobs` | 创建迁移任务 | 是 | `module.confluence-migration.run`, `knowledge.import`, `knowledge.create`, `knowledge.edit`, `knowledge.publish` | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}` | 迁移任务详情 | 是 | — | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/start` | 启动任务 | 是 | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.confluence-migration.run` | — |
| POST | `/_module/crm.confluence-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.confluence-migration.run` | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/items` | 任务条目 | 是 | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/logs` | 任务日志 | 是 | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/report` | 任务报告 | 是 | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/unresolved-links` | 未解析链接 | 是 | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/unsupported-macros` | 不支持的宏 | 是 | — | — |
| GET | `/_module/crm.confluence-migration/jobs/{public_id}/download-report` | 下载报告 | 是 | — | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.confluence-migration.manage` | — |
| GET | `/_module/crm.confluence-migration/connections/{public_id}/group-mappings` | 分组映射 | 是 | — | — |
| PATCH | `/_module/crm.confluence-migration/connections/{public_id}/group-mappings/{mapping_id}` | 更新分组映射 | 是 | `module.confluence-migration.manage` | — |
| GET | `/_module/crm.confluence-migration/settings` | 模块设置 | 是 | — | — |
| PATCH | `/_module/crm.confluence-migration/settings` | 更新设置 | 是 | `module.confluence-migration.manage` | — |

### 模块：draw.io 图表（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.drawio/diagrams` | 图表列表 | 是 | `module.drawio.view` | — |
| POST | `/_module/crm.drawio/diagrams` | 创建图表 | 是 | `module.drawio.manage` | — |
| GET | `/_module/crm.drawio/diagrams/{public_id}` | 图表详情 | 是 | `module.drawio.view` | — |
| PATCH | `/_module/crm.drawio/diagrams/{public_id}` | 更新图表 | 是 | `module.drawio.manage` | — |
| DELETE | `/_module/crm.drawio/diagrams/{public_id}` | 删除图表 | 是 | `module.drawio.manage` | — |

### 模块：GitHub（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.github-integration/connections` | 连接列表 | 是 | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/connections` | 创建连接 | 是 | `module.github-integration.manage`, `module.github-integration.secret_manage` | — |
| PATCH | `/_module/crm.github-integration/connections/{public_id}` | 更新连接 | 是 | `module.github-integration.manage` | — |
| DELETE | `/_module/crm.github-integration/connections/{public_id}` | 删除连接 | 是 | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/connections/{public_id}/test` | 测试连接 | 是 | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/connections/{public_id}/discover` | 发现仓库 | 是 | `module.github-integration.manage` | — |
| GET | `/_module/crm.github-integration/links` | 仓库关联列表 | 是 | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/links` | 创建仓库关联 | 是 | `module.github-integration.manage`, `project.manage`, `task.manage` | — |
| DELETE | `/_module/crm.github-integration/links/{public_id}` | 删除仓库关联 | 是 | `module.github-integration.manage` | — |
| POST | `/_module/crm.github-integration/links/{public_id}/sync` | 立即同步 | 是 | `module.github-integration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.github-integration/links/{public_id}/logs` | 关联日志 | 是 | `module.github-integration.view` | — |
| POST | `/_module/crm.github-integration/webhook/{public_id}` | 入站 Webhook | 否 | — | HMAC 验证 |

### 模块：GitLab（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.gitlab-integration/connections` | 连接列表 | 是 | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/connections` | 创建连接 | 是 | `module.gitlab-integration.manage`, `module.gitlab-integration.secret_manage` | — |
| PATCH | `/_module/crm.gitlab-integration/connections/{public_id}` | 更新连接 | 是 | `module.gitlab-integration.manage` | — |
| DELETE | `/_module/crm.gitlab-integration/connections/{public_id}` | 删除连接 | 是 | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/connections/{public_id}/test` | 测试连接 | 是 | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/connections/{public_id}/discover` | 发现项目 | 是 | `module.gitlab-integration.manage` | — |
| GET | `/_module/crm.gitlab-integration/links` | 项目关联列表 | 是 | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/links` | 创建项目关联 | 是 | `module.gitlab-integration.manage`, `project.manage`, `task.manage` | — |
| DELETE | `/_module/crm.gitlab-integration/links/{public_id}` | 删除项目关联 | 是 | `module.gitlab-integration.manage` | — |
| POST | `/_module/crm.gitlab-integration/links/{public_id}/sync` | 立即同步 | 是 | `module.gitlab-integration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.gitlab-integration/links/{public_id}/logs` | 关联日志 | 是 | `module.gitlab-integration.view` | — |
| POST | `/_module/crm.gitlab-integration/webhook/{public_id}` | 入站 Webhook | 否 | — | 令牌验证 |

### 模块：Google Calendar（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.google-calendar/oauth/start` | OAuth 启动 | 是 | `module.google-calendar.manage` | — |
| GET | `/_module/crm.google-calendar/oauth/callback` | OAuth 回调 | 是 | `module.google-calendar.manage` | — |
| GET | `/_module/crm.google-calendar/connections` | 连接列表 | 是 | `module.google-calendar.view` | — |
| PUT | `/_module/crm.google-calendar/credentials` | 保存凭据 | 是 | `module.google-calendar.manage` | — |
| DELETE | `/_module/crm.google-calendar/credentials` | 删除凭据 | 是 | `module.google-calendar.manage` | — |
| DELETE | `/_module/crm.google-calendar/connections/{public_id}` | 禁用 | 是 | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/connections/{public_id}/test` | 测试连接 | 是 | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/connections/{public_id}/sync` | 同步 | 是 | `module.google-calendar.sync` | — |
| PATCH | `/_module/crm.google-calendar/calendars/{public_id}` | 更新日历 | 是 | `module.google-calendar.manage` | — |
| POST | `/_module/crm.google-calendar/webhook` | 接收 Webhook | 否 | — | — |

### 模块：Jira 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.jira-migration/connections` | 连接列表 | 是 | — | — |
| POST | `/_module/crm.jira-migration/connections` | 创建连接 | 是 | `module.jira-migration.manage`, `module.jira-migration.secret_manage` | — |
| GET | `/_module/crm.jira-migration/connections/{public_id}` | 连接详情 | 是 | — | — |
| PATCH | `/_module/crm.jira-migration/connections/{public_id}` | 更新连接 | 是 | `module.jira-migration.manage`, `module.jira-migration.secret_manage` | — |
| DELETE | `/_module/crm.jira-migration/connections/{public_id}` | 删除连接 | 是 | `module.jira-migration.delete` | — |
| POST | `/_module/crm.jira-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.jira-migration.manage` | — |
| POST | `/_module/crm.jira-migration/discover` | 发现数据 | 是 | `module.jira-migration.view` | — |
| POST | `/_module/crm.jira-migration/dry-run` | 试运行迁移 | 是 | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs` | 迁移任务列表 | 是 | — | — |
| POST | `/_module/crm.jira-migration/jobs` | 创建迁移任务 | 是 | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}` | 迁移任务详情 | 是 | — | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.jira-migration.run` | — |
| POST | `/_module/crm.jira-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.jira-migration.run` | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/items` | 任务条目 | 是 | — | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/logs` | 任务日志 | 是 | — | — |
| GET | `/_module/crm.jira-migration/jobs/{public_id}/report` | 任务报告 | 是 | — | — |
| GET | `/_module/crm.jira-migration/mappings` | 映射 | 是 | — | — |
| POST | `/_module/crm.jira-migration/mappings/discover` | 发现映射 | 是 | `module.jira-migration.manage` | — |
| PATCH | `/_module/crm.jira-migration/mappings/{public_id}` | 更新映射 | 是 | `module.jira-migration.manage` | — |
| GET | `/_module/crm.jira-migration/unresolved` | 未解析项 | 是 | — | — |

### 模块：Kaiten 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.kaiten-migration/connections` | 连接列表 | 是 | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/connections` | 创建连接 | 是 | `module.kaiten-migration.manage`, `module.kaiten-migration.secret_manage` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}` | 连接详情 | 是 | `module.kaiten-migration.view` | — |
| PATCH | `/_module/crm.kaiten-migration/connections/{public_id}` | 更新连接 | 是 | `module.kaiten-migration.manage`, `module.kaiten-migration.secret_manage` | — |
| DELETE | `/_module/crm.kaiten-migration/connections/{public_id}` | 删除连接 | 是 | `module.kaiten-migration.delete` | — |
| POST | `/_module/crm.kaiten-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.kaiten-migration.manage` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/spaces` | 空间列表 | 是 | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/workspaces` | 工作区列表 | 是 | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.kaiten-migration.run` | — |
| GET | `/_module/crm.kaiten-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.kaiten-migration.view` | — |
| PATCH | `/_module/crm.kaiten-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.kaiten-migration.manage` | — |
| GET | `/_module/crm.kaiten-migration/jobs` | 迁移任务列表 | 是 | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/jobs` | 创建迁移任务 | 是 | `module.kaiten-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.kaiten-migration.view` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.kaiten-migration.run` | — |
| POST | `/_module/crm.kaiten-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.kaiten-migration.delete` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.kaiten-migration.view` | — |
| GET | `/_module/crm.kaiten-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.kaiten-migration.report_view` | — |

### 模块：Linear 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.linear-migration/connections` | 连接列表 | 是 | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/connections` | 创建连接 | 是 | `module.linear-migration.manage`, `module.linear-migration.secret_manage` | — |
| GET | `/_module/crm.linear-migration/connections/{public_id}` | 连接详情 | 是 | `module.linear-migration.view` | — |
| PATCH | `/_module/crm.linear-migration/connections/{public_id}` | 更新连接 | 是 | `module.linear-migration.manage` | — |
| DELETE | `/_module/crm.linear-migration/connections/{public_id}` | 删除连接 | 是 | `module.linear-migration.delete` | — |
| POST | `/_module/crm.linear-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.linear-migration.manage` | — |
| POST | `/_module/crm.linear-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.linear-migration.run` | — |
| GET | `/_module/crm.linear-migration/jobs` | 迁移任务列表 | 是 | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/jobs` | 创建迁移任务 | 是 | `module.linear-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}` | 任务详情 | 是 | `module.linear-migration.view` | — |
| POST | `/_module/crm.linear-migration/jobs/{public_id}/run` | 运行任务 | 是 | `module.linear-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.linear-migration.view` | — |
| GET | `/_module/crm.linear-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.linear-migration.view` | — |

### 模块：Notion 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.notion-migration/connections` | 连接列表 | 是 | — | — |
| POST | `/_module/crm.notion-migration/connections` | 创建连接 | 是 | `module.notion-migration.manage`, `module.notion-migration.secret_manage` | — |
| GET | `/_module/crm.notion-migration/connections/{public_id}` | 连接详情 | 是 | — | — |
| PATCH | `/_module/crm.notion-migration/connections/{public_id}` | 更新连接 | 是 | `module.notion-migration.manage`, `module.notion-migration.secret_manage` | — |
| DELETE | `/_module/crm.notion-migration/connections/{public_id}` | 删除连接 | 是 | `module.notion-migration.delete` | — |
| POST | `/_module/crm.notion-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.notion-migration.manage` | — |
| POST | `/_module/crm.notion-migration/connections/{public_id}/discover` | 发现对象 | 是 | `module.notion-migration.run` | — |
| GET | `/_module/crm.notion-migration/jobs` | 迁移任务列表 | 是 | — | — |
| POST | `/_module/crm.notion-migration/jobs` | 创建迁移任务 | 是 | `module.notion-migration.run`, `knowledge.import`, `knowledge.create`, `knowledge.edit`, `knowledge.publish` | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}` | 任务详情 | 是 | — | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/start` | 启动任务 | 是 | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.notion-migration.run` | — |
| POST | `/_module/crm.notion-migration/jobs/{public_id}/retry-failed` | 重试失败条目 | 是 | `module.notion-migration.run` | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/items` | 任务条目 | 是 | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/logs` | 任务日志 | 是 | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/report` | 任务报告 | 是 | — | — |
| GET | `/_module/crm.notion-migration/jobs/{public_id}/download-report` | 下载报告 | 是 | — | — |
| GET | `/_module/crm.notion-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | — | — |
| PATCH | `/_module/crm.notion-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.notion-migration.manage` | — |
| GET | `/_module/crm.notion-migration/settings` | 模块设置 | 是 | — | — |
| PATCH | `/_module/crm.notion-migration/settings` | 更新设置 | 是 | `module.notion-migration.manage` | — |

### 模块：Raycast（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.raycast/config` | MCP 连接配置 | 是 | `module.raycast.view` | — |

### 模块：Shtab.app 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.shtab-migration/connections` | 连接列表 | 是 | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/connections` | 创建连接 | 是 | `module.shtab-migration.manage` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}` | 连接详情 | 是 | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/connections/{public_id}/crm-users` | CRM 用户 | 是 | `module.shtab-migration.view` | — |
| PATCH | `/_module/crm.shtab-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.shtab-migration.manage` | — |
| DELETE | `/_module/crm.shtab-migration/connections/{public_id}` | 删除连接 | 是 | `module.shtab-migration.delete` | — |
| GET | `/_module/crm.shtab-migration/jobs` | 迁移任务列表 | 是 | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/jobs` | 创建迁移任务 | 是 | `module.shtab-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.shtab-migration.view` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.shtab-migration.run` | — |
| POST | `/_module/crm.shtab-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.shtab-migration.delete` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.shtab-migration.view` | — |
| GET | `/_module/crm.shtab-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.shtab-migration.report_view` | — |

### 模块：Slack 通知（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.slack-integration/connections` | 连接列表 | 是 | `module.slack-integration.view` | — |
| POST | `/_module/crm.slack-integration/connections` | 创建连接 | 是 | `module.slack-integration.manage`, `module.slack-integration.secret_manage` | — |
| GET | `/_module/crm.slack-integration/connections/{public_id}` | 连接详情 | 是 | `module.slack-integration.view` | — |
| PATCH | `/_module/crm.slack-integration/connections/{public_id}` | 更新连接 | 是 | `module.slack-integration.manage` | — |
| DELETE | `/_module/crm.slack-integration/connections/{public_id}` | 删除连接 | 是 | `module.slack-integration.manage` | — |
| POST | `/_module/crm.slack-integration/connections/{public_id}/test` | 测试连接 | 是 | `module.slack-integration.manage` | — |
| GET | `/_module/crm.slack-integration/rules` | 通知规则列表 | 是 | `module.slack-integration.view` | — |
| POST | `/_module/crm.slack-integration/rules` | 创建规则 | 是 | `module.slack-integration.manage` | — |
| DELETE | `/_module/crm.slack-integration/rules/{public_id}` | 删除规则 | 是 | `module.slack-integration.manage` | — |
| POST | `/_module/crm.slack-integration/notify` | 发送通知（工作流） | 否 | — | 服务端调用 |
| GET | `/_module/crm.slack-integration/deliveries` | 投递列表 | 是 | `module.slack-integration.view` | — |

### 模块：Todoist 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.todoist-migration/oauth/authorize-url` | OAuth 授权 URL | 是 | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| POST | `/_module/crm.todoist-migration/oauth/exchange` | 交换OAuth 代码 | 是 | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| GET | `/_module/crm.todoist-migration/connections` | 连接列表 | 是 | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/connections` | 创建连接 | 是 | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}` | 连接详情 | 是 | `module.todoist-migration.view` | — |
| PATCH | `/_module/crm.todoist-migration/connections/{public_id}` | 更新连接 | 是 | `module.todoist-migration.manage`, `module.todoist-migration.secret_manage` | — |
| DELETE | `/_module/crm.todoist-migration/connections/{public_id}` | 删除连接 | 是 | `module.todoist-migration.delete` | — |
| POST | `/_module/crm.todoist-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.todoist-migration.manage` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}/projects` | 发现数据 | 是 | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.todoist-migration.view` | — |
| PATCH | `/_module/crm.todoist-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.todoist-migration.manage` | — |
| GET | `/_module/crm.todoist-migration/jobs` | 迁移任务列表 | 是 | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/jobs` | 创建迁移任务 | 是 | `module.todoist-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.todoist-migration.view` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.todoist-migration.run` | — |
| POST | `/_module/crm.todoist-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.todoist-migration.delete` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.todoist-migration.view` | — |
| GET | `/_module/crm.todoist-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.todoist-migration.report_view` | — |

### 模块：Toggl 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.toggl-migration/connections` | 连接列表 | 是 | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/connections` | 创建连接 | 是 | `module.toggl-migration.manage`, `module.toggl-migration.secret_manage` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}` | 连接详情 | 是 | `module.toggl-migration.view` | — |
| PATCH | `/_module/crm.toggl-migration/connections/{public_id}` | 更新连接 | 是 | `module.toggl-migration.manage`, `module.toggl-migration.secret_manage` | — |
| DELETE | `/_module/crm.toggl-migration/connections/{public_id}` | 删除连接 | 是 | `module.toggl-migration.delete` | — |
| POST | `/_module/crm.toggl-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.toggl-migration.manage` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}/workspaces` | 工作区列表 | 是 | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.toggl-migration.run` | — |
| GET | `/_module/crm.toggl-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.toggl-migration.view` | — |
| PATCH | `/_module/crm.toggl-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.toggl-migration.manage` | — |
| GET | `/_module/crm.toggl-migration/jobs` | 迁移任务列表 | 是 | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/jobs` | 创建迁移任务 | 是 | `module.toggl-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.toggl-migration.view` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.toggl-migration.run` | — |
| POST | `/_module/crm.toggl-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.toggl-migration.delete` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.toggl-migration.view` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.toggl-migration.view` | — |
| GET | `/_module/crm.toggl-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.toggl-migration.report_view` | — |

### 模块：Trello 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.trello-migration/connections` | 连接列表 | 是 | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/connections` | 创建连接 | 是 | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}` | 连接详情 | 是 | `module.trello-migration.view` | — |
| PATCH | `/_module/crm.trello-migration/connections/{public_id}` | 更新连接 | 是 | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| DELETE | `/_module/crm.trello-migration/connections/{public_id}` | 删除连接 | 是 | `module.trello-migration.delete` | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.trello-migration.manage` | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.trello-migration.run` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.trello-migration.view` | — |
| PATCH | `/_module/crm.trello-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.trello-migration.manage` | — |
| GET | `/_module/crm.trello-migration/connections/{public_id}/board-configs` | 看板配置 | 是 | `module.trello-migration.view` | — |
| PUT | `/_module/crm.trello-migration/connections/{public_id}/board-configs/{board_id}` | 保存看板配置 | 是 | `module.trello-migration.manage` | — |
| GET | `/_module/crm.trello-migration/jobs` | 迁移任务列表 | 是 | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/jobs` | 创建迁移任务 | 是 | `module.trello-migration.run`, `project.manage`, `task.manage` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.trello-migration.view` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.trello-migration.run` | — |
| POST | `/_module/crm.trello-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.trello-migration.delete` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.trello-migration.view` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.trello-migration.view` | — |
| GET | `/_module/crm.trello-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.trello-migration.report_view` | — |
| POST | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | 接收 Webhook | 否 | — | — |
| POST | `/_module/crm.trello-migration/connections/{public_id}/webhooks` | 创建Webhook | 是 | `module.trello-migration.manage`, `module.trello-migration.secret_manage` | — |
| DELETE | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | 删除Webhook | 是 | `module.trello-migration.manage` | — |
| HEAD | `/_module/crm.trello-migration/webhooks/{webhook_public_id}` | 检查Webhook | 否 | — | — |

### 模块：WIP Limit（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.wip-limit/limits` | 限额列表 | 是 | — | — |
| GET | `/_module/crm.wip-limit/limits/{user_id}` | 用户限额 | 是 | — | — |
| POST | `/_module/crm.wip-limit/limits` | 设置限额 | 是 | — | — |
| DELETE | `/_module/crm.wip-limit/limits/{user_id}` | 删除限额 | 是 | — | — |
| GET | `/_module/crm.wip-limit/summary` | 限额摘要 | 是 | — | — |

### 模块：Worksection 迁移（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| GET | `/_module/crm.worksection-migration/connections` | 连接列表 | 是 | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/connections` | 创建连接 | 是 | `module.worksection-migration.manage`, `module.worksection-migration.secret_manage` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}` | 连接详情 | 是 | `module.worksection-migration.view` | — |
| PATCH | `/_module/crm.worksection-migration/connections/{public_id}` | 更新连接 | 是 | `module.worksection-migration.manage`, `module.worksection-migration.secret_manage` | — |
| DELETE | `/_module/crm.worksection-migration/connections/{public_id}` | 删除连接 | 是 | `module.worksection-migration.delete` | — |
| POST | `/_module/crm.worksection-migration/connections/{public_id}/test` | 测试连接 | 是 | `module.worksection-migration.manage` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}/workspaces` | 工作区列表 | 是 | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/connections/{public_id}/discover` | 发现数据 | 是 | `module.worksection-migration.run` | — |
| GET | `/_module/crm.worksection-migration/connections/{public_id}/user-mappings` | 用户映射 | 是 | `module.worksection-migration.view` | — |
| PATCH | `/_module/crm.worksection-migration/connections/{public_id}/user-mappings/{mapping_id}` | 更新用户映射 | 是 | `module.worksection-migration.manage` | — |
| GET | `/_module/crm.worksection-migration/jobs` | 迁移任务列表 | 是 | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/jobs` | 创建迁移任务 | 是 | `module.worksection-migration.run`, `project.manage`, `task.manage`, `import.manage` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}` | 迁移任务详情 | 是 | `module.worksection-migration.view` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/run` | 启动任务 | 是 | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/pause` | 暂停任务 | 是 | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/resume` | 恢复任务 | 是 | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/cancel` | 取消任务 | 是 | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/retry-failed` | 重试失败项 | 是 | `module.worksection-migration.run` | — |
| POST | `/_module/crm.worksection-migration/jobs/{public_id}/rollback` | 回滚任务 | 是 | `module.worksection-migration.delete` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/items` | 任务条目 | 是 | `module.worksection-migration.view` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/logs` | 任务日志 | 是 | `module.worksection-migration.view` | — |
| GET | `/_module/crm.worksection-migration/jobs/{public_id}/report` | 任务报告 | 是 | `module.worksection-migration.report_view` | — |

### 模块：Yandex.Calendar（如已安装）

| 方法 | 端点 | 说明 | 认证 | 权限 | 备注 |
|-------|----------|------------|:---:|-------------|----------|
| POST | `/_module/crm.yandex-calendar/connections` | 连接 | 是 | `module.yandex-calendar.manage` | — |
| GET | `/_module/crm.yandex-calendar/connections` | 连接列表 | 是 | `module.yandex-calendar.view` | — |
| DELETE | `/_module/crm.yandex-calendar/connections/{public_id}` | 禁用 | 是 | `module.yandex-calendar.manage` | — |
| POST | `/_module/crm.yandex-calendar/connections/{public_id}/test` | 测试连接 | 是 | `module.yandex-calendar.manage` | — |
| POST | `/_module/crm.yandex-calendar/connections/{public_id}/sync` | 同步 | 是 | `module.yandex-calendar.sync` | — |
| PATCH | `/_module/crm.yandex-calendar/calendars/{public_id}` | 更新日历 | 是 | `module.yandex-calendar.manage` | — |

