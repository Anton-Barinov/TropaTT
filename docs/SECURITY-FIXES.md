# Security Audit — Fixes Specification

> Дата аудита: 2025-06-25
> Версия проекта: 1.0.0
> Аудитор: AI Security Audit

## Сводка

| Severity (CVSS) | Количество |
|-----------------|------------|
| Critical (9.0–10.0) | 0          |
| High (7.0–8.9)     | 3          |
| Medium (4.0–6.9)   | 1          |
| Low (0.1–3.9)      | 0          |
| **Итого**          | **4**     |

## Risk Heatmap (по категориям OWASP Top 10)

| OWASP Category | Critical | High | Medium | Low |
|----------------|----------|------|--------|-----|
| A01: Broken Access Control | | 1 | | |
| A03: Injection | | 1 | 1 | |
| A10: SSRF | | 1 | | |
| **Итого** | 0 | 3 | 1 | 0 |

---

## [SEC-001] Server-Side Request Forgery (SSRF) при установке модулей по URL

### Мета

- **Severity**: High
- **CVSS**: 7.2 (AV:N/AC:L/PR:H/UI:N/S:U/C:H/I:L/A:L)
- **CWE-ID**: CWE-918
- **OWASP**: A10:2021 — Server-Side Request Forgery (SSRF)
- **Категория**: ssrf
- **Затронутые файлы**: `api/controller/module/ModuleController.php:287`
- **Endpoint/tool**: `POST /api/v1/modules/install-from-url`
- **Затронутые роли**: authenticated user with `settings.manage`

### Описание проблемы

Метод `ModuleController::installFromUrl()` принимает URL из пользовательского ввода и напрямую передаёт его в `ModuleRemoteInstaller::installFromUrl()` без валидации. В проекте уже существует `UrlSafetyValidator`, который используется для вебхуков, но он не применяется при установке модулей.

```php
public function installFromUrl(array $params = []): JsonResponse
{
    $input = $this->request()->allInput();
    $url = trim((string)($input['url'] ?? $params['url'] ?? ''));
    // ...
    $installer = new ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);
    $name = $installer->installFromUrl($url, true);
    // ...
}
```

### Воспроизведение

1. Авторизоваться пользователем с правом `settings.manage`.
2. Выполнить запрос:
   ```http
   POST /api/v1/modules/install-from-url
   Content-Type: application/json
   Authorization: Bearer <token>

   {"url": "http://127.0.0.1:8080/internal-api"}
   ```
3. Сервер выполнит HTTP-запрос к указанному внутреннему адресу.

### Влияние

- Исследование внутренней сети (network reconnaissance).
- Чтение облачных метаданных (например, `http://169.254.169.254/` на AWS/GCP/Azure).
- Атаки на локальные сервисы (Redis, MySQL, внутренние API).

### Рекомендация по исправлению

**Must-Fix**

1. В `ModuleController::installFromUrl()` создать экземпляр `UrlSafetyValidator`.
2. Вызвать `validateProviderUrl($url, true)` до передачи URL в `ModuleRemoteInstaller`.
3. При неудаче возвращать ошибку 422 с понятным сообщением.

Пример:

```php
use Api\System\Library\Security\UrlSafetyValidator;

public function installFromUrl(array $params = []): JsonResponse
{
    $input = $this->request()->allInput();
    $url = trim((string)($input['url'] ?? $params['url'] ?? ''));
    if ($url === '') {
        return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.url_required'), 400);
    }

    $validator = new UrlSafetyValidator();
    $validation = $validator->validateProviderUrl($url, true);
    if (!$validation['ok']) {
        return JsonResponse::error('INVALID_URL', $validation['message'] ?? 'Unsafe URL', 422);
    }

    // ... existing install logic with $validation['url']
}
```

---

## [SEC-002] Эскалация привилегий через Mass Assignment в UserController::update

### Мета

- **Severity**: High
- **CVSS**: 8.8 (AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H)
- **CWE-ID**: CWE-915
- **OWASP**: A01:2021 — Broken Access Control
- **Категория**: rbac
- **Затронутые файлы**: `api/controller/user/UserController.php:101`
- **Endpoint/tool**: `PATCH /api/v1/users/{public_id}`
- **Затронутые роли**: authenticated user with `user.manage`

