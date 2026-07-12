# Security Audit — Fixes Specification

> Дата аудита: 2026-07-12
> Версия проекта: 1.0.0 (core_build 20260624.003)
> Аудитор: AI Security Audit (Phase 0–10)

## Сводка

| Severity | Количество |
|----------|------------|
| Critical | 0 |
| High     | 1 |
| Medium   | 2 |
| Low      | 3 |
| **Итого** | **6** |

## Risk Heatmap

| Категория      | Critical | High | Medium | Low |
|----------------|----------|------|--------|-----|
| infra          |          | 1    |        | 1   |
| rbac           |          |      | 1      |     |
| business-logic |          |      | 1      |     |
| shared-hosting |          |      |        | 1   |
| installer      |          |      |        | 1   |

---

## [SEC-016] composer.json доступен по HTTP на nginx

### Мета

- **Severity**: High
- **Категория**: infra
- **Затронутые файлы**: `api/composer.json`, `api/.htaccess`
- **Endpoint/tool**: `GET https://demo.tropatt.com/api/composer.json`
- **Затронутые роли**: unauthenticated

### Описание проблемы

`api/composer.json` возвращает HTTP 200 при запросе через nginx. Файл содержит PHP guard (добавлен в SEC-002), который проверяет `PHP_SAPI !== 'cli'` и возвращает 404. Однако на nginx (который использует демо-сайт) файлы `.json` **не передаются PHP-интерпретатору** — они отдаются как статические файлы. PHP guard не выполняется, и содержимое JSON отдаётся как plain text.

`api/.htaccess` содержит правило `RewriteRule ^composer\.(json|lock)$ - [F,L]`, но `.htaccess` не обрабатывается nginx — это Apache-only механизм.

**Подтверждено на демо**: `curl -s -o /dev/null -w "%{http_code}" https://demo.tropatt.com/api/composer.json` → 200

### Воспроизведение

1. Выполнить `curl -s https://demo.tropatt.com/api/composer.json`
2. Наблюдать JSON-содержимое файла (или через HEAD запрос — статус 200)

### Влияние

Раскрытие информации о проекте: имя, описание, авторы, лицензия. Коммерческая CRM, имя автора и структура проекта становятся публичными. Минимальный риск, но информация может быть использована для разведки.

### Рекомендация по исправлению

**Что нужно сделать**: В `api/composer.json` заменить PHP guard на проверку, которая работает и на nginx.

**Как лучше реализовать**:
Вариант A (рекомендуемый): Переименовать `composer.json` в `composer.json.dist` (или аналогичный) и добавить в корень проекта реальный `composer.json`, который содержит только PHP guard:

```php
<?php http_response_code(404); exit;
```

Вариант B: Удалить `composer.json` из `api/` и оставить только в корне проекта, где он не будет доступен через web root.

Вариант C: В `web/index.php` или `api/index.php` добавить проверку для запросов к `composer.json` и возвращать 404 на уровне приложения.

**Приоритет**: Must-Fix

---

## [SEC-017] Sticky Notes и Notification endpoints не имеют required_permissions

### Мета

- **Severity**: Medium
- **Категория**: rbac
- **Затронутые файлы**: `api/config/routes.php` (sticky-notes routes, notification routes)
- **Endpoint/tool**:
  - `GET/POST /api/v1/sticky-notes` и подресурсы
  - `GET/POST /api/v1/notifications` и подресурсы
- **Затронутые роли**: authenticated user (любой)

### Описание проблемы

Эндпоинты sticky notes и notifications имеют `auth: true`, но не имеют `required_permissions`. Любой авторизованный пользователь может создавать/читать/редактировать sticky notes и notifications. В предыдущем цикле (SEC-011) были добавлены permissions для estimate endpoints, но sticky notes и notifications остались без изменений.

Хотя sticky notes и notifications являются user-scoped (пользователь видит только свои), отсутствие `required_permissions` на route level означает, что даже пользователь с минимальными правами (например, только `task.manage`) может создавать sticky notes и просматривать уведомления.

**Подтверждено на демо**: `GET /api/v1/sticky-notes` → `STICKY_NOTES_LISTED`, `GET /api/v1/notifications/counters` → `NOTIFICATION_COUNTERS`

### Воспроизведение

1. Залогиниться под admin
2. Выполнить `GET /api/v1/sticky-notes` или `GET /api/v1/notifications/counters`
3. Запрос успешен (200)

### Влияние

Нарушение RBAC-модели. Пользователи с минимальными правами могут создавать sticky notes. Хотя sticky notes и notifications обычно user-scoped, отсутствие route-level permissions ослабляет security posture.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `required_permissions` к sticky-notes и notification endpoints.

**Как лучше реализовать**:
В `api/config/routes.php` добавить:
- Для sticky notes: `'required_permissions' => ['task.manage']`
- Для notifications: `'required_permissions' => ['task.manage']`

