# Security Audit — Fixes Specification (Cycle 3)

> Дата аудита: 2026-07-12 (Cycle 3)
> Версия проекта: 0.1.0
> Аудитор: AI Defensive Security Code Review
> Предыдущие циклы: Cycle 1 (9 findings), Cycle 2 (6 findings, 3 fixed)
> Scope: Full 10-Phase Audit + Sub-phases

---

## Сводка

| Severity (CVSS) | Количество |
|-----------------|------------|
| Critical (9.0–10.0) | 0 |
| High (7.0–8.9) | 0 |
| Medium (4.0–6.9) | 2 |
| Low (0.1–3.9) | 3 |
| **Итого** | **5** |

### Risk Heatmap

| OWASP Category | Critical | High | Medium | Low |
|----------------|----------|------|--------|-----|
| A04: Insecure Design | 0 | 0 | 1 | 0 |
| A05: Security Misconfiguration | 0 | 0 | 0 | 2 |
| A07: Auth Failures | 0 | 0 | 0 | 1 |
| A09: Logging & Monitoring | 0 | 0 | 1 | 0 |
| **Итого** | **0** | **0** | **2** | **3** |

---

## [SEC-016] Staging update directories не очищаются после обновления

### Мета

- **Severity**: Medium (CVSS 5.3)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N
- **CWE-ID**: CWE-200 (Exposure of Sensitive Information)
- **OWASP**: A04:Insecure Design
- **Категория**: infra
- **Затронутые файлы**: `storage_api/updates/staging/upd_*/*`, `api/system/library/update/CoreUpdatePlanner.php`, `api/system/library/update/CoreUpdateStatusService.php`
- **Endpoint/tool**: `POST /api/v1/core/update/apply`
- **Затронутые роли**: unauthenticated (если .htaccess не защищает)

### Описание проблемы

После успешного обновления staging-директории не удаляются. На демо-сервере обнаружено 5+ директорий в `storage_api/updates/staging/`, содержащих полные копии web-части системы (включая `.htaccess` файлы). Хотя `storage_api/.htaccess` блокирует прямой доступ (`Require all denied`), при изменении конфигурации или ошибке в .htaccess staging-директории станут доступны через URL.

### Воспроизведение

1. Выполнить обновление системы
2. Проверить `storage_api/updates/staging/` — остаются директории `upd_*`
3. Каждая содержит полную копию web-части

### Влияние

- Раскрытие структуры файлов и конфигурации
- Накопление копий системы (дисковое пространство)
- Потенциальный доступ к staging-версии при сбое .htaccess

### Рекомендация по исправлению

**Что нужно сделать**: Добавить очистку staging-директории после успешного применения обновления или после rollback.

**Как лучше реализовать**: В `CoreUpdatePlanner` или `CoreUpdateStatusService`, в методе, который завершает обновление (apply/complete), добавить удаление staging-директории:

```php
// После успешного apply
$stagingBase = rtrim($this->config->get('update.staging_dir'), '/');
$stagingDir = $stagingBase . '/' . $updateSessionDir;
if (is_dir($stagingDir)) {
    $this->removeDirectory($stagingDir);
}
```

Метод `removeDirectory()` уже существует в `ModuleRemoteInstaller::cleanDir()` — можно вынести в общий helper или использовать существующий.

**Приоритет**: Should-Fix

---

## [SEC-017] PII в security-логах при имперсонации

### Мета

- **Severity**: Medium (CVSS 5.0)
- **CVSS Vector**: AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:N/A:N
- **CWE-ID**: CWE-532 (Insertion of Sensitive Information into Log File)
- **OWASP**: A09:Logging & Monitoring
- **Категория**: logging, privacy
- **Затронутые файлы**: `api/system/library/service/ImpersonationService.php:114-115, 220-221`
- **Endpoint/tool**: `POST /api/v1/security/impersonation/start`, `POST /api/v1/security/impersonation/stop`
- **Затронутые роли**: root (admin с правом имперсонации)

### Описание проблемы