### Описание проблемы

В методе `UserController::update()` список разрешённых для обновления полей включает `is_root` и `role_public_ids`:

```php
$input = $this->validatedInput([
    'email', 'full_name', 'locale', 'cost_rate', 'bill_rate',
    'is_active', 'password', 'token', 'is_root', 'role_public_ids'
]);
```

Хотя бизнес-логика в `UserService` может частично проверять права, сам факт передачи этих полей в `validatedInput` на уровне контроллера позволяет любому пользователю с правом `user.manage` попытаться повысить свои привилегии или изменить роли других пользователей.

### Воспроизведение

1. Авторизоваться как менеджер с правом `user.manage`, но без флага `is_root`.
2. Выполнить запрос:
   ```http
   PATCH /api/v1/users/{own_public_id}
   Content-Type: application/json
   Authorization: Bearer <token>

   {"is_root": 1}
   ```
3. Если сервисный слой не отклоняет изменение, пользователь получает root-права.

### Влияние

- Полная компрометация модели RBAC.
- Скрытая эскалация привилегий до суперадминистратора.
- Возможность изменения ролей и доступа к административным функциям.

### Рекомендация по исправлению

**Must-Fix**

1. В `UserController::update()` удалять `is_root` и `role_public_ids` из входных данных, если текущий пользователь не `is_root`.
2. Альтернативно — в `UserService::update()` явно проверять, что только `is_root` может изменять эти поля, и возвращать 403.

Пример в контроллере:

```php
$input = $this->validatedInput([
    'email', 'full_name', 'locale', 'cost_rate', 'bill_rate',
    'is_active', 'password', 'token'
]);

$auth = $this->user();
if (!$auth || empty($auth['user']['is_root'])) {
    unset($input['is_root'], $input['role_public_ids']);
}
```

---

## [SEC-003] SQL Injection через необработанные имена столбцов в QueryBuilder::groupBy

### Мета

- **Severity**: High
- **CVSS**: 8.5 (AV:N/AC:H/PR:L/UI:N/S:C/C:H/I:H/A:H)
- **CWE-ID**: CWE-89
- **OWASP**: A03:2021 — Injection
- **Категория**: injection
- **Затронутые файлы**: `api/system/library/database/builder/QueryBuilder.php:127`
- **Endpoint/tool**: любой endpoint, использующий `groupBy()`
- **Затронутые роли**: зависит от endpoint

### Описание проблемы

В `QueryBuilder` метод `orderBy()` валидирует имя колонки регулярным выражением, но метод `groupBy()` не делает этого:

```php
public function groupBy(string|array $columns): self
{
    $items = is_array($columns) ? $columns : [$columns];
    foreach ($items as $column) {
        $trimmed = trim($column);
        if ($trimmed !== '') {
            $this->groups[] = $trimmed;
        }
    }
    return $this;
}
```

Значения напрямую попадают в SQL-запрос через `toSql()`:

```php
if ($this->groups !== []) {
    $sql .= ' GROUP BY ' . implode(', ', $this->groups);
}
```

Если пользовательский ввод попадёт в `groupBy()`, возникнет SQL-инъекция.

### Воспроизведение

1. Найти endpoint, который передаёт пользовательский параметр в `QueryBuilder::groupBy()`.
2. Подать значение вида `status_code; DROP TABLE users;--`.
3. Вредоносный SQL будет включён в запрос.

### Влияние

- Утечка, изменение или удаление данных в БД.
- На shared-хостинге с единым пользователем БД с полными правами — максимальный ущерб.

### Рекомендация по исправлению

**Must-Fix**

Добавить валидацию имени колонки в `groupBy()`, аналогичную `orderBy()`:

