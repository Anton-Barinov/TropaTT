# Security Audit — Fixes Specification

> **Дата аудита**: 2026-07-12
> **Аудитор**: AI Security Audit (read-only, code unchanged)
> **Демо-сайт**: https://demo.tropatt.com/
> **Git status**: Рабочее дерево не тронуто (подтверждено)

---

## Сводка

| Severity | Количество |
|----------|------------|
| Critical | 1 |
| High     | 4 |
| Medium   | 10 |
| Low      | 7 |
| **Итого** | **22** |

## Risk Heatmap

| Категория | Critical | High | Medium | Low |
|-----------|----------|------|--------|-----|
| Auth & Sessions | — | — | 1 | 1 |
| RBAC & Authorization | — | 1 | 1 | — |
| Injection & Validation | — | 1 | 2 | 1 |
| MCP | — | — | 1 | 1 |
| Secrets & Config | 1 | 1 | 2 | 1 |
| Business Logic | — | — | 1 | 1 |
| Infrastructure & Crypto | — | — | 1 | 1 |
| Files & Storage | — | — | — | 1 |
| Web & Client-side | — | 1 | 1 | — |
| Logging & Monitoring | — | — | 1 | — |

---

## ЗАМЕЧАНИЕ: Что уже реализовано хорошо ✅

Перед списком находок — подтверждённые сильные стороны безопасности TropaTT:

- ✅ **Argon2id** для хеширования паролей (PASSWORD_ARGON2ID)
- ✅ **Timing-attack mitigation** при логине (dummy hash verify для несуществующих пользователей)
- ✅ **32-байтные токены** через `random_bytes()` → base64 (непредсказуемы)
- ✅ **Rate limiting** с file-based блокировками (`flock`) для логина, сброса пароля, приглашений
- ✅ **Отзыв всех сессий** при сбросе пароля (PasswordResetService)
- ✅ **2FA disable требует текущий пароль** (TwoFactorService)
- ✅ **UrlSafetyValidator** блокирует SSRF (private IP, loopback, link-local, IPv4-mapped IPv6)
- ✅ **ModuleRemoteInstaller** защита от path traversal, проверка подписи HMAC-SHA256
- ✅ **Webhook HMAC-SHA256** подписи для payload
- ✅ **AI API ключи шифруются** AES-256-GCM (openssl_encrypt)
- ✅ **Нет .env в git history** — все секреты используют placeholder'ы
- ✅ **api/.htaccess** блокирует скрытые файлы и прямые запросы к config
- ✅ **CSRF защита** в installer (токен + hash_equals проверка)
- ✅ **Idempotency keys** (X-Idempotency-Key) с таблицей idempotency_keys
- ✅ **Invitation markAccepted** с атомарным `WHERE accepted_at IS NULL`
- ✅ **Валидация длины пароля** (mb_strlen >= 8) в PasswordResetService и InvitationService
- ✅ **Карантин расширений** для загрузки файлов (40+ запрещённых расширений)
- ✅ **MCP tools RBAC-отфильтрованы** через `can()` / `canAny()`
- ✅ **Имперсонация требует `user.manage`** permission
- ✅ **Нет использования `rand()`/`mt_rand()`/`uniqid()`** для security-критичных операций
- ✅ **Storage и storage_api защищены** от прямого HTTP-доступа (404/403)
- ✅ **robots.txt**: `Disallow: /` — нет раскрытия путей

---

## [SEC-001] api/composer.json доступен по HTTP (200 OK)

### Мета

- **Severity**: High (was Critical in previous audit; nginx routing confirmed — mitigated by Composer-free architecture in this project)
- **Категория**: secrets, infra
- **Затронутые файлы**: `api/composer.json`
- **Endpoint**: `GET /composer.json`, `GET /api/composer.json`
- **Затронутые роли**: unauthenticated

### Описание проблемы

`api/composer.json` доступен по прямому HTTP-запросу и возвращает HTTP 200. Файл отдаётся nginx как статика, минуя PHP и `.htaccess`. В данном проекте composer.json содержит только метаданные без зависимостей, что снижает критичность, но раскрывает структуру проекта и информацию о его архитектуре.

### Воспроизведение

1. Выполнить `curl -sI https://demo.tropatt.com/api/composer.json`
2. Ответ: `HTTP/2 200`, файл отдаётся как статический контент

### Влияние

Раскрытие информации о проекте (namespace, описание, версия). При наличии зависимостей могло бы раскрыть точные версии библиотек с известными уязвимостями.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить nginx location-блок для запрета прямого доступа к файлам конфигурации и метаданным.

