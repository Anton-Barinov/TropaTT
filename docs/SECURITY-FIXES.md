# Security Audit — Fixes Specification

> Ready for new analysis. Clear for next audit cycle.

---

## Неисправленные находки (требуют nginx config)

| SEC | Severity | Проблема | Причина |
|-----|----------|----------|---------|
| SEC-001 | Low | composer.json HTTP 200 | ⚠️ Не исправлено — требует nginx config (deny all) на сервере |
| SEC-002 | Low | Demo API HTTP 302 | ⚠️ Не исправлено — требует nginx config (API routing через api/index.php) |

### nginx config для применения на сервере:

```nginx
# Блокировка composer.json/composer.lock
location ~* ^/(api/)?(composer\.(json|lock)|\.\..*)$ {
    deny all;
}

# Маршрутизация API через PHP
location /api/ {
    try_files $uri /api/index.php?$query_string;
}
```
