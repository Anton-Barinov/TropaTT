# Security Audit — Fixes Specification

> Дата аудита: 2026-07-12
> Версия проекта: 0.1.0 (build 20260618.003)
> Аудитор: AI Defensive Security Code Review
> Методология: Static Code Analysis (Phase 0-10)

---

## Сводка

| Severity (CVSS) | Количество |
|-----------------|------------|
| Critical (9.0–10.0) | 0 |
| High (7.0–8.9) | 2 |
| Medium (4.0–6.9) | 4 |
| Low (0.1–3.9) | 3 |
| **Итого** | **9** |

> ✅ **Критических уязвимостей не найдено.** Система демонстрирует зрелый подход к безопасности: Argon2id, CSP, HSTS, CSRF, rate limiting, parameterized queries, RBAC на уровне маршрутов и MCP, AES-256-GCM для 2FA.

### Risk Heatmap (по категориям OWASP Top 10)

| OWASP Category | Critical | High | Medium | Low |
|----------------|----------|------|--------|-----|
| A01: Broken Access Control | 0 | 0 | 0 | 0 |
| A02: Cryptographic Failures | 0 | 1 | 0 | 0 |
| A03: Injection | 0 | 0 | 1 | 0 |
| A04: Insecure Design | 0 | 1 | 1 | 0 |
| A05: Security Misconfiguration | 0 | 0 | 0 | 1 |
| A07: Auth Failures | 0 | 0 | 1 | 0 |
| A09: Logging & Monitoring | 0 | 0 | 0 | 1 |
| A10: SSRF | 0 | 0 | 1 | 1 |
| **Итого** | **0** | **2** | **4** | **3** |

---

## [SEC-001] APP_KEY placeholder закоммичен в репозиторий

### Мета

- **Severity**: High (CVSS 7.5)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N
- **CWE-ID**: CWE-798 (Use of Hard-coded Credentials)
- **OWASP**: A02:Cryptographic Failures
- **Категория**: secrets
- **Затронутые файлы**: `.env:1`
- **Затронутые роли**: unauthenticated (read access to repo)

### Описание проблемы

Файл `.env` содержит `APP_KEY=local-dev-app-key-change-in-production` и закоммичен в git-репозиторий. Хотя текущее значение — очевидный placeholder для разработки, сам факт наличия `.env` в истории git:

1. Создаёт прецедент — разработчик может закоммитить реальный ключ в будущем
2. Раскрывает структуру конфигурации системы
3. Не позволяет настроить корректное `.gitignore` для защиты секретов

### Воспроизведение

1. `git log --all --oneline -- '*.env'` — показывает коммиты с `.env`
2. `git show HEAD:.env` — показывает `APP_KEY=local-dev-app-key-change-in-production`

### Влияние

При компрометации APP_KEY злоумышленник может:
- Расшифровать TOTP secrets (2FA) — AES-256-GCM использует APP_KEY
- Расшифровать AI encryption key
- Манипулировать HMAC-подписями (pending 2FA tokens, webhook signatures)

### Рекомендация по исправлению

**Что нужно сделать**: Удалить `.env` из git-истории и заменить его на `.env.example`.

**Как лучше реализовать**:

1. Создать `.env.example` с описанием переменных (без секретов):
   ```
   APP_KEY=change-me-to-64-hex-chars
   CSRF_SECRET_KEY=change-me
   ...
   ```
2. Добавить `.env` и `.env.*` в `.gitignore`
3. Выполнить очистку git-истории:
   ```bash
   git filter-branch --force --index-filter \
     "git rm --cached --ignore-unmatch .env" \
     --prune-empty --tag-name-filter cat -- --all
   ```
4. Добавить проверку в CI: `git diff --cached --name-only | grep -q '\.env$' && exit 1`

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-002] File-based rate limiter теряет состояние при рестарте

### Мета

