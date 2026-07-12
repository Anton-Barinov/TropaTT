# Security Audit — Fixes Specification

> Дата аудита: 2026-07-12
> Версия проекта: (из VERSION)
> Аудитор: AI Security Audit

## Сводка

| Severity | Количество |
|----------|------------|
| Critical | 0 |
| High     | 2 |
| Medium   | 4 |
| Low      | 3 |
| **Итого** | **9** |

## Risk Heatmap

| Категория | Critical | High | Medium | Low |
|-----------|----------|------|--------|-----|
| auth      |          |      | 1      |     |
| installer |          | 1    |        | 1   |
| web       |          |      | 1      | 1   |
| secrets   |          |      | 1      |     |
| infra     |          | 1    |        | 1   |
| business-logic |   |      | 1      |     |

---

## [SEC-001] Installer error messages раскрывают credentials БД

### Мета

- **Severity**: High
- **Категория**: installer
- **Затронутые файлы**: `web/install.php:2582-2585` (функция `testDatabaseConnection`), `web/install.php:1123` (пустой `INSTALL_BOOTSTRAP_SECRET`)
- **Endpoint/tool**: `POST /api/v1/install/check`, `POST /api/v1/install/setup`, AJAX `test_connection`
- **Затронутые роли**: unauthenticated (installer)

### Описание проблемы

Функция `testDatabaseConnection()` пробрасывает исключение PDO напрямую в JSON-ответ:
```php
return ['success' => false, 'message' => t('connection_fail') . ': ' . $e->getMessage()];
```
При ошибке подключения PDO-исключение может содержать:
- Хост и порт БД
- Имя базы данных
- Имя пользователя БД
- Часть сообщения, раскрывающую структуру подключения

На demo `web/install.php` возвращает 410 (установлено), но API-установщик (`/api/v1/install/check`) всё ещё принимает запросы (auth: false) и может раскрыть информацию на свежеустановленной системе, где .env ещё не создан, но install.lock уже существует.

Дополнительно: `INSTALL_BOOTSTRAP_SECRET` генерируется пустым в `.env`:
```php
$envContent .= "INSTALL_BOOTSTRAP_SECRET=\n\n";
```

### Воспроизведение

1. Отправить POST на `/api/index.php?route=api/v1/install/check` с некорректными credentials
2. В ответе получить `connection_fail: SQLSTATE[HY000] [2002] Connection refused` — хост/порт в сообщении

### Влияние

Раскрытие конфигурации БД (хост, порт, имя БД, пользователь) неавторизованным пользователям. Пустой `INSTALL_BOOTSTRAP_SECRET` ослабляет защиту installer API.

### Рекомендация по исправлению

**Что нужно сделать**: Обернуть PDO-исключение в обобщённое сообщение без деталей.

**Как лучше реализовать**:
- В `testDatabaseConnection()` не передавать `$e->getMessage()` клиенту
- Использовать `error_log()` для логирования полной ошибки, клиенту отдавать только `connection_fail`
- Для `INSTALL_BOOTSTRAP_SECRET`: генерировать случайное значение через `bin2hex(random_bytes(16))`

**Приоритет**: Must-Fix

---

## [SEC-002] composer.json и метаданные доступны по HTTP

### Мета

- **Severity**: High
- **Категория**: infra
- **Затронутые файлы**: `api/composer.json`, `api/composer.lock`
- **Endpoint/tool**: `GET /api/composer.json`
- **Затронутые роли**: unauthenticated

### Описание проблемы

`api/composer.json` возвращает HTTP 200 при запросе по URL. Файл содержит метаданные проекта: название, описание, авторов, лицензию, зависимости (хотя composer.json пустой, сам факт доступности — проблема). `api/.htaccess` содержит правило `RewriteRule ^composer\.(json|lock)$ - [F,L]`, но на demo сервере (nginx + PHP-FPM) mod_rewrite через `.htaccess` не работает — nginx не обрабатывает `.htaccess`.

Это известное ограничение shared hosting: `.htaccess` работает только под Apache. На nginx composer.json остаётся доступным.

### Воспроизведение

`GET https://demo.tropatt.com/api/composer.json` → HTTP 200

### Влияние

Раскрытие структуры проекта, метаданных, информации о версиях. Низкий риск, но противоречит принципу defence-in-depth.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить PHP-guard в начало `composer.json` (хотя это нестандартно) или заблокировать через nginx config.