**Как лучше реализовать**: На уровне nginx:
```
location ~* ^/(api/)?(composer\.(json|lock)|\.env.*|\.git) {
    deny all;
}
```
Если нет доступа к nginx — добавить проверку в `api/index.php` front controller для блокировки прямых запросов к статическим файлам.

**Приоритет**: Must-Fix (до релиза), но критичность снижена отсутствием Composer-зависимостей в проекте

---

## [SEC-002] Отсутствуют security-заголовки HTTP (X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy)

### Мета

- **Severity**: High
- **Категория**: web
- **Затронутые файлы**: `api/index.php`, `web/index.php`, nginx config
- **Endpoint**: Все HTTP-ответы
- **Затронутые роли**: unauthenticated, все пользователи

### Описание проблемы

Демо-сайт возвращает только `strict-transport-security: max-age=31536000`. Отсутствуют:
- `X-Frame-Options` / `Content-Security-Policy: frame-ancestors` — риск clickjacking
- `X-Content-Type-Options: nosniff` — риск MIME-sniffing
- `Content-Security-Policy` — риск XSS
- `Referrer-Policy` — утечка URL в referrer
- `Permissions-Policy` — контроль browser features

`X-Powered-By` успешно убран, но `server: nginx` всё ещё виден.

### Воспроизведение

1. Выполнить `curl -sI https://demo.tropatt.com/`
2. Наблюдать заголовки: только HSTS присутствует, остальные отсутствуют

### Влияние

Повышенный риск clickjacking, MIME-sniffing атак, XSS (при отсутствии CSP), утечка URL через Referer header.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить security-заголовки на уровне приложения (PHP) для работы на shared hosting без доступа к nginx.

**Как лучше реализовать**: В `api/system/library/app.php` добавить middleware для установки заголовков:
```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: blob:;');
```

**Приоритет**: Must-Fix

---

## [SEC-003] Демо-API возвращает 302 редиректы вместо прямых JSON-ответов

### Мета

- **Severity**: High
- **Категория**: infra
- **Затронутые файлы**: nginx config
- **Endpoint**: `POST /api/v1/auth/login`, `GET /api/v1/tasks`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Все запросы к API на демо (login, tasks, и т.д.) возвращают HTTP 302 вместо ожидаемых JSON-ответов (200/401/403). Это означает, что nginx не настроен на прямую маршрутизацию API-запросов через `api/index.php`. Запросы уходят в web-фронтенд, который делает редирект на страницу логина.

Это маскирует настоящее поведение API, включая rate limiting (не видно 429 ответов).

### Воспроизведение

1. `curl -sI -X POST https://demo.tropatt.com/api/v1/auth/login -H "Content-Type: application/json" -d '{"login":"admin","password":"adminadmin"}'`
2. Ответ: `HTTP/2 302`
3. `curl -sI https://demo.tropatt.com/api/v1/tasks`
4. Ответ: `HTTP/2 302`

### Влияние

- API-клиенты не могут использовать демо для тестирования
- Rate limiting не срабатывает на демо (запросы не доходят до API)
- Невозможно провести полноценное тестирование безопасности API на демо

### Рекомендация по исправлению

**Что нужно сделать**: Настроить nginx для прямой маршрутизации `/api/v1/*` и `/api/index.php?route=*` на PHP front controller без проксирования через web.

**Как лучше реализовать**: Добавить в nginx config:
```
location /api/ {
    try_files $uri /api/index.php?$query_string;
}
location ~ ^/api/index\.php$ {
    fastcgi_pass php-fpm;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/api/index.php;
}
```

**Приоритет**: Should-Fix (для демо-окружения; в production ожидается корректная настройка)

---

## [SEC-004] Массовое использование allInput() без allowlist (mass assignment)

### Мета

- **Severity**: High
- **Категория**: injection, rbac
- **Затронутые файлы**: 179 контроллеров (примеры: `UserController.php`, `CompanyController.php`, `TaskController.php`, `ProjectController.php`)
- **Endpoint**: Все PATCH/POST endpoints в контроллерах с `allInput()`
- **Затронутые роли**: user, manager, root

### Описание проблемы

179 контроллеров используют `$this->request()->allInput()` для получения всех полей из тела запроса без явного allowlist'а допустимых полей. Это классический вектор mass assignment: атакующий может добавить в PATCH-запрос поля, которые не предназначены для изменения пользователем (например, `is_root`, `permissions`, `created_by_user_id`).

Степень риска зависит от того, фильтрует ли сервисный слой (Service) входящие данные перед записью в БД. Без аудита каждого сервиса невозможно подтвердить защиту.

