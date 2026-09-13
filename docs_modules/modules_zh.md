# TropaTT CRM 模块开发者指南

> TropaTT CRM 扩展模块设计、开发、集成、打包与分发的全栈权威技术指南。

---

## 目录

1. [模块子系统架构](#1-模块子系统架构)
2. [模块剖析与目录结构](#2-模块剖析与目录结构)
3. [清单规范 (manifest.json)](#3-清单规范-manifestjson)
4. [服务提供者 (AbstractModuleServiceProvider) 与依赖注入](#4-服务提供者-abstractmoduleserviceprovider-与依赖注入)
5. [事件总线与 ModuleEvents 完整目录](#5-事件总线与-moduleevents-完整目录)
6. [Web UI 集成：位置插槽 (PositionRegistry) 与模板](#6-web-ui-集成位置插槽-positionregistry-与模板)
7. [Web 与 REST API 路由](#7-web-与-rest-api-路由)
8. [静态资源管理 (Assets: CSS, JS)](#8-静态资源管理-assets-css-js)
9. [数据库迁移与模式生命周期](#9-数据库迁移与模式生命周期)
10. [后台任务：Cron 调度器与事务任务队列](#10-后台任务cron-调度器与事务任务队列)
11. [安全性、静态代码审查与故障隔离 (熔断器 Circuit Breaker)](#11-安全性静态代码审查与故障隔离-熔断器-circuit-breaker)
12. [打包、版本控制与远程安装](#12-打包版本控制与远程安装)
13. [完整参考模块：Acme Telegram Notifier](#13-完整参考模块acme-telegram-notifier)

---

## 1. 模块子系统架构

TropaTT CRM 模块子系统遵循 **Zero-Daemon** 原则构建，专门针对共享主机环境（< 32 MB 内存，25 秒超时限制）进行高度优化，并实现扩展模块与核心代码之间的严格隔离。

### 核心架构原则

- **零核心修改 (Zero-Core-Edits):** 模块仅通过声明式契约 (`manifest.json`)、UI 插槽 (`PositionRegistry`)、事件钩子 (`HookManager`) 以及专用路由定义 (`api_routes`, `web_routes`) 扩展系统功能。严禁修改 CRM 核心源码。
- **双层故障容错 (Fault Tolerance):** 任何模块内部的未捕获异常绝不能导致核心业务崩溃。核心层对所有 Hook 执行采用 `try-catch` 隔离，并内置全自动**熔断器 (Circuit Breaker)**。
- **静态安全代码审计:** 在安装和激活前，模块内的所有 PHP 文件都会经由 `ModuleCodeValidator` 进行词法记号分析 (`token_get_all`)。任何危险函数调用 (`eval`, `exec`, `shell_exec`, `passthru`, `file_put_contents` 等) 均会导致模块被立即拒绝。
- **事务性模式迁移:** 模块可维护独立的数据库表和索引。所有架构变更均受控记录在系统表 `module_migrations` 中，卸载时支持逆向自动回滚。
- **PSR-4 标准自动加载:** 核心加载器会自动为模块映射统一命名空间 `Module\<VendorName>\<ModuleName>\`。

---

## 2. 模块剖析与目录结构

模块放置在 CRM 模块根目录下的专用子文件夹中：`modules/{vendor}.{name}/`。

### 标准目录结构

```
modules/acme.telegram_notifier/
├── manifest.json                  # 模块声明清单（必填）
├── ServiceProvider.php            # 服务提供者、生命周期钩子与注册
├── api_routes.php                 # REST API 路由定义
├── web_routes.php                 # Web 界面路由定义
├── controllers/                   # 控制器
│   ├── ApiSettingsController.php
│   └── WebSettingsController.php
├── models/                        # 领域模型与数据层
│   └── TelegramLogModel.php
├── templates/                     # 视图模板 (HTML/Twig/PHP)
│   ├── settings.php
│   └── sidebar_widget.php
├── assets/                        # 公共静态资源
│   ├── css/
│   │   └── widget.css
│   └── js/
│       └── notifier.js
├── migrations/                    # 数据库迁移脚本
│   ├── 001_create_telegram_logs.up.sql
│   └── 001_create_telegram_logs.down.sql
└── cron/                          # 定时任务处理器
    └── NotificationQueueHandler.php
```

---

## 3. 清单规范 (manifest.json)

`manifest.json` 是模块的核心元数据入口，由 `Api\System\Library\Module\Manifest` 在系统启动时解析。

### 字段参考表

| 字段 | 类型 | 必填 | 说明 | 示例 |
|---|---|:---:|---|---|
| `name` | `string` | **是** | 唯一模块标识符，采用 `{vendor}.{name}` 格式 | `"acme.telegram_notifier"` |
| `version` | `string` | **是** | 语义化版本号 (`SemVer`) | `"1.2.0"` |
| `vendor` | `string` | **是** | 开发者或组织机构名称 | `"Acme Software Ltd"` |
| `author` | `string` | **是** | 作者姓名或标识 | `"Anton Barinov"` |
| `title` | `string` | **是** | CRM 界面展示的友好名称 | `"Telegram 通知中心"` |
| `description` | `string` | **是** | 模块功能描述 | `"在任务状态变更或新增评论时发送实时 Telegram 提醒。"` |
| `category` | `string` | 否 | 模块分类 (`integrations`, `crm`, `finance`, `tools`, `reports`) | `"integrations"` |
| `core_version` | `string` | 否 | CRM 核心版本依赖限制 (默认 `>=1.0.0`) | `">=1.0.0"` |
| `dependencies` | `array` | 否 | 依赖的前置模块标识列表 | `["acme.core_tools"]` |
| `require_permissions` | `array` | 否 | 模块所需的系统 RBAC 权限列表 | `["tasks.read", "settings.manage"]` |
| `service_provider` | `string` | 否 | 服务提供者类名 (FQCN) | `"Module\\Acme\\TelegramNotifier\\ServiceProvider"` |
| `api_routes` | `string` | 否 | REST API 路由配置文件相对路径 | `"api_routes.php"` |
| `web_routes` | `string` | 否 | Web 路由配置文件相对路径 | `"web_routes.php"` |
| `migrations` | `string` | 否 | 数据库迁移目录相对路径 | `"migrations"` |
| `hooks` | `object` | 否 | 核心事件监听器声明 | `{"task.created": [{"handler": "...", "priority": 10}]}` |
| `positions` | `object` | 否 | UI 注入插槽渲染器声明 | `{"task.detail.sidebar": [{"renderer": "...", "priority": 10}]}` |
| `menu_items` | `array` | 否 | 注入左侧导航栏的菜单项 | 见下方菜单配置 |
| `config_defaults` | `object` | 否 | 模块默认键值对配置 | `{"bot_token": "", "chat_id": ""}` |
| `assets` | `object` | 否 | 静态 CSS/JS 资源映射配置 | 见静态资源管理部分 |

---

## 4. 服务提供者 (AbstractModuleServiceProvider) 与依赖注入

模块通过继承 `Api\System\Library\Module\AbstractModuleServiceProvider` 接入核心运行时。

### 核心生命周期方法

- **`register(Container $container): void`**: 在容器构建阶段执行。用于将模块的模型、服务类及 API 客户端绑定至核心依赖注入容器。禁止在此阶段调用其他未初始化的模块。
- **`boot(Container $container): void`**: 在所有模块注册完毕后执行。用于动态事件绑定及运行时环境检测。

---

## 5. 事件总线与 ModuleEvents 完整目录

核心在关键业务生命周期分发事件，所有标准事件常量均在 `Api\System\Library\Module\ModuleEvents` 中维护：

- **任务相关:** `task.created`, `task.updated`, `task.status_changed`, `task.assignee_changed`, `task.deleted`
- **项目相关:** `project.created`, `project.updated`, `project.deleted`
- **工作周期:** `cycle.created`, `cycle.started`, `cycle.completed`, `cycle.reopened`, `cycle.archived`, `cycle.deleted`
- **CRM 实体:** `client.*`, `counterparty.*`, `contact.*`, `company.*`, `organization.*`
- **协同交流:** `comment.added`, `file.uploaded`, `chat.message_created`, `chat.message_updated`, `chat.message_deleted`
- **界面渲染:** `render.before`, `render.after`

---

## 6. Web UI 集成：位置插槽 (PositionRegistry) 与模板

无需修改核心模板，即可通过 `Web\System\Module\PositionRegistry` 将自定义小部件挂载到 CRM 页面：

- **`task.detail.sidebar`**: 任务详情侧边栏。
- **`task.detail.tabs`**: 任务详情卡片扩展选项卡。
- **`project.detail.sidebar`**: 项目详情侧边栏。
- **`client.detail.sidebar`**: 客户详情侧边栏。
- **`dashboard.widgets.top`**: 控制面板顶部小部件区域。
- **`header.actions.right`**: 顶部导航栏右侧操作区。

---

## 7. 静态资源与安全规范

- **静态资源:** 支持全局载入 (`assets.css`, `assets.js`) 以及按路由定向按需加载 (`assets.css_routes`, `assets.js_routes`)。
- **静态安全审查:** 严禁执行系统命令 (`exec`, `passthru`, `system`) 及危险函数。
- **熔断保护:** 连续 5 次执行失败自动开启熔断 (`OPEN`)，隔离故障模块，并在 60 秒后进入半开状态 (`HALF_OPEN`) 自动尝试恢复。

---

*文档版本适用于 TropaTT CRM 2026 核心发布系列。*