`ImpersonationService` логирует полные IP-адреса и User-Agent строки при старте и остановке имперсонации. Хотя SEC-013 исправил маскировку IP в request-логах, security-логи при имперсонации всё ещё содержат полные IP и User-Agent. Это нарушает принцип минимизации данных (GDPR Art. 5(1)(c)).

### Воспроизведение

1. Admin запускает имперсонацию
2. Security-лог содержит: `'ip' => $ip, 'user_agent' => $userAgent` — полные значения
3. Любой пользователь с `logs.view` может видеть эти данные

### Влияние

- Утечка PII (IP-адрес, User-Agent) в security-логах
- Возможность отследить активность админа по IP
- Нарушение 152-ФЗ/GDPR при хранении логов

### Рекомендация по исправлению

**Что нужно сделать**: Маскировать IP-адрес и User-Agent в security-логах при имперсонации для non-root пользователей.

**Как лучше реализовать**: В `ImpersonationService.php`, в методах `start()` (строка 114) и `stop()` (строка 220), применить маскировку:

```php
// В start()
'ip' => $this->maskIp($ip, $actorFull),
'user_agent' => '***',

// В stop()
'ip' => $this->maskIp($ip, $actor),
'user_agent' => '***',
```

Метод `maskIp()` можно заимствовать из `App::maskIpForLog()` (уже существует в `app.php`).

**Приоритет**: Should-Fix

---

## [SEC-018] Отсутствует Permissions-Policy заголовок

### Мета

- **Severity**: Low (CVSS 3.1)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N
- **CWE-ID**: CWE-200 (Exposure of Sensitive Information)
- **OWASP**: A05:Security Misconfiguration
- **Категория**: web
- **Затронутые файлы**: `api/system/library/app.php` (bootstrapRuntime — секция headers)
- **Затронутые роли**: all

### Описание проблемы

Все современные security-заголовки установлены (CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy), но отсутствует `Permissions-Policy`. Этот заголовок ограничивает доступ браузерных API (geolocation, camera, microphone, etc.) для страниц приложения. Хотя CRM не использует эти API, отсутствие заголовка — отступление от best practice defense-in-depth.

### Воспроизведение

1. `curl -s -I https://demo.tropatt.com/api/index.php?route=api/v1/version`
2. В ответе есть CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
3. `Permissions-Policy` отсутствует

### Влияние

- Если в будущем на какой-либо странице будет XSS, злоумышленник сможет использовать браузерные API (geolocation, notifications)
- Низкий риск, отсутствие глубной защиты

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `Permissions-Policy` header с минимальными разрешениями.

**Как лучше реализовать**: В `app.php` в `bootstrapRuntime()`, после существующих security-заголовков:

```php
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), midi=(), sync-xhr=(), accelerometer=(), gyroscope=(), magnetometer=()");
```

Или более краткая версия, блокирующая всё:

```php
header('Permissions-Policy: interest-cohort=()');
```

**Приоритет**: Nice-to-Have

---

## [SEC-019] Session cookie флаги не устанавливаются явно в PHP

### Мета

- **Severity**: Low (CVSS 3.7)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N
- **CWE-ID**: CWE-614 (Sensitive Cookie in HTTPS Session Without 'Secure' Attribute)
- **OWASP**: A05:Security Misconfiguration
- **Категория**: auth, web
- **Затронутые файлы**: `api/system/library/app.php` (bootstrap)
- **Endpoint/tool**: Все cookie-аутентифицированные запросы
- **Затронутые роли**: user (cookie auth)

### Описание проблемы

При cookie-аутентификации (транспорт 'cookie') используются сессионные cookie `crm_api_session`. Однако в bootstrap-коде `app.php` не вызывается `ini_set('session.cookie_secure', 1)` или `ini_set('session.cookie_httponly', 1)` перед отправкой cookie. На shared хостинге, где нет доступа к php.ini, cookie могут быть отправлены без флагов Secure/HttpOnly/SameSite, что делает их уязвимыми к перехвату.

### Воспроизведение