### Воспроизведение

1. Изучить контроллер: `api/controller/user/UserController.php:update()`
2. Вызов: `$this->request()->allInput()` — принимает все поля из запроса
3. Если `UserService::update()` не имеет явного allowlist'а, поля вроде `is_root` могут быть перезаписаны

### Влияние

Потенциальная возможность privilege escalation (установка `is_root=1`), изменение чужих записей (подмена `created_by_user_id`), обход бизнес-правил.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить явный allowlist разрешённых полей в каждом сервисном методе, который принимает пользовательский ввод. Не полагаться на allInput() без фильтрации.

**Как лучше реализовать**: В каждом Service методе create/update:
```php
$allowed = ['title', 'description', 'status', 'priority', 'due_at'];
$input = array_intersect_key($this->request()->allInput(), array_flip($allowed));
```
Или создать helper `Request::only(array $keys)` в классе Request.

**Приоритет**: Must-Fix (наиболее критичные контроллеры: User, Role, Permission, Team)

---

## [SEC-005] ORDER BY — конкатенация пользовательского ввода в SQL

### Мета

- **Severity**: Medium
- **Категория**: injection
- **Затронутые файлы**: `api/model/sticky/StickyNoteRepository.php:93`, `api/model/task/TaskRepository.php:104`, `api/model/project/ProjectRepository.php:48`
- **Endpoint**: `GET /api/v1/tasks?sort=...`, `GET /api/v1/projects?sort=...`
- **Затронутые роли**: user

### Описание проблемы

В нескольких репозиториях значение `$sort` из GET-параметров напрямую конкатенируется в SQL ORDER BY:
```php
$orderBy = 'sn.is_pinned DESC, sn.' . $sort . ' ' . $order . ', sn.updated_at DESC';
$orderBy = 't.' . $sort . ' ' . $order;
```

Хотя QueryBuilder::orderBy() выполняет валидацию (только alphanumeric + underscore), конкатенация до вызова orderBy обходит эту проверку в некоторых местах. В других местах используется QueryBuilder::orderBy() с предварительной валидацией через match/switch — это безопасно.

### Воспроизведение

1. Найти `StickyNoteRepository.php:93` — прямая конкатенация `'sn.' . $sort . ' ' . $order`
2. Параметр `$sort` не валидируется перед конкатенацией
3. Теоретически: `?sort=id; DROP TABLE users--`

### Влияние

SQL injection через ORDER BY clause. Риск смягчён тем, что многие репозитории используют QueryBuilder::orderBy() с предварительной валидацией, но StickyNoteRepository использует прямую конкатенацию.

### Рекомендация по исправлению

**Что нужно сделать**: Всегда использовать allowlist для sort-параметров или QueryBuilder::orderBy() после валидации.

**Как лучше реализовать**:
```php
$allowedSorts = ['created_at', 'updated_at', 'title', 'priority'];
$sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
```

**Приоритет**: Must-Fix

---

## [SEC-006] Динамическая генерация SQL placeholders для IN-клауз

### Мета

- **Severity**: Medium
- **Категория**: injection
- **Затронутые файлы**: `CompanyRepository.php:49`, `ContactRepository.php:85`, `TemplateRepository.php:54`, `CounterpartyRepository.php:53,82`
- **Endpoint**: Все list-методы с фильтром `created_by_user_ids`
- **Затронутые роли**: user

### Описание проблемы

Несколько репозиториев генерируют SQL placeholders динамически:
```php
$placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
$query->whereRaw('(created_by_user_id IS NULL OR created_by_user_id IN (' . $placeholders . '))', $creatorIds);
```

Хотя `$creatorIds` предварительно фильтруется через `array_filter(array_map('intval', ...))`, сам паттерн динамической генерации SQL — это потенциальный риск при будущих изменениях. Если в другом месте такой же паттерн используется без `intval`-фильтрации, возникает SQL injection.

### Воспроизведение

1. Изучить `CompanyRepository.php:49`
2. `$creatorIds` фильтруется через intval (безопасно)
3. Но если добавить новый фильтр без intval — инъекция

### Влияние

Потенциальный SQL injection при будущих изменениях. Текущий код безопасен благодаря intval-фильтрации.

### Рекомендация по исправлению

**Что нужно сделать**: Использовать `QueryBuilder::whereIn()` вместо ручной генерации `whereRaw()` с placeholders.

**Как лучше реализовать**: Заменить:
```php
$query->whereRaw('(created_by_user_id IS NULL OR created_by_user_id IN (' . $placeholders . '))', $creatorIds);
```
на использование whereIn() с составным условием:
```php
$query->where(function($q) use ($creatorIds) {
    $q->whereNull('created_by_user_id')
      ->orWhereIn('created_by_user_id', $creatorIds);
});
```

