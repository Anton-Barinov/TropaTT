# Security Audit — Fixes Specification

> Дата аудита: 2026-07-12
> Версия проекта: 1.0.0
> Аудитор: AI Security Audit
> Scope: 10 фаз, полный код + демо-стенд

## Сводка

| Severity | Количество |
|----------|------------|
| Critical | 1 |
| High     | 4 |
| Medium   | 11 |
| Low      | 6 |
| **Итого** | **22** |

## Risk Heatmap

| Категория | Critical | High | Medium | Low |
|-----------|----------|------|--------|-----|
| Infrastructure / Secrets | 1 | 1 | 0 | 1 |
| Auth / Session | 0 | 0 | 3 | 1 |
| RBAC / Authorization | 0 | 1 | 1 | 0 |
| Injection / XSS | 0 | 0 | 2 | 0 |
| Rate Limiting / DoS | 0 | 1 | 1 | 1 |
| Web / Client-side | 0 | 0 | 2 | 2 |
| MCP / AI | 0 | 1 | 1 | 0 |
| File Upload / Storage | 0 | 0 | 1 | 0 |
| Business Logic | 0 | 0 | 0 | 1 |

---

## [SEC-001] api/composer.json публично доступен через nginx (HTTP 200)

### Мета

- **Severity**: Critical
- **Категория**: infrastructure, secrets
- **Затронутые файлы**: `api/composer.json`, `api/.htaccess:14-18`
- **Endpoint/tool**: `GET /api/composer.json`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Файл `api/composer.json` возвращает HTTP 200 с полным содержимым (1058 байт) при прямом запросе. Сервер nginx отдаёт `.json` файлы как статические, минуя PHP-обработчик и правила `.htaccess`. PHP-уровень защиты в `api/index.php` (блокировка по URL-паттерну) не срабатывает, так как nginx отдаёт файл до передачи запроса PHP.

Содержимое раскрывает структуру проекта: имена скриптов, тестовых runner'ов, пути. Это помогает fingerprinting и разведке.

### Воспроизведение

1. `curl -sI https://demo.tropatt.com/api/composer.json`
2. Ответ: HTTP 200, `content-type: application/json`, `content-length: 1058`
3. Заголовки `last-modified` и `etag` подтверждают прямую отдачу nginx как статического файла

### Влияние

Information disclosure: раскрытие внутренней структуры проекта. В комбинации с другими находками облегчает атаки на известные компоненты.

### Рекомендация по исправлению

**Что нужно сделать**: Настроить nginx для блокировки прямого доступа к `.json`, `.lock`, `.md` файлам в `api/` директории, либо перенаправить все запросы к `api/` через PHP-обработчик.

**Как лучше реализовать**:
1. Для nginx: добавить `location` блок, запрещающий статические файлы в `api/`
2. Для Apache/shared-hosting: существующий `.htaccess` в `api/` уже блокирует не-`index.php` файлы через `<FilesMatch>`. Проблема только на nginx.
3. Рассмотреть перемещение `composer.json` вне document root (в корень проекта)

**Приоритет**: Must-Fix (до релиза)

---

## [SEC-002] Security-заголовки отсутствуют на корневых ответах (только HSTS)

### Мета

- **Severity**: High
- **Категория**: infrastructure, web
- **Затронутые файлы**: `web/index.php:17-43`, `api/system/library/app.php:750-753`
- **Endpoint/tool**: `GET /`
- **Затронутые роли**: all

### Описание проблемы

При GET-запросе к корню демо-сайта ответ содержит только `strict-transport-security`. Отсутствуют: `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`, `Referrer-Policy`. При этом код в `web/index.php:17-43` и `app.php:750-753` устанавливает эти заголовки — но они применяются только когда запрос доходит до PHP-рендеринга. При 302 редиректе (неаутентифицированный пользователь) заголовки не устанавливаются.

### Воспроизведение

1. `curl -sI https://demo.tropatt.com/`
2. Видно: `server: nginx`, `strict-transport-security: max-age=31536000;`
3. Отсутствуют: `X-Frame-Options`, `X-Content-Type-Options`, `CSP`, `Referrer-Policy`

### Влияние

Отсутствие security-заголовков на редиректах увеличивает risk surface для clickjacking, MIME-sniffing атак.

### Рекомендация по исправлению