1. Залогиниться через cookie-аутентификацию (браузер)
2. Проверить Set-Cookie заголовок — есть ли `Secure`, `HttpOnly`, `SameSite`
3. Если php.ini не настроен — cookie будет без флагов

### Влияние

- Cookie без Secure могут быть перехвачены при HTTP-соединении (Man-in-the-Middle)
- Cookie без HttpOnly могут быть украдены через XSS
- Cookie без SameSite уязвимы к CSRF (хотя есть отдельная CSRF-защита)

### Рекомендация по исправлению

**Что нужно сделать**: Устанавливать cookie-флаги в PHP-коде перед созданием сессии.

**Как лучше реализовать**: В `app.php`, в `bootstrapRuntime()`:

```php
// Установка параметров сессии перед session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

$https = strtolower((string)($request->server['HTTPS'] ?? ''));
if ($https !== '' && $https !== 'off' && $https !== '0') {
    ini_set('session.cookie_secure', 1);
}

// Проверка X-Forwarded-Proto для reverse proxy
$forwardedProto = strtolower((string)$request->header('X-Forwarded-Proto', ''));
if ($forwardedProto === 'https') {
    ini_set('session.cookie_secure', 1);
}
```

**Приоритет**: Nice-to-Have

---

## [SEC-020] GitHub Actions workflow не проверяет целостность

### Мета

- **Severity**: Low (CVSS 3.1)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N
- **CWE-ID**: CWE-494 (Download of Code Without Integrity Check)
- **OWASP**: A08:Integrity Failures
- **Категория**: supply-chain
- **Затронутые файлы**: `.github/workflows/*.yml`
- **Затронутые роли**: CI/CD

### Описание проблемы

В GitHub Actions workflow-файлах все actions могут использоваться без указания SHA-commit хеша (используются теги `@v3`, `@v4`). Это создаёт риск supply chain атаки: если репозиторий action будет скомпрометирован и тег `@v3` будет перенаправлен на новую версию с вредоносным кодом, CI/CD pipeline выполнит его.

Также отсутствует `gitleaks` (или аналог) в CI для сканирования секретов перед коммитом.

### Воспроизведение

1. Проверить `.github/workflows/` — все actions по тегу, а не по SHA
2. `gitleaks` не установлен (проверено: not installed)
3. `composer audit` / `npm audit` не запускаются

### Влияние

- Supply chain атака через подмену action
- Секреты могут быть случайно закоммичены без обнаружения
- Уязвимые зависимости не выявляются автоматически

### Рекомендация по исправлению

**Что нужно сделать**: Закрепить actions по SHA-хешу, добавить gitleaks и dependency audit.

**Как лучше реализовать**:

1. В workflow-файлах: `uses: actions/checkout@<SHA>` вместо `@v4`
2. Добавить шаг:
```yaml
- name: Gitleaks
  uses: gitleaks/gitleaks-action@v2
```
3. Установить gitleaks: `brew install gitleaks`
4. Добавить pre-commit hook с gitleaks

**Приоритет**: Nice-to-Have

---

## Общие рекомендации по архитектуре безопасности (Cycle 3)

### 1. Прогресс за 3 цикла аудита

| Цикл | Найдено | Исправлено | Осталось |
|------|---------|------------|----------|
| Cycle 1 | 9 (1H, 4M, 4L) | 7 | 2 |
| Cycle 2 | 6 (1H, 3M, 2L) | 4 | 1 |
| Cycle 3 | 5 (0H, 2M, 3L) | 0 (отчёт) | 5 |
| **Итого** | **20** | **11** | **8** |

### 2. Что уже закрыто за 3 цикла

