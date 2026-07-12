# Security Audit — Fixes Specification

> Дата аудита: 2026-07-12
> Версия проекта: 1.0.0
> Аудитор: AI Security Audit (Read-Only)

## Сводка

| Severity | Количество |
|----------|------------|
| Critical | 0 |
| High     | 0 |
| Medium   | 1 |
| Low      | 2 |
| **Итого** | **3** |

## Risk Heatmap

| Категория | Critical | High | Medium | Low |
|-----------|----------|------|--------|-----|
| injection |          |      | 1      |     |
| infra     |          |      |        | 2   |

---

## [SEC-028] allInput() массовое присвоение полей в OrganizationController

### Мета

- **Severity**: Medium
- **Категория**: injection (mass assignment)
- **Затронутые файлы**: `api/controller/organization/OrganizationController.php:33`, `api/controller/webhook/WebhookController.php:40`, `api/controller/reaction/ReactionController.php:20`
- **Endpoint/tool**: `POST /api/v1/organizations`, `PATCH /api/v1/organizations/{public_id}`, `POST /api/v1/webhooks`, `POST /api/v1/reactions`
- **Затронутые роли**: authenticated (any with organization.manage / webhook.manage)

### Описание проблемы

Несколько контроллеров используют `$this->request()->allInput()` для получения всех полей запроса без явного allowlist. Далее эти данные передаются в сервисные методы, которые могут записывать их напрямую в БД через QueryBuilder.

В `OrganizationController::create()`:
```php
$input = $this->request()->allInput();
$v = new Validator();
$v->require($input, 'title', ...)->maxLen($input, 'title', 255, ...);
// ... передаёт $input в сервис, который может записать все поля
```

Хотя `allInput()` — это удобно, оно передаёт в сервис **все** поля запроса, включая потенциально опасные (`id`, `created_at`, `is_root`, `permissions`, `role`). Если сервис не фильтрует поля перед записью, атакующий может перезаписать защищённые поля (mass assignment).

### Воспроизведение

1. Получить токен admin
2. Выполнить `POST /api/v1/organizations` с дополнительными полями: `{"title":"Test","is_deleted":1,"created_at":"2020-01-01"}`
3. Проверить, были ли нежелательные поля записаны в БД

### Влияние

- Потенциальная перезапись защищённых полей (is_deleted, created_at, id)
- Нарушение целостности данных
- Возможное повреждение ссылочной целостности

### Рекомендация по исправлению

**Что нужно сделать**: Внедрить allowlist-фильтрацию полей перед передачей в сервис.

**Как лучше реализовать**:
- Создать helper-функцию `Input::only(array $input, array $allowedFields): array`
- В `OrganizationController::create()` и `update()` заменить `allInput()` на `Input::only($this->request()->allInput(), ['title', 'slug', 'description', 'status'])`
- Аналогично для `WebhookController` и `ReactionController`
- В сервисных слоях (OrganizationService, WebhookService, ReactionService) добавить дополнительную проверку: явно указывать, какие поля можно обновлять

**Приоритет**: Should-Fix

---

## [SEC-029] storage_api/secrets/ redirect раскрывает существование директории

### Мета

- **Severity**: Low
- **Категория**: infra
- **Затронутые файлы**: `storage_api/.htaccess`
- **Endpoint/tool**: `GET /storage_api/secrets/`
- **Затронутые роли**: unauthenticated

### Описание проблемы

При запросе `https://demo.tropatt.com/storage_api/secrets/` возвращается 302 Redirect, а не 403 Forbidden или 404 Not Found. Это указывает на то, что:
- nginx не применяет .htaccess к этой директории
- nginx перенаправляет запрос (вероятно, добавляя trailing slash), что подтверждает существование директории

Хотя `storage_api/.htaccess` содержит `Require all denied`, на nginx этот файл не применяется. На Apache shared hosting проблема отсутствует (htaccess работает корректно).

### Воспроизведение

1. Выполнить `curl -I https://demo.tropatt.com/storage_api/secrets/`
2. Ответ: `HTTP/2 302`

### Влияние

- Минимальное: раскрытие существования директории `secrets/`
- На nginx без дополнительных правил директория может быть доступна по URL

### Рекомендация по исправлению

**Что нужно сделать**: Добавить defence-in-depth через PHP-уровень или переместить `secrets/` за пределы document root.

**Как лучше реализовать**:
- В `api/index.php` добавить паттерн `#/storage_api/secrets/#` в `$blockedPatterns`
- Альтернативно: переместить директорию `storage_api/secrets/` в родительскую директорию за пределами document root
- На Apache shared hosting .htaccess уже работает корректно

**Приоритет**: Nice-to-Have

---

## [SEC-030] composer.json.dist доступен на nginx

### Мета

- **Severity**: Low
- **Категория**: infra
- **Затронутые файлы**: `api/composer.json.dist`, `api/index.php`
- **Endpoint/tool**: `GET /api/composer.json.dist`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Файл `api/composer.json.dist` доступен по URL (HTTP 200) на nginx сервере. `.htaccess` блокирует его на Apache, но на nginx файлы с неизвестными расширениями (.dist) отдаются как статика без маршрутизации через PHP.