При этом убедиться, что контроллеры/сервисы уже проверяют object-level access (пользователь видит только свои sticky notes и уведомления). StickyNoteController и NotificationController должны фильтровать по `user_id`.

**Приоритет**: Should-Fix

---

## [SEC-018] Module install-from-url: потенциальный SSRF

### Мета

- **Severity**: Medium
- **Категория**: business-logic
- **Затронутые файлы**: `api/controller/module/ModuleController.php:282-298`, `api/system/library/module/ModuleRemoteInstaller.php`
- **Endpoint/tool**: `POST /api/v1/modules/install-from-url`
- **Затронутые роли**: authenticated + `settings.manage`

### Описание проблемы

`POST /api/v1/modules/install-from-url` принимает URL модуля и скачивает его с удалённого сервера. Если URL не проверяется на внутренние адреса (localhost, 127.0.0.1, 10.x.x.x, 172.16.x.x, 192.168.x.x, 169.254.169.254), это может быть использовано для SSRF-атаки.

Требуется `settings.manage` permission, поэтому атака возможна только от admin/manager. Но если admin account скомпрометирован, SSRF может быть использован для сканирования внутренней сети или доступа к metadata service облачных провайдеров (169.254.169.254).

### Воспроизведение

1. Залогиниться как admin
2. Отправить `POST /api/v1/modules/install-from-url` с `url=http://169.254.169.254/latest/meta-data/`
3. Если URL не валидирован — ответ содержит metadata облачного сервера

### Влияние

SSRF-атака с правами admin может привести к доступу к внутренним сервисам, metadata облачных провайдеров, сканированию внутренней сети.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить валидацию URL в `ModuleController::installFromUrl()`.