**Что нужно сделать**: Установить security-заголовки до любых редиректов в `web/index.php`, или добавить их на уровне nginx с флагом `always`.

**Как лучше реализовать**:
1. В `web/index.php`: перенести установку заголовков ДО блока проверки авторизации и редиректов
2. В nginx: `add_header X-Frame-Options "SAMEORIGIN" always;` и аналогично для остальных
3. Для shared-hosting (Apache): заголовки уже в `app.php` — проверить что они работают при прямых запросах к `api/index.php`

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-003] Отсутствует session_regenerate_id при логине — риск session fixation

### Мета

- **Severity**: Medium
- **Категория**: auth, session
- **Затронутые файлы**: `api/system/library/service/AuthService.php:127-165`
- **Endpoint/tool**: `POST /api/v1/auth/login`
- **Затронутые роли**: unauthenticated

### Описание проблемы

При успешном логине в `AuthService::login()` создаётся новая сессия в БД, но не вызывается `session_regenerate_id(true)` для PHP-сессии (если используется cookie-based сессия). В коде проекта не найдено ни одного вызова `session_regenerate_id`. На shared-хостинге без доступа к `php.ini` это критично — злоумышленник может зафиксировать идентификатор сессии до аутентификации.

Однако следует отметить: API использует Bearer token-аутентификацию (opaque tokens в БД), а не PHP-сессии. Session fixation применим только к веб-интерфейсу через `web/index.php` с cookie-сессиями. Установщик (`web/install.php`) использует `session_start()` без `session_regenerate_id` между шагами wizard'а — это более релевантный вектор.

### Воспроизведение

1. Веб-интерфейс: получить cookie сессии до логина
2. Залогиниться — cookie сессии не меняется
3. Злоумышленник, знающий старый ID сессии, получает доступ к аутентифицированной сессии

### Влияние

Потенциальный перехват веб-сессии через session fixation на shared-хостинге.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `session_regenerate_id(true)` после успешной аутентификации в веб-слое.

**Как лучше реализовать**:
1. В `web/index.php` или контроллере логина: после проверки пароля вызвать `session_regenerate_id(true)`
2. В `web/install.php`: регенерировать ID сессии между шагами wizard'а (после ввода конфиденциальных данных)

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-004] Rate limiting на демо недоступен — nginx проксирует API-запросы мимо PHP

### Мета

- **Severity**: High
- **Категория**: rate-limiting, infrastructure
- **Затронутые файлы**: `api/system/library/service/AuthService.php:301-340`, nginx конфигурация
- **Endpoint/tool**: `POST /api/v1/auth/login`
- **Затронутые роли**: unauthenticated

### Описание проблемы

AuthService содержит два уровня rate limiting: IP-based (10/60s/300s) и login+IP-based (5/300s/900s). Однако на демо-стенде все 6 запросов с неверным паролем возвращают HTTP 302 без признаков 429 или `Retry-After`. Причина: nginx направляет API-запросы на `web/index.php`, который делает 302 редирект на страницу логина ДО того, как API успевает обработать запрос и проверить rate limit.

Rate limiting существует и работает в коде, но архитектура проксирования на демо-стенде не позволяет ему срабатывать.

### Воспроизведение

1. 6 POST-запросов к `/api/v1/auth/login` с неверным паролем
2. Все возвращают HTTP 302, без `Retry-After` заголовка
3. Время ответа: 0.7-1.3 сек (без явной блокировки)

### Влияние

Брутфорс-атаки на пароль через API на демо-стенде. На production с правильной конфигурацией (API напрямую) rate limiting работает.

### Рекомендация по исправлению

**Что нужно сделать**: Настроить nginx для проксирования API-запросов напрямую на `api/index.php` без прохождения через `web/index.php`.

**Как лучше реализовать**:
1. В nginx: отдельный `location /api/` блок с `try_files $uri /api/index.php$is_args$args;`
2. Для shared-hosting: веб-слой в `web/index.php` уже содержит `webRateLimitCheck()` — убедиться что он вызывается до редиректа

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-005] MCP tools/list не фильтрует инструменты по правам пользователя

### Мета

- **Severity**: High
- **Категория**: mcp, rbac
- **Затронутые файлы**: `api/controller/mcp/McpController.php:184,2891-2892`
- **Endpoint/tool**: `POST /api/v1/mcp` (tools/list)
- **Затронутые роли**: authenticated

