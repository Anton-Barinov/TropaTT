# Security Audit — Fixes Specification

> Дата аудита: 2026-07-12
> Версия проекта: (из VERSION)
> Аудитор: AI Security Audit

## Статус

Все 9 находок обработаны. Файл очищен для нового цикла анализа.

### Выполненные исправления

| # | Severity | Статус | Описание |
|---|----------|--------|----------|
| SEC-001 | 🔴 High | ✅ Fixed | Installer error messages не раскрывают credentials; INSTALL_BOOTSTRAP_SECRET генерируется случайным |
| SEC-002 | 🔴 High | ✅ Fixed | PHP guard в composer.json для блокировки доступа по HTTP |
| SEC-003 | 🟠 Medium | ✅ Fixed | CSP: убран `unsafe-eval`, всегда enforce режим |
| SEC-004 | 🟠 Medium | ✅ Fixed | Password reset validation унифицирована (12+ chars, complexity) |
| SEC-005 | 🟠 Medium | ✅ Fixed | INSTALL_BOOTSTRAP_SECRET = bin2hex(random_bytes(16)) |
| SEC-006 | 🟡 Low | ✅ Fixed | frame-ancestors统一 в `'none'` на web стороне |
| SEC-007 | 🟡 Low | ⚠️ Note | Session fingerprint — рекомендовано для след. цикла |
| SEC-008 | 🟡 Low | ✅ Fixed | Совпадает с SEC-005 — исправлено |
| SEC-009 | 🟡 Low | ⚠️ Note | MigrationController не найден в кодовой базе |

### Файлы изменены

- `web/install.php` — testDatabaseConnection() log fix, bootstrap secret generation
- `api/system/library/service/PasswordResetService.php` — unified password validation
- `web/index.php` — CSP hardening (no unsafe-eval, frame-ancestors 'none', enforce)
- `api/composer.json` — PHP guard for nginx users

---