**Как лучше реализовать**:
Использовать существующий метод `WebhookService::isPrivateOrReservedIpV6()` или `WebhookService::resolveHostname()` для проверки, что URL не указывает на:
- localhost (127.0.0.1, ::1)
- Private IP ranges (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
- Metadata IP (169.254.169.254)
- Link-local addresses (169.254.x.x)

Перед скачиванием файла:
1. Распарсить URL → hostname
2. Разрешить hostname в IP
3. Проверить, что IP не private/reserved
4. Если IP зарезервирован — вернуть ошибку `PRIVATE_IP_FORBIDDEN`

**Приоритет**: Should-Fix

---

## [SEC-019] Version endpoint раскрывает source_sha

### Мета

- **Severity**: Low
- **Категория**: infra
- **Затронутые файлы**: `api/controller/system/CoreVersionController.php`
- **Endpoint/tool**: `GET /api/v1/version` (public — auth: false)
- **Затронутые роли**: unauthenticated

### Описание проблемы

Публичный endpoint `GET /api/v1/version` раскрывает `source_sha` — SHA коммита в репозитории. Это позволяет атакующему точно определить версию развёрнутого кода и найти известные уязвимости в этой версии.

Endpoint не требует аутентификации, поэтому любой может получить эту информацию.

**Подтверждено на демо**: Ответ содержит `"source_sha":"47d29d4c0c04dccbebc9b7a4e03e90cae5e0e0c4"`

### Воспроизведение

1. Выполнить `GET https://demo.tropatt.com/api/index.php?route=api/v1/version`
2. Наблюдать поле `source_sha` в ответе

### Влияние

Раскрытие точной версии кода упрощает поиск уязвимостей под конкретную версию. Низкий риск — большинство атак и так не зависят от версии.

### Рекомендация по исправлению

**Что нужно сделать**: Не отдавать `source_sha` в публичном endpoint'е.

**Как лучше реализовать**:
В `CoreVersionController::show()`:
- Убрать `source_sha` и `short_sha` из публичного ответа
- Оставить только `state`, `product`, `core_version`, `core_build`
- Для авторизованных пользователей с `settings.manage` можно вернуть полную информацию

**Приоритет**: Nice-to-Have

---

## [SEC-020] No per-user file upload rate limiting

### Мета

- **Severity**: Low
- **Категория**: business-logic
- **Затронутые файлы**: `api/config/security.php` (uploads config)
- **Endpoint/tool**: `POST /api/v1/files`, `POST /api/v1/knowledge/pages/{id}/files`
- **Затронутые роли**: authenticated user

### Описание проблемы

В `api/config/security.php` есть конфигурация загрузок (`max_size_bytes: 20MB`), но нет rate limiting на количество загрузок в единицу времени. Пользователь может загрузить неограниченное количество файлов, что может привести к:
- Исчерпанию дискового пространства на shared хостинге
- Превышению лимита inodes
- DoS-атаке через массовые загрузки

Также нет квоты на общий объём файлов на пользователя/команду.

### Воспроизведение

1. Залогиниться
2. Массовая загрузка файлов через API за короткое время
3. Все загрузки успешны — нет ограничения

### Влияние

Потенциальный DoS-вектор через исчерпание дискового пространства. На shared хостинге с лимитом 1-2GB это может быть проблемой.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить rate limiting на загрузку файлов.

**Как лучше реализовать**:
В `api/config/security.php`:
```php
'uploads' => [
    'max_size_bytes' => 20 * 1024 * 1024,
    'rate_limit' => [
        'max' => 50,
        'window_sec' => 3600,
    ],
    'quota_per_user' => 500 * 1024 * 1024, // 500MB
],
```

Использовать существующий механизм rate limiting (DatabaseRateLimiter) с ключом `upload:{user_id}`.

**Приоритет**: Nice-to-Have

---

## [SEC-021] Installer session не устанавливает Secure cookie flag

### Мета

- **Severity**: Low
- **Категория**: installer
- **Затронутые файлы**: `web/install.php`
- **Endpoint/tool**: `web/install.php`
- **Затронутые роли**: unauthenticated (pre-installation)

### Описание проблемы

Installer устанавливает `session.cookie_httponly = 1` и `session.cookie_samesite = Lax`, но не проверяет, работает ли сайт по HTTPS, и не устанавливает `session.cookie_secure = 1`. Если установка происходит через HTTPS (что обычно для современных хостингов), cookie передаются без флага Secure.

Это означает, что session cookie может быть перехвачен при MITM-атаке во время установки.

### Воспроизведение

1. Открыть `web/install.php` по HTTPS
2. Проверить Set-Cookie заголовок — нет флага `Secure`

### Влияние

Session hijacking во время установки. Низкий риск, так как установка — одноразовый процесс, и обычно происходит сразу после развёртывания.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить динамическую проверку HTTPS и установку `session.cookie_secure`.

**Как лучше реализовать**:
В `web/install.php`, после определения `$lang`, добавить:
```php
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
```

Или через вызов `session_set_cookie_params()` перед `session_start()`.

**Приоритет**: Nice-to-Have

---

## Общие рекомендации по архитектуре безопасности

1. **nginx compatibility**: Поскольку проект поддерживает shared hosting с nginx, все механизмы защиты, основанные на `.htaccess`, должны иметь PHP-level fallback. nginx не обрабатывает `.htaccess`, поэтому блокировка composer.json, config files, .env должна дублироваться на уровне index.php или через PHP guard с корректной обработкой MIME-типов.

2. **RBAC consistency**: Все endpoint'ы с `auth: true` должны иметь `required_permissions`. Исключения должны быть явно документированы (например, notifications — user-scoped by design).

3. **SSRF defense**: Все endpoint'ы, принимающие URL (webhook, module install, update check), должны валидировать URL на private/reserved IP ranges. Использовать существующий `isPrivateOrReservedIpV6()` метод.

4. **Rate limiting completeness**: Rate limiting должен покрывать не только auth endpoints, но и ресурсоёмкие операции (file upload, export, bulk operations).

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0 | SEC-016 | composer.json доступность | 15 min |
| P1 | SEC-017 | Sticky/notification RBAC | 15 min |
| P1 | SEC-018 | Module SSRF | 30 min |
| P2 | SEC-019 | Version source_sha disclosure | 10 min |
| P3 | SEC-020 | Upload rate limiting | 30 min |
| P3 | SEC-021 | Installer Secure cookie | 10 min |

## Методология аудита

Проверены фазы 0-10 из security audit checklist:
- **Phase 0**: Прямой HTTP-доступ к публичным route'ам, storage, config files, .env, .git. Проверка HTTP-заголовков, rate limiting, CORS.
- **Phase 1**: Login flow, session management, password reset, 2FA, password hashing, token generation.
- **Phase 2**: RBAC проверка на демо, проверка route permissions, sticky notes, notifications, impersonation.
- **Phase 3**: XSS тестирование, SQL injection vectors, command execution, XXE, file upload paths.
- **Phase 4**: MCP (data from previous cycles).
- **Phase 5**: Git secrets history, error handling, CORS config, session config, PHP error display.
- **Phase 6**: Business logic — idempotency, export/import, bulk operations, file quotas.
- **Phase 6.1**: Module install from URL, sandbox restrictions, feature flags, integrations.
- **Phase 6.2**: AI config, providers, encryption, controllers.
- **Phase 6.3**: Push notifications config, VAPID keys.
- **Phase 7**: Dependencies (composer, jQuery, Bootstrap), .htaccess, PHP version.
- **Phase 7.1**: Token generation, password hashing, encryption algorithms.
- **Phase 7.2**: Rate limit config, pagination abuse.
- **Phase 7.3**: Installer CSRF, lock file, bootstrap secret, error messages.
- **Phase 8**: Storage .htaccess, file controller, upload protection.
- **Phase 9**: Web UI templates, login template escaping, CSP headers, X-Frame-Options.
- **Phase 10**: Audit logging, log channels, mask_keys, security events.

**Ограничения аудита**: Аудит проводился в read-only режиме. Код не изменялся. Некоторые проверки (race conditions) не выполнялись на живом демо-сервере.
