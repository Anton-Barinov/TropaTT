# Security Audit — Fixes Specification

> Ready for new analysis. Clear for next audit cycle.

---

## Предыдущий цикл (завершён)

| SEC | Severity | Проблема | Статус |
|-----|----------|----------|--------|
| SEC-001 | Low | composer.json HTTP 200 | ⚠️ Не исправлено — требует nginx config |
| SEC-002 | Low | Demo API HTTP 302 | ⚠️ Не исправлено — требует nginx config |

### Что сделано в предыдущем цикле

**PHP-исправления (все выполнены, закоммичены, задеплоены):**
- `hash('sha256')` вместо `md5()` в хешировании данных
- `validatedInput()` в UserController, RoleController, ImpersonationController для защиты от mass assignment
- `session_regenerate_id` в installer после логина
- Rate limits перемещены из `/tmp` в `storage_api/cache/rate_limits`
- Non-production секреты заменены на `random_bytes(32)`
- Retention периоды уменьшены
- **Консолидация RateLimitService**: AuthService, PasswordResetService, InvitationService, BaseController — все используют единый `RateLimitService` вместо дублирующихся file-based реализаций

**nginx-уровень (не исправлено, требуется доступ к серверу):**
- `api/.htaccess` уже блокирует `.json` и `.lock` файлы (работает на Apache)
- На демо стоит nginx — `.htaccess` игнорируется
- PHP-код не может повлиять на nginx routing

**30+ security controls подтверждены:** Argon2id, CSRF HMAC-SHA256, rate limiting, CSP, X-Frame-Options, HSTS, session_regenerate_id, validatedInput, KeyGuard, audit logging, RBAC, MCP permissions, и другие.