| Область | Статус |
|---------|--------|
| CSP with report-uri | ✅ |
| HSTS includeSubDomains | ✅ |
| X-Frame-Options, X-Content-Type-Options, Referrer-Policy | ✅ |
| Argon2id for passwords | ✅ |
| AES-256-GCM for 2FA secrets | ✅ |
| Database rate limiting (login, IP, password reset, MCP) | ✅ |
| CSRF protection for cookie auth | ✅ |
| Route-level RBAC | ✅ |
| MCP RBAC + batch limit (20) | ✅ |
| MCP prompt injection active blocking | ✅ |
| IP masking in logs (request logs) | ✅ |
| Query string masking in logs | ✅ |
| Module package signature (HMAC-SHA256) | ✅ |
| Invitation token TTL (7 days default) | ✅ |
| Upload quarantine | ✅ |
| Webhook SSRF protection (UrlSafetyValidator) | ✅ |
| Installer locked (410 Gone) | ✅ |
| .htaccess protection for storage/ and storage_api/ | ✅ |
| htmlspecialchars in web templates | ✅ |

### 3. Осталось открытых

| SEC | Severity | Описание | Статус |
|-----|----------|----------|--------|
| SEC-007 | Low | CSP nonce | Не исправлено |
| SEC-011 | Medium | Staging cleanup | Не исправлено |
| SEC-016 | Medium | Staging directories persist | Новая находка |
| SEC-017 | Medium | PII in impersonation security logs | Новая находка |
| SEC-018 | Low | Permissions-Policy header | Новая находка |
| SEC-019 | Low | Session cookie flags in PHP | Новая находка |
| SEC-020 | Low | Supply chain / CI integrity | Новая находка |

---

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P1 | SEC-016, SEC-017 | Staging cleanup + PII в security-логах | 2 часа |
| P2 | SEC-018, SEC-019 | Permissions-Policy + session cookie flags | 30 мин |
| P3 | SEC-020 | CI integrity + gitleaks | 1 час |
| P4 | SEC-007, SEC-011 | CSP nonce | 2 часа |

---

## Методология аудита (Cycle 3)

- **Тип**: Defensive Static Code Analysis
- **Coverage**: 10 фаз + подфазы (0–10)
- **Проверенные файлы**: ~30 ключевых файлов безопасности
- **HTTP проверки**: заголовки, rate limiting, public routes, installer
- **Git history**: проверка .env и секретов в истории
- **.htaccess**: все защитные файлы в storage, web, api

### Проверенные области

| Фаза | Статус | Ключевые находки |
|------|--------|-----------------|
| 0 — Рекогносциция | ✅ | 10 public routes, installer 410, все storage защищены |
| 1 — Аутентификация | ✅ | Argon2id, 2FA AES-256-GCM, DatabaseRateLimiter, CSRF |
| 2 — RBAC/IDOR | ✅ | Route-level + MCP RBAC, impersonation hierarchy |
| 3 — Инъекции | ✅ | PDO, ORDER BY validation, file quarantine, no exec/system |
| 4 — MCP | ✅ | Prompt injection blocking, batch limit 20, RBAC |
| 5 — Секреты | ✅ | .env gitignored, no committed secrets, KeyGuard |
| 6 — Бизнес-логика | ✅ | Rate limiting, invitation TTL, impersonation logging |
| 7 — Инфраструктура | ✅ | HSTS, CSP, X-Frame-Options, all headers present |
| 7.5 — Shared hosting | 🟡 SEC-019 | Session cookie flags not explicitly set in PHP |
| 7.10 — Supply chain | 🟡 SEC-020 | No gitleaks, no composer audit in CI |
| 8 — Файлы | ✅ | Quarantine, .htaccess, MIME validation |
| 9 — Web | ✅ | htmlspecialchars, CSP, X-Frame-Options, CSRF |
| 10 — Логирование | 🟡 SEC-017 | PII in impersonation security logs |

### Security Metrics

| Метрика | Цель | Текущее состояние |
|---------|------|-------------------|
| Critical/High issues | = 0 | ✅ 0 (после 3 циклов) |
| Open Medium issues | ≤ 3 | 2 remaining |
| Open Low issues | ≤ 5 | 4 remaining |
| SAST in CI | PHPStan level 6 | ❌ Не настроен |
| Secret scanning | gitleaks — 0 findings | ❌ Не установлен |
| Install lock | installer недоступен | ✅ 410 Gone |
| CSP with report-uri | present | ✅ |
| HSTS includeSubDomains | present | ✅ |
| MCP prompt injection | active block | ✅ |
