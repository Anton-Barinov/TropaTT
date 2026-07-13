# Security Audit — Fixes Specification

> Дата завершения: 2025-07-13
> Статус: все находки из предыдущего аудита реализованы и проверены

## Реализованные исправления

| SEC | Описание | Файлы | Статус |
|-----|----------|-------|--------|
| SEC-001 | SSRF при установке модулей по URL | `api/controller/module/ModuleController.php` | ✅ Исправлено |
| SEC-002 | Mass assignment / эскалация привилегий в `UserController::update` | `api/controller/user/UserController.php` | ✅ Исправлено |
| SEC-003 | SQL Injection через `QueryBuilder::groupBy` | `api/system/library/database/builder/QueryBuilder.php` | ✅ Исправлено |
| SEC-004 | Инъекция переменных окружения через инсталлятор | `web/install.php` | ✅ Исправлено |

## Краткое описание изменений

- **SEC-001**: в `ModuleController::installFromUrl()` добавлена проверка URL через `UrlSafetyValidator` до передачи URL в `ModuleRemoteInstaller`.
- **SEC-002**: в `UserController::update()` поля `is_root` и `role_public_ids` удаляются из входных данных для не-root пользователей.
- **SEC-003**: в `QueryBuilder::groupBy()` добавлена валидация имён колонок регулярным выражением, аналогичная `orderBy()`.
- **SEC-004**: в `web/install.php` добавлена функция `sanitizeEnvValue()` и применена ко всем значениям, записываемым в `.env`.

## Проверка

- PHP синтаксис: пройден для всех изменённых файлов.
- Деплой: изменения запушены и задеплоены на `demo.tropatt.com`.
- Регрессионные проверки: логин, создание задачи, IDOR, XSS — пройдены.
- Targeted проверки: SSRF (internal URL, localhost) — блокируется; mass assignment — защищён; кодовые фиксы SEC-003 и SEC-004 — присутствуют.

---

*Файл очищен для запуска нового аудита.*
