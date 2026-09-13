# TropaTT CRM 模块开发者指南

> TropaTT CRM 扩展模块设计、开发、集成、打包与分发的全栈权威技术指南。本文档与 CRM 核心代码库（2026 核心发布系列）严格同步。

---

## 目录

1. [模块子系统架构](#1-模块子系统架构)
2. [模块剖析、目录结构与自动加载 (PSR-4 / ModuleAutoloader)](#2-模块剖析目录结构与自动加载-psr-4--moduleautoloader)
3. [清单规范 (manifest.json)](#3-清单规范-manifestjson)
4. [服务提供者 (AbstractModuleServiceProvider)、DI 容器与 RBAC](#4-服务提供者-abstractmoduleserviceproviderdi-容器与-rbac)
5. [事件总线：ModuleEvents 完整目录与 HookManager](#5-事件总线moduleevents-完整目录与-hookmanager)
6. [Web UI 集成：位置插槽 (PositionRegistry)、渲染钩子与国际化 (i18n)](#6-web-ui-集成位置插槽-positionregistry渲染钩子与国际化-i18n)
7. [Web 与 REST API 路由系统](#7-web-与-rest-api-路由系统)
8. [静态资源管理 (Assets: CSS, JS)](#8-静态资源管理-assets-css-js)
9. [数据库迁移与模式生命周期](#9-数据库迁移与模式生命周期)
10. [后台任务：Cron 调度器与事务任务队列](#10-后台任务cron-调度器与事务任务队列)
11. [安全性、静态代码审查与故障隔离 (熔断器 Circuit Breaker)](#11-安全性静态代码审查与故障隔离-熔断器-circuit-breaker)
12. [打包、版本控制与远程安全安装](#12-打包版本控制与远程安全安装)
13. [完整参考模块：Acme Telegram Notifier](#13-完整参考模块acme-telegram-notifier)

---

## 1. 模块子系统架构

TropaTT CRM 模块子系统遵循 **Zero-Daemon** 原则构建，专门针对共享主机环境（< 32 MB 内存，25 秒超时限制）进行极致优化，并实现扩展模块与核心代码之间的严格解耦。

### 核心架构原则

- **零核心修改 (Zero-Core-Edits):** 模块仅通过声明式清单 (`manifest.json`)、UI 插槽 (`PositionRegistry`)、事件钩子 (`HookManager`) 以及专用路由定义 (`api_routes`, `web_routes`) 扩展功能。严禁修改核心代码。
- **故障容错与熔断隔离:** 模块内的任何异常绝不能导致核心业务崩溃。核心层对所有 Hook 执行采用 `try-catch` 隔离，并由 `ModuleCircuitBreaker` 提供连续 5 次错误后的自动熔断保护。
- **静态安全代码审计:** 在安装和激活前，模块内的所有 PHP 文件都会经由 `ModuleCodeValidator` 进行词法标记分析 (`token_get_all`)。任何危险系统调用 (`eval`, `exec`, `shell_exec`, `passthru`, `file_put_contents`, `unlink`, `dl`, `ffi` 等) 均会导致模块被立即拒绝。
- **事务性模式迁移:** 模块可维护独立的数据库表和索引。所有架构变更均受控记录在系统表 `module_migrations` 中，卸载时支持逆向自动回滚 (`_rollback.sql` 或 `.down.sql`)。
- **统一命名空间规则:** 模块类均由核心加载器 `ModuleAutoloader` 自动注册在命名空间 `Module\<VendorName>\<ModuleName>\...` 下。

---

## 2. 模块剖析、目录结构与自动加载 (PSR-4 / ModuleAutoloader)

模块放置在 CRM 模块根目录下的专用子文件夹中：`modules/{vendor}.{name}/`。

### 模块命名规范
- 格式正则：`^[a-z0-9]+\.[a-z0-9\-]+$`（仅允许小写字母、数字及中划线，最多 64 字符）。
- 示例：`crm.wip-limit`, `crm.slack-integration`, `acme.telegram-notifier`。

### 类自动加载规则 (`ModuleAutoloader`)
1. 连字符自动转为 **CamelCase**：
   - 目录：`modules/acme.telegram-notifier/`
   - 基础命名空间：`Module\Acme\TelegramNotifier\`
2. 跨目录解析（依次在 `api/` 和 `web/` 下检索）：
   - `Module\Acme\TelegramNotifier\Service\Notifier` 将自动在 `api/Service/` 或 `web/Service/` 找到对应文件。

### 标准模块目录结构

```
modules/acme.telegram-notifier/
├── manifest.json                      # 模块声明清单（必填）
├── README.md                          # 模块使用说明
├── api/                               # 后端与 REST API 业务逻辑
│   ├── TelegramNotifierServiceProvider.php  # 服务提供者
│   ├── config/
│   │   └── routes.php                 # REST API 路由配置
│   ├── controller/
│   │   └── ApiTelegramController.php  # API 控制器
│   ├── service/
│   │   ├── TelegramService.php        # 核心服务
│   │   └── TelegramQueue.php          # 队列模型
│   ├── hook/
│   │   └── StatusHook.php             # 事件钩子监听器
│   ├── cron/
│   │   └── QueueWorkerHandler.php     # 定时任务处理器
│   └── migrations/                    # SQL 数据库迁移脚本
│       ├── 001_create_tables.sql
│       └── 001_create_tables_rollback.sql
└── web/                               # 前端与界面插槽
    ├── config/
    │   └── routes.php                 # Web 界面路由
    ├── controller/
    │   └── WebSettingsController.php  # Web 页面控制器
    ├── position/
    │   └── TaskSidebarPanel.php       # 任务侧边栏 UI 插槽渲染器
    ├── template/
    │   └── page/
    │       └── settings.php           # 页面视图模板
    ├── language/                      # 多语言包
    │   ├── ru-ru.php
    │   └── en-us.php
    └── assets/                        # 静态资源
        ├── css/
        │   └── widget.css
        └── js/
            └── widget.js
```

---

## 3. 清单规范 (manifest.json)

`manifest.json` 由 `Api\System\Library\Module\PluginManager` 在启动阶段解析校验：

| 字段 | 类型 | 必填 | 说明 | 示例 |
|---|---|:---:|---|---|
| `name` | `string` | **是** | 唯一模块标识符 (`^[a-z0-9]+\.[a-z0-9\-]+$`) | `"acme.telegram-notifier"` |
| `version` | `string` | **是** | 语义化版本号 (`SemVer`: `X.Y.Z`) | `"1.2.0"` |
| `vendor` | `string` | **是** | 开发者或组织标识 | `"acme"` |
| `author` | `string` | **是** | 作者姓名或标识 | `"Anton Barinov"` |
| `title` | `string` | **是** | CRM 界面显示的模块友好名称 | `"Telegram 通知中心"` |
| `description` | `string` | **是** | 模块功能描述 | `"在任务状态变更时发送实时 Telegram 提醒。"` |
| `license` | `string` | 否 | 开源或商业许可证 | `"MIT"` |
| `category` | `string` | 否 | 模块分类 (`integration`, `productivity`, `crm`, `finance`, `migration`) | `"integration"` |
| `core_version` | `string` | 否 | 核心版本依赖限制 (默认 `>=1.0.0`) | `">=1.0.0"` |
| `dependencies` | `array` | 否 | 依赖模块列表 | `[]` |
| `require_permissions` | `array` | 否 | 模块所需的系统 RBAC 权限代码 | `["tasks.read"]` |
| `service_provider` | `string` | 否 | 服务提供者类完整命名空间 (FQCN) | `"Module\\Acme\\TelegramNotifier\\TelegramNotifierServiceProvider"` |
| `api_routes` | `string` | 否 | API 路由配置文件路径 | `"api/config/routes.php"` |
| `web_routes` | `string` | 否 | Web 路由配置文件路径 | `"web/config/routes.php"` |
| `migrations` | `string` | 否 | 数据库迁移目录路径 | `"api/migrations/"` |
| `positions` | `object` | 否 | UI 插槽渲染器映射表 | 见第 6 节 |
| `assets` | `object` | 否 | 静态 CSS/JS 资源映射配置 | 见第 8 节 |

---

## 4. 服务提供者 (AbstractModuleServiceProvider)、DI 容器与 RBAC

模块继承 `Api\System\Library\Module\AbstractModuleServiceProvider`：
- **`register(Container $container): void`**: 注册服务与单例。禁止在此阶段调用其他外部服务。
- **`boot(Container $container): void`**: 运行期初始化。注册 HookManager 监听器。
- **`getPermissions(): array`**: 声明的权限会自动同步到系统角色权限列表 (`PermissionRepository::ensureRegistry()`)。

---

## 5. 事件总线：ModuleEvents 完整目录与 HookManager

Hook 处理器的上下文通过引用传递：`function (array &$context): void`。
核心内置 35+ 个标准事件常量 (`Api\System\Library\Module\ModuleEvents`)：
- **任务:** `TASK_CREATED`, `TASK_UPDATED`, `TASK_STATUS_CHANGED`, `TASK_ASSIGNEE_CHANGED`, `TASK_DELETED`
- **项目:** `PROJECT_CREATED`, `PROJECT_UPDATED`, `PROJECT_DELETED`
- **用户:** `USER_CREATED`, `USER_UPDATED`, `USER_DELETED`
- **工作周期:** `CYCLE_CREATED`, `CYCLE_STARTED`, `CYCLE_COMPLETED`, `CYCLE_REOPENED`, `CYCLE_ARCHIVED`, `CYCLE_DELETED`
- **CRM 记录:** `CLIENT_*`, `COUNTERPARTY_*`, `CONTACT_*`, `COMPANY_*`, `ORGANIZATION_*`
- **协同交流:** `COMMENT_ADDED`, `FILE_UPLOADED`, `CHAT_MESSAGE_CREATED`, `CHAT_MESSAGE_UPDATED`, `CHAT_MESSAGE_DELETED`
- **视图渲染:** `RENDER_BEFORE`, `RENDER_AFTER`

---

## 6. Web UI 集成：位置插槽 (PositionRegistry)、渲染钩子与国际化 (i18n)

### 核心支持的位置插槽
- `task.detail.sidebar`: 任务详情侧边栏。
- `project.detail.sidebar`: 项目详情侧边栏。
- `gantt.content.after`: 甘特图下方扩展区。
- `kanban.board.after`: 看板视图下方扩展区。
- `tasks.list.after`: 任务列表视图下方扩展区。
- `calendar.content.after`: 日历视图下方扩展区。
- `counterparties.content.after`: 交易对手列表下方扩展区。
- `profile.content.after`: 用户个人资料下方扩展区。
- `dashboard.content.after`: 控制面板小组件下方扩展区。

### 插槽渲染器示例
```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Position;

final class TaskSidebarPanel
{
    public static function render(array $context): string
    {
        $taskPublicId = htmlspecialchars((string)($context['task_public_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        return '<div class="crm-card mb-3" data-task-public-id="' . $taskPublicId . '">'
            . '<div class="crm-side-card-head"><h2 class="h6 mb-0">Telegram 通知</h2></div>'
            . '<div class="p-3 small text-muted">当前任务已关联 Telegram 提醒。</div>'
            . '</div>';
    }
}
```

---

## 7. Web 与 REST API 路由系统

### REST API 路由 (`api/config/routes.php`)
**核心前缀机制：** 核心路由模块会自动为所有 API 路由附加统一前缀：
`/_module/{vendor}.{name}`

```php
<?php
declare(strict_types=1);

use Module\Acme\TelegramNotifier\Controller\ApiTelegramController;

return [
    [
        'methods'    => ['GET'],
        'route'      => '/settings', // 最终路径：/_module/acme.telegram-notifier/settings
        'controller' => ApiTelegramController::class,
        'action'     => 'getSettings',
        'auth'       => true,
    ],
];
```

控制器返回标准的 `Api\System\Library\Http\JsonResponse`。

---

## 8. 静态资源与安全规范

- **静态资源:** 支持全局加载 (`assets.css`, `assets.js`) 以及按路由按需加载 (`assets.css_routes`, `assets.js_routes`)。
- **静态代码审查 (`ModuleCodeValidator`):** 严禁执行系统命令 (`exec`, `passthru`, `system`) 及危险文件写入函数 (`file_put_contents`, `unlink`)。
- **熔断保护 (`ModuleCircuitBreaker`):** 连续 5 次故障自动开启熔断 (`OPEN`)，60 秒后自动半开 (`HALF_OPEN`) 试探恢复。

---

*文档版本适用于 TropaTT CRM 2026 核心发布系列。*