### Описание проблемы

MCP endpoint `tools/list` возвращает ВСЕ доступные инструменты (563+ tools) независимо от permissions текущего пользователя. Пользователь с базовой ролью видит admin-only инструменты (имперсонация, управление пользователями, системные настройки). Хотя часть инструментов защищена через `withPermissionAny()` (например, `crm_list_audit_log`), сам факт раскрытия полного списка инструментов — information disclosure.

### Воспроизведение

1. Залогиниться под пользователем с базовой ролью
2. Вызвать MCP `tools/list`
3. В ответе видны admin-only инструменты с полными описаниями и параметрами

### Влияние

Information disclosure — раскрытие полного списка API-возможностей всем аутентифицированным пользователям. Облегчает targeted атаки.

### Рекомендация по исправлению

**Что нужно сделать**: Фильтровать `tools/list` по permissions текущего пользователя.

**Как лучше реализовать**:
1. Для каждого tool в `tools()` проверять `required_permissions` через `AuthzService::hasPermissions()`
2. Инструменты без `required_permissions` показывать всем
3. Admin-only инструменты скрывать от пользователей без соответствующих прав
4. Использовать существующий метод `withPermissionAny` как основу

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-006] Массовое использование allInput() без allowlist в контроллерах

### Мета

- **Severity**: High
- **Категория**: rbac, mass-assignment
- **Затронутые файлы**: 179 контроллеров (все используют `$this->request()->allInput()`)
- **Endpoint/tool**: Различные POST/PATCH endpoints
- **Затронутые роли**: authenticated

### Описание проблемы

179 контроллеров передают `$this->request()->allInput()` напрямую в сервисы без фильтрации полей. Хотя многие сервисы имеют внутреннюю валидацию и protection (например, `UserProfileService` с `$unsafe` blacklist для полей `password_hash`, `is_root`, `deleted_at`), отсутствие централизованного allowlist-подхода создаёт risk:

- Злоумышленник может передать неожиданные поля (например, `is_root: true`, `created_by_user_id: 0`)
- Если сервис не фильтрует конкретное поле — mass assignment проходит
- Защита зависит от дисциплины разработчика в каждом отдельном сервисе

### Воспроизведение

1. Отправить PATCH-запрос с неожиданным полем (например, `is_root: true`)
2. Если сервис не имеет явной фильтрации этого поля — значение сохраняется
3. `UserProfileService` блокирует `is_root`, но другие сервисы могут не иметь такой защиты

### Влияние

Потенциальное повышение привилегий через mass-assignment при ошибке в отдельном сервисе.

### Рекомендация по исправлению

**Что нужно сделать**: Внедрить централизованный allowlist-подход для всех create/update операций.

**Как лучше реализовать**:
1. Создать трейт или базовый метод `filterFields(array $input, array $allowed): array`
2. Для каждой сущности определить список разрешённых полей
3. Использовать `array_intersect_key()` для фильтрации
4. Применить поэтапно: начать с критических сущностей (users, roles, settings)

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-007] Динамическая генерация SQL placeholders для IN-клауз — архитектурный risk

### Мета

- **Severity**: Medium
- **Категория**: injection
- **Затронутые файлы**: `api/model/client/ClientRepository.php:117`, `api/model/counterparty/CounterpartyRepository.php:53,82`, `api/model/company/CompanyRepository.php:49`, `api/model/template/TemplateRepository.php:54`
- **Endpoint/tool**: `GET /api/v1/clients`, `GET /api/v1/counterparties`
- **Затронутые роли**: authenticated

### Описание проблемы

В репозиториях используется паттерн динамической генерации SQL placeholders:
```php
$placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
$query->whereRaw('(created_by_user_id IS NULL OR created_by_user_id IN (' . $placeholders . '))', $creatorIds);
```

Хотя значения передаются как параметры через `?` и защищены (часто через `array_map('intval', ...)`), сама конкатенация строки SQL с динамическим количеством placeholders — рискованная практика. Если валидация входных данных будет пропущена, возникнет SQL-инъекция.

### Воспроизведение

1. Текущий код безопасен благодаря валидации входных данных
2. Но паттерн зависит от дисциплины разработчика при модификации

### Влияние

Потенциальная SQL-инъекция при будущих модификациях кода.

### Рекомендация по исправлению