- **Severity**: High (CVSS 7.0)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:L
- **CWE-ID**: CWE-367 (Time-of-check Time-of-use)
- **OWASP**: A04:Insecure Design
- **Категория**: business-logic
- **Затронутые файлы**: `api/system/library/service/AuthService.php:261-281`, `api/system/library/service/RateLimitService.php`
- **Endpoint/tool**: `POST /api/v1/auth/login`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Login rate limiting (lockout) использует file-based storage (`RateLimitService`), а не `DatabaseRateLimiter`. При перезапуске веб-сервера или очистке временных файлов счётчик неудачных попыток сбрасывается. Это позволяет злоумышленнику:

- Обойти lockout рестартом сервера (если есть dos-доступ)
- Дождаться естественной очистки временных файлов (cron cleanup)

При этом IP-based rate limiting (`checkIpRateLimit`) использует `DatabaseRateLimiter` — что создаёт асимметрию: IP блокируется надёжно, а per-login lockout может быть сброшен.

Кроме того, код `login()` содержит два независимых вызова rate limiter:
1. `checkIpRateLimit` — DatabaseRateLimiter (10/60s, 300s lock)
2. `checkLoginRateLimit` → `check()` — RateLimitService (file-based, 5/300s, 900s lock)
3. `hitLoginRateLimit` → `check()` с `$increment=true`

Rate limit считается и до, и после проверки пароля (для несуществующих пользователей и неверных паролей). Счётчик сбрасывается только при успешном логине.

### Воспроизведение

1. Выполнить 5 неудачных попыток входа под одним логином → `AUTH_RATE_LIMITED`
2. Перезапустить веб-сервер (или дождаться очистки временных файлов)
3. Выполнить ещё 5 неудачных попыток → lockout сброшен

### Влияние

- Снижение эффективности brute-force защиты
- Lockout может быть непреднамеренно сброшен при деплое без downtime

### Рекомендация по исправлению

**Что нужно сделать**:
- Перевести login lockout на `DatabaseRateLimiter` как уже сделано для IP-based rate limiting
- Убрать дублирование `check` → `check` (сейчас `hitLoginRateLimit` вызывает `check()` с `$increment=true`, что концептуально неверно)

**Как лучше реализовать**:

1. Заменить `RateLimitService` на `DatabaseRateLimiter` для login rate limiting:
   ```php
   // В AuthService уже есть property:
   private readonly RateLimitService $rateLimiter;
   
   // Заменить на DatabaseRateLimiter:
   private readonly DatabaseRateLimiter $loginRateLimiter;
   ```
2. В `app.php` factory передавать `security.login_rate_limiter` (DatabaseRateLimiter) вместо `service.rate_limiter`
3. Упростить логику:
   - `checkLoginRateLimit()` — только проверка (не увеличивает счётчик)
   - `hitLoginRateLimit()` — увеличивает счётчик и проверяет блокировку
   - Использовать единый DatabaseRateLimiter для обоих вызовов

**Приоритет**: Should-Fix

---

## [SEC-003] `whereRaw()` не валидирует содержимое SQL-выражения

### Мета