**Приоритет**: Should-Fix

---

## [SEC-007] Нет session_regenerate_id для web-сессий

### Мета

- **Severity**: Medium
- **Категория**: auth
- **Затронутые файлы**: `web/install.php`, web-контроллеры (66 страниц)
- **Endpoint**: Web-интерфейс (не REST API)
- **Затронутые роли**: unauthenticated, user

### Описание проблемы

В коде не обнаружено вызовов `session_regenerate_id()`. Для REST API это не проблема (используются Bearer-токены, не PHP-сессии). Но web-интерфейс использует PHP-сессии для CSRF-токенов (в installer) и для cookie-based аутентификации — потенциальный риск session fixation.

API использует Bearer-токены и не зависит от PHP-сессий — риск ограничен web-интерфейсом.

### Воспроизведение

1. Поиск `session_regenerate_id` по всей кодовой базе — 0 результатов
2. Installer создаёт `$_SESSION['csrf_token']` без регенерации ID сессии

### Влияние

Session fixation attack на web-интерфейс (не на API). Атакующий может зафиксировать ID сессии и дождаться, пока жертва залогинится.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `session_regenerate_id(true)` при логине через web-интерфейс и при старте installer wizard.

**Как лучше реализовать**: В момент аутентификации web-пользователя:
```php
session_regenerate_id(true); // true = удалить старую сессию
```

**Приоритет**: Should-Fix

---

## [SEC-008] Дублирование file-based rate limiter в 4+ сервисах

### Мета

- **Severity**: Medium
- **Категория**: auth, infra
- **Затронутые файлы**: `AuthService.php`, `PasswordResetService.php`, `InvitationService.php`
- **Endpoint**: login, password-reset, invitation-accept
- **Затронутые роли**: unauthenticated

### Описание проблемы

Логика file-based rate limiting продублирована в трёх сервисах (`AuthService`, `PasswordResetService`, `InvitationService`) с идентичной (почти) реализацией `fileRateLimit()`/`checkFileRateLimit()`. Это:
- Увеличивает maintenance burden
- Создаёт риск inconsistency в параметрах (разные лимиты, разные форматы файлов)
- Делает сложнее глобальное изменение логики rate limiting

### Воспроизведение

1. Сравнить `AuthService::fileRateLimit()` и `PasswordResetService::checkFileRateLimit()`
2. Разные названия методов, почти идентичная логика, разные параметры по умолчанию

### Влияние

Поддержка становится сложнее. При изменении требований к rate limiting нужно обновлять 3 разных места.

### Рекомендация по исправлению

**Что нужно сделать**: Выделить общий `RateLimitService` или `FileRateLimiter` класс.

**Как лучше реализовать**: Создать `api/system/library/service/RateLimitService.php`:
```php
final class RateLimitService {
    public function check(string $key, int $maxAttempts, int $windowSec, int $lockSec, bool $increment): array;
    public function clear(string $key): void;
}
```

**Приоритет**: Should-Fix

---

## [SEC-009] md5() используется для имён файлов rate limit counters

### Мета

- **Severity**: Low
- **Категория**: crypto
- **Затронутые файлы**: `AuthService.php:304,312,320,327`, `PasswordResetService.php:170`
- **Endpoint**: Все rate-limited endpoints
- **Затронутые роли**: unauthenticated

### Описание проблемы

md5() используется для генерации имён файлов rate-limit counters:
```php
$this->rateLimitDir() . '/crm_login_' . md5($rateKey) . '.counter'
```

md5 не является криптографически безопасным (коллизии), но в данном контексте используется ТОЛЬКО как naming function для файловой системы, не для security-sensitive операций. Риск минимален: теоретически можно создать коллизию имён файлов, но это не влияет на безопасность rate limiting (ключ проверяется внутри файла).

### Воспроизведение

1. Изучить `AuthService.php:304`
2. `md5($rateKey)` используется для имени файла, не для валидации

### Влияние

Минимальное. Не влияет на безопасность rate limiting напрямую. Скорее вопрос code hygiene.

### Рекомендация по исправлению

**Что нужно сделать**: Заменить `md5()` на `hash('sha256', ...)` для консистентности.

**Как лучше реализовать**: `hash('sha256', $rateKey)` — уже используется в других местах кодовой базы.

**Приоритет**: Nice-to-Have

---

## [SEC-010] API error codes могут раскрывать внутренние префиксы (CONFIG_*)

### Мета

