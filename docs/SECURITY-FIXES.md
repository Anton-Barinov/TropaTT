# Security Audit — Fixes Specification

> Дата аудита: 2026-07-12
> Версия проекта: 2.24.0+
> Аудитор: AI Security Audit (Phase 2–10)

## Сводка

| Severity | Количество |
|----------|------------|
| Critical | 0 |
| High     | 1 |
| Medium   | 3 |
| Low      | 2 |
| **Итого** | **6** |

## Risk Heatmap

| Категория          | Critical | High | Medium | Low |
|--------------------|----------|------|--------|-----|
| rbac               |          |      | 2      |     |
| installer          |          | 1    |        |     |
| business-logic     |          |      | 1      |     |
| infra              |          |      |        | 1   |
| shared-hosting     |          |      |        | 1   |

---

## [SEC-010] Installer error messages раскрывают пути файловой системы при ошибках записи

### Мета

- **Severity**: High
- **Категория**: installer
- **Затронутые файлы**: `web/install.php:2588-2595`
- **Endpoint/tool**: `web/install.php` (browser installer)
- **Затронутые роли**: unauthenticated (pre-installation)

### Описание проблемы

Installer при ошибке записи lock-файла или директории storage возвращает полный путь файловой системы в сообщении об ошибке:

```php
throw new RuntimeException("Cannot write lock file: {$storageLock}. Check that the PHP process has write permission...");
```

На shared хостинге пути вида `/home/user/data/www/domain.com/storage_api/install.lock` раскрывают внутреннюю структуру хостинга. Хотя это происходит только на этапе установки (до создания admin-аккаунта), информация может быть использована для разведки.

### Воспроизведение

1. Открыть `web/install.php` на свежей установке
2. На шаге проверки требований (requirements check) смотреть на вывод ошибок при неудачной записи
3. Если PHP не может писать в storage, ошибка содержит полный путь

### Влияние

Раскрытие путей файловой системы — информация для разведки. На shared хостинге может раскрыть реальное расположение пользователя, что упрощает атаки на соседние сайты (cross-tenant атаки).

### Рекомендация по исправлению

**Что нужно сделать**: Заменить прямые исключения с путями в `throw new RuntimeException()` на логирование через `error_log()` и возврат пользователю обобщённого сообщения.

**Как лучше реализовать**:
- В `web/install.php` (строки 2588-2595): обернуть `file_put_contents()` в try-catch, в catch логировать через `error_log('[Installer] ' . $e->getMessage())` и возвращать `t('install/messages.storage_write_error')` или аналогичную языковую строку
- В строках с mkdir: аналогично логировать ошибку, показывать `t('install/messages.dir_create_error')`

**Приоритет**: Must-Fix

---

## [SEC-011] Estimate и Sticky endpoints не имеют `required_permissions`

### Мета

- **Severity**: Medium
- **Категория**: rbac
- **Затронутые файлы**: `api/config/routes.php:387-416` (estimate), `api/config/routes.php:421-443` (sticky notes), `api/config/routes.php:446-464` (notifications)
- **Endpoint/tool**: 
  - `GET/POST /api/v1/estimate-sets` и все подресурсы
  - `GET/POST /api/v1/sticky-notes` и все подресурсы
  - `GET/POST /api/v1/notifications` и все подресурсы
- **Затронутые роли**: authenticated user (любой авторизованный пользователь)

### Описание проблемы

Эндпоинты для управления estimate-sets, sticky notes и notifications имеют `auth: true`, но НЕ имеют `required_permissions`. Это означает, что любой авторизованный пользователь (даже с минимальными правами) может:

- Создавать/редактировать/удалять estimate sets и options
- Создавать/редактировать/удалять sticky notes (включая чужие?)
- Создавать/читать notification'ы

В то время как соседние endpoint'ы (tasks, projects, teams) имеют соответствующие `required_permissions`, эти endpoint'ы полагаются только на наличие токена.

При этом в контроллерах может быть дополнительная проверка (object-level authorization), но на уровне route permission её нет — первый барьер защищает только от неавторизованных запросов.

### Воспроизведение

1. Залогиниться как `admin` (или любой пользователь с `user.view`/`task.manage`)
2. Получить токен
3. Выполнить `POST /api/v1/sticky-notes` с телом `{"title": "test note"}` — запрос должен пройти (201)
4. Выполнить `POST /api/v1/estimate-sets` с телом `{"name": "test set", "unit": "hours"}` — запрос должен пройти (201)

### Влияние

Любой пользователь может создавать/удалять estimates и sticky notes, независимо от роли. Это нарушение RBAC-модели. Sticky notes могут содержать чувствительные заметки, к которым пользователь с минимальными правами не должен иметь доступ.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить `required_permissions` ко всем endpoint'ам estimate, sticky-notes и notifications в `api/config/routes.php`.