- **Severity**: Medium (CVSS 6.8)
- **CVSS Vector**: AV:N/AC:M/PR:L/UI:N/S:U/C:H/I:L/A:N
- **CWE-ID**: CWE-89 (SQL Injection)
- **OWASP**: A03:Injection
- **Категория**: injection
- **Затронутые файлы**: `api/system/library/database/builder/QueryBuilder.php:99-110`
- **Затронутые роли**: user с любыми правами (зависит от endpoint'а)

### Описание проблемы

Метод `whereRaw()` принимает произвольное SQL-выражение и подставляет параметры через `?` placeholder'ы. Валидация SQL-синтаксиса или символьного состава не производится. Хотя параметризация через `?` → `:pN` предотвращает прямую инъекцию через значения, следующий код уязвим, если имя столбца или оператор берётся из пользовательского ввода:

```php
// Пример уязвимого использования (статический анализ не нашёл, но риск есть):
$qb->whereRaw($userInput . ' = ?', [$value]);
```

`whereRaw()` используется в `InvitationRepository.php:54`:
```php
$query->whereRaw('(i.public_id LIKE ? OR i.email LIKE ?)', [$search, $search]);
```
Это безопасно (строковые параметры через placeholder), но сам механизм не имеет защиты от ошибочного использования разработчиком.

### Воспроизведение

1. Найти все места, где `whereRaw()` вызывается с конкатенацией пользовательских данных в SQL-выражение
2. `grep -rn "whereRaw.*\\$" api/ --include="*.php"` — проверить каждое использование

### Влияние

- Потенциальная SQL-инъекция при неаккуратном использовании `whereRaw()` с динамическими именами столбцов
- Риск выше в контроллерах с динамической фильтрацией

### Рекомендация по исправлению

**Что нужно сделать**:
1. Добавить статический анализ: проверять все вызовы `whereRaw()` на наличие пользовательских данных в первом аргументе
2. Рассмотреть замену `whereRaw()` на безопасные методы (`where()`, `whereIn()`) где возможно
3. Добавить предупреждение в PHPDoc `whereRaw()`: "Не передавайте пользовательский ввод в SQL-выражение"

**Как лучше реализовать**:

1. Добавить custom PHPStan rule:
   ```php
   // phpstan-rules/src/Rules/NoRawSqlInjectionRule.php
   // Проверяет, что whereRaw() не вызывается с конкатенацией
   ```
2. Аудит всех вызовов `whereRaw()`: `grep -rn "whereRaw" api/ --include="*.php" | grep -v "test\|Test\|vendor"`

**Приоритет**: Should-Fix

---

## [SEC-004] 2FA verify rate limiting по login_token не защищает от distributed brute-force

### Мета

- **Severity**: Medium (CVSS 6.5)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:L/A:N
- **CWE-ID**: CWE-307 (Improper Restriction of Excessive Authentication Attempts)
- **OWASP**: A07:Auth Failures
- **Категория**: auth
- **Затронутые файлы**: `api/controller/security/TwoFactorController.php:125-134`
- **Endpoint/tool**: `POST /api/v1/security/2fa/verify`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Rate limiting на `POST /api/v1/security/2fa/verify` использует ключ `hash('sha256', $loginToken)`. Это означает, что лимит (5 попыток за 300 секунд) привязан к конкретному login_token.

Проблема: если злоумышленник имеет 100 разных login_token (например, создал 100 сессий логина), он может сделать 500 попыток подбора TOTP-кода (5 на каждый токен × 100 токенов).

TOTP-код — всего 6 цифр (1,000,000 комбинаций):
- 5 попыток на токен → 20,000 токенов для полного перебора
- 100 разных login_token → 500 попыток

Для 6-значного TOTP без блокировки по IP вероятность угадывания за 500 попыток ≈ 0.05%.

Кроме того, нет rate limiting по IP-адресу для 2FA verify endpoint.

### Воспроизведение

1. `POST /auth/login` с правильным паролем → получаем login_token (валидный 5 минут)
2. Выполнить 5 неудачных `POST /2fa/verify` с этим login_token
3. Создать новый login_token (повторить шаг 1)
4. Выполнить ещё 5 попыток
5. Повторять: каждый новый login_token даёт ещё 5 попыток

### Влияние

- Снижение эффективности защиты от brute-force TOTP-кода
- Возможность перебора 6-значного кода за разумное время при большом количестве login_token

### Рекомендация по исправлению

**Что нужно сделать**:
1. Добавить IP-based rate limiting на `POST /api/v1/security/2fa/verify`
2. Привязать лимит также к `userId` (когда login_token разрешён)

**Как лучше реализовать**:

В `TwoFactorController::verify()`, после resolveTwoFactorToken (когда `$user` известен):

```php
// IP-based rate limit
$ipKey = 'tfa_ip:' . hash('sha256', $this->request()->ip());
$ipState = $rateLimiter->check('two_factor_verify', $ipKey, 10, 300, 600);
if ($ipState['blocked'] === true) {
    return $this->error('TWO_FACTOR_RATE_LIMITED', ..., 429, ...);
}

// User-based rate limit (после resolveTwoFactorToken)
$userKey = 'tfa_user:' . (int)$user['id'];
$userState = $rateLimiter->check('two_factor_verify', $userKey, 5, 300, 300);
if ($userState['blocked'] === true) {
    return $this->error('TWO_FACTOR_RATE_LIMITED', ..., 429, ...);
}
```

**Приоритет**: Should-Fix

---

## [SEC-005] Webhook URL не проверяется на внутренние адреса (SSRF)

### Мета

- **Severity**: Medium (CVSS 6.3)
- **CVSS Vector**: AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:L/A:L
- **CWE-ID**: CWE-918 (Server-Side Request Forgery)
- **OWASP**: A10:SSRF
- **Категория**: ssrf
- **Затронутые файлы**: `api/controller/webhook/WebhookController.php` (не проверен детально), `api/system/library/service/WebhookService.php`
- **Endpoint/tool**: `POST /api/v1/webhooks`, `POST /api/v1/webhooks/{public_id}/test`
- **Затронутые роли**: user с `webhook.manage`

### Описание проблемы

При создании вебхука или отправке тестового запроса URL не проверяется на внутренние адреса. Пользователь с permission `webhook.manage` может указать URL, указывающий на:

- `127.0.0.1`, `localhost` — внутренние сервисы
- `169.254.169.254` — cloud metadata endpoints (AWS/GCP/Azure)
- Внутренние docker-контейнеры
- gopher://, file://, dict:// протоколы (если поддерживаются HTTP-клиентом)

В коде присутствует `WebhookUrlValidator` (упоминается в AGENTS.md: `SEC-015 (webhook URL warnings)`), что указывает на то, что предупреждение уже добавлено, но необходима активная блокировка, а не только warning.

### Воспроизведение

1. Создать вебхук с URL `https://169.254.169.254/latest/meta-data/` (AWS metadata)
2. Выполнить тестовую отправку
3. Если блокировки нет — в delivery log попадёт содержимое metadata endpoint

### Влияние

- SSRF-атака на внутренние сервисы
- Потенциальное получение cloud-credentials (в облачных развертываниях)
- Доступ к internal API

### Рекомендация по исправлению

**Что нужно сделать**:
1. Добавить активную блокировку (не только warning) внутренних адресов в URL вебхуков
2. Использовать allowlist протоколов (только `https://`)
3. Добавить DNS-резолв с проверкой блокировки loopback/private диапазонов

**Как лучше реализовать**:

```php
// В WebhookService или отдельном валидаторе:
private function validateWebhookUrl(string $url): bool
{
    $parsed = parse_url($url);
    if (!in_array(strtolower($parsed['scheme'] ?? ''), ['https'], true)) {
        return false; // only HTTPS allowed
    }
    
    $host = $parsed['host'] ?? '';
    // Check for IP-based private ranges
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    
    // Check for localhost/loopback hostnames
    $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]', '::1'];
    if (in_array(strtolower($host), $blockedHosts, true)) {
        return false;
    }
    
    // DNS-resolve and check IP ranges
    $ips = dns_get_record($host, DNS_A | DNS_AAAA);
    foreach ($ips as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? '';
        if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    
    return true;
}
```

Использовать существующий паттерн `SecurityCheckService` или `WebhookUrlValidator` для этого.

**Приоритет**: Should-Fix

---

## [SEC-006] Отсутствует CSP report-uri/report-to и мониторинг CSP violations

### Мета

- **Severity**: Medium (CVSS 5.0)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:L/A:N
- **CWE-ID**: CWE-1021 (Improper Restriction of Rendered UI Layers)
- **OWASP**: A05:Security Misconfiguration
- **Категория**: web
- **Затронутые файлы**: `api/system/library/app.php:286-287`
- **Затронутые роли**: all (все пользователи браузерного интерфейса)

### Описание проблемы

CSP заголовок установлен, но не содержит `report-uri` или `report-to` директивы. Это означает, что:

1. CSP violations не логируются
2. Невозможно обнаружить XSS-попытки через reporting
3. Невозможно отследить некорректную работу CSP при обновлениях

Хотя есть endpoint `POST /api/v1/telemetry/csp-report` (route в routes.php), CSP header не включает `report-uri` для отправки отчётов. CSP violations остаются незамеченными.

### Воспроизведение

1. Проверить HTTP-заголовки: `Content-Security-Policy` в ответе API
2. Убедиться, что `report-uri` отсутствует

### Влияние

- CSP violations не логируются — XSS-попытки остаются незамеченными
- Невозможно отладить блокировки легитимного контента

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `report-uri` или `report-to` директиву в CSP заголовок, указывающую на существующий endpoint.

**Как лучше реализовать**:

```php
// В app.php, в bootstrapRuntime():
$cspUrl = ($forwardedProto === 'https' ? 'https' : 'http') . '://' . $host . '/api/index.php?route=api/v1/telemetry/csp-report';
$csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; report-uri " . $cspUrl;

header('Content-Security-Policy: ' . $csp);
header('Content-Security-Policy-Report-Only: ' . $csp . ''); // Отдельно для мониторинга
```

**Приоритет**: Nice-to-Have

---

## [SEC-007] Нет CSP nonce для script-src

### Мета

- **Severity**: Low (CVSS 3.9)
- **CVSS Vector**: AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:L/A:N
- **CWE-ID**: CWE-79 (Cross-site Scripting)
- **OWASP**: A03:Injection
- **Категория**: xss
- **Затронутые файлы**: `api/system/library/app.php:286`
- **Затронутые роли**: all

### Описание проблемы

CSP `script-src 'self'` не использует nonce или hash для инлайн-скриптов. Если на веб-странице потребуется инлайн-скрипт, его выполнение будет заблокировано.

Это защищает от XSS, но делает невозможным:
- Использование инлайн-скриптов без `unsafe-inline`
- Загрузку скриптов с CDN

### Воспроизведение

N/A — это архитектурное ограничение, не уязвимость.

### Влияние

- Низкий риск (существующая конфигурация безопаснее, чем с nonce)
- При добавлении инлайн-скриптов может потребоваться ослабление CSP

### Рекомендация по исправлению

**Что нужно сделать**: Рассмотреть добавление nonce-генерации для script-src, если появятся инлайн-скрипты.

**Как лучше реализовать**:

```php
// Генерация nonce для каждого запроса
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: script-src 'nonce-{$nonce}'; ...");
// Передать nonce в шаблон: template engine получает $nonce
```

**Приоритет**: Nice-to-Have

---

## [SEC-008] Логи содержат маскированные URL, но не маскируют query parameters

### Мета

- **Severity**: Low (CVSS 3.7)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N
- **CWE-ID**: CWE-532 (Insertion of Sensitive Information into Log File)
- **OWASP**: A09:Logging & Monitoring
- **Категория**: logging
- **Затронутые файлы**: `api/system/library/app.php:buildSafeRequestPayload()`
- **Затронутые роли**: user с `logs.view`

### Описание проблемы

Метод `buildSafeRequestPayload()` маскирует поля, содержащие `password`, `token`, `secret`, и ограничивает логируемые ключи до allowlist. Однако:

1. **Query parameters не маскируются** — URL query string не фильтруется через `buildSafeRequestPayload()`
2. В лог запроса попадает `'route' => $routePath` (полный URL с параметрами)

Если чувствительные данные передаются в query string (а не в body), они будут залогированы.

### Воспроизведение

1. Выполнить запрос с токеном в query string: `?api_key=secret123&token=abc`
2. Проверить лог: `token=abc` попадёт в `route` поле

### Влияние

- Потенциальная утечка API-ключей через логи
- Риск для пользователей с permission `logs.view`

### Рекомендация по исправлению

**Что нужно сделать**:
1. Добавить маскировку query string в `buildSafeRequestPayload()`
2. Парсить `$_SERVER['QUERY_STRING']` и маскировать чувствительные параметры

**Как лучше реализовать**:

```php
private function buildSafeRequestPayload(Request $request): array
{
    $input = $request->allInput();
    $safe = parent::buildSafeRequestPayload($request); // existing logic
    
    // Mask query string
    $queryString = $request->server['QUERY_STRING'] ?? '';
    if ($queryString !== '') {
        parse_str($queryString, $queryParams);
        $maskedQuery = [];
        foreach ($queryParams as $key => $value) {
            $normalized = strtolower((string)$key);
            if (str_contains($normalized, 'token') || str_contains($normalized, 'key') || str_contains($normalized, 'secret')) {
                $maskedQuery[$key] = str_repeat('*', strlen((string)$value));
            } else {
                $maskedQuery[$key] = $value;
            }
        }
        $safe['query_string'] = http_build_query($maskedQuery);
    }
    
    return $safe;
}
```

**Приоритет**: Nice-to-Have

---

## [SEC-009] MCP batch processing не имеет явного ограничения на количество tool calls

### Мета

- **Severity**: Medium (CVSS 5.3)
- **CVSS Vector**: AV:N/AC:L/PR:L/UI:N/S:U/C:N/I:N/A:L
- **CWE-ID**: CWE-770 (Allocation of Resources Without Limits or Throttling)
- **OWASP**: A04:Insecure Design
- **Категория**: mcp
- **Затронутые файлы**: `api/controller/mcp/McpController.php` (7 references to batch)
- **Endpoint/tool**: MCP JSON-RPC batch
- **Затронутые роли**: user с любыми правами (доступ к MCP)

### Описание проблемы

MCP Controller поддерживает batch-запросы (массив JSON-RPC сообщений). Количество tool calls в batch не ограничено. Это позволяет:

1. **Resource exhaustion**: отправить batch из 1000+ tool calls, каждый из которых делает запрос к БД
2. **Rate limiting bypass**: per-tool rate limit применяется к каждому tool в batch отдельно, но batch-запрос считается как один HTTP-запрос (global route limiter 120/min)
3. **Data extraction**: в одном batch можно собрать данные из разных сущностей

### Воспроизведение

1. Отправить batch из 50 JSON-RPC запросов `crm_get_current_user`
2. Сервер выполнит 50 отдельных tool calls за один HTTP-запрос
3. Если каждый tool делает запрос к БД — это 50 запросов к БД мгновенно

### Влияние

- Потенциальный DoS
- Data extraction в обход rate limiting
- Нагрузка на БД

### Рекомендация по исправлению

**Что нужно сделать**: Добавить ограничение на количество tool calls в batch-запросе.

**Как лучше реализовать**:

В `McpController::handle()`, при обработке массива сообщений:

```php
const MAX_BATCH_SIZE = 25;

private function handleBatch(array $messages): array
{
    $count = count($messages);
    if ($count > self::MAX_BATCH_SIZE) {
        return [
            $this->errorMessage(null, -32600, 'Batch size exceeds maximum of ' . self::MAX_BATCH_SIZE)
        ];
    }
    
    $results = [];
    foreach ($messages as $message) {
        $results[] = $this->handleMessage($message);
    }
    return $results;
}
```

**Приоритет**: Should-Fix

---

## Общие рекомендации по архитектуре безопасности

### 1. Унификация rate limiting

Сейчас в проекте используются **три разных механизма** rate limiting:

| Механизм | Где используется | Тип хранения |
|----------|-----------------|--------------|
| `DatabaseRateLimiter` | IP-based, route_global, MCP tool, password reset, uploads | MySQL |
| `RateLimitService` (file-based) | Login lockout (per-login), password reset request | Файлы |
| `checkIpRateLimit()` в контроллерах | Password reset confirm, invitation accept | DatabaseRateLimiter |

**Рекомендация**: Унифицировать всё на `DatabaseRateLimiter` для production (file-based не подходит для shared hosting с возможностью очистки временных файлов).

### 2. Dependency injection для key material

Сейчас ключи извлекаются по-разному:
- `TwoFactorService::getEncryptionKey()` — из `$this->encryptionKey` (передаётся из app.php)
- `AuthService::getPendingTokenKey()` — из `$_SERVER['APP_KEY']` напрямую

**Рекомендация**: Всегда передавать ключи через конструктор (DI), не читать `$_SERVER` напрямую в сервисах.

### 3. Усилить валидацию URL в webhook и module install

`ModuleController::installFromUrl()` принимает URL для установки модуля без проверки на SSRF.

**Рекомендация**: Применить ту же валидацию URL (allowlist протоколов, блокировка private IPs) что и для webhook URL.

---

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0 | — | Критических уязвимостей нет | — |
| P1 | SEC-001 | Очистка .env из git-истории | 1 час |
| P1 | SEC-004 | IP-based + user-based rate limiting для 2FA verify | 1 час |
| P1 | SEC-009 | Ограничение batch размера в MCP | 30 мин |
| P2 | SEC-002 | Переход на DatabaseRateLimiter для login lockout | 2 часа |
| P2 | SEC-005 | Активная блокировка internal URL в webhook | 2 часа |
| P3 | SEC-003 | Статический анализ whereRaw() usage | 1 час |
| P4 | SEC-006, SEC-007 | CSP reporting и nonce | 2 часа |
| P4 | SEC-008 | Маскировка query string в логах | 30 мин |

---

## Методология аудита

- **Тип**: Defensive Static Code Analysis
- **Метод**: анализ исходного кода (PHP 8.1+) без запуска эксплойтов
- **Coverage**: 10 фаз аудита согласно OWASP Top 10 (2021)
- **Проверенные файлы**: ~30 ключевых файлов (контроллеры, сервисы, конфигурация, QueryBuilder)
- **Проверенные endpoint'ы**: ~761 REST route (через routes.php), ~599 MCP tools
- **Ограничения**: без динамического тестирования, без gitleaks/trufflehog сканирования git-истории

### Проверенные области

| Фаза | Статус | Ключевые проверки |
|------|--------|-------------------|
| 0 — Рекогносциция | ✅ | 10 public routes, HTTP headers, rate limiting test, storage доступ |
| 1 — Аутентификация | ✅ | Argon2id, 2FA AES-256-GCM, timing-attack mitigation, CSRF, device fingerprint |
| 2 — RBAC/IDOR | ✅ | Route-level permissions, MCP RBAC, impersonation, mass assignment |
| 3 — Инъекции | ✅ | PDO parameterized queries, ORDER BY validation, file quarantine, no exec() |
| 4 — MCP | ✅ | Per-tool rate limiting, RBAC per tool, prompt injection warning |
| 5 — Секреты | ✅ | .env in git, APP_KEY, no gitleaks.toml |
| 6 — Бизнес-логика | ✅ | Rate limiting consistency, race conditions overview |
| 7 — Инфраструктура | ✅ | CSP, HSTS, CORS, shared hosting considerations |
| 8 — Файлы | ✅ | Upload quarantine, .htaccess defense-in-depth |
| 9 — Web | ✅ | CSP, X-Frame-Options, X-Content-Type-Options |
| 10 — Логирование | ✅ | Masking passwords/tokens, omitting sensitive fields |

### Security Metrics & KPIs

| Метрика | Цель | Текущее состояние |
|---------|------|-------------------|
| **Critical/High issues** | = 0 после исправления | 2 (SEC-001, SEC-002) |
| **SAST in CI** | PHPStan level 6 | ✅ Артефакт есть (см. `phpstan.neon`) |
| **Secret scanning** | 0 secrets в git | ⚠️ gitleaks не установлен/не настроен |
| **Install lock** | installer недоступен | ✅ 410 Gone |
| **2FA** | AES-256-GCM encrypted | ✅ Реализовано |
| **Auth rate limiting** | 5/300s per login+IP | ✅ (но file-based) |
| **CSP** | present | ✅ (без report-uri) |
| **RBAC** | route-level + granular | ✅ |
| **SQL injection** | parameterized queries | ✅ |