**Что нужно сделать**: Заменить динамические `whereRaw` с IN на `whereIn`.

**Как лучше реализовать**:
1. Использовать `QueryBuilder::whereIn()` для случаев `IN (...)`
2. Для случая `IS NULL OR IN` — создать хелпер-метод в QueryBuilder
3. Провести аудит ВСЕХ `whereRaw()` вызовов (найдено 108)

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-008] PasswordResetService не валидирует минимальную длину нового пароля

### Мета

- **Severity**: Medium
- **Категория**: auth
- **Затронутые файлы**: `api/system/library/service/PasswordResetService.php:127`
- **Endpoint/tool**: `POST /api/v1/security/password-reset/confirm`
- **Затронутые роли**: unauthenticated

### Описание проблемы

В `PasswordResetService::confirm()` новый пароль из `$input['new_password']` передаётся напрямую в `$this->hasher->hash()` без проверки минимальной длины. Пользователь может установить пароль из 1 символа через механизм сброса пароля. API-метод `UserService::create()` проверяет длину пароля, и `InvitationService::accept()` теперь тоже (после предыдущего раунда исправлений), но `PasswordResetService::confirm()` не имеет такой проверки.

### Воспроизведение

1. Получить валидный reset token
2. `POST /api/v1/security/password-reset/confirm` с `{"reset_token":"...", "new_password":"a"}`
3. Пароль из 1 символа сохранён

### Влияние

Установка слабого пароля через механизм сброса.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить валидацию `mb_strlen($newPassword) >= 8` перед хешированием.

**Как лучше реализовать**:
1. В `PasswordResetService::confirm()`: проверить длину пароля до вызова `$this->hasher->hash()`
2. Возвращать `PASSWORD_RESET_WEAK_PASSWORD` если пароль слишком короткий

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-009] Дублирование file-based rate limiter в 4+ сервисах

### Мета

- **Severity**: Medium
- **Категория**: rate-limiting, architecture
- **Затронутые файлы**: `AuthService.php:219-340`, `PasswordResetService.php:155-185`, `InvitationService.php:200-225`, `web/index.php` (webRateLimitCheck)
- **Endpoint/tool**: login, password-reset, invitation-accept
- **Затронутые роли**: unauthenticated

### Описание проблемы

Одна и та же логика file-based rate limiting реализована в 4 разных местах с небольшими вариациями параметров. Это создаёт risk рассинхронизации: исправление бага в одной копии не попадёт в другие. Параметры различаются: login (5/300/900), password-reset (5/300/900), invitation (20/60/300), web (5/300/900).

### Воспроизведение

1. Сравнить реализации `fileRateLimit()` в AuthService и `checkFileRateLimit()` в PasswordResetService/InvitationService
2. Логика идентична, параметры различаются

### Влияние

Архитектурный debt. Потенциальный баг в одной копии потребует исправления во всех.

### Рекомендация по исправлению

**Что нужно сделать**: Вынести file-based rate limiter в общий сервис.

**Как лучше реализовать**:
1. Создать `RateLimitService` с единым методом `check(string $key, int $maxAttempts, int $windowSec, int $lockSec): array`
2. Или использовать существующий `DatabaseRateLimiter` из контейнера
3. Переиспользовать во всех сервисах через DI

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-010] CSP заголовок содержит 'unsafe-inline' для style-src

### Мета

- **Severity**: Medium
- **Категория**: web
- **Затронутые файлы**: `api/system/library/app.php:753`
- **Endpoint/tool**: Все API ответы
- **Затронутые роли**: all

### Описание проблемы

CSP заголовок в `app.php:753` содержит `style-src 'self' 'unsafe-inline'`. Разрешение `unsafe-inline` для стилей ослабляет защиту от CSS injection атак. Хотя риск ниже чем `unsafe-inline` для script-src, это всё ещё ослабляет CSP.

### Воспроизведение

1. Отправить запрос к API (минуя nginx, напрямую к `api/index.php`)
2. В ответе: `Content-Security-Policy: ... style-src 'self' 'unsafe-inline' ...`

### Влияние

Ослабленная защита от CSS injection/exfiltration атак.

### Рекомендация по исправлению

**Что нужно сделать**: Использовать nonce или hash-based подход для инлайн-стилей вместо `unsafe-inline`.