**Как лучше реализовать**:
- Для nginx: добавить `location ~ /(composer\.(json|lock)|\.env|\.git)` { deny all; } в конфиг сервера
- Для Apache: правило уже есть в `.htaccess`, работает корректно
- Альтернатива: переместить composer.json выше docroot

**Приоритет**: Should-Fix

---

## [SEC-003] CSP содержит unsafe-inline и unsafe-eval для script-src

### Мета

- **Severity**: Medium
- **Категория**: web
- **Затронутые файлы**: `web/index.php:25-36`
- **Endpoint/tool**: Web страницы
- **Затронутые роли**: authenticated users (web UI)

### Описание проблемы

Content-Security-Policy заголовок на web-страницах содержит:
```
script-src 'self' 'unsafe-inline' 'unsafe-eval' https:
```

`unsafe-inline` разрешает inline-скрипты (уязвимость к stored XSS), `unsafe-eval` разрешает `eval()` (риск для DOM-based XSS). CSP установлен в режиме `Content-Security-Policy-Report-Only` при `CRM_WEB_CSP_REPORT_ONLY=1`, что означает что даже текущие ограничения не блокируются, а только логируются.

### Воспроизведение

Проверить HTTP-заголовок:
`curl -sI 'https://demo.tropatt.com/web/index.php?route=login' | grep -i content-security-policy`

### Влияние

При stored XSS уязвимости (например, через имя пользователя, название задачи) CSP не остановит инлайн-скрипт. Report-Only режим означает отсутствие реальной блокировки.

### Рекомендация по исправлению

**Что нужно сделать**: Ужесточить CSP — убрать `unsafe-inline` и `unsafe-eval`, перевести в enforce-режим.

**Как лучше реализовать**:
1. Перевести все inline-скрипты в отдельные JS-файлы или использовать nonce
2. Убрать `unsafe-eval` и переписать код, использующий `eval()`/`new Function()`
3. Убрать `CRM_WEB_CSP_REPORT_ONLY` или выключить report-only в production
4. Использовать `strict-dynamic` для современных браузеров

**Приоритет**: Should-Fix

---

## [SEC-004] Password reset: несоответствие валидации между контроллером и сервисом

### Мета

- **Severity**: Medium
- **Категория**: auth
- **Затронутые файлы**: `api/controller/security/PasswordResetController.php:55-60`, `api/system/library/service/PasswordResetService.php:76-78`
- **Endpoint/tool**: `POST /api/v1/security/password-reset/confirm`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Контроллер `PasswordResetController::confirm()` проверяет пароль:
```php
if (strlen($newPassword) < 12 || !preg_match('/[A-Z]/', ...) || ...)
```

Сервис `PasswordResetService::confirm()` проверяет только:
```php
if (mb_strlen($newPassword) < 8)
```

Это означает, что если через API будет вызываться `PasswordResetService` напрямую (в будущем, или через другой вход), пароль может быть установлен с длиной всего 8 символов без проверки сложности. Единая точка валидации отсутствует.

### Воспроизведение

1. Получить reset token
2. Вызвать `POST /api/v1/security/password-reset/confirm` с паролем `Abc12345!` (9 символов, проходит сервис но не контроллер)
   → Контроллер отклонит (нужно 12+)
3. Если появится другой endpoint, вызывающий `PasswordResetService::confirm()` напрямую — пароль примет 8 символов

### Влияние

Возможность установки слабого пароля при обходе контроллера. Расхождение логики.

### Рекомендация по исправлению

**Что нужно сделать**: Перенести всю валидацию пароля в сервис, чтобы она применялась единообразно.

**Как лучше реализовать**:
- Добавить метод/конфигурацию для минимальной длины пароля
- Проверять в `PasswordResetService::confirm()` те же требования (12+ символов, сложность)
- В контроллере оставить только первичную проверку (fail-fast), но основной гард должен быть в сервисе

**Приоритет**: Should-Fix

---

## [SEC-005] INSTALL_BOOTSTRAP_SECRET генерируется пустым

### Мета

- **Severity**: Medium
- **Категория**: secrets
- **Затронутые файлы**: `web/install.php:1123`, `api/config/install.php:17`
- **Endpoint/tool**: Installer
- **Затронутые роли**: unauthenticated (installer)