```php
public function groupBy(string|array $columns): self
{
    $items = is_array($columns) ? $columns : [$columns];
    foreach ($items as $column) {
        $trimmed = trim($column);
        if ($trimmed !== '') {
            if (!preg_match('/^[a-zA-Z0-9_.`]+$/', $trimmed)) {
                throw new \InvalidArgumentException('Invalid column name in GROUP BY: ' . $trimmed);
            }
            $this->groups[] = $trimmed;
        }
    }
    return $this;
}
```

---

## [SEC-004] Инъекция переменных окружения через параметры инсталлятора

### Мета

- **Severity**: Medium
- **CVSS**: 5.3 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N)
- **CWE-ID**: CWE-113
- **OWASP**: A03:2021 — Injection
- **Категория**: injection
- **Затронутые файлы**: `web/install.php` (функция `writeEnvFile`)
- **Endpoint/tool**: `POST web/install.php` (AJAX-шаг установки)
- **Затронутые роли**: unauthenticated (installer flow)

### Описание проблемы

Функция `writeEnvFile()` в инсталляторе конкатенирует пользовательские данные в `.env` без удаления символов переноса строки:

```php
$envContent .= "APP_TIMEZONE=" . ($data['timezone'] ?? 'Europe/Moscow') . "\n\n";
```

Если в поле `timezone` (или других полей, например `db_host`) передать строку с `\n`, можно внедрить дополнительные директивы в `.env`.

### Воспроизведение

1. Перехватить AJAX-запрос на шаге сохранения конфигурации установщика.
2. В поле `timezone` передать:
   ```
   Europe/Moscow
   APP_KEY=attacker_known_key
   ```
3. В сгенерированном `.env` появится дополнительная директива, которая может переопределить секрет.

### Влияние

- Установка предсказуемых или известных секретов (`APP_KEY`, `CSRF_SECRET_KEY` и др.).
- Возможность генерации валидных токенов/подписей после установки.

### Рекомендация по исправлению

**Should-Fix**

1. Создать вспомогательную функцию для очистки значений перед записью в `.env`.
2. Удалять символы переноса строки (`\r`, `\n`) и начальные/конечные пробелы.
3. Применять её ко всем значениям в `writeEnvFile()`.

Пример:

```php
function sanitizeEnvValue(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

// В writeEnvFile:
$envContent .= "APP_TIMEZONE=" . sanitizeEnvValue($data['timezone'] ?? 'Europe/Moscow') . "\n\n";
```

---

## Общие рекомендации по архитектуре безопасности

1. **Единый подход к валидации URL**: все внешние URL (вебхуки, установка модулей, проверка обновлений) должны проходить через `UrlSafetyValidator`.
2. **Mass Assignment**: вводить явные allowlists на уровне контроллеров, особенно для чувствительных полей (`is_root`, `role_public_ids`, `permissions`).
3. **QueryBuilder**: применять валидацию имён колонок во всех методах, которые вставляют идентификаторы в SQL (`groupBy`, `orderByRaw`, `whereRaw` и др.).
4. **Installer hardening**: санитизировать все значения, записываемые в `.env`, и рассмотреть возможность генерации секретов на сервере, а не принимать их из формы.

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0 | SEC-001, SEC-002, SEC-003 | SSRF в установке модулей, эскалация привилегий, SQLi в groupBy | 4–8 часов |
| P1 | SEC-004 | Санитизация значений в инсталляторе | 1–2 часа |

## Методология аудита

Аудит проводился методом статического анализа кода (defensive code review) без выполнения активных эксплойтов. Проверены ключевые компоненты: аутентификация, RBAC, валидация ввода, SQL-запросы, загрузка файлов, MCP, модули, вебхуки, инсталлятор и логирование. Выявленные проблемы верифицированы путём чтения соответствующих файлов.

## Security Metrics & KPIs

| Метрика | Цель | Как измерять |
|---------|------|--------------|
| Critical/High issues | = 0 после исправления | Количество SEC-XXX с Severity High/Critical |
| Time to fix High | ≤ 7 дней | Время между находкой и исправлением |
| Open issues | ≤ 5 (только Low) | Количество неисправленных SEC-XXX |
| Re-audit cadence | Каждые 3 месяца | Периодичность полного аудита |
| SAST in CI | 100% PR проходят PHPStan level 6 | % PR с запущенным PHPStan |