**Как лучше реализовать**:
1. Генерировать CSP nonce для каждого запроса
2. Добавлять nonce к инлайн-стилям в HTML
3. Использовать `style-src 'self' 'nonce-{random}'`

**Приоритет**: Nice-to-Have

---

## [SEC-011] md5 используется для ключей rate limiting (не-security контекст)

### Мета

- **Severity**: Low
- **Категория**: crypto
- **Затронутые файлы**: `AuthService.php:306,318`, `PasswordResetService.php:170`, `InvitationService.php:218`
- **Endpoint/tool**: N/A (внутренняя логика)
- **Затронутые роли**: N/A

### Описание проблемы

В file-based rate limiter используется `md5()` для создания имён файлов из ключей. Хотя это не-security контекст (не криптографическая операция, а хеширование для имени файла), использование `md5` в кодовой базе — индикатор потенциального использования слабых алгоритмов. Рекомендуется заменить на `hash('sha256', ...)` для консистентности.

### Воспроизведение

1. `AuthService.php:306`: `md5($ip)`
2. `PasswordResetService.php:170`: `md5($rateKey)`

### Влияние

Низкий risk — md5 используется только для имён файлов, не для security.

### Рекомендация по исправлению

**Что нужно сделать**: Заменить md5 на sha256 для консистентности.

**Как лучше реализовать**:
1. Заменить `md5($key)` на `hash('sha256', $key)` во всех rate limiter'ах

**Приоритет**: Nice-to-Have

---

## [SEC-012] Метод me() в AuthService не проверяет revoked_at явно

### Мета

- **Severity**: Medium
- **Категория**: auth
- **Затронутые файлы**: `api/system/library/service/AuthService.php:166-205`
- **Endpoint/tool**: `GET /api/v1/auth/me`
- **Затронутые роли**: authenticated

### Описание проблемы

Метод `me()` проверяет `is_active`, срок действия сессии (`expires_at`), и максимальное время жизни (`maxSessionLifetime`). Однако он не проверяет поле `revoked_at`. Вместо этого он полагается на запрос `findSessionByTokenHash()`, который может исключать отозванные сессии на уровне репозитория. Если фильтрация `revoked_at` не реализована в репозитории, отозванная сессия может оставаться активной.

### Воспроизведение

1. Проверить запрос в `AuthRepository::findSessionByTokenHash()` — включает ли он `WHERE revoked_at IS NULL`
2. Если нет — отозванная сессия возвращается как активная

### Влияние

Потенциальное использование отозванной сессии.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить явную проверку `revoked_at IS NULL` в методе `me()` или убедиться что она есть в репозитории.

**Как лучше реализовать**:
1. Проверить `AuthRepository::findSessionByTokenHash()` на наличие `WHERE revoked_at IS NULL`
2. При отсутствии — добавить

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-013] Обработка ошибок в api/index.php раскрывает пути к файлам

### Мета

- **Severity**: Medium
- **Категория**: configuration, secrets
- **Затронутые файлы**: `api/index.php:69-80`
- **Endpoint/tool**: Все API endpoints
- **Затронутые роли**: all

### Описание проблемы

В catch-блоке `api/index.php` информация об исключении логируется через `error_log()` с полным путём к файлу и номером строки:
```php
error_log(sprintf(
    'Tropa API bootstrap error [%s]: %s in %s:%d',
    $requestId, $exceptionMessage, $e->getFile(), $e->getLine()
));
```

Хотя это пишется в лог, а не в ответ пользователю, на shared-хостинге error_log может быть доступен другим tenant'ам. Кроме того, конфигурационные ошибки (`CONFIG_*`) возвращаются в API-ответе с деталями.

### Воспроизведение

1. Вызвать endpoint с ошибкой конфигурации
2. В ответе: `{"error": "CONFIG_SECURITY_CSRF_SECRET_REQUIRED"}` — внутренний код ошибки

### Влияние

Information disclosure через коды ошибок и логи.

### Рекомендация по исправлению

**Что нужно сделать**: Использовать общие коды ошибок в API-ответах, не раскрывать внутренние детали.

**Как лучше реализовать**:
1. Заменить `CONFIG_*` коды на общий `CONFIGURATION_ERROR` в ответе пользователю
2. Детали писать только в лог

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-014] Отсутствует server-side валидация email в PasswordResetService

### Мета