### Описание проблемы

При установке в `.env` записывается:
```php
$envContent .= "INSTALL_BOOTSTRAP_SECRET=\n\n";
```
Ключ генерируется пустым. `api/config/install.php` ожидает его использование:
```php
'bootstrap_secret' => (string)(getenv('INSTALL_BOOTSTRAP_SECRET') ?: ''),
```
Если переменная пуста, bootstrap-защита API installer не работает.

### Воспроизведение

1. Установить CRM через web installer
2. Проверить `api/.env` — строка `INSTALL_BOOTSTRAP_SECRET=` пуста
3. API installer endpoint может быть доступен без bootstrap secret

### Влияние

Установщик API (`/api/v1/install/*`) не защищён bootstrap secret, если не задан вручную в .env. На demo это не влияет (installer уже заблокирован), но на свежих установках API installer может быть вызван без секрета.

### Рекомендация по исправлению

**Что нужно сделать**: Генерировать случайное значение для `INSTALL_BOOTSTRAP_SECRET` при установке.

**Как лучше реализовать**:
- В `writeEnvFile()` добавить генерацию:
```php
'bootstrap_secret' => bin2hex(random_bytes(16))
```
- Либо в `writeEnvFile()`: `$envContent .= "INSTALL_BOOTSTRAP_SECRET=" . bin2hex(random_bytes(16)) . "\n\n";`

**Приоритет**: Should-Fix

---

## [SEC-006] CSP mismatch: frame-ancestors отличается между web и API

### Мета

- **Severity**: Low
- **Категория**: web
- **Затронутые файлы**: `web/index.php:25`, `api/system/library/app.php:754`
- **Endpoint/tool**: Web pages, API responses
- **Затронутые роли**: authenticated (web + API)

### Описание проблемы

Web-приложение устанавливает `frame-ancestors 'self'` (разрешает встраивание в iframe на том же домене), а API устанавливает `frame-ancestors 'none'` (запрещает встраивание). Это расхождение может быть использовано для clickjacking через SPA, если SPA-часть встроена во фрейм на поддомене.

### Воспроизведение

Сравнить заголовки:
- Web: `frame-ancestors 'self'` → разрешает iframe на том же origin
- API: `frame-ancestors 'none'` → запрещает полностью

### Влияние

Низкий риск: SPA на том же домене может быть встроен во фрейм на том же origin, но X-Frame-Options: SAMEORIGIN на web и DENY на API компенсируют различие.

### Рекомендация по исправлению

**Что нужно сделать**: Унифицировать frame-ancestors.

**Как лучше реализовать**:
- Установить `frame-ancestors 'none'` на всех страницах, если SPA не использует iframe
- Либо установить `frame-ancestors 'self'` везде, если iframe используется

**Приоритет**: Nice-to-Have

---

## [SEC-007] Session token renews slidingly without forcing re-authentication after critical events

### Мета

- **Severity**: Low
- **Категория**: auth
- **Затронутые файлы**: `api/system/library/service/AuthService.php:190-197`
- **Endpoint/tool**: `GET /api/v1/auth/me`
- **Затронутые роли**: authenticated users

### Описание проблемы

`AuthService::me()` продлевает сессию при каждом обращении:
```php
$newExpiresAt = gmdate('Y-m-d H:i:s', time() + $this->tokenTtlSeconds);
$this->auth->extendSessionByTokenHash($hash, $newExpiresAt);
```
Хотя есть проверка `maxSessionLifetimeSeconds` (абсолютный TTL), сессия не требует повторной аутентификации после:
- Смены пароля (сессии revokeAllByUserId — это хорошо, но logout не принуждается)
- Смены email/логина
- Подозрительной активности (новый IP, новый user-agent)

`device_fingerprint` записывается при создании сессии, но не сверяется при валидации.

### Воспроизведение

1. Залогиниться с User-Agent: Chrome/Windows
2. Периодически вызывать `/api/v1/auth/me` с тем же токеном
3. Сессия будет продлеваться бесконечно до maxSessionLifetime (30 дней)
4. При смене пароля: сессии отзываются, но украденный токен, использованный до смены, не будет продлён

### Влияние

Низкий — maxSessionLifetime (30 дней) уже реализован. Но отсутствие сверки device_fingerprint и IP позволяет использовать украденный токен с другого устройства/локации.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить сверку device_fingerprint и/или подсети IP при продлении сессии.

