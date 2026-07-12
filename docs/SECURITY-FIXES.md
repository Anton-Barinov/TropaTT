# Security Audit — Ready for Fresh Analysis

> **Дата очистки**: 2026-07-12
> **Предыдущие находки**: Все исправлены или задокументированы

Файл очищен для запуска нового анализа безопасности.

## История исправлений

| Раунд | Что сделано | Коммит |
|-------|-------------|--------|
| Round 1 | PasswordResetService password length validation, api/index.php CONFIG_* masking | `29ee8c4` |
| Round 2 | md5→sha256 in 5 files (AuthService, BaseController, PasswordResetService, InvitationService, web/index.php) + cache keys in 20+ controllers, validatedInput() helper, MCP duplicate removal, retention reduction | `3771f3f` |

## Остаётся для nginx/инфраструктуры

- `composer.json` HTTP 200 на демо (требует nginx)
- HTTP 302 на API запросы (требует nginx)
- `server: nginx` header (требует nginx)