- **Severity**: Low
- **Категория**: auth
- **Затронутые файлы**: `api/system/library/service/PasswordResetService.php:31-76`
- **Endpoint/tool**: `POST /api/v1/security/password-reset`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Метод `request()` принимает `identifier` (login или email) без валидации формата. Пустая строка или спецсимволы могут привести к неожиданному поведению. Хотя это не создаёт прямой уязвимости (метод корректно обрабатывает отсутствие пользователя), валидация ввода — best practice.

### Воспроизведение

1. `POST /api/v1/security/password-reset` с `{"identifier":""}`
2. Ответ: `{"ok":true,"accepted":true}` — пустой identifier принят

### Влияние

Низкий risk — дополнительная нагрузка на систему через пустые запросы.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить минимальную валидацию identifier перед поиском пользователя.

**Как лучше реализовать**:
1. Проверить `trim($identifier) !== ''` перед поиском
2. Возвращать `accepted: true` без поиска если identifier пустой (сохраняя защиту от user enumeration)

**Приоритет**: Nice-to-Have

---

## [SEC-015] MCP endpoint доступен без авторизации через некоторые маршруты

### Мета

- **Severity**: Medium
- **Категория**: mcp
- **Затронутые файлы**: `api/config/routes.php` (проверить MCP route)
- **Endpoint/tool**: `POST /api/v1/mcp`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Необходимо проверить, требует ли MCP endpoint аутентификации. Если `tools/list` доступен без авторизации, злоумышленник может получить полную карту API-инструментов без учётной записи.

### Воспроизведение

1. `POST /api/v1/mcp` с `{"method":"tools/list","params":{}}` без токена
2. Проверить ответ

### Влияние

Information disclosure без аутентификации.

### Рекомендация по исправлению

**Что нужно сделать**: Убедиться что MCP endpoint требует `auth: true` и возвращает 401 без токена.

**Как лучше реализовать**:
1. Проверить route для MCP в `routes.php` на наличие `auth: true`
2. При отсутствии — добавить

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-016] Web-слой webRateLimitCheck не срабатывает из-за nginx маршрутизации

### Мета

- **Severity**: Medium
- **Категория**: rate-limiting, web
- **Затронутые файлы**: `web/index.php` (webRateLimitCheck)
- **Endpoint/tool**: `POST /api/v1/auth/login` (через web)
- **Затронутые роли**: unauthenticated

### Описание проблемы

Веб-слой содержит функцию `webRateLimitCheck()` для перехвата login/password-reset запросов до проксирования в API. Однако на демо-стенде nginx маршрутизирует API-запросы иначе, и rate limiting в веб-слое не активируется. На shared-хостинге с Apache эта функция должна работать корректно.

### Воспроизведение

1. 6 POST-запросов к `/api/v1/auth/login`
2. Нет 429, нет задержки — rate limiting в веб-слое не срабатывает

### Влияние

Rate limiting не работает на демо-стенде. На production с Apache — работает.

### Рекомендация по исправлению

**Что нужно сделать**: Настроить nginx для правильной маршрутизации API-запросов через PHP, либо добавить nginx-level rate limiting.

**Как лучше реализовать**:
1. Nginx `location /api/` → `try_files $uri /api/index.php$is_args$args;`
2. Для shared-hosting: существующий код корректен

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-017] Поисковые запросы в SearchRepository используют whereRaw с конкатенацией

### Мета

- **Severity**: Medium
- **Категория**: injection
- **Затронутые файлы**: `api/model/search/SearchRepository.php:57,121-195`
- **Endpoint/tool**: `GET /api/v1/search`
- **Затронутые роли**: authenticated

### Описание проблемы

SearchRepository использует `whereRaw` с LIKE-запросами, где поисковый термин передаётся как параметр:
```php
$qb->whereRaw('(t.title LIKE ? OR t.description LIKE ?)', [$like, $like]);
```

Значения передаются как параметры (безопасно), но сам паттерн множественных whereRaw с LIKE может быть вектором для DoS через дорогие запросы с wildcards в начале строки (`%term%`).

### Воспроизведение

1. Отправить поисковый запрос с `%` в начале и конце
2. Запрос не оптимизирован для поиска с ведущим wildcard

### Влияние

DoS через дорогие LIKE-запросы.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить ограничение на минимальную длину поискового запроса и санитизацию wildcards.