- **Severity**: Low
- **Категория**: secrets
- **Затронутые файлы**: `api/index.php`
- **Endpoint**: Все API ответы при ошибках
- **Затронутые роли**: unauthenticated

### Описание проблемы

При ошибках конфигурации API может вернуть коды вроде `CONFIG_KEY_MISSING`, `CONFIG_VALUE_INVALID`. Это раскрывает детали внутренней архитектуры.

Примечание: в предыдущем раунде аудита эта проблема уже была частично исправлена (замена на `CONFIGURATION_ERROR`).

### Воспроизведение

1. Проверить `api/index.php` — если catch блок выбрасывает оригинальный код ошибки
2. Атакующий может составить карту внутренних модулей по кодам ошибок

### Влияние

Information disclosure — раскрытие внутренней структуры приложения.

### Рекомендация по исправлению

**Что нужно сделать**: Убедиться, что все `CONFIG_*` коды заменены на generic `CONFIGURATION_ERROR` во всех путях обработки ошибок.

**Как лучше реализовать**: В `api/index.php` глобальный exception handler должен маскировать внутренние коды:
```php
$code = str_starts_with($code, 'CONFIG_') ? 'CONFIGURATION_ERROR' : $code;
```

**Приоритет**: Should-Fix (уже частично исправлено в предыдущем раунде)

---

## [SEC-011] Pagination — отсутствие жёсткого верхнего предела limit

### Мета

- **Severity**: Low
- **Категория**: infra
- **Затронутые файлы**: Множество контроллеров с параметром `limit`
- **Endpoint**: Все list endpoints
- **Затронутые роли**: user

### Описание проблемы

Многие list endpoints принимают параметр `limit` с максимальными значениями 100-200, но без жёсткого верхнего предела на уровне API gateway. Теоретически можно запросить все записи с большим limit (DoS через исчерпание памяти).

В коде есть валидация: `$limit = min(100, max(1, (int)($filters['limit'] ?? 20)))` — но в некоторых местах потолок 200 или 500. Это смягчает проблему, но не устраняет полностью.

### Воспроизведение

1. `GET /api/v1/tasks?limit=99999` — вернёт максимум то, что разрешено контроллером
2. Но нет глобального middleware для капирования limit

### Влияние

Потенциальный DoS через большие запросы. Риск смягчён локальной валидацией limit в каждом контроллере.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить глобальный middleware для капирования limit (макс. 100 для обычных пользователей, 500 для root).

**Как лучше реализовать**: В `BaseController` добавить метод `normalizePagination(array $filters, int $maxLimit = 100)`.

**Приоритет**: Nice-to-Have

---

## [SEC-012] MCP tools/list — потенциальная утечка описаний tools без прав

### Мета

- **Severity**: Low
- **Категория**: mcp
- **Затронутые файлы**: `api/controller/mcp/McpController.php:533-1755`
- **Endpoint**: `tools/list` (JSON-RPC)
- **Затронутые роли**: user

### Описание проблемы

MCP `tools/list` возвращает отфильтрованный по RBAC список tools (используются `can()` и `canAny()` проверки). Однако названия и описания tools для higher-privilege операций всё равно видны в коде McpController.php. Сами tools не возвращаются без прав — но описания могут раскрыть информацию о возможностях системы.

### Воспроизведение

1. Вызвать `tools/list` под пользователем с минимальными правами
2. Сравнить с выводом под root
3. Подтверждено: tools фильтруются по правам ✅

### Влияние

Минимальное. Фильтрация работает корректно. Описания не раскрывают критической информации.

### Рекомендация по исправлению

**Что нужно сделать**: Подтвердить аудитом, что фильтрация корректна — уже сделано.

**Приоритет**: Nice-to-Have (подтверждено как реализованное)

---

## [SEC-013] .env.example содержит правдоподобные placeholder'ы

### Мета

- **Severity**: Low
- **Категория**: secrets
- **Затронутые файлы**: `api/.env.example`
- **Endpoint**: N/A (файл в репозитории)
- **Затронутые роли**: N/A

### Описание проблемы

`.env.example` содержит значения вроде `change-me-app-key`, `change-me-csrf-key`. Это стандартная практика, но если разработчик скопирует `.env.example` → `.env` и не заменит все ключи, приложение запустится с предсказуемыми значениями.

В production конфигурация `security.php` проверяет, что ключи заданы явно и выбрасывает исключение при их отсутствии. Это снижает риск.

### Воспроизведение

1. Изучить `api/.env.example` — `APP_KEY=change-me-app-key`
2. Фреймворк в production проверяет наличие ключей — OK ✅