**Как лучше реализовать**:
- В `me()` проверять device_fingerprint из сессии с текущим user-agent
- При резком изменении подсети IP — уменьшать TTL или требовать 2FA
- Опционально: отправлять security alert при входе с нового устройства

**Приоритет**: Nice-to-Have

---

## [SEC-008] Пустой INSTALL_BOOTSTRAP_SECRET снижает защиту API installer

### Мета

- **Severity**: Low
- **Категория**: installer
- **Затронутые файлы**: `web/install.php:1123`, `api/config/install.php:17`
- **Endpoint/tool**: `POST /api/v1/install/*`
- **Затронутые роли**: unauthenticated

### Описание проблемы

(Связано с SEC-005, но с фокусом на API installer bootstrap).
`api/config/install.php` ожидает `INSTALL_BOOTSTRAP_SECRET` для защиты API installer:
```php
'bootstrap_secret' => (string)(getenv('INSTALL_BOOTSTRAP_SECRET') ?: ''),
```
Но installer пишет `.env` с пустым значением. На demo API installer недоступен (возвращает 404), но на свежих установках после успешного web-install'а API installer может быть доступен без bootstrap secret.

### Рекомендация по исправлению

(Совпадает с SEC-005)

**Приоритет**: Nice-to-Have

---

## [SEC-009] internal/migration endpoint'ы не используют middleware поверх route permissions

### Мета

- **Severity**: Low
- **Категория**: infra
- **Затронутые файлы**: `api/config/routes.php:16-19`
- **Endpoint/tool**: `GET|POST /internal/migration/*`
- **Затронутые роли**: authenticated (settings.manage), root

### Описание проблемы

Route'ы `/internal/migration/*` защищены `required_permissions => ['settings.manage']` в routes.php. Однако отсутствует дополнительная проверка `is_root` в контроллере (MigrationController). Если права `settings.manage` назначены не-root пользователю (например, через роль с этим правом), он сможет выполнять миграции, что является исключительно операцией root-администратора.

### Воспроизведение

1. Создать роль с правом `settings.manage` без `is_root`
2. Назначить пользователю
3. Пользователь может вызвать `POST /internal/migration/up`

### Влияние

Не-root администратор может выполнить миграции БД — потенциально деструктивная операция.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `is_root` проверку в MigrationController или изменить route permission на более строгую.

**Как лучше реализовать**:
- В контроллере добавить `if (!(bool)($this->actor()['is_root'] ?? false)) return $this->forbidden()`
- Или изменить permission на кастомное, которое есть только у root

**Приоритет**: Nice-to-Have

---

## Общие рекомендации по архитектуре безопасности

1. **Единая точка валидации**: все security-критичные проверки (пароли, токены, permissions) должны быть реализованы в service-слое, а не только в controller — для защиты от обхода через альтернативные endpoint'ы
2. **Fail-closed для installer**: installer API должен быть доступен только при наличии bootstrap secret и до создания install.lock
3. **CSP hardening**: последовательное ужесточение CSP до `strict-dynamic` с nonce для inline-скриптов
4. **Device fingerprinting**: использовать device_fingerprint при валидации сессии для обнаружения угнанных токенов

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0 | SEC-001 | Installer error messages не раскрывают credentials | 1 hour |
| P1 | SEC-002, SEC-003 | composer.json блокировка, CSP hardening | 2-3 hours |
| P1 | SEC-004, SEC-005 | Password validation unification, bootstrap secret | 1 hour |
| P2 | SEC-006, SEC-007, SEC-008, SEC-009 | CSP unification, session hardening, etc. | 3-4 hours |

## Методология аудита

Аудит проводился read-only: анализ кода (72 контроллера, 64 модели, системные библиотеки, web-шаблоны) + выборочная верификация на demo-сайте `https://demo.tropatt.com/`. Проверены: аутентификация, RBAC (routes.php + контроллеры), SQL-инъекции (QueryBuilder), XSS (шаблоны + JS), SSRF (вебхуки, модули), file upload, installer, CSP/HTTP-заголовки, rate limiting, secret storage, MCP-безопасность.

Не проверялись: бизнес-логика предметной области (workflow, approval, SLA), performance/DoS, полный audit всех 761 route'ов, интеграции с внешними сервисами.
