# TropaTT

**The free, self-hosted, open-source CRM, task manager, and work platform — with 20+ AI tools, built-in team chat, automation, REST API, and no artificial SaaS limits. For freelancers, teams, and businesses that want their data on their own server.**

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-4F5B93?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Zero Deps](https://img.shields.io/badge/Dependencies-0%20packages-6f42c1?style=flat-square)](#by-the-numbers)
[![Self Hosted](https://img.shields.io/badge/Self--hosted-No%20Limits-12805C?style=flat-square)](#self-hosted-your-server-your-rules)
[![AI](https://img.shields.io/badge/AI-20%2B%20workflows-111827?style=flat-square)](#ai--what-it-can-do)
[![PHP CI](https://github.com/Anton-Barinov/TropaTT/actions/workflows/php-ci.yml/badge.svg)](https://github.com/Anton-Barinov/TropaTT/actions/workflows/php-ci.yml)
[![License](https://img.shields.io/badge/License-AGPL--3.0-blue?style=flat-square)](LICENSE)

**Live demo:** [demo.tropatt.com](https://demo.tropatt.com/) — `admin` · `adminadmin`

---

---

## Table of Contents

- [English](#english)
  - [What's TropaTT](#whats-tropatt)
  - [Why TropaTT](#why-tropatt)
  - [Who it's for](#who-its-for)
  - [What's inside](#whats-inside)
  - [Feature overview](#feature-overview)
  - [AI — what it can do](#ai--what-it-can-do)
  - [Team chat](#team-chat)
  - [How people use it](#how-people-use-it)
  - [Automation & API](#automation--api)
  - [Self-hosted. Your server, your rules.](#self-hosted-your-server-your-rules)
  - [Getting started](#getting-started)
  - [FAQ](#faq)
  - [By the numbers](#by-the-numbers)
  - [Tech stack](#tech-stack)
  - [Project layout](#project-layout)
  - [Under the hood](#under-the-hood)
  - [Docs](#docs)
  - [Open-source project files](#open-source-project-files)
  - [Maintenance and contributor workflow](#maintenance-and-contributor-workflow)
  - [Security-sensitive areas](#security-sensitive-areas)
  - [AI-assisted maintenance](#ai-assisted-maintenance)
  - [Who built this](#who-built-this)
- [Русский](#русский)
  - [Что такое TropaTT](#что-такое-tropatt)
  - [Почему TropaTT](#почему-tropatt)
  - [Для кого](#для-кого)
  - [Что внутри](#что-внутри)
  - [Обзор возможностей](#обзор-возможностей)
  - [ИИ — что он умеет](#ии--что-он-умеет)
  - [Командный чат](#командный-чат)
  - [Как это используют](#как-это-используют)
  - [Автоматизация и API](#автоматизация-и-api)
  - [Свой сервер — свои правила](#свой-сервер--свои-правила)
  - [Установка](#установка)
  - [FAQ](#faq-1)
  - [В цифрах](#в-цифрах)
  - [Технологии](#технологии)
  - [Структура](#структура)
  - [Как устроено](#как-устроено)
  - [Документация](#документация)
  - [Файлы open-source проекта](#файлы-open-source-проекта)
  - [Сопровождение проекта](#сопровождение-проекта)
  - [Области, где важна безопасность](#области-где-важна-безопасность)
  - [Где помогает AI при сопровождении](#где-помогает-ai-при-сопровождении)
  - [Кто сделал](#кто-сделал)
- [中文](#中文)
  - [TropaTT 是什么](#tropatt-是什么)
  - [为什么 TropaTT](#为什么-tropatt)
  - [适合谁](#适合谁)
  - [功能](#功能)
  - [功能一览](#功能一览)
  - [AI — 能做什么](#ai--能做什么)
  - [团队聊天](#团队聊天)
  - [使用方式](#使用方式)
  - [自动化与 API](#自动化与-api)
  - [自托管，你的规则](#自托管你的规则)
  - [安装](#安装)
  - [常见问题](#常见问题)
  - [数字说话](#数字说话)
  - [技术栈](#技术栈)
  - [结构](#结构)
  - [内部原理](#内部原理)
  - [文档](#文档)
  - [开源项目文件](#开源项目文件)
  - [维护和贡献流程](#维护和贡献流程)
  - [安全敏感区域](#安全敏感区域)
  - [AI 辅助维护](#ai-辅助维护)
  - [谁做的](#谁做的)

---

## English

### What's TropaTT

TropaTT is a free, self-hosted, open-source PHP/MySQL work platform for client projects. It brings CRM, tasks, projects, Kanban, Gantt, calendar, team chat, automation, REST API, and 20+ AI-assisted workflows into one system that you install on your own server.

It was built for people who manage real work every day: freelancers with many clients, small agencies shipping projects with a handful of people, service companies coordinating field work, studios running campaigns, and teams tired of keeping clients in one app, tasks in another, chat somewhere else, and reports in a spreadsheet.

The practical difference is control. TropaTT does not charge per seat and does not add plan-based caps for users, tasks, projects, or clients. Your data, backups, integrations, and update decisions stay with you. The actual capacity still depends on your hosting, database, configuration, storage, and workload; a small team can start on basic PHP/MySQL hosting and move to stronger infrastructure when it grows.

---

### Why TropaTT

Here's the problem. You have clients in one app. Tasks in another. Team chat in a third. Calendar in a fourth. A spreadsheet for tracking. Nothing talks to anything else. And then the cloud vendor raises prices, limits your seats, or goes down.

**TropaTT replaces all of that with one self-hosted system.**

- **CRM + task manager + project tracker in one place.** Client cards, task hierarchies, Kanban boards, Gantt timelines, and team chat — all on the same data. No copying between apps. No "wait, where did we discuss that?"
- **Actual AI that helps you work.** Not a chatbot sidebar. AI idea analysis turns a sentence like "client wants a booking integration" into a full task hierarchy with subtasks and priorities. AI daily and weekly plans tell you what to focus on — based on your real deadlines and workload. It generates summaries, checklists, risk assessments, meeting briefs. Tools that save time, not gimmicks.
- **Built-in team chat.** No Slack, no Discord, no extra subscription. Discussions live next to the work.
- **No artificial SaaS limits.** No plan-based user caps, task caps, or project caps. Your real limits are your server resources, not a vendor pricing page.
- **Your data, your server.** Every client, task, file, chat message, and business record stays on your infrastructure. GDPR compliance is under your control — not a vendor's promise.
- **Runs where PHP and MySQL run.** Use a local machine, home or office server, VPS, cloud VM, or shared hosting. For public hosting, a $3–5/month PHP/MySQL plan is enough to start.
- **Zero external PHP dependencies.** No Laravel, no Symfony, no Doctrine, no Composer tree of 200 packages. The whole micro-kernel is hand-written. One codebase, not a hundred moving parts.
- **Install in a browser.** Upload files, open the installer, enter MySQL credentials, create an admin account. No terminal, no command line, no DevOps.

---

### Who it's for

TropaTT works for anyone managing clients and executing work. Here's who uses it:

**Freelancers and solo professionals** running 5–50 clients. You get a CRM, task tracker, AI daily planner, and project history — without paying per seat. One person, one tool, zero monthly SaaS bills.

**Small teams (2–15 people).** You need client records, task management, team chat, and project visibility. TropaTT gives you this without managing separate subscriptions for each function.

**Growing companies (15–100+).** You need roles, permissions, workflow automation, SLA, approvals, webhooks, and API access. TropaTT has all of it, without enterprise pricing.

**By industry**: marketing agencies, dev shops, design studios, construction and installation teams, legal and consulting firms, B2B service companies. Basically, anyone who does client work and needs to track it.

**By role**: founders who need the big picture. Project managers running timelines and workloads. Team leads assigning and reviewing work. Individual contributors who want a clear task list and a plan for the day.

---

### What's inside

**CRM.** Clients, counterparties, companies, contacts, organizations, departments, teams. Custom fields for your industry. Full history: every project, task, and communication tied to each client.

**Task manager.** Full hierarchy: parent tasks, subtasks, checklists. Statuses, priorities, due dates, assignees, tags. Task dependencies. WIP limits. Comments with file attachments and @mentions. Templates for recurring work. Human-readable task keys (PRJ-001). Mass actions when you need to update 20 tasks at once.

**Projects.** Milestones, risks, workload context, timeline views. Kanban boards for flow. Gantt charts for deadlines and dependencies. Project templates for repeatable delivery.

**Personal planning.** My Day and My Week views — for both solo prioritization and team coordination. AI-generated plans based on your actual tasks, deadlines, and calendar.

**Calendar.** Events linked to tasks and projects. Configurable working days, holidays, and business hours — used for SLA deadline calculation.

**Team chat.** Built in, not bolted on. Project chats, direct messages, group chats. File attachments, images, @mentions, replies. URL routing so you can link to a conversation. Real-time polling so new messages appear without losing your draft. More details [below](#team-chat).

**Notifications.** Real-time alerts for assignments, comments, mentions, deadlines, approvals. Push via browser API. History in the notification center.

**Analytics.** Dashboards, KPIs, workload analysis, risk signals, team capacity reports. All from real execution data, not manual entry.

**Admin panel.** Users, roles, permissions, statuses, priorities, SLA policies, workflow rules, webhooks, API clients, modules, audit logs, feature flags, rate limits, AI provider settings.

**Ideas + AI analysis.** Capture a raw idea or client request. Let AI assess feasibility and risk. AI proposes a structured task hierarchy. Review and convert to real tasks with a click. "We should probably do something about client retention" → actionable plan in minutes.

---

### Feature overview

| Area | What you get | Why it matters |
|---|---|---|
| **CRM** | Clients, counterparties, companies, contacts, orgs | All business relationships in one place |
| **Tasks** | Hierarchy, subtasks, checklists, WIP limits, dependencies, templates, human-readable keys (PRJ-001) | One task tracker instead of a separate subscription |
| **Projects** | Milestones, risks, Gantt, workload context, templates | Delivery control with timeline and responsibility |
| **Kanban** | Drag-and-drop board for task flow | Visual status management, fewer bottlenecks |
| **Gantt** | Timeline with dependency-aware scheduling | Deadline visibility and resource planning |
| **Calendar** | Events, agenda, day/week views, business calendar | Scheduled work connected to task context |
| **Chat** | Built-in messenger — channels, DMs, attachments, mentions | Communication in the same tool as the work |
| **Notifications** | Real-time alerts, push, notification center | No missed deadlines, mentions, or approvals |
| **Analytics** | Dashboards, KPIs, workload, risks, team capacity | Decisions from real execution data |
| **Automation** | Workflow rules, SLA, approvals, webhooks, jobs | Less manual coordination, fewer errors |
| **AI (20+ tools)** | Idea analysis, plans, decomposition, summaries, checklists, risk review, meeting prep | AI that saves time in real workflows |
| **Admin** | Users, roles, permissions, feature flags, modules, logs | Full control over your workspace |
| **Intake** | Capture, triage, and accept incoming client requests before turning them into tasks | Separate raw requests from real work, accept into tasks in one click |
| **Privacy** | 100% local data, no cloud access | Zero vendor lock-in, GDPR under your control |
| **No plan caps** | Users, tasks, projects, clients, files are not capped by SaaS pricing | Scales with your infrastructure, not your bill |
| **Install** | Browser wizard for any PHP/MySQL host | First launch in minutes, no terminal |
| **Zero deps** | No external PHP packages, custom micro-kernel | No supply-chain risk, one codebase |

---

### AI — what it can do

TropaTT has more AI depth than most SaaS CRMs. Not because someone bolted a chatbot onto the sidebar, but because AI is integrated into the workflows where it actually makes you faster.

You bring your own provider keys (OpenAI, Anthropic, DeepSeek, Google, any compatible API). Everything runs server-side. Your business data never touches an AI service unless you explicitly configure it. Every suggestion is preview-before-apply — nothing changes automatically without your review.

**The standout feature: AI idea analysis.**

Here's how it works. You write a few sentences about an idea, client need, or problem. The AI evaluates scope, feasibility, risks, complexity. Then it proposes a concrete task hierarchy: parent tasks, subtasks, priorities, with reasoning. You review the proposal. One click converts it to real tasks.

Real example: _"Client wants a booking system integrated with their website."_ → AI proposes: research existing APIs (2 subtasks) → design integration architecture → implement booking endpoint → build frontend UI → integration tests → deployment checklist. What was a 30-minute manual planning session becomes a 30-second review-and-confirm.

**All 22 AI workflows:**

AI Idea Analysis · Task Decomposition · Daily Work Plan · Weekly Work Plan · Task Summary · Next Action Suggestions · Checklist Generation · Task Quality Review · Comment Draft Generation · Task Priority Ordering · Project Summary · Project Risk Summary · Project Client Report · Client Summary · Client Meeting Preparation · Client Data Quality Review · Analytics KPI Explanation · Analytics Risks Explanation · Team Workload Summary · Calendar Event Agenda · Dashboard Daily Digest · Semantic Search

**How safety works:**
- Preview-only by default. No automatic writes to your data.
- All AI calls go through the backend API. Provider keys never reach the browser.
- Role-based access per AI capability.
- 43 feature flags for granular rollout.
- Rate and cost limits per workflow type.
- Raw prompts and sensitive context are not stored by default. Only sanitized metadata.

---

### Team chat

TropaTT has a real messenger inside the workspace. Not an integration, not an embed, not a separate subscription. It lives in the same system as your tasks, projects, and clients.

What you get:
- **Two-panel layout.** Conversations on the left, messages on the right.
- **Project and team channels.** Each project gets its own chat. General channels for company-wide stuff.
- **Direct messages.** One-on-one and group conversations.
- **Messages with attachments.** Text, files, images. @mentions with user search. Reply chains.
- **URL-routed chats.** Every conversation has its own URL. Link directly to a discussion.
- **Remembers where you were.** Restores your last active chat on page reload.
- **Live polling.** New messages appear automatically. Your draft stays intact.
- **Quick chat creation.** Search users by name, start a conversation instantly.
- **Participant controls.** Chat owners add and remove members.
- **Enter sends, Shift+Enter for new line.** Exactly what you expect.

The point: you stop asking "where did we discuss this?" because the discussion lives next to the task, the project, and the client context.

---

### How people use it

End-to-end cycle for client work, whether you're a team of 1 or 100:

1. **Capture** — an idea, client request, problem, or incoming lead.
2. **Analyze** — yourself or let AI break it down: scope, risks, feasibility, approach.
3. **Structure** — into projects, tasks, subtasks, checklists, assignees, deadlines.
4. **Coordinate** — through chat, comments, @mentions, notifications.
5. **Execute** — task lists, Kanban, My Day, My Week.
6. **Track** — Gantt, dashboards, analytics, workload, risk signals.
7. **Automate** — workflow rules, SLA policies, webhooks, approval chains.

**Real examples:**

- **Freelancer:** client inquiry → AI analyzes → structured task plan → My Day → execute → chat with client → done.
- **Agency:** client brief → AI decomposes → project + milestones → Kanban → Gantt tracking → client report → analytics.
- **Service company:** counterparty → project → install tasks → Gantt → daily plans → SLA monitoring → sign-off.
- **B2B ops:** company → contacts → tasks + approvals → reminders → webhook → dashboard.

---

### Automation & API

TropaTT's automation and API are production-grade. Built for teams that need the system to talk to the rest of their stack.

- **Workflow rules** — trigger actions on conditions (status change, field update, time-based).
- **SLA management** — service level expectations with deadline tracking and breach alerts.
- **Approval flows** — multi-step decision chains for controlled changes.
- **Webhooks** — fire events to external systems when CRM records change.
- **API clients and keys** — programmatic access with scoped permissions.
- **Background jobs** — scheduled and queued for imports, exports, AI workflows.
- **Module system** — extend business logic without touching core. 19 CLI commands.
- **743 documented REST endpoints** — every entity, task, project, chat, calendar, analytic, and admin function accessible via API.
- **OpenAPI 3.1 spec** — generated from route config, never out of sync with reality.

---

### Self-hosted. Your server, your rules.

TropaTT is open source. Deploy it on your own server. Inspect the code. Modify it. No license fees. No mandatory subscription.

Paid services (customization, integration, migration, support) exist as optional services. They're not required to use the software.

**Why self-hosting matters, practically:**

- **No vendor lock-in.** SaaS raises prices or shuts down, your data goes with it. TropaTT data lives on your server. Migrate, back up, move — anytime.
- **No artificial product limits.** Users, tasks, projects, and clients are not capped by a SaaS plan. The real ceiling is your hardware, database, storage, and configuration.
- **No one can block you.** Your access doesn't get suspended over a billing glitch or policy change. The system is yours.
- **Privacy by design.** Client data, tasks, files, chats — all on your infrastructure. GDPR compliance is directly under your control.
- **Cost control.** You can run TropaTT locally with no external hosting cost, deploy it on your own server, or start with a $3–5/month PHP/MySQL shared hosting plan. Scale by upgrading infrastructure, not your SaaS plan.

---

### Getting started

Browser-based installer. No terminal, no composer, no npm. Designed for shared hosting, VPS, local servers, and simple PHP/MySQL deployments.

**What you need:**
- PHP 8.1+
- An empty MySQL database
- Any web server (Apache, Nginx, or PHP-compatible host)
- Write access for `api/` config and `storage/`

**Steps:**
1. Upload the files.
2. Create an empty MySQL database.
3. Open your domain in a browser. TropaTT detects it's not configured and launches the installer.
4. The installer checks your environment, asks for MySQL credentials, site URL, timezone, and the first admin account.
5. It writes `api/.env`, creates the schema, seeds reference data (statuses, priorities, roles, permissions), creates the admin user, and locks the installer.
6. Log in. Start working.

**Shared hosting example:**
Upload `api/`, `web/`, `modules/`, `index.php`, `favicon.ico`, `README.md` → create a MySQL database in your hosting panel → open your domain → follow the installer → done.

---

### FAQ

**What exactly is TropaTT?**
A free, open-source, self-hosted CRM + task manager + project platform. PHP 8.1+ and MySQL. Runs on your server. Combines clients, tasks, projects, Kanban, Gantt, calendar, built-in chat, analytics, automation, and 20+ AI tools.

**CRM or task manager?**
Both. CRM for clients, contacts, companies. Full task manager with hierarchy, Kanban, Gantt, checklists, and daily planning. You don't need separate tools.

**Can a freelancer use this?**
Yes. Minimum team size: 1. Manage clients, track tasks, plan your day with AI, analyze ideas — one tool, no per-seat pricing.

**Works on shared hosting?**
Yes. Standard PHP 8.1+/MySQL shared hosting ($3–5/month) is enough. The browser installer handles everything.

**What can the AI do?**
22 workflows: idea analysis (turns a paragraph into a task plan), daily/weekly plans, task decomposition, summaries, checklists, risk reviews, meeting prep, and more. You bring your AI provider keys. Processing is server-side. AI never modifies data without your review.

**How does AI idea analysis work?**
You describe an idea → AI evaluates scope/risks/complexity → AI proposes a structured task hierarchy → you review → one click to convert to real tasks.

**Where's my data?**
On your server. 100%. TropaTT never syncs to a cloud. No one — including the developer — has access to your installation.

**User limits? Task limits?**
There are no plan-based caps. You can create as many users, tasks, projects, and clients as your server can handle.

**Does that mean unlimited performance?**
No. TropaTT removes vendor-side limits, not physics. Performance depends on PHP hosting, MySQL configuration, storage, indexes, background jobs, file volume, and concurrent users.

**Is it really open source?**
Yes. Free to use, modify, and deploy.

**API access?**
743 documented REST endpoints. OpenAPI 3.1 spec generated from code. Every feature is programmable.

**Can I customize it?**
Yes. PHP/MySQL stack, modules, REST API, webhooks, workflow rules, custom fields, roles, permissions.

**Who built this?**
**Barinov Anton**, PHP developer. Creator of TropaTT.

---

### By the numbers

| Metric | Value |
|--------|-------|
| API endpoints | 743 (695 route records) |
| Web routes | 68 pages, ~55 templates |
| Backend services | 100+ |
| Repositories | 65+ |
| Domain modules | 35+ |
| JS modules | 24 custom vanilla JS modules, no SPA framework, no build step |
| Public CI | PHP lint on 8.1 and 8.2 |
| AI endpoints | 65 |
| AI workflows | 22 |
| Feature flags | 43 |
| Frontend API coverage | 70.1% (521/743 in UI) |
| External PHP deps | 0 |
| Frontend vendor libs | 3 (Bootstrap 5, FA6, SortableJS) |
| OpenAPI tooling | `api/scripts/generate_openapi.php` |
| Public maintainer docs | `docs/maintainer/` |
| Module CLI commands | 19 |
| Deployment options | Local machine, home/office server, VPS, cloud VM, shared hosting |
| External hosting starting point | ~$3/month shared hosting |

---

### Tech stack

- **Backend:** PHP 8.1+, custom micro-kernel. Zero external packages. No Laravel/Symfony/Doctrine.
- **Database:** MySQL.
- **Frontend:** PHP-rendered MPA with Bootstrap 5 for UI/layout and custom vanilla JS ES5+ modules for behavior. No React/Vue/Angular. No build step. No bundler.
- **Architecture:** API-first. Web UI uses REST API for all data. Zero direct database access from the web layer.
- **Security:** Dual auth (cookie + CSRF for web, Bearer for API). Granular RBAC. Rate limiting. File quarantine. Admin impersonation. Sanitized error responses.
- **Testing:** Public PHP lint CI is enabled. A fast MySQL-backed integration workflow is tracked in the public hardening backlog.
- **AI layer:** Configurable providers (OpenAI, Anthropic, DeepSeek, Google, compatible). Intent-based workflows. Prompt templates. JSON Schema validation. Preview-before-apply.
- **Docs:** Public maintainer docs are included in `docs/maintainer/`. OpenAPI generation tooling is included in `api/scripts/generate_openapi.php`.

---

### Project layout

```text
TropaTT/
├── api/           # API core — controllers, services, repositories, config, migrations, scripts
├── web/           # Web UI — installer, 68 pages, ~55 templates, 24 JS modules, assets
├── modules/       # Pluggable business modules (boilerplate, hello-world, WIP-limit examples)
├── docs/maintainer/ # Public maintainer checklists and OSS readiness notes
├── index.php      # Root entry point
└── README.md      # You're reading it
```

Backend modules organized in 9 groups: Auth/Users · CRM (clients, counterparties, contacts) · Projects/Tasks · Planning (calendar, recurring, reminders) · Communication (chats, notifications, push) · Automation (workflows, SLA, webhooks) · Analytics (dashboards, reports) · AI/LLM (11 modules: providers, intents, suggestions, actions, jobs, prompts, schemas, usage, retention, semantic, context builders) · Admin (settings, logs, audit, flags, modules, storage, trash, search).

---

### Under the hood

**Zero external PHP dependencies.** Router, DI container, autoloader, query builder (no ORM), validator, HTTP client, response handler, migration manager, and module system are hand-written. One `composer.json` with `php >=8.1`. No supply-chain risk. No version conflicts. No dependency audit debt.

**Documented architecture decisions:**
ADR-001 — custom micro-kernel, no framework.
ADR-002 — single JSON response envelope for all API calls.
ADR-003 — no ORM, PDO + Repository pattern.
ADR-001 Web — custom PHP MVC.
ADR-003 Web — custom vanilla JS, Bootstrap 5 UI, no build step.
ADR-006 Web — server-side session verification (cookie + CSRF).

**API-first.** The web UI does not touch the database. Every data load and state change goes through `window.CRM.api.request` → `/api/v1/...`. The API is authoritative. The UI is one consumer.

**Testing roadmap.** The public repository currently ships a fast PHP syntax CI workflow for PHP 8.1 and 8.2. The next hardening step is a MySQL-backed integration workflow, tracked in the public milestone. Security-sensitive areas such as RBAC, CSRF, file access, AI data handling, and OpenAPI consistency are called out in the maintainer checklists.

---

### Docs

The public repository currently includes a focused maintainer documentation set:

| Layer | Where | What |
|-------|-------|------|
| Maintainer | `docs/maintainer/` | Release checklist, security review checklist, Codex for OSS notes, starter issues, GitHub labels |
| API tooling | `api/scripts/generate_openapi.php` | OpenAPI generation entry point for API documentation automation |
| Project root | `README.md`, `AGENTS.md`, `SECURITY.md`, `CONTRIBUTING.md`, [`INSTALL_TROUBLESHOOTING.md`](INSTALL_TROUBLESHOOTING.md), [`SHARED_HOSTING_GUIDE.md`](SHARED_HOSTING_GUIDE.md), [`WEBHOOK_SECURITY.md`](WEBHOOK_SECURITY.md) | Public usage, agent, security, contribution, installation troubleshooting, shared hosting, and webhook security guidance |
---

### Open-source project files

The public repository includes standard project files for maintainers, contributors, security reports, and early adopters:

- [LICENSE](LICENSE) — AGPL-3.0 license.
- [CONTRIBUTING.md](CONTRIBUTING.md) — contribution workflow, checks, PR expectations, security checklist.
- [SECURITY.md](SECURITY.md) — private vulnerability reporting and security-sensitive areas.
- [CHANGELOG.md](CHANGELOG.md) — public preview changelog.
- [ROADMAP.md](ROADMAP.md) — public preview roadmap without promised dates.
- [SUPPORT.md](SUPPORT.md) — support expectations and issue guidance.
- [INSTALL_TROUBLESHOOTING.md](INSTALL_TROUBLESHOOTING.md) — common installation problems and fixes.
- [SHARED_HOSTING_GUIDE.md](SHARED_HOSTING_GUIDE.md) — step-by-step shared hosting installation guide.
- [WEBHOOK_SECURITY.md](WEBHOOK_SECURITY.md) — webhook authentication, verification, and best practices.
- [.github/ISSUE_TEMPLATE](.github/ISSUE_TEMPLATE) — bug, feature, documentation, and installation templates.
- [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md) — PR checklist for tests, docs, security, API, and installer impact.

TropaTT is open source, but the public repository intentionally excludes local secrets, runtime data, private business data, internal screenshots, and maintainer-only artifacts.

---

### Maintenance and contributor workflow

TropaTT is maintained as a real application, not as a demo repository. A typical change can touch several layers at once: database schema, migrations, API routes, services, repositories, permissions, UI pages, JavaScript modules, tests, generated documentation, and installer behavior.

The maintainer work behind the project includes reviewing code changes, checking security-sensitive paths, triaging bugs, validating API compatibility, keeping OpenAPI documentation in sync with routes, preparing releases, and making sure the installer, migrations, tests, and user-facing docs do not drift apart.

Areas where contributor help is especially useful:

- testing installation on different PHP/MySQL environments;
- improving documentation, examples, and translations;
- reviewing API changes and OpenAPI compatibility;
- adding tests for CRM, tasks, projects, chat, automation, calendar, analytics, and AI workflows;
- checking authentication, permissions, file handling, webhooks, and AI provider configuration;
- validating upgrade and migration paths between releases;
- improving accessibility and UI consistency across Bootstrap-based pages.

The project is intentionally built without a large dependency tree, but that also means core infrastructure is part of the codebase. Contributions should be reviewed with the same care as application features.

---

### Security-sensitive areas

TropaTT handles business data: clients, counterparties, tasks, projects, files, chats, notifications, API keys, webhooks, automation rules, audit logs, and optional AI provider configuration. Security review matters because many features operate on private workspace data and can affect permissions, integrations, or stored files.

Security-sensitive parts of the project include:

- authentication and session handling;
- CSRF protection in the web interface;
- Bearer-token access for REST API clients;
- granular RBAC and permission checks;
- admin impersonation and auditability;
- file uploads, storage, quarantine, and download permissions;
- webhook delivery and external HTTP calls;
- workflow automation rules and background jobs;
- installer lock files, environment configuration, and database migrations;
- AI provider keys, server-side AI requests, prompt context, and preview-before-apply behavior;
- error responses, logging, data masking, and sensitive-field handling.

Please do not report security issues as public GitHub issues. Use the repository security policy or contact the maintainer privately.

---

### AI-assisted maintenance

TropaTT has many moving parts: REST endpoints, generated OpenAPI tooling, installer flows, permissions, workflow automation, AI workflows, chat, calendar, analytics, and public CI. AI-assisted maintenance is useful when a change needs to stay consistent across several layers of the project.

Useful AI-assisted maintainer workflows include:

- reviewing pull requests for permission, validation, and API compatibility issues;
- generating or updating tests when routes, services, workflows, or UI behavior change;
- checking whether documentation matches the current implementation;
- summarizing large diffs before release preparation;
- finding missing validation, authorization, error handling, and edge cases;
- preparing changelog entries and release notes;
- helping contributors understand project structure before they submit changes;
- comparing OpenAPI documentation with backend route definitions and frontend API usage.

AI suggestions are treated as review input, not as automatic authority. Human review remains required for code, security-sensitive changes, database migrations, permissions, release decisions, and anything that can affect user data.

---

### Who built this

TropaTT is developed by **Barinov Anton**, PHP developer.

- **GitHub:** [Anton-Barinov](https://github.com/Anton-Barinov)
- **Repo:** [github.com/Anton-Barinov/TropaTT](https://github.com/Anton-Barinov/TropaTT)

The project is a practical, self-hosted work system: PHP-first, MySQL-compatible, installable through a browser, zero artificial limits, and built with data privacy as a first principle.

---

## Русский

### Что такое TropaTT

TropaTT — бесплатная self-hosted рабочая платформа с открытым исходным кодом на PHP/MySQL для клиентских проектов. В одной системе собраны CRM, задачи, проекты, Канбан, Гант, календарь, командный чат, автоматизация, REST API и 20+ ИИ-процессов. Всё это разворачивается на вашем сервере, VPS, локальной машине или обычном PHP-хостинге.

Проект сделан для людей, которые каждый день ведут реальную работу: фрилансеров с десятками клиентов, небольших агентств, сервисных компаний, выездных бригад, студий и команд, которым надоело держать клиентов в одной системе, задачи во второй, чат в третьей, а отчёты — в таблицах.

Главная идея — контроль. В TropaTT нет оплаты за каждого пользователя и нет тарифных ограничений на количество пользователей, задач, проектов или клиентов. Данные, бэкапы, интеграции и решение об обновлениях остаются у вас. При этом производительность не бесконечная: реальный предел зависит от хостинга, базы данных, настроек, файлов и нагрузки. Малой команде достаточно начать с недорогого PHP/MySQL-хостинга, а при росте перейти на более мощную инфраструктуру.

---

### Почему TropaTT

Проблема вот в чём. Клиенты — в одном приложении. Задачи — в другом. Чат команды — в третьем. Календарь — в четвёртом. Таблица для учёта. Ничего не связано между собой. А потом облачный провайдер поднимает цены, урезает лимиты, или случается сбой.

**TropaTT заменяет этот хаос одной самостоятельной системой:**

- **CRM + Таск-менеджер + Проектный трекер в одном инструменте.** Карточки клиентов, иерархии задач, Канбан-доски, диаграммы Ганта и встроенный командный чат работают на одних данных. Никакого копирования между приложениями. Никакой потери контекста.
- **20+ ИИ-инструментов, которые реально помогают работать.** AI-проработка идей превращает сырую мысль в структурированный план задач. AI-план на день подсказывает приоритеты. AI генерирует сводки, декомпозиции, чеклисты, оценки рисков, подготовку к встречам — не покидая рабочее пространство.
- **Встроенный командный чат.** Обсуждайте проекты и задачи там же, где идёт работа. Никакого Slack, Discord или отдельной подписки на мессенджер.
- **Без искусственных SaaS-лимитов.** Пользователи, задачи, проекты и клиенты не ограничены тарифным планом. Реальные ограничения задаёт ваш сервер, база данных, хранилище и настройки.
- **Полная приватность.** Клиенты, задачи, файлы, чаты и бизнес-данные остаются на вашем сервере. Никакой облачный провайдер не имеет доступа. GDPR и хранение данных под вашим контролем — а не под обещания вендора.
- **Работает везде, где есть PHP и MySQL.** Локальный компьютер, домашний или офисный сервер, VPS, облачная VM или шаред-хостинг. Если нужен публичный хостинг, для старта достаточно PHP/MySQL-плана за 200–300 ₽/мес.
- **Ноль внешних PHP-зависимостей.** Никаких Laravel, Symfony, Doctrine, Composer-дерева из сотен пакетов. Всё микроядро написано вручную. Вы разворачиваете одну кодовую базу, а не сто пакетов.
- **Установка через браузер.** Загрузите файлы, откройте установщик в браузере, введите данные MySQL, создайте администратора. Без терминала, командной строки и DevOps.

---

### Для кого

TropaTT подходит всем, кто управляет клиентами и исполняет работу — независимо от размера команды, отрасли или роли:

**По размеру команды:**
- **Фрилансеры и соло-предприниматели** с 5–50 клиентами, которым нужен учёт задач, планирование дня с ИИ и вся история проектов в одном месте — без оплаты за каждое рабочее место.
- **Малые команды (2–15 человек)**, которым нужна CRM, задачи, чат и прозрачность проектов без зоопарка подписок.
- **Растущие компании (15–100+ человек)**, которым нужны роли, права доступа, автоматизация, SLA, согласования, вебхуки и API-интеграции — без ценников enterprise SaaS.

**По отрасли:**
- Маркетинговые и рекламные агентства, управляющие клиентскими кампаниями, сроками и результатами.
- IT-компании и команды разработки, отслеживающие проекты, баги и релизы.
- Дизайн-студии и креативные агентства, управляющие правками, активами и клиентскими согласованиями.
- Монтажные, строительные и выездные сервисные компании, координирующие задачи по объектам.
- Консалтинг, юрфирмы и профессиональные услуги, ведущие клиентские дела и документы.
- B2B-сервисные компании с контрагентами, договорами и повторяющейся работой.
- Любые команды, которым нужна и CRM, и таск-менеджер — а не что-то одно.

**По роли:**
- Основатели и руководители, которым нужна картина происходящего по клиентам, проектам и командам.
- Проджект-менеджеры, планирующие сроки по Ганту, отслеживающие вехи и управляющие загрузкой.
- Тимлиды, распределяющие задачи, контролирующие исполнение и координирующие через встроенный чат.
- Исполнители, которым нужен понятный список задач, план на день и сфокусированное рабочее пространство.

---

### Что внутри

**CRM и управление клиентами.** Карточки клиентов, контрагенты, компании, контакты, организации, отделы, команды. Настраиваемые поля под вашу отрасль. Детальные страницы клиентов с историей проектов, задач и коммуникаций. Управление контрагентами для B2B-отношений с заказчиками, подрядчиками, партнёрами и поставщиками.

**Таск-менеджер и исполнение.** Полная иерархия задач: родительские задачи, подзадачи, чеклисты. Статусы, приоритеты, сроки, исполнители, теги. Комментарии с вложениями и @упоминаниями. Шаблоны задач для повторяющейся работы. Зависимости между задачами. WIP-лимиты для предотвращения перегрузки. Массовые действия.

**Управление проектами.** Страницы проектов с вехами, рисками, контекстом загрузки и временными шкалами. Канбан-доски для потокового исполнения. Диаграммы Ганта для обзора сроков и зависимостей. Шаблоны проектов.

**Персональное планирование.** «Мой день» и «Моя неделя» — для индивидуальной и командной приоритизации. AI-планы на день и неделю на основе ваших реальных задач, сроков и календаря.

**Календарь.** События с привязкой к задачам и проектам. Повестка и обзор расписания. Настраиваемые рабочие дни, праздники и рабочие часы для расчёта SLA-сроков.

**Встроенный командный чат.** Полный CRM-мессенджер: чаты проектов, личные чаты, группы. Сообщения, вложения, изображения, @упоминания, ответы, URL-роутинг, живой polling. Не нужен Slack, Discord или Telegram.

**Уведомления.** Оповещения в реальном времени о назначении задач, комментариях, упоминаниях, изменениях сроков, согласованиях и системных событиях. Push-уведомления через браузерное API. Центр уведомлений с историей.

**Аналитика и дашборды.** Обзорный дашборд бизнеса. KPI, анализ загрузки, сигналы рисков, отчёты по ёмкости команды. Все данные из реального исполнения проектов и задач.

**Администрирование.** Пользователи, роли, гранулярные права доступа, статусы, приоритеты, SLA-политики, workflow-правила, вебхуки, API-клиенты, модули, аудит-логи, feature-флаги, лимиты, настройки AI-провайдеров — всё из панели администратора.

**Идеи и AI-проработка.** Захватите сырую идею, запрос клиента или бизнес-проблему. AI анализирует реализуемость, риски и сложность. AI предлагает структурированную иерархию задач. Просмотрите и превратите в реальные задачи одним кликом. Превращает «надо бы что-то сделать с X» в план действий за минуты.

---

### Обзор возможностей

| Область | Что даёт TropaTT | Почему это важно |
|---|---|---|
| **CRM** | Клиенты, контрагенты, компании, контакты, организации, отделы | Единое место для всех деловых связей и контекста |
| **Таск-менеджер** | Иерархия, подзадачи, чеклисты, WIP-лимиты, зависимости, шаблоны, человекочитаемые ключи (PRJ-001) | Замена отдельных подписок на таск-трекеры |
| **Проекты** | Вехи, риски, Гант, контекст загрузки, шаблоны | Контроль поставки со сроками и ответственностью |
| **Канбан** | Доска с перетаскиванием задач по статусам | Визуальное управление потоком, уменьшение заторов |
| **Гант** | Временная шкала с зависимостями | Обзор сроков и ресурсное планирование |
| **Календарь** | События, повестка, день/неделя, бизнес-календарь | Запланированная работа в контексте задач и проектов |
| **Командный чат** | Встроенный мессенджер с каналами, личными чатами, вложениями | Коммуникация в том же инструменте, где идёт работа |
| **Уведомления** | Оповещения в реальном времени, push, центр уведомлений | Ни одного пропущенного дедлайна, упоминания или согласования |
| **Аналитика** | Дашборды, KPI, загрузка, риски, ёмкость команды | Решения на основе реальных данных исполнения |
| **Автоматизация** | Workflow-правила, SLA, согласования, вебхуки, фоновые задачи | Меньше ручной координации и человеческих ошибок |
| **ИИ (20+ инструментов)** | Проработка идей, планы на день/неделю, декомпозиция, сводки, чеклисты, риски, подготовка к встречам | ИИ помогает работать умнее, а не просто чат-бот |
| **Админка** | Пользователи, роли, права, feature-флаги, модули, логи, настройки | Полный контроль над рабочим пространством |
| **Приватность** | 100% данных на вашем сервере, без облачного доступа | Нулевой vendor lock-in, GDPR под вашим контролем |
| **Без тарифных лимитов** | Пользователи, задачи, проекты, клиенты и файлы не ограничены SaaS-планом | Масштабируется с инфраструктурой, а не с тарифом подписки |
| **Установка** | Браузерный мастер для любого PHP/MySQL хостинга | Первый запуск за минуты, без терминала |
| **Ноль зависимостей** | Ни одного внешнего PHP-пакета, собственное микроядро | Никаких рисков supply-chain, разворачивается одна кодовая база |

---

### ИИ — что он умеет

В TropaTT **больше глубины ИИ, чем во многих SaaS CRM** — не потому что ИИ прикручен как чат-бот, а потому что он встроен в рабочие процессы, где реально экономит время.

Вы подключаете своего AI-провайдера (OpenAI, Anthropic, DeepSeek, Google или любой совместимый API формата OpenAI). Вся обработка идёт на стороне сервера. Данные вашего бизнеса никогда не покидают вашу инфраструктуру, пока вы явно не настроите провайдера. Все AI-предложения — preview-before-apply: ничего не меняется автоматически без вашего утверждения.

**Ключевая функция — AI-проработка идей:**
1. Напишите несколько предложений, описывающих идею, потребность клиента или бизнес-проблему.
2. AI оценивает масштаб, реализуемость, риски, сложность и влияние.
3. AI предлагает структурированную иерархию задач с родительскими задачами, подзадачами, приоритетами и обоснованием.
4. Вы проверяете предложение и превращаете его в реальные задачи одним кликом.

*Пример:* «Клиент хочет интеграцию системы бронирования с сайтом.» → AI предлагает: исследовать API (2 подзадачи), спроектировать архитектуру интеграции, реализовать эндпоинт бронирования, сделать frontend, интеграционные тесты, чеклист деплоя. То, что занимало 30 минут ручного планирования, становится 30-секундным обзором и подтверждением.

**Полный список AI-инструментов:**
AI-проработка идей · Декомпозиция задач · План на день · План на неделю · Сводка задачи · Предложение следующих действий · Генерация чеклистов · Проверка качества задачи · Черновик комментария · Приоритизация задач · Сводка проекта · Сводка рисков проекта · Клиентский отчёт · Сводка клиента · Подготовка к встрече · Проверка качества данных клиента · Объяснение KPI · Объяснение рисков аналитики · Сводка загрузки команды · Повестка календарного события · Ежедневный дайджест · Семантический поиск

**Модель безопасности ИИ:**
- Preview-only по умолчанию — никаких автоматических изменений бизнес-данных.
- Все AI-запросы через backend API — ключи никогда не попадают в браузер.
- Ролевой доступ для каждой AI-возможности.
- 43 feature-флага для гранулярного включения AI.
- Лимиты запросов и стоимости для каждого intent.
- Raw prompt и sensitive context не сохраняются по умолчанию.

---

### Командный чат

TropaTT включает полноценный командный мессенджер внутри рабочего пространства — не интеграцию, не embed, не отдельную подписку. Чат встроен в ту же систему, где живут задачи, проекты и клиенты:

- **Двухпанельный интерфейс:** список бесед слева, переписка справа.
- **Каналы проектов и команд:** у каждого проекта может быть свой чат. Общие командные каналы.
- **Личные сообщения:** беседы 1-на-1 и групповые чаты.
- **Расширенные сообщения:** текст, файлы, изображения, @упоминания с поиском, цепочки ответов.
- **URL-роутинг:** каждая беседа имеет свой URL — прямой переход к обсуждению.
- **Память сессии:** система восстанавливает последний активный чат при перезагрузке.
- **Живой polling:** новые сообщения появляются автоматически, без потери набранного текста.
- **Модалка создания чата:** поиск пользователей, мгновенное создание беседы.
- **Управление участниками:** владелец чата управляет составом, добавляет и удаляет.
- **Composer UX:** Enter отправляет, Shift+Enter — новая строка.

Это устраняет проблему «где мы это обсуждали?». Контекст задачи, обсуждение проекта и командная коммуникация находятся в одном пространстве.

---

### Как это используют

TropaTT поддерживает полный цикл клиентской работы для команд любого размера:

1. **Захват** — идея, запрос клиента, операционная проблема или входящий лид.
2. **Анализ** — вручную или с ИИ: масштаб, риски, реализуемость, предлагаемый подход.
3. **Структурирование** — превращение в проекты, задачи, подзадачи, чеклисты, ответственных, сроки.
4. **Координация** — обсуждение через встроенный чат, комментарии, упоминания, уведомления.
5. **Исполнение** — работа через списки задач, Канбан, «Мой день» и «Моя неделя».
6. **Отслеживание** — мониторинг через Гант, дашборды, аналитику, загрузку и сигналы рисков.
7. **Автоматизация** — повторяющиеся процессы через workflow-правила, SLA, вебхуки, цепочки согласований.

**Реальные примеры процессов:**
- **Фрилансер:** запрос клиента → AI-проработка → структурированный план задач → «Мой день» → исполнение → чат с клиентом → завершение.
- **Агентство:** бриф клиента → AI-декомпозиция → проект с вехами → Канбан команды → Гант → отчёт клиенту → аналитика.
- **Сервисная компания:** контрагент → проект → задачи монтажа → Гант → планы на день → SLA-контроль → подписание.
- **B2B-операции:** компания → контакты → задачи с согласованиями → напоминания → вебхук → дашборд CRM.

---

### Автоматизация и API

Слой автоматизации и REST API TropaTT — production-grade, для команд, которым нужна интеграция с остальным бизнес-стеком:

- **Workflow-правила** — автоматические действия при наступлении условий.
- **SLA-менеджмент** — ожидания по уровню сервиса с отслеживанием сроков и оповещениями о нарушениях.
- **Согласования** — многошаговые цепочки утверждения для контролируемых изменений.
- **Вебхуки** — отправка событий во внешние системы при изменениях в CRM.
- **API-клиенты и ключи** — программный доступ с ограниченными правами.
- **Фоновые задачи** — запланированная и очередейная обработка для импорта, экспорта, AI.
- **Модульная система** — расширение бизнес-логики без модификации ядра. 19 CLI-команд для управления модулями.
- **743 документированных REST API эндпоинта** — каждая CRM-сущность, задача, проект, чат, календарь, аналитика и административная функция доступны через API.
- **Спецификация OpenAPI 3.1** — генерируется из конфигурации маршрутов, никогда не расходится с реализацией.

---

### Свой сервер — свои правила

TropaTT опубликована как программное обеспечение с открытым исходным кодом. Вы можете развернуть её на своём сервере бесплатно. Вы можете изучать, модифицировать и расширять код.

**Что здесь означает открытый исходный код:**
- Бесплатно для любых целей — без лицензионных платежей и обязательных подписок.
- Полный исходный код доступен — настраивайте, аудируйте, участвуйте.
- Опциональные платные услуги (кастомизация, интеграция, миграция, поддержка) — это услуги, а не требования.

**Почему важно иметь систему на своём сервере:**
- **Никакого vendor lock-in.** Если SaaS CRM поднимает цены или закрывается, ваши данные заблокированы. В TropaTT данные на вашем сервере — мигрируйте, делайте бэкапы, переносите в любой момент.
- **Никаких тарифных лимитов.** Пользователи, задачи, проекты и клиенты не упираются в SaaS-план. Реальный потолок — железо, база данных, хранилище и конфигурация.
- **Вас нельзя заблокировать.** Доступ не может быть приостановлен из-за проблем с оплатой, изменения политики или автоматических флагов вендора.
- **Приватность по дизайну.** Клиентские данные, задачи, файлы и коммуникации остаются на вашей инфраструктуре. GDPR и хранение данных под вашим прямым контролем.
- **Контроль затрат.** TropaTT можно запустить локально без расходов на внешний хостинг, поставить на свой сервер или начать с PHP/MySQL-шаред-хостинга за 200–300 ₽/мес. Масштабируйтесь апгрейдом инфраструктуры — а не тарифа SaaS.

---

### Установка

TropaTT включает браузерный установщик для простого PHP/MySQL-развёртывания: локальная машина, свой сервер, VPS или шаред-хостинг. Без терминала, командной строки, composer и npm.

**Требования:**
- PHP 8.1 или новее
- База данных MySQL (пустая, готовая к использованию)
- Веб-сервер (Apache, Nginx или любой PHP-совместимый хостинг)
- Права записи для директорий конфигурации `api/` и `storage/`

**Быстрый старт:**
1. Загрузите файлы проекта на сервер или в локальную директорию веб-сервера.
2. Создайте пустую базу данных MySQL.
3. Откройте домен в браузере — TropaTT определит, что не настроена, и запустит установщик.
4. Установщик проверяет окружение, запрашивает хост/порт/БД/пользователя/пароль MySQL, URL сайта, часовой пояс и данные первого администратора.
5. Установщик создаёт `api/.env`, схему MySQL, заполняет справочные данные (статусы, приоритеты, роли, права), создаёт администратора, подготавливает хранилище и устанавливает lock-файлы.
6. Войдите и начинайте работать.

**Сценарий для шаред-хостинга:**
Загрузите `api/`, `web/`, `modules/`, `index.php`, `favicon.ico` и `README.md` → создайте БД MySQL в панели хостинга → откройте домен → следуйте установщику → готово.

---

### FAQ

**Что такое TropaTT?**
Бесплатная, с открытым исходным кодом, самостоятельная CRM, таск-менеджер и платформа управления бизнесом на PHP 8.1+ и MySQL. Объединяет клиентов, задачи, проекты, Канбан, Гант, календарь, встроенный чат, уведомления, аналитику, автоматизацию и 20+ ИИ-инструментов — всё на вашем сервере.

**Это CRM или таск-менеджер?**
И то, и другое. CRM для клиентов, контрагентов и компаний — и полноценный таск-менеджер для исполнения, Канбана, Ганта, чеклистов и ежедневного планирования. Не нужны отдельные подписки.

**Подходит ли фрилансерам?**
Да. У TropaTT нет минимального размера команды. Фрилансер управляет клиентами, задачами, планирует день с ИИ, анализирует идеи и хранит весь контекст проектов в одном месте — без платы за место.

**Работает ли на шаред-хостинге?**
Да. Стандартного PHP 8.1+ / MySQL шаред-хостинга (200–300 ₽/мес) достаточно. При этом TropaTT можно развернуть и локально на своём компьютере, на домашнем/офисном сервере или на VPS.

**Какие ИИ-возможности?**
20+ ИИ-инструментов: проработка идей, планы на день/неделю, декомпозиция задач, сводки, чеклисты, оценка рисков, подготовка к встречам и многое другое. Вы подключаете своего AI-провайдера. Вся обработка на сервере. ИИ не меняет данные без вашего утверждения.

**Как работает AI-проработка идей?**
Пишете несколько предложений об идее → AI оценивает масштаб, риски и сложность → AI предлагает структурированную иерархию задач → вы проверяете и превращаете в реальные задачи одним кликом.

**Где хранятся мои данные?**
На 100% на вашем сервере. TropaTT никогда не синхронизирует данные с облаком. Никто (включая разработчика) не имеет доступа к вашей установке или данным.

**Есть ли лимиты по пользователям или задачам?**
Тарифных лимитов нет. Пользователей, задач, проектов и клиентов может быть столько, сколько потянет ваш сервер.

**Это значит, что производительность бесконечная?**
Нет. TropaTT убирает ограничения со стороны SaaS-вендора, но не отменяет ограничения инфраструктуры. Скорость и объём зависят от PHP-хостинга, настроек MySQL, индексов, хранилища, фоновых задач, количества файлов и одновременных пользователей.

**Это открытый исходный код?**
Да, опубликовано как open-source проект. Бесплатно для использования, модификации и развёртывания.

**Есть ли API?**
Да, 743 документированных REST API эндпоинта со спецификацией OpenAPI 3.1, сгенерированной из кода. Каждая функция доступна программно.

**Можно ли кастомизировать?**
Да. PHP/MySQL стек, модульные расширения, REST API, вебхуки, workflow-правила, настраиваемые поля, роли, права — всё адаптируется под ваши процессы.

**Кто разрабатывает TropaTT?**
Разрабатывает **Антон Баринов**, PHP-разработчик и создатель проекта TropaTT.

---

### В цифрах

| Метрика | Значение |
|--------|----------|
| API эндпоинтов | 743 нормализованных, 695 записей маршрутов |
| Веб-страниц | 68 маршрутов, ~55 шаблонов |
| PHP-сервисов | 100+ |
| Репозиториев БД | 65+ |
| Доменных модулей | 35+ |
| JavaScript-модулей | 24 собственных модуля на ванильном JS, без SPA-фреймворков и сборки |
| Публичный CI | PHP lint на 8.1 и 8.2 |
| AI API эндпоинтов | 65 |
| Типов AI-процессов | 22 |
| Feature-флагов | 43 |
| Покрытие API фронтендом | 70.1% (521 из 743 эндпоинтов в UI) |
| Внешних PHP-зависимостей | 0 |
| Сторонних frontend-пакетов | 3 (Bootstrap 5, Font Awesome 6, SortableJS) |
| OpenAPI tooling | `api/scripts/generate_openapi.php` |
| Публичная maintainer-документация | `docs/maintainer/` |
| CLI-команд для модулей | 19 |
| Варианты развёртывания | локальный компьютер, домашний/офисный сервер, VPS, облачная VM, шаред-хостинг |
| Внешний хостинг для старта | ~200–300 ₽/мес, шаред-хостинг |

---

### Технологии

- **Бэкенд:** PHP 8.1+ с собственным микроядром — 0 внешних пакетов, без Laravel/Symfony/Doctrine.
- **База данных:** MySQL.
- **Фронтенд:** PHP-рендеринг MPA, Bootstrap 5 для UI/разметки и собственные модули на ванильном JavaScript ES5+ для поведения интерфейса — без React/Vue/Angular, без сборки, без бандлера.
- **Архитектура:** API-first — веб-интерфейс использует REST API для всех данных, без прямого доступа к БД.
- **Безопасность:** двойная аутентификация (cookie-сессия + CSRF для web, Bearer для API), RBAC с гранулярными правами, защита от SSRF, rate limiting, карантин файлов, имперсонация админа, маскирование sensitive-полей в ошибках.
- **Тестирование:** публичный PHP lint CI уже включён. Fast integration workflow с MySQL вынесен в публичный hardening backlog.
- **AI-слой:** настраиваемые провайдеры (OpenAI, Anthropic, DeepSeek, Google, совместимые), intent-based workflows, шаблоны промптов, JSON schema validation, модель preview-before-apply.
- **Документация:** публичные maintainer-документы находятся в `docs/maintainer/`. Инструмент генерации OpenAPI включён в `api/scripts/generate_openapi.php`.

---

### Структура

```text
TropaTT/
├── api/           # Ядро API — контроллеры, сервисы, репозитории, конфигурация, миграции, скрипты
├── web/           # Веб-интерфейс — установщик, 68 страниц, ~55 шаблонов, 24 JS-модуля, ресурсы
├── modules/       # Подключаемые бизнес-модули (boilerplate, hello-world, WIP-limit)
├── docs/maintainer/ # Публичные чеклисты сопровождения и OSS readiness notes
├── index.php      # Корневая точка входа
└── README.md      # Этот файл
```

Доменные модули организованы в 9 групп: Аутификация/Пользователи, CRM (клиенты, контрагенты, контакты), Проекты/Задачи, Планирование (календарь, повторяющиеся задачи, напоминания), Коммуникации (чаты, уведомления, push), Автоматизация (workflows, SLA, вебхуки), Аналитика (дашборды, отчёты), AI/LLM (11 модулей: провайдеры, intents, предложения, действия, задания, промпты, схемы, использование, retention, семантический поиск, context builders), Администрирование (настройки, логи, аудит, feature-флаги, модули, хранилище, корзина, поиск).

---

### Как устроено

**Ноль внешних PHP-зависимостей.** Роутер, DI-контейнер, автозагрузчик, query builder (без ORM), валидатор, HTTP-клиент, обработчик ответов, менеджер миграций и модульная система написаны вручную. Никаких Laravel, Symfony, Doctrine. Один `composer.json` с `php >=8.1`. Это устраняет риски supply-chain, конфликты версий и накладные расходы на аудит зависимостей.

**Архитектурные решения задокументированы:** ADR-001 (собственное микроядро), ADR-002 (единый JSON-envelope ответов), ADR-003 (без ORM — PDO + Repository), ADR-001 Web (собственный PHP MVC), ADR-003 Web (собственный ванильный JS, Bootstrap 5 для UI, без сборки), ADR-006 Web (серверная верификация сессии через cookie + CSRF).

**API-first дизайн.** Веб-интерфейс не имеет прямого доступа к базе данных. Каждая загрузка данных, отправка формы, изменение состояния идёт через `window.CRM.api.request` → `/api/v1/...`. API — авторитетный слой данных, веб-интерфейс — лишь один из потребителей.

**План тестирования.** В публичном репозитории сейчас есть быстрый PHP syntax CI workflow для PHP 8.1 и 8.2. Следующий hardening-шаг — интеграционный workflow с MySQL, он уже вынесен в публичный milestone. Security-sensitive области вроде RBAC, CSRF, доступа к файлам, AI data handling и OpenAPI consistency отдельно отмечены в maintainer-чеклистах.

---

### Документация

В публичном репозитории сейчас есть сфокусированный набор maintainer-документации:

| Слой | Расположение | Содержание |
|------|-------------|-----------|
| Maintainer | `docs/maintainer/` | Release checklist, security review checklist, Codex for OSS notes, starter issues, GitHub labels |
| API tooling | `api/scripts/generate_openapi.php` | Точка входа для автоматизации генерации OpenAPI || Корень проекта | `README.md`, `AGENTS.md`, `SECURITY.md`, `CONTRIBUTING.md`, [`INSTALL_TROUBLESHOOTING.md`](INSTALL_TROUBLESHOOTING.md), [`SHARED_HOSTING_GUIDE.md`](SHARED_HOSTING_GUIDE.md), [`WEBHOOK_SECURITY.md`](WEBHOOK_SECURITY.md) | Публичные правила использования, работы агентов, безопасности, вклада, troubleshooting установки, shared hosting гайд, webhook security |
---

### Файлы open-source проекта

В публичном репозитории есть стандартные файлы для пользователей, участников и сообщений о безопасности:

- [LICENSE](LICENSE) — лицензия AGPL-3.0.
- [CONTRIBUTING.md](CONTRIBUTING.md) — правила вклада, проверки, ожидания к PR и security checklist.
- [SECURITY.md](SECURITY.md) — приватная отправка уязвимостей и перечень security-sensitive областей.
- [CHANGELOG.md](CHANGELOG.md) — changelog публичного preview.
- [ROADMAP.md](ROADMAP.md) — roadmap без обещания конкретных дат.
- [SUPPORT.md](SUPPORT.md) — ожидания по поддержке и правила оформления issues.
- [INSTALL_TROUBLESHOOTING.md](INSTALL_TROUBLESHOOTING.md) — решение типовых проблем установки.
- [SHARED_HOSTING_GUIDE.md](SHARED_HOSTING_GUIDE.md) — пошаговая инструкция установки на shared hosting.
- [WEBHOOK_SECURITY.md](WEBHOOK_SECURITY.md) — аутентификация, верификация и best practices для вебхуков.
- [.github/ISSUE_TEMPLATE](.github/ISSUE_TEMPLATE) — шаблоны для багов, фич, документации и проблем установки.
- [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md) — чеклист PR по тестам, документации, безопасности, API и установщику.

Публичный репозиторий не включает локальные секреты, runtime-данные, приватные бизнес-данные, внутренние скриншоты и maintainer-only артефакты.

---

### Сопровождение проекта

TropaTT сопровождается как реальное приложение, а не как демонстрационный репозиторий. Обычное изменение может затрагивать сразу несколько слоёв: схему базы данных, миграции, API-маршруты, сервисы, репозитории, права доступа, страницы интерфейса, JavaScript-модули, тесты, сгенерированную документацию и поведение установщика.

Работа по сопровождению включает ревью изменений, проверку участков, связанных с безопасностью, разбор ошибок, контроль совместимости API, синхронизацию OpenAPI-документации с маршрутами, подготовку релизов и проверку того, что установщик, миграции, тесты и пользовательская документация не расходятся друг с другом.

Где особенно полезен вклад сообщества:

- тестирование установки в разных PHP/MySQL-окружениях;
- улучшение документации, примеров и переводов;
- ревью изменений API и совместимости OpenAPI;
- добавление тестов для CRM, задач, проектов, чата, автоматизации, календаря, аналитики и AI-процессов;
- проверка аутентификации, прав доступа, обработки файлов, вебхуков и настроек AI-провайдеров;
- проверка сценариев обновления и миграций между релизами;
- улучшение доступности и консистентности интерфейса на страницах с Bootstrap.

Проект сознательно сделан без большого дерева зависимостей, но из-за этого часть инфраструктуры находится прямо в кодовой базе. Такие изменения требуют такого же внимательного ревью, как и бизнес-функции.

---

### Области, где важна безопасность

TropaTT работает с бизнес-данными: клиентами, контрагентами, задачами, проектами, файлами, чатами, уведомлениями, API-ключами, вебхуками, правилами автоматизации, аудит-логами и настройками AI-провайдеров. Проверка безопасности важна, потому что многие функции работают с приватными данными рабочего пространства и могут влиять на права доступа, интеграции или сохранённые файлы.

Особого внимания требуют:

- аутентификация и обработка сессий;
- CSRF-защита в веб-интерфейсе;
- Bearer-доступ для REST API клиентов;
- гранулярные проверки RBAC и прав доступа;
- имперсонация администратора и аудит таких действий;
- загрузка файлов, хранение, карантин и права на скачивание;
- доставка вебхуков и внешние HTTP-запросы;
- workflow-автоматизация и фоновые задачи;
- lock-файлы установщика, конфигурация окружения и миграции базы данных;
- ключи AI-провайдеров, server-side AI-запросы, prompt context и режим preview-before-apply;
- ответы об ошибках, логирование, маскирование данных и обработка sensitive-полей.

Проблемы безопасности не стоит публиковать как обычные GitHub issues. Для этого лучше использовать security policy репозитория или связаться с сопровождающим приватно.

---

### Где помогает AI при сопровождении

У TropaTT много связанных частей: REST API, OpenAPI tooling, установщик, права доступа, workflow-автоматизация, AI-процессы, чат, календарь, аналитика и публичный CI. AI-помощь полезна там, где изменение нужно провести согласованно через несколько слоёв проекта.

Практичные сценарии AI-помощи при сопровождении:

- ревью pull request на проблемы с правами, валидацией и совместимостью API;
- генерация и обновление тестов при изменении маршрутов, сервисов, workflow или поведения интерфейса;
- проверка соответствия документации текущей реализации;
- краткое резюмирование больших diff перед подготовкой релиза;
- поиск пропущенной валидации, авторизации, обработки ошибок и пограничных случаев;
- подготовка changelog и release notes;
- помощь новым участникам в понимании структуры проекта перед вкладом;
- сверка OpenAPI-документации с backend-маршрутами и использованием API на фронтенде.

AI-предложения рассматриваются как материал для ревью, а не как автоматическое решение. Человеческая проверка обязательна для кода, безопасности, миграций базы данных, прав доступа, релизов и всего, что может повлиять на пользовательские данные.

---

### Кто сделал

TropaTT разрабатывает **Антон Баринов**, PHP-разработчик и создатель платформы TropaTT.

- **GitHub:** [Anton-Barinov](https://github.com/Anton-Barinov)
- **Репозиторий:** [github.com/Anton-Barinov/TropaTT](https://github.com/Anton-Barinov/TropaTT)

Проект создаётся как практичная самостоятельная система управления работой с PHP-first подходом, совместимостью с MySQL, прозрачной браузерной установкой, отсутствием искусственных ограничений, фокусом на приватность данных и расширяемой бизнес-логикой.

---

## 中文

### TropaTT 是什么

TropaTT 是一套免费、开源、可自行部署的 PHP/MySQL 工作平台，用于管理客户项目。它把 CRM、任务、项目、看板、甘特图、日历、团队聊天、自动化、REST API 和 20 多项 AI 辅助流程放在同一个系统中，由您部署在自己的服务器、VPS、本地环境或普通 PHP 主机上。

它面向每天真正交付工作的人：管理许多客户的自由职业者、小型代理机构、服务公司、现场团队、工作室，以及厌倦在客户系统、任务工具、聊天软件和电子表格之间来回复制信息的团队。

核心区别是控制权。TropaTT 不按用户收费，也不会用套餐限制用户、任务、项目或客户数量。数据、备份、集成和升级决策都在您手里。当然，这并不等于无限性能：实际容量取决于主机、数据库、配置、文件量和访问负载。小团队可以从基础 PHP/MySQL 主机开始，增长后再迁移到更强的基础设施。

---

### 为什么 TropaTT

问题在这里。客户在一个应用中。任务在另一个中。团队聊天在第三个中。日历在第四个中。还有一个电子表格做跟踪。它们之间互不相通。然后云服务商提高价格、限制您的席位或者发生宕机。

**TropaTT 用一套自行部署的系统取代这种混乱：**

- **CRM + 任务管理器 + 项目跟踪器一体化。** 客户卡片、任务层级、看板、甘特图和内置团队聊天共享同一份数据。无需在应用之间复制粘贴。没有丢失的上下文。
- **20 多项真正帮您工作的 AI 工具。** AI 想法分析将原始思路转化为结构化、有优先级的任务计划。AI 每日计划告诉您今天该关注什么。AI 生成任务摘要、分解、清单、风险评估和客户会议简报——无需离开工作空间。
- **内置团队聊天。** 在工作所在的同一个系统中讨论项目和任务。不需要 Slack、Discord 或单独的通讯订阅。
- **无人工 SaaS 限制。** 用户、任务、项目和客户数量不受套餐限制。真正的上限来自您的服务器、数据库、存储和配置。
- **全面的数据隐私。** 您的客户、任务、文件、聊天和业务数据保存在您的服务器上。没有云服务商可以访问。GDPR 和数据驻留合规由您直接控制——而非供应商的承诺。
- **可在任何支持 PHP 和 MySQL 的环境中运行。** 本地电脑、家庭或办公室服务器、VPS、云虚拟机或共享主机都可以。如果需要公开托管，每月约 $3–5 的 PHP/MySQL 方案即可开始使用。
- **零外部 PHP 依赖。** 不使用 Laravel、Symfony、Doctrine 或 Composer 依赖树。整个微内核均为手写。您部署一个代码库，而非一百个软件包。
- **浏览器安装。** 上传文件，在浏览器中打开安装程序，输入 MySQL 凭据，创建管理员账户。无需终端、命令行或 DevOps 专业知识。

---

### 适合谁

TropaTT 适用于任何管理客户并执行工作的人——无论团队规模、行业或角色：

**按团队规模：**
- **自由职业者和独立创业者** 管理 5-50 个客户，跟踪任务，使用 AI 规划每日工作，将全部项目上下文集中在一个地方——无需按人头付费。
- **小型团队（2-15 人）** 需要 CRM、任务、聊天和项目可见性，无需管理多个工具订阅。
- **成长型公司（15-100+ 人）** 需要角色、权限、工作流自动化、SLA、审批、Webhook 和 API 集成——无需企业级 SaaS 定价。

**按行业：**
- 管理客户营销活动、截止日期和交付物的营销和广告代理机构。
- 跟踪项目、缺陷和部署的软件开发和 IT 服务团队。
- 管理修订、资产和客户审批的设计工作室和创意代理机构。
- 跨站点协调任务的安装、施工和现场服务公司。
- 管理客户委托和文件的咨询、法律和专业服务公司。
- 有合作方、合同和重复性工作的 B2B 服务公司。
- 任何同时需要 CRM 和任务管理——而非其中之一的团队。

**按角色：**
- 需要了解客户、项目和团队全局状况的创始人和管理者。
- 使用甘特图规划时间线、跟踪里程碑和管理工作负载的项目经理。
- 分配任务、审查执行情况并通过内置聊天进行协调的团队负责人。
- 需要清晰任务列表、每日计划和专注工作空间的个人贡献者。

---

### 功能

**CRM 与客户管理。** 客户记录、合作方、公司、联系人、组织、部门、团队。适应您行业的自定义字段。包含完整项目、任务和沟通历史的客户详情页面。面向有客户、承包商、合作伙伴和供应商的 B2B 关系的合作方管理。

**任务管理与执行。** 完整任务层级：父任务、子任务、清单。状态、优先级、截止日期、负责人、标签。带文件附件和 @提及的丰富任务评论。用于重复性工作的任务模板。任务间依赖关系。防止过载的在制品限制。批量操作。

**项目管理。** 包含里程碑、风险、工作负载上下文和时间线视图的项目页面。用于流程化执行的看板。用于进度可见性和依赖规划的甘特图。用于可重复交付模式的项目模板。

**个人规划。** 我的日和我的周视图——专为个人优先级排序和团队协调而设计。基于实际任务、截止日期和日历的 AI 生成每日和每周计划。

**日历。** 与任务和项目关联的事件。议程和日程视图。可配置的工作日、假期和用于 SLA 截止日期计算的工作时间。

**内置团队聊天。** 完整的 CRM 集成通讯工具——项目聊天、直接聊天、群组聊天。消息、附件、图片、@提及、回复、URL 路由和实时轮询。无需 Slack、Discord 或 Telegram。

**通知。** 任务分配、评论、提及、截止日期变更、审批和系统事件的实时提醒。通过浏览器 API 的推送通知。带历史记录的通知中心。

**分析与仪表盘。** 业务概览仪表盘。KPI 跟踪、工作负载分析、风险信号、团队容量报告。所有数据来自实际的项目和任务执行——而非手动录入。

**管理。** 用户管理、角色、细粒度权限、状态、优先级、SLA 策略、工作流规则、Webhook、API 客户端、模块、审计日志、功能标志、速率限制和 AI 提供商配置——全部通过管理面板操作。

**想法与 AI 分析。** 捕获原始想法、客户需求或业务问题。让 AI 分析可行性、风险和复杂度。AI 提出结构化任务层级。审查并以一键转换为真实任务。将"我们可能应该处理 X"在几分钟内转化为可操作的计划。

---

### 功能一览

| 领域 | TropaTT 提供什么 | 为什么重要 |
|---|---|---|
| **CRM** | 客户、合作方、公司、联系人、组织、部门 | 所有业务关系和上下文集中在一个地方 |
| **任务管理器** | 层级、子任务、清单、在制品限制、依赖、模板 | 替代单独的任务跟踪器订阅 |
| **项目** | 里程碑、风险、甘特图、工作负载上下文、模板 | 带有时间线和责任跟踪的交付控制 |
| **看板** | 支持拖拽的流程化任务移动看板 | 可视化状态管理，减少瓶颈 |
| **甘特图** | 支持依赖关系的时间线规划 | 截止日期可见性和资源规划 |
| **日历** | 事件、议程、日/周视图、业务日历 | 与任务和项目上下文关联的计划工作 |
| **团队聊天** | 内置通讯工具，支持项目频道、私信、附件、提及 | 与工作在同一个工具中进行沟通 |
| **通知** | 实时提醒、推送通知、通知中心 | 不错过任何截止日期、提及或审批 |
| **分析** | 仪表盘、KPI、工作负载、风险、团队容量 | 基于真实执行数据的数据驱动决策 |
| **自动化** | 工作流规则、SLA、审批、Webhook、后台任务 | 减少手动协调和人为错误 |
| **AI（20+ 工具）** | 想法分析、日/周计划、任务分解、摘要、清单、风险分析、会议准备 | 帮您更聪明地工作，而非仅仅是一个聊天机器人 |
| **管理** | 用户、角色、权限、功能标志、模块、日志、设置 | 完全掌控您的工作空间 |
| **数据隐私** | 100% 数据存储在您的服务器上，无云访问 | 零供应商锁定，GDPR 由您掌控 |
| **无套餐限制** | 用户、任务、项目、客户和文件不受 SaaS 套餐限制 | 随基础设施扩展，而非随订阅套餐 |
| **安装** | 适用于任何 PHP/MySQL 主机的浏览器安装向导 | 几分钟内完成首次启动，无需终端 |
| **零依赖** | 无外部 PHP 软件包，自定义微内核 | 无供应链风险，只需部署一个代码库 |

---

### AI — 能做什么

TropaTT 拥有**比许多 SaaS CRM 产品更深入的 AI 能力**——不是因为 AI 被当作聊天机器人附加进来，而是因为它被集成到真正节省时间的工作流中。

您连接自己的 AI 提供商（OpenAI、Anthropic、DeepSeek、Google 或任何兼容的 OpenAI 格式 API）。所有 AI 处理在服务器端进行。除非您明确配置提供商，否则您的业务数据永远不会离开您的基础设施。AI 建议始终是预览后再应用——未经您的审查，不会有任何自动更改。

**突出功能——AI 想法分析：**
1. 写几句话描述一个想法、客户需求或业务问题。
2. AI 评估范围、可行性、风险、复杂度和影响。
3. AI 提出包含父任务、子任务、优先级和理由的结构化任务层级。
4. 您审查提案，一键将其转化为真实任务。

*示例：* "客户希望集成预订系统和网站。" → AI 建议：调研现有 API（2 个子任务）、设计集成架构、实现预订端点、构建前端界面、集成测试、部署清单。原本需要 30 分钟的手动规划变成了 30 秒的审查和确认。

**完整的 AI 工作流列表：**
AI 想法分析 · 任务分解 · 每日工作计划 · 每周工作计划 · 任务摘要 · 下一步行动建议 · 清单生成 · 任务质量审查 · 评论草稿生成 · 任务优先级排序 · 项目摘要 · 项目风险摘要 · 项目客户报告 · 客户摘要 · 客户会议准备 · 客户数据质量审查 · 分析 KPI 解释 · 分析风险解释 · 团队工作负载摘要 · 日历事件议程 · 仪表盘每日摘要 · 语义搜索

**AI 安全模型：**
- 默认仅预览——不对业务数据执行自动写入。
- 所有 AI 请求通过后端 API——密钥永远不会暴露给浏览器。
- 每个 AI 能力的基于角色的访问控制。
- 43 个功能标志用于细粒度的 AI 逐步部署。
- 每个意图的速率和成本限制。
- 默认不存储原始提示词和敏感上下文。

---

### 团队聊天

TropaTT 在工作空间内包含一个功能齐全的团队通讯工具——不是集成、不是嵌入、不是单独的订阅。聊天与任务、项目和客户内置于同一系统中：

- **双面板布局：** 左侧会话列表，右侧消息线程。
- **项目和团队频道：** 每个项目可以有自己的聊天。全公司范围的团队频道。
- **私信：** 一对一和群组对话。
- **丰富的消息：** 文本、文件附件、图片、带用户搜索的 @提及、回复链。
- **URL 路由对话：** 每个聊天都有自己的 URL——直接链接到讨论。
- **会话记忆：** 系统在页面重新加载时恢复您上次活跃的聊天。
- **实时轮询：** 新消息自动出现，不会丢失您的草稿。
- **聊天创建模态框：** 按姓名搜索用户，即时创建对话。
- **参与者控制：** 聊天所有者管理成员、添加和移除。
- **编辑器体验：** Enter 发送，Shift+Enter 创建新行。

这消除了"我们在哪里讨论了这个？"的问题。任务上下文、项目讨论和团队沟通共享同一个空间。

---

### 使用方式

TropaTT 支持任何规模团队的端到端客户工作循环：

1. **捕获**——一个想法、客户需求、运营问题或新线索。
2. **分析**——手动或使用 AI：范围、风险、可行性、建议方法。
3. **结构化**——转化为项目、任务、子任务、清单、负责人、截止日期。
4. **协调**——通过内置聊天、任务评论、提及、通知进行讨论。
5. **执行**——通过任务列表、看板、我的日和我的周视图进行工作。
6. **跟踪**——通过甘特图、仪表盘、分析、工作负载视图和风险信号进行监控。
7. **自动化**——通过工作流规则、SLA 策略、Webhook 和审批链处理重复流程。

**真实工作流示例：**
- **自由职业者：** 客户咨询 → AI 想法分析 → 结构化任务计划 → 我的日 → 执行 → 通过聊天与客户沟通 → 完成。
- **代理机构：** 客户简报 → AI 分解 → 带里程碑的项目 → 团队看板 → 甘特图跟踪 → 客户报告 → 分析审查。
- **服务公司：** 合作方设置 → 项目 → 安装任务 → 甘特图 → 每日计划 → SLA 监控 → 签收。
- **B2B 运营：** 公司 → 联系人 → 带审批的任务 → 提醒 → Webhook → CRM 仪表盘。

---

### 自动化与 API

TropaTT 的自动化层和 REST API 是生产级的——为需要系统与业务技术栈其余部分配合工作的团队而设计：

- **工作流规则**——条件满足时触发操作（状态变更、字段更新、基于时间）。
- **SLA 管理**——定义服务级别期望，包含截止日期跟踪和违约提醒。
- **审批流**——用于受控业务变更的多步骤决策链。
- **Webhook**——CRM 记录变更时向外部系统发送事件。
- **API 客户端和密钥**——具有限定权限的编程访问。
- **后台任务**——用于导入、导出、AI 工作流的计划和队列处理。
- **模块系统**——在不修改核心的情况下扩展业务逻辑。19 个模块管理 CLI 命令。
- **743 个文档化的 REST API 端点**——每个 CRM 实体、任务、项目、聊天、日历、分析和管理员功能均可通过 API 访问。
- **OpenAPI 3.1 规范**——从实际路由配置生成，与实现保持同步。

---

### 自托管，你的规则

TropaTT 以开源软件形式发布。您可以在自己的服务器上免费部署。您可以检查、修改和扩展代码。

**开源在此的含义：**
- 任何用途均免费——无许可费用，无强制订阅。
- 完整源代码可用——定制、审计、贡献。
- 可选付费服务（定制、集成、迁移、支持）是服务，而非使用要求。

**为什么自托管很重要：**
- **无供应商锁定。** 如果 SaaS CRM 提价或关闭，您的数据将被困住。使用 TropaTT，您的数据在您的服务器上——随时迁移、备份或移动。
- **无人工限制。** 无限用户、无限任务、无限项目、无限客户。唯一的上限是您服务器的硬件。
- **无封锁或限流。** 您的访问不会因账单问题、政策变更或自动化供应商标记而被暂停。系统属于您。
- **隐私设计。** 客户数据、任务、文件和通信保留在您的基础设施上。GDPR 和数据驻留合规由您直接控制。
- **成本控制。** 您可以在本地运行 TropaTT 而无需外部主机费用，也可以部署到自己的服务器，或从每月 $3–5 的 PHP/MySQL 共享主机开始。扩展时升级基础设施，而不是升级 SaaS 套餐。

---

### 安装

TropaTT 包含一个适用于简单 PHP/MySQL 部署的浏览器安装程序：本地机器、自有服务器、VPS 或共享主机。无需终端、命令行、composer 或 npm。

**要求：**
- PHP 8.1 或更高版本
- MySQL 数据库（可用的空数据库）
- Web 服务器（Apache、Nginx 或任何 PHP 兼容主机）
- `api/` 配置目录和 `storage/` 目录的写权限

**快速开始：**
1. 将项目文件放到服务器或本地 Web 服务器目录中。
2. 创建一个空的 MySQL 数据库。
3. 在浏览器中打开您的域名——TropaTT 自动检测到尚未配置并启动安装程序。
4. 安装程序检查环境，询问 MySQL 主机/端口/数据库/用户名/密码、站点 URL 和时区，以及第一个管理员账户凭据。
5. 安装程序写入 `api/.env`，创建 MySQL 架构，填充参考数据（状态、优先级、角色、权限），创建管理员用户，准备存储，并设置安装锁定文件。
6. 登录并开始工作。

**共享主机场景：**
上传 `api/`、`web/`、`modules/`、`index.php`、`favicon.ico` 和 `README.md` → 在主机面板中创建 MySQL 数据库 → 打开您的域名 → 按照安装程序操作 → 完成。

---

### 常见问题

**TropaTT 是什么？**
一套免费、开源、可自行部署的 CRM、任务管理器和业务运营平台，基于 PHP 8.1+ 和 MySQL 构建。它整合了客户管理、任务管理、项目管理、看板、甘特图、日历、内置团队聊天、通知、分析、自动化和 20 多项 AI 工作流——全部在您自己的服务器上。

**TropaTT 是 CRM 还是任务管理器？**
两者兼而有之。它是管理客户、合作方、联系人和公司的 CRM——也是用于执行、规划、看板、甘特图、清单和日常规划的完整任务管理器。您不需要单独的订阅。

**自由职业者可以使用吗？**
可以。TropaTT 没有最低团队规模要求。自由职业者管理客户、跟踪任务、使用 AI 规划每日工作、分析想法，并将所有项目上下文集中在一个地方——无需按人头付费。

**它能在共享主机上运行吗？**
可以。标准的 PHP 8.1+ / MySQL 共享主机方案（$3–5/月）就足够了。TropaTT 也可以部署在本地电脑、家庭/办公室服务器或 VPS 上。

**AI 功能如何？**
20 多项 AI 工作流：想法分析、每日/每周计划、任务分解、摘要、清单、风险评估、会议准备等。您连接自己的 AI 提供商。所有处理在服务器端进行。AI 未经您的审查不会修改数据。

**AI 想法分析如何工作？**
写几句话描述一个想法 → AI 评估范围、风险和复杂度 → AI 提出结构化任务层级 → 您审查并以一键转化为真实任务。

**我的数据存储在哪里？**
100% 存储在自己的服务器上。TropaTT 永远不会将数据同步到任何云端。任何第三方（包括开发者）都无法访问您的安装或数据。

**有用户限制吗？任务限制？**
没有。TropaTT 没有任何人工限制。您的服务器能处理多少用户、任务、项目和客户就可以创建多少。

**它是开源的吗？**
是的，以开源项目形式发布。免费使用、修改和部署。

**它有 API 访问吗？**
有，743 个文档化的 REST API 端点，带有从代码生成的 OpenAPI 3.1 规范。每个功能均可编程访问。

**可以定制吗？**
可以。PHP/MySQL 技术栈、模块化扩展、REST API、Webhook、工作流规则、自定义字段、角色、权限——全部可适应您的流程。

**谁开发了 TropaTT？**
由 **Anton Barinov** 开发，PHP 开发者，TropaTT 项目的创建者。

---

### 数字说话

| 指标 | 数值 |
|------|------|
| API 端点 | 743 个标准化，695 条路由记录 |
| Web 应用路由 | 68 个页面，~55 个模板 |
| 后端 PHP 服务 | 100+ |
| 数据库仓库 | 65+ |
| 后端域模块 | 35+ |
| JavaScript 模块 | 24 个自定义原生 JS 模块，无 SPA 框架，无构建步骤 |
| 公开 CI | PHP 8.1 和 8.2 lint |
| AI API 端点 | 65 |
| AI 工作流类型 | 22 |
| 功能标志 | 43 |
| 前端 API 覆盖率 | 70.1%（743 个端点中的 521 个已实现 UI） |
| 外部 PHP 依赖 | 0 |
| 前端第三方包 | 3（Bootstrap 5、Font Awesome 6、SortableJS） |
| OpenAPI 工具 | `api/scripts/generate_openapi.php` |
| 公开维护者文档 | `docs/maintainer/` |
| 模块 CLI 命令 | 19 |
| 部署选项 | 本地电脑、家庭/办公室服务器、VPS、云虚拟机、共享主机 |
| 外部主机起点 | ~$3/月，共享主机 |

---

### 技术栈

- **后端：** PHP 8.1+ 配合自定义微内核——零外部软件包，不使用 Laravel/Symfony/Doctrine。
- **数据库：** MySQL。
- **前端：** PHP 渲染的 MPA，使用 Bootstrap 5 处理 UI/布局，并用自定义原生 JavaScript ES5+ 模块实现交互——不使用 React/Vue/Angular，无构建步骤，无打包工具。
- **架构：** API 优先——Web UI 通过 REST API 获取所有数据，Web 层无直接数据库访问。
- **安全性：** 双重认证（Web 的 Cookie 会话 + CSRF，API 的 Bearer），带细粒度权限的 RBAC，速率限制，文件隔离，管理员模拟，已脱敏的错误响应。
- **测试：** 已启用公开 PHP lint CI。带 MySQL service 的快速集成测试 workflow 已进入公开 hardening backlog。
- **AI 层：** 可配置的提供商（OpenAI、Anthropic、DeepSeek、Google、兼容提供商），基于意图的工作流，提示词模板，JSON Schema 验证，预览后应用的安全模型。
- **文档：** 公开维护者文档位于 `docs/maintainer/`。OpenAPI 生成工具位于 `api/scripts/generate_openapi.php`。

---

### 结构

```text
TropaTT/
├── api/           # API 核心——控制器、服务、仓库、配置、迁移、脚本
├── web/           # Web 界面——安装程序、68 个页面、~55 个模板、24 个 JS 模块、资源
├── modules/       # 可选业务模块（boilerplate、hello-world、WIP-limit 示例）
├── docs/maintainer/ # 公开维护者清单和 OSS readiness notes
├── index.php      # 根入口点
└── README.md      # 本文件
```

后端域模块分为 9 组：认证/用户，CRM（客户、合作方、联系人），项目/任务，规划（日历、重复任务、提醒），沟通（聊天、通知、推送），自动化（工作流、SLA、Webhook），分析（仪表盘、报告），AI/LLM（11 个模块：提供商、意图、建议、动作、任务、提示词、模式、使用量、保留、语义搜索、上下文构建器），管理（设置、日志、审计、功能标志、模块、存储、回收站、搜索）。

---

### 内部原理

**零外部 PHP 依赖。** 路由器、DI 容器、自动加载器、查询构建器（无 ORM）、验证器、HTTP 客户端、响应处理器、迁移管理器和模块系统均为手写。不使用 Laravel。不使用 Symfony。不使用 Doctrine。单个 `composer.json` 仅含 `php >=8.1`。这消除了供应链风险、版本冲突和依赖审计开销。

**架构决策记录。** 关键工程决策已文档化：ADR-001（自定义微内核），ADR-002（单一 JSON 响应信封），ADR-003（无 ORM——PDO + 仓库模式），ADR-001 Web（自定义 PHP MVC），ADR-003 Web（自定义原生 JS，Bootstrap 5 UI，无构建步骤），ADR-006 Web（通过 Cookie + CSRF 进行服务器端会话验证）。

**API 优先设计。** Web UI 零直接数据库访问。每次数据加载、每次表单提交、每次状态变更都通过 `window.CRM.api.request` → `/api/v1/...` 完成。API 是权威数据层——Web UI 只是其中一个消费者。

**测试路线图。** 公开仓库当前包含适用于 PHP 8.1 和 8.2 的快速 PHP syntax CI workflow。下一步 hardening 工作是加入带 MySQL 的集成测试 workflow，并已在公开 milestone 中跟踪。RBAC、CSRF、文件访问、AI data handling 和 OpenAPI consistency 等安全敏感领域已在维护者清单中列出。

---

### 文档

公开仓库当前包含一组聚焦的维护者文档：

| 层级 | 位置 | 内容 |
|------|------|------|
| 维护者文档 | `docs/maintainer/` | Release checklist、security review checklist、Codex for OSS notes、starter issues、GitHub labels |
| API 工具 | `api/scripts/generate_openapi.php` | API 文档自动化的 OpenAPI 生成入口 |
| 项目根目录 | `README.md`, `AGENTS.md`, `SECURITY.md`, `CONTRIBUTING.md` | 公开使用、代理、安全和贡献指南 |

---

### 开源项目文件

公共仓库包含面向用户、贡献者和安全报告的标准文件：

- [LICENSE](LICENSE) — AGPL-3.0 许可证。
- [CONTRIBUTING.md](CONTRIBUTING.md) — 贡献流程、检查项、PR 期望和安全清单。
- [SECURITY.md](SECURITY.md) — 私密漏洞报告方式和安全敏感区域。
- [CHANGELOG.md](CHANGELOG.md) — public preview 更新记录。
- [ROADMAP.md](ROADMAP.md) — 不承诺具体日期的路线图。
- [SUPPORT.md](SUPPORT.md) — 支持范围和 issue 提交建议。
- [.github/ISSUE_TEMPLATE](.github/ISSUE_TEMPLATE) — bug、功能、文档和安装问题模板。
- [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md) — 测试、文档、安全、API 和安装影响的 PR 清单。

公共仓库不会包含本地密钥、运行时数据、私有业务数据、内部截图和仅维护者使用的工作文件。

---

### 维护和贡献流程

TropaTT 不是一个演示仓库，而是作为真实应用进行维护。一个普通改动可能同时涉及多个层：数据库结构、迁移、API 路由、服务、仓储、权限、界面页面、JavaScript 模块、测试、生成的文档和安装程序行为。

项目维护工作包括代码审查、安全敏感路径检查、问题分类、API 兼容性验证、保持 OpenAPI 文档与路由同步、准备发布，并确保安装程序、迁移、测试和面向用户的文档不会相互偏离。

特别适合社区贡献的方向：

- 在不同 PHP/MySQL 环境中测试安装流程；
- 改进文档、示例和翻译；
- 审查 API 变更和 OpenAPI 兼容性；
- 为 CRM、任务、项目、聊天、自动化、日历、分析和 AI 工作流添加测试；
- 检查身份认证、权限、文件处理、Webhook 和 AI 提供商配置；
- 验证版本之间的升级和迁移路径；
- 改进基于 Bootstrap 页面的一致性和可访问性。

项目有意避免庞大的依赖树，但这也意味着部分基础设施就在代码库中。对这些代码的贡献需要像业务功能一样认真审查。

---

### 安全敏感区域

TropaTT 处理业务数据：客户、合作方、任务、项目、文件、聊天、通知、API 密钥、Webhook、自动化规则、审计日志以及可选的 AI 提供商配置。安全审查很重要，因为许多功能都在处理私有工作空间数据，并可能影响权限、集成或存储文件。

需要重点关注的安全区域包括：

- 身份认证和会话处理；
- Web 界面的 CSRF 防护；
- REST API 客户端的 Bearer token 访问；
- 细粒度 RBAC 和权限检查；
- 管理员模拟登录及其审计；
- 文件上传、存储、隔离和下载权限；
- Webhook 发送和外部 HTTP 请求；
- 工作流自动化规则和后台任务；
- 安装程序锁文件、环境配置和数据库迁移；
- AI 提供商密钥、服务端 AI 请求、提示词上下文和预览后应用机制；
- 错误响应、日志记录、数据脱敏和敏感字段处理。

请不要把安全问题作为公开 GitHub issue 提交。请使用仓库的安全策略，或通过私密方式联系维护者。

---

### AI 辅助维护

TropaTT 包含许多相互关联的部分：REST API、OpenAPI tooling、安装流程、权限、工作流自动化、AI 流程、聊天、日历、分析和公开 CI。当一个改动需要在多个层之间保持一致时，AI 辅助维护很有价值。

实用的 AI 辅助维护场景包括：

- 审查 pull request 中的权限、验证和 API 兼容性问题；
- 当路由、服务、工作流或 UI 行为变化时生成或更新测试；
- 检查文档是否符合当前实现；
- 在准备发布前总结较大的 diff；
- 查找缺失的验证、授权、错误处理和边界情况；
- 准备 changelog 和 release notes；
- 帮助贡献者在提交改动前理解项目结构；
- 对比 OpenAPI 文档、后端路由定义和前端 API 使用情况。

AI 建议只作为审查输入，而不是自动权威。代码、安全敏感更改、数据库迁移、权限、发布决策以及任何可能影响用户数据的内容，都必须经过人工审查。

---

### 谁做的

TropaTT 由 **Anton Barinov** 开发，PHP 开发者，TropaTT 平台的创建者。

- **GitHub：** [Anton-Barinov](https://github.com/Anton-Barinov)
- **仓库：** [github.com/Anton-Barinov/TropaTT](https://github.com/Anton-Barinov/TropaTT)

该项目构建为一个实用、可自行部署的工作管理系统，采用 PHP 优先的方法，兼容 MySQL，透明的浏览器安装，零人工限制，并专注于数据隐私和可扩展的业务逻辑。