**Как лучше реализовать**:
- Для estimate: `required_permissions' => ['task.manage']` (как у всех task-связанных endpoint'ов)
- Для sticky-notes: `required_permissions' => ['task.manage']`
- Для notifications: проверка уже может быть на уровне сервиса — `required_permissions' => ['task.manage']` или специфическая `['notification.manage']`

**Приоритет**: Must-Fix

---

## [SEC-012] noValidate: web/.htaccess не блокирует `.env` явно (только через SPA routing)

### Мета

- **Severity**: Medium
- **Категория**: shared-hosting
- **Затронутые файлы**: `web/.htaccess`
- **Затронутые роли**: unauthenticated

### Описание проблемы

`web/.htaccess` не содержит явной блокировки доступа к `.env` файлам. Файл `.env` находится в корне проекта (`/Users/bps/sites/crm.ru/.env`), то есть на уровень выше `web/` и `api/`. На демо-сайте возвращается 404, но это потому что SPA routing (через `index.php`) не находит файл.

На shared хостинге, где `web/` является document root:
- `.env` находится выше document root → недоступен (OK)
- Но если кто-то случайно разместит `.env` в `web/` (что документировано в installer как возможность), файл будет доступен

Проблема в том, что нет defence-in-depth — если SPA routing сломается или будет обойдён, `.env` станет доступен.

### Воспроизведение

1. `curl -s -o /dev/null -w "%{http_code}" https://demo.tropatt.com/.env` → 404 (через SPA)
2. Но в случае сбоя SPA или прямого доступа через другой URL — может быть 200

### Влияние

Потенциальная утечка секретов (.env содержит APP_KEY, DB credentials, API keys) при неправильной конфигурации сервера.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить блокировку `.env` и файлов с точкой (`.*`) в `web/.htaccess`.

**Как лучше реализовать**:
В `web/.htaccess`, после директив кэширования, добавить:

```apache
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<FilesMatch "\.env">
    Require all denied
</FilesMatch>
```

Или через mod_rewrite:
```apache
RewriteRule ^\.env - [F,L]
RewriteRule (.*)\.env$ - [F,L]
```

**Приоритет**: Should-Fix

---

## [SEC-013] Отсутствие лимитов пагинации на bulk/batch endpoints

### Мета

- **Severity**: Medium
- **Категория**: business-logic
- **Затронутые файлы**: `api/controller/task/TaskController.php` (bulkUpdate), `api/config/routes.php` (bulk endpoints)
- **Endpoint/tool**: `POST /api/v1/tasks/bulk`
- **Затронутые роли**: authenticated user with `task.manage`

### Описание проблемы

Bulk-эндпоинт `/api/v1/tasks/bulk` не имеет задокументированного лимита на количество элементов в одном запросе. Если контроллер не проверяет размер входного массива, пользователь может отправить запрос с тысячами элементов, что может привести к:
- Исчерпанию памяти PHP
- Длительному времени выполнения запроса (блокировка других запросов)
- Отказу в обслуживании (DoS)

### Воспроизведение

1. Получить токен
2. Отправить `POST /api/v1/tasks/bulk` с массивом из 10000+ задач
3. Наблюдать длительное время ответа или ошибку 500 (memory exhausted)

### Влияние

Потенциальный DoS-вектор через массовые операции. На shared хостинге с ограничением памяти 128MB это может привести к падению PHP процесса.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить проверку количества элементов во входном массиве в контроллере bulkUpdate.

**Как лучше реализовать**:
В `api/controller/task/TaskController.php`, в методе `bulkUpdate()`:
- Проверить `count($tasks)` в начале метода
- Если > 100 (или другого разумного лимита) — вернуть ошибку 400 с кодом `TOO_MANY_ITEMS`
- Использовать конфигурируемый лимит из `api/config/security.php`

**Приоритет**: Should-Fix

---

## [SEC-014] Server header раскрывает nginx

### Мета

- **Severity**: Low
- **Категория**: infra
- **Endpoint/tool**: все
- **Затронутые роли**: unauthenticated

### Описание проблемы

HTTP-заголовок `server: nginx` раскрывает тип и версию веб-сервера. На shared хостинге невозможно убрать этот заголовок (требует доступа к nginx.conf). Это не уязвимость, а отступление от best practice.

### Воспроизведение

```bash
curl -sI https://demo.tropatt.com/ | grep -i server
# → server: nginx
```

### Влияние

Минимальное — информация о версии nginx может быть использована для поиска уязвимостей под конкретную версию. Низкий риск, так как shared hosting обычно обновляет nginx автоматически.

### Рекомендация по исправлению

**Что нужно сделать**: Невозможно исправить на shared хостинге (требует доступа к конфигурации сервера). Это accepted risk.

**Приоритет**: Nice-to-Have (зафиксировать в документации как accepted risk)

---

## [SEC-015] Installer lock file не атомарен — race condition

### Мета

- **Severity**: Low
- **Категория**: installer
- **Затронутые файлы**: `web/install.php:2588-2595`
- **Endpoint/tool**: `web/install.php`
- **Затронутые роли**: unauthenticated

### Описание проблемы

Создание lock-файла (`file_put_contents(LOCK_FILE_PATH, $now)`) не использует блокировку `LOCK_EX`. Если два пользователя одновременно запустят установку, возможна ситуация:

1. Пользователь A проходит проверку `is_file(LOCK_FILE_PATH)` — файла нет
2. Пользователь B проходит проверку `is_file(LOCK_FILE_PATH)` — файла нет
3. Оба начинают установку
4. Один перезаписывает lock-файл другого
5. Данные могут быть повреждены (две БД в разных состояниях)

### Воспроизведение

1. Отправить два параллельных `POST /install/setup` запроса
2. Оба могут пройти и создать дублирующиеся данные

### Влияние

Низкая вероятность на практике (установка — одноразовое событие). Но при автоматизированной установке может привести к повреждению БД.

### Рекомендация по исправлению

**Что нужно сделать**: Добавить блокировку `LOCK_EX` при записи lock-файла, или использовать атомарную операцию `file_put_contents` с эксклюзивной блокировкой.

**Как лучше реализовать**:
В `web/install.php`:
```php
$fh = fopen(LOCK_FILE_PATH, 'x'); // атомарное создание
if ($fh === false) {
    throw new RuntimeException("System already being installed by another process");
}
fwrite($fh, $now);
fclose($fh);
```

Использование флага `'x'` в `fopen()` гарантирует, что файл будет создан только если его не существует. Это предотвращает race condition.

**Приоритет**: Nice-to-Have

---

## Общие рекомендации по архитектуре безопасности

1. **RBAC consistency**: Все API-endpoint'ы должны иметь `required_permissions`. Endpoint'ы с `auth: true` без `required_permissions` — это потенциальный вектор для privilege escalation.

2. **Shared hosting .htaccess defence-in-depth**: Все уровни (.env, storage, config) должны быть защищены .htaccess как минимум блокировкой FilesMatch для dot-files и конфиденциальных расширений.

3. **Bulk operation limits**: Каждый bulk/batch endpoint должен иметь явный лимит на количество элементов и проверку на исчерпание ресурсов (memory, time).

## Порядок исправления (Roadmap)

| Приоритет | SEC-коды | Описание | Ожидаемое время |
|-----------|----------|----------|-----------------|
| P0 | SEC-010 | Installer error paths | 15 min |
| P1 | SEC-011 | RBAC permissions for estimate/sticky/notification | 30 min |
| P2 | SEC-012 | .htaccess .env blocking | 10 min |
| P2 | SEC-013 | Bulk endpoint limits | 30 min |
| P3 | SEC-014 | Server header documentation | 5 min |
| P3 | SEC-015 | Installer lock file atomicity | 15 min |

## Методология аудита

Проверены фазы 2-10 из security audit checklist:
- **Phase 2**: RBAC/IDOR — проверены route permissions для всех endpoint'ов, проверена Impersonation, UserController, MigrationController
- **Phase 3**: Input validation/SQL injection/XSS — проверен QueryBuilder на whereRaw/orderByRaw, FileController/chat uploads, IdeaController raw SQL
- **Phase 4**: MCP — проверены в предыдущем цикле
- **Phase 5**: Secrets — проверен git history для .env, проверены config файлы
- **Phase 6**: Business logic — проверены bulk endpoints, work cycles, balance
- **Phase 6.1**: Module system — проверен installFromUrl, Filesystem sandbox
- **Phase 6.2**: AI — проверен ai config, feature flags
- **Phase 6.3**: Notifications — проверены push subscriptions
- **Phase 7**: Dependencies — проверен composer.json, jQuery/Bootstrap versions, .htaccess
- **Phase 7.1**: Cryptography — проверен password_hash (ARGON2ID), token generation (random_bytes), encryption (AES-256-GCM)
- **Phase 7.2**: Rate limiting — проверен конфиг (auth_login: 15/60s)
- **Phase 7.3**: Installer — проверен lock file, error messages, CSRF
- **Phase 7.4**: Update mechanism — проверен CoreUpdateController, routes
- **Phase 7.5**: Shared hosting — проверены server header, .htaccess limitations
- **Phase 8**: Files/storage — проверены .htaccess, direct access
- **Phase 9**: Web UI — проверены CSP headers, X-Frame-Options, open redirect
- **Phase 10**: Audit logging — проверены log channels, mask_keys

**Ограничения аудита**: Аудит проводился в read-only режиме без деструктивных тестов. Некоторые проверки (race conditions, параллельные запросы) не выполнялись на живом демо-сервере.