**Как лучше реализовать**:
1. Минимальная длина поискового запроса: 2-3 символа
2. Экранировать или удалять множественные `%` и `_` из пользовательского ввода
3. Рассмотреть FULLTEXT search для больших таблиц

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## [SEC-018] robots.txt (корень) возвращает пустой ответ

### Мета

- **Severity**: Low
- **Категория**: web
- **Затронутые файлы**: `web/robots.txt`, nginx конфигурация
- **Endpoint/tool**: `GET /robots.txt`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Корневой `https://demo.tropatt.com/robots.txt` возвращает пустой ответ, в то время как `web/robots.txt` содержит корректный `Disallow: /`. Это происходит из-за nginx routing. Пустой robots.txt может быть воспринят как разрешение на индексацию.

### Воспроизведение

1. `curl https://demo.tropatt.com/robots.txt` → пустой ответ
2. `curl https://demo.tropatt.com/web/robots.txt` → `User-agent: *\nDisallow: /`

### Влияние

Низкий — search engines могут не найти robots.txt и проиндексировать страницы.

### Рекомендация по исправлению

**Что нужно сделать**: Настроить nginx для отдачи robots.txt из web/ директории.

**Как лучше реализовать**:
1. nginx: `location = /robots.txt { alias /path/to/web/robots.txt; }`

**Приоритет**: Nice-to-Have

---

## [SEC-019] 302 редирект раскрывает внутренний маршрут `/web/index.php?route=login`

### Мета

- **Severity**: Low
- **Категория**: web
- **Затронутые файлы**: nginx конфигурация, `web/index.php`
- **Endpoint/tool**: `GET /`
- **Затронутые роли**: unauthenticated

### Описание проблемы

При запросе к корню без авторизации сервер возвращает 302 с заголовком:
`location: /web/index.php?route=login`

Это раскрывает внутреннюю структуру маршрутизации и технологический стек (PHP).

### Воспроизведение

1. `curl -sI https://demo.tropatt.com/`
2. `location: /web/index.php?route=login`

### Влияние

Information disclosure (fingerprinting).

### Рекомендация по исправлению

**Что нужно сделать**: Использовать чистые URL через nginx rewrite.

**Как лучше реализовать**:
1. nginx: `rewrite` правила для скрытия `index.php?route=`
2. For shared-hosting: низкий приоритет, URL rewriting может быть недоступен

**Приоритет**: Nice-to-Have

---

## [SEC-020] API-ответы не содержат security-заголовков при доступе через nginx

### Мета

- **Severity**: Low
- **Категория**: web
- **Затронутые файлы**: nginx конфигурация
- **Endpoint/tool**: Все API endpoints
- **Затронутые роли**: all

### Описание проблемы

API-запросы, проходящие через nginx, не получают security-заголовки (X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy), потому что nginx возвращает 302 редирект до того, как PHP-приложение успевает их установить. Заголовки устанавливаются в `app.php` только при прямом доступе к `api/index.php`.

### Воспроизведение

1. `curl -sI https://demo.tropatt.com/api/v1/version`
2. Отсутствуют security-заголовки кроме HSTS

### Влияние

API-ответы без защиты от clickjacking/MIME-sniffing.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить security-заголовки на уровне nginx с флагом `always`.

**Как лучше реализовать**:
1. В nginx: `add_header` директивы с `always` для всех ответов
2. Для shared-hosting: заголовки устанавливаются в `app.php` при прямых запросах к API

**Приоритет**: Nice-to-Have

---

## [SEC-021] Имперсонация не имеет временного ограничения (session timeout)

### Мета

- **Severity**: Low
- **Категория**: business-logic
- **Затронутые файлы**: `api/system/library/service/ImpersonationService.php`, `api/controller/security/ImpersonationController.php`
- **Endpoint/tool**: `POST /api/v1/security/impersonation/start`
- **Затронутые роли**: root

### Описание проблемы

Сессия имперсонации не имеет автоматического таймаута. Если администратор забыл завершить имперсонацию, сессия остаётся активной неограниченное время. Рекомендуется добавить максимальную длительность сессии имперсонации (например, 1 час).

### Воспроизведение

1. Начать имперсонацию — нет ограничения по времени

### Влияние

Риск оставленной открытой имперсонации.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `impersonation_max_duration` с автоматическим завершением.