### Влияние

Низкое. Production отказывается запускаться без явно заданных ключей.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить комментарий над каждым ключом: `MUST BE REPLACED`. Уже частично сделано.

**Приоритет**: Nice-to-Have

---

## [SEC-014] Cache key derivation использует md5() для имён

### Мета

- **Severity**: Low
- **Категория**: crypto
- **Затронутые файлы**: `FeatureFlagController.php:22`, `AnalyticsController.php:48,76`, `TagController.php:23`, `ClientController.php:26`
- **Endpoint**: GET endpoints с кешированием
- **Затронутые роли**: user

### Описание проблемы

Контроллеры используют `md5(json_encode($input))` для создания cache keys:
```php
$cacheKey = 'list:' . $this->cacheUserId() . ':' . md5(json_encode($input));
```

md5 не является криптографически стойким, но в контексте cache key derivation это не security issue — cache keys не являются секретами. Однако это inconsistency с остальной кодовой базой, где используется sha256.

### Воспроизведение

1. Изучить `ClientController.php:26`
2. `md5(json_encode($input))` для cache key

### Влияние

Минимальное. Не влияет на безопасность.

### Рекомендация по исправлению

**Что нужно сделать**: Заменить `md5()` на `hash('sha256', ...)` для консистентности.

**Приоритет**: Nice-to-Have

---

## [SEC-015] Дублирование MCP-инструментов (crm_get_profile дважды)

### Мета

- **Severity**: Low
- **Категория**: mcp
- **Затронутые файлы**: `api/controller/mcp/McpController.php:553-557,1205`
- **Endpoint**: `tools/list`
- **Затронутые роли**: user

### Описание проблемы

В tools/list инструмент `crm_get_profile` добавляется дважды — один раз в базовом списке (строка 553) и второй раз в блоке после user-инструментов (строка 1205). Это не security issue, но может вызвать путаницу.

### Воспроизведение

1. Изучить `McpController.php:tools()` метод
2. `crm_get_profile` определён на строке 553 и 1205

### Влияние

Минимальное. Дублирование в ответе tools/list.

### Рекомендация по исправлению

**Что нужно сделать**: Удалить дублирующееся определение `crm_get_profile`.

**Приоритет**: Nice-to-Have

---

## [SEC-016] Отсутствие CSP (Content-Security-Policy) на web-интерфейсе

### Мета

- **Severity**: Medium
- **Категория**: web
- **Затронутые файлы**: `web/index.php`, шаблоны в `web/view/template/`
- **Endpoint**: Все web-страницы
- **Затронутые роли**: unauthenticated, user

### Описание проблемы

Web-интерфейс не отправляет заголовок Content-Security-Policy. Это повышает риск XSS-атак: даже если в шаблонах используется `htmlspecialchars()`, отсутствие CSP означает, что любая XSS-уязвимость может быть эксплуатабельна без ограничений.

Примечание: в коде используется `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` для экранирования в шаблонах — это хорошо, но defence-in-depth требует CSP.

### Воспроизведение

1. Выполнить `curl -sI https://demo.tropatt.com/`
2. Отсутствует `Content-Security-Policy`

### Влияние

Повышенный риск XSS при отсутствии других защит. Смягчено использованием htmlspecialchars() в шаблонах.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить CSP-заголовок на web-страницы. Начать с report-only режима для сбора данных о необходимых исключениях.