Файл содержит метаданные проекта (название, описание, авторы) и не содержит секретной информации.

### Воспроизведение

1. Выполнить `curl -I https://demo.tropatt.com/api/composer.json.dist`
2. Ответ: `HTTP/2 200`

### Влияние

- Минимальное: раскрытие метаданных проекта
- Не содержит секретов или путей к файлам

### Рекомендация по исправлению

**Что нужно сделать**: Переместить файл за пределы document root или переименовать.

**Как лучше реализовать**:
- Переместить `api/composer.json.dist` в корневую директорию проекта (за пределами `api/`)
- Или переименовать в `api/composer.json.dist.example` — всё равно будет доступен, но .htaccess блокирует `.example`
- Или добавить PHP-скрипт, который проверяет доступ и отдаёт 404
- На Apache shared hosting проблема уже решена через .htaccess

**Приоритет**: Nice-to-Have

---

## Общие рекомендации по архитектуре безопасности

1. **Allowlist для полей ввода**: Использовать `$this->request()->allInput()` — это удобно, но опасно. Создать helper `Input::only()` или `Input::except()` для явного указания разрешённых полей.

2. **Defence-in-depth на nginx**: Несколько проверок показали, что .htaccess не работает на nginx. Для shared hosting это ожидаемо (Apache), но на VPS с nginx нужно дополнительно настраивать сервер. Добавить в `api/index.php` больше паттернов в `$blockedPatterns`.

3. **Продолжать мониторинг MCP**: 501 RBAC-проверка и 20 batch limit — отличные показатели. Продолжать добавлять prompt injection detection.

## Статус проверки (Positive Summary)

| Область | Статус |
|---------|--------|
| **CSP на web-страницах** | ✅ Присутствует (CSP, X-Frame-Options, X-Content-Type-Options) |
| **HSTS** | ✅ `max-age=31536000` |
| **Rate limiting** | ✅ 429 после 4 попыток (auth_login), работают per-IP и per-user |
| **Password hashing** | ✅ PASSWORD_ARGON2ID |
| **Token generation** | ✅ `random_bytes(32)` через TokenManager |
| **Encryption at rest** | ✅ AES-256-GCM (webhooks), AES-256-GCM (AI keys) |
| **SQL parameterization** | ✅ `whereRaw()` с `?` placeholders |
| **ORDER BY whitelist** | ✅ IdeaController использует `match()` (whitelist) |
| **XXE** | ✅ Нет SimpleXML/DOMDocument с user input |
| **Command injection** | ✅ Нет exec/system/shell_exec в API |
| **File upload quarantine** | ✅ Quarantine расширений + MIME prefix check |
| **MCP RBAC** | ✅ 501+ permission checks |
| **MCP batch limit** | ✅ 20 max |
| **MCP prompt injection** | ✅ Tool descriptions с WARNING |
| **CSRF** | ✅ X-CSRF-Token + HMAC + SameSite Strict |
| **Session config** | ✅ same_site=Strict, HttpOnly |
| **Error handling** | ✅ JSON без stack traces |
| **CORS** | ✅ Allowlist origins |
| **`.env` по URL** | ✅ 404 |
| **storage/ по URL** | ✅ 404 |
| **storage_api/ по URL** | ✅ 403 |
| **Installer** | ✅ 410 (заблокирован после установки) |
| **Webhook URL validation** | ✅ HTTPS only, private networks blocked |
| **No open redirects** | ✅ |
| **Login template (XSS)** | ✅ `htmlspecialchars` на всех выводах |
| **Audit logging** | ✅ mask_keys (password, token, secret, api_key) |
| **Module sandbox** | ✅ canRead/canWrite/canExecute |

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P1 | SEC-028 | allInput() mass assignment в OrganizationController | 1 час |
| P2 | SEC-029 | storage_api/secrets redirect на nginx | 15 мин |
| P2 | SEC-030 | composer.json.dist на nginx | 15 мин |

## Методология аудита

**Тип аудита**: Read-only defensive security code review (Phase 0-10).

**Методы проверки**:
- Статический анализ кода (grep по ключевым словам и паттернам)
- Динамическая проверка на демо-сервере (curl, HTTP-заголовки, ответы API)
- Rate limiting тестирование (6 последовательных запросов)
- MCP RBAC audit (501 permission checks verified)
- Проверка .htaccess в директориях хранения

**Проверенные области**:
✅ Phase 0: Attack surface mapping (9 юнитов)
✅ Phase 1: Authentication & session management
✅ Phase 2: RBAC & authorization
✅ Phase 3: Input validation & injections (SQL, XSS, SSRF, XXE, Command injection)
✅ Phase 4: MCP-specific risks
✅ Phase 5: Secrets & configuration
✅ Phase 6: Business logic
✅ Phase 6.1: Module system
✅ Phase 6.2: AI functionality
✅ Phase 6.3: Notifications
✅ Phase 7: Dependencies & infrastructure
✅ Phase 7.1: Cryptography & TLS
✅ Phase 7.2: Rate limiting & DoS
✅ Phase 7.3: Installer security
✅ Phase 7.4: Updates
✅ Phase 7.5: Shared hosting
✅ Phase 8: File storage
✅ Phase 9: Web UI
✅ Phase 10: Audit logging