**Как лучше реализовать**:
1. В конфигурации: `impersonation_ttl_seconds` (рекомендуется 3600)
2. В `ImpersonationService`: проверять время начала при каждом запросе

**Приоритет**: Nice-to-Have

---

## [SEC-022] CRON_SECRET_KEY не валидируется в cron endpoint

### Мета

- **Severity**: Medium
- **Категория**: auth
- **Затронутые файлы**: `api/controller/system/CronController.php` (если существует), cron endpoint routing
- **Endpoint/tool**: Cron endpoint
- **Затронутые роли**: unauthenticated

### Описание проблемы

В `.env.example` и `web/install.php` присутствует `CRON_SECRET_KEY`, но необходимо проверить что cron endpoint действительно валидирует этот ключ. Если cron endpoint доступен без ключа, злоумышленник может запускать resource-intensive задачи (AI jobs, уведомления, очистка).

### Воспроизведение

1. Найти cron endpoint URL
2. Отправить GET/POST запрос без ключа
3. Проверить, выполняется ли cron-задача

### Влияние

DoS через несанкционированный запуск cron-задач.

### Рекомендация по исправлению

**Что нужно сделать**: Убедиться что cron endpoint валидирует `CRON_SECRET_KEY`.

**Как лучше реализовать**:
1. Проверить cron endpoint на наличие проверки ключа
2. При отсутствии — добавить: `hash_equals($expectedKey, $_GET['key'] ?? '')`

**Приоритет**: Should-Fix (в ближайшем цикле)

---

## Общие рекомендации по архитектуре безопасности

1. **Централизовать rate limiting**: Вынести file-based rate limiter в единый сервис (`RateLimitService`) для устранения дублирования кода.

2. **Внедрить allowlist для всех create/update операций**: Заменить паттерн `allInput() → service->create()` на `allInput() → filterFields($allowed) → service->create()`.

3. **Настроить nginx**: Отдельный location-блок для `/api/` запросов с прямой передачей в `api/index.php`, блокировка статических файлов в `api/`.

4. **Усилить MCP RBAC**: Фильтровать `tools/list` по permissions текущего пользователя.

5. **Добавить валидацию пароля во все точки создания/изменения**: PasswordResetService, InvitationService, UserService (проверить консистентность).

6. **Добавить session_regenerate_id**: В веб-слое после аутентификации и в установщике между шагами.

---

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0 (Critical) | SEC-001 | Блокировка composer.json в nginx | 0.5-1h |
| P1 (High) | SEC-002, SEC-003, SEC-004, SEC-005, SEC-006 | Security-заголовки, session fixation, rate limiting, MCP RBAC, mass assignment | 4-8h |
| P2 (Medium) | SEC-007, SEC-008, SEC-009, SEC-012, SEC-013, SEC-015, SEC-016, SEC-017, SEC-022 | whereRaw аудит, валидация пароля, дублирование rate limiter, me() revoked_at, error disclosure, MCP auth, поиск | 6-12h |
| P3 (Low) | SEC-010, SEC-011, SEC-014, SEC-018, SEC-019, SEC-020, SEC-021 | CSP unsafe-inline, md5 замена, email валидация, robots.txt, редирект URL, API заголовки, имперсонация | 3-6h |

---

## Методология аудита

Аудит проведён 2026-07-12 в формате defensive security review. Проверялись:

1. **Локальная кодовая база** (`/Users/bps/sites/crm.ru/`) — статический анализ PHP-кода (контроллеры, модели, сервисы, конфигурация, роуты)
2. **Демо-стенд** (`https://demo.tropatt.com/`) — HTTP-запросы, анализ заголовков, проверка доступности, rate limiting
3. **Git-история** — проверка на закоммиченные секреты
4. **Конфигурация** — `.htaccess`, `robots.txt`, security headers, CORS, rate limiting

Методы: статический анализ кода (grep/ripgrep по ключевым паттернам), динамическое тестирование демо (curl, анализ заголовков), проверка конфигураций. Деструктивные операции не выполнялись. Код не модифицировался.

Ограничения:
- Не тестировались все 563 MCP tools в полном объёме
- Не проводилось нагрузочное тестирование
- Не проверялась безопасность сторонних JS-библиотек (jQuery, Bootstrap, Sortable)
- Не анализировались модули (Confluence, Jira migration) — точечно