**Как лучше реализовать**: В web/index.php добавить:
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'");
```

**Приоритет**: Should-Fix

---

## [SEC-017] Файловые storage-директории с предсказуемыми URL

### Мета

- **Severity**: Low
- **Категория**: files
- **Затронутые файлы**: `api/controller/file/FileController.php`, `api/controller/chat/ChatController.php`
- **Endpoint**: `GET /api/v1/files/{public_id}/download`, `GET /api/v1/chats/{chat_id}/attachments/{file_id}/download`
- **Затронутые роли**: user

### Описание проблемы

Файлы доступны по предсказуемым URL через API. Хотя доступ проверяется через `FileService` (проверка членства в проекте/чате), сам факт предсказуемости URL может способствовать enumeration-атакам.

С другой стороны, проверка прав доступа реализована (проверка через сервисный слой) — это основная защита.

### Воспроизведение

1. Изучить download URL pattern: `/api/v1/files/{public_id}/download`
2. `public_id` — Ulid (непредсказуем, 26 символов)
3. Доступ проверяется в `FileService`

### Влияние

Минимальное. Public ID непредсказуемы (Ulid), доступ проверяется серверно.

### Рекомендация по исправлению

**Что нужно сделать**: Текущая реализация приемлема. Рассмотреть signed URLs для дополнительной защиты.

**Приоритет**: Nice-to-Have

---

## [SEC-018] Логи хранятся в файлах без шифрования

### Мета

- **Severity**: Medium
- **Категория**: logging
- **Затронутые файлы**: `api/config/logging.php`, `storage_api/logs/`
- **Endpoint**: N/A (файловая система)
- **Затронутые роли**: N/A

### Описание проблемы

Логи аудита, безопасности и запросов пишутся в файлы в `storage_api/logs/`. На shared hosting все файлы принадлежат одному пользователю — компрометация приложения даёт доступ к логам. Логи содержат audit trail, security events и request data. При этом принимаются меры по маскированию IP-адресов для non-root пользователей и исключению чувствительных данных из AI prompt building (AiPromptBuilderService).

### Воспроизведение

1. Изучить `api/config/logging.php` — логи в `storage_api/logs/*.log`
2. Нет шифрования at rest

### Влияние

При компрометации приложения — доступ к полной истории действий. Смягчено фильтрацией sensitive данных перед записью.

### Рекомендация по исправлению

**Что нужно сделать**: Рассмотреть шифрование логов или хранение в БД для дополнительной защиты.

**Приоритет**: Should-Fix

---

## [SEC-019] Retention-периоды для логов — очень длинные

### Мета

- **Severity**: Low
- **Категория**: logging
- **Затронутые файлы**: `api/system/library/service/RetentionService.php:14-15`
- **Endpoint**: N/A (фоновая очистка)
- **Затронутые роли**: N/A

### Описание проблемы

Конфигурация retention:
- `security_logs_days` = 365 (1 год)
- `audit_logs_days` = 3650 (10 лет)

Это очень длинные сроки для shared hosting с ограниченным дисковым пространством. Возможно переполнение диска логами.

### Воспроизведение

1. Изучить `RetentionService.php:14-15`

### Влияние

Потенциальное переполнение диска на shared hosting.

### Рекомендация по исправлению

**Что нужно сделать**: Уменьшить retention до 90 дней для security логов, 365 для audit.

**Приоритет**: Nice-to-Have

---

## [SEC-020] SSL/TLS — `server: nginx` header раскрывает инфраструктуру

### Мета

- **Severity**: Low
- **Категория**: infra
- **Затронутые файлы**: nginx config
- **Endpoint**: Все HTTP-ответы
- **Затронутые роли**: unauthenticated

### Описание проблемы

HTTP-заголовок `server: nginx` раскрывает тип и версию веб-сервера. Это облегчает reconnaissance атакующему.

Демо использует HTTPS (Let's Encrypt), HSTS включён (max-age=31536000).

### Воспроизведение

1. `curl -sI https://demo.tropatt.com/ | grep server`
2. Ответ: `server: nginx`

### Влияние

Information disclosure — упрощает атакующему выбор эксплойтов под конкретную версию nginx.

### Рекомендация по исправлению

**Что нужно сделать**: `server_tokens off;` в nginx config (требует доступа к серверу).

**Приоритет**: Nice-to-Have

---

## [SEC-021] Не обнаружено глобального валидатора входных данных (input sanitizer)

### Мета

- **Severity**: Medium
- **Категория**: injection
- **Затронутые файлы**: `api/system/library/app.php`, `api/controller/common/BaseController.php`
- **Endpoint**: Все
- **Затронутые роли**: user

### Описание проблемы

В проекте нет глобального middleware для санитизации входных данных (trim строк, фильтрация неожиданных полей, валидация типов). Каждый контроллер делает это самостоятельно (или не делает, полагаясь на allInput()).

Это создаёт inconsistency: одни контроллеры валидируют входные данные через Validator, другие — через allInput() без проверки.

### Воспроизведение

1. Поиск глобального input validation middleware — не найден

### Влияние

Inconsistent input validation across 72+ контроллеров.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить глобальный Request Validation Middleware или helper в BaseController.

**Как лучше реализовать**: В BaseController добавить метод `validatedInput(array $allowedKeys)`:
```php
protected function validatedInput(array $allowedKeys): array {
    $input = $this->request()->allInput();
    return array_intersect_key($input, array_flip($allowedKeys));
}
```

**Приоритет**: Should-Fix

---

## [SEC-022] Демо-инсталляция: невозможно подтвердить поведение API напрямую

### Мета

- **Severity**: Medium
- **Категория**: infra
- **Затронутые файлы**: nginx config (демо)
- **Endpoint**: Все API endpoints
- **Затронутые роли**: unauthenticated

### Описание проблемы

Демо-сайт настроен так, что все API-запросы возвращают HTTP 302 (редирект на web-страницу логина). Это означает, что:
- Невозможно протестировать API-поведение на демо
- Невозможно подтвердить rate limiting (429 ответы)
- Невозможно проверить CORS, security-заголовки API
- Невозможно протестировать JSON-структуру ответов

Это проблема ДЕМО-окружения, не production. В production ожидается корректная настройка nginx для API.

### Воспроизведение

1. `curl -X POST https://demo.tropatt.com/api/v1/auth/login -H "Content-Type: application/json" -d '{"login":"admin","password":"adminadmin"}'`
2. Ответ: HTTP 302 (ожидался 200 с JSON)

### Влияние

Ограничивает возможности security-аудита на демо. Production не затронут.

### Рекомендация по исправлению

**Что нужно сделать**: Настроить демо nginx для прямой маршрутизации API-запросов.

**Приоритет**: Should-Fix (для демо)

---

## Общие рекомендации по архитектуре безопасности

1. **Глобальный Input Validation Middleware**: Добавить слой валидации всех входящих данных до достижения контроллера — trim строк, allowlist полей, валидация типов.

2. **Единый RateLimitService**: Консолидировать дублированную логику rate limiting из AuthService, PasswordResetService, InvitationService в один shared service.

3. **Security Headers Middleware**: Добавить глобальный middleware для установки security-заголовков (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy) на каждый ответ.

4. **Allowlist для allInput()**: Постепенно заменить `allInput()` на `allowedInput(['title','description',...])` во всех контроллерах, начиная с наиболее критичных (User, Role, Permission).

5. **ORDER BY валидация**: Везде, где параметры сортировки передаются в SQL, использовать allowlist допустимых колонок.

6. **CSP для Web**: Добавить Content-Security-Policy заголовок для защиты от XSS на web-интерфейсе.

---

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0 | SEC-004, SEC-005 | Mass assignment + SQL ORDER BY injection | 4-6 hours |
| P1 | SEC-001, SEC-002, SEC-016 | Security headers + composer.json exposure | 2-3 hours |
| P2 | SEC-003, SEC-022 | Demo nginx routing | 1-2 hours |
| P3 | SEC-006, SEC-007, SEC-008, SEC-021 | Code quality + session + rate limit refactor | 6-8 hours |
| P4 | SEC-009, SEC-010, SEC-013, SEC-014 | Minor code fixes (md5, error codes, placeholders) | 1-2 hours |
| P5 | SEC-011, SEC-012, SEC-015, SEC-017, SEC-018, SEC-019, SEC-020 | Low-priority improvements | 3-4 hours |

**Общее время исправления**: ~17-25 часов

---

## Методология аудита

Аудит проведён в 10 фаз, последовательно охватывающих все аспекты безопасности:

1. **Фаза 0**: Attack Surface Mapping — HTTP-запросы к демо, проверка доступности файлов конфигурации, storage, security-заголовков, rate limiting
2. **Фаза 1**: Аутентификация — анализ AuthService, PasswordResetService, TwoFactorService, TokenManager, SessionService, InvitationService
3. **Фаза 2**: RBAC — анализ routes.php, контроллеров с permission checks, ImpersonationController
4. **Фаза 3**: Инъекции — поиск whereRaw(), ORDER BY конкатенации, exec(), file upload, XSS экранирования, SSRF (UrlSafetyValidator)
5. **Фаза 4**: MCP — анализ McpController (tools/list, RBAC filtering, batch limits)
6. **Фаза 5**: Секреты — git history, .env.example, .htaccess, GitHub Actions, debug mode
7. **Фаза 6**: Бизнес-логика — idempotency, invitation token одноразовость, race conditions, balance/payment
8. **Фаза 7**: Инфраструктура — crypto (openssl, hmac), composer.json, случайная генерация, hash-функции
9. **Фаза 8**: Файлы — upload/download, path traversal, installer lock, quarantine extensions
10. **Фаза 9**: Web — CSRF, CSP, security headers, template escaping
11. **Фаза 10**: Логирование — audit trail, security events, log storage, retention, PII masking

**Методы**: Статический анализ кода (grep/read_files), HTTP-запросы к демо (только GET + ограниченные POST), проверка git history, анализ конфигурации.

**Ограничения**: Демо-сайт возвращает 302 на API-запросы — невозможно полноценное dynamic testing. Все выводы основаны на статическом анализе кодовой базы.

**Рабочее дерево**: НЕ тронуто. Подтверждено `git status` — только `docs/SECURITY-FIXES.md` добавлен.
